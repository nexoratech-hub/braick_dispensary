<?php
// ================================================================
// FILE: frontend/pages/admin/patients.php
// SUPER ADMIN - MANAGE PATIENTS
// WITH TIME PERIOD FILTERS
// BRAICK DISPENSARY - FULL DELETE FUNCTIONALITY - WITH LOGIN SESSION
// ================================================================

// ================================================================
// START SESSION
// ================================================================
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
// CHECK IF USER HAS ADMIN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// IF SESSION IS INCOMPLETE, TRY TO RECOVER FROM DATABASE
// ================================================================
if ($user_id <= 0) {
    if (isset($username) && !empty($username)) {
        require_once __DIR__ . '/../../../backend/config/database.php';
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, full_name, role, branch_id, profile_pic FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $_SESSION['profile_pic'] = $user['profile_pic'];
                $user_id = $user['id'];
                $user_full_name = $user['full_name'];
                $user_role = $user['role'];
                $user_branch_id = $user['branch_id'];
                $profile_pic = $user['profile_pic'];
            }
        } catch (Exception $e) {
            // Fallback to session values
        }
    }
}

// If still no user_id, redirect to login
if ($user_id <= 0) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// INCLUDE DATABASE AND HELPERS
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

// ================================================================
// GET DATABASE CONNECTION
// ================================================================
try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
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
$selected_branch_id = $_GET['branch'] ?? 'all';
$time_period = isset($_GET['period']) ? $_GET['period'] : 'all';

// ================================================================
// BUILD TIME PERIOD FILTER
// ================================================================
$date_condition = '';

switch ($time_period) {
    case 'today':
        $date_condition = "DATE(p.created_at) = CURDATE()";
        break;
    case 'week':
        $date_condition = "p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_condition = "p.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case '3months':
        $date_condition = "p.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        break;
    case '6months':
        $date_condition = "p.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        break;
    case 'year':
        $date_condition = "p.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    case 'all':
    default:
        $date_condition = "1=1";
        break;
}

