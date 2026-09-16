<?php
// ================================================================
// FILE: frontend/pages/admin/view_visit.php
// RECEPTION/ADMIN - VIEW VISIT DETAILS
// ✅ BLUE THEME ONLY
// ✅ Uses doctor_id + receptionist_id (no created_by)
// ✅ Removed s.service_code (column doesn't exist)
// ✅ Auto-detect technician column (performed_by / technician_id)
// ✅ Uses SHARED admin_header + admin_sidebar
// ✅ NEW: Discount + Premium columns in Bill Summary
// ✅ PDF generation with 7 vital signs
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

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

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'reception';
$branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';

if ($visit_id <= 0) {
    header('Location: visits.php');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    $unread_notifications = 0;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) { $unread_notifications = 0; }
    
    // Admin phones
    $admin_phones = [];
    try {
        $stmt = $db->prepare("SELECT phone FROM users WHERE role = 'admin' AND status = 'active' AND phone IS NOT NULL LIMIT 3");
        $stmt->execute();
        $admin_phones = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { $admin_phones = []; }
    
    $branch_phone = '';
    try {
        $stmt = $db->prepare("SELECT phone FROM branches WHERE id = ?");
        $stmt->execute([$branch_id]);
        $branch_phone = $stmt->fetchColumn();
    } catch (Exception $e) { $branch_phone = ''; }
    
    // ================================================================
    // FETCH VISIT
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
               d.id as doctor_id,
               d.full_name as doctor_name, 
               d.specialty, 
               d.phone as doctor_phone,
               d.profile_pic as doctor_profile_pic,
               d.is_online as doctor_is_online,
               r.full_name as receptionist_name,
               r.phone as receptionist_phone,
               b.name as branch_name,
               b.phone as branch_phone,
               d2.disease_name,
               d2.disease_code as disease_code_full,
               d2.icd_code
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users d ON v.doctor_id = d.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        LEFT JOIN branches b ON v.branch_id = b.id
        LEFT JOIN diseases d2 ON v.disease_id = d2.id
        WHERE v.id = ?
    ");
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit) {
        $error = "Visit not found.";
    }
    
    // ================================================================
    // PRESCRIPTIONS
    // ================================================================
    $prescriptions = [];
    $prescription_items = [];
    if ($visit) {
        $stmt = $db->prepare("SELECT * FROM prescriptions WHERE visit_id = ? ORDER BY created_at DESC");
        $stmt->execute([$visit_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
            $prescription_items[$pres['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // ================================================================
    // LAB TESTS (auto-detect technician column)
    // ================================================================
    $lab_tests = [];
    if ($visit) {
        $tech_column = 'technician_id';
        try {
            $col_check = $db->query("SHOW COLUMNS FROM lab_tests LIKE 'performed_by'");
            if ($col_check->rowCount() > 0) $tech_column = 'performed_by';
        } catch (Exception $e) {}
        
        $has_test_code = false;
        try {
            $col_check = $db->query("SHOW COLUMNS FROM lab_tests_catalog LIKE 'test_code'");
            if ($col_check->rowCount() > 0) $has_test_code = true;
        } catch (Exception $e) {}
        
        $has_ref_range = false;
        try {
            $col_check = $db->query("SHOW COLUMNS FROM lab_tests LIKE 'reference_range'");
            if ($col_check->rowCount() > 0) $has_ref_range = true;
        } catch (Exception $e) {}
        
        $select_cols = "lt.*, u.full_name as technician_name, u.profile_pic as technician_profile_pic";
        if ($has_test_code) $select_cols .= ", ltc.test_code";
        if ($has_ref_range) $select_cols .= ", lt.reference_range";
        
        try {
            $stmt = $db->prepare("
                SELECT $select_cols
                FROM lab_tests lt
                LEFT JOIN users u ON lt.$tech_column = u.id
                LEFT JOIN lab_tests_catalog ltc ON lt.test_id = ltc.id
                WHERE lt.visit_id = ? 
                ORDER BY lt.created_at DESC
            ");
            $stmt->execute([$visit_id]);
            $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $stmt = $db->prepare("SELECT * FROM lab_tests WHERE visit_id = ? ORDER BY created_at DESC");
            $stmt->execute([$visit_id]);
            $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // ================================================================
    // VITAL SIGNS
    // ================================================================
    $vital_signs = null;
    if ($visit) {
        $stmt = $db->prepare("SELECT * FROM vital_signs WHERE visit_id = ? ORDER BY recorded_at DESC LIMIT 1");
        $stmt->execute([$visit_id]);
        $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $spo2_value = null;
    $spo2_class = 'normal';
    $spo2_label = 'Normal';
    
    if ($vital_signs && !empty($vital_signs['oxygen_saturation'])) {
        $spo2_value = (int)$vital_signs['oxygen_saturation'];
        if ($spo2_value < 90) { $spo2_class = 'critical'; $spo2_label = 'Critical'; }
        elseif ($spo2_value < 95) { $spo2_class = 'low'; $spo2_label = 'Low'; }
    }
    
    // ================================================================
    // BILLS — WITH DISCOUNT & PREMIUM
    // ================================================================
    $bills = [];
    $total_amount = 0;
    $total_paid = 0;
    $total_balance = 0;
    $total_discount_sum = 0;
    $total_premium_sum = 0;
    $pending_bills = 0;
    $cancelled_bills = 0;
    $paid_bills = 0;
    
    if ($visit) {
        $stmt = $db->prepare("SELECT * FROM bills WHERE visit_id = ? ORDER BY created_at DESC");
        $stmt->execute([$visit_id]);
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($bills as $bill) {
            $total_amount += $bill['total_amount'] ?? 0;
            $total_paid += $bill['paid_amount'] ?? 0;
            $total_balance += $bill['balance'] ?? 0;
            $total_discount_sum += (float)($bill['total_discount'] ?? 0);
            $total_premium_sum += (float)($bill['premium_amount'] ?? 0);
            
            if (in_array($bill['status'], ['pending', 'partial'])) $pending_bills++;
            elseif ($bill['status'] == 'paid') $paid_bills++;
            elseif ($bill['status'] == 'cancelled') $cancelled_bills++;
        }
    }
    
    // ================================================================
    // PROCEDURES
    // ================================================================
    $procedures = [];
    if ($visit) {
        try {
            $stmt = $db->prepare("
                SELECT p.*, pc.procedure_code, pc.category as procedure_category
                FROM procedures p
                LEFT JOIN procedures_catalog pc ON p.procedure_id = pc.id
                WHERE p.visit_id = ? AND p.status != 'cancelled'
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$visit_id]);
            $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $stmt = $db->prepare("SELECT * FROM procedures WHERE visit_id = ? ORDER BY created_at DESC");
            $stmt->execute([$visit_id]);
            $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // ================================================================
    // EQUIPMENT USED
    // ================================================================
    $equipment_used = [];
    if ($visit) {
        try {
            $stmt = $db->prepare("
                SELECT DISTINCT sm.*, me.equipment_name, me.batch_number, me.unit, me.selling_price
                FROM stock_movements sm
                JOIN medical_equipment me ON sm.equipment_id = me.id
                WHERE sm.patient_id = ? 
                AND sm.reference_type IN ('prescription', 'procedure', 'lab_test')
                AND sm.movement_type = 'out'
                ORDER BY sm.created_at DESC
            ");
            $stmt->execute([$visit['patient_id']]);
            $equipment_used = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { $equipment_used = []; }
    }
    
    // ================================================================
    // BILL ITEMS
    // ================================================================
    $bill_items = [];
    foreach ($bills as $bill) {
        if (!empty($bill['id'])) {
            $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ? ORDER BY created_at DESC");
            $stmt->execute([$bill['id']]);
            $bill_items[$bill['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $visit = null;
    $prescriptions = [];
    $lab_tests = [];
    $vital_signs = null;
    $bills = [];
    $procedures = [];
    $equipment_used = [];
    $bill_items = [];
    $total_amount = 0; $total_paid = 0; $total_balance = 0;
    $total_discount_sum = 0; $total_premium_sum = 0;
    $pending_bills = 0; $cancelled_bills = 0; $paid_bills = 0;
    $unread_notifications = 0;
    $admin_phones = [];
    $branch_phone = '';
    $spo2_value = null; $spo2_class = 'normal'; $spo2_label = 'Normal';
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
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
           BLUE THEME ONLY
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
            --section-spacing: 10px;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
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
        
        /* PAGE HEADER */
        .page-header-custom {
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
        
        .page-header-custom::before {
            content: '';
            position: absolute;
            top: -50%; right: -10%;
            width: 300px; height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        
        .page-header-custom .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin: 0;
        }
        
        .page-header-custom .page-title i {
            font-size: 1.8rem;
            opacity: 0.9;
        }
        
        .page-header-custom .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 4px;
        }
        
        .page-header-custom .header-badge {
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
        
        .page-header-custom .btn-header {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            cursor: pointer;
            position: relative;
            z-index: 1;
        }
        
        .page-header-custom .btn-header:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* DETAIL CARDS */
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 18px 22px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: var(--section-spacing);
        }
        
        .detail-card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-md);
        }
        
        .card-title-section {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--primary);
            flex-wrap: wrap;
        }
        
        .card-title-section h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
        }
        
        .card-title-section .badge-count {
            background: var(--primary-bg);
            color: var(--primary);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: auto;
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
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px 20px; }
        .grid-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 8px 20px; }
        .col-span-2 { grid-column: span 2; }
        .col-span-3 { grid-column: span 3; }
        .col-span-4 { grid-column: span 4; }
        
        /* STATUS BADGES - ALL BLUE */
        .status-badge {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 3px 14px;
            border-radius: 20px;
            text-transform: capitalize;
        }
        
        .status-badge.pending { background: #DBEAFE; color: #1E40AF; }
        .status-badge.assigned { background: #E8F0FE; color: #0B5ED7; }
        .status-badge.with_doctor { background: #BFDBFE; color: #1E40AF; }
        .status-badge.completed { background: #0B5ED7; color: white; }
        .status-badge.cancelled { background: #94A3B8; color: white; }
        .status-badge.paid { background: #1A73E8; color: white; }
        .status-badge.partial { background: #6EA8FE; color: white; }
        .status-badge.in_progress { background: #93C5FD; color: #0A4CA8; }
        .status-badge.lab_completed { background: #1A73E8; color: white; }
        .status-badge.dispensed { background: #0A4CA8; color: white; }
        .status-badge.confirmed { background: #0B5ED7; color: white; }
        
        [data-theme="dark"] .status-badge.pending { background: #1E3A5F; color: #93C5FD; }
        [data-theme="dark"] .status-badge.assigned { background: #1E40AF; color: #DBEAFE; }
        [data-theme="dark"] .status-badge.completed { background: #1A73E8; color: white; }
        [data-theme="dark"] .status-badge.cancelled { background: #475569; color: white; }
        [data-theme="dark"] .status-badge.paid { background: #0B5ED7; color: white; }
        
        /* VITAL SIGNS */
        .vital-grid-7 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }
        
        .vital-grid-7-row2 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 8px;
        }
        
        .vital-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 12px 8px;
            text-align: center;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .vital-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--primary);
        }
        
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
            color: var(--primary);
            margin-top: 2px;
        }
        
        .vital-card .vital-unit {
            font-size: 0.5rem;
            color: var(--text-secondary);
            font-weight: 400;
        }
        
        .spo2-status {
            display: inline-block;
            font-size: 0.5rem;
            font-weight: 600;
            padding: 1px 8px;
            border-radius: 10px;
            margin-left: 4px;
        }
        
        .spo2-status.normal { background: #DBEAFE; color: #0B5ED7; }
        .spo2-status.low { background: #FEF3C7; color: #D97706; }
        .spo2-status.critical { background: #FEE2E2; color: #DC2626; animation: pulse-spo2 1.5s infinite; }
        
        @keyframes pulse-spo2 {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        /* ================================================================
           BILL CARDS — BLUE THEME (with discount + premium)
           ================================================================ */
        .bill-summary-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
        }
        
        .bill-card {
            background: var(--primary-bg);
            border-radius: var(--radius);
            padding: 14px;
            text-align: center;
            border: 2px solid var(--primary-light);
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
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .bill-card .bill-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 500;
            letter-spacing: 0.05em;
            margin-top: 2px;
        }
        
        /* Highlighted cards */
        .bill-card.total { border-color: #0B5ED7; background: linear-gradient(135deg, #DBEAFE, #BFDBFE); }
        .bill-card.total .bill-amount { color: #0B5ED7; font-size: 1.2rem; }
        
        .bill-card.paid { border-color: #1A73E8; background: linear-gradient(135deg, #E8F0FE, #DBEAFE); }
        .bill-card.paid .bill-amount { color: #1A73E8; }
        
        .bill-card.balance { border-color: #0A4CA8; background: linear-gradient(135deg, #BFDBFE, #93C5FD); }
        .bill-card.balance .bill-amount { color: #0A4CA8; }
        
        .bill-card.discount { border-color: #0B5ED7; background: linear-gradient(135deg, #E8F0FE, #DBEAFE); }
        .bill-card.discount .bill-amount { color: #0B5ED7; }
        
        .bill-card.premium { border-color: #1A73E8; background: linear-gradient(135deg, #DBEAFE, #BFDBFE); }
        .bill-card.premium .bill-amount { color: #0A4CA8; }
        
        .bill-card.cancelled { border-color: #94A3B8; background: linear-gradient(135deg, #F1F5F9, #E2E8F0); }
        .bill-card.cancelled .bill-amount { color: #64748B; }
        
        [data-theme="dark"] .bill-card.total { background: linear-gradient(135deg, #1E40AF, #1E3A8A); }
        [data-theme="dark"] .bill-card.paid { background: linear-gradient(135deg, #1E3A5F, #1E40AF); }
        [data-theme="dark"] .bill-card.balance { background: linear-gradient(135deg, #1E3A8A, #0A4CA8); }
        [data-theme="dark"] .bill-card.discount { background: linear-gradient(135deg, #1E3A5F, #1E40AF); }
        [data-theme="dark"] .bill-card.premium { background: linear-gradient(135deg, #1E40AF, #1E3A8A); }
        [data-theme="dark"] .bill-card.cancelled { background: linear-gradient(135deg, #334155, #475569); }
        
        /* TABLE - BLUE HEADERS */
        .table-wrapper {
            overflow-x: auto;
            margin-top: 8px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
        }
        
        .table-wrapper table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        
        .table-wrapper table th {
            background: var(--primary);
            color: white;
            padding: 10px 12px;
            text-align: left;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        
        .table-wrapper table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            word-wrap: break-word;
        }
        
        .table-wrapper table tr:nth-child(even) td {
            background: var(--primary-bg);
        }
        
        [data-theme="dark"] .table-wrapper table tr:nth-child(even) td {
            background: #1E3A5F;
        }
        
        .table-wrapper table tr:hover td {
            background: #DBEAFE;
        }
        
        [data-theme="dark"] .table-wrapper table tr:hover td {
            background: #1E40AF;
        }
        
        /* FOOTER */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: var(--section-spacing);
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* PDF MODAL */
        .pdf-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
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
        }
        
        .pdf-modal-header {
            padding: 14px 22px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
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
        
        .pdf-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 28px;
            background: var(--bg-body);
        }
        
        .pdf-content {
            max-width: 100%;
            font-size: 14px;
            background: var(--bg-card);
            padding: 24px 28px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            line-height: 1.5;
        }
        
        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .vital-grid-7 { grid-template-columns: repeat(3, 1fr); }
            .vital-grid-7-row2 { grid-template-columns: repeat(3, 1fr); }
            .bill-summary-grid { grid-template-columns: repeat(3, 1fr); }
            .grid-4 { grid-template-columns: 1fr 1fr; }
            .grid-3 { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 768px) {
            .vital-grid-7 { grid-template-columns: repeat(2, 1fr); }
            .vital-grid-7-row2 { grid-template-columns: repeat(2, 1fr); }
            .bill-summary-grid { grid-template-columns: repeat(2, 1fr); }
            .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
            .col-span-2, .col-span-3, .col-span-4 { grid-column: span 1; }
            .page-header-custom { padding: 16px 18px; }
            .page-header-custom .page-title { font-size: 1.2rem; }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<main class="main-content">

    <?php if ($error): ?>
        <div style="background:var(--danger-bg);border:2px solid var(--danger);border-radius:12px;padding:20px 24px;text-align:center;max-width:600px;margin:40px auto;">
            <i class="fas fa-exclamation-circle" style="font-size:3rem;color:var(--danger);display:block;margin-bottom:12px;"></i>
            <h3 style="font-size:1.2rem;font-weight:600;color:var(--danger);">❌ Error</h3>
            <p style="color:var(--text-secondary);margin:8px 0 16px;"><?= htmlspecialchars($error) ?></p>
            <a href="visits.php" style="background:var(--primary);color:white;padding:10px 20px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font-weight:600;">
                <i class="fas fa-arrow-left"></i> Back to Visits
            </a>
        </div>
    <?php elseif ($visit): ?>
    
    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-notes-medical"></i>
                Visit Details
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <span class="header-badge">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-calendar"></i> <?= isset($visit['visit_date']) ? date('M d, Y', strtotime($visit['visit_date'])) : 'N/A' ?>
                </span>
                <?php if ($visit['is_completed'] ?? 0): ?>
                    <span class="header-badge" style="background:rgba(255,255,255,0.25);">
                        <i class="fas fa-check-circle"></i> Completed
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="visits.php" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="generatePDF()" class="btn-header">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>

    <!-- 1. VISIT INFORMATION -->
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
                    <span class="status-badge <?= htmlspecialchars($visit['status'] ?? 'pending') ?>">
                        <?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'N/A')) ?>
                    </span>
                </p>
            </div>
            <div>
                <p class="detail-label">Visit Type</p>
                <p class="detail-value"><?= ucfirst(htmlspecialchars(str_replace('_', ' ', $visit['visit_type'] ?? 'N/A'))) ?></p>
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

    <!-- 2. PATIENT INFORMATION -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-user" style="color:var(--primary);font-size:1.2rem;"></i>
            <h3>2. Patient Information</h3>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
            <div style="width:48px;height:48px;border-radius:50%;background:var(--primary-gradient);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:1.2rem;">
                <?= strtoupper(substr($visit['patient_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div>
                <p style="font-weight:600;font-size:1.05rem;color:var(--text-primary);"><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></p>
                <p style="font-size:0.75rem;color:var(--text-secondary);">ID: <?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?></p>
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

    <!-- 3. STAFF INFORMATION -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-users" style="color:var(--primary);font-size:1.2rem;"></i>
            <h3>3. Staff Information</h3>
        </div>
        <div class="grid-2">
            <div>
                <p class="detail-label">👨‍⚕️ Doctor</p>
                <?php if ($visit['doctor_id']): ?>
                    <div style="display:flex;align-items:center;gap:10px;margin-top:6px;">
                        <?php if (!empty($visit['doctor_profile_pic'])): ?>
                            <img src="/dispensary_system/frontend/assets/uploads/profiles/<?= $visit['doctor_profile_pic'] ?>" 
                                 style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid var(--primary-light);"
                                 onerror="this.style.display='none'">
                        <?php else: ?>
                            <div style="width:44px;height:44px;border-radius:50%;background:var(--primary-gradient);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:1.1rem;">
                                <?= strtoupper(substr($visit['doctor_name'] ?? 'D', 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <p style="font-weight:600;font-size:0.95rem;">Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></p>
                            <p style="font-size:0.75rem;color:var(--text-secondary);"><?= htmlspecialchars($visit['specialty'] ?? 'General Practitioner') ?></p>
                            <?php if (!empty($visit['doctor_phone'])): ?>
                                <p style="font-size:0.7rem;color:var(--text-secondary);margin-top:2px;"><i class="fas fa-phone"></i> <?= htmlspecialchars($visit['doctor_phone']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="detail-value" style="color:var(--text-secondary);">No doctor assigned</p>
                <?php endif; ?>
            </div>
            
            <div>
                <p class="detail-label">👤 Receptionist</p>
                <?php if (!empty($visit['receptionist_name'])): ?>
                    <div style="display:flex;align-items:center;gap:10px;margin-top:6px;">
                        <div style="width:44px;height:44px;border-radius:50%;background:var(--primary-gradient);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:1.1rem;">
                            <?= strtoupper(substr($visit['receptionist_name'] ?? 'R', 0, 1)) ?>
                        </div>
                        <div>
                            <p style="font-weight:600;font-size:0.95rem;"><?= htmlspecialchars($visit['receptionist_name']) ?></p>
                            <p style="font-size:0.75rem;color:var(--text-secondary);">Receptionist</p>
                            <?php if (!empty($visit['receptionist_phone'])): ?>
                                <p style="font-size:0.7rem;color:var(--text-secondary);margin-top:2px;"><i class="fas fa-phone"></i> <?= htmlspecialchars($visit['receptionist_phone']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="detail-value" style="color:var(--text-secondary);">Not assigned</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 4. VITAL SIGNS -->
    <?php if ($vital_signs): ?>
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-heartbeat" style="color:var(--primary);font-size:1.2rem;"></i>
            <h3>4. Vital Signs (7 Measurements)</h3>
            <span class="badge-count"><?= isset($vital_signs['recorded_at']) ? date('M d, Y h:i A', strtotime($vital_signs['recorded_at'])) : 'N/A' ?></span>
        </div>
        
        <div class="vital-grid-7">
            <div class="vital-card">
                <span class="vital-icon">🌡️</span>
                <span class="vital-label">Temperature</span>
                <span class="vital-value"><?= htmlspecialchars($vital_signs['temperature'] ?? 'N/A') ?> <span class="vital-unit">°C</span></span>
            </div>
            <div class="vital-card">
                <span class="vital-icon">❤️</span>
                <span class="vital-label">Blood Pressure</span>
                <span class="vital-value">
                    <?php if (!empty($vital_signs['blood_pressure_systolic']) && !empty($vital_signs['blood_pressure_diastolic'])): ?>
                        <?= $vital_signs['blood_pressure_systolic'] ?>/<?= $vital_signs['blood_pressure_diastolic'] ?> <span class="vital-unit">mmHg</span>
                    <?php else: ?>N/A<?php endif; ?>
                </span>
            </div>
            <div class="vital-card">
                <span class="vital-icon">💓</span>
                <span class="vital-label">Pulse Rate</span>
                <span class="vital-value"><?= htmlspecialchars($vital_signs['pulse_rate'] ?? 'N/A') ?> <span class="vital-unit">bpm</span></span>
            </div>
            <div class="vital-card">
                <span class="vital-icon">⚖️</span>
                <span class="vital-label">Weight</span>
                <span class="vital-value"><?= htmlspecialchars($vital_signs['weight'] ?? 'N/A') ?> <span class="vital-unit">kg</span></span>
            </div>
        </div>
        
        <div class="vital-grid-7-row2">
            <div class="vital-card">
                <span class="vital-icon">📏</span>
                <span class="vital-label">Height</span>
                <span class="vital-value"><?= htmlspecialchars($vital_signs['height'] ?? 'N/A') ?> <span class="vital-unit">cm</span></span>
            </div>
            <div class="vital-card">
                <span class="vital-icon">📊</span>
                <span class="vital-label">BMI</span>
                <span class="vital-value"><?= htmlspecialchars($vital_signs['bmi'] ?? 'N/A') ?> <span class="vital-unit">kg/m²</span></span>
            </div>
            <div class="vital-card">
                <span class="vital-icon">🫁</span>
                <span class="vital-label">Oxygen (SpO2)</span>
                <span class="vital-value">
                    <?= $spo2_value !== null ? $spo2_value : 'N/A' ?> <span class="vital-unit">%</span>
                    <?php if ($spo2_value !== null): ?>
                        <span class="spo2-status <?= $spo2_class ?>">
                            <?php if ($spo2_class === 'normal'): ?>✅
                            <?php elseif ($spo2_class === 'low'): ?>⚠️
                            <?php else: ?>🚨<?php endif; ?>
                            <?= $spo2_label ?>
                        </span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <?php if (!empty($vital_signs['notes'])): ?>
            <div style="margin-top:10px;font-size:0.75rem;color:var(--text-secondary);padding:8px 12px;background:var(--primary-bg);border-radius:8px;border-left:3px solid var(--primary);">
                <strong>Notes:</strong> <?= htmlspecialchars($vital_signs['notes']) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 5. CLINICAL INFORMATION -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-file-medical-alt" style="color:var(--primary);font-size:1.2rem;"></i>
            <h3>5. Clinical Information</h3>
        </div>
        <div class="grid-2">
            <?php if (!empty($visit['complaint'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-exclamation-circle"></i> Chief Complaint</p>
                    <p class="detail-value" style="background:var(--primary-bg);padding:10px 14px;border-radius:var(--radius);border-left:4px solid var(--primary);margin-top:4px;">
                        <?= nl2br(htmlspecialchars($visit['complaint'])) ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['symptoms'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-list-ul"></i> Symptoms</p>
                    <p class="detail-value" style="background:var(--primary-bg);padding:10px 14px;border-radius:var(--radius);border-left:4px solid var(--primary-light);margin-top:4px;">
                        <?= nl2br(htmlspecialchars($visit['symptoms'])) ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['hpi'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-history"></i> HPI</p>
                    <p class="detail-value" style="background:var(--primary-bg);padding:10px 14px;border-radius:var(--radius);border-left:4px solid var(--primary-dark);margin-top:4px;">
                        <?= nl2br(htmlspecialchars($visit['hpi'])) ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['physical_exam'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-stethoscope"></i> Physical Examination</p>
                    <p class="detail-value" style="background:var(--primary-bg);padding:10px 14px;border-radius:var(--radius);border-left:4px solid var(--primary);margin-top:4px;">
                        <?= nl2br(htmlspecialchars($visit['physical_exam'])) ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 6. LAB TESTS -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-flask" style="color:var(--primary);font-size:1.2rem;"></i>
            <h3>6. Lab Tests</h3>
            <span class="badge-count"><?= count($lab_tests) ?></span>
        </div>
        <?php if (count($lab_tests) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Results</th>
                            <th>Technician</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $test): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong>
                                    <?php if (!empty($test['test_code'])): ?>
                                        <div style="font-size:0.65rem;color:var(--text-secondary);"><?= htmlspecialchars($test['test_code']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= isset($test['created_at']) ? date('M d, Y', strtotime($test['created_at'])) : 'N/A' ?></td>
                                <td>
                                    <span class="status-badge <?= htmlspecialchars($test['status'] ?? 'pending') ?>">
                                        <?= ucfirst($test['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($test['results'])): ?>
                                        <span style="color:var(--primary);font-weight:600;">✅ <?= htmlspecialchars(substr($test['results'], 0, 60)) ?><?= strlen($test['results']) > 60 ? '...' : '' ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($test['technician_name'])): ?>
                                        <span style="font-size:0.75rem;"><?= htmlspecialchars($test['technician_name']) ?></span>
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
            <p style="color:var(--text-secondary);text-align:center;padding:20px 0;font-style:italic;">No lab tests found for this visit</p>
        <?php endif; ?>
    </div>

    <!-- 7. DIAGNOSIS -->
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
                                <span class="status-badge assigned"><?= htmlspecialchars($visit['disease_code_full']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($visit['icd_code'])): ?>
                                <span class="status-badge pending" style="margin-left:4px;">ICD: <?= htmlspecialchars($visit['icd_code']) ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($visit['diagnosis'])): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Diagnosis Description</p>
                        <p class="detail-value" style="background:var(--primary-bg);padding:12px 16px;border-radius:var(--radius);border-left:4px solid var(--primary);margin-top:4px;">
                            <?= nl2br(htmlspecialchars($visit['diagnosis'])) ?>
                        </p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($visit['treatment'])): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Treatment</p>
                        <p class="detail-value" style="background:var(--primary-bg);padding:12px 16px;border-radius:var(--radius);border-left:4px solid var(--primary-dark);margin-top:4px;">
                            <?= nl2br(htmlspecialchars($visit['treatment'])) ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p style="color:var(--text-secondary);text-align:center;padding:20px 0;font-style:italic;">No diagnosis recorded for this visit</p>
        <?php endif; ?>
    </div>

    <!-- 8. MEDICATIONS -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-prescription" style="color:var(--primary);font-size:1.2rem;"></i>
            <h3>8. Medications</h3>
            <span class="badge-count"><?= count($prescriptions) ?> prescription(s)</span>
        </div>
        <?php if (count($prescriptions) > 0): ?>
            <?php foreach ($prescriptions as $pres): 
                $items = $prescription_items[$pres['id']] ?? [];
            ?>
                <div style="margin-bottom:12px;padding:14px;background:var(--primary-bg);border-radius:var(--radius);border:1px solid var(--primary-light);">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
                        <strong style="color:var(--primary);font-size:0.95rem;">#<?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?></strong>
                        <span class="status-badge <?= htmlspecialchars($pres['status'] ?? 'pending') ?>">
                            <?= ucfirst($pres['status'] ?? 'Pending') ?>
                        </span>
                    </div>
                    
                    <?php if (count($items) > 0): ?>
                        <div class="table-wrapper" style="background:var(--bg-card);">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Medication</th>
                                        <th>Dosage</th>
                                        <th>Frequency</th>
                                        <th>Qty</th>
                                        <th>Instructions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($item['medication_name'] ?? $item['inventory_medication_name'] ?? 'N/A') ?></strong></td>
                                            <td><?= htmlspecialchars($item['dosage'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($item['frequency'] ?? 'N/A') ?></td>
                                            <td><strong><?= $item['quantity'] ?? 0 ?></strong></td>
                                            <td style="font-size:0.75rem;"><?= htmlspecialchars($item['instructions'] ?? '—') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:var(--text-secondary);text-align:center;padding:20px 0;font-style:italic;">No medications prescribed</p>
        <?php endif; ?>
    </div>

    <!-- 9. PROCEDURES & EQUIPMENT -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-syringe" style="color:var(--primary);font-size:1.2rem;"></i>
            <h3>9. Procedures & Equipment</h3>
            <span class="badge-count"><?= count($procedures) ?> procedure(s)</span>
        </div>
        <?php if (count($procedures) > 0 || count($equipment_used) > 0): ?>
            <?php if (count($procedures) > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Procedure Name</th>
                                <th>Status</th>
                                <th>Price</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($procedures as $proc): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($proc['procedure_name'] ?? 'N/A') ?></strong></td>
                                    <td>
                                        <span class="status-badge <?= htmlspecialchars($proc['status'] ?? 'pending') ?>">
                                            <?= ucfirst($proc['status'] ?? 'Pending') ?>
                                        </span>
                                    </td>
                                    <td>TSh <?= number_format($proc['procedure_price'] ?? 0, 0) ?></td>
                                    <td><?= isset($proc['created_at']) ? date('M d, Y', strtotime($proc['created_at'])) : 'N/A' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <?php if (count($equipment_used) > 0): ?>
                <div style="margin-top:12px;">
                    <p style="font-size:0.75rem;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">
                        <i class="fas fa-tools"></i> Medical Equipment Used
                    </p>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Equipment Name</th>
                                    <th>Batch Number</th>
                                    <th>Quantity</th>
                                    <th>Unit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($equipment_used as $eq): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($eq['equipment_name'] ?? 'N/A') ?></strong></td>
                                        <td style="font-family:monospace;font-size:0.75rem;"><?= htmlspecialchars($eq['batch_number'] ?? 'N/A') ?></td>
                                        <td><strong><?= $eq['quantity'] ?? 0 ?></strong></td>
                                        <td><?= htmlspecialchars($eq['unit'] ?? 'pcs') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p style="color:var(--text-secondary);text-align:center;padding:20px 0;font-style:italic;">No procedures or equipment used</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 10. BILL SUMMARY - WITH DISCOUNT & PREMIUM (NEW)                 -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-money-bill-wave" style="color:var(--primary);font-size:1.2rem;"></i>
            <h3>10. Bill Summary</h3>
            <span class="badge-count"><?= count($bills) ?> bill(s)</span>
        </div>
        <?php if (count($bills) > 0): ?>
            <!-- BILL CARDS - 6 cards with Discount + Premium -->
            <div class="bill-summary-grid">
                <div class="bill-card total">
                    <span class="bill-icon">💰</span>
                    <div class="bill-amount">TSh <?= number_format($total_amount, 0) ?></div>
                    <div class="bill-label">Total Amount</div>
                </div>
                <div class="bill-card paid">
                    <span class="bill-icon">✅</span>
                    <div class="bill-amount">TSh <?= number_format($total_paid, 0) ?></div>
                    <div class="bill-label">Paid Amount</div>
                </div>
                <div class="bill-card balance">
                    <span class="bill-icon">⏳</span>
                    <div class="bill-amount">TSh <?= number_format($total_balance, 0) ?></div>
                    <div class="bill-label">Balance</div>
                </div>
                <!-- ✅ MPYA: Total Discount -->
                <div class="bill-card discount">
                    <span class="bill-icon">🎁</span>
                    <div class="bill-amount">TSh <?= number_format($total_discount_sum, 0) ?></div>
                    <div class="bill-label">Total Discount</div>
                </div>
                <!-- ✅ MPYA: Total Premium -->
                <div class="bill-card premium">
                    <span class="bill-icon">👑</span>
                    <div class="bill-amount">TSh <?= number_format($total_premium_sum, 0) ?></div>
                    <div class="bill-label">Premium Amount</div>
                </div>
                <div class="bill-card cancelled">
                    <span class="bill-icon">❌</span>
                    <div class="bill-amount"><?= $cancelled_bills ?></div>
                    <div class="bill-label">Cancelled</div>
                </div>
            </div>
            
            <div style="margin-top:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <span style="font-size:0.8rem;font-weight:600;">Payment Status:</span>
                <span class="status-badge <?= $total_balance <= 0 ? 'paid' : 'partial' ?>">
                    <?= $total_balance <= 0 ? '✅ Fully Paid' : '⏳ Pending' ?>
                </span>
                <?php if ($total_balance > 0): ?>
                    <span style="font-size:0.75rem;color:var(--text-secondary);">
                        Balance Due: <strong style="color:var(--danger);">TSh <?= number_format($total_balance, 0) ?></strong>
                    </span>
                <?php endif; ?>
            </div>
            
            <!-- BILL TABLE - 8 columns with Discount + Premium -->
            <div class="table-wrapper" style="margin-top:12px;">
                <table>
                    <thead>
                        <tr>
                            <th>Bill Number</th>
                            <th>Subtotal</th>
                            <th>🎁 Discount</th>
                            <th>👑 Premium</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $bill): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong></td>
                                <td>TSh <?= number_format($bill['subtotal'] ?? 0, 0) ?></td>
                                <td>
                                    <?php if (($bill['total_discount'] ?? 0) > 0): ?>
                                        <span style="color:var(--success);font-weight:600;">- TSh <?= number_format($bill['total_discount'] ?? 0, 0) ?></span>
                                        <?php if (($bill['discount_percent'] ?? 0) > 0): ?>
                                            <div style="font-size:0.65rem;color:var(--text-secondary);">(<?= number_format($bill['discount_percent'], 1) ?>%)</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($bill['premium_amount'] ?? 0) > 0): ?>
                                        <span style="color:var(--warning);font-weight:600;">+ TSh <?= number_format($bill['premium_amount'] ?? 0, 0) ?></span>
                                        <?php if (!empty($bill['premium_note'])): ?>
                                            <div style="font-size:0.6rem;color:var(--text-secondary);font-style:italic;"><?= htmlspecialchars($bill['premium_note']) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong style="color:var(--primary);">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></strong></td>
                                <td>TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                <td><strong style="color:<?= ($bill['balance'] ?? 0) > 0 ? 'var(--danger)' : 'var(--success)' ?>;">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></strong></td>
                                <td>
                                    <span class="status-badge <?= htmlspecialchars($bill['status'] ?? 'pending') ?>">
                                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color:var(--text-secondary);text-align:center;padding:20px 0;font-style:italic;">No bills found for this visit</p>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span>
            Visit Details
            <span>|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

    <?php endif; ?>

</main>

<!-- PDF MODAL -->
<div class="pdf-modal-overlay" id="pdfModal">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <div class="modal-title">
                <i class="fas fa-file-pdf"></i>
                Visit PDF Preview - <?= htmlspecialchars($visit['visit_number'] ?? 'Visit') ?>
            </div>
            <div class="modal-actions">
                <button onclick="downloadPDF()" class="btn">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="window.print()" class="btn">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="closePDFModal()" class="btn" style="background:rgba(220,38,38,0.3);">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
        <div class="pdf-modal-body">
            <div class="pdf-content" id="pdfContent"></div>
        </div>
    </div>
</div>

<script>
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

function generatePDF() {
    var modal = document.getElementById('pdfModal');
    var content = document.getElementById('pdfContent');
    
    var html = `
        <div style="text-align:center;padding-bottom:12px;border-bottom:3px solid #0B5ED7;margin-bottom:16px;">
            <img src="<?= $logo_path ?>" alt="Braick Logo" style="height:55px;object-fit:contain;" onerror="this.style.display='none'">
            <div style="font-size:1.4rem;font-weight:800;color:#0B5ED7;margin-top:6px;">BRAICK DISPENSARY</div>
            <div style="font-size:0.75rem;color:#64748B;">Tunajali Afya Yako</div>
            <div style="font-size:0.8rem;font-weight:600;color:#0B5ED7;margin-top:6px;background:#E8F0FE;padding:4px 14px;border-radius:20px;display:inline-block;">
                Visit Details Report - <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
            </div>
        </div>
        
        <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin-bottom:8px;">1. VISIT INFORMATION</div>
        <table style="width:100%;font-size:13px;margin-bottom:12px;">
            <tr>
                <td style="padding:4px;width:150px;color:#64748B;font-weight:600;">Visit Number:</td>
                <td style="padding:4px;"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></td>
                <td style="padding:4px;width:150px;color:#64748B;font-weight:600;">Status:</td>
                <td style="padding:4px;"><?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'N/A')) ?></td>
            </tr>
            <tr>
                <td style="padding:4px;color:#64748B;font-weight:600;">Visit Date:</td>
                <td style="padding:4px;"><?= isset($visit['visit_date']) ? date('F d, Y h:i A', strtotime($visit['visit_date'])) : 'N/A' ?></td>
                <td style="padding:4px;color:#64748B;font-weight:600;">Branch:</td>
                <td style="padding:4px;"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></td>
            </tr>
        </table>
        
        <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin-bottom:8px;">2. PATIENT INFORMATION</div>
        <table style="width:100%;font-size:13px;margin-bottom:12px;">
            <tr>
                <td style="padding:4px;width:150px;color:#64748B;font-weight:600;">Full Name:</td>
                <td style="padding:4px;"><strong><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></strong></td>
                <td style="padding:4px;width:150px;color:#64748B;font-weight:600;">Patient ID:</td>
                <td style="padding:4px;"><?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <td style="padding:4px;color:#64748B;font-weight:600;">Phone:</td>
                <td style="padding:4px;"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></td>
                <td style="padding:4px;color:#64748B;font-weight:600;">Gender:</td>
                <td style="padding:4px;"><?= ucfirst($visit['gender'] ?? 'N/A') ?></td>
            </tr>
        </table>
        
        <?php if ($vital_signs): ?>
        <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin-bottom:8px;">3. VITAL SIGNS (7 Measurements)</div>
        <table style="width:100%;font-size:13px;margin-bottom:12px;border-collapse:collapse;">
            <tr style="background:#E8F0FE;">
                <td style="padding:6px;text-align:center;border:1px solid #BFDBFE;"><strong style="color:#DC2626;"><?= htmlspecialchars($vital_signs['temperature'] ?? 'N/A') ?></strong><br><span style="font-size:10px;">🌡️ Temp</span></td>
                <td style="padding:6px;text-align:center;border:1px solid #BFDBFE;"><strong style="color:#0B5ED7;"><?= ($vital_signs['blood_pressure_systolic'] ?? '--') ?>/<?= ($vital_signs['blood_pressure_diastolic'] ?? '--') ?></strong><br><span style="font-size:10px;">❤️ BP</span></td>
                <td style="padding:6px;text-align:center;border:1px solid #BFDBFE;"><strong style="color:#7C3AED;"><?= htmlspecialchars($vital_signs['pulse_rate'] ?? 'N/A') ?></strong><br><span style="font-size:10px;">💓 Pulse</span></td>
                <td style="padding:6px;text-align:center;border:1px solid #BFDBFE;"><strong style="color:#D97706;"><?= htmlspecialchars($vital_signs['weight'] ?? 'N/A') ?></strong><br><span style="font-size:10px;">⚖️ Weight</span></td>
                <td style="padding:6px;text-align:center;border:1px solid #BFDBFE;"><strong style="color:#0D9488;"><?= htmlspecialchars($vital_signs['height'] ?? 'N/A') ?></strong><br><span style="font-size:10px;">📏 Height</span></td>
                <td style="padding:6px;text-align:center;border:1px solid #BFDBFE;"><strong style="color:#2563EB;"><?= htmlspecialchars($vital_signs['bmi'] ?? 'N/A') ?></strong><br><span style="font-size:10px;">📊 BMI</span></td>
                <td style="padding:6px;text-align:center;border:1px solid #BFDBFE;background:#DBEAFE;"><strong style="color:#1E40AF;"><?= $spo2_value !== null ? $spo2_value : 'N/A' ?>%</strong><br><span style="font-size:10px;">🫁 SpO2</span></td>
            </tr>
        </table>
        <?php endif; ?>
        
        <?php if (!empty($visit['diagnosis']) || !empty($visit['disease_name'])): ?>
        <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin-bottom:8px;">4. DIAGNOSIS</div>
        <div style="font-size:13px;padding:8px;background:#E8F0FE;border-left:3px solid #0B5ED7;margin-bottom:12px;">
            <strong><?= htmlspecialchars($visit['disease_name'] ?? $visit['diagnosis'] ?? 'N/A') ?></strong>
            <?php if (!empty($visit['treatment'])): ?>
                <div style="margin-top:4px;font-size:12px;color:#64748B;">Treatment: <?= htmlspecialchars($visit['treatment']) ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if (count($bills) > 0): ?>
        <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin-bottom:8px;">5. BILL SUMMARY</div>
        <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:6px;margin-bottom:8px;">
            <div style="background:#DBEAFE;padding:6px;border-radius:6px;text-align:center;border:1px solid #0B5ED7;">
                <div style="font-size:14px;font-weight:700;color:#0B5ED7;">TSh <?= number_format($total_amount, 0) ?></div>
                <div style="font-size:9px;color:#64748B;text-transform:uppercase;">💰 Total</div>
            </div>
            <div style="background:#E8F0FE;padding:6px;border-radius:6px;text-align:center;border:1px solid #1A73E8;">
                <div style="font-size:14px;font-weight:700;color:#1A73E8;">TSh <?= number_format($total_paid, 0) ?></div>
                <div style="font-size:9px;color:#64748B;text-transform:uppercase;">✅ Paid</div>
            </div>
            <div style="background:#BFDBFE;padding:6px;border-radius:6px;text-align:center;border:1px solid #0A4CA8;">
                <div style="font-size:14px;font-weight:700;color:#0A4CA8;">TSh <?= number_format($total_balance, 0) ?></div>
                <div style="font-size:9px;color:#64748B;text-transform:uppercase;">⏳ Balance</div>
            </div>
            <div style="background:#E8F0FE;padding:6px;border-radius:6px;text-align:center;border:1px solid #0B5ED7;">
                <div style="font-size:14px;font-weight:700;color:#0B5ED7;">TSh <?= number_format($total_discount_sum, 0) ?></div>
                <div style="font-size:9px;color:#64748B;text-transform:uppercase;">🎁 Discount</div>
            </div>
            <div style="background:#DBEAFE;padding:6px;border-radius:6px;text-align:center;border:1px solid #1A73E8;">
                <div style="font-size:14px;font-weight:700;color:#0A4CA8;">TSh <?= number_format($total_premium_sum, 0) ?></div>
                <div style="font-size:9px;color:#64748B;text-transform:uppercase;">👑 Premium</div>
            </div>
            <div style="background:#E2E8F0;padding:6px;border-radius:6px;text-align:center;border:1px solid #94A3B8;">
                <div style="font-size:14px;font-weight:700;color:#64748B;"><?= $cancelled_bills ?></div>
                <div style="font-size:9px;color:#64748B;text-transform:uppercase;">❌ Cancelled</div>
            </div>
        </div>
        
        <table style="width:100%;font-size:12px;margin-top:6px;border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:left;font-size:11px;">Bill #</th>
                    <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:right;font-size:11px;">Subtotal</th>
                    <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:right;font-size:11px;">🎁 Discount</th>
                    <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:right;font-size:11px;">👑 Premium</th>
                    <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:right;font-size:11px;">Total</th>
                    <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:right;font-size:11px;">Paid</th>
                    <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:right;font-size:11px;">Balance</th>
                    <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:left;font-size:11px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bills as $bill): ?>
                <tr>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;font-size:12px;"><strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:12px;">TSh <?= number_format($bill['subtotal'] ?? 0, 0) ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:12px;color:#0B5ED7;"><?= ($bill['total_discount'] ?? 0) > 0 ? '- TSh ' . number_format($bill['total_discount'], 0) : '—' ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:12px;color:#0A4CA8;"><?= ($bill['premium_amount'] ?? 0) > 0 ? '+ TSh ' . number_format($bill['premium_amount'], 0) : '—' ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:12px;color:#0B5ED7;font-weight:600;">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:12px;">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:12px;color:<?= ($bill['balance'] ?? 0) > 0 ? '#DC2626' : '#059669' ?>;font-weight:600;">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;font-size:12px;"><?= ucfirst($bill['status'] ?? 'Pending') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <div style="margin-top:20px;padding-top:12px;border-top:2px solid #E2E8F0;text-align:center;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div style="font-size:12px;color:#64748B;">Date: <?= date('F d, Y') ?></div>
                <div style="text-align:center;padding:6px 18px;border:3px solid #0B5ED7;border-radius:10px;background:#E8F0FE;">
                    <div style="font-size:9px;color:#64748B;text-transform:uppercase;letter-spacing:1px;">Official Stamp</div>
                    <div style="font-size:13px;font-weight:800;color:#0B5ED7;">BRAICK DISPENSARY</div>
                    <div style="font-size:10px;color:#64748B;">Approved By: _________________</div>
                </div>
            </div>
            <div style="font-size:11px;color:#94A3B8;margin-top:8px;">Braick Dispensary - All rights reserved</div>
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
        filename: 'Visit_<?= htmlspecialchars($visit['visit_number'] ?? 'visit') ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff' },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    
    html2pdf().set(opt).from(element).save();
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePDFModal();
});

document.getElementById('pdfModal').addEventListener('click', function(e) {
    if (e.target === this) closePDFModal();
});

console.log('%c🏥 Braick - View Visit (BLUE THEME)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Uses doctor_id + receptionist_id (no created_by)', 'font-size:12px;color:#34D399;');
console.log('%c✅ Auto-detect technician column', 'font-size:12px;color:#34D399;');
console.log('%c✅ BLUE THEME everywhere (no green headers)', 'font-size:12px;color:#34D399;');
console.log('%c✅ NEW: Discount + Premium in Bill Summary', 'font-size:12px;color:#34D399;font-weight:bold;');
</script>

</body>
</html>