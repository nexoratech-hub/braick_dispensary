<?php
// ================================================================
// FILE: frontend/pages/admin/pharmacies.php
// SUPER ADMIN - VIEW ALL PHARMACIES WITH BRANCH FILTERING (V3)
// ✅ V3: PRESCRIPTION = Medication_RAW (GROSS - bila discount, bila premium)
// ✅ V3: Prescription BILA round off (exact value, 2 decimal places)
// ✅ SAWA KWA 100% NA AUDIT V13, ADMIN V20, CASHIERS V13.1, VIEW_CASHIER V14
// ✅ DESIGN MPYA: Gradient cards, JetBrains Mono, animations
// ✅ Inatumia SHARED HEADER & SIDEBAR pekee
// ✅ BLUE THEME
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

if (isset($_GET['error']) && $_GET['error'] === 'invalid_id') {
    $params = $_GET;
    unset($params['error']);
    $new_url = 'pharmacies.php?' . http_build_query($params);
    header('Location: ' . $new_url);
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

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// ✅ V3: ROUND TO NEAREST 50 FUNCTION (kwa cards zingine)
// ================================================================
function round_to_50($value) {
    return round($value / 50) * 50;
}

$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

if ($selected_branch_id !== 'all' && !is_numeric($selected_branch_id)) {
    $selected_branch_id = 'all';
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';

$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// ✅ V3: PRESCRIPTION REVENUE = GROSS (Bila round off)
// Formula: Medication_RAW (GROSS - bila discount, bila premium)
// ================================================================
$total_medication_raw = 0;
$total_pharmacy_discount = 0;
$total_prescription_revenue = 0;

try {
    // 1. Medication RAW (GROSS - bila discount)
    $sql = "SELECT COALESCE(SUM(bi.total_price), 0) as total 
            FROM bill_items bi 
            INNER JOIN bills b ON bi.bill_id = b.id 
            WHERE bi.item_type = 'medication' 
            AND bi.status != 'cancelled' 
            AND b.status IN ('paid', 'partial') 
            AND b.patient_id IS NOT NULL 
            AND b.visit_id IS NOT NULL 
            AND b.bill_number NOT LIKE 'BILL-OTC-%'";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $sql .= " AND bi.branch_id = " . (int)$selected_branch_id;
    }
    $stmt = $db->query($sql);
    $total_medication_raw = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // 2. Pharmacy Discount (info only - haipunguzwi kwenye Prescription)
    $sql = "SELECT COALESCE(SUM(b.pharmacy_discount), 0) as total 
            FROM bills b 
            WHERE b.status IN ('paid', 'partial') 
            AND b.patient_id IS NOT NULL 
            AND b.visit_id IS NOT NULL 
            AND b.bill_number NOT LIKE 'BILL-OTC-%'
            AND b.pharmacy_discount > 0";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $sql .= " AND b.branch_id = " . (int)$selected_branch_id;
    }
    $stmt = $db->query($sql);
    $total_pharmacy_discount = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // ✅ V3: Prescription Revenue = Medication_RAW (GROSS - bila discount, bila premium)
    // BILA round off - exact value
    $total_prescription_revenue = (float)$total_medication_raw;
    
} catch (Exception $e) {}

// Round medication raw + pharmacy discount (info)
$total_medication_raw = round_to_50($total_medication_raw);
$total_pharmacy_discount = round_to_50($total_pharmacy_discount);

// ================================================================
// PRESCRIPTION COUNTS
// ================================================================
$total_prescriptions_count = 0;
$total_dispensed_count = 0;
$total_pending_count = 0;

try {
    $sql = "SELECT COUNT(*) as total FROM prescriptions WHERE 1=1";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $sql .= " AND branch_id = " . (int)$selected_branch_id;
    }
    $stmt = $db->query($sql);
    $total_prescriptions_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql = "SELECT COUNT(*) as total FROM prescriptions WHERE status = 'dispensed'";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $sql .= " AND branch_id = " . (int)$selected_branch_id;
    }
    $stmt = $db->query($sql);
    $total_dispensed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql = "SELECT COUNT(*) as total FROM prescriptions WHERE status = 'pending'";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $sql .= " AND branch_id = " . (int)$selected_branch_id;
    }
    $stmt = $db->query($sql);
    $total_pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// OTC SALES DATA
// ================================================================
$total_otc_sales_db = 0;
$total_otc_revenue_db = 0;

try {
    $sql_otc = "SELECT COUNT(*) as total_sales, COALESCE(SUM(total_amount), 0) as total_revenue FROM otc_sales WHERE payment_status = 'paid'";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $sql_otc .= " AND branch_id = " . (int)$selected_branch_id;
    }
    $stmt = $db->query($sql_otc);
    $otc_totals = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_otc_sales_db = $otc_totals['total_sales'] ?? 0;
    $total_otc_revenue_db = $otc_totals['total_revenue'] ?? 0;
} catch (Exception $e) {}

$total_otc_revenue_db = round_to_50($total_otc_revenue_db);
$total_revenue = $total_prescription_revenue + $total_otc_revenue_db;

// ================================================================
// PHARMACIES QUERY - V3 with GROSS Prescription per branch
// ================================================================
$sql = "
    SELECT 
        b.*,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy' AND status = 'active') as active_pharmacists,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy') as total_pharmacists,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active') as total_medicines,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND quantity <= reorder_level AND quantity > 0) as low_stock_items,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND quantity <= 0) as out_of_stock_items,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'pending') as pending_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'dispensed') as dispensed_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id) as total_prescriptions,
        (SELECT COUNT(*) FROM otc_sales WHERE branch_id = b.id) as total_otc_sales,
        (SELECT COALESCE(SUM(total_amount), 0) FROM otc_sales WHERE branch_id = b.id AND payment_status = 'paid') as otc_revenue,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND expiry_date < CURDATE()) as expired_medicines,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) as expiring_soon_medicines,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active') as total_active_medicines,
        -- ✅ V3: PRESCRIPTION = Medication_RAW (GROSS - bila discount, bila premium)
        COALESCE(
            (SELECT COALESCE(SUM(bi.total_price), 0) 
             FROM bill_items bi 
             INNER JOIN bills bl ON bi.bill_id = bl.id 
             WHERE bi.item_type = 'medication' 
             AND bi.status != 'cancelled' 
             AND bl.status IN ('paid', 'partial') 
             AND bl.patient_id IS NOT NULL 
             AND bl.visit_id IS NOT NULL 
             AND bl.bill_number NOT LIKE 'BILL-OTC-%'
             AND bl.branch_id = b.id),
            0
        ) as prescription_revenue,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'dispensed') as prescription_sales_count
    FROM branches b
    WHERE 1=1
