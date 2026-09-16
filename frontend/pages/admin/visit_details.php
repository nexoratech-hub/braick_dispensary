<?php
// ================================================================
// FILE: frontend/pages/admin/visit_details.php
// VISIT DETAILS - TABLE STYLE VIEW
// ✅ Inatumia SHARED HEADER + SIDEBAR pekee
// ✅ BLUE THEME
// ✅ WITH 7 VITAL SIGNS (INCLUDING OXYGEN SATURATION - SpO2)
// BRAICK DISPENSARY
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

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
// GET LAB TESTS
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
// GET PRESCRIPTIONS
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
$procedure_tools = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT bi.*
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE b.visit_id = ? 
        AND (bi.item_type = 'procedure' OR bi.item_type = 'tool' OR bi.item_type = 'equipment')
        ORDER BY bi.item_type, bi.item_name
    ");
    $stmt->execute([$visit_id]);
    $procedure_tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $procedure_tools = [];
}

// ================================================================
// GET VITAL SIGNS
// ================================================================
$vital_signs = null;
$stmt = $db->prepare("
    SELECT 
        vs.id, vs.patient_id, vs.visit_id,
        vs.temperature, vs.blood_pressure_systolic, vs.blood_pressure_diastolic,
        vs.pulse_rate, vs.oxygen_saturation, vs.weight, vs.height, vs.bmi,
        vs.notes, vs.recorded_at,
        u.full_name as recorded_by_name
    FROM vital_signs vs
    LEFT JOIN users u ON vs.recorded_by = u.id
    WHERE vs.visit_id = ?
    ORDER BY vs.recorded_at DESC
    LIMIT 1
");
$stmt->execute([$visit_id]);
$vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vital_signs) {
    $stmt = $db->prepare("
        SELECT 
            vs.id, vs.patient_id, vs.visit_id,
            vs.temperature, vs.blood_pressure_systolic, vs.blood_pressure_diastolic,
            vs.pulse_rate, vs.oxygen_saturation, vs.weight, vs.height, vs.bmi,
            vs.notes, vs.recorded_at,
            u.full_name as recorded_by_name
        FROM vital_signs vs
        LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE vs.patient_id = ?
        ORDER BY vs.recorded_at DESC
        LIMIT 1
    ");
    $stmt->execute([$patient_id]);
    $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ================================================================
// HELPER: SpO2 Status
// ================================================================
function getSpO2Status($spo2) {
    if ($spo2 === null || $spo2 === '') {
        return ['label' => 'N/A', 'class' => 'unknown', 'color' => '#64748B'];
    }
    $spo2 = (int)$spo2;
    if ($spo2 >= 95) return ['label' => 'NORMAL', 'class' => 'normal', 'color' => '#059669'];
    if ($spo2 >= 90) return ['label' => 'LOW', 'class' => 'low', 'color' => '#D97706'];
    return ['label' => 'CRITICAL', 'class' => 'critical', 'color' => '#DC2626'];
}

// ================================================================
// GET BILLS
// ================================================================
$stmt = $db->prepare("
    SELECT b.*,
           CASE 
               WHEN b.status = 'pending' THEN 'warning'
               WHEN b.status = 'paid' THEN 'success'
               WHEN b.status = 'partial' THEN 'info'
               WHEN b.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM bills b
    WHERE b.visit_id = ?
    ORDER BY b.created_at ASC
");
$stmt->execute([$visit_id]);
$raw_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
// CALCULATE TOTALS
// ================================================================
$bill_category_totals = [
    'consultation' => 0, 'lab_test' => 0, 'medication' => 0,
    'procedure' => 0, 'tool' => 0, 'registration' => 0, 'other' => 0
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
    
    $stmt = $db->prepare("
        SELECT 
            bi.*,
            CASE 
                WHEN bi.item_type = 'consultation' THEN 'consultation'
                WHEN bi.item_type = 'lab_test' THEN 'lab_test'
                WHEN bi.item_type = 'medication' THEN 'medication'
                WHEN bi.item_type = 'procedure' THEN 'procedure'
                WHEN bi.item_type = 'tool' THEN 'tool'
                WHEN bi.item_type = 'equipment' THEN 'tool'
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
// GET BRANCHES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// PROFILE PIC
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
:root {
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #3B82F6;
    --primary-bg: #EFF6FF;
    --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    --primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #073B8A);
    
    --success: #059669;
    --danger: #DC2626;
    --warning: #D97706;
    --purple: #7C3AED;
    --teal: #0D9488;
    --sky: #0EA5E9;
    --pink: #EC4899;
    --indigo: #4F46E5;
    
    --bg-body: #F0F4F8;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --radius: 12px;
    --radius-lg: 18px;
    --table-hover: #F8FAFC;
    --gray-200: #E2E8F0;
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary-bg: #1E3A5F;
    --table-hover: #1E293B;
    --primary-gradient: linear-gradient(135deg, #2563EB, #1D4ED8);
    --primary-gradient-strong: linear-gradient(135deg, #1D4ED8, #1E40AF);
}

/* ================================================================
   PAGE HEADER
   ================================================================ */
.page-header {
    background: var(--primary-gradient-strong);
    border-radius: var(--radius-lg);
    padding: 28px 36px;
    margin-bottom: 28px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -60%; right: -10%;
    width: 400px; height: 400px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    pointer-events: none;
}

.page-header::after {
    content: '';
    position: absolute;
    bottom: -40%; left: -5%;
    width: 300px; height: 300px;
    background: rgba(255,255,255,0.03);
    border-radius: 50%;
    pointer-events: none;
}

.page-header .page-title {
    color: white;
    font-size: 1.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin: 0;
}

.page-header .page-title i {
    font-size: 2rem;
    opacity: 0.9;
}

.page-header .page-subtitle {
    color: rgba(255,255,255,0.85);
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin-top: 6px;
}

.page-header .page-subtitle strong {
    color: white;
    font-weight: 600;
}

.role-badge-display {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    backdrop-filter: blur(4px);
}

.header-badge {
    background: rgba(255,255,255,0.12);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 500;
    backdrop-filter: blur(4px);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid rgba(255,255,255,0.1);
    transition: all 0.3s ease;
}

.header-badge:hover {
    background: rgba(255,255,255,0.2);
    transform: translateY(-1px);
}

.btn-outline-light {
    background: rgba(255,255,255,0.12);
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 8px 18px;
    border-radius: var(--radius);
    font-weight: 500;
    font-size: 0.82rem;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    backdrop-filter: blur(4px);
    position: relative;
    z-index: 1;
}

.btn-outline-light:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    color: white;
}

/* ================================================================
   DETAIL CARD
   ================================================================ */
.detail-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    padding: 24px 28px;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    margin-bottom: 24px;
}

.detail-card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
}

.detail-label {
    font-size: 0.7rem;
    color: var(--text-secondary);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.detail-value {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
}

/* ================================================================
   VITAL SIGNS - 7 SIGNS WITH SpO2
   ================================================================ */
.vital-grid-7 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

.vital-card {
    background: var(--bg-card);
    border-radius: 10px;
    padding: 12px 10px;
    text-align: center;
    border: 2px solid var(--border-color);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    min-height: 90px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.vital-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    border-radius: 10px 10px 0 0;
}

.vital-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}

.vital-card .vital-icon {
    font-size: 1.2rem;
    margin-bottom: 3px;
    line-height: 1;
}

.vital-card .vital-value {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.2;
}

.vital-card .vital-label {
    font-size: 0.5rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.06em;
    margin-top: 2px;
}

.vital-card .vital-unit {
    font-size: 0.55rem;
    color: var(--text-secondary);
    font-weight: 400;
    margin-left: 1px;
}

.vital-card.blue::before { background: linear-gradient(90deg, #0B5ED7, #1A73E8); }
.vital-card.blue .vital-icon { color: #0B5ED7; }
.vital-card.blue .vital-value { color: #0B5ED7; }

.vital-card.red::before { background: linear-gradient(90deg, #EF4444, #F87171); }
.vital-card.red .vital-icon { color: #EF4444; }
.vital-card.red .vital-value { color: #EF4444; }

.vital-card.pink::before { background: linear-gradient(90deg, #EC4899, #F472B6); }
.vital-card.pink .vital-icon { color: #EC4899; }
.vital-card.pink .vital-value { color: #EC4899; }

.vital-card.spo2-card::before { background: linear-gradient(90deg, #0EA5E9, #38BDF8); }
.vital-card.spo2-card .vital-icon { color: #0284C7; }
.vital-card.spo2-card .vital-value { color: #0284C7; }
.vital-card.spo2-card {
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.05), rgba(14, 165, 233, 0.12));
    border-color: #0EA5E9;
}
.vital-card.spo2-card:hover {
    border-color: #0284C7;
    box-shadow: 0 6px 20px rgba(14, 165, 233, 0.25);
}

.vital-card.purple::before { background: linear-gradient(90deg, #7B2FBE, #9B4DCA); }
.vital-card.purple .vital-icon { color: #7B2FBE; }
.vital-card.purple .vital-value { color: #7B2FBE; }

.vital-card.green::before { background: linear-gradient(90deg, #059669, #0AA84F); }
.vital-card.green .vital-icon { color: #059669; }
.vital-card.green .vital-value { color: #059669; }

.vital-card.indigo::before { background: linear-gradient(90deg, #4F46E5, #818CF8); }
.vital-card.indigo .vital-icon { color: #4F46E5; }
.vital-card.indigo .vital-value { color: #4F46E5; }

.spo2-status-badge {
    display: inline-block;
    font-size: 0.5rem;
    font-weight: 700;
    padding: 1px 8px;
    border-radius: 8px;
    margin-top: 3px;
    letter-spacing: 0.4px;
}
.spo2-status-badge.normal { background: #D1FAE5; color: #059669; border: 1px solid #6EE7B7; }
.spo2-status-badge.low { background: #FEF3C7; color: #D97706; border: 1px solid #FCD34D; }
.spo2-status-badge.critical { background: #FEE2E2; color: #DC2626; border: 1px solid #FCA5A5; }
.spo2-status-badge.unknown { background: var(--gray-200); color: var(--text-secondary); }

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
[data-theme="dark"] .vital-card.spo2-card .vital-value { color: #38BDF8; }
[data-theme="dark"] .vital-card.spo2-card { background: linear-gradient(135deg, rgba(14, 165, 233, 0.1), rgba(14, 165, 233, 0.2)); }

.spo2-footer-info {
    margin-top: 12px;
    padding: 8px 14px;
    background: linear-gradient(135deg, #F0F9FF, #E0F2FE);
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    font-size: 0.7rem;
    border: 1px dashed #0EA5E9;
}
[data-theme="dark"] .spo2-footer-info {
    background: #0C2A3A;
    border-color: #0EA5E9;
}

/* ================================================================
   TABLE CONTAINER
   ================================================================ */
.table-container {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    margin-bottom: 24px;
}

.table-container .card-header {
    padding: 14px 20px;
    background: var(--primary-gradient-strong);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.table-container .card-header .card-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: white;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.table-container .card-header .card-title i {
    color: rgba(255,255,255,0.8);
}

.table-container .card-header .card-action {
    color: rgba(255,255,255,0.7);
    font-size: 0.65rem;
    text-decoration: none;
    transition: all 0.3s;
}

.table-container .card-header .card-action:hover {
    color: white;
}

.data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.82rem;
}

.data-table thead th {
    background: var(--bg-body);
    color: var(--text-secondary);
    font-weight: 700;
    padding: 12px 14px;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 2px solid var(--border-color);
    text-align: left;
}

[data-theme="dark"] .data-table thead th {
    background: #0F172A;
}

.data-table td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
}

.data-table tbody tr:hover td {
    background: var(--table-hover);
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}

/* ================================================================
   BADGES
   ================================================================ */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
    color: white;
    letter-spacing: 0.02em;
}

.badge-success { background: #059669; }
.badge-danger { background: #DC2626; }
.badge-warning { background: #D97706; color: #1E293B; }
.badge-info { background: #0B5ED7; }
.badge-secondary { background: #64748B; }
.badge-purple { background: #7C3AED; }
.badge-teal { background: #0D9488; }
.badge-orange { background: #F59E0B; color: #1E293B; }

[data-theme="dark"] .badge-warning { color: #1E293B; }
[data-theme="dark"] .badge-orange { color: #1E293B; }

.status-badge {
    padding: 4px 16px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.status-badge.warning { background: #FEF3C7; color: #D97706; }
.status-badge.success { background: #D1FAE5; color: #059669; }
.status-badge.danger { background: #FEE2E2; color: #EF4444; }
.status-badge.info { background: #E8F0FE; color: #0B5ED7; }
.status-badge.primary { background: #DBEAFE; color: #2563EB; }
.status-badge.orange { background: #FED7AA; color: #EA580C; }
.status-badge.purple { background: #E9D5FF; color: #7B2FBE; }
.status-badge.secondary { background: #E2E8F0; color: #64748B; }

[data-theme="dark"] .status-badge.warning { background: #3A2A1A; color: #FBBF24; }
[data-theme="dark"] .status-badge.success { background: #1A3A2A; color: #34D399; }
[data-theme="dark"] .status-badge.danger { background: #3A1A1A; color: #F87171; }
[data-theme="dark"] .status-badge.info { background: #1E3A5F; color: #6EA8FE; }
[data-theme="dark"] .status-badge.primary { background: #1A2A4A; color: #60A5FA; }
[data-theme="dark"] .status-badge.orange { background: #3A2A1A; color: #FB923C; }
[data-theme="dark"] .status-badge.purple { background: #2A1A3A; color: #A78BFA; }
[data-theme="dark"] .status-badge.secondary { background: #2D3748; color: #94A3B8; }

.technician-tag {
    background: #E8F0FE;
    color: #0B5ED7;
    padding: 3px 12px;
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
    padding: 3px 12px;
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

/* ================================================================
   EMPTY STATE
   ================================================================ */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 2.5rem;
    color: var(--border-color);
    margin-bottom: 10px;
}

.empty-state p {
    font-size: 0.85rem;
    margin: 0;
}

/* ================================================================
   BUTTONS
   ================================================================ */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.78rem;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
    text-decoration: none;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

.btn-sm {
    padding: 5px 12px;
    font-size: 0.68rem;
}

/* ================================================================
   FOOTER
   ================================================================ */
.footer {
    padding: 14px 0;
    border-top: 2px solid var(--border-color);
    margin-top: 24px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.footer .footer-brand {
    color: var(--primary);
    font-weight: 700;
}

/* ================================================================
   RESPONSIVE
   ================================================================ */
@media (max-width: 1024px) {
    .vital-grid-7 { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.3rem; }
    .detail-card { padding: 16px; }
    .vital-grid-7 { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 480px) {
    .page-header { flex-direction: column; align-items: flex-start; }
    .detail-card { padding: 12px 14px; }
    .vital-grid-7 { grid-template-columns: repeat(2, 1fr); }
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in-up {
    animation: fadeInUp 0.5s ease forwards;
    opacity: 0;
}

@media print {
    .no-print, .btn, .btn-outline-light { display: none !important; }
    .main-content { margin: 0 !important; padding: 20px !important; }
    .page-header { background: #0A4CA8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .detail-card { border: 1px solid #ddd !important; page-break-inside: avoid; }
    .vital-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .footer { display: none !important; }
}
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-stethoscope"></i>
                Visit Details
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hashtag"></i>
                <strong><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-calendar-alt"></i>
                    <?= date('M d, Y', strtotime($visit['visit_date'])) ?>
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i>
                    <?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="edit_visit.php?id=<?= $visit['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="visits.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Visit Information -->
    <div class="detail-card animate-fade-in-up">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
            <div>
                <p class="detail-label"><i class="fas fa-hashtag mr-1"></i> Visit Number</p>
                <p class="detail-value" style="font-family:monospace;"><?= htmlspecialchars($visit['visit_number']) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-calendar-alt mr-1"></i> Visit Date</p>
                <p class="detail-value"><?= date('M d, Y h:i A', strtotime($visit['visit_date'])) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-tag mr-1"></i> Visit Type</p>
                <p class="detail-value">
                    <span class="badge badge-info"><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></span>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-circle mr-1"></i> Status</p>
                <p class="detail-value">
                    <span class="status-badge <?= $visit['status_color'] ?? 'secondary' ?>">
                        <?= ucfirst($visit['status'] ?? 'N/A') ?>
                    </span>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user-md mr-1"></i> Doctor</p>
                <p class="detail-value">
                    <?php if ($visit['doctor_name']): ?>
                        <span class="doctor-tag">
                            <i class="fas fa-user-md"></i> <?= htmlspecialchars($visit['doctor_name']) ?>
                        </span>
                    <?php else: ?>
                        <span style="color:var(--text-secondary);font-size:0.85rem;">Not assigned</span>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user-tie mr-1"></i> Receptionist</p>
                <p class="detail-value"><?= htmlspecialchars($visit['receptionist_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-store mr-1"></i> Branch</p>
                <p class="detail-value"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></p>
            </div>
            <?php if ($visit['follow_up_date']): ?>
                <div>
                    <p class="detail-label"><i class="fas fa-calendar-plus mr-1"></i> Follow-up Date</p>
                    <p class="detail-value"><?= date('M d, Y', strtotime($visit['follow_up_date'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Patient Information -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.05s;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
            <h3 style="font-size:0.9rem;font-weight:700;color:var(--primary);margin:0;">
                <i class="fas fa-user"></i> Patient Information
            </h3>
            <a href="view_patient.php?id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-external-link-alt"></i> View Patient
            </a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
            <div>
                <p class="detail-label"><i class="fas fa-user mr-1"></i> Patient Name</p>
                <p class="detail-value"><?= htmlspecialchars($visit['patient_name']) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-id-card mr-1"></i> Patient ID</p>
                <p class="detail-value" style="font-family:monospace;"><?= htmlspecialchars($visit['patient_number']) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-venus-mars mr-1"></i> Gender</p>
                <p class="detail-value"><?= htmlspecialchars($visit['gender'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-calendar-alt mr-1"></i> Date of Birth</p>
                <p class="detail-value"><?= $visit['date_of_birth'] ? date('M d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-tint mr-1"></i> Blood Group</p>
                <p class="detail-value">
                    <?= $visit['blood_group'] ? '<span class="badge badge-danger">' . htmlspecialchars($visit['blood_group']) . '</span>' : 'N/A' ?>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-exclamation-triangle mr-1"></i> Allergies</p>
                <p class="detail-value"><?= htmlspecialchars($visit['allergies'] ?? 'None') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-phone mr-1"></i> Phone</p>
                <p class="detail-value"><?= htmlspecialchars($visit['patient_phone'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-envelope mr-1"></i> Email</p>
                <p class="detail-value"><?= htmlspecialchars($visit['patient_email'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-chart-line mr-1"></i> Total Visits</p>
                <p class="detail-value">
                    <span class="badge badge-info"><?= $total_patient_visits ?></span>
                </p>
            </div>
        </div>
    </div>

    <!-- Symptoms & Diagnosis -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
            <div>
                <h4 style="font-size:0.85rem;font-weight:600;color:var(--text-secondary);margin-bottom:8px;">
                    <i class="fas fa-notes-medical" style="color:#F59E0B;"></i> Symptoms & Complaint
                </h4>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <div>
                        <p style="font-size:0.7rem;color:var(--text-secondary);margin:0;">Symptoms</p>
                        <p style="font-size:0.85rem;margin:4px 0 0 0;"><?= htmlspecialchars($visit['symptoms'] ?? 'None reported') ?></p>
                    </div>
                    <div>
                        <p style="font-size:0.7rem;color:var(--text-secondary);margin:0;">Complaint</p>
                        <p style="font-size:0.85rem;margin:4px 0 0 0;"><?= htmlspecialchars($visit['complaint'] ?? 'None reported') ?></p>
                    </div>
                </div>
            </div>
            <div>
                <h4 style="font-size:0.85rem;font-weight:600;color:var(--text-secondary);margin-bottom:8px;">
                    <i class="fas fa-diagnosis" style="color:#7B2FBE;"></i> Diagnosis & Treatment
                </h4>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <div>
                        <p style="font-size:0.7rem;color:var(--text-secondary);margin:0;">Diagnosis</p>
                        <p style="font-size:0.85rem;font-weight:600;color:#7B2FBE;margin:4px 0 0 0;">
                            <?= htmlspecialchars($visit['diagnosis'] ?? 'Not diagnosed yet') ?>
                        </p>
                    </div>
                    <div>
                        <p style="font-size:0.7rem;color:var(--text-secondary);margin:0;">Treatment</p>
                        <p style="font-size:0.85rem;margin:4px 0 0 0;"><?= htmlspecialchars($visit['treatment'] ?? 'Not prescribed yet') ?></p>
                    </div>
                    <?php if ($visit['notes']): ?>
                        <div>
                            <p style="font-size:0.7rem;color:var(--text-secondary);margin:0;">Notes</p>
                            <p style="font-size:0.85rem;margin:4px 0 0 0;"><?= htmlspecialchars($visit['notes']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Vital Signs -->
    <?php if ($vital_signs): 
        $spo2_status = getSpO2Status($vital_signs['oxygen_saturation'] ?? null);
    ?>
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.15s;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
            <h3 style="font-size:0.9rem;font-weight:700;color:var(--pink);margin:0;">
                <i class="fas fa-heartbeat"></i> Vital Signs (7 Signs)
                <span style="font-size:0.7rem;font-weight:400;color:#0284C7;margin-left:8px;">🫁 SpO2 Normal: 95-100%</span>
            </h3>
            <span style="font-size:0.7rem;color:var(--text-secondary);">
                Recorded: <?= date('M d, Y h:i A', strtotime($vital_signs['recorded_at'] ?? 'now')) ?>
            </span>
        </div>
        
        <div class="vital-grid-7">
            <!-- Temperature -->
            <div class="vital-card blue">
                <div class="vital-icon"><i class="fas fa-thermometer-half"></i></div>
                <div class="vital-value">
                    <?= $vital_signs['temperature'] ?? '-' ?>
                    <span class="vital-unit">°C</span>
                </div>
                <div class="vital-label">Temperature</div>
            </div>
            
            <!-- Blood Pressure -->
            <div class="vital-card red">
                <div class="vital-icon"><i class="fas fa-heart"></i></div>
                <div class="vital-value">
                    <?php 
                        $systolic = $vital_signs['blood_pressure_systolic'] ?? null;
                        $diastolic = $vital_signs['blood_pressure_diastolic'] ?? null;
                        echo ($systolic && $diastolic) ? "$systolic/$diastolic" : ($systolic ?: '-');
                    ?>
                    <span class="vital-unit">mmHg</span>
                </div>
                <div class="vital-label">Blood Pressure</div>
            </div>
            
            <!-- Pulse Rate -->
            <div class="vital-card pink">
                <div class="vital-icon"><i class="fas fa-heartbeat"></i></div>
                <div class="vital-value">
                    <?= $vital_signs['pulse_rate'] ?? '-' ?>
                    <span class="vital-unit">bpm</span>
                </div>
                <div class="vital-label">Pulse Rate</div>
            </div>
            
            <!-- SpO2 -->
            <div class="vital-card spo2-card">
                <div class="vital-icon"><i class="fas fa-lungs"></i></div>
                <div class="vital-value">
                    <?= ($vital_signs['oxygen_saturation'] !== null && $vital_signs['oxygen_saturation'] !== '') ? $vital_signs['oxygen_saturation'] : '--' ?>
                    <span class="vital-unit">%</span>
                </div>
                <div class="vital-label">Oxygen (SpO2)</div>
                <?php if ($vital_signs['oxygen_saturation'] !== null && $vital_signs['oxygen_saturation'] !== ''): ?>
                    <span class="spo2-status-badge <?= $spo2_status['class'] ?>"><?= $spo2_status['label'] ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Weight -->
            <div class="vital-card purple">
                <div class="vital-icon"><i class="fas fa-weight"></i></div>
                <div class="vital-value">
                    <?= $vital_signs['weight'] ?? '-' ?>
                    <span class="vital-unit">kg</span>
                </div>
                <div class="vital-label">Weight</div>
            </div>
            
            <!-- Height -->
            <div class="vital-card green">
                <div class="vital-icon"><i class="fas fa-ruler-vertical"></i></div>
                <div class="vital-value">
                    <?= $vital_signs['height'] ?? '-' ?>
                    <span class="vital-unit">cm</span>
                </div>
                <div class="vital-label">Height</div>
            </div>
            
            <!-- BMI -->
            <div class="vital-card indigo">
                <div class="vital-icon"><i class="fas fa-calculator"></i></div>
                <div class="vital-value"><?= $vital_signs['bmi'] ?? '-' ?></div>
                <div class="vital-label">BMI</div>
            </div>
        </div>
        
        <div class="spo2-footer-info">
            <i class="fas fa-lungs" style="color:#0EA5E9;"></i>
            <span style="color:#0284C7;">SpO2 (Oxygen Saturation) Normal Range: <strong>95-100%</strong></span>
            <span style="color:var(--text-secondary);"> • 7 Vital Signs Tracked</span>
        </div>
        
        <?php if ($vital_signs['notes']): ?>
        <div style="margin-top:12px;padding:12px;background:var(--bg-body);border-radius:8px;">
            <p style="font-size:0.7rem;color:var(--text-secondary);margin:0;">📝 Notes</p>
            <p style="font-size:0.85rem;margin:4px 0 0 0;"><?= htmlspecialchars($vital_signs['notes']) ?></p>
        </div>
        <?php endif; ?>
        
        <p style="font-size:0.7rem;color:var(--text-secondary);margin-top:8px;">
            <i class="fas fa-user"></i> Recorded by: <?= htmlspecialchars($vital_signs['recorded_by_name'] ?? 'N/A') ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- Lab Tests -->
    <?php if (count($visit_lab_tests) > 0): ?>
    <div class="table-container animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-flask" style="color:#F59E0B;"></i>
                Lab Tests & Results (<?= count($visit_lab_tests) ?>)
            </h3>
            <a href="lab_tests.php?visit_id=<?= $visit_id ?>" class="card-action">View All →</a>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th style="min-width:150px;">Test Name</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th style="min-width:140px;">Results</th>
                        <th style="min-width:120px;">Doctor</th>
                        <th style="min-width:120px;">Technician</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($visit_lab_tests as $test): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td style="font-weight:600;"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                            <td>TSh <?= number_format($test['test_price'] ?? 0) ?></td>
                            <td>
                                <span class="status-badge <?= $test['status_color'] ?? 'secondary' ?>" style="font-size:0.6rem;padding:3px 12px;">
                                    <?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'N/A')) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($test['results'])): ?>
                                    <span class="badge badge-info"><?= htmlspecialchars($test['results']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-warning">⏳ Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($test['doctor_name']): ?>
                                    <span class="doctor-tag"><i class="fas fa-user-md"></i> <?= htmlspecialchars($test['doctor_name']) ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-secondary);font-size:0.75rem;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($test['technician_name']): ?>
                                    <span class="technician-tag"><i class="fas fa-microscope"></i> <?= htmlspecialchars($test['technician_name']) ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-secondary);font-size:0.75rem;">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.75rem;"><?= date('M d, Y', strtotime($test['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Prescriptions -->
    <?php if (count($visit_prescriptions) > 0): ?>
    <div class="table-container animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-prescription" style="color:#7B2FBE;"></i>
                Prescriptions (<?= count($visit_prescriptions) ?>)
            </h3>
            <a href="prescriptions.php?visit_id=<?= $visit_id ?>" class="card-action">View All →</a>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Prescription #</th>
                        <th>Doctor</th>
                        <th>Diagnosis</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visit_prescriptions as $prescription): ?>
                        <tr>
                            <td style="font-family:monospace;font-size:0.75rem;"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($prescription['diagnosis'] ?? 'N/A') ?></td>
                            <td>
                                <span class="status-badge <?= $prescription['status_color'] ?? 'secondary' ?>" style="font-size:0.6rem;">
                                    <?= ucfirst($prescription['status'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td style="font-size:0.75rem;"><?= date('M d, Y', strtotime($prescription['created_at'])) ?></td>
                        </tr>
                        <?php if (isset($prescription_items[$prescription['id']]) && count($prescription_items[$prescription['id']]) > 0): ?>
                            <tr style="background:var(--bg-body);">
                                <td colspan="5" style="padding:8px 14px;font-size:0.75rem;">
                                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                        <span style="font-size:0.75rem;color:var(--text-secondary);margin-right:4px;">💊 Medications:</span>
                                        <?php foreach ($prescription_items[$prescription['id']] as $item): ?>
                                            <span class="badge badge-purple" style="font-size:0.6rem;">
                                                <?= htmlspecialchars($item['medication_name']) ?>
                                                (<?= $item['quantity'] ?? 0 ?> x TSh <?= number_format($item['unit_price'] ?? 0) ?>)
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Procedures & Tools -->
    <?php if (count($procedure_tools) > 0): ?>
    <div class="table-container animate-fade-in-up" style="animation-delay:0.3s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-syringe" style="color:#0D9488;"></i>
                Procedures & Tools Used (<?= count($procedure_tools) ?>)
            </h3>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Type</th>
                        <th>Unit Price</th>
                        <th>Quantity</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($procedure_tools as $item): ?>
                        <tr>
                            <td style="font-weight:600;"><?= htmlspecialchars($item['item_name']) ?></td>
                            <td>
                                <span class="badge badge-teal">
                                    <?= ucfirst($item['item_type'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td>TSh <?= number_format($item['unit_price'] ?? 0) ?></td>
                            <td><?= $item['quantity'] ?? 1 ?></td>
                            <td style="font-weight:600;">TSh <?= number_format($item['total_price'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bills -->
    <?php if (count($visit_bills) > 0): ?>
    <div class="table-container animate-fade-in-up" style="animation-delay:0.35s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-invoice" style="color:#0B5ED7;"></i>
                Bills
                <span style="font-size:0.8rem;font-weight:400;color:rgba(255,255,255,0.7);margin-left:8px;">
                    | Total: TSh <?= number_format($total_bill_amount) ?>
                </span>
                <span class="status-badge <?= 
                    $overall_status === 'paid' ? 'success' : 
                    ($overall_status === 'partial' ? 'info' : 
                    ($overall_status === 'cancelled' ? 'danger' : 'warning')) 
                ?>" style="font-size:0.65rem;margin-left:8px;">
                    <?= ucfirst($overall_status) ?>
                </span>
            </h3>
            <a href="bills.php?visit_id=<?= $visit_id ?>" class="card-action">View All →</a>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Items</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visit_bills as $bill): ?>
                        <tr>
                            <td style="font-family:monospace;font-size:0.75rem;"><?= htmlspecialchars($bill['bill_number']) ?></td>
                            <td style="font-weight:600;">TSh <?= number_format($bill['total_amount'] ?? 0) ?></td>
                            <td>TSh <?= number_format($bill['paid_amount'] ?? 0) ?></td>
                            <td>
                                <?php if (($bill['balance'] ?? 0) > 0): ?>
                                    <span style="color:#DC2626;font-weight:600;">TSh <?= number_format($bill['balance'], 0) ?></span>
                                <?php else: ?>
                                    <span style="color:#059669;">TSh 0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $bill['status_color'] ?? 'secondary' ?>" style="font-size:0.6rem;">
                                    <?= ucfirst($bill['status'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td style="font-size:0.75rem;"><?= date('M d, Y', strtotime($bill['created_at'])) ?></td>
                            <td>
                                <?php if (isset($all_bill_items[$bill['id']]) && count($all_bill_items[$bill['id']]) > 0): ?>
                                    <div style="display:flex;flex-wrap:wrap;gap:3px;">
                                        <?php foreach ($all_bill_items[$bill['id']] as $item): ?>
                                            <span class="badge badge-info" style="font-size:0.55rem;">
                                                <?= htmlspecialchars($item['item_name']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color:var(--text-secondary);font-size:0.75rem;">No items</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:var(--border-color);margin:0 8px;">|</span>
            Visit Details - <?= htmlspecialchars($visit['visit_number'] ?? 'Visit') ?>
            <span style="color:var(--border-color);margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:var(--border-color);margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
// DATE & TIME - footer
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

console.log('%c🏥 Braick - Visit Details (BLUE THEME)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Inatumia SHARED HEADER + SIDEBAR pekee', 'font-size:13px; color:#34D399;');
console.log('%c📋 Visit: <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
console.log('%c👤 Patient: <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
console.log('%c❤️ 7 Vital Signs: Temp, BP, Pulse, SpO2, Weight, Height, BMI', 'font-size:13px; color:#EC4899;');
console.log('%c🫁 SpO2 (Oxygen Saturation): Normal 95-100%', 'font-size:13px; color:#0EA5E9;');
</script>

</body>
</html>