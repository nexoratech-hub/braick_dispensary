<?php
// ================================================================
// FILE: frontend/pages/cashier/make_payment.php
// CASHIER - PROCESS PAYMENT (BULK PAYMENT SYSTEM)
// Select bills to pay, choose payment method, complete payment
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// DEFAULT SESSION - Cashier Dodoma (ID: 10)
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

$user_id = $_SESSION['user_id'] ?? 10;
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_full_name = $_SESSION['full_name'] ?? 'Cashier Dodoma';

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$db = getDB();

// ================================================================
// HANDLE AJAX REQUESTS
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $bill_ids = isset($_POST['bill_ids']) ? $_POST['bill_ids'] : [];
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
    
    if ($action === 'complete_payment') {
        if (empty($bill_ids) || !is_array($bill_ids)) {
            echo json_encode(['success' => false, 'message' => 'No bills selected for payment']);
            exit;
        }
        
        try {
            $success_count = 0;
            $failed_bills = [];
            $receipt_numbers = [];
            $total_amount_paid = 0;
            
            foreach ($bill_ids as $bill_id) {
                $bill_id = (int)$bill_id;
                
                // Get bill details
                $stmt = $db->prepare("SELECT * FROM patient_bills WHERE id = ? AND branch_id = ? AND status != 'paid'");
                $stmt->execute([$bill_id, $user_branch_id]);
                $bill = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$bill) {
                    $failed_bills[] = $bill_id;
                    continue;
                }
                
                $remaining = (float)$bill['balance'];
                if ($remaining <= 0) {
                    // Already fully paid, just update status if needed
                    $stmt = $db->prepare("UPDATE patient_bills SET status = 'paid', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$bill_id]);
                    $success_count++;
                    continue;
                }
                
                // Generate receipt number
                $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                
                // Update bill to paid
                $stmt = $db->prepare("
                    UPDATE patient_bills 
                    SET paid_amount = total_amount, 
                        balance = 0, 
                        status = 'paid',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$bill_id]);
                
                // Insert payment record
                $stmt = $db->prepare("
                    INSERT INTO payments (receipt_number, bill_id, patient_id, amount, payment_method, received_by, branch_id, received_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $receipt_number,
                    $bill_id,
                    $bill['patient_id'],
                    $remaining,
                    $payment_method,
                    $user_id,
                    $user_branch_id
                ]);
                
                $total_amount_paid += $remaining;
                $receipt_numbers[] = $receipt_number;
                $success_count++;
            }
            
            // Log activity
            try {
                $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'bulk_payment_processed', ?)");
                $stmt->execute([$user_id, "Processed " . $success_count . " bills totaling TSh " . number_format($total_amount_paid) . " via " . $payment_method]);
            } catch (Exception $e) {}
            
            $message = $success_count . " bill(s) paid successfully! Total: TSh " . number_format($total_amount_paid);
            if (!empty($failed_bills)) {
                $message .= " Failed bills: " . implode(', ', $failed_bills);
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'receipt_numbers' => $receipt_numbers,
                'total_paid' => $total_amount_paid,
                'count' => $success_count
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'pay_all') {
        // Get all pending bills
        $stmt = $db->prepare("SELECT id FROM patient_bills WHERE branch_id = ? AND status != 'paid'");
        $stmt->execute([$user_branch_id]);
        $all_bills = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($all_bills)) {
            echo json_encode(['success' => false, 'message' => 'No pending bills to pay']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'bill_ids' => $all_bills,
            'count' => count($all_bills),
            'message' => count($all_bills) . ' bill(s) selected for payment'
        ]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// ================================================================
// GET ALL BILLS FOR THIS BRANCH (Only Pending and Partial)
// ================================================================
$stmt = $db->prepare("
    SELECT 
        pb.*,
        p.full_name as patient_name,
        p.patient_id as patient_number,
        p.phone as patient_phone,
        v.visit_number,
        v.visit_type,
        v.visit_date,
        u.full_name as doctor_name,
        u2.full_name as created_by_name,
        (
            SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id
        ) as item_count
    FROM patient_bills pb
    JOIN patients p ON pb.patient_id = p.id
    LEFT JOIN visits v ON pb.visit_id = v.id
    LEFT JOIN users u ON v.doctor_id = u.id
    LEFT JOIN users u2 ON pb.created_by = u2.id
    WHERE pb.branch_id = ? AND pb.status != 'paid'
    ORDER BY pb.created_at ASC
");
$stmt->execute([$user_branch_id]);
$bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get paid bills for history (optional - show at bottom)
$stmt = $db->prepare("
    SELECT 
        pb.*,
        p.full_name as patient_name,
        p.patient_id as patient_number,
        v.visit_number
    FROM patient_bills pb
    JOIN patients p ON pb.patient_id = p.id
    LEFT JOIN visits v ON pb.visit_id = v.id
    WHERE pb.branch_id = ? AND pb.status = 'paid'
    ORDER BY pb.updated_at DESC
    LIMIT 20
");
$stmt->execute([$user_branch_id]);
$paid_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CALCULATE TOTALS
// ================================================================
$total_bills = count($bills);
$total_amount = 0;
$total_pending_balance = 0;

foreach ($bills as $bill) {
    $total_amount += (float)$bill['total_amount'];
    $total_pending_balance += (float)$bill['balance'];
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/cashier_header.php';
include_once __DIR__ . '/../../components/cashier_sidebar.php';
?>

<style>
    /* ================================================================
       PROCESS PAYMENT STYLES
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    
    .stat-box {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 16px;
        border: 2px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .stat-box:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }
    
    .stat-box .number {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .stat-box .number.green { color: #059669; }
    .stat-box .number.orange { color: #D97706; }
    .stat-box .number.red { color: #DC2626; }
    .stat-box .number.purple { color: #7C3AED; }
    
    .stat-box .label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        margin-top: 2px;
    }
    
    /* Payment Controls */
    .payment-controls {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }
    
    .payment-controls .control-group {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .payment-controls .control-group label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .payment-controls select {
        padding: 6px 12px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.85rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        cursor: pointer;
    }
    
    .payment-controls select:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .payment-controls .selected-count {
        font-size: 0.8rem;
        color: var(--text-secondary);
        padding: 4px 12px;
        background: var(--bg-body);
        border-radius: 20px;
        border: 1px solid var(--border-color);
    }
    
    .payment-controls .selected-count strong {
        color: var(--primary);
    }
    
    /* Bill Table */
    .bills-table-wrap {
        overflow-x: auto;
        border-radius: 12px;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
        margin-bottom: 20px;
    }
    
    .bills-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
        min-width: 800px;
    }
    
    .bills-table thead th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: white;
        background: #0B5ED7;
        border-bottom: 3px solid #0A4CA8;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .bills-table thead th:first-child {
        border-radius: 8px 0 0 0;
        text-align: center;
    }
    
    .bills-table thead th:last-child {
        border-radius: 0 8px 0 0;
    }
    
    .bills-table tbody td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .bills-table tbody tr:hover td {
        background: var(--table-hover);
    }
    
    .bills-table tbody tr.selected td {
        background: #E8F0FE;
    }
    
    [data-theme="dark"] .bills-table tbody tr.selected td {
        background: #1E3A5F;
    }
    
    .bills-table tbody tr td:first-child {
        text-align: center;
    }
    
    /* Checkbox styling */
    .bill-checkbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #0B5ED7;
        border-radius: 4px;
    }
    
    .bill-checkbox:checked {
        background-color: #0B5ED7;
    }
    
    .bill-status {
        font-size: 0.65rem;
        font-weight: 600;
        padding: 3px 12px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .bill-status.pending {
        background: #FEF3C7;
        color: #D97706;
    }
    
    .bill-status.partial {
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    .bill-status.paid {
        background: #D1FAE5;
        color: #059669;
    }
    
    .bill-status.cancelled {
        background: #FEE2E2;
        color: #DC2626;
    }
    
    [data-theme="dark"] .bill-status.pending {
        background: #3D2E0A;
        color: #FBBF24;
    }
    
    [data-theme="dark"] .bill-status.partial {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    [data-theme="dark"] .bill-status.paid {
        background: #1A3A2A;
        color: #34D399;
    }
    
    [data-theme="dark"] .bill-status.cancelled {
        background: #3A1A1A;
        color: #F87171;
    }
    
    .amount-total {
        font-weight: 700;
        color: var(--text-primary);
    }
    
    .amount-balance {
        color: #DC2626;
        font-weight: 600;
    }
    
    .text-muted {
        color: var(--text-secondary);
    }
    
    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 18px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.82rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        white-space: nowrap;
    }
    
    .btn-success {
        background: #059669;
        color: white;
    }
    
    .btn-success:hover {
        background: #047857;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    
    .btn-primary {
        background: #0B5ED7;
        color: white;
    }
    
    .btn-primary:hover {
        background: #0A4CA8;
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
        border-color: #0B5ED7;
        color: #0B5ED7;
    }
    
    .btn-sm {
        padding: 4px 12px;
        font-size: 0.75rem;
    }
    
    .btn-lg {
        padding: 10px 28px;
        font-size: 0.95rem;
    }
    
    .btn-block {
        width: 100%;
        justify-content: center;
    }
    
    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
    }
    
    /* Paid Bills Section */
    .paid-bills-section {
        margin-top: 24px;
    }
    
    .paid-bills-section .section-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
    }
    
    .paid-bills-section .section-title .badge {
        background: #D1FAE5;
        color: #059669;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    [data-theme="dark"] .paid-bills-section .section-title .badge {
        background: #1A3A2A;
        color: #34D399;
    }
    
    .paid-bill-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 12px;
        border-bottom: 1px solid var(--border-color);
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .paid-bill-item .bill-number {
        font-family: monospace;
        color: var(--text-primary);
        font-weight: 500;
    }
    
    .paid-bill-item .bill-amount {
        color: #059669;
        font-weight: 600;
    }
    
    /* Toast */
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 12px 20px;
        border-radius: 12px;
        z-index: 999;
        max-width: 400px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    
    .toast-custom.show {
        transform: translateY(0);
        opacity: 1;
    }
    
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #DC2626; }
    .toast-custom.info { background: #0B5ED7; }
    
    .toast-custom .toast-icon {
        font-size: 1.2rem;
    }
    
    .toast-custom .toast-content {
        flex: 1;
    }
    
    .toast-custom .toast-title {
        font-weight: 600;
        font-size: 0.9rem;
        margin: 0;
    }
    
    .toast-custom .toast-message {
        font-size: 0.8rem;
        opacity: 0.9;
        margin: 0;
    }
    
    .spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255,255,255,0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
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
    
    .select-all-row {
        background: var(--bg-body);
        font-weight: 500;
    }
    
    .select-all-row td {
        padding: 6px 14px !important;
    }
    
    .select-all-row .select-all-label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .select-all-row .select-all-label:hover {
        color: var(--text-primary);
    }
    
    @media (max-width: 768px) {
        .bills-table {
            font-size: 0.7rem;
            min-width: 600px;
        }
        .bills-table th,
        .bills-table td {
            padding: 6px 8px;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .payment-controls {
            flex-direction: column;
            align-items: stretch;
        }
        .payment-controls .control-group {
            justify-content: center;
        }
        .payment-controls .btn {
            width: 100%;
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .payment-controls select {
            width: 100%;
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
            <input type="text" id="searchInput" placeholder="Search bills by patient name or bill number...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EC%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-money-bill-wave mr-2" style="color: var(--primary);"></i> Process Payments
                <span class="role-badge-display ml-2">CASHIER</span>
            </h1>
            <p class="page-subtitle">
                Select bills to pay, choose payment method, and complete payment
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-file-invoice mr-1"></i> <?= $total_bills ?> pending bill(s)
                </span>
                <span class="ml-2 inline-flex bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-xs border border-orange-200">
                    <i class="fas fa-money-bill mr-1"></i> Balance: <?= number_format($total_pending_balance, 2) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-box">
            <p class="number orange"><?= $total_bills ?></p>
            <p class="label">Pending Bills</p>
        </div>
        <div class="stat-box">
            <p class="number red"><?= number_format($total_pending_balance, 2) ?></p>
            <p class="label">Total Balance</p>
        </div>
        <div class="stat-box">
            <p class="number purple"><?= number_format($total_amount, 2) ?></p>
            <p class="label">Total Amount</p>
        </div>
        <div class="stat-box">
            <p class="number green" id="selectedTotal">0.00</p>
            <p class="label">Selected Total</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PAYMENT CONTROLS -->
    <!-- ================================================================ -->
    <div class="payment-controls" id="paymentControls">
        <div class="control-group">
            <label><i class="fas fa-hand-holding-usd"></i> Payment Method:</label>
            <select id="paymentMethod">
                <option value="cash">💰 Cash</option>
                <option value="m-pesa">📱 M-Pesa</option>
                <option value="airtel_money">📱 Airtel Money</option>
                <option value="tigo_pesa">📱 Tigo Pesa</option>
                <option value="halopesa">📱 HaloPesa</option>
                <option value="card">💳 Card</option>
                <option value="bank">🏦 Bank Transfer</option>
                <option value="insurance">🏥 Insurance</option>
                <option value="other">📦 Other</option>
            </select>
        </div>
        
        <div class="control-group">
            <span class="selected-count" id="selectedCount">Selected: <strong>0</strong> bills</span>
        </div>
        
        <div class="control-group" style="margin-left:auto; display:flex; gap:8px; flex-wrap:wrap;">
            <button onclick="selectAll()" class="btn btn-outline btn-sm">
                <i class="fas fa-check-double"></i> Select All
            </button>
            <button onclick="deselectAll()" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Deselect All
            </button>
            <button onclick="processPayment()" class="btn btn-success" id="completeBtn">
                <i class="fas fa-check-circle"></i> Complete Payment
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILLS TABLE -->
    <!-- ================================================================ -->
    <div class="bills-table-wrap">
        <table class="bills-table" id="billsTable">
            <thead>
                <tr>
                    <th style="text-align:center; width:40px;">
                        <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this.checked)" title="Select All">
                    </th>
                    <th>#</th>
                    <th>Bill Number</th>
                    <th>Patient</th>
                    <th>Visit</th>
                    <th>Doctor</th>
                    <th>Total</th>
                    <th>Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($bills) > 0): ?>
                    <?php $counter = 1; foreach ($bills as $bill): 
                        $balance = (float)$bill['balance'];
                    ?>
                        <tr data-bill-id="<?= $bill['id'] ?>" data-balance="<?= $balance ?>" data-total="<?= $bill['total_amount'] ?>">
                            <td style="text-align:center;">
                                <input type="checkbox" class="bill-checkbox bill-select" data-id="<?= $bill['id'] ?>" onchange="updateSelectedTotal()">
                            </td>
                            <td><?= $counter++ ?></td>
                            <td>
                                <span class="font-mono text-xs font-semibold text-blue-600"><?= htmlspecialchars($bill['bill_number']) ?></span>
                            </td>
                            <td>
                                <div class="font-medium text-sm"><?= htmlspecialchars($bill['patient_name']) ?></div>
                                <div class="text-xs text-gray-400"><?= htmlspecialchars($bill['patient_number'] ?? 'N/A') ?></div>
                            </td>
                            <td>
                                <div class="text-xs font-mono"><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></div>
                                <div class="text-xs text-gray-400 capitalize"><?= htmlspecialchars($bill['visit_type'] ?? 'N/A') ?></div>
                            </td>
                            <td>
                                <div class="text-sm">Dr. <?= htmlspecialchars($bill['doctor_name'] ?? 'Not assigned') ?></div>
                                <div class="text-xs text-gray-400"><?= $bill['item_count'] ?? 0 ?> items</div>
                            </td>
                            <td class="amount-total"><?= number_format($bill['total_amount'] ?? 0, 2) ?></td>
                            <td class="amount-balance"><?= number_format($balance, 2) ?></td>
                            <td>
                                <span class="bill-status <?= $bill['status'] ?>">
                                    <?php if ($bill['status'] === 'paid'): ?>
                                        ✅ Paid
                                    <?php elseif ($bill['status'] === 'partial'): ?>
                                        🔄 Partial
                                    <?php else: ?>
                                        ⏳ Pending
                                    <?php endif; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center py-8 text-gray-400">
                            <i class="fas fa-check-circle text-3xl block mb-2 text-green-500"></i>
                            <p>No pending bills found!</p>
                            <p class="text-sm mt-1">All bills have been paid</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ================================================================ -->
    <!-- PAID BILLS HISTORY -->
    <!-- ================================================================ -->
    <?php if (count($paid_bills) > 0): ?>
    <div class="paid-bills-section">
        <div class="section-title">
            <i class="fas fa-history text-green-600"></i>
            Recently Paid Bills
            <span class="badge"><?= count($paid_bills) ?></span>
        </div>
        <div class="bills-table-wrap" style="border-color: #D1FAE5;">
            <table class="bills-table" style="min-width: 500px;">
                <thead>
                    <tr>
                        <th>Bill Number</th>
                        <th>Patient</th>
                        <th>Total</th>
                        <th>Paid At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paid_bills as $paid): ?>
                        <tr>
                            <td><span class="font-mono text-xs text-green-600"><?= htmlspecialchars($paid['bill_number']) ?></span></td>
                            <td><?= htmlspecialchars($paid['patient_name']) ?></td>
                            <td class="text-green-600 font-semibold"><?= number_format($paid['total_amount'] ?? 0, 2) ?></td>
                            <td class="text-xs text-gray-400"><?= date('M d, Y h:i A', strtotime($paid['updated_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
            Process Payments
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle toast-icon"></i>
    <div class="toast-content">
        <p class="toast-title" id="toastTitle">Notification</p>
        <p class="toast-message" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // SELECT / DESELECT ALL
    // ================================================================
    function toggleSelectAll(checked) {
        var checkboxes = document.querySelectorAll('.bill-select');
        checkboxes.forEach(function(cb) {
            cb.checked = checked;
        });
        updateSelectedTotal();
    }
    
    function selectAll() {
        document.getElementById('selectAllCheckbox').checked = true;
        toggleSelectAll(true);
    }
    
    function deselectAll() {
        document.getElementById('selectAllCheckbox').checked = false;
        toggleSelectAll(false);
    }
    
    // ================================================================
    // UPDATE SELECTED TOTAL
    // ================================================================
    function updateSelectedTotal() {
        var checkboxes = document.querySelectorAll('.bill-select:checked');
        var count = checkboxes.length;
        var total = 0;
        var balance = 0;
        
        checkboxes.forEach(function(cb) {
            var row = cb.closest('tr');
            if (row) {
                var bal = parseFloat(row.dataset.balance || 0);
                balance += bal;
                var tot = parseFloat(row.dataset.total || 0);
                total += tot;
            }
        });
        
        document.getElementById('selectedCount').innerHTML = 'Selected: <strong>' + count + '</strong> bills';
        document.getElementById('selectedTotal').textContent = balance.toFixed(2);
        
        // Update select all checkbox
        var allCheckboxes = document.querySelectorAll('.bill-select');
        var allChecked = true;
        allCheckboxes.forEach(function(cb) {
            if (!cb.checked) allChecked = false;
        });
        document.getElementById('selectAllCheckbox').checked = allChecked && allCheckboxes.length > 0;
    }
    
    // ================================================================
    // PROCESS PAYMENT
    // ================================================================
    function processPayment() {
        var checkboxes = document.querySelectorAll('.bill-select:checked');
        var billIds = [];
        checkboxes.forEach(function(cb) {
            billIds.push(parseInt(cb.dataset.id));
        });
        
        if (billIds.length === 0) {
            showToast('⚠️ No Selection', 'Please select at least one bill to pay', 'info');
            return;
        }
        
        var paymentMethod = document.getElementById('paymentMethod').value;
        var btn = document.getElementById('completeBtn');
        var originalHtml = btn.innerHTML;
        
        btn.innerHTML = '<span class="spinner"></span> Processing...';
        btn.disabled = true;
        
        var formData = new FormData();
        formData.append('action', 'complete_payment');
        formData.append('payment_method', paymentMethod);
        billIds.forEach(function(id) {
            formData.append('bill_ids[]', id);
        });
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('✅ Success', data.message, 'success');
                
                // Update UI - mark selected rows as paid
                checkboxes.forEach(function(cb) {
                    var row = cb.closest('tr');
                    if (row) {
                        var statusCell = row.querySelector('.bill-status');
                        if (statusCell) {
                            statusCell.className = 'bill-status paid';
                            statusCell.innerHTML = '✅ Paid';
                        }
                        row.style.opacity = '0.6';
                        row.style.backgroundColor = 'var(--success-bg)';
                        var balanceCell = row.querySelector('.amount-balance');
                        if (balanceCell) {
                            balanceCell.textContent = '0.00';
                            balanceCell.style.color = '#059669';
                        }
                        cb.disabled = true;
                        cb.checked = false;
                    }
                });
                
                // Reload after 2 seconds to refresh data
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
                
            } else {
                showToast('❌ Error', data.message, 'error');
            }
            
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            updateSelectedTotal();
        })
        .catch(function(error) {
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
            btn.innerHTML = originalHtml;
            btn.disabled = false;
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
        }, 4000);
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
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'process_payment.php?search=' + encodeURIComponent(query);
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
        if (e.key === 'Enter' && e.ctrlKey) {
            e.preventDefault();
            processPayment();
        }
    });

    // ================================================================
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        updateSelectedTotal();
    });

    // ================================================================
    // CONSOLE
    // ================================================================
    console.log('%c💰 Braick - Process Payments (Bulk Payment)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total Bills: <?= $total_bills ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Total Balance: <?= number_format($total_pending_balance, 2) ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c✅ Select bills, choose payment method, click Complete Payment', 'font-size:12px; color:#34D399;');
    console.log('%c🔄 Ctrl+Enter to process payment quickly', 'font-size:12px; color:#34D399;');
</script>

</body>
</html>