<?php
// ================================================================
// FILE: frontend/pages/admin/visit_details.php
// VISIT DETAILS - CARD STYLE VIEW
// BRAICK DISPENSARY - COMPLETE REKEBISHWA
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Admin Only
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database and helpers
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// VARIABLES
// ================================================================
$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($visit_id <= 0) {
    header('Location: visits.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET VISIT DATA
// ================================================================
$stmt = $db->prepare("
    SELECT v.*, 
           p.id as patient_id, p.full_name as patient_name, p.patient_id as patient_number,
           p.phone as patient_phone, p.email as patient_email,
           p.gender, p.date_of_birth, p.blood_group, p.allergies,
           u.id as doctor_id, u.full_name as doctor_name,
           r.id as receptionist_id, r.full_name as receptionist_name,
           b.name as branch_name,
           CASE 
               WHEN v.status = 'pending' THEN 'warning'
               WHEN v.status = 'assigned' THEN 'info'
               WHEN v.status = 'with_doctor' THEN 'primary'
               WHEN v.status = 'lab_test' THEN 'orange'
               WHEN v.status = 'prescribed' THEN 'purple'
               WHEN v.status = 'completed' THEN 'success'
               WHEN v.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM visits v
    INNER JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    LEFT JOIN users r ON v.receptionist_id = r.id
    LEFT JOIN branches b ON v.branch_id = b.id
    WHERE v.id = ?
");
$stmt->execute([$visit_id]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visit) {
    header('Location: visits.php?branch=' . $selected_branch_id);
    exit;
}

$patient_id = $visit['patient_id'];

// ================================================================
// GET VISIT STATISTICS
// ================================================================
$stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$total_patient_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// ✅ GET LAB TESTS WITH TECHNICIAN NAME - FIXED: using lab_technician_id
// ================================================================
$stmt = $db->prepare("
    SELECT lt.*,
           u.full_name as technician_name,
           u.id as technician_id,
           d.full_name as doctor_name,
           CASE 
               WHEN lt.status = 'pending' THEN 'warning'
               WHEN lt.status = 'in_progress' THEN 'info'
               WHEN lt.status = 'completed' THEN 'success'
               WHEN lt.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM lab_tests lt
    LEFT JOIN users u ON lt.lab_technician_id = u.id
    LEFT JOIN users d ON lt.doctor_id = d.id
    WHERE lt.visit_id = ?
    ORDER BY lt.created_at ASC
");
$stmt->execute([$visit_id]);
$visit_lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PRESCRIPTIONS WITH DOCTOR NAME
// ================================================================
$stmt = $db->prepare("
    SELECT p.*,
           u.full_name as doctor_name,
           CASE 
               WHEN p.status = 'pending' THEN 'warning'
               WHEN p.status = 'confirmed' THEN 'info'
               WHEN p.status = 'dispensed' THEN 'success'
               WHEN p.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM prescriptions p
    LEFT JOIN users u ON p.doctor_id = u.id
    WHERE p.visit_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$visit_id]);
$visit_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get prescription items
$prescription_items = [];
$total_prescription_cost = 0;
foreach ($visit_prescriptions as $prescription) {
    $stmt = $db->prepare("
        SELECT pi.*, 
               (pi.quantity * pi.unit_price) as item_total
        FROM prescription_items pi
        WHERE pi.prescription_id = ?
    ");
    $stmt->execute([$prescription['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $prescription_items[$prescription['id']] = $items;
    
    foreach ($items as $item) {
        $total_prescription_cost += $item['item_total'] ?? 0;
    }
}

// ================================================================
// GET PROCEDURES AND TOOLS
// ================================================================
$stmt = $db->prepare("
    SELECT DISTINCT bi.*
    FROM bill_items bi
    INNER JOIN patient_bills pb ON bi.bill_id = pb.id
    WHERE pb.visit_id = ? 
    AND (bi.item_type = 'procedure' OR bi.item_type = 'tool')
    ORDER BY bi.item_type, bi.item_name
");
$stmt->execute([$visit_id]);
$procedure_tools = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET VITAL SIGNS
// ================================================================
$stmt = $db->prepare("
    SELECT vs.*, u.full_name as recorded_by_name
    FROM vital_signs vs
    LEFT JOIN users u ON vs.recorded_by = u.id
    WHERE vs.visit_id = ?
    ORDER BY vs.recorded_at DESC
    LIMIT 1
");
$stmt->execute([$visit_id]);
$vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);

// ================================================================
// GET ALL BILLS - UNIQUE BILLS ONLY
// ================================================================
$stmt = $db->prepare("
    SELECT pb.*,
           CASE 
               WHEN pb.status = 'pending' THEN 'warning'
               WHEN pb.status = 'paid' THEN 'success'
               WHEN pb.status = 'partial' THEN 'info'
               WHEN pb.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM patient_bills pb
    WHERE pb.visit_id = ?
    ORDER BY pb.created_at ASC
");
$stmt->execute([$visit_id]);
$raw_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter duplicate bills
$unique_bills = [];
$seen_bill_numbers = [];
foreach ($raw_bills as $bill) {
    $bill_number = $bill['bill_number'];
    if (!in_array($bill_number, $seen_bill_numbers)) {
        $unique_bills[] = $bill;
        $seen_bill_numbers[] = $bill_number;
    }
}
$visit_bills = $unique_bills;

// ================================================================
// CALCULATE TOTALS BY CATEGORY
// ================================================================
$bill_category_totals = [
    'consultation' => 0,
    'lab_test' => 0,
    'medication' => 0,
    'procedure' => 0,
    'tool' => 0,
    'registration' => 0,
    'other' => 0
];

$all_bill_items = [];
$total_bill_amount = 0;
$total_paid_amount = 0;
$total_balance = 0;
$bill_statuses = [];

foreach ($visit_bills as $bill) {
    $bill_id = $bill['id'];
    $total_bill_amount += $bill['total_amount'] ?? 0;
    $total_paid_amount += $bill['paid_amount'] ?? 0;
    $total_balance += $bill['balance'] ?? 0;
    $bill_statuses[] = $bill['status'];
    
    // Get bill items
    $stmt = $db->prepare("
        SELECT 
            bi.*,
            CASE 
                WHEN bi.item_type = 'consultation' THEN 'consultation'
                WHEN bi.item_type = 'lab_test' THEN 'lab_test'
                WHEN bi.item_type = 'medication' THEN 'medication'
                WHEN bi.item_type = 'procedure' THEN 'procedure'
                WHEN bi.item_type = 'tool' THEN 'tool'
                WHEN bi.item_type = 'registration' THEN 'registration'
                ELSE 'other'
            END as category
        FROM bill_items bi
        WHERE bi.bill_id = ?
        GROUP BY bi.item_name, bi.item_type, bi.unit_price, bi.quantity
        ORDER BY bi.created_at ASC
    ");
    $stmt->execute([$bill_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $all_bill_items[$bill_id] = $items;
    
    foreach ($items as $item) {
        $category = $item['category'] ?? 'other';
        if (isset($bill_category_totals[$category])) {
            $bill_category_totals[$category] += $item['total_price'] ?? 0;
        }
    }
}

// Determine overall status
$overall_status = 'pending';
if (count($visit_bills) > 0) {
    if (in_array('pending', $bill_statuses)) {
        $overall_status = 'pending';
    } elseif (in_array('partial', $bill_statuses)) {
        $overall_status = 'partial';
    } elseif (array_diff($bill_statuses, ['paid']) === []) {
        $overall_status = 'paid';
    } elseif (array_diff($bill_statuses, ['cancelled']) === []) {
        $overall_status = 'cancelled';
    } else {
        $overall_status = 'partial';
    }
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<style>
    /* ================================================================
       CARD STYLES - BLUE THEME
       ================================================================ */
    
    .status-badge {
        padding: 4px 16px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .status-badge.warning { background: #FEF3C7; color: #D97706; }
    .status-badge.success { background: #D1FAE5; color: #059669; }
    .status-badge.danger { background: #FEE2E2; color: #EF4444; }
    .status-badge.info { background: #E8F0FE; color: #0B5ED7; }
    .status-badge.primary { background: #DBEAFE; color: #2563EB; }
    .status-badge.orange { background: #FED7AA; color: #EA580C; }
    .status-badge.purple { background: #E9D5FF; color: #7B2FBE; }
    .status-badge.secondary { background: #E2E8F0; color: #64748B; }
    .status-badge.pink { background: #FCE7F3; color: #DB2777; }
    .status-badge.teal { background: #CCFBF1; color: #0D9488; }
    
    [data-theme="dark"] .status-badge.warning { background: #3A2A1A; color: #FBBF24; }
    [data-theme="dark"] .status-badge.success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge.danger { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .status-badge.info { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge.primary { background: #1A2A4A; color: #60A5FA; }
    [data-theme="dark"] .status-badge.orange { background: #3A2A1A; color: #FB923C; }
    [data-theme="dark"] .status-badge.purple { background: #2A1A3A; color: #A78BFA; }
    [data-theme="dark"] .status-badge.secondary { background: #2D3748; color: #94A3B8; }
    [data-theme="dark"] .status-badge.pink { background: #3A1A2A; color: #F472B6; }
    [data-theme="dark"] .status-badge.teal { background: #1A3A3A; color: #2DD4BF; }
    
    .visit-header {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 16px;
        padding: 24px 30px;
        color: white;
        position: relative;
        overflow: hidden;
    }
    .visit-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
    }
    .visit-header .visit-number {
        font-size: 1.4rem;
        font-weight: 700;
        font-family: monospace;
    }
    .visit-header .visit-meta {
        font-size: 0.85rem;
        opacity: 0.85;
    }
    
    .info-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        height: 100%;
    }
    .info-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
        transform: translateY(-2px);
    }
    .info-card .card-icon {
        font-size: 1.8rem;
        margin-bottom: 8px;
    }
    .info-card .card-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .info-card .card-title .badge-count {
        font-size: 0.6rem;
        font-weight: 400;
        color: var(--text-secondary);
    }
    .info-row {
        display: flex;
        padding: 5px 0;
        border-bottom: 1px solid var(--border-color);
    }
    .info-row:last-child {
        border-bottom: none;
    }
    .info-row .info-label {
        width: 130px;
        font-weight: 600;
        color: var(--text-secondary);
        font-size: 0.75rem;
        flex-shrink: 0;
    }
    .info-row .info-value {
        flex: 1;
        color: var(--text-primary);
        font-size: 0.82rem;
    }
    
    .vital-card {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 16px 12px;
        text-align: center;
        border: 2px solid var(--border-color);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        min-height: 100px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    .vital-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        border-radius: 14px 14px 0 0;
    }
    .vital-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    }
    .vital-card .vital-icon { font-size: 1.8rem; margin-bottom: 6px; }
    .vital-card .vital-value { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); line-height: 1.2; }
    .vital-card .vital-label { font-size: 0.65rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em; margin-top: 2px; }
    .vital-card .vital-unit { font-size: 0.6rem; color: var(--text-secondary); font-weight: 400; margin-left: 2px; }
    
    .vital-card.blue::before { background: linear-gradient(90deg, #0B5ED7, #1A73E8); }
    .vital-card.blue .vital-icon { color: #0B5ED7; }
    .vital-card.blue .vital-value { color: #0B5ED7; }
    .vital-card.red::before { background: linear-gradient(90deg, #EF4444, #F87171); }
    .vital-card.red .vital-icon { color: #EF4444; }
    .vital-card.red .vital-value { color: #EF4444; }
    .vital-card.pink::before { background: linear-gradient(90deg, #EC4899, #F472B6); }
    .vital-card.pink .vital-icon { color: #EC4899; }
    .vital-card.pink .vital-value { color: #EC4899; }
    .vital-card.purple::before { background: linear-gradient(90deg, #7B2FBE, #9B4DCA); }
    .vital-card.purple .vital-icon { color: #7B2FBE; }
    .vital-card.purple .vital-value { color: #7B2FBE; }
    .vital-card.green::before { background: linear-gradient(90deg, #059669, #0AA84F); }
    .vital-card.green .vital-icon { color: #059669; }
    .vital-card.green .vital-value { color: #059669; }
    .vital-card.indigo::before { background: linear-gradient(90deg, #4F46E5, #818CF8); }
    .vital-card.indigo .vital-icon { color: #4F46E5; }
    .vital-card.indigo .vital-value { color: #4F46E5; }
    
    [data-theme="dark"] .vital-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .vital-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 8px 30px rgba(0,0,0,0.3);
    }
    [data-theme="dark"] .vital-card .vital-value { color: #F1F5F9; }
    [data-theme="dark"] .vital-card.blue .vital-value { color: #6EA8FE; }
    [data-theme="dark"] .vital-card.red .vital-value { color: #F87171; }
    [data-theme="dark"] .vital-card.pink .vital-value { color: #F472B6; }
    [data-theme="dark"] .vital-card.purple .vital-value { color: #A78BFA; }
    [data-theme="dark"] .vital-card.green .vital-value { color: #34D399; }
    [data-theme="dark"] .vital-card.indigo .vital-value { color: #A5B4FC; }
    
    .badge {
        padding: 2px 10px !important;
        border-radius: 12px !important;
        font-size: 0.6rem !important;
        font-weight: 600 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
    }
    .badge-blue { background: #E8F0FE !important; color: #0B5ED7 !important; }
    .badge-green { background: #D1FAE5 !important; color: #059669 !important; }
    .badge-orange { background: #FED7AA !important; color: #EA580C !important; }
    .badge-purple { background: #E9D5FF !important; color: #7B2FBE !important; }
    .badge-red { background: #FEE2E2 !important; color: #EF4444 !important; }
    .badge-teal { background: #CCFBF1 !important; color: #0D9488 !important; }
    .badge-pink { background: #FCE7F3 !important; color: #DB2777 !important; }
    .badge-info { background: #E8F0FE !important; color: #0B5ED7 !important; }
    
    [data-theme="dark"] .badge-blue { background: #1E3A5F !important; color: #6EA8FE !important; }
    [data-theme="dark"] .badge-green { background: #1A3A2A !important; color: #34D399 !important; }
    [data-theme="dark"] .badge-orange { background: #3A2A1A !important; color: #FB923C !important; }
    [data-theme="dark"] .badge-purple { background: #2A1A3A !important; color: #A78BFA !important; }
    [data-theme="dark"] .badge-red { background: #3A1A1A !important; color: #F87171 !important; }
    [data-theme="dark"] .badge-teal { background: #1A3A3A !important; color: #2DD4BF !important; }
    [data-theme="dark"] .badge-pink { background: #3A1A2A !important; color: #F472B6 !important; }
    
    /* Lab Table Styles - Blue Theme */
    .lab-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
    }
    .lab-table thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 10px 14px;
        text-align: left;
        border-bottom: 3px solid #0A4CA8;
    }
    .lab-table thead th:first-child { border-radius: 8px 0 0 0; }
    .lab-table thead th:last-child { border-radius: 0 8px 0 0; }
    .lab-table tbody td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }
    .lab-table tbody tr:hover td {
        background: rgba(11, 94, 215, 0.05);
    }
    .lab-table .result-positive {
        background: #D1FAE5;
        color: #059669;
        padding: 3px 12px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.75rem;
        display: inline-block;
    }
    .lab-table .result-negative {
        background: #FEE2E2;
        color: #EF4444;
        padding: 3px 12px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.75rem;
        display: inline-block;
    }
    .lab-table .result-pending {
        background: #FEF3C7;
        color: #D97706;
        padding: 3px 12px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.75rem;
        display: inline-block;
    }
    [data-theme="dark"] .lab-table .result-positive {
        background: #1A3A2A;
        color: #34D399;
    }
    [data-theme="dark"] .lab-table .result-negative {
        background: #3A1A1A;
        color: #F87171;
    }
    [data-theme="dark"] .lab-table .result-pending {
        background: #3A2A1A;
        color: #FBBF24;
    }
    [data-theme="dark"] .lab-table tbody tr:hover td {
        background: rgba(11, 94, 215, 0.1);
    }
    
    /* Prescription Card */
    .prescription-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 18px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 12px;
    }
    .prescription-card:hover {
        border-color: #7B2FBE;
        box-shadow: 0 4px 15px rgba(123, 47, 190, 0.08);
    }
    .prescription-card .med-item {
        background: var(--bg-body);
        border-radius: 8px;
        padding: 8px 12px;
        margin-bottom: 4px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 4px;
    }
    .prescription-card .med-item:last-child {
        margin-bottom: 0;
    }
    .prescription-card .med-name {
        font-weight: 600;
        font-size: 0.85rem;
    }
    
    /* Bill Card */
    .bill-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 18px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 12px;
    }
    .bill-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 15px rgba(11, 94, 215, 0.08);
    }
    
    /* Category Total Box */
    .cat-total-box {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 16px;
        border: 1px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
    }
    .cat-total-box:hover {
        transform: translateY(-3px);
        border-color: #0B5ED7;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    .cat-total-box .cat-icon { font-size: 1.5rem; display: block; margin-bottom: 4px; }
    .cat-total-box .cat-amount { font-size: 1.1rem; font-weight: 700; }
    .cat-total-box .cat-label { font-size: 0.6rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 500; letter-spacing: 0.04em; }
    
    /* Bill Summary Card */
    .bill-summary-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
    }
    .bill-summary-card:hover {
        transform: translateY(-3px);
    }
    .bill-summary-card .amount {
        font-size: 1.6rem;
        font-weight: 700;
    }
    .bill-summary-card .label {
        font-size: 0.65rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        font-weight: 500;
        letter-spacing: 0.04em;
    }
    .bill-summary-card.blue { border-color: #0B5ED7; }
    .bill-summary-card.blue .amount { color: #0B5ED7; }
    .bill-summary-card.green { border-color: #059669; }
    .bill-summary-card.green .amount { color: #059669; }
    .bill-summary-card.orange { border-color: #F59E0B; }
    .bill-summary-card.orange .amount { color: #F59E0B; }
    .bill-summary-card.red { border-color: #EF4444; }
    .bill-summary-card.red .amount { color: #EF4444; }
    .bill-summary-card.purple { border-color: #7B2FBE; }
    .bill-summary-card.purple .amount { color: #7B2FBE; }
    
    [data-theme="dark"] .bill-summary-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .bill-summary-card.blue { border-color: #1A73E8; }
    [data-theme="dark"] .bill-summary-card.blue .amount { color: #6EA8FE; }
    [data-theme="dark"] .bill-summary-card.green { border-color: #0AA84F; }
    [data-theme="dark"] .bill-summary-card.green .amount { color: #34D399; }
    [data-theme="dark"] .bill-summary-card.orange { border-color: #D97706; }
    [data-theme="dark"] .bill-summary-card.orange .amount { color: #FBBF24; }
    [data-theme="dark"] .bill-summary-card.red { border-color: #DC2626; }
    [data-theme="dark"] .bill-summary-card.red .amount { color: #F87171; }
    [data-theme="dark"] .bill-summary-card.purple { border-color: #7B2FBE; }
    [data-theme="dark"] .bill-summary-card.purple .amount { color: #A78BFA; }
    
    .btn-blue {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        border: none;
        padding: 6px 14px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.7rem;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-blue:hover {
        background: linear-gradient(135deg, #0A4CA8, #083C8A);
        transform: translateY(-2px);
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
        color: white;
    }
    .btn-sm { padding: 4px 10px; font-size: 0.65rem; }
    
    .footer {
        margin-top: 30px;
        padding-top: 15px;
        border-top: 1px solid var(--border-color);
        text-align: center;
        font-size: 0.75rem;
        color: var(--text-secondary);
    }
    .footer-brand { font-weight: 600; color: #0B5ED7; }
    [data-theme="dark"] .footer-brand { color: #6EA8FE; }
    
    .technician-tag {
        background: #E8F0FE;
        color: #0B5ED7;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    [data-theme="dark"] .technician-tag {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    .doctor-tag {
        background: #D1FAE5;
        color: #059669;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    [data-theme="dark"] .doctor-tag {
        background: #1A3A2A;
        color: #34D399;
    }
    
    /* ✅ FIXED: Procedure Card - NO BLACK BACKGROUND */
    .procedure-item {
        background: #F8FAFC;
        border-radius: 10px;
        padding: 12px 16px;
        border: 1px solid #E2E8F0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s ease;
    }
    .procedure-item:hover {
        border-color: #0B5ED7;
        box-shadow: 0 2px 10px rgba(11, 94, 215, 0.08);
        transform: translateY(-2px);
    }
    .procedure-item .proc-name {
        font-weight: 600;
        font-size: 0.85rem;
        color: #1E293B;
    }
    .procedure-item .proc-type {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 12px;
        display: inline-block;
        margin-top: 2px;
    }
    .procedure-item .proc-type.procedure {
        background: #CCFBF1;
        color: #0D9488;
    }
    .procedure-item .proc-type.tool {
        background: #FED7AA;
        color: #EA580C;
    }
    .procedure-item .proc-price {
        font-weight: 700;
        color: #0B5ED7;
        font-size: 0.9rem;
    }
    
    [data-theme="dark"] .procedure-item {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .procedure-item .proc-name {
        color: #F1F5F9;
    }
    [data-theme="dark"] .procedure-item .proc-type.procedure {
        background: #1A3A3A;
        color: #2DD4BF;
    }
    [data-theme="dark"] .procedure-item .proc-type.tool {
        background: #3A2A1A;
        color: #FB923C;
    }
    [data-theme="dark"] .procedure-item .proc-price {
        color: #6EA8FE;
    }
    [data-theme="dark"] .procedure-item:hover {
        border-color: #0B5ED7;
    }
    
    @media (max-width: 640px) {
        .visit-header { padding: 16px 18px; }
        .visit-header .visit-number { font-size: 1rem; }
        .info-row { flex-direction: column; gap: 2px; }
        .info-row .info-label { width: 100%; font-size: 0.7rem; }
        .vital-card { min-height: 70px; padding: 10px 8px; }
        .vital-card .vital-value { font-size: 1.1rem; }
        .vital-card .vital-icon { font-size: 1.2rem; }
        .grid-cols-2.sm\:grid-cols-3.md\:grid-cols-6 { grid-template-columns: repeat(2, 1fr); }
        .info-card { padding: 14px 16px; }
        .prescription-card .med-item { flex-direction: column; align-items: flex-start; }
        .bill-summary-card .amount { font-size: 1.2rem; }
        .grid-cols-2.sm\:grid-cols-4 { grid-template-columns: repeat(2, 1fr); }
        .lab-table thead th,
        .lab-table tbody td {
            padding: 6px 10px;
            font-size: 0.65rem;
        }
        .procedure-item {
            flex-direction: column;
            text-align: center;
            gap: 6px;
        }
    }
</style>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <form method="GET" action="visits.php" class="flex-1 flex">
                <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                <input type="text" name="search" placeholder="Search visits..." 
                       class="flex-1 px-3 py-2 bg-transparent border-none outline-none text-sm" 
                       style="color: var(--text-primary);">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search mr-1"></i> Search
                </button>
            </form>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches_list as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="datetime" id="currentDateTime"></span>
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- VISIT HEADER -->
    <!-- ================================================================ -->
    <div class="visit-header mb-5">
        <div class="flex flex-wrap items-center justify-between gap-3" style="position:relative;z-index:1;">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="visit-number">
                        <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($visit['visit_number']) ?>
                    </span>
                    <span class="status-badge <?= $visit['status_color'] ?? 'secondary' ?>">
                        <?= ucfirst($visit['status'] ?? 'N/A') ?>
                    </span>
                </div>
                <div class="visit-meta mt-1">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($visit['patient_name']) ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($visit['patient_number']) ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-calendar-alt"></i> <?= date('M d, Y h:i A', strtotime($visit['visit_date'])) ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?>
                </div>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="edit_visit.php?id=<?= $visit['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm" style="background:rgba(255,255,255,0.2);color:white;border:1px solid rgba(255,255,255,0.2);">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="visits.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.15);">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- CARD 1: VISIT INFORMATION -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
        
        <div class="info-card">
            <div class="card-icon">📋</div>
            <h4 class="card-title">
                <i class="fas fa-info-circle" style="color:#0B5ED7;"></i> Visit Information
            </h4>
            <div>
                <div class="info-row">
                    <span class="info-label">Visit Number</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($visit['visit_number']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Visit Date</span>
                    <span class="info-value"><?= date('M d, Y h:i A', strtotime($visit['visit_date'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Visit Type</span>
                    <span class="info-value">
                        <span class="badge badge-info"><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <span class="status-badge <?= $visit['status_color'] ?? 'secondary' ?>">
                            <?= ucfirst($visit['status'] ?? 'N/A') ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Doctor</span>
                    <span class="info-value">
                        <?php if ($visit['doctor_name']): ?>
                            <span class="doctor-tag">
                                <i class="fas fa-user-md"></i> <?= htmlspecialchars($visit['doctor_name']) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-gray-400">Not assigned</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Receptionist</span>
                    <span class="info-value"><?= htmlspecialchars($visit['receptionist_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Branch</span>
                    <span class="info-value"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></span>
                </div>
                <?php if ($visit['follow_up_date']): ?>
                    <div class="info-row">
                        <span class="info-label">Follow-up Date</span>
                        <span class="info-value"><?= date('M d, Y', strtotime($visit['follow_up_date'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ================================================================ -->
        <!-- CARD 2: PATIENT INFORMATION -->
        <!-- ================================================================ -->
        <div class="info-card">
            <div class="card-icon">👤</div>
            <h4 class="card-title">
                <i class="fas fa-user" style="color:#059669;"></i> Patient Information
                <a href="patient_details.php?id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" class="btn-blue btn-sm ml-auto">
                    <i class="fas fa-external-link-alt"></i> View
                </a>
            </h4>
            <div>
                <div class="info-row">
                    <span class="info-label">Patient Name</span>
                    <span class="info-value font-semibold"><?= htmlspecialchars($visit['patient_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Patient ID</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($visit['patient_number']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Gender</span>
                    <span class="info-value"><?= htmlspecialchars($visit['gender'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date of Birth</span>
                    <span class="info-value"><?= $visit['date_of_birth'] ? date('M d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Blood Group</span>
                    <span class="info-value">
                        <?= $visit['blood_group'] ? '<span class="badge badge-red">' . htmlspecialchars($visit['blood_group']) . '</span>' : 'N/A' ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Allergies</span>
                    <span class="info-value"><?= htmlspecialchars($visit['allergies'] ?? 'None') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?= htmlspecialchars($visit['patient_phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($visit['patient_email'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total Visits</span>
                    <span class="info-value">
                        <span class="badge badge-blue"><?= $total_patient_visits ?></span>
                    </span>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- CARD 3: SYMPTOMS, COMPLAINT, DIAGNOSIS & TREATMENT -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
        
        <div class="info-card">
            <div class="card-icon">🩺</div>
            <h4 class="card-title">
                <i class="fas fa-notes-medical" style="color:#F59E0B;"></i> Symptoms & Complaint
            </h4>
            <div>
                <div class="info-row">
                    <span class="info-label">Symptoms</span>
                    <span class="info-value"><?= htmlspecialchars($visit['symptoms'] ?? 'None reported') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Complaint</span>
                    <span class="info-value"><?= htmlspecialchars($visit['complaint'] ?? 'None reported') ?></span>
                </div>
            </div>
        </div>
        
        <div class="info-card" style="border-color: #7B2FBE;">
            <div class="card-icon">🔍</div>
            <h4 class="card-title">
                <i class="fas fa-diagnosis" style="color:#7B2FBE;"></i> Diagnosis & Treatment
            </h4>
            <div>
                <div class="info-row">
                    <span class="info-label">Diagnosis</span>
                    <span class="info-value font-semibold" style="color:#7B2FBE;">
                        <?= htmlspecialchars($visit['diagnosis'] ?? 'Not diagnosed yet') ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Treatment</span>
                    <span class="info-value"><?= htmlspecialchars($visit['treatment'] ?? 'Not prescribed yet') ?></span>
                </div>
                <?php if ($visit['notes']): ?>
                    <div class="info-row">
                        <span class="info-label">Notes</span>
                        <span class="info-value"><?= htmlspecialchars($visit['notes']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- CARD 4: VITAL SIGNS -->
    <!-- ================================================================ -->
    <?php if ($vital_signs): ?>
    <div class="info-card mb-5">
        <div class="card-icon">❤️</div>
        <h4 class="card-title">
            <i class="fas fa-heartbeat" style="color:#EC4899;"></i> Vital Signs
            <span class="badge-count">(<?= date('M d, Y h:i A', strtotime($vital_signs['recorded_at'])) ?>)</span>
        </h4>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3">
            <div class="vital-card blue">
                <div class="vital-icon"><i class="fas fa-thermometer-half"></i></div>
                <div class="vital-value">
                    <?php $temp = $vital_signs['temperature'] ?? null; echo $temp !== null ? $temp : '-'; ?>
                    <span class="vital-unit">°C</span>
                </div>
                <div class="vital-label">Temperature</div>
            </div>
            <div class="vital-card red">
                <div class="vital-icon"><i class="fas fa-heart"></i></div>
                <div class="vital-value">
                    <?php 
                        $systolic = $vital_signs['blood_pressure_systolic'] ?? null;
                        $diastolic = $vital_signs['blood_pressure_diastolic'] ?? null;
                        if ($systolic !== null && $diastolic !== null) {
                            echo $systolic . '/' . $diastolic;
                        } elseif ($systolic !== null) {
                            echo $systolic;
                        } else {
                            echo '-';
                        }
                    ?>
                    <span class="vital-unit">mmHg</span>
                </div>
                <div class="vital-label">Blood Pressure</div>
            </div>
            <div class="vital-card pink">
                <div class="vital-icon"><i class="fas fa-heartbeat"></i></div>
                <div class="vital-value">
                    <?php $pulse = $vital_signs['pulse_rate'] ?? null; echo $pulse !== null ? $pulse : '-'; ?>
                    <span class="vital-unit">bpm</span>
                </div>
                <div class="vital-label">Pulse Rate</div>
            </div>
            <div class="vital-card purple">
                <div class="vital-icon"><i class="fas fa-weight"></i></div>
                <div class="vital-value">
                    <?php $weight = $vital_signs['weight'] ?? null; echo $weight !== null ? $weight : '-'; ?>
                    <span class="vital-unit">kg</span>
                </div>
                <div class="vital-label">Weight</div>
            </div>
            <div class="vital-card green">
                <div class="vital-icon"><i class="fas fa-ruler-vertical"></i></div>
                <div class="vital-value">
                    <?php $height = $vital_signs['height'] ?? null; echo $height !== null ? $height : '-'; ?>
                    <span class="vital-unit">cm</span>
                </div>
                <div class="vital-label">Height</div>
            </div>
            <div class="vital-card indigo">
                <div class="vital-icon"><i class="fas fa-calculator"></i></div>
                <div class="vital-value">
                    <?php $bmi = $vital_signs['bmi'] ?? null; echo $bmi !== null ? $bmi : '-'; ?>
                </div>
                <div class="vital-label">BMI</div>
            </div>
        </div>
        <?php if ($vital_signs['notes']): ?>
            <div class="mt-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <p class="text-xs text-gray-500">📝 Notes</p>
                <p class="text-sm"><?= htmlspecialchars($vital_signs['notes']) ?></p>
            </div>
        <?php endif; ?>
        <p class="text-xs text-gray-400 mt-2">
            <i class="fas fa-user"></i> Recorded by: <?= htmlspecialchars($vital_signs['recorded_by_name'] ?? 'N/A') ?>
        </p>
    </div>
    <?php else: ?>
    <div class="info-card mb-5">
        <div class="card-icon">❤️</div>
        <h4 class="card-title">
            <i class="fas fa-heartbeat" style="color:#EC4899;"></i> Vital Signs
        </h4>
        <div class="text-center py-4 text-gray-400">
            <i class="fas fa-heartbeat text-3xl block mb-2" style="color: #EC4899;"></i>
            <p>No vital signs recorded for this visit</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ✅ CARD 5: LAB TESTS WITH TECHNICIAN NAME - FIXED -->
    <!-- ================================================================ -->
    <?php if (count($visit_lab_tests) > 0): ?>
    <div class="info-card mb-5">
        <div class="card-icon">🔬</div>
        <h4 class="card-title">
            <i class="fas fa-flask" style="color:#F59E0B;"></i> Lab Tests & Results
            <span class="badge-count">(<?= count($visit_lab_tests) ?> tests)</span>
        </h4>
        
        <div class="overflow-x-auto">
            <table class="lab-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th style="min-width:150px;">Test Name</th>
                        <th style="min-width:80px;">Price</th>
                        <th style="min-width:100px;">Status</th>
                        <th style="min-width:150px;">Results</th>
                        <th style="min-width:140px;">👨‍⚕️ Doctor</th>
                        <th style="min-width:140px;">🔬 Technician</th>
                        <th style="min-width:100px;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($visit_lab_tests as $test): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td class="font-semibold"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                            <td>TSh <?= number_format($test['test_price'] ?? 0) ?></td>
                            <td>
                                <span class="status-badge <?= $test['status_color'] ?? 'secondary' ?>" style="font-size:0.55rem; padding:2px 10px;">
                                    <?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'N/A')) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($test['results'])): ?>
                                    <?php 
                                    $result = strtolower($test['results']);
                                    if (strpos($result, 'positive') !== false || strpos($result, 'pos') !== false):
                                    ?>
                                        <span class="result-positive">✅ <?= htmlspecialchars($test['results']) ?></span>
                                    <?php elseif (strpos($result, 'negative') !== false || strpos($result, 'neg') !== false): ?>
                                        <span class="result-negative">❌ <?= htmlspecialchars($test['results']) ?></span>
                                    <?php else: ?>
                                        <span class="result-positive"><?= htmlspecialchars($test['results']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="result-pending">⏳ Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($test['doctor_name']): ?>
                                    <span class="doctor-tag">
                                        <i class="fas fa-user-md"></i> <?= htmlspecialchars($test['doctor_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($test['technician_name']): ?>
                                    <span class="technician-tag">
                                        <i class="fas fa-microscope"></i> <?= htmlspecialchars($test['technician_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-xs"><?= date('M d, Y', strtotime($test['created_at'])) ?></td>
                        </tr>
                        <?php if (!empty($test['reference_range']) || !empty($test['interpretation'])): ?>
                            <tr style="background: var(--bg-body);">
                                <td colspan="8" style="padding: 4px 14px; font-size:0.65rem; color: var(--text-secondary);">
                                    <?php if (!empty($test['reference_range'])): ?>
                                        <span><strong>Reference Range:</strong> <?= htmlspecialchars($test['reference_range']) ?></span>
                                        <?php if (!empty($test['interpretation'])): ?>
                                            <span class="mx-2">|</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($test['interpretation'])): ?>
                                        <span><strong>Interpretation:</strong> <?= htmlspecialchars($test['interpretation']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="info-card mb-5">
        <div class="card-icon">🔬</div>
        <h4 class="card-title">
            <i class="fas fa-flask" style="color:#F59E0B;"></i> Lab Tests & Results
        </h4>
        <div class="text-center py-4 text-gray-400">
            <i class="fas fa-flask text-3xl block mb-2" style="color: #F59E0B;"></i>
            <p>No lab tests recorded for this visit</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- CARD 6: MEDICATIONS (PRESCRIPTIONS) -->
    <!-- ================================================================ -->
    <?php if (count($visit_prescriptions) > 0): ?>
    <div class="info-card mb-5">
        <div class="card-icon">💊</div>
        <h4 class="card-title">
            <i class="fas fa-prescription" style="color:#7B2FBE;"></i> Medications
            <span class="badge-count">(<?= count($visit_prescriptions) ?> prescriptions)</span>
        </h4>
        <?php foreach ($visit_prescriptions as $prescription): ?>
            <div class="prescription-card">
                <div class="flex flex-wrap justify-between items-start gap-2 mb-2">
                    <div>
                        <p class="font-semibold text-sm">
                            <i class="fas fa-prescription" style="color:#7B2FBE;"></i>
                            <?= htmlspecialchars($prescription['prescription_number']) ?>
                        </p>
                        <?php if ($prescription['doctor_name']): ?>
                            <p class="text-xs text-gray-500">
                                <i class="fas fa-user-md"></i> Doctor: <?= htmlspecialchars($prescription['doctor_name']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <span class="status-badge <?= $prescription['status_color'] ?? 'secondary' ?>" style="font-size:0.6rem;">
                        <?= ucfirst($prescription['status'] ?? 'N/A') ?>
                    </span>
                </div>
                
                <?php if (!empty($prescription['diagnosis'])): ?>
                    <p class="text-xs text-gray-600 dark:text-gray-300"><strong>Diagnosis:</strong> <?= htmlspecialchars($prescription['diagnosis']) ?></p>
                <?php endif; ?>
                
                <?php if (!empty($prescription['instructions'])): ?>
                    <p class="text-xs text-gray-600 dark:text-gray-300"><strong>Instructions:</strong> <?= htmlspecialchars($prescription['instructions']) ?></p>
                <?php endif; ?>
                
                <?php if (isset($prescription_items[$prescription['id']]) && count($prescription_items[$prescription['id']]) > 0): ?>
                    <div class="mt-2">
                        <?php foreach ($prescription_items[$prescription['id']] as $item): ?>
                            <div class="med-item">
                                <div>
                                    <span class="med-name"><?= htmlspecialchars($item['medication_name']) ?></span>
                                    <span class="badge badge-purple ml-1"><?= htmlspecialchars($item['dosage'] ?? 'N/A') ?></span>
                                </div>
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <span class="badge badge-info">Route: <?= htmlspecialchars($item['route'] ?? 'N/A') ?></span>
                                    <span class="badge badge-orange">Freq: <?= htmlspecialchars($item['frequency'] ?? 'N/A') ?></span>
                                    <span class="badge badge-blue">Qty: <?= $item['quantity'] ?? 0 ?></span>
                                    <span class="badge badge-green">Duration: <?= htmlspecialchars($item['duration'] ?? 'N/A') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ✅ CARD 7: PROCEDURES AND TOOLS - FIXED NO BLACK BACKGROUND -->
    <!-- ================================================================ -->
    <?php if (count($procedure_tools) > 0): ?>
    <div class="info-card mb-5">
        <div class="card-icon">🏥</div>
        <h4 class="card-title">
            <i class="fas fa-syringe" style="color:#0D9488;"></i> Procedures & Tools Used
            <span class="badge-count">(<?= count($procedure_tools) ?> items)</span>
        </h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <?php foreach ($procedure_tools as $item): ?>
                <div class="procedure-item">
                    <div>
                        <span class="proc-name"><?= htmlspecialchars($item['item_name']) ?></span>
                        <br>
                        <span class="proc-type <?= $item['item_type'] === 'procedure' ? 'procedure' : 'tool' ?>">
                            <?= ucfirst($item['item_type'] ?? 'N/A') ?>
                        </span>
                    </div>
                    <span class="proc-price">TSh <?= number_format($item['unit_price'] ?? 0) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- CARD 8: BILLS BY CATEGORY -->
    <!-- ================================================================ -->
    <?php if (count($visit_bills) > 0): ?>
    <div class="info-card mb-5">
        <div class="card-icon">💰</div>
        <h4 class="card-title">
            <i class="fas fa-chart-pie" style="color:#0B5ED7;"></i> Bills by Category
            <span class="badge-count">| Total: TSh <?= number_format($total_bill_amount) ?></span>
            <span class="status-badge <?= 
                $overall_status === 'paid' ? 'success' : 
                ($overall_status === 'partial' ? 'info' : 
                ($overall_status === 'cancelled' ? 'danger' : 'warning')) 
            ?>" style="font-size:0.65rem;">
                <?= ucfirst($overall_status) ?>
            </span>
        </h4>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
            <?php
            $category_icons = [
                'consultation' => '🩺',
                'lab_test' => '🔬',
                'medication' => '💊',
                'procedure' => '🏥',
                'tool' => '🛠️',
                'registration' => '📝',
                'other' => '📋'
            ];
            $category_colors = [
                'consultation' => '#0B5ED7',
                'lab_test' => '#F59E0B',
                'medication' => '#7B2FBE',
                'procedure' => '#EF4444',
                'tool' => '#0D9488',
                'registration' => '#059669',
                'other' => '#64748B'
            ];
            $category_labels = [
                'consultation' => 'Consultation',
                'lab_test' => 'Lab Tests',
                'medication' => 'Medications',
                'procedure' => 'Procedures',
                'tool' => 'Tools',
                'registration' => 'Registration',
                'other' => 'Other'
            ];
            ?>
            <?php foreach ($bill_category_totals as $category => $amount): ?>
                <?php if ($amount > 0): ?>
                    <div class="cat-total-box">
                        <span class="cat-icon"><?= $category_icons[$category] ?? '📋' ?></span>
                        <p class="cat-amount" style="color: <?= $category_colors[$category] ?? '#64748B' ?>;">
                            TSh <?= number_format($amount) ?>
                        </p>
                        <p class="cat-label"><?= $category_labels[$category] ?? ucfirst($category) ?></p>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- CARD 9: BILL SUMMARY -->
    <!-- ================================================================ -->
    <div class="info-card mb-5">
        <div class="card-icon">📊</div>
        <h4 class="card-title">
            <i class="fas fa-file-invoice" style="color:#0B5ED7;"></i> Bill Summary
        </h4>
        
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
            <div class="bill-summary-card blue">
                <p class="amount">TSh <?= number_format($total_bill_amount) ?></p>
                <p class="label">Total Amount</p>
            </div>
            <div class="bill-summary-card green">
                <p class="amount">TSh <?= number_format($total_paid_amount) ?></p>
                <p class="label">Paid</p>
            </div>
            <div class="bill-summary-card orange">
                <p class="amount">TSh <?= number_format($total_balance) ?></p>
                <p class="label">Pending Balance</p>
            </div>
            <div class="bill-summary-card purple">
                <p class="amount"><?= count($visit_bills) ?></p>
                <p class="label">Total Bills</p>
            </div>
        </div>
        
        <!-- Individual Bills -->
        <?php foreach ($visit_bills as $index => $bill): ?>
            <div class="bill-card <?= $bill['status'] === 'cancelled' ? 'opacity-60' : '' ?>">
                <div class="flex flex-wrap justify-between items-start gap-2">
                    <div>
                        <p class="font-semibold text-sm">
                            <i class="fas fa-receipt" style="color:#0B5ED7;"></i>
                            <?= htmlspecialchars($bill['bill_number']) ?>
                            <span class="badge-count">#<?= $index + 1 ?></span>
                        </p>
                        <p class="text-xs text-gray-500">
                            <i class="fas fa-calendar-alt"></i> <?= date('M d, Y h:i A', strtotime($bill['created_at'])) ?>
                        </p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-bold" style="color:#0B5ED7;">TSh <?= number_format($bill['total_amount'] ?? 0) ?></span>
                        <span class="status-badge <?= $bill['status_color'] ?? 'secondary' ?>" style="font-size:0.6rem;">
                            <?= ucfirst($bill['status'] ?? 'N/A') ?>
                        </span>
                    </div>
                </div>
                
                <!-- Bill Items -->
                <?php if (isset($all_bill_items[$bill['id']]) && count($all_bill_items[$bill['id']]) > 0): ?>
                    <div class="mt-2 flex flex-wrap gap-1">
                        <?php foreach ($all_bill_items[$bill['id']] as $item): ?>
                            <span class="badge <?= 
                                $item['item_type'] === 'medication' ? 'badge-purple' : 
                                ($item['item_type'] === 'lab_test' ? 'badge-orange' : 
                                ($item['item_type'] === 'consultation' ? 'badge-blue' : 
                                ($item['item_type'] === 'procedure' ? 'badge-red' : 
                                ($item['item_type'] === 'tool' ? 'badge-teal' : 
                                ($item['item_type'] === 'registration' ? 'badge-green' : 'badge-info'))))) 
                            ?>" style="font-size:0.6rem;">
                                <?= htmlspecialchars($item['item_name']) ?>
                                (TSh <?= number_format($item['total_price'] ?? 0) ?>)
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="info-card mb-5">
        <div class="card-icon">💰</div>
        <h4 class="card-title">
            <i class="fas fa-file-invoice" style="color:#0B5ED7;"></i> Bills
        </h4>
        <div class="text-center py-4 text-gray-400">
            <i class="fas fa-receipt text-3xl block mb-2"></i>
            <p>No bills created for this visit</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Visit Details
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');

    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c🏥 Braick Dispensary - Visit Details (Card Style)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Visit: <?= htmlspecialchars($visit['visit_number']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($visit['patient_name']) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🔬 Lab Tests: <?= count($visit_lab_tests) ?>', 'font-size:13px; color:#F59E0B;');
    console.log('%c💊 Prescriptions: <?= count($visit_prescriptions) ?>', 'font-size:13px; color:#7B2FBE;');
    console.log('%c💰 Bills: <?= count($visit_bills) ?> | Total: TSh <?= number_format($total_bill_amount) ?>', 'font-size:13px; color:#0B5ED7;');
    <?php if (count($visit_lab_tests) > 0): ?>
    console.log('%c🔬 Technician Names:', 'font-size:13px; color:#F59E0B;');
    <?php foreach ($visit_lab_tests as $test): ?>
        <?php if ($test['technician_name']): ?>
            console.log('  - <?= htmlspecialchars($test['test_name']) ?>: <?= htmlspecialchars($test['technician_name']) ?>');
        <?php else: ?>
            console.log('  - <?= htmlspecialchars($test['test_name']) ?>: No technician assigned');
        <?php endif; ?>
    <?php endforeach; ?>
    <?php endif; ?>
</script>

</body>
</html>