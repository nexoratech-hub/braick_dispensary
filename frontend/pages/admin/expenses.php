<?php
// ================================================================
// FILE: frontend/pages/admin/expenses.php
// ADMIN - VIEW ALL BRANCH EXPENSES
// WITH VIEW, EDIT, DELETE BUTTONS
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
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET FILTER PARAMETERS
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;

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
// GET BRANCH NAME
// ================================================================
$branch_name = 'All Branches';
if ($selected_branch_id > 0) {
    foreach ($branches as $b) {
        if ($b['id'] == $selected_branch_id) {
            $branch_name = $b['name'];
            break;
        }
    }
}

// ================================================================
// HANDLE ADD EXPENSE
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
    $branch_id = (int)($_POST['branch_id'] ?? $selected_branch_id);
    
    $errors = [];
    if (empty($category)) { $errors[] = 'Category is required'; }
    if (empty($description)) { $errors[] = 'Description is required'; }
    if ($amount <= 0) { $errors[] = 'Amount must be greater than 0'; }
    if (empty($payment_date)) { $errors[] = 'Payment date is required'; }
    if ($branch_id <= 0) { $errors[] = 'Branch is required'; }
    
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
                $branch_id
            ]);
            
            header('Location: expenses.php?branch=' . $branch_id . '&msg=add_success');
            exit;
            
        } catch (Exception $e) {
            header('Location: expenses.php?branch=' . $branch_id . '&msg=add_error');
            exit;
        }
    } else {
        header('Location: expenses.php?branch=' . $branch_id . '&msg=add_validation_error');
        exit;
    }
}

// ================================================================
// HANDLE UPDATE EXPENSE
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_expense') {
    $expense_id = (int)($_POST['expense_id'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = (float)str_replace(',', '', $_POST['amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'paid';
    $receipt_number = trim($_POST['receipt_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $branch_id = (int)($_POST['branch_id'] ?? $selected_branch_id);
    
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
                WHERE id = ?
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
                $expense_id
            ]);
            
            header('Location: expenses.php?branch=' . $branch_id . '&msg=update_success');
            exit;
            
        } catch (Exception $e) {
            header('Location: expenses.php?branch=' . $branch_id . '&msg=update_error');
            exit;
        }
    } else {
        header('Location: expenses.php?branch=' . $branch_id . '&msg=update_validation_error');
        exit;
    }
}

// ================================================================
// HANDLE DELETE EXPENSE
// ================================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $expense_id = (int)$_GET['delete'];
    $branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
    
    try {
        $check = $db->prepare("SELECT id, status FROM expenses WHERE id = ?");
        $check->execute([$expense_id]);
        $expense = $check->fetch(PDO::FETCH_ASSOC);
        
        if ($expense) {
            $stmt = $db->prepare("DELETE FROM expenses WHERE id = ?");
            $stmt->execute([$expense_id]);
            
            if ($stmt->rowCount() > 0) {
                header('Location: expenses.php?branch=' . $branch_id . '&msg=delete_success');
                exit;
            } else {
                header('Location: expenses.php?branch=' . $branch_id . '&msg=delete_error');
                exit;
            }
        } else {
            header('Location: expenses.php?branch=' . $branch_id . '&msg=delete_not_found');
            exit;
        }
        
    } catch (Exception $e) {
        header('Location: expenses.php?branch=' . $branch_id . '&msg=delete_error');
        exit;
    }
}

// ================================================================
// HANDLE REDIRECT MESSAGES
// ================================================================
$redirect_msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$message = '';
$message_type = '';

