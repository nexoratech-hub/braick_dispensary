<?php
// ================================================================
// FILE: frontend/pages/admin/cancel_bill.php
// ADMIN - CANCEL BILL
// BRAICK DISPENSARY - FIXED FOR EXISTING DATABASE
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
// CHECK ADMIN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

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
// GET PARAMETERS
// ================================================================
$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($bill_id <= 0) {
    header('Location: bills.php?branch=' . $selected_branch_id);
    exit;
}

$message = '';
$message_type = '';
$cancellation_reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
$confirm_cancel = isset($_POST['confirm_cancel']) ? (bool)$_POST['confirm_cancel'] : false;

// ================================================================
// FETCH BILL DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        b.*,
        p.full_name as patient_name,
        p.patient_id as patient_number,
        p.phone as patient_phone,
        v.visit_number,
        v.visit_type,
        v.status as visit_status,
        u.full_name as created_by_name,
        br.name as branch_name
    FROM bills b
    LEFT JOIN patients p ON b.patient_id = p.id
    LEFT JOIN visits v ON b.visit_id = v.id
    LEFT JOIN users u ON b.created_by = u.id
    LEFT JOIN branches br ON b.branch_id = br.id
    WHERE b.id = ?
");
$stmt->execute([$bill_id]);
$bill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bill) {
    header('Location: bills.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// FETCH BILL ITEMS
// ================================================================
$stmt = $db->prepare("
    SELECT * FROM bill_items 
    WHERE bill_id = ? AND status != 'cancelled'
    ORDER BY created_at DESC
");
$stmt->execute([$bill_id]);
$bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// FETCH PAYMENTS
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, u.full_name as received_by_name
    FROM payments p
    LEFT JOIN users u ON p.received_by = u.id
    WHERE p.bill_id = ?
    ORDER BY p.received_at DESC
");
$stmt->execute([$bill_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_paid = 0;
foreach ($payments as $payment) {
    $total_paid += $payment['amount'];
}

// ================================================================
// FETCH PRESCRIPTIONS (via visit)
// ================================================================
$prescriptions = [];
if (!empty($bill['visit_id'])) {
    $stmt = $db->prepare("
        SELECT pr.*, u.full_name as doctor_name
        FROM prescriptions pr
        LEFT JOIN users u ON pr.doctor_id = u.id
        WHERE pr.visit_id = ? AND pr.status != 'cancelled'
    ");
    $stmt->execute([$bill['visit_id']]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// FETCH STOCK MOVEMENTS (via reference_id and reference_type)
// ================================================================
$stock_movements = [];
$stmt = $db->prepare("
    SELECT sm.*, mi.id as inventory_id
    FROM stock_movements sm
    LEFT JOIN medications_inventory mi ON sm.inventory_id = mi.id
    WHERE sm.reference_id = ? AND sm.reference_type = 'prescription'
");
$stmt->execute([$bill_id]);
$stock_movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CHECK IF BILL CAN BE CANCELLED
// ================================================================
$can_cancel = false;
$cancel_message = '';

if ($bill['status'] === 'cancelled') {
    $can_cancel = false;
    $cancel_message = 'This bill has already been cancelled.';
} elseif ($bill['status'] === 'paid') {
    $can_cancel = true;
    $cancel_message = '⚠️ This bill has been paid. Cancelling will reverse payments.';
} elseif ($bill['status'] === 'partial') {
    $can_cancel = true;
    $cancel_message = '⚠️ This bill has partial payments. Cancelling will reverse all payments.';
} elseif ($bill['status'] === 'pending') {
    $can_cancel = true;
    $cancel_message = 'This bill is pending and can be cancelled.';
} else {
    $can_cancel = true;
}

// ================================================================
// HANDLE CANCELLATION
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $confirm_cancel) {
    try {
        $db->beginTransaction();
        
        if ($bill['status'] === 'cancelled') {
            throw new Exception('Bill is already cancelled.');
        }
        
        $branch_id = $bill['branch_id'] ?? $user_branch_id;
        $cancellation_note = "Cancelled by {$user_full_name}. Reason: {$cancellation_reason}";
        
        // 1. Reverse payments if any
        if (!empty($payments)) {
            // Note: No status column in payments table, so we just log the reversal
            foreach ($payments as $payment) {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, patient_id, action, details, created_at)
                    VALUES (?, ?, ?, 'payment_reversed', ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    $branch_id,
                    $bill['patient_id'],
                    "Payment #{$payment['receipt_number']} of TSh " . number_format($payment['amount'], 0) . " reversed due to bill cancellation. Reason: {$cancellation_reason}"
                ]);
            }
        }
        
        // 2. Reverse stock movements for medications
        $stmt = $db->prepare("
            SELECT sm.*, mi.id as inventory_id
            FROM stock_movements sm
            LEFT JOIN medications_inventory mi ON sm.inventory_id = mi.id
            WHERE sm.reference_id = ? AND sm.reference_type = 'prescription' AND sm.movement_type = 'out'
        ");
        $stmt->execute([$bill_id]);
        $stock_movements_to_reverse = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($stock_movements_to_reverse as $movement) {
            if ($movement['inventory_id']) {
                // Get current stock
                $stmt = $db->prepare("SELECT quantity FROM medications_inventory WHERE id = ?");
                $stmt->execute([$movement['inventory_id']]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);
                $current_qty = $current['quantity'] ?? 0;
                
                // Restore stock
                $stmt = $db->prepare("
                    UPDATE medications_inventory 
                    SET quantity = quantity + ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$movement['quantity'], $movement['inventory_id']]);
                
                // Log reversal
                $stmt = $db->prepare("
                    INSERT INTO stock_movements (
                        inventory_id, patient_id, movement_type, quantity, 
                        previous_stock, new_stock, reference_type, reference_id,
                        performed_by, branch_id, notes, created_at
                    ) VALUES (?, ?, 'in', ?, ?, ?, 'adjustment', ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $movement['inventory_id'],
                    $bill['patient_id'],
                    $movement['quantity'],
                    $current_qty,
                    $current_qty + $movement['quantity'],
                    $bill_id,
                    $user_id,
                    $branch_id,
                    "Stock reversal due to bill cancellation - Original: {$movement['notes']}"
                ]);
            }
        }
        
        // 3. Cancel prescriptions
        if (!empty($bill['visit_id'])) {
            $stmt = $db->prepare("
                UPDATE prescriptions 
                SET status = 'cancelled',
                    notes = CONCAT(IFNULL(notes, ''), '\n', ?)
                WHERE visit_id = ? AND status != 'cancelled'
            ");
            $stmt->execute([$cancellation_note, $bill['visit_id']]);
            
            // Log prescription cancellations
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, patient_id, action, details, created_at)
                SELECT ?, ?, patient_id, 'prescription_cancelled', ?, NOW()
                FROM prescriptions
                WHERE visit_id = ? AND status = 'cancelled'
            ");
            $stmt->execute([
                $user_id,
                $branch_id,
                "Prescriptions cancelled due to bill cancellation. Reason: {$cancellation_reason}",
                $bill['visit_id']
            ]);
        }
        
        // 4. Cancel bill items
        $stmt = $db->prepare("
            UPDATE bill_items 
            SET status = 'cancelled'
            WHERE bill_id = ? AND status != 'cancelled'
        ");
        $stmt->execute([$bill_id]);
        
        // 5. Update bill status
        $stmt = $db->prepare("
            UPDATE bills 
            SET status = 'cancelled',
                notes = CONCAT(IFNULL(notes, ''), '\n', ?),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$cancellation_note, $bill_id]);
        
        // 6. Update visit if linked
        if ($bill['visit_id']) {
            $stmt = $db->prepare("
                UPDATE visits 
                SET payment_status = 'cancelled',
                    notes = CONCAT(IFNULL(notes, ''), '\n', ?),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$cancellation_note, $bill['visit_id']]);
        }
        
        // 7. Log activity
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, branch_id, patient_id, action, details, created_at)
            VALUES (?, ?, ?, 'bill_cancelled', ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $branch_id,
            $bill['patient_id'],
            "Bill #{$bill['bill_number']} (TSh " . number_format($bill['total_amount'], 0) . ") cancelled. Reason: {$cancellation_reason}"
        ]);
        
        $db->commit();
        
        $message = "✅ Bill #{$bill['bill_number']} has been cancelled successfully!";
        $message .= "<br>📋 Reason: " . htmlspecialchars($cancellation_reason);
        if (!empty($payments)) {
            $message .= "<br>💰 Payments reversed: TSh " . number_format($total_paid, 0);
        }
        $message_type = 'success';
        
        // Refresh bill data
        $stmt = $db->prepare("SELECT * FROM bills WHERE id = ?");
        $stmt->execute([$bill_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Redirect after success
        echo '<script>
            setTimeout(function(){ 
                window.location.href = "bills.php?branch=' . $selected_branch_id . '&success=1"; 
            }, 3000);
        </script>';
        
    } catch (Exception $e) {
        $db->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

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
// INCLUDE HEADERS
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Bill - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563EB;
            --primary-dark: #1D4ED8;
            --primary-light: #60A5FA;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #2563EB, #1D4ED8);
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-bg: #1E3A5F;
            --purple-bg: #2D1B5F;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
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
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
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
        
        .branch-badge-display {
            background: var(--primary-bg);
            color: var(--primary);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--primary-light);
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
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
            box-shadow: 0 8px 32px rgba(37, 99, 235, 0.25);
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
        
        .page-header .page-title i { font-size: 2rem; opacity: 0.9; }
        
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
        
        .status-badge {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .status-badge.pending { background: var(--warning-bg); color: var(--warning); }
        .status-badge.paid { background: var(--success-bg); color: var(--success); }
        .status-badge.partial { background: var(--warning-bg); color: var(--warning); }
        .status-badge.cancelled { background: var(--danger-bg); color: var(--danger); }
        
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
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
        
        .modern-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            margin-bottom: 24px;
        }
        
        .modern-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .modern-card .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modern-card .card-title i { color: var(--primary); }
        .modern-card .card-badge {
            background: var(--primary-bg);
            color: var(--primary);
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        .modern-card .card-badge.success {
            background: var(--success-bg);
            color: var(--success);
        }
        .modern-card .card-badge.danger {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        .alert-modern {
            padding: 14px 18px;
            border-radius: var(--radius);
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .alert-modern-success {
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid var(--success);
        }
        .alert-modern-error {
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid var(--danger);
        }
        .alert-modern-warning {
            background: var(--warning-bg);
            color: var(--warning);
            border: 1px solid var(--warning);
        }
        .alert-modern i { font-size: 1.1rem; margin-top: 2px; }
        
        .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
            display: block;
        }
        .form-label .required { color: var(--danger); margin-left: 2px; }
        .form-label .label-icon { margin-right: 4px; color: var(--primary); }
        
        .form-control-modern {
            width: 100%;
            padding: 10px 16px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.85rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        .form-control-modern:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
        }
        textarea.form-control-modern { resize: vertical; min-height: 80px; }
        
        .btn-modern {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        .btn-modern-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        .btn-modern-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(37, 99, 235, 0.35);
        }
        .btn-modern-danger {
            background: var(--danger);
            color: white;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
        }
        .btn-modern-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(220, 38, 38, 0.35);
        }
        .btn-modern-danger:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .btn-modern-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-modern-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .form-actions-modern {
            display: flex;
            gap: 12px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .table-modern {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .table-modern thead th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 500;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
        }
        .table-modern tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
        }
        .table-modern tbody tr:hover { background: var(--primary-bg); }
        
        .toast-modern {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: var(--radius);
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
        .toast-modern.show { transform: translateY(0); opacity: 1; }
        .toast-modern.success { background: var(--success); }
        .toast-modern.error { background: var(--danger); }
        .toast-modern.info { background: var(--primary); }
        .toast-modern.warning { background: var(--warning); }
        
        .footer-modern {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer-modern .footer-brand { color: var(--primary); font-weight: 500; }
        
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
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .grid { display: grid; }
        .grid-cols-1 { grid-template-columns: 1fr; }
        .grid-cols-2 { grid-template-columns: 1fr 1fr; }
        .gap-4 { gap: 16px; }
        .gap-5 { gap: 20px; }
        .mt-5 { margin-top: 20px; }
        .mb-5 { margin-bottom: 20px; }
        .col-span-2 { grid-column: span 2; }
        .text-center { text-align: center; }
        .text-danger { color: var(--danger); }
        .text-success { color: var(--success); }
        .text-warning { color: var(--warning); }
        .text-primary { color: var(--primary); }
        .text-muted { color: var(--text-secondary); }
        .text-lg { font-size: 1.1rem; }
        .text-xl { font-size: 1.25rem; }
        .font-semibold { font-weight: 600; }
        .space-y-4 > * + * { margin-top: 16px; }
        .py-6 { padding-top: 24px; padding-bottom: 24px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        
        .lg\:col-span-2 { grid-column: span 2; }
        .lg\:grid-cols-3 { grid-template-columns: 1fr 1fr 1fr; }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
        }
        @media (max-width: 768px) {
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .grid-cols-2 { grid-template-columns: 1fr; }
            .lg\:grid-cols-3 { grid-template-columns: 1fr; }
            .form-actions-modern { flex-direction: column; }
            .form-actions-modern .btn-modern { width: 100%; justify-content: center; }
            .detail-card { padding: 16px; }
            .modern-card { padding: 16px; }
        }
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
        
        <div class="search-wrapper" style="display:flex;align-items:center;background:var(--bg-body);border-radius:var(--radius);border:2px solid var(--border-color);flex:1;max-width:500px;">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search..." style="border:none;background:transparent;padding:8px 14px;width:100%;font-size:0.85rem;outline:none;color:var(--text-primary);">
            <button id="searchBtn" class="search-btn" style="background:var(--primary-gradient);color:white;border:none;padding:8px 16px;border-radius:0 var(--radius) var(--radius) 0;cursor:pointer;font-size:0.85rem;transition:all 0.3s;">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime">
            <i class="fas fa-clock" style="color:var(--primary-light);"></i>
            <span id="clockDisplay" style="font-weight:500;"><?= date('d M Y • h:i:s A') ?></span>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-ban"></i>
                Cancel Bill
                <span class="role-badge-display">ADMIN</span>
                <?php if ($bill['status'] === 'cancelled'): ?>
                    <span style="background:rgba(220,38,38,0.3);color:#F87171;padding:3px 12px;border-radius:20px;font-size:0.6rem;display:inline-flex;align-items:center;gap:4px;backdrop-filter:blur(4px);">
                        <i class="fas fa-check-circle"></i> Cancelled
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-file-invoice"></i>
                Bill #<strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong>
                <span class="header-badge" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-tag"></i>
                    <span class="status-badge <?= $bill['status'] ?? 'pending' ?>">
                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                    </span>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-money-bill-wave"></i>
                    TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="bill_details.php?id=<?= $bill_id ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-eye"></i> View Bill
            </a>
            <a href="bills.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert-modern alert-modern-<?= $message_type === 'success' ? 'success' : ($message_type === 'warning' ? 'warning' : 'error') ?>" style="max-width:1100px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <?php if ($bill['status'] !== 'cancelled'): ?>
    <!-- Cancellation Warning -->
    <div class="alert-modern alert-modern-warning" style="max-width:1100px;margin:0 auto 16px;">
        <i class="fas fa-exclamation-triangle" style="font-size:1.2rem;"></i>
        <div>
            <strong>⚠️ Warning: Cancelling this bill will:</strong>
            <ul style="margin-top:6px;padding-left:20px;list-style-type:disc;">
                <li>Reverse all payments made against this bill</li>
                <li>Restore medication stock quantities</li>
                <li>Cancel associated prescriptions</li>
                <li>Mark all bill items as cancelled</li>
                <li>Update the visit payment status</li>
            </ul>
            <?php if (!empty($payments)): ?>
                <div style="margin-top:8px;padding:8px 12px;background:var(--danger-bg);border-radius:var(--radius);color:var(--danger);">
                    <i class="fas fa-coins"></i> 
                    <strong><?= count($payments) ?> payment(s) totaling TSh <?= number_format($total_paid, 0) ?> will be reversed.</strong>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- BILL DETAILS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        <div class="detail-card lg:col-span-2 animate-fade-in-up">
            <h3 class="text-lg font-semibold mb-4">
                <i class="fas fa-file-invoice text-primary mr-2"></i> Bill Information
            </h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="detail-label">Bill Number</p>
                    <p class="detail-value"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Status</p>
                    <p class="detail-value">
                        <span class="status-badge <?= $bill['status'] ?? 'pending' ?>">
                            <?= ucfirst($bill['status'] ?? 'Pending') ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Patient</p>
                    <p class="detail-value">
                        <a href="view_patient.php?id=<?= $bill['patient_id'] ?>" class="text-primary hover:underline">
                            <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>
                        </a>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Patient ID</p>
                    <p class="detail-value"><?= htmlspecialchars($bill['patient_number'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Branch</p>
                    <p class="detail-value"><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Created By</p>
                    <p class="detail-value"><?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Created At</p>
                    <p class="detail-value"><?= date('F d, Y h:i A', strtotime($bill['created_at'] ?? 'now')) ?></p>
                </div>
                <div>
                    <p class="detail-label">Total Amount</p>
                    <p class="detail-value" style="font-size:1.2rem;color:var(--primary);">
                        TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?>
                    </p>
                </div>
                <?php if (!empty($bill['visit_number'])): ?>
                <div class="col-span-2">
                    <p class="detail-label">Visit Number</p>
                    <p class="detail-value">
                        <a href="view_visit.php?id=<?= $bill['visit_id'] ?>" class="text-primary hover:underline">
                            <?= htmlspecialchars($bill['visit_number']) ?>
                        </a>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="detail-card animate-fade-in-up" style="animation-delay:0.05s;">
            <h3 class="text-lg font-semibold mb-4">
                <i class="fas fa-chart-pie text-primary mr-2"></i> Summary
            </h3>
            <div class="space-y-4">
                <div style="padding:12px;background:var(--bg-body);border-radius:var(--radius);">
                    <p class="detail-label">Total Bill Amount</p>
                    <p class="detail-value" style="font-size:1.4rem;color:var(--primary);">
                        TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?>
                    </p>
                </div>
                <div style="padding:12px;background:var(--success-bg);border-radius:var(--radius);">
                    <p class="detail-label">Paid Amount</p>
                    <p class="detail-value" style="font-size:1.2rem;color:var(--success);">
                        TSh <?= number_format($total_paid, 0) ?>
                    </p>
                </div>
                <div style="padding:12px;background:var(--danger-bg);border-radius:var(--radius);">
                    <p class="detail-label">Balance</p>
                    <p class="detail-value" style="font-size:1.2rem;color:var(--danger);">
                        TSh <?= number_format(($bill['total_amount'] ?? 0) - $total_paid, 0) ?>
                    </p>
                </div>
                <div style="padding:12px;background:var(--primary-bg);border-radius:var(--radius);">
                    <p class="detail-label">Bill Items</p>
                    <p class="detail-value"><?= count($bill_items) ?> item(s)</p>
                </div>
                <div style="padding:12px;background:var(--warning-bg);border-radius:var(--radius);">
                    <p class="detail-label">Payments</p>
                    <p class="detail-value"><?= count($payments) ?> payment(s)</p>
                </div>
                <?php if (!empty($prescriptions)): ?>
                <div style="padding:12px;background:var(--purple-bg);border-radius:var(--radius);">
                    <p class="detail-label">Prescriptions</p>
                    <p class="detail-value"><?= count($prescriptions) ?> prescription(s)</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILL ITEMS -->
    <!-- ================================================================ -->
    <div class="modern-card animate-fade-in-up mt-5" style="animation-delay:0.1s;">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-list"></i>
                Bill Items
                <span class="card-badge"><?= count($bill_items) ?></span>
            </div>
        </div>
        
        <?php if (count($bill_items) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th>Item Type</th>
                            <th>Item Name</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bill_items as $item): ?>
                            <tr>
                                <td>
                                    <span class="status-badge" style="background:var(--primary-bg);color:var(--primary);">
                                        <?= ucfirst($item['item_type'] ?? 'Other') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></td>
                                <td><?= $item['quantity'] ?? 1 ?></td>
                                <td>TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                <td><strong>TSh <?= number_format($item['total_price'] ?? 0, 0) ?></strong></td>
                                <td>
                                    <span class="status-badge <?= ($item['status'] ?? 'pending') === 'cancelled' ? 'cancelled' : 'pending' ?>">
                                        <?= ucfirst($item['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="border-top:2px solid var(--border-color);font-weight:600;">
                            <td colspan="4" style="text-align:right;padding:12px;">Total:</td>
                            <td colspan="2" style="padding:12px;color:var(--primary);font-size:1.1rem;">
                                TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-6 text-gray-400">
                <i class="fas fa-box-open text-2xl block mb-2"></i>
                <p>No items found for this bill</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- PAYMENTS -->
    <!-- ================================================================ -->
    <?php if (count($payments) > 0): ?>
    <div class="modern-card animate-fade-in-up mt-5" style="animation-delay:0.15s;">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-coins"></i>
                Payments
                <span class="card-badge success"><?= count($payments) ?></span>
                <span class="card-badge" style="background:var(--success-bg);color:var(--success);">
                    Total: TSh <?= number_format($total_paid, 0) ?>
                </span>
            </div>
        </div>
        
        <div style="overflow-x:auto;">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Received By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?></td>
                            <td><strong>TSh <?= number_format($payment['amount'] ?? 0, 0) ?></strong></td>
                            <td><?= ucfirst($payment['payment_method'] ?? 'Cash') ?></td>
                            <td><?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($payment['received_at'] ?? 'now')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- CANCELLATION FORM -->
    <!-- ================================================================ -->
    <?php if ($bill['status'] !== 'cancelled'): ?>
    <div class="detail-card animate-fade-in-up mt-5" style="border-color:var(--danger);border-width:2px;">
        <h3 class="text-lg font-semibold text-danger mb-4">
            <i class="fas fa-ban text-danger mr-2"></i> Cancel Bill
        </h3>
        
        <form method="POST" action="" id="cancelForm">
            <input type="hidden" name="confirm_cancel" value="1">
            
            <div class="form-row-modern" style="margin-bottom:20px;">
                <label class="form-label">
                    <i class="fas fa-comment label-icon"></i> Cancellation Reason <span class="required">*</span>
                </label>
                <textarea name="reason" class="form-control-modern" required placeholder="Please provide a reason for cancelling this bill..." rows="4"><?= htmlspecialchars($cancellation_reason) ?></textarea>
            </div>
            
            <div class="alert-modern alert-modern-warning" style="margin:12px 0;">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Please confirm:</strong> This action cannot be undone. All payments will be reversed and stock will be restored.
                </div>
            </div>
            
            <div class="form-actions-modern">
                <button type="button" class="btn-modern btn-modern-danger" id="cancelBtn" onclick="confirmCancellation()" style="font-size:1rem;padding:12px 32px;">
                    <i class="fas fa-ban"></i> Cancel Bill
                </button>
                <a href="bills.php?branch=<?= $selected_branch_id ?>" class="btn-modern btn-modern-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
    <?php else: ?>
    <!-- Bill Already Cancelled -->
    <div class="detail-card animate-fade-in-up mt-5" style="border-color:var(--success);border-width:2px;background:var(--success-bg);">
        <div class="text-center py-6">
            <i class="fas fa-check-circle text-4xl text-success mb-3"></i>
            <h3 class="text-xl font-semibold text-success">Bill Already Cancelled</h3>
            <p class="text-gray-500 mt-2">This bill was cancelled on <?= date('F d, Y h:i A', strtotime($bill['updated_at'] ?? 'now')) ?></p>
            <?php if (!empty($bill['notes']) && strpos($bill['notes'], 'Cancelled by') !== false): ?>
                <p class="text-gray-600 mt-1"><?= nl2br(htmlspecialchars($bill['notes'])) ?></p>
            <?php endif; ?>
            <div class="mt-4">
                <a href="bills.php?branch=<?= $selected_branch_id ?>" class="btn-modern btn-modern-primary">
                    <i class="fas fa-arrow-left"></i> Back to Bills
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-modern">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Cancel Bill
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
<div id="toast" class="toast-modern" style="display:none;">
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
    // CLOCK
    // ================================================================
    function updateClock() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('clockDisplay');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

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
        
        toast.className = 'toast-modern ' + type;
        toastTitle.textContent = title;
        toastMessage.innerHTML = message;
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
    // CONFIRM CANCELLATION
    // ================================================================
    function confirmCancellation() {
        var reason = document.querySelector('textarea[name="reason"]');
        if (!reason.value.trim()) {
            showToast('⚠️ Warning', 'Please provide a cancellation reason', 'warning');
            reason.focus();
            return;
        }
        
        var totalAmount = <?= json_encode($bill['total_amount'] ?? 0) ?>;
        var totalPaid = <?= json_encode($total_paid) ?>;
        
        var message = '⚠️ Are you sure you want to cancel this bill?\n\n';
        message += 'Bill #: <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>\n';
        message += 'Total Amount: TSh ' + totalAmount.toLocaleString() + '\n';
        if (totalPaid > 0) {
            message += 'Paid Amount: TSh ' + totalPaid.toLocaleString() + ' (will be reversed)\n';
        }
        message += '\nThis action CANNOT be undone!';
        
        if (confirm(message)) {
            var btn = document.getElementById('cancelBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Processing...';
            
            document.getElementById('cancelForm').submit();
        }
    }

    // ================================================================
    // UPDATE FOOTER TIME
    // ================================================================
    function updateFooterTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('footerTimestamp');
        if (el) {
            el.textContent = 'Last updated: ' + timeStr;
        }
    }
    updateFooterTime();
    setInterval(updateFooterTime, 1000);

    console.log('%c🚫 Braick - Cancel Bill', 'font-size:18px; font-weight:bold; color:#DC2626;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Bill #<?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Total: TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?>', 'font-size:13px; color:#2563EB;');
    console.log('%c💳 Paid: TSh <?= number_format($total_paid, 0) ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Using tables: bills, bill_items, payments, patients, users, branches, visits', 'font-size:13px; color:#059669;');
</script>

</body>
</html>