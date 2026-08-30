<?php
// ================================================================
// FILE: frontend/pages/admin/consultations.php
// ADMIN - VIEW CONSULTATION REVENUE
// Shows consultation fees from bills table using visit_id
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS ADMIN
// ================================================================
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

// ================================================================
// GET ADMIN DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET FILTER PARAMETERS
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 1;
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// BUILD QUERY - Get consultations from bills with visit_id
// ================================================================
$where_conditions = [];
$params = [];

// Only bills with visit_id NOT NULL (consultations)
$where_conditions[] = "b.visit_id IS NOT NULL";
$where_conditions[] = "b.patient_id IS NOT NULL";
$where_conditions[] = "b.bill_number NOT LIKE 'BILL-OTC-%'";

// Branch filter
if ($selected_branch_id > 0) {
    $where_conditions[] = "b.branch_id = ?";
    $params[] = $selected_branch_id;
}

// Status filter
if ($status_filter !== 'all' && !empty($status_filter)) {
    $where_conditions[] = "b.status = ?";
    $params[] = $status_filter;
}

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(b.bill_number LIKE ? OR p.full_name LIKE ? OR b.bill_number LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Date filter
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
// GET CONSULTATION BILLS
// ================================================================
$sql = "
    SELECT 
        b.id,
        b.bill_number,
        b.patient_id,
        b.visit_id,
        b.branch_id,
        b.created_by,
        b.subtotal,
        b.discount_percent,
        b.discount_amount,
        b.total_discount,
        b.total_amount,
        b.paid_amount,
        b.balance,
        b.status,
        b.payment_method,
        b.notes,
        b.created_at,
        b.updated_at,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone as patient_phone,
        u.full_name as cashier_name,
        br.name as branch_name,
        v.visit_type,
        v.consultation_fee,
        v.service_id
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
// GET SUMMARY STATISTICS
// ================================================================

// 1. TOTAL CONSULTATION BILLS
$total_sql = "
    SELECT COUNT(*) as total
    FROM bills b
    WHERE b.visit_id IS NOT NULL
    AND b.patient_id IS NOT NULL
    AND b.bill_number NOT LIKE 'BILL-OTC-%'
";
$total_params = [];
if ($selected_branch_id > 0) {
    $total_sql .= " AND b.branch_id = ?";
    $total_params[] = $selected_branch_id;
}
$stmt = $db->prepare($total_sql);
$stmt->execute($total_params);
$total_bills = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 2. PAID CONSULTATION BILLS
$paid_sql = "
    SELECT COUNT(*) as total
    FROM bills b
    WHERE b.status = 'paid'
    AND b.visit_id IS NOT NULL
    AND b.patient_id IS NOT NULL
    AND b.bill_number NOT LIKE 'BILL-OTC-%'
";
$paid_params = [];
if ($selected_branch_id > 0) {
    $paid_sql .= " AND b.branch_id = ?";
    $paid_params[] = $selected_branch_id;
}
$stmt = $db->prepare($paid_sql);
$stmt->execute($paid_params);
$paid_bills = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 3. PENDING CONSULTATION BILLS
$pending_sql = "
    SELECT COUNT(*) as total
    FROM bills b
    WHERE b.status = 'pending'
    AND b.visit_id IS NOT NULL
    AND b.patient_id IS NOT NULL
    AND b.bill_number NOT LIKE 'BILL-OTC-%'
";
$pending_params = [];
if ($selected_branch_id > 0) {
    $pending_sql .= " AND b.branch_id = ?";
    $pending_params[] = $selected_branch_id;
}
$stmt = $db->prepare($pending_sql);
$stmt->execute($pending_params);
$pending_bills = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 4. TOTAL REVENUE FROM CONSULTATIONS (paid bills only)
$revenue_sql = "
    SELECT COALESCE(SUM(b.paid_amount), 0) as total
    FROM bills b
    WHERE b.status = 'paid'
    AND b.visit_id IS NOT NULL
    AND b.patient_id IS NOT NULL
    AND b.bill_number NOT LIKE 'BILL-OTC-%'
";
$revenue_params = [];
if ($selected_branch_id > 0) {
    $revenue_sql .= " AND b.branch_id = ?";
    $revenue_params[] = $selected_branch_id;
}
$stmt = $db->prepare($revenue_sql);
$stmt->execute($revenue_params);
$total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 5. TOTAL CONSULTATION FEE (from visits table)
$consultation_fee_sql = "
    SELECT COALESCE(SUM(v.consultation_fee), 0) as total
    FROM visits v
    INNER JOIN bills b ON b.visit_id = v.id
    WHERE b.status = 'paid'
    AND b.visit_id IS NOT NULL
    AND b.patient_id IS NOT NULL
    AND b.bill_number NOT LIKE 'BILL-OTC-%'
";
$cfee_params = [];
if ($selected_branch_id > 0) {
    $consultation_fee_sql .= " AND b.branch_id = ?";
    $cfee_params[] = $selected_branch_id;
}
$stmt = $db->prepare($consultation_fee_sql);
$stmt->execute($cfee_params);
$consultation_fee_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 6. TOTAL CONSULTATION REVENUE (total_amount from bills)
$total_amount_sql = "
    SELECT COALESCE(SUM(b.total_amount), 0) as total
    FROM bills b
    WHERE b.visit_id IS NOT NULL
    AND b.patient_id IS NOT NULL
    AND b.bill_number NOT LIKE 'BILL-OTC-%'
";
$tamount_params = [];
if ($selected_branch_id > 0) {
    $total_amount_sql .= " AND b.branch_id = ?";
    $tamount_params[] = $selected_branch_id;
}
$stmt = $db->prepare($total_amount_sql);
$stmt->execute($tamount_params);
$total_amount_all = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 7. TOTAL BALANCE
$balance_sql = "
    SELECT COALESCE(SUM(b.balance), 0) as total
    FROM bills b
    WHERE b.visit_id IS NOT NULL
    AND b.patient_id IS NOT NULL
    AND b.bill_number NOT LIKE 'BILL-OTC-%'
";
$balance_params = [];
if ($selected_branch_id > 0) {
    $balance_sql .= " AND b.branch_id = ?";
    $balance_params[] = $selected_branch_id;
}
$stmt = $db->prepare($balance_sql);
$stmt->execute($balance_params);
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
// FORMAT FUNCTIONS
// ================================================================
function formatMoney($amount) {
    if ($amount === null || $amount === '') {
        return '0';
    }
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

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADERS
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE STYLES -->
<!-- ================================================================ -->
<style>
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 28px 32px;
        min-height: calc(100vh - 68px);
        background: var(--bg-body);
        transition: background 0.3s ease;
    }
    
    @media (max-width: 1024px) {
        .main-content { margin-left: 0; padding: 16px; }
    }
    
    .page-header {
        background: linear-gradient(135deg, #059669, #047857);
        border-radius: 18px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 20px rgba(5, 150, 105, 0.25);
        position: relative;
        overflow: hidden;
    }
    
    .page-header .page-title {
        color: white;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }
    
    .page-header .page-title i { font-size: 1.6rem; opacity: 0.9; }
    
    .page-header .page-subtitle {
        color: rgba(255,255,255,0.85);
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }
    
    .role-badge-display {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.55rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .header-badge {
        background: rgba(255,255,255,0.12);
        color: white;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 500;
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: 1px solid rgba(255,255,255,0.08);
    }
    
    .btn-outline-light {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.12);
        padding: 8px 16px;
        border-radius: 12px;
        font-weight: 500;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        backdrop-filter: blur(4px);
        position: relative;
        z-index: 1;
    }
    
    .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
    }
    
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        border-radius: 18px;
        padding: 16px 18px;
        transition: all 0.3s ease;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        border: none;
        color: white;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    
    .stat-card .stat-number {
        font-size: 1.4rem;
        font-weight: 700;
        color: white;
    }
    
    .stat-card .stat-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
        margin-top: 2px;
        opacity: 0.85;
        color: rgba(255,255,255,0.85);
    }
    
    .stat-card .stat-icon {
        font-size: 1.4rem;
        margin-bottom: 4px;
    }
    
    .filter-section {
        background: var(--bg-card);
        border-radius: 18px;
        padding: 14px 18px;
        border: 1px solid var(--border-color);
        margin-bottom: 20px;
    }
    
    .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }
    
    .filter-input {
        padding: 6px 12px;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        font-size: 0.8rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
        min-width: 140px;
    }
    
    .filter-input:focus {
        border-color: #059669;
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
    }
    
    .filter-select {
        padding: 6px 12px;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        font-size: 0.8rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
        min-width: 140px;
    }
    
    .filter-select:focus {
        border-color: #059669;
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
    }
    
    .btn-search {
        padding: 6px 16px;
        background: #059669;
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-search:hover {
        background: #047857;
        transform: translateY(-1px);
    }
    
    .btn-reset {
        padding: 6px 14px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        border: 2px solid var(--border-color);
        background: transparent;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .btn-reset:hover {
        border-color: #DC2626;
        color: #DC2626;
    }
    
    .table-container {
        background: var(--bg-card);
        border-radius: 18px;
        border: 1px solid var(--border-color);
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        margin-bottom: 20px;
    }
    
    .table-scroll { overflow-x: auto; }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.75rem;
    }
    
    .data-table thead th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 700;
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #ffffff;
        background: #059669;
        border-bottom: 3px solid #047857;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 5;
    }
    
    .data-table tbody td {
        padding: 8px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .data-table tbody tr:hover td {
        background: var(--primary-bg);
    }
    
    .data-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        color: white;
    }
    
    .badge-success { background: #059669; }
    .badge-danger { background: #DC2626; }
    .badge-warning { background: #D97706; color: #1E293B; }
    .badge-info { background: #0B5ED7; }
    .badge-secondary { background: #64748B; }
    
    [data-theme="dark"] .badge-warning { color: #1E293B; }
    
    .table-footer {
        padding: 10px 16px;
        border-top: 1px solid var(--border-color);
        font-size: 0.65rem;
        color: var(--text-secondary);
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 6px;
        background: var(--gray-50);
    }
    
    [data-theme="dark"] .table-footer {
        background: #1E293B;
    }
    
    .footer {
        padding: 10px 0;
        border-top: 1px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.65rem;
        color: var(--text-secondary);
    }
    
    .footer .footer-brand {
        color: #059669;
        font-weight: 600;
    }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-fade-in-up {
        animation: fadeInUp 0.4s ease forwards;
        opacity: 0;
    }
    
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-mono { font-family: monospace; }
    .font-semibold { font-weight: 600; }
    .text-green-600 { color: #059669; }
    .text-red-600 { color: #DC2626; }
    .text-blue-600 { color: #0B5ED7; }
    .text-yellow-600 { color: #D97706; }
    
    @media (max-width: 768px) {
        .filter-row { flex-direction: column; align-items: stretch; }
        .filter-input, .filter-select { width: 100%; min-width: unset; }
        .stats-row { grid-template-columns: 1fr 1fr; }
        .page-header { padding: 16px 18px; }
        .page-header .page-title { font-size: 1.1rem; }
        .data-table { font-size: 0.65rem; }
        .data-table thead th, .data-table td { padding: 4px 6px; }
    }
    
    @media (max-width: 480px) {
        .stats-row { grid-template-columns: 1fr; }
        .data-table { font-size: 0.55rem; }
        .data-table thead th, .data-table td { padding: 3px 4px; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-stethoscope"></i>
                Consultation Bills
                <span class="role-badge-display">ADMIN</span>
                <?php if ($selected_branch_id > 0): ?>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branches[array_search($selected_branch_id, array_column($branches, 'id'))]['name'] ?? 'Branch') ?>
                    </span>
                <?php endif; ?>
                <?php if ($status_filter !== 'all'): ?>
                    <span class="role-badge-display" style="background:rgba(251,191,36,0.2);color:#FBBF24;">
                        <i class="fas fa-filter"></i> <?= ucfirst($status_filter) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-arrow-right"></i>
                <?= number_format($summary['total_bills'] ?? 0) ?> consultation bills
                <span class="header-badge" style="background:rgba(52,211,153,0.2);color:#34D399;">
                    <i class="fas fa-check-circle"></i> <?= number_format($summary['paid_bills'] ?? 0) ?> Paid
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);color:#FBBF24;">
                    <i class="fas fa-clock"></i> <?= number_format($summary['pending_bills'] ?? 0) ?> Pending
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-row animate-fade-in-up" style="animation-delay:0.05s;">
        
        <div class="stat-card" style="background: linear-gradient(135deg, #059669, #047857);">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-number"><?= number_format($summary['total_bills'] ?? 0) ?></div>
            <div class="stat-label">Total Consultation Bills</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #2563EB, #1D4ED8);">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?= number_format($summary['paid_bills'] ?? 0) ?></div>
            <div class="stat-label">Paid</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #D97706, #B45309);">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number"><?= number_format($summary['pending_bills'] ?? 0) ?></div>
            <div class="stat-label">Pending</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #7C3AED, #6D28D9);">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-number">TSh <?= formatMoney($summary['total_revenue'] ?? 0) ?></div>
            <div class="stat-label">Revenue (Paid)</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #0891B2, #0E7490);">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-number">TSh <?= formatMoney($summary['total_amount_all'] ?? 0) ?></div>
            <div class="stat-label">Total Amount (All)</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #DC2626, #B91C1C);">
            <div class="stat-icon"><i class="fas fa-balance-scale"></i></div>
            <div class="stat-number">TSh <?= formatMoney($summary['total_balance'] ?? 0) ?></div>
            <div class="stat-label">Total Balance</div>
        </div>
        
    </div>

    <!-- Filter Section -->
    <div class="filter-section animate-fade-in-up" style="animation-delay:0.1s;">
        <form method="GET" action="" class="w-full">
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
                
                <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($date_from) ?>" placeholder="From">
                <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($date_to) ?>" placeholder="To">
                
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Filter
                </button>
                
                <a href="consultations.php?branch=<?= $selected_branch_id ?>" class="btn-reset">
                    <i class="fas fa-times"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Consultation Bills Table -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.15s;">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;">
            <h3 style="font-size:0.85rem;font-weight:700;color:var(--text-primary);">
                <i class="fas fa-list" style="color:#059669;"></i> Consultation Bills List
                <span style="font-weight:400;font-size:0.7rem;color:var(--text-secondary);">
                    (<?= number_format(count($consultations)) ?> bills)
                </span>
            </h3>
            <span style="font-size:0.65rem;color:var(--text-secondary);">
                Total Revenue: TSh <?= formatMoney($summary['total_revenue'] ?? 0) ?>
            </span>
        </div>
        <div class="table-scroll">
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
                    <?php if (count($consultations) > 0): ?>
                        <?php foreach ($consultations as $bill): 
                            $balance = (float)($bill['balance'] ?? 0);
                            $total = (float)($bill['total_amount'] ?? 0);
                            $paid = (float)($bill['paid_amount'] ?? 0);
                        ?>
                            <tr>
                                <td class="font-mono font-semibold text-green-600" style="font-size:0.65rem;">
                                    <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                                </td>
                                <td class="font-mono" style="font-size:0.6rem;">
                                    <?= htmlspecialchars($bill['visit_id'] ?? 'N/A') ?>
                                </td>
                                <td>
                                    <?php if (!empty($bill['patient_name'])): ?>
                                        <div style="font-weight:500;"><?= htmlspecialchars($bill['patient_name']) ?></div>
                                        <div style="font-size:0.55rem;color:var(--text-secondary);">
                                            <?= htmlspecialchars($bill['patient_code'] ?? '') ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.6rem;"><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></td>
                                <td class="text-right font-semibold">TSh <?= formatMoney($total) ?></td>
                                <td class="text-right text-green-600 font-semibold">TSh <?= formatMoney($paid) ?></td>
                                <td class="text-right <?= $balance > 0 ? 'text-red-600' : 'text-green-600' ?> font-semibold">
                                    TSh <?= formatMoney($balance) ?>
                                </td>
                                <td><?= getStatusBadge($bill['status'] ?? 'pending') ?></td>
                                <td style="font-size:0.6rem;">
                                    <?php if (!empty($bill['payment_method'])): ?>
                                        <?= getPaymentMethodLabel($bill['payment_method']) ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.6rem;white-space:nowrap;">
                                    <?= date('d/m/Y', strtotime($bill['created_at'])) ?>
                                </td>
                                <td style="text-align:center;">
                                    <a href="view_bill.php?id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                       class="text-blue-600 hover:underline" style="font-size:0.6rem;font-weight:600;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($bill['status'] === 'paid'): ?>
                                        <a href="../cashier/print_receipt.php?bill_id=<?= $bill['id'] ?>&print=1" 
                                           target="_blank" class="text-green-600 hover:underline" style="font-size:0.6rem;font-weight:600;margin-left:4px;">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11">
                                <div style="padding:30px;text-align:center;color:var(--text-secondary);">
                                    <i class="fas fa-stethoscope" style="font-size:2rem;color:var(--border-color);display:block;margin-bottom:8px;"></i>
                                    <p style="font-size:0.9rem;">No consultation bills found</p>
                                    <p style="font-size:0.7rem;">Try adjusting your filters</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>
                <i class="fas fa-list"></i> Showing <strong><?= number_format(count($consultations)) ?></strong> consultation bills
                <span class="text-xs" style="color:var(--text-secondary);">
                    Revenue: TSh <?= formatMoney($summary['total_revenue'] ?? 0) ?>
                </span>
            </span>
            <span>
                <span class="text-xs" style="color:var(--text-secondary);" id="updateTimeDisplay">
                    <i class="fas fa-clock"></i> <?= date('H:i:s') ?>
                </span>
            </span>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Consultation Bills
            <span class="text-gray-300 mx-2">|</span>
            <span class="text-gray-400">👤 <?= htmlspecialchars($user_full_name) ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        
        var updateTimeDisplay = document.getElementById('updateTimeDisplay');
        if (updateTimeDisplay) {
            updateTimeDisplay.innerHTML = '<i class="fas fa-clock"></i> ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }

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

    console.log('%c🩺 Braick - Consultation Bills', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:12px; color:#059669;');
    console.log('%c📊 Total Consultation Bills: <?= number_format($summary['total_bills'] ?? 0) ?>', 'font-size:12px; color:#059669;');
    console.log('%c   ├─ Paid: <?= number_format($summary['paid_bills'] ?? 0) ?>', 'font-size:11px; color:#2563EB;');
    console.log('%c   └─ Pending: <?= number_format($summary['pending_bills'] ?? 0) ?>', 'font-size:11px; color:#D97706;');
    console.log('%c💰 Revenue (Paid): TSh <?= formatMoney($summary['total_revenue'] ?? 0) ?>', 'font-size:12px; color:#7C3AED;');
    console.log('%c💵 Total Amount (All): TSh <?= formatMoney($summary['total_amount_all'] ?? 0) ?>', 'font-size:12px; color:#0891B2;');
    console.log('%c⚖️ Total Balance: TSh <?= formatMoney($summary['total_balance'] ?? 0) ?>', 'font-size:12px; color:#DC2626;');
    console.log('%c✅ Uses bills with visit_id NOT NULL', 'font-size:12px; color:#34D399;');
    console.log('%c✅ Excludes OTC bills', 'font-size:12px; color:#34D399;');
</script>

</body>
</html>