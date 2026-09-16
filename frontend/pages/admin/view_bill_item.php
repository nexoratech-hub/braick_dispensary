<?php
// ================================================================
// FILE: frontend/pages/admin/view_bill_item.php
// ADMIN - VIEW BILL ITEM DETAILS WITH ALL ITEMS TABLE
// BRAICK DISPENSARY - GREEN THEME
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
$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch_id'] ?? $_GET['branch'] ?? 'all';

if ($item_id <= 0) {
    header('Location: bills.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH BILL ITEM DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            bi.*,
            b.bill_number, b.patient_id, b.total_amount as bill_total,
            b.status as bill_status, b.created_at as bill_created_at,
            b.paid_amount, b.balance, b.subtotal,
            b.discount_amount as bill_discount_amount,
            b.total_discount, b.cashier_discount, b.pharmacy_discount,
            p.full_name as patient_name, p.patient_id as patient_code, p.phone as patient_phone,
            u.full_name as created_by_name,
            br.name as branch_name
        FROM bill_items bi
        LEFT JOIN bills b ON bi.bill_id = b.id
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN users u ON b.created_by = u.id
        LEFT JOIN branches br ON bi.branch_id = br.id
        WHERE bi.id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        header('Location: bills.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching bill item: " . $e->getMessage());
    header('Location: bills.php?branch=' . urlencode($selected_branch_id) . '&error=database_error');
    exit;
}

// ================================================================
// FETCH ALL ITEMS FOR THIS BILL
// ================================================================
$all_items = [];
try {
    $stmt = $db->prepare("
        SELECT bi.*, b.bill_number
        FROM bill_items bi
        LEFT JOIN bills b ON bi.bill_id = b.id
        WHERE bi.bill_id = ?
        ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$item['bill_id']]);
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching all bill items: " . $e->getMessage());
    $all_items = [];
}

// CALCULATE TOTALS
$subtotal = 0;
$total_items = count($all_items);
foreach ($all_items as $it) {
    $subtotal += ($it['total_price'] ?? 0);
}
$discount_amount = $item['total_discount'] ?? $item['bill_discount_amount'] ?? 0;
$grand_total = $subtotal - $discount_amount;

// GET BRANCHES
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// HELPER FUNCTIONS
function getStatusBadge($status) {
    $classes = [
        'active' => 'success', 'inactive' => 'danger',
        'pending' => 'warning', 'paid' => 'success',
        'partial' => 'warning', 'cancelled' => 'danger',
        'completed' => 'success'
    ];
    return $classes[$status] ?? 'secondary';
}

function getItemTypeLabel($type) {
    $labels = [
        'registration' => 'Registration', 'consultation' => 'Consultation',
        'lab_test' => 'Lab Test', 'medication' => 'Medication',
        'procedure' => 'Procedure', 'equipment' => 'Equipment',
        'tool' => 'Tool/Supply', 'other' => 'Other'
    ];
    return $labels[$type] ?? ucfirst($type);
}

function getItemTypeIcon($type) {
    $icons = [
        'registration' => 'fa-file-medical', 'consultation' => 'fa-stethoscope',
        'lab_test' => 'fa-flask', 'medication' => 'fa-pills',
        'procedure' => 'fa-syringe', 'equipment' => 'fa-tools',
        'tool' => 'fa-tools', 'other' => 'fa-cube'
    ];
    return $icons[$type] ?? 'fa-cube';
}

function getItemTypeColor($type) {
    $colors = [
        'registration' => 'blue', 'consultation' => 'purple',
        'lab_test' => 'orange', 'medication' => 'green',
        'procedure' => 'red', 'equipment' => 'teal',
        'tool' => 'teal', 'other' => 'gray'
    ];
    return $colors[$type] ?? 'gray';
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES - GREEN THEME
       ================================================================ */
    :root {
        --vbi-primary: #059669;
        --vbi-primary-dark: #047857;
        --vbi-primary-light: #34D399;
        --vbi-primary-bg: #D1FAE5;
        --vbi-primary-gradient: linear-gradient(135deg, #059669, #047857);
        --vbi-primary-gradient-strong: linear-gradient(135deg, #047857, #065F46);
        --vbi-success: #059669;
        --vbi-success-bg: #D1FAE5;
        --vbi-danger: #DC2626;
        --vbi-danger-bg: #FEE2E2;
        --vbi-warning: #D97706;
        --vbi-warning-bg: #FEF3C7;
        --vbi-purple: #7C3AED;
        --vbi-purple-bg: #EDE9FE;
        --vbi-teal: #0D9488;
        --vbi-teal-bg: #ECFDF5;
        --vbi-orange: #F59E0B;
        --vbi-orange-bg: #FFFBEB;
        --vbi-blue: #0B5ED7;
        --vbi-gray-50: #F8FAFC;
        --vbi-gray-100: #F1F5F9;
        --vbi-gray-200: #E2E8F0;
        --vbi-gray-300: #CBD5E1;
        --vbi-gray-400: #94A3B8;
        --vbi-gray-500: #64748B;
        --vbi-gray-600: #475569;
        --vbi-gray-700: #334155;
        --vbi-gray-800: #1E293B;
        --vbi-gray-900: #0F172A;
        --vbi-bg-body: #F0FDF4;
        --vbi-bg-card: #FFFFFF;
        --vbi-text-primary: #1E293B;
        --vbi-text-secondary: #64748B;
        --vbi-border-color: #D1FAE5;
        --vbi-radius: 12px;
        --vbi-radius-lg: 18px;
        --vbi-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --vbi-shadow: 0 1px 3px rgba(0,0,0,0.08);
        --vbi-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --vbi-shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        --vbi-table-hover: #ECFDF5;
    }

    [data-theme="dark"] {
        --vbi-bg-body: #0F172A;
        --vbi-bg-card: #1E293B;
        --vbi-text-primary: #F1F5F9;
        --vbi-text-secondary: #94A3B8;
        --vbi-border-color: #334155;
        --vbi-primary: #34D399;
        --vbi-primary-dark: #059669;
        --vbi-primary-light: #6EE7B7;
        --vbi-primary-bg: #1A3A2A;
        --vbi-shadow: 0 1px 3px rgba(0,0,0,0.3);
        --vbi-shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --vbi-shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
        --vbi-table-hover: #1A3A2A;
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
       PAGE HEADER - GREEN THEME
       ================================================================ */
    .page-header-vbi {
        background: var(--vbi-primary-gradient-strong);
        border-radius: var(--vbi-radius-lg);
        padding: 28px 36px;
        margin-bottom: 28px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(4, 120, 87, 0.35);
        position: relative;
        overflow: hidden;
    }

    .page-header-vbi::before {
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

    .page-header-vbi::after {
        content: '';
        position: absolute;
        bottom: -40%;
        left: -5%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.03);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-vbi .page-title-vbi {
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

    .page-header-vbi .page-title-vbi i {
        font-size: 2rem;
        opacity: 0.9;
    }

    .page-header-vbi .page-subtitle-vbi {
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

    .page-header-vbi .page-subtitle-vbi strong {
        color: white;
        font-weight: 600;
    }

    .page-header-vbi .role-badge-display-vbi {
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

    .page-header-vbi .header-badge-vbi {
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

    .page-header-vbi .btn-outline-light-vbi {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: var(--vbi-radius);
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

    .page-header-vbi .btn-outline-light-vbi:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* ================================================================
       DETAIL CARD
       ================================================================ */
    .detail-card-vbi {
        background: var(--vbi-bg-card);
        border-radius: var(--vbi-radius-lg);
        padding: 24px 28px;
        border: 2px solid var(--vbi-border-color);
        transition: all 0.3s ease;
        box-shadow: var(--vbi-shadow-sm);
        margin-bottom: 24px;
    }

    .detail-card-vbi:hover {
        border-color: var(--vbi-primary);
        box-shadow: var(--vbi-shadow-md);
    }

    .detail-label-vbi {
        font-size: 0.7rem;
        color: var(--vbi-text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .detail-value-vbi {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--vbi-text-primary);
    }

    /* ================================================================
       ITEM TYPE BADGE
       ================================================================ */
    .item-type-badge-vbi {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .item-type-badge-vbi.blue { background: #EFF6FF; color: #0B5ED7; }
    .item-type-badge-vbi.purple { background: var(--vbi-purple-bg); color: var(--vbi-purple); }
    .item-type-badge-vbi.orange { background: var(--vbi-orange-bg); color: var(--vbi-orange); }
    .item-type-badge-vbi.green { background: var(--vbi-success-bg); color: var(--vbi-success); }
    .item-type-badge-vbi.red { background: var(--vbi-danger-bg); color: var(--vbi-danger); }
    .item-type-badge-vbi.teal { background: var(--vbi-teal-bg); color: var(--vbi-teal); }
    .item-type-badge-vbi.gray { background: var(--vbi-gray-100); color: var(--vbi-gray-600); }

    [data-theme="dark"] .item-type-badge-vbi.blue { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .item-type-badge-vbi.gray { background: #334155; color: #94A3B8; }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge-vbi {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        color: white;
        letter-spacing: 0.02em;
    }

    .badge-success-vbi { background: #059669; }
    .badge-danger-vbi { background: #DC2626; }
    .badge-warning-vbi { background: #D97706; color: #1E293B; }
    .badge-info-vbi { background: #0B5ED7; }
    .badge-secondary-vbi { background: #64748B; }

    /* ================================================================
       TABLE CONTAINER
       ================================================================ */
    .table-container-vbi {
        background: var(--vbi-bg-card);
        border-radius: var(--vbi-radius-lg);
        border: 2px solid var(--vbi-border-color);
        overflow: hidden;
        box-shadow: var(--vbi-shadow-sm);
        margin-bottom: 24px;
    }

    .table-container-vbi:hover {
        box-shadow: var(--vbi-shadow-md);
    }

    .table-container-vbi .card-header-vbi {
        padding: 14px 20px;
        background: var(--vbi-primary-gradient-strong);
        border-bottom: 2px solid var(--vbi-border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .table-container-vbi .card-header-vbi .card-title-vbi {
        font-size: 0.85rem;
        font-weight: 700;
        color: white;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .table-container-vbi .card-header-vbi .card-title-vbi i {
        color: rgba(255,255,255,0.8);
    }

    /* ================================================================
       DATA TABLE
       ================================================================ */
    .data-table-vbi {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.78rem;
    }

    .data-table-vbi thead th {
        background: var(--vbi-bg-body);
        color: var(--vbi-text-secondary);
        font-weight: 700;
        padding: 10px 14px;
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--vbi-border-color);
        text-align: left;
    }

    [data-theme="dark"] .data-table-vbi thead th {
        background: #0F172A;
    }

    .data-table-vbi td {
        padding: 8px 14px;
        border-bottom: 1px solid var(--vbi-border-color);
        color: var(--vbi-text-primary);
        vertical-align: middle;
    }

    .data-table-vbi tbody tr:hover td {
        background: var(--vbi-table-hover);
    }

    .data-table-vbi tbody tr:last-child td {
        border-bottom: none;
    }

    .data-table-vbi tbody tr:nth-child(even) {
        background: var(--vbi-gray-50);
    }

    [data-theme="dark"] .data-table-vbi tbody tr:nth-child(even) {
        background: #1A3A2A;
    }

    /* TABLE FOOTER */
    .data-table-vbi tfoot {
        background: var(--vbi-primary-bg);
        font-weight: 700;
        border-top: 3px solid var(--vbi-primary);
    }

    .data-table-vbi tfoot td {
        padding: 10px 14px;
        color: var(--vbi-text-primary);
    }

    .data-table-vbi tfoot .total-label-vbi {
        text-align: right;
        color: var(--vbi-text-secondary);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.05em;
    }

    .data-table-vbi tfoot .total-amount-vbi {
        font-family: monospace;
        font-size: 0.95rem;
        font-weight: 700;
    }

    .data-table-vbi tfoot .total-amount-vbi.green { color: var(--vbi-primary); }
    .data-table-vbi tfoot .total-amount-vbi.red { color: var(--vbi-danger); }

    /* ================================================================
       AMOUNT DISPLAY
       ================================================================ */
    .amount-display-vbi {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--vbi-text-primary);
    }

    .amount-display-vbi.green { color: var(--vbi-primary); }
    .amount-display-vbi.red { color: var(--vbi-danger); }
    .amount-display-vbi.blue { color: #0B5ED7; }
    .amount-display-vbi.purple { color: var(--vbi-purple); }

    /* ================================================================
       QUICK ACTIONS
       ================================================================ */
    .quick-action-vbi {
        display: block;
        background: var(--vbi-bg-card);
        border: 2px solid var(--vbi-border-color);
        border-radius: var(--vbi-radius);
        padding: 16px;
        text-align: center;
        transition: all 0.3s ease;
        text-decoration: none;
        color: var(--vbi-text-primary);
    }

    .quick-action-vbi:hover {
        border-color: var(--vbi-primary);
        transform: translateY(-3px);
        box-shadow: var(--vbi-shadow-md);
    }

    .quick-action-vbi .quick-icon-vbi {
        font-size: 2rem;
        display: block;
        margin-bottom: 6px;
    }

    .quick-action-vbi .quick-icon-vbi.green { color: var(--vbi-primary); }
    .quick-action-vbi .quick-icon-vbi.blue { color: #0B5ED7; }
    .quick-action-vbi .quick-icon-vbi.purple { color: var(--vbi-purple); }

    .quick-action-vbi .quick-label-vbi {
        font-size: 0.8rem;
        font-weight: 500;
        color: var(--vbi-text-primary);
    }

    /* ================================================================
       GRID UTILITIES
       ================================================================ */
    .grid-3-vbi {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }

    .grid-5-vbi {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 16px;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-vbi {
        padding: 14px 0;
        border-top: 2px solid var(--vbi-border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--vbi-text-secondary);
    }

    .footer-vbi .footer-brand-vbi {
        color: var(--vbi-primary);
        font-weight: 700;
    }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up-vbi {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .grid-5-vbi { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-vbi { padding: 16px 18px; }
        .page-header-vbi .page-title-vbi { font-size: 1.3rem; }
        .detail-card-vbi { padding: 16px; }
        .data-table-vbi { font-size: 0.65rem; }
        .data-table-vbi thead th, .data-table-vbi td { padding: 6px 8px; }
        .grid-3-vbi { grid-template-columns: 1fr; }
        .grid-5-vbi { grid-template-columns: 1fr; }
    }

    @media (max-width: 480px) {
        .page-header-vbi { flex-direction: column; align-items: flex-start !important; }
        .data-table-vbi { font-size: 0.55rem; }
        .data-table-vbi thead th, .data-table-vbi td { padding: 4px 6px; }
    }

    /* ================================================================
       PRINT
       ================================================================ */
    @media print {
        .btn-outline-light-vbi, .quick-action-vbi { display: none !important; }
        .detail-card-vbi { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
        .table-container-vbi { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
        .page-header-vbi {
            background: #059669 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header-vbi animate-fade-in-up-vbi">
        <div>
            <h1 class="page-title-vbi">
                <i class="fas fa-receipt"></i>
                Bill Item Details
                <span class="role-badge-display-vbi">ADMIN</span>
            </h1>
            <p class="page-subtitle-vbi">
                <i class="fas fa-file-invoice"></i>
                <strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong>
                <span class="header-badge-vbi">
                    <i class="fas fa-hashtag"></i> #<?= $item_id ?>
                </span>
                <span class="header-badge-vbi">
                    <?php if ($item['status'] === 'paid'): ?>
                        <i class="fas fa-check-circle"></i> Paid
                    <?php else: ?>
                        <i class="fas fa-clock"></i> <?= ucfirst($item['status'] ?? 'Pending') ?>
                    <?php endif; ?>
                </span>
                <span class="header-badge-vbi">
                    <i class="fas fa-money-bill-wave"></i>
                    TSh <?= number_format($item['total_price'] ?? 0, 0) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_bill.php?id=<?= $item['bill_id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light-vbi">
                <i class="fas fa-file-invoice"></i> View Bill
            </a>
            <a href="bills.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light-vbi">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILL ITEM DETAILS -->
    <!-- ================================================================ -->
    <div class="detail-card-vbi animate-fade-in-up-vbi" style="animation-delay:0.05s;">
        <!-- Item Type Badge -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;flex-wrap:wrap;gap:16px;">
            <div>
                <span class="item-type-badge-vbi <?= getItemTypeColor($item['item_type'] ?? 'other') ?>">
                    <i class="fas <?= getItemTypeIcon($item['item_type'] ?? 'other') ?>"></i>
                    <?= getItemTypeLabel($item['item_type'] ?? 'other') ?>
                </span>
            </div>
            <div>
                <span class="badge-vbi badge-<?= getStatusBadge($item['status'] ?? 'pending') ?>-vbi">
                    <?php if ($item['status'] === 'paid'): ?>
                        <i class="fas fa-check-circle"></i> Paid
                    <?php else: ?>
                        <i class="fas fa-clock"></i> <?= ucfirst($item['status'] ?? 'Pending') ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;" class="grid-3-vbi">
            <div>
                <p class="detail-label-vbi"><i class="fas fa-tag"></i> Item Name</p>
                <p class="detail-value-vbi"><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label-vbi"><i class="fas fa-cubes"></i> Quantity</p>
                <p class="detail-value-vbi"><?= number_format($item['quantity'] ?? 0) ?></p>
            </div>
            <div>
                <p class="detail-label-vbi"><i class="fas fa-money-bill-wave"></i> Unit Price</p>
                <p class="detail-value-vbi">TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></p>
            </div>
            <div>
                <p class="detail-label-vbi"><i class="fas fa-calculator"></i> Total Price</p>
                <p class="detail-value-vbi amount-display-vbi green">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></p>
            </div>
            <div>
                <p class="detail-label-vbi"><i class="fas fa-file-invoice"></i> Bill Number</p>
                <p class="detail-value-vbi">
                    <a href="view_bill.php?id=<?= $item['bill_id'] ?>&branch=<?= $selected_branch_id ?>" style="color:var(--vbi-primary);text-decoration:none;font-weight:600;">
                        <?= htmlspecialchars($item['bill_number'] ?? 'N/A') ?>
                    </a>
                </p>
            </div>
            <div>
                <p class="detail-label-vbi"><i class="fas fa-user"></i> Patient</p>
                <p class="detail-value-vbi">
                    <a href="view_patient.php?id=<?= $item['patient_id'] ?>&branch=<?= $selected_branch_id ?>" style="color:var(--vbi-primary);text-decoration:none;font-weight:600;">
                        <?= htmlspecialchars($item['patient_name'] ?? 'N/A') ?>
                    </a>
                    <span style="font-size:0.7rem;color:var(--vbi-text-secondary);display:block;"><?= htmlspecialchars($item['patient_code'] ?? '') ?></span>
                </p>
            </div>
            <div>
                <p class="detail-label-vbi"><i class="fas fa-user-plus"></i> Created By</p>
                <p class="detail-value-vbi"><?= htmlspecialchars($item['created_by_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label-vbi"><i class="fas fa-store"></i> Branch</p>
                <p class="detail-value-vbi"><?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label-vbi"><i class="fas fa-clock"></i> Created At</p>
                <p class="detail-value-vbi"><?= date('M d, Y h:i A', strtotime($item['created_at'] ?? 'now')) ?></p>
            </div>
        </div>

        <?php if (!empty($item['description'])): ?>
        <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--vbi-border-color);">
            <p class="detail-label-vbi"><i class="fas fa-align-left"></i> Description</p>
            <p class="detail-value-vbi" style="font-weight:400;font-size:0.85rem;"><?= htmlspecialchars($item['description']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- ALL BILL ITEMS TABLE -->
    <!-- ================================================================ -->
    <div class="table-container-vbi animate-fade-in-up-vbi" style="animation-delay:0.1s;">
        <div class="card-header-vbi">
            <h3 class="card-title-vbi">
                <i class="fas fa-list"></i>
                All Bill Items (<?= $total_items ?>)
            </h3>
            <span style="font-size:0.65rem;color:rgba(255,255,255,0.7);">
                Bill #<?= htmlspecialchars($item['bill_number'] ?? 'N/A') ?>
            </span>
        </div>
        <div style="overflow-x:auto;">
            <?php if (count($all_items) > 0): ?>
                <table class="data-table-vbi">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; foreach ($all_items as $it): ?>
                            <tr>
                                <td style="font-size:0.7rem;color:var(--vbi-text-secondary);"><?= $counter++ ?></td>
                                <td style="font-weight:500;"><?= htmlspecialchars($it['item_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="item-type-badge-vbi <?= getItemTypeColor($it['item_type'] ?? 'other') ?>" style="font-size:0.55rem;padding:2px 10px;">
                                        <i class="fas <?= getItemTypeIcon($it['item_type'] ?? 'other') ?>" style="font-size:0.5rem;"></i>
                                        <?= getItemTypeLabel($it['item_type'] ?? 'other') ?>
                                    </span>
                                </td>
                                <td><?= number_format($it['quantity'] ?? 0) ?></td>
                                <td>TSh <?= number_format($it['unit_price'] ?? 0, 0) ?></td>
                                <td style="font-weight:600;color:var(--vbi-primary);">TSh <?= number_format($it['total_price'] ?? 0, 0) ?></td>
                                <td>
                                    <span class="badge-vbi badge-<?= getStatusBadge($it['status'] ?? 'pending') ?>-vbi" style="font-size:0.5rem;padding:1px 8px;">
                                        <?= ucfirst($it['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_bill_item.php?id=<?= $it['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                       style="color:var(--vbi-primary);font-size:0.7rem;text-decoration:none;font-weight:<?= $it['id'] == $item_id ? '800' : '600' ?>;">
                                        <?= $it['id'] == $item_id ? '📌 View' : 'View' ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="total-label-vbi">Subtotal:</td>
                            <td class="total-amount-vbi green">TSh <?= number_format($subtotal, 0) ?></td>
                            <td colspan="2"></td>
                        </tr>
                        <?php if ($discount_amount > 0): ?>
                        <tr style="background:var(--vbi-warning-bg);">
                            <td colspan="5" class="total-label-vbi">Discount:</td>
                            <td class="total-amount-vbi red">- TSh <?= number_format($discount_amount, 0) ?></td>
                            <td colspan="2"></td>
                        </tr>
                        <?php endif; ?>
                        <tr style="background:var(--vbi-success-bg);font-size:1rem;">
                            <td colspan="5" class="total-label-vbi" style="font-weight:700;">Grand Total:</td>
                            <td class="total-amount-vbi green" style="font-size:1.1rem;">TSh <?= number_format($grand_total, 0) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else: ?>
                <div style="text-align:center;padding:40px 20px;color:var(--vbi-text-secondary);">
                    <i class="fas fa-file-invoice" style="font-size:2rem;color:var(--vbi-border-color);margin-bottom:12px;display:block;"></i>
                    <p>No items found for this bill</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILL SUMMARY -->
    <!-- ================================================================ -->
    <div class="detail-card-vbi animate-fade-in-up-vbi" style="animation-delay:0.15s;">
        <h3 style="font-size:0.9rem;font-weight:700;color:var(--vbi-primary);margin-bottom:16px;">
            <i class="fas fa-file-invoice"></i> Bill Summary
        </h3>
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:16px;" class="grid-5-vbi">
            <div>
                <p class="detail-label-vbi">Subtotal</p>
                <p class="detail-value-vbi amount-display-vbi green">TSh <?= number_format($subtotal, 0) ?></p>
            </div>
            <div>
                <p class="detail-label-vbi">Discount</p>
                <p class="detail-value-vbi amount-display-vbi red">- TSh <?= number_format($discount_amount, 0) ?></p>
                <?php if (!empty($item['discount_percent'])): ?>
                    <span style="font-size:0.7rem;color:var(--vbi-text-secondary);">(<?= $item['discount_percent'] ?>%)</span>
                <?php endif; ?>
            </div>
            <div>
                <p class="detail-label-vbi">Grand Total</p>
                <p class="detail-value-vbi amount-display-vbi green">TSh <?= number_format($grand_total, 0) ?></p>
            </div>
            <div>
                <p class="detail-label-vbi">Paid Amount</p>
                <p class="detail-value-vbi amount-display-vbi green">TSh <?= number_format($item['paid_amount'] ?? 0, 0) ?></p>
            </div>
            <div>
                <p class="detail-label-vbi">Balance</p>
                <p class="detail-value-vbi amount-display-vbi <?= ($item['balance'] ?? 0) > 0 ? 'red' : 'green' ?>">
                    TSh <?= number_format($item['balance'] ?? 0, 0) ?>
                </p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;" class="grid-3-vbi animate-fade-in-up-vbi" style="animation-delay:0.2s;">
        <?php if ($item['status'] !== 'paid' && $item['status'] !== 'cancelled'): ?>
        <a href="add_payment.php?bill_item_id=<?= $item_id ?>&bill_id=<?= $item['bill_id'] ?>&branch=<?= $selected_branch_id ?>" 
           class="quick-action-vbi">
            <span class="quick-icon-vbi green"><i class="fas fa-money-bill-wave"></i></span>
            <span class="quick-label-vbi">Record Payment</span>
        </a>
        <?php endif; ?>
        
        <a href="edit_bill_item.php?id=<?= $item_id ?>&branch=<?= $selected_branch_id ?>" 
           class="quick-action-vbi">
            <span class="quick-icon-vbi blue"><i class="fas fa-edit"></i></span>
            <span class="quick-label-vbi">Edit Item</span>
        </a>
        
        <a href="view_bill.php?id=<?= $item['bill_id'] ?>&branch=<?= $selected_branch_id ?>" 
           class="quick-action-vbi">
            <span class="quick-icon-vbi purple"><i class="fas fa-file-invoice"></i></span>
            <span class="quick-label-vbi">View Full Bill</span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-vbi">
        <p>
            <span class="footer-brand-vbi">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Bill Item Details - <?= htmlspecialchars($item['item_name'] ?? 'Item') ?>
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

    console.log('%c📄 Braick Dispensary - View Bill Item (GREEN THEME)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Item: <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?> (ID: <?= $item_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c✅ No duplicate CSS/JS', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header (shared)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>