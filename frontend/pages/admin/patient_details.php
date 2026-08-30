<?php
// ================================================================
// FILE: frontend/pages/admin/patient_details.php
// VIEW PATIENT - COMPLETE PATIENT DETAILS WITH PDF
// ORDER: Personal Info → Assigned Doctor → Vitals → Visit History → Symptoms → Lab Tests → Diagnosis → Medical Info → Prescriptions → Procedures → Tools → Bills
// BRAICK DISPENSARY - TUNAJARI AFYA YAKO
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK USER ACCESS (Reception or Admin)
// ================================================================
$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'reception';
$branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE CONNECTION
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

$message = '';
$message_type = '';

// Get patient ID
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id <= 0) {
    header('Location: patients.php?error=invalid_patient');
    exit;
}

// ✅ INITIALIZE ALL VARIABLES
$patient = null;
$active_visit = null;
$visit_history = [];
$bills = [];
$bill_items = [];
$procedures = [];
$tools = [];
$latest_vitals = null;
$prescriptions = [];
$lab_tests = [];
$vital_signs = [];
$age = 'N/A';
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

try {
    $db = Database::getInstance()->getConnection();
    
    // ================================================================
    // GET PATIENT DETAILS
    // ================================================================
    $stmt = $db->prepare("
        SELECT 
            p.*,
            u.full_name as created_by_name,
            b.name as branch_name,
            doc.full_name as assigned_doctor_name,
            doc.is_online as assigned_doctor_online
        FROM patients p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN users doc ON p.assigned_doctor_id = doc.id
        WHERE p.id = ? AND p.branch_id = ?
    ");
    $stmt->execute([$patient_id, $branch_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        header('Location: patients.php?error=patient_not_found');
        exit;
    }
    
    // ================================================================
    // GET ACTIVE VISIT
    // ================================================================
    $stmt = $db->prepare("
        SELECT v.*, 
               u.full_name as doctor_name,
               u.is_online as doctor_online
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ? AND v.status IN ('pending', 'assigned', 'with_doctor', 'lab_test')
        ORDER BY v.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$patient_id]);
    $active_visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET VISIT HISTORY - LAST 10
    // ================================================================
    $stmt = $db->prepare("
        SELECT v.*, 
               u.full_name as doctor_name,
               b.total_amount as bill_amount,
               b.status as bill_status,
               b.bill_number
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN bills b ON v.id = b.visit_id
        WHERE v.patient_id = ? 
        ORDER BY v.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $visit_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET BILLS - FROM bills TABLE
    // ================================================================
    $stmt = $db->prepare("
        SELECT b.*, 
               v.visit_number,
               u.full_name as created_by_name
        FROM bills b
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.patient_id = ?
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET BILL ITEMS
    // ================================================================
    $bill_items = [];
    foreach ($bills as $bill) {
        if (!empty($bill['id'])) {
            $stmt = $db->prepare("
                SELECT * FROM bill_items 
                WHERE bill_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$bill['id']]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $bill_items[$bill['id']] = $items;
        }
    }
    
    // ================================================================
    // GET PROCEDURES
    // ================================================================
    $stmt = $db->prepare("
        SELECT bi.*, 
               b.patient_id,
               b.bill_number,
               b.created_at as bill_date
        FROM bill_items bi
        JOIN bills b ON bi.bill_id = b.id
        WHERE b.patient_id = ? AND bi.item_type = 'procedure'
        ORDER BY bi.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET TOOLS/EQUIPMENT
    // ================================================================
    $stmt = $db->prepare("
        SELECT bi.*, 
               b.patient_id,
               b.bill_number,
               b.created_at as bill_date
        FROM bill_items bi
        JOIN bills b ON bi.bill_id = b.id
        WHERE b.patient_id = ? AND bi.item_type = 'equipment'
        ORDER BY bi.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET VITAL SIGNS
    // ================================================================
    $stmt = $db->prepare("
        SELECT vs.*, u.full_name as recorded_by_name
        FROM vital_signs vs
        LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE vs.patient_id = ?
        ORDER BY vs.recorded_at DESC
        LIMIT 1
    ");
    $stmt->execute([$patient_id]);
    $latest_vitals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET PRESCRIPTIONS
    // ================================================================
    $prescriptions = [];
    $stmt = $db->prepare("
        SELECT p.*, 
               u.full_name as doctor_name
        FROM prescriptions p
        LEFT JOIN users u ON p.doctor_id = u.id
        WHERE p.patient_id = ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET LAB TESTS
    // ================================================================
    $lab_tests = [];
    $stmt = $db->prepare("
        SELECT lt.*, 
               u.full_name as doctor_name
        FROM lab_tests lt
        LEFT JOIN users u ON lt.doctor_id = u.id
        WHERE lt.visit_id IN (
            SELECT id FROM visits WHERE patient_id = ?
        )
        ORDER BY lt.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate age
    $age = 'N/A';
    if (!empty($patient['date_of_birth'])) {
        $birthDate = new DateTime($patient['date_of_birth']);
        $today = new DateTime('today');
        $age = $birthDate->diff($today)->y;
    }
    
    // Get branch name
    $branch_name = $patient['branch_name'] ?? $branch_name;
    
    // Get logo path
    $logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
    if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_path)) {
        $logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $patient = null;
    $prescriptions = [];
    $lab_tests = [];
    $visit_history = [];
    $bills = [];
    $procedures = [];
    $tools = [];
}

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_notifications = $stmt->fetch()['total'] ?? 0;
    }
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/reception_header.php';
include_once __DIR__ . '/../../components/reception_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Patient - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
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
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #1A7AFF);
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
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow-blue: 0 4px 16px rgba(11, 94, 215, 0.15);
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
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --purple-bg: #2D1B5F;
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
        
        /* ================================================================
           TOP NAV - WITH CLOCK
           ================================================================ */
        .top-nav {
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .top-nav .datetime i {
            color: var(--primary-light);
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .top-nav .avatar:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all 0.3s;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover {
            background: var(--bg-body);
            color: var(--primary);
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        .branch-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        [data-theme="dark"] .branch-badge-display {
            background: #1A3A2A;
            color: #34D399;
        }
        
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
        
        .pdf-modal-overlay.active {
            display: flex;
        }
        
        .pdf-modal {
            background: var(--bg-card);
            border-radius: 14px;
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
            border-radius: 14px 14px 0 0;
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
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .pdf-modal-header .modal-actions .btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.78rem;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .pdf-modal-header .modal-actions .btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .pdf-modal-header .modal-actions .btn-danger-modal {
            background: rgba(220,38,38,0.3);
            border-color: rgba(220,38,38,0.2);
        }
        
        .pdf-modal-header .modal-actions .btn-danger-modal:hover {
            background: rgba(220,38,38,0.5);
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
            border-radius: 10px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }
        
        /* ================================================================
           PDF CONTENT STYLES
           ================================================================ */
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
            margin: 18px 0 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pdf-content .pdf-row {
            display: flex;
            padding: 4px 0;
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
            gap: 4px 20px;
        }
        
        .pdf-content .pdf-vital-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin: 8px 0;
        }
        
        .pdf-content .pdf-vital-item {
            background: var(--primary-bg);
            padding: 8px 12px;
            border-radius: 6px;
            border-left: 3px solid var(--primary);
            text-align: center;
        }
        
        .pdf-content .pdf-vital-item .vital-label {
            font-size: 0.55rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
        }
        
        .pdf-content .pdf-vital-item .vital-value {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-dark);
        }
        
        .pdf-content .pdf-vital-item .vital-unit {
            font-size: 0.55rem;
            color: var(--text-secondary);
        }
        
        .pdf-content .pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            margin: 8px 0;
        }
        
        .pdf-content .pdf-table th {
            background: var(--primary);
            color: white;
            padding: 6px 10px;
            text-align: left;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
        }
        
        .pdf-content .pdf-table td {
            padding: 5px 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .pdf-content .pdf-table tr:nth-child(even) td {
            background: var(--gray-50);
        }
        
        /* PDF Footer with Official Stamp */
        .pdf-content .pdf-footer {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
        }
        
        .pdf-content .pdf-footer .footer-stamp {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .pdf-content .pdf-footer .footer-left {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .pdf-content .pdf-footer .footer-left .signature-line {
            display: inline-block;
            width: 120px;
            border-bottom: 1px solid var(--text-secondary);
            margin-left: 4px;
        }
        
        .pdf-content .pdf-footer .stamp-box {
            text-align: center;
            padding: 8px 20px;
            border: 3px solid var(--primary);
            border-radius: 10px;
            background: var(--primary-bg);
            min-width: 180px;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-title {
            font-size: 0.55rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-name {
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--primary);
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-line {
            font-size: 0.65rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-date {
            font-size: 0.55rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        
        .pdf-content .pdf-footer .footer-bottom {
            text-align: center;
            margin-top: 12px;
            font-size: 0.6rem;
            color: var(--text-muted);
        }
        
        .pdf-content .pdf-footer .footer-bottom .footer-brand {
            color: var(--primary);
            font-weight: 700;
        }
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
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
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
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
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
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
        
        .page-header .btn-outline-light {
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
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .profile-header {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 28px 32px;
            border: 2px solid var(--primary-light);
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            align-items: center;
            box-shadow: var(--shadow-blue);
            transition: all 0.3s ease;
        }
        
        .profile-header:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.2);
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            color: #ffffff;
            flex-shrink: 0;
        }
        
        .profile-avatar.avatar-male { background: #0B5ED7; }
        .profile-avatar.avatar-female { background: #DC2626; }
        .profile-avatar.avatar-other { background: #7C3AED; }
        .profile-avatar.avatar-default { background: #0B5ED7; }
        
        .profile-info {
            flex: 1;
            min-width: 200px;
        }
        
        .profile-info .patient-name {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .profile-info .patient-id {
            font-size: 0.85rem;
            font-family: monospace;
            color: var(--text-secondary);
            background: var(--bg-body);
            padding: 2px 12px;
            border-radius: 12px;
            display: inline-block;
        }
        
        .profile-info .patient-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 8px;
        }
        
        .profile-info .patient-meta .meta-item {
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .profile-info .patient-meta .meta-item i {
            color: var(--primary);
            width: 16px;
        }
        
        .profile-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .detail-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px 28px;
            border: 2px solid var(--primary-light);
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-blue);
        }
        
        [data-theme="dark"] .detail-card {
            border-color: var(--primary);
        }
        
        [data-theme="dark"] .detail-card:hover {
            border-color: var(--primary-light);
        }
        
        .detail-card .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .detail-card .card-title i {
            color: var(--primary);
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 24px;
        }
        
        .detail-grid .detail-item {
            display: flex;
            flex-direction: column;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .detail-grid .detail-item:last-child,
        .detail-grid .detail-item:nth-last-child(2) {
            border-bottom: none;
        }
        
        .detail-grid .detail-item .detail-label {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .detail-grid .detail-item .detail-value {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .vital-grid-6 {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
        }
        
        .vital-item-blue {
            background: var(--primary-bg);
            border-radius: 10px;
            padding: 12px 14px;
            border-left: 4px solid var(--primary);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .vital-item-blue:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-blue);
        }
        
        .vital-item-blue .vital-label {
            font-size: 0.55rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
        }
        
        .vital-item-blue .vital-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-dark);
        }
        
        .vital-item-blue .vital-unit {
            font-size: 0.6rem;
            font-weight: 400;
            color: var(--text-secondary);
        }
        
        .vital-item-blue.green { border-left-color: var(--success); }
        .vital-item-blue.green .vital-value { color: var(--success-dark); }
        .vital-item-blue.purple { border-left-color: var(--purple); }
        .vital-item-blue.purple .vital-value { color: var(--purple); }
        .vital-item-blue.orange { border-left-color: var(--warning); }
        .vital-item-blue.orange .vital-value { color: var(--warning); }
        .vital-item-blue.teal { border-left-color: #0D9488; }
        .vital-item-blue.teal .vital-value { color: #0D9488; }
        .vital-item-blue.red { border-left-color: var(--danger); }
        .vital-item-blue.red .vital-value { color: var(--danger); }
        
        .bmi-label {
            display: inline-block;
            font-size: 0.5rem;
            font-weight: 600;
            padding: 1px 8px;
            border-radius: 10px;
            margin-left: 4px;
        }
        .bmi-label.normal { background: var(--success-bg); color: var(--success); }
        .bmi-label.underweight { background: var(--warning-bg); color: var(--warning); }
        .bmi-label.overweight { background: var(--warning-bg); color: var(--warning); }
        .bmi-label.obese { background: var(--danger-bg); color: var(--danger); }
        
        .badge {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 12px;
        }
        
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        
        .badge-pending { background: var(--warning-bg); color: var(--warning); }
        .badge-paid { background: var(--success-bg); color: var(--success); }
        .badge-partial { background: var(--primary-bg); color: var(--primary); }
        .badge-cancelled { background: var(--danger-bg); color: var(--danger); }
        
        .table-wrapper {
            overflow-x: auto;
        }
        
        .table-wrapper table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        .table-wrapper table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
        }
        
        .table-wrapper table tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .table-wrapper table tbody tr:hover {
            background: var(--bg-body);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(11, 94, 215, 0.35);
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
        }
        
        .btn-sm {
            padding: 4px 12px;
            font-size: 0.7rem;
            border-radius: 8px;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: var(--success-dark);
        }
        
        .btn-pdf {
            background: #DC2626;
            color: white;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
        }
        
        .btn-pdf:hover {
            background: #B91C1C;
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(220, 38, 38, 0.35);
        }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { 
            color: var(--primary); 
            font-weight: 600; 
        }
        
        .empty-state {
            text-align: center;
            padding: 30px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 2rem;
            color: var(--gray-300);
            display: block;
            margin-bottom: 8px;
        }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
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
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .vital-grid-6 { grid-template-columns: repeat(3, 1fr); }
            .pdf-content .pdf-vital-grid { grid-template-columns: repeat(2, 1fr); }
            .pdf-content .pdf-grid-2 { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .detail-grid { grid-template-columns: 1fr; }
            .profile-header { flex-direction: column; text-align: center; }
            .profile-info .patient-meta { justify-content: center; }
            .profile-actions { justify-content: center; width: 100%; }
            .vital-grid-6 { grid-template-columns: repeat(3, 1fr); }
            .pdf-modal-body .pdf-content { padding: 16px; }
            .pdf-content .pdf-row { flex-direction: column; }
            .pdf-content .pdf-row .pdf-label { width: 100%; }
            .pdf-content .pdf-footer .footer-stamp { flex-direction: column; align-items: center; }
            .pdf-content .pdf-vital-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .detail-card { padding: 16px; }
            .profile-actions .btn { flex: 1; justify-content: center; }
            .vital-grid-6 { grid-template-columns: repeat(2, 1fr); }
            .pdf-modal-header { flex-direction: column; gap: 10px; align-items: stretch; }
            .pdf-modal-header .modal-actions { justify-content: center; }
            .pdf-modal-body .pdf-content { padding: 12px; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        @media print {
            .no-print { display: none !important; }
            .top-nav { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
            .page-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .profile-header { border: 2px solid #0B5ED7 !important; }
            .detail-card { border: 1px solid #ddd !important; page-break-inside: avoid; break-inside: avoid; }
            .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .vital-item-blue { background: #E8F0FE !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .footer { display: none !important; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - WITH CLOCK -->
<!-- ================================================================ -->
<nav class="top-nav no-print">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search patients...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3 no-print">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <!-- CLOCK IN HEADER -->
        <span class="datetime" id="currentDateTime">
            <i class="fas fa-clock" style="color:var(--primary-light);"></i>
            <span id="clockDisplay" style="font-weight:500;"><?= date('d M Y • h:i:s A') ?></span>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if ($patient): ?>
    
    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-circle"></i>
                Patient Details
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-id-card"></i>
                View complete patient information for <strong><?= htmlspecialchars($patient['full_name']) ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-user"></i>
                    ID: <strong><?= htmlspecialchars($patient['patient_id']) ?></strong>
                </span>
                
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-ring"></i>
                    <?= htmlspecialchars($patient['marital_status'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div class="header-right no-print">
            <a href="patients.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
            <button onclick="generatePDF()" class="btn-outline-light" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- PROFILE HEADER -->
    <!-- ================================================================ -->
    <div class="profile-header animate-fade-in-up">
        <?php
            $gender = $patient['gender'] ?? '';
            if ($gender === 'Male') {
                $avatar_class = 'avatar-male';
            } elseif ($gender === 'Female') {
                $avatar_class = 'avatar-female';
            } elseif ($gender === 'Other') {
                $avatar_class = 'avatar-other';
            } else {
                $avatar_class = 'avatar-default';
            }
        ?>
        <div class="profile-avatar <?= $avatar_class ?>">
            <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
        </div>
        
        <div class="profile-info">
            <div>
                <span class="patient-name"><?= htmlspecialchars($patient['full_name']) ?></span>
                <span class="patient-id"><?= htmlspecialchars($patient['patient_id']) ?></span>
                <?php if (!empty($patient['assigned_doctor_name'])): ?>
                    <span class="badge badge-info">
                        <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($patient['assigned_doctor_name']) ?>
                        <?= $patient['assigned_doctor_online'] ? '🟢' : '⚪' ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="patient-meta">
                <span class="meta-item"><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                <span class="meta-item"><i class="fas fa-ring"></i> <?= htmlspecialchars($patient['marital_status'] ?? 'N/A') ?></span>
                <span class="meta-item"><i class="fas fa-calendar"></i> <?= !empty($patient['date_of_birth']) ? date('d M Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></span>
                <span class="meta-item"><i class="fas fa-clock"></i> <?= $age ?> years</span>
                <span class="meta-item"><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                <span class="meta-item"><i class="fas fa-envelope"></i> <?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
                <span class="meta-item"><i class="fas fa-tint"></i> <?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
                <span class="meta-item">
                    <i class="fas fa-circle" style="color:<?= ($patient['status'] ?? 'active') === 'active' ? '#059669' : '#DC2626' ?>;"></i>
                    <?= ucfirst($patient['status'] ?? 'Active') ?>
                </span>
            </div>
        </div>
        
        <div class="profile-actions no-print">
            <a href="assign_doctor.php?patient_id=<?= $patient['id'] ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-user-md"></i> Assign Doctor
            </a>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
            <button onclick="generatePDF()" class="btn btn-pdf btn-sm">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- 1. PERSONAL INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="card-title">
            <i class="fas fa-user"></i>
            Personal Information
        </div>
        
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Full Name</span>
                <span class="detail-value"><?= htmlspecialchars($patient['full_name']) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Patient ID</span>
                <span class="detail-value"><?= htmlspecialchars($patient['patient_id']) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Gender</span>
                <span class="detail-value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Marital Status</span>
                <span class="detail-value"><?= htmlspecialchars($patient['marital_status'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Date of Birth</span>
                <span class="detail-value">
                    <?= !empty($patient['date_of_birth']) ? date('d M Y', strtotime($patient['date_of_birth'])) : 'N/A' ?>
                    <?php if ($age !== 'N/A'): ?>
                        <span class="text-xs text-gray-400">(<?= $age ?> years)</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Age</span>
                <span class="detail-value"><?= $age ?> years</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Email</span>
                <span class="detail-value"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Blood Group</span>
                <span class="detail-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Branch</span>
                <span class="detail-value"><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Registered By</span>
                <span class="detail-value"><?= htmlspecialchars($patient['created_by_name'] ?? 'System') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Registered At</span>
                <span class="detail-value"><?= date('d M Y h:i A', strtotime($patient['created_at'])) ?></span>
            </div>
            <div class="detail-item" style="grid-column: 1 / -1;">
                <span class="detail-label">Address</span>
                <span class="detail-value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- 2. ASSIGNED DOCTOR & ACTIVE VISIT -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="card-title">
            <i class="fas fa-user-md"></i>
            Assigned Doctor & Active Visit
        </div>
        
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Assigned Doctor</span>
                <span class="detail-value">
                    <?php if (!empty($patient['assigned_doctor_name'])): ?>
                        <span class="badge badge-info">
                            <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($patient['assigned_doctor_name']) ?>
                            <?= $patient['assigned_doctor_online'] ? '🟢 Online' : '⚪ Offline' ?>
                        </span>
                    <?php else: ?>
                        <span class="text-gray-400">No doctor assigned</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Active Visit</span>
                <span class="detail-value">
                    <?php if ($active_visit): ?>
                        <span class="badge badge-<?= $active_visit['status'] ?? 'pending' ?>">
                            <?= ucfirst(str_replace('_', ' ', $active_visit['status'] ?? 'Pending')) ?>
                        </span>
                        <span class="text-xs text-gray-400">
                            #<?= htmlspecialchars($active_visit['visit_number'] ?? '') ?>
                        </span>
                        <?php if (!empty($active_visit['doctor_name'])): ?>
                            <span class="text-xs">- Dr. <?= htmlspecialchars($active_visit['doctor_name']) ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-gray-400">No active visit</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- 3. LATEST VITAL SIGNS -->
    <!-- ================================================================ -->
    <?php if ($latest_vitals): ?>
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="card-title" style="border-bottom: 2px solid var(--primary-light);">
            <i class="fas fa-heartbeat" style="color:#DC2626;"></i>
            Latest Vital Signs
            <span class="text-xs text-gray-400">(<?= date('d M Y h:i A', strtotime($latest_vitals['recorded_at'])) ?>)</span>
        </div>
        
        <div class="vital-grid-6">
            <div class="vital-item-blue">
                <span class="vital-label">🌡️ Temperature</span>
                <span class="vital-value"><?= $latest_vitals['temperature'] ?? 'N/A' ?> <span class="vital-unit">°C</span></span>
            </div>
            
            <div class="vital-item-blue green">
                <span class="vital-label">❤️ Blood Pressure</span>
                <span class="vital-value">
                    <?php if (!empty($latest_vitals['blood_pressure_systolic']) && !empty($latest_vitals['blood_pressure_diastolic'])): ?>
                        <?= $latest_vitals['blood_pressure_systolic'] ?> / <?= $latest_vitals['blood_pressure_diastolic'] ?> <span class="vital-unit">mmHg</span>
                    <?php elseif (!empty($latest_vitals['blood_pressure_systolic'])): ?>
                        <?= $latest_vitals['blood_pressure_systolic'] ?> <span class="vital-unit">mmHg</span>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </span>
            </div>
            
            <div class="vital-item-blue purple">
                <span class="vital-label">💓 Pulse Rate</span>
                <span class="vital-value"><?= $latest_vitals['pulse_rate'] ?? 'N/A' ?> <span class="vital-unit">bpm</span></span>
            </div>
            
            <div class="vital-item-blue orange">
                <span class="vital-label">⚖️ Weight</span>
                <span class="vital-value"><?= $latest_vitals['weight'] ?? 'N/A' ?> <span class="vital-unit">kg</span></span>
            </div>
            
            <div class="vital-item-blue teal">
                <span class="vital-label">📏 Height</span>
                <span class="vital-value"><?= $latest_vitals['height'] ?? 'N/A' ?> <span class="vital-unit">cm</span></span>
            </div>
            
            <div class="vital-item-blue red">
                <span class="vital-label">📊 BMI</span>
                <span class="vital-value">
                    <?= $latest_vitals['bmi'] ?? 'N/A' ?>
                    <span class="vital-unit">kg/m²</span>
                    <?php if (!empty($latest_vitals['bmi'])): ?>
                        <?php 
                            $bmi = $latest_vitals['bmi'];
                            if ($bmi < 18.5) $bmi_label = 'Underweight';
                            elseif ($bmi < 25) $bmi_label = 'Normal';
                            elseif ($bmi < 30) $bmi_label = 'Overweight';
                            else $bmi_label = 'Obese';
                            $bmi_class = strtolower($bmi_label);
                        ?>
                        <span class="bmi-label <?= $bmi_class ?>"><?= $bmi_label ?></span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <?php if (!empty($latest_vitals['notes'])): ?>
            <div class="mt-3 text-sm text-gray-500">
                <i class="fas fa-sticky-note mr-1"></i> Notes: <?= htmlspecialchars($latest_vitals['notes']) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($latest_vitals['recorded_by_name'])): ?>
            <div class="mt-2 text-xs text-gray-400">
                <i class="fas fa-user"></i> Recorded By: <?= htmlspecialchars($latest_vitals['recorded_by_name']) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- ================================================================ -->
    <!-- 4. VISIT HISTORY -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="card-title">
            <i class="fas fa-clock"></i>
            Visit History
            <span class="text-xs text-gray-400">(Last 10 visits)</span>
        </div>
        
        <?php if (count($visit_history) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Visit #</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Bill</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visit_history as $visit): ?>
                            <tr>
                                <td><span class="font-mono text-sm"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span></td>
                                <td><?= date('d M Y', strtotime($visit['created_at'])) ?></td>
                                <td><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-<?= $visit['status'] ?? 'pending' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'Pending')) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($visit['bill_amount'])): ?>
                                        <span class="font-semibold">TSh <?= number_format($visit['bill_amount'], 0) ?></span>
                                        <span class="badge badge-<?= $visit['bill_status'] ?? 'pending' ?>">
                                            <?= ucfirst($visit['bill_status'] ?? 'Pending') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">No bill</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view_visit.php?id=<?= $visit['id'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clock"></i>
                <p>No visit history found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- ================================================================ -->
    <!-- 5. SYMPTOMS (From Active Visit) -->
    <!-- ================================================================ -->
    <?php if ($active_visit && !empty($active_visit['symptoms'])): ?>
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="card-title">
            <i class="fas fa-notes-medical"></i>
            Symptoms & Complaint
        </div>
        
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Symptoms</span>
                <span class="detail-value"><?= htmlspecialchars($active_visit['symptoms'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Complaint / Reason</span>
                <span class="detail-value"><?= htmlspecialchars($active_visit['complaint'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($active_visit['notes'])): ?>
            <div class="detail-item" style="grid-column: 1 / -1;">
                <span class="detail-label">Notes</span>
                <span class="detail-value"><?= htmlspecialchars($active_visit['notes']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ================================================================ -->
    <!-- 6. LAB TESTS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.3s;">
        <div class="card-title">
            <i class="fas fa-flask"></i>
            Lab Tests
            <span class="text-xs text-gray-400">(Last 10)</span>
        </div>
        
        <?php if (!empty($lab_tests) && count($lab_tests) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Results</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $test): ?>
                            <tr>
                                <td><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                <td><?= date('d M Y', strtotime($test['created_at'])) ?></td>
                                <td><?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-<?= $test['status'] ?? 'pending' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'Pending')) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($test['results'])): ?>
                                        <span class="text-success">✅ Results available</span>
                                    <?php else: ?>
                                        <span class="text-gray-400">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view_lab.php?id=<?= $test['id'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-flask"></i>
                <p>No lab tests found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- ================================================================ -->
    <!-- 7. DIAGNOSIS (From Active Visit) -->
    <!-- ================================================================ -->
    <?php if ($active_visit && !empty($active_visit['diagnosis'])): ?>
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.35s;">
        <div class="card-title">
            <i class="fas fa-stethoscope"></i>
            Diagnosis & Treatment
        </div>
        
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Diagnosis</span>
                <span class="detail-value"><?= htmlspecialchars($active_visit['diagnosis'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Treatment</span>
                <span class="detail-value"><?= htmlspecialchars($active_visit['treatment'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($active_visit['follow_up_date'])): ?>
            <div class="detail-item">
                <span class="detail-label">Follow-up Date</span>
                <span class="detail-value"><?= date('d M Y', strtotime($active_visit['follow_up_date'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ================================================================ -->
    <!-- 8. MEDICAL INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.4s;">
        <div class="card-title">
            <i class="fas fa-notes-medical"></i>
            Medical Information
        </div>
        
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Blood Group</span>
                <span class="detail-value"><?= htmlspecialchars($patient['blood_group'] ?? 'Not recorded') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Emergency Contact</span>
                <span class="detail-value"><?= htmlspecialchars($patient['emergency_contact'] ?? 'Not recorded') ?></span>
            </div>
            <div class="detail-item" style="grid-column: 1 / -1;">
                <span class="detail-label">Allergies</span>
                <span class="detail-value">
                    <?php if (!empty($patient['allergies'])): ?>
                        <?php 
                            $allergy_list = array_map('trim', explode(',', $patient['allergies']));
                            foreach ($allergy_list as $allergy): 
                        ?>
                            <span class="badge badge-danger" style="margin:2px;">⚠️ <?= htmlspecialchars($allergy) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-gray-400">No known allergies</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- 9. PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.45s;">
        <div class="card-title">
            <i class="fas fa-prescription"></i>
            Prescriptions
            <span class="text-xs text-gray-400">(Last 10)</span>
        </div>
        
        <?php if (!empty($prescriptions) && count($prescriptions) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Prescription #</th>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Medication</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $prescription): ?>
                            <tr>
                                <td><span class="font-mono text-sm"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></span></td>
                                <td><?= date('d M Y', strtotime($prescription['created_at'])) ?></td>
                                <td><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($prescription['medication'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-<?= $prescription['status'] ?? 'pending' ?>">
                                        <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_prescription.php?id=<?= $prescription['id'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription"></i>
                <p>No prescriptions found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- ================================================================ -->
    <!-- 10. PROCEDURES -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.5s;">
        <div class="card-title">
            <i class="fas fa-syringe" style="color:#7C3AED;"></i>
            Procedures
            <span class="text-xs text-gray-400">(Last 10)</span>
        </div>
        
        <?php if (!empty($procedures) && count($procedures) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Procedure Name</th>
                            <th>Date</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Bill #</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($procedures as $procedure): ?>
                            <tr>
                                <td><?= htmlspecialchars($procedure['item_name'] ?? 'N/A') ?></td>
                                <td><?= date('d M Y', strtotime($procedure['created_at'])) ?></td>
                                <td><?= $procedure['quantity'] ?? 1 ?></td>
                                <td>TSh <?= number_format($procedure['unit_price'] ?? 0, 0) ?></td>
                                <td><span class="font-semibold">TSh <?= number_format($procedure['total_price'] ?? 0, 0) ?></span></td>
                                <td>
                                    <span class="badge badge-<?= $procedure['status'] ?? 'pending' ?>">
                                        <?= ucfirst($procedure['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td><span class="font-mono text-sm"><?= htmlspecialchars($procedure['bill_number'] ?? 'N/A') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-syringe"></i>
                <p>No procedures found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- ================================================================ -->
    <!-- 11. TOOLS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.55s;">
        <div class="card-title">
            <i class="fas fa-tools" style="color:#D97706;"></i>
            Tools
            <span class="text-xs text-gray-400">(Last 10)</span>
        </div>
        
        <?php if (!empty($tools) && count($tools) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Tool Name</th>
                            <th>Date</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Bill #</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tools as $tool): ?>
                            <tr>
                                <td><?= htmlspecialchars($tool['item_name'] ?? 'N/A') ?></td>
                                <td><?= date('d M Y', strtotime($tool['created_at'])) ?></td>
                                <td><?= $tool['quantity'] ?? 1 ?></td>
                                <td>TSh <?= number_format($tool['unit_price'] ?? 0, 0) ?></td>
                                <td><span class="font-semibold">TSh <?= number_format($tool['total_price'] ?? 0, 0) ?></span></td>
                                <td>
                                    <span class="badge badge-<?= $tool['status'] ?? 'pending' ?>">
                                        <?= ucfirst($tool['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td><span class="font-mono text-sm"><?= htmlspecialchars($tool['bill_number'] ?? 'N/A') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-tools"></i>
                <p>No tools found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- ================================================================ -->
    <!-- 12. BILLS - CHINI -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.6s;">
        <div class="card-title">
            <i class="fas fa-receipt"></i>
            Bills
            <span class="text-xs text-gray-400">(Last 10 bills)</span>
        </div>
        
        <?php if (!empty($bills) && count($bills) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Date</th>
                            <th>Visit</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $bill): ?>
                            <tr>
                                <td><span class="font-mono text-sm"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></span></td>
                                <td><?= date('d M Y', strtotime($bill['created_at'])) ?></td>
                                <td><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></td>
                                <td><span class="font-semibold">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></span></td>
                                <td>TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                <td>TSh <?= number_format($bill['balance'] ?? 0, 0) ?></td>
                                <td>
                                    <span class="badge badge-<?= $bill['status'] ?? 'pending' ?>">
                                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_bill.php?id=<?= $bill['id'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>No bills found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- ================================================================ -->
    <!-- FOOTER: BRAICK DISPENSARY, TUNAJARI AFYA YAKO -->
    <!-- ================================================================ -->
    <footer class="footer no-print" style="border-top: 3px solid var(--primary); padding: 20px 0; margin-top: 30px;">
        <div style="text-align: center;">
            <div style="font-size: 1.2rem; font-weight: 800; color: var(--primary); letter-spacing: 1px;">
                BRAICK DISPENSARY
            </div>
            <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-secondary); margin-top: 4px;">
                <i class="fas fa-heart" style="color: #DC2626;"></i> 
                TUNAJARI AFYA YAKO 
                <i class="fas fa-heart" style="color: #DC2626;"></i>
            </div>
            <div style="font-size: 0.7rem; color: var(--text-secondary); margin-top: 8px;">
                <span class="footer-brand">Braick Dispensary</span> Management System
                <span class="text-gray-300 mx-2">|</span>
                View Patient
                <span class="text-gray-300 mx-2">|</span>
                <?= htmlspecialchars($patient['full_name']) ?>
                <span class="text-gray-300 mx-2">|</span>
                <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
                <span class="text-gray-300 mx-2">|</span>
                &copy; <?= date('Y') ?> All rights reserved
            </div>
        </div>
    </footer>
    
    <?php else: ?>
    
    <div class="detail-card">
        <div class="empty-state">
            <i class="fas fa-user-slash" style="font-size:3rem;"></i>
            <h3 style="margin-top:12px;">Patient Not Found</h3>
            <p class="text-gray-400">The patient you are looking for does not exist.</p>
            <a href="patients.php" class="btn btn-primary mt-3">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>
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
                Patient PDF Preview - <?= htmlspecialchars($patient['full_name'] ?? 'Patient') ?>
            </div>
            <div class="modal-actions">
                <button onclick="downloadPDF()" class="btn btn-sm" id="downloadPdfBtn">
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
    // CLOCK UPDATE
    // ================================================================
    function updateClock() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('clockDisplay');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

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
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
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
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    // ================================================================
    // GENERATE PDF - WITH OFFICIAL STAMP
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        
        // Patient data from PHP
        var patientData = {
            id: '<?= $patient['id'] ?? 0 ?>',
            patient_id: '<?= addslashes($patient['patient_id'] ?? 'N/A') ?>',
            full_name: '<?= addslashes($patient['full_name'] ?? 'N/A') ?>',
            gender: '<?= addslashes($patient['gender'] ?? 'N/A') ?>',
            marital_status: '<?= addslashes($patient['marital_status'] ?? 'N/A') ?>',
            date_of_birth: '<?= !empty($patient['date_of_birth']) ? date('d/m/Y', strtotime($patient['date_of_birth'])) : 'N/A' ?>',
            age: '<?= $age ?>',
            phone: '<?= addslashes($patient['phone'] ?? 'N/A') ?>',
            email: '<?= addslashes($patient['email'] ?? 'N/A') ?>',
            address: '<?= addslashes($patient['address'] ?? 'N/A') ?>',
            blood_group: '<?= addslashes($patient['blood_group'] ?? 'N/A') ?>',
            allergies: '<?= addslashes($patient['allergies'] ?? 'None') ?>',
            emergency_contact: '<?= addslashes($patient['emergency_contact'] ?? 'N/A') ?>',
            branch_name: '<?= addslashes($patient['branch_name'] ?? $branch_name) ?>',
            assigned_doctor: '<?= addslashes($patient['assigned_doctor_name'] ?? 'Not Assigned') ?>',
            created_at: '<?= date('d/m/Y h:i A', strtotime($patient['created_at'] ?? 'now')) ?>',
            created_by: '<?= addslashes($patient['created_by_name'] ?? 'System') ?>'
        };
        
        // Vital signs
        var vitals = <?= $latest_vitals ? json_encode($latest_vitals) : 'null' ?>;
        
        // Visit history
        var visitHistory = <?= json_encode($visit_history) ?>;
        
        // Bills
        var bills = <?= json_encode($bills) ?>;
        
        // Procedures
        var procedures = <?= json_encode($procedures) ?>;
        
        // Tools
        var tools = <?= json_encode($tools) ?>;
        
        // Prescriptions
        var prescriptions = <?= json_encode($prescriptions) ?>;
        
        // Lab tests
        var labTests = <?= json_encode($lab_tests) ?>;
        
        // Active visit
        var activeVisit = <?= $active_visit ? json_encode($active_visit) : 'null' ?>;
        
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
                ${vitals.notes ? `<div style="margin-top:6px;font-size:0.75rem;color:var(--text-secondary);"><strong>Notes:</strong> ${vitals.notes}</div>` : ''}
                ${vitals.recorded_by_name ? `<div style="margin-top:4px;font-size:0.65rem;color:var(--text-muted);">Recorded By: ${vitals.recorded_by_name}</div>` : ''}
            `;
        } else {
            vitalsHtml = `<p style="color:var(--text-secondary);">No vital signs recorded</p>`;
        }
        
        // Build visit history HTML
        var visitHtml = '';
        if (visitHistory && visitHistory.length > 0) {
            visitHtml = `
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th>Visit #</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Bill</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${visitHistory.map(function(v) {
                            return `
                                <tr>
                                    <td>${v.visit_number || 'N/A'}</td>
                                    <td>${new Date(v.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                                    <td>${v.visit_type || 'N/A'}</td>
                                    <td>${v.doctor_name || 'N/A'}</td>
                                    <td>${v.status || 'N/A'}</td>
                                    <td>${v.bill_amount ? 'TSh ' + Number(v.bill_amount).toLocaleString() : 'N/A'}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
        } else {
            visitHtml = `<p style="color:var(--text-secondary);">No visit history</p>`;
        }
        
        // Build symptoms HTML
        var symptomsHtml = '';
        if (activeVisit) {
            symptomsHtml = `
                <div class="pdf-grid-2">
                    <div class="pdf-row"><span class="pdf-label">Symptoms</span><span class="pdf-value">${activeVisit.symptoms || 'N/A'}</span></div>
                    <div class="pdf-row"><span class="pdf-label">Complaint</span><span class="pdf-value">${activeVisit.complaint || 'N/A'}</span></div>
                    ${activeVisit.notes ? `<div class="pdf-row" style="grid-column: 1 / -1;"><span class="pdf-label">Notes</span><span class="pdf-value">${activeVisit.notes}</span></div>` : ''}
                </div>
            `;
        } else {
            symptomsHtml = `<p style="color:var(--text-secondary);">No active visit symptoms</p>`;
        }
        
        // Build diagnosis HTML
        var diagnosisHtml = '';
        if (activeVisit && (activeVisit.diagnosis || activeVisit.treatment)) {
            diagnosisHtml = `
                <div class="pdf-grid-2">
                    <div class="pdf-row"><span class="pdf-label">Diagnosis</span><span class="pdf-value">${activeVisit.diagnosis || 'N/A'}</span></div>
                    <div class="pdf-row"><span class="pdf-label">Treatment</span><span class="pdf-value">${activeVisit.treatment || 'N/A'}</span></div>
                    ${activeVisit.follow_up_date ? `<div class="pdf-row"><span class="pdf-label">Follow-up Date</span><span class="pdf-value">${new Date(activeVisit.follow_up_date).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })}</span></div>` : ''}
                </div>
            `;
        } else {
            diagnosisHtml = `<p style="color:var(--text-secondary);">No diagnosis recorded</p>`;
        }
        
        // Build bills HTML
        var billsHtml = '';
        if (bills && bills.length > 0) {
            billsHtml = `
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${bills.map(function(b) {
                            return `
                                <tr>
                                    <td>${b.bill_number || 'N/A'}</td>
                                    <td>${new Date(b.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                                    <td>TSh ${Number(b.total_amount || 0).toLocaleString()}</td>
                                    <td>TSh ${Number(b.paid_amount || 0).toLocaleString()}</td>
                                    <td>TSh ${Number(b.balance || 0).toLocaleString()}</td>
                                    <td>${b.status || 'N/A'}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
        } else {
            billsHtml = `<p style="color:var(--text-secondary);">No bills found</p>`;
        }
        
        // Build prescriptions HTML        var prescriptionsHtml = '';
        if (prescriptions && prescriptions.length > 0) {
            prescriptionsHtml = `
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th>Prescription #</th>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Medication</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${prescriptions.map(function(p) {
                            return `
                                <tr>
                                    <td>${p.prescription_number || 'N/A'}</td>
                                    <td>${new Date(p.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                                    <td>${p.doctor_name || 'N/A'}</td>
                                    <td>${p.medication || 'N/A'}</td>
                                    <td>${p.status || 'N/A'}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
        } else {
            prescriptionsHtml = `<p style="color:var(--text-secondary);">No prescriptions found</p>`;
        }
        
        // Build lab tests HTML
        var labTestsHtml = '';
        if (labTests && labTests.length > 0) {
            labTestsHtml = `
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Results</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${labTests.map(function(lt) {
                            return `
                                <tr>
                                    <td>${lt.test_name || 'N/A'}</td>
                                    <td>${new Date(lt.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                                    <td>${lt.doctor_name || 'N/A'}</td>
                                    <td>${lt.status || 'N/A'}</td>
                                    <td>${lt.results ? '✅ Available' : '⏳ Pending'}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
        } else {
            labTestsHtml = `<p style="color:var(--text-secondary);">No lab tests found</p>`;
        }
        
        // Build procedures HTML
        var proceduresHtml = '';
        if (procedures && procedures.length > 0) {
            proceduresHtml = `
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th>Procedure</th>
                            <th>Date</th>
                            <th>Qty</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${procedures.map(function(p) {
                            return `
                                <tr>
                                    <td>${p.item_name || 'N/A'}</td>
                                    <td>${new Date(p.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                                    <td>${p.quantity || 1}</td>
                                    <td>TSh ${Number(p.total_price || 0).toLocaleString()}</td>
                                    <td>${p.status || 'N/A'}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
        } else {
            proceduresHtml = `<p style="color:var(--text-secondary);">No procedures found</p>`;
        }
        
        // Build tools HTML
        var toolsHtml = '';
        if (tools && tools.length > 0) {
            toolsHtml = `
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th>Tool Name</th>
                            <th>Date</th>
                            <th>Qty</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${tools.map(function(t) {
                            return `
                                <tr>
                                    <td>${t.item_name || 'N/A'}</td>
                                    <td>${new Date(t.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                                    <td>${t.quantity || 1}</td>
                                    <td>TSh ${Number(t.total_price || 0).toLocaleString()}</td>
                                    <td>${t.status || 'N/A'}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
        } else {
            toolsHtml = `<p style="color:var(--text-secondary);">No tools found</p>`;
        }
        
        var html = `
            <div class="pdf-header">
                <div class="pdf-logo">
                    <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" onerror="this.style.display='none'">
                    <span class="clinic-name">BRAICK DISPENSARY</span>
                </div>
                <div class="clinic-sub">Quality Healthcare Services • ${patientData.branch_name}</div>
                <div class="doc-title">📋 Patient Medical Record</div>
                <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:4px;">
                    Report Generated: ${reportDate} • ${reportTime}
                </div>
            </div>
            
            <!-- 1. Personal Information -->
            <div class="section-title">👤 Personal Information</div>
            <div class="pdf-grid-2">
                <div class="pdf-row"><span class="pdf-label">Full Name</span><span class="pdf-value"><strong>${patientData.full_name}</strong></span></div>
                <div class="pdf-row"><span class="pdf-label">Patient ID</span><span class="pdf-value">${patientData.patient_id}</span></div>
                <div class="pdf-row"><span class="pdf-label">Gender</span><span class="pdf-value">${patientData.gender}</span></div>
                <div class="pdf-row"><span class="pdf-label">Marital Status</span><span class="pdf-value">${patientData.marital_status}</span></div>
                <div class="pdf-row"><span class="pdf-label">Date of Birth</span><span class="pdf-value">${patientData.date_of_birth} (${patientData.age} years)</span></div>
                <div class="pdf-row"><span class="pdf-label">Phone</span><span class="pdf-value">${patientData.phone}</span></div>
                <div class="pdf-row"><span class="pdf-label">Email</span><span class="pdf-value">${patientData.email}</span></div>
                <div class="pdf-row"><span class="pdf-label">Blood Group</span><span class="pdf-value">${patientData.blood_group}</span></div>
                <div class="pdf-row" style="grid-column: 1 / -1;"><span class="pdf-label">Address</span><span class="pdf-value">${patientData.address}</span></div>
                <div class="pdf-row"><span class="pdf-label">Registered</span><span class="pdf-value">${patientData.created_at} by ${patientData.created_by}</span></div>
                <div class="pdf-row"><span class="pdf-label">Branch</span><span class="pdf-value">${patientData.branch_name}</span></div>
            </div>
            
            <!-- 2. Assigned Doctor -->
            <div class="section-title">👨‍⚕️ Assigned Doctor & Active Visit</div>
            <div class="pdf-grid-2">
                <div class="pdf-row"><span class="pdf-label">Assigned Doctor</span><span class="pdf-value">${patientData.assigned_doctor}</span></div>
                <div class="pdf-row"><span class="pdf-label">Active Visit</span><span class="pdf-value">${activeVisit ? activeVisit.visit_number + ' (' + activeVisit.status + ')' : 'No active visit'}</span></div>
            </div>
            
            <!-- 3. Vital Signs -->
            <div class="section-title">❤️ Vital Signs</div>
            ${vitalsHtml}
            
            <!-- 4. Visit History -->
            <div class="section-title">📋 Visit History (Last 10)</div>
            ${visitHtml}
            
            <!-- 5. Symptoms -->
            <div class="section-title">🩺 Symptoms & Complaint</div>
            ${symptomsHtml}
            
            <!-- 6. Lab Tests -->
            <div class="section-title">🧪 Lab Tests (Last 10)</div>
            ${labTestsHtml}
            
            <!-- 7. Diagnosis -->
            <div class="section-title">📋 Diagnosis & Treatment</div>
            ${diagnosisHtml}
            
            <!-- 8. Medical Information -->
            <div class="section-title">🏥 Medical Information</div>
            <div class="pdf-grid-2">
                <div class="pdf-row"><span class="pdf-label">Blood Group</span><span class="pdf-value">${patientData.blood_group}</span></div>
                <div class="pdf-row"><span class="pdf-label">Emergency Contact</span><span class="pdf-value">${patientData.emergency_contact}</span></div>
                <div class="pdf-row" style="grid-column: 1 / -1;"><span class="pdf-label">Allergies</span><span class="pdf-value">${patientData.allergies}</span></div>
            </div>
            
            <!-- 9. Prescriptions -->
            <div class="section-title">💊 Prescriptions (Last 10)</div>
            ${prescriptionsHtml}
            
            <!-- 10. Procedures -->
            <div class="section-title">💉 Procedures (Last 10)</div>
            ${proceduresHtml}
            
            <!-- 11. Tools -->
            <div class="section-title">🔧 Tools / Equipment (Last 10)</div>
            ${toolsHtml}
            
            <!-- 12. Bills -->
            <div class="section-title">💰 Bills (Last 10)</div>
            ${billsHtml}
            
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
                    <span style="font-weight:600;color:#DC2626;">❤️ TUNAJARI AFYA YAKO</span> • 
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
            margin: [10, 10, 10, 10],
            filename: 'Patient_<?= htmlspecialchars($patient['full_name'] ?? 'patient') ?>_<?= $patient['id'] ?>.pdf',
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

    // ================================================================
    // SHOW TOAST FOR PDF ACTIONS
    // ================================================================
    document.getElementById('downloadPdfBtn')?.addEventListener('click', function() {
        showToast('📄 PDF Download', 'Downloading patient PDF...', 'info');
    });

    console.log('%c👤 Braick - View Patient (Updated Order)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Patient: <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c🆔 ID: <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ ORDER: Personal Info → Assigned Doctor → Vitals → Visit History → Symptoms → Lab Tests → Diagnosis → Medical Info → Prescriptions → Procedures → Tools → Bills', 'font-size:13px; color:#0B5ED7;');
    console.log('%c❤️ Footer: BRAICK DISPENSARY, TUNAJARI AFYA YAKO', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>