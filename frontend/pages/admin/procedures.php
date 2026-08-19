<?php
// ================================================================
// FILE: frontend/pages/admin/procedures.php
// ADMIN - VIEW ALL PROCEDURES WITH TOOLS AND BILL ITEMS
// FIXED: Patient names now show correctly from patient_bills
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
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';

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
// FUNCTION FORMAT CURRENCY
// ================================================================
function formatMoney($amount) {
    if ($amount === null || $amount === '') {
        return '0';
    }
    return number_format((float)$amount, 0, '.', ',');
}

function getStatusBadgeClass($status) {
    return $status == 1 ? 'badge-success' : 'badge-danger';
}

function getStatusLabel($status) {
    return $status == 1 ? '✅ Active' : '❌ Inactive';
}

function formatDateOnly($date) {
    if (empty($date)) return 'N/A';
    return date('M d, Y', strtotime($date));
}

// ================================================================
// HANDLE ADD PROCEDURE
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_procedure') {
    $procedure_name = trim($_POST['procedure_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = (float)str_replace(',', '', $_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $branch_id = (int)($_POST['branch_id'] ?? $selected_branch_id);
    
    $errors = [];
    if (empty($procedure_name)) { $errors[] = 'Procedure name is required'; }
    if ($price <= 0) { $errors[] = 'Price must be greater than 0'; }
    if ($branch_id <= 0) { $errors[] = 'Branch is required'; }
    
    if (empty($errors)) {
        try {
            $code = 'PROC-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("
                INSERT INTO procedures (
                    procedure_name, procedure_code, category, branch_id, price, description, is_active, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $procedure_name,
                $code,
                $category,
                $branch_id,
                $price,
                $description,
                $is_active,
                $user_id
            ]);
            
            header('Location: procedures.php?branch=' . $branch_id . '&msg=add_success');
            exit;
            
        } catch (Exception $e) {
            header('Location: procedures.php?branch=' . $branch_id . '&msg=add_error');
            exit;
        }
    } else {
        header('Location: procedures.php?branch=' . $branch_id . '&msg=add_validation_error');
        exit;
    }
}

