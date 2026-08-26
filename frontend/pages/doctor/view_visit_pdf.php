<?php
// ================================================================
// FILE: frontend/pages/doctor/view_visit_pdf.php
// DOCTOR - VIEW VISIT PDF (FULLY REDESIGNED)
// STRUCTURE: Same as view_visit.php with Official Stamp
// A4 SIZE with Download, Print & Cancel buttons
// BRAICK DISPENSARY
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS DOCTOR OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET DOCTOR INFO FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// GET VISIT ID
// ================================================================
$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($visit_id <= 0) {
    die('Invalid visit ID');
}

// ================================================================
// FUNCTIONS
// ================================================================
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'completed': return 'badge-success';
        case 'cancelled': return 'badge-danger';
        case 'pending': return 'badge-warning';
        case 'assigned': return 'badge-info';
        case 'with_doctor': return 'badge-info';
        case 'lab_test': return 'badge-warning';
        case 'lab_completed': return 'badge-success';
        case 'prescribed': return 'badge-purple';
        case 'dispensed': return 'badge-success';
        case 'paid': return 'badge-success';
        case 'partial': return 'badge-warning';
        default: return 'badge-info';
    }
}

function getStatusIcon($status) {
    switch ($status) {
        case 'completed': return 'fa-check-circle';
        case 'cancelled': return 'fa-times-circle';
        case 'pending': return 'fa-clock';
        case 'assigned': return 'fa-user-md';
        case 'with_doctor': return 'fa-stethoscope';
        case 'lab_test': return 'fa-flask';
        case 'lab_completed': return 'fa-check-circle';
        case 'prescribed': return 'fa-prescription';
        case 'dispensed': return 'fa-check-circle';
        case 'paid': return 'fa-check-circle';
        case 'partial': return 'fa-credit-card';
        default: return 'fa-circle';
    }
}

function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777', '#8B5CF6'];
    $index = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $index = ($index + ord($name[$i])) % count($colors);
    }
    return $colors[$index];
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

