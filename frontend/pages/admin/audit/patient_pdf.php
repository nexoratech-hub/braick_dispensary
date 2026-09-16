<?php
// ================================================================
// FILE: frontend/pages/admin/audit/patient_pdf.php
// PATIENT PDF - Full Report with Logo, Name, Slogan, Admin Phones
// ✅ Logo ya Dispensary juu
// ✅ Jina la Dispensary kutoka system_settings
// ✅ Slogan: "Tunajali Afya Yako"
// ✅ Simu za Admin kutoka users table
// ✅ Patient info + Visits + Bills + Vitals
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

$patient_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($patient_id <= 0) {
    die("Invalid patient ID");
}

require_once __DIR__ . '/../../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// ✅ SYSTEM SETTINGS (Jina la dispensary, currency, etc)
// ================================================================
$settings = [];
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}

$site_name = $settings['site_name'] ?? 'Braick Dispensary';
$currency = $settings['currency'] ?? 'TSh';

// ================================================================
// ✅ ADMIN PHONES KUTOKA TABLE YA USERS
// ================================================================
$admin_phones = [];
try {
    $stmt = $db->query("
        SELECT full_name, phone 
        FROM users 
        WHERE role = 'admin' 
        AND status = 'active' 
        AND phone IS NOT NULL 
        AND phone != ''
        ORDER BY full_name
    ");
    $admin_phones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// GET PATIENT
// ================================================================
$patient = null;
try {
    $stmt = $db->prepare("
        SELECT p.*, 
               b.name AS branch_name,
               b.location AS branch_location,
               b.phone AS branch_phone,
               b.email AS branch_email,
               u.full_name AS registered_by_name,
               d.full_name AS assigned_doctor_name
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN users d ON p.assigned_doctor_id = d.id
        WHERE p.id = ?
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error loading patient: " . $e->getMessage());
}

if (!$patient) {
    die("Patient not found");
}

// ================================================================
// GET ALL VISITS
// ================================================================
$visits = [];
try {
    $stmt = $db->prepare("
        SELECT v.*, 
               u.full_name AS doctor_name,
               r.full_name AS receptionist_name,
               d.disease_name,
               d.disease_code
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        LEFT JOIN diseases d ON v.disease_id = d.id
        WHERE v.patient_id = ?
        ORDER BY v.visit_date DESC
    ");
    $stmt->execute([$patient_id]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// VITALS GROUPED BY VISIT
// ================================================================
$vitals_by_visit = [];
try {
    $stmt = $db->prepare("
        SELECT vs.*, u.full_name AS recorded_by_name
        FROM vital_signs vs
        LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE vs.patient_id = ?
        ORDER BY vs.recorded_at DESC
    ");
    $stmt->execute([$patient_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
        $vid = $v['visit_id'] ?? 0;
        if (!isset($vitals_by_visit[$vid])) $vitals_by_visit[$vid] = [];
        $vitals_by_visit[$vid][] = $v;
    }
} catch (Exception $e) {}

// ================================================================
// BILLS GROUPED BY VISIT
// ================================================================
$bills_by_visit = [];
try {
    $stmt = $db->prepare("
        SELECT b.*, u.full_name AS created_by_name
        FROM bills b
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.patient_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
        $vid = $b['visit_id'] ?? 0;
        if (!isset($bills_by_visit[$vid])) $bills_by_visit[$vid] = [];
        $bills_by_visit[$vid][] = $b;
    }
} catch (Exception $e) {}

// ================================================================
// BILL ITEMS GROUPED BY BILL
// ================================================================
$bill_items_by_bill = [];
try {
    $stmt = $db->prepare("
        SELECT bi.*
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE b.patient_id = ?
        ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $bid = $item['bill_id'] ?? 0;
        if (!isset($bill_items_by_bill[$bid])) $bill_items_by_bill[$bid] = [];
        $bill_items_by_bill[$bid][] = $item;
    }
} catch (Exception $e) {}

// ================================================================
// PRESCRIPTIONS GROUPED BY VISIT
// ================================================================
$prescriptions_by_visit = [];
try {
    $stmt = $db->prepare("
        SELECT pr.*, u.full_name AS doctor_name
        FROM prescriptions pr
        LEFT JOIN users u ON pr.doctor_id = u.id
        WHERE pr.patient_id = ?
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $pr) {
        $vid = $pr['visit_id'] ?? 0;
        if (!isset($prescriptions_by_visit[$vid])) $prescriptions_by_visit[$vid] = [];
        $prescriptions_by_visit[$vid][] = $pr;
    }
} catch (Exception $e) {}

// ================================================================
// PRESCRIPTION ITEMS GROUPED BY PRESCRIPTION
// ================================================================
$prescription_items_by_prescription = [];
try {
    $stmt = $db->prepare("
        SELECT pi.*
        FROM prescription_items pi
        INNER JOIN prescriptions pr ON pi.prescription_id = pr.id
        WHERE pr.patient_id = ?
        ORDER BY pi.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $pid = $item['prescription_id'] ?? 0;
        if (!isset($prescription_items_by_prescription[$pid])) $prescription_items_by_prescription[$pid] = [];
        $prescription_items_by_prescription[$pid][] = $item;
    }
} catch (Exception $e) {}

// ================================================================
// LAB TESTS GROUPED BY VISIT
// ================================================================
$lab_tests_by_visit = [];
try {
    $stmt = $db->prepare("
        SELECT lt.*, u.full_name AS technician_name
        FROM lab_tests lt
        LEFT JOIN users u ON lt.lab_technician_id = u.id
        WHERE lt.patient_id = ?
        ORDER BY lt.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $lt) {
        $vid = $lt['visit_id'] ?? 0;
        if (!isset($lab_tests_by_visit[$vid])) $lab_tests_by_visit[$vid] = [];
        $lab_tests_by_visit[$vid][] = $lt;
    }
} catch (Exception $e) {}

// ================================================================
// PROCEDURES GROUPED BY VISIT
// ================================================================
$procedures_by_visit = [];
try {
    $stmt = $db->prepare("
        SELECT pr.*, u.full_name AS doctor_name
        FROM procedures pr
        LEFT JOIN users u ON pr.doctor_id = u.id
        WHERE pr.patient_id = ?
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $vid = $p['visit_id'] ?? 0;
        if (!isset($procedures_by_visit[$vid])) $procedures_by_visit[$vid] = [];
        $procedures_by_visit[$vid][] = $p;
    }
} catch (Exception $e) {}

// ================================================================
// STATS
// ================================================================
$stats = [
    'visits' => count($visits),
    'total_spent' => 0,
    'total_paid' => 0,
    'total_balance' => 0,
];

try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_bills,
            COALESCE(SUM(total_amount), 0) as total_spent,
            COALESCE(SUM(paid_amount), 0) as total_paid,
            COALESCE(SUM(balance), 0) as total_balance
        FROM bills
        WHERE patient_id = ?
    ");
    $stmt->execute([$patient_id]);
    $bill_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_spent'] = (float)($bill_stats['total_spent'] ?? 0);
    $stats['total_paid'] = (float)($bill_stats['total_paid'] ?? 0);
    $stats['total_balance'] = (float)($bill_stats['total_balance'] ?? 0);
} catch (Exception $e) {}

// ================================================================
// HELPERS
// ================================================================
function calculateAge($dob) {
    if (!$dob) return 'N/A';
    try {
        $birthDate = new DateTime($dob);
        $today = new DateTime();
        $age = $today->diff($birthDate);
        return $age->y . ' yrs';
    } catch (Exception $e) {
        return 'N/A';
    }
}

function getStatusClass($status) {
    $status = strtolower($status ?? 'pending');
    $map = [
        'paid' => 'success',
        'completed' => 'success',
        'confirmed' => 'success',
        'dispensed' => 'success',
        'pending' => 'warning',
        'partial' => 'info',
        'in_progress' => 'info',
        'assigned' => 'info',
        'waiting' => 'warning',
        'scheduled' => 'info',
        'cancelled' => 'danger',
        'rejected' => 'danger',
    ];
    return $map[$status] ?? 'secondary';
}

// ✅ LOGO PATH - tumia logo ya dispensary
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_file_system = __DIR__ . '/../../../../frontend/assets/uploads/profiles/braick_logo.png';

// Kama logo haipo, tumia default
if (!file_exists($logo_file_system)) {
    $logo_path = '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
}

$print_date = date('d M Y, H:i:s');
$print_by = $user_full_name;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Report - <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?> - <?= htmlspecialchars($site_name) ?></title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <!-- ✅ FONTS: INTER + JETBRAINS MONO -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
/* ================================================================
   ✅ FONTS
   ================================================================ */
:root {
    --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    
    --blue-primary: #0B5ED7;
    --blue-primary-dark: #0A4CA8;
    --blue-primary-bg: #E8F0FE;
    
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    
    --success: #059669;
    --danger: #DC2626;
    --warning: #D97706;
    --purple: #7C3AED;
    --info: #0891B2;
    --teal: #0D9488;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: var(--font-primary);
    -webkit-font-smoothing: antialiased;
}

body {
    background: #F1F5F9;
    color: var(--text-primary);
    font-size: 12px;
    line-height: 1.5;
    padding: 20px;
}

.mono {
    font-family: var(--font-mono) !important;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

/* ================================================================
   PAGE CONTAINER
   ================================================================ */
.pdf-page {
    background: white;
    max-width: 210mm;
    margin: 0 auto;
    padding: 15mm 12mm;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    border-radius: 8px;
}

/* ================================================================
   ✅ HEADER - LOGO + JINA + SLOGAN + SIMU
   ================================================================ */
.report-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 15px;
    border-bottom: 3px solid var(--blue-primary);
    margin-bottom: 20px;
    gap: 20px;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1;
}

.header-logo {
    width: 90px;
    height: 90px;
    object-fit: contain;
    border-radius: 12px;
    border: 2px solid var(--blue-primary);
    padding: 5px;
    background: white;
    flex-shrink: 0;
}

.header-info {
    flex: 1;
}

.header-title {
    font-size: 24px;
    font-weight: 900;
    color: var(--blue-primary);
    letter-spacing: -0.03em;
    line-height: 1.1;
    margin-bottom: 4px;
    text-transform: uppercase;
}

.header-slogan {
    font-size: 13px;
    font-weight: 700;
    color: var(--blue-primary-dark);
    font-style: italic;
    margin-bottom: 8px;
    letter-spacing: 0.02em;
}

.header-contacts {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 11px;
    color: var(--text-secondary);
    font-weight: 600;
}

.header-contact-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: var(--blue-primary-bg);
    padding: 3px 10px;
    border-radius: 6px;
    color: var(--blue-primary);
}

.header-contact-item i {
    font-size: 11px;
}

.header-right {
    text-align: right;
    flex-shrink: 0;
}

.report-badge {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    display: inline-block;
    margin-bottom: 8px;
}

.report-meta {
    font-size: 10px;
    color: var(--text-secondary);
    font-weight: 600;
    line-height: 1.6;
}

.report-meta strong {
    color: var(--text-primary);
}

/* ================================================================
   SECTION TITLES
   ================================================================ */
.section-title {
    font-size: 13px;
    font-weight: 800;
    color: var(--blue-primary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 8px 12px;
    background: var(--blue-primary-bg);
    border-left: 4px solid var(--blue-primary);
    border-radius: 6px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title i {
    font-size: 14px;
}

/* ================================================================
   PATIENT INFO BOX
   ================================================================ */
.patient-box {
    background: #F8FAFC;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 14px;
    margin-bottom: 18px;
}

.patient-box-title {
    font-size: 13px;
    font-weight: 800;
    color: var(--blue-primary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
    padding-bottom: 8px;
    border-bottom: 2px dashed var(--border-color);
}

.patient-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}

.patient-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.patient-label {
    font-size: 9px;
    font-weight: 800;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.patient-value {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-primary);
}

/* ================================================================
   STATS CARDS
   ================================================================ */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 18px;
}

.stat-box {
    background: white;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    padding: 10px 12px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.stat-box::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
}

.stat-box.visits::before { background: var(--blue-primary); }
.stat-box.spent::before { background: var(--success); }
.stat-box.paid::before { background: var(--info); }
.stat-box.balance::before { background: var(--danger); }

.stat-label {
    font-size: 9px;
    font-weight: 800;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 18px;
    font-weight: 900;
    font-family: var(--font-mono);
    letter-spacing: -0.03em;
}

.stat-box.visits .stat-value { color: var(--blue-primary); }
.stat-box.spent .stat-value { color: var(--success); }
.stat-box.paid .stat-value { color: var(--info); }
.stat-box.balance .stat-value { color: var(--danger); }

/* ================================================================
   VISIT CARD
   ================================================================ */
.visit-block {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    margin-bottom: 14px;
    overflow: hidden;
    page-break-inside: avoid;
}

.visit-header {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    padding: 10px 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.visit-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.visit-badge {
    background: rgba(255,255,255,0.2);
    padding: 4px 10px;
    border-radius: 6px;
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 800;
    border: 1px solid rgba(255,255,255,0.25);
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.visit-badge-light {
    background: rgba(255,255,255,0.15);
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid rgba(255,255,255,0.15);
}

.visit-body {
    padding: 12px 14px;
    background: white;
}

/* ================================================================
   VITALS GRID
   ================================================================ */
.vitals-row {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 8px;
    margin-bottom: 12px;
}

.vital-box {
    background: #F8FAFC;
    border: 1px solid var(--border-color);
    border-left: 3px solid var(--blue-primary);
    border-radius: 6px;
    padding: 8px 10px;
}

.vital-box.temp { border-left-color: var(--danger); }
.vital-box.bp { border-left-color: var(--purple); }
.vital-box.pulse { border-left-color: #DB2777; }
.vital-box.resp { border-left-color: var(--info); }
.vital-box.oxygen { border-left-color: var(--success); }
.vital-box.glucose { border-left-color: var(--warning); }
.vital-box.weight { border-left-color: var(--blue-primary); }

.vital-label {
    font-size: 8px;
    font-weight: 800;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 3px;
}

.vital-value {
    font-size: 13px;
    font-weight: 800;
    font-family: var(--font-mono);
    color: var(--text-primary);
    letter-spacing: -0.02em;
}

/* ================================================================
   DIAGNOSIS BOX
   ================================================================ */
.diagnosis-row {
    background: rgba(124, 58, 237, 0.08);
    border: 1px solid var(--purple);
    border-left: 4px solid var(--purple);
    border-radius: 6px;
    padding: 10px 12px;
    margin-bottom: 10px;
}

.diagnosis-label {
    font-size: 9px;
    font-weight: 800;
    color: var(--purple);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.diagnosis-value {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.5;
}

.disease-code {
    display: inline-block;
    background: var(--purple);
    color: white;
    font-family: var(--font-mono);
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 800;
    margin-top: 4px;
}

.treatment-row {
    background: rgba(5, 150, 105, 0.08);
    border: 1px solid var(--success);
    border-left: 4px solid var(--success);
    border-radius: 6px;
    padding: 10px 12px;
    margin-bottom: 10px;
}

.treatment-label {
    font-size: 9px;
    font-weight: 800;
    color: var(--success);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.treatment-value {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.5;
}

.symptoms-row {
    background: rgba(217, 119, 6, 0.08);
    border: 1px solid var(--warning);
    border-left: 4px solid var(--warning);
    border-radius: 6px;
    padding: 10px 12px;
    margin-bottom: 10px;
}

.symptoms-label {
    font-size: 9px;
    font-weight: 800;
    color: var(--warning);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.symptoms-value {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.5;
}

/* ================================================================
   TABLE
   ================================================================ */
.pdf-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10px;
    margin-bottom: 10px;
    border-radius: 6px;
    overflow: hidden;
}

.pdf-table thead th {
    background: var(--blue-primary-bg);
    color: var(--blue-primary);
    padding: 6px 8px;
    text-align: left;
    font-weight: 800;
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    border-bottom: 2px solid var(--blue-primary);
    white-space: nowrap;
}

.pdf-table tbody td {
    padding: 6px 8px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
    font-weight: 500;
}

.pdf-table tbody tr:last-child td {
    border-bottom: none;
}

.pdf-table tbody tr:hover td {
    background: #F8FAFC;
}

.money-cell {
    font-family: var(--font-mono);
    font-weight: 800;
    font-size: 10px;
    text-align: right;
    white-space: nowrap;
    color: var(--success);
}

.money-cell.danger { color: var(--danger); }
.money-cell.info { color: var(--info); }
.money-cell.purple { color: var(--purple); }
.money-cell.warning { color: var(--warning); }
.money-cell.blue { color: var(--blue-primary); }

.money-cell .cp {
    font-size: 8px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-right: 2px;
}

/* ================================================================
   BILL BOX
   ================================================================ */
.bill-box {
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 10px;
    margin-bottom: 10px;
    background: #F8FAFC;
}

.bill-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px dashed var(--border-color);
    flex-wrap: wrap;
    gap: 6px;
}

.bill-header-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    font-size: 10px;
}

.bill-number {
    font-family: var(--font-mono);
    font-weight: 800;
    color: var(--blue-primary);
    background: var(--blue-primary-bg);
    padding: 3px 8px;
    border-radius: 5px;
    font-size: 10px;
}

.bill-totals {
    display: flex;
    justify-content: flex-end;
    gap: 14px;
    flex-wrap: wrap;
    padding-top: 8px;
    margin-top: 8px;
    border-top: 1px dashed var(--border-color);
}

.bill-total-item {
    text-align: right;
}

.bill-total-label {
    font-size: 8px;
    font-weight: 800;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 2px;
}

.bill-total-label.pharm { color: var(--purple); }
.bill-total-label.cashier { color: var(--info); }
.bill-total-label.disc { color: var(--danger); }
.bill-total-label.prem { color: var(--warning); }
.bill-total-label.total { color: var(--blue-primary); }
.bill-total-label.paid { color: var(--success); }
.bill-total-label.bal { color: var(--danger); }

/* ================================================================
   STATUS BADGE
   ================================================================ */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.status-badge.success { background: #D1FAE5; color: var(--success); }
.status-badge.warning { background: #FEF3C7; color: var(--warning); }
.status-badge.danger { background: #FEE2E2; color: var(--danger); }
.status-badge.info { background: #CFFAFE; color: var(--info); }
.status-badge.secondary { background: #E2E8F0; color: var(--text-secondary); }

/* ================================================================
   FOOTER
   ================================================================ */
.report-footer {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 3px solid var(--blue-primary);
    text-align: center;
    font-size: 10px;
    color: var(--text-secondary);
    font-weight: 600;
    line-height: 1.6;
}

.footer-brand {
    color: var(--blue-primary);
    font-weight: 800;
    font-size: 12px;
}

.footer-slogan {
    color: var(--blue-primary-dark);
    font-style: italic;
    font-weight: 700;
    font-size: 11px;
    margin-top: 2px;
}

.footer-contacts {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 8px;
    font-size: 10px;
}

.footer-contact-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--blue-primary-bg);
    padding: 3px 10px;
    border-radius: 5px;
    color: var(--blue-primary);
    font-weight: 700;
}

/* ================================================================
   PRINT BUTTON (fixed bottom-right)
   ================================================================ */
.print-controls {
    position: fixed;
    bottom: 20px;
    right: 20px;
    display: flex;
    gap: 10px;
    z-index: 9999;
}

.print-btn, .close-btn {
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 13px;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: var(--font-primary);
    box-shadow: 0 4px 16px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
    text-decoration: none;
}

.print-btn {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
}

.print-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(11, 94, 215, 0.5);
}

.close-btn {
    background: #E2E8F0;
    color: var(--text-primary);
}

.close-btn:hover {
    background: #CBD5E1;
    transform: translateY(-2px);
}

/* ================================================================
   PRINT STYLES
   ================================================================ */
@media print {
    body {
        background: white;
        padding: 0;
        font-size: 10px;
    }
    
    .pdf-page {
        box-shadow: none;
        border-radius: 0;
        padding: 10mm 8mm;
        max-width: 100%;
    }
    
    .print-controls {
        display: none !important;
    }
    
    .visit-block {
        page-break-inside: avoid;
    }
    
    .section-title {
        page-break-after: avoid;
    }
    
    @page {
        size: A4;
        margin: 10mm 8mm;
    }
}

/* ================================================================
   RESPONSIVE
   ================================================================ */
@media (max-width: 768px) {
    .report-header {
        flex-direction: column;
        text-align: center;
    }
    
    .header-left {
        flex-direction: column;
        text-align: center;
    }
    
    .header-right {
        text-align: center;
    }
    
    .patient-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .vitals-row {
        grid-template-columns: repeat(2, 1fr);
    }
}
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- ✅ PDF CONTENT -->
<!-- ================================================================ -->
<div class="pdf-page">
    
    <!-- ✅ HEADER - LOGO + JINA + SLOGAN + SIMU -->
    <div class="report-header">
        <div class="header-left">
            <img src="<?= $logo_path ?>" alt="<?= htmlspecialchars($site_name) ?>" class="header-logo" 
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2290%22 height=%2290%22%3E%3Crect width=%2290%22 height=%2290%22 fill=%22%230B5ED7%22 rx=%2212%22/%3E%3Ctext x=%2245%22 y=%2260%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2240%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            
            <div class="header-info">
                <div class="header-title"><?= htmlspecialchars($site_name) ?></div>
                <div class="header-slogan">
                    <i class="fas fa-heart" style="color: #DC2626;"></i>
                    Tunajali Afya Yako
                </div>
                
                <!-- ✅ Simu za Admin kutoka users table -->
                <div class="header-contacts">
                    <?php if (count($admin_phones) > 0): ?>
                        <?php foreach ($admin_phones as $admin): ?>
                            <span class="header-contact-item">
                                <i class="fas fa-phone"></i>
                                <span class="mono"><?= htmlspecialchars($admin['phone']) ?></span>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($patient['branch_phone'])): ?>
                        <span class="header-contact-item">
                            <i class="fas fa-store-alt"></i>
                            <span class="mono"><?= htmlspecialchars($patient['branch_phone']) ?></span>
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($patient['branch_location'])): ?>
                        <span class="header-contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <?= htmlspecialchars($patient['branch_location']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="header-right">
            <div class="report-badge">
                <i class="fas fa-file-medical-alt"></i>
                Patient Report
            </div>
            <div class="report-meta">
                <div>Printed: <strong class="mono"><?= $print_date ?></strong></div>
                <div>By: <strong><?= htmlspecialchars($print_by) ?></strong></div>
            </div>
        </div>
    </div>
    
    <!-- ✅ PATIENT INFORMATION -->
    <div class="patient-box">
        <div class="patient-box-title">
            <i class="fas fa-user-injured"></i>
            Patient Information
        </div>
        
        <div class="patient-grid">
            <div class="patient-item">
                <div class="patient-label">Full Name</div>
                <div class="patient-value"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></div>
            </div>
            
            <div class="patient-item">
                <div class="patient-label">Patient ID</div>
                <div class="patient-value mono"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></div>
            </div>
            
            <div class="patient-item">
                <div class="patient-label">Gender</div>
                <div class="patient-value"><?= htmlspecialchars(ucfirst($patient['gender'] ?? 'N/A')) ?></div>
            </div>
            
            <div class="patient-item">
                <div class="patient-label">Age</div>
                <div class="patient-value"><?= calculateAge($patient['date_of_birth'] ?? null) ?></div>
            </div>
            
            <div class="patient-item">
                <div class="patient-label">Date of Birth</div>
                <div class="patient-value mono"><?= $patient['date_of_birth'] ? date('d M Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></div>
            </div>
            
            <div class="patient-item">
                <div class="patient-label">Blood Group</div>
                <div class="patient-value mono"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></div>
            </div>
            
            <div class="patient-item">
                <div class="patient-label">Phone</div>
                <div class="patient-value mono"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></div>
            </div>
            
            <div class="patient-item">
                <div class="patient-label">Email</div>
                <div class="patient-value"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></div>
            </div>
            
            <div class="patient-item">
                <div class="patient-label">Address</div>
                <div class="patient-value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></div>
            </div>
            
            <?php if (!empty($patient['allergies'])): ?>
            <div class="patient-item" style="grid-column: span 3;">
                <div class="patient-label" style="color: #DC2626;">
                    <i class="fas fa-exclamation-triangle"></i> Allergies
                </div>
                <div class="patient-value" style="color: #DC2626;"><?= htmlspecialchars($patient['allergies']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ✅ STATS -->
    <div class="stats-row">
        <div class="stat-box visits">
            <div class="stat-label">Total Visits</div>
            <div class="stat-value"><?= number_format($stats['visits']) ?></div>
        </div>
        
        <div class="stat-box spent">
            <div class="stat-label">Total Spent</div>
            <div class="stat-value"><?= number_format($stats['total_spent'], 0) ?></div>
        </div>
        
        <div class="stat-box paid">
            <div class="stat-label">Total Paid</div>
            <div class="stat-value"><?= number_format($stats['total_paid'], 0) ?></div>
        </div>
        
        <div class="stat-box balance">
            <div class="stat-label">Balance Due</div>
            <div class="stat-value"><?= number_format($stats['total_balance'], 0) ?></div>
        </div>
    </div>
    
    <!-- ✅ VISITS -->
    <?php if (count($visits) > 0): ?>
        <div class="section-title">
            <i class="fas fa-hospital-user"></i>
            Medical Records (<?= count($visits) ?> Visits)
        </div>
        
        <?php foreach ($visits as $visit): 
            $vid = $visit['id'];
            $visit_vitals = $vitals_by_visit[$vid] ?? [];
            $visit_bills = $bills_by_visit[$vid] ?? [];
            $visit_prescriptions = $prescriptions_by_visit[$vid] ?? [];
            $visit_labs = $lab_tests_by_visit[$vid] ?? [];
            $visit_procedures = $procedures_by_visit[$vid] ?? [];
        ?>
            <div class="visit-block">
                
                <!-- Visit Header -->
                <div class="visit-header">
                    <div class="visit-header-left">
                        <span class="visit-badge">
                            <i class="fas fa-hashtag"></i>
                            <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                        </span>
                        <span class="visit-badge-light">
                            <i class="fas fa-calendar-alt"></i>
                            <?= date('d M Y, H:i', strtotime($visit['visit_date'] ?? 'now')) ?>
                        </span>
                        <?php if (!empty($visit['doctor_name'])): ?>
                        <span class="visit-badge-light">
                            <i class="fas fa-user-md"></i>
                            <?= htmlspecialchars($visit['doctor_name']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <span class="status-badge <?= getStatusClass($visit['status']) ?>" style="background: rgba(255,255,255,0.25); color: white;">
                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $visit['status'] ?? 'N/A'))) ?>
                    </span>
                </div>
                
                <!-- Visit Body -->
                <div class="visit-body">
                    
                    <!-- VITALS -->
                    <?php if (count($visit_vitals) > 0): ?>
                        <?php foreach ($visit_vitals as $vs): ?>
                            <div class="section-title" style="font-size: 11px; padding: 6px 10px; margin-bottom: 8px;">
                                <i class="fas fa-heartbeat"></i>
                                Vital Signs
                                <?php if (!empty($vs['recorded_by_name'])): ?>
                                    <span style="font-weight: 600; text-transform: none; margin-left: 8px; font-size: 10px; opacity: 0.8;">
                                        (Recorded by: <?= htmlspecialchars($vs['recorded_by_name']) ?>)
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="vitals-row">
                                <?php if (!empty($vs['temperature'])): ?>
                                <div class="vital-box temp">
                                    <div class="vital-label">Temp</div>
                                    <div class="vital-value"><?= htmlspecialchars($vs['temperature']) ?>°C</div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($vs['blood_pressure_systolic'])): ?>
                                <div class="vital-box bp">
                                    <div class="vital-label">Blood Pressure</div>
                                    <div class="vital-value"><?= $vs['blood_pressure_systolic'] ?>/<?= $vs['blood_pressure_diastolic'] ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($vs['pulse_rate'])): ?>
                                <div class="vital-box pulse">
                                    <div class="vital-label">Pulse Rate</div>
                                    <div class="vital-value"><?= htmlspecialchars($vs['pulse_rate']) ?> bpm</div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($vs['respiratory_rate'])): ?>
                                <div class="vital-box resp">
                                    <div class="vital-label">Respiratory</div>
                                    <div class="vital-value"><?= htmlspecialchars($vs['respiratory_rate']) ?>/min</div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($vs['oxygen_saturation'])): ?>
                                <div class="vital-box oxygen">
                                    <div class="vital-label">Oxygen Sat.</div>
                                    <div class="vital-value"><?= htmlspecialchars($vs['oxygen_saturation']) ?>%</div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($vs['blood_glucose'])): ?>
                                <div class="vital-box glucose">
                                    <div class="vital-label">Glucose</div>
                                    <div class="vital-value"><?= htmlspecialchars($vs['blood_glucose']) ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($vs['weight'])): ?>
                                <div class="vital-box weight">
                                    <div class="vital-label">Weight</div>
                                    <div class="vital-value"><?= htmlspecialchars($vs['weight']) ?> kg</div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($vs['height'])): ?>
                                <div class="vital-box weight">
                                    <div class="vital-label">Height</div>
                                    <div class="vital-value"><?= htmlspecialchars($vs['height']) ?> cm</div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($vs['bmi'])): ?>
                                <div class="vital-box weight">
                                    <div class="vital-label">BMI</div>
                                    <div class="vital-value"><?= htmlspecialchars($vs['bmi']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- SYMPTOMS -->
                    <?php if (!empty($visit['symptoms'])): ?>
                    <div class="symptoms-row">
                        <div class="symptoms-label">
                            <i class="fas fa-notes-medical"></i> Symptoms / Chief Complaint
                        </div>
                        <div class="symptoms-value"><?= htmlspecialchars($visit['symptoms']) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- DIAGNOSIS -->
                    <?php if (!empty($visit['diagnosis']) || !empty($visit['disease_name'])): ?>
                    <div class="diagnosis-row">
                        <div class="diagnosis-label">
                            <i class="fas fa-diagnoses"></i> Diagnosis
                        </div>
                        <div class="diagnosis-value">
                            <?= htmlspecialchars($visit['diagnosis'] ?? $visit['disease_name'] ?? 'N/A') ?>
                        </div>
                        <?php if (!empty($visit['disease_code'])): ?>
                            <span class="disease-code">
                                <i class="fas fa-tag"></i>
                                <?= htmlspecialchars($visit['disease_code']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- TREATMENT -->
                    <?php if (!empty($visit['treatment'])): ?>
                    <div class="treatment-row">
                        <div class="treatment-label">
                            <i class="fas fa-prescription-bottle-medical"></i> Treatment Plan
                        </div>
                        <div class="treatment-value"><?= nl2br(htmlspecialchars($visit['treatment'])) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- LAB TESTS -->
                    <?php if (count($visit_labs) > 0): ?>
                        <div class="section-title" style="font-size: 11px; padding: 6px 10px; margin-bottom: 8px; margin-top: 10px;">
                            <i class="fas fa-flask"></i>
                            Lab Tests & Results
                        </div>
                        
                        <table class="pdf-table">
                            <thead>
                                <tr>
                                    <th>Test Name</th>
                                    <th>Result</th>
                                    <th>Reference</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($visit_labs as $lt): ?>
                                    <tr>
                                        <td style="font-weight: 700;"><?= htmlspecialchars($lt['test_name'] ?? 'N/A') ?></td>
                                        <td class="mono" style="font-weight: 700; color: var(--success);">
                                            <?= htmlspecialchars(substr($lt['results'] ?? '-', 0, 50)) ?>
                                            <?= strlen($lt['results'] ?? '') > 50 ? '...' : '' ?>
                                        </td>
                                        <td style="color: var(--text-secondary);"><?= htmlspecialchars($lt['reference_range'] ?? '-') ?></td>
                                        <td>
                                            <span class="status-badge <?= getStatusClass($lt['status']) ?>">
                                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $lt['status'] ?? 'N/A'))) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                    <!-- MEDICATIONS -->
                    <?php if (count($visit_prescriptions) > 0): ?>
                        <div class="section-title" style="font-size: 11px; padding: 6px 10px; margin-bottom: 8px; margin-top: 10px;">
                            <i class="fas fa-pills"></i>
                            Medications / Prescriptions
                        </div>
                        
                        <?php foreach ($visit_prescriptions as $pr): 
                            $pr_items = $prescription_items_by_prescription[$pr['id']] ?? [];
                        ?>
                            <div style="margin-bottom: 8px; padding: 8px; background: #F8FAFC; border: 1px solid var(--border-color); border-radius: 6px;">
                                <div style="font-size: 10px; font-weight: 700; margin-bottom: 6px; color: var(--purple);">
                                    <i class="fas fa-prescription"></i>
                                    <span class="mono"><?= htmlspecialchars($pr['prescription_number'] ?? 'N/A') ?></span>
                                    — <?= htmlspecialchars($pr['doctor_name'] ?? 'N/A') ?>
                                </div>
                                
                                <?php if (count($pr_items) > 0): ?>
                                    <table class="pdf-table">
                                        <thead>
                                            <tr>
                                                <th>Medication</th>
                                                <th>Dosage</th>
                                                <th>Frequency</th>
                                                <th style="text-align: center;">Qty</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pr_items as $item): ?>
                                                <tr>
                                                    <td style="font-weight: 700;"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                                    <td><?= htmlspecialchars($item['dosage'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($item['frequency'] ?? '-') ?></td>
                                                    <td class="mono" style="text-align: center; font-weight: 800; color: var(--purple);">
                                                        <?= number_format($item['quantity'] ?? 0) ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- PROCEDURES -->
                    <?php if (count($visit_procedures) > 0): ?>
                        <div class="section-title" style="font-size: 11px; padding: 6px 10px; margin-bottom: 8px; margin-top: 10px;">
                            <i class="fas fa-procedures"></i>
                            Procedures
                        </div>
                        
                        <table class="pdf-table">
                            <thead>
                                <tr>
                                    <th>Procedure</th>
                                    <th>Category</th>
                                    <th>Doctor</th>
                                    <th style="text-align: right;">Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($visit_procedures as $p): ?>
                                    <tr>
                                        <td style="font-weight: 700;"><?= htmlspecialchars($p['procedure_name'] ?? 'N/A') ?></td>
                                        <td style="color: var(--text-secondary);"><?= htmlspecialchars($p['procedure_category'] ?? $p['category'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($p['doctor_name'] ?? 'N/A') ?></td>
                                        <td class="money-cell"><span class="cp"><?= $currency ?></span><?= number_format($p['procedure_price'] ?? 0, 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                    <!-- BILLS -->
                    <?php if (count($visit_bills) > 0): ?>
                        <div class="section-title" style="font-size: 11px; padding: 6px 10px; margin-bottom: 8px; margin-top: 10px;">
                            <i class="fas fa-file-invoice-dollar"></i>
                            Bills & Payments
                        </div>
                        
                        <?php foreach ($visit_bills as $b): 
                            $bill_items = $bill_items_by_bill[$b['id']] ?? [];
                        ?>
                            <div class="bill-box">
                                <div class="bill-header">
                                    <div class="bill-header-left">
                                        <span class="bill-number">
                                            <i class="fas fa-file-invoice"></i>
                                            <?= htmlspecialchars($b['bill_number'] ?? 'N/A') ?>
                                        </span>
                                        <span style="color: var(--text-secondary);">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($b['created_by_name'] ?? 'N/A') ?>
                                        </span>
                                        <span style="color: var(--text-secondary);">
                                            <i class="fas fa-credit-card"></i> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $b['payment_method'] ?? 'cash'))) ?>
                                        </span>
                                    </div>
                                    <span class="status-badge <?= getStatusClass($b['status']) ?>">
                                        <?= htmlspecialchars(ucfirst($b['status'] ?? 'N/A')) ?>
                                    </span>
                                </div>
                                
                                <?php if (count($bill_items) > 0): ?>
                                    <table class="pdf-table" style="margin-bottom: 8px;">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Type</th>
                                                <th style="text-align: center;">Qty</th>
                                                <th style="text-align: right;">Unit Price</th>
                                                <th style="text-align: right;">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bill_items as $bi): ?>
                                                <tr>
                                                    <td style="font-weight: 600;"><?= htmlspecialchars($bi['item_name'] ?? 'N/A') ?></td>
                                                    <td style="font-size: 9px; text-transform: uppercase; color: var(--blue-primary); font-weight: 700;">
                                                        <?= htmlspecialchars(str_replace('_', ' ', $bi['item_type'] ?? 'other')) ?>
                                                    </td>
                                                    <td class="mono" style="text-align: center; font-weight: 800;"><?= number_format($bi['quantity'] ?? 0) ?></td>
                                                    <td class="money-cell"><span class="cp"><?= $currency ?></span><?= number_format($bi['unit_price'] ?? 0, 0) ?></td>
                                                    <td class="money-cell"><span class="cp"><?= $currency ?></span><?= number_format($bi['total_price'] ?? 0, 0) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                                
                                <div class="bill-totals">
                                    <div class="bill-total-item">
                                        <div class="bill-total-label">Subtotal</div>
                                        <div class="money-cell"><span class="cp"><?= $currency ?></span><?= number_format($b['subtotal'] ?? 0, 0) ?></div>
                                    </div>
                                    
                                    <?php if (($b['pharmacy_discount'] ?? 0) > 0): ?>
                                    <div class="bill-total-item">
                                        <div class="bill-total-label pharm">Pharm Disc</div>
                                        <div class="money-cell purple"><span class="cp">-<?= $currency ?></span><?= number_format($b['pharmacy_discount'] ?? 0, 0) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (($b['cashier_discount'] ?? 0) > 0): ?>
                                    <div class="bill-total-item">
                                        <div class="bill-total-label cashier">Cashier Disc</div>
                                        <div class="money-cell info"><span class="cp">-<?= $currency ?></span><?= number_format($b['cashier_discount'] ?? 0, 0) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (($b['total_discount'] ?? 0) > 0 && ($b['pharmacy_discount'] ?? 0) == 0 && ($b['cashier_discount'] ?? 0) == 0): ?>
                                    <div class="bill-total-item">
                                        <div class="bill-total-label disc">Discount</div>
                                        <div class="money-cell danger"><span class="cp">-<?= $currency ?></span><?= number_format($b['total_discount'] ?? 0, 0) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (($b['premium_amount'] ?? 0) > 0): ?>
                                    <div class="bill-total-item">
                                        <div class="bill-total-label prem">Premium</div>
                                        <div class="money-cell warning"><span class="cp">+<?= $currency ?></span><?= number_format($b['premium_amount'] ?? 0, 0) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="bill-total-item" style="border-left: 2px solid var(--blue-primary); padding-left: 14px;">
                                        <div class="bill-total-label total">Total</div>
                                        <div class="money-cell blue" style="font-size: 13px;"><span class="cp"><?= $currency ?></span><?= number_format($b['total_amount'] ?? 0, 0) ?></div>
                                    </div>
                                    
                                    <div class="bill-total-item">
                                        <div class="bill-total-label paid">Paid</div>
                                        <div class="money-cell"><span class="cp"><?= $currency ?></span><?= number_format($b['paid_amount'] ?? 0, 0) ?></div>
                                    </div>
                                    
                                    <div class="bill-total-item" style="border-left: 2px solid <?= ($b['balance'] ?? 0) > 0 ? 'var(--danger)' : 'var(--success)' ?>; padding-left: 14px;">
                                        <div class="bill-total-label bal">Balance</div>
                                        <div class="money-cell <?= ($b['balance'] ?? 0) > 0 ? 'danger' : '' ?>" style="font-size: 13px; font-weight: 900;">
                                            <span class="cp"><?= $currency ?></span><?= number_format($b['balance'] ?? 0, 0) ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($b['premium_note'])): ?>
                                    <div style="margin-top: 6px; padding: 6px 8px; background: #FEF3C7; border-radius: 4px; font-size: 9px; color: var(--warning); font-weight: 600;">
                                        <i class="fas fa-star"></i> <strong>Premium Note:</strong> <?= htmlspecialchars($b['premium_note']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 30px; color: var(--text-secondary);">
            <i class="fas fa-hospital-user" style="font-size: 40px; opacity: 0.3; margin-bottom: 10px; display: block;"></i>
            <p style="font-weight: 700;">No visits recorded for this patient</p>
        </div>
    <?php endif; ?>
    
    <!-- ✅ FOOTER -->
    <div class="report-footer">
        <div class="footer-brand"><?= htmlspecialchars($site_name) ?></div>
        <div class="footer-slogan">
            <i class="fas fa-heart" style="color: #DC2626;"></i>
            Tunajali Afya Yako
        </div>
        
        <div class="footer-contacts">
            <?php if (count($admin_phones) > 0): ?>
                <?php foreach ($admin_phones as $admin): ?>
                    <span class="footer-contact-item">
                        <i class="fas fa-phone"></i>
                        <span class="mono"><?= htmlspecialchars($admin['phone']) ?></span>
                    </span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 10px; font-size: 9px; color: var(--text-secondary);">
            This is a computer-generated document. Printed on <?= $print_date ?> by <?= htmlspecialchars($print_by) ?>.
        </div>
    </div>
    
</div>

<!-- ================================================================ -->
<!-- ✅ PRINT CONTROLS -->
<!-- ================================================================ -->
<div class="print-controls">
    <button onclick="window.print()" class="print-btn">
        <i class="fas fa-print"></i>
        Print / Save as PDF
    </button>
    <button onclick="window.close()" class="close-btn">
        <i class="fas fa-times"></i>
        Close
    </button>
</div>

<script>
// ✅ Auto-open print dialog baada ya page kupakia
window.addEventListener('load', function() {
    // Subiri kidogo icons zote ziload
    setTimeout(function() {
        // Auto print (kama unataka). Kama hutaki, comment line hii.
        // window.print();
    }, 500);
});

console.log('%c📄 Patient PDF Report', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Logo + Jina + Slogan + Simu za Admin', 'font-size:12px; color:#34D399; font-weight:bold;');
console.log('%c✅ Patient: <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:12px; color:#0B5ED7; font-weight:bold;');
console.log('%c✅ Fonts: Inter + JetBrains Mono', 'font-size:12px; color:#34D399; font-weight:bold;');
</script>

</body>
</html>