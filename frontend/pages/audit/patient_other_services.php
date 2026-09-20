<?php
// ================================================================
// FILE: frontend/pages/audit/patient_other_services.php
// AUDIT - PATIENT OTHER SERVICES (V1 - VIEW ONLY)
// ✅ Types: consultations | procedures | equipment
// ✅ Group by Patient → Visit
// ✅ NO Edit / Delete / Add buttons
// ✅ BLUE THEME + Table nav < >
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// AUDIT ROLE ONLY
if ($_SESSION['role'] !== 'audit') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin': header('Location: /dispensary_system/frontend/pages/admin/dashboard.php'); break;
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Audit User';
$user_role = $_SESSION['role'] ?? 'audit';
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$patient_id = (int)($_GET['patient_id'] ?? 0);
$type = $_GET['type'] ?? 'consultations';
$selected_branch_id = $_GET['branch'] ?? 'all';

// Validate type
if (!in_array($type, ['consultations', 'procedures', 'equipment'])) {
    $type = 'consultations';
}

if ($patient_id <= 0) {
    header('Location: other_services.php?branch=' . urlencode($selected_branch_id));
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// CURRENCY
$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['setting_value'])) $currency = $row['setting_value'];
} catch (Exception $e) {}

// HELPERS
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    try {
        return (new DateTime($dob))->diff(new DateTime('today'))->y . ' yrs';
    } catch (Exception $e) { return 'N/A'; }
}

function getStatusBadge($status) {
    $map = [
        'pending'    => ['class' => 'warning', 'icon' => '⏳', 'label' => 'Pending'],
        'confirmed'  => ['class' => 'info',    'icon' => '🔵', 'label' => 'Confirmed'],
        'paid'       => ['class' => 'success', 'icon' => '✅', 'label' => 'Paid'],
        'dispensed'  => ['class' => 'cyan',    'icon' => '📦', 'label' => 'Dispensed'],
        'completed'  => ['class' => 'success', 'icon' => '✅', 'label' => 'Completed'],
        'cancelled'  => ['class' => 'danger',  'icon' => '❌', 'label' => 'Cancelled'],
        'partial'    => ['class' => 'purple',  'icon' => '⚡', 'label' => 'Partial'],
    ];
    return $map[$status] ?? ['class' => 'warning', 'icon' => '❓', 'label' => ucfirst($status)];
}

function formatTsh($amount) {
    return 'TSh ' . number_format((float)$amount, 0);
}

