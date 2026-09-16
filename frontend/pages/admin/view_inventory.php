<?php
// ================================================================
// FILE: frontend/pages/admin/view_inventory.php
// ADMIN - VIEW INVENTORY ITEM DETAILS
// ✅ Uses SHARED header & sidebar
// ✅ Full dark mode support
// ✅ Blue page header card
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$inventory_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';

if ($inventory_id <= 0) {
    header('Location: pharmacy_inventory.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// GET BRANCHES
$branches = [];
try {
    $stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// FETCH INVENTORY ITEM
$stmt = $db->prepare("
    SELECT 
        mi.*,
        b.name as branch_name,
        b.location as branch_location,
        b.phone as branch_phone,
        b.email as branch_email,
        (SELECT COUNT(*) FROM stock_movements WHERE inventory_id = mi.id) as total_movements,
        (SELECT COUNT(*) FROM otc_sale_items WHERE inventory_id = mi.id) as total_otc_sales,
        (SELECT COUNT(*) FROM prescription_items WHERE inventory_id = mi.id) as total_prescriptions
    FROM medications_inventory mi
    LEFT JOIN branches b ON mi.branch_id = b.id
    WHERE mi.id = ?
");
$stmt->execute([$inventory_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header('Location: pharmacy_inventory.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
    exit;
}

// GET STOCK MOVEMENTS
$movements = [];
try {
    $stmt = $db->prepare("
        SELECT sm.*, u.full_name as performed_by_name
        FROM stock_movements sm
        LEFT JOIN users u ON sm.performed_by = u.id
        WHERE sm.inventory_id = ?
        ORDER BY sm.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$inventory_id]);
    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $movements = [];
}

// GET RELATED SALES
$related_sales = [];
try {
    $stmt = $db->prepare("
        SELECT 
            os.sale_number, os.customer_name, os.total_amount, os.discount_amount,
            os.payment_method, os.payment_status, os.created_at,
            osi.quantity, osi.unit_price, osi.total_price, 'OTC' as sale_type, osi.instructions
        FROM otc_sale_items osi
        LEFT JOIN otc_sales os ON osi.sale_id = os.id
        WHERE osi.inventory_id = ?
        ORDER BY os.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$inventory_id]);
    $related_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $related_sales = [];
}

// STATUS CALCULATIONS
$quantity = $item['quantity'] ?? 0;
$reorder_level = $item['reorder_level'] ?? 0;
$expiry_date = $item['expiry_date'] ?? null;

$is_out_of_stock = $quantity <= 0;
$is_low_stock = $quantity > 0 && $quantity <= $reorder_level;
$is_in_stock = $quantity > $reorder_level;
$is_expired = !empty($expiry_date) && $expiry_date !== '0000-00-00' && strtotime($expiry_date) < time();
$is_expiring_soon = !empty($expiry_date) && $expiry_date !== '0000-00-00' && strtotime($expiry_date) > time() && strtotime($expiry_date) < strtotime('+30 days');
$is_healthy = $is_in_stock && !$is_expired && !$is_expiring_soon;

function getStatusBadge($status) {
    $classes = [
        'active' => 'success', 'inactive' => 'danger', 'pending' => 'warning',
        'paid' => 'success', 'partial' => 'warning', 'cancelled' => 'danger',
        'otc' => 'info', 'prescription' => 'purple'
    ];
    return $classes[$status] ?? 'secondary';
}

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

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// INCLUDE SHARED HEADER & SIDEBAR
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS - FULL DARK MODE -->
<!-- ================================================================ -->
<style>
    /* LIGHT MODE */
    :root {
        --page-bg-body: #F0F4F8;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-text-muted: #94A3B8;
        --page-border: #E2E8F0;
        --page-hover: #F8FAFC;
    }

    /* DARK MODE */
    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-text-muted: #64748B;
        --page-border: #334155;
        --page-hover: #1E3A5F;
    }

    /* BODY & MAIN CONTENT */
    body { background: var(--page-bg-body, #F0F4F8); }
    html[data-theme="dark"] body { background: #0F172A !important; }
    .main-content { background: var(--page-bg-body, #F0F4F8); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; color: #F1F5F9; }

    /* PAGE HEADER */
    .page-header-custom {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083D8A 100%);
        border-radius: 18px;
        padding: 28px 36px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
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

    .page-header-custom .page-title {
        color: white;
        font-size: 1.8rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-custom .page-title i {
        width: 48px; height: 48px;
        background: rgba(255,255,255,0.2);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-custom .page-subtitle {
        color: rgba(255,255,255,0.9);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 8px;
    }

    .page-header-custom .role-badge-display {
        background: rgba(255,255,255,0.25);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .page-header-custom .header-badge {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-custom .btn-outline-light {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1.5px solid rgba(255,255,255,0.3);
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.82rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        position: relative;
        z-index: 1;
    }

    .page-header-custom .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    /* DETAIL CARDS */
    .detail-card-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        margin-bottom: 24px;
    }

    html[data-theme="dark"] .detail-card-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .detail-card-custom:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.08);
    }

    /* ITEM STATUS CARD */
    .item-status-card {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid #6EA8FE;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 24px;
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.1);
    }

    html[data-theme="dark"] .item-status-card {
        background: #1E293B;
        border-color: #6EA8FE;
    }

    .item-status-card .item-name {
        font-size: 1.4rem;
        font-weight: 800;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
    }

    html[data-theme="dark"] .item-status-card .item-name { color: #F1F5F9; }

    .item-status-card .item-meta {
        font-size: 0.85rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 4px;
    }

    .item-status-card .item-meta i {
        color: #0B5ED7;
    }

    /* STATUS BADGES LARGE */
    .status-badge-large {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 22px;
        border-radius: 50px;
        font-weight: 700;
        font-size: 0.9rem;
        color: white;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .status-badge-large.success { background: linear-gradient(135deg, #059669, #047857); }
    .status-badge-large.danger { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    .status-badge-large.warning { background: linear-gradient(135deg, #D97706, #B45309); }
    .status-badge-large.info { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .status-badge-large.secondary { background: linear-gradient(135deg, #64748B, #475569); }

    /* DETAIL GRID */
    .detail-grid-custom {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px 28px;
    }

    .detail-item-custom {
        padding: 6px 0;
    }

    .detail-label-custom {
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 4px;
    }

    .detail-label-custom i {
        color: #0B5ED7;
        margin-right: 4px;
    }

    .detail-value-custom {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
    }

    html[data-theme="dark"] .detail-value-custom { color: #F1F5F9; }

    .detail-value-custom.text-red { color: #DC2626; }
    .detail-value-custom.text-amber { color: #D97706; }
    .detail-value-custom.text-green { color: #059669; }
    .detail-value-custom.text-primary { color: #0B5ED7; }

    /* CARDS */
    .card-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        margin-bottom: 24px;
    }

    html[data-theme="dark"] .card-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .card-custom:hover {
        border-color: #0B5ED7;
    }

    .card-header-custom {
        padding: 16px 24px;
        background: var(--page-hover, #F8FAFC);
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    html[data-theme="dark"] .card-header-custom {
        background: #0F172A;
        border-color: #334155;
    }

    .card-title-custom {
        font-size: 0.95rem;
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

    /* DATA TABLE */
    .data-table-custom {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.8rem;
    }

    .data-table-custom thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        font-weight: 700;
        padding: 12px 14px;
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
        text-align: left;
    }

    .data-table-custom thead th:first-child { border-radius: 8px 0 0 0; }
    .data-table-custom thead th:last-child { border-radius: 0 8px 0 0; }

    .data-table-custom td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: middle;
        transition: background 0.2s ease;
    }

    html[data-theme="dark"] .data-table-custom td {
        color: #F1F5F9;
        border-bottom-color: #334155;
    }

    .data-table-custom tbody tr:hover td {
        background: var(--page-hover, #F8FAFC);
    }

    html[data-theme="dark"] .data-table-custom tbody tr:hover td {
        background: #1E3A5F;
    }

    .data-table-custom tbody tr:last-child td {
        border-bottom: none;
    }

    /* BADGES */
    .badge-custom {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        color: white;
    }

    .badge-success { background: #059669; }
    .badge-danger { background: #DC2626; }
    .badge-warning { background: #D97706; }
    .badge-info { background: #0B5ED7; }
    .badge-secondary { background: #64748B; }
    .badge-purple { background: #7C3AED; }

    /* BUTTONS */
    .btn-custom {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.82rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn-primary-custom {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
    }
    .btn-primary-custom:hover {
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(11, 94, 215, 0.35);
    }

    .btn-success-custom {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
    }
    .btn-success-custom:hover {
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(5, 150, 105, 0.35);
    }

    .btn-danger-custom {
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        color: white;
    }
    .btn-danger-custom:hover {
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(220, 38, 38, 0.35);
    }

    .btn-outline-custom {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }
    .btn-outline-custom:hover {
        background: var(--page-hover, #F1F5F9);
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: translateY(-2px);
    }

    /* EMPTY STATE */
    .empty-state-custom {
        text-align: center;
        padding: 40px 20px;
        color: var(--page-text-secondary, #64748B);
    }

    .empty-state-custom i {
        font-size: 2.5rem;
        color: var(--page-text-muted, #94A3B8);
        margin-bottom: 12px;
        display: block;
        opacity: 0.5;
    }

    .empty-state-custom h4 {
        font-size: 1rem;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 4px;
    }

    html[data-theme="dark"] .empty-state-custom h4 { color: #F1F5F9; }

    .empty-state-custom p {
        font-size: 0.85rem;
        color: var(--page-text-secondary, #64748B);
    }

    /* QUICK ACTIONS */
    .quick-actions-card {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--page-border, #E2E8F0);
        margin-bottom: 24px;
    }

    html[data-theme="dark"] .quick-actions-card {
        background: #1E293B;
        border-color: #334155;
    }

    .quick-actions-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--page-text-secondary, #64748B);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .quick-actions-title i { color: #0B5ED7; }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
        .detail-grid-custom { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-custom { padding: 18px; }
        .page-header-custom .page-title { font-size: 1.15rem; }
        .detail-grid-custom { grid-template-columns: 1fr; }
        .detail-card-custom { padding: 16px; }
        .data-table-custom { font-size: 0.7rem; }
        .data-table-custom thead th, .data-table-custom td { padding: 6px 8px; }
    }

    @media print {
        .page-header-custom { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; }
        .btn-custom, .quick-actions-card { display: none !important; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div style="position:relative;z-index:1;">
            <h1 class="page-title">
                <i class="fas fa-capsules"></i>
                Inventory Item Details
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <span><i class="fas fa-pills"></i> <strong><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></strong></span>
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i> ID: #<?= $item['id'] ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-store"></i> <?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($user_full_name) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="edit_inventory.php?id=<?= $item['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="pharmacy_inventory.php?id=<?= $item['branch_id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ITEM STATUS SUMMARY -->
    <div class="item-status-card">
        <div>
            <h2 class="item-name"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></h2>
            <p class="item-meta">
                <i class="fas fa-tag"></i> <?= htmlspecialchars($item['category'] ?? 'Uncategorized') ?>
                <span style="margin:0 8px;color:#CBD5E1;">|</span>
                <i class="fas fa-box"></i> <?= htmlspecialchars($item['unit'] ?? 'Unit') ?>
            </p>
        </div>
        <div>
            <?php if ($is_out_of_stock) { ?>
                <span class="status-badge-large danger">
                    <i class="fas fa-times-circle"></i> Out of Stock
                </span>
            <?php } elseif ($is_low_stock) { ?>
                <span class="status-badge-large warning">
                    <i class="fas fa-exclamation-triangle"></i> Low Stock
                </span>
            <?php } elseif ($is_expired) { ?>
                <span class="status-badge-large danger">
                    <i class="fas fa-skull"></i> Expired
                </span>
            <?php } elseif ($is_expiring_soon) { ?>
                <span class="status-badge-large warning">
                    <i class="fas fa-hourglass-half"></i> Expiring Soon
                </span>
            <?php } elseif ($is_healthy) { ?>
                <span class="status-badge-large success">
                    <i class="fas fa-check-circle"></i> In Stock
                </span>
            <?php } else { ?>
                <span class="status-badge-large secondary">
                    <i class="fas fa-circle"></i> Unknown
                </span>
            <?php } ?>
        </div>
    </div>

    <!-- ITEM DETAILS -->
    <div class="detail-card-custom">
        <div class="detail-grid-custom">
            <div class="detail-item-custom">
                <p class="detail-label-custom"><i class="fas fa-hashtag"></i> Item ID</p>
                <p class="detail-value-custom">#<?= $item['id'] ?></p>
            </div>
            <div class="detail-item-custom">
                <p class="detail-label-custom"><i class="fas fa-store"></i> Branch</p>
                <p class="detail-value-custom"><?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?></p>
            </div>
            <div class="detail-item-custom">
                <p class="detail-label-custom"><i class="fas fa-tag"></i> Category</p>
                <p class="detail-value-custom"><?= htmlspecialchars($item['category'] ?? 'N/A') ?></p>
            </div>
            <div class="detail-item-custom">
                <p class="detail-label-custom"><i class="fas fa-cubes"></i> Quantity</p>
                <p class="detail-value-custom <?= $is_out_of_stock ? 'text-red' : ($is_low_stock ? 'text-amber' : 'text-green') ?>">
                    <?= number_format($item['quantity'] ?? 0) ?> <?= htmlspecialchars($item['unit'] ?? '') ?>
                </p>
            </div>
            <div class="detail-item-custom">
                <p class="detail-label-custom"><i class="fas fa-flag"></i> Reorder Level</p>
                <p class="detail-value-custom"><?= number_format($item['reorder_level'] ?? 0) ?> <?= htmlspecialchars($item['unit'] ?? '') ?></p>
            </div>
            <div class="detail-item-custom">
                <p class="detail-label-custom"><i class="fas fa-money-bill-wave"></i> Selling Price</p>
                <p class="detail-value-custom text-primary">TSh <?= number_format($item['selling_price'] ?? 0, 0) ?></p>
            </div>
            <div class="detail-item-custom">
                <p class="detail-label-custom"><i class="fas fa-coins"></i> Unit Cost</p>
                <p class="detail-value-custom">TSh <?= number_format($item['unit_cost'] ?? 0, 0) ?></p>
            </div>
            <div class="detail-item-custom">
                <p class="detail-label-custom"><i class="fas fa-calendar-alt"></i> Expiry Date</p>
                <p class="detail-value-custom <?= $is_expired ? 'text-red' : ($is_expiring_soon ? 'text-amber' : '') ?>">
                    <?php 
                    if (!empty($item['expiry_date']) && $item['expiry_date'] !== '0000-00-00') {
                        echo date('M d, Y', strtotime($item['expiry_date']));
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </p>
            </div>
            <div class="detail-item-custom">
                <p class="detail-label-custom"><i class="fas fa-barcode"></i> Batch Number</p>
                <p class="detail-value-custom"><?= htmlspecialchars($item['batch_number'] ?? 'N/A') ?></p>
            </div>
            <div class="detail-item-custom">
                <p class="detail-label-custom"><i class="fas fa-truck"></i> Supplier</p>
                <p class="detail-value-custom"><?= htmlspecialchars($item['supplier'] ?? 'N/A') ?></p>
            </div>
            <div class="detail-item-custom">
                <p class="detail-label-custom"><i class="fas fa-calendar-plus"></i> Created</p>
                <p class="detail-value-custom"><?= date('M d, Y h:i A', strtotime($item['created_at'] ?? 'now')) ?></p>
            </div>
            <div class="detail-item-custom">
                <p class="detail-label-custom"><i class="fas fa-clock"></i> Last Updated</p>
                <p class="detail-value-custom"><?= date('M d, Y h:i A', strtotime($item['updated_at'] ?? 'now')) ?></p>
            </div>
            <div class="detail-item-custom">
                <p class="detail-label-custom"><i class="fas fa-exchange-alt"></i> Total Movements</p>
                <p class="detail-value-custom"><?= number_format($item['total_movements'] ?? 0) ?></p>
            </div>
            <div class="detail-item-custom">
                <p class="detail-label-custom"><i class="fas fa-shopping-cart"></i> OTC Sales</p>
                <p class="detail-value-custom"><?= number_format($item['total_otc_sales'] ?? 0) ?></p>
            </div>
            <div class="detail-item-custom">
                <p class="detail-label-custom"><i class="fas fa-prescription"></i> Prescriptions</p>
                <p class="detail-value-custom"><?= number_format($item['total_prescriptions'] ?? 0) ?></p>
            </div>
        </div>
    </div>

    <!-- STOCK MOVEMENT HISTORY -->
    <div class="card-custom">
        <div class="card-header-custom">
            <h3 class="card-title-custom">
                <i class="fas fa-history"></i>
                Stock Movement History
            </h3>
            <span style="font-size:0.75rem;color:var(--page-text-secondary);">Last 20 movements</span>
        </div>
        <div style="overflow-x:auto;">
            <?php if (count($movements) > 0) { ?>
                <table class="data-table-custom">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Previous Stock</th>
                            <th>New Stock</th>
                            <th>Performed By</th>
                            <th>Notes</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movements as $movement) { ?>
                            <tr>
                                <td>
                                    <span class="badge-custom <?= ($movement['movement_type'] ?? 'out') === 'in' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= ucfirst($movement['movement_type'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="font-weight:700;color:<?= ($movement['movement_type'] ?? 'out') === 'in' ? '#059669' : '#DC2626' ?>;">
                                    <?= ($movement['movement_type'] ?? 'out') === 'in' ? '+' : '-' ?>
                                    <?= number_format($movement['quantity'] ?? 0) ?>
                                </td>
                                <td><?= number_format($movement['previous_stock'] ?? 0) ?></td>
                                <td><?= number_format($movement['new_stock'] ?? 0) ?></td>
                                <td><?= htmlspecialchars($movement['performed_by_name'] ?? 'System') ?></td>
                                <td style="font-size:0.72rem;"><?= htmlspecialchars(substr($movement['notes'] ?? '', 0, 50)) ?></td>
                                <td style="font-size:0.72rem;"><?= date('M d, Y h:i A', strtotime($movement['created_at'] ?? 'now')) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else { ?>
                <div class="empty-state-custom">
                    <i class="fas fa-history"></i>
                    <h4>No Stock Movements</h4>
                    <p>This item has no stock movement history yet.</p>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- RELATED SALES -->
    <div class="card-custom">
        <div class="card-header-custom">
            <h3 class="card-title-custom">
                <i class="fas fa-receipt"></i>
                Related OTC Sales
            </h3>
            <span style="font-size:0.75rem;color:var(--page-text-secondary);">Last 10 sales</span>
        </div>
        <div style="overflow-x:auto;">
            <?php if (count($related_sales) > 0) { ?>
                <table class="data-table-custom">
                    <thead>
                        <tr>
                            <th>Sale #</th>
                            <th>Customer</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($related_sales as $sale) { ?>
                            <tr>
                                <td style="font-family:monospace;font-size:0.72rem;"><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                <td><?= number_format($sale['quantity'] ?? 0) ?></td>
                                <td>TSh <?= number_format($sale['unit_price'] ?? 0, 0) ?></td>
                                <td style="font-weight:700;">TSh <?= number_format($sale['total_price'] ?? 0, 0) ?></td>
                                <td style="font-size:0.72rem;"><?= ucfirst($sale['payment_method'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-custom badge-<?= getStatusBadge($sale['payment_status'] ?? 'pending') ?>">
                                        <?= ucfirst($sale['payment_status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td style="font-size:0.72rem;"><?= date('M d, Y', strtotime($sale['created_at'] ?? 'now')) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else { ?>
                <div class="empty-state-custom">
                    <i class="fas fa-receipt"></i>
                    <h4>No Related Sales</h4>
                    <p>This item has no OTC sales records yet.</p>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="quick-actions-card">
        <div class="quick-actions-title">
            <i class="fas fa-bolt"></i> Quick Actions
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:12px;">
            <a href="edit_inventory.php?id=<?= $item['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-custom btn-primary-custom">
                <i class="fas fa-edit"></i> Edit Item
            </a>
            <a href="add_stock.php?id=<?= $item['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-custom btn-success-custom">
                <i class="fas fa-plus-circle"></i> Add Stock
            </a>
            <a href="remove_stock.php?id=<?= $item['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-custom btn-danger-custom">
                <i class="fas fa-minus-circle"></i> Remove Stock
            </a>
            <a href="pharmacy_inventory.php?id=<?= $item['branch_id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-custom btn-outline-custom">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // DARK MODE BACKGROUND ENFORCEMENT
    function enforceDarkModeBackground() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var body = document.body;
        var mainContent = document.querySelector('.main-content');
        
        if (isDark) {
            if (body) body.style.background = '#0F172A';
            if (mainContent) mainContent.style.background = '#0F172A';
        } else {
            if (body) body.style.background = '#F0F4F8';
            if (mainContent) mainContent.style.background = '#F0F4F8';
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

    console.log('%c💊 Braick - View Inventory Item', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🌙 FULL DARK MODE inafanya kazi', 'font-size:13px; color:#7C3AED;');
    console.log('%c📦 Item: <?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?> (ID: <?= $item['id'] ?>)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🏥 Branch: <?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?>', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>