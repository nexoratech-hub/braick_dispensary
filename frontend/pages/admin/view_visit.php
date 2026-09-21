<?php
// ================================================================
// FILE: frontend/pages/admin/view_visit.php
// ADMIN/RECEPTION - VIEW VISIT DETAILS (V3 - PDF KAMILI)
// ✅ V3: PDF INA SECTION ZOTE (1-8)
// ✅ V3: LOGO KATI KATI + BRAICK DISPENSARY + TUNAJALI AFYA YAKO + ADMIN PHONES
// ✅ V3: Section 1 - Visit Information + Assigned By + Doctor + Date
// ✅ V3: Section 2 - Patient Information KAMILI
// ✅ V3: Section 3 - Vital Signs (7 measurements)
// ✅ V3: Section 4 - Lab Tests + Results + Technician
// ✅ V3: Section 5 - Diagnosis
// ✅ V3: Section 6 - Medications
// ✅ V3: Section 7 - Procedures & Equipment
// ✅ V3: Section 8 - Bills Zote
// ✅ V3: PRESCRIPTION = Medication_RAW - Pharmacy_Discount (415,000)
// ✅ JETBRAINS MONO font kwa namba/IDs
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
        case 'audit': header('Location: ../audit/dashboard.php'); break;
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

// ✅ V3: ROUND TO NEAREST 50
function round_to_50($value) {
    return round($value / 50) * 50;
}