// ================================================================
// GET PATIENT
// ================================================================
$patient = null;
try {
    $stmt = $db->prepare("
        SELECT p.*, b.name as branch_name
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE p.id = ?
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if (!$patient) {
    header('Location: other_services.php?branch=' . urlencode($selected_branch_id));
    exit;
}

// ================================================================
// FETCH DATA BASED ON TYPE
// ================================================================
$visits_data = [];
$all_items = [];

if ($type === 'consultations') {
    // CONSULTATIONS — kutoka `visits` table
    $sql = "
        SELECT v.*, 
               doc.full_name as doctor_name, 
               rec.full_name as receptionist_name, 
               b.name as branch_name
        FROM visits v
        LEFT JOIN users doc ON v.doctor_id = doc.id
        LEFT JOIN users rec ON v.receptionist_id = rec.id
        LEFT JOIN branches b ON v.branch_id = b.id
        WHERE v.patient_id = ?
        ORDER BY v.visit_date DESC, v.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$patient_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rows as $row) {
        $vid = $row['id'];
        $visits_data[$vid] = [
            'visit_id' => $vid,
            'visit_number' => $row['visit_number'] ?? 'N/A',
            'visit_date' => $row['visit_date'] ?? $row['created_at'],
            'doctor_name' => $row['doctor_name'] ?? 'N/A',
            'receptionist_name' => $row['receptionist_name'] ?? 'N/A',
            'branch_name' => $row['branch_name'] ?? 'N/A',
            'visit_type' => $row['visit_type'] ?? 'N/A',
            'consultation_fee' => $row['consultation_fee'] ?? 0,
            'diagnosis' => $row['diagnosis'] ?? '',
            'symptoms' => $row['symptoms'] ?? '',
            'status' => $row['status'] ?? 'N/A',
            'payment_status' => $row['payment_status'] ?? 'N/A',
            'visit_total' => $row['visit_total'] ?? 0,
            'items' => [$row] // Single row = single consultation
        ];
        $all_items[] = $row;
    }
    
} elseif ($type === 'procedures' || $type === 'equipment') {
    // PROCEDURES / EQUIPMENTS — kutoka `procedures` table
    $sql = "
        SELECT p.*, 
               doc.full_name as doctor_name, 
               b.name as branch_name,
               v.visit_number, 
               v.visit_date, 
               v.status as visit_status,
               v.diagnosis
        FROM procedures p
        LEFT JOIN users doc ON p.doctor_id = doc.id
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN visits v ON p.visit_id = v.id
        WHERE p.patient_id = ?
    ";
    
    if ($type === 'equipment') {
        $sql .= " AND (p.category LIKE '%Equipment%' OR p.category LIKE '%equipment%')";
    } else {
        $sql .= " AND (p.category NOT LIKE '%Equipment%' AND p.category NOT LIKE '%equipment%')";
    }
    
    $sql .= " ORDER BY v.visit_date DESC, p.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$patient_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rows as $row) {
        $vid = $row['visit_id'] ?? 0;
        if (!isset($visits_data[$vid])) {
            $visits_data[$vid] = [
                'visit_id' => $vid,
                'visit_number' => $row['visit_number'] ?? 'N/A',
                'visit_date' => $row['visit_date'] ?? $row['created_at'],
                'doctor_name' => $row['doctor_name'] ?? 'N/A',
                'branch_name' => $row['branch_name'] ?? 'N/A',
                'visit_status' => $row['visit_status'] ?? 'N/A',
                'diagnosis' => $row['diagnosis'] ?? '',
                'items' => [],
                'total_amount' => 0
            ];
        }
        $visits_data[$vid]['items'][] = $row;
        $visits_data[$vid]['total_amount'] += $row['procedure_price'] ?? 0;
        $all_items[] = $row;
    }
}

$visits_array = array_values($visits_data);

// ================================================================
// STATS
// ================================================================
$total_items = count($all_items);
$total_amount = 0;
$total_paid = 0;
$total_pending = 0;

foreach ($all_items as $item) {
    if ($type === 'consultations') {
        $price = (float)($item['consultation_fee'] ?? 0);
        $status = $item['payment_status'] ?? 'pending';
    } else {
        $price = (float)($item['procedure_price'] ?? 0);
        $status = $item['status'] ?? 'pending';
    }
    
    $total_amount += $price;
    if (in_array($status, ['paid', 'completed'])) {
        $total_paid += $price;
    } else {
        $total_pending += $price;
    }
}

$total_visits = count($visits_array);

// TYPE LABELS
$type_labels = [
    'consultations' => ['title' => 'Consultations', 'icon' => 'fa-stethoscope', 'color' => '#0B5ED7'],
    'procedures' => ['title' => 'Procedures', 'icon' => 'fa-syringe', 'color' => '#0B5ED7'],
    'equipment' => ['title' => 'Equipments', 'icon' => 'fa-microscope', 'color' => '#0891B2']
];
$type_info = $type_labels[$type] ?? $type_labels['consultations'];

