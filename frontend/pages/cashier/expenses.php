<?php
// ================================================================
// FILE: frontend/pages/cashier/expenses.php
// CASHIER - EXPENSES MANAGEMENT
// VIEW, ADD, EDIT, DELETE EXPENSES
// BRAICK DISPENSARY - GREEN THEME
// FIXED: Column 'branch_id' ambiguous - added table prefix "e."
// FIXED: Money format - 1,000,000,000
// FIXED: Auto-format amount with commas while typing
// FIXED: Reception allowed to access
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
// MONEY FORMAT FUNCTION - 1,000,000,000
// ================================================================
function formatMoney($amount) {
    if ($amount === null || $amount === '') {
        return '0';
    }
    return number_format((float)$amount, 0, '.', ',');
}

// ================================================================
// CHECK IF EXPENSES TABLE EXISTS - CREATE IF NOT
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
                `status` enum('pending','paid','cancelled') DEFAULT 'pending',
                `receipt_number` varchar(50) DEFAULT NULL,
                `notes` text DEFAULT NULL,
                `created_by` int(11) NOT NULL,
                `branch_id` int(11) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `idx_branch` (`branch_id`),
                KEY `idx_status` (`status`),
                KEY `idx_category` (`category`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
} catch (Exception $e) {
    // Table creation failed - handle gracefully
}

// ================================================================
// HANDLE ADD EXPENSE
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    // Remove commas before converting to float
    $amount = (float)str_replace(',', '', $_POST['amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'pending';
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
            
            $message = "✅ Expense added successfully! #" . $expense_number;
            $message_type = 'success';
            
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "❌ " . implode('<br>', $errors);
        $message_type = 'error';
    }
}

