<?php
// ================================================================
// FILE: frontend/pages/admin/cashier_dashboard.php
// ADMIN - CASHIER DASHBOARD WITH 8 CARDS (FULL SIZE)
// ✅ Uses SHARED header & sidebar
// ✅ Beautiful green page header + 8 gradient cards + charts
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

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

$branch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($branch_id <= 0) {
    header('Location: branches.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH BRANCH DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT b.*,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'cashier' AND status = 'active') as active_cashiers,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'cashier') as total_cashiers,
            (SELECT COUNT(*) FROM bills WHERE branch_id = b.id AND status = 'pending') as pending_bills,
            (SELECT COUNT(*) FROM bills WHERE branch_id = b.id AND status = 'partial') as partial_bills,
            (SELECT COUNT(*) FROM bills WHERE branch_id = b.id AND status = 'paid') as paid_bills,
            (SELECT COUNT(*) FROM bills WHERE branch_id = b.id AND status = 'cancelled') as cancelled_bills,
            (SELECT COUNT(*) FROM bills WHERE branch_id = b.id) as total_bills,
            (SELECT COUNT(*) FROM payments WHERE branch_id = b.id) as total_payments,
            (SELECT COUNT(*) FROM payments WHERE branch_id = b.id AND DATE(received_at) = CURDATE()) as today_payments
        FROM branches b
        WHERE b.id = ?
    ");
    $stmt->execute([$branch_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$branch) {
        header('Location: branches.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching branch: " . $e->getMessage());
    header('Location: branches.php?branch=' . urlencode($selected_branch_id) . '&error=database_error');
    exit;
}

// ================================================================
// REVENUE QUERIES
// ================================================================
$total_revenue = 0;
try {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_revenue FROM payments WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
} catch (Exception $e) { $total_revenue = 0; }

$medication_revenue = 0;
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(bi.total_price), 0) as medication_revenue
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE b.branch_id = ? AND bi.item_type = 'medication' AND b.status = 'paid'
    ");
    $stmt->execute([$branch_id]);
    $medication_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['medication_revenue'] ?? 0;
} catch (Exception $e) { $medication_revenue = 0; }

$otc_revenue = 0;
try {
    $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as otc_revenue FROM otc_sales WHERE branch_id = ? AND payment_status = 'paid'");
    $stmt->execute([$branch_id]);
    $otc_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['otc_revenue'] ?? 0;
} catch (Exception $e) { $otc_revenue = 0; }

$pharmacy_total = $medication_revenue + $otc_revenue;

$lab_revenue = 0;
$lab_tests_count = 0;
try {
    $stmt = $db->prepare("SELECT COALESCE(SUM(test_price), 0) as lab_revenue FROM lab_tests WHERE branch_id = ? AND status = 'completed'");
    $stmt->execute([$branch_id]);
    $lab_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['lab_revenue'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM lab_tests WHERE branch_id = ? AND status = 'completed'");
    $stmt->execute([$branch_id]);
    $lab_tests_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $lab_revenue = 0; $lab_tests_count = 0; }

$procedures_revenue = 0;
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(bi.total_price), 0) as procedures_revenue
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE b.branch_id = ? AND bi.item_type = 'procedure' AND b.status = 'paid'
    ");
    $stmt->execute([$branch_id]);
    $procedures_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['procedures_revenue'] ?? 0;
} catch (Exception $e) { $procedures_revenue = 0; }

$tools_revenue = 0;
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(bi.total_price), 0) as tools_revenue
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE b.branch_id = ? AND bi.item_type IN ('equipment', 'tool') AND b.status = 'paid'
    ");
    $stmt->execute([$branch_id]);
    $tools_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['tools_revenue'] ?? 0;
} catch (Exception $e) { $tools_revenue = 0; }

$procedures_tools_total = $procedures_revenue + $tools_revenue;

$consultation_revenue = 0;
try {
    $stmt = $db->prepare("SELECT COALESCE(SUM(consultation_fee), 0) as consultation_revenue FROM visits WHERE branch_id = ? AND status = 'completed'");
    $stmt->execute([$branch_id]);
    $consultation_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['consultation_revenue'] ?? 0;
} catch (Exception $e) { $consultation_revenue = 0; }

$grand_total_revenue = $pharmacy_total + $lab_revenue + $procedures_tools_total + $consultation_revenue;

// ================================================================
// EXPENSES
// ================================================================
$total_expenses = 0;
try {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_expenses FROM expenses WHERE branch_id = ? AND status = 'paid'");
    $stmt->execute([$branch_id]);
    $total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?? 0;
} catch (Exception $e) { $total_expenses = 0; }

$pending_expenses = 0;
try {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as pending_expenses FROM expenses WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$branch_id]);
    $pending_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['pending_expenses'] ?? 0;
} catch (Exception $e) { $pending_expenses = 0; }

$expense_count = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM expenses WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $expense_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $expense_count = 0; }

// ================================================================
// PROFIT
// ================================================================
$net_profit = $grand_total_revenue - $total_expenses;
$profit_margin = $grand_total_revenue > 0 ? ($net_profit / $grand_total_revenue) * 100 : 0;

// ================================================================
// WEEKLY REVENUE
// ================================================================
$weekly_revenue = [];
try {
    $stmt = $db->prepare("
        SELECT DATE(received_at) as date, COALESCE(SUM(amount), 0) as total
        FROM payments 
        WHERE branch_id = ? AND received_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(received_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$branch_id]);
    $weekly_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $weekly_revenue = []; }

