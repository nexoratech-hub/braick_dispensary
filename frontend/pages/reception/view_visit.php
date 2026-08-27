<?php
// ================================================================
// FILE: frontend/pages/reception/view_visit.php
// RECEPTION - VIEW VISIT DETAILS (BRANCH FILTERED)
// WITH PDF GENERATION - Official Stamp Included
// BRAICK DISPENSARY
// THEME: BLUE | TABLE HEADERS: GREEN | SPACING: 1cm
// PDF FONT: 14px | TEXT WRAP: 2 ROWS
// FIXED: PDF starts at top of page
// FIXED: All 10 sections appear in PDF (even empty ones)
// FIXED: Removed Complete Button | Reduced Spacing to 1cm
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
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// CHECK IF USER HAS ACCESS (Reception or Admin)
// ================================================================
$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'reception';
$branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';

if ($visit_id <= 0) {
    header('Location: visits.php');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // ================================================================
    // GET UNREAD NOTIFICATIONS COUNT
    // ================================================================
    $unread_notifications = 0;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $unread_notifications = 0;
    }
    
    // ================================================================
    // GET ADMIN CONTACT NUMBERS
    // ================================================================
    $admin_phones = [];
    try {
        $stmt = $db->prepare("
            SELECT phone FROM users 
            WHERE role = 'admin' AND branch_id = ? AND status = 'active'
            ORDER BY id ASC
        ");
        $stmt->execute([$branch_id]);
        $admin_phones = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $admin_phones = [];
    }
    
    // ================================================================
    // GET BRANCH PHONE
    // ================================================================
    $branch_phone = '';
    try {
        $stmt = $db->prepare("SELECT phone FROM branches WHERE id = ?");
        $stmt->execute([$branch_id]);
        $branch_phone = $stmt->fetchColumn();
    } catch (Exception $e) {
        $branch_phone = '';
    }
    
    // ================================================================
    // GET VISIT DETAILS WITH ALL FIELDS
    // ================================================================
    $stmt = $db->prepare("
        SELECT v.*, 
               p.id as patient_id,
               p.full_name as patient_name, 
               p.patient_id as patient_number, 
               p.phone, 
               p.email, 
               p.address, 
               p.gender, 
               p.date_of_birth,
               p.blood_group,
               p.allergies,
               p.marital_status,
               p.emergency_contact,
               u.id as doctor_id,
               u.full_name as doctor_name, 
               u.specialty, 
               u.phone as doctor_phone,
               u.profile_pic as doctor_profile_pic,
               u.is_online as doctor_is_online,
               b.name as branch_name,
               b.phone as branch_phone,
               v.consultation_fee,
               d.disease_name,
               d.disease_code as disease_code_full,
               d.icd_code
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN branches b ON v.branch_id = b.id
        LEFT JOIN diseases d ON v.disease_id = d.id
        WHERE v.id = ? AND v.branch_id = ?
    ");
    $stmt->execute([$visit_id, $branch_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit) {
        $error = "Visit not found or you don't have permission to view it.";
    }
    
    // ================================================================
    // GET PRESCRIPTIONS FOR THIS VISIT
    // ================================================================
    $prescriptions = [];
    $prescription_items = [];
    if ($visit) {
        $stmt = $db->prepare("
            SELECT * FROM prescriptions 
            WHERE visit_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get prescription items with medication details
        foreach ($prescriptions as $pres) {
            $stmt = $db->prepare("
                SELECT pi.*, mi.medication_name as inventory_medication_name,
                       mi.batch_number, mi.unit, mi.selling_price
                FROM prescription_items pi
                LEFT JOIN medications_inventory mi ON pi.inventory_id = mi.id
                WHERE pi.prescription_id = ?
                ORDER BY pi.created_at DESC
            ");
            $stmt->execute([$pres['id']]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $prescription_items[$pres['id']] = $items;
        }
    }
    
    // ================================================================
    // GET LAB TESTS FOR THIS VISIT WITH TECHNICIAN NAME
    // ================================================================
    $lab_tests = [];
    if ($visit) {
        $stmt = $db->prepare("
            SELECT lt.*, 
                   u.full_name as technician_name,
                   u.profile_pic as technician_profile_pic,
                   ltc.test_code,
                   ltc.reference_range as catalog_reference_range
            FROM lab_tests lt
            LEFT JOIN users u ON lt.technician_id = u.id
            LEFT JOIN lab_tests_catalog ltc ON lt.test_id = ltc.id
            WHERE lt.visit_id = ? 
            ORDER BY lt.created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // GET VITAL SIGNS FOR THIS VISIT
    // ================================================================
    $vital_signs = null;
    if ($visit) {
        $stmt = $db->prepare("
            SELECT * FROM vital_signs 
            WHERE visit_id = ? 
            ORDER BY recorded_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$visit_id]);
        $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // GET BILLS FOR THIS VISIT
    // ================================================================
    $bills = [];
    $total_amount = 0;
    $total_paid = 0;
    $total_balance = 0;
    $pending_bills = 0;
    $cancelled_bills = 0;
    $paid_bills = 0;
    
    if ($visit) {
        $stmt = $db->prepare("
            SELECT * FROM bills 
            WHERE visit_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($bills as $bill) {
            $total_amount += $bill['total_amount'] ?? 0;
            $total_paid += $bill['paid_amount'] ?? 0;
            $total_balance += $bill['balance'] ?? 0;
            
            if ($bill['status'] == 'pending' || $bill['status'] == 'partial') {
                $pending_bills++;
            } elseif ($bill['status'] == 'paid') {
                $paid_bills++;
            } elseif ($bill['status'] == 'cancelled') {
                $cancelled_bills++;
            }
        }
    }
    
    // ================================================================
    // GET PROCEDURES FOR THIS VISIT WITH EQUIPMENT
    // ================================================================
    $procedures = [];
    if ($visit) {
        $stmt = $db->prepare("
            SELECT p.*,
                   pc.procedure_code,
                   pc.category as procedure_category,
                   me.equipment_name,
                   me.batch_number as equipment_batch,
                   me.quantity as equipment_quantity,
                   sm.quantity as equipment_used_quantity
            FROM procedures p
            LEFT JOIN procedures_catalog pc ON p.procedure_id = pc.id
            LEFT JOIN stock_movements sm ON sm.reference_id = p.id AND sm.reference_type = 'procedure'
            LEFT JOIN medical_equipment me ON sm.equipment_id = me.id
            WHERE p.visit_id = ? 
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // GET MEDICAL EQUIPMENT USED FOR THIS VISIT (from stock_movements)
    // ================================================================
    $equipment_used = [];
    if ($visit) {
        $stmt = $db->prepare("
            SELECT DISTINCT sm.*, 
                   me.equipment_name,
                   me.batch_number,
                   me.unit,
                   me.selling_price
            FROM stock_movements sm
            JOIN medical_equipment me ON sm.equipment_id = me.id
            WHERE sm.patient_id = ? 
            AND sm.reference_type IN ('prescription', 'procedure', 'lab_test')
            AND sm.movement_type = 'out'
            ORDER BY sm.created_at DESC
        ");
        $stmt->execute([$visit['patient_id']]);
        $equipment_used = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // GET BILL ITEMS FOR SUMMARY
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
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $visit = null;
    $prescriptions = [];
    $lab_tests = [];
    $vital_signs = null;
    $bills = [];
    $total_amount = 0;
    $total_paid = 0;
    $total_balance = 0;
    $pending_bills = 0;
    $cancelled_bills = 0;
    $paid_bills = 0;
    $procedures = [];
    $equipment_used = [];
    $unread_notifications = 0;
    $admin_phones = [];
    $branch_phone = '';
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visit Details - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
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
            --green-header: #059669;
            --green-header-dark: #047857;
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
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --section-spacing: 10px;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
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
            box-shadow: var(--shadow);
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
            padding: 24px 32px;
            margin-bottom: var(--section-spacing);
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
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
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
           DETAIL CARD - WITH 1cm SPACING
           ================================================================ */
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: var(--section-spacing);
        }
        
        .detail-card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-md);
        }
        
        .detail-card .card-title-section {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 3px solid var(--primary);
        }
        
        .detail-card .card-title-section h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
        }
        
        .detail-card .card-title-section .badge-count {
            background: var(--primary-bg);
            color: var(--primary);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .detail-label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .detail-value {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 20px;
        }
        
        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 6px 20px;
        }
        
        .grid-4 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 6px 20px;
        }
        
        .col-span-2 { grid-column: span 2; }
        .col-span-3 { grid-column: span 3; }
        
        /* ================================================================
           STATUS BADGES
           ================================================================ */
        .status-badge-visit {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 3px 14px;
            border-radius: 20px;
        }
        .status-badge-visit.pending { background: #FEF3C7; color: #D97706; }
        .status-badge-visit.assigned { background: #E8F0FE; color: #0B5ED7; }
        .status-badge-visit.with_doctor { background: #FEF3C7; color: #D97706; }
        .status-badge-visit.completed { background: #D1FAE5; color: #059669; }
        .status-badge-visit.cancelled { background: #FEE2E2; color: #DC2626; }
        .status-badge-visit.paid { background: #D1FAE5; color: #059669; }
        .status-badge-visit.partial { background: #FEF3C7; color: #D97706; }
        .status-badge-visit.in_progress { background: #E8F0FE; color: #0B5ED7; }
        .status-badge-visit.lab_completed { background: #D1FAE5; color: #059669; }
        .status-badge-visit.dispensed { background: #D1FAE5; color: #059669; }
        .status-badge-visit.confirmed { background: #E8F0FE; color: #0B5ED7; }
        
        /* ================================================================
           VITAL SIGNS CARDS (6 CARDS)
           ================================================================ */
        .vital-grid-6 {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
        }
        
        .vital-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 10px 8px;
            text-align: center;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .vital-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .vital-card.blue::before { background: var(--primary); }
        .vital-card.green::before { background: var(--success); }
        .vital-card.purple::before { background: var(--purple); }
        .vital-card.orange::before { background: var(--warning); }
        .vital-card.red::before { background: var(--danger); }
        .vital-card.teal::before { background: #0D9488; }
        
        .vital-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-light);
        }
        
        .vital-card .vital-icon {
            font-size: 1.3rem;
            display: block;
            margin-bottom: 2px;
        }
        
        .vital-card .vital-label {
            font-size: 0.5rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
        }
        
        .vital-card .vital-value {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-top: 2px;
        }
        
        .vital-card .vital-unit {
            font-size: 0.5rem;
            color: var(--text-secondary);
            font-weight: 400;
        }
        
        .vital-card.blue .vital-value { color: var(--primary); }
        .vital-card.green .vital-value { color: var(--success); }
        .vital-card.purple .vital-value { color: var(--purple); }
        .vital-card.orange .vital-value { color: var(--warning); }
        .vital-card.red .vital-value { color: var(--danger); }
        .vital-card.teal .vital-value { color: #0D9488; }
        
        /* ================================================================
           BILL SUMMARY CARDS
           ================================================================ */
        .bill-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }
        
        .bill-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 12px 14px;
            text-align: center;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .bill-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .bill-card .bill-icon {
            font-size: 1.6rem;
            display: block;
            margin-bottom: 2px;
        }
        
        .bill-card .bill-amount {
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        .bill-card .bill-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 500;
            letter-spacing: 0.05em;
            margin-top: 2px;
        }
        
        .bill-card.total .bill-amount { color: var(--primary); }
        .bill-card.total { border-color: var(--primary-light); background: var(--primary-bg); }
        
        .bill-card.pending .bill-amount { color: var(--warning); }
        .bill-card.pending { border-color: #FEF3C7; background: #FFFBEB; }
        
        .bill-card.paid .bill-amount { color: var(--success); }
        .bill-card.paid { border-color: #D1FAE5; background: #ECFDF5; }
        
        .bill-card.cancelled .bill-amount { color: var(--danger); }
        .bill-card.cancelled { border-color: #FEE2E2; background: #FEF2F2; }
        
        [data-theme="dark"] .bill-card.total { background: var(--bg-card); }
        [data-theme="dark"] .bill-card.pending { background: var(--bg-card); }
        [data-theme="dark"] .bill-card.paid { background: var(--bg-card); }
        [data-theme="dark"] .bill-card.cancelled { background: var(--bg-card); }
        
        /* ================================================================
           TABLE STYLES - GREEN HEADERS WITH BEAUTIFUL CSS
           ================================================================ */
        .table-wrapper {
            overflow-x: auto;
            margin-top: 6px;
        }
        
        .table-wrapper table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            border-radius: var(--radius);
            overflow: hidden;
        }
        
        .table-wrapper table th {
            background: var(--green-header);
            color: white;
            padding: 8px 12px;
            text-align: left;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 1px solid var(--green-header-dark);
            position: sticky;
            top: 0;
            z-index: 2;
        }
        
        .table-wrapper table th i {
            margin-right: 6px;
            opacity: 0.9;
        }
        
        .table-wrapper table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            word-wrap: break-word;
            max-width: 200px;
        }
        
        /* Medication table specific styles - Beautiful CSS */
        .medication-table td {
            padding: 10px 12px;
        }
        
        .medication-table .med-name {
            font-weight: 600;
            color: var(--primary);
            font-size: 0.85rem;
        }
        
        .medication-table .med-dosage {
            font-size: 0.75rem;
            color: var(--text-secondary);
            display: inline-block;
            background: var(--gray-100);
            padding: 1px 8px;
            border-radius: 12px;
            margin: 1px 2px;
        }
        
        .medication-table .med-frequency {
            font-size: 0.75rem;
            color: var(--text-secondary);
            display: inline-block;
            background: var(--primary-bg);
            padding: 1px 8px;
            border-radius: 12px;
            margin: 1px 2px;
        }
        
        .medication-table .med-instruction {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-style: italic;
            display: block;
            margin-top: 2px;
            background: var(--gray-50);
            padding: 2px 8px;
            border-radius: 4px;
            border-left: 2px solid var(--warning);
        }
        
        .medication-table .med-batch {
            font-size: 0.6rem;
            color: var(--gray-400);
            display: block;
        }
        
        .medication-table .med-price {
            font-weight: 600;
            color: var(--success);
            font-size: 0.8rem;
        }
        
        .medication-table .med-quantity {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        
        .table-wrapper table tr:nth-child(even) td {
            background: var(--gray-50);
        }
        
        .table-wrapper table tr:hover td {
            background: var(--primary-bg);
        }
        
        [data-theme="dark"] .table-wrapper table tr:nth-child(even) td {
            background: var(--gray-800);
        }
        
        [data-theme="dark"] .table-wrapper table tr:hover td {
            background: var(--gray-700);
        }
        
        .table-wrapper table .status-badge {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 20px;
        }
        .table-wrapper table .status-badge.pending { background: #FEF3C7; color: #D97706; }
        .table-wrapper table .status-badge.in_progress { background: #E8F0FE; color: #0B5ED7; }
        .table-wrapper table .status-badge.completed { background: #D1FAE5; color: #059669; }
        .table-wrapper table .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
        .table-wrapper table .status-badge.paid { background: #D1FAE5; color: #059669; }
        .table-wrapper table .status-badge.dispensed { background: #D1FAE5; color: #059669; }
        
        /* ================================================================
           TECH INFO
           ================================================================ */
        .tech-info {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .tech-info .tech-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--border-color);
        }
        
        .tech-info .tech-name {
            font-weight: 500;
            color: var(--text-primary);
        }
        
        /* ================================================================
           DOCTOR AVATAR
           ================================================================ */
        .doctor-avatar-lg {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-light);
        }
        
        .patient-avatar-sm {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        
        .branch-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        .footer {
            padding: 12px 0;
            border-top: 2px solid var(--border-color);
            margin-top: var(--section-spacing);
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ================================================================
           BRAND HEADER - BLUE THEME - FIXED: Logo Centered
           ================================================================ */
        .brand-header {
            text-align: center;
            padding: 12px 0 10px 0;
            border-bottom: 3px solid var(--primary);
            margin-bottom: var(--section-spacing);
        }
        
        .brand-header .brand-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        
        .brand-header .brand-logo img {
            height: 50px;
            width: auto;
            object-fit: contain;
        }
        
        .brand-header .brand-name {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.5px;
        }
        
        .brand-header .brand-tagline {
            font-size: 0.75rem;
            color: var(--text-secondary);
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
        
        .brand-header .brand-admin-row {
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px solid var(--border-color);
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .brand-header .brand-admin-row span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .brand-header .brand-admin-row i {
            color: var(--primary-light);
        }
        
        .brand-header .brand-admin-row .admin-phone {
            color: var(--primary);
            font-weight: 600;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .vital-grid-6 { grid-template-columns: repeat(3, 1fr); }
            .bill-summary-grid { grid-template-columns: repeat(2, 1fr); }
            .grid-4 { grid-template-columns: 1fr 1fr; }
            .grid-3 { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .vital-grid-6 { grid-template-columns: repeat(2, 1fr); }
            .bill-summary-grid { grid-template-columns: 1fr; }
            .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
            .col-span-2, .col-span-3 { grid-column: span 1; }
            .brand-header .brand-name { font-size: 1.2rem; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .vital-grid-6 { grid-template-columns: repeat(2, 1fr); }
            .page-header .btn-outline-light { padding: 4px 8px; font-size: 0.65rem; }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
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
            padding: 14px 22px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: var(--primary-gradient);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        
        .pdf-modal-header .modal-title {
            font-size: 1rem;
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
            font-size: 0.75rem;
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
        
        .pdf-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 28px;
            background: var(--bg-body);
        }
        
        .pdf-modal-body .pdf-content {
            max-width: 100%;
            font-size: 14px;
            background: var(--bg-card);
            padding: 24px 28px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            line-height: 1.5;
            margin-top: 0;
            padding-top: 28px;
        }
        
        /* PDF Styles - Natural page breaks */
        .pdf-content .pdf-section {
            page-break-inside: avoid;
            break-inside: avoid;
            margin: 0.5rem 0;
            padding: 0.3rem 0;
        }
        
        .pdf-content .pdf-table-wrap {
            page-break-inside: avoid;
            break-inside: avoid;
            overflow-x: auto;
        }
        
        .pdf-content .pdf-header-section {
            page-break-after: avoid;
            break-after: avoid;
            margin-top: 0;
            padding-top: 0;
        }
        
        /* PDF Content Styles - Logo Centered at Top */
        .pdf-content .pdf-header {
            text-align: center;
            padding-bottom: 12px;
            border-bottom: 3px solid var(--primary);
            margin-bottom: 16px;
            page-break-after: avoid;
            break-after: avoid;
            margin-top: 0;
            padding-top: 0;
        }
        
        .pdf-content .pdf-header .pdf-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: 4px;
        }
        
        .pdf-content .pdf-header .pdf-logo img {
            height: 55px;
            width: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        
        .pdf-content .pdf-header .clinic-name {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.5px;
            margin-top: 4px;
        }
        
        .pdf-content .pdf-header .clinic-sub {
            font-size: 0.75rem;
            color: var(--text-secondary);
            letter-spacing: 0.5px;
        }
        
        .pdf-content .pdf-header .doc-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--primary);
            margin-top: 4px;
            background: var(--primary-bg);
            padding: 4px 16px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .pdf-content .section-title {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--primary);
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 4px;
            margin: 6px 0 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            page-break-after: avoid;
            break-after: avoid;
        }
        
        .pdf-content .pdf-row {
            display: flex;
            padding: 2px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }
        
        .pdf-content .pdf-row .pdf-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 130px;
            flex-shrink: 0;
            font-size: 14px;
        }
        
        .pdf-content .pdf-row .pdf-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 14px;
            word-wrap: break-word;
            max-width: 400px;
        }
        
        .pdf-content .pdf-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px 14px;
        }
        
        .pdf-content .pdf-vital-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 4px;
            margin: 4px 0;
        }
        
        .pdf-content .pdf-vital-item {
            background: var(--primary-bg);
            padding: 4px 8px;
            border-radius: 6px;
            border-left: 3px solid var(--primary);
            text-align: center;
        }
        
        .pdf-content .pdf-vital-item .vital-label {
            font-size: 0.45rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
        }
        
        .pdf-content .pdf-vital-item .vital-value {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--primary-dark);
        }
        
        .pdf-content .pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin: 4px 0;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .pdf-content .pdf-table th {
            background: var(--green-header);
            color: white;
            padding: 5px 10px;
            text-align: left;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            border: 1px solid var(--green-header-dark);
        }
        
        .pdf-content .pdf-table td {
            padding: 5px 10px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            max-width: 200px;
            vertical-align: middle;
        }
        
        .pdf-content .pdf-table td .long-text {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .pdf-content .pdf-table tr:nth-child(even) td {
            background: var(--gray-50);
        }
        
        .pdf-content .pdf-empty {
            padding: 6px 0;
            color: var(--text-secondary);
            font-style: italic;
            font-size: 14px;
            text-align: center;
            background: var(--gray-50);
            border-radius: 4px;
            margin: 2px 0;
        }
        
        .pdf-content .pdf-footer {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 2px solid var(--border-color);
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        .pdf-content .pdf-footer .footer-stamp {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .pdf-content .pdf-footer .footer-left {
            font-size: 14px;
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
            padding: 6px 14px;
            border: 3px solid var(--primary);
            border-radius: 10px;
            background: var(--primary-bg);
            min-width: 150px;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-title {
            font-size: 10px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-name {
            font-size: 14px;
            font-weight: 800;
            color: var(--primary);
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-line {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-date {
            font-size: 10px;
            color: var(--text-muted);
            margin-top: 2px;
        }
        
        .pdf-content .pdf-footer .footer-bottom {
            text-align: center;
            margin-top: 6px;
            font-size: 12px;
            color: var(--text-muted);
        }
        
        /* Two row text wrap for PDF */
        .pdf-content .text-wrap-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            max-height: 3em;
            line-height: 1.5em;
        }
        
        .pdf-content .pdf-table td .text-wrap-2 {
            max-width: 180px;
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav no-print">
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
    
    <div class="flex items-center gap-3 no-print">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime">
            <i class="fas fa-clock mr-1" style="color: var(--primary-light);"></i>
            <?= date('D, M d, Y h:i:s A') ?>
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

    <?php if ($error): ?>
        <div class="error-box" style="background:var(--danger-bg);border:2px solid var(--danger);border-radius:12px;padding:20px 24px;text-align:center;max-width:600px;margin:40px auto;">
            <i class="fas fa-exclamation-circle" style="font-size:3rem;color:var(--danger);display:block;margin-bottom:12px;"></i>
            <h3 style="font-size:1.2rem;font-weight:600;color:var(--danger);">❌ Error</h3>
            <p style="color:var(--text-secondary);margin:8px 0 16px;"><?= htmlspecialchars($error) ?></p>
            <a href="visits.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Visits
            </a>
        </div>
    <?php elseif ($visit): ?>
    
    <!-- ================================================================ -->
    <!-- BRAND HEADER - FIXED: Logo Centered with Admin Numbers -->
    <!-- ================================================================ -->
    <div class="brand-header no-print">
        <div class="brand-logo">
            <img src="<?= $logo_path ?>" alt="Braick Logo" onerror="this.style.display='none'">
            <span class="brand-name">BRAICK DISPENSARY</span>
        </div>
        <div class="brand-tagline">Tunajali Afya Yako</div>
        <div class="brand-admin-row">
            <span>
                <i class="fas fa-phone-alt"></i> 
                Admin Contacts: 
                <?php if (!empty($admin_phones)): ?>
                    <?php foreach ($admin_phones as $index => $phone): ?>
                        <span class="admin-phone"><?= htmlspecialchars($phone) ?></span><?= $index < count($admin_phones) - 1 ? ' | ' : '' ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="admin-phone"><?= htmlspecialchars($branch_phone ?? '+255 700 000 001') ?></span>
                <?php endif; ?>
            </span>
            <span><i class="fas fa-building"></i> Branch: <?= htmlspecialchars($branch_name) ?></span>
            <span><i class="fas fa-calendar-alt"></i> <?= date('F d, Y') ?></span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clinic-medical"></i>
                Visit Details
                <span class="role-badge-display">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                View complete visit information
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($visit['patient_name']) ?>
                </span>
                <?php if ($visit['is_completed'] == 1): ?>
                    <span class="header-badge" style="background:rgba(5,150,105,0.2);border-color:rgba(5,150,105,0.3);color:#34D399;">
                        <i class="fas fa-check-circle"></i> Completed
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="header-right no-print" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="visits.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="generatePDF()" class="btn-outline-light" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 1. VISIT INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-info-circle" style="color:var(--primary);font-size:1.2rem;"></i>
            <h3>1. Visit Information</h3>
        </div>
        <div class="grid-2">
            <div>
                <p class="detail-label">Visit Number</p>
                <p class="detail-value"><strong><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></strong></p>
            </div>
            <div>
                <p class="detail-label">Status</p>
                <p class="detail-value">
                    <span class="status-badge-visit <?= $visit['status'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $visit['status'])) ?>
                    </span>
                </p>
            </div>
            <div>
                <p class="detail-label">Visit Type</p>
                <p class="detail-value capitalize"><?= htmlspecialchars($visit['visit_type'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Date & Time</p>
                <p class="detail-value"><?= isset($visit['visit_date']) ? date('F d, Y h:i A', strtotime($visit['visit_date'])) : 'N/A' ?></p>
            </div>
            <div>
                <p class="detail-label">Branch</p>
                <p class="detail-value"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Consultation Fee</p>
                <p class="detail-value">TSh <?= number_format($visit['consultation_fee'] ?? 0, 0) ?></p>
            </div>
            <?php if (!empty($visit['follow_up_date'])): ?>
                <div class="col-span-2">
                    <p class="detail-label">Follow-up Date</p>
                    <p class="detail-value"><?= date('F d, Y', strtotime($visit['follow_up_date'])) ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['notes'])): ?>
                <div class="col-span-2">
                    <p class="detail-label">Notes</p>
                    <p class="detail-value"><?= nl2br(htmlspecialchars($visit['notes'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 2. PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-user" style="color:var(--primary);font-size:1.2rem;"></i>
            <h3>2. Patient Information</h3>
        </div>
        <div class="flex items-center gap-4 mb-3" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <div class="patient-avatar-sm" style="background: <?= '#' . substr(md5($visit['patient_name']), 0, 6) ?>;width:44px;height:44px;font-size:1.1rem;">
                <?= strtoupper(substr($visit['patient_name'], 0, 1)) ?>
            </div>
            <div>
                <p class="font-semibold text-gray-800 text-lg" style="font-weight:600;font-size:1.05rem;color:var(--text-primary);"><?= htmlspecialchars($visit['patient_name']) ?></p>
                <p class="text-sm text-gray-500" style="font-size:0.75rem;color:var(--text-secondary);">ID: <?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?></p>
            </div>
        </div>
        <div class="grid-3">
            <div>
                <p class="detail-label">Phone</p>
                <p class="detail-value"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Email</p>
                <p class="detail-value"><?= htmlspecialchars($visit['email'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Emergency Contact</p>
                <p class="detail-value"><?= htmlspecialchars($visit['emergency_contact'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Gender</p>
                <p class="detail-value"><?= ucfirst(htmlspecialchars($visit['gender'] ?? 'N/A')) ?></p>
            </div>
            <div>
                <p class="detail-label">Marital Status</p>
                <p class="detail-value"><?= htmlspecialchars($visit['marital_status'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Date of Birth</p>
                <p class="detail-value"><?= !empty($visit['date_of_birth']) ? date('F d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?></p>
            </div>
            <div>
                <p class="detail-label">Blood Group</p>
                <p class="detail-value"><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></p>
            </div>
            <div class="col-span-2">
                <p class="detail-label">Address</p>
                <p class="detail-value"><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></p>
            </div>
            <?php if (!empty($visit['allergies'])): ?>
                <div class="col-span-3">
                    <p class="detail-label">Allergies</p>
                    <p class="detail-value" style="color:var(--danger);"><?= htmlspecialchars($visit['allergies']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 3. DOCTOR INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-user-md" style="color:var(--primary);font-size:1.2rem;"></i>
            <h3>3. Doctor Information</h3>
        </div>
        <?php if ($visit['doctor_id']): ?>
            <div class="flex items-center gap-3 flex-wrap" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <div class="flex items-center gap-3" style="display:flex;align-items:center;gap:10px;">
                    <?php if (!empty($visit['doctor_profile_pic'])): ?>
                        <img src="/dispensary_system/frontend/assets/uploads/profiles/<?= $visit['doctor_profile_pic'] ?>" 
                             alt="Doctor" class="doctor-avatar-lg"
                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2224%22 y=%2230%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3E<?= strtoupper(substr($visit['doctor_name'], 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
                    <?php else: ?>
                        <div class="doctor-avatar-lg" style="background:var(--primary);display:flex;align-items:center;justify-content:center;color:white;font-size:1.3rem;font-weight:700;">
                            <?= strtoupper(substr($visit['doctor_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <p class="font-semibold" style="font-weight:600;font-size:0.95rem;color:var(--text-primary);">Dr. <?= htmlspecialchars($visit['doctor_name']) ?></p>
                        <p class="text-sm" style="font-size:0.75rem;color:var(--text-secondary);"><?= htmlspecialchars($visit['specialty'] ?? 'General Practitioner') ?></p>
                    </div>
                </div>
                <?php if (!empty($visit['doctor_phone'])): ?>
                    <span class="text-sm" style="font-size:0.75rem;color:var(--text-secondary);">
                        <i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($visit['doctor_phone']) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($visit['consultation_fee'])): ?>
                    <span class="text-sm" style="font-size:0.75rem;color:var(--text-secondary);">
                        <i class="fas fa-money-bill-wave mr-1"></i> Fee: TSh <?= number_format($visit['consultation_fee'], 0) ?>
                    </span>
                <?php endif; ?>
                <?php if ($visit['doctor_is_online'] ?? 0): ?>
                    <span class="status-badge" style="background:#D1FAE5;color:#059669;font-size:0.6rem;padding:2px 12px;border-radius:20px;">
                        <i class="fas fa-circle" style="font-size:0.4rem;display:inline-block;margin-right:4px;"></i> Online
                    </span>
                <?php else: ?>
                    <span class="status-badge" style="background:#FEE2E2;color:#DC2626;font-size:0.6rem;padding:2px 12px;border-radius:20px;">
                        <i class="fas fa-circle" style="font-size:0.4rem;display:inline-block;margin-right:4px;"></i> Offline
                    </span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-400" style="color:var(--text-secondary);">No doctor assigned to this visit</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 4. VITAL SIGNS (6 CARDS) -->
    <!-- ================================================================ -->
    <?php if ($vital_signs): ?>
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-heartbeat" style="color:var(--danger);font-size:1.2rem;"></i>
            <h3>4. Vital Signs</h3>
            <span class="badge-count"><?= isset($vital_signs['recorded_at']) ? date('M d, Y h:i A', strtotime($vital_signs['recorded_at'])) : 'N/A' ?></span>
        </div>
        <div class="vital-grid-6">
            <div class="vital-card blue">
                <span class="vital-icon">🌡️</span>
                <span class="vital-label">Temperature</span>
                <span class="vital-value"><?= $vital_signs['temperature'] ?? 'N/A' ?> <span class="vital-unit">°C</span></span>
            </div>
            <div class="vital-card green">
                <span class="vital-icon">❤️</span>
                <span class="vital-label">Blood Pressure</span>
                <span class="vital-value">
                    <?php if (!empty($vital_signs['blood_pressure_systolic']) && !empty($vital_signs['blood_pressure_diastolic'])): ?>
                        <?= $vital_signs['blood_pressure_systolic'] ?> / <?= $vital_signs['blood_pressure_diastolic'] ?> <span class="vital-unit">mmHg</span>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </span>
            </div>
            <div class="vital-card purple">
                <span class="vital-icon">💓</span>
                <span class="vital-label">Pulse Rate</span>
                <span class="vital-value"><?= $vital_signs['pulse_rate'] ?? 'N/A' ?> <span class="vital-unit">bpm</span></span>
            </div>
            <div class="vital-card orange">
                <span class="vital-icon">⚖️</span>
                <span class="vital-label">Weight</span>
                <span class="vital-value"><?= $vital_signs['weight'] ?? 'N/A' ?> <span class="vital-unit">kg</span></span>
            </div>
            <div class="vital-card teal">
                <span class="vital-icon">📏</span>
                <span class="vital-label">Height</span>
                <span class="vital-value"><?= $vital_signs['height'] ?? 'N/A' ?> <span class="vital-unit">cm</span></span>
            </div>
            <div class="vital-card red">
                <span class="vital-icon">📊</span>
                <span class="vital-label">BMI</span>
                <span class="vital-value"><?= $vital_signs['bmi'] ?? 'N/A' ?> <span class="vital-unit">kg/m²</span></span>
            </div>
        </div>
        <?php if (!empty($vital_signs['notes'])): ?>
            <div class="mt-2 text-sm" style="margin-top:8px;font-size:0.75rem;color:var(--text-secondary);">
                <i class="fas fa-sticky-note mr-1"></i> Notes: <?= htmlspecialchars($vital_signs['notes']) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- 5. CLINICAL INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-file-medical-alt" style="color:var(--primary);font-size:1.2rem;"></i>
            <h3>5. Clinical Information</h3>
        </div>
        <div class="grid-2">
            <?php if (!empty($visit['complaint'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-exclamation-circle mr-1"></i> Complaint</p>
                    <p class="detail-value" style="background:var(--primary-bg);padding:8px 12px;border-radius:var(--radius);border-left:4px solid var(--primary);">
                        <?= nl2br(htmlspecialchars($visit['complaint'])) ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['symptoms'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-list-ul mr-1"></i> Symptoms</p>
                    <p class="detail-value" style="background:var(--gray-50);padding:8px 12px;border-radius:var(--radius);border-left:4px solid var(--warning);">
                        <?= nl2br(htmlspecialchars($visit['symptoms'])) ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['hpi'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-history mr-1"></i> HPI (History of Presenting Illness)</p>
                    <p class="detail-value" style="background:var(--gray-50);padding:8px 12px;border-radius:var(--radius);border-left:4px solid var(--purple);">
                        <?= nl2br(htmlspecialchars($visit['hpi'])) ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['physical_exam'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-stethoscope mr-1"></i> Physical Examination</p>
                    <p class="detail-value" style="background:var(--gray-50);padding:8px 12px;border-radius:var(--radius);border-left:4px solid var(--success);">
                        <?= nl2br(htmlspecialchars($visit['physical_exam'])) ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['notes'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-sticky-note mr-1"></i> Additional Notes</p>
                    <p class="detail-value" style="background:var(--gray-50);padding:8px 12px;border-radius:var(--radius);border-left:4px solid var(--gray-400);">
                        <?= nl2br(htmlspecialchars($visit['notes'])) ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 6. LAB TESTS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-flask" style="color:var(--purple);font-size:1.2rem;"></i>
            <h3>6. Lab Tests</h3>
            <span class="badge-count"><?= count($lab_tests) ?></span>
        </div>
        <?php if (count($lab_tests) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-vial"></i> Test Name</th>
                            <th><i class="fas fa-calendar-alt"></i> Date</th>
                            <th><i class="fas fa-info-circle"></i> Status</th>
                            <th><i class="fas fa-flask"></i> Results</th>
                            <th><i class="fas fa-user"></i> Technician</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $test): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong>
                                    <?php if (!empty($test['test_code'])): ?>
                                        <span style="font-size:0.6rem;color:var(--text-secondary);display:block;"><?= htmlspecialchars($test['test_code']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= isset($test['created_at']) ? date('M d, Y h:i A', strtotime($test['created_at'])) : 'N/A' ?></td>
                                <td>
                                    <span class="status-badge <?= $test['status'] ?? 'pending' ?>">
                                        <?= ucfirst($test['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($test['results'])): ?>
                                        <span style="color:var(--success);font-weight:600;">✅ <?= htmlspecialchars(substr($test['results'], 0, 50)) . (strlen($test['results']) > 50 ? '...' : '') ?></span>
                                        <?php if (!empty($test['reference_range'])): ?>
                                            <span style="font-size:0.6rem;color:var(--text-secondary);display:block;">Ref: <?= htmlspecialchars($test['reference_range']) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($test['technician_name'])): ?>
                                        <div class="tech-info">
                                            <?php if (!empty($test['technician_profile_pic'])): ?>
                                                <img src="/dispensary_system/frontend/assets/uploads/profiles/<?= $test['technician_profile_pic'] ?>" alt="Tech" class="tech-avatar" onerror="this.style.display='none'">
                                            <?php else: ?>
                                                <div class="tech-avatar" style="background:var(--primary);display:flex;align-items:center;justify-content:center;color:white;font-weight:600;font-size:0.6rem;"><?= strtoupper(substr($test['technician_name'], 0, 1)) ?></div>
                                            <?php endif; ?>
                                            <span class="tech-name"><?= htmlspecialchars($test['technician_name']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);font-size:0.7rem;">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color:var(--text-secondary);text-align:center;padding:16px 0;">No lab tests found for this visit</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 7. DIAGNOSIS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-stethoscope" style="color:var(--primary);font-size:1.2rem;"></i>
            <h3>7. Diagnosis</h3>
        </div>
        <?php if (!empty($visit['diagnosis']) || !empty($visit['disease_name']) || !empty($visit['treatment'])): ?>
        <div class="grid-2">
            <?php if (!empty($visit['disease_name'])): ?>
                <div>
                    <p class="detail-label">Disease Name</p>
                    <p class="detail-value" style="font-size:1rem;font-weight:600;color:var(--primary);"><?= htmlspecialchars($visit['disease_name']) ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['disease_code_full']) || !empty($visit['icd_code'])): ?>
                <div>
                    <p class="detail-label">Disease Code</p>
                    <p class="detail-value">
                        <?php if (!empty($visit['disease_code_full'])): ?>
                            <span class="status-badge" style="background:var(--primary-bg);color:var(--primary);padding:2px 12px;border-radius:20px;font-size:0.75rem;"><?= htmlspecialchars($visit['disease_code_full']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($visit['icd_code'])): ?>
                            <span class="status-badge" style="background:var(--gray-200);color:var(--text-secondary);padding:2px 12px;border-radius:20px;font-size:0.75rem;margin-left:4px;">ICD: <?= htmlspecialchars($visit['icd_code']) ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['diagnosis'])): ?>
                <div class="col-span-2">
                    <p class="detail-label">Diagnosis Description</p>
                    <p class="detail-value" style="background:var(--primary-bg);padding:10px 14px;border-radius:var(--radius);border-left:4px solid var(--primary);font-size:0.95rem;">
                        <?= nl2br(htmlspecialchars($visit['diagnosis'])) ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['treatment'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-prescription mr-1"></i> Treatment</p>
                    <p class="detail-value" style="background:var(--success-bg);padding:10px 14px;border-radius:var(--radius);border-left:4px solid var(--success);">
                        <?= nl2br(htmlspecialchars($visit['treatment'])) ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
            <p class="pdf-empty" style="padding:8px 0;color:var(--text-secondary);font-style:italic;text-align:center;">No diagnosis recorded for this visit</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 8. MEDICATIONS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-prescription" style="color:var(--success);font-size:1.2rem;"></i>
            <h3>8. Medications</h3>
            <span class="badge-count"><?= count($prescriptions) ?> prescription(s)</span>
        </div>
        <?php if (count($prescriptions) > 0): ?>
            <?php foreach ($prescriptions as $pres): 
                $items = $prescription_items[$pres['id']] ?? [];
            ?>
                <div style="margin-bottom:10px;padding:12px 14px;background:var(--gray-50);border-radius:var(--radius);border:1px solid var(--border-color);">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
                        <div>
                            <strong style="color:var(--primary);font-size:0.95rem;">#<?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?></strong>
                            <?php if (!empty($pres['diagnosis'])): ?>
                                <span style="font-size:0.75rem;color:var(--text-secondary);margin-left:8px;">
                                    <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($pres['diagnosis']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="status-badge <?= $pres['status'] ?? 'pending' ?>">
                            <?= ucfirst($pres['status'] ?? 'Pending') ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($pres['instructions'])): ?>
                        <div style="font-size:0.75rem;color:var(--text-secondary);margin-bottom:10px;background:var(--bg-card);padding:4px 10px;border-radius:6px;border-left:3px solid var(--warning);">
                            <i class="fas fa-info-circle mr-1"></i> <?= nl2br(htmlspecialchars($pres['instructions'])) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (count($items) > 0): ?>
                        <div class="table-wrapper">
                            <table class="medication-table">
                                <thead>
                                    <tr>
                                        <th style="width:30%;"><i class="fas fa-capsules"></i> Medication</th>
                                        <th style="width:15%;"><i class="fas fa-weight"></i> Dosage</th>
                                        <th style="width:15%;"><i class="fas fa-clock"></i> Frequency</th>
                                        <th style="width:15%;"><i class="fas fa-cubes"></i> Quantity</th>
                                        <th style="width:25%;"><i class="fas fa-info-circle"></i> Instructions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="med-name"><?= htmlspecialchars($item['medication_name'] ?? $item['inventory_medication_name'] ?? 'N/A') ?></div>
                                                <?php if (!empty($item['batch_number'])): ?>
                                                    <div class="med-batch">Batch: <?= htmlspecialchars($item['batch_number']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($item['total_price'])): ?>
                                                    <div class="med-price">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="med-dosage"><?= htmlspecialchars($item['dosage'] ?? 'N/A') ?></span>
                                                <?php if (!empty($item['duration'])): ?>
                                                    <div style="font-size:0.65rem;color:var(--text-secondary);margin-top:2px;">Duration: <?= htmlspecialchars($item['duration']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="med-frequency"><?= htmlspecialchars($item['frequency'] ?? 'N/A') ?></span>
                                            </td>
                                            <td>
                                                <span class="med-quantity"><?= $item['quantity'] ?? 0 ?></span>
                                                <span style="font-size:0.6rem;color:var(--text-secondary);"> units</span>
                                            </td>
                                            <td>
                                                <?php if (!empty($item['instructions'])): ?>
                                                    <div class="med-instruction"><?= htmlspecialchars($item['instructions']) ?></div>
                                                <?php else: ?>
                                                    <span style="color:var(--text-secondary);font-size:0.7rem;">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="font-size:0.75rem;color:var(--text-secondary);font-style:italic;">No medication items found</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:var(--text-secondary);text-align:center;padding:16px 0;">No medications prescribed for this visit</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 9. PROCEDURES & EQUIPMENT -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-syringe" style="color:var(--purple);font-size:1.2rem;"></i>
            <h3>9. Procedures & Equipment Used</h3>
            <span class="badge-count"><?= count($procedures) ?> procedure(s)</span>
        </div>
        <?php if (count($procedures) > 0 || count($equipment_used) > 0): ?>
            <?php if (count($procedures) > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fas fa-syringe"></i> Procedure Name</th>
                                <th><i class="fas fa-calendar-alt"></i> Date</th>
                                <th><i class="fas fa-info-circle"></i> Status</th>
                                <th><i class="fas fa-tools"></i> Equipment Used</th>
                                <th><i class="fas fa-cubes"></i> Qty</th>
                                <th><i class="fas fa-money-bill-wave"></i> Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($procedures as $proc): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($proc['procedure_name'] ?? 'N/A') ?></strong>
                                        <?php if (!empty($proc['procedure_code'])): ?>
                                            <span style="font-size:0.6rem;color:var(--text-secondary);display:block;"><?= htmlspecialchars($proc['procedure_code']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= isset($proc['created_at']) ? date('M d, Y h:i A', strtotime($proc['created_at'])) : 'N/A' ?></td>
                                    <td>
                                        <span class="status-badge <?= $proc['status'] ?? 'pending' ?>">
                                            <?= ucfirst($proc['status'] ?? 'Pending') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($proc['equipment_name'])): ?>
                                            <span style="font-size:0.75rem;"><?= htmlspecialchars($proc['equipment_name']) ?></span>
                                            <?php if (!empty($proc['equipment_batch'])): ?>
                                                <span style="font-size:0.55rem;color:var(--text-secondary);display:block;">Batch: <?= htmlspecialchars($proc['equipment_batch']) ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);font-size:0.7rem;">No equipment recorded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $proc['equipment_used_quantity'] ?? 1 ?></td>
                                    <td><?= !empty($proc['procedure_price']) ? 'TSh ' . number_format($proc['procedure_price'], 0) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <?php if (count($equipment_used) > 0): ?>
                <div style="margin-top:10px;">
                    <p style="font-size:0.7rem;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;"><i class="fas fa-tools"></i> Medical Equipment Used</p>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th><i class="fas fa-tools"></i> Equipment Name</th>
                                    <th><i class="fas fa-barcode"></i> Batch Number</th>
                                    <th><i class="fas fa-cubes"></i> Quantity</th>
                                    <th><i class="fas fa-ruler"></i> Unit</th>
                                    <th><i class="fas fa-tag"></i> Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($equipment_used as $eq): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($eq['equipment_name'] ?? 'N/A') ?></strong></td>
                                        <td><span style="font-size:0.7rem;color:var(--text-secondary);"><?= htmlspecialchars($eq['batch_number'] ?? 'N/A') ?></span></td>
                                        <td><strong><?= $eq['quantity'] ?? 0 ?></strong></td>
                                        <td><?= htmlspecialchars($eq['unit'] ?? 'pcs') ?></td>
                                        <td>
                                            <span style="font-size:0.65rem;color:var(--text-secondary);text-transform:capitalize;">
                                                <?= htmlspecialchars($eq['reference_type'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="pdf-empty" style="padding:8px 0;color:var(--text-secondary);font-style:italic;text-align:center;">No procedures or equipment used for this visit</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 10. BILL SUMMARY -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-money-bill-wave" style="color:var(--success);font-size:1.2rem;"></i>
            <h3>10. Bill Summary</h3>
            <span class="badge-count"><?= count($bills) ?> bill(s)</span>
        </div>
        <?php if (!empty($bills)): ?>
            <div class="bill-summary-grid">
                <div class="bill-card total">
                    <span class="bill-icon">💰</span>
                    <div class="bill-amount">TSh <?= number_format($total_amount, 0) ?></div>
                    <div class="bill-label">Total Amount</div>
                </div>
                <div class="bill-card pending">
                    <span class="bill-icon">⏳</span>
                    <div class="bill-amount">TSh <?= number_format($total_amount - $total_paid, 0) ?></div>
                    <div class="bill-label">Pending Balance</div>
                </div>
                <div class="bill-card paid">
                    <span class="bill-icon">✅</span>
                    <div class="bill-amount">TSh <?= number_format($total_paid, 0) ?></div>
                    <div class="bill-label">Paid Amount</div>
                </div>
                <div class="bill-card cancelled">
                    <span class="bill-icon">❌</span>
                    <div class="bill-amount"><?= $cancelled_bills ?></div>
                    <div class="bill-label">Cancelled Bills</div>
                </div>
            </div>
            
            <div style="margin-top:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <span style="font-size:0.75rem;font-weight:500;">Payment Status:</span>
                <span class="status-badge <?= $total_balance <= 0 ? 'paid' : 'partial' ?>" style="font-size:0.7rem;padding:3px 14px;">
                    <?= $total_balance <= 0 ? '✅ Fully Paid' : '⏳ Pending / Partial' ?>
                </span>
                <?php if ($total_balance > 0): ?>
                    <span style="font-size:0.7rem;color:var(--text-secondary);">
                        Balance Due: TSh <?= number_format($total_balance, 0) ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if (count($bills) > 0): ?>
                <div style="margin-top:10px;">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th><i class="fas fa-file-invoice"></i> Bill Number</th>
                                    <th><i class="fas fa-money-bill"></i> Total</th>
                                    <th><i class="fas fa-check-circle"></i> Paid</th>
                                    <th><i class="fas fa-balance-scale"></i> Balance</th>
                                    <th><i class="fas fa-info-circle"></i> Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bills as $bill): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong></td>
                                        <td>TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                                        <td>TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                        <td><strong style="color:<?= ($bill['balance'] ?? 0) > 0 ? 'var(--danger)' : 'var(--success)' ?>;">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></strong></td>
                                        <td>
                                            <span class="status-badge <?= $bill['status'] ?? 'pending' ?>">
                                                <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="pdf-empty" style="padding:8px 0;color:var(--text-secondary);font-style:italic;text-align:center;">No bills found for this visit</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer no-print">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Visit Details
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($full_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('h:i:s A') ?></span>
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
                Visit PDF Preview - <?= htmlspecialchars($visit['visit_number'] ?? 'Visit') ?>
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
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short', 
            day: 'numeric', 
            year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit', 
            hour12: true
        });
        
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) {
            dtEl.innerHTML = '<i class="fas fa-clock mr-1" style="color: var(--primary-light);"></i> ' + dateStr + ' • ' + timeStr;
        }
        
        var ftEl = document.getElementById('footerTimestamp');
        if (ftEl) {
            ftEl.textContent = 'Last updated: ' + timeStr;
        }
    }
    
    updateDateTime();
    setInterval(updateDateTime, 1000);

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
        
        if (!toast) return;
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
    // GENERATE PDF - FIXED: Starts at top, All 10 sections included
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        
        var hasVitalSigns = <?= $vital_signs ? 'true' : 'false' ?>;
        var hasClinicalInfo = <?= (!empty($visit['complaint']) || !empty($visit['symptoms']) || !empty($visit['hpi']) || !empty($visit['physical_exam']) || !empty($visit['notes'])) ? 'true' : 'false' ?>;
        var hasLabTests = <?= count($lab_tests) > 0 ? 'true' : 'false' ?>;
        var hasDiagnosis = <?= (!empty($visit['diagnosis']) || !empty($visit['disease_name']) || !empty($visit['treatment'])) ? 'true' : 'false' ?>;
        var hasPrescriptions = <?= count($prescriptions) > 0 ? 'true' : 'false' ?>;
        var hasProcedures = <?= (count($procedures) > 0 || count($equipment_used) > 0) ? 'true' : 'false' ?>;
        var hasBills = <?= !empty($bills) ? 'true' : 'false' ?>;
        
        var vitalSignsHTML = '';
        if (hasVitalSigns) {
            vitalSignsHTML = `
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:4px;">
                    <div style="background:#E8F0FE;padding:4px 6px;border-radius:6px;text-align:center;border-left:3px solid #0B5ED7;"><div style="font-size:0.45rem;font-weight:600;color:#64748B;text-transform:uppercase;">🌡️ Temperature</div><div style="font-weight:700;color:#0B5ED7;font-size:14px;"><?= $vital_signs['temperature'] ?? 'N/A' ?> °C</div></div>
                    <div style="background:#D1FAE5;padding:4px 6px;border-radius:6px;text-align:center;border-left:3px solid #059669;"><div style="font-size:0.45rem;font-weight:600;color:#64748B;text-transform:uppercase;">❤️ Blood Pressure</div><div style="font-weight:700;color:#059669;font-size:14px;"><?= !empty($vital_signs['blood_pressure_systolic']) && !empty($vital_signs['blood_pressure_diastolic']) ? $vital_signs['blood_pressure_systolic'] . ' / ' . $vital_signs['blood_pressure_diastolic'] . ' mmHg' : 'N/A' ?></div></div>
                    <div style="background:#EDE9FE;padding:4px 6px;border-radius:6px;text-align:center;border-left:3px solid #7C3AED;"><div style="font-size:0.45rem;font-weight:600;color:#64748B;text-transform:uppercase;">💓 Pulse Rate</div><div style="font-weight:700;color:#7C3AED;font-size:14px;"><?= $vital_signs['pulse_rate'] ?? 'N/A' ?> bpm</div></div>
                    <div style="background:#FEF3C7;padding:4px 6px;border-radius:6px;text-align:center;border-left:3px solid #D97706;"><div style="font-size:0.45rem;font-weight:600;color:#64748B;text-transform:uppercase;">⚖️ Weight</div><div style="font-weight:700;color:#D97706;font-size:14px;"><?= $vital_signs['weight'] ?? 'N/A' ?> kg</div></div>
                    <div style="background:#D1FAE5;padding:4px 6px;border-radius:6px;text-align:center;border-left:3px solid #0D9488;"><div style="font-size:0.45rem;font-weight:600;color:#64748B;text-transform:uppercase;">📏 Height</div><div style="font-weight:700;color:#0D9488;font-size:14px;"><?= $vital_signs['height'] ?? 'N/A' ?> cm</div></div>
                    <div style="background:#FEE2E2;padding:4px 6px;border-radius:6px;text-align:center;border-left:3px solid #DC2626;"><div style="font-size:0.45rem;font-weight:600;color:#64748B;text-transform:uppercase;">📊 BMI</div><div style="font-weight:700;color:#DC2626;font-size:14px;"><?= $vital_signs['bmi'] ?? 'N/A' ?> kg/m²</div></div>
                </div>
            `;
        } else {
            vitalSignsHTML = `<div class="pdf-empty">No vital signs recorded for this visit</div>`;
        }
        
        var clinicalInfoHTML = '';
        if (hasClinicalInfo) {
            var complaintHTML = '';
            var symptomsHTML = '';
            var hpiHTML = '';
            var physicalExamHTML = '';
            var notesHTML = '';
            
            if ('<?= !empty($visit['complaint']) ?>') {
                complaintHTML = `<div style="padding:3px 0;background:#E8F0FE;padding:4px 8px;border-radius:4px;border-left:3px solid #0B5ED7;margin-bottom:2px;font-size:14px;">
                    <span style="font-weight:600;color:#64748B;">Complaint:</span> <span class="text-wrap-2"><?= nl2br(htmlspecialchars($visit['complaint'])) ?></span>
                </div>`;
            }
            if ('<?= !empty($visit['symptoms']) ?>') {
                symptomsHTML = `<div style="padding:3px 0;background:#FEF3C7;padding:4px 8px;border-radius:4px;border-left:3px solid #D97706;margin-bottom:2px;font-size:14px;">
                    <span style="font-weight:600;color:#64748B;">Symptoms:</span> <span class="text-wrap-2"><?= nl2br(htmlspecialchars($visit['symptoms'])) ?></span>
                </div>`;
            }
            if ('<?= !empty($visit['hpi']) ?>') {
                hpiHTML = `<div style="padding:3px 0;background:#EDE9FE;padding:4px 8px;border-radius:4px;border-left:3px solid #7C3AED;margin-bottom:2px;font-size:14px;">
                    <span style="font-weight:600;color:#64748B;">HPI:</span> <span class="text-wrap-2"><?= nl2br(htmlspecialchars($visit['hpi'])) ?></span>
                </div>`;
            }
            if ('<?= !empty($visit['physical_exam']) ?>') {
                physicalExamHTML = `<div style="padding:3px 0;background:#D1FAE5;padding:4px 8px;border-radius:4px;border-left:3px solid #059669;margin-bottom:2px;font-size:14px;">
                    <span style="font-weight:600;color:#64748B;">Physical Exam:</span> <span class="text-wrap-2"><?= nl2br(htmlspecialchars($visit['physical_exam'])) ?></span>
                </div>`;
            }
            if ('<?= !empty($visit['notes']) ?>') {
                notesHTML = `<div style="padding:3px 0;background:#F1F5F9;padding:4px 8px;border-radius:4px;border-left:3px solid #94A3B8;margin-bottom:2px;font-size:14px;">
                    <span style="font-weight:600;color:#64748B;">Notes:</span> <span class="text-wrap-2"><?= nl2br(htmlspecialchars($visit['notes'])) ?></span>
                </div>`;
            }
            clinicalInfoHTML = complaintHTML + symptomsHTML + hpiHTML + physicalExamHTML + notesHTML;
        } else {
            clinicalInfoHTML = `<div class="pdf-empty">No clinical information recorded for this visit</div>`;
        }
        
        var labTestsHTML = '';
        if (hasLabTests) {
            var labRows = '';
            <?php foreach ($lab_tests as $test): ?>
                labRows += `<tr><td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:14px;"><strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:14px;"><?= isset($test['created_at']) ? date('M d, Y', strtotime($test['created_at'])) : 'N/A' ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:14px;"><?= ucfirst($test['status'] ?? 'Pending') ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:14px;" class="text-wrap-2"><?= !empty($test['results']) ? '✅ ' . htmlspecialchars(substr($test['results'], 0, 50)) : '⏳ Pending' ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:14px;"><?= htmlspecialchars($test['technician_name'] ?? 'Not assigned') ?></td>
                </tr>`;
            <?php endforeach; ?>
            labTestsHTML = `
                <div class="pdf-table-wrap">
                    <table class="pdf-table" style="font-size:14px;width:100%;border-collapse:collapse;">
                        <thead>
                            <tr><th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:13px;">Test Name</th><th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:13px;">Date</th><th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:13px;">Status</th><th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:13px;">Results</th><th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:13px;">Technician</th></tr>
                        </thead>
                        <tbody>
                            ` + labRows + `
                        </tbody>
                    </table>
                </div>
            `;
        } else {
            labTestsHTML = `<div class="pdf-empty">No lab tests found for this visit</div>`;
        }
        
        var diagnosisHTML = '';
        if (hasDiagnosis) {
            var diseaseNameHTML = '';
            var diseaseCodeHTML = '';
            var diagnosisDescHTML = '';
            var treatmentHTML = '';
            
            if ('<?= !empty($visit['disease_name']) ?>') {
                diseaseNameHTML = `<div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;font-size:14px;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;">Disease Name</span><span style="font-size:14px;"><strong><?= htmlspecialchars($visit['disease_name']) ?></strong></span></div>`;
            }
            if ('<?= !empty($visit['disease_code_full']) || !empty($visit['icd_code']) ?>') {
                diseaseCodeHTML = `<div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;font-size:14px;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;">Disease Code</span><span style="font-size:14px;"><?= htmlspecialchars($visit['disease_code_full'] ?? '') ?> <?= !empty($visit['icd_code']) ? '(ICD: ' . htmlspecialchars($visit['icd_code']) . ')' : '' ?></span></div>`;
            }
            if ('<?= !empty($visit['diagnosis']) ?>') {
                diagnosisDescHTML = `<div style="padding:3px 0;background:#E8F0FE;padding:4px 8px;border-radius:4px;border-left:3px solid #0B5ED7;margin:2px 0;font-size:14px;">
                    <span style="font-weight:600;color:#64748B;">Description:</span> <span class="text-wrap-2"><?= nl2br(htmlspecialchars($visit['diagnosis'])) ?></span>
                </div>`;
            }
            if ('<?= !empty($visit['treatment']) ?>') {
                treatmentHTML = `<div style="padding:3px 0;background:#D1FAE5;padding:4px 8px;border-radius:4px;border-left:3px solid #059669;margin:2px 0;font-size:14px;">
                    <span style="font-weight:600;color:#64748B;">Treatment:</span> <span class="text-wrap-2"><?= nl2br(htmlspecialchars($visit['treatment'])) ?></span>
                </div>`;
            }
            diagnosisHTML = diseaseNameHTML + diseaseCodeHTML + diagnosisDescHTML + treatmentHTML;
        } else {
            diagnosisHTML = `<div class="pdf-empty">No diagnosis recorded for this visit</div>`;
        }
        
        var medicationsHTML = '';
        if (hasPrescriptions) {
            var presHTML = '';
            <?php foreach ($prescriptions as $pres): 
                $items = $prescription_items[$pres['id']] ?? [];
            ?>
                presHTML += `
                    <div style="margin-bottom:6px;padding:5px 8px;background:#F8FAFC;border-radius:6px;border:1px solid #E2E8F0;">
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:14px;"><strong>#<?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?></strong> <span style="font-size:12px;"><?= ucfirst($pres['status'] ?? 'Pending') ?></span></div>
                        <?php if (!empty($pres['diagnosis'])): ?>
                        <div style="font-size:13px;color:#64748B;"><strong>Diagnosis:</strong> <?= htmlspecialchars($pres['diagnosis']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($pres['instructions'])): ?>
                        <div style="font-size:13px;color:#64748B;">📝 <span class="text-wrap-2"><?= nl2br(htmlspecialchars($pres['instructions'])) ?></span></div>
                        <?php endif; ?>
                        <?php if (count($items) > 0): ?>
                        <div class="pdf-table-wrap">
                            <table class="pdf-table" style="font-size:13px;margin-top:4px;width:100%;border-collapse:collapse;">
                                <thead>
                                    <tr><th style="background:#059669;color:white;padding:3px 6px;text-align:left;font-size:12px;">Medication</th><th style="background:#059669;color:white;padding:3px 6px;text-align:left;font-size:12px;">Dosage</th><th style="background:#059669;color:white;padding:3px 6px;text-align:left;font-size:12px;">Frequency</th><th style="background:#059669;color:white;padding:3px 6px;text-align:left;font-size:12px;">Qty</th><th style="background:#059669;color:white;padding:3px 6px;text-align:left;font-size:12px;">Instructions</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                    <tr><td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:13px;"><strong><?= htmlspecialchars($item['medication_name'] ?? $item['inventory_medication_name'] ?? 'N/A') ?></strong></td>
                                        <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:13px;"><?= htmlspecialchars($item['dosage'] ?? 'N/A') ?></td>
                                        <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:13px;"><?= htmlspecialchars($item['frequency'] ?? 'N/A') ?></td>
                                        <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:13px;"><?= $item['quantity'] ?? 0 ?></td>
                                        <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:13px;" class="text-wrap-2"><?= htmlspecialchars($item['instructions'] ?? '—') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                `;
            <?php endforeach; ?>
            medicationsHTML = presHTML;
        } else {
            medicationsHTML = `<div class="pdf-empty">No medications prescribed for this visit</div>`;
        }
        
        var proceduresHTML = '';
        if (hasProcedures) {
            var procRows = '';
            <?php if (count($procedures) > 0): ?>
                <?php foreach ($procedures as $proc): ?>
                    procRows += `<tr><td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:14px;"><strong><?= htmlspecialchars($proc['procedure_name'] ?? 'N/A') ?></strong></td>
                        <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:14px;"><?= ucfirst($proc['status'] ?? 'Pending') ?></td>
                        <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:14px;" class="text-wrap-2"><?= htmlspecialchars($proc['equipment_name'] ?? 'None') ?></td>
                        <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:14px;"><?= $proc['equipment_used_quantity'] ?? 1 ?></td>
                    </tr>`;
                <?php endforeach; ?>
            <?php endif; ?>
            
            var equipRows = '';
            <?php if (count($equipment_used) > 0): ?>
                <?php foreach ($equipment_used as $eq): ?>
                    equipRows += `<tr><td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:13px;"><?= htmlspecialchars($eq['equipment_name'] ?? 'N/A') ?></td>
                        <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:13px;"><?= htmlspecialchars($eq['batch_number'] ?? 'N/A') ?></td>
                        <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:13px;"><?= $eq['quantity'] ?? 0 ?></td>
                        <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:13px;text-transform:capitalize;"><?= htmlspecialchars($eq['reference_type'] ?? 'N/A') ?></td>
                    </tr>`;
                <?php endforeach; ?>
            <?php endif; ?>
            
            proceduresHTML = '';
            <?php if (count($procedures) > 0): ?>
                proceduresHTML += `
                    <div class="pdf-table-wrap">
                        <table class="pdf-table" style="font-size:14px;width:100%;border-collapse:collapse;">
                            <thead>
                                <tr><th style="background:#059669;color:white;padding:3px 6px;text-align:left;font-size:13px;">Procedure</th><th style="background:#059669;color:white;padding:3px 6px;text-align:left;font-size:13px;">Status</th><th style="background:#059669;color:white;padding:3px 6px;text-align:left;font-size:13px;">Equipment</th><th style="background:#059669;color:white;padding:3px 6px;text-align:left;font-size:13px;">Qty</th></tr>
                            </thead>
                            <tbody>
                                ` + procRows + `
                            </tbody>
                        </table>
                    </div>
                `;
            <?php endif; ?>
            
            <?php if (count($equipment_used) > 0): ?>
                proceduresHTML += `
                    <div style="margin-top:4px;">
                        <div style="font-size:13px;font-weight:600;color:#64748B;margin-bottom:2px;">Medical Equipment Used:</div>
                        <div class="pdf-table-wrap">
                            <table class="pdf-table" style="font-size:13px;width:100%;border-collapse:collapse;">
                                <thead>
                                    <tr><th style="background:#059669;color:white;padding:3px 6px;text-align:left;font-size:12px;">Equipment</th><th style="background:#059669;color:white;padding:3px 6px;text-align:left;font-size:12px;">Batch</th><th style="background:#059669;color:white;padding:3px 6px;text-align:left;font-size:12px;">Qty</th><th style="background:#059669;color:white;padding:3px 6px;text-align:left;font-size:12px;">Reference</th></tr>
                                </thead>
                                <tbody>
                                    ` + equipRows + `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            <?php endif; ?>
        } else {
            proceduresHTML = `<div class="pdf-empty">No procedures or equipment used for this visit</div>`;
        }
        
        var billsHTML = '';
        if (hasBills) {
            var billRows = '';
            <?php foreach ($bills as $bill): ?>
                billRows += `<tr><td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:13px;"><strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong></td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:13px;">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:13px;">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:13px;color:<?= ($bill['balance'] ?? 0) > 0 ? '#DC2626' : '#059669' ?>;">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:13px;"><?= ucfirst($bill['status'] ?? 'Pending') ?></td>
                </tr>`;
            <?php endforeach; ?>
            
            billsHTML = `
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:4px;margin:4px 0;">
                    <div style="background:#E8F0FE;padding:4px 8px;border-radius:6px;text-align:center;border:1px solid #6EA8FE;"><div style="font-size:16px;font-weight:700;color:#0B5ED7;">TSh <?= number_format($total_amount, 0) ?></div><div style="font-size:10px;color:#64748B;text-transform:uppercase;">💰 Total</div></div>
                    <div style="background:#FEF3C7;padding:4px 8px;border-radius:6px;text-align:center;border:1px solid #D97706;"><div style="font-size:16px;font-weight:700;color:#D97706;">TSh <?= number_format($total_amount - $total_paid, 0) ?></div><div style="font-size:10px;color:#64748B;text-transform:uppercase;">⏳ Pending</div></div>
                    <div style="background:#D1FAE5;padding:4px 8px;border-radius:6px;text-align:center;border:1px solid #059669;"><div style="font-size:16px;font-weight:700;color:#059669;">TSh <?= number_format($total_paid, 0) ?></div><div style="font-size:10px;color:#64748B;text-transform:uppercase;">✅ Paid</div></div>
                    <div style="background:#FEE2E2;padding:4px 8px;border-radius:6px;text-align:center;border:1px solid #DC2626;"><div style="font-size:16px;font-weight:700;color:#DC2626;"><?= $cancelled_bills ?></div><div style="font-size:10px;color:#64748B;text-transform:uppercase;">❌ Cancelled</div></div>
                </div>
                <div style="margin-top:2px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:14px;">
                    <span style="font-size:13px;font-weight:600;">Status:</span>
                    <span style="font-size:13px;padding:2px 10px;border-radius:12px;background:<?= $total_balance <= 0 ? '#D1FAE5' : '#FEF3C7' ?>;color:<?= $total_balance <= 0 ? '#059669' : '#D97706' ?>;">
                        <?= $total_balance <= 0 ? '✅ Fully Paid' : '⏳ Pending / Partial' ?>
                    </span>
                </div>
                <?php if (count($bills) > 0): ?>
                <div class="pdf-table-wrap">
                    <table class="pdf-table" style="font-size:13px;margin-top:4px;width:100%;border-collapse:collapse;">
                        <thead>
                            <tr><th style="background:#059669;color:white;padding:3px 6px;text-align:left;font-size:12px;">Bill Number</th><th style="background:#059669;color:white;padding:3px 6px;text-align:right;font-size:12px;">Total</th><th style="background:#059669;color:white;padding:3px 6px;text-align:right;font-size:12px;">Paid</th><th style="background:#059669;color:white;padding:3px 6px;text-align:right;font-size:12px;">Balance</th><th style="background:#059669;color:white;padding:3px 6px;text-align:left;font-size:12px;">Status</th></tr>
                        </thead>
                        <tbody>
                            ` + billRows + `
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            `;
        } else {
            billsHTML = `<div class="pdf-empty">No bills found for this visit</div>`;
        }
        
        var html = `
            <!-- PDF HEADER -->
            <div class="pdf-header pdf-header-section" style="text-align:center;padding-bottom:12px;border-bottom:3px solid #0B5ED7;margin-bottom:16px;page-break-after:avoid;break-after:avoid;margin-top:0;padding-top:0;">
                <div class="pdf-logo" style="display:flex;flex-direction:column;align-items:center;justify-content:center;margin-bottom:4px;">
                    <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" style="height:55px;width:auto;object-fit:contain;display:block;margin:0 auto;" onerror="this.style.display='none'">
                    <div style="font-size:1.4rem;font-weight:800;color:#0B5ED7;letter-spacing:-0.5px;margin-top:4px;">BRAICK DISPENSARY</div>
                    <div style="font-size:0.75rem;color:#64748B;">Tunajali Afya Yako</div>
                </div>
                <div style="display:flex;justify-content:center;gap:14px;flex-wrap:wrap;margin-top:4px;padding-top:4px;border-top:1px solid #E2E8F0;font-size:0.6rem;color:#64748B;">
                    <span>📞 Admin Contacts: <?= !empty($admin_phones) ? implode(' | ', $admin_phones) : ($branch_phone ?? '+255 700 000 001') ?></span>
                    <span>🏢 Branch: <?= htmlspecialchars($visit['branch_name'] ?? 'Dodoma') ?></span>
                    <span>📅 ${new Date().toLocaleDateString('en-US', { weekday:'short', month:'short', day:'numeric', year:'numeric' })}</span>
                </div>
                <div style="font-size:0.8rem;font-weight:600;color:#0B5ED7;margin-top:4px;background:#E8F0FE;padding:4px 14px;border-radius:20px;display:inline-block;">
                    📋 Visit Details Report - <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                </div>
            </div>
            
            <!-- 1. VISIT INFORMATION -->
            <div class="pdf-section">
                <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin:6px 0 4px 0;">
                    <i class="fas fa-info-circle"></i> 1. Visit Information
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:2px 14px;font-size:14px;">
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Visit Number</span><span style="font-size:14px;"><strong><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></strong></span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Status</span><span style="font-size:14px;"><?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'N/A')) ?></span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Visit Type</span><span style="font-size:14px;"><?= htmlspecialchars($visit['visit_type'] ?? 'N/A') ?></span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Date & Time</span><span style="font-size:14px;"><?= isset($visit['visit_date']) ? date('F d, Y h:i A', strtotime($visit['visit_date'])) : 'N/A' ?></span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;grid-column:span 2;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Branch</span><span style="font-size:14px;"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;grid-column:span 2;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Consultation Fee</span><span style="font-size:14px;">TSh <?= number_format($visit['consultation_fee'] ?? 0, 0) ?></span></div>
                    <?php if (!empty($visit['follow_up_date'])): ?>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;grid-column:span 2;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Follow-up Date</span><span style="font-size:14px;"><?= date('F d, Y', strtotime($visit['follow_up_date'])) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 2. PATIENT INFORMATION -->
            <div class="pdf-section">
                <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin:6px 0 4px 0;">
                    <i class="fas fa-user"></i> 2. Patient Information
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:2px 14px;font-size:14px;">
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;grid-column:span 3;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Full Name</span><span style="font-size:14px;"><strong><?= htmlspecialchars($visit['patient_name']) ?></strong></span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Patient ID</span><span style="font-size:14px;"><?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?></span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Phone</span><span style="font-size:14px;"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Email</span><span style="font-size:14px;"><?= htmlspecialchars($visit['email'] ?? 'N/A') ?></span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Emergency Contact</span><span style="font-size:14px;"><?= htmlspecialchars($visit['emergency_contact'] ?? 'N/A') ?></span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Gender</span><span style="font-size:14px;"><?= ucfirst($visit['gender'] ?? 'N/A') ?></span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Marital Status</span><span style="font-size:14px;"><?= htmlspecialchars($visit['marital_status'] ?? 'N/A') ?></span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Date of Birth</span><span style="font-size:14px;"><?= !empty($visit['date_of_birth']) ? date('F d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?></span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Blood Group</span><span style="font-size:14px;"><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;grid-column:span 3;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Address</span><span style="font-size:14px;"><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></span></div>
                    <?php if (!empty($visit['allergies'])): ?>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;grid-column:span 3;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Allergies</span><span style="font-size:14px;color:#DC2626;"><?= htmlspecialchars($visit['allergies']) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 3. DOCTOR INFORMATION -->
            <div class="pdf-section">
                <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin:6px 0 4px 0;">
                    <i class="fas fa-user-md"></i> 3. Doctor Information
                </div>
                <?php if ($visit['doctor_id']): ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:2px 14px;font-size:14px;">
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;grid-column:span 2;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Doctor Name</span><span style="font-size:14px;"><strong>Dr. <?= htmlspecialchars($visit['doctor_name']) ?></strong></span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Specialty</span><span style="font-size:14px;"><?= htmlspecialchars($visit['specialty'] ?? 'General Practitioner') ?></span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Phone</span><span style="font-size:14px;"><?= htmlspecialchars($visit['doctor_phone'] ?? 'N/A') ?></span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;grid-column:span 2;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Status</span><span style="font-size:14px;"><?= ($visit['doctor_is_online'] ?? 0) ? '🟢 Online' : '🔴 Offline' ?></span></div>
                </div>
                <?php else: ?>
                <div class="pdf-empty">No doctor assigned to this visit</div>
                <?php endif; ?>
            </div>
            
            <!-- 4. VITAL SIGNS -->
            <div class="pdf-section">
                <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin:6px 0 4px 0;">
                    <i class="fas fa-heartbeat"></i> 4. Vital Signs
                </div>
                ` + vitalSignsHTML + `
            </div>
            
            <!-- 5. CLINICAL INFORMATION -->
            <div class="pdf-section">
                <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin:6px 0 4px 0;">
                    <i class="fas fa-file-medical-alt"></i> 5. Clinical Information
                </div>
                ` + clinicalInfoHTML + `
            </div>
            
            <!-- 6. LAB TESTS -->
            <div class="pdf-section">
                <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin:6px 0 4px 0;">
                    <i class="fas fa-flask"></i> 6. Lab Tests
                </div>
                ` + labTestsHTML + `
            </div>
            
            <!-- 7. DIAGNOSIS -->
            <div class="pdf-section">
                <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin:6px 0 4px 0;">
                    <i class="fas fa-stethoscope"></i> 7. Diagnosis
                </div>
                ` + diagnosisHTML + `
            </div>
            
            <!-- 8. MEDICATIONS -->
            <div class="pdf-section">
                <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin:6px 0 4px 0;">
                    <i class="fas fa-prescription"></i> 8. Medications
                </div>
                ` + medicationsHTML + `
            </div>
            
            <!-- 9. PROCEDURES & EQUIPMENT -->
            <div class="pdf-section">
                <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin:6px 0 4px 0;">
                    <i class="fas fa-syringe"></i> 9. Procedures & Equipment
                </div>
                ` + proceduresHTML + `
            </div>
            
            <!-- 10. BILL SUMMARY -->
            <div class="pdf-section">
                <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin:6px 0 4px 0;">
                    <i class="fas fa-money-bill-wave"></i> 10. Bill Summary
                </div>
                ` + billsHTML + `
            </div>
            
            <!-- PDF FOOTER WITH OFFICIAL STAMP -->
            <div class="pdf-footer">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                    <div style="font-size:14px;color:#64748B;">
                        <span>Technician: _________________</span>
                        <span style="margin-left:14px;">Date: <?= date('F d, Y') ?></span>
                    </div>
                    <div style="text-align:center;padding:4px 14px;border:3px solid #0B5ED7;border-radius:10px;background:#E8F0FE;min-width:140px;">
                        <div style="font-size:10px;color:#64748B;text-transform:uppercase;letter-spacing:1px;font-weight:700;">Official Stamp</div>
                        <div style="font-size:14px;font-weight:800;color:#0B5ED7;">BRAICK DISPENSARY</div>
                        <div style="font-size:12px;color:#64748B;margin-top:2px;">Approved By: _________________</div>
                        <div style="font-size:10px;color:#94A3B8;margin-top:2px;">Date: <?= date('F d, Y') ?></div>
                    </div>
                </div>
                <div style="text-align:center;margin-top:6px;font-size:12px;color:#94A3B8;">
                    Braick Dispensary • Generated on <?= date('F d, Y h:i:s A') ?> • All rights reserved
                </div>
            </div>
        `;
        
        content.innerHTML = html;
        modal.classList.add('active');
        
        // Scroll to top of modal body
        var modalBody = document.getElementById('pdfModalBody');
        if (modalBody) {
            modalBody.scrollTop = 0;
        }
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
                logging: false,
                allowTaint: true
            },
            jsPDF: { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait' 
            },
            pagebreak: { 
                mode: ['css', 'legacy']
            }
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

    console.log('%c🏥 Braick Dispensary - Complete Visit Details', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Visit: <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ FIXED: PDF starts at top | All 10 sections included | Empty sections show "No data" message', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📞 Admin Contacts: <?= !empty($admin_phones) ? implode(' | ', $admin_phones) : ($branch_phone ?? '+255 700 000 001') ?>', 'font-size:13px; color:#D97706;');
</script>

</body>
</html>