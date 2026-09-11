<?php
// ================================================================
// FILE: frontend/pages/admin/prescriptions.php
// PRESCRIPTIONS LIST - GROUPED BY PATIENT
// FIXED: Search Bar LEFT (BLUE) + Auto Search + MEDICINE HIGHLIGHT + Delete All
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
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
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
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
// HANDLE DELETE ALL PRESCRIPTIONS FOR A PATIENT
// ================================================================
if (isset($_GET['delete_patient']) && is_numeric($_GET['delete_patient'])) {
    $patient_id = (int)$_GET['delete_patient'];
    $branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("
            SELECT p.id, p.prescription_number, pat.full_name as patient_name
            FROM prescriptions p
            LEFT JOIN patients pat ON p.patient_id = pat.id
            WHERE p.patient_id = ?
        ");
        $stmt->execute([$patient_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($prescriptions)) {
            $db->rollBack();
            header('Location: prescriptions.php?branch=' . urlencode($branch_id) . '&page=' . $page . '&error=no_prescriptions');
            exit;
        }
        
        $patient_name = $prescriptions[0]['patient_name'] ?? 'Unknown';
        $prescription_ids = array_column($prescriptions, 'id');
        
        error_log("🗑️ Deleting ALL prescriptions for patient: $patient_name (" . count($prescription_ids) . " prescriptions)");
        
        $placeholders = implode(',', array_fill(0, count($prescription_ids), '?'));
        
        $stmt = $db->prepare("DELETE FROM prescription_items WHERE prescription_id IN ($placeholders)");
        $stmt->execute($prescription_ids);
        
        $stmt = $db->prepare("DELETE FROM prescriptions WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        $prescriptions_deleted = $stmt->rowCount();
        
        $stmt = $db->prepare("DELETE FROM bill_items WHERE reference_id IN ($placeholders) AND reference_type = 'prescription'");
        $stmt->execute($prescription_ids);
        
        $db->commit();
        
        $redirect_url = 'prescriptions.php?';
        $params = [];
        if ($branch_id !== 'all') $params[] = 'branch=' . urlencode($branch_id);
        if (!empty($search)) $params[] = 'search=' . urlencode($search);
        if (!empty($status_filter)) $params[] = 'status=' . urlencode($status_filter);
        if ($page > 1) $params[] = 'page=' . $page;
        $params[] = 'deleted_patient=1';
        $params[] = 'count=' . $prescriptions_deleted;
        $params[] = 'patient=' . urlencode($patient_name);
        $redirect_url .= implode('&', $params);
        
        header('Location: ' . $redirect_url);
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("❌ Delete all error: " . $e->getMessage());
        
        $redirect_url = 'prescriptions.php?';
        $params = [];
        if ($branch_id !== 'all') $params[] = 'branch=' . urlencode($branch_id);
        if (!empty($search)) $params[] = 'search=' . urlencode($search);
        if (!empty($status_filter)) $params[] = 'status=' . urlencode($status_filter);
        if ($page > 1) $params[] = 'page=' . $page;
        $params[] = 'error=delete_failed';
        $redirect_url .= implode('&', $params);
        
        header('Location: ' . $redirect_url);
        exit;
    }
}

// ================================================================
// VARIABLES
// ================================================================
$message = '';
$message_type = '';
$per_page = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_branch_name = 'All Branches';
if ($selected_branch_id !== 'all') {
    foreach ($branches_list as $b) {
        if ($b['id'] == $selected_branch_id) {
            $selected_branch_name = $b['name'];
            break;
        }
    }
}

// ================================================================
// BUILD QUERY - GROUPED BY PATIENT
// ================================================================
$where_clause = " WHERE 1=1";
$params = [];

if (!empty($search)) {
    // ✅ Search includes medication names
    $where_clause .= " AND (p.prescription_number LIKE ? OR pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.diagnosis LIKE ? OR EXISTS (SELECT 1 FROM prescription_items pi WHERE pi.prescription_id = p.id AND pi.medication_name LIKE ?))";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_clause .= " AND p.status = ?";
    $params[] = $status_filter;
}

if ($selected_branch_id !== 'all') {
    $where_clause .= " AND p.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

// ================================================================
// GET PRESCRIPTIONS - GROUPED BY PATIENT
// ================================================================
$count_sql = "
    SELECT COUNT(DISTINCT pat.id) as total 
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    $where_clause
";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_patients / $per_page);

$sql = "
    SELECT 
        pat.id as patient_id,
        pat.full_name as patient_name,
        pat.patient_id as patient_number,
        pat.phone as patient_phone,
        pat.gender as patient_gender,
        pat.date_of_birth,
        MAX(p.created_at) as last_prescription_date,
        COUNT(p.id) as prescription_count,
        GROUP_CONCAT(DISTINCT p.id ORDER BY p.id DESC) as prescription_ids,
        GROUP_CONCAT(DISTINCT p.prescription_number ORDER BY p.id DESC SEPARATOR '|') as prescription_numbers,
        GROUP_CONCAT(DISTINCT p.status ORDER BY p.id DESC SEPARATOR '|') as prescription_statuses,
        GROUP_CONCAT(DISTINCT u.full_name ORDER BY p.id DESC SEPARATOR '|') as doctor_names,
        GROUP_CONCAT(DISTINCT p.created_at ORDER BY p.id DESC SEPARATOR '|') as created_dates,
        MAX(CASE WHEN p.status != 'cancelled' THEN p.status END) as overall_status,
        SUM(CASE 
            WHEN p.status != 'cancelled' 
            THEN COALESCE((SELECT SUM(pi.total_price) FROM prescription_items pi WHERE pi.prescription_id = p.id), 0)
            ELSE 0 
        END) as total_amount,
        GROUP_CONCAT(
            CONCAT(
                p.id, ':', 
                p.prescription_number, ':', 
                p.status, ':',
                COALESCE((SELECT SUM(pi.total_price) FROM prescription_items pi WHERE pi.prescription_id = p.id), 0), ':',
                COALESCE((SELECT GROUP_CONCAT(CONCAT(pi.medication_name, ' (', pi.quantity, ' pcs)') SEPARATOR ', ') 
                          FROM prescription_items pi WHERE pi.prescription_id = p.id LIMIT 20), 'No medications')
            ) 
            ORDER BY p.id DESC SEPARATOR '||'
        ) as prescription_data
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.doctor_id = u.id
    $where_clause
    GROUP BY pat.id, pat.full_name, pat.patient_id, pat.phone, pat.gender, pat.date_of_birth
    ORDER BY last_prescription_date DESC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;
$stmt = $db->prepare($sql);
$stmt->execute($params);
$grouped_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Parse prescription data for each patient
foreach ($grouped_prescriptions as &$group) {
    $group['prescriptions'] = [];
    if (!empty($group['prescription_data'])) {
        $items = explode('||', $group['prescription_data']);
        foreach ($items as $item) {
            $parts = explode(':', $item);
            if (count($parts) >= 5) {
                $group['prescriptions'][] = [
                    'id' => $parts[0],
                    'number' => $parts[1],
                    'status' => $parts[2],
                    'amount' => $parts[3],
                    'medications' => $parts[4]
                ];
            }
        }
    }
    
    // Build search data
    $search_parts = [
        $group['patient_name'] ?? '',
        $group['patient_number'] ?? '',
        $group['patient_phone'] ?? '',
        $group['patient_gender'] ?? ''
    ];
    
    $all_meds_text = '';
    foreach ($group['prescriptions'] as $presc) {
        if (!empty($presc['medications']) && $presc['medications'] !== 'No medications') {
            $all_meds_text .= ' ' . $presc['medications'];
        }
    }
    $search_parts[] = $all_meds_text;
    
    $group['search_data'] = strtolower(implode(' ', $search_parts));
    
    $status = $group['overall_status'] ?? 'pending';
    $status_colors = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'dispensed' => 'success',
        'cancelled' => 'danger'
    ];
    $group['status_color'] = $status_colors[$status] ?? 'secondary';
    $group['status_label'] = ucfirst($status);
}
unset($group);

// ================================================================
// STATISTICS
// ================================================================
$stats_where = " WHERE 1=1";
$stats_params = [];

if ($selected_branch_id !== 'all') {
    $stats_where .= " AND p.branch_id = ?";
    $stats_params[] = (int)$selected_branch_id;
}

$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM prescriptions p $stats_where");
$stmt->execute($stats_params);
$total_all = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$pending_where = $stats_where . " AND p.status = 'pending'";
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM prescriptions p $pending_where");
$stmt->execute($stats_params);
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$confirmed_where = $stats_where . " AND p.status = 'confirmed'";
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM prescriptions p $confirmed_where");
$stmt->execute($stats_params);
$confirmed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$dispensed_where = $stats_where . " AND p.status = 'dispensed'";
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM prescriptions p $dispensed_where");
$stmt->execute($stats_params);
$dispensed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$cancelled_where = $stats_where . " AND p.status = 'cancelled'";
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM prescriptions p $cancelled_where");
$stmt->execute($stats_params);
$cancelled_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$amount_sql = "SELECT COALESCE(SUM(pi.total_price), 0) as total_amount FROM prescription_items pi INNER JOIN prescriptions p ON pi.prescription_id = p.id $stats_where AND p.status = 'dispensed'";
$stmt = $db->prepare($amount_sql);
$stmt->execute($stats_params);
$total_amount_all = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

$pending_amount_sql = "SELECT COALESCE(SUM(pi.total_price), 0) as total_amount FROM prescription_items pi INNER JOIN prescriptions p ON pi.prescription_id = p.id $stats_where AND p.status = 'pending'";
$stmt = $db->prepare($pending_amount_sql);
$stmt->execute($stats_params);
$pending_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

$confirmed_amount_sql = "SELECT COALESCE(SUM(pi.total_price), 0) as total_amount FROM prescription_items pi INNER JOIN prescriptions p ON pi.prescription_id = p.id $stats_where AND p.status = 'confirmed'";
$stmt = $db->prepare($confirmed_amount_sql);
$stmt->execute($stats_params);
$confirmed_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

$dispensed_amount_sql = "SELECT COALESCE(SUM(pi.total_price), 0) as total_amount FROM prescription_items pi INNER JOIN prescriptions p ON pi.prescription_id = p.id $stats_where AND p.status = 'dispensed'";
$stmt = $db->prepare($dispensed_amount_sql);
$stmt->execute($stats_params);
$dispensed_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

$cancelled_amount_sql = "SELECT COALESCE(SUM(pi.total_price), 0) as total_amount FROM prescription_items pi INNER JOIN prescriptions p ON pi.prescription_id = p.id $stats_where AND p.status = 'cancelled'";
$stmt = $db->prepare($cancelled_amount_sql);
$stmt->execute($stats_params);
$cancelled_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<style>
    .stats-grid-5 { display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; margin-bottom: 24px; }
    .stat-card-custom {
        border-radius: 14px; padding: 16px 18px; border: none;
        display: flex; flex-direction: column;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        color: white; position: relative; overflow: hidden;
        min-height: 110px; cursor: default;
    }
    .stat-card-custom::before {
        content: ''; position: absolute; top: -50%; right: -20%;
        width: 160px; height: 160px;
        background: rgba(255,255,255,0.06); border-radius: 50%;
        pointer-events: none; transition: all 0.5s ease;
    }
    .stat-card-custom:hover { transform: translateY(-4px) scale(1.01); box-shadow: 0 10px 32px rgba(0,0,0,0.2); }
    .stat-card-custom:hover::before { transform: scale(1.3); right: -10%; }
    
    .stat-card-custom .stat-icon {
        width: 44px; height: 44px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem; flex-shrink: 0;
        background: rgba(255,255,255,0.18); color: white;
        border: 1px solid rgba(255,255,255,0.12);
        backdrop-filter: blur(8px); margin-bottom: 4px;
        position: relative; z-index: 1;
    }
    .stat-card-custom .stat-content { position: relative; z-index: 1; flex: 1; display: flex; flex-direction: column; }
    .stat-card-custom .stat-label { font-size: 0.6rem; color: rgba(255,255,255,0.85); font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; margin: 0 0 1px 0; }
    .stat-card-custom .stat-number { font-size: 2.2rem; font-weight: 800; color: white; margin: 0; line-height: 1.1; }
    .stat-card-custom .stat-amount { font-size: 0.9rem; font-weight: 600; color: rgba(255,255,255,0.9); margin-top: 2px; }
    .stat-card-custom .stat-sub { font-size: 0.6rem; color: rgba(255,255,255,0.8); margin-top: 2px; }
    .stat-card-custom .stat-arrow { position: absolute; right: 12px; bottom: 12px; color: rgba(255,255,255,0.12); font-size: 0.7rem; z-index: 1; }
    .stat-card-custom:hover .stat-arrow { transform: translateX(6px); color: rgba(255,255,255,0.4); }
    
    .card-blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .card-orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .card-green { background: linear-gradient(135deg, #059669, #047857); }
    .card-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    
    .table-container { position: relative; overflow: hidden; border-radius: 12px; }
    .table-scroll-wrapper { overflow-x: auto; overflow-y: visible; -webkit-overflow-scrolling: touch; scroll-behavior: smooth; position: relative; }
    .table-scroll-wrapper::-webkit-scrollbar { height: 8px; }
    .table-scroll-wrapper::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 10px; }
    .table-scroll-wrapper::-webkit-scrollbar-thumb { background: #0B5ED7; border-radius: 10px; }
    .table-scroll-wrapper::-webkit-scrollbar-thumb:hover { background: #0A4CA8; }
    
    /* ================================================================
       TABLE HEADER BAR: SEARCH LEFT (BLUE) + SCROLL RIGHT
       ================================================================ */
    .table-header-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 2px solid var(--border-color);
        background: var(--bg-card);
    }
    .table-header-left {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        flex: 1;
        min-width: 0;
    }
    .table-header-right {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        flex-shrink: 0;
    }
    
    /* BLUE SEARCH BOX - LEFT SIDE */
    .table-search-box {
        position: relative;
        min-width: 300px;
        flex: 1;
        max-width: 450px;
    }
    .table-search-box i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(255,255,255,0.95);
        font-size: 0.85rem;
        pointer-events: none;
        z-index: 1;
    }
    .table-search-box input {
        width: 100%;
        padding: 10px 14px 10px 42px;
        border: 2px solid #0A4CA8;
        border-radius: 10px;
        font-size: 0.85rem;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        outline: none;
        transition: all 0.3s ease;
        font-weight: 500;
        height: 42px;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
    }
    .table-search-box input::placeholder { color: rgba(255,255,255,0.85); font-weight: 400; }
    .table-search-box input:focus {
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.25), 0 6px 20px rgba(11, 94, 215, 0.35);
        transform: translateY(-1px);
    }
    
    .table-title-inline {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
        white-space: nowrap;
    }
    .table-title-inline i { color: #0B5ED7; }
    
    .search-results-info {
        font-size: 0.7rem;
        color: #0B5ED7;
        padding: 6px 12px;
        background: #E8F0FE;
        border-radius: 8px;
        white-space: nowrap;
        display: none;
        font-weight: 600;
        border: 1px solid #0B5ED7;
    }
    .search-results-info.show { display: inline-flex; align-items: center; gap: 4px; }
    .search-results-info strong { color: #0B5ED7; font-size: 0.8rem; }
    
    .scroll-btn-header {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        font-size: 0.8rem;
    }
    .scroll-btn-header:hover {
        background: #0B5ED7;
        border-color: #0B5ED7;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    .scroll-btn-header:disabled {
        opacity: 0.35;
        cursor: not-allowed;
        transform: none;
    }
    .scroll-btn-header:disabled:hover {
        background: var(--bg-card);
        border-color: var(--border-color);
        color: var(--text-primary);
        box-shadow: none;
    }
    
    /* HIGHLIGHT STYLES */
    mark.highlight {
        background: #FEF08A;
        color: #854D0E;
        padding: 1px 3px;
        border-radius: 3px;
        font-weight: 700;
        box-shadow: 0 0 0 1px #FDE047;
    }
    [data-theme="dark"] mark.highlight {
        background: #854D0E;
        color: #FEF08A;
        box-shadow: 0 0 0 1px #A16207;
    }
    mark.highlight-med {
        background: #FED7AA;
        color: #9A3412;
        padding: 1px 4px;
        border-radius: 4px;
        font-weight: 700;
        box-shadow: 0 0 0 1px #FDBA74;
    }
    [data-theme="dark"] mark.highlight-med {
        background: #7C2D12;
        color: #FED7AA;
        box-shadow: 0 0 0 1px #9A3412;
    }
    
    tr.presc-row.highlighted td:first-child {
        box-shadow: inset 4px 0 0 0 #FBBF24;
    }
    tr.presc-row.highlighted {
        background: rgba(254, 240, 138, 0.15) !important;
    }
    [data-theme="dark"] tr.presc-row.highlighted {
        background: rgba(133, 77, 14, 0.15) !important;
    }
    
    .medication-item.highlighted-med {
        background: #FED7AA !important;
        border-color: #FDBA74 !important;
        box-shadow: 0 0 0 2px #FED7AA !important;
    }
    
    .medication-list {
        font-size: 0.7rem;
        color: var(--text-secondary);
        line-height: 1.4;
        max-height: 60px;
        overflow-y: auto;
        padding-right: 4px;
    }
    .medication-list::-webkit-scrollbar { width: 3px; }
    .medication-list::-webkit-scrollbar-thumb { background: #0B5ED7; border-radius: 4px; }
    
    .medication-item {
        display: inline-block;
        background: var(--bg-body);
        padding: 1px 6px;
        border-radius: 4px;
        margin: 1px 2px 1px 0;
        font-size: 0.6rem;
        border: 1px solid var(--border-color);
    }
    .medication-item .qty { font-weight: 600; color: #0B5ED7; }
    
    .table-blue thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8) !important;
        color: #FFFFFF !important;
        font-weight: 700 !important;
        font-size: 0.65rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        padding: 10px 14px !important;
        border-bottom: 3px solid #0A4CA8 !important;
        white-space: nowrap !important;
        position: sticky;
        top: 0;
        z-index: 5;
    }
    .table-blue thead th:first-child { border-radius: 8px 0 0 0 !important; }
    .table-blue thead th:last-child { border-radius: 0 8px 0 0 !important; }
    .table-blue tbody td {
        padding: 8px 14px !important;
        border-bottom: 1px solid #E2E8F0 !important;
        color: #1E293B !important;
        vertical-align: middle !important;
        font-size: 0.82rem;
    }
    .table-blue tbody tr:hover td { background: #E8F0FE !important; }
    [data-theme="dark"] .table-blue tbody td { color: #F1F5F9 !important; border-bottom-color: #334155 !important; }
    [data-theme="dark"] .table-blue tbody tr:hover td { background: #1A2A4A !important; }
    
    .amount-cell { font-weight: 700; color: #0B5ED7; font-family: 'Courier New', monospace; font-size: 0.85rem; }
    [data-theme="dark"] .amount-cell { color: #60A5FA; }
    
    .action-buttons-vertical {
        display: flex; flex-direction: column; gap: 6px;
        align-items: center; justify-content: center;
    }
    .btn-action {
        display: inline-flex; align-items: center; justify-content: center;
        gap: 5px; padding: 5px 14px; border-radius: 6px;
        font-size: 0.65rem; font-weight: 600;
        text-decoration: none; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: none; cursor: pointer; min-width: 80px; width: 100%;
    }
    .btn-action:hover { transform: translateY(-2px) scale(1.03); }
    .btn-action:active { transform: scale(0.95); }
    
    .btn-view {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
    }
    .btn-view:hover { box-shadow: 0 6px 20px rgba(11, 94, 215, 0.4); }
    
    .btn-delete-all {
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        color: white;
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
        border: 1px solid rgba(255,255,255,0.15);
    }
    .btn-delete-all:hover {
        box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
        transform: translateY(-2px) scale(1.02);
    }
    .btn-delete-all .count-badge {
        background: rgba(255,255,255,0.2);
        padding: 0 8px; border-radius: 10px;
        font-size: 0.55rem; margin-left: 4px;
    }
    
    .status-badge {
        padding: 3px 12px; border-radius: 20px;
        font-size: 0.6rem; font-weight: 600;
        display: inline-flex; align-items: center; gap: 4px;
    }
    .status-badge.warning { background: #FEF3C7; color: #D97706; }
    .status-badge.success { background: #D1FAE5; color: #059669; }
    .status-badge.danger { background: #FEE2E2; color: #EF4444; }
    .status-badge.info { background: #E8F0FE; color: #0B5ED7; }
    .status-badge.secondary { background: #E2E8F0; color: #64748B; }
    [data-theme="dark"] .status-badge.warning { background: #3A2A1A; color: #FBBF24; }
    [data-theme="dark"] .status-badge.success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge.danger { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .status-badge.info { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge.secondary { background: #2D3748; color: #94A3B8; }
    
    .prescription-tag {
        display: inline-block; font-size: 0.6rem;
        padding: 1px 8px; border-radius: 10px;
        background: var(--bg-body); color: var(--text-secondary);
        border: 1px solid var(--border-color);
        margin: 1px 2px; font-family: monospace;
        white-space: nowrap;
    }
    .prescription-tag .status-dot {
        display: inline-block; width: 6px; height: 6px;
        border-radius: 50%; margin-right: 3px;
    }
    .prescription-tag .status-dot.pending { background: #D97706; }
    .prescription-tag .status-dot.confirmed { background: #0B5ED7; }
    .prescription-tag .status-dot.dispensed { background: #059669; }
    .prescription-tag .status-dot.cancelled { background: #EF4444; }
    
    .filter-section {
        background: var(--bg-card); border-radius: 14px;
        padding: 14px 18px; border: 1px solid var(--border-color);
        margin-bottom: 18px; display: flex; flex-wrap: wrap;
        align-items: center; gap: 8px;
    }
    .filter-section .filter-label { font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); margin-right: 4px; }
    .filter-btn {
        padding: 4px 14px; border-radius: 20px;
        font-size: 0.7rem; font-weight: 500;
        border: 2px solid var(--border-color); background: transparent;
        color: var(--text-secondary); cursor: pointer;
        transition: all 0.3s ease; text-decoration: none;
        display: inline-block;
    }
    .filter-btn:hover { border-color: #0B5ED7; color: #0B5ED7; background: #E8F0FE; }
    .filter-btn.active { background: #0B5ED7; color: white; border-color: #0B5ED7; }
    .filter-btn.active:hover { background: #0A4CA8; border-color: #0A4CA8; }
    [data-theme="dark"] .filter-btn:hover { background: #1A2A4A; border-color: #3B82F6; color: #3B82F6; }
    .filter-btn i { margin-right: 4px; }
    
    .card {
        background: var(--bg-card); border-radius: 16px;
        padding: 18px 20px; border: 1px solid var(--border-color);
        transition: all 0.3s;
    }
    .card:hover { border-color: #0B5ED7; box-shadow: 0 4px 12px rgba(11, 94, 215, 0.05); }
    .card-header {
        display: flex; justify-content: space-between;
        align-items: center; margin-bottom: 12px;
        flex-wrap: wrap; gap: 8px;
    }
    .card-title { font-size: 0.9rem; font-weight: 600; color: var(--text-primary); }
    .title-blue { color: #0B5ED7; }
    
    .pagination { display: flex; gap: 4px; flex-wrap: wrap; }
    .pagination .page-link {
        padding: 6px 12px; border-radius: 6px;
        border: 1px solid var(--border-color);
        color: var(--text-primary); text-decoration: none;
        font-size: 0.8rem; transition: all 0.3s;
        background: var(--bg-card);
    }
    .pagination .page-link:hover { background: #0B5ED7; color: white; border-color: #0B5ED7; }
    .pagination .page-link.active { background: #0B5ED7; color: white; border-color: #0B5ED7; }
    .pagination .page-link.disabled { opacity: 0.5; cursor: not-allowed; }
    
    .page-header {
        background: #0B5ED7 !important; border-radius: 16px !important;
        padding: 20px 28px !important; margin-bottom: 20px !important;
        display: flex !important; flex-wrap: wrap !important;
        justify-content: space-between !important; align-items: center !important;
        gap: 12px !important;
        box-shadow: 0 4px 24px rgba(11, 94, 215, 0.25) !important;
    }
    .page-header .page-title { color: white !important; font-size: 1.4rem !important; font-weight: 700 !important; }
    .page-header .page-title i { color: white !important; }
    .page-header .page-subtitle { color: rgba(255,255,255,0.85) !important; font-size: 0.85rem !important; }
    .page-header .branch-tag { background: rgba(255,255,255,0.2) !important; color: white !important; padding: 3px 14px !important; border-radius: 20px !important; font-size: 0.7rem !important; font-weight: 600 !important; }
    
    .toast-custom {
        position: fixed; bottom: 24px; right: 24px;
        padding: 12px 18px; border-radius: 12px; z-index: 999;
        max-width: 360px; transform: translateY(100px); opacity: 0;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex; align-items: center; gap: 10px; color: white;
    }
    .toast-custom.show { transform: translateY(0); opacity: 1; }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #EF4444; }
    .toast-custom.info { background: #0B5ED7; }
    
    .footer {
        padding: 14px 0; border-top: 2px solid var(--border-color);
        margin-top: 20px; text-align: center;
        font-size: 0.7rem; color: var(--text-secondary);
    }
    .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
    
    .col-prescriptions { min-width: 180px; max-width: 250px; }
    .col-actions { min-width: 90px; text-align: center; }
    
    .no-results-row td {
        text-align: center;
        padding: 30px 20px !important;
        color: var(--text-secondary);
    }
    .no-results-row i {
        font-size: 2rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 8px;
    }
    
    @media (max-width: 1200px) { .stats-grid-5 { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 768px) {
        .stats-grid-5 { grid-template-columns: 1fr 1fr; }
        .stat-card-custom .stat-number { font-size: 1.6rem; }
        .stat-card-custom { min-height: 90px; padding: 14px 16px; }
        .stat-card-custom .stat-icon { width: 38px; height: 38px; font-size: 0.9rem; }
        .filter-section { flex-direction: column; align-items: stretch; }
        .btn-action { font-size: 0.55rem; padding: 3px 10px; min-width: 60px; }
        .table-blue tbody td { font-size: 0.7rem; padding: 6px 10px !important; }
        .scroll-btn-header { width: 32px; height: 32px; font-size: 0.7rem; }
        .col-actions { min-width: 70px; }
        .table-header-bar { flex-direction: column; align-items: stretch; }
        .table-header-left { width: 100%; flex-direction: column; align-items: stretch; }
        .table-search-box { min-width: 100%; max-width: 100%; }
        .table-header-right { width: 100%; justify-content: flex-end; }
    }
    @media (max-width: 480px) {
        .stats-grid-5 { grid-template-columns: 1fr 1fr; }
        .stat-card-custom .stat-number { font-size: 1.4rem; }
        .stat-card-custom { min-height: 80px; padding: 12px 14px; }
        .stat-card-custom .stat-icon { width: 32px; height: 32px; font-size: 0.8rem; }
        .page-header { padding: 14px 16px !important; }
        .page-header .page-title { font-size: 1rem !important; }
        .scroll-btn-header { width: 28px; height: 28px; font-size: 0.65rem; }
        .col-actions { min-width: 60px; }
    }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
</style>

<!-- TOP NAVIGATION -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper" style="display:none;">
            <!-- Hidden top search - now using table search bar -->
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
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- MAIN CONTENT -->
<main class="main-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription mr-2"></i> Prescriptions
                <?php if ($selected_branch_id !== 'all'): ?>
                    <span class="branch-tag ml-2">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($selected_branch_name) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                Manage all prescriptions - Grouped by patient
                <span class="branch-tag ml-2">
                    <i class="fas fa-users"></i> <?= $total_all ?> Patients
                </span>
                <span class="ml-2 inline-flex bg-white/20 text-white px-3 py-1 rounded-full text-xs border border-white/10">
                    <i class="fas fa-money-bill-wave mr-1"></i> TSh <?= number_format($total_amount_all) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="dashboard.php" class="btn btn-outline-light" style="background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.2);padding:6px 16px;border-radius:10px;font-size:0.8rem;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- 5 CARDS -->
    <div class="stats-grid-5 animate-fade-in-up">
        <div class="stat-card-custom card-blue">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Patients</p>
                <p class="stat-number"><?= $total_all ?></p>
                <p class="stat-amount">TSh <?= number_format($total_amount_all, 0) ?></p>
                <p class="stat-sub">All prescriptions</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <div class="stat-card-custom card-orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-content">
                <p class="stat-label">Pending</p>
                <p class="stat-number"><?= $pending_count ?></p>
                <p class="stat-amount">TSh <?= number_format($pending_amount, 0) ?></p>
                <p class="stat-sub">Awaiting approval</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <div class="stat-card-custom card-blue">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Confirmed</p>
                <p class="stat-number"><?= $confirmed_count ?></p>
                <p class="stat-amount">TSh <?= number_format($confirmed_amount, 0) ?></p>
                <p class="stat-sub">Approved</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <div class="stat-card-custom card-green">
            <div class="stat-icon"><i class="fas fa-pills"></i></div>
            <div class="stat-content">
                <p class="stat-label">Dispensed</p>
                <p class="stat-number"><?= $dispensed_count ?></p>
                <p class="stat-amount">TSh <?= number_format($dispensed_amount, 0) ?></p>
                <p class="stat-sub">Completed</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <div class="stat-card-custom card-red">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Cancelled</p>
                <p class="stat-number"><?= $cancelled_count ?></p>
                <p class="stat-amount">TSh <?= number_format($cancelled_amount, 0) ?></p>
                <p class="stat-sub">Voided</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filter-section animate-fade-in-up" style="animation-delay:0.05s;">
        <span class="filter-label"><i class="fas fa-filter"></i> Status:</span>
        
        <a href="?branch=<?= $selected_branch_id ?>&status=&page=1" class="filter-btn <?= empty($status_filter) ? 'active' : '' ?>">
            <i class="fas fa-globe"></i> All
        </a>
        <a href="?branch=<?= $selected_branch_id ?>&status=pending&page=1" class="filter-btn <?= $status_filter === 'pending' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i> Pending
        </a>
        <a href="?branch=<?= $selected_branch_id ?>&status=confirmed&page=1" class="filter-btn <?= $status_filter === 'confirmed' ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i> Confirmed
        </a>
        <a href="?branch=<?= $selected_branch_id ?>&status=dispensed&page=1" class="filter-btn <?= $status_filter === 'dispensed' ? 'active' : '' ?>">
            <i class="fas fa-pills"></i> Dispensed
        </a>
        <a href="?branch=<?= $selected_branch_id ?>&status=cancelled&page=1" class="filter-btn <?= $status_filter === 'cancelled' ? 'active' : '' ?>">
            <i class="fas fa-times-circle"></i> Cancelled
        </a>
        
        <?php if (!empty($status_filter)): ?>
            <a href="prescriptions.php?branch=<?= $selected_branch_id ?>" class="filter-btn" style="border-color: #EF4444; color: #EF4444;">
                <i class="fas fa-times"></i> Clear
            </a>
        <?php endif; ?>
    </div>

    <!-- PRESCRIPTIONS LIST -->
    <div class="card animate-fade-in-up" style="animation-delay:0.1s;">
        
        <!-- TABLE HEADER BAR: SEARCH LEFT (BLUE) + SCROLL RIGHT -->
        <div class="table-header-bar">
            <div class="table-header-left">
                <!-- BLUE SEARCH BOX - LEFT SIDE -->
                <div class="table-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="prescSearchInput" placeholder="🔍 Search patient, ID, prescription, or medicine..." autocomplete="off">
                </div>
                
                <div class="table-title-inline">
                    <i class="fas fa-users"></i>
                    <span>Patients List</span>
                    <span id="prescCountDisplay" style="font-size:0.8rem;color:var(--text-secondary);">
                        (<strong style="color:#0B5ED7;"><?= count($grouped_prescriptions) ?></strong> patients)
                    </span>
                </div>
                
                <span class="search-results-info" id="prescSearchInfo">
                    <i class="fas fa-filter"></i> <strong id="prescSearchCount">0</strong> match
                </span>
            </div>
            
            <div class="table-header-right">
                <button type="button" class="scroll-btn-header" id="prescScrollBtnLeft" onclick="scrollTable('left')" title="Scroll Left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="scroll-btn-header" id="prescScrollBtnRight" onclick="scrollTable('right')" title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <div class="table-container">
            <div class="table-scroll-wrapper" id="tableScrollWrapper">
                <table class="data-table table-blue w-full">
                    <thead>
                        <tr>
                            <th style="width: 45px; min-width: 45px;">#</th>
                            <th style="min-width: 150px;">Patient</th>
                            <th style="min-width: 100px;">Patient ID</th>
                            <th class="col-prescriptions">Prescriptions</th>
                            <th style="min-width: 200px;">Medications</th>
                            <th style="min-width: 120px;" class="text-right">Total Amount</th>
                            <th style="min-width: 100px;">Status</th>
                            <th style="min-width: 70px; text-align: center;">Count</th>
                            <th style="min-width: 110px;">Last Date</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="prescTableBody">
                        <?php if (count($grouped_prescriptions) > 0): ?>
                            <?php $i = $offset + 1; foreach ($grouped_prescriptions as $group): ?>
                                <tr class="presc-row" data-search="<?= htmlspecialchars($group['search_data']) ?>">
                                    <td class="font-bold presc-sno" style="color:#0B5ED7;"><?= $i++ ?></td>
                                    <td class="cell-patient">
                                        <div class="font-semibold patient-name"><?= htmlspecialchars($group['patient_name'] ?? 'Unknown') ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($group['patient_gender'] ?? 'N/A') ?></div>
                                        <div class="text-xs text-gray-400">📞 <span class="patient-phone"><?= htmlspecialchars($group['patient_phone'] ?? 'N/A') ?></span></div>
                                    </td>
                                    <td class="cell-patient-id">
                                        <div class="font-mono text-xs font-bold patient-id"><?= htmlspecialchars($group['patient_number'] ?? 'N/A') ?></div>
                                    </td>
                                    <td class="cell-prescriptions">
                                        <?php 
                                        $status_colors = ['pending' => 'pending', 'confirmed' => 'confirmed', 'dispensed' => 'dispensed', 'cancelled' => 'cancelled'];
                                        foreach (array_slice($group['prescriptions'], 0, 3) as $prescription): 
                                            $color = $status_colors[$prescription['status']] ?? 'secondary';
                                        ?>
                                            <span class="prescription-tag">
                                                <span class="status-dot <?= $color ?>"></span>
                                                <span class="prescription-number"><?= htmlspecialchars($prescription['number']) ?></span>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($group['prescriptions']) > 3): ?>
                                            <span class="prescription-tag" style="background:#E8F0FE;color:#0B5ED7;">
                                                +<?= count($group['prescriptions']) - 3 ?> more
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="cell-medications">
                                        <div class="medication-list">
                                            <?php 
                                            $all_meds = [];
                                            foreach ($group['prescriptions'] as $prescription) {
                                                if (!empty($prescription['medications']) && $prescription['medications'] !== 'No medications') {
                                                    $meds = explode(', ', $prescription['medications']);
                                                    foreach ($meds as $med) {
                                                        if (!empty($med) && $med !== 'No medications') {
                                                            $all_meds[] = $med;
                                                        }
                                                    }
                                                }
                                            }
                                            $unique_meds = array_unique($all_meds);
                                            $display_meds = array_slice($unique_meds, 0, 5);
                                            foreach ($display_meds as $med): 
                                                $qty_display = '';
                                                $med_name = $med;
                                                if (preg_match('/\((\d+)\s*([^)]*)\)/', $med, $matches)) {
                                                    $qty_display = ' <span class="qty">' . $matches[1] . '</span>';
                                                    $med_name = trim(str_replace('(' . $matches[1] . $matches[2] . ')', '', $med));
                                                }
                                            ?>
                                                <span class="medication-item" data-med-name="<?= htmlspecialchars($med_name) ?>">
                                                    <span class="med-name-text"><?= htmlspecialchars(substr($med_name, 0, 25)) . (strlen($med_name) > 25 ? '...' : '') ?></span>
                                                    <?= $qty_display ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if (count($unique_meds) > 5): ?>
                                                <span class="medication-item" style="background:#E8F0FE;color:#0B5ED7;">
                                                    +<?= count($unique_meds) - 5 ?> more
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-right amount-cell">
                                        TSh <?= number_format($group['total_amount'] ?? 0, 0) ?>
                                    </td>
                                    <td class="cell-status">
                                        <?php 
                                        $status = $group['overall_status'] ?? 'pending';
                                        $color = $group['status_color'] ?? 'secondary';
                                        $label = $group['status_label'] ?? 'Unknown';
                                        ?>
                                        <span class="status-badge <?= $color ?>">
                                            <?php if ($status === 'pending'): ?>⏳<?php elseif ($status === 'confirmed'): ?>✅<?php elseif ($status === 'dispensed'): ?>💊<?php else: ?>❌<?php endif; ?>
                                            <?= $label ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="font-bold" style="color:#0B5ED7;">
                                            <?= count($group['prescriptions']) ?>
                                        </span>
                                    </td>
                                    <td class="text-xs">
                                        <?= date('M d, Y', strtotime($group['last_prescription_date'] ?? 'now')) ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons-vertical">
                                            <?php 
                                            $first_prescription = !empty($group['prescriptions']) ? $group['prescriptions'][0] : null;
                                            $prescription_id = $first_prescription ? $first_prescription['id'] : 0;
                                            ?>
                                            <a href="prescription_details.php?id=<?= $prescription_id ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn-action btn-view" title="View Prescriptions">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            
                                            <a href="?delete_patient=<?= $group['patient_id'] ?>&branch=<?= $selected_branch_id ?>&page=<?= $page ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" 
                                               class="btn-action btn-delete-all" 
                                               title="Delete all <?= count($group['prescriptions']) ?> prescriptions for <?= htmlspecialchars($group['patient_name'] ?? 'Unknown') ?>"
                                               onclick="return confirmDeleteAll('<?= htmlspecialchars($group['patient_name'] ?? 'Unknown') ?>', '<?= count($group['prescriptions']) ?>', '<?= number_format($group['total_amount'] ?? 0, 0) ?>')">
                                                <i class="fas fa-trash-alt"></i> 
                                                Delete All
                                                <span class="count-badge"><?= count($group['prescriptions']) ?></span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <tr class="no-results-row" id="prescNoResults" style="display:none;">
                                <td colspan="10">
                                    <i class="fas fa-search-minus"></i>
                                    <p style="color:var(--text-primary);font-weight:500;">No patients match your search</p>
                                    <p style="font-size:0.75rem;margin-top:4px;color:var(--text-muted);">Try searching for patient name, ID, prescription number, or medicine name (e.g. "Albendazole")</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-8 text-gray-400">
                                    <i class="fas fa-prescription text-4xl block mb-3" style="color: #0B5ED7;"></i>
                                    <p class="text-lg font-medium" style="color: var(--text-primary);">
                                        <?= !empty($search) || !empty($status_filter) ? 'No prescriptions found matching your filters' : 'No prescriptions found' ?>
                                    </p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
            <div class="flex flex-wrap justify-between items-center gap-3 mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Showing <?= $offset + 1 ?> - <?= min($offset + $per_page, $total_patients) ?> of <?= $total_patients ?> patients
                </div>
                
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&branch=<?= $selected_branch_id ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="?page=<?= $p ?>&branch=<?= $selected_branch_id ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" 
                           class="page-link <?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&branch=<?= $selected_branch_id ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Prescriptions Management (Grouped by Patient)
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
// ================================================================
// TABLE SCROLL
// ================================================================
function scrollTable(direction) {
    var container = document.getElementById('tableScrollWrapper');
    if (!container) return;
    var scrollAmount = 400;
    if (direction === 'left') {
        container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    } else {
        container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    }
}

function updateScrollButtons() {
    var wrap = document.getElementById('tableScrollWrapper');
    var btnLeft = document.getElementById('prescScrollBtnLeft');
    var btnRight = document.getElementById('prescScrollBtnRight');
    if (!wrap || !btnLeft || !btnRight) return;
    
    var scrollLeft = wrap.scrollLeft;
    var maxScroll = wrap.scrollWidth - wrap.clientWidth;
    
    btnLeft.disabled = (scrollLeft <= 5);
    btnRight.disabled = (scrollLeft >= maxScroll - 5 || maxScroll <= 0);
}

// ================================================================
// AUTO SEARCH WITH HIGHLIGHT
// ================================================================
(function() {
    var searchInput = document.getElementById('prescSearchInput');
    var tableBody = document.getElementById('prescTableBody');
    var noResultsRow = document.getElementById('prescNoResults');
    var countDisplay = document.getElementById('prescCountDisplay');
    var searchInfo = document.getElementById('prescSearchInfo');
    var searchCount = document.getElementById('prescSearchCount');
    
    if (!searchInput || !tableBody) return;
    var totalRows = document.querySelectorAll('.presc-row').length;
    
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    function highlightText(text, query) {
        if (!query || !text) return text;
        var escaped = escapeRegExp(query);
        var regex = new RegExp('(' + escaped + ')', 'gi');
        return text.replace(regex, '<mark class="highlight">$1</mark>');
    }
    
    function applyHighlights(row, query) {
        if (!query) return;
        
        // Highlight patient name, ID, phone
        row.querySelectorAll('.patient-name, .patient-id, .patient-phone').forEach(function(el) {
            var original = el.getAttribute('data-original') || el.textContent;
            el.setAttribute('data-original', original);
            el.innerHTML = highlightText(original, query);
        });
        
        // Highlight prescription numbers
        row.querySelectorAll('.prescription-number').forEach(function(el) {
            var original = el.getAttribute('data-original') || el.textContent;
            el.setAttribute('data-original', original);
            el.innerHTML = highlightText(original, query);
        });
        
        // HIGHLIGHT MEDICINE NAMES
        row.querySelectorAll('.med-name-text').forEach(function(el) {
            var original = el.getAttribute('data-original') || el.textContent;
            el.setAttribute('data-original', original);
            if (original.toLowerCase().includes(query.toLowerCase())) {
                el.innerHTML = original.replace(
                    new RegExp('(' + escapeRegExp(query) + ')', 'gi'),
                    '<mark class="highlight-med">$1</mark>'
                );
            } else {
                el.innerHTML = original;
            }
        });
        
        // Highlight medication items
        row.querySelectorAll('.medication-item').forEach(function(item) {
            var medName = item.getAttribute('data-med-name') || '';
            if (medName.toLowerCase().includes(query.toLowerCase())) {
                item.classList.add('highlighted-med');
            } else {
                item.classList.remove('highlighted-med');
            }
        });
    }
    
    function removeAllHighlights(row) {
        row.querySelectorAll('.patient-name, .patient-id, .patient-phone, .prescription-number, .med-name-text').forEach(function(el) {
            var original = el.getAttribute('data-original');
            if (original !== null) {
                el.innerHTML = original;
            }
        });
        
        row.querySelectorAll('.medication-item').forEach(function(item) {
            item.classList.remove('highlighted-med');
        });
        
        row.classList.remove('highlighted');
    }
    
    function filterTable() {
        var query = searchInput.value.trim();
        var queryLower = query.toLowerCase();
        var rows = document.querySelectorAll('.presc-row');
        var visibleCount = 0;
        
        rows.forEach(function(row) {
            var searchData = row.getAttribute('data-search') || '';
            
            removeAllHighlights(row);
            
            if (query === '' || searchData.includes(queryLower)) {
                row.style.display = '';
                visibleCount++;
                
                var snoCell = row.querySelector('.presc-sno');
                if (snoCell) snoCell.textContent = visibleCount;
                
                if (query !== '') {
                    applyHighlights(row, query);
                    
                    var medMatch = false;
                    row.querySelectorAll('.medication-item').forEach(function(item) {
                        if (item.classList.contains('highlighted-med')) {
                            medMatch = true;
                        }
                    });
                    if (medMatch) {
                        row.classList.add('highlighted');
                    }
                }
            } else {
                row.style.display = 'none';
            }
        });
        
        if (countDisplay) {
            countDisplay.innerHTML = query === '' 
                ? '(<strong style="color:#0B5ED7;">' + totalRows + '</strong> patients)' 
                : '(<strong style="color:#0B5ED7;">' + visibleCount + '</strong> of ' + totalRows + ')';
        }
        
        if (searchInfo && searchCount) {
            if (query === '') {
                searchInfo.classList.remove('show');
            } else {
                searchInfo.classList.add('show');
                searchCount.textContent = visibleCount;
            }
        }
        
        if (noResultsRow) {
            noResultsRow.style.display = (visibleCount === 0 && query !== '') ? '' : 'none';
        }
        
        setTimeout(updateScrollButtons, 100);
    }
    
    searchInput.addEventListener('input', filterTable);
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
            filterTable();
            this.blur();
        }
    });
})();

// ================================================================
// CONFIRM DELETE ALL
// ================================================================
function confirmDeleteAll(patientName, count, totalAmount) {
    var msg = '⚠️ ARE YOU SURE YOU WANT TO DELETE ALL PRESCRIPTIONS?\n\n';
    msg += '👤 Patient: ' + patientName + '\n';
    msg += '📋 Number of Prescriptions: ' + count + '\n';
    msg += '💰 Total Amount: TSh ' + totalAmount + '\n\n';
    msg += 'This will delete:\n';
    msg += '• All ' + count + ' prescription(s)\n';
    msg += '• All medication items\n';
    msg += '• All associated bill items\n\n';
    msg += '⚠️ This action CANNOT be undone!\n';
    msg += '⚠️ All data will be permanently lost!';
    
    return confirm(msg);
}

// ================================================================
// DARK MODE
// ================================================================
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

// ================================================================
// SIDEBAR
// ================================================================
var sidebar = document.getElementById('sidebar');
var sidebarToggle = document.getElementById('sidebarToggle');

sidebarToggle?.addEventListener('click', function() {
    sidebar.classList.toggle('open');
});

document.addEventListener('click', function(e) {
    if (window.innerWidth <= 1024) {
        if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
            sidebar.classList.remove('open');
        }
    }
});

// ================================================================
// SWITCH BRANCH
// ================================================================
function switchBranch(branchId) {
    var url = new URL(window.location.href);
    url.searchParams.set('branch', branchId);
    url.searchParams.delete('page');
    url.searchParams.delete('deleted_patient');
    url.searchParams.delete('error');
    window.location.href = url.toString();
}

// ================================================================
// TOAST
// ================================================================
function showToast(title, message, type) {
    var toast = document.getElementById('toast');
    var toastTitle = document.getElementById('toastTitle');
    var toastMessage = document.getElementById('toastMessage');
    
    toast.className = 'toast-custom ' + type;
    toastTitle.textContent = title;
    toastMessage.textContent = message;
    toast.style.display = 'flex';
    
    toast.classList.add('show');
    clearTimeout(toast.timeout);
    toast.timeout = setTimeout(function() {
        toast.classList.remove('show');
        setTimeout(function() { toast.style.display = 'none'; }, 400);
    }, 4000);
}

// HANDLE DELETE SUCCESS
<?php if (isset($_GET['deleted_patient']) && $_GET['deleted_patient'] == 1): 
    $count = isset($_GET['count']) ? (int)$_GET['count'] : 0;
    $patient = isset($_GET['patient']) ? urldecode($_GET['patient']) : 'Unknown';
?>
    showToast('✅ Success', 'Deleted <?= $count ?> prescription(s) for <?= htmlspecialchars($patient) ?>!', 'success');
    if (window.history && window.history.replaceState) {
        var url = new URL(window.location.href);
        url.searchParams.delete('deleted_patient');
        url.searchParams.delete('count');
        url.searchParams.delete('patient');
        window.history.replaceState({}, document.title, url.toString());
    }
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] == 'delete_failed'): ?>
    showToast('⚠️ Error', 'Failed to delete prescriptions. Please try again.', 'error');
    if (window.history && window.history.replaceState) {
        var url = new URL(window.location.href);
        url.searchParams.delete('error');
        window.history.replaceState({}, document.title, url.toString());
    }
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] == 'no_prescriptions'): ?>
    showToast('⚠️ Error', 'No prescriptions found for this patient.', 'error');
    if (window.history && window.history.replaceState) {
        var url = new URL(window.location.href);
        url.searchParams.delete('error');
        window.history.replaceState({}, document.title, url.toString());
    }
<?php endif; ?>

// ================================================================
// INITIALIZE
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    var wrap = document.getElementById('tableScrollWrapper');
    if (wrap) {
        wrap.addEventListener('scroll', updateScrollButtons);
        setTimeout(updateScrollButtons, 200);
    }
    
    window.addEventListener('resize', function() {
        setTimeout(updateScrollButtons, 200);
    });
});

// ================================================================
// DATE TIME
// ================================================================
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

// ================================================================
// CONSOLE
// ================================================================
console.log('%c🏥 Braick Dispensary - Prescriptions Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
console.log('%c✅ Search bar iko LEFT SIDE na BLUE background', 'font-size:13px; color:#0B5ED7; font-weight:bold;');
console.log('%c✨ HIGHLIGHT: Ukisearch "Albendazole" ita-highlight kila mahali', 'font-size:13px; color:#D97706; font-weight:bold;');
console.log('%c✅ Inatafuta hadi DAWA (medication_name)', 'font-size:13px; color:#059669;');
console.log('%c✅ DELETE ALL button inafuta prescriptions zote za patient', 'font-size:13px; color:#DC2626;');
console.log('%c◄ ► Scroll Buttons kwenye RIGHT SIDE', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>