<?php
// ================================================================
// FILE: frontend/pages/admin/branch_staff.php
// SUPER ADMIN - BRANCH STAFF MANAGEMENT
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK LOGIN SESSION
// ================================================================
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../../dashboard.php');
    exit();
}

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET BRANCH ID
// ================================================================
$branch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($branch_id <= 0) {
    header('Location: branches.php');
    exit();
}

// ================================================================
// GET BRANCH INFO
// ================================================================
$branch_stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
$branch_stmt->execute([$branch_id]);
$branch = $branch_stmt->fetch(PDO::FETCH_ASSOC);

if (!$branch) {
    header('Location: branches.php');
    exit();
}

// ================================================================
// SEARCH FUNCTIONALITY
// ================================================================
$search_term = $_GET['search'] ?? '';

// ================================================================
// GET STAFF FOR THIS BRANCH
// ================================================================
$staff_query = "
    SELECT 
        u.*,
        b.name as branch_name
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.branch_id = ?
";

if (!empty($search_term)) {
    $staff_query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.username LIKE ? OR u.role LIKE ?)";
}

$staff_query .= " ORDER BY u.role, u.full_name";

$staff_stmt = $db->prepare($staff_query);

if (!empty($search_term)) {
    $search_pattern = '%' . $search_term . '%';
    $staff_stmt->execute([$branch_id, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern]);
} else {
    $staff_stmt->execute([$branch_id]);
}

