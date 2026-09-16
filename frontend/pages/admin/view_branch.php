<?php
// ================================================================
// FILE: frontend/pages/admin/view_branch.php
// VIEW BRANCH DETAILS
// ✅ Uses SHARED header & sidebar
// ✅ Total Revenue = Patient Bills + OTC ONLY (No double counting)
// ✅ Prescription is included in Patient Bills - DISPLAY ONLY
// ✅ Excludes OTC bills from bills table
// ✅ 3 CARDS PER ROW (6 cards total = 2 rows of 3)
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$selected_branch_id = $_GET['branch'] ?? 'all';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET BRANCH ID
// ================================================================
$branch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($branch_id <= 0) {
    header('Location: branches.php?branch=all');
    exit;
}

// ================================================================
// GET BRANCH DATA
// ================================================================
$stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->execute([$branch_id]);
$branch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$branch) {
    header('Location: branches.php?branch=all');
    exit;
}

$branch_name = $branch['name'];
$branch_status = $branch['status'] ?? 'inactive';
$branch_location = $branch['location'] ?? 'N/A';
$branch_phone = $branch['phone'] ?? 'N/A';
$branch_email = $branch['email'] ?? 'N/A';
$branch_created = $branch['created_at'] ?? date('Y-m-d H:i:s');
$branch_updated = $branch['updated_at'] ?? date('Y-m-d H:i:s');

// ================================================================
// STATISTICS
// ================================================================
$stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE branch_id = ? AND status = 'active'");
$stmt->execute([$branch_id]);
$total_employees = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

$stmt = $db->prepare("SELECT role, COUNT(*) as count FROM users WHERE branch_id = ? AND status = 'active' GROUP BY role");
$stmt->execute([$branch_id]);
$role_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_doctors = 0;
$total_pharmacy = 0;
$total_reception = 0;
$total_laboratory = 0;
$total_cashiers = 0;
$total_admins = 0;

foreach ($role_counts as $rc) {
    switch ($rc['role']) {
        case 'admin': $total_admins = (int)$rc['count']; break;
        case 'doctor': $total_doctors = (int)$rc['count']; break;
        case 'pharmacy': $total_pharmacy = (int)$rc['count']; break;
        case 'reception': $total_reception = (int)$rc['count']; break;
        case 'laboratory': $total_laboratory = (int)$rc['count']; break;
        case 'cashier': $total_cashiers = (int)$rc['count']; break;
    }
}

$stmt = $db->prepare("SELECT COUNT(*) as total FROM patients WHERE branch_id = ?");
$stmt->execute([$branch_id]);
$total_patients = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

$stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE branch_id = ? AND status != 'cancelled'");
$stmt->execute([$branch_id]);
$total_visits = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

$stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE branch_id = ? AND status != 'cancelled'");
$stmt->execute([$branch_id]);
$total_prescriptions = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

$stmt = $db->prepare("SELECT COUNT(*) as total FROM lab_tests WHERE branch_id = ?");
$stmt->execute([$branch_id]);
$total_lab_tests = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// ================================================================
// BILLS - PAID ONLY (Excludes OTC)
// ================================================================
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total, 
        COALESCE(SUM(paid_amount), 0) as total_revenue 
    FROM bills 
    WHERE branch_id = ? 
    AND status = 'paid'
    AND patient_id IS NOT NULL
    AND visit_id IS NOT NULL
    AND bill_number NOT LIKE 'BILL-OTC-%'
");
$stmt->execute([$branch_id]);
$bill_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_bills = (int)($bill_data['total'] ?? 0);
$bill_revenue = (float)($bill_data['total_revenue'] ?? 0);

