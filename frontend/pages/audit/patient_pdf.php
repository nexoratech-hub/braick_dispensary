<?php
// ================================================================
// FILE: C:\xampp\htdocs\dispensary_system\frontend\pages\audit\patient_pdf.php
// AUDIT - PATIENT PDF (V2 FULL - FULL PATHS + LOGO DETECTION)
// ✅ Full absolute paths (hakuna ../../../)
// ✅ Logo detection + Base64 + SVG fallback
// ✅ Kila Visit na Card yake (Blue Margin)
// ✅ Official Stamp / Muhuli section chini
// ✅ Fonts: Inter + JetBrains Mono
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$allowed_roles = ['admin', 'audit'];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Audit User';
$user_role = $_SESSION['role'] ?? 'audit';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

$is_admin = ($user_role === 'admin');
$is_audit = ($user_role === 'audit');

$patient_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($patient_id <= 0) {
    die("Invalid patient ID");
}

// ================================================================
// ✅ ABSOLUTE PATHS (Full Paths)
// ================================================================
$document_root = $_SERVER['DOCUMENT_ROOT'] ?? 'C:/xampp/htdocs';
$system_root = $document_root . '/dispensary_system';

// ✅ DATABASE - Full Path
require_once $system_root . '/backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $db->exec("SET time_zone = '+03:00'");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// ✅ LOGO DETECTION - Full Paths + Base64
// ================================================================
$logo_base64 = '';
$logo_found_path = '';
$logo_found = false;

// Full paths za kuangalia
$possible_logo_paths = [
    // 1. Main Braick logo (uploads/profiles)
    $system_root . '/frontend/assets/uploads/profiles/braick_logo.png',
    $system_root . '/frontend/assets/uploads/profiles/Braick_logo.png',
    $system_root . '/frontend/assets/uploads/profiles/BRAICK_LOGO.png',
    
    // 2. Generic logo names
    $system_root . '/frontend/assets/uploads/profiles/logo.png',
    $system_root . '/frontend/assets/uploads/profiles/Logo.png',
    
    // 3. assets/images folder
    $system_root . '/frontend/assets/images/braick_logo.png',
    $system_root . '/frontend/assets/images/logo.png',
    
    // 4. Root assets folder
    $system_root . '/assets/uploads/profiles/braick_logo.png',
    $system_root . '/assets/images/logo.png',
    $system_root . '/assets/logo.png',
    
    // 5. Fallback - page folder
    $system_root . '/frontend/pages/audit/braick_logo.png',
    $system_root . '/frontend/pages/audit/logo.png',
    $system_root . '/frontend/pages/admin/audit/braick_logo.png',
    
    // 6. System root
    $system_root . '/braick_logo.png',
    $system_root . '/logo.png',
];

foreach ($possible_logo_paths as $path) {
    if (file_exists($path) && is_readable($path)) {
        $logo_found_path = $path;
        $logo_found = true;
        
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = 'image/png';
        if ($ext === 'jpg' || $ext === 'jpeg') $mime = 'image/jpeg';
        elseif ($ext === 'gif') $mime = 'image/gif';
        elseif ($ext === 'svg') $mime = 'image/svg+xml';
        elseif ($ext === 'webp') $mime = 'image/webp';
        
        $logo_data = file_get_contents($path);
        if ($logo_data !== false && strlen($logo_data) > 0) {
            $logo_base64 = 'data:' . $mime . ';base64,' . base64_encode($logo_data);
        }
        break;
    }
}

// Fallback SVG (Braick "B" logo)
if (!$logo_found || empty($logo_base64)) {
    $svg_logo = '<svg xmlns="http://www.w3.org/2000/svg" width="90" height="90" viewBox="0 0 90 90">' .
        '<defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">' .
        '<stop offset="0%" style="stop-color:#0B5ED7"/>' .
        '<stop offset="100%" style="stop-color:#7C3AED"/>' .
        '</linearGradient></defs>' .
        '<rect width="90" height="90" rx="12" fill="url(#g)"/>' .
        '<text x="45" y="62" text-anchor="middle" fill="white" font-size="48" font-weight="900" font-family="Arial,sans-serif">B</text>' .
        '</svg>';
    
    $logo_base64 = 'data:image/svg+xml;base64,' . base64_encode($svg_logo);
}

// ================================================================
// SYSTEM SETTINGS
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
// ADMIN PHONES
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
// GET VISITS
// ================================================================
$visits = [];
try {
    $stmt = $db->prepare("
        SELECT v.*, 
               u.full_name AS doctor_name,
               u.specialty AS doctor_specialty,
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
// VITALS BY VISIT
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
        if (!isset($vitals_by_visit[$vid])) {
            $vitals_by_visit[$vid] = $v;
        }
    }
} catch (Exception $e) {}

// ================================================================
// BILLS BY VISIT
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
// BILL ITEMS BY BILL + BY VISIT+TYPE
// ================================================================
$bill_items_by_bill = [];
$items_by_visit_type = [];
try {
    $stmt = $db->prepare("
        SELECT bi.*, b.visit_id
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE b.patient_id = ?
        ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_items as $item) {
        $bid = $item['bill_id'] ?? 0;
        if (!isset($bill_items_by_bill[$bid])) $bill_items_by_bill[$bid] = [];
        $bill_items_by_bill[$bid][] = $item;
        
        $vid = $item['visit_id'] ?? 0;
        $item_type = $item['item_type'] ?? 'other';
        $key = $vid . '_' . $item_type;
        if (!isset($items_by_visit_type[$key])) $items_by_visit_type[$key] = [];
        $items_by_visit_type[$key][] = $item;
    }
} catch (Exception $e) {}

