<?php
// ================================================================
// FILE: frontend/pages/audit/patient_details.php
// AUDIT - PATIENT DETAILS (V2 FINAL - SAWA NA ADMIN V6)
// ✅ Allow both admin and audit roles
// ✅ CSS nzuri sana (modern design)
// ✅ Kila Visit na Card yake (blue margin)
// ✅ Sections 9: Patient → Visit → Vitals → Labs → Diagnosis → Meds → Procedures → Equipment → Bills
// ✅ Procedures kutoka bill_items (status = paid)
// ✅ Doctor + Reception aliyecreate
// ✅ View-only mode kwa audit
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

// ✅ Base path
$base_path = $is_admin 
    ? '/dispensary_system/frontend/pages/admin/audit' 
    : '/dispensary_system/frontend/pages/audit';

$patient_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($patient_id <= 0) {
    header('Location: ' . $base_path . '/patients.php?branch=' . urlencode($selected_branch_id));
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $db->exec("SET time_zone = '+03:00'");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET PATIENT
// ================================================================
$patient = null;
try {
    $stmt = $db->prepare("
        SELECT p.*, b.name AS branch_name,
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

if (!$patient) die("Patient not found");

// ================================================================
// GET VISITS
// ================================================================
$visits = [];
try {
    $stmt = $db->prepare("
        SELECT v.*, 
               u_doctor.full_name AS doctor_name, 
               u_doctor.role AS doctor_role,
               u_doctor.specialty AS doctor_specialty,
               u_reception.full_name AS receptionist_name,
               u_reception.role AS receptionist_role,
               d.disease_name, 
               d.disease_code
        FROM visits v
        LEFT JOIN users u_doctor ON v.doctor_id = u_doctor.id
        LEFT JOIN users u_reception ON v.receptionist_id = u_reception.id
        LEFT JOIN diseases d ON v.disease_id = d.id
        WHERE v.patient_id = ?
        ORDER BY v.visit_date DESC
    ");
    $stmt->execute([$patient_id]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// GET VITALS - GROUPED BY VISIT (latest only)
// ================================================================
$vitals_by_visit = [];
try {
    $stmt = $db->prepare("
        SELECT vs.*, u.full_name AS recorded_by_name, u.role AS recorded_by_role
        FROM vital_signs vs
        LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE vs.patient_id = ?
        ORDER BY vs.recorded_at DESC
    ");
    $stmt->execute([$patient_id]);
    $all_vitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_vitals as $v) {
        $vid = $v['visit_id'] ?? 0;
        if (!isset($vitals_by_visit[$vid])) {
            $vitals_by_visit[$vid] = $v;
        }
    }
} catch (Exception $e) {}

// ================================================================
// GET BILLS - GROUPED BY VISIT
// ================================================================
$bills_by_visit = [];
$all_bills = [];
try {
    $stmt = $db->prepare("
        SELECT b.*, u.full_name AS created_by_name, u.role AS created_by_role,
               c.full_name AS cashier_name, c.role AS cashier_role
        FROM bills b 
        LEFT JOIN users u ON b.created_by = u.id
        LEFT JOIN users c ON b.created_by = c.id
        WHERE b.patient_id = ? 
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $all_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all_bills as $b) {
        $vid = $b['visit_id'] ?? 0;
        if ($vid > 0) {
            if (!isset($bills_by_visit[$vid])) $bills_by_visit[$vid] = [];
            $bills_by_visit[$vid][] = $b;
        }
    }
} catch (Exception $e) {}

// ================================================================
// GET BILL ITEMS - GROUPED BY BILL + BY VISIT+TYPE
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
    $all_bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_bill_items as $item) {
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
// GET PAYMENTS - GROUPED BY BILL
// ================================================================
$payments_by_bill = [];
try {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name AS received_by_name, u.role AS received_by_role
        FROM payments p 
        INNER JOIN bills b ON p.bill_id = b.id
        LEFT JOIN users u ON p.received_by = u.id
        WHERE b.patient_id = ?
        ORDER BY p.received_at DESC
    ");
    $stmt->execute([$patient_id]);
    $all_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all_payments as $p) {
        $bid = $p['bill_id'] ?? 0;
        if (!isset($payments_by_bill[$bid])) $payments_by_bill[$bid] = [];
        $payments_by_bill[$bid][] = $p;
    }
} catch (Exception $e) {}

// ================================================================
// GET PRESCRIPTIONS - GROUPED BY VISIT
// ================================================================
$prescriptions_by_visit = [];
try {
    $stmt = $db->prepare("
        SELECT pr.*, u.full_name AS doctor_name, u.specialty AS doctor_specialty,
               ph.full_name AS pharmacist_name,
               (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = pr.id) AS item_count
        FROM prescriptions pr 
        LEFT JOIN users u ON pr.doctor_id = u.id
        LEFT JOIN users ph ON pr.pharmacy_id = ph.id
        WHERE pr.patient_id = ? 
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $all_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all_prescriptions as $pr) {
        $vid = $pr['visit_id'] ?? 0;
        if (!isset($prescriptions_by_visit[$vid])) $prescriptions_by_visit[$vid] = [];
        $prescriptions_by_visit[$vid][] = $pr;
    }
} catch (Exception $e) {}

$prescription_items_by_prescription = [];
try {
    $stmt = $db->prepare("
        SELECT pi.* FROM prescription_items pi
        INNER JOIN prescriptions pr ON pi.prescription_id = pr.id
        WHERE pr.patient_id = ?
    ");
    $stmt->execute([$patient_id]);
    $all_prescription_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all_prescription_items as $item) {
        $pid = $item['prescription_id'] ?? 0;
        if (!isset($prescription_items_by_prescription[$pid])) $prescription_items_by_prescription[$pid] = [];
        $prescription_items_by_prescription[$pid][] = $item;
    }
} catch (Exception $e) {}

// ================================================================
// GET LAB TESTS - GROUPED BY VISIT
// ================================================================
$lab_tests_by_visit = [];
try {
    $stmt = $db->prepare("
        SELECT lt.*, 
               u.full_name AS technician_name,
               u.role AS technician_role,
               u.specialty AS technician_specialty
        FROM lab_tests lt 
        LEFT JOIN users u ON lt.lab_technician_id = u.id
        WHERE lt.patient_id = ? 
        ORDER BY lt.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $all_lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all_lab_tests as $lt) {
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
    'lab_tests' => count($all_lab_tests ?? []),
    'prescriptions' => count($all_prescriptions ?? []),
    'procedures' => 0,
];
foreach (($all_bills ?? []) as $b) {
    $stats['total_spent'] += (float)($b['total_amount'] ?? 0);
    $stats['total_paid'] += (float)($b['paid_amount'] ?? 0);
    $stats['total_balance'] += (float)($b['balance'] ?? 0);
}
if (isset($items_by_visit_type)) {
    foreach ($items_by_visit_type as $key => $items) {
        if (strpos($key, '_procedure') !== false) {
            $stats['procedures'] += count($items);
        }
    }
}

// ================================================================
// CURRENCY
// ================================================================
$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $currency = $row['setting_value'];
} catch (Exception $e) {}

// ================================================================
// HELPERS
// ================================================================
function calculateAge($dob) {
    if (!$dob) return 'N/A';
    try {
        $birthDate = new DateTime($dob);
        $today = new DateTime();
        return $today->diff($birthDate)->y . ' yrs';
    } catch (Exception $e) { return 'N/A'; }
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

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once __DIR__ . '/../../components/audit_header.php';
include_once __DIR__ . '/../../components/audit_sidebar.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ================================================================
   V6: MODERN THEME + BEAUTIFUL DESIGN
   ================================================================ */
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
    --text-muted: #94A3B8;
    --border-color: #E2E8F0;
    --border-strong: #CBD5E1;
    
    --success: #059669;
    --success-light: #34D399;
    --success-bg: #D1FAE5;
    
    --danger: #DC2626;
    --danger-light: #F87171;
    --danger-bg: #FEE2E2;
    
    --warning: #D97706;
    --warning-light: #FBBF24;
    --warning-bg: #FEF3C7;
    
    --purple: #7C3AED;
    --purple-light: #A78BFA;
    --purple-bg: #EDE9FE;
    
    --cyan: #0891B2;
    --cyan-light: #22D3EE;
    --cyan-bg: #CFFAFE;
    
    --teal: #0D9488;
    --teal-bg: #CCFBF1;
    
    --pink: #DB2777;
    --pink-light: #F472B6;
    --pink-bg: #FCE7F3;
    
    --slate: #64748B;
    --slate-bg: #F1F5F9;
    
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    --shadow-xl: 0 20px 50px rgba(0,0,0,0.15);
    
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --radius-xl: 20px;
    --radius-full: 9999px;
}

[data-theme="dark"] {
    --primary: #3B82F6;
    --primary-dark: #2563EB;
    --primary-bg: #1E3A5F;
    --bg-body: #0B1220;
    --bg-card: #111C33;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --text-muted: #64748B;
    --border-color: #1E2E4A;
    --border-strong: #2A3E5F;
    --success-bg: #0F2E22;
    --danger-bg: #3A1414;
    --warning-bg: #3A2A0F;
    --purple-bg: #2A1A4A;
    --cyan-bg: #0A2E3A;
    --teal-bg: #0A2E2A;
    --pink-bg: #4A1E36;
    --slate-bg: #1E2A3D;
}

* {
    font-family: var(--font-primary);
    -webkit-font-smoothing: antialiased;
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    background: var(--bg-body);
    color: var(--text-primary);
    font-size: 14px;
    line-height: 1.5;
}

.mono, .money-cell, .vital-value, .mono-value, .stat-value {
    font-family: var(--font-mono) !important;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

/* ================================================================
   PAGE HEADER - MODERN GRADIENT
   ================================================================ */
.page-header {
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #7C3AED 100%);
    border-radius: var(--radius-lg);
    padding: 22px 28px;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 10px 40px rgba(11, 94, 215, 0.35);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.page-header .page-title {
    color: white;
    font-size: 1.5rem;
    font-weight: 900;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}

.page-header .page-title i { 
    font-size: 1.6rem; 
    color: #93C5FD;
}

.page-header .page-subtitle {
    color: rgba(255,255,255,0.9);
    font-size: 0.82rem;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin-top: 6px;
}

.branch-tag {
    background: rgba(255,255,255,0.18);
    color: white;
    padding: 5px 12px;
    border-radius: var(--radius-full);
    font-size: 0.7rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
}

.role-tag {
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    padding: 4px 12px;
    border-radius: var(--radius-full);
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

.role-tag.audit-role {
    background: linear-gradient(135deg, #3B82F6, #2563EB);
    color: white;
}

.btn-outline-light {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1.5px solid rgba(255,255,255,0.3);
    padding: 10px 18px;
    border-radius: var(--radius-sm);
    font-weight: 700;
    font-size: 0.78rem;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    position: relative;
    z-index: 1;
    cursor: pointer;
    backdrop-filter: blur(10px);
}

.btn-outline-light:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.2);
}

.btn-export-pdf {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    color: white;
    border: none;
    padding: 10px 18px;
    border-radius: var(--radius-sm);
    font-weight: 800;
    font-size: 0.78rem;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    position: relative;
    z-index: 1;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(220, 38, 38, 0.35);
}

.btn-export-pdf:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(220, 38, 38, 0.5);
}

/* ================================================================
   ALLERGY ALERT
   ================================================================ */
.allergy-alert {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, rgba(220, 38, 38, 0.3), rgba(220, 38, 38, 0.15));
    color: white;
    padding: 8px 16px;
    border-radius: var(--radius-sm);
    font-size: 0.75rem;
    font-weight: 800;
    border: 1.5px solid rgba(220, 38, 38, 0.5);
    margin-top: 8px;
    position: relative;
    z-index: 1;
    backdrop-filter: blur(10px);
    animation: pulseAlert 2s infinite;
}

@keyframes pulseAlert {
    0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); }
    50% { box-shadow: 0 0 0 10px rgba(220, 38, 38, 0); }
}

/* ================================================================
   PROFILE CARD
   ================================================================ */
.profile-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    padding: 24px 28px;
    border: 2px solid var(--border-color);
    margin-bottom: 20px;
    box-shadow: var(--shadow-md);
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.profile-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #0B5ED7, #3B82F6, #7C3AED);
}

.profile-avatar {
    width: 88px;
    height: 88px;
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    font-size: 2rem;
    color: white;
    background: linear-gradient(135deg, #0B5ED7, #3B82F6);
    box-shadow: 0 8px 24px rgba(11, 94, 215, 0.4);
    flex-shrink: 0;
    font-family: var(--font-mono);
    text-transform: uppercase;
}

.profile-info {
    flex: 1;
    min-width: 240px;
}

.profile-name {
    font-size: 1.4rem;
    font-weight: 900;
    color: var(--text-primary);
    margin-bottom: 8px;
    line-height: 1.2;
    letter-spacing: -0.02em;
}

.profile-id {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: var(--radius-sm);
    background: var(--primary-bg);
    color: var(--primary);
    font-family: var(--font-mono);
    font-size: 0.78rem;
    font-weight: 800;
    border: 1.5px solid rgba(11, 94, 215, 0.25);
    margin-bottom: 10px;
}

.profile-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.profile-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: var(--radius-sm);
    background: var(--bg-body);
    color: var(--text-secondary);
    font-size: 0.72rem;
    font-weight: 700;
    border: 1px solid var(--border-color);
}

.profile-meta-item i {
    color: var(--primary);
    font-size: 0.75rem;
}

.gender-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: var(--radius-sm);
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.gender-badge.male {
    background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
    color: #1E40AF;
    border: 1.5px solid #93C5FD;
}

.gender-badge.female {
    background: linear-gradient(135deg, #FCE7F3, #FBCFE8);
    color: #9D174D;
    border: 1.5px solid #F9A8D4;
}

.gender-badge.other {
    background: var(--border-color);
    color: var(--text-secondary);
}

/* ================================================================
   STATS GRID
   ================================================================ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}

.stat-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    padding: 18px 20px;
    border: 1.5px solid var(--border-color);
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
    min-height: 120px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-card.visits::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6); }
.stat-card.spent::before { background: linear-gradient(90deg, #059669, #34D399); }
.stat-card.paid::before { background: linear-gradient(90deg, #0891B2, #06B6D4); }
.stat-card.balance::before { background: linear-gradient(90deg, #DC2626, #F87171); }

.stat-card .stat-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    color: white;
    margin-bottom: 10px;
    transition: transform 0.3s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.stat-card:hover .stat-icon {
    transform: scale(1.15) rotate(-6deg);
}

.stat-card.visits .stat-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.stat-card.spent .stat-icon { background: linear-gradient(135deg, #059669, #34D399); }
.stat-card.paid .stat-icon { background: linear-gradient(135deg, #0891B2, #06B6D4); }
.stat-card.balance .stat-icon { background: linear-gradient(135deg, #DC2626, #F87171); }

.stat-card .stat-label {
    font-size: 0.65rem;
    color: var(--text-secondary);
    font-weight: 800;
    text-transform: uppercase;
    margin-bottom: 4px;
    letter-spacing: 0.06em;
}

.stat-card .stat-value {
    font-size: 1.5rem;
    font-weight: 900;
    font-family: var(--font-mono);
    color: var(--text-primary);
    letter-spacing: -0.03em;
    line-height: 1.1;
}

.stat-card.visits .stat-value { color: var(--primary); }
.stat-card.spent .stat-value { color: var(--success); }
.stat-card.paid .stat-value { color: var(--cyan); }
.stat-card.balance .stat-value { color: var(--danger); }

/* ================================================================
   VISIT SECTION - FULL CARD
   ================================================================ */
.visits-container {
    display: flex;
    flex-direction: column;
    gap: 32px;
    margin-bottom: 20px;
}

.visit-full-card {
    background: var(--bg-card);
    border-radius: var(--radius-xl);
    border: 2px solid var(--border-color);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    transition: all 0.35s ease;
}

.visit-full-card:hover {
    box-shadow: 0 20px 50px rgba(11, 94, 215, 0.15);
    border-color: rgba(11, 94, 215, 0.3);
}

.visit-full-header {
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #7C3AED 100%);
    padding: 22px 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    position: relative;
    overflow: hidden;
}

.visit-full-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.visit-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}

.visit-number-big {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    color: white;
    padding: 10px 18px;
    border-radius: var(--radius-md);
    font-family: var(--font-mono);
    font-size: 1rem;
    font-weight: 900;
    border: 1.5px solid rgba(255,255,255,0.3);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.visit-type-badge {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    color: white;
    padding: 8px 14px;
    border-radius: var(--radius-sm);
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border: 1.5px solid rgba(255,255,255,0.25);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.visit-header-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}

.visit-date-info {
    background: rgba(255,255,255,0.18);
    backdrop-filter: blur(10px);
    color: white;
    padding: 10px 16px;
    border-radius: var(--radius-md);
    border: 1.5px solid rgba(255,255,255,0.25);
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 150px;
}

.visit-date-info .date-label {
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgba(255,255,255,0.75);
    display: flex;
    align-items: center;
    gap: 4px;
}

.visit-date-info .date-value {
    font-size: 0.85rem;
    font-weight: 900;
    font-family: var(--font-mono);
    color: white;
}

.visit-status-big {
    padding: 10px 18px;
    border-radius: var(--radius-md);
    font-size: 0.78rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.visit-status-big.success { background: linear-gradient(135deg, #34D399, #059669); color: white; }
.visit-status-big.warning { background: linear-gradient(135deg, #FBBF24, #D97706); color: white; }
.visit-status-big.danger { background: linear-gradient(135deg, #F87171, #DC2626); color: white; }
.visit-status-big.info { background: linear-gradient(135deg, #22D3EE, #0891B2); color: white; }
.visit-status-big.secondary { background: rgba(255,255,255,0.25); color: white; }

.visit-full-body {
    padding: 0;
}

/* ================================================================
   SECTION - WITH NUMBERED HEADER
   ================================================================ */
.visit-section-block {
    border-top: 2px solid var(--border-color);
    padding: 0;
}

.visit-section-block:first-child {
    border-top: none;
}

.section-header-bar {
    background: linear-gradient(135deg, rgba(11, 94, 215, 0.08), rgba(124, 58, 237, 0.05));
    padding: 14px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    border-bottom: 2px solid var(--border-color);
}

.section-header-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.9rem;
    font-weight: 900;
    color: var(--text-primary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.section-number-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0B5ED7, #7C3AED);
    color: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-mono);
    font-weight: 900;
    font-size: 0.85rem;
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.35);
    flex-shrink: 0;
}

.section-header-title i {
    color: var(--primary);
    font-size: 1rem;
}

.section-count-badge {
    background: var(--primary-bg);
    color: var(--primary);
    padding: 5px 12px;
    border-radius: var(--radius-full);
    font-size: 0.65rem;
    font-weight: 800;
    font-family: var(--font-mono);
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1.5px solid rgba(11, 94, 215, 0.25);
}

.section-content {
    padding: 20px 24px;
    background: var(--bg-card);
}

/* ================================================================
   INFO GRID
   ================================================================ */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
}

.info-item {
    background: linear-gradient(135deg, var(--bg-body), var(--bg-card));
    border-radius: var(--radius-md);
    padding: 12px 14px;
    border: 1.5px solid var(--border-color);
    transition: all 0.25s ease;
}

.info-item:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow-sm);
}

.info-label {
    font-size: 0.6rem;
    color: var(--text-secondary);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.info-label i {
    color: var(--primary);
    font-size: 0.68rem;
}

.info-value {
    font-size: 0.82rem;
    color: var(--text-primary);
    font-weight: 800;
    word-break: break-word;
}

.info-value.mono {
    font-family: var(--font-mono);
    font-size: 0.78rem;
    letter-spacing: -0.02em;
}

.info-item.highlight-red {
    background: linear-gradient(135deg, var(--danger-bg), rgba(220, 38, 38, 0.05));
    border-color: rgba(220, 38, 38, 0.3);
}
.info-item.highlight-red .info-label i { color: var(--danger); }
.info-item.highlight-red .info-value { color: var(--danger); font-weight: 900; }

.info-item.highlight-purple {
    background: linear-gradient(135deg, var(--purple-bg), rgba(124, 58, 237, 0.05));
    border-color: rgba(124, 58, 237, 0.3);
}
.info-item.highlight-purple .info-label i { color: var(--purple); }
.info-item.highlight-purple .info-value { color: var(--purple); font-weight: 900; }

/* ================================================================
   VITAL SIGNS GRID
   ================================================================ */
.vitals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 14px;
}

.vital-item {
    position: relative;
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-body) 100%);
    border: 2px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 16px 16px 14px;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    min-height: 120px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.vital-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    transition: height 0.3s ease;
}

.vital-item::after {
    content: '';
    position: absolute;
    top: -40px;
    right: -40px;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    opacity: 0.08;
    transition: all 0.4s ease;
    pointer-events: none;
}

.vital-item:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.15);
}

.vital-item:hover::before { height: 6px; }
.vital-item:hover::after { transform: scale(1.5); opacity: 0.15; }

.vital-item.temp { border-color: rgba(220, 38, 38, 0.25); }
.vital-item.temp::before { background: linear-gradient(90deg, #DC2626, #F87171); }
.vital-item.temp::after { background: #DC2626; }
.vital-item.temp .vital-label i { color: #DC2626; }
.vital-item.temp .vital-value { color: #DC2626; }

.vital-item.bp { border-color: rgba(124, 58, 237, 0.25); }
.vital-item.bp::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }
.vital-item.bp::after { background: #7C3AED; }
.vital-item.bp .vital-label i { color: #7C3AED; }
.vital-item.bp .vital-value { color: #7C3AED; }

.vital-item.pulse { border-color: rgba(219, 39, 119, 0.25); }
.vital-item.pulse::before { background: linear-gradient(90deg, #DB2777, #F472B6); }
.vital-item.pulse::after { background: #DB2777; }
.vital-item.pulse .vital-label i { color: #DB2777; }
.vital-item.pulse .vital-value { color: #DB2777; }

.vital-item.resp { border-color: rgba(8, 145, 178, 0.25); }
.vital-item.resp::before { background: linear-gradient(90deg, #0891B2, #06B6D4); }
.vital-item.resp::after { background: #0891B2; }
.vital-item.resp .vital-label i { color: #0891B2; }
.vital-item.resp .vital-value { color: #0891B2; }

.vital-item.oxygen { border-color: rgba(5, 150, 105, 0.25); }
.vital-item.oxygen::before { background: linear-gradient(90deg, #059669, #34D399); }
.vital-item.oxygen::after { background: #059669; }
.vital-item.oxygen .vital-label i { color: #059669; }
.vital-item.oxygen .vital-value { color: #059669; }

.vital-item.glucose { border-color: rgba(217, 119, 6, 0.25); }
.vital-item.glucose::before { background: linear-gradient(90deg, #D97706, #FBBF24); }
.vital-item.glucose::after { background: #D97706; }
.vital-item.glucose .vital-label i { color: #D97706; }
.vital-item.glucose .vital-value { color: #D97706; }

.vital-item.weight { border-color: rgba(11, 94, 215, 0.25); }
.vital-item.weight::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6); }
.vital-item.weight::after { background: #0B5ED7; }
.vital-item.weight .vital-label i { color: #0B5ED7; }
.vital-item.weight .vital-value { color: #0B5ED7; }

.vital-item.height { border-color: rgba(79, 70, 229, 0.25); }
.vital-item.height::before { background: linear-gradient(90deg, #4F46E5, #818CF8); }
.vital-item.height::after { background: #4F46E5; }
.vital-item.height .vital-label i { color: #4F46E5; }
.vital-item.height .vital-value { color: #4F46E5; }

.vital-item.bmi { border-color: rgba(13, 148, 136, 0.25); }
.vital-item.bmi::before { background: linear-gradient(90deg, #0D9488, #5EEAD4); }
.vital-item.bmi::after { background: #0D9488; }
.vital-item.bmi .vital-label i { color: #0D9488; }
.vital-item.bmi .vital-value { color: #0D9488; }

.vital-item.muac { border-color: rgba(245, 158, 11, 0.25); }
.vital-item.muac::before { background: linear-gradient(90deg, #F59E0B, #FCD34D); }
.vital-item.muac::after { background: #F59E0B; }
.vital-item.muac .vital-label i { color: #F59E0B; }
.vital-item.muac .vital-value { color: #F59E0B; }

.vital-item.pain { border-color: rgba(239, 68, 68, 0.25); }
.vital-item.pain::before { background: linear-gradient(90deg, #EF4444, #FCA5A5); }
.vital-item.pain::after { background: #EF4444; }
.vital-item.pain .vital-label i { color: #EF4444; }
.vital-item.pain .vital-value { color: #EF4444; }

.vital-label {
    font-size: 0.65rem;
    color: var(--text-secondary);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
    position: relative;
    z-index: 1;
}

.vital-label i { font-size: 0.78rem; }

.vital-value {
    font-family: var(--font-mono);
    font-size: 1.6rem;
    font-weight: 900;
    color: var(--text-primary);
    letter-spacing: -0.04em;
    line-height: 1;
    position: relative;
    z-index: 1;
}

.vital-value .unit {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--text-secondary);
    margin-left: 4px;
    font-family: var(--font-primary);
}

.vital-recorded-by {
    font-size: 0.62rem;
    color: var(--text-secondary);
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 5px;
    padding-top: 8px;
    border-top: 1px dashed var(--border-color);
    font-weight: 700;
    position: relative;
    z-index: 1;
}

.vital-recorded-by i {
    color: var(--purple);
    font-size: 0.62rem;
}

/* ================================================================
   DATA TABLE
   ================================================================ */
.modern-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
    background: var(--bg-card);
    border-radius: var(--radius-md);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}

.modern-table thead th {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    padding: 12px 14px;
    text-align: left;
    font-weight: 900;
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    white-space: nowrap;
    border-bottom: none;
}

.modern-table tbody td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
    font-weight: 500;
}

.modern-table tbody tr:nth-child(even) td {
    background: rgba(11, 94, 215, 0.02);
}

.modern-table tbody tr:hover td {
    background: var(--primary-bg);
}

.modern-table tbody tr:last-child td {
    border-bottom: none;
}

.modern-table tbody tr.total-row td {
    background: linear-gradient(135deg, rgba(11, 94, 215, 0.08), rgba(124, 58, 237, 0.05));
    font-weight: 900;
    font-size: 0.85rem;
    border-top: 3px solid var(--primary);
    color: var(--primary);
}

/* ================================================================
   MONEY / STATUS
   ================================================================ */
.money-cell {
    font-family: var(--font-mono);
    font-weight: 800;
    font-size: 0.82rem;
    color: var(--success);
    text-align: right;
    white-space: nowrap;
    letter-spacing: -0.02em;
}

.money-cell.danger { color: var(--danger); }
.money-cell.info { color: var(--cyan); }
.money-cell.purple { color: var(--purple); }
.money-cell.warning { color: var(--warning); }
.money-cell.blue { color: var(--primary); }
.money-cell.slate { color: var(--slate); }
.money-cell.teal { color: var(--teal); }

.money-cell .currency-prefix {
    font-size: 0.62rem;
    color: var(--text-secondary);
    margin-right: 3px;
    font-weight: 600;
    font-family: var(--font-primary);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 11px;
    border-radius: var(--radius-full);
    font-size: 0.62rem;
    font-weight: 900;
    text-transform: uppercase;
    white-space: nowrap;
    letter-spacing: 0.04em;
}

.status-badge.success { background: var(--success-bg); color: var(--success); border: 1.5px solid var(--success); }
.status-badge.warning { background: var(--warning-bg); color: var(--warning); border: 1.5px solid var(--warning); }
.status-badge.danger { background: var(--danger-bg); color: var(--danger); border: 1.5px solid var(--danger); }
.status-badge.info { background: var(--cyan-bg); color: var(--cyan); border: 1.5px solid var(--cyan); }
.status-badge.purple { background: var(--purple-bg); color: var(--purple); border: 1.5px solid var(--purple); }
.status-badge.secondary { background: var(--border-color); color: var(--text-secondary); border: 1.5px solid var(--border-strong); }

/* ================================================================
   DIAGNOSIS / TREATMENT
   ================================================================ */
.diagnosis-box {
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.08), rgba(167, 139, 250, 0.03));
    border-radius: var(--radius-lg);
    padding: 16px 18px;
    border: 2px solid var(--purple);
    border-left: 6px solid var(--purple);
    margin-bottom: 14px;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.1);
}

.diagnosis-label {
    font-size: 0.68rem;
    color: var(--purple);
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.diagnosis-value {
    font-size: 0.92rem;
    color: var(--text-primary);
    font-weight: 800;
    line-height: 1.5;
    margin-bottom: 8px;
}

.disease-code {
    display: inline-block;
    font-family: var(--font-mono);
    background: linear-gradient(135deg, var(--purple), var(--purple-light));
    color: white;
    padding: 4px 10px;
    border-radius: var(--radius-sm);
    font-size: 0.68rem;
    font-weight: 900;
    box-shadow: 0 2px 6px rgba(124, 58, 237, 0.3);
}

.treatment-box {
    background: linear-gradient(135deg, rgba(5, 150, 105, 0.08), rgba(52, 211, 153, 0.03));
    border-radius: var(--radius-lg);
    padding: 16px 18px;
    border: 2px solid var(--success);
    border-left: 6px solid var(--success);
    margin-bottom: 14px;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.1);
}

.treatment-label {
    font-size: 0.68rem;
    color: var(--success);
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.treatment-value {
    font-size: 0.82rem;
    color: var(--text-primary);
    font-weight: 700;
    line-height: 1.6;
}

.symptoms-box {
    background: linear-gradient(135deg, rgba(217, 119, 6, 0.08), rgba(251, 191, 36, 0.03));
    border-radius: var(--radius-lg);
    padding: 14px 16px;
    border: 2px solid var(--warning);
    border-left: 6px solid var(--warning);
    margin-bottom: 14px;
    box-shadow: 0 4px 12px rgba(217, 119, 6, 0.1);
}

.symptoms-label {
    font-size: 0.68rem;
    color: var(--warning);
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.symptoms-value {
    font-size: 0.82rem;
    color: var(--text-primary);
    font-weight: 700;
    line-height: 1.6;
}

/* ================================================================
   BILL CARD
   ================================================================ */
.bill-card {
    background: linear-gradient(135deg, var(--bg-body), var(--bg-card));
    border-radius: var(--radius-lg);
    padding: 18px;
    margin-bottom: 16px;
    border: 2px solid var(--border-color);
    transition: all 0.3s ease;
    box-shadow: var(--shadow-sm);
}

.bill-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-md);
}

.bill-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding-bottom: 14px;
    margin-bottom: 14px;
    border-bottom: 2px dashed var(--border-color);
}

.bill-number-big {
    font-family: var(--font-mono);
    font-weight: 900;
    color: var(--primary);
    font-size: 0.85rem;
    background: var(--primary-bg);
    padding: 6px 14px;
    border-radius: var(--radius-sm);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1.5px solid rgba(11, 94, 215, 0.25);
}

.bill-meta {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    font-size: 0.7rem;
    color: var(--text-secondary);
    font-weight: 700;
}

.bill-meta span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: var(--bg-card);
    padding: 4px 10px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border-color);
}

.bill-meta span i {
    color: var(--primary);
    font-size: 0.68rem;
}

.bill-summary {
    display: flex;
    justify-content: flex-end;
    gap: 20px;
    flex-wrap: wrap;
    padding-top: 16px;
    margin-top: 16px;
    border-top: 2px dashed var(--border-color);
}

.bill-summary-item {
    text-align: right;
    min-width: 100px;
}

.bill-summary-label {
    font-size: 0.6rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    font-weight: 900;
    letter-spacing: 0.05em;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
}

.bill-summary-value {
    font-family: var(--font-mono);
    font-size: 0.88rem;
    font-weight: 900;
    color: var(--text-primary);
}

.bill-summary-value.total { color: var(--primary); font-size: 1rem; }
.bill-summary-value.paid { color: var(--success); font-size: 1rem; }
.bill-summary-value.balance { color: var(--danger); font-size: 1.05rem; }
.bill-summary-value.discount { color: var(--warning); }
.bill-summary-value.premium { color: var(--purple); }

.bill-total-highlight {
    background: linear-gradient(135deg, rgba(11, 94, 215, 0.1), rgba(124, 58, 237, 0.05));
    border-radius: var(--radius-md);
    padding: 14px 18px;
    margin-top: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    border: 2px solid var(--primary);
}

.bill-total-label {
    font-size: 0.8rem;
    font-weight: 900;
    color: var(--primary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    display: flex;
    align-items: center;
    gap: 8px;
}

.bill-total-value {
    font-family: var(--font-mono);
    font-size: 1.4rem;
    font-weight: 900;
    color: var(--primary);
    letter-spacing: -0.03em;
}

/* ================================================================
   EMPTY STATE
   ================================================================ */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 2px dashed var(--border-color);
}

.empty-state i {
    font-size: 3rem;
    opacity: 0.3;
    display: block;
    margin-bottom: 14px;
    color: var(--primary);
}

.empty-state p {
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.empty-mini {
    text-align: center;
    padding: 18px;
    color: var(--text-secondary);
    font-size: 0.75rem;
    background: var(--bg-body);
    border-radius: var(--radius-md);
    border: 2px dashed var(--border-color);
    font-weight: 600;
}

.empty-mini i {
    display: block;
    font-size: 1.5rem;
    opacity: 0.3;
    margin-bottom: 6px;
    color: var(--primary);
}

/* ================================================================
   RESPONSIVE
   ================================================================ */
@media (max-width: 1024px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .vitals-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
    .info-grid { grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); }
}

@media (max-width: 768px) {
    .page-header { padding: 16px 20px; }
    .page-header .page-title { font-size: 1.2rem; }
    
    .profile-card { padding: 16px 18px; gap: 14px; }
    .profile-avatar { width: 70px; height: 70px; font-size: 1.6rem; }
    .profile-name { font-size: 1.15rem; }
    
    .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .stat-card { padding: 14px; min-height: 100px; }
    .stat-card .stat-value { font-size: 1.2rem; }
    
    .visit-full-header { padding: 16px 20px; }
    .visit-number-big { font-size: 0.85rem; padding: 8px 14px; }
    .section-content { padding: 16px 18px; }
    
    .vitals-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
    .vital-item { padding: 12px; min-height: 105px; }
    .vital-value { font-size: 1.35rem; }
    
    .info-grid { grid-template-columns: 1fr; gap: 10px; }
    
    .modern-table { font-size: 0.72rem; }
    .modern-table thead th,
    .modern-table tbody td { padding: 9px 11px; }
    
    .bill-summary { gap: 12px; }
    .bill-summary-item { min-width: 80px; }
}

@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .vitals-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
    .vital-item { min-height: 100px; padding: 10px; }
    .vital-value { font-size: 1.2rem; }
}

@media print {
    .btn-outline-light, .btn-export-pdf { display: none !important; }
    .page-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; }
    .visit-full-card { break-inside: avoid; box-shadow: none; border: 1px solid #ccc; }
    .vital-item { break-inside: avoid; }
    .bill-card { break-inside: avoid; }
}
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-injured"></i>
                Patient Details
                <?php if ($is_admin): ?>
                    <span class="role-tag"><i class="fas fa-crown"></i> ADMIN</span>
                <?php else: ?>
                    <span class="role-tag audit-role"><i class="fas fa-shield-alt"></i> AUDIT</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-id-card"></i>
                <strong><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> 
                    <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-calendar"></i> 
                    Registered: <?= date('d M Y', strtotime($patient['created_at'] ?? 'now')) ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-hospital-user"></i>
                    <?= count($visits) ?> Visits
                </span>
            </p>
            
            <?php if (!empty($patient['allergies'])): ?>
            <div class="allergy-alert">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>ALLERGIES:</strong> <?= htmlspecialchars($patient['allergies']) ?>
            </div>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="<?= $base_path ?>/patients.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($is_admin): ?>
            <a href="<?= $base_path ?>/patient_edit.php?id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" 
               class="btn-outline-light">
                <i class="fas fa-edit"></i> Edit
            </a>
            <?php endif; ?>
            <a href="<?= $base_path ?>/patient_pdf.php?id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" 
               target="_blank"
               class="btn-export-pdf">
                <i class="fas fa-file-pdf"></i> Export PDF
            </a>
        </div>
    </div>

    <!-- PROFILE CARD -->
    <div class="profile-card">
        <div class="profile-avatar">
            <?= strtoupper(substr($patient['full_name'] ?? 'P', 0, 1)) ?>
        </div>
        
        <div class="profile-info">
            <h2 class="profile-name"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></h2>
            
            <div class="profile-id">
                <i class="fas fa-id-card"></i>
                <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
            </div>
            
            <div class="profile-meta">
                <?php 
                $gender = strtolower($patient['gender'] ?? 'other');
                $gender_class = $gender === 'female' ? 'female' : ($gender === 'male' ? 'male' : 'other');
                $gender_icon = $gender === 'female' ? 'venus' : ($gender === 'male' ? 'mars' : 'genderless');
                ?>
                
                <span class="gender-badge <?= $gender_class ?>">
                    <i class="fas fa-<?= $gender_icon ?>"></i>
                    <?= htmlspecialchars(ucfirst($patient['gender'] ?? 'N/A')) ?>
                </span>
                
                <span class="profile-meta-item">
                    <i class="fas fa-birthday-cake"></i>
                    <?= calculateAge($patient['date_of_birth'] ?? null) ?>
                </span>
                
                <?php if (!empty($patient['phone'])): ?>
                <span class="profile-meta-item mono">
                    <i class="fas fa-phone"></i>
                    <?= htmlspecialchars($patient['phone']) ?>
                </span>
                <?php endif; ?>
                
                <?php if (!empty($patient['email'])): ?>
                <span class="profile-meta-item">
                    <i class="fas fa-envelope"></i>
                    <?= htmlspecialchars($patient['email']) ?>
                </span>
                <?php endif; ?>
                
                <?php if (!empty($patient['blood_group'])): ?>
                <span class="profile-meta-item mono" style="background:var(--danger-bg);color:var(--danger);border-color:var(--danger);">
                    <i class="fas fa-tint" style="color:var(--danger);"></i>
                    <?= htmlspecialchars($patient['blood_group']) ?>
                </span>
                <?php endif; ?>
                
                <?php if (!empty($patient['address'])): ?>
                <span class="profile-meta-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <?= htmlspecialchars($patient['address']) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- STATS GRID -->
    <div class="stats-grid">
        <div class="stat-card visits">
            <div class="stat-icon"><i class="fas fa-hospital-user"></i></div>
            <div class="stat-label">Total Visits</div>
            <div class="stat-value"><?= number_format($stats['visits']) ?></div>
        </div>
        
        <div class="stat-card spent">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-label">Total Spent</div>
            <div class="stat-value"><?= number_format($stats['total_spent'], 0) ?></div>
        </div>
        
        <div class="stat-card paid">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-label">Total Paid</div>
            <div class="stat-value"><?= number_format($stats['total_paid'], 0) ?></div>
        </div>
        
        <div class="stat-card balance">
            <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
            <div class="stat-label">Balance Due</div>
            <div class="stat-value"><?= number_format($stats['total_balance'], 0) ?></div>
        </div>
    </div>

    <!-- ================================================================
         VISITS - FULL CARDS
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
                
                $visit_status_class = getStatusClass($visit['status']);
            ?>
                <div class="visit-full-card">
                    
                    <!-- VISIT HEADER -->
                    <div class="visit-full-header">
                        <div class="visit-header-left">
                            <span class="visit-number-big">
                                <i class="fas fa-hashtag"></i>
                                <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                            </span>
                            <span class="visit-type-badge">
                                <i class="fas fa-tag"></i>
                                <?= htmlspecialchars($visit['visit_type'] ?? 'N/A') ?>
                            </span>
                            <span class="visit-status-big <?= $visit_status_class ?>">
                                <i class="fas fa-<?= $visit_status_class === 'success' ? 'check-circle' : ($visit_status_class === 'danger' ? 'times-circle' : 'clock') ?>"></i>
                                <?= strtoupper(htmlspecialchars(str_replace('_', ' ', $visit['status'] ?? 'PENDING'))) ?>
                            </span>
                        </div>
                        
                        <div class="visit-header-right">
                            <div class="visit-date-info">
                                <span class="date-label"><i class="fas fa-calendar-alt"></i> Visit Date</span>
                                <span class="date-value"><?= date('d M Y', strtotime($visit['visit_date'] ?? 'now')) ?></span>
                            </div>
                            <div class="visit-date-info">
                                <span class="date-label"><i class="fas fa-clock"></i> Time</span>
                                <span class="date-value"><?= date('H:i', strtotime($visit['visit_date'] ?? 'now')) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- VISIT BODY -->
                    <div class="visit-full-body">
                        
                        <!-- SECTION 1: PATIENT INFORMATION -->
                        <div class="visit-section-block">
                            <div class="section-header-bar">
                                <div class="section-header-title">
                                    <span class="section-number-circle">1</span>
                                    <i class="fas fa-user-injured"></i>
                                    Patient Information
                                </div>
                            </div>
                            
                            <div class="section-content">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <div class="info-label"><i class="fas fa-user"></i> Full Name</div>
                                        <div class="info-value"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label"><i class="fas fa-id-card"></i> Patient ID</div>
                                        <div class="info-value mono"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label"><i class="fas fa-venus-mars"></i> Gender</div>
                                        <div class="info-value"><?= htmlspecialchars(ucfirst($patient['gender'] ?? 'N/A')) ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label"><i class="fas fa-birthday-cake"></i> Age</div>
                                        <div class="info-value"><?= calculateAge($patient['date_of_birth'] ?? null) ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label"><i class="fas fa-phone"></i> Phone</div>
                                        <div class="info-value mono"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></div>
                                    </div>
                                    <div class="info-item highlight-red">
                                        <div class="info-label"><i class="fas fa-tint"></i> Blood Group</div>
                                        <div class="info-value mono"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- SECTION 2: VISIT INFORMATION -->
                        <div class="visit-section-block">
                            <div class="section-header-bar">
                                <div class="section-header-title">
                                    <span class="section-number-circle">2</span>
                                    <i class="fas fa-clipboard-list"></i>
                                    Visit Information
                                </div>
                            </div>
                            
                            <div class="section-content">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <div class="info-label"><i class="fas fa-hashtag"></i> Visit Number</div>
                                        <div class="info-value mono"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label"><i class="fas fa-calendar-alt"></i> Date & Time</div>
                                        <div class="info-value mono"><?= date('d M Y, H:i', strtotime($visit['visit_date'] ?? 'now')) ?></div>
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
                                        <div class="info-label"><i class="fas fa-stethoscope"></i> Visit Type</div>
                                        <div class="info-value"><?= htmlspecialchars($visit['visit_type'] ?? 'N/A') ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label"><i class="fas fa-info-circle"></i> Status</div>
                                        <div class="info-value">
                                            <span class="status-badge <?= $visit_status_class ?>">
                                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $visit['status'] ?? 'N/A'))) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if (!empty($visit['consultation_fee']) && $visit['consultation_fee'] > 0): ?>
                                    <div class="info-item">
                                        <div class="info-label"><i class="fas fa-money-bill"></i> Consultation Fee</div>
                                        <div class="info-value mono"><?= $currency ?> <?= number_format($visit['consultation_fee'], 0) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($visit['visit_total']) && $visit['visit_total'] > 0): ?>
                                    <div class="info-item highlight-purple">
                                        <div class="info-label"><i class="fas fa-calculator"></i> Visit Total</div>
                                        <div class="info-value mono"><?= $currency ?> <?= number_format($visit['visit_total'], 0) ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- SECTION 3: VITAL SIGNS -->
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
                            <div class="visit-section-block">
                                <div class="section-header-bar">
                                    <div class="section-header-title">
                                        <span class="section-number-circle">3</span>
                                        <i class="fas fa-heartbeat"></i>
                                        Vital Signs
                                    </div>
                                    <span class="section-count-badge">
                                        <i class="fas fa-user-nurse"></i>
                                        <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?>
                                    </span>
                                </div>
                                
                                <div class="section-content">
                                    <div class="vitals-grid">
                                        <?php if (hasVitalValue($visit_vital['temperature'] ?? null)): ?>
                                        <div class="vital-item temp">
                                            <div class="vital-label"><i class="fas fa-thermometer-half"></i> Temperature</div>
                                            <div class="vital-value"><?= htmlspecialchars($visit_vital['temperature']) ?><span class="unit">°C</span></div>
                                            <div class="vital-recorded-by"><i class="fas fa-user-nurse"></i> <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (hasVitalValue($visit_vital['blood_pressure_systolic'] ?? null) && hasVitalValue($visit_vital['blood_pressure_diastolic'] ?? null)): ?>
                                        <div class="vital-item bp">
                                            <div class="vital-label"><i class="fas fa-heart"></i> Blood Pressure</div>
                                            <div class="vital-value"><?= $visit_vital['blood_pressure_systolic'] ?>/<?= $visit_vital['blood_pressure_diastolic'] ?><span class="unit">mmHg</span></div>
                                            <div class="vital-recorded-by"><i class="fas fa-user-nurse"></i> <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (hasVitalValue($visit_vital['pulse_rate'] ?? null)): ?>
                                        <div class="vital-item pulse">
                                            <div class="vital-label"><i class="fas fa-heartbeat"></i> Pulse Rate</div>
                                            <div class="vital-value"><?= htmlspecialchars($visit_vital['pulse_rate']) ?><span class="unit">bpm</span></div>
                                            <div class="vital-recorded-by"><i class="fas fa-user-nurse"></i> <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (hasVitalValue($visit_vital['respiratory_rate'] ?? null)): ?>
                                        <div class="vital-item resp">
                                            <div class="vital-label"><i class="fas fa-lungs"></i> Respiratory</div>
                                            <div class="vital-value"><?= htmlspecialchars($visit_vital['respiratory_rate']) ?><span class="unit">/min</span></div>
                                            <div class="vital-recorded-by"><i class="fas fa-user-nurse"></i> <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (hasVitalValue($visit_vital['oxygen_saturation'] ?? null)): ?>
                                        <div class="vital-item oxygen">
                                            <div class="vital-label"><i class="fas fa-wind"></i> Oxygen Sat. (SpO₂)</div>
                                            <div class="vital-value"><?= htmlspecialchars($visit_vital['oxygen_saturation']) ?><span class="unit">%</span></div>
                                            <div class="vital-recorded-by"><i class="fas fa-user-nurse"></i> <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (hasVitalValue($visit_vital['blood_glucose'] ?? null)): ?>
                                        <div class="vital-item glucose">
                                            <div class="vital-label"><i class="fas fa-cube"></i> Blood Glucose</div>
                                            <div class="vital-value"><?= htmlspecialchars($visit_vital['blood_glucose']) ?><span class="unit">mg/dL</span></div>
                                            <div class="vital-recorded-by"><i class="fas fa-user-nurse"></i> <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (hasVitalValue($visit_vital['weight'] ?? null)): ?>
                                        <div class="vital-item weight">
                                            <div class="vital-label"><i class="fas fa-weight"></i> Weight</div>
                                            <div class="vital-value"><?= htmlspecialchars($visit_vital['weight']) ?><span class="unit">kg</span></div>
                                            <div class="vital-recorded-by"><i class="fas fa-user-nurse"></i> <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (hasVitalValue($visit_vital['height'] ?? null)): ?>
                                        <div class="vital-item height">
                                            <div class="vital-label"><i class="fas fa-ruler-vertical"></i> Height</div>
                                            <div class="vital-value"><?= htmlspecialchars($visit_vital['height']) ?><span class="unit">cm</span></div>
                                            <div class="vital-recorded-by"><i class="fas fa-user-nurse"></i> <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (hasVitalValue($visit_vital['bmi'] ?? null)): ?>
                                        <div class="vital-item bmi">
                                            <div class="vital-label"><i class="fas fa-chart-line"></i> BMI</div>
                                            <div class="vital-value"><?= htmlspecialchars($visit_vital['bmi']) ?></div>
                                            <div class="vital-recorded-by"><i class="fas fa-user-nurse"></i> <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (hasVitalValue($visit_vital['muac'] ?? null)): ?>
                                        <div class="vital-item muac">
                                            <div class="vital-label"><i class="fas fa-child"></i> MUAC</div>
                                            <div class="vital-value"><?= htmlspecialchars($visit_vital['muac']) ?><span class="unit">cm</span></div>
                                            <div class="vital-recorded-by"><i class="fas fa-user-nurse"></i> <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (hasVitalValue($visit_vital['pain_score'] ?? null)): ?>
                                        <div class="vital-item pain">
                                            <div class="vital-label"><i class="fas fa-face-frown"></i> Pain Score</div>
                                            <div class="vital-value"><?= htmlspecialchars($visit_vital['pain_score']) ?><span class="unit">/10</span></div>
                                            <div class="vital-recorded-by"><i class="fas fa-user-nurse"></i> <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($visit_vital['notes'])): ?>
                                    <div style="margin-top:14px;padding:12px 16px;background:var(--cyan-bg);border-radius:var(--radius-md);font-size:0.75rem;color:var(--cyan);border-left:4px solid var(--cyan);font-weight:700;">
                                        <i class="fas fa-sticky-note"></i> <strong>Vital Notes:</strong> <?= htmlspecialchars($visit_vital['notes']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- SECTION 4: LAB TESTS -->
                        <?php if (count($visit_labs) > 0): ?>
                        <div class="visit-section-block">
                            <div class="section-header-bar">
                                <div class="section-header-title">
                                    <span class="section-number-circle">4</span>
                                    <i class="fas fa-flask"></i>
                                    Lab Tests & Results
                                </div>
                                <span class="section-count-badge">
                                    <i class="fas fa-vial"></i>
                                    <?= count($visit_labs) ?> Tests
                                </span>
                            </div>
                            
                            <div class="section-content" style="padding:0;">
                                <div style="overflow-x:auto;">
                                    <table class="modern-table">
                                        <thead>
                                            <tr>
                                                <th style="width:40px;">#</th>
                                                <th>Test Name</th>
                                                <th>Result</th>
                                                <th>Reference Range</th>
                                                <th>Interpretation</th>
                                                <th>Lab Technician</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $lab_num = 1; foreach ($visit_labs as $lt): ?>
                                                <tr>
                                                    <td style="text-align:center;font-weight:800;color:var(--text-secondary);font-family:var(--font-mono);"><?= $lab_num++ ?></td>
                                                    <td style="font-weight:800;"><?= htmlspecialchars($lt['test_name'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <?php if (!empty($lt['results'])): ?>
                                                            <span style="font-family:var(--font-mono);font-weight:900;color:var(--success);font-size:0.75rem;">
                                                                <?= htmlspecialchars(substr($lt['results'], 0, 60)) ?><?= strlen($lt['results']) > 60 ? '...' : '' ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span style="color:var(--text-muted);">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="font-size:0.72rem;color:var(--text-secondary);font-family:var(--font-mono);">
                                                        <?= htmlspecialchars($lt['reference_range'] ?? '—') ?>
                                                    </td>
                                                    <td style="font-size:0.72rem;color:var(--text-secondary);">
                                                        <?= htmlspecialchars(substr($lt['interpretation'] ?? '—', 0, 40)) ?>
                                                    </td>
                                                    <td>
                                                        <span style="display:inline-flex;align-items:center;gap:5px;font-size:0.72rem;font-weight:700;">
                                                            <i class="fas fa-user-flask" style="color:var(--cyan);"></i>
                                                            <?= htmlspecialchars($lt['technician_name'] ?? 'N/A') ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge <?= getStatusClass($lt['status']) ?>">
                                                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $lt['status'] ?? 'N/A'))) ?>
                                                        </span>
                                                    </td>
                                                    <td style="font-size:0.68rem;font-family:var(--font-mono);color:var(--text-secondary);">
                                                        <?= date('d M Y', strtotime($lt['created_at'] ?? 'now')) ?>
                                                        <br>
                                                        <span style="font-size:0.62rem;"><?= date('H:i', strtotime($lt['created_at'] ?? 'now')) ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- SECTION 5: DIAGNOSIS & TREATMENT -->
                        <?php if (!empty($visit['diagnosis']) || !empty($visit['disease_name']) || !empty($visit['symptoms']) || !empty($visit['treatment'])): ?>
                        <div class="visit-section-block">
                            <div class="section-header-bar">
                                <div class="section-header-title">
                                    <span class="section-number-circle">5</span>
                                    <i class="fas fa-stethoscope"></i>
                                    Diagnosis & Treatment
                                </div>
                            </div>
                            
                            <div class="section-content">
                                <?php if (!empty($visit['symptoms'])): ?>
                                <div class="symptoms-box">
                                    <div class="symptoms-label">
                                        <i class="fas fa-notes-medical"></i> Symptoms
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
                                
                                <?php if (!empty($visit['follow_up_date'])): ?>
                                <div style="padding:12px 16px;background:var(--cyan-bg);border-radius:var(--radius-md);font-size:0.78rem;color:var(--cyan);border-left:4px solid var(--cyan);font-weight:800;">
                                    <i class="fas fa-calendar-check"></i>
                                    Follow-up: <?= date('d M Y', strtotime($visit['follow_up_date'])) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- SECTION 6: MEDICATIONS -->
                        <?php if (count($visit_prescriptions) > 0): ?>
                        <div class="visit-section-block">
                            <div class="section-header-bar">
                                <div class="section-header-title">
                                    <span class="section-number-circle">6</span>
                                    <i class="fas fa-pills"></i>
                                    Medications / Prescriptions
                                </div>
                                <span class="section-count-badge">
                                    <i class="fas fa-prescription"></i>
                                    <?= count($visit_prescriptions) ?> Prescriptions
                                </span>
                            </div>
                            
                            <div class="section-content">
                                <?php foreach ($visit_prescriptions as $pr): 
                                    $pr_items = $prescription_items_by_prescription[$pr['id']] ?? [];
                                ?>
                                    <div class="bill-card">
                                        <div class="bill-header">
                                            <span class="bill-number-big">
                                                <i class="fas fa-prescription"></i>
                                                <?= htmlspecialchars($pr['prescription_number'] ?? 'N/A') ?>
                                            </span>
                                            <div class="bill-meta">
                                                <span>
                                                    <i class="fas fa-user-md"></i>
                                                    Dr. <?= htmlspecialchars($pr['doctor_name'] ?? 'N/A') ?>
                                                </span>
                                                <?php if (!empty($pr['pharmacist_name'])): ?>
                                                <span>
                                                    <i class="fas fa-user-nurse"></i>
                                                    Pharm: <?= htmlspecialchars($pr['pharmacist_name']) ?>
                                                </span>
                                                <?php endif; ?>
                                                <span>
                                                    <i class="fas fa-calendar"></i>
                                                    <?= date('d M Y', strtotime($pr['created_at'] ?? 'now')) ?>
                                                </span>
                                                <span class="status-badge <?= getStatusClass($pr['status']) ?>">
                                                    <?= htmlspecialchars(ucfirst($pr['status'] ?? 'N/A')) ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <?php if (count($pr_items) > 0): ?>
                                            <div style="overflow-x:auto;">
                                                <table class="modern-table">
                                                    <thead>
                                                        <tr>
                                                            <th style="width:40px;">#</th>
                                                            <th>Medication</th>
                                                            <th>Dosage</th>
                                                            <th>Frequency</th>
                                                            <th>Route</th>
                                                            <th style="text-align:center;">Qty</th>
                                                            <th>Instructions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php $med_num = 1; foreach ($pr_items as $item): ?>
                                                            <tr>
                                                                <td style="text-align:center;font-weight:800;color:var(--text-secondary);font-family:var(--font-mono);"><?= $med_num++ ?></td>
                                                                <td style="font-weight:800;color:var(--purple);"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                                                <td style="font-size:0.72rem;"><?= htmlspecialchars($item['dosage'] ?? '—') ?></td>
                                                                <td style="font-size:0.72rem;"><?= htmlspecialchars($item['frequency'] ?? '—') ?></td>
                                                                <td style="font-size:0.72rem;"><?= htmlspecialchars($item['route'] ?? '—') ?></td>
                                                                <td style="text-align:center;font-family:var(--font-mono);font-weight:900;color:var(--purple);">
                                                                    <?= number_format($item['quantity'] ?? 0) ?>
                                                                </td>
                                                                <td style="font-size:0.72rem;color:var(--text-secondary);">
                                                                    <?= htmlspecialchars($item['instructions'] ?? $item['pharmacy_instructions'] ?? '—') ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="empty-mini">
                                                <i class="fas fa-pills"></i>
                                                No medications in this prescription
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- SECTION 7: PROCEDURES (FROM bill_items) -->
                        <?php if (count($visit_procedures) > 0): ?>
                        <div class="visit-section-block">
                            <div class="section-header-bar">
                                <div class="section-header-title">
                                    <span class="section-number-circle">7</span>
                                    <i class="fas fa-procedures"></i>
                                    Procedures
                                </div>
                                <span class="section-count-badge">
                                    <i class="fas fa-syringe"></i>
                                    <?= count($visit_procedures) ?> Procedures
                                </span>
                            </div>
                            
                            <div class="section-content" style="padding:0;">
                                <div style="overflow-x:auto;">
                                    <table class="modern-table">
                                        <thead>
                                            <tr>
                                                <th style="width:40px;">#</th>
                                                <th>Procedure Name</th>
                                                <th style="text-align:center;">Quantity</th>
                                                <th style="text-align:right;">Unit Price</th>
                                                <th style="text-align:right;">Total Price</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $proc_num = 1; foreach ($visit_procedures as $p): ?>
                                                <tr>
                                                    <td style="text-align:center;font-weight:800;color:var(--text-secondary);font-family:var(--font-mono);"><?= $proc_num++ ?></td>
                                                    <td style="font-weight:800;color:var(--teal);">
                                                        <i class="fas fa-procedures" style="font-size:0.72rem;"></i>
                                                        <?= htmlspecialchars($p['item_name'] ?? 'N/A') ?>
                                                    </td>
                                                    <td style="text-align:center;font-family:var(--font-mono);font-weight:900;color:var(--teal);">
                                                        <?= number_format($p['quantity'] ?? 0) ?>
                                                    </td>
                                                    <td class="money-cell teal">
                                                        <span class="currency-prefix"><?= $currency ?></span><?= number_format($p['unit_price'] ?? 0, 0) ?>
                                                    </td>
                                                    <td class="money-cell teal">
                                                        <span class="currency-prefix"><?= $currency ?></span><?= number_format($p['total_price'] ?? 0, 0) ?>
                                                    </td>
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
                                                <td colspan="4" style="text-align:right;font-weight:900;font-size:0.82rem;">
                                                    <i class="fas fa-calculator"></i> PROCEDURES TOTAL
                                                </td>
                                                <td style="text-align:right;font-weight:900;font-size:0.9rem;color:var(--teal);font-family:var(--font-mono);">
                                                    <?php 
                                                    $proc_total = 0;
                                                    foreach ($visit_procedures as $p) {
                                                        $proc_total += (float)($p['total_price'] ?? 0);
                                                    }
                                                    echo $currency . ' ' . number_format($proc_total, 0);
                                                    ?>
                                                </td>
                                                <td style="text-align:center;font-weight:900;color:var(--teal);font-size:0.72rem;">
                                                    <?= count($visit_procedures) ?> items
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- SECTION 8: MEDICAL EQUIPMENT -->
                        <?php if (count($visit_equipment) > 0): ?>
                        <div class="visit-section-block">
                            <div class="section-header-bar">
                                <div class="section-header-title">
                                    <span class="section-number-circle">8</span>
                                    <i class="fas fa-tools"></i>
                                    Medical Equipment
                                </div>
                                <span class="section-count-badge">
                                    <i class="fas fa-toolbox"></i>
                                    <?= count($visit_equipment) ?> Items
                                </span>
                            </div>
                            
                            <div class="section-content" style="padding:0;">
                                <div style="overflow-x:auto;">
                                    <table class="modern-table">
                                        <thead>
                                            <tr>
                                                <th style="width:40px;">#</th>
                                                <th>Equipment Name</th>
                                                <th style="text-align:center;">Quantity</th>
                                                <th style="text-align:right;">Unit Price</th>
                                                <th style="text-align:right;">Total</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $eq_num = 1; foreach ($visit_equipment as $e): ?>
                                                <tr>
                                                    <td style="text-align:center;font-weight:800;color:var(--text-secondary);font-family:var(--font-mono);"><?= $eq_num++ ?></td>
                                                    <td style="font-weight:800;color:var(--purple);">
                                                        <i class="fas fa-tools" style="font-size:0.72rem;"></i>
                                                        <?= htmlspecialchars($e['item_name'] ?? 'N/A') ?>
                                                    </td>
                                                    <td style="text-align:center;font-family:var(--font-mono);font-weight:900;color:var(--purple);">
                                                        <?= number_format($e['quantity'] ?? 0) ?>
                                                    </td>
                                                    <td class="money-cell purple">
                                                        <span class="currency-prefix"><?= $currency ?></span><?= number_format($e['unit_price'] ?? 0, 0) ?>
                                                    </td>
                                                    <td class="money-cell purple">
                                                        <span class="currency-prefix"><?= $currency ?></span><?= number_format($e['total_price'] ?? 0, 0) ?>
                                                    </td>
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
                                                <td colspan="4" style="text-align:right;font-weight:900;font-size:0.82rem;">
                                                    <i class="fas fa-calculator"></i> EQUIPMENT TOTAL
                                                </td>
                                                <td style="text-align:right;font-weight:900;font-size:0.9rem;color:var(--purple);font-family:var(--font-mono);">
                                                    <?php 
                                                    $eq_total = 0;
                                                    foreach ($visit_equipment as $e) {
                                                        $eq_total += (float)($e['total_price'] ?? 0);
                                                    }
                                                    echo $currency . ' ' . number_format($eq_total, 0);
                                                    ?>
                                                </td>
                                                <td style="text-align:center;font-weight:900;color:var(--purple);font-size:0.72rem;">
                                                    <?= count($visit_equipment) ?> items
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- SECTION 9: BILLS & PAYMENTS -->
                        <?php if (count($visit_bills) > 0): ?>
                        <div class="visit-section-block">
                            <div class="section-header-bar">
                                <div class="section-header-title">
                                    <span class="section-number-circle">9</span>
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    Bills & Payments
                                </div>
                                <span class="section-count-badge">
                                    <i class="fas fa-receipt"></i>
                                    <?= count($visit_bills) ?> Bill(s)
                                </span>
                            </div>
                            
                            <div class="section-content">
                                <?php foreach ($visit_bills as $b): 
                                    $bill_items = $bill_items_by_bill[$b['id']] ?? [];
                                    $bill_payments = $payments_by_bill[$b['id']] ?? [];
                                    $bill_status_class = getStatusClass($b['status']);
                                ?>
                                    <div class="bill-card">
                                        <div class="bill-header">
                                            <span class="bill-number-big">
                                                <i class="fas fa-file-invoice"></i>
                                                <?= htmlspecialchars($b['bill_number'] ?? 'N/A') ?>
                                            </span>
                                            <div class="bill-meta">
                                                <span>
                                                    <i class="fas fa-user"></i>
                                                    Created by: <?= htmlspecialchars($b['created_by_name'] ?? 'N/A') ?>
                                                </span>
                                                <span>
                                                    <i class="fas fa-calendar"></i>
                                                    <?= date('d M Y, H:i', strtotime($b['created_at'] ?? 'now')) ?>
                                                </span>
                                                <span class="status-badge <?= $bill_status_class ?>">
                                                    <?= strtoupper(htmlspecialchars($b['status'] ?? 'N/A')) ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <?php if (count($bill_items) > 0): ?>
                                            <div style="overflow-x:auto;margin-bottom:14px;">
                                                <table class="modern-table">
                                                    <thead>
                                                        <tr>
                                                            <th style="width:40px;">#</th>
                                                            <th>Item Name</th>
                                                            <th>Type</th>
                                                            <th style="text-align:center;">Qty</th>
                                                            <th style="text-align:right;">Unit Price</th>
                                                            <th style="text-align:right;">Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php $item_num = 1; foreach ($bill_items as $bi): 
                                                            $item_type = $bi['item_type'] ?? 'other';
                                                            $type_color = 'primary';
                                                            if ($item_type === 'medication') $type_color = 'warning';
                                                            elseif ($item_type === 'lab_test') $type_color = 'cyan';
                                                            elseif ($item_type === 'procedure') $type_color = 'teal';
                                                            elseif ($item_type === 'equipment') $type_color = 'purple';
                                                            elseif ($item_type === 'consultation') $type_color = 'success';
                                                        ?>
                                                            <tr>
                                                                <td style="text-align:center;font-weight:800;color:var(--text-secondary);font-family:var(--font-mono);"><?= $item_num++ ?></td>
                                                                <td style="font-weight:700;font-size:0.75rem;"><?= htmlspecialchars($bi['item_name'] ?? 'N/A') ?></td>
                                                                <td>
                                                                    <span style="font-size:0.6rem;font-weight:900;padding:3px 8px;background:var(--<?= $type_color ?>-bg);color:var(--<?= $type_color ?>);border-radius:var(--radius-sm);text-transform:uppercase;letter-spacing:0.04em;">
                                                                        <?= htmlspecialchars(str_replace('_', ' ', $item_type)) ?>
                                                                    </span>
                                                                </td>
                                                                <td style="text-align:center;font-family:var(--font-mono);font-weight:800;"><?= number_format($bi['quantity'] ?? 0) ?></td>
                                                                <td class="money-cell">
                                                                    <span class="currency-prefix"><?= $currency ?></span><?= number_format($bi['unit_price'] ?? 0, 0) ?>
                                                                </td>
                                                                <td class="money-cell">
                                                                    <span class="currency-prefix"><?= $currency ?></span><?= number_format($bi['total_price'] ?? 0, 0) ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (count($bill_payments) > 0): ?>
                                            <div style="margin-bottom:14px;padding:12px 14px;background:var(--success-bg);border-radius:var(--radius-md);border-left:4px solid var(--success);">
                                                <div style="font-size:0.7rem;font-weight:900;color:var(--success);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                                                    <i class="fas fa-check-circle"></i> Payment History
                                                </div>
                                                <div style="overflow-x:auto;">
                                                    <table style="width:100%;border-collapse:collapse;font-size:0.72rem;">
                                                        <thead>
                                                            <tr style="border-bottom:1px solid rgba(5, 150, 105, 0.2);">
                                                                <th style="text-align:left;padding:6px 8px;font-size:0.58rem;font-weight:900;color:var(--success);text-transform:uppercase;">Receipt #</th>
                                                                <th style="text-align:left;padding:6px 8px;font-size:0.58rem;font-weight:900;color:var(--success);text-transform:uppercase;">Method</th>
                                                                <th style="text-align:left;padding:6px 8px;font-size:0.58rem;font-weight:900;color:var(--success);text-transform:uppercase;">Received By</th>
                                                                <th style="text-align:left;padding:6px 8px;font-size:0.58rem;font-weight:900;color:var(--success);text-transform:uppercase;">Date & Time</th>
                                                                <th style="text-align:right;padding:6px 8px;font-size:0.58rem;font-weight:900;color:var(--success);text-transform:uppercase;">Amount</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($bill_payments as $pay): ?>
                                                                <tr style="border-bottom:1px dashed rgba(5, 150, 105, 0.15);">
                                                                    <td style="padding:8px;font-family:var(--font-mono);font-weight:800;color:var(--success);font-size:0.7rem;">
                                                                        <?= htmlspecialchars($pay['receipt_number'] ?? 'N/A') ?>
                                                                    </td>
                                                                    <td style="padding:8px;font-size:0.7rem;font-weight:700;">
                                                                        <i class="fas fa-credit-card" style="color:var(--success);"></i>
                                                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $pay['payment_method'] ?? 'Cash'))) ?>
                                                                    </td>
                                                                    <td style="padding:8px;font-size:0.7rem;font-weight:700;">
                                                                        <?= htmlspecialchars($pay['received_by_name'] ?? 'N/A') ?>
                                                                    </td>
                                                                    <td style="padding:8px;font-size:0.68rem;color:var(--text-secondary);font-family:var(--font-mono);">
                                                                        <?= date('d M Y, H:i', strtotime($pay['received_at'] ?? 'now')) ?>
                                                                    </td>
                                                                    <td style="padding:8px;text-align:right;font-family:var(--font-mono);font-weight:900;color:var(--success);font-size:0.78rem;">
                                                                        <span style="font-size:0.62rem;color:var(--text-secondary);margin-right:3px;"><?= $currency ?></span>
                                                                        <?= number_format($pay['amount'] ?? 0, 0) ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="bill-summary">
                                            <div class="bill-summary-item">
                                                <div class="bill-summary-label"><i class="fas fa-list"></i> Subtotal</div>
                                                <div class="bill-summary-value">
                                                    <span style="font-size:0.65rem;color:var(--text-secondary);"><?= $currency ?></span>
                                                    <?= number_format($b['subtotal'] ?? 0, 0) ?>
                                                </div>
                                            </div>
                                            
                                            <?php if (($b['pharmacy_discount'] ?? 0) > 0): ?>
                                            <div class="bill-summary-item">
                                                <div class="bill-summary-label" style="color:var(--purple);"><i class="fas fa-prescription"></i> Pharm Disc</div>
                                                <div class="bill-summary-value discount">
                                                    -<span style="font-size:0.65rem;color:var(--text-secondary);"><?= $currency ?></span>
                                                    <?= number_format($b['pharmacy_discount'] ?? 0, 0) ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (($b['cashier_discount'] ?? 0) > 0): ?>
                                            <div class="bill-summary-item">
                                                <div class="bill-summary-label" style="color:var(--warning);"><i class="fas fa-tag"></i> Cashier Disc</div>
                                                <div class="bill-summary-value discount">
                                                    -<span style="font-size:0.65rem;color:var(--text-secondary);"><?= $currency ?></span>
                                                    <?= number_format($b['cashier_discount'] ?? 0, 0) ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (($b['pharmacy_premium'] ?? 0) > 0): ?>
                                            <div class="bill-summary-item">
                                                <div class="bill-summary-label" style="color:var(--purple);"><i class="fas fa-star"></i> Pharm Prem</div>
                                                <div class="bill-summary-value premium">
                                                    +<span style="font-size:0.65rem;color:var(--text-secondary);"><?= $currency ?></span>
                                                    <?= number_format($b['pharmacy_premium'] ?? 0, 0) ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (($b['cashier_premium'] ?? 0) > 0): ?>
                                            <div class="bill-summary-item">
                                                <div class="bill-summary-label" style="color:var(--purple);"><i class="fas fa-star"></i> Cashier Prem</div>
                                                <div class="bill-summary-value premium">
                                                    +<span style="font-size:0.65rem;color:var(--text-secondary);"><?= $currency ?></span>
                                                    <?= number_format($b['cashier_premium'] ?? 0, 0) ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="bill-summary-item">
                                                <div class="bill-summary-label" style="color:var(--primary);"><i class="fas fa-calculator"></i> Total</div>
                                                <div class="bill-summary-value total">
                                                    <span style="font-size:0.7rem;color:var(--text-secondary);"><?= $currency ?></span>
                                                    <?= number_format($b['total_amount'] ?? 0, 0) ?>
                                                </div>
                                            </div>
                                            
                                            <div class="bill-summary-item">
                                                <div class="bill-summary-label" style="color:var(--success);"><i class="fas fa-check-circle"></i> Paid</div>
                                                <div class="bill-summary-value paid">
                                                    <span style="font-size:0.7rem;color:var(--text-secondary);"><?= $currency ?></span>
                                                    <?= number_format($b['paid_amount'] ?? 0, 0) ?>
                                                </div>
                                            </div>
                                            
                                            <div class="bill-summary-item">
                                                <div class="bill-summary-label" style="color:<?= ($b['balance'] ?? 0) > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                                                    <i class="fas fa-exclamation-circle"></i> Balance
                                                </div>
                                                <div class="bill-summary-value balance" style="color:<?= ($b['balance'] ?? 0) > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                                                    <span style="font-size:0.7rem;color:var(--text-secondary);"><?= $currency ?></span>
                                                    <?= number_format($b['balance'] ?? 0, 0) ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="bill-total-highlight">
                                            <div class="bill-total-label">
                                                <i class="fas fa-file-invoice-dollar"></i>
                                                Bill Total
                                            </div>
                                            <div class="bill-total-value">
                                                <?= $currency ?> <?= number_format($b['total_amount'] ?? 0, 0) ?>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($b['notes'])): ?>
                                        <div style="margin-top:12px;padding:10px 14px;background:var(--bg-body);border-radius:var(--radius-sm);font-size:0.7rem;color:var(--text-secondary);border-left:3px solid var(--border-color);">
                                            <i class="fas fa-sticky-note"></i> <strong>Notes:</strong> <?= htmlspecialchars($b['notes']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-hospital-user"></i>
            <p>No Visits Recorded</p>
            <p style="font-size:0.78rem;margin-top:8px;color:var(--text-muted);">This patient has no visit records yet.</p>
        </div>
    <?php endif; ?>

</main>

<script>
console.log('%c👤 Audit Patient Details V2 - SAWA NA ADMIN V6', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Role: <?= $user_role ?>', 'font-size:12px; color:#34D399; font-weight:bold;');
console.log('%c✅ CSS nzuri sana - modern design', 'font-size:12px; color:#34D399; font-weight:bold;');
console.log('%c✅ Kila Visit na Card yake (blue margin)', 'font-size:12px; color:#34D399; font-weight:bold;');
console.log('%c✅ Sections 9: Patient → Visit → Vitals → Labs → Diagnosis → Meds → Procedures → Equipment → Bills', 'font-size:12px; color:#34D399; font-weight:bold;');
console.log('%c✅ Procedures kutoka bill_items (status = paid)', 'font-size:12px; color:#7C3AED; font-weight:bold;');
console.log('%c✅ Doctor + Reception aliyecreate kila visit', 'font-size:12px; color:#7C3AED; font-weight:bold;');
</script>

</body>
</html>