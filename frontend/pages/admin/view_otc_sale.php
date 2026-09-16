<?php
// ================================================================
// FILE: frontend/pages/admin/view_otc_sale.php
// SUPER ADMIN - VIEW OTC SALE DETAILS WITH ITEMS
// ✅ Uses SHARED header & sidebar
// ✅ Full dark mode support
// ✅ Blue page header card
// ✅ Print Receipt & Delete buttons removed
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
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// PARAMETERS
$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;

if ($sale_id <= 0) {
    header('Location: otc_sales.php?branch=' . $branch_id . '&error=invalid_id');
    exit;
}

// GET OTC SALE DETAILS
$otc_sale = null;
try {
    $sql = "
        SELECT 
            os.*,
            b.name as branch_name,
            u.full_name as sold_by_name,
            COALESCE((SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = os.id), 0) as total_items
        FROM otc_sales os
        LEFT JOIN branches b ON os.branch_id = b.id
        LEFT JOIN users u ON os.sold_by = u.id
        WHERE os.id = ?
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$sale_id]);
    $otc_sale = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching OTC sale: " . $e->getMessage());
}

if (!$otc_sale) {
    header('Location: otc_sales.php?branch=' . $branch_id . '&error=notfound');
    exit;
}

// GET OTC SALE ITEMS
$sale_items = [];
try {
    $stmt = $db->prepare("
        SELECT 
            osi.*,
            mi.medication_name,
            mi.unit,
            mi.batch_number,
            mi.selling_price as current_price
        FROM otc_sale_items osi
        LEFT JOIN medications_inventory mi ON osi.inventory_id = mi.id
        WHERE osi.sale_id = ?
        ORDER BY osi.id ASC
    ");
    $stmt->execute([$sale_id]);
    $sale_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($sale_items)) {
        $stmt = $db->prepare("
            SELECT 
                id, sale_id, inventory_id, medicine_name, item_name,
                quantity, unit_price, total_price, instructions, created_at
            FROM otc_sale_items 
            WHERE sale_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$sale_id]);
        $sale_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching OTC sale items: " . $e->getMessage());
    $sale_items = [];
}

// UNREAD NOTIFICATIONS
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// BRANCHES FOR FILTER
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// HELPERS
function getStatusBadge($status) {
    $classes = [
        'paid' => 'success',
        'pending' => 'warning',
        'cancelled' => 'danger',
        'partial' => 'warning'
    ];
    return $classes[$status] ?? 'secondary';
}

function format_currency($amount) {
    if ($amount == 0) return 'TSh 0';
    return 'TSh ' . number_format($amount, 0);
}