// ================================================================
// OTC REVENUE
// ================================================================
$stmt = $db->prepare("
    SELECT COALESCE(SUM(total_amount), 0) as total_otc_revenue
    FROM otc_sales 
    WHERE branch_id = ? 
    AND payment_status = 'paid'
");
$stmt->execute([$branch_id]);
$otc_revenue = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_otc_revenue'] ?? 0);

// ================================================================
// PRESCRIPTION REVENUE (DISPLAY ONLY)
// ================================================================
$stmt = $db->prepare("
    SELECT COALESCE(SUM(pi.total_price), 0) as total_prescription_revenue
    FROM prescription_items pi
    INNER JOIN prescriptions p ON pi.prescription_id = p.id
    WHERE p.branch_id = ? 
    AND p.status = 'dispensed'
");
$stmt->execute([$branch_id]);
$prescription_revenue = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_prescription_revenue'] ?? 0);

// ================================================================
// TOTAL REVENUE = Patient Bills + OTC ONLY
// ================================================================
$total_revenue = $bill_revenue + $otc_revenue;

// ================================================================
// PENDING BILLS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total, 
        COALESCE(SUM(total_amount), 0) as pending_amount 
    FROM bills 
    WHERE branch_id = ? 
    AND status IN ('pending', 'partial')
    AND patient_id IS NOT NULL
    AND visit_id IS NOT NULL
    AND bill_number NOT LIKE 'BILL-OTC-%'
");
$stmt->execute([$branch_id]);
$pending_data = $stmt->fetch(PDO::FETCH_ASSOC);
$pending_bills = (int)($pending_data['total'] ?? 0);
$pending_revenue = (float)($pending_data['pending_amount'] ?? 0);

// ================================================================
// TOTAL EXPENSES
// ================================================================
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_expenses FROM expenses WHERE branch_id = ? AND status = 'paid'");
$stmt->execute([$branch_id]);
$total_expenses = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?? 0);

$net_profit = $total_revenue - $total_expenses;

// ================================================================
// TOTAL PAYMENTS
// ================================================================
$stmt = $db->prepare("SELECT COUNT(*) as total FROM payments WHERE branch_id = ?");
$stmt->execute([$branch_id]);
$total_payments = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// ================================================================
// RECENT DATA
// ================================================================
$stmt = $db->prepare("SELECT id, full_name, patient_id, phone, created_at FROM patients WHERE branch_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$branch_id]);
$recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT id, full_name, username, role, status, created_at FROM users WHERE branch_id = ? AND role != 'admin' ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$branch_id]);
$recent_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("
    SELECT p.id, p.prescription_number, p.status, p.created_at, pat.full_name as patient_name 
    FROM prescriptions p 
    JOIN patients pat ON p.patient_id = pat.id 
    WHERE p.branch_id = ? 
    ORDER BY p.created_at DESC 
    LIMIT 10
");
$stmt->execute([$branch_id]);
$recent_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("
    SELECT b.id, b.bill_number, b.total_amount, b.paid_amount, b.balance, b.status, b.created_at, p.full_name as patient_name 
    FROM bills b 
    JOIN patients p ON b.patient_id = p.id 
    WHERE b.branch_id = ? 
    AND b.patient_id IS NOT NULL
    AND b.visit_id IS NOT NULL
    AND b.bill_number NOT LIKE 'BILL-OTC-%'
    ORDER BY b.created_at DESC 
    LIMIT 10
");
$stmt->execute([$branch_id]);
$recent_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

function formatMoney($amount) {
    if ($amount === null || $amount === '') return '0';
    return number_format((float)$amount, 0, '.', ',');
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    :root {
        --page-bg-body: #F0F4F8;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-text-muted: #94A3B8;
        --page-border: #E2E8F0;
        --page-hover: #F8FAFC;
        --page-primary: #1A56DB;
        --page-primary-dark: #1A3E8C;
        --page-success: #16A34A;
        --page-success-bg: #DCFCE7;
        --page-danger: #DC2626;
        --page-danger-bg: #FEE2E2;
        --page-warning: #D97706;
        --page-warning-bg: #FEF3C7;
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-text-muted: #64748B;
        --page-border: #334155;
        --page-hover: #1E3A5F;
        --page-primary: #3B82F6;
        --page-primary-dark: #2563EB;
        --page-success-bg: #064E3B;
        --page-danger-bg: #7F1D1D;
        --page-warning-bg: #78350F;
    }

    body { background: var(--page-bg-body, #F0F4F8); }
    html[data-theme="dark"] body { background: #0F172A !important; }
    .main-content { background: var(--page-bg-body, #F0F4F8); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; color: #F1F5F9; }

    /* PAGE HEADER */
    .page-header-custom {
        background: linear-gradient(135deg, #1A56DB 0%, #1A3E8C 100%);
        border-radius: 18px;
        padding: 28px 36px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(26, 86, 219, 0.35);
        position: relative;
        overflow: hidden;
    }

    .page-header-custom::before {
        content: '';
        position: absolute;
        top: -60%; right: -10%;
        width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
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
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
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
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    /* STATS CARDS - 3 PER ROW */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
        margin-bottom: 24px;
    }

    .stat-card-custom {
        border-radius: 14px;
        padding: 20px 24px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        min-height: 90px;
    }

    .stat-card-custom:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 32px rgba(0,0,0,0.25);
    }

    .stat-card-custom .stat-icon {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
        background: rgba(255,255,255,0.2);
        color: white;
    }

    .stat-card-custom .stat-label {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0;
        color: rgba(255,255,255,0.85);
    }

    .stat-card-custom .stat-value {
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0;
        line-height: 1.2;
        color: white;
    }

    .stat-card-custom .stat-sub {
        font-size: 0.65rem;
        color: rgba(255,255,255,0.7);
        margin-top: 2px;
    }

    .stat-card-custom.blue { background: #1A56DB; }
    .stat-card-custom.green { background: #16A34A; }
    .stat-card-custom.red { background: #DC2626; }
    .stat-card-custom.orange { background: #D97706; }

    /* DETAIL CARDS */
    .detail-card-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        padding: 20px 24px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        margin-bottom: 20px;
    }

    html[data-theme="dark"] .detail-card-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .detail-card-custom:hover {
        border-color: var(--page-primary, #1A56DB);
        box-shadow: 0 4px 12px rgba(26, 86, 219, 0.08);
    }

    .detail-card-custom .card-title-custom {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        padding-bottom: 12px;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    html[data-theme="dark"] .detail-card-custom .card-title-custom {
        color: #F1F5F9;
        border-bottom-color: #334155;
    }

    .detail-card-custom .card-title-custom i {
        color: var(--page-primary, #1A56DB);
    }

    .detail-card-custom .card-title-custom .badge-count {
        background: var(--page-primary, #1A56DB);
        color: white;
        padding: 1px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        margin-left: auto;
    }

    .detail-row-custom {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        font-size: 0.85rem;
    }

    html[data-theme="dark"] .detail-row-custom {
        border-bottom-color: #334155;
    }

    .detail-row-custom:last-child {
        border-bottom: none;
    }

    .detail-row-custom .detail-label {
        font-weight: 500;
        color: var(--page-text-secondary, #64748B);
    }

    .detail-row-custom .detail-value {
        font-weight: 500;
        color: var(--page-text-primary, #1E293B);
    }

    html[data-theme="dark"] .detail-row-custom .detail-value {
        color: #F1F5F9;
    }

    /* STATUS BADGES */
    .status-badge-custom {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 700;
    }

    .status-badge-custom.active {
        background: var(--page-success-bg, #DCFCE7);
        color: var(--page-success, #16A34A);
    }

    .status-badge-custom.inactive {
        background: var(--page-danger-bg, #FEE2E2);
        color: var(--page-danger, #DC2626);
    }

    .status-badge-custom.pending {
        background: var(--page-warning-bg, #FEF3C7);
        color: var(--page-warning, #D97706);
    }

    /* LIST ITEMS */
    .list-item-custom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 10px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    html[data-theme="dark"] .list-item-custom {
        border-bottom-color: #334155;
    }

    .list-item-custom:hover {
        background: var(--page-hover, #F8FAFC);
    }

    html[data-theme="dark"] .list-item-custom:hover {
        background: #1E3A5F;
    }

    .list-item-custom:last-child {
        border-bottom: none;
    }

    .list-item-custom .item-info .name {
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        font-size: 0.85rem;
    }

    html[data-theme="dark"] .list-item-custom .item-info .name {
        color: #F1F5F9;
    }

    .list-item-custom .item-info .sub {
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
    }

    .list-item-custom .item-actions .btn-sm-custom {
        padding: 3px 10px;
        font-size: 0.65rem;
        border-radius: 6px;
        text-decoration: none;
        background: var(--page-primary, #1A56DB);
        color: white;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-weight: 600;
    }

    .list-item-custom .item-actions .btn-sm-custom:hover {
        background: var(--page-primary-dark, #1A3E8C);
        transform: scale(1.05);
    }

    .scroll-container {
        max-height: 260px;
        overflow-y: auto;
    }

    .scroll-container::-webkit-scrollbar { width: 4px; }
    .scroll-container::-webkit-scrollbar-track { background: var(--page-bg-body, #F0F4F8); border-radius: 4px; }
    .scroll-container::-webkit-scrollbar-thumb { background: var(--page-primary, #1A56DB); border-radius: 4px; }

    /* QUICK ACTIONS */
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-top: 20px;
    }

    .quick-action-custom {
        padding: 16px;
        border-radius: 12px;
        text-align: center;
        transition: all 0.3s ease;
        text-decoration: none;
        display: block;
        border: 2px solid var(--page-border, #E2E8F0);
        background: var(--page-bg-card, #FFFFFF);
    }

    html[data-theme="dark"] .quick-action-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .quick-action-custom:hover {
        border-color: var(--page-primary, #1A56DB);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .quick-action-custom .icon {
        font-size: 1.6rem;
        display: block;
        margin-bottom: 4px;
    }

    .quick-action-custom .label {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
    }

    html[data-theme="dark"] .quick-action-custom .label {
        color: #F1F5F9;
    }

    .empty-state-custom {
        text-align: center;
        padding: 30px 20px;
        color: var(--page-text-secondary, #64748B);
    }

    .empty-state-custom i {
        font-size: 2.5rem;
        color: var(--page-text-muted, #94A3B8);
        display: block;
        margin-bottom: 8px;
        opacity: 0.5;
    }

    .empty-state-custom p {
        font-size: 0.85rem;
        margin: 0;
    }

    /* GRID UTILITIES */
    .grid-2-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .grid-3-cols { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }

    @media (max-width: 1024px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .quick-actions-grid { grid-template-columns: repeat(2, 1fr); }
        .grid-3-cols { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: 1fr; gap: 10px; }
        .stat-card-custom { padding: 14px 16px; }
        .stat-card-custom .stat-value { font-size: 1.2rem; }
        .grid-2-cols { grid-template-columns: 1fr; }
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }

    @media print {
        .page-header-custom { background: #1A56DB !important; -webkit-print-color-adjust: exact; }
        .quick-action-custom { display: none !important; }
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
                <i class="fas fa-store-alt"></i>
                <?= htmlspecialchars($branch_name) ?>
                <span class="role-badge-display">BRANCH</span>
                <?php if ($branch_status === 'active') { ?>
                    <span class="header-badge" style="background:rgba(52,211,153,0.3);border-color:rgba(52,211,153,0.4);color:#A7F3D0;">
                        <i class="fas fa-circle" style="font-size:6px;"></i> Active
                    </span>
                <?php } else { ?>
                    <span class="header-badge" style="background:rgba(248,113,113,0.3);border-color:rgba(248,113,113,0.4);color:#FECACA;">
                        <i class="fas fa-circle" style="font-size:6px;"></i> Inactive
                    </span>
                <?php } ?>
            </h1>
            <p class="page-subtitle">
                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($branch_location) ?></span>
                <span class="header-badge"><i class="fas fa-phone"></i> <?= htmlspecialchars($branch_phone) ?></span>
                <span class="header-badge"><i class="fas fa-envelope"></i> <?= htmlspecialchars($branch_email) ?></span>
                <span class="header-badge"><i class="fas fa-id-card"></i> ID: #<?= $branch_id ?></span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="edit_branch.php?id=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-edit"></i> Edit Branch
            </a>
            <a href="branches.php?branch=all" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- STATS CARDS - 6 CARDS (3 PER ROW) -->
    <div class="stats-grid">
        <div class="stat-card-custom blue">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div>
                <p class="stat-label">Total Employees</p>
                <p class="stat-value"><?= number_format($total_employees) ?></p>
            </div>
        </div>
        
        <div class="stat-card-custom blue">
            <div class="stat-icon"><i class="fas fa-user-injured"></i></div>
            <div>
                <p class="stat-label">Total Patients</p>
                <p class="stat-value"><?= number_format($total_patients) ?></p>
            </div>
        </div>
        
        <div class="stat-card-custom green">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <p class="stat-label">Total Revenue</p>
                <p class="stat-value">TSh <?= number_format($total_revenue, 0) ?></p>
                <p class="stat-sub">Bills + OTC (Prescription in Bills)</p>
            </div>
        </div>
        
        <div class="stat-card-custom red">
            <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
            <div>
                <p class="stat-label">Total Expenses</p>
                <p class="stat-value">TSh <?= number_format($total_expenses, 0) ?></p>
            </div>
        </div>
        
        <div class="stat-card-custom green">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div>
                <p class="stat-label">Net Profit</p>
                <p class="stat-value">TSh <?= number_format($net_profit, 0) ?></p>
            </div>
        </div>
        
        <div class="stat-card-custom orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div>
                <p class="stat-label">Pending Revenue</p>
                <p class="stat-value">TSh <?= number_format($pending_revenue, 0) ?></p>
                <p class="stat-sub"><?= number_format($pending_bills) ?> pending bills</p>
            </div>
        </div>
    </div>

    <!-- DETAILS & LISTS -->
    <div class="grid-3-cols">
        
        <!-- Branch Information -->
        <div class="detail-card-custom animate-fade-in-up">
            <div class="card-title-custom">
                <i class="fas fa-info-circle"></i>
                Branch Information
            </div>
            
            <div class="detail-row-custom">
                <span class="detail-label">Branch Name</span>
                <span class="detail-value"><strong><?= htmlspecialchars($branch_name) ?></strong></span>
            </div>
            <div class="detail-row-custom">
                <span class="detail-label">Location</span>
                <span class="detail-value"><?= htmlspecialchars($branch_location) ?></span>
            </div>
            <div class="detail-row-custom">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?= htmlspecialchars($branch_phone) ?></span>
            </div>
            <div class="detail-row-custom">
                <span class="detail-label">Email</span>
                <span class="detail-value"><?= htmlspecialchars($branch_email) ?></span>
            </div>
            <div class="detail-row-custom">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <span class="status-badge-custom <?= $branch_status === 'active' ? 'active' : 'inactive' ?>">
                        <?= ucfirst($branch_status) ?>
                    </span>
                </span>
            </div>
            <div class="detail-row-custom">
                <span class="detail-label">Created</span>
                <span class="detail-value"><?= date('F d, Y', strtotime($branch_created)) ?></span>
            </div>
            <div class="detail-row-custom">
                <span class="detail-label">Last Updated</span>
                <span class="detail-value"><?= date('F d, Y', strtotime($branch_updated)) ?></span>
            </div>
            
            <!-- Staff Breakdown -->
            <div style="margin-top:14px;padding-top:14px;border-top:2px solid var(--page-border);">
                <div style="font-size:0.7rem;font-weight:700;color:var(--page-text-secondary);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px;">Staff Breakdown</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 16px;">
                    <div class="detail-row-custom" style="border-bottom:none;padding:3px 0;">
                        <span class="detail-label">👨‍⚕️ Doctors</span>
                        <span class="detail-value"><strong><?= number_format($total_doctors) ?></strong></span>
                    </div>
                    <div class="detail-row-custom" style="border-bottom:none;padding:3px 0;">
                        <span class="detail-label">💊 Pharmacy</span>
                        <span class="detail-value"><strong><?= number_format($total_pharmacy) ?></strong></span>
                    </div>
                    <div class="detail-row-custom" style="border-bottom:none;padding:3px 0;">
                        <span class="detail-label">💉 Laboratory</span>
                        <span class="detail-value"><strong><?= number_format($total_laboratory) ?></strong></span>
                    </div>
                    <div class="detail-row-custom" style="border-bottom:none;padding:3px 0;">
                        <span class="detail-label">📋 Reception</span>
                        <span class="detail-value"><strong><?= number_format($total_reception) ?></strong></span>
                    </div>
                    <div class="detail-row-custom" style="border-bottom:none;padding:3px 0;">
                        <span class="detail-label">💰 Cashiers</span>
                        <span class="detail-value"><strong><?= number_format($total_cashiers) ?></strong></span>
                    </div>
                    <div class="detail-row-custom" style="border-bottom:none;padding:3px 0;">
                        <span class="detail-label">👤 Admins</span>
                        <span class="detail-value"><strong><?= number_format($total_admins) ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Financial Summary -->
        <div class="detail-card-custom animate-fade-in-up" style="grid-column: span 2;">
            <div class="card-title-custom">
                <i class="fas fa-chart-bar"></i>
                Financial Summary
                <span class="badge-count"><?= date('M Y') ?></span>
            </div>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div style="background:#DCFCE7;border-radius:12px;padding:14px 16px;">
                    <p style="font-size:0.7rem;font-weight:600;color:#64748B;margin:0;">💰 Total Revenue</p>
                    <p style="font-size:1.3rem;font-weight:800;color:#16A34A;margin:0;">TSh <?= number_format($total_revenue, 0) ?></p>
                    <div style="display:flex;gap:8px;font-size:0.55rem;color:#64748B;margin-top:4px;flex-wrap:wrap;">
                        <span>📋 Bills: TSh <?= number_format($bill_revenue, 0) ?></span>
                        <span>🏪 OTC: TSh <?= number_format($otc_revenue, 0) ?></span>
                        <span style="font-style:italic;">💊 Presc: TSh <?= number_format($prescription_revenue, 0) ?> (in Bills)</span>
                    </div>
                </div>
                
                <div style="background:#FEE2E2;border-radius:12px;padding:14px 16px;">
                    <p style="font-size:0.7rem;font-weight:600;color:#64748B;margin:0;">📤 Total Expenses</p>
                    <p style="font-size:1.3rem;font-weight:800;color:#DC2626;margin:0;">TSh <?= number_format($total_expenses, 0) ?></p>
                </div>
                
                <div style="background:#DCFCE7;border-radius:12px;padding:14px 16px;">
                    <p style="font-size:0.7rem;font-weight:600;color:#64748B;margin:0;">📈 Net Profit</p>
                    <p style="font-size:1.3rem;font-weight:800;color:#16A34A;margin:0;">TSh <?= number_format($net_profit, 0) ?></p>
                </div>
                
                <div style="background:#FEF3C7;border-radius:12px;padding:14px 16px;">
                    <p style="font-size:0.7rem;font-weight:600;color:#64748B;margin:0;">⏳ Pending Revenue</p>
                    <p style="font-size:1.3rem;font-weight:800;color:#D97706;margin:0;">TSh <?= number_format($pending_revenue, 0) ?></p>
                    <p style="font-size:0.55rem;color:#64748B;margin:0;"><?= number_format($pending_bills) ?> pending bills</p>
                </div>
            </div>
            
            <!-- Additional Stats -->
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:12px;padding-top:12px;border-top:1px solid var(--page-border);">
                <div style="text-align:center;">
                    <p style="font-size:0.6rem;color:var(--page-text-secondary);margin:0;">💊 Prescriptions</p>
                    <p style="font-size:1.1rem;font-weight:800;color:var(--page-text-primary);margin:0;"><?= number_format($total_prescriptions) ?></p>
                </div>
                <div style="text-align:center;">
                    <p style="font-size:0.6rem;color:var(--page-text-secondary);margin:0;">🧪 Lab Tests</p>
                    <p style="font-size:1.1rem;font-weight:800;color:var(--page-text-primary);margin:0;"><?= number_format($total_lab_tests) ?></p>
                </div>
                <div style="text-align:center;">
                    <p style="font-size:0.6rem;color:var(--page-text-secondary);margin:0;">🏥 Visits</p>
                    <p style="font-size:1.1rem;font-weight:800;color:var(--page-text-primary);margin:0;"><?= number_format($total_visits) ?></p>
                </div>
                <div style="text-align:center;">
                    <p style="font-size:0.6rem;color:var(--page-text-secondary);margin:0;">💳 Payments</p>
                    <p style="font-size:1.1rem;font-weight:800;color:var(--page-text-primary);margin:0;"><?= number_format($total_payments) ?></p>
                </div>
            </div>
        </div>
        
    </div>

    <!-- LISTS -->
    <div class="grid-2-cols" style="margin-top:20px;">
        
        <!-- Recent Patients -->
        <div class="detail-card-custom animate-fade-in-up">
            <div class="card-title-custom">
                <i class="fas fa-user-injured"></i>
                Recent Patients
                <span class="badge-count"><?= count($recent_patients) ?></span>
            </div>
            
            <div class="scroll-container">
                <?php if (count($recent_patients) > 0) { ?>
                    <?php foreach ($recent_patients as $patient) { ?>
                        <div class="list-item-custom">
                            <div class="item-info">
                                <div class="name"><?= htmlspecialchars($patient['full_name']) ?></div>
                                <div class="sub">
                                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id']) ?>
                                    <i class="fas fa-phone" style="margin-left:8px;"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?>
                                </div>
                            </div>
                            <div class="item-actions">
                                <a href="view_patient.php?id=<?= $patient['id'] ?>" class="btn-sm-custom">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="empty-state-custom">
                        <i class="fas fa-user-injured"></i>
                        <p>No patients registered</p>
                    </div>
                <?php } ?>
            </div>
        </div>
        
        <!-- Recent Employees -->
        <div class="detail-card-custom animate-fade-in-up">
            <div class="card-title-custom">
                <i class="fas fa-user-tie"></i>
                Recent Employees
                <span class="badge-count"><?= count($recent_employees) ?></span>
            </div>
            
            <div class="scroll-container">
                <?php if (count($recent_employees) > 0) { ?>
                    <?php foreach ($recent_employees as $employee) { ?>
                        <div class="list-item-custom">
                            <div class="item-info">
                                <div class="name"><?= htmlspecialchars($employee['full_name']) ?></div>
                                <div class="sub">
                                    <i class="fas fa-user-tag"></i> <?= ucfirst($employee['role']) ?>
                                    <span class="status-badge-custom <?= $employee['status'] === 'active' ? 'active' : 'inactive' ?>" style="font-size:0.5rem;padding:1px 8px;margin-left:4px;">
                                        <?= ucfirst($employee['status']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="item-actions">
                                <a href="view_user.php?id=<?= $employee['id'] ?>" class="btn-sm-custom">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="empty-state-custom">
                        <i class="fas fa-user-tie"></i>
                        <p>No employees assigned</p>
                    </div>
                <?php } ?>
            </div>
        </div>
        
        <!-- Recent Prescriptions -->
        <div class="detail-card-custom animate-fade-in-up">
            <div class="card-title-custom">
                <i class="fas fa-prescription"></i>
                Recent Prescriptions
                <span class="badge-count"><?= count($recent_prescriptions) ?></span>
            </div>
            
            <div class="scroll-container">
                <?php if (count($recent_prescriptions) > 0) { ?>
                    <?php foreach ($recent_prescriptions as $pres) { ?>
                        <div class="list-item-custom">
                            <div class="item-info">
                                <div class="name"><?= htmlspecialchars($pres['patient_name'] ?? 'Unknown') ?></div>
                                <div class="sub">
                                    <i class="fas fa-prescription"></i> <?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?>
                                    <span class="status-badge-custom <?= $pres['status'] === 'dispensed' ? 'active' : ($pres['status'] === 'confirmed' ? 'pending' : 'inactive') ?>" 
                                          style="font-size:0.5rem;padding:1px 8px;margin-left:4px;">
                                        <?= ucfirst($pres['status'] ?? 'pending') ?>
                                    </span>
                                </div>
                            </div>
                            <div class="item-actions">
                                <a href="view_prescription.php?id=<?= $pres['id'] ?>" class="btn-sm-custom">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="empty-state-custom">
                        <i class="fas fa-prescription"></i>
                        <p>No prescriptions available</p>
                    </div>
                <?php } ?>
            </div>
        </div>
        
        <!-- Recent Bills -->
        <div class="detail-card-custom animate-fade-in-up">
            <div class="card-title-custom">
                <i class="fas fa-file-invoice"></i>
                Recent Bills
                <span class="badge-count"><?= count($recent_bills) ?></span>
            </div>
            
            <div class="scroll-container">
                <?php if (count($recent_bills) > 0) { ?>
                    <?php foreach ($recent_bills as $bill) { ?>
                        <div class="list-item-custom">
                            <div class="item-info">
                                <div class="name"><?= htmlspecialchars($bill['patient_name'] ?? 'Unknown') ?></div>
                                <div class="sub">
                                    <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($bill['bill_number']) ?>
                                    <i class="fas fa-money-bill-wave" style="margin-left:8px;"></i> TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?>
                                    <span class="status-badge-custom <?= $bill['status'] === 'paid' ? 'active' : ($bill['status'] === 'partial' ? 'pending' : 'inactive') ?>" 
                                          style="font-size:0.5rem;padding:1px 8px;margin-left:4px;">
                                        <?= ucfirst($bill['status'] ?? 'pending') ?>
                                    </span>
                                </div>
                            </div>
                            <div class="item-actions">
                                <a href="view_bill.php?id=<?= $bill['id'] ?>" class="btn-sm-custom">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="empty-state-custom">
                        <i class="fas fa-file-invoice"></i>
                        <p>No bills available</p>
                    </div>
                <?php } ?>
            </div>
        </div>
        
    </div>

    <!-- QUICK ACTIONS -->
    <div class="quick-actions-grid">
        <a href="add_employee.php?branch=<?= $branch_id ?>" class="quick-action-custom">
            <span class="icon">👤</span>
            <span class="label">Add Employee</span>
        </a>
        <a href="edit_branch.php?id=<?= $branch_id ?>" class="quick-action-custom">
            <span class="icon">✏️</span>
            <span class="label">Edit Branch</span>
        </a>
        <a href="branch_reports.php?id=<?= $branch_id ?>" class="quick-action-custom">
            <span class="icon">📊</span>
            <span class="label">Reports</span>
        </a>
        <a href="branches.php?branch=all" class="quick-action-custom">
            <span class="icon">🏢</span>
            <span class="label">All Branches</span>
        </a>
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

    console.log('%c🏢 Braick - View Branch', 'font-size:18px; font-weight:bold; color:#1A56DB;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#16A34A;');
    console.log('%c🌙 FULL DARK MODE inafanya kazi', 'font-size:13px; color:#7C3AED;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?> (ID: <?= $branch_id ?>)', 'font-size:13px; color:#1A56DB;');
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue, 0) ?> (Bills + OTC ONLY)', 'font-size:13px; color:#16A34A;');
    console.log('%c✅ 3 CARDS PER ROW (6 cards = 2 rows)', 'font-size:13px; color:#16A34A;');
</script>

</body>
</html>