<?php
// ================================================================
// FILE: frontend/pages/admin/purchase_history.php
// ADMIN - PURCHASE HISTORY WITH VIEW, EDIT, DELETE, CANCEL REASON
// ✅ Uses SHARED admin_header.php & admin_sidebar.php
// ✅ Page-specific CSS only (no duplicates)
// ✅ Dark mode via header toggle
// DELETE removes from purchases/purchase_items ONLY
// Inventory stock REMAINS (does not delete stock)
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
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES
       ================================================================ */
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
        --ph-radius: 12px;
        --ph-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --ph-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    }

    [data-theme="dark"] {
        --ph-bg-body: #0F172A;
        --ph-bg-card: #1E293B;
        --ph-border-color: #334155;
        --ph-text-primary: #F1F5F9;
        --ph-text-secondary: #94A3B8;
        --ph-text-muted: #64748B;
    }

    /* ================================================================
       DARK MODE - PAGE YOTE
       ================================================================ */
    html[data-theme="dark"] body {
        background: #0F172A !important;
    }

    html[data-theme="dark"] .main-content {
        background: #0F172A !important;
        color: #F1F5F9;
    }

    /* ================================================================
       PAGE HEADER BOX
       ================================================================ */
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
        top: -60%;
        right: -10%;
        width: 350px;
        height: 350px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-box-ph .page-title-ph {
        color: white;
        font-size: 1.4rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-box-ph .page-title-ph .role-badge-display-ph {
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

    .page-header-box-ph .page-title-ph .branch-name-display-ph {
        background: rgba(255,255,255,0.15);
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        color: white;
    }

    .header-actions-ph {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
        position: relative;
        z-index: 1;
    }

    .btn-back-ph {
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

    .btn-back-ph:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    /* ================================================================
       STATS CARDS
       ================================================================ */
    .stats-grid-ph {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }

    .stat-card-ph {
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

    .stat-card-ph:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

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

    /* ================================================================
       CARD
       ================================================================ */
    .card-ph {
        background: var(--ph-bg-card);
        border-radius: 14px;
        padding: 18px 22px;
        border: 2px solid var(--ph-border-color);
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }

    .card-ph:hover {
        border-color: var(--ph-primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }

    .card-header-ph {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
        flex-wrap: wrap;
        gap: 8px;
    }

    .card-title-ph {
        font-size: 1rem;
        font-weight: 600;
        color: var(--ph-text-primary);
        margin: 0;
    }

    .card-title-ph .title-blue { color: var(--ph-primary); }
    .card-title-ph .title-green { color: var(--ph-success); }

    .result-count-ph {
        font-size: 0.8rem;
        color: var(--ph-text-secondary);
    }

    .result-count-ph strong { color: var(--ph-primary); }

    /* ================================================================
       SCROLL BUTTONS
       ================================================================ */
    .scroll-arrows-ph {
        display: flex;
        gap: 5px;
        align-items: center;
    }

    .scroll-arrow-btn-ph {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        border: 2px solid var(--ph-border-color);
        background: var(--ph-bg-card);
        color: var(--ph-text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
    }

    .scroll-arrow-btn-ph:hover {
        border-color: var(--ph-primary);
        color: var(--ph-primary);
        background: var(--ph-primary-light);
        transform: scale(1.05);
    }

    .table-wrapper-ph { position: relative; }

    .table-scroll-container-ph {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 500px;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
    }

    .table-scroll-container-ph::-webkit-scrollbar { height: 6px; width: 5px; }
    .table-scroll-container-ph::-webkit-scrollbar-track { background: var(--ph-bg-body); border-radius: 4px; }
    .table-scroll-container-ph::-webkit-scrollbar-thumb { background: var(--ph-primary); border-radius: 4px; }

    /* ================================================================
       FILTERS
       ================================================================ */
    .filter-group-ph {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 14px;
    }

    .filter-btn-ph {
        padding: 4px 14px;
        border-radius: 16px;
        font-size: 0.7rem;
        font-weight: 600;
        border: 2px solid var(--ph-border-color);
        background: transparent;
        color: var(--ph-text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
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
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }

    .search-form-ph input[type="text"],
    .search-form-ph select,
    .search-form-ph input[type="date"] {
        padding: 6px 12px;
        border: 2px solid var(--ph-border-color);
        border-radius: 8px;
        font-size: 0.8rem;
        background: var(--ph-bg-card);
        color: var(--ph-text-primary);
        outline: none;
        transition: all 0.3s ease;
        flex: 1;
        min-width: 100px;
        font-family: inherit;
    }

    .search-form-ph input:focus,
    .search-form-ph select:focus {
        border-color: var(--ph-primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }

    .btn-search-ph {
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        border: none;
        background: var(--ph-primary);
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: inherit;
    }

    .btn-search-ph:hover {
        background: var(--ph-primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }

    .btn-reset-ph {
        padding: 8px 16px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        border: 2px solid var(--ph-border-color);
        background: transparent;
        color: var(--ph-text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .btn-reset-ph:hover { border-color: var(--ph-danger); color: var(--ph-danger); }

    /* ================================================================
       TABLE
       ================================================================ */
    .data-table-ph {
        width: 100%;
        min-width: 1300px;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.78rem;
    }

    .data-table-ph thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: var(--ph-primary);
        color: white;
        padding: 8px 12px;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
        white-space: nowrap;
        text-align: left;
    }

    .data-table-ph thead th:first-child { border-radius: 8px 0 0 0; }
    .data-table-ph thead th:last-child { border-radius: 0 8px 0 0; }

    .data-table-ph tbody tr:nth-child(even) { background: #E8F0FE; }
    .data-table-ph tbody tr:hover td { background: #D1FAE5; }

    [data-theme="dark"] .data-table-ph tbody tr:nth-child(even) { background: #1E293B; }
    [data-theme="dark"] .data-table-ph tbody tr:hover td { background: #1A3A2A; }

    .data-table-ph td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--ph-border-color);
        color: var(--ph-text-primary);
        vertical-align: middle;
    }

    .col-sno-ph { width: 35px; text-align: center; }
    .col-invoice-ph { min-width: 140px; }
    .col-type-ph { min-width: 80px; }
    .col-creator-ph { min-width: 140px; }
    .col-items-ph { min-width: 60px; text-align: center; }
    .col-qty-ph { min-width: 60px; text-align: center; }
    .col-buying-ph { min-width: 120px; }
    .col-selling-ph { min-width: 120px; }
    .col-profit-ph { min-width: 120px; }
    .col-status-ph { min-width: 150px; text-align: center; }
    .col-reason-ph { min-width: 200px; }
    .col-date-ph { min-width: 140px; }
    .col-actions-ph { min-width: 220px; text-align: center; }

    /* ================================================================
       BADGES
       ================================================================ */
    .status-badge-ph {
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }

    .status-badge-ph.completed { background: #D1FAE5; color: #059669; }
    .status-badge-ph.in-progress { background: #FEF3C7; color: #D97706; }
    .status-badge-ph.cancelled { background: #FEE2E2; color: #DC2626; }

    [data-theme="dark"] .status-badge-ph.completed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge-ph.in-progress { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-badge-ph.cancelled { background: #3A1A1A; color: #F87171; }

    .type-badge-ph {
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.55rem;
        font-weight: 600;
    }

    .type-badge-ph.medicine { background: #E8F0FE; color: #0B5ED7; }
    .type-badge-ph.equipment { background: #EDE9FE; color: #7C3AED; }

    [data-theme="dark"] .type-badge-ph.medicine { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .type-badge-ph.equipment { background: #2D1B5F; color: #A78BFA; }

    .profit-positive-ph { color: #059669; }
    .profit-negative-ph { color: #DC2626; }

    /* ================================================================
       CANCEL REASON CELL
       ================================================================ */
    .cancel-reason-cell-ph {
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

    [data-theme="dark"] .cancel-reason-cell-ph { background: #3A1A1A; }

    .cancel-reason-cell-ph .reason-label-ph {
        font-weight: 700;
        font-size: 0.6rem;
        text-transform: uppercase;
        display: block;
        margin-bottom: 2px;
        color: #991B1B;
    }

    [data-theme="dark"] .cancel-reason-cell-ph .reason-label-ph { color: #FCA5A5; }

    /* ================================================================
       ACTION BUTTONS
       ================================================================ */
    .action-group-ph {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .action-btn-ph {
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
        font-family: inherit;
    }

    .action-btn-ph.view { background: var(--ph-primary); }
    .action-btn-ph.view:hover { background: var(--ph-primary-dark); transform: scale(1.03); color: white; }

    .action-btn-ph.edit { background: var(--ph-warning); }
    .action-btn-ph.edit:hover { background: #B45309; transform: scale(1.03); color: white; }

    .action-btn-ph.delete { background: var(--ph-danger); }
    .action-btn-ph.delete:hover { background: #991B1B; transform: scale(1.03); color: white; }

    .action-btn-ph i { font-size: 0.5rem; }

    .btn-pdf-new-window-ph {
        background: var(--ph-danger);
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

    .btn-pdf-new-window-ph:hover { background: #991B1B; transform: scale(1.03); color: white; }
    .btn-pdf-new-window-ph i { font-size: 0.5rem; }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state-ph {
        text-align: center;
        padding: 40px 20px;
        color: var(--ph-text-secondary);
    }

    .empty-state-ph i {
        font-size: 2.5rem;
        color: var(--ph-border-color);
        display: block;
        margin-bottom: 10px;
    }

    .empty-state-ph .sub { font-size: 0.8rem; margin-top: 4px; }

    /* ================================================================
       MESSAGE
       ================================================================ */
    .message-box-ph {
        padding: 12px 18px;
        border-radius: 10px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
        font-size: 0.9rem;
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

    /* ================================================================
       MODAL
       ================================================================ */
    .modal-overlay-ph {
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
        backdrop-filter: blur(4px);
    }

    .modal-overlay-ph.show { display: flex; }

    .modal-content-ph {
        background: var(--ph-bg-card);
        border-radius: 12px;
        max-width: 900px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        padding: 24px 28px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        border: 2px solid var(--ph-border-color);
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .modal-header-ph {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--ph-border-color);
        margin-bottom: 16px;
    }

    .modal-header-ph .modal-title-ph {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--ph-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .modal-close-ph {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--ph-text-secondary);
        transition: all 0.3s ease;
        text-decoration: none;
        line-height: 1;
    }

    .modal-close-ph:hover { color: var(--ph-danger); transform: rotate(90deg); }

    .modal-actions-ph {
        display: flex;
        gap: 10px;
        padding-top: 14px;
        border-top: 2px solid var(--ph-border-color);
        margin-top: 16px;
        flex-wrap: wrap;
    }

    .btn-close-modal-ph {
        background: transparent;
        color: var(--ph-text-secondary);
        border: 2px solid var(--ph-border-color);
        padding: 10px 24px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        font-family: inherit;
    }

    .btn-close-modal-ph:hover { border-color: var(--ph-danger); color: var(--ph-danger); }

    .btn-print-invoice-ph {
        background: var(--ph-danger);
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

    .btn-print-invoice-ph:hover { background: #991B1B; transform: translateY(-2px); color: white; }

    /* ================================================================
       VIEW GRID
       ================================================================ */
    .view-grid-ph {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-bottom: 14px;
    }

    .view-item-ph {
        padding: 8px 12px;
        background: var(--ph-bg-body);
        border-radius: 6px;
        border: 1px solid var(--ph-border-color);
    }

    .view-item-ph .label {
        font-size: 0.55rem;
        text-transform: uppercase;
        color: var(--ph-text-secondary);
        font-weight: 600;
        letter-spacing: 0.05em;
    }

    .view-item-ph .value {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--ph-text-primary);
        margin-top: 2px;
    }

    .view-item-ph.full-width { grid-column: 1 / -1; }

    /* ================================================================
       CANCELLED INFO BOX
       ================================================================ */
    .cancelled-info-box-ph {
        background: linear-gradient(135deg, #FEF2F2, #FEE2E2);
        border: 2px solid #DC2626;
        border-radius: 10px;
        padding: 14px 16px;
        margin-bottom: 16px;
        display: flex;
        gap: 12px;
        align-items: flex-start;
    }

    [data-theme="dark"] .cancelled-info-box-ph { background: linear-gradient(135deg, #3A1A1A, #4A1F1F); }

    .cancelled-info-box-ph i {
        color: #DC2626;
        font-size: 1.5rem;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .cancelled-info-box-ph .info-text-ph { flex: 1; }

    .cancelled-info-box-ph .info-text-ph .info-title-ph {
        font-size: 0.85rem;
        font-weight: 700;
        color: #991B1B;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    [data-theme="dark"] .cancelled-info-box-ph .info-text-ph .info-title-ph { color: #FCA5A5; }

    .cancelled-info-box-ph .info-text-ph .info-row-ph {
        font-size: 0.78rem;
        color: #7F1D1D;
        margin-bottom: 3px;
        display: flex;
        gap: 6px;
        align-items: flex-start;
    }

    [data-theme="dark"] .cancelled-info-box-ph .info-text-ph .info-row-ph { color: #FCA5A5; }

    .cancelled-info-box-ph .info-text-ph .info-row-ph .info-label-ph {
        font-weight: 600;
        min-width: 100px;
    }

    .cancelled-info-box-ph .info-text-ph .info-row-ph .info-value-ph {
        font-weight: 600;
        color: #450A0A;
        word-break: break-word;
    }

    [data-theme="dark"] .cancelled-info-box-ph .info-text-ph .info-row-ph .info-value-ph { color: #FECACA; }

    /* ================================================================
       VIEW ITEMS TABLE
       ================================================================ */
    .view-items-table-ph {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.75rem;
        margin-top: 10px;
    }

    .view-items-table-ph thead th {
        background: var(--ph-primary);
        color: white;
        padding: 6px 10px;
        font-size: 0.6rem;
        text-transform: uppercase;
        font-weight: 700;
        text-align: left;
    }

    .view-items-table-ph tbody td {
        padding: 5px 10px;
        border-bottom: 1px solid var(--ph-border-color);
        color: var(--ph-text-primary);
    }

    .view-items-table-ph tbody tr:nth-child(even) { background: #E8F0FE; }
    [data-theme="dark"] .view-items-table-ph tbody tr:nth-child(even) { background: #1E293B; }

    /* ================================================================
       DELETE MODAL
       ================================================================ */
    .delete-warning-icon-ph {
        text-align: center;
        font-size: 3rem;
        color: var(--ph-danger);
        margin-bottom: 10px;
    }

    .delete-modal-content-ph {
        background: var(--ph-bg-card);
        border-radius: 12px;
        max-width: 500px;
        width: 100%;
        padding: 24px 28px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        border: 2px solid var(--ph-border-color);
    }

    .delete-modal-content-ph .modal-header-ph .modal-title-ph { color: var(--ph-danger); }

    .btn-confirm-delete-ph {
        background: var(--ph-danger);
        color: white;
        padding: 10px 28px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.9rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        flex: 1;
        font-family: inherit;
    }

    .btn-confirm-delete-ph:hover { background: #991B1B; transform: translateY(-2px); }

    .delete-reason-textarea-ph {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid var(--ph-border-color);
        border-radius: 8px;
        font-size: 0.9rem;
        resize: vertical;
        min-height: 60px;
        background: var(--ph-bg-body);
        color: var(--ph-text-primary);
        transition: all 0.3s ease;
        font-family: inherit;
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
        font-size: 0.8rem;
        color: #D97706;
        font-weight: 600;
        margin: 0;
    }

    .delete-note-ph p i { margin-right: 4px; }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-ph {
        padding: 12px 0;
        border-top: 1px solid var(--ph-border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.65rem;
        color: var(--ph-text-secondary);
    }

    .footer-ph .footer-brand-ph { color: var(--ph-primary); font-weight: 600; }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up-ph {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .stats-grid-ph { grid-template-columns: repeat(3, 1fr); }
    }

    @media (max-width: 768px) {
        .stats-grid-ph { grid-template-columns: repeat(2, 1fr); }
        .search-form-ph { flex-direction: column; align-items: stretch; }
        .search-form-ph input, .search-form-ph select { min-width: 100%; }
        .filter-group-ph { justify-content: center; }
        .card-ph { padding: 12px 14px; }
        .page-header-box-ph .page-title-ph { font-size: 1.1rem; }
        .stat-card-ph .stat-number { font-size: 1.1rem; }
        .stat-card-ph { padding: 10px 12px; min-height: 65px; }
        .header-actions-ph { flex-direction: column; align-items: stretch; width: 100%; }
        .header-actions-ph .btn-back-ph { width: 100%; justify-content: center; }
        .data-table-ph { min-width: 1000px; font-size: 0.65rem; }
        .data-table-ph th, .data-table-ph td { padding: 4px 6px; }
        .view-grid-ph { grid-template-columns: 1fr; }
        .modal-content-ph { padding: 16px; }
        .delete-modal-content-ph { padding: 16px; }
    }

    @media (max-width: 480px) {
        .stats-grid-ph { grid-template-columns: 1fr 1fr; }
        .stat-card-ph .stat-number { font-size: 0.9rem; }
        .stat-card-ph { padding: 8px 10px; min-height: 55px; }
        .page-header-box-ph { flex-direction: column; align-items: flex-start !important; }
    }

    @media print {
        .btn-back-ph, .btn-search-ph, .btn-reset-ph, .action-group-ph,
        .scroll-arrows-ph, .filter-group-ph, .search-form-ph { display: none !important; }
        .page-header-box-ph { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
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
            <div class="stat-label">Total Buying Cost</div>
            <div class="stat-sub">Money spent on purchases</div>
        </div>
        <div class="stat-card-ph purple">
            <span class="stat-icon"><i class="fas fa-chart-line"></i></span>
            <div class="stat-number <?= $total_profit >= 0 ? 'profit-positive-ph' : 'profit-negative-ph' ?>">
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
            <?php if ($status_filter !== 'all' || $type_filter !== 'all' || !empty($search) || !empty($date_from) || !empty($date_to)): ?>
                <a href="purchase_history.php?branch=<?= $selected_branch_id ?>" class="filter-btn-ph clear-filter-ph">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </div>
        
        <form method="GET" class="search-form-ph">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
            <input type="hidden" name="status" value="<?= $status_filter ?>">
            <input type="hidden" name="type" value="<?= $type_filter ?>">
            <input type="text" name="search" placeholder="🔍 Search invoice, creator, or cancel reason..." value="<?= htmlspecialchars($search) ?>">
            <input type="date" name="date_from" value="<?= $date_from ?>" placeholder="From">
            <input type="date" name="date_to" value="<?= $date_to ?>" placeholder="To">
            <button type="submit" class="btn-search-ph"><i class="fas fa-search"></i> Filter</button>
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>" class="btn-reset-ph"><i class="fas fa-times"></i> Reset</a>
        </form>
    </div>

    <!-- PURCHASE TABLE -->
    <div class="card-ph animate-fade-in-up-ph" style="animation-delay:0.15s;">
        <div class="card-header-ph">
            <h3 class="card-title-ph">
                <i class="fas fa-list title-blue"></i> Purchase List
                <span class="result-count-ph">(<strong><?= count($purchases) ?></strong> purchases)</span>
                <?php if ($status_filter === 'CANCELLED'): ?>
                    <span style="font-size:0.7rem;color:#DC2626;font-weight:600;margin-left:6px;">
                        <i class="fas fa-ban"></i> Showing Cancelled Purchases with Reasons
                    </span>
                <?php endif; ?>
            </h3>
            <div class="scroll-arrows-ph">
                <button class="scroll-arrow-btn-ph" onclick="scrollTable('left')" title="Scroll Left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="scroll-arrow-btn-ph" onclick="scrollTable('right')" title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <?php if (count($purchases) > 0): ?>
            <div class="table-wrapper-ph">
                <div class="table-scroll-container-ph" id="tableScrollContainer">
                    <table class="data-table-ph" id="purchaseTable">
                        <thead>
                            <tr>
                                <th class="col-sno-ph">#</th>
                                <th class="col-invoice-ph">Invoice</th>
                                <th class="col-type-ph">Type</th>
                                <th class="col-creator-ph">Created By</th>
                                <th class="col-items-ph">Items</th>
                                <th class="col-qty-ph">Qty</th>
                                <th class="col-buying-ph">Buying Cost</th>
                                <th class="col-selling-ph">Selling Value</th>
                                <th class="col-profit-ph">Profit</th>
                                <th class="col-status-ph">Status</th>
                                <th class="col-reason-ph">Cancel Reason</th>
                                <th class="col-date-ph">Date</th>
                                <th class="col-actions-ph">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($purchases as $purchase): ?>
                                <?php 
                                    $profit = ($purchase['total_selling_value'] ?? 0) - ($purchase['total_buying_cost'] ?? 0);
                                    $profit_class = $profit >= 0 ? 'profit-positive-ph' : 'profit-negative-ph';
                                    $status_class = strtolower($purchase['status']);
                                    $status_icon = $purchase['status'] === 'COMPLETED' ? 'fa-check-circle' : ($purchase['status'] === 'IN_PROGRESS' ? 'fa-spinner fa-spin' : 'fa-ban');
                                    $has_items = ($purchase['items_count'] ?? 0) > 0;
                                    $can_edit = ($purchase['status'] === 'IN_PROGRESS');
                                    $is_cancelled = ($purchase['status'] === 'CANCELLED');
                                ?>
                                <tr style="<?= $is_cancelled ? 'background:rgba(220,38,38,0.05);' : '' ?>">
                                    <td class="col-sno-ph"><?= $counter++ ?></td>
                                    <td class="col-invoice-ph">
                                        <strong style="<?= $is_cancelled ? 'text-decoration:line-through;color:#DC2626;' : '' ?>">
                                            <?= htmlspecialchars($purchase['invoice_number']) ?>
                                        </strong>
                                    </td>
                                    <td class="col-type-ph">
                                        <span class="type-badge-ph <?= $purchase['purchase_type'] ?>">
                                            <?= ucfirst($purchase['purchase_type']) ?>
                                        </span>
                                    </td>
                                    <td class="col-creator-ph">
                                        <i class="fas fa-user" style="color:#0B5ED7;font-size:0.65rem;"></i>
                                        <?= htmlspecialchars($purchase['creator_name'] ?? $purchase['created_by_name'] ?? 'Unknown') ?>
                                    </td>
                                    <td class="col-items-ph"><?= number_format($purchase['items_count'] ?? 0) ?></td>
                                    <td class="col-qty-ph"><?= number_format($purchase['total_qty'] ?? 0) ?></td>
                                    <td class="col-buying-ph" style="color:#DC2626;font-weight:600;">
                                        TSh <?= number_format($purchase['total_buying_cost'] ?? 0) ?>
                                    </td>
                                    <td class="col-selling-ph" style="color:#059669;font-weight:600;">
                                        TSh <?= number_format($purchase['total_selling_value'] ?? 0) ?>
                                    </td>
                                    <td class="col-profit-ph">
                                        <span class="<?= $profit_class ?>" style="font-weight:600;">
                                            TSh <?= number_format($profit) ?>
                                            <?php if ($purchase['total_buying_cost'] > 0 && $purchase['status'] === 'COMPLETED'): ?>
                                                <span style="font-size:0.55rem;">
                                                    (<?= round(($profit / $purchase['total_buying_cost']) * 100, 1) ?>%)
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="col-status-ph">
                                        <span class="status-badge-ph <?= $status_class ?>">
                                            <i class="fas <?= $status_icon ?>"></i>
                                            <?= $purchase['status'] ?>
                                        </span>
                                        <?php if ($is_cancelled && !empty($purchase['cancelled_at'])): ?>
                                            <div style="font-size:0.55rem;color:var(--ph-text-muted);margin-top:3px;">
                                                <i class="fas fa-clock"></i>
                                                <?= date('d/m/Y H:i', strtotime($purchase['cancelled_at'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-reason-ph">
                                        <?php if ($is_cancelled): ?>
                                            <div class="cancel-reason-cell-ph">
                                                <span class="reason-label-ph">
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
                                            <span style="font-size:0.7rem;color:var(--ph-text-muted);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-date-ph">
                                        <?= date('d/m/Y H:i', strtotime($purchase['created_at'])) ?>
                                        <?php if ($purchase['status'] === 'COMPLETED' && $purchase['completed_at']): ?>
                                            <br><span style="font-size:0.55rem;color:var(--ph-text-muted);">
                                                <i class="fas fa-check-circle" style="color:#059669;"></i>
                                                <?= date('d/m/Y H:i', strtotime($purchase['completed_at'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-actions-ph">
                                        <div class="action-group-ph">
                                            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>&view=<?= $purchase['id'] ?>" class="action-btn-ph view" title="View Details">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            
                                            <?php if ($can_edit): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="edit_purchase">
                                                    <input type="hidden" name="purchase_id" value="<?= $purchase['id'] ?>">
                                                    <input type="hidden" name="purchase_type" value="<?= $purchase['purchase_type'] ?>">
                                                    <button type="submit" class="action-btn-ph edit" title="Edit Purchase">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <button class="action-btn-ph delete" 
                                                    onclick="openDeleteModal(<?= $purchase['id'] ?>, '<?= addslashes($purchase['invoice_number']) ?>')" 
                                                    title="Delete Purchase">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                            
                                            <?php if ($purchase['status'] === 'COMPLETED' && $has_items): ?>
                                                <a href="get_invoice.php?id=<?= $purchase['id'] ?>" target="_blank" class="btn-pdf-new-window-ph" title="View PDF Invoice">
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
            <div class="empty-state-ph">
                <i class="fas fa-history"></i>
                <p>No purchases found</p>
                <p class="sub">Try adjusting your filters or create a new purchase</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer-ph">
        <p>
            <span class="footer-brand-ph">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            Admin Purchase History
            <span style="margin:0 8px;">|</span>
            <strong><?= $total_purchases ?></strong> purchases · 
            <strong><?= $completed_count ?></strong> completed · 
            <strong style="color:#DC2626;"><?= $cancelled_count ?></strong> cancelled · 
            TSh <strong><?= formatMoney($total_spending) ?></strong> spent
            <span style="margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- VIEW MODAL -->
<?php if ($view_purchase && $view_id > 0): ?>
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
            <div class="view-item-ph full-width">
                <div class="label">Status</div>
                <div class="value">
                    <span class="status-badge-ph <?= strtolower($view_purchase['status']) ?>">
                        <i class="fas <?= $view_purchase['status'] === 'COMPLETED' ? 'fa-check-circle' : ($view_purchase['status'] === 'IN_PROGRESS' ? 'fa-spinner fa-spin' : 'fa-ban') ?>"></i>
                        <?= $view_purchase['status'] ?>
                    </span>
                </div>
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
                <div class="label">Total Items</div>
                <div class="value"><?= number_format($view_purchase['total_items']) ?></div>
            </div>
            <div class="view-item-ph">
                <div class="label">Total Quantity</div>
                <div class="value"><?= number_format($view_purchase['total_quantity']) ?> units</div>
            </div>
            <div class="view-item-ph" style="background:#FEE2E2;border:2px solid #DC2626;">
                <div class="label" style="color:#DC2626;">Total Buying Cost</div>
                <div class="value" style="color:#DC2626;">TSh <?= number_format($view_purchase['total_buying_cost'] ?? 0) ?></div>
            </div>
            <div class="view-item-ph" style="background:#D1FAE5;border:2px solid #059669;">
                <div class="label" style="color:#059669;">Total Selling Value</div>
                <div class="value" style="color:#059669;">TSh <?= number_format($view_purchase['total_selling_value'] ?? 0) ?></div>
            </div>
            <?php 
                $view_profit = ($view_purchase['total_selling_value'] ?? 0) - ($view_purchase['total_buying_cost'] ?? 0);
                $view_profit_class = $view_profit >= 0 ? 'profit-positive-ph' : 'profit-negative-ph';
            ?>
            <div class="view-item-ph" style="background:<?= $view_profit >= 0 ? '#D1FAE5' : '#FEE2E2' ?>;border:2px solid <?= $view_profit >= 0 ? '#059669' : '#DC2626' ?>;">
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
                <div style="font-size:0.8rem;font-weight:600;margin-bottom:8px;color:var(--ph-text-primary);">
                    <i class="fas fa-list"></i> Items (<?= count($view_items) ?>)
                </div>
                <div style="overflow-x:auto;">
                    <table class="view-items-table-ph">
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
                                        <div style="font-size:0.6rem;color:var(--ph-text-muted);">
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

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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
    // DELETE MODAL
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

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDeleteModal();
        }
    });

    // Auto-hide message after 5 seconds
    setTimeout(function() {
        var messageBox = document.getElementById('messageBox');
        if (messageBox) messageBox.style.display = 'none';
    }, 5000);

    // ================================================================
    // FOOTER TIME
    // ================================================================
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    console.log('%c📜 Braick - Admin Purchase History', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c✅ No duplicate CSS/JS', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Total Purchases: <?= $total_purchases ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Completed: <?= $completed_count ?> | In Progress: <?= $in_progress_count ?>', 'font-size:13px; color:#D97706;');
    console.log('%c❌ Cancelled: <?= $cancelled_count ?>', 'font-size:13px; color:#DC2626; font-weight:bold;');
    console.log('%c💰 Total Spending: TSh <?= formatMoney($total_spending) ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c📈 Total Profit: TSh <?= formatMoney($total_profit) ?>', 'font-size:13px; color:<?= $total_profit >= 0 ? '#059669' : '#DC2626' ?>;');
    console.log('%c🌙 Dark mode: Handled by header (shared)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>