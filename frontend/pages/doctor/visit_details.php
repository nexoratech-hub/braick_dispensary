<?php
// ================================================================
// FILE: frontend/pages/doctor/visit_details.php
// VISIT DETAILS - FULL HISTORY
// Shows: Patient Info, Vital Signs (6), Symptoms, Lab Tests, Results,
//        Diagnosis, Medications, Procedures, Tools, Bills
// WITH PDF DOWNLOAD - Beautiful Design with Logo
// Session-based login (NO BYPASS)
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
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_online = $_SESSION['is_online'] ?? 0;

// ================================================================
// GET PARAMETERS
// ================================================================
$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($visit_id <= 0) {
    header('Location: my_patients.php?error=invalid_visit');
    exit;
}

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
    $stmt = $db->prepare("SELECT id, full_name, branch_id, specialty, profile_pic, status, is_online FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor_data || $doctor_data['status'] !== 'active') {
        session_destroy();
        header('Location: /dispensary_system/frontend/pages/login.php');
        exit;
    }
    
    $doctor_name = $doctor_data['full_name'];
    $doctor_branch_id = $doctor_data['branch_id'] ?? 1;
    $doctor_specialty = $doctor_data['specialty'] ?? 'General Medicine';
    $profile_pic = $doctor_data['profile_pic'] ?? '';
    $is_online = $doctor_data['is_online'] ?? 0;
    
    $_SESSION['full_name'] = $doctor_name;
    $_SESSION['branch_id'] = $doctor_branch_id;
    $_SESSION['specialty'] = $doctor_specialty;
    $_SESSION['profile_pic'] = $profile_pic;
    $_SESSION['is_online'] = $is_online;
    
} catch (Exception $e) {
    error_log("visit_details verification error: " . $e->getMessage());
}

// ================================================================
// GET VISIT DETAILS WITH PATIENT INFO - Verify doctor has access
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT v.*, 
               p.id as patient_id,
               p.full_name as patient_name,
               p.patient_id as patient_code,
               p.phone,
               p.email,
               p.date_of_birth,
               p.gender,
               p.address,
               p.blood_group,
               p.allergies,
               p.emergency_contact,
               p.created_at as patient_registered,
               u.full_name as doctor_name,
               u.specialty as doctor_specialty,
               b.name as branch_name
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN branches b ON v.branch_id = b.id
        WHERE v.id = ? AND v.doctor_id = ?
    ");
    $stmt->execute([$visit_id, $doctor_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$visit) {
        // Check if visit exists but belongs to another doctor
        $stmt = $db->prepare("SELECT id, doctor_id FROM visits WHERE id = ?");
        $stmt->execute([$visit_id]);
        $visit_check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($visit_check) {
            header('Location: my_patients.php?error=access_denied');
            exit;
        }
        header('Location: my_patients.php?error=visit_not_found');
        exit;
    }
} catch (Exception $e) {
    error_log("Visit fetch error: " . $e->getMessage());
    header('Location: my_patients.php?error=database_error');
    exit;
}

$patient_id = $visit['patient_id'];

// ================================================================
// GET VITAL SIGNS - ONLY 6: Temperature, BP, Pulse, Weight, Height, BMI
// ================================================================
$vital_signs = [];
try {
    $stmt = $db->prepare("
        SELECT temperature, blood_pressure_systolic, blood_pressure_diastolic, pulse_rate, weight, height, bmi, recorded_at 
        FROM vital_signs 
        WHERE visit_id = ? 
        ORDER BY recorded_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$visit_id]);
    $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Format blood pressure for display
    if ($vital_signs && isset($vital_signs['blood_pressure_systolic']) && isset($vital_signs['blood_pressure_diastolic'])) {
        $vital_signs['blood_pressure'] = $vital_signs['blood_pressure_systolic'] . '/' . $vital_signs['blood_pressure_diastolic'];
    } else {
        $vital_signs['blood_pressure'] = 'N/A';
    }
} catch (Exception $e) {
    $vital_signs = [];
}

// ================================================================
// GET LAB TESTS AND RESULTS
// ================================================================
$lab_tests = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM lab_tests 
        WHERE visit_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lab_tests = [];
}

