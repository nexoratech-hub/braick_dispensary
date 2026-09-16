<?php
// ================================================================
// FILE: frontend/pages/admin/view_equipment.php
// ADMIN - VIEW EQUIPMENT DETAILS
// BRAICK DISPENSARY - BLUE THEME
// ✅ Uses SHARED admin_header.php & admin_sidebar.php
// ✅ Page-specific CSS only (no duplicates)
// ✅ Dark mode via header toggle
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
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
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET PARAMETERS
// ================================================================
$equipment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$return_branch = isset($_GET['branch']) ? $_GET['branch'] : 'all';
$return_tab = isset($_GET['tab']) ? $_GET['tab'] : 'equipment';

// GET UNREAD NOTIFICATIONS
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// GET EQUIPMENT DETAILS
$equipment = null;
$error_message = '';

try {
    $stmt = $db->prepare("
        SELECT e.*, b.name as branch_name
        FROM medical_equipment e
        LEFT JOIN branches b ON e.branch_id = b.id
        WHERE e.id = ?
    ");
    $stmt->execute([$equipment_id]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$equipment) {
        $error_message = "Equipment not found. It may have been deleted.";
    }
} catch (Exception $e) {
    $error_message = "Error loading equipment: " . $e->getMessage();
    $equipment = null;
}

// GET LINKED LAB TESTS
$linked_tests = [];
$linked_count = 0;

if ($equipment) {
    try {
        $stmt = $db->prepare("
            SELECT l.id, l.test_name, l.category, l.price, l.is_active, l.created_at,
                   u.full_name as created_by_name
            FROM lab_test_equipment le
            INNER JOIN lab_tests_catalog l ON le.lab_test_id = l.id
            LEFT JOIN users u ON l.created_by = u.id
            WHERE le.equipment_id = ?
            ORDER BY l.test_name ASC
        ");
        $stmt->execute([$equipment_id]);
        $linked_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $linked_count = count($linked_tests);
    } catch (Exception $e) {
        $linked_tests = [];
        $linked_count = 0;
    }
}

// GET STOCK MOVEMENTS
$stock_movements = [];
if ($equipment) {
    try {
        $stmt = $db->prepare("
            SELECT sm.*, u.full_name as performed_by_name, p.full_name as patient_name
            FROM stock_movements sm
            LEFT JOIN users u ON sm.performed_by = u.id
            LEFT JOIN patients p ON sm.patient_id = p.id
            WHERE sm.equipment_id = ?
            ORDER BY sm.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$equipment_id]);
        $stock_movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $stock_movements = [];
    }
}

// GET BRANCHES
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// HELPER FUNCTIONS
function getStockStatus($quantity, $reorder_level) {
    if ($quantity <= 0) return ['class' => 'out', 'label' => 'Out of Stock', 'icon' => 'fa-times-circle'];
    if ($quantity <= $reorder_level) return ['class' => 'low', 'label' => 'Low Stock', 'icon' => 'fa-exclamation-triangle'];
    return ['class' => 'ok', 'label' => 'In Stock', 'icon' => 'fa-check-circle'];
}

function getExpiryStatus($expiry_date) {
    if (empty($expiry_date) || $expiry_date === '0000-00-00') {
        return ['class' => 'no-expiry', 'label' => 'No Expiry', 'icon' => 'fa-infinity', 'days' => null];
    }
    $days = floor((strtotime($expiry_date) - time()) / 86400);
    if ($days < 0) return ['class' => 'expired', 'label' => 'Expired', 'icon' => 'fa-skull', 'days' => $days];
    if ($days <= 30) return ['class' => 'expiring', 'label' => 'Expiring Soon', 'icon' => 'fa-clock', 'days' => $days];
    return ['class' => 'valid', 'label' => 'Valid', 'icon' => 'fa-check', 'days' => $days];
}

function getStatusBadge($status) {
    return $status === 'active' ? 'active' : 'inactive';
}

function getStatusLabel($status) {
    return $status === 'active' ? 'Active' : 'Inactive';
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
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES
       ================================================================ */
    :root {
        --ve-primary: #0B5ED7;
        --ve-primary-dark: #0A4CA8;
        --ve-primary-light: #3B82F6;
        --ve-primary-bg: #EFF6FF;
        --ve-primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        --ve-primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #073B8A);
        --ve-success: #059669;
        --ve-success-bg: #D1FAE5;
        --ve-danger: #DC2626;
        --ve-danger-bg: #FEE2E2;
        --ve-warning: #D97706;
        --ve-warning-bg: #FEF3C7;
        --ve-purple: #7C3AED;
        --ve-purple-bg: #EDE9FE;
        --ve-teal: #0D9488;
        --ve-teal-bg: #ECFDF5;
        --ve-gray-50: #F8FAFC;
        --ve-gray-100: #F1F5F9;
        --ve-gray-200: #E2E8F0;
        --ve-gray-300: #CBD5E1;
        --ve-gray-400: #94A3B8;
        --ve-gray-500: #64748B;
        --ve-gray-600: #475569;
        --ve-gray-700: #334155;
        --ve-gray-800: #1E293B;
        --ve-gray-900: #0F172A;
        --ve-bg-body: #F0F4F8;
        --ve-bg-card: #FFFFFF;
        --ve-text-primary: #1E293B;
        --ve-text-secondary: #64748B;
        --ve-border-color: #E2E8F0;
        --ve-radius: 12px;
        --ve-radius-lg: 18px;
        --ve-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --ve-shadow: 0 1px 3px rgba(0,0,0,0.08);
        --ve-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --ve-shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    }

    [data-theme="dark"] {
        --ve-bg-body: #0F172A;
        --ve-bg-card: #1E293B;
        --ve-text-primary: #F1F5F9;
        --ve-text-secondary: #94A3B8;
        --ve-border-color: #334155;
        --ve-primary: #3B82F6;
        --ve-primary-bg: #1E3A5F;
        --ve-primary-gradient: linear-gradient(135deg, #2563EB, #1D4ED8);
        --ve-primary-gradient-strong: linear-gradient(135deg, #1D4ED8, #1E40AF);
        --ve-shadow: 0 1px 3px rgba(0,0,0,0.3);
        --ve-shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --ve-shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
    }

    /* ================================================================
       DARK MODE
       ================================================================ */
    html[data-theme="dark"] body {
        background: #0F172A !important;
    }

    html[data-theme="dark"] .main-content {
        background: #0F172A !important;
        color: #F1F5F9;
    }

    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header-ve {
        background: var(--ve-primary-gradient-strong);
        border-radius: var(--ve-radius-lg);
        padding: 28px 36px;
        margin-bottom: 28px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
        position: relative;
        overflow: hidden;
    }

    .page-header-ve::before {
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

    .page-header-ve .page-title-ve {
        color: white;
        font-size: 1.8rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-ve .page-title-ve i { font-size: 2rem; opacity: 0.9; }

    .page-header-ve .page-subtitle-ve {
        color: rgba(255,255,255,0.85);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 6px;
    }

    .page-header-ve .page-subtitle-ve strong { color: white; font-weight: 600; }

    .page-header-ve .role-badge-display-ve {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        backdrop-filter: blur(4px);
    }

    .page-header-ve .header-badge-ve {
        background: rgba(255,255,255,0.12);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .page-header-ve .btn-outline-light-ve {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: var(--ve-radius);
        font-weight: 500;
        font-size: 0.82rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(4px);
        position: relative;
        z-index: 1;
        transition: all 0.3s;
    }

    .page-header-ve .btn-outline-light-ve:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* ================================================================
       CARDS
       ================================================================ */
    .card-ve {
        background: var(--ve-bg-card);
        border-radius: var(--ve-radius-lg);
        padding: 20px 24px;
        border: 2px solid var(--ve-border-color);
        transition: all 0.3s ease;
        box-shadow: var(--ve-shadow-sm);
        margin-bottom: 24px;
    }

    .card-ve:hover {
        border-color: var(--ve-primary);
        box-shadow: var(--ve-shadow-md);
    }

    .card-ve .card-header-ve {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 8px;
    }

    .card-ve .card-title-ve {
        font-size: 1rem;
        font-weight: 600;
        color: var(--ve-text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }

    .card-ve .card-title-ve i { color: var(--ve-primary); }

    /* ================================================================
       DETAIL GRID
       ================================================================ */
    .detail-grid-ve {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 16px;
    }

    .detail-item-ve {
        padding: 12px 16px;
        background: var(--ve-bg-body);
        border-radius: var(--ve-radius);
        border: 1px solid var(--ve-border-color);
    }

    .detail-item-ve .label {
        font-size: 0.65rem;
        color: var(--ve-text-secondary);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .detail-item-ve .value {
        font-size: 1rem;
        font-weight: 600;
        color: var(--ve-text-primary);
        margin-top: 2px;
    }

    /* ================================================================
       BADGES
       ================================================================ */
    .stock-badge-ve {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .stock-badge-ve.ok { background: var(--ve-success-bg); color: var(--ve-success); }
    .stock-badge-ve.low { background: var(--ve-warning-bg); color: var(--ve-warning); }
    .stock-badge-ve.out { background: var(--ve-danger-bg); color: var(--ve-danger); }

    .expiry-badge-ve {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .expiry-badge-ve.valid { background: var(--ve-success-bg); color: var(--ve-success); }
    .expiry-badge-ve.expiring { background: var(--ve-warning-bg); color: var(--ve-warning); }
    .expiry-badge-ve.expired { background: var(--ve-danger-bg); color: var(--ve-danger); }
    .expiry-badge-ve.no-expiry { background: var(--ve-gray-200); color: var(--ve-gray-500); }

    [data-theme="dark"] .expiry-badge-ve.no-expiry { background: #334155; color: #94A3B8; }

    .status-badge-ve {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    .status-badge-ve.active { background: var(--ve-success-bg); color: var(--ve-success); }
    .status-badge-ve.inactive { background: var(--ve-danger-bg); color: var(--ve-danger); }

    [data-theme="dark"] .status-badge-ve.active { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge-ve.inactive { background: #3A1A1A; color: #F87171; }

    /* ================================================================
       TABLE
       ================================================================ */
    .table-container-ve {
        overflow-x: auto;
        border-radius: var(--ve-radius);
        border: 1px solid var(--ve-border-color);
    }

    .table-container-ve table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        min-width: 700px;
    }

    .table-container-ve thead {
        background: var(--ve-primary-gradient-strong);
        color: #ffffff;
    }

    .table-container-ve thead th {
        padding: 10px 14px;
        text-align: left;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
    }

    .table-container-ve thead th i { margin-right: 6px; opacity: 0.8; }

    .table-container-ve tbody tr {
        transition: all 0.3s ease;
        border-bottom: 1px solid var(--ve-border-color);
    }

    .table-container-ve tbody tr:last-child { border-bottom: none; }
    .table-container-ve tbody tr:hover { background: var(--ve-primary-bg); }

    .table-container-ve tbody td {
        padding: 10px 14px;
        vertical-align: middle;
        color: var(--ve-text-primary);
    }

    .batch-number-ve {
        font-family: monospace;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 4px;
        background: var(--ve-primary-bg);
        color: var(--ve-primary);
    }

    [data-theme="dark"] .batch-number-ve {
        background: #1E3A5F;
        color: #6EA8FE;
    }

    .branch-tag-ve {
        display: inline-block;
        background: var(--ve-primary-bg);
        color: var(--ve-primary);
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .branch-tag-ve.all-branches {
        background: #FEF3C7;
        color: #D97706;
    }

    [data-theme="dark"] .branch-tag-ve.all-branches {
        background: #3D2E0A;
        color: #FBBF24;
    }

    .movement-type-ve {
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .movement-type-ve.in { background: var(--ve-success-bg); color: var(--ve-success); }
    .movement-type-ve.out { background: var(--ve-danger-bg); color: var(--ve-danger); }
    .movement-type-ve.adjustment { background: var(--ve-warning-bg); color: var(--ve-warning); }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state-ve {
        text-align: center;
        padding: 30px 20px;
        color: var(--ve-text-secondary);
    }

    .empty-state-ve i {
        font-size: 2.5rem;
        color: var(--ve-gray-300);
        display: block;
        margin-bottom: 10px;
    }

    [data-theme="dark"] .empty-state-ve i { color: var(--ve-gray-600); }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-ve {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 20px;
        border-radius: var(--ve-radius);
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        font-family: inherit;
    }

    .btn-primary-ve {
        background: var(--ve-primary);
        color: white;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
    }
    .btn-primary-ve:hover {
        background: var(--ve-primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        color: white;
    }

    .btn-sm-ve { padding: 4px 12px; font-size: 0.7rem; }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-ve {
        padding: 14px 0;
        border-top: 2px solid var(--ve-border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--ve-text-secondary);
    }

    .footer-ve .footer-brand-ve {
        color: var(--ve-primary);
        font-weight: 700;
    }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up-ve {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .page-header-ve { padding: 16px 18px; }
        .page-header-ve .page-title-ve { font-size: 1.3rem; }
        .detail-grid-ve { grid-template-columns: 1fr; }
        .card-ve { padding: 14px 16px; }
    }

    @media (max-width: 480px) {
        .page-header-ve { flex-direction: column; align-items: flex-start !important; }
    }

    /* ================================================================
       PRINT
       ================================================================ */
    @media print {
        .btn-ve, .btn-outline-light-ve { display: none !important; }
        .card-ve { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
        .table-container-ve { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
        .page-header-ve {
            background: #0B5ED7 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if ($equipment && empty($error_message)): 
        $stock = getStockStatus($equipment['quantity'], $equipment['reorder_level']);
        $expiry = getExpiryStatus($equipment['expiry_date']);
    ?>
        <!-- PAGE HEADER -->
        <div class="page-header-ve animate-fade-in-up-ve">
            <div>
                <h1 class="page-title-ve">
                    <i class="fas fa-tools"></i>
                    <?= htmlspecialchars($equipment['equipment_name']) ?>
                    <span class="role-badge-display-ve">DETAILS</span>
                    <span class="role-badge-display-ve" style="background:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-hashtag"></i> #<?= $equipment['id'] ?>
                    </span>
                </h1>
                <p class="page-subtitle-ve">
                    <i class="fas fa-tag"></i>
                    <strong><?= htmlspecialchars($equipment['category'] ?? 'Uncategorized') ?></strong>
                    <span class="header-badge-ve">
                        <i class="fas fa-boxes"></i> <?= number_format($equipment['quantity']) ?> in stock
                    </span>
                    <span class="header-badge-ve" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                        <i class="fas fa-calendar"></i> <?= $expiry['label'] ?>
                    </span>
                </p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
                <a href="edit_equipment.php?id=<?= $equipment['id'] ?>&branch=<?= urlencode($return_branch) ?>" class="btn-outline-light-ve">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="equipment_inventory.php?branch=<?= urlencode($return_branch) ?>&tab=<?= $return_tab ?>" class="btn-outline-light-ve">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <!-- EQUIPMENT DETAILS -->
        <div class="card-ve animate-fade-in-up-ve" style="animation-delay:0.05s;">
            <div class="card-header-ve">
                <h3 class="card-title-ve">
                    <i class="fas fa-info-circle"></i> Equipment Details
                </h3>
                <div>
                    <span class="status-badge-ve <?= getStatusBadge($equipment['status']) ?>">
                        <?= getStatusLabel($equipment['status']) ?>
                    </span>
                </div>
            </div>
            
            <div class="detail-grid-ve">
                <div class="detail-item-ve">
                    <div class="label"><i class="fas fa-tag"></i> Equipment Name</div>
                    <div class="value"><?= htmlspecialchars($equipment['equipment_name']) ?></div>
                </div>
                
                <div class="detail-item-ve">
                    <div class="label"><i class="fas fa-tag"></i> Category</div>
                    <div class="value"><?= htmlspecialchars($equipment['category'] ?? 'N/A') ?></div>
                </div>
                
                <div class="detail-item-ve">
                    <div class="label"><i class="fas fa-cube"></i> Unit</div>
                    <div class="value"><?= htmlspecialchars($equipment['unit'] ?? 'pcs') ?></div>
                </div>
                
                <div class="detail-item-ve">
                    <div class="label"><i class="fas fa-store-alt"></i> Branch</div>
                    <div class="value">
                        <?php if ($equipment['branch_id'] === null): ?>
                            <span class="branch-tag-ve all-branches">🌐 All Branches</span>
                        <?php else: ?>
                            <span class="branch-tag-ve"><?= htmlspecialchars($equipment['branch_name'] ?? 'N/A') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item-ve">
                    <div class="label"><i class="fas fa-boxes"></i> Quantity</div>
                    <div class="value" style="font-size:1.3rem;">
                        <?= number_format($equipment['quantity']) ?>
                    </div>
                </div>
                
                <div class="detail-item-ve">
                    <div class="label"><i class="fas fa-chart-line"></i> Stock Status</div>
                    <div class="value">
                        <span class="stock-badge-ve <?= $stock['class'] ?>">
                            <i class="fas <?= $stock['icon'] ?>"></i>
                            <?= $stock['label'] ?>
                            <?php if ($stock['class'] === 'low'): ?>
                                (Reorder: <?= $equipment['reorder_level'] ?>)
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
                <div class="detail-item-ve">
                    <div class="label"><i class="fas fa-tag"></i> Price</div>
                    <div class="value">
                        <?= ($equipment['selling_price'] ?? 0) > 0 ? 'TSh ' . number_format($equipment['selling_price'], 0) : 'FREE' ?>
                    </div>
                </div>
                
                <div class="detail-item-ve">
                    <div class="label"><i class="fas fa-truck"></i> Supplier</div>
                    <div class="value"><?= htmlspecialchars($equipment['supplier'] ?? 'N/A') ?></div>
                </div>
                
                <div class="detail-item-ve">
                    <div class="label"><i class="fas fa-barcode"></i> Batch Number</div>
                    <div class="value">
                        <span class="batch-number-ve"><?= htmlspecialchars($equipment['batch_number']) ?></span>
                    </div>
                </div>
                
                <div class="detail-item-ve">
                    <div class="label"><i class="fas fa-calendar"></i> Expiry Date</div>
                    <div class="value">
                        <span class="expiry-badge-ve <?= $expiry['class'] ?>">
                            <i class="fas <?= $expiry['icon'] ?>"></i>
                            <?= $expiry['label'] ?>
                            <?php if ($expiry['days'] !== null && $expiry['class'] !== 'no-expiry'): ?>
                                <?php if ($expiry['days'] < 0): ?>
                                    (<?= abs($expiry['days']) ?> days overdue)
                                <?php else: ?>
                                    (<?= $expiry['days'] ?> days left)
                                <?php endif; ?>
                            <?php endif; ?>
                        </span>
                        <?php if (!empty($equipment['expiry_date']) && $equipment['expiry_date'] !== '0000-00-00'): ?>
                            <br><span style="font-size:0.7rem;color:var(--ve-text-secondary);">
                                <?= date('F d, Y', strtotime($equipment['expiry_date'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item-ve">
                    <div class="label"><i class="fas fa-calendar-plus"></i> Created At</div>
                    <div class="value" style="font-size:0.85rem;">
                        <?= date('F d, Y h:i A', strtotime($equipment['created_at'])) ?>
                    </div>
                </div>
                
                <div class="detail-item-ve">
                    <div class="label"><i class="fas fa-edit"></i> Last Updated</div>
                    <div class="value" style="font-size:0.85rem;">
                        <?= date('F d, Y h:i A', strtotime($equipment['updated_at'])) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- LINKED LAB TESTS -->
        <div class="card-ve animate-fade-in-up-ve" style="animation-delay:0.1s;">
            <div class="card-header-ve">
                <h3 class="card-title-ve">
                    <i class="fas fa-link"></i> Linked Lab Tests
                    <span style="font-size:0.7rem;font-weight:400;color:var(--ve-text-secondary);">
                        (<?= $linked_count ?> linked)
                    </span>
                </h3>
                <a href="services.php?tab=lab_tests&branch=<?= urlencode($return_branch) ?>" class="btn-ve btn-primary-ve btn-sm-ve">
                    <i class="fas fa-plus"></i> Manage Lab Tests
                </a>
            </div>
            
            <?php if ($linked_count > 0): ?>
                <div class="table-container-ve">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Test Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Created By</th>
                                <th>Status</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($linked_tests as $test): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><strong><?= htmlspecialchars($test['test_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($test['category'] ?? 'Uncategorized') ?></td>
                                    <td style="font-weight:600;color:var(--ve-primary);">
                                        TSh <?= number_format($test['price'] ?? 0, 0) ?>
                                    </td>
                                    <td><?= htmlspecialchars($test['created_by_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="status-badge-ve <?= $test['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $test['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.8rem;">
                                        <?= date('M d, Y', strtotime($test['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state-ve">
                    <i class="fas fa-link"></i>
                    <p style="font-size:0.9rem;">This equipment is not linked to any lab test.</p>
                    <a href="services.php?tab=lab_tests&branch=<?= urlencode($return_branch) ?>" class="btn-ve btn-primary-ve btn-sm-ve" style="margin-top:10px;">
                        <i class="fas fa-link"></i> Link to Lab Test
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- STOCK MOVEMENTS -->
        <div class="card-ve animate-fade-in-up-ve" style="animation-delay:0.15s;">
            <div class="card-header-ve">
                <h3 class="card-title-ve">
                    <i class="fas fa-history"></i> Stock Movements
                    <span style="font-size:0.7rem;font-weight:400;color:var(--ve-text-secondary);">
                        (<?= count($stock_movements) ?> movements)
                    </span>
                </h3>
            </div>
            
            <?php if (count($stock_movements) > 0): ?>
                <div class="table-container-ve">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Patient</th>
                                <th>Performed By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($stock_movements as $movement): 
                                $movement_type = $movement['movement_type'] ?? 'adjustment';
                                $type_class = $movement_type === 'in' ? 'in' : ($movement_type === 'out' ? 'out' : 'adjustment');
                                $type_label = $movement_type === 'in' ? 'Stock In' : ($movement_type === 'out' ? 'Stock Out' : 'Adjustment');
                                $type_icon = $movement_type === 'in' ? 'fa-arrow-down' : ($movement_type === 'out' ? 'fa-arrow-up' : 'fa-arrows-alt-h');
                            ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <span class="movement-type-ve <?= $type_class ?>">
                                            <i class="fas <?= $type_icon ?>"></i>
                                            <?= $type_label ?>
                                        </span>
                                    </td>
                                    <td style="font-weight:700;color:<?= $movement_type === 'in' ? 'var(--ve-success)' : ($movement_type === 'out' ? 'var(--ve-danger)' : 'var(--ve-warning)') ?>;">
                                        <?= $movement_type === 'in' ? '+' : ($movement_type === 'out' ? '-' : '') ?>
                                        <?= $movement['quantity'] ?>
                                    </td>
                                    <td><?= number_format($movement['previous_stock']) ?></td>
                                    <td><?= number_format($movement['new_stock']) ?></td>
                                    <td>
                                        <?php if (!empty($movement['patient_name'])): ?>
                                            <?= htmlspecialchars($movement['patient_name']) ?>
                                        <?php else: ?>
                                            <span style="color:var(--ve-text-secondary);font-size:0.7rem;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($movement['performed_by_name'] ?? 'System') ?></td>
                                    <td style="font-size:0.75rem;">
                                        <?= date('M d, Y h:i A', strtotime($movement['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state-ve">
                    <i class="fas fa-history"></i>
                    <p style="font-size:0.9rem;">No stock movements recorded for this equipment.</p>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- EQUIPMENT NOT FOUND -->
        <div class="page-header-ve animate-fade-in-up-ve" style="background:var(--ve-danger);">
            <div>
                <h1 class="page-title-ve">
                    <i class="fas fa-exclamation-triangle"></i>
                    Equipment Not Found
                    <span class="role-badge-display-ve">ERROR</span>
                </h1>
                <p class="page-subtitle-ve">
                    <i class="fas fa-tools"></i>
                    The equipment you are looking for could not be found.
                </p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
                <a href="equipment_inventory.php?branch=<?= urlencode($return_branch) ?>" class="btn-outline-light-ve">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>
        
        <div class="card-ve">
            <div class="empty-state-ve">
                <i class="fas fa-tools"></i>
                <h3 style="font-size:1.2rem;color:var(--ve-text-primary);margin-bottom:8px;">Equipment Not Found</h3>
                <p style="font-size:0.9rem;">The equipment with ID #<?= $equipment_id ?> could not be found in the system.</p>
                <p style="font-size:0.8rem;color:var(--ve-text-secondary);margin-top:4px;">It may have been deleted or the ID may be incorrect.</p>
                <a href="equipment_inventory.php?branch=<?= urlencode($return_branch) ?>" class="btn-ve btn-primary-ve" style="margin-top:16px;">
                    <i class="fas fa-arrow-left"></i> Back to Equipment List
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer class="footer-ve">
        <p>
            <span class="footer-brand-ve">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            View Equipment
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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

    console.log('%c🔧 Braick Dispensary - View Equipment', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    <?php if ($equipment): ?>
        console.log('%c📦 Equipment: <?= htmlspecialchars($equipment['equipment_name']) ?> (ID: <?= $equipment['id'] ?>)', 'font-size:13px; color:#0B5ED7;');
        console.log('%c📊 Quantity: <?= $equipment['quantity'] ?> | Batch: <?= htmlspecialchars($equipment['batch_number']) ?>', 'font-size:13px; color:#D97706;');
        console.log('%c🔗 Linked Lab Tests: <?= $linked_count ?>', 'font-size:13px; color:#7C3AED;');
        console.log('%c📋 Stock Movements: <?= count($stock_movements) ?>', 'font-size:13px; color:#0D9488;');
    <?php else: ?>
        console.log('%c❌ Equipment not found (ID: <?= $equipment_id ?>)', 'font-size:13px; color:#DC2626;');
    <?php endif; ?>
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c✅ No duplicate CSS/JS', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header (shared)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>