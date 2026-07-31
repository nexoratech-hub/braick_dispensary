<?php
// ================================================================
// FILE: frontend/pages/admin/pharmacies.php
// SUPER ADMIN - PHARMACIES MANAGEMENT
// VIEW AND MANAGE PHARMACY BRANCHES
// BRAICK DISPENSARY - BLUE THEME
// ================================================================

session_start();

// ================================================================
// FORCE SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET BRANCH FILTER
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$search_term = $_GET['search'] ?? '';
$message = '';
$message_type = '';

// ================================================================
// GET BRANCH NAME
// ================================================================
$branch_name = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([(int)$selected_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
    }
} else {
    $selected_branch_id = 'all';
}

// ================================================================
// FETCH PHARMACY BRANCHES
// ================================================================
$query = "
    SELECT 
        b.*,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy' AND status = 'active') as pharmacist_count,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy') as total_pharmacists,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active') as total_medicines,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND quantity <= reorder_level) as low_stock_items,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND quantity <= 0) as out_of_stock_items,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'pending') as pending_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'dispensed') as dispensed_prescriptions,
        (SELECT COALESCE(SUM(total_amount), 0) FROM patient_bills WHERE branch_id = b.id AND status = 'paid' AND bill_number LIKE 'BILL-PRES-%') as pharmacy_revenue
    FROM branches b
    WHERE 1=1
";

if (!empty($search_term)) {
    $query .= " AND (b.name LIKE :search OR b.location LIKE :search OR b.phone LIKE :search OR b.email LIKE :search)";
}

$query .= " ORDER BY b.name ASC";

$stmt = $db->prepare($query);

if (!empty($search_term)) {
    $stmt->bindValue(':search', '%' . $search_term . '%');
}

$stmt->execute();
$pharmacies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
$total_pharmacies = count($pharmacies);
$total_pharmacists = 0;
$total_medicines = 0;
$total_low_stock = 0;
$total_out_of_stock = 0;
$total_pending_prescriptions = 0;
$total_revenue = 0;

