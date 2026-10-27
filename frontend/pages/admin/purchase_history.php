<?php
// ================================================================
// FILE: frontend/pages/admin/purchase_history.php
// ADMIN - PURCHASE HISTORY V10.1
// ✅ V10.1: DISCOUNT INAPUNGUZA BUYING COST (sio selling)
// ✅ V10.1: Inaonyesha final buying cost baada ya discount
// ✅ V10.1: Card-based layout (badala ya table)
// ✅ V10.1: Kila card: Invoice, Date, Creator, Items, Status
// ✅ V10.1: Amounts zinaonekana kwenye View Modal
// ✅ V10.1: Item name sahihi kutoka purchase_items
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
        case 'audit': header('Location: ../audit/dashboard.php'); break;
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

$action = isset($_GET['action']) ? $_GET['action'] : '';
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Auto-migration
try {
    $stmt = $db->query("SHOW COLUMNS FROM purchases LIKE 'global_discount_amount'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `purchases` 
            ADD COLUMN `global_discount_type` VARCHAR(20) DEFAULT 'amount',
            ADD COLUMN `global_discount_value` DECIMAL(12,2) DEFAULT 0,
            ADD COLUMN `global_discount_amount` DECIMAL(12,2) DEFAULT 0,
            ADD COLUMN `final_total_buying_cost` DECIMAL(12,2) DEFAULT 0
        ");
    }
} catch (Exception $e) {}

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
                    
                    $_SESSION['purchase_history_message'] = "✅ Purchase <strong>{$purchase['invoice_number']}</strong> deleted!";
                    $_SESSION['purchase_history_message_type'] = 'success';
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
        (SELECT SUM(quantity) FROM purchase_items WHERE purchase_id = p.id) as total_qty,
        (SELECT GROUP_CONCAT(DISTINCT item_name SEPARATOR ', ') 
         FROM purchase_items 
         WHERE purchase_id = p.id AND item_name IS NOT NULL) as items_list
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
                COALESCE(pi.item_name, 
                    CASE WHEN pi.item_type = 'medicine' THEN mi.medication_name 
                         WHEN pi.item_type = 'equipment' THEN eq.equipment_name END
                ) as item_name,
                COALESCE(pi.item_category,
                    CASE WHEN pi.item_type = 'medicine' THEN mi.category 
                         WHEN pi.item_type = 'equipment' THEN eq.category END
                ) as category,
                COALESCE(pi.item_unit,
                    CASE WHEN pi.item_type = 'medicine' THEN mi.unit 
                         WHEN pi.item_type = 'equipment' THEN eq.unit END
                ) as unit,
                COALESCE(pi.item_batch_number,
                    CASE WHEN pi.item_type = 'medicine' THEN mi.batch_number 
                         WHEN pi.item_type = 'equipment' THEN eq.batch_number END
                ) as batch_number,
                COALESCE(pi.item_expiry_date,
                    CASE WHEN pi.item_type = 'medicine' THEN mi.expiry_date 
                         WHEN pi.item_type = 'equipment' THEN eq.expiry_date END
                ) as expiry_date,
                COALESCE(pi.item_supplier,
                    CASE WHEN pi.item_type = 'medicine' THEN mi.supplier 
                         WHEN pi.item_type = 'equipment' THEN eq.supplier END
                ) as supplier,
                COALESCE(pi.added_by_name, u.full_name) as added_by_full_name
            FROM purchase_items pi
            LEFT JOIN medications_inventory mi ON pi.item_id = mi.id AND pi.item_type = 'medicine'
            LEFT JOIN medical_equipment eq ON pi.item_id = eq.id AND pi.item_type = 'equipment'
            LEFT JOIN users u ON pi.added_by = u.id
            WHERE pi.purchase_id = ?
            ORDER BY pi.item_name ASC, pi.added_at DESC
        ");
        $stmt->execute([$view_id]);
        $view_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ✅ V10.1: STATISTICS - DISCOUNT-AWARE
$stats_where = "";
$stats_params = [];
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stats_where = " AND branch_id = ?";
    $stats_params = [(int)$selected_branch_id];
}

