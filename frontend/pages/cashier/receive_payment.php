<?php
// ================================================================
// FILE: frontend/pages/cashier/receive_payment.php
// CASHIER - RECEIVE PAYMENT
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// SESSION - Default to reception.rose (Cashier)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reception') {
    $_SESSION['user_id'] = 6;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'reception.rose';
    $_SESSION['is_admin'] = false;
}

$user_id = $_SESSION['user_id'] ?? 6;
$user_full_name = $_SESSION['full_name'] ?? 'Rose Mwangi';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

$db = getDB();

// ================================================================
// GET BILL ID FROM URL
// ================================================================
$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$bill_number = isset($_GET['bill_number']) ? trim($_GET['bill_number']) : '';

// ================================================================
// GET BILL DETAILS
// ================================================================
$bill = null;
$bill_items = [];
$patient = null;
$visit = null;

if ($bill_id > 0) {
    // Get bill
    $stmt = $db->prepare("
        SELECT b.*, p.full_name as patient_name, p.patient_id, p.phone,
               v.visit_number
        FROM bills b
        JOIN patients p ON b.patient_id = p.id
        LEFT JOIN visits v ON b.visit_id = v.id
        WHERE b.id = ? AND b.branch_id = ?
    ");
    $stmt->execute([$bill_id, $user_branch_id]);
    $bill = $stmt->fetch();
    
    if ($bill) {
        // Get bill items
        $stmt = $db->prepare("
            SELECT * FROM bill_items 
            WHERE bill_id = ?
            ORDER BY id
        ");
        $stmt->execute([$bill_id]);
        $bill_items = $stmt->fetchAll();
        
        // Get patient
        $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$bill['patient_id']]);
        $patient = $stmt->fetch();
        
        // Get visit
        if ($bill['visit_id']) {
            $stmt = $db->prepare("SELECT * FROM visits WHERE id = ?");
            $stmt->execute([$bill['visit_id']]);
            $visit = $stmt->fetch();
        }
    }
}

