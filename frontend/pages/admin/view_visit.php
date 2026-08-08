<?php
// ================================================================
// FILE: frontend/pages/admin/view_visit.php
// ADMIN - VIEW VISIT DETAILS
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
$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : ($_SESSION['branch_id'] ?? 1);

if ($visit_id <= 0) {
    header('Location: visits.php?branch_id=' . $branch_id . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH VISIT DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        v.*,
        p.id as patient_id,
        p.patient_id as patient_number,
        p.full_name as patient_name,
        p.gender as patient_gender,
        p.date_of_birth as patient_dob,
        p.phone as patient_phone,
        p.email as patient_email,
        p.address as patient_address,
        p.blood_group as patient_blood_group,
        p.allergies as patient_allergies,
        u.id as doctor_id,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty,
        u.phone as doctor_phone,
        r.id as receptionist_id,
        r.full_name as receptionist_name,
        b.name as branch_name
    FROM visits v
    LEFT JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    LEFT JOIN users r ON v.receptionist_id = r.id
    LEFT JOIN branches b ON v.branch_id = b.id
    WHERE v.id = ?
");
$stmt->execute([$visit_id]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visit) {
    header('Location: visits.php?branch_id=' . $branch_id . '&error=notfound');
    exit;
}

// ================================================================
// FETCH PRESCRIPTIONS
// ================================================================
$prescriptions = [];
try {
    $stmt = $db->prepare("
        SELECT 
            p.*,
            u.full_name as doctor_name,
            (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = p.id) as item_count
        FROM prescriptions p
        LEFT JOIN users u ON p.doctor_id = u.id
        WHERE p.visit_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $prescriptions = [];
}

// ================================================================
// FETCH PRESCRIPTION ITEMS
// ================================================================
$prescription_items = [];
try {
    $stmt = $db->prepare("
        SELECT 
            pi.*,
            p.prescription_number
        FROM prescription_items pi
        LEFT JOIN prescriptions p ON pi.prescription_id = p.id
        WHERE p.visit_id = ?
        ORDER BY pi.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $prescription_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $prescription_items = [];
}

// ================================================================
// FETCH LAB TESTS
// ================================================================
$lab_tests = [];
try {
    $stmt = $db->prepare("
        SELECT 
            lt.*,
            u.full_name as doctor_name,
            ltu.full_name as technician_name
        FROM lab_tests lt
        LEFT JOIN users u ON lt.doctor_id = u.id
        LEFT JOIN users ltu ON lt.lab_technician_id = ltu.id
        WHERE lt.visit_id = ?
        ORDER BY lt.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lab_tests = [];
}

// ================================================================
// FETCH BILLS
// ================================================================
$bills = [];
try {
    $stmt = $db->prepare("
        SELECT 
            pb.*,
            u.full_name as created_by_name
        FROM patient_bills pb
        LEFT JOIN users u ON pb.created_by = u.id
        WHERE pb.visit_id = ?
        ORDER BY pb.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bills = [];
}

// ================================================================
// FETCH PAYMENTS
// ================================================================
$payments = [];
try {
    $stmt = $db->prepare("
        SELECT 
            p.*,
            u.full_name as received_by_name
        FROM payments p
        LEFT JOIN users u ON p.received_by = u.id
        WHERE p.bill_id IN (
            SELECT id FROM patient_bills WHERE visit_id = ?
        )
        ORDER BY p.received_at DESC
    ");
    $stmt->execute([$visit_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $payments = [];
}

// ================================================================
// FETCH VITAL SIGNS
// ================================================================
$vital_signs = [];
try {
    $stmt = $db->prepare("
        SELECT 
            vs.*,
            u.full_name as recorded_by_name
        FROM vital_signs vs
        LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE vs.visit_id = ?
        ORDER BY vs.recorded_at DESC
        LIMIT 1
    ");
    $stmt->execute([$visit_id]);
    $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $vital_signs = [];
}

// ================================================================
// CALCULATE SUMMARY
// ================================================================
$total_bill_amount = 0;
$total_paid_amount = 0;
$total_discount = $visit['total_discount'] ?? 0;
$visit_total = $visit['visit_total'] ?? 0;

foreach ($bills as $bill) {
    $total_bill_amount += $bill['total_amount'] ?? 0;
    $total_paid_amount += $bill['paid_amount'] ?? 0;
    if (($bill['discount_amount'] ?? 0) > $total_discount) {
        $total_discount = $bill['discount_amount'] ?? 0;
    }
}

$net_total = $total_bill_amount - $total_discount;
$balance = $total_bill_amount - $total_paid_amount - $total_discount;

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
    $status = $status ?? 'pending';
    
    $classes = [
        'pending' => 'warning',
        'assigned' => 'info',
        'with_doctor' => 'primary',
        'lab_test' => 'purple',
        'lab_completed' => 'info',
        'prescribed' => 'info',
        'completed' => 'success',
        'cancelled' => 'danger',
        'paid' => 'success',
        'partial' => 'warning',
        'pending_payment' => 'warning',
        'active' => 'success',
        'inactive' => 'danger',
        'dispensed' => 'success',
        'confirmed' => 'info',
        'scheduled' => 'info',
        'online' => 'success',
        'offline' => 'danger',
        'new' => 'info',
        'follow-up' => 'warning',
        'emergency' => 'danger',
        'unknown' => 'secondary'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $status = $status ?? 'pending';
    
    $icons = [
        'pending' => 'fa-clock',
        'assigned' => 'fa-user-check',
        'with_doctor' => 'fa-stethoscope',
        'lab_test' => 'fa-flask',
        'lab_completed' => 'fa-check-double',
        'prescribed' => 'fa-prescription',
        'completed' => 'fa-check-circle',
        'cancelled' => 'fa-times-circle',
        'paid' => 'fa-check-circle',
        'partial' => 'fa-clock',
        'pending_payment' => 'fa-clock',
        'active' => 'fa-check-circle',
        'inactive' => 'fa-times-circle',
        'dispensed' => 'fa-check-circle',
        'confirmed' => 'fa-check-double',
        'scheduled' => 'fa-calendar-check',
        'online' => 'fa-circle',
        'offline' => 'fa-circle',
        'new' => 'fa-user-plus',
        'follow-up' => 'fa-user-check',
        'emergency' => 'fa-ambulance',
        'unknown' => 'fa-circle'
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
    <title>View Visit - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - BLUE THEME
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --primary-gradient-hover: linear-gradient(135deg, #0A4CA8, #083C8A);
            
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
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 40px rgba(0,0,0,0.12);
            
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
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --shadow-xl: 0 20px 40px rgba(0,0,0,0.5);
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
           PAGE HEADER - BLUE THEME
           ================================================================ */
        .page-header {
            background: var(--primary-gradient);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
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
           DETAIL CARDS
           ================================================================ */
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 2px solid var(--border-color);
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
           STATS CARDS - SUMMARY
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 16px 18px;
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            color: inherit;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-gradient);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-card .stat-content {
            flex: 1;
        }
        
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.05);
        }
        
        .stat-icon.blue { background: var(--primary-bg); color: var(--primary); }
        .stat-icon.green { background: #ECFDF5; color: #059669; }
        .stat-icon.orange { background: #FFFBEB; color: #F59E0B; }
        .stat-icon.purple { background: #F5F3FF; color: #7C3AED; }
        .stat-icon.red { background: #FEF2F2; color: #DC2626; }
        .stat-icon.teal { background: #ECFDF5; color: #0D9488; }
        
        [data-theme="dark"] .stat-icon.blue { background: #1E3A5F; color: #3B82F6; }
        [data-theme="dark"] .stat-icon.green { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .stat-icon.orange { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .stat-icon.purple { background: #2D1B4E; color: #A78BFA; }
        [data-theme="dark"] .stat-icon.red { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .stat-icon.teal { background: #1A3A2A; color: #2DD4BF; }
        
        .stat-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
        }
        
        .stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            line-height: 1.2;
        }
        
        .stat-value.blue-text { color: var(--primary); }
        .stat-value.green-text { color: #059669; }
        .stat-value.orange-text { color: #F59E0B; }
        .stat-value.purple-text { color: #7C3AED; }
        .stat-value.red-text { color: #DC2626; }
        .stat-value.teal-text { color: #0D9488; }
        
        .stat-sub {
            font-size: 0.55rem;
            color: var(--text-secondary);
            margin-top: 1px;
        }
        
        .stat-arrow {
            opacity: 0;
            transition: all 0.3s ease;
            color: var(--primary);
            font-size: 0.7rem;
            flex-shrink: 0;
        }
        
        .stat-card:hover .stat-arrow {
            opacity: 1;
            transform: translateX(4px);
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
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
        .badge-primary { background: #0B5ED7; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            padding: 16px 24px;
            background: var(--bg-body);
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        [data-theme="dark"] .card-header {
            background: #0F172A;
        }
        
        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .card-title i {
            margin-right: 8px;
        }
        
        /* ================================================================
           DATA TABLE
           ================================================================ */
        .table-container {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.8rem;
        }
        
        .data-table thead th {
            background: var(--primary-gradient);
            color: white;
            font-weight: 600;
            padding: 10px 12px;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: none;
            white-space: nowrap;
        }
        
        .data-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .data-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            transition: background 0.2s ease;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
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
            margin-bottom: 10px;
        }
        
        .empty-state h4 {
            font-size: 0.95rem;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-gradient-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-sm {
            padding: 4px 10px;
            font-size: 0.65rem;
            border-radius: 6px;
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
        
        .btn-success {
            background: #059669;
            color: white;
        }
        
        .btn-success:hover {
            background: #047857;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
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
            font-weight: 500;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .detail-card .grid { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .detail-card .grid { grid-template-columns: 1fr; }
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
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .stat-card:hover .stat-icon {
            animation: pulse 0.5s ease;
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
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $branch_id == $b['id'] ? 'selected' : '' ?>>
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
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-hospital-user"></i>
                Visit Details
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="visits.php?branch_id=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if (($visit['status'] ?? '') === 'pending' || ($visit['status'] ?? '') === 'assigned'): ?>
                <a href="assign_doctor_visit.php?visit_id=<?= $visit_id ?>&branch_id=<?= $branch_id ?>" class="btn-outline-light" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);">
                    <i class="fas fa-user-md"></i> Assign Doctor
                </a>
            <?php endif; ?>
            <?php if (($visit['status'] ?? '') === 'completed'): ?>
                <a href="view_bill.php?visit_id=<?= $visit_id ?>&branch_id=<?= $branch_id ?>" class="btn-outline-light" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);">
                    <i class="fas fa-receipt"></i> View Bill
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Total Bill</p>
                <p class="stat-value blue-text">TSh <?= number_format($total_bill_amount, 0) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Paid</p>
                <p class="stat-value green-text">TSh <?= number_format($total_paid_amount, 0) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-tags"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Discount</p>
                <p class="stat-value orange-text">TSh <?= number_format($total_discount, 0) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon <?= $balance > 0 ? 'red' : 'green' ?>">
                <i class="fas <?= $balance > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Balance</p>
                <p class="stat-value <?= $balance > 0 ? 'red-text' : 'green-text' ?>">
                    TSh <?= number_format($balance, 0) ?>
                </p>
                <p class="stat-sub"><?= $balance > 0 ? 'Pending payment' : 'Fully paid' ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VISIT INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <p class="detail-label"><i class="fas fa-hashtag mr-1"></i> Visit Number</p>
                <p class="detail-value font-mono"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-calendar-day mr-1"></i> Visit Date</p>
                <p class="detail-value"><?= date('M d, Y h:i A', strtotime($visit['visit_date'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-tag mr-1"></i> Visit Type</p>
                <p class="detail-value">
                    <span class="badge badge-<?= getStatusBadge($visit['visit_type'] ?? 'new') ?>" style="font-size:0.65rem;">
                        <?= ucfirst($visit['visit_type'] ?? 'New') ?>
                    </span>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-info-circle mr-1"></i> Status</p>
                <p class="detail-value">
                    <span class="badge badge-<?= getStatusBadge($visit['status'] ?? 'pending') ?>" style="font-size:0.65rem;">
                        <i class="fas <?= getStatusIcon($visit['status'] ?? 'pending') ?>"></i>
                        <?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'Pending')) ?>
                    </span>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user-md mr-1"></i> Doctor</p>
                <p class="detail-value">
                    <?php if (!empty($visit['doctor_name'])): ?>
                        <?= htmlspecialchars($visit['doctor_name']) ?>
                        <span class="text-xs text-gray-400 block"><?= htmlspecialchars($visit['doctor_specialty'] ?? 'General') ?></span>
                    <?php else: ?>
                        <span class="text-gray-400">Not assigned</span>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user-tie mr-1"></i> Receptionist</p>
                <p class="detail-value"><?= htmlspecialchars($visit['receptionist_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-money-bill-wave mr-1"></i> Registration Fee</p>
                <p class="detail-value">TSh <?= number_format($visit['registration_fee'] ?? 0, 0) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-stethoscope mr-1"></i> Consultation Fee</p>
                <p class="detail-value">TSh <?= number_format($visit['consultation_fee'] ?? 0, 0) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-flask mr-1"></i> Lab Fees</p>
                <p class="detail-value">TSh <?= number_format($visit['lab_fees_total'] ?? 0, 0) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-prescription mr-1"></i> Pharmacy Fees</p>
                <p class="detail-value">TSh <?= number_format($visit['pharmacy_fees_total'] ?? 0, 0) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-tools mr-1"></i> Other Fees</p>
                <p class="detail-value">TSh <?= number_format($visit['other_fees_total'] ?? 0, 0) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-calculator mr-1"></i> Total</p>
                <p class="detail-value font-bold text-primary">TSh <?= number_format($visit['visit_total'] ?? 0, 0) ?></p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.1s;">
        <h3 class="text-sm font-semibold text-primary mb-3">
            <i class="fas fa-user mr-2"></i> Patient Information
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <p class="detail-label">Patient ID</p>
                <p class="detail-value font-mono"><?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Full Name</p>
                <p class="detail-value font-semibold"><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Gender</p>
                <p class="detail-value">
                    <span class="badge badge-<?= ($visit['patient_gender'] ?? '') === 'Male' ? 'info' : (($visit['patient_gender'] ?? '') === 'Female' ? 'purple' : 'secondary') ?>" style="font-size:0.6rem;padding:2px 12px;">
                        <?= htmlspecialchars($visit['patient_gender'] ?? 'N/A') ?>
                    </span>
                </p>
            </div>
            <div>
                <p class="detail-label">Date of Birth</p>
                <p class="detail-value"><?= !empty($visit['patient_dob']) ? date('M d, Y', strtotime($visit['patient_dob'])) : 'N/A' ?></p>
            </div>
            <div>
                <p class="detail-label">Phone</p>
                <p class="detail-value"><?= htmlspecialchars($visit['patient_phone'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Email</p>
                <p class="detail-value"><?= htmlspecialchars($visit['patient_email'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Address</p>
                <p class="detail-value"><?= htmlspecialchars($visit['patient_address'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Blood Group</p>
                <p class="detail-value"><?= htmlspecialchars($visit['patient_blood_group'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Allergies</p>
                <p class="detail-value <?= !empty($visit['patient_allergies']) ? 'text-red-600' : 'text-gray-400' ?>">
                    <?= htmlspecialchars($visit['patient_allergies'] ?? 'None') ?>
                </p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VITAL SIGNS -->
    <!-- ================================================================ -->
    <?php if (!empty($vital_signs)): ?>
        <div class="detail-card animate-fade-in-up" style="animation-delay:0.15s;">
            <h3 class="text-sm font-semibold text-primary mb-3">
                <i class="fas fa-heartbeat mr-2"></i> Vital Signs
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                <?php if (!empty($vital_signs['temperature'])): ?>
                    <div class="text-center p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <p class="text-xs text-gray-500">Temperature</p>
                        <p class="text-lg font-bold text-primary"><?= $vital_signs['temperature'] ?> °C</p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($vital_signs['blood_pressure_systolic']) && !empty($vital_signs['blood_pressure_diastolic'])): ?>
                    <div class="text-center p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <p class="text-xs text-gray-500">Blood Pressure</p>
                        <p class="text-lg font-bold text-primary"><?= $vital_signs['blood_pressure_systolic'] ?>/<?= $vital_signs['blood_pressure_diastolic'] ?> mmHg</p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($vital_signs['pulse_rate'])): ?>
                    <div class="text-center p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <p class="text-xs text-gray-500">Pulse Rate</p>
                        <p class="text-lg font-bold text-primary"><?= $vital_signs['pulse_rate'] ?> bpm</p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($vital_signs['respiratory_rate'])): ?>
                    <div class="text-center p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <p class="text-xs text-gray-500">Respiratory Rate</p>
                        <p class="text-lg font-bold text-primary"><?= $vital_signs['respiratory_rate'] ?> /min</p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($vital_signs['oxygen_saturation'])): ?>
                    <div class="text-center p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <p class="text-xs text-gray-500">SpO2</p>
                        <p class="text-lg font-bold text-primary"><?= $vital_signs['oxygen_saturation'] ?>%</p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($vital_signs['weight']) && !empty($vital_signs['height'])): ?>
                    <div class="text-center p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <p class="text-xs text-gray-500">BMI</p>
                        <p class="text-lg font-bold text-primary"><?= number_format($vital_signs['bmi'] ?? 0, 1) ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($vital_signs['notes'])): ?>
                <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    <strong>Notes:</strong> <?= htmlspecialchars($vital_signs['notes']) ?>
                </div>
            <?php endif; ?>
            <div class="text-xs text-gray-400 mt-2">
                Recorded by: <?= htmlspecialchars($vital_signs['recorded_by_name'] ?? 'N/A') ?> 
                at <?= date('M d, Y h:i A', strtotime($vital_signs['recorded_at'] ?? 'now')) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-prescription text-purple-600"></i>
                Prescriptions (<?= count($prescriptions) ?>)
            </h3>
            <?php if (($visit['status'] ?? '') === 'with_doctor' || ($visit['status'] ?? '') === 'prescribed'): ?>
                <a href="add_prescription.php?visit_id=<?= $visit_id ?>&branch_id=<?= $branch_id ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus"></i> Add Prescription
                </a>
            <?php endif; ?>
        </div>
        <div class="table-container">
            <?php if (count($prescriptions) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Prescription #</th>
                            <th>Doctor</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $prescription): ?>
                            <tr>
                                <td class="font-mono text-xs font-semibold">
                                    <?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?>
                                </td>
                                <td><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></td>
                                <td><?= number_format($prescription['item_count'] ?? 0) ?></td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($prescription['status'] ?? 'pending') ?>" style="font-size:0.55rem;padding:2px 10px;">
                                        <i class="fas <?= getStatusIcon($prescription['status'] ?? 'pending') ?>"></i>
                                        <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y h:i A', strtotime($prescription['created_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_prescription.php?id=<?= $prescription['id'] ?>&branch_id=<?= $branch_id ?>" class="text-blue-600 text-xs hover:underline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-prescription"></i>
                    <h4>No Prescriptions</h4>
                    <p>No prescriptions have been created for this visit yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- LAB TESTS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-flask text-purple-600"></i>
                Lab Tests (<?= count($lab_tests) ?>)
            </h3>
            <?php if (($visit['status'] ?? '') === 'with_doctor' || ($visit['status'] ?? '') === 'lab_test'): ?>
                <a href="add_lab_test.php?visit_id=<?= $visit_id ?>&branch_id=<?= $branch_id ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus"></i> Add Lab Test
                </a>
            <?php endif; ?>
        </div>
        <div class="table-container">
            <?php if (count($lab_tests) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Doctor</th>
                            <th>Technician</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $test): ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($test['technician_name'] ?? 'Not assigned') ?></td>
                                <td>TSh <?= number_format($test['test_price'] ?? 0, 0) ?></td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($test['status'] ?? 'pending') ?>" style="font-size:0.55rem;padding:2px 10px;">
                                        <i class="fas <?= getStatusIcon($test['status'] ?? 'pending') ?>"></i>
                                        <?= ucfirst($test['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_lab_test.php?id=<?= $test['id'] ?>&branch_id=<?= $branch_id ?>" class="text-blue-600 text-xs hover:underline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-flask"></i>
                    <h4>No Lab Tests</h4>
                    <p>No lab tests have been ordered for this visit yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILLS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.3s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-receipt text-green-600"></i>
                Bills (<?= count($bills) ?>)
            </h3>
            <?php if (($visit['status'] ?? '') === 'completed' && count($bills) == 0): ?>
                <a href="create_bill.php?visit_id=<?= $visit_id ?>&branch_id=<?= $branch_id ?>" class="btn btn-sm btn-success">
                    <i class="fas fa-plus"></i> Create Bill
                </a>
            <?php endif; ?>
        </div>
        <div class="table-container">
            <?php if (count($bills) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $bill): 
                            $bill_balance = ($bill['total_amount'] ?? 0) - ($bill['paid_amount'] ?? 0) - ($bill['discount_amount'] ?? 0);
                        ?>
                            <tr>
                                <td class="font-mono text-xs font-semibold">
                                    <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                                </td>
                                <td>TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                                <td class="text-green-600">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                <td class="<?= $bill_balance > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                    TSh <?= number_format($bill_balance, 0) ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($bill['status'] ?? 'pending') ?>" style="font-size:0.55rem;padding:2px 10px;">
                                        <i class="fas <?= getStatusIcon($bill['status'] ?? 'pending') ?>"></i>
                                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($bill['created_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_bill.php?id=<?= $bill['id'] ?>&branch_id=<?= $branch_id ?>" class="text-blue-600 text-xs hover:underline">View</a>
                                    <?php if (($bill['status'] ?? '') === 'pending' || ($bill['status'] ?? '') === 'partial'): ?>
                                        <span class="text-gray-300 mx-1">|</span>
                                        <a href="add_payment.php?bill_id=<?= $bill['id'] ?>&branch_id=<?= $branch_id ?>" class="text-green-600 text-xs hover:underline">Pay</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <h4>No Bills</h4>
                    <p>No bills have been created for this visit yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PAYMENTS -->
    <!-- ================================================================ -->
    <?php if (count($payments) > 0): ?>
        <div class="card animate-fade-in-up" style="animation-delay:0.35s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-hand-holding-usd text-teal-600"></i>
                    Payments (<?= count($payments) ?>)
                </h3>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Received By</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td class="font-mono text-xs font-semibold">
                                    <?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?>
                                </td>
                                <td class="font-semibold text-green-600">TSh <?= number_format($payment['amount'] ?? 0, 0) ?></td>
                                <td>
                                    <span class="badge badge-info" style="font-size:0.55rem;padding:2px 10px;">
                                        <?= ucfirst($payment['payment_method'] ?? 'Cash') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= date('M d, Y h:i A', strtotime($payment['received_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_receipt.php?id=<?= $payment['id'] ?>&branch_id=<?= $branch_id ?>" class="text-blue-600 text-xs hover:underline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Visit Details - <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

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
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch_id=<?= $branch_id ?>';
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
        url.searchParams.set('branch_id', branchId);
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

    console.log('%c🏥 Braick Dispensary - View Visit (BLUE THEME)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Visit: <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c👨‍⚕️ Doctor: <?= htmlspecialchars($visit['doctor_name'] ?? 'Not assigned') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💰 Total Bill: TSh <?= number_format($total_bill_amount, 0) ?>', 'font-size:13px; color:#0D9488;');
    console.log('%c💳 Paid: TSh <?= number_format($total_paid_amount, 0) ?> | Balance: TSh <?= number_format($balance, 0) ?>', 'font-size:13px; color:#059669;');
</script>

</body>
</html>