<?php
// ================================================================
// FILE: frontend/pages/pharmacy/purchase_history.php
// PHARMACY - PURCHASE HISTORY V9.2
// ✅ Table header search (auto-filter, no submit)
// ✅ Rows & columns kubwa, zinaonekana vizuri
// ✅ Group dawa kwa invoice zao + margin per invoice
// ✅ Column "Added By" kwenye View Modal (per item)
// ✅ Modal ipo MBELE ya sidebar
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['pharmacy', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacist';
$user_role = $_SESSION['role'] ?? 'pharmacy';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

try {
    $stmt = $db->query("SHOW COLUMNS FROM purchases LIKE 'branch_id'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `purchases` ADD COLUMN `branch_id` INT NULL AFTER `created_by_name`");
        $db->exec("UPDATE `purchases` SET `branch_id` = 1 WHERE `branch_id` IS NULL");
    }
} catch (Exception $e) {}

function formatMoney($amount) {
    if ($amount === null || $amount === '') return '0.00';
    return number_format((float)$amount, 2, '.', ',');
}

function formatMoneyNoDecimal($amount) {
    if ($amount === null || $amount === '') return '0';
    return number_format((float)$amount, 0, '.', ',');
}

function formatMoneyShort($amount) {
    if ($amount === null || $amount === '') return '0';
    $amount = (float)$amount;
    if ($amount >= 1000000000) return number_format($amount / 1000000000, 1) . 'B';
    if ($amount >= 1000000) return number_format($amount / 1000000, 1) . 'M';
    if ($amount >= 1000) return number_format($amount / 1000, 1) . 'K';
    return number_format($amount, 0);
}

// ================================================================
// GET SINGLE PURCHASE FOR VIEW
// ================================================================
$view_purchase = null;
$view_items = [];
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG';

