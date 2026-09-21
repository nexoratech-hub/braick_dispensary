<?php
// ================================================================
// FILE: frontend/pages/admin/cashiers.php
// SUPER ADMIN - VIEW ALL CASHIERS (V13.1 - PRESCRIPTION GROSS)
// ✅ V13.1: Ondoa formula breakdown card ya juu (Prescription ipo Card #4 pekee)
// ✅ V13: PRESCRIPTION = Medication_RAW (GROSS - bila discount, bila premium)
// ✅ V13: Prescription BILA round off (exact value, 2 decimal places)
// ✅ SAWA KWA 100% NA AUDIT V13, ADMIN V20
// ✅ FIXED: Font Awesome 6 included (icons zinaonekana)
// ✅ FONT: JetBrains Mono
// ✅ PAYMENTS-BASED
// ✅ Uses SHARED header & sidebar
// ✅ 8 cards (4 top + 4 bottom) with All Discount + All Premium
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
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
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

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// ✅ V13.1: ROUND TO NEAREST 50 FUNCTION (kwa cards zingine)
// ================================================================
function round_to_50($value) {
    return round($value / 50) * 50;
}

// ================================================================
// FILTERS
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';

if (isset($_GET['error'])) {
    $selected_branch_id = 'all';
    header('Location: ' . $_SERVER['PHP_SELF'] . '?branch=all');
    exit;
}

// ================================================================
// BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $branches = []; }

// ================================================================
// ✅ V13.1: FINANCIAL SUMMARY - PRESCRIPTION GROSS
// ================================================================
function getFinancialSummary($db, $branch_id = 'all') {
    $results = [];
    
    $branch_p = "";
    $params_p = [];
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $branch_p = " AND p.branch_id = ?";
        $params_p[] = (int)$branch_id;
    }
    
    // Patient revenue (payments)
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(p.amount), 0) as patient_revenue,
               COUNT(DISTINCT p.id) as payments_count
        FROM payments p
        INNER JOIN bills b ON p.bill_id = b.id
        WHERE b.patient_id IS NOT NULL 
        AND b.visit_id IS NOT NULL 
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
        $branch_p
    ");
    $stmt->execute($params_p);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $results['patient_revenue'] = $row['patient_revenue'] ?? 0;
    $results['payments_count'] = $row['payments_count'] ?? 0;
    
    // OTC revenue
    $branch_cond = "";
    $params = [];
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $branch_cond = " AND os.branch_id = ?";
        $params[] = (int)$branch_id;
    }
    $stmt = $db->prepare("SELECT COALESCE(SUM(os.total_amount), 0) as otc_revenue FROM otc_sales os WHERE os.payment_status = 'paid' $branch_cond");
    $stmt->execute($params);
    $results['otc_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['otc_revenue'] ?? 0;
    
    // ✅ V13.1: Medication RAW (GROSS - bila discount)
    $branch_med = "";
    $params_med = [];
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $branch_med = " AND bi.branch_id = ?";
        $params_med[] = (int)$branch_id;
    }
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(bi.total_price), 0) as medication_raw
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE bi.item_type = 'medication'
        AND bi.status != 'cancelled'
        AND b.status IN ('paid', 'partial')
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
        $branch_med
    ");
    $stmt->execute($params_med);
    $results['medication_raw'] = $stmt->fetch(PDO::FETCH_ASSOC)['medication_raw'] ?? 0;
    
    // Pharmacy Discount (info only)
    $branch_disc = "";
    $params_disc = [];
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $branch_disc = " AND b.branch_id = ?";
        $params_disc[] = (int)$branch_id;
    }
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(b.pharmacy_discount), 0) as pharmacy_discount
        FROM bills b
        WHERE b.status IN ('paid', 'partial')
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
        AND b.pharmacy_discount > 0
        $branch_disc
    ");
    $stmt->execute($params_disc);
    $results['pharmacy_discount'] = $stmt->fetch(PDO::FETCH_ASSOC)['pharmacy_discount'] ?? 0;
    
    // ✅ V13.1: PRESCRIPTION = Medication_RAW (GROSS - bila discount, bila premium, bila round)
    $results['prescription_revenue'] = (float)$results['medication_raw'];
    
    // Round Patient Revenue + OTC + Medication_RAW (cards zingine zinatumia round 50)
    $results['patient_revenue'] = round_to_50($results['patient_revenue']);
    $results['otc_revenue'] = round_to_50($results['otc_revenue']);
    $results['medication_raw'] = round_to_50($results['medication_raw']);
    $results['pharmacy_discount'] = round_to_50($results['pharmacy_discount']);
    
    $results['total_revenue'] = $results['patient_revenue'] + $results['otc_revenue'];
    
    // Expenses
    $branch_exp = "";
    $params_exp = [];
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $branch_exp = " AND branch_id = ?";
        $params_exp[] = (int)$branch_id;
    }
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_expenses FROM expenses WHERE status = 'paid' $branch_exp");
    $stmt->execute($params_exp);
    $results['total_expenses'] = round_to_50($stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?? 0);
    
    $results['net_profit'] = $results['total_revenue'] - $results['total_expenses'];
    
    // Paid bills
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT p.bill_id) as paid_bills
        FROM payments p
        INNER JOIN bills b ON p.bill_id = b.id
        WHERE b.patient_id IS NOT NULL 
        AND b.visit_id IS NOT NULL 
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
        $branch_p
    ");
    $stmt->execute($params_p);
    $results['paid_bills'] = $stmt->fetch(PDO::FETCH_ASSOC)['paid_bills'] ?? 0;
    
    // Pending bills
    $branch_b = "";
    $params_b = [];
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $branch_b = " AND b.branch_id = ?";
        $params_b[] = (int)$branch_id;
    }
    $stmt = $db->prepare("SELECT COUNT(*) as pending_bills FROM bills b WHERE b.status = 'pending' AND b.patient_id IS NOT NULL AND b.visit_id IS NOT NULL AND b.bill_number NOT LIKE 'BILL-OTC-%' $branch_b");
    $stmt->execute($params_b);
    $results['pending_bills'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_bills'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total_bills FROM bills b WHERE b.patient_id IS NOT NULL AND b.visit_id IS NOT NULL AND b.bill_number NOT LIKE 'BILL-OTC-%' $branch_b");
    $stmt->execute($params_b);
    $results['total_bills'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_bills'] ?? 0;
    
    // Total Discount
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(b.pharmacy_discount + b.cashier_discount), 0) as total_discount 
        FROM bills b 
        WHERE b.status IN ('paid', 'partial')
        AND (b.pharmacy_discount > 0 OR b.cashier_discount > 0)
        AND b.bill_number NOT LIKE 'BILL-OTC-%' 
        $branch_b
    ");
    $stmt->execute($params_b);
    $results['total_discount'] = round_to_50($stmt->fetch(PDO::FETCH_ASSOC)['total_discount'] ?? 0);
    
    // Total Premium
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(b.pharmacy_premium + b.cashier_premium), 0) as total_premium 
        FROM bills b 
        WHERE b.status IN ('paid', 'partial')
        AND (b.pharmacy_premium > 0 OR b.cashier_premium > 0)
        AND b.bill_number NOT LIKE 'BILL-OTC-%' 
        $branch_b
    ");
    $stmt->execute($params_b);
    $results['total_premium'] = round_to_50($stmt->fetch(PDO::FETCH_ASSOC)['total_premium'] ?? 0);
    
    return $results;
}

