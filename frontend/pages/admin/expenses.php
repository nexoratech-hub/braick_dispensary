<?php
// ================================================================
// FILE: frontend/pages/admin/expenses.php
// ADMIN - EXPENSES MANAGEMENT WITH PDF EXPORT
// ✅ Uses SHARED header & sidebar (NO DUPLICATES)
// ✅ Blue theme + full dark mode support via --page-* variables
// ✅ 4 Cards: All, Pending, Paid, Cancelled
// ✅ View, Edit, Delete, Pay buttons
// ✅ PDF Export
// ✅ FIXED: Date filters (Daily, Week, Monthly, 3M, 6M, 1Y, All, Custom)
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

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

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

function formatMoney($amount) {
    if ($amount === null || $amount === '') return '0';
    return number_format((float)$amount, 0, '.', ',');
}

// ================================================================
// ENSURE EXPENSES TABLE EXISTS
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
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
} catch (Exception $e) {}

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
    $branch_id = (int)($_POST['branch_id'] ?? $user_branch_id);
    
    $errors = [];
    if (empty($category)) $errors[] = 'Category is required';
    if (empty($description)) $errors[] = 'Description is required';
    if ($amount <= 0) $errors[] = 'Amount must be greater than 0';
    if ($branch_id <= 0) $errors[] = 'Branch is required';
    
    if (empty($errors)) {
        try {
            $expense_number = 'EXP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("
                INSERT INTO expenses (expense_number, category, description, amount,
                    payment_method, payment_date, status, receipt_number,
                    notes, created_by, branch_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$expense_number, $category, $description, $amount,
                $payment_method, $payment_date, $status, $receipt_number,
                $notes, $user_id, $branch_id]);
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
    $branch_id = (int)($_POST['branch_id'] ?? $user_branch_id);
    
    if ($expense_id > 0) {
        try {
            $stmt = $db->prepare("
                UPDATE expenses SET category = ?, description = ?, amount = ?,
                    payment_method = ?, payment_date = ?, status = ?,
                    receipt_number = ?, notes = ?, updated_at = NOW()
                WHERE id = ? AND branch_id = ?
            ");
            $stmt->execute([$category, $description, $amount, $payment_method,
                $payment_date, $status, $receipt_number, $notes, $expense_id, $branch_id]);
            header('Location: expenses.php?branch=' . $branch_id . '&msg=update_success');
            exit;
        } catch (Exception $e) {
            header('Location: expenses.php?branch=' . $branch_id . '&msg=update_error');
            exit;
        }
    }
}

// ================================================================
// HANDLE DELETE
// ================================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $expense_id = (int)$_GET['delete'];
    $branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
    
    try {
        $stmt = $db->prepare("DELETE FROM expenses WHERE id = ? AND branch_id = ?");
        $stmt->execute([$expense_id, $branch_id]);
        if ($stmt->rowCount() > 0) {
            header('Location: expenses.php?branch=' . $branch_id . '&msg=delete_success');
            exit;
        }
        header('Location: expenses.php?branch=' . $branch_id . '&msg=delete_error');
        exit;
    } catch (Exception $e) {
        header('Location: expenses.php?branch=' . $branch_id . '&msg=delete_error');
        exit;
    }
}

// ================================================================
// HANDLE PAY
// ================================================================
if (isset($_GET['pay']) && is_numeric($_GET['pay'])) {
    $expense_id = (int)$_GET['pay'];
    $branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
    
    try {
        $stmt = $db->prepare("UPDATE expenses SET status = 'paid', updated_at = NOW() WHERE id = ? AND branch_id = ? AND status = 'pending'");
        $stmt->execute([$expense_id, $branch_id]);
        header('Location: expenses.php?branch=' . $branch_id . '&msg=pay_success');
        exit;
    } catch (Exception $e) {
        header('Location: expenses.php?branch=' . $branch_id . '&msg=pay_error');
        exit;
    }
}

// ================================================================
// REDIRECT MESSAGES
// ================================================================
$redirect_msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$selected_branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
if ($selected_branch_id == 0) $selected_branch_id = $user_branch_id;

$msg_map = [
    'add_success' => ['✅ Expense added successfully!', 'success'],
    'add_error' => ['❌ Error adding expense!', 'error'],
    'add_validation_error' => ['❌ Please fill in all required fields!', 'error'],
    'update_success' => ['✅ Expense updated successfully!', 'success'],
    'update_error' => ['❌ Error updating expense!', 'error'],
    'update_validation_error' => ['❌ Please fill in all required fields!', 'error'],
    'delete_success' => ['✅ Expense deleted successfully!', 'success'],
    'delete_error' => ['❌ Error deleting expense!', 'error'],
    'delete_not_found' => ['❌ Expense not found!', 'error'],
    'pay_success' => ['✅ Expense marked as paid!', 'success'],
    'pay_error' => ['❌ Error marking as paid!', 'error']
];
if (isset($msg_map[$redirect_msg])) {
    $message = $msg_map[$redirect_msg][0];
    $message_type = $msg_map[$redirect_msg][1];
}

// ================================================================
// FILTERS
// ================================================================
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;

$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ================================================================
// ✅ FIXED: DATE CONDITION (HAKUNA "AND" MWANZO)
// ================================================================
$date_condition = "";
$date_params = [];
if ($date_filter === 'daily') {
    $date_condition = "e.payment_date = CURDATE()";
} elseif ($date_filter === 'week') {
    $date_condition = "YEARWEEK(e.payment_date, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($date_filter === 'monthly') {
    $date_condition = "(MONTH(e.payment_date) = MONTH(CURDATE()) AND YEAR(e.payment_date) = YEAR(CURDATE()))";
} elseif ($date_filter === '3months') {
    $date_condition = "e.payment_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
} elseif ($date_filter === '6months') {
    $date_condition = "e.payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
} elseif ($date_filter === '1year') {
    $date_condition = "e.payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
} elseif ($date_filter === 'custom' && !empty($date_from) && !empty($date_to)) {
    $date_condition = "e.payment_date BETWEEN ? AND ?";
    $date_params = [$date_from, $date_to];
}

// ================================================================
// GET EXPENSES
// ================================================================
$conditions = ["e.branch_id = ?"];
$params = [$selected_branch_id];

if ($filter_status !== 'all') { $conditions[] = "e.status = ?"; $params[] = $filter_status; }
if (!empty($filter_category)) { $conditions[] = "e.category = ?"; $params[] = $filter_category; }
if (!empty($search)) {
    $conditions[] = "(e.description LIKE ? OR e.category LIKE ? OR e.expense_number LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if (!empty($date_condition)) {
    $conditions[] = $date_condition;
    $params = array_merge($params, $date_params);
}
$where_clause = implode(" AND ", $conditions);

$sql = "SELECT e.*, u.full_name as created_by_name, b.name as branch_name
    FROM expenses e
    LEFT JOIN users u ON e.created_by = u.id
    LEFT JOIN branches b ON e.branch_id = b.id
    WHERE " . $where_clause . "
    ORDER BY e.payment_date DESC, e.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATS
// ================================================================
try {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_amount, COUNT(*) as total_count FROM expenses WHERE branch_id = ?");
    $stmt->execute([$selected_branch_id]);
    $d = $stmt->fetch(PDO::FETCH_ASSOC); $all_expenses = $d['total_count']; $all_amount = $d['total_amount'];

    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_amount, COUNT(*) as total_count FROM expenses WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$selected_branch_id]);
    $d = $stmt->fetch(PDO::FETCH_ASSOC); $pending_expenses = $d['total_count']; $pending_amount = $d['total_amount'];

    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_amount, COUNT(*) as total_count FROM expenses WHERE branch_id = ? AND status = 'paid'");
    $stmt->execute([$selected_branch_id]);
    $d = $stmt->fetch(PDO::FETCH_ASSOC); $paid_expenses = $d['total_count']; $paid_amount = $d['total_amount'];

    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_amount, COUNT(*) as total_count FROM expenses WHERE branch_id = ? AND status = 'cancelled'");
    $stmt->execute([$selected_branch_id]);
    $d = $stmt->fetch(PDO::FETCH_ASSOC); $cancelled_expenses = $d['total_count']; $cancelled_amount = $d['total_amount'];
} catch (Exception $e) {
    $all_expenses = $all_amount = $pending_expenses = $pending_amount = 0;
    $paid_expenses = $paid_amount = $cancelled_expenses = $cancelled_amount = 0;
}

