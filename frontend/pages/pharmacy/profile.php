<?php
// ================================================================
// FILE: frontend/pages/pharmacy/profile.php
// PHARMACY - PROFILE (UPDATED FOR NEW DATABASE)
// USES SHARED HEADER & SIDEBAR - NO DUPLICATE HEADER
// QUICK ACTIONS: Pending, New OTC, Inventory (3 items)
// BRAICK DISPENSARY - dispensary_db
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
$user_email = $_SESSION['email'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// IF SESSION IS INCOMPLETE, TRY TO RECOVER FROM DATABASE
// ================================================================
if ($user_id <= 0) {
    if (isset($user_username) && !empty($user_username)) {
        require_once __DIR__ . '/../../../backend/config/database.php';
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, full_name, role, branch_id, email, phone, profile_pic FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$user_username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['phone'] = $user['phone'];
                $_SESSION['profile_pic'] = $user['profile_pic'];
                $user_id = $user['id'];
                $user_full_name = $user['full_name'];
                $user_role = $user['role'];
                $user_branch_id = $user['branch_id'];
                $user_email = $user['email'];
                $user_phone = $user['phone'];
                $profile_pic = $user['profile_pic'];
                
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
// GET USER STATISTICS - UPDATED FOR NEW DATABASE
// ================================================================

// 1. Total Prescriptions Dispensed
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM prescriptions 
    WHERE pharmacy_id = ? AND status = 'dispensed'
");
$stmt->execute([$user_id]);
$total_prescriptions = $stmt->fetch()['count'] ?? 0;

// 2. Total Prescriptions Confirmed
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM prescriptions 
    WHERE pharmacy_id = ? AND status = 'confirmed'
");
$stmt->execute([$user_id]);
$total_confirmed = $stmt->fetch()['count'] ?? 0;

// 3. Total OTC Sales
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM otc_sales 
    WHERE sold_by = ?
");
$stmt->execute([$user_id]);
$total_otc = $stmt->fetch()['count'] ?? 0;

// 4. Total Sales
$total_sales = $total_prescriptions + $total_otc;

// 5. Today's Sales
$today = date('Y-m-d');

$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM prescriptions 
    WHERE pharmacy_id = ? AND status = 'dispensed' AND DATE(dispensed_at) = ?
");
$stmt->execute([$user_id, $today]);
$today_prescriptions = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM otc_sales 
    WHERE sold_by = ? AND DATE(created_at) = ?
");
$stmt->execute([$user_id, $today]);
$today_otc = $stmt->fetch()['count'] ?? 0;

$today_sales = $today_prescriptions + $today_otc;

// 6. Total Medication Items Dispensed
$stmt = $db->prepare("
    SELECT SUM(pi.quantity) as total_items
    FROM prescription_items pi
    JOIN prescriptions p ON pi.prescription_id = p.id
    WHERE p.pharmacy_id = ? AND p.status = 'dispensed'
");
$stmt->execute([$user_id]);
$total_items_dispensed = $stmt->fetch()['total_items'] ?? 0;

// 7. Recent Activity
$stmt = $db->prepare("
    (SELECT 
        'prescription' as type,
        p.prescription_number as number,
        pat.full_name as patient_or_customer,
        '0' as total_amount,
        p.dispensed_at as created_at
    FROM prescriptions p
    JOIN patients pat ON p.patient_id = pat.id
    WHERE p.pharmacy_id = ? AND p.status = 'dispensed')
    UNION ALL
    (SELECT 
        'otc' as type,
        o.sale_number as number,
        o.customer_name as patient_or_customer,
        o.total_amount,
        o.created_at
    FROM otc_sales o
    WHERE o.sold_by = ?)
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id, $user_id]);
$recent_activity = $stmt->fetchAll();

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$pending_prescriptions = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_prescriptions = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

$low_stock_count = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND quantity <= reorder_level AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_count = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $low_stock_count = 0;
}

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A3D8A;
            --primary-light: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
            --success-light: #D1FAE5;
            --warning: #D97706;
            --warning-light: #FEF3C7;
            --danger: #DC2626;
            --danger-light: #FEE2E2;
            --purple: #7C3AED;
            --purple-light: #EDE9FE;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --border-color: #E2E8F0;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-muted: #94A3B8;
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --border-color: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.4);
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
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
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
            color: white;
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
        
        .page-header .page-title .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.1);
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
            margin-top: 4px;
        }
        
        .page-header .branch-tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
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
        
        .btn-edit {
            background: var(--success);
            color: white;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
            position: relative;
            z-index: 1;
        }
        
        .btn-edit:hover {
            background: var(--success-dark);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.35);
        }
        
        /* ================================================================
           PROFILE HEADER
           ================================================================ */
        .profile-header {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px 30px;
            border: 2px solid var(--border-color);
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            transition: all 0.3s ease;
        }
        
        .profile-header:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
        }
        
        .profile-header .profile-header-main {
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
        }
        
        .profile-header .profile-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary);
            flex-shrink: 0;
        }
        
        .profile-header .profile-avatar-placeholder {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            background: var(--primary);
            flex-shrink: 0;
            border: 4px solid var(--primary);
        }
        
        .profile-header .profile-info .profile-name {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .profile-header .profile-info .profile-name i {
            color: var(--primary);
        }
        
        .profile-header .profile-info .profile-role {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .profile-header .profile-info .profile-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }
        
        .badge-blue { background: #E8F0FE; color: #0B5ED7; }
        .badge-green { background: #D1FAE5; color: #059669; }
        .badge-purple { background: #F3E8FF; color: #7C3AED; }
        .badge-teal { background: #ECFDF5; color: #0D9488; }
        
        [data-theme="dark"] .badge-blue { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .badge-green { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .badge-purple { background: #2A1A3A; color: #9B4DCA; }
        [data-theme="dark"] .badge-teal { background: #0A2A2A; color: #2DD4BF; }
        
        /* ================================================================
           STATS GRID
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-box {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 16px 18px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-box:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
        }
        
        .stat-box .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-box .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-top: 2px;
        }
        
        .stat-box .stat-icon {
            font-size: 1.2rem;
            margin-bottom: 4px;
            color: var(--primary);
        }
        
        /* ================================================================
           INFO CARD
           ================================================================ */
        .info-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .info-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
        }
        
        .info-card .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 10px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-card .card-title i {
            color: var(--primary);
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-row .info-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .info-row .info-value {
            font-size: 0.85rem;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        /* ================================================================
           ACTIVITY
           ================================================================ */
        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s ease;
        }
        
        .activity-item:hover {
            background: var(--primary-light);
            border-radius: 8px;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-item .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .activity-item .activity-icon.prescription {
            background: #E8F0FE;
            color: #0B5ED7;
        }
        
        .activity-item .activity-icon.otc {
            background: #D1FAE5;
            color: #059669;
        }
        
        [data-theme="dark"] .activity-item .activity-icon.prescription {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        [data-theme="dark"] .activity-item .activity-icon.otc {
            background: #1A3A2A;
            color: #34D399;
        }
        
        .activity-item .activity-info .activity-title {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .activity-item .activity-info .activity-desc {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .activity-item .activity-time {
            font-size: 0.65rem;
            color: var(--text-secondary);
            margin-left: auto;
            white-space: nowrap;
        }
        
        .activity-item .activity-type-badge {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 1px 8px;
            border-radius: 10px;
        }
        
        .activity-item .activity-type-badge.prescription {
            background: #E8F0FE;
            color: #0B5ED7;
        }
        
        .activity-item .activity-type-badge.otc {
            background: #D1FAE5;
            color: #059669;
        }
        
        [data-theme="dark"] .activity-item .activity-type-badge.prescription {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        [data-theme="dark"] .activity-item .activity-type-badge.otc {
            background: #1A3A2A;
            color: #34D399;
        }
        
        /* ================================================================
           QUICK ACTIONS
           ================================================================ */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .quick-action-item {
            text-align: center;
            padding: 16px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            transition: all 0.3s ease;
            text-decoration: none;
            background: var(--bg-card);
        }
        
        .quick-action-item:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.1);
        }
        
        .quick-action-item i {
            font-size: 1.8rem;
            color: var(--primary);
            display: block;
            margin-bottom: 8px;
        }
        
        .quick-action-item .label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 2rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 10px;
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
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
                padding: 18px;
            }
            .profile-header .profile-header-main {
                flex-direction: column;
                justify-content: center;
            }
            .profile-header .profile-info .profile-badges {
                justify-content: center;
            }
            .profile-header .profile-info .profile-name {
                justify-content: center;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 2px;
            }
            .activity-item {
                flex-wrap: wrap;
            }
            .activity-item .activity-time {
                margin-left: 0;
                width: 100%;
                padding-left: 48px;
            }
            .quick-actions-grid {
                grid-template-columns: 1fr 1fr 1fr;
                max-width: 100%;
                gap: 10px;
            }
            .quick-action-item { padding: 12px; }
            .quick-action-item i { font-size: 1.4rem; }
            .quick-action-item .label { font-size: 0.65rem; }
            .page-header .page-title { font-size: 1.3rem; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .profile-header .profile-avatar,
            .profile-header .profile-avatar-placeholder {
                width: 70px;
                height: 70px;
                font-size: 2rem;
            }
            .profile-header .profile-info .profile-name { font-size: 1.1rem; }
            .quick-actions-grid { grid-template-columns: 1fr 1fr; }
            .quick-action-item { padding: 10px; }
            .quick-action-item i { font-size: 1.2rem; }
            .quick-action-item .label { font-size: 0.6rem; }
            .page-header .page-title { font-size: 1.1rem; }
            .page-header { padding: 16px 18px; }
        }
        
        /* Animations */
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up:nth-child(1) { animation-delay: 0.05s; }
        .animate-fade-in-up:nth-child(2) { animation-delay: 0.1s; }
        .animate-fade-in-up:nth-child(3) { animation-delay: 0.15s; }
        .animate-fade-in-up:nth-child(4) { animation-delay: 0.2s; }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-circle"></i>
                My Profile
                <span class="role-badge-display">PHARMACY</span>
            </h1>
            <p class="page-subtitle">
                View and manage your profile information
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="edit_profile.php" class="btn-edit">
                <i class="fas fa-edit"></i> Edit Profile
            </a>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PROFILE HEADER -->
    <!-- ================================================================ -->
    <div class="profile-header animate-fade-in-up">
        <div class="profile-header-main">
            <?php if (!empty($profile_pic)): ?>
                <img src="<?= $profile_pic_url ?>" alt="Profile" class="profile-avatar">
            <?php else: ?>
                <div class="profile-avatar-placeholder">
                    <?= strtoupper(substr($user_full_name, 0, 1)) ?>
                </div>
            <?php endif; ?>
            
            <div class="profile-info">
                <div class="profile-name">
                    <i class="fas fa-user-circle"></i>
                    <?= htmlspecialchars($user_full_name) ?>
                </div>
                <div class="profile-role">
                    <i class="fas fa-prescription mr-1"></i> Pharmacist
                    <span class="badge-blue" style="font-size:0.6rem;padding:1px 10px;margin-left:6px;border-radius:20px;">
                        <?= ucfirst($user_role) ?>
                    </span>
                </div>
                <div class="profile-badges">
                    <span class="badge-blue" style="padding:2px 12px;border-radius:20px;font-size:0.65rem;font-weight:600;">
                        <i class="fas fa-user mr-1"></i> <?= ucfirst($user_role) ?>
                    </span>
                    <span class="badge-green" style="padding:2px 12px;border-radius:20px;font-size:0.65rem;font-weight:600;">
                        <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
                    </span>
                    <span class="badge-purple" style="padding:2px 12px;border-radius:20px;font-size:0.65rem;font-weight:600;">
                        <i class="fas fa-prescription mr-1"></i> <?= $total_sales ?> sales
                    </span>
                    <span class="badge-teal" style="padding:2px 12px;border-radius:20px;font-size:0.65rem;font-weight:600;">
                        <i class="fas fa-calendar-check mr-1"></i> Member
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Date & Time -->
        <div style="display:flex;flex-direction:column;gap:4px;padding:10px 18px;background:var(--primary-light);border-radius:12px;border:2px solid var(--border-color);min-width:200px;transition:all 0.3s ease;">
            <div style="display:flex;align-items:center;gap:8px;font-size:0.8rem;color:var(--text-primary);font-weight:500;">
                <i class="fas fa-calendar-day" style="color:var(--primary);"></i>
                <span id="profileDate"><?= date('l, F d, Y') ?></span>
            </div>
            <div style="width:100%;height:1px;background:var(--border-color);"></div>
            <div style="display:flex;align-items:center;gap:8px;font-size:0.8rem;color:var(--text-primary);font-weight:500;">
                <i class="fas fa-clock" style="color:var(--primary);"></i>
                <span id="profileTime" style="color:var(--primary);font-weight:700;font-size:0.95rem;"><?= date('h:i:s A') ?></span>
            </div>
            <div style="width:100%;height:1px;background:var(--border-color);"></div>
            <div style="display:flex;align-items:center;gap:8px;font-size:0.75rem;color:var(--text-secondary);font-weight:500;">
                <i class="fas fa-store-alt" style="color:var(--primary);"></i>
                <span><?= htmlspecialchars($user_branch_name) ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <p class="stat-number"><?= $total_prescriptions ?></p>
            <p class="stat-label">Prescriptions Dispensed</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <p class="stat-number"><?= $total_confirmed ?></p>
            <p class="stat-label">Confirmed</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <p class="stat-number"><?= $total_otc ?></p>
            <p class="stat-label">OTC Sales</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <p class="stat-number"><?= $total_sales ?></p>
            <p class="stat-label">Total Sales</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <p class="stat-number"><?= $today_sales ?></p>
            <p class="stat-label">Today's Sales</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-capsules"></i></div>
            <p class="stat-number"><?= number_format($total_items_dispensed) ?></p>
            <p class="stat-label">Items Dispensed</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PROFILE DETAILS & RECENT ACTIVITY -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        <!-- Personal Information -->
        <div class="info-card animate-fade-in-up">
            <div class="card-title">
                <i class="fas fa-user-circle"></i>
                Personal Information
            </div>
            <div class="info-row">
                <span class="info-label">Full Name</span>
                <span class="info-value"><?= htmlspecialchars($user_full_name) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Username</span>
                <span class="info-value"><?= htmlspecialchars($user_username) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value"><?= htmlspecialchars($user_email) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Phone</span>
                <span class="info-value"><?= htmlspecialchars($user_phone) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Role</span>
                <span class="info-value">
                    <span class="badge-blue" style="padding:1px 12px;border-radius:20px;font-size:0.7rem;font-weight:600;">
                        <?= ucfirst($user_role) ?>
                    </span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Branch</span>
                <span class="info-value"><?= htmlspecialchars($user_branch_name) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Member Since</span>
                <span class="info-value"><?= date('F d, Y') ?></span>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="info-card animate-fade-in-up">
            <div class="card-title">
                <i class="fas fa-history"></i>
                Recent Activity
            </div>
            
            <?php if (count($recent_activity) > 0): ?>
                <?php foreach ($recent_activity as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?= $activity['type'] ?>">
                            <i class="fas <?= $activity['type'] === 'prescription' ? 'fa-prescription' : 'fa-shopping-cart' ?>"></i>
                        </div>
                        <div class="activity-info">
                            <div class="activity-title">
                                <?= $activity['type'] === 'prescription' ? 'Prescription Dispensed' : 'OTC Sale' ?>
                            </div>
                            <div class="activity-desc">
                                <i class="fas fa-user mr-1"></i>
                                <?= htmlspecialchars($activity['patient_or_customer'] ?? 'Unknown') ?>
                                <span class="mx-1">|</span>
                                <i class="fas fa-receipt mr-1"></i>
                                <?= htmlspecialchars($activity['number'] ?? 'N/A') ?>
                                <?php if ($activity['type'] === 'otc'): ?>
                                    <span class="mx-1">|</span>
                                    <i class="fas fa-money-bill-wave mr-1"></i>
                                    TSh <?= number_format($activity['total_amount'] ?? 0) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="activity-type-badge <?= $activity['type'] ?>">
                                <?= ucfirst($activity['type']) ?>
                            </span>
                            <span class="activity-time">
                                <?= date('M d, Y h:i A', strtotime($activity['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-prescription"></i>
                    <p>No recent activity</p>
                    <p class="text-xs text-gray-400 mt-1">Dispense some prescriptions or make OTC sales to see activity here</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS - 3 ITEMS ONLY -->
    <!-- ================================================================ -->
    <div class="info-card animate-fade-in-up">
        <div class="card-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </div>
        <div class="quick-actions-grid">
            <a href="pending_prescriptions.php" class="quick-action-item">
                <i class="fas fa-clock"></i>
                <span class="label">Pending Prescriptions</span>
            </a>
            <a href="new_otc_sale.php" class="quick-action-item">
                <i class="fas fa-plus-circle"></i>
                <span class="label">New OTC Sale</span>
            </a>
            <a href="inventory.php" class="quick-action-item">
                <i class="fas fa-warehouse"></i>
                <span class="label">Inventory</span>
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            Pharmacy Profile
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            <span id="footerTime"><?= date('h:i:s A') ?></span>
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DATE & TIME - UPDATE LIVE
    // ================================================================
    function updateProfileDateTime() {
        var now = new Date();
        
        var profileDateEl = document.getElementById('profileDate');
        if (profileDateEl) {
            profileDateEl.textContent = now.toLocaleDateString('en-US', {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });
        }
        
        var profileTimeEl = document.getElementById('profileTime');
        if (profileTimeEl) {
            profileTimeEl.textContent = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
        }
        
        var footerTimeEl = document.getElementById('footerTime');
        if (footerTimeEl) {
            footerTimeEl.textContent = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
        }
    }
    
    updateProfileDateTime();
    setInterval(updateProfileDateTime, 1000);

    // ================================================================
    // SIDEBAR TOGGLE - Inashughulikiwa na shared sidebar
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && sidebarToggle) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('open');
                }
            }
        }
    });

    // ================================================================
    // DARK MODE - Inashughulikiwa na shared header
    // ================================================================
    var htmlElement = document.documentElement;
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
    }

    console.log('%c💊 Braick - Pharmacy Profile (NEW DATABASE - FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Using NEW DATABASE: dispensary_db', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Uses shared header & sidebar (NO DUPLICATES)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ QUICK ACTIONS: 3 items (Pending, New OTC, Inventory)', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>