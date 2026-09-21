<?php
// ================================================================
// FILE: frontend/pages/admin/patient_prescriptions.php
// ADMIN - VIEW ALL PRESCRIPTIONS FOR A SINGLE PATIENT (V3)
// ✅ V3: DESIGN MPYA - Sawa na prescriptions.php
// ✅ V3: JetBrains Mono font kwa namba/IDs
// ✅ V3: PRESCRIPTION = Medication_RAW - Pharmacy_Discount
// ✅ V3: SAWA KWA 100% NA DASHBOARD ZOTE
// ✅ Group by Visit
// ✅ Search functionality
// ✅ Quick date filters
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
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
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ✅ V3: ROUND TO NEAREST 50
function round_to_50($value) {
    return round($value / 50) * 50;
}

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';
$quick_filter = isset($_GET['quick']) ? $_GET['quick'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

if ($patient_id <= 0) {
    header('Location: prescriptions.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// QUICK DATE FILTERS
// ================================================================
$quick_date_from = '';
$quick_date_to = date('Y-m-d');

switch ($quick_filter) {
    case 'today':
        $quick_date_from = date('Y-m-d');
        $quick_date_to = date('Y-m-d');
        break;
    case '1w':
        $quick_date_from = date('Y-m-d', strtotime('-7 days'));
        break;
    case '1m':
        $quick_date_from = date('Y-m-d', strtotime('-1 month'));
        break;
    case '3m':
        $quick_date_from = date('Y-m-d', strtotime('-3 months'));
        break;
    case '6m':
        $quick_date_from = date('Y-m-d', strtotime('-6 months'));
        break;
    case '1y':
        $quick_date_from = date('Y-m-d', strtotime('-1 year'));
        break;
    case 'custom':
        $quick_date_from = $date_from;
        $quick_date_to = $date_to;
        break;
    case 'all':
    default:
        $quick_date_from = '';
        $quick_date_to = '';
        break;
}

// ================================================================
// FETCH PATIENT
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, b.name as branch_name
    FROM patients p
    LEFT JOIN branches b ON p.branch_id = b.id
    WHERE p.id = ?
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: prescriptions.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// ✅ V3: FETCH PRESCRIPTIONS WITH SAHIHI REVENUE
// ================================================================
$where_clause = " WHERE p.patient_id = ?";
$params = [$patient_id];

$where_clause .= " AND EXISTS (
    SELECT 1 FROM prescription_items pi_check 
    WHERE pi_check.prescription_id = p.id 
    AND pi_check.quantity > 0
)";

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $where_clause .= " AND p.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

if (!empty($quick_date_from)) {
    $where_clause .= " AND DATE(p.created_at) >= ?";
    $params[] = $quick_date_from;
}

if (!empty($quick_date_to)) {
    $where_clause .= " AND DATE(p.created_at) <= ?";
    $params[] = $quick_date_to;
}

$sql = "
    SELECT 
        p.id, p.prescription_number, p.patient_id, p.visit_id, p.doctor_id,
        p.diagnosis, p.instructions, p.status, p.branch_id, p.created_at, p.dispensed_at,
        doc.full_name as doctor_name,
        b.name as branch_name,
        v.visit_number,
        v.visit_date,
        v.created_at as visit_created_at,
        (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = p.id AND quantity > 0) as item_count,
        (SELECT COALESCE(SUM(total_price), 0) FROM prescription_items WHERE prescription_id = p.id AND quantity > 0) as total_amount
    FROM prescriptions p
    LEFT JOIN users doc ON p.doctor_id = doc.id
    LEFT JOIN branches b ON p.branch_id = b.id
    LEFT JOIN visits v ON p.visit_id = v.id
    $where_clause
    ORDER BY p.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$all_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($all_prescriptions as &$presc) {
    $stmt = $db->prepare("
        SELECT medication_name, dosage, frequency, quantity, unit_price, total_price, duration, route, instructions
        FROM prescription_items 
        WHERE prescription_id = ? AND quantity > 0
        ORDER BY id ASC
    ");
    $stmt->execute([$presc['id']]);
    $presc['medications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($presc);

// ================================================================
// GROUP BY VISIT
// ================================================================
$visits_data = [];

foreach ($all_prescriptions as $presc) {
    if (empty($presc['medications'])) continue;
    
    $visit_id = $presc['visit_id'] ?? 0;
    $visit_key = $visit_id > 0 ? 'v_' . $visit_id : 'no_visit_' . $presc['id'];
    
    if (!isset($visits_data[$visit_key])) {
        $visits_data[$visit_key] = [
            'visit_id' => $visit_id,
            'visit_number' => $presc['visit_number'] ?? 'N/A',
            'visit_date' => $presc['visit_date'] ?? $presc['visit_created_at'] ?? $presc['created_at'],
            'doctor_name' => $presc['doctor_name'] ?? 'N/A',
            'branch_name' => $presc['branch_name'] ?? 'N/A',
            'prescriptions' => [],
            'total_amount' => 0,
            'statuses' => [],
            'total_medicines' => 0
        ];
    }
    
    $visits_data[$visit_key]['prescriptions'][] = $presc;
    $visits_data[$visit_key]['total_amount'] += $presc['total_amount'] ?? 0;
    $visits_data[$visit_key]['statuses'][] = $presc['status'];
    $visits_data[$visit_key]['total_medicines'] += count($presc['medications']);
}

foreach ($visits_data as &$visit) {
    $statuses = $visit['statuses'];
    if (in_array('pending', $statuses)) $visit['overall_status'] = 'pending';
    elseif (in_array('confirmed', $statuses)) $visit['overall_status'] = 'confirmed';
    elseif (in_array('dispensed', $statuses)) $visit['overall_status'] = 'dispensed';
    elseif (in_array('cancelled', $statuses)) $visit['overall_status'] = 'cancelled';
    else $visit['overall_status'] = 'pending';
}
unset($visit);

$visits_array = array_values($visits_data);
usort($visits_array, function($a, $b) {
    return strtotime($b['visit_date']) - strtotime($a['visit_date']);
});

// ================================================================
// STATS
// ================================================================
$total_prescriptions = 0;
$total_dispensed = 0;
$total_pending = 0;
$total_confirmed = 0;
$total_cancelled = 0;

foreach ($all_prescriptions as $p) {
    if (empty($p['medications'])) continue;
    $total_prescriptions++;
    if ($p['status'] === 'dispensed') $total_dispensed++;
    elseif ($p['status'] === 'pending') $total_pending++;
    elseif ($p['status'] === 'confirmed') $total_confirmed++;
    elseif ($p['status'] === 'cancelled') $total_cancelled++;
}

// ✅ V3: Medication RAW + Pharmacy Discount
$medication_raw = 0;
$pharmacy_discount = 0;
$prescription_revenue = 0;

try {
    $raw_params = [$patient_id];
    $raw_branch = "";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $raw_branch = " AND b.branch_id = ?";
        $raw_params[] = (int)$selected_branch_id;
    }
    if (!empty($quick_date_from)) {
        $raw_branch .= " AND DATE(b.created_at) >= ?";
        $raw_params[] = $quick_date_from;
    }
    if (!empty($quick_date_to)) {
        $raw_branch .= " AND DATE(b.created_at) <= ?";
        $raw_params[] = $quick_date_to;
    }
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(bi.total_price), 0) as medication_raw
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE b.patient_id = ?
        AND bi.item_type = 'medication'
        AND bi.status != 'cancelled'
        AND b.status IN ('paid', 'partial')
        $raw_branch
    ");
    $stmt->execute($raw_params);
    $medication_raw = (float)($stmt->fetch(PDO::FETCH_ASSOC)['medication_raw'] ?? 0);
    
    $disc_params = [$patient_id];
    $disc_branch = "";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $disc_branch = " AND b.branch_id = ?";
        $disc_params[] = (int)$selected_branch_id;
    }
    if (!empty($quick_date_from)) {
        $disc_branch .= " AND DATE(b.created_at) >= ?";
        $disc_params[] = $quick_date_from;
    }
    if (!empty($quick_date_to)) {
        $disc_branch .= " AND DATE(b.created_at) <= ?";
        $disc_params[] = $quick_date_to;
    }
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(b.pharmacy_discount), 0) as pharmacy_discount
        FROM bills b
        WHERE b.patient_id = ?
        AND b.status IN ('paid', 'partial')
        AND b.pharmacy_discount > 0
        $disc_branch
    ");
    $stmt->execute($disc_params);
    $pharmacy_discount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['pharmacy_discount'] ?? 0);
    
    $prescription_revenue = round_to_50($medication_raw - $pharmacy_discount);
} catch (Exception $e) {}

