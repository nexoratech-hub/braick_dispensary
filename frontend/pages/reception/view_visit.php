<?php
// ================================================================
// FILE: frontend/pages/reception/view_visit.php
// VIEW VISIT DETAILS - RECEPTION
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT RECEPTION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reception') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// GET SESSION DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Receptionist';
$branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? 'reception';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_admin = $_SESSION['is_admin'] ?? false;

$user_branch_id = $branch_id;
$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$message_type = '';

// ================================================================
// INCLUDE DATABASE - CORRECT PATH
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

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
    // GET VISIT DETAILS WITH PATIENT AND DOCTOR INFO
    // ================================================================
    if ($visit_id > 0) {
        $stmt = $db->prepare("
            SELECT 
                v.*,
                p.full_name as patient_name,
                p.patient_id as patient_number,
                p.phone as patient_phone,
                p.email as patient_email,
                p.date_of_birth,
                p.gender,
                p.address,
                u.full_name as doctor_name,
                u.specialty as doctor_specialty,
                u.is_online as doctor_online,
                b.name as branch_name,
                r.full_name as receptionist_name
            FROM visits v
            LEFT JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u ON v.doctor_id = u.id
            LEFT JOIN branches b ON v.branch_id = b.id
            LEFT JOIN users r ON v.receptionist_id = r.id
            WHERE v.id = ? AND v.branch_id = ?
        ");
        $stmt->execute([$visit_id, $user_branch_id]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$visit) {
            $message = "❌ Visit not found or you don't have permission to view it.";
            $message_type = 'error';
        }
    } else {
        $message = "❌ No visit ID provided.";
        $message_type = 'error';
    }
    
    // ================================================================
    // GET VITAL SIGNS FOR THIS VISIT (6 signs only)
    // ================================================================
    $vital_signs = null;
    if ($visit_id > 0 && $visit) {
        $stmt = $db->prepare("
            SELECT 
                temperature,
                blood_pressure_systolic,
                blood_pressure_diastolic,
                pulse_rate,
                weight,
                height,
                bmi,
                notes,
                recorded_at,
                u.full_name as recorded_by_name
            FROM vital_signs vs
            LEFT JOIN users u ON vs.recorded_by = u.id
            WHERE vs.visit_id = ? 
            ORDER BY vs.recorded_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$visit_id]);
        $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // GET PRESCRIPTIONS FOR THIS VISIT
    // ================================================================
    $prescriptions = [];
    if ($visit_id > 0 && $visit) {
        $stmt = $db->prepare("
            SELECT p.*, 
                   pi.medication_name, pi.dosage, pi.frequency, 
                   pi.quantity, pi.duration, pi.route, pi.instructions,
                   pi.unit_price, pi.total_price
            FROM prescriptions p
            LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
            WHERE p.visit_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // GET ALL BILLS FOR THIS VISIT (NOT JUST ONE)
    // ================================================================
    $bills = [];
    $bill_items = [];
    $payments = [];
    $total_bill_amount = 0;
    $total_paid_amount = 0;
    $total_balance = 0;
    $total_discount = 0;
    $all_bills_paid = true;
    $bill_status = 'pending';
    
    if ($visit_id > 0 && $visit) {
        // Get all bills for this visit
        $stmt = $db->prepare("
            SELECT * FROM patient_bills 
            WHERE visit_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get all bill items for all bills
        if (!empty($bills)) {
            $bill_ids = array_column($bills, 'id');
            $placeholders = implode(',', array_fill(0, count($bill_ids), '?'));
            
            $stmt = $db->prepare("
                SELECT * FROM bill_items 
                WHERE bill_id IN ($placeholders) 
                ORDER BY created_at DESC
            ");
            $stmt->execute($bill_ids);
            $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get all payments for all bills
            $stmt = $db->prepare("
                SELECT * FROM payments 
                WHERE bill_id IN ($placeholders) 
                ORDER BY received_at DESC
            ");
            $stmt->execute($bill_ids);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate totals
            foreach ($bills as $bill) {
                $total_bill_amount += $bill['total_amount'] ?? 0;
                $total_paid_amount += $bill['paid_amount'] ?? 0;
                $total_balance += $bill['balance'] ?? 0;
                $total_discount += $bill['discount_amount'] ?? 0;
                
                if ($bill['status'] !== 'paid') {
                    $all_bills_paid = false;
                }
            }
            
            // Determine overall bill status
            if ($all_bills_paid && $total_balance == 0) {
                $bill_status = 'paid';
            } elseif ($total_paid_amount > 0 && $total_balance > 0) {
                $bill_status = 'partial';
            } elseif ($total_paid_amount == 0 && $total_balance > 0) {
                $bill_status = 'pending';
            } else {
                $bill_status = 'pending';
            }
        }
    }
    
    // ================================================================
    // GET LAB TESTS FOR THIS VISIT
    // ================================================================
    $lab_tests = [];
    if ($visit_id > 0 && $visit) {
        $stmt = $db->prepare("
            SELECT * FROM lab_tests 
            WHERE visit_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $visit = null;
    $vital_signs = null;
    $prescriptions = [];
    $bills = [];
    $bill_items = [];
    $payments = [];
    $lab_tests = [];
    $total_bill_amount = 0;
    $total_paid_amount = 0;
    $total_balance = 0;
    $total_discount = 0;
    $bill_status = 'pending';
    $unread_notifications = 0;
}

// ================================================================
// LOGO PATH
// ================================================================
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
    <title>View Visit - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
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
            --table-stripe: #E8F0FE;
            --table-hover: #D1FAE5;
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
            --table-stripe: #1E293B;
            --table-hover: #1A3A2A;
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
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
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
        
        .detail-card {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 28px 32px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            max-width: 1200px;
            margin: 0 auto;
            box-shadow: var(--shadow-md);
        }
        
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 30px rgba(11, 94, 215, 0.08);
        }
        
        .detail-card .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .detail-card .section-title i {
            color: var(--primary);
            font-size: 1.2rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 24px;
        }
        
        .info-grid .info-item {
            display: flex;
            flex-direction: column;
            padding: 8px 0;
        }
        
        .info-grid .info-item .info-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .info-grid .info-item .info-value {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.scheduled { background: #FEF3C7; color: #D97706; }
        .status-badge.confirmed { background: #D1FAE5; color: #059669; }
        .status-badge.completed { background: #D1FAE5; color: #059669; }
        .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
        .status-badge.pending { background: #FEF3C7; color: #D97706; }
        .status-badge.paid { background: #D1FAE5; color: #059669; font-weight: 700; }
        .status-badge.partial { background: #FEF3C7; color: #D97706; }
        .status-badge.in-progress { background: #DBEAFE; color: #0B5ED7; }
        .status-badge.assigned { background: #DBEAFE; color: #0B5ED7; }
        .status-badge.with_doctor { background: #DBEAFE; color: #0B5ED7; }
        .status-badge.lab_test { background: #FEF3C7; color: #D97706; }
        .status-badge.prescribed { background: #D1FAE5; color: #059669; }
        .status-badge.dispensed { background: #D1FAE5; color: #059669; }
        
        [data-theme="dark"] .status-badge.scheduled { background: #422800; color: #F59E0B; }
        [data-theme="dark"] .status-badge.confirmed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge.completed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge.cancelled { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .status-badge.pending { background: #422800; color: #F59E0B; }
        [data-theme="dark"] .status-badge.paid { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge.partial { background: #422800; color: #F59E0B; }
        [data-theme="dark"] .status-badge.in-progress { background: #1A2A4A; color: #6EA8FE; }
        [data-theme="dark"] .status-badge.assigned { background: #1A2A4A; color: #6EA8FE; }
        [data-theme="dark"] .status-badge.with_doctor { background: #1A2A4A; color: #6EA8FE; }
        [data-theme="dark"] .status-badge.lab_test { background: #422800; color: #F59E0B; }
        [data-theme="dark"] .status-badge.prescribed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge.dispensed { background: #1A3A2A; color: #34D399; }
        
        /* ================================================================
           VITAL SIGNS - 6 ITEMS ONLY
           ================================================================ */
        .vital-signs-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        
        .vital-sign-item {
            background: var(--bg-body);
            border-radius: 10px;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .vital-sign-item:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.06);
        }
        
        .vital-sign-item .vital-label {
            font-size: 0.6rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
        }
        
        .vital-sign-item .vital-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            display: block;
            margin-top: 2px;
        }
        
        .vital-sign-item .vital-unit {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 400;
        }
        
        .vital-sign-item .vital-category {
            font-size: 0.55rem;
            font-weight: 600;
            display: block;
            margin-top: 2px;
        }
        
        /* Vital sign specific colors */
        .vital-sign-item.bp-item .vital-value {
            color: var(--primary);
        }
        
        .vital-sign-item.temp-item .vital-value {
            color: #DC2626;
        }
        
        .vital-sign-item.pulse-item .vital-value {
            color: #7C3AED;
        }
        
        .vital-sign-item.weight-item .vital-value {
            color: #D97706;
        }
        
        .vital-sign-item.bmi-item .vital-value {
            color: #059669;
        }
        
        [data-theme="dark"] .vital-sign-item.bp-item .vital-value { color: #6EA8FE; }
        [data-theme="dark"] .vital-sign-item.temp-item .vital-value { color: #F87171; }
        [data-theme="dark"] .vital-sign-item.pulse-item .vital-value { color: #A78BFA; }
        [data-theme="dark"] .vital-sign-item.weight-item .vital-value { color: #F59E0B; }
        [data-theme="dark"] .vital-sign-item.bmi-item .vital-value { color: #34D399; }
        
        .table-container {
            overflow-x: auto;
        }
        
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        .table-custom th {
            background: var(--primary);
            color: white;
            padding: 10px 14px;
            text-align: left;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .table-custom td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table-custom tr:hover td {
            background: var(--table-hover);
        }
        
        .table-custom tfoot td {
            background: var(--primary-bg);
            font-weight: 700;
        }
        
        [data-theme="dark"] .table-custom tr:hover td {
            background: #1A3A2A;
        }
        
        [data-theme="dark"] .table-custom tfoot td {
            background: #1A2A4A;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(11, 94, 215, 0.35);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
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
        
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .alert-success {
            background: var(--success-bg);
            color: var(--success-dark);
            border: 1px solid var(--success);
        }
        
        .alert-error {
            background: var(--danger-bg);
            color: var(--danger-dark);
            border: 1px solid var(--danger);
        }
        
        .alert i {
            font-size: 1.1rem;
            margin-top: 2px;
        }
        
        .alert .alert-content {
            flex: 1;
        }
        
        /* ================================================================
           BILL SUMMARY CARDS - CSS NZURI
           ================================================================ */
        .bill-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .bill-summary-card {
            background: var(--bg-body);
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .bill-summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 14px 14px 0 0;
        }
        
        .bill-summary-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .bill-summary-card .bill-summary-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .bill-summary-card .bill-summary-content {
            flex: 1;
        }
        
        .bill-summary-card .bill-summary-label {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
        }
        
        .bill-summary-card .bill-summary-value {
            font-size: 1.2rem;
            font-weight: 700;
            display: block;
            margin-top: 2px;
        }
        
        /* Total Card */
        .bill-summary-card.total-card {
            border-color: var(--primary);
        }
        
        .bill-summary-card.total-card::before {
            background: var(--primary);
        }
        
        .bill-summary-card.total-card .bill-summary-icon {
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .bill-summary-card.total-card .bill-summary-value {
            color: var(--primary);
        }
        
        /* Paid Card */
        .bill-summary-card.paid-card {
            border-color: var(--success);
        }
        
        .bill-summary-card.paid-card::before {
            background: var(--success);
        }
        
        .bill-summary-card.paid-card .bill-summary-icon {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .bill-summary-card.paid-card .bill-summary-value {
            color: var(--success);
        }
        
        /* Balance Card */
        .bill-summary-card.balance-card {
            border-color: var(--danger);
        }
        
        .bill-summary-card.balance-card::before {
            background: var(--danger);
        }
        
        .bill-summary-card.balance-card .bill-summary-icon {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        .bill-summary-card.balance-card .bill-summary-value {
            color: var(--danger);
        }
        
        .bill-summary-card.balance-card.zero-balance {
            border-color: var(--success);
        }
        
        .bill-summary-card.balance-card.zero-balance::before {
            background: var(--success);
        }
        
        .bill-summary-card.balance-card.zero-balance .bill-summary-icon {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .bill-summary-card.balance-card.zero-balance .bill-summary-value {
            color: var(--success);
        }
        
        /* Discount Card */
        .bill-summary-card.discount-card {
            border-color: #7C3AED;
        }
        
        .bill-summary-card.discount-card::before {
            background: #7C3AED;
        }
        
        .bill-summary-card.discount-card .bill-summary-icon {
            background: #EDE9FE;
            color: #7C3AED;
        }
        
        .bill-summary-card.discount-card .bill-summary-value {
            color: #7C3AED;
        }
        
        [data-theme="dark"] .bill-summary-card.discount-card .bill-summary-icon {
            background: #2D1A4A;
            color: #A78BFA;
        }
        
        [data-theme="dark"] .bill-summary-card.discount-card .bill-summary-value {
            color: #A78BFA;
        }
        
        [data-theme="dark"] .bill-summary-card.total-card .bill-summary-icon {
            background: #1A2A4A;
        }
        
        [data-theme="dark"] .bill-summary-card.paid-card .bill-summary-icon {
            background: #1A3A2A;
        }
        
        [data-theme="dark"] .bill-summary-card.balance-card .bill-summary-icon {
            background: #3A1A1A;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            margin-bottom: 16px;
        }
        
        .empty-state h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .empty-state p {
            font-size: 0.85rem;
        }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .recorded-by {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 400;
            margin-left: auto;
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .detail-card { padding: 20px; }
            .bill-summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .vital-signs-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .detail-card { padding: 14px; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .info-grid {
                grid-template-columns: 1fr;
                gap: 4px;
            }
            .vital-signs-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .bill-summary-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            .bill-summary-card {
                padding: 14px 16px;
            }
            .bill-summary-card .bill-summary-value {
                font-size: 1rem;
            }
            .bill-summary-card .bill-summary-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .detail-card { padding: 12px; }
            .vital-signs-grid {
                grid-template-columns: 1fr 1fr;
            }
            .page-header .page-title { font-size: 1rem; }
            .page-header .btn-outline-light { font-size: 0.7rem; padding: 6px 12px; }
            .bill-summary-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }
        }
    </style>
</head>
<body>

<!-- TOP NAVIGATION -->
<nav class="top-nav">
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
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= substr($full_name, 0, 1) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- MAIN CONTENT -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-medical-alt"></i>
                Visit Details
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-calendar-check"></i>
                View complete visit information for 
                <?php if ($visit): ?>
                    <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                <?php else: ?>
                    Visit #<?= $visit_id ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="appointments.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert <?= $message_type === 'success' ? 'alert-success' : 'alert-error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div class="alert-content"><?= $message ?></div>
        </div>
    <?php endif; ?>

    <?php if ($visit): ?>
    
    <!-- VISIT DETAILS -->
    <div class="detail-card animate-fade-in-up">
        <div class="section-title">
            <i class="fas fa-calendar-check"></i>
            Visit Information
            <span class="status-badge <?= $visit['status'] ?>">
                <?= ucfirst($visit['status'] ?? 'N/A') ?>
            </span>
            <?php if ($visit['is_completed'] == 1): ?>
                <span class="status-badge completed" style="font-size:0.6rem;">
                    <i class="fas fa-check-circle"></i> Completed
                </span>
            <?php endif; ?>
            <?php if ($visit['is_referred'] == 1): ?>
                <span class="status-badge" style="background:#DBEAFE;color:#0B5ED7;font-size:0.6rem;">
                    <i class="fas fa-share"></i> Referred
                </span>
            <?php endif; ?>
            <?php if ($visit['doctor_online'] == 1): ?>
                <span class="status-badge confirmed" style="font-size:0.6rem;">
                    <i class="fas fa-circle" style="color:#059669;font-size:0.4rem;"></i> Doctor Online
                </span>
            <?php endif; ?>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Visit Number</span>
                <span class="info-value"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Status</span>
                <span class="info-value">
                    <span class="status-badge <?= $visit['status'] ?>">
                        <?= ucfirst($visit['status'] ?? 'N/A') ?>
                    </span>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Date & Time</span>
                <span class="info-value">
                    <?= date('d/m/Y', strtotime($visit['visit_date'])) ?>
                    at <?= date('h:i A', strtotime($visit['visit_date'])) ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Visit Type</span>
                <span class="info-value"><?= ucfirst($visit['visit_type'] ?? 'New') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Branch</span>
                <span class="info-value"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Receptionist</span>
                <span class="info-value"><?= htmlspecialchars($visit['receptionist_name'] ?? 'N/A') ?></span>
            </div>
            <?php if ($visit['follow_up_date']): ?>
            <div class="info-item">
                <span class="info-label">Follow-up Date</span>
                <span class="info-value"><?= date('d/m/Y', strtotime($visit['follow_up_date'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($visit['completed_at']): ?>
            <div class="info-item">
                <span class="info-label">Completed At</span>
                <span class="info-value"><?= date('d/m/Y h:i A', strtotime($visit['completed_at'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($visit['symptoms']): ?>
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Symptoms</span>
                <span class="info-value"><?= htmlspecialchars($visit['symptoms']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($visit['complaint']): ?>
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Complaint</span>
                <span class="info-value"><?= htmlspecialchars($visit['complaint']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($visit['diagnosis']): ?>
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Diagnosis</span>
                <span class="info-value"><?= htmlspecialchars($visit['diagnosis']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($visit['treatment']): ?>
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Treatment</span>
                <span class="info-value"><?= htmlspecialchars($visit['treatment']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($visit['notes']): ?>
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Notes</span>
                <span class="info-value"><?= htmlspecialchars($visit['notes']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PATIENT INFORMATION -->
    <div class="detail-card animate-fade-in-up" style="margin-top:20px;">
        <div class="section-title">
            <i class="fas fa-user"></i>
            Patient Information
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Patient Name</span>
                <span class="info-value"><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Patient ID</span>
                <span class="info-value"><?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Gender</span>
                <span class="info-value"><?= ucfirst($visit['gender'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Date of Birth</span>
                <span class="info-value"><?= $visit['date_of_birth'] ? date('d/m/Y', strtotime($visit['date_of_birth'])) : 'N/A' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Phone</span>
                <span class="info-value"><?= htmlspecialchars($visit['patient_phone'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Email</span>
                <span class="info-value"><?= htmlspecialchars($visit['patient_email'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Address</span>
                <span class="info-value"><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>

    <!-- DOCTOR INFORMATION -->
    <div class="detail-card animate-fade-in-up" style="margin-top:20px;">
        <div class="section-title">
            <i class="fas fa-user-md"></i>
            Doctor Information
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Doctor Name</span>
                <span class="info-value">Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'Not Assigned') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Specialty</span>
                <span class="info-value"><?= htmlspecialchars($visit['doctor_specialty'] ?? 'General') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Status</span>
                <span class="info-value">
                    <?php if ($visit['doctor_online'] == 1): ?>
                        <span style="color:#059669;">🟢 Online</span>
                    <?php elseif ($visit['doctor_id']): ?>
                        <span style="color:#94A3B8;">⚪ Offline</span>
                    <?php else: ?>
                        <span style="color:#94A3B8;">Not Assigned</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VITAL SIGNS - 6 TU (Temperature, BP, Pulse, Weight, Height, BMI) -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="margin-top:20px;">
        <div class="section-title">
            <i class="fas fa-heartbeat"></i>
            6 Vital Signs
            <?php if ($vital_signs): ?>
                <span style="font-size:0.65rem;font-weight:400;color:var(--text-secondary);">
                    <?= date('d/m/Y h:i A', strtotime($vital_signs['recorded_at'])) ?>
                </span>
                <?php if ($vital_signs['recorded_by_name']): ?>
                    <span class="recorded-by">
                        <i class="fas fa-user-circle"></i> 
                        <?= htmlspecialchars($vital_signs['recorded_by_name']) ?>
                    </span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <?php if ($vital_signs): ?>
            <div class="vital-signs-grid">
                <!-- 1. Temperature -->
                <div class="vital-sign-item temp-item">
                    <span class="vital-label">🌡️ Temperature</span>
                    <span class="vital-value">
                        <?= $vital_signs['temperature'] ?? '--' ?> 
                        <span class="vital-unit">°C</span>
                    </span>
                </div>
                
                <!-- 2. Blood Pressure - FIXED: Shows Systolic even if Diastolic is NULL -->
                <div class="vital-sign-item bp-item">
                    <span class="vital-label">💓 Blood Pressure</span>
                    <span class="vital-value">
                        <?php 
                        $sys = $vital_signs['blood_pressure_systolic'] ?? null;
                        $dia = $vital_signs['blood_pressure_diastolic'] ?? null;
                        
                        // Check if values exist and are not empty
                        $sys_exists = ($sys !== null && $sys !== '' && $sys !== 0);
                        $dia_exists = ($dia !== null && $dia !== '' && $dia !== 0);
                        
                        if ($sys_exists && $dia_exists) {
                            echo $sys . '/' . $dia . ' <span class="vital-unit">mmHg</span>';
                        } elseif ($sys_exists) {
                            echo $sys . ' <span class="vital-unit">mmHg</span>';
                        } elseif ($dia_exists) {
                            echo $dia . ' <span class="vital-unit">mmHg</span>';
                        } else {
                            echo '--';
                        }
                        ?>
                    </span>
                </div>
                
                <!-- 3. Pulse Rate -->
                <div class="vital-sign-item pulse-item">
                    <span class="vital-label">💓 Pulse Rate</span>
                    <span class="vital-value">
                        <?= $vital_signs['pulse_rate'] ?? '--' ?> 
                        <span class="vital-unit">bpm</span>
                    </span>
                </div>
                
                <!-- 4. Weight -->
                <div class="vital-sign-item weight-item">
                    <span class="vital-label">⚖️ Weight</span>
                    <span class="vital-value">
                        <?= $vital_signs['weight'] ?? '--' ?> 
                        <span class="vital-unit">kg</span>
                    </span>
                </div>
                
                <!-- 5. Height -->
                <div class="vital-sign-item">
                    <span class="vital-label">📏 Height</span>
                    <span class="vital-value">
                        <?= $vital_signs['height'] ?? '--' ?> 
                        <span class="vital-unit">cm</span>
                    </span>
                </div>
                
                <!-- 6. BMI -->
                <div class="vital-sign-item bmi-item">
                    <span class="vital-label">📊 BMI</span>
                    <span class="vital-value">
                        <?php 
                        $bmi = $vital_signs['bmi'] ?? null;
                        if ($bmi !== null && $bmi !== '' && $bmi !== 0) {
                            echo $bmi . ' <span class="vital-unit">kg/m²</span>';
                            // BMI Category
                            if ($bmi < 16) {
                                echo '<span class="vital-category" style="color:#DC2626;">Severe Underweight</span>';
                            } elseif ($bmi < 18.5) {
                                echo '<span class="vital-category" style="color:#D97706;">Underweight</span>';
                            } elseif ($bmi < 25) {
                                echo '<span class="vital-category" style="color:#059669;">Normal</span>';
                            } elseif ($bmi < 30) {
                                echo '<span class="vital-category" style="color:#D97706;">Overweight</span>';
                            } elseif ($bmi < 35) {
                                echo '<span class="vital-category" style="color:#DC2626;">Obese Class I</span>';
                            } elseif ($bmi < 40) {
                                echo '<span class="vital-category" style="color:#DC2626;">Obese Class II</span>';
                            } else {
                                echo '<span class="vital-category" style="color:#DC2626;">Obese Class III</span>';
                            }
                        } else {
                            echo '--';
                        }
                        ?>
                    </span>
                </div>
            </div>
            
            <?php if ($vital_signs['notes']): ?>
                <div class="mt-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        <strong>Notes:</strong> <?= htmlspecialchars($vital_signs['notes']) ?>
                    </p>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-heartbeat"></i>
                <h4>No Vital Signs Recorded</h4>
                <p>Vital signs have not been recorded for this visit yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- PRESCRIPTIONS -->
    <div class="detail-card animate-fade-in-up" style="margin-top:20px;">
        <div class="section-title">
            <i class="fas fa-prescription"></i>
            Prescriptions
            <span style="font-size:0.65rem;font-weight:400;color:var(--text-secondary);">
                Total: <?= count($prescriptions) ?>
            </span>
        </div>
        
        <?php if (!empty($prescriptions)): ?>
            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Medicine</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Duration</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $index => $prescription): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($prescription['medication_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($prescription['dosage'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($prescription['frequency'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($prescription['duration'] ?? 'N/A') ?></td>
                                <td><?= $prescription['quantity'] ?? 'N/A' ?></td>
                                <td>TSh <?= number_format($prescription['total_price'] ?? 0, 2) ?></td>
                                <td>
                                    <span class="status-badge <?= $prescription['status'] ?? 'pending' ?>">
                                        <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription"></i>
                <h4>No Prescriptions</h4>
                <p>Prescriptions have not been added for this visit yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- LAB TESTS -->
    <div class="detail-card animate-fade-in-up" style="margin-top:20px;">
        <div class="section-title">
            <i class="fas fa-microscope"></i>
            Lab Tests
            <span style="font-size:0.65rem;font-weight:400;color:var(--text-secondary);">
                Total: <?= count($lab_tests) ?>
            </span>
        </div>
        
        <?php if (!empty($lab_tests)): ?>
            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Test Name</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Results</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $index => $test): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                <td>TSh <?= number_format($test['test_price'] ?? 0, 2) ?></td>
                                <td>
                                    <span class="status-badge <?= $test['status'] ?? 'pending' ?>">
                                        <?= ucfirst($test['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($test['results'] ?? '--') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-microscope"></i>
                <h4>No Lab Tests</h4>
                <p>Lab tests have not been requested for this visit yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- BILL & PAYMENTS - WITH NICE CSS CARDS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="margin-top:20px;">
        <div class="section-title">
            <i class="fas fa-money-bill-wave"></i>
            Bill & Payments
            <span style="font-size:0.65rem;font-weight:400;color:var(--text-secondary);">
                Total Bills: <?= count($bills) ?>
            </span>
            <?php if ($bill_status): ?>
                <span style="font-size:0.65rem;font-weight:400;color:var(--text-secondary);">
                    Status: 
                    <span class="status-badge <?= $bill_status ?>" style="font-size:0.65rem;">
                        <?= ucfirst($bill_status) ?>
                    </span>
                </span>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($bills)): ?>
            <!-- Bill Summary Cards - JUU -->
            <div class="bill-summary-grid">
                <!-- Total Card -->
                <div class="bill-summary-card total-card">
                    <div class="bill-summary-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="bill-summary-content">
                        <span class="bill-summary-label">Total Amount</span>
                        <span class="bill-summary-value">TSh <?= number_format($total_bill_amount, 2) ?></span>
                    </div>
                </div>
                
                <!-- Paid Card -->
                <div class="bill-summary-card paid-card">
                    <div class="bill-summary-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="bill-summary-content">
                        <span class="bill-summary-label">Paid Amount</span>
                        <span class="bill-summary-value">TSh <?= number_format($total_paid_amount, 2) ?></span>
                    </div>
                </div>
                
                <!-- Balance Card -->
                <div class="bill-summary-card balance-card <?= ($total_balance) <= 0 ? 'zero-balance' : '' ?>">
                    <div class="bill-summary-icon">
                        <i class="fas <?= ($total_balance) > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i>
                    </div>
                    <div class="bill-summary-content">
                        <span class="bill-summary-label">Balance</span>
                        <span class="bill-summary-value">TSh <?= number_format($total_balance, 2) ?></span>
                    </div>
                </div>
                
                <!-- Discount Card -->
                <div class="bill-summary-card discount-card">
                    <div class="bill-summary-icon">
                        <i class="fas fa-tag"></i>
                    </div>
                    <div class="bill-summary-content">
                        <span class="bill-summary-label">Discount</span>
                        <span class="bill-summary-value">TSh <?= number_format($total_discount, 2) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- All Bills List -->
            <div class="table-container" style="margin-top:16px;">
                <h4 style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:10px;">
                    <i class="fas fa-receipt"></i> All Bills (<?= count($bills) ?>)
                </h4>
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Bill Number</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $bill): ?>
                            <tr>
                                <td><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                                <td>TSh <?= number_format($bill['total_amount'] ?? 0, 2) ?></td>
                                <td>TSh <?= number_format($bill['paid_amount'] ?? 0, 2) ?></td>
                                <td>TSh <?= number_format($bill['balance'] ?? 0, 2) ?></td>
                                <td>
                                    <span class="status-badge <?= $bill['status'] ?? 'pending' ?>">
                                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td style="font-weight:700;">TOTAL</td>
                            <td style="font-weight:700;color:var(--primary);">
                                TSh <?= number_format($total_bill_amount, 2) ?>
                            </td>
                            <td style="font-weight:700;color:var(--success);">
                                TSh <?= number_format($total_paid_amount, 2) ?>
                            </td>
                            <td style="font-weight:700;color:<?= $total_balance > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                                TSh <?= number_format($total_balance, 2) ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $bill_status ?>">
                                    <?= ucfirst($bill_status) ?>
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Bill Items -->
            <?php if (!empty($bill_items)): ?>
                <div class="table-container" style="margin-top:16px;">
                    <h4 style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:10px;">
                        <i class="fas fa-list"></i> All Bill Items
                    </h4>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Bill</th>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $item_total = 0;
                            foreach ($bill_items as $index => $item): 
                                $item_total += $item['total_price'];
                            ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($item['bill_id'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></td>
                                    <td><?= $item['quantity'] ?? 1 ?></td>
                                    <td>TSh <?= number_format($item['unit_price'] ?? 0, 2) ?></td>
                                    <td>TSh <?= number_format($item['total_price'] ?? 0, 2) ?></td>
                                    <td>
                                        <span class="status-badge <?= $item['payment_status'] ?? 'pending' ?>">
                                            <?= ucfirst($item['payment_status'] ?? 'Pending') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" style="text-align:right;font-weight:700;">Total Items:</td>
                                <td colspan="2" style="font-weight:700;color:var(--primary);">
                                    TSh <?= number_format($item_total, 2) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- Payments -->
            <?php if (!empty($payments)): ?>
                <div class="table-container" style="margin-top:16px;">
                    <h4 style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:10px;">
                        <i class="fas fa-receipt"></i> Payments Received
                    </h4>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Bill</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_payments = 0;
                            foreach ($payments as $payment): 
                                $total_payments += $payment['amount'];
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($payment['bill_id'] ?? 'N/A') ?></td>
                                    <td>TSh <?= number_format($payment['amount'] ?? 0, 2) ?></td>
                                    <td><?= ucfirst($payment['payment_method'] ?? 'N/A') ?></td>
                                    <td><?= date('d/m/Y h:i A', strtotime($payment['received_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" style="text-align:right;font-weight:700;">Total Payments:</td>
                                <td colspan="3" style="font-weight:700;color:var(--success);">
                                    TSh <?= number_format($total_payments, 2) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-money-bill-wave"></i>
                <h4>No Bills Created</h4>
                <p>Bills have not been created for this visit yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php else: ?>
        <!-- VISIT NOT FOUND -->
        <div class="detail-card animate-fade-in-up">
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h4>Visit Not Found</h4>
                <p>The visit you are looking for could not be found or you don't have permission to view it.</p>
                <a href="appointments.php" class="btn btn-primary mt-4">
                    <i class="fas fa-arrow-left"></i> Back to Appointments
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            View Visit Details
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($full_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp"><?= date('h:i:s A') ?></span>
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
    // DATE & TIME - 12 HOUR FORMAT
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
        
        var ftEl = document.getElementById('footerTimestamp');
        if (ftEl) ftEl.textContent = 'Last updated: ' + timeStr;
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

    console.log('%c📋 Braick - View Visit Details', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#6EA8FE;');
    console.log('%c📝 Visit ID: <?= $visit_id ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Total Bills: <?= count($bills) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💵 Total Amount: TSh <?= number_format($total_bill_amount, 2) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Total Paid: TSh <?= number_format($total_paid_amount, 2) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔐 Session-based login active', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>