// ================================================================
// HANDLE UPDATE PROCEDURE
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_procedure') {
    $procedure_id = (int)($_POST['procedure_id'] ?? 0);
    $procedure_name = trim($_POST['procedure_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = (float)str_replace(',', '', $_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $branch_id = (int)($_POST['branch_id'] ?? $selected_branch_id);
    
    $errors = [];
    if (empty($procedure_name)) { $errors[] = 'Procedure name is required'; }
    if ($price <= 0) { $errors[] = 'Price must be greater than 0'; }
    
    if (empty($errors) && $procedure_id > 0) {
        try {
            $stmt = $db->prepare("
                UPDATE procedures 
                SET procedure_name = ?,
                    category = ?,
                    price = ?,
                    description = ?,
                    is_active = ?,
                    updated_at = NOW()
                WHERE id = ? AND branch_id = ?
            ");
            $stmt->execute([
                $procedure_name,
                $category,
                $price,
                $description,
                $is_active,
                $procedure_id,
                $branch_id
            ]);
            
            header('Location: procedures.php?branch=' . $branch_id . '&msg=update_success');
            exit;
            
        } catch (Exception $e) {
            header('Location: procedures.php?branch=' . $branch_id . '&msg=update_error');
            exit;
        }
    } else {
        header('Location: procedures.php?branch=' . $branch_id . '&msg=update_validation_error');
        exit;
    }
}

// ================================================================
// HANDLE DELETE PROCEDURE
// ================================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $procedure_id = (int)$_GET['delete'];
    $branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
    
    try {
        $stmt = $db->prepare("DELETE FROM procedures WHERE id = ?");
        $stmt->execute([$procedure_id]);
        
        if ($stmt->rowCount() > 0) {
            header('Location: procedures.php?branch=' . $branch_id . '&msg=delete_success');
            exit;
        } else {
            header('Location: procedures.php?branch=' . $branch_id . '&msg=delete_error');
            exit;
        }
        
    } catch (Exception $e) {
        header('Location: procedures.php?branch=' . $branch_id . '&msg=delete_error');
        exit;
    }
}

// ================================================================
// HANDLE TOGGLE STATUS
// ================================================================
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $procedure_id = (int)$_GET['toggle'];
    $branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
    
    try {
        $stmt = $db->prepare("SELECT is_active FROM procedures WHERE id = ?");
        $stmt->execute([$procedure_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current) {
            $new_status = $current['is_active'] == 1 ? 0 : 1;
            $stmt = $db->prepare("UPDATE procedures SET is_active = ? WHERE id = ?");
            $stmt->execute([$new_status, $procedure_id]);
            
            header('Location: procedures.php?branch=' . $branch_id . '&msg=toggle_success');
            exit;
        } else {
            header('Location: procedures.php?branch=' . $branch_id . '&msg=toggle_error');
            exit;
        }
        
    } catch (Exception $e) {
        header('Location: procedures.php?branch=' . $branch_id . '&msg=toggle_error');
        exit;
    }
}

// ================================================================
// HANDLE ADD TOOL TO PROCEDURE
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_tool') {
    $procedure_id = (int)($_POST['procedure_id'] ?? 0);
    $tool_name = trim($_POST['tool_name'] ?? '');
    $price = (float)str_replace(',', '', $_POST['tool_price'] ?? 0);
    $branch_id = (int)($_POST['branch_id'] ?? $selected_branch_id);
    
    if (!empty($tool_name) && $procedure_id > 0) {
        try {
            $stmt = $db->prepare("
                INSERT INTO procedure_tools (procedure_name, tool_name, branch_id, price, is_active, created_at)
                VALUES (?, ?, ?, ?, 1, NOW())
            ");
            $proc_stmt = $db->prepare("SELECT procedure_name FROM procedures WHERE id = ?");
            $proc_stmt->execute([$procedure_id]);
            $proc = $proc_stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt->execute([
                $proc['procedure_name'] ?? 'Unknown',
                $tool_name,
                $branch_id,
                $price
            ]);
            
            header('Location: procedures.php?branch=' . $branch_id . '&msg=tool_add_success');
            exit;
            
        } catch (Exception $e) {
            header('Location: procedures.php?branch=' . $branch_id . '&msg=tool_add_error');
            exit;
        }
    } else {
        header('Location: procedures.php?branch=' . $branch_id . '&msg=tool_add_error');
        exit;
    }
}

// ================================================================
// HANDLE DELETE TOOL
// ================================================================
if (isset($_GET['delete_tool']) && is_numeric($_GET['delete_tool'])) {
    $tool_id = (int)$_GET['delete_tool'];
    $branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
    
    try {
        $stmt = $db->prepare("DELETE FROM procedure_tools WHERE id = ?");
        $stmt->execute([$tool_id]);
        
        header('Location: procedures.php?branch=' . $branch_id . '&msg=tool_delete_success');
        exit;
        
    } catch (Exception $e) {
        header('Location: procedures.php?branch=' . $branch_id . '&msg=tool_delete_error');
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
    $message = "✅ Procedure added successfully!";
    $message_type = 'success';
} elseif ($redirect_msg === 'add_error') {
    $message = "❌ Error adding procedure!";
    $message_type = 'error';
} elseif ($redirect_msg === 'add_validation_error') {
    $message = "❌ Please fill in all required fields correctly!";
    $message_type = 'error';
} elseif ($redirect_msg === 'update_success') {
    $message = "✅ Procedure updated successfully!";
    $message_type = 'success';
} elseif ($redirect_msg === 'update_error') {
    $message = "❌ Error updating procedure!";
    $message_type = 'error';
} elseif ($redirect_msg === 'update_validation_error') {
    $message = "❌ Please fill in all required fields correctly!";
    $message_type = 'error';
} elseif ($redirect_msg === 'delete_success') {
    $message = "✅ Procedure deleted successfully!";
    $message_type = 'success';
} elseif ($redirect_msg === 'delete_error') {
    $message = "❌ Error deleting procedure!";
    $message_type = 'error';
} elseif ($redirect_msg === 'toggle_success') {
    $message = "✅ Procedure status updated successfully!";
    $message_type = 'success';
} elseif ($redirect_msg === 'toggle_error') {
    $message = "❌ Error updating procedure status!";
    $message_type = 'error';
} elseif ($redirect_msg === 'tool_add_success') {
    $message = "✅ Tool added successfully!";
    $message_type = 'success';
} elseif ($redirect_msg === 'tool_add_error') {
    $message = "❌ Error adding tool!";
    $message_type = 'error';
} elseif ($redirect_msg === 'tool_delete_success') {
    $message = "✅ Tool deleted successfully!";
    $message_type = 'success';
} elseif ($redirect_msg === 'tool_delete_error') {
    $message = "❌ Error deleting tool!";
    $message_type = 'error';
}

// ================================================================
// BUILD QUERY FOR PROCEDURES
// ================================================================
$conditions = ["1=1"];
$params = [];

if ($selected_branch_id > 0) {
    $conditions[] = "p.branch_id = ?";
    $params[] = $selected_branch_id;
}

if (!empty($filter_category)) {
    $conditions[] = "p.category = ?";
    $params[] = $filter_category;
}

if (!empty($search)) {
    $conditions[] = "(p.procedure_name LIKE ? OR p.category LIKE ? OR p.procedure_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(" AND ", $conditions);

$sql = "
    SELECT 
        p.*,
        b.name as branch_name
    FROM procedures p
    LEFT JOIN branches b ON p.branch_id = b.id
    WHERE $where_clause
    ORDER BY p.procedure_name ASC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET TOOLS FOR EACH PROCEDURE
// ================================================================
$procedure_tools = [];
foreach ($procedures as $proc) {
    $stmt = $db->prepare("
        SELECT * FROM procedure_tools 
        WHERE procedure_name = ? AND branch_id = ?
        ORDER BY tool_name ASC
    ");
    $stmt->execute([$proc['procedure_name'], $proc['branch_id']]);
    $procedure_tools[$proc['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// GET BILL ITEMS FOR PROCEDURES - FIXED: Get patient names from patient_bills
// ================================================================
$bill_items = [];
$bill_conditions = ["bi.item_type = 'procedure'"];
$bill_params = [];

if ($selected_branch_id > 0) {
    $bill_conditions[] = "pb.branch_id = ?";
    $bill_params[] = $selected_branch_id;
}

$bill_where = implode(" AND ", $bill_conditions);

$bill_sql = "
    SELECT 
        bi.*,
        pb.patient_id as bill_patient_id,
        pb.bill_number,
        pb.status as bill_status,
        pb.branch_id as bill_branch_id,
        p.full_name as patient_name,
        p.patient_id as patient_number,
        p.phone as patient_phone
    FROM bill_items bi
    LEFT JOIN patient_bills pb ON bi.bill_id = pb.id
    LEFT JOIN patients p ON pb.patient_id = p.id
    WHERE $bill_where
    ORDER BY bi.created_at DESC
";

$stmt = $db->prepare($bill_sql);
$stmt->execute($bill_params);
$bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CALCULATE TOTAL PROCEDURE BILL AMOUNT
// ================================================================
$total_bill_items = count($bill_items);
$total_bill_amount = 0;
$procedure_totals = [];

foreach ($bill_items as $item) {
    $total_bill_amount += (float)$item['total_price'];
    
    $proc_name = $item['item_name'] ?? 'Unknown';
    if (!isset($procedure_totals[$proc_name])) {
        $procedure_totals[$proc_name] = 0;
    }
    $procedure_totals[$proc_name] += (float)$item['total_price'];
}

// ================================================================
// GET CATEGORIES FOR FILTER
// ================================================================
$categories = [];
try {
    $stmt = $db->prepare("SELECT DISTINCT category FROM procedures ORDER BY category");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// ================================================================
// GET SUMMARY STATISTICS
// ================================================================
$total_procedures = count($procedures);
$total_active = 0;
$total_inactive = 0;
$total_amount = 0;
$total_tools = 0;

foreach ($procedures as $proc) {
    if ($proc['is_active'] == 1) $total_active++;
    else $total_inactive++;
    $total_amount += (float)$proc['price'];
    $total_tools += count($procedure_tools[$proc['id']] ?? []);
}

// ================================================================
// GET PROCEDURE FOR EDIT
// ================================================================
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_procedure = null;
if ($edit_id > 0) {
    $stmt = $db->prepare("SELECT * FROM procedures WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_procedure = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>Procedures & Tools - Braick Dispensary</title>
    
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
            
            --info: #3B82F6;
            --info-bg: #DBEAFE;
            
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
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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
        .stat-card.active { background: linear-gradient(135deg, #059669, #047857); }
        .stat-card.inactive { background: linear-gradient(135deg, #DC2626, #991B1B); }
        .stat-card.tools { background: linear-gradient(135deg, #3B82F6, #1D4ED8); }
        .stat-card.bills { background: linear-gradient(135deg, #D97706, #B45309); }
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
            margin-bottom: 20px;
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
        
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-info { background: var(--info-bg); color: var(--info); border: 1px solid var(--info); }
        
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
        
        .btn-edit {
            background: #3B82F6;
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
            background: #2563EB;
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(59, 130, 246, 0.25);
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
        
        .btn-toggle {
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
        
        .btn-toggle:hover {
            background: var(--gray-600);
            transform: translateY(-1px);
        }
        
        .btn-toggle.active {
            background: var(--success);
        }
        
        .btn-toggle.active:hover {
            background: var(--success-dark);
        }
        
        .btn-toggle.inactive {
            background: var(--danger);
        }
        
        .btn-toggle.inactive:hover {
            background: var(--danger-dark);
        }
        
        .btn-tool-delete {
            background: #DC2626;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 600;
            font-size: 0.5rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }
        
        .btn-tool-delete:hover {
            background: #B91C1C;
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
        
        .filter-divider {
            width: 1px;
            height: 28px;
            background: var(--border-color);
            margin: 0 4px;
        }
        
        .tools-list {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            align-items: center;
        }
        
        .tool-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--info-bg);
            color: var(--info);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.55rem;
            font-weight: 500;
            border: 1px solid var(--info);
        }
        
        .tool-tag .tool-price {
            font-weight: 600;
            color: var(--info);
        }
        
        .btn-add-tool {
            background: var(--teal);
            color: white;
            padding: 1px 8px;
            border-radius: 12px;
            font-size: 0.5rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }
        
        .btn-add-tool:hover {
            background: var(--teal);
            opacity: 0.8;
            transform: scale(1.05);
        }
        
        .section-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-primary);
            padding: 10px 14px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section-title i {
            color: var(--primary);
        }
        
        .total-row {
            font-weight: 700;
            border-top: 3px solid var(--primary);
            background: var(--primary-bg);
        }
        
        .total-row td {
            padding: 8px 12px;
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
            <input type="text" id="searchInput" placeholder="Search procedures..." value="<?= htmlspecialchars($search) ?>">
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
        
        <span class="datetime" id="currentDateTime">
            <i class="far fa-calendar-alt mr-1"></i>
            <span id="dateDisplay"><?= date('M d, Y') ?></span>
            <span class="mx-1">|</span>
            <i class="far fa-clock mr-1"></i>
            <span id="timeDisplay"><?= date('h:i:s A') ?></span>
        </span>
        
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
                <i class="fas fa-syringe"></i>
                Procedures & Tools
                <span class="role-badge-display">ADMIN</span>
                <?php if ($selected_branch_id > 0): ?>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                    </span>
                <?php endif; ?>
                <span class="header-badge">
                    <i class="fas fa-list"></i> <?= $total_procedures ?> Procedures
                </span>
                <span class="header-badge" style="background:rgba(59,130,246,0.2);border-color:rgba(59,130,246,0.2);color:#60A5FA;">
                    <i class="fas fa-tools"></i> <?= $total_tools ?> Tools
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.2);color:#FBBF24;">
                    <i class="fas fa-file-invoice"></i> <?= $total_bill_items ?> Bills
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-arrow-right"></i>
                Manage procedures, tools, and view bill items
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.2);color:#34D399;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= formatMoney($total_bill_amount) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="openAddModal()" class="btn-outline-light" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.2);color:#34D399;">
                <i class="fas fa-plus-circle"></i> Add Procedure
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-row animate-fade-in-up">
        <div class="stat-card total">
            <div class="stat-icon"><i class="fas fa-syringe"></i></div>
            <div class="stat-number"><?= $total_procedures ?></div>
            <div class="stat-label">Total Procedures</div>
        </div>
        <div class="stat-card active">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?= $total_active ?></div>
            <div class="stat-label">✅ Active</div>
        </div>
        <div class="stat-card inactive">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-number"><?= $total_inactive ?></div>
            <div class="stat-label">❌ Inactive</div>
        </div>
        <div class="stat-card tools">
            <div class="stat-icon"><i class="fas fa-tools"></i></div>
            <div class="stat-number"><?= $total_tools ?></div>
            <div class="stat-label">🔧 Tools</div>
        </div>
        <div class="stat-card bills">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-number"><?= $total_bill_items ?></div>
            <div class="stat-label">📄 Bill Items</div>
        </div>
        <div class="stat-card amount">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-number">TSh <?= formatMoney($total_bill_amount) ?></div>
            <div class="stat-label">Total Bill Amount</div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section animate-fade-in-up" style="animation-delay:0.05s;">
        <form method="GET" action="" id="filterForm" class="w-full">
            <div class="filter-row">
                <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
                
                <?php if (!empty($categories)): ?>
                <select name="category" class="filter-input" style="min-width:130px;" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $filter_category === $cat['category'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                
                <span class="filter-divider"></span>
                
                <input type="text" name="search" class="filter-input" style="min-width:150px;flex:1;" placeholder="Search procedures..." value="<?= htmlspecialchars($search) ?>">
                
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Filter
                </button>
                
                <a href="procedures.php?branch=<?= $selected_branch_id ?>" class="btn-reset">
                    <i class="fas fa-times"></i> Reset
                </a>
                
                <button type="button" onclick="openAddModal()" class="btn-add">
                    <i class="fas fa-plus"></i> Add
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 1: PROCEDURES WITH TOOLS -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="section-title">
            <i class="fas fa-syringe"></i> Procedures & Tools
            <span style="font-size:0.6rem;font-weight:400;color:var(--text-secondary);margin-left:8px;">
                <?= $total_procedures ?> procedures | <?= $total_tools ?> tools
            </span>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:30px;"><i class="fas fa-hashtag"></i></th>
                        <th><i class="fas fa-code"></i> Code</th>
                        <th><i class="fas fa-syringe"></i> Procedure Name</th>
                        <th><i class="fas fa-tag"></i> Category</th>
                        <th><i class="fas fa-store-alt"></i> Branch</th>
                        <th style="text-align:center;"><i class="fas fa-money-bill"></i> Price</th>
                        <th style="text-align:center;"><i class="fas fa-tools"></i> Tools</th>
                        <th style="text-align:center;"><i class="fas fa-info-circle"></i> Status</th>
                        <th style="text-align:center;"><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($procedures) > 0): ?>
                        <?php $i = 1; foreach ($procedures as $proc): 
                            $tools = $procedure_tools[$proc['id']] ?? [];
                        ?>
                            <tr>
                                <td style="text-align:center;"><?= $i++ ?></td>
                                <td>
                                    <span class="font-mono font-semibold" style="color:var(--primary);font-size:0.65rem;">
                                        <?= htmlspecialchars($proc['procedure_code'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight:600;font-size:0.75rem;color:var(--text-primary);">
                                        <?= htmlspecialchars($proc['procedure_name']) ?>
                                    </span>
                                    <?php if (!empty($proc['description'])): ?>
                                        <div style="font-size:0.6rem;color:var(--text-secondary);">
                                            <?= htmlspecialchars(substr($proc['description'], 0, 60)) ?>
                                            <?= strlen($proc['description']) > 60 ? '...' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status" style="background:var(--purple-bg);color:var(--purple);border-color:var(--purple);">
                                        <?= htmlspecialchars($proc['category'] ?? 'Uncategorized') ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:0.65rem;color:var(--teal);">
                                        <?= htmlspecialchars($proc['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="text-align:center;font-weight:700;color:var(--primary);">
                                    TSh <?= formatMoney($proc['price']) ?>
                                </td>
                                <td style="text-align:center;">
                                    <div class="tools-list">
                                        <?php if (count($tools) > 0): ?>
                                            <?php foreach ($tools as $tool): ?>
                                                <span class="tool-tag">
                                                    <i class="fas fa-wrench"></i>
                                                    <?= htmlspecialchars($tool['tool_name']) ?>
                                                    <span class="tool-price">(TSh <?= formatMoney($tool['price']) ?>)</span>
                                                    <a href="procedures.php?delete_tool=<?= $tool['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                                       class="btn-tool-delete" 
                                                       onclick="return confirm('Delete this tool?')" 
                                                       title="Delete Tool">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span style="font-size:0.55rem;color:var(--text-secondary);">No tools</span>
                                        <?php endif; ?>
                                        <button onclick="openAddToolModal(<?= $proc['id'] ?>, '<?= htmlspecialchars($proc['procedure_name']) ?>')" 
                                                class="btn-add-tool" 
                                                title="Add Tool">
                                            <i class="fas fa-plus"></i> Tool
                                        </button>
                                    </div>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge-status <?= getStatusBadgeClass($proc['is_active']) ?>">
                                        <?= getStatusLabel($proc['is_active']) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <div class="action-buttons">
                                        <a href="procedures.php?edit=<?= $proc['id'] ?>&branch=<?= $selected_branch_id ?>&category=<?= urlencode($filter_category) ?>&search=<?= urlencode($search) ?>" class="btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        
                                        <a href="procedures.php?toggle=<?= $proc['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-toggle <?= $proc['is_active'] == 1 ? 'active' : 'inactive' ?>" title="Toggle Status">
                                            <i class="fas fa-<?= $proc['is_active'] == 1 ? 'pause' : 'play' ?>"></i>
                                            <?= $proc['is_active'] == 1 ? 'Deactivate' : 'Activate' ?>
                                        </a>
                                        
                                        <a href="procedures.php?delete=<?= $proc['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-delete" onclick="return confirm('Delete this procedure? This action cannot be undone!')" title="Delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-syringe"></i>
                                    <p>No procedures found</p>
                                    <?php if (!empty($search) || !empty($filter_category)): ?>
                                        <p class="sub">Try adjusting your filters</p>
                                    <?php else: ?>
                                        <p class="sub">Click "Add Procedure" to get started</p>
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
                <i class="fas fa-list"></i> Showing <strong><?= count($procedures) ?></strong> procedures
                <span class="text-xs" style="color:var(--text-secondary);">Tools: <?= $total_tools ?></span>
            </span>
            <span>
                <span class="count-badge"><?= $total_procedures ?></span> Total
                <span class="text-xs" style="color:var(--text-secondary);" id="updateTimeDisplay">Last update: <?= date('H:i:s') ?></span>
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 2: BILL ITEMS FOR PROCEDURES - FIXED: Shows patient names -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="section-title">
            <i class="fas fa-file-invoice"></i> Procedure Bill Items
            <span style="font-size:0.6rem;font-weight:400;color:var(--text-secondary);margin-left:8px;">
                <?= $total_bill_items ?> items | Total: TSh <?= formatMoney($total_bill_amount) ?>
            </span>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:30px;"><i class="fas fa-hashtag"></i></th>
                        <th><i class="fas fa-receipt"></i> Bill #</th>
                        <th><i class="fas fa-user"></i> Patient</th>
                        <th><i class="fas fa-syringe"></i> Procedure</th>
                        <th style="text-align:center;"><i class="fas fa-money-bill"></i> Price</th>
                        <th style="text-align:center;"><i class="fas fa-calendar"></i> Date</th>
                        <th style="text-align:center;"><i class="fas fa-info-circle"></i> Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($bill_items) > 0): ?>
                        <?php 
                        $i = 1; 
                        foreach ($bill_items as $item): 
                        ?>
                            <tr>
                                <td style="text-align:center;"><?= $i++ ?></td>
                                <td>
                                    <span class="font-mono font-semibold" style="color:var(--primary);font-size:0.65rem;">
                                        <?= htmlspecialchars($item['bill_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <!-- =================================================== -->
                                    <!-- FIXED: Patient name now shows correctly -->
                                    <!-- =================================================== -->
                                    <div style="font-weight:600;font-size:0.75rem;color:var(--text-primary);">
                                        <?php if (!empty($item['patient_name'])): ?>
                                            <?= htmlspecialchars($item['patient_name']) ?>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);">Unknown Patient</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:0.55rem;color:var(--text-secondary);">
                                        <?php if (!empty($item['patient_number'])): ?>
                                            <?= htmlspecialchars($item['patient_number']) ?>
                                        <?php else: ?>
                                            ID: <?= htmlspecialchars($item['bill_patient_id'] ?? 'N/A') ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight:600;font-size:0.7rem;color:var(--text-primary);">
                                        <?= htmlspecialchars($item['item_name']) ?>
                                    </span>
                                    <div style="font-size:0.5rem;color:var(--text-secondary);">
                                        <?= ucfirst($item['item_type']) ?>
                                        <?php if (!empty($item['description'])): ?>
                                            - <?= htmlspecialchars($item['description']) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="text-align:center;font-weight:700;color:var(--primary);">
                                    TSh <?= formatMoney($item['total_price']) ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="text-xs"><?= formatDateOnly($item['created_at']) ?></span>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge-status <?= ($item['is_paid'] ?? 0) ? 'badge-success' : 'badge-warning' ?>">
                                        <?= ($item['is_paid'] ?? 0) ? '✅ Paid' : '⏳ Pending' ?>
                                    </span>
                                    <div style="font-size:0.5rem;color:var(--text-secondary);">
                                        <?= htmlspecialchars($item['bill_status'] ?? 'N/A') ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- TOTAL ROW -->
                        <tr class="total-row">
                            <td colspan="4" style="text-align:right;font-size:0.75rem;color:var(--text-primary);">
                                <i class="fas fa-calculator" style="color:var(--primary);"></i> 
                                TOTAL PROCEDURE BILL AMOUNT
                            </td>
                            <td style="text-align:center;font-size:0.85rem;font-weight:700;color:var(--primary);">
                                TSh <?= formatMoney($total_bill_amount) ?>
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-file-invoice"></i>
                                    <p>No bill items found for procedures</p>
                                    <p class="sub">Bill items will appear when procedures are added to patient bills</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="table-footer">
            <span>
                <i class="fas fa-list"></i> Showing <strong><?= $total_bill_items ?></strong> bill items
                <span class="text-xs" style="color:var(--text-secondary);">Total: TSh <?= formatMoney($total_bill_amount) ?></span>
            </span>
            <span>
                <span class="count-badge"><?= $total_bill_items ?></span> Items
                <span class="count-badge" style="background:var(--primary);">TSh <?= formatMoney($total_bill_amount) ?></span>
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
            Procedures & Tools
            <span class="text-gray-300 mx-2">|</span>
            <span class="text-gray-400">👤 <?= htmlspecialchars($user_full_name) ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- ADD PROCEDURE MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="addModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-plus-circle"></i> Add New Procedure
            </div>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        
        <form method="POST" action="" id="procedureForm">
            <input type="hidden" name="action" value="add_procedure">
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
                <label>Procedure Name <span class="required">*</span></label>
                <input type="text" name="procedure_name" class="form-control" placeholder="e.g. Wound Dressing" required>
            </div>
            
            <div class="form-group">
                <label>Category <span class="required">*</span></label>
                <select name="category" class="form-control" required>
                    <option value="">Select Category</option>
                    <option value="Wound Care">🩹 Wound Care</option>
                    <option value="Surgery">🔬 Surgery</option>
                    <option value="Orthopedics">🦴 Orthopedics</option>
                    <option value="Diagnostic">📊 Diagnostic</option>
                    <option value="Administration">💉 Administration</option>
                    <option value="Procedure">📋 Procedure</option>
                    <option value="Other">📌 Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Price (TSh) <span class="required">*</span></label>
                <input type="text" 
                       name="price" 
                       id="priceInput" 
                       class="form-control" 
                       placeholder="0" 
                       required
                       oninput="formatAmount(this)"
                       onfocus="this.select()">
                <div style="font-size:0.65rem;color:var(--text-secondary);margin-top:2px;">
                    <i class="fas fa-info-circle"></i> Type numbers - commas added automatically
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Procedure description..."></textarea>
            </div>
            
            <div class="form-group" style="display:flex;align-items:center;gap:10px;">
                <input type="checkbox" name="is_active" id="isActive" checked style="width:18px;height:18px;accent-color:var(--primary);">
                <label for="isActive" style="margin:0;font-weight:500;font-size:0.8rem;color:var(--text-primary);">Active (available for use)</label>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Save Procedure
                </button>
                <button type="button" class="btn-cancel-modal" onclick="closeModal('addModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- ADD TOOL MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="addToolModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-tools"></i> Add Tool to Procedure
                <span style="font-size:0.6rem;font-weight:400;color:var(--text-secondary);margin-left:8px;" id="toolProcedureName"></span>
            </div>
            <button class="modal-close" onclick="closeModal('addToolModal')">&times;</button>
        </div>
        
        <form method="POST" action="" id="toolForm">
            <input type="hidden" name="action" value="add_tool">
            <input type="hidden" name="procedure_id" id="toolProcedureId">
            <input type="hidden" name="branch_id" value="<?= $selected_branch_id ?>">
            
            <div class="form-group">
                <label>Tool Name <span class="required">*</span></label>
                <input type="text" name="tool_name" class="form-control" placeholder="e.g. Syringe, Scalpel, Bandage" required>
            </div>
            
            <div class="form-group">
                <label>Tool Price (TSh) <span class="required">*</span></label>
                <input type="text" 
                       name="tool_price" 
                       id="toolPriceInput" 
                       class="form-control" 
                       placeholder="0" 
                       required
                       oninput="formatAmount(this)"
                       onfocus="this.select()">
                <div style="font-size:0.65rem;color:var(--text-secondary);margin-top:2px;">
                    <i class="fas fa-info-circle"></i> Type numbers - commas added automatically
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Add Tool
                </button>
                <button type="button" class="btn-cancel-modal" onclick="closeModal('addToolModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- EDIT PROCEDURE MODAL -->
<!-- ================================================================ -->
<?php if ($edit_procedure): ?>
<div class="modal-overlay show" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-edit"></i> Edit Procedure
                <span style="font-size:0.6rem;font-weight:400;color:var(--text-secondary);margin-left:8px;">
                    #<?= htmlspecialchars($edit_procedure['procedure_code'] ?? '') ?>
                </span>
            </div>
            <a href="procedures.php?branch=<?= $selected_branch_id ?>&category=<?= urlencode($filter_category) ?>&search=<?= urlencode($search) ?>" class="modal-close">&times;</a>
        </div>
        
        <form method="POST" action="" id="editForm">
            <input type="hidden" name="action" value="update_procedure">
            <input type="hidden" name="procedure_id" value="<?= $edit_procedure['id'] ?>">
            <input type="hidden" name="branch_id" value="<?= $edit_procedure['branch_id'] ?>">
            
            <div class="form-group">
                <label>Procedure Code</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($edit_procedure['procedure_code'] ?? 'N/A') ?>" disabled style="background:var(--gray-100);">
            </div>
            
            <div class="form-group">
                <label>Procedure Name <span class="required">*</span></label>
                <input type="text" name="procedure_name" class="form-control" value="<?= htmlspecialchars($edit_procedure['procedure_name']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Category <span class="required">*</span></label>
                <select name="category" class="form-control" required>
                    <option value="">Select Category</option>
                    <option value="Wound Care" <?= $edit_procedure['category'] == 'Wound Care' ? 'selected' : '' ?>>🩹 Wound Care</option>
                    <option value="Surgery" <?= $edit_procedure['category'] == 'Surgery' ? 'selected' : '' ?>>🔬 Surgery</option>
                    <option value="Orthopedics" <?= $edit_procedure['category'] == 'Orthopedics' ? 'selected' : '' ?>>🦴 Orthopedics</option>
                    <option value="Diagnostic" <?= $edit_procedure['category'] == 'Diagnostic' ? 'selected' : '' ?>>📊 Diagnostic</option>
                    <option value="Administration" <?= $edit_procedure['category'] == 'Administration' ? 'selected' : '' ?>>💉 Administration</option>
                    <option value="Procedure" <?= $edit_procedure['category'] == 'Procedure' ? 'selected' : '' ?>>📋 Procedure</option>
                    <option value="Other" <?= $edit_procedure['category'] == 'Other' ? 'selected' : '' ?>>📌 Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Price (TSh) <span class="required">*</span></label>
                <input type="text" 
                       name="price" 
                       id="editPriceInput" 
                       class="form-control" 
                       value="<?= formatMoney($edit_procedure['price']) ?>" 
                       required
                       oninput="formatAmount(this)"
                       onfocus="this.select()">
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Procedure description..."><?= htmlspecialchars($edit_procedure['description'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group" style="display:flex;align-items:center;gap:10px;">
                <input type="checkbox" name="is_active" id="editIsActive" <?= $edit_procedure['is_active'] == 1 ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--primary);">
                <label for="editIsActive" style="margin:0;font-weight:500;font-size:0.8rem;color:var(--text-primary);">Active (available for use)</label>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Update Procedure
                </button>
                <a href="procedures.php?branch=<?= $selected_branch_id ?>&category=<?= urlencode($filter_category) ?>&search=<?= urlencode($search) ?>" class="btn-cancel-modal">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

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
    // DARK MODE
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
        
        var saved = localStorage.getItem('darkMode');
        if (saved === null) {
            var cookieMatch = document.cookie.match(/dark_mode=([^;]+)/);
            saved = cookieMatch ? cookieMatch[1] : 'false';
        }
        applyDarkMode(saved === 'true');
        
        if (darkToggle) {
            darkToggle.addEventListener('click', function() {
                var isDark = htmlElement.getAttribute('data-theme') === 'dark';
                applyDarkMode(!isDark);
                window.dispatchEvent(new StorageEvent('storage', {
                    key: 'darkMode',
                    newValue: isDark ? 'false' : 'true'
                }));
            });
        }
        
        window.addEventListener('storage', function(e) {
            if (e.key === 'darkMode') {
                applyDarkMode(e.newValue === 'true');
            }
        });
    })();

    // ================================================================
    // DATE & TIME
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
    
    updateDateTime();
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
        url.searchParams.delete('edit');
        window.location.href = url.toString();
    }

    // ================================================================
    // MODAL
    // ================================================================
    function openAddModal() {
        document.getElementById('addModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(function() {
            var priceInput = document.getElementById('priceInput');
            if (priceInput) {
                priceInput.focus();
                priceInput.select();
            }
        }, 300);
    }
    
    function openAddToolModal(procedureId, procedureName) {
        document.getElementById('toolProcedureId').value = procedureId;
        document.getElementById('toolProcedureName').textContent = '- ' + procedureName;
        document.getElementById('addToolModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(function() {
            var toolPriceInput = document.getElementById('toolPriceInput');
            if (toolPriceInput) {
                toolPriceInput.focus();
                toolPriceInput.select();
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

    console.log('%c💉 Braick - Procedures & Tools', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:12px; color:#059669;');
    console.log('%c🏢 Branch: <?= $branch_name ?> (ID: <?= $selected_branch_id ?>)', 'font-size:12px; color:#059669;');
    console.log('%c📊 Total Procedures: <?= $total_procedures ?>', 'font-size:12px; color:#059669;');
    console.log('%c🔧 Total Tools: <?= $total_tools ?>', 'font-size:12px; color:#3B82F6;');
    console.log('%c📄 Total Bill Items: <?= $total_bill_items ?>', 'font-size:12px; color:#D97706;');
    console.log('%c💰 Total Bill Amount: TSh <?= formatMoney($total_bill_amount) ?>', 'font-size:12px; color:#059669;');
    console.log('%c👤 Patient names: Now showing correctly from patient_bills', 'font-size:12px; color:#34D399;');
</script>

</body>
</html>