if ($view_id > 0) {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as creator_name, cb.full_name as cancelled_by_full_name
        FROM purchases p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN users cb ON p.cancelled_by = cb.id
        WHERE p.id = ? AND p.branch_id = ?
    ");
    $stmt->execute([$view_id, $user_branch_id]);
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

// ================================================================
// GET FILTERS
// ================================================================
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
        (SELECT COUNT(*) FROM purchase_items WHERE purchase_id = p.id) as items_count,
        (SELECT SUM(quantity) FROM purchase_items WHERE purchase_id = p.id) as total_qty,
        (SELECT GROUP_CONCAT(DISTINCT item_name SEPARATOR ', ') 
         FROM purchase_items 
         WHERE purchase_id = p.id AND item_name IS NOT NULL) as items_list
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

// ================================================================
// GET STATISTICS
// ================================================================
$stmt = $db->prepare("SELECT COUNT(*) as count FROM purchases WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_purchases = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM purchases WHERE status = 'COMPLETED' AND branch_id = ?");
$stmt->execute([$user_branch_id]);
$completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM purchases WHERE status = 'IN_PROGRESS' AND branch_id = ?");
$stmt->execute([$user_branch_id]);
$in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM purchases WHERE status = 'CANCELLED' AND branch_id = ?");
$stmt->execute([$user_branch_id]);
$cancelled_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COALESCE(SUM(total_buying_cost), 0) as total FROM purchases WHERE status = 'COMPLETED' AND branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_spending = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("SELECT COALESCE(SUM(total_selling_value), 0) as total FROM purchases WHERE status = 'COMPLETED' AND branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_selling = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$total_profit = $total_selling - $total_spending;

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

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

    .main-content-ph {
        margin-left: 270px;
        margin-top: 68px;
        padding: 28px 32px;
        min-height: calc(100vh - 68px);
        background: var(--ph-bg-body);
    }

    /* ================================================================
       PAGE HEADER
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

    /* ================================================================
       TABLE HEADER BAR
       ================================================================ */
    .table-header-bar-ph {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 14px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--ph-border-color);
    }

    .table-header-left-ph {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        flex: 1;
        min-width: 0;
    }

    .table-header-right-ph {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }

    .table-search-box-ph {
        position: relative;
        min-width: 320px;
        flex: 1;
        max-width: 500px;
    }

    .table-search-box-ph i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(255,255,255,0.95);
        font-size: 0.9rem;
        pointer-events: none;
        z-index: 1;
    }

    .table-search-box-ph input {
        width: 100%;
        padding: 11px 16px 11px 44px;
        border: 2px solid var(--ph-primary);
        border-radius: 10px;
        font-size: 0.85rem;
        background: linear-gradient(135deg, var(--ph-primary), var(--ph-primary-dark));
        color: white;
        outline: none;
        font-weight: 500;
        height: 44px;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        transition: all 0.3s ease;
        font-family: inherit;
    }

    .table-search-box-ph input::placeholder {
        color: rgba(255,255,255,0.85);
        font-weight: 400;
    }

    .table-search-box-ph input:focus {
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.25), 0 6px 20px rgba(11, 94, 215, 0.35);
        transform: translateY(-1px);
    }

    .scroll-btn-header-ph {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        border: 2px solid var(--ph-border-color);
        background: var(--ph-bg-card);
        color: var(--ph-text-primary);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        font-size: 0.85rem;
    }

    .scroll-btn-header-ph:hover {
        background: var(--ph-primary);
        border-color: var(--ph-primary);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }

    .scroll-btn-header-ph:disabled { opacity: 0.35; cursor: not-allowed; transform: none; }

    .search-results-info-ph {
        font-size: 0.72rem;
        color: var(--ph-primary);
        padding: 6px 12px;
        background: var(--ph-primary-light);
        border-radius: 8px;
        display: none;
        font-weight: 600;
        border: 1px solid var(--ph-primary);
    }

    .card-title-ph {
        font-size: 1rem;
        font-weight: 600;
        color: var(--ph-text-primary);
        margin: 0;
    }

    .card-title-ph .title-blue { color: var(--ph-primary); }

    .result-count-ph {
        font-size: 0.8rem;
        color: var(--ph-text-secondary);
    }

    .result-count-ph strong { color: var(--ph-primary); }

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
        padding: 8px 14px;
        border: 2px solid var(--ph-border-color);
        border-radius: 8px;
        font-size: 0.82rem;
        background: var(--ph-bg-card);
        color: var(--ph-text-primary);
        outline: none;
        transition: all 0.3s ease;
        flex: 1;
        min-width: 120px;
        font-family: inherit;
        height: 40px;
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
        height: 40px;
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
        height: 40px;
        display: inline-flex;
        align-items: center;
    }

    .btn-reset-ph:hover { border-color: var(--ph-danger); color: var(--ph-danger); }

    /* ================================================================
       TABLE
       ================================================================ */
    .table-wrapper-ph { position: relative; }

    .table-scroll-container-ph {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 600px;
        scroll-behavior: smooth;
        border-radius: 12px;
        border: 2px solid var(--ph-border-color);
        background: var(--ph-bg-card);
    }

    .table-scroll-container-ph::-webkit-scrollbar { height: 10px; width: 10px; }
    .table-scroll-container-ph::-webkit-scrollbar-track { background: var(--ph-bg-body); border-radius: 6px; }
    .table-scroll-container-ph::-webkit-scrollbar-thumb { 
        background: var(--ph-primary); 
        border-radius: 6px;
        border: 2px solid var(--ph-bg-body);
    }
    .table-scroll-container-ph::-webkit-scrollbar-thumb:hover { background: var(--ph-primary-dark); }

    .data-table-ph {
        width: 100%;
        min-width: 1600px;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.82rem;
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }

    .data-table-ph thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: linear-gradient(135deg, var(--ph-primary), var(--ph-primary-dark));
        color: white;
        padding: 14px 16px;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 700;
        white-space: nowrap;
        text-align: left;
        border-right: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .data-table-ph thead th:last-child { border-right: none; text-align: center; }
    .data-table-ph thead th:first-child { border-radius: 10px 0 0 0; }
    .data-table-ph thead th:last-child { border-radius: 0 10px 0 0; }

    .data-table-ph tbody tr {
        transition: all 0.2s ease;
        background: var(--ph-bg-card);
    }

    .data-table-ph tbody tr:nth-child(even) {
        background: rgba(11, 94, 215, 0.03);
    }

    [data-theme="dark"] .data-table-ph tbody tr:nth-child(even) {
        background: rgba(11, 94, 215, 0.08);
    }

    .data-table-ph tbody tr:hover {
        background: linear-gradient(90deg, rgba(209, 250, 229, 0.5) 0%, rgba(209, 250, 229, 0.15) 100%) !important;
        box-shadow: inset 4px 0 0 var(--ph-success);
    }

    [data-theme="dark"] .data-table-ph tbody tr:hover {
        background: linear-gradient(90deg, rgba(26, 58, 42, 0.7) 0%, rgba(26, 58, 42, 0.3) 100%) !important;
        box-shadow: inset 4px 0 0 #34D399;
    }

    .data-table-ph td {
        padding: 14px 16px;
        border-bottom: 1px solid var(--ph-border-color);
        color: var(--ph-text-primary);
        vertical-align: middle;
        font-size: 0.82rem;
        line-height: 1.45;
        transition: all 0.2s ease;
    }

    .data-table-ph tbody tr:last-child td { border-bottom: none; }

    /* Column widths */
    .col-sno-ph { width: 55px; min-width: 55px; text-align: center; font-weight: 700; color: var(--ph-text-secondary); font-family: 'JetBrains Mono', monospace; }
    .col-invoice-ph { min-width: 180px; }
    .col-invoice-ph strong { font-size: 0.85rem; }
    .col-type-ph { min-width: 100px; }
    .col-creator-ph { min-width: 170px; }
    .col-items-ph { min-width: 90px; text-align: center; }
    .col-qty-ph { min-width: 80px; text-align: center; font-family: 'JetBrains Mono', monospace; }
    .col-buying-ph { min-width: 140px; text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #DC2626; }
    .col-selling-ph { min-width: 140px; text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #059669; }
    .col-profit-ph { min-width: 140px; text-align: right; font-family: 'JetBrains Mono', monospace; }
    .col-status-ph { min-width: 160px; text-align: center; }
    .col-reason-ph { min-width: 240px; max-width: 300px; white-space: normal; }
    .col-date-ph { min-width: 160px; }
    .col-actions-ph { min-width: 200px; text-align: center; padding: 12px 8px; }

    /* ================================================================
       BADGES
       ================================================================ */
    .status-badge-ph {
        padding: 5px 14px;
        border-radius: 20px;
        font-size: 0.68rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .status-badge-ph.completed { background: #D1FAE5; color: #059669; border: 1px solid rgba(5, 150, 105, 0.3); }
    .status-badge-ph.in-progress { background: #FEF3C7; color: #D97706; border: 1px solid rgba(217, 119, 6, 0.3); }
    .status-badge-ph.cancelled { background: #FEE2E2; color: #DC2626; border: 1px solid rgba(220, 38, 38, 0.3); }

    [data-theme="dark"] .status-badge-ph.completed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge-ph.in-progress { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-badge-ph.cancelled { background: #3A1A1A; color: #F87171; }

    .type-badge-ph {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .type-badge-ph.medicine { background: #E8F0FE; color: #0B5ED7; border: 1px solid rgba(11, 94, 215, 0.2); }
    .type-badge-ph.equipment { background: #EDE9FE; color: #7C3AED; border: 1px solid rgba(124, 58, 237, 0.2); }

    [data-theme="dark"] .type-badge-ph.medicine { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .type-badge-ph.equipment { background: #2D1B5F; color: #A78BFA; }

    .profit-positive-ph { color: #059669; }
    .profit-negative-ph { color: #DC2626; }

    /* ================================================================
       CANCEL REASON CELL
       ================================================================ */
    .cancel-reason-cell-ph {
        font-size: 0.75rem;
        color: #DC2626;
        background: #FEE2E2;
        padding: 8px 12px;
        border-radius: 8px;
        border-left: 4px solid #DC2626;
        word-wrap: break-word;
        white-space: normal;
        line-height: 1.4;
    }

    [data-theme="dark"] .cancel-reason-cell-ph { background: #3A1A1A; }

    .cancel-reason-cell-ph .reason-label-ph {
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        display: block;
        margin-bottom: 4px;
        color: #991B1B;
    }

    [data-theme="dark"] .cancel-reason-cell-ph .reason-label-ph { color: #FCA5A5; }

    /* ================================================================
       ACTION BUTTONS
       ================================================================ */
    .action-group-ph {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .action-btn-ph {
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 0.72rem;
        font-weight: 700;
        border: none;
        cursor: pointer;
        transition: all 0.25s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        color: white;
        min-width: 72px;
        height: 34px;
        white-space: nowrap;
        font-family: inherit;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }

    .action-btn-ph.view { background: linear-gradient(135deg, var(--ph-primary), var(--ph-primary-dark)); }
    .action-btn-ph.view:hover { background: linear-gradient(135deg, var(--ph-primary-dark), #083B87); transform: translateY(-2px); box-shadow: 0 6px 18px rgba(11, 94, 215, 0.5); color: white; }

    .action-btn-ph.pdf { background: linear-gradient(135deg, var(--ph-danger), #991B1B); }
    .action-btn-ph.pdf:hover { background: linear-gradient(135deg, #991B1B, #7F1D1D); transform: translateY(-2px); box-shadow: 0 6px 18px rgba(220, 38, 38, 0.5); color: white; }

    .action-btn-ph i { font-size: 0.72rem; }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state-ph {
        text-align: center;
        padding: 50px 20px;
        color: var(--ph-text-secondary);
    }

    .empty-state-ph i {
        font-size: 3rem;
        color: var(--ph-border-color);
        display: block;
        margin-bottom: 12px;
    }

    .empty-state-ph p { font-size: 0.95rem; font-weight: 600; margin-bottom: 6px; }
    .empty-state-ph .sub { font-size: 0.8rem; font-weight: 400; }

    .no-results-row-ph td {
        text-align: center;
        padding: 50px 20px !important;
        color: var(--ph-text-secondary);
    }

    .no-results-row-ph i {
        font-size: 2.5rem;
        color: var(--ph-border-color);
        display: block;
        margin-bottom: 10px;
    }

    .no-results-row-ph p {
        font-weight: 600;
        color: var(--ph-text-primary);
        margin: 0;
    }

    /* ================================================================
       MODAL
       ================================================================ */
    .modal-overlay-ph {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.75);
        z-index: 999999;
        justify-content: center;
        align-items: center;
        padding: 20px;
        backdrop-filter: blur(4px);
    }

    .modal-overlay-ph.show { display: flex; }

    .modal-content-ph {
        background: var(--ph-bg-card);
        border-radius: 12px;
        max-width: 1200px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        padding: 24px 28px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        border: 2px solid var(--ph-border-color);
        position: relative;
        z-index: 1000000;
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
        display: inline-flex;
        align-items: center;
        gap: 6px;
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
        text-decoration: none;
    }

    .btn-print-invoice-ph:hover { background: #991B1B; transform: translateY(-2px); color: white; }

    /* ================================================================
       VIEW GRID
       ================================================================ */
    .view-grid-ph {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 14px;
    }

    .view-item-ph {
        padding: 12px 16px;
        background: var(--ph-bg-body);
        border-radius: 8px;
        border: 1px solid var(--ph-border-color);
    }

    .view-item-ph .label {
        font-size: 0.6rem;
        text-transform: uppercase;
        color: var(--ph-text-secondary);
        font-weight: 700;
        letter-spacing: 0.05em;
    }

    .view-item-ph .value {
        font-size: 0.92rem;
        font-weight: 600;
        color: var(--ph-text-primary);
        margin-top: 3px;
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

    .cancelled-info-box-ph i { color: #DC2626; font-size: 1.5rem; flex-shrink: 0; margin-top: 2px; }
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

    .cancelled-info-box-ph .info-text-ph .info-row-ph .info-label-ph { font-weight: 600; min-width: 100px; }
    .cancelled-info-box-ph .info-text-ph .info-row-ph .info-value-ph { font-weight: 600; color: #450A0A; word-break: break-word; }

    [data-theme="dark"] .cancelled-info-box-ph .info-text-ph .info-row-ph .info-value-ph { color: #FECACA; }

    /* ================================================================
       INVOICE GROUP (View Modal)
       ================================================================ */
    .invoice-group-ph {
        border: 2px solid var(--ph-border-color);
        border-radius: 10px;
        margin-bottom: 14px;
        overflow: hidden;
        background: var(--ph-bg-card);
    }

    .invoice-group-header-ph {
        background: linear-gradient(135deg, var(--ph-primary), var(--ph-primary-dark));
        color: white;
        padding: 12px 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .invoice-group-header-ph .ig-title-ph {
        font-size: 0.9rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .invoice-group-header-ph .ig-stats-ph {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .invoice-group-header-ph .ig-stat-ph {
        background: rgba(255,255,255,0.2);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .invoice-group-header-ph .ig-margin-ph {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .invoice-group-header-ph .ig-margin-ph.positive { background: #D1FAE5; color: #059669; }
    .invoice-group-header-ph .ig-margin-ph.negative { background: #FEE2E2; color: #DC2626; }

    .invoice-group-body-ph { padding: 0; overflow-x: auto; }

    /* ================================================================
       VIEW ITEMS TABLE
       ================================================================ */
    .view-items-table-ph {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.75rem;
        table-layout: auto;
        min-width: 1100px;
    }

    .view-items-table-ph thead th {
        background: rgba(11, 94, 215, 0.08);
        color: var(--ph-primary);
        padding: 10px 10px;
        font-size: 0.6rem;
        text-transform: uppercase;
        font-weight: 700;
        text-align: left;
        border-bottom: 2px solid var(--ph-primary);
        letter-spacing: 0.04em;
        white-space: nowrap;
    }

    [data-theme="dark"] .view-items-table-ph thead th {
        background: rgba(110, 168, 254, 0.1);
        color: #6EA8FE;
    }

    .view-items-table-ph tbody td {
        padding: 10px 10px;
        border-bottom: 1px solid var(--ph-border-color);
        color: var(--ph-text-primary);
        font-size: 0.76rem;
        white-space: nowrap;
    }

    .view-items-table-ph tbody tr:nth-child(even) { background: rgba(11, 94, 215, 0.03); }
    [data-theme="dark"] .view-items-table-ph tbody tr:nth-child(even) { background: rgba(11, 94, 215, 0.08); }

    .view-items-table-ph tbody tr:hover { background: rgba(209, 250, 229, 0.4); }
    [data-theme="dark"] .view-items-table-ph tbody tr:hover { background: rgba(26, 58, 42, 0.5); }

    .item-name-cell-ph {
        display: flex;
        flex-direction: column;
        gap: 3px;
        white-space: normal;
        min-width: 200px;
    }

    .item-name-cell-ph strong {
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--ph-text-primary);
    }

    .item-name-cell-ph .item-meta-ph {
        font-size: 0.65rem;
        color: var(--ph-text-muted);
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .item-name-cell-ph .item-meta-ph span {
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }

    .item-batch-tag-ph {
        background: #FEF3C7;
        color: #D97706;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.62rem;
        font-weight: 700;
        font-family: 'JetBrains Mono', monospace;
    }

    [data-theme="dark"] .item-batch-tag-ph {
        background: #3D2E0A;
        color: #FBBF24;
    }

    /* ADDED BY TAG */
    .added-by-tag-ph {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        background: #EDE9FE;
        color: #7C3AED;
        white-space: nowrap;
        border: 1px solid rgba(124, 58, 237, 0.2);
    }

    [data-theme="dark"] .added-by-tag-ph {
        background: #2D1B4E;
        color: #C4B5FD;
        border-color: rgba(196, 181, 253, 0.2);
    }

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
        .main-content-ph { margin-left: 0; padding: 16px; }
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
        .data-table-ph { min-width: 1200px; font-size: 0.72rem; }
        .data-table-ph th, .data-table-ph td { padding: 10px 12px; }
        .view-grid-ph { grid-template-columns: 1fr; }
        .modal-content-ph { padding: 16px; }
        .table-search-box-ph { min-width: 100%; max-width: 100%; }
        .table-header-bar-ph { flex-direction: column; align-items: stretch; }
        .view-items-table-ph { min-width: 1000px; }
    }

    @media (max-width: 480px) {
        .stats-grid-ph { grid-template-columns: 1fr 1fr; }
        .stat-card-ph .stat-number { font-size: 0.9rem; }
        .stat-card-ph { padding: 8px 10px; min-height: 55px; }
        .page-header-box-ph { flex-direction: column; align-items: flex-start !important; }
    }

    @media print {
        .btn-back-ph, .btn-search-ph, .btn-reset-ph, .action-group-ph,
        .scroll-btn-header-ph, .filter-group-ph, .search-form-ph, .table-search-box-ph { display: none !important; }
        .page-header-box-ph { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<main class="main-content-ph">

    <!-- PAGE HEADER -->
    <div class="page-header-box-ph animate-fade-in-up-ph">
        <div>
            <h1 class="page-title-ph">
                <i class="fas fa-history"></i>
                Purchase History
                <span class="role-badge-display-ph"><?= strtoupper($user_role) ?></span>
                <span class="branch-name-display-ph">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </h1>
        </div>
        <div class="header-actions-ph">
            <a href="inventory.php" class="btn-back-ph">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
            <a href="purchases.php" class="btn-back-ph">
                <i class="fas fa-shopping-cart"></i> New Purchase
            </a>
        </div>
    </div>

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
        <a href="purchase_history.php?status=CANCELLED" class="stat-card-ph dark-red" style="text-decoration:none;">
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
            <a href="purchase_history.php?status=all" class="filter-btn-ph <?= $status_filter === 'all' ? 'active' : '' ?>">All</a>
            <a href="purchase_history.php?status=COMPLETED" class="filter-btn-ph <?= $status_filter === 'COMPLETED' ? 'active' : '' ?>">✅ Completed</a>
            <a href="purchase_history.php?status=IN_PROGRESS" class="filter-btn-ph <?= $status_filter === 'IN_PROGRESS' ? 'active' : '' ?>">⏳ In Progress</a>
            <a href="purchase_history.php?status=CANCELLED" class="filter-btn-ph cancelled-filter-ph <?= $status_filter === 'CANCELLED' ? 'active' : '' ?>">
                <i class="fas fa-ban"></i> Cancelled (<?= $cancelled_count ?>)
            </a>
            <a href="purchase_history.php?type=medicine" class="filter-btn-ph <?= $type_filter === 'medicine' ? 'active' : '' ?>">💊 Medicine</a>
            <a href="purchase_history.php?type=equipment" class="filter-btn-ph <?= $type_filter === 'equipment' ? 'active' : '' ?>">🔧 Equipment</a>
            <?php if ($status_filter !== 'all' || $type_filter !== 'all' || !empty($date_from) || !empty($date_to)): ?>
                <a href="purchase_history.php" class="filter-btn-ph clear-filter-ph">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </div>
        
        <form method="GET" class="search-form-ph">
            <input type="hidden" name="status" value="<?= $status_filter ?>">
            <input type="hidden" name="type" value="<?= $type_filter ?>">
            <input type="date" name="date_from" value="<?= $date_from ?>" placeholder="From">
            <input type="date" name="date_to" value="<?= $date_to ?>" placeholder="To">
            <button type="submit" class="btn-search-ph"><i class="fas fa-search"></i> Filter</button>
            <a href="purchase_history.php" class="btn-reset-ph"><i class="fas fa-times"></i> Reset</a>
        </form>
    </div>

    <!-- PURCHASE TABLE -->
    <div class="card-ph animate-fade-in-up-ph" style="animation-delay:0.15s;">
        
        <!-- TABLE HEADER BAR -->
        <div class="table-header-bar-ph">
            <div class="table-header-left-ph">
                <div class="table-search-box-ph">
                    <i class="fas fa-search"></i>
                    <input type="text" id="phSearchInput" placeholder="🔍 Auto-search invoice, item, batch, creator, reason..." autocomplete="off">
                </div>
                <h3 class="card-title-ph" style="margin:0;">
                    <i class="fas fa-list title-blue"></i> Purchase List
                    <span class="result-count-ph" id="phCountDisplay">(<strong><?= count($purchases) ?></strong> purchases)</span>
                </h3>
                <span class="search-results-info-ph" id="phSearchInfo">
                    <strong id="phSearchCount">0</strong> match
                </span>
                <?php if ($status_filter === 'CANCELLED'): ?>
                    <span style="font-size:0.7rem;color:#DC2626;font-weight:600;">
                        <i class="fas fa-ban"></i> Cancelled Only
                    </span>
                <?php endif; ?>
            </div>
            <div class="table-header-right-ph">
                <button class="scroll-btn-header-ph" id="phScrollBtnLeft" onclick="scrollTable('left')" title="Scroll Left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="scroll-btn-header-ph" id="phScrollBtnRight" onclick="scrollTable('right')" title="Scroll Right">
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
                        <tbody id="purchaseTableBody">
                            <?php $counter = 1; ?>
                            <?php foreach ($purchases as $purchase): ?>
                                <?php 
                                    $profit = ($purchase['total_selling_value'] ?? 0) - ($purchase['total_buying_cost'] ?? 0);
                                    $profit_class = $profit >= 0 ? 'profit-positive-ph' : 'profit-negative-ph';
                                    $status_class = strtolower($purchase['status']);
                                    $status_icon = $purchase['status'] === 'COMPLETED' ? 'fa-check-circle' : ($purchase['status'] === 'IN_PROGRESS' ? 'fa-spinner fa-spin' : 'fa-ban');
                                    $has_items = ($purchase['items_count'] ?? 0) > 0;
                                    $is_cancelled = ($purchase['status'] === 'CANCELLED');
                                    
                                    $search_data = strtolower(
                                        ($purchase['invoice_number'] ?? '') . ' ' .
                                        ($purchase['purchase_type'] ?? '') . ' ' .
                                        ($purchase['creator_name'] ?? $purchase['created_by_name'] ?? '') . ' ' .
                                        ($purchase['cancelled_reason'] ?? '') . ' ' .
                                        ($purchase['cancelled_by_name'] ?? '') . ' ' .
                                        ($purchase['status'] ?? '') . ' ' .
                                        ($purchase['items_list'] ?? '')
                                    );
                                ?>
                                <tr class="ph-row" data-search="<?= htmlspecialchars($search_data) ?>" style="<?= $is_cancelled ? 'background:rgba(220,38,38,0.05);' : '' ?>">
                                    <td class="col-sno-ph"><?= $counter++ ?></td>
                                    <td class="col-invoice-ph">
                                        <strong style="<?= $is_cancelled ? 'text-decoration:line-through;color:#DC2626;' : '' ?>">
                                            <?= htmlspecialchars($purchase['invoice_number']) ?>
                                        </strong>
                                    </td>
                                    <td class="col-type-ph">
                                        <span class="type-badge-ph <?= $purchase['purchase_type'] ?>">
                                            <i class="fas <?= $purchase['purchase_type'] === 'medicine' ? 'fa-pills' : 'fa-tools' ?>"></i>
                                            <?= ucfirst($purchase['purchase_type']) ?>
                                        </span>
                                    </td>
                                    <td class="col-creator-ph">
                                        <i class="fas fa-user" style="color:#0B5ED7;font-size:0.7rem;"></i>
                                        <?= htmlspecialchars($purchase['creator_name'] ?? $purchase['created_by_name'] ?? 'Unknown') ?>
                                    </td>
                                    <td class="col-items-ph"><?= number_format($purchase['items_count'] ?? 0) ?></td>
                                    <td class="col-qty-ph"><?= number_format($purchase['total_qty'] ?? 0) ?></td>
                                    <td class="col-buying-ph">
                                        TSh <?= number_format($purchase['total_buying_cost'] ?? 0) ?>
                                    </td>
                                    <td class="col-selling-ph">
                                        TSh <?= number_format($purchase['total_selling_value'] ?? 0) ?>
                                    </td>
                                    <td class="col-profit-ph">
                                        <span class="<?= $profit_class ?>" style="font-weight:700;">
                                            TSh <?= number_format($profit) ?>
                                            <?php if ($purchase['total_buying_cost'] > 0 && $purchase['status'] === 'COMPLETED'): ?>
                                                <span style="font-size:0.62rem;display:block;color:var(--ph-text-muted);">
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
                                            <div style="font-size:0.6rem;color:var(--ph-text-muted);margin-top:4px;">
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
                                                    <div style="font-size:0.65rem;margin-top:4px;color:#7F1D1D;font-style:italic;">
                                                        <i class="fas fa-user-slash"></i> By: <?= htmlspecialchars($purchase['cancelled_by_name']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="font-size:0.75rem;color:var(--ph-text-muted);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-date-ph">
                                        <?= date('d/m/Y H:i', strtotime($purchase['created_at'])) ?>
                                        <?php if ($purchase['status'] === 'COMPLETED' && $purchase['completed_at']): ?>
                                            <br><span style="font-size:0.6rem;color:var(--ph-text-muted);">
                                                <i class="fas fa-check-circle" style="color:#059669;"></i>
                                                <?= date('d/m/Y H:i', strtotime($purchase['completed_at'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-actions-ph">
                                        <div class="action-group-ph">
                                            <a href="purchase_history.php?view=<?= $purchase['id'] ?>" class="action-btn-ph view" title="View Details">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            
                                            <?php if ($purchase['status'] === 'COMPLETED' && $has_items): ?>
                                                <a href="get_invoice.php?id=<?= $purchase['id'] ?>" target="_blank" class="action-btn-ph pdf" title="View PDF Invoice">
                                                    <i class="fas fa-file-pdf"></i> PDF
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="no-results-row-ph" id="phNoResults" style="display:none;">
                                <td colspan="13">
                                    <i class="fas fa-search-minus"></i>
                                    <p>No purchases match your search</p>
                                </td>
                            </tr>
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
            Pharmacy Purchase History
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
<div class="modal-overlay-ph show" id="viewModal" style="display:flex;z-index:999999;">
    <div class="modal-content-ph" style="z-index:1000000;">
        <div class="modal-header-ph">
            <div class="modal-title-ph">
                <i class="fas fa-eye"></i> Purchase Details - <?= htmlspecialchars($view_purchase['invoice_number']) ?>
                <span style="font-size:0.75rem;font-weight:400;color:var(--ph-text-secondary);margin-left:8px;">
                    (<?= ucfirst($view_purchase['purchase_type']) ?>)
                </span>
            </div>
            <a href="purchase_history.php" class="modal-close-ph">&times;</a>
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
            <div class="view-item-ph full-width" style="background:<?= $view_profit >= 0 ? '#D1FAE5' : '#FEE2E2' ?>;border:2px solid <?= $view_profit >= 0 ? '#059669' : '#DC2626' ?>;">
                <div class="label" style="color:<?= $view_profit >= 0 ? '#059669' : '#DC2626' ?>;">
                    <i class="fas fa-chart-line"></i> Margin / Expected Profit (Per Invoice)
                </div>
                <div class="value <?= $view_profit_class ?>" style="font-size:1.1rem;">
                    TSh <?= number_format($view_profit) ?>
                    <?php if ($view_purchase['total_buying_cost'] > 0): ?>
                        <span style="font-size:0.75rem;font-weight:600;background:<?= $view_profit >= 0 ? '#059669' : '#DC2626' ?>;color:white;padding:3px 10px;border-radius:12px;margin-left:8px;">
                            <?= round(($view_profit / $view_purchase['total_buying_cost']) * 100, 1) ?>% margin
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- ITEMS GROUPED BY INVOICE -->
        <?php if (count($view_items) > 0): ?>
            <div style="margin-top:16px;">
                <div class="invoice-group-ph">
                    <div class="invoice-group-header-ph">
                        <div class="ig-title-ph">
                            <i class="fas fa-file-invoice"></i>
                            Invoice: <?= htmlspecialchars($view_purchase['invoice_number']) ?>
                        </div>
                        <div class="ig-stats-ph">
                            <span class="ig-stat-ph">
                                <i class="fas fa-boxes"></i> <?= count($view_items) ?> items
                            </span>
                            <span class="ig-stat-ph">
                                <i class="fas fa-coins"></i> Buy: TSh <?= number_format($view_purchase['total_buying_cost'] ?? 0) ?>
                            </span>
                            <span class="ig-stat-ph">
                                <i class="fas fa-tag"></i> Sell: TSh <?= number_format($view_purchase['total_selling_value'] ?? 0) ?>
                            </span>
                            <span class="ig-margin-ph <?= $view_profit >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas <?= $view_profit >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i>
                                Margin: TSh <?= number_format($view_profit) ?>
                                <?php if ($view_purchase['total_buying_cost'] > 0): ?>
                                    (<?= round(($view_profit / $view_purchase['total_buying_cost']) * 100, 1) ?>%)
                                <?php endif; ?>
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
                                    <th style="text-align:right;width:105px;">Profit</th>
                                    <th style="min-width:150px;text-align:center;">Added By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $vcounter = 1; $item_total_profit = 0; ?>
                                <?php foreach ($view_items as $item): 
                                    $item_profit = ($item['total_selling_value'] ?? 0) - ($item['total_buying_cost'] ?? 0);
                                    $item_total_profit += $item_profit;
                                ?>
                                    <tr>
                                        <td style="text-align:center;font-weight:700;color:var(--ph-text-secondary);"><?= $vcounter++ ?></td>
                                        <td>
                                            <div class="item-name-cell-ph">
                                                <strong><?= htmlspecialchars($item['item_name'] ?? 'Unknown') ?></strong>
                                                <div class="item-meta-ph">
                                                    <?php if (!empty($item['batch_number']) && $item['batch_number'] !== 'N/A'): ?>
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
                                        <td style="text-align:right;font-weight:700;color:<?= $item_profit >= 0 ? '#059669' : '#DC2626' ?>;">
                                            TSh <?= number_format($item_profit) ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="added-by-tag-ph">
                                                <i class="fas fa-user-circle"></i>
                                                <?= htmlspecialchars($item['added_by_full_name'] ?? 'Unknown') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background:rgba(11, 94, 215, 0.08);">
                                    <td colspan="6" style="text-align:right;font-weight:700;color:var(--ph-text-primary);padding:12px 14px;font-size:0.75rem;">
                                        TOTALS:
                                    </td>
                                    <td style="text-align:right;font-weight:800;color:#DC2626;padding:12px 14px;font-size:0.85rem;border-top:2px solid #DC2626;">
                                        TSh <?= number_format($view_purchase['total_buying_cost'] ?? 0) ?>
                                    </td>
                                    <td></td>
                                    <td style="text-align:right;font-weight:800;color:#059669;padding:12px 14px;font-size:0.85rem;border-top:2px solid #059669;">
                                        TSh <?= number_format($view_purchase['total_selling_value'] ?? 0) ?>
                                    </td>
                                    <td style="text-align:right;font-weight:800;color:<?= $item_total_profit >= 0 ? '#059669' : '#DC2626' ?>;padding:12px 14px;font-size:0.85rem;border-top:2px solid <?= $item_total_profit >= 0 ? '#059669' : '#DC2626' ?>;">
                                        TSh <?= number_format($item_total_profit) ?>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
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
                <a href="get_invoice.php?id=<?= $view_purchase['id'] ?>" target="_blank" class="btn-print-invoice-ph">
                    <i class="fas fa-file-pdf"></i> PDF Invoice
                </a>
            <?php endif; ?>
            <a href="purchase_history.php" class="btn-close-modal-ph">
                <i class="fas fa-times"></i> Close
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // AUTO-SEARCH
    (function() {
        var searchInput = document.getElementById('phSearchInput');
        var countDisplay = document.getElementById('phCountDisplay');
        var searchInfo = document.getElementById('phSearchInfo');
        var searchCount = document.getElementById('phSearchCount');
        var noResults = document.getElementById('phNoResults');
        
        if (!searchInput) return;
        
        searchInput.addEventListener('input', function() {
            var q = this.value.toLowerCase().trim();
            var rows = document.querySelectorAll('.ph-row');
            var total = rows.length;
            var visible = 0;
            
            rows.forEach(function(row) {
                var searchData = row.getAttribute('data-search') || '';
                if (q === '' || searchData.includes(q)) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            if (countDisplay) {
                if (q === '') {
                    countDisplay.innerHTML = '(<strong>' + total + '</strong> purchases)';
                } else {
                    countDisplay.innerHTML = '(<strong>' + visible + '</strong> of ' + total + ')';
                }
            }
            
            if (searchInfo && searchCount) {
                if (q === '') {
                    searchInfo.style.display = 'none';
                } else {
                    searchInfo.style.display = 'inline-flex';
                    searchCount.textContent = visible;
                }
            }
            
            if (noResults) {
                noResults.style.display = (visible === 0 && q !== '') ? '' : 'none';
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

    // SCROLL BUTTONS
    function scrollTable(direction) {
        var container = document.getElementById('tableScrollContainer');
        if (!container) return;
        var scrollAmount = 400;
        if (direction === 'left') {
            container.scrollLeft -= scrollAmount;
        } else {
            container.scrollLeft += scrollAmount;
        }
    }
    
    function updateScrollButtons() {
        var container = document.getElementById('tableScrollContainer');
        var btnLeft = document.getElementById('phScrollBtnLeft');
        var btnRight = document.getElementById('phScrollBtnRight');
        
        if (!container || !btnLeft || !btnRight) return;
        
        var scrollLeft = container.scrollLeft;
        var maxScroll = container.scrollWidth - container.clientWidth;
        
        btnLeft.disabled = scrollLeft <= 5;
        btnRight.disabled = scrollLeft >= maxScroll - 5 || maxScroll <= 0;
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        var container = document.getElementById('tableScrollContainer');
        if (container) {
            container.addEventListener('scroll', updateScrollButtons);
            setTimeout(updateScrollButtons, 200);
        }
    });

    // FOOTER TIME
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    console.log('%c📜 Braick - Pharmacy Purchase History V9.2', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Table header search (auto-filter, no submit)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Column "Added By" kwenye View Modal (per item)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Group dawa kwa invoice zao + margin per invoice', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Modal ipo MBELE ya sidebar (z-index 999999)', 'font-size:13px; color:#34D399;');
    console.log('%c💡 Press Ctrl+K to focus search', 'font-size:13px; color:#D97706; font-weight:bold;');
</script>

</body>
</html>