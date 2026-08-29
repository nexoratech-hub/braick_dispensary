<?php
// ================================================================
// FILE: frontend/pages/pharmacy/otc_history.php
// PHARMACY - OTC SALE HISTORY
// View all OTC sales with details
// ✅ Auto-dismiss messages after 5 seconds
// ✅ Click to dismiss immediately
// ✅ Session auto-clear after 30 seconds
// ✅ Reduced table width (Items column smaller)
// ✅ Dark Mode support
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT PHARMACY
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$profile_pic = $_SESSION['profile_pic'] ?? '';

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
// CHECK FOR SESSION MESSAGES WITH AUTO-DISMISS
// ================================================================
$message = '';
$message_type = '';
$show_message = false;
$auto_dismiss = isset($_GET['auto_dismiss']) ? true : false;

if (isset($_SESSION['otc_sale_message'])) {
    $message = $_SESSION['otc_sale_message'];
    $message_type = $_SESSION['otc_sale_message_type'] ?? 'success';
    $show_message = true;
    
    // Auto clear after 30 seconds (in case user stays on page)
    $message_time = $_SESSION['otc_sale_message_time'] ?? 0;
    if ($message_time > 0 && (time() - $message_time) > 30) {
        unset($_SESSION['otc_sale_message']);
        unset($_SESSION['otc_sale_message_type']);
        unset($_SESSION['otc_sale_message_time']);
        $show_message = false;
    }
}

// ================================================================
// GET FILTERS FROM URL
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$payment_status = isset($_GET['payment_status']) ? trim($_GET['payment_status']) : '';

// ================================================================
// GET OTC SALES WITH ITEMS
// ================================================================
$query = "
    SELECT 
        os.id as sale_id,
        os.sale_number,
        os.customer_name,
        os.customer_phone,
        os.patient_id,
        os.subtotal,
        os.discount_amount,
        os.total_amount as grand_total,
        os.payment_method,
        os.payment_status,
        os.sold_by,
        os.branch_id,
        os.notes,
        os.created_at,
        os.updated_at,
        os.bill_id,
        GROUP_CONCAT(
            CONCAT(
                oi.item_name,
                ' (Qty: ', oi.quantity,
                ', TSh ', FORMAT(oi.unit_price, 0),
                ', Total: TSh ', FORMAT(oi.total_price, 0)
            )
            SEPARATOR ' | '
        ) as items_list,
        COUNT(oi.id) as items_count,
        SUM(oi.quantity) as total_items_quantity,
        u.full_name as sold_by_name,
        b.status as bill_status
    FROM otc_sales os
    LEFT JOIN otc_sale_items oi ON os.id = oi.sale_id
    LEFT JOIN users u ON os.sold_by = u.id
    LEFT JOIN bills b ON os.bill_id = b.id
    WHERE os.branch_id = ?
";

$params = [$user_branch_id];

if (!empty($search)) {
    $query .= " AND (os.sale_number LIKE ? OR os.customer_name LIKE ? OR oi.item_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($date_from)) {
    $query .= " AND DATE(os.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(os.created_at) <= ?";
    $params[] = $date_to;
}

if (!empty($payment_status)) {
    $query .= " AND os.payment_status = ?";
    $params[] = $payment_status;
}

$query .= " GROUP BY os.id ORDER BY os.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================

// Total sales count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM otc_sales WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_sales = $stmt->fetch()['count'] ?? 0;

// Total revenue
$stmt = $db->prepare("SELECT SUM(total_amount) as total FROM otc_sales WHERE branch_id = ? AND payment_status = 'paid'");
$stmt->execute([$user_branch_id]);
$total_revenue = $stmt->fetch()['total'] ?? 0;

// Pending payments
$stmt = $db->prepare("SELECT COUNT(*) as count FROM otc_sales WHERE branch_id = ? AND payment_status = 'pending'");
$stmt->execute([$user_branch_id]);
$pending_count = $stmt->fetch()['count'] ?? 0;

// Today's sales
$stmt = $db->prepare("SELECT COUNT(*) as count, SUM(total_amount) as total FROM otc_sales WHERE branch_id = ? AND DATE(created_at) = CURDATE() AND payment_status = 'paid'");
$stmt->execute([$user_branch_id]);
$today_data = $stmt->fetch(PDO::FETCH_ASSOC);
$today_count = $today_data['count'] ?? 0;
$today_revenue = $today_data['total'] ?? 0;