$financial = getFinancialSummary($db, $selected_branch_id);

// ================================================================
// ✅ V13.1: FETCH BRANCHES - PRESCRIPTION GROSS
// ================================================================
$query = "
    SELECT b.*,
        COALESCE((SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'cashier' AND status = 'active'), 0) as active_cashiers,
        COALESCE((SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'cashier'), 0) as total_cashiers,
        COALESCE((SELECT COUNT(*) FROM bills pb WHERE pb.branch_id = b.id AND pb.patient_id IS NOT NULL AND pb.visit_id IS NOT NULL AND pb.bill_number NOT LIKE 'BILL-OTC-%'), 0) as total_bills,
        COALESCE((SELECT COUNT(*) FROM bills pb WHERE pb.branch_id = b.id AND pb.status = 'pending' AND pb.patient_id IS NOT NULL AND pb.visit_id IS NOT NULL AND pb.bill_number NOT LIKE 'BILL-OTC-%'), 0) as pending_bills,
        COALESCE((SELECT COUNT(DISTINCT p.bill_id) FROM payments p INNER JOIN bills pb ON p.bill_id = pb.id WHERE pb.branch_id = b.id AND pb.patient_id IS NOT NULL AND pb.visit_id IS NOT NULL AND pb.bill_number NOT LIKE 'BILL-OTC-%'), 0) as paid_bills,
        COALESCE((SELECT COUNT(*) FROM bills pb WHERE pb.branch_id = b.id AND pb.status = 'partial' AND pb.patient_id IS NOT NULL AND pb.visit_id IS NOT NULL AND pb.bill_number NOT LIKE 'BILL-OTC-%'), 0) as partial_bills,
        
        COALESCE((
            SELECT COALESCE(SUM(p.amount), 0) 
            FROM payments p 
            INNER JOIN bills pb ON p.bill_id = pb.id 
            WHERE pb.branch_id = b.id 
            AND pb.patient_id IS NOT NULL 
            AND pb.visit_id IS NOT NULL 
            AND pb.bill_number NOT LIKE 'BILL-OTC-%'
        ), 0) as patient_bills_revenue,
        
        COALESCE((SELECT COALESCE(SUM(os.total_amount), 0) FROM otc_sales os WHERE os.branch_id = b.id AND os.payment_status = 'paid'), 0) as otc_revenue,
        
        -- ✅ V13.1: PRESCRIPTION = Medication_RAW (GROSS - bila discount, bila premium)
        COALESCE((
            SELECT COALESCE(SUM(bi.total_price), 0)
            FROM bill_items bi
            INNER JOIN bills pb ON bi.bill_id = pb.id
            WHERE pb.branch_id = b.id
            AND bi.item_type = 'medication'
            AND bi.status != 'cancelled'
            AND pb.status IN ('paid', 'partial')
            AND pb.patient_id IS NOT NULL
            AND pb.visit_id IS NOT NULL
            AND pb.bill_number NOT LIKE 'BILL-OTC-%'
        ), 0) as prescription_revenue,
        
        COALESCE((SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE branch_id = b.id AND status = 'paid'), 0) as branch_expenses,
        
        COALESCE((SELECT COALESCE(SUM(pb.pharmacy_discount + pb.cashier_discount), 0) FROM bills pb WHERE pb.branch_id = b.id AND (pb.pharmacy_discount > 0 OR pb.cashier_discount > 0) AND pb.bill_number NOT LIKE 'BILL-OTC-%'), 0) as branch_discount,
        
        COALESCE((SELECT COALESCE(SUM(pb.pharmacy_premium + pb.cashier_premium), 0) FROM bills pb WHERE pb.branch_id = b.id AND (pb.pharmacy_premium > 0 OR pb.cashier_premium > 0) AND pb.bill_number NOT LIKE 'BILL-OTC-%'), 0) as branch_premium,
        
        COALESCE(
            (SELECT COALESCE(SUM(p.amount), 0) 
             FROM payments p 
             INNER JOIN bills pb ON p.bill_id = pb.id 
             WHERE pb.branch_id = b.id 
             AND pb.patient_id IS NOT NULL 
             AND pb.visit_id IS NOT NULL 
             AND pb.bill_number NOT LIKE 'BILL-OTC-%')
            + 
            (SELECT COALESCE(SUM(os.total_amount), 0) FROM otc_sales os WHERE os.branch_id = b.id AND os.payment_status = 'paid'),
            0
        ) as total_revenue
    FROM branches b
    WHERE 1=1
