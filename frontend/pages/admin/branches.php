<?php
// ================================================================
// FILE: frontend/pages/admin/branches.php
// SUPER ADMIN - BRANCHES MANAGEMENT
// ✅ Uses SHARED admin_sidebar.php
// ✅ Branch filter in header
// ✅ NO sidebar CSS conflicts
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../../dashboard.php');
    exit();
}

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET BRANCH FILTER
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$search_term = $_GET['search'] ?? '';
$message = '';
$message_type = '';

// ================================================================
// HANDLE FORM SUBMISSIONS
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        
        if ($id > 0 && in_array($status, ['active', 'inactive'])) {
            try {
                $stmt = $db->prepare("UPDATE branches SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $id]);
                $message = "✅ Branch status updated to <strong>" . ucfirst($status) . "</strong>";
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// ================================================================
// GET BRANCHES FOR HEADER FILTER
// ================================================================
$branches_for_filter = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches_for_filter = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches_for_filter = [];
}

// ================================================================
// FETCH BRANCHES
// ================================================================
$query = "SELECT b.* FROM branches b WHERE 1=1";

if (!empty($search_term)) {
    $query .= " AND (b.name LIKE :search OR b.location LIKE :search OR b.phone LIKE :search OR b.email LIKE :search)";
}

$query .= " ORDER BY b.name ASC";

$stmt = $db->prepare($query);

if (!empty($search_term)) {
    $stmt->bindValue(':search', '%' . $search_term . '%');
}

$stmt->execute();
$branches_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// ENRICH BRANCH DATA
// ================================================================
$branches = [];
$total_staff = 0;
$total_patients = 0;
$active_branches = 0;

foreach ($branches_raw as $branch) {
    $staff_stmt = $db->prepare("
        SELECT role, COUNT(*) as count 
        FROM users 
        WHERE branch_id = ? AND status = 'active'
        GROUP BY role
    ");
    $staff_stmt->execute([$branch['id']]);
    $staff_counts = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $admin_count = 0;
    $doctor_count = 0;
    $reception_count = 0;
    $pharmacy_count = 0;
    $cashier_count = 0;
    $lab_count = 0;
    
    foreach ($staff_counts as $sc) {
        switch ($sc['role']) {
            case 'admin': $admin_count = $sc['count']; break;
            case 'doctor': $doctor_count = $sc['count']; break;
            case 'reception': $reception_count = $sc['count']; break;
            case 'pharmacy': $pharmacy_count = $sc['count']; break;
            case 'cashier': $cashier_count = $sc['count']; break;
            case 'laboratory': $lab_count = $sc['count']; break;
        }
    }
    
    $branch_total_staff = $admin_count + $doctor_count + $reception_count + $pharmacy_count + $cashier_count + $lab_count;
    $total_staff += $branch_total_staff;
    
    $patient_stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
    $patient_stmt->execute([$branch['id']]);
    $patient_count = $patient_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $total_patients += $patient_count;
    
    $visits_stmt = $db->prepare("
        SELECT 
            COUNT(CASE WHEN status IN ('pending', 'assigned', 'with_doctor') THEN 1 END) as active_visits,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_visits
        FROM visits 
        WHERE branch_id = ?
    ");
    $visits_stmt->execute([$branch['id']]);
    $visits_data = $visits_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($branch['status'] === 'active') {
        $active_branches++;
    }
    
    $branch['admin_count'] = $admin_count;
    $branch['doctor_count'] = $doctor_count;
    $branch['reception_count'] = $reception_count;
    $branch['pharmacy_count'] = $pharmacy_count;
    $branch['cashier_count'] = $cashier_count;
    $branch['lab_count'] = $lab_count;
    $branch['patient_count'] = $patient_count;
    $branch['active_visits'] = $visits_data['active_visits'] ?? 0;
    $branch['completed_visits'] = $visits_data['completed_visits'] ?? 0;
    $branch['total_staff'] = $branch_total_staff;
    
    $branches[] = $branch;
}

$total_branches = count($branches);

// ================================================================
// GET USER DATA
// ================================================================
$user_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// LOGO & PROFILE URLS
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE SHARED ADMIN SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branches - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #1A56DB;
            --primary-dark: #1A3E8C;
            --primary-light: #3B82F6;
            --primary-bg: #E8EFF9;
            --primary-solid: #1A56DB;
            
            --success: #1A56DB;
            --success-dark: #1A3E8C;
            --success-light: #3B82F6;
            --success-bg: #E8EFF9;
            
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
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
            --shadow-xl: 0 20px 30px rgba(0,0,0,0.12);
            
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
            --primary-dark: #2563EB;
            --primary-light: #60A5FA;
            --primary-bg: #1E3A5F;
            --primary-solid: #2563EB;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --purple-bg: #2D1B5F;
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
            max-width: 400px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(26, 86, 219, 0.12);
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
            background: var(--primary-solid);
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
            background: var(--primary-dark);
            transform: scale(1.02);
        }
        
        /* ✅ BRANCH SELECTOR IN HEADER */
        .top-nav .branch-selector-header {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 8px 14px;
            font-size: 0.8rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            min-width: 180px;
        }
        
        .top-nav .branch-selector-header:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.12);
        }
        
        .top-nav .branch-selector-header:hover {
            border-color: var(--primary);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .top-nav .datetime i { color: var(--primary-light); }
        
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
           PAGE HEADER - BLUE
           ================================================================ */
        .page-header {
            background: var(--primary-solid);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(26, 86, 219, 0.3);
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
            cursor: pointer;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================
           STATS CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        
        .stat-card {
            background: var(--primary-solid);
            border-radius: var(--radius);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(26, 86, 219, 0.25);
            border: none;
            cursor: default;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 32px rgba(26, 86, 219, 0.35);
        }
        
        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            background: rgba(255,255,255,0.2);
            color: white;
            backdrop-filter: blur(4px);
        }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.75);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
        }
        
        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin: 0;
            line-height: 1.2;
        }
        
        /* ================================================================
           BRANCH CARDS
           ================================================================ */
        .branches-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
        }
        
        .branch-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .branch-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }
        
        .branch-card.active {
            border-left: 4px solid var(--primary-solid);
        }
        
        .branch-card.inactive {
            border-left: 4px solid var(--danger);
            opacity: 0.85;
        }
        
        .branch-card.inactive:hover {
            opacity: 1;
        }
        
        .branch-card-header {
            padding: 16px 20px;
            background: var(--primary-solid);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
        }
        
        .branch-card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .branch-info {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            position: relative;
            z-index: 1;
        }
        
        .branch-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: rgba(255,255,255,0.2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.15);
        }
        
        .branch-name {
            font-size: 0.95rem;
            font-weight: 600;
            color: white;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .branch-code {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.7);
            display: block;
            font-family: 'Courier New', monospace;
        }
        
        .branch-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            color: white;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.15);
            position: relative;
            z-index: 1;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .branch-status-badge.active {
            background: rgba(52, 211, 153, 0.3);
            border-color: rgba(52, 211, 153, 0.3);
            color: #34D399;
        }
        
        .branch-status-badge.inactive {
            background: rgba(248, 113, 113, 0.3);
            border-color: rgba(248, 113, 113, 0.3);
            color: #F87171;
        }
        
        .branch-card-body {
            padding: 16px 20px;
        }
        
        .branch-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 16px;
            margin-bottom: 14px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.82rem;
            color: var(--text-primary);
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-item i {
            color: var(--primary-solid);
            font-size: 0.85rem;
            width: 18px;
            text-align: center;
            flex-shrink: 0;
        }
        
        .detail-item .detail-label {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            min-width: 60px;
        }
        
        .detail-item .detail-value {
            color: var(--text-primary);
            font-weight: 500;
            word-break: break-all;
        }
        
        [data-theme="dark"] .detail-item i {
            color: var(--primary-light);
        }
        
        .branch-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            padding: 10px 0;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-item .stat-number {
            display: block;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .stat-item .stat-label {
            font-size: 0.55rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            font-weight: 600;
        }
        
        .branch-card-footer {
            padding: 12px 20px;
            background: var(--bg-body);
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        [data-theme="dark"] .branch-card-footer {
            background: var(--bg-card);
        }
        
        .staff-breakdown {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        .staff-tag {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 0.55rem;
            font-weight: 500;
            padding: 2px 8px;
            border-radius: 10px;
            background: var(--bg-card);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            white-space: nowrap;
        }
        
        [data-theme="dark"] .staff-tag {
            background: var(--bg-body);
        }
        
        .staff-tag i {
            font-size: 0.5rem;
        }
        
        .branch-actions {
            display: flex;
            gap: 4px;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1.5px solid var(--border-color);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-sm {
            padding: 4px 10px;
            font-size: 0.65rem;
            border-radius: 6px;
        }
        
        .btn-primary {
            background: var(--primary-solid);
            color: white;
            border-color: var(--primary-solid);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            color: white;
        }
        
        .btn-outline-primary {
            background: transparent;
            color: var(--primary-solid);
            border: 1.5px solid var(--primary-solid);
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-solid);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 86, 219, 0.3);
        }
        
        .btn-outline-success {
            color: var(--primary-solid);
            border-color: var(--primary-solid);
        }
        
        .btn-outline-success:hover {
            background: var(--primary-solid);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 86, 219, 0.3);
        }
        
        .btn-outline-danger {
            color: var(--danger);
            border-color: var(--danger);
        }
        
        .btn-outline-danger:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        
        /* ================================================================
           ALERT
           ================================================================ */
        .alert-modern {
            padding: 14px 18px;
            border-radius: var(--radius);
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .alert-modern-success {
            background: var(--primary-bg);
            color: var(--primary-dark);
            border: 1px solid var(--primary-solid);
        }
        
        .alert-modern-error {
            background: var(--danger-bg);
            color: var(--danger-dark);
            border: 1px solid var(--danger);
        }
        
        .alert-modern i { font-size: 1.1rem; margin-top: 2px; }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--text-secondary);
            opacity: 0.3;
            margin-bottom: 16px;
            display: block;
        }
        
        .empty-state h3 {
            font-size: 1.2rem;
            color: var(--text-primary);
            margin: 0 0 8px 0;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            margin: 0 0 20px 0;
            font-size: 0.9rem;
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
        
        .footer .footer-brand {
            color: var(--primary-solid);
            font-weight: 500;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 250px; }
            .branches-grid { grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
            .top-nav .branch-selector-header { min-width: 140px; font-size: 0.75rem; padding: 6px 10px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 150px; }
            .top-nav .datetime { display: none; }
            .top-nav .branch-selector-header { min-width: 120px; font-size: 0.7rem; padding: 5px 8px; }
            
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .branches-grid { grid-template-columns: 1fr; }
            .branch-details { grid-template-columns: 1fr; }
            .branch-stats { grid-template-columns: repeat(2, 1fr); }
            .branch-card-footer { flex-direction: column; align-items: stretch; }
            .staff-breakdown { justify-content: center; }
            .branch-actions { justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
            .branch-card-header { flex-direction: column; align-items: stretch; text-align: center; }
            .branch-info { flex-direction: column; text-align: center; }
            .branch-status { text-align: center; }
            .top-nav .search-wrapper { max-width: 100px; }
            .top-nav .branch-selector-header { min-width: 100px; font-size: 0.65rem; padding: 4px 6px; }
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
<!-- TOP NAVIGATION (WITH BRANCH FILTER) -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn" style="background:transparent;border:none;cursor:pointer;color:var(--text-secondary);font-size:1.2rem;padding:8px;display:none;">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search branches..." value="<?= htmlspecialchars($search_term) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <!-- ✅ BRANCH FILTER IN HEADER -->
        <select id="headerBranchSelector" 
                class="branch-selector-header"
                onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches_for_filter as $branch_filter): ?>
                <option value="<?= $branch_filter['id'] ?>" <?= $selected_branch_id == $branch_filter['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch_filter['name']) ?>
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
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= substr($user_name, 0, 1) ?>%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-store-alt"></i>
                Branches Management
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-building"></i>
                Manage all dispensary branches
                <span class="header-badge">
                    <i class="fas fa-store"></i> <?= $total_branches ?> Total
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-check-circle"></i> <?= $active_branches ?> Active
                </span>
                <span class="header-badge" style="background:rgba(248,113,113,0.2);border-color:rgba(248,113,113,0.3);color:#F87171;">
                    <i class="fas fa-times-circle"></i> <?= $total_branches - $active_branches ?> Inactive
                </span>
                <span class="header-badge" style="background:rgba(167,139,250,0.2);border-color:rgba(167,139,250,0.3);color:#A78BFA;">
                    <i class="fas fa-users"></i> <?= number_format($total_staff) ?> Staff
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="add_branch.php" class="btn-outline-light">
                <i class="fas fa-plus-circle"></i> Add Branch
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert-modern alert-modern-<?= $message_type === 'success' ? 'success' : 'error' ?>" style="max-width:1100px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- STATISTICS CARDS -->
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-store"></i>
            </div>
            <div>
                <p class="stat-label">Total Branches</p>
                <p class="stat-value"><?= $total_branches ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <p class="stat-label">Active Branches</p>
                <p class="stat-value"><?= $active_branches ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <p class="stat-label">Total Staff</p>
                <p class="stat-value"><?= number_format($total_staff) ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-user-injured"></i>
            </div>
            <div>
                <p class="stat-label">Total Patients</p>
                <p class="stat-value"><?= number_format($total_patients) ?></p>
            </div>
        </div>
    </div>

    <!-- BRANCHES GRID -->
    <?php if (count($branches) > 0): ?>
        <div class="branches-grid animate-fade-in-up" style="animation-delay:0.05s;">
            <?php foreach ($branches as $branch): 
                $branch_id = $branch['id'] ?? 0;
                $branch_name = htmlspecialchars($branch['name'] ?? 'Unknown');
                $branch_status = $branch['status'] ?? 'active';
                $branch_location = htmlspecialchars($branch['location'] ?? 'Not set');
                $branch_phone = htmlspecialchars($branch['phone'] ?? 'Not set');
                $branch_email = htmlspecialchars($branch['email'] ?? 'Not set');
                
                $admin_count = $branch['admin_count'] ?? 0;
                $doctor_count = $branch['doctor_count'] ?? 0;
                $reception_count = $branch['reception_count'] ?? 0;
                $pharmacy_count = $branch['pharmacy_count'] ?? 0;
                $cashier_count = $branch['cashier_count'] ?? 0;
                $lab_count = $branch['lab_count'] ?? 0;
                $total_staff_branch = $admin_count + $doctor_count + $reception_count + $pharmacy_count + $cashier_count + $lab_count;
                
                $patient_count = $branch['patient_count'] ?? 0;
                $active_visits = $branch['active_visits'] ?? 0;
                $completed_visits = $branch['completed_visits'] ?? 0;
            ?>
                <div class="branch-card <?= $branch_status === 'active' ? 'active' : 'inactive' ?>">
                    <div class="branch-card-header">
                        <div class="branch-info">
                            <div class="branch-icon">
                                <i class="fas fa-hospital"></i>
                            </div>
                            <div>
                                <h3 class="branch-name"><?= $branch_name ?></h3>
                                <span class="branch-code">ID: <?= $branch_id ?></span>
                            </div>
                        </div>
                        <div class="branch-status">
                            <span class="branch-status-badge <?= $branch_status === 'active' ? 'active' : 'inactive' ?>">
                                <i class="fas fa-<?= $branch_status === 'active' ? 'circle' : 'times-circle' ?>"></i>
                                <?= ucfirst($branch_status) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="branch-card-body">
                        <div class="branch-details">
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span class="detail-label">Location</span>
                                <span class="detail-value"><?= $branch_location ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-phone"></i>
                                <span class="detail-label">Phone</span>
                                <span class="detail-value"><?= $branch_phone ?></span>
                            </div>
                            <div class="detail-item" style="grid-column: span 2;">
                                <i class="fas fa-envelope"></i>
                                <span class="detail-label">Email</span>
                                <span class="detail-value"><?= $branch_email ?></span>
                            </div>
                        </div>
                        
                        <div class="branch-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?= $total_staff_branch ?></span>
                                <span class="stat-label">Staff</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?= $patient_count ?></span>
                                <span class="stat-label">Patients</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?= $active_visits ?></span>
                                <span class="stat-label">Active Visits</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?= $completed_visits ?></span>
                                <span class="stat-label">Completed</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="branch-card-footer">
                        <div class="staff-breakdown">
                            <?php if ($admin_count > 0): ?>
                                <span class="staff-tag">
                                    <i class="fas fa-user-tie"></i> <?= $admin_count ?> Admins
                                </span>
                            <?php endif; ?>
                            <?php if ($doctor_count > 0): ?>
                                <span class="staff-tag">
                                    <i class="fas fa-user-md"></i> <?= $doctor_count ?> Doctors
                                </span>
                            <?php endif; ?>
                            <?php if ($reception_count > 0): ?>
                                <span class="staff-tag">
                                    <i class="fas fa-user-friends"></i> <?= $reception_count ?> Reception
                                </span>
                            <?php endif; ?>
                            <?php if ($pharmacy_count > 0): ?>
                                <span class="staff-tag">
                                    <i class="fas fa-prescription-bottle"></i> <?= $pharmacy_count ?> Pharmacy
                                </span>
                            <?php endif; ?>
                            <?php if ($lab_count > 0): ?>
                                <span class="staff-tag">
                                    <i class="fas fa-flask"></i> <?= $lab_count ?> Lab
                                </span>
                            <?php endif; ?>
                            <?php if ($total_staff_branch == 0): ?>
                                <span class="staff-tag" style="color:var(--text-secondary);">
                                    <i class="fas fa-user-slash"></i> No staff assigned
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="branch-actions">
                            <a href="view_branch.php?id=<?= $branch_id ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit_branch.php?id=<?= $branch_id ?>" class="btn btn-sm btn-outline-primary" title="Edit Branch">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="branch_staff.php?id=<?= $branch_id ?>" class="btn btn-sm btn-outline-primary" title="Manage Staff">
                                <i class="fas fa-users-cog"></i>
                            </a>
                            <?php if ($branch_status === 'active'): ?>
                                <button onclick="toggleBranch(<?= $branch_id ?>, 'inactive')" class="btn btn-sm btn-outline-danger" title="Deactivate">
                                    <i class="fas fa-pause"></i>
                                </button>
                            <?php else: ?>
                                <button onclick="toggleBranch(<?= $branch_id ?>, 'active')" class="btn btn-sm btn-outline-success" title="Activate">
                                    <i class="fas fa-play"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state animate-fade-in-up">
            <i class="fas fa-store-alt-slash"></i>
            <h3>No Branches Found</h3>
            <p>No branches match your search criteria. Try adjusting your search or add a new branch.</p>
            <a href="add_branch.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Add Branch
            </a>
        </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Branches Management
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOGGLE STATUS FORM -->
<!-- ================================================================ -->
<form id="toggleStatusForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="id" id="toggleStatusId" value="0">
    <input type="hidden" name="status" id="toggleStatusValue" value="">
</form>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        if (url.searchParams.has('id')) {
            url.searchParams.delete('id');
        }
        window.location.href = url.toString();
    }

    // ================================================================
    // SIDEBAR TOGGLE - Uses SHARED sidebar
    // ================================================================
    (function() {
        function initSidebar() {
            var sidebar = document.getElementById('sidebar');
            var toggleBtn = document.getElementById('sidebarToggle');
            var overlay = document.getElementById('sidebarOverlay');
            
            if (!sidebar) {
                console.warn('⚠️ Sidebar not found');
                return;
            }
            
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'sidebarOverlay';
                document.body.appendChild(overlay);
            }
            
            function updateToggleVisibility() {
                if (!toggleBtn) return;
                toggleBtn.style.display = window.innerWidth <= 1024 ? 'block' : 'none';
            }
            
            updateToggleVisibility();
            window.addEventListener('resize', updateToggleVisibility);
            
            function openSidebar() {
                sidebar.classList.add('open');
                if (overlay) {
                    overlay.style.display = 'block';
                    overlay.classList.add('active');
                }
                document.body.style.overflow = 'hidden';
                document.body.classList.add('sidebar-open');
            }
            
            function closeSidebar() {
                sidebar.classList.remove('open');
                if (overlay) {
                    overlay.style.display = 'none';
                    overlay.classList.remove('active');
                }
                document.body.style.overflow = '';
                document.body.classList.remove('sidebar-open');
            }
            
            function toggleSidebar() {
                if (sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }
            
            if (toggleBtn && !toggleBtn.dataset.wired) {
                toggleBtn.dataset.wired = 'true';
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleSidebar();
                });
            }
            
            if (overlay && !overlay.dataset.wired) {
                overlay.dataset.wired = 'true';
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) closeSidebar();
                });
            }
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            console.log('✅ Sidebar initialized with shared component');
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSidebar);
        } else {
            initSidebar();
        }
    })();

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
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        var branch = '<?= $selected_branch_id ?>';
        if (query.length > 0) {
            window.location.href = 'branches.php?branch=' + branch + '&search=' + encodeURIComponent(query);
        } else {
            window.location.href = 'branches.php?branch=' + branch;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // TOGGLE BRANCH STATUS
    // ================================================================
    function toggleBranch(id, status) {
        var action = status === 'active' ? 'activate' : 'deactivate';
        if (confirm('Are you sure you want to ' + action + ' this branch?')) {
            document.getElementById('toggleStatusId').value = id;
            document.getElementById('toggleStatusValue').value = status;
            document.getElementById('toggleStatusForm').submit();
        }
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

    console.log('%c🏢 Braick Dispensary - Branches Management', 'font-size:18px; font-weight:bold; color:#1A56DB;');
    console.log('%c✅ Using SHARED admin_sidebar.php', 'font-size:13px; color:#34D399; font-weight:bold;');
    console.log('%c✅ Branch filter in header', 'font-size:13px; color:#34D399;');
    console.log('%c📊 Total Branches: <?= $total_branches ?>', 'font-size:13px; color:#1A56DB;');
</script>

</body>
</html>