<?php
// ================================================================
// FILE: frontend/pages/audit/view_prescription.php
// AUDIT - VIEW ALL PRESCRIPTIONS FOR A VISIT
// ✅ Branch ya aliye login TU
// ✅ Back button inafanya kazi kila wakati (goBack function)
// ✅ Single table design (kama OTC)
// ✅ All prescriptions in ONE table
// ✅ Blue theme (#0B5ED7)
// ✅ SCROLL BUTTONS < > kwenye table header
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

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Audit User';
$user_role = $_SESSION['role'] ?? 'audit';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$prescription_id = (int)($_GET['id'] ?? 0);
$visit_id = (int)($_GET['visit_id'] ?? 0);
$patient_id = (int)($_GET['patient_id'] ?? 0);

// ================================================================
// ✅ LAZIMISHA branch ya mtumiaji aliye login TU
// ================================================================
$selected_branch_id = (int)$user_branch_id;

if ($prescription_id <= 0 && $visit_id <= 0) {
    die("
        <!DOCTYPE html>
        <html>
        <head>
            <title>Invalid Request</title>
            <style>
                body { font-family: 'Inter', sans-serif; padding: 40px; background: #F1F5F9; text-align: center; }
                .box { max-width: 500px; margin: 0 auto; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-left: 4px solid #DC2626; }
                h2 { color: #DC2626; margin-bottom: 16px; }
                code { background: #F1F5F9; padding: 4px 8px; border-radius: 4px; font-family: monospace; color: #0B5ED7; font-size: 0.85rem; }
                .btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #0B5ED7; color: white; text-decoration: none; border-radius: 8px; font-weight: 700; }
            </style>
        </head>
        <body>
            <div class='box'>
                <h2>⚠️ Invalid Request</h2>
                <p style='color: #64748B;'>Prescription ID au Visit ID inahitajika.</p>
                <a href='other_services.php?tab=prescriptions' class='btn'>← Back to Prescriptions</a>
            </div>
        </body>
        </html>
    ");
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/backend/config/database.php';

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
// ✅ STEP 1: GET ALL PRESCRIPTIONS - LAZIMISHA BRANCH
// ================================================================
$prescriptions = [];

try {
    if ($visit_id > 0) {
        $stmt = $db->prepare("
            SELECT id, prescription_number, visit_id, patient_id, doctor_id, 
                   pharmacy_id, status, branch_id, created_at, updated_at, dispensed_at
            FROM prescriptions 
            WHERE visit_id = ? AND branch_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$visit_id, $selected_branch_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (empty($prescriptions) && $prescription_id > 0) {
        $stmt = $db->prepare("
            SELECT id, prescription_number, visit_id, patient_id, doctor_id, 
                   pharmacy_id, status, branch_id, created_at, updated_at, dispensed_at
            FROM prescriptions 
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$prescription_id, $selected_branch_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    error_log("Prescriptions fetch error: " . $e->getMessage());
}

if (empty($prescriptions)) {
    die("
        <!DOCTYPE html>
        <html>
        <head>
            <title>Prescriptions Not Found</title>
            <style>
                body { font-family: 'Inter', sans-serif; padding: 40px; background: #F1F5F9; text-align: center; }
                .box { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-left: 4px solid #D97706; }
                h2 { color: #D97706; margin-bottom: 16px; }
                code { background: #F1F5F9; padding: 4px 8px; border-radius: 4px; font-family: monospace; color: #0B5ED7; font-size: 0.85rem; }
                .btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #0B5ED7; color: white; text-decoration: none; border-radius: 8px; font-weight: 700; }
            </style>
        </head>
        <body>
            <div class='box'>
                <h2>⚠️ Prescriptions Not Found</h2>
                <p style='color: #64748B;'>Prescription ID: <code>" . htmlspecialchars((string)$prescription_id) . "</code></p>
                <p style='color: #64748B;'>Visit ID: <code>" . htmlspecialchars((string)$visit_id) . "</code></p>
                <a href='other_services.php?tab=prescriptions' class='btn'>← Back to Prescriptions</a>
            </div>
        </body>
        </html>
    ");
    exit;
}

$first_prescription = $prescriptions[0];
$total_prescriptions = count($prescriptions);

if ($patient_id <= 0 && !empty($first_prescription['patient_id'])) {
    $patient_id = (int)$first_prescription['patient_id'];
}

// ================================================================
// STEP 2: GET PATIENT INFO
// ================================================================
$patient = [];
if ($patient_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT id, patient_id, full_name, date_of_birth, gender, 
                   phone, email, address, blood_group, allergies
            FROM patients 
            WHERE id = ?
        ");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("Patient fetch error: " . $e->getMessage());
    }
}

// ================================================================
// STEP 3: GET VISIT INFO
// ================================================================
$visit = [];
$visit_id_final = $visit_id > 0 ? $visit_id : (int)($first_prescription['visit_id'] ?? 0);
if ($visit_id_final > 0) {
    try {
        $stmt = $db->prepare("
            SELECT id, visit_number, visit_date, patient_id, doctor_id, 
                   diagnosis, symptoms, treatment, branch_id
            FROM visits 
            WHERE id = ?
        ");
        $stmt->execute([$visit_id_final]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("Visit fetch error: " . $e->getMessage());
    }
}

// ================================================================
// STEP 4: GET DOCTOR INFO
// ================================================================
$doctor = [];
$doctor_id = (int)($first_prescription['doctor_id'] ?? 0);
if ($doctor_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT id, full_name, username, role, specialty 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$doctor_id]);
        $doctor = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("Doctor fetch error: " . $e->getMessage());
    }
}

// ================================================================
// STEP 5: GET BRANCH INFO
// ================================================================
$branch = [];
$branch_id = (int)($first_prescription['branch_id'] ?? 0);
if ($branch_id > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM branches WHERE id = ?");
        $stmt->execute([$branch_id]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("Branch fetch error: " . $e->getMessage());
    }
}

// ================================================================
// STEP 6: GET ALL PRESCRIPTION ITEMS
// ================================================================
$prescription_ids = array_column($prescriptions, 'id');
$prescription_map = [];
foreach ($prescriptions as $rx) {
    $prescription_map[$rx['id']] = $rx;
}

$all_items = [];

if (!empty($prescription_ids)) {
    try {
        $placeholders = implode(',', array_fill(0, count($prescription_ids), '?'));
        $sql = "SELECT 
            id, prescription_id, patient_id, inventory_id, medication_name, 
            dosage, frequency, quantity, duration, route, instructions, 
            pharmacy_instructions, unit_price, total_price, branch_id, 
            created_at, dispensed_at, dispensed_by
        FROM prescription_items 
        WHERE prescription_id IN ($placeholders)
        ORDER BY prescription_id ASC, id ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($prescription_ids);
        $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Prescription items error: " . $e->getMessage());
    }
}

// ================================================================
// STEP 7: GET RELATED BILL
// ================================================================
$related_bill = null;

if (!empty($prescription_ids)) {
    try {
        $placeholders = implode(',', array_fill(0, count($prescription_ids), '?'));
        $sql = "SELECT DISTINCT b.id, b.bill_number, b.total_amount, b.paid_amount, 
                       b.balance, b.status, b.created_at
                FROM bills b
                INNER JOIN bill_items bi ON bi.bill_id = b.id
                WHERE bi.reference_type = 'prescription'
                AND bi.reference_id IN ($placeholders)
                AND b.branch_id = ?
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $params = array_merge($prescription_ids, [$selected_branch_id]);
        $stmt->execute($params);
        $related_bill = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log("Related bill error: " . $e->getMessage());
    }
}

// ================================================================
// CALCULATIONS
// ================================================================
$grand_total_items = count($all_items);
$grand_total_qty = 0;
$grand_total_amount = 0;

foreach ($all_items as $item) {
    $grand_total_qty += (int)($item['quantity'] ?? 0);
    $grand_total_amount += (float)($item['total_price'] ?? 0);
}

// Status info
$status_info = [
    'pending' => ['label' => 'PENDING', 'icon' => 'fa-clock'],
    'confirmed' => ['label' => 'CONFIRMED', 'icon' => 'fa-check'],
    'dispensed' => ['label' => 'DISPENSED', 'icon' => 'fa-check-circle'],
    'paid' => ['label' => 'PAID', 'icon' => 'fa-money-bill-wave'],
    'cancelled' => ['label' => 'CANCELLED', 'icon' => 'fa-times-circle'],
    'rejected' => ['label' => 'REJECTED', 'icon' => 'fa-ban'],
];

// Patient age
$patient_age = 'N/A';
if (!empty($patient['date_of_birth'])) {
    try {
        $dob = new DateTime($patient['date_of_birth']);
        $now = new DateTime();
        $patient_age = $now->diff($dob)->y . ' yrs';
    } catch (Exception $e) {}
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/components/audit_header.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/components/audit_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions - <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></title>
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
    --primary-light: #60A5FA;
    --primary-bg: #1E3A5F;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3A2A1A;
    --purple-bg: #2D1B4E;
    --cyan-bg: #0E3A47;
}
* { font-family: var(--font-primary); -webkit-font-smoothing: antialiased; }
html, body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); }
.money-number, .money-cell, .font-mono, .rx-number, .stat-value, .qty-badge {
    font-family: var(--font-mono) !important;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

.page-header {
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 18px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25);
    position: relative;
    overflow: hidden;
}
.page-header::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 350px; height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.page-header .page-title {
    color: white;
    font-size: 1.4rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    letter-spacing: -0.02em;
}
.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }
.page-header .page-subtitle {
    color: rgba(255,255,255,0.9);
    font-size: 0.78rem;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 4px;
    position: relative;
    z-index: 1;
}
.branch-tag {
    background: rgba(255,255,255,0.18);
    color: white;
    padding: 3px 10px;
    border-radius: 16px;
    font-size: 0.65rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.15);
}
.branch-tag.user-tag {
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    font-weight: 800;
    box-shadow: 0 2px 8px rgba(252, 211, 77, 0.3);
}
.btn-header {
    background: rgba(255,255,255,0.18);
    color: white;
    border: 1px solid rgba(255,255,255,0.28);
    padding: 8px 14px;
    border-radius: 9px;
    font-weight: 600;
    font-size: 0.75rem;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    backdrop-filter: blur(4px);
    position: relative;
    z-index: 1;
    cursor: pointer;
}
.btn-header:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
}

.status-banner {
    border-radius: 14px;
    padding: 16px 22px;
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    color: white;
    box-shadow: var(--shadow-md);
    position: relative;
    overflow: hidden;
}
.status-banner::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 300px; height: 300px;
    background: rgba(255,255,255,0.08);
    border-radius: 50%;
    pointer-events: none;
}
.status-banner.confirmed { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
.status-banner.pending { background: linear-gradient(135deg, #D97706, #B45309); }
.status-banner.dispensed,
.status-banner.paid { background: linear-gradient(135deg, #059669, #047857); }
.status-banner.cancelled,
.status-banner.rejected { background: linear-gradient(135deg, #DC2626, #B91C1C); }

.status-banner .status-left {
    display: flex;
    align-items: center;
    gap: 14px;
    position: relative;
    z-index: 1;
}
.status-banner .status-icon {
    width: 54px; height: 54px;
    border-radius: 14px;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    border: 2px solid rgba(255,255,255,0.3);
    backdrop-filter: blur(4px);
}
.status-banner .status-info .status-label {
    font-size: 1.3rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    line-height: 1.1;
}
.status-banner .status-info .status-sub {
    font-size: 0.72rem;
    opacity: 0.9;
    font-weight: 500;
    margin-top: 3px;
}
.status-banner .status-count {
    background: rgba(255,255,255,0.25);
    padding: 8px 20px;
    border-radius: 12px;
    font-weight: 800;
    font-size: 1rem;
    letter-spacing: 0.02em;
    border: 2px solid rgba(255,255,255,0.3);
    position: relative;
    z-index: 1;
    text-align: center;
}
.status-banner .status-count .count-number {
    font-family: var(--font-mono);
    font-size: 1.5rem;
    font-weight: 900;
    line-height: 1;
    display: block;
    margin-bottom: 2px;
}
.status-banner .status-count .count-label {
    font-size: 0.6rem;
    text-transform: uppercase;
    opacity: 0.85;
    letter-spacing: 0.08em;
}

.view-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 18px;
}

.info-card {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--border-color);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}
.info-card:hover {
    box-shadow: var(--shadow-md);
    border-color: var(--primary);
}
.info-card .card-header {
    padding: 12px 18px;
    background: var(--primary-bg);
    border-bottom: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.82rem;
    font-weight: 800;
    color: var(--text-primary);
}
[data-theme="dark"] .info-card .card-header { background: #1E3A5F; }
.info-card .card-header i { color: var(--primary); font-size: 0.95rem; }
.info-card .card-body { padding: 16px 18px; }

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.8rem;
}
.info-row:last-child { border-bottom: none; padding-bottom: 0; }
.info-row:first-child { padding-top: 0; }
.info-row .label {
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 0.72rem;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}
.info-row .label i { color: var(--primary); font-size: 0.7rem; }
.info-row .value {
    color: var(--text-primary);
    font-weight: 700;
    text-align: right;
    word-break: break-word;
}
.info-row .value.mono {
    font-family: var(--font-mono);
    font-size: 0.78rem;
}

.patient-profile {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    background: linear-gradient(135deg, var(--primary-bg), #D6E4FF);
    border-bottom: 2px solid var(--border-color);
}
[data-theme="dark"] .patient-profile { background: linear-gradient(135deg, #1E3A5F, #16294A); }
.patient-avatar {
    width: 56px; height: 56px;
    border-radius: 14px;
    background: linear-gradient(135deg, #0B5ED7, #3B82F6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 900;
    text-transform: uppercase;
    flex-shrink: 0;
    box-shadow: 0 6px 16px rgba(11, 94, 215, 0.35);
    border: 3px solid var(--bg-card);
}
.patient-name {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--text-primary);
    letter-spacing: -0.02em;
    line-height: 1.2;
    margin-bottom: 4px;
}
.patient-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.patient-meta-item {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--bg-card);
    padding: 3px 9px;
    border-radius: 12px;
    border: 1px solid var(--border-color);
}
.patient-meta-item i { color: var(--primary); font-size: 0.62rem; }

.items-table-card {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--border-color);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    margin-bottom: 18px;
}
.items-table-card .card-header {
    padding: 14px 20px;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.items-table-card .card-header .title {
    color: white;
    font-size: 0.9rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 8px;
}
.items-table-card .card-header .title i { color: #93C5FD; font-size: 1rem; }
.items-table-card .card-header .count {
    color: rgba(255,255,255,0.9);
    font-size: 0.7rem;
    font-weight: 700;
    background: rgba(255,255,255,0.15);
    padding: 4px 12px;
    border-radius: 10px;
    backdrop-filter: blur(4px);
}

.table-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 10px 16px;
    background: var(--primary-bg);
    border-bottom: 2px solid var(--border-color);
}
[data-theme="dark"] .table-toolbar { background: rgba(10, 46, 92, 0.3); }

.table-toolbar-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 200px;
}
.table-toolbar-right {
    display: flex;
    align-items: center;
    gap: 6px;
}

.scroll-btn {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    border: 2px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-primary);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    transition: all 0.25s ease;
    flex-shrink: 0;
}

.scroll-btn:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}

.scroll-btn:active {
    transform: translateY(0) scale(0.95);
}

.scroll-btn i {
    font-size: 0.85rem;
}

.scroll-hint {
    font-size: 0.65rem;
    color: var(--text-secondary);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 0 8px;
}
.scroll-hint i {
    color: var(--primary);
    font-size: 0.7rem;
}

.table-scroll-wrapper {
    overflow-x: auto;
    scroll-behavior: smooth;
    position: relative;
}

.table-scroll-wrapper::-webkit-scrollbar {
    height: 8px;
}
.table-scroll-wrapper::-webkit-scrollbar-track {
    background: var(--border-color);
    border-radius: 10px;
}
.table-scroll-wrapper::-webkit-scrollbar-thumb {
    background: linear-gradient(90deg, #0B5ED7, #3B82F6);
    border-radius: 10px;
}
.table-scroll-wrapper::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(90deg, #0A4CA8, #0B5ED7);
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
    min-width: 1300px;
}
.items-table thead th {
    text-align: left;
    padding: 11px 16px;
    font-weight: 800;
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: white;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    white-space: nowrap;
}
.items-table tbody td {
    padding: 11px 16px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
    font-weight: 500;
}
.items-table tbody tr { transition: background 0.2s ease; }
.items-table tbody tr:hover td { background: var(--primary-bg); }
[data-theme="dark"] .items-table tbody tr:hover td { background: #1E3A5F; }
.items-table tbody tr:last-child td { border-bottom: none; }

.rx-badge {
    font-family: var(--font-mono);
    font-size: 0.65rem;
    font-weight: 800;
    padding: 3px 10px;
    border-radius: 6px;
    background: var(--primary-bg);
    color: var(--primary);
    display: inline-block;
    border: 1px solid rgba(11, 94, 215, 0.2);
    white-space: nowrap;
}
[data-theme="dark"] .rx-badge {
    background: #1E3A5F;
    color: #93C5FD;
    border-color: rgba(96, 165, 250, 0.3);
}

.qty-badge {
    font-family: var(--font-mono);
    font-size: 0.72rem;
    font-weight: 800;
    padding: 4px 12px;
    border-radius: 8px;
    background: linear-gradient(135deg, #0B5ED7, #3B82F6);
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    box-shadow: 0 2px 6px rgba(11, 94, 215, 0.3);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 9px;
    border-radius: 8px;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    white-space: nowrap;
}
.status-badge.pending { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
.status-badge.confirmed { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
.status-badge.dispensed, .status-badge.paid { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.status-badge.cancelled, .status-badge.rejected { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }

.money-cell {
    font-family: var(--font-mono);
    font-weight: 800;
    font-size: 0.82rem;
    text-align: right;
    color: var(--success);
    letter-spacing: -0.02em;
}
.money-cell .currency-prefix {
    font-size: 0.65rem;
    color: var(--text-secondary);
    margin-right: 2px;
    font-family: var(--font-primary);
    font-weight: 600;
}

.med-name {
    font-weight: 700;
    font-size: 0.85rem;
    color: var(--text-primary);
}

.totals-card {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--border-color);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    margin-bottom: 18px;
}
.totals-card .card-header {
    padding: 12px 20px;
    background: linear-gradient(135deg, #059669, #047857);
    color: white;
    font-size: 0.85rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 8px;
}
.totals-card .card-header i { color: #A7F3D0; }
.totals-body { padding: 8px 0; }
.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 20px;
    font-size: 0.85rem;
    border-bottom: 1px solid var(--border-color);
}
.total-row:last-child { border-bottom: none; }
.total-row .label {
    color: var(--text-secondary);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}
.total-row .label i { color: var(--primary); font-size: 0.8rem; width: 16px; text-align: center; }
.total-row .value {
    font-family: var(--font-mono);
    font-weight: 800;
    font-size: 0.92rem;
    color: var(--text-primary);
    letter-spacing: -0.02em;
}
.total-row .value.purple { color: var(--primary); }
.total-row.grand {
    background: linear-gradient(135deg, var(--primary-bg), #D6E4FF);
    border-top: 3px solid var(--primary);
    padding: 16px 20px;
}
[data-theme="dark"] .total-row.grand { background: linear-gradient(135deg, #1E3A5F, #16294A); }
.total-row.grand .label {
    color: var(--primary);
    font-size: 1rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.total-row.grand .value {
    color: var(--primary);
    font-size: 1.35rem;
    font-weight: 900;
}

.action-bar {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--border-color);
    padding: 16px 20px;
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    box-shadow: var(--shadow-sm);
}
.action-bar .action-info {
    display: flex;
    align-items: center;
    gap: 12px;
}
.action-bar .action-info .info-icon {
    width: 42px; height: 42px;
    border-radius: 12px;
    background: var(--primary-bg);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}
[data-theme="dark"] .action-bar .action-info .info-icon { background: #1E3A5F; }
.action-bar .action-info .info-text .info-title {
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--text-primary);
}
.action-bar .action-info .info-text .info-sub {
    font-size: 0.7rem;
    color: var(--text-secondary);
    font-weight: 500;
}
.action-bar .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
.btn {
    padding: 10px 18px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.8rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    text-decoration: none;
    white-space: nowrap;
    font-family: var(--font-primary);
}
.btn:hover { transform: translateY(-2px); }
.btn-primary {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}
.btn-primary:hover { box-shadow: 0 6px 20px rgba(11, 94, 215, 0.5); color: white; }
.btn-secondary {
    background: var(--bg-card);
    color: var(--text-primary);
    border: 2px solid var(--border-color);
}
.btn-secondary:hover { border-color: var(--primary); color: var(--primary); }

.empty-mini {
    text-align: center;
    padding: 30px 20px;
    color: var(--text-secondary);
}
.empty-mini i {
    font-size: 2.5rem;
    opacity: 0.3;
    display: block;
    margin-bottom: 10px;
    color: var(--primary);
}
.empty-mini p { font-weight: 600; font-size: 0.85rem; }

@media (max-width: 1024px) {
    .view-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .status-banner { padding: 14px 16px; }
    .status-banner .status-count .count-number { font-size: 1.2rem; }
    .status-banner .status-icon { width: 44px; height: 44px; font-size: 1.2rem; }
    .items-table { font-size: 0.72rem; }
    .items-table thead th, .items-table tbody td { padding: 8px 10px; }
    .action-bar { padding: 12px 14px; }
    .btn { padding: 8px 14px; font-size: 0.75rem; }
    .scroll-btn { width: 32px; height: 32px; }
}

@media print {
    .action-bar, .btn-header, .table-toolbar, .scroll-btn { display: none !important; }
    .page-header {
        background: #0B5ED7 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .status-banner {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription"></i>
                Prescription Details
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user-circle"></i>
                Karibu, <span class="branch-tag user-tag"><i class="fas fa-user"></i> <?= htmlspecialchars($user_full_name) ?></span>
                <?php if (!empty($visit['visit_number'])): ?>
                    <span class="branch-tag">
                        <i class="fas fa-clipboard-check"></i> <?= htmlspecialchars($visit['visit_number']) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($visit['visit_date'])): ?>
                    <span class="branch-tag">
                        <i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($visit['visit_date'])) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($branch['name'])): ?>
                    <span class="branch-tag">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch['name']) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($doctor['full_name'])): ?>
                    <span class="branch-tag">
                        <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                    </span>
                <?php endif; ?>
                <span class="branch-tag" style="background:rgba(255,255,255,0.3);font-weight:800;">
                    <i class="fas fa-prescription"></i> <?= $total_prescriptions ?> Rx
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <?php if ($related_bill): ?>
                <a href="/dispensary_system/frontend/pages/audit/view_bill.php?id=<?= $related_bill['id'] ?>" 
                   class="btn-header" target="_blank">
                    <i class="fas fa-file-invoice"></i> View Bill
                </a>
            <?php endif; ?>
            <!-- ✅ BACK BUTTON - INAFANYA KAZI KILA WAKATI -->
            <button type="button" onclick="goBack()" class="btn-header" style="cursor:pointer;">
                <i class="fas fa-arrow-left"></i> Back
            </button>
        </div>
    </div>

    <!-- STATUS BANNER -->
    <div class="status-banner confirmed">
        <div class="status-left">
            <div class="status-icon">
                <i class="fas fa-prescription"></i>
            </div>
            <div class="status-info">
                <div class="status-label">ALL PRESCRIPTIONS FOR VISIT</div>
                <div class="status-sub">
                    <i class="fas fa-calendar"></i>
                    <?= date('d M Y, H:i', strtotime($first_prescription['created_at'] ?? 'now')) ?>
                    <?php if (!empty($doctor['full_name'])): ?>
                        • <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="status-count">
            <span class="count-number"><?= $total_prescriptions ?></span>
            <span class="count-label">Prescription(s)</span>
        </div>
    </div>

    <!-- PATIENT & VISIT INFO GRID -->
    <div class="view-grid">
        
        <!-- PATIENT INFO -->
        <div class="info-card">
            <div class="card-header">
                <i class="fas fa-user-injured"></i> Patient Information
            </div>
            
            <?php if (!empty($patient['full_name'])): ?>
            <div class="patient-profile">
                <div class="patient-avatar">
                    <?= strtoupper(substr($patient['full_name'] ?? 'P', 0, 1)) ?>
                </div>
                <div style="flex:1;min-width:0;">
                    <div class="patient-name"><?= htmlspecialchars($patient['full_name']) ?></div>
                    <div class="patient-meta">
                        <?php if (!empty($patient['patient_id'])): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($patient['gender'])): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-<?= strtolower($patient['gender']) === 'female' ? 'venus' : 'mars' ?>"></i>
                                <?= htmlspecialchars($patient['gender']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($patient_age !== 'N/A'): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-birthday-cake"></i> <?= $patient_age ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="value mono"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-map-marker-alt"></i> Address</span>
                    <span class="value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-tint"></i> Blood Group</span>
                    <span class="value mono" style="color:var(--danger);font-weight:800;">
                        <?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-exclamation-triangle"></i> Allergies</span>
                    <span class="value" style="color:<?= !empty($patient['allergies']) ? 'var(--danger)' : 'var(--text-secondary)' ?>;">
                        <?= htmlspecialchars($patient['allergies'] ?? 'None') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- VISIT INFO -->
        <div class="info-card">
            <div class="card-header">
                <i class="fas fa-clipboard-check"></i> Visit Information
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-hashtag"></i> Visit #</span>
                    <span class="value mono" style="color:var(--primary);font-weight:800;">
                        <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-calendar"></i> Visit Date</span>
                    <span class="value">
                        <?= !empty($visit['visit_date']) ? date('d M Y, H:i', strtotime($visit['visit_date'])) : 'N/A' ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-user-md"></i> Doctor</span>
                    <span class="value">
                        Dr. <?= htmlspecialchars($doctor['full_name'] ?? 'N/A') ?>
                        <?php if (!empty($doctor['specialty'])): ?>
                            <span style="font-size:0.6rem;color:var(--text-secondary);display:block;">
                                <?= htmlspecialchars($doctor['specialty']) ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if (!empty($visit['diagnosis'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-diagnoses"></i> Diagnosis</span>
                    <span class="value"><?= htmlspecialchars($visit['diagnosis']) ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-store-alt"></i> Branch</span>
                    <span class="value"><?= htmlspecialchars($branch['name'] ?? 'N/A') ?></span>
                </div>
                <?php if ($related_bill): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-file-invoice"></i> Bill #</span>
                    <span class="value mono" style="color:var(--primary);">
                        <?= htmlspecialchars($related_bill['bill_number'] ?? 'N/A') ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- SINGLE TABLE WITH SCROLL BUTTONS -->
    <div class="items-table-card">
        <div class="card-header">
            <span class="title">
                <i class="fas fa-list"></i>
                Prescription Items (All Prescriptions)
            </span>
            <span class="count"><?= $grand_total_items ?> items</span>
        </div>
        
        <!-- TOOLBAR WITH SCROLL BUTTONS -->
        <div class="table-toolbar">
            <div class="table-toolbar-left">
                <span class="scroll-hint">
                    <i class="fas fa-arrows-alt-h"></i>
                    Scroll: <strong>‹ ›</strong> buttons or <strong>Alt + ←/→</strong>
                </span>
            </div>
            <div class="table-toolbar-right">
                <button type="button" 
                        class="scroll-btn" 
                        onclick="scrollItemsTable('left')" 
                        title="Scroll Left (Alt + ←)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                
                <button type="button" 
                        class="scroll-btn" 
                        onclick="scrollItemsTable('right')" 
                        title="Scroll Right (Alt + →)">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <?php if (count($all_items) > 0): ?>
        <div class="table-scroll-wrapper" id="itemsTableWrapper">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th style="width:160px;">Rx Number</th>
                        <th>Medication</th>
                        <th style="text-align:center;">Qty</th>
                        <th>Dosage</th>
                        <th>Frequency</th>
                        <th>Duration</th>
                        <th>Route</th>
                        <th>Instructions</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($all_items as $item): 
                        $rx_id = (int)($item['prescription_id'] ?? 0);
                        $rx_info = isset($prescription_map[$rx_id]) ? $prescription_map[$rx_id] : null;
                        $rx_number = $rx_info ? ($rx_info['prescription_number'] ?? 'N/A') : 'N/A';
                        $rx_status = $rx_info ? strtolower($rx_info['status'] ?? 'pending') : 'pending';
                        $rx_status_display = isset($status_info[$rx_status]) ? $status_info[$rx_status] : $status_info['pending'];
                        
                        $med_name = $item['medication_name'] ?? 'N/A';
                        $qty = (int)($item['quantity'] ?? 0);
                        $unit_price = (float)($item['unit_price'] ?? 0);
                        $total_price = (float)($item['total_price'] ?? 0);
                    ?>
                        <tr>
                            <td style="text-align:center;font-weight:700;color:var(--text-secondary);font-family:var(--font-mono);"><?= $i++ ?></td>
                            <td>
                                <span class="rx-badge">
                                    <i class="fas fa-prescription"></i>
                                    <?= htmlspecialchars($rx_number) ?>
                                </span>
                            </td>
                            <td>
                                <div class="med-name"><?= htmlspecialchars($med_name) ?></div>
                            </td>
                            <td style="text-align:center;">
                                <span class="qty-badge">
                                    <i class="fas fa-pills"></i> <?= number_format($qty) ?>
                                </span>
                            </td>
                            <td style="font-size:0.78rem;font-weight:600;color:var(--primary);">
                                <?= htmlspecialchars($item['dosage'] ?? '—') ?>
                            </td>
                            <td style="font-size:0.78rem;font-weight:600;color:var(--cyan);">
                                <?= htmlspecialchars($item['frequency'] ?? '—') ?>
                            </td>
                            <td style="font-size:0.78rem;font-weight:600;color:var(--warning);">
                                <?= htmlspecialchars($item['duration'] ?? '—') ?>
                            </td>
                            <td style="font-size:0.78rem;">
                                <?= htmlspecialchars($item['route'] ?? '—') ?>
                            </td>
                            <td style="font-size:0.72rem;color:var(--text-secondary);max-width:200px;">
                                <?= htmlspecialchars($item['instructions'] ?? $item['pharmacy_instructions'] ?? '—') ?>
                            </td>
                            <td class="money-cell">
                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($unit_price, 0) ?>
                            </td>
                            <td class="money-cell">
                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($total_price, 0) ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="status-badge <?= htmlspecialchars($rx_status) ?>">
                                    <i class="fas <?= htmlspecialchars($rx_status_display['icon']) ?>"></i>
                                    <?= htmlspecialchars($rx_status_display['label']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-mini">
            <i class="fas fa-pills"></i>
            <p>No medications in these prescriptions</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- GRAND TOTALS -->
    <div class="totals-card">
        <div class="card-header">
            <i class="fas fa-calculator"></i> Grand Total (All Prescriptions)
        </div>
        <div class="totals-body">
            <div class="total-row">
                <span class="label"><i class="fas fa-prescription"></i> Total Prescriptions</span>
                <span class="value purple"><?= number_format($total_prescriptions) ?></span>
            </div>
            <div class="total-row">
                <span class="label"><i class="fas fa-list"></i> Total Items</span>
                <span class="value purple"><?= number_format($grand_total_items) ?></span>
            </div>
            <div class="total-row">
                <span class="label"><i class="fas fa-pills"></i> Total Quantity</span>
                <span class="value purple"><?= number_format($grand_total_qty) ?> units</span>
            </div>
            <div class="total-row grand">
                <span class="label"><i class="fas fa-money-bill-wave"></i> Grand Total Amount</span>
                <span class="value">
                    <span style="font-size:0.85rem;"><?= $currency ?></span> <?= number_format($grand_total_amount, 0) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ACTION BAR -->
    <div class="action-bar">
        <div class="action-info">
            <div class="info-icon">
                <i class="fas fa-prescription"></i>
            </div>
            <div class="info-text">
                <div class="info-title">Prescription Actions</div>
                <div class="info-sub"><?= $total_prescriptions ?> prescription(s) • <?= $grand_total_items ?> item(s)</div>
            </div>
        </div>
        <div class="action-buttons">
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print All
            </button>
            <?php if ($related_bill): ?>
                <a href="/dispensary_system/frontend/pages/audit/view_bill.php?id=<?= $related_bill['id'] ?>" 
                   class="btn btn-primary" target="_blank">
                    <i class="fas fa-file-invoice"></i> View Bill
                </a>
            <?php endif; ?>
            <!-- ✅ BACK BUTTON - INAFANYA KAZI KILA WAKATI -->
            <button type="button" onclick="goBack()" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back
            </button>
        </div>
    </div>

</main>

<script>
// ================================================================
// ✅ SMART BACK FUNCTION - Inafanya kazi kila wakati
// ================================================================
function goBack() {
    var fallbackUrl = '/dispensary_system/frontend/pages/audit/other_services.php?tab=prescriptions';
    
    // Jaribu history.back() kwanza
    if (window.history.length > 1 && document.referrer && document.referrer !== '') {
        try {
            window.history.back();
            // Kama bado tuko hapa baada ya 500ms, tumia fallback
            setTimeout(function() {
                // Kama URL haijabadilika (bado tupo kwenye page hii)
                if (document.visibilityState === 'visible' && window.location.href.indexOf('view_prescription.php') !== -1) {
                    window.location.href = fallbackUrl;
                }
            }, 500);
        } catch (e) {
            window.location.href = fallbackUrl;
        }
    } else {
        // Direct visit - tumia fallback URL
        window.location.href = fallbackUrl;
    }
}

// ================================================================
// SCROLL TABLE FUNCTION
// ================================================================
function scrollItemsTable(direction) {
    var wrapper = document.getElementById('itemsTableWrapper');
    if (!wrapper) return;
    
    var scrollAmount = 350;
    var currentScroll = wrapper.scrollLeft;
    var targetScroll = direction === 'left' 
        ? currentScroll - scrollAmount 
        : currentScroll + scrollAmount;
    
    wrapper.scrollTo({
        left: targetScroll,
        behavior: 'smooth'
    });
    
    var btns = document.querySelectorAll('.scroll-btn');
    btns.forEach(function(btn) {
        btn.style.transform = 'scale(0.92)';
        setTimeout(function() { btn.style.transform = ''; }, 150);
    });
}

document.addEventListener('keydown', function(e) {
    if (e.altKey && e.key === 'ArrowLeft') {
        e.preventDefault();
        scrollItemsTable('left');
    }
    if (e.altKey && e.key === 'ArrowRight') {
        e.preventDefault();
        scrollItemsTable('right');
    }
});

document.addEventListener('DOMContentLoaded', function() {
    var wrapper = document.getElementById('itemsTableWrapper');
    if (wrapper) {
        setTimeout(function() {
            if (wrapper.scrollWidth > wrapper.clientWidth) {
                console.log('📜 Table scrollable — use < > buttons or Alt+←/→');
            }
        }, 500);
    }
});

console.log('%c💊 View Prescriptions - BRANCH LOCKED', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#F59E0B; font-weight:bold;');
console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?> (ID: <?= $selected_branch_id ?>)', 'font-size:13px; color:#10B981; font-weight:bold;');
console.log('%c✅ Visit: <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7; font-weight:bold;');
console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
console.log('%c💊 Total Prescriptions: <?= $total_prescriptions ?>', 'font-size:13px; color:#0B5ED7;');
console.log('%c💊 Total Items: <?= $grand_total_items ?> | Total Qty: <?= $grand_total_qty ?>', 'font-size:13px; color:#0B5ED7;');
console.log('%c✅ Back button inafanya kazi (goBack function)', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ Scroll: < > buttons au Alt+←/→', 'font-size:13px; color:#34D399; font-weight:bold;');
</script>

</body>
</html>