// ================================================================
// GET SIDEBAR STATS
// ================================================================
$low_stock_count = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND quantity <= reorder_level AND quantity > 0 AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_count = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $low_stock_count = 0;
}

$pending_prescriptions = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_prescriptions = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// DARK MODE - Check cookie
// ================================================================
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= $dark_mode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTC History - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A3D8A;
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
            --gold: #F59E0B;
            --gold-light: #FEF3C7;
            
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --border-color: #E2E8F0;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-muted: #94A3B8;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        /* ================================================================
           DARK MODE VARIABLES
           ================================================================ */
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --border-color: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.4);
            --primary-light: #1E3A5F;
            --success-light: #1A3A2A;
            --warning-light: #3D2E0A;
            --danger-light: #3A1A1A;
            --purple-light: #2A1A3A;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
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
            background: linear-gradient(135deg, #7C3AED, #6D28D9);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 24px;
            box-shadow: 0 8px 32px rgba(124, 58, 237, 0.25);
            position: relative;
            overflow: hidden;
            color: white;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
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
        
        .page-header .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 1.8rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 4px;
        }
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .page-header .stat-chip {
            background: rgba(255,255,255,0.12);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            color: rgba(255,255,255,0.9);
            border: 1px solid rgba(255,255,255,0.1);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 10px;
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
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================
           STATS CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            border-radius: 12px;
            padding: 14px 16px;
            transition: all 0.3s ease;
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            min-height: 75px;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.85);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .stat-card .stat-icon {
            font-size: 1.2rem;
            opacity: 0.8;
            float: right;
        }
        
        .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
        .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
        .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        
        /* ================================================================
           MESSAGE BOX WITH AUTO-DISMISS
           ================================================================ */
        .message-box {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideDown 0.4s ease;
            position: relative;
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        
        .message-box.hide {
            opacity: 0;
            transform: translateY(-20px);
            display: none;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message-box.success {
            background: var(--success-light);
            color: #065F46;
            border: 2px solid #6EE7B7;
        }
        
        .message-box.error {
            background: var(--danger-light);
            color: #991B1B;
            border: 2px solid #FCA5A5;
        }
        
        .message-box.info {
            background: var(--primary-light);
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        
        .message-box i {
            font-size: 1.3rem;
        }
        
        .message-box .message-close {
            margin-left: auto;
            cursor: pointer;
            font-size: 1.2rem;
            font-weight: 700;
            opacity: 0.6;
            transition: opacity 0.3s ease;
            padding: 0 4px;
        }
        
        .message-box .message-close:hover {
            opacity: 1;
        }
        
        .message-box .message-timer {
            font-size: 0.6rem;
            opacity: 0.6;
            margin-left: 8px;
        }
        
        [data-theme="dark"] .message-box.success {
            background: #1A3A2A;
            color: #34D399;
            border-color: #34D399;
        }
        
        [data-theme="dark"] .message-box.error {
            background: #3A1A1A;
            color: #F87171;
            border-color: #F87171;
        }
        
        [data-theme="dark"] .message-box.info {
            background: #1E3A5F;
            color: #6EA8FE;
            border-color: #6EA8FE;
        }
        
        /* ================================================================
           CARD
           ================================================================ */
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
        
        .card-title i {
            color: var(--primary);
        }
        
        .result-count {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .result-count strong {
            color: var(--primary);
        }
        
        /* ================================================================
           FILTERS
           ================================================================ */
        .filter-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-form .form-group {
            flex: 1;
            min-width: 140px;
        }
        
        .filter-form .form-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            display: block;
            margin-bottom: 2px;
        }
        
        .filter-form .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
        }
        
        .filter-form .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        [data-theme="dark"] .filter-form .form-control {
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        [data-theme="dark"] .filter-form .form-control option {
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .btn-search {
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            border: none;
            background: var(--primary);
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 42px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-reset {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 42px;
        }
        
        .btn-reset:hover {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        /* ================================================================
           TABLE - REDUCED WIDTH
           ================================================================ */
        .table-wrap {
            overflow-x: auto;
        }
        
        .table-wrap::-webkit-scrollbar {
            height: 6px;
            width: 6px;
        }
        
        .table-wrap::-webkit-scrollbar-track {
            background: var(--bg-body);
            border-radius: 4px;
        }
        
        .table-wrap::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        .data-table {
            width: 100%;
            min-width: 800px;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.75rem;
        }
        
        .data-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--primary);
            color: white;
            padding: 6px 10px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            white-space: nowrap;
            text-align: left;
        }
        
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        
        .data-table thead th.center { text-align: center; }
        .data-table thead th.right { text-align: right; }
        
        .data-table tbody tr:nth-child(even) {
            background: var(--primary-light);
        }
        
        .data-table tbody tr:hover td {
            background: var(--success-light);
        }
        
        .data-table td {
            padding: 6px 10px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table td.center { text-align: center; }
        .data-table td.right { text-align: right; }
        
        /* ================================================================
           COLUMN WIDTHS - REDUCED
           ================================================================ */
        .col-sno { width: 30px; text-align: center; }
        .col-sale { width: 100px; }
        .col-customer { width: 130px; }
        .col-items { width: 140px; }  /* REDUCED */
        .col-total { width: 85px; text-align: right; }
        .col-payment { width: 80px; }
        .col-status { width: 80px; text-align: center; }
        .col-date { width: 100px; }
        .col-actions { width: 80px; text-align: center; }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge-status {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.55rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .badge-success { background: var(--success-light); color: var(--success); border: 1px solid var(--success); }
        .badge-warning { background: var(--warning-light); color: var(--warning); border: 1px solid var(--warning); }
        .badge-danger { background: var(--danger-light); color: var(--danger); border: 1px solid var(--danger); }
        .badge-info { background: var(--primary-light); color: var(--primary); border: 1px solid var(--primary); }
        .badge-purple { background: var(--purple-light); color: var(--purple); border: 1px solid var(--purple); }
        
        [data-theme="dark"] .badge-success {
            background: #1A3A2A;
            color: #34D399;
            border-color: #34D399;
        }
        [data-theme="dark"] .badge-warning {
            background: #3D2E0A;
            color: #FBBF24;
            border-color: #FBBF24;
        }
        [data-theme="dark"] .badge-danger {
            background: #3A1A1A;
            color: #F87171;
            border-color: #F87171;
        }
        [data-theme="dark"] .badge-info {
            background: #1E3A5F;
            color: #6EA8FE;
            border-color: #6EA8FE;
        }
        [data-theme="dark"] .badge-purple {
            background: #2A1A3A;
            color: #A78BFA;
            border-color: #A78BFA;
        }
        
        .action-btn {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.6rem;
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
        
        .action-btn.print {
            background: var(--purple);
            color: white;
        }
        
        .action-btn.print:hover {
            background: #6D28D9;
            transform: scale(1.05);
        }
        
        [data-theme="dark"] .action-btn.view {
            background: #2563EB;
        }
        [data-theme="dark"] .action-btn.view:hover {
            background: #1D4ED8;
        }
        [data-theme="dark"] .action-btn.print {
            background: #7C3AED;
        }
        [data-theme="dark"] .action-btn.print:hover {
            background: #6D28D9;
        }
        
        /* ================================================================
           BATCH NUMBER
           ================================================================ */
        .batch-number {
            font-family: 'Courier New', monospace;
            font-size: 0.7rem;
            font-weight: 600;
            background: var(--primary-light);
            padding: 2px 8px;
            border-radius: 4px;
            color: var(--primary);
            display: inline-block;
        }
        
        [data-theme="dark"] .batch-number {
            background: #1E3A5F;
            color: #60A5FA;
        }
        
        /* ================================================================
           ITEMS LIST - COMPACT
           ================================================================ */
        .items-list-compact {
            font-size: 0.6rem;
            color: var(--text-secondary);
            max-width: 130px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .items-list-compact .item-count {
            font-weight: 600;
            color: var(--primary);
        }
        
        [data-theme="dark"] .items-list-compact .item-count {
            color: #60A5FA;
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
            display: block;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            font-size: 0.9rem;
        }
        
        .empty-state .sub {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .card { padding: 12px 14px; }
            .filter-form { flex-direction: column; align-items: stretch; }
            .filter-form .form-group { min-width: 100%; }
            .stat-card .stat-number { font-size: 1.1rem; }
            .stat-card { padding: 10px 12px; min-height: 60px; }
            .data-table { min-width: 650px; font-size: 0.65rem; }
            .data-table th, .data-table td { padding: 4px 6px; }
            .col-items { width: 100px; }
            .col-customer { width: 100px; }
            .col-date { width: 80px; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card .stat-number { font-size: 0.9rem; }
            .stat-card { padding: 6px 8px; min-height: 50px; }
            .stat-card .stat-icon { font-size: 0.8rem; }
            .data-table { min-width: 550px; font-size: 0.55rem; }
            .data-table th, .data-table td { padding: 3px 4px; }
            .page-header .page-title { font-size: 1rem; }
            .page-header .btn-outline-light { font-size: 0.7rem; padding: 4px 10px; }
            .col-items { width: 80px; }
            .col-sale { width: 80px; }
            .col-customer { width: 80px; }
        }
        
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
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
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
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
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-history"></i>
                OTC Sale History
            </h1>
            <p class="page-subtitle">
                View all over-the-counter sales
                <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                <span class="stat-chip">
                    <i class="fas fa-shopping-cart"></i> <?= $total_sales ?> total sales
                </span>
                <span class="stat-chip">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_revenue) ?> revenue
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="new_otc_sale.php" class="btn-outline-light">
                <i class="fas fa-plus-circle"></i> New OTC Sale
            </a>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE WITH AUTO-DISMISS -->
    <!-- ================================================================ -->
    <?php if ($show_message): ?>
        <div class="message-box <?= $message_type ?>" id="messageBox">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle') ?>"></i>
            <?= htmlspecialchars($message) ?>
            <span class="message-timer">(auto-dismiss in 5s)</span>
            <span class="message-close" onclick="dismissMessage()">&times;</span>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <span class="stat-icon"><i class="fas fa-shopping-cart"></i></span>
            <div class="stat-number"><?= number_format($total_sales) ?></div>
            <div class="stat-label">Total Sales</div>
        </div>
        <div class="stat-card green">
            <span class="stat-icon"><i class="fas fa-money-bill-wave"></i></span>
            <div class="stat-number">TSh <?= number_format($total_revenue) ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
        <div class="stat-card orange">
            <span class="stat-icon"><i class="fas fa-clock"></i></span>
            <div class="stat-number"><?= number_format($pending_count) ?></div>
            <div class="stat-label">Pending Payments</div>
        </div>
        <div class="stat-card purple">
            <span class="stat-icon"><i class="fas fa-calendar-day"></i></span>
            <div class="stat-number"><?= number_format($today_count) ?></div>
            <div class="stat-label">Today's Sales</div>
        </div>
        <div class="stat-card red">
            <span class="stat-icon"><i class="fas fa-coins"></i></span>
            <div class="stat-number">TSh <?= number_format($today_revenue) ?></div>
            <div class="stat-label">Today's Revenue</div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card">
        <form method="GET" action="" class="filter-form">
            <div class="form-group">
                <label>Search</label>
                <input type="text" name="search" class="form-control" 
                       placeholder="Sale #, Customer, Item..." 
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="form-group">
                <label>Date From</label>
                <input type="date" name="date_from" class="form-control" 
                       value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="form-group">
                <label>Date To</label>
                <input type="date" name="date_to" class="form-control" 
                       value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="form-group">
                <label>Payment Status</label>
                <select name="payment_status" class="form-control">
                    <option value="">All</option>
                    <option value="paid" <?= $payment_status === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="pending" <?= $payment_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="partial" <?= $payment_status === 'partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="cancelled" <?= $payment_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="otc_history.php" class="btn-reset">
                    <i class="fas fa-times"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- OTC SALES TABLE - REDUCED WIDTH -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list"></i>
                OTC Sales
                <span class="result-count">(<strong><?= count($sales) ?></strong> records)</span>
            </h3>
        </div>
        
        <?php if (count($sales) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="col-sno">#</th>
                            <th class="col-sale">Sale #</th>
                            <th class="col-customer">Customer</th>
                            <th class="col-items">Items</th>
                            <th class="col-total">Total</th>
                            <th class="col-payment">Payment</th>
                            <th class="col-status">Status</th>
                            <th class="col-date">Date</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach ($sales as $sale): ?>
                            <?php
                                $status_class = 'badge-warning';
                                if ($sale['payment_status'] === 'paid') {
                                    $status_class = 'badge-success';
                                } elseif ($sale['payment_status'] === 'cancelled') {
                                    $status_class = 'badge-danger';
                                } elseif ($sale['payment_status'] === 'partial') {
                                    $status_class = 'badge-warning';
                                }
                                
                                $items_count = $sale['items_count'] ?? 0;
                                $items_list = $sale['items_list'] ?? '';
                                $grand_total = $sale['grand_total'] ?? 0;
                                
                                // Shorten items list for display
                                $short_items = $items_list;
                                if (strlen($short_items) > 60) {
                                    $short_items = substr($short_items, 0, 60) . '...';
                                }
                            ?>
                            <tr>
                                <td class="col-sno"><?= $counter++ ?></td>
                                <td class="col-sale">
                                    <span class="batch-number">
                                        <?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="col-customer">
                                    <strong><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></strong>
                                    <?php if (!empty($sale['customer_phone'])): ?>
                                        <div style="font-size:0.55rem;color:var(--text-muted);">
                                            <i class="fas fa-phone"></i> <?= htmlspecialchars($sale['customer_phone']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="col-items">
                                    <div class="items-list-compact">
                                        <span class="item-count"><?= $items_count ?></span> item(s)
                                        <?php if (!empty($items_list)): ?>
                                            <div style="font-size:0.55rem;color:var(--text-muted);max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                                <?= htmlspecialchars($short_items) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="col-total">
                                    <strong style="color:var(--primary);font-size:0.75rem;">TSh <?= number_format($grand_total) ?></strong>
                                </td>
                                <td class="col-payment">
                                    <span style="font-size:0.65rem;">
                                        <?= ucfirst(str_replace('_', ' ', $sale['payment_method'] ?? 'N/A')) ?>
                                    </span>
                                </td>
                                <td class="col-status">
                                    <span class="badge-status <?= $status_class ?>">
                                        <?= ucfirst($sale['payment_status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="col-date">
                                    <div style="font-size:0.65rem;">
                                        <?= date('d/m/Y', strtotime($sale['created_at'] ?? 'now')) ?>
                                    </div>
                                    <div style="font-size:0.55rem;color:var(--text-muted);">
                                        <?= date('H:i', strtotime($sale['created_at'] ?? 'now')) ?>
                                    </div>
                                </td>
                                <td class="col-actions">
                                    <a href="view_otc_sale.php?id=<?= $sale['sale_id'] ?>" class="action-btn view" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="print_otc_receipt.php?id=<?= $sale['sale_id'] ?>" class="action-btn print" title="Print Receipt" target="_blank">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <p>No OTC sales found</p>
                <p class="sub">Start selling by clicking "New OTC Sale"</p>
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
            OTC Sale History
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // AUTO-DISMISS MESSAGES AFTER 5 SECONDS
    // ================================================================
    function dismissMessage() {
        var messageBox = document.getElementById('messageBox');
        if (messageBox) {
            messageBox.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            messageBox.style.opacity = '0';
            messageBox.style.transform = 'translateY(-20px)';
            setTimeout(function() {
                messageBox.style.display = 'none';
            }, 500);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var messageBox = document.getElementById('messageBox');
        if (messageBox) {
            // Auto dismiss after 5 seconds
            var dismissTimer = setTimeout(function() {
                dismissMessage();
            }, 5000);
            
            // Click on message or close button to dismiss immediately
            messageBox.addEventListener('click', function(e) {
                if (e.target.classList.contains('message-close') || e.target === this) {
                    clearTimeout(dismissTimer);
                    dismissMessage();
                }
            });
            
            // Hover to pause auto-dismiss
            messageBox.addEventListener('mouseenter', function() {
                clearTimeout(dismissTimer);
            });
            
            // Resume auto-dismiss after hover
            messageBox.addEventListener('mouseleave', function() {
                dismissTimer = setTimeout(function() {
                    dismissMessage();
                }, 3000);
            });
        }
    });

    // ================================================================
    // CLEAR SESSION MESSAGE VIA AJAX (Optional)
    // ================================================================
    function clearSessionMessage() {
        fetch('clear_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=clear_otc_message'
        }).catch(function() {});
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // DARK MODE - Page listens to header toggle
    // ================================================================
    console.log('%c💊 Braick - OTC Sale History', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c✅ Table width reduced (Items column compact)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Dark Mode support (controlled by header)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Messages auto-dismiss after 5 seconds', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Using item_name (NOT medicine_name)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>