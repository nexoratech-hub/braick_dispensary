<?php
// ================================================================
// FILE: frontend/pages/pharmacy/low_stock.php
// PHARMACY - LOW STOCK & OUT OF STOCK REPORT
// USING NEW DATABASE: dispensary_db
// WITH SHARED HEADER & AUTO-DISMISS MESSAGE
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT PHARMACY
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE CONNECTION - NEW DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET FILTERS
// ================================================================
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, low, out
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ================================================================
// BUILD QUERY - SORT: EXPIRING FIRST - NEW DATABASE
// ================================================================
$query = "
    SELECT 
        id,
        medication_name,
        category,
        unit,
        quantity,
        reorder_level,
        selling_price,
        unit_cost,
        supplier,
        expiry_date,
        batch_number,
        status,
        created_at,
        updated_at,
        DATEDIFF(expiry_date, CURDATE()) as days_remaining
    FROM medications_inventory 
    WHERE branch_id = ?
";

$params = [$user_branch_id];

// Only show active medicines
$query .= " AND status = 'active'";

// Filter by stock status
if ($filter === 'low') {
    $query .= " AND quantity <= reorder_level AND quantity > 0";
} elseif ($filter === 'out') {
    $query .= " AND quantity = 0";
} elseif ($filter === 'all') {
    $query .= " AND quantity <= reorder_level";
}

// Search filter
if (!empty($search)) {
    $query .= " AND medication_name LIKE ?";
    $params[] = "%$search%";
}

// Sort by expiry date first (expiring soon at top)
$query .= " ORDER BY 
    CASE 
        WHEN expiry_date IS NULL THEN 2
        WHEN expiry_date < CURDATE() THEN 0  -- Expired (highest priority)
        WHEN expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1  -- Expiring in 7 days
        WHEN expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 3  -- Expiring in 30 days
        ELSE 4  -- Valid for longer
    END ASC,
    expiry_date ASC,
    quantity ASC,
    medication_name ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS - NEW DATABASE
// ================================================================

// Low Stock Count (quantity <= reorder_level AND quantity > 0)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND quantity <= reorder_level AND quantity > 0 AND status = 'active'
");
$stmt->execute([$user_branch_id]);
$low_stock_count = $stmt->fetch()['count'] ?? 0;

// Out of Stock Count (quantity = 0)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND quantity = 0 AND status = 'active'
");
$stmt->execute([$user_branch_id]);
$out_of_stock_count = $stmt->fetch()['count'] ?? 0;

// Total Low Stock (both low and out)
$total_low_stock = $low_stock_count + $out_of_stock_count;

// ================================================================
// GET EXPIRING SOON COUNT - NEW DATABASE
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND status = 'active'
    AND expiry_date IS NOT NULL 
    AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute([$user_branch_id]);
$expiring_soon_count = $stmt->fetch()['count'] ?? 0;

