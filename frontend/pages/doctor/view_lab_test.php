<?php
// ================================================================
// FILE: frontend/pages/doctor/view_lab_test.php
// DOCTOR - VIEW LAB TEST (FULLY FIXED)
// Session-based login - USING dispensary_db
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
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_online = $_SESSION['is_online'] ?? 0;

// ================================================================
// GET LAB TEST ID
// ================================================================
$test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;

if ($test_id <= 0) {
    header('Location: lab_results.php?error=invalid_id');
    exit;
}

// ================================================================
// INCLUDE DATABASE - USING dispensary_db
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
    error_log("view_lab_test verification error: " . $e->getMessage());
}

// ================================================================
// GET LAB TEST DETAILS - FIXED: Using correct table columns
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            lt.id,
            lt.visit_id,
            lt.patient_id,
            lt.doctor_id,
            lt.lab_technician_id,
            lt.test_id,
            lt.test_name,
            lt.test_price,
            lt.equipment_used,
            lt.batch_number,
            lt.test_type,
            lt.sample_type,
            lt.test_date,
            lt.results,
            lt.formatted_result,
            lt.reference_range,
            lt.interpretation,
            lt.performed_by,
            lt.status,
            lt.started_at,
            lt.bill_created,
            lt.branch_id,
            lt.notes,
            lt.created_at,
            lt.completed_at,
            lt.printed_at,
            lt.printed_by,
            lt.updated_at,
            v.visit_number,
            v.visit_date,
            v.diagnosis as visit_diagnosis,
            v.symptoms as visit_symptoms,
            v.hpi as visit_hpi,
            v.physical_exam as visit_physical_exam,
            v.treatment as visit_treatment,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.gender as patient_gender,
            p.date_of_birth as patient_dob,
            p.phone as patient_phone,
            p.blood_group as patient_blood_group,
            p.allergies as patient_allergies,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            tech.full_name as technician_name,
            tech.phone as technician_phone,
            b.name as branch_name
        FROM lab_tests lt
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN patients p ON lt.patient_id = p.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        LEFT JOIN users tech ON lt.performed_by = tech.id
        LEFT JOIN branches b ON lt.branch_id = b.id
        WHERE lt.id = ? AND lt.doctor_id = ?
    ");
    $stmt->execute([$test_id, $doctor_id]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        // Check if test exists but belongs to another doctor
        $stmt = $db->prepare("SELECT id, doctor_id FROM lab_tests WHERE id = ?");
        $stmt->execute([$test_id]);
        $test_check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($test_check) {
            header('Location: lab_results.php?error=access_denied');
            exit;
        }
        header('Location: lab_results.php?error=not_found');
        exit;
    }
} catch (Exception $e) {
    error_log("Lab test fetch error: " . $e->getMessage());
    header('Location: lab_results.php?error=database_error');
    exit;
}

// ================================================================
// GET EQUIPMENT USED FOR THIS TEST
// ================================================================
$equipment_used = [];
try {
    $stmt = $db->prepare("
        SELECT me.equipment_name, me.batch_number, me.quantity, lte.created_at
        FROM lab_test_equipment lte
        LEFT JOIN medical_equipment me ON lte.equipment_id = me.id
        WHERE lte.lab_test_id = ?
    ");
    $stmt->execute([$test_id]);
    $equipment_used = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $equipment_used = [];
}

// ================================================================
// GET PRESCRIPTIONS LINKED TO THIS VISIT
// ================================================================
$prescriptions = [];
if ($test['visit_id']) {
    try {
        $stmt = $db->prepare("
            SELECT p.id, p.prescription_number, p.status, p.created_at,
                   pi.medication_name, pi.dosage, pi.frequency, pi.quantity, pi.instructions
            FROM prescriptions p
            LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
            WHERE p.visit_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$test['visit_id']]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $prescriptions = [];
    }
}

// ================================================================
// GET PROCEDURES LINKED TO THIS VISIT
// ================================================================
$procedures = [];
if ($test['visit_id']) {
    try {
        $stmt = $db->prepare("
            SELECT procedure_name, procedure_price, status, created_at
            FROM procedures
            WHERE visit_id = ? AND status != 'cancelled'
            ORDER BY created_at DESC
        ");
        $stmt->execute([$test['visit_id']]);
        $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $procedures = [];
    }
}

// ================================================================
// FUNCTIONS
// ================================================================
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'completed': return 'badge-success';
        case 'cancelled': return 'badge-danger';
        case 'in_progress': return 'badge-warning';
        case 'pending': return 'badge-warning';
        default: return 'badge-info';
    }
}

