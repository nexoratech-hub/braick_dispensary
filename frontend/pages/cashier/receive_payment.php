<?php
// ================================================================
// FILE: frontend/pages/cashier/receive_payment.php
// CASHIER - RECEIVE PAYMENT
// FIXED: Uses bills table instead of patient_bills
// 8 CARDS DESIGN: 4 TOP + 4 BOTTOM
// WITH AUTO-UPDATE (3 SECONDS)
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
$user_email = $_SESSION['email'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';

// ================================================================
// CHECK IF USER IS RECEPTION
// ================================================================
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
// GET BILL ID FROM URL
// ================================================================
$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$bill_number = isset($_GET['bill_number']) ? trim($_GET['bill_number']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ================================================================
// GET BILL DETAILS - USING bills TABLE
// ================================================================
$bill = null;
$bill_items = [];
$patient = null;
$visit = null;

if ($bill_id > 0) {
    // Get bill from bills table
    $stmt = $db->prepare("
        SELECT 
            b.*,
            p.full_name as patient_name,
            p.patient_id,
            p.phone,
            p.gender,
            p.date_of_birth,
            v.visit_number,
            v.visit_type,
            v.status as visit_status,
            u.full_name as created_by_name
        FROM bills b
        JOIN patients p ON b.patient_id = p.id
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.id = ? AND b.branch_id = ?
    ");
    $stmt->execute([$bill_id, $user_branch_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bill) {
        // Get bill items
        $stmt = $db->prepare("
            SELECT * FROM bill_items 
            WHERE bill_id = ? AND status != 'cancelled'
            ORDER BY id
        ");
        $stmt->execute([$bill_id]);
        $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get patient
        $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$bill['patient_id']]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get visit
        if ($bill['visit_id']) {
            $stmt = $db->prepare("SELECT * FROM visits WHERE id = ?");
            $stmt->execute([$bill['visit_id']]);
            $visit = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Get payments
        $stmt = $db->prepare("
            SELECT * FROM payments 
            WHERE bill_id = ?
            ORDER BY received_at DESC
        ");
        $stmt->execute([$bill_id]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// If bill not found by ID, try by bill number
if (!$bill && !empty($bill_number)) {
    $stmt = $db->prepare("
        SELECT 
            b.*,
            p.full_name as patient_name,
            p.patient_id,
            p.phone,
            p.gender,
            p.date_of_birth,
            v.visit_number,
            v.visit_type,
            v.status as visit_status,
            u.full_name as created_by_name
        FROM bills b
        JOIN patients p ON b.patient_id = p.id
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.bill_number = ? AND b.branch_id = ?
    ");
    $stmt->execute([$bill_number, $user_branch_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bill) {
        $bill_id = $bill['id'];
        // Get bill items
        $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ? AND status != 'cancelled'");
        $stmt->execute([$bill_id]);
        $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$bill['patient_id']]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get payments
        $stmt = $db->prepare("SELECT * FROM payments WHERE bill_id = ? ORDER BY received_at DESC");
        $stmt->execute([$bill_id]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Search bills
if (!empty($search) && !$bill) {
    $stmt = $db->prepare("
        SELECT b.id, b.bill_number, b.total_amount, b.balance, b.status,
               p.full_name as patient_name, p.patient_id
        FROM bills b
        JOIN patients p ON b.patient_id = p.id
        WHERE b.branch_id = ? 
        AND (b.bill_number LIKE ? OR p.full_name LIKE ? OR p.patient_id LIKE ?)
        AND b.status IN ('pending', 'partial')
        LIMIT 10
    ");
    $search_term = "%$search%";
    $stmt->execute([$user_branch_id, $search_term, $search_term, $search_term]);
    $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// PROCESS PAYMENT
// ================================================================
$message = '';
$message_type = '';
$payment_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
    $bill_id = (int)$_POST['bill_id'];
    $amount = (float)$_POST['amount'];
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $reference_number = trim($_POST['reference_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if ($bill_id <= 0) {
        $message = "Invalid bill!";
        $message_type = 'error';
    } elseif ($amount <= 0) {
        $message = "Amount must be greater than zero!";
        $message_type = 'error';
    } else {
        // Get bill details from bills table
        $stmt = $db->prepare("SELECT * FROM bills WHERE id = ? AND branch_id = ? AND status NOT IN ('paid', 'cancelled')");
        $stmt->execute([$bill_id, $user_branch_id]);
        $bill_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bill_data) {
            $message = "Bill not found or already processed!";
            $message_type = 'error';
        } elseif ($bill_data['status'] === 'paid') {
            $message = "This bill is already fully paid!";
            $message_type = 'error';
        } elseif ($bill_data['status'] === 'cancelled') {
            $message = "This bill has been cancelled!";
            $message_type = 'error';
        } else {
            $balance = $bill_data['balance'];
            
            // Check if amount exceeds balance
            $change = 0;
            $exceeds_balance = false;
            if ($amount > $balance) {
                $change = $amount - $balance;
                $amount = $balance;
                $exceeds_balance = true;
            }
            
            try {
                $db->beginTransaction();
                
                // Generate receipt number
                $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Insert payment
                $stmt = $db->prepare("
                    INSERT INTO payments (
                        receipt_number, bill_id, patient_id, amount, 
                        payment_method, reference_number, notes, 
                        received_by, branch_id, received_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $receipt_number,
                    $bill_id,
                    $bill_data['patient_id'],
                    $amount,
                    $payment_method,
                    $reference_number,
                    $notes,
                    $user_id,
                    $user_branch_id
                ]);
                $payment_id = $db->lastInsertId();
                
                // Update bill
                $new_paid = $bill_data['paid_amount'] + $amount;
                $new_balance = $bill_data['total_amount'] - $new_paid;
                
                if ($new_balance <= 0) {
                    $new_status = 'paid';
                    $new_balance = 0;
                } else {
                    $new_status = 'partial';
                }
                
                // Update bills table
                $stmt = $db->prepare("
                    UPDATE bills 
                    SET paid_amount = ?, 
                        balance = ?, 
                        status = ?, 
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$new_paid, $new_balance, $new_status, $bill_id]);
                
                // Update bill_items - mark as paid
                $stmt = $db->prepare("
                    UPDATE bill_items 
                    SET status = 'paid',
                        updated_at = NOW()
                    WHERE bill_id = ? AND status = 'pending'
                ");
                $stmt->execute([$bill_id]);
                
                $db->commit();
                
                $payment_success = true;
                $message = "Payment processed successfully!";
                $message_type = 'success';
                
                // Refresh bill data
                $stmt = $db->prepare("
                    SELECT 
                        b.*,
                        p.full_name as patient_name,
                        p.patient_id,
                        p.phone,
                        p.gender,
                        p.date_of_birth,
                        v.visit_number,
                        v.visit_type,
                        v.status as visit_status,
                        u.full_name as created_by_name
                    FROM bills b
                    JOIN patients p ON b.patient_id = p.id
                    LEFT JOIN visits v ON b.visit_id = v.id
                    LEFT JOIN users u ON b.created_by = u.id
                    WHERE b.id = ? AND b.branch_id = ?
                ");
                $stmt->execute([$bill_id, $user_branch_id]);
                $bill = $stmt->fetch(PDO::FETCH_ASSOC);
                $bill_items = [];
                $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ? AND status != 'cancelled'");
                $stmt->execute([$bill_id]);
                $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Redirect to receipt if requested
                if (isset($_POST['save_and_print']) || isset($_POST['print_receipt'])) {
                    echo '<script>setTimeout(function(){ window.location.href = "print_receipt.php?payment_id=' . $payment_id . '&bill_id=' . $bill_id . '&print=1"; }, 1500);</script>';
                } else {
                    echo '<script>setTimeout(function(){ window.location.href = "paid_bills.php?success=1"; }, 1500);</script>';
                }
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = "Error: " . $e->getMessage();
                $message_type = 'error';
                error_log("Payment processing error: " . $e->getMessage());
            }
        }
    }
}

// ================================================================
// GET GLOBAL STATS FOR AUTO-UPDATE - Using bills table
// ================================================================
$today = date('Y-m-d');

try {
    // Today Payments
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? 
        AND DATE(updated_at) = ?
        AND paid_amount > 0
        AND status IN ('paid', 'partial')
    ");
    $stmt->execute([$user_branch_id, $today]);
    $today_payments = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_payments_count = $today_payments['count'] ?? 0;
    $today_payments_total = $today_payments['total'] ?? 0;

    // Pending Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? AND status = 'pending'
    ");
    $stmt->execute([$user_branch_id]);
    $pending_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_bills_count = $pending_bills['count'] ?? 0;
    $pending_bills_total = $pending_bills['total'] ?? 0;

    // Paid Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? AND status = 'paid'
    ");
    $stmt->execute([$user_branch_id]);
    $paid_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    $paid_bills_count = $paid_bills['count'] ?? 0;
    $paid_bills_total = $paid_bills['total'] ?? 0;

    // Cancelled Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? AND status = 'cancelled'
    ");
    $stmt->execute([$user_branch_id]);
    $cancelled_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $cancelled_bills_count = $cancelled_stats['count'] ?? 0;
    $cancelled_bills_total = $cancelled_stats['total'] ?? 0;

    // Total Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
        FROM bills WHERE branch_id = ?
    ");
    $stmt->execute([$user_branch_id]);
    $total_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_bills_count = $total_bills['count'] ?? 0;
    $total_bills_amount = $total_bills['total'] ?? 0;

    // Partial Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total_paid, COALESCE(SUM(balance), 0) as total_balance
        FROM bills 
        WHERE branch_id = ? AND status = 'partial'
    ");
    $stmt->execute([$user_branch_id]);
    $partial_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    $partial_bills_count = $partial_bills['count'] ?? 0;
    $partial_bills_paid = $partial_bills['total_paid'] ?? 0;
    $partial_bills_balance = $partial_bills['total_balance'] ?? 0;

    // Expenses
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
        FROM expenses 
        WHERE branch_id = ? AND status = 'paid'
    ");
    $stmt->execute([$user_branch_id]);
    $expenses = $stmt->fetch(PDO::FETCH_ASSOC);
    $expenses_count = $expenses['count'] ?? 0;
    $expenses_total = $expenses['total'] ?? 0;
    
    // Today's receipts count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM receipts 
        WHERE branch_id = ? AND DATE(printed_at) = ?
    ");
    $stmt->execute([$user_branch_id, $today]);
    $receipts_today = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_receipts = $receipts_today['count'] ?? 0;
    
} catch (Exception $e) {
    error_log("Global stats error: " . $e->getMessage());
    $today_payments_count = 0;
    $today_payments_total = 0;
    $pending_bills_count = 0;
    $pending_bills_total = 0;
    $paid_bills_count = 0;
    $paid_bills_total = 0;
    $cancelled_bills_count = 0;
    $cancelled_bills_total = 0;
    $total_bills_count = 0;
    $total_bills_amount = 0;
    $partial_bills_count = 0;
    $partial_bills_paid = 0;
    $partial_bills_balance = 0;
    $expenses_count = 0;
    $expenses_total = 0;
    $today_receipts = 0;
}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/cashier_header.php';
include_once __DIR__ . '/../../components/cashier_sidebar.php';
?>

<style>
    /* ================================================================
       RECEIVE PAYMENT STYLES
       ================================================================ */
    
    /* STATS CARDS - 4 TOP + 4 BOTTOM (8 CARDS TOTAL) */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        max-width: 1200px;
        margin: 0 auto 16px;
    }
    
    .stats-grid-bottom {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        max-width: 1200px;
        margin: 0 auto 20px;
    }
    
    .stat-card {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 16px 18px;
        border: 2px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
        box-shadow: var(--shadow-sm);
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }
    
    .stat-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        border-radius: 14px 14px 0 0;
    }
    
    .stat-card:hover {
        border-color: var(--success);
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
    }
    
    .stat-card .stat-icon {
        font-size: 1.6rem;
        margin-bottom: 4px;
        display: block;
    }
    
    .stat-card .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        line-height: 1.2;
        letter-spacing: -0.02em;
    }
    
    .stat-card .stat-number.green { color: var(--success); }
    .stat-card .stat-number.red { color: #DC2626; }
    .stat-card .stat-number.blue { color: var(--primary); }
    .stat-card .stat-number.yellow { color: var(--warning); }
    .stat-card .stat-number.purple { color: #7C3AED; }
    .stat-card .stat-number.pink { color: #DB2777; }
    .stat-card .stat-number.indigo { color: #4F46E5; }
    
    .stat-card .stat-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        margin-top: 2px;
    }
    
    .stat-card .stat-sub {
        font-size: 0.6rem;
        color: var(--text-secondary);
        margin-top: 2px;
        opacity: 0.7;
    }
    
    /* Card accent colors */
    .stat-card.accent-blue::after { background: var(--primary); }
    .stat-card.accent-red::after { background: #DC2626; }
    .stat-card.accent-green::after { background: var(--success); }
    .stat-card.accent-yellow::after { background: var(--warning); }
    .stat-card.accent-purple::after { background: #7C3AED; }
    .stat-card.accent-pink::after { background: #DB2777; }
    .stat-card.accent-indigo::after { background: #4F46E5; }
    .stat-card.accent-orange::after { background: #EA580C; }
    
    .bill-summary {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        margin-bottom: 20px;
    }
    
    .bill-summary .summary-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 10px;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .bill-summary .summary-title i {
        color: var(--primary);
    }
    
    .bill-summary .summary-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 16px;
        margin-bottom: 12px;
    }
    
    .bill-summary .summary-item .label {
        font-size: 0.6rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .bill-summary .summary-item .value {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .bill-summary .summary-item .value .grand-total {
        font-size: 1.2rem;
        color: var(--primary);
    }
    
    .bill-summary .summary-item .value .balance {
        color: #DC2626;
    }
    
    .bill-summary .summary-item .value .paid {
        color: #059669;
    }
    
    .payment-form {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
    }
    
    .payment-form .form-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 10px;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .payment-form .form-title i {
        color: #059669;
    }
    
    .form-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
        display: block;
    }
    
    .form-label .required {
        color: #DC2626;
        margin-left: 2px;
    }
    
    .form-control {
        width: 100%;
        padding: 8px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--bg-card);
        color: var(--text-primary);
    }
    
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .form-control:disabled {
        background: var(--bg-body);
        color: var(--text-secondary);
        cursor: not-allowed;
    }
    
    .form-control.form-control-lg {
        font-size: 1.2rem;
        font-weight: 700;
        padding: 12px 16px;
    }
    
    .form-row {
        margin-bottom: 14px;
    }
    
    .form-row:last-child {
        margin-bottom: 0;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 24px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-primary {
        background: var(--primary);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(11, 94, 215, 0.4);
    }
    
    .btn-success {
        background: #059669;
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    
    .btn-success:hover {
        background: #047857;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(5, 150, 105, 0.4);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
    }
    
    .btn-warning {
        background: #D97706;
        color: white;
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
    }
    
    .btn-warning:hover {
        background: #B45309;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(217, 119, 6, 0.4);
    }
    
    .btn-sm {
        padding: 6px 14px;
        font-size: 0.75rem;
        border-radius: 6px;
    }
    
    .btn-block {
        width: 100%;
        justify-content: center;
    }
    
    .form-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        padding-top: 16px;
        margin-top: 16px;
        border-top: 2px solid var(--border-color);
    }
    
    .change-amount {
        background: #FEF3C7;
        border: 2px solid #D97706;
        border-radius: 10px;
        padding: 12px 16px;
        margin-top: 10px;
        display: none;
    }
    
    .change-amount.show {
        display: block;
    }
    
    .change-amount .change-label {
        font-size: 0.75rem;
        color: #D97706;
        font-weight: 600;
    }
    
    .change-amount .change-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #D97706;
    }
    
    [data-theme="dark"] .change-amount {
        background: #3D2E0A;
        border-color: #FBBF24;
    }
    
    [data-theme="dark"] .change-amount .change-label {
        color: #FBBF24;
    }
    
    [data-theme="dark"] .change-amount .change-value {
        color: #FBBF24;
    }
    
    .bill-items-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
        margin-top: 10px;
    }
    
    .bill-items-table th {
        text-align: left;
        padding: 6px 10px;
        font-weight: 600;
        font-size: 0.65rem;
        text-transform: uppercase;
        color: var(--text-secondary);
        border-bottom: 2px solid var(--border-color);
    }
    
    .bill-items-table td {
        padding: 6px 10px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
    }
    
    .bill-items-table tr:last-child td {
        border-bottom: none;
    }
    
    .message-box {
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .message-box.success {
        background: #D1FAE5;
        color: #059669;
        border: 1px solid #059669;
    }
    
    .message-box.error {
        background: #FEE2E2;
        color: #DC2626;
        border: 1px solid #DC2626;
    }
    
    .message-box.info {
        background: #E8F0FE;
        color: #0B5ED7;
        border: 1px solid #0B5ED7;
    }
    
    [data-theme="dark"] .message-box.success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }
    
    [data-theme="dark"] .message-box.error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }
    
    [data-theme="dark"] .message-box.info {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: #6EA8FE;
    }
    
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
    
    .search-results {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        margin-bottom: 20px;
    }
    
    .search-results .result-item {
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .search-results .result-item:last-child {
        border-bottom: none;
    }
    
    .search-results .result-item:hover {
        background: var(--table-hover);
    }
    
    .status-badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .status-badge.pending { background: #FEF3C7; color: #D97706; }
    .status-badge.partial { background: #E8F0FE; color: #0B5ED7; }
    .status-badge.paid { background: #D1FAE5; color: #059669; }
    .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
    
    .page-header {
        background: linear-gradient(135deg, #059669, #047857);
        border-radius: 16px;
        padding: 24px 32px;
        margin-bottom: 28px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 4px 20px rgba(5, 150, 105, 0.25);
    }
    
    .page-header .page-title {
        color: white;
        font-size: 1.8rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
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
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .page-header .role-badge-display {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        backdrop-filter: blur(4px);
    }
    
    .page-header .branch-tag {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.1);
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .footer {
        padding: 14px 0;
        border-top: 1px solid var(--border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .footer .footer-brand { 
        color: var(--success); 
        font-weight: 600; 
    }
    
    @media (max-width: 1024px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .stats-grid-bottom { grid-template-columns: repeat(2, 1fr); }
    }
    
    @media (max-width: 768px) {
        .bill-summary .summary-grid {
            grid-template-columns: 1fr 1fr;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .stats-grid-bottom { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .stat-card { padding: 12px 14px; }
        .stat-card .stat-number { font-size: 1.4rem; }
    }
    
    @media (max-width: 640px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 6px; }
        .stats-grid-bottom { grid-template-columns: repeat(2, 1fr); gap: 6px; }
        .stat-card { padding: 8px 10px; }
        .stat-card .stat-number { font-size: 1.1rem; }
        .stat-card .stat-label { font-size: 0.55rem; }
        .stat-card .stat-icon { font-size: 1.2rem; }
        .bill-summary .summary-grid {
            grid-template-columns: 1fr;
        }
        .bill-summary {
            padding: 14px 16px;
        }
        .payment-form {
            padding: 14px 16px;
        }
    }
    
    @media (max-width: 400px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 4px; }
        .stats-grid-bottom { grid-template-columns: repeat(2, 1fr); gap: 4px; }
        .stat-card { padding: 6px 6px; }
        .stat-card .stat-number { font-size: 0.9rem; }
        .stat-card .stat-label { font-size: 0.5rem; }
        .stat-card .stat-icon { font-size: 1rem; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-hand-holding-usd"></i>
                Receive Payment
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
                <?php if ($is_reception): ?>
                    <span class="role-badge-display" style="background:rgba(251,191,36,0.3);color:#FCD34D;border:1px solid rgba(251,191,36,0.2);">
                        <i class="fas fa-eye"></i> Reception
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                Process payment for patient bill
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="pending_bills.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.2);padding:8px 18px;border-radius:10px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;backdrop-filter:blur(4px);">
                <i class="fas fa-arrow-left"></i> Back to Pending Bills
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 8 STATS CARDS - 4 TOP + 4 BOTTOM -->
    <!-- ================================================================ -->
    
    <!-- TOP 4 CARDS -->
    <div class="stats-grid" id="globalStatsTop">
        <!-- Card 1: Today Payments -->
        <div class="stat-card accent-blue" onclick="window.location.href='payment_history.php'">
            <span class="stat-icon">💳</span>
            <p class="stat-number blue" id="statTodayPayments"><?= number_format($today_payments_count) ?></p>
            <p class="stat-label">Today Payments</p>
            <p class="stat-sub">TSh <?= number_format($today_payments_total) ?></p>
        </div>
        
        <!-- Card 2: Pending Bills -->
        <div class="stat-card accent-red" onclick="window.location.href='pending_bills.php'">
            <span class="stat-icon">⏳</span>
            <p class="stat-number red" id="statPending"><?= number_format($pending_bills_count) ?></p>
            <p class="stat-label">Pending Bills</p>
            <p class="stat-sub">TSh <?= number_format($pending_bills_total) ?></p>
        </div>
        
        <!-- Card 3: Paid Bills -->
        <div class="stat-card accent-green" onclick="window.location.href='paid_bills.php'">
            <span class="stat-icon">✅</span>
            <p class="stat-number green" id="statPaid"><?= number_format($paid_bills_count) ?></p>
            <p class="stat-label">Paid Bills</p>
            <p class="stat-sub">TSh <?= number_format($paid_bills_total) ?></p>
        </div>
        
        <!-- Card 4: Cancelled Bills -->
        <div class="stat-card accent-red" onclick="window.location.href='cancelled_bills.php'">
            <span class="stat-icon">❌</span>
            <p class="stat-number red" id="statCancelled"><?= number_format($cancelled_bills_count) ?></p>
            <p class="stat-label">Cancelled Bills</p>
            <p class="stat-sub">TSh <?= number_format($cancelled_bills_total) ?></p>
        </div>
    </div>
    
    <!-- BOTTOM 4 CARDS -->
    <div class="stats-grid-bottom" id="globalStatsBottom">
        <!-- Card 5: Total Bills -->
        <div class="stat-card accent-purple" onclick="window.location.href='all_bills.php'">
            <span class="stat-icon">📋</span>
            <p class="stat-number purple" id="statTotal"><?= number_format($total_bills_count) ?></p>
            <p class="stat-label">Total Bills</p>
            <p class="stat-sub">TSh <?= number_format($total_bills_amount) ?></p>
        </div>
        
        <!-- Card 6: Partial Bills -->
        <div class="stat-card accent-yellow" onclick="window.location.href='partial_payments.php'">
            <span class="stat-icon">💰</span>
            <p class="stat-number yellow" id="statPartial"><?= number_format($partial_bills_count) ?></p>
            <p class="stat-label">Partial Bills</p>
            <p class="stat-sub">Paid: TSh <?= number_format($partial_bills_paid) ?></p>
        </div>
        
        <!-- Card 7: Expenses -->
        <div class="stat-card accent-pink" onclick="window.location.href='expenses.php'">
            <span class="stat-icon">💸</span>
            <p class="stat-number pink" id="statExpenses"><?= number_format($expenses_count) ?></p>
            <p class="stat-label">Expenses</p>
            <p class="stat-sub">TSh <?= number_format($expenses_total) ?></p>
        </div>
        
        <!-- Card 8: Today Receipts -->
        <div class="stat-card accent-indigo" onclick="window.location.href='receipt_history.php'">
            <span class="stat-icon">🧾</span>
            <p class="stat-number indigo" id="statReceipts"><?= number_format($today_receipts) ?></p>
            <p class="stat-label">Today Receipts</p>
            <p class="stat-sub">Printed today</p>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle') ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- Search Results -->
    <?php if (!empty($search_results) && !$bill): ?>
        <div class="search-results animate-fade-in-up">
            <div class="flex justify-between items-center mb-3">
                <h4 class="font-semibold text-sm">Search Results</h4>
                <span class="text-xs text-gray-400"><?= count($search_results) ?> bills found</span>
            </div>
            <?php foreach ($search_results as $result): ?>
                <div class="result-item">
                    <div>
                        <span class="font-medium"><?= htmlspecialchars($result['patient_name']) ?></span>
                        <span class="text-xs text-gray-400 ml-2"><?= htmlspecialchars($result['patient_id']) ?></span>
                        <div class="text-xs text-gray-400"><?= htmlspecialchars($result['bill_number']) ?></div>
                    </div>
                    <div class="text-right">
                        <div class="font-semibold">TSh <?= number_format($result['balance'], 0) ?></div>
                        <div class="text-xs">
                            <span class="status-badge <?= $result['status'] ?>"><?= ucfirst($result['status']) ?></span>
                        </div>
                        <a href="receive_payment.php?bill_id=<?= $result['id'] ?>" class="btn btn-success btn-sm mt-1">
                            <i class="fas fa-hand-holding-usd"></i> Pay
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PAYMENT FORM -->
    <!-- ================================================================ -->
    <?php if ($bill): ?>
        <div class="bill-summary animate-fade-in-up">
            <div class="summary-title">
                <i class="fas fa-file-invoice"></i>
                Bill Summary
            </div>
            
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="label">Bill Number</div>
                    <div class="value"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></div>
                </div>
                <div class="summary-item">
                    <div class="label">Patient</div>
                    <div class="value"><?= htmlspecialchars($bill['patient_name'] ?? 'Unknown') ?></div>
                    <div class="text-xs text-gray-400">ID: <?= htmlspecialchars($bill['patient_id'] ?? 'N/A') ?></div>
                </div>
                <div class="summary-item">
                    <div class="label">Visit</div>
                    <div class="value"><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></div>
                </div>
                <div class="summary-item">
                    <div class="label">Phone</div>
                    <div class="value"><?= htmlspecialchars($bill['phone'] ?? 'N/A') ?></div>
                </div>
                <div class="summary-item">
                    <div class="label">Status</div>
                    <div class="value">
                        <span class="status-badge <?= $bill['status'] ?? 'pending' ?>">
                            <?= ucfirst($bill['status'] ?? 'Pending') ?>
                        </span>
                    </div>
                </div>
                <div class="summary-item">
                    <div class="label">Created</div>
                    <div class="value"><?= isset($bill['created_at']) ? date('M d, Y h:i A', strtotime($bill['created_at'])) : 'N/A' ?></div>
                </div>
            </div>
            
            <!-- Bill Items -->
            <?php if (count($bill_items) > 0): ?>
                <table class="bill-items-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bill_items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['item_name'] ?? $item['description'] ?? 'N/A') ?></td>
                                <td class="text-right"><?= $item['quantity'] ?? 1 ?></td>
                                <td class="text-right">TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                <td class="text-right">TSh <?= number_format($item['total_price'] ?? $item['amount'] ?? 0, 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <!-- Totals -->
            <div class="flex flex-wrap justify-between items-center mt-4 pt-4 border-t border-gray-200">
                <div class="text-right">
                    <div class="text-sm text-gray-500">Total Amount</div>
                    <div class="text-xl font-bold text-blue-600">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">Amount Paid</div>
                    <div class="text-xl font-bold text-green-600">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">Balance</div>
                    <div class="text-xl font-bold text-red-600">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></div>
                </div>
            </div>
        </div>
        
        <!-- Payment Form -->
        <?php if (($bill['status'] ?? '') !== 'paid' && ($bill['status'] ?? '') !== 'cancelled'): ?>
        <div class="payment-form animate-fade-in-up">
            <div class="form-title">
                <i class="fas fa-hand-holding-usd"></i>
                Process Payment
            </div>
            
            <form method="POST" action="" id="paymentForm">
                <input type="hidden" name="action" value="process_payment">
                <input type="hidden" name="bill_id" value="<?= $bill['id'] ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    
                    <!-- Amount to Pay -->
                    <div class="form-row">
                        <label class="form-label">Amount to Pay <span class="required">*</span></label>
                        <input type="number" name="amount" id="paymentAmount" 
                               class="form-control form-control-lg" 
                               placeholder="Enter amount" 
                               max="<?= $bill['balance'] ?? 0 ?>" 
                               value="<?= $bill['balance'] ?? 0 ?>" 
                               step="0.01" min="0.01" required
                               oninput="calculateChange()">
                        <div class="text-xs text-gray-400 mt-1">
                            Max: TSh <?= number_format($bill['balance'] ?? 0, 0) ?>
                        </div>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="form-row">
                        <label class="form-label">Payment Method <span class="required">*</span></label>
                        <select name="payment_method" class="form-control" required>
                            <option value="cash">Cash</option>
                            <option value="m-pesa">M-Pesa</option>
                            <option value="airtel_money">Airtel Money</option>
                            <option value="tigo_pesa">Tigo Pesa</option>
                            <option value="halopesa">Halopesa</option>
                            <option value="bank">Bank</option>
                            <option value="card">Card</option>
                            <option value="insurance">Insurance</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <!-- Reference Number -->
                    <div class="form-row">
                        <label class="form-label">Reference Number <span class="text-gray-400 text-xs">(Optional)</span></label>
                        <input type="text" name="reference_number" class="form-control" 
                               placeholder="e.g. Transaction ID, Cheque Number">
                    </div>
                    
                    <!-- Notes -->
                    <div class="form-row">
                        <label class="form-label">Notes <span class="text-gray-400 text-xs">(Optional)</span></label>
                        <textarea name="notes" class="form-control" rows="2" 
                                  placeholder="Any additional notes..."></textarea>
                    </div>
                    
                </div>
                
                <!-- Change Amount -->
                <div class="change-amount" id="changeContainer">
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="change-label">Change</div>
                            <div class="change-value" id="changeAmount">TSh 0</div>
                        </div>
                        <div class="text-sm text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i>
                            Amount exceeds balance. Change will be given.
                        </div>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" name="save_and_print" value="1" class="btn btn-success">
                        <i class="fas fa-save"></i> Save & Print Receipt
                    </button>
                    <button type="submit" name="print_receipt" value="1" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print Receipt
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Receive Payment
                    </button>
                    <a href="pending_bills.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
                
            </form>
        </div>
        <?php else: ?>
            <div class="message-box info">
                <i class="fas fa-info-circle"></i>
                This bill is already <?= ($bill['status'] ?? '') === 'paid' ? 'fully paid' : 'cancelled' ?>.
                <a href="pending_bills.php" class="ml-2 text-blue-600 hover:underline">Go to pending bills</a>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-invoice"></i>
            <p>No bill selected</p>
            <p class="sub">
                Please select a bill from <a href="pending_bills.php" class="text-blue-600 hover:underline">Pending Bills</a>
                or enter a bill number in the search above.
            </p>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer mt-5">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Receive Payment
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
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // CALCULATE CHANGE
    // ================================================================
    function calculateChange() {
        var amountInput = document.getElementById('paymentAmount');
        var changeContainer = document.getElementById('changeContainer');
        var changeAmount = document.getElementById('changeAmount');
        var balance = <?= $bill['balance'] ?? 0 ?>;
        
        var amount = parseFloat(amountInput.value) || 0;
        
        if (amount > balance) {
            var change = amount - balance;
            changeContainer.classList.add('show');
            changeAmount.textContent = 'TSh ' + change.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0});
        } else {
            changeContainer.classList.remove('show');
        }
    }

    // ================================================================
    // DARK MODE - SYNC WITH HEADER
    // ================================================================
    // Note: Dark mode is controlled by header.

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
            window.location.href = 'receive_payment.php?search=' + encodeURIComponent(query);
        }
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
        // Set value from PHP
        searchInput.value = '<?= htmlspecialchars($search) ?>';
    }

    // ================================================================
    // MANUAL REFRESH
    // ================================================================
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        if (btn) {
            btn.innerHTML = '<span class="spinner" style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.3);border-top-color:white;border-radius:50%;animation:spin 0.6s linear infinite;"></span> Loading...';
            btn.disabled = true;
        }
        
        fetchDashboardData();
        
        setTimeout(function() {
            if (btn) {
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
                btn.disabled = false;
            }
            showToast('✅ Refreshed', 'Page data updated manually', 'success');
        }, 1500);
    }

    // ================================================================
    // FETCH DASHBOARD DATA (AJAX)
    // ================================================================
    function fetchDashboardData() {
        var url = 'get_dashboard_data.php?t=' + Date.now();
        
        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    updateStats(data);
                } else {
                    console.error('Failed to fetch dashboard data:', data.message);
                }
            })
            .catch(function(error) {
                console.error('Fetch error:', error);
            });
    }

    // ================================================================
    // UPDATE STATS UI
    // ================================================================
    function updateStats(data) {
        // Update all 8 stat cards
        var statMap = {
            'statTodayPayments': data.today_payments_count || 0,
            'statPending': data.pending_bills || 0,
            'statPaid': data.paid_bills || 0,
            'statCancelled': data.cancelled_bills || 0,
            'statTotal': data.total_bills || 0,
            'statPartial': data.partial_bills || 0,
            'statExpenses': data.expenses_count || 0,
            'statReceipts': data.today_receipts || 0
        };
        
        for (var key in statMap) {
            var el = document.getElementById(key);
            if (el) {
                el.textContent = Number(statMap[key]).toLocaleString();
            }
        }
        
        // Update footer timestamp
        var footerTs = document.getElementById('footerTimestamp');
        if (footerTs) {
            var now = new Date();
            var timeStr = now.toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
            });
            footerTs.textContent = 'Last updated: ' + timeStr;
        }
    }

    // ================================================================
    // AUTO UPDATE - EVERY 3 SECONDS
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    
    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        fetchDashboardData();
        updateInterval = setInterval(function() {
            if (!isUpdating) {
                isUpdating = true;
                fetchDashboardData();
                setTimeout(function() {
                    isUpdating = false;
                }, 1000);
            }
        }, 3000);
        console.log('%c🔄 Auto-update started (every 3s)', 'font-size:12px; color:#34D399;');
    }
    
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            console.log('%c⏹️ Auto-update stopped', 'font-size:12px; color:#DC2626;');
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

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
    // ADD CSS ANIMATIONS
    // ================================================================
    var style = document.createElement('style');
    style.textContent = `
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes pulse-dot { 
            0%, 100% { opacity: 1; transform: scale(1); } 
            50% { opacity: 0.5; transform: scale(0.8); } 
        }
        .stat-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .stat-card:hover { transform: translateY(-4px); }
        .form-control { transition: all 0.3s ease; }
        .btn { transition: all 0.3s ease; }
        .stat-number { transition: all 0.3s ease; }
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    `;
    document.head.appendChild(style);

    // ================================================================
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startAutoUpdate();
        }, 1000);
    });

    console.log('%c💰 Braick - Receive Payment (8 Cards + Auto-Update)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ ALLOWED ROLES: Cashier, Reception, Admin', 'font-size:13px; color:#34D399;');
    console.log('%c📋 Bill: <?= isset($bill['bill_number']) ? htmlspecialchars($bill['bill_number']) : 'None' ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💰 Balance: TSh <?= isset($bill['balance']) ? number_format($bill['balance']) : '0' ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c✅ FIXED: Uses bills table instead of patient_bills', 'font-size:13px; color:#34D399;');
    console.log('%c🔄 Auto-update every 3 seconds', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>