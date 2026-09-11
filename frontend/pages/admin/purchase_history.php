<?php
// ================================================================
// FILE: frontend/pages/admin/purchase_history.php
// ADMIN - PURCHASE HISTORY WITH VIEW, EDIT, DELETE, CANCEL REASON
// ✅ EMBEDDED HEADER (same as shared admin_header.php)
// ✅ Uses SHARED admin_sidebar.php
// DELETE removes from purchases/purchase_items ONLY
// Inventory stock REMAINS (does not delete stock)
// WITH SCROLL BUTTONS AND PDF IN NEW PAGE
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

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

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'admin';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_is_online = $_SESSION['is_online'] ?? 1;

$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';
if ($selected_branch_id === 'all') {
    $branch_id_for_query = $user_branch_id;
} else {
    $branch_id_for_query = (int)$selected_branch_id;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$delete_id = isset($_GET['delete']) ? (int)$_GET['delete'] : 0;

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

function formatMoney($amount) {
    if ($amount === null || $amount === '') return '0.00';
    return number_format((float)$amount, 2, '.', ',');
}

function formatMoneyShort($amount) {
    if ($amount === null || $amount === '') return '0';
    $amount = (float)$amount;
    if ($amount >= 1000000000) return number_format($amount / 1000000000, 1) . 'B';
    if ($amount >= 1000000) return number_format($amount / 1000000, 1) . 'M';
    if ($amount >= 1000) return number_format($amount / 1000, 1) . 'K';
    return number_format($amount, 0);
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_post = $_POST['action'] ?? '';
    
    // DELETE PURCHASE
    if ($action_post === 'delete_purchase') {
        $purchase_id = (int)($_POST['purchase_id'] ?? 0);
        $confirmed = isset($_POST['confirmed']) ? (int)$_POST['confirmed'] : 0;
        $delete_reason = trim($_POST['delete_reason'] ?? '');
        
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
                    $stmt = $db->prepare("DELETE FROM purchase_items WHERE purchase_id = ?");
                    $stmt->execute([$purchase_id]);
                    
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
        }
    }
    
    // EDIT PURCHASE
    if ($action_post === 'edit_purchase') {
        $purchase_id = (int)($_POST['purchase_id'] ?? 0);
        $purchase_type = $_POST['purchase_type'] ?? 'medicine';
        header('Location: purchases.php?id=' . $purchase_id . '&type=' . $purchase_type . '&branch=' . $selected_branch_id);
        exit;
    }
}

if (isset($_SESSION['purchase_history_message'])) {
    $message = $_SESSION['purchase_history_message'];
    $message_type = $_SESSION['purchase_history_message_type'] ?? 'success';
    unset($_SESSION['purchase_history_message']);
    unset($_SESSION['purchase_history_message_type']);
}

// GET BRANCHES
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// GET FILTERS
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// BUILD QUERY
$query = "
    SELECT 
        p.*,
        u.full_name as creator_name,
        (SELECT COUNT(*) FROM purchase_items WHERE purchase_id = p.id) as items_count,
        (SELECT SUM(quantity) FROM purchase_items WHERE purchase_id = p.id) as total_qty
    FROM purchases p
    LEFT JOIN users u ON p.created_by = u.id
    WHERE 1=1
";

$params = [];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $query .= " AND p.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

if ($status_filter !== 'all') {
    $query .= " AND p.status = ?";
    $params[] = $status_filter;
}

if ($type_filter !== 'all') {
    $query .= " AND p.purchase_type = ?";
    $params[] = $type_filter;
}

