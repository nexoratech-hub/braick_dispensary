<?php
// ================================================================
// FILE: frontend/pages/audit/view_consultation.php
// AUDIT - VIEW CONSULTATION DETAILS (V3 - BRANCH LOCKED CLEAN)
// ✅ AUDIT ANAONA CONSULTATION ZA BRANCH YAKE TU
// ✅ ONDOA VIEW ONLY notice na badges
// ✅ Inatumia audit_header.php + audit_sidebar.php
// ✅ AUDIT ROLE TU
// ✅ HAKUNA Edit button - VIEW ONLY
// ================================================================

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ✅ AUDIT ROLE TU
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
$is_audit = true;

$visit_id = (int)($_GET['id'] ?? 0);

// ✅ AUDIT ANAONA BRANCH YAKE TU
$selected_branch_id = $user_branch_id;

if ($visit_id <= 0) {
    header('Location: other_services.php?tab=consultations');
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

// FETCH CONSULTATION - ✅ BRANCH YA ALIYE LOGIN TU
$visit = null;
try {
    $stmt = $db->prepare("
        SELECT v.*,
               p.id as patient_db_id, p.full_name as patient_name, 
               p.patient_id as patient_number, p.phone as patient_phone,
               p.gender as patient_gender, p.date_of_birth, p.address as patient_address,
               p.blood_group, p.allergies, p.email as patient_email,
               d.full_name as doctor_name, d.specialty as doctor_specialty,
               r.full_name as receptionist_name,
               br.name as branch_name,
               dis.disease_name, dis.disease_code
        FROM visits v
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users d ON v.doctor_id = d.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        LEFT JOIN branches br ON v.branch_id = br.id
        LEFT JOIN diseases dis ON v.disease_id = dis.id
        WHERE v.id = ? AND v.branch_id = ?
        LIMIT 1
    ");
    $stmt->execute([$visit_id, $user_branch_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Visit fetch error: " . $e->getMessage());
}

if (!$visit) {
    $_SESSION['error_message'] = "Consultation not found or you don't have permission to view this consultation (different branch).";
    header('Location: other_services.php?tab=consultations');
    exit;
}

$patient_db_id = $visit['patient_db_id'];

// FETCH BILL + PAYMENTS
$bill = null;
$payments = [];
try {
    $stmt = $db->prepare("
        SELECT b.*,
               (SELECT COUNT(*) FROM payments WHERE bill_id = b.id) as payments_count
        FROM bills b
        WHERE b.visit_id = ? AND b.branch_id = ?
        ORDER BY b.id DESC
        LIMIT 1
    ");
    $stmt->execute([$visit_id, $user_branch_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bill) {
        $stmt = $db->prepare("
            SELECT p.*, u.full_name as received_by_name, u.role as received_by_role
            FROM payments p
            LEFT JOIN users u ON p.received_by = u.id
            WHERE p.bill_id = ?
            ORDER BY p.received_at DESC
        ");
        $stmt->execute([$bill['id']]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

// FETCH BILL ITEMS
$bill_items = [];
if ($bill) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM bill_items
            WHERE bill_id = ?
            ORDER BY 
                CASE item_type 
                    WHEN 'consultation' THEN 1
                    WHEN 'lab_test' THEN 2
                    WHEN 'medication' THEN 3
                    WHEN 'procedure' THEN 4
                    WHEN 'equipment' THEN 5
                    ELSE 6
                END, id ASC
        ");
        $stmt->execute([$bill['id']]);
        $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// FETCH LAB TESTS - ✅ BRANCH YA ALIYE LOGIN TU
$lab_tests = [];
try {
    $stmt = $db->prepare("
        SELECT lt.*, 
               tech.full_name as technician_name,
               doc.full_name as doctor_name,
               ltc.test_name as catalog_name,
               ltc.category as test_category
        FROM lab_tests lt
        LEFT JOIN users tech ON lt.lab_technician_id = tech.id
        LEFT JOIN users doc ON lt.doctor_id = doc.id
        LEFT JOIN lab_tests_catalog ltc ON lt.test_id = ltc.id
        WHERE lt.visit_id = ? AND lt.branch_id = ?
        ORDER BY lt.created_at ASC
    ");
    $stmt->execute([$visit_id, $user_branch_id]);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// FETCH PRESCRIPTIONS - ✅ BRANCH YA ALIYE LOGIN TU
$prescriptions = [];
try {
    $stmt = $db->prepare("
        SELECT pr.*, 
               doc.full_name as doctor_name,
               pharm.full_name as pharmacy_name
        FROM prescriptions pr
        LEFT JOIN users doc ON pr.doctor_id = doc.id
        LEFT JOIN users pharm ON pr.pharmacy_id = pharm.id
        WHERE pr.visit_id = ? AND pr.branch_id = ?
        ORDER BY pr.created_at ASC
    ");
    $stmt->execute([$visit_id, $user_branch_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($prescriptions as &$presc) {
        $stmt = $db->prepare("
            SELECT pi.*, 
                   u.full_name as dispensed_by_name,
                   mi.medication_name as inventory_name,
                   mi.batch_number
            FROM prescription_items pi
            LEFT JOIN users u ON pi.dispensed_by = u.id
            LEFT JOIN medications_inventory mi ON pi.inventory_id = mi.id
            WHERE pi.prescription_id = ?
            ORDER BY pi.id ASC
        ");
        $stmt->execute([$presc['id']]);
        $presc['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($presc);
} catch (Exception $e) {}

// FETCH PROCEDURES - ✅ BRANCH YA ALIYE LOGIN TU
$procedures = [];
try {
    $stmt = $db->prepare("
        SELECT pr.*, 
               doc.full_name as doctor_name,
               pc.category as procedure_category
        FROM procedures pr
        LEFT JOIN users doc ON pr.doctor_id = doc.id
        LEFT JOIN procedures_catalog pc ON pr.procedure_id = pc.id
        WHERE pr.visit_id = ? AND pr.branch_id = ?
        ORDER BY pr.created_at ASC
    ");
    $stmt->execute([$visit_id, $user_branch_id]);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// FETCH VITALS
$vitals = null;
try {
    $stmt = $db->prepare("
        SELECT vs.*, u.full_name as recorded_by_name
        FROM vital_signs vs
        LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE vs.visit_id = ?
        ORDER BY vs.recorded_at DESC
        LIMIT 1
    ");
    $stmt->execute([$visit_id]);
    $vitals = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$status_info = getStatusBadge($visit['status']);
$payment_info = getStatusBadge($visit['payment_status']);
$patient_age = calculateAge($visit['date_of_birth']);

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ✅ AUDIT HEADER + SIDEBAR
include_once __DIR__ . '/../../components/audit_header.php';
include_once __DIR__ . '/../../components/audit_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Consultation - <?= htmlspecialchars($visit['visit_number']) ?></title>
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
.page-header .page-title { color: white; font-size: 1.4rem; font-weight: 800; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; position: relative; z-index: 1; letter-spacing: -0.02em; margin: 0; }
.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }
.page-header .page-subtitle { color: rgba(255,255,255,0.85); font-size: 0.78rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px; position: relative; z-index: 1; }
.branch-tag { background: rgba(255,255,255,0.15); color: white; padding: 3px 10px; border-radius: 16px; font-size: 0.65rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1); }
.branch-tag.audit-tag { background: linear-gradient(135deg, #0EA5E9, #0284C7); font-weight: 800; }
.branch-tag.count-tag { background: linear-gradient(135deg, #10B981, #059669); font-weight: 700; }
.btn-header { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 8px 14px; border-radius: 9px; font-weight: 600; font-size: 0.75rem; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(4px); position: relative; z-index: 1; cursor: pointer; }
.btn-header:hover { background: rgba(255,255,255,0.28); transform: translateY(-2px); color: white; }

/* STATUS BANNER */
.status-banner { border-radius: 14px; padding: 16px 22px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; position: relative; overflow: hidden; box-shadow: var(--shadow-md); }
.status-banner.pending { background: linear-gradient(135deg, #D97706, #B45309); color: white; }
.status-banner.waiting { background: linear-gradient(135deg, #F59E0B, #D97706); color: white; }
.status-banner.assigned { background: linear-gradient(135deg, #0EA5E9, #0284C7); color: white; }
.status-banner.with_doctor { background: linear-gradient(135deg, #0891B2, #0E7490); color: white; }
.status-banner.lab_test { background: linear-gradient(135deg, #7C3AED, #6D28D9); color: white; }
.status-banner.lab_completed { background: linear-gradient(135deg, #059669, #047857); color: white; }
.status-banner.prescribed { background: linear-gradient(135deg, #0891B2, #0E7490); color: white; }
.status-banner.completed { background: linear-gradient(135deg, #059669, #047857); color: white; }
.status-banner.cancelled { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; }
.status-banner .status-left { display: flex; align-items: center; gap: 16px; position: relative; z-index: 1; }
.status-banner .status-icon { width: 56px; height: 56px; border-radius: 14px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; border: 2px solid rgba(255,255,255,0.3); backdrop-filter: blur(4px); }
.status-banner .status-info .status-label { font-size: 1.35rem; font-weight: 900; letter-spacing: -0.02em; line-height: 1.1; }
.status-banner .status-info .status-sub { font-size: 0.75rem; opacity: 0.9; font-weight: 500; margin-top: 4px; }
.status-banner .status-amount { text-align: right; position: relative; z-index: 1; }
.status-banner .status-amount .label { font-size: 0.65rem; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; margin-bottom: 4px; }
.status-banner .status-amount .value { font-size: 1.75rem; font-weight: 900; font-family: var(--font-mono); letter-spacing: -0.03em; line-height: 1; }
.status-banner .status-amount .currency-prefix { font-size: 0.95rem; opacity: 0.9; font-family: var(--font-primary); font-weight: 700; }

.view-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px; }

/* INFO CARDS */
.info-card { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); transition: all 0.3s ease; margin-bottom: 16px; }
.info-card:hover { box-shadow: var(--shadow-md); border-color: var(--primary); }
.info-card.blue-theme { border-color: var(--primary); }
.info-card.blue-theme .card-header { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border-bottom-color: transparent; }
.info-card.blue-theme .card-header i { color: #93C5FD; }
.info-card .card-header { padding: 14px 18px; background: var(--primary-bg); border-bottom: 2px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 0.85rem; font-weight: 800; color: var(--text-primary); }
.info-card .card-header .title { display: flex; align-items: center; gap: 8px; }
.info-card .card-header i { color: var(--primary); font-size: 0.95rem; }
.info-card .card-body { padding: 18px 20px; }

.info-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border-color); font-size: 0.82rem; }
.info-row:last-child { border-bottom: none; padding-bottom: 0; }
.info-row:first-child { padding-top: 0; }
.info-row .label { color: var(--text-secondary); font-weight: 600; font-size: 0.72rem; display: flex; align-items: center; gap: 6px; flex-shrink: 0; min-width: 120px; }
.info-row .label i { font-size: 0.7rem; opacity: 0.8; color: var(--primary); }
.info-row .value { color: var(--text-primary); font-weight: 700; text-align: right; word-break: break-word; flex: 1; }
.info-row .value.mono { font-family: var(--font-mono); font-size: 0.8rem; letter-spacing: -0.02em; }

.profile-header { display: flex; align-items: center; gap: 14px; padding: 16px 18px; background: linear-gradient(135deg, var(--primary-bg), var(--primary-bg)); border-bottom: 2px solid var(--border-color); }
.profile-avatar { width: 56px; height: 56px; border-radius: 14px; background: linear-gradient(135deg, #0B5ED7, #3B82F6); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 900; text-transform: uppercase; flex-shrink: 0; box-shadow: 0 6px 16px rgba(11, 94, 215, 0.3); border: 3px solid var(--bg-card); }
.profile-name { font-size: 1.1rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.02em; line-height: 1.2; margin-bottom: 4px; }
.profile-meta { display: flex; flex-wrap: wrap; gap: 8px; }
.profile-meta-item { font-size: 0.68rem; font-weight: 600; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 4px; background: var(--bg-card); padding: 3px 10px; border-radius: 12px; border: 1px solid var(--border-color); }
.profile-meta-item i { color: var(--primary); font-size: 0.65rem; }

.status-badge { padding: 4px 12px; border-radius: 18px; font-size: 0.68rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
.status-badge.warning { background: #FEF3C7; color: #D97706; }
.status-badge.success { background: #D1FAE5; color: #059669; }
.status-badge.danger  { background: #FEE2E2; color: #EF4444; }
.status-badge.info    { background: #E8F0FE; color: #0B5ED7; }
.status-badge.purple  { background: #EDE9FE; color: #7C3AED; }
.status-badge.cyan    { background: #CFFAFE; color: #0891B2; }

/* VITALS GRID */
.vitals-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; }
.vital-item { background: linear-gradient(135deg, var(--primary-bg), var(--bg-card)); border-radius: 10px; padding: 12px 14px; border: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 2px; }
.vital-item .vital-label { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
.vital-item .vital-value { font-size: 1.05rem; font-weight: 800; color: var(--primary); font-family: var(--font-mono); }

/* TABLE */
.data-table-wrap { overflow-x: auto; border-radius: 10px; }
.data-table-wrap::-webkit-scrollbar { height: 8px; }
.data-table-wrap::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 10px; }
.data-table-wrap::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; min-width: 800px; }
.data-table thead th { text-align: left; padding: 10px 12px; font-weight: 800; font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.06em; color: white; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); white-space: nowrap; }
.data-table tbody td { padding: 10px 12px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); vertical-align: middle; }
.data-table tbody tr:hover td { background: var(--primary-bg); }
.data-table tbody tr:last-child td { border-bottom: none; }

.amount-cell { font-family: var(--font-mono); font-weight: 800; color: var(--primary); font-size: 0.82rem; text-align: right; white-space: nowrap; }
.amount-cell.green { color: var(--success); }
.amount-cell.red { color: var(--danger); }

.received-by-cell { display: flex; align-items: center; gap: 8px; }
.received-by-avatar { width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #059669, #34D399); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.6rem; text-transform: uppercase; flex-shrink: 0; font-family: var(--font-mono); }
.received-by-name { font-size: 0.75rem; font-weight: 700; color: var(--text-primary); }
.received-by-meta { font-size: 0.58rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); }

/* BILL SUMMARY */
.bill-summary-box { background: linear-gradient(135deg, #EDE9FE, #DDD6FE); border-radius: 12px; padding: 16px 20px; border-left: 4px solid var(--purple); }
[data-theme="dark"] .bill-summary-box { background: linear-gradient(135deg, #2D1B4E, #1E1B4B); }
.bill-summary-title { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--purple); letter-spacing: 0.05em; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }
.bill-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; }
.bill-summary-item { background: rgba(255,255,255,0.6); padding: 10px 14px; border-radius: 8px; text-align: center; }
[data-theme="dark"] .bill-summary-item { background: rgba(255,255,255,0.1); }
.bill-summary-item .bs-label { font-size: 0.58rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
.bill-summary-item .bs-value { font-size: 0.95rem; font-weight: 800; font-family: var(--font-mono); color: var(--text-primary); margin-top: 2px; }
.bill-summary-item.paid .bs-value { color: var(--success); }
.bill-summary-item.balance .bs-value { color: var(--danger); }
.bill-summary-item.total .bs-value { color: var(--primary); }
.bill-summary-item.discount .bs-value { color: var(--cyan); }
.bill-summary-item.premium .bs-value { color: var(--purple); }

/* ACTION BAR */
.action-bar { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); padding: 16px 20px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; box-shadow: var(--shadow-sm); position: sticky; bottom: 20px; z-index: 10; }
.action-bar .action-info { display: flex; align-items: center; gap: 12px; }
.action-bar .action-info .info-icon { width: 42px; height: 42px; border-radius: 12px; background: var(--primary-bg); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
.action-bar .action-info .info-text .info-title { font-size: 0.85rem; font-weight: 800; color: var(--text-primary); }
.action-bar .action-info .info-text .info-sub { font-size: 0.7rem; color: var(--text-secondary); font-weight: 500; }
.action-bar .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
.btn { padding: 10px 20px; border-radius: 10px; font-weight: 700; font-size: 0.8rem; border: none; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 7px; text-decoration: none; white-space: nowrap; }
.btn:hover { transform: translateY(-2px); }
.btn-primary { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
.btn-primary:hover { color: white; }
.btn-secondary { background: var(--bg-card); color: var(--text-primary); border: 2px solid var(--border-color); }
.btn-secondary:hover { border-color: var(--primary); color: var(--primary); }

/* EMPTY */
.empty-section { text-align: center; padding: 30px 20px; color: var(--text-secondary); font-size: 0.8rem; }
.empty-section i { font-size: 2.5rem; opacity: 0.3; display: block; margin-bottom: 8px; }

.footer { padding: 14px 0; border-top: 1px solid var(--border-color); margin-top: 24px; text-align: center; font-size: 0.7rem; color: var(--text-secondary); }
.footer .footer-brand { color: var(--primary); font-weight: 600; }

@media (max-width: 1024px) { .view-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .status-banner { padding: 14px 16px; }
    .status-banner .status-amount .value { font-size: 1.35rem; }
    .status-banner .status-icon { width: 44px; height: 44px; font-size: 1.2rem; }
    .status-banner .status-info .status-label { font-size: 1.1rem; }
    .action-bar { padding: 12px 14px; flex-direction: column; align-items: stretch; }
    .action-bar .action-buttons { justify-content: center; }
    .btn { padding: 8px 14px; font-size: 0.75rem; }
}
@media print {
    .action-bar, .btn-header { display: none !important; }
    .page-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; }
    .status-banner { -webkit-print-color-adjust: exact; }
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
                Consultation Details
                <span class="branch-tag audit-tag"><i class="fas fa-shield-alt"></i> AUDIT</span>
                <span class="branch-tag count-tag">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($visit['visit_number']) ?>
                </span>
                <span class="branch-tag"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($visit['branch_name'] ?? $user_branch_name) ?></span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-calendar"></i>
                <?= date('d M Y, H:i', strtotime($visit['visit_date'])) ?>
                <?php if (!empty($visit['visit_type'])): ?>
                    <span class="branch-tag">
                        <i class="fas fa-notes-medical"></i> <?= htmlspecialchars($visit['visit_type']) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="other_services.php?tab=consultations" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- STATUS BANNER -->
    <div class="status-banner <?= htmlspecialchars($visit['status']) ?>">
        <div class="status-left">
            <div class="status-icon">
                <i class="fas fa-<?= $visit['status'] === 'completed' ? 'check-double' : ($visit['status'] === 'cancelled' ? 'times-circle' : ($visit['status'] === 'waiting' ? 'hourglass-half' : 'stethoscope')) ?>"></i>
            </div>
            <div class="status-info">
                <div class="status-label"><?= strtoupper($status_info['label']) ?></div>
                <div class="status-sub">
                    <i class="fas fa-stethoscope"></i>
                    <?= htmlspecialchars($visit['visit_type'] ?: 'Consultation') ?>
                    • <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'Not assigned') ?>
                    • <i class="fas fa-money-bill-wave"></i> <?= htmlspecialchars(ucfirst($visit['payment_status'])) ?>
                </div>
            </div>
        </div>
        <div class="status-amount">
            <div class="label">Consultation Fee</div>
            <div class="value">
                <span class="currency-prefix"><?= $currency ?></span>
                <?= number_format($visit['consultation_fee'] ?? 0, 0) ?>
            </div>
        </div>
    </div>

    <!-- PATIENT + DOCTOR INFO -->
    <div class="view-grid">
        
        <!-- PATIENT -->
        <div class="info-card blue-theme">
            <div class="card-header">
                <span class="title"><i class="fas fa-user"></i> Patient Information</span>
            </div>
            <div class="profile-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($visit['patient_name'] ?? 'U', 0, 1)) ?>
                </div>
                <div style="flex:1;">
                    <div class="profile-name"><?= htmlspecialchars($visit['patient_name'] ?? 'Unknown') ?></div>
                    <div class="profile-meta">
                        <?php if (!empty($visit['patient_number'])): ?>
                            <span class="profile-meta-item">
                                <i class="fas fa-id-card"></i> <?= htmlspecialchars($visit['patient_number']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($visit['patient_gender'])): ?>
                            <span class="profile-meta-item">
                                <i class="fas fa-<?= strtolower($visit['patient_gender']) === 'female' ? 'venus' : 'mars' ?>"></i>
                                <?= htmlspecialchars($visit['patient_gender']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($patient_age !== 'N/A'): ?>
                            <span class="profile-meta-item">
                                <i class="fas fa-birthday-cake"></i> <?= $patient_age ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="value mono"><?= htmlspecialchars($visit['patient_phone'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($visit['patient_email'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-envelope"></i> Email</span>
                    <span class="value mono"><?= htmlspecialchars($visit['patient_email']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($visit['patient_address'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-map-marker-alt"></i> Address</span>
                    <span class="value"><?= htmlspecialchars($visit['patient_address']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($visit['blood_group'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-tint"></i> Blood Group</span>
                    <span class="value" style="color:var(--danger);"><?= htmlspecialchars($visit['blood_group']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($visit['allergies'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-exclamation-triangle"></i> Allergies</span>
                    <span class="value" style="color:var(--danger);"><?= htmlspecialchars($visit['allergies']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- DOCTOR -->
        <div class="info-card blue-theme">
            <div class="card-header">
                <span class="title"><i class="fas fa-user-md"></i> Doctor Information</span>
            </div>
            <div class="profile-header">
                <div class="profile-avatar" style="background:linear-gradient(135deg,#059669,#34D399);">
                    <?= strtoupper(substr($visit['doctor_name'] ?? 'D', 0, 1)) ?>
                </div>
                <div style="flex:1;">
                    <div class="profile-name">Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'Not Assigned') ?></div>
                    <div class="profile-meta">
                        <?php if (!empty($visit['doctor_specialty'])): ?>
                            <span class="profile-meta-item">
                                <i class="fas fa-briefcase-medical"></i> <?= htmlspecialchars($visit['doctor_specialty']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-user-tie"></i> Receptionist</span>
                    <span class="value"><?= htmlspecialchars($visit['receptionist_name'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($visit['disease_name'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-notes-medical"></i> Disease</span>
                    <span class="value">
                        <?= htmlspecialchars($visit['disease_name']) ?>
                        <?php if (!empty($visit['disease_code'])): ?>
                            <span style="font-family:var(--font-mono);font-size:0.7rem;color:var(--text-secondary);">
                                (<?= htmlspecialchars($visit['disease_code']) ?>)
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-calendar-check"></i> Visit Date</span>
                    <span class="value mono"><?= date('d M Y, H:i', strtotime($visit['visit_date'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-info-circle"></i> Visit Status</span>
                    <span class="value">
                        <span class="status-badge <?= $status_info['class'] ?>">
                            <?= $status_info['icon'] ?> <?= $status_info['label'] ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-credit-card"></i> Payment</span>
                    <span class="value">
                        <span class="status-badge <?= $payment_info['class'] ?>">
                            <?= $payment_info['icon'] ?> <?= $payment_info['label'] ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

    </div>

    <!-- CHIEF COMPLAINT + DIAGNOSIS -->
    <?php if (!empty($visit['symptoms']) || !empty($visit['hpi']) || !empty($visit['physical_exam']) || !empty($visit['diagnosis']) || !empty($visit['treatment']) || !empty($visit['notes'])): ?>
    <div class="info-card">
        <div class="card-header">
            <span class="title"><i class="fas fa-notes-medical"></i> Clinical Notes</span>
        </div>
        <div class="card-body">
            <?php if (!empty($visit['symptoms'])): ?>
            <div class="info-row">
                <span class="label"><i class="fas fa-comment-medical"></i> Symptoms</span>
                <span class="value" style="text-align:left;font-weight:600;"><?= nl2br(htmlspecialchars($visit['symptoms'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($visit['hpi'])): ?>
            <div class="info-row">
                <span class="label"><i class="fas fa-history"></i> HPI</span>
                <span class="value" style="text-align:left;font-weight:600;"><?= nl2br(htmlspecialchars($visit['hpi'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($visit['physical_exam'])): ?>
            <div class="info-row">
                <span class="label"><i class="fas fa-stethoscope"></i> Physical Exam</span>
                <span class="value" style="text-align:left;font-weight:600;"><?= nl2br(htmlspecialchars($visit['physical_exam'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($visit['diagnosis'])): ?>
            <div class="info-row">
                <span class="label"><i class="fas fa-diagnoses"></i> Diagnosis</span>
                <span class="value" style="text-align:left;font-weight:700;color:var(--purple);"><?= nl2br(htmlspecialchars($visit['diagnosis'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($visit['treatment'])): ?>
            <div class="info-row">
                <span class="label"><i class="fas fa-prescription"></i> Treatment</span>
                <span class="value" style="text-align:left;font-weight:600;"><?= nl2br(htmlspecialchars($visit['treatment'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($visit['notes'])): ?>
            <div class="info-row">
                <span class="label"><i class="fas fa-sticky-note"></i> Notes</span>
                <span class="value" style="text-align:left;font-weight:600;"><?= nl2br(htmlspecialchars($visit['notes'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- VITAL SIGNS -->
    <?php if ($vitals): ?>
    <div class="info-card">
        <div class="card-header">
            <span class="title"><i class="fas fa-heartbeat"></i> Vital Signs</span>
            <span style="font-size:0.68rem;color:var(--text-secondary);">
                <i class="fas fa-user"></i> <?= htmlspecialchars($vitals['recorded_by_name'] ?? 'N/A') ?>
                • <?= date('d M Y, H:i', strtotime($vitals['recorded_at'])) ?>
            </span>
        </div>
        <div class="card-body">
            <div class="vitals-grid">
                <?php if (!empty($vitals['temperature'])): ?>
                <div class="vital-item">
                    <span class="vital-label"><i class="fas fa-thermometer-half"></i> Temperature</span>
                    <span class="vital-value"><?= $vitals['temperature'] ?> °C</span>
                </div>
                <?php endif; ?>
                <?php if (!empty($vitals['blood_pressure_systolic'])): ?>
                <div class="vital-item">
                    <span class="vital-label"><i class="fas fa-heart"></i> BP</span>
                    <span class="vital-value"><?= $vitals['blood_pressure_systolic'] ?>/<?= $vitals['blood_pressure_diastolic'] ?></span>
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
                    <span class="vital-label"><i class="fas fa-lungs"></i> Respiratory</span>
                    <span class="vital-value"><?= $vitals['respiratory_rate'] ?> /min</span>
                </div>
                <?php endif; ?>
                <?php if (!empty($vitals['oxygen_saturation'])): ?>
                <div class="vital-item">
                    <span class="vital-label"><i class="fas fa-lungs"></i> SpO₂</span>
                    <span class="vital-value"><?= $vitals['oxygen_saturation'] ?> %</span>
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
                <?php if (!empty($vitals['blood_glucose'])): ?>
                <div class="vital-item">
                    <span class="vital-label"><i class="fas fa-tint"></i> Glucose</span>
                    <span class="vital-value"><?= $vitals['blood_glucose'] ?> mg/dL</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- BILL SUMMARY -->
    <?php if ($bill): ?>
    <div class="info-card">
        <div class="card-header">
            <span class="title"><i class="fas fa-file-invoice-dollar"></i> Bill Summary</span>
            <a href="view_bill.php?id=<?= $bill['id'] ?>" 
               style="text-decoration:none;background:var(--primary);color:white;padding:6px 14px;border-radius:8px;font-size:0.7rem;font-weight:700;">
                <i class="fas fa-eye"></i> View Full Bill
            </a>
        </div>
        <div class="card-body">
            <div class="bill-summary-box">
                <div class="bill-summary-title">
                    <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($bill['bill_number']) ?>
                </div>
                <div class="bill-summary-grid">
                    <div class="bill-summary-item total">
                        <div class="bs-label">Total Bill</div>
                        <div class="bs-value"><?= formatTsh($bill['total_amount'] ?? 0) ?></div>
                    </div>
                    <div class="bill-summary-item paid">
                        <div class="bs-label">Paid</div>
                        <div class="bs-value"><?= formatTsh($bill['paid_amount'] ?? 0) ?></div>
                    </div>
                    <div class="bill-summary-item balance">
                        <div class="bs-label">Balance</div>
                        <div class="bs-value"><?= formatTsh($bill['balance'] ?? 0) ?></div>
                    </div>
                    <div class="bill-summary-item discount">
                        <div class="bs-label">Discount</div>
                        <div class="bs-value"><?= formatTsh($bill['total_discount'] ?? 0) ?></div>
                    </div>
                    <div class="bill-summary-item premium">
                        <div class="bs-label">Premium</div>
                        <div class="bs-value"><?= formatTsh($bill['premium_amount'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- BILL ITEMS -->
    <?php if (!empty($bill_items)): ?>
    <div class="info-card">
        <div class="card-header">
            <span class="title"><i class="fas fa-list"></i> Bill Items (<?= count($bill_items) ?>)</span>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:45px;">#</th>
                            <th>Item</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th style="text-align:right;">Unit Price</th>
                            <th style="text-align:right;">Discount</th>
                            <th style="text-align:right;">Total</th>
                            <th style="text-align:center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($bill_items as $item): 
                            $is = getStatusBadge($item['status']);
                        ?>
                            <tr>
                                <td style="text-align:center;font-weight:700;"><?= $i++ ?></td>
                                <td style="font-weight:700;color:var(--primary);"><?= htmlspecialchars($item['item_name']) ?></td>
                                <td>
                                    <span class="status-badge purple" style="font-size:0.55rem;">
                                        <?= ucfirst(str_replace('_', ' ', $item['item_type'])) ?>
                                    </span>
                                </td>
                                <td><?= $item['quantity'] ?></td>
                                <td class="amount-cell"><?= formatTsh($item['unit_price']) ?></td>
                                <td class="amount-cell <?= ($item['discount_amount'] ?? 0) > 0 ? 'red' : '' ?>">
                                    <?= ($item['discount_amount'] ?? 0) > 0 ? '-' . formatTsh($item['discount_amount']) : '—' ?>
                                </td>
                                <td class="amount-cell"><?= formatTsh($item['total_price']) ?></td>
                                <td style="text-align:center;">
                                    <span class="status-badge <?= $is['class'] ?>" style="font-size:0.55rem;">
                                        <?= $is['icon'] ?> <?= $is['label'] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- LAB TESTS -->
    <?php if (!empty($lab_tests)): ?>
    <div class="info-card">
        <div class="card-header">
            <span class="title"><i class="fas fa-flask"></i> Lab Tests (<?= count($lab_tests) ?>)</span>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Test</th>
                            <th>Category</th>
                            <th>Results</th>
                            <th>Technician</th>
                            <th style="text-align:right;">Price</th>
                            <th style="text-align:center;">Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $lt): 
                            $ls = getStatusBadge($lt['status']);
                        ?>
                            <tr>
                                <td style="font-weight:700;color:var(--primary);">
                                    <?= htmlspecialchars($lt['test_name'] ?? $lt['catalog_name'] ?? 'N/A') ?>
                                </td>
                                <td>
                                    <span class="status-badge info" style="font-size:0.55rem;">
                                        <?= htmlspecialchars($lt['test_category'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="font-size:0.72rem;font-weight:600;color:var(--success);">
                                    <?= htmlspecialchars($lt['results'] ?? '—') ?>
                                </td>
                                <td style="font-size:0.72rem;">
                                    <i class="fas fa-user-nurse" style="color:var(--cyan);"></i>
                                    <?= htmlspecialchars($lt['technician_name'] ?? 'N/A') ?>
                                </td>
                                <td class="amount-cell"><?= formatTsh($lt['test_price'] ?? 0) ?></td>
                                <td style="text-align:center;">
                                    <span class="status-badge <?= $ls['class'] ?>" style="font-size:0.55rem;">
                                        <?= $ls['icon'] ?> <?= $ls['label'] ?>
                                    </span>
                                </td>
                                <td style="font-size:0.7rem;">
                                    <?= $lt['completed_at'] ? date('d M Y', strtotime($lt['completed_at'])) : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- PRESCRIPTIONS / MEDICATIONS -->
    <?php if (!empty($prescriptions)): ?>
    <div class="info-card">
        <div class="card-header">
            <span class="title"><i class="fas fa-pills"></i> Medications (<?= count($prescriptions) ?> prescription(s))</span>
        </div>
        <div class="card-body" style="padding:0;">
            <?php foreach ($prescriptions as $presc): 
                $ps = getStatusBadge($presc['status']);
            ?>
                <div style="padding:14px 20px;border-bottom:2px solid var(--border-color);">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                        <div>
                            <span style="font-family:var(--font-mono);font-weight:800;color:var(--primary);font-size:0.85rem;">
                                <i class="fas fa-prescription"></i> <?= htmlspecialchars($presc['prescription_number']) ?>
                            </span>
                            <span class="status-badge <?= $ps['class'] ?>" style="margin-left:8px;font-size:0.55rem;">
                                <?= $ps['icon'] ?> <?= $ps['label'] ?>
                            </span>
                        </div>
                        <div style="font-size:0.68rem;color:var(--text-secondary);">
                            <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($presc['doctor_name'] ?? 'N/A') ?>
                            <?php if (!empty($presc['pharmacy_name'])): ?>
                                • <i class="fas fa-pills"></i> <?= htmlspecialchars($presc['pharmacy_name']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($presc['items'])): ?>
                    <div class="data-table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Medication</th>
                                    <th>Dosage</th>
                                    <th>Frequency</th>
                                    <th>Qty</th>
                                    <th>Duration</th>
                                    <th style="text-align:right;">Unit Price</th>
                                    <th style="text-align:right;">Total</th>
                                    <th>Dispensed By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($presc['items'] as $pi): ?>
                                    <tr>
                                        <td style="font-weight:700;color:var(--primary);">
                                            <?= htmlspecialchars($pi['medication_name'] ?? $pi['inventory_name'] ?? 'N/A') ?>
                                            <?php if (!empty($pi['batch_number'])): ?>
                                                <div style="font-size:0.58rem;color:var(--text-secondary);font-family:var(--font-mono);">
                                                    Batch: <?= htmlspecialchars($pi['batch_number']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:0.72rem;"><?= htmlspecialchars($pi['dosage'] ?? '—') ?></td>
                                        <td style="font-size:0.72rem;"><?= htmlspecialchars($pi['frequency'] ?? '—') ?></td>
                                        <td style="font-weight:700;"><?= $pi['quantity'] ?></td>
                                        <td style="font-size:0.72rem;"><?= htmlspecialchars($pi['duration'] ?? '—') ?></td>
                                        <td class="amount-cell"><?= formatTsh($pi['unit_price'] ?? 0) ?></td>
                                        <td class="amount-cell"><?= formatTsh($pi['total_price'] ?? 0) ?></td>
                                        <td style="font-size:0.7rem;">
                                            <?php if (!empty($pi['dispensed_by_name'])): ?>
                                                <i class="fas fa-check-circle" style="color:var(--success);"></i>
                                                <?= htmlspecialchars($pi['dispensed_by_name']) ?>
                                            <?php else: ?>
                                                <span style="color:var(--text-secondary);font-style:italic;">Not dispensed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="empty-section" style="padding:14px;">
                            <i class="fas fa-pills"></i>
                            No items in this prescription
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- PROCEDURES -->
    <?php if (!empty($procedures)): ?>
    <div class="info-card">
        <div class="card-header">
            <span class="title"><i class="fas fa-syringe"></i> Procedures (<?= count($procedures) ?>)</span>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Procedure</th>
                            <th>Category</th>
                            <th>Doctor</th>
                            <th style="text-align:right;">Price</th>
                            <th style="text-align:center;">Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($procedures as $proc): 
                            $prs = getStatusBadge($proc['status']);
                        ?>
                            <tr>
                                <td style="font-weight:700;color:var(--primary);">
                                    <?= htmlspecialchars($proc['procedure_name']) ?>
                                </td>
                                <td>
                                    <span class="status-badge purple" style="font-size:0.55rem;">
                                        <?= htmlspecialchars($proc['procedure_category'] ?? $proc['category'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="font-size:0.72rem;">
                                    <i class="fas fa-user-md" style="color:var(--cyan);"></i>
                                    <?= htmlspecialchars($proc['doctor_name'] ?? 'N/A') ?>
                                </td>
                                <td class="amount-cell"><?= formatTsh($proc['procedure_price'] ?? 0) ?></td>
                                <td style="text-align:center;">
                                    <span class="status-badge <?= $prs['class'] ?>" style="font-size:0.55rem;">
                                        <?= $prs['icon'] ?> <?= $prs['label'] ?>
                                    </span>
                                </td>
                                <td style="font-size:0.7rem;">
                                    <?= date('d M Y', strtotime($proc['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- PAYMENTS -->
    <?php if (!empty($payments)): ?>
    <div class="info-card">
        <div class="card-header">
            <span class="title"><i class="fas fa-money-bill-wave"></i> Payment History (<?= count($payments) ?>)</span>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th style="text-align:right;">Amount</th>
                            <th>Method</th>
                            <th>Received By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): 
                            $rb_initials = getInitials($p['received_by_name'] ?? '');
                            $is_refund = ($p['amount'] ?? 0) < 0;
                        ?>
                            <tr>
                                <td style="font-family:var(--font-mono);font-weight:700;color:<?= $is_refund ? 'var(--danger)' : 'var(--primary)' ?>;">
                                    <?php if ($is_refund): ?>
                                        <i class="fas fa-undo-alt"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($p['receipt_number']) ?>
                                </td>
                                <td class="amount-cell <?= $is_refund ? 'red' : 'green' ?>">
                                    <?= $is_refund ? '-' : '' ?><?= formatTsh(abs($p['amount'])) ?>
                                </td>
                                <td><?= strtoupper(str_replace('_', ' ', $p['payment_method'] ?? 'cash')) ?></td>
                                <td>
                                    <div class="received-by-cell">
                                        <div class="received-by-avatar"><?= htmlspecialchars($rb_initials) ?></div>
                                        <div>
                                            <div class="received-by-name"><?= htmlspecialchars($p['received_by_name'] ?? 'N/A') ?></div>
                                            <div class="received-by-meta"><?= htmlspecialchars(strtoupper($p['received_by_role'] ?? 'user')) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-size:0.72rem;"><?= date('d M Y, H:i', strtotime($p['received_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ACTION BAR -->
    <div class="action-bar">
        <div class="action-info">
            <div class="info-icon"><i class="fas fa-clipboard-list"></i></div>
            <div class="info-text">
                <div class="info-title">Consultation Details</div>
                <div class="info-sub">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($visit['branch_name'] ?? $user_branch_name) ?>
                </div>
            </div>
        </div>
        <div class="action-buttons">
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="other_services.php?tab=consultations" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Consultations
            </a>
        </div>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            Consultation <?= htmlspecialchars($visit['visit_number']) ?>
            <span style="margin:0 8px;">|</span>
            <span><?= date('d M Y, H:i:s') ?></span>
        </p>
    </footer>

</main>

<script>
console.log('%c🔍 Audit - View Consultation (BRANCH LOCKED CLEAN)', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ AUDIT ANAONA BRANCH YAKE TU', 'font-size:13px;color:#34D399;font-weight:bold;');
console.log('%c✅ VIEW ONLY notice na badges zimeondolewa', 'font-size:13px;color:#34D399;font-weight:bold;');
console.log('%c👥 Branch: <?= htmlspecialchars($visit['branch_name'] ?? $user_branch_name) ?>', 'font-size:13px;color:#0B5ED7;font-weight:bold;');
console.log('%c🩺 Visit: <?= htmlspecialchars($visit['visit_number']) ?>', 'font-size:13px;color:#34D399;font-weight:bold;');
console.log('%c👤 Patient: <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>', 'font-size:13px;color:#34D399;');
console.log('%c👨‍⚕️ Doctor: Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?>', 'font-size:13px;color:#34D399;');
console.log('%c💰 Fee: <?= $currency ?> <?= number_format($visit['consultation_fee'] ?? 0, 0) ?>', 'font-size:13px;color:#0B5ED7;font-weight:bold;');
<?php if ($bill): ?>
console.log('%c📄 Bill: <?= htmlspecialchars($bill['bill_number']) ?>', 'font-size:13px;color:#7C3AED;font-weight:bold;');
<?php endif; ?>
console.log('%c🧪 Lab Tests: <?= count($lab_tests) ?>', 'font-size:13px;color:#0891B2;');
console.log('%c💊 Prescriptions: <?= count($prescriptions) ?>', 'font-size:13px;color:#7C3AED;');
console.log('%c💉 Procedures: <?= count($procedures) ?>', 'font-size:13px;color:#059669;');
</script>

</body>
</html>