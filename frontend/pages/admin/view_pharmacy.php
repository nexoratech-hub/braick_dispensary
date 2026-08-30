<?php
// ================================================================
// FILE: frontend/pages/admin/view_pharmacy.php
// SUPER ADMIN - VIEW PHARMACY BRANCH DETAILS
// FIXED: Using bills and bill_items tables for prescription revenue
// ================================================================

// ================================================================
// START SESSION
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
// CHECK IF USER IS ADMIN
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET SESSION DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$dark_mode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] : 'false';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET BRANCH ID
// ================================================================
$pharmacy_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

if ($pharmacy_id <= 0) {
    header('Location: pharmacies.php?branch=' . $selected_branch_id . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH PHARMACY DETAILS - USING YOUR DATABASE
// ================================================================
$stmt = $db->prepare("
    SELECT 
        b.*,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy' AND status = 'active') as active_pharmacists,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy') as total_pharmacists,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active') as total_medicines,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND quantity <= reorder_level AND quantity > 0) as low_stock_items,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND quantity <= 0) as out_of_stock_items,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'pending') as pending_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'dispensed') as dispensed_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'confirmed') as confirmed_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'cancelled') as cancelled_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id) as total_prescriptions,
        -- FIXED: Prescription revenue from bill_items (medication items)
        (SELECT COALESCE(SUM(bi.total_price), 0) 
         FROM bill_items bi
         INNER JOIN bills bl ON bi.bill_id = bl.id
         WHERE bi.item_type = 'medication' 
         AND bl.branch_id = b.id 
         AND bl.status IN ('paid', 'partial')) as prescription_revenue,
        (SELECT COUNT(*) FROM otc_sales WHERE branch_id = b.id) as total_otc_sales,
        (SELECT COALESCE(SUM(total_amount), 0) FROM otc_sales WHERE branch_id = b.id AND payment_status = 'paid') as otc_revenue,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND expiry_date < CURDATE()) as expired_medicines,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) as expiring_soon_medicines,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active') as total_active_medicines
    FROM branches b
    WHERE b.id = ?
");
$stmt->execute([$pharmacy_id]);
$pharmacy = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pharmacy) {
    header('Location: pharmacies.php?branch=' . $selected_branch_id . '&error=notfound');
    exit;
}

// Calculate total revenue
$total_revenue = ($pharmacy['prescription_revenue'] ?? 0) + ($pharmacy['otc_revenue'] ?? 0);

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
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// GET PHARMACISTS
// ================================================================
$pharmacists = [];
try {
    $stmt = $db->prepare("
        SELECT id, full_name, email, phone, status, created_at 
        FROM users 
        WHERE branch_id = ? AND role = 'pharmacy'
        ORDER BY full_name
    ");
    $stmt->execute([$pharmacy_id]);
    $pharmacists = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pharmacists = [];
}

// ================================================================
// GET RECENT PRESCRIPTIONS WITH AMOUNT FROM bill_items
// ================================================================
$recent_prescriptions = [];
try {
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.prescription_number,
            p.status,
            p.created_at,
            pat.full_name as patient_name,
            u.full_name as doctor_name,
            COALESCE((
                SELECT SUM(bi.total_price) 
                FROM bill_items bi
                INNER JOIN bills bl ON bi.bill_id = bl.id
                WHERE bi.reference_id = p.id 
                AND bi.reference_type = 'prescription'
                AND bl.status IN ('paid', 'partial')
            ), 0) as total_amount
        FROM prescriptions p
        LEFT JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN users u ON p.doctor_id = u.id
        WHERE p.branch_id = ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$pharmacy_id]);
    $recent_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching recent prescriptions: " . $e->getMessage());
    $recent_prescriptions = [];
}

// ================================================================
// GET RECENT INVENTORY
// ================================================================
$recent_inventory = [];
try {
    $stmt = $db->prepare("
        SELECT 
            id,
            medication_name,
            category,
            quantity,
            reorder_level,
            selling_price,
            expiry_date,
            status,
            updated_at
        FROM medications_inventory
        WHERE branch_id = ?
        ORDER BY updated_at DESC
        LIMIT 10
    ");
    $stmt->execute([$pharmacy_id]);
    $recent_inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_inventory = [];
}

// ================================================================
// GET RECENT OTC SALES
// ================================================================
$recent_otc_sales = [];
try {
    $stmt = $db->prepare("
        SELECT 
            id,
            sale_number,
            customer_name,
            total_amount,
            subtotal as net_amount,
            payment_method,
            payment_status,
            created_at
        FROM otc_sales
        WHERE branch_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$pharmacy_id]);
    $recent_otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_otc_sales = [];
}

