<?php
// ================================================================
// FILE: frontend/pages/admin/view_patient.php
// ADMIN - VIEW PATIENT DETAILS
// BRAICK DISPENSARY - BLUE THEME
// ================================================================

session_start();

// ================================================================
// FORCE SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET PARAMETERS
// ================================================================
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch_id'] ?? $_GET['branch'] ?? 'all';

if ($patient_id <= 0) {
    header('Location: patients.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH PATIENT DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            p.*,
            u.full_name as created_by_name,
            u2.full_name as assigned_doctor_name,
            u2.is_online as doctor_online,
            u2.specialty as doctor_specialty,
            b.name as branch_name,
            (SELECT COUNT(*) FROM visits WHERE patient_id = p.id) as total_visits,
            (SELECT COUNT(*) FROM visits WHERE patient_id = p.id AND status = 'pending') as pending_visits,
            (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id AND status = 'scheduled') as scheduled_appointments,
            (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id AND status = 'confirmed') as confirmed_appointments,
            (SELECT COUNT(*) FROM visits WHERE patient_id = p.id AND status = 'completed') as completed_visits,
            (SELECT COUNT(*) FROM prescriptions WHERE patient_id = p.id) as total_prescriptions,
            (SELECT COUNT(*) FROM lab_tests WHERE visit_id IN (SELECT id FROM visits WHERE patient_id = p.id)) as total_lab_tests,
            (SELECT COUNT(*) FROM patient_bills WHERE patient_id = p.id AND status = 'paid') as paid_bills,
            (SELECT COUNT(*) FROM patient_bills WHERE patient_id = p.id AND status = 'pending') as pending_bills
        FROM patients p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN users u2 ON p.assigned_doctor_id = u2.id
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE p.id = ?
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        header('Location: patients.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching patient: " . $e->getMessage());
    header('Location: patients.php?branch=' . urlencode($selected_branch_id) . '&error=database_error');
    exit;
}

// ================================================================
// CALCULATE AGE
// ================================================================
$age = null;
if (!empty($patient['date_of_birth'])) {
    $birthDate = new DateTime($patient['date_of_birth']);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
}

// ================================================================
// GET RECENT VISITS
// ================================================================
$recent_visits = [];
try {
    $stmt = $db->prepare("
        SELECT 
            v.id,
            v.visit_number,
            v.visit_date,
            v.status,
            v.visit_type,
            v.created_at,
            v.visit_total,
            v.payment_status,
            u.full_name as doctor_name
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ?
        ORDER BY v.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $recent_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_visits = [];
}

// ================================================================
// GET RECENT APPOINTMENTS
// ================================================================
$recent_appointments = [];
try {
    $stmt = $db->prepare("
        SELECT 
            a.id,
            a.appointment_date,
            a.status,
            a.visit_type,
            a.purpose,
            a.created_at,
            u.full_name as doctor_name
        FROM appointments a
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.patient_id = ?
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$patient_id]);
    $recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_appointments = [];
}

// ================================================================
// GET RECENT PRESCRIPTIONS
// ================================================================
$recent_prescriptions = [];
try {
    $stmt = $db->prepare("
        SELECT 
            pr.id,
            pr.prescription_number,
            pr.status,
            pr.created_at,
            pr.diagnosis,
            u.full_name as doctor_name
        FROM prescriptions pr
        LEFT JOIN users u ON pr.doctor_id = u.id
        WHERE pr.patient_id = ?
        ORDER BY pr.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$patient_id]);
    $recent_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_prescriptions = [];
}

// ================================================================
// GET RECENT BILLS
// ================================================================
$recent_bills = [];
try {
    $stmt = $db->prepare("
        SELECT 
            pb.id,
            pb.bill_number,
            pb.total_amount,
            pb.paid_amount,
            pb.balance,
            pb.status,
            pb.created_at
        FROM patient_bills pb
        WHERE pb.patient_id = ?
        ORDER BY pb.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$patient_id]);
    $recent_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_bills = [];
}

