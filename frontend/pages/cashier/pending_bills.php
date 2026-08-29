<?php
// ================================================================
// FILE: frontend/pages/cashier/pending_bills.php
// CASHIER - PENDING BILLS LIST
// FIXED: Shows pharmacy discount on bills
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

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

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$is_admin = ($user_role === 'admin');
$is_reception = ($user_role === 'reception');

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$message = '';
$message_type = '';

$pending_bills = [];
$total_pending_amount = 0;
$total_bills_count = 0;
$currency = 'TSh';

try {
    // ================================================================
    // HANDLE CANCEL BILL ACTION
    // ================================================================
    if (isset($_GET['cancel_bill']) && is_numeric($_GET['cancel_bill'])) {
        $bill_id = (int)$_GET['cancel_bill'];
        
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("SELECT * FROM bills WHERE id = ? AND branch_id = ?");
            $stmt->execute([$bill_id, $user_branch_id]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($bill) {
                if (in_array($bill['status'], ['pending', 'partial'])) {
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM payments WHERE bill_id = ?");
                    $stmt->execute([$bill_id]);
                    $payment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                    
                    if ($payment_count == 0) {
                        $stmt = $db->prepare("
                            UPDATE bills 
                            SET status = 'cancelled', 
                                updated_at = NOW() 
                            WHERE id = ? AND branch_id = ?
                        ");
                        $stmt->execute([$bill_id, $user_branch_id]);
                        
                        $stmt = $db->prepare("
                            UPDATE bill_items 
                            SET status = 'cancelled',
                                updated_at = NOW()
                            WHERE bill_id = ?
                        ");
                        $stmt->execute([$bill_id]);
                        
                        $db->commit();
                        $_SESSION['flash_message'] = "✅ Bill #" . $bill['bill_number'] . " cancelled successfully!";
                        $_SESSION['flash_type'] = 'success';
                    } else {
                        $db->rollBack();
                        $_SESSION['flash_message'] = "❌ Cannot cancel bill with existing payments.";
                        $_SESSION['flash_type'] = 'error';
                    }
                } else {
                    $db->rollBack();
                    $_SESSION['flash_message'] = "⚠️ Bill is already " . ucfirst($bill['status']);
                    $_SESSION['flash_type'] = 'warning';
                }
            } else {
                $db->rollBack();
                $_SESSION['flash_message'] = "❌ Bill not found.";
                $_SESSION['flash_type'] = 'error';
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash_message'] = "❌ Error: " . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
        }
        
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
    
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $message_type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
    
    // ================================================================
    // BUILD FILTERS
    // ================================================================
    $date_condition = "";
    $params = [];
    
    switch ($filter) {
        case 'today':
            $date_condition = "AND DATE(b.created_at) = CURDATE()";
            break;
        case 'week':
            $date_condition = "AND b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $date_condition = "AND b.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case '3months':
            $date_condition = "AND b.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            break;
        case '6months':
            $date_condition = "AND b.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
            break;
        case 'year':
            $date_condition = "AND b.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
        case 'custom':
            if (!empty($start_date) && !empty($end_date)) {
                $date_condition = "AND DATE(b.created_at) BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
            }
            break;
        default:
            $date_condition = "";
            break;
    }
    
    $search_condition = "";
    if (!empty($search)) {
        $search_condition = "AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR b.bill_number LIKE ? OR p.phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // ================================================================
    // GET PENDING BILLS - WITH DISCOUNT INFO
    // ================================================================
    $sql = "
        SELECT 
            b.*,
            p.full_name as patient_name,
            p.patient_id as patient_id_number,
            p.phone,
            p.gender,
            p.date_of_birth,
            u.full_name as created_by_name,
            v.visit_number,
            v.visit_type,
            v.status as visit_status,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND status != 'cancelled') as item_count,
            (SELECT COUNT(*) FROM payments WHERE bill_id = b.id) as payment_count,
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE bill_id = b.id) as total_paid,
            (SELECT COALESCE(SUM(discount_amount), 0) FROM bill_items WHERE bill_id = b.id AND item_type = 'medication') as pharmacy_discount,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND item_type = 'medication' AND discount_amount > 0) as med_discount_items
        FROM bills b
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN users u ON b.created_by = u.id
        LEFT JOIN visits v ON b.visit_id = v.id
        WHERE b.branch_id = ? 
        AND b.status IN ('pending', 'partial')
        $date_condition
        $search_condition
        ORDER BY b.created_at DESC
    ";
    
    $stmt = $db->prepare($sql);
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
                'date_of_birth' => $bill['date_of_birth'] ?? null,
                'bills' => [],
                'total_amount' => 0,
                'total_balance' => 0,
                'total_paid' => 0,
                'total_discount' => 0,
                'bill_count' => 0
            ];
        }
        
        $patient_bills[$patient_id]['bills'][] = $bill;
        $patient_bills[$patient_id]['total_amount'] += $bill['total_amount'];
        $patient_bills[$patient_id]['total_balance'] += $bill['balance'];
        $patient_bills[$patient_id]['total_paid'] += $bill['paid_amount'];
        $patient_bills[$patient_id]['total_discount'] += ($bill['pharmacy_discount'] ?? 0);
        $patient_bills[$patient_id]['bill_count']++;
    }
    
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

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        /* ================================================================
           ROOT VARIABLES
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
            --warning-dark: #B45309;
            --warning-bg: #FEF3C7;
            --info: #0B5ED7;
            --info-bg: #E8F0FE;
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
            --shadow-2xl: 0 25px 50px rgba(0,0,0,0.15);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 16px;
            --radius-sm: 10px;
            --radius-xs: 6px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
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
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.4);
            --primary-bg: #1E3A5F;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3D2E0A;
            --info-bg: #1E3A5F;
            --gray-100: #1E293B;
            --gray-200: #334155;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: var(--transition);
        }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            transition: var(--transition);
        }
        
        /* ================================================================
           PAGE HEADER - MODERN GRADIENT
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, #059669 0%, #0B5ED7 50%, #7C3AED 100%);
            border-radius: var(--radius);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(5, 150, 105, 0.25);
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
        
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -10%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .header-content {
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
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
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 4px;
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.1);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .page-header .role-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: var(--radius-sm);
            font-weight: 500;
            font-size: 0.82rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            cursor: pointer;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================
           FILTER SECTION - MODERN
           ================================================================ */
        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 16px 24px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .filter-section:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        
        .filter-row .filter-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .filter-btn {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
        }
        
        .filter-btn:hover {
            border-color: var(--success);
            color: var(--success);
            background: var(--success-bg);
            transform: translateY(-1px);
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
            font-size: 0.6rem;
        }
        
        .filter-divider {
            width: 1px;
            height: 24px;
            background: var(--border-color);
            margin: 0 4px;
        }
        
        .date-picker-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .date-picker-group .form-control {
            padding: 4px 10px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-xs);
            font-size: 0.7rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
            width: auto;
        }
        
        .date-picker-group .form-control:focus {
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }
        
        .date-picker-group .btn-apply {
            padding: 4px 14px;
            border-radius: var(--radius-xs);
            font-size: 0.7rem;
            font-weight: 600;
            background: var(--success);
            color: white;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .date-picker-group .btn-apply:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
        }
        
        /* ================================================================
           STATS CARDS - MODERN
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
            padding: 18px 22px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            border-radius: var(--radius) var(--radius) 0 0;
        }
        
        .stat-card.green::before { background: var(--success); }
        .stat-card.orange::before { background: var(--warning); }
        .stat-card.blue::before { background: var(--primary); }
        .stat-card.purple::before { background: #7C3AED; }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--success);
        }
        
        .stat-card .stat-icon {
            font-size: 1.4rem;
            margin-bottom: 4px;
            display: inline-block;
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        
        .stat-card .stat-number.green { color: var(--success); }
        .stat-card .stat-number.orange { color: var(--warning); }
        .stat-card .stat-number.blue { color: var(--primary); }
        .stat-card .stat-number.purple { color: #7C3AED; }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-top: 2px;
        }
        
        /* ================================================================
           PATIENT CARDS - MODERN ACCORDION
           ================================================================ */
        .patient-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            transition: var(--transition);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 16px;
        }
        
        .patient-card:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        
        .patient-card-header {
            padding: 16px 22px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: var(--transition);
            background: var(--bg-card);
            border-bottom: 1px solid transparent;
        }
        
        .patient-card-header:hover {
            background: var(--primary-bg);
        }
        
        .patient-card-header.expanded {
            border-bottom-color: var(--border-color);
            background: var(--primary-bg);
        }
        
        .patient-card-header .patient-info {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
            min-width: 200px;
        }
        
        .patient-card-header .patient-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            background: linear-gradient(135deg, var(--success), var(--primary));
            flex-shrink: 0;
        }
        
        .patient-card-header .patient-name {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-primary);
        }
        
        .patient-card-header .patient-id {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-family: monospace;
        }
        
        .patient-card-header .patient-meta {
            font-size: 0.7rem;
            color: var(--text-secondary);
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .patient-card-header .patient-totals {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .patient-card-header .total-badge {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .total-badge.orange { background: var(--warning-bg); color: var(--warning); }
        .total-badge.green { background: var(--success-bg); color: var(--success); }
        .total-badge.red { background: var(--danger-bg); color: var(--danger); }
        .total-badge.blue { background: var(--info-bg); color: var(--info); }
        .total-badge.discount { background: #FEF3C7; color: #D97706; border: 1px solid #D97706; }
        
        .patient-card-header .total-amount {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--danger);
        }
        
        .chevron-icon {
            transition: transform 0.3s ease;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .chevron-icon.rotated {
            transform: rotate(180deg);
        }
        
        .patient-card-body {
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.3s ease;
            max-height: 0;
            padding: 0 22px;
            background: var(--bg-card);
        }
        
        .patient-card-body.open {
            max-height: 5000px;
            padding: 16px 22px 22px;
        }
        
        /* ================================================================
           TABLE - MODERN
           ================================================================ */
        .table-wrap {
            overflow-x: auto;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            min-width: 750px;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 600;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            border-bottom: none;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        
        .data-table thead th:first-child {
            border-radius: var(--radius-xs) 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 var(--radius-xs) 0 0;
        }
        
        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr {
            transition: var(--transition);
        }
        
        .data-table tbody tr:hover td {
            background: var(--primary-bg);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .data-table .bill-number {
            font-weight: 600;
            font-size: 0.7rem;
            font-family: monospace;
            color: var(--text-primary);
        }
        
        /* ================================================================
           STATUS BADGES
           ================================================================ */
        .status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        
        .status-badge.pending {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        .status-badge.partial {
            background: var(--info-bg);
            color: var(--info);
        }
        
        .status-badge.paid {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .status-badge.cancelled {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        /* ================================================================
           DISCOUNT BADGE - SMALL
           ================================================================ */
        .discount-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 12px;
            font-size: 0.5rem;
            font-weight: 600;
            background: #FEF3C7;
            color: #D97706;
            border: 1px solid #D97706;
        }
        
        [data-theme="dark"] .discount-badge {
            background: #3A2A1A;
            color: #F59E0B;
            border-color: #D97706;
        }
        
        /* ================================================================
           ACTION BUTTONS - VERTICAL STACK
           ================================================================ */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: stretch;
            min-width: 70px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 6px 12px;
            border-radius: var(--radius-xs);
            font-weight: 600;
            font-size: 0.6rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
            text-align: center;
            white-space: nowrap;
            min-height: 30px;
            width: 100%;
        }
        
        .btn i { font-size: 0.6rem; }
        
        .btn-view {
            background: var(--primary);
            color: white;
        }
        
        .btn-view:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-process {
            background: var(--success);
            color: white;
        }
        
        .btn-process:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-cancel {
            background: var(--danger);
            color: white;
        }
        
        .btn-cancel:hover {
            background: var(--danger-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        
        .btn-cancel:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .action-status {
            font-size: 0.55rem;
            padding: 6px 8px;
            text-align: center;
            border-radius: var(--radius-xs);
            background: var(--gray-100);
            color: var(--text-secondary);
            display: block;
            width: 100%;
            min-height: 30px;
            line-height: 1.4;
        }
        
        /* ================================================================
           PATIENT TOTAL ROW
           ================================================================ */
        .patient-total-row {
            background: var(--primary-bg);
            font-weight: 600;
        }
        
        .patient-total-row td {
            border-top: 2px solid var(--border-color);
            padding: 8px 14px;
            font-size: 0.75rem;
        }
        
        /* ================================================================
           MESSAGE BOX
           ================================================================ */
        .message-box {
            padding: 12px 20px;
            border-radius: var(--radius-sm);
            border: 2px solid transparent;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            font-size: 1.2rem;
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 2px dashed var(--border-color);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 16px;
        }
        
        .empty-state h3 {
            font-size: 1.2rem;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 16px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .brand {
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
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .animate-pulse { animation: pulse 2s ease-in-out infinite; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 20px; }
            .page-header .page-title { font-size: 1.3rem; }
            .filter-section { padding: 12px 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .patient-card-header { flex-direction: column; align-items: flex-start; }
            .patient-card-header .patient-totals { width: 100%; justify-content: flex-start; }
            .data-table { min-width: 600px; }
            .action-buttons { min-width: 60px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-divider { display: none; }
            .date-picker-group { flex-direction: column; }
            .date-picker-group .form-control { width: 100%; }
            .date-picker-group .btn-apply { width: 100%; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div class="header-content">
            <h1 class="page-title">
                <i class="fas fa-clock"></i>
                Pending Bills
                <span class="role-badge"><?= strtoupper($user_role) ?></span>
                <?php if ($is_admin): ?>
                    <span class="role-badge" style="background:rgba(124,58,237,0.4);">
                        <i class="fas fa-user-shield"></i> Admin
                    </span>
                <?php endif; ?>
                <?php if ($is_reception): ?>
                    <span class="role-badge" style="background:rgba(251,191,36,0.3);color:#FCD34D;">
                        <i class="fas fa-eye"></i> Reception
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-file-invoice"></i>
                Manage pending bills at <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-file-invoice"></i> <?= $total_bills_count ?> Bills
                </span>
                <span class="header-badge">
                    <i class="fas fa-users"></i> <?= count($patient_bills) ?> Patients
                </span>
                <?php if ($filter !== 'all' && $filter !== 'custom'): ?>
                <span class="header-badge">
                    <i class="fas fa-filter"></i> <?= ucfirst(str_replace('months', ' Months', $filter)) ?>
                </span>
                <?php endif; ?>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.2);">
                    <i class="fas fa-tag"></i> Pharmacy Discounts Shown
                </span>
            </p>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
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
        <div class="filter-row">
            <span class="filter-label"><i class="fas fa-calendar-alt"></i> Filter:</span>
            
            <a href="?filter=all&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-globe"></i> All
            </a>
            <a href="?filter=today&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">
                <i class="fas fa-calendar-day"></i> Today
            </a>
            <a href="?filter=week&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>">
                <i class="fas fa-calendar-week"></i> 7D
            </a>
            <a href="?filter=month&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 1M
            </a>
            <a href="?filter=3months&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === '3months' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 3M
            </a>
            <a href="?filter=6months&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === '6months' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 6M
            </a>
            <a href="?filter=year&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'year' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 1Y
            </a>
            
            <span class="filter-divider"></span>
            
            <form method="GET" action="" class="date-picker-group" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                <input type="hidden" name="filter" value="custom">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                
                <input type="date" name="start_date" class="form-control" 
                       value="<?= $start_date ?>" placeholder="Start">
                <span style="color:var(--text-secondary);font-size:0.65rem;">→</span>
                <input type="date" name="end_date" class="form-control" 
                       value="<?= $end_date ?>" placeholder="End">
                <button type="submit" class="btn-apply">
                    <i class="fas fa-check"></i> Apply
                </button>
                <?php if ($filter === 'custom' && !empty($start_date) && !empty($end_date)): ?>
                    <a href="?filter=all&search=<?= urlencode($search) ?>" class="btn-apply" style="background:var(--danger);">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-card orange">
            <div class="stat-icon">📋</div>
            <p class="stat-number orange"><?= $total_bills_count ?></p>
            <p class="stat-label">Total Pending Bills</p>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon">👤</div>
            <p class="stat-number blue"><?= count($patient_bills) ?></p>
            <p class="stat-label">Patients with Bills</p>
        </div>
        <div class="stat-card green">
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
        <?php if ($is_admin): ?>
        <div class="stat-card purple">
            <div class="stat-icon">💰</div>
            <p class="stat-number purple"><?= $currency ?> <?= number_format($total_pending_amount, 0) ?></p>
            <p class="stat-label">Total Balance</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT BILLS LIST -->
    <!-- ================================================================ -->
    <?php if (count($patient_bills) > 0): ?>
        <?php foreach ($patient_bills as $patient): ?>
            <div class="patient-card animate-fade-in-up">
                <!-- Patient Header -->
                <div class="patient-card-header" onclick="togglePatient(<?= $patient['patient_id'] ?>)">
                    <div class="patient-info">
                        <div class="patient-avatar">
                            <?= strtoupper(substr($patient['patient_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="patient-name">
                                <?= htmlspecialchars($patient['patient_name']) ?>
                            </div>
                            <div class="patient-meta">
                                <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id_number'] ?? 'N/A') ?></span>
                                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                                <?php if ($patient['gender']): ?>
                                    <span><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($patient['gender']) ?></span>
                                <?php endif; ?>
                                <?php if ($patient['date_of_birth']): ?>
                                    <span><i class="fas fa-birthday-cake"></i> <?= date('d/m/Y', strtotime($patient['date_of_birth'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="patient-totals">
                        <span class="total-badge orange">
                            <i class="fas fa-file-invoice"></i> <?= $patient['bill_count'] ?> Bills
                        </span>
                        <?php if ($patient['total_discount'] > 0): ?>
                            <span class="total-badge discount">
                                <i class="fas fa-tag"></i> Disc: <?= $currency ?> <?= number_format($patient['total_discount'], 0) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($is_admin): ?>
                            <span class="total-badge red">
                                <i class="fas fa-money-bill"></i> Balance: <?= $currency ?> <?= number_format($patient['total_balance'], 0) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($patient['total_paid'] > 0): ?>
                            <span class="total-badge green">
                                <i class="fas fa-check-circle"></i> Paid: <?= $currency ?> <?= number_format($patient['total_paid'], 0) ?>
                            </span>
                        <?php endif; ?>
                        <span class="total-amount">
                            <?= $currency ?> <?= number_format($patient['total_amount'], 0) ?>
                        </span>
                        <i class="fas fa-chevron-down chevron-icon" id="chevron_<?= $patient['patient_id'] ?>"></i>
                    </div>
                </div>
                
                <!-- Patient Bills Table -->
                <div class="patient-card-body" id="patient_<?= $patient['patient_id'] ?>">
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width:30px;">#</th>
                                    <th style="min-width:110px;">Bill #</th>
                                    <th style="min-width:80px;">Visit</th>
                                    <th style="min-width:80px;">Total</th>
                                    <th style="min-width:80px;">Discount</th>
                                    <th style="min-width:80px;">Paid</th>
                                    <?php if ($is_admin): ?>
                                        <th style="min-width:80px;">Balance</th>
                                    <?php endif; ?>
                                    <th style="min-width:70px;">Status</th>
                                    <th style="min-width:40px;">Items</th>
                                    <th style="min-width:90px;">Created</th>
                                    <th style="min-width:80px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($patient['bills'] as $bill): 
                                    $has_payments = ($bill['payment_count'] ?? 0) > 0;
                                    $can_cancel = in_array($bill['status'], ['pending', 'partial']) && !$has_payments;
                                    $discount = $bill['pharmacy_discount'] ?? 0;
                                    $has_discount = $discount > 0;
                                ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td>
                                            <span class="bill-number"><?= htmlspecialchars($bill['bill_number']) ?></span>
                                            <?php if (($bill['total_amount'] ?? 0) == 0): ?>
                                                <span class="text-xs text-gray-400 block">(Zero)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($bill['visit_number']): ?>
                                                <span class="text-xs font-medium"><?= htmlspecialchars($bill['visit_number']) ?></span>
                                                <span class="text-xs text-gray-400 block"><?= ucfirst($bill['visit_type'] ?? 'N/A') ?></span>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="font-semibold"><?= $currency ?> <?= number_format($bill['total_amount'], 0) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($has_discount): ?>
                                                <span style="color:var(--warning);font-weight:600;">
                                                    -<?= $currency ?> <?= number_format($discount, 0) ?>
                                                    <span class="discount-badge" style="display:block;margin-top:2px;">
                                                        <i class="fas fa-prescription"></i> Pharmacy
                                                    </span>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400">None</span>
                                            <?php endif; ?>
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
                                            <span class="text-sm font-semibold"><?= $bill['item_count'] ?? 0 ?></span>
                                        </td>
                                        <td>
                                            <span class="text-xs"><?= isset($bill['created_at']) ? date('d/m/Y', strtotime($bill['created_at'])) : 'N/A' ?></span>
                                            <br>
                                            <span style="color:var(--text-secondary);font-size:0.55rem;">
                                                <?= isset($bill['created_at']) ? date('h:i A', strtotime($bill['created_at'])) : '' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="view_bill.php?id=<?= $bill['id'] ?>" class="btn btn-view" title="View Details">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                
                                                <?php if (!$is_admin && ($bill['total_amount'] ?? 0) > 0): ?>
                                                    <a href="process_payment.php?bill_id=<?= $bill['id'] ?>" class="btn btn-process" title="Process Payment">
                                                        <i class="fas fa-money-bill-wave"></i> Pay
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($can_cancel): ?>
                                                    <a href="?cancel_bill=<?= $bill['id'] ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>" 
                                                       class="btn btn-cancel" 
                                                       title="Cancel this bill"
                                                       onclick="return confirm('⚠️ Cancel Bill #<?= htmlspecialchars($bill['bill_number']) ?>?\nPatient: <?= htmlspecialchars($patient['patient_name']) ?>\nAmount: <?= $currency ?> <?= number_format($bill['total_amount'], 0) ?>\n\nThis cannot be undone!');">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </a>
                                                <?php elseif ($has_payments): ?>
                                                    <span class="action-status" title="Has payments">🔒 Has payments</span>
                                                <?php elseif ($bill['status'] === 'cancelled'): ?>
                                                    <span class="action-status">Already cancelled</span>
                                                <?php else: ?>
                                                    <span class="action-status">🔒 <?= ucfirst($bill['status']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <!-- Patient Total -->
                                <tr class="patient-total-row">
                                    <td colspan="2" style="text-align:right;font-size:0.75rem;">
                                        <i class="fas fa-calculator"></i> Patient Total:
                                    </td>
                                    <td></td>
                                    <td><?= $currency ?> <?= number_format($patient['total_amount'], 0) ?></td>
                                    <td>
                                        <?php if ($patient['total_discount'] > 0): ?>
                                            <span style="color:var(--warning);font-weight:600;">
                                                -<?= $currency ?> <?= number_format($patient['total_discount'], 0) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $currency ?> <?= number_format($patient['total_paid'], 0) ?></td>
                                    <?php if ($is_admin): ?>
                                        <td style="color:var(--danger);"><?= $currency ?> <?= number_format($patient['total_balance'], 0) ?></td>
                                        <td colspan="<?= $is_admin ? 4 : 5 ?>"></td>
                                    <?php else: ?>
                                        <td colspan="<?= $is_admin ? 4 : 5 ?>"></td>
                                    <?php endif; ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Patient Actions -->
                    <div style="padding-top:12px;display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid var(--border-color);margin-top:12px;">
                        <a href="patient_bills.php?patient_id=<?= $patient['patient_id'] ?>" class="btn btn-view" style="width:auto;padding:6px 16px;">
                            <i class="fas fa-file-invoice"></i> All Bills
                        </a>
                        <?php if (!$is_admin): ?>
                            <a href="process_payment.php?patient_id=<?= $patient['patient_id'] ?>" class="btn btn-process" style="width:auto;padding:6px 16px;">
                                <i class="fas fa-money-bill-wave"></i> Pay All
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-check-circle" style="color:var(--success);"></i>
            <h3>No Pending Bills</h3>
            <p>All bills have been cleared. Check back later for new pending bills.</p>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Pending Bills
            <span class="text-gray-300 mx-2">|</span>
            <span style="color:<?= $is_reception ? '#FCD34D' : '#FFD700' ?>;font-weight:600;">
                👤 <?= htmlspecialchars($user_full_name) ?>
            </span>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?>
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // Dark mode sync
    (function() {
        var html = document.documentElement;
        function syncDark() {
            if (localStorage.getItem('darkMode') === 'true') {
                html.setAttribute('data-theme', 'dark');
            } else {
                html.removeAttribute('data-theme');
            }
        }
        syncDark();
        window.addEventListener('storage', function(e) {
            if (e.key === 'darkMode') syncDark();
        });
        document.addEventListener('darkModeChanged', function(e) {
            if (e.detail && e.detail.isDark) {
                html.setAttribute('data-theme', 'dark');
            } else {
                html.removeAttribute('data-theme');
            }
        });
    })();

    // Toggle patient bills
    function togglePatient(patientId) {
        var body = document.getElementById('patient_' + patientId);
        var chevron = document.getElementById('chevron_' + patientId);
        var header = body ? body.closest('.patient-card').querySelector('.patient-card-header') : null;
        
        if (body) {
            body.classList.toggle('open');
            if (header) {
                header.classList.toggle('expanded');
            }
        }
        if (chevron) {
            chevron.classList.toggle('rotated');
        }
    }

    // Open first patient by default
    document.addEventListener('DOMContentLoaded', function() {
        var firstBody = document.querySelector('.patient-card-body');
        var firstHeader = document.querySelector('.patient-card-header');
        var firstChevron = document.querySelector('.chevron-icon');
        
        if (firstBody) {
            setTimeout(function() {
                firstBody.classList.add('open');
                if (firstHeader) firstHeader.classList.add('expanded');
                if (firstChevron) firstChevron.classList.add('rotated');
            }, 300);
        }
    });

    // Manual refresh
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        btn.innerHTML = '<span class="spinner"></span> Loading...';
        btn.disabled = true;
        setTimeout(function() {
            window.location.reload();
        }, 800);
    }

    // Update footer time
    function updateFooterTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('footerTimestamp');
        if (el) el.textContent = timeStr;
    }
    updateFooterTime();
    setInterval(updateFooterTime, 1000);

    console.log('%c🏥 Braick - Pending Bills (With Pharmacy Discount)', 'font-size:18px;font-weight:bold;color:#059669;');
    console.log('%c📊 Shows pharmacy discount on each bill', 'font-size:13px;color:#D97706;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px;color:#64748B;');
    console.log('%c📋 Total Bills: <?= $total_bills_count ?>', 'font-size:13px;color:#64748B;');
</script>

</body>
</html>