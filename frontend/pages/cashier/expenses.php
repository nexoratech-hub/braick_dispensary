<?php
// ================================================================
// FILE: frontend/pages/cashier/expenses.php
// CASHIER - EXPENSES MANAGEMENT
// FIXED: Uses expenses table correctly
// 4 CARDS DESIGN: All, Pending, Paid, Cancelled
// WITH AUTO-UPDATE (3 SECONDS)
// VIEW, ADD, PAY Expenses
// BRAICK DISPENSARY - GREEN THEME
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// FORCE CLEAR SESSION CACHE
// ================================================================
unset($_SESSION['expenses_stats']);
unset($_SESSION['expenses_paid']);
unset($_SESSION['expenses_total']);

// ================================================================
// FORCE NO CACHE HEADERS
// ================================================================
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// ALLOWED ROLES: Cashier, Reception, Admin
// ================================================================
$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Cashier';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// CHECK IF USER IS RECEPTION
// ================================================================
$is_reception = ($user_role === 'reception');
$is_admin = ($user_role === 'admin');

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';
$message_type = '';
$currency = 'TSh';

// ================================================================
// GET SYSTEM SETTINGS
// ================================================================
try {
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
} catch (Exception $e) {
    $currency = 'TSh';
}

// ================================================================
// MONEY FORMAT FUNCTION
// ================================================================
function formatMoney($amount) {
    if ($amount === null || $amount === '') {
        return '0';
    }
    return number_format((float)$amount, 0, '.', ',');
}

