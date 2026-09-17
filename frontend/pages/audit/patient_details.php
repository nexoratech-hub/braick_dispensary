<?php
// ================================================================
// FILE: frontend/pages/audit/patient_details.php
// PATIENT DETAILS - V5 (FOR AUDIT ROLE)
// ✅ Allow both admin and audit roles
// ✅ Path sahihi kwa pages/audit/ directory
// ✅ VIEW ONLY - No edit/delete buttons
// ✅ Oxygen Saturation INAONEKANA
// ✅ Blood Group kwenye Visit Information
// ✅ Vital Signs HAZIRUDIWI (latest only)
// ✅ Allergies Alert kwenye Header
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ ALLOW BOTH admin AND audit
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

$patient_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($patient_id <= 0) {
    header('Location: patients.php?branch=' . urlencode($selected_branch_id));
    exit;
}

// ✅ Determine base path
$is_admin = ($user_role === 'admin');
$base_path = $is_admin 
    ? '/dispensary_system/frontend/pages/admin/audit' 
    : '/dispensary_system/frontend/pages/audit';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
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
        SELECT v.*, u.full_name AS doctor_name, u.role AS doctor_role,
               u.specialty AS doctor_specialty, r.full_name AS receptionist_name,
               d.disease_name, d.disease_code
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
// GET VITALS - GROUPED BY VISIT (only latest per visit)
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
// GET BILLS
// ================================================================
$bills_by_visit = [];
try {
    $stmt = $db->prepare("
        SELECT b.*, u.full_name AS created_by_name, u.role AS created_by_role
        FROM bills b LEFT JOIN users u ON b.created_by = u.id
        WHERE b.patient_id = ? ORDER BY b.created_at DESC
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
// GET BILL ITEMS
// ================================================================
$bill_items_by_bill = [];
try {
    $stmt = $db->prepare("
        SELECT bi.* FROM bill_items bi INNER JOIN bills b ON bi.bill_id = b.id
        WHERE b.patient_id = ? ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $all_bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all_bill_items as $item) {
        $bid = $item['bill_id'] ?? 0;
        if (!isset($bill_items_by_bill[$bid])) $bill_items_by_bill[$bid] = [];
        $bill_items_by_bill[$bid][] = $item;
    }
} catch (Exception $e) {}

// ================================================================
// GET PRESCRIPTIONS
// ================================================================
$prescriptions_by_visit = [];
try {
    $stmt = $db->prepare("
        SELECT pr.*, u.full_name AS doctor_name,
               (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = pr.id) AS item_count
        FROM prescriptions pr LEFT JOIN users u ON pr.doctor_id = u.id
        WHERE pr.patient_id = ? ORDER BY pr.created_at DESC
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
// GET LAB TESTS
// ================================================================
$lab_tests_by_visit = [];
try {
    $stmt = $db->prepare("
        SELECT lt.*, u.full_name AS technician_name
        FROM lab_tests lt LEFT JOIN users u ON lt.lab_technician_id = u.id
        WHERE lt.patient_id = ? ORDER BY lt.created_at DESC
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
// GET PROCEDURES
// ================================================================
$procedures_by_visit = [];
try {
    $stmt = $db->prepare("
        SELECT pr.*, u.full_name AS doctor_name
        FROM procedures pr LEFT JOIN users u ON pr.doctor_id = u.id
        WHERE pr.patient_id = ? ORDER BY pr.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $all_procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all_procedures as $p) {
        $vid = $p['visit_id'] ?? 0;
        if (!isset($procedures_by_visit[$vid])) $procedures_by_visit[$vid] = [];
        $procedures_by_visit[$vid][] = $p;
    }
} catch (Exception $e) {}

// ================================================================
// GET APPOINTMENTS
// ================================================================
$appointments = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, u.full_name AS doctor_name, u.specialty AS doctor_specialty
        FROM appointments a LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.patient_id = ? ORDER BY a.appointment_date DESC LIMIT 20
    ");
    $stmt->execute([$patient_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// GET REFERRALS
// ================================================================
$referrals = [];
try {
    $stmt = $db->prepare("
        SELECT r.*, u_from.full_name AS from_doctor_name, u_to.full_name AS to_doctor_name
        FROM referrals r
        LEFT JOIN users u_from ON r.from_doctor_id = u_from.id
        LEFT JOIN users u_to ON r.to_doctor_id = u_to.id
        WHERE r.patient_id = ? ORDER BY r.referral_date DESC LIMIT 20
    ");
    $stmt->execute([$patient_id]);
    $referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// GET SICK SHEETS
// ================================================================
$sick_sheets = [];
try {
    $stmt = $db->prepare("
        SELECT pd.*, u.full_name AS doctor_name
        FROM patient_documents pd LEFT JOIN users u ON pd.doctor_id = u.id
        WHERE pd.patient_id = ? AND pd.document_type = 'sick_sheet' AND pd.status = 'active'
        ORDER BY pd.upload_date DESC LIMIT 20
    ");
    $stmt->execute([$patient_id]);
    $sick_sheets = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
foreach (($all_bills ?? []) as $b) {
    $stats['total_spent'] += (float)($b['total_amount'] ?? 0);
    $stats['total_paid'] += (float)($b['paid_amount'] ?? 0);
    $stats['total_balance'] += (float)($b['balance'] ?? 0);
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

// ✅ Include header/sidebar - PATH SAHIHI kwa pages/audit/
include_once __DIR__ . '/../../components/audit_header.php';
include_once __DIR__ . '/../../components/audit_sidebar.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ================================================================
   THEME + FONTS
   ================================================================ */
:root {
    --font-primary: 'Inter', -apple-system, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-bg: #E8F0FE;
    --primary-accent: #3B82F6;
    
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
    --pink: #DB2777;
    --pink-bg: #FCE7F3;
    --info: #0891B2;
    --info-bg: #CFFAFE;
    --teal: #0D9488;
    --teal-bg: #CCFBF1;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
}

[data-theme="dark"] {
    --primary: #3B82F6;
    --primary-dark: #2563EB;
    --primary-bg: #1E3A5F;
    --primary-accent: #60A5FA;
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3A2A1A;
    --purple-bg: #2D1B4E;
    --pink-bg: #4A1E36;
    --info-bg: #164E63;
    --teal-bg: #134E4A;
}

* {
    font-family: var(--font-primary);
    -webkit-font-smoothing: antialiased;
}

html, body {
    font-family: var(--font-primary);
    background: var(--bg-body);
    font-size: 14px;
}

.mono, .money-cell, .stat-value, .vital-value, .mono-value {
    font-family: var(--font-mono) !important;
    font-feature-settings: 'tnum';
    letter-spacing: -0.02em;
}

/* PAGE HEADER */
.page-header {
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
    border-radius: 14px;
    padding: 18px 24px;
    margin-bottom: 18px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 350px; height: 350px;
    background: radial-gradient(circle, rgba(110, 168, 254, 0.18) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.page-header .page-title {
    color: white;
    font-size: 1.35rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}

.page-header .page-title i { 
    font-size: 1.5rem; 
    color: #BFDBFE;
}

.role-tag {
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    padding: 2px 10px;
    border-radius: 14px;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.role-tag.audit-role {
    background: linear-gradient(135deg, #3B82F6, #2563EB);
    color: white;
}

.page-header .page-subtitle {
    color: rgba(255,255,255,0.9);
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin-top: 3px;
}

.allergy-alert {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(220, 38, 38, 0.25);
    color: white;
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 0.72rem;
    font-weight: 700;
    border: 1px solid rgba(220, 38, 38, 0.5);
    margin-top: 6px;
    position: relative;
    z-index: 1;
    backdrop-filter: blur(4px);
    animation: pulseAlert 2s infinite;
}

@keyframes pulseAlert {
    0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); }
    50% { box-shadow: 0 0 0 8px rgba(220, 38, 38, 0); }
}

.branch-tag {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 3px 10px;
    border-radius: 14px;
    font-size: 0.68rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.15);
}

.btn-outline-light {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.25);
    padding: 8px 15px;
    border-radius: 9px;
    font-weight: 600;
    font-size: 0.78rem;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    position: relative;
    z-index: 1;
    cursor: pointer;
}

.btn-outline-light:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-1px);
}

.btn-export-pdf {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 9px;
    font-weight: 700;
    font-size: 0.78rem;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    position: relative;
    z-index: 1;
    cursor: pointer;
}

.btn-export-pdf:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(220, 38, 38, 0.35);
}

/* PROFILE CARD */
.profile-card {
    background: var(--bg-card);
    border-radius: 14px;
    padding: 18px 22px;
    border: 2px solid var(--border-color);
    margin-bottom: 18px;
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.profile-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #0B5ED7, #3B82F6);
}

.profile-avatar {
    width: 72px;
    height: 72px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1.75rem;
    color: white;
    background: linear-gradient(135deg, #0B5ED7, #3B82F6);
    box-shadow: 0 6px 16px rgba(11, 94, 215, 0.3);
    flex-shrink: 0;
    font-family: var(--font-mono);
    text-transform: uppercase;
}

.profile-info {
    flex: 1;
    min-width: 220px;
}

.profile-name {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--text-primary);
    margin-bottom: 4px;
    line-height: 1.2;
}

.profile-id {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 7px;
    background: var(--primary-bg);
    color: var(--primary);
    font-family: var(--font-mono);
    font-size: 0.72rem;
    font-weight: 800;
    border: 1px solid rgba(11, 94, 215, 0.2);
    margin-bottom: 8px;
}

[data-theme="dark"] .profile-id {
    background: #1E3A5F;
    color: #93C5FD;
}

.profile-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 8px;
}

.profile-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 7px;
    background: var(--bg-body);
    color: var(--text-secondary);
    font-size: 0.7rem;
    font-weight: 600;
    border: 1px solid var(--border-color);
}

.profile-meta-item i {
    color: var(--primary);
    font-size: 0.72rem;
}

.gender-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 7px;
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
}

.gender-badge.male {
    background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
    color: #1E40AF;
    border: 1px solid #93C5FD;
}

.gender-badge.female {
    background: linear-gradient(135deg, #FCE7F3, #FBCFE8);
    color: #9D174D;
    border: 1px solid #F9A8D4;
}

.gender-badge.other {
    background: var(--border-color);
    color: var(--text-secondary);
}

/* STATS GRID */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 18px;
}

.stat-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 16px 18px;
    border: 2px solid var(--border-color);
    transition: all 0.3s ease;
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
    min-height: 110px;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
}

.stat-card.visits::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6); }
.stat-card.spent::before { background: linear-gradient(90deg, #059669, #34D399); }
.stat-card.paid::before { background: linear-gradient(90deg, #0891B2, #06B6D4); }
.stat-card.balance::before { background: linear-gradient(90deg, #DC2626, #F87171); }

.stat-card .stat-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    color: white;
    margin-bottom: 8px;
    transition: transform 0.3s ease;
}

.stat-card:hover .stat-icon {
    transform: scale(1.08) rotate(-5deg);
}

.stat-card.visits .stat-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.stat-card.spent .stat-icon { background: linear-gradient(135deg, #059669, #34D399); }
.stat-card.paid .stat-icon { background: linear-gradient(135deg, #0891B2, #06B6D4); }
.stat-card.balance .stat-icon { background: linear-gradient(135deg, #DC2626, #F87171); }

.stat-card .stat-label {
    font-size: 0.62rem;
    color: var(--text-secondary);
    font-weight: 700;
    text-transform: uppercase;
    margin-bottom: 3px;
    letter-spacing: 0.05em;
}

.stat-card .stat-value {
    font-size: 1.3rem;
    font-weight: 800;
    font-family: var(--font-mono);
    color: var(--text-primary);
    letter-spacing: -0.03em;
    line-height: 1.1;
}

.stat-card.visits .stat-value { color: var(--primary); }
[data-theme="dark"] .stat-card.visits .stat-value { color: var(--primary-accent); }
.stat-card.spent .stat-value { color: var(--success); }
.stat-card.paid .stat-value { color: var(--info); }
.stat-card.balance .stat-value { color: var(--danger); }

/* VISIT CARD */
.visit-timeline {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 18px;
}

.visit-card {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--border-color);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}

.visit-card:hover {
    box-shadow: var(--shadow-md);
}

.visit-card-header {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    padding: 12px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.visit-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.visit-number-badge {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 6px 12px;
    border-radius: 8px;
    font-family: var(--font-mono);
    font-size: 0.72rem;
    font-weight: 800;
    border: 1px solid rgba(255,255,255,0.25);
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.visit-date-badge {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.68rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid rgba(255,255,255,0.15);
}

.visit-header-right {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.visit-card-body {
    padding: 18px 20px;
}

.visit-section {
    margin-bottom: 18px;
    padding-bottom: 16px;
    border-bottom: 2px dashed var(--border-color);
}

.visit-section:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.visit-section-title {
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--text-primary);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    flex-wrap: wrap;
}

.visit-section-title i {
    color: var(--primary);
    font-size: 0.9rem;
}

[data-theme="dark"] .visit-section-title i {
    color: var(--primary-accent);
}

.visit-section-count {
    margin-left: auto;
    background: var(--primary-bg);
    color: var(--primary);
    padding: 3px 10px;
    border-radius: 7px;
    font-size: 0.62rem;
    font-weight: 800;
    font-family: var(--font-mono);
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

[data-theme="dark"] .visit-section-count {
    background: #1E3A5F;
    color: #93C5FD;
}

/* VISIT INFO */
.visit-info-section {
    background: var(--primary-bg);
    border-radius: 10px;
    padding: 14px;
    margin-bottom: 12px;
    border-left: 4px solid var(--primary);
}

[data-theme="dark"] .visit-info-section {
    background: #1E3A5F;
}

.visit-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
}

.visit-info-item {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.visit-info-label {
    font-size: 0.6rem;
    color: var(--text-secondary);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: flex;
    align-items: center;
    gap: 4px;
}

.visit-info-label i {
    color: var(--primary);
    font-size: 0.65rem;
}

.visit-info-value {
    font-size: 0.78rem;
    color: var(--text-primary);
    font-weight: 700;
}

.visit-info-value.mono {
    font-family: var(--font-mono);
    font-size: 0.75rem;
}

/* VITAL SIGNS */
.vitals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(165px, 1fr));
    gap: 12px;
}

.vital-item {
    position: relative;
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-body) 100%);
    border: 2px solid var(--border-color);
    border-radius: 14px;
    padding: 14px 14px 12px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
    cursor: pointer;
    min-height: 110px;
}

.vital-item::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    transition: height 0.3s ease;
}

.vital-item::after {
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 100px; height: 100px;
    border-radius: 50%;
    opacity: 0.08;
    transition: all 0.4s ease;
    pointer-events: none;
}

.vital-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 24px rgba(0,0,0,0.12);
}

.vital-item:hover::before { height: 5px; }
.vital-item:hover::after { transform: scale(1.4); opacity: 0.14; }

.vital-item.temp { border-color: rgba(220, 38, 38, 0.25); background: linear-gradient(135deg, var(--bg-card) 0%, #FEF2F2 100%); }
[data-theme="dark"] .vital-item.temp { background: linear-gradient(135deg, #1E293B 0%, #3A1A1A 100%); border-color: rgba(220, 38, 38, 0.35); }
.vital-item.temp::before { background: linear-gradient(90deg, #DC2626, #F87171); }
.vital-item.temp::after { background: #DC2626; }
.vital-item.temp .vital-label i { color: #DC2626; }
.vital-item.temp .vital-value { color: #DC2626; }

.vital-item.bp { border-color: rgba(124, 58, 237, 0.25); background: linear-gradient(135deg, var(--bg-card) 0%, #F5F3FF 100%); }
[data-theme="dark"] .vital-item.bp { background: linear-gradient(135deg, #1E293B 0%, #2D1B4E 100%); border-color: rgba(124, 58, 237, 0.35); }
.vital-item.bp::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }
.vital-item.bp::after { background: #7C3AED; }
.vital-item.bp .vital-label i { color: #7C3AED; }
.vital-item.bp .vital-value { color: #7C3AED; }

.vital-item.pulse { border-color: rgba(219, 39, 119, 0.25); background: linear-gradient(135deg, var(--bg-card) 0%, #FDF2F8 100%); }
[data-theme="dark"] .vital-item.pulse { background: linear-gradient(135deg, #1E293B 0%, #4A1E36 100%); border-color: rgba(219, 39, 119, 0.35); }
.vital-item.pulse::before { background: linear-gradient(90deg, #DB2777, #F472B6); }
.vital-item.pulse::after { background: #DB2777; }
.vital-item.pulse .vital-label i { color: #DB2777; }
.vital-item.pulse .vital-value { color: #DB2777; }

.vital-item.resp { border-color: rgba(8, 145, 178, 0.25); background: linear-gradient(135deg, var(--bg-card) 0%, #ECFEFF 100%); }
[data-theme="dark"] .vital-item.resp { background: linear-gradient(135deg, #1E293B 0%, #164E63 100%); border-color: rgba(8, 145, 178, 0.35); }
.vital-item.resp::before { background: linear-gradient(90deg, #0891B2, #06B6D4); }
.vital-item.resp::after { background: #0891B2; }
.vital-item.resp .vital-label i { color: #0891B2; }
.vital-item.resp .vital-value { color: #0891B2; }

.vital-item.oxygen { border-color: rgba(5, 150, 105, 0.25); background: linear-gradient(135deg, var(--bg-card) 0%, #ECFDF5 100%); }
[data-theme="dark"] .vital-item.oxygen { background: linear-gradient(135deg, #1E293B 0%, #134E4A 100%); border-color: rgba(5, 150, 105, 0.35); }
.vital-item.oxygen::before { background: linear-gradient(90deg, #059669, #34D399); }
.vital-item.oxygen::after { background: #059669; }
.vital-item.oxygen .vital-label i { color: #059669; }
.vital-item.oxygen .vital-value { color: #059669; }

.vital-item.glucose { border-color: rgba(217, 119, 6, 0.25); background: linear-gradient(135deg, var(--bg-card) 0%, #FFFBEB 100%); }
[data-theme="dark"] .vital-item.glucose { background: linear-gradient(135deg, #1E293B 0%, #3A2A1A 100%); border-color: rgba(217, 119, 6, 0.35); }
.vital-item.glucose::before { background: linear-gradient(90deg, #D97706, #FBBF24); }
.vital-item.glucose::after { background: #D97706; }
.vital-item.glucose .vital-label i { color: #D97706; }
.vital-item.glucose .vital-value { color: #D97706; }

.vital-item.weight { border-color: rgba(11, 94, 215, 0.25); background: linear-gradient(135deg, var(--bg-card) 0%, #EFF6FF 100%); }
[data-theme="dark"] .vital-item.weight { background: linear-gradient(135deg, #1E293B 0%, #1E3A5F 100%); border-color: rgba(11, 94, 215, 0.35); }
.vital-item.weight::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6); }
.vital-item.weight::after { background: #0B5ED7; }
.vital-item.weight .vital-label i { color: #0B5ED7; }
.vital-item.weight .vital-value { color: #0B5ED7; }

.vital-item.height { border-color: rgba(79, 70, 229, 0.25); background: linear-gradient(135deg, var(--bg-card) 0%, #EEF2FF 100%); }
[data-theme="dark"] .vital-item.height { background: linear-gradient(135deg, #1E293B 0%, #1E1B4B 100%); border-color: rgba(79, 70, 229, 0.35); }
.vital-item.height::before { background: linear-gradient(90deg, #4F46E5, #818CF8); }
.vital-item.height::after { background: #4F46E5; }
.vital-item.height .vital-label i { color: #4F46E5; }
.vital-item.height .vital-value { color: #4F46E5; }

.vital-item.bmi { border-color: rgba(13, 148, 136, 0.25); background: linear-gradient(135deg, var(--bg-card) 0%, #F0FDFA 100%); }
[data-theme="dark"] .vital-item.bmi { background: linear-gradient(135deg, #1E293B 0%, #134E4A 100%); border-color: rgba(13, 148, 136, 0.35); }
.vital-item.bmi::before { background: linear-gradient(90deg, #0D9488, #5EEAD4); }
.vital-item.bmi::after { background: #0D9488; }
.vital-item.bmi .vital-label i { color: #0D9488; }
.vital-item.bmi .vital-value { color: #0D9488; }

.vital-item.muac { border-color: rgba(245, 158, 11, 0.25); background: linear-gradient(135deg, var(--bg-card) 0%, #FFFBEB 100%); }
[data-theme="dark"] .vital-item.muac { background: linear-gradient(135deg, #1E293B 0%, #3A2A1A 100%); border-color: rgba(245, 158, 11, 0.35); }
.vital-item.muac::before { background: linear-gradient(90deg, #F59E0B, #FCD34D); }
.vital-item.muac::after { background: #F59E0B; }
.vital-item.muac .vital-label i { color: #F59E0B; }
.vital-item.muac .vital-value { color: #F59E0B; }

.vital-item.pain { border-color: rgba(239, 68, 68, 0.25); background: linear-gradient(135deg, var(--bg-card) 0%, #FEF2F2 100%); }
[data-theme="dark"] .vital-item.pain { background: linear-gradient(135deg, #1E293B 0%, #3A1A1A 100%); border-color: rgba(239, 68, 68, 0.35); }
.vital-item.pain::before { background: linear-gradient(90deg, #EF4444, #FCA5A5); }
.vital-item.pain::after { background: #EF4444; }
.vital-item.pain .vital-label i { color: #EF4444; }
.vital-item.pain .vital-value { color: #EF4444; }

.vital-label {
    font-size: 0.62rem;
    color: var(--text-secondary);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 5px;
    position: relative;
    z-index: 1;
}

.vital-label i { font-size: 0.72rem; }

.vital-value {
    font-family: var(--font-mono);
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--text-primary);
    letter-spacing: -0.03em;
    line-height: 1.1;
    position: relative;
    z-index: 1;
}

.vital-value .unit {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-left: 3px;
}

.vital-recorded-by {
    font-size: 0.6rem;
    color: var(--text-secondary);
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 4px;
    padding-top: 6px;
    border-top: 1px dashed var(--border-color);
    font-weight: 600;
    position: relative;
    z-index: 1;
}

.vital-recorded-by i {
    color: var(--purple);
    font-size: 0.6rem;
}

/* VISIT TABLE */
.visit-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.72rem;
    background: var(--bg-body);
    border-radius: 8px;
    overflow: hidden;
}

.visit-table thead th {
    background: var(--primary-bg);
    color: var(--text-primary);
    padding: 9px 11px;
    text-align: left;
    font-weight: 800;
    font-size: 0.58rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 2px solid var(--border-color);
    white-space: nowrap;
}

[data-theme="dark"] .visit-table thead th {
    background: #1E3A5F;
    color: #93C5FD;
}

.visit-table tbody td {
    padding: 9px 11px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
}

.visit-table tbody tr:last-child td { border-bottom: none; }
.visit-table tbody tr:hover td { background: var(--primary-bg); }
[data-theme="dark"] .visit-table tbody tr:hover td { background: #1E3A5F; }

/* DIAGNOSIS / TREATMENT / SYMPTOMS */
.diagnosis-box {
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.06), rgba(167, 139, 250, 0.03));
    border-radius: 12px;
    padding: 14px;
    border: 2px solid var(--purple);
    border-left: 4px solid var(--purple);
    margin-bottom: 12px;
}

.diagnosis-box .diagnosis-label {
    font-size: 0.62rem;
    color: var(--purple);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.diagnosis-box .diagnosis-value {
    font-size: 0.85rem;
    color: var(--text-primary);
    font-weight: 700;
    line-height: 1.5;
    margin-bottom: 5px;
}

.diagnosis-box .disease-code {
    display: inline-block;
    font-family: var(--font-mono);
    background: var(--purple);
    color: white;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.65rem;
    font-weight: 800;
}

.treatment-box {
    background: linear-gradient(135deg, rgba(5, 150, 105, 0.06), rgba(52, 211, 153, 0.03));
    border-radius: 12px;
    padding: 14px;
    border: 2px solid var(--success);
    border-left: 4px solid var(--success);
    margin-bottom: 12px;
}

.treatment-box .treatment-label {
    font-size: 0.62rem;
    color: var(--success);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.treatment-box .treatment-value {
    font-size: 0.78rem;
    color: var(--text-primary);
    font-weight: 600;
    line-height: 1.5;
}

.symptoms-box {
    background: linear-gradient(135deg, rgba(217, 119, 6, 0.06), rgba(251, 191, 36, 0.03));
    border-radius: 12px;
    padding: 12px;
    border: 2px solid var(--warning);
    border-left: 4px solid var(--warning);
    margin-bottom: 12px;
}

.symptoms-box .symptoms-label {
    font-size: 0.62rem;
    color: var(--warning);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.symptoms-box .symptoms-value {
    font-size: 0.78rem;
    color: var(--text-primary);
    font-weight: 600;
    line-height: 1.5;
}

/* MONEY / STATUS */
.money-cell {
    font-family: var(--font-mono);
    font-weight: 800;
    font-size: 0.75rem;
    color: var(--success);
    text-align: right;
    white-space: nowrap;
}

.money-cell.danger { color: var(--danger); }
.money-cell.info { color: var(--info); }
.money-cell.purple { color: var(--purple); }
.money-cell.warning { color: var(--warning); }
.money-cell.blue { color: var(--primary); }

.money-cell .currency-prefix {
    font-size: 0.6rem;
    color: var(--text-secondary);
    margin-right: 2px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
}

.status-badge.success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.status-badge.warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
.status-badge.danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
.status-badge.info { background: var(--info-bg); color: var(--info); border: 1px solid var(--info); }
.status-badge.secondary { background: var(--border-color); color: var(--text-secondary); }

/* EMPTY STATE */
.no-visits-state {
    text-align: center;
    padding: 45px 20px;
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px dashed var(--border-color);
}

.no-visits-state i {
    font-size: 2.75rem;
    color: var(--primary);
    opacity: 0.2;
    margin-bottom: 12px;
    display: block;
}

.no-visits-state h3 {
    font-size: 1rem;
    font-weight: 800;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.no-visits-state p {
    color: var(--text-secondary);
    font-size: 0.78rem;
}

/* RESPONSIVE */
@media (max-width: 1024px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .vitals-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
}

@media (max-width: 768px) {
    .page-header { padding: 14px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    
    .profile-card { padding: 14px 16px; gap: 12px; }
    .profile-avatar { width: 60px; height: 60px; font-size: 1.5rem; }
    .profile-name { font-size: 1.05rem; }
    
    .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .stat-card { padding: 12px; min-height: 95px; }
    .stat-card .stat-value { font-size: 1.15rem; }
    
    .visit-card-header { padding: 10px 14px; }
    .visit-card-body { padding: 14px 16px; }
    
    .vitals-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
    .vital-item { padding: 12px; min-height: 100px; }
    .vital-value { font-size: 1.2rem; }
    .visit-info-grid { grid-template-columns: 1fr; }
}

@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .vitals-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
}

@media print {
    .btn-outline-light, .btn-export-pdf { display: none !important; }
    .page-header { background: #0B5ED7 !important; }
    .visit-card { break-inside: avoid; }
    .vital-item { break-inside: avoid; }
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
                    <?= date('d M Y', strtotime($patient['created_at'] ?? 'now')) ?>
                </span>
            </p>
            
            <!-- ✅ ALLERGIES ALERT -->
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
            <a href="<?= $base_path ?>/patient_pdf.php?id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" 
               target="_blank"
               class="btn-export-pdf">
                <i class="fas fa-file-pdf"></i> PDF
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

    <!-- VISIT TIMELINE -->
    <?php if (count($visits) > 0): ?>
        <div class="visit-timeline">
            <?php foreach ($visits as $visit): 
                $vid = $visit['id'];
                $visit_vital = $vitals_by_visit[$vid] ?? null;
                $visit_bills = $bills_by_visit[$vid] ?? [];
                $visit_prescriptions = $prescriptions_by_visit[$vid] ?? [];
                $visit_labs = $lab_tests_by_visit[$vid] ?? [];
                $visit_procedures = $procedures_by_visit[$vid] ?? [];
            ?>
                <div class="visit-card">
                    
                    <div class="visit-card-header">
                        <div class="visit-header-left">
                            <span class="visit-number-badge">
                                <i class="fas fa-hashtag"></i>
                                <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                            </span>
                            <span class="visit-date-badge">
                                <i class="fas fa-calendar-alt"></i>
                                <?= date('d M Y', strtotime($visit['visit_date'] ?? 'now')) ?>
                                <span style="opacity:0.7;">•</span>
                                <?= date('H:i', strtotime($visit['visit_date'] ?? 'now')) ?>
                            </span>
                        </div>
                        
                        <div class="visit-header-right">
                            <?php if (!empty($visit['doctor_name'])): ?>
                            <span class="visit-date-badge">
                                <i class="fas fa-user-md"></i>
                                <?= htmlspecialchars($visit['doctor_name']) ?>
                            </span>
                            <?php endif; ?>
                            
                            <span class="status-badge <?= getStatusClass($visit['status']) ?>" style="background: rgba(255,255,255,0.2); color: white; border-color: rgba(255,255,255,0.3);">
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $visit['status'] ?? 'N/A'))) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="visit-card-body">
                        
                        <!-- 1. PATIENT INFORMATION -->
                        <div class="visit-section">
                            <div class="visit-section-title">
                                <i class="fas fa-user-injured"></i>
                                1. Patient Information
                            </div>
                            
                            <div class="visit-info-grid">
                                <div class="visit-info-item">
                                    <div class="visit-info-label"><i class="fas fa-user"></i> Name</div>
                                    <div class="visit-info-value"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></div>
                                </div>
                                <div class="visit-info-item">
                                    <div class="visit-info-label"><i class="fas fa-id-card"></i> Patient ID</div>
                                    <div class="visit-info-value mono"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></div>
                                </div>
                                <div class="visit-info-item">
                                    <div class="visit-info-label"><i class="fas fa-venus-mars"></i> Gender</div>
                                    <div class="visit-info-value"><?= htmlspecialchars(ucfirst($patient['gender'] ?? 'N/A')) ?></div>
                                </div>
                                <div class="visit-info-item">
                                    <div class="visit-info-label"><i class="fas fa-birthday-cake"></i> Age</div>
                                    <div class="visit-info-value"><?= calculateAge($patient['date_of_birth'] ?? null) ?></div>
                                </div>
                                <div class="visit-info-item">
                                    <div class="visit-info-label"><i class="fas fa-phone"></i> Phone</div>
                                    <div class="visit-info-value mono"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></div>
                                </div>
                                <div class="visit-info-item">
                                    <div class="visit-info-label"><i class="fas fa-tint"></i> Blood Group</div>
                                    <div class="visit-info-value mono" style="color:var(--danger);font-weight:800;">
                                        <?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 2. VISIT INFORMATION -->
                        <div class="visit-section">
                            <div class="visit-section-title">
                                <i class="fas fa-clipboard-list"></i>
                                2. Visit Information
                            </div>
                            
                            <div class="visit-info-section">
                                <div class="visit-info-grid">
                                    <div class="visit-info-item">
                                        <div class="visit-info-label"><i class="fas fa-hashtag"></i> Visit #</div>
                                        <div class="visit-info-value mono"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></div>
                                    </div>
                                    <div class="visit-info-item">
                                        <div class="visit-info-label"><i class="fas fa-calendar-alt"></i> Date & Time</div>
                                        <div class="visit-info-value mono"><?= date('d M Y, H:i', strtotime($visit['visit_date'] ?? 'now')) ?></div>
                                    </div>
                                    <div class="visit-info-item">
                                        <div class="visit-info-label"><i class="fas fa-user-md"></i> Doctor</div>
                                        <div class="visit-info-value"><?= htmlspecialchars($visit['doctor_name'] ?? 'Not assigned') ?></div>
                                    </div>
                                    <div class="visit-info-item">
                                        <div class="visit-info-label"><i class="fas fa-user-tie"></i> Created By</div>
                                        <div class="visit-info-value"><?= htmlspecialchars($visit['receptionist_name'] ?? 'N/A') ?></div>
                                    </div>
                                    <div class="visit-info-item">
                                        <div class="visit-info-label"><i class="fas fa-stethoscope"></i> Type</div>
                                        <div class="visit-info-value"><?= htmlspecialchars($visit['visit_type'] ?? 'N/A') ?></div>
                                    </div>
                                    <div class="visit-info-item">
                                        <div class="visit-info-label"><i class="fas fa-tint"></i> Blood Group</div>
                                        <div class="visit-info-value mono" style="color:var(--danger);font-weight:800;">
                                            <?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                    <div class="visit-info-item">
                                        <div class="visit-info-label"><i class="fas fa-info-circle"></i> Status</div>
                                        <div class="visit-info-value">
                                            <span class="status-badge <?= getStatusClass($visit['status']) ?>">
                                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $visit['status'] ?? 'N/A'))) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 3. VITAL SIGNS -->
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
                                    3. Vital Signs
                                    <span class="visit-section-count">
                                        <i class="fas fa-user-nurse"></i>
                                        <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?>
                                    </span>
                                </div>
                                
                                <div class="vitals-grid">
                                    
                                    <?php if (hasVitalValue($visit_vital['temperature'] ?? null)): ?>
                                    <div class="vital-item temp">
                                        <div class="vital-label"><i class="fas fa-thermometer-half"></i> Temperature</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['temperature']) ?><span class="unit">°C</span></div>
                                        <div class="vital-recorded-by">
                                            <i class="fas fa-user-nurse"></i>
                                            <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['blood_pressure_systolic'] ?? null) && hasVitalValue($visit_vital['blood_pressure_diastolic'] ?? null)): ?>
                                    <div class="vital-item bp">
                                        <div class="vital-label"><i class="fas fa-heart"></i> Blood Pressure</div>
                                        <div class="vital-value"><?= $visit_vital['blood_pressure_systolic'] ?>/<?= $visit_vital['blood_pressure_diastolic'] ?><span class="unit">mmHg</span></div>
                                        <div class="vital-recorded-by">
                                            <i class="fas fa-user-nurse"></i>
                                            <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['pulse_rate'] ?? null)): ?>
                                    <div class="vital-item pulse">
                                        <div class="vital-label"><i class="fas fa-heartbeat"></i> Pulse Rate</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['pulse_rate']) ?><span class="unit">bpm</span></div>
                                        <div class="vital-recorded-by">
                                            <i class="fas fa-user-nurse"></i>
                                            <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['respiratory_rate'] ?? null)): ?>
                                    <div class="vital-item resp">
                                        <div class="vital-label"><i class="fas fa-lungs"></i> Respiratory</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['respiratory_rate']) ?><span class="unit">/min</span></div>
                                        <div class="vital-recorded-by">
                                            <i class="fas fa-user-nurse"></i>
                                            <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['oxygen_saturation'] ?? null)): ?>
                                    <div class="vital-item oxygen">
                                        <div class="vital-label"><i class="fas fa-wind"></i> Oxygen Sat. (SpO₂)</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['oxygen_saturation']) ?><span class="unit">%</span></div>
                                        <div class="vital-recorded-by">
                                            <i class="fas fa-user-nurse"></i>
                                            <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['blood_glucose'] ?? null)): ?>
                                    <div class="vital-item glucose">
                                        <div class="vital-label"><i class="fas fa-cube"></i> Blood Glucose</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['blood_glucose']) ?><span class="unit">mg/dL</span></div>
                                        <div class="vital-recorded-by">
                                            <i class="fas fa-user-nurse"></i>
                                            <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['weight'] ?? null)): ?>
                                    <div class="vital-item weight">
                                        <div class="vital-label"><i class="fas fa-weight"></i> Weight</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['weight']) ?><span class="unit">kg</span></div>
                                        <div class="vital-recorded-by">
                                            <i class="fas fa-user-nurse"></i>
                                            <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['height'] ?? null)): ?>
                                    <div class="vital-item height">
                                        <div class="vital-label"><i class="fas fa-ruler-vertical"></i> Height</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['height']) ?><span class="unit">cm</span></div>
                                        <div class="vital-recorded-by">
                                            <i class="fas fa-user-nurse"></i>
                                            <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['bmi'] ?? null)): ?>
                                    <div class="vital-item bmi">
                                        <div class="vital-label"><i class="fas fa-chart-line"></i> BMI</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['bmi']) ?></div>
                                        <div class="vital-recorded-by">
                                            <i class="fas fa-user-nurse"></i>
                                            <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['muac'] ?? null)): ?>
                                    <div class="vital-item muac">
                                        <div class="vital-label"><i class="fas fa-child"></i> MUAC</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['muac']) ?><span class="unit">cm</span></div>
                                        <div class="vital-recorded-by">
                                            <i class="fas fa-user-nurse"></i>
                                            <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (hasVitalValue($visit_vital['pain_score'] ?? null)): ?>
                                    <div class="vital-item pain">
                                        <div class="vital-label"><i class="fas fa-face-frown"></i> Pain Score</div>
                                        <div class="vital-value"><?= htmlspecialchars($visit_vital['pain_score']) ?><span class="unit">/10</span></div>
                                        <div class="vital-recorded-by">
                                            <i class="fas fa-user-nurse"></i>
                                            <?= htmlspecialchars($visit_vital['recorded_by_name'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                </div>
                                
                                <?php if (!empty($visit_vital['notes'])): ?>
                                <div style="margin-top:10px;padding:10px;background:var(--info-bg);border-radius:8px;font-size:0.7rem;color:var(--info);border-left:3px solid var(--info);font-weight:600;">
                                    <i class="fas fa-sticky-note"></i> <strong>Notes:</strong> <?= htmlspecialchars($visit_vital['notes']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- 4. LAB TESTS -->
                        <?php if (count($visit_labs) > 0): ?>
                        <div class="visit-section">
                            <div class="visit-section-title">
                                <i class="fas fa-flask"></i>
                                4. Lab Tests
                                <span class="visit-section-count"><?= count($visit_labs) ?> Tests</span>
                            </div>
                            
                            <table class="visit-table">
                                <thead>
                                    <tr>
                                        <th>Test Name</th>
                                        <th>Result</th>
                                        <th>Reference</th>
                                        <th>Technician</th>
                                        <th style="text-align:center;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($visit_labs as $lt): ?>
                                        <tr>
                                            <td style="font-weight:700;"><?= htmlspecialchars($lt['test_name'] ?? 'N/A') ?></td>
                                            <td>
                                                <?php if (!empty($lt['results'])): ?>
                                                    <span style="font-family:var(--font-mono);font-weight:800;color:var(--success);font-size:0.7rem;">
                                                        <?= htmlspecialchars(substr($lt['results'], 0, 50)) ?><?= strlen($lt['results']) > 50 ? '...' : '' ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color:var(--text-secondary);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size:0.68rem;color:var(--text-secondary);font-family:var(--font-mono);">
                                                <?= htmlspecialchars($lt['reference_range'] ?? '-') ?>
                                            </td>
                                            <td style="font-size:0.7rem;"><?= htmlspecialchars($lt['technician_name'] ?? 'N/A') ?></td>
                                            <td style="text-align:center;">
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
                        
                        <!-- 5. DIAGNOSIS -->
                        <?php if (!empty($visit['diagnosis']) || !empty($visit['disease_name']) || !empty($visit['symptoms']) || !empty($visit['treatment'])): ?>
                        <div class="visit-section">
                            <div class="visit-section-title">
                                <i class="fas fa-stethoscope"></i>
                                5. Diagnosis & Treatment
                            </div>
                            
                            <?php if (!empty($visit['symptoms'])): ?>
                            <div class="symptoms-box">
                                <div class="symptoms-label">
                                    <i class="fas fa-notes-medical"></i> Symptoms
                                </div>
                                <div class="symptoms-value"><?= htmlspecialchars($visit['symptoms']) ?></div>
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
                            <div style="margin-top:10px;padding:10px;background:var(--info-bg);border-radius:8px;font-size:0.72rem;color:var(--info);border-left:3px solid var(--info);font-weight:700;">
                                <i class="fas fa-calendar-check"></i>
                                Follow-up: <?= date('d M Y', strtotime($visit['follow_up_date'])) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- 6. MEDICATIONS -->
                        <?php if (count($visit_prescriptions) > 0): ?>
                        <div class="visit-section">
                            <div class="visit-section-title">
                                <i class="fas fa-pills"></i>
                                6. Medications
                                <span class="visit-section-count"><?= count($visit_prescriptions) ?> Rx</span>
                            </div>
                            
                            <?php foreach ($visit_prescriptions as $pr): 
                                $pr_items = $prescription_items_by_prescription[$pr['id']] ?? [];
                            ?>
                                <div style="background:var(--bg-body);border-radius:10px;padding:12px;margin-bottom:10px;border:1px solid var(--border-color);">
                                    
                                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px;padding-bottom:10px;border-bottom:1px dashed var(--border-color);">
                                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                            <span style="font-family:var(--font-mono);font-weight:800;color:var(--purple);font-size:0.72rem;background:var(--purple-bg);padding:3px 8px;border-radius:6px;">
                                                <i class="fas fa-prescription"></i>
                                                <?= htmlspecialchars($pr['prescription_number'] ?? 'N/A') ?>
                                            </span>
                                            <span style="font-size:0.68rem;color:var(--text-secondary);">
                                                <i class="fas fa-user-md"></i> <?= htmlspecialchars($pr['doctor_name'] ?? 'N/A') ?>
                                            </span>
                                        </div>
                                        <span class="status-badge <?= getStatusClass($pr['status']) ?>">
                                            <?= htmlspecialchars(ucfirst($pr['status'] ?? 'N/A')) ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (count($pr_items) > 0): ?>
                                        <table class="visit-table">
                                            <thead>
                                                <tr>
                                                    <th>Medication</th>
                                                    <th>Dosage</th>
                                                    <th>Frequency</th>
                                                    <th style="text-align:center;">Qty</th>
                                                    <th>Instructions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pr_items as $item): ?>
                                                    <tr>
                                                        <td style="font-weight:700;font-size:0.7rem;"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                                        <td style="font-size:0.68rem;"><?= htmlspecialchars($item['dosage'] ?? '-') ?></td>
                                                        <td style="font-size:0.68rem;"><?= htmlspecialchars($item['frequency'] ?? '-') ?></td>
                                                        <td style="text-align:center;font-family:var(--font-mono);font-weight:800;color:var(--purple);">
                                                            <?= number_format($item['quantity'] ?? 0) ?>
                                                        </td>
                                                        <td style="font-size:0.68rem;color:var(--text-secondary);">
                                                            <?= htmlspecialchars($item['instructions'] ?? $item['pharmacy_instructions'] ?? '-') ?>
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
                        
                        <!-- 7. PROCEDURES -->
                        <?php if (count($visit_procedures) > 0): ?>
                        <div class="visit-section">
                            <div class="visit-section-title">
                                <i class="fas fa-procedures"></i>
                                7. Procedures
                                <span class="visit-section-count"><?= count($visit_procedures) ?> Procedures</span>
                            </div>
                            
                            <table class="visit-table">
                                <thead>
                                    <tr>
                                        <th>Procedure</th>
                                        <th>Category</th>
                                        <th>Doctor</th>
                                        <th style="text-align:right;">Price</th>
                                        <th style="text-align:center;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($visit_procedures as $p): ?>
                                        <tr>
                                            <td style="font-weight:700;"><?= htmlspecialchars($p['procedure_name'] ?? 'N/A') ?></td>
                                            <td style="font-size:0.68rem;color:var(--text-secondary);">
                                                <?= htmlspecialchars($p['procedure_category'] ?? $p['category'] ?? 'N/A') ?>
                                            </td>
                                            <td style="font-size:0.7rem;"><?= htmlspecialchars($p['doctor_name'] ?? 'N/A') ?></td>
                                            <td class="money-cell">
                                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($p['procedure_price'] ?? 0, 0) ?>
                                            </td>
                                            <td style="text-align:center;">
                                                <span class="status-badge <?= getStatusClass($p['status']) ?>">
                                                    <?= htmlspecialchars(ucfirst($p['status'] ?? 'N/A')) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                        <!-- 8. BILLS -->
                        <?php if (count($visit_bills) > 0): ?>
                        <div class="visit-section">
                            <div class="visit-section-title">
                                <i class="fas fa-file-invoice-dollar"></i>
                                8. Bills & Payments
                                <span class="visit-section-count"><?= count($visit_bills) ?> Bill(s)</span>
                            </div>
                            
                            <?php foreach ($visit_bills as $b): 
                                $bill_items = $bill_items_by_bill[$b['id']] ?? [];
                            ?>
                                <div style="background:var(--bg-body);border-radius:10px;padding:12px;margin-bottom:10px;border:1px solid var(--border-color);">
                                    
                                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px;padding-bottom:10px;border-bottom:1px dashed var(--border-color);">
                                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                            <span style="font-family:var(--font-mono);font-weight:800;color:var(--primary);font-size:0.72rem;background:var(--primary-bg);padding:3px 8px;border-radius:6px;">
                                                <i class="fas fa-file-invoice"></i>
                                                <?= htmlspecialchars($b['bill_number'] ?? 'N/A') ?>
                                            </span>
                                            <span style="font-size:0.68rem;color:var(--text-secondary);">
                                                <i class="fas fa-user"></i> <?= htmlspecialchars($b['created_by_name'] ?? 'N/A') ?>
                                            </span>
                                        </div>
                                        <span class="status-badge <?= getStatusClass($b['status']) ?>">
                                            <?= htmlspecialchars(ucfirst($b['status'] ?? 'N/A')) ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (count($bill_items) > 0): ?>
                                        <table class="visit-table" style="margin-bottom:10px;">
                                            <thead>
                                                <tr>
                                                    <th>Item</th>
                                                    <th>Type</th>
                                                    <th style="text-align:center;">Qty</th>
                                                    <th style="text-align:right;">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($bill_items as $bi): ?>
                                                    <tr>
                                                        <td style="font-weight:600;font-size:0.7rem;"><?= htmlspecialchars($bi['item_name'] ?? 'N/A') ?></td>
                                                        <td>
                                                            <span style="font-size:0.58rem;font-weight:800;padding:2px 6px;background:var(--primary-bg);color:var(--primary);border-radius:5px;text-transform:uppercase;">
                                                                <?= htmlspecialchars(str_replace('_', ' ', $bi['item_type'] ?? 'other')) ?>
                                                            </span>
                                                        </td>
                                                        <td style="text-align:center;font-family:var(--font-mono);font-weight:800;">
                                                            <?= number_format($bi['quantity'] ?? 0) ?>
                                                        </td>
                                                        <td class="money-cell">
                                                            <span class="currency-prefix"><?= $currency ?></span><?= number_format($bi['total_price'] ?? 0, 0) ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                    
                                    <div style="display:flex;justify-content:flex-end;gap:12px;flex-wrap:wrap;padding-top:10px;border-top:1px dashed var(--border-color);">
                                        <div style="text-align:right;">
                                            <div style="font-size:0.55rem;color:var(--text-secondary);text-transform:uppercase;font-weight:800;margin-bottom:3px;">Subtotal</div>
                                            <div class="money-cell"><span class="currency-prefix"><?= $currency ?></span><?= number_format($b['subtotal'] ?? 0, 0) ?></div>
                                        </div>
                                        
                                        <?php if (($b['pharmacy_discount'] ?? 0) > 0): ?>
                                        <div style="text-align:right;">
                                            <div style="font-size:0.55rem;color:var(--purple);text-transform:uppercase;font-weight:800;margin-bottom:3px;">
                                                <i class="fas fa-prescription"></i> Pharm
                                            </div>
                                            <div class="money-cell purple"><span class="currency-prefix">-<?= $currency ?></span><?= number_format($b['pharmacy_discount'] ?? 0, 0) ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (($b['cashier_discount'] ?? 0) > 0): ?>
                                        <div style="text-align:right;">
                                            <div style="font-size:0.55rem;color:var(--info);text-transform:uppercase;font-weight:800;margin-bottom:3px;">
                                                <i class="fas fa-cash-register"></i> Cashier
                                            </div>
                                            <div class="money-cell info"><span class="currency-prefix">-<?= $currency ?></span><?= number_format($b['cashier_discount'] ?? 0, 0) ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (($b['premium_amount'] ?? 0) > 0): ?>
                                        <div style="text-align:right;">
                                            <div style="font-size:0.55rem;color:var(--warning);text-transform:uppercase;font-weight:800;margin-bottom:3px;">
                                                <i class="fas fa-star"></i> Premium
                                            </div>
                                            <div class="money-cell warning"><span class="currency-prefix">+<?= $currency ?></span><?= number_format($b['premium_amount'] ?? 0, 0) ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div style="text-align:right;padding:0 10px;border-left:2px solid var(--primary);border-right:1px dashed var(--border-color);">
                                            <div style="font-size:0.55rem;color:var(--primary);text-transform:uppercase;font-weight:800;margin-bottom:3px;">
                                                <i class="fas fa-calculator"></i> Total
                                            </div>
                                            <div class="money-cell blue" style="font-size:0.9rem;"><span class="currency-prefix"><?= $currency ?></span><?= number_format($b['total_amount'] ?? 0, 0) ?></div>
                                        </div>
                                        
                                        <div style="text-align:right;">
                                            <div style="font-size:0.55rem;color:var(--success);text-transform:uppercase;font-weight:800;margin-bottom:3px;">
                                                <i class="fas fa-check-circle"></i> Paid
                                            </div>
                                            <div class="money-cell" style="color:var(--success);font-size:0.9rem;"><span class="currency-prefix"><?= $currency ?></span><?= number_format($b['paid_amount'] ?? 0, 0) ?></div>
                                        </div>
                                        
                                        <div style="text-align:right;padding:0 10px;border-left:2px solid <?= ($b['balance'] ?? 0) > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                                            <div style="font-size:0.55rem;color:<?= ($b['balance'] ?? 0) > 0 ? 'var(--danger)' : 'var(--success)' ?>;text-transform:uppercase;font-weight:800;margin-bottom:3px;">
                                                <i class="fas fa-exclamation-circle"></i> Balance
                                            </div>
                                            <div class="money-cell <?= ($b['balance'] ?? 0) > 0 ? 'danger' : '' ?>" style="font-size:0.95rem;font-weight:900;">
                                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($b['balance'] ?? 0, 0) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($b['premium_note'])): ?>
                                        <div style="margin-top:8px;padding:8px;background:var(--warning-bg);border-radius:6px;font-size:0.65rem;color:var(--warning);border-left:3px solid var(--warning);font-weight:600;">
                                            <i class="fas fa-star"></i> <strong>Premium:</strong> <?= htmlspecialchars($b['premium_note']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-visits-state">
            <i class="fas fa-hospital-user"></i>
            <h3>No Visits Recorded</h3>
            <p>This patient has no visit records yet.</p>
        </div>
    <?php endif; ?>

    <!-- APPOINTMENTS -->
    <?php if (count($appointments) > 0): ?>
    <div class="visit-card" style="margin-top:14px;">
        <div class="visit-card-header">
            <div class="visit-header-left">
                <span class="visit-number-badge">
                    <i class="fas fa-calendar-check"></i>
                    Appointments
                </span>
                <span class="visit-date-badge">
                    <i class="fas fa-list"></i>
                    <?= count($appointments) ?> total
                </span>
            </div>
        </div>
        <div class="visit-card-body">
            <table class="visit-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Date & Time</th>
                        <th>Purpose</th>
                        <th>Doctor</th>
                        <th style="text-align:center;">Type</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $apt_num = 1; foreach ($appointments as $a): ?>
                        <tr>
                            <td style="text-align:center;font-family:var(--font-mono);font-weight:800;color:var(--primary);font-size:0.68rem;">
                                <?= $apt_num++ ?>
                            </td>
                            <td>
                                <div style="font-weight:800;font-size:0.72rem;color:var(--primary);font-family:var(--font-mono);">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?= date('d M Y', strtotime($a['appointment_date'] ?? 'now')) ?>
                                </div>
                                <div style="font-size:0.65rem;color:var(--text-secondary);font-family:var(--font-mono);font-weight:700;margin-top:2px;">
                                    <i class="fas fa-clock"></i>
                                    <?= date('h:i A', strtotime($a['appointment_date'] ?? 'now')) ?>
                                </div>
                            </td>
                            <td style="font-size:0.7rem;font-weight:600;"><?= htmlspecialchars($a['purpose'] ?? '-') ?></td>
                            <td style="font-size:0.7rem;">
                                <i class="fas fa-user-md" style="color:var(--primary);font-size:0.68rem;"></i>
                                <?= htmlspecialchars($a['doctor_name'] ?? 'N/A') ?>
                            </td>
                            <td style="text-align:center;">
                                <span style="font-size:0.58rem;font-weight:800;padding:2px 7px;background:var(--primary-bg);color:var(--primary);border-radius:5px;text-transform:uppercase;">
                                    <?= htmlspecialchars($a['visit_type'] ?? 'new') ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <span class="status-badge <?= getStatusClass($a['status']) ?>">
                                    <?= htmlspecialchars(ucfirst($a['status'] ?? 'N/A')) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- REFERRALS -->
    <?php if (count($referrals) > 0): ?>
    <div class="visit-card" style="margin-top:14px;">
        <div class="visit-card-header" style="background: linear-gradient(135deg, #059669, #34D399);">
            <div class="visit-header-left">
                <span class="visit-number-badge"><i class="fas fa-share-square"></i> Referrals</span>
                <span class="visit-date-badge"><?= count($referrals) ?> total</span>
            </div>
        </div>
        <div class="visit-card-body">
            <table class="visit-table">
                <thead>
                    <tr>
                        <th>Referral #</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($referrals as $r): ?>
                        <tr>
                            <td>
                                <span style="font-family:var(--font-mono);font-weight:700;color:var(--primary);font-size:0.65rem;">
                                    <?= htmlspecialchars($r['referral_number'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td style="font-size:0.68rem;font-family:var(--font-mono);"><?= date('d M Y', strtotime($r['referral_date'] ?? 'now')) ?></td>
                            <td>
                                <span style="font-size:0.58rem;font-weight:800;padding:2px 6px;background:var(--primary-bg);color:var(--primary);border-radius:5px;text-transform:uppercase;">
                                    <?= htmlspecialchars($r['referral_type'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td style="font-size:0.7rem;"><?= htmlspecialchars($r['from_doctor_name'] ?? 'N/A') ?></td>
                            <td style="font-size:0.7rem;">
                                <?php if ($r['referral_type'] === 'internal'): ?>
                                    <?= htmlspecialchars($r['to_doctor_name'] ?? 'N/A') ?>
                                <?php else: ?>
                                    <?= htmlspecialchars($r['to_hospital_name'] ?? 'N/A') ?>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="status-badge <?= getStatusClass($r['status']) ?>">
                                    <?= htmlspecialchars(ucfirst($r['status'] ?? 'N/A')) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- SICK SHEETS -->
    <?php if (count($sick_sheets) > 0): ?>
    <div class="visit-card" style="margin-top:14px;">
        <div class="visit-card-header" style="background: linear-gradient(135deg, #D97706, #FBBF24);">
            <div class="visit-header-left">
                <span class="visit-number-badge"><i class="fas fa-file-medical"></i> Sick Sheets</span>
                <span class="visit-date-badge"><?= count($sick_sheets) ?> total</span>
            </div>
        </div>
        <div class="visit-card-body">
            <table class="visit-table">
                <thead>
                    <tr>
                        <th>Document #</th>
                        <th>Date</th>
                        <th>Doctor</th>
                        <th>Diagnosis</th>
                        <th style="text-align:center;">Days</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sick_sheets as $ss): ?>
                        <tr>
                            <td>
                                <span style="font-family:var(--font-mono);font-weight:700;color:var(--primary);font-size:0.65rem;">
                                    <?= htmlspecialchars($ss['document_number'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td style="font-size:0.68rem;font-family:var(--font-mono);"><?= date('d M Y', strtotime($ss['upload_date'] ?? 'now')) ?></td>
                            <td style="font-size:0.7rem;"><?= htmlspecialchars($ss['doctor_name'] ?? 'N/A') ?></td>
                            <td style="font-size:0.68rem;"><?= htmlspecialchars($ss['sick_sheet_diagnosis'] ?? $ss['description'] ?? '-') ?></td>
                            <td style="text-align:center;font-family:var(--font-mono);font-weight:800;color:var(--warning);">
                                <?= number_format($ss['sick_sheet_days'] ?? 0) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</main>

<script>
console.log('%c👤 Patient Details V5 - AUDIT', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Role: <?= $user_role ?>', 'font-size:12px; color:#34D399;');
console.log('%c✅ VIEW ONLY - No edit/delete buttons', 'font-size:12px; color:#34D399;');
console.log('%c✅ Patient: <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:12px; color:#0B5ED7;');
console.log('%c✅ Path: pages/audit/patient_details.php', 'font-size:12px; color:#34D399;');
</script>

</body>
</html>