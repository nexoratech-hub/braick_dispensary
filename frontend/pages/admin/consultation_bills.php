<?php
// ================================================================
// FILE: frontend/pages/admin/consultation_bills.php
// ADMIN - VIEW CONSULTATION BILLS ONLY
// ✅ Uses SHARED header & sidebar
// ✅ Beautiful blue/purple page header + 5 summary cards
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

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;

// ================================================================
// BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $branches = []; }

// ================================================================
// BRANCH NAME
// ================================================================
$branch_name = 'All Branches';

if ($branch_id > 0) {
    try {
        $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
        $stmt->execute([$branch_id]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($branch) {
            $branch_name = $branch['name'];
        } else {
            $branch_name = 'Unknown Branch';
        }
    } catch (Exception $e) {
        $branch_name = 'Unknown Branch';
    }
}

// ================================================================
// FILTERS
// ================================================================
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

// ================================================================
// QUERY - CONSULTATION BILLS
// ================================================================
$query = "
    SELECT 
        b.id,
        b.bill_number,
        b.total_amount,
        b.paid_amount,
        b.balance,
        b.status,
        b.subtotal,
        b.discount_amount,
        b.total_discount,
        b.payment_method,
        b.created_at,
        b.updated_at,
        pat.id as patient_id,
        pat.full_name as patient_name,
        pat.patient_id as patient_code,
        pat.phone as patient_phone,
        pat.email as patient_email,
        pat.branch_id as patient_branch_id,
        br.id as branch_id,
        br.name as branch_name,
        br.location as branch_location,
        (
            SELECT COUNT(*) 
            FROM bill_items bi 
            WHERE bi.bill_id = b.id 
            AND bi.item_type = 'consultation'
            AND bi.status != 'cancelled'
        ) as consultation_count,
        (
            SELECT COALESCE(SUM(bi.total_price), 0)
            FROM bill_items bi 
            WHERE bi.bill_id = b.id 
            AND bi.item_type = 'consultation'
            AND bi.status != 'cancelled'
        ) as consultation_total,
        (
            SELECT GROUP_CONCAT(DISTINCT bi.item_name SEPARATOR ', ')
            FROM bill_items bi 
            WHERE bi.bill_id = b.id 
            AND bi.item_type = 'consultation'
            AND bi.status != 'cancelled'
            LIMIT 3
        ) as consultation_items,
        (
            SELECT COUNT(*) 
            FROM bill_items bi 
            WHERE bi.bill_id = b.id 
            AND bi.status != 'cancelled'
        ) as total_items
    FROM bills b
    INNER JOIN patients pat ON b.patient_id = pat.id
    LEFT JOIN branches br ON pat.branch_id = br.id
    WHERE 1=1
    AND EXISTS (
        SELECT 1 
        FROM bill_items bi 
        WHERE bi.bill_id = b.id 
        AND bi.item_type = 'consultation'
        AND bi.status != 'cancelled'
    )
";

$params = [];

if ($branch_id > 0) {
    $query .= " AND pat.branch_id = ?";
    $params[] = $branch_id;
}

if ($status_filter !== 'all') {
    $query .= " AND b.status = ?";
    $params[] = $status_filter;
}

if (!empty($from_date)) {
    $query .= " AND DATE(b.created_at) >= ?";
    $params[] = $from_date;
}
if (!empty($to_date)) {
    $query .= " AND DATE(b.created_at) <= ?";
    $params[] = $to_date;
}

if (!empty($search)) {
    $query .= " AND (b.bill_number LIKE ? OR pat.full_name LIKE ? OR pat.phone LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY b.created_at DESC";

$consultation_bills = [];
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $consultation_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching consultation bills: " . $e->getMessage());
    $consultation_bills = [];
}

// ================================================================
// SUMMARY
// ================================================================
$total_bills = count($consultation_bills);
$total_consultation_revenue = 0;
$total_bill_amount = 0;
$total_paid = 0;
$total_pending = 0;
$total_partial = 0;
$total_cancelled = 0;
$total_paid_amount = 0;
$total_balance = 0;

$unique_branches = [];

foreach ($consultation_bills as $bill) {
    $consultation_amount = $bill['consultation_total'] ?? 0;
    $total_consultation_revenue += $consultation_amount;
    $total_bill_amount += $bill['total_amount'] ?? 0;
    $total_paid_amount += $bill['paid_amount'] ?? 0;
    $total_balance += $bill['balance'] ?? 0;

    if (!empty($bill['branch_name']) && !in_array($bill['branch_name'], $unique_branches)) {
        $unique_branches[] = $bill['branch_name'];
    }

    switch ($bill['status']) {
        case 'paid': $total_paid++; break;
        case 'pending': $total_pending++; break;
        case 'partial': $total_partial++; break;
        case 'cancelled': $total_cancelled++; break;
    }
}

$branch_display_name = 'All Branches';
if ($branch_id > 0) {
    $branch_display_name = $branch_name;
} else {
    if (count($unique_branches) == 1) {
        $branch_display_name = $unique_branches[0];
    } elseif (count($unique_branches) > 1) {
        $branch_display_name = 'Multiple Branches (' . count($unique_branches) . ')';
    }
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// HELPERS
// ================================================================
function getStatusBadge($status) {
    $classes = ['pending' => 'warning', 'paid' => 'success', 'partial' => 'warning', 'cancelled' => 'danger'];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = ['pending' => 'fa-clock', 'paid' => 'fa-check-circle', 'partial' => 'fa-hourglass-half', 'cancelled' => 'fa-times-circle'];
    return $icons[$status] ?? 'fa-circle';
}

function formatCurrency($amount) {
    return 'TSh ' . number_format($amount, 0);
}

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
        --page-primary-bg: #EFF6FF;
        --page-primary-light: #3B82F6;
        --page-purple: #7C3AED;
        --page-purple-bg: #EDE9FE;
        --page-success: #059669;
        --page-success-bg: #D1FAE5;
        --page-danger: #DC2626;
        --page-danger-bg: #FEE2E2;
        --page-warning: #D97706;
        --page-warning-bg: #FEF3C7;
        --page-bg-body: #EFF6FF;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-border: #BFDBFE;
        --page-table-hover: #EFF6FF;
        --page-shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
        --page-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --page-shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-border: #334155;
        --page-table-hover: #1A2A4A;
        --page-primary: #3B82F6;
        --page-primary-bg: #1E3A5F;
        --page-purple-bg: #2D1B5F;
        --page-success-bg: #1A3A2A;
        --page-danger-bg: #3A1A1A;
        --page-warning-bg: #3D2E0A;
    }

    body { background: var(--page-bg-body); }
    html[data-theme="dark"] body { background: #0F172A !important; }

    .main-content { background: var(--page-bg-body); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; }

    /* ================================================================
       BLUE/PURPLE PAGE HEADER CARD
       ================================================================ */
    .page-header-card {
        background: linear-gradient(135deg, #0B5ED7 0%, #4F46E5 50%, #7C3AED 100%);
        border-radius: 20px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        color: white;
        box-shadow: 0 8px 32px rgba(79, 70, 229, 0.3), 0 4px 12px rgba(124, 58, 237, 0.2);
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
       STATS CARDS - 5 cards
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 14px;
        margin-bottom: 24px;
    }

    .stat-card {
        border-radius: 16px;
        padding: 18px 20px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        color: white;
        position: relative;
        overflow: hidden;
        border: none;
        min-height: 110px;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: -50%; right: -20%;
        width: 160px; height: 160px;
        background: rgba(255,255,255,0.06);
        border-radius: 50%;
        pointer-events: none;
        transition: all 0.5s ease;
    }

    .stat-card:hover {
        transform: translateY(-4px) scale(1.01);
        box-shadow: 0 10px 32px rgba(0,0,0,0.2);
    }

    .stat-card:hover::before { transform: scale(1.3); right: -10%; }

    .stat-card .stat-label {
        font-size: 0.62rem;
        color: rgba(255,255,255,0.9);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 700;
        margin: 0 0 4px 0;
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .stat-card .stat-number {
        font-size: 1.75rem;
        font-weight: 800;
        color: white;
        line-height: 1.1;
        margin: 0;
        position: relative;
        z-index: 1;
    }

    .stat-card .stat-sub {
        font-size: 0.62rem;
        color: rgba(255,255,255,0.9);
        margin: 4px 0 0 0;
        position: relative;
        z-index: 1;
    }

    .stat-card .stat-icon-bg {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 3rem;
        color: rgba(255,255,255,0.1);
        z-index: 0;
    }

    /* Card Colors */
    .card-blue-dark { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .card-blue-dark:hover { box-shadow: 0 10px 32px rgba(11, 94, 215, 0.4); }

    .card-green { background: linear-gradient(135deg, #059669, #047857); }
    .card-green:hover { box-shadow: 0 10px 32px rgba(5, 150, 105, 0.4); }

    .card-orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .card-orange:hover { box-shadow: 0 10px 32px rgba(217, 119, 6, 0.4); }

    .card-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    .card-red:hover { box-shadow: 0 10px 32px rgba(220, 38, 38, 0.4); }

    .card-purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    .card-purple:hover { box-shadow: 0 10px 32px rgba(124, 58, 237, 0.4); }

    [data-theme="dark"] .card-blue-dark { background: linear-gradient(135deg, #2563EB, #1D4ED8); }
    [data-theme="dark"] .card-green { background: linear-gradient(135deg, #059669, #047857); }
    [data-theme="dark"] .card-orange { background: linear-gradient(135deg, #D97706, #B45309); }
    [data-theme="dark"] .card-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    [data-theme="dark"] .card-purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }

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
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }

    .btn-primary {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(11, 94, 215, 0.35);
        color: white;
    }

    .btn-outline {
        background: transparent;
        color: var(--page-text-secondary);
        border: 2px solid var(--page-border);
    }

    html[data-theme="dark"] .btn-outline {
        color: #94A3B8;
        border-color: #334155;
    }

    .btn-outline:hover {
        border-color: var(--page-primary);
        color: var(--page-primary);
    }

    /* ================================================================
       TABLE
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
        background: linear-gradient(135deg, #0B5ED7, #4F46E5);
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

    .table-card .table-card-action {
        color: rgba(255,255,255,0.85);
        font-size: 0.72rem;
        text-decoration: none;
        transition: all 0.3s;
        font-weight: 600;
        background: transparent;
        border: none;
        cursor: pointer;
    }

    .table-card .table-card-action:hover {
        color: white;
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
    .badge-purple { background: #7C3AED; }

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
        font-size: 3.5rem;
        color: var(--page-border);
        margin-bottom: 16px;
        display: block;
    }

    .empty-state h3 {
        font-size: 1.2rem;
        color: var(--page-text-primary);
        margin-bottom: 8px;
    }

    .empty-state p { font-size: 0.9rem; }

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
    .text-blue { color: #0B5ED7; }
    .text-purple { color: #7C3AED; }
    .text-green { color: #059669; }
    .text-red { color: #DC2626; }
    .font-mono { font-family: 'Courier New', monospace; }
    .font-semibold { font-weight: 600; }
    .text-xs { font-size: 0.7rem; }
    .text-gray-400 { color: var(--page-text-secondary); }

    html[data-theme="dark"] .text-blue { color: #60A5FA; }
    html[data-theme="dark"] .text-purple { color: #A78BFA; }
    html[data-theme="dark"] .text-green { color: #34D399; }
    html[data-theme="dark"] .text-red { color: #F87171; }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1200px) {
        .stats-grid { grid-template-columns: repeat(3, 1fr); }
    }

    @media (max-width: 1024px) {
        .stats-grid { grid-template-columns: repeat(3, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-card { padding: 20px; }
        .page-header-title { font-size: 1.2rem; }
        .page-header-title i { width: 36px; height: 36px; font-size: 1rem; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
        .stat-card { padding: 14px 16px; min-height: 100px; }
        .stat-card .stat-number { font-size: 1.4rem; }
        .stat-card .stat-icon-bg { font-size: 2.2rem; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar select, .filter-bar input { width: 100%; min-width: unset; }
        .data-table { font-size: 0.7rem; }
        .data-table thead th, .data-table td { padding: 8px 10px; }
    }

    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; gap: 10px; }
        .data-table { font-size: 0.62rem; }
        .data-table thead th, .data-table td { padding: 6px 8px; }
    }

    /* Print */
    @media print {
        .page-header-actions, .btn, .btn-outline-light, .filter-bar { display: none !important; }
        .page-header-card { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .table-card .table-card-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .stat-card, .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Blue/Purple Page Header Card -->
    <div class="page-header-card animate-fade-in-up">
        <div>
            <h1 class="page-header-title">
                <i class="fas fa-stethoscope"></i>
                Consultation Bills
                <span class="role-badge-display">ADMIN</span>
                <span class="page-header-badge" style="background:rgba(124,58,237,0.35);">
                    <i class="fas fa-filter"></i> Consultations Only
                </span>
            </h1>
            <p class="page-header-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($branch_display_name) ?></strong>
                <span class="page-header-badge">
                    <i class="fas fa-file-invoice"></i> <?= number_format($total_bills) ?> Bills
                </span>
                <span class="page-header-badge" style="background:rgba(255,255,255,0.25);">
                    <i class="fas fa-money-bill-wave"></i> <?= formatCurrency($total_consultation_revenue) ?> Revenue
                </span>
                <span class="page-header-badge" style="background:rgba(192,132,252,0.35);">
                    <i class="fas fa-user-md"></i>
                    <?= number_format($total_bill_amount > 0 ? round(($total_consultation_revenue / $total_bill_amount) * 100, 1) : 0) ?>% Consult
                </span>
            </p>
        </div>
        <div class="page-header-actions">
            <a href="view_cashier.php?id=<?= $branch_id ?>&branch=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================
         5 STATS CARDS
         ================================================================ -->
    <div class="stats-grid animate-fade-in-up" style="animation-delay:0.05s;">

        <div class="stat-card card-blue-dark">
            <div class="stat-icon-bg"><i class="fas fa-file-invoice"></i></div>
            <p class="stat-label"><i class="fas fa-file-invoice"></i> Total Bills</p>
            <p class="stat-number"><?= number_format($total_bills) ?></p>
            <p class="stat-sub">All consultation bills</p>
        </div>

        <div class="stat-card card-green">
            <div class="stat-icon-bg"><i class="fas fa-check-circle"></i></div>
            <p class="stat-label"><i class="fas fa-check-circle"></i> Paid</p>
            <p class="stat-number"><?= number_format($total_paid) ?></p>
            <p class="stat-sub"><?= $total_bills > 0 ? round(($total_paid / $total_bills) * 100, 1) : 0 ?>% of total</p>
        </div>

        <div class="stat-card card-orange">
            <div class="stat-icon-bg"><i class="fas fa-clock"></i></div>
            <p class="stat-label"><i class="fas fa-clock"></i> Pending</p>
            <p class="stat-number"><?= number_format($total_pending) ?></p>
            <p class="stat-sub">Awaiting payment</p>
        </div>

        <div class="stat-card card-red">
            <div class="stat-icon-bg"><i class="fas fa-times-circle"></i></div>
            <p class="stat-label"><i class="fas fa-times-circle"></i> Cancelled</p>
            <p class="stat-number"><?= number_format($total_cancelled) ?></p>
            <p class="stat-sub">Voided transactions</p>
        </div>

        <div class="stat-card card-purple">
            <div class="stat-icon-bg"><i class="fas fa-money-bill-wave"></i></div>
            <p class="stat-label"><i class="fas fa-money-bill-wave"></i> Consult Revenue</p>
            <p class="stat-number"><?= formatCurrency($total_consultation_revenue) ?></p>
            <p class="stat-sub">Consultation items only</p>
        </div>

    </div>

    <!-- ================================================================
         FILTER BAR
         ================================================================ -->
    <div class="filter-bar animate-fade-in-up" style="animation-delay:0.1s;">
        <span class="filter-label"><i class="fas fa-filter"></i> Filter</span>
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;flex:1;">
            <input type="hidden" name="branch" value="<?= $branch_id ?>">

            <select name="status">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partial</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>

            <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>">
            <span style="color:var(--page-text-secondary);font-size:0.8rem;">to</span>
            <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>">

            <input type="text" name="search" placeholder="Search bill # or patient..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:180px;">

            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
            <a href="consultation_bills.php?branch=<?= $branch_id ?>" class="btn btn-outline"><i class="fas fa-times"></i> Reset</a>
        </form>
    </div>

    <!-- ================================================================
         TABLE
         ================================================================ -->
    <div class="table-card animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="table-card-header">
            <h3 class="table-card-title">
                <i class="fas fa-stethoscope"></i>
                Consultation Bills (<?= number_format($total_bills) ?>)
                <span style="font-size:0.65rem;opacity:0.8;font-weight:400;">
                    <i class="fas fa-filter"></i> Only consultation items
                </span>
            </h3>
            <div style="display:flex;align-items:center;gap:12px;">
                <span class="table-card-action">
                    <i class="fas fa-calendar-alt"></i>
                    <?= !empty($from_date) ? date('M d, Y', strtotime($from_date)) : 'All' ?>
                    -
                    <?= !empty($to_date) ? date('M d, Y', strtotime($to_date)) : 'Now' ?>
                </span>
                <button onclick="window.print()" class="table-card-action">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>

        <?php if (count($consultation_bills) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Patient</th>
                            <th>Branch</th>
                            <th>Consultation Items</th>
                            <th>Consult Total</th>
                            <th>Bill Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consultation_bills as $bill): ?>
                            <tr>
                                <td class="font-mono text-xs font-semibold text-blue">
                                    <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                                </td>
                                <td>
                                    <div class="font-semibold"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($bill['patient_phone'] ?? '') ?></div>
                                </td>
                                <td>
                                    <div class="font-semibold" style="font-size:0.75rem;"><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($bill['branch_location'] ?? '') ?></div>
                                </td>
                                <td>
                                    <div class="font-semibold"><?= number_format($bill['consultation_count'] ?? 0) ?> items</div>
                                    <div class="text-xs text-gray-400" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($bill['consultation_items'] ?? '') ?>">
                                        <?= htmlspecialchars($bill['consultation_items'] ?? '') ?>
                                    </div>
                                </td>
                                <td class="font-semibold text-purple">
                                    <?= formatCurrency($bill['consultation_total'] ?? 0) ?>
                                </td>
                                <td class="font-semibold"><?= formatCurrency($bill['total_amount'] ?? 0) ?></td>
                                <td class="text-green"><?= formatCurrency($bill['paid_amount'] ?? 0) ?></td>
                                <td>
                                    <?php if (($bill['balance'] ?? 0) > 0): ?>
                                        <span class="text-red font-semibold"><?= formatCurrency($bill['balance'] ?? 0) ?></span>
                                    <?php else: ?>
                                        <span class="text-green"><?= formatCurrency(0) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($bill['status'] ?? 'pending') ?>">
                                        <i class="fas <?= getStatusIcon($bill['status'] ?? 'pending') ?>"></i>
                                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs">
                                    <?= date('M d, Y', strtotime($bill['created_at'] ?? 'now')) ?>
                                    <div class="text-gray-400" style="font-size:0.55rem;">
                                        <?= date('h:i A', strtotime($bill['created_at'] ?? 'now')) ?>
                                    </div>
                                </td>
                                <td>
                                    <a href="view_bill.php?id=<?= $bill['id'] ?>&branch=<?= $branch_id ?>" 
                                       class="text-blue text-xs font-semibold" style="text-decoration:none;">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Summary Row -->
            <div style="padding:12px 20px;background:var(--page-bg-body);border-top:2px solid var(--page-border);display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;font-size:0.75rem;">
                <div style="color:var(--page-text-secondary);">
                    <span class="font-semibold">Total Bills:</span> <?= number_format($total_bills) ?>
                    <span style="margin:0 8px;color:var(--page-border);">|</span>
                    <span class="font-semibold">Consult Revenue:</span> <span class="text-purple font-semibold"><?= formatCurrency($total_consultation_revenue) ?></span>
                    <?php if ($branch_id > 0): ?>
                        <span style="margin:0 8px;color:var(--page-border);">|</span>
                        <span class="font-semibold">Branch:</span> <?= htmlspecialchars($branch_name) ?>
                    <?php endif; ?>
                </div>
                <div class="text-gray-400">
                    <i class="fas fa-info-circle"></i> Using <strong class="text-blue">bills</strong> table with <strong class="text-blue">bill_items</strong> join
                </div>
            </div>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-stethoscope"></i>
                <h3>No Consultation Bills Found</h3>
                <p>Try adjusting your filters or <a href="consultation_bills.php?branch=<?= $branch_id ?>" class="text-blue" style="text-decoration:underline;">reset all filters</a></p>
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
            if (body) body.style.background = '#EFF6FF';
            if (mainContent) mainContent.style.background = '#EFF6FF';
        }
    }

    enforceDarkModeBackground();
    document.addEventListener('darkModeChanged', function() { setTimeout(enforceDarkModeBackground, 50); });
    new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            if (m.attributeName === 'data-theme') enforceDarkModeBackground();
        });
    }).observe(document.documentElement, { attributes: true });

    console.log('%c🔵 Braick - Consultation Bills', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🎨 Blue/Purple page header + 5 summary cards', 'font-size:13px; color:#7C3AED;');
    console.log('%c🌙 FULL DARK MODE works', 'font-size:13px; color:#3B82F6;');
    console.log('%c💡 USING: bills + bill_items (item_type=consultation)', 'font-size:13px; color:#059669;');
</script>

</body>
</html>