// ================================================================
// STATUS BADGE CLASS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'active' => 'success',
        'inactive' => 'danger',
        'pending' => 'warning',
        'dispensed' => 'success',
        'confirmed' => 'info',
        'cancelled' => 'danger',
        'paid' => 'success',
        'partial' => 'warning'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'active' => 'fa-check-circle',
        'inactive' => 'fa-times-circle',
        'pending' => 'fa-clock',
        'dispensed' => 'fa-check-circle',
        'confirmed' => 'fa-check-double',
        'cancelled' => 'fa-times-circle',
        'paid' => 'fa-check-circle',
        'partial' => 'fa-clock'
    ];
    return $icons[$status] ?? 'fa-circle';
}

function format_currency($amount) {
    if ($amount == 0) {
        return 'TSh 0';
    }
    return 'TSh ' . number_format($amount, 0);
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= $dark_mode === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Pharmacy - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #083C8A);
            
            --card-blue: #0B5ED7;
            --card-red: #DC2626;
            --card-green: #059669;
            --card-orange: #D97706;
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
            --card-blue: #2563EB;
            --card-red: #DC2626;
            --card-green: #059669;
            --card-orange: #D97706;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: background 0.3s ease;
        }
        
        .stats-grid-8 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }
        
        .stat-card-8 {
            border-radius: 14px;
            padding: 16px 18px;
            border: none;
            display: flex;
            flex-direction: column;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            color: white;
            position: relative;
            overflow: hidden;
            min-height: 110px;
            cursor: pointer;
            text-decoration: none;
        }
        
        .stat-card-8::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 160px;
            height: 160px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
            pointer-events: none;
            transition: all 0.5s ease;
        }
        .stat-card-8::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -10%;
            width: 120px;
            height: 120px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
            pointer-events: none;
            transition: all 0.5s ease;
        }
        .stat-card-8:hover {
            transform: translateY(-4px) scale(1.01);
            box-shadow: 0 10px 32px rgba(0,0,0,0.2);
        }
        .stat-card-8:hover::before { transform: scale(1.3); right: -10%; }
        .stat-card-8:hover::after { transform: scale(1.4); bottom: -30%; }
        
        .stat-card-8 .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            background: rgba(255,255,255,0.18);
            color: white;
            border: 1px solid rgba(255,255,255,0.12);
            backdrop-filter: blur(8px);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            margin-bottom: 4px;
        }
        .stat-card-8:hover .stat-icon {
            transform: scale(1.05) rotate(-2deg);
            background: rgba(255,255,255,0.3);
        }
        .stat-card-8 .stat-content {
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .stat-card-8 .stat-label {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.85);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin: 0 0 1px 0;
        }
        .stat-card-8 .stat-number-small {
            font-size: 2.2rem;
            font-weight: 800;
            color: white;
            margin: 0;
            line-height: 1.1;
            letter-spacing: -0.02em;
        }
        .stat-card-8 .stat-amount-large {
            font-size: 1.6rem;
            font-weight: 800;
            color: white;
            margin: 0;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }
        .stat-card-8 .stat-currency {
            font-size: 0.8rem;
            font-weight: 600;
            color: rgba(255,255,255,0.9);
            margin-right: 3px;
        }
        .stat-card-8 .stat-sub {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.9);
            margin-top: 3px;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .stat-card-8 .stat-arrow {
            position: absolute;
            right: 12px;
            bottom: 12px;
            color: rgba(255,255,255,0.12);
            font-size: 0.7rem;
            transition: all 0.3s ease;
            z-index: 1;
        }
        .stat-card-8:hover .stat-arrow {
            transform: translateX(6px);
            color: rgba(255,255,255,0.4);
        }
        .stat-card-8 .flex-row {
            display: flex;
            align-items: baseline;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .card-blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .card-blue:hover { box-shadow: 0 10px 32px rgba(11, 94, 215, 0.4); }
        .card-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        .card-red:hover { box-shadow: 0 10px 32px rgba(220, 38, 38, 0.4); }
        .card-green { background: linear-gradient(135deg, #059669, #047857); }
        .card-green:hover { box-shadow: 0 10px 32px rgba(5, 150, 105, 0.4); }
        .card-orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .card-orange:hover { box-shadow: 0 10px 32px rgba(217, 119, 6, 0.4); }
        
        [data-theme="dark"] .card-blue { background: linear-gradient(135deg, #2563EB, #1D4ED8); }
        [data-theme="dark"] .card-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        [data-theme="dark"] .card-green { background: linear-gradient(135deg, #059669, #047857); }
        [data-theme="dark"] .card-orange { background: linear-gradient(135deg, #D97706, #B45309); }
        
        .page-header-box {
            background: var(--primary-gradient);
            border-radius: 16px;
            padding: 20px 28px;
            margin-bottom: 24px;
            box-shadow: 0 6px 24px rgba(11, 94, 215, 0.2);
            position: relative;
            overflow: hidden;
        }
        .page-header-box::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 350px;
            height: 350px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        .page-header-box .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .page-header-box .page-title .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        .page-header-box .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 4px;
        }
        .page-header-box .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        .page-header-box .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .page-header-box .header-badge i { opacity: 0.8; }
        
        .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 12px;
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
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .detail-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 24px;
        }
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .detail-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .detail-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 2px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }
        .card:hover {
            border-color: var(--primary);
        }
        .card-header {
            padding: 14px 20px;
            background: var(--primary-gradient-strong);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .card-header .card-title {
            color: white;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .card-header .card-title i { margin-right: 8px; }
        .card-header .card-action {
            color: rgba(255,255,255,0.7);
            font-size: 0.7rem;
            text-decoration: none;
            transition: all 0.3s;
        }
        .card-header .card-action:hover { color: white; }
        .card-body { padding: 0; overflow-x: auto; }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
        }
        .data-table thead {
            background: var(--bg-body);
        }
        .data-table thead th {
            padding: 10px 14px;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        .data-table tr:hover td {
            background: #F8FAFC;
        }
        [data-theme="dark"] .data-table tr:hover td {
            background: #1E293B;
        }
        .data-table tr:last-child td { border-bottom: none; }
        
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
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .status-badge.success { background: #D1FAE5; color: #059669; }
        .status-badge.danger { background: #FEE2E2; color: #DC2626; }
        .status-badge.warning { background: #FEF3C7; color: #D97706; }
        .status-badge.info { background: #EFF6FF; color: #0B5ED7; }
        .status-badge.secondary { background: #F1F5F9; color: #64748B; }
        [data-theme="dark"] .status-badge.success { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge.danger { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .status-badge.warning { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge.info { background: #1E3A5F; color: #3B82F6; }
        [data-theme="dark"] .status-badge.secondary { background: #2D3748; color: #94A3B8; }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        .empty-state i {
            font-size: 2.5rem;
            color: var(--border-color);
            margin-bottom: 8px;
        }
        
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
        }
        .btn-primary {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .btn-primary:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        
        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            cursor: pointer;
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
        }
        .btn-view i {
            font-size: 0.65rem;
            transition: transform 0.3s ease;
        }
        .btn-view:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 6px 20px rgba(11, 94, 215, 0.35);
            background: var(--primary-gradient-strong);
            color: white;
        }
        .btn-view:hover i {
            transform: translateX(3px);
        }
        .btn-view:active {
            transform: scale(0.95);
        }
        .btn-view-outline {
            background: transparent;
            color: var(--primary);
            border-color: var(--primary);
            box-shadow: none;
        }
        .btn-view-outline:hover {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
            box-shadow: 0 6px 20px rgba(11, 94, 215, 0.35);
        }
        .btn-view-sm {
            padding: 3px 10px;
            font-size: 0.6rem;
            border-radius: 6px;
        }
        .btn-view-sm i {
            font-size: 0.55rem;
        }
        .btn-view-success {
            background: linear-gradient(135deg, #059669, #047857);
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
        }
        .btn-view-success:hover {
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.35);
            background: linear-gradient(135deg, #047857, #065F46);
        }
        
        [data-theme="dark"] .btn-view-outline {
            color: #60A5FA;
            border-color: #60A5FA;
        }
        [data-theme="dark"] .btn-view-outline:hover {
            color: white;
        }
        
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            transition: border-color 0.3s ease, color 0.3s ease;
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid-8 { grid-template-columns: repeat(4, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-grid-8 { grid-template-columns: 1fr 1fr; }
            .page-header-box .page-title { font-size: 1.3rem; }
            .page-header-box { padding: 16px 18px; }
            .stat-card-8 { padding: 14px 16px; min-height: 90px; }
            .stat-card-8 .stat-number-small { font-size: 1.8rem; }
            .stat-card-8 .stat-amount-large { font-size: 1.4rem; }
            .stat-card-8 .stat-icon { width: 38px; height: 38px; font-size: 1rem; }
        }
        @media (max-width: 480px) {
            .stats-grid-8 { grid-template-columns: 1fr; }
            .stat-card-8 { padding: 12px 14px; min-height: 80px; }
            .stat-card-8 .stat-number-small { font-size: 1.6rem; }
            .stat-card-8 .stat-amount-large { font-size: 1.2rem; }
            .stat-card-8 .stat-icon { width: 34px; height: 34px; font-size: 0.85rem; }
            .page-header-box .page-title { font-size: 1rem; flex-direction: column; align-items: flex-start; }
            .page-header-box .page-subtitle { font-size: 0.75rem; flex-direction: column; align-items: flex-start; gap: 4px; }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        #sidebarOverlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 45;
            display: none;
            backdrop-filter: blur(2px);
        }
        @media (max-width: 1024px) {
            #sidebarOverlay.show { display: block; }
        }
    </style>
</head>
<body>

<div id="sidebarOverlay"></div>

<!-- ================================================================ -->
<!-- SIDEBAR - Loaded from admin_sidebar.php -->
<!-- ================================================================ -->

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-box animate-fade-in-up" style="animation-delay:0.05s;">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription-bottle"></i>
                Pharmacy Details
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <strong><?= htmlspecialchars($pharmacy['name']) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-<?= $pharmacy['status'] === 'active' ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= ucfirst($pharmacy['status']) ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#6EE7B7;">
                    <i class="fas fa-pills"></i> <?= number_format($pharmacy['total_medicines'] ?? 0) ?> Medicines
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-money-bill-wave"></i> <?= format_currency($total_revenue) ?>
                </span>
            </p>
        </div>
        <div style="position:relative;z-index:1;display:flex;gap:8px;flex-wrap:wrap;">
            <a href="edit_pharmacy.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="pharmacies.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- PHARMACY INFO -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <p class="detail-label"><i class="fas fa-map-marker-alt mr-1"></i> Location</p>
                <p class="detail-value"><?= htmlspecialchars($pharmacy['location'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-phone mr-1"></i> Phone</p>
                <p class="detail-value"><?= htmlspecialchars($pharmacy['phone'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-envelope mr-1"></i> Email</p>
                <p class="detail-value"><?= htmlspecialchars($pharmacy['email'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-calendar-plus mr-1"></i> Created</p>
                <p class="detail-value"><?= date('M d, Y h:i A', strtotime($pharmacy['created_at'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user-md mr-1"></i> Pharmacists</p>
                <p class="detail-value"><?= $pharmacy['active_pharmacists'] ?? 0 ?> Active / <?= $pharmacy['total_pharmacists'] ?? 0 ?> Total</p>
            </div>
        </div>
    </div>

    <!-- 8 CARDS -->
    <div class="stats-grid-8 animate-fade-in-up" style="animation-delay:0.15s;">
        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="stat-card-8 card-blue">
            <div class="stat-icon"><i class="fas fa-pills"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Medicines</p>
                <p class="stat-number-small"><?= number_format($pharmacy['total_medicines'] ?? 0) ?></p>
                <p class="stat-sub">Active inventory items</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <a href="prescriptions.php?branch=<?= $pharmacy['id'] ?>&filter=all" class="stat-card-8 card-blue">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Prescriptions</p>
                <div class="flex-row">
                    <span class="stat-number-small"><?= number_format($pharmacy['total_prescriptions'] ?? 0) ?></span>
                    <span class="stat-amount-large" style="font-size:1.4rem;">TSh <?= number_format($pharmacy['prescription_revenue'] ?? 0, 0) ?></span>
                </div>
                <p class="stat-sub"><?= $pharmacy['pending_prescriptions'] ?? 0 ?> pending · <?= $pharmacy['dispensed_prescriptions'] ?? 0 ?> dispensed</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <a href="otc_sales.php?branch=<?= $pharmacy['id'] ?>" class="stat-card-8 card-blue">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-content">
                <p class="stat-label">OTC Sales</p>
                <div class="flex-row">
                    <span class="stat-number-small"><?= number_format($pharmacy['total_otc_sales'] ?? 0) ?></span>
                    <span class="stat-amount-large" style="font-size:1.4rem;">TSh <?= number_format($pharmacy['otc_revenue'] ?? 0, 0) ?></span>
                </div>
                <p class="stat-sub">💰 Paid OTC revenue</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <a href="reports.php?branch=<?= $pharmacy['id'] ?>&type=pharmacy" class="stat-card-8 card-green">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Revenue</p>
                <p class="stat-amount-large"><span class="stat-currency">TSh</span> <?= number_format($total_revenue, 0) ?></p>
                <p class="stat-sub">Rx: TSh <?= number_format($pharmacy['prescription_revenue'] ?? 0, 0) ?> · OTC: TSh <?= number_format($pharmacy['otc_revenue'] ?? 0, 0) ?></p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>&filter=outofstock" class="stat-card-8 card-orange">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Out of Stock</p>
                <p class="stat-number-small"><?= number_format($pharmacy['out_of_stock_items'] ?? 0) ?></p>
                <p class="stat-sub">❌ Items with zero quantity</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>&filter=lowstock" class="stat-card-8 card-orange">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Low Stock</p>
                <p class="stat-number-small"><?= number_format($pharmacy['low_stock_items'] ?? 0) ?></p>
                <p class="stat-sub">⚠️ Below reorder level</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>&filter=expired" class="stat-card-8 card-red">
            <div class="stat-icon"><i class="fas fa-skull"></i></div>
            <div class="stat-content">
                <p class="stat-label">Expired</p>
                <p class="stat-number-small"><?= number_format($pharmacy['expired_medicines'] ?? 0) ?></p>
                <p class="stat-sub">💀 Past expiry date</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>&filter=expiring" class="stat-card-8 card-red">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-content">
                <p class="stat-label">Expiring Soon</p>
                <p class="stat-number-small"><?= number_format($pharmacy['expiring_soon_medicines'] ?? 0) ?></p>
                <p class="stat-sub">⏰ Next 30 days</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PRESCRIPTIONS - FIXED: Using bill_items for amount -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-prescription"></i> Recent Prescriptions</h3>
            <a href="prescriptions.php?branch=<?= $pharmacy['id'] ?>" class="card-action">View All →</a>
        </div>
        <div class="card-body">
            <?php if (count($recent_prescriptions) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th class="text-right">Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_prescriptions as $rx): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($rx['prescription_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($rx['patient_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($rx['doctor_name'] ?? 'N/A') ?></td>
                                <td class="text-right font-semibold text-blue-600 dark:text-blue-400">
                                    <?= format_currency($rx['total_amount'] ?? 0) ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= getStatusBadge($rx['status'] ?? 'pending') ?>">
                                        <?= ucfirst($rx['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($rx['created_at'] ?? 'now')) ?></td>
                                <td class="text-center">
                                    <a href="view_prescription.php?id=<?= $rx['id'] ?>&branch=<?= $pharmacy['id'] ?>" 
                                       class="btn-view btn-view-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-prescription"></i><p>No prescriptions found</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RECENT INVENTORY -->
    <div class="card animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-boxes"></i> Recent Inventory Updates</h3>
            <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="card-action">View All →</a>
        </div>
        <div class="card-body">
            <?php if (count($recent_inventory) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Price</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_inventory as $item): 
                            $is_out = ($item['quantity'] ?? 0) <= 0;
                            $is_low = ($item['quantity'] ?? 0) <= ($item['reorder_level'] ?? 0) && ($item['quantity'] ?? 0) > 0;
                            $is_expired = !empty($item['expiry_date']) && strtotime($item['expiry_date']) < time();
                            $is_expiring = !empty($item['expiry_date']) && strtotime($item['expiry_date']) > time() && strtotime($item['expiry_date']) < strtotime('+30 days');
                            if ($is_out || $is_expired) $status_class = 'danger';
                            elseif ($is_low || $is_expiring) $status_class = 'warning';
                            else $status_class = 'success';
                        ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                <td class="text-right font-semibold <?= $is_out ? 'text-red-600 dark:text-red-400' : ($is_low ? 'text-yellow-600 dark:text-yellow-400' : 'text-green-600 dark:text-green-400') ?>">
                                    <?= number_format($item['quantity'] ?? 0) ?>
                                </td>
                                <td class="text-right">TSh <?= number_format($item['selling_price'] ?? 0, 0) ?></td>
                                <td class="<?= $is_expired ? 'text-red-600 dark:text-red-400 font-bold' : ($is_expiring ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-500 dark:text-gray-400') ?>">
                                    <?= !empty($item['expiry_date']) ? date('M d, Y', strtotime($item['expiry_date'])) : 'N/A' ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $status_class ?>">
                                        <?php if ($is_out): ?><i class="fas fa-times-circle"></i> Out
                                        <?php elseif ($is_low): ?><i class="fas fa-exclamation-triangle"></i> Low
                                        <?php elseif ($is_expired): ?><i class="fas fa-skull"></i> Expired
                                        <?php elseif ($is_expiring): ?><i class="fas fa-clock"></i> Soon
                                        <?php else: ?><i class="fas fa-check-circle"></i> OK
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="edit_inventory.php?id=<?= $item['id'] ?>&branch=<?= $pharmacy['id'] ?>" 
                                       class="btn-view btn-view-sm btn-view-outline">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-boxes"></i><p>No inventory items found</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RECENT OTC SALES -->
    <div class="card animate-fade-in-up" style="animation-delay:0.3s;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-shopping-cart"></i> Recent OTC Sales</h3>
            <a href="otc_sales.php?branch=<?= $pharmacy['id'] ?>" class="card-action">View All →</a>
        </div>
        <div class="card-body">
            <?php if (count($recent_otc_sales) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sale #</th>
                            <th>Customer</th>
                            <th class="text-right">Net Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_otc_sales as $sale): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                <td class="text-right font-semibold text-green-600 dark:text-green-400">
                                    TSh <?= number_format($sale['net_amount'] ?? 0, 0) ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= getStatusBadge($sale['payment_status'] ?? 'pending') ?>">
                                        <?= ucfirst($sale['payment_status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($sale['created_at'] ?? 'now')) ?></td>
                                <td class="text-center">
                                    <a href="view_otc_sale.php?id=<?= $sale['id'] ?>&branch=<?= $pharmacy['id'] ?>" 
                                       class="btn-view btn-view-sm btn-view-success">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-shopping-cart"></i><p>No OTC sales found</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PHARMACISTS -->
    <div class="card animate-fade-in-up" style="animation-delay:0.35s;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-user-md"></i> Pharmacists (<?= count($pharmacists) ?>)</h3>
            <a href="add_employee.php?branch=<?= $pharmacy['id'] ?>&role=pharmacy" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Pharmacist
            </a>
        </div>
        <div class="card-body">
            <?php if (count($pharmacists) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pharmacists as $pharmacist): ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($pharmacist['full_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($pharmacist['email'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($pharmacist['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge <?= $pharmacist['status'] === 'active' ? 'success' : 'danger' ?>">
                                        <?= ucfirst($pharmacist['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="view_employee.php?id=<?= $pharmacist['id'] ?>&branch=<?= $pharmacy['id'] ?>" 
                                       class="btn-view btn-view-sm">
                                        <i class="fas fa-user"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-user-md"></i><p>No pharmacists assigned</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Pharmacy Details - <?= htmlspecialchars($pharmacy['name']) ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
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

    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var overlay = document.getElementById('sidebarOverlay');
    
    function toggleSidebar() {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
        document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
    }
    
    sidebarToggle?.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleSidebar();
    });
    
    overlay?.addEventListener('click', function() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
    
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
    });

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.delete('error');
        window.location.href = url.toString();
    }

    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var url = new URL(window.location.href);
            url.searchParams.set('search', query);
            window.location.href = url.toString();
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

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

    console.log('%c💊 Braick Dispensary - View Pharmacy', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏥 Pharmacy: <?= htmlspecialchars($pharmacy['name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Total Revenue: <?= format_currency($total_revenue) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ FIXED: Prescription amount from bill_items (medication items)', 'font-size:13px; color:#34D399;');
    console.log('%c📊 Using tables: branches, users, medications_inventory, prescriptions, bills, bill_items, otc_sales', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>