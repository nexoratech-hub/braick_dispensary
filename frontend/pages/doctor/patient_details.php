<?php
// ================================================================
// FILE: frontend/pages/doctor/patient_details.php
// DOCTOR - PATIENT DETAILS WITH FULL REDESIGN V2
// FIXED: Uses database from dispensary_db
// NEW FLOW: Patient Info → Visit Info → Vital Signs (2 rows, 3 per row) → 
// Clinical Assessment Table (Symptoms, Complaints, Notes, HPI, Physical Exam) → 
// Lab Tests → Diagnosis → Prescriptions → Procedures/Equipment → Appointments → Bills
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
require_once __DIR__ . '/../../../backend/config/database.php';

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
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        COALESCE(SUM(total_amount), 0) as total_amount,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) as paid_amount,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END), 0) as pending_amount,
        COALESCE(SUM(CASE WHEN status = 'cancelled' THEN total_amount ELSE 0 END), 0) as cancelled_amount
    FROM bills 
    WHERE patient_id = ? AND status != 'cancelled'
");
$stmt->execute([$patient_id]);
$bills_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$total_bills = $bills_stats['total'] ?? 0;
$total_bill_amount = $bills_stats['total_amount'] ?? 0;
$paid_bill_amount = $bills_stats['paid_amount'] ?? 0;
$pending_bill_amount = $bills_stats['pending_amount'] ?? 0;
$cancelled_bill_amount = $bills_stats['cancelled_amount'] ?? 0;

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

// Total Procedures
$stmt = $db->prepare("SELECT COUNT(*) as total FROM procedures WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$total_procedures = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Payments
$stmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(amount), 0) as total_amount FROM payments WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$payments_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_payments = $payments_data['total'] ?? 0;
$total_payments_amount = $payments_data['total_amount'] ?? 0;

