<?php
// ================================================================
// FILE: frontend/pages/admin/lab_tests.php
// ADMIN - VIEW ALL LAB TESTS
// BRAICK DISPENSARY
// WITH FULL DELETE FUNCTIONALITY
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

// ================================================================
// ROLE CHECK - ONLY ADMIN CAN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET FILTERS
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$patient_search = isset($_GET['patient']) ? trim($_GET['patient']) : '';

// ================================================================
// HANDLE DELETE - POST REQUEST
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_lab_test']) && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    $branch_param = isset($_POST['branch']) ? $_POST['branch'] : 'all';
    
    if ($delete_id > 0) {
        try {
            // Check if test exists
            $stmt = $db->prepare("SELECT * FROM lab_tests WHERE id = ?");
            $stmt->execute([$delete_id]);
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$test) {
                header('Location: lab_tests.php?branch=' . urlencode($branch_param) . '&error=notfound');
                exit;
            }
            
            // Check if test has paid bill
            $stmt = $db->prepare("
                SELECT b.id, b.status 
                FROM bill_items bi
                LEFT JOIN bills b ON bi.bill_id = b.id
                WHERE bi.reference_id = ? AND bi.reference_type = 'lab_test' AND b.status = 'paid'
                LIMIT 1
            ");
            $stmt->execute([$delete_id]);
            if ($stmt->fetch()) {
                header('Location: lab_tests.php?branch=' . urlencode($branch_param) . '&error=cannot_delete_paid');
                exit;
            }
            
            $db->beginTransaction();
            
            // Delete bill items
            $stmt = $db->prepare("DELETE FROM bill_items WHERE reference_id = ? AND reference_type = 'lab_test'");
            $stmt->execute([$delete_id]);
            
            // Delete equipment links
            $stmt = $db->prepare("DELETE FROM lab_test_equipment WHERE lab_test_id = ?");
            $stmt->execute([$delete_id]);
            
            // Delete the test
            $stmt = $db->prepare("DELETE FROM lab_tests WHERE id = ?");
            $stmt->execute([$delete_id]);
            
            // Update visit status if needed
            $visit_id = $test['visit_id'] ?? null;
            if ($visit_id) {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE visit_id = ?");
                $stmt->execute([$visit_id]);
                $remaining = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                
                if ($remaining == 0) {
                    $stmt = $db->prepare("UPDATE visits SET status = 'assigned', updated_at = NOW() WHERE id = ? AND status = 'lab_test'");
                    $stmt->execute([$visit_id]);
                }
            }
            
            // Update bill totals
            if ($visit_id) {
                $stmt = $db->prepare("SELECT id FROM bills WHERE visit_id = ?");
                $stmt->execute([$visit_id]);
                $bill = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($bill) {
                    $stmt = $db->prepare("SELECT SUM(total_price) as total FROM bill_items WHERE bill_id = ? AND status != 'cancelled'");
                    $stmt->execute([$bill['id']]);
                    $subtotal = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
                    
                    $stmt = $db->prepare("SELECT total_discount FROM bills WHERE id = ?");
                    $stmt->execute([$bill['id']]);
                    $discount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_discount'] ?? 0);
                    
                    $total_amount = max(0, $subtotal - $discount);
                    
                    $stmt = $db->prepare("SELECT SUM(amount) as payment_total FROM payments WHERE bill_id = ?");
                    $stmt->execute([$bill['id']]);
                    $paid_amount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['payment_total'] ?? 0);
                    
                    $balance = $total_amount - $paid_amount;
                    
                    if ($total_amount == 0) {
                        $bill_status = 'pending';
                    } elseif ($balance <= 0 && $total_amount > 0) {
                        $bill_status = 'paid';
                    } elseif ($paid_amount > 0 && $balance > 0) {
                        $bill_status = 'partial';
                    } else {
                        $bill_status = 'pending';
                    }
                    
                    $stmt = $db->prepare("UPDATE bills SET subtotal = ?, total_amount = ?, paid_amount = ?, balance = ?, status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$subtotal, $total_amount, $paid_amount, $balance, $bill_status, $bill['id']]);
                }
            }
            
            $db->commit();
            header('Location: lab_tests.php?branch=' . urlencode($branch_param) . '&error=delete_success');
            exit;
            
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Delete lab test error: " . $e->getMessage());
            header('Location: lab_tests.php?branch=' . urlencode($branch_param) . '&error=database_error');
            exit;
        }
    } else {
        header('Location: lab_tests.php?branch=' . urlencode($branch_param) . '&error=invalid_id');
        exit;
    }
}