foreach ($pharmacies as $pharmacy) {
    $total_pharmacists += $pharmacy['total_pharmacists'] ?? 0;
    $total_medicines += $pharmacy['total_medicines'] ?? 0;
    $total_low_stock += $pharmacy['low_stock_items'] ?? 0;
    $total_out_of_stock += $pharmacy['out_of_stock_items'] ?? 0;
    $total_pending_prescriptions += $pharmacy['pending_prescriptions'] ?? 0;
    $total_revenue += $pharmacy['pharmacy_revenue'] ?? 0;
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER
// ================================================================
include_once '../../components/admin_header.php';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
include_once '../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacies - Braick Dispensary</title>
    
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
            
            --success: #0B5ED7;
            --success-dark: #0A4CA8;
            --success-light: #3B82F6;
            --success-bg: #EFF6FF;
            
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
            --purple-bg: #2D1B5F;
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
           STATS CARDS - BLUE THEME
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        
        .stat-icon.blue { background: var(--primary-bg); color: var(--primary); }
        .stat-icon.green { background: #ECFDF5; color: #059669; }
        .stat-icon.orange { background: #FFFBEB; color: #F59E0B; }
        .stat-icon.purple { background: #F5F3FF; color: #7C3AED; }
        .stat-icon.red { background: #FEF2F2; color: #DC2626; }
        
        [data-theme="dark"] .stat-icon.blue { background: #1E3A5F; color: #3B82F6; }
        [data-theme="dark"] .stat-icon.green { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .stat-icon.orange { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .stat-icon.purple { background: #2D1B4E; color: #A78BFA; }
        [data-theme="dark"] .stat-icon.red { background: #3A1A1A; color: #F87171; }
        
        .stat-label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin: 0;
        }
        
        .stat-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }
        
        .stat-value.blue-text { color: var(--primary); }
        .stat-value.green-text { color: #059669; }
        .stat-value.orange-text { color: #F59E0B; }
        .stat-value.red-text { color: #DC2626; }
        
        /* ================================================================
           PHARMACY CARDS - BLUE THEME
           ================================================================ */
        .pharmacies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 20px;
        }
        
        .pharmacy-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .pharmacy-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }
        
        .pharmacy-card-header {
            padding: 16px 20px;
            background: var(--primary-gradient);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
        }
        
        .pharmacy-card-header::before {
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
        
        .pharmacy-info {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            position: relative;
            z-index: 1;
        }
        
        .pharmacy-icon {
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
        
        .pharmacy-name {
            font-size: 0.95rem;
            font-weight: 600;
            color: white;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .pharmacy-code {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.7);
            display: block;
            font-family: 'Courier New', monospace;
        }
        
        .pharmacy-status-badge {
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
        
        .pharmacy-status-badge.active {
            background: rgba(52, 211, 153, 0.3);
            border-color: rgba(52, 211, 153, 0.3);
            color: #34D399;
        }
        
        .pharmacy-status-badge.inactive {
            background: rgba(248, 113, 113, 0.3);
            border-color: rgba(248, 113, 113, 0.3);
            color: #F87171;
        }
        
        .pharmacy-card-body {
            padding: 16px 20px;
        }
        
        .pharmacy-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 16px;
            margin-bottom: 14px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            color: var(--text-secondary);
            padding: 4px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-item i {
            color: var(--primary);
            font-size: 0.75rem;
            width: 16px;
            text-align: center;
            flex-shrink: 0;
        }
        
        [data-theme="dark"] .detail-item i {
            color: var(--primary-light);
        }
        
        .detail-item span {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .pharmacy-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
        
        .stat-item .stat-number.blue-text { color: var(--primary); }
        .stat-item .stat-number.green-text { color: #059669; }
        .stat-item .stat-number.orange-text { color: #F59E0B; }
        .stat-item .stat-number.red-text { color: #DC2626; }
        
        .pharmacy-card-footer {
            padding: 12px 20px;
            background: var(--bg-body);
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        [data-theme="dark"] .pharmacy-card-footer {
            background: var(--bg-card);
        }
        
        .stock-alerts {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .stock-tag {
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
        
        .stock-tag.danger {
            background: var(--danger-bg);
            color: var(--danger);
            border-color: var(--danger);
        }
        
        .stock-tag.warning {
            background: var(--warning-bg);
            color: var(--warning);
            border-color: var(--warning);
        }
        
        [data-theme="dark"] .stock-tag {
            background: var(--bg-body);
        }
        
        .pharmacy-actions {
            display: flex;
            gap: 4px;
        }
        
        /* ================================================================
           BUTTONS - BLUE THEME
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
            background: var(--primary-gradient);
            color: white;
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1.5px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-outline-primary {
            background: transparent;
            color: var(--primary);
            border: 1.5px solid var(--primary);
        }
        
        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
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
            letter-spacing: 0.02em;
        }
        
        .badge-success { background: var(--primary); }
        .badge-danger { background: var(--danger); }
        .badge-warning { background: var(--warning); color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        
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
            border: 1px solid var(--primary);
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
            color: var(--primary);
            font-weight: 500;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .pharmacies-grid { grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .pharmacies-grid { grid-template-columns: 1fr; }
            .pharmacy-details { grid-template-columns: 1fr; }
            .pharmacy-stats { grid-template-columns: repeat(2, 1fr); }
            .pharmacy-card-footer { flex-direction: column; align-items: stretch; }
            .stock-alerts { justify-content: center; }
            .pharmacy-actions { justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
            .pharmacy-card-header { flex-direction: column; align-items: stretch; text-align: center; }
            .pharmacy-info { flex-direction: column; text-align: center; }
            .pharmacy-status { text-align: center; }
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
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .pharmacy-actions, .page-header .btn-outline-light,
            .footer, #sidebarToggle { display: none !important; }
            
            .main-content { margin: 0; padding: 20px; }
            .pharmacy-card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .pharmacy-card-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .pharmacy-name, .pharmacy-code, .pharmacy-status-badge { color: white !important; }
            .pharmacy-icon { background: rgba(255,255,255,0.2) !important; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - SHARED HEADER -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search pharmacies..." value="<?= htmlspecialchars($search_term) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($pharmacies as $pharmacy): ?>
                <option value="<?= $pharmacy['id'] ?>" <?= $selected_branch_id == $pharmacy['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($pharmacy['name']) ?>
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
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-prescription-bottle"></i>
                Pharmacy Branches
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                Manage all pharmacy branches
                <span class="header-badge">
                    <i class="fas fa-prescription"></i> <?= $total_pharmacies ?> Pharmacies
                </span>
                <span class="header-badge" style="background:rgba(59,130,246,0.2);border-color:rgba(59,130,246,0.3);color:#60A5FA;">
                    <i class="fas fa-user-md"></i> <?= number_format($total_pharmacists) ?> Pharmacists
                </span>
                <span class="header-badge" style="background:rgba(167,139,250,0.2);border-color:rgba(167,139,250,0.3);color:#A78BFA;">
                    <i class="fas fa-pills"></i> <?= number_format($total_medicines) ?> Medicines
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_revenue, 0) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="add_pharmacy.php" class="btn-outline-light">
                <i class="fas fa-plus-circle"></i> Add Pharmacy
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

    <!-- ================================================================ -->
    <!-- STATISTICS SUMMARY -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-store"></i>
            </div>
            <div>
                <p class="stat-label">Total Pharmacies</p>
                <p class="stat-value blue-text"><?= $total_pharmacies ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-user-md"></i>
            </div>
            <div>
                <p class="stat-label">Pharmacists</p>
                <p class="stat-value green-text"><?= number_format($total_pharmacists) ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-pills"></i>
            </div>
            <div>
                <p class="stat-label">Total Medicines</p>
                <p class="stat-value"><?= number_format($total_medicines) ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <p class="stat-label">Pending Prescriptions</p>
                <p class="stat-value orange-text"><?= number_format($total_pending_prescriptions) ?></p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PHARMACIES GRID -->
    <!-- ================================================================ -->
    <?php if (count($pharmacies) > 0): ?>
        <div class="pharmacies-grid animate-fade-in-up" style="animation-delay:0.05s;">
            <?php foreach ($pharmacies as $pharmacy): ?>
                <div class="pharmacy-card">
                    <div class="pharmacy-card-header">
                        <div class="pharmacy-info">
                            <div class="pharmacy-icon">
                                <i class="fas fa-prescription-bottle"></i>
                            </div>
                            <div>
                                <h3 class="pharmacy-name"><?= htmlspecialchars($pharmacy['name']) ?></h3>
                                <span class="pharmacy-code">ID: <?= htmlspecialchars($pharmacy['id']) ?></span>
                            </div>
                        </div>
                        <div class="pharmacy-status">
                            <span class="pharmacy-status-badge <?= $pharmacy['status'] === 'active' ? 'active' : 'inactive' ?>">
                                <i class="fas fa-<?= $pharmacy['status'] === 'active' ? 'circle' : 'times-circle' ?>"></i>
                                <?= ucfirst($pharmacy['status']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="pharmacy-card-body">
                        <div class="pharmacy-details">
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?= htmlspecialchars($pharmacy['location'] ?? 'N/A') ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-phone"></i>
                                <span><?= htmlspecialchars($pharmacy['phone'] ?? 'N/A') ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-envelope"></i>
                                <span><?= htmlspecialchars($pharmacy['email'] ?? 'N/A') ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-user-md"></i>
                                <span><?= $pharmacy['pharmacist_count'] ?? 0 ?> Active Pharmacists</span>
                            </div>
                        </div>
                        
                        <div class="pharmacy-stats">
                            <div class="stat-item">
                                <span class="stat-number blue-text"><?= $pharmacy['total_medicines'] ?? 0 ?></span>
                                <span class="stat-label">Medicines</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number <?= ($pharmacy['pending_prescriptions'] ?? 0) > 0 ? 'orange-text' : '' ?>">
                                    <?= $pharmacy['pending_prescriptions'] ?? 0 ?>
                                </span>
                                <span class="stat-label">Pending RX</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number <?= ($pharmacy['dispensed_prescriptions'] ?? 0) > 0 ? 'green-text' : '' ?>">
                                    <?= $pharmacy['dispensed_prescriptions'] ?? 0 ?>
                                </span>
                                <span class="stat-label">Dispensed</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pharmacy-card-footer">
                        <div class="stock-alerts">
                            <?php if (($pharmacy['out_of_stock_items'] ?? 0) > 0): ?>
                                <span class="stock-tag danger">
                                    <i class="fas fa-times-circle"></i>
                                    <?= $pharmacy['out_of_stock_items'] ?> Out of Stock
                                </span>
                            <?php endif; ?>
                            <?php if (($pharmacy['low_stock_items'] ?? 0) > 0): ?>
                                <span class="stock-tag warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?= $pharmacy['low_stock_items'] ?> Low Stock
                                </span>
                            <?php endif; ?>
                            <?php if (($pharmacy['out_of_stock_items'] ?? 0) == 0 && ($pharmacy['low_stock_items'] ?? 0) == 0): ?>
                                <span class="stock-tag" style="border-color:var(--primary);color:var(--primary);">
                                    <i class="fas fa-check-circle"></i>
                                    Stock OK
                                </span>
                            <?php endif; ?>
                            <span class="stock-tag" style="border-color:var(--primary);color:var(--primary);">
                                <i class="fas fa-money-bill-wave"></i>
                                TSh <?= number_format($pharmacy['pharmacy_revenue'] ?? 0, 0) ?>
                            </span>
                        </div>
                        <div class="pharmacy-actions">
                            <a href="view_pharmacy.php?id=<?= $pharmacy['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit_pharmacy.php?id=<?= $pharmacy['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit Pharmacy">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>" class="btn btn-sm btn-outline-primary" title="Manage Inventory">
                                <i class="fas fa-boxes"></i>
                            </a>
                            <a href="pharmacy_prescriptions.php?id=<?= $pharmacy['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Prescriptions">
                                <i class="fas fa-prescription"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state animate-fade-in-up">
            <i class="fas fa-prescription-bottle-slash"></i>
            <h3>No Pharmacy Branches Found</h3>
            <p>No pharmacy branches match your search criteria. Try adjusting your search or add a new pharmacy branch.</p>
            <a href="add_pharmacy.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Add Pharmacy
            </a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Pharmacy Branches
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
        var branch = '<?= $selected_branch_id ?>';
        if (query.length > 0) {
            window.location.href = 'pharmacies.php?branch=' + branch + '&search=' + encodeURIComponent(query);
        } else {
            window.location.href = 'pharmacies.php?branch=' + branch;
        }
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

    console.log('%c💊 Braick Dispensary - Pharmacy Branches (BLUE THEME)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏥 Total Pharmacies: <?= $total_pharmacies ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👨‍⚕️ Pharmacists: <?= number_format($total_pharmacists) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💊 Medicines: <?= number_format($total_medicines) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c⏳ Pending Prescriptions: <?= number_format($total_pending_prescriptions) ?>', 'font-size:13px; color:#F59E0B;');
    console.log('%c💰 Revenue: TSh <?= number_format($total_revenue, 0) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>