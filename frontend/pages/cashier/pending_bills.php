<?php
// ================================================================
// FILE: frontend/pages/cashier/pending_bills.php
// CASHIER - PENDING BILLS LIST (GREEN THEME)
// FIXED: Auto-update removed (causing JSON error)
// FIXED: Cancel button works via GET
// FIXED: Buttons vertical (stacked) in one row
// FIXED: Table width compact
// ALLOWS: Cashier, Reception, Admin
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// ALLOWED ROLES: Cashier, Reception, Admin
// ================================================================
$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// CHECK IF USER IS ADMIN OR RECEPTION
// ================================================================
$is_admin = ($user_role === 'admin');
$is_reception = ($user_role === 'reception');

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET FILTER PARAMETERS
// ================================================================
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$message = '';
$message_type = '';

// Initialize variables
$pending_bills = [];
$total_pending_amount = 0;
$total_bills_count = 0;
$currency = 'TSh';

try {
    // ================================================================
    // HANDLE CANCEL BILL ACTION - FIXED
    // ================================================================
    if (isset($_GET['cancel_bill']) && is_numeric($_GET['cancel_bill'])) {
        $bill_id = (int)$_GET['cancel_bill'];
        
        try {
            $db->beginTransaction();
            
            // Get bill details
            $stmt = $db->prepare("SELECT * FROM patient_bills WHERE id = ? AND branch_id = ?");
            $stmt->execute([$bill_id, $user_branch_id]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($bill) {
                // Check if bill is pending or partial (not already cancelled)
                if (in_array($bill['status'], ['pending', 'partial', '0', '1', 0, 1])) {
                    // Check if bill has any payments
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM payments WHERE bill_id = ?");
                    $stmt->execute([$bill_id]);
                    $payment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                    
                    if ($payment_count == 0) {
                        // Update bill status to cancelled
                        $stmt = $db->prepare("
                            UPDATE patient_bills 
                            SET status = 'cancelled', 
                                updated_at = NOW() 
                            WHERE id = ? AND branch_id = ?
                        ");
                        $stmt->execute([$bill_id, $user_branch_id]);
                        
                        // Update bill items to cancelled
                        $stmt = $db->prepare("
                            UPDATE bill_items 
                            SET status = 'cancelled',
                                payment_status = 'cancelled',
                                updated_at = NOW()
                            WHERE bill_id = ?
                        ");
                        $stmt->execute([$bill_id]);
                        
                        $db->commit();
                        $_SESSION['flash_message'] = "✅ Bill #" . $bill['bill_number'] . " has been cancelled successfully!";
                        $_SESSION['flash_type'] = 'success';
                    } else {
                        $db->rollBack();
                        $_SESSION['flash_message'] = "❌ Cannot cancel bill with existing payments. Please refund payments first.";
                        $_SESSION['flash_type'] = 'error';
                    }
                } else {
                    $db->rollBack();
                    if ($bill['status'] === 'cancelled') {
                        $_SESSION['flash_message'] = "⚠️ Bill #" . $bill['bill_number'] . " is already cancelled.";
                        $_SESSION['flash_type'] = 'warning';
                    } else {
                        $_SESSION['flash_message'] = "❌ Bill is already " . ucfirst($bill['status']) . " and cannot be cancelled.";
                        $_SESSION['flash_type'] = 'error';
                    }
                }
            } else {
                $db->rollBack();
                $_SESSION['flash_message'] = "❌ Bill not found.";
                $_SESSION['flash_type'] = 'error';
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash_message'] = "❌ Error cancelling bill: " . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
        }
        
        // Redirect to remove cancel_bill from URL
        $redirect_url = 'pending_bills.php';
        $params = [];
        if ($filter !== 'all') $params[] = 'filter=' . $filter;
        if (!empty($search)) $params[] = 'search=' . urlencode($search);
        if (!empty($start_date)) $params[] = 'start_date=' . $start_date;
        if (!empty($end_date)) $params[] = 'end_date=' . $end_date;
        if (!empty($params)) {
            $redirect_url .= '?' . implode('&', $params);
        }
        header('Location: ' . $redirect_url);
        exit;
    }
    
    // ================================================================
    // GET FLASH MESSAGES
    // ================================================================
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $message_type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
    
    // ================================================================
    // BUILD DATE FILTER
    // ================================================================
    $date_condition = "";
    $params = [];
    
    switch ($filter) {
        case 'today':
            $date_condition = "AND DATE(pb.created_at) = CURDATE()";
            break;
        case 'week':
            $date_condition = "AND pb.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $date_condition = "AND pb.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case '3months':
            $date_condition = "AND pb.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            break;
        case '6months':
            $date_condition = "AND pb.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
            break;
        case 'year':
            $date_condition = "AND pb.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
        case 'custom':
            if (!empty($start_date) && !empty($end_date)) {
                $date_condition = "AND DATE(pb.created_at) BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
            } else {
                $date_condition = "";
            }
            break;
        case 'all':
        default:
            $date_condition = "";
            break;
    }
    
    // ================================================================
    // BUILD SEARCH CONDITION
    // ================================================================
    $search_condition = "";
    if (!empty($search)) {
        $search_condition = "AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR pb.bill_number LIKE ? OR p.phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // ================================================================
    // GET PENDING BILLS - EXCLUDE CANCELLED
    // ================================================================
    $sql = "
        SELECT 
            pb.*, 
            p.full_name as patient_name, 
            p.patient_id as patient_id_number,
            p.phone,
            p.gender,
            u.full_name as created_by_name,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id) as item_count,
            (SELECT COUNT(*) FROM payments WHERE bill_id = pb.id) as payment_count,
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE bill_id = pb.id) as total_paid
        FROM patient_bills pb
        LEFT JOIN patients p ON pb.patient_id = p.id
        LEFT JOIN users u ON pb.created_by = u.id
        WHERE pb.branch_id = ? 
        AND pb.status IN ('pending', 'partial', '0', '1', 0, 1)
        $date_condition
        $search_condition
        ORDER BY pb.created_at DESC
    ";
    
    $stmt = $db->prepare($sql);
    
    // Build parameters
    $exec_params = [$user_branch_id];
    foreach ($params as $param) {
        $exec_params[] = $param;
    }
    
    $stmt->execute($exec_params);
    $pending_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GROUP BILLS BY PATIENT
    // ================================================================
    $patient_bills = [];
    foreach ($pending_bills as $bill) {
        $patient_id = $bill['patient_id'];
        if (!isset($patient_bills[$patient_id])) {
            $patient_bills[$patient_id] = [
                'patient_id' => $patient_id,
                'patient_name' => $bill['patient_name'] ?? 'Unknown Patient',
                'patient_id_number' => $bill['patient_id_number'] ?? 'N/A',
                'phone' => $bill['phone'] ?? 'N/A',
                'gender' => $bill['gender'] ?? 'N/A',
                'bills' => [],
                'total_amount' => 0,
                'total_balance' => 0,
                'total_paid' => 0,
                'bill_count' => 0
            ];
        }
        
        $patient_bills[$patient_id]['bills'][] = $bill;
        $patient_bills[$patient_id]['total_amount'] += $bill['total_amount'];
        $patient_bills[$patient_id]['total_balance'] += $bill['balance'];
        $patient_bills[$patient_id]['total_paid'] += $bill['paid_amount'];
        $patient_bills[$patient_id]['bill_count']++;
    }
    
    // Calculate totals
    $total_bills_count = count($pending_bills);
    $total_pending_amount = 0;
    foreach ($patient_bills as $patient) {
        $total_pending_amount += $patient['total_balance'];
    }
    
    // ================================================================
    // GET SYSTEM SETTINGS
    // ================================================================
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $pending_bills = [];
    $patient_bills = [];
    $total_pending_amount = 0;
    $total_bills_count = 0;
    error_log("Pending bills error: " . $e->getMessage());
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
include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Bills - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - LIGHT MODE (DEFAULT)
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
            --success-light: #34D399;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
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
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-stripe: #E8F0FE;
            --table-hover: #D1FAE5;
            --patient-card-border: #059669;
            --toast-bg: #FFFFFF;
            --toast-text: #1E293B;
            --input-bg: #FFFFFF;
            --input-border: #E2E8F0;
            --input-text: #1E293B;
            --empty-state-color: #64748B;
            --footer-border: #E2E8F0;
            --badge-pending-bg: #FEF3C7;
            --badge-pending-text: #D97706;
            --badge-partial-bg: #E8F0FE;
            --badge-partial-text: #0B5ED7;
            --badge-cancelled-bg: #FEE2E2;
            --badge-cancelled-text: #DC2626;
            --total-row-bg: #E8F0FE;
            --patient-header-bg: #E8F0FE;
            --patient-header-hover: #D1FAE5;
        }
        
        /* ================================================================
           ROOT VARIABLES - DARK MODE
           ================================================================ */
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --table-stripe: #1E293B;
            --table-hover: #1A3A2A;
            --patient-card-border: #34D399;
            --toast-bg: #1E293B;
            --toast-text: #F1F5F9;
            --input-bg: #1E293B;
            --input-border: #334155;
            --input-text: #F1F5F9;
            --empty-state-color: #94A3B8;
            --footer-border: #334155;
            --badge-pending-bg: #3D2E0A;
            --badge-pending-text: #FBBF24;
            --badge-partial-bg: #1E3A5F;
            --badge-partial-text: #6EA8FE;
            --badge-cancelled-bg: #3A1A1A;
            --badge-cancelled-text: #F87171;
            --total-row-bg: #1E3A5F;
            --patient-header-bg: #1E3A5F;
            --patient-header-hover: #1A3A2A;
            --primary-bg: #1E3A5F;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3D2E0A;
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
        ::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            transition: background 0.3s ease;
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--warning), #B45309);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(217, 119, 6, 0.25);
            position: relative;
            overflow: hidden;
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
            background: rgba(255,255,255,0.15);
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
        
        /* ================================================================
           FILTER SECTION - COMPACT
           ================================================================ */
        .filter-section {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            margin-bottom: 16px;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }
        
        .filter-section:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        
        .filter-btn {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .filter-btn:hover {
            border-color: var(--success);
            color: var(--success);
            background: var(--success-bg);
        }
        
        .filter-btn.active {
            background: var(--success);
            color: white;
            border-color: var(--success);
        }
        
        .filter-btn.active:hover {
            background: var(--success-dark);
            border-color: var(--success-dark);
        }
        
        .filter-btn i {
            margin-right: 4px;
        }
        
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px;
        }
        
        .filter-group .filter-label {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-right: 4px;
        }
        
        .date-picker-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .date-picker-group .form-control {
            padding: 3px 8px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.7rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
            width: auto;
        }
        
        .date-picker-group .form-control:focus {
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }
        
        .date-picker-group .btn-apply {
            padding: 3px 12px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            background: var(--success);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .date-picker-group .btn-apply:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
        }
        
        /* ================================================================
           STATS - COMPACT
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            max-width: 1400px;
            margin: 0 auto 16px;
        }
        
        .stat-card-box {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-card-box:hover {
            border-color: var(--success);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card-box .stat-number {
            font-size: 1.6rem;
            font-weight: 700;
        }
        
        .stat-card-box .stat-number.orange {
            color: #D97706;
        }
        
        .stat-card-box .stat-number.green {
            color: var(--success);
        }
        
        .stat-card-box .stat-number.red {
            color: var(--danger);
        }
        
        .stat-card-box .stat-number.blue {
            color: var(--primary);
        }
        
        .stat-card-box .stat-label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-top: 2px;
        }
        
        .stat-card-box .stat-icon {
            font-size: 1.2rem;
            margin-bottom: 2px;
        }
        
        /* ================================================================
           PATIENT CARD - COMPACT
           ================================================================ */
        .patient-card {
            background: var(--bg-card);
            border-radius: 14px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            max-width: 1400px;
            margin: 0 auto 14px;
        }
        
        .patient-card:hover {
            border-color: var(--patient-card-border);
            box-shadow: var(--shadow-md);
        }
        
        .patient-card-header {
            background: var(--patient-header-bg);
            padding: 10px 16px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid var(--border-color);
            cursor: pointer;
            transition: background 0.3s ease, border-color 0.3s ease;
            user-select: none;
        }
        
        .patient-card-header:hover {
            background: var(--patient-header-hover);
        }
        
        .patient-card-header .patient-info {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            flex: 1;
        }
        
        .patient-card-header .patient-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            color: white;
            background: var(--success);
            flex-shrink: 0;
        }
        
        .patient-card-header .patient-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
        }
        
        .patient-card-header .patient-id {
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .patient-card-header .patient-totals {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .patient-card-header .total-badge {
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .patient-card-header .total-badge.orange {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        .patient-card-header .total-badge.green {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .patient-card-header .total-badge.red {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        .patient-card-header .total-amount {
            font-weight: 700;
            font-size: 1rem;
            color: var(--danger);
        }
        
        .chevron-icon {
            transition: transform 0.3s ease;
            font-size: 0.9rem;
            color: var(--text-secondary);
            display: inline-block;
        }
        
        .chevron-icon.rotated {
            transform: rotate(180deg);
        }
        
        .patient-card-body {
            overflow: hidden;
            transition: max-height 0.4s ease-in-out, padding 0.3s ease;
            max-height: 0;
            padding: 0 16px;
            background: var(--bg-card);
        }
        
        .patient-card-body.open {
            max-height: 3000px;
            padding: 12px 16px;
        }
        
        /* ================================================================
           TABLE - COMPACT
           ================================================================ */
        .table-wrap {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            min-width: 750px;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 6px 10px;
            font-weight: 700;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--success);
            border-bottom: 3px solid var(--success-dark);
            white-space: nowrap;
        }
        
        .data-table thead th:first-child {
            border-radius: 6px 0 0 0;
            width: 30px;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 6px 0 0;
        }
        
        .data-table td {
            padding: 6px 10px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .data-table .bill-number {
            font-weight: 600;
            font-size: 0.7rem;
            font-family: monospace;
        }
        
        .data-table .bill-number.pending {
            color: var(--warning);
        }
        
        .data-table .bill-number.partial {
            color: var(--primary);
        }
        
        .data-table .bill-number.cancelled {
            color: var(--danger);
            text-decoration: line-through;
        }
        
        /* ================================================================
           STATUS BADGE - COMPACT
           ================================================================ */
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.pending {
            background: var(--badge-pending-bg);
            color: var(--badge-pending-text);
        }
        
        .status-badge.partial {
            background: var(--badge-partial-bg);
            color: var(--badge-partial-text);
        }
        
        .status-badge.cancelled {
            background: var(--badge-cancelled-bg);
            color: var(--badge-cancelled-text);
        }
        
        /* ================================================================
           BUTTONS - VERTICAL (STACKED) AND COMPACT
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.65rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
            text-align: center;
            white-space: nowrap;
            min-height: 30px;
            width: 100%;
        }
        
        .btn-view {
            background: var(--primary);
            color: white;
        }
        
        .btn-view:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
        }
        
        .btn-process {
            background: var(--success);
            color: white;
        }
        
        .btn-process:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.25);
        }
        
        .btn-cancel {
            background: var(--danger);
            color: white;
        }
        
        .btn-cancel:hover {
            background: var(--danger-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
        }
        
        .btn-cancel:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-sm { 
            padding: 4px 8px; 
            font-size: 0.55rem; 
            border-radius: 4px; 
            min-height: 24px;
        }
        
        /* ================================================================
           ACTION BUTTONS CONTAINER - VERTICAL (STACKED)
           ================================================================ */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 3px;
            align-items: stretch;
            min-width: 65px;
        }
        
        .action-buttons .btn {
            width: 100%;
            justify-content: center;
            padding: 5px 8px;
            font-size: 0.6rem;
            min-height: 28px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 3px;
        }
        
        .action-buttons .btn i {
            font-size: 0.55rem;
        }
        
        .action-status {
            font-size: 0.55rem;
            padding: 5px 8px;
            text-align: center;
            border-radius: 4px;
            background: var(--gray-100);
            color: var(--text-secondary);
            display: block;
            width: 100%;
            min-height: 28px;
            line-height: 1.4;
        }
        
        [data-theme="dark"] .action-status {
            background: var(--gray-700);
            color: var(--gray-400);
        }
        
        /* ================================================================
           PATIENT TOTAL ROW
           ================================================================ */
        .patient-total-row {
            background: var(--total-row-bg);
            font-weight: 700;
            transition: background 0.3s ease;
        }
        
        .patient-total-row td {
            border-bottom: 2px solid var(--border-color);
            padding: 6px 10px;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .role-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--primary-bg);
            color: var(--primary);
            text-transform: uppercase;
        }
        
        .branch-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        .admin-badge {
            display: <?= $is_admin ? 'inline-block' : 'none' ?>;
            background: #7C3AED;
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .reception-badge {
            display: <?= $is_reception ? 'inline-block' : 'none' ?>;
            background: rgba(251,191,36,0.3);
            color: #FCD34D;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            border: 1px solid rgba(251,191,36,0.2);
        }
        
        /* ================================================================
           MESSAGE BOX
           ================================================================ */
        .message-box {
            max-width: 1400px;
            margin: 0 auto 12px;
            padding: 10px 16px;
            border-radius: 10px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .message-box.success {
            background: var(--success-bg);
            color: var(--success);
            border-color: var(--success);
        }
        
        .message-box.error {
            background: var(--danger-bg);
            color: var(--danger);
            border-color: var(--danger);
        }
        
        .message-box.warning {
            background: var(--warning-bg);
            color: var(--warning);
            border-color: var(--warning);
        }
        
        .message-box i {
            margin-right: 6px;
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--empty-state-color);
        }
        
        .empty-state i {
            font-size: 2.5rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 10px;
        }
        
        .empty-state .sub {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 12px 0;
            border-top: 1px solid var(--footer-border);
            margin-top: 20px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
            transition: border-color 0.3s ease;
        }
        
        .footer .footer-brand { 
            color: var(--success); 
            font-weight: 600; 
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        
        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .filter-section { padding: 10px 12px; }
            .filter-group { gap: 4px; }
            .filter-btn { font-size: 0.55rem; padding: 3px 8px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .patient-card-header { flex-direction: column; align-items: flex-start; }
            .patient-card-header .patient-totals { width: 100%; justify-content: flex-start; }
            .data-table { min-width: 600px; }
            .action-buttons { min-width: 55px; }
            .action-buttons .btn { 
                font-size: 0.55rem; 
                padding: 4px 6px; 
                min-height: 24px;
            }
            .action-buttons .btn i { font-size: 0.5rem; }
            .action-status { font-size: 0.5rem; padding: 4px 6px; min-height: 24px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .filter-section { padding: 8px 10px; }
            .filter-btn { font-size: 0.5rem; padding: 2px 6px; }
            .date-picker-group { flex-direction: column; align-items: stretch; }
            .date-picker-group .form-control { width: 100%; }
            .date-picker-group .btn-apply { width: 100%; justify-content: center; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 6px; }
            .stat-card-box { padding: 8px 12px; }
            .stat-card-box .stat-number { font-size: 1.2rem; }
            .data-table { min-width: 500px; font-size: 0.6rem; }
            .patient-card-header .patient-info { width: 100%; }
            .patient-card-header .patient-totals { width: 100%; flex-wrap: wrap; }
            .action-buttons { min-width: 50px; }
            .action-buttons .btn { 
                font-size: 0.5rem; 
                padding: 3px 5px; 
                min-height: 22px;
                gap: 2px;
            }
            .action-buttons .btn i { font-size: 0.45rem; }
            .action-status { 
                font-size: 0.5rem; 
                padding: 3px 5px; 
                min-height: 22px;
            }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - FROM HEADER (Already included) -->
<!-- ================================================================ -->

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clock"></i>
                Pending Bills
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;"><?= strtoupper($user_role) ?></span>
                <?php if ($is_admin): ?>
                    <span class="admin-badge"><i class="fas fa-user-shield"></i> ADMIN VIEW</span>
                <?php endif; ?>
                <?php if ($is_reception): ?>
                    <span class="reception-badge"><i class="fas fa-eye"></i> RECEPTION</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-file-invoice"></i>
                Manage pending bills in <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-file-invoice"></i>
                    <?= $total_bills_count ?> Bills
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-users"></i>
                    <?= count($patient_bills) ?> Patients
                </span>
                
                <?php if ($filter !== 'all' && $filter !== 'custom'): ?>
                <span class="header-badge">
                    <i class="fas fa-filter"></i>
                    <?= ucfirst(str_replace('months', ' Months', $filter)) ?>
                </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section">
        <div class="filter-group" style="margin-bottom:6px;">
            <span class="filter-label"><i class="fas fa-calendar-alt"></i> Filter:</span>
            
            <a href="?filter=all&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-globe"></i> All
            </a>
            <a href="?filter=today&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">
                <i class="fas fa-calendar-day"></i> Today
            </a>
            <a href="?filter=week&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>">
                <i class="fas fa-calendar-week"></i> 1 Week
            </a>
            <a href="?filter=month&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 1 Month
            </a>
            <a href="?filter=3months&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === '3months' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 3 Months
            </a>
            <a href="?filter=6months&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === '6months' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 6 Months
            </a>
            <a href="?filter=year&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'year' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 1 Year
            </a>
        </div>
        
        <form method="GET" action="" class="filter-group" style="border-top:1px solid var(--border-color);padding-top:6px;margin-top:4px;transition:border-color 0.3s ease;">
            <input type="hidden" name="filter" value="custom">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            
            <span class="filter-label"><i class="fas fa-calendar-plus"></i> Custom:</span>
            
            <div class="date-picker-group">
                <input type="date" name="start_date" class="form-control" 
                       value="<?= $start_date ?>" placeholder="Start Date">
                <span style="color:var(--text-secondary);font-size:0.65rem;">to</span>
                <input type="date" name="end_date" class="form-control" 
                       value="<?= $end_date ?>" placeholder="End Date">
                <button type="submit" class="btn-apply">
                    <i class="fas fa-check"></i> Apply
                </button>
                <?php if ($filter === 'custom' && !empty($start_date) && !empty($end_date)): ?>
                    <a href="?filter=all&search=<?= urlencode($search) ?>" class="btn-apply" style="background:#DC2626;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- STATS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-card-box">
            <div class="stat-icon">📋</div>
            <p class="stat-number orange"><?= $total_bills_count ?></p>
            <p class="stat-label">Total Pending Bills</p>
        </div>
        <div class="stat-card-box">
            <div class="stat-icon">👤</div>
            <p class="stat-number blue"><?= count($patient_bills) ?></p>
            <p class="stat-label">Patients with Bills</p>
        </div>
        <?php if ($is_admin): ?>
        <div class="stat-card-box">
            <div class="stat-icon">💰</div>
            <p class="stat-number red"><?= $currency ?> <?= number_format($total_pending_amount, 0) ?></p>
            <p class="stat-label">Total Balance</p>
        </div>
        <?php endif; ?>
        <div class="stat-card-box">
            <div class="stat-icon">📅</div>
            <p class="stat-number green">
                <?php 
                    if ($filter === 'today') echo 'Today';
                    elseif ($filter === 'week') echo '7 Days';
                    elseif ($filter === 'month') echo '30 Days';
                    elseif ($filter === '3months') echo '90 Days';
                    elseif ($filter === '6months') echo '180 Days';
                    elseif ($filter === 'year') echo '365 Days';
                    elseif ($filter === 'custom') echo 'Custom';
                    else echo 'All Time';
                ?>
            </p>
            <p class="stat-label">Date Range</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT BILLS LIST -->
    <!-- ================================================================ -->
    <?php if (count($patient_bills) > 0): ?>
        <?php foreach ($patient_bills as $patient): ?>
            <div class="patient-card animate-fade-in-up">
                <!-- Patient Header - Click to toggle -->
                <div class="patient-card-header" onclick="togglePatient(<?= $patient['patient_id'] ?>)">
                    <div class="patient-info">
                        <div class="patient-avatar">
                            <?= strtoupper(substr($patient['patient_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="patient-name">
                                <?= htmlspecialchars($patient['patient_name']) ?>
                                <span class="patient-id ml-2">
                                    <?= htmlspecialchars($patient['patient_id_number'] ?? 'N/A') ?>
                                </span>
                            </div>
                            <div style="font-size:0.7rem;color:var(--text-secondary);">
                                <i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?>
                                <?php if ($patient['gender']): ?>
                                    <span class="mx-1">•</span>
                                    <i class="fas fa-venus-mars mr-1"></i> <?= htmlspecialchars($patient['gender']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="patient-totals">
                        <span class="total-badge orange">
                            <i class="fas fa-file-invoice mr-1"></i> <?= $patient['bill_count'] ?> Bills
                        </span>
                        <?php if ($is_admin): ?>
                            <span class="total-badge red">
                                <i class="fas fa-money-bill mr-1"></i> Balance: <?= $currency ?> <?= number_format($patient['total_balance'], 0) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($patient['total_paid'] > 0): ?>
                            <span class="total-badge green">
                                <i class="fas fa-check-circle mr-1"></i> Paid: <?= $currency ?> <?= number_format($patient['total_paid'], 0) ?>
                            </span>
                        <?php endif; ?>
                        <span class="total-amount">
                            <?= $currency ?> <?= number_format($patient['total_amount'], 0) ?>
                        </span>
                        <i class="fas fa-chevron-down chevron-icon" id="chevron_<?= $patient['patient_id'] ?>"></i>
                    </div>
                </div>
                
                <!-- Patient Bills Table - Collapsible -->
                <div class="patient-card-body" id="patient_<?= $patient['patient_id'] ?>">
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width:30px;">#</th>
                                    <th style="min-width:100px;">Bill #</th>
                                    <th style="min-width:80px;">Total</th>
                                    <th style="min-width:80px;">Paid</th>
                                    <?php if ($is_admin): ?>
                                        <th style="min-width:80px;">Balance</th>
                                    <?php endif; ?>
                                    <th style="min-width:70px;">Status</th>
                                    <th style="min-width:40px;">Items</th>
                                    <th style="min-width:80px;">Created</th>
                                    <th style="min-width:75px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($patient['bills'] as $bill): 
                                    $has_payments = ($bill['payment_count'] ?? 0) > 0;
                                    $bill_amount_zero = ($bill['total_amount'] ?? 0) == 0;
                                    $can_cancel = in_array($bill['status'], ['pending', 'partial', '0', '1', 0, 1]) && !$has_payments;
                                ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td>
                                            <span class="bill-number <?= $bill['status'] ?>">
                                                <?= htmlspecialchars($bill['bill_number']) ?>
                                            </span>
                                            <?php if ($bill_amount_zero): ?>
                                                <span class="text-xs text-gray-400 block">(Zero)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="font-semibold">
                                                <?= $currency ?> <?= number_format($bill['total_amount'], 0) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="color:var(--success);">
                                                <?= $currency ?> <?= number_format($bill['paid_amount'] ?? 0, 0) ?>
                                            </span>
                                            <?php if ($has_payments): ?>
                                                <span class="text-xs text-gray-400 block"><?= $bill['payment_count'] ?> payment(s)</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($is_admin): ?>
                                            <td>
                                                <span class="font-semibold" style="color:var(--danger);">
                                                    <?= $currency ?> <?= number_format($bill['balance'], 0) ?>
                                                </span>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="status-badge <?= $bill['status'] ?>">
                                                <?= ucfirst($bill['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="text-sm font-semibold">
                                                <?= $bill['item_count'] ?? 0 ?>
                                            </span>
                                        </td>
                                        <td class="text-xs">
                                            <?= isset($bill['created_at']) ? date('d/m/Y', strtotime($bill['created_at'])) : 'N/A' ?>
                                            <br>
                                            <span style="color:var(--text-secondary);font-size:0.55rem;">
                                                <?= isset($bill['created_at']) ? date('h:i A', strtotime($bill['created_at'])) : '' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <!-- Action Buttons - VERTICAL STACK -->
                                            <div class="action-buttons">
                                                <!-- View Button -->
                                                <a href="view_bill.php?id=<?= $bill['id'] ?>" class="btn btn-view" title="View Bill Details">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                
                                                <!-- Process Payment Button -->
                                                <?php if (!$is_admin && !$bill_amount_zero): ?>
                                                    <a href="process_payment.php?bill_id=<?= $bill['id'] ?>" class="btn btn-process" title="Process Payment">
                                                        <i class="fas fa-money-bill-wave"></i> Pay
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <!-- Cancel Bill Button -->
                                                <?php if ($can_cancel): ?>
                                                    <a href="?cancel_bill=<?= $bill['id'] ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>" 
                                                       class="btn btn-cancel" 
                                                       title="Cancel this bill"
                                                       onclick="return confirm('⚠️ Are you sure you want to cancel Bill #<?= htmlspecialchars($bill['bill_number']) ?>?\n\nPatient: <?= htmlspecialchars($patient['patient_name']) ?>\nAmount: <?= $currency ?> <?= number_format($bill['total_amount'], 0) ?>\n\nThis action cannot be undone!');">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </a>
                                                <?php elseif ($has_payments): ?>
                                                    <span class="action-status" title="Cannot cancel bill with payments">
                                                        🔒 Has payments
                                                    </span>
                                                <?php elseif ($bill['status'] === 'cancelled'): ?>
                                                    <span class="action-status">Already cancelled</span>
                                                <?php else: ?>
                                                    <span class="action-status" title="Cannot cancel - <?= ucfirst($bill['status']) ?>">
                                                        🔒 <?= ucfirst($bill['status']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <!-- Patient Total Row -->
                                <tr class="patient-total-row">
                                    <td colspan="2" style="text-align:right;font-size:0.75rem;">
                                        <i class="fas fa-calculator mr-1"></i> Patient Total:
                                    </td>
                                    <td>
                                        <?= $currency ?> <?= number_format($patient['total_amount'], 0) ?>
                                    </td>
                                    <td>
                                        <?= $currency ?> <?= number_format($patient['total_paid'], 0) ?>
                                    </td>
                                    <?php if ($is_admin): ?>
                                        <td style="color:var(--danger);">
                                            <?= $currency ?> <?= number_format($patient['total_balance'], 0) ?>
                                        </td>
                                    <?php endif; ?>
                                    <td colspan="<?= $is_admin ? 6 : 5 ?>"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Patient Action Buttons -->
                    <div style="padding: 8px 0 0; display: flex; gap: 6px; flex-wrap: wrap; border-top: 1px solid var(--border-color); margin-top: 6px; transition: border-color 0.3s ease;">
                        <a href="patient_bills.php?patient_id=<?= $patient['patient_id'] ?>" class="btn btn-view btn-sm" style="width:auto;">
                            <i class="fas fa-file-invoice"></i> All Bills
                        </a>
                        <?php if (!$is_admin): ?>
                            <a href="process_payment.php?patient_id=<?= $patient['patient_id'] ?>" class="btn btn-process btn-sm" style="width:auto;">
                                <i class="fas fa-money-bill-wave"></i> Pay All
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state" style="max-width:1400px;margin:0 auto;">
            <i class="fas fa-exclamation-circle" style="color:var(--warning);"></i>
            <p class="text-lg font-semibold">No Pending Bills Found</p>
            <p class="sub">Check if there are any bills with status 'pending' or 'partial'</p>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Pending Bills
            <span class="text-gray-300 mx-2">|</span>
            <span style="color:<?= $is_reception ? '#FCD34D' : '#FFD700' ?>;font-weight:600;">
                👤 <?= htmlspecialchars($user_full_name) ?>
                <?php if ($is_reception): ?>
                    <span style="color:#FCD34D;font-weight:500;font-size:0.55rem;background:rgba(251,191,36,0.15);padding:2px 10px;border-radius:10px;margin-left:4px;">👀 Reception</span>
                <?php endif; ?>
            </span>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

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

<!-- ================================================================ -->
<!-- JAVASCRIPT - NO AUTO-UPDATE (TO AVOID JSON ERROR) -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE - SYNC WITH HEADER
    // ================================================================
    (function() {
        var htmlElement = document.documentElement;
        
        function syncDarkMode() {
            var isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) {
                htmlElement.setAttribute('data-theme', 'dark');
            } else {
                htmlElement.removeAttribute('data-theme');
            }
        }
        
        syncDarkMode();
        
        window.addEventListener('storage', function(e) {
            if (e.key === 'darkMode') {
                syncDarkMode();
            }
        });
        
        document.addEventListener('darkModeChanged', function(e) {
            var isDark = e.detail && e.detail.isDark;
            if (isDark) {
                htmlElement.setAttribute('data-theme', 'dark');
            } else {
                htmlElement.removeAttribute('data-theme');
            }
        });
    })();

    // ================================================================
    // CLOCK - UPDATE EVERY SECOND
    // ================================================================
    function updateClock() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        
        var clockDisplay = document.getElementById('clockDisplay');
        if (clockDisplay) {
            clockDisplay.textContent = dateStr + ' • ' + timeStr;
        }
    }
    
    setInterval(updateClock, 1000);
    updateClock();

    // ================================================================
    // TOGGLE PATIENT BILLS
    // ================================================================
    function togglePatient(patientId) {
        var body = document.getElementById('patient_' + patientId);
        var chevron = document.getElementById('chevron_' + patientId);
        
        if (body) {
            body.classList.toggle('open');
        }
        if (chevron) {
            chevron.classList.toggle('rotated');
        }
    }

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar) sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // DATE & TIME (for footer)
    // ================================================================
    function updateFooterTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
    }
    updateFooterTime();

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        var filter = '<?= $filter ?>';
        var start_date = '<?= $start_date ?>';
        var end_date = '<?= $end_date ?>';
        if (query.length > 0) {
            window.location.href = 'pending_bills.php?search=' + encodeURIComponent(query) + '&filter=' + filter + '&start_date=' + start_date + '&end_date=' + end_date;
        }
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        if (!toast) return;
        
        toast.className = 'toast-custom ' + (type || 'info');
        toastTitle.textContent = title || 'Notification';
        toastMessage.textContent = message || '';
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
    // MANUAL REFRESH - WITHOUT AUTO-UPDATE
    // ================================================================
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        btn.innerHTML = '<span class="spinner"></span> Loading...';
        btn.disabled = true;
        
        setTimeout(function() {
            window.location.reload();
        }, 1000);
        
        setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            btn.disabled = false;
            showToast('✅ Refreshed', 'Page data updated manually', 'success');
        }, 2000);
    }

    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        // Open first patient card by default
        var firstPatient = document.querySelector('.patient-card-body');
        if (firstPatient) {
            setTimeout(function() {
                firstPatient.classList.add('open');
                var chevron = document.querySelector('.chevron-icon');
                if (chevron) {
                    chevron.classList.add('rotated');
                }
            }, 300);
        }
    });

    console.log('%c⏳ Braick - Pending Bills (Cancel Working - No Auto-Update)', 'font-size:18px; font-weight:bold; color:#D97706;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#059669;');
    console.log('%c✅ ALLOWED ROLES: Cashier, Reception, Admin', 'font-size:13px; color:#34D399;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📋 Total Bills Found: <?= $total_bills_count ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👤 Patients: <?= count($patient_bills) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🗑️ Cancel button uses GET request (reliable)', 'font-size:13px; color:#DC2626;');
    console.log('%c✅ Cancelled bills removed from pending list', 'font-size:13px; color:#34D399;');
    console.log('%c📌 Buttons are VERTICAL (STACKED) in one row', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Table width is COMPACT', 'font-size:13px; color:#059669;');
    console.log('%c❌ Auto-update removed to avoid JSON errors', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>