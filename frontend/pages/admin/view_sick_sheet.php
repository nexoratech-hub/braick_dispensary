<?php
// ================================================================
// FILE: frontend/pages/admin/view_sick_sheet.php
// SUPER ADMIN - VIEW SICK SHEET
// ✅ FIXED: External sick sheets — vital signs from external_sick_sheets
// ✅ FIXED: Internal sick sheets — vital signs from vital_signs table
// ✅ 7 Vital Signs (with Oxygen Saturation - SpO2)
// ✅ BLUE theme + SHARED header & sidebar
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
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
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
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

// ================================================================
// GET PARAMETERS
// ================================================================
$sick_sheet_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sick_sheet_type = isset($_GET['type']) ? trim($_GET['type']) : 'external';
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';

if ($sick_sheet_id <= 0) {
    header('Location: sick_sheets.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

if (!in_array($sick_sheet_type, ['external', 'internal'])) {
    $sick_sheet_type = 'external';
}

// ================================================================
// FETCH SICK SHEET DATA
// ================================================================
$sick_sheet = null;

if ($sick_sheet_type === 'external') {
    // ✅ External sick sheet
    $stmt = $db->prepare("
        SELECT ess.*, 
               u.full_name as doctor_name,
               u.specialty as doctor_specialty,
               u.phone as doctor_phone,
               b.name as branch_name,
               b.location as branch_location,
               b.phone as branch_phone
        FROM external_sick_sheets ess
        LEFT JOIN users u ON ess.doctor_id = u.id
        LEFT JOIN branches b ON ess.branch_id = b.id
        WHERE ess.id = ?
    ");
    $stmt->execute([$sick_sheet_id]);
    $sick_sheet = $stmt->fetch(PDO::FETCH_ASSOC);
    
} else {
    // ✅ Internal sick sheet (from patient_documents)
    $stmt = $db->prepare("
        SELECT pd.*,
               p.full_name as patient_full_name,
               p.patient_id as patient_number,
               p.gender,
               p.phone,
               p.date_of_birth,
               p.address,
               p.blood_group,
               p.allergies,
               u.full_name as doctor_name,
               u.specialty as doctor_specialty,
               u.phone as doctor_phone,
               b.name as branch_name,
               b.location as branch_location,
               b.phone as branch_phone
        FROM patient_documents pd
        LEFT JOIN patients p ON pd.patient_id = p.id
        LEFT JOIN users u ON pd.doctor_id = u.id
        LEFT JOIN branches b ON pd.branch_id = b.id
        WHERE pd.id = ? AND pd.document_type = 'sick_sheet'
    ");
    $stmt->execute([$sick_sheet_id]);
    $sick_sheet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sick_sheet) {
        // Map patient data to sick_sheet fields
        $sick_sheet['full_name'] = $sick_sheet['patient_full_name'] ?? '';
        $sick_sheet['patient_id'] = $sick_sheet['patient_number'] ?? '';
        $sick_sheet['diagnosis'] = $sick_sheet['sick_sheet_diagnosis'] ?? '';
        $sick_sheet['sick_days'] = $sick_sheet['sick_sheet_days'] ?? 0;
        $sick_sheet['sick_from'] = $sick_sheet['sick_sheet_from_date'] ?? '';
        $sick_sheet['sick_to'] = $sick_sheet['sick_sheet_to_date'] ?? '';
        $sick_sheet['instructions'] = $sick_sheet['sick_sheet_recommendations'] ?? '';
        $sick_sheet['sick_restrictions'] = $sick_sheet['sick_sheet_restrictions'] ?? '';
    }
}

if (!$sick_sheet) {
    header('Location: sick_sheets.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
    exit;
}

// ================================================================
// ✅ FETCH VITAL SIGNS
// Kwa EXTERNAL: zipo kwenye external_sick_sheets yenyewe
// Kwa INTERNAL: zinatafutwa kwenye vital_signs table
// ================================================================
$vital_signs = null;

if ($sick_sheet_type === 'external') {
    // ✅ External: Chukua moja kwa moja kutoka external_sick_sheets
    $vital_signs = [
        'temperature' => $sick_sheet['temperature'] ?? null,
        'blood_pressure_systolic' => $sick_sheet['bp_systolic'] ?? null,
        'blood_pressure_diastolic' => $sick_sheet['bp_diastolic'] ?? null,
        'pulse_rate' => $sick_sheet['pulse_rate'] ?? null,
        'oxygen_saturation' => $sick_sheet['oxygen_saturation'] ?? null,
        'weight' => $sick_sheet['weight'] ?? null,
        'height' => $sick_sheet['height'] ?? null,
        'bmi' => $sick_sheet['bmi'] ?? null,
        'recorded_at' => $sick_sheet['updated_at'] ?? $sick_sheet['created_at'] ?? null,
        'recorded_by_name' => $sick_sheet['doctor_name'] ?? null,
        'notes' => null
    ];
    
    // Kama hakuna vital signs zote, weka null
    $has_any = $vital_signs['temperature'] !== null 
        || $vital_signs['blood_pressure_systolic'] !== null 
        || $vital_signs['pulse_rate'] !== null 
        || $vital_signs['oxygen_saturation'] !== null
        || $vital_signs['weight'] !== null
        || $vital_signs['height'] !== null
        || $vital_signs['bmi'] !== null;
    
    if (!$has_any) {
        $vital_signs = null;
    }
    
} else {
    // ✅ Internal: Chukua kutoka vital_signs table
    try {
        if (!empty($sick_sheet['visit_id'])) {
            $stmt = $db->prepare("
                SELECT vs.*, u.full_name as recorded_by_name
                FROM vital_signs vs
                LEFT JOIN users u ON vs.recorded_by = u.id
                WHERE vs.visit_id = ? 
                ORDER BY vs.recorded_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$sick_sheet['visit_id']]);
            $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Fallback: get latest for this patient
        if (!$vital_signs && !empty($sick_sheet['patient_id'])) {
            $stmt = $db->prepare("
                SELECT vs.*, u.full_name as recorded_by_name
                FROM vital_signs vs
                LEFT JOIN users u ON vs.recorded_by = u.id
                WHERE vs.patient_id = ? 
                ORDER BY vs.recorded_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$sick_sheet['patient_id']]);
            $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Vital signs fetch error: " . $e->getMessage());
    }
}

// ================================================================
// HELPER: SpO2 Status
// ================================================================
function getSpO2Status($spo2) {
    if ($spo2 === null || $spo2 === '') {
        return ['label' => 'N/A', 'class' => 'unknown', 'color' => '#64748B'];
    }
    $spo2 = (int)$spo2;
    if ($spo2 >= 95) return ['label' => 'NORMAL', 'class' => 'normal', 'color' => '#059669'];
    if ($spo2 >= 90) return ['label' => 'LOW', 'class' => 'low', 'color' => '#D97706'];
    return ['label' => 'CRITICAL', 'class' => 'critical', 'color' => '#DC2626'];
}

function calculateAge($dob) {
    if (empty($dob) || $dob === '0000-00-00') return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<style>
:root {
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    --primary-bg: #EFF6FF;
    --success: #059669;
    --danger: #DC2626;
    --warning: #D97706;
    --purple: #7C3AED;
    --teal: #0D9488;
    --sky: #0EA5E9;
    --pink: #EC4899;
    --indigo: #4F46E5;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary-bg: #1E3A5F;
}

/* PAGE HEADER */
.page-header-custom {
    background: var(--primary-gradient);
    border-radius: 16px;
    padding: 24px 32px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
    position: relative;
    overflow: hidden;
    color: white;
}

.page-header-custom::before {
    content: '';
    position: absolute;
    top: -60%; right: -10%;
    width: 400px; height: 400px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    pointer-events: none;
}

.page-header-custom .page-title {
    color: white;
    font-size: 1.6rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin: 0;
    position: relative;
    z-index: 1;
}

.page-header-custom .page-title i {
    width: 44px; height: 44px;
    background: rgba(255,255,255,0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.page-header-custom .page-subtitle {
    color: rgba(255,255,255,0.85);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 8px;
    position: relative;
    z-index: 1;
}

.badge-doc {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    font-family: monospace;
}

.badge-type {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid rgba(255,255,255,0.2);
}

.btn-outline-light {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1.5px solid rgba(255,255,255,0.3);
    padding: 9px 20px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    backdrop-filter: blur(10px);
    position: relative;
    z-index: 1;
}

.btn-outline-light:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
    color: white;
}

/* CARDS */
.card {
    background: var(--bg-card);
    border-radius: 16px;
    border: 2px solid var(--border-color);
    overflow: hidden;
    transition: all 0.3s ease;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.card:hover { border-color: var(--primary); }

.card-header {
    padding: 16px 24px;
    background: var(--primary-bg);
    border-bottom: 2px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

[data-theme="dark"] .card-header { background: #0F172A; }

.card-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-title i { color: var(--primary); }

.card-body { padding: 20px 24px; }

/* INFO GRID */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
}

.info-item {
    padding: 12px 14px;
    background: var(--primary-bg);
    border-radius: 10px;
    border-left: 4px solid var(--primary);
}

.info-label {
    font-size: 0.65rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: block;
    margin-bottom: 4px;
}

.info-label i { margin-right: 4px; }

.info-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
}

.info-value.large {
    font-size: 1.1rem;
    color: var(--primary);
}

/* ✅ VITAL SIGNS - 7 CARDS */
.vital-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}

.vital-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 14px 12px;
    text-align: center;
    border: 2px solid var(--border-color);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    min-height: 110px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.vital-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    border-radius: 12px 12px 0 0;
}

.vital-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}

/* VITAL COLORS */
.vital-card.temp-card::before { background: linear-gradient(90deg, #EF4444, #F87171); }
.vital-card.temp-card .vital-icon { color: #EF4444; }
.vital-card.temp-card .vital-value { color: #EF4444; }

.vital-card.bp-card::before { background: linear-gradient(90deg, #0B5ED7, #1A73E8); }
.vital-card.bp-card .vital-icon { color: #0B5ED7; }
.vital-card.bp-card .vital-value { color: #0B5ED7; }

.vital-card.pulse-card::before { background: linear-gradient(90deg, #EC4899, #F472B6); }
.vital-card.pulse-card .vital-icon { color: #EC4899; }
.vital-card.pulse-card .vital-value { color: #EC4899; }

/* ✅ OXYGEN - Sky Blue */
.vital-card.oxygen-card::before { background: linear-gradient(90deg, #0EA5E9, #38BDF8); }
.vital-card.oxygen-card .vital-icon { color: #0284C7; }
.vital-card.oxygen-card .vital-value { color: #0284C7; }
.vital-card.oxygen-card {
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.05), rgba(14, 165, 233, 0.12));
    border-color: #0EA5E9;
}
.vital-card.oxygen-card:hover {
    border-color: #0284C7;
    box-shadow: 0 6px 20px rgba(14, 165, 233, 0.25);
}

.vital-card.weight-card::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }
.vital-card.weight-card .vital-icon { color: #7C3AED; }
.vital-card.weight-card .vital-value { color: #7C3AED; }

.vital-card.height-card::before { background: linear-gradient(90deg, #059669, #10B981); }
.vital-card.height-card .vital-icon { color: #059669; }
.vital-card.height-card .vital-value { color: #059669; }

.vital-card.bmi-card::before { background: linear-gradient(90deg, #D97706, #F59E0B); }
.vital-card.bmi-card .vital-icon { color: #D97706; }
.vital-card.bmi-card .vital-value { color: #D97706; }

.vital-card .vital-icon {
    font-size: 1.5rem;
    margin-bottom: 4px;
    display: block;
}

.vital-card .vital-value {
    font-size: 1.3rem;
    font-weight: 700;
    line-height: 1.2;
    display: block;
}

.vital-card .vital-unit {
    font-size: 0.7rem;
    color: var(--text-secondary);
    font-weight: 400;
}

.vital-card .vital-label {
    font-size: 0.6rem;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-top: 6px;
    display: block;
}

/* SpO2 Status Badge */
.spo2-status-badge {
    display: inline-block;
    font-size: 0.55rem;
    font-weight: 700;
    padding: 2px 10px;
    border-radius: 8px;
    margin-top: 4px;
    letter-spacing: 0.4px;
}

.spo2-status-badge.normal { background: #D1FAE5; color: #059669; border: 1px solid #6EE7B7; }
.spo2-status-badge.low { background: #FEF3C7; color: #D97706; border: 1px solid #FCD34D; }
.spo2-status-badge.critical { background: #FEE2E2; color: #DC2626; border: 1px solid #FCA5A5; }
.spo2-status-badge.unknown { background: #E2E8F0; color: #64748B; }

/* Vital footer */
.vital-footer {
    margin-top: 16px;
    padding: 10px 16px;
    background: linear-gradient(135deg, #F0F9FF, #E0F2FE);
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    font-size: 0.75rem;
    border: 1px dashed #0EA5E9;
}

[data-theme="dark"] .vital-footer { background: #0C2A3A; border-color: #0EA5E9; }

/* INFO BOX */
.info-box {
    padding: 14px 18px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.info-box.blue { background: #E8F0FE; color: #0B5ED7; border: 2px solid #BFDBFE; }
.info-box.green { background: #D1FAE5; color: #065F46; border: 2px solid #6EE7B7; }
.info-box.orange { background: #FEF3C7; color: #92400E; border: 2px solid #FCD34D; }
.info-box.red { background: #FEE2E2; color: #991B1B; border: 2px solid #FCA5A5; }

[data-theme="dark"] .info-box.blue { background: #1E3A5F; color: #6EA8FE; border-color: #1E3A5F; }
[data-theme="dark"] .info-box.green { background: #1A3A2A; color: #34D399; border-color: #34D399; }
[data-theme="dark"] .info-box.orange { background: #3D2E0A; color: #FBBF24; border-color: #FBBF24; }
[data-theme="dark"] .info-box.red { background: #3A1A1A; color: #F87171; border-color: #F87171; }

.info-box i { font-size: 1.4rem; flex-shrink: 0; }

/* DETAIL ROWS */
.detail-row {
    display: flex;
    padding: 10px 0;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.85rem;
}

.detail-row:last-child { border-bottom: none; }

.detail-label {
    font-weight: 600;
    color: var(--text-secondary);
    width: 200px;
    flex-shrink: 0;
    font-size: 0.78rem;
}

.detail-value {
    flex: 1;
    color: var(--text-primary);
    font-weight: 500;
    word-break: break-word;
}

/* STATUS BADGE */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-badge.active { background: #D1FAE5; color: #059669; }
.status-badge.inactive { background: #FEE2E2; color: #DC2626; }

/* BUTTONS */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
    text-decoration: none;
    font-family: inherit;
    min-height: 44px;
}

.btn-primary {
    background: var(--primary-gradient);
    color: white;
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0A4CA8, #1557B0);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(11, 94, 215, 0.4);
    color: white;
}

.btn-outline {
    background: transparent;
    color: var(--text-secondary);
    border: 2px solid var(--border-color);
}

.btn-outline:hover {
    border-color: #0B5ED7;
    color: #0B5ED7;
    background: rgba(11, 94, 215, 0.05);
}

.btn-danger {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    color: white;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}

.btn-danger:hover {
    background: linear-gradient(135deg, #B91C1C, #991B1B);
    transform: translateY(-2px);
    color: white;
}

.btn-success {
    background: linear-gradient(135deg, #059669, #047857);
    color: white;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
}

.btn-success:hover {
    background: linear-gradient(135deg, #047857, #065F46);
    transform: translateY(-2px);
    color: white;
}

.action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding-top: 20px;
    margin-top: 20px;
    border-top: 2px solid var(--border-color);
}

/* FOOTER */
.footer {
    padding: 14px 0;
    border-top: 1px solid var(--border-color);
    margin-top: 24px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.footer .footer-brand { color: #0B5ED7; font-weight: 600; }

/* RESPONSIVE */
@media (max-width: 992px) {
    .vital-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 768px) {
    .page-header-custom { padding: 16px 18px; }
    .page-header-custom .page-title { font-size: 1.2rem; }
    .vital-grid { grid-template-columns: repeat(2, 1fr); }
    .detail-row { flex-direction: column; }
    .detail-label { width: 100%; margin-bottom: 4px; }
    .action-buttons { flex-direction: column; }
    .action-buttons .btn { width: 100%; }
}

@media print {
    .top-nav, .sidebar, .action-buttons, .btn-outline-light,
    .footer, #sidebarToggle { display: none !important; }
    .main-content { margin: 0; padding: 20px; }
    .page-header-custom { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .vital-card, .card { break-inside: avoid; }
    .vital-card.oxygen-card { background: #E0F2FE !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-medical-alt"></i>
                Sick Sheet Details
                <span class="badge-type">
                    <i class="fas <?= $sick_sheet_type === 'external' ? 'fa-globe-africa' : 'fa-user-check' ?>"></i>
                    <?= $sick_sheet_type === 'external' ? 'External' : 'Internal' ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hashtag"></i>
                <span class="badge-doc"><?= htmlspecialchars($sick_sheet['document_number'] ?? 'N/A') ?></span>
                <span><i class="fas fa-user"></i> <?= htmlspecialchars($sick_sheet['full_name'] ?? 'N/A') ?></span>
                <span><i class="fas fa-store-alt"></i> <?= htmlspecialchars($sick_sheet['branch_name'] ?? 'N/A') ?></span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="edit_sick_sheet.php?id=<?= $sick_sheet_id ?>&type=<?= $sick_sheet_type ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="print_sick_sheet.php?id=<?= $sick_sheet_id ?>&type=<?= $sick_sheet_type ?>" target="_blank" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </a>
            <a href="sick_sheets.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- INFO BANNER -->
    <div class="info-box <?= $sick_sheet_type === 'external' ? 'blue' : 'green' ?>">
        <i class="fas <?= $sick_sheet_type === 'external' ? 'fa-globe-africa' : 'fa-user-check' ?>"></i>
        <div>
            <strong>
                <?php if ($sick_sheet_type === 'external'): ?>
                    External Patient Sick Sheet
                <?php else: ?>
                    Registered Patient Sick Sheet
                <?php endif; ?>
            </strong>
            <div style="font-size:0.75rem;opacity:0.85;margin-top:2px;">
                <?php if ($sick_sheet_type === 'external'): ?>
                    This sick sheet is for an external patient (not registered in the system).
                <?php else: ?>
                    This sick sheet is stored in the patient's documents.
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- PATIENT INFORMATION -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user-circle"></i> Patient Information
            </h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-user"></i> Full Name</span>
                    <span class="info-value large"><?= htmlspecialchars($sick_sheet['full_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-id-card"></i> Patient ID</span>
                    <span class="info-value" style="font-family:monospace;"><?= htmlspecialchars($sick_sheet['patient_id'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-venus-mars"></i> Gender</span>
                    <span class="info-value"><?= htmlspecialchars($sick_sheet['gender'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-calendar"></i> Date of Birth</span>
                    <span class="info-value">
                        <?= !empty($sick_sheet['date_of_birth']) && $sick_sheet['date_of_birth'] !== '0000-00-00' 
                            ? date('M d, Y', strtotime($sick_sheet['date_of_birth'])) . ' (' . calculateAge($sick_sheet['date_of_birth']) . ' yrs)' 
                            : 'N/A' ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="info-value"><?= htmlspecialchars($sick_sheet['phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-tint"></i> Blood Group</span>
                    <span class="info-value">
                        <?php if (!empty($sick_sheet['blood_group'])): ?>
                            <span style="background:#FEE2E2;color:#DC2626;padding:2px 10px;border-radius:12px;font-size:0.8rem;font-weight:700;">
                                <?= htmlspecialchars($sick_sheet['blood_group']) ?>
                            </span>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-exclamation-triangle"></i> Allergies</span>
                    <span class="info-value" style="color:#DC2626;"><?= htmlspecialchars($sick_sheet['allergies'] ?? 'None') ?></span>
                </div>
                <div class="info-item" style="grid-column:1/-1;">
                    <span class="info-label"><i class="fas fa-map-marker-alt"></i> Address</span>
                    <span class="info-value"><?= htmlspecialchars($sick_sheet['address'] ?? 'N/A') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ✅ VITAL SIGNS - 7 CARDS (FIXED) -->
    <!-- ============================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-heartbeat" style="color:#EC4899;"></i> Vital Signs (7 Signs)
                <span style="font-size:0.7rem;font-weight:400;color:#0284C7;margin-left:8px;">🫁 SpO2 Normal: 95-100%</span>
            </h3>
            <?php if ($vital_signs && !empty($vital_signs['recorded_at'])): ?>
                <span style="font-size:0.7rem;color:var(--text-secondary);">
                    <i class="fas fa-clock"></i> Recorded: <?= date('M d, Y h:i A', strtotime($vital_signs['recorded_at'])) ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            
            <?php if ($vital_signs): 
                $spo2_status = getSpO2Status($vital_signs['oxygen_saturation'] ?? null);
            ?>
                
                <div class="vital-grid">
                    
                    <!-- 1. TEMPERATURE -->
                    <div class="vital-card temp-card">
                        <span class="vital-icon">🌡️</span>
                        <span class="vital-value">
                            <?= !empty($vital_signs['temperature']) ? $vital_signs['temperature'] : '--' ?>
                            <span class="vital-unit">°C</span>
                        </span>
                        <span class="vital-label">Temperature</span>
                    </div>
                    
                    <!-- 2. BLOOD PRESSURE -->
                    <div class="vital-card bp-card">
                        <span class="vital-icon">💓</span>
                        <span class="vital-value">
                            <?php 
                                $sys = $vital_signs['blood_pressure_systolic'] ?? null;
                                $dia = $vital_signs['blood_pressure_diastolic'] ?? null;
                                if ($sys && $dia) {
                                    echo $sys . '/' . $dia;
                                } else {
                                    echo '--';
                                }
                            ?>
                            <span class="vital-unit">mmHg</span>
                        </span>
                        <span class="vital-label">Blood Pressure</span>
                    </div>
                    
                    <!-- 3. PULSE RATE -->
                    <div class="vital-card pulse-card">
                        <span class="vital-icon">💗</span>
                        <span class="vital-value">
                            <?= !empty($vital_signs['pulse_rate']) ? $vital_signs['pulse_rate'] : '--' ?>
                            <span class="vital-unit">bpm</span>
                        </span>
                        <span class="vital-label">Pulse Rate</span>
                    </div>
                    
                    <!-- 4. ✅ OXYGEN SATURATION (SpO2) - 7th VITAL SIGN -->
                    <div class="vital-card oxygen-card">
                        <span class="vital-icon">🫁</span>
                        <span class="vital-value">
                            <?= !empty($vital_signs['oxygen_saturation']) ? $vital_signs['oxygen_saturation'] : '--' ?>
                            <span class="vital-unit">%</span>
                        </span>
                        <span class="vital-label">Oxygen (SpO2)</span>
                        <?php if (!empty($vital_signs['oxygen_saturation'])): ?>
                            <span class="spo2-status-badge <?= $spo2_status['class'] ?>"><?= $spo2_status['label'] ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- 5. WEIGHT -->
                    <div class="vital-card weight-card">
                        <span class="vital-icon">⚖️</span>
                        <span class="vital-value">
                            <?= !empty($vital_signs['weight']) ? $vital_signs['weight'] : '--' ?>
                            <span class="vital-unit">kg</span>
                        </span>
                        <span class="vital-label">Weight</span>
                    </div>
                    
                    <!-- 6. HEIGHT -->
                    <div class="vital-card height-card">
                        <span class="vital-icon">📏</span>
                        <span class="vital-value">
                            <?= !empty($vital_signs['height']) ? $vital_signs['height'] : '--' ?>
                            <span class="vital-unit">cm</span>
                        </span>
                        <span class="vital-label">Height</span>
                    </div>
                    
                    <!-- 7. BMI -->
                    <div class="vital-card bmi-card">
                        <span class="vital-icon">📊</span>
                        <span class="vital-value">
                            <?= !empty($vital_signs['bmi']) ? $vital_signs['bmi'] : '--' ?>
                        </span>
                        <span class="vital-label">BMI (kg/m²)</span>
                    </div>
                    
                    <!-- Empty slot for alignment -->
                    <div style="visibility:hidden;"></div>
                    
                </div>
                
                <!-- Vital footer -->
                <div class="vital-footer">
                    <i class="fas fa-lungs" style="color:#0EA5E9;"></i>
                    <span style="color:#0284C7;">SpO2 (Oxygen Saturation) Normal Range: <strong>95-100%</strong></span>
                    <span style="color:var(--text-secondary);"> • 7 Vital Signs Tracked</span>
                    <?php if (!empty($vital_signs['recorded_by_name'])): ?>
                        <span style="color:var(--text-secondary);margin-left:auto;">
                            <i class="fas fa-user"></i> Recorded by: <?= htmlspecialchars($vital_signs['recorded_by_name']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($vital_signs['notes'])): ?>
                    <div style="margin-top:12px;padding:12px 16px;background:var(--bg-body);border-radius:10px;border-left:4px solid var(--primary);">
                        <p style="font-size:0.7rem;color:var(--text-secondary);margin:0 0 4px 0;font-weight:600;">📝 NOTES</p>
                        <p style="font-size:0.85rem;margin:0;"><?= nl2br(htmlspecialchars($vital_signs['notes'])) ?></p>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div style="text-align:center;padding:40px 20px;color:var(--text-secondary);">
                    <i class="fas fa-heartbeat" style="font-size:3rem;color:var(--border-color);display:block;margin-bottom:12px;"></i>
                    <p style="font-size:1rem;font-weight:500;">No Vital Signs Recorded</p>
                    <p style="font-size:0.85rem;margin-top:4px;">Vital signs were not recorded for this patient</p>
                </div>
            <?php endif; ?>
            
        </div>
    </div>

    <!-- CLINICAL INFORMATION -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-stethoscope" style="color:#7C3AED;"></i> Clinical Information
            </h3>
        </div>
        <div class="card-body">
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-notes-medical"></i> Symptoms</span>
                <span class="detail-value"><?= !empty($sick_sheet['symptoms']) ? nl2br(htmlspecialchars($sick_sheet['symptoms'])) : 'None reported' ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-diagnoses"></i> Diagnosis</span>
                <span class="detail-value" style="font-weight:700;color:#7C3AED;">
                    <?= !empty($sick_sheet['diagnosis']) ? nl2br(htmlspecialchars($sick_sheet['diagnosis'])) : 'Not specified' ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-pills"></i> Treatment</span>
                <span class="detail-value"><?= !empty($sick_sheet['treatment']) ? nl2br(htmlspecialchars($sick_sheet['treatment'])) : 'Not specified' ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-prescription-bottle"></i> Instructions</span>
                <span class="detail-value"><?= !empty($sick_sheet['instructions']) ? nl2br(htmlspecialchars($sick_sheet['instructions'])) : 'Not specified' ?></span>
            </div>
        </div>
    </div>

    <!-- LAB & MEDICATIONS -->
    <?php if (!empty($sick_sheet['lab_results']) || !empty($sick_sheet['medications']) || !empty($sick_sheet['procedures'])): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-flask" style="color:#0D9488;"></i> Lab & Medications
            </h3>
        </div>
        <div class="card-body">
            <?php if (!empty($sick_sheet['lab_results'])): ?>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-flask"></i> Lab Results</span>
                    <span class="detail-value"><?= nl2br(htmlspecialchars($sick_sheet['lab_results'])) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($sick_sheet['medications'])): ?>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-pills"></i> Medications</span>
                    <span class="detail-value"><?= nl2br(htmlspecialchars($sick_sheet['medications'])) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($sick_sheet['procedures'])): ?>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-syringe"></i> Procedures</span>
                    <span class="detail-value"><?= nl2br(htmlspecialchars($sick_sheet['procedures'])) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- SICK LEAVE DETAILS -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-calendar-check" style="color:#D97706;"></i> Sick Leave Details
            </h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item" style="border-left-color:#D97706;background:#FEF3C7;">
                    <span class="info-label" style="color:#92400E;"><i class="fas fa-calendar-day"></i> Sick Days</span>
                    <span class="info-value large" style="color:#92400E;">
                        <?= htmlspecialchars($sick_sheet['sick_days'] ?? 0) ?> days
                    </span>
                </div>
                <div class="info-item" style="border-left-color:#D97706;">
                    <span class="info-label"><i class="fas fa-calendar-alt"></i> From</span>
                    <span class="info-value">
                        <?= !empty($sick_sheet['sick_from']) ? date('M d, Y', strtotime($sick_sheet['sick_from'])) : 'N/A' ?>
                    </span>
                </div>
                <div class="info-item" style="border-left-color:#D97706;">
                    <span class="info-label"><i class="fas fa-calendar-alt"></i> To</span>
                    <span class="info-value">
                        <?= !empty($sick_sheet['sick_to']) ? date('M d, Y', strtotime($sick_sheet['sick_to'])) : 'N/A' ?>
                    </span>
                </div>
            </div>
            
            <?php if (!empty($sick_sheet['sick_reason'])): ?>
                <div class="detail-row" style="margin-top:14px;">
                    <span class="detail-label"><i class="fas fa-comment-medical"></i> Reason</span>
                    <span class="detail-value"><?= nl2br(htmlspecialchars($sick_sheet['sick_reason'])) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($sick_sheet['sick_restrictions'])): ?>
                <div class="info-box orange" style="margin-top:14px;margin-bottom:0;">
                    <i class="fas fa-ban"></i>
                    <div>
                        <strong>⚠️ Restrictions</strong>
                        <div style="font-size:0.85rem;margin-top:2px;"><?= nl2br(htmlspecialchars($sick_sheet['sick_restrictions'])) ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- DOCTOR & ISSUE INFORMATION -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user-md" style="color:#0B5ED7;"></i> Issued By
            </h3>
            <?php if (!empty($sick_sheet['status'])): ?>
                <span class="status-badge <?= $sick_sheet['status'] === 'active' ? 'active' : 'inactive' ?>">
                    <i class="fas <?= $sick_sheet['status'] === 'active' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                    <?= ucfirst($sick_sheet['status']) ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-user-md"></i> Doctor</span>
                    <span class="info-value"><?= htmlspecialchars($sick_sheet['doctor_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-stethoscope"></i> Specialty</span>
                    <span class="info-value"><?= htmlspecialchars($sick_sheet['doctor_specialty'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-phone"></i> Doctor Phone</span>
                    <span class="info-value"><?= htmlspecialchars($sick_sheet['doctor_phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-store-alt"></i> Branch</span>
                    <span class="info-value"><?= htmlspecialchars($sick_sheet['branch_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-calendar-plus"></i> Issued On</span>
                    <span class="info-value">
                        <?php 
                            $issued_date = $sick_sheet['created_at'] ?? $sick_sheet['upload_date'] ?? 'now';
                            echo date('M d, Y h:i A', strtotime($issued_date));
                        ?>
                    </span>
                </div>
                <?php if (!empty($sick_sheet['updated_at'])): ?>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-sync-alt"></i> Last Updated</span>
                    <span class="info-value"><?= date('M d, Y h:i A', strtotime($sick_sheet['updated_at'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ACTION BUTTONS -->
    <div class="card">
        <div class="card-body">
            <div class="action-buttons">
                <a href="edit_sick_sheet.php?id=<?= $sick_sheet_id ?>&type=<?= $sick_sheet_type ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Sick Sheet
                </a>
                <a href="print_sick_sheet.php?id=<?= $sick_sheet_id ?>&type=<?= $sick_sheet_type ?>" target="_blank" class="btn btn-success">
                    <i class="fas fa-print"></i> Print / PDF
                </a>
                <a href="sick_sheets.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-list"></i> All Sick Sheets
                </a>
                <a href="delete_sick_sheet.php?id=<?= $sick_sheet_id ?>&type=<?= $sick_sheet_type ?>&branch=<?= $selected_branch_id ?>" 
                   class="btn btn-danger"
                   onclick="return confirm('⚠️ Delete this sick sheet?\n\nDocument #: <?= htmlspecialchars(addslashes($sick_sheet['document_number'] ?? 'N/A')) ?>\nPatient: <?= htmlspecialchars(addslashes($sick_sheet['full_name'] ?? 'N/A')) ?>\n\nThis action CANNOT be undone!');">
                    <i class="fas fa-trash"></i> Delete
                </a>
            </div>
        </div>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span>
            Sick Sheet Details - <?= htmlspecialchars($sick_sheet['document_number'] ?? 'N/A') ?>
            <span>|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
// ================================================================
// FOOTER TIME
// ================================================================
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

console.log('%c👁️ Admin - View Sick Sheet', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ 7 Vital Signs: Temp, BP, Pulse, SpO2, Weight, Height, BMI', 'font-size:13px;color:#EC4899;');
console.log('%c🫁 Oxygen Saturation (SpO2) - 7th Vital Sign', 'font-size:13px;color:#0EA5E9;');
console.log('%c📋 Type: <?= $sick_sheet_type ?>', 'font-size:13px;color:#059669;');
console.log('%c👤 Patient: <?= htmlspecialchars($sick_sheet['full_name'] ?? 'N/A') ?>', 'font-size:13px;color:#0B5ED7;');
</script>

</body>
</html>