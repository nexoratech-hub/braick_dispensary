<?php
// ================================================================
// FILE: frontend/pages/admin/visit_details.php
// VISIT DETAILS - TABLE STYLE VIEW (MATCHES view_patient.php)
// WITH VITAL SIGNS CARDS (6 CARDS) - ROWS ZAONGEWA UKUBWA
// BRAICK DISPENSARY - FIXED: Uses bills table instead of patient_bills
// ================================================================

// ================================================================
// START SESSION
// ================================================================
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
// CHECK IF USER HAS ADMIN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE AND HELPERS
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

// ================================================================
// GET DATABASE CONNECTION
// ================================================================
try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// VARIABLES
// ================================================================
$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($visit_id <= 0) {
    header('Location: visits.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET VISIT DATA
// ================================================================
$stmt = $db->prepare("
    SELECT v.*, 
           p.id as patient_id, p.full_name as patient_name, p.patient_id as patient_number,
           p.phone as patient_phone, p.email as patient_email,
           p.gender, p.date_of_birth, p.blood_group, p.allergies,
           u.id as doctor_id, u.full_name as doctor_name,
           r.id as receptionist_id, r.full_name as receptionist_name,
           b.name as branch_name,
           CASE 
               WHEN v.status = 'pending' THEN 'warning'
               WHEN v.status = 'assigned' THEN 'info'
               WHEN v.status = 'with_doctor' THEN 'primary'
               WHEN v.status = 'lab_test' THEN 'orange'
               WHEN v.status = 'prescribed' THEN 'purple'
               WHEN v.status = 'completed' THEN 'success'
               WHEN v.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM visits v
    INNER JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    LEFT JOIN users r ON v.receptionist_id = r.id
    LEFT JOIN branches b ON v.branch_id = b.id
    WHERE v.id = ?
");
$stmt->execute([$visit_id]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visit) {
    header('Location: visits.php?branch=' . $selected_branch_id);
    exit;
}

$patient_id = $visit['patient_id'];

// ================================================================
// GET VISIT STATISTICS
// ================================================================
$stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$total_patient_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// GET LAB TESTS WITH TECHNICIAN NAME
// ================================================================
$stmt = $db->prepare("
    SELECT lt.*,
           u.full_name as technician_name,
           u.id as technician_id,
           d.full_name as doctor_name,
           CASE 
               WHEN lt.status = 'pending' THEN 'warning'
               WHEN lt.status = 'in_progress' THEN 'info'
               WHEN lt.status = 'completed' THEN 'success'
               WHEN lt.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM lab_tests lt
    LEFT JOIN users u ON lt.lab_technician_id = u.id
    LEFT JOIN users d ON lt.doctor_id = d.id
    WHERE lt.visit_id = ?
    ORDER BY lt.created_at ASC
");
$stmt->execute([$visit_id]);
$visit_lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PRESCRIPTIONS WITH DOCTOR NAME
// ================================================================
$stmt = $db->prepare("
    SELECT p.*,
           u.full_name as doctor_name,
           CASE 
               WHEN p.status = 'pending' THEN 'warning'
               WHEN p.status = 'confirmed' THEN 'info'
               WHEN p.status = 'dispensed' THEN 'success'
               WHEN p.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM prescriptions p
    LEFT JOIN users u ON p.doctor_id = u.id
    WHERE p.visit_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$visit_id]);
$visit_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get prescription items
$prescription_items = [];
$total_prescription_cost = 0;
foreach ($visit_prescriptions as $prescription) {
    $stmt = $db->prepare("
        SELECT pi.*, 
               (pi.quantity * pi.unit_price) as item_total
        FROM prescription_items pi
        WHERE pi.prescription_id = ?
    ");
    $stmt->execute([$prescription['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $prescription_items[$prescription['id']] = $items;
    
    foreach ($items as $item) {
        $total_prescription_cost += $item['item_total'] ?? 0;
    }
}

// ================================================================
// GET PROCEDURES AND TOOLS - FIXED: Uses bills table
// ================================================================
$procedure_tools = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT bi.*
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE b.visit_id = ? 
        AND (bi.item_type = 'procedure' OR bi.item_type = 'tool')
        ORDER BY bi.item_type, bi.item_name
    ");
    $stmt->execute([$visit_id]);
    $procedure_tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $procedure_tools = [];
}

// ================================================================
// GET VITAL SIGNS
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
// GET ALL BILLS - FIXED: Uses bills table
// ================================================================
$stmt = $db->prepare("
    SELECT b.*,
           CASE 
               WHEN b.status = 'pending' THEN 'warning'
               WHEN b.status = 'paid' THEN 'success'
               WHEN b.status = 'partial' THEN 'info'
               WHEN b.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM bills b
    WHERE b.visit_id = ?
    ORDER BY b.created_at ASC
");
$stmt->execute([$visit_id]);
$raw_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter duplicate bills
$unique_bills = [];
$seen_bill_numbers = [];
foreach ($raw_bills as $bill) {
    $bill_number = $bill['bill_number'];
    if (!in_array($bill_number, $seen_bill_numbers)) {
        $unique_bills[] = $bill;
        $seen_bill_numbers[] = $bill_number;
    }
}
$visit_bills = $unique_bills;

// ================================================================
// CALCULATE TOTALS BY CATEGORY
// ================================================================
$bill_category_totals = [
    'consultation' => 0,
    'lab_test' => 0,
    'medication' => 0,
    'procedure' => 0,
    'tool' => 0,
    'registration' => 0,
    'other' => 0
];

$all_bill_items = [];
$total_bill_amount = 0;
$total_paid_amount = 0;
$total_balance = 0;
$bill_statuses = [];

foreach ($visit_bills as $bill) {
    $bill_id = $bill['id'];
    $total_bill_amount += $bill['total_amount'] ?? 0;
    $total_paid_amount += $bill['paid_amount'] ?? 0;
    $total_balance += $bill['balance'] ?? 0;
    $bill_statuses[] = $bill['status'];
    
    // Get bill items
    $stmt = $db->prepare("
        SELECT 
            bi.*,
            CASE 
                WHEN bi.item_type = 'consultation' THEN 'consultation'
                WHEN bi.item_type = 'lab_test' THEN 'lab_test'
                WHEN bi.item_type = 'medication' THEN 'medication'
                WHEN bi.item_type = 'procedure' THEN 'procedure'
                WHEN bi.item_type = 'tool' THEN 'tool'
                WHEN bi.item_type = 'registration' THEN 'registration'
                ELSE 'other'
            END as category
        FROM bill_items bi
        WHERE bi.bill_id = ?
        GROUP BY bi.item_name, bi.item_type, bi.unit_price, bi.quantity
        ORDER BY bi.created_at ASC
    ");
    $stmt->execute([$bill_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $all_bill_items[$bill_id] = $items;
    
    foreach ($items as $item) {
        $category = $item['category'] ?? 'other';
        if (isset($bill_category_totals[$category])) {
            $bill_category_totals[$category] += $item['total_price'] ?? 0;
        }
    }
}

// Determine overall status
$overall_status = 'pending';
if (count($visit_bills) > 0) {
    if (in_array('pending', $bill_statuses)) {
        $overall_status = 'pending';
    } elseif (in_array('partial', $bill_statuses)) {
        $overall_status = 'partial';
    } elseif (array_diff($bill_statuses, ['paid']) === []) {
        $overall_status = 'paid';
    } elseif (array_diff($bill_statuses, ['cancelled']) === []) {
        $overall_status = 'cancelled';
    } else {
        $overall_status = 'partial';
    }
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        'dispensed' => 'success',
        'with_doctor' => 'info',
        'lab_test' => 'orange',
        'prescribed' => 'purple',
        'in_progress' => 'info'
    ];
    return $classes[$status] ?? 'secondary';
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visit Details - <?= htmlspecialchars($visit['visit_number'] ?? 'Visit') ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
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
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
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
        
        .vital-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 20px 16px;
            text-align: center;
            border: 2px solid var(--border-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            min-height: 120px;
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
            height: 5px;
            border-radius: 14px 14px 0 0;
        }
        
        .vital-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        }
        
        .vital-card .vital-icon {
            font-size: 2rem;
            margin-bottom: 8px;
        }
        
        .vital-card .vital-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }
        
        .vital-card .vital-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.04em;
            margin-top: 4px;
        }
        
        .vital-card .vital-unit {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 400;
            margin-left: 2px;
        }
        
        .vital-card.blue::before { background: linear-gradient(90deg, #0B5ED7, #1A73E8); }
        .vital-card.blue .vital-icon { color: #0B5ED7; }
        .vital-card.blue .vital-value { color: #0B5ED7; }
        
        .vital-card.red::before { background: linear-gradient(90deg, #EF4444, #F87171); }
        .vital-card.red .vital-icon { color: #EF4444; }
        .vital-card.red .vital-value { color: #EF4444; }
        
        .vital-card.pink::before { background: linear-gradient(90deg, #EC4899, #F472B6); }
        .vital-card.pink .vital-icon { color: #EC4899; }
        .vital-card.pink .vital-value { color: #EC4899; }
        
        .vital-card.purple::before { background: linear-gradient(90deg, #7B2FBE, #9B4DCA); }
        .vital-card.purple .vital-icon { color: #7B2FBE; }
        .vital-card.purple .vital-value { color: #7B2FBE; }
        
        .vital-card.green::before { background: linear-gradient(90deg, #059669, #0AA84F); }
        .vital-card.green .vital-icon { color: #059669; }
        .vital-card.green .vital-value { color: #059669; }
        
        .vital-card.indigo::before { background: linear-gradient(90deg, #4F46E5, #818CF8); }
        .vital-card.indigo .vital-icon { color: #4F46E5; }
        .vital-card.indigo .vital-value { color: #4F46E5; }
        
        [data-theme="dark"] .vital-card {
            background: #1E293B;
            border-color: #334155;
        }
        
        [data-theme="dark"] .vital-card:hover {
            border-color: #0B5ED7;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
        }
        
        [data-theme="dark"] .vital-card .vital-value {
            color: #F1F5F9;
        }
        
        [data-theme="dark"] .vital-card.blue .vital-value { color: #6EA8FE; }
        [data-theme="dark"] .vital-card.red .vital-value { color: #F87171; }
        [data-theme="dark"] .vital-card.pink .vital-value { color: #F472B6; }
        [data-theme="dark"] .vital-card.purple .vital-value { color: #A78BFA; }
        [data-theme="dark"] .vital-card.green .vital-value { color: #34D399; }
        [data-theme="dark"] .vital-card.indigo .vital-value { color: #A5B4FC; }
        
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
            font-size: 0.82rem;
        }
        
        .data-table thead th {
            background: var(--bg-body);
            color: var(--text-secondary);
            font-weight: 700;
            padding: 12px 14px;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border-color);
            text-align: left;
        }
        
        [data-theme="dark"] .data-table thead th {
            background: #0F172A;
        }
        
        .data-table td {
            padding: 12px 14px;
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
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
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
        .badge-orange { background: #F59E0B; color: #1E293B; }
        .badge-pink { background: #EC4899; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        [data-theme="dark"] .badge-orange { color: #1E293B; }
        
        .status-badge {
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-badge.warning { background: #FEF3C7; color: #D97706; }
        .status-badge.success { background: #D1FAE5; color: #059669; }
        .status-badge.danger { background: #FEE2E2; color: #EF4444; }
        .status-badge.info { background: #E8F0FE; color: #0B5ED7; }
        .status-badge.primary { background: #DBEAFE; color: #2563EB; }
        .status-badge.orange { background: #FED7AA; color: #EA580C; }
        .status-badge.purple { background: #E9D5FF; color: #7B2FBE; }
        .status-badge.secondary { background: #E2E8F0; color: #64748B; }
        .status-badge.pink { background: #FCE7F3; color: #DB2777; }
        .status-badge.teal { background: #CCFBF1; color: #0D9488; }
        
        [data-theme="dark"] .status-badge.warning { background: #3A2A1A; color: #FBBF24; }
        [data-theme="dark"] .status-badge.success { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge.danger { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .status-badge.info { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .status-badge.primary { background: #1A2A4A; color: #60A5FA; }
        [data-theme="dark"] .status-badge.orange { background: #3A2A1A; color: #FB923C; }
        [data-theme="dark"] .status-badge.purple { background: #2A1A3A; color: #A78BFA; }
        [data-theme="dark"] .status-badge.secondary { background: #2D3748; color: #94A3B8; }
        [data-theme="dark"] .status-badge.pink { background: #3A1A2A; color: #F472B6; }
        [data-theme="dark"] .status-badge.teal { background: #1A3A3A; color: #2DD4BF; }
        
        .technician-tag {
            background: #E8F0FE;
            color: #0B5ED7;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        [data-theme="dark"] .technician-tag {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .doctor-tag {
            background: #D1FAE5;
            color: #059669;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        [data-theme="dark"] .doctor-tag {
            background: #1A3A2A;
            color: #34D399;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 2.5rem;
            color: var(--border-color);
            margin-bottom: 10px;
        }
        
        .empty-state p {
            font-size: 0.85rem;
            margin: 0;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.78rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 5px 12px;
            font-size: 0.68rem;
        }
        
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
            .detail-card { padding: 16px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .detail-card { padding: 12px 14px; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
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
            <form method="GET" action="visits.php" class="flex-1 flex">
                <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                <input type="text" name="search" placeholder="Search visits..." 
                       class="flex-1 px-3 py-2 bg-transparent border-none outline-none text-sm" 
                       style="color: var(--text-primary);">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search mr-1"></i> Search
                </button>
            </form>
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
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-stethoscope"></i>
                Visit Details
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hashtag"></i>
                <strong><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-calendar-alt"></i>
                    <?= date('M d, Y', strtotime($visit['visit_date'])) ?>
                </span>
                
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-store-alt"></i>
                    <?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="edit_visit.php?id=<?= $visit['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="visits.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VISIT INFORMATION TABLE -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <p class="detail-label"><i class="fas fa-hashtag mr-1"></i> Visit Number</p>
                <p class="detail-value font-mono"><?= htmlspecialchars($visit['visit_number']) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-calendar-alt mr-1"></i> Visit Date</p>
                <p class="detail-value"><?= date('M d, Y h:i A', strtotime($visit['visit_date'])) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-tag mr-1"></i> Visit Type</p>
                <p class="detail-value">
                    <span class="badge badge-info"><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></span>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-circle mr-1"></i> Status</p>
                <p class="detail-value">
                    <span class="status-badge <?= $visit['status_color'] ?? 'secondary' ?>">
                        <?= ucfirst($visit['status'] ?? 'N/A') ?>
                    </span>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user-md mr-1"></i> Doctor</p>
                <p class="detail-value">
                    <?php if ($visit['doctor_name']): ?>
                        <span class="doctor-tag">
                            <i class="fas fa-user-md"></i> <?= htmlspecialchars($visit['doctor_name']) ?>
                        </span>
                    <?php else: ?>
                        <span class="text-gray-400 text-sm">Not assigned</span>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user-tie mr-1"></i> Receptionist</p>
                <p class="detail-value"><?= htmlspecialchars($visit['receptionist_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-store mr-1"></i> Branch</p>
                <p class="detail-value"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></p>
            </div>
            <?php if ($visit['follow_up_date']): ?>
                <div>
                    <p class="detail-label"><i class="fas fa-calendar-plus mr-1"></i> Follow-up Date</p>
                    <p class="detail-value"><?= date('M d, Y', strtotime($visit['follow_up_date'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFORMATION TABLE -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-bold text-primary">
                <i class="fas fa-user" style="color:#059669;"></i> Patient Information
            </h3>
            <a href="patient_details.php?id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-external-link-alt"></i> View Patient
            </a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <p class="detail-label"><i class="fas fa-user mr-1"></i> Patient Name</p>
                <p class="detail-value font-semibold"><?= htmlspecialchars($visit['patient_name']) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-id-card mr-1"></i> Patient ID</p>
                <p class="detail-value font-mono"><?= htmlspecialchars($visit['patient_number']) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-venus-mars mr-1"></i> Gender</p>
                <p class="detail-value"><?= htmlspecialchars($visit['gender'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-calendar-alt mr-1"></i> Date of Birth</p>
                <p class="detail-value"><?= $visit['date_of_birth'] ? date('M d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-tint mr-1"></i> Blood Group</p>
                <p class="detail-value">
                    <?= $visit['blood_group'] ? '<span class="badge badge-danger">' . htmlspecialchars($visit['blood_group']) . '</span>' : 'N/A' ?>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-exclamation-triangle mr-1"></i> Allergies</p>
                <p class="detail-value"><?= htmlspecialchars($visit['allergies'] ?? 'None') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-phone mr-1"></i> Phone</p>
                <p class="detail-value"><?= htmlspecialchars($visit['patient_phone'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-envelope mr-1"></i> Email</p>
                <p class="detail-value"><?= htmlspecialchars($visit['patient_email'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-chart-line mr-1"></i> Total Visits</p>
                <p class="detail-value">
                    <span class="badge badge-info"><?= $total_patient_visits ?></span>
                </p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SYMPTOMS, COMPLAINT, DIAGNOSIS & TREATMENT -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <h4 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2">
                    <i class="fas fa-notes-medical" style="color:#F59E0B;"></i> Symptoms & Complaint
                </h4>
                <div class="space-y-2">
                    <div>
                        <p class="text-xs text-gray-500">Symptoms</p>
                        <p class="text-sm"><?= htmlspecialchars($visit['symptoms'] ?? 'None reported') ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Complaint</p>
                        <p class="text-sm"><?= htmlspecialchars($visit['complaint'] ?? 'None reported') ?></p>
                    </div>
                </div>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2">
                    <i class="fas fa-diagnosis" style="color:#7B2FBE;"></i> Diagnosis & Treatment
                </h4>
                <div class="space-y-2">
                    <div>
                        <p class="text-xs text-gray-500">Diagnosis</p>
                        <p class="text-sm font-semibold" style="color:#7B2FBE;">
                            <?= htmlspecialchars($visit['diagnosis'] ?? 'Not diagnosed yet') ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Treatment</p>
                        <p class="text-sm"><?= htmlspecialchars($visit['treatment'] ?? 'Not prescribed yet') ?></p>
                    </div>
                    <?php if ($visit['notes']): ?>
                        <div>
                            <p class="text-xs text-gray-500">Notes</p>
                            <p class="text-sm"><?= htmlspecialchars($visit['notes']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VITAL SIGNS - 6 CARDS (MATCHES view_patient) - ZAONGEWA UKUBWA -->
    <!-- ================================================================ -->
    <?php if ($vital_signs): ?>
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-bold text-primary">
                <i class="fas fa-heartbeat" style="color: #EC4899;"></i> Latest Vital Signs
            </h3>
            <span class="text-xs text-gray-400">Recorded: <?= date('M d, Y h:i A', strtotime($vital_signs['recorded_at'] ?? 'now')) ?></span>
        </div>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3">
            
            <!-- 1. Temperature -->
            <div class="vital-card blue">
                <div class="vital-icon"><i class="fas fa-thermometer-half"></i></div>
                <div class="vital-value">
                    <?php 
                        $temp = $vital_signs['temperature'] ?? null;
                        echo $temp !== null ? $temp : '-';
                    ?>
                    <span class="vital-unit">°C</span>
                </div>
                <div class="vital-label">Temperature</div>
            </div>
            
            <!-- 2. Blood Pressure - FIXED: Shows only systolic if diastolic is NULL -->
            <div class="vital-card red">
                <div class="vital-icon"><i class="fas fa-heart"></i></div>
                <div class="vital-value">
                    <?php 
                        $systolic = $vital_signs['blood_pressure_systolic'] ?? null;
                        $diastolic = $vital_signs['blood_pressure_diastolic'] ?? null;
                        
                        if ($systolic !== null && $diastolic !== null) {
                            echo $systolic . '/' . $diastolic;
                        } elseif ($systolic !== null) {
                            echo $systolic;
                        } else {
                            echo '-';
                        }
                    ?>
                    <span class="vital-unit">mmHg</span>
                </div>
                <div class="vital-label">Blood Pressure</div>
            </div>
            
            <!-- 3. Pulse Rate -->
            <div class="vital-card pink">
                <div class="vital-icon"><i class="fas fa-heartbeat"></i></div>
                <div class="vital-value">
                    <?php 
                        $pulse = $vital_signs['pulse_rate'] ?? null;
                        echo $pulse !== null ? $pulse : '-';
                    ?>
                    <span class="vital-unit">bpm</span>
                </div>
                <div class="vital-label">Pulse Rate</div>
            </div>
            
            <!-- 4. Weight -->
            <div class="vital-card purple">
                <div class="vital-icon"><i class="fas fa-weight"></i></div>
                <div class="vital-value">
                    <?php 
                        $weight = $vital_signs['weight'] ?? null;
                        echo $weight !== null ? $weight : '-';
                    ?>
                    <span class="vital-unit">kg</span>
                </div>
                <div class="vital-label">Weight</div>
            </div>
            
            <!-- 5. Height -->
            <div class="vital-card green">
                <div class="vital-icon"><i class="fas fa-ruler-vertical"></i></div>
                <div class="vital-value">
                    <?php 
                        $height = $vital_signs['height'] ?? null;
                        echo $height !== null ? $height : '-';
                    ?>
                    <span class="vital-unit">cm</span>
                </div>
                <div class="vital-label">Height</div>
            </div>
            
            <!-- 6. BMI -->
            <div class="vital-card indigo">
                <div class="vital-icon"><i class="fas fa-calculator"></i></div>
                <div class="vital-value">
                    <?php 
                        $bmi = $vital_signs['bmi'] ?? null;
                        echo $bmi !== null ? $bmi : '-';
                    ?>
                </div>
                <div class="vital-label">BMI</div>
            </div>
            
        </div>
        
        <?php if ($vital_signs['notes']): ?>
        <div class="mt-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <p class="text-xs text-gray-500">📝 Notes</p>
            <p class="text-sm"><?= htmlspecialchars($vital_signs['notes']) ?></p>
        </div>
        <?php endif; ?>
        
        <p class="text-xs text-gray-400 mt-2">
            <i class="fas fa-user"></i> Recorded by: <?= htmlspecialchars($vital_signs['recorded_by_name'] ?? 'N/A') ?>
        </p>
    </div>
    <?php else: ?>
    <div class="table-container animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-heartbeat" style="color:#EC4899;"></i>
                Vital Signs
            </h3>
        </div>
        <div class="empty-state">
            <i class="fas fa-heartbeat" style="color:#EC4899;"></i>
            <p>No vital signs recorded for this visit</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- LAB TESTS TABLE -->
    <!-- ================================================================ -->
    <?php if (count($visit_lab_tests) > 0): ?>
    <div class="table-container animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-flask" style="color:#F59E0B;"></i>
                Lab Tests & Results (<?= count($visit_lab_tests) ?>)
            </h3>
            <a href="lab_tests.php?visit_id=<?= $visit_id ?>" class="card-action">View All →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th style="min-width:150px;">Test Name</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th style="min-width:140px;">Results</th>
                        <th style="min-width:120px;">Doctor</th>
                        <th style="min-width:120px;">Technician</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($visit_lab_tests as $test): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td class="font-semibold"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                            <td>TSh <?= number_format($test['test_price'] ?? 0) ?></td>
                            <td>
                                <span class="status-badge <?= $test['status_color'] ?? 'secondary' ?>" style="font-size:0.6rem; padding:3px 12px;">
                                    <?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'N/A')) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($test['results'])): ?>
                                    <?php 
                                    $result = strtolower($test['results']);
                                    if (strpos($result, 'positive') !== false || strpos($result, 'pos') !== false):
                                    ?>
                                        <span class="badge badge-success">✅ <?= htmlspecialchars($test['results']) ?></span>
                                    <?php elseif (strpos($result, 'negative') !== false || strpos($result, 'neg') !== false): ?>
                                        <span class="badge badge-danger">❌ <?= htmlspecialchars($test['results']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-info"><?= htmlspecialchars($test['results']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge badge-warning">⏳ Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($test['doctor_name']): ?>
                                    <span class="doctor-tag">
                                        <i class="fas fa-user-md"></i> <?= htmlspecialchars($test['doctor_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($test['technician_name']): ?>
                                    <span class="technician-tag">
                                        <i class="fas fa-microscope"></i> <?= htmlspecialchars($test['technician_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-xs"><?= date('M d, Y', strtotime($test['created_at'])) ?></td>
                        </tr>
                        <?php if (!empty($test['reference_range']) || !empty($test['interpretation'])): ?>
                            <tr style="background: var(--bg-body);">
                                <td colspan="8" style="padding: 6px 14px; font-size:0.7rem; color: var(--text-secondary);">
                                    <?php if (!empty($test['reference_range'])): ?>
                                        <span><strong>Reference Range:</strong> <?= htmlspecialchars($test['reference_range']) ?></span>
                                        <?php if (!empty($test['interpretation'])): ?>
                                            <span class="mx-2">|</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($test['interpretation'])): ?>
                                        <span><strong>Interpretation:</strong> <?= htmlspecialchars($test['interpretation']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="table-container animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-flask" style="color:#F59E0B;"></i>
                Lab Tests & Results
            </h3>
        </div>
        <div class="empty-state">
            <i class="fas fa-flask" style="color:#F59E0B;"></i>
            <p>No lab tests recorded for this visit</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS TABLE -->
    <!-- ================================================================ -->
    <?php if (count($visit_prescriptions) > 0): ?>
    <div class="table-container animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-prescription" style="color:#7B2FBE;"></i>
                Prescriptions (<?= count($visit_prescriptions) ?>)
            </h3>
            <a href="prescriptions.php?visit_id=<?= $visit_id ?>" class="card-action">View All →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Prescription #</th>
                        <th>Doctor</th>
                        <th>Diagnosis</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visit_prescriptions as $prescription): ?>
                        <tr>
                            <td class="font-mono text-xs"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($prescription['diagnosis'] ?? 'N/A') ?></td>
                            <td>
                                <span class="status-badge <?= $prescription['status_color'] ?? 'secondary' ?>" style="font-size:0.6rem;">
                                    <?= ucfirst($prescription['status'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td class="text-xs"><?= date('M d, Y', strtotime($prescription['created_at'])) ?></td>
                        </tr>
                        <?php if (isset($prescription_items[$prescription['id']]) && count($prescription_items[$prescription['id']]) > 0): ?>
                            <tr style="background: var(--bg-body);">
                                <td colspan="5" style="padding: 8px 14px; font-size:0.75rem;">
                                    <div class="flex flex-wrap gap-1">
                                        <span class="text-xs text-gray-500 mr-1">💊 Medications:</span>
                                        <?php foreach ($prescription_items[$prescription['id']] as $item): ?>
                                            <span class="badge badge-purple" style="font-size:0.6rem;">
                                                <?= htmlspecialchars($item['medication_name']) ?>
                                                (<?= $item['quantity'] ?? 0 ?> x TSh <?= number_format($item['unit_price'] ?? 0) ?>)
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PROCEDURES AND TOOLS TABLE - FIXED: Uses bills table -->
    <!-- ================================================================ -->
    <?php if (count($procedure_tools) > 0): ?>
    <div class="table-container animate-fade-in-up" style="animation-delay:0.3s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-syringe" style="color:#0D9488;"></i>
                Procedures & Tools Used (<?= count($procedure_tools) ?>)
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Type</th>
                        <th>Unit Price</th>
                        <th>Quantity</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($procedure_tools as $item): ?>
                        <tr>
                            <td class="font-semibold"><?= htmlspecialchars($item['item_name']) ?></td>
                            <td>
                                <span class="badge <?= $item['item_type'] === 'procedure' ? 'badge-teal' : 'badge-orange' ?>">
                                    <?= ucfirst($item['item_type'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td>TSh <?= number_format($item['unit_price'] ?? 0) ?></td>
                            <td><?= $item['quantity'] ?? 1 ?></td>
                            <td class="font-semibold">TSh <?= number_format($item['total_price'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- BILLS TABLE - FIXED: Uses bills table -->
    <!-- ================================================================ -->
    <?php if (count($visit_bills) > 0): ?>
    <div class="table-container animate-fade-in-up" style="animation-delay:0.35s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-invoice" style="color:#0B5ED7;"></i>
                Bills
                <span class="text-sm font-normal text-white/70">| Total: TSh <?= number_format($total_bill_amount) ?></span>
                <span class="status-badge <?= 
                    $overall_status === 'paid' ? 'success' : 
                    ($overall_status === 'partial' ? 'info' : 
                    ($overall_status === 'cancelled' ? 'danger' : 'warning')) 
                ?>" style="font-size:0.65rem;">
                    <?= ucfirst($overall_status) ?>
                </span>
            </h3>
            <a href="bills.php?visit_id=<?= $visit_id ?>" class="card-action">View All →</a>
        </div>
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
                        <th>Items</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visit_bills as $index => $bill): ?>
                        <tr>
                            <td class="font-mono text-xs"><?= htmlspecialchars($bill['bill_number']) ?></td>
                            <td class="font-semibold">TSh <?= number_format($bill['total_amount'] ?? 0) ?></td>
                            <td>TSh <?= number_format($bill['paid_amount'] ?? 0) ?></td>
                            <td>
                                <?php if (($bill['balance'] ?? 0) > 0): ?>
                                    <span class="text-red-600 font-semibold">TSh <?= number_format($bill['balance'], 0) ?></span>
                                <?php else: ?>
                                    <span class="text-green-600">TSh 0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $bill['status_color'] ?? 'secondary' ?>" style="font-size:0.6rem;">
                                    <?= ucfirst($bill['status'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td class="text-xs"><?= date('M d, Y', strtotime($bill['created_at'])) ?></td>
                            <td>
                                <?php if (isset($all_bill_items[$bill['id']]) && count($all_bill_items[$bill['id']]) > 0): ?>
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($all_bill_items[$bill['id']] as $item): ?>
                                            <span class="badge <?= 
                                                $item['item_type'] === 'medication' ? 'badge-purple' : 
                                                ($item['item_type'] === 'lab_test' ? 'badge-orange' : 
                                                ($item['item_type'] === 'consultation' ? 'badge-info' : 
                                                ($item['item_type'] === 'procedure' ? 'badge-teal' : 
                                                ($item['item_type'] === 'tool' ? 'badge-orange' : 
                                                ($item['item_type'] === 'registration' ? 'badge-success' : 'badge-secondary'))))) 
                                            ?>" style="font-size:0.55rem;">
                                                <?= htmlspecialchars($item['item_name']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">No items</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="table-container animate-fade-in-up" style="animation-delay:0.35s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-invoice" style="color:#0B5ED7;"></i>
                Bills
            </h3>
        </div>
        <div class="empty-state">
            <i class="fas fa-receipt"></i>
            <p>No bills created for this visit</p>
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
            Visit Details - <?= htmlspecialchars($visit['visit_number'] ?? 'Visit') ?>
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

    console.log('%c🏥 Braick Dispensary - Visit Details (TABLE STYLE)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#059669;');
    console.log('%c📋 Visit: <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c❤️ Vital Signs: 6 cards (Temp, BP, Pulse, Weight, Height, BMI)', 'font-size:13px; color:#EC4899;');
    console.log('%c📐 Rows enlarged - More padding for better readability', 'font-size:13px; color:#34D399;');
    console.log('%c🔬 Lab Tests: <?= count($visit_lab_tests) ?>', 'font-size:13px; color:#F59E0B;');
    console.log('%c💊 Prescriptions: <?= count($visit_prescriptions) ?>', 'font-size:13px; color:#7B2FBE;');
    console.log('%c💰 Bills: <?= count($visit_bills) ?> | Total: TSh <?= number_format($total_bill_amount) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ FIXED: Uses bills table (not patient_bills)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ FIXED: Procedures & Tools from bills table', 'font-size:13px; color:#34D399;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>