if (!empty($search)) {
    $query .= " AND (p.invoice_number LIKE ? OR p.created_by_name LIKE ? OR p.cancelled_reason LIKE ?)";
    $params[] = "%$search%";
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

$query .= " ORDER BY 
    CASE p.status 
        WHEN 'IN_PROGRESS' THEN 1 
        WHEN 'COMPLETED' THEN 2 
        WHEN 'CANCELLED' THEN 3 
        ELSE 4 
    END,
    p.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// GET SINGLE PURCHASE FOR VIEW
$view_purchase = null;
$view_items = [];
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG';

if ($view_id > 0) {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as creator_name, cb.full_name as cancelled_by_full_name
        FROM purchases p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN users cb ON p.cancelled_by = cb.id
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

// GET STATISTICS
$stats_where = "";
$stats_params = [];
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stats_where = " WHERE branch_id = ?";
    $stats_params = [(int)$selected_branch_id];
}

$stmt = $db->prepare("SELECT COUNT(*) as count FROM purchases" . $stats_where);
$stmt->execute($stats_params);
$total_purchases = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$sql = "SELECT COUNT(*) as count FROM purchases WHERE status = 'COMPLETED'";
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $sql .= " AND branch_id = ?";
}
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$sql = "SELECT COUNT(*) as count FROM purchases WHERE status = 'IN_PROGRESS'";
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $sql .= " AND branch_id = ?";
}
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$sql = "SELECT COUNT(*) as count FROM purchases WHERE status = 'CANCELLED'";
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $sql .= " AND branch_id = ?";
}
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$cancelled_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$sql = "SELECT COALESCE(SUM(total_buying_cost), 0) as total FROM purchases WHERE status = 'COMPLETED'";
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $sql .= " AND branch_id = ?";
}
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$total_spending = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$sql = "SELECT COALESCE(SUM(total_selling_value), 0) as total FROM purchases WHERE status = 'COMPLETED'";
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $sql .= " AND branch_id = ?";
}
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$total_selling = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$total_profit = $total_selling - $total_spending;

// UNREAD NOTIFICATIONS
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$display_branch_name = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    foreach ($branches as $b) {
        if ($b['id'] == $selected_branch_id) {
            $display_branch_name = $b['name'];
            break;
        }
    }
}

