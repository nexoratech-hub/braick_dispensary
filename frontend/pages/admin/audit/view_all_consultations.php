<?php
// ================================================================
// FILE: frontend/pages/admin/audit/view_all_consultations.php
// ADMIN/ADMIN AUDIT - VIEW ALL CONSULTATIONS FOR A PATIENT
// ✅ Inaonyesha consultations zote za mgonjwa mmoja
// ✅ Group by visit
// ✅ Inaonyesha bill info (status, paid, balance)
// ✅ Inaonyesha vitals, diagnosis, treatment
// ✅ BLUE THEME + Table nav < >
// ✅ FIXED: Vital signs zinaonyeshwa kwa kila visit husika
//           (inashughulikia records zenye visit_id NULL)
// ================================================================

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$allowed_roles = ['admin', 'audit'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_admin = ($user_role === 'admin');
$is_audit = ($user_role === 'audit');

$patient_id = (int)($_GET['patient_id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($patient_id <= 0) {
    header('Location: other_services.php?tab=consultations&branch=' . urlencode($selected_branch_id));
    exit;
}

require_once __DIR__ . '/../../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// CURRENCY
// ================================================================
$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['setting_value'])) $currency = $row['setting_value'];
} catch (Exception $e) {}

// ================================================================
// HELPERS
// ================================================================
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
        'in_progress'=> ['class' => 'info',    'icon' => '🔄', 'label' => 'In Progress'],
        'assigned'   => ['class' => 'info',    'icon' => '👨‍⚕️', 'label' => 'Assigned'],
        'with_doctor'=> ['class' => 'cyan',    'icon' => '🩺', 'label' => 'With Doctor'],
        'lab_test'   => ['class' => 'purple',  'icon' => '🧪', 'label' => 'Lab Test'],
        'lab_completed' => ['class' => 'success', 'icon' => '✅', 'label' => 'Lab Completed'],
        'prescribed' => ['class' => 'cyan',    'icon' => '💊', 'label' => 'Prescribed'],
        'waiting'    => ['class' => 'warning', 'icon' => '⏳', 'label' => 'Waiting'],
    ];
    return $map[$status] ?? ['class' => 'warning', 'icon' => '❓', 'label' => ucfirst($status ?: 'Unknown')];
}

function formatTsh($amount) {
    return 'TSh ' . number_format((float)$amount, 0);
}

function getInitials($name) {
    if (empty($name)) return 'NA';
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

// ================================================================
// FETCH PATIENT
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
    header('Location: other_services.php?tab=consultations&branch=' . urlencode($selected_branch_id));
    exit;
}

// ================================================================
// FETCH ALL CONSULTATIONS
// ✅ FIXED: Inachukua vital signs kwa kila visit husika
// ================================================================
$visits_data = [];
$all_visits = [];

