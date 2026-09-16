<?php
// ================================================================
// FILE: frontend/pages/admin/consultations.php
// ADMIN - VIEW CONSULTATION REVENUE
// ✅ Uses SHARED header & sidebar
// ✅ Beautiful green page header + 6 summary cards
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

$selected_branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 1;
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $branches = []; }

// ================================================================
// BUILD QUERY
// ================================================================
$where_conditions = [];
$params = [];

$where_conditions[] = "b.visit_id IS NOT NULL";
$where_conditions[] = "b.patient_id IS NOT NULL";
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

$where_clause = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";

// ================================================================
// FETCH CONSULTATIONS
// ================================================================
$sql = "
    SELECT 
        b.id, b.bill_number, b.patient_id, b.visit_id, b.branch_id, b.created_by,
        b.subtotal, b.discount_percent, b.discount_amount, b.total_discount,
        b.total_amount, b.paid_amount, b.balance, b.status, b.payment_method,
        b.notes, b.created_at, b.updated_at,
        p.full_name as patient_name, p.patient_id as patient_code, p.phone as patient_phone,
        u.full_name as cashier_name,
        br.name as branch_name,
        v.visit_type, v.consultation_fee, v.service_id
    FROM bills b
    LEFT JOIN patients p ON b.patient_id = p.id
    LEFT JOIN users u ON b.created_by = u.id
    LEFT JOIN branches br ON b.branch_id = br.id
    LEFT JOIN visits v ON b.visit_id = v.id
    $where_clause
    ORDER BY b.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// SUMMARY STATS
