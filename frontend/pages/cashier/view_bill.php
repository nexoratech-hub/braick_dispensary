<?php
// ================================================================
// FILE: frontend/pages/cashier/view_bill.php
// CASHIER - VIEW BILL DETAILS
// WITH AUTO-UPDATE (3 SECONDS) - NO REFRESH NEEDED
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Cashier Dodoma
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    $_SESSION['user_id'] = 10;
    $_SESSION['full_name'] = 'Cashier Dodoma';
    $_SESSION['role'] = 'cashier';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'cashier.dodoma';
    $_SESSION['is_admin'] = false;
}

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_full_name = $_SESSION['full_name'] ?? 'Cashier';
$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$unread_notifications = 0;
$message = '';
$message_type = '';

if ($bill_id <= 0) {
    header('Location: pending_bills.php');
    exit;
}

try {
    $db = getDB();
    $today = date('Y-m-d');
    
    // ================================================================
    // GET UNREAD NOTIFICATIONS
    // ================================================================
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_notifications = $stmt->fetch()['total'] ?? 0;
    }
    
    // ================================================================
    // GET BILL DETAILS
    // ================================================================
    $stmt = $db->prepare("
        SELECT pb.*, p.full_name as patient_name, p.patient_id, p.phone, p.email, p.address,
               v.visit_number, v.visit_type, v.created_at as visit_date, v.status as visit_status,
               u.full_name as doctor_name, u.specialty,
               (SELECT COUNT(*) FROM payments WHERE bill_id = pb.id) as payment_count,
               (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE bill_id = pb.id) as total_paid
        FROM patient_bills pb
        JOIN patients p ON pb.patient_id = p.id
        LEFT JOIN visits v ON pb.visit_id = v.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE pb.id = ? AND pb.branch_id = ?
    ");
    $stmt->execute([$bill_id, $user_branch_id]);
    $bill = $stmt->fetch();
    
    if (!$bill) {
        header('Location: pending_bills.php');
        exit;
    }
    
    // ================================================================
    // GET BILL ITEMS
    // ================================================================
    $stmt = $db->prepare("
        SELECT * FROM bill_items 
        WHERE bill_id = ?
        ORDER BY created_at
    ");
    $stmt->execute([$bill_id]);
    $bill_items = $stmt->fetchAll();
    
    // ================================================================
    // GET PAYMENT HISTORY
    // ================================================================
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as cashier_name
        FROM payments p
        LEFT JOIN users u ON p.received_by = u.id
        WHERE p.bill_id = ?
        ORDER BY p.received_at DESC
    ");
    $stmt->execute([$bill_id]);
    $payments = $stmt->fetchAll();
    
    // ================================================================
    // GET STATS FOR INITIAL LOAD
    // ================================================================
    
    // Pending Bills Count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM patient_bills 
        WHERE branch_id = ? AND status IN ('pending', 'partial')
    ");
    $stmt->execute([$user_branch_id]);
    $pending_bills = $stmt->fetch()['count'] ?? 0;
    
    // Today's Revenue
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM patient_bills 
        WHERE branch_id = ? AND DATE(created_at) = ? AND status = 'paid'
    ");
    $stmt->execute([$user_branch_id, $today]);
    $today_revenue = $stmt->fetch()['total'] ?? 0;
    
    // Today's Payments
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM payments 
        WHERE branch_id = ? AND DATE(received_at) = ?
    ");
    $stmt->execute([$user_branch_id, $today]);
    $today_payments = $stmt->fetch()['count'] ?? 0;
    
    // ================================================================
    // HANDLE PAYMENT PROCESSING
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
        $amount = (float)($_POST['amount'] ?? 0);
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $reference_number = trim($_POST['reference_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        $errors = [];
        if ($amount <= 0) $errors[] = 'Amount must be greater than 0';
        if ($amount > ($bill['balance'] ?? 0)) $errors[] = 'Amount cannot exceed balance';
        
        if (empty($errors)) {
            $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("
                INSERT INTO payments (
                    receipt_number, bill_id, patient_id, amount, 
                    payment_method, reference_number, notes, received_by, branch_id, received_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([
                $receipt_number,
                $bill_id,
                $bill['patient_id'],
                $amount,
                $payment_method,
                $reference_number,
                $notes,
                $_SESSION['user_id'],
                $user_branch_id
            ])) {
                // Update bill status
                $remaining_balance = $bill['balance'] - $amount;
                
                if ($remaining_balance <= 0) {
                    $new_status = 'paid';
                    $remaining_balance = 0;
                } else {
                    $new_status = 'partial';
                }
                
                $stmt = $db->prepare("
                    UPDATE patient_bills 
                    SET balance = ?, status = ?, paid_amount = paid_amount + ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$remaining_balance, $new_status, $amount, $bill_id]);
                
                // Log activity
                try {
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (user_id, action, details, created_at) 
                        VALUES (?, 'payment_processed', ?, NOW())
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        "Payment of TSh " . number_format($amount) . " processed for bill #" . $bill['bill_number']
                    ]);
                } catch (Exception $e) {}
                
                $message = "✅ Payment processed successfully!";
                $message .= "<br>💰 Amount: <strong>TSh " . number_format($amount) . "</strong>";
                $message .= "<br>📋 Receipt: <strong>$receipt_number</strong>";
                $message .= "<br>📊 Remaining Balance: <strong>TSh " . number_format($remaining_balance) . "</strong>";
                $message_type = 'success';
                
                // Refresh bill data
                $stmt = $db->prepare("
                    SELECT pb.*, p.full_name as patient_name, p.patient_id, p.phone, p.email, p.address,
                           v.visit_number, v.visit_type, v.created_at as visit_date, v.status as visit_status,
                           u.full_name as doctor_name, u.specialty,
                           (SELECT COUNT(*) FROM payments WHERE bill_id = pb.id) as payment_count,
                           (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE bill_id = pb.id) as total_paid
                    FROM patient_bills pb
                    JOIN patients p ON pb.patient_id = p.id
                    LEFT JOIN visits v ON pb.visit_id = v.id
                    LEFT JOIN users u ON v.doctor_id = u.id
                    WHERE pb.id = ? AND pb.branch_id = ?
                ");
                $stmt->execute([$bill_id, $user_branch_id]);
                $bill = $stmt->fetch();
                
                // Refresh payments
                $stmt = $db->prepare("
                    SELECT p.*, u.full_name as cashier_name
                    FROM payments p
                    LEFT JOIN users u ON p.received_by = u.id
                    WHERE p.bill_id = ?
                    ORDER BY p.received_at DESC
                ");
                $stmt->execute([$bill_id]);
                $payments = $stmt->fetchAll();
                
                echo '<script>
                    setTimeout(function(){ 
                        window.location.href = "view_bill.php?id=' . $bill_id . '&success=1"; 
                    }, 2000);
                </script>';
            } else {
                $message = "❌ Failed to process payment!";
                $message_type = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
} catch (Exception $e) {
    $bill = null;
    $bill_items = [];
    $payments = [];
    $pending_bills = 0;
    $today_revenue = 0;
    $today_payments = 0;
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
}

// ================================================================
// INCLUDE CASHIER HEADER & SIDEBAR
// ================================================================
include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bill - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
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
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
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
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
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
            border-radius: 10px;
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
        
        .page-header .header-badge .count {
            color: #34D399;
            font-weight: 700;
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
        
        .update-badge-light {
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.8);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        /* ================================================================
           BILL DETAILS
           ================================================================ */
        .detail-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }
        
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
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
        
        .status-badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 16px;
            border-radius: 20px;
        }
        
        .status-badge.pending { background: #FEF3C7; color: #D97706; }
        .status-badge.partial { background: #E8F0FE; color: #0B5ED7; }
        .status-badge.paid { background: #D1FAE5; color: #059669; }
        .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
        
        [data-theme="dark"] .status-badge.pending { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge.partial { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .status-badge.paid { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge.cancelled { background: #3A1A1A; color: #F87171; }
        
        /* ================================================================
           TABLE STYLES
           ================================================================ */
        .table-wrap {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            min-width: 600px;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
        }
        
        .data-table tbody td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--bg-body);
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
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
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-danger:hover {
            background: var(--danger-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 18px 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-title .title-blue { color: var(--primary); }
        .card-title .title-green { color: var(--success); }
        
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
        
        [data-theme="dark"] .role-badge-display {
            background: #1E3A5F;
            color: #6EA8FE;
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
        
        [data-theme="dark"] .branch-badge-display {
            background: #1A3A2A;
            color: #34D399;
        }
        
        /* ================================================================
           FORM
           ================================================================ */
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.08);
        }
        
        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }
        
        .form-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            display: block;
        }
        
        .form-label .required {
            color: var(--danger);
            margin-left: 2px;
        }
        
        /* ================================================================
           SCROLL CONTAINER
           ================================================================ */
        .scroll-container {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .scroll-container::-webkit-scrollbar {
            width: 4px;
        }
        
        .scroll-container::-webkit-scrollbar-track {
            background: var(--bg-body);
            border-radius: 4px;
        }
        
        .scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
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
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .detail-card { padding: 14px 16px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .detail-card { padding: 12px 14px; }
            .data-table { font-size: 0.7rem; min-width: 500px; }
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
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search patients...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if ($bill): ?>
    
    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-receipt"></i>
                Bill Details
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">CASHIER</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-file-invoice"></i>
                View and manage bill #<strong><?= htmlspecialchars($bill['bill_number']) ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($bill['patient_name']) ?>
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-money-bill"></i>
                    TSh <span id="billTotal"><?= number_format($bill['total_amount'] ?? 0) ?></span>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="pending_bills.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Pending Bills
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert <?= $message_type === 'success' ? 'alert-success' : 'alert-error' ?>" style="max-width:1000px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div class="alert-content"><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- BILL SUMMARY -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5" style="max-width:1000px;margin:0 auto;">
        
        <div class="detail-card">
            <p class="detail-label">Bill Number</p>
            <p class="detail-value">#<?= htmlspecialchars($bill['bill_number']) ?></p>
        </div>
        
        <div class="detail-card">
            <p class="detail-label">Status</p>
            <p class="detail-value">
                <span class="status-badge <?= $bill['status'] ?? 'pending' ?>">
                    <?= ucfirst($bill['status'] ?? 'Pending') ?>
                </span>
            </p>
        </div>
        
        <div class="detail-card">
            <p class="detail-label">Created At</p>
            <p class="detail-value"><?= date('F d, Y h:i A', strtotime($bill['created_at'])) ?></p>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT & VISIT INFO -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5" style="max-width:1000px;margin:0 auto;">
        
        <div class="detail-card">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-3">
                <i class="fas fa-user text-primary mr-2"></i> Patient Information
            </h3>
            <div class="space-y-2">
                <div>
                    <p class="detail-label">Name</p>
                    <p class="detail-value"><?= htmlspecialchars($bill['patient_name']) ?></p>
                </div>
                <div>
                    <p class="detail-label">Patient ID</p>
                    <p class="detail-value"><?= htmlspecialchars($bill['patient_id'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Phone</p>
                    <p class="detail-value"><?= htmlspecialchars($bill['phone'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Email</p>
                    <p class="detail-value"><?= htmlspecialchars($bill['email'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Address</p>
                    <p class="detail-value"><?= htmlspecialchars($bill['address'] ?? 'N/A') ?></p>
                </div>
            </div>
        </div>
        
        <div class="detail-card">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-3">
                <i class="fas fa-clinic-medical text-primary mr-2"></i> Visit Information
            </h3>
            <div class="space-y-2">
                <div>
                    <p class="detail-label">Visit Number</p>
                    <p class="detail-value"><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Visit Type</p>
                    <p class="detail-value capitalize"><?= htmlspecialchars($bill['visit_type'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Doctor</p>
                    <p class="detail-value">Dr. <?= htmlspecialchars($bill['doctor_name'] ?? 'Not Assigned') ?></p>
                </div>
                <div>
                    <p class="detail-label">Visit Date</p>
                    <p class="detail-value"><?= isset($bill['visit_date']) ? date('F d, Y h:i A', strtotime($bill['visit_date'])) : 'N/A' ?></p>
                </div>
                <div>
                    <p class="detail-label">Visit Status</p>
                    <p class="detail-value capitalize"><?= htmlspecialchars($bill['visit_status'] ?? 'N/A') ?></p>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- BILL ITEMS -->
    <!-- ================================================================ -->
    <div class="card mb-5" style="max-width:1000px;margin:0 auto 20px;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Bill Items
                <span class="text-sm font-normal text-gray-400">(<?= count($bill_items) ?> items)</span>
            </h3>
        </div>
        
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius:8px 0 0 0;">#</th>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th style="border-radius:0 8px 0 0;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($bill_items) > 0): ?>
                        <?php $i = 1; foreach ($bill_items as $item): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($item['item_name']) ?></td>
                                <td><span class="text-xs capitalize"><?= htmlspecialchars($item['item_type'] ?? 'N/A') ?></span></td>
                                <td><?= $item['quantity'] ?? 1 ?></td>
                                <td>TSh <?= number_format($item['unit_price'] ?? 0) ?></td>
                                <td class="font-semibold">TSh <?= number_format($item['total_price'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-gray-400">No items found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PAYMENT SUMMARY -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5" style="max-width:1000px;margin:0 auto;">
        
        <div class="detail-card" style="border-left: 4px solid #0B5ED7;">
            <p class="detail-label">Total Amount</p>
            <p class="detail-value" style="font-size:1.2rem;color:#0B5ED7;">
                TSh <?= number_format($bill['total_amount'] ?? 0) ?>
            </p>
        </div>
        
        <div class="detail-card" style="border-left: 4px solid #059669;">
            <p class="detail-label">Paid Amount</p>
            <p class="detail-value" style="font-size:1.2rem;color:#059669;">
                TSh <?= number_format($bill['total_paid'] ?? 0) ?>
            </p>
        </div>
        
        <div class="detail-card" style="border-left: 4px solid <?= ($bill['balance'] ?? 0) > 0 ? '#DC2626' : '#059669' ?>;">
            <p class="detail-label">Balance</p>
            <p class="detail-value" style="font-size:1.2rem;color:<?= ($bill['balance'] ?? 0) > 0 ? '#DC2626' : '#059669' ?>;">
                TSh <?= number_format($bill['balance'] ?? 0) ?>
            </p>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- PAYMENT HISTORY -->
    <!-- ================================================================ -->
    <div class="card mb-5" style="max-width:1000px;margin:0 auto 20px;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-history title-blue mr-2"></i> Payment History
                <span class="text-sm font-normal text-gray-400">(<?= count($payments) ?> payments)</span>
            </h3>
        </div>
        
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius:8px 0 0 0;">Receipt #</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Cashier</th>
                        <th style="border-radius:0 8px 0 0;">Date</th>
                    </tr>
                </thead>
                <tbody id="paymentHistoryList">
                    <?php if (count($payments) > 0): ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($payment['receipt_number']) ?></td>
                                <td class="font-semibold text-green-600">TSh <?= number_format($payment['amount'] ?? 0) ?></td>
                                <td>
                                    <span class="text-sm capitalize">
                                        <?php 
                                            $method = $payment['payment_method'] ?? 'cash';
                                            $methodIcon = $method === 'cash' ? '💵' : ($method === 'm-pesa' ? '📱' : '💳');
                                            echo $methodIcon . ' ' . strtoupper($method);
                                        ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($payment['reference_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($payment['cashier_name'] ?? 'N/A') ?></td>
                                <td class="text-sm text-gray-500"><?= date('M d, Y h:i A', strtotime($payment['received_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-gray-400">No payments recorded</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PROCESS PAYMENT FORM -->
    <!-- ================================================================ -->
    <?php if (($bill['status'] ?? '') !== 'paid' && ($bill['balance'] ?? 0) > 0): ?>
    <div class="card" style="max-width:1000px;margin:0 auto;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-money-bill-wave title-green mr-2"></i> Process Payment
                <span class="text-sm font-normal text-gray-400">Balance: TSh <?= number_format($bill['balance'] ?? 0) ?></span>
            </h3>
        </div>
        
        <form method="POST" action="" id="paymentForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <div>
                    <label class="form-label">Amount <span class="required">*</span></label>
                    <input type="number" name="amount" class="form-control" 
                           placeholder="Enter amount" 
                           value="<?= $bill['balance'] ?? 0 ?>" 
                           min="1" max="<?= $bill['balance'] ?? 0 ?>" required>
                </div>
                
                <div>
                    <label class="form-label">Payment Method <span class="required">*</span></label>
                    <select name="payment_method" class="form-control" required>
                        <option value="cash">💵 Cash</option>
                        <option value="m-pesa">📱 M-Pesa</option>
                        <option value="airtel_money">📱 Airtel Money</option>
                        <option value="tigo_pesa">📱 Tigo Pesa</option>
                        <option value="halopesa">📱 Halopesa</option>
                        <option value="card">💳 Card</option>
                        <option value="bank">🏦 Bank Transfer</option>
                        <option value="insurance">🏥 Insurance</option>
                        <option value="other">📦 Other</option>
                    </select>
                </div>
                
                <div>
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference_number" class="form-control" 
                           placeholder="e.g. Transaction ID">
                </div>
                
                <div>
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" placeholder="Additional notes..." rows="1"></textarea>
                </div>
                
            </div>
            
            <div class="mt-4 flex gap-3 flex-wrap">
                <button type="submit" name="process_payment" class="btn btn-success" id="processPaymentBtn">
                    <i class="fas fa-check"></i> Process Payment
                </button>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="pending_bills.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php else: ?>
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-receipt text-4xl block mb-3"></i>
            <p class="text-lg">Bill not found</p>
            <a href="pending_bills.php" class="text-primary hover:underline">Back to pending bills</a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            View Bill
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">● Live</span>
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
<!-- CASHIER GLOBAL STATS AUTO-UPDATE -->
<!-- ================================================================ -->
<script src="/dispensary_system/frontend/assets/js/cashier_global_stats.js"></script>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
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
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
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
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

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
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
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
    // MONITOR CASHIER STATS
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var checkCashierStats = setInterval(function() {
            if (window.CashierStats) {
                console.log('%c💰 Cashier Stats System Connected', 'font-size:14px; font-weight:bold; color:#34D399;');
                console.log('%c🔄 Auto-update every ' + window.CashierStats.config.updateInterval / 1000 + ' seconds', 'font-size:12px; color:#64748B;');
                clearInterval(checkCashierStats);
            }
        }, 500);
    });

    console.log('%c💰 Braick - View Bill (Cashier)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Bill: <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👤 Patient: <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Total: TSh <?= number_format($bill['total_amount'] ?? 0) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💳 Balance: TSh <?= number_format($bill['balance'] ?? 0) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c🔄 Auto-update every 3 seconds via cashier_global_stats.js', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>