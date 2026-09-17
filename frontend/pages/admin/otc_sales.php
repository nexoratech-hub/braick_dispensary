<?php
// ================================================================
// FILE: frontend/pages/admin/otc_sales.php
// OTC SALES MANAGEMENT V4 - Admin Module
// ✅ FIXED: Font Awesome icons zinaonekana
// ✅ Font family: Inter + JetBrains Mono (sawa na dashboard)
// ✅ Beautiful amount cards with gradients
// ✅ Medication column (jina la dawa + qty)
// ✅ Quick filters (Today, 1W, 1M, 3M, 6M, 1Y, All, Custom)
// ✅ View / Edit / Delete buttons
// ✅ Delete confirmation modal
// ✅ < > scroll buttons
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['admin', 'pharmacy', 'cashier'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// HANDLE DELETE ACTION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_otc') {
    $sale_id = (int)($_POST['sale_id'] ?? 0);
    
    if ($sale_id > 0) {
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("SELECT sale_number FROM otc_sales WHERE id = ?");
            $stmt->execute([$sale_id]);
            $sale_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($sale_data) {
                $db->prepare("DELETE FROM otc_sale_items WHERE sale_id = ?")->execute([$sale_id]);
                $db->prepare("DELETE FROM otc_sales WHERE id = ?")->execute([$sale_id]);
                
                try {
                    $db->prepare("INSERT INTO activity_logs (user_id, action, description, branch_id, ip_address, created_at) 
                                  VALUES (?, 'DELETE_OTC_SALE', ?, ?, ?, NOW())")
                       ->execute([
                           $user_id,
                           "Deleted OTC sale: " . ($sale_data['sale_number'] ?? 'N/A'),
                           $user_branch_id,
                           $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                       ]);
                } catch (Exception $e) {}
                
                $db->commit();
                $message = "OTC Sale " . ($sale_data['sale_number'] ?? 'N/A') . " deleted successfully!";
                $message_type = 'success';
            } else {
                $db->rollBack();
                $message = "Sale not found.";
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $db->rollBack();
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_name_display = 'All Branches';

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id_param = (int)$selected_branch_id;
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id_param]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name_display = $branch_data['name'];
    }
} else {
    $selected_branch_id = 'all';
}

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active'");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// FILTER PARAMETERS
// ================================================================
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$quick_filter = $_GET['quick'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Determine date range based on quick filter
$quick_date_from = '';
$quick_date_to = date('Y-m-d');

switch ($quick_filter) {
    case 'today':
        $quick_date_from = date('Y-m-d');
        $quick_date_to = date('Y-m-d');
        break;
    case '1w':
        $quick_date_from = date('Y-m-d', strtotime('-7 days'));
        break;
    case '1m':
        $quick_date_from = date('Y-m-d', strtotime('-1 month'));
        break;
    case '3m':
        $quick_date_from = date('Y-m-d', strtotime('-3 months'));
        break;
    case '6m':
        $quick_date_from = date('Y-m-d', strtotime('-6 months'));
        break;
    case '1y':
        $quick_date_from = date('Y-m-d', strtotime('-1 year'));
        break;
    case 'custom':
        $quick_date_from = $date_from;
        $quick_date_to = $date_to;
        break;
    case 'all':
    default:
        $quick_date_from = '';
        $quick_date_to = '';
        break;
}

// ================================================================
// BUILD WHERE
// ================================================================
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(os.sale_number LIKE ? OR os.customer_name LIKE ? OR os.customer_phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter !== 'all') {
    $where_conditions[] = "os.payment_status = ?";
    $params[] = $status_filter;
}

if (!empty($quick_date_from)) {
    $where_conditions[] = "DATE(os.created_at) >= ?";
    $params[] = $quick_date_from;
}
if (!empty($quick_date_to)) {
    $where_conditions[] = "DATE(os.created_at) <= ?";
    $params[] = $quick_date_to;
}

$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

if ($selected_branch_id !== 'all') {
    if (!empty($where_clause)) {
        $where_clause .= " AND os.branch_id = " . (int)$selected_branch_id;
    } else {
        $where_clause = "WHERE os.branch_id = " . (int)$selected_branch_id;
    }
}

// ================================================================
// TOTAL COUNT
// ================================================================
$count_sql = "SELECT COUNT(*) as total FROM otc_sales os $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_records / $limit);

// ================================================================
// GET OTC SALES
// ================================================================
$sql = "
    SELECT 
        os.id,
        os.sale_number,
        os.customer_name,
        os.customer_phone,
        os.total_amount,
        os.subtotal,
        os.discount_amount,
        os.payment_status,
        os.payment_method,
        os.bill_id,
        os.sold_by,
        os.notes,
        os.created_at,
        os.updated_at,
        os.branch_id,
        COALESCE(b.paid_amount, 0) as paid_amount,
        COALESCE(b.status, 'pending') as bill_status,
        u.full_name as sold_by_name
    FROM otc_sales os
    LEFT JOIN bills b ON os.bill_id = b.id
    LEFT JOIN users u ON os.sold_by = u.id
    $where_clause
    ORDER BY os.created_at DESC
    LIMIT ? OFFSET ?
";

$query_params = $params;
$query_params[] = $limit;
$query_params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($query_params);
$otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ITEMS FOR EACH SALE
// ================================================================
$sale_ids = array_column($otc_sales, 'id');
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
$stats_sql = "
    SELECT 
        COUNT(*) as total_sales,
        COALESCE(SUM(os.total_amount), 0) as total_revenue,
        COALESCE(SUM(b.paid_amount), 0) as total_paid,
        COALESCE(SUM(os.total_amount - COALESCE(b.paid_amount, 0)), 0) as total_balance
    FROM otc_sales os
    LEFT JOIN bills b ON os.bill_id = b.id
    $where_clause
";

$stmt = $db->prepare($stats_sql);
$stmt->execute($params);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$total_sales = $stats['total_sales'] ?? 0;
$total_revenue = $stats['total_revenue'] ?? 0;
$total_paid = $stats['total_paid'] ?? 0;
$total_balance = $stats['total_balance'] ?? 0;

// ================================================================
// RECENT OTC SALES
// ================================================================
$recent_sql = "
    SELECT 
        os.id,
        os.sale_number,
        os.customer_name,
        os.total_amount,
        os.payment_status,
        os.created_at,
        COALESCE(b.paid_amount, 0) as paid_amount
    FROM otc_sales os
    LEFT JOIN bills b ON os.bill_id = b.id
    $where_clause
    ORDER BY os.created_at DESC
    LIMIT 5
";

$stmt = $db->prepare($recent_sql);
$stmt->execute($params);
$recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// URLS
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// Helper function to build URL with current filters
function buildFilterUrl($params_to_update = []) {
    $current = $_GET;
    foreach ($params_to_update as $key => $value) {
        if ($value === null || $value === '') {
            unset($current[$key]);
        } else {
            $current[$key] = $value;
        }
    }
    return '?' . http_build_query($current);
}

include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================
     ✅ FIX: FONT AWESOME ICONS - HAKIKISHA ZINAONEKANA
     ================================================================ -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- ✅ GOOGLE FONTS - INTER + JETBRAINS MONO -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    /* ================================================================
       ✅ FIX: FONT AWESOME ICONS - MUHIMU
       ================================================================ */
    i.fas, i.far, i.fab, i.fa,
    i[class*="fa-"] {
        font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands", "FontAwesome" !important;
        font-weight: 900 !important;
        font-style: normal !important;
        font-variant: normal !important;
        text-rendering: auto !important;
        line-height: 1 !important;
        display: inline-block !important;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /* Specific fix kwa icons zote kwenye page hii */
    .stat-card-custom i,
    .stat-card-custom .stat-icon-box i,
    .stat-card-custom .stat-badge i,
    .stat-card-custom .stat-label i,
    .stat-card-custom .stat-footer i,
    .btn-custom i,
    .btn-outline-light i,
    .scroll-btn i,
    .quick-filter-btn i,
    .filter-label i,
    .page-title i,
    .header-badge i,
    .card-title-custom i,
    .section-title i,
    .action-btn i {
        font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands" !important;
        font-weight: 900 !important;
    }

    /* ================================================================
       FONT FAMILY - INTER + JETBRAINS MONO
       ================================================================ */
    :root {
        --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        --font-mono: 'JetBrains Mono', 'Courier New', monospace;
        
        --page-bg-body: #F1F5F9;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-text-muted: #94A3B8;
        --page-border: #E2E8F0;
        --page-hover: #E8F0FE;
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-text-muted: #64748B;
        --page-border: #334155;
        --page-hover: #1E3A5F;
    }

    body, .main-content, .main-content * {
        font-family: var(--font-primary);
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /* Mono font kwa namba */
    .money, .money-value, .stat-number, .mono,
    .sale-number, .phone-number, .med-qty, .balance-value,
    .currency-value, .amount-value {
        font-family: var(--font-mono) !important;
        font-feature-settings: 'tnum';
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
    }

    body { background: var(--page-bg-body, #F1F5F9); }
    html[data-theme="dark"] body { background: #0F172A !important; }
    .main-content { background: var(--page-bg-body, #F1F5F9); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; color: #F1F5F9; }

    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header-custom {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083D8A 100%);
        border-radius: 18px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.35), 0 4px 12px rgba(11, 94, 215, 0.2);
        position: relative;
        overflow: hidden;
    }

    .page-header-custom::before {
        content: '';
        position: absolute;
        top: -60%; right: -10%;
        width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-custom::after {
        content: '';
        position: absolute;
        bottom: -60%; left: -5%;
        width: 300px; height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-custom .page-title {
        color: white;
        font-size: 1.6rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
        letter-spacing: -0.02em;
    }

    .page-header-custom .page-title i {
        width: 44px;
        height: 44px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-custom .page-subtitle {
        color: rgba(255,255,255,0.9);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 8px;
    }

    .page-header-custom .header-badge {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-custom .btn-outline-light {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1.5px solid rgba(255,255,255,0.3);
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        backdrop-filter: blur(4px);
        position: relative;
        z-index: 1;
        cursor: pointer;
        white-space: nowrap;
    }

    .page-header-custom .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    /* ALERT */
    .alert-custom {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 600;
        font-size: 0.85rem;
        animation: slideDown 0.4s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .alert-custom.success { background: #D1FAE5; color: #065F46; border-left: 4px solid #059669; }
    .alert-custom.error { background: #FEE2E2; color: #991B1B; border-left: 4px solid #DC2626; }

    [data-theme="dark"] .alert-custom.success { background: #1A3A2A; color: #34D399; border-left-color: #059669; }
    [data-theme="dark"] .alert-custom.error { background: #3A1A1A; color: #F87171; border-left-color: #DC2626; }

    /* ================================================================
       BEAUTIFUL STAT CARDS
       ================================================================ */
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card-custom {
        position: relative;
        border-radius: 16px;
        padding: 20px 22px;
        overflow: hidden;
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        color: white;
        min-height: 130px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        cursor: pointer;
    }

    .stat-card-custom.sales { background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%); }
    .stat-card-custom.revenue { background: linear-gradient(135deg, #1E88E5 0%, #1565C0 100%); }
    .stat-card-custom.paid { background: linear-gradient(135deg, #10B981 0%, #059669 100%); }
    .stat-card-custom.outstanding { background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); }

    .stat-card-custom::before {
        content: '';
        position: absolute;
        top: -40%; right: -20%;
        width: 180px; height: 180px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
        pointer-events: none;
        transition: all 0.5s ease;
    }

    .stat-card-custom::after {
        content: '';
        position: absolute;
        bottom: -60%; left: -15%;
        width: 140px; height: 140px;
        background: rgba(255,255,255,0.06);
        border-radius: 50%;
        pointer-events: none;
        transition: all 0.5s ease;
    }

    .stat-card-custom:hover {
        transform: translateY(-6px);
        box-shadow: 0 12px 32px rgba(0,0,0,0.2);
    }

    .stat-card-custom:hover::before {
        top: -50%; right: -30%;
        width: 220px; height: 220px;
    }

    .stat-card-custom:hover::after {
        bottom: -70%; left: -25%;
        width: 180px; height: 180px;
    }

    .stat-card-custom .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        position: relative;
        z-index: 1;
        margin-bottom: 12px;
    }

    .stat-card-custom .stat-icon-box {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: white;
        transition: transform 0.3s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .stat-card-custom:hover .stat-icon-box {
        transform: scale(1.1) rotate(-5deg);
    }

    .stat-card-custom .stat-badge {
        background: rgba(255,255,255,0.25);
        color: white;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.62rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.3);
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
    }

    .stat-card-custom .stat-badge .pulse-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: white;
        animation: pulse-dot 1.5s infinite;
    }

    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.4; transform: scale(0.8); }
    }

    .stat-card-custom .stat-content {
        position: relative;
        z-index: 1;
    }

    .stat-card-custom .stat-label {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.85);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .stat-card-custom .stat-number {
        font-size: 1.65rem;
        font-weight: 900;
        color: white;
        line-height: 1.1;
        font-family: var(--font-mono) !important;
        letter-spacing: -0.03em;
        display: flex;
        align-items: baseline;
        gap: 5px;
        flex-wrap: wrap;
        text-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }

    .stat-card-custom .stat-number .currency-label {
        font-size: 0.9rem;
        font-weight: 600;
        color: rgba(255,255,255,0.75);
        font-family: var(--font-primary) !important;
    }

    .stat-card-custom .stat-footer {
        position: relative;
        z-index: 1;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid rgba(255,255,255,0.2);
        font-size: 0.65rem;
        color: rgba(255,255,255,0.85);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        flex-wrap: wrap;
    }

    .stat-card-custom .stat-footer .footer-left {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-weight: 600;
    }

    .stat-card-custom .stat-footer .footer-right {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-weight: 700;
        font-family: var(--font-mono);
        color: white;
    }

    .stat-card-custom .stat-footer i {
        font-size: 0.6rem;
        opacity: 0.9;
    }

    /* CARD */
    .card-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        margin-bottom: 24px;
    }

    html[data-theme="dark"] .card-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .card-header-custom {
        padding: 14px 20px;
        background: var(--page-hover, #F8FAFC);
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    html[data-theme="dark"] .card-header-custom {
        background: #0F172A;
        border-color: #334155;
    }

    .card-title-custom {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    html[data-theme="dark"] .card-title-custom { color: #F1F5F9; }
    .card-title-custom i { color: #0B5ED7; }
    html[data-theme="dark"] .card-title-custom i { color: #6EA8FE; }

    .card-title-custom .count-badge {
        font-size: 0.72rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 400;
        margin-left: 4px;
    }

    /* QUICK DATE FILTERS */
    .quick-filters {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        align-items: center;
        padding: 12px 20px;
        background: var(--page-bg-body, #F1F5F9);
        border-bottom: 1px solid var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .quick-filters {
        background: #0F172A;
        border-bottom-color: #334155;
    }

    .quick-filters .filter-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--page-text-secondary, #64748B);
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-right: 6px;
    }

    .quick-filter-btn {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        border: 2px solid var(--page-border, #E2E8F0);
        background: var(--page-bg-card, #FFFFFF);
        color: var(--page-text-secondary, #64748B);
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
        transition: all 0.25s ease;
    }

    html[data-theme="dark"] .quick-filter-btn {
        background: #1E293B;
        border-color: #334155;
        color: #94A3B8;
    }

    .quick-filter-btn:hover {
        border-color: #0B5ED7;
        color: #0B5ED7;
        background: #E8F0FE;
        transform: translateY(-1px);
    }

    html[data-theme="dark"] .quick-filter-btn:hover {
        background: #1E3A5F;
        color: #6EA8FE;
    }

    .quick-filter-btn.active {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-color: #0B5ED7;
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.35);
    }

    .quick-filter-btn.today.active {
        background: linear-gradient(135deg, #059669, #047857);
        border-color: #059669;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
    }

    .quick-filter-btn.custom.active {
        background: linear-gradient(135deg, #D97706, #B45309);
        border-color: #D97706;
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.35);
    }

    /* CUSTOM DATE ROW */
    .custom-date-row {
        display: none;
        padding: 12px 20px;
        background: var(--page-hover, #F8FAFC);
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        gap: 10px;
        flex-wrap: wrap;
        align-items: end;
    }

    html[data-theme="dark"] .custom-date-row {
        background: #0F172A;
        border-bottom-color: #334155;
    }

    .custom-date-row.show { display: flex; }
    .custom-date-row .form-group { flex: 1; min-width: 140px; }

    .custom-date-row .form-group label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--page-text-secondary, #64748B);
        display: block;
        margin-bottom: 4px;
    }

    .custom-date-row .form-control {
        width: 100%;
        padding: 8px 12px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 8px;
        font-size: 0.8rem;
        background: var(--page-bg-card, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        outline: none;
    }

    html[data-theme="dark"] .custom-date-row .form-control {
        background: #1E293B;
        color: #F1F5F9;
        border-color: #334155;
    }

    /* FILTER FORM */
    .filter-form-custom {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .filter-form-custom input,
    .filter-form-custom select {
        padding: 6px 12px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 8px;
        font-size: 0.72rem;
        background: var(--page-bg-body, #F1F5F9);
        color: var(--page-text-primary, #1E293B);
        outline: none;
        transition: all 0.3s;
    }

    html[data-theme="dark"] .filter-form-custom input,
    html[data-theme="dark"] .filter-form-custom select {
        background: #0F172A;
        color: #F1F5F9;
        border-color: #334155;
    }

    .filter-form-custom input:focus,
    .filter-form-custom select:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }

    /* SCROLL BUTTONS */
    .scroll-buttons {
        display: inline-flex;
        gap: 6px;
        align-items: center;
    }

    .scroll-btn {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        font-weight: 700;
        transition: all 0.25s ease;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
    }

    .scroll-btn:hover {
        transform: translateY(-2px) scale(1.05);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.4);
        background: linear-gradient(135deg, #1E88E5, #0B5ED7);
    }

    .scroll-btn:active {
        transform: translateY(0) scale(0.95);
    }

    /* DATA TABLE */
    .table-wrapper-custom {
        overflow-x: auto;
        scroll-behavior: smooth;
    }

    .table-wrapper-custom::-webkit-scrollbar { height: 8px; }
    .table-wrapper-custom::-webkit-scrollbar-track {
        background: var(--page-bg-body, #F1F5F9);
        border-radius: 4px;
    }
    .table-wrapper-custom::-webkit-scrollbar-thumb {
        background: #0B5ED7;
        border-radius: 4px;
    }

    .data-table-custom {
        width: 100%;
        min-width: 1300px;
        border-collapse: collapse;
        font-size: 0.78rem;
    }

    .data-table-custom thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        font-weight: 700;
        padding: 10px 14px;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        text-align: left;
        white-space: nowrap;
    }

    .data-table-custom thead th:first-child { border-radius: 10px 0 0 0; }
    .data-table-custom thead th:last-child { border-radius: 0 10px 0 0; }

    .data-table-custom td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: top;
    }

    html[data-theme="dark"] .data-table-custom td {
        color: #F1F5F9;
        border-bottom-color: #334155;
    }

    .data-table-custom tbody tr:nth-child(even) td {
        background: var(--page-hover, #F8FAFC);
    }

    html[data-theme="dark"] .data-table-custom tbody tr:nth-child(even) td {
        background: #1E3A5F;
    }

    .data-table-custom tbody tr:hover td {
        background: #E8F0FE;
    }

    html[data-theme="dark"] .data-table-custom tbody tr:hover td {
        background: #1E40AF;
    }

    .data-table-custom tbody tr:last-child td { border-bottom: none; }

    /* MEDICATION CELL */
    .med-list {
        display: flex;
        flex-direction: column;
        gap: 3px;
        min-width: 200px;
    }

    .med-item {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 3px 0;
        border-bottom: 1px dashed var(--page-border, #E2E8F0);
        font-size: 0.7rem;
        line-height: 1.3;
    }

    .med-item:last-child { border-bottom: none; }

    .med-item .med-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #0B5ED7;
        flex-shrink: 0;
    }

    .med-item .med-name {
        font-weight: 700;
        color: #0B5ED7;
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 140px;
    }

    html[data-theme="dark"] .med-item .med-name { color: #6EA8FE; }

    .med-item .med-qty {
        background: #0B5ED7;
        color: white;
        padding: 1px 7px;
        border-radius: 8px;
        font-size: 0.6rem;
        font-weight: 700;
        white-space: nowrap;
        font-family: var(--font-mono) !important;
    }

    .med-more {
        font-size: 0.6rem;
        color: var(--page-text-muted, #94A3B8);
        padding: 2px 0;
        font-style: italic;
    }

    /* STATUS BADGES */
    .status-badge-custom {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .status-badge-custom.paid { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
    .status-badge-custom.unpaid { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
    .status-badge-custom.partial { background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; }

    html[data-theme="dark"] .status-badge-custom.paid { background: #1A3A2A; color: #34D399; border-color: #065F46; }
    html[data-theme="dark"] .status-badge-custom.unpaid { background: #3A1A1A; color: #F87171; border-color: #7F1D1D; }
    html[data-theme="dark"] .status-badge-custom.partial { background: #3A2A1A; color: #FBBF24; border-color: #78350F; }

    /* BUTTONS */
    .btn-custom {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 14px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.72rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn-primary-custom { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; }
    .btn-primary-custom:hover { color: white; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }

    .btn-outline-custom {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }
    .btn-outline-custom:hover {
        background: var(--page-bg-body, #F1F5F9);
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: translateY(-2px);
    }

    .btn-info-custom { background: #0B5ED7; color: white; }
    .btn-info-custom:hover { background: #0A4CA8; color: white; transform: scale(1.05); }

    .btn-success-custom { background: #059669; color: white; }
    .btn-success-custom:hover { background: #047857; color: white; transform: scale(1.05); }

    .btn-warning-custom { background: #D97706; color: white; }
    .btn-warning-custom:hover { background: #B45309; color: white; transform: scale(1.05); }

    .btn-danger-custom { background: #DC2626; color: white; }
    .btn-danger-custom:hover { background: #B91C1C; color: white; transform: scale(1.05); }

    .btn-sm-custom { padding: 5px 10px; font-size: 0.68rem; border-radius: 6px; }

    /* PAGINATION */
    .pagination-custom { display: flex; gap: 4px; flex-wrap: wrap; }

    .pagination-custom .page-link {
        padding: 5px 12px;
        border-radius: 6px;
        border: 1.5px solid var(--page-border, #E2E8F0);
        color: var(--page-text-secondary, #64748B);
        text-decoration: none;
        font-size: 0.72rem;
        font-weight: 600;
        transition: all 0.3s;
        background: var(--page-bg-card, #FFFFFF);
    }

    html[data-theme="dark"] .pagination-custom .page-link {
        background: #1E293B;
        color: #F1F5F9;
        border-color: #334155;
    }

    .pagination-custom .page-link:hover { background: #0B5ED7; color: white; border-color: #0B5ED7; }
    .pagination-custom .page-link.active { background: #0B5ED7; color: white; border-color: #0B5ED7; }

    /* DELETE MODAL */
    .modal-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(6px);
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-overlay.active { display: flex; }

    .modal-box {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 20px;
        max-width: 460px;
        width: 100%;
        padding: 32px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        text-align: center;
    }

    html[data-theme="dark"] .modal-box { background: #1E293B; }

    @keyframes modalPop {
        0% { opacity: 0; transform: scale(0.8) translateY(20px); }
        100% { opacity: 1; transform: scale(1) translateY(0); }
    }

    .modal-icon {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: #FEE2E2;
        color: #DC2626;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin: 0 auto 18px;
        animation: iconPulse 1.5s infinite;
    }

    @keyframes iconPulse {
        0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); }
        50% { transform: scale(1.05); box-shadow: 0 0 0 12px rgba(220, 38, 38, 0); }
    }

    .modal-title {
        font-size: 1.3rem;
        font-weight: 800;
        margin-bottom: 10px;
        color: var(--page-text-primary, #1E293B);
    }

    html[data-theme="dark"] .modal-title { color: #F1F5F9; }

    .modal-text {
        font-size: 0.9rem;
        color: var(--page-text-secondary, #64748B);
        margin-bottom: 24px;
        line-height: 1.7;
    }

    .modal-text strong {
        color: #0B5ED7;
        background: #E8F0FE;
        padding: 2px 8px;
        border-radius: 6px;
        font-family: var(--font-mono);
    }

    html[data-theme="dark"] .modal-text strong {
        background: #1E3A5F;
        color: #6EA8FE;
    }

    .modal-warning {
        color: #DC2626;
        font-weight: 700;
        display: block;
        margin-top: 6px;
        font-size: 0.8rem;
    }

    .modal-actions { display: flex; gap: 10px; justify-content: center; }

    .modal-btn {
        padding: 11px 26px;
        border-radius: 11px;
        font-weight: 700;
        font-size: 0.85rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .modal-btn.cancel { background: var(--page-border, #E2E8F0); color: var(--page-text-primary, #1E293B); }
    .modal-btn.cancel:hover { background: #CBD5E1; transform: translateY(-2px); }

    .modal-btn.danger { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; }
    .modal-btn.danger:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5); }

    /* RESPONSIVE */
    @media (max-width: 1200px) {
        .stat-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 992px) {
        .stat-card-custom .stat-number { font-size: 1.4rem; }
    }

    @media (max-width: 768px) {
        .stat-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .stat-card-custom { padding: 16px 18px; min-height: 115px; }
        .stat-card-custom .stat-number { font-size: 1.2rem; }
        .stat-card-custom .stat-icon-box { width: 40px; height: 40px; font-size: 1.1rem; }
        .page-header-custom { padding: 18px; }
        .page-header-custom .page-title { font-size: 1.15rem; }
        .quick-filters { justify-content: center; }
        .quick-filter-btn { font-size: 0.65rem; padding: 5px 10px; }
    }

    @media (max-width: 480px) {
        .stat-grid { grid-template-columns: 1fr; gap: 8px; }
        .stat-card-custom { padding: 14px 16px; min-height: 100px; }
        .stat-card-custom .stat-number { font-size: 1.1rem; }
    }

    @media print {
        .page-header-custom { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; }
        .filter-form-custom, .btn-custom, .pagination-custom, .quick-filters, .scroll-buttons { display: none !important; }
    }
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div style="position:relative;z-index:1;">
            <h1 class="page-title">
                <i class="fas fa-cash-register"></i>
                OTC Sales
            </h1>
            <p class="page-subtitle">
                <span>Manage over-the-counter sales</span>
                <span class="header-badge"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name_display) ?></span>
                <span class="header-badge"><i class="fas fa-calendar-day"></i> <?= date('F d, Y') ?></span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="add_otc_sale.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-plus-circle"></i> New OTC Sale
            </a>
            <button onclick="location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ALERT -->
    <?php if (!empty($message)): ?>
        <div class="alert-custom <?= $message_type ?>" id="alertBox">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
        <script>
            setTimeout(function() {
                var el = document.getElementById('alertBox');
                if (el) { el.style.transition = 'all 0.5s ease'; el.style.opacity = '0'; setTimeout(function() { el.remove(); }, 500); }
            }, 4000);
        </script>
    <?php endif; ?>

    <!-- STAT CARDS -->
    <div class="stat-grid">
        
        <!-- CARD 1: TOTAL SALES -->
        <div class="stat-card-custom sales">
            <div class="stat-header">
                <div class="stat-icon-box">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <span class="stat-badge">
                    <span class="pulse-dot"></span> LIVE
                </span>
            </div>
            <div class="stat-content">
                <div class="stat-label">
                    <i class="fas fa-receipt"></i> Total Sales
                </div>
                <div class="stat-number">
                    <?= number_format($total_sales) ?>
                </div>
            </div>
            <div class="stat-footer">
                <span class="footer-left">
                    <i class="fas fa-info-circle"></i> Transactions
                </span>
                <span class="footer-right">
                    <i class="fas fa-arrow-up"></i> All Time
                </span>
            </div>
        </div>

        <!-- CARD 2: TOTAL REVENUE -->
        <div class="stat-card-custom revenue">
            <div class="stat-header">
                <div class="stat-icon-box">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <span class="stat-badge">
                    <i class="fas fa-chart-line"></i> REVENUE
                </span>
            </div>
            <div class="stat-content">
                <div class="stat-label">
                    <i class="fas fa-coins"></i> Total Revenue
                </div>
                <div class="stat-number">
                    <span class="currency-label">TSh</span>
                    <?= number_format($total_revenue) ?>
                </div>
            </div>
            <div class="stat-footer">
                <span class="footer-left">
                    <i class="fas fa-calculator"></i> Gross
                </span>
                <span class="footer-right">
                    <i class="fas fa-arrow-up"></i> Total
                </span>
            </div>
        </div>

        <!-- CARD 3: AMOUNT PAID -->
        <div class="stat-card-custom paid">
            <div class="stat-header">
                <div class="stat-icon-box">
                    <i class="fas fa-check-circle"></i>
                </div>
                <span class="stat-badge">
                    <i class="fas fa-check-double"></i> PAID
                </span>
            </div>
            <div class="stat-content">
                <div class="stat-label">
                    <i class="fas fa-hand-holding-usd"></i> Amount Paid
                </div>
                <div class="stat-number">
                    <span class="currency-label">TSh</span>
                    <?= number_format($total_paid) ?>
                </div>
            </div>
            <div class="stat-footer">
                <span class="footer-left">
                    <i class="fas fa-check"></i> Collected
                </span>
                <span class="footer-right">
                    <?php 
                    $paid_pct = $total_revenue > 0 ? round(($total_paid / $total_revenue) * 100, 1) : 0;
                    echo $paid_pct . '%';
                    ?>
                </span>
            </div>
        </div>

        <!-- CARD 4: OUTSTANDING -->
        <div class="stat-card-custom outstanding">
            <div class="stat-header">
                <div class="stat-icon-box">
                    <i class="fas fa-clock"></i>
                </div>
                <span class="stat-badge">
                    <i class="fas fa-hourglass-half"></i> PENDING
                </span>
            </div>
            <div class="stat-content">
                <div class="stat-label">
                    <i class="fas fa-exclamation-triangle"></i> Outstanding
                </div>
                <div class="stat-number">
                    <span class="currency-label">TSh</span>
                    <?= number_format($total_balance) ?>
                </div>
            </div>
            <div class="stat-footer">
                <span class="footer-left">
                    <i class="fas fa-times-circle"></i> Unpaid
                </span>
                <span class="footer-right">
                    <?php 
                    $balance_pct = $total_revenue > 0 ? round(($total_balance / $total_revenue) * 100, 1) : 0;
                    echo $balance_pct . '%';
                    ?>
                </span>
            </div>
        </div>

    </div>

    <!-- OTC SALES TABLE -->
    <div class="card-custom">
        
        <!-- CARD HEADER -->
        <div class="card-header-custom">
            <h3 class="card-title-custom">
                <i class="fas fa-list"></i> OTC Sales List
                <span class="count-badge"><?= $total_records ?> records</span>
            </h3>
            
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <div class="scroll-buttons">
                    <button type="button" class="scroll-btn" onclick="scrollTable('left')" title="Scroll Left">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="scroll-btn" onclick="scrollTable('right')" title="Scroll Right">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- QUICK DATE FILTERS -->
        <div class="quick-filters">
            <span class="filter-label"><i class="fas fa-bolt"></i> Quick:</span>
            
            <a href="<?= buildFilterUrl(['quick' => 'all', 'date_from' => null, 'date_to' => null]) ?>" 
               class="quick-filter-btn <?= $quick_filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-infinity"></i> All
            </a>
            
            <a href="<?= buildFilterUrl(['quick' => 'today', 'date_from' => null, 'date_to' => null]) ?>" 
               class="quick-filter-btn today <?= $quick_filter === 'today' ? 'active' : '' ?>">
                <i class="fas fa-calendar-day"></i> Today
            </a>
            
            <a href="<?= buildFilterUrl(['quick' => '1w', 'date_from' => null, 'date_to' => null]) ?>" 
               class="quick-filter-btn <?= $quick_filter === '1w' ? 'active' : '' ?>">
                <i class="fas fa-calendar-week"></i> 1 Week
            </a>
            
            <a href="<?= buildFilterUrl(['quick' => '1m', 'date_from' => null, 'date_to' => null]) ?>" 
               class="quick-filter-btn <?= $quick_filter === '1m' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 1 Month
            </a>
            
            <a href="<?= buildFilterUrl(['quick' => '3m', 'date_from' => null, 'date_to' => null]) ?>" 
               class="quick-filter-btn <?= $quick_filter === '3m' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 3 Months
            </a>
            
            <a href="<?= buildFilterUrl(['quick' => '6m', 'date_from' => null, 'date_to' => null]) ?>" 
               class="quick-filter-btn <?= $quick_filter === '6m' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 6 Months
            </a>
            
            <a href="<?= buildFilterUrl(['quick' => '1y', 'date_from' => null, 'date_to' => null]) ?>" 
               class="quick-filter-btn <?= $quick_filter === '1y' ? 'active' : '' ?>">
                <i class="fas fa-calendar"></i> 1 Year
            </a>
            
            <a href="javascript:void(0)" 
               onclick="toggleCustomDate()" 
               class="quick-filter-btn custom <?= $quick_filter === 'custom' ? 'active' : '' ?>">
                <i class="fas fa-calendar-check"></i> Custom
            </a>
        </div>
        
        <!-- CUSTOM DATE ROW -->
        <div class="custom-date-row <?= $quick_filter === 'custom' ? 'show' : '' ?>" id="customDateRow">
            <form method="GET" style="display:contents;">
                <input type="hidden" name="quick" value="custom">
                <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>" required>
                </div>
                <button type="submit" class="btn-custom btn-primary-custom" style="height:40px;">
                    <i class="fas fa-check"></i> Apply
                </button>
            </form>
        </div>
        
        <!-- ADVANCED FILTER FORM -->
        <div style="padding:12px 20px;border-bottom:1px solid var(--page-border,#E2E8F0);">
            <form method="GET" class="filter-form-custom" id="filterForm">
                <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
                <input type="hidden" name="quick" value="<?= $quick_filter ?>">
                
                <input type="text" name="search" placeholder="Search sale #, customer..." value="<?= htmlspecialchars($search) ?>" style="width:200px;">
                
                <select name="status">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>✅ Paid</option>
                    <option value="unpaid" <?= $status_filter === 'unpaid' ? 'selected' : '' ?>>❌ Unpaid</option>
                    <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>⏳ Partial</option>
                </select>
                
                <button type="submit" class="btn-custom btn-primary-custom btn-sm-custom">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <?php if (!empty($search) || $status_filter !== 'all' || $quick_filter !== 'all'): ?>
                    <a href="otc_sales.php?branch=<?= $selected_branch_id ?>" class="btn-custom btn-outline-custom btn-sm-custom">
                        <i class="fas fa-times"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- TABLE -->
        <div class="table-wrapper-custom" id="tableWrapper">
            <?php if (count($otc_sales) > 0): ?>
                <table class="data-table-custom">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Sale Number</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th><i class="fas fa-pills"></i> Medication</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Date</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($otc_sales as $index => $sale): 
                            $balance = $sale['total_amount'] - ($sale['paid_amount'] ?? 0);
                            $status_class = $sale['payment_status'] ?? 'unpaid';
                            
                            $sale_items = $sale_items_map[$sale['id']] ?? [];
                            
                            $meds_html = '';
                            $max_show = 3;
                            $display_items = array_slice($sale_items, 0, $max_show);
                            $remaining = count($sale_items) - $max_show;
                            
                            if (count($sale_items) > 0) {
                                $meds_html .= '<div class="med-list">';
                                foreach ($display_items as $item) {
                                    $meds_html .= '<div class="med-item">';
                                    $meds_html .= '<span class="med-dot"></span>';
                                    $meds_html .= '<span class="med-name" title="' . htmlspecialchars($item['item_name']) . '">' 
                                               . htmlspecialchars($item['item_name']) . '</span>';
                                    $meds_html .= '<span class="med-qty">×' . $item['quantity'] . '</span>';
                                    $meds_html .= '</div>';
                                }
                                if ($remaining > 0) {
                                    $meds_html .= '<div class="med-more">+' . $remaining . ' more item(s)</div>';
                                }
                                $meds_html .= '</div>';
                            } else {
                                $meds_html = '<span style="font-size:0.7rem;color:var(--page-text-muted);">No items</span>';
                            }
                        ?>
                            <tr>
                                <td style="font-weight:600;color:#0B5ED7;"><?= $offset + $index + 1 ?></td>
                                <td><strong class="sale-number" style="font-size:0.72rem;"><?= htmlspecialchars($sale['sale_number']) ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></strong>
                                    <?php if (!empty($sale['sold_by_name'])): ?>
                                        <div style="font-size:0.6rem;color:var(--page-text-muted);">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($sale['sold_by_name']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="phone-number" style="font-size:0.75rem;"><?= htmlspecialchars($sale['customer_phone'] ?? '-') ?></td>
                                <td><?= $meds_html ?></td>
                                <td><strong style="color:#0B5ED7;font-family:var(--font-mono);">TSh <?= number_format($sale['total_amount']) ?></strong></td>
                                <td style="color:#059669;font-weight:700;font-family:var(--font-mono);">TSh <?= number_format($sale['paid_amount'] ?? 0) ?></td>
                                <td>
                                    <?php if ($balance > 0): ?>
                                        <span style="color:#DC2626;font-weight:700;font-family:var(--font-mono);">TSh <?= number_format($balance) ?></span>
                                    <?php else: ?>
                                        <span style="color:#059669;font-weight:700;font-family:var(--font-mono);">TSh 0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge-custom <?= $status_class ?>">
                                        <?php if ($status_class === 'paid'): ?>
                                            <i class="fas fa-check-circle"></i> Paid
                                        <?php elseif ($status_class === 'partial'): ?>
                                            <i class="fas fa-clock"></i> Partial
                                        <?php else: ?>
                                            <i class="fas fa-times-circle"></i> Unpaid
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($sale['payment_method']): ?>
                                        <span style="font-size:0.7rem;">
                                            <?php if ($sale['payment_method'] === 'cash'): ?>
                                                <i class="fas fa-money-bill"></i> Cash
                                            <?php elseif ($sale['payment_method'] === 'mpesa'): ?>
                                                <i class="fas fa-mobile-alt"></i> M-Pesa
                                            <?php elseif ($sale['payment_method'] === 'bank'): ?>
                                                <i class="fas fa-university"></i> Bank
                                            <?php else: ?>
                                                <?= htmlspecialchars($sale['payment_method']) ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--page-text-muted);font-size:0.7rem;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size:0.7rem;font-family:var(--font-mono);"><?= date('d/m/Y', strtotime($sale['created_at'])) ?></div>
                                    <div style="font-size:0.6rem;color:var(--page-text-muted);font-family:var(--font-mono);"><?= date('H:i', strtotime($sale['created_at'])) ?></div>
                                </td>
                                <td>
                                    <div style="display:flex;gap:4px;justify-content:center;">
                                        <a href="view_otc_sale.php?id=<?= $sale['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-custom btn-info-custom btn-sm-custom" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_otc_sale.php?id=<?= $sale['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-custom btn-warning-custom btn-sm-custom" title="Edit Sale">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn-custom btn-danger-custom btn-sm-custom" 
                                                title="Delete Sale"
                                                onclick="confirmDeleteSale(<?= $sale['id'] ?>, '<?= htmlspecialchars(addslashes($sale['sale_number'] ?? 'N/A')) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align:center;padding:48px 20px;color:var(--page-text-muted);">
                    <i class="fas fa-shopping-cart" style="font-size:3rem;color:var(--page-text-muted);opacity:0.5;display:block;margin-bottom:12px;"></i>
                    <p style="font-size:1rem;font-weight:600;color:var(--page-text-primary);margin-bottom:4px;">No OTC sales found</p>
                    <p style="font-size:0.85rem;margin-bottom:16px;">
                        <?php if ($quick_filter !== 'all'): ?>
                            No sales in this date range. Try changing the filter.
                        <?php else: ?>
                            Try adjusting your filters or create a new sale
                        <?php endif; ?>
                    </p>
                    <a href="add_otc_sale.php?branch=<?= $selected_branch_id ?>" class="btn-custom btn-primary-custom">
                        <i class="fas fa-plus-circle"></i> New OTC Sale
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
            <div style="padding:14px 20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;border-top:2px solid var(--page-border);">
                <span style="font-size:0.78rem;color:var(--page-text-secondary);">
                    Showing <?= $offset + 1 ?> - <?= min($offset + $limit, $total_records) ?> of <?= $total_records ?> records
                </span>
                <div class="pagination-custom">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&branch=<?= $selected_branch_id ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&quick=<?= $quick_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
                           class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="page-link active"><?= $i ?></span>
                        <?php elseif ($i <= 3 || $i > $total_pages - 3 || abs($i - $page) <= 2): ?>
                            <a href="?page=<?= $i ?>&branch=<?= $selected_branch_id ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&quick=<?= $quick_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
                               class="page-link">
                                <?= $i ?>
                            </a>
                        <?php elseif ($i == 4 || $i == $total_pages - 3): ?>
                            <span class="page-link">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&branch=<?= $selected_branch_id ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&quick=<?= $quick_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
                           class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- RECENT OTC SALES -->
    <?php if (count($recent_sales) > 0): ?>
        <div class="card-custom">
            <div class="card-header-custom">
                <h3 class="card-title-custom">
                    <i class="fas fa-clock"></i> Recent OTC Sales
                </h3>
                <a href="otc_sales.php?branch=<?= $selected_branch_id ?>" style="font-size:0.78rem;color:#0B5ED7;font-weight:600;text-decoration:none;">
                    View All →
                </a>
            </div>
            <div class="table-wrapper-custom">
                <table class="data-table-custom" style="min-width:auto;">
                    <thead>
                        <tr>
                            <th>Sale Number</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Paid</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_sales as $sale): ?>
                            <tr>
                                <td><strong class="sale-number" style="font-size:0.72rem;"><?= htmlspecialchars($sale['sale_number']) ?></strong></td>
                                <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                <td><strong style="color:#0B5ED7;font-family:var(--font-mono);">TSh <?= number_format($sale['total_amount']) ?></strong></td>
                                <td style="color:#059669;font-weight:700;font-family:var(--font-mono);">TSh <?= number_format($sale['paid_amount'] ?? 0) ?></td>
                                <td>
                                    <span class="status-badge-custom <?= $sale['payment_status'] ?? 'unpaid' ?>">
                                        <?= ucfirst($sale['payment_status'] ?? 'unpaid') ?>
                                    </span>
                                </td>
                                <td><span style="font-size:0.7rem;font-family:var(--font-mono);"><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></span></td>
                                <td>
                                    <a href="view_otc_sale.php?id=<?= $sale['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                       class="btn-custom btn-info-custom btn-sm-custom">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</main>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="modal-title">Delete OTC Sale?</h3>
        <p class="modal-text">
            Are you sure you want to delete<br>
            <strong id="deleteSaleNumber">#</strong><br>
            <span class="modal-warning">
                <i class="fas fa-exclamation-circle"></i> This action cannot be undone!
            </span>
        </p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete_otc">
            <input type="hidden" name="sale_id" id="deleteSaleId" value="">
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="modal-btn danger">
                    <i class="fas fa-trash"></i> Yes, Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // ================================================================
    // DARK MODE BACKGROUND ENFORCEMENT
    // ================================================================
    function enforceDarkModeBackground() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var body = document.body;
        var mainContent = document.querySelector('.main-content');
        
        if (isDark) {
            if (body) body.style.background = '#0F172A';
            if (mainContent) mainContent.style.background = '#0F172A';
        } else {
            if (body) body.style.background = '#F1F5F9';
            if (mainContent) mainContent.style.background = '#F1F5F9';
        }
    }

    enforceDarkModeBackground();

    document.addEventListener('darkModeChanged', function(e) {
        setTimeout(enforceDarkModeBackground, 50);
    });

    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'data-theme') {
                enforceDarkModeBackground();
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });

    // ================================================================
    // SCROLL TABLE
    // ================================================================
    function scrollTable(direction) {
        var tableWrapper = document.getElementById('tableWrapper');
        if (!tableWrapper) return;
        
        var scrollAmount = 300;
        var currentScroll = tableWrapper.scrollLeft;
        
        if (direction === 'left') {
            tableWrapper.scrollTo({ left: currentScroll - scrollAmount, behavior: 'smooth' });
        } else if (direction === 'right') {
            tableWrapper.scrollTo({ left: currentScroll + scrollAmount, behavior: 'smooth' });
        }
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT') return;
        
        if (e.key === 'ArrowLeft' && e.ctrlKey) {
            e.preventDefault();
            scrollTable('left');
        } else if (e.key === 'ArrowRight' && e.ctrlKey) {
            e.preventDefault();
            scrollTable('right');
        }
    });

    // ================================================================
    // TOGGLE CUSTOM DATE
    // ================================================================
    function toggleCustomDate() {
        var row = document.getElementById('customDateRow');
        if (!row) return;
        
        if (row.classList.contains('show')) {
            if (!<?= $quick_filter === 'custom' ? 'true' : 'false' ?>) {
                row.classList.remove('show');
            }
        } else {
            row.classList.add('show');
        }
    }

    // ================================================================
    // DELETE CONFIRMATION
    // ================================================================
    function confirmDeleteSale(saleId, saleNumber) {
        document.getElementById('deleteSaleId').value = saleId;
        document.getElementById('deleteSaleNumber').textContent = '#' + saleNumber;
        document.getElementById('deleteModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeDeleteModal();
    });

    // ================================================================
    // KEYBOARD SHORTCUT - Ctrl+K to focus search
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var si = document.querySelector('.filter-form-custom input[name="search"]');
            if (si) si.focus();
        }
    });

    console.log('%c🏥 Braick - OTC Sales V4', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Font Awesome icons zinaonekana', 'font-size:13px; color:#34D399; font-weight:bold;');
    console.log('%c✅ Font: Inter + JetBrains Mono', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Beautiful amount cards with gradients', 'font-size:13px; color:#FCD34D;');
    console.log('%c✅ Medication column', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Quick filters + Custom date', 'font-size:13px; color:#6EA8FE;');
    console.log('%c✅ View / Edit / Delete buttons', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>