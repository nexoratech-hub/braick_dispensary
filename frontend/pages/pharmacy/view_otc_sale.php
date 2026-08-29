<?php
// ================================================================
// FILE: frontend/pages/pharmacy/view_otc_sale.php
// PHARMACY - VIEW OTC SALE DETAILS (NO FINANCIAL DATA)
// WITH PDF GENERATION - Official Stamp Included
// UPDATED FOR NEW DATABASE: dispensary_db
// WITH SESSION MANAGEMENT & LOGIN PROTECTION
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
$user_phone = $_SESSION['phone'] ?? '';
$user_email = $_SESSION['email'] ?? '';

// ================================================================
// IF SESSION IS INCOMPLETE, TRY TO RECOVER FROM DATABASE
// ================================================================
if ($user_id <= 0) {
    if (isset($user_username) && !empty($user_username)) {
        require_once __DIR__ . '/../../../backend/config/database.php';
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, full_name, role, branch_id, profile_pic, phone, email FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$user_username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $_SESSION['profile_pic'] = $user['profile_pic'];
                $_SESSION['phone'] = $user['phone'] ?? '';
                $_SESSION['email'] = $user['email'] ?? '';
                $user_id = $user['id'];
                $user_full_name = $user['full_name'];
                $user_role = $user['role'];
                $user_branch_id = $user['branch_id'];
                $profile_pic = $user['profile_pic'];
                $user_phone = $user['phone'] ?? '';
                $user_email = $user['email'] ?? '';
                
                // Get branch name
                $stmt = $db->prepare("SELECT name, phone FROM branches WHERE id = ?");
                $stmt->execute([$user_branch_id]);
                $branch = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($branch) {
                    $_SESSION['branch_name'] = $branch['name'];
                    $_SESSION['branch_phone'] = $branch['phone'] ?? '';
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
// GET SALE ID
// ================================================================
$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($sale_id <= 0) {
    header('Location: otc_history.php');
    exit;
}

// ================================================================
// GET ADMIN CONTACT NUMBERS
// ================================================================
$admin_phones = [];
$admin_name = '';
try {
    $stmt = $db->prepare("
        SELECT phone, full_name FROM users 
        WHERE role = 'admin' AND branch_id = ? AND status = 'active'
        ORDER BY id ASC
    ");
    $stmt->execute([$user_branch_id]);
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($admins as $admin) {
        if (!empty($admin['phone'])) {
            $admin_phones[] = $admin['phone'];
        }
        if (empty($admin_name)) {
            $admin_name = $admin['full_name'] ?? 'Admin';
        }
    }
} catch (Exception $e) {
    $admin_phones = [];
    $admin_name = 'Admin';
}

// ================================================================
// GET BRANCH PHONE
// ================================================================
$branch_phone = '';
try {
    $stmt = $db->prepare("SELECT phone FROM branches WHERE id = ?");
    $stmt->execute([$user_branch_id]);
    $branch_phone = $stmt->fetchColumn();
} catch (Exception $e) {
    $branch_phone = '';
}

// ================================================================
// GET OTC SALE DETAILS - NEW DATABASE
// ================================================================
$stmt = $db->prepare("
    SELECT 
        os.*,
        u.full_name as cashier_name,
        u.phone as cashier_phone,
        u.email as cashier_email,
        b.name as branch_name,
        b.phone as branch_phone,
        p.full_name as patient_full_name,
        p.patient_id as patient_number,
        p.phone as patient_phone,
        p.email as patient_email,
        p.address as patient_address,
        p.gender as patient_gender,
        p.date_of_birth as patient_dob
    FROM otc_sales os
    LEFT JOIN users u ON os.sold_by = u.id
    LEFT JOIN branches b ON os.branch_id = b.id
    LEFT JOIN patients p ON os.patient_id = p.id
    WHERE os.id = ? AND os.branch_id = ?
");
$stmt->execute([$sale_id, $user_branch_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    header('Location: otc_history.php');
    exit;
}

// ================================================================
// GET SALE ITEMS - NEW DATABASE
// ================================================================
$stmt = $db->prepare("
    SELECT 
        osi.*,
        mi.medication_name as inventory_medication_name,
        mi.unit,
        mi.batch_number
    FROM otc_sale_items osi
    LEFT JOIN medications_inventory mi ON osi.inventory_id = mi.id
    WHERE osi.sale_id = ?
    ORDER BY osi.id ASC
");
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
// PROFILE PICTURE
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
    $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
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
    $low_stock_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $low_stock_count = 0;
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
    <title>OTC Sale Details - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
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
            
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
            --green-header: #059669;
            --green-header-dark: #047857;
        }
        
        /* ================================================================
           DARK MODE VARIABLES
           ================================================================ */
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
            --gray-50: #1E293B;
            --gray-100: #2D3748;
            --gray-200: #4A5568;
            --gray-300: #718096;
            --gray-400: #A0AEC0;
            --green-header: #047857;
            --green-header-dark: #065F46;
            --success-bg: #1A3A2A;
            --warning-bg: #3D2E0A;
            --danger-bg: #3A1A1A;
            --purple-bg: #2A1A3A;
            --teal-bg: #1A3A3A;
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
            border-radius: var(--radius-lg);
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(10, 76, 168, 0.30);
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
            font-size: 1.8rem;
            opacity: 0.9;
        }
        
        .page-header .page-title .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 2px;
        }
        
        .page-header .page-subtitle .branch-tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 2px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .page-subtitle .type-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 2px 14px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .header-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 7px 16px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.78rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
            cursor: pointer;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .page-header .btn-outline-light.pdf-btn {
            background: rgba(220,38,38,0.25);
            border-color: rgba(220,38,38,0.3);
        }
        
        .page-header .btn-outline-light.pdf-btn:hover {
            background: rgba(220,38,38,0.4);
        }
        
        /* ================================================================
           DETAIL CARD
           ================================================================ */
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .detail-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--primary-light);
        }
        
        .detail-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .detail-card .card-header .sale-number {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .detail-card .card-header .sale-number .sale-id {
            font-family: 'Courier New', monospace;
            background: var(--gray-100);
            padding: 2px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        [data-theme="dark"] .detail-card .card-header .sale-number .sale-id {
            background: var(--gray-800);
        }
        
        .detail-card .card-header .sale-date {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .detail-card .card-header .sale-date i {
            margin-right: 4px;
            color: var(--primary-light);
        }
        
        /* Status Badge */
        .status-badge {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .status-badge.paid {
            background: var(--success-bg);
            color: var(--success);
        }
        .status-badge.pending {
            background: var(--warning-bg);
            color: var(--warning);
        }
        .status-badge.cancelled {
            background: var(--danger-bg);
            color: var(--danger);
        }
        .status-badge.partial {
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        [data-theme="dark"] .status-badge.paid {
            background: #1A3A2A;
            color: #34D399;
        }
        [data-theme="dark"] .status-badge.pending {
            background: #3D2E0A;
            color: #FBBF24;
        }
        [data-theme="dark"] .status-badge.cancelled {
            background: #3A1A1A;
            color: #F87171;
        }
        [data-theme="dark"] .status-badge.partial {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        /* ================================================================
           INFO GRID
           ================================================================ */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px 24px;
            margin-bottom: 4px;
        }
        
        .info-grid .info-item {
            padding: 4px 0;
        }
        
        .info-grid .info-item .label {
            font-size: 0.6rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 2px;
        }
        
        .info-grid .info-item .value {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .info-grid .info-item .value .sub {
            font-size: 0.7rem;
            font-weight: 400;
            color: var(--text-secondary);
            display: block;
        }
        
        /* ================================================================
           MEDICINES LIST
           ================================================================ */
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--primary);
        }
        
        .section-title .badge-count {
            background: var(--primary-bg);
            color: var(--primary);
            padding: 1px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: auto;
        }
        
        .medicines-list {
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        
        .medicines-list .list-header {
            display: grid;
            grid-template-columns: 2fr 0.8fr 1fr 1fr 2fr;
            gap: 8px;
            padding: 8px 16px;
            background: var(--green-header);
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        [data-theme="dark"] .medicines-list .list-header {
            background: var(--green-header-dark);
        }
        
        .medicines-list .list-item {
            display: grid;
            grid-template-columns: 2fr 0.8fr 1fr 1fr 2fr;
            gap: 8px;
            padding: 10px 16px;
            border-bottom: 1px solid var(--border-color);
            align-items: start;
            transition: background 0.2s ease;
        }
        
        .medicines-list .list-item:last-child {
            border-bottom: none;
        }
        
        .medicines-list .list-item:hover {
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .medicines-list .list-item:hover {
            background: var(--gray-800);
        }
        
        .medicines-list .list-item .med-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        
        .medicines-list .list-item .med-batch {
            font-size: 0.6rem;
            color: var(--text-secondary);
        }
        
        .medicines-list .list-item .med-qty {
            font-weight: 600;
            font-size: 0.85rem;
            text-align: center;
        }
        
        .medicines-list .list-item .med-price {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-align: right;
        }
        
        .medicines-list .list-item .med-total {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--success);
            text-align: right;
        }
        
        .medicines-list .list-item .med-instructions {
            font-size: 0.75rem;
            color: var(--text-primary);
            background: var(--warning-bg);
            padding: 4px 12px;
            border-radius: 6px;
            border-left: 3px solid var(--warning);
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.5;
        }
        
        [data-theme="dark"] .medicines-list .list-item .med-instructions {
            background: #2D2A1A;
        }
        
        .medicines-list .list-item .med-instructions i {
            color: var(--warning);
            margin-right: 4px;
        }
        
        /* ================================================================
           SUMMARY BOX
           ================================================================ */
        .summary-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 16px;
            padding: 12px 20px;
            background: var(--gray-50);
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
        }
        
        [data-theme="dark"] .summary-box {
            background: var(--gray-800);
        }
        
        .summary-box .summary-left {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .summary-box .summary-left .total-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        
        .summary-box .summary-left .total-items {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .summary-box .summary-left .total-items strong {
            color: var(--primary);
        }
        
        .summary-box .summary-right {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .summary-box .summary-right .amount-box {
            text-align: center;
            padding: 4px 16px;
            border-radius: 8px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
        }
        
        .summary-box .summary-right .amount-box .amount-label {
            font-size: 0.5rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        
        .summary-box .summary-right .amount-box .amount-value {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--primary);
        }
        
        .summary-box .summary-right .amount-box .amount-value.discount {
            color: var(--danger);
        }
        
        /* ================================================================
           ACTION BUTTONS
           ================================================================ */
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 2px solid var(--border-color);
        }
        
        .btn-action {
            padding: 8px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.82rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-action.btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-action.btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-action.btn-success {
            background: var(--success);
            color: white;
        }
        .btn-action.btn-success:hover {
            background: #047857;
        }
        
        .btn-action.btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-action.btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        
        .btn-action.btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-action.btn-danger:hover {
            background: #B91C1C;
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
            font-weight: 700;
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
        
        /* ================================================================
           BRAND HEADER
           ================================================================ */
        .brand-header {
            text-align: center;
            padding: 16px 0 12px 0;
            border-bottom: 3px solid var(--primary);
            margin-bottom: 20px;
        }
        
        .brand-header .brand-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .brand-header .brand-logo img {
            height: 50px;
            width: auto;
            object-fit: contain;
        }
        
        .brand-header .brand-name {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.5px;
        }
        
        .brand-header .brand-tagline {
            font-size: 0.75rem;
            color: var(--text-secondary);
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
        
        .brand-header .brand-admin-row {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid var(--border-color);
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .brand-header .brand-admin-row span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .brand-header .brand-admin-row i {
            color: var(--primary-light);
        }
        
        .brand-header .brand-admin-row .admin-phone {
            color: var(--primary);
            font-weight: 600;
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
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ================================================================
           PDF MODAL
           ================================================================ */
        .pdf-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
        }
        .pdf-modal-overlay.active { display: flex; }
        
        .pdf-modal {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            width: 95%;
            max-width: 1100px;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .pdf-modal-header {
            padding: 14px 22px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: var(--primary-gradient);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        
        .pdf-modal-header .modal-title {
            font-size: 1rem;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .pdf-modal-header .modal-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .pdf-modal-header .modal-actions .btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .pdf-modal-header .modal-actions .btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .pdf-modal-header .modal-actions .btn-danger-modal {
            background: rgba(220,38,38,0.3);
            border-color: rgba(220,38,38,0.2);
        }
        
        .pdf-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 28px;
            background: var(--bg-body);
        }
        
        .pdf-modal-body .pdf-content {
            max-width: 100%;
            font-size: 14px;
            background: var(--bg-card);
            padding: 24px 28px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            line-height: 1.5;
            margin-top: 0;
            padding-top: 28px;
        }
        
        /* PDF Styles */
        .pdf-content .pdf-section {
            page-break-inside: avoid;
            break-inside: avoid;
            margin: 0.5rem 0;
            padding: 0.3rem 0;
        }
        
        .pdf-content .pdf-table-wrap {
            page-break-inside: avoid;
            break-inside: avoid;
            overflow-x: auto;
        }
        
        .pdf-content .pdf-header-section {
            page-break-after: avoid;
            break-after: avoid;
            margin-top: 0;
            padding-top: 0;
        }
        
        .pdf-content .pdf-header {
            text-align: center;
            padding-bottom: 12px;
            border-bottom: 3px solid var(--primary);
            margin-bottom: 16px;
            page-break-after: avoid;
            break-after: avoid;
            margin-top: 0;
            padding-top: 0;
        }
        
        .pdf-content .pdf-header .pdf-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: 4px;
        }
        
        .pdf-content .pdf-header .pdf-logo img {
            height: 55px;
            width: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        
        .pdf-content .pdf-header .clinic-name {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.5px;
            margin-top: 4px;
        }
        
        .pdf-content .pdf-header .clinic-sub {
            font-size: 0.75rem;
            color: var(--text-secondary);
            letter-spacing: 0.5px;
        }
        
        .pdf-content .pdf-header .doc-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--primary);
            margin-top: 4px;
            background: var(--primary-bg);
            padding: 4px 16px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .pdf-content .section-title-pdf {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--primary);
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 4px;
            margin: 6px 0 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            page-break-after: avoid;
            break-after: avoid;
        }
        
        .pdf-content .pdf-row {
            display: flex;
            padding: 2px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }
        
        .pdf-content .pdf-row .pdf-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 130px;
            flex-shrink: 0;
            font-size: 14px;
        }
        
        .pdf-content .pdf-row .pdf-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 14px;
            word-wrap: break-word;
        }
        
        .pdf-content .pdf-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px 14px;
        }
        
        .pdf-content .pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin: 4px 0;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .pdf-content .pdf-table th {
            background: var(--green-header);
            color: white;
            padding: 8px 10px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            border: 1px solid var(--green-header-dark);
            vertical-align: middle;
        }
        
        .pdf-content .pdf-table th.center {
            text-align: center;
        }
        
        .pdf-content .pdf-table th.right {
            text-align: right;
        }
        
        .pdf-content .pdf-table td {
            padding: 8px 10px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
            vertical-align: top;
            line-height: 1.6;
        }
        
        .pdf-content .pdf-table td.center {
            text-align: center;
            vertical-align: middle;
        }
        
        .pdf-content .pdf-table td.right {
            text-align: right;
            vertical-align: middle;
        }
        
        .pdf-content .pdf-table td.instructions-cell {
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            background: #FFFBEB;
            border-left: 4px solid #D97706;
            vertical-align: top;
            text-align: left;
            padding: 8px 10px;
            width: 40%;
        }
        
        [data-theme="dark"] .pdf-content .pdf-table td.instructions-cell {
            background: #2D2A1A;
            border-left-color: #FBBF24;
        }
        
        [data-theme="dark"] .pdf-content .pdf-table tr:nth-child(even) td.instructions-cell {
            background: #2A2518;
        }
        
        .pdf-content .pdf-table td.instructions-cell .inst-text {
            display: block;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.8;
            font-size: 13px;
            text-align: left;
            vertical-align: top;
            padding: 2px 4px;
            color: #1E293B;
        }
        
        [data-theme="dark"] .pdf-content .pdf-table td.instructions-cell .inst-text {
            color: #F1F5F9;
        }
        
        .pdf-content .pdf-table td.instructions-cell .inst-text .inst-icon {
            font-weight: 600;
            color: #D97706;
            margin-right: 4px;
        }
        
        .pdf-content .pdf-table td.instructions-cell .inst-empty {
            color: #94A3B8;
            font-style: italic;
            text-align: left;
            display: block;
            padding: 2px 4px;
        }
        
        .pdf-content .pdf-table tr:nth-child(even) td {
            background: var(--gray-50);
        }
        
        .pdf-content .pdf-table tr:nth-child(even) td.instructions-cell {
            background: #FFF8E7;
        }
        
        .pdf-content .pdf-empty {
            padding: 6px 0;
            color: var(--text-secondary);
            font-style: italic;
            font-size: 14px;
            text-align: center;
            background: var(--gray-50);
            border-radius: 4px;
            margin: 2px 0;
        }
        
        .pdf-content .pdf-footer {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 2px solid var(--border-color);
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        .pdf-content .pdf-footer .footer-stamp {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .pdf-content .pdf-footer .footer-left {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .pdf-content .pdf-footer .footer-left .signature-line {
            display: inline-block;
            width: 120px;
            border-bottom: 1px solid var(--text-secondary);
            margin-left: 4px;
        }
        
        .pdf-content .pdf-footer .stamp-box {
            text-align: center;
            padding: 6px 14px;
            border: 3px solid var(--primary);
            border-radius: 10px;
            background: var(--primary-bg);
            min-width: 150px;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-title {
            font-size: 10px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-name {
            font-size: 14px;
            font-weight: 800;
            color: var(--primary);
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-line {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-date {
            font-size: 10px;
            color: var(--text-muted);
            margin-top: 2px;
        }
        
        .pdf-content .pdf-footer .footer-bottom {
            text-align: center;
            margin-top: 6px;
            font-size: 12px;
            color: var(--text-muted);
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .info-grid { grid-template-columns: 1fr 1fr; }
            .medicines-list .list-header,
            .medicines-list .list-item {
                grid-template-columns: 1.5fr 0.6fr 0.8fr 0.8fr 1.5fr;
                gap: 6px;
            }
        }
        
        @media (max-width: 768px) {
            .main-content { padding: 12px; }
            .page-header { padding: 16px 18px; flex-direction: column; align-items: flex-start; }
            .page-header .page-title { font-size: 1.2rem; }
            .page-header .header-actions { width: 100%; }
            .page-header .header-actions .btn-outline-light { flex: 1; justify-content: center; }
            .detail-card { padding: 16px 18px; }
            .info-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .medicines-list .list-header { display: none; }
            .medicines-list .list-item {
                grid-template-columns: 1fr;
                gap: 4px;
                padding: 12px 16px;
            }
            .medicines-list .list-item .med-qty,
            .medicines-list .list-item .med-price,
            .medicines-list .list-item .med-total {
                text-align: left;
            }
            .medicines-list .list-item .med-qty::before { content: "Qty: "; font-weight: 400; color: var(--text-secondary); font-size: 0.7rem; }
            .medicines-list .list-item .med-price::before { content: "Price: "; font-weight: 400; color: var(--text-secondary); font-size: 0.7rem; }
            .medicines-list .list-item .med-total::before { content: "Total: "; font-weight: 400; color: var(--text-secondary); font-size: 0.7rem; }
            .summary-box { flex-direction: column; align-items: stretch; }
            .summary-box .summary-left { justify-content: center; }
            .summary-box .summary-right { justify-content: center; }
            .action-buttons { flex-direction: column; align-items: stretch; }
            .action-buttons .btn-action { justify-content: center; }
            .brand-header .brand-name { font-size: 1.2rem; }
            .brand-header .brand-logo img { height: 40px; }
            .brand-header .brand-admin-row { gap: 8px; font-size: 0.55rem; }
            .pdf-modal { width: 98%; max-height: 98vh; }
            .pdf-modal-body { padding: 12px; }
            .pdf-modal-body .pdf-content { padding: 14px 16px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .page-header { padding: 12px 14px; }
            .page-header .page-title { font-size: 1rem; }
            .detail-card { padding: 12px 14px; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- BRAND HEADER -->
    <!-- ================================================================ -->
    <div class="brand-header no-print">
        <div class="brand-logo">
            <img src="<?= $logo_path ?>" alt="Braick Logo" onerror="this.style.display='none'">
            <span class="brand-name">BRAICK DISPENSARY</span>
        </div>
        <div class="brand-tagline">Tunajali Afya Yako</div>
        <div class="brand-admin-row">
            <span>
                <i class="fas fa-phone-alt"></i> 
                Admin Contacts: 
                <?php if (!empty($admin_phones)): ?>
                    <?php foreach ($admin_phones as $index => $phone): ?>
                        <span class="admin-phone"><?= htmlspecialchars($phone) ?></span><?= $index < count($admin_phones) - 1 ? ' | ' : '' ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="admin-phone"><?= htmlspecialchars($branch_phone ?? '+255 700 000 001') ?></span>
                <?php endif; ?>
            </span>
            <span><i class="fas fa-building"></i> Branch: <?= htmlspecialchars($user_branch_name) ?></span>
            <span><i class="fas fa-calendar-alt"></i> <?= date('F d, Y') ?></span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-shopping-cart"></i>
                OTC Sale Details
                <span class="role-badge-display">PHARMACY</span>
            </h1>
            <div class="page-subtitle">
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="type-badge">
                    <i class="fas fa-shopping-cart"></i> OTC
                </span>
            </div>
        </div>
        <div class="header-actions">
            <a href="otc_history.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="generatePDF()" class="btn-outline-light pdf-btn">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- OTC SALE DETAILS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <!-- Card Header -->
        <div class="card-header">
            <div class="sale-number">
                <span class="sale-id"><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></span>
                <span class="status-badge <?= $sale['payment_status'] ?? 'pending' ?>">
                    <?php 
                        $status = $sale['payment_status'] ?? 'pending';
                        if ($status === 'paid') {
                            echo '<i class="fas fa-check-circle"></i> Paid';
                        } elseif ($status === 'pending') {
                            echo '<i class="fas fa-clock"></i> Pending';
                        } elseif ($status === 'cancelled') {
                            echo '<i class="fas fa-times-circle"></i> Cancelled';
                        } elseif ($status === 'partial') {
                            echo '<i class="fas fa-money-bill-wave"></i> Partial';
                        } else {
                            echo ucfirst($status);
                        }
                    ?>
                </span>
            </div>
            <div class="sale-date">
                <i class="fas fa-calendar-alt"></i>
                <?= date('M d, Y h:i A', strtotime($sale['created_at'] ?? 'now')) ?>
            </div>
        </div>
        
        <!-- Customer Information -->
        <div class="info-grid">
            <div class="info-item">
                <div class="label"><i class="fas fa-user"></i> Customer</div>
                <div class="value"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></div>
                <span class="sub">Phone: <?= htmlspecialchars($sale['customer_phone'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($sale['patient_full_name'])): ?>
                <div class="info-item">
                    <div class="label"><i class="fas fa-id-card"></i> Registered Patient</div>
                    <div class="value"><?= htmlspecialchars($sale['patient_full_name']) ?></div>
                    <span class="sub">ID: <?= htmlspecialchars($sale['patient_number'] ?? 'N/A') ?></span>
                </div>
            <?php endif; ?>
            <div class="info-item">
                <div class="label"><i class="fas fa-user-tie"></i> Cashier</div>
                <div class="value"><?= htmlspecialchars($sale['cashier_name'] ?? 'Unknown') ?></div>
                <span class="sub">Phone: <?= htmlspecialchars($sale['cashier_phone'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <div class="label"><i class="fas fa-credit-card"></i> Payment Method</div>
                <div class="value">
                    <span style="background:var(--primary-bg);color:var(--primary);padding:2px 14px;border-radius:20px;font-size:0.8rem;font-weight:600;">
                        <?= ucfirst(str_replace('_', ' ', $sale['payment_method'] ?? 'cash')) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MEDICINES LIST -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="section-title">
            <i class="fas fa-pills"></i>
            Medicines Dispensed
            <span class="badge-count"><?= count($items) ?> item(s)</span>
        </div>
        
        <?php if (count($items) > 0): ?>
            <div class="medicines-list">
                <!-- Header -->
                <div class="list-header">
                    <span><i class="fas fa-capsules"></i> Medication</span>
                    <span style="text-align:center;"><i class="fas fa-cubes"></i> Qty</span>
                    <span style="text-align:right;"><i class="fas fa-tag"></i> Unit Price</span>
                    <span style="text-align:right;"><i class="fas fa-calculator"></i> Total</span>
                    <span><i class="fas fa-info-circle"></i> Instructions</span>
                </div>
                
                <!-- Items -->
                <?php foreach ($items as $item): ?>
                    <div class="list-item">
                        <div>
                            <div class="med-name">
                                <?= htmlspecialchars($item['item_name'] ?? $item['medicine_name'] ?? $item['inventory_medication_name'] ?? 'N/A') ?>
                            </div>
                            <?php if (!empty($item['batch_number'])): ?>
                                <div class="med-batch"><i class="fas fa-barcode"></i> Batch: <?= htmlspecialchars($item['batch_number']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="med-qty"><?= $item['quantity'] ?? 0 ?></div>
                        <div class="med-price">TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></div>
                        <div class="med-total">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></div>
                        <div>
                            <?php if (!empty($item['instructions'])): ?>
                                <div class="med-instructions">
                                    <i class="fas fa-info-circle"></i>
                                    <?= nl2br(htmlspecialchars($item['instructions'])) ?>
                                </div>
                            <?php else: ?>
                                <span style="color:var(--text-secondary);font-size:0.7rem;">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription"></i>
                <p>No medicines found for this sale</p>
            </div>
        <?php endif; ?>
        
        <!-- ================================================================ -->
        <!-- SUMMARY BOX -->
        <!-- ================================================================ -->
        <div class="summary-box">
            <div class="summary-left">
                <span class="total-label">
                    <i class="fas fa-pills"></i> Total Items
                </span>
                <span class="total-items">
                    <strong><?= count($items) ?></strong> medicine(s)
                </span>
            </div>
            <div class="summary-right">
                <div class="amount-box">
                    <div class="amount-label">Subtotal</div>
                    <div class="amount-value">TSh <?= number_format($sale['subtotal'] ?? 0, 0) ?></div>
                </div>
                <?php if (($sale['discount_amount'] ?? 0) > 0): ?>
                    <div class="amount-box">
                        <div class="amount-label">Discount</div>
                        <div class="amount-value discount">-TSh <?= number_format($sale['discount_amount'] ?? 0, 0) ?></div>
                    </div>
                <?php endif; ?>
                <div class="amount-box" style="background:var(--primary-bg);border-color:var(--primary-light);">
                    <div class="amount-label" style="color:var(--primary);">Total</div>
                    <div class="amount-value" style="color:var(--primary);font-size:1.1rem;">TSh <?= number_format($sale['total_amount'] ?? 0, 0) ?></div>
                </div>
            </div>
        </div>
        
        <!-- ================================================================ -->
        <!-- ACTION BUTTONS -->
        <!-- ================================================================ -->
        <div class="action-buttons">
            <button onclick="generatePDF()" class="btn-action btn-primary">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
            <a href="otc_history.php" class="btn-action btn-outline">
                <i class="fas fa-arrow-left"></i> Back to History
            </a>
            <?php if (($sale['payment_status'] ?? '') === 'pending'): ?>
                <button onclick="confirmCancel(<?= $sale['id'] ?>)" class="btn-action btn-danger">
                    <i class="fas fa-times"></i> Cancel Sale
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            OTC Sale Details
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            <span id="footerTime"><?= date('h:i:s A') ?></span>
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PDF MODAL -->
<!-- ================================================================ -->
<div class="pdf-modal-overlay" id="pdfModal">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <div class="modal-title">
                <i class="fas fa-file-pdf" style="color:rgba(255,255,255,0.8);"></i>
                OTC Sale PDF Preview - <?= htmlspecialchars($sale['sale_number'] ?? 'OTC Sale') ?>
            </div>
            <div class="modal-actions">
                <button onclick="downloadPDF()" class="btn btn-sm">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="window.print()" class="btn btn-sm">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="closePDFModal()" class="btn btn-sm btn-danger-modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
        <div class="pdf-modal-body" id="pdfModalBody">
            <div class="pdf-content" id="pdfContent">
                <!-- PDF content generated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT - NO DARK MODE TOGGLE HERE (USES HEADER TOGGLE) -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    (function() {
        function initToggle() {
            var toggleBtn = document.getElementById('sidebarToggleBtn');
            var sidebar = document.getElementById('sidebarModern') || document.getElementById('sidebar');
            var overlay = document.getElementById('sidebarOverlayModern') || document.getElementById('sidebarOverlay');
            
            if (!toggleBtn) {
                console.log('⚠️ Sidebar toggle button not found');
                return;
            }
            
            if (!sidebar) {
                console.log('⚠️ Sidebar not found');
                return;
            }
            
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'sidebarOverlay';
                overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:9998;display:none;backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);';
                document.body.appendChild(overlay);
            }
            
            function openSidebar() {
                sidebar.classList.add('open');
                overlay.style.display = 'block';
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i class="fas fa-times"></i><span class="toggle-label">CLOSE</span>';
                }
                console.log('🔓 Sidebar opened');
            }
            
            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                overlay.classList.remove('active');
                document.body.style.overflow = '';
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i class="fas fa-bars"></i><span class="toggle-label">MENU</span>';
                }
                console.log('🔒 Sidebar closed');
            }
            
            function toggleSidebar() {
                if (sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }
            
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
            
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        closeSidebar();
                    }
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
            
            console.log('✅ Sidebar toggle initialized');
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initToggle);
        } else {
            initToggle();
        }
    })();

    // ================================================================
    // TOGGLE NOTIFICATION DROPDOWN
    // ================================================================
    function toggleNotifications() {
        var dropdown = document.getElementById('notifDropdown');
        if (dropdown) {
            dropdown.classList.toggle('open');
        }
    }

    document.addEventListener('click', function(e) {
        var wrapper = document.querySelector('.notif-bell-wrapper');
        var dropdown = document.getElementById('notifDropdown');
        if (wrapper && dropdown) {
            if (!wrapper.contains(e.target)) {
                dropdown.classList.remove('open');
            }
        }
    });

    // ================================================================
    // MARK NOTIFICATION AS READ
    // ================================================================
    function markNotificationRead(id, event) {
        if (event) event.preventDefault();
        if (!id) return;
        
        fetch('../../backend/api/mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function markAllRead(event) {
        if (event) event.preventDefault();
        
        fetch('../../backend/api/mark_all_notifications_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // ================================================================
    // DATE & TIME - UPDATES EVERY SECOND
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) {
            ftEl.textContent = timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

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
    // CANCEL SALE
    // ================================================================
    function confirmCancel(saleId) {
        if (confirm('Are you sure you want to cancel this OTC sale?')) {
            window.location.href = 'cancel_otc_sale.php?id=' + saleId;
        }
    }

    // ================================================================
    // GENERATE PDF
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        
        var itemsHTML = '';
        <?php if (count($items) > 0): ?>
            <?php foreach ($items as $item): 
                $item_name = addslashes($item['item_name'] ?? $item['medicine_name'] ?? $item['inventory_medication_name'] ?? 'N/A');
                $batch = addslashes($item['batch_number'] ?? '');
                $instructions = addslashes($item['instructions'] ?? '—');
                $quantity = $item['quantity'] ?? 0;
                $unit_price = $item['unit_price'] ?? 0;
                $total_price = $item['total_price'] ?? 0;
            ?>
                itemsHTML += `
                    <tr>
                        <td style="padding:8px 10px;border-bottom:1px solid #E2E8F0;font-size:13px;vertical-align:top;width:22%;">
                            <strong style="font-size:14px;color:#0B5ED7;"><?= htmlspecialchars($item_name) ?></strong>
                            <?php if (!empty($batch)): ?>
                                <div style="font-size:10px;color:#94A3B8;margin-top:2px;">📦 Batch: <?= htmlspecialchars($batch) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="padding:8px 10px;border-bottom:1px solid #E2E8F0;font-size:14px;text-align:center;vertical-align:middle;width:8%;">
                            <span style="font-weight:700;font-size:15px;"><?= $quantity ?></span>
                        </td>
                        <td style="padding:8px 10px;border-bottom:1px solid #E2E8F0;font-size:13px;text-align:right;vertical-align:middle;width:15%;">
                            TSh <?= number_format($unit_price, 0) ?>
                        </td>
                        <td style="padding:8px 10px;border-bottom:1px solid #E2E8F0;font-size:13px;text-align:right;vertical-align:middle;width:15%;">
                            <span style="font-weight:700;color:#059669;font-size:14px;">TSh <?= number_format($total_price, 0) ?></span>
                        </td>
                        <td style="padding:8px 10px;border-bottom:1px solid #E2E8F0;font-size:13px;vertical-align:top;width:40%;background:#FFFBEB;border-left:4px solid #D97706;text-align:left;">
                            <?php if (!empty($instructions) && $instructions !== '—'): ?>
                                <div style="display:block;white-space:pre-wrap;word-wrap:break-word;line-height:1.8;font-size:13px;color:#1E293B;padding:2px 4px;text-align:left;">
                                    <span style="font-weight:600;color:#D97706;">📋</span>
                                    <?= nl2br(htmlspecialchars($instructions)) ?>
                                </div>
                            <?php else: ?>
                                <span style="color:#94A3B8;font-style:italic;text-align:left;display:block;padding:2px 4px;">— No instructions</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                `;
            <?php endforeach; ?>
        <?php endif; ?>
        
        var html = `
            <!-- PDF HEADER -->
            <div class="pdf-header pdf-header-section" style="text-align:center;padding-bottom:14px;border-bottom:3px solid #0B5ED7;margin-bottom:18px;page-break-after:avoid;break-after:avoid;margin-top:0;padding-top:0;">
                <div class="pdf-logo" style="display:flex;flex-direction:column;align-items:center;justify-content:center;margin-bottom:6px;">
                    <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" style="height:60px;width:auto;object-fit:contain;display:block;margin:0 auto;" onerror="this.style.display='none'">
                    <div style="font-size:1.6rem;font-weight:800;color:#0B5ED7;letter-spacing:-0.5px;margin-top:4px;">BRAICK DISPENSARY</div>
                    <div style="font-size:0.8rem;color:#64748B;letter-spacing:0.5px;">Tunajali Afya Yako</div>
                </div>
                <div style="display:flex;justify-content:center;gap:20px;flex-wrap:wrap;margin-top:6px;padding-top:6px;border-top:1px solid #E2E8F0;font-size:0.6rem;color:#64748B;">
                    <span>📞 Admin Contacts: <?= !empty($admin_phones) ? implode(' | ', $admin_phones) : ($branch_phone ?? '+255 700 000 001') ?></span>
                    <span>🏢 Branch: <?= htmlspecialchars($user_branch_name) ?></span>
                    <span>📅 <?= date('F d, Y') ?></span>
                </div>
                <div style="font-size:0.85rem;font-weight:600;color:#0B5ED7;margin-top:4px;background:#E8F0FE;padding:4px 20px;border-radius:20px;display:inline-block;">
                    💊 OTC Sale Details - <?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?>
                </div>
            </div>
            
            <!-- 1. SALE INFORMATION -->
            <div class="pdf-section">
                <div class="section-title-pdf">
                    <i class="fas fa-info-circle"></i> 1. Sale Information
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 14px;font-size:14px;">
                    <div style="display:flex;padding:4px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Sale Number</span><span style="font-size:14px;"><strong><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></strong></span></div>
                    <div style="display:flex;padding:4px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Status</span><span style="font-size:14px;"><?= ucfirst($sale['payment_status'] ?? 'Pending') ?></span></div>
                    <div style="display:flex;padding:4px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Date & Time</span><span style="font-size:14px;"><?= isset($sale['created_at']) ? date('F d, Y h:i A', strtotime($sale['created_at'])) : 'N/A' ?></span></div>
                    <div style="display:flex;padding:4px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Payment Method</span><span style="font-size:14px;"><?= ucfirst(str_replace('_', ' ', $sale['payment_method'] ?? 'cash')) ?></span></div>
                    <div style="display:flex;padding:4px 0;border-bottom:1px solid #E2E8F0;grid-column:span 2;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Branch</span><span style="font-size:14px;"><?= htmlspecialchars($sale['branch_name'] ?? $user_branch_name) ?></span></div>
                </div>
            </div>
            
            <!-- 2. CUSTOMER INFORMATION -->
            <div class="pdf-section">
                <div class="section-title-pdf">
                    <i class="fas fa-user"></i> 2. Customer Information
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 14px;font-size:14px;">
                    <div style="display:flex;padding:4px 0;border-bottom:1px solid #E2E8F0;grid-column:span 2;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Customer Name</span><span style="font-size:14px;"><strong><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></strong></span></div>
                    <div style="display:flex;padding:4px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Phone</span><span style="font-size:14px;"><?= htmlspecialchars($sale['customer_phone'] ?? 'N/A') ?></span></div>
                    <?php if (!empty($sale['patient_full_name'])): ?>
                        <div style="display:flex;padding:4px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Registered Patient</span><span style="font-size:14px;"><?= htmlspecialchars($sale['patient_full_name']) ?> (<?= htmlspecialchars($sale['patient_number'] ?? 'N/A') ?>)</span></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 3. PHARMACY INFORMATION -->
            <div class="pdf-section">
                <div class="section-title-pdf">
                    <i class="fas fa-user-md"></i> 3. Pharmacy Information
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 14px;font-size:14px;">
                    <div style="display:flex;padding:4px 0;border-bottom:1px solid #E2E8F0;grid-column:span 2;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Cashier Name</span><span style="font-size:14px;"><strong><?= htmlspecialchars($sale['cashier_name'] ?? 'Unknown') ?></strong></span></div>
                    <div style="display:flex;padding:4px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Phone</span><span style="font-size:14px;"><?= htmlspecialchars($sale['cashier_phone'] ?? 'N/A') ?></span></div>
                    <div style="display:flex;padding:4px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Email</span><span style="font-size:14px;"><?= htmlspecialchars($sale['cashier_email'] ?? 'N/A') ?></span></div>
                    <div style="display:flex;padding:4px 0;border-bottom:1px solid #E2E8F0;grid-column:span 2;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Branch Phone</span><span style="font-size:14px;"><?= htmlspecialchars($sale['branch_phone'] ?? $branch_phone ?? 'N/A') ?></span></div>
                </div>
            </div>
            
            <!-- 4. MEDICINES DISPENSED -->
            <div class="pdf-section">
                <div class="section-title-pdf">
                    <i class="fas fa-pills"></i> 4. Medicines Dispensed
                </div>
                <?php if (count($items) > 0): ?>
                <div class="pdf-table-wrap">
                    <table class="pdf-table" style="font-size:14px;width:100%;border-collapse:collapse;">
                        <thead>
                            <tr>
                                <th style="background:#059669;color:white;padding:8px 10px;text-align:left;font-size:12px;width:22%;text-transform:uppercase;letter-spacing:0.05em;vertical-align:middle;">
                                    <i class="fas fa-capsules"></i> Medication
                                </th>
                                <th style="background:#059669;color:white;padding:8px 10px;text-align:center;font-size:12px;width:8%;text-transform:uppercase;letter-spacing:0.05em;vertical-align:middle;">
                                    <i class="fas fa-cubes"></i> Qty
                                </th>
                                <th style="background:#059669;color:white;padding:8px 10px;text-align:right;font-size:12px;width:15%;text-transform:uppercase;letter-spacing:0.05em;vertical-align:middle;">
                                    <i class="fas fa-tag"></i> Unit Price
                                </th>
                                <th style="background:#059669;color:white;padding:8px 10px;text-align:right;font-size:12px;width:15%;text-transform:uppercase;letter-spacing:0.05em;vertical-align:middle;">
                                    <i class="fas fa-calculator"></i> Total
                                </th>
                                <th style="background:#059669;color:white;padding:8px 10px;text-align:left;font-size:12px;width:40%;text-transform:uppercase;letter-spacing:0.05em;vertical-align:middle;">
                                    <i class="fas fa-info-circle"></i> Instructions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            ` + itemsHTML + `
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="pdf-empty">No medicines found for this sale</div>
                <?php endif; ?>
            </div>
            
            <!-- 5. SALE SUMMARY -->
            <div class="pdf-section">
                <div class="section-title-pdf">
                    <i class="fas fa-money-bill-wave"></i> 5. Sale Summary
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:4px 14px;font-size:14px;">
                    <div style="display:flex;padding:4px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Subtotal</span><span style="font-size:14px;text-align:right;">TSh <?= number_format($sale['subtotal'] ?? 0, 0) ?></span></div>
                    <?php if (($sale['discount_amount'] ?? 0) > 0): ?>
                    <div style="display:flex;padding:4px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Discount</span><span style="font-size:14px;text-align:right;color:#DC2626;">- TSh <?= number_format($sale['discount_amount'] ?? 0, 0) ?></span></div>
                    <?php endif; ?>
                    <div style="display:flex;padding:4px 0;border-bottom:1px solid #E2E8F0;background:#E8F0FE;border-radius:4px;padding:4px 8px;"><span style="font-weight:700;color:#0B5ED7;width:130px;flex-shrink:0;font-size:14px;">Total Amount</span><span style="font-weight:700;color:#0B5ED7;font-size:14px;text-align:right;">TSh <?= number_format($sale['total_amount'] ?? 0, 0) ?></span></div>
                    <?php if (!empty($sale['notes'])): ?>
                    <div style="display:flex;padding:4px 0;border-bottom:1px solid #E2E8F0;grid-column:span 3;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;font-size:14px;">Notes</span><span style="font-size:14px;"><?= htmlspecialchars($sale['notes']) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- PDF FOOTER WITH OFFICIAL STAMP -->
            <div class="pdf-footer">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                    <div style="font-size:14px;color:#64748B;">
                        <span>Cashier: _________________</span>
                        <span style="margin-left:14px;">Date: <?= date('F d, Y') ?></span>
                    </div>
                    <div style="text-align:center;padding:6px 18px;border:3px solid #0B5ED7;border-radius:10px;background:#E8F0FE;min-width:150px;">
                        <div style="font-size:10px;color:#64748B;text-transform:uppercase;letter-spacing:1px;font-weight:700;">Official Stamp</div>
                        <div style="font-size:15px;font-weight:800;color:#0B5ED7;">BRAICK DISPENSARY</div>
                        <div style="font-size:12px;color:#64748B;margin-top:2px;">Approved By: _________________</div>
                        <div style="font-size:10px;color:#94A3B8;margin-top:2px;">Date: <?= date('F d, Y') ?></div>
                    </div>
                </div>
                <div style="text-align:center;margin-top:8px;font-size:11px;color:#94A3B8;">
                    Braick Dispensary • Generated on <?= date('F d, Y h:i:s A') ?> • All rights reserved
                </div>
            </div>
        `;
        
        content.innerHTML = html;
        modal.classList.add('active');
        
        var modalBody = document.getElementById('pdfModalBody');
        if (modalBody) {
            modalBody.scrollTop = 0;
        }
    }
    
    function closePDFModal() {
        document.getElementById('pdfModal').classList.remove('active');
    }
    
    function downloadPDF() {
        var element = document.getElementById('pdfContent');
        var opt = {
            margin: [8, 8, 8, 8],
            filename: 'OTC_Sale_<?= htmlspecialchars($sale['sale_number'] ?? 'sale') ?>_<?= $sale['id'] ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2, 
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false,
                allowTaint: true
            },
            jsPDF: { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait' 
            },
            pagebreak: { 
                mode: ['css', 'legacy']
            }
        };
        
        html2pdf().set(opt).from(element).save();
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePDFModal();
        }
    });

    // ================================================================
    // CLICK OUTSIDE TO CLOSE PDF MODAL
    // ================================================================
    document.getElementById('pdfModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePDFModal();
        }
    });

    console.log('%c💊 Braick - View OTC Sale', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c✅ PDF - Instructions Left Aligned & Same Height!', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Dark Mode controlled by Header button', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📋 Sale #: <?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📦 Items: <?= count($items) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Instructions: Left aligned, full display, no truncation!', 'font-size:13px; color:#D97706;');
</script>

</body>
</html>