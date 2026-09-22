<?php
// ================================================================
// FILE: frontend/pages/audit/patient_lab_tests.php
// AUDIT - ALL LAB TESTS FOR A PATIENT
// ✅ Inaonyesha data za branch ya mtumiaji aliye login TU
// ✅ Jina la mtumiaji aliye login linaonekana kwenye header
// ✅ Arrow <> buttons kwenye kila visit table header
// ✅ Lab Technician column per test (lab_technician_id)
// ✅ Interpretation column
// ✅ Print + Back + View button only
// ✅ BLUE THEME
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
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$patient_id = (int)($_GET['patient_id'] ?? 0);

// ================================================================
// ✅ LAZIMISHA branch ya mtumiaji aliye login TU
// ================================================================
$selected_branch_id = (int)$user_branch_id;

if ($patient_id <= 0) {
    header('Location: lab_tests.php');
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

// ================================================================
// GET PATIENT INFO - LAZIMISHA BRANCH YA MTUMIAJI
// ================================================================
$patient = null;
try {
    $stmt = $db->prepare("
        SELECT p.*, b.name as branch_name
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE p.id = ? AND p.branch_id = ?
    ");
    $stmt->execute([$patient_id, $selected_branch_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if (!$patient) {
    header('Location: lab_tests.php');
    exit;
}

$patient_branch_id = (int)($patient['branch_id'] ?? 0);

// ================================================================
// GET LAB TEST CATALOG
// ================================================================
$lab_catalog = [];
try {
    if ($patient_branch_id > 0) {
        $sql = "SELECT id, test_name, test_code, category, price 
                FROM lab_tests_catalog
                WHERE is_active = 1
                AND branch_id = ?
                ORDER BY category, test_name";
        $stmt = $db->prepare($sql);
        $stmt->execute([$patient_branch_id]);
        $lab_catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Catalog fetch error: " . $e->getMessage());
}

// ================================================================
// GET ALL LAB TESTS WITH LAB TECHNICIAN (lab_technician_id)
// ================================================================
$visits_data = [];
$all_tests = [];
try {
    $sql = "
        SELECT 
            lt.*,
            doc.full_name as doctor_name,
            recv.full_name as received_by_name,
            recv.role as received_by_role,
            tech.full_name as technician_name,
            tech.role as technician_role,
            tech.specialty as technician_specialty,
            v.visit_number,
            v.visit_date,
            v.diagnosis,
            v.status as visit_status,
            b.name as branch_name
        FROM lab_tests lt
        LEFT JOIN users doc ON lt.doctor_id = doc.id
        LEFT JOIN users recv ON lt.performed_by = recv.id
        LEFT JOIN users tech ON lt.lab_technician_id = tech.id
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN branches b ON lt.branch_id = b.id
        WHERE lt.patient_id = ? AND lt.branch_id = ?
        ORDER BY v.visit_date DESC, lt.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$patient_id, $selected_branch_id]);
    $all_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_tests as $test) {
        $vid = $test['visit_id'] ?? 0;
        if (!isset($visits_data[$vid])) {
            $visits_data[$vid] = [
                'visit_id' => $vid,
                'visit_number' => $test['visit_number'] ?? 'N/A',
                'visit_date' => $test['visit_date'],
                'diagnosis' => $test['diagnosis'] ?? '',
                'visit_status' => $test['visit_status'] ?? 'N/A',
                'doctor_name' => $test['doctor_name'] ?? 'N/A',
                'branch_name' => $test['branch_name'] ?? 'N/A',
                'tests' => [],
                'total_amount' => 0,
                'total_paid' => 0
            ];
        }
        
        $visits_data[$vid]['tests'][] = $test;
        $visits_data[$vid]['total_amount'] += (float)($test['test_price'] ?? 0);
        if (in_array($test['status'], ['completed', 'paid'])) {
            $visits_data[$vid]['total_paid'] += (float)($test['test_price'] ?? 0);
        }
    }
} catch (Exception $e) {
    error_log("Fetch tests error: " . $e->getMessage());
}

$visits_array = array_values($visits_data);

// ================================================================
// STATS
// ================================================================
$total_tests = 0;
$total_amount = 0;
$total_paid = 0;
$total_pending = 0;

foreach ($all_tests as $t) {
    $total_tests++;
    $total_amount += (float)($t['test_price'] ?? 0);
    if (in_array($t['status'], ['completed', 'paid'])) {
        $total_paid += (float)($t['test_price'] ?? 0);
    } else {
        $total_pending += (float)($t['test_price'] ?? 0);
    }
}

$total_visits = count($visits_array);

// HELPERS
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    try {
        return (new DateTime($dob))->diff(new DateTime('today'))->y . ' yrs';
    } catch (Exception $e) { return 'N/A'; }
}

function getStatusBadge($status) {
    $map = [
        'pending' => ['class' => 'warning', 'icon' => '⏳', 'label' => 'Pending'],
        'in_progress' => ['class' => 'info', 'icon' => '🔄', 'label' => 'In Progress'],
        'completed' => ['class' => 'success', 'icon' => '✅', 'label' => 'Completed'],
        'cancelled' => ['class' => 'danger', 'icon' => '❌', 'label' => 'Cancelled']
    ];
    return $map[$status] ?? ['class' => 'warning', 'icon' => '❓', 'label' => ucfirst($status)];
}

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
    <title>Patient Lab Tests - <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
:root {
    --font-primary: 'Inter', -apple-system, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #3B82F6;
    --primary-bg: #E8F0FE;
    --primary-soft: #DBEAFE;
    
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
    --teal: #0D9488;
    --teal-bg: #CCFBF1;
    
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
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
    --primary-soft: #1E40AF;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3A2A1A;
    --purple-bg: #2D1B4E;
    --cyan-bg: #0E3A47;
    --teal-bg: #134E4A;
}

* { font-family: var(--font-primary); -webkit-font-smoothing: antialiased; }

html, body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); }