function getStatusIcon($status) {
    switch ($status) {
        case 'completed': return 'fa-check-circle';
        case 'cancelled': return 'fa-times-circle';
        case 'in_progress': return 'fa-spinner fa-spin';
        case 'pending': return 'fa-clock';
        default: return 'fa-circle';
    }
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
// GET UNREAD NOTIFICATIONS COUNT
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$doctor_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// INCLUDE HEADER & SIDEBAR - USING RELATIVE PATHS
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Test Details - Braick Dispensary</title>
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
        
        /* ================================================================ */
        /* SUMMARY HEADER */
        /* ================================================================ */
        .summary-header {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px 28px;
            border: 2px solid var(--border-color);
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            transition: all 0.3s ease;
        }
        
        .summary-header:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
        }
        
        .summary-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 6px 0;
        }
        
        .summary-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .meta-item {
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .meta-item i {
            font-size: 0.8rem;
            color: var(--primary);
        }
        
        .summary-header-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
        }
        
        .status-badge {
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            border: none;
        }
        
        .status-badge.badge-success { background: #059669; }
        .status-badge.badge-danger { background: #EF4444; }
        .status-badge.badge-warning { background: #D97706; }
        .status-badge.badge-info { background: var(--primary); }
        
        .completed-date {
            font-size: 0.7rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        /* ================================================================ */
        /* INFO GRID */
        /* ================================================================ */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .info-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .info-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
        }
        
        .info-card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 10px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-card-body {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .info-value {
            font-size: 0.85rem;
            color: var(--text-primary);
            text-align: right;
            max-width: 60%;
            word-break: break-word;
        }
        
        .info-value .tag-sm {
            font-size: 0.65rem;
            padding: 2px 10px;
            border-radius: 12px;
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .info-value.font-semibold { font-weight: 600; }
        .info-value.font-mono { font-family: monospace; }
        .info-value.text-sm { font-size: 0.8rem; }
        
        /* ================================================================ */
        /* RESULT CARD */
        /* ================================================================ */
        .result-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 24px;
        }
        
        .result-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
        }
        
        .result-card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 10px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .results-content {
            padding: 16px;
            background: var(--bg-body);
            border-radius: 10px;
            border: 1px solid var(--border-color);
            font-size: 0.9rem;
            color: var(--text-primary);
            line-height: 1.6;
            white-space: pre-wrap;
        }
        
        /* ================================================================ */
        /* EQUIPMENT USED */
        /* ================================================================ */
        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 8px;
        }
        
        .equipment-item {
            background: var(--gray-50);
            border-radius: 8px;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        [data-theme="dark"] .equipment-item {
            background: #1E293B;
        }
        
        .equipment-item .eq-icon {
            font-size: 1.2rem;
            color: var(--primary);
        }
        
        .equipment-item .eq-name {
            font-weight: 500;
            font-size: 0.8rem;
            color: var(--text-primary);
        }
        
        .equipment-item .eq-batch {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-family: monospace;
        }
        
        /* ================================================================ */
        /* PRESCRIPTIONS & PROCEDURES */
        /* ================================================================ */
        .linked-items {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .linked-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .linked-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
        }
        
        .linked-card-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .linked-item {
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.8rem;
            color: var(--text-primary);
        }
        
        .linked-item:last-child {
            border-bottom: none;
        }
        
        .linked-item .item-meta {
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        /* ================================================================ */
        /* PENDING MESSAGE */
        /* ================================================================ */
        .pending-message {
            background: #FEF3C7;
            border: 1px solid #FDE68A;
            border-radius: 16px;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }
        
        [data-theme="dark"] .pending-message {
            background: #3D2E0A;
            border-color: #F59E0B;
        }
        
        .pending-message-icon {
            width: 48px;
            height: 48px;
            background: #F59E0B;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: white;
            flex-shrink: 0;
        }
        
        .pending-message-title {
            font-size: 1rem;
            font-weight: 600;
            color: #92400E;
            margin: 0;
        }
        
        [data-theme="dark"] .pending-message-title {
            color: #FBBF24;
        }
        
        .pending-message-text {
            font-size: 0.85rem;
            color: #B45309;
            margin: 0;
        }
        
        [data-theme="dark"] .pending-message-text {
            color: #FDE68A;
        }
        
        /* ================================================================ */
        /* NO RESULTS */
        /* ================================================================ */
        .no-results {
            text-align: center;
            padding: 40px 20px;
            background: var(--bg-card);
            border-radius: 16px;
            border: 2px solid var(--border-color);
            color: var(--text-secondary);
            margin-bottom: 24px;
        }
        
        .no-results i {
            font-size: 2.5rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 8px;
        }
        
        .no-results p {
            font-size: 0.9rem;
        }
        
        /* ================================================================ */
        /* BUTTONS */
        /* ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.78rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            min-height: 36px;
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
            min-height: 30px;
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
        
        /* ================================================================ */
        /* UTILITY */
        /* ================================================================ */
        .text-gray-300 { color: #D1D5DB; }
        .mx-2 { margin-left: 0.5rem; margin-right: 0.5rem; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .flex-wrap { flex-wrap: wrap; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .flex { display: flex; }
        .mb-6 { margin-bottom: 24px; }
        .ml-2 { margin-left: 8px; }
        
        /* ================================================================ */
        /* RESPONSIVE */
        /* ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            .linked-items {
                grid-template-columns: 1fr;
            }
            .summary-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 16px 18px;
            }
            .summary-header-right {
                align-items: flex-start;
                width: 100%;
            }
            .summary-title {
                font-size: 1.1rem;
            }
            .summary-meta {
                flex-direction: column;
                gap: 4px;
            }
            .page-header {
                padding: 16px 18px;
            }
            .page-header .page-title {
                font-size: 1.2rem;
            }
            .info-card {
                padding: 14px 16px;
            }
            .result-card {
                padding: 14px 16px;
            }
            .info-row {
                flex-direction: column;
                align-items: flex-start;
            }
            .info-value {
                text-align: left;
                max-width: 100%;
            }
            .pending-message {
                flex-direction: column;
                text-align: center;
                padding: 16px;
            }
            .equipment-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header .page-title { font-size: 1rem; }
            .info-card { padding: 10px 12px; }
            .result-card { padding: 10px 12px; }
            .summary-title { font-size: 1rem; }
            .summary-header { padding: 12px 14px; }
            .btn { font-size: 0.7rem; padding: 4px 10px; }
            .status-badge { font-size: 0.7rem; padding: 4px 12px; }
        }
        
        @media print {
            .top-nav, .sidebar, .btn, .footer { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
            .summary-header, .info-card, .result-card, .linked-card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }
            .page-header { background: #0B5ED7 !important; }
            .status-badge.badge-success { background: #059669 !important; }
            .status-badge.badge-warning { background: #D97706 !important; }
            .status-badge.badge-info { background: #0B5ED7 !important; }
            .pending-message { background: #FEF3C7 !important; }
            [data-theme="dark"] .summary-header,
            [data-theme="dark"] .info-card,
            [data-theme="dark"] .result-card,
            [data-theme="dark"] .linked-card {
                background: white !important;
                color: #1E293B !important;
            }
            [data-theme="dark"] .summary-title,
            [data-theme="dark"] .info-value,
            [data-theme="dark"] .info-label,
            [data-theme="dark"] .result-card-title,
            [data-theme="dark"] .linked-card-title {
                color: #1E293B !important;
            }
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
                <i class="fas fa-flask"></i>
                Lab Test Details
                <span class="role-badge">DOCTOR</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-info-circle"></i>
                View complete laboratory test information
                <span class="tag"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?></span>
                <span class="tag"><i class="fas fa-hashtag"></i> Test #<?= $test['id'] ?></span>
                <?php if ($test['patient_name']): ?>
                    <span class="tag"><i class="fas fa-user"></i> <?= htmlspecialchars($test['patient_name']) ?></span>
                <?php endif; ?>
                <span class="tag"><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($doctor_name) ?></span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="lab_results.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($test['visit_id']): ?>
                <a href="view_visit.php?id=<?= $test['visit_id'] ?>" class="btn-outline-light">
                    <i class="fas fa-clinic-medical"></i> View Visit
                </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TEST SUMMARY -->
    <!-- ================================================================ -->
    <div class="summary-header">
        <div class="summary-header-left">
            <h2 class="summary-title"><?= htmlspecialchars($test['test_name'] ?? 'Lab Test') ?></h2>
            <div class="summary-meta">
                <span class="meta-item">
                    <i class="far fa-calendar-alt"></i>
                    <?= formatDate($test['created_at'] ?? '') ?>
                </span>
                <span class="meta-item">
                    <i class="fas fa-user-md"></i>
                    Doctor: <?= htmlspecialchars($test['doctor_name'] ?? 'Not assigned') ?>
                    <?= !empty($test['doctor_specialty']) ? '(' . htmlspecialchars($test['doctor_specialty']) . ')' : '' ?>
                </span>
                <?php if ($test['visit_number']): ?>
                    <span class="meta-item">
                        <i class="fas fa-clinic-medical"></i>
                        Visit: <?= htmlspecialchars($test['visit_number']) ?>
                    </span>
                <?php endif; ?>
                <?php if ($test['technician_name']): ?>
                    <span class="meta-item">
                        <i class="fas fa-user"></i>
                        Technician: <?= htmlspecialchars($test['technician_name']) ?>
                    </span>
                <?php endif; ?>
                <?php if ($test['branch_name']): ?>
                    <span class="meta-item">
                        <i class="fas fa-store-alt"></i>
                        Branch: <?= htmlspecialchars($test['branch_name']) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="summary-header-right">
            <span class="status-badge <?= getStatusBadgeClass($test['status']) ?>">
                <i class="fas <?= getStatusIcon($test['status']) ?>"></i>
                <?= ucfirst($test['status'] ?? 'Pending') ?>
            </span>
            <?php if ($test['completed_at']): ?>
                <span class="completed-date">
                    <i class="fas fa-check-circle text-green-500"></i>
                    Completed: <?= formatDate($test['completed_at']) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TEST & PATIENT INFO -->
    <!-- ================================================================ -->
    <div class="info-grid">
        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-info-circle" style="color:var(--primary);"></i> Test Information
            </h4>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-label">Test Name</span>
                    <span class="info-value font-semibold"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Test Type</span>
                    <span class="info-value"><?= htmlspecialchars($test['test_type'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Sample Type</span>
                    <span class="info-value"><?= htmlspecialchars($test['sample_type'] ?? 'N/A') ?></span>
                </div>
                <?php if ($test['test_date']): ?>
                    <div class="info-row">
                        <span class="info-label">Test Date</span>
                        <span class="info-value"><?= date('M d, Y', strtotime($test['test_date'])) ?></span>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <span class="tag-sm <?= getStatusBadgeClass($test['status']) ?>" style="background:<?= $test['status'] === 'completed' ? '#059669' : ($test['status'] === 'pending' ? '#D97706' : '#0B5ED7') ?>;color:white;padding:2px 10px;border-radius:12px;font-size:0.65rem;">
                            <?= ucfirst($test['status'] ?? 'Pending') ?>
                        </span>
                    </span>
                </div>
                <?php if ($test['test_price'] && $test['test_price'] > 0): ?>
                    <div class="info-row">
                        <span class="info-label">Test Price</span>
                        <span class="info-value font-semibold">TSh <?= number_format($test['test_price'], 0) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($test['reference_range']): ?>
                    <div class="info-row">
                        <span class="info-label">Reference Range</span>
                        <span class="info-value"><?= htmlspecialchars($test['reference_range']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($test['interpretation']): ?>
                    <div class="info-row">
                        <span class="info-label">Interpretation</span>
                        <span class="info-value"><?= htmlspecialchars($test['interpretation']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($test['batch_number']): ?>
                    <div class="info-row">
                        <span class="info-label">Batch Number</span>
                        <span class="info-value font-mono"><?= htmlspecialchars($test['batch_number']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-user" style="color:#059669;"></i> Patient Information
            </h4>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-label">Full Name</span>
                    <span class="info-value font-semibold"><?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Patient ID</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($test['patient_code'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Gender</span>
                    <span class="info-value"><?= ucfirst(htmlspecialchars($test['patient_gender'] ?? 'N/A')) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Age</span>
                    <span class="info-value"><?= calculateAge($test['patient_dob'] ?? '') ?> years</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?= htmlspecialchars($test['patient_phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Blood Group</span>
                    <span class="info-value"><?= htmlspecialchars($test['patient_blood_group'] ?? 'N/A') ?></span>
                </div>
                <?php if ($test['patient_allergies']): ?>
                    <div class="info-row">
                        <span class="info-label">Allergies</span>
                        <span class="info-value text-sm" style="color:#DC2626;"><?= htmlspecialchars($test['patient_allergies']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($test['visit_number']): ?>
                    <div class="info-row">
                        <span class="info-label">Visit Number</span>
                        <span class="info-value font-mono"><?= htmlspecialchars($test['visit_number']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($test['visit_diagnosis']): ?>
                    <div class="info-row">
                        <span class="info-label">Visit Diagnosis</span>
                        <span class="info-value text-sm"><?= nl2br(htmlspecialchars($test['visit_diagnosis'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RESULTS SUMMARY -->
    <!-- ================================================================ -->
    <?php if (!empty($test['results'])): ?>
    <div class="result-card">
        <h4 class="result-card-title">
            <i class="fas fa-file-alt" style="color:#0B5ED7;"></i> Results Summary
        </h4>
        <div class="results-content">
            <?= nl2br(htmlspecialchars($test['results'])) ?>
        </div>
        <?php if ($test['formatted_result']): ?>
            <div class="mt-3" style="padding:10px 14px;background:var(--primary-bg);border-radius:8px;border-left:4px solid var(--primary);">
                <strong style="color:var(--primary);">Formatted Result:</strong>
                <div style="margin-top:4px;font-size:0.85rem;"><?= nl2br(htmlspecialchars($test['formatted_result'])) ?></div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- EQUIPMENT USED -->
    <!-- ================================================================ -->
    <?php if (!empty($equipment_used)): ?>
    <div class="result-card">
        <h4 class="result-card-title">
            <i class="fas fa-tools" style="color:#D97706;"></i> Equipment Used
        </h4>
        <div class="equipment-grid">
            <?php foreach ($equipment_used as $eq): ?>
                <div class="equipment-item">
                    <span class="eq-icon">🔧</span>
                    <div>
                        <div class="eq-name"><?= htmlspecialchars($eq['equipment_name'] ?? 'N/A') ?></div>
                        <?php if ($eq['batch_number']): ?>
                            <div class="eq-batch">Batch: <?= htmlspecialchars($eq['batch_number']) ?></div>
                        <?php endif; ?>
                        <?php if ($eq['quantity']): ?>
                            <div class="eq-batch">Qty: <?= $eq['quantity'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- LINKED PRESCRIPTIONS & PROCEDURES -->
    <!-- ================================================================ -->
    <?php if (!empty($prescriptions) || !empty($procedures)): ?>
    <div class="linked-items">
        <?php if (!empty($prescriptions)): ?>
            <div class="linked-card">
                <h4 class="linked-card-title">
                    <i class="fas fa-prescription" style="color:#7C3AED;"></i> Prescriptions
                    <span style="font-size:0.65rem;color:var(--text-secondary);font-weight:400;">(<?= count($prescriptions) ?>)</span>
                </h4>
                <?php foreach ($prescriptions as $pres): ?>
                    <div class="linked-item">
                        <div><?= htmlspecialchars($pres['medication_name'] ?? 'N/A') ?></div>
                        <div class="item-meta">
                            <?= htmlspecialchars($pres['dosage'] ?? '') ?>
                            <?= htmlspecialchars($pres['frequency'] ?? '') ?>
                            x<?= $pres['quantity'] ?? 0 ?>
                            <?php if ($pres['instructions']): ?>
                                - <?= htmlspecialchars($pres['instructions']) ?>
                            <?php endif; ?>
                            <span class="tag-sm" style="margin-left:6px;font-size:0.6rem;background:<?= $pres['status'] === 'dispensed' ? '#D1FAE5' : '#FEF3C7' ?>;color:<?= $pres['status'] === 'dispensed' ? '#059669' : '#D97706' ?>;">
                                <?= ucfirst($pres['status'] ?? 'Pending') ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($procedures)): ?>
            <div class="linked-card">
                <h4 class="linked-card-title">
                    <i class="fas fa-syringe" style="color:#D97706;"></i> Procedures
                    <span style="font-size:0.65rem;color:var(--text-secondary);font-weight:400;">(<?= count($procedures) ?>)</span>
                </h4>
                <?php foreach ($procedures as $proc): ?>
                    <div class="linked-item">
                        <div><?= htmlspecialchars($proc['procedure_name'] ?? 'N/A') ?></div>
                        <div class="item-meta">
                            <?php if ($proc['procedure_price'] > 0): ?>
                                TSh <?= number_format($proc['procedure_price'], 0) ?>
                            <?php else: ?>
                                FREE
                            <?php endif; ?>
                            <span class="tag-sm" style="margin-left:6px;font-size:0.6rem;background:<?= $proc['status'] === 'completed' ? '#D1FAE5' : '#FEF3C7' ?>;color:<?= $proc['status'] === 'completed' ? '#059669' : '#D97706' ?>;">
                                <?= ucfirst($proc['status'] ?? 'Pending') ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- NOTES -->
    <!-- ================================================================ -->
    <?php if (!empty($test['notes'])): ?>
    <div class="result-card">
        <h4 class="result-card-title">
            <i class="fas fa-sticky-note" style="color:#D97706;"></i> Notes
        </h4>
        <div class="results-content">
            <?= nl2br(htmlspecialchars($test['notes'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PENDING MESSAGE -->
    <!-- ================================================================ -->
    <?php if (($test['status'] ?? '') === 'pending'): ?>
    <div class="pending-message">
        <div class="pending-message-icon">
            <i class="fas fa-clock"></i>
        </div>
        <div>
            <p class="pending-message-title">Test Pending</p>
            <p class="pending-message-text">This test is still pending. Results will appear once the lab completes the test.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- IN PROGRESS MESSAGE -->
    <!-- ================================================================ -->
    <?php if (($test['status'] ?? '') === 'in_progress'): ?>
    <div class="pending-message" style="background:#E8F0FE; border-color:#BFDBFE;">
        <div class="pending-message-icon" style="background:#0B5ED7;">
            <i class="fas fa-spinner fa-spin"></i>
        </div>
        <div>
            <p class="pending-message-title" style="color:#0B5ED7;">Test In Progress</p>
            <p class="pending-message-text" style="color:#1E3A5F;">This test is currently being processed by the laboratory.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- NO RESULTS MESSAGE -->
    <!-- ================================================================ -->
    <?php if (empty($test['results']) && ($test['status'] ?? '') !== 'pending' && ($test['status'] ?? '') !== 'in_progress'): ?>
    <div class="no-results">
        <i class="fas fa-file-alt"></i>
        <p>No results recorded for this test yet</p>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Lab Test Details
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

    console.log('%c🧪 View Lab Test - <?= htmlspecialchars($test['test_name'] ?? 'Lab Test') ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔐 Session-based login active', 'font-size:12px; color:#34D399;');
    console.log('%c📋 Patient: <?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?>', 'font-size:12px; color:#059669;');
    console.log('%c📋 Status: <?= ucfirst($test['status'] ?? 'Pending') ?>', 'font-size:12px; color:#64748B;');
    console.log('%c🔒 Doctor: View Only - No Edit Button', 'font-size:12px; color:#EF4444;');
    console.log('%c👨‍⚕️ Doctor: Dr. <?= htmlspecialchars($doctor_name) ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($doctor_branch_name) ?>', 'font-size:12px; color:#0B5ED7;');
    <?php if (!empty($equipment_used)): ?>
        console.log('%c🔧 Equipment Used: <?= count($equipment_used) ?> item(s)', 'font-size:12px; color:#D97706;');
    <?php endif; ?>
    <?php if (!empty($prescriptions)): ?>
        console.log('%c💊 Prescriptions: <?= count($prescriptions) ?>', 'font-size:12px; color:#7C3AED;');
    <?php endif; ?>
    <?php if (!empty($procedures)): ?>
        console.log('%c💉 Procedures: <?= count($procedures) ?>', 'font-size:12px; color:#D97706;');
    <?php endif; ?>
</script>

</body>
</html>