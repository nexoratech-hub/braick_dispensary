<?php
// ================================================================
// FILE: frontend/pages/doctor/patient_details.php
// DOCTOR - PATIENT DETAILS WITH VITAL SIGNS & PDF EXPORT
// FIXED: Session-based login (NO BYPASS)
// FIXED: Uses shared header and sidebar with dark mode
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT DOCTOR
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// GET DOCTOR DATA FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. Unknown';
$selected_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// VERIFY DOCTOR EXISTS AND IS ACTIVE
// ================================================================
try {
    $stmt = $db->prepare("SELECT id, full_name, branch_id, profile_pic, status, is_online FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor_data || $doctor_data['status'] !== 'active') {
        session_destroy();
        header('Location: /dispensary_system/frontend/pages/login.php');
        exit;
    }
    
    $doctor_name = $doctor_data['full_name'];
    $profile_pic = $doctor_data['profile_pic'] ?? '';
    $is_online = $doctor_data['is_online'] ?? 0;
    $selected_branch_id = $doctor_data['branch_id'] ?? 1;
    
    $_SESSION['full_name'] = $doctor_name;
    $_SESSION['profile_pic'] = $profile_pic;
    $_SESSION['is_online'] = $is_online;
    $_SESSION['branch_id'] = $selected_branch_id;
    
} catch (Exception $e) {
    error_log("patient_details verification error: " . $e->getMessage());
    $profile_pic = '';
    $is_online = 0;
}

// ================================================================
// VARIABLES
// ================================================================
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id <= 0) {
    header('Location: my_patients.php');
    exit;
}

// ================================================================
// GET PATIENT DATA - Verify doctor has access
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT p.*, b.name as branch_name, u.full_name as assigned_doctor_name
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN users u ON p.assigned_doctor_id = u.id
        WHERE p.id = ? AND p.assigned_doctor_id = ?
    ");
    $stmt->execute([$patient_id, $doctor_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patient = null;
}

if (!$patient) {
    header('Location: my_patients.php');
    exit;
}

// ================================================================
// GET STATISTICS
// ================================================================

// Total Visits
$stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Bills
$stmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(total_amount), 0) as total_amount FROM patient_bills WHERE patient_id = ? AND status != 'cancelled'");
$stmt->execute([$patient_id]);
$bills_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_bills = $bills_data['total'] ?? 0;
$total_bill_amount = $bills_data['total_amount'] ?? 0;

// Total Prescriptions
$stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Lab Tests
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM lab_tests lt
    INNER JOIN visits v ON lt.visit_id = v.id
    WHERE v.patient_id = ?
");
$stmt->execute([$patient_id]);
$total_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Appointments
$stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Payments
$stmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(amount), 0) as total_amount FROM payments WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$payments_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_payments = $payments_data['total'] ?? 0;
$total_payments_amount = $payments_data['total_amount'] ?? 0;

