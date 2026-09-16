<?php
// ================================================================
// FILE: frontend/pages/admin/bills.php
// ADMIN - VIEW ALL BILLS (WITH PREMIUM COLUMN)
// ✅ Uses SHARED header & sidebar
// ✅ Beautiful stat cards + premium column
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

$user_id = $_SESSION['user_id'] ?? 0;
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
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// FILTERS
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 1;
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// ================================================================
// BRANCHES
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// BUILD WHERE
// ================================================================
$where_conditions = [];
$params = [];

$where_conditions[] = "b.bill_number NOT LIKE 'BILL-OTC-%'";

if ($selected_branch_id > 0) {
    $where_conditions[] = "b.branch_id = ?";
    $params[] = $selected_branch_id;
}

if ($status_filter !== 'all' && !empty($status_filter)) {
    $where_conditions[] = "b.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(b.bill_number LIKE ? OR p.full_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_from) && !empty($date_to)) {
    $where_conditions[] = "DATE(b.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
} elseif (!empty($date_from)) {
    $where_conditions[] = "DATE(b.created_at) >= ?";
    $params[] = $date_from;
} elseif (!empty($date_to)) {
    $where_conditions[] = "DATE(b.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = "";
if (count($where_conditions) > 0) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// ================================================================
// GET BILLS
// ================================================================
$sql = "
    SELECT 
        b.id, b.bill_number, b.patient_id, b.visit_id, b.branch_id, b.created_by,
        b.subtotal, b.discount_percent, b.discount_amount, b.total_discount,
        b.total_amount, b.paid_amount, b.balance,
        b.premium_amount, b.premium_note,
        b.status, b.payment_method, b.notes, b.created_at, b.updated_at,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone as patient_phone,
        u.full_name as cashier_name,
        br.name as branch_name
    FROM bills b
    LEFT JOIN patients p ON b.patient_id = p.id
    LEFT JOIN users u ON b.created_by = u.id
    LEFT JOIN branches br ON b.branch_id = br.id
    $where_clause
    ORDER BY b.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// SUMMARY STATS
// ================================================================
function countBills($db, $status, $branch_id) {
    $sql = "SELECT COUNT(*) as total FROM bills b WHERE b.bill_number NOT LIKE 'BILL-OTC-%'";
    $params = [];
    if ($status !== null) {
        $sql .= " AND b.status = ?";
        $params[] = $status;
    }
    if ($branch_id > 0) {
        $sql .= " AND b.branch_id = ?";
        $params[] = $branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
}

$total_bills = countBills($db, null, $selected_branch_id);
$paid_bills = countBills($db, 'paid', $selected_branch_id);
$pending_bills = countBills($db, 'pending', $selected_branch_id);
$partial_bills = countBills($db, 'partial', $selected_branch_id);
$cancelled_bills = countBills($db, 'cancelled', $selected_branch_id);

$total_revenue = 0;
$stmt = $db->prepare("SELECT COALESCE(SUM(b.paid_amount), 0) as total FROM bills b WHERE b.status = 'paid' AND b.bill_number NOT LIKE 'BILL-OTC-%'" . ($selected_branch_id > 0 ? " AND b.branch_id = ?" : ""));
$stmt->execute($selected_branch_id > 0 ? [$selected_branch_id] : []);
$total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$total_paid_amount = 0;
$stmt = $db->prepare("SELECT COALESCE(SUM(b.paid_amount), 0) as total FROM bills b WHERE b.status = 'paid' AND b.bill_number NOT LIKE 'BILL-OTC-%'" . ($selected_branch_id > 0 ? " AND b.branch_id = ?" : ""));
$stmt->execute($selected_branch_id > 0 ? [$selected_branch_id] : []);
$total_paid_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$total_balance = 0;
$stmt = $db->prepare("SELECT COALESCE(SUM(b.balance), 0) as total FROM bills b WHERE b.bill_number NOT LIKE 'BILL-OTC-%'" . ($selected_branch_id > 0 ? " AND b.branch_id = ?" : ""));
$stmt->execute($selected_branch_id > 0 ? [$selected_branch_id] : []);
$total_balance = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$total_premium = 0;
$stmt = $db->prepare("SELECT COALESCE(SUM(b.premium_amount), 0) as total FROM bills b WHERE b.bill_number NOT LIKE 'BILL-OTC-%' AND b.premium_amount > 0" . ($selected_branch_id > 0 ? " AND b.branch_id = ?" : ""));
$stmt->execute($selected_branch_id > 0 ? [$selected_branch_id] : []);
$total_premium = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$total_discount = 0;
$stmt = $db->prepare("SELECT COALESCE(SUM(b.total_discount), 0) as total FROM bills b WHERE b.bill_number NOT LIKE 'BILL-OTC-%'" . ($selected_branch_id > 0 ? " AND b.branch_id = ?" : ""));
$stmt->execute($selected_branch_id > 0 ? [$selected_branch_id] : []);
$total_discount = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$bills_with_premium = 0;
$stmt = $db->prepare("SELECT COUNT(*) as total FROM bills b WHERE b.bill_number NOT LIKE 'BILL-OTC-%' AND b.premium_amount > 0" . ($selected_branch_id > 0 ? " AND b.branch_id = ?" : ""));
$stmt->execute($selected_branch_id > 0 ? [$selected_branch_id] : []);
$bills_with_premium = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$summary = [
    'total_bills' => $total_bills,
    'paid_bills' => $paid_bills,
    'pending_bills' => $pending_bills,
    'partial_bills' => $partial_bills,
    'cancelled_bills' => $cancelled_bills,
    'total_revenue' => $total_revenue,
    'total_paid' => $total_paid_amount,
    'total_balance' => $total_balance,
    'total_premium' => $total_premium,
    'total_discount' => $total_discount,
    'bills_with_premium' => $bills_with_premium
];

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function formatMoney($amount) {
    if ($amount === null || $amount === '') return '0';
    return number_format((float)$amount, 0, '.', ',');
}

function getStatusBadge($status) {
    $badges = [
        'paid' => '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Paid</span>',
        'pending' => '<span class="badge badge-warning"><i class="fas fa-clock"></i> Pending</span>',
        'partial' => '<span class="badge badge-info"><i class="fas fa-hourglass-half"></i> Partial</span>',
        'cancelled' => '<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Cancelled</span>'
    ];
    return $badges[$status] ?? '<span class="badge badge-secondary">' . ucfirst($status) . '</span>';
}

function getPaymentMethodLabel($method) {
    $map = [
        'cash' => '💰 Cash',
        'm-pesa' => '📱 M-Pesa',
        'airtel_money' => '📱 Airtel Money',
        'tigo_pesa' => '📱 Tigo Pesa',
        'halopesa' => '📱 Halo Pesa',
        'bank' => '🏦 Bank',
        'card' => '💳 Card',
        'insurance' => '🏥 Insurance',
        'other' => '📦 Other'
    ];
    return $map[$method] ?? ucfirst($method);
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $unread_notifications = 0; }

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================
     PAGE-SPECIFIC CSS
     ================================================================ -->
<style>
    :root {
        --page-primary: #0B5ED7;
        --page-primary-dark: #0A4CA8;
        --page-primary-bg: #E8F0FE;
        --page-primary-light: #6EA8FE;
        --page-success: #059669;
        --page-success-bg: #D1FAE5;
        --page-danger: #DC2626;
        --page-danger-bg: #FEE2E2;
        --page-warning: #D97706;
        --page-warning-bg: #FEF3C7;
        --page-purple: #7C3AED;
        --page-purple-bg: #EDE9FE;
        --page-cyan: #0891B2;
        --page-bg-body: #F1F5F9;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-border: #E2E8F0;
        --page-table-hover: #E8F0FE;
        --page-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --page-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --page-shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-border: #334155;
        --page-table-hover: #1E3A5F;
        --page-primary-bg: #1E3A5F;
        --page-success-bg: #1A3A2A;
        --page-danger-bg: #3A1A1A;
        --page-warning-bg: #3D2E0A;
        --page-purple-bg: #2D1B4E;
        --page-shadow-sm: 0 1px 2px rgba(0,0,0,0.3);
        --page-shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --page-shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
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
        margin-bottom: 20px;
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

    .page-header-subtitle {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.9);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

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

    .header-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 20px;
        font-size: 0.7rem;
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
       STAT CARDS
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }

    .stat-card {
        border-radius: 16px;
        padding: 18px 20px;
        color: white;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-md);
        cursor: default;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: -40%; right: -20%;
        width: 180px; height: 180px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 32px rgba(0,0,0,0.15);
    }

    .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
    .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    .stat-card.red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    .stat-card.cyan { background: linear-gradient(135deg, #0891B2, #0E7490); }
    .stat-card.gold {
        background: linear-gradient(135deg, #D97706, #92400E);
        animation: premiumPulse 2.5s ease-in-out infinite;
    }
    .stat-card.yellow { background: linear-gradient(135deg, #F59E0B, #D97706); }

    @keyframes premiumPulse {
        0%, 100% { box-shadow: 0 8px 24px rgba(217, 119, 6, 0.3); }
        50% { box-shadow: 0 8px 32px rgba(217, 119, 6, 0.55); }
    }

    .stat-icon {
        font-size: 1.4rem;
        margin-bottom: 6px;
        opacity: 0.95;
        position: relative;
        z-index: 1;
    }

    .stat-number {
        font-size: 1.35rem;
        font-weight: 800;
        color: white;
        position: relative;
        z-index: 1;
        line-height: 1.2;
        word-break: break-word;
    }

    .stat-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 700;
        margin-top: 4px;
        color: rgba(255,255,255,0.9);
        position: relative;
        z-index: 1;
    }

    .stat-sub {
        font-size: 0.6rem;
        color: rgba(255,255,255,0.75);
        margin-top: 2px;
        font-weight: 500;
    }

    /* ================================================================
       FILTER SECTION
       ================================================================ */
    .filter-card {
        background: var(--page-bg-card);
        border-radius: 16px;
        padding: 16px 20px;
        border: 1px solid var(--page-border);
        margin-bottom: 20px;
        box-shadow: var(--page-shadow-sm);
    }

    .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }

    .filter-input, .filter-select {
        padding: 8px 14px;
        border: 2px solid var(--page-border);
        border-radius: 10px;
        font-size: 0.8rem;
        background: var(--page-bg-card);
        color: var(--page-text-primary);
        outline: none;
        transition: all 0.3s ease;
        min-width: 150px;
        font-family: inherit;
    }

    .filter-input:focus, .filter-select:focus {
        border-color: var(--page-primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }

    .btn-search {
        padding: 8px 20px;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
    }

    .btn-search:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(11, 94, 215, 0.35);
    }

    .btn-reset {
        padding: 8px 16px;
        border-radius: 10px;
        font-size: 0.75rem;
        font-weight: 600;
        border: 2px solid var(--page-border);
        background: transparent;
        color: var(--page-text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-reset:hover {
        border-color: var(--page-danger);
        color: var(--page-danger);
    }

    /* ================================================================
       TABLE CARD
       ================================================================ */
    .table-card {
        background: var(--page-bg-card);
        border-radius: 16px;
        border: 1px solid var(--page-border);
        overflow: hidden;
        box-shadow: var(--page-shadow-md);
        margin-bottom: 20px;
    }

    .table-card-header {
        padding: 14px 20px;
        border-bottom: 1px solid var(--page-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        background: var(--page-bg-body);
    }

    html[data-theme="dark"] .table-card-header { background: #0F172A; }

    .table-card-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--page-text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .table-card-title i { color: var(--page-primary); }

    .table-scroll { overflow-x: auto; }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.75rem;
    }

    .data-table thead th {
        text-align: left;
        padding: 12px 14px;
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #ffffff;
        background: #0B5ED7;
        border-bottom: 3px solid #0A4CA8;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 5;
    }

    .data-table tbody td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--page-border);
        color: var(--page-text-primary);
        vertical-align: middle;
    }

    .data-table tbody tr { transition: background 0.2s ease; }
    .data-table tbody tr:hover td { background: var(--page-table-hover); }
    .data-table tbody tr:last-child td { border-bottom: none; }

    /* Premium rows highlighted */
    .data-table tbody tr.has-premium {
        background: rgba(254, 243, 199, 0.35);
        border-left: 3px solid #D97706;
    }

    html[data-theme="dark"] .data-table tbody tr.has-premium {
        background: rgba(61, 46, 10, 0.4);
        border-left-color: #FCD34D;
    }

    /* Badges */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.62rem;
        font-weight: 700;
        color: white;
        letter-spacing: 0.02em;
    }

    .badge-success { background: #059669; }
    .badge-danger { background: #DC2626; }
    .badge-warning { background: #D97706; color: #1E293B; }
    .badge-info { background: #0B5ED7; }
    .badge-secondary { background: #64748B; }

    html[data-theme="dark"] .badge-warning { color: #1E293B; }

    /* Premium badge */
    .premium-badge {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 0.55rem;
        font-weight: 800;
        padding: 2px 8px;
        border-radius: 10px;
        background: #FEF3C7;
        color: #D97706;
        border: 1px solid #D97706;
    }

    html[data-theme="dark"] .premium-badge {
        background: #3D2E0A;
        color: #FCD34D;
        border-color: #D97706;
    }

    .premium-amount {
        color: #D97706;
        font-weight: 800;
        font-family: 'Courier New', monospace;
    }

    html[data-theme="dark"] .premium-amount { color: #FCD34D; }

    .discount-amount {
        color: #D97706;
        font-weight: 700;
        font-family: 'Courier New', monospace;
    }

    html[data-theme="dark"] .discount-amount { color: #FBBF24; }

    .money-blue { color: #0B5ED7; font-weight: 700; }
    .money-green { color: #059669; font-weight: 700; }
    .money-red { color: #DC2626; font-weight: 700; }

    html[data-theme="dark"] .money-blue { color: #6EA8FE; }
    html[data-theme="dark"] .money-green { color: #34D399; }
    html[data-theme="dark"] .money-red { color: #F87171; }

    .action-link {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 0.65rem;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.2s ease;
        border: 1px solid transparent;
    }

    .action-link.view {
        background: #E8F0FE;
        color: #0B5ED7;
    }

    html[data-theme="dark"] .action-link.view {
        background: #1E3A5F;
        color: #6EA8FE;
    }

    .action-link.print {
        background: #D1FAE5;
        color: #059669;
    }

    html[data-theme="dark"] .action-link.print {
        background: #1A3A2A;
        color: #34D399;
    }

    .action-link:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    /* Empty state */
    .empty-state {
        padding: 50px 20px;
        text-align: center;
        color: var(--page-text-secondary);
    }

    .empty-state i {
        font-size: 3rem;
        color: var(--page-border);
        display: block;
        margin-bottom: 12px;
    }

    .empty-state p {
        font-size: 0.95rem;
        font-weight: 600;
        margin: 0 0 4px 0;
    }

    .empty-state small {
        font-size: 0.75rem;
        opacity: 0.8;
    }

    /* Table footer */
    .table-footer {
        padding: 12px 20px;
        border-top: 1px solid var(--page-border);
        font-size: 0.72rem;
        color: var(--page-text-secondary);
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        background: var(--page-bg-body);
    }

    html[data-theme="dark"] .table-footer { background: #0F172A; }

    .table-footer strong { color: var(--page-text-primary); }

    /* Utility */
    .font-mono { font-family: 'Courier New', monospace; }
    .font-semibold { font-weight: 600; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .text-xs { font-size: 0.65rem; }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up {
        animation: fadeInUp 0.4s ease forwards;
        opacity: 0;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .page-header-card { padding: 20px; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-card { padding: 18px 20px; }
        .page-header-title { font-size: 1.2rem; }
        .page-header-title i { width: 36px; height: 36px; font-size: 1rem; }
        .filter-row { flex-direction: column; align-items: stretch; }
        .filter-input, .filter-select { width: 100%; min-width: unset; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .stat-card { padding: 14px 16px; }
        .stat-number { font-size: 1.1rem; }
        .data-table { font-size: 0.65rem; }
        .data-table thead th, .data-table td { padding: 8px 10px; }
    }

    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
        .data-table { font-size: 0.6rem; }
        .data-table thead th, .data-table td { padding: 6px 8px; }
        .data-table thead th { font-size: 0.55rem; }
    }

    /* Print */
    @media print {
        .page-header-actions, .btn, .btn-outline-light, .filter-card, .table-footer { display: none !important; }
        .main-content { padding: 0 !important; }
        .page-header-card { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .stat-card, .badge, .premium-badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .data-table thead th { background: #0B5ED7 !important; color: white !important; -webkit-print-color-adjust: exact; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Blue Page Header Card -->
    <div class="page-header-card animate-fade-in-up">
        <div>
            <h1 class="page-header-title">
                <i class="fas fa-file-invoice"></i>
                Bills
                <span class="role-badge-display">ADMIN</span>
                <?php if (($summary['total_premium'] ?? 0) > 0): ?>
                    <span class="role-badge-display" style="background:rgba(251,191,36,0.35);color:#FCD34D;">
                        <i class="fas fa-crown"></i> Premium: TSh <?= formatMoney($summary['total_premium']) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-header-subtitle">
                <i class="fas fa-chart-bar"></i>
                <strong><?= number_format($summary['total_bills'] ?? 0) ?></strong> total bills
                <span class="header-badge">
                    <i class="fas fa-check-circle" style="color:#34D399;"></i>
                    <?= number_format($summary['paid_bills'] ?? 0) ?> Paid
                </span>
                <span class="header-badge">
                    <i class="fas fa-clock" style="color:#FBBF24;"></i>
                    <?= number_format($summary['pending_bills'] ?? 0) ?> Pending
                </span>
                <span class="header-badge">
                    <i class="fas fa-hourglass-half" style="color:#60A5FA;"></i>
                    <?= number_format($summary['partial_bills'] ?? 0) ?> Partial
                </span>
                <span class="header-badge">
                    <i class="fas fa-times-circle" style="color:#F87171;"></i>
                    <?= number_format($summary['cancelled_bills'] ?? 0) ?> Cancelled
                </span>
                <?php if (($summary['bills_with_premium'] ?? 0) > 0): ?>
                    <span class="header-badge" style="background:rgba(251,191,36,0.35);">
                        <i class="fas fa-crown" style="color:#FCD34D;"></i>
                        <?= number_format($summary['bills_with_premium']) ?> With Premium
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="page-header-actions">
            <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-chart-line"></i> Revenue
            </a>
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="stats-grid animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-number"><?= number_format($summary['total_bills'] ?? 0) ?></div>
            <div class="stat-label">Total Bills</div>
        </div>

        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?= number_format($summary['paid_bills'] ?? 0) ?></div>
            <div class="stat-label">Paid</div>
        </div>

        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number"><?= number_format($summary['pending_bills'] ?? 0) ?></div>
            <div class="stat-label">Pending</div>
        </div>

        <div class="stat-card purple">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-number"><?= number_format($summary['partial_bills'] ?? 0) ?></div>
            <div class="stat-label">Partial</div>
        </div>

        <div class="stat-card red">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-number"><?= number_format($summary['cancelled_bills'] ?? 0) ?></div>
            <div class="stat-label">Cancelled</div>
        </div>

        <div class="stat-card cyan">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-number" style="font-size:1.05rem;">TSh <?= formatMoney($summary['total_revenue'] ?? 0) ?></div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-sub">From paid bills only</div>
        </div>

        <div class="stat-card gold">
            <div class="stat-icon"><i class="fas fa-crown"></i></div>
            <div class="stat-number" style="font-size:1.05rem;">TSh <?= formatMoney($summary['total_premium'] ?? 0) ?></div>
            <div class="stat-label">👑 Total Premium</div>
            <div class="stat-sub"><?= number_format($summary['bills_with_premium'] ?? 0) ?> bills with premium</div>
        </div>

        <div class="stat-card yellow">
            <div class="stat-icon"><i class="fas fa-tag"></i></div>
            <div class="stat-number" style="font-size:1.05rem;">TSh <?= formatMoney($summary['total_discount'] ?? 0) ?></div>
            <div class="stat-label">🏷️ Total Discount</div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card animate-fade-in-up" style="animation-delay:0.1s;">
        <form method="GET" action="">
            <div class="filter-row">
                <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">

                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>✅ Paid</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                    <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>🔄 Partial</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                </select>

                <input type="text" name="search" class="filter-input" placeholder="Search bill #, patient..." value="<?= htmlspecialchars($search) ?>">

                <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($date_from) ?>">
                <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($date_to) ?>">

                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Filter
                </button>

                <a href="bills.php?branch=<?= $selected_branch_id ?>" class="btn-reset">
                    <i class="fas fa-times"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Bills Table -->
    <div class="table-card animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="table-card-header">
            <h3 class="table-card-title">
                <i class="fas fa-list"></i>
                Bills List
                <span style="font-weight:400;font-size:0.72rem;color:var(--page-text-secondary);">
                    (<?= number_format(count($bills)) ?> bills)
                </span>
            </h3>
            <span style="font-size:0.68rem;color:var(--page-text-secondary);">
                Revenue: <strong style="color:#059669;">TSh <?= formatMoney($summary['total_revenue'] ?? 0) ?></strong>
                <?php if (($summary['total_premium'] ?? 0) > 0): ?>
                    | 👑 Premium: <strong style="color:#D97706;">TSh <?= formatMoney($summary['total_premium']) ?></strong>
                <?php endif; ?>
            </span>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Patient</th>
                        <th>Branch</th>
                        <th style="text-align:right;">Subtotal</th>
                        <th style="text-align:right;">Discount</th>
                        <th style="text-align:right;">👑 Premium</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:right;">Paid</th>
                        <th style="text-align:right;">Balance</th>
                        <th>Status</th>
                        <th>Method</th>
                        <th>Date</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($bills) > 0): ?>
                        <?php foreach ($bills as $bill): 
                            $balance = (float)($bill['balance'] ?? 0);
                            $total = (float)($bill['total_amount'] ?? 0);
                            $paid = (float)($bill['paid_amount'] ?? 0);
                            $subtotal = (float)($bill['subtotal'] ?? 0);
                            $discount = (float)($bill['total_discount'] ?? 0);
                            $premium = (float)($bill['premium_amount'] ?? 0);
                            $premium_note = $bill['premium_note'] ?? '';
                            $has_premium = ($premium > 0);
                            $has_discount = ($discount > 0);
                            $row_class = $has_premium ? 'has-premium' : '';
                        ?>
                            <tr class="<?= $row_class ?>">
                                <td class="font-mono font-semibold" style="font-size:0.68rem;color:#0B5ED7;">
                                    <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                                    <?php if ($has_premium): ?>
                                        <span class="premium-badge" style="margin-left:4px;">
                                            <i class="fas fa-crown"></i> Premium
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($bill['patient_name'])): ?>
                                        <div style="font-weight:600;"><?= htmlspecialchars($bill['patient_name']) ?></div>
                                        <div style="font-size:0.6rem;color:var(--page-text-secondary);">
                                            <?= htmlspecialchars($bill['patient_code'] ?? '') ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--page-text-secondary);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.7rem;"><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></td>

                                <!-- Subtotal -->
                                <td class="text-right font-semibold" style="font-size:0.72rem;">
                                    TSh <?= formatMoney($subtotal) ?>
                                </td>

                                <!-- Discount -->
                                <td class="text-right" style="font-size:0.72rem;">
                                    <?php if ($has_discount): ?>
                                        <span class="discount-amount">- TSh <?= formatMoney($discount) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--page-text-secondary);">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Premium -->
                                <td class="text-right" style="font-size:0.72rem;">
                                    <?php if ($has_premium): ?>
                                        <span class="premium-amount">+ TSh <?= formatMoney($premium) ?></span>
                                        <?php if (!empty($premium_note)): ?>
                                            <div style="font-size:0.55rem;color:var(--page-text-secondary);font-weight:400;margin-top:2px;">
                                                <?= htmlspecialchars(substr($premium_note, 0, 20)) ?><?= strlen($premium_note) > 20 ? '...' : '' ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--page-text-secondary);">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Total -->
                                <td class="text-right money-blue" style="font-size:0.78rem;">
                                    TSh <?= formatMoney($total) ?>
                                </td>

                                <!-- Paid -->
                                <td class="text-right money-green" style="font-size:0.72rem;">
                                    TSh <?= formatMoney($paid) ?>
                                </td>

                                <!-- Balance -->
                                <td class="text-right <?= $balance > 0 ? 'money-red' : 'money-green' ?>" style="font-size:0.72rem;">
                                    TSh <?= formatMoney($balance) ?>
                                </td>

                                <td><?= getStatusBadge($bill['status'] ?? 'pending') ?></td>
                                <td style="font-size:0.65rem;">
                                    <?php if (!empty($bill['payment_method'])): ?>
                                        <?= getPaymentMethodLabel($bill['payment_method']) ?>
                                    <?php else: ?>
                                        <span style="color:var(--page-text-secondary);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.65rem;white-space:nowrap;">
                                    <?= date('d/m/Y', strtotime($bill['created_at'])) ?>
                                </td>
                                <td style="text-align:center;white-space:nowrap;">
                                    <a href="view_bill.php?id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                       class="action-link view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($bill['status'] === 'paid'): ?>
                                        <a href="../cashier/print_receipt.php?bill_id=<?= $bill['id'] ?>&print=1" 
                                           target="_blank" class="action-link print" style="margin-left:4px;">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="13">
                                <div class="empty-state">
                                    <i class="fas fa-file-invoice"></i>
                                    <p>No bills found</p>
                                    <small>Try adjusting your filters</small>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>
                <i class="fas fa-list"></i> Showing <strong><?= number_format(count($bills)) ?></strong> bills
                <span class="text-xs">
                    &nbsp;|&nbsp; Revenue: <strong style="color:#059669;">TSh <?= formatMoney($summary['total_revenue'] ?? 0) ?></strong>
                    <?php if (($summary['total_premium'] ?? 0) > 0): ?>
                        &nbsp;|&nbsp; 👑 Premium: <strong style="color:#D97706;">TSh <?= formatMoney($summary['total_premium']) ?></strong>
                    <?php endif; ?>
                    <?php if (($summary['total_discount'] ?? 0) > 0): ?>
                        &nbsp;|&nbsp; 🏷️ Discount: <strong style="color:#D97706;">TSh <?= formatMoney($summary['total_discount']) ?></strong>
                    <?php endif; ?>
                </span>
            </span>
            <span class="text-xs" id="updateTimeDisplay">
                <i class="fas fa-clock"></i> <?= date('H:i:s') ?>
            </span>
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

    // ================================================================
    // UPDATE TIME DISPLAY
    // ================================================================
    function updateUpdateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('updateTimeDisplay');
        if (el) el.innerHTML = '<i class="fas fa-clock"></i> ' + timeStr;
    }
    updateUpdateTime();
    setInterval(updateUpdateTime, 1000);

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            var searchInput = document.querySelector('input[name="search"]');
            if (searchInput) searchInput.focus();
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var searchInput = document.querySelector('input[name="search"]');
            if (searchInput) searchInput.focus();
        }
    });

    console.log('%c📋 Braick - Bills (WITH PREMIUM)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🎨 Blue page header card + 8 stat cards', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👑 Premium column + badge + stats', 'font-size:13px; color:#D97706;');
    console.log('%c🌙 FULL DARK MODE works', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>