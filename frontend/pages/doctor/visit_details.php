<?php
// ================================================================
// FILE: frontend/pages/doctor/visit_details.php
// DOCTOR - VISIT DETAILS (FULL HISTORY)
// Shows: Patient Info, Vital Signs (6), Symptoms, Lab Tests, Results,
//        Diagnosis, Medications, Procedures, Tools, Bills
// WITH PDF DOWNLOAD - Beautiful Design with Official Stamp
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
    header('Location: visits.php?error=invalid_visit');
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
        SELECT 
            v.id,
            v.visit_number,
            v.visit_date,
            v.patient_id,
            v.doctor_id,
            v.receptionist_id,
            v.branch_id,
            v.visit_type,
            v.consultation_fee,
            v.status,
            v.symptoms,
            v.hpi,
            v.physical_exam,
            v.complaint,
            v.diagnosis,
            v.disease_id,
            v.disease_code,
            v.treatment,
            v.notes,
            v.follow_up_date,
            v.is_referred,
            v.created_at,
            v.updated_at,
            v.is_completed,
            v.completed_at,
            v.lab_fees_total,
            v.pharmacy_fees_total,
            v.other_fees_total,
            v.visit_total,
            v.payment_status,
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
            p.marital_status,
            p.created_at as patient_registered,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            b.name as branch_name,
            b.location as branch_location,
            b.phone as branch_phone,
            d.disease_name,
            d.disease_code as disease_code_db,
            d.treatment as disease_treatment
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN branches b ON v.branch_id = b.id
        LEFT JOIN diseases d ON v.disease_id = d.id
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
            header('Location: visits.php?error=access_denied');
            exit;
        }
        header('Location: visits.php?error=visit_not_found');
        exit;
    }
} catch (Exception $e) {
    error_log("Visit fetch error: " . $e->getMessage());
    header('Location: visits.php?error=database_error');
    exit;
}

$patient_id = $visit['patient_id'];

// ================================================================
// GET VITAL SIGNS - 6: Temperature, BP, Pulse, Weight, Height, BMI
// ================================================================
$vital_signs = null;
try {
    $stmt = $db->prepare("
        SELECT temperature, blood_pressure_systolic, blood_pressure_diastolic, 
               pulse_rate, weight, height, bmi, respiratory_rate, 
               oxygen_saturation, blood_glucose, muac, pain_score, 
               notes, recorded_at, recorded_by,
               u.full_name as recorded_by_name
        FROM vital_signs vs
        LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE visit_id = ? 
        ORDER BY recorded_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$visit_id]);
    $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $vital_signs = null;
}

// ================================================================
// GET LAB TESTS AND RESULTS
// ================================================================
$lab_tests = [];
try {
    $stmt = $db->prepare("
        SELECT lt.*, 
               tech.full_name as technician_name
        FROM lab_tests lt
        LEFT JOIN users tech ON lt.lab_technician_id = tech.id
        WHERE visit_id = ? 
        ORDER BY lt.created_at DESC
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
               ph.full_name as pharmacy_name,
               GROUP_CONCAT(
                   CONCAT(pi.medication_name, '|', pi.dosage, '|', pi.frequency, '|', pi.quantity, '|', pi.total_price, '|', pi.instructions, '|', pi.unit_price, '|', pi.route, '|', pi.duration, '|', pi.id)
                   SEPARATOR '||'
               ) as items_data
        FROM prescriptions p
        LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
        LEFT JOIN users ph ON p.pharmacy_id = ph.id
        WHERE p.visit_id = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $prescriptions = [];
}

// Process prescription items
foreach ($prescriptions as $key => $pres) {
    $items_data = $pres['items_data'] ?? '';
    $items = [];
    if (!empty($items_data)) {
        $parts = explode('||', $items_data);
        foreach ($parts as $part) {
            if (empty($part)) continue;
            $fields = explode('|', $part);
            if (count($fields) >= 5) {
                $items[] = [
                    'medication_name' => $fields[0] ?? '',
                    'dosage' => $fields[1] ?? '',
                    'frequency' => $fields[2] ?? '',
                    'quantity' => $fields[3] ?? 0,
                    'total_price' => $fields[4] ?? 0,
                    'instructions' => $fields[5] ?? '',
                    'unit_price' => $fields[6] ?? 0,
                    'route' => $fields[7] ?? '',
                    'duration' => $fields[8] ?? '',
                    'item_id' => $fields[9] ?? ''
                ];
            }
        }
    }
    $prescriptions[$key]['items'] = $items;
    unset($prescriptions[$key]['items_data']);
}

// ================================================================
// GET PROCEDURES
// ================================================================
$procedures = [];
try {
    $stmt = $db->prepare("
        SELECT *
        FROM procedures 
        WHERE visit_id = ? AND status != 'cancelled'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $procedures = [];
}

// ================================================================
// GET BILL INFORMATION
// ================================================================
$bill = null;
$bill_items = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM bills 
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
    $bill = null;
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
// GET BRANCH NAME
// ================================================================
$doctor_branch_name = 'Not Assigned';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $doctor_branch_name = $branch_data['name'];
    }
} catch (Exception $e) {
    $doctor_branch_name = 'Branch';
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function calculateAge($dob) {
    if (empty($dob) || $dob === '0000-00-00') return 'N/A';
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
        'lab_completed' => 'badge-info',
        'prescribed' => 'badge-purple',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger',
        'paid' => 'badge-success',
        'partial' => 'badge-warning',
        'in_progress' => 'badge-info'
    ];
    return $map[$status] ?? 'badge-info';
}

function getStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending',
        'assigned' => '👨‍⚕️ Assigned',
        'with_doctor' => '🩺 With Doctor',
        'lab_test' => '🧪 Lab Test',
        'lab_completed' => '✅ Lab Done',
        'prescribed' => '💊 Prescribed',
        'completed' => '✅ Completed',
        'cancelled' => '❌ Cancelled',
        'paid' => '✅ Paid',
        'partial' => '🔄 Partial',
        'in_progress' => '⏳ In Progress'
    ];
    return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777', '#2563EB', '#0891B2'];
    $index = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $index = ($index + ord($name[$i])) % count($colors);
    }
    return $colors[$index];
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visit Details - <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?> - Braick Dispensary</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        /* ================================================================
           ROOT VARIABLES - BLUE THEME
           ================================================================ */
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
            --radius: 10px;
            --radius-lg: 14px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
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
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.4);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
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
        .page-header * { color: white !important; }
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white !important;
            border: 1px solid rgba(255,255,255,0.25);
        }
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.4);
            transform: translateY(-2px);
        }
        .page-header .btn-outline-light.btn-pdf {
            background: rgba(220,38,38,0.25);
            border-color: rgba(220,38,38,0.3);
        }
        .page-header .btn-outline-light.btn-pdf:hover {
            background: rgba(220,38,38,0.4);
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 0;
        }
        .page-title i { color: rgba(255,255,255,0.8) !important; }
        
        .page-badge {
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            color: white !important;
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
            color: rgba(255,255,255,0.9) !important;
        }
        .page-subtitle strong { color: white !important; font-weight: 700; }
        
        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white !important;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .header-badge {
            background: rgba(255,255,255,0.12);
            color: white !important;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
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
        
        /* ================================================================
           DETAIL CARDS
           ================================================================ */
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .card-title i { font-size: 1.1rem; }
        .card-title .title-blue { color: var(--primary); }
        .card-title .title-green { color: var(--success); }
        .card-title .title-purple { color: var(--purple); }
        .card-title .title-orange { color: var(--warning); }
        .card-title .title-red { color: var(--danger); }
        
        .detail-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .detail-row:last-child { border-bottom: none; }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 160px;
            flex-shrink: 0;
            font-size: 0.85rem;
        }
        .detail-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        
        .row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .col-span-2 { grid-column: span 2; }
        
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
        
        .vital-card .vital-icon { font-size: 1.4rem; color: var(--primary); display: block; margin-bottom: 4px; }
        .vital-card .vital-label { font-size: 0.6rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); display: block; }
        .vital-card .vital-value { font-size: 1.2rem; font-weight: 700; color: var(--text-primary); display: block; margin-top: 2px; }
        .vital-card .vital-unit { font-size: 0.65rem; color: var(--text-secondary); font-weight: 400; }
        
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
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        .data-table tbody tr:hover td { background: var(--primary-bg); }
        [data-theme="dark"] .data-table tbody tr:hover td { background: #1E3A5F; }
        
        .table-wrap {
            overflow-x: auto;
            margin-top: 8px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
        }
        
        /* ================================================================
           BILL SUMMARY CARDS
           ================================================================ */
        .bill-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        .bill-item {
            background: var(--bg-body);
            border-radius: var(--radius);
            padding: 12px 16px;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        .bill-item .bill-amount { font-size: 1.2rem; font-weight: 700; }
        .bill-item .bill-amount.total { color: var(--primary); }
        .bill-item .bill-amount.paid { color: var(--success); }
        .bill-item .bill-amount.balance { color: var(--danger); }
        .bill-item .bill-amount.balance.zero { color: var(--success); }
        .bill-item .bill-label { font-size: 0.6rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em; }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
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
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(5,150,105,0.35);
        }
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        .btn-sm { padding: 4px 12px; font-size: 0.7rem; border-radius: 6px; }
        .btn-pdf { background: #DC2626; color: white; }
        .btn-pdf:hover { background: #B91C1C; transform: translateY(-2px); }
        
        /* ================================================================
           PDF MODAL
           ================================================================ */
        .pdf-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
        }
        .pdf-modal-overlay.active { display: flex; }
        
        .pdf-modal {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            width: 95%;
            max-width: 1100px;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .pdf-modal-header {
            padding: 16px 24px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: var(--primary-gradient);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        .pdf-modal-header * { color: white !important; }
        
        .pdf-modal-header .modal-title {
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .pdf-modal-header .modal-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .pdf-modal-header .modal-actions .btn {
            background: rgba(255,255,255,0.15);
            color: white !important;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.78rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        .pdf-modal-header .modal-actions .btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        .pdf-modal-header .modal-actions .btn-danger-modal {
            background: rgba(220,38,38,0.3);
            border-color: rgba(220,38,38,0.2);
        }
        
        .pdf-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 24px 32px;
            background: var(--bg-body);
        }
        
        .pdf-modal-body .pdf-content {
            max-width: 100%;
            font-size: 0.85rem;
            background: var(--bg-card);
            padding: 32px 40px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }
        
        /* PDF Content Styles */
        .pdf-content .pdf-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 3px solid var(--primary);
            margin-bottom: 24px;
        }
        
        .pdf-content .pdf-header .pdf-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-bottom: 6px;
        }
        .pdf-content .pdf-header .pdf-logo img {
            height: 55px;
            width: auto;
            object-fit: contain;
        }
        .pdf-content .pdf-header .clinic-name {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.5px;
        }
        .pdf-content .pdf-header .clinic-sub {
            font-size: 0.8rem;
            color: var(--text-secondary);
            letter-spacing: 0.5px;
        }
        .pdf-content .pdf-header .doc-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary);
            margin-top: 6px;
            background: var(--primary-bg);
            padding: 4px 16px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .pdf-content .section-title {
            font-weight: 700;
            font-size: 1rem;
            color: var(--primary);
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 6px;
            margin: 14px 0 8px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pdf-content .pdf-row {
            display: flex;
            padding: 3px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .pdf-content .pdf-row .pdf-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 160px;
            flex-shrink: 0;
        }
        .pdf-content .pdf-row .pdf-value {
            flex: 1;
            color: var(--text-primary);
        }
        
        .pdf-content .pdf-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px 16px;
        }
        
        .pdf-content .pdf-vital-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            margin: 6px 0;
        }
        @media (max-width: 768px) {
            .pdf-content .pdf-vital-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        .pdf-content .pdf-vital-item {
            background: var(--primary-bg);
            padding: 6px 10px;
            border-radius: 6px;
            border-left: 3px solid var(--primary);
            text-align: center;
        }
        .pdf-content .pdf-vital-item .vital-label {
            font-size: 0.5rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
        }
        .pdf-content .pdf-vital-item .vital-value {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--primary-dark);
        }
        
        .pdf-content .pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            margin: 4px 0;
        }
        .pdf-content .pdf-table th {
            background: var(--primary);
            color: white;
            padding: 4px 10px;
            text-align: left;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
        }
        .pdf-content .pdf-table td {
            padding: 4px 10px;
            border-bottom: 1px solid var(--border-color);
        }
        .pdf-content .pdf-table tr:nth-child(even) td {
            background: var(--gray-50);
        }
        
        .pdf-content .pdf-footer {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 2px solid var(--border-color);
        }
        .pdf-content .pdf-footer .footer-stamp {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .pdf-content .pdf-footer .footer-left { font-size: 0.7rem; color: var(--text-secondary); }
        .pdf-content .pdf-footer .footer-left .signature-line {
            display: inline-block;
            width: 120px;
            border-bottom: 1px solid var(--text-secondary);
            margin-left: 4px;
        }
        .pdf-content .pdf-footer .stamp-box {
            text-align: center;
            padding: 6px 16px;
            border: 3px solid var(--primary);
            border-radius: 10px;
            background: var(--primary-bg);
            min-width: 160px;
        }
        .pdf-content .pdf-footer .stamp-box .stamp-title {
            font-size: 0.5rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        .pdf-content .pdf-footer .stamp-box .stamp-name {
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--primary);
        }
        .pdf-content .pdf-footer .stamp-box .stamp-line {
            font-size: 0.6rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        .pdf-content .pdf-footer .stamp-box .stamp-date {
            font-size: 0.5rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .pdf-content .pdf-footer .footer-bottom {
            text-align: center;
            margin-top: 10px;
            font-size: 0.6rem;
            color: var(--text-muted);
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: var(--radius);
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        
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
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        .text-gray-400 { color: var(--text-secondary); }
        .text-muted { color: var(--text-secondary); }
        .text-xs { font-size: 0.75rem; }
        .text-sm { font-size: 0.85rem; }
        .text-lg { font-size: 1.1rem; }
        .font-medium { font-weight: 500; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
        .capitalize { text-transform: capitalize; }
        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-2 { gap: 0.5rem; }
        .gap-3 { gap: 0.75rem; }
        .gap-4 { gap: 1rem; }
        .mb-3 { margin-bottom: 0.75rem; }
        .mb-4 { margin-bottom: 1rem; }
        .mt-3 { margin-top: 0.75rem; }
        .mt-4 { margin-top: 1rem; }
        .w-full { width: 100%; }
        .text-center { text-align: center; }
        .py-4 { padding-top: 1rem; padding-bottom: 1rem; }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .vital-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .main-content { padding: 12px; }
            .row-2col { grid-template-columns: 1fr; }
            .vital-grid { grid-template-columns: repeat(2, 1fr); }
            .page-header { flex-direction: column; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; margin-bottom: 2px; }
            .bill-summary { grid-template-columns: 1fr; }
            .pdf-modal { width: 100%; max-height: 100vh; border-radius: 0; }
            .pdf-modal-header { flex-direction: column; gap: 10px; align-items: stretch; }
            .pdf-modal-header .modal-actions { justify-content: center; flex-wrap: wrap; }
            .pdf-modal-body .pdf-content { padding: 16px; }
            .pdf-content .pdf-grid-2 { grid-template-columns: 1fr; }
            .pdf-content .pdf-footer .footer-stamp { flex-direction: column; align-items: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-title { font-size: 1.1rem; }
            .detail-card { padding: 12px 16px; }
            .vital-grid { grid-template-columns: 1fr 1fr; }
            .vital-card .vital-value { font-size: 1rem; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
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
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-medical-alt"></i> Visit Details
                <span class="page-badge"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span>
                <span class="role-badge-display">DOCTOR</span>
                <?php if ($visit['status'] === 'completed'): ?>
                    <span class="role-badge-display" style="background:rgba(5,150,105,0.3);border-color:rgba(5,150,105,0.3);color:#34D399;">
                        ✅ Completed
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                Patient: <strong><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></strong>
                (<?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?>)
                <span class="separator">|</span>
                Doctor: <strong><?= htmlspecialchars($visit['doctor_name'] ?? 'Not Assigned') ?></strong>
                <span class="separator">|</span>
                Date: <strong><?= date('M d, Y h:i A', strtotime($visit['created_at'] ?? 'now')) ?></strong>
                <span class="header-badge"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($visit['branch_name'] ?? $doctor_branch_name) ?></span>
                <span class="header-badge"><i class="fas fa-user-md"></i> <?= htmlspecialchars($doctor_name) ?></span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="visits.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="generatePDF()" class="btn-outline-light btn-pdf">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
            <?php if ($visit['status'] !== 'completed' && $visit['status'] !== 'cancelled'): ?>
                <a href="consultation.php?visit_id=<?= $visit['id'] ?>" class="btn-outline-light" style="background:rgba(124,58,237,0.2);border-color:rgba(124,58,237,0.3);">
                    <i class="fas fa-stethoscope"></i> Consult
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($visit): ?>
    
    <!-- ================================================================ -->
    <!-- SECTION 1: VISIT INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <h3 class="card-title"><i class="fas fa-info-circle title-blue"></i> Visit Information</h3>
        <div class="row-2col">
            <div>
                <div class="detail-row"><span class="detail-label">Visit Number</span><span class="detail-value" style="font-family:monospace;font-weight:600;color:var(--primary);"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Visit Type</span><span class="detail-value capitalize"><?= htmlspecialchars($visit['visit_type'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value"><span class="badge-status <?= getStatusBadgeClass($visit['status'] ?? 'pending') ?>"><?= getStatusLabel($visit['status'] ?? 'pending') ?></span></span></div>
                <div class="detail-row"><span class="detail-label">Payment Status</span><span class="detail-value"><span class="badge-status <?= getStatusBadgeClass($visit['payment_status'] ?? 'pending') ?>"><?= getStatusLabel($visit['payment_status'] ?? 'pending') ?></span></span></div>
            </div>
            <div>
                <div class="detail-row"><span class="detail-label">Visit Date</span><span class="detail-value"><?= date('M d, Y h:i A', strtotime($visit['visit_date'] ?? $visit['created_at'] ?? 'now')) ?></span></div>
                <div class="detail-row"><span class="detail-label">Branch</span><span class="detail-value"><?= htmlspecialchars($visit['branch_name'] ?? $doctor_branch_name) ?></span></div>
                <div class="detail-row"><span class="detail-label">Consultation Fee</span><span class="detail-value" style="font-weight:600;color:var(--success);">TSh <?= number_format($visit['consultation_fee'] ?? 0, 0) ?></span></div>
                <?php if ($visit['completed_at']): ?>
                    <div class="detail-row"><span class="detail-label">Completed At</span><span class="detail-value"><?= date('M d, Y h:i A', strtotime($visit['completed_at'])) ?></span></div>
                <?php endif; ?>
            </div>
            <?php if (!empty($visit['symptoms'])): ?>
                <div class="col-span-2">
                    <div class="detail-row"><span class="detail-label">Symptoms</span><span class="detail-value"><?= nl2br(htmlspecialchars($visit['symptoms'])) ?></span></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['complaint'])): ?>
                <div class="col-span-2">
                    <div class="detail-row"><span class="detail-label">Complaint</span><span class="detail-value"><?= nl2br(htmlspecialchars($visit['complaint'])) ?></span></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['notes'])): ?>
                <div class="col-span-2">
                    <div class="detail-row"><span class="detail-label">Notes</span><span class="detail-value"><?= nl2br(htmlspecialchars($visit['notes'])) ?></span></div>
                </div>
            <?php endif; ?>
            <?php if ($visit['follow_up_date']): ?>
                <div class="col-span-2">
                    <div class="detail-row"><span class="detail-label">Follow-up Date</span><span class="detail-value"><?= date('M d, Y', strtotime($visit['follow_up_date'])) ?></span></div>
                </div>
            <?php endif; ?>
            <?php if ($visit['is_referred']): ?>
                <div class="col-span-2">
                    <div class="detail-row"><span class="detail-label">Referred</span><span class="detail-value" style="color:var(--warning);">✅ Yes</span></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 2: PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <h3 class="card-title"><i class="fas fa-user title-blue"></i> Patient Information</h3>
        <div class="patient-info-header">
            <div class="patient-avatar" style="background: <?= getUserColor($visit['patient_name'] ?? 'Unknown') ?>;">
                <?= strtoupper(substr($visit['patient_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div>
                <h4 style="font-size:1.1rem;font-weight:700;color:var(--text-primary);"><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></h4>
                <p style="font-size:0.8rem;color:var(--text-secondary);font-family:monospace;">ID: <?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?></p>
                <p style="font-size:0.85rem;color:var(--text-secondary);">
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
                <div class="detail-row"><span class="detail-label">Marital Status</span><span class="detail-value"><?= htmlspecialchars($visit['marital_status'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Date of Birth</span><span class="detail-value"><?= !empty($visit['date_of_birth']) ? date('M d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?> (<?= calculateAge($visit['date_of_birth'] ?? '') ?> years)</span></div>
            </div>
            <div>
                <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value"><?= htmlspecialchars($visit['email'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Blood Group</span><span class="detail-value"><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Allergies</span><span class="detail-value" style="color:<?= !empty($visit['allergies']) ? 'var(--danger)' : 'var(--text-secondary)' ?>;"><?= htmlspecialchars($visit['allergies'] ?? 'None') ?></span></div>
                <div class="detail-row"><span class="detail-label">Emergency Contact</span><span class="detail-value"><?= htmlspecialchars($visit['emergency_contact'] ?? 'N/A') ?></span></div>
            </div>
            <div class="col-span-2">
                <div class="detail-row"><span class="detail-label">Address</span><span class="detail-value"><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></span></div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 3: DOCTOR INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <h3 class="card-title"><i class="fas fa-user-md title-green"></i> Doctor Information</h3>
        <?php if ($visit['doctor_id']): ?>
            <div class="flex items-center gap-4 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-lg" style="background: <?= getUserColor($visit['doctor_name']) ?>;">
                        <?= strtoupper(substr($visit['doctor_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800 text-lg">Dr. <?= htmlspecialchars($visit['doctor_name']) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($visit['doctor_specialty'] ?? 'General Practitioner') ?></p>
                    </div>
                </div>
                <?php if (!empty($visit['consultation_fee']) && $visit['consultation_fee'] > 0): ?>
                    <span class="text-sm text-gray-500">
                        <i class="fas fa-money-bill-wave mr-1"></i> Fee: TSh <?= number_format($visit['consultation_fee'], 0) ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-400">No doctor assigned to this visit</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 4: VITAL SIGNS - 6 CARDS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
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
                    <span class="vital-value">
                        <?php if (!empty($vital_signs['blood_pressure_systolic']) && !empty($vital_signs['blood_pressure_diastolic'])): ?>
                            <?= $vital_signs['blood_pressure_systolic'] ?>/<?= $vital_signs['blood_pressure_diastolic'] ?> <span class="vital-unit">mmHg</span>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </span>
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
            <?php if (!empty($vital_signs['recorded_by_name'])): ?>
                <div style="text-align:right;font-size:0.7rem;color:var(--text-secondary);margin-top:10px;">
                    <i class="fas fa-user-circle"></i> Recorded by: <?= htmlspecialchars($vital_signs['recorded_by_name']) ?>
                    <?php if (!empty($vital_signs['recorded_at'])): ?>
                        • <?= date('M d, Y h:i A', strtotime($vital_signs['recorded_at'])) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($vital_signs['notes'])): ?>
                <div class="mt-2 text-sm text-gray-500">
                    <i class="fas fa-sticky-note mr-1"></i> Notes: <?= htmlspecialchars($vital_signs['notes']) ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-gray-400">No vital signs recorded</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 5: LAB TESTS & RESULTS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <h3 class="card-title"><i class="fas fa-flask title-purple"></i> Lab Tests & Results</h3>
        <?php if (count($lab_tests) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Price</th>
                            <th>Result</th>
                            <th>Reference Range</th>
                            <th>Technician</th>
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
                                    <?php if ($lab['status'] === 'completed' && !empty($lab['results'])): ?>
                                        <span style="font-weight:600;color:var(--success);"><?= htmlspecialchars($lab['results']) ?></span>
                                    <?php elseif ($lab['status'] === 'completed'): ?>
                                        <span style="color:var(--success);">✅ Completed</span>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($lab['reference_range'] ?? '') ?></td>
                                <td><?= htmlspecialchars($lab['technician_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-status <?= getStatusBadgeClass($lab['status'] ?? 'pending') ?>">
                                        <?= ucfirst(str_replace('_', ' ', $lab['status'] ?? 'Pending')) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($lab['created_at'] ?? 'now')) ?></td>
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
    <!-- SECTION 6: DIAGNOSIS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <h3 class="card-title"><i class="fas fa-diagnoses title-blue"></i> Diagnosis</h3>
        <?php if (!empty($visit['diagnosis'])): ?>
            <div style="padding:12px 16px;background:var(--primary-bg);border-radius:var(--radius);border-left:4px solid var(--primary);margin-bottom:12px;">
                <p style="font-size:1rem;font-weight:600;color:var(--primary-dark);"><?= nl2br(htmlspecialchars($visit['diagnosis'])) ?></p>
                <?php if (!empty($visit['disease_code']) || !empty($visit['disease_code_db'])): ?>
                    <p style="font-size:0.8rem;color:var(--text-secondary);margin-top:4px;">
                        <i class="fas fa-code"></i> Code: <?= htmlspecialchars($visit['disease_code'] ?? $visit['disease_code_db'] ?? 'N/A') ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-400">No diagnosis recorded</p>
        <?php endif; ?>
        
        <?php if (!empty($visit['treatment']) || !empty($visit['disease_treatment'])): ?>
            <div style="padding:12px 16px;background:var(--success-bg);border-radius:var(--radius);border-left:4px solid var(--success);">
                <p style="font-size:0.85rem;font-weight:600;color:var(--success-dark);"><i class="fas fa-prescription"></i> Treatment Plan</p>
                <p style="font-size:0.9rem;color:var(--text-primary);margin-top:4px;"><?= nl2br(htmlspecialchars($visit['treatment'] ?? $visit['disease_treatment'] ?? 'No treatment plan')) ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($visit['hpi']) || !empty($visit['physical_exam'])): ?>
            <div class="mt-3 row-2col">
                <?php if (!empty($visit['hpi'])): ?>
                    <div>
                        <p style="font-size:0.7rem;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;">HPI</p>
                        <p style="font-size:0.85rem;color:var(--text-primary);"><?= nl2br(htmlspecialchars($visit['hpi'])) ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($visit['physical_exam'])): ?>
                    <div>
                        <p style="font-size:0.7rem;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;">Physical Exam</p>
                        <p style="font-size:0.85rem;color:var(--text-primary);"><?= nl2br(htmlspecialchars($visit['physical_exam'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 7: MEDICATIONS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <h3 class="card-title"><i class="fas fa-prescription title-purple"></i> Medications</h3>
        <?php if (count($prescriptions) > 0): ?>
            <?php foreach ($prescriptions as $pres): ?>
                <div style="margin-bottom:12px;padding:12px 16px;background:var(--bg-body);border-radius:var(--radius);border:1px solid var(--border-color);">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div>
                            <span style="font-weight:600;color:var(--text-primary);">#<?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?></span>
                            <?php if (!empty($pres['diagnosis'])): ?>
                                <span style="font-size:0.7rem;color:var(--text-secondary);margin-left:8px;"><?= htmlspecialchars($pres['diagnosis']) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="badge-status <?= getStatusBadgeClass($pres['status'] ?? 'pending') ?>">
                            <?= getStatusLabel($pres['status'] ?? 'pending') ?>
                        </span>
                    </div>
                    <?php if (!empty($pres['pharmacy_name'])): ?>
                        <div style="font-size:0.7rem;color:var(--text-secondary);margin-top:2px;">
                            <i class="fas fa-store"></i> Dispensed by: <?= htmlspecialchars($pres['pharmacy_name']) ?>
                            <?php if (!empty($pres['dispensed_at'])): ?>
                                • <?= date('M d, Y', strtotime($pres['dispensed_at'])) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (count($pres['items'] ?? []) > 0): ?>
                        <div class="table-wrap" style="margin-top:8px;">
                            <table class="data-table" style="font-size:0.75rem;">
                                <thead>
                                    <tr>
                                        <th>Medication</th>
                                        <th>Dosage</th>
                                        <th>Frequency</th>
                                        <th>Duration</th>
                                        <th>Qty</th>
                                        <th>Route</th>
                                        <th>Instructions</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pres['items'] as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($item['dosage'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($item['frequency'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($item['duration'] ?? '') ?> days</td>
                                            <td><?= $item['quantity'] ?? 0 ?></td>
                                            <td><?= htmlspecialchars($item['route'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($item['instructions'] ?? '') ?></td>
                                            <td style="font-weight:600;">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($pres['instructions']) || !empty($pres['notes'])): ?>
                        <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:6px;">
                            <?php if (!empty($pres['instructions'])): ?>
                                <strong>Instructions:</strong> <?= htmlspecialchars($pres['instructions']) ?>
                            <?php endif; ?>
                            <?php if (!empty($pres['notes'])): ?>
                                <span class="ml-2"><strong>Notes:</strong> <?= htmlspecialchars($pres['notes']) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-gray-400">No medications prescribed</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 8: PROCEDURES -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <h3 class="card-title"><i class="fas fa-syringe title-blue"></i> Procedures</h3>
        <?php if (count($procedures) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Procedure Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($procedures as $proc): ?>
                            <tr>
                                <td><?= htmlspecialchars($proc['procedure_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($proc['category'] ?? $proc['procedure_category'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if (($proc['procedure_price'] ?? 0) > 0): ?>
                                        TSh <?= number_format($proc['procedure_price'] ?? 0, 0) ?>
                                    <?php else: ?>
                                        <span style="color:var(--success);font-weight:600;">FREE</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= getStatusBadgeClass($proc['status'] ?? 'pending') ?>">
                                        <?= getStatusLabel($proc['status'] ?? 'pending') ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($proc['created_at'] ?? 'now')) ?></td>
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
    <!-- SECTION 9: BILL INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <h3 class="card-title"><i class="fas fa-receipt title-green"></i> Bill Information</h3>
        <?php if ($bill): ?>
            <div class="bill-summary" style="margin-bottom:16px;">
                <div class="bill-item">
                    <p class="bill-amount total">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></p>
                    <p class="bill-label">Total Amount</p>
                </div>
                <div class="bill-item">
                    <p class="bill-amount paid">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></p>
                    <p class="bill-label">Paid Amount</p>
                </div>
                <div class="bill-item">
                    <p class="bill-amount balance <?= ($bill['balance'] ?? 0) <= 0 ? 'zero' : '' ?>">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></p>
                    <p class="bill-label">Balance</p>
                </div>
            </div>
            
            <div class="row-2col" style="margin-bottom:16px;">
                <div>
                    <div class="detail-row"><span class="detail-label">Bill Number</span><span class="detail-value" style="font-family:monospace;font-weight:600;color:var(--primary);"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value"><span class="badge-status <?= getStatusBadgeClass($bill['status'] ?? 'pending') ?>"><?= getStatusLabel($bill['status'] ?? 'pending') ?></span></span></div>
                </div>
                <div>
                    <div class="detail-row"><span class="detail-label">Payment Method</span><span class="detail-value capitalize"><?= htmlspecialchars($bill['payment_method'] ?? 'N/A') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Date</span><span class="detail-value"><?= date('M d, Y h:i A', strtotime($bill['created_at'] ?? 'now')) ?></span></div>
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
                                    <td><span class="badge-status badge-info"><?= ucfirst($item['item_type'] ?? 'N/A') ?></span></td>
                                    <td><?= $item['quantity'] ?? 1 ?></td>
                                    <td>TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                    <td style="font-weight:600;">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                    <td>
                                        <span class="badge-status <?= $item['status'] === 'paid' ? 'badge-success' : 'badge-warning' ?>">
                                            <?= $item['status'] === 'paid' ? '✅ Paid' : '⏳ Pending' ?>
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
    <div class="detail-card animate-fade-in-up">
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
                                <td style="font-family:monospace;"><?= htmlspecialchars($pv['visit_number'] ?? 'N/A') ?></td>
                                <td><?= date('M d, Y', strtotime($pv['created_at'] ?? 'now')) ?></td>
                                <td><?= htmlspecialchars($pv['doctor_name'] ?? 'N/A') ?></td>
                                <td><span class="badge-status <?= getStatusBadgeClass($pv['status'] ?? 'pending') ?>"><?= getStatusLabel($pv['status'] ?? 'pending') ?></span></td>
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

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
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

    <?php else: ?>
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-clinic-medical text-4xl block mb-3"></i>
            <p class="text-lg">Visit not found</p>
            <a href="visits.php" class="text-primary hover:underline">Back to visits</a>
        </div>
    <?php endif; ?>

</main>

<!-- ================================================================ -->
<!-- PDF MODAL -->
<!-- ================================================================ -->
<div class="pdf-modal-overlay" id="pdfModal">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <div class="modal-title">
                <i class="fas fa-file-pdf" style="color:rgba(255,255,255,0.8);"></i>
                PDF Preview - <?= htmlspecialchars($visit['visit_number'] ?? 'Visit') ?>
            </div>
            <div class="modal-actions">
                <button onclick="downloadPDF()" class="btn btn-sm">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="window.print()" class="btn btn-sm">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="closePDFModal()" class="btn btn-sm btn-danger-modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
        <div class="pdf-modal-body" id="pdfModalBody">
            <div class="pdf-content" id="pdfContent">
                <!-- PDF content generated by JavaScript -->
            </div>
        </div>
    </div>
</div>

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
    // DARK MODE
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
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
    // GENERATE PDF - WITH OFFICIAL STAMP
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        
        // Data from PHP
        var visitData = {
            visit_number: '<?= addslashes($visit['visit_number'] ?? 'N/A') ?>',
            status: '<?= addslashes($visit['status'] ?? 'N/A') ?>',
            visit_type: '<?= addslashes($visit['visit_type'] ?? 'N/A') ?>',
            visit_date: '<?= addslashes($visit['visit_date'] ?? $visit['created_at'] ?? '') ?>',
            branch_name: '<?= addslashes($visit['branch_name'] ?? $doctor_branch_name) ?>',
            symptoms: '<?= addslashes($visit['symptoms'] ?? '') ?>',
            complaint: '<?= addslashes($visit['complaint'] ?? '') ?>',
            notes: '<?= addslashes($visit['notes'] ?? '') ?>',
            diagnosis: '<?= addslashes($visit['diagnosis'] ?? '') ?>',
            disease_code: '<?= addslashes($visit['disease_code'] ?? $visit['disease_code_db'] ?? '') ?>',
            treatment: '<?= addslashes($visit['treatment'] ?? $visit['disease_treatment'] ?? '') ?>',
            hpi: '<?= addslashes($visit['hpi'] ?? '') ?>',
            physical_exam: '<?= addslashes($visit['physical_exam'] ?? '') ?>',
            follow_up_date: '<?= addslashes($visit['follow_up_date'] ?? '') ?>',
            patient_name: '<?= addslashes($visit['patient_name'] ?? 'N/A') ?>',
            patient_code: '<?= addslashes($visit['patient_code'] ?? 'N/A') ?>',
            phone: '<?= addslashes($visit['phone'] ?? 'N/A') ?>',
            email: '<?= addslashes($visit['email'] ?? 'N/A') ?>',
            gender: '<?= addslashes($visit['gender'] ?? 'N/A') ?>',
            marital_status: '<?= addslashes($visit['marital_status'] ?? 'N/A') ?>',
            date_of_birth: '<?= addslashes($visit['date_of_birth'] ?? '') ?>',
            blood_group: '<?= addslashes($visit['blood_group'] ?? 'N/A') ?>',
            address: '<?= addslashes($visit['address'] ?? 'N/A') ?>',
            allergies: '<?= addslashes($visit['allergies'] ?? '') ?>',
            emergency_contact: '<?= addslashes($visit['emergency_contact'] ?? '') ?>',
            doctor_name: '<?= addslashes($visit['doctor_name'] ?? 'Not Assigned') ?>',
            doctor_specialty: '<?= addslashes($visit['doctor_specialty'] ?? 'General Practitioner') ?>',
            consultation_fee: '<?= number_format($visit['consultation_fee'] ?? 0, 0) ?>',
            payment_status: '<?= addslashes($visit['payment_status'] ?? 'pending') ?>'
        };
        
        // Vital signs
        var vitals = <?= $vital_signs ? json_encode($vital_signs) : 'null' ?>;
        
        // Lab tests
        var labTests = <?= json_encode($lab_tests) ?>;
        
        // Prescriptions
        var prescriptions = <?= json_encode($prescriptions) ?>;
        
        // Procedures
        var procedures = <?= json_encode($procedures) ?>;
        
        // Bills
        var bill = <?= $bill ? json_encode($bill) : 'null' ?>;
        var billItems = <?= json_encode($bill_items) ?>;
        
        // Patient visits history
        var patientVisits = <?= json_encode($patient_visits) ?>;
        
        var now = new Date();
        var reportDate = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        var reportTime = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
        
        // Build vitals HTML
        var vitalsHtml = '';
        if (vitals) {
            vitalsHtml = `
                <div class="pdf-vital-grid">
                    <div class="pdf-vital-item">
                        <div class="vital-label">🌡️ Temperature</div>
                        <div class="vital-value">${vitals.temperature || 'N/A'} <span class="vital-unit">°C</span></div>
                    </div>
                    <div class="pdf-vital-item">
                        <div class="vital-label">❤️ Blood Pressure</div>
                        <div class="vital-value">
                            ${vitals.blood_pressure_systolic && vitals.blood_pressure_diastolic ? 
                                vitals.blood_pressure_systolic + ' / ' + vitals.blood_pressure_diastolic + ' <span class="vital-unit">mmHg</span>' : 
                                'N/A'}
                        </div>
                    </div>
                    <div class="pdf-vital-item">
                        <div class="vital-label">💓 Pulse Rate</div>
                        <div class="vital-value">${vitals.pulse_rate || 'N/A'} <span class="vital-unit">bpm</span></div>
                    </div>
                    <div class="pdf-vital-item">
                        <div class="vital-label">⚖️ Weight</div>
                        <div class="vital-value">${vitals.weight || 'N/A'} <span class="vital-unit">kg</span></div>
                    </div>
                    <div class="pdf-vital-item">
                        <div class="vital-label">📏 Height</div>
                        <div class="vital-value">${vitals.height || 'N/A'} <span class="vital-unit">cm</span></div>
                    </div>
                    <div class="pdf-vital-item">
                        <div class="vital-label">📊 BMI</div>
                        <div class="vital-value">${vitals.bmi || 'N/A'} <span class="vital-unit">kg/m²</span></div>
                    </div>
                </div>
                ${vitals.notes ? `<div style="margin-top:4px;font-size:0.7rem;color:var(--text-secondary);"><strong>Notes:</strong> ${vitals.notes}</div>` : ''}
                ${vitals.recorded_by_name ? `<div style="font-size:0.65rem;color:var(--text-secondary);margin-top:4px;"><i class="fas fa-user-circle"></i> Recorded by: ${vitals.recorded_by_name}</div>` : ''}
            `;
        } else {
            vitalsHtml = `<p style="color:var(--text-secondary);">No vital signs recorded</p>`;
        }
        
        // Build lab tests HTML
        var labHtml = '';
        if (labTests && labTests.length > 0) {
            labHtml = `
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Price</th>
                            <th>Result</th>
                            <th>Reference Range</th>
                            <th>Technician</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${labTests.map(function(lt) {
                            var resultText = lt.status === 'completed' && lt.results ? lt.results : (lt.status === 'completed' ? '✅ Completed' : '⏳ Pending');
                            return `
                                <tr>
                                    <td>${lt.test_name || 'N/A'}</td>
                                    <td>TSh ${Number(lt.test_price || 0).toLocaleString()}</td>
                                    <td>${resultText}</td>
                                    <td>${lt.reference_range || ''}</td>
                                    <td>${lt.technician_name || 'N/A'}</td>
                                    <td>${lt.status || 'Pending'}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
        } else {
            labHtml = `<p style="color:var(--text-secondary);">No lab tests found</p>`;
        }
        
        // Build prescriptions HTML
        var presHtml = '';
        if (prescriptions && prescriptions.length > 0) {
            presHtml = prescriptions.map(function(pres) {
                var items = pres.items || [];
                var itemsHtml = items.map(function(item) {
                    return `
                        <tr>
                            <td>${item.medication_name || 'N/A'}</td>
                            <td>${item.dosage || ''}</td>
                            <td>${item.frequency || ''}</td>
                            <td>${item.duration || ''} days</td>
                            <td>${item.quantity || 0}</td>
                            <td>${item.route || ''}</td>
                            <td>${item.instructions || ''}</td>
                            <td>TSh ${Number(item.total_price || 0).toLocaleString()}</td>
                        </tr>
                    `;
                }).join('');
                return `
                    <div style="margin-bottom:8px;padding:8px 12px;background:var(--gray-50);border-radius:6px;border:1px solid var(--border-color);">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:4px;">
                            <strong>#${pres.prescription_number || 'N/A'}</strong>
                            <span style="font-size:0.65rem;padding:2px 10px;border-radius:12px;background:${pres.status === 'dispensed' ? '#D1FAE5' : '#FEF3C7'};color:${pres.status === 'dispensed' ? '#059669' : '#D97706'};">${pres.status || 'Pending'}</span>
                        </div>
                        ${pres.diagnosis ? `<div style="font-size:0.7rem;color:var(--text-secondary);"><strong>Diagnosis:</strong> ${pres.diagnosis}</div>` : ''}
                        ${pres.pharmacy_name ? `<div style="font-size:0.65rem;color:var(--text-secondary);"><i class="fas fa-store"></i> Dispensed by: ${pres.pharmacy_name}</div>` : ''}
                        ${items.length > 0 ? `
                            <table class="pdf-table" style="margin-top:4px;font-size:0.7rem;">
                                <thead>
                                    <tr>
                                        <th>Medication</th>
                                        <th>Dosage</th>
                                        <th>Frequency</th>
                                        <th>Duration</th>
                                        <th>Qty</th>
                                        <th>Route</th>
                                        <th>Instructions</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>${itemsHtml}</tbody>
                            </table>
                        ` : ''}
                        ${pres.instructions ? `<div style="font-size:0.7rem;color:var(--text-secondary);margin-top:4px;"><strong>Instructions:</strong> ${pres.instructions}</div>` : ''}
                    </div>
                `;
            }).join('');
        } else {
            presHtml = `<p style="color:var(--text-secondary);">No medications prescribed</p>`;
        }
        
        // Build procedures HTML
        var procHtml = '';
        if (procedures && procedures.length > 0) {
            procHtml = `
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th>Procedure Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${procedures.map(function(p) {
                            var priceText = (p.procedure_price || 0) > 0 ? 'TSh ' + Number(p.procedure_price || 0).toLocaleString() : 'FREE';
                            return `
                                <tr>
                                    <td>${p.procedure_name || 'N/A'}</td>
                                    <td>${p.category || p.procedure_category || 'N/A'}</td>
                                    <td>${priceText}</td>
                                    <td>${p.status || 'Pending'}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
        } else {
            procHtml = `<p style="color:var(--text-secondary);">No procedures performed</p>`;
        }
        
        // Build bill HTML
        var billHtml = '';
        if (bill) {
            var totalAmount = Number(bill.total_amount || 0);
            var paidAmount = Number(bill.paid_amount || 0);
            var balanceAmount = Number(bill.balance || 0);
            var statusClass = balanceAmount <= 0 ? 'paid' : 'partial';
            var statusLabel = balanceAmount <= 0 ? '✅ Paid' : '⏳ Partial / Pending';
            var balanceColor = balanceAmount <= 0 ? '#059669' : '#DC2626';
            
            billHtml = `
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:6px 0;">
                    <div style="background:var(--gray-50);padding:8px 12px;border-radius:6px;text-align:center;border:1px solid var(--border-color);">
                        <div style="font-size:1.1rem;font-weight:700;color:var(--primary);">TSh ${totalAmount.toLocaleString()}</div>
                        <div style="font-size:0.55rem;color:var(--text-secondary);text-transform:uppercase;">Total</div>
                    </div>
                    <div style="background:var(--gray-50);padding:8px 12px;border-radius:6px;text-align:center;border:1px solid var(--border-color);">
                        <div style="font-size:1.1rem;font-weight:700;color:#059669;">TSh ${paidAmount.toLocaleString()}</div>
                        <div style="font-size:0.55rem;color:var(--text-secondary);text-transform:uppercase;">Paid</div>
                    </div>
                    <div style="background:var(--gray-50);padding:8px 12px;border-radius:6px;text-align:center;border:1px solid var(--border-color);">
                        <div style="font-size:1.1rem;font-weight:700;color:${balanceColor};">TSh ${balanceAmount.toLocaleString()}</div>
                        <div style="font-size:0.55rem;color:var(--text-secondary);text-transform:uppercase;">Balance</div>
                    </div>
                </div>
                <div style="display:flex;gap:16px;flex-wrap:wrap;margin:4px 0;">
                    <div style="font-size:0.7rem;"><strong>Bill #:</strong> ${bill.bill_number || 'N/A'}</div>
                    <div style="font-size:0.7rem;"><strong>Status:</strong> <span style="padding:2px 12px;border-radius:12px;background:${balanceAmount <= 0 ? '#D1FAE5' : '#FEF3C7'};color:${balanceAmount <= 0 ? '#059669' : '#D97706'};">${statusLabel}</span></div>
                    <div style="font-size:0.7rem;"><strong>Method:</strong> ${bill.payment_method || 'N/A'}</div>
                    <div style="font-size:0.7rem;"><strong>Date:</strong> ${new Date(bill.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })}</div>
                </div>
            `;
            
            if (billItems && billItems.length > 0) {
                billHtml += `
                    <table class="pdf-table" style="margin-top:6px;">
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
                            ${billItems.map(function(item) {
                                return `
                                    <tr>
                                        <td>${item.item_name || 'N/A'}</td>
                                        <td>${item.item_type || 'N/A'}</td>
                                        <td>${item.quantity || 1}</td>
                                        <td>TSh ${Number(item.unit_price || 0).toLocaleString()}</td>
                                        <td>TSh ${Number(item.total_price || 0).toLocaleString()}</td>
                                        <td>${item.status === 'paid' ? '✅ Paid' : '⏳ Pending'}</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                `;
            }
        } else {
            billHtml = `<p style="color:var(--text-secondary);">No bill created</p>`;
        }
        
        // Build patient visits history HTML
        var historyHtml = '';
        if (patientVisits && patientVisits.length > 0) {
            historyHtml = `
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th>Visit #</th>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Branch</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${patientVisits.map(function(pv) {
                            return `
                                <tr>
                                    <td>${pv.visit_number || 'N/A'}</td>
                                    <td>${new Date(pv.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                                    <td>${pv.doctor_name || 'N/A'}</td>
                                    <td>${pv.status || 'N/A'}</td>
                                    <td>${pv.branch_name || 'N/A'}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
        } else {
            historyHtml = `<p style="color:var(--text-secondary);">No previous visits</p>`;
        }
        
        var html = `
            <div class="pdf-header">
                <div class="pdf-logo">
                    <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" onerror="this.style.display='none'">
                    <span class="clinic-name">BRAICK DISPENSARY</span>
                </div>
                <div class="clinic-sub">Quality Healthcare Services • ${visitData.branch_name}</div>
                <div class="doc-title">📋 Visit Details Report</div>
                <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:4px;">
                    Report Generated: ${reportDate} • ${reportTime}
                </div>
            </div>
            
            <!-- 1. Visit Information -->
            <div class="section-title">📋 Visit Information</div>
            <div class="pdf-grid-2">
                <div class="pdf-row"><span class="pdf-label">Visit Number</span><span class="pdf-value"><strong>${visitData.visit_number}</strong></span></div>
                <div class="pdf-row"><span class="pdf-label">Status</span><span class="pdf-value"><span style="padding:2px 12px;border-radius:12px;background:${visitData.status === 'completed' ? '#D1FAE5' : '#FEF3C7'};color:${visitData.status === 'completed' ? '#059669' : '#D97706'};font-size:0.7rem;font-weight:600;">${visitData.status}</span></span></div>
                <div class="pdf-row"><span class="pdf-label">Visit Type</span><span class="pdf-value">${visitData.visit_type}</span></div>
                <div class="pdf-row"><span class="pdf-label">Payment Status</span><span class="pdf-value">${visitData.payment_status}</span></div>
                <div class="pdf-row"><span class="pdf-label">Date & Time</span><span class="pdf-value">${visitData.visit_date ? new Date(visitData.visit_date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) + ' • ' + new Date(visitData.visit_date).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) : 'N/A'}</span></div>
                <div class="pdf-row"><span class="pdf-label">Consultation Fee</span><span class="pdf-value">TSh ${visitData.consultation_fee}</span></div>
                <div class="pdf-row" style="grid-column: 1 / -1;"><span class="pdf-label">Branch</span><span class="pdf-value">${visitData.branch_name}</span></div>
                ${visitData.symptoms ? `<div class="pdf-row" style="grid-column: 1 / -1;"><span class="pdf-label">Symptoms</span><span class="pdf-value">${visitData.symptoms}</span></div>` : ''}
                ${visitData.complaint ? `<div class="pdf-row" style="grid-column: 1 / -1;"><span class="pdf-label">Complaint</span><span class="pdf-value">${visitData.complaint}</span></div>` : ''}
                ${visitData.notes ? `<div class="pdf-row" style="grid-column: 1 / -1;"><span class="pdf-label">Notes</span><span class="pdf-value">${visitData.notes}</span></div>` : ''}
                ${visitData.follow_up_date ? `<div class="pdf-row" style="grid-column: 1 / -1;"><span class="pdf-label">Follow-up Date</span><span class="pdf-value">${new Date(visitData.follow_up_date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })}</span></div>` : ''}
            </div>
            
            <!-- 2. Patient Information -->
            <div class="section-title">👤 Patient Information</div>
            <div class="pdf-grid-2">
                <div class="pdf-row"><span class="pdf-label">Full Name</span><span class="pdf-value"><strong>${visitData.patient_name}</strong></span></div>
                <div class="pdf-row"><span class="pdf-label">Patient ID</span><span class="pdf-value">${visitData.patient_code}</span></div>
                <div class="pdf-row"><span class="pdf-label">Phone</span><span class="pdf-value">${visitData.phone}</span></div>
                <div class="pdf-row"><span class="pdf-label">Email</span><span class="pdf-value">${visitData.email}</span></div>
                <div class="pdf-row"><span class="pdf-label">Gender</span><span class="pdf-value">${visitData.gender}</span></div>
                <div class="pdf-row"><span class="pdf-label">Marital Status</span><span class="pdf-value">${visitData.marital_status}</span></div>
                <div class="pdf-row"><span class="pdf-label">Date of Birth</span><span class="pdf-value">${visitData.date_of_birth ? new Date(visitData.date_of_birth).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A'}</span></div>
                <div class="pdf-row"><span class="pdf-label">Blood Group</span><span class="pdf-value">${visitData.blood_group}</span></div>
                <div class="pdf-row" style="grid-column: 1 / -1;"><span class="pdf-label">Address</span><span class="pdf-value">${visitData.address}</span></div>
                ${visitData.allergies ? `<div class="pdf-row" style="grid-column: 1 / -1;"><span class="pdf-label">Allergies</span><span class="pdf-value" style="color:#DC2626;">${visitData.allergies}</span></div>` : ''}
                ${visitData.emergency_contact ? `<div class="pdf-row" style="grid-column: 1 / -1;"><span class="pdf-label">Emergency Contact</span><span class="pdf-value">${visitData.emergency_contact}</span></div>` : ''}
            </div>
            
            <!-- 3. Doctor Information -->
            <div class="section-title">👨‍⚕️ Doctor Information</div>
            <div class="pdf-grid-2">
                <div class="pdf-row"><span class="pdf-label">Doctor Name</span><span class="pdf-value"><strong>Dr. ${visitData.doctor_name}</strong></span></div>
                <div class="pdf-row"><span class="pdf-label">Specialty</span><span class="pdf-value">${visitData.doctor_specialty}</span></div>
                <div class="pdf-row"><span class="pdf-label">Consultation Fee</span><span class="pdf-value">TSh ${visitData.consultation_fee}</span></div>
            </div>
            
            <!-- 4. Vital Signs -->
            <div class="section-title">❤️ Vital Signs</div>
            ${vitalsHtml}
            
            <!-- 5. Lab Tests -->
            <div class="section-title">🧪 Lab Tests</div>
            ${labHtml}
            
            <!-- 6. Diagnosis -->
            ${visitData.diagnosis ? `
                <div class="section-title">📋 Diagnosis</div>
                <div style="padding:8px 12px;background:var(--primary-bg);border-radius:6px;border-left:4px solid var(--primary);margin:4px 0;">
                    <div style="font-size:0.85rem;font-weight:600;color:var(--primary-dark);">${visitData.diagnosis}</div>
                    ${visitData.disease_code ? `<div style="font-size:0.7rem;color:var(--text-secondary);"><i class="fas fa-code"></i> Code: ${visitData.disease_code}</div>` : ''}
                    ${visitData.treatment ? `<div style="margin-top:4px;font-size:0.8rem;color:var(--text-secondary);"><strong>Treatment:</strong> ${visitData.treatment}</div>` : ''}
                </div>
                ${visitData.hpi ? `<div style="font-size:0.8rem;color:var(--text-secondary);margin-top:4px;"><strong>HPI:</strong> ${visitData.hpi}</div>` : ''}
                ${visitData.physical_exam ? `<div style="font-size:0.8rem;color:var(--text-secondary);margin-top:4px;"><strong>Physical Exam:</strong> ${visitData.physical_exam}</div>` : ''}
            ` : ''}
            
            <!-- 7. Medications -->
            <div class="section-title">💊 Medications</div>
            ${presHtml}
            
            <!-- 8. Procedures -->
            ${procedures && procedures.length > 0 ? `
                <div class="section-title">💉 Procedures</div>
                ${procHtml}
            ` : ''}
            
            <!-- 9. Bill Summary -->
            <div class="section-title">💰 Bill Summary</div>
            ${billHtml}
            
            <!-- 10. Patient Visit History -->
            <div class="section-title">📋 Patient Visit History</div>
            ${historyHtml}
            
            <!-- Footer with Official Stamp -->
            <div class="pdf-footer">
                <div class="footer-stamp">
                    <div class="footer-left">
                        <span>Technician: _________________</span>
                        <span style="margin-left:20px;">Date: ${reportDate}</span>
                    </div>
                    <div class="stamp-box">
                        <div class="stamp-title">Official Stamp</div>
                        <div class="stamp-name">BRAICK DISPENSARY</div>
                        <div class="stamp-line">Approved By: _________________</div>
                        <div class="stamp-date">Date: ${reportDate}</div>
                    </div>
                </div>
                <div class="footer-bottom">
                    <span class="footer-brand">Braick Dispensary</span> • 
                    Generated on ${reportDate} at ${reportTime} • 
                    All rights reserved
                </div>
            </div>
        `;
        
        content.innerHTML = html;
        modal.classList.add('active');
    }
    
    function closePDFModal() {
        document.getElementById('pdfModal').classList.remove('active');
    }
    
    function downloadPDF() {
        var element = document.getElementById('pdfContent');
        var opt = {
            margin: [8, 8, 8, 8],
            filename: 'Visit_<?= htmlspecialchars($visit['visit_number'] ?? 'visit') ?>_<?= $visit['id'] ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2, 
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false
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
    });

    // ================================================================
    // CLICK OUTSIDE TO CLOSE PDF MODAL
    // ================================================================
    document.getElementById('pdfModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePDFModal();
        }
    });

    console.log('%c📋 Visit Details - Full History (Doctor Version)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
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
    console.log('%c📄 PDF Order: Visit Info → Patient → Doctor → Vital Signs → Lab Tests → Diagnosis → Medications → Procedures → Bill → History', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>