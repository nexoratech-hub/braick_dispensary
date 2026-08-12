<?php
// ================================================================
// FILE: frontend/pages/admin/dashboard.php
// SUPER ADMIN DASHBOARD - MODERN DESIGN
// 8 CARDS ONLY: Revenue, Expenses, Profit, Prescriptions, OTC, Stock, Expiry, Patients
// FIXED: Only bills from existing patients
// FIXED: Column 'branch_id' ambiguous - added table prefixes
// SOLID COLORS WITH OPACITY - SOFT MODERN LOOK
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_name = 'All Branches';

// ================================================================
// FUNCTION TO CHECK IF COLUMN EXISTS
// ================================================================
function columnExists($db, $table, $column) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM $table LIKE '$column'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// ================================================================
// BRANCH NAME
// ================================================================
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id = (int)$selected_branch_id;
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
    }
} else {
    $selected_branch_id = 'all';
}

// ================================================================
// ✅ BRANCH FILTERS - WITH TABLE PREFIXES
// ================================================================

// For patient_bills (pb)
$branch_filter_pb = "";
if ($selected_branch_id !== 'all') {
    $branch_filter_pb = " AND pb.branch_id = " . (int)$selected_branch_id;
}

// For other tables (no prefix needed, or use correct prefix)
$branch_filter = "";
if ($selected_branch_id !== 'all') {
    $branch_filter = " AND branch_id = " . (int)$selected_branch_id;
}

$today = date('Y-m-d');