$patient_age = calculateAge($patient['date_of_birth']);
$initials = strtoupper(substr($patient['full_name'] ?? 'P', 0, 1));

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once __DIR__ . '/../../components/audit_header.php';
include_once __DIR__ . '/../../components/audit_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient <?= htmlspecialchars($type_info['title']) ?> - <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
:root {
    --font-primary: 'Inter', -apple-system, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #3B82F6;
    --primary-bg: #E8F0FE;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --success: #059669;
    --success-bg: #D1FAE5;
    --danger: #DC2626;
    --danger-bg: #FEE2E2;
    --warning: #D97706;
    --warning-bg: #FEF3C7;
    --purple: #7C3AED;
    --purple-bg: #EDE9FE;
    --cyan: #0891B2;
    --cyan-bg: #CFFAFE;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
}
[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary: #3B82F6;
    --primary-dark: #2563EB;
    --primary-bg: #1E3A5F;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3A2A1A;
    --purple-bg: #2D1B4E;
    --cyan-bg: #0E3A47;
}
* { font-family: var(--font-primary); -webkit-font-smoothing: antialiased; }
html, body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); }
.money-number, .money-cell, .font-mono, .mono {
    font-family: var(--font-mono) !important;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

/* VIEW-ONLY BANNER */
.view-only-banner {
    background: linear-gradient(135deg, #FEF3C7, #FDE68A);
    border: 2px solid #F59E0B;
    border-radius: 12px;
    padding: 14px 20px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
}
[data-theme="dark"] .view-only-banner {
    background: linear-gradient(135deg, #3A2A1A, #2A1E0E);
    border-color: #B45309;
}
.view-only-banner .vo-icon {
    width: 44px; height: 44px; border-radius: 12px;
    background: linear-gradient(135deg, #F59E0B, #D97706);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; flex-shrink: 0;
}
.view-only-banner .vo-content { flex: 1; min-width: 200px; }
.view-only-banner .vo-title {
    font-size: 0.9rem; font-weight: 800;
    color: #78350F; display: flex; align-items: center;
    gap: 6px; margin-bottom: 2px;
}
[data-theme="dark"] .view-only-banner .vo-title { color: #FCD34D; }
.view-only-banner .vo-sub {
    font-size: 0.72rem; color: #92400E; font-weight: 600;
}
[data-theme="dark"] .view-only-banner .vo-sub { color: #FDE68A; }

.page-header { background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%); border-radius: 16px; padding: 20px 24px; margin-bottom: 18px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px; box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25); position: relative; overflow: hidden; }
.page-header::before { content: ''; position: absolute; top: -50%; right: -20%; width: 350px; height: 350px; background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%); border-radius: 50%; pointer-events: none; }
.page-header .page-title { color: white; font-size: 1.4rem; font-weight: 800; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; position: relative; z-index: 1; letter-spacing: -0.02em; }
.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }
.page-header .page-subtitle { color: rgba(255,255,255,0.85); font-size: 0.78rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px; position: relative; z-index: 1; }
.branch-tag { background: rgba(255,255,255,0.15); color: white; padding: 3px 10px; border-radius: 16px; font-size: 0.65rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1); }
.branch-tag.view-tag { background: linear-gradient(135deg, #F59E0B, #D97706); color: white; font-weight: 800; }
.btn-header { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 8px 14px; border-radius: 9px; font-weight: 600; font-size: 0.75rem; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(4px); position: relative; z-index: 1; cursor: pointer; }
.btn-header:hover { background: rgba(255,255,255,0.28); transform: translateY(-2px); color: white; }

/* PATIENT CARD */
.patient-card {
    background: var(--bg-card); border-radius: 14px;
    border: 2px solid var(--border-color); padding: 18px 22px;
    margin-bottom: 18px; display: flex; align-items: center; gap: 16px;
    flex-wrap: wrap; box-shadow: var(--shadow-sm);
    position: relative; overflow: hidden;
}
.patient-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0;
    height: 4px; background: linear-gradient(90deg, #0B5ED7, #3B82F6);
}
.patient-avatar-lg {
    width: 72px; height: 72px; border-radius: 16px;
    background: linear-gradient(135deg, #0B5ED7, #3B82F6);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 2rem; font-weight: 900; text-transform: uppercase;
    flex-shrink: 0; box-shadow: 0 8px 20px rgba(11, 94, 215, 0.3);
    border: 3px solid var(--bg-card);
}
.patient-info-main { flex: 1; min-width: 250px; }
.patient-name-lg {
    font-size: 1.35rem; font-weight: 800; color: var(--text-primary);
    letter-spacing: -0.02em; line-height: 1.2; margin-bottom: 6px;
}
.patient-meta-lg { display: flex; flex-wrap: wrap; gap: 8px; }
.patient-meta-lg .meta-pill {
    font-size: 0.7rem; font-weight: 600; color: var(--text-secondary);
    display: inline-flex; align-items: center; gap: 4px;
    background: var(--bg-body); padding: 4px 10px;
    border-radius: 12px; border: 1px solid var(--border-color);
}
.patient-meta-lg .meta-pill i { color: var(--primary); font-size: 0.68rem; }

/* STATS */
.stats-grid-4 {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 12px; margin-bottom: 18px;
}
.stat-mini {
    background: var(--bg-card); border-radius: 12px;
    padding: 14px 16px; border: 2px solid var(--border-color);
    display: flex; align-items: center; gap: 12px;
    box-shadow: var(--shadow-sm); transition: all 0.3s ease;
}
.stat-mini:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.stat-mini .icon-box {
    width: 42px; height: 42px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; color: white; flex-shrink: 0;
}
.stat-mini.blue .icon-box { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.stat-mini.green .icon-box { background: linear-gradient(135deg, #059669, #34D399); }
.stat-mini.orange .icon-box { background: linear-gradient(135deg, #D97706, #FBBF24); }
.stat-mini.purple .icon-box { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
.stat-mini .stat-info .stat-lbl {
    font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.06em;
    color: var(--text-secondary); font-weight: 800; margin-bottom: 2px;
}
.stat-mini .stat-info .stat-val {
    font-size: 1.15rem; font-weight: 900; color: var(--text-primary);
    font-family: var(--font-mono); letter-spacing: -0.03em; line-height: 1.1;
}
.stat-mini.blue .stat-val { color: var(--primary); }
.stat-mini.green .stat-val { color: var(--success); }
.stat-mini.orange .stat-val { color: var(--warning); }
.stat-mini.purple .stat-val { color: var(--purple); }

/* TYPE TABS */
.type-tabs {
    display: flex; gap: 8px; background: var(--bg-card);
    border-radius: 12px; padding: 8px; margin-bottom: 18px;
    border: 2px solid var(--border-color); flex-wrap: wrap;
}
.type-tab {
    padding: 10px 20px; border-radius: 8px;
    font-weight: 700; font-size: 0.8rem; text-decoration: none;
    color: var(--text-secondary); background: transparent;
    border: none; cursor: pointer;
    display: inline-flex; align-items: center; gap: 8px;
    transition: all 0.25s ease; flex: 1;
    justify-content: center;
}
.type-tab:hover { background: var(--primary-bg); color: var(--primary); }
.type-tab.active {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white; box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}

/* VISIT CARD */
.visit-card {
    background: var(--bg-card); border-radius: 14px;
    border: 2px solid var(--border-color); margin-bottom: 18px;
    overflow: hidden; box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}
.visit-card:hover { border-color: var(--primary); box-shadow: var(--shadow-md); }
.visit-header {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    padding: 14px 20px; display: flex; justify-content: space-between;
    align-items: center; gap: 12px; flex-wrap: wrap;
}
.visit-header-left {
    display: flex; align-items: center; gap: 12px;
    flex-wrap: wrap; flex: 1;
}
.visit-icon-badge {
    width: 42px; height: 42px; border-radius: 11px;
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F; display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);
    flex-shrink: 0;
}
.visit-number {
    font-family: var(--font-mono); font-size: 0.95rem; font-weight: 800;
    color: white; background: rgba(255,255,255,0.2);
    padding: 5px 12px; border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.25);
}
.visit-meta-item {
    color: rgba(255,255,255,0.9); font-size: 0.72rem;
    font-weight: 600; display: inline-flex; align-items: center; gap: 5px;
}
.visit-meta-item i { font-size: 0.7rem; }
.visit-header-right {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.visit-stat-pill {
    background: rgba(255,255,255,0.2); color: white;
    padding: 4px 12px; border-radius: 16px;
    font-size: 0.68rem; font-weight: 700;
    display: inline-flex; align-items: center; gap: 5px;
    border: 1px solid rgba(255,255,255,0.15);
}
.visit-stat-pill .num {
    font-family: var(--font-mono); font-weight: 900; font-size: 0.8rem;
}

/* TABLE NAV < > */
.table-nav-group {
    display: inline-flex; align-items: center; gap: 2px;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.25);
    border-radius: 8px; padding: 2px;
}
.table-nav-btn {
    background: rgba(255,255,255,0.15); border: none;
    color: white; width: 28px; height: 28px;
    border-radius: 6px; cursor: pointer;
    display: inline-flex; align-items: center;
    justify-content: center; font-size: 0.72rem;
    transition: all 0.2s ease;
}
.table-nav-btn:hover:not(:disabled) {
    background: rgba(255,255,255,0.35); transform: scale(1.1);
}
.table-nav-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.table-nav-indicator {
    font-size: 0.6rem; font-weight: 800;
    font-family: var(--font-mono);
    color: white; padding: 0 6px;
    min-width: 38px; text-align: center;
    background: rgba(255,255,255,0.15);
    border-radius: 5px; height: 24px;
    line-height: 24px;
}

/* TABLE */
.table-wrapper { overflow-x: auto; scroll-behavior: smooth; }
.table-wrapper::-webkit-scrollbar { height: 8px; }
.table-wrapper::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 10px; }
.table-wrapper::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }

.data-table {
    width: 100%; border-collapse: collapse;
    font-size: 0.78rem; min-width: 800px;
}
.data-table thead th {
    text-align: left; padding: 10px 12px;
    font-weight: 800; font-size: 0.6rem; text-transform: uppercase;
    letter-spacing: 0.06em; color: white;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    white-space: nowrap;
}
.data-table tbody td {
    padding: 10px 12px; border-bottom: 1px solid var(--border-color);
    color: var(--text-primary); vertical-align: middle;
}
.data-table tbody tr:hover td { background: var(--primary-bg); }
.data-table tbody tr:last-child td { border-bottom: none; }

/* STATUS BADGE */
.status-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 18px;
    font-size: 0.62rem; font-weight: 700;
    text-transform: uppercase; white-space: nowrap;
}
.status-badge.warning { background: var(--warning-bg); color: var(--warning); }
.status-badge.success { background: var(--success-bg); color: var(--success); }
.status-badge.danger { background: var(--danger-bg); color: var(--danger); }
.status-badge.info { background: var(--primary-bg); color: var(--primary); }
.status-badge.purple { background: var(--purple-bg); color: var(--purple); }
.status-badge.cyan { background: var(--cyan-bg); color: var(--cyan); }

/* ACTION BUTTON — VIEW ONLY */
.action-group { display: flex; gap: 4px; align-items: center; justify-content: center; }
.btn-act {
    width: 30px; height: 30px; border-radius: 7px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.75rem; border: none; cursor: pointer;
    transition: all 0.25s ease; text-decoration: none; color: white;
}
.btn-act:hover { transform: translateY(-2px); color: white; }
.btn-act.view { background: #0B5ED7; }
.btn-act.view:hover { background: #0A4CA8; box-shadow: 0 4px 10px rgba(11, 94, 215, 0.4); }

/* MONEY CELL */
.money-cell {
    font-family: var(--font-mono); font-weight: 800;
    color: var(--primary); font-size: 0.82rem;
    text-align: right; white-space: nowrap;
}

/* EMPTY STATE */
.empty-state {
    text-align: center; padding: 60px 20px;
    color: var(--text-secondary); background: var(--bg-card);
    border-radius: 14px; border: 2px dashed var(--border-color);
}
.empty-state i {
    font-size: 3.5rem; color: var(--primary);
    display: block; margin-bottom: 12px; opacity: 0.4;
}
.empty-state p { font-size: 1rem; font-weight: 700; color: var(--text-primary); }
.empty-state .sub { font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px; font-weight: 400; }

/* RESPONSIVE */
@media (max-width: 1024px) {
    .stats-grid-4 { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .stats-grid-4 { grid-template-columns: 1fr 1fr; gap: 10px; }
    .stat-mini { padding: 12px; }
    .stat-mini .icon-box { width: 36px; height: 36px; font-size: 0.95rem; }
    .stat-mini .stat-info .stat-val { font-size: 1rem; }
    .patient-avatar-lg { width: 58px; height: 58px; font-size: 1.6rem; }
    .patient-name-lg { font-size: 1.1rem; }
    .visit-header { padding: 12px 14px; }
    .type-tab { padding: 8px 14px; font-size: 0.72rem; }
    .visit-header-right { flex-direction: column; align-items: stretch; }
}
@media (max-width: 480px) {
    .stats-grid-4 { grid-template-columns: 1fr; }
}

@media print {
    .btn-header, .btn-act { display: none !important; }
    .view-only-banner { display: none !important; }
    .page-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
    </style>
</head>
<body>

<main class="main-content">

    <!-- VIEW ONLY BANNER -->
    <div class="view-only-banner">
        <div class="vo-icon"><i class="fas fa-eye"></i></div>
        <div class="vo-content">
            <div class="vo-title"><i class="fas fa-lock"></i> View-Only Mode</div>
            <div class="vo-sub">Audit access — You can view and print records only. To edit or delete, contact an administrator.</div>
        </div>
    </div>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas <?= $type_info['icon'] ?>"></i>
                Patient <?= htmlspecialchars($type_info['title']) ?>
                <span class="branch-tag view-tag">
                    <i class="fas fa-eye"></i> VIEW ONLY
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user-injured"></i>
                <strong><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-clipboard-list"></i> <?= $total_visits ?> Visit(s)
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="other_services.php?branch=<?= $selected_branch_id ?>&tab=<?= $type === 'consultations' ? 'consultations' : 'procedures' ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <!-- PATIENT CARD -->
    <div class="patient-card">
        <div class="patient-avatar-lg"><?= $initials ?></div>
        <div class="patient-info-main">
            <div class="patient-name-lg"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></div>
            <div class="patient-meta-lg">
                <span class="meta-pill">
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                </span>
                <?php if (!empty($patient['gender'])): ?>
                <span class="meta-pill">
                    <i class="fas fa-<?= strtolower($patient['gender']) === 'female' ? 'venus' : 'mars' ?>"></i>
                    <?= htmlspecialchars($patient['gender']) ?>
                </span>
                <?php endif; ?>
                <?php if ($patient_age !== 'N/A'): ?>
                <span class="meta-pill">
                    <i class="fas fa-birthday-cake"></i> <?= $patient_age ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($patient['phone'])): ?>
                <span class="meta-pill">
                    <i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone']) ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($patient['branch_name'])): ?>
                <span class="meta-pill">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($patient['branch_name']) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TYPE TABS -->
    <div class="type-tabs">
        <a href="?patient_id=<?= $patient_id ?>&type=consultations&branch=<?= $selected_branch_id ?>" 
           class="type-tab <?= $type === 'consultations' ? 'active' : '' ?>">
            <i class="fas fa-stethoscope"></i> Consultations
        </a>
        <a href="?patient_id=<?= $patient_id ?>&type=procedures&branch=<?= $selected_branch_id ?>" 
           class="type-tab <?= $type === 'procedures' ? 'active' : '' ?>">
            <i class="fas fa-syringe"></i> Procedures
        </a>
        <a href="?patient_id=<?= $patient_id ?>&type=equipment&branch=<?= $selected_branch_id ?>" 
           class="type-tab <?= $type === 'equipment' ? 'active' : '' ?>">
            <i class="fas fa-microscope"></i> Equipments
        </a>
    </div>

    <!-- STATS -->
    <div class="stats-grid-4">
        <div class="stat-mini blue">
            <div class="icon-box"><i class="fas <?= $type_info['icon'] ?>"></i></div>
            <div class="stat-info">
                <div class="stat-lbl">Total <?= htmlspecialchars($type_info['title']) ?></div>
                <div class="stat-val"><?= number_format($total_items) ?></div>
            </div>
        </div>
        <div class="stat-mini green">
            <div class="icon-box"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-lbl">Paid / Completed</div>
                <div class="stat-val"><?= $currency ?> <?= number_format($total_paid, 0) ?></div>
            </div>
        </div>
        <div class="stat-mini orange">
            <div class="icon-box"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <div class="stat-lbl">Pending</div>
                <div class="stat-val"><?= $currency ?> <?= number_format($total_pending, 0) ?></div>
            </div>
        </div>
        <div class="stat-mini purple">
            <div class="icon-box"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-info">
                <div class="stat-lbl">Total Amount</div>
                <div class="stat-val"><?= $currency ?> <?= number_format($total_amount, 0) ?></div>
            </div>
        </div>
    </div>

    <!-- VISITS -->
    <?php if (count($visits_array) > 0): ?>
        <?php foreach ($visits_array as $visit): 
            $vid = $visit['visit_id'];
            $visit_items = $visit['items'];
            $item_count = count($visit_items);
            $visit_total = 0;
            
            if ($type === 'consultations') {
                $visit_total = (float)($visit['consultation_fee'] ?? 0);
            } else {
                $visit_total = $visit['total_amount'] ?? 0;
            }
            
            $uid = $patient_id . '-' . $vid;
        ?>
            <div class="visit-card">
                <div class="visit-header">
                    <div class="visit-header-left">
                        <div class="visit-icon-badge"><i class="fas <?= $type_info['icon'] ?>"></i></div>
                        <span class="visit-number"><?= htmlspecialchars($visit['visit_number']) ?></span>
                        <span class="visit-meta-item">
                            <i class="fas fa-calendar-day"></i>
                            <?= !empty($visit['visit_date']) ? date('d M Y', strtotime($visit['visit_date'])) : 'N/A' ?>
                        </span>
                        <?php if (!empty($visit['doctor_name']) && $visit['doctor_name'] !== 'N/A'): ?>
                        <span class="visit-meta-item">
                            <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($visit['doctor_name']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="visit-header-right">
                        <span class="visit-stat-pill">
                            <i class="fas <?= $type_info['icon'] ?>"></i>
                            <span class="num"><?= $item_count ?></span> <?= htmlspecialchars($type_info['title']) ?>
                        </span>
                        <span class="visit-stat-pill" style="background:rgba(52,211,153,0.3);">
                            <i class="fas fa-money-bill-wave"></i>
                            <span class="num"><?= $currency ?> <?= number_format($visit_total, 0) ?></span>
                        </span>
                        <div class="table-nav-group">
                            <button type="button" class="table-nav-btn" onclick="scrollTable('table-<?= $uid ?>', -1)">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <span class="table-nav-indicator" id="indicator-<?= $uid ?>">0%</span>
                            <button type="button" class="table-nav-btn" onclick="scrollTable('table-<?= $uid ?>', 1)">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="table-wrapper" id="table-<?= $uid ?>">
                    <table class="data-table">
                        <thead>
                            <?php if ($type === 'consultations'): ?>
                                <tr>
                                    <th>Visit #</th>
                                    <th>Visit Type</th>
                                    <th>Doctor</th>
                                    <th>Receptionist</th>
                                    <th>Diagnosis</th>
                                    <th style="text-align:right;">Fee</th>
                                    <th style="text-align:center;">Payment</th>
                                    <th style="text-align:center;">View</th>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <th style="width:45px;">#</th>
                                    <th><?= $type === 'equipment' ? 'Equipment' : 'Procedure' ?> Name</th>
                                    <th>Category</th>
                                    <th>Doctor</th>
                                    <th style="text-align:right;">Price</th>
                                    <th style="text-align:center;">Status</th>
                                    <th>Date</th>
                                    <th style="text-align:center;">View</th>
                                </tr>
                            <?php endif; ?>
                        </thead>
                        <tbody>
                            <?php if ($type === 'consultations'): 
                                $item = $visit_items[0];
                                $vs = getStatusBadge($item['payment_status'] ?? 'pending');
                            ?>
                                <tr>
                                    <td style="font-weight:700;color:var(--primary);font-family:var(--font-mono);">
                                        <?= htmlspecialchars($visit['visit_number']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($visit['visit_type'] ?? '—') ?></td>
                                    <td><i class="fas fa-user-md" style="color:var(--cyan);"></i> <?= htmlspecialchars($visit['doctor_name']) ?></td>
                                    <td><i class="fas fa-user-tie" style="color:var(--purple);"></i> <?= htmlspecialchars($visit['receptionist_name'] ?? '—') ?></td>
                                    <td style="font-size:0.72rem;color:var(--text-secondary);max-width:220px;">
                                        <?= htmlspecialchars($visit['diagnosis'] ?: '—') ?>
                                    </td>
                                    <td class="money-cell"><?= formatTsh($visit['consultation_fee'] ?? 0) ?></td>
                                    <td style="text-align:center;">
                                        <span class="status-badge <?= $vs['class'] ?>">
                                            <?= $vs['icon'] ?> <?= $vs['label'] ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <a href="view_consultation.php?id=<?= $vid ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-act view" title="View Consultation">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php else: 
                                $i = 1;
                                foreach ($visit_items as $item): 
                                    $s = getStatusBadge($item['status']);
                            ?>
                                <tr data-search="<?= htmlspecialchars(strtolower($item['procedure_name'] . ' ' . ($item['category'] ?? ''))) ?>">
                                    <td style="text-align:center;font-weight:700;"><?= $i++ ?></td>
                                    <td>
                                        <span style="font-weight:700;color:var(--primary);">
                                            <?= htmlspecialchars($item['procedure_name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge purple" style="font-size:0.55rem;">
                                            <?= htmlspecialchars($item['category'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-size:0.72rem;">
                                            <i class="fas fa-user-md" style="color:var(--cyan);"></i>
                                            <?= htmlspecialchars($item['doctor_name'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td class="money-cell"><?= formatTsh($item['procedure_price']) ?></td>
                                    <td style="text-align:center;">
                                        <span class="status-badge <?= $s['class'] ?>">
                                            <?= $s['icon'] ?> <?= $s['label'] ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.7rem;"><?= date('d M Y', strtotime($item['created_at'])) ?></td>
                                    <td style="text-align:center;">
                                        <a href="view_procedure.php?id=<?= $item['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-act view" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas <?= $type_info['icon'] ?>"></i>
            <p>No <?= strtolower(htmlspecialchars($type_info['title'])) ?> found</p>
            <p class="sub">This patient has no <?= strtolower(htmlspecialchars($type_info['title'])) ?> recorded yet</p>
        </div>
    <?php endif; ?>

</main>

<script>
// ================================================================
// TABLE NAV < >
// ================================================================
function scrollTable(tableId, direction) {
    var table = document.getElementById(tableId);
    if (!table) return;
    
    var scrollAmount = 300;
    var currentScroll = table.scrollLeft;
    var maxScroll = table.scrollWidth - table.clientWidth;
    
    var newScroll = currentScroll + (direction * scrollAmount);
    if (newScroll < 0) newScroll = 0;
    if (newScroll > maxScroll) newScroll = maxScroll;
    
    table.scrollTo({ left: newScroll, behavior: 'smooth' });
    setTimeout(function() { updateTableNav(tableId); }, 350);
}

function updateTableNav(tableId) {
    var table = document.getElementById(tableId);
    if (!table) return;
    
    var uniqueId = tableId.replace('table-', '');
    var indicator = document.getElementById('indicator-' + uniqueId);
    
    var currentScroll = table.scrollLeft;
    var maxScroll = table.scrollWidth - table.clientWidth;
    
    var percent = 0;
    if (maxScroll > 0) percent = Math.round((currentScroll / maxScroll) * 100);
    if (indicator) indicator.textContent = percent + '%';
    
    var section = table.closest('.visit-card');
    if (section) {
        var btns = section.querySelectorAll('.table-nav-btn');
        if (btns.length >= 2) {
            btns[0].disabled = (currentScroll <= 1);
            btns[1].disabled = (currentScroll >= maxScroll - 1);
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.table-wrapper').forEach(function(tbl) {
        if (!tbl.id) return;
        tbl.addEventListener('scroll', function() { updateTableNav(tbl.id); });
        updateTableNav(tbl.id);
    });
});

console.log('%c🔍 Audit - Patient Other Services (VIEW ONLY)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Type: <?= $type ?>', 'font-size:12px;color:#34D399;font-weight:bold;');
console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:12px;color:#34D399;');
console.log('%c📊 Total: <?= $total_items ?> items / <?= $total_visits ?> visits', 'font-size:12px;color:#34D399;');
console.log('%c❌ NO Edit/Delete buttons', 'font-size:12px;color:#DC2626;font-weight:bold;');
</script>

</body>
</html>