// ================================================================
// GET VITAL SIGNS (Latest)
// ================================================================
$vital_signs = [];
try {
    $stmt = $db->prepare("
        SELECT 
            vs.*,
            u.full_name as recorded_by_name
        FROM vital_signs vs
        LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE vs.patient_id = ?
        ORDER BY vs.recorded_at DESC
        LIMIT 1
    ");
    $stmt->execute([$patient_id]);
    $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $vital_signs = [];
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// STATUS BADGE CLASS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'active' => 'success',
        'inactive' => 'danger',
        'pending' => 'warning',
        'assigned' => 'info',
        'confirmed' => 'success',
        'scheduled' => 'warning',
        'completed' => 'success',
        'cancelled' => 'danger',
        'paid' => 'success',
        'partial' => 'warning',
        'dispensed' => 'success'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'active' => 'fa-check-circle',
        'inactive' => 'fa-times-circle',
        'pending' => 'fa-clock',
        'assigned' => 'fa-user-check',
        'confirmed' => 'fa-check-double',
        'scheduled' => 'fa-calendar-check',
        'completed' => 'fa-check-circle',
        'cancelled' => 'fa-times-circle',
        'paid' => 'fa-check-circle',
        'partial' => 'fa-clock',
        'dispensed' => 'fa-check-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER
// ================================================================
include_once '../../components/admin_header.php';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
include_once '../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Patient - <?= htmlspecialchars($patient['full_name'] ?? 'Patient') ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- html2pdf for PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        /* ================================================================
           ROOT VARIABLES - BOLDER BLUE THEME
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #073B8A);
            
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
            
            --teal: #0D9488;
            --teal-bg: #ECFDF5;
            
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
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
            --table-hover: #F8FAFC;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-light: #60A5FA;
            --primary-bg: #1E3A5F;
            --primary-gradient: linear-gradient(135deg, #2563EB, #1D4ED8);
            --primary-gradient-strong: linear-gradient(135deg, #1D4ED8, #1E40AF);
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --table-hover: #1E293B;
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
           TOP NAV - SHARED HEADER
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
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
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
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            transform: scale(1.02);
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
            border-radius: var(--radius);
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
        
        .branch-selector {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            font-size: 0.78rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .branch-selector:focus {
            border-color: var(--primary);
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
        
        /* ================================================================
           PAGE HEADER - BOLDER BLUE THEME
           ================================================================ */
        .page-header {
            background: var(--primary-gradient-strong);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -5%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
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
            background: rgba(255,255,255,0.12);
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
            transition: all 0.3s ease;
        }
        
        .page-header .header-badge:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: var(--radius);
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
        
        /* ================================================================
           PATIENT DETAILS CARD
           ================================================================ */
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .detail-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .detail-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        /* ================================================================
           STATS CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: var(--text-primary);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--primary-gradient-strong);
            border-radius: 0 3px 3px 0;
            opacity: 0.8;
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(11, 94, 215, 0.15);
        }
        
        .stat-card .stat-number {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.1;
        }
        
        .stat-card .stat-number.blue { color: var(--primary); }
        .stat-card .stat-number.green { color: #059669; }
        .stat-card .stat-number.orange { color: #F59E0B; }
        .stat-card .stat-number.purple { color: #7C3AED; }
        .stat-card .stat-number.teal { color: #0D9488; }
        .stat-card .stat-number.red { color: #DC2626; }
        
        .stat-card .stat-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
        }
        
        .stat-card .stat-icon-small {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        .stat-card .stat-icon-small.blue { background: var(--primary-bg); color: var(--primary); }
        .stat-card .stat-icon-small.green { background: #ECFDF5; color: #059669; }
        .stat-card .stat-icon-small.orange { background: #FFFBEB; color: #F59E0B; }
        .stat-card .stat-icon-small.purple { background: #F5F3FF; color: #7C3AED; }
        .stat-card .stat-icon-small.teal { background: #ECFDF5; color: #0D9488; }
        .stat-card .stat-icon-small.red { background: #FEF2F2; color: #DC2626; }
        
        [data-theme="dark"] .stat-card .stat-icon-small.blue { background: #1E3A5F; color: #3B82F6; }
        [data-theme="dark"] .stat-card .stat-icon-small.green { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .stat-card .stat-icon-small.orange { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .stat-card .stat-icon-small.purple { background: #2D1B4E; color: #A78BFA; }
        [data-theme="dark"] .stat-card .stat-icon-small.teal { background: #0F3D3D; color: #5EEAD4; }
        [data-theme="dark"] .stat-card .stat-icon-small.red { background: #3A1A1A; color: #F87171; }
        
        /* ================================================================
           DATA TABLE
           ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .table-container .card-header {
            padding: 14px 20px;
            background: var(--primary-gradient-strong);
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .table-container .card-header .card-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: white;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-container .card-header .card-title i {
            color: rgba(255,255,255,0.8);
        }
        
        .table-container .card-header .card-action {
            color: rgba(255,255,255,0.7);
            font-size: 0.65rem;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .table-container .card-header .card-action:hover {
            color: white;
        }
        
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.78rem;
        }
        
        .data-table thead th {
            background: var(--bg-body);
            color: var(--text-secondary);
            font-weight: 700;
            padding: 8px 12px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border-color);
            text-align: left;
        }
        
        [data-theme="dark"] .data-table thead th {
            background: #0F172A;
        }
        
        .data-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            color: white;
            letter-spacing: 0.02em;
        }
        
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        .badge-secondary { background: #64748B; }
        .badge-purple { background: #7C3AED; }
        .badge-teal { background: #0D9488; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        /* ================================================================
           VITAL SIGNS
           ================================================================ */
        .vital-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        
        .vital-item {
            background: var(--bg-body);
            border-radius: var(--radius);
            padding: 12px 16px;
            text-align: center;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .vital-item:hover {
            border-color: var(--primary);
        }
        
        .vital-item .vital-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .vital-item .vital-label {
            font-size: 0.55rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 600;
        }
        
        .vital-item .vital-unit {
            font-size: 0.5rem;
            color: var(--text-secondary);
        }
        
        .vital-item.blue { border-color: var(--primary); background: var(--primary-bg); }
        .vital-item.green { border-color: var(--success); background: var(--success-bg); }
        .vital-item.orange { border-color: var(--warning); background: var(--warning-bg); }
        .vital-item.purple { border-color: var(--purple); background: var(--purple-bg); }
        .vital-item.teal { border-color: var(--teal); background: var(--teal-bg); }
        
        [data-theme="dark"] .vital-item.blue { background: #1E3A5F; }
        [data-theme="dark"] .vital-item.green { background: #1A3A2A; }
        [data-theme="dark"] .vital-item.orange { background: #3D2E0A; }
        [data-theme="dark"] .vital-item.purple { background: #2D1B4E; }
        [data-theme="dark"] .vital-item.teal { background: #0F3D3D; }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 2rem;
            color: var(--border-color);
            margin-bottom: 8px;
        }
        
        .empty-state p {
            font-size: 0.8rem;
            margin: 0;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn-export {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            background: var(--success);
            color: white;
            box-shadow: 0 2px 12px rgba(5, 150, 105, 0.25);
        }
        
        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.35);
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 700;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .detail-card { padding: 16px; }
            .vital-grid { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .vital-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
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
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .footer, #sidebarToggle, .btn-export { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .detail-card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .table-container { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .page-header {
                background: #0B5ED7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-title, .page-subtitle, .header-badge, .role-badge-display {
                color: white !important;
            }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - SHARED HEADER -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content" id="patientContent">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-circle"></i>
                Patient Details
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-id-card"></i>
                <strong><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></strong>
                
                <!-- FIXED: times-circle icon with isset check -->
                <span class="header-badge">
                    <?php if (isset($patient['status']) && $patient['status'] === 'active'): ?>
                        <i class="fas fa-check-circle"></i> Active
                    <?php else: ?>
                        <i class="fas fa-times-circle"></i> Inactive
                    <?php endif; ?>
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i>
                    <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-calendar"></i>
                    <?= $age !== null ? $age . ' yrs' : 'N/A' ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <button onclick="exportPDF()" class="btn-export">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
            <a href="edit_patient.php?id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="patients.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <p class="detail-label"><i class="fas fa-user mr-1"></i> Full Name</p>
                <p class="detail-value"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-id-card mr-1"></i> Patient ID</p>
                <p class="detail-value"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-calendar-alt mr-1"></i> Date of Birth</p>
                <p class="detail-value">
                    <?= !empty($patient['date_of_birth']) ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'N/A' ?>
                    <?php if ($age !== null): ?>
                        <span class="text-sm text-gray-400">(<?= $age ?> yrs)</span>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-venus-mars mr-1"></i> Gender</p>
                <p class="detail-value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-phone mr-1"></i> Phone</p>
                <p class="detail-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-envelope mr-1"></i> Email</p>
                <p class="detail-value"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-map-marker-alt mr-1"></i> Address</p>
                <p class="detail-value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-tint mr-1"></i> Blood Group</p>
                <p class="detail-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-exclamation-triangle mr-1"></i> Allergies</p>
                <p class="detail-value"><?= htmlspecialchars($patient['allergies'] ?? 'None') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user-md mr-1"></i> Assigned Doctor</p>
                <p class="detail-value">
                    <?php if (!empty($patient['assigned_doctor_name'])): ?>
                        Dr. <?= htmlspecialchars($patient['assigned_doctor_name']) ?>
                        <?php if (isset($patient['doctor_online']) && $patient['doctor_online'] == 1): ?>
                            <span class="text-green-500 text-xs">🟢 Online</span>
                        <?php else: ?>
                            <span class="text-gray-400 text-xs">⚪ Offline</span>
                        <?php endif; ?>
                        <?php if (!empty($patient['doctor_specialty'])): ?>
                            <span class="text-xs text-gray-400">(<?= htmlspecialchars($patient['doctor_specialty']) ?>)</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-gray-400 text-sm">Not assigned</span>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-store mr-1"></i> Branch</p>
                <p class="detail-value"><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user-plus mr-1"></i> Registered By</p>
                <p class="detail-value"><?= htmlspecialchars($patient['created_by_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-clock mr-1"></i> Registered On</p>
                <p class="detail-value"><?= date('M d, Y h:i A', strtotime($patient['created_at'] ?? 'now')) ?></p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up" style="animation-delay:0.05s;">
        <a href="visits.php?patient_id=<?= $patient_id ?>" class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small blue"><i class="fas fa-clinic-medical"></i></div>
                <div>
                    <p class="stat-label">Total Visits</p>
                    <p class="stat-number blue"><?= number_format($patient['total_visits'] ?? 0) ?></p>
                </div>
            </div>
        </a>
        <a href="visits.php?patient_id=<?= $patient_id ?>&status=pending" class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small orange"><i class="fas fa-clock"></i></div>
                <div>
                    <p class="stat-label">Pending Visits</p>
                    <p class="stat-number orange"><?= number_format($patient['pending_visits'] ?? 0) ?></p>
                </div>
            </div>
        </a>
        <a href="appointments.php?patient_id=<?= $patient_id ?>" class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small purple"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <p class="stat-label">Appointments</p>
                    <p class="stat-number purple"><?= number_format(($patient['scheduled_appointments'] ?? 0) + ($patient['confirmed_appointments'] ?? 0)) ?></p>
                </div>
            </div>
        </a>
        <a href="prescriptions.php?patient_id=<?= $patient_id ?>" class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small teal"><i class="fas fa-prescription"></i></div>
                <div>
                    <p class="stat-label">Prescriptions</p>
                    <p class="stat-number teal"><?= number_format($patient['total_prescriptions'] ?? 0) ?></p>
                </div>
            </div>
        </a>
        <a href="lab_tests.php?patient_id=<?= $patient_id ?>" class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small purple"><i class="fas fa-flask"></i></div>
                <div>
                    <p class="stat-label">Lab Tests</p>
                    <p class="stat-number purple"><?= number_format($patient['total_lab_tests'] ?? 0) ?></p>
                </div>
            </div>
        </a>
        <a href="bills.php?patient_id=<?= $patient_id ?>" class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small green"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <p class="stat-label">Paid Bills</p>
                    <p class="stat-number green"><?= number_format($patient['paid_bills'] ?? 0) ?></p>
                </div>
            </div>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- VITAL SIGNS -->
    <!-- ================================================================ -->
    <?php if (!empty($vital_signs)): ?>
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="flex justify-between items-center mb-2">
            <h3 class="text-sm font-bold text-primary">
                <i class="fas fa-heartbeat"></i> Latest Vital Signs
            </h3>
            <span class="text-xs text-gray-400">Recorded: <?= date('M d, Y h:i A', strtotime($vital_signs['recorded_at'] ?? 'now')) ?></span>
        </div>
        <div class="vital-grid">
            <?php if (!empty($vital_signs['temperature'])): ?>
            <div class="vital-item blue">
                <div class="vital-value"><?= $vital_signs['temperature'] ?>°C</div>
                <div class="vital-label">Temperature</div>
            </div>
            <?php endif; ?>
            <?php if (!empty($vital_signs['blood_pressure_systolic']) && !empty($vital_signs['blood_pressure_diastolic'])): ?>
            <div class="vital-item green">
                <div class="vital-value"><?= $vital_signs['blood_pressure_systolic'] ?>/<?= $vital_signs['blood_pressure_diastolic'] ?></div>
                <div class="vital-label">Blood Pressure</div>
                <div class="vital-unit">mmHg</div>
            </div>
            <?php endif; ?>
            <?php if (!empty($vital_signs['pulse_rate'])): ?>
            <div class="vital-item orange">
                <div class="vital-value"><?= $vital_signs['pulse_rate'] ?></div>
                <div class="vital-label">Pulse Rate</div>
                <div class="vital-unit">bpm</div>
            </div>
            <?php endif; ?>
            <?php if (!empty($vital_signs['weight'])): ?>
            <div class="vital-item purple">
                <div class="vital-value"><?= $vital_signs['weight'] ?> kg</div>
                <div class="vital-label">Weight</div>
            </div>
            <?php endif; ?>
            <?php if (!empty($vital_signs['height'])): ?>
            <div class="vital-item teal">
                <div class="vital-value"><?= $vital_signs['height'] ?> cm</div>
                <div class="vital-label">Height</div>
            </div>
            <?php endif; ?>
            <?php if (!empty($vital_signs['bmi'])): ?>
            <div class="vital-item blue">
                <div class="vital-value"><?= $vital_signs['bmi'] ?></div>
                <div class="vital-label">BMI</div>
                <div class="vital-unit">kg/m²</div>
            </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($vital_signs['notes'])): ?>
        <div class="mt-2 text-xs text-gray-500">
            <i class="fas fa-sticky-note"></i> <?= htmlspecialchars($vital_signs['notes']) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($vital_signs['recorded_by_name'])): ?>
        <div class="mt-1 text-xs text-gray-400 text-right">
            Recorded by: <?= htmlspecialchars($vital_signs['recorded_by_name']) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- RECENT VISITS -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-clinic-medical"></i>
                Recent Visits (<?= count($recent_visits) ?>)
            </h3>
            <a href="visits.php?patient_id=<?= $patient_id ?>" class="card-action">View All →</a>
        </div>
        <?php if (count($recent_visits) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Visit #</th>
                            <th>Doctor</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_visits as $visit): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-info" style="font-size:0.5rem;padding:1px 8px;">
                                        <?= ucfirst($visit['visit_type'] ?? 'new') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($visit['status'] ?? 'pending') ?>">
                                        <?= ucfirst($visit['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($visit['visit_total'] > 0): ?>
                                        TSh <?= number_format($visit['visit_total'], 0) ?>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($visit['visit_date'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_visit.php?id=<?= $visit['id'] ?>&branch=<?= $selected_branch_id ?>" class="text-blue-600 text-xs hover:underline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clinic-medical"></i>
                <p>No visits found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT APPOINTMENTS -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-calendar-check"></i>
                Recent Appointments (<?= count($recent_appointments) ?>)
            </h3>
            <a href="appointments.php?patient_id=<?= $patient_id ?>" class="card-action">View All →</a>
        </div>
        <?php if (count($recent_appointments) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_appointments as $appointment): ?>
                            <tr>
                                <td><?= htmlspecialchars($appointment['doctor_name'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= date('M d, Y h:i A', strtotime($appointment['appointment_date'] ?? 'now')) ?></td>
                                <td>
                                    <span class="badge badge-info" style="font-size:0.5rem;padding:1px 8px;">
                                        <?= ucfirst($appointment['visit_type'] ?? 'new') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($appointment['status'] ?? 'scheduled') ?>">
                                        <?= ucfirst($appointment['status'] ?? 'Scheduled') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_appointment.php?id=<?= $appointment['id'] ?>&branch=<?= $selected_branch_id ?>" class="text-blue-600 text-xs hover:underline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-check"></i>
                <p>No appointments found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-prescription"></i>
                Recent Prescriptions (<?= count($recent_prescriptions) ?>)
            </h3>
            <a href="prescriptions.php?patient_id=<?= $patient_id ?>" class="card-action">View All →</a>
        </div>
        <?php if (count($recent_prescriptions) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Prescription #</th>
                            <th>Doctor</th>
                            <th>Diagnosis</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_prescriptions as $prescription): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($prescription['diagnosis'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($prescription['status'] ?? 'pending') ?>">
                                        <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($prescription['created_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_prescription.php?id=<?= $prescription['id'] ?>&branch=<?= $selected_branch_id ?>" class="text-blue-600 text-xs hover:underline">View</a>
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
    <!-- RECENT BILLS -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.3s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-receipt"></i>
                Recent Bills (<?= count($recent_bills) ?>)
            </h3>
            <a href="bills.php?patient_id=<?= $patient_id ?>" class="card-action">View All →</a>
        </div>
        <?php if (count($recent_bills) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_bills as $bill): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                                <td>TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                                <td>TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                <td>
                                    <?php if (($bill['balance'] ?? 0) > 0): ?>
                                        <span class="text-red-600 font-semibold">TSh <?= number_format($bill['balance'], 0) ?></span>
                                    <?php else: ?>
                                        <span class="text-green-600">TSh 0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($bill['status'] ?? 'pending') ?>">
                                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($bill['created_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_bill.php?id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" class="text-blue-600 text-xs hover:underline">View</a>
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
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 animate-fade-in-up" style="animation-delay:0.35s;">
        <a href="add_visit.php?patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" 
           class="bg-card border-2 border-border rounded-lg p-4 text-center hover:border-primary transition-all hover:shadow-md text-decoration-none">
            <i class="fas fa-clinic-medical text-2xl text-blue-600 block mb-2"></i>
            <span class="text-sm font-medium text-text-primary">Add Visit</span>
        </a>
        <a href="add_appointment.php?patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" 
           class="bg-card border-2 border-border rounded-lg p-4 text-center hover:border-purple-500 transition-all hover:shadow-md text-decoration-none">
            <i class="fas fa-calendar-plus text-2xl text-purple-600 block mb-2"></i>
            <span class="text-sm font-medium text-text-primary">Schedule Appointment</span>
        </a>
        <a href="add_prescription.php?patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" 
           class="bg-card border-2 border-border rounded-lg p-4 text-center hover:border-green-500 transition-all hover:shadow-md text-decoration-none">
            <i class="fas fa-prescription text-2xl text-green-600 block mb-2"></i>
            <span class="text-sm font-medium text-text-primary">Add Prescription</span>
        </a>
        <a href="edit_patient.php?id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" 
           class="bg-card border-2 border-border rounded-lg p-4 text-center hover:border-orange-500 transition-all hover:shadow-md text-decoration-none">
            <i class="fas fa-edit text-2xl text-orange-500 block mb-2"></i>
            <span class="text-sm font-medium text-text-primary">Edit Patient</span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Patient Details - <?= htmlspecialchars($patient['full_name'] ?? 'Patient') ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
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
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');

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
    // SEARCH
    // ================================================================
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // EXPORT PDF
    // ================================================================
    function exportPDF() {
        var element = document.getElementById('patientContent');
        var btn = document.querySelector('.btn-export');
        var originalText = btn.innerHTML;
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
        btn.disabled = true;
        
        var opt = {
            margin:        [10, 10, 10, 10],
            filename:     'Patient_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $patient['full_name'] ?? 'Patient') ?>_<?= date('Y-m-d') ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, letterRendering: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        // Add print styles for PDF
        var style = document.createElement('style');
        style.innerHTML = `
            .btn-export { display: none !important; }
            .btn-outline-light { display: none !important; }
            .top-nav { display: none !important; }
            .sidebar { display: none !important; }
            .footer { display: none !important; }
            .page-header { display: none !important; }
            .main-content { margin: 0 !important; padding: 10px !important; }
            .detail-card { border: 1px solid #ddd !important; box-shadow: none !important; }
            .table-container { border: 1px solid #ddd !important; box-shadow: none !important; }
            .table-container .card-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .table-container .card-header .card-title { color: white !important; }
            .badge { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        `;
        document.head.appendChild(style);
        
        // Generate PDF
        html2pdf().set(opt).from(element).save().then(function() {
            document.head.removeChild(style);
            btn.innerHTML = originalText;
            btn.disabled = false;
            showToast('✅ Success', 'PDF downloaded successfully!', 'success');
        }).catch(function(error) {
            console.error('PDF generation error:', error);
            document.head.removeChild(style);
            btn.innerHTML = originalText;
            btn.disabled = false;
            showToast('❌ Error', 'Failed to generate PDF: ' + error.message, 'error');
        });
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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    console.log('%c👤 Braick Dispensary - View Patient (BLUE THEME)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?> (ID: <?= $patient_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c📋 Patient ID: <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📅 Age: <?= $age !== null ? $age . ' yrs' : 'N/A' ?>', 'font-size:13px; color:#F59E0B;');
    console.log('%c🏥 Branch: <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?>', 'font-size:13px; color:#0D9488;');
    console.log('%c👨‍⚕️ Doctor: <?= htmlspecialchars($patient['assigned_doctor_name'] ?? 'Not assigned') ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📄 Export PDF button added', 'font-size:13px; color:#34D399;');
    console.log('%c✅ times-circle icon fixed with isset check', 'font-size:13px; color:#059669;');
</script>

</body>
</html>