// ================================================================
// GET PRESCRIPTIONS / MEDICATIONS
// ================================================================
$prescriptions = [];
try {
    $stmt = $db->prepare("
        SELECT p.*, 
               GROUP_CONCAT(pi.medication_name SEPARATOR ', ') as medication_names,
               GROUP_CONCAT(pi.dosage SEPARATOR ', ') as dosage_list,
               GROUP_CONCAT(pi.frequency SEPARATOR ', ') as frequency_list,
               GROUP_CONCAT(pi.quantity SEPARATOR ', ') as quantity_list,
               GROUP_CONCAT(pi.total_price SEPARATOR ', ') as total_price_list
        FROM prescriptions p
        LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
        WHERE p.visit_id = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $prescriptions = [];
}

// ================================================================
// GET PROCEDURES
// ================================================================
$procedures = [];
try {
    $stmt = $db->prepare("
        SELECT bi.* 
        FROM bill_items bi
        WHERE bi.bill_id IN (SELECT id FROM patient_bills WHERE visit_id = ?)
        AND bi.item_type = 'procedure'
        AND bi.status != 'cancelled'
        ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $procedures = [];
}

// ================================================================
// GET TOOLS
// ================================================================
$tools = [];
try {
    $stmt = $db->prepare("
        SELECT bi.* 
        FROM bill_items bi
        WHERE bi.bill_id IN (SELECT id FROM patient_bills WHERE visit_id = ?)
        AND bi.item_type = 'tool'
        AND bi.status != 'cancelled'
        ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tools = [];
}

// ================================================================
// GET BILL INFORMATION
// ================================================================
$bill = [];
$bill_items = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM patient_bills 
        WHERE visit_id = ? 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$visit_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bill) {
        $stmt = $db->prepare("
            SELECT * FROM bill_items 
            WHERE bill_id = ? AND status != 'cancelled'
            ORDER BY created_at DESC
        ");
        $stmt->execute([$bill['id']]);
        $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $bill = [];
    $bill_items = [];
}

// ================================================================
// GET ALL VISITS FOR THIS PATIENT (HISTORY)
// ================================================================
$patient_visits = [];
try {
    $stmt = $db->prepare("
        SELECT v.*, 
               u.full_name as doctor_name,
               b.name as branch_name
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN branches b ON v.branch_id = b.id
        WHERE v.patient_id = ? 
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $patient_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patient_visits = [];
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'assigned' => 'badge-info',
        'with_doctor' => 'badge-info',
        'lab_test' => 'badge-warning',
        'in_progress' => 'badge-info',
        'prescribed' => 'badge-purple',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger',
        'paid' => 'badge-success',
        'partial' => 'badge-warning'
    ];
    return $map[$status] ?? 'badge-info';
}

function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
    $index = abs(crc32($name)) % count($colors);
    return $colors[$index];
}

// ================================================================
// GET LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_path)) {
    $logo_path = '';
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!-- ================================================================ -->
<!-- MAIN CONTENT (HTML REMAINS THE SAME) -->
<!-- ================================================================ -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visit Details - <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?> - Braick Dispensary</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        /* All styles remain the same as in your original file */
        /* ================================================================
           BLUE THEME - MAIN STYLES
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --primary-gradient: linear-gradient(135deg, #0B5ED7 0%, #1A7FE8 100%);
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
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
            --radius: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(11,94,215,0.10);
            --shadow-lg: 0 8px 32px rgba(11,94,215,0.15);
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--gray-50);
            color: var(--gray-800);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        
        [data-theme="dark"] body {
            background: var(--gray-900);
            color: var(--gray-100);
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--gray-50);
            color: var(--gray-800);
            transition: var(--transition);
        }
        
        [data-theme="dark"] .main-content {
            background: var(--gray-900);
            color: var(--gray-100);
        }
        
        /* ================================================================
           SCROLLBAR
           ================================================================ */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: var(--primary-light); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary); }
        [data-theme="dark"] ::-webkit-scrollbar-track { background: var(--gray-700); }
        [data-theme="dark"] ::-webkit-scrollbar-thumb { background: var(--primary-dark); }
        
        /* ================================================================
           PAGE HEADER - BLUE GRADIENT
           ================================================================ */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
            padding: 24px 28px;
            background: var(--primary-gradient);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            position: relative;
            color: white;
        }
        .page-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, rgba(255,255,255,0.3), rgba(255,255,255,0.6), rgba(255,255,255,0.3));
            border-radius: 0 0 4px 4px;
        }
        .page-header-left { flex: 1; }
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 0;
            color: white;
        }
        .page-title i { color: rgba(255,255,255,0.8); }
        
        .page-badge {
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-family: monospace;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .page-subtitle {
            font-size: 0.9rem;
            opacity: 0.85;
            margin-top: 6px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.9);
        }
        .page-subtitle strong { color: white; font-weight: 700; }
        
        .status-badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 16px;
            border-radius: 20px;
            text-transform: capitalize;
        }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        
        .branch-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.2);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .page-header-right .btn-outline {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.25);
        }
        .page-header-right .btn-outline:hover {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.4);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        .btn-primary {
            background: var(--primary-gradient);
            color: #ffffff;
            box-shadow: 0 2px 12px rgba(11,94,215,0.25);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(11,94,215,0.35);
        }
        .btn-success {
            background: linear-gradient(135deg, #059669, #10B981);
            color: #ffffff;
            box-shadow: 0 2px 12px rgba(5,150,105,0.25);
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(5,150,105,0.35);
        }
        .btn-outline {
            background: transparent;
            color: var(--gray-600);
            border: 2px solid var(--gray-200);
        }
        .btn-outline:hover {
            background: var(--primary-bg);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        .btn-danger {
            background: linear-gradient(135deg, #DC2626, #EF4444);
            color: #ffffff;
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(220,38,38,0.35);
        }
        .btn-sm {
            padding: 6px 16px;
            font-size: 0.75rem;
            border-radius: 8px;
        }
        
        /* ================================================================
           CARDS - BLUE THEME
           ================================================================ */
        .detail-card {
            background: linear-gradient(135deg, #ffffff 0%, #f0f7ff 100%);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 1px solid var(--primary-light);
            transition: var(--transition);
            margin-bottom: 20px;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }
        .detail-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
            border-radius: 0 0 4px 4px;
        }
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        [data-theme="dark"] .detail-card {
            background: linear-gradient(135deg, var(--gray-800) 0%, #1a2a4a 100%);
            border-color: var(--primary-dark);
        }
        [data-theme="dark"] .detail-card::before {
            background: var(--primary-gradient);
        }
        [data-theme="dark"] .detail-card:hover {
            border-color: var(--primary);
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-dark);
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        [data-theme="dark"] .card-title {
            color: var(--primary-light);
            border-color: var(--primary-dark);
        }
        .card-title i { font-size: 1.1rem; }
        .title-blue { color: var(--primary); }
        .title-green { color: var(--success); }
        .title-purple { color: var(--purple); }
        .title-orange { color: var(--warning); }
        .title-red { color: var(--danger); }
        
        .detail-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid var(--gray-100);
        }
        .detail-row:last-child { border-bottom: none; }
        [data-theme="dark"] .detail-row { border-color: var(--gray-700); }
        
        .detail-label {
            font-weight: 600;
            color: var(--gray-500);
            width: 160px;
            flex-shrink: 0;
            font-size: 0.85rem;
        }
        .detail-value {
            flex: 1;
            color: var(--gray-800);
            font-size: 0.9rem;
        }
        [data-theme="dark"] .detail-value { color: var(--gray-200); }
        
        /* ================================================================
           VITAL SIGNS - 6 CARDS
           ================================================================ */
        .vital-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 14px;
            margin-top: 4px;
        }
        
        .vital-card {
            background: var(--primary-bg);
            border-radius: var(--radius);
            padding: 14px 12px;
            text-align: center;
            border: 2px solid var(--primary-light);
            transition: var(--transition);
        }
        .vital-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        [data-theme="dark"] .vital-card {
            background: #1E3A5F;
            border-color: var(--primary-dark);
        }
        [data-theme="dark"] .vital-card:hover {
            border-color: var(--primary);
        }
        
        .vital-card .vital-icon {
            font-size: 1.4rem;
            color: var(--primary);
            display: block;
            margin-bottom: 4px;
        }
        .vital-card .vital-label {
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-500);
            display: block;
        }
        .vital-card .vital-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--gray-800);
            display: block;
            margin-top: 2px;
        }
        [data-theme="dark"] .vital-card .vital-value {
            color: var(--gray-100);
        }
        .vital-card .vital-unit {
            font-size: 0.65rem;
            color: var(--gray-400);
            font-weight: 400;
        }
        
        /* ================================================================
           TABLE STYLES
           ================================================================ */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--primary-gradient);
            border-bottom: 3px solid var(--primary-dark);
        }
        .data-table tbody td {
            padding: 8px 14px;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
        }
        [data-theme="dark"] .data-table tbody td {
            color: var(--gray-300);
            border-color: var(--gray-700);
        }
        .data-table tbody tr:hover td {
            background: var(--primary-bg);
        }
        [data-theme="dark"] .data-table tbody tr:hover td {
            background: #1E3A5F;
        }
        
        .table-wrap {
            overflow-x: auto;
            margin-top: 8px;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
        }
        [data-theme="dark"] .table-wrap {
            border-color: var(--gray-700);
        }
        
        /* ================================================================
           PATIENT INFO HEADER
           ================================================================ */
        .patient-info-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 16px 20px;
            background: var(--primary-bg);
            border-radius: var(--radius);
            margin-bottom: 16px;
            border: 2px solid var(--primary-light);
        }
        [data-theme="dark"] .patient-info-header {
            background: #1E3A5F;
            border-color: var(--primary-dark);
        }
        
        .patient-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }
        
        .row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        
        /* ================================================================
           PDF MODAL - BEAUTIFUL DESIGN
           ================================================================ */
        .pdf-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            backdrop-filter: blur(8px);
        }
        .pdf-modal-overlay.active { display: flex; align-items: center; justify-content: center; }
        
        .pdf-modal {
            background: white;
            border-radius: var(--radius-lg);
            width: 95%;
            max-width: 1200px;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        [data-theme="dark"] .pdf-modal { background: var(--gray-800); }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .pdf-modal-header {
            padding: 16px 24px;
            border-bottom: 2px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: var(--primary-gradient);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        [data-theme="dark"] .pdf-modal-header { 
            border-color: var(--gray-700);
            background: var(--primary-gradient);
        }
        
        .pdf-modal-header .modal-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .pdf-modal-header .modal-actions {
            display: flex;
            gap: 10px;
        }
        
        .pdf-modal-header .modal-actions .btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.25);
        }
        .pdf-modal-header .modal-actions .btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        .pdf-modal-header .modal-actions .btn-danger {
            background: rgba(220,38,38,0.4);
            border-color: rgba(220,38,38,0.3);
        }
        .pdf-modal-header .modal-actions .btn-danger:hover {
            background: rgba(220,38,38,0.6);
        }
        
        .pdf-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 24px 32px;
            background: #f8fafc;
        }
        [data-theme="dark"] .pdf-modal-body { 
            background: var(--gray-800);
        }
        
        .pdf-modal-body .pdf-content {
            max-width: 100%;
            font-size: 0.85rem;
            color: var(--gray-800);
            background: white;
            padding: 32px 40px;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
        }
        [data-theme="dark"] .pdf-modal-body .pdf-content { 
            color: var(--gray-200);
            background: var(--gray-700);
        }
        
        /* PDF Header with Logo */
        .pdf-content .pdf-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 3px solid var(--primary);
            margin-bottom: 24px;
            position: relative;
        }
        
        .pdf-content .pdf-header .pdf-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-bottom: 6px;
        }
        .pdf-content .pdf-header .pdf-logo img {
            height: 60px;
            width: auto;
            object-fit: contain;
        }
        .pdf-content .pdf-header .pdf-logo .clinic-name {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.5px;
        }
        .pdf-content .pdf-header .clinic-sub {
            font-size: 0.85rem;
            color: var(--gray-500);
            letter-spacing: 1px;
        }
        .pdf-content .pdf-header .visit-number {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
            margin-top: 6px;
            background: var(--primary-bg);
            padding: 4px 16px;
            border-radius: 20px;
            display: inline-block;
        }
        [data-theme="dark"] .pdf-content .pdf-header .visit-number {
            background: #1E3A5F;
        }
        
        .pdf-content .section-title {
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--primary);
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 6px;
            margin: 20px 0 12px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        [data-theme="dark"] .pdf-content .section-title {
            border-color: var(--primary-dark);
        }
        
        .pdf-content .pdf-row {
            display: flex;
            padding: 4px 0;
            border-bottom: 1px solid var(--gray-100);
        }
        [data-theme="dark"] .pdf-content .pdf-row {
            border-color: var(--gray-600);
        }
        .pdf-content .pdf-row .pdf-label {
            font-weight: 600;
            color: var(--gray-500);
            width: 150px;
            flex-shrink: 0;
        }
        .pdf-content .pdf-row .pdf-value {
            flex: 1;
            color: var(--gray-800);
        }
        [data-theme="dark"] .pdf-content .pdf-row .pdf-value {
            color: var(--gray-200);
        }
        
        /* PDF Vital Signs Grid */
        .pdf-content .pdf-vital-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin: 10px 0 14px 0;
        }
        .pdf-content .pdf-vital-grid .pdf-vital-card {
            background: var(--primary-bg);
            border-radius: 8px;
            padding: 10px 8px;
            text-align: center;
            border: 1px solid var(--primary-light);
        }
        [data-theme="dark"] .pdf-content .pdf-vital-grid .pdf-vital-card {
            background: #1E3A5F;
            border-color: var(--primary-dark);
        }
        .pdf-content .pdf-vital-grid .pdf-vital-card .vital-label {
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--gray-500);
            display: block;
        }
        .pdf-content .pdf-vital-grid .pdf-vital-card .vital-value {
            font-size: 1rem;
            font-weight: 700;
            color: var(--gray-800);
            display: block;
        }
        [data-theme="dark"] .pdf-content .pdf-vital-grid .pdf-vital-card .vital-value {
            color: var(--gray-100);
        }
        
        /* PDF Tables */
        .pdf-content .pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            margin: 8px 0;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--gray-200);
        }
        [data-theme="dark"] .pdf-content .pdf-table {
            border-color: var(--gray-600);
        }
        .pdf-content .pdf-table thead th {
            text-align: left;
            padding: 6px 10px;
            font-weight: 700;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--primary-gradient);
            border-bottom: 2px solid var(--primary-dark);
        }
        .pdf-content .pdf-table tbody td {
            padding: 5px 10px;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
        }
        [data-theme="dark"] .pdf-content .pdf-table tbody td {
            color: var(--gray-300);
            border-color: var(--gray-600);
        }
        .pdf-content .pdf-table tbody tr:nth-child(even) td {
            background: var(--gray-50);
        }
        [data-theme="dark"] .pdf-content .pdf-table tbody tr:nth-child(even) td {
            background: var(--gray-600);
        }
        
        .pdf-content .pdf-footer {
            text-align: center;
            padding-top: 20px;
            border-top: 2px solid var(--gray-200);
            margin-top: 24px;
            font-size: 0.7rem;
            color: var(--gray-400);
        }
        [data-theme="dark"] .pdf-content .pdf-footer {
            border-color: var(--gray-600);
        }
        
        .pdf-content .pdf-footer .footer-brand {
            color: var(--primary);
            font-weight: 700;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1200px) {
            .vital-grid { grid-template-columns: repeat(3, 1fr); }
            .pdf-content .pdf-vital-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 12px; }
            .row-2col { grid-template-columns: 1fr; }
            .vital-grid { grid-template-columns: repeat(2, 1fr); }
            .page-header { flex-direction: column; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; margin-bottom: 2px; }
            .pdf-modal { width: 100%; max-height: 100vh; border-radius: 0; }
            .pdf-modal-header { flex-direction: column; gap: 10px; align-items: stretch; }
            .pdf-modal-header .modal-actions { justify-content: center; flex-wrap: wrap; }
            .pdf-modal-body .pdf-content { padding: 16px; }
            .pdf-content .pdf-vital-grid { grid-template-columns: repeat(2, 1fr); }
            .pdf-content .pdf-row { flex-direction: column; }
            .pdf-content .pdf-row .pdf-label { width: 100%; }
        }
        @media (max-width: 480px) {
            .page-title { font-size: 1.1rem; }
            .detail-card { padding: 12px 16px; }
            .btn { font-size: 0.75rem; padding: 6px 12px; }
            .vital-grid { grid-template-columns: 1fr 1fr; }
            .vital-card .vital-value { font-size: 1rem; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">
                <i class="fas fa-file-medical-alt"></i> Visit Details
                <span class="page-badge"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span>
                <span class="status-badge <?= getStatusBadgeClass($visit['status'] ?? 'pending') ?>">
                    <?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'Pending')) ?>
                </span>
            </h1>
            <p class="page-subtitle">
                Patient: <strong><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></strong>
                (<?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?>)
                <span class="separator">|</span>
                Doctor: <strong><?= htmlspecialchars($visit['doctor_name'] ?? 'Not Assigned') ?></strong>
                <span class="separator">|</span>
                Date: <strong><?= date('M d, Y h:i A', strtotime($visit['created_at'])) ?></strong>
                <span class="branch-badge"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></span>
                <span class="separator">|</span>
                <span class="branch-badge"><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($doctor_name) ?></span>
            </p>
        </div>
        <div class="page-header-right" style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="my_patients.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="generatePDF()" class="btn btn-primary btn-sm">
                <i class="fas fa-file-pdf"></i> Download PDF
            </button>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 1: PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="card-title"><i class="fas fa-user title-blue"></i> Patient Information</h3>
        <div class="patient-info-header">
            <div class="patient-avatar" style="background: <?= getUserColor($visit['patient_name'] ?? 'Unknown') ?>;">
                <?= strtoupper(substr($visit['patient_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div>
                <h4 style="font-size:1.1rem;font-weight:700;color:var(--gray-800);"><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></h4>
                <p style="font-size:0.8rem;color:var(--gray-500);font-family:monospace;">ID: <?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?></p>
                <p style="font-size:0.85rem;color:var(--gray-500);">
                    <?= htmlspecialchars($visit['gender'] ?? 'N/A') ?> • 
                    <?= calculateAge($visit['date_of_birth'] ?? '') ?> years • 
                    Blood: <?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?>
                </p>
            </div>
        </div>
        
        <div class="row-2col">
            <div>
                <div class="detail-row"><span class="detail-label">Full Name</span><span class="detail-value"><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Patient ID</span><span class="detail-value"><?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Gender</span><span class="detail-value"><?= htmlspecialchars($visit['gender'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Date of Birth</span><span class="detail-value"><?= !empty($visit['date_of_birth']) ? date('M d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?> (<?= calculateAge($visit['date_of_birth'] ?? '') ?> years)</span></div>
            </div>
            <div>
                <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value"><?= htmlspecialchars($visit['email'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Blood Group</span><span class="detail-value"><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Allergies</span><span class="detail-value"><?= htmlspecialchars($visit['allergies'] ?? 'None') ?></span></div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 2: VITAL SIGNS - 6 CARDS -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="card-title"><i class="fas fa-heartbeat title-red"></i> Vital Signs</h3>
        <?php if ($vital_signs): ?>
            <div class="vital-grid">
                <div class="vital-card">
                    <span class="vital-icon"><i class="fas fa-thermometer-half"></i></span>
                    <span class="vital-label">Temperature</span>
                    <span class="vital-value"><?= htmlspecialchars($vital_signs['temperature'] ?? 'N/A') ?> <span class="vital-unit">°C</span></span>
                </div>
                <div class="vital-card">
                    <span class="vital-icon"><i class="fas fa-heart"></i></span>
                    <span class="vital-label">Blood Pressure</span>
                    <span class="vital-value"><?= htmlspecialchars($vital_signs['blood_pressure'] ?? 'N/A') ?> <span class="vital-unit">mmHg</span></span>
                </div>
                <div class="vital-card">
                    <span class="vital-icon"><i class="fas fa-pulse"></i></span>
                    <span class="vital-label">Pulse Rate</span>
                    <span class="vital-value"><?= htmlspecialchars($vital_signs['pulse_rate'] ?? 'N/A') ?> <span class="vital-unit">bpm</span></span>
                </div>
                <div class="vital-card">
                    <span class="vital-icon"><i class="fas fa-weight"></i></span>
                    <span class="vital-label">Weight</span>
                    <span class="vital-value"><?= htmlspecialchars($vital_signs['weight'] ?? 'N/A') ?> <span class="vital-unit">kg</span></span>
                </div>
                <div class="vital-card">
                    <span class="vital-icon"><i class="fas fa-ruler-vertical"></i></span>
                    <span class="vital-label">Height</span>
                    <span class="vital-value"><?= htmlspecialchars($vital_signs['height'] ?? 'N/A') ?> <span class="vital-unit">cm</span></span>
                </div>
                <div class="vital-card">
                    <span class="vital-icon"><i class="fas fa-calculator"></i></span>
                    <span class="vital-label">BMI</span>
                    <span class="vital-value"><?= htmlspecialchars($vital_signs['bmi'] ?? 'N/A') ?> <span class="vital-unit">kg/m²</span></span>
                </div>
            </div>
            <?php if (!empty($vital_signs['recorded_at'])): ?>
                <div style="text-align:right;font-size:0.7rem;color:var(--gray-400);margin-top:10px;">
                    <i class="fas fa-clock"></i> Recorded: <?= date('M d, Y h:i A', strtotime($vital_signs['recorded_at'])) ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-gray-400">No vital signs recorded</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 3: SYMPTOMS -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="card-title"><i class="fas fa-list-ul title-blue"></i> Symptoms</h3>
        <div class="detail-row">
            <span class="detail-label">Description</span>
            <span class="detail-value"><?= nl2br(htmlspecialchars($visit['symptoms'] ?? 'No symptoms recorded')) ?></span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 4: LAB TESTS & RESULTS -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="card-title"><i class="fas fa-flask title-green"></i> Lab Tests & Results</h3>
        <?php if (count($lab_tests) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Price</th>
                            <th>Result</th>
                            <th>Reference Range</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $lab): ?>
                            <tr>
                                <td><?= htmlspecialchars($lab['test_name'] ?? 'N/A') ?></td>
                                <td>TSh <?= number_format($lab['test_price'] ?? 0, 0) ?></td>
                                <td>
                                    <?php if ($lab['status'] === 'completed'): ?>
                                        <span style="font-weight:600;color:var(--success);"><?= htmlspecialchars($lab['results'] ?? 'N/A') ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);">Not yet</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($lab['reference_range'] ?? '') ?></td>
                                <td>
                                    <span class="status-badge <?= getStatusBadgeClass($lab['status'] ?? 'pending') ?>">
                                        <?= ucfirst(str_replace('_', ' ', $lab['status'] ?? 'Pending')) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($lab['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-400">No lab tests performed</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 5: DIAGNOSIS -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="card-title"><i class="fas fa-diagnoses title-blue"></i> Diagnosis</h3>
        <div class="detail-row">
            <span class="detail-label">Diagnosis</span>
            <span class="detail-value"><?= nl2br(htmlspecialchars($visit['diagnosis'] ?? 'No diagnosis recorded')) ?></span>
        </div>
        <?php if (!empty($visit['treatment'])): ?>
            <div class="detail-row">
                <span class="detail-label">Treatment</span>
                <span class="detail-value"><?= nl2br(htmlspecialchars($visit['treatment'] ?? '')) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($visit['notes'])): ?>
            <div class="detail-row">
                <span class="detail-label">Notes</span>
                <span class="detail-value"><?= nl2br(htmlspecialchars($visit['notes'] ?? '')) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 6: MEDICATIONS -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="card-title"><i class="fas fa-prescription title-purple"></i> Medications</h3>
        <?php if (count($prescriptions) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Medication</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Duration</th>
                            <th>Qty</th>
                            <th>Instructions</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $pres): 
                            $names = explode(',', $pres['medication_names'] ?? '');
                            $dosages = explode(',', $pres['dosage_list'] ?? '');
                            $frequencies = explode(',', $pres['frequency_list'] ?? '');
                            $quantities = explode(',', $pres['quantity_list'] ?? '');
                            $count = count($names);
                        ?>
                            <?php for ($i = 0; $i < $count; $i++): ?>
                                <tr>
                                    <td><?= htmlspecialchars(trim($names[$i] ?? 'N/A')) ?></td>
                                    <td><?= htmlspecialchars(trim($dosages[$i] ?? '')) ?></td>
                                    <td><?= htmlspecialchars(trim($frequencies[$i] ?? '')) ?></td>
                                    <td><?= $pres['duration'] ?? '' ?> days</td>
                                    <td><?= trim($quantities[$i] ?? '0') ?></td>
                                    <td><?= htmlspecialchars($pres['instructions'] ?? '') ?></td>
                                    <td>
                                        <span class="status-badge <?= $pres['status'] === 'dispensed' ? 'badge-success' : 'badge-warning' ?>">
                                            <?= $pres['status'] === 'dispensed' ? '✅ Dispensed' : '⏳ Pending' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-400">No medications prescribed</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 7: PROCEDURES -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="card-title"><i class="fas fa-syringe title-blue"></i> Procedures</h3>
        <?php if (count($procedures) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Procedure Name</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($procedures as $proc): ?>
                            <tr>
                                <td><?= htmlspecialchars($proc['item_name'] ?? 'N/A') ?></td>
                                <td><?= $proc['quantity'] ?? 1 ?></td>
                                <td>TSh <?= number_format($proc['unit_price'] ?? 0, 0) ?></td>
                                <td>TSh <?= number_format($proc['total_price'] ?? 0, 0) ?></td>
                                <td>
                                    <span class="status-badge <?= ($proc['is_paid'] ?? 0) == 1 ? 'badge-success' : 'badge-warning' ?>">
                                        <?= ($proc['is_paid'] ?? 0) == 1 ? '✅ Paid' : '⏳ Pending' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-400">No procedures performed</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 8: TOOLS -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="card-title"><i class="fas fa-tools title-orange"></i> Tools</h3>
        <?php if (count($tools) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tool Name</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tools as $tool): ?>
                            <tr>
                                <td><?= htmlspecialchars($tool['item_name'] ?? 'N/A') ?></td>
                                <td><?= $tool['quantity'] ?? 1 ?></td>
                                <td>TSh <?= number_format($tool['unit_price'] ?? 0, 0) ?></td>
                                <td>TSh <?= number_format($tool['total_price'] ?? 0, 0) ?></td>
                                <td>
                                    <span class="status-badge <?= ($tool['is_paid'] ?? 0) == 1 ? 'badge-success' : 'badge-warning' ?>">
                                        <?= ($tool['is_paid'] ?? 0) == 1 ? '✅ Paid' : '⏳ Pending' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-400">No tools used</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 9: BILL INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="card-title"><i class="fas fa-receipt title-green"></i> Bill Information</h3>
        <?php if ($bill): ?>
            <div class="row-2col" style="margin-bottom:16px;">
                <div>
                    <div class="detail-row"><span class="detail-label">Bill Number</span><span class="detail-value"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Total Amount</span><span class="detail-value" style="font-weight:700;color:var(--success);">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></span></div>
                    <div class="detail-row"><span class="detail-label">Paid Amount</span><span class="detail-value" style="font-weight:700;color:var(--success);">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></span></div>
                </div>
                <div>
                    <div class="detail-row"><span class="detail-label">Balance</span><span class="detail-value" style="font-weight:700;color:<?= ($bill['balance'] ?? 0) > 0 ? 'var(--danger)' : 'var(--success)' ?>;">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></span></div>
                    <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value"><span class="status-badge <?= getStatusBadgeClass($bill['status'] ?? 'pending') ?>"><?= ucfirst($bill['status'] ?? 'Pending') ?></span></span></div>
                    <div class="detail-row"><span class="detail-label">Date</span><span class="detail-value"><?= date('M d, Y h:i A', strtotime($bill['created_at'])) ?></span></div>
                </div>
            </div>
            
            <?php if (count($bill_items) > 0): ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Type</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bill_items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></td>
                                    <td><span class="status-badge badge-info"><?= ucfirst($item['item_type'] ?? 'N/A') ?></span></td>
                                    <td><?= $item['quantity'] ?? 1 ?></td>
                                    <td>TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                    <td>TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                    <td>
                                        <span class="status-badge <?= ($item['is_paid'] ?? 0) == 1 ? 'badge-success' : 'badge-warning' ?>">
                                            <?= ($item['is_paid'] ?? 0) == 1 ? '✅ Paid' : '⏳ Pending' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-gray-400">No bill created for this visit</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 10: VISIT HISTORY -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="card-title"><i class="fas fa-history title-blue"></i> Patient Visit History</h3>
        <?php if (count($patient_visits) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Visit #</th>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Branch</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patient_visits as $pv): ?>
                            <tr>
                                <td><?= htmlspecialchars($pv['visit_number'] ?? 'N/A') ?></td>
                                <td><?= date('M d, Y', strtotime($pv['created_at'])) ?></td>
                                <td><?= htmlspecialchars($pv['doctor_name'] ?? 'N/A') ?></td>
                                <td><span class="status-badge <?= getStatusBadgeClass($pv['status'] ?? 'pending') ?>"><?= ucfirst(str_replace('_', ' ', $pv['status'] ?? 'Pending')) ?></span></td>
                                <td><?= htmlspecialchars($pv['branch_name'] ?? 'N/A') ?></td>
                                <td>
                                    <a href="visit_details.php?id=<?= $pv['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-400">No previous visits</p>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer" style="padding:16px 0;border-top:2px solid var(--gray-200);margin-top:24px;text-align:center;font-size:0.7rem;color:var(--gray-500);">
        <p>
            <span style="color:var(--primary);font-weight:600;">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Visit Details
            <span class="text-gray-300 mx-2">|</span>
            <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
            <span class="text-gray-300 mx-2">|</span>
            Dr. <?= htmlspecialchars($doctor_name) ?>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PDF MODAL - BEAUTIFUL DESIGN WITH LOGO -->
<!-- ================================================================ -->
<div class="pdf-modal-overlay" id="pdfModal">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <div class="modal-title">
                <i class="fas fa-file-pdf" style="color:rgba(255,255,255,0.8);"></i>
                PDF Preview - <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
            </div>
            <div class="modal-actions">
                <button onclick="downloadPDF()" class="btn btn-sm">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="window.print()" class="btn btn-sm">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="closePDFModal()" class="btn btn-sm btn-danger">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
        <div class="pdf-modal-body" id="pdfModalBody">
            <div class="pdf-content" id="pdfContent">
                <!-- PDF content will be generated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    // ================================================================
    // GENERATE PDF - BEAUTIFUL DESIGN WITH LOGO
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        
        // Build PDF content
        var html = `
            <div class="pdf-header">
                <div class="pdf-logo">
                    <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" onerror="this.style.display='none'">
                    <span class="clinic-name">Braick Dispensary</span>
                </div>
                <div class="clinic-sub">Quality Healthcare Services • <?= htmlspecialchars($visit['branch_name'] ?? '') ?></div>
                <div class="visit-number">📋 Visit: <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></div>
                <div style="font-size:0.8rem;color:var(--gray-500);margin-top:4px;">
                    Date: <?= date('M d, Y h:i A', strtotime($visit['created_at'])) ?> | 
                    Status: <?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'Pending')) ?>
                </div>
            </div>
            
            <!-- Patient Information -->
            <div class="section-title">👤 Patient Information</div>
            <div class="pdf-row"><span class="pdf-label">Full Name</span><span class="pdf-value"><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></span></div>
            <div class="pdf-row"><span class="pdf-label">Patient ID</span><span class="pdf-value"><?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?></span></div>
            <div class="pdf-row"><span class="pdf-label">Gender</span><span class="pdf-value"><?= htmlspecialchars($visit['gender'] ?? 'N/A') ?></span></div>
            <div class="pdf-row"><span class="pdf-label">Date of Birth</span><span class="pdf-value"><?= !empty($visit['date_of_birth']) ? date('M d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?> (<?= calculateAge($visit['date_of_birth'] ?? '') ?> years)</span></div>
            <div class="pdf-row"><span class="pdf-label">Phone</span><span class="pdf-value"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></span></div>
            <div class="pdf-row"><span class="pdf-label">Blood Group</span><span class="pdf-value"><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></span></div>
            <div class="pdf-row"><span class="pdf-label">Allergies</span><span class="pdf-value"><?= htmlspecialchars($visit['allergies'] ?? 'None') ?></span></div>
            
            <!-- Vital Signs - 6 Cards -->
            <?php if ($vital_signs): ?>
            <div class="section-title">❤️ Vital Signs</div>
            <div class="pdf-vital-grid">
                <div class="pdf-vital-card">
                    <span class="vital-label">Temperature</span>
                    <span class="vital-value"><?= htmlspecialchars($vital_signs['temperature'] ?? 'N/A') ?> °C</span>
                </div>
                <div class="pdf-vital-card">
                    <span class="vital-label">Blood Pressure</span>
                    <span class="vital-value"><?= htmlspecialchars($vital_signs['blood_pressure'] ?? 'N/A') ?> mmHg</span>
                </div>
                <div class="pdf-vital-card">
                    <span class="vital-label">Pulse Rate</span>
                    <span class="vital-value"><?= htmlspecialchars($vital_signs['pulse_rate'] ?? 'N/A') ?> bpm</span>
                </div>
                <div class="pdf-vital-card">
                    <span class="vital-label">Weight</span>
                    <span class="vital-value"><?= htmlspecialchars($vital_signs['weight'] ?? 'N/A') ?> kg</span>
                </div>
                <div class="pdf-vital-card">
                    <span class="vital-label">Height</span>
                    <span class="vital-value"><?= htmlspecialchars($vital_signs['height'] ?? 'N/A') ?> cm</span>
                </div>
                <div class="pdf-vital-card">
                    <span class="vital-label">BMI</span>
                    <span class="vital-value"><?= htmlspecialchars($vital_signs['bmi'] ?? 'N/A') ?> kg/m²</span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Symptoms -->
            <div class="section-title">📋 Symptoms</div>
            <div class="pdf-row"><span class="pdf-label">Description</span><span class="pdf-value"><?= nl2br(htmlspecialchars($visit['symptoms'] ?? 'No symptoms recorded')) ?></span></div>
            
            <!-- Lab Tests -->
            <div class="section-title">🧪 Lab Tests & Results</div>
            <?php if (count($lab_tests) > 0): ?>
            <table class="pdf-table">
                <thead><tr>
                    <th>Test Name</th>
                    <th>Result</th>
                    <th>Reference Range</th>
                    <th>Status</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($lab_tests as $lab): ?>
                    <tr>
                        <td><?= htmlspecialchars($lab['test_name'] ?? 'N/A') ?></td>
                        <td><?= $lab['status'] === 'completed' ? htmlspecialchars($lab['results'] ?? 'N/A') : 'Not yet' ?></td>
                        <td><?= htmlspecialchars($lab['reference_range'] ?? '') ?></td>
                        <td><?= ucfirst(str_replace('_', ' ', $lab['status'] ?? 'Pending')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="pdf-row"><span class="pdf-label">No lab tests performed</span></div>
            <?php endif; ?>
            
            <!-- Diagnosis -->
            <div class="section-title">📝 Diagnosis</div>
            <div class="pdf-row"><span class="pdf-label">Diagnosis</span><span class="pdf-value"><?= nl2br(htmlspecialchars($visit['diagnosis'] ?? 'No diagnosis recorded')) ?></span></div>
            <?php if (!empty($visit['treatment'])): ?>
            <div class="pdf-row"><span class="pdf-label">Treatment</span><span class="pdf-value"><?= nl2br(htmlspecialchars($visit['treatment'] ?? '')) ?></span></div>
            <?php endif; ?>
            
            <!-- Medications -->
            <div class="section-title">💊 Medications</div>
            <?php if (count($prescriptions) > 0): ?>
            <table class="pdf-table">
                <thead><tr>
                    <th>Medication</th>
                    <th>Dosage</th>
                    <th>Frequency</th>
                    <th>Duration</th>
                    <th>Qty</th>
                    <th>Instructions</th>
                    <th>Status</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($prescriptions as $pres): 
                        $names = explode(',', $pres['medication_names'] ?? '');
                        $dosages = explode(',', $pres['dosage_list'] ?? '');
                        $frequencies = explode(',', $pres['frequency_list'] ?? '');
                        $quantities = explode(',', $pres['quantity_list'] ?? '');
                        $count = count($names);
                    ?>
                        <?php for ($i = 0; $i < $count; $i++): ?>
                        <tr>
                            <td><?= htmlspecialchars(trim($names[$i] ?? 'N/A')) ?></td>
                            <td><?= htmlspecialchars(trim($dosages[$i] ?? '')) ?></td>
                            <td><?= htmlspecialchars(trim($frequencies[$i] ?? '')) ?></td>
                            <td><?= $pres['duration'] ?? '' ?> days</td>
                            <td><?= trim($quantities[$i] ?? '0') ?></td>
                            <td><?= htmlspecialchars($pres['instructions'] ?? '') ?></td>
                            <td><?= $pres['status'] === 'dispensed' ? '✅ Dispensed' : '⏳ Pending' ?></td>
                        </tr>
                        <?php endfor; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="pdf-row"><span class="pdf-label">No medications prescribed</span></div>
            <?php endif; ?>
            
            <!-- Procedures -->
            <div class="section-title">💉 Procedures</div>
            <?php if (count($procedures) > 0): ?>
            <table class="pdf-table">
                <thead><tr>
                    <th>Procedure Name</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($procedures as $proc): ?>
                    <tr>
                        <td><?= htmlspecialchars($proc['item_name'] ?? 'N/A') ?></td>
                        <td><?= $proc['quantity'] ?? 1 ?></td>
                        <td>TSh <?= number_format($proc['unit_price'] ?? 0, 0) ?></td>
                        <td>TSh <?= number_format($proc['total_price'] ?? 0, 0) ?></td>
                        <td><?= ($proc['is_paid'] ?? 0) == 1 ? '✅ Paid' : '⏳ Pending' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="pdf-row"><span class="pdf-label">No procedures performed</span></div>
            <?php endif; ?>
            
            <!-- Tools -->
            <div class="section-title">🔧 Tools</div>
            <?php if (count($tools) > 0): ?>
            <table class="pdf-table">
                <thead><tr>
                    <th>Tool Name</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($tools as $tool): ?>
                    <tr>
                        <td><?= htmlspecialchars($tool['item_name'] ?? 'N/A') ?></td>
                        <td><?= $tool['quantity'] ?? 1 ?></td>
                        <td>TSh <?= number_format($tool['unit_price'] ?? 0, 0) ?></td>
                        <td>TSh <?= number_format($tool['total_price'] ?? 0, 0) ?></td>
                        <td><?= ($tool['is_paid'] ?? 0) == 1 ? '✅ Paid' : '⏳ Pending' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="pdf-row"><span class="pdf-label">No tools used</span></div>
            <?php endif; ?>
            
            <!-- Bill Information -->
            <div class="section-title">💰 Bill Information</div>
            <?php if ($bill): ?>
            <div class="pdf-row"><span class="pdf-label">Bill Number</span><span class="pdf-value"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></span></div>
            <div class="pdf-row"><span class="pdf-label">Total Amount</span><span class="pdf-value">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></span></div>
            <div class="pdf-row"><span class="pdf-label">Paid Amount</span><span class="pdf-value">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></span></div>
            <div class="pdf-row"><span class="pdf-label">Balance</span><span class="pdf-value">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></span></div>
            <div class="pdf-row"><span class="pdf-label">Status</span><span class="pdf-value"><?= ucfirst($bill['status'] ?? 'Pending') ?></span></div>
            
            <?php if (count($bill_items) > 0): ?>
            <table class="pdf-table">
                <thead><tr>
                    <th>Item Name</th>
                    <th>Type</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($bill_items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></td>
                        <td><?= ucfirst($item['item_type'] ?? 'N/A') ?></td>
                        <td><?= $item['quantity'] ?? 1 ?></td>
                        <td>TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                        <td>TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                        <td><?= ($item['is_paid'] ?? 0) == 1 ? '✅ Paid' : '⏳ Pending' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <?php else: ?>
            <div class="pdf-row"><span class="pdf-label">No bill created</span></div>
            <?php endif; ?>
            
            <!-- Footer -->
            <div class="pdf-footer">
                <p>
                    <span class="footer-brand">Braick Dispensary</span> Management System
                    <br>
                    Generated on <?= date('M d, Y h:i A') ?> • All rights reserved
                    <br>
                    <span style="font-size:0.65rem;color:var(--gray-400);">
                        This is a computer generated document. No signature required.
                    </span>
                </p>
            </div>
        `;
        
        content.innerHTML = html;
        modal.classList.add('active');
        
        // Fix PDF styling
        setTimeout(function() {
            var tables = document.querySelectorAll('#pdfContent .pdf-table');
            tables.forEach(function(table) {
                table.style.width = '100%';
                table.style.borderCollapse = 'collapse';
                table.style.fontSize = '0.78rem';
            });
        }, 100);
    }
    
    function closePDFModal() {
        document.getElementById('pdfModal').classList.remove('active');
    }
    
    function downloadPDF() {
        var element = document.getElementById('pdfContent');
        var opt = {
            margin: [8, 8, 8, 8],
            filename: 'Visit_<?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2, 
                useCORS: true,
                backgroundColor: '#ffffff'
            },
            jsPDF: { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait' 
            },
            pagebreak: { mode: 'avoid-all' }
        };
        
        html2pdf().set(opt).from(element).save();
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePDFModal();
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            // Allow print
        }
    });

    // ================================================================
    // CLICK OUTSIDE TO CLOSE PDF MODAL
    // ================================================================
    document.getElementById('pdfModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePDFModal();
        }
    });

    console.log('%c📋 Visit Details - Full History', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔐 Session-based login active', 'font-size:13px; color:#34D399;');
    console.log('%c🆔 Visit: <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Status: <?= ucfirst($visit['status'] ?? 'Pending') ?>', 'font-size:13px; color:#D97706;');
    console.log('%c❤️ Vital Signs: 6 (Temp, BP, Pulse, Weight, Height, BMI)', 'font-size:13px; color:#DC2626;');
    console.log('%c🧪 Lab Tests: <?= count($lab_tests) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c💊 Medications: <?= count($prescriptions) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c💰 Bill Total: TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏥 Braick Logo included in PDF', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👨‍⚕️ Doctor: Dr. <?= htmlspecialchars($doctor_name) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>