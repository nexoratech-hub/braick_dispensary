<?php
// ================================================================
// FILE: frontend/pages/pharmacy/otc_history.php
// PHARMACY - OTC SALE HISTORY
// ✅ BLUE THEME
// ✅ Search bar shows TOTAL DISPENSED for SPECIFIC medicine
// ✅ Removed Pending Payments & Today's Sales cards
// ✅ Dispensed Items focus (no revenue)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA
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
// SESSION MESSAGES
// ================================================================
$message = '';
$message_type = '';
$show_message = false;

if (isset($_SESSION['otc_sale_message'])) {
    $message = $_SESSION['otc_sale_message'];
    $message_type = $_SESSION['otc_sale_message_type'] ?? 'success';
    $show_message = true;
    
    $message_time = $_SESSION['otc_sale_message_time'] ?? 0;
    if ($message_time > 0 && (time() - $message_time) > 30) {
        unset($_SESSION['otc_sale_message']);
        unset($_SESSION['otc_sale_message_type']);
        unset($_SESSION['otc_sale_message_time']);
        $show_message = false;
    }
}

// ================================================================
// FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$payment_status = isset($_GET['payment_status']) ? trim($_GET['payment_status']) : '';

// ================================================================
// GET OTC SALES
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
        os.premium_amount,
        os.premium_note,
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
    $query .= " AND (os.sale_number LIKE ? OR os.customer_name LIKE ? OR os.customer_phone LIKE ? OR oi.item_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
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
// ✅ GET ALL ITEMS FOR EACH SALE (kwa ajili ya search ya dawa specific)
// ================================================================
$sale_ids = array_column($sales, 'sale_id');
$sale_items_map = [];

if (!empty($sale_ids)) {
    $placeholders = implode(',', array_fill(0, count($sale_ids), '?'));
    $stmt = $db->prepare("
        SELECT sale_id, item_name, quantity, unit_price, total_price
        FROM otc_sale_items
        WHERE sale_id IN ($placeholders)
        ORDER BY sale_id, id
    ");
    $stmt->execute($sale_ids);
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_items as $item) {
        $sale_items_map[$item['sale_id']][] = $item;
    }
}

// ================================================================
// STATISTICS
// ================================================================

// Total Sales
$stmt = $db->prepare("SELECT COUNT(*) as count FROM otc_sales WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_sales = $stmt->fetch()['count'] ?? 0;

// ✅ TOTAL ITEMS DISPENSED
$stmt = $db->prepare("
    SELECT COALESCE(SUM(oi.quantity), 0) as total_dispensed 
    FROM otc_sale_items oi 
    INNER JOIN otc_sales os ON oi.sale_id = os.id 
    WHERE os.branch_id = ?
");
$stmt->execute([$user_branch_id]);
$total_dispensed_items = $stmt->fetch()['total_dispensed'] ?? 0;

// ✅ TOTAL UNIQUE MEDICINES
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT oi.item_name) as unique_medicines 
    FROM otc_sale_items oi 
    INNER JOIN otc_sales os ON oi.sale_id = os.id 
    WHERE os.branch_id = ?
");
$stmt->execute([$user_branch_id]);
$unique_medicines = $stmt->fetch()['unique_medicines'] ?? 0;

// Today's Sales & Today's Dispensed Items
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT os.id) as count,
        COALESCE(SUM(oi.quantity), 0) as total_dispensed
    FROM otc_sales os
    LEFT JOIN otc_sale_items oi ON os.id = oi.sale_id
    WHERE os.branch_id = ? 
    AND DATE(os.created_at) = CURDATE() 
    AND os.payment_status = 'paid'
");
$stmt->execute([$user_branch_id]);
$today_data = $stmt->fetch(PDO::FETCH_ASSOC);
$today_count = $today_data['count'] ?? 0;
$today_dispensed_items = $today_data['total_dispensed'] ?? 0;

// ================================================================
// SIDEBAR STATS
// ================================================================
$low_stock_count = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM medications_inventory WHERE branch_id = ? AND quantity <= reorder_level AND quantity > 0 AND status = 'active'");
    $stmt->execute([$user_branch_id]);
    $low_stock_count = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) { $low_stock_count = 0; }

