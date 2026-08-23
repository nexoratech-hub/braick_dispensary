<?php
// ================================================================
// FILE: frontend/pages/pharmacy/expiring_soon.php
// PHARMACY - EXPIRING SOON MEDICINES
// USING NEW DATABASE: dispensary_db
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// SESSION START
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
// CHECK IF USER HAS PHARMACY ACCESS
// ================================================================
$allowed_roles = ['pharmacy', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_role = $_SESSION['role'] ?? 'pharmacy';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// IF SESSION IS INCOMPLETE, TRY TO RECOVER FROM DATABASE
// ================================================================
if ($user_id <= 0) {
    if (isset($user_username) && !empty($user_username)) {
        require_once __DIR__ . '/../../../backend/config/database.php';
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, full_name, role, branch_id FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$user_username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $user_id = $user['id'];
                $user_full_name = $user['full_name'];
                $user_role = $user['role'];
                $user_branch_id = $user['branch_id'];
                
                // Get branch name
                $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
                $stmt->execute([$user_branch_id]);
                $branch = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($branch) {
                    $_SESSION['branch_name'] = $branch['name'];
                    $user_branch_name = $branch['name'];
                }
            }
        } catch (Exception $e) {
            // Fallback to session values
        }
    }
}

// If still no user_id, redirect to login
if ($user_id <= 0) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// DATABASE CONNECTION - NEW DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET BRANCH FILTER
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$search_term = $_GET['search'] ?? '';
$days_filter = isset($_GET['days']) ? (int)$_GET['days'] : 30;

// If user is not admin, force their branch
if ($user_role !== 'admin') {
    $selected_branch_id = $user_branch_id;
}

// ================================================================
// GET BRANCHES FOR FILTER - NEW DB
// ================================================================
$branches = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// GET EXPIRING SOON MEDICINES - NEW DB
// ================================================================
$today = date('Y-m-d');
$expiry_threshold = date('Y-m-d', strtotime("+$days_filter days"));

$query = "
    SELECT 
        mi.*,
        b.name as branch_name,
        DATEDIFF(mi.expiry_date, CURDATE()) as days_remaining,
        CASE 
            WHEN mi.expiry_date < CURDATE() THEN 'Expired'
            WHEN mi.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Critical'
            WHEN mi.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY) THEN 'Urgent'
            WHEN mi.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Warning'
            ELSE 'Normal'
        END as expiry_status
    FROM medications_inventory mi
    LEFT JOIN branches b ON mi.branch_id = b.id
    WHERE mi.expiry_date IS NOT NULL
    AND mi.expiry_date <= ?
    AND mi.status = 'active'
";

$params = [$expiry_threshold];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $query .= " AND mi.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

if (!empty($search_term)) {
    $query .= " AND (mi.medication_name LIKE ? OR mi.category LIKE ? OR mi.batch_number LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

$query .= " ORDER BY mi.expiry_date ASC, mi.medication_name ASC";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $expiring_medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $expiring_medicines = [];
}

// ================================================================
// GET STATISTICS
// ================================================================
$critical_count = 0;
$urgent_count = 0;
$warning_count = 0;
$expired_count = 0;

foreach ($expiring_medicines as $medicine) {
    if ($medicine['expiry_date'] < $today) {
        $expired_count++;
    } elseif ($medicine['days_remaining'] <= 7) {
        $critical_count++;
    } elseif ($medicine['days_remaining'] <= 14) {
        $urgent_count++;
    } elseif ($medicine['days_remaining'] <= 30) {
        $warning_count++;
    }
}

$total_expiring = count($expiring_medicines);

// ================================================================
// GET UNREAD NOTIFICATIONS - NEW DB
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
// GET PENDING PRESCRIPTIONS - NEW DB
// ================================================================
$pending_prescriptions = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_prescriptions = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

// ================================================================
// GET LOW STOCK COUNT - NEW DB
// ================================================================
$low_stock_count = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND quantity > 0 AND quantity <= reorder_level AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_count = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $low_stock_count = 0;
}

// ================================================================
// GET STATUS BADGE CLASS
// ================================================================
function getExpiryBadge($status) {
    $classes = [
        'Expired' => 'danger',
        'Critical' => 'danger',
        'Urgent' => 'warning',
        'Warning' => 'info',
        'Normal' => 'success'
    ];
    return $classes[$status] ?? 'secondary';
}

