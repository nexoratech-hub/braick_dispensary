<?php
// ================================================================
// FILE: frontend/pages/admin/delete_equipment.php
// ADMIN - DELETE EQUIPMENT
// DELETE EQUIPMENT WITH CONFIRMATION
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
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

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
$linked_count = 0;

try {
    $stmt = $db->prepare("
        SELECT 
            e.*,
            b.name as branch_name,
            (SELECT COUNT(*) FROM lab_test_equipment WHERE equipment_id = e.id) as linked_count
        FROM medical_equipment e
        LEFT JOIN branches b ON e.branch_id = b.id
        WHERE e.id = ?
    ");
    $stmt->execute([$equipment_id]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$equipment) {
        $error_message = "Equipment not found. It may have been deleted.";
    } else {
        $linked_count = $equipment['linked_count'] ?? 0;
    }
} catch (Exception $e) {
    $error_message = "Error loading equipment: " . $e->getMessage();
    $equipment = null;
}

// ================================================================
// PROCESS DELETION
// ================================================================
$message = '';
$message_type = '';
$deleted = false;

if ($confirmed && $equipment) {
    // Check if equipment is linked to lab tests
    if ($linked_count > 0) {
        // Option 1: Delete links first, then equipment
        try {
            $db->beginTransaction();
            
            // Delete from lab_test_equipment
            $stmt = $db->prepare("DELETE FROM lab_test_equipment WHERE equipment_id = ?");
            $stmt->execute([$equipment_id]);
            
            // Delete the equipment
            $stmt = $db->prepare("DELETE FROM medical_equipment WHERE id = ?");
            $stmt->execute([$equipment_id]);
            
            $db->commit();
            
            $deleted = true;
            $message = "✅ Equipment '<strong>" . htmlspecialchars($equipment['equipment_name']) . "</strong>' deleted successfully!";
            $message_type = 'success';
            
            // Redirect after 2 seconds
            echo '<script>
                setTimeout(function() {
                    window.location.href = "equipment_inventory.php?branch=' . urlencode($return_branch) . '&success=1";
                }, 2000);
            </script>';
            
        } catch (Exception $e) {
            if (isset($db)) $db->rollBack();
            $message = "❌ Error deleting equipment: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        // No links, delete directly
        try {
            $stmt = $db->prepare("DELETE FROM medical_equipment WHERE id = ?");
            $stmt->execute([$equipment_id]);
            
            $deleted = true;
            $message = "✅ Equipment '<strong>" . htmlspecialchars($equipment['equipment_name']) . "</strong>' deleted successfully!";
            $message_type = 'success';
            
            // Redirect after 2 seconds
            echo '<script>
                setTimeout(function() {
                    window.location.href = "equipment_inventory.php?branch=' . urlencode($return_branch) . '&success=1";
                }, 2000);
            </script>';
            
        } catch (Exception $e) {
            $message = "❌ Error deleting equipment: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ================================================================
// GET BRANCHES FOR BRANCH SELECTOR
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
    <title>Delete Equipment - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ALL CSS STYLES - SAME AS SERVICES.PHP
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
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
            --teal-bg: #CCFBF1;
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
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --transition: all 0.3s ease;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
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
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .page-header {
            background: var(--primary-gradient);
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
            font-size: 1.6rem;
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
        
        .role-badge-display {
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
        
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
            margin-bottom: 24px;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        [data-theme="dark"] .card {
            background: var(--gray-800);
            border-color: var(--gray-700);
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
        
        .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .status-badge.active { background: var(--success-bg); color: var(--success); }
        .status-badge.inactive { background: var(--danger-bg); color: var(--danger); }
        
        .stock-badge {
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .stock-badge.ok { background: var(--success-bg); color: var(--success); }
        .stock-badge.low { background: var(--warning-bg); color: var(--warning); }
        .stock-badge.out { background: var(--danger-bg); color: var(--danger); }
        
        .expiry-badge {
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .expiry-badge.valid { background: var(--success-bg); color: var(--success); }
        .expiry-badge.expiring { background: var(--warning-bg); color: var(--warning); }
        .expiry-badge.expired { background: var(--danger-bg); color: var(--danger); }
        .expiry-badge.no-expiry { background: var(--gray-200); color: var(--gray-500); }
        
        .branch-tag {
            display: inline-block;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 1px 10px;
            border-radius: 12px;
            font-size: 0.65rem;
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
        
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            border: 1px solid transparent;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        .alert-info { background: var(--primary-bg); color: var(--primary); border-color: var(--primary); }
        
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
        
        .btn-danger {
            background: var(--danger);
            color: white;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.2);
        }
        .btn-danger:hover {
            background: var(--danger-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(220, 38, 38, 0.3);
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
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
        }
        
        .btn-sm { padding: 4px 12px; font-size: 0.7rem; }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--gray-300);
            display: block;
            margin-bottom: 12px;
        }
        
        [data-theme="dark"] .empty-state i { color: var(--gray-600); }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        [data-theme="dark"] .footer { border-color: var(--gray-700); }
        
        /* Delete confirmation specific styles */
        .delete-icon {
            font-size: 4rem;
            color: var(--danger);
            display: block;
            margin-bottom: 12px;
        }
        
        .warning-box {
            background: var(--danger-bg);
            border: 2px solid var(--danger);
            border-radius: var(--radius);
            padding: 16px 20px;
            margin: 16px 0;
        }
        
        .warning-box .warning-title {
            color: var(--danger);
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .warning-box .warning-text {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-top: 4px;
        }
        
        .equipment-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .equipment-detail-row:last-child {
            border-bottom: none;
        }
        
        .equipment-detail-label {
            color: var(--text-secondary);
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .equipment-detail-value {
            color: var(--text-primary);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .card { padding: 14px 16px; }
            .btn-group { flex-direction: column; }
            .btn-group .btn { justify-content: center; }
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
<!-- TOP NAVIGATION - SAME AS SERVICES.PHP -->
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
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $return_branch === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $return_branch == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <a href="../notifications.php" class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </a>
        
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
        <!-- PAGE HEADER - DANGER THEME -->
        <!-- ================================================================ -->
        <div class="page-header" style="background:linear-gradient(135deg, #DC2626, #991B1B);">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-trash"></i>
                    Delete Equipment
                    <span class="role-badge-display">ADMIN</span>
                    <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:#FCA5A5;">
                        <i class="fas fa-hashtag"></i> #<?= $equipment['id'] ?>
                    </span>
                </h1>
                <p class="page-subtitle">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action cannot be undone!
                    <span class="header-badge" style="background:rgba(255,255,255,0.15);">
                        <i class="fas fa-link"></i> <?= $linked_count ?> linked lab tests
                    </span>
                </p>
            </div>
            <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
                <a href="view_equipment.php?id=<?= $equipment['id'] ?>&branch=<?= urlencode($return_branch) ?>" class="btn-outline-light">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="edit_equipment.php?id=<?= $equipment['id'] ?>&branch=<?= urlencode($return_branch) ?>" class="btn-outline-light">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="equipment_inventory.php?branch=<?= urlencode($return_branch) ?>" class="btn-outline-light">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- MESSAGE -->
        <!-- ================================================================ -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- DELETE CONFIRMATION CARD -->
        <!-- ================================================================ -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-exclamation-triangle" style="color:var(--danger);"></i> 
                    Confirm Deletion
                </h3>
                <div>
                    <span class="status-badge <?= $equipment['status'] === 'active' ? 'active' : 'inactive' ?>">
                        <?= ucfirst($equipment['status'] ?? 'active') ?>
                    </span>
                    <span class="stock-badge <?= $stock['class'] ?>" style="margin-left:8px;">
                        <i class="fas <?= $stock['class'] === 'ok' ? 'fa-check-circle' : ($stock['class'] === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                        <?= $stock['label'] ?>
                    </span>
                </div>
            </div>
            
            <div style="text-align:center;padding:10px 0;">
                <i class="fas fa-trash delete-icon"></i>
                <h2 style="font-size:1.3rem;color:var(--danger);margin-bottom:8px;">
                    Are you sure you want to delete this equipment?
                </h2>
                <p style="color:var(--text-secondary);font-size:0.95rem;">
                    This action <strong style="color:var(--danger);">cannot be undone</strong>. All data associated with this equipment will be permanently removed.
                </p>
            </div>
            
            <!-- Equipment Details -->
            <div style="background:var(--bg-body);border-radius:var(--radius);padding:16px 20px;margin:16px 0;">
                <h4 style="font-size:0.8rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;font-weight:600;margin-bottom:12px;">
                    <i class="fas fa-info-circle"></i> Equipment Details
                </h4>
                
                <div class="equipment-detail-row">
                    <span class="equipment-detail-label"><i class="fas fa-tag"></i> Equipment Name</span>
                    <span class="equipment-detail-value"><strong><?= htmlspecialchars($equipment['equipment_name']) ?></strong></span>
                </div>
                
                <div class="equipment-detail-row">
                    <span class="equipment-detail-label"><i class="fas fa-tag"></i> Category</span>
                    <span class="equipment-detail-value"><?= htmlspecialchars($equipment['category'] ?? 'N/A') ?></span>
                </div>
                
                <div class="equipment-detail-row">
                    <span class="equipment-detail-label"><i class="fas fa-cube"></i> Unit</span>
                    <span class="equipment-detail-value"><?= htmlspecialchars($equipment['unit'] ?? 'pcs') ?></span>
                </div>
                
                <div class="equipment-detail-row">
                    <span class="equipment-detail-label"><i class="fas fa-store-alt"></i> Branch</span>
                    <span class="equipment-detail-value">
                        <?php if ($equipment['branch_id'] === null): ?>
                            <span class="branch-tag all-branches">🌐 All Branches</span>
                        <?php else: ?>
                            <span class="branch-tag"><?= htmlspecialchars($equipment['branch_name'] ?? 'N/A') ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="equipment-detail-row">
                    <span class="equipment-detail-label"><i class="fas fa-boxes"></i> Quantity</span>
                    <span class="equipment-detail-value"><?= number_format($equipment['quantity']) ?></span>
                </div>
                
                <div class="equipment-detail-row">
                    <span class="equipment-detail-label"><i class="fas fa-barcode"></i> Batch Number</span>
                    <span class="equipment-detail-value"><span class="batch-number"><?= htmlspecialchars($equipment['batch_number']) ?></span></span>
                </div>
                
                <div class="equipment-detail-row">
                    <span class="equipment-detail-label"><i class="fas fa-calendar"></i> Expiry Date</span>
                    <span class="equipment-detail-value">
                        <span class="expiry-badge <?= $expiry['class'] ?>">
                            <i class="fas <?= $expiry['class'] === 'valid' ? 'fa-check' : ($expiry['class'] === 'expiring' ? 'fa-clock' : ($expiry['class'] === 'expired' ? 'fa-skull' : 'fa-infinity')) ?>"></i>
                            <?= $expiry['label'] ?>
                        </span>
                    </span>
                </div>
                
                <div class="equipment-detail-row">
                    <span class="equipment-detail-label"><i class="fas fa-link"></i> Linked Lab Tests</span>
                    <span class="equipment-detail-value">
                        <?php if ($linked_count > 0): ?>
                            <span style="color:var(--warning);font-weight:700;">
                                <i class="fas fa-exclamation-triangle"></i> <?= $linked_count ?> test(s) linked
                            </span>
                        <?php else: ?>
                            <span style="color:var(--success);">No tests linked</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            
            <!-- Warning if linked -->
            <?php if ($linked_count > 0): ?>
                <div class="warning-box">
                    <div class="warning-title">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Warning: Equipment is linked to <?= $linked_count ?> lab test(s)</span>
                    </div>
                    <div class="warning-text">
                        This equipment is currently linked to <?= $linked_count ?> lab test(s). 
                        Deleting this equipment will also remove it from all linked lab tests.
                        The lab tests themselves will not be deleted.
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Action Buttons -->
            <div class="btn-group">
                <a href="view_equipment.php?id=<?= $equipment['id'] ?>&branch=<?= urlencode($return_branch) ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <a href="?id=<?= $equipment['id'] ?>&branch=<?= urlencode($return_branch) ?>&confirm=yes" class="btn btn-danger" style="flex:1;">
                    <i class="fas fa-trash"></i> Yes, Delete Equipment
                </a>
            </div>
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
                    The equipment you are trying to delete could not be found.
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
            Delete Equipment
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
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

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

    console.log('%c🗑️ Braick Dispensary - Delete Equipment', 'font-size:18px; font-weight:bold; color:#DC2626;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    <?php if ($equipment): ?>
        console.log('%c📦 Deleting: <?= htmlspecialchars($equipment['equipment_name']) ?> (ID: <?= $equipment['id'] ?>)', 'font-size:13px; color:#DC2626;');
        console.log('%c📊 Quantity: <?= $equipment['quantity'] ?> | Batch: <?= htmlspecialchars($equipment['batch_number']) ?>', 'font-size:13px; color:#D97706;');
        console.log('%c🔗 Linked Lab Tests: <?= $linked_count ?>', 'font-size:13px; color:#7C3AED;');
    <?php else: ?>
        console.log('%c❌ Equipment not found (ID: <?= $equipment_id ?>)', 'font-size:13px; color:#DC2626;');
    <?php endif; ?>
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#34D399;');
    console.log('%c⚠️ Warning: This action cannot be undone!', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>