$stmt = $db->prepare("SELECT COUNT(*) as count FROM purchases WHERE 1=1" . $stats_where);
$stmt->execute($stats_params);
$total_purchases = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM purchases WHERE status = 'COMPLETED'" . $stats_where);
$stmt->execute($stats_params);
$completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM purchases WHERE status = 'IN_PROGRESS'" . $stats_where);
$stmt->execute($stats_params);
$in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM purchases WHERE status = 'CANCELLED'" . $stats_where);
$stmt->execute($stats_params);
$cancelled_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ✅ V10.1: Tumia final_total_buying_cost (baada ya discount)
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(COALESCE(NULLIF(final_total_buying_cost, 0), total_buying_cost)), 0) as total_buying,
        COALESCE(SUM(total_selling_value), 0) as total_selling,
        COALESCE(SUM(global_discount_amount), 0) as total_discount
    FROM purchases 
    WHERE status = 'COMPLETED'" . $stats_where
);
$stmt->execute($stats_params);
$stats_totals = $stmt->fetch(PDO::FETCH_ASSOC);
$total_spending = (float)($stats_totals['total_buying'] ?? 0);
$total_selling = (float)($stats_totals['total_selling'] ?? 0);
$total_discount = (float)($stats_totals['total_discount'] ?? 0);
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

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<style>
    :root {
        --ph-primary: #0B5ED7;
        --ph-primary-dark: #0A4CA8;
        --ph-primary-light: #E8F0FE;
        --ph-success: #059669;
        --ph-success-dark: #047857;
        --ph-success-light: #D1FAE5;
        --ph-warning: #D97706;
        --ph-warning-light: #FEF3C7;
        --ph-danger: #DC2626;
        --ph-danger-light: #FEE2E2;
        --ph-purple: #7C3AED;
        --ph-purple-light: #EDE9FE;
        --ph-teal: #0D9488;
        --ph-teal-light: #CCFBF1;
        --ph-bg-body: #F1F5F9;
        --ph-bg-card: #FFFFFF;
        --ph-border-color: #E2E8F0;
        --ph-text-primary: #1E293B;
        --ph-text-secondary: #64748B;
        --ph-text-muted: #94A3B8;
    }

    [data-theme="dark"] {
        --ph-bg-body: #0F172A;
        --ph-bg-card: #1E293B;
        --ph-border-color: #334155;
        --ph-text-primary: #F1F5F9;
        --ph-text-secondary: #94A3B8;
        --ph-text-muted: #64748B;
    }

    html[data-theme="dark"] body { background: #0F172A !important; }
    html[data-theme="dark"] .main-content { background: #0F172A !important; color: #F1F5F9; }

    /* PAGE HEADER */
    .page-header-box-ph {
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

    .page-header-box-ph::before {
        content: '';
        position: absolute;
        top: -60%; right: -10%;
        width: 350px; height: 350px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-box-ph .page-title-ph {
        color: white; font-size: 1.4rem; font-weight: 700;
        display: flex; align-items: center; gap: 8px;
        flex-wrap: wrap; position: relative; z-index: 1; margin: 0;
    }

    .page-header-box-ph .page-title-ph .role-badge-display-ph {
        background: rgba(255,255,255,0.2); color: white;
        padding: 2px 10px; border-radius: 20px;
        font-size: 0.55rem; font-weight: 600; text-transform: uppercase;
    }

    .page-header-box-ph .page-title-ph .branch-name-display-ph {
        background: rgba(255,255,255,0.15); padding: 2px 12px;
        border-radius: 20px; font-size: 0.7rem; font-weight: 500; color: white;
    }

    .header-actions-ph {
        display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
        position: relative; z-index: 1;
    }

    .btn-back-ph {
        background: rgba(255,255,255,0.15); color: white;
        padding: 6px 16px; border-radius: 8px;
        font-weight: 500; font-size: 0.75rem;
        border: 1px solid rgba(255,255,255,0.15);
        cursor: pointer; transition: all 0.3s ease;
        display: inline-flex; align-items: center; gap: 6px;
        text-decoration: none;
    }

    .btn-back-ph:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px); color: white;
    }

    /* STATS CARDS */
    .stats-grid-ph {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 12px; margin-bottom: 20px;
    }

    .stat-card-ph {
        border-radius: 12px; padding: 14px 16px;
        transition: all 0.3s ease; cursor: pointer;
        text-decoration: none; display: block;
        color: white; box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        min-height: 80px;
    }

    .stat-card-ph:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
    .stat-card-ph .stat-number { font-size: 1.4rem; font-weight: 700; line-height: 1.2; }
    .stat-card-ph .stat-label { font-size: 0.6rem; color: rgba(255,255,255,0.85); font-weight: 500; margin-top: 2px; }
    .stat-card-ph .stat-icon { font-size: 1.2rem; opacity: 0.8; float: right; }
    .stat-card-ph .stat-sub { font-size: 0.55rem; color: rgba(255,255,255,0.5); }

    .stat-card-ph.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .stat-card-ph.green { background: linear-gradient(135deg, #059669, #047857); }
    .stat-card-ph.orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .stat-card-ph.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
    .stat-card-ph.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    .stat-card-ph.teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
    .stat-card-ph.dark-red { background: linear-gradient(135deg, #7F1D1D, #450A0A); }

    /* CARD */
    .card-ph {
        background: var(--ph-bg-card);
        border-radius: 14px; padding: 18px 22px;
        border: 2px solid var(--ph-border-color);
        transition: all 0.3s ease; margin-bottom: 20px;
    }

    .card-ph:hover { border-color: var(--ph-primary); box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06); }

    /* FILTERS */
    .filter-group-ph {
        display: flex; flex-wrap: wrap;
        gap: 6px; margin-bottom: 14px;
    }

    .filter-btn-ph {
        padding: 4px 14px; border-radius: 16px;
        font-size: 0.7rem; font-weight: 600;
        border: 2px solid var(--ph-border-color);
        background: transparent; color: var(--ph-text-secondary);
        cursor: pointer; transition: all 0.3s ease;
        text-decoration: none;
    }

    .filter-btn-ph:hover { border-color: var(--ph-primary); color: var(--ph-primary); }
    .filter-btn-ph.active { background: var(--ph-primary); border-color: var(--ph-primary); color: white; }
    .filter-btn-ph.clear-filter-ph { border-color: var(--ph-danger); color: var(--ph-danger); }
    .filter-btn-ph.clear-filter-ph:hover { background: var(--ph-danger); color: white; }
    .filter-btn-ph.cancelled-filter-ph { border-color: #7F1D1D; color: #7F1D1D; }
    .filter-btn-ph.cancelled-filter-ph:hover { background: #7F1D1D; color: white; }
    .filter-btn-ph.cancelled-filter-ph.active { background: #7F1D1D; border-color: #7F1D1D; color: white; }

    .search-form-ph {
        display: flex; gap: 8px;
        flex-wrap: wrap; align-items: center;
    }

    .search-form-ph input[type="text"],
    .search-form-ph select,
    .search-form-ph input[type="date"] {
        padding: 8px 14px;
        border: 2px solid var(--ph-border-color);
        border-radius: 8px; font-size: 0.82rem;
        background: var(--ph-bg-card); color: var(--ph-text-primary);
        outline: none; transition: all 0.3s ease;
        flex: 1; min-width: 120px;
        font-family: inherit; height: 40px;
    }

    .search-form-ph input:focus,
    .search-form-ph select:focus {
        border-color: var(--ph-primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }

    .btn-search-ph {
        padding: 8px 20px; border-radius: 10px;
        font-weight: 600; font-size: 0.85rem; border: none;
        background: var(--ph-primary); color: white;
        cursor: pointer; transition: all 0.3s ease;
        font-family: inherit; height: 40px;
    }

    .btn-search-ph:hover {
        background: var(--ph-primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }

    .btn-reset-ph {
        padding: 8px 16px; border-radius: 10px;
        font-weight: 600; font-size: 0.85rem;
        border: 2px solid var(--ph-border-color);
        background: transparent; color: var(--ph-text-secondary);
        cursor: pointer; transition: all 0.3s ease;
        text-decoration: none; height: 40px;
        display: inline-flex; align-items: center;
    }

    .btn-reset-ph:hover { border-color: var(--ph-danger); color: var(--ph-danger); }

    /* ✅ V10.1: PURCHASES CARDS GRID */
    .search-bar-ph {
        display: flex; justify-content: space-between;
        align-items: center; flex-wrap: wrap;
        gap: 12px; margin-bottom: 16px;
        padding-bottom: 14px;
        border-bottom: 2px solid var(--ph-border-color);
    }

    .search-box-ph {
        position: relative; flex: 1;
        max-width: 500px; min-width: 280px;
    }

    .search-box-ph i {
        position: absolute; left: 14px;
        top: 50%; transform: translateY(-50%);
        color: rgba(255,255,255,0.95); font-size: 0.9rem;
        pointer-events: none; z-index: 1;
    }

    .search-box-ph input {
        width: 100%; padding: 11px 16px 11px 44px;
        border: 2px solid var(--ph-primary);
        border-radius: 10px; font-size: 0.85rem;
        background: linear-gradient(135deg, var(--ph-primary), var(--ph-primary-dark));
        color: white; outline: none; font-weight: 500;
        height: 44px; box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        transition: all 0.3s ease; font-family: inherit;
    }

    .search-box-ph input::placeholder { color: rgba(255,255,255,0.85); font-weight: 400; }
    .search-box-ph input:focus {
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.25), 0 6px 20px rgba(11, 94, 215, 0.35);
        transform: translateY(-1px);
    }

    .search-count-ph {
        font-size: 0.8rem; color: var(--ph-text-secondary);
        white-space: nowrap;
    }
    .search-count-ph strong { color: var(--ph-primary); }

    .purchases-grid-ph {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 16px;
    }

    .purchase-card-ph {
        background: var(--ph-bg-card);
        border: 2px solid var(--ph-border-color);
        border-radius: 14px; overflow: hidden;
        transition: all 0.3s ease;
        position: relative; display: flex;
        flex-direction: column;
    }

    .purchase-card-ph:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }

    .purchase-card-ph.status-completed { border-color: #059669; }
    .purchase-card-ph.status-in_progress { border-color: #D97706; }
    .purchase-card-ph.status-cancelled { border-color: #DC2626; opacity: 0.85; }

    .purchase-card-ph .card-header-strip {
        padding: 14px 18px;
        display: flex; justify-content: space-between;
        align-items: center; gap: 10px;
        position: relative; overflow: hidden;
    }

    .purchase-card-ph.status-completed .card-header-strip { background: linear-gradient(135deg, #059669, #047857); }
    .purchase-card-ph.status-in_progress .card-header-strip { background: linear-gradient(135deg, #D97706, #B45309); }
    .purchase-card-ph.status-cancelled .card-header-strip { background: linear-gradient(135deg, #DC2626, #991B1B); }

    .purchase-card-ph .card-header-strip::before {
        content: ''; position: absolute;
        top: -50%; right: -20%;
        width: 200px; height: 200px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%; pointer-events: none;
    }

    .purchase-card-ph .invoice-number-ph {
        font-size: 0.95rem; font-weight: 800;
        color: white; display: flex;
        align-items: center; gap: 6px;
        position: relative; z-index: 1;
        font-family: 'JetBrains Mono', monospace;
    }

    .purchase-card-ph .status-pill-ph {
        padding: 3px 12px; border-radius: 20px;
        font-size: 0.6rem; font-weight: 800;
        text-transform: uppercase; letter-spacing: 0.05em;
        background: rgba(255,255,255,0.25); color: white;
        border: 1px solid rgba(255,255,255,0.3);
        position: relative; z-index: 1;
        display: inline-flex; align-items: center; gap: 4px;
    }

    .purchase-card-ph .card-body-ph {
        padding: 14px 18px; flex: 1;
        display: flex; flex-direction: column; gap: 10px;
    }

    .purchase-card-ph .meta-row-ph {
        display: flex; align-items: center;
        gap: 8px; font-size: 0.75rem;
        color: var(--ph-text-secondary);
    }

    .purchase-card-ph .meta-row-ph i {
        color: var(--ph-primary); font-size: 0.75rem;
        width: 16px; text-align: center;
    }

    .purchase-card-ph .meta-row-ph strong {
        color: var(--ph-text-primary); font-weight: 700;
    }

    .purchase-card-ph .type-badge-ph {
        padding: 4px 12px; border-radius: 12px;
        font-size: 0.7rem; font-weight: 700;
        display: inline-flex; align-items: center; gap: 4px;
    }

    .purchase-card-ph .type-badge-ph.medicine {
        background: #E8F0FE; color: #0B5ED7;
        border: 1px solid rgba(11, 94, 215, 0.2);
    }
    .purchase-card-ph .type-badge-ph.equipment {
        background: #EDE9FE; color: #7C3AED;
        border: 1px solid rgba(124, 58, 237, 0.2);
    }

    .purchase-card-ph .items-summary-ph {
        background: var(--ph-bg-body);
        border-radius: 8px; padding: 10px 12px;
        border-left: 3px solid var(--ph-primary);
    }

    .purchase-card-ph .items-summary-ph .summary-label {
        font-size: 0.58rem; text-transform: uppercase;
        color: var(--ph-text-secondary); font-weight: 700;
        letter-spacing: 0.05em; margin-bottom: 4px;
        display: flex; align-items: center; gap: 4px;
    }

    .purchase-card-ph .items-summary-ph .summary-value {
        font-size: 0.78rem; color: var(--ph-text-primary);
        font-weight: 600; line-height: 1.4;
        display: -webkit-box; -webkit-line-clamp: 2;
        -webkit-box-orient: vertical; overflow: hidden;
    }

    .purchase-card-ph .stats-row-ph {
        display: grid; grid-template-columns: 1fr 1fr;
        gap: 8px;
    }

    .purchase-card-ph .stat-mini-ph {
        background: var(--ph-bg-body);
        border-radius: 8px; padding: 8px 10px;
        text-align: center;
    }

    .purchase-card-ph .stat-mini-ph .stat-label {
        font-size: 0.55rem; text-transform: uppercase;
        color: var(--ph-text-secondary); font-weight: 700;
        letter-spacing: 0.04em; margin-bottom: 2px;
    }

    .purchase-card-ph .stat-mini-ph .stat-value {
        font-size: 0.9rem; font-weight: 800;
        font-family: 'JetBrains Mono', monospace;
        color: var(--ph-primary);
    }

    .purchase-card-ph .discount-badge-ph {
        background: linear-gradient(135deg, #FEF3C7, #FDE68A);
        border: 1px solid #D97706;
        border-radius: 8px; padding: 6px 10px;
        font-size: 0.65rem; font-weight: 800;
        color: #92400E; display: flex;
        align-items: center; gap: 5px;
        justify-content: center;
    }

    .purchase-card-ph .cancel-reason-ph {
        background: #FEE2E2; border: 1px solid #DC2626;
        border-radius: 8px; padding: 8px 10px;
        font-size: 0.7rem; color: #991B1B;
        font-weight: 600;
    }

    .purchase-card-ph .cancel-reason-ph .reason-label {
        font-size: 0.55rem; text-transform: uppercase;
        font-weight: 800; letter-spacing: 0.04em;
        margin-bottom: 2px; display: block;
    }

    .purchase-card-ph .card-footer-ph {
        padding: 12px 18px;
        border-top: 2px solid var(--ph-border-color);
        display: flex; gap: 8px;
        flex-wrap: wrap; background: var(--ph-bg-body);
    }

    .purchase-card-ph .btn-card-ph {
        flex: 1; padding: 8px 12px;
        border-radius: 8px; font-size: 0.72rem;
        font-weight: 700; border: none;
        cursor: pointer; display: inline-flex;
        align-items: center; justify-content: center;
        gap: 5px; text-decoration: none;
        transition: all 0.25s ease;
        white-space: nowrap; font-family: inherit;
        min-width: 80px;
    }

    .purchase-card-ph .btn-card-ph.view {
        background: linear-gradient(135deg, var(--ph-primary), var(--ph-primary-dark));
        color: white; box-shadow: 0 2px 6px rgba(11, 94, 215, 0.2);
    }

    .purchase-card-ph .btn-card-ph.view:hover {
        background: linear-gradient(135deg, var(--ph-primary-dark), #083B87);
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(11, 94, 215, 0.4);
        color: white;
    }

    .purchase-card-ph .btn-card-ph.edit {
        background: linear-gradient(135deg, #D97706, #B45309);
        color: white; box-shadow: 0 2px 6px rgba(217, 119, 6, 0.2);
    }

    .purchase-card-ph .btn-card-ph.edit:hover {
        background: linear-gradient(135deg, #B45309, #92400E);
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(217, 119, 6, 0.4);
        color: white;
    }

    .purchase-card-ph .btn-card-ph.pdf {
        background: linear-gradient(135deg, #DC2626, #991B1B);
        color: white; box-shadow: 0 2px 6px rgba(220, 38, 38, 0.2);
    }

    .purchase-card-ph .btn-card-ph.pdf:hover {
        background: linear-gradient(135deg, #991B1B, #7F1D1D);
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(220, 38, 38, 0.4);
        color: white;
    }

    .purchase-card-ph .btn-card-ph.delete {
        background: linear-gradient(135deg, #DC2626, #991B1B);
        color: white; box-shadow: 0 2px 6px rgba(220, 38, 38, 0.2);
    }

    .purchase-card-ph .btn-card-ph.delete:hover {
        background: linear-gradient(135deg, #991B1B, #7F1D1D);
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(220, 38, 38, 0.4);
        color: white;
    }

    .empty-state-ph {
        text-align: center; padding: 60px 20px;
        color: var(--ph-text-secondary);
    }

    .empty-state-ph i {
        font-size: 4rem; color: var(--ph-border-color);
        display: block; margin-bottom: 12px;
    }

    .empty-state-ph p { font-size: 0.95rem; font-weight: 600; margin-bottom: 6px; }
    .empty-state-ph .sub { font-size: 0.8rem; font-weight: 400; }

    .no-results-ph {
        display: none; text-align: center;
        padding: 60px 20px; color: var(--ph-text-secondary);
    }

    .no-results-ph i {
        font-size: 3rem; color: var(--ph-border-color);
        display: block; margin-bottom: 12px;
    }

    /* MESSAGE */
    .message-box-ph {
        padding: 12px 18px; border-radius: 10px;
        margin-bottom: 16px; display: flex;
        align-items: center; gap: 10px;
        font-weight: 500; font-size: 0.9rem;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .message-box-ph.success { background: #D1FAE5; color: #065F46; border-left: 5px solid #059669; }
    .message-box-ph.error { background: #FEE2E2; color: #991B1B; border-left: 5px solid #DC2626; }

    [data-theme="dark"] .message-box-ph.success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .message-box-ph.error { background: #3A1A1A; color: #F87171; }

    /* MODAL */
    .modal-overlay-ph {
        display: none; position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.7); z-index: 999999;
        justify-content: center; align-items: center;
        padding: 20px; backdrop-filter: blur(4px);
    }

    .modal-overlay-ph.show { display: flex; }

    .modal-content-ph {
        background: var(--ph-bg-card);
        border-radius: 12px; max-width: 1200px;
        width: 100%; max-height: 90vh; overflow-y: auto;
        padding: 24px 28px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        border: 2px solid var(--ph-border-color);
        animation: slideUp 0.3s ease;
        position: relative; z-index: 1000000;
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .modal-header-ph {
        display: flex; justify-content: space-between;
        align-items: center; padding-bottom: 12px;
        border-bottom: 2px solid var(--ph-border-color);
        margin-bottom: 16px;
    }

    .modal-header-ph .modal-title-ph {
        font-size: 1.1rem; font-weight: 700;
        color: var(--ph-primary);
        display: flex; align-items: center;
        gap: 8px; flex-wrap: wrap;
    }

    .modal-close-ph {
        background: none; border: none;
        font-size: 1.5rem; cursor: pointer;
        color: var(--ph-text-secondary);
        transition: all 0.3s ease;
        text-decoration: none; line-height: 1;
    }

    .modal-close-ph:hover { color: var(--ph-danger); transform: rotate(90deg); }

    .modal-actions-ph {
        display: flex; gap: 10px;
        padding-top: 14px;
        border-top: 2px solid var(--ph-border-color);
        margin-top: 16px; flex-wrap: wrap;
    }

    .btn-close-modal-ph {
        background: transparent;
        color: var(--ph-text-secondary);
        border: 2px solid var(--ph-border-color);
        padding: 10px 24px; border-radius: 8px;
        font-weight: 600; font-size: 0.9rem;
        cursor: pointer; transition: all 0.3s ease;
        text-decoration: none; font-family: inherit;
        display: inline-flex; align-items: center;
        gap: 6px;
    }

    .btn-close-modal-ph:hover { border-color: var(--ph-danger); color: var(--ph-danger); }

    .btn-print-invoice-ph {
        background: var(--ph-danger);
        color: white; padding: 10px 24px;
        border-radius: 8px; font-weight: 600;
        font-size: 0.9rem; border: none;
        cursor: pointer; transition: all 0.3s ease;
        display: inline-flex; align-items: center;
        gap: 6px;
    }

    .btn-print-invoice-ph:hover { background: #991B1B; transform: translateY(-2px); color: white; }

    /* VIEW GRID */
    .view-grid-ph {
        display: grid; grid-template-columns: 1fr 1fr;
        gap: 12px; margin-bottom: 14px;
    }

    .view-item-ph {
        padding: 12px 16px;
        background: var(--ph-bg-body);
        border-radius: 8px;
        border: 1px solid var(--ph-border-color);
    }

    .view-item-ph .label {
        font-size: 0.6rem; text-transform: uppercase;
        color: var(--ph-text-secondary);
        font-weight: 700; letter-spacing: 0.05em;
    }

    .view-item-ph .value {
        font-size: 0.92rem; font-weight: 600;
        color: var(--ph-text-primary); margin-top: 3px;
    }

    .view-item-ph.full-width { grid-column: 1 / -1; }

    .view-item-ph.amount-box {
        padding: 14px 18px;
    }

    .view-item-ph.amount-box .label {
        font-size: 0.62rem; margin-bottom: 4px;
    }

    .view-item-ph.amount-box .value {
        font-size: 1.15rem; font-weight: 900;
        font-family: 'JetBrains Mono', monospace;
        margin-top: 4px;
    }

    /* CANCELLED INFO BOX */
    .cancelled-info-box-ph {
        background: linear-gradient(135deg, #FEF2F2, #FEE2E2);
        border: 2px solid #DC2626;
        border-radius: 10px; padding: 14px 16px;
        margin-bottom: 16px; display: flex;
        gap: 12px; align-items: flex-start;
    }

    [data-theme="dark"] .cancelled-info-box-ph { background: linear-gradient(135deg, #3A1A1A, #4A1F1F); }

    .cancelled-info-box-ph i { color: #DC2626; font-size: 1.5rem; flex-shrink: 0; margin-top: 2px; }
    .cancelled-info-box-ph .info-text-ph { flex: 1; }

    .cancelled-info-box-ph .info-text-ph .info-title-ph {
        font-size: 0.85rem; font-weight: 700;
        color: #991B1B; margin-bottom: 6px;
        display: flex; align-items: center; gap: 6px;
    }

    [data-theme="dark"] .cancelled-info-box-ph .info-text-ph .info-title-ph { color: #FCA5A5; }

    .cancelled-info-box-ph .info-text-ph .info-row-ph {
        font-size: 0.78rem; color: #7F1D1D;
        margin-bottom: 3px; display: flex;
        gap: 6px; align-items: flex-start;
    }

    [data-theme="dark"] .cancelled-info-box-ph .info-text-ph .info-row-ph { color: #FCA5A5; }

    .cancelled-info-box-ph .info-text-ph .info-row-ph .info-label-ph { font-weight: 600; min-width: 100px; }
    .cancelled-info-box-ph .info-text-ph .info-row-ph .info-value-ph { font-weight: 600; color: #450A0A; word-break: break-word; }

    [data-theme="dark"] .cancelled-info-box-ph .info-text-ph .info-row-ph .info-value-ph { color: #FECACA; }

    /* INVOICE GROUP */
    .invoice-group-ph {
        border: 2px solid var(--ph-border-color);
        border-radius: 10px; margin-bottom: 14px;
        overflow: hidden; background: var(--ph-bg-card);
    }

    .invoice-group-header-ph {
        background: linear-gradient(135deg, var(--ph-primary), var(--ph-primary-dark));
        color: white; padding: 12px 18px;
        display: flex; justify-content: space-between;
        align-items: center; flex-wrap: wrap; gap: 10px;
    }

    .invoice-group-header-ph .ig-title-ph {
        font-size: 0.9rem; font-weight: 700;
        display: flex; align-items: center; gap: 8px;
    }

    .invoice-group-header-ph .ig-stats-ph {
        display: flex; gap: 8px; flex-wrap: wrap;
    }

    .invoice-group-header-ph .ig-stat-ph {
        background: rgba(255,255,255,0.2);
        padding: 4px 12px; border-radius: 20px;
        font-size: 0.72rem; font-weight: 600;
        display: inline-flex; align-items: center; gap: 5px;
    }

    .invoice-group-body-ph { padding: 0; overflow-x: auto; }

    /* VIEW ITEMS TABLE */
    .view-items-table-ph {
        width: 100%; border-collapse: collapse;
        font-size: 0.75rem; table-layout: auto;
        min-width: 1100px;
    }

    .view-items-table-ph thead th {
        background: rgba(11, 94, 215, 0.08);
        color: var(--ph-primary); padding: 10px 10px;
        font-size: 0.6rem; text-transform: uppercase;
        font-weight: 700; text-align: left;
        border-bottom: 2px solid var(--ph-primary);
        letter-spacing: 0.04em; white-space: nowrap;
    }

    [data-theme="dark"] .view-items-table-ph thead th {
        background: rgba(110, 168, 254, 0.1);
        color: #6EA8FE;
    }

    .view-items-table-ph tbody td {
        padding: 10px 10px;
        border-bottom: 1px solid var(--ph-border-color);
        color: var(--ph-text-primary);
        font-size: 0.76rem; white-space: nowrap;
    }

    .view-items-table-ph tbody tr:nth-child(even) { background: rgba(11, 94, 215, 0.03); }
    [data-theme="dark"] .view-items-table-ph tbody tr:nth-child(even) { background: rgba(11, 94, 215, 0.08); }

    .view-items-table-ph tbody tr:hover { background: rgba(209, 250, 229, 0.4); }
    [data-theme="dark"] .view-items-table-ph tbody tr:hover { background: rgba(26, 58, 42, 0.5); }

    .item-name-cell-ph {
        display: flex; flex-direction: column;
        gap: 3px; white-space: normal;
        min-width: 200px;
    }

    .item-name-cell-ph strong {
        font-size: 0.82rem; font-weight: 700;
        color: var(--ph-text-primary);
    }

    .item-name-cell-ph .item-meta-ph {
        font-size: 0.65rem; color: var(--ph-text-muted);
        display: flex; gap: 8px; flex-wrap: wrap;
    }

    .item-batch-tag-ph {
        background: #FEF3C7; color: #D97706;
        padding: 2px 8px; border-radius: 6px;
        font-size: 0.62rem; font-weight: 700;
        font-family: 'JetBrains Mono', monospace;
    }

    [data-theme="dark"] .item-batch-tag-ph { background: #3D2E0A; color: #FBBF24; }

    .added-by-tag-ph {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 12px; border-radius: 20px;
        font-size: 0.7rem; font-weight: 600;
        background: #EDE9FE; color: #7C3AED;
        white-space: nowrap;
        border: 1px solid rgba(124, 58, 237, 0.2);
    }

    [data-theme="dark"] .added-by-tag-ph {
        background: #2D1B4E; color: #C4B5FD;
        border-color: rgba(196, 181, 253, 0.2);
    }

    /* DISCOUNT DETAIL BOX */
    .discount-detail-box-ph {
        background: linear-gradient(135deg, #FEF3C7, #FDE68A);
        border: 2px solid #D97706;
        border-radius: 10px; padding: 14px 18px;
        margin-bottom: 16px; display: flex;
        justify-content: space-between;
        align-items: center; gap: 16px;
        flex-wrap: wrap;
    }

    .discount-detail-box-ph .discount-info-ph {
        display: flex; align-items: center;
        gap: 12px; flex: 1; min-width: 200px;
    }

    .discount-detail-box-ph .discount-icon-ph {
        width: 44px; height: 44px;
        background: #D97706; color: white;
        border-radius: 10px; display: flex;
        align-items: center; justify-content: center;
        font-size: 1.4rem; font-weight: 900;
        flex-shrink: 0;
    }

    .discount-detail-box-ph .discount-text-ph .discount-label-ph {
        font-size: 0.6rem; text-transform: uppercase;
        font-weight: 800; color: #92400E;
        letter-spacing: 0.05em; margin-bottom: 2px;
    }

    .discount-detail-box-ph .discount-text-ph .discount-desc-ph {
        font-size: 0.8rem; color: #78350F;
        font-weight: 600;
    }

    .discount-detail-box-ph .discount-amount-ph {
        font-size: 1.3rem; font-weight: 900;
        color: #DC2626;
        font-family: 'JetBrains Mono', monospace;
        white-space: nowrap;
    }

    /* DELETE MODAL */
    .delete-warning-icon-ph {
        text-align: center; font-size: 3rem;
        color: var(--ph-danger); margin-bottom: 10px;
    }

    .delete-modal-content-ph {
        background: var(--ph-bg-card);
        border-radius: 12px; max-width: 500px;
        width: 100%; padding: 24px 28px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        border: 2px solid var(--ph-border-color);
    }

    .delete-modal-content-ph .modal-header-ph .modal-title-ph { color: var(--ph-danger); }

    .btn-confirm-delete-ph {
        background: var(--ph-danger);
        color: white; padding: 10px 28px;
        border-radius: 8px; font-weight: 600;
        font-size: 0.9rem; border: none;
        cursor: pointer; transition: all 0.3s ease;
        flex: 1; font-family: inherit;
    }

    .btn-confirm-delete-ph:hover { background: #991B1B; transform: translateY(-2px); }

    .delete-reason-textarea-ph {
        width: 100%; padding: 10px 14px;
        border: 2px solid var(--ph-border-color);
        border-radius: 8px; font-size: 0.9rem;
        resize: vertical; min-height: 60px;
        background: var(--ph-bg-body);
        color: var(--ph-text-primary);
        transition: all 0.3s ease; font-family: inherit;
    }

    .delete-reason-textarea-ph:focus {
        border-color: var(--ph-danger);
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        outline: none;
    }

    .delete-note-ph {
        background: #FEF3C7;
        padding: 10px 14px;
        border-radius: 8px;
        border: 2px solid #D97706;
        margin-bottom: 14px;
    }

    [data-theme="dark"] .delete-note-ph { background: #3D2E0A; }

    .delete-note-ph p {
        font-size: 0.8rem; color: #D97706;
        font-weight: 600; margin: 0;
    }

    .delete-note-ph p i { margin-right: 4px; }

    /* FOOTER */
    .footer-ph {
        padding: 12px 0;
        border-top: 1px solid var(--ph-border-color);
        margin-top: 20px; text-align: center;
        font-size: 0.65rem; color: var(--ph-text-secondary);
    }

    .footer-ph .footer-brand-ph { color: var(--ph-primary); font-weight: 600; }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up-ph {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }

    @media (max-width: 1200px) {
        .stats-grid-ph { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 1024px) {
        .stats-grid-ph { grid-template-columns: repeat(3, 1fr); }
        .purchases-grid-ph { grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
    }

    @media (max-width: 768px) {
        .stats-grid-ph { grid-template-columns: repeat(2, 1fr); }
        .search-form-ph { flex-direction: column; align-items: stretch; }
        .search-form-ph input { min-width: 100%; }
        .filter-group-ph { justify-content: center; }
        .card-ph { padding: 12px 14px; }
        .page-header-box-ph .page-title-ph { font-size: 1.1rem; }
        .stat-card-ph .stat-number { font-size: 1.1rem; }
        .stat-card-ph { padding: 10px 12px; min-height: 65px; }
        .header-actions-ph { flex-direction: column; align-items: stretch; width: 100%; }
        .header-actions-ph .btn-back-ph { width: 100%; justify-content: center; }
        .view-grid-ph { grid-template-columns: 1fr; }
        .modal-content-ph { padding: 16px; }
        .delete-modal-content-ph { padding: 16px; }
        .search-box-ph { min-width: 100%; max-width: 100%; }
        .purchases-grid-ph { grid-template-columns: 1fr; }
        .view-items-table-ph { min-width: 1000px; }
    }

    @media (max-width: 480px) {
        .stats-grid-ph { grid-template-columns: 1fr 1fr; }
        .stat-card-ph .stat-number { font-size: 0.9rem; }
        .page-header-box-ph { flex-direction: column; align-items: flex-start !important; }
    }
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-box-ph animate-fade-in-up-ph">
        <div>
            <h1 class="page-title-ph">
                <i class="fas fa-history"></i>
                Purchase History
                <span class="role-badge-display-ph">ADMIN</span>
                <span class="branch-name-display-ph">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($display_branch_name) ?>
                </span>
            </h1>
        </div>
        <div class="header-actions-ph">
            <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="btn-back-ph">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
            <a href="purchases.php?branch=<?= $selected_branch_id ?>" class="btn-back-ph">
                <i class="fas fa-shopping-cart"></i> New Purchase
            </a>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div class="message-box-ph <?= $message_type ?>" id="messageBox">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <span><?= $message ?></span>
            <button onclick="this.parentElement.style.display='none'" style="margin-left:auto;background:none;border:none;cursor:pointer;font-size:1.1rem;color:inherit;">&times;</button>
        </div>
    <?php endif; ?>

    <!-- STATS CARDS -->
    <div class="stats-grid-ph animate-fade-in-up-ph" style="animation-delay:0.05s;">
        <div class="stat-card-ph blue">
            <span class="stat-icon"><i class="fas fa-shopping-cart"></i></span>
            <div class="stat-number"><?= $total_purchases ?></div>
            <div class="stat-label">Total Purchases</div>
            <div class="stat-sub">All purchases</div>
        </div>
        <div class="stat-card-ph green">
            <span class="stat-icon"><i class="fas fa-check-circle"></i></span>
            <div class="stat-number"><?= $completed_count ?></div>
            <div class="stat-label">Completed</div>
            <div class="stat-sub">Finished purchases</div>
        </div>
        <div class="stat-card-ph orange">
            <span class="stat-icon"><i class="fas fa-spinner fa-spin"></i></span>
            <div class="stat-number"><?= $in_progress_count ?></div>
            <div class="stat-label">In Progress</div>
            <div class="stat-sub">Pending completion</div>
        </div>
        <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&status=CANCELLED" class="stat-card-ph dark-red" style="text-decoration:none;">
            <span class="stat-icon"><i class="fas fa-ban"></i></span>
            <div class="stat-number"><?= $cancelled_count ?></div>
            <div class="stat-label">Cancelled</div>
            <div class="stat-sub">Click to view reasons</div>
        </a>
        <div class="stat-card-ph red">
            <span class="stat-icon"><i class="fas fa-coins"></i></span>
            <div class="stat-number">TSh <?= formatMoneyShort($total_spending) ?></div>
            <div class="stat-label">Final Buying Cost</div>
            <div class="stat-sub">
                <?php if ($total_discount > 0): ?>
                    After TSh <?= formatMoneyShort($total_discount) ?> discount
                <?php else: ?>
                    Money spent on purchases
                <?php endif; ?>
            </div>
        </div>
        <div class="stat-card-ph purple">
            <span class="stat-icon"><i class="fas fa-chart-line"></i></span>
            <div class="stat-number">
                TSh <?= formatMoneyShort($total_profit) ?>
            </div>
            <div class="stat-label"><?= $total_profit >= 0 ? '💰 Total Profit' : '📉 Total Loss' ?></div>
            <div class="stat-sub"><?= $total_spending > 0 ? round(($total_profit / $total_spending) * 100, 1) . '% margin' : 'No data' ?></div>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="card-ph animate-fade-in-up-ph" style="animation-delay:0.1s;">
        <div class="filter-group-ph">
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&status=all" class="filter-btn-ph <?= $status_filter === 'all' ? 'active' : '' ?>">All</a>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&status=COMPLETED" class="filter-btn-ph <?= $status_filter === 'COMPLETED' ? 'active' : '' ?>">✅ Completed</a>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&status=IN_PROGRESS" class="filter-btn-ph <?= $status_filter === 'IN_PROGRESS' ? 'active' : '' ?>">⏳ In Progress</a>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&status=CANCELLED" class="filter-btn-ph cancelled-filter-ph <?= $status_filter === 'CANCELLED' ? 'active' : '' ?>">
                <i class="fas fa-ban"></i> Cancelled (<?= $cancelled_count ?>)
            </a>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&type=medicine" class="filter-btn-ph <?= $type_filter === 'medicine' ? 'active' : '' ?>">💊 Medicine</a>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&type=equipment" class="filter-btn-ph <?= $type_filter === 'equipment' ? 'active' : '' ?>">🔧 Equipment</a>
            <?php if ($status_filter !== 'all' || $type_filter !== 'all' || !empty($date_from) || !empty($date_to)): ?>
                <a href="purchase_history.php?branch=<?= $selected_branch_id ?>" class="filter-btn-ph clear-filter-ph">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </div>
        
        <form method="GET" class="search-form-ph">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
            <input type="hidden" name="status" value="<?= $status_filter ?>">
            <input type="hidden" name="type" value="<?= $type_filter ?>">
            <input type="date" name="date_from" value="<?= $date_from ?>" placeholder="From">
            <input type="date" name="date_to" value="<?= $date_to ?>" placeholder="To">
            <button type="submit" class="btn-search-ph"><i class="fas fa-search"></i> Filter</button>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>" class="btn-reset-ph"><i class="fas fa-times"></i> Reset</a>
        </form>
    </div>

    <!-- PURCHASE CARDS -->
    <div class="card-ph animate-fade-in-up-ph" style="animation-delay:0.15s;">
        
        <div class="search-bar-ph">
            <div class="search-box-ph">
                <i class="fas fa-search"></i>
                <input type="text" id="phSearchInput" placeholder="🔍 Auto-search invoice, item, creator, reason..." autocomplete="off">
            </div>
            <div class="search-count-ph">
                <i class="fas fa-list" style="color:var(--ph-primary);"></i>
                <span id="phCountDisplay"><strong><?= count($purchases) ?></strong> purchases</span>
                <span id="phSearchInfo" style="display:none;color:var(--ph-primary);font-weight:700;margin-left:8px;">
                    → <strong id="phSearchCount">0</strong> match
                </span>
            </div>
        </div>
        
        <?php if (count($purchases) > 0): ?>
            <div class="purchases-grid-ph" id="purchasesGrid">
                <?php foreach ($purchases as $purchase): 
                    $status_lower = strtolower($purchase['status']);
                    
                    // ✅ V10.1: Discount-aware
                    $discount_amt = (float)($purchase['global_discount_amount'] ?? 0);
                    $total_buying = (float)($purchase['total_buying_cost'] ?? 0);
                    $final_buying = (float)($purchase['final_total_buying_cost'] ?? 0);
                    if ($final_buying == 0 && $discount_amt == 0) $final_buying = $total_buying;
                    $total_selling = (float)($purchase['total_selling_value'] ?? 0);
                    $profit = $total_selling - $final_buying;
                    
                    $status_icon = $purchase['status'] === 'COMPLETED' ? 'fa-check-circle' : ($purchase['status'] === 'IN_PROGRESS' ? 'fa-spinner fa-spin' : 'fa-ban');
                    $has_items = ($purchase['items_count'] ?? 0) > 0;
                    $can_edit = ($purchase['status'] === 'IN_PROGRESS');
                    $is_cancelled = ($purchase['status'] === 'CANCELLED');
                    
                    $search_data = strtolower(
                        ($purchase['invoice_number'] ?? '') . ' ' .
                        ($purchase['purchase_type'] ?? '') . ' ' .
                        ($purchase['creator_name'] ?? $purchase['created_by_name'] ?? '') . ' ' .
                        ($purchase['cancelled_reason'] ?? '') . ' ' .
                        ($purchase['status'] ?? '') . ' ' .
                        ($purchase['items_list'] ?? '')
                    );
                ?>
                    <div class="purchase-card-ph status-<?= $status_lower ?> ph-card" data-search="<?= htmlspecialchars($search_data) ?>">
                        
                        <!-- HEADER STRIP -->
                        <div class="card-header-strip">
                            <div class="invoice-number-ph">
                                <i class="fas fa-file-invoice"></i>
                                <?= htmlspecialchars($purchase['invoice_number']) ?>
                            </div>
                            <span class="status-pill-ph">
                                <i class="fas <?= $status_icon ?>"></i>
                                <?= str_replace('_', ' ', $purchase['status']) ?>
                            </span>
                        </div>
                        
                        <!-- BODY -->
                        <div class="card-body-ph">
                            
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                                <span class="type-badge-ph <?= $purchase['purchase_type'] ?>">
                                    <i class="fas <?= $purchase['purchase_type'] === 'medicine' ? 'fa-pills' : 'fa-tools' ?>"></i>
                                    <?= ucfirst($purchase['purchase_type']) ?>
                                </span>
                                <span style="font-size:0.7rem;color:var(--ph-text-secondary);">
                                    <i class="fas fa-hashtag" style="color:var(--ph-primary);"></i>
                                    #<?= $purchase['id'] ?>
                                </span>
                            </div>
                            
                            <div class="meta-row-ph">
                                <i class="fas fa-user-circle"></i>
                                <span>Added by: <strong><?= htmlspecialchars($purchase['creator_name'] ?? $purchase['created_by_name'] ?? 'Unknown') ?></strong></span>
                            </div>
                            
                            <div class="meta-row-ph">
                                <i class="fas fa-calendar-alt"></i>
                                <span><strong><?= date('d M Y', strtotime($purchase['created_at'])) ?></strong> · <?= date('H:i', strtotime($purchase['created_at'])) ?></span>
                            </div>
                            
                            <?php if ($purchase['status'] === 'COMPLETED' && !empty($purchase['completed_at'])): ?>
                            <div class="meta-row-ph">
                                <i class="fas fa-check-circle" style="color:#059669;"></i>
                                <span>Completed: <strong><?= date('d M Y', strtotime($purchase['completed_at'])) ?></strong> · <?= date('H:i', strtotime($purchase['completed_at'])) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="items-summary-ph">
                                <div class="summary-label">
                                    <i class="fas fa-boxes"></i> Items (<?= number_format($purchase['items_count'] ?? 0) ?>)
                                </div>
                                <div class="summary-value">
                                    <?php 
                                        $items_list = $purchase['items_list'] ?? '';
                                        echo !empty($items_list) 
                                            ? htmlspecialchars(strlen($items_list) > 100 ? substr($items_list, 0, 100) . '...' : $items_list)
                                            : 'No items';
                                    ?>
                                </div>
                            </div>
                            
                            <div class="stats-row-ph">
                                <div class="stat-mini-ph">
                                    <div class="stat-label">Quantity</div>
                                    <div class="stat-value"><?= number_format($purchase['total_qty'] ?? 0) ?></div>
                                </div>
                                <div class="stat-mini-ph">
                                    <div class="stat-label">Items</div>
                                    <div class="stat-value"><?= number_format($purchase['items_count'] ?? 0) ?></div>
                                </div>
                            </div>
                            
                            <?php if ($discount_amt > 0): ?>
                            <div class="discount-badge-ph">
                                <i class="fas fa-percent"></i>
                                Discount Applied: -TSh <?= number_format($discount_amt) ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($is_cancelled): ?>
                            <div class="cancel-reason-ph">
                                <span class="reason-label">
                                    <i class="fas fa-exclamation-circle"></i> Cancel Reason
                                </span>
                                <?= htmlspecialchars($purchase['cancelled_reason'] ?? 'No reason provided') ?>
                                <?php if (!empty($purchase['cancelled_by_name'])): ?>
                                    <div style="font-size:0.62rem;margin-top:3px;font-style:italic;opacity:0.85;">
                                        <i class="fas fa-user-slash"></i> By: <?= htmlspecialchars($purchase['cancelled_by_name']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                        </div>
                        
                        <!-- FOOTER - ACTIONS -->
                        <div class="card-footer-ph">
                            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&view=<?= $purchase['id'] ?>" class="btn-card-ph view">
                                <i class="fas fa-eye"></i> View
                            </a>
                            
                            <?php if ($can_edit): ?>
                            <form method="POST" style="display:inline;flex:1;">
                                <input type="hidden" name="action" value="edit_purchase">
                                <input type="hidden" name="purchase_id" value="<?= $purchase['id'] ?>">
                                <input type="hidden" name="purchase_type" value="<?= $purchase['purchase_type'] ?>">
                                <button type="submit" class="btn-card-ph edit" style="width:100%;">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <?php if ($purchase['status'] === 'COMPLETED' && $has_items): ?>
                            <a href="get_invoice.php?id=<?= $purchase['id'] ?>" target="_blank" class="btn-card-ph pdf">
                                <i class="fas fa-file-pdf"></i> PDF
                            </a>
                            <?php endif; ?>
                            
                            <button type="button" class="btn-card-ph delete" 
                                    onclick="openDeleteModal(<?= $purchase['id'] ?>, '<?= addslashes($purchase['invoice_number']) ?>')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                        
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="no-results-ph" id="phNoResults">
                <i class="fas fa-search-minus"></i>
                <p style="font-weight:700;color:var(--ph-text-primary);">No purchases match your search</p>
                <p style="font-size:0.85rem;">Try a different search term</p>
            </div>
            
        <?php else: ?>
            <div class="empty-state-ph">
                <i class="fas fa-history"></i>
                <p>No purchases found</p>
                <p class="sub">Try adjusting your filters or create a new purchase</p>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer-ph">
        <p>
            <span class="footer-brand-ph">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            Admin Purchase History V10.1
            <span style="margin:0 8px;">|</span>
            <strong><?= $total_purchases ?></strong> purchases · 
            <strong><?= $completed_count ?></strong> completed · 
            <strong style="color:#DC2626;"><?= $cancelled_count ?></strong> cancelled
            <span style="margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="margin:0 8px;">|</span>
            &copy; <?= date('Y') ?>
        </p>
    </footer>

</main>

<!-- VIEW MODAL -->
<?php if ($view_purchase && $view_id > 0): 
    // ✅ V10.1: Discount-aware
    $vd_discount = (float)($view_purchase['global_discount_amount'] ?? 0);
    $vd_discount_type = $view_purchase['global_discount_type'] ?? 'amount';
    $vd_discount_value = (float)($view_purchase['global_discount_value'] ?? 0);
    $vd_total_buying = (float)($view_purchase['total_buying_cost'] ?? 0);
    $vd_final_buying = (float)($view_purchase['final_total_buying_cost'] ?? 0);
    if ($vd_final_buying == 0 && $vd_discount == 0) $vd_final_buying = $vd_total_buying;
    $vd_total_selling = (float)($view_purchase['total_selling_value'] ?? 0);
    $vd_profit = $vd_total_selling - $vd_final_buying;
    $vd_margin_pct = ($vd_final_buying > 0) ? (($vd_profit / $vd_final_buying) * 100) : 0;
?>
<div class="modal-overlay-ph show" id="viewModal" style="display:flex;">
    <div class="modal-content-ph">
        <div class="modal-header-ph">
            <div class="modal-title-ph">
                <i class="fas fa-eye"></i> Purchase Details - <?= htmlspecialchars($view_purchase['invoice_number']) ?>
                <span style="font-size:0.75rem;font-weight:400;color:var(--ph-text-secondary);margin-left:8px;">
                    (<?= ucfirst($view_purchase['purchase_type']) ?>)
                </span>
            </div>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>" class="modal-close-ph">&times;</a>
        </div>
        
        <?php if ($view_purchase['status'] === 'CANCELLED'): ?>
            <div class="cancelled-info-box-ph">
                <i class="fas fa-ban"></i>
                <div class="info-text-ph">
                    <div class="info-title-ph">
                        <i class="fas fa-exclamation-triangle"></i>
                        This Purchase was Cancelled
                    </div>
                    <div class="info-row-ph">
                        <span class="info-label-ph"><i class="fas fa-comment-alt"></i> Reason:</span>
                        <span class="info-value-ph"><?= htmlspecialchars($view_purchase['cancelled_reason'] ?? 'No reason provided') ?></span>
                    </div>
                    <?php if (!empty($view_purchase['cancelled_by_full_name'])): ?>
                        <div class="info-row-ph">
                            <span class="info-label-ph"><i class="fas fa-user-slash"></i> Cancelled By:</span>
                            <span class="info-value-ph"><?= htmlspecialchars($view_purchase['cancelled_by_full_name']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($view_purchase['cancelled_at'])): ?>
                        <div class="info-row-ph">
                            <span class="info-label-ph"><i class="fas fa-clock"></i> Cancelled At:</span>
                            <span class="info-value-ph"><?= date('d/m/Y H:i:s', strtotime($view_purchase['cancelled_at'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="view-grid-ph">
            <div class="view-item-ph">
                <div class="label">Status</div>
                <div class="value">
                    <span class="status-badge-ph <?= strtolower($view_purchase['status']) ?>" style="padding:5px 14px;border-radius:20px;font-size:0.68rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;text-transform:uppercase;">
                        <i class="fas <?= $view_purchase['status'] === 'COMPLETED' ? 'fa-check-circle' : ($view_purchase['status'] === 'IN_PROGRESS' ? 'fa-spinner fa-spin' : 'fa-ban') ?>"></i>
                        <?= str_replace('_', ' ', $view_purchase['status']) ?>
                    </span>
                </div>
            </div>
            <div class="view-item-ph">
                <div class="label">Purchase Type</div>
                <div class="value"><?= ucfirst($view_purchase['purchase_type']) ?></div>
            </div>
            <div class="view-item-ph">
                <div class="label">Created By</div>
                <div class="value"><?= htmlspecialchars($view_purchase['creator_name'] ?? 'Unknown') ?></div>
            </div>
            <div class="view-item-ph">
                <div class="label">Created At</div>
                <div class="value"><?= date('d/m/Y H:i', strtotime($view_purchase['created_at'])) ?></div>
            </div>
            <?php if ($view_purchase['completed_at']): ?>
            <div class="view-item-ph">
                <div class="label">Completed At</div>
                <div class="value"><?= date('d/m/Y H:i', strtotime($view_purchase['completed_at'])) ?></div>
            </div>
            <?php endif; ?>
            <div class="view-item-ph">
                <div class="label">Total Quantity</div>
                <div class="value"><?= number_format($view_purchase['total_quantity']) ?> units (<?= number_format($view_purchase['total_items']) ?> items)</div>
            </div>
        </div>
        
        <!-- ✅ V10.1: AMOUNTS BREAKDOWN -->
        <div style="margin-top:14px;margin-bottom:16px;">
            <div style="font-size:0.85rem;font-weight:800;color:var(--ph-text-primary);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                <i class="fas fa-coins" style="color:#D97706;"></i> Amounts Breakdown
            </div>
            
            <div class="view-grid-ph">
                <div class="view-item-ph amount-box" style="background:#FEE2E2;border:2px solid #DC2626;">
                    <div class="label" style="color:#DC2626;">💰 Buying Cost (Before Discount)</div>
                    <div class="value" style="color:#DC2626;">TSh <?= number_format($vd_total_buying) ?></div>
                </div>
                
                <?php if ($vd_discount > 0): ?>
                <div class="view-item-ph amount-box" style="background:#FEF3C7;border:2px solid #D97706;">
                    <div class="label" style="color:#D97706;">
                        🎁 Discount
                        <?php if ($vd_discount_type === 'percent'): ?>
                            (<?= number_format($vd_discount_value, 2) ?>%)
                        <?php endif; ?>
                    </div>
                    <div class="value" style="color:#D97706;">-TSh <?= number_format($vd_discount) ?></div>
                </div>
                <?php endif; ?>
                
                <div class="view-item-ph amount-box" style="background:#FECACA;border:2px solid #991B1B;">
                    <div class="label" style="color:#991B1B;">💰 Final Buying Cost</div>
                    <div class="value" style="color:#991B1B;">TSh <?= number_format($vd_final_buying) ?></div>
                </div>
                
                <div class="view-item-ph amount-box" style="background:#E8F0FE;border:2px solid #0B5ED7;">
                    <div class="label" style="color:#0B5ED7;">💰 Total Selling Value</div>
                    <div class="value" style="color:#0B5ED7;">TSh <?= number_format($vd_total_selling) ?></div>
                </div>
                
                <div class="view-item-ph amount-box full-width" style="background:<?= $vd_profit >= 0 ? '#D1FAE5' : '#FEE2E2' ?>;border:2px solid <?= $vd_profit >= 0 ? '#059669' : '#DC2626' ?>;">
                    <div class="label" style="color:<?= $vd_profit >= 0 ? '#059669' : '#DC2626' ?>;">
                        <i class="fas fa-chart-line"></i> 
                        <?= $vd_profit >= 0 ? 'Expected Profit' : 'Expected Loss' ?>
                    </div>
                    <div class="value" style="color:<?= $vd_profit >= 0 ? '#059669' : '#DC2626' ?>;">
                        TSh <?= number_format($vd_profit) ?>
                        <span style="font-size:0.75rem;font-weight:700;background:<?= $vd_profit >= 0 ? '#059669' : '#DC2626' ?>;color:white;padding:3px 10px;border-radius:12px;margin-left:8px;">
                            <?= number_format($vd_margin_pct, 1) ?>% margin
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ✅ V10.1: DISCOUNT DETAIL BOX -->
        <?php if ($vd_discount > 0): ?>
        <div class="discount-detail-box-ph">
            <div class="discount-info-ph">
                <div class="discount-icon-ph">%</div>
                <div class="discount-text-ph">
                    <div class="discount-label-ph">Discount Applied to Buying Cost</div>
                    <div class="discount-desc-ph">
                        <?php if ($vd_discount_type === 'percent'): ?>
                            <?= number_format($vd_discount_value, 2) ?>% off on TSh <?= number_format($vd_total_buying) ?>
                        <?php else: ?>
                            Fixed amount off on total buying cost
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="discount-amount-ph">-TSh <?= number_format($vd_discount) ?></div>
        </div>
        <?php endif; ?>
        
        <!-- ITEMS TABLE -->
        <?php if (count($view_items) > 0): ?>
            <div style="margin-top:16px;">
                <div class="invoice-group-ph">
                    <div class="invoice-group-header-ph">
                        <div class="ig-title-ph">
                            <i class="fas fa-list"></i>
                            Items Purchased (<?= count($view_items) ?>)
                        </div>
                        <div class="ig-stats-ph">
                            <span class="ig-stat-ph">
                                <i class="fas fa-boxes"></i> <?= number_format($view_purchase['total_quantity']) ?> units
                            </span>
                        </div>
                    </div>
                    
                    <div class="invoice-group-body-ph">
                        <table class="view-items-table-ph">
                            <thead>
                                <tr>
                                    <th style="width:40px;text-align:center;">#</th>
                                    <th style="min-width:200px;">Item</th>
                                    <th style="min-width:110px;">Category</th>
                                    <th style="text-align:center;width:60px;">Qty</th>
                                    <th style="text-align:center;width:65px;">Unit</th>
                                    <th style="text-align:right;width:105px;">Buy Price</th>
                                    <th style="text-align:right;width:115px;">Buy Total</th>
                                    <th style="text-align:right;width:105px;">Sell Price</th>
                                    <th style="text-align:right;width:115px;">Sell Total</th>
                                    <th style="min-width:150px;text-align:center;">Added By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $vcounter = 1; foreach ($view_items as $item): ?>
                                    <tr>
                                        <td style="text-align:center;font-weight:700;color:var(--ph-text-secondary);"><?= $vcounter++ ?></td>
                                        <td>
                                            <div class="item-name-cell-ph">
                                                <strong><?= htmlspecialchars($item['item_name'] ?? 'Unknown') ?></strong>
                                                <div class="item-meta-ph">
                                                    <?php if (!empty($item['batch_number'])): ?>
                                                        <span class="item-batch-tag-ph">
                                                            <i class="fas fa-barcode"></i> <?= htmlspecialchars($item['batch_number']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['expiry_date']) && $item['expiry_date'] !== '0000-00-00'): ?>
                                                        <span style="color:#DC2626;font-weight:600;">
                                                            <i class="fas fa-calendar-times"></i>
                                                            Exp: <?= date('d/m/Y', strtotime($item['expiry_date'])) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['supplier'])): ?>
                                                        <span>
                                                            <i class="fas fa-truck"></i> <?= htmlspecialchars($item['supplier']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="background:var(--ph-primary-light);color:var(--ph-primary);padding:3px 10px;border-radius:10px;font-size:0.68rem;font-weight:600;">
                                                <?= htmlspecialchars($item['category'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center;font-weight:700;font-family:monospace;"><?= number_format($item['quantity']) ?></td>
                                        <td style="text-align:center;">
                                            <span style="background:#CCFBF1;color:#0D9488;padding:2px 8px;border-radius:6px;font-size:0.68rem;font-weight:700;">
                                                <?= htmlspecialchars($item['unit'] ?? 'pcs') ?>
                                            </span>
                                        </td>
                                        <td style="text-align:right;color:#DC2626;font-weight:600;">TSh <?= number_format($item['buying_price'] ?? 0) ?></td>
                                        <td style="text-align:right;color:#DC2626;font-weight:700;">TSh <?= number_format($item['total_buying_cost'] ?? 0) ?></td>
                                        <td style="text-align:right;color:#059669;font-weight:600;">TSh <?= number_format($item['selling_price'] ?? 0) ?></td>
                                        <td style="text-align:right;color:#059669;font-weight:700;">TSh <?= number_format($item['total_selling_value'] ?? 0) ?></td>
                                        <td style="text-align:center;">
                                            <span class="added-by-tag-ph">
                                                <i class="fas fa-user-circle"></i>
                                                <?= htmlspecialchars($item['added_by_full_name'] ?? 'Unknown') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state-ph" style="padding:20px;">
                <i class="fas fa-box-open" style="font-size:1.5rem;"></i>
                <p>No items found in this purchase</p>
                <?php if ($view_purchase['status'] === 'CANCELLED'): ?>
                    <p style="font-size:0.75rem;color:#DC2626;margin-top:6px;">
                        <i class="fas fa-info-circle"></i> Items were deleted when purchase was cancelled
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="modal-actions-ph">
            <?php if ($view_purchase['status'] === 'COMPLETED' && count($view_items) > 0): ?>
                <a href="get_invoice.php?id=<?= $view_purchase['id'] ?>" target="_blank" class="btn-print-invoice-ph" style="text-decoration:none;">
                    <i class="fas fa-file-pdf"></i> PDF Invoice
                </a>
            <?php endif; ?>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>" class="btn-close-modal-ph">
                <i class="fas fa-times"></i> Close
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- DELETE MODAL -->
<div class="modal-overlay-ph" id="deleteModal">
    <div class="delete-modal-content-ph">
        <div class="modal-header-ph">
            <div class="modal-title-ph">
                <i class="fas fa-exclamation-triangle"></i> Delete Purchase
            </div>
            <button class="modal-close-ph" onclick="closeDeleteModal()">&times;</button>
        </div>
        
        <div class="delete-warning-icon-ph">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        
        <p style="text-align:center;color:var(--ph-text-primary);font-weight:500;margin-bottom:4px;">
            Are you sure you want to delete this purchase?
        </p>
        <p style="text-align:center;color:var(--ph-text-secondary);font-size:0.85rem;margin-bottom:12px;">
            <strong id="deleteInvoiceNumber"></strong>
        </p>
        
        <div class="delete-note-ph">
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
                <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:4px;color:var(--ph-text-primary);">Reason for Deletion <span style="color:#DC2626;">*</span></label>
                <textarea name="delete_reason" id="deleteReason" class="delete-reason-textarea-ph" 
                          placeholder="Why is this purchase being deleted? (This will be logged for audit)" required></textarea>
            </div>
            
            <div class="modal-actions-ph">
                <button type="submit" class="btn-confirm-delete-ph">
                    <i class="fas fa-trash"></i> Yes, Delete Purchase
                </button>
                <button type="button" class="btn-close-modal-ph" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> No, Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // AUTO-SEARCH for cards
    (function() {
        var searchInput = document.getElementById('phSearchInput');
        var countDisplay = document.getElementById('phCountDisplay');
        var searchInfo = document.getElementById('phSearchInfo');
        var searchCount = document.getElementById('phSearchCount');
        var noResults = document.getElementById('phNoResults');
        
        if (!searchInput) return;
        
        searchInput.addEventListener('input', function() {
            var q = this.value.toLowerCase().trim();
            var cards = document.querySelectorAll('.ph-card');
            var total = cards.length;
            var visible = 0;
            
            cards.forEach(function(card) {
                var searchData = card.getAttribute('data-search') || '';
                if (q === '' || searchData.includes(q)) {
                    card.style.display = '';
                    visible++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            if (countDisplay) {
                countDisplay.innerHTML = '<strong>' + total + '</strong> purchases';
            }
            
            if (searchInfo && searchCount) {
                if (q === '') {
                    searchInfo.style.display = 'none';
                } else {
                    searchInfo.style.display = 'inline';
                    searchCount.textContent = visible;
                }
            }
            
            if (noResults) {
                noResults.style.display = (visible === 0 && q !== '') ? 'block' : 'none';
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
        });
    })();

    // DELETE MODAL
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
        if (e.target === this) closeDeleteModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeDeleteModal();
    });

    setTimeout(function() {
        var messageBox = document.getElementById('messageBox');
        if (messageBox) messageBox.style.display = 'none';
    }, 5000);

    // FOOTER TIME
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    console.log('%c📜 Braick - Admin Purchase History V10.1', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ V10.1: Card-based layout (badala ya table)', 'font-size:13px; color:#34D399; font-weight:bold;');
    console.log('%c✅ V10.1: Amounts zinaonekana kwenye View Modal tu', 'font-size:13px; color:#34D399;');
    console.log('%c✅ V10.1: Discount inapunguza BUYING COST', 'font-size:13px; color:#34D399;');
    console.log('%c✅ V10.1: Final buying cost baada ya discount', 'font-size:13px; color:#34D399;');
    console.log('%c💡 Press Ctrl+K to focus search', 'font-size:13px; color:#D97706; font-weight:bold;');
</script>

</body>
</html>