// ================================================================
// CHECK IF EXPENSES TABLE EXISTS
// ================================================================
try {
    $stmt = $db->query("SHOW TABLES LIKE 'expenses'");
    if ($stmt->rowCount() == 0) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `expenses` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `expense_number` varchar(50) NOT NULL,
                `category` varchar(100) NOT NULL,
                `description` text NOT NULL,
                `amount` decimal(10,2) NOT NULL,
                `payment_method` enum('cash','m-pesa','airtel_money','tigo_pesa','bank','card','other') DEFAULT 'cash',
                `payment_date` date NOT NULL,
                `status` enum('pending','paid','cancelled') DEFAULT 'paid',
                `receipt_number` varchar(50) DEFAULT NULL,
                `notes` text DEFAULT NULL,
                `created_by` int(11) NOT NULL,
                `branch_id` int(11) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `idx_branch` (`branch_id`),
                KEY `idx_status` (`status`),
                KEY `idx_category` (`category`),
                KEY `idx_payment_date` (`payment_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
} catch (Exception $e) {
    // Table creation failed
}

// ================================================================
// HANDLE ADD EXPENSE - DEFAULT STATUS = 'paid'
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = (float)str_replace(',', '', $_POST['amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'paid';
    $receipt_number = trim($_POST['receipt_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    $errors = [];
    if (empty($category)) { $errors[] = 'Category is required'; }
    if (empty($description)) { $errors[] = 'Description is required'; }
    if ($amount <= 0) { $errors[] = 'Amount must be greater than 0'; }
    if (empty($payment_date)) { $errors[] = 'Payment date is required'; }
    
    if (empty($errors)) {
        try {
            $expense_number = 'EXP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("
                INSERT INTO expenses (
                    expense_number, category, description, amount,
                    payment_method, payment_date, status, receipt_number,
                    notes, created_by, branch_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $expense_number,
                $category,
                $description,
                $amount,
                $payment_method,
                $payment_date,
                $status,
                $receipt_number,
                $notes,
                $user_id,
                $user_branch_id
            ]);
            
            header('Location: expenses.php?msg=add_success&number=' . urlencode($expense_number));
            exit;
            
        } catch (Exception $e) {
            header('Location: expenses.php?msg=add_error');
            exit;
        }
    } else {
        header('Location: expenses.php?msg=add_validation_error');
        exit;
    }
}

// ================================================================
// HANDLE PAY EXPENSE (Mark as paid) - ONLY ACTION AVAILABLE
// ================================================================
if (isset($_GET['pay']) && is_numeric($_GET['pay'])) {
    $expense_id = (int)$_GET['pay'];
    
    try {
        $check = $db->prepare("SELECT id, status FROM expenses WHERE id = ? AND branch_id = ?");
        $check->execute([$expense_id, $user_branch_id]);
        $expense = $check->fetch(PDO::FETCH_ASSOC);
        
        if ($expense) {
            if ($expense['status'] === 'pending') {
                $stmt = $db->prepare("
                    UPDATE expenses 
                    SET status = 'paid', updated_at = NOW() 
                    WHERE id = ? AND branch_id = ?
                ");
                $stmt->execute([$expense_id, $user_branch_id]);
                
                if ($stmt->rowCount() > 0) {
                    header('Location: expenses.php?msg=pay_success');
                    exit;
                } else {
                    header('Location: expenses.php?msg=pay_error');
                    exit;
                }
            } elseif ($expense['status'] === 'paid') {
                header('Location: expenses.php?msg=pay_already_paid');
                exit;
            } else {
                header('Location: expenses.php?msg=pay_cancelled');
                exit;
            }
        } else {
            header('Location: expenses.php?msg=pay_not_found');
            exit;
        }
        
    } catch (Exception $e) {
        header('Location: expenses.php?msg=pay_error');
        exit;
    }
}

// ================================================================
// HANDLE REDIRECT MESSAGES
// ================================================================
$redirect_msg = isset($_GET['msg']) ? $_GET['msg'] : '';

if ($redirect_msg === 'add_success') {
    $exp_number = isset($_GET['number']) ? $_GET['number'] : '';
    $message = "✅ Expense added successfully! #" . htmlspecialchars($exp_number);
    $message_type = 'success';
} elseif ($redirect_msg === 'add_error') {
    $message = "❌ Error adding expense!";
    $message_type = 'error';
} elseif ($redirect_msg === 'add_validation_error') {
    $message = "❌ Please fill in all required fields correctly!";
    $message_type = 'error';
} elseif ($redirect_msg === 'pay_success') {
    $message = "✅ Expense marked as paid successfully!";
    $message_type = 'success';
} elseif ($redirect_msg === 'pay_already_paid') {
    $message = "ℹ️ Expense is already paid!";
    $message_type = 'info';
} elseif ($redirect_msg === 'pay_cancelled') {
    $message = "❌ Cannot mark cancelled expense as paid!";
    $message_type = 'error';
} elseif ($redirect_msg === 'pay_not_found') {
    $message = "❌ Expense not found!";
    $message_type = 'error';
} elseif ($redirect_msg === 'pay_error') {
    $message = "❌ Error marking expense as paid!";
    $message_type = 'error';
}

// ================================================================
// GET FILTER PARAMETERS
// ================================================================
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;

// ================================================================
// DATE FILTER PARAMETERS
// ================================================================
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ================================================================
// BUILD DATE FILTER CONDITIONS
// ================================================================
$date_condition = "";
$date_params = [];

if ($date_filter === 'daily') {
    $date_condition = " AND e.payment_date = CURDATE()";
} elseif ($date_filter === 'week') {
    $date_condition = " AND YEARWEEK(e.payment_date, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($date_filter === 'monthly') {
    $date_condition = " AND MONTH(e.payment_date) = MONTH(CURDATE()) AND YEAR(e.payment_date) = YEAR(CURDATE())";
} elseif ($date_filter === '3months') {
    $date_condition = " AND e.payment_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
} elseif ($date_filter === '6months') {
    $date_condition = " AND e.payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
} elseif ($date_filter === '1year') {
    $date_condition = " AND e.payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
} elseif ($date_filter === 'custom' && !empty($date_from) && !empty($date_to)) {
    $date_condition = " AND e.payment_date BETWEEN ? AND ?";
    $date_params = [$date_from, $date_to];
}

// ================================================================
// GET EXPENSES - Show ONLY this branch
// ================================================================
$conditions = ["e.branch_id = ?"];
$params = [$user_branch_id];

if ($filter_status !== 'all') {
    $conditions[] = "e.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_category)) {
    $conditions[] = "e.category = ?";
    $params[] = $filter_category;
}

if (!empty($search)) {
    $conditions[] = "(e.description LIKE ? OR e.category LIKE ? OR e.expense_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Add date filter conditions
if (!empty($date_condition)) {
    $conditions[] = $date_condition;
    $params = array_merge($params, $date_params);
}

$where_clause = implode(" AND ", $conditions);

$sql = "
    SELECT e.*, u.full_name as created_by_name
    FROM expenses e
    LEFT JOIN users u ON e.created_by = u.id
    WHERE $where_clause
    ORDER BY e.payment_date DESC, e.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATS FOR 4 CARDS
// ================================================================
try {
    // All Expenses
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_amount, COUNT(*) as total_count
        FROM expenses 
        WHERE branch_id = ?
    ");
    $stmt->execute([$user_branch_id]);
    $all_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $all_expenses = $all_data['total_count'] ?? 0;
    $all_amount = $all_data['total_amount'] ?? 0;
    
    // Pending Expenses
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_amount, COUNT(*) as total_count
        FROM expenses 
        WHERE branch_id = ? AND status = 'pending'
    ");
    $stmt->execute([$user_branch_id]);
    $pending_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_expenses = $pending_data['total_count'] ?? 0;
    $pending_amount = $pending_data['total_amount'] ?? 0;
    
    // Paid Expenses
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_amount, COUNT(*) as total_count
        FROM expenses 
        WHERE branch_id = ? AND status = 'paid'
    ");
    $stmt->execute([$user_branch_id]);
    $paid_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $paid_expenses = $paid_data['total_count'] ?? 0;
    $paid_amount = $paid_data['total_amount'] ?? 0;
    
    // Cancelled Expenses
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_amount, COUNT(*) as total_count
        FROM expenses 
        WHERE branch_id = ? AND status = 'cancelled'
    ");
    $stmt->execute([$user_branch_id]);
    $cancelled_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $cancelled_expenses = $cancelled_data['total_count'] ?? 0;
    $cancelled_amount = $cancelled_data['total_amount'] ?? 0;
    
} catch (Exception $e) {
    $all_expenses = 0;
    $all_amount = 0;
    $pending_expenses = 0;
    $pending_amount = 0;
    $paid_expenses = 0;
    $paid_amount = 0;
    $cancelled_expenses = 0;
    $cancelled_amount = 0;
}

// ================================================================
// GET CATEGORIES FOR DROPDOWN
// ================================================================
$categories = [];
try {
    $stmt = $db->prepare("SELECT DISTINCT category FROM expenses WHERE branch_id = ? ORDER BY category");
    $stmt->execute([$user_branch_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// ================================================================
// GET VIEW DATA
// ================================================================
$view_data = null;
if ($view_id > 0) {
    $stmt = $db->prepare("
        SELECT e.*, u.full_name as created_by_name
        FROM expenses e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = ? AND e.branch_id = ?
    ");
    $stmt->execute([$view_id, $user_branch_id]);
    $view_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ================================================================
// STATUS BADGE CLASS
// ================================================================
function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'paid' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-warning';
}

function getStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending',
        'paid' => '✅ Paid',
        'cancelled' => '❌ Cancelled'
    ];
    return $map[$status] ?? ucfirst($status);
}

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('M d, Y h:i A', strtotime($datetime));
}

function formatDateOnly($date) {
    if (empty($date)) return 'N/A';
    return date('M d, Y', strtotime($date));
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
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
            --warning-dark: #B45309;
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
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.15);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
            --table-hover: #E8F0FE;
            --transition: all 0.3s ease;
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
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.4);
            --primary-bg: #1E3A5F;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3D2E0A;
            --gray-100: #1E293B;
            --gray-200: #334155;
            --table-hover: #1E3A5F;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: var(--transition);
        }
        
        /* ================================================================
           4 STATS CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            max-width: 1200px;
            margin: 0 auto 16px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 18px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 14px 14px 0 0;
        }
        
        .stat-card:hover {
            border-color: var(--success);
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card .stat-icon {
            font-size: 1.6rem;
            margin-bottom: 4px;
            display: block;
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }
        
        .stat-card .stat-number.blue { color: var(--primary); }
        .stat-card .stat-number.yellow { color: var(--warning); }
        .stat-card .stat-number.green { color: var(--success); }
        .stat-card .stat-number.red { color: #DC2626; }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-top: 2px;
        }
        
        .stat-card .stat-sub {
            font-size: 0.6rem;
            color: var(--text-secondary);
            margin-top: 2px;
            opacity: 0.7;
        }
        
        /* Card accent colors */
        .stat-card.accent-blue::after { background: var(--primary); }
        .stat-card.accent-yellow::after { background: var(--warning); }
        .stat-card.accent-green::after { background: var(--success); }
        .stat-card.accent-red::after { background: #DC2626; }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, #059669, #047857);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.25);
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
            transition: var(--transition);
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
           FILTER SECTION
           ================================================================ */
        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .filter-section:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
        
        .filter-btn {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
        }
        
        .filter-btn:hover {
            border-color: var(--success);
            color: var(--success);
            background: var(--success-bg);
            transform: translateY(-1px);
        }
        
        .filter-btn.active {
            background: var(--success);
            color: white;
            border-color: var(--success);
        }
        
        .filter-btn.active:hover {
            background: var(--success-dark);
            border-color: var(--success-dark);
        }
        
        .filter-btn i { margin-right: 4px; font-size: 0.6rem; }
        
        .filter-input {
            padding: 5px 10px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.75rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
            flex: 1;
            min-width: 120px;
        }
        
        .filter-input:focus {
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }
        
        .filter-input.date-input {
            max-width: 150px;
            min-width: 130px;
        }
        
        .btn-search {
            padding: 5px 14px;
            background: var(--success);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.7rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-search:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
        }
        
        .btn-add {
            background: var(--success);
            color: white;
            padding: 8px 18px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.75rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-add:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        /* ================================================================
           TABLE
           ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .table-scroll {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 700;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            border-bottom: 3px solid var(--success-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .data-table thead th i { margin-right: 4px; opacity: 0.7; }
        .data-table thead th:first-child { border-radius: var(--radius-xs) 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 var(--radius-xs) 0 0; }
        
        .data-table td {
            padding: 6px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td { background: var(--table-hover); }
        .data-table tbody tr:last-child td { border-bottom: none; }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge-status {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 16px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.6rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-view {
            background: var(--primary);
            color: white;
        }
        .btn-view:hover { background: var(--primary-dark); transform: translateY(-1px); }
        
        .btn-pay {
            background: var(--success);
            color: white;
        }
        .btn-pay:hover { background: var(--success-dark); transform: translateY(-1px); box-shadow: 0 3px 10px rgba(5, 150, 105, 0.25); }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            align-items: center;
            justify-content: center;
        }
        
        .table-footer {
            padding: 8px 14px;
            border-top: 1px solid var(--border-color);
            font-size: 0.65rem;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 6px;
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .table-footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
            background: var(--gray-800);
        }
        
        .count-badge {
            background: var(--success);
            color: white;
            padding: 1px 10px;
            border-radius: 16px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        /* ================================================================
           MODAL
           ================================================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-overlay.show { display: flex; }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px 28px;
            max-width: 600px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow-xl);
            animation: slideUp 0.3s ease;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 16px;
        }
        
        .modal-header .modal-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-header .modal-title i { color: var(--success); }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }
        
        .modal-close:hover {
            color: var(--danger);
            transform: rotate(90deg);
        }
        
        .form-group {
            margin-bottom: 14px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .form-group label .required { color: var(--danger); margin-left: 2px; }
        
        .form-group .form-control {
            width: 100%;
            padding: 8px 14px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .form-group .form-control:focus {
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }
        
        .form-group textarea.form-control {
            resize: vertical;
            min-height: 60px;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .btn-save {
            background: var(--success);
            color: white;
            padding: 8px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-save:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-cancel-modal {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-cancel-modal:hover {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        .help-text {
            font-size: 0.65rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i { font-size: 3rem; color: var(--border-color); display: block; margin-bottom: 12px; }
        .empty-state p { font-size: 0.9rem; }
        .empty-state .sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 4px; }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 10px;
            z-index: 999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: var(--shadow-xl);
            font-size: 0.8rem;
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
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--success); font-weight: 600; }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.4s ease forwards; opacity: 0; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 20px; }
            .page-header .page-title { font-size: 1.3rem; }
            .filter-section { padding: 12px 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stat-card { padding: 12px 14px; }
            .stat-card .stat-number { font-size: 1.4rem; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 6px; }
            .stat-card { padding: 8px 10px; }
            .stat-card .stat-number { font-size: 1.1rem; }
            .stat-card .stat-label { font-size: 0.55rem; }
            .stat-card .stat-icon { font-size: 1.2rem; }
            .filter-row { flex-direction: column; align-items: stretch; }
        }
        
        @media (max-width: 400px) {
            .stats-grid { grid-template-columns: 1fr; gap: 6px; }
            .stat-card { padding: 8px 10px; }
            .stat-card .stat-number { font-size: 1.1rem; }
            .stat-card .stat-label { font-size: 0.55rem; }
            .stat-card .stat-icon { font-size: 1.2rem; }
        }
    </style>
</head>
<body>

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
                <i class="fas fa-coins"></i>
                Expenses
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
                <?php if ($is_reception): ?>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;border-color:rgba(52,211,153,0.3);">
                        <i class="fas fa-check-circle"></i> Full Access
                    </span>
                <?php endif; ?>
                <span class="header-badge">
                    <i class="fas fa-list"></i> <?= $all_expenses ?> Total
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-arrow-right"></i>
                Manage all branch expenses
                <?php if ($date_filter !== 'all'): ?>
                    <span class="header-badge" style="background:rgba(251,191,36,0.15);border-color:rgba(251,191,36,0.1);font-size:0.5rem;">
                        <i class="fas fa-calendar"></i> <?= ucfirst($date_filter) ?> Filter
                    </span>
                <?php endif; ?>
                <?php if (!empty($date_from) && !empty($date_to)): ?>
                    <span class="header-badge" style="background:rgba(251,191,36,0.15);border-color:rgba(251,191,36,0.1);font-size:0.5rem;">
                        <i class="fas fa-calendar-range"></i> <?= date('M d, Y', strtotime($date_from)) ?> - <?= date('M d, Y', strtotime($date_to)) ?>
                    </span>
                <?php endif; ?>
                <?php if ($is_reception): ?>
                    <span class="header-badge" style="background:rgba(52,211,153,0.2);color:#34D399;border-color:rgba(52,211,153,0.2);font-size:0.5rem;">
                        <i class="fas fa-user-tag"></i> Reception Access
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="openAddModal()" class="btn-outline-light" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.2);color:#34D399;">
                <i class="fas fa-plus-circle"></i> Add Expense
            </button>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 4 STATS CARDS: All, Pending, Paid, Cancelled -->
    <!-- ================================================================ -->
    <div class="stats-grid" id="globalStats">
        <!-- Card 1: All Expenses -->
        <div class="stat-card accent-blue" onclick="window.location.href='expenses.php?status=all'">
            <span class="stat-icon">📋</span>
            <p class="stat-number blue" id="statAll"><?= number_format($all_expenses) ?></p>
            <p class="stat-label">All Expenses</p>
            <p class="stat-sub">TSh <?= number_format($all_amount) ?></p>
        </div>
        
        <!-- Card 2: Pending Expenses -->
        <div class="stat-card accent-yellow" onclick="window.location.href='expenses.php?status=pending'">
            <span class="stat-icon">⏳</span>
            <p class="stat-number yellow" id="statPending"><?= number_format($pending_expenses) ?></p>
            <p class="stat-label">Pending</p>
            <p class="stat-sub">TSh <?= number_format($pending_amount) ?></p>
        </div>
        
        <!-- Card 3: Paid Expenses -->
        <div class="stat-card accent-green" onclick="window.location.href='expenses.php?status=paid'">
            <span class="stat-icon">✅</span>
            <p class="stat-number green" id="statPaid"><?= number_format($paid_expenses) ?></p>
            <p class="stat-label">Paid</p>
            <p class="stat-sub">TSh <?= number_format($paid_amount) ?></p>
        </div>
        
        <!-- Card 4: Cancelled Expenses -->
        <div class="stat-card accent-red" onclick="window.location.href='expenses.php?status=cancelled'">
            <span class="stat-icon">❌</span>
            <p class="stat-number red" id="statCancelled"><?= number_format($cancelled_expenses) ?></p>
            <p class="stat-label">Cancelled</p>
            <p class="stat-sub">TSh <?= number_format($cancelled_amount) ?></p>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-3 rounded-lg mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : ($message_type === 'info' ? 'bg-blue-100 text-blue-700 border border-blue-200 dark:bg-blue-900/20 dark:text-blue-300 dark:border-blue-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800') ?>" style="max-width:1200px;margin:0 auto 12px;font-size:0.8rem;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'info' ? 'fa-info-circle' : 'fa-exclamation-circle') ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- DATE FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="filter-row" style="margin-bottom:6px;">
            <span style="font-size:0.6rem;font-weight:700;color:var(--success);text-transform:uppercase;letter-spacing:0.04em;">
                <i class="fas fa-calendar"></i> Date:
            </span>
            <a href="?date_filter=daily<?= $filter_status !== 'all' ? '&status=' . urlencode($filter_status) : '' ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn date-btn <?= $date_filter === 'daily' ? 'active' : '' ?>">📅 Daily</a>
            <a href="?date_filter=week<?= $filter_status !== 'all' ? '&status=' . urlencode($filter_status) : '' ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn date-btn <?= $date_filter === 'week' ? 'active' : '' ?>">📅 Week</a>
            <a href="?date_filter=monthly<?= $filter_status !== 'all' ? '&status=' . urlencode($filter_status) : '' ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn date-btn <?= $date_filter === 'monthly' ? 'active' : '' ?>">📅 Monthly</a>
            <a href="?date_filter=3months<?= $filter_status !== 'all' ? '&status=' . urlencode($filter_status) : '' ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn date-btn <?= $date_filter === '3months' ? 'active' : '' ?>">📅 3 Months</a>
            <a href="?date_filter=6months<?= $filter_status !== 'all' ? '&status=' . urlencode($filter_status) : '' ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn date-btn <?= $date_filter === '6months' ? 'active' : '' ?>">📅 6 Months</a>
            <a href="?date_filter=1year<?= $filter_status !== 'all' ? '&status=' . urlencode($filter_status) : '' ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn date-btn <?= $date_filter === '1year' ? 'active' : '' ?>">📅 1 Year</a>
            <a href="?date_filter=all<?= $filter_status !== 'all' ? '&status=' . urlencode($filter_status) : '' ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn date-btn <?= $date_filter === 'all' ? 'active' : '' ?>">📅 All</a>
        </div>
        
        <!-- Custom Date Range -->
        <div class="filter-row" style="border-top:1px solid var(--border-color);padding-top:8px;margin-top:4px;">
            <span style="font-size:0.6rem;font-weight:700;color:var(--success);text-transform:uppercase;letter-spacing:0.04em;">
                <i class="fas fa-calendar-range"></i> Range:
            </span>
            <form method="GET" class="filter-row" style="flex:1;gap:6px;" id="dateRangeForm">
                <input type="hidden" name="date_filter" value="custom">
                <?php if ($filter_status !== 'all'): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <?php endif; ?>
                <?php if (!empty($filter_category)): ?>
                    <input type="hidden" name="category" value="<?= htmlspecialchars($filter_category) ?>">
                <?php endif; ?>
                <?php if (!empty($search)): ?>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <?php endif; ?>
                <input type="date" name="date_from" class="filter-input date-input" value="<?= htmlspecialchars($date_from) ?>" placeholder="From">
                <span style="color:var(--text-secondary);font-size:0.7rem;">to</span>
                <input type="date" name="date_to" class="filter-input date-input" value="<?= htmlspecialchars($date_to) ?>" placeholder="To">
                <button type="submit" class="btn-search" style="padding:5px 12px;font-size:0.65rem;">
                    <i class="fas fa-search"></i> Apply
                </button>
                <?php if (!empty($date_from) && !empty($date_to)): ?>
                    <a href="?date_filter=all<?= $filter_status !== 'all' ? '&status=' . urlencode($filter_status) : '' ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="btn btn-outline" style="padding:4px 10px;font-size:0.6rem;border:2px solid var(--border-color);border-radius:5px;background:transparent;color:var(--text-secondary);cursor:pointer;text-decoration:none;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATUS & CATEGORY FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="filter-row">
            <span style="font-size:0.6rem;font-weight:700;color:var(--success);text-transform:uppercase;letter-spacing:0.04em;">
                <i class="fas fa-filter"></i> Status:
            </span>
            <a href="?date_filter=<?= $date_filter ?>&status=all<?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_from) ? '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) : '' ?>" class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>">📋 All</a>
            <a href="?date_filter=<?= $date_filter ?>&status=pending<?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_from) ? '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) : '' ?>" class="filter-btn <?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ Pending</a>
            <a href="?date_filter=<?= $date_filter ?>&status=paid<?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_from) ? '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) : '' ?>" class="filter-btn <?= $filter_status === 'paid' ? 'active' : '' ?>">✅ Paid</a>
            <a href="?date_filter=<?= $date_filter ?>&status=cancelled<?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_from) ? '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) : '' ?>" class="filter-btn <?= $filter_status === 'cancelled' ? 'active' : '' ?>">❌ Cancelled</a>
            
            <?php if (!empty($categories)): ?>
            <select name="category" class="filter-input" style="flex:0 1 auto;min-width:100px;max-width:180px;" onchange="window.location.href='?date_filter=<?= $date_filter ?>&status=<?= $filter_status ?>&category='+this.value+'<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_from) ? '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) : '' ?>'">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $filter_category === $cat['category'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['category']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            
            <div style="flex:1;"></div>
            
            <form method="GET" class="filter-row" style="flex:1;gap:6px;max-width:350px;" id="searchForm">
                <input type="hidden" name="date_filter" value="<?= htmlspecialchars($date_filter) ?>">
                <?php if ($filter_status !== 'all'): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <?php endif; ?>
                <?php if (!empty($filter_category)): ?>
                    <input type="hidden" name="category" value="<?= htmlspecialchars($filter_category) ?>">
                <?php endif; ?>
                <?php if (!empty($date_from)): ?>
                    <input type="hidden" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                    <input type="hidden" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                <?php endif; ?>
                <input type="text" name="search" id="searchInput" class="filter-input" placeholder="Search description..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:100px;">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i>
                </button>
                <?php if (!empty($search) || $filter_status !== 'all' || !empty($filter_category) || !empty($date_from) || !empty($date_to)): ?>
                    <a href="expenses.php?date_filter=all" class="btn btn-outline" style="padding:5px 10px;font-size:0.6rem;border:2px solid var(--border-color);border-radius:5px;background:transparent;color:var(--text-secondary);cursor:pointer;text-decoration:none;">
                        <i class="fas fa-times"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
            
            <button onclick="openAddModal()" class="btn-add">
                <i class="fas fa-plus"></i> Add
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TABLE -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:30px;"><i class="fas fa-hashtag"></i></th>
                        <th><i class="fas fa-receipt"></i> Expense #</th>
                        <th><i class="fas fa-tag"></i> Category</th>
                        <th><i class="fas fa-align-left"></i> Description</th>
                        <th style="text-align:center;"><i class="fas fa-money-bill"></i> Amount</th>
                        <th style="text-align:center;"><i class="fas fa-credit-card"></i> Method</th>
                        <th style="text-align:center;"><i class="fas fa-calendar"></i> Date</th>
                        <th style="text-align:center;"><i class="fas fa-info-circle"></i> Status</th>
                        <th style="text-align:center;min-width:120px;"><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($expenses) > 0): ?>
                        <?php $i = 1; foreach ($expenses as $exp): ?>
                            <tr>
                                <td style="text-align:center;"><?= $i++ ?></td>
                                <td>
                                    <span class="font-mono font-semibold" style="color:var(--success);font-size:0.7rem;">
                                        <?= htmlspecialchars($exp['expense_number']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status" style="background:var(--primary-bg);color:var(--primary);border-color:var(--primary);">
                                        <?= htmlspecialchars($exp['category']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:0.75rem;"><?= htmlspecialchars($exp['description']) ?></span>
                                    <?php if (!empty($exp['notes'])): ?>
                                        <div class="text-xs text-gray-400">📝 <?= htmlspecialchars($exp['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;font-weight:700;color:var(--success);">
                                    <?= $currency ?> <?= formatMoney($exp['amount']) ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge-status" style="background:var(--gray-100);color:var(--gray-600);border-color:var(--gray-300);font-size:0.5rem;">
                                        <?= ucfirst(str_replace('_', ' ', $exp['payment_method'] ?? 'cash')) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <span class="text-xs"><?= formatDateOnly($exp['payment_date']) ?></span>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge-status <?= getStatusBadgeClass($exp['status']) ?>">
                                        <?= getStatusLabel($exp['status']) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <div class="action-buttons">
                                        <!-- VIEW button - available for ALL statuses -->
                                        <a href="expenses.php?view=<?= $exp['id'] ?><?= !empty($date_from) ? '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) : '' ?><?= $date_filter !== 'all' ? '&date_filter=' . urlencode($date_filter) : '' ?><?= $filter_status !== 'all' ? '&status=' . urlencode($filter_status) : '' ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="btn btn-view" title="View Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <!-- PAY button - ONLY for PENDING status -->
                                        <?php if ($exp['status'] === 'pending'): ?>
                                            <a href="expenses.php?pay=<?= $exp['id'] ?><?= !empty($date_from) ? '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) : '' ?><?= $date_filter !== 'all' ? '&date_filter=' . urlencode($date_filter) : '' ?><?= $filter_status !== 'all' ? '&status=' . urlencode($filter_status) : '' ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="btn btn-pay" onclick="return confirm('Mark this expense as paid?')" title="Mark as Paid">
                                                <i class="fas fa-check"></i> Pay
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-coins"></i>
                                    <p>No expenses found</p>
                                    <?php if (!empty($search) || !empty($filter_category) || $filter_status !== 'all' || !empty($date_from) || !empty($date_to) || $date_filter !== 'all'): ?>
                                        <p class="sub">Try adjusting your filters</p>
                                    <?php else: ?>
                                        <p class="sub">Click "Add Expense" to get started</p>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="table-footer">
            <span>
                <i class="fas fa-list"></i> Showing <strong><?= count($expenses) ?></strong> expenses
                <span class="text-xs" style="color:var(--text-secondary);">
                    Total: <?= $currency ?> <?= formatMoney($all_amount) ?>
                </span>
            </span>
            <span>
                <span class="count-badge"><?= $all_expenses ?></span> Total expenses
                <span class="text-xs" style="color:var(--text-secondary);" id="updateTimeDisplay">Last update: <?= date('H:i:s') ?></span>
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Expenses
            <span class="text-gray-300 mx-2">|</span>
            <span class="text-gray-400">👤 <?= htmlspecialchars($user_full_name) ?></span>
            <?php if ($is_reception): ?>
                <span class="text-gray-400 mx-2">|</span>
                <span style="color:#34D399;">👀 Reception Access</span>
            <?php endif; ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- VIEW EXPENSE MODAL -->
<!-- ================================================================ -->
<?php if ($view_data): ?>
<div class="modal-overlay show" id="viewModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-eye"></i> Expense Details
            </div>
            <a href="expenses.php?date_filter=<?= $date_filter ?>&status=<?= $filter_status ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_from) ? '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) : '' ?>" class="modal-close">&times;</a>
        </div>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div style="padding:8px 12px;background:var(--bg-body);border-radius:8px;">
                <div style="font-size:0.6rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Expense Number</div>
                <div style="font-size:0.9rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($view_data['expense_number']) ?></div>
            </div>
            <div style="padding:8px 12px;background:var(--bg-body);border-radius:8px;">
                <div style="font-size:0.6rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Category</div>
                <div style="font-size:0.9rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($view_data['category']) ?></div>
            </div>
            <div style="padding:8px 12px;background:var(--bg-body);border-radius:8px;grid-column:1/-1;">
                <div style="font-size:0.6rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Description</div>
                <div style="font-size:0.9rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($view_data['description']) ?></div>
            </div>
            <div style="padding:8px 12px;background:var(--bg-body);border-radius:8px;">
                <div style="font-size:0.6rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Amount</div>
                <div style="font-size:0.9rem;font-weight:700;color:var(--success);"><?= $currency ?> <?= formatMoney($view_data['amount']) ?></div>
            </div>
            <div style="padding:8px 12px;background:var(--bg-body);border-radius:8px;">
                <div style="font-size:0.6rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Payment Method</div>
                <div style="font-size:0.9rem;font-weight:600;color:var(--text-primary);"><?= ucfirst(str_replace('_', ' ', $view_data['payment_method'] ?? 'cash')) ?></div>
            </div>
            <div style="padding:8px 12px;background:var(--bg-body);border-radius:8px;">
                <div style="font-size:0.6rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Payment Date</div>
                <div style="font-size:0.9rem;font-weight:600;color:var(--text-primary);"><?= formatDateOnly($view_data['payment_date']) ?></div>
            </div>
            <div style="padding:8px 12px;background:var(--bg-body);border-radius:8px;">
                <div style="font-size:0.6rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Status</div>
                <div style="font-size:0.9rem;font-weight:600;">
                    <span class="badge-status <?= getStatusBadgeClass($view_data['status']) ?>">
                        <?= getStatusLabel($view_data['status']) ?>
                    </span>
                </div>
            </div>
            <?php if (!empty($view_data['receipt_number'])): ?>
            <div style="padding:8px 12px;background:var(--bg-body);border-radius:8px;">
                <div style="font-size:0.6rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Receipt Number</div>
                <div style="font-size:0.9rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($view_data['receipt_number']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($view_data['notes'])): ?>
            <div style="padding:8px 12px;background:var(--bg-body);border-radius:8px;grid-column:1/-1;">
                <div style="font-size:0.6rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Notes</div>
                <div style="font-size:0.9rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($view_data['notes']) ?></div>
            </div>
            <?php endif; ?>
            <div style="padding:8px 12px;background:var(--bg-body);border-radius:8px;grid-column:1/-1;">
                <div style="font-size:0.6rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Created By</div>
                <div style="font-size:0.9rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($view_data['created_by_name'] ?? 'Unknown') ?></div>
            </div>
            <div style="padding:8px 12px;background:var(--bg-body);border-radius:8px;grid-column:1/-1;">
                <div style="font-size:0.6rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Created At</div>
                <div style="font-size:0.9rem;font-weight:600;color:var(--text-primary);"><?= formatDate($view_data['created_at']) ?></div>
            </div>
        </div>
        
        <div class="form-actions">
            <a href="expenses.php?date_filter=<?= $date_filter ?>&status=<?= $filter_status ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_from) ? '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) : '' ?>" class="btn-cancel-modal">
                <i class="fas fa-times"></i> Close
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ================================================================ -->
<!-- ADD EXPENSE MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="addModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-plus-circle"></i> Add New Expense
            </div>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        
        <form method="POST" action="" id="expenseForm">
            <input type="hidden" name="action" value="add_expense">
            
            <div class="form-group">
                <label>Category <span class="required">*</span></label>
                <select name="category" class="form-control" required>
                    <option value="">Select Category</option>
                    <option value="Utilities">🔌 Utilities</option>
                    <option value="Rent">🏠 Rent</option>
                    <option value="Salary">💼 Salary</option>
                    <option value="Medical Supplies">💊 Medical Supplies</option>
                    <option value="Equipment">🔧 Equipment</option>
                    <option value="Maintenance">🛠️ Maintenance</option>
                    <option value="Transport">🚗 Transport</option>
                    <option value="Stationery">📄 Stationery</option>
                    <option value="Cleaning">🧹 Cleaning</option>
                    <option value="Security">🔒 Security</option>
                    <option value="Marketing">📢 Marketing</option>
                    <option value="Training">📚 Training</option>
                    <option value="Insurance">🛡️ Insurance</option>
                    <option value="Tax">📊 Tax</option>
                    <option value="Other">📌 Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Description <span class="required">*</span></label>
                <input type="text" name="description" class="form-control" placeholder="Enter expense description" required>
            </div>
            
            <div class="form-group">
                <label>Amount (<?= $currency ?>) <span class="required">*</span></label>
                <input type="text" 
                       name="amount" 
                       id="amountInput" 
                       class="form-control" 
                       placeholder="0" 
                       required
                       oninput="formatAmount(this)"
                       onfocus="this.select()">
                <div class="help-text">
                    <i class="fas fa-info-circle"></i> Type numbers - commas added automatically
                </div>
            </div>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" class="form-control">
                        <option value="cash">💵 Cash</option>
                        <option value="m-pesa">📱 M-Pesa</option>
                        <option value="airtel_money">📱 Airtel Money</option>
                        <option value="tigo_pesa">📱 Tigo Pesa</option>
                        <option value="bank">🏦 Bank</option>
                        <option value="card">💳 Card</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Payment Date <span class="required">*</span></label>
                    <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="pending">⏳ Pending</option>
                        <option value="paid" selected>✅ Paid</option>
                        <option value="cancelled">❌ Cancelled</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Receipt Number</label>
                    <input type="text" name="receipt_number" class="form-control" placeholder="Optional receipt #">
                </div>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes..."></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Save Expense
                </button>
                <button type="button" class="btn-cancel-modal" onclick="closeModal('addModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:0.9rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // AUTO-FORMAT AMOUNT WITH COMMAS WHILE TYPING
    // ================================================================
    function formatAmount(input) {
        var raw = input.value.replace(/[^0-9]/g, '');
        if (raw === '') {
            input.value = '';
            return;
        }
        var num = parseInt(raw, 10);
        if (isNaN(num)) {
            input.value = '';
            return;
        }
        input.value = num.toLocaleString('en-US');
    }

    // ================================================================
    // DARK MODE - Sync with header
    // ================================================================
    (function() {
        var htmlElement = document.documentElement;
        function applyDarkMode(isDark) {
            if (isDark) {
                htmlElement.setAttribute('data-theme', 'dark');
            } else {
                htmlElement.removeAttribute('data-theme');
            }
        }
        var saved = localStorage.getItem('darkMode');
        applyDarkMode(saved === 'true');
        
        window.addEventListener('storage', function(e) {
            if (e.key === 'darkMode') {
                applyDarkMode(e.newValue === 'true');
            }
        });
    })();

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // MODAL
    // ================================================================
    function openAddModal() {
        document.getElementById('addModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(function() {
            var amountInput = document.getElementById('amountInput');
            if (amountInput) {
                amountInput.focus();
                amountInput.select();
            }
        }, 300);
    }
    
    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
        document.body.style.overflow = 'auto';
    }
    
    document.querySelectorAll('.modal-overlay').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        });
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.show').forEach(function(modal) {
                modal.classList.remove('show');
                document.body.style.overflow = 'auto';
            });
        }
    });

    // ================================================================
    // SEARCH
    // ================================================================
    var searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('searchForm').submit();
            }
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

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var updateTimeDisplay = document.getElementById('updateTimeDisplay');
        if (updateTimeDisplay) {
            updateTimeDisplay.textContent = 'Last update: ' + timeStr;
        }
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // MANUAL REFRESH
    // ================================================================
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        if (btn) {
            btn.innerHTML = '<span class="spinner" style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.3);border-top-color:white;border-radius:50%;animation:spin 0.6s linear infinite;"></span> Loading...';
            btn.disabled = true;
        }
        
        fetchDashboardData();
        
        setTimeout(function() {
            if (btn) {
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
                btn.disabled = false;
            }
            showToast('✅ Refreshed', 'Page data updated manually', 'success');
        }, 1500);
    }

    // ================================================================
    // FETCH DASHBOARD DATA (AJAX)
    // ================================================================
    function fetchDashboardData() {
        var url = 'get_dashboard_data.php?t=' + Date.now();
        
        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    updateStats(data);
                } else {
                    console.error('Failed to fetch dashboard data:', data.message);
                }
            })
            .catch(function(error) {
                console.error('Fetch error:', error);
            });
    }

    // ================================================================
    // UPDATE STATS UI
    // ================================================================
    function updateStats(data) {
        // Update 4 stat cards
        var statMap = {
            'statAll': data.all_expenses || 0,
            'statPending': data.pending_expenses || 0,
            'statPaid': data.paid_expenses || 0,
            'statCancelled': data.cancelled_expenses || 0
        };
        
        for (var key in statMap) {
            var el = document.getElementById(key);
            if (el) {
                el.textContent = Number(statMap[key]).toLocaleString();
            }
        }
    }

    // ================================================================
    // AUTO UPDATE - EVERY 3 SECONDS
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    
    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        fetchDashboardData();
        updateInterval = setInterval(function() {
            if (!isUpdating) {
                isUpdating = true;
                fetchDashboardData();
                setTimeout(function() {
                    isUpdating = false;
                }, 1000);
            }
        }, 3000);
        console.log('%c🔄 Auto-update started (every 3s)', 'font-size:12px; color:#34D399;');
    }
    
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            console.log('%c⏹️ Auto-update stopped', 'font-size:12px; color:#DC2626;');
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
    });

    // ================================================================
    // ADD CSS ANIMATIONS
    // ================================================================
    var style = document.createElement('style');
    style.textContent = `
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes pulse-dot { 
            0%, 100% { opacity: 1; transform: scale(1); } 
            50% { opacity: 0.5; transform: scale(0.8); } 
        }
        .stat-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .stat-card:hover { transform: translateY(-4px); }
        .filter-btn { transition: all 0.3s ease; }
        .btn { transition: all 0.3s ease; }
    `;
    document.head.appendChild(style);

    // ================================================================
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startAutoUpdate();
        }, 1000);
    });

    <?php if ($message && $message_type): ?>
        setTimeout(function() {
            showToast('<?= $message_type === 'success' ? '✅ Success' : ($message_type === 'info' ? 'ℹ️ Info' : '❌ Error') ?>', 
                '<?= addslashes($message) ?>', 
                '<?= $message_type ?>'
            );
        }, 500);
    <?php endif; ?>

    console.log('%c💰 Braick - Expenses Management (4 Cards)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c🏢 Branch: <?= $user_branch_name ?> (ID: <?= $user_branch_id ?>)', 'font-size:13px; color:#64748B;');
    console.log('%c📊 All: <?= $all_expenses ?> | Pending: <?= $pending_expenses ?> | Paid: <?= $paid_expenses ?> | Cancelled: <?= $cancelled_expenses ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ 4 CARDS: All, Pending, Paid, Cancelled', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Paid Expenses: Only VIEW button', 'font-size:13px; color:#34D399;');
    console.log('%c⏳ Pending Expenses: VIEW + PAY buttons', 'font-size:13px; color:#34D399;');
    console.log('%c🚫 NO EDIT or DELETE buttons available', 'font-size:13px; color:#EF4444;');
    console.log('%c🔄 Auto-update every 3 seconds', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>