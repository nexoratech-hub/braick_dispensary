<?php
// ================================================================
// FILE: frontend/pages/admin/prescriptions.php
// PRESCRIPTIONS LIST - VIEW ALL PRESCRIPTIONS
// BRAICK DISPENSARY
// WITH SESSION MANAGEMENT & LOGIN PROTECTION
// BLUE THEME
// FIXED: Doctor name now shows correctly from prescriptions table
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

// ================================================================
// ROLE CHECK - ONLY ADMIN CAN ACCESS
// ================================================================
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

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// Include database and helpers
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

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
$selected_branch_id = $_GET['branch'] ?? 'all';

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// ✅ FIXED: BUILD QUERY FROM prescriptions TABLE WITH CORRECT JOINS
// ================================================================
$where_clause = " WHERE 1=1";
$params = [];

// Search filter
if (!empty($search)) {
    $where_clause .= " AND (p.prescription_number LIKE ? OR pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.diagnosis LIKE ? OR p.medication LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Status filter
if (!empty($status_filter)) {
    $where_clause .= " AND p.status = ?";
    $params[] = $status_filter;
}

// Branch filter
if ($selected_branch_id !== 'all') {
    $where_clause .= " AND p.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

// ================================================================
// ✅ FIXED: GET PRESCRIPTIONS FROM prescriptions TABLE
// ================================================================

// Get total count
$count_sql = "
    SELECT COUNT(*) as total 
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    $where_clause
";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_prescriptions / $per_page);

// ✅ FIXED: Get prescriptions with doctor name from users table
$sql = "
    SELECT 
        p.*,
        pat.full_name as patient_name, 
        pat.patient_id as patient_number,
        u.full_name as doctor_name,
        b.name as branch_name,
        CASE 
            WHEN p.status = 'pending' THEN 'warning'
            WHEN p.status = 'confirmed' THEN 'info'
            WHEN p.status = 'dispensed' THEN 'success'
            WHEN p.status = 'cancelled' THEN 'danger'
            ELSE 'secondary'
        END as status_color
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.doctor_id = u.id
    LEFT JOIN branches b ON p.branch_id = b.id
    $where_clause
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($sql);
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PRESCRIPTION SALES DATA FOR SALE NUMBER
// ================================================================
$prescription_sales_map = [];
try {
    $stmt = $db->query("
        SELECT ps.prescription_id, ps.sale_number, ps.total_amount, ps.status as sale_status
        FROM prescription_sales ps
        WHERE ps.status IN ('dispensed', 'pending')
    ");
    $sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sales_data as $sale) {
        $prescription_sales_map[$sale['prescription_id']] = $sale;
    }
} catch (Exception $e) {
    $prescription_sales_map = [];
}

// ================================================================
// GET STATISTICS FROM prescriptions TABLE
// ================================================================

// Total prescriptions
$stmt = $db->query("SELECT COUNT(*) as total FROM prescriptions");
$total_all = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Pending prescriptions
$stmt = $db->query("SELECT COUNT(*) as total FROM prescriptions WHERE status = 'pending'");
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Confirmed prescriptions
$stmt = $db->query("SELECT COUNT(*) as total FROM prescriptions WHERE status = 'confirmed'");
$confirmed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Dispensed prescriptions
$stmt = $db->query("SELECT COUNT(*) as total FROM prescriptions WHERE status = 'dispensed'");
$dispensed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Cancelled prescriptions
$stmt = $db->query("SELECT COUNT(*) as total FROM prescriptions WHERE status = 'cancelled'");
$cancelled_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total amount from prescription_sales
$stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total_amount FROM prescription_sales WHERE status = 'paid'");
$total_amount_all = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

// Pending amount
$stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total_amount FROM prescription_sales WHERE status = 'pending'");
$pending_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

// Dispensed amount
$stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total_amount FROM prescription_sales WHERE status = 'dispensed'");
$dispensed_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

// Confirmed amount (from prescriptions table - no amount)
$confirmed_amount = 0;

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<style>
    /* ================================================================
       ADDITIONAL STYLES - BLUE THEME
       ================================================================ */
    
    /* Stat Cards */
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
    .stat-card-mini .stat-number.blue { color: #0B5ED7; }
    .stat-card-mini .stat-number.red { color: #EF4444; }
    
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
    
    .stat-card-mini .stat-amount {
        font-size: 0.8rem;
        font-weight: 600;
        color: #0B5ED7;
        margin-top: 2px;
    }
    
    .stat-card-mini .stat-amount.green { color: #059669; }
    .stat-card-mini .stat-amount.orange { color: #F59E0B; }
    .stat-card-mini .stat-amount.blue { color: #0B5ED7; }
    .stat-card-mini .stat-amount.red { color: #EF4444; }
    
    [data-theme="dark"] .stat-card-mini {
        background: #1E293B;
        border-color: #334155;
    }
    
    [data-theme="dark"] .stat-card-mini:hover {
        border-color: #3B82F6;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-number {
        color: #60A5FA;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-amount {
        color: #60A5FA;
    }
    
    /* Table Container with Arrows */
    .table-container {
        position: relative;
        overflow: hidden;
        border-radius: 12px;
    }
    
    .table-scroll-wrapper {
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
        scroll-behavior: smooth;
        position: relative;
    }
    
    .table-scroll-wrapper::-webkit-scrollbar {
        height: 6px;
    }
    
    .table-scroll-wrapper::-webkit-scrollbar-track {
        background: var(--bg-body);
        border-radius: 10px;
    }
    
    .table-scroll-wrapper::-webkit-scrollbar-thumb {
        background: #0B5ED7;
        border-radius: 10px;
    }
    
    /* Scroll Buttons (Arrows) - BLUE */
    .scroll-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: none;
        background: #0B5ED7;
        color: white;
        font-size: 0.9rem;
        cursor: pointer;
        z-index: 10;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
    }
    
    .scroll-btn:hover {
        background: #0A4CA8;
        transform: translateY(-50%) scale(1.1);
        box-shadow: 0 6px 20px rgba(11, 94, 215, 0.4);
    }
    
    .scroll-btn:active {
        transform: translateY(-50%) scale(0.95);
    }
    
    .scroll-btn.show {
        opacity: 1;
        visibility: visible;
    }
    
    .scroll-btn-left {
        left: 8px;
    }
    
    .scroll-btn-right {
        right: 8px;
    }
    
    [data-theme="dark"] .scroll-btn {
        background: #2563EB;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }
    
    [data-theme="dark"] .scroll-btn:hover {
        background: #1D4ED8;
    }
    
    /* Table Header - BLUE Theme */
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
        white-space: nowrap;
    }
    
    .table-blue tbody tr:hover td {
        background: #E8F0FE !important;
    }
    
    [data-theme="dark"] .table-blue tbody td {
        color: #F1F5F9 !important;
        border-bottom-color: #334155 !important;
    }
    
    [data-theme="dark"] .table-blue tbody tr:hover td {
        background: #1A2A4A !important;
    }
    
    /* Items Column - Black Color */
    .items-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 700;
        color: #FFFFFF !important;
        background: #1A1A1A !important;
        border: 1px solid #333333;
    }
    
    [data-theme="dark"] .items-count {
        background: #000000 !important;
        border-color: #444444;
        color: #FFFFFF !important;
    }
    
    /* Table Header with Search and Scroll Controls */
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
        border-color: #3B82F6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
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
        color: #3B82F6;
    }
    
    /* Scroll Controls in Header - BLUE */
    .scroll-controls {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-left: auto;
    }
    
    .scroll-btn-header {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .scroll-btn-header:hover {
        background: linear-gradient(135deg, #0A4CA8, #083D8A);
        transform: scale(1.05);
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.35);
    }
    
    .scroll-btn-header:active {
        transform: scale(0.95);
    }
    
    .scroll-btn-header:disabled {
        opacity: 0.4;
        cursor: not-allowed;
        transform: none !important;
    }
    
    .scroll-btn-header:disabled:hover {
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
        transform: none !important;
    }
    
    [data-theme="dark"] .scroll-btn-header {
        background: linear-gradient(135deg, #2563EB, #1D4ED8);
        box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
    }
    
    [data-theme="dark"] .scroll-btn-header:hover {
        background: linear-gradient(135deg, #1D4ED8, #1A3E8C);
        box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
    }
    
    .scroll-indicator {
        font-size: 0.65rem;
        color: var(--text-secondary);
        font-weight: 500;
        padding: 0 4px;
    }
    
    /* Status Badges */
    .status-badge {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
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
    
    /* Filter Buttons - BLUE */
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
        background: #1A2A4A;
        border-color: #3B82F6;
        color: #3B82F6;
    }
    
    .filter-btn i { margin-right: 4px; }
    
    /* Filter Section */
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
    
    /* Action Buttons - BLUE */
    .action-buttons {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    
    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.6rem;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
        border: none;
        cursor: pointer;
    }
    
    .btn-action i { font-size: 0.65rem; }
    
    .btn-view {
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    .btn-view:hover {
        background: #0B5ED7;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.3);
    }
    
    .btn-edit {
        background: #FEF3C7;
        color: #D97706;
    }
    
    .btn-edit:hover {
        background: #D97706;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(217, 119, 6, 0.3);
    }
    
    .btn-dispense {
        background: #D1FAE5;
        color: #059669;
    }
    
    .btn-dispense:hover {
        background: #059669;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
    }
    
    .btn-delete {
        background: #FEE2E2;
        color: #EF4444;
    }
    
    .btn-delete:hover {
        background: #EF4444;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }
    
    [data-theme="dark"] .btn-view {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    [data-theme="dark"] .btn-view:hover {
        background: #0B5ED7;
        color: white;
    }
    
    [data-theme="dark"] .btn-edit {
        background: #3A2A1A;
        color: #FBBF24;
    }
    [data-theme="dark"] .btn-edit:hover {
        background: #D97706;
        color: white;
    }
    
    [data-theme="dark"] .btn-dispense {
        background: #1A3A2A;
        color: #34D399;
    }
    [data-theme="dark"] .btn-dispense:hover {
        background: #059669;
        color: white;
    }
    
    [data-theme="dark"] .btn-delete {
        background: #3A1A1A;
        color: #F87171;
    }
    [data-theme="dark"] .btn-delete:hover {
        background: #EF4444;
        color: white;
    }
    
    /* Card */
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
    
    /* Pagination - BLUE */
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
    
    /* Badge for items */
    .badge {
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .badge-info {
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    [data-theme="dark"] .badge-info {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    /* Page Header - BLUE */
    .page-header {
        background: #0B5ED7 !important;
        border-radius: 16px !important;
        padding: 20px 28px !important;
        margin-bottom: 20px !important;
        display: flex !important;
        flex-wrap: wrap !important;
        justify-content: space-between !important;
        align-items: center !important;
        gap: 12px !important;
        box-shadow: 0 4px 24px rgba(11, 94, 215, 0.25) !important;
    }
    
    .page-header .page-title {
        color: white !important;
        font-size: 1.4rem !important;
        font-weight: 700 !important;
    }
    
    .page-header .page-title i {
        color: white !important;
    }
    
    .page-header .page-subtitle {
        color: rgba(255,255,255,0.85) !important;
        font-size: 0.85rem !important;
    }
    
    .page-header .branch-tag {
        background: rgba(255,255,255,0.2) !important;
        color: white !important;
        padding: 3px 14px !important;
        border-radius: 20px !important;
        font-size: 0.7rem !important;
        font-weight: 600 !important;
    }
    
    /* Toast */
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
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
    }
    
    .toast-custom.show {
        transform: translateY(0);
        opacity: 1;
    }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #EF4444; }
    .toast-custom.info { background: #0B5ED7; }
    
    /* Responsive */
    @media (max-width: 640px) {
        .stat-card-mini .stat-number {
            font-size: 1.4rem;
        }
        .filter-section {
            flex-direction: column;
            align-items: stretch;
        }
        .filter-section .filter-label {
            margin-bottom: 4px;
        }
        .table-blue tbody td {
            font-size: 0.7rem;
            padding: 6px 10px !important;
        }
        .btn {
            font-size: 0.7rem;
            padding: 4px 10px;
        }
        .table-header-wrapper {
            flex-direction: column;
            align-items: stretch;
        }
        .table-header-wrapper .search-box {
            max-width: 100%;
        }
        .scroll-controls {
            margin-left: 0;
            justify-content: center;
        }
        .page-header {
            padding: 14px 18px !important;
        }
        .page-header .page-title {
            font-size: 1.1rem !important;
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
            <form method="GET" action="" class="flex-1 flex">
                <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Search prescriptions..." 
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
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription mr-2"></i> Prescriptions
            </h1>
            <p class="page-subtitle">
                Manage all prescriptions in the system
                <span class="branch-tag ml-2">
                    <i class="fas fa-prescription"></i> <?= $total_all ?> Total
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

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        
        <div class="stat-card-mini">
            <div class="stat-icon">📋</div>
            <p class="stat-number"><?= $total_all ?></p>
            <p class="stat-label">Total</p>
            <p class="stat-amount">TSh <?= number_format($total_amount_all) ?></p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">⏳</div>
            <p class="stat-number orange"><?= $pending_count ?></p>
            <p class="stat-label">Pending</p>
            <p class="stat-amount orange">TSh <?= number_format($pending_amount) ?></p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">✅</div>
            <p class="stat-number blue"><?= $confirmed_count ?></p>
            <p class="stat-label">Confirmed</p>
            <p class="stat-amount blue">TSh <?= number_format($confirmed_amount) ?></p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">💊</div>
            <p class="stat-number green"><?= $dispensed_count ?></p>
            <p class="stat-label">Dispensed</p>
            <p class="stat-amount green">TSh <?= number_format($dispensed_amount) ?></p>
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
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'pending', 'page' => 1])) ?>" 
           class="filter-btn <?= $status_filter === 'pending' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i> Pending
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'confirmed', 'page' => 1])) ?>" 
           class="filter-btn <?= $status_filter === 'confirmed' ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i> Confirmed
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'dispensed', 'page' => 1])) ?>" 
           class="filter-btn <?= $status_filter === 'dispensed' ? 'active' : '' ?>">
            <i class="fas fa-pills"></i> Dispensed
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'cancelled', 'page' => 1])) ?>" 
           class="filter-btn <?= $status_filter === 'cancelled' ? 'active' : '' ?>">
            <i class="fas fa-times-circle"></i> Cancelled
        </a>
        
        <?php if (!empty($search) || !empty($status_filter)): ?>
            <a href="prescriptions.php?branch=<?= $selected_branch_id ?>" class="filter-btn" style="border-color: #EF4444; color: #EF4444;">
                <i class="fas fa-times"></i> Clear Filters
            </a>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS LIST WITH SEARCH AND SCROLL CONTROLS -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i>
                Prescriptions List
                <span class="text-sm font-normal text-gray-400">(<?= $total_prescriptions ?> prescriptions)</span>
            </h3>
        </div>
        
        <!-- Table Header with Search and Scroll Controls -->
        <div class="table-header-wrapper">
            <!-- Search Box -->
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="tableSearch" placeholder="Filter prescriptions in table..." onkeyup="filterTable()">
            </div>
            
            <!-- Search Info -->
            <div class="search-info">
                Showing <strong id="visibleCount"><?= count($prescriptions) ?></strong> of <strong id="totalCount"><?= $total_prescriptions ?></strong>
            </div>
            
            <!-- Scroll Controls (Arrows) -->
            <div class="scroll-controls">
                <button class="scroll-btn-header" id="scrollLeftBtn" onclick="scrollTable('left')" title="Scroll Left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span class="scroll-indicator">
                    <i class="fas fa-arrows-alt-h"></i>
                </span>
                <button class="scroll-btn-header" id="scrollRightBtn" onclick="scrollTable('right')" title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <!-- Table Container with Scroll -->
        <div class="table-container">
            <div class="table-scroll-wrapper" id="tableScrollWrapper">
                <table class="data-table table-blue w-full" id="prescriptionsTable">
                    <thead>
                        <tr>
                            <th style="width: 50px; min-width: 50px;">#</th>
                            <th style="min-width: 140px;">Prescription #</th>
                            <th style="min-width: 140px;">Patient</th>
                            <th style="min-width: 120px;">Patient ID</th>
                            <th style="min-width: 120px;">Doctor</th>
                            <th style="min-width: 150px;">Medication</th>
                            <th style="min-width: 100px;">Status</th>
                            <th style="min-width: 100px;">Date</th>
                            <th style="min-width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php if (count($prescriptions) > 0): ?>
                            <?php $i = $offset + 1; foreach ($prescriptions as $prescription): 
                                $sale_info = isset($prescription_sales_map[$prescription['id']]) ? $prescription_sales_map[$prescription['id']] : null;
                                $sale_number = $sale_info ? $sale_info['sale_number'] : null;
                            ?>
                                <tr>
                                    <td class="font-bold text-blue-600 dark:text-blue-400"><?= $i++ ?></td>
                                    <td class="font-mono text-xs font-bold"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></td>
                                    <td class="font-semibold"><?= htmlspecialchars($prescription['patient_name'] ?? 'Unknown') ?></td>
                                    <td class="font-mono text-xs"><?= htmlspecialchars($prescription['patient_number'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if (!empty($prescription['doctor_name'])): ?>
                                            <span class="badge badge-info">
                                                <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($prescription['doctor_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($prescription['medication'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="status-badge <?= $prescription['status_color'] ?? 'secondary' ?>">
                                            <?= ucfirst($prescription['status'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td class="text-xs"><?= date('M d, Y', strtotime($prescription['created_at'])) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <!-- View -->
                                            <a href="view_prescription.php?id=<?= $prescription['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn-action btn-view" title="View Prescription">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            
                                            <!-- Dispense (for pending) -->
                                            <?php if ($prescription['status'] === 'pending'): ?>
                                                <a href="dispense_prescription.php?id=<?= $prescription['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                                   class="btn-action btn-dispense" title="Dispense Prescription">
                                                    <i class="fas fa-check-circle"></i> Dispense
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Edit -->
                                            <a href="edit_prescription.php?id=<?= $prescription['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn-action btn-edit" title="Edit Prescription">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-8 text-gray-400">
                                    <i class="fas fa-prescription text-4xl block mb-3" style="color: #0B5ED7;"></i>
                                    <p class="text-lg font-medium" style="color: var(--text-primary);">
                                        <?= !empty($search) || !empty($status_filter) ? 'No prescriptions found matching your filters' : 'No prescriptions found' ?>
                                    </p>
                                    <p class="text-sm" style="color: var(--text-secondary);">
                                        <?= !empty($search) || !empty($status_filter) ? 'Try changing your search or filter criteria' : 'No prescriptions have been created yet' ?>
                                    </p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- ================================================================ -->
        <!-- PAGINATION -->
        <!-- ================================================================ -->
        <?php if ($total_pages > 1): ?>
            <div class="flex flex-wrap justify-between items-center gap-3 mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Showing <?= $offset + 1 ?> - <?= min($offset + $per_page, $total_prescriptions) ?> of <?= $total_prescriptions ?> prescriptions
                </div>
                
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                    <?php endif; ?>
                    
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="?page=<?= $p ?>&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" 
                           class="page-link <?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" class="page-link">
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
            Prescriptions Management
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
        var table = document.getElementById('prescriptionsTable');
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
    // TABLE SCROLL WITH ARROWS
    // ================================================================
    function scrollTable(direction) {
        var wrapper = document.getElementById('tableScrollWrapper');
        var scrollAmount = 350;
        
        if (direction === 'left') {
            wrapper.scrollLeft -= scrollAmount;
        } else {
            wrapper.scrollLeft += scrollAmount;
        }
        
        updateScrollButtons();
    }
    
    function updateScrollButtons() {
        var wrapper = document.getElementById('tableScrollWrapper');
        var leftBtn = document.getElementById('scrollLeftBtn');
        var rightBtn = document.getElementById('scrollRightBtn');
        
        if (wrapper.scrollLeft <= 10) {
            leftBtn.disabled = true;
        } else {
            leftBtn.disabled = false;
        }
        
        if (wrapper.scrollLeft >= wrapper.scrollWidth - wrapper.clientWidth - 10) {
            rightBtn.disabled = true;
        } else {
            rightBtn.disabled = false;
        }
    }
    
    document.getElementById('tableScrollWrapper')?.addEventListener('scroll', updateScrollButtons);
    
    window.addEventListener('load', function() {
        setTimeout(updateScrollButtons, 200);
    });
    
    window.addEventListener('resize', updateScrollButtons);

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.delete('page');
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

    console.log('%c🏥 Braick Dispensary - Prescriptions Management (FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💊 Total Prescriptions: <?= $total_all ?>', 'font-size:13px; color:#059669;');
    console.log('%c👨‍⚕️ Doctor names: NOW SHOWING from users table', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Data from: prescriptions table', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ FIXED: Doctor name displayed correctly', 'font-size:13px; color:#059669;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>