<?php
// ================================================================
// FILE: frontend/pages/admin/employees.php
// SUPER ADMIN - EMPLOYEE MANAGEMENT
// VERSION 3.2 - WITH SHARED SIDEBAR
// BRAICK DISPENSARY
// ================================================================

session_start();

// Check if logged in as super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

// Include database and helpers
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_name = 'All Branches';

// Check if branch_id column exists
$columns = [];
$col_check = $db->query("SHOW COLUMNS FROM users");
while ($col = $col_check->fetch(PDO::FETCH_ASSOC)) {
    $columns[] = $col['Field'];
}

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id = (int)$selected_branch_id;
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
    }
} else {
    $selected_branch_id = 'all';
}

// ================================================================
// SEARCH FROM TOP BAR - Get search term for display only
// ================================================================
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

// ================================================================
// GET EMPLOYEES
// ================================================================
$query = "SELECT u.*, b.name as branch_name";

$has_employee_roles = false;
try {
    $check = $db->query("SHOW TABLES LIKE 'employee_roles'");
    if ($check->rowCount() > 0) {
        $has_employee_roles = true;
        $query .= ", GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') as role_names";
    } else {
        $query .= ", '' as role_names";
    }
} catch (Exception $e) {
    $query .= ", '' as role_names";
}

$has_employee_depts = false;
try {
    $check = $db->query("SHOW TABLES LIKE 'employee_departments'");
    if ($check->rowCount() > 0) {
        $has_employee_depts = true;
        $query .= ", GROUP_CONCAT(DISTINCT d.name SEPARATOR ', ') as department_names";
    } else {
        $query .= ", '' as department_names";
    }
} catch (Exception $e) {
    $query .= ", '' as department_names";
}

$query .= " FROM users u LEFT JOIN branches b ON ";

if (in_array('branch_id', $columns)) {
    $query .= "u.branch_id = b.id";
} else {
    $query .= "1=0";
}

if ($has_employee_roles) {
    $query .= " LEFT JOIN employee_roles er ON er.user_id = u.id LEFT JOIN roles r ON r.id = er.role_id";
}

if ($has_employee_depts) {
    $query .= " LEFT JOIN employee_departments ed ON ed.user_id = u.id LEFT JOIN departments d ON d.id = ed.department_id";
}

$query .= " WHERE u.role != 'admin'";

// Branch filter
if ($selected_branch_id !== 'all' && in_array('branch_id', $columns)) {
    $query .= " AND u.branch_id = " . (int)$selected_branch_id;
}

// Search filter - GLOBAL
if ($search) {
    $query .= " AND (u.full_name LIKE '%$search%' OR u.username LIKE '%$search%' OR u.email LIKE '%$search%' OR u.phone LIKE '%$search%')";
}

// Role filter
if ($role_filter && $has_employee_roles) {
    $query .= " AND u.id IN (SELECT user_id FROM employee_roles WHERE role_id = " . (int)$role_filter . ")";
}

// Status filter
if ($status_filter && in_array('status', $columns)) {
    $query .= " AND u.status = '$status_filter'";
}

$query .= " GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $db->query($query);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================

$query_count = "SELECT COUNT(*) as count FROM users WHERE role != 'admin'";
if ($selected_branch_id !== 'all' && in_array('branch_id', $columns)) {
    $query_count .= " AND branch_id = " . (int)$selected_branch_id;
}
$stmt = $db->query($query_count);
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

if (in_array('status', $columns)) {
    $query_active = "SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'active'";
    if ($selected_branch_id !== 'all' && in_array('branch_id', $columns)) {
        $query_active .= " AND branch_id = " . (int)$selected_branch_id;
    }
    $stmt = $db->query($query_active);
    $active_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $query_inactive = "SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'inactive'";
    if ($selected_branch_id !== 'all' && in_array('branch_id', $columns)) {
        $query_inactive .= " AND branch_id = " . (int)$selected_branch_id;
    }
    $stmt = $db->query($query_inactive);
    $inactive_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} else {
    $active_employees = 0;
    $inactive_employees = 0;
}

$query_doctors = "SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'";
if ($selected_branch_id !== 'all' && in_array('branch_id', $columns)) {
    $query_doctors .= " AND branch_id = " . (int)$selected_branch_id;
}
$stmt = $db->query($query_doctors);
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// GET BRANCHES, ROLES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$roles = [];
try {
    $stmt = $db->query("SELECT id, name FROM roles ORDER BY name");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $roles = [];
}