function getVitalStatus($value, $type) {
    if ($value === null || $value === '') return ['label' => 'N/A', 'class' => 'unknown'];
    switch ($type) {
        case 'temperature':
            if ($value > 37.5) return ['label' => 'HIGH', 'class' => 'high'];
            if ($value < 36.0) return ['label' => 'LOW', 'class' => 'low'];
            return ['label' => 'NORMAL', 'class' => 'normal'];
        case 'systolic':
            if ($value > 140) return ['label' => 'HIGH', 'class' => 'high'];
            if ($value < 90) return ['label' => 'LOW', 'class' => 'low'];
            return ['label' => 'NORMAL', 'class' => 'normal'];
        case 'pulse':
            if ($value > 100) return ['label' => 'HIGH', 'class' => 'high'];
            if ($value < 60) return ['label' => 'LOW', 'class' => 'low'];
            return ['label' => 'NORMAL', 'class' => 'normal'];
        case 'bmi':
            if ($value >= 30) return ['label' => 'OBESE', 'class' => 'high'];
            if ($value >= 25) return ['label' => 'OVERWEIGHT', 'class' => 'high'];
            if ($value >= 18.5) return ['label' => 'NORMAL', 'class' => 'normal'];
            return ['label' => 'UNDERWEIGHT', 'class' => 'low'];
        default:
            return ['label' => 'N/A', 'class' => 'unknown'];
    }
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// GET VISIT DETAILS
// ================================================================
if ($is_admin) {
    $stmt = $db->prepare("
        SELECT v.*, 
               p.full_name as patient_name,
               p.patient_id as patient_code,
               p.phone,
               p.date_of_birth,
               p.gender,
               p.address,
               p.blood_group,
               p.allergies,
               p.emergency_contact,
               u.full_name as doctor_name,
               u.specialty as doctor_specialty,
               r.full_name as receptionist_name,
               b.name as branch_name,
               b.location as branch_location,
               b.phone as branch_phone,
               d.disease_name,
               d.disease_code
        FROM visits v
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        LEFT JOIN branches b ON v.branch_id = b.id
        LEFT JOIN diseases d ON v.disease_id = d.id
        WHERE v.id = ?
    ");
    $stmt->execute([$visit_id]);
} else {
    $stmt = $db->prepare("
        SELECT v.*, 
               p.full_name as patient_name,
               p.patient_id as patient_code,
               p.phone,
               p.date_of_birth,
               p.gender,
               p.address,
               p.blood_group,
               p.allergies,
               p.emergency_contact,
               u.full_name as doctor_name,
               u.specialty as doctor_specialty,
               r.full_name as receptionist_name,
               b.name as branch_name,
               b.location as branch_location,
               b.phone as branch_phone,
               d.disease_name,
               d.disease_code
        FROM visits v
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        LEFT JOIN branches b ON v.branch_id = b.id
        LEFT JOIN diseases d ON v.disease_id = d.id
        WHERE v.id = ? AND v.doctor_id = ?
    ");
    $stmt->execute([$visit_id, $doctor_id]);
}
$visit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visit) {
    die('Visit not found or you don\'t have access');
}

// ================================================================
// GET VITAL SIGNS FOR THIS VISIT
// ================================================================
$stmt = $db->prepare("
    SELECT vs.*, u.full_name as recorded_by_name
    FROM vital_signs vs
    LEFT JOIN users u ON vs.recorded_by = u.id
    WHERE vs.visit_id = ?
    ORDER BY vs.recorded_at DESC
    LIMIT 1
");
$stmt->execute([$visit_id]);
$vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);

// ================================================================
// GET LAB TESTS FOR THIS VISIT
// ================================================================
$stmt = $db->prepare("
    SELECT lt.*, u.full_name as doctor_name, tech.full_name as technician_name
    FROM lab_tests lt
    LEFT JOIN users u ON lt.doctor_id = u.id
    LEFT JOIN users tech ON lt.performed_by = tech.id
    WHERE lt.visit_id = ?
    ORDER BY lt.created_at DESC
");
$stmt->execute([$visit_id]);
$lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET DIAGNOSIS DATA
// ================================================================
$diagnosis_data = [];
if (!empty($visit['disease_id']) || !empty($visit['diagnosis'])) {
    $diagnosis_data = [
        'disease_name' => $visit['disease_name'] ?? $visit['diagnosis'] ?? 'N/A',
        'disease_code' => $visit['disease_code'] ?? 'N/A',
        'treatment' => $visit['treatment'] ?? 'N/A'
    ];
}

// ================================================================
// GET PRESCRIPTIONS FOR THIS VISIT
// ================================================================
$stmt = $db->prepare("
    SELECT pr.*, 
           GROUP_CONCAT(CONCAT(pi.medication_name, ' (', pi.quantity, ' ', pi.dosage, ')') SEPARATOR ', ') as medications,
           COUNT(pi.id) as medications_count
    FROM prescriptions pr
    LEFT JOIN prescription_items pi ON pr.id = pi.prescription_id
    WHERE pr.visit_id = ?
    GROUP BY pr.id
    ORDER BY pr.created_at DESC
");
$stmt->execute([$visit_id]);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PROCEDURES FOR THIS VISIT
// ================================================================
$stmt = $db->prepare("
    SELECT pr.*, u.full_name as doctor_name
    FROM procedures pr
    LEFT JOIN users u ON pr.doctor_id = u.id
    WHERE pr.visit_id = ? AND pr.status != 'cancelled'
    ORDER BY pr.created_at DESC
");
$stmt->execute([$visit_id]);
$procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET EQUIPMENT/TOOLS FOR THIS VISIT
// ================================================================
$stmt = $db->prepare("
    SELECT bi.*, b.bill_number
    FROM bill_items bi
    JOIN bills b ON bi.bill_id = b.id
    WHERE b.visit_id = ? AND bi.item_type IN ('equipment', 'tool') AND bi.status != 'cancelled'
    ORDER BY bi.created_at DESC
");
$stmt->execute([$visit_id]);
$equipment_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET BILLS FOR THIS VISIT
// ================================================================
$stmt = $db->prepare("
    SELECT b.*, u.full_name as created_by_name
    FROM bills b
    LEFT JOIN users u ON b.created_by = u.id
    WHERE b.visit_id = ? AND b.status != 'cancelled'
    ORDER BY b.created_at DESC
");
$stmt->execute([$visit_id]);
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
// CALCULATE BILL STATISTICS
// ================================================================
$total_bills = 0;
$total_amount = 0;
$paid_amount = 0;
$pending_amount = 0;
$cancelled_amount = 0;

foreach ($bills as $bill) {
    $total_bills++;
    $total_amount += $bill['total_amount'] ?? 0;
    if ($bill['status'] === 'paid') {
        $paid_amount += $bill['total_amount'] ?? 0;
    } elseif ($bill['status'] === 'pending' || $bill['status'] === 'partial') {
        $pending_amount += $bill['total_amount'] ?? 0;
    } elseif ($bill['status'] === 'cancelled') {
        $cancelled_amount += $bill['total_amount'] ?? 0;
    }
}

// ================================================================
// GET ADMIN INFO FOR STAMP
// ================================================================
$admin_name = '';
$admin_phone = '';
$admin_email = '';
try {
    $stmt = $db->prepare("SELECT full_name, phone, email FROM users WHERE role = 'admin' AND status = 'active' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        $admin_name = $admin['full_name'] ?? 'Admin';
        $admin_phone = $admin['phone'] ?? '';
        $admin_email = $admin['email'] ?? '';
    }
} catch (Exception $e) {
    $admin_name = 'Admin';
}

// ================================================================
// GET BRANCH NAME
// ================================================================
$branch_name = 'Not Assigned';
$branch_location = '';
$branch_phone = '';
try {
    $stmt = $db->prepare("SELECT name, location, phone FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
        $branch_location = $branch_data['location'] ?? '';
        $branch_phone = $branch_data['phone'] ?? '';
    }
} catch (Exception $e) {
    $branch_name = 'Branch';
}

// ================================================================
// GET LOGO
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_path = $_SERVER['DOCUMENT_ROOT'] . $logo_url;
if (!file_exists($logo_path)) {
    $logo_url = '/dispensary_system/frontend/assets/uploads/profiles/logo.png';
}

// ================================================================
// GENERATE PDF CONTENT
// ================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visit Details PDF - <?= htmlspecialchars($visit['visit_number'] ?? 'Visit') ?> - Braick Dispensary</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        /* ================================================================ */
        /* PDF STYLES - A4 SIZE */
        /* ================================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            background: #f0f2f5;
            color: #1E293B;
            font-size: 10pt;
            line-height: 1.5;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        /* ================================================================ */
        /* TOOLBAR - Buttons at top */
        /* ================================================================ */
        .toolbar {
            width: 100%;
            max-width: 1100px;
            background: #ffffff;
            border-radius: 12px;
            padding: 14px 24px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: 1px solid #E2E8F0;
        }
        
        .toolbar .toolbar-title {
            font-size: 13pt;
            font-weight: 700;
            color: #0B5ED7;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .toolbar .toolbar-title i {
            font-size: 16pt;
        }
        
        .toolbar .toolbar-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .toolbar .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 9pt;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .toolbar .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .toolbar .btn-download {
            background: #0B5ED7;
            color: white;
        }
        .toolbar .btn-download:hover {
            background: #0A4CA8;
        }
        
        .toolbar .btn-print {
            background: #059669;
            color: white;
        }
        .toolbar .btn-print:hover {
            background: #047857;
        }
        
        .toolbar .btn-cancel {
            background: #EF4444;
            color: white;
        }
        .toolbar .btn-cancel:hover {
            background: #DC2626;
        }
        
        /* ================================================================ */
        /* PDF CONTAINER - A4 SIZE */
        /* ================================================================ */
        .pdf-container {
            width: 100%;
            max-width: 1100px;
            background: #ffffff;
            padding: 30px 35px;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
            border: 1px solid #E2E8F0;
        }
        
        /* ================================================================ */
        /* HEADER */
        /* ================================================================ */
        .pdf-header {
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 3px double #0B5ED7;
            margin-bottom: 20px;
        }
        
        .pdf-header .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 6px;
            flex-wrap: wrap;
        }
        
        .pdf-header .logo-container img {
            height: 55px;
            width: auto;
            max-height: 55px;
            object-fit: contain;
        }
        
        .pdf-header .clinic-name {
            color: #0B5ED7;
            font-size: 20pt;
            font-weight: 800;
            letter-spacing: 1px;
        }
        
        .pdf-header .clinic-sub {
            font-size: 9pt;
            color: #059669;
            letter-spacing: 3px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .pdf-header .doc-title {
            font-size: 14pt;
            font-weight: 700;
            color: #0B5ED7;
            background: #E8F0FE;
            padding: 4px 24px;
            border-radius: 30px;
            display: inline-block;
            margin-top: 6px;
            border: 2px solid #6EA8FE;
        }
        
        .pdf-header .contact-info {
            font-size: 8pt;
            color: #64748B;
            margin-top: 6px;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .pdf-header .contact-info span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .pdf-header .contact-info i {
            color: #0B5ED7;
        }
        
        /* ================================================================ */
        /* SECTION TITLE */
        /* ================================================================ */
        .section-title {
            font-size: 11pt;
            font-weight: 700;
            color: #0B5ED7;
            border-bottom: 2px solid #0B5ED7;
            padding-bottom: 4px;
            margin: 12px 0 8px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section-title .badge-count {
            font-size: 7pt;
            font-weight: 400;
            color: #64748B;
            background: #F1F5F9;
            padding: 1px 10px;
            border-radius: 10px;
        }
        
        /* ================================================================ */
        /* INFO ROWS */
        /* ================================================================ */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px 16px;
        }
        
        .info-row {
            display: flex;
            padding: 3px 0;
            border-bottom: 1px solid #E2E8F0;
        }
        
        .info-row .info-label {
            font-weight: 600;
            color: #64748B;
            width: 130px;
            flex-shrink: 0;
            font-size: 8.5pt;
        }
        
        .info-row .info-value {
            flex: 1;
            color: #1E293B;
            font-size: 9pt;
        }
        
        .info-row .info-value.font-semibold { font-weight: 600; }
        .info-row .info-value.font-mono { font-family: monospace; }
        .info-row .info-value.text-danger { color: #DC2626; }
        
        .col-span-2 { grid-column: span 2; }
        
        /* ================================================================ */
        /* VITAL SIGNS - 2 ROWS, 3 PER ROW */
        /* ================================================================ */
        .vital-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin: 4px 0 8px 0;
        }
        
        .vital-card {
            background: #F8FAFC;
            border-radius: 8px;
            padding: 10px 8px;
            text-align: center;
            border: 1px solid #E2E8F0;
            position: relative;
            overflow: hidden;
        }
        
        .vital-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            border-radius: 8px 8px 0 0;
        }
        
        .vital-card .vital-icon { font-size: 14pt; display: block; margin-bottom: 2px; }
        .vital-card .vital-value {
            font-size: 13pt;
            font-weight: 700;
            color: #1E293B;
            line-height: 1.2;
        }
        .vital-card .vital-label {
            font-size: 6.5pt;
            color: #64748B;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.04em;
            margin-top: 2px;
        }
        .vital-card .vital-unit {
            font-size: 6.5pt;
            color: #64748B;
            font-weight: 400;
        }
        .vital-card .vital-status {
            font-size: 5.5pt;
            font-weight: 700;
            padding: 1px 8px;
            border-radius: 8px;
            display: inline-block;
            margin-top: 2px;
        }
        .vital-card .vital-status.normal { background: #D1FAE5; color: #059669; }
        .vital-card .vital-status.high { background: #FEE2E2; color: #DC2626; }
        .vital-card .vital-status.low { background: #FEF3C7; color: #D97706; }
        .vital-card .vital-status.unknown { background: #F1F5F9; color: #64748B; }
        
        .vital-card.blue::before { background: #0B5ED7; }
        .vital-card.blue .vital-value { color: #0B5ED7; }
        .vital-card.red::before { background: #DC2626; }
        .vital-card.red .vital-value { color: #DC2626; }
        .vital-card.pink::before { background: #EC4899; }
        .vital-card.pink .vital-value { color: #EC4899; }
        .vital-card.purple::before { background: #7C3AED; }
        .vital-card.purple .vital-value { color: #7C3AED; }
        .vital-card.green::before { background: #059669; }
        .vital-card.green .vital-value { color: #059669; }
        .vital-card.indigo::before { background: #4F46E5; }
        .vital-card.indigo .vital-value { color: #4F46E5; }
        
        .vital-notes {
            font-size: 7.5pt;
            color: #64748B;
            padding: 4px 10px;
            background: #F8FAFC;
            border-radius: 4px;
            border-left: 3px solid #0B5ED7;
            margin-top: 4px;
        }
        
        /* ================================================================ */
        /* CLINICAL TABLE */
        /* ================================================================ */
        .clinical-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin: 4px 0;
        }
        
        .clinical-table thead th {
            background: #0B5ED7;
            color: #ffffff;
            font-weight: 700;
            font-size: 6.5pt;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 5px 8px;
            text-align: left;
            border-bottom: 2px solid #0A4CA8;
        }
        
        .clinical-table tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid #E2E8F0;
            vertical-align: top;
        }
        
        .clinical-table .symptom-tag {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 6.5pt;
            font-weight: 500;
            background: #E8F0FE;
            color: #0B5ED7;
            border: 1px solid #BFDBFE;
            margin: 1px 2px 1px 0;
        }
        
        .clinical-table .empty-cell {
            color: #94A3B8;
            font-style: italic;
            font-size: 7.5pt;
        }
        
        /* ================================================================ */
        /* DATA TABLES */
        /* ================================================================ */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            margin: 4px 0;
        }
        
        .data-table thead th {
            background: #0B5ED7;
            color: #ffffff;
            font-weight: 700;
            font-size: 6.5pt;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 5px 8px;
            text-align: left;
            border-bottom: 2px solid #0A4CA8;
        }
        
        .data-table tbody td {
            padding: 4px 8px;
            border-bottom: 1px solid #E2E8F0;
        }
        
        .data-table tbody tr:nth-child(even) td {
            background: #F8FAFC;
        }
        
        .data-table .badge {
            display: inline-block;
            padding: 1px 10px;
            border-radius: 12px;
            font-size: 6pt;
            font-weight: 600;
        }
        .data-table .badge-success { background: #D1FAE5; color: #059669; }
        .data-table .badge-warning { background: #FEF3C7; color: #D97706; }
        .data-table .badge-danger { background: #FEE2E2; color: #DC2626; }
        .data-table .badge-info { background: #E8F0FE; color: #0B5ED7; }
        .data-table .badge-purple { background: #EDE9FE; color: #7C3AED; }
        .data-table .badge-secondary { background: #F1F5F9; color: #64748B; }
        
        /* ================================================================ */
        /* BILL SUMMARY CARDS */
        /* ================================================================ */
        .bill-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin: 8px 0;
        }
        
        .bill-summary-card {
            background: #F8FAFC;
            border-radius: 8px;
            padding: 10px 12px;
            text-align: center;
            border: 1px solid #E2E8F0;
        }
        
        .bill-summary-card .bill-label {
            font-size: 6.5pt;
            font-weight: 600;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
        }
        .bill-summary-card .bill-value {
            font-size: 12pt;
            font-weight: 700;
            display: block;
            margin-top: 2px;
        }
        
        .bill-summary-card.total .bill-value { color: #0B5ED7; }
        .bill-summary-card.paid .bill-value { color: #059669; }
        .bill-summary-card.pending .bill-value { color: #D97706; }
        .bill-summary-card.cancelled .bill-value { color: #DC2626; }
        
        .bill-summary-card .bill-icon { font-size: 16pt; display: block; margin-bottom: 2px; }
        
        /* ================================================================ */
        /* BILL ITEMS SUBTABLE */
        /* ================================================================ */
        .bill-items-sub {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
            margin-top: 2px;
        }
        
        .bill-items-sub thead th {
            background: #E2E8F0;
            color: #64748B;
            font-weight: 600;
            font-size: 5.5pt;
            text-transform: uppercase;
            padding: 2px 6px;
            text-align: left;
        }
        
        .bill-items-sub tbody td {
            padding: 2px 6px;
            border-bottom: 1px solid #F1F5F9;
            font-size: 7pt;
        }
        
        /* ================================================================ */
        /* FOOTER WITH OFFICIAL STAMP */
        /* ================================================================ */
        .pdf-footer {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 2px solid #0B5ED7;
        }
        
        .pdf-footer .footer-stamp {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .pdf-footer .footer-left {
            font-size: 8pt;
            color: #64748B;
        }
        
        .pdf-footer .footer-left .signature-line {
            display: inline-block;
            width: 120px;
            border-bottom: 1px solid #64748B;
            margin-left: 4px;
        }
        
        .pdf-footer .stamp-box {
            text-align: center;
            padding: 8px 20px;
            border: 3px solid #0B5ED7;
            border-radius: 10px;
            background: #E8F0FE;
            min-width: 180px;
        }
        
        .pdf-footer .stamp-box .stamp-title {
            font-size: 6pt;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        
        .pdf-footer .stamp-box .stamp-name {
            font-size: 11pt;
            font-weight: 800;
            color: #0B5ED7;
        }
        
        .pdf-footer .stamp-box .stamp-line {
            font-size: 7pt;
            color: #64748B;
            margin-top: 2px;
        }
        
        .pdf-footer .stamp-box .stamp-date {
            font-size: 6.5pt;
            color: #64748B;
            margin-top: 2px;
        }
        
        .pdf-footer .footer-bottom {
            text-align: center;
            margin-top: 10px;
            font-size: 7pt;
            color: #94A3B8;
        }
        
        .pdf-footer .footer-bottom .brand {
            color: #0B5ED7;
            font-weight: 600;
        }
        
        /* ================================================================ */
        /* UTILITY */
        /* ================================================================ */
        .text-xs { font-size: 7pt; }
        .text-sm { font-size: 8pt; }
        .font-mono { font-family: monospace; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mb-2 { margin-bottom: 8px; }
        .text-gray-400 { color: #94A3B8; }
        .text-gray-500 { color: #64748B; }
        .text-danger { color: #DC2626; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 16px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
        .col-span-2 { grid-column: span 2; }
        .col-span-3 { grid-column: span 3; }
        
        /* ================================================================ */
        /* PRINT - A4 SIZE */
        /* ================================================================ */
        @media print {
            body {
                background: #ffffff;
                padding: 0;
                margin: 0;
            }
            
            .toolbar {
                display: none !important;
            }
            
            .pdf-container {
                max-width: 100%;
                padding: 15px 20px;
                border: none;
                border-radius: 0;
                box-shadow: none;
            }
            
            .vital-card { break-inside: avoid; }
            .diagnosis-box { break-inside: avoid; }
            .bill-summary-card { break-inside: avoid; }
            .data-table tbody tr { break-inside: avoid; }
            .clinical-table tbody tr { break-inside: avoid; }
        }
        
        @page {
            size: A4;
            margin: 12mm 10mm;
        }
    </style>
</head>
<body>

    <!-- ================================================================ -->
    <!-- TOOLBAR - Buttons -->
    <!-- ================================================================ -->
    <div class="toolbar" id="toolbar">
        <div class="toolbar-title">
            <i class="fas fa-file-pdf" style="color:#DC2626;"></i>
            Visit Details Report
            <span style="font-size:8pt;font-weight:400;color:#64748B;margin-left:8px;">
                <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?> | <?= htmlspecialchars($visit['patient_name'] ?? 'Unknown') ?>
            </span>
        </div>
        <div class="toolbar-actions">
            <button class="btn btn-download" onclick="downloadPDF()">
                <i class="fas fa-download"></i> Download
            </button>
            <button class="btn btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
            <button class="btn btn-cancel" onclick="window.close()">
                <i class="fas fa-times"></i> Cancel
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PDF CONTENT -->
    <!-- ================================================================ -->
    <div class="pdf-container" id="pdfContent">
        
        <!-- ================================================================ -->
        <!-- HEADER -->
        <!-- ================================================================ -->
        <div class="pdf-header">
            <div class="logo-container">
                <img src="<?= $logo_url ?>" alt="Braick Dispensary" onerror="this.style.display='none'">
                <span class="clinic-name">BRAICK DISPENSARY</span>
            </div>
            <div class="clinic-sub">Tunajali Afya Yako</div>
            <div class="doc-title">📋 Visit Details Report</div>
            <div class="contact-info">
                <span>📞 <?= htmlspecialchars($branch_phone ?: $admin_phone) ?></span>
                <span>📍 <?= htmlspecialchars($branch_location ?: $branch_name) ?></span>
                <span>📅 <?= date('d/m/Y h:i A') ?></span>
                <?php if (!empty($admin_email)): ?>
                    <span>✉️ <?= htmlspecialchars($admin_email) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- 1. PATIENT INFORMATION -->
        <!-- ================================================================ -->
        <div class="section-title">
            👤 Patient Information
            <span class="badge-count">Personal Details</span>
        </div>
        <div class="info-grid">
            <div class="info-row"><span class="info-label">Full Name</span><span class="info-value font-semibold"><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></span></div>
            <div class="info-row"><span class="info-label">Patient ID</span><span class="info-value font-mono"><?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?></span></div>
            <div class="info-row"><span class="info-label">Date of Birth</span><span class="info-value"><?= !empty($visit['date_of_birth']) ? date('M d, Y', strtotime($visit['date_of_birth'])) . ' (' . calculateAge($visit['date_of_birth']) . ' yrs)' : 'N/A' ?></span></div>
            <div class="info-row"><span class="info-label">Gender</span><span class="info-value"><?= ucfirst(htmlspecialchars($visit['gender'] ?? 'N/A')) ?></span></div>
            <div class="info-row"><span class="info-label">Phone</span><span class="info-value"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></span></div>
            <div class="info-row"><span class="info-label">Blood Group</span><span class="info-value"><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></span></div>
            <div class="info-row col-span-2"><span class="info-label">Address</span><span class="info-value"><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></span></div>
            <div class="info-row col-span-2"><span class="info-label">Emergency Contact</span><span class="info-value font-semibold text-danger"><?= htmlspecialchars($visit['emergency_contact'] ?? 'N/A') ?></span></div>
            <div class="info-row col-span-2"><span class="info-label">Allergies</span><span class="info-value text-danger"><?= htmlspecialchars($visit['allergies'] ?? 'None') ?></span></div>
        </div>

        <!-- ================================================================ -->
        <!-- 2. VISIT INFORMATION -->
        <!-- ================================================================ -->
        <div class="section-title">
            🏥 Visit Information
            <span class="badge-count">Visit Details</span>
        </div>
        <div class="info-grid">
            <div class="info-row"><span class="info-label">Visit Number</span><span class="info-value font-mono font-semibold"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span></div>
            <div class="info-row"><span class="info-label">Date & Time</span><span class="info-value"><?= formatDate($visit['created_at']) ?></span></div>
            <div class="info-row"><span class="info-label">Visit Type</span><span class="info-value"><?= ucfirst(htmlspecialchars($visit['visit_type'] ?? 'N/A')) ?></span></div>
            <div class="info-row"><span class="info-label">Status</span><span class="info-value"><span class="badge badge-<?= $visit['status'] === 'completed' ? 'success' : ($visit['status'] === 'prescribed' ? 'purple' : 'warning') ?>"><?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'Pending')) ?></span></span></div>
            <div class="info-row"><span class="info-label">Doctor</span><span class="info-value">Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'Not assigned') ?></span></div>
            <div class="info-row"><span class="info-label">Specialty</span><span class="info-value"><?= htmlspecialchars($visit['doctor_specialty'] ?? 'N/A') ?></span></div>
            <div class="info-row"><span class="info-label">Branch</span><span class="info-value"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></span></div>
            <div class="info-row"><span class="info-label">Consultation Fee</span><span class="info-value font-semibold">TSh <?= number_format($visit['consultation_fee'] ?? 0, 0) ?></span></div>
            <?php if (!empty($visit['receptionist_name'])): ?>
            <div class="info-row col-span-2"><span class="info-label">Receptionist</span><span class="info-value"><?= htmlspecialchars($visit['receptionist_name']) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($visit['follow_up_date'])): ?>
            <div class="info-row col-span-2"><span class="info-label">Follow-up Date</span><span class="info-value"><?= formatDateShort($visit['follow_up_date']) ?></span></div>
            <?php endif; ?>
        </div>

        <!-- ================================================================ -->
        <!-- 3. VITAL SIGNS - 2 ROWS, 3 PER ROW -->
        <!-- ================================================================ -->
        <div class="section-title">
            ❤️ Vital Signs
            <span class="badge-count">Latest Record</span>
        </div>
        <?php if ($vital_signs): 
            $temp_status = getVitalStatus($vital_signs['temperature'] ?? null, 'temperature');
            $sys = $vital_signs['blood_pressure_systolic'] ?? null;
            $bp_status = getVitalStatus($sys, 'systolic');
            $pulse_status = getVitalStatus($vital_signs['pulse_rate'] ?? null, 'pulse');
            $bmi_status = getVitalStatus($vital_signs['bmi'] ?? null, 'bmi');
        ?>
        <div class="vital-grid">
            <div class="vital-card blue">
                <span class="vital-icon">🌡️</span>
                <span class="vital-value"><?= $vital_signs['temperature'] ?? '--' ?> <span class="vital-unit">°C</span></span>
                <span class="vital-label">Temperature</span>
                <span class="vital-status <?= $temp_status['class'] ?>"><?= $temp_status['label'] ?></span>
            </div>
            <div class="vital-card red">
                <span class="vital-icon">💓</span>
                <span class="vital-value"><?= ($vital_signs['blood_pressure_systolic'] ?? '--') . '/' . ($vital_signs['blood_pressure_diastolic'] ?? '--') ?> <span class="vital-unit">mmHg</span></span>
                <span class="vital-label">Blood Pressure</span>
                <span class="vital-status <?= $bp_status['class'] ?>"><?= $bp_status['label'] ?></span>
            </div>
            <div class="vital-card pink">
                <span class="vital-icon">💓</span>
                <span class="vital-value"><?= $vital_signs['pulse_rate'] ?? '--' ?> <span class="vital-unit">bpm</span></span>
                <span class="vital-label">Pulse Rate</span>
                <span class="vital-status <?= $pulse_status['class'] ?>"><?= $pulse_status['label'] ?></span>
            </div>
            <div class="vital-card purple">
                <span class="vital-icon">⚖️</span>
                <span class="vital-value"><?= $vital_signs['weight'] ?? '--' ?> <span class="vital-unit">kg</span></span>
                <span class="vital-label">Weight</span>
            </div>
            <div class="vital-card green">
                <span class="vital-icon">📏</span>
                <span class="vital-value"><?= $vital_signs['height'] ?? '--' ?> <span class="vital-unit">cm</span></span>
                <span class="vital-label">Height</span>
            </div>
            <div class="vital-card indigo">
                <span class="vital-icon">📊</span>
                <span class="vital-value"><?= $vital_signs['bmi'] ?? '--' ?></span>
                <span class="vital-label">BMI</span>
                <span class="vital-status <?= $bmi_status['class'] ?>"><?= $bmi_status['label'] ?></span>
            </div>
        </div>
        <?php if (!empty($vital_signs['notes'])): ?>
            <div class="vital-notes">
                <strong>Notes:</strong> <?= nl2br(htmlspecialchars($vital_signs['notes'])) ?>
            </div>
        <?php endif; ?>
        <div style="font-size:7.5pt;color:#64748B;margin-top:4px;">
            <strong>Recorded by:</strong> <?= htmlspecialchars($vital_signs['recorded_by_name'] ?? 'N/A') ?>
            <?php if (!empty($vital_signs['recorded_at'])): ?>
                | <strong>Date:</strong> <?= formatDate($vital_signs['recorded_at']) ?>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:8px;color:#94A3B8;">No vital signs recorded for this visit</div>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- 4. CLINICAL ASSESSMENT TABLE -->
        <!-- ================================================================ -->
        <div class="section-title">
            📋 Clinical Assessment
            <span class="badge-count">Symptoms, Complaints, Notes, HPI & Physical Exam</span>
        </div>
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
                        <?php if (!empty($visit['symptoms'])): ?>
                            <?php 
                                $symptoms_array = array_map('trim', explode(',', $visit['symptoms']));
                                foreach ($symptoms_array as $sym):
                                    if (!empty($sym)):
                            ?>
                                <span class="symptom-tag"><?= htmlspecialchars($sym) ?></span>
                            <?php 
                                    endif;
                                endforeach; 
                            ?>
                        <?php else: ?>
                            <span class="empty-cell">No symptoms recorded</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($visit['complaint'])): ?>
                            <?= nl2br(htmlspecialchars($visit['complaint'])) ?>
                        <?php else: ?>
                            <span class="empty-cell">No complaint recorded</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($visit['notes'])): ?>
                            <?= nl2br(htmlspecialchars($visit['notes'])) ?>
                        <?php else: ?>
                            <span class="empty-cell">No notes recorded</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($visit['hpi'])): ?>
                            <?= nl2br(htmlspecialchars($visit['hpi'])) ?>
                        <?php else: ?>
                            <span class="empty-cell">No HPI recorded</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($visit['physical_exam'])): ?>
                            <?= nl2br(htmlspecialchars($visit['physical_exam'])) ?>
                        <?php else: ?>
                            <span class="empty-cell">No physical exam recorded</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- ================================================================ -->
        <!-- 5. LAB TESTS -->
        <!-- ================================================================ -->
        <div class="section-title">
            🧪 Lab Tests
            <span class="badge-count"><?= count($lab_tests) ?> records</span>
        </div>
        <?php if (count($lab_tests) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Test Name</th>
                    <th>Results</th>
                    <th>Lab Technician</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lab_tests as $index => $test): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td class="font-semibold"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                    <td>
                        <?php if ($test['status'] === 'completed' && !empty($test['results'])): ?>
                            <span style="color:#059669;"><?= htmlspecialchars(substr($test['results'], 0, 50)) . (strlen($test['results'] ?? '') > 50 ? '...' : '') ?></span>
                        <?php elseif ($test['status'] === 'completed'): ?>
                            <span style="color:#059669;">✅ Available</span>
                        <?php else: ?>
                            <span style="color:#94A3B8;">⏳ Pending</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($test['technician_name'] ?? 'Not assigned') ?></td>
                    <td><span class="badge badge-<?= $test['status'] === 'completed' ? 'success' : ($test['status'] === 'in_progress' ? 'info' : 'warning') ?>"><?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'Pending')) ?></span></td>
                    <td><?= formatDateShort($test['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align:center;padding:8px;color:#94A3B8;">No lab tests for this visit</div>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- 6. DIAGNOSIS -->
        <!-- ================================================================ -->
        <div class="section-title">
            🩺 Diagnosis
            <span class="badge-count">Diagnosis Details</span>
        </div>
        <?php if (!empty($diagnosis_data['disease_name']) && $diagnosis_data['disease_name'] !== 'N/A'): ?>
        <div class="grid-3">
            <div class="info-row" style="grid-column: span 1;">
                <span class="info-label" style="font-weight:600;color:#64748B;width:100px;">Disease Name</span>
                <span class="info-value font-semibold" style="color:#0B5ED7;"><?= htmlspecialchars($diagnosis_data['disease_name']) ?></span>
            </div>
            <div class="info-row" style="grid-column: span 1;">
                <span class="info-label" style="font-weight:600;color:#64748B;width:100px;">Disease Code</span>
                <span class="info-value font-mono"><?= htmlspecialchars($diagnosis_data['disease_code']) ?></span>
            </div>
            <div class="info-row" style="grid-column: span 1;">
                <span class="info-label" style="font-weight:600;color:#64748B;width:100px;">Treatment</span>
                <span class="info-value"><?= htmlspecialchars($diagnosis_data['treatment']) ?></span>
            </div>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:8px;color:#94A3B8;">No diagnosis recorded for this visit</div>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- 7. PRESCRIPTIONS -->
        <!-- ================================================================ -->
        <div class="section-title">
            💊 Prescriptions
            <span class="badge-count"><?= count($prescriptions) ?> records</span>
        </div>
        <?php if (count($prescriptions) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Prescription #</th>
                    <th>Medications</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prescriptions as $index => $pr): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td class="font-mono" style="color:#0B5ED7;font-weight:600;"><?= htmlspecialchars($pr['prescription_number'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($pr['medications'] ?? 'No items') ?></td>
                    <td><span class="badge badge-<?= $pr['status'] === 'dispensed' ? 'success' : 'warning' ?>"><?= ucfirst($pr['status'] ?? 'Pending') ?></span></td>
                    <td><?= formatDateShort($pr['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align:center;padding:8px;color:#94A3B8;">No prescriptions for this visit</div>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- 8. PROCEDURES & EQUIPMENT -->
        <!-- ================================================================ -->
        <div class="section-title">
            💉 Procedures & Equipment
            <span class="badge-count"><?= count($procedures) ?> procedures, <?= count($equipment_items) ?> equipment</span>
        </div>
        <?php if (count($procedures) > 0 || count($equipment_items) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Name</th>
                    <th>Type</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php $proc_index = 1; ?>
                <?php foreach ($procedures as $proc): ?>
                <tr>
                    <td><?= $proc_index++ ?></td>
                    <td class="font-semibold"><?= htmlspecialchars($proc['procedure_name'] ?? 'N/A') ?></td>
                    <td><span class="badge badge-info" style="font-size:5.5pt;padding:1px 6px;">Procedure</span></td>
                    <td>1</td>
                    <td><?= ($proc['procedure_price'] ?? 0) > 0 ? 'TSh ' . number_format($proc['procedure_price'], 0) : '<span style="color:#059669;">FREE</span>' ?></td>
                    <td><span class="badge badge-<?= $proc['status'] === 'completed' ? 'success' : 'warning' ?>"><?= ucfirst($proc['status'] ?? 'Pending') ?></span></td>
                    <td><?= formatDateShort($proc['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php foreach ($equipment_items as $item): ?>
                <tr>
                    <td><?= $proc_index++ ?></td>
                    <td><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></td>
                    <td><span class="badge badge-info" style="font-size:5.5pt;padding:1px 6px;"><?= ucfirst(htmlspecialchars($item['item_type'] ?? 'Equipment')) ?></span></td>
                    <td><?= $item['quantity'] ?? 1 ?></td>
                    <td><?= ($item['total_price'] ?? 0) > 0 ? 'TSh ' . number_format($item['total_price'], 0) : '<span style="color:#059669;">FREE</span>' ?></td>
                    <td><span class="badge badge-<?= $item['status'] === 'paid' ? 'success' : 'warning' ?>"><?= ucfirst($item['status'] ?? 'Pending') ?></span></td>
                    <td><?= formatDateShort($item['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align:center;padding:8px;color:#94A3B8;">No procedures or equipment for this visit</div>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- 9. BILLS WITH SUMMARY CARDS -->
        <!-- ================================================================ -->
        <div class="section-title">
            💰 Bills & Payments
            <span class="badge-count"><?= $total_bills ?> bills</span>
        </div>
        
        <!-- Bill Summary Cards -->
        <div class="bill-summary-grid">
            <div class="bill-summary-card total">
                <span class="bill-icon">📄</span>
                <span class="bill-label">Total Bills</span>
                <span class="bill-value">TSh <?= number_format($total_amount, 0) ?></span>
            </div>
            <div class="bill-summary-card paid">
                <span class="bill-icon">✅</span>
                <span class="bill-label">Paid</span>
                <span class="bill-value">TSh <?= number_format($paid_amount, 0) ?></span>
            </div>
            <div class="bill-summary-card pending">
                <span class="bill-icon">⏳</span>
                <span class="bill-label">Pending</span>
                <span class="bill-value">TSh <?= number_format($pending_amount, 0) ?></span>
            </div>
            <div class="bill-summary-card cancelled">
                <span class="bill-icon">❌</span>
                <span class="bill-label">Cancelled</span>
                <span class="bill-value">TSh <?= number_format($cancelled_amount, 0) ?></span>
            </div>
        </div>

        <!-- Bill Items Table -->
        <?php if (count($bills) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Bill Number</th>
                    <th>Items</th>
                    <th>Total Amount</th>
                    <th>Paid Amount</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bills as $index => $bill): 
                    $items = $bill_items_data[$bill['id']] ?? [];
                ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td class="font-mono" style="color:#0B5ED7;font-weight:600;"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                    <td>
                        <?php if (count($items) > 0): ?>
                            <table class="bill-items-sub">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Qty</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['item_name'] ?? '') ?></td>
                                            <td><?= $item['quantity'] ?? 1 ?></td>
                                            <td style="font-weight:500;">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <span style="color:#94A3B8;font-size:6pt;">No items</span>
                        <?php endif; ?>
                    </td>
                    <td class="font-semibold">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                    <td>TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                    <td style="font-weight:600;color:<?= ($bill['balance'] ?? 0) > 0 ? '#DC2626' : '#059669' ?>;">
                        TSh <?= number_format($bill['balance'] ?? 0, 0) ?>
                    </td>
                    <td><span class="badge badge-<?= $bill['status'] === 'paid' ? 'success' : ($bill['status'] === 'partial' ? 'info' : 'warning') ?>"><?= ucfirst($bill['status'] ?? 'Pending') ?></span></td>
                    <td><?= formatDateShort($bill['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align:center;padding:8px;color:#94A3B8;">No bills for this visit</div>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- FOOTER WITH OFFICIAL STAMP -->
        <!-- ================================================================ -->
        <div class="pdf-footer">
            <div class="footer-stamp">
                <div class="footer-left">
                    <span>👨‍⚕️ Doctor: <strong>Dr. <?= htmlspecialchars($doctor_name) ?></strong></span><br>
                    <span>🏥 Branch: <?= htmlspecialchars($branch_name) ?></span><br>
                    <span>📅 Generated: <?= date('d/m/Y h:i A') ?></span>
                </div>
                <div class="stamp-box">
                    <div class="stamp-title">Official Stamp</div>
                    <div class="stamp-name">BRAICK DISPENSARY</div>
                    <div class="stamp-line">Approved By: _________________</div>
                    <div class="stamp-date">Date: <?= date('d/m/Y') ?></div>
                </div>
            </div>
            <div class="footer-bottom">
                <span class="brand">💙 BRAICK DISPENSARY</span> - Tunajali Afya Yako<br>
                This is a computer-generated document. No signature required.
            </div>
        </div>

    </div>

    <!-- ================================================================ -->
    <!-- JAVASCRIPT -->
    <!-- ================================================================ -->
    <script>
        // ================================================================
        // DOWNLOAD PDF
        // ================================================================
        function downloadPDF() {
            var element = document.getElementById('pdfContent');
            var opt = {
                margin: [10, 10, 10, 10],
                filename: 'Visit_<?= htmlspecialchars($visit['visit_number'] ?? 'visit') ?>_<?= date('Y-m-d') ?>.pdf',
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
            
            var downloadBtn = document.querySelector('.btn-download');
            downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
            downloadBtn.disabled = true;
            
            html2pdf().set(opt).from(element).save().then(function() {
                downloadBtn.innerHTML = '<i class="fas fa-download"></i> Download';
                downloadBtn.disabled = false;
            });
        }

        // ================================================================
        // KEYBOARD SHORTCUTS
        // ================================================================
        document.addEventListener('keydown', function(e) {
            // Ctrl+P - Print
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            // Escape - Cancel
            if (e.key === 'Escape') {
                window.close();
            }
        });

        console.log('%c📄 Braick Dispensary - Visit PDF Report', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
        console.log('%c📋 Visit: <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
        console.log('%c👤 Patient: <?= htmlspecialchars($visit['patient_name'] ?? 'Unknown') ?>', 'font-size:13px; color:#64748B;');
        console.log('%c📅 Generated: <?= date('d/m/Y h:i A') ?>', 'font-size:13px; color:#0B5ED7;');
        console.log('%c📄 A4 Size | Download, Print & Cancel buttons', 'font-size:13px; color:#DC2626;');
        console.log('%c✅ Official Stamp included', 'font-size:13px; color:#34D399;');
        console.log('%c📋 Flow: Patient Info → Visit Info → Vital Signs (2 rows, 3 per row) → Clinical Table → Lab Tests → Diagnosis → Prescriptions → Procedures/Equipment → Bills with Cards', 'font-size:12px; color:#34D399;');
    </script>

</body>
</html>