$pending_prescriptions = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_prescriptions = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) { $pending_prescriptions = 0; }

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) { $unread_notifications = 0; }

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light';

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
            --highlight-bg: #FEF08A;
            --highlight-text: #713F12;
            
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --border-color: #E2E8F0;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-muted: #94A3B8;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --border-color: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
            --primary-light: #1E3A5F;
            --success-light: #1A3A2A;
            --warning-light: #3D2E0A;
            --danger-light: #3A1A1A;
            --purple-light: #2A1A3A;
            --highlight-bg: #FCD34D;
            --highlight-text: #422006;
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
        
        /* ✅ PAGE HEADER - BLUE THEME */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A3D8A);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 24px;
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
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
            top: -60%; right: -10%;
            width: 400px; height: 400px;
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
        
        .page-header .page-title i { font-size: 1.8rem; opacity: 0.9; }
        
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
        
        .page-header .page-subtitle strong { color: white; font-weight: 600; }
        
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
        }
        
        /* STATS - 4 CARDS ONLY */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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
        .stat-card.blue-dark { background: linear-gradient(135deg, #0A4CA8, #083A80); }
        .stat-card.blue-light { background: linear-gradient(135deg, #1E88E5, #1565C0); }
        .stat-card.sky { background: linear-gradient(135deg, #0288D1, #01579B); }
        
        /* MESSAGE BOX */
        .message-box {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideDown 0.4s ease;
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
        
        .message-box i { font-size: 1.3rem; }
        
        .message-box .message-close {
            margin-left: auto;
            cursor: pointer;
            font-size: 1.2rem;
            font-weight: 700;
            opacity: 0.6;
        }
        
        .message-box .message-close:hover { opacity: 1; }
        
        .message-box .message-timer {
            font-size: 0.6rem;
            opacity: 0.6;
            margin-left: 8px;
        }
        
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
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
        }
        
        /* TABLE HEADER WITH SEARCH */
        .table-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 14px;
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
            gap: 8px;
            flex-shrink: 0;
            flex-wrap: wrap;
        }
        
        /* Search box with icon */
        .table-search-box {
            position: relative;
            min-width: 280px;
            flex: 1;
            max-width: 420px;
        }
        
        .table-search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.9);
            font-size: 0.85rem;
            pointer-events: none;
        }
        
        .table-search-box input {
            width: 100%;
            padding: 10px 40px 10px 40px;
            border: 2px solid var(--primary);
            border-radius: 10px;
            font-size: 0.85rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            outline: none;
            font-weight: 500;
            height: 42px;
            transition: all 0.3s ease;
        }
        
        .table-search-box input::placeholder {
            color: rgba(255,255,255,0.85);
        }
        
        .table-search-box input:focus {
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.2);
            transform: translateY(-1px);
        }
        
        .table-search-box .clear-search-inline {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.9);
            font-size: 1rem;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 50%;
            transition: all 0.2s ease;
            display: none;
        }
        
        .table-search-box .clear-search-inline.show { display: block; }
        .table-search-box .clear-search-inline:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-50%) scale(1.1);
        }
        
        /* ✅ TOTAL DISPENSED INFO BOX */
        .dispensed-info-box {
            display: none;
            margin-bottom: 14px;
            padding: 14px 20px;
            border-radius: 12px;
            background: linear-gradient(135deg, #E8F0FE, #D6E4FF);
            border: 2px solid var(--primary);
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            animation: slideDown 0.4s ease;
        }
        
        [data-theme="dark"] .dispensed-info-box {
            background: linear-gradient(135deg, #1E3A5F, #16294A);
        }
        
        .dispensed-info-box.show { display: flex; }
        
        .dispensed-info-box .info-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .dispensed-info-box .info-text {
            flex: 1;
            min-width: 180px;
        }
        
        .dispensed-info-box .info-label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .dispensed-info-box .info-value {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1.1;
        }
        
        .dispensed-info-box .info-value small {
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-left: 6px;
        }
        
        .dispensed-info-box .info-divider {
            width: 2px;
            height: 45px;
            background: var(--border-color);
        }
        
        .dispensed-info-box .info-search-term {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .dispensed-info-box .info-search-term strong {
            color: var(--primary);
            font-weight: 700;
        }
        
        /* ✅ Matched items badges */
        .matched-items-list {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 6px;
        }
        
        .matched-item-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--primary-light);
            color: var(--primary);
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 700;
            border: 1px solid var(--primary);
        }
        
        .matched-item-badge .qty {
            background: var(--primary);
            color: white;
            padding: 1px 6px;
            border-radius: 8px;
            font-size: 0.6rem;
        }
        
        /* Filter chips */
        .filter-chips {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-chip {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        
        .filter-chip:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
            transform: translateY(-1px);
        }
        
        .filter-chip.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .filter-chip.paid.active {
            background: var(--success);
            border-color: var(--success);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .filter-chip.pending.active {
            background: var(--warning);
            border-color: var(--warning);
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
        }
        
        .filter-chip.danger.active {
            background: var(--danger);
            border-color: var(--danger);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        
        /* Result count */
        .result-count {
            font-size: 0.75rem;
            color: var(--text-secondary);
            padding: 4px 12px;
            background: var(--bg-body);
            border-radius: 20px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--border-color);
        }
        
        .result-count strong { color: var(--primary); }
        
        /* Advanced filters */
        .advanced-filters {
            display: none;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 14px;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .advanced-filters.show { display: flex; }
        
        .advanced-filters .form-group {
            flex: 1;
            min-width: 140px;
        }
        
        .advanced-filters .form-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            display: block;
            margin-bottom: 2px;
        }
        
        .advanced-filters .form-control {
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
        
        .advanced-filters .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .btn-apply-filters {
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            border: none;
            background: var(--primary);
            color: white;
            cursor: pointer;
            height: 42px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            align-self: flex-end;
        }
        
        .btn-apply-filters:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-toggle-filters {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .btn-toggle-filters:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-toggle-filters.active {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        /* TABLE */
        .table-wrap { overflow-x: auto; }
        
        .table-wrap::-webkit-scrollbar { height: 6px; width: 6px; }
        .table-wrap::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
        .table-wrap::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
        
        .data-table {
            width: 100%;
            min-width: 850px;
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
            padding: 8px 10px;
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
        
        .data-table tbody tr:nth-child(even) { background: var(--primary-light); }
        
        .data-table tbody tr:hover td { background: var(--success-light); }
        
        .data-table td {
            padding: 8px 10px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table td.center { text-align: center; }
        .data-table td.right { text-align: right; }
        
        /* Column widths */
        .col-sno { width: 30px; text-align: center; }
        .col-sale { width: 110px; }
        .col-customer { width: 140px; }
        .col-items { width: 160px; }
        .col-qty { width: 70px; text-align: center; }
        .col-total { width: 90px; text-align: right; }
        .col-payment { width: 85px; }
        .col-status { width: 85px; text-align: center; }
        .col-date { width: 100px; }
        .col-actions { width: 80px; text-align: center; }
        
        /* BADGES */
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
        
        /* BATCH NUMBER */
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
        
        /* ITEMS LIST */
        .items-list-compact {
            font-size: 0.6rem;
            color: var(--text-secondary);
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .items-list-compact .item-count {
            font-weight: 600;
            color: var(--primary);
        }
        
        /* QTY BADGE */
        .qty-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--primary-light);
            color: var(--primary);
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 700;
            border: 1px solid var(--primary);
        }
        
        /* ACTION BUTTONS */
        .action-btn {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin: 2px;
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
            background: #0A3D8A;
            color: white;
        }
        
        .action-btn.print:hover {
            background: #083A80;
            transform: scale(1.05);
        }
        
        /* HIGHLIGHT STYLES */
        mark.search-highlight {
            background: var(--highlight-bg);
            color: var(--highlight-text);
            padding: 0 2px;
            border-radius: 3px;
            font-weight: 700;
            box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.3);
            animation: highlight-pulse 0.6s ease;
        }
        
        [data-theme="dark"] mark.search-highlight {
            background: var(--highlight-bg);
            color: var(--highlight-text);
            box-shadow: 0 0 0 1px rgba(252, 211, 77, 0.5);
        }
        
        @keyframes highlight-pulse {
            0% { background-color: #FDE047; transform: scale(1.05); }
            50% { background-color: #FCD34D; transform: scale(1.1); }
            100% { background-color: var(--highlight-bg); transform: scale(1); }
        }
        
        .data-table td mark.search-highlight {
            display: inline-block;
        }
        
        /* EMPTY STATE */
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
        
        .empty-state p { font-size: 0.9rem; }
        .empty-state .sub { font-size: 0.75rem; color: var(--text-muted); }
        
        /* FOOTER */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* TOAST */
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
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* Highlight count badge */
        .highlight-count {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.6rem;
            padding: 2px 8px;
            border-radius: 10px;
            background: var(--highlight-bg);
            color: var(--highlight-text);
            font-weight: 700;
            margin-left: 6px;
        }
        
        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .card { padding: 12px 14px; }
            .table-header-bar { flex-direction: column; align-items: stretch; }
            .table-header-left { flex-direction: column; align-items: stretch; }
            .table-header-right { justify-content: stretch; }
            .table-search-box { min-width: 100%; max-width: 100%; }
            .filter-chips { justify-content: center; }
            .data-table { min-width: 700px; font-size: 0.65rem; }
            .data-table th, .data-table td { padding: 5px 6px; }
            .dispensed-info-box { flex-direction: column; text-align: center; }
            .dispensed-info-box .info-divider { display: none; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card { padding: 8px 10px; min-height: 55px; }
            .stat-card .stat-number { font-size: 1rem; }
            .filter-chip { font-size: 0.6rem; padding: 4px 10px; }
            .data-table { min-width: 600px; font-size: 0.55rem; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- ✅ PAGE HEADER - BLUE THEME -->
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
                    <i class="fas fa-pills"></i> <?= number_format($total_dispensed_items) ?> items dispensed
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

    <!-- MESSAGE -->
    <?php if ($show_message): ?>
        <div class="message-box <?= $message_type ?>" id="messageBox">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle') ?>"></i>
            <?= htmlspecialchars($message) ?>
            <span class="message-timer">(auto-dismiss in 5s)</span>
            <span class="message-close" onclick="dismissMessage()">&times;</span>
        </div>
    <?php endif; ?>

    <!-- ✅ STATS - 4 CARDS ONLY (BLUE THEME) -->
    <div class="stats-grid">
        <!-- Total Sales -->
        <div class="stat-card blue">
            <span class="stat-icon"><i class="fas fa-shopping-cart"></i></span>
            <div class="stat-number"><?= number_format($total_sales) ?></div>
            <div class="stat-label">Total Sales</div>
        </div>
        
        <!-- Items Dispensed -->
        <div class="stat-card blue-dark">
            <span class="stat-icon"><i class="fas fa-pills"></i></span>
            <div class="stat-number"><?= number_format($total_dispensed_items) ?></div>
            <div class="stat-label">Items Dispensed</div>
        </div>
        
        <!-- Unique Medicines -->
        <div class="stat-card blue-light">
            <span class="stat-icon"><i class="fas fa-capsules"></i></span>
            <div class="stat-number"><?= number_format($unique_medicines) ?></div>
            <div class="stat-label">Unique Medicines</div>
        </div>
        
        <!-- Today's Dispensed -->
        <div class="stat-card sky">
            <span class="stat-icon"><i class="fas fa-calendar-day"></i></span>
            <div class="stat-number"><?= number_format($today_dispensed_items) ?></div>
            <div class="stat-label">Today's Dispensed</div>
        </div>
    </div>

    <!-- SALES TABLE WITH SEARCH & HIGHLIGHT -->
    <div class="card">
        
        <!-- TABLE HEADER WITH SEARCH BAR -->
        <div class="table-header-bar">
            <div class="table-header-left">
                <!-- Search Box -->
                <div class="table-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" 
                           id="tableSearchInput" 
                           placeholder="Search medicine name to see total dispensed..." 
                           value="<?= htmlspecialchars($search) ?>"
                           autocomplete="off">
                    <span class="clear-search-inline" id="clearSearchBtn" onclick="clearTableSearch()">&times;</span>
                </div>
                
                <!-- Result count -->
                <span class="result-count" id="resultCount">
                    <i class="fas fa-list"></i>
                    <strong><?= count($sales) ?></strong> records
                    <span class="highlight-count" id="highlightCount" style="display:none;">
                        <i class="fas fa-highlighter"></i> <span id="highlightNum">0</span> highlights
                    </span>
                </span>
            </div>
            
            <div class="table-header-right">
                <!-- Advanced filter toggle -->
                <button type="button" class="btn-toggle-filters" id="toggleFiltersBtn" onclick="toggleAdvancedFilters()">
                    <i class="fas fa-filter"></i> Filters
                </button>
                
                <!-- Export button -->
                <button type="button" class="btn-toggle-filters" onclick="exportToCSV()">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>
        
        <!-- ✅ DISPENSED INFO BOX -->
        <div class="dispensed-info-box" id="dispensedInfoBox">
            <div class="info-icon">
                <i class="fas fa-pills"></i>
            </div>
            <div class="info-text">
                <div class="info-label">Total Dispensed for Search</div>
                <div class="info-search-term">
                    "<strong id="searchTermDisplay"></strong>"
                </div>
                <!-- ✅ Matched items badges -->
                <div class="matched-items-list" id="matchedItemsList"></div>
            </div>
            <div class="info-divider"></div>
            <div class="info-text" style="text-align:center;min-width:120px;">
                <div class="info-label">Total Quantity</div>
                <div class="info-value" id="totalDispensedDisplay">0 <small>items</small></div>
            </div>
            <div class="info-divider"></div>
            <div class="info-text" style="text-align:center;min-width:100px;">
                <div class="info-label">Transactions</div>
                <div class="info-value" id="totalTransactionsDisplay">0 <small>sales</small></div>
            </div>
        </div>
        
        <!-- FILTER CHIPS -->
        <div class="filter-chips" style="margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--border-color);">
            <a href="?<?= http_build_query(array_merge($_GET, ['payment_status' => ''])) ?>" 
               class="filter-chip <?= empty($payment_status) ? 'active' : '' ?>">
                <i class="fas fa-list"></i> All
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['payment_status' => 'paid'])) ?>" 
               class="filter-chip paid <?= $payment_status === 'paid' ? 'active' : '' ?>">
                <i class="fas fa-check-circle"></i> Paid
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['payment_status' => 'pending'])) ?>" 
               class="filter-chip pending <?= $payment_status === 'pending' ? 'active' : '' ?>">
                <i class="fas fa-clock"></i> Pending
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['payment_status' => 'partial'])) ?>" 
               class="filter-chip <?= $payment_status === 'partial' ? 'active' : '' ?>">
                <i class="fas fa-hourglass-half"></i> Partial
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['payment_status' => 'cancelled'])) ?>" 
               class="filter-chip danger <?= $payment_status === 'cancelled' ? 'active' : '' ?>">
                <i class="fas fa-times-circle"></i> Cancelled
            </a>
            
            <?php if (!empty($search) || !empty($date_from) || !empty($date_to) || !empty($payment_status)): ?>
                <a href="otc_history.php" class="filter-chip" style="border-color:var(--danger);color:var(--danger);margin-left:auto;">
                    <i class="fas fa-times"></i> Clear All
                </a>
            <?php endif; ?>
        </div>
        
        <!-- ADVANCED FILTERS -->
        <div class="advanced-filters <?= (!empty($date_from) || !empty($date_to)) ? 'show' : '' ?>" id="advancedFilters">
            <form method="GET" action="" style="display:contents;">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="payment_status" value="<?= htmlspecialchars($payment_status) ?>">
                
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <button type="submit" class="btn-apply-filters">
                    <i class="fas fa-check"></i> Apply Date Filter
                </button>
            </form>
        </div>
        
        <!-- TABLE -->
        <?php if (count($sales) > 0): ?>
            <div class="table-wrap">
                <table class="data-table" id="salesTable">
                    <thead>
                        <tr>
                            <th class="col-sno">#</th>
                            <th class="col-sale">Sale #</th>
                            <th class="col-customer">Customer</th>
                            <th class="col-items">Items</th>
                            <th class="col-qty center">Qty</th>
                            <th class="col-total right">Total</th>
                            <th class="col-payment">Payment</th>
                            <th class="col-status center">Status</th>
                            <th class="col-date">Date</th>
                            <th class="col-actions center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="salesTableBody">
                        <?php $counter = 1; ?>
                        <?php foreach ($sales as $sale): ?>
                            <?php
                                $status_class = 'badge-warning';
                                if ($sale['payment_status'] === 'paid') {
                                    $status_class = 'badge-success';
                                } elseif ($sale['payment_status'] === 'cancelled') {
                                    $status_class = 'badge-danger';
                                }
                                
                                $items_count = $sale['items_count'] ?? 0;
                                $items_list = $sale['items_list'] ?? '';
                                $grand_total = $sale['grand_total'] ?? 0;
                                $total_qty = $sale['total_items_quantity'] ?? 0;
                                
                                $short_items = $items_list;
                                if (strlen($short_items) > 60) {
                                    $short_items = substr($short_items, 0, 60) . '...';
                                }
                                
                                // ✅ Pata items za sale hii kwa ajili ya search
                                $sale_items = $sale_items_map[$sale['sale_id']] ?? [];
                                
                                // ✅ Tengeneza JSON ya items (name + qty)
                                $items_json = [];
                                foreach ($sale_items as $item) {
                                    $items_json[] = [
                                        'name' => strtolower(trim($item['item_name'])),
                                        'qty' => (int)$item['quantity']
                                    ];
                                }
                                $items_json_str = htmlspecialchars(json_encode($items_json), ENT_QUOTES, 'UTF-8');
                                
                                // Search data
                                $search_data = strtolower(
                                    ($sale['sale_number'] ?? '') . ' ' .
                                    ($sale['customer_name'] ?? '') . ' ' .
                                    ($sale['customer_phone'] ?? '') . ' ' .
                                    ($sale['payment_method'] ?? '') . ' ' .
                                    ($sale['payment_status'] ?? '') . ' ' .
                                    ($items_list ?? '')
                                );
                            ?>
                            <tr class="sale-row" 
                                data-search="<?= htmlspecialchars($search_data) ?>"
                                data-qty="<?= $total_qty ?>"
                                data-items='<?= $items_json_str ?>'>
                                <td class="col-sno"><?= $counter++ ?></td>
                                <td class="col-sale">
                                    <span class="batch-number" data-searchable>
                                        <?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="col-customer">
                                    <strong data-searchable><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></strong>
                                    <?php if (!empty($sale['customer_phone'])): ?>
                                        <div style="font-size:0.55rem;color:var(--text-muted);">
                                            <i class="fas fa-phone"></i> <span data-searchable><?= htmlspecialchars($sale['customer_phone']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="col-items">
                                    <div class="items-list-compact">
                                        <span class="item-count"><?= $items_count ?></span> item(s)
                                        <?php if (!empty($items_list)): ?>
                                            <div style="font-size:0.55rem;color:var(--text-muted);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" 
                                                 title="<?= htmlspecialchars($items_list) ?>"
                                                 data-searchable>
                                                <?= htmlspecialchars($short_items) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <!-- QTY COLUMN -->
                                <td class="col-qty center">
                                    <span class="qty-badge" data-searchable>
                                        <i class="fas fa-pills"></i> <?= number_format($total_qty) ?>
                                    </span>
                                </td>
                                <td class="col-total right">
                                    <strong style="color:var(--primary);font-size:0.8rem;" data-searchable>TSh <?= number_format($grand_total) ?></strong>
                                </td>
                                <td class="col-payment">
                                    <span style="font-size:0.65rem;" data-searchable>
                                        <?= ucfirst(str_replace('_', ' ', $sale['payment_method'] ?? 'N/A')) ?>
                                    </span>
                                </td>
                                <td class="col-status center">
                                    <span class="badge-status <?= $status_class ?>" data-searchable>
                                        <?= ucfirst($sale['payment_status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="col-date">
                                    <div style="font-size:0.65rem;" data-searchable>
                                        <?= date('d/m/Y', strtotime($sale['created_at'] ?? 'now')) ?>
                                    </div>
                                    <div style="font-size:0.55rem;color:var(--text-muted);">
                                        <?= date('H:i', strtotime($sale['created_at'] ?? 'now')) ?>
                                    </div>
                                </td>
                                <td class="col-actions center">
                                    <a href="view_otc_sale.php?id=<?= $sale['sale_id'] ?>" class="action-btn view" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="print_otc_receipt.php?id=<?= $sale['sale_id'] ?>" class="action-btn print" title="Print Receipt" target="_blank">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- No results row -->
                        <tr id="noResultsRow" style="display:none;">
                            <td colspan="10">
                                <div class="empty-state">
                                    <i class="fas fa-search-minus"></i>
                                    <p>No sales match your search</p>
                                    <p class="sub">Try different keywords</p>
                                </div>
                            </td>
                        </tr>
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

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span>
            OTC Sale History
            <span>|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle" style="font-weight:600;">Notification</p>
        <p id="toastMessage" style="font-size:0.85rem;"></p>
    </div>
</div>

<script>
    // ================================================================
    // VARIABLES
    // ================================================================
    var searchInput = document.getElementById('tableSearchInput');
    var clearBtn = document.getElementById('clearSearchBtn');
    var resultCountEl = document.getElementById('resultCount');
    var noResultsRow = document.getElementById('noResultsRow');
    var highlightCountEl = document.getElementById('highlightCount');
    var highlightNumEl = document.getElementById('highlightNum');
    var dispensedInfoBox = document.getElementById('dispensedInfoBox');
    var searchTermDisplay = document.getElementById('searchTermDisplay');
    var totalDispensedDisplay = document.getElementById('totalDispensedDisplay');
    var totalTransactionsDisplay = document.getElementById('totalTransactionsDisplay');
    var matchedItemsList = document.getElementById('matchedItemsList');
    var totalRows = <?= count($sales) ?>;
    
    var originalHTMLMap = new WeakMap();
    
    // ================================================================
    // ESCAPE FUNCTIONS
    // ================================================================
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function escapeRegex(text) {
        return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    // ================================================================
    // NUMBER FORMAT HELPER
    // ================================================================
    function numberFormat(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    // ================================================================
    // HIGHLIGHT TEXT
    // ================================================================
    function highlightText(element, query) {
        if (!element) return 0;
        
        if (!originalHTMLMap.has(element)) {
            originalHTMLMap.set(element, element.innerHTML);
        }
        
        var originalHTML = originalHTMLMap.get(element);
        
        if (!query || query.trim() === '') {
            element.innerHTML = originalHTML;
            return 0;
        }
        
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = originalHTML;
        var textContent = tempDiv.textContent || tempDiv.innerText || '';
        
        if (!textContent.trim()) {
            element.innerHTML = originalHTML;
            return 0;
        }
        
        var escapedQuery = escapeRegex(query);
        var regex = new RegExp('(' + escapedQuery + ')', 'gi');
        
        var matches = textContent.match(regex);
        var matchCount = matches ? matches.length : 0;
        
        if (matchCount === 0) {
            element.innerHTML = originalHTML;
            return 0;
        }
        
        var escapedText = escapeHtml(textContent);
        var highlightedHTML = escapedText.replace(regex, '<mark class="search-highlight">$1</mark>');
        
        element.innerHTML = highlightedHTML;
        return matchCount;
    }
    
    // ================================================================
    // REMOVE ALL HIGHLIGHTS
    // ================================================================
    function removeAllHighlights() {
        document.querySelectorAll('[data-searchable]').forEach(function(el) {
            if (originalHTMLMap.has(el)) {
                el.innerHTML = originalHTMLMap.get(el);
            }
        });
        
        if (highlightCountEl) highlightCountEl.style.display = 'none';
        if (highlightNumEl) highlightNumEl.textContent = '0';
    }
    
    // ================================================================
    // ✅ CALCULATE TOTAL DISPENSED FOR SPECIFIC MEDICINE
    // ================================================================
    function calculateDispensedTotals(query) {
        if (!query || query.trim() === '') {
            if (dispensedInfoBox) dispensedInfoBox.classList.remove('show');
            return { totalQty: 0, totalTransactions: 0 };
        }
        
        var lowerQuery = query.toLowerCase().trim();
        var rows = document.querySelectorAll('.sale-row');
        var totalQty = 0;
        var totalTransactions = 0;
        var matchedItemsMap = {};
        
        rows.forEach(function(row) {
            var itemsJson = row.dataset.items;
            if (!itemsJson) return;
            
            var items = [];
            try {
                items = JSON.parse(itemsJson);
            } catch (e) {
                return;
            }
            
            // ✅ Hesabu qty ya dawa iliyotafutwa TU
            var matchedQty = 0;
            var hasMatch = false;
            
            items.forEach(function(item) {
                // Angalia kama jina la dawa lina match na search query
                if (item.name.indexOf(lowerQuery) !== -1) {
                    matchedQty += item.qty;
                    hasMatch = true;
                    
                    // Track kwa item name
                    if (!matchedItemsMap[item.name]) {
                        matchedItemsMap[item.name] = 0;
                    }
                    matchedItemsMap[item.name] += item.qty;
                }
            });
            
            // ✅ Kama dawa imepatikana kwenye sale hii, ongeza qty
            if (hasMatch) {
                totalQty += matchedQty;
                totalTransactions++;
            }
        });
        
        // ✅ Show the info box
        if (dispensedInfoBox && totalTransactions > 0) {
            searchTermDisplay.textContent = query;
            totalDispensedDisplay.innerHTML = numberFormat(totalQty) + ' <small>items</small>';
            totalTransactionsDisplay.innerHTML = numberFormat(totalTransactions) + ' <small>sales</small>';
            
            // ✅ Onyesha matched items badges
            var badgesHtml = '';
            Object.keys(matchedItemsMap).forEach(function(name) {
                badgesHtml += '<span class="matched-item-badge">' 
                           + escapeHtml(name) 
                           + ' <span class="qty">' + matchedItemsMap[name] + '</span></span>';
            });
            matchedItemsList.innerHTML = badgesHtml;
            
            dispensedInfoBox.classList.add('show');
        } else if (dispensedInfoBox) {
            dispensedInfoBox.classList.remove('show');
            matchedItemsList.innerHTML = '';
        }
        
        return { totalQty: totalQty, totalTransactions: totalTransactions };
    }
    
    // ================================================================
    // ✅ MAIN FILTER FUNCTION
    // ================================================================
    function filterTable() {
        var query = (searchInput.value || '').trim();
        var lowerQuery = query.toLowerCase();
        var rows = document.querySelectorAll('.sale-row');
        var visibleCount = 0;
        var totalHighlights = 0;
        
        // Show/hide clear button
        if (query.length > 0) {
            clearBtn.classList.add('show');
        } else {
            clearBtn.classList.remove('show');
        }
        
        // Remove existing highlights first
        removeAllHighlights();
        
        // Filter rows
        rows.forEach(function(row) {
            var searchData = (row.dataset.search || '').toLowerCase();
            var matches = lowerQuery === '' || searchData.indexOf(lowerQuery) !== -1;
            
            if (matches) {
                row.style.display = '';
                visibleCount++;
                
                // Apply highlight
                if (query !== '') {
                    var searchableElements = row.querySelectorAll('[data-searchable]');
                    searchableElements.forEach(function(el) {
                        var count = highlightText(el, query);
                        totalHighlights += count;
                    });
                }
            } else {
                row.style.display = 'none';
            }
        });
        
        // Update result count
        if (resultCountEl) {
            if (query === '') {
                resultCountEl.innerHTML = '<i class="fas fa-list"></i> <strong>' + totalRows + '</strong> records';
            } else {
                resultCountEl.innerHTML = '<i class="fas fa-search"></i> <strong>' + visibleCount + '</strong> of ' + totalRows + ' match';
            }
        }
        
        // ✅ Calculate and show total dispensed (SPECIFIC MEDICINE)
        calculateDispensedTotals(query);
        
        // Show highlight count
        if (highlightCountEl && highlightNumEl) {
            if (query !== '' && totalHighlights > 0) {
                highlightCountEl.style.display = 'inline-flex';
                highlightNumEl.textContent = totalHighlights;
                
                if (!resultCountEl.contains(highlightCountEl)) {
                    resultCountEl.appendChild(highlightCountEl);
                }
            } else {
                highlightCountEl.style.display = 'none';
            }
        }
        
        // Show/hide no results row
        if (noResultsRow) {
            noResultsRow.style.display = (visibleCount === 0 && query !== '' && totalRows > 0) ? '' : 'none';
        }
    }
    
    // ================================================================
    // DEBOUNCE
    // ================================================================
    function debounce(func, wait) {
        var timeout;
        return function() {
            var context = this;
            var args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', debounce(filterTable, 150));
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                clearTableSearch();
            }
        });
    }
    
    function clearTableSearch() {
        if (searchInput) {
            searchInput.value = '';
            removeAllHighlights();
            filterTable();
            searchInput.focus();
        }
    }
    
    // ================================================================
    // TOGGLE ADVANCED FILTERS
    // ================================================================
    function toggleAdvancedFilters() {
        var filters = document.getElementById('advancedFilters');
        var btn = document.getElementById('toggleFiltersBtn');
        
        if (filters.classList.contains('show')) {
            filters.classList.remove('show');
            btn.classList.remove('active');
        } else {
            filters.classList.add('show');
            btn.classList.add('active');
        }
    }
    
    // ================================================================
    // MESSAGE AUTO-DISMISS
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
            var dismissTimer = setTimeout(dismissMessage, 5000);
            
            messageBox.addEventListener('click', function(e) {
                if (e.target.classList.contains('message-close') || e.target === this) {
                    clearTimeout(dismissTimer);
                    dismissMessage();
                }
            });
            
            messageBox.addEventListener('mouseenter', function() {
                clearTimeout(dismissTimer);
            });
            
            messageBox.addEventListener('mouseleave', function() {
                dismissTimer = setTimeout(dismissMessage, 3000);
            });
        }
        
        // ✅ Apply search on page load
        <?php if (!empty($search)): ?>
            if (searchInput && searchInput.value.trim() !== '') {
                setTimeout(function() {
                    filterTable();
                    searchInput.focus();
                    searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
                }, 200);
            }
        <?php endif; ?>
    });
    
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
    // KEYBOARD SHORTCUTS
    // ================================================================
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
    // EXPORT TO CSV
    // ================================================================
    function exportToCSV() {
        var rows = document.querySelectorAll('.sale-row');
        var csv = [];
        
        csv.push('Sale #,Customer,Phone,Items,Qty,Total,Payment Method,Status,Date');
        
        rows.forEach(function(row) {
            if (row.style.display !== 'none') {
                var cells = row.querySelectorAll('td');
                var rowData = [];
                for (var i = 1; i < cells.length - 1; i++) {
                    var text = cells[i].innerText.replace(/\n/g, ' ').replace(/,/g, ';').trim();
                    rowData.push('"' + text + '"');
                }
                csv.push(rowData.join(','));
            }
        });
        
        var csvContent = csv.join('\n');
        var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'otc_history_' + new Date().toISOString().slice(0,10) + '.csv';
        link.click();
        
        showToast('Success', 'Exported ' + (csv.length - 1) + ' records', 'success');
    }
    
    console.log('%c💊 Braick - OTC Sale History (BLUE THEME)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Blue theme applied', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Search shows SPECIFIC medicine qty', 'font-size:13px; color:#FCD34D;');
    console.log('%c✅ Matched items badges shown', 'font-size:13px; color:#FCD34D;');
    console.log('%c✅ Live search with YELLOW HIGHLIGHT', 'font-size:13px; color:#FCD34D;');
    console.log('%c✅ Dark Mode support', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>