function getExpiryIcon($status) {
    $icons = [
        'Expired' => 'fa-times-circle',
        'Critical' => 'fa-exclamation-circle',
        'Urgent' => 'fa-exclamation-triangle',
        'Warning' => 'fa-clock',
        'Normal' => 'fa-check-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/pharmacy_header.php';
include_once '../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expiring Soon - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
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
            --transition: all 0.3s ease;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
            --table-hover: #E8F0FE;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --table-hover: #1E3A5F;
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
           TOP NAV
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
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        .branch-badge {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        [data-theme="dark"] .branch-badge {
            background: #1A3A2A;
            color: #34D399;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
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
        
        .page-header .branch-tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .date-badge {
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.8);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            backdrop-filter: blur(4px);
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
        
        .page-header .new-db-badge {
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.7);
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.55rem;
            font-weight: 600;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.08);
            letter-spacing: 0.03em;
        }
        
        /* ================================================================
           STAT CARDS
           ================================================================ */
        .stat-card {
            border-radius: 12px;
            padding: 16px 18px;
            border: none;
            transition: all 0.3s ease;
            color: white;
            min-height: 80px;
            display: block;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card:active {
            transform: scale(0.97);
        }
        
        .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.orange-dark { background: linear-gradient(135deg, #B45309, #92400E); }
        .stat-card.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
        .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        
        .stat-card .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            background: rgba(255,255,255,0.2);
            color: white;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(-5deg);
            background: rgba(255,255,255,0.3);
        }
        
        .stat-card .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .stat-card .stat-trend {
            font-size: 0.6rem;
            font-weight: 500;
            padding: 2px 10px;
            border-radius: 12px;
            background: rgba(255,255,255,0.15);
            color: white;
            display: inline-block;
            margin-top: 2px;
        }
        
        /* ================================================================
           GRID
           ================================================================ */
        .grid {
            display: grid;
            gap: 16px;
        }
        
        .grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
        .grid-cols-4 { grid-template-columns: repeat(4, 1fr); }
        
        @media (min-width: 640px) {
            .sm\:grid-cols-4 { grid-template-columns: repeat(4, 1fr); }
        }
        
        .mb-5 { margin-bottom: 20px; }
        .gap-4 { gap: 16px; }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 20px;
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--primary-light);
        }
        
        .card-header {
            padding: 14px 20px;
            background: var(--bg-body);
            border-bottom: 1px solid var(--border-color);
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
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .title-blue { color: var(--primary); }
        .title-green { color: var(--success); }
        
        /* ================================================================
           FILTER SECTION
           ================================================================ */
        .filter-section {
            padding: 16px 20px;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
            min-width: 140px;
        }
        
        .filter-group label {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .filter-select,
        .filter-input {
            padding: 6px 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-body);
            color: var(--text-primary);
            font-size: 0.8rem;
            outline: none;
            transition: all 0.3s;
            width: 100%;
        }
        
        .filter-select:focus,
        .filter-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .filter-select:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .filter-actions {
            display: flex;
            gap: 6px;
            flex: 0 0 auto;
            align-items: center;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-sm { padding: 3px 10px; font-size: 0.65rem; }
        
        .btn-blue { background: var(--primary); color: white; }
        .btn-blue:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(11,94,215,0.3); }
        
        .btn-green { background: var(--success); color: white; }
        .btn-green:hover { background: var(--success-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(5,150,105,0.3); }
        
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #991B1B; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(220,38,38,0.3); }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
        }
        
        .action-buttons {
            display: flex;
            gap: 4px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        /* ================================================================
           DATA TABLE
           ================================================================ */
        .overflow-x-auto {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.8rem;
        }
        
        .data-table thead th {
            background: var(--primary) !important;
            color: white !important;
            font-weight: 600;
            padding: 10px 12px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: none !important;
            white-space: nowrap;
            text-align: left;
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
        
        .data-table .text-center { text-align: center; }
        .data-table .text-sm { font-size: 0.75rem; }
        .data-table .text-xs { font-size: 0.65rem; }
        .data-table .font-mono { font-family: 'Courier New', monospace; }
        .data-table .font-medium { font-weight: 500; }
        .data-table .font-bold { font-weight: 700; }
        .data-table .py-8 { padding-top: 32px; padding-bottom: 32px; }
        .data-table .text-4xl { font-size: 2.5rem; }
        .data-table .text-lg { font-size: 1.1rem; }
        .data-table .text-green-500 { color: #059669; }
        .data-table .text-gray-400 { color: var(--text-secondary); }
        .data-table .mb-3 { margin-bottom: 12px; }
        .data-table .block { display: block; }
        .data-table .text-danger { color: var(--danger) !important; }
        
        /* ================================================================
           ROW STYLES
           ================================================================ */
        .expired-row td {
            background: #FEE2E2 !important;
            color: #991B1B !important;
        }
        
        .expired-row:hover td {
            background: #FECACA !important;
        }
        
        .critical-row td {
            background: #FEF3C7 !important;
        }
        
        .critical-row:hover td {
            background: #FDE68A !important;
        }
        
        .urgent-row td {
            background: #FEF9C3 !important;
        }
        
        .urgent-row:hover td {
            background: #FDE047 !important;
        }
        
        [data-theme="dark"] .expired-row td {
            background: #3A1A1A !important;
            color: #F87171 !important;
        }
        
        [data-theme="dark"] .critical-row td {
            background: #3D2E0A !important;
            color: #FBBF24 !important;
        }
        
        [data-theme="dark"] .urgent-row td {
            background: #3D3A0A !important;
            color: #FCD34D !important;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            color: white;
        }
        
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        .badge-secondary { background: #64748B; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        .category-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 500;
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        [data-theme="dark"] .category-badge {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .quantity-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            background: var(--success-bg);
            color: var(--success);
        }
        
        .quantity-badge.warning {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        .quantity-badge.danger {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        [data-theme="dark"] .quantity-badge {
            background: #1A3A2A;
            color: #34D399;
        }
        
        [data-theme="dark"] .quantity-badge.warning {
            background: #3D2E0A;
            color: #FBBF24;
        }
        
        [data-theme="dark"] .quantity-badge.danger {
            background: #3A1A1A;
            color: #F87171;
        }
        
        .days-remaining {
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .days-remaining.danger {
            color: var(--danger);
        }
        
        .days-remaining.warning {
            color: var(--warning);
        }
        
        .branch-badge-sm {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.55rem;
            font-weight: 500;
            background: var(--bg-body);
            color: var(--text-secondary);
        }
        
        [data-theme="dark"] .branch-badge-sm {
            background: #334155;
            color: #94A3B8;
        }
        
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
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
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
            box-shadow: var(--shadow-lg);
            font-size: 0.85rem;
        }
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: #D97706; }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        .animate-fade-in-up:nth-child(1) { animation-delay: 0.05s; }
        .animate-fade-in-up:nth-child(2) { animation-delay: 0.1s; }
        .animate-fade-in-up:nth-child(3) { animation-delay: 0.15s; }
        .animate-fade-in-up:nth-child(4) { animation-delay: 0.2s; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .filter-form { flex-direction: column; align-items: stretch; }
            .filter-group { min-width: 100%; }
            .filter-actions { flex-direction: row; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .data-table { font-size: 0.7rem; }
            .data-table td, .data-table th { padding: 6px 8px; }
            .data-table thead th { font-size: 0.55rem; padding: 6px 8px; }
            .action-buttons { flex-direction: column; gap: 3px; }
            .action-buttons .btn { width: 100%; justify-content: center; }
            .grid-cols-4 { grid-template-columns: 1fr 1fr; }
            .card-header { flex-direction: column; align-items: flex-start; gap: 8px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .stat-card { min-height: 65px; padding: 10px 14px; }
            .stat-card .stat-number { font-size: 1.2rem; }
            .stat-card .stat-icon { width: 32px; height: 32px; font-size: 0.9rem; }
            .filter-actions { flex-direction: column; }
            .filter-actions .btn { width: 100%; justify-content: center; }
            .grid-cols-4 { grid-template-columns: 1fr 1fr; }
            .data-table td { font-size: 0.6rem; }
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
            <input type="text" id="searchInput" placeholder="Search medicines..." value="<?= htmlspecialchars($search_term) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='<?= $logo_path ?>'">
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
                <i class="fas fa-clock"></i>
                Expiring Soon
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="new-db-badge">
                    <i class="fas fa-database"></i> New DB
                </span>
            </h1>
            <p class="page-subtitle">
                Medicines expiring within <?= $days_filter ?> days
                <span class="date-badge">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
                <span class="date-badge">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($user_full_name) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="inventory.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
        
        <div class="stat-card orange animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Total Expiring</p>
                    <p class="stat-number" id="statTotal"><?= number_format($total_expiring) ?></p>
                    <span class="stat-trend">Within <?= $days_filter ?> days</span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card red animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Critical</p>
                    <p class="stat-number" id="statCritical"><?= number_format($critical_count) ?></p>
                    <span class="stat-trend">Within 7 days</span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card orange-dark animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Urgent</p>
                    <p class="stat-number" id="statUrgent"><?= number_format($urgent_count) ?></p>
                    <span class="stat-trend">8-14 days</span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card red animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Expired</p>
                    <p class="stat-number" id="statExpired"><?= number_format($expired_count) ?></p>
                    <span class="stat-trend">Already expired</span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-filter title-blue"></i> Filters
            </h3>
        </div>
        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label>Branch</label>
                    <select name="branch" class="filter-select" onchange="this.form.submit()" <?= $user_role !== 'admin' ? 'disabled' : '' ?>>
                        <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>All Branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($branch['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Days Range</label>
                    <select name="days" class="filter-select" onchange="this.form.submit()">
                        <option value="7" <?= $days_filter == 7 ? 'selected' : '' ?>>Next 7 Days</option>
                        <option value="14" <?= $days_filter == 14 ? 'selected' : '' ?>>Next 14 Days</option>
                        <option value="30" <?= $days_filter == 30 ? 'selected' : '' ?>>Next 30 Days</option>
                        <option value="60" <?= $days_filter == 60 ? 'selected' : '' ?>>Next 60 Days</option>
                        <option value="90" <?= $days_filter == 90 ? 'selected' : '' ?>>Next 90 Days</option>
                    </select>
                </div>
                
                <div class="filter-group" style="flex: 1; min-width: 200px;">
                    <label>Search</label>
                    <input type="text" name="search" class="filter-input" placeholder="Search by name, category, batch..." value="<?= htmlspecialchars($search_term) ?>">
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-blue">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="expiring_soon.php?branch=<?= $selected_branch_id ?>&days=<?= $days_filter ?>" class="btn btn-outline">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- EXPIRING SOON TABLE -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue"></i> Expiring Medicines
                <span class="text-xs text-gray-400 font-normal">(<span id="totalItems"><?= number_format($total_expiring) ?></span> items)</span>
            </h3>
            <div class="flex gap-2 flex-wrap">
                <button onclick="window.print()" class="btn btn-outline btn-sm">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="exportCSV()" class="btn btn-green btn-sm">
                    <i class="fas fa-file-export"></i> Export CSV
                </button>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table" id="expiringTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Medicine Name</th>
                        <th>Category</th>
                        <th>Batch #</th>
                        <th>Quantity</th>
                        <th>Expiry Date</th>
                        <th>Days Left</th>
                        <th>Status</th>
                        <th>Branch</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($expiring_medicines) > 0): ?>
                        <?php $i = 1; foreach ($expiring_medicines as $medicine): ?>
                            <?php 
                                $status = $medicine['expiry_status'];
                                $days = $medicine['days_remaining'];
                                $row_class = '';
                                
                                if ($status === 'Expired') {
                                    $row_class = 'expired-row';
                                } elseif ($status === 'Critical') {
                                    $row_class = 'critical-row';
                                } elseif ($status === 'Urgent') {
                                    $row_class = 'urgent-row';
                                }
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td><?= $i++ ?></td>
                                <td class="font-medium">
                                    <?= htmlspecialchars($medicine['medication_name']) ?>
                                </td>
                                <td>
                                    <span class="category-badge"><?= htmlspecialchars($medicine['category'] ?? 'N/A') ?></span>
                                </td>
                                <td class="font-mono text-sm"><?= htmlspecialchars($medicine['batch_number'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="quantity-badge <?= $medicine['quantity'] <= 0 ? 'danger' : ($medicine['quantity'] <= $medicine['reorder_level'] ? 'warning' : '') ?>">
                                        <?= number_format($medicine['quantity']) ?> <?= htmlspecialchars($medicine['unit'] ?? '') ?>
                                    </span>
                                </td>
                                <td class="font-mono text-sm">
                                    <?php if ($medicine['expiry_date'] < $today): ?>
                                        <span class="text-danger"><?= date('M d, Y', strtotime($medicine['expiry_date'])) ?></span>
                                    <?php else: ?>
                                        <?= date('M d, Y', strtotime($medicine['expiry_date'])) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($medicine['expiry_date'] < $today): ?>
                                        <span class="text-danger font-bold">Expired</span>
                                    <?php else: ?>
                                        <span class="days-remaining <?= $days <= 7 ? 'danger' : ($days <= 14 ? 'warning' : '') ?>">
                                            <?= $days ?> days
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getExpiryBadge($status) ?>">
                                        <i class="fas <?= getExpiryIcon($status) ?>"></i>
                                        <?= $status ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="branch-badge-sm">
                                        <?= htmlspecialchars($medicine['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_inventory.php?id=<?= $medicine['id'] ?>&type=medicine" 
                                           class="btn btn-sm btn-blue" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button onclick="markAsExpired(<?= $medicine['id'] ?>)" 
                                                class="btn btn-sm btn-danger" title="Mark as Expired">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-gray-400 text-sm py-8">
                                <i class="fas fa-check-circle text-4xl block mb-3 text-green-500"></i>
                                <p class="text-lg font-medium">No expiring medicines found</p>
                                <p class="text-sm">All medicines are within their expiry date</p>
                            </td>
                        </tr>
                    <?php endif; ?>
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
            Expiring Soon
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            <span style="color:var(--primary);font-weight:600;font-size:0.65rem;">
                <i class="fas fa-database"></i> New DB
            </span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
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
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
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

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            var days = '<?= $days_filter ?>';
            window.location.href = 'expiring_soon.php?search=' + encodeURIComponent(query) + '&branch=' + branch + '&days=' + days;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

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
    // EXPORT CSV
    // ================================================================
    function exportCSV() {
        var table = document.getElementById('expiringTable');
        if (!table) return;
        
        var rows = table.querySelectorAll('tr');
        var csv = [];
        
        // Get headers
        var headers = [];
        table.querySelectorAll('thead th').forEach(function(th) {
            headers.push(th.textContent.trim());
        });
        csv.push(headers.join(','));
        
        // Get data rows
        table.querySelectorAll('tbody tr').forEach(function(row) {
            var rowData = [];
            row.querySelectorAll('td').forEach(function(td, index) {
                // Skip action buttons column (last column)
                if (index < headers.length - 1) {
                    var text = td.textContent.trim().replace(/"/g, '""');
                    rowData.push('"' + text + '"');
                }
            });
            if (rowData.length > 0) {
                csv.push(rowData.join(','));
            }
        });
        
        // Download CSV
        var blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'expiring_medicines_' + new Date().toISOString().slice(0,10) + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    // ================================================================
    // MARK AS EXPIRED
    // ================================================================
    function markAsExpired(id) {
        if (!confirm('⚠️ Are you sure you want to mark this medicine as EXPIRED?\n\nThis action will update the status to inactive.')) {
            return;
        }
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'mark_medicine_expired.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            showToast('✅ Success', 'Medicine marked as expired successfully!', 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showToast('❌ Error', data.message || 'Failed to mark as expired', 'error');
                        }
                    } catch (e) {
                        showToast('❌ Error', 'Invalid response from server', 'error');
                    }
                } else {
                    showToast('❌ Error', 'Network error. Please try again.', 'error');
                }
            }
        };
        
        xhr.send('id=' + id + '&branch=<?= $selected_branch_id ?>');
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
            searchInput?.select();
        }
        if (e.key === 'Escape') {
            if (searchInput && document.activeElement === searchInput) {
                searchInput.value = '';
                searchInput.blur();
            }
        }
    });

    console.log('%c💊 Pharmacy - Expiring Soon (NEW DATABASE)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Using NEW DATABASE: dispensary_db', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Total Expiring: <?= number_format($total_expiring) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c🔴 Critical: <?= number_format($critical_count) ?> | 🟠 Urgent: <?= number_format($urgent_count) ?> | ⚪ Warning: <?= number_format($warning_count) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Tables: medications_inventory, branches, notifications, prescriptions', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Export CSV available', 'font-size:13px; color:#059669;');
</script>

</body>
</html>