<?php
// ================================================================
// FILE: frontend/pages/pharmacy/purchase_history.php
// PHARMACY - PURCHASE HISTORY WITH PDF VIEW & PRINT
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
// CHECK USER ACCESS (Pharmacy or Admin)
// ================================================================
$allowed_roles = ['pharmacy', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../dashboard.php');
    exit;
}

// ================================================================
// GET USER DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacist';
$user_role = $_SESSION['role'] ?? 'pharmacy';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// GET PURCHASE ID FOR VIEW
// ================================================================
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

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
// CHECK AND ADD branch_id COLUMN IF NOT EXISTS
// ================================================================
try {
    $stmt = $db->query("SHOW COLUMNS FROM purchases LIKE 'branch_id'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `purchases` ADD COLUMN `branch_id` INT NULL AFTER `created_by_name`");
        $db->exec("UPDATE `purchases` SET `branch_id` = 1 WHERE `branch_id` IS NULL");
    }
} catch (Exception $e) {}

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

// ================================================================
// GET SINGLE PURCHASE FOR VIEW (With Branch Filter)
// ================================================================
$view_purchase = null;
$view_items = [];
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG';

if ($view_id > 0) {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as creator_name 
        FROM purchases p
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.id = ? AND p.branch_id = ?
    ");
    $stmt->execute([$view_id, $user_branch_id]);
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
// GET FILTERS (Search imeondolewa hapa - iko kwenye table sasa)
// ================================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ================================================================
// BUILD QUERY (With Branch Filter)
// ================================================================
$query = "
    SELECT 
        p.*,
        u.full_name as creator_name,
        (SELECT COUNT(*) FROM purchase_items WHERE purchase_id = p.id) as items_count,
        (SELECT SUM(quantity) FROM purchase_items WHERE purchase_id = p.id) as total_qty
    FROM purchases p
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.branch_id = ?
";

$params = [$user_branch_id];

if ($status_filter !== 'all') {
    $query .= " AND p.status = ?";
    $params[] = $status_filter;
}

if ($type_filter !== 'all') {
    $query .= " AND p.purchase_type = ?";
    $params[] = $type_filter;
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
// GET STATISTICS (Filtered by Branch)
// ================================================================

// Total Purchases (Branch)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM purchases WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_purchases = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Completed Purchases (Branch)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM purchases WHERE status = 'COMPLETED' AND branch_id = ?");
$stmt->execute([$user_branch_id]);
$completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// In Progress Purchases (Branch)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM purchases WHERE status = 'IN_PROGRESS' AND branch_id = ?");
$stmt->execute([$user_branch_id]);
$in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Total Spending (Buying Cost) - Branch
$stmt = $db->prepare("SELECT COALESCE(SUM(total_buying_cost), 0) as total FROM purchases WHERE status = 'COMPLETED' AND branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_spending = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Selling Value - Branch
$stmt = $db->prepare("SELECT COALESCE(SUM(total_selling_value), 0) as total FROM purchases WHERE status = 'COMPLETED' AND branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_selling = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Profit
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
// PROFILE & LOGO
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
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
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
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
            --purple-light: #EDE9FE;
            --teal: #0D9488;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
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
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
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
        
        /* Stats Cards */
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
        
        .stat-card .stat-number {
            font-size: 1.4rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.85);
            font-weight: 500;
            margin-top: 2px;
        }
        
        .stat-card .stat-icon {
            font-size: 1.2rem;
            opacity: 0.8;
            float: right;
        }
        
        .stat-card .stat-sub {
            font-size: 0.55rem;
            color: rgba(255,255,255,0.5);
        }
        
        .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
        .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
        .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .stat-card.teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
        
        /* Card */
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
        
        /* Filters */
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
        
        .date-form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .date-form input[type="date"] {
            padding: 6px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
        }
        
        .date-form input:focus {
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
        
        /* ================================================================
           TABLE HEADER WITH SCROLL BUTTONS + SEARCH
           ================================================================ */
        .table-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 12px;
        }
        
        .table-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .table-header-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .scroll-btn-header {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-primary);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 0.75rem;
        }
        
        .scroll-btn-header:hover {
            background: var(--primary);
            border-color: var(--primary);
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
        
        .table-search-box {
            position: relative;
            min-width: 220px;
        }
        
        .table-search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.8rem;
            pointer-events: none;
        }
        
        .table-search-box input {
            width: 100%;
            padding: 8px 12px 8px 36px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
        }
        
        .table-search-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .table-search-box input::placeholder {
            color: var(--text-muted);
        }
        
        .search-results-info {
            font-size: 0.65rem;
            color: var(--text-secondary);
            padding: 4px 10px;
            background: var(--bg-body);
            border-radius: 8px;
            white-space: nowrap;
        }
        
        .search-results-info strong {
            color: var(--primary);
        }
        
        /* ================================================================
           TABLE WRAPPER
           ================================================================ */
        .table-container {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table-wrap {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 500px;
            scroll-behavior: smooth;
            position: relative;
            border-radius: 10px;
            border: 2px solid var(--border-color);
        }
        
        .table-wrap::-webkit-scrollbar {
            height: 8px;
            width: 8px;
        }
        
        .table-wrap::-webkit-scrollbar-track {
            background: var(--bg-body);
            border-radius: 4px;
        }
        
        .table-wrap::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        .table-wrap::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
        
        /* Table */
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
            padding: 10px 12px;
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
        
        .col-sno { width: 40px; text-align: center; }
        .col-invoice { min-width: 150px; }
        .col-type { min-width: 90px; }
        .col-creator { min-width: 130px; }
        .col-items { min-width: 60px; text-align: center; }
        .col-qty { min-width: 60px; text-align: center; }
        .col-buying { min-width: 130px; }
        .col-selling { min-width: 130px; }
        .col-profit { min-width: 130px; }
        .col-status { min-width: 100px; text-align: center; }
        .col-date { min-width: 150px; }
        .col-actions { min-width: 110px; text-align: center; }
        
        .status-badge {
            padding: 3px 10px;
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
        
        .action-btn {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .action-btn.view {
            background: var(--primary);
            color: white;
        }
        
        .action-btn.view:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }
        
        .action-btn.pdf {
            background: var(--danger);
            color: white;
        }
        
        .action-btn.pdf:hover {
            background: #991B1B;
            transform: scale(1.05);
        }
        
        .profit-positive {
            color: var(--success);
        }
        
        .profit-negative {
            color: var(--danger);
        }
        
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
        
        /* ================================================================
           PDF VIEW MODAL
           ================================================================ */
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
            background: white;
            border-radius: 12px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 2px solid #E2E8F0;
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
            color: #64748B;
            transition: all 0.3s ease;
        }
        
        .modal-close:hover {
            color: #DC2626;
            transform: rotate(90deg);
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            padding-top: 12px;
            border-top: 2px solid #E2E8F0;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        
        .btn-print-invoice {
            background: #0B5ED7;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-print-invoice:hover {
            background: #0A4CA8;
            transform: translateY(-2px);
        }
        
        .btn-close-modal {
            background: transparent;
            color: #64748B;
            border: 2px solid #E2E8F0;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-close-modal:hover {
            border-color: #DC2626;
            color: #DC2626;
        }
        
        @media print {
            .modal-overlay {
                position: static;
                background: white;
                padding: 0;
            }
            .modal-content {
                max-height: none;
                overflow: visible;
                padding: 10px;
                box-shadow: none;
            }
            .modal-header, .modal-actions, .btn-print-invoice, .btn-close-modal {
                display: none !important;
            }
            .invoice-container {
                padding: 0;
            }
            body {
                background: white;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .date-form { flex-direction: column; align-items: stretch; }
            .filter-group { justify-content: center; }
            .card { padding: 12px 14px; }
            .page-header-box .page-title { font-size: 1.1rem; }
            .stat-card .stat-number { font-size: 1.1rem; }
            .stat-card { padding: 10px 12px; min-height: 65px; }
            .header-actions { flex-direction: column; align-items: stretch; width: 100%; }
            .header-actions .btn-back { width: 100%; justify-content: center; }
            .data-table { min-width: 900px; font-size: 0.65rem; }
            .data-table th, .data-table td { padding: 5px 7px; }
            .table-header-bar { flex-direction: column; align-items: stretch; }
            .table-search-box { min-width: 100%; }
            .table-header-right { width: 100%; }
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
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
                <span class="branch-name-display">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </h1>
        </div>
        <div class="header-actions">
            <a href="inventory.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
            <a href="purchases.php" class="btn-back">
                <i class="fas fa-shopping-cart"></i> New Purchase
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS (Branch Filtered) -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-card blue">
            <span class="stat-icon"><i class="fas fa-shopping-cart"></i></span>
            <div class="stat-number"><?= $total_purchases ?></div>
            <div class="stat-label">Total Purchases</div>
            <div class="stat-sub"><?= htmlspecialchars($user_branch_name) ?> branch</div>
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
    <!-- FILTERS (Search imeondolewa - iko kwenye table sasa) -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="filter-group">
            <a href="purchase_history.php?status=all" class="filter-btn <?= $status_filter === 'all' ? 'active' : '' ?>">All</a>
            <a href="purchase_history.php?status=COMPLETED" class="filter-btn <?= $status_filter === 'COMPLETED' ? 'active' : '' ?>">Completed</a>
            <a href="purchase_history.php?status=IN_PROGRESS" class="filter-btn <?= $status_filter === 'IN_PROGRESS' ? 'active' : '' ?>">In Progress</a>
            <a href="purchase_history.php?type=medicine" class="filter-btn <?= $type_filter === 'medicine' ? 'active' : '' ?>">💊 Medicine</a>
            <a href="purchase_history.php?type=equipment" class="filter-btn <?= $type_filter === 'equipment' ? 'active' : '' ?>">🔧 Equipment</a>
            <?php if ($status_filter !== 'all' || $type_filter !== 'all' || !empty($date_from) || !empty($date_to)): ?>
                <a href="purchase_history.php" class="filter-btn clear-filter">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </div>
        
        <form method="GET" class="date-form">
            <input type="hidden" name="status" value="<?= $status_filter ?>">
            <input type="hidden" name="type" value="<?= $type_filter ?>">
            <input type="date" name="date_from" value="<?= $date_from ?>" placeholder="From">
            <input type="date" name="date_to" value="<?= $date_to ?>" placeholder="To">
            <button type="submit" class="btn-search"><i class="fas fa-filter"></i> Filter by Date</button>
            <a href="purchase_history.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- PURCHASE TABLE (Branch Filtered + Scroll Buttons + Auto Search) -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        
        <!-- TABLE HEADER BAR: Title + Scroll Buttons + Search -->
        <div class="table-header-bar">
            <div class="table-header-left">
                <h3 class="card-title">
                    <i class="fas fa-list title-blue"></i> Purchase List
                    <span class="result-count" id="purchaseCountDisplay">(<strong><?= count($purchases) ?></strong> purchases)</span>
                </h3>
                <span style="font-size:0.65rem;font-weight:400;color:var(--text-secondary);">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </div>
            
            <div class="table-header-right">
                <!-- Scroll Buttons < > -->
                <button type="button" class="scroll-btn-header" id="scrollBtnLeft" onclick="scrollTableLeft()" title="Sogeza Kushoto">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="scroll-btn-header" id="scrollBtnRight" onclick="scrollTableRight()" title="Sogeza Kulia">
                    <i class="fas fa-chevron-right"></i>
                </button>
                
                <!-- Auto Search Box -->
                <div class="table-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="tableSearchInput" placeholder="Search invoice, creator, status..." autocomplete="off">
                </div>
                
                <span class="search-results-info" id="searchResultsInfo" style="display:none;">
                    <i class="fas fa-filter"></i> <strong id="searchMatchCount">0</strong> match
                </span>
            </div>
        </div>
        
        <?php if (count($purchases) > 0): ?>
            <div class="table-container">
                <div class="table-wrap" id="purchaseTableWrap">
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
                        <tbody id="purchaseTableBody">
                            <?php $counter = 1; ?>
                            <?php foreach ($purchases as $purchase): ?>
                                <?php 
                                    $profit = ($purchase['total_selling_value'] ?? 0) - ($purchase['total_buying_cost'] ?? 0);
                                    $profit_class = $profit >= 0 ? 'profit-positive' : 'profit-negative';
                                    $status_class = strtolower($purchase['status']);
                                    $status_icon = $purchase['status'] === 'COMPLETED' ? 'fa-check-circle' : 'fa-spinner fa-spin';
                                    $has_items = ($purchase['items_count'] ?? 0) > 0;
                                    
                                    // Search data (lowercase for filtering)
                                    $search_data = strtolower(
                                        $purchase['invoice_number'] . ' ' .
                                        $purchase['purchase_type'] . ' ' .
                                        ($purchase['creator_name'] ?? $purchase['created_by_name'] ?? '') . ' ' .
                                        $purchase['status'] . ' ' .
                                        date('d/m/Y H:i', strtotime($purchase['created_at']))
                                    );
                                ?>
                                <tr class="purchase-row" data-search="<?= htmlspecialchars($search_data) ?>">
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
                                        <?php if ($purchase['status'] === 'COMPLETED' && $has_items): ?>
                                            <button onclick="openPDFView(<?= $purchase['id'] ?>)" class="action-btn pdf" title="View Invoice PDF">
                                                <i class="fas fa-file-pdf"></i> PDF
                                            </button>
                                        <?php endif; ?>
                                        <a href="purchases.php?id=<?= $purchase['id'] ?>&type=<?= $purchase['purchase_type'] ?>" class="action-btn view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <!-- No Results Row (hidden by default) -->
                            <tr class="no-results-row" id="noResultsRow" style="display:none;">
                                <td colspan="12">
                                    <i class="fas fa-search-minus"></i>
                                    <p>No purchases match your search</p>
                                    <p style="font-size:0.75rem;margin-top:4px;">Try different keywords</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <p>No purchases found for <?= htmlspecialchars($user_branch_name) ?></p>
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
            Purchase History
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
<!-- PDF VIEW MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="pdfModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-file-pdf" style="color:#DC2626;"></i> Purchase Invoice
            </div>
            <button class="modal-close" onclick="closePDF()">&times;</button>
        </div>
        
        <!-- Invoice Content -->
        <div id="pdfInvoiceContent" style="background:white;padding:10px;">
            <div style="text-align:center;padding:30px;color:#64748B;">
                <i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i>
                <p>Loading invoice...</p>
            </div>
        </div>
        
        <div class="modal-actions">
            <button class="btn-print-invoice" onclick="printPDFInvoice()">
                <i class="fas fa-print"></i> Print Invoice
            </button>
            <button class="btn-close-modal" onclick="closePDF()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
// ================================================================
// TABLE SCROLL FUNCTIONS (◄ ►)
// ================================================================
var scrollAmount = 400;

function scrollTableLeft() {
    var wrap = document.getElementById('purchaseTableWrap');
    if (wrap) {
        wrap.scrollBy({
            left: -scrollAmount,
            behavior: 'smooth'
        });
    }
}

function scrollTableRight() {
    var wrap = document.getElementById('purchaseTableWrap');
    if (wrap) {
        wrap.scrollBy({
            left: scrollAmount,
            behavior: 'smooth'
        });
    }
}

// ================================================================
// UPDATE SCROLL BUTTONS STATE
// ================================================================
function updateScrollButtons() {
    var wrap = document.getElementById('purchaseTableWrap');
    var btnLeft = document.getElementById('scrollBtnLeft');
    var btnRight = document.getElementById('scrollBtnRight');
    
    if (!wrap || !btnLeft || !btnRight) return;
    
    var scrollLeft = wrap.scrollLeft;
    var maxScroll = wrap.scrollWidth - wrap.clientWidth;
    
    // Disable left button kama tuko mwanzo
    if (scrollLeft <= 5) {
        btnLeft.disabled = true;
    } else {
        btnLeft.disabled = false;
    }
    
    // Disable right button kama tuko mwisho
    if (scrollLeft >= maxScroll - 5 || maxScroll <= 0) {
        btnRight.disabled = true;
    } else {
        btnRight.disabled = false;
    }
}

// ================================================================
// AUTO SEARCH FILTER (Inafilter automatic unapoandika)
// ================================================================
(function() {
    'use strict';
    
    var searchInput = document.getElementById('tableSearchInput');
    var tableBody = document.getElementById('purchaseTableBody');
    var noResultsRow = document.getElementById('noResultsRow');
    var countDisplay = document.getElementById('purchaseCountDisplay');
    var searchInfo = document.getElementById('searchResultsInfo');
    var searchMatchCount = document.getElementById('searchMatchCount');
    
    if (!searchInput || !tableBody) return;
    
    var totalRows = document.querySelectorAll('.purchase-row').length;
    
    function filterTable() {
        var query = searchInput.value.toLowerCase().trim();
        var rows = document.querySelectorAll('.purchase-row');
        var visibleCount = 0;
        
        rows.forEach(function(row) {
            var searchData = row.getAttribute('data-search') || '';
            
            if (query === '' || searchData.includes(query)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Update counter display
        if (countDisplay) {
            if (query === '') {
                countDisplay.innerHTML = '(<strong>' + totalRows + '</strong> purchases)';
            } else {
                countDisplay.innerHTML = '(<strong>' + visibleCount + '</strong> of ' + totalRows + ')';
            }
        }
        
        // Show/hide search info badge
        if (searchInfo && searchMatchCount) {
            if (query === '') {
                searchInfo.style.display = 'none';
            } else {
                searchInfo.style.display = 'inline-flex';
                searchMatchCount.textContent = visibleCount;
            }
        }
        
        // Show/hide "No results" row
        if (noResultsRow) {
            if (visibleCount === 0 && query !== '') {
                noResultsRow.style.display = '';
            } else {
                noResultsRow.style.display = 'none';
            }
        }
        
        // Update scroll buttons baada ya filter
        setTimeout(updateScrollButtons, 100);
    }
    
    // Listen kwa kila input (automatic filtering)
    searchInput.addEventListener('input', filterTable);
    
    // Clear kwa Escape key
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
            filterTable();
            this.blur();
        }
    });
})();

// ================================================================
// INITIALIZE SCROLL
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    var wrap = document.getElementById('purchaseTableWrap');
    
    if (wrap) {
        wrap.addEventListener('scroll', updateScrollButtons);
        window.addEventListener('resize', updateScrollButtons);
        
        // Initial update
        setTimeout(updateScrollButtons, 200);
    }
});

// ================================================================
// OPEN PDF VIEW - Loads invoice via AJAX
// ================================================================
function openPDFView(purchaseId) {
    var modal = document.getElementById('pdfModal');
    var content = document.getElementById('pdfInvoiceContent');
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    content.innerHTML = `
        <div style="text-align:center;padding:30px;color:#64748B;">
            <i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i>
            <p>Loading invoice...</p>
        </div>
    `;
    
    fetch('get_invoice.php?id=' + purchaseId)
        .then(function(response) {
            return response.text();
        })
        .then(function(html) {
            content.innerHTML = html;
        })
        .catch(function(error) {
            content.innerHTML = `
                <div style="text-align:center;padding:30px;color:#DC2626;">
                    <i class="fas fa-exclamation-circle" style="font-size:2rem;"></i>
                    <p>Error loading invoice: ${error.message}</p>
                </div>
            `;
        });
}

// ================================================================
// CLOSE PDF MODAL
// ================================================================
function closePDF() {
    var modal = document.getElementById('pdfModal');
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';
}

// ================================================================
// PRINT PDF INVOICE
// ================================================================
function printPDFInvoice() {
    var content = document.getElementById('pdfInvoiceContent');
    if (!content) return;
    
    var printContents = content.innerHTML;
    var originalTitle = document.title;
    document.title = 'Invoice - Braick Dispensary';
    
    var win = window.open('', '_blank', 'width=900,height=700');
    if (!win) {
        var originalContents = document.body.innerHTML;
        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;
        document.title = originalTitle;
        return;
    }
    
    win.document.write('<!DOCTYPE html><html><head><title>Invoice - Braick Dispensary</title>');
    win.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">');
    win.document.write('<style>');
    win.document.write(`
        body { font-family: Arial, sans-serif; background: white; padding: 20px; margin: 0; }
        .invoice-container { max-width: 800px; margin: 0 auto; }
        .invoice-header { text-align: center; border-bottom: 3px solid #0B5ED7; padding-bottom: 15px; margin-bottom: 20px; }
        .invoice-header .logo { max-height: 70px; width: auto; display: block; margin: 0 auto 10px; }
        .invoice-header h1 { font-size: 24px; color: #0B5ED7; margin: 0; }
        .invoice-header p { font-size: 12px; color: #64748B; margin: 2px 0; }
        .invoice-title { text-align: center; margin-bottom: 20px; }
        .invoice-title h2 { font-size: 20px; color: #0B5ED7; margin: 0; }
        .invoice-title p { font-size: 12px; color: #64748B; margin: 2px 0; }
        .invoice-info { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 12px; background: #F8FAFC; border-radius: 8px; border: 1px solid #E2E8F0; margin-bottom: 20px; }
        .invoice-info .label { font-size: 11px; color: #64748B; margin: 0; font-weight: 600; }
        .invoice-info .value { font-size: 14px; font-weight: 600; margin: 2px 0; }
        .invoice-info .text-right { text-align: right; }
        .invoice-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px; }
        .invoice-table thead th { background: #0B5ED7; color: white; padding: 8px 10px; text-align: left; border: 1px solid #0B5ED7; }
        .invoice-table thead th.text-center { text-align: center; }
        .invoice-table thead th.text-right { text-align: right; }
        .invoice-table tbody td { padding: 6px 10px; border: 1px solid #E2E8F0; }
        .invoice-table tbody td.text-center { text-align: center; }
        .invoice-table tbody td.text-right { text-align: right; }
        .invoice-table tbody td .batch-info { font-size: 11px; color: #64748B; }
        .invoice-table tbody tr:nth-child(even) { background: #F8FAFC; }
        .invoice-table tfoot td { padding: 8px 10px; border-top: 2px solid #0B5ED7; font-weight: 700; }
        .invoice-summary { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; padding: 12px; background: #F8FAFC; border-radius: 8px; border: 1px solid #E2E8F0; margin-bottom: 20px; }
        .invoice-summary .label { font-size: 11px; color: #64748B; margin: 0; font-weight: 600; }
        .invoice-summary .value { font-size: 18px; font-weight: 700; margin: 2px 0; }
        .invoice-summary .text-right { text-align: right; }
        .invoice-added-by { font-size: 11px; color: #64748B; border-top: 1px solid #E2E8F0; padding-top: 10px; margin-top: 10px; }
        .invoice-footer { text-align: center; border-top: 2px solid #0B5ED7; padding-top: 12px; margin-top: 15px; }
        .invoice-footer p { font-size: 11px; color: #64748B; margin: 0; }
        .invoice-footer .thank-you { font-size: 10px; color: #94A3B8; margin: 2px 0; }
        @media print { body { padding: 10px; } }
        @media (max-width: 600px) { .invoice-info { grid-template-columns: 1fr; } .invoice-summary { grid-template-columns: 1fr; } }
    `);
    win.document.write('</style>');
    win.document.write('</head><body>');
    win.document.write('<div class="invoice-container">');
    win.document.write(printContents);
    win.document.write('</div>');
    win.document.write('</body></html>');
    win.document.close();
    
    setTimeout(function() {
        win.focus();
        win.print();
        win.close();
    }, 500);
}

// ================================================================
// CLOSE MODAL ON ESCAPE
// ================================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePDF();
    }
});

// ================================================================
// CLOSE MODAL ON OUTSIDE CLICK
// ================================================================
document.getElementById('pdfModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePDF();
    }
});

// ================================================================
// CONSOLE LOG
// ================================================================
console.log('%c📜 Braick - Purchase History', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Branch Filter: <?= htmlspecialchars($user_branch_name) ?> (ID: <?= $user_branch_id ?>)', 'font-size:13px; color:#7C3AED;');
console.log('%c◄ ► Scroll Buttons: Juu kwenye header ya table', 'font-size:13px; color:#0B5ED7;');
console.log('%c🔍 Auto Search: Andika tu kwenye search box, inafilter automatic', 'font-size:13px; color:#059669;');
console.log('%c✅ Total Purchases: <?= $total_purchases ?>', 'font-size:13px; color:#059669;');
console.log('%c✅ Completed: <?= $completed_count ?> | In Progress: <?= $in_progress_count ?>', 'font-size:13px; color:#D97706;');
console.log('%c💰 Total Spending: TSh <?= formatMoney($total_spending) ?>', 'font-size:13px; color:#DC2626;');
console.log('%c📈 Total Profit: TSh <?= formatMoney($total_profit) ?>', 'font-size:13px; color:<?= $total_profit >= 0 ? '#059669' : '#DC2626' ?>;');
</script>

</body>
</html>