// ================================================================
// ✅ DELETE PATIENT - FULL DELETION WITH ALL 23 TABLES
// ================================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $patient_id = (int)$_GET['delete'];
    
    try {
        $db->beginTransaction();
        
        // Get patient info first
        $stmt = $db->prepare("SELECT full_name, patient_id FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($patient) {
            $patient_name = $patient['full_name'];
            $patient_number = $patient['patient_id'];
            
            // ============================================================
            // DELETE FROM ALL 23 TABLES WITH patient_id
            // ============================================================
            
            $tables = [
                'activity_logs',
                'appointments',
                'bill_items',
                'lab_billing_items',
                'lab_requests',
                'lab_request_items',
                'notifications',
                'otc_sales',
                'otc_sale_items',
                'patient_bills',
                'patient_documents',
                'payments',
                'pharmacy_sales',
                'prescriptions',
                'prescription_items',
                'prescription_sales',
                'prescription_sale_items',
                'receipts',
                'referrals',
                'referral_logs',
                'stock_movements',
                'visits',
                'vital_signs'
            ];
            
            // Delete from each table
            $deleted_count = 0;
            foreach ($tables as $table) {
                try {
                    $stmt = $db->prepare("DELETE FROM `$table` WHERE patient_id = ?");
                    $stmt->execute([$patient_id]);
                    $deleted_count++;
                } catch (Exception $e) {
                    // Log error but continue
                    error_log("Warning: Could not delete from $table: " . $e->getMessage());
                }
            }
            
            // ============================================================
            // FINAL: DELETE PATIENT
            // ============================================================
            $stmt = $db->prepare("DELETE FROM patients WHERE id = ?");
            $stmt->execute([$patient_id]);
            
            // Log activity
            try {
                $log_stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                    VALUES (?, ?, 'patient_deleted', ?, NOW())
                ");
                $log_stmt->execute([
                    $user_id, 
                    $user_branch_id,
                    "Patient deleted: $patient_name (ID: $patient_number) by " . $user_full_name
                ]);
            } catch (Exception $e) {
                // Silent fail
            }
            
            $db->commit();
            
            $message = "✅ Patient '$patient_name' (ID: $patient_number) deleted successfully!";
            $message .= " (Deleted from $deleted_count tables)";
            $message_type = 'success';
            
            header("Location: patients.php?page=$page&period=$time_period" . 
                   ($search ? "&search=" . urlencode($search) : "") . 
                   "&branch=" . urlencode($selected_branch_id));
            exit();
            
        } else {
            $db->rollBack();
            $message = "❌ Patient not found!";
            $message_type = 'error';
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        $message = "❌ Error deleting patient: " . $e->getMessage();
        $message_type = 'error';
        error_log("Delete Patient Error: " . $e->getMessage());
    }
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PATIENTS WITH PAGINATION, SEARCH, DATE FILTER AND BRANCH FILTER
// ================================================================
$where_clause = "";
$params = [];

// Add date condition
$where_clause .= " WHERE $date_condition";

// Add search filter
if (!empty($search)) {
    $where_clause .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ? OR p.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add branch filter
if ($selected_branch_id !== 'all') {
    $where_clause .= " AND p.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

// ================================================================
// GET STATISTICS WITH TIME PERIOD
// ================================================================
$stats_where = "WHERE $date_condition";
$stats_params = [];

if ($selected_branch_id !== 'all') {
    $stats_where .= " AND branch_id = ?";
    $stats_params[] = (int)$selected_branch_id;
}

if (!empty($search)) {
    $stats_where .= " AND (full_name LIKE ? OR patient_id LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $stats_params[] = "%$search%";
    $stats_params[] = "%$search%";
    $stats_params[] = "%$search%";
    $stats_params[] = "%$search%";
}

$stats_sql = "SELECT COUNT(*) as total FROM patients p $stats_where";
$stmt = $db->prepare($stats_sql);
$stmt->execute($stats_params);
$total_all = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Today's patients
$today_sql = "SELECT COUNT(*) as total FROM patients WHERE DATE(created_at) = CURDATE()";
if ($selected_branch_id !== 'all') {
    $today_sql .= " AND branch_id = " . (int)$selected_branch_id;
}
$stmt = $db->query($today_sql);
$today_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// With branch assigned
$branch_sql = "SELECT COUNT(*) as total FROM patients WHERE branch_id IS NOT NULL";
if ($selected_branch_id !== 'all') {
    $branch_sql .= " AND branch_id = " . (int)$selected_branch_id;
}
$stmt = $db->query($branch_sql);
$with_branch = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// GET TOTAL COUNT FOR PAGINATION
// ================================================================
$count_sql = "SELECT COUNT(*) as total FROM patients p $where_clause";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_patients = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_patients / $per_page);

// ================================================================
// GET PATIENTS FOR CURRENT PAGE
// ================================================================
$sql = "
    SELECT p.*, b.name as branch_name, 
           (SELECT COUNT(*) FROM visits WHERE patient_id = p.id) as total_visits,
           (SELECT COUNT(*) FROM patient_bills WHERE patient_id = p.id AND status != 'cancelled') as total_bills
    FROM patients p
    LEFT JOIN branches b ON p.branch_id = b.id
    $where_clause
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($sql);
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET TIME PERIOD LABEL
// ================================================================
$period_labels = [
    'today' => 'Today',
    'week' => 'This Week',
    'month' => 'This Month',
    '3months' => '3 Months',
    '6months' => '6 Months',
    'year' => '1 Year',
    'all' => 'All Time'
];
$period_label = $period_labels[$time_period] ?? 'All Time';

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
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<style>
    /* ================================================================
       ADDITIONAL STYLES
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
    
    .stat-card-mini .stat-number.green {
        color: #059669;
    }
    
    .stat-card-mini .stat-number.orange {
        color: #F59E0B;
    }
    
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
    
    /* Time Period Filters */
    .period-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 16px;
    }
    
    .period-btn {
        padding: 5px 14px;
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
    
    .period-btn:hover {
        border-color: #0B5ED7;
        color: #0B5ED7;
        background: #E8F0FE;
    }
    
    .period-btn.active {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }
    
    .period-btn.active:hover {
        background: #0A4CA8;
        border-color: #0A4CA8;
    }
    
    [data-theme="dark"] .period-btn:hover {
        background: #1E3A5F;
        border-color: #6EA8FE;
        color: #6EA8FE;
    }
    
    [data-theme="dark"] .period-btn.active {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }
    
    .period-btn i {
        margin-right: 4px;
    }
    
    /* Table Container */
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
    
    /* Scroll Controls */
    .scroll-controls {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-left: auto;
    }
    
    .scroll-btn-header {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        border: none;
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        color: white;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .scroll-btn-header:hover {
        background: linear-gradient(135deg, #0A4CA8, #0B5ED7);
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
    
    [data-theme="dark"] .scroll-btn-header {
        background: linear-gradient(135deg, #1A73E8, #0B5ED7);
    }
    
    .scroll-indicator {
        font-size: 0.65rem;
        color: var(--text-secondary);
        font-weight: 500;
        padding: 0 4px;
    }
    
    /* Table Header - Blue Theme */
    .table-blue thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8) !important;
        color: #FFFFFF !important;
        font-weight: 700 !important;
        font-size: 0.7rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        padding: 12px 16px !important;
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
        padding: 10px 16px !important;
        border-bottom: 1px solid #E2E8F0 !important;
        color: #1E293B !important;
        vertical-align: middle !important;
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
        background: #1A3A5F !important;
    }
    
    /* Table Header Wrapper */
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
    
    /* Badge styles */
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
    
    .badge-blue {
        background: #E8F0FE !important;
        color: #0B5ED7 !important;
    }
    
    .badge-green {
        background: #D1FAE5 !important;
        color: #059669 !important;
    }
    
    [data-theme="dark"] .badge-blue {
        background: #1E3A5F !important;
        color: #6EA8FE !important;
    }
    
    [data-theme="dark"] .badge-green {
        background: #1A3A2A !important;
        color: #34D399 !important;
    }
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }
    
    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 0.65rem;
        font-weight: 600;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        border: none;
        cursor: pointer;
        letter-spacing: 0.01em;
        white-space: nowrap;
    }
    
    .btn-action i {
        font-size: 0.7rem;
    }
    
    .btn-view {
        background: #E8F0FE;
        color: #0B5ED7;
        border: 1px solid rgba(11, 94, 215, 0.15);
    }
    
    .btn-view:hover {
        background: #0B5ED7;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .btn-edit {
        background: #D1FAE5;
        color: #059669;
        border: 1px solid rgba(5, 150, 105, 0.15);
    }
    
    .btn-edit:hover {
        background: #059669;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    
    .btn-delete {
        background: #FEE2E2;
        color: #EF4444;
        border: 1px solid rgba(239, 68, 68, 0.15);
    }
    
    .btn-delete:hover {
        background: #EF4444;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }
    
    [data-theme="dark"] .btn-view {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: rgba(110, 168, 254, 0.15);
    }
    
    [data-theme="dark"] .btn-view:hover {
        background: #0B5ED7;
        color: white;
    }
    
    [data-theme="dark"] .btn-edit {
        background: #1A3A2A;
        color: #34D399;
        border-color: rgba(52, 211, 153, 0.15);
    }
    
    [data-theme="dark"] .btn-edit:hover {
        background: #059669;
        color: white;
    }
    
    [data-theme="dark"] .btn-delete {
        background: #3A1A1A;
        color: #F87171;
        border-color: rgba(248, 113, 113, 0.15);
    }
    
    [data-theme="dark"] .btn-delete:hover {
        background: #EF4444;
        color: white;
    }
    
    /* Pagination */
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
    
    /* Period label in header */
    .period-label {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    [data-theme="dark"] .period-label {
        background: #1E3A5F;
        color: #6EA8FE;
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
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .card-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .title-blue {
        color: #0B5ED7;
    }
    
    /* Page Header */
    .page-header {
        margin-bottom: 20px;
    }
    
    .page-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
    }
    
    .page-subtitle {
        font-size: 0.85rem;
        color: var(--text-secondary);
    }
    
    .btn-blue {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        border: none;
        padding: 8px 18px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-blue:hover {
        background: linear-gradient(135deg, #0A4CA8, #083C8A);
        transform: translateY(-2px);
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
        color: white;
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-primary);
        border: 2px solid var(--border-color);
        padding: 6px 14px;
        border-radius: 8px;
        font-weight: 500;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-outline:hover {
        border-color: #0B5ED7;
        color: #0B5ED7;
    }
    
    .btn-sm {
        padding: 5px 12px;
        font-size: 0.7rem;
    }
    
    .branch-tag {
        background: #E8F0FE;
        color: #0B5ED7;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 500;
    }
    
    [data-theme="dark"] .branch-tag {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    /* Toast */
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 14px 20px;
        border-radius: 12px;
        z-index: 999;
        max-width: 400px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    }
    .toast-custom.show {
        transform: translateY(0);
        opacity: 1;
    }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #DC2626; }
    .toast-custom.info { background: #0B5ED7; }
    .toast-custom.warning { background: #D97706; }
    
    /* Footer */
    .footer {
        margin-top: 30px;
        padding-top: 15px;
        border-top: 1px solid var(--border-color);
        text-align: center;
        font-size: 0.75rem;
        color: var(--text-secondary);
    }
    
    .footer-brand {
        font-weight: 600;
        color: #0B5ED7;
    }
    
    [data-theme="dark"] .footer-brand {
        color: #6EA8FE;
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
                <input type="hidden" name="period" value="<?= htmlspecialchars($time_period) ?>">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Search patients..." 
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
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
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
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-users mr-2"></i> Manage Patients
            </h1>
            <p class="page-subtitle">
                View and manage all patients in the system
                <span class="branch-tag ml-2">
                    <i class="fas fa-user-injured"></i> <?= $total_all ?> Total Patients
                </span>
                <span class="period-label ml-2">
                    <i class="fas fa-calendar-alt"></i> <?= $period_label ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="add_patient.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-blue btn-sm">
                <i class="fas fa-plus"></i> Add Patient
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        
        <div class="stat-card-mini">
            <div class="stat-icon">👤</div>
            <p class="stat-number"><?= $total_all ?></p>
            <p class="stat-label">Total Patients (<?= $period_label ?>)</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">📅</div>
            <p class="stat-number green"><?= $today_patients ?></p>
            <p class="stat-label">Today's Patients</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">🏥</div>
            <p class="stat-number orange"><?= $with_branch ?></p>
            <p class="stat-label">With Branch Assigned</p>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- TIME PERIOD FILTERS -->
    <!-- ================================================================ -->
    <div class="period-filters">
        <a href="?period=all&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?>" 
           class="period-btn <?= $time_period === 'all' ? 'active' : '' ?>">
            <i class="fas fa-globe"></i> All
        </a>
        <a href="?period=today&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?>" 
           class="period-btn <?= $time_period === 'today' ? 'active' : '' ?>">
            <i class="fas fa-calendar-day"></i> Today
        </a>
        <a href="?period=week&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?>" 
           class="period-btn <?= $time_period === 'week' ? 'active' : '' ?>">
            <i class="fas fa-calendar-week"></i> Week
        </a>
        <a href="?period=month&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?>" 
           class="period-btn <?= $time_period === 'month' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> Month
        </a>
        <a href="?period=3months&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?>" 
           class="period-btn <?= $time_period === '3months' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> 3 Months
        </a>
        <a href="?period=6months&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?>" 
           class="period-btn <?= $time_period === '6months' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> 6 Months
        </a>
        <a href="?period=year&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?>" 
           class="period-btn <?= $time_period === 'year' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> 1 Year
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENTS LIST -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i>
                Patients List
                <span class="text-sm font-normal text-gray-400">(<?= $total_patients ?> patients)</span>
            </h3>
            <div class="flex gap-2">
                <?php if (!empty($search)): ?>
                    <a href="patients.php?period=<?= $time_period ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-times"></i> Clear Search
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Table Header with Search and Scroll Controls -->
        <div class="table-header-wrapper">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="tableSearch" placeholder="Filter patients in table..." onkeyup="filterTable()">
            </div>
            
            <div class="search-info">
                Showing <strong id="visibleCount"><?= count($patients) ?></strong> of <strong id="totalCount"><?= $total_patients ?></strong> patients
            </div>
            
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
        
        <!-- Table -->
        <div class="table-container">
            <div class="table-scroll-wrapper" id="tableScrollWrapper">
                <table class="data-table table-blue w-full" id="patientsTable">
                    <thead>
                        <tr>
                            <th style="width: 50px; min-width: 50px;">#</th>
                            <th style="min-width: 120px;">Patient ID</th>
                            <th style="min-width: 150px;">Full Name</th>
                            <th style="min-width: 120px;">Phone</th>
                            <th style="min-width: 180px;">Email</th>
                            <th style="min-width: 120px;">Branch</th>
                            <th style="width: 80px; min-width: 80px;">Visits</th>
                            <th style="width: 80px; min-width: 80px;">Bills</th>
                            <th style="min-width: 110px;">Registered</th>
                            <th style="min-width: 200px;">Actions</th>
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
                                    <td><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="badge badge-blue">
                                            <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-green">
                                            <?= $patient['total_visits'] ?? 0 ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-blue">
                                            <?= $patient['total_bills'] ?? 0 ?>
                                        </span>
                                    </td>
                                    <td class="text-xs text-gray-500 dark:text-gray-400">
                                        <?= date('M d, Y', strtotime($patient['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="patient_details.php?id=<?= $patient['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" 
                                               class="btn-action btn-view" title="View Patient">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="edit_patient.php?id=<?= $patient['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" 
                                               class="btn-action btn-edit" title="Edit Patient">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="?delete=<?= $patient['id'] ?>&page=<?= $page ?>&period=<?= $time_period ?>&branch=<?= urlencode($selected_branch_id) ?><?= $search ? '&search='.urlencode($search) : '' ?>" 
                                               class="btn-action btn-delete" 
                                               onclick="return confirmDelete('<?= htmlspecialchars($patient['full_name']) ?>', '<?= htmlspecialchars($patient['patient_id']) ?>')" 
                                               title="Delete Patient">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-8 text-gray-400">
                                    <i class="fas fa-user-injured text-4xl block mb-3" style="color: #0B5ED7;"></i>
                                    <p class="text-lg font-medium" style="color: #1E293B; dark:text-white;">
                                        <?= !empty($search) ? 'No patients found matching "' . htmlspecialchars($search) . '"' : 'No patients found' ?>
                                    </p>
                                    <p class="text-sm">
                                        <?= !empty($search) ? 'Try a different search term' : 'Click "Add Patient" to create one' ?>
                                    </p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="flex flex-wrap justify-between items-center gap-3 mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Showing <?= $offset + 1 ?> - <?= min($offset + $per_page, $total_patients) ?> of <?= $total_patients ?> patients
                </div>
                
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&period=<?= $time_period ?>&branch=<?= urlencode($selected_branch_id) ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                    <?php endif; ?>
                    
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="?page=<?= $p ?>&period=<?= $time_period ?>&branch=<?= urlencode($selected_branch_id) ?><?= $search ? '&search='.urlencode($search) : '' ?>" 
                           class="page-link <?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&period=<?= $time_period ?>&branch=<?= urlencode($selected_branch_id) ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="page-link">
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

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Patients Management
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
    // TABLE SEARCH FILTER
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
    // TABLE SCROLL
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
    // CONFIRM DELETE - FULL WARNING
    // ================================================================
    function confirmDelete(patientName, patientId) {
        return confirm(
            '⚠️⚠️⚠️ WARNING: THIS ACTION CANNOT BE UNDONE! ⚠️⚠️⚠️\n\n' +
            'Are you sure you want to permanently delete patient:\n' +
            '┌─────────────────────────────────────────────┐\n' +
            '│ Name: ' + patientName + '\n' +
            '│ ID: ' + patientId + '\n' +
            '└─────────────────────────────────────────────┘\n\n' +
            'This will DELETE ALL related data from 23 tables:\n' +
            '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n' +
            '📋 Visits & Appointments\n' +
            '💰 Bills, Payments & Receipts\n' +
            '💊 Prescriptions & Medications\n' +
            '🔬 Lab Tests & Results\n' +
            '📝 Patient Documents\n' +
            '💳 OTC Sales\n' +
            '📊 Vital Signs\n' +
            '🔄 Referrals\n' +
            '📧 Notifications\n' +
            '📜 Activity Logs\n' +
            '📦 Stock Movements\n' +
            '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n' +
            'Click OK to confirm permanent deletion.\n' +
            'Click Cancel to abort.'
        );
    }

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

    console.log('%c🏥 Braick Dispensary - Patients Management (WITH LOGIN SESSION)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Total Patients: <?= $total_all ?>', 'font-size:13px; color:#059669;');
    console.log('%c📅 Today\'s Patients: <?= $today_patients ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🗑️ DELETE PATIENT - Full deletion from 23 tables', 'font-size:13px; color:#EF4444;');
    console.log('%c✅ All tables have patient_id column', 'font-size:13px; color:#059669;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>