if ($redirect_msg === 'add_success') {
    $message = "✅ Expense added successfully!";
    $message_type = 'success';
} elseif ($redirect_msg === 'add_error') {
    $message = "❌ Error adding expense!";
    $message_type = 'error';
} elseif ($redirect_msg === 'add_validation_error') {
    $message = "❌ Please fill in all required fields correctly!";
    $message_type = 'error';
} elseif ($redirect_msg === 'update_success') {
    $message = "✅ Expense updated successfully!";
    $message_type = 'success';
} elseif ($redirect_msg === 'update_error') {
    $message = "❌ Error updating expense!";
    $message_type = 'error';
} elseif ($redirect_msg === 'update_validation_error') {
    $message = "❌ Please fill in all required fields correctly!";
    $message_type = 'error';
} elseif ($redirect_msg === 'delete_success') {
    $message = "✅ Expense deleted successfully!";
    $message_type = 'success';
} elseif ($redirect_msg === 'delete_not_found') {
    $message = "❌ Expense not found!";
    $message_type = 'error';
} elseif ($redirect_msg === 'delete_error') {
    $message = "❌ Error deleting expense!";
    $message_type = 'error';
}

// ================================================================
// BUILD QUERY FOR EXPENSES
// ================================================================
$conditions = ["1=1"];
$params = [];

// Branch filter
if ($selected_branch_id > 0) {
    $conditions[] = "e.branch_id = ?";
    $params[] = $selected_branch_id;
}

// Status filter
if ($filter_status !== 'all') {
    $conditions[] = "e.status = ?";
    $params[] = $filter_status;
}

// Category filter
if (!empty($filter_category)) {
    $conditions[] = "e.category = ?";
    $params[] = $filter_category;
}