// ================================================================
// GET ERROR MESSAGE FROM URL
// ================================================================
$error = $_GET['error'] ?? '';
$error_message = '';
$show_error = false;
$error_message_type = 'error';

if ($error === 'invalid_id') {
    $error_message = '⚠️ Invalid test ID provided.';
    $show_error = true;
} elseif ($error === 'notfound') {
    $error_message = '⚠️ Test not found.';
    $show_error = true;
} elseif ($error === 'database_error') {
    $error_message = '⚠️ Database error occurred. Please try again.';
    $show_error = true;
} elseif ($error === 'cannot_delete_paid') {
    $error_message = '❌ Cannot delete: This lab test is already paid.';
    $show_error = true;
} elseif ($error === 'delete_success') {
    $error_message = '✅ Lab test deleted successfully!';
    $show_error = true;
    $error_message_type = 'success';
}

// ================================================================
// BUILD QUERY FOR LAB TESTS
// ================================================================
$sql = "
    SELECT 
        lt.*,
        p.id as patient_id,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        u.full_name as doctor_name,
        u2.full_name as technician_name,
        v.visit_number,
        v.visit_type,
        v.id as visit_id,
        b.name as branch_name
    FROM lab_tests lt
    LEFT JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN patients p ON lt.patient_id = p.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    LEFT JOIN users u2 ON lt.lab_technician_id = u2.id
    LEFT JOIN branches b ON lt.branch_id = b.id
    WHERE 1=1
";

$params = [];