$weekly_expenses = [];
try {
    $stmt = $db->prepare("
        SELECT DATE(payment_date) as date, COALESCE(SUM(amount), 0) as total
        FROM expenses 
        WHERE branch_id = ? AND status = 'paid' AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(payment_date)
        ORDER BY date ASC
    ");
    $stmt->execute([$branch_id]);
    $weekly_expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $weekly_expenses = []; }

// ================================================================
// RECENT DATA
// ================================================================
$recent_payments = [];
try {
    $stmt = $db->prepare("
        SELECT p.id, p.receipt_number, p.amount, p.payment_method, p.received_at,
            b.bill_number, pat.full_name as patient_name, u.full_name as received_by_name
        FROM payments p
        LEFT JOIN bills b ON p.bill_id = b.id
        LEFT JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN users u ON p.received_by = u.id
        WHERE p.branch_id = ?
        ORDER BY p.received_at DESC LIMIT 10
    ");
    $stmt->execute([$branch_id]);
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recent_payments = []; }

$recent_bills = [];
try {
    $stmt = $db->prepare("
        SELECT b.id, b.bill_number, b.total_amount, b.paid_amount, b.balance, b.status, b.created_at,
            pat.full_name as patient_name
        FROM bills b
        LEFT JOIN patients pat ON b.patient_id = pat.id
        WHERE b.branch_id = ?
        ORDER BY b.created_at DESC LIMIT 10
    ");
    $stmt->execute([$branch_id]);
    $recent_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recent_bills = []; }

$recent_expenses = [];
try {
    $stmt = $db->prepare("
        SELECT e.id, e.expense_number, e.category, e.description, e.amount, 
            e.payment_method, e.payment_date, e.status, e.created_at,
            u.full_name as created_by_name
        FROM expenses e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.branch_id = ?
        ORDER BY e.created_at DESC LIMIT 10
    ");
    $stmt->execute([$branch_id]);
    $recent_expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recent_expenses = []; }

$cashiers = [];
try {
    $stmt = $db->prepare("SELECT id, full_name, email, phone, status, created_at FROM users WHERE branch_id = ? AND role = 'cashier' ORDER BY full_name");
    $stmt->execute([$branch_id]);
    $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $cashiers = []; }

$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $branches = []; }

// ================================================================
// HELPERS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'active' => 'success', 'inactive' => 'danger', 'pending' => 'warning',
        'paid' => 'success', 'partial' => 'warning', 'cancelled' => 'danger', 'approved' => 'success'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'active' => 'fa-check-circle', 'inactive' => 'fa-times-circle', 'pending' => 'fa-clock',
        'paid' => 'fa-check-circle', 'partial' => 'fa-clock', 'cancelled' => 'fa-times-circle', 'approved' => 'fa-check-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

function formatCurrency($amount) {
    return 'TSh ' . number_format($amount, 0);
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
        --page-primary: #059669;
        --page-primary-dark: #047857;
        --page-primary-bg: #D1FAE5;
        --page-primary-light: #34D399;
        --page-success: #059669;
        --page-success-bg: #D1FAE5;
        --page-danger: #DC2626;
        --page-danger-bg: #FEE2E2;
        --page-warning: #D97706;
        --page-warning-bg: #FEF3C7;
        --page-purple: #7C3AED;
        --page-purple-bg: #EDE9FE;
        --page-teal: #0D9488;
        --page-teal-bg: #ECFDF5;
        --page-orange: #F59E0B;
        --page-orange-bg: #FFFBEB;
        --page-pink: #EC4899;
        --page-pink-bg: #FDF2F8;
        --page-indigo: #4F46E5;
        --page-indigo-bg: #EEF2FF;
        --page-blue: #0B5ED7;
        --page-blue-bg: #E8F0FE;
        --page-bg-body: #F0FDF4;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-border: #D1FAE5;
        --page-table-hover: #ECFDF5;
        --page-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --page-shadow-md: 0 8px 30px rgba(0,0,0,0.12);
        --page-shadow-lg: 0 15px 50px rgba(0,0,0,0.15);
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-border: #334155;
        --page-table-hover: #1A3A2A;
        --page-primary: #34D399;
        --page-primary-dark: #059669;
        --page-primary-bg: #1A3A2A;
        --page-shadow-md: 0 8px 30px rgba(0,0,0,0.3);
        --page-shadow-lg: 0 15px 50px rgba(0,0,0,0.4);
    }

    body { background: var(--page-bg-body); }
    html[data-theme="dark"] body { background: #0F172A !important; }

    .main-content { background: var(--page-bg-body); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; }

    /* ================================================================
       GREEN PAGE HEADER CARD (CASHIER THEME)
       ================================================================ */
    .page-header-card {
        background: linear-gradient(135deg, #047857 0%, #065F46 50%, #064E3B 100%);
        border-radius: 20px;
        padding: 28px 36px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        color: white;
        box-shadow: 0 8px 32px rgba(4, 120, 87, 0.35), 0 4px 12px rgba(4, 120, 87, 0.2);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
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

    .page-header-card::after {
        content: '';
        position: absolute;
        bottom: -60%; left: -5%;
        width: 300px; height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-title {
        font-size: 1.6rem;
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
        width: 46px; height: 46px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-subtitle {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.95);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .page-header-subtitle strong { color: white; font-weight: 700; }

    .role-badge-display {
        background: rgba(255,255,255,0.25);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
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
       8 CARDS GRID
       ================================================================ */
    .cards-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 28px;
    }

    .dashboard-card {
        background: var(--page-bg-card);
        border-radius: 18px;
        border: 2px solid var(--page-border);
        box-shadow: var(--page-shadow-sm);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        color: var(--page-text-primary);
        overflow: hidden;
        position: relative;
        min-height: 170px;
        display: flex;
        flex-direction: column;
    }

    html[data-theme="dark"] .dashboard-card {
        background: #1E293B;
        border-color: #334155;
    }

    .dashboard-card::after {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
        background: var(--card-accent, var(--page-primary));
        opacity: 0.9;
        transition: all 0.3s ease;
    }

    .dashboard-card:hover {
        transform: translateY(-6px);
        box-shadow: var(--page-shadow-lg);
        border-color: var(--card-accent, var(--page-primary));
    }

    .dashboard-card .card-header-bg {
        padding: 16px 20px 14px;
        display: flex;
        align-items: center;
        gap: 12px;
        position: relative;
        min-height: 66px;
    }

    .dashboard-card .card-header-bg .card-icon {
        width: 48px; height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: white;
        flex-shrink: 0;
        background: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.15);
        transition: all 0.3s ease;
    }

    .dashboard-card:hover .card-header-bg .card-icon {
        transform: scale(1.08);
        background: rgba(255,255,255,0.3);
    }

    .dashboard-card .card-header-bg .card-label {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin: 0;
        color: rgba(255,255,255,0.95);
        line-height: 1.3;
    }

    .dashboard-card .card-header-bg .card-label small {
        display: block;
        font-weight: 400;
        text-transform: none;
        opacity: 0.8;
        font-size: 0.6rem;
        letter-spacing: 0.02em;
        margin-top: 2px;
    }

    .dashboard-card .card-body {
        padding: 16px 20px 18px;
        background: var(--page-bg-card);
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    html[data-theme="dark"] .dashboard-card .card-body { background: #1E293B; }

    .dashboard-card .card-body .card-amount {
        font-size: 1.85rem;
        font-weight: 800;
        color: var(--page-text-primary);
        line-height: 1.1;
        letter-spacing: -0.02em;
    }

    .dashboard-card .card-body .card-amount .currency {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--page-text-secondary);
        margin-right: 4px;
    }

    .dashboard-card .card-body .card-amount.green { color: #059669; }
    .dashboard-card .card-body .card-amount.blue { color: #0B5ED7; }
    .dashboard-card .card-body .card-amount.purple { color: #7C3AED; }
    .dashboard-card .card-body .card-amount.teal { color: #0D9488; }
    .dashboard-card .card-body .card-amount.orange { color: #D97706; }
    .dashboard-card .card-body .card-amount.pink { color: #EC4899; }
    .dashboard-card .card-body .card-amount.red { color: #DC2626; }
    .dashboard-card .card-body .card-amount.indigo { color: #4F46E5; }

    html[data-theme="dark"] .dashboard-card .card-body .card-amount.green { color: #34D399; }
    html[data-theme="dark"] .dashboard-card .card-body .card-amount.blue { color: #6EA8FE; }
    html[data-theme="dark"] .dashboard-card .card-body .card-amount.purple { color: #A78BFA; }
    html[data-theme="dark"] .dashboard-card .card-body .card-amount.teal { color: #2DD4BF; }
    html[data-theme="dark"] .dashboard-card .card-body .card-amount.orange { color: #FBBF24; }
    html[data-theme="dark"] .dashboard-card .card-body .card-amount.pink { color: #F472B6; }
    html[data-theme="dark"] .dashboard-card .card-body .card-amount.red { color: #F87171; }
    html[data-theme="dark"] .dashboard-card .card-body .card-amount.indigo { color: #A5B4FC; }

    .dashboard-card .card-body .card-sub {
        font-size: 0.72rem;
        color: var(--page-text-secondary);
        margin: 8px 0 0 0;
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .dashboard-card .card-body .card-sub .badge-mini {
        font-size: 0.6rem;
        padding: 3px 10px;
        border-radius: 14px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .dashboard-card .card-body .card-sub .badge-mini.green { background: #D1FAE5; color: #065F46; }
    .dashboard-card .card-body .card-sub .badge-mini.red { background: #FEE2E2; color: #991B1B; }
    .dashboard-card .card-body .card-sub .badge-mini.orange { background: #FEF3C7; color: #92400E; }
    .dashboard-card .card-body .card-sub .badge-mini.purple { background: #EDE9FE; color: #5B21B6; }
    .dashboard-card .card-body .card-sub .badge-mini.teal { background: #ECFDF5; color: #0F766E; }
    .dashboard-card .card-body .card-sub .badge-mini.blue { background: #E8F0FE; color: #0A4CA8; }
    .dashboard-card .card-body .card-sub .badge-mini.pink { background: #FDF2F8; color: #9D174D; }

    html[data-theme="dark"] .dashboard-card .card-body .card-sub .badge-mini.green { background: #1A3A2A; color: #34D399; }
    html[data-theme="dark"] .dashboard-card .card-body .card-sub .badge-mini.red { background: #3A1A1A; color: #F87171; }
    html[data-theme="dark"] .dashboard-card .card-body .card-sub .badge-mini.orange { background: #3D2E0A; color: #FBBF24; }
    html[data-theme="dark"] .dashboard-card .card-body .card-sub .badge-mini.purple { background: #2D1B5F; color: #A78BFA; }
    html[data-theme="dark"] .dashboard-card .card-body .card-sub .badge-mini.teal { background: #0D2E2A; color: #2DD4BF; }
    html[data-theme="dark"] .dashboard-card .card-body .card-sub .badge-mini.blue { background: #1E3A5F; color: #6EA8FE; }
    html[data-theme="dark"] .dashboard-card .card-body .card-sub .badge-mini.pink { background: #3A1A28; color: #F472B6; }

    .dashboard-card .card-footer {
        padding: 8px 20px 10px;
        border-top: 1px solid var(--page-border);
        background: var(--page-bg-body);
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 6px;
    }

    html[data-theme="dark"] .dashboard-card .card-footer { background: #0F172A; }

    .dashboard-card .card-footer .card-nav-arrow {
        font-size: 0.72rem;
        color: var(--page-text-secondary);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .dashboard-card .card-footer .card-nav-arrow .arrow-text {
        font-size: 0.62rem;
        font-weight: 600;
        opacity: 0;
        transition: all 0.3s ease;
    }

    .dashboard-card:hover .card-footer .card-nav-arrow {
        color: var(--card-accent, var(--page-primary));
    }

    .dashboard-card:hover .card-footer .card-nav-arrow .arrow-text { opacity: 1; }

    .dashboard-card:hover .card-footer .card-nav-arrow i {
        transform: translateX(4px);
    }

    /* Card header gradient backgrounds */
    .card-header-bg.green { background: linear-gradient(135deg, #059669, #047857); --card-accent: #059669; }
    .card-header-bg.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); --card-accent: #0B5ED7; }
    .card-header-bg.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); --card-accent: #7C3AED; }
    .card-header-bg.teal { background: linear-gradient(135deg, #0D9488, #0F766E); --card-accent: #0D9488; }
    .card-header-bg.orange { background: linear-gradient(135deg, #D97706, #B45309); --card-accent: #D97706; }
    .card-header-bg.pink { background: linear-gradient(135deg, #EC4899, #BE185D); --card-accent: #EC4899; }
    .card-header-bg.red { background: linear-gradient(135deg, #DC2626, #B91C1C); --card-accent: #DC2626; }
    .card-header-bg.indigo { background: linear-gradient(135deg, #4F46E5, #4338CA); --card-accent: #4F46E5; }

    /* ================================================================
       CHART GRID
       ================================================================ */
    .chart-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }

    .chart-container {
        position: relative;
        height: 240px;
        width: 100%;
    }

    /* ================================================================
       TABLE CARDS
       ================================================================ */
    .table-card {
        background: var(--page-bg-card);
        border-radius: 18px;
        border: 2px solid var(--page-border);
        overflow: hidden;
        box-shadow: var(--page-shadow-sm);
        margin-bottom: 20px;
    }

    html[data-theme="dark"] .table-card {
        background: #1E293B;
        border-color: #334155;
    }

    .table-card .card-header {
        padding: 14px 22px;
        background: linear-gradient(135deg, #047857, #065F46);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .table-card .card-header .card-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: white;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .table-card .card-header .card-action {
        color: rgba(255,255,255,0.85);
        font-size: 0.72rem;
        text-decoration: none;
        transition: all 0.3s;
        font-weight: 600;
    }

    .table-card .card-header .card-action:hover { color: white; }

    .table-card .card-body { padding: 0; }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }

    .data-table thead th {
        background: var(--page-bg-body);
        color: var(--page-text-secondary);
        font-weight: 700;
        padding: 12px 16px;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--page-border);
        text-align: left;
        white-space: nowrap;
    }

    html[data-theme="dark"] .data-table thead th { background: #0F172A; }

    .data-table td {
        padding: 10px 16px;
        border-bottom: 1px solid var(--page-border);
        color: var(--page-text-primary);
        vertical-align: middle;
    }

    .data-table tbody tr { transition: background 0.2s ease; }
    .data-table tbody tr:hover td { background: var(--page-table-hover); }
    .data-table tbody tr:last-child td { border-bottom: none; }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        color: white;
        letter-spacing: 0.02em;
    }

    .badge-success { background: #059669; }
    .badge-danger { background: #DC2626; }
    .badge-warning { background: #D97706; color: #1E293B; }
    .badge-info { background: #0B5ED7; }
    .badge-secondary { background: #64748B; }
    .badge-purple { background: #7C3AED; }
    .badge-teal { background: #0D9488; }

    html[data-theme="dark"] .badge-warning { color: #1E293B; }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state {
        padding: 40px 20px;
        text-align: center;
        color: var(--page-text-secondary);
    }

    .empty-state i {
        font-size: 2.5rem;
        display: block;
        margin-bottom: 10px;
        opacity: 0.5;
    }

    .empty-state p { margin: 0; font-size: 0.85rem; }

    /* ================================================================
       SPINNER / ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up {
        animation: fadeInUp 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        opacity: 0;
    }

    .cards-grid .dashboard-card {
        animation: fadeInUp 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        opacity: 0;
    }

    .cards-grid .dashboard-card:nth-child(1) { animation-delay: 0.05s; }
    .cards-grid .dashboard-card:nth-child(2) { animation-delay: 0.10s; }
    .cards-grid .dashboard-card:nth-child(3) { animation-delay: 0.15s; }
    .cards-grid .dashboard-card:nth-child(4) { animation-delay: 0.20s; }
    .cards-grid .dashboard-card:nth-child(5) { animation-delay: 0.25s; }
    .cards-grid .dashboard-card:nth-child(6) { animation-delay: 0.30s; }
    .cards-grid .dashboard-card:nth-child(7) { animation-delay: 0.35s; }
    .cards-grid .dashboard-card:nth-child(8) { animation-delay: 0.40s; }

    /* ================================================================
       TOAST
       ================================================================ */
    .toast-modern {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 14px 20px;
        border-radius: 12px;
        z-index: 9999;
        max-width: 420px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }

    .toast-modern.show { transform: translateY(0); opacity: 1; }
    .toast-modern.success { background: linear-gradient(135deg, #059669, #047857); }
    .toast-modern.error { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    .toast-modern.info { background: linear-gradient(135deg, #2563EB, #1D4ED8); }

    /* ================================================================
       UTILITY
       ================================================================ */
    .font-mono { font-family: 'Courier New', monospace; }
    .font-semibold { font-weight: 600; }
    .text-xs { font-size: 0.7rem; }
    .text-green-600 { color: #059669; }
    .text-red-600 { color: #DC2626; }
    .text-blue-600 { color: #0B5ED7; }

    html[data-theme="dark"] .text-green-600 { color: #34D399; }
    html[data-theme="dark"] .text-red-600 { color: #F87171; }
    html[data-theme="dark"] .text-blue-600 { color: #6EA8FE; }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1200px) {
        .cards-grid { grid-template-columns: repeat(3, 1fr); }
    }

    @media (max-width: 1024px) {
        .cards-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-card { padding: 20px; }
        .page-header-title { font-size: 1.2rem; }
        .page-header-title i { width: 38px; height: 38px; font-size: 1rem; }
        .cards-grid { grid-template-columns: 1fr 1fr; gap: 14px; }
        .dashboard-card .card-body .card-amount { font-size: 1.4rem; }
        .chart-grid { grid-template-columns: 1fr; }
        .data-table { font-size: 0.7rem; }
        .data-table thead th, .data-table td { padding: 8px 10px; }
    }

    @media (max-width: 480px) {
        .cards-grid { grid-template-columns: 1fr; gap: 14px; }
        .dashboard-card .card-body .card-amount { font-size: 1.6rem; }
        .data-table { font-size: 0.62rem; }
        .data-table thead th, .data-table td { padding: 6px 8px; }
    }

    /* Print */
    @media print {
        .page-header-actions, .btn, .btn-outline-light, .toast-modern { display: none !important; }
        .page-header-card { background: #047857 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .table-card .card-header { background: #047857 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .dashboard-card, .badge, .badge-mini { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Green Page Header Card -->
    <div class="page-header-card animate-fade-in-up">
        <div>
            <h1 class="page-header-title">
                <i class="fas fa-cash-register"></i>
                Cashier Dashboard
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-header-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($branch['name']) ?></strong>
                <span class="page-header-badge">
                    <i class="fas fa-<?= $branch['status'] === 'active' ? 'check-circle' : 'times-circle' ?>" style="color:<?= $branch['status'] === 'active' ? '#34D399' : '#F87171' ?>;"></i>
                    <?= ucfirst($branch['status']) ?>
                </span>
                <span class="page-header-badge" style="background:rgba(52,211,153,0.25);">
                    <i class="fas fa-money-bill-wave"></i> <?= formatCurrency($grand_total_revenue) ?> Revenue
                </span>
                <span class="page-header-badge" style="background:rgba(251,191,36,0.25);">
                    <i class="fas fa-file-invoice"></i> <?= $branch['total_bills'] ?? 0 ?> Bills
                </span>
            </p>
        </div>
        <div class="page-header-actions">
            <a href="branches.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="cashier_reports.php?id=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-file-alt"></i> Reports
            </a>
        </div>
    </div>

    <!-- ================================================================
         8 CARDS GRID
         ================================================================ -->
    <div class="cards-grid">

        <!-- 1. TOTAL REVENUE -->
        <a href="payments.php?branch=<?= $branch_id ?>" class="dashboard-card">
            <div class="card-header-bg green">
                <div class="card-icon"><i class="fas fa-money-bill-wave"></i></div>
                <p class="card-label">Total Revenue <small>All payments</small></p>
            </div>
            <div class="card-body">
                <p class="card-amount green"><span class="currency">TSh</span> <?= number_format($grand_total_revenue, 0) ?></p>
                <p class="card-sub">
                    <span class="badge-mini green"><i class="fas fa-arrow-up"></i> +<?= number_format($profit_margin, 1) ?>%</span>
                    <?= $branch['total_payments'] ?? 0 ?> transactions
                </p>
            </div>
            <div class="card-footer">
                <span class="card-nav-arrow">
                    <span class="arrow-text">View Details</span>
                    <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </a>

        <!-- 2. EXPENSES -->
        <a href="expenses.php?branch=<?= $branch_id ?>" class="dashboard-card">
            <div class="card-header-bg red">
                <div class="card-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                <p class="card-label">Expenses <small>Total paid</small></p>
            </div>
            <div class="card-body">
                <p class="card-amount red"><span class="currency">TSh</span> <?= number_format($total_expenses, 0) ?></p>
                <p class="card-sub">
                    <span class="badge-mini orange"><i class="fas fa-clock"></i> <?= formatCurrency($pending_expenses) ?> pending</span>
                    <?= $expense_count ?> transactions
                </p>
            </div>
            <div class="card-footer">
                <span class="card-nav-arrow">
                    <span class="arrow-text">View Details</span>
                    <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </a>

        <!-- 3. NET PROFIT -->
        <a href="profit.php?branch=<?= $branch_id ?>" class="dashboard-card">
            <div class="card-header-bg <?= $net_profit >= 0 ? 'green' : 'red' ?>">
                <div class="card-icon"><i class="fas fa-chart-line"></i></div>
                <p class="card-label">Net Profit <small>Revenue - Expenses</small></p>
            </div>
            <div class="card-body">
                <p class="card-amount <?= $net_profit >= 0 ? 'green' : 'red' ?>">
                    <span class="currency">TSh</span> <?= number_format($net_profit, 0) ?>
                </p>
                <p class="card-sub">
                    <span class="badge-mini <?= $net_profit >= 0 ? 'green' : 'red' ?>">
                        <i class="fas <?= $net_profit >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i>
                        <?= number_format($profit_margin, 1) ?>% margin
                    </span>
                </p>
            </div>
            <div class="card-footer">
                <span class="card-nav-arrow">
                    <span class="arrow-text">View Details</span>
                    <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </a>

        <!-- 4. PHARMACY REVENUE -->
        <a href="pharmacy_revenue.php?branch=<?= $branch_id ?>" class="dashboard-card">
            <div class="card-header-bg purple">
                <div class="card-icon"><i class="fas fa-prescription-bottle"></i></div>
                <p class="card-label">Pharmacy Revenue <small>Medication + OTC</small></p>
            </div>
            <div class="card-body">
                <p class="card-amount purple"><span class="currency">TSh</span> <?= number_format($pharmacy_total, 0) ?></p>
                <p class="card-sub">
                    <span class="badge-mini purple">💊 <?= formatCurrency($medication_revenue) ?></span>
                    <span class="badge-mini orange">🛒 <?= formatCurrency($otc_revenue) ?></span>
                </p>
            </div>
            <div class="card-footer">
                <span class="card-nav-arrow">
                    <span class="arrow-text">View Details</span>
                    <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </a>

        <!-- 5. LAB REVENUE -->
        <a href="lab_revenue.php?branch=<?= $branch_id ?>" class="dashboard-card">
            <div class="card-header-bg teal">
                <div class="card-icon"><i class="fas fa-flask"></i></div>
                <p class="card-label">Lab Revenue <small>Completed tests</small></p>
            </div>
            <div class="card-body">
                <p class="card-amount teal"><span class="currency">TSh</span> <?= number_format($lab_revenue, 0) ?></p>
                <p class="card-sub">
                    <span class="badge-mini teal"><i class="fas fa-microscope"></i> <?= $lab_tests_count ?> tests</span>
                </p>
            </div>
            <div class="card-footer">
                <span class="card-nav-arrow">
                    <span class="arrow-text">View Details</span>
                    <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </a>

        <!-- 6. PROCEDURES & TOOLS -->
        <a href="procedures_tools_revenue.php?branch=<?= $branch_id ?>" class="dashboard-card">
            <div class="card-header-bg indigo">
                <div class="card-icon"><i class="fas fa-toolbox"></i></div>
                <p class="card-label">Procedures & Tools <small>Combined</small></p>
            </div>
            <div class="card-body">
                <p class="card-amount indigo"><span class="currency">TSh</span> <?= number_format($procedures_tools_total, 0) ?></p>
                <p class="card-sub">
                    <span class="badge-mini pink">🔧 <?= formatCurrency($procedures_revenue) ?></span>
                    <span class="badge-mini orange">🛠️ <?= formatCurrency($tools_revenue) ?></span>
                </p>
            </div>
            <div class="card-footer">
                <span class="card-nav-arrow">
                    <span class="arrow-text">View Details</span>
                    <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </a>

        <!-- 7. CONSULTATION REVENUE -->
        <a href="consultation_revenue.php?branch=<?= $branch_id ?>" class="dashboard-card">
            <div class="card-header-bg blue">
                <div class="card-icon"><i class="fas fa-stethoscope"></i></div>
                <p class="card-label">Consultation <small>Doctor visits</small></p>
            </div>
            <div class="card-body">
                <p class="card-amount blue"><span class="currency">TSh</span> <?= number_format($consultation_revenue, 0) ?></p>
                <p class="card-sub">
                    <span class="badge-mini blue">🩺 Consultation fees</span>
                </p>
            </div>
            <div class="card-footer">
                <span class="card-nav-arrow">
                    <span class="arrow-text">View Details</span>
                    <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </a>

        <!-- 8. BILLS OVERVIEW -->
        <a href="bills.php?branch=<?= $branch_id ?>" class="dashboard-card">
            <div class="card-header-bg orange">
                <div class="card-icon"><i class="fas fa-file-invoice"></i></div>
                <p class="card-label">Bills Overview <small>Total bills</small></p>
            </div>
            <div class="card-body">
                <p class="card-amount orange"><span class="currency">TSh</span> <?= number_format($grand_total_revenue, 0) ?></p>
                <p class="card-sub">
                    <span class="badge-mini green">✅ <?= $branch['paid_bills'] ?? 0 ?> paid</span>
                    <span class="badge-mini orange">⏳ <?= $branch['pending_bills'] ?? 0 ?> pending</span>
                    <span class="badge-mini red">⚠️ <?= $branch['partial_bills'] ?? 0 ?> partial</span>
                </p>
            </div>
            <div class="card-footer">
                <span class="card-nav-arrow">
                    <span class="arrow-text">View Details</span>
                    <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </a>

    </div>

    <!-- ================================================================
         CHARTS: REVENUE & EXPENSES
         ================================================================ -->
    <div class="chart-grid animate-fade-in-up" style="animation-delay:0.45s;">

        <div class="table-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-line"></i>
                    Weekly Revenue (Last 7 Days)
                </h3>
                <a href="revenue_reports.php?branch=<?= $branch_id ?>" class="card-action">View Reports →</a>
            </div>
            <div class="card-body" style="padding:16px 20px;">
                <div class="chart-container">
                    <canvas id="weeklyRevenueChart"></canvas>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-line"></i>
                    Weekly Expenses (Last 7 Days)
                </h3>
                <a href="expense_reports.php?branch=<?= $branch_id ?>" class="card-action">View Reports →</a>
            </div>
            <div class="card-body" style="padding:16px 20px;">
                <div class="chart-container">
                    <canvas id="weeklyExpensesChart"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- ================================================================
         RECENT PAYMENTS
         ================================================================ -->
    <div class="table-card animate-fade-in-up" style="animation-delay:0.5s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-credit-card"></i>
                Recent Payments (<?= count($recent_payments) ?>)
            </h3>
            <a href="payments.php?branch=<?= $branch_id ?>" class="card-action">View All →</a>
        </div>
        <?php if (count($recent_payments) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Bill #</th>
                            <th>Patient</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Received By</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_payments as $payment): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?></td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($payment['bill_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($payment['patient_name'] ?? 'N/A') ?></td>
                                <td class="font-semibold text-green-600"><?= formatCurrency($payment['amount'] ?? 0) ?></td>
                                <td>
                                    <span class="badge badge-info" style="font-size:0.6rem;padding:2px 10px;">
                                        <?= ucfirst($payment['payment_method'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= date('M d, Y h:i A', strtotime($payment['received_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_payment.php?id=<?= $payment['id'] ?>&branch=<?= $branch_id ?>" class="text-green-600 text-xs hover:underline font-semibold">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-credit-card"></i>
                <p>No payments found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================
         RECENT EXPENSES
         ================================================================ -->
    <div class="table-card animate-fade-in-up" style="animation-delay:0.55s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-invoice-dollar"></i>
                Recent Expenses (<?= count($recent_expenses) ?>)
            </h3>
            <a href="expenses.php?branch=<?= $branch_id ?>" class="card-action">View All →</a>
        </div>
        <?php if (count($recent_expenses) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Expense #</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_expenses as $expense): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($expense['expense_number'] ?? 'N/A') ?></td>
                                <td><span class="badge badge-secondary" style="font-size:0.6rem;padding:2px 10px;"><?= htmlspecialchars($expense['category'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars(substr($expense['description'] ?? 'N/A', 0, 40)) ?></td>
                                <td class="font-semibold text-red-600"><?= formatCurrency($expense['amount'] ?? 0) ?></td>
                                <td>
                                    <span class="badge badge-info" style="font-size:0.6rem;padding:2px 10px;">
                                        <?= ucfirst($expense['payment_method'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($expense['status'] ?? 'pending') ?>" style="font-size:0.6rem;padding:2px 10px;">
                                        <i class="fas <?= getStatusIcon($expense['status'] ?? 'pending') ?>"></i>
                                        <?= ucfirst($expense['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($expense['payment_date'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_expense.php?id=<?= $expense['id'] ?>&branch=<?= $branch_id ?>" class="text-green-600 text-xs hover:underline font-semibold">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-invoice-dollar"></i>
                <p>No expenses found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================
         RECENT BILLS
         ================================================================ -->
    <div class="table-card animate-fade-in-up" style="animation-delay:0.6s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-invoice"></i>
                Recent Bills (<?= count($recent_bills) ?>)
            </h3>
            <a href="bills.php?branch=<?= $branch_id ?>" class="card-action">View All →</a>
        </div>
        <?php if (count($recent_bills) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Patient</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_bills as $bill): 
                            $balance = (float)$bill['balance'];
                        ?>
                            <tr>
                                <td class="font-mono text-xs font-semibold text-green-600">
                                    <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                                </td>
                                <td><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></td>
                                <td class="font-semibold"><?= formatCurrency($bill['total_amount'] ?? 0) ?></td>
                                <td class="text-green-600"><?= formatCurrency($bill['paid_amount'] ?? 0) ?></td>
                                <td>
                                    <?php if ($balance > 0): ?>
                                        <span class="text-red-600 font-semibold"><?= formatCurrency($balance) ?></span>
                                    <?php else: ?>
                                        <span class="text-green-600"><?= formatCurrency(0) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($bill['status'] ?? 'pending') ?>">
                                        <i class="fas <?= getStatusIcon($bill['status'] ?? 'pending') ?>"></i>
                                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($bill['created_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="bill_details.php?id=<?= $bill['id'] ?>&branch=<?= $branch_id ?>" class="text-green-600 text-xs hover:underline font-semibold">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-invoice"></i>
                <p>No bills found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================
         CASHIERS
         ================================================================ -->
    <div class="table-card animate-fade-in-up" style="animation-delay:0.65s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user-tie"></i>
                Cashiers (<?= count($cashiers) ?>)
            </h3>
            <a href="add_employee.php?branch=<?= $branch_id ?>&role=cashier" class="card-action">
                <i class="fas fa-plus"></i> Add Cashier
            </a>
        </div>
        <?php if (count($cashiers) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cashiers as $cashier_user): ?>
                            <tr>
                                <td class="font-semibold"><?= htmlspecialchars($cashier_user['full_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($cashier_user['email'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($cashier_user['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-<?= $cashier_user['status'] === 'active' ? 'success' : 'danger' ?>" style="font-size:0.65rem;padding:2px 12px;">
                                        <?= ucfirst($cashier_user['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_employee.php?id=<?= $cashier_user['id'] ?>&branch=<?= $branch_id ?>" class="text-green-600 text-xs hover:underline font-semibold">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-tie"></i>
                <p>No cashiers assigned to this branch</p>
                <a href="add_employee.php?branch=<?= $branch_id ?>&role=cashier" style="color:var(--page-primary);font-size:0.85rem;text-decoration:none;font-weight:600;margin-top:8px;display:inline-block;">Add Cashier</a>
            </div>
        <?php endif; ?>
    </div>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-modern" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.2rem;"></i>
    <div>
        <p style="font-weight:700;font-size:0.85rem;margin:0 0 2px 0;" id="toastTitle">Notification</p>
        <p style="font-size:0.78rem;opacity:0.95;margin:0;line-height:1.4;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
            if (body) body.style.background = '#F0FDF4';
            if (mainContent) mainContent.style.background = '#F0FDF4';
        }
    }

    enforceDarkModeBackground();
    document.addEventListener('darkModeChanged', function() { setTimeout(enforceDarkModeBackground, 50); });
    new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            if (m.attributeName === 'data-theme') enforceDarkModeBackground();
        });
    }).observe(document.documentElement, { attributes: true });

    // ================================================================
    // CHARTS
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.05)';
        var tickColor = isDark ? '#94A3B8' : '#64748B';

        // Revenue Chart
        var revenueCtx = document.getElementById('weeklyRevenueChart');
        if (revenueCtx) {
            var labels = <?= json_encode(array_map(function($d) { return date('D', strtotime($d)); }, array_map(function($i) { return date('Y-m-d', strtotime("-$i days")); }, range(6, 0)))) ?>;
            var revenueData = <?php 
                $week_revenue_data = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $found = false;
                    foreach ($weekly_revenue as $w) {
                        if ($w['date'] == $date) {
                            $week_revenue_data[] = (float)$w['total'];
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) $week_revenue_data[] = 0;
                }
                echo json_encode($week_revenue_data);
            ?>;

            new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenue (TSh)',
                        data: revenueData,
                        backgroundColor: 'rgba(5, 150, 105, 0.75)',
                        borderColor: 'rgba(5, 150, 105, 1)',
                        borderWidth: 2,
                        borderRadius: 8,
                        barPercentage: 0.6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: isDark ? '#1E293B' : 'white',
                            titleColor: isDark ? '#F1F5F9' : '#1E293B',
                            bodyColor: isDark ? '#94A3B8' : '#64748B',
                            borderColor: '#059669',
                            borderWidth: 1,
                            callbacks: {
                                label: function(context) {
                                    return 'TSh ' + context.raw.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: tickColor,
                                callback: function(value) { return 'TSh ' + value.toLocaleString(); }
                            },
                            grid: { color: gridColor }
                        },
                        x: { 
                            ticks: { color: tickColor },
                            grid: { display: false } 
                        }
                    }
                }
            });
        }

        // Expenses Chart
        var expensesCtx = document.getElementById('weeklyExpensesChart');
        if (expensesCtx) {
            var labels2 = <?= json_encode(array_map(function($d) { return date('D', strtotime($d)); }, array_map(function($i) { return date('Y-m-d', strtotime("-$i days")); }, range(6, 0)))) ?>;
            var expensesData = <?php 
                $week_expense_data = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $found = false;
                    foreach ($weekly_expenses as $w) {
                        if ($w['date'] == $date) {
                            $week_expense_data[] = (float)$w['total'];
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) $week_expense_data[] = 0;
                }
                echo json_encode($week_expense_data);
            ?>;

            new Chart(expensesCtx, {
                type: 'bar',
                data: {
                    labels: labels2,
                    datasets: [{
                        label: 'Expenses (TSh)',
                        data: expensesData,
                        backgroundColor: 'rgba(220, 38, 38, 0.75)',
                        borderColor: 'rgba(220, 38, 38, 1)',
                        borderWidth: 2,
                        borderRadius: 8,
                        barPercentage: 0.6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: isDark ? '#1E293B' : 'white',
                            titleColor: isDark ? '#F1F5F9' : '#1E293B',
                            bodyColor: isDark ? '#94A3B8' : '#64748B',
                            borderColor: '#DC2626',
                            borderWidth: 1,
                            callbacks: {
                                label: function(context) {
                                    return 'TSh ' + context.raw.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: tickColor,
                                callback: function(value) { return 'TSh ' + value.toLocaleString(); }
                            },
                            grid: { color: gridColor }
                        },
                        x: { 
                            ticks: { color: tickColor },
                            grid: { display: false } 
                        }
                    }
                }
            });
        }
    });

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-modern ' + type;
        toastTitle.textContent = title;
        toastMessage.innerHTML = message;
        toast.style.display = 'flex';
        void toast.offsetWidth;
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    console.log('%c💰 Braick - Cashier Dashboard (ADMIN)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🎨 Green page header + 8 gradient cards + 2 charts', 'font-size:13px; color:#059669;');
    console.log('%c🌙 FULL DARK MODE works', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>