// INCLUDE SHARED HEADER & SIDEBAR
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS - FULL DARK MODE -->
<!-- ================================================================ -->
<style>
    /* LIGHT MODE */
    :root {
        --page-bg-body: #F1F5F9;
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
    body { background: var(--page-bg-body, #F1F5F9); }
    html[data-theme="dark"] body { background: #0F172A !important; }
    .main-content { background: var(--page-bg-body, #F1F5F9); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; color: #F1F5F9; }

    /* PAGE HEADER */
    .page-header-custom {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083D8A 100%);
        border-radius: 18px;
        padding: 24px 32px;
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
        font-size: 1.6rem;
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
        width: 44px; height: 44px;
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

    .page-header-custom .sale-number-badge {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 700;
        font-family: monospace;
        border: 1px solid rgba(255,255,255,0.2);
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

    .page-header-custom .header-badge.revenue {
        background: rgba(251,191,36,0.25);
        border-color: rgba(251,191,36,0.4);
        color: #FDE68A;
    }

    .page-header-custom .header-badge.paid-badge {
        background: rgba(52,211,153,0.25);
        border-color: rgba(52,211,153,0.4);
        color: #A7F3D0;
    }

    .page-header-custom .header-badge.pending-badge {
        background: rgba(251,191,36,0.25);
        border-color: rgba(251,191,36,0.4);
        color: #FDE68A;
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

    /* DETAIL GRID */
    .detail-grid-custom {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }

    /* DETAIL CARD */
    .detail-card-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--page-border, #E2E8F0);
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        transition: all 0.3s ease;
    }

    html[data-theme="dark"] .detail-card-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .detail-card-custom:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.08);
    }

    .detail-card-custom .detail-title {
        font-size: 0.72rem;
        font-weight: 800;
        color: var(--page-text-secondary, #64748B);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    html[data-theme="dark"] .detail-card-custom .detail-title {
        border-bottom-color: #334155;
    }

    .detail-card-custom .detail-title i {
        color: #0B5ED7;
    }

    html[data-theme="dark"] .detail-card-custom .detail-title i { color: #6EA8FE; }

    .detail-row-custom {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        font-size: 0.85rem;
        gap: 8px;
    }

    html[data-theme="dark"] .detail-row-custom {
        border-bottom-color: #334155;
    }

    .detail-row-custom:last-child {
        border-bottom: none;
    }

    .detail-row-custom .label {
        color: var(--page-text-secondary, #64748B);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .detail-row-custom .label i {
        color: #0B5ED7;
        font-size: 0.8rem;
        width: 16px;
    }

    html[data-theme="dark"] .detail-row-custom .label i { color: #6EA8FE; }

    .detail-row-custom .value {
        color: var(--page-text-primary, #1E293B);
        font-weight: 700;
        text-align: right;
        word-break: break-word;
    }

    html[data-theme="dark"] .detail-row-custom .value { color: #F1F5F9; }

    .detail-row-custom .value.success { color: #059669; }
    .detail-row-custom .value.danger { color: #DC2626; }
    .detail-row-custom .value.warning { color: #D97706; }
    .detail-row-custom .value.primary { color: #0B5ED7; }

    html[data-theme="dark"] .detail-row-custom .value.primary { color: #6EA8FE; }

    /* ITEMS TABLE */
    .items-table-wrap {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        margin-top: 20px;
        margin-bottom: 24px;
    }

    html[data-theme="dark"] .items-table-wrap {
        background: #1E293B;
        border-color: #334155;
    }

    .items-table-wrap .table-header {
        padding: 14px 20px;
        background: linear-gradient(135deg, #0A4CA8, #083D8A);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .items-table-wrap .table-header .title {
        color: white;
        font-size: 0.9rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .items-table-wrap .table-header .count {
        color: rgba(255,255,255,0.85);
        font-size: 0.75rem;
        font-weight: 600;
        background: rgba(255,255,255,0.15);
        padding: 3px 12px;
        border-radius: 20px;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .items-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
    }

    .items-table thead {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    }

    .items-table th {
        padding: 12px 14px;
        text-align: left;
        font-weight: 700;
        color: white;
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
    }

    .items-table td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: middle;
    }

    html[data-theme="dark"] .items-table td {
        color: #F1F5F9;
        border-bottom-color: #334155;
    }

    .items-table tbody tr:nth-child(even) td {
        background: var(--page-hover, #F8FAFC);
    }

    html[data-theme="dark"] .items-table tbody tr:nth-child(even) td {
        background: #1E3A5F;
    }

    .items-table tbody tr:hover td {
        background: #E8F0FE;
    }

    html[data-theme="dark"] .items-table tbody tr:hover td {
        background: #1E40AF;
    }

    .items-table tfoot td {
        border-top: 2px solid var(--page-border, #E2E8F0);
        font-weight: 700;
        padding: 12px 14px;
    }

    html[data-theme="dark"] .items-table tfoot td {
        border-top-color: #334155;
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
        font-size: 3rem;
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

    /* TABLE FOOTER */
    .table-footer-custom {
        padding: 14px 20px;
        background: var(--page-hover, #F8FAFC);
        border-top: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        justify-content: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    html[data-theme="dark"] .table-footer-custom {
        background: #0F172A;
        border-top-color: #334155;
    }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
        .detail-grid-custom { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
        .page-header-custom { padding: 18px; }
        .page-header-custom .page-title { font-size: 1.2rem; }
        .page-header-custom .page-title i { width: 36px; height: 36px; font-size: 1rem; }
        .detail-card-custom { padding: 16px; }
        .items-table { font-size: 0.7rem; }
        .items-table th, .items-table td { padding: 8px 10px; }
        .btn-custom { padding: 8px 16px; font-size: 0.75rem; }
    }

    @media print {
        .page-header-custom { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; }
        .btn-custom, .table-footer-custom { display: none !important; }
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
                <i class="fas fa-shopping-cart"></i>
                OTC Sale Details
                <span class="role-badge-display">ADMIN</span>
                <span class="sale-number-badge">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($otc_sale['sale_number'] ?? 'N/A') ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <strong><?= htmlspecialchars($otc_sale['customer_name'] ?? 'Walk-in Customer') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($otc_sale['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge revenue">
                    <i class="fas fa-money-bill-wave"></i> <?= format_currency($otc_sale['total_amount'] ?? 0) ?>
                </span>
                <?php if (($otc_sale['payment_status'] ?? 'pending') === 'paid') { ?>
                    <span class="header-badge paid-badge">
                        <i class="fas fa-check-circle"></i> Paid
                    </span>
                <?php } else { ?>
                    <span class="header-badge pending-badge">
                        <i class="fas fa-clock"></i> <?= ucfirst($otc_sale['payment_status'] ?? 'Pending') ?>
                    </span>
                <?php } ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="otc_sales.php?branch=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- DETAIL GRID -->
    <div class="detail-grid-custom">
        
        <!-- Sale Information -->
        <div class="detail-card-custom">
            <div class="detail-title">
                <i class="fas fa-info-circle"></i> Sale Information
            </div>
            <div class="detail-row-custom">
                <span class="label"><i class="fas fa-hashtag"></i> Sale Number</span>
                <span class="value primary" style="font-family:monospace;"><?= htmlspecialchars($otc_sale['sale_number'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row-custom">
                <span class="label"><i class="fas fa-user"></i> Customer</span>
                <span class="value"><?= htmlspecialchars($otc_sale['customer_name'] ?? 'Walk-in Customer') ?></span>
            </div>
            <div class="detail-row-custom">
                <span class="label"><i class="fas fa-phone"></i> Phone</span>
                <span class="value"><?= htmlspecialchars($otc_sale['customer_phone'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row-custom">
                <span class="label"><i class="fas fa-store-alt"></i> Branch</span>
                <span class="value"><?= htmlspecialchars($otc_sale['branch_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row-custom">
                <span class="label"><i class="fas fa-user-md"></i> Sold By</span>
                <span class="value"><?= htmlspecialchars($otc_sale['sold_by_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row-custom">
                <span class="label"><i class="fas fa-calendar"></i> Date</span>
                <span class="value"><?= date('F d, Y h:i A', strtotime($otc_sale['created_at'] ?? 'now')) ?></span>
            </div>
        </div>
        
        <!-- Payment Information -->
        <div class="detail-card-custom">
            <div class="detail-title">
                <i class="fas fa-credit-card"></i> Payment Information
            </div>
            <div class="detail-row-custom">
                <span class="label"><i class="fas fa-money-bill-wave"></i> Total Amount</span>
                <span class="value primary" style="font-size:1.05rem;"><?= format_currency($otc_sale['total_amount'] ?? 0) ?></span>
            </div>
            <div class="detail-row-custom">
                <span class="label"><i class="fas fa-percent"></i> Discount</span>
                <span class="value warning">- <?= format_currency($otc_sale['discount_amount'] ?? 0) ?></span>
            </div>
            <div class="detail-row-custom">
                <span class="label"><i class="fas fa-coins"></i> Subtotal</span>
                <span class="value success" style="font-size:1.1rem;"><?= format_currency($otc_sale['subtotal'] ?? 0) ?></span>
            </div>
            <div class="detail-row-custom">
                <span class="label"><i class="fas fa-credit-card"></i> Payment Method</span>
                <span class="value"><?= ucfirst(str_replace('_', ' ', $otc_sale['payment_method'] ?? 'N/A')) ?></span>
            </div>
            <div class="detail-row-custom">
                <span class="label"><i class="fas fa-circle"></i> Status</span>
                <span class="value">
                    <span class="badge-custom badge-<?= getStatusBadge($otc_sale['payment_status'] ?? 'pending') ?>">
                        <?= ucfirst($otc_sale['payment_status'] ?? 'Pending') ?>
                    </span>
                </span>
            </div>
            <?php if (!empty($otc_sale['notes'])) { ?>
            <div class="detail-row-custom">
                <span class="label"><i class="fas fa-sticky-note"></i> Notes</span>
                <span class="value" style="max-width:60%;font-weight:500;font-size:0.78rem;"><?= htmlspecialchars($otc_sale['notes']) ?></span>
            </div>
            <?php } ?>
            <?php if (!empty($otc_sale['bill_id'])) { ?>
            <div class="detail-row-custom">
                <span class="label"><i class="fas fa-file-invoice"></i> Bill ID</span>
                <span class="value primary">#<?= htmlspecialchars($otc_sale['bill_id']) ?></span>
            </div>
            <?php } ?>
        </div>
        
    </div>

    <!-- ITEMS TABLE -->
    <div class="items-table-wrap">
        <div class="table-header">
            <span class="title">
                <i class="fas fa-boxes"></i> Sale Items (<?= count($sale_items) ?>)
            </span>
            <span class="count">
                <i class="fas fa-hashtag"></i> Total Items: <?= number_format($otc_sale['total_items'] ?? 0) ?>
            </span>
        </div>
        
        <?php if (count($sale_items) > 0) { ?>
        <div style="overflow-x:auto;">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:5%;">#</th>
                        <th style="width:30%;">Item Name</th>
                        <th style="width:20%;">Batch / Unit</th>
                        <th style="width:10%;text-align:right;">Qty</th>
                        <th style="width:15%;text-align:right;">Unit Price</th>
                        <th style="width:20%;text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; foreach ($sale_items as $item) { ?>
                    <tr>
                        <td style="font-weight:700;color:#0B5ED7;"><?= $counter++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($item['medication_name'] ?? $item['medicine_name'] ?? $item['item_name'] ?? 'Unknown Item') ?></strong>
                            <?php if (!empty($item['unit'])) { ?>
                                <span style="color:var(--page-text-muted);font-size:0.72rem;"> (<?= htmlspecialchars($item['unit']) ?>)</span>
                            <?php } ?>
                            <?php if (!empty($item['instructions'])) { ?>
                                <div style="font-size:0.7rem;color:var(--page-text-secondary);margin-top:4px;font-style:italic;">
                                    <i class="fas fa-info-circle"></i> <?= htmlspecialchars(substr($item['instructions'], 0, 50)) ?>
                                    <?= strlen($item['instructions'] ?? '') > 50 ? '...' : '' ?>
                                </div>
                            <?php } ?>
                        </td>
                        <td style="font-size:0.75rem;">
                            <?php if (!empty($item['batch_number'])) { ?>
                                <div style="font-family:monospace;">Batch: <?= htmlspecialchars($item['batch_number']) ?></div>
                            <?php } ?>
                            <?php if (!empty($item['unit'])) { ?>
                                <div style="color:var(--page-text-secondary);">Unit: <?= htmlspecialchars($item['unit']) ?></div>
                            <?php } ?>
                            <?php if (empty($item['batch_number']) && empty($item['unit'])) { ?>
                                <span style="color:var(--page-text-muted);">N/A</span>
                            <?php } ?>
                        </td>
                        <td style="text-align:right;font-weight:700;"><?= number_format($item['quantity'] ?? 0) ?></td>
                        <td style="text-align:right;"><?= format_currency($item['unit_price'] ?? 0) ?></td>
                        <td style="text-align:right;font-weight:700;color:#0B5ED7;"><?= format_currency($item['total_price'] ?? 0) ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" style="text-align:right;color:var(--page-text-secondary);">Subtotal:</td>
                        <td style="text-align:right;color:#0B5ED7;"><?= format_currency($otc_sale['subtotal'] ?? 0) ?></td>
                    </tr>
                    <tr>
                        <td colspan="5" style="text-align:right;color:#D97706;">Discount:</td>
                        <td style="text-align:right;color:#D97706;">- <?= format_currency($otc_sale['discount_amount'] ?? 0) ?></td>
                    </tr>
                    <tr style="background:#EFF6FF;">
                        <td colspan="5" style="text-align:right;color:#059669;font-size:1.1rem;">Total Amount:</td>
                        <td style="text-align:right;color:#059669;font-size:1.1rem;"><?= format_currency($otc_sale['total_amount'] ?? 0) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php } else { ?>
        <div class="empty-state-custom">
            <i class="fas fa-box-open"></i>
            <h4>No Items Found</h4>
            <p style="font-size:0.85rem;">No items were found for this OTC sale.</p>
        </div>
        <?php } ?>
        
        <div class="table-footer-custom">
            <?php if (($otc_sale['payment_status'] ?? '') == 'pending') { ?>
                <a href="process_otc_payment.php?id=<?= $otc_sale['id'] ?>&branch=<?= $branch_id ?>" 
                   class="btn-custom btn-success-custom">
                    <i class="fas fa-credit-card"></i> Process Payment
                </a>
            <?php } ?>
            <a href="otc_sales.php?branch=<?= $branch_id ?>" 
               class="btn-custom btn-outline-custom">
                <i class="fas fa-arrow-left"></i> Back to OTC Sales
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

    console.log('%c🛒 Braick - View OTC Sale', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🌙 FULL DARK MODE inafanya kazi', 'font-size:13px; color:#7C3AED;');
    console.log('%c📋 Sale: <?= htmlspecialchars($otc_sale['sale_number'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Customer: <?= htmlspecialchars($otc_sale['customer_name'] ?? 'Walk-in') ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Total: <?= format_currency($otc_sale['total_amount'] ?? 0) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📦 Items: <?= count($sale_items) ?>', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>