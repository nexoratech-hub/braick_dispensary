<?php
// ================================================================
// FILE: frontend/pages/doctor/view_prescription.php
// DOCTOR - VIEW SINGLE PRESCRIPTION (BEAUTIFUL CSS)
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
// GET PRESCRIPTION ID
// ================================================================
$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($prescription_id <= 0) {
    header('Location: view_prescriptions.php?error=invalid_id');
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
    error_log("view_prescription verification error: " . $e->getMessage());
}

// ================================================================
// GET PRESCRIPTION DETAILS - Verify doctor has access
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            pr.*,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone,
            p.email,
            p.date_of_birth,
            p.gender,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            v.visit_number,
            v.diagnosis as visit_diagnosis,
            v.symptoms,
            v.hpi,
            v.physical_exam,
            v.complaint,
            v.treatment as visit_treatment,
            v.consultation_fee
        FROM prescriptions pr
        JOIN patients p ON pr.patient_id = p.id
        JOIN users u ON pr.doctor_id = u.id
        LEFT JOIN visits v ON pr.visit_id = v.id
        WHERE pr.id = ? AND pr.doctor_id = ?
    ");
    $stmt->execute([$prescription_id, $doctor_id]);
    $prescription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prescription) {
        // Check if prescription exists but belongs to another doctor
        $stmt = $db->prepare("SELECT id, doctor_id FROM prescriptions WHERE id = ?");
        $stmt->execute([$prescription_id]);
        $prescription_check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($prescription_check) {
            header('Location: view_prescriptions.php?error=access_denied');
            exit;
        }
        header('Location: view_prescriptions.php?error=not_found');
        exit;
    }
} catch (Exception $e) {
    error_log("Prescription fetch error: " . $e->getMessage());
    header('Location: view_prescriptions.php?error=database_error');
    exit;
}

// ================================================================
// GET PRESCRIPTION ITEMS
// ================================================================
$items = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM prescription_items 
        WHERE prescription_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$prescription_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $items = [];
}

// ================================================================
// GET BILL INFO FOR THIS PRESCRIPTION
// ================================================================
$bill_info = null;
try {
    $stmt = $db->prepare("
        SELECT bi.*, b.bill_number, b.total_amount, b.status as bill_status
        FROM bill_items bi
        JOIN bills b ON bi.bill_id = b.id
        WHERE bi.reference_id = ? AND bi.reference_type = 'prescription'
        LIMIT 1
    ");
    $stmt->execute([$prescription_id]);
    $bill_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bill_info = null;
}

// ================================================================
// GET STATUS BADGE CLASS
// ================================================================
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'dispensed': return 'badge-success';
        case 'cancelled': return 'badge-danger';
        case 'confirmed': return 'badge-info';
        case 'pending': return 'badge-warning';
        default: return 'badge-warning';
    }
}

function getStatusIcon($status) {
    switch ($status) {
        case 'dispensed': return 'fa-check-circle';
        case 'cancelled': return 'fa-times-circle';
        case 'confirmed': return 'fa-check';
        case 'pending': return 'fa-clock';
        default: return 'fa-clock';
    }
}