try {
    $db = Database::getInstance()->getConnection();
    
    $unread_notifications = 0;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) { $unread_notifications = 0; }
    
    // ✅ V3: ADMIN PHONES - TABLE YA USERS
    $admin_phones = [];
    $admin_names = [];
    try {
        $stmt = $db->prepare("SELECT full_name, phone FROM users WHERE role = 'admin' AND status = 'active' AND phone IS NOT NULL AND phone != '' ORDER BY id LIMIT 5");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($admins as $admin) {
            $admin_phones[] = $admin['phone'];
            $admin_names[] = $admin['full_name'];
        }
    } catch (Exception $e) { $admin_phones = []; $admin_names = []; }
    
    $branch_phone = '';
    try {
        $stmt = $db->prepare("SELECT phone FROM branches WHERE id = ?");
        $stmt->execute([$branch_id]);
        $branch_phone = $stmt->fetchColumn();
    } catch (Exception $e) { $branch_phone = ''; }
    
    // ================================================================
    // FETCH VISIT - ✅ V3: Na assigned_by_name
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
               ab.full_name as assigned_by_name,
               ab.phone as assigned_by_phone,
               b.name as branch_name,
               b.phone as branch_phone,
               d2.disease_name,
               d2.disease_code as disease_code_full,
               d2.icd_code
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users d ON v.doctor_id = d.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        LEFT JOIN users ab ON v.assigned_by_id = ab.id
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
    // LAB TESTS
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
    // BILLS
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
    
    $medication_raw = 0;
    $pharmacy_discount = 0;
    $prescription_revenue = 0;
    $prescription_count = 0;
    
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
        
        // ✅ V3: Medication RAW
        try {
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(bi.total_price), 0) as medication_raw,
                       COUNT(DISTINCT bi.id) as prescription_count
                FROM bill_items bi
                INNER JOIN bills b ON bi.bill_id = b.id
                WHERE b.visit_id = ?
                AND bi.item_type = 'medication'
                AND bi.status != 'cancelled'
                AND b.status IN ('paid', 'partial')
            ");
            $stmt->execute([$visit_id]);
            $med_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $medication_raw = (float)($med_data['medication_raw'] ?? 0);
            $prescription_count = (int)($med_data['prescription_count'] ?? 0);
        } catch (Exception $e) { $medication_raw = 0; }
        
        // ✅ V3: Pharmacy Discount
        try {
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(pharmacy_discount), 0) as pharmacy_discount
                FROM bills
                WHERE visit_id = ?
                AND status IN ('paid', 'partial')
                AND pharmacy_discount > 0
            ");
            $stmt->execute([$visit_id]);
            $pharmacy_discount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['pharmacy_discount'] ?? 0);
        } catch (Exception $e) { $pharmacy_discount = 0; }
        
        $prescription_revenue = round_to_50($medication_raw - $pharmacy_discount);
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
    $medication_raw = 0; $pharmacy_discount = 0; $prescription_revenue = 0;
    $prescription_count = 0;
    $unread_notifications = 0;
    $admin_phones = [];
    $admin_names = [];
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        :root {
            --font-main: 'Inter', -apple-system, sans-serif;
            --font-mono: 'JetBrains Mono', 'Courier New', monospace;
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-darker: #083C8A;
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
            --cyan: #0891B2;
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
            --radius-xl: 18px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
            --shadow-xl: 0 20px 40px rgba(0,0,0,0.15);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --section-spacing: 12px;
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
            font-family: var(--font-main);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
            -webkit-font-smoothing: antialiased;
        }
        
        .font-mono,
        .detail-value-mono,
        .vital-value,
        .vital-unit,
        .bill-amount,
        .status-badge,
        .header-badge,
        .visit-number,
        .patient-id,
        .phone-number,
        .date-value,
        .amount-value,
        .table-wrapper td,
        .table-wrapper th,
        .badge-count,
        .stat-amount,
        .invoice-number,
        .id-display {
            font-family: var(--font-mono) !important;
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.02em;
        }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary-dark); }
        
        /* PAGE HEADER */
        .page-header-custom {
            background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083C8A 100%);
            border-radius: var(--radius-xl);
            padding: 26px 34px;
            margin-bottom: var(--section-spacing);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 10px 40px rgba(11, 94, 215, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .page-header-custom::before {
            content: '';
            position: absolute;
            top: -60%; right: -10%;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header-custom .page-title {
            color: white;
            font-size: 1.7rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin: 0;
        }
        
        .page-header-custom .page-title i {
            font-size: 2rem;
            opacity: 0.95;
            background: rgba(255,255,255,0.15);
            padding: 10px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .page-header-custom .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 8px;
        }
        
        .page-header-custom .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }
        
        .page-header-custom .btn-header {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1.5px solid rgba(255,255,255,0.25);
            padding: 10px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(10px);
            cursor: pointer;
            position: relative;
            z-index: 1;
            font-family: var(--font-mono);
        }
        
        .page-header-custom .btn-header:hover {
            background: rgba(255,255,255,0.28);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        
        /* DETAIL CARDS */
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            padding: 22px 26px;
            border: 2px solid var(--border-color);
            transition: all 0.35s ease;
            margin-bottom: var(--section-spacing);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .detail-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--primary-gradient);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .detail-card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .detail-card:hover::before { opacity: 1; }
        
        .card-title-section {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 2px dashed var(--border-color);
            flex-wrap: wrap;
        }
        
        .card-title-section .card-title-icon {
            width: 42px; height: 42px;
            border-radius: 12px;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
            transition: all 0.3s ease;
        }
        
        .detail-card:hover .card-title-icon {
            transform: scale(1.08) rotate(-3deg);
        }
        
        .card-title-section h3 {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0;
        }
        
        .card-title-section .badge-count {
            background: var(--primary-bg);
            color: var(--primary);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 700;
            margin-left: auto;
            border: 1px solid var(--primary-light);
        }
        
        .detail-label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 4px;
        }
        
        .detail-value {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
            word-break: break-word;
        }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px 24px; }
        .grid-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px 24px; }
        .col-span-2 { grid-column: span 2; }
        .col-span-3 { grid-column: span 3; }
        .col-span-4 { grid-column: span 4; }
        
        /* STATUS BADGES */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 4px 14px;
            border-radius: 20px;
            text-transform: capitalize;
            font-family: var(--font-mono);
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        
        .status-badge.pending { background: linear-gradient(135deg, #DBEAFE, #BFDBFE); color: #1E40AF; }
        .status-badge.assigned { background: linear-gradient(135deg, #E8F0FE, #DBEAFE); color: #0B5ED7; }
        .status-badge.with_doctor { background: linear-gradient(135deg, #BFDBFE, #93C5FD); color: #1E40AF; }
        .status-badge.completed { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; }
        .status-badge.cancelled { background: linear-gradient(135deg, #94A3B8, #64748B); color: white; }
        .status-badge.paid { background: linear-gradient(135deg, #0B5ED7, #083C8A); color: white; }
        .status-badge.partial { background: linear-gradient(135deg, #6EA8FE, #3B82F6); color: white; }
        .status-badge.in_progress { background: linear-gradient(135deg, #93C5FD, #6EA8FE); color: #0A4CA8; }
        .status-badge.lab_completed { background: linear-gradient(135deg, #1A73E8, #0B5ED7); color: white; }
        .status-badge.dispensed { background: linear-gradient(135deg, #0A4CA8, #083C8A); color: white; }
        .status-badge.confirmed { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; }
        
        [data-theme="dark"] .status-badge.pending { background: linear-gradient(135deg, #1E3A5F, #1E40AF); color: #93C5FD; }
        [data-theme="dark"] .status-badge.completed { background: linear-gradient(135deg, #1A73E8, #0B5ED7); color: white; }
        
        /* VITAL SIGNS */
        .vital-grid-7 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        
        .vital-grid-7-row2 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 12px;
        }
        
        .vital-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 16px 12px;
            text-align: center;
            border: 2px solid var(--border-color);
            transition: all 0.35s ease;
            position: relative;
            overflow: hidden;
        }
        
        .vital-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--primary-gradient);
            transition: height 0.3s ease;
        }
        
        .vital-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }
        
        .vital-card:hover::before { height: 6px; }
        
        .vital-card .vital-icon {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 6px;
            transition: transform 0.3s ease;
        }
        
        .vital-card:hover .vital-icon { transform: scale(1.15) rotate(-5deg); }
        
        .vital-card .vital-label {
            font-size: 0.55rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            display: block;
        }
        
        .vital-card .vital-value {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--primary);
            margin-top: 4px;
            font-family: var(--font-mono);
        }
        
        .vital-card .vital-unit {
            font-size: 0.55rem;
            color: var(--text-secondary);
            font-weight: 600;
            font-family: var(--font-mono);
        }
        
        /* TABLE */
        .table-wrapper {
            overflow-x: auto;
            margin-top: 10px;
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        
        .table-wrapper table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            font-family: var(--font-mono);
        }
        
        .table-wrapper table th {
            background: var(--primary-gradient);
            color: white;
            padding: 12px 14px;
            text-align: left;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            font-family: var(--font-mono);
        }
        
        .table-wrapper table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            word-wrap: break-word;
            font-family: var(--font-mono);
        }
        
        .table-wrapper table tr:nth-child(even) td { background: var(--primary-bg); }
        
        [data-theme="dark"] .table-wrapper table tr:nth-child(even) td { background: #1E3A5F; }
        
        .table-wrapper table tr:hover td { background: #DBEAFE; }
        
        [data-theme="dark"] .table-wrapper table tr:hover td { background: #1E40AF; }
        
        /* BILL CARDS */
        .bill-summary-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
        }
        
        .bill-card {
            background: var(--primary-bg);
            border-radius: var(--radius-lg);
            padding: 18px 14px;
            text-align: center;
            border: 2px solid var(--primary-light);
            transition: all 0.35s ease;
            position: relative;
            overflow: hidden;
        }
        
        .bill-card:hover {
            transform: translateY(-6px) scale(1.02);
            box-shadow: var(--shadow-lg);
        }
        
        .bill-card .bill-icon {
            font-size: 1.8rem;
            display: block;
            margin-bottom: 4px;
            transition: transform 0.3s ease;
        }
        
        .bill-card:hover .bill-icon { transform: scale(1.15) rotate(-5deg); }
        
        .bill-card .bill-amount {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--primary);
            font-family: var(--font-mono);
        }
        
        .bill-card .bill-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.05em;
            margin-top: 4px;
            font-family: var(--font-mono);
        }
        
        .bill-card.total { border-color: #0B5ED7; background: linear-gradient(135deg, #DBEAFE, #BFDBFE); }
        .bill-card.total .bill-amount { color: #0B5ED7; font-size: 1.25rem; }
        .bill-card.paid { border-color: #1A73E8; background: linear-gradient(135deg, #E8F0FE, #DBEAFE); }
        .bill-card.balance { border-color: #0A4CA8; background: linear-gradient(135deg, #BFDBFE, #93C5FD); }
        .bill-card.discount { border-color: #D97706; background: linear-gradient(135deg, #FEF3C7, #FDE68A); }
        .bill-card.discount .bill-amount { color: #D97706; }
        .bill-card.premium { border-color: #7C3AED; background: linear-gradient(135deg, #EDE9FE, #DDD6FE); }
        .bill-card.premium .bill-amount { color: #7C3AED; }
        .bill-card.cancelled { border-color: #94A3B8; background: linear-gradient(135deg, #F1F5F9, #E2E8F0); }
        .bill-card.cancelled .bill-amount { color: #64748B; }
        
        /* FORMULA BREAKDOWN */
        .formula-breakdown {
            background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
            border: 2px solid #BFDBFE;
            border-radius: var(--radius-lg);
            padding: 16px 22px;
            margin-bottom: var(--section-spacing);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }
        
        .formula-breakdown-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            color: #0B5ED7;
            font-size: 0.9rem;
            font-family: var(--font-mono);
        }
        
        .formula-breakdown-title i {
            width: 36px; height: 36px;
            background: #0B5ED7;
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .formula-stats { display: flex; gap: 12px; flex-wrap: wrap; }
        
        .formula-stat {
            text-align: center;
            padding: 10px 18px;
            background: white;
            border-radius: 12px;
            border: 2px solid #BFDBFE;
            min-width: 140px;
        }
        
        .formula-stat.raw { border-color: #0B5ED7; }
        .formula-stat.discount { border-color: #FCD34D; background: #FFFBEB; }
        .formula-stat.total { border-color: #7C3AED; background: #F5F3FF; }
        
        .formula-stat-label {
            font-size: 0.55rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748B;
            letter-spacing: 0.05em;
            font-family: var(--font-mono);
            display: block;
            margin-bottom: 4px;
        }
        
        .formula-stat-value {
            font-size: 1.05rem;
            font-weight: 800;
            font-family: var(--font-mono);
        }
        
        .formula-stat.raw .formula-stat-value { color: #0B5ED7; }
        .formula-stat.discount .formula-stat-value { color: #D97706; }
        .formula-stat.total .formula-stat-value { color: #7C3AED; }
        
        /* FOOTER */
        .footer {
            padding: 16px 0;
            border-top: 2px solid var(--border-color);
            margin-top: var(--section-spacing);
            text-align: center;
            font-size: 0.72rem;
            color: var(--text-secondary);
            font-family: var(--font-mono);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 700; }
        
        /* PDF MODAL */
        .pdf-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            backdrop-filter: blur(6px);
            justify-content: center;
            align-items: center;
        }
        
        .pdf-modal-overlay.active { display: flex; }
        
        .pdf-modal {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            width: 95%;
            max-width: 1100px;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-xl);
            animation: modalFadeIn 0.3s ease;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .pdf-modal-header {
            padding: 16px 24px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            background: var(--primary-gradient);
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
        }
        
        .pdf-modal-header .modal-title {
            font-size: 1rem;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: var(--font-mono);
        }
        
        .pdf-modal-header .modal-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        
        .pdf-modal-header .modal-actions .btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: var(--font-mono);
        }
        
        .pdf-modal-header .modal-actions .btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .pdf-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 24px 30px;
            background: var(--bg-body);
        }
        
        .pdf-content {
            max-width: 100%;
            font-size: 14px;
            background: var(--bg-card);
            padding: 28px 32px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            line-height: 1.6;
            font-family: var(--font-mono);
        }
        
        /* ANIMATIONS */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
        
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
            .page-header-custom { padding: 18px 20px; }
            .page-header-custom .page-title { font-size: 1.2rem; }
            .page-header-custom .page-title i { font-size: 1.4rem; padding: 8px; }
            .detail-card { padding: 16px 18px; }
            .formula-breakdown { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <?php if ($error): ?>
        <div style="background:var(--danger-bg);border:2px solid var(--danger);border-radius:var(--radius-xl);padding:30px 24px;text-align:center;max-width:600px;margin:40px auto;">
            <i class="fas fa-exclamation-circle" style="font-size:3.5rem;color:var(--danger);display:block;margin-bottom:16px;"></i>
            <h3 style="font-size:1.3rem;font-weight:800;color:var(--danger);margin-bottom:8px;">❌ Error</h3>
            <p style="color:var(--text-secondary);margin:8px 0 20px;font-family:var(--font-mono);"><?= htmlspecialchars($error) ?></p>
            <a href="visits.php" style="background:var(--primary-gradient);color:white;padding:12px 24px;border-radius:10px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-weight:700;font-family:var(--font-mono);">
                <i class="fas fa-arrow-left"></i> Back to Visits
            </a>
        </div>
    <?php elseif ($visit): ?>
    
    <!-- PAGE HEADER -->
    <div class="page-header-custom animate-fade-in-up">
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
                    <span class="header-badge" style="background:rgba(52,211,153,0.25);border-color:rgba(52,211,153,0.4);color:#A7F3D0;">
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
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="card-title-section">
            <div class="card-title-icon"><i class="fas fa-info-circle"></i></div>
            <h3>1. Visit Information</h3>
        </div>
        <div class="grid-3">
            <div>
                <p class="detail-label">Visit Number</p>
                <p class="detail-value font-mono"><strong><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></strong></p>
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
                <p class="detail-value font-mono"><?= isset($visit['visit_date']) ? date('F d, Y h:i A', strtotime($visit['visit_date'])) : 'N/A' ?></p>
            </div>
            <div>
                <p class="detail-label">Branch</p>
                <p class="detail-value"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Consultation Fee</p>
                <p class="detail-value font-mono">TSh <?= number_format($visit['consultation_fee'] ?? 0, 0) ?></p>
            </div>
            <div>
                <p class="detail-label">👨‍⚕️ Doctor</p>
                <p class="detail-value"><strong>Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></strong></p>
            </div>
            <div>
                <p class="detail-label">👤 Receptionist</p>
                <p class="detail-value"><?= htmlspecialchars($visit['receptionist_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">📋 Assigned By</p>
                <p class="detail-value"><?= htmlspecialchars($visit['assigned_by_name'] ?? $visit['receptionist_name'] ?? 'N/A') ?></p>
            </div>
            <?php if (!empty($visit['follow_up_date'])): ?>
                <div class="col-span-3">
                    <p class="detail-label">Follow-up Date</p>
                    <p class="detail-value font-mono"><?= date('F d, Y', strtotime($visit['follow_up_date'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. PATIENT INFORMATION -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="card-title-section">
            <div class="card-title-icon"><i class="fas fa-user"></i></div>
            <h3>2. Patient Information</h3>
        </div>
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:16px;padding:14px;background:linear-gradient(135deg, #EFF6FF, #DBEAFE);border-radius:var(--radius-lg);border:2px solid #BFDBFE;">
            <div style="width:56px;height:56px;border-radius:50%;background:var(--primary-gradient);display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:1.4rem;box-shadow:0 4px 12px rgba(11,94,215,0.3);font-family:var(--font-mono);">
                <?= strtoupper(substr($visit['patient_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div>
                <p style="font-weight:800;font-size:1.1rem;color:var(--text-primary);"><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></p>
                <p style="font-size:0.78rem;color:var(--text-secondary);font-family:var(--font-mono);">
                    <i class="fas fa-id-card"></i> ID: <?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?>
                </p>
            </div>
        </div>
        <div class="grid-3">
            <div>
                <p class="detail-label">Phone</p>
                <p class="detail-value font-mono"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Email</p>
                <p class="detail-value"><?= htmlspecialchars($visit['email'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Emergency Contact</p>
                <p class="detail-value font-mono"><?= htmlspecialchars($visit['emergency_contact'] ?? 'N/A') ?></p>
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
                <p class="detail-value font-mono"><?= !empty($visit['date_of_birth']) ? date('F d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?></p>
            </div>
            <div>
                <p class="detail-label">Blood Group</p>
                <p class="detail-value font-mono"><strong style="color:var(--danger);"><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></strong></p>
            </div>
            <div class="col-span-2">
                <p class="detail-label">Address</p>
                <p class="detail-value"><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></p>
            </div>
            <?php if (!empty($visit['allergies'])): ?>
                <div class="col-span-3">
                    <p class="detail-label">Allergies</p>
                    <p class="detail-value" style="color:var(--danger);padding:8px 12px;background:var(--danger-bg);border-radius:8px;border-left:4px solid var(--danger);">
                        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($visit['allergies']) ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. VITAL SIGNS -->
    <?php if ($vital_signs): ?>
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="card-title-section">
            <div class="card-title-icon"><i class="fas fa-heartbeat"></i></div>
            <h3>3. Vital Signs (7 Measurements)</h3>
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
                </span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 4. LAB TESTS -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="card-title-section">
            <div class="card-title-icon"><i class="fas fa-flask"></i></div>
            <h3>4. Lab Tests</h3>
            <span class="badge-count"><?= count($lab_tests) ?></span>
        </div>
        <?php if (count($lab_tests) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Test Name</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Results</th>
                            <th>Technician</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $ln = 1; foreach ($lab_tests as $test): ?>
                            <tr>
                                <td><?= $ln++ ?></td>
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
                                        <span style="color:var(--success);font-weight:700;">✅ <?= htmlspecialchars(substr($test['results'], 0, 80)) ?><?= strlen($test['results']) > 80 ? '...' : '' ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($test['technician_name'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color:var(--text-secondary);text-align:center;padding:24px 0;font-style:italic;">No lab tests found</p>
        <?php endif; ?>
    </div>

    <!-- 5. DIAGNOSIS -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="card-title-section">
            <div class="card-title-icon"><i class="fas fa-stethoscope"></i></div>
            <h3>5. Diagnosis</h3>
        </div>
        <?php if (!empty($visit['diagnosis']) || !empty($visit['disease_name']) || !empty($visit['treatment'])): ?>
            <div class="grid-2">
                <?php if (!empty($visit['disease_name'])): ?>
                    <div>
                        <p class="detail-label">Disease Name</p>
                        <p class="detail-value" style="font-size:1.1rem;font-weight:800;color:var(--primary);"><?= htmlspecialchars($visit['disease_name']) ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($visit['disease_code_full']) || !empty($visit['icd_code'])): ?>
                    <div>
                        <p class="detail-label">Disease Code</p>
                        <p class="detail-value">
                            <?php if (!empty($visit['disease_code_full'])): ?>
                                <span class="status-badge assigned"><?= htmlspecialchars($visit['disease_code_full']) ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($visit['diagnosis'])): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Diagnosis Description</p>
                        <p class="detail-value" style="background:linear-gradient(135deg, #EFF6FF, #DBEAFE);padding:14px 18px;border-radius:var(--radius);border-left:4px solid var(--primary);margin-top:6px;">
                            <?= nl2br(htmlspecialchars($visit['diagnosis'])) ?>
                        </p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($visit['treatment'])): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Treatment</p>
                        <p class="detail-value" style="background:linear-gradient(135deg, #EFF6FF, #DBEAFE);padding:14px 18px;border-radius:var(--radius);border-left:4px solid var(--primary-dark);margin-top:6px;">
                            <?= nl2br(htmlspecialchars($visit['treatment'])) ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p style="color:var(--text-secondary);text-align:center;padding:24px 0;font-style:italic;">No diagnosis recorded</p>
        <?php endif; ?>
    </div>

    <!-- 6. MEDICATIONS -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.3s;">
        <div class="card-title-section">
            <div class="card-title-icon"><i class="fas fa-prescription"></i></div>
            <h3>6. Medications</h3>
            <span class="badge-count"><?= count($prescriptions) ?> prescription(s)</span>
        </div>
        <?php if (count($prescriptions) > 0): ?>
            <?php foreach ($prescriptions as $pres): 
                $items = $prescription_items[$pres['id']] ?? [];
            ?>
                <div style="margin-bottom:14px;padding:16px;background:linear-gradient(135deg, #EFF6FF, #DBEAFE);border-radius:var(--radius-lg);border:2px solid #BFDBFE;">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                        <strong style="color:var(--primary);font-size:1rem;font-family:var(--font-mono);">#<?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?></strong>
                        <span class="status-badge <?= htmlspecialchars($pres['status'] ?? 'pending') ?>">
                            <?= ucfirst($pres['status'] ?? 'Pending') ?>
                        </span>
                    </div>
                    <?php if (count($items) > 0): ?>
                        <div class="table-wrapper" style="background:var(--bg-card);">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Medication</th>
                                        <th>Dosage</th>
                                        <th>Frequency</th>
                                        <th>Qty</th>
                                        <th>Instructions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $mn = 1; foreach ($items as $item): ?>
                                        <tr>
                                            <td><?= $mn++ ?></td>
                                            <td><strong><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></strong></td>
                                            <td><?= htmlspecialchars($item['dosage'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($item['frequency'] ?? 'N/A') ?></td>
                                            <td><strong style="color:var(--primary);"><?= $item['quantity'] ?? 0 ?></strong></td>
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
            <p style="color:var(--text-secondary);text-align:center;padding:24px 0;font-style:italic;">No medications prescribed</p>
        <?php endif; ?>
    </div>

    <!-- 7. PROCEDURES & EQUIPMENT -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.35s;">
        <div class="card-title-section">
            <div class="card-title-icon"><i class="fas fa-syringe"></i></div>
            <h3>7. Procedures & Equipment</h3>
            <span class="badge-count"><?= count($procedures) ?> procedure(s)</span>
        </div>
        <?php if (count($procedures) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Procedure Name</th>
                            <th>Status</th>
                            <th>Price</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $pn = 1; foreach ($procedures as $proc): ?>
                            <tr>
                                <td><?= $pn++ ?></td>
                                <td><strong><?= htmlspecialchars($proc['procedure_name'] ?? 'N/A') ?></strong></td>
                                <td><span class="status-badge <?= htmlspecialchars($proc['status'] ?? 'pending') ?>"><?= ucfirst($proc['status'] ?? 'Pending') ?></span></td>
                                <td style="font-weight:700;color:var(--primary);">TSh <?= number_format($proc['procedure_price'] ?? 0, 0) ?></td>
                                <td><?= isset($proc['created_at']) ? date('M d, Y', strtotime($proc['created_at'])) : 'N/A' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <?php if (count($equipment_used) > 0): ?>
            <div style="margin-top:16px;">
                <p style="font-size:0.75rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px;font-family:var(--font-mono);">
                    <i class="fas fa-tools"></i> Equipment Used (<?= count($equipment_used) ?>)
                </p>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Equipment Name</th>
                                <th>Batch Number</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $en = 1; foreach ($equipment_used as $eq): ?>
                                <tr>
                                    <td><?= $en++ ?></td>
                                    <td><strong><?= htmlspecialchars($eq['equipment_name'] ?? 'N/A') ?></strong></td>
                                    <td style="font-family:var(--font-mono);font-size:0.75rem;"><?= htmlspecialchars($eq['batch_number'] ?? 'N/A') ?></td>
                                    <td><strong><?= $eq['quantity'] ?? 0 ?></strong></td>
                                    <td><?= htmlspecialchars($eq['unit'] ?? 'pcs') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (count($procedures) == 0 && count($equipment_used) == 0): ?>
            <p style="color:var(--text-secondary);text-align:center;padding:24px 0;font-style:italic;">No procedures or equipment used</p>
        <?php endif; ?>
    </div>

    <!-- ✅ V3: PRESCRIPTION FORMULA BREAKDOWN -->
    <?php if ($medication_raw > 0 || $pharmacy_discount > 0): ?>
    <div class="formula-breakdown animate-fade-in-up" style="animation-delay:0.38s;">
        <div class="formula-breakdown-title">
            <i class="fas fa-calculator"></i>
            Prescription Revenue Breakdown
        </div>
        <div class="formula-stats">
            <div class="formula-stat raw">
                <span class="formula-stat-label">💊 Medication RAW</span>
                <span class="formula-stat-value">TSh <?= number_format($medication_raw, 0) ?></span>
            </div>
            <div class="formula-stat discount">
                <span class="formula-stat-label">🏷️ Pharmacy Discount</span>
                <span class="formula-stat-value">- TSh <?= number_format($pharmacy_discount, 0) ?></span>
            </div>
            <div class="formula-stat total">
                <span class="formula-stat-label">💊 Prescription Revenue</span>
                <span class="formula-stat-value">TSh <?= number_format($prescription_revenue, 0) ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 8. BILL SUMMARY -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.4s;">
        <div class="card-title-section">
            <div class="card-title-icon"><i class="fas fa-money-bill-wave"></i></div>
            <h3>8. Bill Summary</h3>
            <span class="badge-count"><?= count($bills) ?> bill(s)</span>
        </div>
        <?php if (count($bills) > 0): ?>
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
                <div class="bill-card discount">
                    <span class="bill-icon">🎁</span>
                    <div class="bill-amount">TSh <?= number_format($total_discount_sum, 0) ?></div>
                    <div class="bill-label">Total Discount</div>
                </div>
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
            
            <div class="table-wrapper" style="margin-top:16px;">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
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
                        <?php $bn = 1; foreach ($bills as $bill): ?>
                            <tr>
                                <td><?= $bn++ ?></td>
                                <td><strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong></td>
                                <td>TSh <?= number_format($bill['subtotal'] ?? 0, 0) ?></td>
                                <td>
                                    <?php if (($bill['total_discount'] ?? 0) > 0): ?>
                                        <span style="color:var(--warning);font-weight:700;">- TSh <?= number_format($bill['total_discount'] ?? 0, 0) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($bill['premium_amount'] ?? 0) > 0): ?>
                                        <span style="color:var(--purple);font-weight:700;">+ TSh <?= number_format($bill['premium_amount'] ?? 0, 0) ?></span>
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
            <p style="color:var(--text-secondary);text-align:center;padding:24px 0;font-style:italic;">No bills found</p>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            Visit Details
            <span style="margin:0 8px;">|</span>
            <span id="footerTime" class="font-mono"><?= date('H:i:s') ?></span>
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
        <!-- ✅ V3: LOGO KATI KATI + ADMIN PHONES -->
        <div style="text-align:center;padding-bottom:16px;border-bottom:3px solid #0B5ED7;margin-bottom:20px;">
            <img src="<?= $logo_path ?>" alt="Braick Logo" style="height:80px;object-fit:contain;display:block;margin:0 auto 10px;" onerror="this.style.display='none'">
            <div style="font-size:1.6rem;font-weight:900;color:#0B5ED7;font-family:'JetBrains Mono',monospace;letter-spacing:1px;">BRAICK DISPENSARY</div>
            <div style="font-size:0.85rem;color:#64748B;font-style:italic;margin-top:4px;">Tunajali Afya Yako</div>
            <div style="font-size:0.75rem;color:#0B5ED7;font-weight:700;margin-top:8px;font-family:'JetBrains Mono',monospace;">
                📞 <?= implode(' | ', array_map('htmlspecialchars', $admin_phones)) ?>
            </div>
            <div style="font-size:0.85rem;font-weight:800;color:#0B5ED7;margin-top:12px;background:#E8F0FE;padding:6px 18px;border-radius:20px;display:inline-block;font-family:'JetBrains Mono',monospace;">
                VISIT DETAILS REPORT - <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
            </div>
        </div>
        
        <!-- 1. VISIT INFORMATION -->
        <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin-bottom:10px;font-family:'JetBrains Mono',monospace;">1. VISIT INFORMATION</div>
        <table style="width:100%;font-size:13px;margin-bottom:14px;font-family:'JetBrains Mono',monospace;">
            <tr>
                <td style="padding:5px;width:150px;color:#64748B;font-weight:600;">Visit Number:</td>
                <td style="padding:5px;"><strong><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></strong></td>
                <td style="padding:5px;width:150px;color:#64748B;font-weight:600;">Status:</td>
                <td style="padding:5px;"><?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'N/A')) ?></td>
            </tr>
            <tr>
                <td style="padding:5px;color:#64748B;font-weight:600;">Visit Date:</td>
                <td style="padding:5px;"><?= isset($visit['visit_date']) ? date('F d, Y h:i A', strtotime($visit['visit_date'])) : 'N/A' ?></td>
                <td style="padding:5px;color:#64748B;font-weight:600;">Branch:</td>
                <td style="padding:5px;"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <td style="padding:5px;color:#64748B;font-weight:600;">Visit Type:</td>
                <td style="padding:5px;"><?= ucfirst(htmlspecialchars(str_replace('_', ' ', $visit['visit_type'] ?? 'N/A'))) ?></td>
                <td style="padding:5px;color:#64748B;font-weight:600;">Consultation Fee:</td>
                <td style="padding:5px;">TSh <?= number_format($visit['consultation_fee'] ?? 0, 0) ?></td>
            </tr>
            <tr>
                <td style="padding:5px;color:#64748B;font-weight:600;">👨‍⚕️ Doctor:</td>
                <td style="padding:5px;"><strong>Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></strong></td>
                <td style="padding:5px;color:#64748B;font-weight:600;">👤 Receptionist:</td>
                <td style="padding:5px;"><?= htmlspecialchars($visit['receptionist_name'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <td style="padding:5px;color:#64748B;font-weight:600;">📋 Assigned By:</td>
                <td style="padding:5px;"><?= htmlspecialchars($visit['assigned_by_name'] ?? $visit['receptionist_name'] ?? 'N/A') ?></td>
                <td style="padding:5px;color:#64748B;font-weight:600;">Follow-up:</td>
                <td style="padding:5px;"><?= !empty($visit['follow_up_date']) ? date('F d, Y', strtotime($visit['follow_up_date'])) : 'N/A' ?></td>
            </tr>
        </table>
        
        <!-- 2. PATIENT INFORMATION -->
        <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin-bottom:10px;font-family:'JetBrains Mono',monospace;">2. PATIENT INFORMATION</div>
        <table style="width:100%;font-size:13px;margin-bottom:14px;font-family:'JetBrains Mono',monospace;">
            <tr>
                <td style="padding:5px;width:150px;color:#64748B;font-weight:600;">Full Name:</td>
                <td style="padding:5px;"><strong><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></strong></td>
                <td style="padding:5px;width:150px;color:#64748B;font-weight:600;">Patient ID:</td>
                <td style="padding:5px;"><?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <td style="padding:5px;color:#64748B;font-weight:600;">Phone:</td>
                <td style="padding:5px;"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></td>
                <td style="padding:5px;color:#64748B;font-weight:600;">Gender:</td>
                <td style="padding:5px;"><?= ucfirst($visit['gender'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <td style="padding:5px;color:#64748B;font-weight:600;">Email:</td>
                <td style="padding:5px;"><?= htmlspecialchars($visit['email'] ?? 'N/A') ?></td>
                <td style="padding:5px;color:#64748B;font-weight:600;">Date of Birth:</td>
                <td style="padding:5px;"><?= !empty($visit['date_of_birth']) ? date('F d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?></td>
            </tr>
            <tr>
                <td style="padding:5px;color:#64748B;font-weight:600;">Blood Group:</td>
                <td style="padding:5px;"><strong style="color:#DC2626;"><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></strong></td>
                <td style="padding:5px;color:#64748B;font-weight:600;">Marital Status:</td>
                <td style="padding:5px;"><?= htmlspecialchars($visit['marital_status'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <td style="padding:5px;color:#64748B;font-weight:600;">Emergency Contact:</td>
                <td style="padding:5px;"><?= htmlspecialchars($visit['emergency_contact'] ?? 'N/A') ?></td>
                <td style="padding:5px;color:#64748B;font-weight:600;">Allergies:</td>
                <td style="padding:5px;color:#DC2626;"><?= htmlspecialchars($visit['allergies'] ?? 'None') ?></td>
            </tr>
            <tr>
                <td style="padding:5px;color:#64748B;font-weight:600;">Address:</td>
                <td style="padding:5px;" colspan="3"><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></td>
            </tr>
        </table>
        
        <?php if ($vital_signs): ?>
        <!-- 3. VITAL SIGNS -->
        <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin-bottom:10px;font-family:'JetBrains Mono',monospace;">3. VITAL SIGNS (7 Measurements)</div>
        <table style="width:100%;font-size:13px;margin-bottom:14px;border-collapse:collapse;font-family:'JetBrains Mono',monospace;">
            <tr style="background:#E8F0FE;">
                <td style="padding:8px;text-align:center;border:1px solid #BFDBFE;"><strong style="color:#DC2626;"><?= htmlspecialchars($vital_signs['temperature'] ?? 'N/A') ?>°C</strong><br><span style="font-size:10px;">🌡️ Temp</span></td>
                <td style="padding:8px;text-align:center;border:1px solid #BFDBFE;"><strong style="color:#0B5ED7;"><?= ($vital_signs['blood_pressure_systolic'] ?? '--') ?>/<?= ($vital_signs['blood_pressure_diastolic'] ?? '--') ?></strong><br><span style="font-size:10px;">❤️ BP mmHg</span></td>
                <td style="padding:8px;text-align:center;border:1px solid #BFDBFE;"><strong style="color:#7C3AED;"><?= htmlspecialchars($vital_signs['pulse_rate'] ?? 'N/A') ?></strong><br><span style="font-size:10px;">💓 Pulse bpm</span></td>
                <td style="padding:8px;text-align:center;border:1px solid #BFDBFE;"><strong style="color:#D97706;"><?= htmlspecialchars($vital_signs['weight'] ?? 'N/A') ?></strong><br><span style="font-size:10px;">⚖️ Weight kg</span></td>
                <td style="padding:8px;text-align:center;border:1px solid #BFDBFE;"><strong style="color:#0D9488;"><?= htmlspecialchars($vital_signs['height'] ?? 'N/A') ?></strong><br><span style="font-size:10px;">📏 Height cm</span></td>
                <td style="padding:8px;text-align:center;border:1px solid #BFDBFE;"><strong style="color:#2563EB;"><?= htmlspecialchars($vital_signs['bmi'] ?? 'N/A') ?></strong><br><span style="font-size:10px;">📊 BMI</span></td>
                <td style="padding:8px;text-align:center;border:1px solid #BFDBFE;background:#DBEAFE;"><strong style="color:#1E40AF;"><?= $spo2_value !== null ? $spo2_value : 'N/A' ?>%</strong><br><span style="font-size:10px;">🫁 SpO2</span></td>
            </tr>
        </table>
        <?php endif; ?>
        
        <?php if (count($lab_tests) > 0): ?>
        <!-- 4. LAB TESTS -->
        <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin-bottom:10px;font-family:'JetBrains Mono',monospace;">4. LAB TESTS (<?= count($lab_tests) ?>)</div>
        <table style="width:100%;font-size:12px;margin-bottom:14px;border-collapse:collapse;font-family:'JetBrains Mono',monospace;">
            <thead>
                <tr>
                    <th style="background:#0B5ED7;color:white;padding:6px 8px;text-align:left;font-size:11px;">#</th>
                    <th style="background:#0B5ED7;color:white;padding:6px 8px;text-align:left;font-size:11px;">Test Name</th>
                    <th style="background:#0B5ED7;color:white;padding:6px 8px;text-align:left;font-size:11px;">Date</th>
                    <th style="background:#0B5ED7;color:white;padding:6px 8px;text-align:left;font-size:11px;">Status</th>
                    <th style="background:#0B5ED7;color:white;padding:6px 8px;text-align:left;font-size:11px;">Results</th>
                    <th style="background:#0B5ED7;color:white;padding:6px 8px;text-align:left;font-size:11px;">Technician</th>
                </tr>
            </thead>
            <tbody>
                <?php $ln = 1; foreach ($lab_tests as $test): ?>
                <tr>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><?= $ln++ ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><?= isset($test['created_at']) ? date('M d, Y', strtotime($test['created_at'])) : 'N/A' ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><?= ucfirst($test['status'] ?? 'Pending') ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;color:#059669;"><?= htmlspecialchars($test['results'] ?? 'Pending') ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><?= htmlspecialchars($test['technician_name'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <!-- 5. DIAGNOSIS -->
        <?php if (!empty($visit['diagnosis']) || !empty($visit['disease_name'])): ?>
        <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin-bottom:10px;font-family:'JetBrains Mono',monospace;">5. DIAGNOSIS</div>
        <div style="font-size:13px;padding:10px 14px;background:#E8F0FE;border-left:4px solid #0B5ED7;margin-bottom:14px;border-radius:6px;font-family:'JetBrains Mono',monospace;">
            <strong style="color:#0B5ED7;"><?= htmlspecialchars($visit['disease_name'] ?? $visit['diagnosis'] ?? 'N/A') ?></strong>
            <?php if (!empty($visit['diagnosis']) && !empty($visit['disease_name'])): ?>
                <div style="margin-top:4px;font-size:12px;color:#64748B;"><?= htmlspecialchars($visit['diagnosis']) ?></div>
            <?php endif; ?>
            <?php if (!empty($visit['treatment'])): ?>
                <div style="margin-top:6px;font-size:12px;color:#64748B;"><strong>Treatment:</strong> <?= htmlspecialchars($visit['treatment']) ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- 6. MEDICATIONS -->
        <?php if (count($prescriptions) > 0): ?>
        <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin-bottom:10px;font-family:'JetBrains Mono',monospace;">6. MEDICATIONS (<?= count($prescriptions) ?> prescription)</div>
        <?php foreach ($prescriptions as $pres): 
            $items = $prescription_items[$pres['id']] ?? [];
        ?>
            <div style="margin-bottom:8px;font-size:12px;font-weight:700;color:#0B5ED7;font-family:'JetBrains Mono',monospace;">
                #<?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?> — <?= ucfirst($pres['status'] ?? 'Pending') ?>
            </div>
            <?php if (count($items) > 0): ?>
            <table style="width:100%;font-size:12px;margin-bottom:14px;border-collapse:collapse;font-family:'JetBrains Mono',monospace;">
                <thead>
                    <tr>
                        <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:left;font-size:11px;">#</th>
                        <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:left;font-size:11px;">Medication</th>
                        <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:left;font-size:11px;">Dosage</th>
                        <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:left;font-size:11px;">Frequency</th>
                        <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:left;font-size:11px;">Qty</th>
                        <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:left;font-size:11px;">Instructions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $mn = 1; foreach ($items as $item): ?>
                    <tr>
                        <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><?= $mn++ ?></td>
                        <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><strong><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></strong></td>
                        <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><?= htmlspecialchars($item['dosage'] ?? '—') ?></td>
                        <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><?= htmlspecialchars($item['frequency'] ?? '—') ?></td>
                        <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><strong style="color:#0B5ED7;"><?= $item['quantity'] ?? 0 ?></strong></td>
                        <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><?= htmlspecialchars($item['instructions'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- 7. PROCEDURES & EQUIPMENT -->
        <?php if (count($procedures) > 0 || count($equipment_used) > 0): ?>
        <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin-bottom:10px;font-family:'JetBrains Mono',monospace;">7. PROCEDURES & EQUIPMENT</div>
        
        <?php if (count($procedures) > 0): ?>
        <div style="font-size:12px;font-weight:700;color:#0B5ED7;margin-bottom:6px;font-family:'JetBrains Mono',monospace;">Procedures (<?= count($procedures) ?>):</div>
        <table style="width:100%;font-size:12px;margin-bottom:10px;border-collapse:collapse;font-family:'JetBrains Mono',monospace;">
            <thead>
                <tr>
                    <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:left;font-size:11px;">#</th>
                    <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:left;font-size:11px;">Procedure Name</th>
                    <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:left;font-size:11px;">Status</th>
                    <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:right;font-size:11px;">Price</th>
                    <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:left;font-size:11px;">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php $pn = 1; foreach ($procedures as $proc): ?>
                <tr>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><?= $pn++ ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><strong><?= htmlspecialchars($proc['procedure_name'] ?? 'N/A') ?></strong></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><?= ucfirst($proc['status'] ?? 'Pending') ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;text-align:right;color:#0B5ED7;font-weight:700;">TSh <?= number_format($proc['procedure_price'] ?? 0, 0) ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><?= isset($proc['created_at']) ? date('M d, Y', strtotime($proc['created_at'])) : 'N/A' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <?php if (count($equipment_used) > 0): ?>
        <div style="font-size:12px;font-weight:700;color:#0B5ED7;margin-bottom:6px;font-family:'JetBrains Mono',monospace;">Equipment Used (<?= count($equipment_used) ?>):</div>
        <table style="width:100%;font-size:12px;margin-bottom:14px;border-collapse:collapse;font-family:'JetBrains Mono',monospace;">
            <thead>
                <tr>
                    <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:left;font-size:11px;">#</th>
                    <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:left;font-size:11px;">Equipment Name</th>
                    <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:left;font-size:11px;">Batch Number</th>
                    <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:right;font-size:11px;">Quantity</th>
                    <th style="background:#0B5ED7;color:white;padding:5px 8px;text-align:left;font-size:11px;">Unit</th>
                </tr>
            </thead>
            <tbody>
                <?php $en = 1; foreach ($equipment_used as $eq): ?>
                <tr>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><?= $en++ ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><strong><?= htmlspecialchars($eq['equipment_name'] ?? 'N/A') ?></strong></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><?= htmlspecialchars($eq['batch_number'] ?? '—') ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;text-align:right;font-weight:700;"><?= $eq['quantity'] ?? 0 ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><?= htmlspecialchars($eq['unit'] ?? 'pcs') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php endif; ?>
        
        <!-- PRESCRIPTION REVENUE BREAKDOWN -->
        <?php if ($medication_raw > 0 || $pharmacy_discount > 0): ?>
        <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin-bottom:10px;font-family:'JetBrains Mono',monospace;">PRESCRIPTION REVENUE BREAKDOWN</div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px;">
            <div style="background:#E8F0FE;padding:10px;border-radius:8px;text-align:center;border:2px solid #0B5ED7;font-family:'JetBrains Mono',monospace;">
                <div style="font-size:16px;font-weight:800;color:#0B5ED7;">TSh <?= number_format($medication_raw, 0) ?></div>
                <div style="font-size:10px;color:#64748B;text-transform:uppercase;">💊 Medication RAW</div>
            </div>
            <div style="background:#FFFBEB;padding:10px;border-radius:8px;text-align:center;border:2px solid #FCD34D;font-family:'JetBrains Mono',monospace;">
                <div style="font-size:16px;font-weight:800;color:#D97706;">- TSh <?= number_format($pharmacy_discount, 0) ?></div>
                <div style="font-size:10px;color:#64748B;text-transform:uppercase;">🏷️ Pharmacy Discount</div>
            </div>
            <div style="background:#F5F3FF;padding:10px;border-radius:8px;text-align:center;border:2px solid #7C3AED;font-family:'JetBrains Mono',monospace;">
                <div style="font-size:16px;font-weight:800;color:#7C3AED;">TSh <?= number_format($prescription_revenue, 0) ?></div>
                <div style="font-size:10px;color:#64748B;text-transform:uppercase;">💊 Prescription Revenue</div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- 8. BILL SUMMARY -->
        <?php if (count($bills) > 0): ?>
        <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin-bottom:10px;font-family:'JetBrains Mono',monospace;">8. BILL SUMMARY (<?= count($bills) ?>)</div>
        <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:8px;margin-bottom:12px;">
            <div style="background:#DBEAFE;padding:8px;border-radius:8px;text-align:center;border:2px solid #0B5ED7;font-family:'JetBrains Mono',monospace;">
                <div style="font-size:14px;font-weight:800;color:#0B5ED7;">TSh <?= number_format($total_amount, 0) ?></div>
                <div style="font-size:9px;color:#64748B;text-transform:uppercase;">💰 Total</div>
            </div>
            <div style="background:#E8F0FE;padding:8px;border-radius:8px;text-align:center;border:2px solid #1A73E8;font-family:'JetBrains Mono',monospace;">
                <div style="font-size:14px;font-weight:800;color:#1A73E8;">TSh <?= number_format($total_paid, 0) ?></div>
                <div style="font-size:9px;color:#64748B;text-transform:uppercase;">✅ Paid</div>
            </div>
            <div style="background:#BFDBFE;padding:8px;border-radius:8px;text-align:center;border:2px solid #0A4CA8;font-family:'JetBrains Mono',monospace;">
                <div style="font-size:14px;font-weight:800;color:#0A4CA8;">TSh <?= number_format($total_balance, 0) ?></div>
                <div style="font-size:9px;color:#64748B;text-transform:uppercase;">⏳ Balance</div>
            </div>
            <div style="background:#FEF3C7;padding:8px;border-radius:8px;text-align:center;border:2px solid #D97706;font-family:'JetBrains Mono',monospace;">
                <div style="font-size:14px;font-weight:800;color:#D97706;">TSh <?= number_format($total_discount_sum, 0) ?></div>
                <div style="font-size:9px;color:#64748B;text-transform:uppercase;">🎁 Discount</div>
            </div>
            <div style="background:#EDE9FE;padding:8px;border-radius:8px;text-align:center;border:2px solid #7C3AED;font-family:'JetBrains Mono',monospace;">
                <div style="font-size:14px;font-weight:800;color:#7C3AED;">TSh <?= number_format($total_premium_sum, 0) ?></div>
                <div style="font-size:9px;color:#64748B;text-transform:uppercase;">👑 Premium</div>
            </div>
            <div style="background:#E2E8F0;padding:8px;border-radius:8px;text-align:center;border:2px solid #94A3B8;font-family:'JetBrains Mono',monospace;">
                <div style="font-size:14px;font-weight:800;color:#64748B;"><?= $cancelled_bills ?></div>
                <div style="font-size:9px;color:#64748B;text-transform:uppercase;">❌ Cancelled</div>
            </div>
        </div>
        
        <table style="width:100%;font-size:12px;margin-top:8px;border-collapse:collapse;font-family:'JetBrains Mono',monospace;">
            <thead>
                <tr>
                    <th style="background:#0B5ED7;color:white;padding:6px 8px;text-align:left;font-size:11px;">#</th>
                    <th style="background:#0B5ED7;color:white;padding:6px 8px;text-align:left;font-size:11px;">Bill #</th>
                    <th style="background:#0B5ED7;color:white;padding:6px 8px;text-align:right;font-size:11px;">Subtotal</th>
                    <th style="background:#0B5ED7;color:white;padding:6px 8px;text-align:right;font-size:11px;">🎁 Discount</th>
                    <th style="background:#0B5ED7;color:white;padding:6px 8px;text-align:right;font-size:11px;">👑 Premium</th>
                    <th style="background:#0B5ED7;color:white;padding:6px 8px;text-align:right;font-size:11px;">Total</th>
                    <th style="background:#0B5ED7;color:white;padding:6px 8px;text-align:right;font-size:11px;">Paid</th>
                    <th style="background:#0B5ED7;color:white;padding:6px 8px;text-align:right;font-size:11px;">Balance</th>
                    <th style="background:#0B5ED7;color:white;padding:6px 8px;text-align:left;font-size:11px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php $bn = 1; foreach ($bills as $bill): ?>
                <tr>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><?= $bn++ ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;text-align:right;">TSh <?= number_format($bill['subtotal'] ?? 0, 0) ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;text-align:right;color:#D97706;"><?= ($bill['total_discount'] ?? 0) > 0 ? '- TSh ' . number_format($bill['total_discount'], 0) : '—' ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;text-align:right;color:#7C3AED;"><?= ($bill['premium_amount'] ?? 0) > 0 ? '+ TSh ' . number_format($bill['premium_amount'], 0) : '—' ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;text-align:right;color:#0B5ED7;font-weight:700;">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;text-align:right;">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;text-align:right;color:<?= ($bill['balance'] ?? 0) > 0 ? '#DC2626' : '#059669' ?>;font-weight:700;">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></td>
                    <td style="padding:5px 8px;border-bottom:1px solid #E2E8F0;"><?= ucfirst($bill['status'] ?? 'Pending') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <!-- FOOTER -->
        <div style="margin-top:24px;padding-top:16px;border-top:2px solid #E2E8F0;text-align:center;font-family:'JetBrains Mono',monospace;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
                <div style="font-size:12px;color:#64748B;">Date: <?= date('F d, Y') ?></div>
                <div style="text-align:center;padding:10px 22px;border:3px solid #0B5ED7;border-radius:12px;background:#E8F0FE;">
                    <div style="font-size:10px;color:#64748B;text-transform:uppercase;letter-spacing:1px;">Official Stamp</div>
                    <div style="font-size:14px;font-weight:800;color:#0B5ED7;">BRAICK DISPENSARY</div>
                    <div style="font-size:11px;color:#64748B;">Approved By: _________________</div>
                </div>
            </div>
            <div style="font-size:11px;color:#94A3B8;margin-top:10px;">Braick Dispensary - All rights reserved</div>
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

console.log('%c🏥 Braick - View Visit V3 KAMILI', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ V3: PDF INA SECTION ZOTE (1-8)', 'font-size:13px;color:#059669;font-weight:bold;');
console.log('%c✅ V3: LOGO KATI KATI + BRAICK DISPENSARY + TUNAJALI AFYA YAKO', 'font-size:13px;color:#059669;');
console.log('%c✅ V3: ADMIN PHONES kutoka users table', 'font-size:13px;color:#059669;');
console.log('%c✅ V3: Assigned By + Doctor + Date', 'font-size:13px;color:#059669;');
console.log('%c✅ V3: Lab Tests + Results + Technician', 'font-size:13px;color:#059669;');
console.log('%c✅ V3: Medication + Procedures + Equipment + Bills', 'font-size:13px;color:#059669;');
</script>

</body>
</html>