";

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $sql .= " AND b.id = " . (int)$selected_branch_id;
}
if ($status_filter !== 'all') {
    $sql .= " AND b.status = '" . addslashes($status_filter) . "'";
}
if (!empty($search)) {
    $sql .= " AND (b.name LIKE '%" . addslashes($search) . "%' OR b.location LIKE '%" . addslashes($search) . "%')";
}
$sql .= " ORDER BY b.name ASC";

$pharmacies = [];
try {
    $stmt = $db->query($sql);
    $pharmacies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pharmacies = [];
}

// ================================================================
// TOTALS
// ================================================================
$total_pharmacies = count($pharmacies);
$total_medicines = 0;
$total_prescriptions = 0;
$total_dispensed = 0;
$total_pending = 0;
$total_otc_sales = 0;
$total_otc_revenue = 0;
$total_prescription_revenue_sum = 0;
$total_revenue_sum = 0;
$total_out_of_stock = 0;
$total_low_stock = 0;
$total_expired = 0;
$total_expiring_soon = 0;

foreach ($pharmacies as $p) {
    $total_medicines += $p['total_medicines'] ?? 0;
    $total_prescriptions += $p['total_prescriptions'] ?? 0;
    $total_dispensed += $p['dispensed_prescriptions'] ?? 0;
    $total_pending += $p['pending_prescriptions'] ?? 0;
    $total_otc_sales += $p['total_otc_sales'] ?? 0;
    $total_otc_revenue += $p['otc_revenue'] ?? 0;
    $total_prescription_revenue_sum += $p['prescription_revenue'] ?? 0;
    $total_revenue_sum += ($p['prescription_revenue'] ?? 0) + ($p['otc_revenue'] ?? 0);
    $total_out_of_stock += $p['out_of_stock_items'] ?? 0;
    $total_low_stock += $p['low_stock_items'] ?? 0;
    $total_expired += $p['expired_medicines'] ?? 0;
    $total_expiring_soon += $p['expiring_soon_medicines'] ?? 0;
}

// ================================================================
// INVENTORY DATA
// ================================================================
$inventory_items = [];
try {
    $sql_inventory = "
        SELECT mi.id, mi.medication_name, mi.category, mi.unit, mi.quantity, mi.reorder_level,
               mi.unit_cost, mi.selling_price, mi.supplier, mi.expiry_date, mi.batch_number,
               mi.branch_id, mi.status, b.name as branch_name
        FROM medications_inventory mi
        INNER JOIN branches b ON mi.branch_id = b.id
        WHERE mi.status = 'active'
    ";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $sql_inventory .= " AND mi.branch_id = " . (int)$selected_branch_id;
    }
    $sql_inventory .= " ORDER BY b.name, mi.medication_name ASC";
    $stmt = $db->query($sql_inventory);
    $inventory_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $inventory_items = [];
}

// ================================================================
// DISPLAY BRANCH NAME
// ================================================================
$display_branch_name = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    foreach ($branches as $b) {
        if ($b['id'] == $selected_branch_id) {
            $display_branch_name = $b['name'];
            break;
        }
    }
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'active' => 'success', 'inactive' => 'danger', 'pending' => 'warning',
        'dispensed' => 'success', 'confirmed' => 'info', 'cancelled' => 'danger',
        'paid' => 'success', 'partial' => 'warning'
    ];
    return $classes[$status] ?? 'secondary';
}

function getInventoryStatusBadge($quantity, $reorder_level) {
    if ($quantity <= 0) {
        return '<span class="badge badge-danger" style="font-size:0.6rem;"><i class="fas fa-times-circle"></i> Out of Stock</span>';
    } elseif ($quantity <= $reorder_level) {
        return '<span class="badge badge-warning" style="font-size:0.6rem;"><i class="fas fa-exclamation-triangle"></i> Low Stock</span>';
    } else {
        return '<span class="badge badge-success" style="font-size:0.6rem;"><i class="fas fa-check-circle"></i> In Stock</span>';
    }
}

