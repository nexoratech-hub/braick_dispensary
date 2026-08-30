<?php
// ================================================================
// FILE: frontend/pages/admin/view_equipment.php
// ADMIN - VIEW EQUIPMENT DETAILS
// VIEW ALL EQUIPMENT INFORMATION WITH BATCH DETAILS
// SHOW LINKED LAB TESTS AND STOCK MOVEMENTS
// BRAICK DISPENSARY - BLUE THEME
// WITH SESSION MANAGEMENT & LOGIN PROTECTION
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
// GET PARAMETERS
// ================================================================
$equipment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$return_branch = isset($_GET['branch']) ? $_GET['branch'] : 'all';
$return_tab = isset($_GET['tab']) ? $_GET['tab'] : 'equipment';

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
// GET EQUIPMENT DETAILS
// ================================================================
$equipment = null;
$error_message = '';

try {
    $stmt = $db->prepare("
        SELECT 
            e.*,
            b.name as branch_name
        FROM medical_equipment e
        LEFT JOIN branches b ON e.branch_id = b.id
        WHERE e.id = ?
    ");
    $stmt->execute([$equipment_id]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$equipment) {
        $error_message = "Equipment not found. It may have been deleted.";
    }
} catch (Exception $e) {
    $error_message = "Error loading equipment: " . $e->getMessage();
    $equipment = null;
}

// ================================================================
// GET LINKED LAB TESTS
// ================================================================
$linked_tests = [];
$linked_count = 0;

if ($equipment) {
    try {
        $stmt = $db->prepare("
            SELECT 
                l.id,
                l.test_name,
                l.category,
                l.price,
                l.is_active,
                l.created_at,
                u.full_name as created_by_name
            FROM lab_test_equipment le
            INNER JOIN lab_tests_catalog l ON le.lab_test_id = l.id
            LEFT JOIN users u ON l.created_by = u.id
            WHERE le.equipment_id = ?
            ORDER BY l.test_name ASC
        ");
        $stmt->execute([$equipment_id]);
        $linked_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $linked_count = count($linked_tests);
    } catch (Exception $e) {
        $linked_tests = [];
        $linked_count = 0;
    }
}

// ================================================================
// GET STOCK MOVEMENTS
// ================================================================
$stock_movements = [];

if ($equipment) {
    try {
        $stmt = $db->prepare("
            SELECT 
                sm.*,
                u.full_name as performed_by_name,
                p.full_name as patient_name
            FROM stock_movements sm
            LEFT JOIN users u ON sm.performed_by = u.id
            LEFT JOIN patients p ON sm.patient_id = p.id
            WHERE sm.equipment_id = ?
            ORDER BY sm.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$equipment_id]);
        $stock_movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $stock_movements = [];
    }
}

// ================================================================
// GET BRANCHES FOR RETURN URL
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getStockStatus($quantity, $reorder_level) {
    if ($quantity <= 0) return ['class' => 'out', 'label' => 'Out of Stock', 'icon' => 'fa-times-circle'];
    if ($quantity <= $reorder_level) return ['class' => 'low', 'label' => 'Low Stock', 'icon' => 'fa-exclamation-triangle'];
    return ['class' => 'ok', 'label' => 'In Stock', 'icon' => 'fa-check-circle'];
}

function getExpiryStatus($expiry_date) {
    if (empty($expiry_date) || $expiry_date === '0000-00-00') {
        return ['class' => 'no-expiry', 'label' => 'No Expiry', 'icon' => 'fa-infinity', 'days' => null];
    }
    $days = floor((strtotime($expiry_date) - time()) / 86400);
    if ($days < 0) return ['class' => 'expired', 'label' => 'Expired', 'icon' => 'fa-skull', 'days' => $days];
    if ($days <= 30) return ['class' => 'expiring', 'label' => 'Expiring Soon', 'icon' => 'fa-clock', 'days' => $days];
    return ['class' => 'valid', 'label' => 'Valid', 'icon' => 'fa-check', 'days' => $days];
}

function getStatusBadge($status) {
    return $status === 'active' ? 'active' : 'inactive';
}

function getStatusLabel($status) {
    return $status === 'active' ? 'Active' : 'Inactive';
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
    <title>View Equipment - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #073B8A);
            
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
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
            
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-bg: #1E3A5F;
            --primary-gradient: linear-gradient(135deg, #2563EB, #1D4ED8);
            --primary-gradient-strong: linear-gradient(135deg, #1D4ED8, #1E40AF);
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
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
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER - BLUE BACKGROUND
           ================================================================ */
        .page-header {
            background: var(--primary-gradient-strong);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
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
           CARDS
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-title i { color: var(--primary); }
        
        /* ================================================================
           DETAIL GRID
           ================================================================ */
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }
        
        .detail-item {
            padding: 12px 16px;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
        }
        
        .detail-item .label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .detail-item .value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 2px;
        }
        
        .detail-item .value .badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        .badge-teal { background: var(--teal-bg); color: var(--teal); }
        
        /* ================================================================
           STOCK BADGE
           ================================================================ */
        .stock-badge {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .stock-badge.ok { background: var(--success-bg); color: var(--success); }
        .stock-badge.low { background: var(--warning-bg); color: var(--warning); }
        .stock-badge.out { background: var(--danger-bg); color: var(--danger); }
        
        /* ================================================================
           EXPIRY BADGE
           ================================================================ */
        .expiry-badge {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .expiry-badge.valid { background: var(--success-bg); color: var(--success); }
        .expiry-badge.expiring { background: var(--warning-bg); color: var(--warning); }
        .expiry-badge.expired { background: var(--danger-bg); color: var(--danger); }
        .expiry-badge.no-expiry { background: var(--gray-200); color: var(--gray-500); }
        
        /* ================================================================
           STATUS BADGE
           ================================================================ */
        .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .status-badge.active { background: var(--success-bg); color: var(--success); }
        .status-badge.inactive { background: var(--danger-bg); color: var(--danger); }
        
        /* ================================================================
           TABLE
           ================================================================ */
        .table-container {
            overflow-x: auto;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
        }
        
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 700px;
        }
        
        .table-container thead {
            background: var(--primary-gradient-strong);
            color: #ffffff;
        }
        
        .table-container thead th {
            padding: 10px 14px;
            text-align: left;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        
        .table-container thead th i { margin-right: 6px; opacity: 0.8; }
        
        .table-container tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table-container tbody tr:last-child { border-bottom: none; }
        .table-container tbody tr:hover { background: var(--primary-bg); }
        [data-theme="dark"] .table-container tbody tr:hover { background: #1E3A5F; }
        
        .table-container tbody td {
            padding: 10px 14px;
            vertical-align: middle;
            color: var(--text-primary);
        }
        
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 2.5rem;
            color: var(--gray-300);
            display: block;
            margin-bottom: 10px;
        }
        
        [data-theme="dark"] .empty-state i { color: var(--gray-600); }
        
        .batch-number {
            font-family: monospace;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 4px;
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        [data-theme="dark"] .batch-number {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .branch-tag {
            display: inline-block;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .branch-tag.all-branches {
            background: #FEF3C7;
            color: #D97706;
        }
        
        [data-theme="dark"] .branch-tag.all-branches {
            background: #3D2E0A;
            color: #FBBF24;
        }
        
        .movement-type {
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .movement-type.in { background: var(--success-bg); color: var(--success); }
        .movement-type.out { background: var(--danger-bg); color: var(--danger); }
        .movement-type.adjustment { background: var(--warning-bg); color: var(--warning); }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
        }
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.2);
        }
        .btn-danger:hover {
            background: var(--danger-dark);
            transform: translateY(-2px);
        }
        
        .btn-sm { padding: 4px 12px; font-size: 0.7rem; }
        
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
            font-weight: 700;
        }
        
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
            .detail-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .card { padding: 14px 16px; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
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
            <input type="text" id="searchInput" placeholder="Search equipment..." value="">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
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

    <?php if ($equipment && empty($error_message)): 
        $stock = getStockStatus($equipment['quantity'], $equipment['reorder_level']);
        $expiry = getExpiryStatus($equipment['expiry_date']);
    ?>
        <!-- ================================================================ -->
        <!-- PAGE HEADER -->
        <!-- ================================================================ -->
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-tools"></i>
                    <?= htmlspecialchars($equipment['equipment_name']) ?>
                    <span class="role-badge-display">DETAILS</span>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-hashtag"></i> #<?= $equipment['id'] ?>
                    </span>
                </h1>
                <p class="page-subtitle">
                    <i class="fas fa-tag"></i>
                    <strong><?= htmlspecialchars($equipment['category'] ?? 'Uncategorized') ?></strong>
                    <span class="header-badge">
                        <i class="fas fa-boxes"></i> <?= number_format($equipment['quantity']) ?> in stock
                    </span>
                    <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                        <i class="fas fa-calendar"></i> <?= $expiry['label'] ?>
                    </span>
                </p>
            </div>
            <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
                <a href="edit_equipment.php?id=<?= $equipment['id'] ?>&branch=<?= urlencode($return_branch) ?>" class="btn-outline-light">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="equipment_inventory.php?branch=<?= urlencode($return_branch) ?>&tab=<?= $return_tab ?>" class="btn-outline-light">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- ERROR MESSAGE -->
        <!-- ================================================================ -->
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error" style="padding:14px 20px;border-radius:var(--radius);margin-bottom:20px;display:flex;align-items:center;gap:12px;background:var(--danger-bg);color:var(--danger);border:1px solid var(--danger);">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- EQUIPMENT DETAILS -->
        <!-- ================================================================ -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle"></i> Equipment Details
                </h3>
                <div>
                    <span class="status-badge <?= getStatusBadge($equipment['status']) ?>">
                        <?= getStatusLabel($equipment['status']) ?>
                    </span>
                </div>
            </div>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="label"><i class="fas fa-tag"></i> Equipment Name</div>
                    <div class="value"><?= htmlspecialchars($equipment['equipment_name']) ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="label"><i class="fas fa-tag"></i> Category</div>
                    <div class="value"><?= htmlspecialchars($equipment['category'] ?? 'N/A') ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="label"><i class="fas fa-cube"></i> Unit</div>
                    <div class="value"><?= htmlspecialchars($equipment['unit'] ?? 'pcs') ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="label"><i class="fas fa-store-alt"></i> Branch</div>
                    <div class="value">
                        <?php if ($equipment['branch_id'] === null): ?>
                            <span class="branch-tag all-branches">🌐 All Branches</span>
                        <?php else: ?>
                            <span class="branch-tag"><?= htmlspecialchars($equipment['branch_name'] ?? 'N/A') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="label"><i class="fas fa-boxes"></i> Quantity</div>
                    <div class="value" style="font-size:1.3rem;">
                        <?= number_format($equipment['quantity']) ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="label"><i class="fas fa-chart-line"></i> Stock Status</div>
                    <div class="value">
                        <span class="stock-badge <?= $stock['class'] ?>">
                            <i class="fas <?= $stock['icon'] ?>"></i>
                            <?= $stock['label'] ?>
                            <?php if ($stock['class'] === 'low'): ?>
                                (Reorder level: <?= $equipment['reorder_level'] ?>)
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="label"><i class="fas fa-tag"></i> Price</div>
                    <div class="value">
                        <?= ($equipment['selling_price'] ?? 0) > 0 ? 'TSh ' . number_format($equipment['selling_price'], 0) : 'FREE' ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="label"><i class="fas fa-truck"></i> Supplier</div>
                    <div class="value"><?= htmlspecialchars($equipment['supplier'] ?? 'N/A') ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="label"><i class="fas fa-barcode"></i> Batch Number</div>
                    <div class="value">
                        <span class="batch-number"><?= htmlspecialchars($equipment['batch_number']) ?></span>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="label"><i class="fas fa-calendar"></i> Expiry Date</div>
                    <div class="value">
                        <span class="expiry-badge <?= $expiry['class'] ?>">
                            <i class="fas <?= $expiry['icon'] ?>"></i>
                            <?= $expiry['label'] ?>
                            <?php if ($expiry['days'] !== null && $expiry['class'] !== 'no-expiry'): ?>
                                <?php if ($expiry['days'] < 0): ?>
                                    (<?= abs($expiry['days']) ?> days overdue)
                                <?php else: ?>
                                    (<?= $expiry['days'] ?> days left)
                                <?php endif; ?>
                            <?php endif; ?>
                        </span>
                        <?php if (!empty($equipment['expiry_date']) && $equipment['expiry_date'] !== '0000-00-00'): ?>
                            <br><span style="font-size:0.7rem;color:var(--text-secondary);">
                                <?= date('F d, Y', strtotime($equipment['expiry_date'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="label"><i class="fas fa-calendar-plus"></i> Created At</div>
                    <div class="value" style="font-size:0.85rem;">
                        <?= date('F d, Y h:i A', strtotime($equipment['created_at'])) ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="label"><i class="fas fa-edit"></i> Last Updated</div>
                    <div class="value" style="font-size:0.85rem;">
                        <?= date('F d, Y h:i A', strtotime($equipment['updated_at'])) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- LINKED LAB TESTS -->
        <!-- ================================================================ -->
        <div class="card animate-fade-in-up" style="animation-delay:0.1s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-link"></i> Linked Lab Tests
                    <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);">
                        (<?= $linked_count ?> linked)
                    </span>
                </h3>
                <a href="services.php?tab=lab_tests&branch=<?= urlencode($return_branch) ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Manage Lab Tests
                </a>
            </div>
            
            <?php if ($linked_count > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Test Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Created By</th>
                                <th>Status</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($linked_tests as $test): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($test['test_name']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($test['category'] ?? 'Uncategorized') ?></td>
                                    <td class="price-display" style="font-weight:600;color:var(--primary);">
                                        TSh <?= number_format($test['price'] ?? 0, 0) ?>
                                    </td>
                                    <td><?= htmlspecialchars($test['created_by_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="status-badge <?= $test['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $test['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.8rem;">
                                        <?= date('M d, Y', strtotime($test['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-link"></i>
                    <p style="font-size:0.9rem;">This equipment is not linked to any lab test.</p>
                    <a href="services.php?tab=lab_tests&branch=<?= urlencode($return_branch) ?>" class="btn btn-primary btn-sm" style="margin-top:10px;">
                        <i class="fas fa-link"></i> Link to Lab Test
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- ================================================================ -->
        <!-- STOCK MOVEMENTS -->
        <!-- ================================================================ -->
        <div class="card animate-fade-in-up" style="animation-delay:0.2s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-history"></i> Stock Movements
                    <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);">
                        (<?= count($stock_movements) ?> movements)
                    </span>
                </h3>
            </div>
            
            <?php if (count($stock_movements) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Patient</th>
                                <th>Performed By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($stock_movements as $movement): 
                                $movement_type = $movement['movement_type'] ?? 'adjustment';
                                $type_class = $movement_type === 'in' ? 'in' : ($movement_type === 'out' ? 'out' : 'adjustment');
                                $type_label = $movement_type === 'in' ? 'Stock In' : ($movement_type === 'out' ? 'Stock Out' : 'Adjustment');
                                $type_icon = $movement_type === 'in' ? 'fa-arrow-down' : ($movement_type === 'out' ? 'fa-arrow-up' : 'fa-arrows-alt-h');
                            ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <span class="movement-type <?= $type_class ?>">
                                            <i class="fas <?= $type_icon ?>"></i>
                                            <?= $type_label ?>
                                        </span>
                                    </td>
                                    <td style="font-weight:700;color:<?= $movement_type === 'in' ? 'var(--success)' : ($movement_type === 'out' ? 'var(--danger)' : 'var(--warning)') ?>;">
                                        <?= $movement_type === 'in' ? '+' : ($movement_type === 'out' ? '-' : '') ?>
                                        <?= $movement['quantity'] ?>
                                    </td>
                                    <td><?= number_format($movement['previous_stock']) ?></td>
                                    <td><?= number_format($movement['new_stock']) ?></td>
                                    <td>
                                        <?php if (!empty($movement['patient_name'])): ?>
                                            <?= htmlspecialchars($movement['patient_name']) ?>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);font-size:0.7rem;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($movement['performed_by_name'] ?? 'System') ?></td>
                                    <td style="font-size:0.75rem;">
                                        <?= date('M d, Y h:i A', strtotime($movement['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p style="font-size:0.9rem;">No stock movements recorded for this equipment.</p>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- ================================================================ -->
        <!-- EQUIPMENT NOT FOUND -->
        <!-- ================================================================ -->
        <div class="page-header" style="background:var(--danger);">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Equipment Not Found
                    <span class="role-badge-display">ERROR</span>
                </h1>
                <p class="page-subtitle">
                    <i class="fas fa-tools"></i>
                    The equipment you are looking for could not be found.
                </p>
            </div>
            <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
                <a href="equipment_inventory.php?branch=<?= urlencode($return_branch) ?>" class="btn-outline-light">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>
        
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-tools"></i>
                <h3 style="font-size:1.2rem;color:var(--text-primary);margin-bottom:8px;">Equipment Not Found</h3>
                <p style="font-size:0.9rem;">The equipment with ID #<?= $equipment_id ?> could not be found in the system.</p>
                <p style="font-size:0.8rem;color:var(--text-secondary);margin-top:4px;">It may have been deleted or the ID may be incorrect.</p>
                <a href="equipment_inventory.php?branch=<?= urlencode($return_branch) ?>" class="btn btn-primary" style="margin-top:16px;">
                    <i class="fas fa-arrow-left"></i> Back to Equipment List
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            View Equipment
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
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
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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
    function performSearch() {
        var query = searchInput.value.trim();
        var branch = '<?= urlencode($return_branch) ?>';
        var url = 'equipment_inventory.php?branch=' + encodeURIComponent(branch);
        if (query.length > 0) {
            url += '&search=' + encodeURIComponent(query);
        }
        window.location.href = url;
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // DATE & TIME
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

    console.log('%c🔧 Braick Dispensary - View Equipment', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    <?php if ($equipment): ?>
        console.log('%c📦 Equipment: <?= htmlspecialchars($equipment['equipment_name']) ?> (ID: <?= $equipment['id'] ?>)', 'font-size:13px; color:#0B5ED7;');
        console.log('%c📊 Quantity: <?= $equipment['quantity'] ?> | Batch: <?= htmlspecialchars($equipment['batch_number']) ?>', 'font-size:13px; color:#D97706;');
        console.log('%c🔗 Linked Lab Tests: <?= $linked_count ?>', 'font-size:13px; color:#7C3AED;');
        console.log('%c📋 Stock Movements: <?= count($stock_movements) ?>', 'font-size:13px; color:#0D9488;');
    <?php else: ?>
        console.log('%c❌ Equipment not found (ID: <?= $equipment_id ?>)', 'font-size:13px; color:#DC2626;');
    <?php endif; ?>
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>