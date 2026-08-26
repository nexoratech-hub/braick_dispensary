<?php
// ================================================================
// FILE: frontend/pages/doctor/my_patients.php
// DOCTOR - MY PATIENTS LIST
// FIXED: Removed patient_bills table (use bills instead)
// FIXED: Added VISITS button to view all visits
// FIXED: Full CSS with dark mode support
// BRAICK DISPENSARY
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS DOCTOR OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET DOCTOR INFO FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
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
$error = isset($_GET['error']) ? trim($_GET['error']) : '';

// Show error message
if ($error === 'invalid_patient') {
    $message = '⚠️ Invalid patient selected. Please try again.';
    $message_type = 'error';
} elseif ($error === 'patient_not_found') {
    $message = '⚠️ Patient not found.';
    $message_type = 'error';
} elseif ($error === 'database_error') {
    $message = '⚠️ Database error occurred. Please try again.';
    $message_type = 'error';
}

if (isset($_GET['success']) && $_GET['success'] === 'referral') {
    $message = '✅ Patient referred successfully!';
    $message_type = 'success';
}

// ================================================================
// GET DOCTOR'S PATIENTS
// ================================================================
if ($is_admin) {
    $where_clause = " WHERE 1=1";
    $params = [];
} else {
    $where_clause = " WHERE p.assigned_doctor_id = ?";
    $params = [$doctor_id];
}

// Search filter
if (!empty($search)) {
    $where_clause .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ? OR p.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Status filter
if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $where_clause .= " AND EXISTS (SELECT 1 FROM visits WHERE patient_id = p.id)";
    } elseif ($status_filter === 'inactive') {
        $where_clause .= " AND NOT EXISTS (SELECT 1 FROM visits WHERE patient_id = p.id)";
    }
}

// ================================================================
// GET PATIENTS WITH PAGINATION
// ================================================================

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM patients p $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_patients / $per_page);

// Get patients for current page
$sql = "
    SELECT p.*, 
           b.name as branch_name,
           (SELECT COUNT(*) FROM visits WHERE patient_id = p.id) as total_visits,
           (SELECT COUNT(*) FROM visits WHERE patient_id = p.id AND status = 'completed') as completed_visits,
           (SELECT COUNT(*) FROM prescriptions WHERE patient_id = p.id) as total_prescriptions,
           (SELECT COUNT(*) FROM bills WHERE patient_id = p.id AND status != 'cancelled') as total_bills,
           (SELECT MAX(created_at) FROM visits WHERE patient_id = p.id) as last_visit_date,
           (SELECT COUNT(*) FROM lab_tests lt JOIN visits v ON lt.visit_id = v.id WHERE v.patient_id = p.id) as total_lab_tests
    FROM patients p
    LEFT JOIN branches b ON p.branch_id = b.id
    $where_clause
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $per_page;
$params[] = $offset;
$stmt = $db->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================

if ($is_admin) {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM patients");
    $stmt->execute();
    $total_assigned = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT p.id) as total 
        FROM patients p 
        INNER JOIN visits v ON p.id = v.patient_id 
    ");
    $stmt->execute();
    $active_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT p.id) as total 
        FROM patients p 
        INNER JOIN visits v ON p.id = v.patient_id 
        WHERE v.status IN ('pending', 'assigned', 'with_doctor')
    ");
    $stmt->execute();
    $pending_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM visits");
    $stmt->execute();
    $total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} else {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM patients WHERE assigned_doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_assigned = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT p.id) as total 
        FROM patients p 
        INNER JOIN visits v ON p.id = v.patient_id 
        WHERE p.assigned_doctor_id = ?
    ");
    $stmt->execute([$doctor_id]);
    $active_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT p.id) as total 
        FROM patients p 
        INNER JOIN visits v ON p.id = v.patient_id 
        WHERE p.assigned_doctor_id = ? AND v.status IN ('pending', 'assigned', 'with_doctor')
    ");
    $stmt->execute([$doctor_id]);
    $pending_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!-- ================================================================ -->
