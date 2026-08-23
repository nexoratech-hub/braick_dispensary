<?php
// ================================================================
// FILE: frontend/pages/pharmacy/expired.php
// PHARMACY - EXPIRED MEDICINES REPORT
// ✅ USING SHARED HEADER (pharmacy_header.php)
// ✅ AUTO-DISMISS MESSAGES AFTER 5 SECONDS
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
// CHECK SESSION - REDIRECT TO LOGIN IF NOT PHARMACY
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$profile_pic = $_SESSION['profile_pic'] ?? '';

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
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';

// ================================================================
// BUILD QUERY - SHOWS ALL EXPIRED (ACTIVE + INACTIVE)
// ================================================================
$query = "
    SELECT 
        id,
        medication_name,
        category,
        unit,
        quantity,
        reorder_level,
        selling_price,
        unit_cost,
        supplier,
        expiry_date,
        batch_number,
        status,
        created_at,
        updated_at,
        DATEDIFF(CURDATE(), expiry_date) as days_expired
    FROM medications_inventory 
    WHERE branch_id = ?
    AND expiry_date IS NOT NULL 
    AND expiry_date < CURDATE()
";

$params = [$user_branch_id];

// Status filter (active/inactive/all)
if ($status_filter === 'active') {
    $query .= " AND status = 'active'";
} elseif ($status_filter === 'inactive') {
    $query .= " AND status = 'inactive'";
}

// Search filter
if (!empty($search)) {
    $query .= " AND medication_name LIKE ?";
    $params[] = "%$search%";
}

$query .= " ORDER BY expiry_date ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$expired_medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS - NEW DATABASE
// ================================================================