$total_medicines_all = 0;
foreach ($all_prescriptions as $p) {
    $total_medicines_all += count($p['medications'] ?? []);
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    return (new DateTime($dob))->diff(new DateTime('today'))->y;
}

function buildFilterUrl($params_to_update = []) {
    $current = $_GET;
    foreach ($params_to_update as $key => $value) {
        if ($value === null || $value === '') unset($current[$key]);
        else $current[$key] = $value;
    }
    return '?' . http_build_query($current);
}

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --font-mono: 'JetBrains Mono', 'Courier New', 'Consolas', monospace;
    --font-main: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-darker: #083C8A;
    --primary-light: #6EA8FE;
    --primary-bg: #E8F0FE;
    --success: #059669;
    --success-bg: #D1FAE5;
    --danger: #DC2626;
    --danger-bg: #FEE2E2;
    --warning: #D97706;
    --warning-bg: #FEF3C7;
    --purple: #7C3AED;
    --purple-bg: #EDE9FE;
    --gray-50: #F8FAFC;
    --gray-100: #F1F5F9;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --radius: 10px;
    --radius-lg: 14px;
    --radius-xl: 18px;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
    --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
}

body { font-family: var(--font-main) !important; }

.stat-number, .stat-amount, .stat-value, .stat-pill,
.prescription-tag, .patient-avatar, .patient-meta span,
.patient-actions .patient-actions-info strong,
.amount-cell, .data-table td:first-child,
.visit-number-display, .visit-date-display,
.med-qty, .quick-filter-btn, .filter-btn,
.med-summary-stat-value, .med-summary-name,
input[type="date"], input[type="text"], input[type="number"],
textarea, select, .form-control,
#footerTimestamp, .mono {
    font-family: var(--font-mono) !important;
    font-variant-numeric: tabular-nums;
}