// If bill not found by ID, try by bill number
if (!$bill && !empty($bill_number)) {
    $stmt = $db->prepare("
        SELECT b.*, p.full_name as patient_name, p.patient_id, p.phone,
               v.visit_number
        FROM bills b
        JOIN patients p ON b.patient_id = p.id
        LEFT JOIN visits v ON b.visit_id = v.id
        WHERE b.bill_number = ? AND b.branch_id = ?
    ");
    $stmt->execute([$bill_number, $user_branch_id]);
    $bill = $stmt->fetch();
    
    if ($bill) {
        $bill_id = $bill['id'];
        // Get bill items
        $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ?");
        $stmt->execute([$bill_id]);
        $bill_items = $stmt->fetchAll();
        
        $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$bill['patient_id']]);
        $patient = $stmt->fetch();
    }
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
        // Get bill details
        $stmt = $db->prepare("SELECT * FROM bills WHERE id = ? AND branch_id = ?");
        $stmt->execute([$bill_id, $user_branch_id]);
        $bill_data = $stmt->fetch();
        
        if (!$bill_data) {
            $message = "Bill not found!";
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
                $new_paid = $bill_data['amount_paid'] + $amount;
                $new_balance = $bill_data['grand_total'] - $new_paid;
                
                if ($new_balance <= 0) {
                    $new_status = 'paid';
                } else {
                    $new_status = 'partial';
                }
                
                $stmt = $db->prepare("
                    UPDATE bills 
                    SET amount_paid = ?, balance = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$new_paid, $new_balance, $new_status, $bill_id]);
                
                $db->commit();
                
                $payment_success = true;
                $message = "Payment processed successfully!";
                $message_type = 'success';
                
                // Refresh bill data
                $stmt = $db->prepare("
                    SELECT b.*, p.full_name as patient_name, p.patient_id, p.phone,
                           v.visit_number
                    FROM bills b
                    JOIN patients p ON b.patient_id = p.id
                    LEFT JOIN visits v ON b.visit_id = v.id
                    WHERE b.id = ? AND b.branch_id = ?
                ");
                $stmt->execute([$bill_id, $user_branch_id]);
                $bill = $stmt->fetch();
                $bill_items = [];
                $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ?");
                $stmt->execute([$bill_id]);
                $bill_items = $stmt->fetchAll();
                
                // Redirect to receipt if requested
                if (isset($_POST['save_and_print']) || isset($_POST['print_receipt'])) {
                    echo '<script>setTimeout(function(){ window.location.href = "print_receipt.php?payment_id=' . $payment_id . '"; }, 1500);</script>';
                } else {
                    echo '<script>setTimeout(function(){ window.location.href = "view_bill.php?id=' . $bill['bill_number'] . '&success=1"; }, 1500);</script>';
                }
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = "Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// ================================================================
// GET SIDEBAR STATISTICS
// ================================================================
$pending_bills = 0;
$partial_payments = 0;
$paid_today = 0;
$patients_waiting = 0;

try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_bills = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'partial'");
    $stmt->execute([$user_branch_id]);
    $partial_payments = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'paid' AND DATE(updated_at) = CURDATE()");
    $stmt->execute([$user_branch_id]);
    $paid_today = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM bills WHERE branch_id = ? AND status IN ('pending', 'partial')");
    $stmt->execute([$user_branch_id]);
    $patients_waiting = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    // Keep counts as 0
}

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic = $_SESSION['profile_pic'] ?? '';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

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
    }
    
    @media (max-width: 480px) {
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
</style>

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
            <input type="text" id="searchInput" placeholder="Search bills, patients...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
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
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-hand-holding-usd mr-2" style="color: #059669;"></i> Receive Payment
            </h1>
            <p class="page-subtitle">
                Process payment for patient bill
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="pending_bills.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Pending Bills
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle') ?>"></i>
            <?= $message ?>
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
                    <div class="value"><?= htmlspecialchars($bill['bill_number']) ?></div>
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
                        <span class="badge <?= 
                            $bill['status'] === 'paid' ? 'badge-paid' : 
                            ($bill['status'] === 'partial' ? 'badge-partial' : 
                            ($bill['status'] === 'pending' ? 'badge-pending' : 'badge-cancelled'))
                        ?>">
                            <?= ucfirst($bill['status'] ?? 'Pending') ?>
                        </span>
                    </div>
                </div>
                <div class="summary-item">
                    <div class="label">Created</div>
                    <div class="value"><?= date('M d, Y h:i A', strtotime($bill['created_at'])) ?></div>
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
                                <td><?= htmlspecialchars($item['description']) ?></td>
                                <td class="text-right"><?= $item['quantity'] ?></td>
                                <td class="text-right">TSh <?= number_format($item['unit_price']) ?></td>
                                <td class="text-right">TSh <?= number_format($item['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <!-- Totals -->
            <div class="flex flex-wrap justify-between items-center mt-4 pt-4 border-t border-gray-200">
                <div class="text-right">
                    <div class="text-sm text-gray-500">Grand Total</div>
                    <div class="text-xl font-bold text-blue-600">TSh <?= number_format($bill['grand_total']) ?></div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">Amount Paid</div>
                    <div class="text-xl font-bold text-green-600">TSh <?= number_format($bill['amount_paid']) ?></div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">Balance</div>
                    <div class="text-xl font-bold text-red-600">TSh <?= number_format($bill['balance']) ?></div>
                </div>
            </div>
        </div>
        
        <!-- Payment Form -->
        <?php if ($bill['status'] !== 'paid' && $bill['status'] !== 'cancelled'): ?>
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
                               max="<?= $bill['balance'] ?>" 
                               value="<?= $bill['balance'] ?>" 
                               step="0.01" min="0.01" required
                               oninput="calculateChange()">
                        <div class="text-xs text-gray-400 mt-1">
                            Max: TSh <?= number_format($bill['balance']) ?>
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
                    <button type="submit" class="btn btn-primary">
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
                This bill is already <?= $bill['status'] === 'paid' ? 'fully paid' : 'cancelled' ?>.
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
            changeAmount.textContent = 'TSh ' + change.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            // Set amount to balance (will be processed server-side)
        } else {
            changeContainer.classList.remove('show');
        }
    }

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
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }

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
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
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
            searchInput?.focus();
            searchInput?.select();
        }
    });

    console.log('%c💰 Braick - Receive Payment', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Bill: <?= isset($bill['bill_number']) ? htmlspecialchars($bill['bill_number']) : 'None' ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💰 Balance: TSh <?= isset($bill['balance']) ? number_format($bill['balance']) : '0' ?>', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>