// ================================================================
// GET VITAL SIGNS HISTORY
// ================================================================
$stmt = $db->prepare("
    SELECT vs.*, 
           u.full_name as recorded_by_name,
           v.visit_number,
           v.visit_date
    FROM vital_signs vs
    LEFT JOIN users u ON vs.recorded_by = u.id
    LEFT JOIN visits v ON vs.visit_id = v.id
    WHERE vs.patient_id = ?
    ORDER BY vs.recorded_at DESC
    LIMIT 20
");
$stmt->execute([$patient_id]);
$vital_signs_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total Vital Signs Records
$total_vital_signs = count($vital_signs_history);

// Get latest vital signs
$latest_vital_signs = !empty($vital_signs_history) ? $vital_signs_history[0] : null;

// ================================================================
// GET RECENT VISITS
// ================================================================
$stmt = $db->prepare("
    SELECT v.*, u.full_name as doctor_name, 
           CASE 
               WHEN v.status = 'pending' THEN 'warning'
               WHEN v.status = 'completed' THEN 'success'
               WHEN v.status = 'cancelled' THEN 'danger'
               ELSE 'info'
           END as status_color
    FROM visits v
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE v.patient_id = ?
    ORDER BY v.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$recent_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT BILLS
// ================================================================
$stmt = $db->prepare("
    SELECT pb.*, 
           CASE 
               WHEN pb.status = 'pending' THEN 'warning'
               WHEN pb.status = 'paid' THEN 'success'
               WHEN pb.status = 'partial' THEN 'info'
               WHEN pb.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM patient_bills pb
    WHERE pb.patient_id = ?
    ORDER BY pb.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$recent_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT PRESCRIPTIONS
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, u.full_name as doctor_name
    FROM prescriptions p
    LEFT JOIN users u ON p.doctor_id = u.id
    WHERE p.patient_id = ?
    ORDER BY p.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$recent_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT LAB TESTS
// ================================================================
$stmt = $db->prepare("
    SELECT lt.*, 
           CASE 
               WHEN lt.status = 'pending' THEN 'warning'
               WHEN lt.status = 'in_progress' THEN 'info'
               WHEN lt.status = 'completed' THEN 'success'
               WHEN lt.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color,
           u.full_name as doctor_name
    FROM lab_tests lt
    INNER JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    WHERE v.patient_id = ?
    ORDER BY lt.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$recent_lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT APPOINTMENTS
// ================================================================
$stmt = $db->prepare("
    SELECT a.*, u.full_name as doctor_name,
           CASE 
               WHEN a.status = 'scheduled' THEN 'warning'
               WHEN a.status = 'confirmed' THEN 'info'
               WHEN a.status = 'completed' THEN 'success'
               WHEN a.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM appointments a
    LEFT JOIN users u ON a.doctor_id = u.id
    WHERE a.patient_id = ?
    ORDER BY a.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_header.php';
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_sidebar.php';
?>

<!-- ================================================================ -->
<!-- FULL CSS WITH DARK MODE SUPPORT (SAME AS ORIGINAL) -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       ROOT VARIABLES - LIGHT & DARK MODE
       ================================================================ */
    :root {
        --primary: #0B5ED7;
        --primary-dark: #0A4CA8;
        --primary-light: #6EA8FE;
        --primary-bg: #E8F0FE;
        --success: #059669;
        --success-dark: #047857;
        --success-light: #34D399;
        --success-bg: #D1FAE5;
        --danger: #DC2626;
        --danger-dark: #B91C1C;
        --danger-light: #F87171;
        --danger-bg: #FEE2E2;
        --warning: #D97706;
        --warning-bg: #FEF3C7;
        --purple: #7C3AED;
        --purple-bg: #EDE9FE;
        --white: #FFFFFF;
        --gray-50: #F8FAFC;
        --gray-100: #F1F5F9;
        --gray-200: #E2E8F0;
        --gray-300: #CBD5E1;
        --gray-400: #94A3B8;
        --gray-500: #64748B;
        --gray-600: #475569;
        --gray-700: #334155;
        --gray-800: #1E293B;
        --gray-900: #0F172A;
        --bg-body: #F1F5F9;
        --bg-card: #FFFFFF;
        --bg-nav: #FFFFFF;
        --text-primary: #1E293B;
        --text-secondary: #64748B;
        --border-color: #E2E8F0;
        --shadow: 0 1px 3px rgba(0,0,0,0.08);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.07);
        --shadow-lg: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    [data-theme="dark"] {
        --bg-body: #0F172A;
        --bg-card: #1E293B;
        --bg-nav: #1E293B;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --border-color: #334155;
        --shadow: 0 1px 3px rgba(0,0,0,0.3);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --shadow-lg: 0 8px 25px rgba(0,0,0,0.4);
    }
    
    /* ================================================================
       BASE STYLES
       ================================================================ */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
        font-family: 'Inter', 'Segoe UI', sans-serif;
        background: var(--bg-body);
        color: var(--text-primary);
        transition: background 0.3s ease, color 0.3s ease;
    }
    
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: var(--bg-body); }
    ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
    
    /* ================================================================
       MAIN CONTENT
       ================================================================ */
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 24px 28px;
        min-height: calc(100vh - 68px);
        transition: all 0.3s ease;
        background: var(--bg-body);
    }
    
    /* ================================================================
       PROFILE HEADER
       ================================================================ */
    .profile-header {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 16px;
        padding: 24px 30px;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .profile-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
    }
    
    .profile-header .profile-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        border: 3px solid rgba(255,255,255,0.3);
        flex-shrink: 0;
    }
    
    .profile-header .profile-name {
        font-size: 1.5rem;
        font-weight: 700;
    }
    
    .profile-header .profile-id {
        font-size: 0.85rem;
        opacity: 0.8;
        font-family: monospace;
    }
    
    .profile-header .profile-badge {
        background: rgba(255,255,255,0.15);
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.1);
    }
    
    /* ================================================================
       STAT CARDS
       ================================================================ */
    .stat-card-mini {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .stat-card-mini:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        border-color: #0B5ED7;
    }
    
    .stat-card-mini .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: #0B5ED7;
    }
    
    .stat-card-mini .stat-number.green { color: #059669; }
    .stat-card-mini .stat-number.orange { color: #F59E0B; }
    .stat-card-mini .stat-number.red { color: #EF4444; }
    .stat-card-mini .stat-number.purple { color: #7B2FBE; }
    .stat-card-mini .stat-number.pink { color: #EC4899; }
    
    .stat-card-mini .stat-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .stat-card-mini .stat-icon {
        font-size: 1.5rem;
        margin-bottom: 4px;
    }
    
    .stat-card-mini .stat-amount {
        font-size: 0.8rem;
        font-weight: 600;
        color: #059669;
        margin-top: 2px;
    }
    
    [data-theme="dark"] .stat-card-mini {
        background: #1E293B;
        border-color: #334155;
    }
    
    [data-theme="dark"] .stat-card-mini:hover {
        border-color: #0B5ED7;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-number {
        color: #6EA8FE;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-number.green {
        color: #34D399;
    }
    
    /* ================================================================
       TABLE - BLUE THEME
       ================================================================ */
    .table-blue thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8) !important;
        color: #FFFFFF !important;
        font-weight: 700 !important;
        font-size: 0.65rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        padding: 10px 14px !important;
        border-bottom: 3px solid #0A4CA8 !important;
        white-space: nowrap !important;
        position: sticky;
        top: 0;
        z-index: 5;
    }
    
    .table-blue thead th:first-child {
        border-radius: 8px 0 0 0 !important;
    }
    
    .table-blue thead th:last-child {
        border-radius: 0 8px 0 0 !important;
    }
    
    .table-blue tbody td {
        padding: 8px 14px !important;
        border-bottom: 1px solid #E2E8F0 !important;
        color: #1E293B !important;
        vertical-align: middle !important;
        font-size: 0.82rem;
    }
    
    .table-blue tbody tr:hover td {
        background: #E8F0FE !important;
    }
    
    [data-theme="dark"] .table-blue tbody td {
        color: #F1F5F9 !important;
        border-bottom-color: #334155 !important;
    }
    
    [data-theme="dark"] .table-blue tbody tr:hover td {
        background: #1A3A5F !important;
    }
    
    /* ================================================================
       BADGES
       ================================================================ */
    .badge {
        padding: 3px 12px !important;
        border-radius: 20px !important;
        font-size: 0.6rem !important;
        font-weight: 600 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        border: none !important;
    }
    
    .badge-success { background: #D1FAE5 !important; color: #059669 !important; }
    .badge-warning { background: #FEF3C7 !important; color: #D97706 !important; }
    .badge-danger { background: #FEE2E2 !important; color: #EF4444 !important; }
    .badge-info { background: #E8F0FE !important; color: #0B5ED7 !important; }
    .badge-secondary { background: #E2E8F0 !important; color: #64748B !important; }
    
    [data-theme="dark"] .badge-success { background: #1A3A2A !important; color: #34D399 !important; }
    [data-theme="dark"] .badge-warning { background: #3A2A1A !important; color: #FBBF24 !important; }
    [data-theme="dark"] .badge-danger { background: #3A1A1A !important; color: #F87171 !important; }
    [data-theme="dark"] .badge-info { background: #1E3A5F !important; color: #6EA8FE !important; }
    [data-theme="dark"] .badge-secondary { background: #2D3748 !important; color: #94A3B8 !important; }
    
    /* ================================================================
       INFO ROWS
       ================================================================ */
    .info-row {
        display: flex;
        padding: 8px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .info-row .info-label {
        width: 140px;
        font-weight: 600;
        color: var(--text-secondary);
        font-size: 0.82rem;
        flex-shrink: 0;
    }
    
    .info-row .info-value {
        flex: 1;
        color: var(--text-primary);
        font-size: 0.85rem;
    }
    
    /* ================================================================
       SECTION TITLE
       ================================================================ */
    .section-title {
        font-size: 1rem;
        font-weight: 700;
        color: #0B5ED7;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
    }
    
    [data-theme="dark"] .section-title {
        color: #6EA8FE;
    }
    
    .section-title .badge-count {
        font-size: 0.7rem;
        font-weight: 400;
        color: var(--text-secondary);
    }
    
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        transition: all 0.3s;
    }
    
    .card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.05);
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .card-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    /* ================================================================
       VITAL SIGNS CARDS
       ================================================================ */
    .vital-card {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 16px 12px;
        text-align: center;
        border: 2px solid var(--border-color);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        min-height: 100px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    
    .vital-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        border-radius: 14px 14px 0 0;
    }
    
    .vital-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    }
    
    .vital-card .vital-icon { font-size: 1.8rem; margin-bottom: 6px; }
    .vital-card .vital-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.2;
    }
    .vital-card .vital-label {
        font-size: 0.65rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.04em;
        margin-top: 2px;
    }
    .vital-card .vital-unit {
        font-size: 0.6rem;
        color: var(--text-secondary);
        font-weight: 400;
        margin-left: 2px;
    }
    
    .vital-card.blue::before { background: linear-gradient(90deg, #0B5ED7, #1A73E8); }
    .vital-card.blue .vital-icon { color: #0B5ED7; }
    .vital-card.blue .vital-value { color: #0B5ED7; }
    
    .vital-card.red::before { background: linear-gradient(90deg, #EF4444, #F87171); }
    .vital-card.red .vital-icon { color: #EF4444; }
    .vital-card.red .vital-value { color: #EF4444; }
    
    .vital-card.pink::before { background: linear-gradient(90deg, #EC4899, #F472B6); }
    .vital-card.pink .vital-icon { color: #EC4899; }
    .vital-card.pink .vital-value { color: #EC4899; }
    
    .vital-card.purple::before { background: linear-gradient(90deg, #7B2FBE, #9B4DCA); }
    .vital-card.purple .vital-icon { color: #7B2FBE; }
    .vital-card.purple .vital-value { color: #7B2FBE; }
    
    .vital-card.green::before { background: linear-gradient(90deg, #059669, #0AA84F); }
    .vital-card.green .vital-icon { color: #059669; }
    .vital-card.green .vital-value { color: #059669; }
    
    .vital-card.indigo::before { background: linear-gradient(90deg, #4F46E5, #818CF8); }
    .vital-card.indigo .vital-icon { color: #4F46E5; }
    .vital-card.indigo .vital-value { color: #4F46E5; }
    
    [data-theme="dark"] .vital-card {
        background: #1E293B;
        border-color: #334155;
    }
    
    [data-theme="dark"] .vital-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 8px 30px rgba(0,0,0,0.3);
    }
    
    [data-theme="dark"] .vital-card .vital-value {
        color: #F1F5F9;
    }
    
    [data-theme="dark"] .vital-card.blue .vital-value { color: #6EA8FE; }
    [data-theme="dark"] .vital-card.red .vital-value { color: #F87171; }
    [data-theme="dark"] .vital-card.pink .vital-value { color: #F472B6; }
    [data-theme="dark"] .vital-card.purple .vital-value { color: #A78BFA; }
    [data-theme="dark"] .vital-card.green .vital-value { color: #34D399; }
    [data-theme="dark"] .vital-card.indigo .vital-value { color: #A5B4FC; }
    
    /* ================================================================
       PDF EXPORT BUTTON
       ================================================================ */
    .btn-pdf {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.82rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        border: none;
        text-decoration: none;
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        color: white;
        box-shadow: 0 4px 14px rgba(220, 38, 38, 0.3);
        position: relative;
        overflow: hidden;
    }
    
    .btn-pdf::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
        pointer-events: none;
    }
    
    .btn-pdf:hover {
        background: linear-gradient(135deg, #B91C1C, #991B1B);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
    }
    
    .btn-pdf:active {
        transform: translateY(0px);
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
    }
    
    .btn-pdf i { font-size: 1rem; }
    .btn-pdf .pdf-icon {
        background: rgba(255,255,255,0.2);
        padding: 4px 6px;
        border-radius: 6px;
        font-size: 0.7rem;
    }
    
    [data-theme="dark"] .btn-pdf {
        background: linear-gradient(135deg, #DC2626, #991B1B);
        box-shadow: 0 4px 14px rgba(220, 38, 38, 0.4);
    }
    
    [data-theme="dark"] .btn-pdf:hover {
        background: linear-gradient(135deg, #B91C1C, #7F1D1D);
        box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5);
    }
    
    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        background: transparent;
    }
    
    .btn-primary {
        background: var(--primary);
        color: white;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
    }
    
    .btn-sm {
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    
    /* ================================================================
       FOOTER
       ================================================================ */
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
    .text-gray-300 { color: #D1D5DB; }
    .mx-2 { margin-left: 0.5rem; margin-right: 0.5rem; }
    
    /* ================================================================
       TOAST
       ================================================================ */
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 12px 18px;
        border-radius: 12px;
        z-index: 999;
        max-width: 360px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    .toast-custom.show { transform: translateY(0); opacity: 1; }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #EF4444; }
    .toast-custom.info { background: #0B5ED7; }
    .toast-custom.warning { background: #D97706; }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .main-content { padding: 16px; }
    }
    
    @media (max-width: 768px) {
        .main-content { padding: 12px; }
        .profile-header { padding: 16px 18px; }
        .profile-header .profile-avatar {
            width: 60px;
            height: 60px;
            font-size: 1.8rem;
        }
        .profile-header .profile-name { font-size: 1.2rem; }
        .info-row { flex-direction: column; gap: 2px; }
        .info-row .info-label { width: 100%; font-size: 0.75rem; }
        .stat-card-mini .stat-number { font-size: 1.4rem; }
        .table-blue tbody td { font-size: 0.7rem; padding: 6px 10px !important; }
        .vital-card { min-height: 80px; padding: 12px 8px; }
        .vital-card .vital-value { font-size: 1.2rem; }
        .vital-card .vital-icon { font-size: 1.4rem; }
        .grid-cols-2.sm\:grid-cols-3.md\:grid-cols-6 { grid-template-columns: repeat(2, 1fr); }
        .btn-pdf { padding: 6px 12px; font-size: 0.7rem; }
        .btn-pdf .pdf-text { display: none; }
        .page-header { flex-direction: column; align-items: flex-start; }
        .card-header { flex-direction: column; align-items: flex-start; }
    }
    
    @media (max-width: 480px) {
        .main-content { padding: 8px; }
        .profile-header .profile-avatar {
            width: 50px;
            height: 50px;
            font-size: 1.4rem;
        }
        .profile-header .profile-name { font-size: 1rem; }
        .stat-card-mini .stat-number { font-size: 1.2rem; }
        .stat-card-mini { padding: 10px 12px; }
        .card { padding: 12px 14px; }
        .grid-cols-2.sm\:grid-cols-3.md\:grid-cols-6 { grid-template-columns: repeat(2, 1fr); }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PROFILE HEADER -->
    <!-- ================================================================ -->
    <div class="profile-header mb-5">
        <div class="flex items-center gap-4 flex-wrap" style="position:relative;z-index:1;">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="flex-1">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="profile-name"><?= htmlspecialchars($patient['full_name']) ?></h1>
                    <span class="profile-badge">
                        <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id']) ?>
                    </span>
                    <span class="profile-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.2);">
                        <i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($patient['created_at'])) ?>
                    </span>
                </div>
                <div class="flex items-center gap-3 flex-wrap mt-1" style="opacity:0.85;">
                    <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-store-alt"></i> <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></span>
                    <?php if ($patient['assigned_doctor_name']): ?>
                        <span><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($patient['assigned_doctor_name']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="add_visit.php?patient=<?= $patient['id'] ?>" class="btn btn-primary btn-sm" style="background:rgba(255,255,255,0.2);color:white;border:1px solid rgba(255,255,255,0.2);">
                    <i class="fas fa-stethoscope"></i> New Visit
                </a>
                <a href="my_patients.php" class="btn btn-outline btn-sm" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.15);">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-5">
        
        <div class="stat-card-mini">
            <div class="stat-icon">📋</div>
            <p class="stat-number"><?= $total_visits ?></p>
            <p class="stat-label">Total Visits</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">💰</div>
            <p class="stat-number green"><?= $total_bills ?></p>
            <p class="stat-label">Total Bills</p>
            <p class="stat-amount">TSh <?= number_format($total_bill_amount) ?></p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">💊</div>
            <p class="stat-number purple"><?= $total_prescriptions ?></p>
            <p class="stat-label">Prescriptions</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">🔬</div>
            <p class="stat-number orange"><?= $total_lab_tests ?></p>
            <p class="stat-label">Lab Tests</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">📅</div>
            <p class="stat-number"><?= $total_appointments ?></p>
            <p class="stat-label">Appointments</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">💵</div>
            <p class="stat-number green"><?= $total_payments ?></p>
            <p class="stat-label">Payments</p>
            <p class="stat-amount">TSh <?= number_format($total_payments_amount) ?></p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">❤️</div>
            <p class="stat-number pink"><?= $total_vital_signs ?></p>
            <p class="stat-label">Vital Signs</p>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- LATEST VITAL SIGNS -->
    <!-- ================================================================ -->
    <?php if ($latest_vital_signs): ?>
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-heartbeat mr-2" style="color: #EC4899;"></i> Latest Vital Signs
                <span class="badge-count">(<?= date('M d, Y h:i A', strtotime($latest_vital_signs['recorded_at'])) ?>)</span>
            </h3>
            <a href="add_vital_signs.php?patient=<?= $patient['id'] ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add Vital Signs
            </a>
        </div>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3">
            
            <!-- 1. Temperature -->
            <div class="vital-card blue">
                <div class="vital-icon"><i class="fas fa-thermometer-half"></i></div>
                <div class="vital-value">
                    <?php 
                        $temp = $latest_vital_signs['temperature'] ?? null;
                        echo $temp !== null ? $temp : '-';
                    ?>
                    <span class="vital-unit">°C</span>
                </div>
                <div class="vital-label">Temperature</div>
            </div>
            
            <!-- 2. Blood Pressure -->
            <div class="vital-card red">
                <div class="vital-icon"><i class="fas fa-heart"></i></div>
                <div class="vital-value">
                    <?php 
                        $systolic = $latest_vital_signs['blood_pressure_systolic'] ?? null;
                        $diastolic = $latest_vital_signs['blood_pressure_diastolic'] ?? null;
                        
                        if ($systolic !== null && $diastolic !== null) {
                            echo $systolic . '/' . $diastolic;
                        } elseif ($systolic !== null) {
                            echo $systolic;
                        } else {
                            echo '-';
                        }
                    ?>
                    <span class="vital-unit">mmHg</span>
                </div>
                <div class="vital-label">Blood Pressure</div>
            </div>
            
            <!-- 3. Pulse Rate -->
            <div class="vital-card pink">
                <div class="vital-icon"><i class="fas fa-heartbeat"></i></div>
                <div class="vital-value">
                    <?php 
                        $pulse = $latest_vital_signs['pulse_rate'] ?? null;
                        echo $pulse !== null ? $pulse : '-';
                    ?>
                    <span class="vital-unit">bpm</span>
                </div>
                <div class="vital-label">Pulse Rate</div>
            </div>
            
            <!-- 4. Weight -->
            <div class="vital-card purple">
                <div class="vital-icon"><i class="fas fa-weight"></i></div>
                <div class="vital-value">
                    <?php 
                        $weight = $latest_vital_signs['weight'] ?? null;
                        echo $weight !== null ? $weight : '-';
                    ?>
                    <span class="vital-unit">kg</span>
                </div>
                <div class="vital-label">Weight</div>
            </div>
            
            <!-- 5. Height -->
            <div class="vital-card green">
                <div class="vital-icon"><i class="fas fa-ruler-vertical"></i></div>
                <div class="vital-value">
                    <?php 
                        $height = $latest_vital_signs['height'] ?? null;
                        echo $height !== null ? $height : '-';
                    ?>
                    <span class="vital-unit">cm</span>
                </div>
                <div class="vital-label">Height</div>
            </div>
            
            <!-- 6. BMI -->
            <div class="vital-card indigo">
                <div class="vital-icon"><i class="fas fa-calculator"></i></div>
                <div class="vital-value">
                    <?php 
                        $bmi = $latest_vital_signs['bmi'] ?? null;
                        echo $bmi !== null ? $bmi : '-';
                    ?>
                </div>
                <div class="vital-label">BMI</div>
            </div>
            
        </div>
        
        <?php if ($latest_vital_signs['notes']): ?>
        <div class="mt-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <p class="text-xs text-gray-500">📝 Notes</p>
            <p class="text-sm"><?= htmlspecialchars($latest_vital_signs['notes']) ?></p>
        </div>
        <?php endif; ?>
        
        <p class="text-xs text-gray-400 mt-2">
            <i class="fas fa-user"></i> Recorded by: <?= htmlspecialchars($latest_vital_signs['recorded_by_name'] ?? 'N/A') ?>
            <?php if ($latest_vital_signs['visit_number']): ?>
                <span class="mx-2">|</span>
                <i class="fas fa-stethoscope"></i> Visit: <?= htmlspecialchars($latest_vital_signs['visit_number']) ?>
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PATIENT INFORMATION WITH PDF EXPORT -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-info-circle title-blue mr-2"></i> Patient Information
            </h3>
            <button onclick="exportPDF()" class="btn-pdf">
                <i class="fas fa-file-pdf"></i>
                <span class="pdf-text">Export PDF</span>
                <span class="pdf-icon"><i class="fas fa-download"></i></span>
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <div class="info-row">
                <span class="info-label"><i class="fas fa-user"></i> Full Name</span>
                <span class="info-value"><?= htmlspecialchars($patient['full_name']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-id-card"></i> Patient ID</span>
                <span class="info-value font-mono"><?= htmlspecialchars($patient['patient_id']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-calendar-alt"></i> Date of Birth</span>
                <span class="info-value"><?= $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-venus-mars"></i> Gender</span>
                <span class="info-value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-ring"></i> Marital Status</span>
                <span class="info-value"><?= htmlspecialchars($patient['marital_status'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-tint"></i> Blood Group</span>
                <span class="info-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-phone"></i> Phone</span>
                <span class="info-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                <span class="info-value"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-map-marker-alt"></i> Address</span>
                <span class="info-value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-phone-alt"></i> Emergency Contact</span>
                <span class="info-value"><?= htmlspecialchars($patient['emergency_contact'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-allergies"></i> Allergies</span>
                <span class="info-value"><?= htmlspecialchars($patient['allergies'] ?? 'None') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-store-alt"></i> Branch</span>
                <span class="info-value"><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VITAL SIGNS HISTORY -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-heartbeat mr-2" style="color: #EC4899;"></i> Vital Signs History
                <span class="badge-count">(<?= $total_vital_signs ?> records)</span>
            </h3>
        </div>
        
        <?php if (count($vital_signs_history) > 0): ?>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th style="min-width: 130px;">Date</th>
                        <th style="min-width: 100px;">Visit</th>
                        <th style="min-width: 90px;">Temp (°C)</th>
                        <th style="min-width: 110px;">BP (mmHg)</th>
                        <th style="min-width: 90px;">Pulse (bpm)</th>
                        <th style="min-width: 90px;">Weight (kg)</th>
                        <th style="min-width: 90px;">Height (cm)</th>
                        <th style="min-width: 80px;">BMI</th>
                        <th style="min-width: 120px;">Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($vital_signs_history as $vs): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td class="text-xs"><?= date('M d, Y h:i A', strtotime($vs['recorded_at'])) ?></td>
                            <td>
                                <?php if ($vs['visit_number']): ?>
                                    <a href="visit_details.php?id=<?= $vs['visit_id'] ?>" 
                                       class="text-blue-600 hover:underline text-xs font-mono">
                                        <?= htmlspecialchars($vs['visit_number']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $vs['temperature'] !== null ? $vs['temperature'] : '-' ?></td>
                            <td>
                                <?php 
                                    $systolic = $vs['blood_pressure_systolic'] ?? null;
                                    $diastolic = $vs['blood_pressure_diastolic'] ?? null;
                                    
                                    if ($systolic !== null && $diastolic !== null) {
                                        echo $systolic . '/' . $diastolic;
                                    } elseif ($systolic !== null) {
                                        echo $systolic;
                                    } else {
                                        echo '-';
                                    }
                                ?>
                            </td>
                            <td><?= $vs['pulse_rate'] !== null ? $vs['pulse_rate'] : '-' ?></td>
                            <td><?= $vs['weight'] !== null ? $vs['weight'] : '-' ?></td>
                            <td><?= $vs['height'] !== null ? $vs['height'] : '-' ?></td>
                            <td><?= $vs['bmi'] !== null ? $vs['bmi'] : '-' ?></td>
                            <td class="text-xs"><?= htmlspecialchars($vs['recorded_by_name'] ?? 'N/A') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-6 text-gray-400">
            <i class="fas fa-heartbeat text-3xl block mb-2" style="color: #EC4899;"></i>
            <p>No vital signs recorded for this patient</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT VISITS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-notes-medical title-blue mr-2"></i> Recent Visits
                <span class="badge-count">(<?= $total_visits ?> total)</span>
            </h3>
            <a href="visits.php?patient=<?= $patient['id'] ?>" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th>Visit #</th>
                        <th>Doctor</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_visits) > 0): ?>
                        <?php foreach ($recent_visits as $visit): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($visit['visit_number']) ?></td>
                                <td><?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($visit['visit_date'])) ?></td>
                                <td><span class="badge badge-info"><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></span></td>
                                <td>
                                    <span class="badge badge-<?= $visit['status_color'] ?? 'secondary' ?>">
                                        <?= ucfirst($visit['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="visit_details.php?id=<?= $visit['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-gray-400">No visits found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT BILLS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-invoice title-blue mr-2"></i> Recent Bills
                <span class="badge-count">(<?= $total_bills ?> total)</span>
            </h3>
            <a href="bills.php?patient=<?= $patient['id'] ?>" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Total Amount</th>
                        <th>Paid Amount</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_bills) > 0): ?>
                        <?php foreach ($recent_bills as $bill): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($bill['bill_number']) ?></td>
                                <td class="font-bold">TSh <?= number_format($bill['total_amount'] ?? 0) ?></td>
                                <td>TSh <?= number_format($bill['paid_amount'] ?? 0) ?></td>
                                <td>TSh <?= number_format($bill['balance'] ?? 0) ?></td>
                                <td>
                                    <span class="badge badge-<?= $bill['status_color'] ?? 'secondary' ?>">
                                        <?= ucfirst($bill['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($bill['created_at'])) ?></td>
                                <td>
                                    <a href="bill_details.php?id=<?= $bill['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-4 text-gray-400">No bills found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-prescription title-blue mr-2"></i> Recent Prescriptions
                <span class="badge-count">(<?= $total_prescriptions ?> total)</span>
            </h3>
            <a href="prescriptions.php?patient=<?= $patient['id'] ?>" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th>Prescription #</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_prescriptions) > 0): ?>
                        <?php foreach ($recent_prescriptions as $prescription): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($prescription['prescription_number']) ?></td>
                                <td><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-<?= $prescription['status'] === 'dispensed' ? 'success' : ($prescription['status'] === 'pending' ? 'warning' : 'secondary') ?>">
                                        <?= ucfirst($prescription['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($prescription['created_at'])) ?></td>
                                <td>
                                    <a href="prescription_details.php?id=<?= $prescription['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-gray-400">No prescriptions found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT LAB TESTS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-flask title-blue mr-2"></i> Recent Lab Tests
                <span class="badge-count">(<?= $total_lab_tests ?> total)</span>
            </h3>
            <a href="lab_tests.php?patient=<?= $patient['id'] ?>" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th>Test Name</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_lab_tests) > 0): ?>
                        <?php foreach ($recent_lab_tests as $test): ?>
                            <tr>
                                <td><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-<?= $test['status_color'] ?? 'secondary' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'N/A')) ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($test['created_at'])) ?></td>
                                <td>
                                    <a href="lab_test_details.php?id=<?= $test['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-gray-400">No lab tests found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT APPOINTMENTS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-calendar-check title-blue mr-2"></i> Recent Appointments
                <span class="badge-count">(<?= $total_appointments ?> total)</span>
            </h3>
            <a href="appointments.php?patient=<?= $patient['id'] ?>" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Appointment Date</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_appointments) > 0): ?>
                        <?php foreach ($recent_appointments as $appointment): ?>
                            <tr>
                                <td><?= htmlspecialchars($appointment['doctor_name'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= date('M d, Y h:i A', strtotime($appointment['appointment_date'])) ?></td>
                                <td><span class="badge badge-info"><?= ucfirst($appointment['visit_type'] ?? 'N/A') ?></span></td>
                                <td>
                                    <span class="badge badge-<?= $appointment['status_color'] ?? 'secondary' ?>">
                                        <?= ucfirst($appointment['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="appointment_details.php?id=<?= $appointment['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-gray-400">No appointments found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Patient Details
            <span class="text-gray-300 mx-2">|</span>
            Dr. <?= htmlspecialchars($doctor_name) ?>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE - SYNC WITH HEADER
    // ================================================================
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // ================================================================
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // PDF EXPORT
    // ================================================================
    function exportPDF() {
        showToast('📄 PDF Export', 'Generating PDF report for patient...', 'info');
        
        // Redirect to PDF generation page
        window.open('export_patient_pdf.php?id=<?= $patient_id ?>', '_blank');
        
        setTimeout(function() {
            showToast('✅ PDF Generated', 'PDF report has been generated successfully!', 'success');
        }, 2000);
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c🏥 Braick Dispensary - Patient Details (Doctor)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔐 Session-based login active', 'font-size:13px; color:#34D399;');
    console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 ID: <?= htmlspecialchars($patient['patient_id']) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c❤️ Vital Signs Records: <?= $total_vital_signs ?>', 'font-size:13px; color:#EC4899;');
    console.log('%c📊 Visits: <?= $total_visits ?> | Bills: <?= $total_bills ?> | Prescriptions: <?= $total_prescriptions ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📄 PDF Export: Modern design button', 'font-size:13px; color:#DC2626;');
    console.log('%c🌓 Dark mode synced with header', 'font-size:13px; color:#8B5CF6;');
</script>

</body>
</html>