.data-table thead th {
    font-family: var(--font-mono) !important;
    letter-spacing: 0.02em;
}

.page-title, h1, h2, h3, h4, h5, h6 {
    font-family: var(--font-main) !important;
    letter-spacing: -0.01em;
}

.btn-patient-view, .btn-patient-delete, .btn-outline-light,
.btn-apply-filters, .btn-action-sm {
    font-family: var(--font-main) !important;
}

/* PAGE HEADER */
.page-header-custom {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8, #083C8A);
    border-radius: var(--radius-xl);
    padding: 24px 32px;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 8px 32px rgba(11, 94, 215, 0.3);
    position: relative;
    overflow: hidden;
}

.page-header-custom::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.page-header-custom .page-title {
    color: white;
    font-size: 1.5rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin: 0;
}

.page-header-custom .page-title i { font-size: 1.7rem; opacity: 0.95; }

.page-header-custom .page-subtitle {
    color: rgba(255,255,255,0.9);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin-top: 6px;
}

.page-header-custom .role-badge-display {
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
}

.page-header-custom .branch-tag {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.68rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid rgba(255,255,255,0.15);
    font-family: var(--font-mono);
}

.page-header-custom .btn-outline-light {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1.5px solid rgba(255,255,255,0.25);
    padding: 8px 18px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.78rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    position: relative;
    z-index: 1;
    transition: all 0.3s;
    font-family: var(--font-mono);
    cursor: pointer;
}

.page-header-custom .btn-outline-light:hover {
    background: rgba(255,255,255,0.28);
    transform: translateY(-2px);
    color: white;
}

/* PATIENT BANNER */
.patient-banner {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    padding: 20px 26px;
    margin-bottom: 20px;
    border: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    box-shadow: var(--shadow-sm);
}

.patient-banner .patient-avatar-lg {
    width: 72px; height: 72px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1.8rem;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    border: 3px solid white;
    font-family: var(--font-mono);
}

.patient-banner .patient-info h2 {
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0 0 6px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.patient-banner .patient-info .badge-id {
    background: var(--primary-bg);
    color: var(--primary);
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 700;
    font-family: var(--font-mono);
}

.patient-banner .patient-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 0.75rem;
    color: var(--text-secondary);
    font-family: var(--font-mono);
}

.patient-banner .patient-meta span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.patient-banner .patient-meta i { color: var(--primary); }

/* STATS */
.stats-grid-5 {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.stat-card-custom {
    border-radius: var(--radius-lg);
    padding: 16px 18px;
    display: flex;
    flex-direction: column;
    transition: all 0.4s ease;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    color: white;
    position: relative;
    overflow: hidden;
    min-height: 105px;
    text-decoration: none;
}

.stat-card-custom::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 140px; height: 140px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
    pointer-events: none;
    transition: all 0.5s ease;
}

.stat-card-custom:hover {
    transform: translateY(-6px) scale(1.02);
    box-shadow: 0 12px 36px rgba(0,0,0,0.2);
}

.stat-card-custom:hover::before { transform: scale(1.3); }

.stat-card-custom .stat-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    background: rgba(255,255,255,0.18);
    color: white;
    border: 1px solid rgba(255,255,255,0.12);
    margin-bottom: 6px;
    transition: all 0.3s;
}

.stat-card-custom:hover .stat-icon {
    transform: scale(1.1) rotate(-5deg);
    background: rgba(255,255,255,0.3);
}

.stat-card-custom .stat-label {
    font-size: 0.55rem;
    color: rgba(255,255,255,0.85);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin: 0 0 2px 0;
    font-family: var(--font-mono);
}

.stat-card-custom .stat-number {
    font-size: 1.7rem;
    font-weight: 800;
    color: white;
    margin: 0;
    line-height: 1.1;
    font-family: var(--font-mono);
}