try {
    $where = " WHERE v.patient_id = ?";
    $params = [$patient_id];
    
    if ($selected_branch_id !== 'all') {
        $where .= " AND v.branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    
    $sql = "
        SELECT v.*,
               d.full_name as doctor_name, d.specialty as doctor_specialty,
               r.full_name as receptionist_name,
               br.name as branch_name,
               dis.disease_name, dis.disease_code,
               b.id as bill_id, b.bill_number, b.status as bill_status,
               b.total_amount as bill_total, b.paid_amount as bill_paid,
               b.balance as bill_balance, b.total_discount as bill_discount,
               b.premium_amount as bill_premium
        FROM visits v
        LEFT JOIN users d ON v.doctor_id = d.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        LEFT JOIN branches br ON v.branch_id = br.id
        LEFT JOIN diseases dis ON v.disease_id = dis.id
        LEFT JOIN bills b ON v.id = b.visit_id
        $where
        ORDER BY v.visit_date DESC, v.created_at DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $all_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ✅ Kwa kila visit, chukua vital signs zake pekee
    foreach ($all_visits as &$visit) {
        $vitals = null;
        $visit_id = $visit['id'];
        $patient_id_v = $visit['patient_id'];
        $visit_date = $visit['visit_date'];
        
        // 1) Jaribu kwanza kwa visit_id (records mpya)
        $stmt_vs = $db->prepare("
            SELECT * FROM vital_signs 
            WHERE patient_id = ? 
            AND visit_id = ?
            ORDER BY recorded_at DESC LIMIT 1
        ");
        $stmt_vs->execute([$patient_id_v, $visit_id]);
        $vitals = $stmt_vs->fetch(PDO::FETCH_ASSOC);
        
        // 2) Kama haipo, tafuta kwa tarehe inayokaribiana (±2 siku)
        if (!$vitals) {
            $stmt_vs = $db->prepare("
                SELECT *, ABS(TIMESTAMPDIFF(MINUTE, recorded_at, ?)) as time_diff
                FROM vital_signs 
                WHERE patient_id = ? 
                AND recorded_at BETWEEN DATE_SUB(?, INTERVAL 2 DAY) 
                                    AND DATE_ADD(?, INTERVAL 2 DAY)
                ORDER BY time_diff ASC, recorded_at DESC 
                LIMIT 1
            ");
            $stmt_vs->execute([$visit_date, $patient_id_v, $visit_date, $visit_date]);
            $vitals = $stmt_vs->fetch(PDO::FETCH_ASSOC);
        }
        
        // 3) Kama bado haipo, chukua ya karibu zaidi kabla ya visit
        if (!$vitals) {
            $stmt_vs = $db->prepare("
                SELECT * FROM vital_signs 
                WHERE patient_id = ? 
                AND recorded_at <= ?
                ORDER BY recorded_at DESC 
                LIMIT 1
            ");
            $stmt_vs->execute([$patient_id_v, $visit_date]);
            $vitals = $stmt_vs->fetch(PDO::FETCH_ASSOC);
        }
        
        // Ongeza vital signs kwenye visit array
        $visit['vitals'] = $vitals ?: [];
        
        // Backward compatibility - ongeza fields moja moja
        if ($vitals) {
            $visit['temperature'] = $vitals['temperature'];
            $visit['blood_pressure_systolic'] = $vitals['blood_pressure_systolic'];
            $visit['blood_pressure_diastolic'] = $vitals['blood_pressure_diastolic'];
            $visit['pulse_rate'] = $vitals['pulse_rate'];
            $visit['respiratory_rate'] = $vitals['respiratory_rate'];
            $visit['oxygen_saturation'] = $vitals['oxygen_saturation'];
            $visit['blood_glucose'] = $vitals['blood_glucose'];
            $visit['weight'] = $vitals['weight'];
            $visit['height'] = $vitals['height'];
            $visit['bmi'] = $vitals['bmi'];
            $visit['muac'] = $vitals['muac'];
            $visit['pain_score'] = $vitals['pain_score'];
            $visit['vitals_recorded_at'] = $vitals['recorded_at'];
            $visit['vitals_notes'] = $vitals['notes'];
        }
    }
    unset($visit);
    
} catch (Exception $e) {
    error_log("Consultations fetch error: " . $e->getMessage());
}

// ================================================================
// CALCULATE STATS
// ================================================================
$total_visits = count($all_visits);
$total_fees = 0;
$total_paid = 0;
$total_balance = 0;
$total_discount = 0;
$total_premium = 0;
$paid_count = 0;
$pending_count = 0;
$partial_count = 0;

foreach ($all_visits as $v) {
    $total_fees += (float)($v['consultation_fee'] ?? 0);
    if ($v['bill_id']) {
        $total_paid += (float)($v['bill_paid'] ?? 0);
        $total_balance += (float)($v['bill_balance'] ?? 0);
        $total_discount += (float)($v['bill_discount'] ?? 0);
        $total_premium += (float)($v['bill_premium'] ?? 0);
        
        if ($v['bill_status'] === 'paid') $paid_count++;
        elseif ($v['bill_status'] === 'pending') $pending_count++;
        elseif ($v['bill_status'] === 'partial') $partial_count++;
    }
}

$patient_age = calculateAge($patient['date_of_birth']);
$initials = strtoupper(substr($patient['full_name'] ?? 'P', 0, 1));
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

if ($is_audit) {
    include_once __DIR__ . '/../../components/audit_header.php';
    include_once __DIR__ . '/../../components/audit_sidebar.php';
} else {
    include_once __DIR__ . '/../../../components/admin_audit_header.php';
    include_once __DIR__ . '/../../../components/admin_audit_sidebar.php';
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Consultations - <?= htmlspecialchars($patient['full_name']) ?></title>
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
    --primary-light: #60A5FA;
    --primary-bg: #1E3A5F;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3A2A1A;
    --purple-bg: #2D1B4E;
    --cyan-bg: #0E3A47;
}
* { font-family: var(--font-primary); box-sizing: border-box; }
html, body { background: var(--bg-body); color: var(--text-primary); margin: 0; }
.mono { font-family: var(--font-mono) !important; font-variant-numeric: tabular-nums; }

/* PAGE HEADER */
.page-header { background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%); border-radius: 16px; padding: 20px 24px; margin-bottom: 18px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px; box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25); position: relative; overflow: hidden; }
.page-header::before { content: ''; position: absolute; top: -50%; right: -20%; width: 350px; height: 350px; background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%); border-radius: 50%; pointer-events: none; }
.page-header .page-title { color: white; font-size: 1.4rem; font-weight: 800; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; position: relative; z-index: 1; margin: 0; }
.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }
.page-header .page-subtitle { color: rgba(255,255,255,0.85); font-size: 0.78rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px; position: relative; z-index: 1; }
.branch-tag { background: rgba(255,255,255,0.15); color: white; padding: 3px 10px; border-radius: 16px; font-size: 0.65rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; border: 1px solid rgba(255,255,255,0.1); }
.branch-tag.count-tag { background: linear-gradient(135deg, #10B981, #059669); font-weight: 700; }
.btn-header { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 8px 14px; border-radius: 9px; font-weight: 600; font-size: 0.75rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; position: relative; z-index: 1; }
.btn-header:hover { background: rgba(255,255,255,0.28); color: white; }

/* PATIENT CARD */
.patient-card { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); padding: 18px 22px; margin-bottom: 18px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; box-shadow: var(--shadow-sm); position: relative; overflow: hidden; }
.patient-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #0B5ED7, #3B82F6); }
.patient-avatar-lg { width: 72px; height: 72px; border-radius: 16px; background: linear-gradient(135deg, #0B5ED7, #3B82F6); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 900; text-transform: uppercase; flex-shrink: 0; box-shadow: 0 8px 20px rgba(11, 94, 215, 0.3); border: 3px solid var(--bg-card); }
.patient-info-main { flex: 1; min-width: 250px; }
.patient-name-lg { font-size: 1.35rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.02em; line-height: 1.2; margin-bottom: 6px; }
.patient-meta-lg { display: flex; flex-wrap: wrap; gap: 8px; }
.patient-meta-lg .meta-pill { font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 4px; background: var(--bg-body); padding: 4px 10px; border-radius: 12px; border: 1px solid var(--border-color); }
.patient-meta-lg .meta-pill i { color: var(--primary); font-size: 0.68rem; }

/* STATS GRID */
.stats-grid-5 { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 20px; }
.stat-card { border-radius: 12px; padding: 14px 16px; display: flex; align-items: center; gap: 12px; color: white; box-shadow: 0 4px 16px rgba(0,0,0,0.12); position: relative; overflow: hidden; }
.stat-card::before { content: ''; position: absolute; top: -50%; right: -20%; width: 140px; height: 140px; background: rgba(255,255,255,0.06); border-radius: 50%; }
.stat-card .stat-icon { width: 42px; height: 42px; border-radius: 10px; background: rgba(255,255,255,0.18); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.12); }
.stat-card .stat-info { flex: 1; }
.stat-card .stat-label { font-size: 0.6rem; color: rgba(255,255,255,0.85); font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; margin: 0; }
.stat-card .stat-number { font-size: 1.4rem; font-weight: 800; color: white; margin: 0; line-height: 1.1; font-family: var(--font-mono); }
.stat-card .stat-amount { font-size: 0.7rem; color: rgba(255,255,255,0.9); font-weight: 600; margin-top: 2px; }
.card-blue-1 { background: linear-gradient(135deg, #3B82F6, #0B5ED7, #0A4CA8); }
.card-green  { background: linear-gradient(135deg, #10B981, #059669, #047857); }
.card-orange { background: linear-gradient(135deg, #F59E0B, #D97706, #B45309); }
.card-purple { background: linear-gradient(135deg, #7C3AED, #6D28D9, #5B21B6); }
.card-cyan   { background: linear-gradient(135deg, #06B6D4, #0891B2, #0E7490); }

/* VISIT SECTION */
.visit-section { border: 2px solid var(--border-color); border-radius: 12px; margin-bottom: 16px; overflow: hidden; transition: all 0.3s ease; background: var(--bg-card); }
.visit-section:last-child { margin-bottom: 0; }
.visit-section:hover { border-color: var(--primary); box-shadow: var(--shadow-md); }

.visit-section-header { background: linear-gradient(135deg, #E8F0FE, #D6E4FF); padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-bottom: 2px solid var(--primary-light); }
[data-theme="dark"] .visit-section-header { background: linear-gradient(135deg, #1E3A5F, #16294A); }
.visit-info-left { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex: 1; }
.visit-icon-badge { width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #FCD34D, #F59E0B); color: #78350F; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: 0 3px 10px rgba(245, 158, 11, 0.3); flex-shrink: 0; }
.visit-number-display { font-weight: 800; font-size: 0.9rem; color: #78350F; background: rgba(255,255,255,0.6); padding: 4px 12px; border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.3); font-family: var(--font-mono); }
[data-theme="dark"] .visit-number-display { color: #FCD34D; background: rgba(255,255,255,0.1); }
.visit-date-display { font-size: 0.72rem; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 4px; font-weight: 600; }
.visit-doctor-display { font-size: 0.72rem; color: var(--primary); font-weight: 700; display: inline-flex; align-items: center; gap: 4px; background: rgba(255,255,255,0.6); padding: 3px 10px; border-radius: 20px; border: 1px solid var(--primary-light); }
[data-theme="dark"] .visit-doctor-display { background: rgba(255,255,255,0.1); color: #93C5FD; }
.visit-stats-right { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.visit-mini-stat { background: rgba(255,255,255,0.7); padding: 4px 10px; border-radius: 16px; font-size: 0.65rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
[data-theme="dark"] .visit-mini-stat { background: rgba(255,255,255,0.1); }

/* TABLE NAV */
.table-nav-group { display: inline-flex; align-items: center; gap: 2px; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); border: 1px solid rgba(255,255,255,0.3); border-radius: 8px; padding: 2px; }
.table-nav-btn { background: rgba(255,255,255,0.15); border: none; color: white; width: 28px; height: 28px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.72rem; transition: all 0.2s ease; }
.table-nav-btn:hover:not(:disabled) { background: rgba(255,255,255,0.35); transform: scale(1.1); }
.table-nav-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.table-nav-indicator { font-size: 0.6rem; font-weight: 800; font-family: var(--font-mono); color: white; padding: 0 6px; min-width: 38px; text-align: center; background: rgba(255,255,255,0.15); border-radius: 5px; height: 24px; line-height: 24px; }

/* VISIT BODY */
.visit-section-body { padding: 16px 18px; background: var(--bg-card); }

/* INFO GRID */
.info-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
.info-block { background: linear-gradient(135deg, #F8FAFC, #F1F5F9); border-radius: 10px; padding: 14px 16px; border-left: 3px solid var(--primary); }
[data-theme="dark"] .info-block { background: linear-gradient(135deg, #1E293B, #0F172A); }
.info-block .block-label { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; color: var(--primary); letter-spacing: 0.05em; margin-bottom: 6px; display: flex; align-items: center; gap: 5px; }
.info-block .block-content { font-size: 0.8rem; color: var(--text-primary); line-height: 1.6; font-weight: 500; }
.info-block .block-content.empty { color: var(--text-secondary); font-style: italic; }

/* VITALS GRID */
.vitals-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 8px; margin-bottom: 14px; }
.vital-item { background: linear-gradient(135deg, #E8F0FE, #D6E4FF); border-radius: 8px; padding: 10px 12px; display: flex; flex-direction: column; gap: 2px; border: 1px solid var(--primary-light); }
[data-theme="dark"] .vital-item { background: linear-gradient(135deg, #1E3A5F, #16294A); }
.vital-item .vital-label { font-size: 0.55rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
.vital-item .vital-value { font-size: 0.95rem; font-weight: 800; color: var(--primary); font-family: var(--font-mono); }

/* BILL BOX */
.bill-box { background: linear-gradient(135deg, #EDE9FE, #DDD6FE); border-radius: 10px; padding: 14px 16px; border-left: 4px solid var(--purple); margin-top: 14px; }
[data-theme="dark"] .bill-box { background: linear-gradient(135deg, #2D1B4E, #1E1B4B); }
.bill-box-title { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: var(--purple); letter-spacing: 0.05em; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; }
.bill-box-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 8px; }
.bill-item { background: rgba(255,255,255,0.6); padding: 8px 12px; border-radius: 8px; text-align: center; }
[data-theme="dark"] .bill-item { background: rgba(255,255,255,0.1); }
.bill-item .bi-label { font-size: 0.55rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
.bill-item .bi-value { font-size: 0.85rem; font-weight: 800; font-family: var(--font-mono); color: var(--text-primary); margin-top: 2px; }
.bill-item.paid .bi-value { color: var(--success); }
.bill-item.balance .bi-value { color: var(--danger); }
.bill-item.total .bi-value { color: var(--primary); }

/* ACTION BUTTONS */
.action-buttons-group { display: flex; gap: 4px; flex-wrap: wrap; }
.btn-action-sm { display: inline-flex; align-items: center; justify-content: center; gap: 3px; padding: 5px 10px; border-radius: 6px; font-weight: 700; font-size: 0.65rem; cursor: pointer; border: none; text-decoration: none; transition: all 0.15s ease; }
.btn-action-sm.view { background: var(--primary); color: white; }
.btn-action-sm.view:hover { background: var(--primary-dark); transform: translateY(-1px); color: white; }
.btn-action-sm.edit { background: var(--warning); color: white; }
.btn-action-sm.edit:hover { background: #B45309; transform: translateY(-1px); color: white; }

/* EMPTY STATE */
.empty-state { text-align: center; padding: 60px 20px; color: var(--text-secondary); background: var(--bg-card); border-radius: 14px; border: 2px dashed var(--border-color); }
.empty-state i { font-size: 3.5rem; color: var(--primary); display: block; margin-bottom: 12px; opacity: 0.4; }
.empty-state p { font-size: 1rem; font-weight: 700; color: var(--text-primary); }
.empty-state .sub { font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px; font-weight: 400; }

.footer { padding: 14px 0; border-top: 1px solid var(--border-color); margin-top: 24px; text-align: center; font-size: 0.7rem; color: var(--text-secondary); }
.footer .footer-brand { color: var(--primary); font-weight: 600; }

@media (max-width: 1024px) {
    .info-grid-2 { grid-template-columns: 1fr; }
    .stats-grid-5 { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .stats-grid-5 { grid-template-columns: 1fr; }
    .visit-section-header { flex-direction: column; align-items: stretch; }
}
@media print {
    .btn-header, .btn-action-sm, .table-nav-group { display: none !important; }
    .page-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; }
}
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-stethoscope"></i>
                All Consultations
                <?php if ($is_admin): ?>
                    <span class="branch-tag" style="background:linear-gradient(135deg,#FCD34D,#F59E0B);color:#78350F;font-weight:800;">
                        <i class="fas fa-crown"></i> ADMIN
                    </span>
                <?php else: ?>
                    <span class="branch-tag" style="background:linear-gradient(135deg,#F59E0B,#D97706);font-weight:700;">
                        <i class="fas fa-eye"></i> VIEW ONLY
                    </span>
                <?php endif; ?>
                <span class="branch-tag count-tag">
                    <i class="fas fa-clipboard-list"></i> <?= $total_visits ?> visit(s)
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                <strong><?= htmlspecialchars($patient['full_name']) ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id']) ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="other_services.php?tab=consultations&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- PATIENT CARD -->
    <div class="patient-card">
        <div class="patient-avatar-lg"><?= $initials ?></div>
        <div class="patient-info-main">
            <div class="patient-name-lg"><?= htmlspecialchars($patient['full_name']) ?></div>
            <div class="patient-meta-lg">
                <span class="meta-pill"><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id']) ?></span>
                <?php if (!empty($patient['gender'])): ?>
                    <span class="meta-pill"><i class="fas fa-<?= strtolower($patient['gender']) === 'female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($patient['gender']) ?></span>
                <?php endif; ?>
                <?php if ($patient_age !== 'N/A'): ?>
                    <span class="meta-pill"><i class="fas fa-birthday-cake"></i> <?= $patient_age ?></span>
                <?php endif; ?>
                <?php if (!empty($patient['phone'])): ?>
                    <span class="meta-pill"><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-grid-5">
        <div class="stat-card card-blue-1">
            <div class="stat-icon"><i class="fas fa-stethoscope"></i></div>
            <div class="stat-info">
                <p class="stat-label">Total Visits</p>
                <p class="stat-number"><?= $total_visits ?></p>
                <p class="stat-amount"><?= formatTsh($total_fees) ?></p>
            </div>
        </div>
        <div class="stat-card card-green">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <p class="stat-label">Paid (<?= $paid_count ?>)</p>
                <p class="stat-number"><?= formatTsh($total_paid) ?></p>
                <p class="stat-amount">Completed</p>
            </div>
        </div>
        <div class="stat-card card-orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <p class="stat-label">Pending (<?= $pending_count ?>)</p>
                <p class="stat-number"><?= formatTsh($total_balance) ?></p>
                <p class="stat-amount">Unpaid</p>
            </div>
        </div>
        <div class="stat-card card-purple">
            <div class="stat-icon"><i class="fas fa-star"></i></div>
            <div class="stat-info">
                <p class="stat-label">Premium</p>
                <p class="stat-number"><?= formatTsh($total_premium) ?></p>
                <p class="stat-amount">Total Premium</p>
            </div>
        </div>
        <div class="stat-card card-cyan">
            <div class="stat-icon"><i class="fas fa-percent"></i></div>
            <div class="stat-info">
                <p class="stat-label">Discount</p>
                <p class="stat-number"><?= formatTsh($total_discount) ?></p>
                <p class="stat-amount">Total Discounts</p>
            </div>
        </div>
    </div>

    <!-- CONSULTATIONS -->
    <?php if (!empty($all_visits)): ?>
        <?php foreach ($all_visits as $idx => $v): 
            $uid = $patient_id . '-v' . $v['id'];
            $visit_status = getStatusBadge($v['status']);
            $bill_status = getStatusBadge($v['bill_status'] ?? 'pending');
            
            // ✅ VITALS - Chukua kutoka $v['vitals']
            $vitals = $v['vitals'] ?? [];
            $has_vitals = !empty($vitals['temperature']) 
                       || !empty($vitals['blood_pressure_systolic']) 
                       || !empty($vitals['pulse_rate']) 
                       || !empty($vitals['oxygen_saturation'])
                       || !empty($vitals['weight']) 
                       || !empty($vitals['bmi'])
                       || !empty($vitals['blood_glucose']);
        ?>
            <div class="visit-section">
                <div class="visit-section-header">
                    <div class="visit-info-left">
                        <div class="visit-icon-badge"><i class="fas fa-stethoscope"></i></div>
                        <span class="visit-number-display"><?= htmlspecialchars($v['visit_number']) ?></span>
                        <span class="visit-date-display">
                            <i class="fas fa-calendar-day"></i>
                            <?= date('d M Y', strtotime($v['visit_date'])) ?>
                        </span>
                        <?php if (!empty($v['doctor_name'])): ?>
                            <span class="visit-doctor-display">
                                <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($v['doctor_name']) ?>
                            </span>
                        <?php endif; ?>
                        <span class="status-badge <?= $visit_status['class'] ?>" style="font-size:0.6rem;">
                            <?= $visit_status['icon'] ?> <?= $visit_status['label'] ?>
                        </span>
                    </div>
                    <div class="visit-stats-right">
                        <span class="visit-mini-stat">
                            <i class="fas fa-money-bill-wave"></i>
                            <strong style="color:var(--primary);"><?= formatTsh($v['consultation_fee'] ?? 0) ?></strong>
                        </span>
                        <?php if ($v['bill_id']): ?>
                            <span class="status-badge <?= $bill_status['class'] ?>" style="font-size:0.6rem;">
                                <?= $bill_status['icon'] ?> <?= $bill_status['label'] ?>
                            </span>
                        <?php endif; ?>
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
                
                <div class="visit-section-body">
                    
                    <!-- ✅ VITALS - Kila visit inaonyesha vitals zake -->
                    <?php if ($has_vitals): ?>
                        <div style="margin-bottom:14px;">
                            <div style="font-size:0.6rem;font-weight:800;text-transform:uppercase;color:var(--primary);letter-spacing:0.05em;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                                <i class="fas fa-heartbeat"></i> Vital Signs
                                <?php if (!empty($vitals['recorded_at'])): ?>
                                    <span style="font-size:0.55rem;color:var(--text-secondary);font-weight:500;text-transform:none;letter-spacing:0;">
                                        (<?= date('d M Y, H:i', strtotime($vitals['recorded_at'])) ?>)
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="vitals-grid">
                                <?php if (!empty($vitals['temperature'])): ?>
                                    <div class="vital-item">
                                        <span class="vital-label"><i class="fas fa-thermometer-half"></i> Temp</span>
                                        <span class="vital-value"><?= $vitals['temperature'] ?> °C</span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($vitals['blood_pressure_systolic']) && !empty($vitals['blood_pressure_diastolic'])): ?>
                                    <div class="vital-item">
                                        <span class="vital-label"><i class="fas fa-heart"></i> BP</span>
                                        <span class="vital-value"><?= $vitals['blood_pressure_systolic'] ?>/<?= $vitals['blood_pressure_diastolic'] ?> mmHg</span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($vitals['pulse_rate'])): ?>
                                    <div class="vital-item">
                                        <span class="vital-label"><i class="fas fa-heartbeat"></i> Pulse</span>
                                        <span class="vital-value"><?= $vitals['pulse_rate'] ?> bpm</span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($vitals['respiratory_rate'])): ?>
                                    <div class="vital-item">
                                        <span class="vital-label"><i class="fas fa-wind"></i> Resp</span>
                                        <span class="vital-value"><?= $vitals['respiratory_rate'] ?> /min</span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($vitals['oxygen_saturation'])): ?>
                                    <div class="vital-item">
                                        <span class="vital-label"><i class="fas fa-lungs"></i> SpO₂</span>
                                        <span class="vital-value"><?= $vitals['oxygen_saturation'] ?> %</span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($vitals['blood_glucose'])): ?>
                                    <div class="vital-item">
                                        <span class="vital-label"><i class="fas fa-tint"></i> Glucose</span>
                                        <span class="vital-value"><?= $vitals['blood_glucose'] ?> mg/dL</span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($vitals['weight'])): ?>
                                    <div class="vital-item">
                                        <span class="vital-label"><i class="fas fa-weight"></i> Weight</span>
                                        <span class="vital-value"><?= $vitals['weight'] ?> kg</span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($vitals['height'])): ?>
                                    <div class="vital-item">
                                        <span class="vital-label"><i class="fas fa-ruler-vertical"></i> Height</span>
                                        <span class="vital-value"><?= $vitals['height'] ?> cm</span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($vitals['bmi'])): ?>
                                    <div class="vital-item">
                                        <span class="vital-label"><i class="fas fa-calculator"></i> BMI</span>
                                        <span class="vital-value"><?= $vitals['bmi'] ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($vitals['muac'])): ?>
                                    <div class="vital-item">
                                        <span class="vital-label"><i class="fas fa-child"></i> MUAC</span>
                                        <span class="vital-value"><?= $vitals['muac'] ?> cm</span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($vitals['pain_score'])): ?>
                                    <div class="vital-item">
                                        <span class="vital-label"><i class="fas fa-frown"></i> Pain</span>
                                        <span class="vital-value"><?= $vitals['pain_score'] ?>/10</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($vitals['notes'])): ?>
                                <div style="margin-top:8px;padding:8px 12px;background:linear-gradient(135deg,#FFFBEB,#FEF3C7);border-left:3px solid var(--warning);border-radius:6px;font-size:0.72rem;color:#78350F;">
                                    <i class="fas fa-sticky-note"></i> <?= htmlspecialchars($vitals['notes']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- CLINICAL INFO GRID -->
                    <div class="info-grid-2">
                        <div class="info-block">
                            <div class="block-label">
                                <i class="fas fa-comment-medical"></i> Symptoms / Complaint
                            </div>
                            <div class="block-content <?= empty($v['symptoms']) ? 'empty' : '' ?>">
                                <?= !empty($v['symptoms']) ? nl2br(htmlspecialchars($v['symptoms'])) : 'No symptoms recorded' ?>
                            </div>
                        </div>
                        <div class="info-block">
                            <div class="block-label">
                                <i class="fas fa-diagnoses"></i> Diagnosis
                            </div>
                            <div class="block-content <?= empty($v['diagnosis']) ? 'empty' : '' ?>">
                                <?= !empty($v['diagnosis']) ? nl2br(htmlspecialchars($v['diagnosis'])) : 'No diagnosis recorded' ?>
                                <?php if (!empty($v['disease_name'])): ?>
                                    <div style="margin-top:4px;font-size:0.7rem;color:var(--purple);font-weight:700;">
                                        <i class="fas fa-virus"></i> <?= htmlspecialchars($v['disease_name']) ?>
                                        <?php if (!empty($v['disease_code'])): ?>
                                            (<?= htmlspecialchars($v['disease_code']) ?>)
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($v['treatment'])): ?>
                        <div class="info-block" style="margin-bottom:14px;">
                            <div class="block-label">
                                <i class="fas fa-prescription"></i> Treatment
                            </div>
                            <div class="block-content">
                                <?= nl2br(htmlspecialchars($v['treatment'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- BILL INFO -->
                    <?php if ($v['bill_id']): ?>
                        <div class="bill-box">
                            <div class="bill-box-title">
                                <span>
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    <?= htmlspecialchars($v['bill_number']) ?>
                                </span>
                                <a href="view_bill.php?id=<?= $v['bill_id'] ?>&branch=<?= $selected_branch_id ?>" 
                                   class="btn-action-sm view" style="text-decoration:none;">
                                    <i class="fas fa-eye"></i> View Bill
                                </a>
                            </div>
                            <div class="bill-box-grid">
                                <div class="bill-item total">
                                    <div class="bi-label">Total</div>
                                    <div class="bi-value"><?= formatTsh($v['bill_total'] ?? 0) ?></div>
                                </div>
                                <div class="bill-item paid">
                                    <div class="bi-label">Paid</div>
                                    <div class="bi-value"><?= formatTsh($v['bill_paid'] ?? 0) ?></div>
                                </div>
                                <div class="bill-item balance">
                                    <div class="bi-label">Balance</div>
                                    <div class="bi-value"><?= formatTsh($v['bill_balance'] ?? 0) ?></div>
                                </div>
                                <?php if (($v['bill_discount'] ?? 0) > 0): ?>
                                    <div class="bill-item">
                                        <div class="bi-label">Discount</div>
                                        <div class="bi-value" style="color:var(--cyan);">-<?= formatTsh($v['bill_discount']) ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if (($v['bill_premium'] ?? 0) > 0): ?>
                                    <div class="bill-item">
                                        <div class="bi-label">Premium</div>
                                        <div class="bi-value" style="color:var(--purple);">+<?= formatTsh($v['bill_premium']) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- ACTION BUTTONS -->
                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;flex-wrap:wrap;">
                        <a href="view_consultation.php?id=<?= $v['id'] ?>&branch=<?= $selected_branch_id ?>" 
                           class="btn-action-sm view">
                            <i class="fas fa-eye"></i> View Full
                        </a>
                        <?php if ($is_admin): ?>
                            <a href="edit_consultation.php?id=<?= $v['id'] ?>&branch=<?= $selected_branch_id ?>" 
                               class="btn-action-sm edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        <?php endif; ?>
                    </div>
                    
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-stethoscope"></i>
            <p>No consultations found</p>
            <p class="sub">This patient has no consultations recorded yet</p>
        </div>
    <?php endif; ?>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            All Consultations
            <span style="margin:0 8px;">|</span>
            <span><?= date('d M Y, H:i:s') ?></span>
        </p>
    </footer>

</main>

<script>
// ================================================================
// TABLE NAV SCROLL
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
    
    var section = table.closest('.visit-section');
    if (section) {
        var navGroup = section.querySelector('.visit-section-header .table-nav-group');
        if (navGroup) {
            var btns = navGroup.querySelectorAll('.table-nav-btn');
            if (btns.length >= 2) {
                btns[0].disabled = (currentScroll <= 1);
                btns[1].disabled = (currentScroll >= maxScroll - 1);
            }
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.table-scroll').forEach(function(tbl) {
        if (!tbl.id) return;
        tbl.addEventListener('scroll', function() { updateTableNav(tbl.id); });
        updateTableNav(tbl.id);
    });
});

console.log('%c🩺 All Consultations', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name']) ?>', 'font-size:13px;color:#34D399;font-weight:bold;');
console.log('%c📋 Total Visits: <?= $total_visits ?>', 'font-size:13px;color:#34D399;');
console.log('%c💰 Total Fees: <?= $currency ?> <?= number_format($total_fees, 0) ?>', 'font-size:13px;color:#0B5ED7;font-weight:bold;');
console.log('%c✅ Total Paid: <?= $currency ?> <?= number_format($total_paid, 0) ?>', 'font-size:13px;color:#10B981;');
console.log('%c⏳ Total Balance: <?= $currency ?> <?= number_format($total_balance, 0) ?>', 'font-size:13px;color:#DC2626;');
</script>

</body>
</html>