// Apply filters
if ($selected_branch_id !== 'all') {
    $sql .= " AND lt.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

if ($status_filter !== 'all') {
    $sql .= " AND lt.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $sql .= " AND (lt.test_name LIKE ? OR p.full_name LIKE ? OR p.patient_id LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($patient_search)) {
    $sql .= " AND p.full_name LIKE ?";
    $params[] = "%$patient_search%";
}

if (!empty($date_from)) {
    $sql .= " AND DATE(lt.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $sql .= " AND DATE(lt.created_at) <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY lt.created_at DESC";

$lab_tests = [];
try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lab_tests = [];
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CALCULATE TOTALS
// ================================================================
$total_tests = count($lab_tests);
$pending_tests = 0;
$in_progress_tests = 0;
$completed_tests = 0;
$cancelled_tests = 0;
$total_revenue = 0;

foreach ($lab_tests as $test) {
    $status = $test['status'] ?? 'pending';
    if ($status === 'pending') $pending_tests++;
    elseif ($status === 'in_progress') $in_progress_tests++;
    elseif ($status === 'completed') $completed_tests++;
    elseif ($status === 'cancelled') $cancelled_tests++;
    $total_revenue += floatval($test['test_price'] ?? 0);
}

// ================================================================
// STATUS BADGE CLASS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'pending' => 'warning',
        'in_progress' => 'info',
        'completed' => 'success',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'pending' => 'fa-clock',
        'in_progress' => 'fa-spinner fa-spin',
        'completed' => 'fa-check-circle',
        'cancelled' => 'fa-times-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
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
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Tests - Braick Dispensary</title>
    
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
            --success-bg: #D1FAE5;
            
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-bg: #FEE2E2;
            
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            
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
        
        /* ================================================================
           TOP NAV - SHARED HEADER
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
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
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
        
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -5%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.03);
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
            transition: all 0.3s ease;
        }
        
        .page-header .header-badge:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
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
           ALERT / ERROR MESSAGE
           ================================================================ */
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeInUp 0.5s ease forwards;
        }
        
        .alert-danger {
            background: var(--danger-bg);
            border: 2px solid var(--danger);
            color: var(--danger-dark);
        }
        
        .alert-success {
            background: var(--success-bg);
            border: 2px solid var(--success);
            color: var(--success-dark);
        }
        
        .alert i {
            font-size: 1.2rem;
        }
        
        .alert .alert-close {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
            transition: opacity 0.3s;
            padding: 0 4px;
        }
        
        .alert .alert-close:hover {
            opacity: 1;
        }
        
        /* ================================================================
           STATS CARDS - BLUE BACKGROUND, WHITE TEXT
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--primary-gradient);
            border-radius: var(--radius);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.25);
            text-decoration: none;
            color: white;
            position: relative;
            overflow: hidden;
            border: none;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
            transition: all 0.5s ease;
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -20%;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
            transition: all 0.5s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-4px) scale(1.01);
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.4);
        }
        
        .stat-card:hover::before {
            transform: scale(1.3);
            right: -20%;
        }
        
        .stat-card:hover::after {
            transform: scale(1.3);
            bottom: -20%;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .stat-label {
            font-size: 0.65rem;
            color: rgba(255,255,255,0.8);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
            position: relative;
            z-index: 1;
        }
        
        .stat-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: white;
            margin: 0;
            line-height: 1.2;
            position: relative;
            z-index: 1;
        }
        
        .stat-sub {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.7);
            margin-top: 2px;
            position: relative;
            z-index: 1;
        }
        
        [data-theme="dark"] .stat-card {
            background: linear-gradient(135deg, #1D4ED8, #1E40AF);
            box-shadow: 0 4px 16px rgba(29, 78, 216, 0.3);
        }
        
        [data-theme="dark"] .stat-card:hover {
            box-shadow: 0 8px 32px rgba(29, 78, 216, 0.5);
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            color: white;
            letter-spacing: 0.02em;
        }
        
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        .badge-secondary { background: #64748B; }
        .badge-primary { background: var(--primary); }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        /* ================================================================
           FILTERS
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
        }
        
        .filter-bar select, .filter-bar input {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 8px 14px;
            font-size: 0.8rem;
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
            min-width: 150px;
        }
        
        .filter-bar select:focus, .filter-bar input:focus {
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
           DATA TABLE
           ================================================================ */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.8rem;
        }
        
        .data-table thead th {
            background: var(--primary-gradient);
            color: white;
            font-weight: 600;
            padding: 10px 12px;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: none;
            white-space: nowrap;
        }
        
        .data-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .data-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            transition: background 0.2s ease;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tbody tr.deleting {
            transition: all 0.4s ease;
            opacity: 0;
            transform: translateX(30px);
        }
        
        /* ================================================================
           ACTION BUTTONS - 3 ROWS (View, Edit, Delete)
           ================================================================ */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 3px;
            align-items: center;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.62rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            cursor: pointer;
            min-width: 55px;
            width: 100%;
            min-height: 26px;
            white-space: nowrap;
        }
        
        .btn-action i {
            font-size: 0.65rem;
        }
        
        .btn-action .btn-label {
            display: inline;
            font-size: 0.58rem;
        }
        
        .btn-action:hover {
            transform: translateY(-1px) scale(1.02);
        }
        
        .btn-action:active {
            transform: scale(0.95);
        }
        
        .btn-action:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        /* View Button - Top row (Blue) */
        .btn-view {
            background: var(--primary-bg);
            color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-view:hover {
            background: var(--primary-gradient);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.3);
        }
        
        [data-theme="dark"] .btn-view {
            background: #1E3A5F;
            color: #3B82F6;
            border-color: #3B82F6;
        }
        
        [data-theme="dark"] .btn-view:hover {
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            color: white;
        }
        
        /* Edit Button - Middle row (Green) - Only active when completed */
        .btn-edit {
            background: var(--success-bg);
            color: var(--success);
            border-color: var(--success);
        }
        
        .btn-edit:hover {
            background: var(--success);
            color: white;
            border-color: var(--success);
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
        }
        
        .btn-edit-disabled {
            background: var(--gray-100);
            color: var(--gray-400);
            border-color: var(--gray-300);
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .btn-edit-disabled:hover {
            transform: none !important;
            box-shadow: none !important;
            background: var(--gray-100) !important;
            color: var(--gray-400) !important;
        }
        
        [data-theme="dark"] .btn-edit {
            background: #064E3B;
            color: #34D399;
            border-color: #34D399;
        }
        
        [data-theme="dark"] .btn-edit:hover {
            background: #059669;
            color: white;
        }
        
        [data-theme="dark"] .btn-edit-disabled {
            background: #1E293B;
            color: #475569;
            border-color: #334155;
        }
        
        /* Delete Button - Bottom row (Red) */
        .btn-delete {
            background: var(--danger-bg);
            color: var(--danger);
            border-color: var(--danger);
        }
        
        .btn-delete:hover {
            background: var(--danger);
            color: white;
            border-color: var(--danger);
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
        }
        
        [data-theme="dark"] .btn-delete {
            background: #3A1A1A;
            color: #F87171;
            border-color: #F87171;
        }
        
        [data-theme="dark"] .btn-delete:hover {
            background: #DC2626;
            color: white;
        }
        
        @media (max-width: 480px) {
            .btn-action .btn-label {
                display: none;
            }
            .btn-action {
                padding: 3px 5px;
                min-width: 28px;
                min-height: 24px;
            }
            .btn-action i {
                font-size: 0.7rem;
            }
        }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            padding: 16px 24px;
            background: var(--bg-body);
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        [data-theme="dark"] .card-header {
            background: #0F172A;
        }
        
        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-title i {
            color: var(--primary);
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px dashed var(--border-color);
        }
        
        .empty-state i {
            font-size: 3.5rem;
            color: var(--border-color);
            margin-bottom: 16px;
        }
        
        .empty-state h3 {
            font-size: 1.2rem;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        /* ================================================================
           DELETE MODAL
           ================================================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            animation: fadeInOverlay 0.3s ease;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        @keyframes fadeInOverlay {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 32px;
            max-width: 450px;
            width: 90%;
            box-shadow: var(--shadow-xl);
            border: 2px solid var(--border-color);
            animation: modalSlideUp 0.3s ease;
        }
        
        @keyframes modalSlideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        [data-theme="dark"] .modal-content {
            background: var(--bg-card);
            border-color: var(--border-color);
        }
        
        .modal-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--danger-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 2rem;
            color: var(--danger);
        }
        
        .modal-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            text-align: center;
            margin-bottom: 8px;
        }
        
        .modal-message {
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 4px;
        }
        
        .modal-test-name {
            font-size: 0.75rem;
            color: var(--danger);
            text-align: center;
            font-weight: 600;
            margin-top: 8px;
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 24px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-danger:hover {
            background: var(--danger-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(220, 38, 38, 0.3);
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 99999;
            max-width: 420px;
            transform: translateY(120px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success {
            background: #059669;
        }
        
        .toast-custom.error {
            background: #DC2626;
        }
        
        .toast-custom.warning {
            background: #D97706;
        }
        
        .toast-custom.info {
            background: #0B5ED7;
        }
        
        .toast-custom .toast-icon {
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        
        .toast-custom .toast-content {
            flex: 1;
        }
        
        .toast-custom .toast-title {
            font-weight: 600;
            font-size: 0.85rem;
            margin: 0;
        }
        
        .toast-custom .toast-message {
            font-size: 0.75rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .toast-custom .toast-close {
            background: none;
            border: none;
            color: rgba(255,255,255,0.6);
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0 4px;
            transition: all 0.3s ease;
        }
        
        .toast-custom .toast-close:hover {
            color: white;
            transform: scale(1.1);
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
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .stat-card:hover .stat-icon {
            animation: pulse 0.5s ease;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar select, .filter-bar input { width: 100%; min-width: unset; }
            .data-table { font-size: 0.7rem; }
            .data-table thead th, .data-table td { padding: 6px 8px; }
            .btn-action {
                padding: 3px 8px;
                min-height: 24px;
                font-size: 0.6rem;
                min-width: 45px;
            }
            .btn-action i { font-size: 0.6rem; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .action-buttons { gap: 2px; }
            .modal-content { padding: 20px; }
        }
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .footer, #sidebarToggle, .filter-bar,
            .action-buttons, .modal-overlay, .toast-custom { display: none !important; }
            
            .main-content { margin: 0; padding: 20px; }
            .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .data-table thead th {
                background: #0B5ED7 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-header {
                background: #0B5ED7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-title, .page-subtitle, .role-badge-display, .header-badge {
                color: white !important;
            }
            .stat-card {
                background: #0B5ED7 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                border: none !important;
                box-shadow: none !important;
            }
            .stat-card .stat-label,
            .stat-card .stat-value,
            .stat-card .stat-sub,
            .stat-card .stat-icon {
                color: white !important;
            }
            .alert { display: none !important; }
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
            <input type="text" id="searchInput" placeholder="Search tests or patients..." value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot has-notif"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Lab Tests
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-vial"></i>
                <strong><?= $total_tests ?></strong> total tests
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-check-circle"></i> <?= $completed_tests ?> Completed
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-spinner fa-spin"></i> <?= $in_progress_tests ?> In Progress
                </span>
                <span class="header-badge" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);color:#F87171;">
                    <i class="fas fa-clock"></i> <?= $pending_tests ?> Pending
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_revenue, 0) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="add_lab_test.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-plus"></i> Add Test
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ERROR/SUCCESS MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($show_error && !empty($error_message)): ?>
        <div class="alert <?= $error_message_type === 'success' ? 'alert-success' : 'alert-danger' ?> animate-fade-in-up">
            <i class="fas <?= $error_message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $error_message ?>
            <button class="alert-close" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>" class="stat-card">
            <div class="stat-icon"><i class="fas fa-flask"></i></div>
            <div>
                <p class="stat-label">Total Tests</p>
                <p class="stat-value" id="statTotal"><?= number_format($total_tests) ?></p>
            </div>
        </a>
        
        <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>&status=completed" class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div>
                <p class="stat-label">Completed</p>
                <p class="stat-value" id="statCompleted"><?= number_format($completed_tests) ?></p>
            </div>
        </a>
        
        <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>&status=in_progress" class="stat-card">
            <div class="stat-icon"><i class="fas fa-spinner fa-spin"></i></div>
            <div>
                <p class="stat-label">In Progress</p>
                <p class="stat-value" id="statInProgress"><?= number_format($in_progress_tests) ?></p>
            </div>
        </a>
        
        <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>&status=pending" class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div>
                <p class="stat-label">Pending</p>
                <p class="stat-value" id="statPending"><?= number_format($pending_tests) ?></p>
            </div>
        </a>
        
        <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>&status=cancelled" class="stat-card">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div>
                <p class="stat-label">Cancelled</p>
                <p class="stat-value" id="statCancelled"><?= number_format($cancelled_tests) ?></p>
            </div>
        </a>
        
        <a href="reports.php?branch=<?= urlencode($selected_branch_id) ?>&type=lab" class="stat-card">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <p class="stat-label">Total Revenue</p>
                <p class="stat-value">TSh <?= number_format($total_revenue, 0) ?></p>
            </div>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-bar animate-fade-in-up" style="animation-delay:0.05s;">
        <form method="GET" class="flex flex-wrap gap-3 items-center w-full">
            <select name="branch" onchange="this.form.submit()" class="flex-1 min-w-[150px]">
                <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>All Branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="status" onchange="this.form.submit()" class="flex-1 min-w-[150px]">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            
            <input type="text" name="search" placeholder="Search by test or patient..." value="<?= htmlspecialchars($search) ?>" class="flex-1 min-w-[200px]">
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-outline">
                <i class="fas fa-times"></i> Clear
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- LAB TESTS TABLE -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.1s;" id="labTestsCard">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list"></i>
                Lab Tests List
                <span class="text-xs text-gray-400 font-normal" id="recordCount">(<?= $total_tests ?> records)</span>
            </h3>
            <a href="add_lab_test.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-primary" style="padding: 8px 18px; font-size: 0.8rem;">
                <i class="fas fa-plus"></i> Add New Test
            </a>
        </div>
        <div class="overflow-x-auto">
            <?php if (count($lab_tests) > 0): ?>
                <table class="data-table" id="labTestsTable">
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Technician</th>
                            <th>Status</th>
                            <th>Price</th>
                            <th>Created</th>
                            <th style="text-align:center; min-width:80px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="labTestsBody">
                        <?php foreach ($lab_tests as $test): 
                            $is_completed = ($test['status'] ?? '') === 'completed';
                        ?>
                            <tr data-test-id="<?= $test['id'] ?>" class="test-row">
                                <td class="font-medium text-sm"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if (!empty($test['patient_id']) && !empty($test['patient_name'])): ?>
                                        <a href="view_patient.php?id=<?= $test['patient_id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" class="text-blue-600 hover:underline">
                                            <?= htmlspecialchars($test['patient_name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($test['doctor_name'])): ?>
                                        Dr. <?= htmlspecialchars($test['doctor_name']) ?>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($test['technician_name'])): ?>
                                        <?= htmlspecialchars($test['technician_name']) ?>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($test['status'] ?? 'pending') ?>" style="font-size:0.6rem;padding:2px 10px;">
                                        <i class="fas <?= getStatusIcon($test['status'] ?? 'pending') ?>"></i>
                                        <?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'Pending')) ?>
                                    </span>
                                </td>
                                <td class="font-medium">TSh <?= number_format($test['test_price'] ?? 0, 0) ?></td>
                                <td class="text-xs text-gray-500"><?= date('M d, Y', strtotime($test['created_at'] ?? 'now')) ?></td>
                                <td>
                                    <!-- ================================================================ -->
                                    <!-- ACTION BUTTONS - 3 ROWS -->
                                    <!-- ================================================================ -->
                                    <div class="action-buttons">
                                        <!-- View -->
                                        <a href="view_lab_result.php?id=<?= $test['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" 
                                           class="btn-action btn-view" title="View Test">
                                            <i class="fas fa-eye"></i>
                                            <span class="btn-label">View</span>
                                        </a>
                                        
                                        <!-- Edit - Only if completed -->
                                        <?php if ($is_completed): ?>
                                            <a href="edit_lab_test.php?id=<?= $test['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" 
                                               class="btn-action btn-edit" title="Edit Test">
                                                <i class="fas fa-edit"></i>
                                                <span class="btn-label">Edit</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="btn-action btn-edit-disabled" title="Edit only available for completed tests">
                                                <i class="fas fa-edit"></i>
                                                <span class="btn-label">Edit</span>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <!-- Delete -->
                                        <button type="button" 
                                                class="btn-action btn-delete" 
                                                title="Delete Test" 
                                                onclick="deleteLabTest(<?= $test['id'] ?>, '<?= htmlspecialchars($test['test_name'] ?? 'Unknown') ?>', '<?= urlencode($selected_branch_id) ?>')">
                                            <i class="fas fa-trash"></i>
                                            <span class="btn-label">Delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-flask"></i>
                    <h3>No Lab Tests Found</h3>
                    <p class="text-gray-400"><?= !empty($search) ? 'No results match your search criteria.' : 'No lab tests have been created yet.' ?></p>
                    <?php if (!empty($search) || $status_filter !== 'all'): ?>
                        <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-primary mt-4">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php else: ?>
                        <a href="add_lab_test.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-primary mt-4">
                            <i class="fas fa-plus"></i> Add Lab Test
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Lab Tests - <?= $total_tests ?> tests
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- DELETE CONFIRMATION MODAL -->
<!-- ================================================================ -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-icon">
            <i class="fas fa-trash"></i>
        </div>
        <h3 class="modal-title">⚠️ Confirm Delete</h3>
        <p class="modal-message" id="deleteModalMessage">Are you sure you want to delete this lab test?</p>
        <p class="modal-test-name" id="deleteModalTestName">Test: Unknown</p>
        
        <form id="deleteForm" method="POST" action="lab_tests.php">
            <input type="hidden" name="delete_id" id="deleteId" value="">
            <input type="hidden" name="branch" id="deleteBranch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeDeleteModal()" style="padding:8px 24px;">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" name="delete_lab_test" class="btn-danger">
                    <i class="fas fa-trash"></i> Delete Permanently
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle toast-icon"></i>
    <div class="toast-content">
        <p class="toast-title" id="toastTitle">Notification</p>
        <p class="toast-message" id="toastMessage"></p>
    </div>
    <button class="toast-close" onclick="closeToast()">&times;</button>
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
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');

    function performSearch() {
        var query = searchInput.value.trim();
        var url = new URL(window.location.href);
        if (query.length > 0) {
            url.searchParams.set('search', query);
        } else {
            url.searchParams.delete('search');
        }
        window.location.href = url.toString();
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // BRANCH SWITCH
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    // ================================================================
    // DATE/TIME
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
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // DELETE MODAL
    // ================================================================
    function openDeleteModal(testId, testName, branch) {
        document.getElementById('deleteId').value = testId;
        document.getElementById('deleteModalTestName').textContent = 'Test: ' + testName;
        document.getElementById('deleteModalMessage').textContent = 'Are you sure you want to delete this lab test?\n\nThis will also remove:\n• Bill items linked to this test\n• Equipment linked to this test\n• Update visit status if no tests remain';
        document.getElementById('deleteBranch').value = branch || 'all';
        document.getElementById('deleteModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('show');
        document.body.style.overflow = '';
    }
    
    function deleteLabTest(testId, testName, branch) {
        // Check if test is paid (we'll check via AJAX)
        openDeleteModal(testId, testName, branch);
    }
    
    // Close modal on overlay click
    document.getElementById('deleteModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDeleteModal();
        }
    });

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        var toastIcon = toast.querySelector('.toast-icon');
        
        toast.className = 'toast-custom ' + type;
        toastIcon.className = 'fas ' + (type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle') + ' toast-icon';
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        
        setTimeout(function() {
            toast.classList.add('show');
        }, 50);
        
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 4000);
    }
    
    function closeToast() {
        var toast = document.getElementById('toast');
        toast.classList.remove('show');
        setTimeout(function() {
            toast.style.display = 'none';
        }, 400);
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
        }
    });

    // ================================================================
    // CONSOLE LOGS
    // ================================================================
    console.log('%c🧪 Braick Dispensary - Lab Tests (FULL FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Tests: <?= $total_tests ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Completed: <?= $completed_tests ?>', 'font-size:13px; color:#059669;');
    console.log('%c⏳ In Progress: <?= $in_progress_tests ?>', 'font-size:13px; color:#F59E0B;');
    console.log('%c⏰ Pending: <?= $pending_tests ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue, 0) ?>', 'font-size:13px; color:#0D9488;');
    console.log('%c🗑️ Delete: Working with modal confirmation', 'font-size:13px; color:#EF4444; font-weight:bold;');
    console.log('%c🔘 Action buttons: View ↑, Edit (green, only if completed), Delete ↓', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>