function getExpiryBadge($expiry_date) {
    if (empty($expiry_date)) {
        return '<span class="badge badge-secondary" style="font-size:0.6rem;">N/A</span>';
    }
    $today = new DateTime();
    $expiry = new DateTime($expiry_date);
    $diff = $today->diff($expiry)->days;
    
    if ($expiry < $today) {
        return '<span class="badge badge-danger" style="font-size:0.6rem;"><i class="fas fa-skull"></i> Expired</span>';
    } elseif ($diff <= 30) {
        return '<span class="badge badge-warning" style="font-size:0.6rem;"><i class="fas fa-clock"></i> ' . $diff . ' days</span>';
    } else {
        return '<span class="badge badge-success" style="font-size:0.6rem;"><i class="fas fa-check"></i> ' . $diff . ' days</span>';
    }
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS - V3 DESIGN MPYA -->
<!-- ================================================================ -->
<style>
@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&display=swap');

:root {
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-bg: #EFF6FF;
    --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    --success: #059669;
    --danger: #DC2626;
    --warning: #D97706;
    --purple: #7C3AED;
    --cyan: #0891B2;
    --bg-card: #FFFFFF;
    --bg-body: #F1F5F9;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --table-hover: #F1F5F9;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
}

[data-theme="dark"] {
    --bg-card: #1E293B;
    --bg-body: #0F172A;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary-bg: #1E3A5F;
    --table-hover: #1E293B;
}

/* JetBrains Mono for numbers */
.money-cell, .card-value, .stat-number, .stat-amount, .revenue-amount, .stat-number-small, .stat-amount-large, .rank-badge {
    font-family: var(--font-mono) !important;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

/* PAGE HEADER */
.page-header-box {
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
    border-radius: 16px;
    padding: 24px 32px;
    margin-bottom: 24px;
    box-shadow: 0 6px 24px rgba(11, 94, 215, 0.25);
    position: relative;
    overflow: hidden;
}

.page-header-box::before {
    content: '';
    position: absolute;
    top: -60%; right: -10%;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.page-header-box .page-title {
    color: white;
    font-size: 1.6rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin: 0;
    font-family: var(--font-mono);
}

.page-header-box .page-title .role-badge-display {
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
}

.page-header-box .page-title .branch-name-display {
    background: rgba(255,255,255,0.15);
    padding: 3px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.page-header-box .page-title .btn-inventory-top-green {
    background: linear-gradient(135deg, #059669, #047857);
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
    margin-left: 4px;
    font-family: var(--font-mono);
}

.page-header-box .page-title .btn-inventory-top-green:hover {
    background: linear-gradient(135deg, #047857, #065F46);
    transform: translateY(-2px) scale(1.03);
    box-shadow: 0 6px 20px rgba(5, 150, 105, 0.5);
}

.page-header-box .page-subtitle {
    color: rgba(255,255,255,0.9);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin-top: 10px;
}

.page-header-box .page-subtitle strong { color: white; font-weight: 700; font-family: var(--font-mono); }

.page-header-box .header-badge {
    background: rgba(255,255,255,0.12);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.68rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid rgba(255,255,255,0.15);
    backdrop-filter: blur(4px);
    font-family: var(--font-mono);
}

.page-header-box .header-badge.medicines {
    background: rgba(52, 211, 153, 0.2);
    border-color: rgba(52, 211, 153, 0.3);
    color: #6EE7B7;
}

.page-header-box .header-badge.revenue {
    background: rgba(251, 191, 36, 0.2);
    border-color: rgba(251, 191, 36, 0.3);
    color: #FBBF24;
}

.page-header-box .header-badge.rx {
    background: rgba(124, 58, 237, 0.2);
    border-color: rgba(124, 58, 237, 0.3);
    color: #A78BFA;
}

.page-header-box .header-badge.otc {
    background: rgba(251, 146, 60, 0.2);
    border-color: rgba(251, 146, 60, 0.3);
    color: #FDBA74;
}

/* STATS GRID 8 */
.stats-grid-8 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 24px;
}

.stat-card-8 {
    border-radius: 14px;
    padding: 16px 18px;
    border: none;
    display: flex;
    flex-direction: column;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    color: white;
    position: relative;
    overflow: hidden;
    min-height: 130px;
    cursor: pointer;
}

.stat-card-8::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 160px; height: 160px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
    pointer-events: none;
    transition: transform 0.4s ease;
}

.stat-card-8::after {
    content: '';
    position: absolute;
    bottom: -40%; left: -20%;
    width: 150px; height: 150px;
    background: rgba(255,255,255,0.03);
    border-radius: 50%;
    pointer-events: none;
    transition: transform 0.4s ease;
}

.stat-card-8:hover {
    transform: translateY(-6px) scale(1.01);
    box-shadow: 0 12px 35px rgba(0,0,0,0.2);
}

.stat-card-8:hover::before { transform: scale(1.2); right: -15%; }
.stat-card-8:hover::after { transform: scale(1.3); bottom: -30%; }

.stat-card-8 .stat-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    background: rgba(255,255,255,0.18);
    color: white;
    border: 1px solid rgba(255,255,255,0.12);
    position: relative;
    z-index: 1;
    margin-bottom: 8px;
    transition: transform 0.3s ease, background 0.3s ease;
}

.stat-card-8:hover .stat-icon {
    transform: scale(1.1) rotate(-5deg);
    background: rgba(255,255,255,0.3);
}

.stat-card-8 .stat-content {
    position: relative;
    z-index: 1;
    flex: 1;
}

.stat-card-8 .stat-label {
    font-size: 0.6rem;
    color: rgba(255,255,255,0.85);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin: 0 0 4px 0;
    font-family: var(--font-mono);
}

.stat-card-8 .stat-number-small {
    font-size: 2.2rem;
    font-weight: 800;
    color: white;
    margin: 0;
    line-height: 1.1;
    font-family: var(--font-mono);
}

.stat-card-8 .stat-amount-large {
    font-size: 1.6rem;
    font-weight: 800;
    color: white;
    margin: 0;
    line-height: 1.2;
    font-family: var(--font-mono);
}

.stat-card-8 .stat-currency {
    font-size: 0.8rem;
    font-weight: 600;
    color: rgba(255,255,255,0.9);
    margin-right: 3px;
    font-family: var(--font-mono);
}

.stat-card-8 .stat-sub {
    font-size: 0.6rem;
    color: rgba(255,255,255,0.9);
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    font-family: var(--font-mono);
}

.stat-card-8 .stat-sub .highlight {
    color: rgba(255,255,255,0.95);
    font-weight: 700;
    font-family: var(--font-mono);
}

.stat-card-8 .stat-sub .badge-mini {
    font-size: 0.5rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 10px;
    background: rgba(255,255,255,0.2);
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-family: var(--font-mono);
}

.stat-card-8 .stat-sub .badge-mini.danger {
    background: rgba(239, 68, 68, 0.4);
    color: #FCA5A5;
}

.stat-card-8 .stat-sub .badge-mini.warning {
    background: rgba(245, 158, 11, 0.4);
    color: #FCD34D;
}

.stat-card-8 .stat-sub .badge-mini.success {
    background: rgba(52, 211, 153, 0.4);
    color: #6EE7B7;
}

.stat-card-8 .stat-arrow {
    position: absolute;
    right: 12px; bottom: 12px;
    color: rgba(255,255,255,0.12);
    font-size: 0.7rem;
    z-index: 1;
    transition: all 0.3s ease;
}

.stat-card-8:hover .stat-arrow {
    transform: translateX(4px);
    color: rgba(255,255,255,0.6);
}

.stat-card-8 .flex-row {
    display: flex;
    align-items: baseline;
    gap: 10px;
    flex-wrap: wrap;
}

.card-blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
.card-blue:hover { box-shadow: 0 12px 35px rgba(11, 94, 215, 0.4); }
.card-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
.card-red:hover { box-shadow: 0 12px 35px rgba(220, 38, 38, 0.4); }
.card-green { background: linear-gradient(135deg, #059669, #047857); }
.card-green:hover { box-shadow: 0 12px 35px rgba(5, 150, 105, 0.4); }
.card-orange { background: linear-gradient(135deg, #D97706, #B45309); }
.card-orange:hover { box-shadow: 0 12px 35px rgba(217, 119, 6, 0.4); }
.card-purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
.card-purple:hover { box-shadow: 0 12px 35px rgba(124, 58, 237, 0.4); }
.card-cyan { background: linear-gradient(135deg, #0891B2, #0E7490); }
.card-cyan:hover { box-shadow: 0 12px 35px rgba(8, 145, 178, 0.4); }

/* FILTER BAR */
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
    align-items: center;
    background: var(--bg-card);
    padding: 14px 18px;
    border-radius: 12px;
    border: 2px solid var(--border-color);
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}

.filter-bar:hover {
    box-shadow: var(--shadow-md);
    border-color: var(--primary);
}

.filter-bar .filter-label {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--primary);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-family: var(--font-mono);
}

.filter-bar select, .filter-bar input {
    background: var(--bg-body);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 8px 14px;
    font-size: 0.8rem;
    color: var(--text-primary);
    outline: none;
    transition: all 0.3s;
    min-width: 150px;
    font-family: var(--font-mono);
}

.filter-bar select:focus, .filter-bar input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
}

/* BUTTONS */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.8rem;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
    text-decoration: none;
    font-family: var(--font-mono);
}

.btn-primary { background: var(--primary-gradient); color: white; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }

.btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
.btn-outline:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); }

/* PHARMACY GRID */
.pharmacy-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.pharmacy-card {
    background: var(--bg-card);
    border-radius: 18px;
    border: 2px solid var(--border-color);
    overflow: hidden;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-sm);
    position: relative;
}

.pharmacy-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--primary-gradient);
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 2;
}

