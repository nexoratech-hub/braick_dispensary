<?php
// ================================================================
// FILE: frontend/pages/reception/services.php
// SERVICES MANAGEMENT (V2 - SHARED HEADER/SIDEBAR)
// ✅ Using shared reception_header.php & reception_sidebar.php
// ✅ ADD ONLY WITH VIEW BUTTON
// ✅ Only CONSULTATION category for reception
// ✅ Money format (1,000,000,000)
// ✅ Newest first
// BRAICK DISPENSARY
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

// ================================================================
// ACCESS CONTROL
// ================================================================
$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor':     header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy':   header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier':    header('Location: ../cashier/dashboard.php'); break;
        default:           header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// USER DATA
// ================================================================
$user_id          = $_SESSION['user_id']     ?? 0;
$user_full_name   = $_SESSION['full_name']   ?? 'User';
$user_role        = $_SESSION['role']        ?? 'reception';
$user_branch_id   = $_SESSION['branch_id']   ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username    = $_SESSION['username']    ?? '';
$user_email       = $_SESSION['email']       ?? '';
$user_phone       = $_SESSION['phone']       ?? '';
$profile_pic      = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET CATEGORIES - ONLY CONSULTATION FOR RECEPTION
// ================================================================
$categories = [];
try {
    $stmt = $db->prepare("SELECT id, category_name FROM service_categories WHERE category_name = 'Consultation' ORDER BY category_name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// ================================================================
// HANDLE ADD SERVICE
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_service') {
        $service_name = trim($_POST['service_name'] ?? '');
        $category_id  = (int)($_POST['category_id'] ?? 0);
        $description  = trim($_POST['description'] ?? '');
        $branch_id    = $user_branch_id;
        $price_raw    = str_replace(',', '', $_POST['price'] ?? '0');
        $price        = (float)$price_raw;
        $is_active    = isset($_POST['is_active']) ? 1 : 0;

        if (empty($service_name)) {
            $message = "Service name is required";
            $message_type = 'error';
        } elseif ($price < 0) {
            $message = "Price cannot be negative";
            $message_type = 'error';
        } elseif ($category_id <= 0) {
            $message = "Please select a category";
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO services (
                        service_name, category_id, description, branch_id,
                        price, is_active, created_by,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $service_name, $category_id, $description, $branch_id,
                    $price, $is_active, $user_id
                ]);

                $message = "Service added successfully to your branch!";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// ================================================================
// GET SERVICE FOR VIEW MODAL
// ================================================================
$view_service = null;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $view_id = (int)$_GET['view'];
    try {
        $stmt = $db->prepare("
            SELECT s.*,
                   c.category_name,
                   b.name as branch_name,
                   u.full_name as created_by_name
            FROM services s
            LEFT JOIN service_categories c ON s.category_id = c.id
            LEFT JOIN branches b ON s.branch_id = b.id
            LEFT JOIN users u ON s.created_by = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$view_id]);
        $view_service = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $view_service = null;
    }
}

