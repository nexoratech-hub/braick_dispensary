<?php
// ================================================================
// FILE: frontend/pages/admin/bill_details.php
// SUPER ADMIN - BILL DETAILS WITH PREMIUM
// ✅ Uses SHARED header & sidebar
// ✅ Beautiful blue page header card
// ✅ Financial summary cards with Premium
// ✅ Full dark mode support
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
        case 'audit': header('Location: ../audit/dashboard.php'); break;
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

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($bill_id <= 0) {
    header('Location: bills.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active'");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// FETCH BILL DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        b.*,
        b.premium_amount,
        b.premium_note,
        p.full_name as patient_name,
        p.patient_id as patient_id_number,
        p.phone as patient_phone,
        p.gender as patient_gender,
        p.date_of_birth as patient_dob,
        u.full_name as created_by_name,
        u.username as created_by_username,
        br.name as branch_name,
        br.location as branch_location,
        (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id) as total_items,
        (SELECT COUNT(*) FROM payments WHERE bill_id = b.id) as total_payments
    FROM bills b
    LEFT JOIN patients p ON b.patient_id = p.id
    LEFT JOIN users u ON b.created_by = u.id
    LEFT JOIN branches br ON b.branch_id = br.id
    WHERE b.id = ?
");
$stmt->execute([$bill_id]);
$bill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bill) {
    header('Location: bills.php?branch=' . $selected_branch_id . '&error=notfound');
    exit;
}

// ================================================================
// FETCH BILL ITEMS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        id, item_type, item_name, description, quantity,
        unit_price, total_price, discount_amount, tax_amount,
        final_price, status, created_at
    FROM bill_items
    WHERE bill_id = ?
    ORDER BY created_at ASC
");
$stmt->execute([$bill_id]);
$bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// FETCH PAYMENTS
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, u.full_name as received_by_name
    FROM payments p
    LEFT JOIN users u ON p.received_by = u.id
    WHERE p.bill_id = ?
    ORDER BY p.received_at DESC
");
$stmt->execute([$bill_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// FETCH VISIT
// ================================================================
$visit = null;
if (!empty($bill['visit_id'])) {
    $stmt = $db->prepare("
        SELECT v.*, d.full_name as doctor_name, r.full_name as receptionist_name
        FROM visits v
        LEFT JOIN users d ON v.doctor_id = d.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        WHERE v.id = ?
    ");
    $stmt->execute([$bill['visit_id']]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ================================================================
// CALCULATE TOTALS
// ================================================================
$total_items_amount = 0;
foreach ($bill_items as $item) {
    $total_items_amount += $item['total_price'];
}

$subtotal = (float)($bill['subtotal'] ?? $total_items_amount);
$total_paid = (float)($bill['paid_amount'] ?? 0);
$balance = (float)($bill['balance'] ?? 0);
$total_amount = (float)($bill['total_amount'] ?? 0);
$discount_amount = (float)($bill['discount_amount'] ?? 0);
$discount_percent = (float)($bill['discount_percent'] ?? 0);
$pharmacy_discount = (float)($bill['pharmacy_discount'] ?? 0);
$cashier_discount = (float)($bill['cashier_discount'] ?? 0);
$total_discount = (float)($bill['total_discount'] ?? 0);
$premium_amount = (float)($bill['premium_amount'] ?? 0);
$premium_note = $bill['premium_note'] ?? '';

function getStatusBadge($status) {
    $classes = ['paid' => 'success', 'pending' => 'warning', 'partial' => 'info', 'cancelled' => 'danger'];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = ['paid' => 'fa-check-circle', 'pending' => 'fa-clock', 'partial' => 'fa-hourglass-half', 'cancelled' => 'fa-times-circle'];
    return $icons[$status] ?? 'fa-circle';
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================
     PAGE-SPECIFIC CSS
     ================================================================ -->
<style>
    :root {
        --page-primary: #0B5ED7;
        --page-primary-dark: #0A4CA8;
        --page-primary-bg: #E8F0FE;
        --page-success: #059669;
        --page-success-bg: #D1FAE5;
        --page-danger: #DC2626;
        --page-danger-bg: #FEE2E2;
        --page-warning: #D97706;
        --page-warning-bg: #FEF3C7;
        --page-purple: #7B2FBE;
        --page-purple-bg: #F5F3FF;
        --page-gold: #D97706;
        --page-gold-bg: #FEF3C7;
        --page-bg-body: #F1F5F9;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #0F172A;
        --page-text-secondary: #64748B;
        --page-border: #E2E8F0;
        --page-table-hover: #F8FAFC;
        --page-shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
        --page-shadow-md: 0 4px 20px rgba(0,0,0,0.08);
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-border: #334155;
        --page-table-hover: #1E293B;
        --page-shadow-sm: 0 2px 8px rgba(0,0,0,0.3);
        --page-shadow-md: 0 4px 20px rgba(0,0,0,0.4);
        --page-primary-bg: #1E3A5F;
        --page-success-bg: #1A3A2A;
        --page-danger-bg: #3A1A1A;
        --page-warning-bg: #3D2E0A;
        --page-purple-bg: #2D1B4E;
        --page-gold-bg: #3D2E0A;
    }

    /* BODY & MAIN CONTENT - DARK MODE */
    body { background: var(--page-bg-body); }
    html[data-theme="dark"] body { background: #0F172A !important; }

    .main-content { background: var(--page-bg-body); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; }

    /* ================================================================
       BLUE PAGE HEADER CARD
       ================================================================ */
    .page-header-card {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4FB0 50%, #083D8A 100%);
        border-radius: 20px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        color: white;
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.3), 0 4px 12px rgba(11, 94, 215, 0.2);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .page-header-card.premium-mode {
        background: linear-gradient(135deg, #D97706 0%, #B45309 50%, #92400E 100%);
        box-shadow: 0 8px 32px rgba(217, 119, 6, 0.3), 0 4px 12px rgba(217, 119, 6, 0.2);
    }

    .page-header-card::before {
        content: '';
        position: absolute;
        top: -50%; right: -10%;
        width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-title {
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0 0 8px 0;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        color: white;
        position: relative;
        z-index: 2;
    }

    .page-header-title i {
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

    .premium-header-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: rgba(255,255,255,0.25);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.05em;
        border: 1.5px solid rgba(255,255,255,0.3);
        animation: pulse 2s infinite;
        backdrop-filter: blur(10px);
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.85; transform: scale(1.02); }
    }

    .page-header-subtitle {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.9);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .page-header-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
        backdrop-filter: blur(10px);
    }

    .page-header-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .btn-outline-light {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.3);
        border-radius: 12px;
        color: white;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        white-space: nowrap;
        cursor: pointer;
    }

    .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    /* ================================================================
       BILL SUMMARY CARD
       ================================================================ */
    .bill-summary-card {
        background: var(--page-bg-card);
        border-radius: 16px;
        border: 1px solid var(--page-border);
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm);
        margin-bottom: 20px;
    }

    .bill-summary-card:hover {
        box-shadow: var(--page-shadow-md);
        border-color: var(--page-primary);
    }

    .bill-summary-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 18px 24px;
        background: var(--page-bg-body);
        border-bottom: 1px solid var(--page-border);
        flex-wrap: wrap;
        gap: 12px;
    }

    html[data-theme="dark"] .bill-summary-header { background: #0F172A; }

    .bill-number .label {
        font-size: 0.65rem;
        color: var(--page-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: block;
        font-weight: 700;
    }

    .bill-number .value {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--page-text-primary);
        font-family: 'Courier New', monospace;
        letter-spacing: 0.5px;
    }

    .bill-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        padding: 24px;
    }

    .summary-item .label {
        font-size: 0.65rem;
        color: var(--page-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: block;
        font-weight: 700;
    }

    .summary-item .value {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--page-text-primary);
        display: block;
        margin: 4px 0;
    }

    .summary-item .sub {
        font-size: 0.72rem;
        color: var(--page-text-secondary);
    }

    /* ================================================================
       FINANCIAL SUMMARY CARDS
       ================================================================ */
    .financial-summary-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }

    .financial-card {
        background: var(--page-bg-card);
        border-radius: 14px;
        padding: 18px 20px;
        border: 1.5px solid var(--page-border);
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm);
        position: relative;
        overflow: hidden;
    }

    .financial-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
    }

    .financial-card:nth-child(1)::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6); }
    .financial-card:nth-child(2)::before { background: linear-gradient(90deg, #7B2FBE, #A78BFA); }
    .financial-card:nth-child(3)::before { background: linear-gradient(90deg, #D97706, #F59E0B); }
    .financial-card:nth-child(4)::before { background: linear-gradient(90deg, #059669, #34D399); }
    .financial-card:nth-child(5)::before { background: linear-gradient(90deg, #DC2626, #F87171); }

    .financial-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--page-shadow-md);
        border-color: var(--page-primary);
    }

    .financial-card.premium-card {
        border-color: var(--page-gold);
        background: linear-gradient(135deg, var(--page-bg-card), var(--page-gold-bg));
    }

    .financial-card.premium-card:hover {
        box-shadow: 0 6px 24px rgba(217, 119, 6, 0.25);
        border-color: #F59E0B;
    }

    .financial-icon {
        width: 48px; height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
        transition: all 0.3s ease;
    }

    .financial-icon.blue { background: #EFF6FF; color: #0B5ED7; }
    .financial-icon.green { background: #ECFDF5; color: #059669; }
    .financial-icon.orange { background: #FFFBEB; color: #F59E0B; }
    .financial-icon.purple { background: #F5F3FF; color: #7B2FBE; }
    .financial-icon.gold { background: var(--page-gold-bg); color: var(--page-gold); }
    .financial-icon.red { background: #FEF2F2; color: #DC2626; }

    html[data-theme="dark"] .financial-icon.blue { background: #1E3A5F; color: #6EA8FE; }
    html[data-theme="dark"] .financial-icon.green { background: #1A3A2A; color: #34D399; }
    html[data-theme="dark"] .financial-icon.orange { background: #3D2E0A; color: #FBBF24; }
    html[data-theme="dark"] .financial-icon.purple { background: #2D1B4E; color: #A78BFA; }
    html[data-theme="dark"] .financial-icon.gold { background: #3D2E0A; color: #FCD34D; }
    html[data-theme="dark"] .financial-icon.red { background: #3A1A1A; color: #F87171; }

    .financial-card:hover .financial-icon { transform: scale(1.08); }

    .financial-label {
        font-size: 0.65rem;
        color: var(--page-text-secondary);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0;
    }

    .financial-value {
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--page-text-primary);
        margin: 4px 0 0 0;
        line-height: 1.2;
    }

    .financial-sub {
        font-size: 0.6rem;
        color: var(--page-text-secondary);
        margin: 4px 0 0 0;
        font-weight: 500;
    }

    /* ================================================================
       PREMIUM DETAILS CARD
       ================================================================ */
    .premium-details-card {
        background: var(--page-bg-card);
        border-radius: 16px;
        border: 2px solid var(--page-gold);
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 4px 20px rgba(217, 119, 6, 0.1);
        margin-bottom: 20px;
    }

    .premium-details-card:hover {
        box-shadow: 0 6px 30px rgba(217, 119, 6, 0.2);
        transform: translateY(-2px);
    }

    .premium-details-header {
        background: linear-gradient(135deg, #F59E0B, #D97706);
        color: white;
        padding: 14px 24px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        font-size: 0.95rem;
        flex-wrap: wrap;
    }

    .premium-details-header i { font-size: 1.2rem; }

    .premium-amount-badge {
        margin-left: auto;
        background: rgba(255,255,255,0.25);
        padding: 4px 16px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 800;
        border: 1px solid rgba(255,255,255,0.3);
    }

    .premium-details-body {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        padding: 20px 24px;
    }

    .premium-detail-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .premium-detail-item.full { grid-column: 1 / -1; }

    .premium-detail-item .label {
        font-size: 0.65rem;
        color: var(--page-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
    }

    .premium-detail-item .value {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--page-text-primary);
    }

    .premium-detail-item .value.gold {
        color: var(--page-gold);
        font-size: 1.1rem;
        font-weight: 800;
    }

    /* ================================================================
       CARDS
       ================================================================ */
    .card {
        background: var(--page-bg-card);
        border-radius: 16px;
        border: 1px solid var(--page-border);
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm);
    }

    .card:hover { box-shadow: var(--page-shadow-md); }

    .card-header {
        padding: 16px 24px;
        background: var(--page-bg-body);
        border-bottom: 1px solid var(--page-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    html[data-theme="dark"] .card-header { background: #0F172A; }

    .card-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--page-text-primary);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .title-blue { color: #0B5ED7; }
    .title-green { color: #059669; }
    .title-purple { color: #7B2FBE; }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        color: white;
        letter-spacing: 0.02em;
        transition: all 0.2s ease;
    }

    .badge-success { background: #059669; }
    .badge-warning { background: #F59E0B; color: #1E293B; }
    .badge-info { background: #0B5ED7; }
    .badge-danger { background: #EF4444; }
    .badge-secondary { background: #64748B; }

    html[data-theme="dark"] .badge-warning { color: #1E293B; }

    .bill-status .badge { font-size: 0.8rem; padding: 6px 18px; }

    /* ================================================================
       ITEM TYPE BADGES
       ================================================================ */
    .item-type-badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .item-consultation { background: #E8F0FE; color: #0B5ED7; }
    .item-lab_test { background: #EDE9FE; color: #7B2FBE; }
    .item-medication { background: #D1FAE5; color: #059669; }
    .item-procedure { background: #FEF3C7; color: #D97706; }
    .item-equipment { background: #FCE4EC; color: #DC2626; }
    .item-tool { background: #FCE4EC; color: #DC2626; }
    .item-other { background: #F1F5F9; color: #64748B; }
    .item-registration { background: #CCFBF1; color: #0D9488; }

    html[data-theme="dark"] .item-consultation { background: #1E3A5F; color: #6EA8FE; }
    html[data-theme="dark"] .item-lab_test { background: #2D1B4E; color: #A78BFA; }
    html[data-theme="dark"] .item-medication { background: #1A3A2A; color: #34D399; }
    html[data-theme="dark"] .item-procedure { background: #3D2E0A; color: #FBBF24; }
    html[data-theme="dark"] .item-equipment { background: #3A1A1A; color: #F87171; }
    html[data-theme="dark"] .item-tool { background: #3A1A1A; color: #F87171; }
    html[data-theme="dark"] .item-other { background: #334155; color: #94A3B8; }
    html[data-theme="dark"] .item-registration { background: #0D2E2A; color: #2DD4BF; }

    /* ================================================================
       DATA TABLE
       ================================================================ */
    .data-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.8rem;
    }

    .data-table thead th {
        background: #0B5ED7 !important;
        color: white !important;
        font-weight: 700;
        padding: 12px 16px;
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: none !important;
        white-space: nowrap;
        text-align: left;
    }

    .data-table thead th:first-child { border-radius: 8px 0 0 0; }
    .data-table thead th:last-child { border-radius: 0 8px 0 0; }

    .data-table td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--page-border);
        color: var(--page-text-primary);
        vertical-align: middle;
        transition: background 0.2s ease;
    }

    .data-table tbody tr { transition: background 0.2s ease; }
    .data-table tbody tr:hover td { background: var(--page-table-hover); }
    .data-table tbody tr:last-child td { border-bottom: none; }

    .data-table tfoot td {
        padding: 12px 16px;
        border-top: 2px solid var(--page-border);
        font-weight: 600;
        color: var(--page-text-primary);
    }

    .data-table .total-row td {
        border-top: 3px solid #0B5ED7;
        font-size: 1rem;
        background: var(--page-bg-body);
        font-weight: 700;
    }

    html[data-theme="dark"] .data-table .total-row td { background: #0F172A; }

    .data-table .text-right { text-align: right; }
    .data-table .text-center { text-align: center; }
    .data-table .font-semibold { font-weight: 600; }
    .data-table .font-bold { font-weight: 700; }
    .data-table .font-medium { font-weight: 500; }
    .data-table .text-lg { font-size: 1.05rem; }
    .data-table .text-xs { font-size: 0.65rem; }
    .data-table .text-gray-400 { color: var(--page-text-secondary); }

    /* ================================================================
       PAYMENT ITEMS
       ================================================================ */
    .payment-item {
        padding: 14px 18px;
        background: var(--page-bg-body);
        border-radius: 10px;
        border: 1px solid var(--page-border);
        transition: all 0.3s ease;
    }

    .payment-item:hover {
        border-color: var(--page-primary);
        transform: translateX(4px);
        box-shadow: var(--page-shadow-sm);
    }

    .payment-item .payment-amount {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 6px;
        flex-wrap: wrap;
    }

    .payment-item .amount {
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--page-text-primary);
    }

    .payment-item .payment-details {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        font-size: 0.75rem;
        color: var(--page-text-secondary);
    }

    .payment-item .payment-details .receipt {
        font-family: 'Courier New', monospace;
        font-weight: 600;
        color: var(--page-text-primary);
    }

    .payment-item .payment-reference {
        font-size: 0.75rem;
        color: var(--page-text-secondary);
        margin-top: 6px;
        padding: 2px 10px;
        background: var(--page-bg-card);
        border-radius: 4px;
        display: inline-block;
        border: 1px solid var(--page-border);
        font-family: 'Courier New', monospace;
    }

    .payment-item .payment-notes {
        font-size: 0.75rem;
        color: var(--page-text-secondary);
        margin-top: 6px;
        font-style: italic;
        padding: 4px 10px;
        background: var(--page-bg-card);
        border-radius: 4px;
        border-left: 3px solid var(--page-primary);
    }

    .space-y-3 > * + * { margin-top: 12px; }

    /* ================================================================
       RELATED ITEMS
       ================================================================ */
    .related-item {
        padding: 14px 18px;
        background: var(--page-bg-body);
        border-radius: 10px;
        border: 1px solid var(--page-border);
        transition: all 0.3s ease;
    }

    .related-item:hover {
        border-color: var(--page-primary);
        box-shadow: var(--page-shadow-sm);
    }

    .related-item h4 {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--page-text-primary);
        margin: 0 0 12px 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .related-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4px 20px;
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid var(--page-border);
    }

    .detail-row:last-child { border-bottom: none; }
    .detail-row.full { grid-column: 1 / -1; }

    .detail-row .label {
        font-size: 0.7rem;
        color: var(--page-text-secondary);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .detail-row .value {
        font-size: 0.8rem;
        color: var(--page-text-primary);
        font-weight: 500;
        text-align: right;
        word-break: break-word;
        max-width: 60%;
    }

    .m-4 { margin: 16px; }

    /* ================================================================
       GRID
       ================================================================ */
    .grid { display: grid; gap: 20px; }
    .grid-cols-1 { grid-template-columns: 1fr; }

    @media (min-width: 1024px) {
        .lg\:grid-cols-2 { grid-template-columns: 1fr 1fr; }
    }

    .flex { display: flex; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .gap-2 { gap: 8px; }
    .gap-3 { gap: 12px; }
    .mb-5 { margin-bottom: 20px; }
    .ml-2 { margin-left: 8px; }
    .mr-2 { margin-right: 8px; }
    .p-4 { padding: 16px; }
    .py-5 { padding-top: 20px; padding-bottom: 20px; }
    .text-center { text-align: center; }
    .text-sm { font-size: 0.8rem; }
    .text-2xl { font-size: 1.5rem; }
    .text-gray-400 { color: var(--page-text-secondary); }
    .block { display: block; }
    .overflow-x-auto { overflow-x: auto; }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1200px) {
        .financial-summary-grid { grid-template-columns: repeat(3, 1fr); }
    }

    @media (max-width: 1024px) {
        .page-header-card { padding: 20px; }
        .bill-summary-grid { grid-template-columns: 1fr 1fr; }
        .financial-summary-grid { grid-template-columns: repeat(2, 1fr); }
        .related-details { grid-template-columns: 1fr; }
        .detail-row { flex-direction: column; align-items: flex-start; gap: 2px; }
        .detail-row .value { text-align: left; max-width: 100%; }
    }

    @media (max-width: 768px) {
        .page-header-card { padding: 18px 20px; }
        .page-header-title { font-size: 1.2rem; }
        .page-header-title i { width: 36px; height: 36px; font-size: 1rem; }
        .bill-summary-header { flex-direction: column; gap: 10px; text-align: center; }
        .bill-summary-grid { grid-template-columns: 1fr 1fr; padding: 16px; }
        .financial-summary-grid { grid-template-columns: 1fr 1fr; }
        .data-table { font-size: 0.7rem; }
        .data-table td, .data-table th { padding: 8px 10px; }
        .data-table thead th { font-size: 0.55rem; padding: 8px 10px; }
        .financial-card { padding: 14px 16px; }
        .financial-value { font-size: 1rem; }
    }

    @media (max-width: 480px) {
        .bill-summary-grid { grid-template-columns: 1fr; padding: 12px; }
        .financial-summary-grid { grid-template-columns: 1fr; }
        .bill-summary-header { padding: 12px 16px; }
        .card-header { padding: 12px 16px; }
        .data-table td, .data-table th { padding: 6px 8px; font-size: 0.6rem; }
        .data-table thead th { font-size: 0.5rem; padding: 6px 8px; }
        .payment-item { padding: 10px 14px; }
        .payment-item .payment-details { flex-direction: column; gap: 4px; }
    }

    /* Print */
    @media print {
        .page-header-actions, .btn, .footer { display: none !important; }
        .main-content { padding: 0 !important; background: white !important; }
        .card, .bill-summary-card, .premium-details-card { box-shadow: none !important; border: 1px solid #ddd !important; break-inside: avoid; }
        .page-header-card { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .data-table thead th { background: #0B5ED7 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .premium-details-header, .premium-header-badge, .financial-icon, .item-type-badge, .badge { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Blue Page Header Card -->
    <div class="page-header-card <?= $premium_amount > 0 ? 'premium-mode' : '' ?>">
        <div>
            <h1 class="page-header-title">
                <i class="fas <?= $premium_amount > 0 ? 'fa-crown' : 'fa-file-invoice' ?>"></i>
                Bill Details
                <?php if ($premium_amount > 0): ?>
                    <span class="premium-header-badge">
                        <i class="fas fa-crown"></i> PREMIUM
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-header-subtitle">
                <i class="fas fa-hashtag"></i>
                <strong><?= htmlspecialchars($bill['bill_number']) ?></strong>
                <span class="page-header-badge">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>
                </span>
                <span class="page-header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?>
                </span>
                <?php if ($premium_amount > 0): ?>
                    <span class="page-header-badge" style="background:rgba(255,255,255,0.25);">
                        <i class="fas fa-crown"></i> Premium: TSh <?= number_format($premium_amount, 0) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="page-header-actions">
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="bills.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Bills
            </a>
        </div>
    </div>

    <!-- ================================================================
         FINANCIAL SUMMARY CARDS
         ================================================================ -->
    <div class="financial-summary-grid">
        <!-- Card 1: Total Amount -->
        <div class="financial-card">
            <div class="financial-icon blue">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div>
                <p class="financial-label">Total Amount</p>
                <p class="financial-value">TSh <?= number_format($total_amount, 2) ?></p>
            </div>
        </div>

        <!-- Card 2: Discount -->
        <div class="financial-card">
            <div class="financial-icon purple">
                <i class="fas fa-tags"></i>
            </div>
            <div>
                <p class="financial-label">Discount</p>
                <p class="financial-value" style="color:#7B2FBE;">
                    - TSh <?= number_format($total_discount, 2) ?>
                </p>
                <?php if ($pharmacy_discount > 0 || $cashier_discount > 0): ?>
                    <p class="financial-sub">
                        <?php if ($pharmacy_discount > 0): ?>
                            Pharm: TSh <?= number_format($pharmacy_discount, 0) ?>
                        <?php endif; ?>
                        <?php if ($cashier_discount > 0): ?>
                            <?php if ($pharmacy_discount > 0): ?> | <?php endif; ?>
                            Cash: TSh <?= number_format($cashier_discount, 0) ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card 3: PREMIUM -->
        <div class="financial-card premium-card">
            <div class="financial-icon gold">
                <i class="fas fa-crown"></i>
            </div>
            <div>
                <p class="financial-label">👑 Premium</p>
                <p class="financial-value" style="color:#D97706;">
                    + TSh <?= number_format($premium_amount, 2) ?>
                </p>
                <?php if (!empty($premium_note)): ?>
                    <p class="financial-sub"><?= htmlspecialchars($premium_note) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card 4: Paid Amount -->
        <div class="financial-card">
            <div class="financial-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <p class="financial-label">Paid Amount</p>
                <p class="financial-value" style="color:#059669;">TSh <?= number_format($total_paid, 2) ?></p>
            </div>
        </div>

        <!-- Card 5: Balance -->
        <div class="financial-card">
            <div class="financial-icon <?= $balance > 0 ? 'red' : 'green' ?>">
                <i class="fas fa-<?= $balance > 0 ? 'clock' : 'check-double' ?>"></i>
            </div>
            <div>
                <p class="financial-label">Balance</p>
                <p class="financial-value" style="color: <?= $balance > 0 ? '#DC2626' : '#059669' ?>;">
                    TSh <?= number_format($balance, 2) ?>
                </p>
            </div>
        </div>
    </div>

    <!-- ================================================================
         PREMIUM DETAILS CARD
         ================================================================ -->
    <?php if ($premium_amount > 0): ?>
    <div class="premium-details-card">
        <div class="premium-details-header">
            <i class="fas fa-crown"></i>
            <span>Premium Charge Applied</span>
            <span class="premium-amount-badge">TSh <?= number_format($premium_amount, 0) ?></span>
        </div>
        <div class="premium-details-body">
            <div class="premium-detail-item">
                <span class="label">Premium Amount</span>
                <span class="value gold">TSh <?= number_format($premium_amount, 2) ?></span>
            </div>
            <?php if (!empty($premium_note)): ?>
            <div class="premium-detail-item full">
                <span class="label">Premium Note</span>
                <span class="value"><?= htmlspecialchars($premium_note) ?></span>
            </div>
            <?php endif; ?>
            <div class="premium-detail-item">
                <span class="label">Added to Subtotal</span>
                <span class="value" style="color:#0891B2;">
                    TSh <?= number_format($subtotal, 2) ?> + TSh <?= number_format($premium_amount, 2) ?> = TSh <?= number_format($subtotal + $premium_amount, 2) ?>
                </span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================
         BILL SUMMARY CARD
         ================================================================ -->
    <div class="bill-summary-card">
        <div class="bill-summary-header">
            <div class="bill-number">
                <span class="label">Bill Number</span>
                <span class="value"><?= htmlspecialchars($bill['bill_number']) ?></span>
            </div>
            <div class="bill-status">
                <span class="badge badge-<?= getStatusBadge($bill['status']) ?>">
                    <i class="fas <?= getStatusIcon($bill['status']) ?>"></i>
                    <?= ucfirst($bill['status']) ?>
                </span>
            </div>
        </div>

        <div class="bill-summary-grid">
            <div class="summary-item">
                <span class="label">Patient</span>
                <span class="value"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></span>
                <span class="sub">ID: <?= htmlspecialchars($bill['patient_id_number'] ?? 'N/A') ?></span>
            </div>
            <div class="summary-item">
                <span class="label">Created By</span>
                <span class="value"><?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?></span>
                <span class="sub">@<?= htmlspecialchars($bill['created_by_username'] ?? '') ?></span>
            </div>
            <div class="summary-item">
                <span class="label">Date Created</span>
                <span class="value"><?= date('M d, Y', strtotime($bill['created_at'])) ?></span>
                <span class="sub"><?= date('h:i:s A', strtotime($bill['created_at'])) ?></span>
            </div>
            <div class="summary-item">
                <span class="label">Branch</span>
                <span class="value"><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></span>
                <span class="sub"><?= htmlspecialchars($bill['branch_location'] ?? '') ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================
         BILL ITEMS TABLE
         ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue"></i>
                Bill Items
                <span class="text-xs text-gray-400" style="font-weight:400;">(<?= count($bill_items) ?> items)</span>
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Name</th>
                        <th>Type</th>
                        <th style="text-align:center;">Qty</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Total Price</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($bill_items) > 0): ?>
                        <?php $i = 1; foreach ($bill_items as $item): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <span class="font-medium"><?= htmlspecialchars($item['item_name']) ?></span>
                                    <?php if (!empty($item['description'])): ?>
                                        <br><span class="text-xs text-gray-400"><?= htmlspecialchars($item['description']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="item-type-badge item-<?= $item['item_type'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $item['item_type'])) ?>
                                    </span>
                                </td>
                                <td class="text-center"><?= $item['quantity'] ?></td>
                                <td class="text-right">TSh <?= number_format($item['unit_price'], 2) ?></td>
                                <td class="text-right font-semibold">TSh <?= number_format($item['total_price'], 2) ?></td>
                                <td>
                                    <span class="badge badge-<?= $item['status'] === 'paid' ? 'success' : 'warning' ?>" style="font-size:0.6rem;padding:2px 10px;">
                                        <?= ucfirst($item['status'] ?? 'pending') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-gray-400 text-sm py-5">
                                <i class="fas fa-inbox text-2xl block mb-2"></i>
                                No items found for this bill
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="text-right font-bold">Subtotal</td>
                        <td class="text-right font-bold">TSh <?= number_format($subtotal, 2) ?></td>
                        <td></td>
                    </tr>
                    <?php if ($premium_amount > 0): ?>
                    <tr>
                        <td colspan="5" class="text-right font-bold" style="color:#D97706;">
                            <i class="fas fa-crown"></i> Premium Charge
                        </td>
                        <td class="text-right font-bold" style="color:#D97706;">
                            + TSh <?= number_format($premium_amount, 2) ?>
                        </td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="5" class="text-right font-bold" style="color:#0891B2;">
                            Total with Premium
                        </td>
                        <td class="text-right font-bold" style="color:#0891B2;">
                            TSh <?= number_format($subtotal + $premium_amount, 2) ?>
                        </td>
                        <td></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($total_discount > 0): ?>
                        <tr>
                            <td colspan="5" class="text-right" style="color:#7B2FBE;">
                                Discount
                                <?php if ($pharmacy_discount > 0): ?>
                                    (Pharmacy: TSh <?= number_format($pharmacy_discount, 0) ?>)
                                <?php endif; ?>
                                <?php if ($cashier_discount > 0): ?>
                                    (Cashier: TSh <?= number_format($cashier_discount, 0) ?>)
                                <?php endif; ?>
                            </td>
                            <td class="text-right" style="color:#7B2FBE;">
                                - TSh <?= number_format($total_discount, 2) ?>
                            </td>
                            <td></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td colspan="5" class="text-right font-bold text-lg">Total</td>
                        <td class="text-right font-bold text-lg" style="color:#0B5ED7;">TSh <?= number_format($total_amount, 2) ?></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="5" class="text-right font-bold" style="color:#059669;">Paid</td>
                        <td class="text-right font-bold" style="color:#059669;">TSh <?= number_format($total_paid, 2) ?></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="5" class="text-right font-bold" style="color:<?= $balance > 0 ? '#DC2626' : '#059669' ?>;">Balance</td>
                        <td class="text-right font-bold" style="color:<?= $balance > 0 ? '#DC2626' : '#059669' ?>;">TSh <?= number_format($balance, 2) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- ================================================================
         PAYMENTS & VISIT
         ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2">

        <!-- Payments Section -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-credit-card title-green"></i>
                    Payment History
                    <span class="text-xs text-gray-400" style="font-weight:400;">(<?= count($payments) ?> payments)</span>
                </h3>
            </div>
            <?php if (count($payments) > 0): ?>
                <div class="space-y-3 p-4">
                    <?php foreach ($payments as $payment): ?>
                        <div class="payment-item">
                            <div class="payment-amount">
                                <span class="amount">TSh <?= number_format($payment['amount'], 2) ?></span>
                                <span class="badge badge-<?= $payment['payment_method'] === 'cash' ? 'success' : 'info' ?>" style="font-size:0.6rem;">
                                    <?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?>
                                </span>
                            </div>
                            <div class="payment-details">
                                <span class="receipt"><i class="fas fa-receipt"></i> <?= htmlspecialchars($payment['receipt_number']) ?></span>
                                <span><i class="fas fa-user"></i> <?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?></span>
                                <span><i class="fas fa-clock"></i> <?= date('M d, Y h:i A', strtotime($payment['received_at'])) ?></span>
                            </div>
                            <?php if (!empty($payment['reference_number'])): ?>
                                <div class="payment-reference">
                                    <i class="fas fa-hashtag"></i> Ref: <?= htmlspecialchars($payment['reference_number']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($payment['notes'])): ?>
                                <div class="payment-notes">
                                    <?= htmlspecialchars($payment['notes']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center text-gray-400 text-sm py-5">
                    <i class="fas fa-credit-card text-2xl block mb-2"></i>
                    No payments recorded for this bill
                </div>
            <?php endif; ?>
        </div>

        <!-- Visit Details -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-stethoscope title-purple"></i>
                    Visit Information
                </h3>
            </div>

            <?php if ($visit): ?>
                <div class="related-item m-4">
                    <h4><i class="fas fa-stethoscope"></i> Visit Details</h4>
                    <div class="related-details">
                        <div class="detail-row">
                            <span class="label">Visit #</span>
                            <span class="value"><?= htmlspecialchars($visit['visit_number']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Doctor</span>
                            <span class="value"><?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Receptionist</span>
                            <span class="value"><?= htmlspecialchars($visit['receptionist_name'] ?? 'N/A') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Status</span>
                            <span class="value">
                                <span class="badge badge-<?= $visit['status'] === 'completed' ? 'success' : 'warning' ?>" style="font-size:0.6rem;">
                                    <?= ucfirst($visit['status']) ?>
                                </span>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Visit Type</span>
                            <span class="value"><?= htmlspecialchars($visit['visit_type'] ?? 'N/A') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Date</span>
                            <span class="value"><?= date('M d, Y', strtotime($visit['visit_date'] ?? $visit['created_at'])) ?></span>
                        </div>
                        <?php if (!empty($visit['diagnosis'])): ?>
                            <div class="detail-row full">
                                <span class="label">Diagnosis</span>
                                <span class="value"><?= htmlspecialchars($visit['diagnosis']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($visit['symptoms'])): ?>
                            <div class="detail-row full">
                                <span class="label">Symptoms</span>
                                <span class="value"><?= htmlspecialchars($visit['symptoms']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($visit['treatment'])): ?>
                            <div class="detail-row full">
                                <span class="label">Treatment</span>
                                <span class="value"><?= htmlspecialchars($visit['treatment']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center text-gray-400 text-sm py-5">
                    <i class="fas fa-info-circle text-2xl block mb-2"></i>
                    No visit found for this bill
                </div>
            <?php endif; ?>
        </div>
    </div>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
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
    document.addEventListener('darkModeChanged', function() { setTimeout(enforceDarkModeBackground, 50); });
    new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            if (m.attributeName === 'data-theme') enforceDarkModeBackground();
        });
    }).observe(document.documentElement, { attributes: true });

    console.log('%c📄 Braick - Bill Details (WITH PREMIUM)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🎨 Blue page header + Financial cards', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👑 Premium card highlighted', 'font-size:13px; color:#D97706;');
    console.log('%c🌙 FULL DARK MODE works', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>