.money-number, .money-cell, .font-mono, .mono {
    font-family: var(--font-mono) !important;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

/* PAGE HEADER */
.page-header {
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
    border-radius: 16px; padding: 20px 24px; margin-bottom: 18px;
    display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center;
    gap: 14px; box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25);
    position: relative; overflow: hidden;
}
.page-header::before {
    content: ''; position: absolute; top: -50%; right: -20%;
    width: 350px; height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%; pointer-events: none;
}
.page-header .page-title {
    color: white; font-size: 1.35rem; font-weight: 800;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    position: relative; z-index: 1; letter-spacing: -0.02em;
}
.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }
.page-header .page-subtitle {
    color: rgba(255,255,255,0.85); font-size: 0.78rem;
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    margin-top: 4px; position: relative; z-index: 1;
}
.branch-tag {
    background: rgba(255,255,255,0.15); color: white;
    padding: 3px 10px; border-radius: 16px; font-size: 0.65rem;
    font-weight: 500; display: inline-flex; align-items: center; gap: 4px;
    backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1);
}
.branch-tag.user-tag {
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    font-weight: 800;
    box-shadow: 0 2px 8px rgba(252, 211, 77, 0.3);
}
.btn-header {
    background: rgba(255,255,255,0.15); color: white;
    border: 1px solid rgba(255,255,255,0.25);
    padding: 8px 14px; border-radius: 9px;
    font-weight: 600; font-size: 0.75rem;
    transition: all 0.3s ease; text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
    backdrop-filter: blur(4px); position: relative; z-index: 1; cursor: pointer;
}
.btn-header:hover { background: rgba(255,255,255,0.28); transform: translateY(-2px); }

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

/* SCROLL BUTTONS - Kwenye visit header */
.scroll-buttons-group {
    display: inline-flex;
    gap: 6px;
    align-items: center;
    margin-left: 8px;
    padding-left: 10px;
    border-left: 2px solid rgba(255,255,255,0.2);
}
.scroll-btn-header {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1.5px solid rgba(255,255,255,0.3);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 800;
    transition: all 0.25s ease;
    flex-shrink: 0;
    backdrop-filter: blur(4px);
}
.scroll-btn-header:hover {
    background: rgba(255,255,255,0.4);
    border-color: rgba(255,255,255,0.6);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
.scroll-btn-header:active {
    transform: translateY(0) scale(0.95);
}

/* TABLE */
.table-wrapper { 
    overflow-x: auto; 
    scroll-behavior: smooth; 
    position: relative;
}
.table-wrapper::-webkit-scrollbar { height: 8px; }
.table-wrapper::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; }
.table-wrapper::-webkit-scrollbar-thumb { 
    background: linear-gradient(90deg, #0B5ED7, #3B82F6); 
    border-radius: 10px; 
}
.table-wrapper::-webkit-scrollbar-thumb:hover { 
    background: linear-gradient(90deg, #0A4CA8, #2563EB); 
}

.data-table {
    width: 100%; border-collapse: collapse;
    font-size: 0.78rem; min-width: 1300px;
}
.data-table thead th {
    text-align: left; padding: 10px 12px;
    font-weight: 800; font-size: 0.6rem; text-transform: uppercase;
    letter-spacing: 0.06em; color: white;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 2;
}
.data-table tbody td {
    padding: 10px 12px; border-bottom: 1px solid var(--border-color);
    color: var(--text-primary); vertical-align: middle;
}
.data-table tbody tr:hover td { background: var(--primary-bg); }
.data-table tbody tr:last-child td { border-bottom: none; }

/* LAB TECHNICIAN CELL */
.tech-info {
    display: flex;
    align-items: center;
    gap: 8px;
}
.tech-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0891B2, #22D3EE);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.72rem;
    flex-shrink: 0;
    text-transform: uppercase;
    box-shadow: 0 2px 6px rgba(8, 145, 178, 0.3);
}
.tech-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.tech-name {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-primary);
    white-space: nowrap;
}
.tech-role {
    font-size: 0.58rem;
    font-weight: 800;
    color: var(--cyan);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.tech-empty {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    color: var(--text-secondary);
    font-style: italic;
    padding: 4px 8px;
    border-radius: 6px;
    background: var(--bg-body);
}

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
.status-badge.teal { background: var(--teal-bg); color: var(--teal); }

/* ACTION BUTTON */
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
    .tech-avatar { width: 28px; height: 28px; font-size: 0.65rem; }
    .tech-name { font-size: 0.7rem; }
    .scroll-btn-header { width: 28px; height: 28px; font-size: 0.7rem; }
}
@media (max-width: 480px) {
    .stats-grid-4 { grid-template-columns: 1fr; }
}