// ================================================================
// GET ALL SERVICES - FILTERED BY BRANCH, ONLY CONSULTATION
// ================================================================
$services = [];
try {
    $stmt = $db->prepare("
        SELECT s.*,
               c.category_name,
               b.name as branch_name,
               u.full_name as created_by_name
        FROM services s
        LEFT JOIN service_categories c ON s.category_id = c.id
        LEFT JOIN branches b ON s.branch_id = b.id
        LEFT JOIN users u ON s.created_by = u.id
        WHERE (s.branch_id = ? OR s.branch_id IS NULL)
        AND c.category_name = 'Consultation'
        ORDER BY s.created_at DESC, s.id DESC
    ");
    $stmt->execute([$user_branch_id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $services = [];
}

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $unread_notifications = $result['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PATHS
// ================================================================
$profile_pic_url = !empty($profile_pic)
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// GET BRANCH NAME
// ================================================================
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$user_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $user_branch_name = $branch_data['name'];
    }
} catch (Exception $e) {
    $user_branch_name = 'Branch';
}

// ================================================================
// NOTE: time_ago() is provided by reception_header.php
// DO NOT redeclare here.
// ================================================================

include_once __DIR__ . '/../../components/reception_header.php';
include_once __DIR__ . '/../../components/reception_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Management - Braick Dispensary</title>

    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ================================================================
           SERVICES PAGE - SPECIFIC STYLES ONLY
           (Base styles, variables, .card, .footer, .toast-custom,
            .main-content are provided by reception_header.php)
           ================================================================ */

        /* ---------- PAGE HEADER ---------- */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083B8A 100%);
            border-radius: 20px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 10px 40px rgba(11, 94, 215, 0.3);
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
            background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -80%;
            left: -5%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-header .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            letter-spacing: -0.5px;
        }

        .page-header .page-title i { font-size: 1.8rem; opacity: 0.95; }

        .page-header .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 6px;
        }

        .page-header .page-subtitle strong { color: white; font-weight: 700; }

        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.15);
        }

        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 600;
            backdrop-filter: blur(8px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.15);
        }

        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 9px 18px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(8px);
            position: relative;
            z-index: 1;
            cursor: pointer;
            font-family: inherit;
        }

        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.28);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            color: white;
        }

        /* ---------- STATS ROW ---------- */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-item {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 22px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }

        .stat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #0B5ED7, #0A4CA8);
        }

        .stat-item:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(11, 94, 215, 0.12);
        }

        .stat-item .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 1.2rem;
            background: linear-gradient(135deg, #E8F0FE, #DBEAFE);
            color: #0B5ED7;
        }

        [data-theme="dark"] .stat-item .stat-icon {
            background: linear-gradient(135deg, #1E3A5F, #1E40AF);
            color: #6EA8FE;
        }

        .stat-item .stat-icon.green  { background: linear-gradient(135deg, #D1FAE5, #A7F3D0); color: #059669; }
        .stat-item .stat-icon.red    { background: linear-gradient(135deg, #FEE2E2, #FECACA); color: #DC2626; }
        .stat-item .stat-icon.purple { background: linear-gradient(135deg, #EDE9FE, #DDD6FE); color: #7C3AED; }

        [data-theme="dark"] .stat-item .stat-icon.green  { background: linear-gradient(135deg, #1A3A2A, #065F46); color: #34D399; }
        [data-theme="dark"] .stat-item .stat-icon.red    { background: linear-gradient(135deg, #3A1A1A, #7F1D1D); color: #F87171; }
        [data-theme="dark"] .stat-item .stat-icon.purple { background: linear-gradient(135deg, #2D1B5F, #4C1D95); color: #C4B5FD; }

        .stat-item .stat-number {
            font-size: 1.7rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1;
            letter-spacing: -0.5px;
        }

        .stat-item .stat-number.green    { color: #059669; }
        .stat-item .stat-number.red      { color: #DC2626; }
        .stat-item .stat-number.purple   { color: #7C3AED; }

        .stat-item .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 8px;
        }

        /* ---------- ALERT ---------- */
        .alert {
            padding: 14px 20px;
            border-radius: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.88rem;
            font-weight: 600;
            border: 1px solid transparent;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .alert-success { background: #D1FAE5; color: #059669; border-color: #34D399; }
        .alert-error   { background: #FEE2E2; color: #DC2626; border-color: #F87171; }
        .alert-warning { background: #FEF3C7; color: #D97706; border-color: #FBBF24; }

        [data-theme="dark"] .alert-success { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .alert-error   { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .alert-warning { background: #3D2E0A; color: #FBBF24; }

        /* ---------- CARDS ---------- */
        .modern-card {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-bottom: 24px;
        }

        .modern-card:hover {
            border-color: var(--primary-light);
            box-shadow: 0 10px 30px rgba(11, 94, 215, 0.08);
        }

        .modern-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            flex-wrap: wrap;
            gap: 10px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--border-color);
        }

        .modern-card .card-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modern-card .card-title i { color: var(--primary); font-size: 1.1rem; }

        .modern-card .card-subtitle {
            font-size: 0.72rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* ---------- BRANCH INFO ---------- */
        .branch-info {
            background: linear-gradient(135deg, #E8F0FE, #DBEAFE);
            padding: 12px 18px;
            border-radius: 12px;
            border: 1px solid #6EA8FE;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.82rem;
            color: #0B5ED7;
            font-weight: 600;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        [data-theme="dark"] .branch-info {
            background: linear-gradient(135deg, #1E3A5F, #1E40AF);
            border-color: #3B82F6;
            color: #93C5FD;
        }

        /* ---------- FORM CONTROLS ---------- */
        .form-group { margin-bottom: 16px; }

        .form-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .form-label .required { color: #DC2626; margin-left: 3px; }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 0.85rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
        }

        .form-control.price-input {
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #059669;
        }

        .form-control.price-input:focus {
            border-color: #059669;
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12);
        }

        textarea.form-control { resize: vertical; min-height: 70px; }
        select.form-control { appearance: auto; cursor: pointer; }

        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* ---------- PRICE PREVIEW ---------- */
        .price-preview {
            font-size: 0.72rem;
            color: var(--text-secondary);
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .price-preview .formatted-price {
            font-weight: 800;
            color: #059669;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 0.95rem;
            background: #D1FAE5;
            padding: 4px 14px;
            border-radius: 8px;
            border: 1px solid #34D399;
        }

        [data-theme="dark"] .price-preview .formatted-price {
            background: #1A3A2A;
            color: #6EE7B7;
            border-color: #34D399;
        }

        /* ---------- BUTTONS ---------- */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.82rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
            min-height: 42px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(11, 94, 215, 0.4);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(5, 150, 105, 0.4);
            color: white;
        }

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }

        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .btn-sm { padding: 7px 16px; font-size: 0.75rem; min-height: 36px; }

        /* ---------- TABLE ---------- */
        .table-container {
            overflow-x: auto;
            border-radius: 14px;
            border: 1px solid var(--border-color);
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 700px;
        }

        .table-container thead {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
        }

        .table-container thead th {
            padding: 14px 16px;
            text-align: left;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            color: white;
        }

        .table-container thead th i { margin-right: 6px; opacity: 0.9; }

        .table-container tbody tr {
            transition: all 0.25s ease;
            border-bottom: 1px solid var(--border-color);
        }

        .table-container tbody tr:last-child { border-bottom: none; }
        .table-container tbody tr:hover { background: var(--primary-bg); }

        [data-theme="dark"] .table-container tbody tr:hover { background: #1E3A5F; }

        .table-container tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            color: var(--text-primary);
        }

        .table-container .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .table-container .status-badge.active {
            background: #D1FAE5;
            color: #059669;
            border: 1px solid #34D399;
        }

        .table-container .status-badge.inactive {
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #F87171;
        }

        [data-theme="dark"] .table-container .status-badge.active { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .table-container .status-badge.inactive { background: #3A1A1A; color: #F87171; }

        .table-container .price-display {
            font-weight: 800;
            color: #059669;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 0.9rem;
        }

        .table-container .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #E8F0FE;
            color: #0B5ED7;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 700;
            border: 1px solid #6EA8FE;
        }

        [data-theme="dark"] .table-container .category-badge {
            background: #1E3A5F;
            color: #93C5FD;
            border-color: #3B82F6;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            text-decoration: none;
        }

        .btn-icon.view {
            background: linear-gradient(135deg, #E8F0FE, #DBEAFE);
            color: #0B5ED7;
            border: 1px solid #6EA8FE;
        }

        .btn-icon.view:hover {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            transform: translateY(-2px) scale(1.08);
            box-shadow: 0 6px 16px rgba(11, 94, 215, 0.4);
        }

        /* ---------- NEW TAG ---------- */
        .new-tag {
            display: inline-block;
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.55rem;
            font-weight: 800;
            margin-left: 8px;
            animation: pulse-new 2s infinite;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @keyframes pulse-new {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%      { opacity: 0.7; transform: scale(1.05); }
        }

        /* ---------- EMPTY STATE ---------- */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3.5rem;
            color: var(--primary);
            opacity: 0.3;
            display: block;
            margin-bottom: 16px;
            animation: float-icon 3s ease-in-out infinite;
        }

        @keyframes float-icon {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-10px); }
        }

        .empty-state p { font-size: 0.95rem; font-weight: 600; color: var(--text-primary); }

        /* ---------- MODAL ---------- */
        .modal-overlay {
            display: <?= $view_service ? 'flex' : 'none' ?>;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(6px);
            animation: fadeIn 0.3s ease;
            padding: 20px;
        }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        @keyframes modalSlideIn {
            from { transform: translateY(-30px) scale(0.95); opacity: 0; }
            to   { transform: translateY(0) scale(1); opacity: 1; }
        }

        .modal-content {
            background: var(--bg-card);
            border-radius: 20px;
            max-width: 600px;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
            padding: 32px 36px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
            animation: modalSlideIn 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 16px;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-header h2 i { color: var(--primary); }

        .modal-close {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: none;
            background: #FEE2E2;
            color: #DC2626;
            cursor: pointer;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        [data-theme="dark"] .modal-close { background: #3A1A1A; color: #F87171; }

        .modal-close:hover {
            background: #DC2626;
            color: white;
            transform: rotate(90deg);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
        }

        .modal-body .detail-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-body .detail-row:last-child { border-bottom: none; }

        .modal-body .detail-label {
            font-weight: 700;
            color: var(--text-secondary);
            width: 140px;
            flex-shrink: 0;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .modal-body .detail-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 0.88rem;
            font-weight: 600;
        }

        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 1024px) {
            .form-row-2 { grid-template-columns: 1fr; }
            .modal-content { padding: 22px; }
        }

        @media (max-width: 768px) {
            .page-header { padding: 18px 20px; flex-direction: column; align-items: flex-start; }
            .page-header .page-title { font-size: 1.25rem; }
            .modern-card { padding: 16px; border-radius: 14px; }
            .stats-row { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-item { padding: 14px 12px; border-radius: 12px; }
            .stat-item .stat-number { font-size: 1.35rem; }
            .table-container table { font-size: 0.75rem; }
            .table-container thead th,
            .table-container tbody td { padding: 10px 10px; }
            .modal-body .detail-row { flex-direction: column; }
            .modal-body .detail-label { width: 100%; margin-bottom: 4px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
        }

        @media (max-width: 480px) {
            .stats-row { grid-template-columns: 1fr; }
            .modern-card { padding: 14px; }
            .modal-content { padding: 18px; }
            .modal-header h2 { font-size: 1.1rem; }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }

        /* Form actions wrapper */
        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SHARED HEADER & SIDEBAR (INCLUDED ABOVE) -->
<!-- ================================================================ -->

<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-concierge-bell"></i>
                Services Management
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
                <span class="header-badge">
                    <i class="fas fa-tag"></i> Consultation Only
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-list"></i>
                Manage <strong>Consultation</strong> services for <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-concierge-bell"></i>
                    <?= count($services) ?> Total
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="scrollToForm()" class="btn-outline-light">
                <i class="fas fa-plus"></i> Add Service
            </button>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS ROW -->
    <!-- ================================================================ -->
    <div class="stats-row animate-fade-in-up">
        <div class="stat-item">
            <div class="stat-icon"><i class="fas fa-concierge-bell"></i></div>
            <div class="stat-number"><?= count($services) ?></div>
            <div class="stat-label">Total Services</div>
        </div>
        <div class="stat-item">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number green"><?= count(array_filter($services, function($s) { return $s['is_active'] == 1; })) ?></div>
            <div class="stat-label">Active Services</div>
        </div>
        <div class="stat-item">
            <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
            <div class="stat-number red"><?= count(array_filter($services, function($s) { return $s['is_active'] == 0; })) ?></div>
            <div class="stat-label">Inactive Services</div>
        </div>
        <div class="stat-item">
            <?php $total_value = array_sum(array_column($services, 'price')); ?>
            <div class="stat-icon purple"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-number purple" style="font-size:1.2rem;">TSh <?= number_format($total_value, 0) ?></div>
            <div class="stat-label">Total Value</div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>" style="font-size:1.1rem;"></i>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ADD SERVICE FORM -->
    <!-- ================================================================ -->
    <div id="serviceForm" class="modern-card animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-plus-circle"></i>
                Add New Consultation Service
            </h3>
            <span class="card-subtitle">
                <i class="fas fa-info-circle"></i> Fill in the details below
            </span>
        </div>

        <div class="branch-info">
            <i class="fas fa-store"></i>
            <strong>Branch:</strong> <?= htmlspecialchars($user_branch_name) ?>
            <span style="font-size:0.72rem;opacity:0.8;">(Auto-assigned to your branch)</span>
            <span style="margin-left:auto;color:#059669;font-weight:800;">
                <i class="fas fa-check-circle"></i> Category: Consultation
            </span>
        </div>

        <form method="POST" action="" id="serviceFormElement">
            <input type="hidden" name="action" value="add_service">

            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Service Name <span class="required">*</span></label>
                    <input type="text" name="service_name" class="form-control"
                           placeholder="Enter service name..." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Category <span class="required">*</span></label>
                    <select name="category_id" class="form-control" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" selected>
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (empty($categories)): ?>
                            <option value="" disabled>No categories available</option>
                        <?php endif; ?>
                    </select>
                    <small style="font-size:0.68rem;color:var(--text-secondary);display:block;margin-top:6px;">
                        <i class="fas fa-info-circle"></i> Reception can only add Consultation services
                    </small>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2"
                          placeholder="Service description..."></textarea>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Price (TSh) <span class="required">*</span></label>
                    <input type="text" name="price" class="form-control price-input"
                           id="priceInput" placeholder="e.g. 1,000,000"
                           value="0" required
                           oninput="formatPriceInput(this)">
                    <div class="price-preview">
                        <span>Formatted:</span>
                        <span class="formatted-price" id="pricePreview">TSh 0</span>
                    </div>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:8px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.85rem;font-weight:700;color:var(--text-primary);padding:10px 16px;background:var(--bg-body);border-radius:12px;border:2px solid var(--border-color);width:100%;">
                        <input type="checkbox" name="is_active" value="1" checked style="width:18px;height:18px;accent-color:#059669;">
                        <span><i class="fas fa-check-circle" style="color:#059669;"></i> Active</span>
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Add Service
                </button>
                <button type="reset" class="btn btn-outline" onclick="resetPriceInput()">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- SERVICES TABLE -->
    <!-- ================================================================ -->
    <div class="modern-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-table"></i>
                All Consultation Services
                <span class="card-subtitle" id="serviceCount">(<?= count($services) ?> services)</span>
                <span class="card-subtitle" style="font-style:italic;">— Newest first</span>
            </h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button onclick="exportServices()" class="btn btn-outline btn-sm">
                    <i class="fas fa-download"></i> Export CSV
                </button>
            </div>
        </div>

        <div class="table-container">
            <table id="servicesTable">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> ID</th>
                        <th><i class="fas fa-tag"></i> Service Name</th>
                        <th><i class="fas fa-folder"></i> Category</th>
                        <th><i class="fas fa-money-bill"></i> Price</th>
                        <th><i class="fas fa-calendar-plus"></i> Added</th>
                        <th><i class="fas fa-toggle-on"></i> Status</th>
                        <th><i class="fas fa-eye"></i> Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($services) > 0): ?>
                        <?php foreach ($services as $service):
                            $is_new = (strtotime($service['created_at']) > strtotime('-7 days'));
                        ?>
                            <tr>
                                <td><span style="font-family:monospace;font-size:0.78rem;font-weight:700;color:var(--text-secondary);">#<?= $service['id'] ?></span></td>
                                <td>
                                    <strong style="font-size:0.9rem;color:var(--text-primary);"><?= htmlspecialchars($service['service_name']) ?></strong>
                                    <?php if ($is_new): ?>
                                        <span class="new-tag">NEW</span>
                                    <?php endif; ?>
                                    <?php if (!empty($service['description'])): ?>
                                        <div style="font-size:0.72rem;color:var(--text-secondary);margin-top:4px;">
                                            <?= htmlspecialchars(substr($service['description'], 0, 60)) ?><?= strlen($service['description']) > 60 ? '...' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="category-badge">
                                        <i class="fas fa-stethoscope"></i>
                                        <?= htmlspecialchars($service['category_name'] ?? 'Consultation') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="price-display">TSh <?= number_format($service['price'] ?? 0, 0) ?></span>
                                </td>
                                <td>
                                    <span style="font-size:0.75rem;color:var(--text-secondary);font-weight:600;">
                                        <i class="fas fa-calendar-alt" style="opacity:0.5;"></i>
                                        <?= date('M d, Y', strtotime($service['created_at'] ?? 'now')) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $service['is_active'] ? 'active' : 'inactive' ?>">
                                        <i class="fas <?= $service['is_active'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                        <?= $service['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?view=<?= $service['id'] ?>" class="btn-icon view" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-concierge-bell"></i>
                                    <p>No consultation services found</p>
                                    <p style="font-size:0.82rem;color:var(--text-secondary);margin-top:6px;">
                                        Click "Add Service" to create your first consultation service
                                    </p>
                                </div>
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
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            Consultation Services
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            <span id="footerTimestamp"><?= date('h:i:s A') ?></span>
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- VIEW SERVICE MODAL -->
<!-- ================================================================ -->
<?php if ($view_service): ?>
<div class="modal-overlay" id="viewModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>
                <i class="fas fa-eye"></i>
                Service Details
            </h2>
            <button class="modal-close" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="detail-row">
                <span class="detail-label">ID</span>
                <span class="detail-value">#<?= $view_service['id'] ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Service Name</span>
                <span class="detail-value" style="font-size:1rem;"><?= htmlspecialchars($view_service['service_name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Category</span>
                <span class="detail-value">
                    <span class="category-badge">
                        <i class="fas fa-stethoscope"></i>
                        <?= htmlspecialchars($view_service['category_name'] ?? 'Consultation') ?>
                    </span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Description</span>
                <span class="detail-value"><?= nl2br(htmlspecialchars($view_service['description'] ?? 'No description')) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Price</span>
                <span class="detail-value" style="color:#059669;font-size:1.05rem;font-family:'JetBrains Mono',monospace;">
                    TSh <?= number_format($view_service['price'] ?? 0, 0) ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Branch</span>
                <span class="detail-value"><?= htmlspecialchars($view_service['branch_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <span class="status-badge <?= $view_service['is_active'] ? 'active' : 'inactive' ?>">
                        <i class="fas <?= $view_service['is_active'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                        <?= $view_service['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Created By</span>
                <span class="detail-value"><?= htmlspecialchars($view_service['created_by_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Created At</span>
                <span class="detail-value"><?= date('M d, Y h:i A', strtotime($view_service['created_at'] ?? 'now')) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Updated At</span>
                <span class="detail-value"><?= date('M d, Y h:i A', strtotime($view_service['updated_at'] ?? 'now')) ?></span>
            </div>
        </div>
        <div style="margin-top:24px;text-align:center;">
            <button class="btn btn-primary" onclick="closeModal()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    // ================================================================
    // MONEY FORMAT - Format price with commas (1,000,000,000)
    // ================================================================
    function formatPriceInput(input) {
        var raw = input.value.replace(/[^0-9]/g, '');
        if (raw === '') {
            input.value = '';
            var preview = document.getElementById('pricePreview');
            if (preview) preview.textContent = 'TSh 0';
            return;
        }
        var formatted = parseInt(raw).toLocaleString('en-US');
        input.value = formatted;
        var preview = document.getElementById('pricePreview');
        if (preview) preview.textContent = 'TSh ' + formatted;
    }

    function resetPriceInput() {
        var input = document.getElementById('priceInput');
        if (input) {
            input.value = '0';
            var preview = document.getElementById('pricePreview');
            if (preview) preview.textContent = 'TSh 0';
        }
    }

    // ================================================================
    // CLOCK
    // ================================================================
    function updateClock() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ft = document.getElementById('footerTimestamp');
        if (ft) ft.textContent = timeStr;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // ================================================================
    // SCROLL TO FORM
    // ================================================================
    function scrollToForm() {
        var form = document.getElementById('serviceForm');
        if (form) {
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            var firstInput = form.querySelector('input[name="service_name"]');
            if (firstInput) setTimeout(function() { firstInput.focus(); }, 500);
        }
    }

    // ================================================================
    // CLOSE MODAL
    // ================================================================
    function closeModal() {
        var modal = document.getElementById('viewModal');
        if (modal) modal.style.display = 'none';
        var url = new URL(window.location.href);
        url.searchParams.delete('view');
        window.history.replaceState({}, document.title, url);
    }

    // ================================================================
    // EXPORT CSV
    // ================================================================
    function exportServices() {
        var table = document.getElementById('servicesTable');
        var rows = table.getElementsByTagName('tr');
        var csv = [];

        var headers = ['ID', 'Service Name', 'Category', 'Price (TSh)', 'Date Added', 'Status'];
        csv.push(headers.join(','));

        for (var i = 1; i < rows.length; i++) {
            var row = rows[i];
            var cells = row.getElementsByTagName('td');
            if (row.style.display === 'none') continue;
            if (cells.length < 6) continue;

            var rowData = [];
            for (var j = 0; j < 6 && j < cells.length; j++) {
                var text = cells[j].textContent || cells[j].innerText;
                text = text.replace(/,/g, ';').trim();
                rowData.push('"' + text + '"');
            }
            csv.push(rowData.join(','));
        }

        var blob = new Blob([csv.join('\n')], { type: 'text/csv' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'consultation_services_' + new Date().toISOString().slice(0,10) + '.csv';
        a.click();
        URL.revokeObjectURL(url);

        showToast('Export', 'Consultation services exported successfully!', 'success');
    }

    // ================================================================
    // TOAST (fallback if not defined by header)
    // ================================================================
    if (typeof window.showToast !== 'function') {
        window.showToast = function(title, message, type) {
            var toast = document.getElementById('toast');
            if (!toast) return;
            var toastTitle = document.getElementById('toastTitle');
            var toastMessage = document.getElementById('toastMessage');
            toast.className = 'toast-custom ' + (type || 'info');
            toastTitle.textContent = title;
            toastMessage.textContent = message;
            toast.style.display = 'flex';
            toast.classList.add('show');
            clearTimeout(toast.timeout);
            toast.timeout = setTimeout(function() {
                toast.classList.remove('show');
                setTimeout(function() { toast.style.display = 'none'; }, 400);
            }, 5000);
        };
    }

    // Close modal on background click
    document.addEventListener('click', function(e) {
        var modal = document.getElementById('viewModal');
        if (modal && e.target === modal) closeModal();
    });

    // Close modal on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    console.log('%c🛠️ Braick - Consultation Services (V2)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Services: <?= count($services) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Using shared reception_header.php & reception_sidebar.php', 'font-size:13px; color:#34D399; font-weight:bold;');
</script>

</body>
</html>