// ================================================================
// GET UNREAD NOTIFICATIONS - NEW DATABASE
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC STYLES -->
<!-- ================================================================ -->
<style>
    :root {
        --primary: #0B5ED7;
        --primary-dark: #0A3D8A;
        --primary-light: #E8F0FE;
        --success: #059669;
        --success-dark: #047857;
        --success-light: #D1FAE5;
        --warning: #D97706;
        --warning-light: #FEF3C7;
        --danger: #DC2626;
        --danger-light: #FEE2E2;
        --purple: #7C3AED;
        --purple-light: #EDE9FE;
        
        --bg-body: #F1F5F9;
        --bg-card: #FFFFFF;
        --bg-nav: #FFFFFF;
        --border-color: #E2E8F0;
        --text-primary: #0F172A;
        --text-secondary: #475569;
        --text-muted: #94A3B8;
        --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
    }
    
    [data-theme="dark"] {
        --bg-body: #0F172A;
        --bg-card: #1E293B;
        --bg-nav: #1E293B;
        --border-color: #334155;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --text-muted: #64748B;
        --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --shadow-lg: 0 8px 30px rgba(0,0,0,0.4);
    }
    
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
        font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
        background: var(--bg-body);
        color: var(--text-primary);
        transition: background 0.3s ease, color 0.3s ease;
    }
    
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: var(--bg-body); }
    ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
    
    /* ================================================================
       MAIN CONTENT
       ================================================================ */
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 28px 32px;
        min-height: calc(100vh - 68px);
    }
    
    /* ================================================================
       PAGE HEADER - ORANGE THEME
       ================================================================ */
    .page-header {
        background: linear-gradient(135deg, #D97706, #B45309);
        border-radius: 16px;
        padding: 24px 32px;
        margin-bottom: 28px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 4px 20px rgba(217, 119, 6, 0.25);
        position: relative;
        overflow: hidden;
    }
    
    .page-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }
    
    .page-header .page-title {
        color: white;
        font-size: 1.8rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }
    
    .page-header .page-title i {
        font-size: 2rem;
        opacity: 0.9;
    }
    
    .page-header .page-subtitle {
        color: rgba(255,255,255,0.85);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }
    
    .page-header .page-subtitle strong {
        color: white;
        font-weight: 600;
    }
    
    .page-header .branch-tag {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.1);
    }
    
    .page-header .btn-outline-light {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 16px;
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.82rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(4px);
        position: relative;
        z-index: 1;
    }
    
    .page-header .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }
    
    .page-header .badge-count {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.1);
    }
    
    /* ================================================================
       STATS CARDS
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        border-radius: 16px;
        padding: 18px 20px;
        border: none;
        transition: all 0.3s;
        color: white;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        min-height: 90px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    
    .stat-card:active {
        transform: scale(0.97);
    }
    
    .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .stat-card.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
    .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    
    .stat-card .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        background: rgba(255,255,255,0.15);
        color: white;
        flex-shrink: 0;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover .stat-icon {
        transform: scale(1.1) rotate(-5deg);
        background: rgba(255,255,255,0.25);
    }
    
    .stat-card .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: white;
        line-height: 1.2;
    }
    
    .stat-card .stat-label {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.85);
        font-weight: 500;
        margin-top: 2px;
    }
    
    .stat-card .stat-trend {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: rgba(255,255,255,0.15);
        color: white;
        display: inline-block;
        margin-top: 4px;
    }
    
    /* ================================================================
       CARDS
       ================================================================ */
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }
    
    .card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .card-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .card-title .title-orange { color: var(--warning); }
    .card-title .title-red { color: var(--danger); }
    .card-title .title-blue { color: var(--primary); }
    
    .result-count {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .result-count strong {
        color: var(--primary);
    }
    
    /* ================================================================
       FILTERS
       ================================================================ */
    .filter-group {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }
    
    .filter-btn {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        border: 2px solid var(--border-color);
        background: transparent;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .filter-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .filter-btn.active {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }
    
    .filter-btn.active:hover {
        background: var(--primary-dark);
        border-color: var(--primary-dark);
    }
    
    .filter-btn.orange.active {
        background: var(--warning);
        border-color: var(--warning);
    }
    
    .filter-btn.red.active {
        background: var(--danger);
        border-color: var(--danger);
    }
    
    /* ================================================================
       SEARCH FORM
       ================================================================ */
    .search-form {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .search-form input[type="text"] {
        padding: 8px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
        flex: 1;
        min-width: 200px;
    }
    
    .search-form input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .search-form .btn-search {
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        border: none;
        background: var(--primary);
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .search-form .btn-search:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .search-form .btn-reset {
        padding: 8px 16px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        border: 2px solid var(--border-color);
        background: transparent;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .search-form .btn-reset:hover {
        border-color: var(--danger);
        color: var(--danger);
    }
    
    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
        padding: 6px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.78rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .btn-outline:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .btn-sm {
        padding: 3px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    
    /* ================================================================
       TABLE
       ================================================================ */
    .table-wrap {
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    
    .data-table thead th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: white;
        background: var(--primary);
        border-bottom: 3px solid var(--primary-dark);
        white-space: nowrap;
    }
    
    .data-table thead th:first-child {
        border-radius: 8px 0 0 0;
    }
    
    .data-table thead th:last-child {
        border-radius: 0 8px 0 0;
    }
    
    .data-table tbody tr:nth-child(even) {
        background: var(--primary-light);
    }
    
    .data-table tbody tr:hover td {
        background: var(--success-light);
    }
    
    [data-theme="dark"] .data-table tbody tr:nth-child(even) {
        background: #1E293B;
    }
    
    [data-theme="dark"] .data-table tbody tr:hover td {
        background: #1A3A2A;
    }
    
    .data-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    /* ================================================================
       BADGES
       ================================================================ */
    .stock-badge {
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .stock-badge.low {
        background: var(--warning-light);
        color: var(--warning);
        animation: pulse-low 1.5s infinite;
    }
    
    .stock-badge.out {
        background: var(--danger-light);
        color: var(--danger);
        animation: pulse-low 1s infinite;
    }
    
    .stock-badge.in-stock {
        background: var(--success-light);
        color: var(--success);
    }
    
    @keyframes pulse-low {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }
    
    [data-theme="dark"] .stock-badge.low {
        background: #3D2E0A;
        color: #FBBF24;
    }
    
    [data-theme="dark"] .stock-badge.out {
        background: #3A1A1A;
        color: #F87171;
    }
    
    [data-theme="dark"] .stock-badge.in-stock {
        background: #1A3A2A;
        color: #34D399;
    }
    
    .status-badge {
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .status-badge.active {
        background: var(--success-light);
        color: var(--success);
    }
    
    .status-badge.inactive {
        background: var(--danger-light);
        color: var(--danger);
    }
    
    [data-theme="dark"] .status-badge.active {
        background: #1A3A2A;
        color: #34D399;
    }
    
    [data-theme="dark"] .status-badge.inactive {
        background: #3A1A1A;
        color: #F87171;
    }
    
    .expiry-badge {
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    
    .expiry-badge.valid {
        background: var(--success-light);
        color: var(--success);
    }
    
    .expiry-badge.expiring {
        background: var(--warning-light);
        color: var(--warning);
        animation: pulse-low 1.5s infinite;
    }
    
    .expiry-badge.expired {
        background: var(--danger-light);
        color: var(--danger);
        animation: pulse-low 1s infinite;
    }
    
    .batch-number {
        font-family: monospace;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 4px;
        background: var(--primary-light);
        color: var(--primary);
    }
    
    [data-theme="dark"] .batch-number {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    /* ================================================================
       ACTION BUTTON - ONLY VIEW
       ================================================================ */
    .action-btn {
        padding: 4px 12px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .action-btn.view {
        background: var(--primary);
        color: white;
    }
    
    .action-btn.view:hover {
        background: var(--primary-dark);
        transform: scale(1.05);
    }
    
    /* ================================================================
       ROW STYLES
       ================================================================ */
    .row-expiring-soon {
        border-left: 4px solid var(--warning) !important;
        background: var(--warning-light) !important;
    }
    
    .row-expired {
        border-left: 4px solid var(--danger) !important;
        background: var(--danger-light) !important;
    }
    
    .row-expiring-soon td:first-child {
        border-radius: 8px 0 0 8px;
    }
    
    .row-expiring-soon td:last-child {
        border-radius: 0 8px 8px 0;
    }
    
    [data-theme="dark"] .row-expiring-soon {
        background: #3D2E0A !important;
        border-left-color: #FBBF24 !important;
    }
    
    [data-theme="dark"] .row-expired {
        background: #3A1A1A !important;
        border-left-color: #F87171 !important;
    }
    
    /* ================================================================
       DAYS REMAINING
       ================================================================ */
    .days-remaining {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .days-remaining.good {
        background: var(--success-light);
        color: var(--success);
    }
    
    .days-remaining.warning {
        background: var(--warning-light);
        color: var(--warning);
        animation: pulse-low 1.5s infinite;
    }
    
    .days-remaining.danger {
        background: var(--danger-light);
        color: var(--danger);
        animation: pulse-low 1s infinite;
    }
    
    [data-theme="dark"] .days-remaining.good {
        background: #1A3A2A;
        color: #34D399;
    }
    
    [data-theme="dark"] .days-remaining.warning {
        background: #3D2E0A;
        color: #FBBF24;
    }
    
    [data-theme="dark"] .days-remaining.danger {
        background: #3A1A1A;
        color: #F87171;
    }
    
    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 3rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 12px;
    }
    
    .empty-state p {
        font-size: 0.95rem;
    }
    
    .empty-state .sub {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-top: 4px;
    }
    
    /* ================================================================
       MESSAGE - WITH AUTO-DISMISS
       ================================================================ */
    .message-box {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        animation: slideDown 0.4s ease;
        transition: opacity 0.5s ease, transform 0.5s ease;
    }
    
    .message-box.hide {
        opacity: 0;
        transform: translateY(-10px);
        max-height: 0;
        padding: 0 20px;
        margin: 0;
        overflow: hidden;
    }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .message-box.success {
        background: var(--success-light);
        color: #065F46;
        border: 2px solid #6EE7B7;
    }
    
    .message-box.error {
        background: var(--danger-light);
        color: #991B1B;
        border: 2px solid #FCA5A5;
    }
    
    .message-box i {
        font-size: 1.3rem;
    }
    
    [data-theme="dark"] .message-box.success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }
    
    [data-theme="dark"] .message-box.error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }
    
    /* ================================================================
       FOOTER
       ================================================================ */
    .footer {
        padding: 14px 0;
        border-top: 1px solid var(--border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .footer .footer-brand { color: var(--primary); font-weight: 600; }
    
    /* ================================================================
       TOAST
       ================================================================ */
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 12px 18px;
        border-radius: 10px;
        z-index: 999;
        max-width: 380px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
        box-shadow: var(--shadow-lg);
        font-size: 0.85rem;
    }
    
    .toast-custom.show {
        transform: translateY(0);
        opacity: 1;
    }
    
    .toast-custom.success { background: var(--success); }
    .toast-custom.error { background: var(--danger); }
    .toast-custom.info { background: var(--primary); }
    .toast-custom.warning { background: #D97706; }
    
    /* ================================================================
       ANIMATIONS
       ================================================================ */
    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }
    
    .animate-fade-in-up:nth-child(1) { animation-delay: 0.05s; }
    .animate-fade-in-up:nth-child(2) { animation-delay: 0.1s; }
    .animate-fade-in-up:nth-child(3) { animation-delay: 0.15s; }
    .animate-fade-in-up:nth-child(4) { animation-delay: 0.2s; }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* ================================================================
       RECOMMENDATIONS GRID
       ================================================================ */
    .grid {
        display: grid;
        gap: 16px;
    }
    
    .grid-cols-1 { grid-template-columns: 1fr; }
    .grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
    
    @media (min-width: 768px) {
        .md\:grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
    }
    
    .gap-4 { gap: 16px; }
    .mt-4 { margin-top: 16px; }
    
    .p-4 { padding: 16px; }
    .border { border: 1px solid var(--border-color); }
    .rounded-lg { border-radius: 10px; }
    
    .text-sm { font-size: 0.85rem; }
    .text-xs { font-size: 0.7rem; }
    .text-gray-600 { color: var(--text-secondary); }
    .text-gray-400 { color: var(--text-muted); }
    .text-red-500 { color: var(--danger); }
    .text-red-600 { color: var(--danger); }
    .text-orange-600 { color: var(--warning); }
    .text-green-600 { color: var(--success); }
    
    .font-semibold { font-weight: 600; }
    .font-bold { font-weight: 700; }
    .block { display: block; }
    .mt-1 { margin-top: 4px; }
    .mb-2 { margin-bottom: 8px; }
    .mr-1 { margin-right: 4px; }
    .mr-2 { margin-right: 8px; }
    .ml-2 { margin-left: 8px; }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .main-content { margin-left: 0; padding: 16px; }
    }
    
    @media (max-width: 768px) {
        .page-header { padding: 16px 18px; }
        .page-header .page-title { font-size: 1.3rem; }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .search-form {
            flex-direction: column;
            align-items: stretch;
        }
        .search-form input[type="text"] {
            min-width: 100%;
        }
        .filter-group {
            justify-content: center;
        }
        .card {
            padding: 12px 14px;
        }
        .data-table {
            font-size: 0.7rem;
        }
        .data-table th,
        .data-table td {
            padding: 5px 8px;
        }
        .stat-card .stat-number {
            font-size: 1.3rem;
        }
        .stat-card {
            padding: 12px 16px;
            min-height: 70px;
        }
        .stat-card .stat-icon {
            width: 36px;
            height: 36px;
            font-size: 1rem;
        }
        .grid-cols-3 {
            grid-template-columns: 1fr 1fr;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .stat-card .stat-number {
            font-size: 1.1rem;
        }
        .stat-card .stat-label {
            font-size: 0.6rem;
        }
        .stat-card .stat-icon {
            width: 30px;
            height: 30px;
            font-size: 0.8rem;
        }
        .stat-card {
            padding: 8px 12px;
            min-height: 60px;
        }
        .data-table {
            font-size: 0.65rem;
        }
        .data-table th,
        .data-table td {
            padding: 4px 6px;
        }
        .main-content { padding: 10px; }
        .page-header .page-title { font-size: 1.1rem; }
        .grid-cols-3 {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-exclamation-triangle mr-2"></i> Low Stock Report
                <span class="badge-count"><?= $total_low_stock ?> need attention</span>
            </h1>
            <p class="page-subtitle">
                View all medicines with low or out of stock
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <?php if ($expiring_soon_count > 0): ?>
                <span class="branch-tag" style="background:rgba(239,68,68,0.3);color:#FCA5A5;border-color:rgba(239,68,68,0.2);">
                    <i class="fas fa-clock mr-1"></i> <?= $expiring_soon_count ?> expiring soon
                </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="inventory.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <a href="low_stock.php?filter=all" class="stat-card orange">
            <div>
                <p class="stat-label">Total Low Stock</p>
                <p class="stat-number"><?= number_format($total_low_stock) ?></p>
                <span class="stat-trend"><i class="fas fa-warehouse"></i> Need attention</span>
            </div>
            <div class="stat-icon"><i class="fas fa-warehouse"></i></div>
        </a>
        
        <a href="low_stock.php?filter=low" class="stat-card orange">
            <div>
                <p class="stat-label">Low Stock</p>
                <p class="stat-number"><?= number_format($low_stock_count) ?></p>
                <span class="stat-trend"><i class="fas fa-exclamation-triangle"></i> Below reorder</span>
            </div>
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        </a>
        
        <a href="low_stock.php?filter=out" class="stat-card red">
            <div>
                <p class="stat-label">Out of Stock</p>
                <p class="stat-number"><?= number_format($out_of_stock_count) ?></p>
                <span class="stat-trend"><i class="fas fa-times-circle"></i> Empty</span>
            </div>
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        </a>
        
        <a href="low_stock.php?filter=all" class="stat-card purple">
            <div>
                <p class="stat-label">Expiring Soon</p>
                <p class="stat-number"><?= number_format($expiring_soon_count) ?></p>
                <span class="stat-trend"><i class="fas fa-clock"></i> Within 30 days</span>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE - AUTO-DISMISS AFTER 5 SECONDS -->
    <!-- ================================================================ -->
    <?php if (count($medicines) == 0 && empty($search)): ?>
        <div class="message-box success" id="successMessage">
            <i class="fas fa-check-circle"></i>
            🎉 All medicines are well stocked! No low stock items found.
        </div>
        <script>
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                var msg = document.getElementById('successMessage');
                if (msg) {
                    msg.classList.add('hide');
                    setTimeout(function() {
                        msg.style.display = 'none';
                    }, 500);
                }
            }, 5000);
        </script>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FILTERS & SEARCH -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="filter-group">
            <a href="low_stock.php?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-list"></i> All
            </a>
            <a href="low_stock.php?filter=low" class="filter-btn orange <?= $filter === 'low' ? 'active' : '' ?>">
                <i class="fas fa-exclamation-triangle"></i> Low Stock
            </a>
            <a href="low_stock.php?filter=out" class="filter-btn red <?= $filter === 'out' ? 'active' : '' ?>">
                <i class="fas fa-times-circle"></i> Out of Stock
            </a>
        </div>
        
        <form method="GET" class="search-form">
            <input type="hidden" name="filter" value="<?= $filter ?>">
            
            <input type="text" name="search" placeholder="🔍 Search medicine..." 
                   value="<?= htmlspecialchars($search) ?>">
            
            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i> Search
            </button>
            
            <a href="low_stock.php?filter=<?= $filter ?>" class="btn-reset">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- LOW STOCK TABLE - SORTED BY EXPIRY DATE (EXPIRING FIRST) -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-orange mr-2"></i>
                Medicines Needing Restock
                <span class="result-count ml-2">(<strong><?= number_format(count($medicines)) ?></strong> record(s))</span>
                <?php if ($expiring_soon_count > 0): ?>
                <span class="ml-2 inline-flex bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs border border-yellow-200 animate-pulse">
                    <i class="fas fa-clock mr-1"></i> Expiring soon shown first
                </span>
                <?php endif; ?>
            </h3>
        </div>
        
        <?php if (count($medicines) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="border-radius: 8px 0 0 0;">#</th>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Current Qty</th>
                            <th>Reorder Level</th>
                            <th>Status</th>
                            <th>Expiry Date</th>
                            <th>Days Left</th>
                            <th>Batch Number</th>
                            <th>Supplier</th>
                            <th style="border-radius: 0 8px 0 0;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach ($medicines as $item): ?>
                            <?php
                                $stock_status = 'in-stock';
                                $stock_label = 'In Stock';
                                $stock_icon = 'fa-check-circle';
                                
                                if ($item['quantity'] <= 0) {
                                    $stock_status = 'out';
                                    $stock_label = 'Out of Stock';
                                    $stock_icon = 'fa-times-circle';
                                } elseif ($item['quantity'] <= $item['reorder_level']) {
                                    $stock_status = 'low';
                                    $stock_label = 'Low Stock';
                                    $stock_icon = 'fa-exclamation-triangle';
                                }
                                
                                // Expiry status
                                $expiry_status = 'valid';
                                $days_remaining = '-';
                                $days_class = 'good';
                                $row_class = '';
                                
                                if (!empty($item['expiry_date'])) {
                                    $days_remaining = (int)$item['days_remaining'];
                                    
                                    if ($days_remaining < 0) {
                                        $expiry_status = 'expired';
                                        $days_class = 'danger';
                                        $row_class = 'row-expired';
                                    } elseif ($days_remaining <= 7) {
                                        $expiry_status = 'expiring';
                                        $days_class = 'danger';
                                        $row_class = 'row-expiring-soon';
                                    } elseif ($days_remaining <= 30) {
                                        $expiry_status = 'expiring';
                                        $days_class = 'warning';
                                        $row_class = 'row-expiring-soon';
                                    } else {
                                        $expiry_status = 'valid';
                                        $days_class = 'good';
                                    }
                                }
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td><?= $counter++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['medication_name']) ?></strong>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></div>
                                </td>
                                <td><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                <td>
                                    <strong style="color: <?= $item['quantity'] <= 0 ? 'var(--danger)' : ($item['quantity'] <= $item['reorder_level'] ? 'var(--warning)' : 'var(--success)') ?>;">
                                        <?= $item['quantity'] ?>
                                    </strong>
                                    <?php if ($item['quantity'] <= $item['reorder_level'] && $item['quantity'] > 0): ?>
                                        <span class="text-xs text-gray-400 block">Reorder: <?= $item['reorder_level'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $item['reorder_level'] ?></td>
                                <td>
                                    <span class="stock-badge <?= $stock_status ?>">
                                        <i class="fas <?= $stock_icon ?>"></i>
                                        <?= $stock_label ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($item['expiry_date'])): ?>
                                        <span class="expiry-badge <?= $expiry_status ?>">
                                            <?= date('M d, Y', strtotime($item['expiry_date'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['expiry_date']) && $days_remaining !== '-'): ?>
                                        <span class="days-remaining <?= $days_class ?>">
                                            <?php if ($days_remaining < 0): ?>
                                                <i class="fas fa-skull"></i> EXPIRED
                                            <?php elseif ($days_remaining <= 7): ?>
                                                <i class="fas fa-clock"></i> <?= $days_remaining ?> days ⚠️
                                            <?php elseif ($days_remaining <= 30): ?>
                                                <i class="fas fa-clock"></i> <?= $days_remaining ?> days
                                            <?php else: ?>
                                                <i class="fas fa-check"></i> <?= $days_remaining ?> days
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['batch_number'])): ?>
                                        <span class="batch-number"><?= htmlspecialchars($item['batch_number']) ?></span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($item['supplier'] ?? 'N/A') ?></td>
                                <td>
                                    <a href="view_inventory.php?id=<?= $item['id'] ?>&type=medicine" 
                                       class="action-btn view" title="View Details">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                <p>No low stock medicines found</p>
                <p class="sub">All medicines are well stocked! 🎉</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- RECOMMENDATIONS -->
    <!-- ================================================================ -->
    <?php if (count($medicines) > 0): ?>
    <div class="card mt-4 animate-fade-in-up" style="border-color: var(--warning);">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-lightbulb" style="color: var(--warning);"></i>
                Restock Recommendations
            </h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 border rounded-lg" style="border-color: var(--border-color);">
                <h4 class="font-semibold text-red-600 mb-2">
                    <i class="fas fa-skull mr-1"></i> Expired
                </h4>
                <p class="text-sm text-gray-600">
                    <?php 
                        $expired_items = array_filter($medicines, function($item) {
                            return !empty($item['expiry_date']) && $item['days_remaining'] < 0;
                        });
                    ?>
                    <strong><?= count($expired_items) ?></strong> medicine(s) have expired.
                    <?php if (count($expired_items) > 0): ?>
                        <span class="block text-xs text-red-500 mt-1 font-semibold">
                            ⚠️ Should be removed from inventory!
                        </span>
                        <span class="block text-xs text-gray-400 mt-1">
                            <?php 
                                $names = array_slice(array_column($expired_items, 'medication_name'), 0, 5);
                                echo implode(', ', $names);
                                if (count($expired_items) > 5) echo ' and ' . (count($expired_items) - 5) . ' more';
                            ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="p-4 border rounded-lg" style="border-color: var(--border-color);">
                <h4 class="font-semibold text-orange-600 mb-2">
                    <i class="fas fa-clock mr-1"></i> Expiring Soon (30 days)
                </h4>
                <p class="text-sm text-gray-600">
                    <?php 
                        $expiring_items = array_filter($medicines, function($item) {
                            return !empty($item['expiry_date']) && $item['days_remaining'] >= 0 && $item['days_remaining'] <= 30;
                        });
                    ?>
                    <strong><?= count($expiring_items) ?></strong> medicine(s) expiring within 30 days.
                    <?php if (count($expiring_items) > 0): ?>
                        <span class="block text-xs text-gray-400 mt-1">
                            <?php 
                                $names = array_slice(array_column($expiring_items, 'medication_name'), 0, 5);
                                echo implode(', ', $names);
                                if (count($expiring_items) > 5) echo ' and ' . (count($expiring_items) - 5) . ' more';
                            ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="p-4 border rounded-lg" style="border-color: var(--border-color);">
                <h4 class="font-semibold text-orange-600 mb-2">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Out of Stock
                </h4>
                <p class="text-sm text-gray-600">
                    <?php 
                        $out_items = array_filter($medicines, function($item) {
                            return $item['quantity'] <= 0;
                        });
                    ?>
                    <strong><?= count($out_items) ?></strong> medicine(s) completely out of stock.
                    <?php if (count($out_items) > 0): ?>
                        <span class="block text-xs text-gray-400 mt-1">
                            <?php 
                                $names = array_slice(array_column($out_items, 'medication_name'), 0, 5);
                                echo implode(', ', $names);
                                if (count($out_items) > 5) echo ' and ' . (count($out_items) - 5) . ' more';
                            ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Low Stock Report
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('h:i:s A') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE - SYNC WITH HEADER
    // ================================================================
    document.addEventListener('darkModeChanged', function(e) {
        var isDark = e.detail && e.detail.isDark;
        var html = document.documentElement;
        
        if (isDark) {
            html.setAttribute('data-theme', 'dark');
        } else {
            html.removeAttribute('data-theme');
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE - SYNC WITH HEADER
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggleBtn');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar) sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // DATE & TIME - UPDATE LIVE
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) {
            ftEl.textContent = timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var searchInput = document.querySelector('.search-form input[type="text"]');
            searchInput?.focus();
            searchInput?.select();
        }
        if (e.key === 'Escape') {
            var searchInput = document.querySelector('.search-form input[type="text"]');
            if (searchInput && document.activeElement === searchInput) {
                searchInput.value = '';
                searchInput.blur();
            }
        }
    });

    // ================================================================
    // CONSOLE
    // ================================================================
    console.log('%c💊 Braick - Low Stock Report (NEW DATABASE)', 'font-size:18px; font-weight:bold; color:#D97706;');
    console.log('%c✅ Using SHARED HEADER with date & time', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Success message auto-dismiss after 5 seconds', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📦 Low Stock: <?= $low_stock_count ?> | Out of Stock: <?= $out_of_stock_count ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c⏰ Expiring Soon: <?= $expiring_soon_count ?> (shown first)', 'font-size:13px; color:#DC2626;');
</script>
</body>
</html>