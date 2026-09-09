<?php
// ================================================================
// FILE: frontend/pages/admin/purchase_history.php
// ADMIN - PURCHASE HISTORY WITH VIEW, EDIT, DELETE
// DELETE removes from purchases/purchase_items ONLY
// Inventory stock REMAINS (does not delete stock)
// WITH SCROLL BUTTONS AND PDF IN NEW PAGE
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
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK USER ACCESS (Admin only)
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'admin';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_is_online = $_SESSION['is_online'] ?? 1;

// ================================================================
// GET SELECTED BRANCH FROM URL
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';
if ($selected_branch_id === 'all') {
    $branch_id_for_query = $user_branch_id;
} else {
    $branch_id_for_query = (int)$selected_branch_id;
}

// ================================================================
// GET ACTION AND ID FROM URL
// ================================================================
$action = isset($_GET['action']) ? $_GET['action'] : '';
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$delete_id = isset($_GET['delete']) ? (int)$_GET['delete'] : 0;

// ================================================================
// DATABASE CONNECTION
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// MONEY FORMAT FUNCTIONS
// ================================================================
function formatMoney($amount) {
    if ($amount === null || $amount === '') {
        return '0.00';
    }
    return number_format((float)$amount, 2, '.', ',');
}

function formatMoneyNoDecimal($amount) {
    if ($amount === null || $amount === '') {
        return '0';
    }
    return number_format((float)$amount, 0, '.', ',');
}

function formatMoneyShort($amount) {
    if ($amount === null || $amount === '') {
        return '0';
    }
    $amount = (float)$amount;
    if ($amount >= 1000000000) {
        return number_format($amount / 1000000000, 1) . 'B';
    }
    if ($amount >= 1000000) {
        return number_format($amount / 1000000, 1) . 'M';
    }
    if ($amount >= 1000) {
        return number_format($amount / 1000, 1) . 'K';
    }
    return number_format($amount, 0);
}

function cleanMoney($value) {
    return str_replace(',', '', $value);
}

function getMoney($value) {
    $clean = cleanMoney($value);
    return floatval($clean);
}

// ================================================================
// PROCESS POST REQUESTS
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_post = $_POST['action'] ?? '';
    
    // ================================================================
    // DELETE PURCHASE - ONLY FROM PURCHASES/PURCHASE_ITEMS
    // INVENTORY STOCK REMAINS UNCHANGED
    // ================================================================
    if ($action_post === 'delete_purchase') {
        $purchase_id = (int)($_POST['purchase_id'] ?? 0);
        $confirmed = isset($_POST['confirmed']) ? (int)$_POST['confirmed'] : 0;
        
        if ($confirmed == 1 && $purchase_id > 0) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("SELECT id, invoice_number, status FROM purchases WHERE id = ?");
                $stmt->execute([$purchase_id]);
                $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$purchase) {
                    $message = "❌ Purchase not found.";
                    $message_type = 'error';
                } else {
                    // Delete purchase items first
                    $stmt = $db->prepare("DELETE FROM purchase_items WHERE purchase_id = ?");
                    $stmt->execute([$purchase_id]);
                    
                    // Delete purchase
                    $stmt = $db->prepare("DELETE FROM purchases WHERE id = ?");
                    $stmt->execute([$purchase_id]);
                    
                    $db->commit();
                    
                    $message = "✅ Purchase <strong>{$purchase['invoice_number']}</strong> deleted successfully! (Inventory stock unaffected)";
                    $message_type = 'success';
                    $_SESSION['purchase_history_message'] = $message;
                    $_SESSION['purchase_history_message_type'] = $message_type;
                    header('Location: purchase_history.php?branch=' . $selected_branch_id);
                    exit;
                }
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = "❌ Deletion not confirmed";
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // EDIT PURCHASE - ONLY FOR IN_PROGRESS
    // ================================================================
    if ($action_post === 'edit_purchase') {
        $purchase_id = (int)($_POST['purchase_id'] ?? 0);
        $purchase_type = $_POST['purchase_type'] ?? 'medicine';
        
        header('Location: purchases.php?id=' . $purchase_id . '&type=' . $purchase_type . '&branch=' . $selected_branch_id);
        exit;
    }
}

// ================================================================
// CHECK SESSION MESSAGES
// ================================================================
if (isset($_SESSION['purchase_history_message'])) {
    $message = $_SESSION['purchase_history_message'];
    $message_type = $_SESSION['purchase_history_message_type'] ?? 'success';
    unset($_SESSION['purchase_history_message']);
    unset($_SESSION['purchase_history_message_type']);
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ================================================================
// BUILD QUERY
// ================================================================
$query = "
    SELECT 
        p.*,
        u.full_name as creator_name,
        a.full_name as cancelled_by_name,
        (SELECT COUNT(*) FROM purchase_items WHERE purchase_id = p.id) as items_count,
        (SELECT SUM(quantity) FROM purchase_items WHERE purchase_id = p.id) as total_qty
    FROM purchases p
    LEFT JOIN users u ON p.created_by = u.id
    LEFT JOIN users a ON p.cancelled_by = a.id
    WHERE 1=1
";

$params = [];

if ($status_filter !== 'all') {
    $query .= " AND p.status = ?";
    $params[] = $status_filter;
}

if ($type_filter !== 'all') {
    $query .= " AND p.purchase_type = ?";
    $params[] = $type_filter;
}

if (!empty($search)) {
    $query .= " AND (p.invoice_number LIKE ? OR p.created_by_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($date_from)) {
    $query .= " AND DATE(p.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(p.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET SINGLE PURCHASE FOR VIEW
// ================================================================
$view_purchase = null;
$view_items = [];
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG';

if ($view_id > 0) {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as creator_name, a.full_name as cancelled_by_name
        FROM purchases p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN users a ON p.cancelled_by = a.id
        WHERE p.id = ?
    ");
    $stmt->execute([$view_id]);
    $view_purchase = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($view_purchase) {
        $stmt = $db->prepare("
            SELECT 
                pi.*,
                CASE 
                    WHEN pi.item_type = 'medicine' THEN mi.medication_name 
                    WHEN pi.item_type = 'equipment' THEN eq.equipment_name 
                END as item_name,
                CASE 
                    WHEN pi.item_type = 'medicine' THEN mi.category 
                    WHEN pi.item_type = 'equipment' THEN eq.category 
                END as category,
                CASE 
                    WHEN pi.item_type = 'medicine' THEN mi.unit 
                    WHEN pi.item_type = 'equipment' THEN eq.unit 
                END as unit,
                CASE 
                    WHEN pi.item_type = 'medicine' THEN mi.batch_number 
                    WHEN pi.item_type = 'equipment' THEN eq.batch_number 
                END as batch_number,
                u.full_name as added_by_full_name
            FROM purchase_items pi
            LEFT JOIN medications_inventory mi ON pi.item_id = mi.id AND pi.item_type = 'medicine'
            LEFT JOIN medical_equipment eq ON pi.item_id = eq.id AND pi.item_type = 'equipment'
            LEFT JOIN users u ON pi.added_by = u.id
            WHERE pi.purchase_id = ?
            ORDER BY pi.added_at DESC
        ");
        $stmt->execute([$view_id]);
        $view_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ================================================================
// GET STATISTICS
// ================================================================

$stmt = $db->query("SELECT COUNT(*) as count FROM purchases");
$total_purchases = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as count FROM purchases WHERE status = 'COMPLETED'");
$completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as count FROM purchases WHERE status = 'IN_PROGRESS'");
$in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->query("SELECT COALESCE(SUM(total_buying_cost), 0) as total FROM purchases WHERE status = 'COMPLETED'");
$total_spending = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->query("SELECT COALESCE(SUM(total_selling_value), 0) as total FROM purchases WHERE status = 'COMPLETED'");
$total_selling = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$total_profit = $total_selling - $total_spending;

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
// SIDEBAR STATISTICS
// ================================================================
$total_employees_sidebar = 0;
$total_doctors_sidebar = 0;
$total_branches_sidebar = 0;
$module_counts = ['pharmacy' => 0, 'reception' => 0, 'laboratory' => 0, 'cashier' => 0];
$total_patients_sidebar = 0;
$today_patients_sidebar = 0;
$total_services_sidebar = 0;
$today_services_sidebar = 0;
$pending_prescriptions_sidebar = 0;
$pending_lab_tests_sidebar = 0;

try {
    $stats_branch = $selected_branch_id;
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'active'");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'active' AND branch_id = ?");
        $stmt->execute([(int)$stats_branch]);
    }
    $total_employees_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ?");
        $stmt->execute([(int)$stats_branch]);
    }
    $total_doctors_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $modules = ['pharmacy', 'reception', 'laboratory', 'cashier'];
    foreach ($modules as $module) {
        if ($stats_branch === 'all') {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = ? AND status = 'active'");
            $stmt->execute([$module]);
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = ? AND status = 'active' AND branch_id = ?");
            $stmt->execute([$module, (int)$stats_branch]);
        }
        $module_counts[$module] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM patients");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
        $stmt->execute([(int)$stats_branch]);
    }
    $total_patients_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = CURDATE()");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([(int)$stats_branch]);
    }
    $today_patients_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM bill_items WHERE status != 'cancelled'");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bill_items WHERE branch_id = ? AND status != 'cancelled'");
        $stmt->execute([(int)$stats_branch]);
    }
    $total_services_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM bill_items WHERE status != 'cancelled' AND DATE(created_at) = CURDATE()");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bill_items WHERE branch_id = ? AND status != 'cancelled' AND DATE(created_at) = CURDATE()");
        $stmt->execute([(int)$stats_branch]);
    }
    $today_services_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status IN ('pending', 'confirmed')");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status IN ('pending', 'confirmed')");
        $stmt->execute([(int)$stats_branch]);
    }
    $pending_prescriptions_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status IN ('pending', 'in_progress')");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status IN ('pending', 'in_progress')");
        $stmt->execute([(int)$stats_branch]);
    }
    $pending_lab_tests_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
    $total_branches_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
} catch (Exception $e) {
    // ignore
}

