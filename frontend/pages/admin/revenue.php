<?php
// ================================================================
// FILE: frontend/pages/admin/revenue.php
// SUPER ADMIN - REVENUE REPORT PAGE - FIXED
// BRAICK DISPENSARY - USING EXISTING DATABASE TABLES
// FIXED: No undefined variables
// FIXED: Revenue = Patient Bills (paid_amount) + OTC + Prescriptions
// FIXED: Excludes OTC bills from bills table
// FIXED: Prescription revenue from prescription_items table
// FIXED: Header displays correctly with date/time
// WITH FULL JAVASCRIPT
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
// CHECK IF USER IS ADMIN
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
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

if ($selected_branch_id !== 'all' && !is_numeric($selected_branch_id)) {
    $selected_branch_id = 'all';
}

$branch_name_display = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([(int)$selected_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name_display = $branch_data['name'];
    }
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
// BRANCH FILTER FOR QUERIES
// ================================================================
$branch_filter = "";
$branch_params = [];

if ($selected_branch_id !== 'all') {
    $branch_filter = " AND branch_id = ?";
    $branch_params[] = (int)$selected_branch_id;
}

// For bills table with different column name
$branch_filter_b = "";
$branch_params_b = [];
if ($selected_branch_id !== 'all') {
    $branch_filter_b = " AND b.branch_id = ?";
    $branch_params_b[] = (int)$selected_branch_id;
}

// For prescriptions table
$branch_filter_p = "";
$branch_params_p = [];
if ($selected_branch_id !== 'all') {
    $branch_filter_p = " AND p.branch_id = ?";
    $branch_params_p[] = (int)$selected_branch_id;
}

// ================================================================
// ✅ 1. PATIENT BILLS REVENUE - Using paid_amount, excludes OTC bills
// ================================================================
$patient_bills_revenue = 0;
$patient_bills_count = 0;
try {
    $sql = "
        SELECT COALESCE(SUM(b.paid_amount), 0) as total, COUNT(*) as count 
        FROM bills b
        WHERE b.status = 'paid'
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
    ";
    if (!empty($branch_filter_b)) {
        $sql .= $branch_filter_b;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params_b);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_bills_revenue = $data['total'] ?? 0;
    $patient_bills_count = $data['count'] ?? 0;
} catch (Exception $e) {
    $patient_bills_revenue = 0;
    $patient_bills_count = 0;
}

// ================================================================
// ✅ 2. OTC REVENUE - from otc_sales table
// ================================================================
$otc_revenue = 0;
$otc_count = 0;
try {
    $sql = "
        SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count 
        FROM otc_sales 
        WHERE payment_status = 'paid'
    ";
    if (!empty($branch_filter)) {
        $sql .= $branch_filter;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $otc_revenue = $data['total'] ?? 0;
    $otc_count = $data['count'] ?? 0;
} catch (Exception $e) {
    $otc_revenue = 0;
    $otc_count = 0;
}

// ================================================================
// ✅ 3. PRESCRIPTION REVENUE - from prescription_items table
// ================================================================
$prescription_revenue = 0;
$prescription_count = 0;
try {
    $sql = "
        SELECT COALESCE(SUM(pi.total_price), 0) as total, COUNT(DISTINCT pi.id) as count 
        FROM prescription_items pi
        INNER JOIN prescriptions p ON pi.prescription_id = p.id
        WHERE p.status = 'dispensed'
    ";
    if (!empty($branch_filter_p)) {
        $sql .= $branch_filter_p;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params_p);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $prescription_revenue = $data['total'] ?? 0;
    $prescription_count = $data['count'] ?? 0;
} catch (Exception $e) {
    $prescription_revenue = 0;
    $prescription_count = 0;
}

// ================================================================
// ✅ 4. CONSULTATION REVENUE - from bill_items (paid bills only)
// ================================================================
$consultation_revenue = 0;
$consultation_count = 0;
try {
    $sql = "
        SELECT COALESCE(SUM(bi.final_price), 0) as total, COUNT(DISTINCT bi.id) as count 
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE b.status = 'paid'
        AND bi.item_type = 'consultation'
        AND bi.status = 'paid'
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
    ";
    if (!empty($branch_filter_b)) {
        $sql .= $branch_filter_b;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params_b);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $consultation_revenue = $data['total'] ?? 0;
    $consultation_count = $data['count'] ?? 0;
} catch (Exception $e) {
    $consultation_revenue = 0;
    $consultation_count = 0;
}

// ================================================================
// ✅ 5. LAB TEST REVENUE - from bill_items (paid bills only)
// ================================================================
$lab_revenue = 0;
$lab_count = 0;
try {
    $sql = "
        SELECT COALESCE(SUM(bi.final_price), 0) as total, COUNT(DISTINCT bi.id) as count 
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE b.status = 'paid'
        AND bi.item_type = 'lab_test'
        AND bi.status = 'paid'
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
    ";
    if (!empty($branch_filter_b)) {
        $sql .= $branch_filter_b;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params_b);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $lab_revenue = $data['total'] ?? 0;
    $lab_count = $data['count'] ?? 0;
} catch (Exception $e) {
    $lab_revenue = 0;
    $lab_count = 0;
}

// ================================================================
// ✅ 6. PROCEDURES REVENUE - from bill_items (paid bills only)
// ================================================================
$procedure_revenue = 0;
$procedure_count = 0;
try {
    $sql = "
        SELECT COALESCE(SUM(bi.final_price), 0) as total, COUNT(DISTINCT bi.id) as count 
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE b.status = 'paid'
        AND bi.item_type = 'procedure'
        AND bi.status = 'paid'
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
    ";
    if (!empty($branch_filter_b)) {
        $sql .= $branch_filter_b;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params_b);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $procedure_revenue = $data['total'] ?? 0;
    $procedure_count = $data['count'] ?? 0;
} catch (Exception $e) {
    $procedure_revenue = 0;
    $procedure_count = 0;
}

// ================================================================
// ✅ 7. MEDICATION REVENUE - from bill_items (paid bills only)
// ================================================================
$medication_revenue = 0;
$medication_count = 0;
try {
    $sql = "
        SELECT COALESCE(SUM(bi.final_price), 0) as total, COUNT(DISTINCT bi.id) as count 
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE b.status = 'paid'
        AND bi.item_type = 'medication'
        AND bi.status = 'paid'
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
    ";
    if (!empty($branch_filter_b)) {
        $sql .= $branch_filter_b;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params_b);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $medication_revenue = $data['total'] ?? 0;
    $medication_count = $data['count'] ?? 0;
} catch (Exception $e) {
    $medication_revenue = 0;
    $medication_count = 0;
}

// ================================================================
// ✅ 8. REGISTRATION REVENUE - from bill_items (paid bills only)
// ================================================================
$registration_revenue = 0;
$registration_count = 0;
try {
    $sql = "
        SELECT COALESCE(SUM(bi.final_price), 0) as total, COUNT(DISTINCT bi.id) as count 
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE b.status = 'paid'
        AND bi.item_type = 'registration'
        AND bi.status = 'paid'
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
    ";
    if (!empty($branch_filter_b)) {
        $sql .= $branch_filter_b;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params_b);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $registration_revenue = $data['total'] ?? 0;
    $registration_count = $data['count'] ?? 0;
} catch (Exception $e) {
    $registration_revenue = 0;
    $registration_count = 0;
}

// ================================================================
// ✅ 9. TOTAL REVENUE = Patient Bills + OTC + Prescriptions
// ================================================================
$total_revenue = $patient_bills_revenue + $otc_revenue + $prescription_revenue;

// Total transactions = paid bills + OTC transactions + prescriptions
$total_transactions = $patient_bills_count + $otc_count + $prescription_count;

// ================================================================
// ✅ 10. EXPENSES
// ================================================================
$total_expenses = 0;
try {
    $sql = "
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM expenses 
        WHERE status = 'paid'
    ";
    if (!empty($branch_filter)) {
        $sql .= $branch_filter;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_expenses = $data['total'] ?? 0;
} catch (Exception $e) {
    $total_expenses = 0;
}

// ================================================================
// ✅ 11. NET PROFIT
// ================================================================
$net_profit = $total_revenue - $total_expenses;
$profit_percentage = ($total_revenue > 0) ? round(($net_profit / $total_revenue) * 100, 1) : 0;

// ================================================================
// GET MONTHLY REVENUE DATA (Last 12 months)
// ================================================================
$monthly_labels = [];
$monthly_patient = [];
$monthly_otc = [];
$monthly_prescription = [];
$monthly_total = [];

for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_label = date('M Y', strtotime("-$i months"));
    $monthly_labels[] = $month_label;
    
    $params = [$month];
    $params_b = [$month];
    $params_p = [$month];
    
    if ($selected_branch_id !== 'all') {
        $params[] = (int)$selected_branch_id;
        $params_b[] = (int)$selected_branch_id;
        $params_p[] = (int)$selected_branch_id;
    }
    
    // Patient Bills
    $sql = "
        SELECT COALESCE(SUM(b.paid_amount), 0) as total 
        FROM bills b
        WHERE b.status = 'paid'
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
        AND DATE_FORMAT(b.created_at, '%Y-%m') = ?
    ";
    if ($selected_branch_id !== 'all') {
        $sql .= " AND b.branch_id = ?";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params_b);
    $patient_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $monthly_patient[] = (float)$patient_total;
    
    // OTC
    $sql = "
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM otc_sales 
        WHERE payment_status = 'paid'
        AND DATE_FORMAT(created_at, '%Y-%m') = ?
    ";
    if ($selected_branch_id !== 'all') {
        $sql .= " AND branch_id = ?";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $otc_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $monthly_otc[] = (float)$otc_total;
    
    // Prescriptions
    $sql = "
        SELECT COALESCE(SUM(pi.total_price), 0) as total 
        FROM prescription_items pi
        INNER JOIN prescriptions p ON pi.prescription_id = p.id
        WHERE p.status = 'dispensed'
        AND DATE_FORMAT(p.created_at, '%Y-%m') = ?
    ";
    if ($selected_branch_id !== 'all') {
        $sql .= " AND p.branch_id = ?";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params_p);
    $pres_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $monthly_prescription[] = (float)$pres_total;
    
    $monthly_total[] = (float)($patient_total + $otc_total + $pres_total);
}

// ================================================================
// GET DAILY REVENUE (Last 30 days)
// ================================================================
$daily_labels = [];
$daily_patient = [];
$daily_otc = [];
$daily_prescription = [];
$daily_total = [];

for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_labels[] = date('d M', strtotime($date));
    
    $params = [$date];
    $params_b = [$date];
    $params_p = [$date];
    
    if ($selected_branch_id !== 'all') {
        $params[] = (int)$selected_branch_id;
        $params_b[] = (int)$selected_branch_id;
        $params_p[] = (int)$selected_branch_id;
    }
    
    // Patient Bills
    $sql = "
        SELECT COALESCE(SUM(b.paid_amount), 0) as total 
        FROM bills b
        WHERE b.status = 'paid'
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
        AND DATE(b.created_at) = ?
    ";
    if ($selected_branch_id !== 'all') {
        $sql .= " AND b.branch_id = ?";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params_b);
    $patient_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $daily_patient[] = (float)$patient_total;
    
    // OTC
    $sql = "
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM otc_sales 
        WHERE payment_status = 'paid'
        AND DATE(created_at) = ?
    ";
    if ($selected_branch_id !== 'all') {
        $sql .= " AND branch_id = ?";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $otc_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $daily_otc[] = (float)$otc_total;
    
    // Prescriptions
    $sql = "
        SELECT COALESCE(SUM(pi.total_price), 0) as total 
        FROM prescription_items pi
        INNER JOIN prescriptions p ON pi.prescription_id = p.id
        WHERE p.status = 'dispensed'
        AND DATE(p.created_at) = ?
    ";
    if ($selected_branch_id !== 'all') {
        $sql .= " AND p.branch_id = ?";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params_p);
    $pres_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $daily_prescription[] = (float)$pres_total;
    
    $daily_total[] = (float)($patient_total + $otc_total + $pres_total);
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
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// FORMAT CURRENCY
// ================================================================
function formatCurrency($amount) {
    return 'TSh ' . number_format($amount, 0);
}

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
    <title>Revenue Report - Braick Dispensary</title>
    
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
            
            --teal: #0D9488;
            --teal-bg: #ECFDF5;
            
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
        
        /* ================================================================
           TOP NAV - OVERRIDE FOR HEADER
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
            border-radius: 10px;
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
            border-radius: 0 10px 10px 0;
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
           MAIN CONTENT - Override shared header margin
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            transition: background 0.3s ease;
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
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
           STATS CARDS - REVENUE CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            border-radius: var(--radius);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
            color: white;
            position: relative;
            overflow: hidden;
            min-height: 90px;
            text-decoration: none;
            cursor: default;
            border: none;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }
        
        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.1);
            position: relative;
            z-index: 1;
        }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.8);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
            position: relative;
            z-index: 1;
        }
        
        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin: 0;
            line-height: 1.2;
            position: relative;
            z-index: 1;
        }
        
        .stat-card .stat-sub {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.6);
            margin-top: 2px;
            position: relative;
            z-index: 1;
        }
        
        /* Card Colors */
        .stat-card.card-total { background: var(--primary-gradient); }
        .stat-card.card-consultation { background: #059669; }
        .stat-card.card-lab { background: #7C3AED; }
        .stat-card.card-medication { background: #D97706; }
        .stat-card.card-procedure { background: #0D9488; }
        .stat-card.card-otc { background: #0891B2; }
        .stat-card.card-prescription { background: #7C3AED; }
        .stat-card.card-expenses { background: #DC2626; }
        .stat-card.card-profit { background: #059669; }
        
        /* ================================================================
           FILTER BAR
           ================================================================ */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            align-items: center;
            background: var(--bg-card);
            padding: 16px 20px;
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        
        .filter-bar .filter-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .filter-bar select {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 8px 14px;
            font-size: 0.8rem;
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
            min-width: 200px;
        }
        
        .filter-bar select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
        }
        
        .filter-bar .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
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
        
        /* ================================================================
           CHART CARDS
           ================================================================ */
        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .chart-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }
        
        .chart-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--primary-light);
        }
        
        .chart-card .chart-header {
            padding: 14px 20px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .chart-card .chart-header .chart-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .chart-card .chart-header .chart-title i {
            color: var(--primary);
        }
        
        .chart-card .chart-header .chart-total {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .chart-card .chart-body {
            padding: 16px 20px;
            height: 220px;
            position: relative;
        }
        
        /* ================================================================
           REVENUE BREAKDOWN TABLE
           ================================================================ */
        .table-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .table-card .table-header {
            padding: 14px 20px;
            background: var(--primary-gradient);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .table-card .table-header .title {
            color: white;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .table-card .table-header .title i {
            margin-right: 8px;
        }
        
        .table-card .table-header .count {
            color: rgba(255,255,255,0.8);
            font-size: 0.75rem;
        }
        
        .table-card table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        
        .table-card table thead {
            background: var(--bg-body);
        }
        
        .table-card table th {
            padding: 10px 14px;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        
        .table-card table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .table-card table tr:hover td {
            background: var(--table-hover);
        }
        
        .table-card table tr:last-child td {
            border-bottom: none;
        }
        
        .table-card table tr.total-row td {
            border-top: 2px solid var(--border-color);
            font-weight: 700;
            font-size: 0.95rem;
        }
        
        .table-card table tr.total-row td:last-child {
            color: var(--primary);
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
            transition: border-color 0.3s ease, color 0.3s ease;
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .chart-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar select { width: 100%; min-width: unset; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
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
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .footer, #sidebarToggle, .filter-bar { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .stat-card { border: 1px solid #ddd !important; box-shadow: none !important; }
            .page-header {
                background: #0B5ED7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-title, .page-subtitle, .role-badge-display, .header-badge {
                color: white !important;
            }
            .chart-card, .table-card { break-inside: avoid; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SIDEBAR OVERLAY FOR MOBILE -->
<!-- ================================================================ -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-30 hidden lg:hidden"></div>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i>
                Revenue Report
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($branch_name_display) ?></strong>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_revenue, 0) ?> Total Revenue
                </span>
                <span class="header-badge" style="background:rgba(96,165,250,0.2);border-color:rgba(96,165,250,0.3);color:#60A5FA;">
                    <i class="fas fa-receipt"></i> <?= number_format($total_transactions) ?> Transactions
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS - 8 CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up" style="animation-delay:0.05s;">
        
        <!-- 1. Total Revenue -->
        <div class="stat-card card-total">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <p class="stat-label">Total Revenue</p>
                <p class="stat-value">TSh <?= number_format($total_revenue, 0) ?></p>
                <p class="stat-sub">Bills + OTC + Prescriptions</p>
            </div>
        </div>
        
        <!-- 2. Patient Bills -->
        <div class="stat-card" style="background: #0B5ED7;">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div>
                <p class="stat-label">Patient Bills</p>
                <p class="stat-value">TSh <?= number_format($patient_bills_revenue, 0) ?></p>
                <p class="stat-sub"><?= number_format($patient_bills_count) ?> paid bills</p>
            </div>
        </div>
        
        <!-- 3. OTC Revenue -->
        <div class="stat-card card-otc">
            <div class="stat-icon"><i class="fas fa-cash-register"></i></div>
            <div>
                <p class="stat-label">OTC Sales</p>
                <p class="stat-value">TSh <?= number_format($otc_revenue, 0) ?></p>
                <p class="stat-sub"><?= number_format($otc_count) ?> transactions</p>
            </div>
        </div>
        
        <!-- 4. Prescription Revenue -->
        <div class="stat-card card-prescription">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div>
                <p class="stat-label">Prescriptions</p>
                <p class="stat-value">TSh <?= number_format($prescription_revenue, 0) ?></p>
                <p class="stat-sub"><?= number_format($prescription_count) ?> dispensed</p>
            </div>
        </div>
        
        <!-- 5. Consultation -->
        <div class="stat-card card-consultation">
            <div class="stat-icon"><i class="fas fa-stethoscope"></i></div>
            <div>
                <p class="stat-label">Consultation</p>
                <p class="stat-value">TSh <?= number_format($consultation_revenue, 0) ?></p>
                <p class="stat-sub"><?= number_format($consultation_count) ?> consultations</p>
            </div>
        </div>
        
        <!-- 6. Lab Tests -->
        <div class="stat-card card-lab">
            <div class="stat-icon"><i class="fas fa-flask"></i></div>
            <div>
                <p class="stat-label">Lab Tests</p>
                <p class="stat-value">TSh <?= number_format($lab_revenue, 0) ?></p>
                <p class="stat-sub"><?= number_format($lab_count) ?> tests</p>
            </div>
        </div>
        
        <!-- 7. Medications -->
        <div class="stat-card card-medication">
            <div class="stat-icon"><i class="fas fa-pills"></i></div>
            <div>
                <p class="stat-label">Medications</p>
                <p class="stat-value">TSh <?= number_format($medication_revenue, 0) ?></p>
                <p class="stat-sub"><?= number_format($medication_count) ?> items</p>
            </div>
        </div>
        
        <!-- 8. Net Profit -->
        <div class="stat-card card-profit">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div>
                <p class="stat-label"><?= $net_profit >= 0 ? 'Net Profit' : 'Net Loss' ?></p>
                <p class="stat-value">TSh <?= number_format(abs($net_profit), 0) ?></p>
                <p class="stat-sub"><?= $profit_percentage ?>% margin</p>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-bar animate-fade-in-up" style="animation-delay:0.1s;">
        <span class="filter-label"><i class="fas fa-filter"></i> Filter</span>
        
        <form method="GET" class="flex flex-wrap gap-3 items-center w-full">
            <select name="branch" onchange="this.form.submit()" class="flex-1 min-w-[200px]">
                <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>All Branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                        🏥 <?= htmlspecialchars($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Apply Filter
            </button>
            
            <a href="revenue.php" class="btn btn-outline">
                <i class="fas fa-times"></i> Clear
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- CHARTS -->
    <!-- ================================================================ -->
    <div class="chart-grid animate-fade-in-up" style="animation-delay:0.15s;">
        
        <!-- Monthly Revenue Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">
                    <i class="fas fa-calendar-alt"></i> Monthly Revenue
                </span>
                <span class="chart-total">Last 12 months</span>
            </div>
            <div class="chart-body">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
        
        <!-- Daily Revenue Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">
                    <i class="fas fa-calendar-day"></i> Daily Revenue
                </span>
                <span class="chart-total">Last 30 days</span>
            </div>
            <div class="chart-body">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- REVENUE BREAKDOWN TABLE -->
    <!-- ================================================================ -->
    <div class="table-card animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="table-header">
            <span class="title"><i class="fas fa-list"></i> Revenue Breakdown by Source</span>
            <span class="count">Total: TSh <?= number_format($total_revenue, 0) ?></span>
        </div>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th>Source</th>
                        <th style="text-align:right;">Revenue</th>
                        <th style="text-align:right;">% of Total</th>
                        <th style="text-align:right;">Transactions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span style="color:#0B5ED7;">●</span> Patient Bills</td>
                        <td style="text-align:right;font-weight:600;color:#0B5ED7;">TSh <?= number_format($patient_bills_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);">
                            <?= $total_revenue > 0 ? round(($patient_bills_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($patient_bills_count) ?></td>
                    </tr>
                    <tr>
                        <td><span style="color:#0891B2;">●</span> OTC Sales</td>
                        <td style="text-align:right;font-weight:600;color:#0891B2;">TSh <?= number_format($otc_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);">
                            <?= $total_revenue > 0 ? round(($otc_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($otc_count) ?></td>
                    </tr>
                    <tr>
                        <td><span style="color:#7C3AED;">●</span> Prescriptions</td>
                        <td style="text-align:right;font-weight:600;color:#7C3AED;">TSh <?= number_format($prescription_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);">
                            <?= $total_revenue > 0 ? round(($prescription_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($prescription_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:24px;"><span style="color:#059669;">●</span> Consultation</td>
                        <td style="text-align:right;font-weight:500;color:#059669;">TSh <?= number_format($consultation_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);">
                            <?= $total_revenue > 0 ? round(($consultation_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($consultation_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:24px;"><span style="color:#7C3AED;">●</span> Lab Tests</td>
                        <td style="text-align:right;font-weight:500;color:#7C3AED;">TSh <?= number_format($lab_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);">
                            <?= $total_revenue > 0 ? round(($lab_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($lab_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:24px;"><span style="color:#D97706;">●</span> Medications</td>
                        <td style="text-align:right;font-weight:500;color:#D97706;">TSh <?= number_format($medication_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);">
                            <?= $total_revenue > 0 ? round(($medication_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($medication_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:24px;"><span style="color:#0D9488;">●</span> Procedures</td>
                        <td style="text-align:right;font-weight:500;color:#0D9488;">TSh <?= number_format($procedure_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);">
                            <?= $total_revenue > 0 ? round(($procedure_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($procedure_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:24px;"><span style="color:#64748B;">●</span> Registration</td>
                        <td style="text-align:right;font-weight:500;color:#64748B;">TSh <?= number_format($registration_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);">
                            <?= $total_revenue > 0 ? round(($registration_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($registration_count) ?></td>
                    </tr>
                    <tr class="total-row">
                        <td style="font-weight:700;font-size:0.95rem;">TOTAL</td>
                        <td style="text-align:right;font-weight:700;font-size:0.95rem;color:var(--primary);">TSh <?= number_format($total_revenue, 0) ?></td>
                        <td style="text-align:right;font-weight:700;font-size:0.95rem;color:var(--primary);">100%</td>
                        <td style="text-align:right;font-weight:700;font-size:0.95rem;color:var(--primary);"><?= number_format($total_transactions) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Revenue Report
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT - CHARTS -->
<!-- ================================================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    // ================================================================
    // DARK MODE - Sync with shared header
    // ================================================================
    (function() {
        var htmlElement = document.documentElement;
        var savedDarkMode = localStorage.getItem('darkMode');
        if (savedDarkMode === 'true') {
            htmlElement.setAttribute('data-theme', 'dark');
        } else {
            htmlElement.removeAttribute('data-theme');
        }
        
        window.addEventListener('storage', function(e) {
            if (e.key === 'darkMode') {
                if (e.newValue === 'true') {
                    htmlElement.setAttribute('data-theme', 'dark');
                } else {
                    htmlElement.removeAttribute('data-theme');
                }
            }
        });
    })();

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var overlay = document.getElementById('sidebarOverlay');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar) {
                sidebar.classList.toggle('open');
                if (overlay) overlay.classList.toggle('show');
                document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
            }
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            if (sidebar) {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
                if (overlay) overlay.classList.remove('show');
                document.body.style.overflow = '';
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
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH FUNCTION
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput ? searchInput.value.trim() : '';
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
    }

    // ================================================================
    // CHARTS
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var textColor = isDark ? '#94A3B8' : '#64748B';
        var gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
        
        // Monthly Chart
        var ctxMonthly = document.getElementById('monthlyChart')?.getContext('2d');
        if (ctxMonthly && typeof Chart !== 'undefined') {
            var monthlyLabels = <?= json_encode($monthly_labels) ?>;
            var monthlyPatient = <?= json_encode($monthly_patient) ?>;
            var monthlyOtc = <?= json_encode($monthly_otc) ?>;
            var monthlyPrescription = <?= json_encode($monthly_prescription) ?>;
            
            new Chart(ctxMonthly, {
                type: 'bar',
                data: {
                    labels: monthlyLabels,
                    datasets: [
                        {
                            label: 'Patient Bills',
                            data: monthlyPatient,
                            backgroundColor: '#0B5ED7',
                            borderRadius: 3,
                            barPercentage: 0.3
                        },
                        {
                            label: 'OTC',
                            data: monthlyOtc,
                            backgroundColor: '#0891B2',
                            borderRadius: 3,
                            barPercentage: 0.3
                        },
                        {
                            label: 'Prescriptions',
                            data: monthlyPrescription,
                            backgroundColor: '#7C3AED',
                            borderRadius: 3,
                            barPercentage: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: { size: 8, weight: '600' },
                                boxWidth: 10,
                                padding: 6,
                                color: textColor
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': TSh ' + context.raw.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'TSh ' + value.toLocaleString();
                                },
                                font: { size: 8 },
                                color: textColor
                            },
                            grid: { color: gridColor }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 8 }, color: textColor }
                        }
                    }
                }
            });
        }
        
        // Daily Chart
        var ctxDaily = document.getElementById('dailyChart')?.getContext('2d');
        if (ctxDaily && typeof Chart !== 'undefined') {
            var dailyLabels = <?= json_encode($daily_labels) ?>;
            var dailyPatient = <?= json_encode($daily_patient) ?>;
            var dailyOtc = <?= json_encode($daily_otc) ?>;
            var dailyPrescription = <?= json_encode($daily_prescription) ?>;
            
            new Chart(ctxDaily, {
                type: 'line',
                data: {
                    labels: dailyLabels,
                    datasets: [
                        {
                            label: 'Patient Bills',
                            data: dailyPatient,
                            borderColor: '#0B5ED7',
                            backgroundColor: 'rgba(11, 94, 215, 0.08)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 1.5,
                            pointBackgroundColor: '#0B5ED7',
                            borderWidth: 2
                        },
                        {
                            label: 'OTC',
                            data: dailyOtc,
                            borderColor: '#0891B2',
                            backgroundColor: 'rgba(8, 145, 178, 0.08)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 1.5,
                            pointBackgroundColor: '#0891B2',
                            borderWidth: 2
                        },
                        {
                            label: 'Prescriptions',
                            data: dailyPrescription,
                            borderColor: '#7C3AED',
                            backgroundColor: 'rgba(124, 58, 237, 0.08)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 1.5,
                            pointBackgroundColor: '#7C3AED',
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: { size: 8, weight: '600' },
                                boxWidth: 10,
                                padding: 6,
                                color: textColor
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': TSh ' + context.raw.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'TSh ' + value.toLocaleString();
                                },
                                font: { size: 8 },
                                color: textColor
                            },
                            grid: { color: gridColor }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 7 }, color: textColor, maxTicksLimit: 15 }
                        }
                    },
                    interaction: { intersect: false, mode: 'index' }
                }
            });
        }
    });

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        // Ctrl+K or Cmd+K to focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var searchInput = document.getElementById('searchInput');
            if (searchInput) searchInput.focus();
        }
    });

    // ================================================================
    // CONSOLE LOG
    // ================================================================
    console.log('%c🏥 Braick Dispensary - Revenue Report', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue, 0) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c   ├─ Patient Bills: TSh <?= number_format($patient_bills_revenue, 0) ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c   ├─ OTC Sales: TSh <?= number_format($otc_revenue, 0) ?>', 'font-size:12px; color:#0891B2;');
    console.log('%c   └─ Prescriptions: TSh <?= number_format($prescription_revenue, 0) ?>', 'font-size:12px; color:#7C3AED;');
    console.log('%c💸 Expenses: TSh <?= number_format($total_expenses, 0) ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c📈 Net Profit: TSh <?= number_format($net_profit, 0) ?> (<?= $profit_percentage ?>%)', 'font-size:13px; color:#059669;');
    console.log('%c✅ Header fixed with date/time display', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Revenue = Patient Bills + OTC + Prescriptions', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Uses paid_amount from bills (includes discounts)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Excludes OTC bills from bills table', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Prescription revenue from prescription_items table', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>