// ================================================================
// ✅ 1. PATIENT BILLS REVENUE - ONLY EXISTING PATIENTS
// ✅ FIXED: Uses pb.branch_id
// ================================================================
$stmt = $db->query("
    SELECT COALESCE(SUM(pb.total_amount), 0) as total 
    FROM patient_bills pb
    INNER JOIN patients p ON pb.patient_id = p.id
    WHERE pb.status = 'paid'
    $branch_filter_pb
");
$patient_bills_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// ✅ 2. OTC REVENUE - ALL OTC (walk-in customers)
// ================================================================
$stmt = $db->query("
    SELECT COALESCE(SUM(net_amount), 0) as total 
    FROM otc_sales 
    WHERE payment_status = 'paid'
    $branch_filter
");
$otc_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// ✅ 3. TOTAL REVENUE
// ================================================================
$total_revenue = $patient_bills_revenue + $otc_revenue;

// ================================================================
// ✅ 4. TOTAL EXPENSES
// ================================================================
$expenses_table_exists = false;
try {
    $stmt = $db->query("SHOW TABLES LIKE 'expenses'");
    if ($stmt->rowCount() > 0) {
        $expenses_table_exists = true;
    }
} catch (Exception $e) {
    $expenses_table_exists = false;
}

$total_expenses = 0;
if ($expenses_table_exists) {
    $stmt = $db->query("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM expenses 
        WHERE status = 'paid'
        $branch_filter
    ");
    $total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
}

// ================================================================
// ✅ 5. NET PROFIT
// ================================================================
$net_profit = $total_revenue - $total_expenses;
$profit_percentage = ($total_revenue > 0) ? round(($net_profit / $total_revenue) * 100, 1) : 0;

// ================================================================
// ✅ 6. PRESCRIPTION AMOUNT - ONLY EXISTING PATIENTS
// ✅ FIXED: Uses pb.branch_id
// ================================================================
$stmt = $db->query("
    SELECT COALESCE(SUM(bi.total_price), 0) as total
    FROM bill_items bi
    INNER JOIN patient_bills pb ON bi.bill_id = pb.id
    INNER JOIN patients p ON pb.patient_id = p.id
    WHERE bi.item_type = 'medication'
    AND pb.status = 'paid'
    $branch_filter_pb
");
$prescription_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Prescription count - ONLY EXISTING PATIENTS
$stmt = $db->query("
    SELECT COUNT(DISTINCT pb.id) as count
    FROM patient_bills pb
    INNER JOIN bill_items bi ON bi.bill_id = pb.id
    INNER JOIN patients p ON pb.patient_id = p.id
    WHERE bi.item_type = 'medication'
    AND pb.status = 'paid'
    $branch_filter_pb
");
$prescription_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// ✅ 7. OTC SALES DETAILS
// ================================================================
$stmt = $db->query("
    SELECT COUNT(*) as count, 
           COALESCE(SUM(net_amount), 0) as total
    FROM otc_sales 
    WHERE payment_status = 'paid'
    $branch_filter
");
$otc_data = $stmt->fetch(PDO::FETCH_ASSOC);
$otc_count = $otc_data['count'] ?? 0;
$otc_total = $otc_data['total'] ?? 0;

// ================================================================
// ✅ 8. STOCK - Out of Stock & Low Stock
// ================================================================
$stmt = $db->query("
    SELECT 
        SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN quantity > 0 AND quantity <= reorder_level THEN 1 ELSE 0 END) as low_stock
    FROM medications_inventory 
    WHERE status = 'active' 
    $branch_filter
");
$stock_data = $stmt->fetch(PDO::FETCH_ASSOC);
$out_of_stock = $stock_data['out_of_stock'] ?? 0;
$low_stock = $stock_data['low_stock'] ?? 0;

// ================================================================
// ✅ 9. EXPIRY - Expired & Expiring Soon
// ================================================================
$today_date = date('Y-m-d');
$stmt = $db->query("
    SELECT 
        SUM(CASE WHEN expiry_date < '$today_date' AND expiry_date IS NOT NULL THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN expiry_date BETWEEN '$today_date' AND DATE_ADD('$today_date', INTERVAL 30 DAY) AND expiry_date IS NOT NULL THEN 1 ELSE 0 END) as expiring_soon
    FROM medications_inventory 
    WHERE status = 'active' 
    $branch_filter
");
$expiry_data = $stmt->fetch(PDO::FETCH_ASSOC);
$expired = $expiry_data['expired'] ?? 0;
$expiring_soon = $expiry_data['expiring_soon'] ?? 0;

// ================================================================
// ✅ 10. PATIENTS - Total Patients
// ================================================================
$stmt = $db->query("
    SELECT COUNT(*) as count 
    FROM patients 
    WHERE 1=1 
    $branch_filter
");
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Today's patients
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM patients 
    WHERE DATE(created_at) = ? 
    $branch_filter
");
$stmt->execute([$today]);
$today_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// CHART DATA - Last 7 Days Revenue (ONLY EXISTING PATIENTS)
// ✅ FIXED: Uses pb.branch_id
// ================================================================
$chart_labels = [];
$chart_values = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D', strtotime($date));
    
    $daily_total = 0;
    
    // Patient bills - ONLY EXISTING PATIENTS
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(pb.total_amount), 0) as total 
        FROM patient_bills pb
        INNER JOIN patients p ON pb.patient_id = p.id
        WHERE DATE(pb.created_at) = ? 
        AND pb.status = 'paid'
        $branch_filter_pb
    ");
    $stmt->execute([$date]);
    $daily_total += $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // OTC Sales
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(net_amount), 0) as total 
        FROM otc_sales 
        WHERE DATE(created_at) = ? 
        AND payment_status = 'paid'
        $branch_filter
    ");
    $stmt->execute([$date]);
    $daily_total += $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $chart_values[] = (float)$daily_total;
}

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active'");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT PATIENTS
// ================================================================
$recent_patients = [];
$has_patient_branch = columnExists($db, 'patients', 'branch_id');

if ($selected_branch_id !== 'all' && $has_patient_branch) {
    $stmt = $db->prepare("
        SELECT p.*, b.name as branch_name 
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE p.branch_id = ?
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([(int)$selected_branch_id]);
    $recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->query("
        SELECT p.*, b.name as branch_name 
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// GET RECENT ACTIVITIES
// ================================================================
$recent_activities = [];
try {
    $stmt = $db->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5");
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_activities = [
        ['action' => 'System Started', 'details' => 'Super Admin logged in', 'created_at' => date('Y-m-d H:i:s')],
    ];
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- STYLES -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       MODERN DASHBOARD STYLES - 8 CARDS
       SOLID COLORS WITH LOW OPACITY - SOFT MODERN LOOK
       ================================================================ */
    
    :root {
        --card-radius: 16px;
        --card-shadow: 0 4px 20px rgba(0,0,0,0.06);
        --card-hover-shadow: 0 8px 35px rgba(0,0,0,0.12);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    [data-theme="dark"] {
        --card-shadow: 0 4px 20px rgba(0,0,0,0.3);
        --card-hover-shadow: 0 8px 35px rgba(0,0,0,0.5);
    }
    
    /* ================================================================
       STAT CARDS - 8 CARDS - SOLID COLORS WITH OPACITY
       ================================================================ */
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        position: relative;
        border-radius: var(--card-radius);
        padding: 20px 22px;
        color: white;
        text-decoration: none;
        display: block;
        overflow: hidden;
        transition: var(--transition);
        box-shadow: var(--card-shadow);
        min-height: 130px;
        height: 100%;
        cursor: pointer;
        border: none;
    }
    
    /* ================================================================
       SOLID COLORS WITH OPACITY - SOFT MODERN LOOK
       ================================================================ */
    
    /* 1. Revenue - Blue with 85% opacity */
    .stat-card.card-revenue {
        background: rgba(11, 94, 215, 0.85);
    }
    .stat-card.card-revenue:hover {
        background: rgba(11, 94, 215, 0.95);
    }
    
    /* 2. Expenses - Rose with 85% opacity */
    .stat-card.card-expenses {
        background: rgba(225, 29, 72, 0.85);
    }
    .stat-card.card-expenses:hover {
        background: rgba(225, 29, 72, 0.95);
    }
    
    /* 3. Profit - Green with 85% opacity */
    .stat-card.card-profit {
        background: rgba(5, 150, 105, 0.85);
    }
    .stat-card.card-profit:hover {
        background: rgba(5, 150, 105, 0.95);
    }
    
    /* 4. Prescription - Purple with 85% opacity */
    .stat-card.card-prescription {
        background: rgba(124, 58, 237, 0.85);
    }
    .stat-card.card-prescription:hover {
        background: rgba(124, 58, 237, 0.95);
    }
    
    /* 5. OTC - Amber with 85% opacity */
    .stat-card.card-otc {
        background: rgba(217, 119, 6, 0.85);
    }
    .stat-card.card-otc:hover {
        background: rgba(217, 119, 6, 0.95);
    }
    
    /* 6. Stock - Cyan with 85% opacity */
    .stat-card.card-stock {
        background: rgba(8, 145, 178, 0.85);
    }
    .stat-card.card-stock:hover {
        background: rgba(8, 145, 178, 0.95);
    }
    
    /* 7. Expiry - Red with 85% opacity */
    .stat-card.card-expiry {
        background: rgba(220, 38, 38, 0.85);
    }
    .stat-card.card-expiry:hover {
        background: rgba(220, 38, 38, 0.95);
    }
    
    /* 8. Patients - Indigo with 85% opacity */
    .stat-card.card-patients {
        background: rgba(79, 70, 229, 0.85);
    }
    .stat-card.card-patients:hover {
        background: rgba(79, 70, 229, 0.95);
    }
    
    /* Subtle decorative circles with lower opacity */
    .stat-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -30%;
        width: 200px;
        height: 200px;
        border-radius: 50%;
        background: rgba(255,255,255,0.04);
        pointer-events: none;
        transition: var(--transition);
    }
    
    .stat-card::after {
        content: '';
        position: absolute;
        bottom: -40%;
        left: -20%;
        width: 150px;
        height: 150px;
        border-radius: 50%;
        background: rgba(255,255,255,0.02);
        pointer-events: none;
        transition: var(--transition);
    }
    
    .stat-card:hover {
        transform: translateY(-6px);
        box-shadow: var(--card-hover-shadow);
    }
    
    .stat-card:hover::before {
        transform: scale(1.2);
        right: -20%;
    }
    
    .stat-card:hover::after {
        transform: scale(1.3);
        bottom: -30%;
    }
    
    .stat-card:active {
        transform: scale(0.97);
    }
    
    /* Card Inner Content */
    .stat-card .card-content {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
    }
    
    .stat-card .card-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    
    .stat-card .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        background: rgba(255,255,255,0.12);
        color: white;
        flex-shrink: 0;
        backdrop-filter: blur(4px);
        transition: var(--transition);
    }
    
    .stat-card:hover .stat-icon {
        transform: scale(1.1) rotate(-5deg);
        background: rgba(255,255,255,0.2);
    }
    
    .stat-card .stat-number {
        font-size: 1.7rem;
        font-weight: 700;
        color: white;
        line-height: 1.2;
        margin-top: 2px;
        letter-spacing: -0.02em;
    }
    
    .stat-card .stat-label {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.9);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    
    .stat-card .stat-sub {
        font-size: 0.65rem;
        color: rgba(255,255,255,0.8);
        margin-top: 2px;
    }
    
    .stat-card .stat-trend {
        font-size: 0.55rem;
        font-weight: 500;
        padding: 2px 10px;
        border-radius: 20px;
        background: rgba(255,255,255,0.1);
        color: white;
        display: inline-block;
        margin-top: 4px;
        backdrop-filter: blur(4px);
    }
    
    .stat-card .stat-badge-row {
        display: flex;
        gap: 8px;
        margin-top: 4px;
        flex-wrap: wrap;
    }
    
    .stat-card .stat-badge {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 12px;
        border-radius: 20px;
        background: rgba(255,255,255,0.1);
        color: white;
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .stat-card .stat-badge.danger {
        background: rgba(239, 68, 68, 0.25);
        color: #FCA5A5;
    }
    
    .stat-card .stat-badge.warning {
        background: rgba(245, 158, 11, 0.25);
        color: #FCD34D;
    }
    
    .stat-card .stat-badge.success {
        background: rgba(52, 211, 153, 0.25);
        color: #6EE7B7;
    }
    
    .stat-card .stat-arrow {
        position: absolute;
        right: 16px;
        bottom: 16px;
        color: rgba(255,255,255,0.25);
        font-size: 0.75rem;
        transition: var(--transition);
        z-index: 1;
    }
    
    .stat-card:hover .stat-arrow {
        transform: translateX(4px);
        color: rgba(255,255,255,0.6);
    }
    
    /* ================================================================
       RESPONSIVE - 8 CARDS
       ================================================================ */
    @media (max-width: 1200px) {
        .stat-grid {
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }
        .stat-card .stat-number {
            font-size: 1.4rem;
        }
    }
    
    @media (max-width: 992px) {
        .stat-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        .stat-card {
            min-height: 110px;
            padding: 16px 18px;
        }
        .stat-card .stat-number {
            font-size: 1.3rem;
        }
        .stat-card .stat-icon {
            width: 38px;
            height: 38px;
            font-size: 1rem;
        }
    }
    
    @media (max-width: 600px) {
        .stat-grid {
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .stat-card {
            min-height: 95px;
            padding: 12px 14px;
        }
        .stat-card .stat-number {
            font-size: 1.1rem;
        }
        .stat-card .stat-label {
            font-size: 0.6rem;
        }
        .stat-card .stat-sub {
            font-size: 0.55rem;
        }
        .stat-card .stat-icon {
            width: 32px;
            height: 32px;
            font-size: 0.85rem;
        }
        .stat-card .stat-arrow {
            display: none;
        }
        .stat-card .stat-badge {
            font-size: 0.5rem;
            padding: 1px 8px;
        }
    }
    
    @media (max-width: 400px) {
        .stat-grid {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .stat-card {
            min-height: 80px;
            padding: 10px 12px;
            border-radius: 12px;
        }
        .stat-card .stat-number {
            font-size: 0.95rem;
        }
        .stat-card .stat-label {
            font-size: 0.5rem;
        }
        .stat-card .stat-sub {
            font-size: 0.45rem;
        }
        .stat-card .stat-icon {
            width: 26px;
            height: 26px;
            font-size: 0.7rem;
        }
    }
    
    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header {
        background: rgba(11, 94, 215, 0.9);
        border-radius: 16px;
        padding: 20px 28px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.15);
        position: relative;
        overflow: hidden;
    }
    
    .page-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.04);
        border-radius: 50%;
        pointer-events: none;
    }
    
    .page-header .page-title {
        color: white;
        font-size: 1.4rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        position: relative;
        z-index: 1;
    }
    
    .page-header .page-title i {
        font-size: 1.5rem;
        opacity: 0.9;
    }
    
    .page-header .page-subtitle {
        color: rgba(255,255,255,0.85);
        font-size: 0.85rem;
        margin: 2px 0 0 0;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        position: relative;
        z-index: 1;
    }
    
    .page-header .branch-tag {
        background: rgba(255,255,255,0.12);
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.06);
    }
    
    .page-header .date-badge {
        background: rgba(255,255,255,0.08);
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.06);
    }
    
    .page-header .btn-outline-light {
        background: rgba(255,255,255,0.1);
        color: white;
        border: 1px solid rgba(255,255,255,0.1);
        padding: 6px 14px;
        border-radius: 8px;
        font-weight: 500;
        font-size: 0.75rem;
        transition: var(--transition);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        backdrop-filter: blur(4px);
        position: relative;
        z-index: 1;
    }
    
    .page-header .btn-outline-light:hover {
        background: rgba(255,255,255,0.2);
        transform: translateY(-2px);
    }
    
    /* ================================================================
       CARD - For Recent Items
       ================================================================ */
    .card {
        background: var(--bg-card);
        border-radius: 12px;
        border: 1px solid var(--border-color);
        overflow: hidden;
        transition: var(--transition);
        box-shadow: var(--shadow-sm);
    }
    
    .card:hover {
        box-shadow: var(--shadow-md);
    }
    
    .card-header {
        padding: 8px 14px;
        background: var(--bg-body);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 4px;
    }
    
    [data-theme="dark"] .card-header {
        background: #0F172A;
    }
    
    .card-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
    }
    
    .card-title i {
        margin-right: 6px;
    }
    
    .title-blue { color: #0B5ED7; }
    .title-green { color: #059669; }
    
    /* ================================================================
       DATA TABLE
       ================================================================ */
    .data-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.75rem;
    }
    
    .data-table thead th {
        background: rgba(11, 94, 215, 0.9);
        color: white;
        font-weight: 600;
        padding: 6px 10px;
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: none;
        white-space: nowrap;
    }
    
    .data-table thead th:first-child { border-radius: 6px 0 0 0; }
    .data-table thead th:last-child { border-radius: 0 6px 0 0; }
    
    .data-table td {
        padding: 5px 10px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
        transition: background 0.2s ease;
    }
    
    .data-table tbody tr:hover td { background: var(--table-hover); }
    .data-table tbody tr:last-child td { border-bottom: none; }
    
    .max-h-50 { max-height: 160px; overflow-y: auto; }
    .overflow-x-auto { overflow-x: auto; }
    
    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 12px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.7rem;
        transition: var(--transition);
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-sm {
        padding: 3px 10px;
        font-size: 0.65rem;
        border-radius: 4px;
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 1.5px solid var(--border-color);
    }
    
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .btn-blue {
        background: rgba(11, 94, 215, 0.9);
        color: white;
    }
    
    .btn-blue:hover {
        background: rgba(11, 94, 215, 1);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    /* ================================================================
       FOOTER
       ================================================================ */
    .footer {
        margin-top: 16px;
        padding: 8px 0;
        border-top: 1px solid var(--border-color);
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .footer .footer-brand {
        color: var(--primary);
        font-weight: 600;
    }
    
    /* ================================================================
       RESPONSIVE - CARD
       ================================================================ */
    @media (max-width: 768px) {
        .page-header {
            padding: 14px 18px;
        }
        .page-header .page-title {
            font-size: 1.1rem;
        }
        .page-header .page-subtitle {
            font-size: 0.7rem;
        }
        .card-header {
            padding: 6px 10px;
        }
        .data-table {
            font-size: 0.65rem;
        }
        .data-table thead th, .data-table td {
            padding: 4px 6px;
        }
        .max-h-50 {
            max-height: 120px;
        }
    }
    
    @media (max-width: 480px) {
        .page-header {
            padding: 10px 14px;
        }
        .page-header .page-title {
            font-size: 0.95rem;
        }
        .page-header .page-subtitle {
            font-size: 0.6rem;
        }
        .card-title {
            font-size: 0.7rem;
        }
        .data-table {
            font-size: 0.55rem;
        }
        .data-table thead th, .data-table td {
            padding: 3px 4px;
        }
        .btn {
            font-size: 0.6rem;
            padding: 3px 8px;
        }
    }
    
    /* ================================================================
       PRINT
       ================================================================ */
    @media print {
        .stat-card { border: 1px solid #ddd !important; box-shadow: none !important; }
        .stat-card.card-revenue { background: #0B5ED7 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .stat-card.card-expenses { background: #E11D48 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .stat-card.card-profit { background: #059669 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .stat-card.card-prescription { background: #7C3AED !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .stat-card.card-otc { background: #D97706 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .stat-card.card-stock { background: #0891B2 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .stat-card.card-expiry { background: #DC2626 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .stat-card.card-patients { background: #4F46E5 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .card { border: 1px solid #ddd !important; box-shadow: none !important; }
        .page-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .page-title, .page-subtitle { color: white !important; }
        .stat-card .stat-number, .stat-card .stat-label, .stat-card .stat-sub { color: white !important; }
        .stat-card .stat-icon { background: rgba(255,255,255,0.2) !important; }
    }
</style>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search patients, doctors, medicines...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-home"></i> Super Admin Dashboard
            </h1>
            <p class="page-subtitle">
                Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?>!
                <span class="branch-tag"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?></span>
                <span class="date-badge"><i class="fas fa-calendar-day"></i> <?= date('F d, Y') ?></span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="reports.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-file-export"></i> Report
            </a>
            <button onclick="location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ✅ 8 CARDS - SOLID COLORS WITH OPACITY -->
    <!-- ================================================================ -->
    <div class="stat-grid">
        
        <!-- 1. TOTAL REVENUE -->
        <a href="reports.php?type=revenue&branch=<?= $selected_branch_id ?>" class="stat-card card-revenue">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Total Revenue</p>
                        <p class="stat-number">TSh <?= number_format($total_revenue) ?></p>
                        <p class="stat-sub">Patient Bills + OTC Sales</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-arrow-up"></i> All time</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 2. TOTAL EXPENSES -->
        <a href="../cashier/expenses.php?branch=<?= $selected_branch_id ?>" class="stat-card card-expenses">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Total Expenses</p>
                        <p class="stat-number">TSh <?= number_format($total_expenses) ?></p>
                        <p class="stat-sub">Paid expenses</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-coins"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-arrow-down"></i> All time</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 3. NET PROFIT -->
        <a href="reports.php?type=profit&branch=<?= $selected_branch_id ?>" class="stat-card card-profit">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label"><?= $net_profit >= 0 ? '💰 Net Profit' : '📉 Net Loss' ?></p>
                        <p class="stat-number">TSh <?= number_format(abs($net_profit)) ?></p>
                        <p class="stat-sub">
                            <?php if ($total_revenue > 0): ?>
                                <?= $profit_percentage ?>% margin
                            <?php else: ?>
                                No revenue yet
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="stat-icon"><i class="fas <?= $net_profit >= 0 ? 'fa-chart-line' : 'fa-exclamation-triangle' ?>"></i></div>
                </div>
                <div class="stat-trend">
                    <?php if ($net_profit >= 0): ?>
                        <i class="fas fa-arrow-up"></i> Revenue - Expenses
                    <?php else: ?>
                        <i class="fas fa-arrow-down"></i> Expenses exceed revenue
                    <?php endif; ?>
                </div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 4. PRESCRIPTION AMOUNT -->
        <a href="bills.php?type=prescription&branch=<?= $selected_branch_id ?>" class="stat-card card-prescription">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Prescription Amount</p>
                        <p class="stat-number">TSh <?= number_format($prescription_amount) ?></p>
                        <p class="stat-sub"><?= $prescription_count ?> prescriptions</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-prescription"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-pills"></i> Medication bills</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 5. OTC SALES -->
        <a href="../pharmacy/otc_sales.php?branch=<?= $selected_branch_id ?>" class="stat-card card-otc">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">OTC Sales</p>
                        <p class="stat-number">TSh <?= number_format($otc_total) ?></p>
                        <p class="stat-sub"><?= $otc_count ?> transactions</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-cash-register"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-store"></i> Over the counter</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 6. STOCK - Out of Stock & Low Stock -->
        <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="stat-card card-stock">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Stock Status</p>
                        <p class="stat-number">
                            <?php 
                                $total_stock_issues = $out_of_stock + $low_stock;
                                echo number_format($total_stock_issues);
                            ?>
                        </p>
                        <div class="stat-badge-row">
                            <span class="stat-badge danger"><i class="fas fa-times-circle"></i> <?= $out_of_stock ?> Out</span>
                            <span class="stat-badge warning"><i class="fas fa-exclamation-triangle"></i> <?= $low_stock ?> Low</span>
                        </div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-warehouse"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-pills"></i> Needs attention</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 7. EXPIRY - Expired & Expiring Soon -->
        <a href="inventory.php?filter=expired&branch=<?= $selected_branch_id ?>" class="stat-card card-expiry">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Expiry Status</p>
                        <p class="stat-number">
                            <?php 
                                $total_expiry_issues = $expired + $expiring_soon;
                                echo number_format($total_expiry_issues);
                            ?>
                        </p>
                        <div class="stat-badge-row">
                            <span class="stat-badge danger"><i class="fas fa-skull"></i> <?= $expired ?> Expired</span>
                            <span class="stat-badge warning"><i class="fas fa-clock"></i> <?= $expiring_soon ?> Soon</span>
                        </div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-calendar-times"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-clock"></i> Needs disposal</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 8. PATIENTS -->
        <a href="patients.php?branch=<?= $selected_branch_id ?>" class="stat-card card-patients">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Total Patients</p>
                        <p class="stat-number"><?= number_format($total_patients) ?></p>
                        <p class="stat-sub"><i class="fas fa-user-plus"></i> <?= $today_patients ?> today</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-user-injured"></i> All time</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- CHART - Revenue -->
    <!-- ================================================================ -->
    <div class="card mb-4">
        <div class="card-header py-2">
            <h3 class="card-title text-sm">
                <i class="fas fa-chart-line title-blue mr-2"></i> Revenue Overview (Last 7 Days)
                <span class="text-xs text-gray-400 font-normal">TSh <?= number_format(array_sum($chart_values)) ?> total</span>
            </h3>
        </div>
        <div style="height: 150px; padding: 8px 12px;">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PATIENTS & ACTIVITIES -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        
        <div class="card">
            <div class="card-header py-2">
                <h3 class="card-title text-sm">
                    <i class="fas fa-user-injured title-blue mr-2"></i> Recent Patients
                </h3>
                <a href="patients.php?branch=<?= $selected_branch_id ?>" class="text-xs text-blue-600 font-medium hover:underline">View All →</a>
            </div>
            <div class="overflow-x-auto max-h-50 overflow-y-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient ID</th>
                            <th>Name</th>
                            <th>Branch</th>
                            <th>Registered</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_patients) > 0): ?>
                            <?php foreach ($recent_patients as $patient): ?>
                                <tr>
                                    <td class="font-mono text-xs"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                                    <td class="font-medium"><?= htmlspecialchars($patient['full_name'] ?? 'Unknown') ?></td>
                                    <td class="text-xs"><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></td>
                                    <td class="text-xs"><?= date('M d, Y', strtotime($patient['created_at'])) ?></td>
                                    <td>
                                        <a href="patient_details.php?id=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="text-blue-600 text-xs hover:underline">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-gray-400 text-sm py-2">No patients found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header py-2">
                <h3 class="card-title text-sm">
                    <i class="fas fa-clock title-green mr-2"></i> Recent Activities
                </h3>
                <a href="system_logs.php" class="text-xs text-blue-600 font-medium hover:underline">View All →</a>
            </div>
            <div class="space-y-1 max-h-50 overflow-y-auto px-2">
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="flex items-start gap-2 p-1.5 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition">
                        <div class="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5 text-white text-xs">
                            <i class="fas fa-circle text-[5px]"></i>
                        </div>
                        <div>
                            <p class="font-medium text-xs text-gray-800 dark:text-gray-200"><?= htmlspecialchars($activity['action'] ?? 'Action') ?></p>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400"><?= htmlspecialchars($activity['details'] ?? '') ?></p>
                            <p class="text-[9px] text-gray-400 dark:text-gray-500 mt-0.5">
                                <?= isset($activity['created_at']) ? time_ago($activity['created_at']) : 'Just now' ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK REPORTS -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header py-2">
            <h3 class="card-title text-sm">
                <i class="fas fa-file-alt title-blue mr-2"></i> Quick Reports
            </h3>
        </div>
        <div class="flex flex-wrap gap-1.5 px-1 pb-2">
            <a href="reports.php?type=daily&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm text-xs">Daily</a>
            <a href="reports.php?type=weekly&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm text-xs">Weekly</a>
            <a href="reports.php?type=monthly&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm text-xs">Monthly</a>
            <a href="reports.php?type=revenue&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm text-xs">Revenue</a>
            <a href="reports.php?type=profit&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm text-xs">Profit/Loss</a>
            <a href="reports.php?type=medicine&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm text-xs">Medicine</a>
            <a href="reports.php?type=laboratory&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm text-xs">Laboratory</a>
            <a href="reports.php?type=expenses&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm text-xs">Expenses</a>
            <div class="flex-1"></div>
            <button onclick="window.print()" class="btn btn-outline btn-sm text-xs">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="mx-2">|</span>
            Super Admin Dashboard v4.0
            <span class="mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // ================================================================
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');

    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // REVENUE CHART
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('revenueChart')?.getContext('2d');
        if (ctx) {
            if (typeof Chart !== 'undefined') {
                var labels = <?= json_encode($chart_labels) ?>;
                var values = <?= json_encode($chart_values) ?>;
                
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Revenue (TSh)',
                            data: values,
                            borderColor: '#0B5ED7',
                            backgroundColor: 'rgba(11, 94, 215, 0.1)',
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#0B5ED7',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 1.5,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { 
                                display: true,
                                labels: {
                                    font: { size: 9, weight: '600' },
                                    boxWidth: 10,
                                    padding: 8,
                                    color: '#64748B'
                                }
                            },
                            tooltip: {
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
                                    callback: function(value) {
                                        return 'TSh ' + value.toLocaleString();
                                    },
                                    font: { size: 8 }
                                },
                                grid: { color: 'rgba(0,0,0,0.05)' }
                            },
                            x: { 
                                grid: { display: false },
                                ticks: { font: { size: 8 } }
                            }
                        },
                        interaction: { intersect: false, mode: 'index' }
                    }
                });
            }
        }
    });

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
        }
    });

    console.log('%c🏥 Braick Dispensary - Super Admin Dashboard v4.0', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👋 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💸 Total Expenses: TSh <?= number_format($total_expenses) ?>', 'font-size:13px; color:#E11D48;');
    console.log('%c📈 Net Profit: TSh <?= number_format($net_profit) ?> (<?= $profit_percentage ?>%)', 'font-size:13px; color:<?= $net_profit >= 0 ? '#059669' : '#EF4444' ?>;');
    console.log('%c💊 Prescriptions: TSh <?= number_format($prescription_amount) ?> (<?= $prescription_count ?> bills)', 'font-size:13px; color:#7C3AED;');
    console.log('%c🏪 OTC Sales: TSh <?= number_format($otc_total) ?> (<?= $otc_count ?> transactions)', 'font-size:13px; color:#D97706;');
    console.log('%c📦 Stock Issues: <?= $out_of_stock + $low_stock ?> (Out: <?= $out_of_stock ?>, Low: <?= $low_stock ?>)', 'font-size:13px; color:#0891B2;');
    console.log('%c📅 Expiry Issues: <?= $expired + $expiring_soon ?> (Expired: <?= $expired ?>, Soon: <?= $expiring_soon ?>)', 'font-size:13px; color:#DC2626;');
    console.log('%c👤 Total Patients: <?= number_format($total_patients) ?> (Today: <?= $today_patients ?>)', 'font-size:13px; color:#4F46E5;');
    console.log('%c🎯 8 CARDS ONLY - Solid Colors with Opacity', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ FIXED: Only bills from existing patients', 'font-size:13px; color:#059669;');
    console.log('%c✅ FIXED: Column "branch_id" ambiguous - added table prefixes', 'font-size:13px; color:#059669;');
</script>

</body>
</html>