// Total expired (all)
$stmt = $db->prepare("
    SELECT COUNT(*) as count, SUM(quantity) as total_quantity
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND expiry_date IS NOT NULL 
    AND expiry_date < CURDATE()
");
$stmt->execute([$user_branch_id]);
$total_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_expired = $total_data['count'] ?? 0;
$total_expired_units = $total_data['total_quantity'] ?? 0;

// Active expired
$stmt = $db->prepare("
    SELECT COUNT(*) as count, SUM(quantity) as total_quantity
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND expiry_date IS NOT NULL 
    AND expiry_date < CURDATE()
    AND status = 'active'
");
$stmt->execute([$user_branch_id]);
$active_data = $stmt->fetch(PDO::FETCH_ASSOC);
$active_expired = $active_data['count'] ?? 0;
$active_expired_units = $active_data['total_quantity'] ?? 0;

// Inactive expired
$stmt = $db->prepare("
    SELECT COUNT(*) as count, SUM(quantity) as total_quantity
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND expiry_date IS NOT NULL 
    AND expiry_date < CURDATE()
    AND status = 'inactive'
");
$stmt->execute([$user_branch_id]);
$inactive_data = $stmt->fetch(PDO::FETCH_ASSOC);
$inactive_expired = $inactive_data['count'] ?? 0;
$inactive_expired_units = $inactive_data['total_quantity'] ?? 0;

// ================================================================
// GET STATISTICS FOR SIDEBAR - NEW DATABASE
// ================================================================
$pending_prescriptions = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE branch_id = ? AND status = 'pending'
    ");
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
        WHERE branch_id = ? AND quantity > 0 AND quantity <= reorder_level AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_count = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $low_stock_count = 0;
}

$unread_notifications = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expired Medicines - Braick Dispensary</title>
    
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
            --bg-nav: #FFFFFF;
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
            --bg-nav: #1E293B;
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
            background: linear-gradient(135deg, #DC2626, #991B1B);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(220, 38, 38, 0.25);
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
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 16px;
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
        
        .page-header .badge-count {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        /* ================================================================
           STATS CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            border-radius: 16px;
            padding: 18px 20px;
            border: none;
            transition: all 0.3s;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            min-height: 90px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }
        
        .stat-card:active {
            transform: scale(0.97);
        }
        
        .stat-card.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
        .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.gray { background: linear-gradient(135deg, #6B7280, #4B5563); }
        
        .stat-card .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            background: rgba(255,255,255,0.15);
            color: white;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(-5deg);
            background: rgba(255,255,255,0.25);
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.85);
            font-weight: 500;
            margin-top: 2px;
        }
        
        .stat-card .stat-trend {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: rgba(255,255,255,0.15);
            color: white;
            display: inline-block;
            margin-top: 4px;
        }
        
        /* ================================================================
           CARDS
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
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
        }
        
        .card-title .title-red { color: var(--danger); }
        .card-title .title-blue { color: var(--primary); }
        
        .result-count {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .result-count strong {
            color: var(--primary);
        }
        
        /* ================================================================
           FILTERS
           ================================================================ */
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }
        
        .filter-btn {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .filter-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .filter-btn.active:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .filter-btn.red.active {
            background: var(--danger);
            border-color: var(--danger);
        }
        
        .filter-btn.clear-filter {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        .filter-btn.clear-filter:hover {
            background: var(--danger);
            color: white;
        }
        
        /* ================================================================
           SEARCH FORM
           ================================================================ */
        .search-form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-form input[type="text"] {
            padding: 8px 14px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.85rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
            flex: 1;
            min-width: 200px;
        }
        
        .search-form input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .search-form .btn-search {
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            background: var(--primary);
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .search-form .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .search-form .btn-reset {
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .search-form .btn-reset:hover {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        /* ================================================================
           TABLE
           ================================================================ */
        .table-wrap {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--danger);
            border-bottom: 3px solid #991B1B;
            white-space: nowrap;
        }
        
        .data-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .data-table tbody tr:nth-child(even) {
            background: var(--danger-light);
        }
        
        .data-table tbody tr:hover td {
            background: var(--warning-light);
        }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) {
            background: #3A1A1A;
        }
        
        [data-theme="dark"] .data-table tbody tr:hover td {
            background: #3D2E0A;
        }
        
        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .status-badge {
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-badge.active {
            background: var(--success-light);
            color: var(--success);
        }
        
        .status-badge.inactive {
            background: var(--danger-light);
            color: var(--danger);
        }
        
        [data-theme="dark"] .status-badge.active {
            background: #1A3A2A;
            color: #34D399;
        }
        
        [data-theme="dark"] .status-badge.inactive {
            background: #3A1A1A;
            color: #F87171;
        }
        
        .expired-badge {
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 600;
            background: var(--danger-light);
            color: var(--danger);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        [data-theme="dark"] .expired-badge {
            background: #3A1A1A;
            color: #F87171;
        }
        
        .batch-number {
            font-family: monospace;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
            background: var(--primary-light);
            color: var(--primary);
        }
        
        [data-theme="dark"] .batch-number {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .days-expired {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--danger-light);
            color: var(--danger);
        }
        
        [data-theme="dark"] .days-expired {
            background: #3A1A1A;
            color: #F87171;
        }
        
        /* ================================================================
           ACTION BUTTONS - ONLY VIEW
           ================================================================ */
        .action-btn {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .action-btn.view {
            background: var(--purple);
            color: white;
        }
        
        .action-btn.view:hover {
            background: #6D28D9;
            transform: scale(1.05);
        }
        
        .action-btn.delete {
            background: var(--danger);
            color: white;
        }
        
        .action-btn.delete:hover {
            background: #991B1B;
            transform: scale(1.05);
        }
        
        /* ================================================================
           ✅ MESSAGE BOX WITH AUTO-DISMISS
           ================================================================ */
        .message-box {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideDown 0.4s ease;
            transition: opacity 0.5s ease, transform 0.5s ease;
            position: relative;
        }
        
        .message-box.hide {
            opacity: 0;
            transform: translateY(-20px);
            display: none;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message-box.success {
            background: var(--success-light);
            color: #065F46;
            border: 2px solid #6EE7B7;
        }
        
        .message-box.warning {
            background: var(--warning-light);
            color: #92400E;
            border: 2px solid #FCD34D;
        }
        
        .message-box i {
            font-size: 1.3rem;
        }
        
        .message-box .message-close {
            margin-left: auto;
            cursor: pointer;
            font-size: 1.2rem;
            font-weight: 700;
            opacity: 0.6;
            transition: opacity 0.3s ease;
            padding: 0 4px;
        }
        
        .message-box .message-close:hover {
            opacity: 1;
        }
        
        .message-box .message-timer {
            font-size: 0.6rem;
            opacity: 0.6;
            margin-left: 8px;
        }
        
        [data-theme="dark"] .message-box.success {
            background: #1A3A2A;
            color: #34D399;
            border-color: #34D399;
        }
        
        [data-theme="dark"] .message-box.warning {
            background: #3D2E0A;
            color: #FBBF24;
            border-color: #FBBF24;
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 12px;
        }
        
        .empty-state p {
            font-size: 0.95rem;
        }
        
        .empty-state .sub {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 4px;
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
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }
            .search-form input[type="text"] {
                min-width: 100%;
            }
            .filter-group {
                justify-content: center;
            }
            .card {
                padding: 12px 14px;
            }
            .data-table {
                font-size: 0.7rem;
            }
            .data-table th,
            .data-table td {
                padding: 5px 8px;
            }
            .stat-card .stat-number {
                font-size: 1.3rem;
            }
            .stat-card {
                padding: 12px 16px;
                min-height: 70px;
            }
            .stat-card .stat-icon {
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            .stat-card .stat-number {
                font-size: 1.1rem;
            }
            .stat-card .stat-label {
                font-size: 0.6rem;
            }
            .stat-card .stat-icon {
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }
            .stat-card {
                padding: 8px 12px;
                min-height: 60px;
            }
            .data-table {
                font-size: 0.65rem;
            }
            .data-table th,
            .data-table td {
                padding: 4px 6px;
            }
            .main-content { padding: 10px; }
            .page-header .page-title { font-size: 1.1rem; }
        }
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
                <i class="fas fa-skull mr-2"></i> Expired Medicines
                <span class="badge-count"><?= $total_expired ?> items</span>
            </h1>
            <p class="page-subtitle">
                View all expired medicines (active + inactive)
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="text-xs text-white/50 ml-2">
                    <i class="fas fa-database"></i> New DB
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="inventory.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <a href="expired.php" class="stat-card red">
            <div>
                <p class="stat-label">Total Expired</p>
                <p class="stat-number"><?= number_format($total_expired) ?></p>
                <span class="stat-trend"><i class="fas fa-skull"></i> <?= number_format($total_expired_units) ?> units</span>
            </div>
            <div class="stat-icon"><i class="fas fa-skull"></i></div>
        </a>
        
        <a href="expired.php?status=active" class="stat-card orange">
            <div>
                <p class="stat-label">Active (Still in stock)</p>
                <p class="stat-number"><?= number_format($active_expired) ?></p>
                <span class="stat-trend"><i class="fas fa-exclamation-triangle"></i> <?= number_format($active_expired_units) ?> units</span>
            </div>
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        </a>
        
        <a href="expired.php?status=inactive" class="stat-card gray">
            <div>
                <p class="stat-label">Inactive (Hidden)</p>
                <p class="stat-number"><?= number_format($inactive_expired) ?></p>
                <span class="stat-trend"><i class="fas fa-check-circle"></i> <?= number_format($inactive_expired_units) ?> units</span>
            </div>
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- ✅ MESSAGES WITH AUTO-DISMISS AFTER 5 SECONDS -->
    <!-- ================================================================ -->
    <?php if (count($expired_medicines) == 0 && empty($search)): ?>
        <div class="message-box success" id="messageBox">
            <i class="fas fa-check-circle"></i>
            🎉 No expired medicines found! All medicines are within expiry date.
            <span class="message-timer">(auto-dismiss in 5s)</span>
            <span class="message-close" onclick="dismissMessage()">&times;</span>
        </div>
    <?php elseif (count($expired_medicines) > 0 && $active_expired > 0): ?>
        <div class="message-box warning" id="messageBox">
            <i class="fas fa-exclamation-triangle"></i>
            ⚠️ <strong><?= $active_expired ?></strong> expired medicine(s) are still <strong>ACTIVE</strong> in inventory. 
            Please remove or mark as inactive!
            <span class="message-timer">(auto-dismiss in 5s)</span>
            <span class="message-close" onclick="dismissMessage()">&times;</span>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FILTERS & SEARCH -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="filter-group">
            <a href="expired.php" class="filter-btn <?= $status_filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-list"></i> All
            </a>
            <a href="expired.php?status=active" class="filter-btn red <?= $status_filter === 'active' ? 'active' : '' ?>">
                <i class="fas fa-exclamation-triangle"></i> Active
            </a>
            <a href="expired.php?status=inactive" class="filter-btn <?= $status_filter === 'inactive' ? 'active' : '' ?>">
                <i class="fas fa-check-circle"></i> Inactive
            </a>
            <?php if (!empty($search) || $status_filter !== 'all'): ?>
                <a href="expired.php" class="filter-btn clear-filter">
                    <i class="fas fa-times"></i> Clear Filter
                </a>
            <?php endif; ?>
        </div>
        
        <form method="GET" class="search-form">
            <?php if ($status_filter !== 'all'): ?>
                <input type="hidden" name="status" value="<?= $status_filter ?>">
            <?php endif; ?>
            
            <input type="text" name="search" placeholder="🔍 Search expired medicine..." 
                   value="<?= htmlspecialchars($search) ?>">
            
            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i> Search
            </button>
            
            <a href="expired.php" class="btn-reset">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- EXPIRED MEDICINES TABLE -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-red mr-2"></i>
                Expired Medicines
                <span class="result-count ml-2">(<strong><?= number_format(count($expired_medicines)) ?></strong> record(s))</span>
                <?php if ($active_expired > 0): ?>
                    <span class="ml-2 inline-flex bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs border border-red-200 animate-pulse">
                        <i class="fas fa-exclamation-circle mr-1"></i> <?= $active_expired ?> active
                    </span>
                <?php endif; ?>
            </h3>
        </div>
        
        <?php if (count($expired_medicines) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="border-radius: 8px 0 0 0;">#</th>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Expiry Date</th>
                            <th>Days Expired</th>
                            <th>Batch Number</th>
                            <th>Status</th>
                            <th style="border-radius: 0 8px 0 0;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach ($expired_medicines as $item): ?>
                            <tr>
                                <td><?= $counter++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['medication_name']) ?></strong>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></div>
                                </td>
                                <td><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                <td>
                                    <strong style="color: var(--danger);"><?= $item['quantity'] ?></strong>
                                    <?php if ($item['status'] === 'inactive'): ?>
                                        <span class="text-xs text-gray-400">(hidden)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="expired-badge">
                                        <i class="fas fa-calendar-times mr-1"></i>
                                        <?= date('M d, Y', strtotime($item['expiry_date'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="days-expired">
                                        <i class="fas fa-clock"></i>
                                        <?= $item['days_expired'] ?> days ago
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($item['batch_number'])): ?>
                                        <span class="batch-number"><?= htmlspecialchars($item['batch_number']) ?></span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $item['status'] ?? 'active' ?>">
                                        <?= ucfirst($item['status'] ?? 'Active') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <a href="view_inventory.php?id=<?= $item['id'] ?>&type=medicine" 
                                           class="action-btn view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                <p>No expired medicines found</p>
                <p class="sub">All medicines are within expiry date 🎉</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- RECOMMENDATIONS -->
    <!-- ================================================================ -->
    <?php if (count($expired_medicines) > 0): ?>
    <div class="card mt-4 animate-fade-in-up" style="border-color: var(--danger);">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-lightbulb" style="color: var(--danger);"></i>
                Recommendations
            </h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 border rounded-lg" style="border-color: var(--border-color);">
                <h4 class="font-semibold text-red-600 mb-2">
                    <i class="fas fa-skull mr-1"></i> Active Expired Medicines
                </h4>
                <p class="text-sm text-gray-600">
                    <?php if ($active_expired > 0): ?>
                        <strong><?= $active_expired ?></strong> expired medicine(s) are still <span class="text-red-600 font-semibold">ACTIVE</span> in inventory.
                        <span class="block text-xs text-gray-400 mt-1">
                            ⚠️ These medicines should be <strong>marked as inactive</strong> or <strong>removed</strong> from inventory.
                        </span>
                    <?php else: ?>
                        ✅ All expired medicines are already marked as <span class="text-green-600 font-semibold">INACTIVE</span>.
                    <?php endif; ?>
                </p>
            </div>
            <div class="p-4 border rounded-lg" style="border-color: var(--border-color);">
                <h4 class="font-semibold text-orange-600 mb-2">
                    <i class="fas fa-clock mr-1"></i> Actions Required
                </h4>
                <p class="text-sm text-gray-600">
                    <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                        <li>📌 Review expired medicines regularly</li>
                        <li>🗑️ Remove or mark as inactive expired stock</li>
                        <li>📦 Check if any expired medicines are still in active prescriptions</li>
                        <li>🔄 Update inventory to prevent dispensing expired medicines</li>
                        <?php if ($active_expired > 0): ?>
                            <li class="text-red-600 font-semibold">⚠️ <strong><?= $active_expired ?></strong> item(s) need immediate attention!</li>
                        <?php endif; ?>
                    </ul>
                </p>
            </div>
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
            Expired Medicines Report
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
    // ✅ AUTO-DISMISS MESSAGES AFTER 5 SECONDS
    // ================================================================
    function dismissMessage() {
        var messageBox = document.getElementById('messageBox');
        if (messageBox) {
            messageBox.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            messageBox.style.opacity = '0';
            messageBox.style.transform = 'translateY(-20px)';
            setTimeout(function() {
                messageBox.style.display = 'none';
            }, 500);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var messageBox = document.getElementById('messageBox');
        if (messageBox) {
            // Auto dismiss after 5 seconds
            var dismissTimer = setTimeout(function() {
                dismissMessage();
            }, 5000);
            
            // Click on message or close button to dismiss immediately
            messageBox.addEventListener('click', function(e) {
                if (e.target.classList.contains('message-close') || e.target === this) {
                    clearTimeout(dismissTimer);
                    dismissMessage();
                }
            });
            
            // Hover to pause auto-dismiss
            messageBox.addEventListener('mouseenter', function() {
                clearTimeout(dismissTimer);
            });
            
            // Resume auto-dismiss after hover
            messageBox.addEventListener('mouseleave', function() {
                dismissTimer = setTimeout(function() {
                    dismissMessage();
                }, 3000);
            });
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
            var searchInput = document.querySelector('.search-form input[type="text"]');
            searchInput?.focus();
            searchInput?.select();
        }
        if (e.key === 'Escape') {
            var searchInput = document.querySelector('.search-form input[type="text"]');
            if (searchInput && document.activeElement === searchInput) {
                searchInput.value = '';
                searchInput.blur();
            }
        }
    });

    console.log('%c💊 Braick - Expired Medicines Report (NEW DATABASE)', 'font-size:18px; font-weight:bold; color:#DC2626;');
    console.log('%c📊 Using NEW DATABASE: dispensary_db', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🗑️ Total Expired: <?= $total_expired ?> (units: <?= $total_expired_units ?>)', 'font-size:13px; color:#DC2626;');
    console.log('%c⚠️ Active Expired: <?= $active_expired ?> (units: <?= $active_expired_units ?>)', 'font-size:13px; color:#D97706;');
    console.log('%c✅ Inactive Expired: <?= $inactive_expired ?> (units: <?= $inactive_expired_units ?>)', 'font-size:13px; color:#6B7280;');
    console.log('%c✅ Messages auto-dismiss after 5 seconds', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Using shared header & sidebar', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>