// ================================================================
// CALCULATE AGE
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    return $age;
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
    <title>Prescription Details - Braick Dispensary</title>
    <link rel="icon" href="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================ */
        /* ROOT VARIABLES - LIGHT & DARK MODE */
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
        
        /* ================================================================ */
        /* PRESCRIPTION HEADER */
        /* ================================================================ */
        .prescription-header {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px 28px;
            border: 2px solid var(--border-color);
            margin-bottom: 24px;
            transition: all 0.3s ease;
        }
        
        .prescription-header:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
        }
        
        .prescription-header-top {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }
        
        .prescription-number {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 6px 0;
        }
        
        .prescription-meta {
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
        
        .prescription-header-right {
            text-align: right;
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
        
        .dispensed-date {
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
        
        .info-value.font-semibold { font-weight: 600; }
        .info-value.font-mono { font-family: monospace; }
        .info-value.text-sm { font-size: 0.8rem; }
        .info-value.text-gray-500 { color: var(--text-secondary); }
        .info-value .symptom-tag {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.6rem;
            font-weight: 500;
            background: var(--primary-bg);
            color: var(--primary);
            border: 1px solid var(--primary-light);
            margin: 1px 2px 1px 0;
        }
        
        /* ================================================================ */
        /* MEDICATION CARD */
        /* ================================================================ */
        .medication-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 24px;
        }
        
        .medication-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
        }
        
        .medication-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .medication-card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
        }
        
        .table-wrap {
            overflow-x: auto;
        }
        
        .medication-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        .medication-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
        }
        
        .medication-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .medication-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .medication-table tbody tr:nth-child(even) {
            background: var(--primary-bg);
        }
        
        .medication-table tbody tr:nth-child(odd) {
            background: var(--bg-card);
        }
        
        .medication-table tbody tr:hover {
            background: #D1FAE5;
        }
        
        [data-theme="dark"] .medication-table tbody tr:hover {
            background: #1A3A2A;
        }
        
        .medication-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .medication-table td .font-semibold { font-weight: 600; }
        .medication-table td .text-gray-800 { color: var(--text-primary); }
        .medication-table td .text-sm { font-size: 0.8rem; }
        .medication-table td .text-gray-500 { color: var(--text-secondary); }
        
        /* ================================================================ */
        /* SUMMARY CARD */
        /* ================================================================ */
        .summary-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .summary-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
        }
        
        .summary-card-title {
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
        
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 24px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .summary-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .summary-value {
            font-size: 0.85rem;
            color: var(--text-primary);
            font-weight: 500;
            text-align: right;
        }
        
        .summary-value.font-mono { font-family: monospace; }
        .summary-value.text-green-600 { color: #059669; }
        .summary-value.text-red-600 { color: #DC2626; }
        
        /* ================================================================ */
        /* EMPTY STATE */
        /* ================================================================ */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 2.5rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 8px;
        }
        
        .empty-state p {
            font-size: 0.9rem;
        }
        
        /* ================================================================ */
        /* BADGE */
        /* ================================================================ */
        .badge {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
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
        
        /* ================================================================ */
        /* RESPONSIVE */
        /* ================================================================ */
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 12px; }
            .info-grid { grid-template-columns: 1fr; }
            .summary-grid { grid-template-columns: 1fr; }
            .prescription-header-top { flex-direction: column; align-items: flex-start; }
            .prescription-header-right { text-align: left; align-items: flex-start; width: 100%; }
            .prescription-number { font-size: 1.1rem; }
            .prescription-meta { flex-direction: column; gap: 4px; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.2rem; }
            .medication-card { padding: 14px 16px; }
            .medication-table { font-size: 0.75rem; }
            .medication-table th, .medication-table td { padding: 6px 10px; }
            .info-card { padding: 14px 16px; }
            .summary-card { padding: 14px 16px; }
            .prescription-header { padding: 16px 18px; }
            .info-row { flex-direction: column; align-items: flex-start; }
            .info-value { text-align: left; max-width: 100%; }
            .page-header { flex-direction: column; align-items: flex-start; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .info-grid { grid-template-columns: 1fr; }
            .summary-grid { grid-template-columns: 1fr; }
            .prescription-number { font-size: 1rem; }
            .prescription-meta { font-size: 0.7rem; }
            .prescription-header { padding: 12px 14px; }
            .page-header .page-title { font-size: 1rem; }
            .medication-card { padding: 10px 12px; }
            .medication-table th, .medication-table td { padding: 4px 6px; font-size: 0.7rem; }
            .info-card { padding: 10px 12px; }
            .summary-card { padding: 10px 12px; }
            .summary-grid { gap: 4px; }
            .summary-item { flex-direction: column; align-items: flex-start; }
            .summary-value { text-align: left; }
            .btn { font-size: 0.7rem; padding: 4px 10px; }
            .status-badge { font-size: 0.7rem; padding: 4px 12px; }
        }
        
        @media print {
            .top-nav, .sidebar, .btn, .btn-outline-light, .footer, .no-print { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
            .prescription-header, .info-card, .medication-card, .summary-card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }
            .page-header { background: #0B5ED7 !important; }
            .medication-table thead th { background: #0B5ED7 !important; color: white !important; }
            .status-badge { color: white !important; }
            .status-badge.badge-success { background: #059669 !important; }
            .status-badge.badge-warning { background: #D97706 !important; }
            .status-badge.badge-info { background: #0B5ED7 !important; }
            .status-badge.badge-danger { background: #DC2626 !important; }
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
                <i class="fas fa-prescription"></i>
                Prescription Details
                <span class="role-badge">DOCTOR</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-info-circle"></i>
                View complete prescription information
                <span class="tag"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?></span>
                <span class="tag"><i class="fas fa-hashtag"></i> <?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></span>
                <span class="tag"><i class="fas fa-user"></i> <?= htmlspecialchars($prescription['patient_name'] ?? 'N/A') ?></span>
                <span class="tag"><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($doctor_name) ?></span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap no-print">
            <a href="view_prescriptions.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION HEADER -->
    <!-- ================================================================ -->
    <div class="prescription-header">
        <div class="prescription-header-top">
            <div class="prescription-header-left">
                <h2 class="prescription-number">
                    <i class="fas fa-prescription" style="color:#0B5ED7;"></i>
                    <?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?>
                </h2>
                <div class="prescription-meta">
                    <span class="meta-item">
                        <i class="far fa-calendar-alt"></i>
                        <?= date('F d, Y h:i A', strtotime($prescription['created_at'] ?? 'now')) ?>
                    </span>
                    <span class="meta-item">
                        <i class="fas fa-user-md"></i>
                        Dr. <?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?>
                        <?= !empty($prescription['doctor_specialty']) ? '(' . htmlspecialchars($prescription['doctor_specialty']) . ')' : '' ?>
                    </span>
                    <?php if ($prescription['visit_number']): ?>
                        <span class="meta-item">
                            <i class="fas fa-clinic-medical"></i>
                            Visit: <?= htmlspecialchars($prescription['visit_number']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="prescription-header-right">
                <span class="status-badge <?= getStatusBadgeClass($prescription['status'] ?? 'pending') ?>">
                    <i class="fas <?= getStatusIcon($prescription['status'] ?? 'pending') ?>"></i>
                    <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                </span>
                <?php if ($bill_info): ?>
                    <span class="dispensed-date">
                        <i class="fas fa-receipt text-blue-500"></i>
                        Bill: <?= htmlspecialchars($bill_info['bill_number'] ?? 'N/A') ?>
                        <span class="badge <?= $bill_info['bill_status'] === 'paid' ? 'badge-success' : 'badge-warning' ?>" style="font-size:0.55rem;padding:1px 8px;">
                            <?= ucfirst($bill_info['bill_status'] ?? 'Pending') ?>
                        </span>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT & DIAGNOSIS INFO -->
    <!-- ================================================================ -->
    <div class="info-grid">
        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-user" style="color:#0B5ED7;"></i> Patient Information
            </h4>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-label">Full Name</span>
                    <span class="info-value font-semibold"><?= htmlspecialchars($prescription['patient_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Patient ID</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($prescription['patient_code'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Gender</span>
                    <span class="info-value"><?= htmlspecialchars($prescription['gender'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date of Birth</span>
                    <span class="info-value"><?= !empty($prescription['date_of_birth']) ? date('M d, Y', strtotime($prescription['date_of_birth'])) : 'N/A' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Age</span>
                    <span class="info-value"><?= calculateAge($prescription['date_of_birth'] ?? '') ?> years</span>
                </div>
                <?php if (!empty($prescription['phone'])): ?>
                    <div class="info-row">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?= htmlspecialchars($prescription['phone']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($prescription['email'])): ?>
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?= htmlspecialchars($prescription['email']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-stethoscope" style="color:#059669;"></i> Diagnosis & Visit Details
            </h4>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-label">Diagnosis</span>
                    <span class="info-value">
                        <?= !empty($prescription['diagnosis']) ? nl2br(htmlspecialchars($prescription['diagnosis'])) : '<span class="text-gray-500">No diagnosis recorded</span>' ?>
                    </span>
                </div>
                <?php if (!empty($prescription['visit_diagnosis']) && $prescription['diagnosis'] !== $prescription['visit_diagnosis']): ?>
                    <div class="info-row">
                        <span class="info-label">Visit Diagnosis</span>
                        <span class="info-value text-sm text-gray-500"><?= htmlspecialchars($prescription['visit_diagnosis']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($prescription['visit_treatment'])): ?>
                    <div class="info-row">
                        <span class="info-label">Treatment</span>
                        <span class="info-value text-sm"><?= htmlspecialchars($prescription['visit_treatment']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($prescription['consultation_fee'])): ?>
                    <div class="info-row">
                        <span class="info-label">Consultation Fee</span>
                        <span class="info-value font-semibold">TSh <?= number_format($prescription['consultation_fee'], 0) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($prescription['symptoms'])): ?>
                    <div class="info-row">
                        <span class="info-label">Symptoms</span>
                        <span class="info-value text-sm">
                            <?php 
                                $symptoms_array = array_map('trim', explode(',', $prescription['symptoms']));
                                foreach ($symptoms_array as $sym):
                                    if (!empty($sym)):
                            ?>
                                <span class="symptom-tag"><?= htmlspecialchars($sym) ?></span>
                            <?php 
                                    endif;
                                endforeach; 
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($prescription['notes'])): ?>
                    <div class="info-row">
                        <span class="info-label">Notes</span>
                        <span class="info-value text-sm"><?= nl2br(htmlspecialchars($prescription['notes'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MEDICATION ITEMS -->
    <!-- ================================================================ -->
    <div class="medication-card">
        <div class="medication-card-header">
            <h3 class="medication-card-title">
                <i class="fas fa-pills" style="color:#0B5ED7;"></i>
                Medication Items
                <span class="text-sm font-normal text-gray-400">(<?= count($items) ?> item<?= count($items) > 1 ? 's' : '' ?>)</span>
            </h3>
            <?php if ($bill_info): ?>
                <span class="badge badge-info" style="font-size:0.65rem;">
                    <i class="fas fa-receipt"></i> Bill: <?= htmlspecialchars($bill_info['bill_number'] ?? 'N/A') ?>
                </span>
            <?php endif; ?>
        </div>
        
        <?php if (count($items) > 0): ?>
            <div class="table-wrap">
                <table class="medication-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Medication</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Quantity</th>
                            <th>Duration</th>
                            <th>Instructions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td class="font-semibold text-gray-800"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['dosage'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['frequency'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['quantity'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['duration'] ?? 'N/A') ?></td>
                                <td class="text-sm text-gray-500"><?= htmlspecialchars($item['instructions'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-pills"></i>
                <p>No medication items found for this prescription</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY CARD -->
    <!-- ================================================================ -->
    <div class="summary-card">
        <h4 class="summary-card-title">
            <i class="fas fa-file-alt" style="color:#0B5ED7;"></i> Prescription Summary
        </h4>
        <div class="summary-grid">
            <div class="summary-item">
                <span class="summary-label">Prescription Number</span>
                <span class="summary-value font-mono"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Patient</span>
                <span class="summary-value"><?= htmlspecialchars($prescription['patient_name'] ?? 'N/A') ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Doctor</span>
                <span class="summary-value">Dr. <?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Status</span>
                <span class="summary-value">
                    <span class="badge <?= getStatusBadgeClass($prescription['status'] ?? 'pending') ?>">
                        <i class="fas <?= getStatusIcon($prescription['status'] ?? 'pending') ?>"></i>
                        <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                    </span>
                </span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Total Items</span>
                <span class="summary-value"><?= count($items) ?> item(s)</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Created</span>
                <span class="summary-value"><?= date('M d, Y h:i A', strtotime($prescription['created_at'] ?? 'now')) ?></span>
            </div>
            <?php if (!empty($prescription['dispensed_at'])): ?>
                <div class="summary-item">
                    <span class="summary-label">Dispensed</span>
                    <span class="summary-value text-green-600"><?= date('M d, Y h:i A', strtotime($prescription['dispensed_at'])) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($bill_info): ?>
                <div class="summary-item">
                    <span class="summary-label">Bill</span>
                    <span class="summary-value">
                        <span class="font-mono"><?= htmlspecialchars($bill_info['bill_number'] ?? 'N/A') ?></span>
                        <span class="badge <?= $bill_info['bill_status'] === 'paid' ? 'badge-success' : 'badge-warning' ?>" style="font-size:0.55rem;padding:1px 8px;margin-left:4px;">
                            <?= ucfirst($bill_info['bill_status'] ?? 'Pending') ?>
                        </span>
                    </span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Bill Amount</span>
                    <span class="summary-value font-semibold">TSh <?= number_format($bill_info['total_price'] ?? 0, 0) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Prescription Details
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

    console.log('%c💊 Prescription Details - <?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔐 Session-based login active', 'font-size:12px; color:#34D399;');
    console.log('%c📋 Patient: <?= htmlspecialchars($prescription['patient_name'] ?? 'N/A') ?>', 'font-size:12px; color:#059669;');
    console.log('%c💊 Items: <?= count($items) ?>', 'font-size:12px; color:#64748B;');
    console.log('%c📋 Status: <?= ucfirst($prescription['status'] ?? 'Pending') ?>', 'font-size:12px; color:#64748B;');
    console.log('%c👨‍⚕️ Doctor: Dr. <?= htmlspecialchars($doctor_name) ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($doctor_branch_name) ?>', 'font-size:12px; color:#0B5ED7;');
    <?php if ($bill_info): ?>
        console.log('%c💰 Bill: <?= htmlspecialchars($bill_info['bill_number'] ?? 'N/A') ?> - TSh <?= number_format($bill_info['total_price'] ?? 0, 0) ?>', 'font-size:12px; color:#059669;');
    <?php endif; ?>
</script>

</body>
</html>