// ================================================================
// PROFILE & LOGO
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG';

// Display branch name
$display_branch_name = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    foreach ($branches as $b) {
        if ($b['id'] == $selected_branch_id) {
            $display_branch_name = $b['name'];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase History - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= $logo_path ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================ */
        /* ROOT VARIABLES */
        /* ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
            --success-light: #D1FAE5;
            --warning: #D97706;
            --warning-light: #FEF3C7;
            --danger: #DC2626;
            --danger-light: #FEE2E2;
            --purple: #7C3AED;
            --teal: #0D9488;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --border-color: #E2E8F0;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-muted: #94A3B8;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
            --radius: 12px;
            --radius-lg: 16px;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --border-color: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.4);
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
        
        /* ================================================================ */
        /* SIDEBAR */
        /* ================================================================ */
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: 270px; 
            background: linear-gradient(180deg, #0B4EA8 0%, #0A3D7A 100%);
            color: white; z-index: 50; overflow-y: auto; overflow-x: hidden;
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(0);
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
            scroll-behavior: smooth;
        }
        
        [data-theme="dark"] .sidebar {
            background: linear-gradient(180deg, #0A3D7A 0%, #082F5E 100%);
        }
        
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
        .sidebar::-webkit-scrollbar-thumb { background: #0AA84F; border-radius: 10px; }
        
        .sidebar-brand {
            padding: 18px 16px 14px;
            border-bottom: 2px solid rgba(255,255,255,0.08);
            background: rgba(0,0,0,0.1);
            position: sticky; top: 0; z-index: 5;
            backdrop-filter: blur(10px);
        }
        
        .sidebar-brand .logo {
            width: 42px; height: 42px; border-radius: 10px;
            object-fit: cover; background: white; padding: 4px;
            border: 2px solid rgba(255,255,255,0.15);
            transition: transform 0.3s ease;
        }
        
        .sidebar-brand .logo:hover { transform: rotate(-5deg) scale(1.05); }
        
        .sidebar-brand .brand-text { color: white; font-weight: 700; font-size: 0.95rem; line-height: 1.2; letter-spacing: 0.5px; }
        .sidebar-brand .brand-sub { color: #9EC5FE; font-size: 0.65rem; font-weight: 500; letter-spacing: 0.3px; }
        
        .sidebar-branch-selector {
            padding: 10px 14px;
            border-bottom: 2px solid rgba(255,255,255,0.06);
            background: rgba(0,0,0,0.05);
        }
        
        .sidebar-branch-selector select {
            width: 100%;
            padding: 7px 10px;
            border-radius: 8px;
            border: none;
            background: rgba(255,255,255,0.12);
            color: white;
            font-size: 0.75rem;
            cursor: pointer;
            outline: none;
            transition: all 0.3s ease;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='white' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
        }
        
        .sidebar-branch-selector select:hover {
            background-color: rgba(255,255,255,0.2);
        }
        
        .sidebar-branch-selector select:focus {
            box-shadow: 0 0 0 2px rgba(10, 168, 79, 0.5);
        }
        
        .sidebar-branch-selector select option {
            background: #0B4EA8;
            color: white;
            padding: 8px;
        }
        
        .sidebar-nav { padding: 10px 8px 20px; }
        
        .sidebar-nav .nav-label {
            font-size: 0.5rem; text-transform: uppercase;
            letter-spacing: 0.08em; color: #6EA8FE;
            padding: 8px 10px 4px; margin: 8px 0 2px;
            font-weight: 700; opacity: 0.8;
        }
        
        .sidebar-nav .nav-label .label-icon { margin-right: 4px; }
        
        .sidebar-link {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 12px; border-radius: 8px;
            color: #D2E3FC; text-decoration: none;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.8rem; font-weight: 500;
            margin: 1px 0; background: transparent;
            cursor: pointer; border: none; width: 100%;
            text-align: left; position: relative;
        }
        
        .sidebar-link:hover {
            background: rgba(10, 168, 79, 0.4);
            color: white; box-shadow: 0 4px 12px rgba(10, 168, 79, 0.2);
            transform: translateX(4px);
        }
        
        .sidebar-link.active {
            background: rgba(10, 168, 79, 0.5);
            color: white; box-shadow: 0 4px 12px rgba(10, 168, 79, 0.3);
        }
        
        .sidebar-link.active::before {
            content: ''; position: absolute; left: 0; top: 15%; bottom: 15%;
            width: 4px; background: #0AA84F;
            border-radius: 0 4px 4px 0;
            box-shadow: 0 0 12px rgba(10, 168, 79, 0.5);
        }
        
        .sidebar-link i { width: 20px; text-align: center; font-size: 0.9rem; flex-shrink: 0; opacity: 0.8; }
        .sidebar-link:hover i, .sidebar-link.active i { opacity: 1; }
        .sidebar-link .link-text { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        .sidebar-link .badge {
            margin-left: auto; background: rgba(255,255,255,0.12);
            padding: 1px 8px; border-radius: 20px;
            font-size: 0.6rem; font-weight: 600; color: white;
            transition: all 0.3s ease; flex-shrink: 0; min-width: 20px;
            text-align: center; border: 1px solid rgba(255,255,255,0.05);
        }
        
        .sidebar-link .badge.danger { background: #EF4444; animation: pulse-badge 2s infinite; border-color: #EF4444; }
        .sidebar-link .badge.success { background: #059669; border-color: #059669; }
        
        .sidebar-link.logout-link {
            border-top: 2px solid rgba(255,255,255,0.06);
            padding-top: 10px; margin-top: 4px; color: #FCA5A5;
        }
        
        .sidebar-link.logout-link:hover { background: #DC2626; color: white; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4); transform: translateX(4px); }
        
        .sidebar-status {
            padding: 10px 16px; border-top: 2px solid rgba(255,255,255,0.06);
            display: flex; align-items: center; gap: 10px;
            background: rgba(0,0,0,0.1); position: sticky; bottom: 0;
            backdrop-filter: blur(10px);
        }
        
        .sidebar-status .status-dot {
            width: 8px; height: 8px; border-radius: 50%; display: inline-block;
            transition: all 0.3s ease;
        }
        
        .sidebar-status .status-dot.online {
            background: #34D399; box-shadow: 0 0 8px rgba(52, 211, 153, 0.3);
            animation: pulse-dot 1.5s infinite;
        }
        
        .sidebar-status .status-dot.offline {
            background: #94A3B8;
        }
        
        .sidebar-status .status-text { font-size: 0.65rem; color: #D2E3FC; font-weight: 500; }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.8); }
        }
        
        @keyframes pulse-badge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }
        
        #sidebarOverlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 45;
            display: none; backdrop-filter: blur(4px);
        }
        
        #sidebarOverlay.active { display: block !important; }
        
        @media (min-width: 1025px) {
            .sidebar { transform: translateX(0) !important; z-index: 50; }
            #sidebarOverlay { display: none !important; }
        }
        
        @media (max-width: 1024px) {
            .sidebar { width: 280px; transform: translateX(-100%); z-index: 9999; border-radius: 0 12px 12px 0; }
            .sidebar.open { transform: translateX(0) !important; }
            #sidebarOverlay { display: none; z-index: 9998; }
            #sidebarOverlay.active { display: block !important; }
        }
        
        /* ================================================================ */
        /* HEADER & MAIN CONTENT */
        /* ================================================================ */
        .embedded-header {
            position: fixed; top: 0; left: 270px; right: 0;
            height: 68px; background: var(--bg-nav); z-index: 40;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease; backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }
        
        .embedded-header .search-wrapper {
            display: flex; align-items: center; background: var(--bg-body);
            border-radius: var(--radius); border: 2px solid var(--border-color);
            transition: all 0.3s; flex: 1; max-width: 500px;
        }
        
        .embedded-header .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
        }
        
        .embedded-header .search-wrapper input {
            border: none; background: transparent; padding: 8px 14px;
            width: 100%; font-size: 0.85rem; outline: none;
            color: var(--text-primary);
        }
        
        .embedded-header .search-wrapper input::placeholder { color: var(--text-secondary); }
        
        .embedded-header .search-wrapper .search-btn {
            background: var(--primary); color: white; border: none;
            padding: 8px 16px; border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer; font-size: 0.85rem; transition: all 0.3s;
            white-space: nowrap;
        }
        
        .embedded-header .search-wrapper .search-btn:hover {
            background: var(--primary-dark); transform: scale(1.02);
        }
        
        .embedded-header .datetime { font-size: 0.78rem; color: var(--text-secondary); font-weight: 500; display: flex; align-items: center; gap: 6px; }
        .embedded-header .datetime i { color: var(--success); }
        
        .embedded-header .avatar {
            width: 40px; height: 40px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--border-color);
            cursor: pointer; transition: all 0.3s;
        }
        
        .embedded-header .avatar:hover { border-color: var(--primary); transform: scale(1.05); }
        
        .embedded-header .icon-btn {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-secondary); transition: all 0.3s;
            background: transparent; border: none; cursor: pointer;
            position: relative;
        }
        
        .embedded-header .icon-btn:hover { background: var(--bg-body); color: var(--primary); }
        
        .embedded-header .notif-dot {
            position: absolute; top: 6px; right: 6px;
            width: 8px; height: 8px; border-radius: 50%;
            border: 2px solid var(--bg-nav); animation: pulse-dot 2s infinite;
        }
        
        .embedded-header .notif-dot.has-notif { background: var(--danger); }
        .embedded-header .notif-dot.no-notif { background: var(--text-muted); animation: none; }
        
        .embedded-header .dark-toggle-btn {
            background: var(--bg-body); border: 2px solid var(--border-color);
            border-radius: var(--radius); padding: 6px 12px;
            cursor: pointer; font-size: 0.82rem; color: var(--text-primary);
            transition: all 0.3s; display: flex; align-items: center; gap: 6px;
        }
        
        .embedded-header .dark-toggle-btn:hover { border-color: var(--primary); background: var(--bg-card); }
        .embedded-header .dark-toggle-btn i { font-size: 0.9rem; }
        
        .embedded-header .branch-selector {
            background: var(--bg-body); border: 2px solid var(--border-color);
            border-radius: var(--radius); padding: 6px 12px;
            font-size: 0.78rem; color: var(--text-primary);
            outline: none; cursor: pointer; transition: all 0.3s;
        }
        
        .embedded-header .branch-selector:focus { border-color: var(--primary); }
        
        .main-content {
            margin-left: 270px; margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================ */
        /* PAGE HEADER BOX */
        /* ================================================================ */
        .page-header-box {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
            padding: 18px 24px;
            margin-bottom: 20px;
            box-shadow: 0 6px 24px rgba(11, 94, 215, 0.2);
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .page-header-box::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 350px;
            height: 350px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header-box .page-title {
            color: white;
            font-size: 1.4rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header-box .page-title .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header-box .page-title .branch-name-display {
            background: rgba(255,255,255,0.15);
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            color: white;
        }
        
        .header-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        
        .btn-back {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 6px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.75rem;
            border: 1px solid rgba(255,255,255,0.15);
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        /* ================================================================ */
        /* STATS CARDS */
        /* ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            border-radius: 12px;
            padding: 14px 16px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: block;
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            min-height: 80px;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card .stat-number { font-size: 1.4rem; font-weight: 700; line-height: 1.2; }
        .stat-card .stat-label { font-size: 0.6rem; color: rgba(255,255,255,0.85); font-weight: 500; margin-top: 2px; }
        .stat-card .stat-icon { font-size: 1.2rem; opacity: 0.8; float: right; }
        .stat-card .stat-sub { font-size: 0.55rem; color: rgba(255,255,255,0.5); }
        
        .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
        .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
        .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .stat-card.teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
        
        /* ================================================================ */
        /* CARD */
        /* ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 18px 22px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-title .title-blue { color: var(--primary); }
        .card-title .title-green { color: var(--success); }
        
        .result-count {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .result-count strong { color: var(--primary); }
        
        /* ================================================================ */
        /* SCROLL BUTTONS */
        /* ================================================================ */
        .scroll-arrows {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .scroll-arrow-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }
        
        .scroll-arrow-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
            transform: scale(1.05);
        }
        
        .table-wrapper {
            position: relative;
        }
        
        .table-scroll-container {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 500px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-scroll-container::-webkit-scrollbar {
            height: 6px;
            width: 5px;
        }
        
        .table-scroll-container::-webkit-scrollbar-track {
            background: var(--bg-body);
            border-radius: 4px;
        }
        
        .table-scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        /* ================================================================ */
        /* FILTERS */
        /* ================================================================ */
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 14px;
        }
        
        .filter-btn {
            padding: 4px 14px;
            border-radius: 16px;
            font-size: 0.7rem;
            font-weight: 600;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .filter-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .filter-btn.clear-filter {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        .filter-btn.clear-filter:hover {
            background: var(--danger);
            color: white;
        }
        
        .search-form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-form input[type="text"],
        .search-form select,
        .search-form input[type="date"] {
            padding: 6px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
            flex: 1;
            min-width: 100px;
        }
        
        .search-form input:focus,
        .search-form select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .btn-search {
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            background: var(--primary);
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-reset {
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-reset:hover {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        /* ================================================================ */
        /* TABLE */
        /* ================================================================ */
        .data-table {
            width: 100%;
            min-width: 1100px;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.78rem;
        }
        
        .data-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--primary);
            color: white;
            padding: 8px 12px;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            white-space: nowrap;
            text-align: left;
        }
        
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        
        .data-table tbody tr:nth-child(even) {
            background: var(--primary-light);
        }
        
        .data-table tbody tr:hover td {
            background: var(--success-light);
        }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) {
            background: #1E293B;
        }
        
        [data-theme="dark"] .data-table tbody tr:hover td {
            background: #1A3A2A;
        }
        
        .data-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            white-space: nowrap;
        }
        
        .col-sno { width: 35px; text-align: center; }
        .col-invoice { min-width: 140px; }
        .col-type { min-width: 80px; }
        .col-creator { min-width: 120px; }
        .col-items { min-width: 60px; text-align: center; }
        .col-qty { min-width: 60px; text-align: center; }
        .col-buying { min-width: 120px; }
        .col-selling { min-width: 120px; }
        .col-profit { min-width: 120px; }
        .col-status { min-width: 90px; text-align: center; }
        .col-date { min-width: 140px; }
        .col-actions { min-width: 160px; text-align: center; }
        
        /* ================================================================ */
        /* BADGES */
        /* ================================================================ */
        .status-badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .status-badge.completed {
            background: var(--success-light);
            color: var(--success);
        }
        
        .status-badge.in-progress {
            background: var(--warning-light);
            color: var(--warning);
        }
        
        .status-badge.cancelled {
            background: var(--danger-light);
            color: var(--danger);
        }
        
        .type-badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.55rem;
            font-weight: 600;
        }
        
        .type-badge.medicine {
            background: var(--primary-light);
            color: var(--primary);
        }
        
        .type-badge.equipment {
            background: var(--purple-light);
            color: var(--purple);
        }
        
        .profit-positive {
            color: var(--success);
        }
        
        .profit-negative {
            color: var(--danger);
        }
        
        /* ================================================================ */
        /* ACTION BUTTONS */
        /* ================================================================ */
        .action-group {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .action-btn {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            color: white;
            height: 26px;
            white-space: nowrap;
        }
        
        .action-btn.view {
            background: var(--primary);
        }
        .action-btn.view:hover {
            background: var(--primary-dark);
            transform: scale(1.03);
        }
        
        .action-btn.edit {
            background: var(--warning);
        }
        .action-btn.edit:hover {
            background: #B45309;
            transform: scale(1.03);
        }
        
        .action-btn.delete {
            background: var(--danger);
        }
        .action-btn.delete:hover {
            background: #991B1B;
            transform: scale(1.03);
        }
        
        .action-btn.pdf {
            background: #DC2626;
        }
        .action-btn.pdf:hover {
            background: #991B1B;
            transform: scale(1.03);
        }
        
        .action-btn i { font-size: 0.5rem; }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 2.5rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 10px;
        }
        
        .empty-state .sub {
            font-size: 0.8rem;
            margin-top: 4px;
        }
        
        .footer {
            padding: 12px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ================================================================ */
        /* VIEW MODAL */
        /* ================================================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: 12px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 24px 28px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 2px solid var(--border-color);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 16px;
        }
        
        .modal-header .modal-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }
        
        .modal-close:hover {
            color: var(--danger);
            transform: rotate(90deg);
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            padding-top: 14px;
            border-top: 2px solid var(--border-color);
            margin-top: 16px;
            flex-wrap: wrap;
        }
        
        .btn-close-modal {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-close-modal:hover {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        .btn-print-invoice {
            background: #DC2626;
            color: white;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-print-invoice:hover {
            background: #991B1B;
            transform: translateY(-2px);
        }
        
        .view-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 14px;
        }
        
        .view-item {
            padding: 8px 12px;
            background: var(--bg-body);
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }
        
        .view-item .label {
            font-size: 0.55rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        
        .view-item .value {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 2px;
        }
        
        .view-item.full-width { grid-column: 1 / -1; }
        
        .view-items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            margin-top: 10px;
        }
        
        .view-items-table thead th {
            background: var(--primary);
            color: white;
            padding: 6px 10px;
            font-size: 0.6rem;
            text-transform: uppercase;
            font-weight: 700;
            text-align: left;
        }
        
        .view-items-table tbody td {
            padding: 5px 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .view-items-table tbody tr:nth-child(even) {
            background: var(--primary-light);
        }
        
        [data-theme="dark"] .view-items-table tbody tr:nth-child(even) {
            background: #1E293B;
        }
        
        /* ================================================================ */
        /* DELETE MODAL */
        /* ================================================================ */
        .delete-warning-icon {
            text-align: center;
            font-size: 3rem;
            color: var(--danger);
            margin-bottom: 10px;
        }
        
        .delete-modal-content {
            background: var(--bg-card);
            border-radius: 12px;
            max-width: 500px;
            width: 100%;
            padding: 24px 28px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 2px solid var(--border-color);
        }
        
        .delete-modal-content .modal-header .modal-title {
            color: var(--danger);
        }
        
        .btn-confirm-delete {
            background: var(--danger);
            color: white;
            padding: 10px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
        }
        
        .btn-confirm-delete:hover {
            background: #991B1B;
            transform: translateY(-2px);
        }
        
        .delete-reason-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            resize: vertical;
            min-height: 60px;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }
        
        .delete-reason-textarea:focus {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
            outline: none;
        }
        
        .delete-note {
            background: var(--warning-light);
            padding: 10px 14px;
            border-radius: 8px;
            border: 2px solid var(--warning);
            margin-bottom: 14px;
        }
        
        .delete-note p {
            font-size: 0.8rem;
            color: var(--warning);
            font-weight: 600;
            margin: 0;
        }
        
        .delete-note p i { margin-right: 4px; }
        
        /* ================================================================ */
        /* PDF BUTTON STYLES */
        /* ================================================================ */
        .btn-pdf-new-window {
            background: #DC2626;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            height: 26px;
            white-space: nowrap;
        }
        
        .btn-pdf-new-window:hover {
            background: #991B1B;
            transform: scale(1.03);
        }
        
        .btn-pdf-new-window i { font-size: 0.5rem; }
        
        /* ================================================================ */
        /* RESPONSIVE */
        /* ================================================================ */
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .search-form { flex-direction: column; align-items: stretch; }
            .search-form input, .search-form select { min-width: 100%; }
            .filter-group { justify-content: center; }
            .card { padding: 12px 14px; }
            .page-header-box .page-title { font-size: 1.1rem; }
            .stat-card .stat-number { font-size: 1.1rem; }
            .stat-card { padding: 10px 12px; min-height: 65px; }
            .header-actions { flex-direction: column; align-items: stretch; width: 100%; }
            .header-actions .btn-back { width: 100%; justify-content: center; }
            .data-table { min-width: 750px; font-size: 0.65rem; }
            .data-table th, .data-table td { padding: 4px 6px; }
            .col-profit { min-width: 90px; }
            .col-actions { min-width: 120px; }
            .view-grid { grid-template-columns: 1fr; }
            .modal-content { padding: 16px; }
            .delete-modal-content { padding: 16px; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card .stat-number { font-size: 0.9rem; }
            .stat-card { padding: 8px 10px; min-height: 55px; }
            .data-table { min-width: 650px; font-size: 0.6rem; }
            .data-table th, .data-table td { padding: 3px 5px; }
            .col-actions { min-width: 100px; }
            .action-btn { font-size: 0.5rem; padding: 2px 5px; height: 22px; }
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
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_path ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">👑 Super Admin</p>
            </div>
        </div>
    </div>
    
    <div class="sidebar-branch-selector">
        <select id="sidebarBranchSelector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-label"><span class="label-icon">📋</span> Main Menu</div>
        <a href="dashboard.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-home"></i> <span class="link-text">Dashboard</span>
        </a>
        <a href="employees.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-users"></i> <span class="link-text">Employees</span>
            <span class="badge"><?= $total_employees_sidebar ?></span>
        </a>
        <a href="patients.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-user-injured"></i> <span class="link-text">Patients</span>
            <span class="badge"><?= $total_patients_sidebar ?></span>
            <?php if ($today_patients_sidebar > 0): ?>
                <span class="badge success">+<?= $today_patients_sidebar ?></span>
            <?php endif; ?>
        </a>
        
        <div class="nav-label"><span class="label-icon">⚙️</span> Modules</div>
        <a href="doctors_list.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-user-md"></i> <span class="link-text">Doctors</span>
            <span class="badge"><?= $total_doctors_sidebar ?></span>
        </a>
        <a href="view_pharmacy.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-prescription"></i> <span class="link-text">Pharmacy</span>
            <span class="badge"><?= $module_counts['pharmacy'] ?? 0 ?></span>
            <?php if ($pending_prescriptions_sidebar > 0): ?>
                <span class="badge danger"><?= $pending_prescriptions_sidebar ?></span>
            <?php endif; ?>
        </a>
        <a href="view_reception.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-headset"></i> <span class="link-text">Reception</span>
            <span class="badge"><?= $module_counts['reception'] ?? 0 ?></span>
        </a>
        <a href="view_laboratory.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-flask"></i> <span class="link-text">Laboratory</span>
            <span class="badge"><?= $module_counts['laboratory'] ?? 0 ?></span>
            <?php if ($pending_lab_tests_sidebar > 0): ?>
                <span class="badge danger"><?= $pending_lab_tests_sidebar ?></span>
            <?php endif; ?>
        </a>
        <a href="view_cashier.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-cash-register"></i> <span class="link-text">Cashier</span>
            <span class="badge"><?= $module_counts['cashier'] ?? 0 ?></span>
        </a>
        
        <div class="nav-label"><span class="label-icon">💼</span> Services</div>
        <a href="services.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-concierge-bell"></i> <span class="link-text">Services</span>
            <span class="badge"><?= $total_services_sidebar ?></span>
            <?php if ($today_services_sidebar > 0): ?>
                <span class="badge success">+<?= $today_services_sidebar ?></span>
            <?php endif; ?>
        </a>
        
        <div class="nav-label"><span class="label-icon">🏢</span> Management</div>
        <a href="branches.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-store-alt"></i> <span class="link-text">Branches</span>
            <span class="badge"><?= $total_branches_sidebar ?></span>
        </a>
        <a href="departments.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-building"></i> <span class="link-text">Departments</span>
        </a>
        <a href="reports.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-chart-bar"></i> <span class="link-text">Reports</span>
        </a>
        
        <div class="nav-label"><span class="label-icon">🔧</span> System</div>
        <a href="settings.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-cog"></i> <span class="link-text">Settings</span>
        </a>
        
        <div class="nav-label"><span class="label-icon">👤</span> Account</div>
        <a href="profile.php" class="sidebar-link">
            <i class="fas fa-user-circle"></i> <span class="link-text">Profile</span>
        </a>
        <a href="/dispensary_system/frontend/pages/logout.php" class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i> <span class="link-text">Logout</span>
        </a>
    </nav>
    
    <div class="sidebar-status">
        <span class="status-dot <?= $user_is_online ? 'online' : 'offline' ?>"></span>
        <span class="status-text"><?= $user_is_online ? 'Online' : 'Offline' ?></span>
    </div>
</aside>

<!-- ================================================================ -->
<!-- HEADER -->
<!-- ================================================================ -->
<header class="embedded-header">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="icon-btn lg:hidden">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search purchases...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="datetime" id="currentDateTime">
            <i class="fas fa-clock" style="color:#059669;"></i>
            <span id="clockDisplay"><?= date('d M Y • h:i:s A') ?></span>
        </span>
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        <button class="icon-btn" onclick="window.location.href='notifications.php'">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</header>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header-box">
        <div>
            <h1 class="page-title">
                <i class="fas fa-history"></i>
                Purchase History
                <span class="role-badge-display">ADMIN</span>
                <span class="branch-name-display">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($display_branch_name) ?>
                </span>
            </h1>
        </div>
        <div class="header-actions">
            <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
            <a href="purchases.php?branch=<?= $selected_branch_id ?>" class="btn-back">
                <i class="fas fa-shopping-cart"></i> New Purchase
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>" id="messageBox">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'info' ? 'fa-info-circle' : 'fa-exclamation-circle') ?>"></i>
            <span><?= $message ?></span>
            <button class="message-close" onclick="closeMessage()">&times;</button>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-card blue">
            <span class="stat-icon"><i class="fas fa-shopping-cart"></i></span>
            <div class="stat-number"><?= $total_purchases ?></div>
            <div class="stat-label">Total Purchases</div>
            <div class="stat-sub">All purchases</div>
        </div>
        <div class="stat-card green">
            <span class="stat-icon"><i class="fas fa-check-circle"></i></span>
            <div class="stat-number"><?= $completed_count ?></div>
            <div class="stat-label">Completed</div>
            <div class="stat-sub">Finished purchases</div>
        </div>
        <div class="stat-card orange">
            <span class="stat-icon"><i class="fas fa-spinner fa-spin"></i></span>
            <div class="stat-number"><?= $in_progress_count ?></div>
            <div class="stat-label">In Progress</div>
            <div class="stat-sub">Pending completion</div>
        </div>
        <div class="stat-card red">
            <span class="stat-icon"><i class="fas fa-coins"></i></span>
            <div class="stat-number">TSh <?= formatMoneyShort($total_spending) ?></div>
            <div class="stat-label">Total Buying Cost</div>
            <div class="stat-sub">Money spent on purchases</div>
        </div>
        <div class="stat-card purple">
            <span class="stat-icon"><i class="fas fa-chart-line"></i></span>
            <div class="stat-number <?= $total_profit >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                TSh <?= formatMoneyShort($total_profit) ?>
            </div>
            <div class="stat-label"><?= $total_profit >= 0 ? '💰 Total Profit' : '📉 Total Loss' ?></div>
            <div class="stat-sub"><?= $total_spending > 0 ? round(($total_profit / $total_spending) * 100, 1) . '% margin' : 'No data' ?></div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="filter-group">
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&status=all" class="filter-btn <?= $status_filter === 'all' ? 'active' : '' ?>">All</a>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&status=COMPLETED" class="filter-btn <?= $status_filter === 'COMPLETED' ? 'active' : '' ?>">Completed</a>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&status=IN_PROGRESS" class="filter-btn <?= $status_filter === 'IN_PROGRESS' ? 'active' : '' ?>">In Progress</a>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&type=medicine" class="filter-btn <?= $type_filter === 'medicine' ? 'active' : '' ?>">💊 Medicine</a>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&type=equipment" class="filter-btn <?= $type_filter === 'equipment' ? 'active' : '' ?>">🔧 Equipment</a>
            <?php if ($status_filter !== 'all' || $type_filter !== 'all' || !empty($search) || !empty($date_from) || !empty($date_to)): ?>
                <a href="purchase_history.php?branch=<?= $selected_branch_id ?>" class="filter-btn clear-filter">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </div>
        
        <form method="GET" class="search-form">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
            <input type="hidden" name="status" value="<?= $status_filter ?>">
            <input type="hidden" name="type" value="<?= $type_filter ?>">
            <input type="text" name="search" placeholder="🔍 Search invoice or creator..." value="<?= htmlspecialchars($search) ?>">
            <input type="date" name="date_from" value="<?= $date_from ?>" placeholder="From">
            <input type="date" name="date_to" value="<?= $date_to ?>" placeholder="To">
            <button type="submit" class="btn-search"><i class="fas fa-search"></i> Filter</button>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- PURCHASE TABLE -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue"></i> Purchase List
                <span class="result-count">(<strong><?= count($purchases) ?></strong> purchases)</span>
            </h3>
            <div class="scroll-arrows">
                <button class="scroll-arrow-btn" onclick="scrollTable('left')" title="Scroll Left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="scroll-arrow-btn" onclick="scrollTable('right')" title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <?php if (count($purchases) > 0): ?>
            <div class="table-wrapper">
                <div class="table-scroll-container" id="tableScrollContainer">
                    <table class="data-table" id="purchaseTable">
                        <thead>
                            <tr>
                                <th class="col-sno">#</th>
                                <th class="col-invoice">Invoice</th>
                                <th class="col-type">Type</th>
                                <th class="col-creator">Created By</th>
                                <th class="col-items">Items</th>
                                <th class="col-qty">Qty</th>
                                <th class="col-buying">Buying Cost</th>
                                <th class="col-selling">Selling Value</th>
                                <th class="col-profit">Profit</th>
                                <th class="col-status">Status</th>
                                <th class="col-date">Date</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($purchases as $purchase): ?>
                                <?php 
                                    $profit = ($purchase['total_selling_value'] ?? 0) - ($purchase['total_buying_cost'] ?? 0);
                                    $profit_class = $profit >= 0 ? 'profit-positive' : 'profit-negative';
                                    $status_class = strtolower($purchase['status']);
                                    $status_icon = $purchase['status'] === 'COMPLETED' ? 'fa-check-circle' : ($purchase['status'] === 'IN_PROGRESS' ? 'fa-spinner fa-spin' : 'fa-times-circle');
                                    $has_items = ($purchase['items_count'] ?? 0) > 0;
                                    $can_edit = ($purchase['status'] === 'IN_PROGRESS');
                                ?>
                                <tr>
                                    <td class="col-sno"><?= $counter++ ?></td>
                                    <td class="col-invoice">
                                        <strong><?= htmlspecialchars($purchase['invoice_number']) ?></strong>
                                    </td>
                                    <td class="col-type">
                                        <span class="type-badge <?= $purchase['purchase_type'] ?>">
                                            <?= ucfirst($purchase['purchase_type']) ?>
                                        </span>
                                    </td>
                                    <td class="col-creator">
                                        <i class="fas fa-user" style="color:var(--primary);font-size:0.65rem;"></i>
                                        <?= htmlspecialchars($purchase['creator_name'] ?? $purchase['created_by_name'] ?? 'Unknown') ?>
                                        <?php if ($purchase['status'] === 'CANCELLED' && $purchase['cancelled_by_name']): ?>
                                            <br><span style="font-size:0.55rem;color:var(--danger);">
                                                <i class="fas fa-user-slash"></i> Cancelled by: <?= htmlspecialchars($purchase['cancelled_by_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-items"><?= number_format($purchase['items_count'] ?? 0) ?></td>
                                    <td class="col-qty"><?= number_format($purchase['total_qty'] ?? 0) ?></td>
                                    <td class="col-buying" style="color:var(--danger);font-weight:600;">
                                        TSh <?= number_format($purchase['total_buying_cost'] ?? 0) ?>
                                    </td>
                                    <td class="col-selling" style="color:var(--success);font-weight:600;">
                                        TSh <?= number_format($purchase['total_selling_value'] ?? 0) ?>
                                    </td>
                                    <td class="col-profit">
                                        <span class="<?= $profit_class ?>" style="font-weight:600;">
                                            TSh <?= number_format($profit) ?>
                                            <?php if ($purchase['total_buying_cost'] > 0 && $purchase['status'] === 'COMPLETED'): ?>
                                                <span style="font-size:0.55rem;">
                                                    (<?= round(($profit / $purchase['total_buying_cost']) * 100, 1) ?>%)
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="col-status">
                                        <span class="status-badge <?= $status_class ?>">
                                            <i class="fas <?= $status_icon ?>"></i>
                                            <?= $purchase['status'] ?>
                                        </span>
                                    </td>
                                    <td class="col-date">
                                        <?= date('d/m/Y H:i', strtotime($purchase['created_at'])) ?>
                                        <?php if ($purchase['status'] === 'COMPLETED' && $purchase['completed_at']): ?>
                                            <br><span style="font-size:0.55rem;color:var(--text-muted);">
                                                <i class="fas fa-check-circle" style="color:var(--success);"></i>
                                                <?= date('d/m/Y H:i', strtotime($purchase['completed_at'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-actions">
                                        <div class="action-group">
                                            <!-- View Button -->
                                            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&view=<?= $purchase['id'] ?>" class="action-btn view" title="View Details">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            
                                            <!-- Edit Button - Only for IN_PROGRESS -->
                                            <?php if ($can_edit): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="edit_purchase">
                                                    <input type="hidden" name="purchase_id" value="<?= $purchase['id'] ?>">
                                                    <input type="hidden" name="purchase_type" value="<?= $purchase['purchase_type'] ?>">
                                                    <button type="submit" class="action-btn edit" title="Edit Purchase">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <!-- Delete Button - For all purchases -->
                                            <button class="action-btn delete" 
                                                    onclick="openDeleteModal(<?= $purchase['id'] ?>, '<?= addslashes($purchase['invoice_number']) ?>')" 
                                                    title="Delete Purchase">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                            
                                            <!-- PDF Invoice - Opens in new window -->
                                            <?php if ($purchase['status'] === 'COMPLETED' && $has_items): ?>
                                                <a href="get_invoice.php?id=<?= $purchase['id'] ?>" target="_blank" class="btn-pdf-new-window" title="View PDF Invoice">
                                                    <i class="fas fa-file-pdf"></i> PDF
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <p>No purchases found</p>
                <p class="sub">Try adjusting your filters or create a new purchase</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-400 mx-2">|</span>
            Admin Purchase History
            <span class="text-gray-400 mx-2">|</span>
            <strong><?= $total_purchases ?></strong> purchases · 
            <strong><?= $completed_count ?></strong> completed · 
            TSh <strong><?= formatMoney($total_spending) ?></strong> spent
            <span class="text-gray-400 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- VIEW MODAL -->
<!-- ================================================================ -->
<?php if ($view_purchase && $view_id > 0): ?>
<div class="modal-overlay show" id="viewModal" style="display:flex;">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-eye"></i> Purchase Details - <?= htmlspecialchars($view_purchase['invoice_number']) ?>
                <span style="font-size:0.75rem;font-weight:400;color:var(--text-secondary);margin-left:8px;">
                    (<?= ucfirst($view_purchase['purchase_type']) ?>)
                </span>
            </div>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>" class="modal-close">&times;</a>
        </div>
        
        <div class="view-grid">
            <div class="view-item full-width">
                <div class="label">Status</div>
                <div class="value">
                    <span class="status-badge <?= strtolower($view_purchase['status']) ?>">
                        <i class="fas <?= $view_purchase['status'] === 'COMPLETED' ? 'fa-check-circle' : ($view_purchase['status'] === 'IN_PROGRESS' ? 'fa-spinner fa-spin' : 'fa-times-circle') ?>"></i>
                        <?= $view_purchase['status'] ?>
                    </span>
                </div>
            </div>
            <div class="view-item">
                <div class="label">Created By</div>
                <div class="value"><?= htmlspecialchars($view_purchase['creator_name'] ?? 'Unknown') ?></div>
            </div>
            <div class="view-item">
                <div class="label">Created At</div>
                <div class="value"><?= date('d/m/Y H:i', strtotime($view_purchase['created_at'])) ?></div>
            </div>
            <?php if ($view_purchase['completed_at']): ?>
                <div class="view-item">
                    <div class="label">Completed At</div>
                    <div class="value"><?= date('d/m/Y H:i', strtotime($view_purchase['completed_at'])) ?></div>
                </div>
            <?php endif; ?>
            <?php if ($view_purchase['status'] === 'CANCELLED' && $view_purchase['cancelled_reason']): ?>
                <div class="view-item full-width">
                    <div class="label">Cancellation Reason</div>
                    <div class="value" style="color:var(--danger);"><?= htmlspecialchars($view_purchase['cancelled_reason']) ?></div>
                </div>
                <div class="view-item">
                    <div class="label">Cancelled By</div>
                    <div class="value"><?= htmlspecialchars($view_purchase['cancelled_by_name'] ?? 'Admin') ?></div>
                </div>
            <?php endif; ?>
            <div class="view-item">
                <div class="label">Total Items</div>
                <div class="value"><?= number_format($view_purchase['total_items']) ?></div>
            </div>
            <div class="view-item">
                <div class="label">Total Quantity</div>
                <div class="value"><?= number_format($view_purchase['total_quantity']) ?> units</div>
            </div>
            <div class="view-item" style="background:var(--danger-light);border:2px solid var(--danger);">
                <div class="label" style="color:var(--danger);">Total Buying Cost</div>
                <div class="value" style="color:var(--danger);">TSh <?= number_format($view_purchase['total_buying_cost'] ?? 0) ?></div>
            </div>
            <div class="view-item" style="background:var(--success-light);border:2px solid var(--success);">
                <div class="label" style="color:var(--success);">Total Selling Value</div>
                <div class="value" style="color:var(--success);">TSh <?= number_format($view_purchase['total_selling_value'] ?? 0) ?></div>
            </div>
            <?php 
                $view_profit = ($view_purchase['total_selling_value'] ?? 0) - ($view_purchase['total_buying_cost'] ?? 0);
                $view_profit_class = $view_profit >= 0 ? 'profit-positive' : 'profit-negative';
            ?>
            <div class="view-item" style="background:var(<?= $view_profit >= 0 ? '--success-light' : '--danger-light' ?>);border:2px solid var(<?= $view_profit >= 0 ? '--success' : '--danger' ?>);">
                <div class="label" style="color:var(<?= $view_profit >= 0 ? '--success' : '--danger' ?>);">Expected Profit</div>
                <div class="value <?= $view_profit_class ?>">
                    TSh <?= number_format($view_profit) ?>
                    <?php if ($view_purchase['total_buying_cost'] > 0): ?>
                        <span style="font-size:0.6rem;">
                            (<?= round(($view_profit / $view_purchase['total_buying_cost']) * 100, 1) ?>% margin)
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (count($view_items) > 0): ?>
            <div style="margin-top:12px;">
                <div style="font-size:0.8rem;font-weight:600;margin-bottom:8px;color:var(--text-primary);">
                    <i class="fas fa-list"></i> Items (<?= count($view_items) ?>)
                </div>
                <div style="overflow-x:auto;">
                    <table class="view-items-table">
                        <thead>
                            <tr>
                                <th style="width:30px;">#</th>
                                <th>Item</th>
                                <th style="text-align:center;width:50px;">Qty</th>
                                <th style="text-align:right;width:100px;">Buy Price</th>
                                <th style="text-align:right;width:100px;">Buy Total</th>
                                <th style="text-align:right;width:100px;">Sell Price</th>
                                <th style="text-align:right;width:100px;">Sell Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $vcounter = 1; ?>
                            <?php foreach ($view_items as $item): ?>
                                <tr>
                                    <td style="text-align:center;"><?= $vcounter++ ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($item['item_name'] ?? 'Unknown') ?></strong>
                                        <div style="font-size:0.6rem;color:var(--text-muted);">
                                            <?= htmlspecialchars($item['unit'] ?? 'pcs') ?> | 
                                            Batch: <?= htmlspecialchars($item['batch_number'] ?? 'N/A') ?> |
                                            Added by: <?= htmlspecialchars($item['added_by_full_name'] ?? 'Unknown') ?>
                                        </div>
                                    </td>
                                    <td style="text-align:center;"><?= number_format($item['quantity']) ?></td>
                                    <td style="text-align:right;color:var(--danger);">TSh <?= number_format($item['buying_price'] ?? 0) ?></td>
                                    <td style="text-align:right;color:var(--danger);font-weight:600;">TSh <?= number_format($item['total_buying_cost'] ?? 0) ?></td>
                                    <td style="text-align:right;color:var(--success);">TSh <?= number_format($item['selling_price'] ?? 0) ?></td>
                                    <td style="text-align:right;color:var(--success);font-weight:600;">TSh <?= number_format($item['total_selling_value'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4"></td>
                                <td style="text-align:right;font-weight:700;color:var(--danger);border-top:2px solid var(--danger);">
                                    TSh <?= number_format($view_purchase['total_buying_cost'] ?? 0) ?>
                                </td>
                                <td></td>
                                <td style="text-align:right;font-weight:700;color:var(--success);border-top:2px solid var(--success);">
                                    TSh <?= number_format($view_purchase['total_selling_value'] ?? 0) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding:20px;">
                <i class="fas fa-box-open" style="font-size:1.5rem;"></i>
                <p>No items found in this purchase</p>
            </div>
        <?php endif; ?>
        
        <div class="modal-actions">
            <?php if ($view_purchase['status'] === 'COMPLETED' && count($view_items) > 0): ?>
                <a href="get_invoice.php?id=<?= $view_purchase['id'] ?>" target="_blank" class="btn-print-invoice" style="text-decoration:none;">
                    <i class="fas fa-file-pdf"></i> PDF Invoice
                </a>
            <?php endif; ?>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>" class="btn-close-modal">
                <i class="fas fa-times"></i> Close
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ================================================================ -->
<!-- DELETE MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="deleteModal">
    <div class="delete-modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-exclamation-triangle"></i> Delete Purchase
            </div>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        
        <div class="delete-warning-icon">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        
        <p style="text-align:center;color:var(--text-primary);font-weight:500;margin-bottom:4px;">
            Are you sure you want to delete this purchase?
        </p>
        <p style="text-align:center;color:var(--text-secondary);font-size:0.85rem;margin-bottom:12px;">
            <strong id="deleteInvoiceNumber"></strong>
        </p>
        
        <div class="delete-note">
            <p>
                <i class="fas fa-info-circle"></i> 
                <strong>Note:</strong> This will only delete the purchase record.
                <br>Inventory stock will <strong>NOT</strong> be affected.
            </p>
        </div>
        
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete_purchase">
            <input type="hidden" name="purchase_id" id="deletePurchaseId" value="">
            <input type="hidden" name="confirmed" value="1">
            
            <div style="margin-bottom:12px;">
                <label class="form-label" style="font-size:0.8rem;">Reason for Deletion <span class="required">*</span></label>
                <textarea name="delete_reason" id="deleteReason" class="delete-reason-textarea" 
                          placeholder="Why is this purchase being deleted? (This will be logged for audit)" required></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn-confirm-delete">
                    <i class="fas fa-trash"></i> Yes, Delete Purchase
                </button>
                <button type="button" class="btn-close-modal" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> No, Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
// ================================================================
// CLOSE MESSAGE
// ================================================================
function closeMessage() {
    var messageBox = document.getElementById('messageBox');
    if (messageBox) {
        messageBox.style.display = 'none';
    }
}

setTimeout(function() {
    var messageBox = document.getElementById('messageBox');
    if (messageBox) {
        messageBox.style.display = 'none';
    }
}, 5000);

// ================================================================
// CLOCK
// ================================================================
function updateClock() {
    var now = new Date();
    var dateStr = now.toLocaleDateString('en-US', {
        weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
    });
    var timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    var el = document.getElementById('clockDisplay');
    if (el) {
        el.textContent = dateStr + ' • ' + timeStr;
    }
}
setInterval(updateClock, 1000);
updateClock();

// ================================================================
// BRANCH SWITCHER
// ================================================================
function switchBranch(branchId) {
    var url = new URL(window.location.href);
    url.searchParams.set('branch', branchId);
    window.location.href = url.toString();
}

// ================================================================
// SEARCH
// ================================================================
var searchBtn = document.getElementById('searchBtn');
var searchInput = document.getElementById('searchInput');

function performSearch() {
    var query = searchInput.value.trim();
    if (query.length > 0) {
        var branch = document.getElementById('branchSelector')?.value || 'all';
        var status = document.querySelector('input[name="status"]')?.value || 'all';
        var type = document.querySelector('input[name="type"]')?.value || 'all';
        window.location.href = 'purchase_history.php?search=' + encodeURIComponent(query) + '&branch=' + branch + '&status=' + status + '&type=' + type;
    }
}

searchBtn?.addEventListener('click', performSearch);
searchInput?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') performSearch();
});

document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
});

// ================================================================
// SIDEBAR TOGGLE
// ================================================================
(function() {
    var sidebar = document.getElementById('sidebar');
    var toggleBtn = document.getElementById('sidebarToggle');
    var overlay = document.getElementById('sidebarOverlay');
    
    function toggleSidebar() {
        if (!sidebar) return;
        sidebar.classList.toggle('open');
        if (overlay) {
            if (sidebar.classList.contains('open')) {
                overlay.style.display = 'block';
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            } else {
                overlay.style.display = 'none';
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
    }
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            if (sidebar) {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    }
})();

// ================================================================
// DARK MODE TOGGLE
// ================================================================
(function() {
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        if (darkIcon) darkIcon.className = 'fas fa-sun';
        if (darkText) darkText.textContent = 'Light';
    }
    
    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', function(e) {
            e.preventDefault();
            var isDarkNow = htmlElement.getAttribute('data-theme') === 'dark';
            if (isDarkNow) {
                htmlElement.removeAttribute('data-theme');
                if (darkIcon) darkIcon.className = 'fas fa-moon';
                if (darkText) darkText.textContent = 'Dark';
                localStorage.setItem('darkMode', 'false');
                document.cookie = "dark_mode=false; path=/";
            } else {
                htmlElement.setAttribute('data-theme', 'dark');
                if (darkIcon) darkIcon.className = 'fas fa-sun';
                if (darkText) darkText.textContent = 'Light';
                localStorage.setItem('darkMode', 'true');
                document.cookie = "dark_mode=true; path=/";
            }
        });
    }
})();

// ================================================================
// TABLE SCROLL
// ================================================================
function scrollTable(direction) {
    var container = document.getElementById('tableScrollContainer');
    if (!container) return;
    var scrollAmount = 300;
    if (direction === 'left') {
        container.scrollLeft -= scrollAmount;
    } else {
        container.scrollLeft += scrollAmount;
    }
}

// ================================================================
// DELETE MODAL FUNCTIONS
// ================================================================
function openDeleteModal(purchaseId, invoiceNumber) {
    var modal = document.getElementById('deleteModal');
    if (!modal) return;
    
    document.getElementById('deletePurchaseId').value = purchaseId;
    document.getElementById('deleteInvoiceNumber').textContent = 'Invoice: ' + invoiceNumber;
    document.getElementById('deleteReason').value = '';
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    var modal = document.getElementById('deleteModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }
}

document.getElementById('deleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

// ================================================================
// CLOSE MODAL ON ESCAPE
// ================================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
    }
});

// ================================================================
// CONSOLE LOG
// ================================================================
console.log('%c📜 Braick - Admin Purchase History', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Total Purchases: <?= $total_purchases ?>', 'font-size:13px; color:#059669;');
console.log('%c✅ Completed: <?= $completed_count ?> | In Progress: <?= $in_progress_count ?>', 'font-size:13px; color:#D97706;');
console.log('%c💰 Total Spending: TSh <?= formatMoney($total_spending) ?>', 'font-size:13px; color:#DC2626;');
console.log('%c💰 Total Selling: TSh <?= formatMoney($total_selling) ?>', 'font-size:13px; color:#059669;');
console.log('%c📈 Total Profit: TSh <?= formatMoney($total_profit) ?>', 'font-size:13px; color:<?= $total_profit >= 0 ? '#059669' : '#DC2626' ?>;');
console.log('%c🗑️ Delete removes from purchases only (inventory stock unaffected)', 'font-size:13px; color:#EF4444; font-weight:bold;');
console.log('%c📄 PDF: Opens get_invoice.php in new window', 'font-size:13px; color:#DC2626;');
console.log('%c⬅️➡️ Scroll buttons added to table header', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>