// ================================================================
// GET CATEGORIES
// ================================================================
$categories = [];
try {
    $stmt = $db->prepare("SELECT DISTINCT category FROM expenses WHERE branch_id = ? ORDER BY category");
    $stmt->execute([$selected_branch_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $categories = []; }

// ================================================================
// GET VIEW DATA
// ================================================================
$view_data = null;
if ($view_id > 0) {
    $stmt = $db->prepare("SELECT e.*, u.full_name as created_by_name, b.name as branch_name
        FROM expenses e LEFT JOIN users u ON e.created_by = u.id LEFT JOIN branches b ON e.branch_id = b.id
        WHERE e.id = ? AND e.branch_id = ?");
    $stmt->execute([$view_id, $selected_branch_id]);
    $view_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getStatusBadgeClass($status) {
    $map = ['pending' => 'warning', 'paid' => 'success', 'cancelled' => 'danger'];
    return $map[$status] ?? 'warning';
}
function getStatusLabel($status) {
    $map = ['pending' => '⏳ Pending', 'paid' => '✅ Paid', 'cancelled' => '❌ Cancelled'];
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

$branch_name = 'All Branches';
foreach ($branches as $b) {
    if ($b['id'] == $selected_branch_id) { $branch_name = $b['name']; break; }
}

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// ✅ SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================
     PAGE-SPECIFIC CSS - TUMIA VARIABLES ZA HEADER (--page-*)
     ================================================================ -->
<style>
    /* ================================================================
       PAGE HEADER - GREEN GRADIENT
       ================================================================ */
    .page-header-exp {
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

    .page-header-exp::before {
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

    .page-header-exp .page-title-exp {
        color: white;
        font-size: 1.8rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-exp .page-title-exp i { font-size: 2rem; opacity: 0.9; }

    .page-header-exp .page-subtitle-exp {
        color: rgba(255,255,255,0.85);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 6px;
    }

    .page-header-exp .role-badge-display {
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

    .page-header-exp .header-badge-exp {
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

    .page-header-exp .btn-outline-light-exp {
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
        cursor: pointer;
    }

    .page-header-exp .btn-outline-light-exp:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* ================================================================
       STATS CARDS - 4 CARDS
       ================================================================ */
    .stats-grid-exp {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        max-width: 1200px;
        margin: 0 auto 16px;
    }

    .stat-card-exp {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 14px;
        padding: 16px 18px;
        border: 2px solid var(--page-border, #E2E8F0);
        text-align: center;
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .stat-card-exp::after {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
        border-radius: 14px 14px 0 0;
    }

    .stat-card-exp:hover {
        transform: translateY(-4px);
        box-shadow: var(--page-shadow-lg, 0 10px 25px rgba(0,0,0,0.1));
        border-color: #059669;
    }

    .stat-card-exp .stat-icon-exp {
        font-size: 1.6rem;
        margin-bottom: 4px;
        display: block;
    }

    .stat-card-exp .stat-number-exp {
        font-size: 1.8rem;
        font-weight: 700;
        line-height: 1.2;
        letter-spacing: -0.02em;
    }

    .stat-card-exp .stat-number-exp.blue { color: #0B5ED7; }
    .stat-card-exp .stat-number-exp.yellow { color: #D97706; }
    .stat-card-exp .stat-number-exp.green { color: #059669; }
    .stat-card-exp .stat-number-exp.red { color: #DC2626; }

    .stat-card-exp .stat-label-exp {
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 500;
        margin-top: 2px;
    }

    .stat-card-exp .stat-sub-exp {
        font-size: 0.6rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 2px;
        opacity: 0.7;
    }

    .stat-card-exp.accent-blue::after { background: #0B5ED7; }
    .stat-card-exp.accent-yellow::after { background: #D97706; }
    .stat-card-exp.accent-green::after { background: #059669; }
    .stat-card-exp.accent-red::after { background: #DC2626; }

    /* ================================================================
       FILTER SECTIONS
       ================================================================ */
    .filter-section-exp {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 12px;
        padding: 16px 20px;
        border: 1px solid var(--page-border, #E2E8F0);
        margin-bottom: 20px;
        box-shadow: var(--page-shadow-sm);
        transition: all 0.3s;
    }

    .filter-section-exp:hover {
        border-color: #059669;
        box-shadow: var(--page-shadow-md);
    }

    .filter-row-exp {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
    }

    .filter-label-exp {
        font-size: 0.65rem;
        font-weight: 700;
        color: #059669;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .filter-btn-exp {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        border: 2px solid var(--page-border, #E2E8F0);
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }

    .filter-btn-exp:hover {
        border-color: #059669;
        color: #059669;
        background: #D1FAE5;
        transform: translateY(-1px);
    }

    [data-theme="dark"] .filter-btn-exp:hover {
        background: #1A3A2A;
    }

    .filter-btn-exp.active {
        background: #059669;
        color: white;
        border-color: #059669;
    }

    .filter-btn-exp.active:hover {
        background: #047857;
        border-color: #047857;
    }

    .filter-input-exp {
        padding: 6px 12px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 12px;
        font-size: 0.78rem;
        background: var(--page-input-bg, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        outline: none;
        transition: all 0.3s;
        flex: 1;
        min-width: 120px;
        font-family: inherit;
    }

    .filter-input-exp:focus {
        border-color: #059669;
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
    }

    .btn-search-exp {
        padding: 6px 14px;
        background: #059669;
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.72rem;
        cursor: pointer;
        transition: all 0.3s;
        font-family: inherit;
    }

    .btn-search-exp:hover {
        background: #047857;
        transform: translateY(-1px);
    }

    .btn-add-exp {
        background: #059669;
        color: white;
        padding: 8px 18px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.78rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-family: inherit;
    }

    .btn-add-exp:hover {
        background: #047857;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }

    .btn-reset-exp {
        padding: 5px 10px;
        font-size: 0.65rem;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 8px;
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .btn-reset-exp:hover {
        border-color: #DC2626;
        color: #DC2626;
    }

    /* ================================================================
       TABLE
       ================================================================ */
    .table-container-exp {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        border: 1px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        box-shadow: var(--page-shadow-sm);
    }

    .table-scroll-exp {
        overflow-x: auto;
    }

    .data-table-exp {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.75rem;
    }

    .data-table-exp thead th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 700;
        font-size: 0.62rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: white;
        background: linear-gradient(135deg, #059669, #047857);
        border-bottom: 3px solid #047857;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 5;
    }

    .data-table-exp thead th i { margin-right: 4px; opacity: 0.75; }

    .data-table-exp tbody td {
        padding: 8px 14px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: middle;
    }

    .data-table-exp tbody tr:hover td {
        background: #E8F0FE;
    }

    [data-theme="dark"] .data-table-exp tbody tr:hover td {
        background: #1E3A5F;
    }

    .data-table-exp tbody tr:last-child td { border-bottom: none; }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge-status-exp {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 16px;
        font-size: 0.6rem;
        font-weight: 600;
        text-transform: capitalize;
    }

    .badge-warning-exp { background: #FEF3C7; color: #D97706; border: 1px solid #D97706; }
    .badge-success-exp { background: #D1FAE5; color: #059669; border: 1px solid #059669; }
    .badge-danger-exp { background: #FEE2E2; color: #DC2626; border: 1px solid #DC2626; }

    [data-theme="dark"] .badge-warning-exp { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .badge-success-exp { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .badge-danger-exp { background: #3A1A1A; color: #F87171; }

    /* ================================================================
       ACTION BUTTONS
       ================================================================ */
    .btn-exp {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 10px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.62rem;
        transition: all 0.3s;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }

    .btn-view-exp { background: #0B5ED7; color: white; }
    .btn-view-exp:hover { background: #0A4CA8; transform: translateY(-1px); color: white; }

    .btn-edit-exp { background: #D97706; color: white; }
    .btn-edit-exp:hover { background: #B45309; transform: translateY(-1px); color: white; }

    .btn-delete-exp { background: #DC2626; color: white; }
    .btn-delete-exp:hover { background: #B91C1C; transform: translateY(-1px); color: white; }

    .btn-pay-exp { background: #059669; color: white; }
    .btn-pay-exp:hover { background: #047857; transform: translateY(-1px); color: white; }

    .action-buttons-exp {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        align-items: center;
        justify-content: center;
    }

    .table-footer-exp {
        padding: 10px 16px;
        border-top: 1px solid var(--page-border, #E2E8F0);
        font-size: 0.68rem;
        color: var(--page-text-secondary, #64748B);
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 6px;
        background: var(--page-hover, #F8FAFC);
    }

    [data-theme="dark"] .table-footer-exp {
        background: #0F172A;
    }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state-exp {
        text-align: center;
        padding: 40px 20px;
        color: var(--page-text-secondary, #64748B);
    }

    .empty-state-exp i {
        font-size: 3rem;
        color: var(--page-text-muted, #94A3B8);
        display: block;
        margin-bottom: 12px;
    }

    /* ================================================================
       MODAL
       ================================================================ */
    .modal-overlay-exp {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(4px);
    }

    .modal-overlay-exp.show { display: flex; }

    .modal-content-exp {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        padding: 24px 28px;
        max-width: 600px;
        width: 95%;
        max-height: 90vh;
        overflow-y: auto;
        border: 2px solid var(--page-border, #E2E8F0);
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: slideUpExp 0.3s ease;
    }

    @keyframes slideUpExp {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .modal-header-exp {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        margin-bottom: 16px;
    }

    .modal-title-exp {
        font-size: 1rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-title-exp i { color: #059669; }

    .modal-close-exp {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--page-text-secondary, #64748B);
        transition: all 0.3s ease;
    }

    .modal-close-exp:hover {
        color: #DC2626;
        transform: rotate(90deg);
    }

    .form-group-exp { margin-bottom: 14px; }

    .form-group-exp label {
        display: block;
        font-weight: 600;
        font-size: 0.8rem;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 4px;
    }

    .form-group-exp label .required { color: #DC2626; margin-left: 2px; }

    .form-control-exp {
        width: 100%;
        padding: 8px 14px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 10px;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--page-input-bg, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        font-family: inherit;
    }

    .form-control-exp:focus {
        border-color: #059669;
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
    }

    textarea.form-control-exp { resize: vertical; min-height: 60px; }

    .form-actions-exp {
        display: flex;
        gap: 12px;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 2px solid var(--page-border, #E2E8F0);
        flex-wrap: wrap;
    }

    .btn-save-exp {
        background: #059669;
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
        font-family: inherit;
    }

    .btn-save-exp:hover {
        background: #047857;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }

    .btn-cancel-modal-exp {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        font-family: inherit;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-cancel-modal-exp:hover {
        border-color: #DC2626;
        color: #DC2626;
    }

    /* ================================================================
       MODAL VIEW GRID
       ================================================================ */
    .view-grid-exp {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .view-item-exp {
        padding: 8px 12px;
        background: var(--page-hover, #F8FAFC);
        border-radius: 8px;
    }

    [data-theme="dark"] .view-item-exp {
        background: #0F172A;
    }

    .view-item-exp .view-label-exp {
        font-size: 0.6rem;
        text-transform: uppercase;
        color: var(--page-text-secondary, #64748B);
        font-weight: 600;
        margin-bottom: 2px;
    }

    .view-item-exp .view-value-exp {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
    }

    .view-item-exp.full-width { grid-column: 1 / -1; }

    /* ================================================================
       PDF MODAL
       ================================================================ */
    .pdf-modal-overlay-exp {
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
    .pdf-modal-overlay-exp.active { display: flex; }

    .pdf-modal-exp {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        width: 95%;
        max-width: 1100px;
        max-height: 95vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    }

    .pdf-modal-header-exp {
        padding: 14px 22px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
        background: linear-gradient(135deg, #059669, #047857);
        border-radius: 16px 16px 0 0;
        flex-wrap: wrap;
        gap: 10px;
    }

    .pdf-modal-header-exp .modal-title-exp {
        color: white;
        font-size: 1rem;
    }

    .pdf-modal-header-exp .modal-actions-exp {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .pdf-modal-header-exp .modal-actions-exp button {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 6px 14px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: all 0.3s;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-family: inherit;
    }

    .pdf-modal-header-exp .modal-actions-exp button:hover {
        background: rgba(255,255,255,0.3);
        transform: translateY(-2px);
    }

    .pdf-modal-body-exp {
        flex: 1;
        overflow-y: auto;
        padding: 20px 28px;
        background: var(--page-bg-body, #F1F5F9);
    }

    .pdf-content-exp {
        max-width: 100%;
        font-size: 14px;
        background: var(--page-bg-card, #FFFFFF);
        padding: 24px 28px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid var(--page-border, #E2E8F0);
        line-height: 1.5;
    }

    .pdf-content-exp .pdf-header-exp {
        text-align: center;
        padding-bottom: 12px;
        border-bottom: 3px solid #059669;
        margin-bottom: 16px;
    }

    .pdf-content-exp .pdf-header-exp img {
        height: 55px;
        width: auto;
        object-fit: contain;
        display: block;
        margin: 0 auto;
    }

    .pdf-content-exp .clinic-name-exp {
        font-size: 1.4rem;
        font-weight: 800;
        color: #059669;
        letter-spacing: -0.5px;
        margin-top: 4px;
    }

    .pdf-content-exp .clinic-sub-exp {
        font-size: 0.75rem;
        color: var(--page-text-secondary, #64748B);
        letter-spacing: 0.5px;
    }

    .pdf-content-exp .doc-title-exp {
        font-size: 0.85rem;
        font-weight: 700;
        color: #059669;
        margin-top: 4px;
        background: #D1FAE5;
        padding: 4px 16px;
        border-radius: 20px;
        display: inline-block;
    }

    .pdf-content-exp .pdf-section-title-exp {
        font-weight: 700;
        font-size: 0.95rem;
        color: #059669;
        border-bottom: 2px solid #34D399;
        padding-bottom: 4px;
        margin: 6px 0 4px 0;
    }

    .pdf-content-exp .pdf-table-exp {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        margin: 4px 0;
    }

    .pdf-content-exp .pdf-table-exp th {
        background: #059669;
        color: white;
        padding: 4px 8px;
        text-align: left;
        font-size: 10px;
        text-transform: uppercase;
        border: 1px solid #047857;
    }

    .pdf-content-exp .pdf-table-exp td {
        padding: 4px 8px;
        border-bottom: 1px solid #E2E8F0;
        font-size: 11px;
    }

    .pdf-content-exp .pdf-table-exp tr:nth-child(even) td {
        background: #F8FAFC;
    }

    .pdf-content-exp .pdf-footer-exp {
        margin-top: 12px;
        padding-top: 10px;
        border-top: 2px solid #E2E8F0;
    }

    .pdf-content-exp .footer-stamp-exp {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }

    .pdf-content-exp .stamp-box-exp {
        text-align: center;
        padding: 6px 14px;
        border: 3px solid #059669;
        border-radius: 10px;
        background: #D1FAE5;
        min-width: 150px;
    }

    .pdf-content-exp .stamp-title-exp {
        font-size: 10px;
        color: var(--page-text-secondary, #64748B);
        text-transform: uppercase;
        font-weight: 700;
    }

    .pdf-content-exp .stamp-name-exp {
        font-size: 14px;
        font-weight: 800;
        color: #059669;
    }

    .pdf-content-exp .stamp-line-exp {
        font-size: 12px;
        color: var(--page-text-secondary, #64748B);
        margin-top: 2px;
    }

    .pdf-content-exp .footer-bottom-exp {
        text-align: center;
        margin-top: 6px;
        font-size: 12px;
        color: #94A3B8;
    }

    /* ================================================================
       MESSAGE BOX
       ================================================================ */
    .message-box-exp {
        padding: 12px 18px;
        border-radius: 10px;
        margin-bottom: 16px;
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideDownExp 0.4s ease;
    }

    @keyframes slideDownExp {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .message-box-exp.success {
        background: #D1FAE5;
        color: #065F46;
        border: 1px solid #6EE7B7;
    }

    .message-box-exp.error {
        background: #FEE2E2;
        color: #991B1B;
        border: 1px solid #FCA5A5;
    }

    [data-theme="dark"] .message-box-exp.success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }

    [data-theme="dark"] .message-box-exp.error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-exp {
        padding: 14px 0;
        border-top: 1px solid var(--page-border, #E2E8F0);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-exp .footer-brand-exp {
        color: #059669;
        font-weight: 600;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .stats-grid-exp { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-exp { padding: 20px; }
        .page-header-exp .page-title-exp { font-size: 1.3rem; }
        .filter-section-exp { padding: 12px 16px; }
        .stats-grid-exp { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .stat-card-exp { padding: 12px 14px; }
        .stat-card-exp .stat-number-exp { font-size: 1.4rem; }
        .view-grid-exp { grid-template-columns: 1fr; }
    }

    @media (max-width: 640px) {
        .stats-grid-exp { grid-template-columns: repeat(2, 1fr); gap: 6px; }
        .stat-card-exp { padding: 8px 10px; }
        .stat-card-exp .stat-number-exp { font-size: 1.1rem; }
        .filter-row-exp { flex-direction: column; align-items: stretch; }
    }

    @media (max-width: 400px) {
        .stats-grid-exp { grid-template-columns: 1fr; }
    }

    @media print {
        .btn-exp, .filter-section-exp, .page-header-exp .btn-outline-light-exp { display: none !important; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-exp">
        <div>
            <h1 class="page-title-exp">
                <i class="fas fa-coins"></i>
                Expenses
                <span class="role-badge-display">ADMIN</span>
                <?php if ($selected_branch_id > 0): ?>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                    </span>
                <?php endif; ?>
                <span class="header-badge-exp">
                    <i class="fas fa-list"></i> <?= $all_expenses ?> Total
                </span>
            </h1>
            <p class="page-subtitle-exp">
                <i class="fas fa-arrow-right"></i>
                Manage all branch expenses
                <?php if ($date_filter !== 'all'): ?>
                    <span class="header-badge-exp" style="background:rgba(251,191,36,0.15);">
                        <i class="fas fa-calendar"></i> <?= ucfirst($date_filter) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="openAddModal()" class="btn-outline-light-exp" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.2);color:#34D399;">
                <i class="fas fa-plus-circle"></i> Add Expense
            </button>
            <button onclick="generatePDF()" class="btn-outline-light-exp" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>

    <!-- STATS CARDS - 4 -->
    <div class="stats-grid-exp">
        <div class="stat-card-exp accent-blue" onclick="window.location.href='expenses.php?branch=<?= $selected_branch_id ?>&status=all'">
            <span class="stat-icon-exp">📋</span>
            <p class="stat-number-exp blue"><?= number_format($all_expenses) ?></p>
            <p class="stat-label-exp">All Expenses</p>
            <p class="stat-sub-exp"><?= $currency ?> <?= number_format($all_amount) ?></p>
        </div>
        <div class="stat-card-exp accent-yellow" onclick="window.location.href='expenses.php?branch=<?= $selected_branch_id ?>&status=pending'">
            <span class="stat-icon-exp">⏳</span>
            <p class="stat-number-exp yellow"><?= number_format($pending_expenses) ?></p>
            <p class="stat-label-exp">Pending</p>
            <p class="stat-sub-exp"><?= $currency ?> <?= number_format($pending_amount) ?></p>
        </div>
        <div class="stat-card-exp accent-green" onclick="window.location.href='expenses.php?branch=<?= $selected_branch_id ?>&status=paid'">
            <span class="stat-icon-exp">✅</span>
            <p class="stat-number-exp green"><?= number_format($paid_expenses) ?></p>
            <p class="stat-label-exp">Paid</p>
            <p class="stat-sub-exp"><?= $currency ?> <?= number_format($paid_amount) ?></p>
        </div>
        <div class="stat-card-exp accent-red" onclick="window.location.href='expenses.php?branch=<?= $selected_branch_id ?>&status=cancelled'">
            <span class="stat-icon-exp">❌</span>
            <p class="stat-number-exp red"><?= number_format($cancelled_expenses) ?></p>
            <p class="stat-label-exp">Cancelled</p>
            <p class="stat-sub-exp"><?= $currency ?> <?= number_format($cancelled_amount) ?></p>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div class="message-box-exp <?= $message_type === 'success' ? 'success' : 'error' ?>" style="max-width:1200px;margin:0 auto 12px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <span><?= $message ?></span>
        </div>
    <?php endif; ?>

    <!-- DATE FILTERS -->
    <div class="filter-section-exp">
        <div class="filter-row-exp" style="margin-bottom:6px;">
            <span class="filter-label-exp"><i class="fas fa-calendar"></i> Date:</span>
            <a href="?branch=<?= $selected_branch_id ?>&date_filter=daily<?= $filter_status !== 'all' ? '&status=' . urlencode($filter_status) : '' ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn-exp <?= $date_filter === 'daily' ? 'active' : '' ?>">📅 Daily</a>
            <a href="?branch=<?= $selected_branch_id ?>&date_filter=week<?= $filter_status !== 'all' ? '&status=' . urlencode($filter_status) : '' ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn-exp <?= $date_filter === 'week' ? 'active' : '' ?>">📅 Week</a>
            <a href="?branch=<?= $selected_branch_id ?>&date_filter=monthly<?= $filter_status !== 'all' ? '&status=' . urlencode($filter_status) : '' ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn-exp <?= $date_filter === 'monthly' ? 'active' : '' ?>">📅 Monthly</a>
            <a href="?branch=<?= $selected_branch_id ?>&date_filter=3months<?= $filter_status !== 'all' ? '&status=' . urlencode($filter_status) : '' ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn-exp <?= $date_filter === '3months' ? 'active' : '' ?>">📅 3 Months</a>
            <a href="?branch=<?= $selected_branch_id ?>&date_filter=6months<?= $filter_status !== 'all' ? '&status=' . urlencode($filter_status) : '' ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn-exp <?= $date_filter === '6months' ? 'active' : '' ?>">📅 6 Months</a>
            <a href="?branch=<?= $selected_branch_id ?>&date_filter=1year<?= $filter_status !== 'all' ? '&status=' . urlencode($filter_status) : '' ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn-exp <?= $date_filter === '1year' ? 'active' : '' ?>">📅 1 Year</a>
            <a href="?branch=<?= $selected_branch_id ?>&date_filter=all<?= $filter_status !== 'all' ? '&status=' . urlencode($filter_status) : '' ?><?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn-exp <?= $date_filter === 'all' ? 'active' : '' ?>">📅 All</a>
        </div>
        
        <div class="filter-row-exp" style="border-top:1px solid var(--page-border);padding-top:8px;margin-top:4px;">
            <span class="filter-label-exp"><i class="fas fa-calendar-range"></i> Range:</span>
            <form method="GET" class="filter-row-exp" style="flex:1;gap:6px;">
                <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
                <input type="hidden" name="date_filter" value="custom">
                <?php if ($filter_status !== 'all'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>"><?php endif; ?>
                <?php if (!empty($filter_category)): ?><input type="hidden" name="category" value="<?= htmlspecialchars($filter_category) ?>"><?php endif; ?>
                <?php if (!empty($search)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                <input type="date" name="date_from" class="filter-input-exp" value="<?= htmlspecialchars($date_from) ?>" placeholder="From">
                <span style="color:var(--page-text-secondary);font-size:0.7rem;">to</span>
                <input type="date" name="date_to" class="filter-input-exp" value="<?= htmlspecialchars($date_to) ?>" placeholder="To">
                <button type="submit" class="btn-search-exp"><i class="fas fa-search"></i> Apply</button>
                <?php if (!empty($date_from) && !empty($date_to)): ?>
                    <a href="?branch=<?= $selected_branch_id ?>&date_filter=all" class="btn-reset-exp"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- STATUS & CATEGORY FILTERS -->
    <div class="filter-section-exp">
        <div class="filter-row-exp">
            <span class="filter-label-exp"><i class="fas fa-filter"></i> Status:</span>
            <a href="?branch=<?= $selected_branch_id ?>&date_filter=<?= $date_filter ?>&status=all<?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn-exp <?= $filter_status === 'all' ? 'active' : '' ?>">📋 All</a>
            <a href="?branch=<?= $selected_branch_id ?>&date_filter=<?= $date_filter ?>&status=pending<?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn-exp <?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ Pending</a>
            <a href="?branch=<?= $selected_branch_id ?>&date_filter=<?= $date_filter ?>&status=paid<?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn-exp <?= $filter_status === 'paid' ? 'active' : '' ?>">✅ Paid</a>
            <a href="?branch=<?= $selected_branch_id ?>&date_filter=<?= $date_filter ?>&status=cancelled<?= !empty($filter_category) ? '&category=' . urlencode($filter_category) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn-exp <?= $filter_status === 'cancelled' ? 'active' : '' ?>">❌ Cancelled</a>
            
            <?php if (!empty($categories)): ?>
            <select class="filter-input-exp" style="flex:0 1 auto;min-width:120px;max-width:180px;" onchange="window.location.href='?branch=<?= $selected_branch_id ?>&date_filter=<?= $date_filter ?>&status=<?= $filter_status ?>&category='+this.value<?= !empty($search) ? "+'&search=" . urlencode($search) . "'" : '' ?>">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $filter_category === $cat['category'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['category']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            
            <div style="flex:1;"></div>
            
            <form method="GET" class="filter-row-exp" style="flex:1;gap:6px;max-width:350px;">
                <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
                <input type="hidden" name="date_filter" value="<?= htmlspecialchars($date_filter) ?>">
                <?php if ($filter_status !== 'all'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>"><?php endif; ?>
                <?php if (!empty($filter_category)): ?><input type="hidden" name="category" value="<?= htmlspecialchars($filter_category) ?>"><?php endif; ?>
                <input type="text" name="search" id="searchInput" class="filter-input-exp" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:100px;">
                <button type="submit" class="btn-search-exp"><i class="fas fa-search"></i></button>
                <?php if (!empty($search) || $filter_status !== 'all' || !empty($filter_category)): ?>
                    <a href="expenses.php?branch=<?= $selected_branch_id ?>&date_filter=all" class="btn-reset-exp"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
            
            <button onclick="openAddModal()" class="btn-add-exp">
                <i class="fas fa-plus"></i> Add
            </button>
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-container-exp">
        <div class="table-scroll-exp">
            <table class="data-table-exp">
                <thead>
                    <tr>
                        <th style="width:30px;">#</th>
                        <th><i class="fas fa-receipt"></i> Expense #</th>
                        <th><i class="fas fa-store-alt"></i> Branch</th>
                        <th><i class="fas fa-tag"></i> Category</th>
                        <th><i class="fas fa-align-left"></i> Description</th>
                        <th style="text-align:center;"><i class="fas fa-money-bill"></i> Amount</th>
                        <th style="text-align:center;"><i class="fas fa-credit-card"></i> Method</th>
                        <th style="text-align:center;"><i class="fas fa-calendar"></i> Date</th>
                        <th style="text-align:center;"><i class="fas fa-info-circle"></i> Status</th>
                        <th style="text-align:center;min-width:140px;"><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($expenses) > 0): ?>
                        <?php $i = 1; foreach ($expenses as $exp): ?>
                            <tr>
                                <td style="text-align:center;"><?= $i++ ?></td>
                                <td>
                                    <span style="font-family:monospace;font-weight:600;color:#059669;font-size:0.7rem;">
                                        <?= htmlspecialchars($exp['expense_number']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status-exp" style="background:#E8F0FE;color:#0B5ED7;border-color:#0B5ED7;">
                                        <?= htmlspecialchars($exp['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status-exp" style="background:#EDE9FE;color:#7C3AED;border-color:#7C3AED;">
                                        <?= htmlspecialchars($exp['category']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:0.75rem;"><?= htmlspecialchars($exp['description']) ?></span>
                                    <?php if (!empty($exp['notes'])): ?>
                                        <div style="font-size:0.65rem;color:var(--page-text-secondary);">📝 <?= htmlspecialchars($exp['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;font-weight:700;color:#059669;">
                                    <?= $currency ?> <?= formatMoney($exp['amount']) ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge-status-exp" style="background:var(--page-hover);color:var(--page-text-secondary);border-color:var(--page-border);font-size:0.55rem;">
                                        <?= ucfirst(str_replace('_', ' ', $exp['payment_method'] ?? 'cash')) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;font-size:0.7rem;"><?= formatDateOnly($exp['payment_date']) ?></td>
                                <td style="text-align:center;">
                                    <span class="badge-status-exp badge-<?= getStatusBadgeClass($exp['status']) ?>-exp">
                                        <?= getStatusLabel($exp['status']) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <div class="action-buttons-exp">
                                        <a href="expenses.php?branch=<?= $selected_branch_id ?>&view=<?= $exp['id'] ?>" class="btn-exp btn-view-exp" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="#" class="btn-exp btn-edit-exp" title="Edit" onclick="openEditModal(<?= $exp['id'] ?>, <?= $exp['branch_id'] ?>, '<?= htmlspecialchars($exp['expense_number']) ?>', '<?= htmlspecialchars(addslashes($exp['category'])) ?>', '<?= htmlspecialchars(addslashes($exp['description'])) ?>', '<?= formatMoney($exp['amount']) ?>', '<?= $exp['payment_method'] ?? 'cash' ?>', '<?= $exp['payment_date'] ?>', '<?= $exp['status'] ?>', '<?= htmlspecialchars(addslashes($exp['receipt_number'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($exp['notes'] ?? '')) ?>')">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="expenses.php?branch=<?= $selected_branch_id ?>&delete=<?= $exp['id'] ?>" class="btn-exp btn-delete-exp" onclick="return confirm('Delete this expense?')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php if ($exp['status'] === 'pending'): ?>
                                            <a href="expenses.php?branch=<?= $selected_branch_id ?>&pay=<?= $exp['id'] ?>" class="btn-exp btn-pay-exp" onclick="return confirm('Mark this expense as paid?')" title="Mark as Paid">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10">
                                <div class="empty-state-exp">
                                    <i class="fas fa-coins"></i>
                                    <p>No expenses found</p>
                                    <p style="font-size:0.8rem;color:var(--page-text-muted);margin-top:4px;">Try adjusting your filters</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="table-footer-exp">
            <span>
                <i class="fas fa-list"></i> Showing <strong><?= count($expenses) ?></strong> expenses
                <span style="font-size:0.65rem;">| Total: <?= $currency ?> <?= formatMoney($all_amount) ?></span>
            </span>
            <span>
                <span style="background:#059669;color:white;padding:1px 10px;border-radius:16px;font-size:0.65rem;font-weight:600;"><?= $all_expenses ?></span> Total
            </span>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer-exp">
        <p>
            <span class="footer-brand-exp">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Expenses
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- VIEW EXPENSE MODAL -->
<!-- ================================================================ -->
<?php if ($view_data): ?>
<div class="modal-overlay-exp show" id="viewModal">
    <div class="modal-content-exp">
        <div class="modal-header-exp">
            <div class="modal-title-exp"><i class="fas fa-eye"></i> Expense Details</div>
            <a href="expenses.php?branch=<?= $selected_branch_id ?>&date_filter=<?= $date_filter ?>&status=<?= $filter_status ?>" class="modal-close-exp" style="text-decoration:none;">&times;</a>
        </div>
        
        <div class="view-grid-exp">
            <div class="view-item-exp">
                <div class="view-label-exp">Expense #</div>
                <div class="view-value-exp"><?= htmlspecialchars($view_data['expense_number']) ?></div>
            </div>
            <div class="view-item-exp">
                <div class="view-label-exp">Branch</div>
                <div class="view-value-exp"><?= htmlspecialchars($view_data['branch_name'] ?? 'N/A') ?></div>
            </div>
            <div class="view-item-exp">
                <div class="view-label-exp">Category</div>
                <div class="view-value-exp"><?= htmlspecialchars($view_data['category']) ?></div>
            </div>
            <div class="view-item-exp">
                <div class="view-label-exp">Amount</div>
                <div class="view-value-exp" style="color:#059669;font-weight:700;"><?= $currency ?> <?= formatMoney($view_data['amount']) ?></div>
            </div>
            <div class="view-item-exp full-width">
                <div class="view-label-exp">Description</div>
                <div class="view-value-exp"><?= htmlspecialchars($view_data['description']) ?></div>
            </div>
            <div class="view-item-exp">
                <div class="view-label-exp">Payment Method</div>
                <div class="view-value-exp"><?= ucfirst(str_replace('_', ' ', $view_data['payment_method'] ?? 'cash')) ?></div>
            </div>
            <div class="view-item-exp">
                <div class="view-label-exp">Payment Date</div>
                <div class="view-value-exp"><?= formatDateOnly($view_data['payment_date']) ?></div>
            </div>
            <div class="view-item-exp">
                <div class="view-label-exp">Status</div>
                <div class="view-value-exp">
                    <span class="badge-status-exp badge-<?= getStatusBadgeClass($view_data['status']) ?>-exp">
                        <?= getStatusLabel($view_data['status']) ?>
                    </span>
                </div>
            </div>
            <?php if (!empty($view_data['receipt_number'])): ?>
            <div class="view-item-exp">
                <div class="view-label-exp">Receipt #</div>
                <div class="view-value-exp"><?= htmlspecialchars($view_data['receipt_number']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($view_data['notes'])): ?>
            <div class="view-item-exp full-width">
                <div class="view-label-exp">Notes</div>
                <div class="view-value-exp"><?= htmlspecialchars($view_data['notes']) ?></div>
            </div>
            <?php endif; ?>
            <div class="view-item-exp full-width">
                <div class="view-label-exp">Created By</div>
                <div class="view-value-exp"><?= htmlspecialchars($view_data['created_by_name'] ?? 'Unknown') ?></div>
            </div>
            <div class="view-item-exp full-width">
                <div class="view-label-exp">Created At</div>
                <div class="view-value-exp"><?= formatDate($view_data['created_at']) ?></div>
            </div>
        </div>
        
        <div class="form-actions-exp">
            <a href="expenses.php?branch=<?= $selected_branch_id ?>&date_filter=<?= $date_filter ?>&status=<?= $filter_status ?>" class="btn-cancel-modal-exp">
                <i class="fas fa-times"></i> Close
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ================================================================ -->
<!-- ADD MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay-exp" id="addModal">
    <div class="modal-content-exp">
        <div class="modal-header-exp">
            <div class="modal-title-exp"><i class="fas fa-plus-circle"></i> Add New Expense</div>
            <button class="modal-close-exp" onclick="closeModal('addModal')">&times;</button>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_expense">
            <input type="hidden" name="branch_id" value="<?= $selected_branch_id ?>">
            
            <div class="form-group-exp">
                <label>Category <span class="required">*</span></label>
                <select name="category" class="form-control-exp" required>
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
                    <option value="Other">📌 Other</option>
                </select>
            </div>
            
            <div class="form-group-exp">
                <label>Description <span class="required">*</span></label>
                <input type="text" name="description" class="form-control-exp" placeholder="Enter description" required>
            </div>
            
            <div class="form-group-exp">
                <label>Amount (<?= $currency ?>) <span class="required">*</span></label>
                <input type="text" name="amount" class="form-control-exp" placeholder="0" required oninput="formatAmount(this)" onfocus="this.select()">
            </div>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group-exp">
                    <label>Payment Method</label>
                    <select name="payment_method" class="form-control-exp">
                        <option value="cash">💵 Cash</option>
                        <option value="m-pesa">📱 M-Pesa</option>
                        <option value="airtel_money">📱 Airtel Money</option>
                        <option value="tigo_pesa">📱 Tigo Pesa</option>
                        <option value="bank">🏦 Bank</option>
                        <option value="card">💳 Card</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group-exp">
                    <label>Payment Date <span class="required">*</span></label>
                    <input type="date" name="payment_date" class="form-control-exp" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group-exp">
                    <label>Status</label>
                    <select name="status" class="form-control-exp">
                        <option value="pending">⏳ Pending</option>
                        <option value="paid" selected>✅ Paid</option>
                        <option value="cancelled">❌ Cancelled</option>
                    </select>
                </div>
                <div class="form-group-exp">
                    <label>Receipt #</label>
                    <input type="text" name="receipt_number" class="form-control-exp" placeholder="Optional">
                </div>
            </div>
            
            <div class="form-group-exp">
                <label>Notes</label>
                <textarea name="notes" class="form-control-exp" rows="2" placeholder="Additional notes..."></textarea>
            </div>
            
            <div class="form-actions-exp">
                <button type="submit" class="btn-save-exp"><i class="fas fa-save"></i> Save Expense</button>
                <button type="button" class="btn-cancel-modal-exp" onclick="closeModal('addModal')"><i class="fas fa-times"></i> Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- EDIT MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay-exp" id="editModal">
    <div class="modal-content-exp">
        <div class="modal-header-exp">
            <div class="modal-title-exp"><i class="fas fa-edit"></i> Edit Expense</div>
            <button class="modal-close-exp" onclick="closeModal('editModal')">&times;</button>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_expense">
            <input type="hidden" name="expense_id" id="editExpenseId">
            <input type="hidden" name="branch_id" id="editBranchId" value="<?= $selected_branch_id ?>">
            
            <div class="form-group-exp">
                <label>Expense Number</label>
                <input type="text" id="editExpenseNumber" class="form-control-exp" disabled>
            </div>
            
            <div class="form-group-exp">
                <label>Category <span class="required">*</span></label>
                <select name="category" id="editCategory" class="form-control-exp" required>
                    <option value="">Select</option>
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
                    <option value="Other">📌 Other</option>
                </select>
            </div>
            
            <div class="form-group-exp">
                <label>Description <span class="required">*</span></label>
                <input type="text" name="description" id="editDescription" class="form-control-exp" required>
            </div>
            
            <div class="form-group-exp">
                <label>Amount (<?= $currency ?>) <span class="required">*</span></label>
                <input type="text" name="amount" id="editAmount" class="form-control-exp" required oninput="formatAmount(this)" onfocus="this.select()">
            </div>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group-exp">
                    <label>Payment Method</label>
                    <select name="payment_method" id="editPaymentMethod" class="form-control-exp">
                        <option value="cash">💵 Cash</option>
                        <option value="m-pesa">📱 M-Pesa</option>
                        <option value="airtel_money">📱 Airtel Money</option>
                        <option value="tigo_pesa">📱 Tigo Pesa</option>
                        <option value="bank">🏦 Bank</option>
                        <option value="card">💳 Card</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group-exp">
                    <label>Payment Date <span class="required">*</span></label>
                    <input type="date" name="payment_date" id="editPaymentDate" class="form-control-exp" required>
                </div>
            </div>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group-exp">
                    <label>Status</label>
                    <select name="status" id="editStatus" class="form-control-exp">
                        <option value="pending">⏳ Pending</option>
                        <option value="paid">✅ Paid</option>
                        <option value="cancelled">❌ Cancelled</option>
                    </select>
                </div>
                <div class="form-group-exp">
                    <label>Receipt #</label>
                    <input type="text" name="receipt_number" id="editReceiptNumber" class="form-control-exp" placeholder="Optional">
                </div>
            </div>
            
            <div class="form-group-exp">
                <label>Notes</label>
                <textarea name="notes" id="editNotes" class="form-control-exp" rows="2"></textarea>
            </div>
            
            <div class="form-actions-exp">
                <button type="submit" class="btn-save-exp"><i class="fas fa-save"></i> Update</button>
                <button type="button" class="btn-cancel-modal-exp" onclick="closeModal('editModal')"><i class="fas fa-times"></i> Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- PDF MODAL -->
<!-- ================================================================ -->
<div class="pdf-modal-overlay-exp" id="pdfModal">
    <div class="pdf-modal-exp">
        <div class="pdf-modal-header-exp">
            <div class="modal-title-exp">
                <i class="fas fa-file-pdf" style="color:rgba(255,255,255,0.8);"></i> Expenses Report
            </div>
            <div class="modal-actions-exp">
                <button onclick="downloadPDF()"><i class="fas fa-download"></i> Download</button>
                <button onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                <button onclick="closePDFModal()" style="background:rgba(220,38,38,0.3);border-color:rgba(220,38,38,0.2);"><i class="fas fa-times"></i> Close</button>
            </div>
        </div>
        <div class="pdf-modal-body-exp">
            <div class="pdf-content-exp" id="pdfContent"></div>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT (NO dark mode, NO sidebar, NO date-time) -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // FOOTER TIME ONLY
    // ================================================================
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    // ================================================================
    // FORMAT AMOUNT
    // ================================================================
    function formatAmount(input) {
        var raw = input.value.replace(/[^0-9]/g, '');
        if (raw === '') { input.value = ''; return; }
        var num = parseInt(raw, 10);
        if (isNaN(num)) { input.value = ''; return; }
        input.value = num.toLocaleString('en-US');
    }

    // ================================================================
    // MODAL
    // ================================================================
    function openAddModal() {
        document.getElementById('addModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
        document.body.style.overflow = 'auto';
    }
    
    document.querySelectorAll('.modal-overlay-exp').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        });
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay-exp.show').forEach(function(modal) {
                modal.classList.remove('show');
                document.body.style.overflow = 'auto';
            });
            closePDFModal();
        }
    });

    // ================================================================
    // EDIT MODAL
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
    // PDF GENERATION
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        
        var currency = '<?= $currency ?>';
        var branchName = '<?= htmlspecialchars($branch_name) ?>';
        var totalBills = <?= $all_expenses ?>;
        var totalAmount = <?= $all_amount ?>;
        var pendingCount = <?= $pending_expenses ?>;
        var paidCount = <?= $paid_expenses ?>;
        var cancelledCount = <?= $cancelled_expenses ?>;
        var filterDisplay = '<?= $date_filter === 'all' ? 'All Time' : ucfirst($date_filter) ?>';
        
        var expensesHtml = '';
        var counter = 1;
        <?php foreach ($expenses as $exp): ?>
            expensesHtml += `
                <tr>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;text-align:center;font-size:11px;">${counter}</td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:11px;font-weight:600;color:#059669;"><?= htmlspecialchars($exp['expense_number']) ?></td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:11px;"><?= htmlspecialchars($exp['branch_name'] ?? 'N/A') ?></td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:11px;"><?= htmlspecialchars($exp['category']) ?></td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:11px;"><?= htmlspecialchars($exp['description']) ?></td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:11px;font-weight:700;color:#059669;">${currency} <?= formatMoney($exp['amount']) ?></td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:10px;text-align:center;"><?= ucfirst(str_replace('_', ' ', $exp['payment_method'] ?? 'cash')) ?></td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:10px;text-align:center;"><?= date('M d, Y', strtotime($exp['payment_date'])) ?></td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:10px;text-align:center;"><span style="background:<?= $exp['status'] === 'paid' ? '#D1FAE5' : ($exp['status'] === 'pending' ? '#FEF3C7' : '#FEE2E2') ?>;color:<?= $exp['status'] === 'paid' ? '#059669' : ($exp['status'] === 'pending' ? '#D97706' : '#DC2626') ?>;padding:2px 8px;border-radius:12px;font-size:9px;font-weight:600;text-transform:capitalize;"><?= getStatusLabel($exp['status']) ?></span></td>
                </tr>
            `;
            counter++;
        <?php endforeach; ?>
        
        if (!expensesHtml) {
            expensesHtml = `<tr><td colspan="9" style="text-align:center;padding:20px;font-size:14px;color:#64748B;">No expenses found</td></tr>`;
        }
        
        var html = `
            <div class="pdf-header-exp">
                <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" onerror="this.style.display='none'">
                <div class="clinic-name-exp">BRAICK DISPENSARY</div>
                <div class="clinic-sub-exp">Tunajali Afya Yako</div>
                <div class="doc-title-exp">💰 Expenses Report - ${filterDisplay}</div>
            </div>
            
            <div style="margin-bottom:8px;">
                <div class="pdf-section-title-exp">📊 Summary</div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px;margin:4px 0;">
                    <div style="background:#D1FAE5;padding:6px 10px;border-radius:8px;text-align:center;border:1px solid #059669;">
                        <div style="font-size:18px;font-weight:700;color:#059669;">${totalBills}</div>
                        <div style="font-size:9px;color:#64748B;text-transform:uppercase;">📋 Total</div>
                    </div>
                    <div style="background:#FEF3C7;padding:6px 10px;border-radius:8px;text-align:center;border:1px solid #D97706;">
                        <div style="font-size:18px;font-weight:700;color:#D97706;">${pendingCount}</div>
                        <div style="font-size:9px;color:#64748B;text-transform:uppercase;">⏳ Pending</div>
                    </div>
                    <div style="background:#D1FAE5;padding:6px 10px;border-radius:8px;text-align:center;border:1px solid #059669;">
                        <div style="font-size:18px;font-weight:700;color:#059669;">${paidCount}</div>
                        <div style="font-size:9px;color:#64748B;text-transform:uppercase;">✅ Paid</div>
                    </div>
                    <div style="background:#FEE2E2;padding:6px 10px;border-radius:8px;text-align:center;border:1px solid #DC2626;">
                        <div style="font-size:18px;font-weight:700;color:#DC2626;">${cancelledCount}</div>
                        <div style="font-size:9px;color:#64748B;text-transform:uppercase;">❌ Cancelled</div>
                    </div>
                </div>
                <div style="background:#E8F0FE;padding:6px 10px;border-radius:6px;text-align:center;border:1px solid #0B5ED7;margin-top:4px;">
                    <div style="font-size:16px;font-weight:700;color:#0B5ED7;">💰 Total: ${currency} ${Number(totalAmount).toLocaleString()}</div>
                </div>
            </div>
            
            <div style="margin-bottom:8px;">
                <div class="pdf-section-title-exp">📋 Expenses List (${totalBills})</div>
                <table class="pdf-table-exp">
                    <thead>
                        <tr>
                            <th style="text-align:center;">#</th>
                            <th>Expense #</th>
                            <th>Branch</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th style="text-align:right;">Amount</th>
                            <th style="text-align:center;">Method</th>
                            <th style="text-align:center;">Date</th>
                            <th style="text-align:center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>${expensesHtml}</tbody>
                </table>
            </div>
            
            <div class="pdf-footer-exp">
                <div class="footer-stamp-exp">
                    <div style="font-size:14px;color:#64748B;">
                        <span>Generated by: <?= htmlspecialchars($user_full_name) ?></span>
                        <span style="margin-left:14px;">Date: <?= date('F d, Y') ?></span>
                    </div>
                    <div class="stamp-box-exp">
                        <div class="stamp-title-exp">Official Stamp</div>
                        <div class="stamp-name-exp">BRAICK DISPENSARY</div>
                        <div class="stamp-line-exp">Approved By: _____________</div>
                    </div>
                </div>
                <div class="footer-bottom-exp">
                    Braick Dispensary • Generated on <?= date('F d, Y h:i:s A') ?>
                </div>
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
        if (typeof html2pdf === 'undefined') {
            alert('PDF library loading... Please try again.');
            return;
        }
        var opt = {
            margin: [8, 8, 8, 8],
            filename: 'Expenses_Report_<?= date('Y-m-d') ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', logging: false },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['css', 'legacy'] }
        };
        html2pdf().set(opt).from(element).save();
    }

    // ================================================================
    // SEARCH ENTER KEY
    // ================================================================
    document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') this.form.submit();
    });

    <?php if ($message && $message_type): ?>
        console.log('%c<?= $message_type === 'success' ? '✅' : '❌' ?> <?= addslashes($message) ?>', 'font-size:13px; color:<?= $message_type === 'success' ? '#059669' : '#DC2626' ?>;');
    <?php endif; ?>

    console.log('%c💰 Braick - Expenses', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c✅ NO duplicate dark mode JavaScript', 'font-size:13px; color:#059669;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
    console.log('%c📊 All: <?= $all_expenses ?> | Pending: <?= $pending_expenses ?> | Paid: <?= $paid_expenses ?> | Cancelled: <?= $cancelled_expenses ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>