";

$params = [];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $check_stmt = $db->prepare("SELECT id FROM branches WHERE id = ? AND status = 'active'");
    $check_stmt->execute([(int)$selected_branch_id]);
    if ($check_stmt->fetch()) {
        $query .= " AND b.id = ?";
        $params[] = (int)$selected_branch_id;
    } else {
        $selected_branch_id = 'all';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

if ($status_filter !== 'all') {
    $query .= " AND b.status = ?";
    $params[] = $status_filter;
} else {
    $query .= " AND b.status = 'active'";
}

if (!empty($search)) {
    $query .= " AND (b.name LIKE ? OR b.location LIKE ? OR b.phone LIKE ? OR b.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY b.name ASC";

$cashiers = [];
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching cashiers: " . $e->getMessage());
    $cashiers = [];
}

// ✅ V13.1: Prescription BILA round off (exact value) per branch
foreach ($cashiers as &$c) {
    $c['prescription_revenue'] = (float)($c['prescription_revenue'] ?? 0);
    // Rounding kwa cards zingine
    $c['patient_bills_revenue'] = round_to_50($c['patient_bills_revenue'] ?? 0);
    $c['otc_revenue'] = round_to_50($c['otc_revenue'] ?? 0);
    $c['branch_discount'] = round_to_50($c['branch_discount'] ?? 0);
    $c['branch_premium'] = round_to_50($c['branch_premium'] ?? 0);
    $c['branch_expenses'] = round_to_50($c['branch_expenses'] ?? 0);
    $c['total_revenue'] = round_to_50($c['total_revenue'] ?? 0);
}
unset($c);

$total_cashiers = count($cashiers);

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $unread_notifications = 0; }

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================
     ✅ FONT AWESOME + JETBRAINS MONO
     ================================================================ -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --font-mono: 'JetBrains Mono', 'Courier New', monospace;
        --page-primary: #0B5ED7;
        --page-primary-dark: #0A4CA8;
        --page-primary-bg: #EFF6FF;
        --page-primary-light: #3B82F6;
        --page-bg-body: #F1F5F9;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-border: #E2E8F0;
        --page-shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
        --page-shadow-md: 0 6px 24px rgba(11, 94, 215, 0.2);
        --page-shadow-lg: 0 20px 40px rgba(0,0,0,0.12);
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-border: #334155;
        --page-primary: #3B82F6;
        --page-primary-bg: #1E3A5F;
    }

    /* ✅ ENSURE FONT AWESOME WORKS */
    .fas, .far, .fab, .fa {
        font-family: "Font Awesome 6 Free" !important;
        font-weight: 900 !important;
        font-style: normal !important;
        font-variant: normal !important;
        text-rendering: auto !important;
        -webkit-font-smoothing: antialiased !important;
        display: inline-block !important;
    }

    .far { font-weight: 400 !important; }
    .fab { font-family: "Font Awesome 6 Brands" !important; font-weight: 400 !important; }

    /* ✅ JetBrains Mono kwa namba */
    .stat-number,
    .stat-label,
    .stat-sub,
    .stat-card-8,
    .stat-card-8 *,
    .page-header-badge,
    .cashier-card .card-stats .num,
    .cashier-card .card-stats .label,
    .revenue-breakdown,
    .revenue-breakdown *,
    .badge,
    .page-header-subtitle,
    .page-header-title {
        font-family: var(--font-mono) !important;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
    }

    /* ✅ ICONS LAZIMA ZITUMIE FONT AWESOME (sio JetBrains Mono) */
    .fas, .far, .fab, .fa,
    .stat-icon i,
    .page-header-title i,
    .info-row i,
    .stat-arrow,
    .btn i,
    .btn-outline-light i,
    .btn-action i,
    .card-title i {
        font-family: "Font Awesome 6 Free" !important;
        font-weight: 900 !important;
    }

    body { background: var(--page-bg-body); }
    html[data-theme="dark"] body { background: #0F172A !important; }

    .main-content { background: var(--page-bg-body); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; }

    /* ================================================================
       BLUE PAGE HEADER CARD - V13.1
       ================================================================ */
    .page-header-card {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083C8A 100%);
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
        background: linear-gradient(135deg, #FCD34D, #F59E0B);
        color: #78350F;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .v13-badge {
        background: rgba(252,211,77,0.25);
        color: #FCD34D;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.62rem;
        font-weight: 800;
        border: 1px solid rgba(252,211,77,0.4);
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .pulse-dot {
        display: inline-block;
        width: 6px; height: 6px;
        border-radius: 50%;
        background: currentColor;
        margin-right: 4px;
        animation: pulseDot 1.5s infinite;
    }

    @keyframes pulseDot {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
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

    .page-header-badge i {
        font-size: 0.7rem;
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
        font-family: var(--font-mono);
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
    .stats-grid-8 {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card-8 {
        border-radius: 16px;
        border: none;
        display: flex;
        flex-direction: column;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        color: white;
        position: relative;
        overflow: hidden;
        cursor: default;
        min-height: 140px;
        padding: 18px 20px;
    }

    .stat-card-8::before {
        content: '';
        position: absolute;
        top: -50%; right: -20%;
        width: 160px; height: 160px;
        background: rgba(255,255,255,0.06);
        border-radius: 50%;
        pointer-events: none;
        transition: all 0.5s ease;
    }

    .stat-card-8::after {
        content: '';
        position: absolute;
        bottom: -40%; left: -10%;
        width: 120px; height: 120px;
        background: rgba(255,255,255,0.04);
        border-radius: 50%;
        pointer-events: none;
        transition: all 0.5s ease;
    }

    .stat-card-8:hover {
        transform: translateY(-6px) scale(1.02);
        box-shadow: 0 12px 36px rgba(0,0,0,0.2);
    }

    .stat-card-8:hover::before { transform: scale(1.3); right: -10%; }
    .stat-card-8:hover::after { transform: scale(1.4); bottom: -30%; }

    .stat-card-8 .stat-icon {
        width: 46px; height: 46px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
        background: rgba(255,255,255,0.18);
        color: white;
        border: 1px solid rgba(255,255,255,0.12);
        backdrop-filter: blur(8px);
        transition: all 0.3s ease;
        margin-bottom: 10px;
        position: relative;
        z-index: 1;
    }

    .stat-card-8 .stat-icon i {
        font-size: 1.2rem;
        color: white;
    }

    .stat-card-8:hover .stat-icon {
        transform: scale(1.1) rotate(-3deg);
        background: rgba(255,255,255,0.3);
    }

    .stat-card-8 .stat-content {
        position: relative;
        z-index: 1;
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .stat-card-8 .stat-label {
        font-size: 0.62rem;
        color: rgba(255,255,255,0.85);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin: 0 0 4px 0;
    }

    .stat-card-8 .stat-number {
        font-size: 1.5rem;
        font-weight: 800;
        color: white;
        margin: 0;
        line-height: 1.1;
        letter-spacing: -0.03em;
        word-break: break-word;
    }

    .stat-card-8 .stat-sub {
        font-size: 0.6rem;
        color: rgba(255,255,255,0.85);
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .stat-card-8 .stat-sub i {
        font-size: 0.6rem;
    }

    .stat-card-8 .stat-arrow {
        position: absolute;
        right: 14px;
        bottom: 14px;
        color: rgba(255,255,255,0.15);
        font-size: 0.9rem;
        transition: all 0.3s ease;
        z-index: 1;
    }

    .stat-card-8:hover .stat-arrow {
        transform: translateX(8px);
        color: rgba(255,255,255,0.5);
    }

    /* Card Colors */
    .card-blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .card-blue:hover { box-shadow: 0 12px 36px rgba(11, 94, 215, 0.4); }

    .card-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    .card-red:hover { box-shadow: 0 12px 36px rgba(220, 38, 38, 0.4); }

    .card-green { background: linear-gradient(135deg, #059669, #047857); }
    .card-green:hover { box-shadow: 0 12px 36px rgba(5, 150, 105, 0.4); }

    .card-orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .card-orange:hover { box-shadow: 0 12px 36px rgba(217, 119, 6, 0.4); }

    .card-purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    .card-purple:hover { box-shadow: 0 12px 36px rgba(124, 58, 237, 0.4); }

    .card-gold { background: linear-gradient(135deg, #F59E0B, #D97706); }
    .card-gold:hover { box-shadow: 0 12px 36px rgba(245, 158, 11, 0.4); }

    .card-teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
    .card-teal:hover { box-shadow: 0 12px 36px rgba(13, 148, 136, 0.4); }

    @keyframes premiumPulse {
        0%, 100% { box-shadow: 0 4px 16px rgba(245, 158, 11, 0.3); }
        50% { box-shadow: 0 4px 32px rgba(245, 158, 11, 0.6); }
    }

    /* ================================================================
       FILTER BAR
       ================================================================ */
    .filter-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
        align-items: center;
        background: var(--page-bg-card);
        padding: 14px 18px;
        border-radius: 12px;
        border: 2px solid var(--page-border);
        box-shadow: var(--page-shadow-sm);
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
        font-family: var(--font-mono);
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .filter-bar select, .filter-bar input {
        background: var(--page-bg-body);
        border: 2px solid var(--page-border);
        border-radius: 12px;
        padding: 8px 14px;
        font-size: 0.8rem;
        color: var(--page-text-primary);
        outline: none;
        transition: all 0.3s;
        min-width: 150px;
        font-family: var(--font-mono);
    }

    html[data-theme="dark"] .filter-bar select,
    html[data-theme="dark"] .filter-bar input {
        background: #0F172A;
        color: #F1F5F9;
        border-color: #334155;
    }

    html[data-theme="dark"] .filter-bar select option { background: #1E293B; }

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
        font-family: var(--font-mono);
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
       BRANCH CARDS GRID
       ================================================================ */
    .cashier-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }

    .cashier-card {
        background: var(--page-bg-card);
        border-radius: 18px;
        border: 2px solid var(--page-border);
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm);
        position: relative;
    }

    html[data-theme="dark"] .cashier-card {
        background: #1E293B;
        border-color: #334155;
    }

    .cashier-card:hover {
        transform: translateY(-6px);
        border-color: var(--page-primary);
        box-shadow: var(--page-shadow-lg);
    }

    .cashier-card .card-top {
        padding: 16px 20px;
        background: linear-gradient(135deg, #0A4CA8, #083C8A);
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        overflow: hidden;
    }

    .cashier-card .card-top::before {
        content: '';
        position: absolute;
        top: -50%; right: -10%;
        width: 180px; height: 180px;
        background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .cashier-card .card-top .name {
        font-size: 1rem;
        font-weight: 700;
        color: white;
        display: flex;
        align-items: center;
        gap: 8px;
        position: relative;
        z-index: 1;
    }

    .cashier-card .card-top .name i { color: rgba(255,255,255,0.85); }

    .cashier-card .card-top .location-text {
        font-size: 0.72rem;
        color: rgba(255,255,255,0.85);
        display: flex;
        align-items: center;
        gap: 4px;
        margin-top: 2px;
        position: relative;
        z-index: 1;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.68rem;
        font-weight: 700;
        color: white;
        letter-spacing: 0.02em;
    }

    .badge-success { background: #059669; }
    .badge-danger { background: #DC2626; }
    .badge-warning { background: #D97706; color: #1E293B; }
    .badge-info { background: #0B5ED7; }

    .cashier-card .card-body { padding: 16px 20px; }

    .cashier-card .card-body .info-row {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 5px 0;
        font-size: 0.8rem;
        color: var(--page-text-secondary);
        font-family: var(--font-mono);
    }

    .cashier-card .card-body .info-row i {
        width: 18px;
        color: var(--page-primary);
        font-size: 0.78rem;
        text-align: center;
    }

    .revenue-breakdown {
        display: flex;
        gap: 12px;
        justify-content: center;
        padding: 10px 0 4px;
        border-top: 1px solid var(--page-border);
        margin-top: 10px;
        font-size: 0.65rem;
        color: var(--page-text-secondary);
        flex-wrap: wrap;
        font-family: var(--font-mono);
    }

    .revenue-breakdown .item { display: flex; align-items: center; gap: 4px; }
    .revenue-breakdown .item .label { font-weight: 600; }
    .revenue-breakdown .item .amount { font-weight: 700; }
    .revenue-breakdown .item .amount.blue { color: #0B5ED7; }
    .revenue-breakdown .item .amount.green { color: #059669; }
    .revenue-breakdown .item .amount.red { color: #DC2626; }
    .revenue-breakdown .item .amount.teal { color: #0D9488; }
    .revenue-breakdown .item .amount.purple { color: #7C3AED; }
    .revenue-breakdown .item .amount.gold { color: #D97706; }

    html[data-theme="dark"] .revenue-breakdown .item .amount.blue { color: #6EA8FE; }
    html[data-theme="dark"] .revenue-breakdown .item .amount.green { color: #34D399; }
    html[data-theme="dark"] .revenue-breakdown .item .amount.red { color: #F87171; }
    html[data-theme="dark"] .revenue-breakdown .item .amount.teal { color: #2DD4BF; }
    html[data-theme="dark"] .revenue-breakdown .item .amount.purple { color: #A78BFA; }
    html[data-theme="dark"] .revenue-breakdown .item .amount.gold { color: #FBBF24; }

    .cashier-card .card-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 4px;
        padding: 12px 14px;
        background: var(--page-bg-body);
        border-top: 2px solid var(--page-border);
        border-bottom: 2px solid var(--page-border);
    }

    html[data-theme="dark"] .cashier-card .card-stats { background: #0F172A; }

    .cashier-card .card-stats .stat-item {
        text-align: center;
        padding: 4px 0;
        border-radius: 6px;
    }

    .cashier-card .card-stats .stat-item .num {
        font-size: 0.95rem;
        font-weight: 800;
        color: var(--page-text-primary);
    }

    .cashier-card .card-stats .stat-item .num.green { color: #059669; }
    .cashier-card .card-stats .stat-item .num.orange { color: #D97706; }
    .cashier-card .card-stats .stat-item .num.purple { color: #7C3AED; }
    .cashier-card .card-stats .stat-item .num.teal { color: #0D9488; }
    .cashier-card .card-stats .stat-item .num.red { color: #DC2626; }

    html[data-theme="dark"] .cashier-card .card-stats .stat-item .num.green { color: #34D399; }
    html[data-theme="dark"] .cashier-card .card-stats .stat-item .num.orange { color: #FBBF24; }
    html[data-theme="dark"] .cashier-card .card-stats .stat-item .num.purple { color: #A78BFA; }
    html[data-theme="dark"] .cashier-card .card-stats .stat-item .num.teal { color: #2DD4BF; }
    html[data-theme="dark"] .cashier-card .card-stats .stat-item .num.red { color: #F87171; }

    .cashier-card .card-stats .stat-item .label {
        font-size: 0.55rem;
        color: var(--page-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 700;
        margin-top: 2px;
        display: block;
    }

    .cashier-card .card-actions {
        padding: 14px 20px;
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    .cashier-card .card-actions .btn-action {
        padding: 6px 16px;
        border-radius: 10px;
        font-size: 0.72rem;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.3s;
        border: 2px solid var(--page-border);
        color: var(--page-text-secondary);
        background: transparent;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-family: var(--font-mono);
    }

    html[data-theme="dark"] .cashier-card .card-actions .btn-action {
        color: #94A3B8;
        border-color: #334155;
    }

    .cashier-card .card-actions .btn-action:hover {
        border-color: var(--page-primary);
        color: var(--page-primary);
        transform: translateY(-2px);
    }

    .cashier-card .card-actions .btn-action.primary {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        border-color: transparent;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
    }

    .cashier-card .card-actions .btn-action.primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(11, 94, 215, 0.35);
    }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--page-text-secondary);
        background: var(--page-bg-card);
        border-radius: 18px;
        border: 2px dashed var(--page-border);
    }

    html[data-theme="dark"] .empty-state { background: #1E293B; }

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
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1200px) {
        .stats-grid-8 { grid-template-columns: repeat(4, 1fr); }
        .cashier-grid { grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); }
    }

    @media (max-width: 1024px) {
        .stats-grid-8 { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-card { padding: 20px; }
        .page-header-title { font-size: 1.2rem; }
        .page-header-title i { width: 36px; height: 36px; font-size: 1rem; }
        .stats-grid-8 { grid-template-columns: 1fr 1fr; gap: 12px; }
        .stat-card-8 .stat-number { font-size: 1.15rem; }
        .stat-card-8 .stat-icon { width: 38px; height: 38px; font-size: 1rem; }
        .cashier-grid { grid-template-columns: 1fr; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar select, .filter-bar input { width: 100%; min-width: unset; }
    }

    @media (max-width: 480px) {
        .stats-grid-8 { grid-template-columns: 1fr; gap: 12px; }
        .stat-card-8 .stat-number { font-size: 1.15rem; }
        .stat-card-8 { min-height: 120px; padding: 14px 16px; }
        .stat-card-8 .stat-icon { width: 34px; height: 34px; font-size: 0.9rem; }
        .page-header-title { font-size: 1rem; }
        .page-header-subtitle { font-size: 0.78rem; flex-direction: column; align-items: flex-start; gap: 6px; }
        .cashier-card .card-stats { grid-template-columns: repeat(2, 1fr); }
    }

    @media print {
        .page-header-actions, .btn, .btn-outline-light, .filter-bar, .card-actions { display: none !important; }
        .page-header-card { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .cashier-card .card-top { background: #0A4CA8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .stat-card-8, .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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
                <i class="fas fa-cash-register"></i>
                Cashiers Dashboard
                <span class="role-badge-display"><i class="fas fa-user-shield"></i> ADMIN</span>
                <span class="v13-badge">
                    <span class="pulse-dot"></span> V13.1 GROSS
                </span>
            </h1>
            <p class="page-header-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= $total_cashiers ?></strong> branch<?= $total_cashiers != 1 ? 'es' : '' ?> found
                <span class="page-header-badge" style="background:rgba(251,191,36,0.25);">
                    <i class="fas fa-money-bill-wave"></i>
                    TSh <?= number_format($financial['total_revenue'], 0) ?> Revenue
                </span>
                <span class="page-header-badge" style="background:rgba(52,211,153,0.25);">
                    <i class="fas fa-file-invoice"></i>
                    <?= number_format($financial['total_bills']) ?> Bills
                </span>
                <span class="page-header-badge" style="background:rgba(248,113,113,0.25);">
                    <i class="fas fa-arrow-up"></i>
                    TSh <?= number_format($financial['total_expenses'], 0) ?> Expenses
                </span>
                <?php if (($financial['total_premium'] ?? 0) > 0): ?>
                    <span class="page-header-badge" style="background:rgba(251,191,36,0.35);">
                        <i class="fas fa-crown"></i>
                        TSh <?= number_format($financial['total_premium'], 0) ?> Premium
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="page-header-actions">
            <a href="add_cashier.php" class="btn-outline-light">
                <i class="fas fa-plus"></i> Add Cashier
            </a>
        </div>
    </div>

    <!-- ================================================================
         8 CARDS - 4 TOP + 4 BOTTOM (V13.1: Prescription Card #4 pekee)
         ================================================================ -->
    <div class="stats-grid-8 animate-fade-in-up" style="animation-delay:0.05s;">

        <!-- ROW 1 -->
        <!-- 1. Total Revenue - BLUE -->
        <div class="stat-card-8 card-blue">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Revenue</p>
                <p class="stat-number">TSh <?= number_format($financial['total_revenue'], 0) ?></p>
                <p class="stat-sub"><i class="fas fa-chart-line"></i> Payments + OTC</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>

        <!-- 2. Total Expenses - RED -->
        <div class="stat-card-8 card-red">
            <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Expenses</p>
                <p class="stat-number">TSh <?= number_format($financial['total_expenses'], 0) ?></p>
                <p class="stat-sub"><i class="fas fa-file-invoice"></i> From expenses</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>

        <!-- 3. Net Profit - GREEN -->
        <div class="stat-card-8 card-green">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-content">
                <p class="stat-label">Net Profit</p>
                <p class="stat-number">TSh <?= number_format($financial['net_profit'], 0) ?></p>
                <p class="stat-sub"><i class="fas fa-calculator"></i> Revenue - Expenses</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>

        <!-- 4. Prescription Revenue (GROSS) - PURPLE (V13.1: Card #4 pekee) -->
        <div class="stat-card-8 card-purple">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="stat-content">
                <p class="stat-label">Prescription (Gross)</p>
                <p class="stat-number" style="font-size:1.35rem;">
                    TSh <?= number_format($financial['prescription_revenue'], 2, '.', ',') ?>
                </p>
                <p class="stat-sub">
                    <i class="fas fa-info-circle"></i> GROSS = Medication RAW
                </p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>

        <!-- ROW 2 -->
        <!-- 5. OTC Revenue - TEAL -->
        <div class="stat-card-8 card-teal">
            <div class="stat-icon"><i class="fas fa-cash-register"></i></div>
            <div class="stat-content">
                <p class="stat-label">OTC Revenue</p>
                <p class="stat-number">TSh <?= number_format($financial['otc_revenue'], 0) ?></p>
                <p class="stat-sub"><i class="fas fa-shopping-cart"></i> Over-the-counter</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>

        <!-- 6. ALL DISCOUNT - GOLD -->
        <div class="stat-card-8 card-gold">
            <div class="stat-icon"><i class="fas fa-tag"></i></div>
            <div class="stat-content">
                <p class="stat-label">All Discount</p>
                <p class="stat-number">TSh <?= number_format($financial['total_discount'] ?? 0, 0) ?></p>
                <p class="stat-sub"><i class="fas fa-percent"></i> Pharmacy + Cashier</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>

        <!-- 7. ALL PREMIUM - GOLD with PULSE -->
        <div class="stat-card-8 card-gold" style="animation: premiumPulse 2.5s ease-in-out infinite;">
            <div class="stat-icon"><i class="fas fa-crown"></i></div>
            <div class="stat-content">
                <p class="stat-label">👑 All Premium</p>
                <p class="stat-number">TSh <?= number_format($financial['total_premium'] ?? 0, 0) ?></p>
                <p class="stat-sub"><i class="fas fa-star"></i> Pharmacy + Cashier</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>

        <!-- 8. Pending Bills - ORANGE -->
        <div class="stat-card-8 card-orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-content">
                <p class="stat-label">Pending Bills</p>
                <p class="stat-number"><?= number_format($financial['pending_bills']) ?></p>
                <p class="stat-sub"><i class="fas fa-hourglass-half"></i> Awaiting payment</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>

    </div>

    <!-- Filter Bar -->
    <div class="filter-bar animate-fade-in-up" style="animation-delay:0.1s;">
        <span class="filter-label"><i class="fas fa-filter"></i> Filter</span>
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;flex:1;">

            <select name="branch" onchange="this.form.submit()">
                <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                        🏥 <?= htmlspecialchars($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status" onchange="this.form.submit()">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>

            <input type="text" name="search" placeholder="Search by name, location..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px;">

            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
            <a href="cashiers.php" class="btn btn-outline"><i class="fas fa-times"></i> Reset</a>
        </form>
    </div>

    <!-- Branch Grid -->
    <?php if (count($cashiers) > 0): ?>
        <div class="cashier-grid animate-fade-in-up" style="animation-delay:0.15s;">
            <?php foreach ($cashiers as $cashier): 
                $net_profit_branch = ($cashier['total_revenue'] ?? 0) - ($cashier['branch_expenses'] ?? 0);
            ?>
                <div class="cashier-card">
                    <div class="card-top">
                        <div>
                            <div class="name">
                                <i class="fas fa-store-alt"></i>
                                <?= htmlspecialchars($cashier['name']) ?>
                            </div>
                            <div class="location-text">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= htmlspecialchars($cashier['location'] ?? 'N/A') ?>
                            </div>
                        </div>
                        <span class="badge badge-<?= ($cashier['status'] ?? 'active') === 'active' ? 'success' : 'danger' ?>">
                            <?= ucfirst($cashier['status'] ?? 'Active') ?>
                        </span>
                    </div>

                    <div class="card-body">
                        <div class="info-row">
                            <i class="fas fa-phone"></i>
                            <?= htmlspecialchars($cashier['phone'] ?? 'N/A') ?>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-envelope"></i>
                            <?= htmlspecialchars($cashier['email'] ?? 'N/A') ?>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-user-tie"></i>
                            <?= ($cashier['active_cashiers'] ?? 0) ?> Active / <?= ($cashier['total_cashiers'] ?? 0) ?> Cashiers
                        </div>

                        <div class="revenue-breakdown">
                            <span class="item">
                                <span class="label">Bills:</span>
                                <span class="amount blue">TSh <?= number_format($cashier['patient_bills_revenue'] ?? 0, 0) ?></span>
                            </span>
                            <span class="item">
                                <span class="label">OTC:</span>
                                <span class="amount green">TSh <?= number_format($cashier['otc_revenue'] ?? 0, 0) ?></span>
                            </span>
                            <span class="item">
                                <span class="label">💊 Presc:</span>
                                <span class="amount purple">TSh <?= number_format($cashier['prescription_revenue'] ?? 0, 2, '.', ',') ?></span>
                            </span>
                            <span class="item">
                                <span class="label">🏷️ Disc:</span>
                                <span class="amount gold">TSh <?= number_format($cashier['branch_discount'] ?? 0, 0) ?></span>
                            </span>
                            <span class="item">
                                <span class="label">👑 Prem:</span>
                                <span class="amount gold">TSh <?= number_format($cashier['branch_premium'] ?? 0, 0) ?></span>
                            </span>
                            <span class="item">
                                <span class="label">Exp:</span>
                                <span class="amount red">TSh <?= number_format($cashier['branch_expenses'] ?? 0, 0) ?></span>
                            </span>
                            <span class="item" style="font-weight:700;">
                                <span class="label">Net:</span>
                                <span class="amount teal">TSh <?= number_format($net_profit_branch, 0) ?></span>
                            </span>
                        </div>
                    </div>

                    <div class="card-stats">
                        <div class="stat-item">
                            <div class="num green"><?= number_format($cashier['total_bills'] ?? 0) ?></div>
                            <span class="label">Bills</span>
                        </div>
                        <div class="stat-item">
                            <div class="num <?= ($cashier['pending_bills'] ?? 0) > 0 ? 'orange' : 'green' ?>">
                                <?= number_format($cashier['pending_bills'] ?? 0) ?>
                            </div>
                            <span class="label">Pending</span>
                        </div>
                        <div class="stat-item">
                            <div class="num purple"><?= number_format($cashier['paid_bills'] ?? 0) ?></div>
                            <span class="label">Paid</span>
                        </div>
                        <div class="stat-item">
                            <div class="num teal">TSh <?= number_format($cashier['total_revenue'] ?? 0, 0) ?></div>
                            <span class="label">Revenue</span>
                        </div>
                    </div>

                    <div class="card-actions">
                        <a href="view_cashier.php?id=<?= (int)$cashier['id'] ?>" class="btn-action primary">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="edit_cashier.php?id=<?= (int)$cashier['id'] ?>" class="btn-action">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align:center;font-size:0.85rem;color:var(--page-text-secondary);padding:10px;font-family:var(--font-mono);">
            Showing <strong><?= count($cashiers) ?></strong> branch<?= count($cashiers) > 1 ? 'es' : '' ?>
        </div>
    <?php else: ?>
        <div class="empty-state animate-fade-in-up">
            <i class="fas fa-store-alt"></i>
            <h3>No Branches Found</h3>
            <p>No active branches found. <a href="branches.php" style="color:var(--page-primary);text-decoration:underline;">Manage branches</a></p>
        </div>
    <?php endif; ?>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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

    console.log('%c💰 Braick - Cashiers Dashboard V13.1 GROSS', 'font-family: monospace; font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ V13.1: Formula breakdown card ya juu imeondolewa', 'font-family: monospace; font-size:13px; color:#10B981; font-weight:bold;');
    console.log('%c✅ Prescription (GROSS) ipo Card #4 pekee', 'font-family: monospace; font-size:13px; color:#FCD34D; font-weight:bold;');
    console.log('%c✅ V13: PRESCRIPTION = Medication_RAW (GROSS - bila discount, bila premium)', 'font-family: monospace; font-size:13px; color:#10B981; font-weight:bold;');
    console.log('%c✅ V13: Prescription BILA round off (exact value, 2 decimal places)', 'font-family: monospace; font-size:13px; color:#FCD34D; font-weight:bold;');
    console.log('%c✅ SAWA KWA 100% NA AUDIT V13, ADMIN V20', 'font-family: monospace; font-size:13px; color:#FCD34D; font-weight:bold;');
    console.log('%c💊 Prescription (GROSS): TSh <?= number_format($financial['prescription_revenue'] ?? 0, 2, '.', ',') ?>', 'font-family: monospace; font-size:12px; color:#7C3AED; font-weight:bold;');
</script>

</body>
</html>