.stat-card-custom .stat-amount {
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(255,255,255,0.9);
    margin-top: 2px;
    font-family: var(--font-mono);
}

.card-blue-1 { background: linear-gradient(135deg, #3B82F6, #0B5ED7, #0A4CA8); }
.card-blue-2 { background: linear-gradient(135deg, #0EA5E9, #0284C7, #075985); }
.card-blue-3 { background: linear-gradient(135deg, #0B5ED7, #083C8A, #062E6B); }
.card-blue-4 { background: linear-gradient(135deg, #7C3AED, #6D28D9, #5B21B6); }
.card-blue-5 { background: linear-gradient(135deg, #DC2626, #B91C1C, #991B1B); }

/* V3 FORMULA BREAKDOWN */
.formula-breakdown {
    background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
    border: 2px solid #BFDBFE;
    border-radius: var(--radius-lg);
    padding: 16px 22px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
}

[data-theme="dark"] .formula-breakdown {
    background: linear-gradient(135deg, #1E3A5F, #16294A);
    border-color: #3B82F6;
}

.formula-breakdown-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 800;
    color: #0B5ED7;
    font-size: 0.9rem;
    font-family: var(--font-mono);
}

.formula-breakdown-title i {
    width: 36px; height: 36px;
    background: #0B5ED7;
    color: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.formula-stats { display: flex; gap: 12px; flex-wrap: wrap; }

.formula-stat {
    text-align: center;
    padding: 10px 18px;
    background: white;
    border-radius: 12px;
    border: 2px solid #BFDBFE;
    min-width: 140px;
}

[data-theme="dark"] .formula-stat { background: #1E293B; }

.formula-stat.raw { border-color: #0B5ED7; }
.formula-stat.discount { border-color: #FCD34D; background: #FFFBEB; }
.formula-stat.total { border-color: #7C3AED; background: #F5F3FF; }

.formula-stat-label {
    font-size: 0.55rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748B;
    letter-spacing: 0.05em;
    font-family: var(--font-mono);
    display: block;
    margin-bottom: 4px;
}

.formula-stat-value {
    font-size: 1.05rem;
    font-weight: 800;
    font-family: var(--font-mono);
}

.formula-stat.raw .formula-stat-value { color: #0B5ED7; }
.formula-stat.discount .formula-stat-value { color: #D97706; }
.formula-stat.total .formula-stat-value { color: #7C3AED; }

/* FILTERS */
.quick-filters {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    padding: 12px 18px;
    border: 2px solid var(--border-color);
    margin-bottom: 16px;
    box-shadow: var(--shadow-sm);
}

.quick-filters .filter-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-right: 6px;
    font-family: var(--font-mono);
}

.quick-filter-btn {
    padding: 6px 14px;
    border-radius: 18px;
    font-size: 0.7rem;
    font-weight: 700;
    border: 2px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-secondary);
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
    font-family: var(--font-mono);
    transition: all 0.3s;
}

.quick-filter-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-bg);
    transform: translateY(-1px);
}

.quick-filter-btn.active {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border-color: var(--primary);
    color: white;
}

.quick-filter-btn.today.active { background: linear-gradient(135deg, #059669, #047857); border-color: #059669; }
.quick-filter-btn.custom.active { background: linear-gradient(135deg, #D97706, #B45309); border-color: #D97706; }

/* CUSTOM DATE */
.custom-date-row {
    display: none;
    padding: 14px 18px;
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    margin-bottom: 16px;
    gap: 10px;
    flex-wrap: wrap;
    align-items: end;
    border: 2px solid var(--primary);
}

.custom-date-row.show { display: flex; }

.custom-date-row .form-group { flex: 1; min-width: 140px; }

.custom-date-row .form-group label {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--text-secondary);
    display: block;
    margin-bottom: 6px;
    font-family: var(--font-mono);
}

.custom-date-row .form-control {
    width: 100%;
    padding: 8px 12px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    font-size: 0.82rem;
    background: var(--bg-body);
    color: var(--text-primary);
    outline: none;
    font-family: var(--font-mono);
}

.btn-apply-filters {
    padding: 8px 20px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.8rem;
    border: none;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    cursor: pointer;
    height: 40px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: var(--font-mono);
}

/* VISIT CARD */
.visit-card {
    background: var(--bg-card);
    border-radius: var(--radius-xl);
    border: 2px solid var(--border-color);
    margin-bottom: 20px;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: all 0.35s ease;
}

.visit-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
}

.visit-card-header {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    padding: 16px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    position: relative;
    overflow: hidden;
}

.visit-card-header::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.visit-card-header .visit-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}

.visit-card-header .visit-icon-badge {
    width: 40px; height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    box-shadow: 0 3px 10px rgba(245, 158, 11, 0.3);
    flex-shrink: 0;
}

.visit-card-header .visit-number-display {
    font-weight: 800;
    font-size: 0.9rem;
    color: white;
    background: rgba(255,255,255,0.15);
    padding: 5px 14px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.2);
    font-family: var(--font-mono);
}

.visit-card-header .visit-date-display,
.visit-card-header .visit-doctor-display {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.75rem;
    color: rgba(255,255,255,0.9);
    font-weight: 600;
    font-family: var(--font-mono);
}

.visit-card-header .visit-doctor-display {
    background: rgba(255,255,255,0.15);
    padding: 4px 12px;
    border-radius: 20px;
}

.visit-card-header .visit-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}

.visit-card-header .visit-stat-pill {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid rgba(255,255,255,0.2);
    font-family: var(--font-mono);
}

.visit-card-body { padding: 0; }

/* TABLE */
.table-wrapper {
    overflow-x: auto;
    border-radius: 0;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
    font-family: var(--font-mono);
}

.data-table thead th {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    padding: 12px 14px;
    text-align: left;
    font-weight: 700;
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    white-space: nowrap;
    font-family: var(--font-mono);
}

.data-table tbody td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
    font-family: var(--font-mono);
}

.data-table tbody tr:nth-child(even) td { background: var(--primary-bg); }
[data-theme="dark"] .data-table tbody tr:nth-child(even) td { background: #1E3A5F; }
.data-table tbody tr:hover td { background: #DBEAFE; }
[data-theme="dark"] .data-table tbody tr:hover td { background: #1E40AF; }

/* MEDICATIONS */
.medication-list {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 200px;
}

.medication-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    background: var(--bg-body);
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 0.72rem;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.medication-item:hover {
    border-color: var(--primary);
    background: var(--primary-bg);
    transform: translateX(3px);
}

.medication-item .med-name {
    font-weight: 600;
    flex: 1;
    color: var(--text-primary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.medication-item .med-qty {
    background: var(--primary);
    color: white;
    padding: 2px 9px;
    border-radius: 10px;
    font-size: 0.62rem;
    font-weight: 700;
    white-space: nowrap;
    font-family: var(--font-mono);
}

/* STATUS BADGES */
.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.62rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
    font-family: var(--font-mono);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

.status-badge.warning { background: linear-gradient(135deg, #FEF3C7, #FDE68A); color: #D97706; }
.status-badge.success { background: linear-gradient(135deg, #D1FAE5, #A7F3D0); color: #059669; }
.status-badge.danger { background: linear-gradient(135deg, #FEE2E2, #FECACA); color: #DC2626; }
.status-badge.info { background: linear-gradient(135deg, #E8F0FE, #DBEAFE); color: #0B5ED7; }

.prescription-tag {
    display: inline-block;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 6px;
    background: var(--primary-bg);
    color: var(--primary);
    white-space: nowrap;
    font-family: var(--font-mono);
    border: 1px solid var(--primary-light);
}

.amount-cell {
    font-weight: 800;
    color: var(--primary);
    font-size: 0.85rem;
    font-family: var(--font-mono);
}

/* EMPTY STATE */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
    background: var(--bg-card);
    border-radius: var(--radius-xl);
    border: 2px dashed var(--border-color);
}

.empty-state i {
    font-size: 3.5rem;
    color: var(--primary);
    display: block;
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--text-primary);
    margin-bottom: 8px;
    font-family: var(--font-main);
}

/* FOOTER */
.footer {
    padding: 16px 0;
    border-top: 2px solid var(--border-color);
    margin-top: 24px;
    text-align: center;
    font-size: 0.72rem;
    color: var(--text-secondary);
    font-family: var(--font-mono);
}

.footer .footer-brand { color: var(--primary); font-weight: 700; }

/* ANIMATIONS */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }

/* RESPONSIVE */
@media (max-width: 1200px) { .stats-grid-5 { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 1024px) { .stats-grid-5 { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) {
    .page-header-custom { padding: 16px 18px; }
    .page-header-custom .page-title { font-size: 1.15rem; }
    .stats-grid-5 { grid-template-columns: 1fr 1fr; gap: 8px; }
    .stat-card-custom { min-height: 90px; padding: 12px; }
    .stat-card-custom .stat-number { font-size: 1.3rem; }
    .patient-banner { flex-direction: column; align-items: flex-start; }
    .visit-card-header { flex-direction: column; align-items: flex-start; }
}
@media (max-width: 480px) {
    .stats-grid-5 { grid-template-columns: 1fr; }
    .formula-breakdown { flex-direction: column; align-items: stretch; }
    .formula-stat { min-width: 100%; }
}
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription"></i>
                Patient Prescriptions
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
                <span class="branch-tag" style="background:rgba(252,211,77,0.25);color:#FCD34D;border-color:rgba(252,211,77,0.4);">
                    <i class="fas fa-star"></i> V3 SAHIHI
                </span>
            </h1>
            <p class="page-subtitle">
                <span class="branch-tag"><i class="fas fa-user"></i> <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></span>
                <span class="branch-tag"><i class="fas fa-prescription"></i> <?= $total_prescriptions ?> Prescriptions</span>
                <span class="branch-tag" style="background:rgba(52,211,153,0.25);color:#A7F3D0;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($prescription_revenue, 0) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="prescriptions.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- PATIENT BANNER -->
    <div class="patient-banner animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="patient-avatar-lg" style="background: <?= '#' . substr(md5($patient['full_name'] ?? 'X'), 0, 6) ?>;">
            <?= strtoupper(substr($patient['full_name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="patient-info" style="flex:1;">
            <h2>
                <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>
                <span class="badge-id"><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span>
            </h2>
            <div class="patient-meta">
                <?php if (!empty($patient['phone'])): ?>
                    <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone']) ?></span>
                <?php endif; ?>
                <?php if (!empty($patient['gender'])): ?>
                    <span><i class="fas fa-<?= $patient['gender'] === 'Female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($patient['gender']) ?> • <?= calculateAge($patient['date_of_birth']) ?> yrs</span>
                <?php endif; ?>
                <?php if (!empty($patient['blood_group'])): ?>
                    <span><i class="fas fa-tint"></i> <?= htmlspecialchars($patient['blood_group']) ?></span>
                <?php endif; ?>
                <?php if (!empty($patient['branch_name'])): ?>
                    <span><i class="fas fa-store-alt"></i> <?= htmlspecialchars($patient['branch_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- STATS CARDS -->
    <div class="stats-grid-5 animate-fade-in-up" style="animation-delay:0.1s;">
        <a href="<?= buildFilterUrl(['quick' => 'all']) ?>" class="stat-card-custom card-blue-1">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <p class="stat-label">Total Rx</p>
            <p class="stat-number"><?= $total_prescriptions ?></p>
            <p class="stat-amount"><?= $total_medicines_all ?> medicines</p>
        </a>
        <a href="<?= buildFilterUrl(['status' => 'pending']) ?>" class="stat-card-custom card-blue-2">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <p class="stat-label">Pending</p>
            <p class="stat-number"><?= $total_pending ?></p>
            <p class="stat-amount">Awaiting</p>
        </a>
        <a href="<?= buildFilterUrl(['status' => 'confirmed']) ?>" class="stat-card-custom card-blue-3">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <p class="stat-label">Confirmed</p>
            <p class="stat-number"><?= $total_confirmed ?></p>
            <p class="stat-amount">Ready</p>
        </a>
        <a href="<?= buildFilterUrl(['status' => 'dispensed']) ?>" class="stat-card-custom card-blue-4">
            <div class="stat-icon"><i class="fas fa-pills"></i></div>
            <p class="stat-label">Dispensed</p>
            <p class="stat-number"><?= $total_dispensed ?></p>
            <p class="stat-amount">Completed</p>
        </a>
        <a href="<?= buildFilterUrl(['status' => 'cancelled']) ?>" class="stat-card-custom card-blue-5">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <p class="stat-label">Cancelled</p>
            <p class="stat-number"><?= $total_cancelled ?></p>
            <p class="stat-amount">Rejected</p>
        </a>
    </div>

    <!-- V3 FORMULA BREAKDOWN -->
    <?php if ($medication_raw > 0 || $pharmacy_discount > 0): ?>
    <div class="formula-breakdown animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="formula-breakdown-title">
            <i class="fas fa-calculator"></i>
            Prescription Revenue Breakdown (V3 SAHIHI)
        </div>
        <div class="formula-stats">
            <div class="formula-stat raw">
                <span class="formula-stat-label">💊 Medication RAW</span>
                <span class="formula-stat-value">TSh <?= number_format($medication_raw, 0) ?></span>
            </div>
            <div class="formula-stat discount">
                <span class="formula-stat-label">🏷️ Pharmacy Discount</span>
                <span class="formula-stat-value">- TSh <?= number_format($pharmacy_discount, 0) ?></span>
            </div>
            <div class="formula-stat total">
                <span class="formula-stat-label">💊 Prescription Revenue</span>
                <span class="formula-stat-value">TSh <?= number_format($prescription_revenue, 0) ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- QUICK DATE FILTERS -->
    <div class="quick-filters animate-fade-in-up" style="animation-delay:0.2s;">
        <span class="filter-label"><i class="fas fa-bolt"></i> Quick:</span>
        <a href="<?= buildFilterUrl(['quick' => 'all', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn <?= $quick_filter === 'all' ? 'active' : '' ?>">
            <i class="fas fa-infinity"></i> All
        </a>
        <a href="<?= buildFilterUrl(['quick' => 'today', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn today <?= $quick_filter === 'today' ? 'active' : '' ?>">
            <i class="fas fa-calendar-day"></i> Today
        </a>
        <a href="<?= buildFilterUrl(['quick' => '1w', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn <?= $quick_filter === '1w' ? 'active' : '' ?>">
            <i class="fas fa-calendar-week"></i> 1W
        </a>
        <a href="<?= buildFilterUrl(['quick' => '1m', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn <?= $quick_filter === '1m' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> 1M
        </a>
        <a href="<?= buildFilterUrl(['quick' => '3m', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn <?= $quick_filter === '3m' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> 3M
        </a>
        <a href="<?= buildFilterUrl(['quick' => '6m', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn <?= $quick_filter === '6m' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> 6M
        </a>
        <a href="<?= buildFilterUrl(['quick' => '1y', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn <?= $quick_filter === '1y' ? 'active' : '' ?>">
            <i class="fas fa-calendar"></i> 1Y
        </a>
        <a href="javascript:void(0)" onclick="toggleCustomDate()" 
           class="quick-filter-btn custom <?= $quick_filter === 'custom' ? 'active' : '' ?>">
            <i class="fas fa-calendar-check"></i> Custom
        </a>
        <?php if ($quick_filter !== 'all'): ?>
            <a href="<?= buildFilterUrl(['quick' => 'all', 'date_from' => null, 'date_to' => null]) ?>" 
               class="quick-filter-btn" style="border-color:var(--danger);color:var(--danger);margin-left:auto;">
                <i class="fas fa-times"></i> Clear
            </a>
        <?php endif; ?>
    </div>

    <!-- CUSTOM DATE FILTER -->
    <div class="custom-date-row <?= $quick_filter === 'custom' ? 'show' : '' ?>" id="customDateRow">
        <form method="GET" style="display:contents;">
            <input type="hidden" name="quick" value="custom">
            <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            <div class="form-group">
                <label><i class="fas fa-calendar-alt"></i> Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar-alt"></i> Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>" required>
            </div>
            <button type="submit" class="btn-apply-filters">
                <i class="fas fa-check"></i> Apply
            </button>
        </form>
    </div>

    <!-- VISITS + PRESCRIPTIONS -->
    <?php if (count($visits_array) > 0): ?>
        <?php foreach ($visits_array as $v_idx => $visit): 
            $overall_status = $visit['overall_status'];
            $status_class = 'warning';
            $status_icon = '⏳';
            if ($overall_status === 'confirmed') { $status_class = 'info'; $status_icon = '✅'; }
            elseif ($overall_status === 'dispensed') { $status_class = 'success'; $status_icon = '💊'; }
            elseif ($overall_status === 'cancelled') { $status_class = 'danger'; $status_icon = '❌'; }
        ?>
            <div class="visit-card animate-fade-in-up" style="animation-delay:<?= 0.25 + ($v_idx * 0.05) ?>s;">
                
                <!-- VISIT HEADER -->
                <div class="visit-card-header">
                    <div class="visit-left">
                        <div class="visit-icon-badge">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <span class="visit-number-display">
                            <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                        </span>
                        <span class="visit-date-display">
                            <i class="fas fa-calendar-day"></i>
                            <?= !empty($visit['visit_date']) ? date('d M Y', strtotime($visit['visit_date'])) : 'N/A' ?>
                        </span>
                        <span class="visit-doctor-display">
                            <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?>
                        </span>
                    </div>
                    <div class="visit-right">
                        <span class="visit-stat-pill">
                            <i class="fas fa-prescription"></i> <?= count($visit['prescriptions']) ?> Rx
                        </span>
                        <span class="visit-stat-pill">
                            <i class="fas fa-pills"></i> <?= $visit['total_medicines'] ?> Meds
                        </span>
                        <span class="visit-stat-pill" style="background:rgba(252,211,77,0.25);border-color:rgba(252,211,77,0.4);color:#FCD34D;">
                            <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($visit['total_amount'], 0) ?>
                        </span>
                        <span class="status-badge <?= $status_class ?>" style="box-shadow:none;">
                            <?= $status_icon ?> <?= ucfirst($overall_status) ?>
                        </span>
                    </div>
                </div>
                
                <!-- VISIT BODY - PRESCRIPTIONS TABLE -->
                <div class="visit-card-body">
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width:50px;">#</th>
                                    <th><i class="fas fa-hashtag"></i> Prescription #</th>
                                    <th><i class="fas fa-pills"></i> Medications</th>
                                    <th><i class="fas fa-info-circle"></i> Status</th>
                                    <th style="text-align:right;"><i class="fas fa-money-bill-wave"></i> Amount</th>
                                    <th><i class="fas fa-calendar"></i> Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($visit['prescriptions'] as $presc): 
                                    $status = $presc['status'] ?? 'pending';
                                    $status_class_p = 'warning';
                                    $status_icon_p = '⏳';
                                    if ($status === 'confirmed') { $status_class_p = 'info'; $status_icon_p = '✅'; }
                                    elseif ($status === 'dispensed') { $status_class_p = 'success'; $status_icon_p = '💊'; }
                                    elseif ($status === 'cancelled') { $status_class_p = 'danger'; $status_icon_p = '❌'; }
                                ?>
                                    <tr>
                                        <td style="text-align:center;font-weight:800;color:var(--text-secondary);"><?= $i++ ?></td>
                                        <td>
                                            <span class="prescription-tag"><?= htmlspecialchars($presc['prescription_number'] ?? 'N/A') ?></span>
                                        </td>
                                        <td>
                                            <?php if (!empty($presc['medications'])): ?>
                                                <div class="medication-list">
                                                    <?php foreach ($presc['medications'] as $med): ?>
                                                        <div class="medication-item">
                                                            <div class="med-name">
                                                                <i class="fas fa-pills" style="color:var(--primary);margin-right:5px;"></i>
                                                                <?= htmlspecialchars($med['medication_name'] ?? 'N/A') ?>
                                                                <?php if (!empty($med['dosage'])): ?>
                                                                    <span style="font-size:0.62rem;color:var(--text-secondary);margin-left:4px;"><?= htmlspecialchars($med['dosage']) ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <span class="med-qty">x<?= (int)($med['quantity'] ?? 0) ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color:var(--text-secondary);font-size:0.7rem;">No meds</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $status_class_p ?>">
                                                <?= $status_icon_p ?> <?= ucfirst($status) ?>
                                            </span>
                                        </td>
                                        <td class="amount-cell" style="text-align:right;">
                                            TSh <?= number_format($presc['total_amount'] ?? 0, 0) ?>
                                        </td>
                                        <td style="font-size:0.72rem;">
                                            <?= date('M d, Y', strtotime($presc['created_at'] ?? 'now')) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div style="text-align:center;padding:14px;font-size:0.78rem;color:var(--text-secondary);font-family:var(--font-mono);">
            <i class="fas fa-chart-bar"></i> Showing <strong style="color:var(--primary);"><?= count($visits_array) ?></strong> visit(s) · 
            <strong style="color:var(--primary);"><?= $total_prescriptions ?></strong> prescription(s) · 
            <strong style="color:var(--primary);"><?= $total_medicines_all ?></strong> medicine(s)
        </div>
        
    <?php else: ?>
        <div class="empty-state animate-fade-in-up">
            <i class="fas fa-prescription"></i>
            <h3>No Prescriptions Found</h3>
            <p style="color:var(--text-secondary);margin-top:8px;">
                <?= $quick_filter !== 'all' ? 'Try adjusting your date filter' : 'This patient has no prescriptions yet' ?>
            </p>
            <?php if ($quick_filter !== 'all'): ?>
                <a href="<?= buildFilterUrl(['quick' => 'all', 'date_from' => null, 'date_to' => null]) ?>" 
                   style="margin-top:16px;display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:var(--primary);color:white;border-radius:10px;text-decoration:none;font-weight:700;font-family:var(--font-mono);">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System V3
            <span style="margin:0 8px;">|</span>
            Patient Prescriptions
            <span style="margin:0 8px;">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
        </p>
    </footer>

</main>

<script>
function toggleCustomDate() {
    var row = document.getElementById('customDateRow');
    if (!row) return;
    row.classList.toggle('show');
}

setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var ftEl = document.getElementById('footerTimestamp');
    if (ftEl) ftEl.textContent = 'Last updated: ' + timeStr;
}, 1000);

console.log('%c💊 Braick - Patient Prescriptions V3 SAHIHI', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ V3: DESIGN MPYA - Sawa na prescriptions.php', 'font-size:13px;color:#059669;font-weight:bold;');
console.log('%c✅ V3: JetBrains Mono font kwa namba/IDs', 'font-size:13px;color:#059669;');
console.log('%c✅ V3: PRESCRIPTION = Medication_RAW - Pharmacy_Discount', 'font-size:13px;color:#7C3AED;font-weight:bold;');
console.log('%c💊 Medication RAW: TSh <?= number_format($medication_raw, 0) ?>', 'font-size:12px;color:#0B5ED7;');
console.log('%c🏷️ Pharmacy Discount: TSh <?= number_format($pharmacy_discount, 0) ?>', 'font-size:12px;color:#D97706;');
console.log('%c💰 Prescription Revenue: TSh <?= number_format($prescription_revenue, 0) ?>', 'font-size:12px;color:#7C3AED;font-weight:bold;');
</script>

</body>
</html>