// Search filter
if (!empty($search)) {
    $conditions[] = "(e.description LIKE ? OR e.category LIKE ? OR e.expense_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Date range filter
if (!empty($date_from) && !empty($date_to)) {
    $conditions[] = "DATE(e.payment_date) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
} elseif (!empty($date_from)) {
    $conditions[] = "DATE(e.payment_date) >= ?";
    $params[] = $date_from;
} elseif (!empty($date_to)) {
    $conditions[] = "DATE(e.payment_date) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(" AND ", $conditions);

$sql = "
    SELECT 
        e.*, 
        u.full_name as created_by_name,
        b.name as branch_name
    FROM expenses e
    LEFT JOIN users u ON e.created_by = u.id
    LEFT JOIN branches b ON e.branch_id = b.id
    WHERE $where_clause
    ORDER BY e.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET SUMMARY STATISTICS
// ================================================================
$total_expenses = 0;
$total_amount = 0;
$total_pending = 0;
$total_paid = 0;
$total_cancelled = 0;

foreach ($expenses as $exp) {
    $total_expenses++;
    $total_amount += (float)$exp['amount'];
    if ($exp['status'] === 'pending') $total_pending++;
    if ($exp['status'] === 'paid') $total_paid++;
    if ($exp['status'] === 'cancelled') $total_cancelled++;
}

// ================================================================
// GET CATEGORIES FOR DROPDOWN
// ================================================================
$categories = [];
try {
    $stmt = $db->prepare("SELECT DISTINCT category FROM expenses ORDER BY category");
    $stmt->execute();
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
        SELECT e.*, u.full_name as created_by_name, b.name as branch_name
        FROM expenses e
        LEFT JOIN users u ON e.created_by = u.id
        LEFT JOIN branches b ON e.branch_id = b.id
        WHERE e.id = ?
    ");
    $stmt->execute([$view_id]);
    $view_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ================================================================
// FUNCTION FORMAT CURRENCY
// ================================================================
function formatMoney($amount) {
    if ($amount === null || $amount === '') {
        return '0';
    }
    return number_format((float)$amount, 0, '.', ',');
}

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

function formatDateOnly($date) {
    if (empty($date)) return 'N/A';
    return date('M d, Y', strtotime($date));
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
    <title>Expenses - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
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
            --transition: all 0.3s ease;
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
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12);
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
            white-space: nowrap;
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
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .main-content { padding: 12px; }
        }
        
        .page-header {
            background: var(--primary-gradient-strong);
            border-radius: var(--radius-lg);
            padding: 24px 32px;
            margin-bottom: 24px;
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
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 1.6rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
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
            font-size: 0.65rem;
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
            padding: 8px 16px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.8rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            border-radius: var(--radius-lg);
            padding: 14px 18px;
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
            margin-top: 2px;
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
        
        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 14px 18px;
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
        
        .btn-reset {
            padding: 5px 12px;
            border-radius: var(--radius);
            font-size: 0.7rem;
            font-weight: 600;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .btn-reset:hover {
            border-color: var(--danger);
            color: var(--danger);
        }
        
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
            background: var(--primary);
            color: white;
            padding: 1px 10px;
            border-radius: 16px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
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
        
        .footer {
            padding: 10px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.4s ease forwards; opacity: 0; }
        
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
        
        .filter-divider {
            width: 1px;
            height: 28px;
            background: var(--border-color);
            margin: 0 4px;
        }
        
        @media (max-width: 768px) {
            .filter-divider { display: none; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-input { width: 100%; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.1rem; }
        }
        
        @media (max-width: 480px) {
            .stats-row { grid-template-columns: 1fr; }
            .data-table { font-size: 0.6rem; }
            .data-table thead th, .data-table td { padding: 4px 6px; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search expenses..." value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="0" <?= $selected_branch_id == 0 ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <!-- ============================================================ -->
        <!-- DATE & TIME - FIXED: Now showing correctly -->
        <!-- ============================================================ -->
        <span class="datetime" id="currentDateTime">
            <i class="far fa-calendar-alt mr-1"></i>
            <span id="dateDisplay"><?= date('M d, Y') ?></span>
            <span class="mx-1">|</span>
            <i class="far fa-clock mr-1"></i>
            <span id="timeDisplay"><?= date('h:i:s A') ?></span>
        </span>
        
        <!-- ============================================================ -->
        <!-- DARK MODE TOGGLE - FIXED: Now working -->
        <!-- ============================================================ -->
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas <?= (isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true') ? 'fa-sun' : 'fa-moon' ?>"></i>
            <span id="darkText"><?= (isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true') ? 'Light' : 'Dark' ?></span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="../admin/profile.php">
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
                <i class="fas fa-coins"></i>
                Expenses
                <span class="role-badge-display">ADMIN</span>
                <?php if ($selected_branch_id > 0): ?>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                    </span>
                <?php endif; ?>
                <span class="header-badge">
                    <i class="fas fa-list"></i> <?= $total_expenses ?> Total
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.2);color:#34D399;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= formatMoney($total_amount) ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-arrow-right"></i>
                Manage all branch expenses
                <span class="header-badge" style="background:rgba(251,191,36,0.15);border-color:rgba(251,191,36,0.1);font-size:0.55rem;">
                    <i class="fas fa-clock"></i> Pending: <?= $total_pending ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.15);border-color:rgba(52,211,153,0.1);font-size:0.55rem;">
                    <i class="fas fa-check-circle"></i> Paid: <?= $total_paid ?>
                </span>
                <span class="header-badge" style="background:rgba(239,68,68,0.15);border-color:rgba(239,68,68,0.1);font-size:0.55rem;">
                    <i class="fas fa-times-circle"></i> Cancelled: <?= $total_cancelled ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="openAddModal()" class="btn-outline-light">
                <i class="fas fa-plus-circle"></i> Add Expense
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-3 rounded-lg mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800' ?>" style="max-width:1200px;font-size:0.8rem;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-row animate-fade-in-up">
        <a href="expenses.php?branch=<?= $selected_branch_id ?>&status=all" class="stat-card total">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-number"><?= $total_expenses ?></div>
            <div class="stat-label">Total Expenses</div>
        </a>
        <a href="expenses.php?branch=<?= $selected_branch_id ?>&status=pending" class="stat-card pending">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number"><?= $total_pending ?></div>
            <div class="stat-label">⏳ Pending</div>
        </a>
        <a href="expenses.php?branch=<?= $selected_branch_id ?>&status=paid" class="stat-card paid">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?= $total_paid ?></div>
            <div class="stat-label">✅ Paid</div>
        </a>
        <a href="expenses.php?branch=<?= $selected_branch_id ?>&status=cancelled" class="stat-card cancelled">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-number"><?= $total_cancelled ?></div>
            <div class="stat-label">❌ Cancelled</div>
        </a>
        <a href="expenses.php?branch=<?= $selected_branch_id ?>" class="stat-card amount">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-number">TSh <?= formatMoney($total_amount) ?></div>
            <div class="stat-label">Total Amount</div>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section animate-fade-in-up" style="animation-delay:0.05s;">
        <form method="GET" action="" id="filterForm" class="w-full">
            <div class="filter-row">
                <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
                
                <a href="?branch=<?= $selected_branch_id ?>&status=all&category=<?= urlencode($filter_category) ?>&search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>">📋 All</a>
                <a href="?branch=<?= $selected_branch_id ?>&status=pending&category=<?= urlencode($filter_category) ?>&search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="filter-btn <?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ Pending</a>
                <a href="?branch=<?= $selected_branch_id ?>&status=paid&category=<?= urlencode($filter_category) ?>&search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="filter-btn <?= $filter_status === 'paid' ? 'active' : '' ?>">✅ Paid</a>
                <a href="?branch=<?= $selected_branch_id ?>&status=cancelled&category=<?= urlencode($filter_category) ?>&search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="filter-btn <?= $filter_status === 'cancelled' ? 'active' : '' ?>">❌ Cancelled</a>
                
                <span class="filter-divider"></span>
                
                <?php if (!empty($categories)): ?>
                <select name="category" class="filter-input" style="min-width:120px;" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $filter_category === $cat['category'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                
                <span class="filter-divider"></span>
                
                <input type="date" name="date_from" class="filter-input" style="min-width:130px;" value="<?= $date_from ?>" placeholder="From">
                <span style="font-size:0.7rem;color:var(--text-secondary);">to</span>
                <input type="date" name="date_to" class="filter-input" style="min-width:130px;" value="<?= $date_to ?>" placeholder="To">
                
                <span class="filter-divider"></span>
                
                <input type="text" name="search" class="filter-input" style="min-width:150px;flex:1;" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Filter
                </button>
                
                <a href="expenses.php?branch=<?= $selected_branch_id ?>" class="btn-reset">
                    <i class="fas fa-times"></i> Reset
                </a>
                
                <button type="button" onclick="openAddModal()" class="btn-add">
                    <i class="fas fa-plus"></i> Add
                </button>
            </div>
        </form>
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
                        <th><i class="fas fa-store-alt"></i> Branch</th>
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
                                    <span class="font-medium" style="font-size:0.65rem;color:var(--primary);">
                                        <?= htmlspecialchars($exp['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
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
                                    TSh <?= formatMoney($exp['amount']) ?>
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
                                        <a href="expenses.php?view=<?= $exp['id'] ?>&branch=<?= $selected_branch_id ?>&status=<?= $filter_status ?>&category=<?= urlencode($filter_category) ?>&search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="btn-view" title="View Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <a href="#" class="btn-edit" title="Edit" onclick="openEditModal(<?= $exp['id'] ?>, <?= $exp['branch_id'] ?>, '<?= htmlspecialchars($exp['expense_number']) ?>', '<?= htmlspecialchars(addslashes($exp['category'])) ?>', '<?= htmlspecialchars(addslashes($exp['description'])) ?>', '<?= formatMoney($exp['amount']) ?>', '<?= $exp['payment_method'] ?? 'cash' ?>', '<?= $exp['payment_date'] ?>', '<?= $exp['status'] ?>', '<?= htmlspecialchars(addslashes($exp['receipt_number'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($exp['notes'] ?? '')) ?>')">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        
                                        <a href="expenses.php?delete=<?= $exp['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-delete" onclick="return confirm('Delete this expense? This action cannot be undone!')" title="Delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <i class="fas fa-coins"></i>
                                    <p>No expenses found</p>
                                    <?php if (!empty($search) || !empty($filter_category) || $filter_status !== 'all' || !empty($date_from) || !empty($date_to)): ?>
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
                <span class="text-xs" style="color:var(--text-secondary);">Total: TSh <?= formatMoney($total_amount) ?></span>
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
            <a href="expenses.php?branch=<?= $selected_branch_id ?>&status=<?= $filter_status ?>&category=<?= urlencode($filter_category) ?>&search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="modal-close">&times;</a>
        </div>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div style="padding:8px 12px;background:var(--bg-body);border-radius:8px;">
                <div style="font-size:0.6rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Branch</div>
                <div style="font-size:0.9rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($view_data['branch_name'] ?? 'N/A') ?></div>
            </div>
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
                <div style="font-size:0.9rem;font-weight:700;color:var(--primary);">TSh <?= formatMoney($view_data['amount']) ?></div>
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
                <div style="font-size:0.9rem;font-weight:600;color:var(--text-primary);"><?= date('M d, Y h:i A', strtotime($view_data['created_at'] ?? 'now')) ?></div>
            </div>
        </div>
        
        <div class="form-actions">
            <a href="expenses.php?branch=<?= $selected_branch_id ?>&status=<?= $filter_status ?>&category=<?= urlencode($filter_category) ?>&search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="btn-cancel-modal">
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
            <input type="hidden" name="branch_id" value="<?= $selected_branch_id ?>">
            
            <?php if ($selected_branch_id == 0): ?>
            <div class="form-group">
                <label>Branch <span class="required">*</span></label>
                <select name="branch_id" class="form-control" required>
                    <option value="">Select Branch</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
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
<!-- EDIT EXPENSE MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-edit"></i> Edit Expense
            </div>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        
        <form method="POST" action="" id="editForm">
            <input type="hidden" name="action" value="update_expense">
            <input type="hidden" name="expense_id" id="editExpenseId">
            <input type="hidden" name="branch_id" id="editBranchId" value="<?= $selected_branch_id ?>">
            
            <div class="form-group">
                <label>Expense Number</label>
                <input type="text" id="editExpenseNumber" class="form-control" disabled style="background:var(--gray-100);">
            </div>
            
            <div class="form-group">
                <label>Category <span class="required">*</span></label>
                <select name="category" id="editCategory" class="form-control" required>
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
                <input type="text" name="description" id="editDescription" class="form-control" placeholder="Enter expense description" required>
            </div>
            
            <div class="form-group">
                <label>Amount (TSh) <span class="required">*</span></label>
                <input type="text" 
                       name="amount" 
                       id="editAmount" 
                       class="form-control" 
                       placeholder="0" 
                       required
                       oninput="formatAmount(this)"
                       onfocus="this.select()">
            </div>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" id="editPaymentMethod" class="form-control">
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
                    <input type="date" name="payment_date" id="editPaymentDate" class="form-control" required>
                </div>
            </div>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="editStatus" class="form-control">
                        <option value="pending">⏳ Pending</option>
                        <option value="paid" selected>✅ Paid</option>
                        <option value="cancelled">❌ Cancelled</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Receipt Number</label>
                    <input type="text" name="receipt_number" id="editReceiptNumber" class="form-control" placeholder="Optional receipt #">
                </div>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="editNotes" class="form-control" rows="2" placeholder="Additional notes..."></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Update Expense
                </button>
                <button type="button" class="btn-cancel-modal" onclick="closeModal('editModal')">
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
    // AUTO-FORMAT AMOUNT WITH COMMAS
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
    // OPEN EDIT MODAL
    // ================================================================
    function openEditModal(id, branchId, number, category, description, amount, method, date, status, receipt, notes) {
        document.getElementById('editExpenseId').value = id;
        document.getElementById('editBranchId').value = branchId;
        document.getElementById('editExpenseNumber').value = number;
        document.getElementById('editCategory').value = category;
        document.getElementById('editDescription').value = description;
        document.getElementById('editAmount').value = amount;
        document.getElementById('editPaymentMethod').value = method;
        document.getElementById('editPaymentDate').value = date;
        document.getElementById('editStatus').value = status;
        document.getElementById('editReceiptNumber').value = receipt || '';
        document.getElementById('editNotes').value = notes || '';
        document.getElementById('editModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    // ================================================================
    // DARK MODE - FIXED: Now working properly
    // ================================================================
    (function() {
        var htmlElement = document.documentElement;
        var darkIcon = document.getElementById('darkIcon');
        var darkText = document.getElementById('darkText');
        var darkToggle = document.getElementById('darkModeToggle');
        
        function applyDarkMode(isDark) {
            if (isDark) {
                htmlElement.setAttribute('data-theme', 'dark');
                document.cookie = "dark_mode=true; path=/";
                if (darkIcon) { darkIcon.className = 'fas fa-sun'; }
                if (darkText) { darkText.textContent = 'Light'; }
                localStorage.setItem('darkMode', 'true');
            } else {
                htmlElement.removeAttribute('data-theme');
                document.cookie = "dark_mode=false; path=/";
                if (darkIcon) { darkIcon.className = 'fas fa-moon'; }
                if (darkText) { darkText.textContent = 'Dark'; }
                localStorage.setItem('darkMode', 'false');
            }
        }
        
        // Load saved preference
        var saved = localStorage.getItem('darkMode');
        if (saved === null) {
            // Check cookie
            var cookieMatch = document.cookie.match(/dark_mode=([^;]+)/);
            saved = cookieMatch ? cookieMatch[1] : 'false';
        }
        applyDarkMode(saved === 'true');
        
        // Toggle on button click
        if (darkToggle) {
            darkToggle.addEventListener('click', function() {
                var isDark = htmlElement.getAttribute('data-theme') === 'dark';
                applyDarkMode(!isDark);
                // Notify other pages
                window.dispatchEvent(new StorageEvent('storage', {
                    key: 'darkMode',
                    newValue: isDark ? 'false' : 'true'
                }));
            });
        }
        
        // Listen for changes from other tabs
        window.addEventListener('storage', function(e) {
            if (e.key === 'darkMode') {
                applyDarkMode(e.newValue === 'true');
            }
        });
    })();

    // ================================================================
    // DATE & TIME - FIXED: Now showing live updates
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var options = { year: 'numeric', month: 'short', day: 'numeric' };
        var dateStr = now.toLocaleDateString('en-US', options);
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        
        var dateDisplay = document.getElementById('dateDisplay');
        var timeDisplay = document.getElementById('timeDisplay');
        var updateTimeDisplay = document.getElementById('updateTimeDisplay');
        
        if (dateDisplay) dateDisplay.textContent = dateStr;
        if (timeDisplay) timeDisplay.textContent = timeStr;
        if (updateTimeDisplay) updateTimeDisplay.textContent = 'Last update: ' + timeStr;
    }
    
    // Initial update
    updateDateTime();
    
    // Update every second
    setInterval(updateDateTime, 1000);

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
    // BRANCH SWITCH
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.delete('view');
        window.location.href = url.toString();
    }

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
                document.getElementById('filterForm').submit();
            }
        });
    }
    
    // Search button
    var searchBtn = document.getElementById('searchBtn');
    if (searchBtn) {
        searchBtn.addEventListener('click', function() {
            document.getElementById('filterForm').submit();
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

    console.log('%c💰 Braick - Admin Expenses', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:12px; color:#059669;');
    console.log('%c🏢 Branch: <?= $branch_name ?> (ID: <?= $selected_branch_id ?>)', 'font-size:12px; color:#059669;');
    console.log('%c📊 Total Expenses: <?= $total_expenses ?>', 'font-size:12px; color:#059669;');
    console.log('%c✅ WITH VIEW, EDIT, DELETE buttons', 'font-size:12px; color:#34D399;');
</script>

</body>
</html>