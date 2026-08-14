<?php
// ================================================================
// FILE: frontend/pages/cashier/patient_bills.php
// CASHIER - VIEW PAID BILLS FOR A SPECIFIC PATIENT
// DISPLAYS ONLY PAID BILLS WITH "PAID" WATERMARK
// FIXED: Uses shared header with clock
// FIXED: Dark mode fully working with header
// FIXED: Reception access allowed
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
    header('Location: ../login.php');
    exit;
}

// ================================================================
// ALLOWED ROLES: Cashier, Reception, Admin
// ================================================================
$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Cashier';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// CHECK IF USER IS RECEPTION
// ================================================================
$is_reception = ($user_role === 'reception');

// ================================================================
// CHECK IF USER IS ADMIN
// ================================================================
$is_admin = ($user_role === 'admin');

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';
$message_type = '';
$currency = 'TSh';

// ================================================================
// GET PATIENT ID FROM URL
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if ($patient_id <= 0) {
    header('Location: patients.php');
    exit;
}

try {
    // ================================================================
    // GET SYSTEM SETTINGS
    // ================================================================
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';

    // ================================================================
    // GET PATIENT DETAILS
    // ================================================================
    $stmt = $db->prepare("
        SELECT p.*, b.name as branch_name 
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE p.id = ? AND p.branch_id = ?
    ");
    $stmt->execute([$patient_id, $user_branch_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        header('Location: patients.php');
        exit;
    }

    // ================================================================
    // GET ONLY PAID BILLS FOR THIS PATIENT
    // ================================================================
    $stmt = $db->prepare("
        SELECT 
            pb.*,
            v.visit_number,
            v.visit_type,
            v.visit_date,
            u.full_name as doctor_name,
            u2.full_name as created_by_name,
            (
                SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id AND status != 'cancelled'
            ) as item_count,
            (
                SELECT COALESCE(SUM(total_price), 0) FROM bill_items WHERE bill_id = pb.id AND status != 'cancelled'
            ) as items_total
        FROM patient_bills pb
        LEFT JOIN visits v ON pb.visit_id = v.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN users u2 ON pb.created_by = u2.id
        WHERE pb.patient_id = ? AND pb.branch_id = ? AND pb.status = 'paid'
        ORDER BY pb.updated_at DESC
    ");
    $stmt->execute([$patient_id, $user_branch_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ================================================================
    // GET BILL ITEMS FOR EACH BILL
    // ================================================================
    $bill_items = [];
    foreach ($bills as $bill) {
        $stmt = $db->prepare("
            SELECT * FROM bill_items 
            WHERE bill_id = ? AND status != 'cancelled'
            ORDER BY created_at ASC
        ");
        $stmt->execute([$bill['id']]);
        $bill_items[$bill['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================================================
    // CALCULATE SUMMARY
    // ================================================================
    $total_bills = count($bills);
    $total_amount = 0;
    $total_paid = 0;

    foreach ($bills as $bill) {
        $total_amount += (float)$bill['total_amount'];
        $total_paid += (float)$bill['paid_amount'];
    }

} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $bills = [];
    $bill_items = [];
    $total_bills = 0;
    $total_amount = 0;
    $total_paid = 0;
    $currency = 'TSh';
    error_log("Patient bills error: " . $e->getMessage());
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/cashier_header.php';

// ================================================================
// SIDEBAR - CASHIER SIDEBAR (RECEPTION HAS FULL ACCESS)
// ================================================================
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paid Bills - <?= htmlspecialchars($patient['full_name'] ?? 'Patient') ?> - Braick Dispensary</title>
    
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
            --toast-bg: #FFFFFF;
            --toast-text: #1E293B;
            --input-bg: #FFFFFF;
            --input-border: #E2E8F0;
            --input-text: #1E293B;
            --empty-state-color: #64748B;
            --footer-border: #E2E8F0;
            --watermark-color: rgba(5, 150, 105, 0.08);
            --watermark-border: rgba(5, 150, 105, 0.10);
            --bill-header-bg: #F8FAFC;
            --bill-footer-bg: #F8FAFC;
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
            --toast-bg: #1E293B;
            --toast-text: #F1F5F9;
            --input-bg: #1E293B;
            --input-border: #334155;
            --input-text: #F1F5F9;
            --empty-state-color: #94A3B8;
            --footer-border: #334155;
            --watermark-color: rgba(52, 211, 153, 0.06);
            --watermark-border: rgba(52, 211, 153, 0.06);
            --bill-header-bg: #1E293B;
            --bill-footer-bg: #1E293B;
            --primary-bg: #1E3A5F;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3D2E0A;
            --purple-bg: #2D1B5F;
        }
        
        /* ================================================================
           GLOBAL STYLES
           ================================================================ */
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
        
        /* ================================================================
           MAIN CONTENT OVERRIDE
           ================================================================ */
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
           PATIENT PROFILE CARD
           ================================================================ */
        .patient-profile-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px 28px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .patient-profile-card:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        
        .patient-avatar-large {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(0,0,0,0.15);
        }
        
        .patient-info h2 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }
        
        .patient-info .patient-id {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .patient-info .patient-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 4px;
        }
        
        .patient-info .patient-meta span {
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 4px;
            background: var(--bg-body);
            padding: 2px 12px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
        }
        
        .patient-info .patient-meta span i {
            color: var(--success);
        }
        
        /* ================================================================
           SUMMARY STATS
           ================================================================ */
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .summary-stat {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .summary-stat:hover {
            border-color: var(--success);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .summary-stat .stat-number {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--success);
        }
        
        .summary-stat .stat-number.purple {
            color: var(--purple);
        }
        
        .summary-stat .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-top: 2px;
        }
        
        /* ================================================================
           BILL ROW WITH WATERMARK
           ================================================================ */
        .bill-row {
            background: var(--bg-card);
            border-radius: 12px;
            border: 2px solid var(--border-color);
            margin-bottom: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .bill-row:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        
        /* ================================================================
           "PAID" WATERMARK
           ================================================================ */
        .bill-row .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 7rem;
            font-weight: 900;
            color: var(--watermark-color);
            letter-spacing: 8px;
            text-transform: uppercase;
            pointer-events: none;
            z-index: 1;
            white-space: nowrap;
            user-select: none;
            font-family: 'Arial Black', 'Impact', sans-serif;
            text-shadow: 0 2px 10px rgba(5, 150, 105, 0.05);
            border: 4px solid var(--watermark-border);
            padding: 20px 60px;
            border-radius: 20px;
        }
        
        .bill-row .watermark::before {
            content: '';
            position: absolute;
            top: -20px;
            left: -20px;
            right: -20px;
            bottom: -20px;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 40px,
                rgba(5, 150, 105, 0.02) 40px,
                rgba(5, 150, 105, 0.02) 41px
            );
            border-radius: 20px;
            pointer-events: none;
        }
        
        /* ================================================================
           BILL ROW HEADER
           ================================================================ */
        .bill-row-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            background: var(--bill-header-bg);
            border-bottom: 2px solid var(--border-color);
            cursor: pointer;
            transition: background 0.2s ease;
            flex-wrap: wrap;
            gap: 8px;
            position: relative;
            z-index: 2;
        }
        
        .bill-row-header:hover {
            background: var(--table-hover);
        }
        
        .bill-row-header .bill-number {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--success);
            font-family: monospace;
        }
        
        .bill-row-header .bill-status {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 14px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        .bill-row-header .bill-amount {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-primary);
        }
        
        .bill-row-header .bill-amount .amount-paid {
            color: var(--success);
        }
        
        .bill-row-body {
            padding: 16px 20px;
            background: var(--bg-card);
            position: relative;
            z-index: 2;
        }
        
        .bill-row-body.collapsed {
            display: none;
        }
        
        .bill-row-body .bill-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 8px 16px;
            margin-bottom: 12px;
        }
        
        .bill-row-body .bill-detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .bill-row-body .bill-detail-item .label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .bill-row-body .bill-detail-item .value {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .bill-row-body .bill-detail-item .value.doctor {
            color: var(--primary);
        }
        
        /* ================================================================
           BILL ITEMS TABLE
           ================================================================ */
        .bill-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 0.82rem;
        }
        
        .bill-items-table thead th {
            text-align: left;
            padding: 6px 10px;
            font-weight: 600;
            font-size: 0.65rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
            background: var(--bg-body);
        }
        
        .bill-items-table tbody td {
            padding: 6px 10px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .bill-items-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .bill-items-table tbody tr:hover {
            background: var(--table-hover);
        }
        
        .bill-items-table .item-total {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        /* ================================================================
           BILL ROW FOOTER
           ================================================================ */
        .bill-row-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            border-top: 2px solid var(--border-color);
            background: var(--bill-footer-bg);
            border-radius: 0 0 12px 12px;
            flex-wrap: wrap;
            gap: 8px;
            position: relative;
            z-index: 2;
        }
        
        .bill-row-footer .total-summary {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .bill-row-footer .total-summary span {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-secondary);
        }
        
        .bill-row-footer .total-summary .strong {
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .bill-row-footer .total-summary .paid {
            color: var(--success);
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.72rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--success);
            color: var(--success);
        }
        
        .btn-sm {
            padding: 4px 10px;
            font-size: 0.65rem;
            border-radius: 6px;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        /* ================================================================
           TOGGLE ICON
           ================================================================ */
        .toggle-icon {
            transition: transform 0.3s ease;
        }
        .toggle-icon.expanded {
            transform: rotate(180deg);
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--bg-card);
            border-radius: 16px;
            border: 2px solid var(--border-color);
        }
        
        .empty-state i {
            font-size: 4rem;
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
        }
        
        .empty-state .sub {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        
        /* ================================================================
           MESSAGE BOX
           ================================================================ */
        .message-box {
            max-width: 1400px;
            margin: 0 auto 16px;
            padding: 12px 16px;
            border-radius: 12px;
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
        .message-box i { margin-right: 8px; }
        
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
            box-shadow: var(--shadow-lg);
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--footer-border);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            transition: border-color 0.3s ease;
        }
        .footer .footer-brand { 
            color: var(--success); 
            font-weight: 600; 
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
            .patient-profile-card { padding: 16px 18px; }
            .patient-avatar-large { width: 56px; height: 56px; font-size: 1.4rem; }
            .patient-info h2 { font-size: 1.1rem; }
            .bill-row-header { padding: 10px 14px; }
            .bill-row-body { padding: 12px 14px; }
            .bill-row-footer { flex-direction: column; align-items: stretch; gap: 10px; }
            .bill-row-footer .action-buttons { justify-content: center; }
            .summary-stats { grid-template-columns: repeat(2, 1fr); }
            .bill-items-table { font-size: 0.7rem; }
            .bill-items-table thead th,
            .bill-items-table tbody td { padding: 4px 6px; }
            .bill-row .watermark { font-size: 4rem; padding: 15px 30px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .summary-stats { grid-template-columns: 1fr; }
            .bill-details-grid { grid-template-columns: 1fr 1fr !important; }
            .bill-row-footer .total-summary { flex-direction: column; gap: 4px; }
            .bill-row-footer .action-buttons .btn { width: 100%; justify-content: center; }
            .bill-row .watermark { font-size: 2.5rem; padding: 10px 20px; transform: translate(-50%, -50%) rotate(-25deg); }
            .patient-profile-card { flex-direction: column; text-align: center; }
            .patient-info .patient-meta { justify-content: center; }
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
                <i class="fas fa-file-invoice-dollar"></i>
                Paid Bills
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;"><?= strtoupper($user_role) ?></span>
                <?php if ($is_reception): ?>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;border-color:rgba(52,211,153,0.3);">
                        <i class="fas fa-check-circle"></i> Full Access
                    </span>
                <?php endif; ?>
                <?php if ($is_admin): ?>
                    <span class="header-badge" style="background:rgba(124,58,237,0.3);border-color:rgba(124,58,237,0.3);color:#C4B5FD;">
                        <i class="fas fa-user-shield"></i> ADMIN VIEW
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                Viewing paid bills for 
                <strong><?= htmlspecialchars($patient['full_name'] ?? 'Unknown Patient') ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i>
                    <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                </span>
                
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-check-circle"></i>
                    <?= $total_bills ?> paid bill(s)
                </span>
                
                <?php if ($is_reception): ?>
                    <span class="header-badge" style="background:rgba(52,211,153,0.2);color:#34D399;border-color:rgba(52,211,153,0.2);">
                        <i class="fas fa-user-tag"></i> Reception Access
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="patients.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if (isset($message) && $message): ?>
        <div class="message-box <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PATIENT PROFILE -->
    <!-- ================================================================ -->
    <div class="patient-profile-card">
        <div class="patient-avatar-large" style="background: <?= '#' . substr(md5($patient['full_name'] ?? 'Unknown'), 0, 6) ?>;">
            <?= strtoupper(substr($patient['full_name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="patient-info">
            <h2><?= htmlspecialchars($patient['full_name'] ?? 'Unknown Patient') ?></h2>
            <p class="patient-id"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></p>
            <div class="patient-meta">
                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
                <span><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                <span><i class="fas fa-store-alt"></i> <?= htmlspecialchars($patient['branch_name'] ?? $user_branch_name) ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY STATISTICS -->
    <!-- ================================================================ -->
    <div class="summary-stats">
        <div class="summary-stat">
            <p class="stat-number"><?= $total_bills ?></p>
            <p class="stat-label">Paid Bills</p>
        </div>
        <div class="summary-stat">
            <p class="stat-number"><?= $currency ?> <?= number_format($total_paid, 0) ?></p>
            <p class="stat-label">Total Paid</p>
        </div>
        <div class="summary-stat">
            <p class="stat-number purple"><?= $currency ?> <?= number_format($total_amount, 0) ?></p>
            <p class="stat-label">Total Amount</p>
        </div>
        <div class="summary-stat">
            <p class="stat-number" style="color: var(--success);">✅ All Paid</p>
            <p class="stat-label">Status</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILLS LIST - ONLY PAID BILLS WITH WATERMARK -->
    <!-- ================================================================ -->
    <?php if (count($bills) > 0): ?>
        
        <?php foreach ($bills as $bill): 
            $items = $bill_items[$bill['id']] ?? [];
        ?>
        <div class="bill-row animate-fade-in-up">
            <!-- PAID WATERMARK -->
            <div class="watermark">✅ PAID</div>
            
            <!-- Bill Header - Click to toggle details -->
            <div class="bill-row-header" onclick="toggleBill(<?= $bill['id'] ?>)">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="bill-number">#<?= htmlspecialchars($bill['bill_number']) ?></span>
                    <span class="bill-status">
                        <i class="fas fa-check-circle"></i> Paid
                    </span>
                    <?php if ($bill['item_count'] > 0): ?>
                        <span class="text-xs" style="color:var(--text-secondary);">(<?= $bill['item_count'] ?> items)</span>
                    <?php endif; ?>
                    <?php if ($bill['visit_number']): ?>
                        <span class="text-xs font-mono" style="color:var(--text-secondary);">Visit: <?= htmlspecialchars($bill['visit_number']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-4 flex-wrap">
                    <span class="bill-amount">
                        <span class="amount-paid"><?= $currency ?> <?= number_format($bill['total_amount'] ?? 0, 0) ?></span>
                        <span class="text-xs" style="color:var(--success);margin-left:4px;">✅ Paid</span>
                    </span>
                    <i class="fas fa-chevron-down toggle-icon" id="toggleIcon_<?= $bill['id'] ?>"></i>
                </div>
            </div>
            
            <!-- Bill Body - Collapsible -->
            <div class="bill-row-body collapsed" id="billBody_<?= $bill['id'] ?>">
                <!-- Bill Details -->
                <div class="bill-details-grid">
                    <div class="bill-detail-item">
                        <span class="label">Visit Number</span>
                        <span class="value"><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></span>
                    </div>
                    <div class="bill-detail-item">
                        <span class="label">Visit Type</span>
                        <span class="value capitalize"><?= htmlspecialchars($bill['visit_type'] ?? 'N/A') ?></span>
                    </div>
                    <div class="bill-detail-item">
                        <span class="label">Visit Date</span>
                        <span class="value"><?= $bill['visit_date'] ? date('M d, Y', strtotime($bill['visit_date'])) : 'N/A' ?></span>
                    </div>
                    <div class="bill-detail-item">
                        <span class="label">Doctor</span>
                        <span class="value doctor">Dr. <?= htmlspecialchars($bill['doctor_name'] ?? 'Not assigned') ?></span>
                    </div>
                    <div class="bill-detail-item">
                        <span class="label">Created By</span>
                        <span class="value"><?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?></span>
                    </div>
                    <div class="bill-detail-item">
                        <span class="label">Paid At</span>
                        <span class="value"><?= date('M d, Y h:i A', strtotime($bill['updated_at'])) ?></span>
                    </div>
                </div>
                
                <!-- Bill Items Table -->
                <?php if (count($items) > 0): ?>
                    <table class="bill-items-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th>Type</th>
                                <th style="text-align:right;">Qty</th>
                                <th style="text-align:right;">Unit Price</th>
                                <th style="text-align:right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; foreach ($items as $item): ?>
                                <tr>
                                    <td><?= $counter++ ?></td>
                                    <td><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></td>
                                    <td><span class="text-xs capitalize" style="color:var(--text-secondary);"><?= htmlspecialchars($item['item_type'] ?? 'N/A') ?></span></td>
                                    <td style="text-align:right;"><?= $item['quantity'] ?? 1 ?></td>
                                    <td style="text-align:right;"><?= $currency ?> <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                    <td style="text-align:right;" class="item-total"><?= $currency ?> <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="text-center py-2" style="color:var(--text-secondary);font-size:0.8rem;">
                        <i class="fas fa-info-circle mr-1"></i> No items in this bill
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Bill Footer -->
            <div class="bill-row-footer">
                <div class="total-summary">
                    <span>Subtotal: <span class="strong"><?= $currency ?> <?= number_format($bill['subtotal'] ?? $bill['total_amount'] ?? 0, 0) ?></span></span>
                    <?php if (($bill['discount_amount'] ?? 0) > 0): ?>
                        <span>Discount: <span class="strong" style="color:var(--warning);">-<?= $currency ?> <?= number_format($bill['discount_amount'], 0) ?></span></span>
                    <?php endif; ?>
                    <span>Total: <span class="strong"><?= $currency ?> <?= number_format($bill['total_amount'] ?? 0, 0) ?></span></span>
                    <span>✅ <span class="paid">Fully Paid</span></span>
                </div>
                <div class="action-buttons" style="display:flex;gap:6px;flex-wrap:wrap;">
                    <a href="view_bill.php?id=<?= $bill['id'] ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <a href="print_receipt.php?bill_id=<?= $bill['id'] ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-print"></i> Receipt
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-invoice"></i>
            <h3>No Paid Bills Found</h3>
            <p>This patient has no paid bills yet</p>
            <p class="sub">Once a bill is fully paid, it will appear here with a "PAID" watermark</p>
            <a href="patients.php" class="btn btn-primary mt-4">
                <i class="fas fa-arrow-left"></i> Back to Patients
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
            Paid Bills
            <span class="text-gray-300 mx-2">|</span>
            <span class="text-gray-400">👤 <?= htmlspecialchars($user_full_name) ?></span>
            <?php if ($is_reception): ?>
                <span class="text-gray-300 mx-2">|</span>
                <span style="color:#34D399;">👀 Reception Access</span>
            <?php endif; ?>
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
<!-- JAVASCRIPT -->
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
    // TOGGLE BILL DETAILS
    // ================================================================
    function toggleBill(billId) {
        var body = document.getElementById('billBody_' + billId);
        var icon = document.getElementById('toggleIcon_' + billId);
        
        if (body) {
            if (body.classList.contains('collapsed')) {
                body.classList.remove('collapsed');
                if (icon) icon.classList.add('expanded');
            } else {
                body.classList.add('collapsed');
                if (icon) icon.classList.remove('expanded');
            }
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
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
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
        
        var clockDisplay = document.getElementById('clockDisplay');
        if (clockDisplay) {
            clockDisplay.textContent = dateStr + ' • ' + timeStr;
        }
        
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
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
    // MANUAL REFRESH
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
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
    });

    // ================================================================
    // INIT - Expand first bill by default
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var firstBillBody = document.querySelector('.bill-row-body');
        var firstIcon = document.querySelector('.toggle-icon');
        if (firstBillBody && firstIcon) {
            firstBillBody.classList.remove('collapsed');
            firstIcon.classList.add('expanded');
        }
    });

    console.log('%c💰 Braick - Paid Bills (With Watermark)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Total Paid Bills: <?= $total_bills ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Total Paid: <?= $currency ?> <?= number_format($total_paid, 0) ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Reception access: <?= $is_reception ? 'YES' : 'NO' ?>', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Each bill has a "PAID" watermark with slash/strikethrough effect', 'font-size:12px; color:#34D399;');
    console.log('%c🌓 Dark mode synced with header via localStorage', 'font-size:13px; color:#8B5CF6;');
</script>

</body>
</html>