// ================================================================
// HANDLE UPDATE EXPENSE
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_expense') {
    $expense_id = (int)($_POST['expense_id'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    // Remove commas before converting to float
    $amount = (float)str_replace(',', '', $_POST['amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'pending';
    $receipt_number = trim($_POST['receipt_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    $errors = [];
    if (empty($category)) { $errors[] = 'Category is required'; }
    if (empty($description)) { $errors[] = 'Description is required'; }
    if ($amount <= 0) { $errors[] = 'Amount must be greater than 0'; }
    if (empty($payment_date)) { $errors[] = 'Payment date is required'; }
    
    if (empty($errors) && $expense_id > 0) {
        try {
            $stmt = $db->prepare("
                UPDATE expenses 
                SET category = ?,
                    description = ?,
                    amount = ?,
                    payment_method = ?,
                    payment_date = ?,
                    status = ?,
                    receipt_number = ?,
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ? AND branch_id = ?
            ");
            $stmt->execute([
                $category,
                $description,
                $amount,
                $payment_method,
                $payment_date,
                $status,
                $receipt_number,
                $notes,
                $expense_id,
                $user_branch_id
            ]);
            
            $message = "✅ Expense updated successfully!";
            $message_type = 'success';
            
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "❌ " . implode('<br>', $errors);
        $message_type = 'error';
    }
}

// ================================================================
// HANDLE DELETE EXPENSE
// ================================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $expense_id = (int)$_GET['delete'];
    
    try {
        $stmt = $db->prepare("DELETE FROM expenses WHERE id = ? AND branch_id = ?");
        $stmt->execute([$expense_id, $user_branch_id]);
        
        if ($stmt->rowCount() > 0) {
            $message = "✅ Expense deleted successfully!";
            $message_type = 'success';
        } else {
            $message = "❌ Expense not found!";
            $message_type = 'error';
        }
        
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// ================================================================
// HANDLE PAY EXPENSE (Mark as paid)
// ================================================================
if (isset($_GET['pay']) && is_numeric($_GET['pay'])) {
    $expense_id = (int)$_GET['pay'];
    
    try {
        $stmt = $db->prepare("
            UPDATE expenses 
            SET status = 'paid', updated_at = NOW() 
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$expense_id, $user_branch_id]);
        
        if ($stmt->rowCount() > 0) {
            $message = "✅ Expense marked as paid!";
            $message_type = 'success';
        } else {
            $message = "❌ Expense not found!";
            $message_type = 'error';
        }
        
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// ================================================================
// GET FILTER PARAMETERS
// ================================================================
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;

// ================================================================
// GET EXPENSES - FIXED: Added table prefix "e." for all columns
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

$where_clause = implode(" AND ", $conditions);

$sql = "
    SELECT e.*, u.full_name as created_by_name
    FROM expenses e
    LEFT JOIN users u ON e.created_by = u.id
    WHERE $where_clause
    ORDER BY e.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
$total_expenses = 0;
$total_pending = 0;
$total_paid = 0;
$total_cancelled = 0;
$total_amount = 0;
$pending_amount = 0;
$paid_amount = 0;

foreach ($expenses as $exp) {
    $total_expenses++;
    $total_amount += $exp['amount'];
    if ($exp['status'] === 'pending') { $total_pending++; $pending_amount += $exp['amount']; }
    if ($exp['status'] === 'paid') { $total_paid++; $paid_amount += $exp['amount']; }
    if ($exp['status'] === 'cancelled') { $total_cancelled++; }
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

// ================================================================
// SIDEBAR - CASHIER SIDEBAR (RECEPTION HAS FULL ACCESS)
// ================================================================
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
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - GREEN THEME
           ================================================================ */
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --primary-light: #34D399;
            --primary-bg: #D1FAE5;
            --primary-gradient: linear-gradient(135deg, #059669, #047857);
            --primary-gradient-strong: linear-gradient(135deg, #047857, #065F46);
            
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            
            --danger: #DC2626;
            --danger-dark: #B91C1C;
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
            
            --bg-body: #F0FDF4;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #D1FAE5;
            --radius: 12px;
            --radius-lg: 18px;
            --table-hover: #ECFDF5;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #34D399;
            --primary-dark: #059669;
            --primary-light: #6EE7B7;
            --primary-bg: #1A3A2A;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --table-hover: #1A3A2A;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: var(--primary-gradient-strong);
            border-radius: var(--radius-lg);
            padding: 18px 24px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(4, 120, 87, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 1.4rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        
        .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.12);
            padding: 5px 12px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.7rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
        }
        
        /* ================================================================
           STATS ROW
           ================================================================ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            border-radius: var(--radius-lg);
            padding: 12px 16px;
            border: none;
            transition: var(--transition);
            color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            text-decoration: none;
            display: block;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }
        
        .stat-card .stat-number {
            font-size: 1.3rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.6rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.85;
            margin-top: 1px;
        }
        
        .stat-card .stat-icon {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .stat-card.total { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .stat-card.pending { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.paid { background: linear-gradient(135deg, #059669, #047857); }
        .stat-card.cancelled { background: linear-gradient(135deg, #DC2626, #991B1B); }
        .stat-card.amount { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        
        /* ================================================================
           FILTER SECTION
           ================================================================ */
        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
        
        .filter-btn {
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 0.65rem;
            font-weight: 600;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
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
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }
        
        .btn-search {
            padding: 5px 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.7rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        .btn-add {
            background: var(--primary);
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
            background: var(--primary-dark);
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
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
            color: #ffffff;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .data-table thead th i {
            margin-right: 4px;
            opacity: 0.7;
        }
        
        .data-table tbody td {
            padding: 6px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--primary-bg);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tbody tr:nth-child(even) td {
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td {
            background: #1A1A2E;
        }
        
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
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(5, 150, 105, 0.25);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1.5px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-view {
            background: var(--gray-500);
            color: white;
            padding: 3px 10px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.55rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .btn-view:hover {
            background: var(--gray-600);
            transform: translateY(-1px);
        }
        
        .btn-edit {
            background: #0B5ED7;
            color: white;
            padding: 3px 10px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.55rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .btn-edit:hover {
            background: #0A4CA8;
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(11, 94, 215, 0.25);
        }
        
        .btn-delete {
            background: #DC2626;
            color: white;
            padding: 3px 10px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.55rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .btn-delete:hover {
            background: #B91C1C;
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(220, 38, 38, 0.25);
        }
        
        .btn-pay {
            background: #059669;
            color: white;
            padding: 3px 10px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.55rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .btn-pay:hover {
            background: #047857;
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(5, 150, 105, 0.25);
        }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            align-items: center;
        }
        
        /* ================================================================
           TABLE FOOTER
           ================================================================ */
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
            background: var(--primary);
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
        
        .modal-overlay.show {
            display: flex;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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
        
        .modal-header .modal-title i {
            color: var(--primary);
        }
        
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
        
        .form-group label .required {
            color: var(--danger);
            margin-left: 2px;
        }
        
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
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }
        
        .form-group textarea.form-control {
            resize: vertical;
            min-height: 60px;
        }
        
        .form-group select.form-control {
            appearance: auto;
            cursor: pointer;
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
            background: var(--primary);
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
            background: var(--primary-dark);
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
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 12px;
        }
        
        .empty-state p {
            font-size: 0.9rem;
        }
        
        .empty-state .sub {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        
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
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
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
            padding: 10px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
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
            .main-content { margin-left: 0; padding: 14px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 14px 16px; }
            .page-header .page-title { font-size: 1.1rem; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-input { width: 100%; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .data-table { font-size: 0.65rem; }
            .data-table thead th, .data-table tbody td { padding: 4px 8px; }
            .action-buttons { flex-direction: column; gap: 2px; }
            .modal-content { padding: 16px 18px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .stats-row { grid-template-columns: 1fr; }
            .page-title { font-size: 1rem; }
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
                    <i class="fas fa-list"></i> <?= $total_expenses ?> Total
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.2);color:#34D399;">
                    <i class="fas fa-money-bill-wave"></i> <?= $currency ?> <?= formatMoney($total_amount) ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-arrow-right"></i>
                Manage all branch expenses
                <span class="header-badge" style="background:rgba(251,191,36,0.15);border-color:rgba(251,191,36,0.1);font-size:0.5rem;">
                    <i class="fas fa-clock"></i> Pending: <?= $total_pending ?> (<?= $currency ?> <?= formatMoney($pending_amount) ?>)
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.15);border-color:rgba(52,211,153,0.1);font-size:0.5rem;">
                    <i class="fas fa-check-circle"></i> Paid: <?= $total_paid ?> (<?= $currency ?> <?= formatMoney($paid_amount) ?>)
                </span>
                <?php if ($is_reception): ?>
                    <span class="header-badge" style="background:rgba(52,211,153,0.2);color:#34D399;border-color:rgba(52,211,153,0.2);font-size:0.5rem;">
                        <i class="fas fa-user-tag"></i> Reception Access
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="openAddModal()" class="btn-outline-light" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.2);color:#34D399;">
                <i class="fas fa-plus-circle"></i> Add Expense
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-3 rounded-lg mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800' ?>" style="max-width:1200px;margin:0 auto 12px;font-size:0.8rem;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-row animate-fade-in-up">
        <div class="stat-card total">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-number"><?= $total_expenses ?></div>
            <div class="stat-label">Total Expenses</div>
        </div>
        <div class="stat-card pending">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number"><?= $total_pending ?></div>
            <div class="stat-label">⏳ Pending (<?= $currency ?> <?= formatMoney($pending_amount) ?>)</div>
        </div>
        <div class="stat-card paid">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?= $total_paid ?></div>
            <div class="stat-label">✅ Paid (<?= $currency ?> <?= formatMoney($paid_amount) ?>)</div>
        </div>
        <div class="stat-card cancelled">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-number"><?= $total_cancelled ?></div>
            <div class="stat-label">❌ Cancelled</div>
        </div>
        <div class="stat-card amount">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-number"><?= $currency ?> <?= formatMoney($total_amount) ?></div>
            <div class="stat-label">Total Amount</div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="filter-row">
            <a href="?status=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>">📋 All</a>
            <a href="?status=pending<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ Pending</a>
            <a href="?status=paid<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= $filter_status === 'paid' ? 'active' : '' ?>">✅ Paid</a>
            <a href="?status=cancelled<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= $filter_status === 'cancelled' ? 'active' : '' ?>">❌ Cancelled</a>
            
            <?php if (!empty($categories)): ?>
            <select name="category" class="filter-input" style="flex:0 1 auto;min-width:100px;" onchange="window.location.href='?status=<?= $filter_status ?>&category='+this.value+'<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>'">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $filter_category === $cat['category'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['category']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            
            <div style="flex:1;"></div>
            
            <form method="GET" class="filter-row" style="flex:1;gap:6px;" id="filterForm">
                <input type="hidden" name="status" id="filterStatus" value="<?= htmlspecialchars($filter_status) ?>">
                <input type="text" name="search" class="filter-input" id="searchInput" placeholder="Search description..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i>
                </button>
                <?php if (!empty($search) || $filter_status !== 'all'): ?>
                    <a href="expenses.php" class="btn btn-outline" style="padding:5px 10px;font-size:0.6rem;">
                        <i class="fas fa-times"></i>
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
    <div class="table-container animate-fade-in-up" style="animation-delay:0.1s;">
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
                        <th style="text-align:center;"><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($expenses) > 0): ?>
                        <?php $i = 1; foreach ($expenses as $exp): ?>
                            <tr>
                                <td style="text-align:center;"><?= $i++ ?></td>
                                <td>
                                    <span class="font-mono font-semibold" style="color:var(--primary);font-size:0.7rem;">
                                        <?= htmlspecialchars($exp['expense_number']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status" style="background:var(--purple-bg);color:var(--purple);border-color:var(--purple);">
                                        <?= htmlspecialchars($exp['category']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:0.75rem;"><?= htmlspecialchars($exp['description']) ?></span>
                                    <?php if (!empty($exp['notes'])): ?>
                                        <div class="text-xs text-gray-400">📝 <?= htmlspecialchars($exp['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;font-weight:700;color:var(--primary);">
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
                                    <div class="action-buttons" style="justify-content:center;">
                                        <a href="expenses.php?view=<?= $exp['id'] ?>" class="btn-view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($exp['status'] === 'pending'): ?>
                                            <a href="expenses.php?pay=<?= $exp['id'] ?>" class="btn-pay" onclick="return confirm('Mark this expense as paid?')" title="Mark as Paid">
                                                <i class="fas fa-check"></i> Pay
                                            </a>
                                            <a href="expenses.php?edit=<?= $exp['id'] ?>" class="btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($exp['status'] !== 'paid'): ?>
                                            <a href="expenses.php?delete=<?= $exp['id'] ?>" class="btn-delete" onclick="return confirm('Delete this expense? This action cannot be undone!')" title="Delete">
                                                <i class="fas fa-trash"></i>
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
                                    <?php if (!empty($search) || !empty($filter_category) || $filter_status !== 'all'): ?>
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
                <span class="text-xs" style="color:var(--text-secondary);">Total: <?= $currency ?> <?= formatMoney($total_amount) ?></span>
            </span>
            <span>
                <span class="count-badge"><?= $total_expenses ?></span> Total expenses
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
            <a href="expenses.php" class="modal-close">&times;</a>
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
                <div style="font-size:0.9rem;font-weight:700;color:var(--primary);"><?= $currency ?> <?= formatMoney($view_data['amount']) ?></div>
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
            <a href="expenses.php" class="btn-cancel-modal">
                <i class="fas fa-times"></i> Close
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ================================================================ -->
<!-- ADD EXPENSE MODAL - WITH AUTO-FORMAT COMMAS -->
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
            
            <!-- AMOUNT WITH AUTO-FORMAT COMMAS -->
            <div class="form-group">
                <label>Amount (TSh) <span class="required">*</span></label>
                <input type="text" 
                       name="amount" 
                       id="amountInput" 
                       class="form-control" 
                       placeholder="0" 
                       required
                       oninput="formatAmount(this)"
                       onfocus="this.select()">
                <div class="help-text" style="font-size:0.65rem;color:var(--text-muted);margin-top:2px;">
                    <i class="fas fa-info-circle"></i> Type numbers - commas added automatically (e.g., 1,000,000,000)
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
                        <option value="paid">✅ Paid</option>
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
        // Remove all non-digit characters
        var raw = input.value.replace(/[^0-9]/g, '');
        
        if (raw === '') {
            input.value = '';
            return;
        }
        
        // Parse as integer
        var num = parseInt(raw, 10);
        if (isNaN(num)) {
            input.value = '';
            return;
        }
        
        // Format with commas
        input.value = num.toLocaleString('en-US');
    }

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
        // Focus on amount input and select all
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
                document.getElementById('filterForm').submit();
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
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

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

    <?php if ($message && $message_type): ?>
        setTimeout(function() {
            showToast('<?= $message_type === 'success' ? '✅ Success' : '❌ Error' ?>', 
                '<?= addslashes($message) ?>', 
                '<?= $message_type ?>'
            );
        }, 500);
    <?php endif; ?>

    console.log('%c💰 Braick - Expenses Management', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:12px; color:#059669;');
    console.log('%c📊 Total: <?= $total_expenses ?> | Amount: <?= $currency ?> <?= formatMoney($total_amount) ?>', 'font-size:12px; color:#059669;');
    console.log('%c⏳ Pending: <?= $total_pending ?> | ✅ Paid: <?= $total_paid ?>', 'font-size:12px; color:#D97706;');
    console.log('%c✅ Reception access: <?= $is_reception ? 'YES' : 'NO' ?>', 'font-size:12px; color:#34D399;');
</script>

</body>
</html>