// ================================================================
// HANDLE DELETE ACTION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    if ($action === 'delete') {
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['role'] !== 'admin') {
            try {
                $stmt = $db->prepare("DELETE FROM employee_roles WHERE user_id = ?");
                $stmt->execute([$user_id]);
            } catch (Exception $e) {}
            
            try {
                $stmt = $db->prepare("DELETE FROM employee_departments WHERE user_id = ?");
                $stmt->execute([$user_id]);
            } catch (Exception $e) {}
            
            $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
            if ($stmt->execute([$user_id])) {
                $message = "Employee deleted successfully!";
                $message_type = 'success';
            } else {
                $message = "Failed to delete employee!";
                $message_type = 'error';
            }
        } else {
            $message = "Cannot delete admin user!";
            $message_type = 'error';
        }
    }
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// Store search term for display in page title
$search_display = $search;

// ================================================================
// VARIABLES FOR SHARED SIDEBAR
// ================================================================
$total_employees = $total_employees ?? 0;
$total_doctors = $total_doctors ?? 0;
$total_branches = $total_branches ?? 0;
$pending_lab_tests = $pending_lab_tests ?? 0;
$pending_prescriptions = $pending_prescriptions ?? 0;
$selected_branch_id = $selected_branch_id ?? 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           BRAICK DISPENSARY - BLUE & GREEN THEME
           ================================================================ */
        
        :root {
            --blue-600: #0B5ED7;
            --blue-700: #0B4EA8;
            --green-600: #059669;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --white: #FFFFFF;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--gray-50);
            color: var(--gray-800);
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); }
        ::-webkit-scrollbar-thumb { background: var(--blue-600); border-radius: 10px; }
        
        /* ================================================================
           TOP NAV - WHITE
           ================================================================ */
        .top-nav {
            position: fixed; top: 0; left: 270px; right: 0;
            height: 68px; background: white; z-index: 40;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; border-bottom: 2px solid #D2E3FC;
        }
        
        .top-nav .search-wrapper {
            display: flex; align-items: center;
            background: var(--gray-50); border-radius: 10px;
            border: 2px solid var(--gray-200);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--blue-600);
            box-shadow: 0 0 0 3px #D2E3FC;
        }
        
        .top-nav .search-wrapper input {
            border: none; background: transparent;
            padding: 8px 14px; width: 100%;
            font-size: 0.85rem; outline: none;
            color: var(--gray-700);
        }
        
        .top-nav .search-wrapper input::placeholder { color: var(--gray-400); }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--blue-600); color: white;
            border: none; padding: 8px 16px;
            border-radius: 0 10px 10px 0;
            cursor: pointer; font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover { background: #0B3D8A; }
        
        .top-nav .branch-selector {
            border: 2px solid var(--gray-200);
            border-radius: 10px; padding: 6px 12px;
            background: white; font-size: 0.82rem;
            font-weight: 500; cursor: pointer; outline: none;
            min-width: 160px; color: var(--gray-700);
            transition: all 0.3s;
        }
        
        .top-nav .branch-selector:focus {
            border-color: var(--blue-600);
            box-shadow: 0 0 0 3px #D2E3FC;
        }
        
        .top-nav .datetime {
            font-size: 0.78rem; color: var(--gray-500); font-weight: 500;
        }
        
        .top-nav .avatar {
            width: 38px; height: 38px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--gray-200);
            cursor: pointer; transition: all 0.3s;
        }
        
        .top-nav .avatar:hover { border-color: var(--blue-600); }
        
        .top-nav .icon-btn {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--gray-500); transition: all 0.3s;
            background: transparent; border: none; cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover { background: #E8F0FE; color: var(--blue-600); }
        
        .notif-dot {
            position: absolute; top: 6px; right: 6px;
            width: 8px; height: 8px; background: var(--green-600);
            border-radius: 50%; border: 2px solid white;
        }
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px; margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           STAT CARDS - BLUE & GREEN (SOLID COLORS)
           ================================================================ */
        .stat-card {
            border-radius: 16px;
            padding: 18px 20px;
            border: none;
            transition: all 0.3s;
            color: white;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card.blue { background: var(--blue-600); }
        .stat-card.blue-dark { background: #0B3D8A; }
        .stat-card.blue-light { background: #1A73E8; }
        .stat-card.green { background: var(--green-600); }
        .stat-card.green-dark { background: #047857; }
        .stat-card.green-light { background: #0AA84F; }
        
        .stat-card .stat-icon {
            width: 42px; height: 42px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            background: rgba(255,255,255,0.15);
            color: white;
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem; font-weight: 700; color: white;
        }
        
        .stat-card .stat-label {
            font-size: 0.75rem; color: rgba(255,255,255,0.8); font-weight: 500;
        }
        
        .stat-card .stat-trend {
            font-size: 0.65rem; font-weight: 600;
            padding: 2px 10px; border-radius: 20px;
            background: rgba(255,255,255,0.15);
            color: white;
        }
        
        /* ================================================================
           CARDS - WHITE BACKGROUND
           ================================================================ */
        .card {
            background: white;
            border-radius: 16px;
            padding: 18px 20px;
            border: 2px solid var(--gray-200);
            transition: all 0.3s;
        }
        
        .card:hover {
            border-color: var(--blue-600);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
        }
        
        .card-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 12px;
            flex-wrap: wrap; gap: 8px;
        }
        
        .card-title {
            font-size: 0.9rem; font-weight: 600; color: var(--gray-800);
        }
        
        .card-title .title-blue { color: var(--blue-600); }
        .card-title .title-green { color: var(--green-600); }
        
        /* ================================================================
           TABLE - BLUE HEADER
           ================================================================ */
        .table-wrap { overflow-x: auto; }
        
        .data-table {
            width: 100%; border-collapse: collapse;
            font-size: 0.8rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 8px 10px;
            font-weight: 700;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--blue-600);
            border-bottom: 3px solid #0B3D8A;
            white-space: nowrap;
        }
        
        .data-table tbody tr:nth-child(even) {
            background: #E8F0FE;
        }
        
        .data-table tbody tr:nth-child(odd) {
            background: white;
        }
        
        .data-table tbody tr:hover {
            background: #D1FAE5;
        }
        
        .data-table td {
            padding: 6px 10px;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-700);
            vertical-align: middle;
        }
        
        /* HIGHLIGHT SEARCH RESULTS */
        .highlight {
            background: #FEF08A !important;
            padding: 1px 4px;
            border-radius: 3px;
            font-weight: 600;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            padding: 2px 10px; border-radius: 20px;
            font-size: 0.6rem; font-weight: 600;
            display: inline-flex; align-items: center; gap: 4px;
            color: white;
            border: none;
        }
        
        .badge-success { background: var(--green-600); color: white; }
        .badge-danger { background: #EF4444; color: white; }
        .badge-info { background: var(--blue-600); color: white; }
        
        .role-badge {
            padding: 1px 8px; border-radius: 10px;
            font-size: 0.6rem; font-weight: 500;
            background: #D2E3FC;
            color: var(--blue-700);
            margin: 1px; display: inline-block;
        }
        
        .dept-badge {
            padding: 1px 8px; border-radius: 10px;
            font-size: 0.6rem; font-weight: 500;
            background: #D1FAE5;
            color: #047857;
            margin: 1px; display: inline-block;
        }
        
        /* ================================================================
           BUTTONS - SMALL SIZE
           ================================================================ */
        .btn {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 6px;
            font-weight: 600; font-size: 0.65rem;
            transition: all 0.2s; cursor: pointer;
            border: none; text-decoration: none;
            white-space: nowrap;
        }
        
        .btn-blue {
            background: var(--blue-600); color: white;
        }
        .btn-blue:hover {
            background: #0B3D8A;
            transform: translateY(-1px);
        }
        
        .btn-green {
            background: var(--green-600); color: white;
        }
        .btn-green:hover {
            background: #047857;
            transform: translateY(-1px);
        }
        
        .btn-outline {
            background: transparent; color: var(--gray-600);
            border: 1.5px solid var(--gray-200);
        }
        .btn-outline:hover {
            background: #E8F0FE;
            border-color: var(--blue-600);
            color: var(--blue-600);
        }
        
        .btn-danger {
            background: #EF4444; color: white;
        }
        .btn-danger:hover {
            background: #DC2626;
            transform: translateY(-1px);
        }
        
        .btn-sm { padding: 3px 8px; font-size: 0.6rem; border-radius: 4px; }
        
        /* View, Edit, Delete - SMALL */
        .btn-view { 
            background: var(--blue-600); 
            color: white; 
            padding: 2px 7px; 
            font-size: 0.6rem; 
            border-radius: 4px;
        }
        .btn-view:hover { 
            background: #0B3D8A; 
            transform: scale(1.05);
        }
        
        .btn-edit { 
            background: var(--green-600); 
            color: white; 
            padding: 2px 7px; 
            font-size: 0.6rem; 
            border-radius: 4px;
        }
        .btn-edit:hover { 
            background: #047857; 
            transform: scale(1.05);
        }
        
        .btn-delete { 
            background: #EF4444; 
            color: white; 
            padding: 2px 7px; 
            font-size: 0.6rem; 
            border-radius: 4px;
        }
        .btn-delete:hover { 
            background: #DC2626; 
            transform: scale(1.05);
        }
        
        /* PDF & EXCEL Buttons */
        .btn-pdf {
            background: #DC2626; color: white;
            padding: 4px 12px; font-size: 0.7rem; border-radius: 6px;
        }
        .btn-pdf:hover {
            background: #B91C1C;
            transform: translateY(-1px);
        }
        
        .btn-excel {
            background: #059669; color: white;
            padding: 4px 12px; font-size: 0.7rem; border-radius: 6px;
        }
        .btn-excel:hover {
            background: #047857;
            transform: translateY(-1px);
        }
        
        /* ================================================================
           ACTION BUTTONS - INLINE
           ================================================================ */
        .action-buttons {
            display: flex;
            align-items: center;
            gap: 3px;
            flex-wrap: nowrap;
            justify-content: center;
        }
        
        .action-buttons .btn {
            padding: 2px 7px;
            font-size: 0.6rem;
            border-radius: 4px;
            font-weight: 600;
        }
        
        .action-buttons .btn i {
            font-size: 0.65rem;
        }
        
        /* ================================================================
           FILTERS
           ================================================================ */
        .filter-select {
            padding: 5px 10px; border-radius: 8px;
            border: 2px solid var(--gray-200);
            background: white;
            font-size: 0.75rem; outline: none;
            color: var(--gray-700); transition: all 0.3s;
        }
        
        .filter-select:focus {
            border-color: var(--blue-600);
            box-shadow: 0 0 0 3px #D2E3FC;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed; bottom: 24px; right: 24px;
            padding: 12px 18px; border-radius: 12px;
            z-index: 999; max-width: 360px;
            transform: translateY(100px); opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; align-items: center; gap: 10px;
            color: white;
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--green-600); }
        .toast-custom.error { background: #EF4444; }
        .toast-custom.info { background: var(--blue-600); }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            border-bottom: 3px solid var(--blue-600);
            padding-bottom: 12px;
        }
        
        .page-header .page-title {
            color: #0B3D8A;
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .page-header .page-subtitle {
            color: var(--gray-500);
            font-size: 0.9rem;
        }
        
        .page-header .branch-tag {
            background: var(--green-600);
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
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--gray-200);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--gray-500);
        }
        
        .footer .footer-brand {
            color: var(--blue-600);
            font-weight: 600;
        }
        
        .result-count {
            font-size: 0.75rem;
            color: var(--gray-500);
            font-weight: 500;
        }
        .result-count strong {
            color: var(--blue-600);
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .branch-selector { min-width: 120px; font-size: 0.7rem; }
            .top-nav .datetime { display: none; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .stat-card .stat-number { font-size: 1.4rem; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        
        .animate-fade-in-up:nth-child(1) { animation-delay: 0.03s; }
        .animate-fade-in-up:nth-child(2) { animation-delay: 0.06s; }
        .animate-fade-in-up:nth-child(3) { animation-delay: 0.09s; }
        .animate-fade-in-up:nth-child(4) { animation-delay: 0.12s; }
        
        .spinner {
            display: inline-block; width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SHARED SIDEBAR - SAME FOR ALL PAGES -->
<!-- ================================================================ -->
<?php include_once '../../components/admin_sidebar.php'; ?>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - WITH GLOBAL SEARCH -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <!-- GLOBAL SEARCH - ONE SEARCH BAR -->
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="globalSearch" placeholder="Search employees by name, email, ID...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2238%22 height=%2238%22%3E%3Crect width=%2238%22 height=%2238%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2219%22 y=%2225%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-users mr-2" style="color: var(--blue-600);"></i> Employee Management
            </h1>
            <p class="page-subtitle">
                Manage all employees • <?= htmlspecialchars($branch_name) ?>
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                <?php if ($search_display): ?>
                    <span class="ml-2 inline-flex bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs border border-yellow-200">
                        <i class="fas fa-search mr-1"></i> Results for: "<?= htmlspecialchars($search_display) ?>"
                        <a href="?branch=<?= $selected_branch_id ?>" class="ml-2 text-yellow-600 hover:text-yellow-800" title="Clear search">
                            <i class="fas fa-times"></i>
                        </a>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <!-- PDF & EXCEL Buttons -->
            <button onclick="exportPDF()" class="btn btn-pdf btn-sm">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button onclick="exportExcel()" class="btn btn-excel btn-sm">
                <i class="fas fa-file-excel"></i> Excel
            </button>
            <a href="add_employee.php?branch=<?= $selected_branch_id ?>" class="btn btn-blue btn-sm">
                <i class="fas fa-user-plus"></i> Add
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS - BLUE & GREEN -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
        
        <div class="stat-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Total Employees</p>
                    <p class="stat-number"><?= $total_employees ?></p>
                    <span class="stat-trend"><i class="fas fa-users"></i> All staff</span>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        
        <div class="stat-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Active</p>
                    <p class="stat-number"><?= $active_employees ?></p>
                    <span class="stat-trend"><i class="fas fa-user-check"></i> Online</span>
                </div>
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
            </div>
        </div>
        
        <div class="stat-card blue-dark animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Inactive</p>
                    <p class="stat-number"><?= $inactive_employees ?></p>
                    <span class="stat-trend"><i class="fas fa-user-slash"></i> Offline</span>
                </div>
                <div class="stat-icon"><i class="fas fa-user-slash"></i></div>
            </div>
        </div>
        
        <div class="stat-card green-dark animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Doctors</p>
                    <p class="stat-number"><?= $total_doctors ?></p>
                    <span class="stat-trend"><i class="fas fa-user-md"></i> Active</span>
                </div>
                <div class="stat-icon"><i class="fas fa-user-md"></i></div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS (Role & Status Only - Search is in Top Bar) -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-sm font-medium text-gray-600"><i class="fas fa-filter mr-1"></i> Filters:</span>
            
            <select id="roleFilter" class="filter-select" onchange="applyFilters()">
                <option value="">All Roles</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= $role['id'] ?>" <?= $role_filter == $role['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($role['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select id="statusFilter" class="filter-select" onchange="applyFilters()">
                <option value="">All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            
            <button onclick="clearFilters()" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Clear
            </button>
            
            <span class="result-count ml-auto">
                <strong><?= count($employees) ?></strong> record(s) found
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- EMPLOYEES TABLE - View, Edit, Delete (Small, Inline) -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Employee List
            </h3>
        </div>
        
        <div class="table-wrap">
            <table class="data-table" id="employeeTable">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">Employee</th>
                        <th>ID</th>
                        <th>Branch</th>
                        <th>Departments</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th style="border-radius: 0 8px 0 0; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($employees) > 0): ?>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs">
                                            <?= strtoupper(substr($emp['full_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <p class="font-medium text-sm text-gray-800">
                                                <?php 
                                                    $name = htmlspecialchars($emp['full_name']);
                                                    if ($search_display) {
                                                        $name = preg_replace('/(' . preg_quote($search_display, '/') . ')/i', '<span class="highlight">$1</span>', $name);
                                                    }
                                                    echo $name;
                                                ?>
                                            </p>
                                            <p class="text-xs text-gray-400">
                                                <?php 
                                                    $email = htmlspecialchars($emp['email']);
                                                    if ($search_display) {
                                                        $email = preg_replace('/(' . preg_quote($search_display, '/') . ')/i', '<span class="highlight">$1</span>', $email);
                                                    }
                                                    echo $email;
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-xs font-mono bg-gray-100 px-2 py-0.5 rounded">
                                        <?php 
                                            $username = htmlspecialchars($emp['username'] ?? 'N/A');
                                            if ($search_display) {
                                                $username = preg_replace('/(' . preg_quote($search_display, '/') . ')/i', '<span class="highlight">$1</span>', $username);
                                            }
                                            echo $username;
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-sm"><?= htmlspecialchars($emp['branch_name'] ?? 'Not Assigned') ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($emp['department_names']) && $emp['department_names'] != ''): ?>
                                        <?php foreach (explode(', ', $emp['department_names']) as $dept): ?>
                                            <span class="dept-badge"><?= htmlspecialchars($dept) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($emp['role_names']) && $emp['role_names'] != ''): ?>
                                        <?php foreach (explode(', ', $emp['role_names']) as $role): ?>
                                            <span class="role-badge"><?= htmlspecialchars($role) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($emp['status'])): ?>
                                        <span class="badge <?= $emp['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                            <i class="fas fa-circle text-[5px]"></i>
                                            <?= ucfirst($emp['status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-info">
                                            <i class="fas fa-circle text-[5px]"></i> Active
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- VIEW - Small -->
                                        <a href="employee_profile.php?id=<?= $emp['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn btn-view" 
                                           title="View Profile">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <!-- EDIT - Small -->
                                        <a href="edit_employee.php?id=<?= $emp['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn btn-edit" 
                                           title="Edit Employee">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <!-- DELETE - Small -->
                                        <button onclick="deleteEmployee(<?= $emp['id'] ?>)" 
                                                class="btn btn-delete" 
                                                title="Delete Employee">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-400">
                                <i class="fas fa-users text-3xl block mb-2"></i>
                                <?php if ($search_display): ?>
                                    No employees found matching "<strong><?= htmlspecialchars($search_display) ?></strong>"
                                <?php else: ?>
                                    No employees found. Click "Add Employee" to get started.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Employee Management v3.2
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
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const branchSelector = document.getElementById('branchSelector');
    const globalSearch = document.getElementById('globalSearch');
    const searchBtn = document.getElementById('searchBtn');
    const toast = document.getElementById('toast');

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
    // GLOBAL SEARCH
    // ================================================================
    function performSearch() {
        var query = globalSearch.value.trim();
        var branch = '<?= $selected_branch_id ?>';
        var role = document.getElementById('roleFilter').value;
        var status = document.getElementById('statusFilter').value;
        
        var url = window.location.pathname + '?branch=' + branch;
        if (query) url += '&search=' + encodeURIComponent(query);
        if (role) url += '&role=' + role;
        if (status) url += '&status=' + status;
        
        // Clear search bar after search
        globalSearch.value = '';
        
        window.location.href = url;
    }
    
    searchBtn?.addEventListener('click', performSearch);
    globalSearch?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        var search = globalSearch.value.trim();
        if (search) url.searchParams.set('search', search);
        window.location.href = url.toString();
    }

    // ================================================================
    // FILTERS
    // ================================================================
    function applyFilters() {
        var search = globalSearch.value.trim();
        var role = document.getElementById('roleFilter').value;
        var status = document.getElementById('statusFilter').value;
        var branch = '<?= $selected_branch_id ?>';
        
        var url = window.location.pathname + '?branch=' + branch;
        if (search) url += '&search=' + encodeURIComponent(search);
        if (role) url += '&role=' + role;
        if (status) url += '&status=' + status;
        
        window.location.href = url;
    }
    
    function clearFilters() {
        document.getElementById('roleFilter').value = '';
        document.getElementById('statusFilter').value = '';
        globalSearch.value = '';
        applyFilters();
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
    // DELETE EMPLOYEE
    // ================================================================
    function deleteEmployee(userId) {
        if (confirm('⚠️ Are you sure you want to DELETE this employee?\n\nThis action CANNOT be undone!')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="${userId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // ================================================================
    // EXPORT PDF & EXCEL
    // ================================================================
    function exportPDF() {
        showToast('PDF Export', 'Generating PDF report...', 'info');
        var branch = '<?= $selected_branch_id ?>';
        var search = globalSearch.value.trim();
        var url = 'reports.php?export=pdf&branch=' + branch;
        if (search) url += '&search=' + encodeURIComponent(search);
        window.location.href = url;
    }
    
    function exportExcel() {
        showToast('Excel Export', 'Preparing Excel export...', 'info');
        var branch = '<?= $selected_branch_id ?>';
        var search = globalSearch.value.trim();
        var url = 'reports.php?export=excel&branch=' + branch;
        if (search) url += '&search=' + encodeURIComponent(search);
        window.location.href = url;
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
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            globalSearch?.focus();
            globalSearch?.select();
        }
        if (e.key === 'Escape' && document.activeElement === globalSearch) {
            globalSearch.value = '';
            performSearch();
        }
    });

    console.log('%c👥 Braick - Employee Management v3.2', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total Employees: <?= $total_employees ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔗 Using Shared Sidebar', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>