.pharmacy-card:hover {
    transform: translateY(-6px);
    border-color: var(--primary);
    box-shadow: 0 20px 40px rgba(0,0,0,0.12);
}

.pharmacy-card:hover::before { opacity: 1; }

.pharmacy-card-header {
    padding: 16px 20px;
    background: var(--primary-gradient);
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.pharmacy-card-header::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 150px; height: 150px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
    pointer-events: none;
}

.pharmacy-card-header .pharmacy-name {
    font-size: 1rem;
    font-weight: 700;
    color: white;
    display: flex;
    align-items: center;
    gap: 8px;
    position: relative;
    z-index: 1;
    font-family: var(--font-mono);
}

.pharmacy-card-body { padding: 16px 20px; }

.revenue-section {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 10px;
    margin-bottom: 14px;
    padding: 14px;
    background: linear-gradient(135deg, rgba(11,94,215,0.05) 0%, rgba(11,94,215,0.02) 100%);
    border-radius: 12px;
    border: 2px solid var(--border-color);
}

.revenue-item { text-align: center; }

.revenue-item .revenue-label {
    font-size: 0.55rem;
    color: var(--text-secondary);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-family: var(--font-mono);
}

.revenue-item .revenue-amount {
    font-size: 0.9rem;
    font-weight: 800;
    color: var(--text-primary);
    font-family: var(--font-mono);
    margin-top: 2px;
}

.revenue-item .revenue-amount.blue { color: var(--primary); }
.revenue-item .revenue-amount.green { color: #059669; }
.revenue-item .revenue-amount.orange { color: #D97706; }

.stats-inner-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    margin-bottom: 12px;
}

.stats-inner-grid .stat-inner {
    text-align: center;
    padding: 10px 6px;
    border-radius: 10px;
    border: 2px solid var(--border-color);
    background: var(--bg-body);
    transition: all 0.3s ease;
}

.stats-inner-grid .stat-inner:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
}

.stats-inner-grid .stat-inner .stat-number {
    font-size: 1.15rem;
    font-weight: 800;
    font-family: var(--font-mono);
}