<!-- FULL CSS WITH DARK MODE SUPPORT -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       ROOT VARIABLES - LIGHT & DARK MODE
       ================================================================ */
    :root {
        --primary: #0B5ED7;
        --primary-dark: #0A4CA8;
        --primary-light: #6EA8FE;
        --primary-bg: #E8F0FE;
        --success: #059669;
        --success-dark: #047857;
        --success-light: #34D399;
        --success-bg: #D1FAE5;
        --danger: #DC2626;
        --danger-dark: #B91C1C;
        --danger-light: #F87171;
        --danger-bg: #FEE2E2;
        --warning: #D97706;
        --warning-bg: #FEF3C7;
        --purple: #7C3AED;
        --purple-bg: #EDE9FE;
        --teal: #0D9488;
        --teal-bg: #CCFBF1;
        --white: #FFFFFF;
        --gray-50: #F8FAFC;
        --gray-100: #F1F5F9;
        --gray-200: #E2E8F0;
        --gray-300: #CBD5E1;
        --gray-400: #94A3B8;
        --gray-500: #64748B;
        --gray-600: #475569;
        --gray-700: #334155;
        --gray-800: #1E293B;
        --gray-900: #0F172A;
        --bg-body: #F1F5F9;
        --bg-card: #FFFFFF;
        --bg-nav: #FFFFFF;
        --text-primary: #1E293B;
        --text-secondary: #64748B;
        --border-color: #E2E8F0;
        --shadow: 0 1px 3px rgba(0,0,0,0.08);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.07);
        --shadow-lg: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    [data-theme="dark"] {
        --bg-body: #0F172A;
        --bg-card: #1E293B;
        --bg-nav: #1E293B;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --border-color: #334155;
        --shadow: 0 1px 3px rgba(0,0,0,0.3);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --shadow-lg: 0 8px 25px rgba(0,0,0,0.4);
    }
    
    /* ================================================================
       BASE STYLES
       ================================================================ */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
        font-family: 'Inter', 'Segoe UI', sans-serif;
        background: var(--bg-body);
        color: var(--text-primary);
        transition: background 0.3s ease, color 0.3s ease;
    }
    
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: var(--bg-body); }
    ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
    
    /* ================================================================
       MAIN CONTENT
       ================================================================ */
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 24px 28px;
        min-height: calc(100vh - 68px);
        transition: all 0.3s ease;
        background: var(--bg-body);
    }
    
    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header {
        border-bottom: 3px solid var(--primary);
        padding-bottom: 12px;
        margin-bottom: 20px;
    }
    
    .page-title {
        color: var(--primary-dark);
        font-size: 1.8rem;
        font-weight: 700;
    }
    
    [data-theme="dark"] .page-title {
        color: var(--primary-light);
    }
    
    .page-subtitle {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    
    .branch-tag {
        background: var(--primary);
        color: white;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    /* ================================================================
       STAT CARDS
       ================================================================ */
    .stat-card-mini {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .stat-card-mini:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        border-color: #0B5ED7;
    }
    
    .stat-card-mini .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: #0B5ED7;
    }
    
    .stat-card-mini .stat-number.green { color: #059669; }
    .stat-card-mini .stat-number.orange { color: #F59E0B; }
    .stat-card-mini .stat-number.purple { color: #7B2FBE; }
    .stat-card-mini .stat-number.red { color: #EF4444; }
    .stat-card-mini .stat-number.teal { color: #0D9488; }
    
    .stat-card-mini .stat-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .stat-card-mini .stat-icon {
        font-size: 1.5rem;
        margin-bottom: 4px;
    }
    
    [data-theme="dark"] .stat-card-mini {
        background: #1E293B;
        border-color: #334155;
    }
    
    [data-theme="dark"] .stat-card-mini:hover {
        border-color: #0B5ED7;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-number {
        color: #6EA8FE;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-number.green {
        color: #34D399;
    }
    
    /* ================================================================
       TABLE - BLUE THEME WITH DARK MODE
       ================================================================ */
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
    
    .table-blue thead th:first-child {
        border-radius: 8px 0 0 0 !important;
    }
    
    .table-blue thead th:last-child {
        border-radius: 0 8px 0 0 !important;
    }
    
    .table-blue tbody td {
        padding: 8px 14px !important;
        border-bottom: 1px solid #E2E8F0 !important;
        color: #1E293B !important;
        vertical-align: middle !important;
        font-size: 0.82rem;
    }
    
    .table-blue tbody tr:hover td {
        background: #E8F0FE !important;
    }
    
    [data-theme="dark"] .table-blue tbody td {
        color: #F1F5F9 !important;
        border-bottom-color: #334155 !important;
    }
    
    [data-theme="dark"] .table-blue tbody tr:hover td {
        background: #1A3A5F !important;
    }
    
    /* ================================================================
       BADGES - DARK MODE SUPPORT
       ================================================================ */
    .badge {
        padding: 3px 12px !important;
        border-radius: 20px !important;
        font-size: 0.6rem !important;
        font-weight: 600 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        border: none !important;
    }
    
    .badge-success { background: #D1FAE5 !important; color: #059669 !important; }
    .badge-warning { background: #FEF3C7 !important; color: #D97706 !important; }
    .badge-danger { background: #FEE2E2 !important; color: #EF4444 !important; }
    .badge-info { background: #E8F0FE !important; color: #0B5ED7 !important; }
    .badge-secondary { background: #E2E8F0 !important; color: #64748B !important; }
    .badge-blue { background: #E8F0FE !important; color: #0B5ED7 !important; }
    .badge-green { background: #D1FAE5 !important; color: #059669 !important; }
    .badge-teal { background: #CCFBF1 !important; color: #0D9488 !important; }
    .badge-purple { background: #EDE9FE !important; color: #7C3AED !important; }
    
    [data-theme="dark"] .badge-success { background: #1A3A2A !important; color: #34D399 !important; }
    [data-theme="dark"] .badge-warning { background: #3A2A1A !important; color: #FBBF24 !important; }
    [data-theme="dark"] .badge-danger { background: #3A1A1A !important; color: #F87171 !important; }
    [data-theme="dark"] .badge-info { background: #1E3A5F !important; color: #6EA8FE !important; }
    [data-theme="dark"] .badge-secondary { background: #2D3748 !important; color: #94A3B8 !important; }
    [data-theme="dark"] .badge-blue { background: #1E3A5F !important; color: #6EA8FE !important; }
    [data-theme="dark"] .badge-green { background: #1A3A2A !important; color: #34D399 !important; }
    [data-theme="dark"] .badge-teal { background: #1A3A3A !important; color: #2DD4BF !important; }
    [data-theme="dark"] .badge-purple { background: #2D1A3A !important; color: #A78BFA !important; }
    
    /* ================================================================
       FILTER BUTTONS
       ================================================================ */
    .filter-btn {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        border: 2px solid var(--border-color);
        background: transparent;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }
    
    .filter-btn:hover {
        border-color: #0B5ED7;
        color: #0B5ED7;
        background: #E8F0FE;
    }
    
    .filter-btn.active {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }
    
    .filter-btn.active:hover {
        background: #0A4CA8;
        border-color: #0A4CA8;
    }
    
    [data-theme="dark"] .filter-btn:hover {
        background: #1E3A5F;
        border-color: #0B5ED7;
        color: #6EA8FE;
    }
    
    .filter-btn i { margin-right: 4px; }
    
    /* ================================================================
       FILTER SECTION
       ================================================================ */
    .filter-section {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 14px 18px;
        border: 1px solid var(--border-color);
        margin-bottom: 18px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }
    
    .filter-section .filter-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-secondary);
        margin-right: 4px;
    }
    
    /* ================================================================
       ACTION BUTTONS - VIEW + VISITS
       ================================================================ */
    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 16px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
        border: none;
        cursor: pointer;
        white-space: nowrap;
    }
    
    .btn-action i { font-size: 0.8rem; }
    
    .btn-view {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
    }
    
    .btn-view:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.4);
        color: white;
    }
    
    .btn-visits {
        background: linear-gradient(135deg, #0D9488, #0F766E);
        color: white;
        box-shadow: 0 2px 8px rgba(13, 148, 136, 0.25);
    }
    
    .btn-visits:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(13, 148, 136, 0.4);
        color: white;
    }
    
    [data-theme="dark"] .btn-view {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
    }
    
    [data-theme="dark"] .btn-view:hover {
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        color: white;
    }
    
    [data-theme="dark"] .btn-visits {
        background: linear-gradient(135deg, #0D9488, #0F766E);
        color: white;
    }
    
    [data-theme="dark"] .btn-visits:hover {
        box-shadow: 0 4px 16px rgba(13, 148, 136, 0.3);
        color: white;
    }
    
    .btn-action-group {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    
    /* ================================================================
       CARD
       ================================================================ */
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        transition: all 0.3s;
    }
    
    .card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.05);
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .card-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .title-blue { color: #0B5ED7; }
    
    /* ================================================================
       TABLE HEADER WITH SEARCH
       ================================================================ */
    .table-header-wrapper {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .table-header-wrapper .search-box {
        position: relative;
        flex: 1;
        min-width: 200px;
        max-width: 350px;
    }
    
    .table-header-wrapper .search-box input {
        width: 100%;
        padding: 8px 16px 8px 38px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        background: #FFFFFF !important;
        color: #1E293B !important;
        outline: none;
    }
    
    .table-header-wrapper .search-box input:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
    }
    
    [data-theme="dark"] .table-header-wrapper .search-box input {
        background: #1E293B !important;
        color: #F1F5F9 !important;
        border-color: #334155 !important;
    }
    
    [data-theme="dark"] .table-header-wrapper .search-box input:focus {
        border-color: #6EA8FE;
        box-shadow: 0 0 0 3px rgba(110, 168, 254, 0.15);
    }
    
    .table-header-wrapper .search-box .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    
    .table-header-wrapper .search-info {
        font-size: 0.8rem;
        color: var(--text-secondary);
        white-space: nowrap;
    }
    
    .table-header-wrapper .search-info strong {
        color: #0B5ED7;
    }
    
    [data-theme="dark"] .table-header-wrapper .search-info strong {
        color: #6EA8FE;
    }
    
    /* ================================================================
       PAGINATION
       ================================================================ */
    .pagination {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    
    .pagination .page-link {
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        text-decoration: none;
        font-size: 0.8rem;
        transition: all 0.3s;
        background: var(--bg-card);
    }
    
    .pagination .page-link:hover {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }
    
    .pagination .page-link.active {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }
    
    .pagination .page-link.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    [data-theme="dark"] .pagination .page-link {
        background: #1E293B;
        border-color: #334155;
    }
    
    [data-theme="dark"] .pagination .page-link:hover {
        background: #0B5ED7;
        border-color: #0B5ED7;
    }
    
    /* ================================================================
       TOAST
       ================================================================ */
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 12px 18px;
        border-radius: 12px;
        z-index: 999;
        max-width: 360px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    .toast-custom.show { transform: translateY(0); opacity: 1; }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #EF4444; }
    .toast-custom.info { background: #0B5ED7; }
    .toast-custom.warning { background: #D97706; }
    
    /* ================================================================
       FOOTER
       ================================================================ */
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
    .text-gray-300 { color: #D1D5DB; }
    .mx-2 { margin-left: 0.5rem; margin-right: 0.5rem; }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .main-content { padding: 16px; }
    }
    @media (max-width: 768px) {
        .main-content { padding: 12px; }
        .table-blue tbody td { font-size: 0.7rem; padding: 6px 10px !important; }
        .filter-section { flex-direction: column; align-items: stretch; }
        .filter-section .filter-label { margin-bottom: 4px; }
        .table-header-wrapper { flex-direction: column; align-items: stretch; }
        .table-header-wrapper .search-box { max-width: 100%; }
        .btn-action { padding: 4px 10px; font-size: 0.65rem; }
        .btn-action i { font-size: 0.65rem; }
        .stat-card-mini .stat-number { font-size: 1.4rem; }
        .page-title { font-size: 1.2rem; }
        .btn-action-group { gap: 4px; }
    }
    @media (max-width: 480px) {
        .main-content { padding: 8px; }
        .stat-card-mini .stat-number { font-size: 1.2rem; }
        .page-title { font-size: 1rem; }
        .btn-action { padding: 3px 8px; font-size: 0.6rem; }
        .btn-action i { font-size: 0.6rem; }
        .stat-card-mini { padding: 10px 12px; }
        .card { padding: 12px 14px; }
    }
    
    /* ================================================================
       DARK MODE OVERRIDES
       ================================================================ */
    [data-theme="dark"] .stat-card-mini {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .stat-card-mini:hover {
        border-color: #0B5ED7;
    }
    [data-theme="dark"] .stat-card-mini .stat-number {
        color: #6EA8FE;
    }
    [data-theme="dark"] .stat-card-mini .stat-number.green {
        color: #34D399;
    }
    [data-theme="dark"] .card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .card:hover {
        border-color: #0B5ED7;
    }
    [data-theme="dark"] .card-title {
        color: #F1F5F9;
    }
    [data-theme="dark"] .filter-section {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .filter-btn {
        color: #94A3B8;
        border-color: #334155;
    }
    [data-theme="dark"] .filter-btn:hover {
        background: #1E3A5F;
        border-color: #0B5ED7;
        color: #6EA8FE;
    }
    [data-theme="dark"] .filter-btn.active {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }
    [data-theme="dark"] .table-header-wrapper {
        border-color: #334155;
    }
    [data-theme="dark"] .pagination .page-link {
        background: #1E293B;
        border-color: #334155;
        color: #F1F5F9;
    }
    [data-theme="dark"] .pagination .page-link:hover {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }
    [data-theme="dark"] .page-subtitle {
        color: #94A3B8;
    }
    [data-theme="dark"] .footer {
        border-color: #334155;
        color: #94A3B8;
    }
    [data-theme="dark"] .toast-custom {
        box-shadow: 0 8px 30px rgba(0,0,0,0.4);
    }
    
    /* Alert Styles */
    .alert {
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
        border: 1px solid transparent;
        animation: slideDown 0.3s ease;
    }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .alert-success { background: #D1FAE5; color: #059669; border-color: #059669; }
    .alert-error { background: #FEE2E2; color: #DC2626; border-color: #DC2626; }
    .alert-warning { background: #FEF3C7; color: #D97706; border-color: #D97706; }
    .alert-info { background: #E8F0FE; color: #0B5ED7; border-color: #0B5ED7; }
    
    [data-theme="dark"] .alert-success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
    [data-theme="dark"] .alert-error { background: #3A1A1A; color: #F87171; border-color: #F87171; }
    [data-theme="dark"] .alert-warning { background: #3D2E0A; color: #FBBF24; border-color: #FBBF24; }
    [data-theme="dark"] .alert-info { background: #1E3A5F; color: #6EA8FE; border-color: #6EA8FE; }
    
    /* Grid Utilities */
    .grid { display: grid; }
    .grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
    .sm\:grid-cols-4 { grid-template-columns: repeat(4, 1fr); }
    .gap-3 { gap: 12px; }
    .gap-2 { gap: 8px; }
    .flex { display: flex; }
    .flex-wrap { flex-wrap: wrap; }
    .justify-between { justify-content: space-between; }
    .items-center { align-items: center; }
    .mb-5 { margin-bottom: 20px; }
    .mt-4 { margin-top: 16px; }
    .pt-3 { padding-top: 12px; }
    .w-full { width: 100%; }
    .overflow-x-auto { overflow-x: auto; }
    .text-center { text-align: center; }
    .text-sm { font-size: 0.875rem; }
    .text-xs { font-size: 0.75rem; }
    .text-lg { font-size: 1.125rem; }
    .text-4xl { font-size: 2.25rem; }
    .font-medium { font-weight: 500; }
    .font-bold { font-weight: 700; }
    .font-mono { font-family: monospace; }
    .py-8 { padding-top: 2rem; padding-bottom: 2rem; }
    .mb-3 { margin-bottom: 12px; }
    .block { display: block; }
    .text-gray-400 { color: #94A3B8; }
    .text-gray-500 { color: #64748B; }
    .text-blue-600 { color: #0B5ED7; }
    .border-t { border-top: 1px solid; }
    .border-gray-200 { border-color: #E2E8F0; }
    .ml-2 { margin-left: 8px; }
    .mr-2 { margin-right: 8px; }
    
    [data-theme="dark"] .border-gray-200 { border-color: #334155; }
    [data-theme="dark"] .text-blue-600 { color: #6EA8FE; }
    [data-theme="dark"] .text-gray-400 { color: #94A3B8; }
    [data-theme="dark"] .text-gray-500 { color: #94A3B8; }
    
    .dark\:border-gray-700 { border-color: #334155; }
    .dark\:text-blue-400 { color: #6EA8FE; }
    .dark\:text-white { color: #F1F5F9; }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-users mr-2" style="color: #0B5ED7;"></i> My Patients
                <?php if ($is_admin): ?>
                    <span class="page-badge" style="background:#DC2626;color:white;font-size:0.65rem;">👑 Admin Mode</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                View and manage your assigned patients
                <span class="branch-tag ml-2" style="background: #0B5ED7;">
                    <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($doctor_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user-injured mr-1"></i> <?= $total_assigned ?> Total Patients
                </span>
                <?php if ($is_admin): ?>
                    <span class="ml-2 inline-flex bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs border border-red-200">
                        <i class="fas fa-user-shield mr-1"></i> Admin View
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        
        <div class="stat-card-mini">
            <div class="stat-icon">👤</div>
            <p class="stat-number"><?= $total_assigned ?></p>
            <p class="stat-label">Total Patients</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">🟢</div>
            <p class="stat-number green"><?= $active_patients ?></p>
            <p class="stat-label">Active Patients</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">⏳</div>
            <p class="stat-number orange"><?= $pending_visits ?></p>
            <p class="stat-label">Pending Visits</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">📋</div>
            <p class="stat-number purple"><?= $total_visits ?></p>
            <p class="stat-label">Total Visits</p>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section">
        <span class="filter-label"><i class="fas fa-filter"></i> Status:</span>
        
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => '', 'page' => 1])) ?>" 
           class="filter-btn <?= empty($status_filter) ? 'active' : '' ?>">
            <i class="fas fa-globe"></i> All
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'active', 'page' => 1])) ?>" 
           class="filter-btn <?= $status_filter === 'active' ? 'active' : '' ?>">
            <i class="fas fa-check-circle" style="color: #059669;"></i> Active
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'inactive', 'page' => 1])) ?>" 
           class="filter-btn <?= $status_filter === 'inactive' ? 'active' : '' ?>">
            <i class="fas fa-clock" style="color: #F59E0B;"></i> Inactive
        </a>
        
        <?php if (!empty($search) || !empty($status_filter)): ?>
            <a href="my_patients.php" class="filter-btn" style="border-color: #EF4444; color: #EF4444;">
                <i class="fas fa-times"></i> Clear Filters
            </a>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENTS LIST WITH SEARCH IN TABLE HEADER -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i>
                Patients List
                <span class="text-sm font-normal text-gray-400">(<?= $total_patients ?> patients)</span>
            </h3>
        </div>
        
        <!-- Table Header with Search -->
        <div class="table-header-wrapper">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="tableSearch" placeholder="Filter patients in table..." onkeyup="filterTable()">
            </div>
            <div class="search-info">
                Showing <strong id="visibleCount"><?= count($patients) ?></strong> of <strong id="totalCount"><?= $total_patients ?></strong> patients
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full" id="patientsTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th style="min-width: 120px;">Patient ID</th>
                        <th style="min-width: 150px;">Patient Name</th>
                        <th style="min-width: 100px;">Phone</th>
                        <th style="min-width: 70px;">Visits</th>
                        <th style="min-width: 70px;">Presc.</th>
                        <th style="min-width: 110px;">Last Visit</th>
                        <th style="min-width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if (count($patients) > 0): ?>
                        <?php $i = $offset + 1; foreach ($patients as $patient): ?>
                            <tr>
                                <td class="font-bold text-blue-600 dark:text-blue-400"><?= $i++ ?></td>
                                <td class="font-mono text-xs font-bold text-blue-600 dark:text-blue-400">
                                    <?= htmlspecialchars($patient['patient_id']) ?>
                                </td>
                                <td class="font-semibold"><?= htmlspecialchars($patient['full_name']) ?></td>
                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                <td class="text-center">
                                    <span class="badge badge-info"><?= $patient['total_visits'] ?? 0 ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-green"><?= $patient['total_prescriptions'] ?? 0 ?></span>
                                </td>
                                <td class="text-xs">
                                    <?= $patient['last_visit_date'] ? date('M d, Y', strtotime($patient['last_visit_date'])) : 'Never' ?>
                                </td>
                                <td>
                                    <div class="btn-action-group">
                                        <!-- VIEW PATIENT DETAILS -->
                                        <a href="patient_details.php?id=<?= $patient['id'] ?>" 
                                           class="btn-action btn-view" title="View Patient Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <!-- VIEW ALL VISITS -->
                                        <a href="patient_visits.php?id=<?= $patient['id'] ?>" 
                                           class="btn-action btn-visits" title="View All Visits (<?= $patient['total_visits'] ?? 0 ?>)">
                                            <i class="fas fa-clinic-medical"></i> Visits <?= $patient['total_visits'] ?? 0 ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-400">
                                <i class="fas fa-user-injured text-4xl block mb-3" style="color: #0B5ED7;"></i>
                                <p class="text-lg font-medium" style="color: #1E293B; dark:text-white;">
                                    <?= !empty($search) || !empty($status_filter) ? 'No patients found matching your filters' : 'No patients assigned to you' ?>
                                </p>
                                <p class="text-sm">
                                    <?= !empty($search) || !empty($status_filter) ? 'Try changing your search or filter criteria' : 'Patients will appear here once assigned to you' ?>
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ================================================================ -->
        <!-- PAGINATION -->
        <!-- ================================================================ -->
        <?php if ($total_pages > 1): ?>
            <div class="flex flex-wrap justify-between items-center gap-3 mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Showing <?= $offset + 1 ?> - <?= min($offset + $per_page, $total_patients) ?> of <?= $total_patients ?> patients
                </div>
                
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                    <?php endif; ?>
                    
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="?page=<?= $p ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" 
                           class="page-link <?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            My Patients
            <?php if ($is_admin): ?>
                <span class="text-gray-300 mx-2">|</span>
                <span style="color:#DC2626;">👑 Admin Mode</span>
            <?php endif; ?>
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
    // ================================================================
    // DARK MODE - SYNC WITH HEADER (localStorage)
    // ================================================================
    document.addEventListener('darkModeChanged', function(e) {
        var isDark = e.detail && e.detail.isDark;
        var html = document.documentElement;
        
        if (isDark) {
            html.setAttribute('data-theme', 'dark');
        } else {
            html.removeAttribute('data-theme');
        }
    });
    
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    // ================================================================
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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

    // ================================================================
    // TABLE SEARCH FILTER (Real-time)
    // ================================================================
    function filterTable() {
        var input = document.getElementById('tableSearch');
        var filter = input.value.toLowerCase();
        var table = document.getElementById('patientsTable');
        var rows = table.getElementsByTagName('tr');
        var visibleCount = 0;
        
        for (var i = 1; i < rows.length; i++) {
            var row = rows[i];
            var text = row.textContent.toLowerCase();
            if (text.includes(filter)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        }
        
        document.getElementById('visibleCount').textContent = visibleCount;
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
        }, 3500);
    }

    // ================================================================
    // DATE & TIME
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
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $doctor_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    console.log('%c🏥 Braick Dispensary - My Patients', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User ID: <?= $doctor_id ?> | Role: <?= $_SESSION['role'] ?>', 'font-size:12px; color:#64748B;');
    <?php if ($is_admin): ?>
    console.log('%c👑 Admin Mode - Viewing All Patients', 'font-size:12px; color:#DC2626;');
    <?php endif; ?>
    console.log('%c👨‍⚕️ Doctor: Dr. <?= htmlspecialchars($doctor_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Total Patients: <?= $total_assigned ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🟢 Active: <?= $active_patients ?> | ⏳ Pending Visits: <?= $pending_visits ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔍 Real-time table search filter enabled', 'font-size:13px; color:#7B2FBE;');
    console.log('%c✅ Two buttons: View (details) + Visits (all visits)', 'font-size:13px; color:#059669;');
    console.log('%c📋 Visits button shows count: <?= $patient['total_visits'] ?? 0 ?>', 'font-size:13px; color:#0D9488;');
    console.log('%c🌙 Dark mode synced with header via localStorage', 'font-size:13px; color:#8B5CF6;');
    console.log('%c🔧 FIXED: Removed patient_bills table (using bills instead)', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>