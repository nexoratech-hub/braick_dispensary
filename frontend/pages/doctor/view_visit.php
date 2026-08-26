<?php
// ================================================================
// FILE: frontend/pages/doctor/view_visit.php
// DOCTOR - VIEW VISIT DETAILS (VIEW ONLY - NO BUTTONS)
// WITH PDF EXPORT AND REDESIGNED FLOW
// USING dispensary_db
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
    header('Location: my_patients.php');
    exit;
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
    header('Location: my_patients.php?error=visit_not_found');
    exit;
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
// GET DIAGNOSIS HISTORY (from diseases table via visit)
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
// GET EQUIPMENT/TOOLS FOR THIS VISIT (from bill_items)
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
// GET DOCTOR'S BRANCH NAME
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
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

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
    <title>Visit Details - Braick Dispensary</title>
    <link rel="icon" href="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" type="image/png">
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
            --teal: #0D9488;
            --teal-bg: #CCFBF1;
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
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
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
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        /* ================================================================ */
        /* PAGE HEADER */
        /* ================================================================ */
        .page-header {
            background: var(--primary-gradient);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .role-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .btn-pdf {
            background: rgba(220,38,38,0.3);
            color: white;
            border: 1px solid rgba(220,38,38,0.2);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .btn-pdf:hover {
            background: rgba(220,38,38,0.4);
            transform: translateY(-2px);
        }
        
        /* ================================================================ */
        /* SECTION TITLE */
        /* ================================================================ */
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 8px;
            margin: 16px 0 12px 0;
            display: flex;
            align-items: center;
            gap: 10px;
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
        /* CARDS */
        /* ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 18px 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            margin-bottom: 16px;
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
        /* INFO ROWS */
        /* ================================================================ */
        .info-row {
            display: flex;
            padding: 4px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .info-row:last-child { border-bottom: none; }
        
        .info-row .info-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 140px;
            flex-shrink: 0;
            font-size: 0.8rem;
        }
        
        .info-row .info-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        
        /* ================================================================ */
        /* VITAL SIGNS - 2 ROWS, 3 PER ROW */
        /* ================================================================ */
        .vital-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        
        .vital-card {
            background: var(--bg-card);
            border-radius: 10px;
            padding: 12px 8px;
            text-align: center;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .vital-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        
        .vital-card .vital-icon { font-size: 1.2rem; display: block; margin-bottom: 2px; }
        .vital-card .vital-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: block;
        }
        .vital-card .vital-label {
            font-size: 0.55rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
        }
        .vital-card .vital-unit {
            font-size: 0.5rem;
            color: var(--text-secondary);
            font-weight: 400;
        }
        
        .vital-card.blue .vital-value { color: #0B5ED7; }
        .vital-card.blue { border-color: #BFDBFE; }
        .vital-card.red .vital-value { color: #DC2626; }
        .vital-card.red { border-color: #FCA5A5; }
        .vital-card.pink .vital-value { color: #EC4899; }
        .vital-card.pink { border-color: #F9A8D4; }
        .vital-card.purple .vital-value { color: #7C3AED; }
        .vital-card.purple { border-color: #C4B5FD; }
        .vital-card.green .vital-value { color: #059669; }
        .vital-card.green { border-color: #6EE7B7; }
        .vital-card.indigo .vital-value { color: #4F46E5; }
        .vital-card.indigo { border-color: #A5B4FC; }
        
        [data-theme="dark"] .vital-card { background: #1E293B; border-color: #334155; }
        [data-theme="dark"] .vital-card:hover { border-color: #0B5ED7; }
        [data-theme="dark"] .vital-card.blue .vital-value { color: #6EA8FE; }
        [data-theme="dark"] .vital-card.blue { border-color: #1E3A5F; }
        [data-theme="dark"] .vital-card.red .vital-value { color: #F87171; }
        [data-theme="dark"] .vital-card.red { border-color: #7F1D1D; }
        [data-theme="dark"] .vital-card.pink .vital-value { color: #F472B6; }
        [data-theme="dark"] .vital-card.pink { border-color: #831843; }
        [data-theme="dark"] .vital-card.purple .vital-value { color: #A78BFA; }
        [data-theme="dark"] .vital-card.purple { border-color: #4C1D95; }
        [data-theme="dark"] .vital-card.green .vital-value { color: #34D399; }
        [data-theme="dark"] .vital-card.green { border-color: #065F46; }
        [data-theme="dark"] .vital-card.indigo .vital-value { color: #A5B4FC; }
        [data-theme="dark"] .vital-card.indigo { border-color: #312E81; }
        
        /* ================================================================ */
        /* CLINICAL TABLE - Symptoms, Complaint, Notes, HPI, Physical Exam */
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
        
        .clinical-table .symptom-tag {
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
        
        [data-theme="dark"] .clinical-table .symptom-tag {
            background: #1A2A4A;
            color: #6EA8FE;
            border-color: #1E3A5F;
        }
        
        /* ================================================================ */
        /* DATA TABLES */
        /* ================================================================ */
        .table-wrap {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        
        .data-table thead th {
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
        
        .data-table thead th:first-child { border-radius: 8px 0 0 0 !important; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0 !important; }
        
        .data-table tbody td {
            padding: 8px 12px !important;
            border-bottom: 1px solid var(--border-color) !important;
            color: var(--text-primary) !important;
            vertical-align: middle !important;
        }
        
        .data-table tbody tr:nth-child(even) td {
            background: var(--primary-bg);
        }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td {
            background: #1E293B;
        }
        
        .data-table tbody tr:hover td {
            background: #D1FAE5 !important;
        }
        
        [data-theme="dark"] .data-table tbody tr:hover td {
            background: #1A3A2A !important;
        }
        
        /* ================================================================ */
        /* BADGES */
        /* ================================================================ */
        .badge {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: white;
            border: none;
        }
        
        .badge-success { background: #059669; }
        .badge-danger { background: #EF4444; }
        .badge-warning { background: #D97706; }
        .badge-info { background: var(--primary); }
        .badge-purple { background: #7C3AED; }
        .badge-teal { background: #0D9488; }
        
        [data-theme="dark"] .badge-success { background: #059669; }
        [data-theme="dark"] .badge-danger { background: #EF4444; }
        [data-theme="dark"] .badge-warning { background: #D97706; }
        [data-theme="dark"] .badge-info { background: #0B5ED7; }
        [data-theme="dark"] .badge-purple { background: #7C3AED; }
        
        /* ================================================================ */
        /* BILL SUMMARY CARDS */
        /* ================================================================ */
        .bill-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 16px;
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
        /* EMPTY STATE */
        /* ================================================================ */
        .empty-state {
            text-align: center;
            padding: 20px 10px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 2rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 8px;
        }
        
        .empty-state p {
            font-size: 0.85rem;
        }
        
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
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        .text-gray-300 { color: #D1D5DB; }
        .mx-2 { margin-left: 0.5rem; margin-right: 0.5rem; }
        .text-xs { font-size: 0.65rem; }
        .text-sm { font-size: 0.75rem; }
        .font-mono { font-family: monospace; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mb-2 { margin-bottom: 8px; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .flex-wrap { flex-wrap: wrap; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .flex { display: flex; }
        
        /* ================================================================ */
        /* RESPONSIVE */
        /* ================================================================ */
        @media (max-width: 1024px) {
            .main-content { padding: 16px; }
            .bill-summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 12px; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.2rem; }
            .vital-grid { grid-template-columns: repeat(2, 1fr); }
            .bill-summary-grid { grid-template-columns: 1fr; }
            .info-row { flex-direction: column; }
            .info-row .info-label { width: 100%; }
            .clinical-table { font-size: 0.7rem; }
            .clinical-table thead th, .clinical-table tbody td { padding: 6px 8px !important; }
            .data-table { font-size: 0.7rem; }
            .data-table thead th, .data-table tbody td { padding: 4px 8px !important; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .vital-grid { grid-template-columns: 1fr 1fr; }
            .bill-summary-grid { grid-template-columns: 1fr; }
            .page-header .page-title { font-size: 1rem; }
            .page-header .page-subtitle { font-size: 0.75rem; }
            .btn-outline-light, .btn-pdf { padding: 4px 10px; font-size: 0.65rem; }
        }
        
        @media print {
            .top-nav, .sidebar, .btn-outline-light, .btn-pdf, .footer { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
            .card, .page-header {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }
            .page-header { background: #0B5ED7 !important; }
            .data-table thead th { background: #0B5ED7 !important; color: white !important; }
            .clinical-table thead th { background: #0B5ED7 !important; color: white !important; }
            .badge-success { background: #059669 !important; }
            .badge-warning { background: #D97706 !important; }
            .badge-info { background: #0B5ED7 !important; }
            .badge-danger { background: #DC2626 !important; }
            .bill-summary-card { border: 1px solid #ddd !important; }
            .vital-card { border: 1px solid #ddd !important; }
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
                <i class="fas fa-clinic-medical"></i>
                Visit Details
                <span class="role-badge">DOCTOR</span>
                <?php if ($is_admin): ?>
                    <span class="role-badge" style="background:rgba(220,38,38,0.4);border-color:rgba(220,38,38,0.3);color:#FCA5A5;">👑 ADMIN</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-info-circle"></i>
                View complete visit information
                <span class="tag"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?></span>
                <span class="tag"><i class="fas fa-user"></i> <?= htmlspecialchars($visit['patient_name'] ?? 'Unknown') ?></span>
                <span class="tag"><i class="fas fa-hashtag"></i> <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span>
                <span class="tag"><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($doctor_name) ?></span>
                <?php if ($is_admin): ?>
                    <span class="tag" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.2);color:#FCA5A5;">👑 Admin View</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap no-print">
            <button onclick="window.location.href='view_visit_pdf.php?id=<?= $visit_id ?>'" class="btn-pdf">
                <i class="fas fa-file-pdf"></i> View PDF
            </button>
            <a href="my_patients.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
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
        <div class="grid" style="display:grid; grid-template-columns:1fr 1fr; gap:2px 16px;">
            <div class="info-row">
                <span class="info-label"><i class="fas fa-user"></i> Full Name</span>
                <span class="info-value font-semibold"><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-id-card"></i> Patient ID</span>
                <span class="info-value font-mono"><?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-calendar-alt"></i> Date of Birth</span>
                <span class="info-value"><?= !empty($visit['date_of_birth']) ? date('M d, Y', strtotime($visit['date_of_birth'])) . ' (' . calculateAge($visit['date_of_birth']) . ' yrs)' : 'N/A' ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-venus-mars"></i> Gender</span>
                <span class="info-value"><?= ucfirst(htmlspecialchars($visit['gender'] ?? 'N/A')) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-phone"></i> Phone</span>
                <span class="info-value"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-tint"></i> Blood Group</span>
                <span class="info-value"><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row" style="grid-column: span 2;">
                <span class="info-label"><i class="fas fa-map-marker-alt"></i> Address</span>
                <span class="info-value"><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row" style="grid-column: span 2;">
                <span class="info-label"><i class="fas fa-phone-alt"></i> Emergency Contact</span>
                <span class="info-value font-semibold" style="color:var(--danger);"><?= htmlspecialchars($visit['emergency_contact'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row" style="grid-column: span 2;">
                <span class="info-label"><i class="fas fa-allergies"></i> Allergies</span>
                <span class="info-value" style="color:var(--danger);"><?= htmlspecialchars($visit['allergies'] ?? 'None') ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 2. VISIT INFORMATION -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-clinic-medical"></i> Visit Information
        <span class="badge-count">Visit Details</span>
    </div>
    <div class="card">
        <div class="grid" style="display:grid; grid-template-columns:1fr 1fr; gap:2px 16px;">
            <div class="info-row">
                <span class="info-label"><i class="fas fa-hashtag"></i> Visit Number</span>
                <span class="info-value font-mono font-semibold"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-calendar-alt"></i> Date & Time</span>
                <span class="info-value"><?= formatDate($visit['created_at']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-tag"></i> Visit Type</span>
                <span class="info-value"><?= ucfirst(htmlspecialchars($visit['visit_type'] ?? 'N/A')) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-clock"></i> Status</span>
                <span class="info-value">
                    <span class="badge <?= getStatusBadgeClass($visit['status']) ?>">
                        <i class="fas <?= getStatusIcon($visit['status']) ?>"></i>
                        <?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'Pending')) ?>
                    </span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-user-md"></i> Doctor</span>
                <span class="info-value">Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'Not assigned') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-stethoscope"></i> Specialty</span>
                <span class="info-value"><?= htmlspecialchars($visit['doctor_specialty'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-store-alt"></i> Branch</span>
                <span class="info-value"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-money-bill-wave"></i> Consultation Fee</span>
                <span class="info-value font-semibold" style="color:var(--primary);">TSh <?= number_format($visit['consultation_fee'] ?? 0, 0) ?></span>
            </div>
            <?php if (!empty($visit['receptionist_name'])): ?>
            <div class="info-row" style="grid-column: span 2;">
                <span class="info-label"><i class="fas fa-user"></i> Receptionist</span>
                <span class="info-value"><?= htmlspecialchars($visit['receptionist_name']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($visit['follow_up_date'])): ?>
            <div class="info-row" style="grid-column: span 2;">
                <span class="info-label"><i class="fas fa-calendar-plus"></i> Follow-up Date</span>
                <span class="info-value"><?= formatDateShort($visit['follow_up_date']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 3. VITAL SIGNS - 2 ROWS, 3 PER ROW -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-heartbeat" style="color:#EC4899;"></i> Vital Signs
        <span class="badge-count">Latest Record</span>
    </div>
    <div class="card">
        <?php if ($vital_signs): ?>
            <div class="vital-grid">
                <div class="vital-card blue">
                    <span class="vital-icon">🌡️</span>
                    <span class="vital-value"><?= $vital_signs['temperature'] ?? '--' ?> <span class="vital-unit">°C</span></span>
                    <span class="vital-label">Temperature</span>
                </div>
                <div class="vital-card red">
                    <span class="vital-icon">💓</span>
                    <span class="vital-value"><?= ($vital_signs['blood_pressure_systolic'] ?? '--') . '/' . ($vital_signs['blood_pressure_diastolic'] ?? '--') ?> <span class="vital-unit">mmHg</span></span>
                    <span class="vital-label">Blood Pressure</span>
                </div>
                <div class="vital-card pink">
                    <span class="vital-icon">💓</span>
                    <span class="vital-value"><?= $vital_signs['pulse_rate'] ?? '--' ?> <span class="vital-unit">bpm</span></span>
                    <span class="vital-label">Pulse Rate</span>
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
                </div>
            </div>
            <?php if (!empty($vital_signs['notes'])): ?>
                <div class="mt-2 text-sm" style="color:var(--text-secondary);padding:6px 12px;background:var(--bg-body);border-radius:6px;border-left:3px solid var(--primary);">
                    <strong>Notes:</strong> <?= htmlspecialchars($vital_signs['notes']) ?>
                </div>
            <?php endif; ?>
            <div class="mt-2 text-xs" style="color:var(--text-secondary);">
                <i class="fas fa-user"></i> Recorded by: <?= htmlspecialchars($vital_signs['recorded_by_name'] ?? 'N/A') ?>
                <?php if (!empty($vital_signs['recorded_at'])): ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-clock"></i> <?= formatDate($vital_signs['recorded_at']) ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-heartbeat" style="color:#EC4899;"></i>
                <p>No vital signs recorded for this visit</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 4. CLINICAL ASSESSMENT TABLE - Symptoms, Complaint, Notes, HPI, Physical Exam -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-clipboard-list" style="color:#D97706;"></i> Clinical Assessment
        <span class="badge-count">Symptoms, Complaints, Notes, HPI & Physical Exam</span>
    </div>
    <div class="card">
        <div class="table-wrap">
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
            <div class="table-wrap">
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
                                        <span style="color:var(--success);"><?= htmlspecialchars(substr($test['results'], 0, 50)) . (strlen($test['results'] ?? '') > 50 ? '...' : '') ?></span>
                                    <?php elseif ($test['status'] === 'completed'): ?>
                                        <span style="color:var(--success);">✅ Available</span>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($test['technician_name'] ?? 'Not assigned') ?></td>
                                <td>
                                    <span class="badge <?= getStatusBadgeClass($test['status']) ?>">
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
            <div class="empty-state">
                <i class="fas fa-flask" style="color:#7C3AED;"></i>
                <p>No lab tests for this visit</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 6. DIAGNOSIS -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-diagnoses" style="color:#7C3AED;"></i> Diagnosis
        <span class="badge-count">Diagnosis Details</span>
    </div>
    <div class="card">
        <?php if (!empty($diagnosis_data['disease_name']) && $diagnosis_data['disease_name'] !== 'N/A'): ?>
            <div class="grid" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:2px 16px;">
                <div class="info-row" style="grid-column: span 1;">
                    <span class="info-label"><i class="fas fa-disease"></i> Disease Name</span>
                    <span class="info-value font-semibold" style="color:var(--primary);"><?= htmlspecialchars($diagnosis_data['disease_name']) ?></span>
                </div>
                <div class="info-row" style="grid-column: span 1;">
                    <span class="info-label"><i class="fas fa-code"></i> Disease Code</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($diagnosis_data['disease_code']) ?></span>
                </div>
                <div class="info-row" style="grid-column: span 1;">
                    <span class="info-label"><i class="fas fa-prescription"></i> Treatment</span>
                    <span class="info-value"><?= htmlspecialchars($diagnosis_data['treatment']) ?></span>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-diagnoses" style="color:#7C3AED;"></i>
                <p>No diagnosis recorded for this visit</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 7. PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-prescription" style="color:#059669;"></i> Prescriptions
        <span class="badge-count"><?= count($prescriptions) ?> records</span>
    </div>
    <div class="card">
        <?php if (count($prescriptions) > 0): ?>
            <div class="table-wrap">
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
                                <td class="font-mono text-xs font-semibold" style="color:var(--primary);"><?= htmlspecialchars($pr['prescription_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($pr['medications'] ?? 'No items') ?></td>
                                <td>
                                    <span class="badge <?= getStatusBadgeClass($pr['status']) ?>">
                                        <?= ucfirst($pr['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= formatDateShort($pr['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription" style="color:#059669;"></i>
                <p>No prescriptions for this visit</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 8. PROCEDURES & EQUIPMENT -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-syringe" style="color:#D97706;"></i> Procedures & Equipment
        <span class="badge-count"><?= count($procedures) ?> procedures, <?= count($equipment_items) ?> equipment</span>
    </div>
    <div class="card">
        <?php if (count($procedures) > 0 || count($equipment_items) > 0): ?>
            <div class="table-wrap">
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
                                <td><span class="badge badge-info" style="font-size:0.55rem;padding:1px 8px;">Procedure</span></td>
                                <td>1</td>
                                <td><?= ($proc['procedure_price'] ?? 0) > 0 ? 'TSh ' . number_format($proc['procedure_price'], 0) : '<span style="color:var(--success);">FREE</span>' ?></td>
                                <td>
                                    <span class="badge <?= getStatusBadgeClass($proc['status']) ?>">
                                        <?= ucfirst($proc['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= formatDateShort($proc['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ($equipment_items as $item): ?>
                            <tr>
                                <td><?= $proc_index++ ?></td>
                                <td><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></td>
                                <td><span class="badge badge-teal" style="font-size:0.55rem;padding:1px 8px;"><?= ucfirst(htmlspecialchars($item['item_type'] ?? 'Equipment')) ?></span></td>
                                <td><?= $item['quantity'] ?? 1 ?></td>
                                <td><?= ($item['total_price'] ?? 0) > 0 ? 'TSh ' . number_format($item['total_price'], 0) : '<span style="color:var(--success);">FREE</span>' ?></td>
                                <td>
                                    <span class="badge <?= $item['status'] === 'paid' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= ucfirst($item['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= formatDateShort($item['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-syringe" style="color:#D97706;"></i>
                <p>No procedures or equipment for this visit</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 9. BILLS WITH SUMMARY CARDS -->
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
                <span class="bill-value">TSh <?= number_format($total_amount, 0) ?></span>
            </div>
        </div>
        <div class="bill-summary-card paid-card">
            <div class="bill-icon"><i class="fas fa-check-circle"></i></div>
            <div class="bill-content">
                <span class="bill-label">✅ Paid</span>
                <span class="bill-value">TSh <?= number_format($paid_amount, 0) ?></span>
            </div>
        </div>
        <div class="bill-summary-card pending-card">
            <div class="bill-icon"><i class="fas fa-clock"></i></div>
            <div class="bill-content">
                <span class="bill-label">⏳ Pending</span>
                <span class="bill-value">TSh <?= number_format($pending_amount, 0) ?></span>
            </div>
        </div>
        <div class="bill-summary-card cancelled-card">
            <div class="bill-icon"><i class="fas fa-times-circle"></i></div>
            <div class="bill-content">
                <span class="bill-label">❌ Cancelled</span>
                <span class="bill-value">TSh <?= number_format($cancelled_amount, 0) ?></span>
            </div>
        </div>
    </div>

    <!-- Bill Items Table -->
    <div class="card">
        <?php if (count($bills) > 0): ?>
            <div class="table-wrap">
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
                            $total_items = count($items);
                        ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td class="font-mono text-xs font-semibold" style="color:var(--primary);"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($total_items > 0): ?>
                                        <?php foreach ($items as $item): ?>
                                            <div style="font-size:0.7rem;padding:1px 0;border-bottom:1px solid var(--border-color);">
                                                <?= htmlspecialchars($item['item_name'] ?? '') ?>
                                                <span style="color:var(--text-secondary);">x<?= $item['quantity'] ?? 1 ?></span>
                                                <span style="float:right;font-weight:500;">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);font-size:0.65rem;">No items</span>
                                    <?php endif; ?>
                                </td>
                                <td class="font-semibold">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                                <td>TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                <td class="font-semibold" style="color:<?= ($bill['balance'] ?? 0) > 0 ? '#DC2626' : '#059669' ?>;">
                                    TSh <?= number_format($bill['balance'] ?? 0, 0) ?>
                                </td>
                                <td>
                                    <span class="badge <?= getStatusBadgeClass($bill['status']) ?>">
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
            <div class="empty-state">
                <i class="fas fa-receipt" style="color:#0B5ED7;"></i>
                <p>No bills for this visit</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Visit Details
            <?php if ($is_admin): ?>
                <span class="text-gray-300 mx-2">|</span>
                <span style="color:#DC2626;">👑 Admin Mode</span>
            <?php endif; ?>
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($doctor_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
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
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
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
        }, 5000);
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

    console.log('%c👨‍⚕️ View Visit - <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?> (View Only)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User ID: <?= $doctor_id ?> | Role: <?= $_SESSION['role'] ?>', 'font-size:12px; color:#64748B;');
    <?php if ($is_admin): ?>
    console.log('%c👑 Admin Mode - Viewing All Visits', 'font-size:12px; color:#DC2626;');
    <?php endif; ?>
    console.log('%c👤 Patient: <?= htmlspecialchars($visit['patient_name'] ?? 'Unknown') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Status: <?= $visit['status'] ?? 'Pending' ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📋 New Flow: Patient Info → Visit Info → Vital Signs (2 rows, 3 per row) → Clinical Table → Lab Tests → Diagnosis → Prescriptions → Procedures/Equipment → Bills with Cards', 'font-size:12px; color:#34D399;');
    console.log('%c💰 Bills Summary: Total: TSh <?= number_format($total_amount, 0) ?> | Paid: TSh <?= number_format($paid_amount, 0) ?> | Pending: TSh <?= number_format($pending_amount, 0) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📄 View PDF button added', 'font-size:12px; color:#DC2626;');
    console.log('%c🚫 NO ACTION BUTTONS - View only', 'font-size:12px; color:#EF4444;');
    console.log('%c✅ Using dispensary_db - All tables fixed', 'font-size:12px; color:#34D399;');
</script>

</body>
</html>