$staff_list = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STAFF COUNTS BY ROLE
// ================================================================
$count_stmt = $db->prepare("
    SELECT role, COUNT(*) as count 
    FROM users 
    WHERE branch_id = ? AND status = 'active'
    GROUP BY role
");
$count_stmt->execute([$branch_id]);
$role_counts = $count_stmt->fetchAll(PDO::FETCH_ASSOC);

$role_count_map = [];
foreach ($role_counts as $rc) {
    $role_count_map[$rc['role']] = $rc['count'];
}

$total_staff = count($staff_list);
$active_staff = 0;
$inactive_staff = 0;

foreach ($staff_list as $staff) {
    if ($staff['status'] === 'active') {
        $active_staff++;
    } else {
        $inactive_staff++;
    }
}

// ================================================================
// HANDLE STAFF STATUS TOGGLE
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_status') {
        $staff_id = (int)($_POST['staff_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        
        if ($staff_id > 0 && in_array($status, ['active', 'inactive'])) {
            try {
                $stmt = $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $staff_id]);
                $message = "✅ Staff status updated to <strong>" . ucfirst($status) . "</strong>";
                $message_type = 'success';
                
                // Refresh staff list
                if (!empty($search_term)) {
                    $staff_stmt->execute([$branch_id, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern]);
                } else {
                    $staff_stmt->execute([$branch_id]);
                }
                $staff_list = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Recalculate counts
                $active_staff = 0;
                $inactive_staff = 0;
                foreach ($staff_list as $staff) {
                    if ($staff['status'] === 'active') {
                        $active_staff++;
                    } else {
                        $inactive_staff++;
                    }
                }
                $total_staff = count($staff_list);
                
            } catch (PDOException $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    if ($action === 'delete_staff') {
        $staff_id = (int)($_POST['staff_id'] ?? 0);
        
        if ($staff_id > 0) {
            try {
                // Check if staff has any dependencies
                $check_stmt = $db->prepare("
                    SELECT COUNT(*) as count FROM visits WHERE doctor_id = ?
                ");
                $check_stmt->execute([$staff_id]);
                $visit_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                
                if ($visit_count > 0) {
                    $message = "❌ Cannot delete this staff member. They have $visit_count associated visits.";
                    $message_type = 'error';
                } else {
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$staff_id]);
                    $message = "✅ Staff member removed successfully.";
                    $message_type = 'success';
                    
                    // Refresh staff list
                    if (!empty($search_term)) {
                        $staff_stmt->execute([$branch_id, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern]);
                    } else {
                        $staff_stmt->execute([$branch_id]);
                    }
                    $staff_list = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Recalculate counts
                    $active_staff = 0;
                    $inactive_staff = 0;
                    foreach ($staff_list as $staff) {
                        if ($staff['status'] === 'active') {
                            $active_staff++;
                        } else {
                            $inactive_staff++;
                        }
                    }
                    $total_staff = count($staff_list);
                }
            } catch (PDOException $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$user_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$selected_branch_id = $branch_id;
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff - <?= htmlspecialchars($branch['name']) ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #1A56DB;
            --primary-dark: #1A3E8C;
            --primary-light: #3B82F6;
            --primary-bg: #E8EFF9;
            --primary-solid: #1A56DB;
            
            --success: #1A56DB;
            --success-dark: #1A3E8C;
            --success-light: #3B82F6;
            --success-bg: #E8EFF9;
            
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            
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
            
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 30px rgba(0,0,0,0.12);
            
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
            --table-hover: #F8FAFC;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-light: #60A5FA;
            --primary-bg: #1E3A5F;
            --primary-solid: #2563EB;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --purple-bg: #2D1B5F;
            --table-hover: #1E293B;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        /* ================================================================
           TOP NAV
           ================================================================ */
        .top-nav {
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(26, 86, 219, 0.12);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary-solid);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.02);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .top-nav .datetime i { color: var(--primary-light); }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .top-nav .avatar:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all 0.3s;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover {
            background: var(--bg-body);
            color: var(--primary);
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: var(--primary-solid);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(26, 86, 219, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -5%;
            width: 300px;
            height: 300px;
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
        }
        
        .page-header .role-badge-display {
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
        
        .page-header .header-badge {
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
        }
        
        .page-header .btn-outline-light {
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
            cursor: pointer;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================
           STATS CARDS - BLUE BACKGROUND
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        
        .stat-card {
            background: var(--primary-solid);
            border-radius: var(--radius);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(26, 86, 219, 0.25);
            border: none;
            cursor: default;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 32px rgba(26, 86, 219, 0.35);
        }
        
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            background: rgba(255,255,255,0.2);
            color: white;
            backdrop-filter: blur(4px);
        }
        
        .stat-card .stat-label {
            font-size: 0.65rem;
            color: rgba(255,255,255,0.75);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
        }
        
        .stat-card .stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: white;
            margin: 0;
            line-height: 1.2;
        }
        
        /* ================================================================
           STAFF TABLE - BLUE HEADER
           ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .table-container .table-header {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            background: var(--bg-body);
        }
        
        [data-theme="dark"] .table-container .table-header {
            background: var(--bg-card);
        }
        
        .table-container .table-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
        
        /* ================================================================
           TABLE WITH BLUE HEADER
           ================================================================ */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table thead {
            background: var(--primary-solid);
        }
        
        table thead th {
            padding: 14px 16px;
            text-align: left;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            border-bottom: none;
        }
        
        table thead th i {
            margin-right: 6px;
            opacity: 0.8;
        }
        
        table tbody td {
            padding: 12px 16px;
            font-size: 0.82rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        table tbody tr:hover {
            background: var(--table-hover);
        }
        
        table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Staff Avatar */
        .staff-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }
        
        .staff-avatar-placeholder {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-bg);
            color: var(--primary-solid);
            font-weight: 700;
            font-size: 0.8rem;
            border: 2px solid var(--border-color);
        }
        
        /* Role Badges */
        .badge-role {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .badge-role.admin { background: #DC2626; color: white; }
        .badge-role.doctor { background: #1A56DB; color: white; }
        .badge-role.reception { background: #7C3AED; color: white; }
        .badge-role.pharmacy { background: #D97706; color: white; }
        .badge-role.cashier { background: #059669; color: white; }
        .badge-role.laboratory { background: #0D9488; color: white; }
        
        /* Status Badges */
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .badge-status.active {
            background: #D1FAE5;
            color: #065F46;
        }
        
        .badge-status.inactive {
            background: #FEE2E2;
            color: #991B1B;
        }
        
        [data-theme="dark"] .badge-status.active {
            background: #064E3B;
            color: #34D399;
        }
        
        [data-theme="dark"] .badge-status.inactive {
            background: #7F1D1D;
            color: #FCA5A5;
        }
        
        /* ================================================================
           ACTION BUTTONS - ENHANCED
           ================================================================ */
        .actions-cell {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 6px 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 0.7rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            min-width: 32px;
            min-height: 32px;
        }
        
        .btn-action i {
            font-size: 0.8rem;
        }
        
        /* Edit Button */
        .btn-action.edit {
            background: var(--primary-bg);
            color: var(--primary-solid);
            border: 1.5px solid var(--primary-solid);
        }
        
        .btn-action.edit:hover {
            background: var(--primary-solid);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 86, 219, 0.3);
        }
        
        /* Activate Button */
        .btn-action.activate {
            background: #D1FAE5;
            color: #065F46;
            border: 1.5px solid #065F46;
        }
        
        .btn-action.activate:hover {
            background: #065F46;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        /* Deactivate Button */
        .btn-action.deactivate {
            background: #FEF3C7;
            color: #92400E;
            border: 1.5px solid #92400E;
        }
        
        .btn-action.deactivate:hover {
            background: #92400E;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
        }
        
        /* Delete Button */
        .btn-action.delete {
            background: #FEE2E2;
            color: #991B1B;
            border: 1.5px solid #991B1B;
        }
        
        .btn-action.delete:hover {
            background: #991B1B;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        
        /* ================================================================
           ADD STAFF BUTTON
           ================================================================ */
        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary-solid);
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-add:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 86, 219, 0.3);
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--text-secondary);
            opacity: 0.3;
            margin-bottom: 16px;
            display: block;
        }
        
        .empty-state h3 {
            font-size: 1.2rem;
            color: var(--text-primary);
            margin: 0 0 8px 0;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            margin: 0 0 20px 0;
            font-size: 0.9rem;
        }
        
        /* ================================================================
           ALERT
           ================================================================ */
        .alert-modern {
            padding: 14px 18px;
            border-radius: var(--radius);
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .alert-modern-success {
            background: var(--primary-bg);
            color: var(--primary-dark);
            border: 1px solid var(--primary-solid);
        }
        
        .alert-modern-error {
            background: var(--danger-bg);
            color: var(--danger-dark);
            border: 1px solid var(--danger);
        }
        
        .alert-modern i { font-size: 1.1rem; margin-top: 2px; }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary-solid);
            font-weight: 500;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            
            .stats-grid { grid-template-columns: 1fr 1fr; }
            
            .table-container .table-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            /* Mobile table */
            table thead { display: none; }
            table tbody td {
                display: block;
                padding: 8px 12px;
                border-bottom: none;
            }
            table tbody tr {
                display: block;
                border-bottom: 2px solid var(--border-color);
                padding: 8px 0;
            }
            table tbody tr:last-child {
                border-bottom: none;
            }
            table tbody td:before {
                content: attr(data-label);
                font-weight: 600;
                display: inline-block;
                width: 100px;
                font-size: 0.7rem;
                text-transform: uppercase;
                color: var(--text-secondary);
            }
            table tbody td:not(:last-child) {
                border-bottom: 1px solid var(--border-color);
            }
            .actions-cell {
                justify-content: flex-start;
            }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        /* ================================================================
           SIDEBAR STYLES
           ================================================================ */
        .sidebar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            bottom: 0 !important;
            width: 270px !important;
            background: #0B4EA8 !important;
            color: white !important;
            z-index: 50 !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            transition: transform 0.3s ease-in-out !important;
            transform: translateX(0) !important;
            box-shadow: 4px 0 20px rgba(0,0,0,0.15) !important;
        }
        
        #sidebarOverlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 45;
            display: none;
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }
        
        #sidebarOverlay.active {
            display: block !important;
        }
        
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%) !important; }
            .sidebar.open { transform: translateX(0) !important; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SIDEBAR OVERLAY -->
<!-- ================================================================ -->
<div id="sidebarOverlay"></div>

<!-- ================================================================ -->
<!-- SIDEBAR -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    <div style="padding:18px 16px 14px;border-bottom:2px solid #0B3D8A;background:#0B4EA8;position:sticky;top:0;z-index:5;">
        <div style="display:flex;align-items:center;gap:12px;">
            <img src="<?= $logo_url ?>" alt="Braick Logo" style="width:42px;height:42px;border-radius:10px;object-fit:cover;background:white;padding:4px;border:2px solid rgba(255,255,255,0.1);"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p style="color:white;font-weight:700;font-size:0.95rem;line-height:1.2;margin:0;">Braick Dispensary</p>
                <p style="color:#9EC5FE;font-size:0.65rem;font-weight:500;margin:0;">Super Admin</p>
            </div>
        </div>
    </div>
    
    <div style="padding:10px 14px;border-bottom:2px solid #0B3D8A;background:#0B4EA8;">
        <select id="sidebarBranchSelector" onchange="switchBranch(this.value)" style="width:100%;padding:7px 10px;border-radius:8px;border:none;background:rgba(255,255,255,0.12);color:white;font-size:0.75rem;cursor:pointer;outline:none;transition:all 0.3s ease;appearance:none;-webkit-appearance:none;background-image:url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2212%22 height=%2212%22 viewBox=%220 0 12 12%22%3E%3Cpath fill=%22white%22 d=%22M6 8L1 3h10z%22/%3E%3C/svg%3E');background-repeat:no-repeat;background-position:right 10px center;">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php
            try {
                $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $sel = ($selected_branch_id == $row['id']) ? 'selected' : '';
                    echo '<option value="' . $row['id'] . '" ' . $sel . ' style="background:#0B4EA8;color:white;padding:8px;">🏥 ' . htmlspecialchars($row['name']) . '</option>';
                }
            } catch (Exception $e) {}
            ?>
        </select>
    </div>
    
    <nav style="padding:10px 8px 20px;">
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Main Menu</div>
        
        <a href="/dispensary_system/frontend/pages/admin/dashboard.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-home"></i> Dashboard
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/employees.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-users"></i> Employees
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/patients.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-user-injured"></i> Patients
        </a>
        
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Modules</div>
        
        <a href="/dispensary_system/frontend/pages/admin/doctors_list.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-user-md"></i> Doctors
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_pharmacy.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-prescription"></i> Pharmacy
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_reception.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-headset"></i> Reception
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_laboratory.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-flask"></i> Laboratory
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_cashier.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-cash-register"></i> Cashier
        </a>
        
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Management</div>
        
        <a href="/dispensary_system/frontend/pages/admin/branches.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-store-alt"></i> Branches
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/departments.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-building"></i> Departments
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/reports.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-chart-bar"></i> Reports
        </a>
        
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Account</div>
        
        <a href="/dispensary_system/frontend/pages/admin/profile.php" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-user-circle"></i> Profile
        </a>
        
        <a href="/dispensary_system/frontend/pages/logout.php" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;border-top:2px solid rgba(255,255,255,0.08);padding-top:10px;margin-top:6px;color:#FCA5A5;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</aside>

<!-- ================================================================ -->
<!-- TOP NAVIGATION WITH SEARCH -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn" style="background:transparent;border:none;cursor:pointer;color:var(--text-secondary);font-size:1.2rem;padding:8px;">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- ================================================================
             SEARCH BAR IN HEADER
             ================================================================ -->
        <form method="GET" action="" style="flex:1;max-width:500px;display:flex;align-items:center;">
            <input type="hidden" name="id" value="<?= $branch_id ?>">
            <div class="search-wrapper" style="flex:1;">
                <i class="fas fa-search text-gray-400 ml-3"></i>
                <input type="text" name="search" id="searchInput" 
                       placeholder="Search staff by name, email, role..." 
                       value="<?= htmlspecialchars($search_term) ?>"
                       style="border:none;background:transparent;padding:8px 14px;width:100%;font-size:0.85rem;outline:none;color:var(--text-primary);">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search mr-1"></i> Search
                </button>
            </div>
        </form>
    </div>
    
    <div class="flex items-center gap-3">
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
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= substr($user_name, 0, 1) ?>%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-users-cog"></i>
                Staff - <?= htmlspecialchars($branch['name']) ?>
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user-md"></i>
                Manage staff members for <?= htmlspecialchars($branch['name']) ?> branch
                <span class="header-badge">
                    <i class="fas fa-store"></i> Branch #<?= $branch_id ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($branch['location'] ?? 'N/A') ?>
                </span>
                <?php if (!empty($search_term)): ?>
                    <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                        <i class="fas fa-search"></i> Results for "<?= htmlspecialchars($search_term) ?>"
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="branches.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Branches
            </a>
            <a href="add_staff.php?branch_id=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-user-plus"></i> Add Staff
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert-modern alert-modern-<?= $message_type === 'success' ? 'success' : 'error' ?>" style="max-width:1100px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS - BLUE BACKGROUND -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <p class="stat-label">Total Staff</p>
                <p class="stat-value"><?= $total_staff ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <p class="stat-label">Active</p>
                <p class="stat-value"><?= $active_staff ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div>
                <p class="stat-label">Inactive</p>
                <p class="stat-value"><?= $inactive_staff ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-user-md"></i>
            </div>
            <div>
                <p class="stat-label">Doctors</p>
                <p class="stat-value"><?= $role_count_map['doctor'] ?? 0 ?></p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STAFF TABLE WITH BLUE HEADER -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="table-header">
            <h3>
                <i class="fas fa-users"></i> 
                Staff Members (<?= $total_staff ?>)
                <?php if (!empty($search_term)): ?>
                    <span style="font-weight:400;font-size:0.8rem;color:var(--text-secondary);">
                        - filtered by "<?= htmlspecialchars($search_term) ?>"
                        <a href="?id=<?= $branch_id ?>" style="color:var(--primary-solid);text-decoration:none;margin-left:8px;">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </span>
                <?php endif; ?>
            </h3>
            <a href="add_staff.php?branch_id=<?= $branch_id ?>" class="btn-add">
                <i class="fas fa-user-plus"></i> Add Staff
            </a>
        </div>
        
        <?php if (count($staff_list) > 0): ?>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width:50px;"><i class="fas fa-hashtag"></i> #</th>
                            <th><i class="fas fa-user"></i> Staff</th>
                            <th><i class="fas fa-briefcase"></i> Role</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-phone"></i> Phone</th>
                            <th><i class="fas fa-circle"></i> Status</th>
                            <th style="text-align:center;"><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; foreach ($staff_list as $staff): 
                            $staff_id = $staff['id'] ?? 0;
                            $staff_name = htmlspecialchars($staff['full_name'] ?? 'Unknown');
                            $staff_role = $staff['role'] ?? 'unknown';
                            $staff_email = htmlspecialchars($staff['email'] ?? 'N/A');
                            $staff_phone = htmlspecialchars($staff['phone'] ?? 'N/A');
                            $staff_status = $staff['status'] ?? 'inactive';
                            $profile_pic = $staff['profile_pic'] ?? '';
                            
                            $role_badge_class = 'badge-role';
                            switch ($staff_role) {
                                case 'admin': $role_badge_class .= ' admin'; break;
                                case 'doctor': $role_badge_class .= ' doctor'; break;
                                case 'reception': $role_badge_class .= ' reception'; break;
                                case 'pharmacy': $role_badge_class .= ' pharmacy'; break;
                                case 'cashier': $role_badge_class .= ' cashier'; break;
                                case 'laboratory': $role_badge_class .= ' laboratory'; break;
                                default: $role_badge_class .= ' admin';
                            }
                            
                            $role_icons = [
                                'admin' => 'fa-user-tie',
                                'doctor' => 'fa-user-md',
                                'reception' => 'fa-headset',
                                'pharmacy' => 'fa-prescription-bottle',
                                'cashier' => 'fa-cash-register',
                                'laboratory' => 'fa-flask'
                            ];
                            $role_icon = $role_icons[$staff_role] ?? 'fa-user';
                        ?>
                            <tr>
                                <td data-label="#"><?= $counter++ ?></td>
                                <td data-label="Staff">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <?php if (!empty($profile_pic)): ?>
                                            <img src="/dispensary_system/frontend/assets/uploads/profiles/<?= $profile_pic ?>" 
                                                 alt="<?= $staff_name ?>" 
                                                 class="staff-avatar"
                                                 onerror="this.style.display='none'">
                                        <?php endif; ?>
                                        <?php if (empty($profile_pic)): ?>
                                            <div class="staff-avatar-placeholder"><?= substr($staff_name, 0, 1) ?></div>
                                        <?php endif; ?>
                                        <div>
                                            <div style="font-weight:600;color:var(--text-primary);"><?= $staff_name ?></div>
                                            <div style="font-size:0.65rem;color:var(--text-secondary);">ID: #<?= $staff_id ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Role">
                                    <span class="<?= $role_badge_class ?>">
                                        <i class="fas <?= $role_icon ?>"></i>
                                        <?= ucfirst($staff_role) ?>
                                    </span>
                                </td>
                                <td data-label="Email">
                                    <a href="mailto:<?= $staff_email ?>" style="color:var(--primary-solid);text-decoration:none;font-weight:500;">
                                        <?= $staff_email ?>
                                    </a>
                                </td>
                                <td data-label="Phone"><?= $staff_phone ?></td>
                                <td data-label="Status">
                                    <span class="badge-status <?= $staff_status === 'active' ? 'active' : 'inactive' ?>">
                                        <i class="fas fa-<?= $staff_status === 'active' ? 'circle' : 'times-circle' ?>"></i>
                                        <?= ucfirst($staff_status) ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <div class="actions-cell" style="justify-content:center;">
                                        <a href="edit_staff.php?id=<?= $staff_id ?>&branch_id=<?= $branch_id ?>" 
                                           class="btn-action edit" title="Edit Staff">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <?php if ($staff_status === 'active'): ?>
                                            <button onclick="toggleStaffStatus(<?= $staff_id ?>, 'inactive')" 
                                                    class="btn-action deactivate" title="Deactivate Staff">
                                                <i class="fas fa-pause"></i> Deactivate
                                            </button>
                                        <?php else: ?>
                                            <button onclick="toggleStaffStatus(<?= $staff_id ?>, 'active')" 
                                                    class="btn-action activate" title="Activate Staff">
                                                <i class="fas fa-play"></i> Activate
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="deleteStaff(<?= $staff_id ?>, '<?= addslashes($staff_name) ?>')" 
                                                class="btn-action delete" title="Delete Staff">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-slash"></i>
                <h3>No Staff Found</h3>
                <p><?= !empty($search_term) ? 'No staff members match your search criteria.' : 'This branch currently has no staff members assigned.' ?></p>
                <?php if (!empty($search_term)): ?>
                    <a href="?id=<?= $branch_id ?>" class="btn-add" style="display:inline-flex;">
                        <i class="fas fa-times"></i> Clear Search
                    </a>
                <?php else: ?>
                    <a href="add_staff.php?branch_id=<?= $branch_id ?>" class="btn-add" style="display:inline-flex;">
                        <i class="fas fa-user-plus"></i> Add First Staff
                    </a>
                <?php endif; ?>
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
            Staff Management - <?= htmlspecialchars($branch['name']) ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- FORMS FOR ACTIONS -->
<!-- ================================================================ -->
<form id="toggleStatusForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="staff_id" id="toggleStaffId" value="0">
    <input type="hidden" name="status" id="toggleStaffStatus" value="">
</form>

<form id="deleteStaffForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="action" value="delete_staff">
    <input type="hidden" name="staff_id" id="deleteStaffId" value="0">
</form>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('sidebar');
        var sidebarToggle = document.getElementById('sidebarToggle');
        var overlay = document.getElementById('sidebarOverlay');
        
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'sidebarOverlay';
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:45;display:none;backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);';
            document.body.appendChild(overlay);
        }
        
        function toggleSidebar() {
            var isOpen = sidebar.classList.contains('open');
            if (isOpen) {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                document.body.style.overflow = '';
            } else {
                sidebar.classList.add('open');
                overlay.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }
        
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleSidebar();
            });
        }
        
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
        
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
    });

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
    // TOGGLE STAFF STATUS
    // ================================================================
    function toggleStaffStatus(staffId, status) {
        var action = status === 'active' ? 'activate' : 'deactivate';
        if (confirm('Are you sure you want to ' + action + ' this staff member?')) {
            document.getElementById('toggleStaffId').value = staffId;
            document.getElementById('toggleStaffStatus').value = status;
            document.getElementById('toggleStatusForm').submit();
        }
    }

    // ================================================================
    // DELETE STAFF
    // ================================================================
    function deleteStaff(staffId, staffName) {
        if (confirm('Are you sure you want to delete staff member: "' + staffName + '"? This action cannot be undone.')) {
            document.getElementById('deleteStaffId').value = staffId;
            document.getElementById('deleteStaffForm').submit();
        }
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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);
</script>

</body>
</html>