// ================================================================
// GET LATEST VISIT
// ================================================================
$stmt = $db->prepare("
    SELECT v.*, u.full_name as doctor_name, u.specialty as doctor_specialty
    FROM visits v
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE v.patient_id = ?
    ORDER BY v.created_at DESC
    LIMIT 1
");
$stmt->execute([$patient_id]);
$latest_visit = $stmt->fetch(PDO::FETCH_ASSOC);

// ================================================================
// GET VITAL SIGNS - LATEST
// ================================================================
$stmt = $db->prepare("
    SELECT vs.*, 
           u.full_name as recorded_by_name,
           v.visit_number
    FROM vital_signs vs
    LEFT JOIN users u ON vs.recorded_by = u.id
    LEFT JOIN visits v ON vs.visit_id = v.id
    WHERE vs.patient_id = ?
    ORDER BY vs.recorded_at DESC
    LIMIT 1
");
$stmt->execute([$patient_id]);
$latest_vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);

// ================================================================
// GET ALL VITAL SIGNS HISTORY
// ================================================================
$stmt = $db->prepare("
    SELECT vs.*, 
           u.full_name as recorded_by_name,
           v.visit_number
    FROM vital_signs vs
    LEFT JOIN users u ON vs.recorded_by = u.id
    LEFT JOIN visits v ON vs.visit_id = v.id
    WHERE vs.patient_id = ?
    ORDER BY vs.recorded_at DESC
    LIMIT 20
");
$stmt->execute([$patient_id]);
$vital_signs_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_vital_signs = count($vital_signs_history);

// ================================================================
// GET LAB TESTS
// ================================================================
$stmt = $db->prepare("
    SELECT lt.*, 
           u.full_name as doctor_name,
           tech.full_name as technician_name,
           v.visit_number
    FROM lab_tests lt
    INNER JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    LEFT JOIN users tech ON lt.performed_by = tech.id
    WHERE v.patient_id = ?
    ORDER BY lt.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET DIAGNOSIS HISTORY
// ================================================================
$stmt = $db->prepare("
    SELECT diagnosis, disease_id, disease_code, treatment, created_at, symptoms, hpi, physical_exam, complaint, notes
    FROM visits 
    WHERE patient_id = ? AND (diagnosis IS NOT NULL AND diagnosis != '' OR disease_id IS NOT NULL)
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$diagnosis_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get disease names for diagnosis
foreach ($diagnosis_history as &$diag) {
    if ($diag['disease_id']) {
        $stmt = $db->prepare("SELECT disease_name FROM diseases WHERE id = ?");
        $stmt->execute([$diag['disease_id']]);
        $disease = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($disease) {
            $diag['disease_name'] = $disease['disease_name'];
        }
    }
}
unset($diag);

// ================================================================
// GET PRESCRIPTIONS
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, 
           u.full_name as doctor_name,
           pi.medication_name, pi.dosage, pi.frequency, pi.quantity, 
           pi.duration, pi.route, pi.instructions, pi.unit_price, pi.total_price
    FROM prescriptions p
    LEFT JOIN users u ON p.doctor_id = u.id
    LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
    WHERE p.patient_id = ?
    ORDER BY p.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PROCEDURES
// ================================================================
$stmt = $db->prepare("
    SELECT pr.*, u.full_name as doctor_name
    FROM procedures pr
    LEFT JOIN users u ON pr.doctor_id = u.id
    WHERE pr.patient_id = ?
    ORDER BY pr.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET APPOINTMENTS
// ================================================================
$stmt = $db->prepare("
    SELECT a.*, u.full_name as doctor_name
    FROM appointments a
    LEFT JOIN users u ON a.doctor_id = u.id
    WHERE a.patient_id = ?
    ORDER BY a.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET BILLS
// ================================================================
$stmt = $db->prepare("
    SELECT b.*, 
           COALESCE(SUM(bi.total_price), 0) as items_total
    FROM bills b
    LEFT JOIN bill_items bi ON b.id = bi.bill_id
    WHERE b.patient_id = ?
    GROUP BY b.id
    ORDER BY b.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET BILL ITEMS FOR EACH BILL
// ================================================================
$bill_items_data = [];
foreach ($bills as $bill) {
    $stmt = $db->prepare("
        SELECT * FROM bill_items 
        WHERE bill_id = ? AND status != 'cancelled'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$bill['id']]);
    $bill_items_data[$bill['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// HELPERS
// ================================================================
function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'paid' => 'badge-success',
        'partial' => 'badge-info',
        'cancelled' => 'badge-danger',
        'completed' => 'badge-success',
        'dispensed' => 'badge-success',
        'in_progress' => 'badge-info',
        'scheduled' => 'badge-warning',
        'confirmed' => 'badge-info',
        'prescribed' => 'badge-purple',
        'assigned' => 'badge-info',
        'with_doctor' => 'badge-info',
        'lab_test' => 'badge-warning',
        'lab_completed' => 'badge-success'
    ];
    return $map[$status] ?? 'badge-secondary';
}

function getStatusIcon($status) {
    $map = [
        'pending' => 'fa-clock',
        'paid' => 'fa-check-circle',
        'partial' => 'fa-credit-card',
        'cancelled' => 'fa-times-circle',
        'completed' => 'fa-check-circle',
        'dispensed' => 'fa-check-circle',
        'in_progress' => 'fa-spinner fa-spin',
        'scheduled' => 'fa-calendar',
        'confirmed' => 'fa-check-circle',
        'prescribed' => 'fa-prescription',
        'assigned' => 'fa-user-md',
        'with_doctor' => 'fa-stethoscope',
        'lab_test' => 'fa-flask',
        'lab_completed' => 'fa-check-circle'
    ];
    return $map[$status] ?? 'fa-circle';
}

function getStatusColor($status) {
    $map = [
        'pending' => '#D97706',
        'paid' => '#059669',
        'partial' => '#0B5ED7',
        'cancelled' => '#DC2626',
        'completed' => '#059669',
        'dispensed' => '#059669',
        'in_progress' => '#0B5ED7',
        'scheduled' => '#D97706',
        'confirmed' => '#0B5ED7',
        'prescribed' => '#7C3AED',
        'assigned' => '#0B5ED7',
        'with_doctor' => '#0B5ED7',
        'lab_test' => '#D97706',
        'lab_completed' => '#059669'
    ];
    return $map[$status] ?? '#64748B';
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function formatDate($date) {
    if (empty($date)) return 'N/A';
    return date('M d, Y h:i A', strtotime($date));
}

function formatDateShort($date) {
    if (empty($date)) return 'N/A';
    return date('M d, Y', strtotime($date));
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details - <?= htmlspecialchars($patient['full_name']) ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================ */
        /* ROOT VARIABLES */
        /* ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --pink: #EC4899;
            --pink-bg: #FCE7F3;
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
        
        /* ================================================================ */
        /* BASE STYLES */
        /* ================================================================ */
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
        
        /* ================================================================ */
        /* MAIN CONTENT */
        /* ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: all 0.3s ease;
            background: var(--bg-body);
        }
        
        /* ================================================================ */
        /* SECTION DIVIDER */
        /* ================================================================ */
        .section-divider {
            border: none;
            border-top: 3px double var(--border-color);
            margin: 20px 0;
            opacity: 0.5;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 3px solid var(--primary-light);
        }
        
        [data-theme="dark"] .section-title {
            color: #6EA8FE;
            border-bottom-color: #1E3A5F;
        }
        
        .section-title .badge-count {
            font-size: 0.7rem;
            font-weight: 400;
            color: var(--text-secondary);
            background: var(--gray-100);
            padding: 2px 12px;
            border-radius: 12px;
        }
        
        [data-theme="dark"] .section-title .badge-count {
            background: #1E293B;
            color: #94A3B8;
        }
        
        /* ================================================================ */
        /* PROFILE HEADER */
        /* ================================================================ */
        .profile-header {
            background: var(--primary-gradient);
            border-radius: 16px;
            padding: 24px 30px;
            color: white;
            position: relative;
            overflow: hidden;
            margin-bottom: 24px;
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
        
        /* ================================================================ */
        /* STAT CARDS */
        /* ================================================================ */
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
            border-color: var(--primary);
        }
        
        .stat-card-mini .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        .stat-card-mini .stat-number.green { color: var(--success); }
        .stat-card-mini .stat-number.orange { color: var(--warning); }
        .stat-card-mini .stat-number.red { color: var(--danger); }
        .stat-card-mini .stat-number.purple { color: var(--purple); }
        .stat-card-mini .stat-number.pink { color: var(--pink); }
        
        .stat-card-mini .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .stat-card-mini .stat-icon { font-size: 1.5rem; margin-bottom: 4px; }
        .stat-card-mini .stat-amount {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--success);
            margin-top: 2px;
        }
        
        [data-theme="dark"] .stat-card-mini {
            background: #1E293B;
            border-color: #334155;
        }
        [data-theme="dark"] .stat-card-mini:hover { border-color: #0B5ED7; }
        [data-theme="dark"] .stat-card-mini .stat-number { color: #6EA8FE; }
        [data-theme="dark"] .stat-card-mini .stat-number.green { color: #34D399; }
        
        /* ================================================================ */
        /* CARDS */
        /* ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 18px 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        
        .card:hover {
            border-color: var(--primary);
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
        
        /* ================================================================ */
        /* TABLES - BLUE THEME */
        /* ================================================================ */
        .table-blue {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        
        .table-blue thead th {
            background: var(--primary-gradient) !important;
            color: #FFFFFF !important;
            font-weight: 700 !important;
            font-size: 0.6rem !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            padding: 8px 12px !important;
            border-bottom: 3px solid var(--primary-dark) !important;
            white-space: nowrap !important;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .table-blue thead th:first-child { border-radius: 8px 0 0 0 !important; }
        .table-blue thead th:last-child { border-radius: 0 8px 0 0 !important; }
        
        .table-blue tbody td {
            padding: 6px 12px !important;
            border-bottom: 1px solid var(--border-color) !important;
            color: var(--text-primary) !important;
            vertical-align: middle !important;
        }
        
        .table-blue tbody tr:hover td {
            background: var(--primary-bg) !important;
        }
        
        [data-theme="dark"] .table-blue tbody tr:hover td {
            background: #1A3A5F !important;
        }
        
        .table-blue .empty-row td {
            text-align: center;
            padding: 16px !important;
            color: var(--text-secondary);
            font-style: italic;
        }
        
        /* ================================================================ */
        /* BADGES */
        /* ================================================================ */
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
        .badge-success { background: var(--success-bg) !important; color: var(--success) !important; }
        .badge-warning { background: var(--warning-bg) !important; color: var(--warning) !important; }
        .badge-danger { background: var(--danger-bg) !important; color: var(--danger) !important; }
        .badge-info { background: var(--primary-bg) !important; color: var(--primary) !important; }
        .badge-secondary { background: var(--gray-200) !important; color: var(--gray-600) !important; }
        .badge-purple { background: var(--purple-bg) !important; color: var(--purple) !important; }
        .badge-pink { background: var(--pink-bg) !important; color: var(--pink) !important; }
        
        [data-theme="dark"] .badge-success { background: #1A3A2A !important; color: #34D399 !important; }
        [data-theme="dark"] .badge-warning { background: #3A2A1A !important; color: #FBBF24 !important; }
        [data-theme="dark"] .badge-danger { background: #3A1A1A !important; color: #F87171 !important; }
        [data-theme="dark"] .badge-info { background: #1E3A5F !important; color: #6EA8FE !important; }
        [data-theme="dark"] .badge-secondary { background: #2D3748 !important; color: #94A3B8 !important; }
        [data-theme="dark"] .badge-purple { background: #2A1A4A !important; color: #A78BFA !important; }
        
        /* ================================================================ */
        /* INFO ROWS */
        /* ================================================================ */
        .info-row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .info-row:last-child { border-bottom: none; }
        
        .info-row .info-label {
            width: 140px;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.78rem;
            flex-shrink: 0;
        }
        
        .info-row .info-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 0.82rem;
        }
        
        /* ================================================================ */
        /* VITAL SIGNS CARDS - 2 ROWS, 3 PER ROW */
        /* ================================================================ */
        .vital-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        
        .vital-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 14px 12px;
            text-align: center;
            border: 2px solid var(--border-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            min-height: 90px;
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
        
        .vital-card .vital-icon { font-size: 1.5rem; margin-bottom: 4px; }
        .vital-card .vital-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }
        .vital-card .vital-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.04em;
            margin-top: 2px;
        }
        .vital-card .vital-unit {
            font-size: 0.55rem;
            color: var(--text-secondary);
            font-weight: 400;
        }
        
        .vital-card.blue::before { background: linear-gradient(90deg, #0B5ED7, #1A73E8); }
        .vital-card.blue .vital-value { color: #0B5ED7; }
        .vital-card.red::before { background: linear-gradient(90deg, #EF4444, #F87171); }
        .vital-card.red .vital-value { color: #EF4444; }
        .vital-card.pink::before { background: linear-gradient(90deg, #EC4899, #F472B6); }
        .vital-card.pink .vital-value { color: #EC4899; }
        .vital-card.purple::before { background: linear-gradient(90deg, #7B2FBE, #9B4DCA); }
        .vital-card.purple .vital-value { color: #7B2FBE; }
        .vital-card.green::before { background: linear-gradient(90deg, #059669, #0AA84F); }
        .vital-card.green .vital-value { color: #059669; }
        .vital-card.indigo::before { background: linear-gradient(90deg, #4F46E5, #818CF8); }
        .vital-card.indigo .vital-value { color: #4F46E5; }
        
        [data-theme="dark"] .vital-card { background: #1E293B; border-color: #334155; }
        [data-theme="dark"] .vital-card:hover { border-color: #0B5ED7; box-shadow: 0 8px 30px rgba(0,0,0,0.3); }
        [data-theme="dark"] .vital-card .vital-value { color: #F1F5F9; }
        [data-theme="dark"] .vital-card.blue .vital-value { color: #6EA8FE; }
        [data-theme="dark"] .vital-card.red .vital-value { color: #F87171; }
        [data-theme="dark"] .vital-card.pink .vital-value { color: #F472B6; }
        [data-theme="dark"] .vital-card.purple .vital-value { color: #A78BFA; }
        [data-theme="dark"] .vital-card.green .vital-value { color: #34D399; }
        [data-theme="dark"] .vital-card.indigo .vital-value { color: #A5B4FC; }
        
        /* ================================================================ */
        /* CLINICAL ASSESSMENT TABLE */
        /* ================================================================ */
        .clinical-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        
        .clinical-table thead th {
            background: var(--primary-gradient) !important;
            color: #FFFFFF !important;
            font-weight: 700 !important;
            font-size: 0.6rem !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            padding: 8px 12px !important;
            border-bottom: 3px solid var(--primary-dark) !important;
            text-align: left !important;
        }
        
        .clinical-table thead th:first-child { border-radius: 8px 0 0 0 !important; }
        .clinical-table thead th:last-child { border-radius: 0 8px 0 0 !important; }
        
        .clinical-table tbody td {
            padding: 10px 12px !important;
            border-bottom: 1px solid var(--border-color) !important;
            color: var(--text-primary) !important;
            vertical-align: top !important;
            background: var(--bg-card) !important;
        }
        
        .clinical-table tbody tr:hover td {
            background: var(--primary-bg) !important;
        }
        
        [data-theme="dark"] .clinical-table tbody td {
            background: #1E293B !important;
        }
        [data-theme="dark"] .clinical-table tbody tr:hover td {
            background: #1A3A5F !important;
        }
        
        .clinical-table .empty-cell {
            color: var(--text-secondary);
            font-style: italic;
            font-size: 0.75rem;
        }
        
        .clinical-table .symptom-tag-sm {
            display: inline-block;
            padding: 1px 10px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 500;
            background: var(--primary-bg);
            color: var(--primary);
            border: 1px solid var(--primary-light);
            margin: 1px 3px 1px 0;
        }
        
        [data-theme="dark"] .clinical-table .symptom-tag-sm {
            background: #1A2A4A;
            color: #6EA8FE;
            border-color: #1E3A5F;
        }
        
        /* ================================================================ */
        /* DIAGNOSIS BOX */
        /* ================================================================ */
        .diagnosis-box {
            background: var(--primary-bg);
            border-radius: 10px;
            padding: 10px 14px;
            border-left: 4px solid var(--primary);
            margin-bottom: 6px;
        }
        
        [data-theme="dark"] .diagnosis-box { background: #1A2A4A; }
        
        .diagnosis-box .diag-name {
            font-weight: 700;
            color: var(--primary);
        }
        .diagnosis-box .diag-code {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .diagnosis-box .diag-treatment {
            font-size: 0.78rem;
            color: var(--text-primary);
            margin-top: 4px;
        }
        .diagnosis-box .diag-date {
            font-size: 0.6rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        /* ================================================================ */
        /* BILL SUMMARY CARDS */
        /* ================================================================ */
        .bill-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-top: 12px;
        }
        
        .bill-summary-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .bill-summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        
        .bill-summary-card .bill-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        
        .bill-summary-card .bill-content { flex: 1; }
        .bill-summary-card .bill-label {
            font-size: 0.6rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
        }
        .bill-summary-card .bill-value {
            font-size: 1.1rem;
            font-weight: 700;
            display: block;
            margin-top: 2px;
        }
        
        .bill-summary-card.total-card { border-color: var(--primary); }
        .bill-summary-card.total-card .bill-icon { background: var(--primary-bg); color: var(--primary); }
        .bill-summary-card.total-card .bill-value { color: var(--primary); }
        
        .bill-summary-card.paid-card { border-color: var(--success); }
        .bill-summary-card.paid-card .bill-icon { background: var(--success-bg); color: var(--success); }
        .bill-summary-card.paid-card .bill-value { color: var(--success); }
        
        .bill-summary-card.pending-card { border-color: var(--warning); }
        .bill-summary-card.pending-card .bill-icon { background: var(--warning-bg); color: var(--warning); }
        .bill-summary-card.pending-card .bill-value { color: var(--warning); }
        
        .bill-summary-card.cancelled-card { border-color: var(--danger); }
        .bill-summary-card.cancelled-card .bill-icon { background: var(--danger-bg); color: var(--danger); }
        .bill-summary-card.cancelled-card .bill-value { color: var(--danger); }
        
        [data-theme="dark"] .bill-summary-card { background: #1E293B; border-color: #334155; }
        [data-theme="dark"] .bill-summary-card.total-card { border-color: #6EA8FE; }
        [data-theme="dark"] .bill-summary-card.paid-card { border-color: #34D399; }
        [data-theme="dark"] .bill-summary-card.pending-card { border-color: #FBBF24; }
        [data-theme="dark"] .bill-summary-card.cancelled-card { border-color: #F87171; }
        
        /* ================================================================ */
        /* CONTENT BLOCK */
        /* ================================================================ */
        .content-block {
            padding: 8px 12px;
            background: var(--gray-50);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 0.82rem;
            color: var(--text-primary);
            line-height: 1.5;
            white-space: pre-wrap;
        }
        
        [data-theme="dark"] .content-block {
            background: #0F172A;
            border-color: #334155;
        }
        
        /* ================================================================ */
        /* LAB TEST RESULT */
        /* ================================================================ */
        .lab-result {
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
        }
        .lab-result.normal { color: var(--success); }
        .lab-result.abnormal { color: var(--danger); }
        .lab-result.pending { color: var(--warning); }
        
        /* ================================================================ */
        /* BUTTONS */
        /* ================================================================ */
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
        
        .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #047857; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5,150,105,0.3); }
        
        /* PDF Button */
        .btn-pdf {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
            box-shadow: 0 4px 14px rgba(220, 38, 38, 0.3);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
        
        .btn-pdf i { font-size: 1rem; }
        
        /* ================================================================ */
        /* FOOTER */
        /* ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        .text-gray-300 { color: #D1D5DB; }
        .mx-2 { margin-left: 0.5rem; margin-right: 0.5rem; }
        .text-xs { font-size: 0.65rem; }
        .text-sm { font-size: 0.75rem; }
        .font-mono { font-family: monospace; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mt-4 { margin-top: 16px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .flex-wrap { flex-wrap: wrap; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .flex { display: flex; }
        .grid { display: grid; }
        .grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
        .grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
        .grid-cols-4 { grid-template-columns: repeat(4, 1fr); }
        .col-span-2 { grid-column: span 2; }
        .col-span-3 { grid-column: span 3; }
        
        /* ================================================================ */
        /* RESPONSIVE */
        /* ================================================================ */
        @media (max-width: 1024px) {
            .main-content { padding: 16px; }
            .vital-grid { grid-template-columns: repeat(3, 1fr); }
            .bill-summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .main-content { padding: 12px; margin-left: 0; }
            .profile-header { padding: 16px 18px; }
            .profile-header .profile-avatar { width: 60px; height: 60px; font-size: 1.8rem; }
            .profile-header .profile-name { font-size: 1.2rem; }
            .info-row { flex-direction: column; gap: 2px; }
            .info-row .info-label { width: 100%; font-size: 0.7rem; }
            .stat-card-mini .stat-number { font-size: 1.4rem; }
            .grid-cols-2 { grid-template-columns: 1fr; }
            .grid-cols-3 { grid-template-columns: 1fr; }
            .grid-cols-4 { grid-template-columns: 1fr 1fr; }
            .vital-grid { grid-template-columns: repeat(2, 1fr); }
            .bill-summary-grid { grid-template-columns: 1fr; }
            .vital-card { min-height: 70px; padding: 10px 8px; }
            .vital-card .vital-value { font-size: 1rem; }
            .vital-card .vital-icon { font-size: 1.2rem; }
            .card { padding: 12px 14px; }
            .card-header { flex-direction: column; align-items: flex-start; }
            .section-title { font-size: 0.95rem; }
            .table-blue { font-size: 0.7rem; }
            .table-blue thead th, .table-blue tbody td { padding: 4px 8px !important; }
            .clinical-table { font-size: 0.7rem; }
            .clinical-table thead th, .clinical-table tbody td { padding: 6px 8px !important; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .profile-header .profile-avatar { width: 50px; height: 50px; font-size: 1.4rem; }
            .profile-header .profile-name { font-size: 1rem; }
            .stat-card-mini .stat-number { font-size: 1.2rem; }
            .stat-card-mini { padding: 10px 12px; }
            .grid-cols-4 { grid-template-columns: 1fr 1fr; }
            .vital-grid { grid-template-columns: 1fr 1fr; }
            .bill-summary-grid { grid-template-columns: 1fr; }
            .vital-card { min-height: 60px; padding: 8px 6px; }
            .vital-card .vital-value { font-size: 0.9rem; }
            .card { padding: 10px 12px; }
            .btn { font-size: 0.65rem; padding: 4px 10px; }
            .btn-pdf { font-size: 0.7rem; padding: 6px 12px; }
        }
        
        /* ================================================================ */
        /* PRINT */
        /* ================================================================ */
        @media print {
            .top-nav, .sidebar, .btn, .btn-pdf, .footer, .no-print { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
            .card, .profile-header { border: 1px solid #ddd !important; box-shadow: none !important; page-break-inside: avoid; }
            .profile-header { background: #0B5ED7 !important; }
            .table-blue thead th { background: #0B5ED7 !important; }
            .clinical-table thead th { background: #0B5ED7 !important; }
            .bill-summary-card { border: 1px solid #ddd !important; }
            .section-title { border-bottom-color: #0B5ED7 !important; }
            .vital-card { border: 1px solid #ddd !important; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PROFILE HEADER -->
    <!-- ================================================================ -->
    <div class="profile-header">
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
                    <span class="profile-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.2);">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?>
                    </span>
                </div>
                <div class="flex items-center gap-3 flex-wrap mt-1" style="opacity:0.85;">
                    <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-phone-alt"></i> Emergency: <strong><?= htmlspecialchars($patient['emergency_contact'] ?? 'N/A') ?></strong></span>
                    <?php if ($patient['assigned_doctor_name']): ?>
                        <span><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($patient['assigned_doctor_name']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex gap-2 flex-wrap no-print">
                <!-- View PDF Button - Replaced New Visit and Consultation -->
                <button onclick="window.location.href='view_patient_pdf.php?id=<?= $patient['id'] ?>'" class="btn-pdf">
                    <i class="fas fa-file-pdf"></i> View PDF
                </button>
                <a href="my_patients.php" class="btn" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.15);">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-3 mb-5">
        
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
            <div class="stat-icon">💉</div>
            <p class="stat-number red"><?= $total_procedures ?></p>
            <p class="stat-label">Procedures</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">❤️</div>
            <p class="stat-number pink"><?= $total_vital_signs ?></p>
            <p class="stat-label">Vital Signs</p>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- 1. PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-user-circle"></i> Patient Information
        <span class="badge-count">Personal Details</span>
    </div>
    <div class="card">
        <div class="grid grid-cols-2 gap-2">
            <div class="info-row">
                <span class="info-label"><i class="fas fa-user"></i> Full Name</span>
                <span class="info-value font-semibold"><?= htmlspecialchars($patient['full_name']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-id-card"></i> Patient ID</span>
                <span class="info-value font-mono"><?= htmlspecialchars($patient['patient_id']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-calendar-alt"></i> Date of Birth</span>
                <span class="info-value"><?= $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) . ' (' . calculateAge($patient['date_of_birth']) . ' yrs)' : 'N/A' ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-venus-mars"></i> Gender</span>
                <span class="info-value"><?= ucfirst(htmlspecialchars($patient['gender'] ?? 'N/A')) ?></span>
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
                <span class="info-value font-semibold" style="color:var(--danger);"><?= htmlspecialchars($patient['emergency_contact'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row col-span-2">
                <span class="info-label"><i class="fas fa-allergies"></i> Allergies</span>
                <span class="info-value" style="color:var(--danger);"><?= htmlspecialchars($patient['allergies'] ?? 'None') ?></span>
            </div>
            <div class="info-row col-span-2">
                <span class="info-label"><i class="fas fa-store-alt"></i> Branch</span>
                <span class="info-value"><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 2. VISIT INFORMATION -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-clinic-medical"></i> Visit Information
        <span class="badge-count">Latest Visit</span>
    </div>
    <div class="card">
        <?php if ($latest_visit): ?>
            <div class="grid grid-cols-2 gap-2">
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-hashtag"></i> Visit Number</span>
                    <span class="info-value font-mono font-semibold"><?= htmlspecialchars($latest_visit['visit_number']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-calendar-alt"></i> Date</span>
                    <span class="info-value"><?= formatDate($latest_visit['visit_date']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-user-md"></i> Doctor</span>
                    <span class="info-value">Dr. <?= htmlspecialchars($latest_visit['doctor_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-stethoscope"></i> Specialty</span>
                    <span class="info-value"><?= htmlspecialchars($latest_visit['doctor_specialty'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-tag"></i> Visit Type</span>
                    <span class="info-value"><?= ucfirst($latest_visit['visit_type'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-money-bill-wave"></i> Consultation Fee</span>
                    <span class="info-value font-semibold">TSh <?= number_format($latest_visit['consultation_fee'] ?? 0, 0) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-clock"></i> Status</span>
                    <span class="info-value">
                        <span class="badge <?= getStatusBadgeClass($latest_visit['status']) ?>">
                            <i class="fas <?= getStatusIcon($latest_visit['status']) ?>"></i>
                            <?= ucfirst(str_replace('_', ' ', $latest_visit['status'] ?? 'Pending')) ?>
                        </span>
                    </span>
                </div>
                <?php if ($latest_visit['is_completed']): ?>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-check-circle"></i> Completed</span>
                        <span class="info-value"><?= formatDate($latest_visit['completed_at']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-gray-400">
                <i class="fas fa-clinic-medical text-2xl block mb-2"></i>
                <p>No visits recorded for this patient</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 3. VITAL SIGNS - 2 ROWS, 3 PER ROW -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-heartbeat" style="color:#EC4899;"></i> Vital Signs
        <span class="badge-count"><?= $total_vital_signs ?> records</span>
    </div>
    <div class="card">
        <?php if ($latest_vital_signs): ?>
            <!-- Row 1: Temperature, Blood Pressure, Pulse Rate -->
            <div class="vital-grid mb-3">
                <div class="vital-card blue">
                    <div class="vital-icon"><i class="fas fa-thermometer-half"></i></div>
                    <div class="vital-value"><?= $latest_vital_signs['temperature'] ?? '--' ?> <span class="vital-unit">°C</span></div>
                    <div class="vital-label">Temperature</div>
                </div>
                <div class="vital-card red">
                    <div class="vital-icon"><i class="fas fa-heart"></i></div>
                    <div class="vital-value"><?= ($latest_vital_signs['blood_pressure_systolic'] ?? '--') . '/' . ($latest_vital_signs['blood_pressure_diastolic'] ?? '--') ?> <span class="vital-unit">mmHg</span></div>
                    <div class="vital-label">Blood Pressure</div>
                </div>
                <div class="vital-card pink">
                    <div class="vital-icon"><i class="fas fa-heartbeat"></i></div>
                    <div class="vital-value"><?= $latest_vital_signs['pulse_rate'] ?? '--' ?> <span class="vital-unit">bpm</span></div>
                    <div class="vital-label">Pulse Rate</div>
                </div>
            </div>
            <!-- Row 2: Weight, Height, BMI -->
            <div class="vital-grid">
                <div class="vital-card purple">
                    <div class="vital-icon"><i class="fas fa-weight"></i></div>
                    <div class="vital-value"><?= $latest_vital_signs['weight'] ?? '--' ?> <span class="vital-unit">kg</span></div>
                    <div class="vital-label">Weight</div>
                </div>
                <div class="vital-card green">
                    <div class="vital-icon"><i class="fas fa-ruler-vertical"></i></div>
                    <div class="vital-value"><?= $latest_vital_signs['height'] ?? '--' ?> <span class="vital-unit">cm</span></div>
                    <div class="vital-label">Height</div>
                </div>
                <div class="vital-card indigo">
                    <div class="vital-icon"><i class="fas fa-calculator"></i></div>
                    <div class="vital-value"><?= $latest_vital_signs['bmi'] ?? '--' ?></div>
                    <div class="vital-label">BMI</div>
                </div>
            </div>
            <div class="text-xs text-gray-400 mt-3">
                <i class="fas fa-user"></i> Recorded by: <?= htmlspecialchars($latest_vital_signs['recorded_by_name'] ?? 'N/A') ?>
                <?php if ($latest_vital_signs['visit_number']): ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-stethoscope"></i> Visit: <?= htmlspecialchars($latest_vital_signs['visit_number']) ?>
                <?php endif; ?>
                <span class="mx-2">|</span>
                <i class="fas fa-clock"></i> <?= formatDate($latest_vital_signs['recorded_at']) ?>
            </div>
            <?php if ($latest_vital_signs['notes']): ?>
                <div class="mt-2 content-block"><?= nl2br(htmlspecialchars($latest_vital_signs['notes'])) ?></div>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-4 text-gray-400">
                <i class="fas fa-heartbeat text-2xl block mb-2" style="color:#EC4899;"></i>
                <p>No vital signs recorded</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 4. CLINICAL ASSESSMENT TABLE - Symptoms, Complaints, Notes, HPI, Physical Exam -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-clipboard-list" style="color:#D97706;"></i> Clinical Assessment
        <span class="badge-count">Symptoms, Complaints, Notes, HPI & Physical Exam</span>
    </div>
    <div class="card">
        <div class="overflow-x-auto">
            <table class="clinical-table">
                <thead>
                    <tr>
                        <th style="width:20%;">Symptoms</th>
                        <th style="width:20%;">Complaints</th>
                        <th style="width:20%;">Notes</th>
                        <th style="width:20%;">HPI</th>
                        <th style="width:20%;">Physical Examination</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <?php if (!empty($latest_visit['symptoms'])): ?>
                                <?php 
                                    $symptoms_array = array_map('trim', explode(',', $latest_visit['symptoms']));
                                    foreach ($symptoms_array as $sym):
                                        if (!empty($sym)):
                                ?>
                                    <span class="symptom-tag-sm"><?= htmlspecialchars($sym) ?></span>
                                <?php 
                                        endif;
                                    endforeach; 
                                ?>
                            <?php else: ?>
                                <span class="empty-cell">No symptoms recorded</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($latest_visit['complaint'])): ?>
                                <?= nl2br(htmlspecialchars($latest_visit['complaint'])) ?>
                            <?php else: ?>
                                <span class="empty-cell">No complaint recorded</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($latest_visit['notes'])): ?>
                                <?= nl2br(htmlspecialchars($latest_visit['notes'])) ?>
                            <?php else: ?>
                                <span class="empty-cell">No notes recorded</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($latest_visit['hpi'])): ?>
                                <?= nl2br(htmlspecialchars($latest_visit['hpi'])) ?>
                            <?php else: ?>
                                <span class="empty-cell">No HPI recorded</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($latest_visit['physical_exam'])): ?>
                                <?= nl2br(htmlspecialchars($latest_visit['physical_exam'])) ?>
                            <?php else: ?>
                                <span class="empty-cell">No physical exam recorded</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 5. LAB TESTS -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-flask" style="color:#7C3AED;"></i> Lab Tests
        <span class="badge-count"><?= count($lab_tests) ?> records</span>
    </div>
    <div class="card">
        <?php if (count($lab_tests) > 0): ?>
            <div class="overflow-x-auto">
                <table class="table-blue">
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Visit</th>
                            <th>Results</th>
                            <th>Lab Technician</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $test): ?>
                            <tr>
                                <td class="font-semibold"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                <td><span class="font-mono text-xs"><?= htmlspecialchars($test['visit_number'] ?? 'N/A') ?></span></td>
                                <td>
                                    <?php if (!empty($test['results'])): ?>
                                        <span class="lab-result normal"><?= htmlspecialchars(substr($test['results'], 0, 50)) . (strlen($test['results']) > 50 ? '...' : '') ?></span>
                                    <?php else: ?>
                                        <span class="lab-result pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($test['technician_name'] ?? 'Not assigned') ?></td>
                                <td>
                                    <span class="badge <?= getStatusBadgeClass($test['status']) ?>">
                                        <i class="fas <?= getStatusIcon($test['status']) ?>"></i>
                                        <?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'Pending')) ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= formatDateShort($test['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-gray-400">
                <i class="fas fa-flask text-2xl block mb-2" style="color:#7C3AED;"></i>
                <p>No lab tests found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 6. DIAGNOSIS -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-diagnoses" style="color:#7C3AED;"></i> Diagnosis
        <span class="badge-count"><?= count($diagnosis_history) ?> records</span>
    </div>
    <div class="card">
        <?php if (count($diagnosis_history) > 0): ?>
            <div class="grid grid-cols-2 gap-3">
                <?php foreach ($diagnosis_history as $diag): ?>
                    <div class="diagnosis-box">
                        <div class="diag-name">
                            <?= htmlspecialchars($diag['disease_name'] ?? $diag['diagnosis'] ?? 'N/A') ?>
                            <?php if ($diag['disease_code']): ?>
                                <span class="diag-code">(<?= htmlspecialchars($diag['disease_code']) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($diag['treatment']): ?>
                            <div class="diag-treatment">
                                <i class="fas fa-prescription"></i> <?= htmlspecialchars($diag['treatment']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="diag-date">
                            <i class="fas fa-clock"></i> <?= formatDateShort($diag['created_at']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-gray-400">
                <i class="fas fa-diagnoses text-2xl block mb-2" style="color:#7C3AED;"></i>
                <p>No diagnosis recorded</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 7. PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-prescription" style="color:#059669;"></i> Recent Prescriptions
        <span class="badge-count"><?= count($prescriptions) ?> records</span>
    </div>
    <div class="card">
        <?php if (count($prescriptions) > 0): ?>
            <div class="overflow-x-auto">
                <table class="table-blue">
                    <thead>
                        <tr>
                            <th>Prescription #</th>
                            <th>Medication</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Qty</th>
                            <th>Instructions</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $pres): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?></td>
                                <td class="font-semibold"><?= htmlspecialchars($pres['medication_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($pres['dosage'] ?? '') ?></td>
                                <td><?= htmlspecialchars($pres['frequency'] ?? '') ?></td>
                                <td><?= $pres['quantity'] ?? 0 ?></td>
                                <td><?= htmlspecialchars($pres['instructions'] ?? '') ?></td>
                                <td>
                                    <span class="badge <?= getStatusBadgeClass($pres['status']) ?>">
                                        <i class="fas <?= getStatusIcon($pres['status']) ?>"></i>
                                        <?= ucfirst($pres['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-gray-400">
                <i class="fas fa-prescription text-2xl block mb-2" style="color:#059669;"></i>
                <p>No prescriptions found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 8. PROCEDURES -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-syringe" style="color:#D97706;"></i> Recent Procedures & Equipment
        <span class="badge-count"><?= count($procedures) ?> records</span>
    </div>
    <div class="card">
        <?php if (count($procedures) > 0): ?>
            <div class="overflow-x-auto">
                <table class="table-blue">
                    <thead>
                        <tr>
                            <th>Procedure Name</th>
                            <th>Doctor</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($procedures as $proc): ?>
                            <tr>
                                <td class="font-semibold"><?= htmlspecialchars($proc['procedure_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($proc['doctor_name'] ?? 'N/A') ?></td>
                                <td><?= ($proc['procedure_price'] ?? 0) > 0 ? 'TSh ' . number_format($proc['procedure_price'], 0) : '<span class="text-green-600">FREE</span>' ?></td>
                                <td>
                                    <span class="badge <?= getStatusBadgeClass($proc['status']) ?>">
                                        <i class="fas <?= getStatusIcon($proc['status']) ?>"></i>
                                        <?= ucfirst($proc['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= formatDateShort($proc['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-gray-400">
                <i class="fas fa-syringe text-2xl block mb-2" style="color:#D97706;"></i>
                <p>No procedures found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 9. APPOINTMENTS -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-calendar-check" style="color:#0B5ED7;"></i> Recent Appointments
        <span class="badge-count"><?= count($appointments) ?> records</span>
    </div>
    <div class="card">
        <?php if (count($appointments) > 0): ?>
            <div class="overflow-x-auto">
                <table class="table-blue">
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Appointment Date</th>
                            <th>Type</th>
                            <th>Purpose</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $app): ?>
                            <tr>
                                <td>Dr. <?= htmlspecialchars($app['doctor_name'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= formatDate($app['appointment_date']) ?></td>
                                <td><span class="badge badge-info"><?= ucfirst($app['visit_type'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($app['purpose'] ?? '') ?></td>
                                <td>
                                    <span class="badge <?= getStatusBadgeClass($app['status']) ?>">
                                        <i class="fas <?= getStatusIcon($app['status']) ?>"></i>
                                        <?= ucfirst($app['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-gray-400">
                <i class="fas fa-calendar-check text-2xl block mb-2" style="color:#0B5ED7;"></i>
                <p>No appointments found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 10. BILLS -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-receipt" style="color:#0B5ED7;"></i> Bills & Payments
        <span class="badge-count"><?= $total_bills ?> bills</span>
    </div>
    
    <!-- Bill Summary Cards -->
    <div class="bill-summary-grid">
        <div class="bill-summary-card total-card">
            <div class="bill-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="bill-content">
                <span class="bill-label">💰 Total Bills</span>
                <span class="bill-value">TSh <?= number_format($total_bill_amount, 0) ?></span>
            </div>
        </div>
        <div class="bill-summary-card paid-card">
            <div class="bill-icon"><i class="fas fa-check-circle"></i></div>
            <div class="bill-content">
                <span class="bill-label">✅ Paid</span>
                <span class="bill-value">TSh <?= number_format($paid_bill_amount, 0) ?></span>
            </div>
        </div>
        <div class="bill-summary-card pending-card">
            <div class="bill-icon"><i class="fas fa-clock"></i></div>
            <div class="bill-content">
                <span class="bill-label">⏳ Pending</span>
                <span class="bill-value">TSh <?= number_format($pending_bill_amount, 0) ?></span>
            </div>
        </div>
        <div class="bill-summary-card cancelled-card">
            <div class="bill-icon"><i class="fas fa-times-circle"></i></div>
            <div class="bill-content">
                <span class="bill-label">❌ Cancelled</span>
                <span class="bill-value">TSh <?= number_format($cancelled_bill_amount, 0) ?></span>
            </div>
        </div>
    </div>

    <!-- Bill Items Table -->
    <div class="card mt-3">
        <?php if (count($bills) > 0): ?>
            <div class="overflow-x-auto">
                <table class="table-blue">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Paid Amount</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $bill): 
                            $items = $bill_items_data[$bill['id']] ?? [];
                        ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if (count($items) > 0): ?>
                                        <?php foreach ($items as $item): ?>
                                            <div class="text-xs" style="padding:1px 0;border-bottom:1px solid var(--border-color);">
                                                <?= htmlspecialchars($item['item_name'] ?? '') ?>
                                                <span class="text-gray-400">x<?= $item['quantity'] ?? 1 ?></span>
                                                <span class="float-right">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">No items</span>
                                    <?php endif; ?>
                                </td>
                                <td class="font-semibold">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                                <td>TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                <td class="font-semibold <?= ($bill['balance'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                    TSh <?= number_format($bill['balance'] ?? 0, 0) ?>
                                </td>
                                <td>
                                    <span class="badge <?= getStatusBadgeClass($bill['status']) ?>">
                                        <i class="fas <?= getStatusIcon($bill['status']) ?>"></i>
                                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= formatDateShort($bill['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-gray-400">
                <i class="fas fa-receipt text-2xl block mb-2" style="color:#0B5ED7;"></i>
                <p>No bills found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer no-print">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Patient Details
            <span class="text-gray-300 mx-2">|</span>
            Dr. <?= htmlspecialchars($doctor_name) ?>
            <span class="text-gray-300 mx-2">|</span>
            <?= htmlspecialchars($patient['full_name']) ?>
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
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
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

    console.log('%c🏥 Braick Dispensary - Patient Details (Redesigned V2)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 New Flow: Patient Info → Visit Info → Vital Signs (2 rows, 3 per row) → Clinical Assessment Table → Lab Tests → Diagnosis → Prescriptions → Procedures → Appointments → Bills', 'font-size:12px; color:#059669;');
    console.log('%c📊 Clinical Assessment Table: Symptoms, Complaints, Notes, HPI, Physical Exam', 'font-size:12px; color:#D97706;');
    console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 ID: <?= htmlspecialchars($patient['patient_id']) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c❤️ Vital Signs: <?= $total_vital_signs ?> records (2 rows, 3 per row)', 'font-size:13px; color:#EC4899;');
    console.log('%c💰 Bills: <?= $total_bills ?> | Total: TSh <?= number_format($total_bill_amount, 0) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 Bill Cards: Total, Paid, Pending, Cancelled', 'font-size:13px; color:#059669;');
    console.log('%c✅ Diagnosis: <?= count($diagnosis_history) ?> records', 'font-size:13px; color:#7C3AED;');
    console.log('%c💊 Prescriptions: <?= $total_prescriptions ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔬 Lab Tests: <?= $total_lab_tests ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📄 View PDF button only (New Visit & Consultation removed)', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>