// ================================================================
function countConsultBills($db, $status, $branch_id) {
    $sql = "SELECT COUNT(*) as total FROM bills b WHERE b.visit_id IS NOT NULL AND b.patient_id IS NOT NULL AND b.bill_number NOT LIKE 'BILL-OTC-%'";
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

$total_bills = countConsultBills($db, null, $selected_branch_id);
$paid_bills = countConsultBills($db, 'paid', $selected_branch_id);
$pending_bills = countConsultBills($db, 'pending', $selected_branch_id);

// Total Revenue (paid only)
$stmt = $db->prepare("SELECT COALESCE(SUM(b.paid_amount), 0) as total FROM bills b WHERE b.status = 'paid' AND b.visit_id IS NOT NULL AND b.patient_id IS NOT NULL AND b.bill_number NOT LIKE 'BILL-OTC-%'" . ($selected_branch_id > 0 ? " AND b.branch_id = ?" : ""));
$stmt->execute($selected_branch_id > 0 ? [$selected_branch_id] : []);
$total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Consultation Fee Total (from visits)
$stmt = $db->prepare("SELECT COALESCE(SUM(v.consultation_fee), 0) as total FROM visits v INNER JOIN bills b ON b.visit_id = v.id WHERE b.status = 'paid' AND b.visit_id IS NOT NULL AND b.patient_id IS NOT NULL AND b.bill_number NOT LIKE 'BILL-OTC-%'" . ($selected_branch_id > 0 ? " AND b.branch_id = ?" : ""));
$stmt->execute($selected_branch_id > 0 ? [$selected_branch_id] : []);
$consultation_fee_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Amount (All)
$stmt = $db->prepare("SELECT COALESCE(SUM(b.total_amount), 0) as total FROM bills b WHERE b.visit_id IS NOT NULL AND b.patient_id IS NOT NULL AND b.bill_number NOT LIKE 'BILL-OTC-%'" . ($selected_branch_id > 0 ? " AND b.branch_id = ?" : ""));
$stmt->execute($selected_branch_id > 0 ? [$selected_branch_id] : []);
$total_amount_all = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Balance
$stmt = $db->prepare("SELECT COALESCE(SUM(b.balance), 0) as total FROM bills b WHERE b.visit_id IS NOT NULL AND b.patient_id IS NOT NULL AND b.bill_number NOT LIKE 'BILL-OTC-%'" . ($selected_branch_id > 0 ? " AND b.branch_id = ?" : ""));
$stmt->execute($selected_branch_id > 0 ? [$selected_branch_id] : []);
$total_balance = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$summary = [
    'total_bills' => $total_bills,
    'paid_bills' => $paid_bills,
    'pending_bills' => $pending_bills,
    'total_revenue' => $total_revenue,
    'consultation_fee_total' => $consultation_fee_total,
    'total_amount_all' => $total_amount_all,
    'total_balance' => $total_balance
];

// ================================================================
// HELPERS
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
        'cash' => '💰 Cash', 'm-pesa' => '📱 M-Pesa', 'airtel_money' => '📱 Airtel',
        'tigo_pesa' => '📱 Tigo', 'halopesa' => '📱 Halo', 'bank' => '🏦 Bank',
        'card' => '💳 Card', 'insurance' => '🏥 Insurance', 'other' => '📦 Other'
    ];
    return $map[$method] ?? ucfirst($method);
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

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
        --page-cyan: #0891B2;
        --page-bg-body: #F0FDF4;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-border: #D1FAE5;
        --page-table-hover: #ECFDF5;
        --page-shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
        --page-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-border: #334155;
        --page-table-hover: #1A3A2A;
        --page-primary: #34D399;
        --page-primary-bg: #1A3A2A;
        --page-success-bg: #1A3A2A;
        --page-danger-bg: #3A1A1A;
        --page-warning-bg: #3D2E0A;
        --page-purple-bg: #2D1B5F;
    }

    body { background: var(--page-bg-body); }
    html[data-theme="dark"] body { background: #0F172A !important; }

    .main-content { background: var(--page-bg-body); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; }

    /* ================================================================
       GREEN PAGE HEADER CARD
       ================================================================ */
    .page-header-card {
        background: linear-gradient(135deg, #059669 0%, #047857 50%, #065F46 100%);
        border-radius: 20px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        color: white;
        box-shadow: 0 8px 32px rgba(5, 150, 105, 0.3), 0 4px 12px rgba(5, 150, 105, 0.2);
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
       STAT CARDS - 6 cards
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        border-radius: 16px;
        padding: 18px 20px;
        color: white;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        min-height: 120px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: -50%; right: -20%;
        width: 160px; height: 160px;
        background: rgba(255,255,255,0.08);
        border-radius: 50%;
        pointer-events: none;
        transition: all 0.5s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 32px rgba(0,0,0,0.2);
    }

    .stat-card:hover::before { transform: scale(1.3); right: -10%; }

    .stat-card .stat-icon {
        width: 44px; height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        background: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.15);
        backdrop-filter: blur(8px);
        margin-bottom: 10px;
        position: relative;
        z-index: 1;
    }

    .stat-card .stat-label {
        font-size: 0.65rem;
        color: rgba(255,255,255,0.9);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin: 0 0 4px 0;
        position: relative;
        z-index: 1;
    }

    .stat-card .stat-number {
        font-size: 1.55rem;
        font-weight: 800;
        color: white;
        line-height: 1.1;
        margin: 0;
        position: relative;
        z-index: 1;
        word-break: break-word;
    }

    .stat-card .stat-sub {
        font-size: 0.62rem;
        color: rgba(255,255,255,0.85);
        margin-top: 4px;
        position: relative;
        z-index: 1;
    }

    /* Card Colors */
    .card-green { background: linear-gradient(135deg, #059669, #047857); }
    .card-green:hover { box-shadow: 0 12px 32px rgba(5, 150, 105, 0.4); }

    .card-blue { background: linear-gradient(135deg, #2563EB, #1D4ED8); }
    .card-blue:hover { box-shadow: 0 12px 32px rgba(37, 99, 235, 0.4); }

    .card-orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .card-orange:hover { box-shadow: 0 12px 32px rgba(217, 119, 6, 0.4); }

    .card-purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    .card-purple:hover { box-shadow: 0 12px 32px rgba(124, 58, 237, 0.4); }

    .card-cyan { background: linear-gradient(135deg, #0891B2, #0E7490); }
    .card-cyan:hover { box-shadow: 0 12px 32px rgba(8, 145, 178, 0.4); }

    .card-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    .card-red:hover { box-shadow: 0 12px 32px rgba(220, 38, 38, 0.4); }

    [data-theme="dark"] .card-green { background: linear-gradient(135deg, #059669, #047857); }
    [data-theme="dark"] .card-blue { background: linear-gradient(135deg, #2563EB, #1D4ED8); }
    [data-theme="dark"] .card-orange { background: linear-gradient(135deg, #D97706, #B45309); }
    [data-theme="dark"] .card-purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    [data-theme="dark"] .card-cyan { background: linear-gradient(135deg, #0891B2, #0E7490); }
    [data-theme="dark"] .card-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }

    /* ================================================================
       FILTER BAR
       ================================================================ */
    .filter-bar {
        background: var(--page-bg-card);
        border-radius: 16px;
        padding: 16px 20px;
        border: 2px solid var(--page-border);
        margin-bottom: 24px;
        box-shadow: var(--page-shadow-sm);
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
    }

    html[data-theme="dark"] .filter-bar {
        background: #1E293B;
        border-color: #334155;
    }

    .filter-bar .filter-label {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--page-primary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .filter-bar select, .filter-bar input {
        background: var(--page-bg-body);
        border: 2px solid var(--page-border);
        border-radius: 10px;
        padding: 8px 14px;
        font-size: 0.8rem;
        color: var(--page-text-primary);
        outline: none;
        transition: all 0.3s;
        min-width: 150px;
        font-family: inherit;
    }

    html[data-theme="dark"] .filter-bar select,
    html[data-theme="dark"] .filter-bar input {
        background: #0F172A;
        color: #F1F5F9;
        border-color: #334155;
    }

    .filter-bar select:focus, .filter-bar input:focus {
        border-color: var(--page-primary);
        box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.1);
    }

    .btn-search {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 20px;
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
    }

    .btn-search:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(5, 150, 105, 0.35);
    }

    .btn-reset {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 18px;
        border-radius: 10px;
        font-size: 0.8rem;
        font-weight: 700;
        border: 2px solid var(--page-border);
        background: transparent;
        color: var(--page-text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    html[data-theme="dark"] .btn-reset {
        color: #94A3B8;
        border-color: #334155;
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

    .table-card .table-card-header {
        padding: 14px 22px;
        background: linear-gradient(135deg, #059669, #047857);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .table-card .table-card-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: white;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.78rem;
    }

    .data-table thead th {
        background: var(--page-bg-body);
        color: var(--page-text-secondary);
        font-weight: 700;
        padding: 12px 14px;
        font-size: 0.62rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--page-border);
        text-align: left;
        white-space: nowrap;
    }

    html[data-theme="dark"] .data-table thead th { background: #0F172A; }

    .data-table td {
        padding: 10px 14px;
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

    html[data-theme="dark"] .badge-warning { color: #1E293B; }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--page-text-secondary);
    }

    .empty-state i {
        font-size: 3rem;
        color: var(--page-border);
        margin-bottom: 14px;
        display: block;
    }

    .empty-state p { font-size: 0.9rem; }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--page-border);
        margin-top: 24px;
        text-align: center;
        font-size: 0.72rem;
        color: var(--page-text-secondary);
    }

    .footer .footer-brand { color: var(--page-primary); font-weight: 700; }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }

    /* ================================================================
       UTILITY
       ================================================================ */
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-mono { font-family: 'Courier New', monospace; }
    .font-semibold { font-weight: 600; }
    .text-green-600 { color: #059669; }
    .text-red-600 { color: #DC2626; }
    .text-blue-600 { color: #0B5ED7; }

    html[data-theme="dark"] .text-green-600 { color: #34D399; }
    html[data-theme="dark"] .text-red-600 { color: #F87171; }
    html[data-theme="dark"] .text-blue-600 { color: #6EA8FE; }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .page-header-card { padding: 20px; }
        .page-header-title { font-size: 1.2rem; }
        .page-header-title i { width: 36px; height: 36px; font-size: 1rem; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
        .stat-card { padding: 14px 16px; min-height: 105px; }
        .stat-card .stat-number { font-size: 1.3rem; }
        .stat-card .stat-icon { width: 38px; height: 38px; font-size: 1rem; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar select, .filter-bar input { width: 100%; min-width: unset; }
        .data-table { font-size: 0.65rem; }
        .data-table thead th, .data-table td { padding: 8px 10px; }
    }

    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; gap: 10px; }
        .data-table { font-size: 0.55rem; }
        .data-table thead th, .data-table td { padding: 6px 8px; }
    }

    /* Print */
    @media print {
        .page-header-actions, .btn-search, .btn-reset, .btn-outline-light, .filter-bar { display: none !important; }
        .page-header-card { background: #059669 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .table-card .table-card-header { background: #059669 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .stat-card, .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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
                <i class="fas fa-stethoscope"></i>
                Consultation Bills
                <span class="role-badge-display">ADMIN</span>
                <?php if ($selected_branch_id > 0): ?>
                    <span class="page-header-badge" style="background:rgba(52,211,153,0.3);">
                        <i class="fas fa-store-alt"></i>
                        <?= htmlspecialchars($branches[array_search($selected_branch_id, array_column($branches, 'id'))]['name'] ?? 'Branch') ?>
                    </span>
                <?php endif; ?>
                <?php if ($status_filter !== 'all'): ?>
                    <span class="page-header-badge" style="background:rgba(251,191,36,0.3);">
                        <i class="fas fa-filter"></i> <?= ucfirst($status_filter) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-header-subtitle">
                <i class="fas fa-chart-bar"></i>
                <strong><?= number_format($summary['total_bills'] ?? 0) ?></strong> consultation bills
                <span class="page-header-badge" style="background:rgba(52,211,153,0.25);">
                    <i class="fas fa-check-circle" style="color:#34D399;"></i>
                    <?= number_format($summary['paid_bills'] ?? 0) ?> Paid
                </span>
                <span class="page-header-badge" style="background:rgba(251,191,36,0.25);">
                    <i class="fas fa-clock" style="color:#FBBF24;"></i>
                    <?= number_format($summary['pending_bills'] ?? 0) ?> Pending
                </span>
            </p>
        </div>
        <div class="page-header-actions">
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================
         6 STATS CARDS
         ================================================================ -->
    <div class="stats-grid animate-fade-in-up" style="animation-delay:0.05s;">

        <div class="stat-card card-green">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <p class="stat-label">Total Bills</p>
            <p class="stat-number"><?= number_format($summary['total_bills'] ?? 0) ?></p>
            <p class="stat-sub">Consultation bills</p>
        </div>

        <div class="stat-card card-blue">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <p class="stat-label">Paid</p>
            <p class="stat-number"><?= number_format($summary['paid_bills'] ?? 0) ?></p>
            <p class="stat-sub">Completed payments</p>
        </div>

        <div class="stat-card card-orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <p class="stat-label">Pending</p>
            <p class="stat-number"><?= number_format($summary['pending_bills'] ?? 0) ?></p>
            <p class="stat-sub">Awaiting payment</p>
        </div>

        <div class="stat-card card-purple">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <p class="stat-label">Revenue (Paid)</p>
            <p class="stat-number">TSh <?= formatMoney($summary['total_revenue'] ?? 0) ?></p>
            <p class="stat-sub">From paid bills</p>
        </div>

        <div class="stat-card card-cyan">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <p class="stat-label">Total Amount (All)</p>
            <p class="stat-number">TSh <?= formatMoney($summary['total_amount_all'] ?? 0) ?></p>
            <p class="stat-sub">All bills total</p>
        </div>

        <div class="stat-card card-red">
            <div class="stat-icon"><i class="fas fa-balance-scale"></i></div>
            <p class="stat-label">Total Balance</p>
            <p class="stat-number">TSh <?= formatMoney($summary['total_balance'] ?? 0) ?></p>
            <p class="stat-sub">Outstanding balance</p>
        </div>

    </div>

    <!-- ================================================================
         FILTER BAR
         ================================================================ -->
    <div class="filter-bar animate-fade-in-up" style="animation-delay:0.1s;">
        <span class="filter-label"><i class="fas fa-filter"></i> Filter</span>
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;flex:1;">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">

            <select name="status" onchange="this.form.submit()">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>✅ Paid</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>🔄 Partial</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
            </select>

            <input type="text" name="search" placeholder="Search bill # or patient..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:180px;">

            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
            <span style="color:var(--page-text-secondary);font-size:0.8rem;">to</span>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">

            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i> Filter
            </button>

            <a href="consultations.php?branch=<?= $selected_branch_id ?>" class="btn-reset">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- ================================================================
         TABLE
         ================================================================ -->
    <div class="table-card animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="table-card-header">
            <h3 class="table-card-title">
                <i class="fas fa-list"></i>
                Consultation Bills List
                <span style="font-weight:400;font-size:0.72rem;opacity:0.85;">
                    (<?= number_format(count($consultations)) ?> bills)
                </span>
            </h3>
            <span style="font-size:0.72rem;color:rgba(255,255,255,0.9);font-weight:600;">
                Revenue: TSh <?= formatMoney($summary['total_revenue'] ?? 0) ?>
            </span>
        </div>

        <?php if (count($consultations) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Visit #</th>
                            <th>Patient</th>
                            <th>Branch</th>
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
                        <?php foreach ($consultations as $bill): 
                            $balance = (float)($bill['balance'] ?? 0);
                            $total = (float)($bill['total_amount'] ?? 0);
                            $paid = (float)($bill['paid_amount'] ?? 0);
                        ?>
                            <tr>
                                <td class="font-mono font-semibold text-green-600" style="font-size:0.7rem;">
                                    <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                                </td>
                                <td class="font-mono" style="font-size:0.68rem;">
                                    <?= htmlspecialchars($bill['visit_id'] ?? 'N/A') ?>
                                </td>
                                <td>
                                    <?php if (!empty($bill['patient_name'])): ?>
                                        <div style="font-weight:600;"><?= htmlspecialchars($bill['patient_name']) ?></div>
                                        <div style="font-size:0.62rem;color:var(--page-text-secondary);">
                                            <?= htmlspecialchars($bill['patient_code'] ?? '') ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--page-text-secondary);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.72rem;"><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></td>
                                <td class="text-right font-semibold">TSh <?= formatMoney($total) ?></td>
                                <td class="text-right text-green-600 font-semibold">TSh <?= formatMoney($paid) ?></td>
                                <td class="text-right <?= $balance > 0 ? 'text-red-600' : 'text-green-600' ?> font-semibold">
                                    TSh <?= formatMoney($balance) ?>
                                </td>
                                <td><?= getStatusBadge($bill['status'] ?? 'pending') ?></td>
                                <td style="font-size:0.68rem;">
                                    <?php if (!empty($bill['payment_method'])): ?>
                                        <?= getPaymentMethodLabel($bill['payment_method']) ?>
                                    <?php else: ?>
                                        <span style="color:var(--page-text-secondary);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.68rem;white-space:nowrap;">
                                    <?= date('d/m/Y', strtotime($bill['created_at'])) ?>
                                </td>
                                <td style="text-align:center;white-space:nowrap;">
                                    <a href="view_bill.php?id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                       class="text-blue-600" style="font-size:0.68rem;font-weight:700;text-decoration:none;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($bill['status'] === 'paid'): ?>
                                        <a href="../cashier/print_receipt.php?bill_id=<?= $bill['id'] ?>&print=1" 
                                           target="_blank" class="text-green-600" style="font-size:0.68rem;font-weight:700;text-decoration:none;margin-left:6px;">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="padding:12px 20px;background:var(--page-bg-body);border-top:2px solid var(--page-border);display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;font-size:0.75rem;">
                <span style="color:var(--page-text-secondary);">
                    <i class="fas fa-list"></i> Showing <strong style="color:var(--page-text-primary);"><?= number_format(count($consultations)) ?></strong> consultation bills
                    <span style="margin-left:8px;color:var(--page-primary);font-weight:700;">Revenue: TSh <?= formatMoney($summary['total_revenue'] ?? 0) ?></span>
                </span>
                <span style="color:var(--page-text-secondary);" id="updateTimeDisplay">
                    <i class="fas fa-clock"></i> <?= date('H:i:s') ?>
                </span>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-stethoscope"></i>
                <p style="font-weight:600;font-size:1rem;">No consultation bills found</p>
                <p style="font-size:0.8rem;">Try adjusting your filters</p>
            </div>
        <?php endif; ?>
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
    });

    console.log('%c🩺 Braick - Consultation Bills', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🎨 Green page header + 6 summary cards', 'font-size:13px; color:#059669;');
    console.log('%c🌙 FULL DARK MODE works', 'font-size:13px; color:#7C3AED;');
    console.log('%c📊 Total Bills: <?= number_format($summary['total_bills'] ?? 0) ?>', 'font-size:13px; color:#2563EB;');
    console.log('%c💰 Revenue: TSh <?= formatMoney($summary['total_revenue'] ?? 0) ?>', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>