// ================================================================
// PAYMENTS BY BILL
// ================================================================
$payments_by_bill = [];
try {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name AS received_by_name
        FROM payments p
        INNER JOIN bills b ON p.bill_id = b.id
        LEFT JOIN users u ON p.received_by = u.id
        WHERE b.patient_id = ?
        ORDER BY p.received_at DESC
    ");
    $stmt->execute([$patient_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $bid = $p['bill_id'] ?? 0;
        if (!isset($payments_by_bill[$bid])) $payments_by_bill[$bid] = [];
        $payments_by_bill[$bid][] = $p;
    }
} catch (Exception $e) {}

// ================================================================
// PRESCRIPTIONS BY VISIT
// ================================================================
$prescriptions_by_visit = [];
try {
    $stmt = $db->prepare("
        SELECT pr.*, u.full_name AS doctor_name,
               ph.full_name AS pharmacist_name
        FROM prescriptions pr
        LEFT JOIN users u ON pr.doctor_id = u.id
        LEFT JOIN users ph ON pr.pharmacy_id = ph.id
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
// LAB TESTS BY VISIT
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
        'paid' => 'success', 'completed' => 'success', 'confirmed' => 'success',
        'dispensed' => 'success', 'pending' => 'warning', 'partial' => 'info',
        'in_progress' => 'info', 'assigned' => 'info', 'waiting' => 'warning',
        'scheduled' => 'info', 'cancelled' => 'danger', 'rejected' => 'danger',
    ];
    return $map[$status] ?? 'secondary';
}

function hasVitalValue($value) {
    if ($value === null) return false;
    if ($value === '') return false;
    if ($value === '0') return false;
    if ($value === '0.0' || $value === '0.00') return false;
    return true;
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
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
/* ================================================================
   FONTS + THEME
   ================================================================ */
:root {
    --font-primary: 'Inter', -apple-system, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    
    --blue-primary: #0B5ED7;
    --blue-primary-dark: #0A4CA8;
    --blue-primary-bg: #E8F0FE;
    --blue-primary-light: #3B82F6;
    
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
    --info: #0891B2;
    --info-bg: #CFFAFE;
    --teal: #0D9488;
    --teal-bg: #CCFBF1;
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
   HEADER
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

/* ✅ LOGO STYLES */
.header-logo {
    width: 90px;
    height: 90px;
    object-fit: contain;
    border-radius: 12px;
    border: 2px solid var(--blue-primary);
    padding: 5px;
    background: white;
    flex-shrink: 0;
    display: block;
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
    gap: 8px;
    font-size: 11px;
}

.header-contact-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: var(--blue-primary-bg);
    padding: 3px 10px;
    border-radius: 6px;
    color: var(--blue-primary);
    font-weight: 700;
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
   PATIENT INFO BOX
   ================================================================ */
.patient-box {
    background: #F8FAFC;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 14px;
    margin-bottom: 18px;
    border-left: 4px solid var(--blue-primary);
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
   VISIT SECTION - BLUE MARGIN
   ================================================================ */
.visits-container {
    display: flex;
    flex-direction: column;
    gap: 30px;
    margin-bottom: 20px;
}

.visit-card {
    background: white;
    border: 3px solid var(--blue-primary);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(11, 94, 215, 0.15);
    page-break-inside: avoid;
    position: relative;
}

.visit-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 6px;
    background: linear-gradient(90deg, #0B5ED7, #3B82F6, #7C3AED);
}

.visit-header {
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #7C3AED 100%);
    color: white;
    padding: 14px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 6px;
}

.visit-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.visit-badge {
    background: rgba(255,255,255,0.2);
    padding: 5px 12px;
    border-radius: 6px;
    font-family: var(--font-mono);
    font-size: 12px;
    font-weight: 900;
    border: 1.5px solid rgba(255,255,255,0.3);
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.visit-badge-light {
    background: rgba(255,255,255,0.15);
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid rgba(255,255,255,0.2);
}

.visit-status-badge {
    background: rgba(255,255,255,0.25);
    color: white;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border: 1.5px solid rgba(255,255,255,0.3);
}

.visit-body {
    padding: 0;
}

.visit-section {
    border-bottom: 2px dashed var(--border-color);
    padding: 14px 18px;
}

.visit-section:last-child {
    border-bottom: none;
}

.visit-section-title {
    font-size: 12px;
    font-weight: 900;
    color: var(--blue-primary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--blue-primary);
}

.visit-section-title i {
    font-size: 14px;
}

.visit-section-title .section-count {
    margin-left: auto;
    background: var(--blue-primary-bg);
    color: var(--blue-primary);
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 800;
    font-family: var(--font-mono);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}

.info-item {
    background: #F8FAFC;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 8px 10px;
}

.info-label {
    font-size: 9px;
    font-weight: 800;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 3px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.info-label i {
    color: var(--blue-primary);
    font-size: 10px;
}

.info-value {
    font-size: 11px;
    font-weight: 700;
    color: var(--text-primary);
    word-break: break-word;
}

.info-value.mono {
    font-family: var(--font-mono);
    font-size: 10px;
}

.info-item.highlight-purple {
    background: var(--purple-bg);
    border-color: var(--purple);
}

.info-item.highlight-purple .info-label i { color: var(--purple); }
.info-item.highlight-purple .info-value { color: var(--purple); font-weight: 900; }

/* Vitals Grid */
.vitals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(115px, 1fr));
    gap: 8px;
}

.vital-item {
    background: #F8FAFC;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 10px 12px;
    border-top: 3px solid var(--blue-primary);
}

.vital-item.temp { border-top-color: var(--danger); }
.vital-item.bp { border-top-color: var(--purple); }
.vital-item.pulse { border-top-color: #DB2777; }
.vital-item.resp { border-top-color: var(--info); }
.vital-item.oxygen { border-top-color: var(--success); }
.vital-item.glucose { border-top-color: var(--warning); }
.vital-item.weight { border-top-color: var(--blue-primary); }
.vital-item.height { border-top-color: #4F46E5; }
.vital-item.bmi { border-top-color: var(--teal); }
.vital-item.muac { border-top-color: #F59E0B; }
.vital-item.pain { border-top-color: #EF4444; }

.vital-label {
    font-size: 8px;
    font-weight: 800;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 3px;
}

.vital-label i { font-size: 9px; }

.vital-item.temp .vital-label i, .vital-item.temp .vital-value { color: var(--danger); }
.vital-item.bp .vital-label i, .vital-item.bp .vital-value { color: var(--purple); }
.vital-item.pulse .vital-label i, .vital-item.pulse .vital-value { color: #DB2777; }
.vital-item.resp .vital-label i, .vital-item.resp .vital-value { color: var(--info); }
.vital-item.oxygen .vital-label i, .vital-item.oxygen .vital-value { color: var(--success); }
.vital-item.glucose .vital-label i, .vital-item.glucose .vital-value { color: var(--warning); }
.vital-item.weight .vital-label i, .vital-item.weight .vital-value { color: var(--blue-primary); }
.vital-item.height .vital-label i, .vital-item.height .vital-value { color: #4F46E5; }
.vital-item.bmi .vital-label i, .vital-item.bmi .vital-value { color: var(--teal); }
.vital-item.muac .vital-label i, .vital-item.muac .vital-value { color: #F59E0B; }
.vital-item.pain .vital-label i, .vital-item.pain .vital-value { color: #EF4444; }

.vital-value {
    font-size: 14px;
    font-weight: 900;
    font-family: var(--font-mono);
    color: var(--text-primary);
    letter-spacing: -0.03em;
    line-height: 1.1;
}

.vital-value .unit {
    font-size: 9px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-left: 2px;
}

/* Diagnosis Boxes */
.diagnosis-box {
    background: rgba(124, 58, 237, 0.08);
    border: 2px solid var(--purple);
    border-left: 5px solid var(--purple);
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 10px;
}

.diagnosis-label {
    font-size: 10px;
    font-weight: 900;
    color: var(--purple);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
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
    margin-top: 5px;
}

.treatment-box {
    background: rgba(5, 150, 105, 0.08);
    border: 2px solid var(--success);
    border-left: 5px solid var(--success);
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 10px;
}

.treatment-label {
    font-size: 10px;
    font-weight: 900;
    color: var(--success);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.treatment-value {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.6;
}

.symptoms-box {
    background: rgba(217, 119, 6, 0.08);
    border: 2px solid var(--warning);
    border-left: 5px solid var(--warning);
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 10px;
}

.symptoms-label {
    font-size: 10px;
    font-weight: 900;
    color: var(--warning);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.symptoms-value {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.6;
}

/* Table */
.pdf-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10px;
    margin-bottom: 10px;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid var(--border-color);
}

.pdf-table thead th {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    padding: 7px 9px;
    text-align: left;
    font-weight: 800;
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    white-space: nowrap;
}

.pdf-table tbody td {
    padding: 7px 9px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
    font-weight: 500;
}

.pdf-table tbody tr:nth-child(even) td {
    background: rgba(11, 94, 215, 0.02);
}

.pdf-table tbody tr:last-child td {
    border-bottom: none;
}

.pdf-table tbody tr.total-row td {
    background: var(--blue-primary-bg);
    font-weight: 900;
    color: var(--blue-primary);
    border-top: 2px solid var(--blue-primary);
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
.money-cell.teal { color: var(--teal); }

.money-cell .cp {
    font-size: 8px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-right: 2px;
}

/* Bill Box */
.bill-box {
    border: 2px solid var(--border-color);
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
    background: #F8FAFC;
}

.bill-box:last-child {
    margin-bottom: 0;
}

.bill-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 2px dashed var(--border-color);
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
    font-weight: 900;
    color: var(--blue-primary);
    background: var(--blue-primary-bg);
    padding: 4px 10px;
    border-radius: 5px;
    font-size: 10px;
    border: 1px solid var(--blue-primary);
}

.bill-totals {
    display: flex;
    justify-content: flex-end;
    gap: 14px;
    flex-wrap: wrap;
    padding-top: 10px;
    margin-top: 10px;
    border-top: 2px dashed var(--border-color);
}

.bill-total-item {
    text-align: right;
    min-width: 80px;
}

.bill-total-label {
    font-size: 8px;
    font-weight: 800;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 3px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 3px;
}

.bill-total-label.pharm { color: var(--purple); }
.bill-total-label.cashier { color: var(--info); }
.bill-total-label.disc { color: var(--danger); }
.bill-total-label.prem { color: var(--warning); }
.bill-total-label.total { color: var(--blue-primary); }
.bill-total-label.paid { color: var(--success); }
.bill-total-label.bal { color: var(--danger); }

.payment-history {
    margin-top: 10px;
    padding: 10px;
    background: var(--success-bg);
    border-radius: 6px;
    border-left: 4px solid var(--success);
}

.payment-history-title {
    font-size: 10px;
    font-weight: 900;
    color: var(--success);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.status-badge.success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.status-badge.warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
.status-badge.danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
.status-badge.info { background: var(--info-bg); color: var(--info); border: 1px solid var(--info); }
.status-badge.secondary { background: #E2E8F0; color: var(--text-secondary); border: 1px solid var(--border-color); }

/* ================================================================
   OFFICIAL STAMP SECTION
   ================================================================ */
.stamp-section {
    margin-top: 40px;
    padding-top: 25px;
    border-top: 3px dashed var(--blue-primary);
    page-break-inside: avoid;
}

.stamp-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    align-items: end;
}

.stamp-certification {
    padding: 15px 0;
}

.stamp-certification-title {
    font-size: 13px;
    font-weight: 900;
    color: var(--blue-primary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.stamp-certification-text {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-secondary);
    line-height: 1.7;
    margin-bottom: 10px;
}

.stamp-certification-text strong {
    color: var(--text-primary);
}

.stamp-info-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--border-color);
}

.stamp-info-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.stamp-info-label {
    font-size: 9px;
    font-weight: 800;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.stamp-info-value {
    font-size: 11px;
    font-weight: 800;
    color: var(--text-primary);
    font-family: var(--font-mono);
}

.stamp-area {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 10px 0;
}

.stamp-box {
    width: 220px;
    height: 140px;
    border: 3px dashed var(--blue-primary);
    border-radius: 12px;
    background: linear-gradient(135deg, #F8FAFC, #E8F0FE);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    position: relative;
    overflow: hidden;
    box-shadow: inset 0 0 20px rgba(11, 94, 215, 0.05);
}

.stamp-box::before {
    content: '';
    position: absolute;
    top: 8px;
    left: 8px;
    right: 8px;
    bottom: 8px;
    border: 1.5px solid rgba(11, 94, 215, 0.2);
    border-radius: 8px;
    pointer-events: none;
}

.stamp-corner {
    position: absolute;
    width: 18px;
    height: 18px;
    border-color: var(--blue-primary);
    border-style: solid;
}

.stamp-corner.tl { top: 5px; left: 5px; border-width: 2px 0 0 2px; border-radius: 6px 0 0 0; }
.stamp-corner.tr { top: 5px; right: 5px; border-width: 2px 2px 0 0; border-radius: 0 6px 0 0; }
.stamp-corner.bl { bottom: 5px; left: 5px; border-width: 0 0 2px 2px; border-radius: 0 0 0 6px; }
.stamp-corner.br { bottom: 5px; right: 5px; border-width: 0 2px 2px 0; border-radius: 0 0 6px 0; }

.stamp-icon {
    font-size: 32px;
    color: var(--blue-primary);
    opacity: 0.3;
    margin-bottom: 5px;
}

.stamp-text {
    font-size: 11px;
    font-weight: 900;
    color: var(--blue-primary);
    opacity: 0.6;
    text-transform: uppercase;
    letter-spacing: 0.15em;
    text-align: center;
    line-height: 1.4;
}

.stamp-subtext {
    font-size: 8px;
    font-weight: 700;
    color: var(--text-secondary);
    opacity: 0.7;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    text-align: center;
    margin-top: 4px;
}

.stamp-caption {
    font-size: 10px;
    font-weight: 800;
    color: var(--blue-primary);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-top: 12px;
    text-align: center;
    display: flex;
    align-items: center;
    gap: 5px;
    justify-content: center;
}

.stamp-caption::before,
.stamp-caption::after {
    content: '';
    width: 30px;
    height: 2px;
    background: var(--blue-primary);
    opacity: 0.4;
}

.stamp-signature-line {
    margin-top: 10px;
    width: 220px;
    text-align: center;
}

.stamp-signature-dash {
    border-top: 2px dashed var(--border-color);
    margin-bottom: 5px;
}

.stamp-signature-label {
    font-size: 9px;
    font-weight: 800;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

/* ================================================================
   FOOTER
   ================================================================ */
.report-footer {
    margin-top: 25px;
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
    font-weight: 900;
    font-size: 14px;
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
    gap: 8px;
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

/* Print Controls */
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
        padding: 8mm 6mm;
        max-width: 100%;
    }
    
    .print-controls {
        display: none !important;
    }
    
    .visit-card {
        page-break-inside: avoid;
        break-inside: avoid;
        margin-bottom: 20px;
    }
    
    .visit-section {
        page-break-inside: avoid;
    }
    
    .pdf-table {
        page-break-inside: avoid;
    }
    
    .bill-box {
        page-break-inside: avoid;
    }
    
    .stamp-section {
        page-break-inside: avoid;
        break-inside: avoid;
    }
    
    .stamp-box {
        border-color: var(--blue-primary) !important;
        background: linear-gradient(135deg, #F8FAFC, #E8F0FE) !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    @page {
        size: A4;
        margin: 10mm 8mm;
    }
}

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
    
    .info-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .stamp-container {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}
    </style>
</head>
<body>

<div class="pdf-page">
    
    <!-- ================================================================
         HEADER - NA LOGO
         ================================================================ -->
    <div class="report-header">
        <div class="header-left">
            <img src="<?= $logo_base64 ?>" 
                 alt="<?= htmlspecialchars($site_name) ?>" 
                 class="header-logo">
            
            <div class="header-info">
                <div class="header-title"><?= htmlspecialchars($site_name) ?></div>
                <div class="header-slogan">
                    <i class="fas fa-heart" style="color: #DC2626;"></i>
                    Tunajali Afya Yako
                </div>
                
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
    
    <!-- ================================================================
         PATIENT INFORMATION
         ================================================================ -->
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
                <div class="patient-value mono" style="color: var(--danger); font-weight: 900;"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></div>
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
            <div class="patient-item" style="grid-column: span 3; background: var(--danger-bg); border-color: var(--danger);">
                <div class="patient-label" style="color: var(--danger);">
                    <i class="fas fa-exclamation-triangle"></i> Allergies
                </div>
                <div class="patient-value" style="color: var(--danger);"><?= htmlspecialchars($patient['allergies']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ================================================================
         STATS
         ================================================================ -->
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
    
    <!-- ================================================================
         VISITS
         ================================================================ -->
    <?php if (count($visits) > 0): ?>
        <div class="visits-container">
            <?php foreach ($visits as $visit_index => $visit): 
                $vid = $visit['id'];
                $visit_vital = $vitals_by_visit[$vid] ?? null;
                $visit_bills = $bills_by_visit[$vid] ?? [];
                $visit_prescriptions = $prescriptions_by_visit[$vid] ?? [];
                $visit_labs = $lab_tests_by_visit[$vid] ?? [];
                $visit_procedures = $items_by_visit_type[$vid . '_procedure'] ?? [];
                $visit_equipment = $items_by_visit_type[$vid . '_equipment'] ?? [];
            ?>
                <div class="visit-card">
                    
                    <!-- VISIT HEADER -->
                    <div class="visit-header">
                        <div class="visit-header-left">
                            <span class="visit-badge">
                                <i class="fas fa-hashtag"></i>
                                <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                            </span>
                            <span class="visit-badge-light">
                                <i class="fas fa-calendar-alt"></i>
                                <?= date('d M Y', strtotime($visit['visit_date'] ?? 'now')) ?>
                            </span>
                            <span class="visit-badge-light">
                                <i class="fas fa-clock"></i>
                                <?= date('H:i', strtotime($visit['visit_date'] ?? 'now')) ?>
                            </span>
                            <?php if (!empty($visit['visit_type'])): ?>
                            <span class="visit-badge-light">
                                <i class="fas fa-tag"></i>
                                <?= htmlspecialchars($visit['visit_type']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <span class="visit-status-badge">
                            <i class="fas fa-<?= getStatusClass($visit['status']) === 'success' ? 'check-circle' : 'clock' ?>"></i>
                            <?= strtoupper(htmlspecialchars(str_replace('_', ' ', $visit['status'] ?? 'PENDING'))) ?>
                        </span>
                    </div>
                    
                    <!-- VISIT BODY -->
                    <div class="visit-body">
                        
                        <!-- SECTION 1: VISIT INFORMATION -->
                        <div class="visit-section">
                            <div class="visit-section-title">
                                <i class="fas fa-clipboard-list"></i>
                                Visit Information
                            </div>
                            
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-hashtag"></i> Visit Number</div>
                                    <div class="info-value mono"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-calendar-alt"></i> Date & Time</div>
                                    <div class="info-value mono"><?= date('d M Y, H:i', strtotime($visit['visit_date'] ?? 'now')) ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-stethoscope"></i> Visit Type</div>
                                    <div class="info-value"><?= htmlspecialchars($visit['visit_type'] ?? 'N/A') ?></div>
                                </div>
                                
                                <div class="info-item highlight-purple">
                                    <div class="info-label"><i class="fas fa-user-md"></i> Doctor (Aliyehudumia)</div>
                                    <div class="info-value"><?= htmlspecialchars($visit['doctor_name'] ?? 'Not assigned') ?></div>
                                </div>
                                
                                <div class="info-item highlight-purple">
                                    <div class="info-label"><i class="fas fa-user-tie"></i> Reception (Aliyecreate)</div>
                                    <div class="info-value"><?= htmlspecialchars($visit['receptionist_name'] ?? 'N/A') ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-info-circle"></i> Status</div>
                                    <div class="info-value">
                                        <span class="status-badge <?= getStatusClass($visit['status']) ?>">
                                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $visit['status'] ?? 'N/A'))) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- SECTION 2: VITAL SIGNS -->
                        <?php if ($visit_vital !== null): 
                            $has_vital = false;
                            $fields = ['temperature', 'blood_pressure_systolic', 'pulse_rate', 
                                       'respiratory_rate', 'oxygen_saturation', 'blood_glucose', 
                                       'weight', 'height', 'bmi', 'muac', 'pain_score'];
                            foreach ($fields as $f) {
                                if (hasVitalValue($visit_vital[$f] ?? null)) {
                                    $has_vital = true;
                                    break;
                                }
                            }
                        ?>
                            <?php if ($has_vital): ?>
                            <div class="visit-section">
                                <div class="visit-section-title">
                                    <i class="fas fa-heartbeat"></i>
                                    Vital Signs
                                    <?php if (!empty($visit_vital['recorded_by_name'])): ?>
                                    <span class="section-count">
                                        <i class="fas fa-user-nurse"></i>
                                        <?= htmlspecialchars($visit_vital['recorded_by_name']) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="vitals-grid">
                                    <?php if (hasVitalValue($visit_vital['temperature'] ?? null)): ?>
                                    <div class="vital-item temp">
                                        <div class="vital-label"><i class="fas fa-thermometer-half"></i> Temp</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['temperature']) ?><span class="unit">°C</span></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['blood_pressure_systolic'] ?? null) && hasVitalValue($visit_vital['blood_pressure_diastolic'] ?? null)): ?>
                                    <div class="vital-item bp">
                                        <div class="vital-label"><i class="fas fa-heart"></i> Blood Pressure</div>
                                        <div class="vital-value"><?= $visit_vital['blood_pressure_systolic'] ?>/<?= $visit_vital['blood_pressure_diastolic'] ?><span class="unit">mmHg</span></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['pulse_rate'] ?? null)): ?>
                                    <div class="vital-item pulse">
                                        <div class="vital-label"><i class="fas fa-heartbeat"></i> Pulse Rate</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['pulse_rate']) ?><span class="unit">bpm</span></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['respiratory_rate'] ?? null)): ?>
                                    <div class="vital-item resp">
                                        <div class="vital-label"><i class="fas fa-lungs"></i> Resp Rate</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['respiratory_rate']) ?><span class="unit">/min</span></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['oxygen_saturation'] ?? null)): ?>
                                    <div class="vital-item oxygen">
                                        <div class="vital-label"><i class="fas fa-wind"></i> Oxygen Sat.</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['oxygen_saturation']) ?><span class="unit">%</span></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['blood_glucose'] ?? null)): ?>
                                    <div class="vital-item glucose">
                                        <div class="vital-label"><i class="fas fa-cube"></i> Glucose</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['blood_glucose']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['weight'] ?? null)): ?>
                                    <div class="vital-item weight">
                                        <div class="vital-label"><i class="fas fa-weight"></i> Weight</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['weight']) ?><span class="unit">kg</span></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['height'] ?? null)): ?>
                                    <div class="vital-item height">
                                        <div class="vital-label"><i class="fas fa-ruler-vertical"></i> Height</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['height']) ?><span class="unit">cm</span></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['bmi'] ?? null)): ?>
                                    <div class="vital-item bmi">
                                        <div class="vital-label"><i class="fas fa-chart-line"></i> BMI</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['bmi']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['muac'] ?? null)): ?>
                                    <div class="vital-item muac">
                                        <div class="vital-label"><i class="fas fa-child"></i> MUAC</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['muac']) ?><span class="unit">cm</span></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['pain_score'] ?? null)): ?>
                                    <div class="vital-item pain">
                                        <div class="vital-label"><i class="fas fa-face-frown"></i> Pain</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['pain_score']) ?><span class="unit">/10</span></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- SECTION 3: DIAGNOSIS & TREATMENT -->
                        <?php if (!empty($visit['symptoms']) || !empty($visit['diagnosis']) || !empty($visit['disease_name']) || !empty($visit['treatment'])): ?>
                        <div class="visit-section">
                            <div class="visit-section-title">
                                <i class="fas fa-stethoscope"></i>
                                Diagnosis & Treatment
                            </div>
                            
                            <?php if (!empty($visit['symptoms'])): ?>
                            <div class="symptoms-box">
                                <div class="symptoms-label">
                                    <i class="fas fa-notes-medical"></i> Symptoms / Chief Complaint
                                </div>
                                <div class="symptoms-value"><?= nl2br(htmlspecialchars($visit['symptoms'])) ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($visit['diagnosis']) || !empty($visit['disease_name'])): ?>
                            <div class="diagnosis-box">
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
                            
                            <?php if (!empty($visit['treatment'])): ?>
                            <div class="treatment-box">
                                <div class="treatment-label">
                                    <i class="fas fa-prescription-bottle-medical"></i> Treatment Plan
                                </div>
                                <div class="treatment-value"><?= nl2br(htmlspecialchars($visit['treatment'])) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- SECTION 4: LAB TESTS -->
                        <?php if (count($visit_labs) > 0): ?>
                        <div class="visit-section">
                            <div class="visit-section-title">
                                <i class="fas fa-flask"></i>
                                Lab Tests & Results
                                <span class="section-count"><?= count($visit_labs) ?> Tests</span>
                            </div>
                            
                            <table class="pdf-table">
                                <thead>
                                    <tr>
                                        <th style="width: 30px;">#</th>
                                        <th>Test Name</th>
                                        <th>Result</th>
                                        <th>Reference</th>
                                        <th>Lab Technician</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $lab_num = 1; foreach ($visit_labs as $lt): ?>
                                        <tr>
                                            <td style="text-align: center; font-weight: 800; color: var(--text-secondary); font-family: var(--font-mono);"><?= $lab_num++ ?></td>
                                            <td style="font-weight: 700;"><?= htmlspecialchars($lt['test_name'] ?? 'N/A') ?></td>
                                            <td class="mono" style="font-weight: 900; color: var(--success); font-size: 10px;">
                                                <?= htmlspecialchars(substr($lt['results'] ?? '-', 0, 50)) ?>
                                                <?= strlen($lt['results'] ?? '') > 50 ? '...' : '' ?>
                                            </td>
                                            <td style="color: var(--text-secondary); font-size: 9px;"><?= htmlspecialchars($lt['reference_range'] ?? '-') ?></td>
                                            <td style="font-size: 10px;">
                                                <i class="fas fa-user-flask" style="color: var(--info);"></i>
                                                <?= htmlspecialchars($lt['technician_name'] ?? 'N/A') ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?= getStatusClass($lt['status']) ?>">
                                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $lt['status'] ?? 'N/A'))) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                        <!-- SECTION 5: MEDICATIONS -->
                        <?php if (count($visit_prescriptions) > 0): ?>
                        <div class="visit-section">
                            <div class="visit-section-title">
                                <i class="fas fa-pills"></i>
                                Medications / Prescriptions
                                <span class="section-count"><?= count($visit_prescriptions) ?> Prescriptions</span>
                            </div>
                            
                            <?php foreach ($visit_prescriptions as $pr): 
                                $pr_items = $prescription_items_by_prescription[$pr['id']] ?? [];
                            ?>
                                <div style="margin-bottom: 12px; padding: 10px; background: var(--purple-bg); border-radius: 6px; border-left: 4px solid var(--purple);">
                                    <div style="font-size: 10px; font-weight: 900; margin-bottom: 8px; color: var(--purple); display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                        <i class="fas fa-prescription"></i>
                                        <span class="mono"><?= htmlspecialchars($pr['prescription_number'] ?? 'N/A') ?></span>
                                        <span style="color: var(--text-secondary); font-weight: 700;">
                                            <i class="fas fa-user-md"></i> <?= htmlspecialchars($pr['doctor_name'] ?? 'N/A') ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (count($pr_items) > 0): ?>
                                        <table class="pdf-table" style="margin-bottom: 0;">
                                            <thead>
                                                <tr>
                                                    <th style="width: 30px;">#</th>
                                                    <th>Medication</th>
                                                    <th>Dosage</th>
                                                    <th>Frequency</th>
                                                    <th style="text-align: center;">Qty</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $med_num = 1; foreach ($pr_items as $item): ?>
                                                    <tr>
                                                        <td style="text-align: center; font-weight: 800; color: var(--text-secondary); font-family: var(--font-mono);"><?= $med_num++ ?></td>
                                                        <td style="font-weight: 900; color: var(--purple);"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($item['dosage'] ?? '-') ?></td>
                                                        <td><?= htmlspecialchars($item['frequency'] ?? '-') ?></td>
                                                        <td class="mono" style="text-align: center; font-weight: 900; color: var(--purple);">
                                                            <?= number_format($item['quantity'] ?? 0) ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- SECTION 6: PROCEDURES -->
                        <?php if (count($visit_procedures) > 0): ?>
                        <div class="visit-section">
                            <div class="visit-section-title">
                                <i class="fas fa-procedures"></i>
                                Procedures
                                <span class="section-count"><?= count($visit_procedures) ?> Procedures</span>
                            </div>
                            
                            <table class="pdf-table">
                                <thead>
                                    <tr>
                                        <th style="width: 30px;">#</th>
                                        <th>Procedure Name</th>
                                        <th style="text-align: center;">Qty</th>
                                        <th style="text-align: right;">Unit Price</th>
                                        <th style="text-align: right;">Total</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $proc_num = 1; foreach ($visit_procedures as $p): ?>
                                        <tr>
                                            <td style="text-align: center; font-weight: 800; color: var(--text-secondary); font-family: var(--font-mono);"><?= $proc_num++ ?></td>
                                            <td style="font-weight: 700; color: var(--teal);"><?= htmlspecialchars($p['item_name'] ?? 'N/A') ?></td>
                                            <td class="mono" style="text-align: center; font-weight: 900; color: var(--teal);">
                                                <?= number_format($p['quantity'] ?? 0) ?>
                                            </td>
                                            <td class="money-cell teal"><span class="cp"><?= $currency ?></span><?= number_format($p['unit_price'] ?? 0, 0) ?></td>
                                            <td class="money-cell teal"><span class="cp"><?= $currency ?></span><?= number_format($p['total_price'] ?? 0, 0) ?></td>
                                            <td>
                                                <span class="status-badge <?= getStatusClass($p['status']) ?>">
                                                    <?= htmlspecialchars(ucfirst($p['status'] ?? 'N/A')) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="total-row">
                                        <td colspan="4" style="text-align: right; font-weight: 900;">TOTAL PROCEDURES</td>
                                        <td class="money-cell teal" style="font-size: 11px; font-weight: 900;">
                                            <?php 
                                            $proc_total = 0;
                                            foreach ($visit_procedures as $p) {
                                                $proc_total += (float)($p['total_price'] ?? 0);
                                            }
                                            echo '<span class="cp">' . $currency . '</span>' . number_format($proc_total, 0);
                                            ?>
                                        </td>
                                        <td style="text-align: center; font-weight: 900;"><?= count($visit_procedures) ?> items</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                        <!-- SECTION 7: MEDICAL EQUIPMENT -->
                        <?php if (count($visit_equipment) > 0): ?>
                        <div class="visit-section">
                            <div class="visit-section-title">
                                <i class="fas fa-tools"></i>
                                Medical Equipment
                                <span class="section-count"><?= count($visit_equipment) ?> Items</span>
                            </div>
                            
                            <table class="pdf-table">
                                <thead>
                                    <tr>
                                        <th style="width: 30px;">#</th>
                                        <th>Equipment Name</th>
                                        <th style="text-align: center;">Qty</th>
                                        <th style="text-align: right;">Unit Price</th>
                                        <th style="text-align: right;">Total</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $eq_num = 1; foreach ($visit_equipment as $e): ?>
                                        <tr>
                                            <td style="text-align: center; font-weight: 800; color: var(--text-secondary); font-family: var(--font-mono);"><?= $eq_num++ ?></td>
                                            <td style="font-weight: 700; color: var(--purple);"><?= htmlspecialchars($e['item_name'] ?? 'N/A') ?></td>
                                            <td class="mono" style="text-align: center; font-weight: 900; color: var(--purple);">
                                                <?= number_format($e['quantity'] ?? 0) ?>
                                            </td>
                                            <td class="money-cell purple"><span class="cp"><?= $currency ?></span><?= number_format($e['unit_price'] ?? 0, 0) ?></td>
                                            <td class="money-cell purple"><span class="cp"><?= $currency ?></span><?= number_format($e['total_price'] ?? 0, 0) ?></td>
                                            <td>
                                                <span class="status-badge <?= getStatusClass($e['status']) ?>">
                                                    <?= htmlspecialchars(ucfirst($e['status'] ?? 'N/A')) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="total-row">
                                        <td colspan="4" style="text-align: right; font-weight: 900;">TOTAL EQUIPMENT</td>
                                        <td class="money-cell purple" style="font-size: 11px; font-weight: 900;">
                                            <?php 
                                            $eq_total = 0;
                                            foreach ($visit_equipment as $e) {
                                                $eq_total += (float)($e['total_price'] ?? 0);
                                            }
                                            echo '<span class="cp">' . $currency . '</span>' . number_format($eq_total, 0);
                                            ?>
                                        </td>
                                        <td style="text-align: center; font-weight: 900;"><?= count($visit_equipment) ?> items</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                        <!-- SECTION 8: BILLS & PAYMENTS -->
                        <?php if (count($visit_bills) > 0): ?>
                        <div class="visit-section">
                            <div class="visit-section-title">
                                <i class="fas fa-file-invoice-dollar"></i>
                                Bills & Payments
                                <span class="section-count"><?= count($visit_bills) ?> Bill(s)</span>
                            </div>
                            
                            <?php foreach ($visit_bills as $b): 
                                $bill_items = $bill_items_by_bill[$b['id']] ?? [];
                                $bill_payments = $payments_by_bill[$b['id']] ?? [];
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
                                            <span style="color: var(--text-secondary); font-family: var(--font-mono);">
                                                <i class="fas fa-calendar"></i> <?= date('d M Y, H:i', strtotime($b['created_at'] ?? 'now')) ?>
                                            </span>
                                        </div>
                                        <span class="status-badge <?= getStatusClass($b['status']) ?>">
                                            <?= htmlspecialchars(ucfirst($b['status'] ?? 'N/A')) ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (count($bill_items) > 0): ?>
                                        <table class="pdf-table" style="margin-bottom: 0;">
                                            <thead>
                                                <tr>
                                                    <th style="width: 30px;">#</th>
                                                    <th>Item</th>
                                                    <th>Type</th>
                                                    <th style="text-align: center;">Qty</th>
                                                    <th style="text-align: right;">Unit</th>
                                                    <th style="text-align: right;">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $bi_num = 1; foreach ($bill_items as $bi): ?>
                                                    <tr>
                                                        <td style="text-align: center; font-weight: 800; color: var(--text-secondary); font-family: var(--font-mono);"><?= $bi_num++ ?></td>
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
                                    
                                    <?php if (count($bill_payments) > 0): ?>
                                    <div class="payment-history">
                                        <div class="payment-history-title">
                                            <i class="fas fa-check-circle"></i> Payment History
                                        </div>
                                        <table class="pdf-table" style="margin-bottom: 0; background: white;">
                                            <thead>
                                                <tr>
                                                    <th>Receipt #</th>
                                                    <th>Method</th>
                                                    <th>Received By</th>
                                                    <th>Date & Time</th>
                                                    <th style="text-align: right;">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($bill_payments as $pay): ?>
                                                    <tr>
                                                        <td class="mono" style="font-weight: 800; color: var(--success);"><?= htmlspecialchars($pay['receipt_number'] ?? 'N/A') ?></td>
                                                        <td style="font-size: 10px;"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $pay['payment_method'] ?? 'Cash'))) ?></td>
                                                        <td style="font-size: 10px;"><?= htmlspecialchars($pay['received_by_name'] ?? 'N/A') ?></td>
                                                        <td class="mono" style="font-size: 9px; color: var(--text-secondary);"><?= date('d M Y, H:i', strtotime($pay['received_at'] ?? 'now')) ?></td>
                                                        <td class="money-cell"><span class="cp"><?= $currency ?></span><?= number_format($pay['amount'] ?? 0, 0) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
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
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 30px; color: var(--text-secondary);">
            <i class="fas fa-hospital-user" style="font-size: 40px; opacity: 0.3; margin-bottom: 10px; display: block;"></i>
            <p style="font-weight: 700;">No visits recorded for this patient</p>
        </div>
    <?php endif; ?>
    
    <!-- ================================================================
         OFFICIAL STAMP / MUHULI SECTION
         ================================================================ -->
    <div class="stamp-section">
        <div class="stamp-container">
            
            <!-- LEFT: Certification -->
            <div class="stamp-certification">
                <div class="stamp-certification-title">
                    <i class="fas fa-certificate"></i>
                    Official Certification
                </div>
                
                <div class="stamp-certification-text">
                    This is to certify that the medical records contained in this report are 
                    true and accurate as recorded at <strong><?= htmlspecialchars($site_name) ?></strong>.
                    This document is issued for official purposes and is valid only with the 
                    official stamp and signature below.
                </div>
                
                <div class="stamp-info-row">
                    <div class="stamp-info-item">
                        <div class="stamp-info-label">Patient ID</div>
                        <div class="stamp-info-value"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></div>
                    </div>
                    <div class="stamp-info-item">
                        <div class="stamp-info-label">Date Issued</div>
                        <div class="stamp-info-value"><?= date('d M Y') ?></div>
                    </div>
                    <div class="stamp-info-item">
                        <div class="stamp-info-label">Report Ref</div>
                        <div class="stamp-info-value">PR-<?= str_pad($patient_id, 6, '0', STR_PAD_LEFT) ?></div>
                    </div>
                </div>
            </div>
            
            <!-- RIGHT: Stamp / Muhuli Area -->
            <div class="stamp-area">
                <div class="stamp-box">
                    <div class="stamp-corner tl"></div>
                    <div class="stamp-corner tr"></div>
                    <div class="stamp-corner bl"></div>
                    <div class="stamp-corner br"></div>
                    
                    <i class="fas fa-stamp stamp-icon"></i>
                    <div class="stamp-text">
                        Official<br>
                        Stamp Here
                    </div>
                    <div class="stamp-subtext">
                        Muhuli wa Dispensary
                    </div>
                </div>
                
                <div class="stamp-caption">
                    <i class="fas fa-stamp"></i>
                    Gonga Muhuli Hapa
                </div>
                
                <div class="stamp-signature-line">
                    <div class="stamp-signature-dash"></div>
                    <div class="stamp-signature-label">Authorized Signature</div>
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- ================================================================
         FOOTER
         ================================================================ -->
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

<!-- PRINT CONTROLS -->
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
console.log('%c📄 Audit Patient PDF - FULL PATHS + LOGO', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Role: <?= $user_role ?>', 'font-size:12px; color:#34D399; font-weight:bold;');
console.log('%c✅ Document Root: <?= htmlspecialchars($document_root) ?>', 'font-size:12px; color:#7C3AED;');
console.log('%c✅ System Root: <?= htmlspecialchars($system_root) ?>', 'font-size:12px; color:#7C3AED;');
console.log('%c✅ Logo Found: <?= $logo_found ? 'YES' : 'NO (using SVG fallback)' ?>', 'font-size:12px; color:<?= $logo_found ? '#34D399' : '#FBBF24' ?>; font-weight:bold;');
<?php if ($logo_found): ?>
console.log('%c✅ Logo Path: <?= htmlspecialchars($logo_found_path) ?>', 'font-size:11px; color:#34D399;');
<?php endif; ?>
console.log('%c✅ Kila Visit na Card yake (Blue Margin)', 'font-size:12px; color:#34D399; font-weight:bold;');
console.log('%c✅ Official Stamp / Muhuli section chini', 'font-size:12px; color:#34D399; font-weight:bold;');
</script>

</body>
</html>