// ================================================================
// ✅ INCLUDE SHARED SIDEBAR ONLY (NO HEADER - WE HAVE EMBEDDED HEADER)
// ================================================================
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase History - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7; --primary-dark: #0A4CA8; --primary-light: #E8F0FE;
            --success: #059669; --success-dark: #047857; --success-light: #D1FAE5;
            --warning: #D97706; --warning-light: #FEF3C7;
            --danger: #DC2626; --danger-light: #FEE2E2;
            --purple: #7C3AED; --purple-light: #EDE9FE;
            --teal: #0D9488; --teal-light: #CCFBF1;
            --bg-body: #F1F5F9; --bg-card: #FFFFFF; --bg-nav: #FFFFFF;
            --border-color: #E2E8F0;
            --text-primary: #1E293B; --text-secondary: #64748B; --text-muted: #94A3B8;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A; --bg-card: #1E293B; --bg-nav: #1E293B;
            --border-color: #334155;
            --text-primary: #F1F5F9; --text-secondary: #94A3B8; --text-muted: #64748B;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: var(--bg-body); color: var(--text-primary); }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        /* ================================================================
           ✅ EMBEDDED HEADER - SAME AS SHARED admin_header.php
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
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 12px;
            border: 2px solid var(--border-color);
            flex: 1;
            max-width: 500px;
            height: 42px;
            transition: all 0.3s;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
            height: 100%;
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.85rem;
            height: 100%;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
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
        
        .top-nav .datetime i {
            color: var(--primary-light);
        }
        
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
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
            transition: all 0.3s;
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
        .notif-dot.no-notif { background: var(--text-muted); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .branch-selector {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.78rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .branch-selector:focus {
            border-color: var(--primary);
        }
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content { margin-left: 270px; margin-top: 68px; padding: 28px 32px; min-height: calc(100vh - 68px); }
        
        /* PAGE HEADER BOX */
        .page-header-box {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
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
        
        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
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
        .stat-card.dark-red { background: linear-gradient(135deg, #7F1D1D, #450A0A); }
        
        /* CARD */
        .card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 18px 22px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .card:hover {
            border-color: #0B5ED7;
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
        
        .card-title .title-blue { color: #0B5ED7; }
        .card-title .title-green { color: #059669; }
        
        .result-count {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .result-count strong { color: #0B5ED7; }
        
        /* SCROLL BUTTONS */
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
            border-color: #0B5ED7;
            color: #0B5ED7;
            background: var(--primary-light);
            transform: scale(1.05);
        }
        
        .table-wrapper { position: relative; }
        
        .table-scroll-container {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 500px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-scroll-container::-webkit-scrollbar { height: 6px; width: 5px; }
        .table-scroll-container::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
        .table-scroll-container::-webkit-scrollbar-thumb { background: #0B5ED7; border-radius: 4px; }
        
        /* FILTERS */
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
        
        .filter-btn:hover { border-color: #0B5ED7; color: #0B5ED7; }
        .filter-btn.active { background: #0B5ED7; border-color: #0B5ED7; color: white; }
        
        .filter-btn.clear-filter { border-color: #DC2626; color: #DC2626; }
        .filter-btn.clear-filter:hover { background: #DC2626; color: white; }
        
        .filter-btn.cancelled-filter { border-color: #7F1D1D; color: #7F1D1D; }
        .filter-btn.cancelled-filter:hover { background: #7F1D1D; color: white; }
        .filter-btn.cancelled-filter.active { background: #7F1D1D; border-color: #7F1D1D; color: white; }
        
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
            border-color: #0B5ED7;
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .btn-search {
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            background: #0B5ED7;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-search:hover {
            background: #0A4CA8;
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
        
        .btn-reset:hover { border-color: #DC2626; color: #DC2626; }
        
        /* TABLE */
        .data-table {
            width: 100%;
            min-width: 1300px;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.78rem;
        }
        
        .data-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #0B5ED7;
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
        
        .data-table tbody tr:nth-child(even) { background: #E8F0FE; }
        .data-table tbody tr:hover td { background: #D1FAE5; }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) { background: #1E293B; }
        [data-theme="dark"] .data-table tbody tr:hover td { background: #1A3A2A; }
        
        .data-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .col-sno { width: 35px; text-align: center; }
        .col-invoice { min-width: 140px; }
        .col-type { min-width: 80px; }
        .col-creator { min-width: 140px; }
        .col-items { min-width: 60px; text-align: center; }
        .col-qty { min-width: 60px; text-align: center; }
        .col-buying { min-width: 120px; }
        .col-selling { min-width: 120px; }
        .col-profit { min-width: 120px; }
        .col-status { min-width: 150px; text-align: center; }
        .col-reason { min-width: 200px; }
        .col-date { min-width: 140px; }
        .col-actions { min-width: 220px; text-align: center; }
        
        /* BADGES */
        .status-badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .status-badge.completed { background: #D1FAE5; color: #059669; }
        .status-badge.in-progress { background: #FEF3C7; color: #D97706; }
        .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
        
        .type-badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.55rem;
            font-weight: 600;
        }
        
        .type-badge.medicine { background: #E8F0FE; color: #0B5ED7; }
        .type-badge.equipment { background: #EDE9FE; color: #7C3AED; }
        
        .profit-positive { color: #059669; }
        .profit-negative { color: #DC2626; }
        
        /* CANCEL REASON DISPLAY */
        .cancel-reason-cell {
            font-size: 0.7rem;
            color: #DC2626;
            background: #FEE2E2;
            padding: 4px 8px;
            border-radius: 6px;
            border-left: 3px solid #DC2626;
            max-width: 200px;
            word-wrap: break-word;
            white-space: normal;
            line-height: 1.3;
        }
        
        .cancel-reason-cell .reason-label {
            font-weight: 700;
            font-size: 0.6rem;
            text-transform: uppercase;
            display: block;
            margin-bottom: 2px;
            color: #991B1B;
        }
        
        /* ACTION BUTTONS */
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
        
        .action-btn.view { background: #0B5ED7; }
        .action-btn.view:hover { background: #0A4CA8; transform: scale(1.03); }
        
        .action-btn.edit { background: #D97706; }
        .action-btn.edit:hover { background: #B45309; transform: scale(1.03); }
        
        .action-btn.delete { background: #DC2626; }
        .action-btn.delete:hover { background: #991B1B; transform: scale(1.03); }
        
        .action-btn i { font-size: 0.5rem; }
        
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
        
        .btn-pdf-new-window:hover { background: #991B1B; transform: scale(1.03); }
        .btn-pdf-new-window i { font-size: 0.5rem; }
        
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
        
        .empty-state .sub { font-size: 0.8rem; margin-top: 4px; }
        
        .footer {
            padding: 12px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* MODAL */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .modal-overlay.show { display: flex; }
        
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
            color: #0B5ED7;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .modal-close:hover { color: #DC2626; transform: rotate(90deg); }
        
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
            text-decoration: none;
        }
        
        .btn-close-modal:hover { border-color: #DC2626; color: #DC2626; }
        
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
        
        .btn-print-invoice:hover { background: #991B1B; transform: translateY(-2px); }
        
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
        
        /* CANCELLED INFO BOX IN MODAL */
        .cancelled-info-box {
            background: linear-gradient(135deg, #FEF2F2, #FEE2E2);
            border: 2px solid #DC2626;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 16px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        
        [data-theme="dark"] .cancelled-info-box {
            background: linear-gradient(135deg, #3A1A1A, #4A1F1F);
        }
        
        .cancelled-info-box i {
            color: #DC2626;
            font-size: 1.5rem;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .cancelled-info-box .info-text {
            flex: 1;
        }
        
        .cancelled-info-box .info-text .info-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #991B1B;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .cancelled-info-box .info-text .info-title i {
            font-size: 0.9rem;
            margin-top: 0;
        }
        
        .cancelled-info-box .info-text .info-row {
            font-size: 0.78rem;
            color: #7F1D1D;
            margin-bottom: 3px;
            display: flex;
            gap: 6px;
            align-items: flex-start;
        }
        
        .cancelled-info-box .info-text .info-row .info-label {
            font-weight: 600;
            min-width: 100px;
        }
        
        .cancelled-info-box .info-text .info-row .info-value {
            font-weight: 600;
            color: #450A0A;
            word-break: break-word;
        }
        
        [data-theme="dark"] .cancelled-info-box .info-text .info-title,
        [data-theme="dark"] .cancelled-info-box .info-text .info-row {
            color: #FCA5A5;
        }
        
        [data-theme="dark"] .cancelled-info-box .info-text .info-row .info-value {
            color: #FECACA;
        }
        
        .view-items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            margin-top: 10px;
        }
        
        .view-items-table thead th {
            background: #0B5ED7;
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
        
        .view-items-table tbody tr:nth-child(even) { background: #E8F0FE; }
        [data-theme="dark"] .view-items-table tbody tr:nth-child(even) { background: #1E293B; }
        
        /* DELETE MODAL */
        .delete-warning-icon {
            text-align: center;
            font-size: 3rem;
            color: #DC2626;
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
        
        .delete-modal-content .modal-header .modal-title { color: #DC2626; }
        
        .btn-confirm-delete {
            background: #DC2626;
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
        
        .btn-confirm-delete:hover { background: #991B1B; transform: translateY(-2px); }
        
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
            border-color: #DC2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
            outline: none;
        }
        
        .delete-note {
            background: #FEF3C7;
            padding: 10px 14px;
            border-radius: 8px;
            border: 2px solid #D97706;
            margin-bottom: 14px;
        }
        
        .delete-note p {
            font-size: 0.8rem;
            color: #D97706;
            font-weight: 600;
            margin: 0;
        }
        
        .delete-note p i { margin-right: 4px; }
        
        /* MESSAGE */
        .message-box {
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .message-box.success { background: #D1FAE5; color: #065F46; border-left: 5px solid #059669; }
        .message-box.error { background: #FEE2E2; color: #991B1B; border-left: 5px solid #DC2626; }
        
        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        
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
            .data-table { min-width: 1000px; font-size: 0.65rem; }
            .data-table th, .data-table td { padding: 4px 6px; }
            .view-grid { grid-template-columns: 1fr; }
            .modal-content { padding: 16px; }
            .delete-modal-content { padding: 16px; }
            .datetime { display: none; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card .stat-number { font-size: 0.9rem; }
            .stat-card { padding: 8px 10px; min-height: 55px; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- ✅ EMBEDDED HEADER - SAME AS SHARED admin_header.php -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div style="display:flex;align-items:center;gap:16px;flex:1;">
        <button id="sidebarToggle" class="lg:hidden icon-btn" style="display:none;">
            <i class="fas fa-bars" style="font-size:1.1rem;"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search" style="color:#94A3B8;margin-left:12px;"></i>
            <input type="text" id="globalSearchInput" placeholder="Search purchase history...">
            <button id="globalSearchBtn" class="search-btn">
                <i class="fas fa-search"></i> Search
            </button>
        </div>
    </div>
    
    <div style="display:flex;align-items:center;gap:12px;">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime">
            <i class="fas fa-clock"></i>
            <span id="currentDateTime"><?= date('d M Y • h:i:s A') ?></span>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn" onclick="window.location.href='notifications.php'">
            <i class="fas fa-bell" style="font-size:1.1rem;"></i>
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

    <!-- PAGE HEADER -->
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

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>" id="messageBox">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <span><?= $message ?></span>
            <button onclick="this.parentElement.style.display='none'" style="margin-left:auto;background:none;border:none;cursor:pointer;font-size:1.1rem;color:inherit;">&times;</button>
        </div>
    <?php endif; ?>

    <!-- STATS CARDS -->
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
        <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&status=CANCELLED" class="stat-card dark-red" style="text-decoration:none;">
            <span class="stat-icon"><i class="fas fa-ban"></i></span>
            <div class="stat-number"><?= $cancelled_count ?></div>
            <div class="stat-label">Cancelled</div>
            <div class="stat-sub">Click to view reasons</div>
        </a>
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

    <!-- FILTERS -->
    <div class="card animate-fade-in-up">
        <div class="filter-group">
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&status=all" class="filter-btn <?= $status_filter === 'all' ? 'active' : '' ?>">All</a>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&status=COMPLETED" class="filter-btn <?= $status_filter === 'COMPLETED' ? 'active' : '' ?>">✅ Completed</a>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&status=IN_PROGRESS" class="filter-btn <?= $status_filter === 'IN_PROGRESS' ? 'active' : '' ?>">⏳ In Progress</a>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&status=CANCELLED" class="filter-btn cancelled-filter <?= $status_filter === 'CANCELLED' ? 'active' : '' ?>">
                <i class="fas fa-ban"></i> Cancelled (<?= $cancelled_count ?>)
            </a>
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
            <input type="text" name="search" placeholder="🔍 Search invoice, creator, or cancel reason..." value="<?= htmlspecialchars($search) ?>">
            <input type="date" name="date_from" value="<?= $date_from ?>" placeholder="From">
            <input type="date" name="date_to" value="<?= $date_to ?>" placeholder="To">
            <button type="submit" class="btn-search"><i class="fas fa-search"></i> Filter</button>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
        </form>
    </div>

    <!-- PURCHASE TABLE -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue"></i> Purchase List
                <span class="result-count">(<strong><?= count($purchases) ?></strong> purchases)</span>
                <?php if ($status_filter === 'CANCELLED'): ?>
                    <span style="font-size:0.7rem;color:#DC2626;font-weight:600;margin-left:6px;">
                        <i class="fas fa-ban"></i> Showing Cancelled Purchases with Reasons
                    </span>
                <?php endif; ?>
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
                                <th class="col-reason">Cancel Reason</th>
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
                                    $status_icon = $purchase['status'] === 'COMPLETED' ? 'fa-check-circle' : ($purchase['status'] === 'IN_PROGRESS' ? 'fa-spinner fa-spin' : 'fa-ban');
                                    $has_items = ($purchase['items_count'] ?? 0) > 0;
                                    $can_edit = ($purchase['status'] === 'IN_PROGRESS');
                                    $is_cancelled = ($purchase['status'] === 'CANCELLED');
                                ?>
                                <tr style="<?= $is_cancelled ? 'background:rgba(220,38,38,0.05);' : '' ?>">
                                    <td class="col-sno"><?= $counter++ ?></td>
                                    <td class="col-invoice">
                                        <strong style="<?= $is_cancelled ? 'text-decoration:line-through;color:#DC2626;' : '' ?>">
                                            <?= htmlspecialchars($purchase['invoice_number']) ?>
                                        </strong>
                                    </td>
                                    <td class="col-type">
                                        <span class="type-badge <?= $purchase['purchase_type'] ?>">
                                            <?= ucfirst($purchase['purchase_type']) ?>
                                        </span>
                                    </td>
                                    <td class="col-creator">
                                        <i class="fas fa-user" style="color:#0B5ED7;font-size:0.65rem;"></i>
                                        <?= htmlspecialchars($purchase['creator_name'] ?? $purchase['created_by_name'] ?? 'Unknown') ?>
                                    </td>
                                    <td class="col-items"><?= number_format($purchase['items_count'] ?? 0) ?></td>
                                    <td class="col-qty"><?= number_format($purchase['total_qty'] ?? 0) ?></td>
                                    <td class="col-buying" style="color:#DC2626;font-weight:600;">
                                        TSh <?= number_format($purchase['total_buying_cost'] ?? 0) ?>
                                    </td>
                                    <td class="col-selling" style="color:#059669;font-weight:600;">
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
                                        <?php if ($is_cancelled && !empty($purchase['cancelled_at'])): ?>
                                            <div style="font-size:0.55rem;color:var(--text-muted);margin-top:3px;">
                                                <i class="fas fa-clock"></i>
                                                <?= date('d/m/Y H:i', strtotime($purchase['cancelled_at'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-reason">
                                        <?php if ($is_cancelled): ?>
                                            <div class="cancel-reason-cell">
                                                <span class="reason-label">
                                                    <i class="fas fa-info-circle"></i> Reason:
                                                </span>
                                                <?= htmlspecialchars($purchase['cancelled_reason'] ?? 'No reason provided') ?>
                                                <?php if (!empty($purchase['cancelled_by_name'])): ?>
                                                    <div style="font-size:0.6rem;margin-top:3px;color:#7F1D1D;font-style:italic;">
                                                        <i class="fas fa-user-slash"></i> By: <?= htmlspecialchars($purchase['cancelled_by_name']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="font-size:0.7rem;color:var(--text-muted);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-date">
                                        <?= date('d/m/Y H:i', strtotime($purchase['created_at'])) ?>
                                        <?php if ($purchase['status'] === 'COMPLETED' && $purchase['completed_at']): ?>
                                            <br><span style="font-size:0.55rem;color:var(--text-muted);">
                                                <i class="fas fa-check-circle" style="color:#059669;"></i>
                                                <?= date('d/m/Y H:i', strtotime($purchase['completed_at'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-actions">
                                        <div class="action-group">
                                            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&view=<?= $purchase['id'] ?>" class="action-btn view" title="View Details">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            
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
                                            
                                            <button class="action-btn delete" 
                                                    onclick="openDeleteModal(<?= $purchase['id'] ?>, '<?= addslashes($purchase['invoice_number']) ?>')" 
                                                    title="Delete Purchase">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                            
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

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span>
            Admin Purchase History
            <span>|</span>
            <strong><?= $total_purchases ?></strong> purchases · 
            <strong><?= $completed_count ?></strong> completed · 
            <strong style="color:#DC2626;"><?= $cancelled_count ?></strong> cancelled · 
            TSh <strong><?= formatMoney($total_spending) ?></strong> spent
            <span>|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- VIEW MODAL -->
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
        
        <?php if ($view_purchase['status'] === 'CANCELLED'): ?>
            <div class="cancelled-info-box">
                <i class="fas fa-ban"></i>
                <div class="info-text">
                    <div class="info-title">
                        <i class="fas fa-exclamation-triangle"></i>
                        This Purchase was Cancelled
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-comment-alt"></i> Reason:</span>
                        <span class="info-value"><?= htmlspecialchars($view_purchase['cancelled_reason'] ?? 'No reason provided') ?></span>
                    </div>
                    <?php if (!empty($view_purchase['cancelled_by_full_name'])): ?>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-user-slash"></i> Cancelled By:</span>
                            <span class="info-value"><?= htmlspecialchars($view_purchase['cancelled_by_full_name']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($view_purchase['cancelled_at'])): ?>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-clock"></i> Cancelled At:</span>
                            <span class="info-value"><?= date('d/m/Y H:i:s', strtotime($view_purchase['cancelled_at'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="view-grid">
            <div class="view-item full-width">
                <div class="label">Status</div>
                <div class="value">
                    <span class="status-badge <?= strtolower($view_purchase['status']) ?>">
                        <i class="fas <?= $view_purchase['status'] === 'COMPLETED' ? 'fa-check-circle' : ($view_purchase['status'] === 'IN_PROGRESS' ? 'fa-spinner fa-spin' : 'fa-ban') ?>"></i>
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
            <div class="view-item">
                <div class="label">Total Items</div>
                <div class="value"><?= number_format($view_purchase['total_items']) ?></div>
            </div>
            <div class="view-item">
                <div class="label">Total Quantity</div>
                <div class="value"><?= number_format($view_purchase['total_quantity']) ?> units</div>
            </div>
            <div class="view-item" style="background:#FEE2E2;border:2px solid #DC2626;">
                <div class="label" style="color:#DC2626;">Total Buying Cost</div>
                <div class="value" style="color:#DC2626;">TSh <?= number_format($view_purchase['total_buying_cost'] ?? 0) ?></div>
            </div>
            <div class="view-item" style="background:#D1FAE5;border:2px solid #059669;">
                <div class="label" style="color:#059669;">Total Selling Value</div>
                <div class="value" style="color:#059669;">TSh <?= number_format($view_purchase['total_selling_value'] ?? 0) ?></div>
            </div>
            <?php 
                $view_profit = ($view_purchase['total_selling_value'] ?? 0) - ($view_purchase['total_buying_cost'] ?? 0);
                $view_profit_class = $view_profit >= 0 ? 'profit-positive' : 'profit-negative';
            ?>
            <div class="view-item" style="background:<?= $view_profit >= 0 ? '#D1FAE5' : '#FEE2E2' ?>;border:2px solid <?= $view_profit >= 0 ? '#059669' : '#DC2626' ?>;">
                <div class="label" style="color:<?= $view_profit >= 0 ? '#059669' : '#DC2626' ?>;">Expected Profit</div>
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
                                    <td style="text-align:right;color:#DC2626;">TSh <?= number_format($item['buying_price'] ?? 0) ?></td>
                                    <td style="text-align:right;color:#DC2626;font-weight:600;">TSh <?= number_format($item['total_buying_cost'] ?? 0) ?></td>
                                    <td style="text-align:right;color:#059669;">TSh <?= number_format($item['selling_price'] ?? 0) ?></td>
                                    <td style="text-align:right;color:#059669;font-weight:600;">TSh <?= number_format($item['total_selling_value'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4"></td>
                                <td style="text-align:right;font-weight:700;color:#DC2626;border-top:2px solid #DC2626;">
                                    TSh <?= number_format($view_purchase['total_buying_cost'] ?? 0) ?>
                                </td>
                                <td></td>
                                <td style="text-align:right;font-weight:700;color:#059669;border-top:2px solid #059669;">
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
                <?php if ($view_purchase['status'] === 'CANCELLED'): ?>
                    <p style="font-size:0.75rem;color:#DC2626;margin-top:6px;">
                        <i class="fas fa-info-circle"></i> Items were deleted when purchase was cancelled
                    </p>
                <?php endif; ?>
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

<!-- DELETE MODAL -->
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
                <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:4px;">Reason for Deletion <span style="color:#DC2626;">*</span></label>
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

<script>
// ================================================================
// HEADER JAVASCRIPT
// ================================================================
(function() {
    var toggle = document.getElementById('darkModeToggle');
    var icon = document.getElementById('darkIcon');
    var text = document.getElementById('darkText');
    var html = document.documentElement;
    
    if (localStorage.getItem('darkMode') === 'true') {
        html.setAttribute('data-theme', 'dark');
        if (icon) icon.className = 'fas fa-sun';
        if (text) text.textContent = 'Light';
    }
    
    toggle?.addEventListener('click', function() {
        if (html.getAttribute('data-theme') === 'dark') {
            html.removeAttribute('data-theme');
            if (icon) icon.className = 'fas fa-moon';
            if (text) text.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
        } else {
            html.setAttribute('data-theme', 'dark');
            if (icon) icon.className = 'fas fa-sun';
            if (text) text.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });
})();

function updateClock() {
    var n = new Date();
    var d = n.toLocaleDateString('en-US', { weekday:'short', month:'short', day:'numeric', year:'numeric' });
    var tm = n.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true });
    var el = document.getElementById('currentDateTime');
    if (el) el.textContent = d + ' • ' + tm;
}
updateClock();
setInterval(updateClock, 1000);

function switchBranch(id) {
    var url = new URL(window.location.href);
    url.searchParams.set('branch', id);
    window.location.href = url.toString();
}

// ================================================================
// PURCHASE HISTORY SPECIFIC JAVASCRIPT
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

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
    }
});

setTimeout(function() {
    var messageBox = document.getElementById('messageBox');
    if (messageBox) messageBox.style.display = 'none';
}, 5000);

console.log('%c📜 Braick - Admin Purchase History', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ EMBEDDED HEADER (same as shared)', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ Uses SHARED admin_sidebar.php', 'font-size:13px; color:#34D399;');
console.log('%c✅ Total Purchases: <?= $total_purchases ?>', 'font-size:13px; color:#059669;');
console.log('%c✅ Completed: <?= $completed_count ?> | In Progress: <?= $in_progress_count ?>', 'font-size:13px; color:#D97706;');
console.log('%c❌ Cancelled: <?= $cancelled_count ?>', 'font-size:13px; color:#DC2626; font-weight:bold;');
console.log('%c💰 Total Spending: TSh <?= formatMoney($total_spending) ?>', 'font-size:13px; color:#DC2626;');
console.log('%c📈 Total Profit: TSh <?= formatMoney($total_profit) ?>', 'font-size:13px; color:<?= $total_profit >= 0 ? '#059669' : '#DC2626' ?>;');
</script>

</body>
</html>