@media print {
    .btn-header, .btn-act, .scroll-buttons-group { display: none !important; }
    .page-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Patient Lab Tests
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user-circle"></i>
                Karibu, <span class="branch-tag user-tag"><i class="fas fa-user"></i> <?= htmlspecialchars($user_full_name) ?></span>
                <span class="branch-tag">
                    <i class="fas fa-user-injured"></i> <strong><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></strong>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
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
            <a href="lab_tests.php" class="btn-header">
                <i class="fas fa-arrow-left"></i> All Lab Tests
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

    <!-- STATS -->
    <div class="stats-grid-4">
        <div class="stat-mini blue">
            <div class="icon-box"><i class="fas fa-flask"></i></div>
            <div class="stat-info">
                <div class="stat-lbl">Total Tests</div>
                <div class="stat-val"><?= number_format($total_tests) ?></div>
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
        <?php foreach ($visits_array as $visit_index => $visit): 
            $vid = $visit['visit_id'];
            $visit_tests = $visit['tests'];
            $test_count = count($visit_tests);
            $visit_total = $visit['total_amount'];
            $wrapper_id = 'visitTable_' . $visit_index;
        ?>
            <div class="visit-card">
                <div class="visit-header">
                    <div class="visit-header-left">
                        <div class="visit-icon-badge">
                            <i class="fas fa-calendar-check"></i>
                        </div>
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
                            <i class="fas fa-flask"></i>
                            <span class="num"><?= $test_count ?></span> Tests
                        </span>
                        <span class="visit-stat-pill" style="background:rgba(52,211,153,0.3);">
                            <i class="fas fa-money-bill-wave"></i>
                            <span class="num"><?= $currency ?> <?= number_format($visit_total, 0) ?></span>
                        </span>
                        
                        <!-- SCROLL ARROW BUTTONS -->
                        <div class="scroll-buttons-group">
                            <button type="button" class="scroll-btn-header" 
                                    onclick="scrollVisitTable('<?= $wrapper_id ?>', 'left')" 
                                    title="Scroll Left">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button type="button" class="scroll-btn-header" 
                                    onclick="scrollVisitTable('<?= $wrapper_id ?>', 'right')" 
                                    title="Scroll Right">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="table-wrapper" id="<?= $wrapper_id ?>">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:40px;">#</th>
                                <th><i class="fas fa-flask"></i> Test Name</th>
                                <th><i class="fas fa-tag"></i> Type</th>
                                <th><i class="fas fa-vial"></i> Sample</th>
                                <th><i class="fas fa-file-medical"></i> Result</th>
                                <th><i class="fas fa-comment-medical"></i> Interpretation</th>
                                <th><i class="fas fa-user-flask"></i> Lab Technician</th>
                                <th style="text-align:right;"><i class="fas fa-money-bill-wave"></i> Price</th>
                                <th style="text-align:center;"><i class="fas fa-info-circle"></i> Status</th>
                                <th style="text-align:center;"><i class="fas fa-eye"></i> View</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $ti = 1; foreach ($visit_tests as $test): 
                                $badge = getStatusBadge($test['status']);
                                
                                // Lab Technician Info - from lab_technician_id
                                $tech_name = $test['technician_name'] ?? '';
                                $tech_role = $test['technician_role'] ?? '';
                                $tech_initials = '';
                                if (!empty($tech_name)) {
                                    $name_parts = explode(' ', trim($tech_name));
                                    $tech_initials = count($name_parts) >= 2 
                                        ? strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1)) 
                                        : strtoupper(substr($tech_name, 0, 2));
                                }
                                
                                // Fallback: received_by (performed_by) kama technician_name haipo
                                if (empty($tech_name) && !empty($test['received_by_name'])) {
                                    $tech_name = $test['received_by_name'];
                                    $tech_role = $test['received_by_role'] ?? 'Lab Technician';
                                    $name_parts = explode(' ', trim($tech_name));
                                    $tech_initials = count($name_parts) >= 2 
                                        ? strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1)) 
                                        : strtoupper(substr($tech_name, 0, 2));
                                }
                            ?>
                                <tr>
                                    <td style="text-align:center;font-weight:700;color:var(--text-secondary);font-family:var(--font-mono);font-size:0.7rem;">
                                        <?= $ti++ ?>
                                    </td>
                                    <td>
                                        <span style="font-weight:700;color:var(--primary);">
                                            <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?>
                                        </span>
                                        <?php if (!empty($test['test_code'])): ?>
                                            <span style="font-family:var(--font-mono);font-size:0.6rem;background:var(--primary-bg);color:var(--primary);padding:1px 6px;border-radius:4px;margin-left:4px;font-weight:800;">
                                                <?= htmlspecialchars($test['test_code']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($test['test_type'])): ?>
                                            <span class="status-badge purple"><?= htmlspecialchars($test['test_type']) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.75rem;">
                                        <?= htmlspecialchars($test['sample_type'] ?? '—') ?>
                                    </td>
                                    <td style="max-width:180px;">
                                        <?php if (!empty($test['results'])): ?>
                                            <span style="font-family:var(--font-mono);font-weight:700;color:var(--success);font-size:0.72rem;">
                                                <?= htmlspecialchars(substr($test['results'], 0, 35)) ?>
                                                <?= strlen($test['results']) > 35 ? '...' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);font-style:italic;font-size:0.7rem;">
                                                Waiting...
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width:180px;font-size:0.72rem;color:var(--text-secondary);">
                                        <?php if (!empty($test['interpretation'])): ?>
                                            <span style="font-weight:600;">
                                                <?= htmlspecialchars(substr($test['interpretation'], 0, 40)) ?>
                                                <?= strlen($test['interpretation']) > 40 ? '...' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);font-style:italic;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- LAB TECHNICIAN COLUMN -->
                                    <td>
                                        <?php if (!empty($tech_name)): ?>
                                            <div class="tech-info">
                                                <div class="tech-avatar"><?= htmlspecialchars($tech_initials) ?></div>
                                                <div class="tech-details">
                                                    <span class="tech-name"><?= htmlspecialchars($tech_name) ?></span>
                                                    <span class="tech-role">
                                                        <i class="fas fa-user-flask"></i>
                                                        <?= htmlspecialchars($tech_role ?: 'Lab Technician') ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="tech-empty">
                                                <i class="fas fa-user-slash"></i> Not assigned
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="money-cell">
                                        <?= $currency ?> <?= number_format($test['test_price'] ?? 0, 0) ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="status-badge <?= $badge['class'] ?>">
                                            <?= $badge['icon'] ?> <?= $badge['label'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-group">
                                            <a href="view_lab_test.php?id=<?= $test['id'] ?>" 
                                               class="btn-act view" title="View Lab Test">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-flask"></i>
            <p>No lab tests found for this patient</p>
            <p class="sub">This patient has no lab tests recorded yet</p>
        </div>
    <?php endif; ?>

</main>

<script>
// ================================================================
// SCROLL FUNCTION - Kila visit table ina scroll yake
// ================================================================
function scrollVisitTable(wrapperId, direction) {
    var wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;
    
    var amount = 300;
    wrapper.scrollBy({
        left: direction === 'left' ? -amount : amount,
        behavior: 'smooth'
    });
}

console.log('%c🔍 Audit - Patient Lab Tests', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px;color:#F59E0B;font-weight:bold;');
console.log('%c✅ Inaonyesha data za branch: <?= htmlspecialchars($user_branch_name) ?> (ID: <?= $selected_branch_id ?>)', 'font-size:13px;color:#34D399;font-weight:bold;');
console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:12px;color:#34D399;');
console.log('%c📊 Total Tests: <?= $total_tests ?>', 'font-size:12px;color:#34D399;');
console.log('%c📊 Total Visits: <?= $total_visits ?>', 'font-size:12px;color:#34D399;');
</script>

</body>
</html>