.stats-inner-grid .stat-inner .stat-number.primary { color: var(--primary); }
.stats-inner-grid .stat-inner .stat-number.success { color: #059669; }
.stats-inner-grid .stat-inner .stat-number.danger { color: #DC2626; }
.stats-inner-grid .stat-inner .stat-number.warning { color: #D97706; }
.stats-inner-grid .stat-inner .stat-number.purple { color: #7C3AED; }
.stats-inner-grid .stat-inner .stat-number.teal { color: #0D9488; }

.stats-inner-grid .stat-inner .stat-label {
    font-size: 0.5rem;
    color: var(--text-secondary);
    font-weight: 700;
    text-transform: uppercase;
    font-family: var(--font-mono);
    margin-top: 2px;
}

.stock-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 2px dashed var(--border-color);
}

.stock-badge {
    font-size: 0.6rem;
    font-weight: 700;
    padding: 3px 12px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: var(--font-mono);
}

.stock-badge.danger { background: #FEE2E2; color: #DC2626; }
.stock-badge.warning { background: #FEF3C7; color: #D97706; }
.stock-badge.success { background: #D1FAE5; color: #059669; }
.stock-badge.info { background: #EFF6FF; color: #0B5ED7; }

[data-theme="dark"] .stock-badge.danger { background: #3A1A1A; color: #F87171; }
[data-theme="dark"] .stock-badge.warning { background: #3D2E0A; color: #FBBF24; }
[data-theme="dark"] .stock-badge.success { background: #1A3A2A; color: #34D399; }
[data-theme="dark"] .stock-badge.info { background: #1E3A5F; color: #3B82F6; }

.pharmacy-card-footer {
    padding: 12px 20px;
    background: var(--bg-body);
    border-top: 2px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: wrap;
}

.pharmacy-card-footer .btn-sm {
    padding: 6px 14px;
    font-size: 0.7rem;
    border-radius: 8px;
    font-weight: 700;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: var(--font-mono);
}

.pharmacy-card-footer .btn-sm-primary {
    background: var(--primary-gradient);
    color: white;
}

.pharmacy-card-footer .btn-sm-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}

.pharmacy-card-footer .btn-sm-outline {
    background: transparent;
    color: var(--text-secondary);
    border: 2px solid var(--border-color);
}

.pharmacy-card-footer .btn-sm-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-bg);
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.8rem;
}

.info-row:last-child { border-bottom: none; }

.info-label {
    color: var(--text-secondary);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
    font-family: var(--font-mono);
}

.info-label i { color: var(--primary); width: 16px; font-size: 0.75rem; }

.info-value {
    color: var(--text-primary);
    font-weight: 700;
    font-family: var(--font-mono);
}

/* BADGES */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    color: white;
    font-family: var(--font-mono);
}

.badge-success { background: #059669; }
.badge-danger { background: #DC2626; }
.badge-warning { background: #D97706; color: #1E293B; }
.badge-info { background: #0B5ED7; }
.badge-secondary { background: #64748B; }

/* INVENTORY TABLE */
.inventory-table-container {
    background: var(--bg-card);
    border-radius: 18px;
    border: 2px solid var(--border-color);
    overflow: hidden;
    margin-bottom: 24px;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}

.inventory-table-container:hover {
    box-shadow: var(--shadow-md);
    border-color: var(--primary);
}

.inventory-table-container .table-header {
    padding: 16px 20px;
    background: var(--primary-gradient);
    border-bottom: 2px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    position: relative;
    overflow: hidden;
}

.inventory-table-container .table-header::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 300px; height: 300px;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
    pointer-events: none;
}

.inventory-table-container .table-header .table-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: white;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    position: relative;
    z-index: 1;
    font-family: var(--font-mono);
}

.inventory-table-container .table-header .table-title i {
    color: rgba(255,255,255,0.8);
}

.inventory-table-container .table-header .table-badge {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 3px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    font-family: var(--font-mono);
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.inventory-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
    font-family: var(--font-mono);
}

.inventory-table thead th {
    background: var(--primary-gradient);
    color: white;
    font-weight: 700;
    padding: 10px 14px;
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 2px solid var(--border-color);
    text-align: left;
    position: sticky;
    top: 0;
    z-index: 2;
    font-family: var(--font-mono);
}

.inventory-table td {
    padding: 8px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
    font-family: var(--font-mono);
}

.inventory-table tbody tr:nth-child(even) { background: var(--primary-bg); }
.inventory-table tbody tr:hover td { background: var(--table-hover); }
.inventory-table tbody tr:last-child td { border-bottom: none; }

.table-scroll {
    overflow-x: auto;
    max-height: 500px;
    overflow-y: auto;
}

.text-danger { color: #DC2626; }
.text-warning { color: #D97706; }
.text-success { color: #059669; }

/* EMPTY STATE */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
    background: var(--bg-card);
    border-radius: 18px;
    border: 2px dashed var(--border-color);
}

.empty-state i {
    font-size: 3.5rem;
    color: var(--border-color);
    margin-bottom: 16px;
    display: block;
}

.empty-state h3 {
    font-size: 1.2rem;
    color: var(--text-primary);
    margin-bottom: 8px;
    font-family: var(--font-mono);
}

/* FOOTER */
.footer {
    padding: 14px 0;
    border-top: 2px solid var(--border-color);
    margin-top: 20px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
    font-family: var(--font-mono);
}

.footer .footer-brand { color: var(--primary); font-weight: 700; }

/* ANIMATIONS */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes pulse-dot {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

.animate-fade-in-up {
    animation: fadeInUp 0.5s ease forwards;
    opacity: 0;
}

.pulse-dot {
    display: inline-block;
    width: 6px; height: 6px;
    border-radius: 50%;
    background: currentColor;
    margin-right: 4px;
    animation: pulse-dot 1.5s infinite;
}

/* RESPONSIVE */
@media (max-width: 1200px) {
    .stats-grid-8 { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 1024px) {
    .stats-grid-8 { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .stats-grid-8 { grid-template-columns: 1fr 1fr; }
    .pharmacy-grid { grid-template-columns: 1fr; }
    .page-header-box .page-title { font-size: 1.2rem; }
    .page-header-box { padding: 16px 18px; }
    .revenue-section { grid-template-columns: 1fr 1fr; }
    .stats-inner-grid { grid-template-columns: repeat(2, 1fr); }
    .stat-card-8 { padding: 14px 16px; min-height: 110px; }
    .stat-card-8 .stat-number-small { font-size: 1.8rem; }
    .stat-card-8 .stat-amount-large { font-size: 1.4rem; }
    .stat-card-8 .stat-icon { width: 38px; height: 38px; font-size: 1rem; }
    .inventory-table { font-size: 0.65rem; }
    .inventory-table thead th, .inventory-table td { padding: 6px 8px; }
}

@media (max-width: 480px) {
    .stats-grid-8 { grid-template-columns: 1fr; }
    .stat-card-8 { padding: 12px 14px; min-height: 90px; }
    .stat-card-8 .stat-number-small { font-size: 1.6rem; }
    .stat-card-8 .stat-amount-large { font-size: 1.2rem; }
    .page-header-box .page-title { font-size: 1rem; flex-direction: column; align-items: flex-start; }
    .page-header-box .page-subtitle { font-size: 0.75rem; flex-direction: column; align-items: flex-start; gap: 4px; }
    .inventory-table { font-size: 0.55rem; }
    .inventory-table thead th, .inventory-table td { padding: 4px 6px; }
}
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-box animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription-bottle"></i>
                Pharmacies
                <span class="role-badge-display"><i class="fas fa-user-shield"></i> ADMIN</span>
                <span class="branch-name-display">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($display_branch_name) ?>
                </span>
                <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="btn-inventory-top-green">
                    <i class="fas fa-boxes"></i> 📦 Inventory
                </a>
            </h1>
            <p class="page-subtitle">
                <strong><?= $total_pharmacies ?></strong> pharmacy branches
                <span class="header-badge medicines">
                    <i class="fas fa-pills"></i> <?= number_format($total_medicines) ?> Medicines
                </span>
                <span class="header-badge revenue">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_revenue, 0) ?> Revenue
                </span>
                <span class="header-badge rx">
                    <i class="fas fa-prescription"></i> <?= number_format($total_prescriptions_count) ?> Rx
                </span>
                <span class="header-badge otc">
                    <i class="fas fa-shopping-cart"></i> <?= number_format($total_otc_sales_db) ?> OTC Sales
                </span>
                <span class="header-badge" style="background:rgba(252,211,77,0.15);border-color:rgba(252,211,77,0.3);color:#FCD34D;">
                    <span class="pulse-dot"></span> V3 GROSS
                </span>
            </p>
        </div>
    </div>

    <!-- 8 CARDS -->
    <div class="stats-grid-8 animate-fade-in-up">
        
        <!-- 1. Total Pharmacies - BLUE -->
        <div class="stat-card-8 card-blue">
            <div class="stat-icon"><i class="fas fa-store"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Pharmacies</p>
                <p class="stat-number-small"><?= number_format($total_pharmacies) ?></p>
                <p class="stat-sub">Active branches</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 2. Total Medicines - BLUE -->
        <div class="stat-card-8 card-blue">
            <div class="stat-icon"><i class="fas fa-pills"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Medicines</p>
                <p class="stat-number-small"><?= number_format($total_medicines) ?></p>
                <p class="stat-sub">Active inventory items</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 3. Total Revenue - GREEN -->
        <div class="stat-card-8 card-green">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Revenue</p>
                <p class="stat-amount-large"><span class="stat-currency">TSh</span> <?= number_format($total_revenue, 0) ?></p>
                <p class="stat-sub">
                    <span class="highlight">💊 Rx: TSh <?= number_format($total_prescription_revenue, 2, '.', ',') ?></span>
                    <span class="highlight">🛒 OTC: TSh <?= number_format($total_otc_revenue_db, 0) ?></span>
                </p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 4. Prescriptions - PURPLE (V3: GROSS - Bila round off) -->
        <div class="stat-card-8 card-purple">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="stat-content">
                <p class="stat-label">Prescriptions (Gross)</p>
                <div class="flex-row">
                    <span class="stat-number-small"><?= number_format($total_prescriptions_count) ?></span>
                    <span class="stat-amount-large" style="font-size:1.25rem;">TSh <?= number_format($total_prescription_revenue, 2, '.', ',') ?></span>
                </div>
                <p class="stat-sub">
                    <span class="badge-mini success">✅ <?= number_format($total_dispensed_count) ?> dispensed</span>
                    <span class="badge-mini warning">⏳ <?= number_format($total_pending_count) ?> pending</span>
                </p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 5. OTC Sales - ORANGE -->
        <div class="stat-card-8 card-orange">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-content">
                <p class="stat-label">OTC Sales</p>
                <div class="flex-row">
                    <span class="stat-number-small"><?= number_format($total_otc_sales_db) ?></span>
                    <span class="stat-amount-large" style="font-size:1.4rem;">TSh <?= number_format($total_otc_revenue_db, 0) ?></span>
                </div>
                <p class="stat-sub">💰 Revenue from OTC sales</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 6. Stock Alerts - ORANGE -->
        <div class="stat-card-8 card-orange">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Stock Alerts</p>
                <p class="stat-number-small"><?= number_format($total_out_of_stock + $total_low_stock) ?></p>
                <p class="stat-sub">
                    <span class="badge-mini danger">❌ <?= $total_out_of_stock ?> out of stock</span>
                    <span class="badge-mini warning">⚠️ <?= $total_low_stock ?> low stock</span>
                </p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 7. Expiry Alerts - RED -->
        <div class="stat-card-8 card-red">
            <div class="stat-icon"><i class="fas fa-calendar-times"></i></div>
            <div class="stat-content">
                <p class="stat-label">Expiry Alerts</p>
                <p class="stat-number-small"><?= number_format($total_expired + $total_expiring_soon) ?></p>
                <p class="stat-sub">
                    <span class="badge-mini danger">💀 <?= $total_expired ?> expired</span>
                    <span class="badge-mini warning">⏰ <?= $total_expiring_soon ?> expiring soon</span>
                </p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 8. Pending Prescriptions - ORANGE -->
        <div class="stat-card-8 card-orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-content">
                <p class="stat-label">Pending Prescriptions</p>
                <p class="stat-number-small"><?= number_format($total_pending_count) ?></p>
                <p class="stat-sub">Awaiting approval/dispensing</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
    </div>

    <!-- FILTERS -->
    <div class="filter-bar animate-fade-in-up">
        <span class="filter-label"><i class="fas fa-filter"></i> Filter</span>
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;width:100%;">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            
            <select name="status" onchange="this.form.submit()" style="flex:1;min-width:150px;">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            
            <input type="text" name="search" placeholder="Search pharmacies..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px;">
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
            <a href="pharmacies.php?branch=<?= htmlspecialchars($selected_branch_id) ?>" class="btn btn-outline"><i class="fas fa-times"></i> Clear</a>
        </form>
    </div>

    <!-- PHARMACY GRID -->
    <?php if (count($pharmacies) > 0): ?>
        <div class="pharmacy-grid animate-fade-in-up">
            <?php foreach ($pharmacies as $pharmacy): 
                // ✅ V3: Prescription = GROSS (Bila round off)
                $prescription_revenue = (float)($pharmacy['prescription_revenue'] ?? 0);
                $otc_revenue = round_to_50($pharmacy['otc_revenue'] ?? 0);
                $total_revenue_pharmacy = $prescription_revenue + $otc_revenue;
                $total_prescriptions = $pharmacy['total_prescriptions'] ?? 0;
                $pending_prescriptions = $pharmacy['pending_prescriptions'] ?? 0;
                $dispensed_prescriptions = $pharmacy['dispensed_prescriptions'] ?? 0;
                $prescription_sales = $pharmacy['prescription_sales_count'] ?? 0;
                $out_of_stock = $pharmacy['out_of_stock_items'] ?? 0;
                $low_stock = $pharmacy['low_stock_items'] ?? 0;
                $expired = $pharmacy['expired_medicines'] ?? 0;
                $expiring_soon = $pharmacy['expiring_soon_medicines'] ?? 0;
                $total_medicines_pharmacy = $pharmacy['total_medicines'] ?? 0;
                $has_alerts = $out_of_stock > 0 || $low_stock > 0 || $expired > 0 || $expiring_soon > 0;
            ?>
                <div class="pharmacy-card">
                    <div class="pharmacy-card-header">
                        <span class="pharmacy-name"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($pharmacy['name']) ?></span>
                        <span class="badge badge-<?= getStatusBadge($pharmacy['status'] ?? 'active') ?>" style="font-size:0.6rem;padding:3px 12px;">
                            <?= ucfirst($pharmacy['status'] ?? 'Active') ?>
                        </span>
                    </div>
                    
                    <div class="pharmacy-card-body">
                        <div class="revenue-section">
                            <div class="revenue-item">
                                <div class="revenue-label">💊 Rx (Gross)</div>
                                <div class="revenue-amount blue" style="font-size:0.75rem;">
                                    TSh <?= number_format($prescription_revenue, 2, '.', ',') ?>
                                </div>
                            </div>
                            <div class="revenue-item">
                                <div class="revenue-label">🛒 OTC Revenue</div>
                                <div class="revenue-amount orange">TSh <?= number_format($otc_revenue, 0) ?></div>
                            </div>
                            <div class="revenue-item">
                                <div class="revenue-label">💰 Total Revenue</div>
                                <div class="revenue-amount green">TSh <?= number_format($total_revenue_pharmacy, 0) ?></div>
                            </div>
                        </div>
                        
                        <div class="stats-inner-grid">
                            <div class="stat-inner">
                                <div class="stat-number primary"><?= number_format($total_prescriptions) ?></div>
                                <div class="stat-label">Total Rx</div>
                            </div>
                            <div class="stat-inner">
                                <div class="stat-number <?= $pending_prescriptions > 0 ? 'warning' : 'success' ?>">
                                    <?= number_format($pending_prescriptions) ?>
                                </div>
                                <div class="stat-label">Pending</div>
                            </div>
                            <div class="stat-inner">
                                <div class="stat-number purple"><?= number_format($dispensed_prescriptions) ?></div>
                                <div class="stat-label">Dispensed</div>
                            </div>
                            <div class="stat-inner">
                                <div class="stat-number teal"><?= number_format($prescription_sales) ?></div>
                                <div class="stat-label">Rx Sales</div>
                            </div>
                        </div>
                        
                        <div class="stock-badges">
                            <?php if ($out_of_stock > 0): ?>
                                <span class="stock-badge danger"><i class="fas fa-times-circle"></i> <?= $out_of_stock ?> Out of Stock</span>
                            <?php endif; ?>
                            <?php if ($low_stock > 0): ?>
                                <span class="stock-badge warning"><i class="fas fa-exclamation-triangle"></i> <?= $low_stock ?> Low Stock</span>
                            <?php endif; ?>
                            <?php if ($expired > 0): ?>
                                <span class="stock-badge danger"><i class="fas fa-skull"></i> <?= $expired ?> Expired</span>
                            <?php endif; ?>
                            <?php if ($expiring_soon > 0): ?>
                                <span class="stock-badge warning"><i class="fas fa-clock"></i> <?= $expiring_soon ?> Expiring Soon</span>
                            <?php endif; ?>
                            <?php if (!$has_alerts): ?>
                                <span class="stock-badge success"><i class="fas fa-check-circle"></i> All Clear ✅</span>
                            <?php endif; ?>
                            <span class="stock-badge info" style="margin-left:auto;">
                                <i class="fas fa-pills"></i> <?= number_format($total_medicines_pharmacy) ?> Items
                            </span>
                        </div>
                        
                        <div class="info-row" style="margin-top:10px;">
                            <span class="info-label"><i class="fas fa-map-marker-alt"></i> Location</span>
                            <span class="info-value"><?= htmlspecialchars($pharmacy['location'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-phone"></i> Phone</span>
                            <span class="info-value"><?= htmlspecialchars($pharmacy['phone'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-user-md"></i> Pharmacists</span>
                            <span class="info-value"><?= $pharmacy['active_pharmacists'] ?? 0 ?> Active / <?= $pharmacy['total_pharmacists'] ?? 0 ?> Total</span>
                        </div>
                    </div>
                    
                    <div class="pharmacy-card-footer">
                        <a href="view_pharmacy.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-sm btn-sm-primary">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="otc_sales.php?branch=<?= $pharmacy['id'] ?>" class="btn-sm btn-sm-outline">
                            <i class="fas fa-shopping-cart"></i> OTC
                        </a>
                        <a href="prescriptions.php?branch=<?= $pharmacy['id'] ?>" class="btn-sm btn-sm-outline">
                            <i class="fas fa-prescription"></i> Rx
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align:center;padding:8px 0;font-size:0.75rem;color:var(--text-secondary);font-family:var(--font-mono);">
            Showing <strong><?= count($pharmacies) ?></strong> pharmacy branch<?= count($pharmacies) > 1 ? 'es' : '' ?>
            <?php if ($total_prescriptions_count > 0): ?>
                · <span style="color:#7C3AED;">💊 <?= number_format($total_prescriptions_count) ?> Rx</span>
                · <span style="color:#059669;">💰 TSh <?= number_format($total_prescription_revenue, 2, '.', ',') ?></span>
            <?php endif; ?>
            <?php if ($total_otc_sales_db > 0): ?>
                · <span style="color:#D97706;">🛒 <?= number_format($total_otc_sales_db) ?> OTC · TSh <?= number_format($total_otc_revenue_db, 0) ?></span>
            <?php endif; ?>
            · <span style="color:#0B5ED7;">📊 Total Revenue: TSh <?= number_format($total_revenue, 0) ?></span>
        </div>
        
    <?php else: ?>
        <div class="empty-state animate-fade-in-up">
            <i class="fas fa-prescription-bottle"></i>
            <h3>No Pharmacies Found</h3>
            <p style="color:var(--text-secondary);"><?= !empty($search) ? 'No results match your search criteria.' : 'No pharmacy branches have been created yet.' ?></p>
            <?php if (!empty($search)): ?>
                <a href="pharmacies.php" class="btn btn-primary" style="margin-top:16px;"><i class="fas fa-times"></i> Clear Filters</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- INVENTORY TABLE -->
    <div id="inventory-table" class="inventory-table-container animate-fade-in-up">
        <div class="table-header">
            <h3 class="table-title">
                <i class="fas fa-boxes"></i>
                Medicine Inventory
                <span class="table-badge">
                    <i class="fas fa-pills"></i> <?= count($inventory_items) ?> Medicines
                </span>
                <?php if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)): ?>
                    <span class="table-badge">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($display_branch_name) ?>
                    </span>
                <?php else: ?>
                    <span class="table-badge">
                        <i class="fas fa-globe"></i> All Branches
                    </span>
                <?php endif; ?>
            </h3>
            <div>
                <a href="inventory.php?branch=<?= $selected_branch_id ?>" style="display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:8px;background:rgba(255,255,255,0.15);color:white;text-decoration:none;font-size:0.75rem;font-weight:700;font-family:var(--font-mono);border:1px solid rgba(255,255,255,0.2);transition:all 0.3s ease;">
                    <i class="fas fa-arrow-right"></i> View All
                </a>
            </div>
        </div>
        
        <?php if (count($inventory_items) > 0): ?>
            <div class="table-scroll">
                <table class="inventory-table" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>Branch</th>
                            <th>Medication Name</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Quantity</th>
                            <th>Selling Price</th>
                            <th>Stock Status</th>
                            <th>Expiry</th>
                            <th>Batch #</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory_items as $item): 
                            $quantity = (int)$item['quantity'];
                            $reorder_level = (int)$item['reorder_level'];
                            $selling_price = (float)($item['selling_price'] ?? $item['unit_cost'] ?? 0);
                            $unit = htmlspecialchars($item['unit'] ?? 'pcs');
                        ?>
                            <tr>
                                <td>
                                    <span style="font-weight:700;font-size:0.7rem;color:var(--primary);">
                                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="font-weight:600;"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-info" style="font-size:0.55rem;padding:2px 8px;">
                                        <?= htmlspecialchars($item['category'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= $unit ?></td>
                                <td style="font-weight:800;" class="<?= $quantity <= 0 ? 'text-danger' : ($quantity <= $reorder_level ? 'text-warning' : 'text-success') ?>">
                                    <?= number_format($quantity) ?>
                                </td>
                                <td>TSh <?= number_format($selling_price, 0) ?></td>
                                <td><?= getInventoryStatusBadge($quantity, $reorder_level) ?></td>
                                <td><?= getExpiryBadge($item['expiry_date'] ?? null) ?></td>
                                <td style="font-family:monospace;font-size:0.72rem;"><?= htmlspecialchars($item['batch_number'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align:center;padding:30px 20px;color:var(--text-secondary);font-family:var(--font-mono);">
                <i class="fas fa-boxes" style="font-size:2rem;display:block;margin-bottom:12px;color:var(--border-color);"></i>
                <p>No medicines found in inventory</p>
                <?php if ($selected_branch_id !== 'all'): ?>
                    <p style="font-size:0.75rem;">No inventory items for <?= htmlspecialchars($display_branch_name) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            Pharmacies V3 - <?= $total_pharmacies ?> branches
            <span style="margin:0 8px;">|</span>
            Inventory - <?= count($inventory_items) ?> medicines
            <span style="margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PAGE JAVASCRIPT - PEKEE -->
<!-- ================================================================ -->
<script>
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

<?php if (!empty($search) || $status_filter !== 'all'): ?>
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.querySelector('input[name="search"]');
    if (searchInput && searchInput.value) {
        searchInput.focus();
    }
});
<?php endif; ?>

console.log('%c🏥 Braick Dispensary - Pharmacies V3 (GROSS)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ V3: PRESCRIPTION = Medication_RAW (GROSS - bila discount, bila premium)', 'font-size:12px;color:#34D399;font-weight:bold;');
console.log('%c✅ V3: Prescription BILA round off (exact value, 2 decimal places)', 'font-size:12px;color:#FCD34D;font-weight:bold;');
console.log('%c✅ SAWA KWA 100% NA AUDIT V13, ADMIN V20, CASHIERS V13.1, VIEW_CASHIER V14', 'font-size:12px;color:#FCD34D;font-weight:bold;');
console.log('%c📊 Total Pharmacies: <?= $total_pharmacies ?>', 'font-size:12px;color:#0B5ED7;');
console.log('%c💊 Total Medicines: <?= number_format($total_medicines) ?>', 'font-size:12px;color:#059669;');
console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue, 0) ?>', 'font-size:12px;color:#0B5ED7;');
console.log('%c💊 Prescription (GROSS): TSh <?= number_format($total_prescription_revenue, 2, '.', ',') ?>', 'font-size:12px;color:#7C3AED;font-weight:bold;');
console.log('%c🛒 OTC: TSh <?= number_format($total_otc_revenue_db, 0) ?>', 'font-size:12px;color:#D97706;');
console.log('%c📦 Inventory Items: <?= count($inventory_items) ?>', 'font-size:12px;color:#7C3AED;');
</script>

</body>
</html>