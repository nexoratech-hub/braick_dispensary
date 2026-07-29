<?php
// ================================================================
// FILE: frontend/pages/admin/bill_details.php
// SUPER ADMIN - BILL DETAILS
// VIEW COMPLETE BILL INFORMATION
// BRAICK DISPENSARY
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
// GET BILL ID
// ================================================================
$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($bill_id <= 0) {
    header('Location: bills.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active'");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// FETCH BILL DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        pb.*,
        p.full_name as patient_name,
        p.patient_id as patient_id_number,
        p.phone as patient_phone,
        p.gender as patient_gender,
        p.date_of_birth as patient_dob,
        u.full_name as created_by_name,
        u.username as created_by_username,
        b.name as branch_name,
        b.location as branch_location,
        (SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id) as total_items,
        (SELECT COUNT(*) FROM payments WHERE bill_id = pb.id) as total_payments
    FROM patient_bills pb
    LEFT JOIN patients p ON pb.patient_id = p.id
    LEFT JOIN users u ON pb.created_by = u.id
    LEFT JOIN branches b ON pb.branch_id = b.id
    WHERE pb.id = ?
");
$stmt->execute([$bill_id]);
$bill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bill) {
    header('Location: bills.php?branch=' . $selected_branch_id . '&error=notfound');
    exit;
}

// ================================================================
// FETCH BILL ITEMS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        id,
        item_type,
        item_name,
        quantity,
        unit_price,
        total_price,
        payment_status,
        is_paid,
        status,
        paid_at,
        created_at,
        description
    FROM bill_items
    WHERE bill_id = ?
    ORDER BY created_at ASC
");
$stmt->execute([$bill_id]);
$bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// FETCH PAYMENTS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        id,
        receipt_number,
        amount,
        payment_method,
        reference_number,
        notes,
        received_by,
        received_at,
        (SELECT full_name FROM users WHERE id = payments.received_by) as received_by_name
    FROM payments
    WHERE bill_id = ?
    ORDER BY received_at DESC
");
$stmt->execute([$bill_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// FETCH PRESCRIPTION (if exists)
// ================================================================
$prescription = null;
if (!empty($bill['prescription_id'])) {
    $stmt = $db->prepare("
        SELECT 
            p.*,
            d.full_name as doctor_name,
            pat.full_name as patient_name
        FROM prescriptions p
        LEFT JOIN users d ON p.doctor_id = d.id
        LEFT JOIN patients pat ON p.patient_id = pat.id
        WHERE p.id = ?
    ");
    $stmt->execute([$bill['prescription_id']]);
    $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ================================================================
// FETCH VISIT (if exists)
// ================================================================
$visit = null;
if (!empty($bill['visit_id'])) {
    $stmt = $db->prepare("
        SELECT 
            v.*,
            d.full_name as doctor_name,
            r.full_name as receptionist_name
        FROM visits v
        LEFT JOIN users d ON v.doctor_id = d.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        WHERE v.id = ?
    ");
    $stmt->execute([$bill['visit_id']]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ================================================================
// CALCULATE TOTALS
// ================================================================
$total_items_amount = 0;
foreach ($bill_items as $item) {
    $total_items_amount += $item['total_price'];
}

$total_paid = $bill['paid_amount'] ?? 0;
$balance = $bill['balance'] ?? 0;
$total_amount = $bill['total_amount'] ?? 0;

// ================================================================
// GET STATUS BADGE CLASS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'paid' => 'success',
        'pending' => 'warning',
        'partial' => 'info',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'paid' => 'fa-check-circle',
        'pending' => 'fa-clock',
        'partial' => 'fa-hourglass-half',
        'cancelled' => 'fa-times-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
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
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
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
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-invoice mr-2"></i> Bill Details
            </h1>
            <p class="page-subtitle">
                View complete bill information
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="ml-2 date-badge">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <?php if ($bill['status'] !== 'paid' && $bill['status'] !== 'cancelled'): ?>
                <a href="process_payment.php?bill_id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-success btn-sm">
                    <i class="fas fa-money-bill-wave"></i> Process Payment
                </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="bills.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Bills
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILL SUMMARY CARD -->
    <!-- ================================================================ -->
    <div class="bill-summary-card mb-5">
        <div class="bill-summary-header">
            <div class="bill-number">
                <span class="label">Bill Number</span>
                <span class="value"><?= htmlspecialchars($bill['bill_number']) ?></span>
            </div>
            <div class="bill-status">
                <span class="badge badge-<?= getStatusBadge($bill['status']) ?>">
                    <i class="fas <?= getStatusIcon($bill['status']) ?>"></i>
                    <?= ucfirst($bill['status']) ?>
                </span>
            </div>
        </div>
        
        <div class="bill-summary-grid">
            <div class="summary-item">
                <span class="label">Patient</span>
                <span class="value"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></span>
                <span class="sub">ID: <?= htmlspecialchars($bill['patient_id_number'] ?? 'N/A') ?></span>
            </div>
            <div class="summary-item">
                <span class="label">Created By</span>
                <span class="value"><?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?></span>
                <span class="sub">@<?= htmlspecialchars($bill['created_by_username'] ?? '') ?></span>
            </div>
            <div class="summary-item">
                <span class="label">Date Created</span>
                <span class="value"><?= date('M d, Y', strtotime($bill['created_at'])) ?></span>
                <span class="sub"><?= date('h:i:s A', strtotime($bill['created_at'])) ?></span>
            </div>
            <div class="summary-item">
                <span class="label">Branch</span>
                <span class="value"><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></span>
                <span class="sub"><?= htmlspecialchars($bill['branch_location'] ?? '') ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FINANCIAL SUMMARY -->
    <!-- ================================================================ -->
    <div class="financial-summary-grid mb-5">
        <div class="financial-card">
            <div class="financial-icon blue">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div>
                <p class="financial-label">Total Amount</p>
                <p class="financial-value">TSh <?= number_format($total_amount, 2) ?></p>
            </div>
        </div>
        <div class="financial-card">
            <div class="financial-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <p class="financial-label">Paid Amount</p>
                <p class="financial-value">TSh <?= number_format($total_paid, 2) ?></p>
            </div>
        </div>
        <div class="financial-card">
            <div class="financial-icon orange">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <p class="financial-label">Balance</p>
                <p class="financial-value" style="color: <?= $balance > 0 ? '#EF4444' : '#059669' ?>;">
                    TSh <?= number_format($balance, 2) ?>
                </p>
            </div>
        </div>
        <div class="financial-card">
            <div class="financial-icon purple">
                <i class="fas fa-tags"></i>
            </div>
            <div>
                <p class="financial-label">Total Items</p>
                <p class="financial-value"><?= number_format($bill['total_items'] ?? 0) ?></p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILL ITEMS TABLE -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Bill Items
                <span class="text-xs text-gray-400 font-normal">(<?= count($bill_items) ?> items)</span>
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Name</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Total Price</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($bill_items) > 0): ?>
                        <?php $i = 1; foreach ($bill_items as $item): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <span class="font-medium"><?= htmlspecialchars($item['item_name']) ?></span>
                                    <?php if (!empty($item['description'])): ?>
                                        <br><span class="text-xs text-gray-400"><?= htmlspecialchars($item['description']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="item-type-badge item-<?= $item['item_type'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $item['item_type'])) ?>
                                    </span>
                                </td>
                                <td class="text-center"><?= $item['quantity'] ?></td>
                                <td class="text-right">TSh <?= number_format($item['unit_price'], 2) ?></td>
                                <td class="text-right font-semibold">TSh <?= number_format($item['total_price'], 2) ?></td>
                                <td>
                                    <span class="badge badge-<?= $item['is_paid'] ? 'success' : 'warning' ?>" style="font-size: 0.6rem; padding: 2px 10px;">
                                        <?= $item['is_paid'] ? 'Paid' : 'Unpaid' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-gray-400 text-sm py-5">
                                <i class="fas fa-inbox text-2xl block mb-2"></i>
                                No items found for this bill
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="text-right font-bold">Subtotal</td>
                        <td class="text-right font-bold">TSh <?= number_format($total_items_amount, 2) ?></td>
                        <td></td>
                    </tr>
                    <?php if ($bill['discount_amount'] > 0): ?>
                        <tr>
                            <td colspan="5" class="text-right text-red-500">Discount (<?= $bill['discount_percent'] ?>%)</td>
                            <td class="text-right text-red-500">- TSh <?= number_format($bill['discount_amount'], 2) ?></td>
                            <td></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td colspan="5" class="text-right font-bold text-lg">Total</td>
                        <td class="text-right font-bold text-lg text-blue-600">TSh <?= number_format($total_amount, 2) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PAYMENTS & PRESCRIPTION -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        
        <!-- Payments Section -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-credit-card title-green mr-2"></i> Payment History
                    <span class="text-xs text-gray-400 font-normal">(<?= count($payments) ?> payments)</span>
                </h3>
            </div>
            <?php if (count($payments) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($payments as $payment): ?>
                        <div class="payment-item">
                            <div class="payment-info">
                                <div class="payment-amount">
                                    <span class="amount">TSh <?= number_format($payment['amount'], 2) ?></span>
                                    <span class="method badge badge-<?= $payment['payment_method'] === 'cash' ? 'success' : 'info' ?>" style="font-size: 0.6rem;">
                                        <?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?>
                                    </span>
                                </div>
                                <div class="payment-details">
                                    <span class="receipt">Receipt: <?= htmlspecialchars($payment['receipt_number']) ?></span>
                                    <span class="received-by">By: <?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?></span>
                                    <span class="date"><?= date('M d, Y h:i A', strtotime($payment['received_at'])) ?></span>
                                </div>
                                <?php if (!empty($payment['reference_number'])): ?>
                                    <div class="payment-reference">
                                        Ref: <?= htmlspecialchars($payment['reference_number']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($payment['notes'])): ?>
                                    <div class="payment-notes">
                                        <?= htmlspecialchars($payment['notes']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center text-gray-400 text-sm py-5">
                    <i class="fas fa-credit-card text-2xl block mb-2"></i>
                    No payments recorded for this bill
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Prescription & Visit Details -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-prescription title-purple mr-2"></i> Related Information
                </h3>
            </div>
            
            <?php if ($prescription): ?>
                <div class="related-item">
                    <h4><i class="fas fa-prescription-bottle mr-2"></i> Prescription</h4>
                    <div class="related-details">
                        <div class="detail-row">
                            <span class="label">Prescription #</span>
                            <span class="value"><?= htmlspecialchars($prescription['prescription_number']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Doctor</span>
                            <span class="value"><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Status</span>
                            <span class="value">
                                <span class="badge badge-<?= $prescription['status'] === 'dispensed' ? 'success' : 'warning' ?>" style="font-size: 0.6rem;">
                                    <?= ucfirst($prescription['status']) ?>
                                </span>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Created</span>
                            <span class="value"><?= date('M d, Y', strtotime($prescription['created_at'])) ?></span>
                        </div>
                        <?php if (!empty($prescription['diagnosis'])): ?>
                            <div class="detail-row full">
                                <span class="label">Diagnosis</span>
                                <span class="value"><?= htmlspecialchars($prescription['diagnosis']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($visit): ?>
                <div class="related-item <?= $prescription ? 'mt-3' : '' ?>">
                    <h4><i class="fas fa-stethoscope mr-2"></i> Visit</h4>
                    <div class="related-details">
                        <div class="detail-row">
                            <span class="label">Visit #</span>
                            <span class="value"><?= htmlspecialchars($visit['visit_number']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Doctor</span>
                            <span class="value"><?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Status</span>
                            <span class="value">
                                <span class="badge badge-<?= $visit['status'] === 'completed' ? 'success' : 'warning' ?>" style="font-size: 0.6rem;">
                                    <?= ucfirst($visit['status']) ?>
                                </span>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Date</span>
                            <span class="value"><?= date('M d, Y', strtotime($visit['visit_date'])) ?></span>
                        </div>
                        <?php if (!empty($visit['diagnosis'])): ?>
                            <div class="detail-row full">
                                <span class="label">Diagnosis</span>
                                <span class="value"><?= htmlspecialchars($visit['diagnosis']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!$prescription && !$visit): ?>
                <div class="text-center text-gray-400 text-sm py-5">
                    <i class="fas fa-info-circle text-2xl block mb-2"></i>
                    No related prescription or visit found
                </div>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-bolt title-blue mr-2"></i> Quick Actions
            </h3>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if ($bill['status'] !== 'paid' && $bill['status'] !== 'cancelled'): ?>
                <a href="process_payment.php?bill_id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-success btn-sm">
                    <i class="fas fa-money-bill-wave"></i> Process Payment
                </a>
            <?php endif; ?>
            <a href="print_bill.php?id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" target="_blank" class="btn btn-blue btn-sm">
                <i class="fas fa-print"></i> Print Bill
            </a>
            <?php if ($bill['status'] !== 'cancelled'): ?>
                <a href="cancel_bill.php?id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this bill?')">
                    <i class="fas fa-times-circle"></i> Cancel Bill
                </a>
            <?php endif; ?>
            <a href="bills.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Bills
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Bill Details
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<style>
    /* ================================================================
       CUSTOM STYLES
       ================================================================ */
    
    /* Bill Summary Card */
    .bill-summary-card {
        background: var(--bg-card);
        border-radius: 16px;
        border: 1px solid var(--border-color);
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .bill-summary-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    
    .bill-summary-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 24px;
        background: #F8FAFC;
        border-bottom: 1px solid var(--border-color);
    }
    
    [data-theme="dark"] .bill-summary-header {
        background: #1E293B;
    }
    
    .bill-number .label {
        font-size: 0.65rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.03em;
        display: block;
    }
    
    .bill-number .value {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
        font-family: monospace;
    }
    
    .bill-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        padding: 20px 24px;
    }
    
    .summary-item .label {
        font-size: 0.65rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.03em;
        display: block;
    }
    
    .summary-item .value {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
        display: block;
    }
    
    .summary-item .sub {
        font-size: 0.75rem;
        color: var(--text-secondary);
    }
    
    /* Financial Summary */
    .financial-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
    }
    
    .financial-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 20px;
        border: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.3s ease;
    }
    
    .financial-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 2px 12px rgba(0,0,0,0.05);
    }
    
    .financial-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    
    .financial-icon.blue { background: #EFF6FF; color: #0B5ED7; }
    .financial-icon.green { background: #ECFDF5; color: #059669; }
    .financial-icon.orange { background: #FFFBEB; color: #F59E0B; }
    .financial-icon.purple { background: #F5F3FF; color: #7B2FBE; }
    
    [data-theme="dark"] .financial-icon.blue { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .financial-icon.green { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .financial-icon.orange { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .financial-icon.purple { background: #2D1B4E; color: #A78BFA; }
    
    .financial-label {
        font-size: 0.65rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin: 0;
    }
    
    .financial-value {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }
    
    /* Badges */
    .badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        color: white;
    }
    
    .badge-success { background: #059669; }
    .badge-warning { background: #F59E0B; color: #1E293B; }
    .badge-info { background: #0B5ED7; }
    .badge-danger { background: #EF4444; }
    .badge-secondary { background: #64748B; }
    
    [data-theme="dark"] .badge-warning { color: #1E293B; }
    
    /* Item Type Badges */
    .item-type-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 500;
    }
    
    .item-consultation { background: #E8F0FE; color: #0B5ED7; }
    .item-lab_test { background: #EDE9FE; color: #7B2FBE; }
    .item-medication { background: #D1FAE5; color: #059669; }
    .item-procedure { background: #FEF3C7; color: #D97706; }
    .item-tool { background: #FCE4EC; color: #DC2626; }
    .item-other { background: #F1F5F9; color: #64748B; }
    .item-registration { background: #CCFBF1; color: #0D9488; }
    
    [data-theme="dark"] .item-consultation { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .item-lab_test { background: #2D1B4E; color: #A78BFA; }
    [data-theme="dark"] .item-medication { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .item-procedure { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .item-tool { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .item-other { background: #334155; color: #94A3B8; }
    [data-theme="dark"] .item-registration { background: #0D2E2A; color: #2DD4BF; }
    
    /* Table */
    .data-table thead th {
        background: #0B5ED7 !important;
        color: white !important;
        font-weight: 600;
        padding: 10px 12px;
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        border-bottom: none !important;
    }
    
    .data-table thead th:first-child {
        border-radius: 8px 0 0 0;
    }
    
    .data-table thead th:last-child {
        border-radius: 0 8px 0 0;
    }
    
    .data-table td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        font-size: 0.8rem;
        vertical-align: middle;
    }
    
    .data-table tbody tr:hover td {
        background: var(--table-hover);
    }
    
    .data-table tfoot td {
        padding: 10px 12px;
        border-top: 2px solid var(--border-color);
        font-weight: 600;
    }
    
    .data-table .total-row td {
        border-top: 3px solid #0B5ED7;
        font-size: 1rem;
        background: #F8FAFC;
    }
    
    [data-theme="dark"] .data-table .total-row td {
        background: #1E293B;
    }
    
    /* Payment Items */
    .payment-item {
        padding: 12px 16px;
        background: var(--bg-body);
        border-radius: 10px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .payment-item:hover {
        border-color: #0B5ED7;
    }
    
    .payment-info .payment-amount {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 4px;
    }
    
    .payment-info .amount {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-primary);
    }
    
    .payment-info .payment-details {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .payment-info .payment-details span {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .payment-info .payment-reference {
        font-size: 0.7rem;
        color: var(--text-secondary);
        margin-top: 4px;
        padding: 2px 8px;
        background: var(--bg-card);
        border-radius: 4px;
        display: inline-block;
    }
    
    .payment-info .payment-notes {
        font-size: 0.7rem;
        color: var(--text-secondary);
        margin-top: 4px;
        font-style: italic;
    }
    
    /* Related Items */
    .related-item {
        padding: 12px 16px;
        background: var(--bg-body);
        border-radius: 10px;
        border: 1px solid var(--border-color);
    }
    
    .related-item h4 {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 10px 0;
    }
    
    .related-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px 20px;
    }
    
    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .detail-row:last-child {
        border-bottom: none;
    }
    
    .detail-row.full {
        grid-column: 1 / -1;
    }
    
    .detail-row .label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .detail-row .value {
        font-size: 0.8rem;
        color: var(--text-primary);
        font-weight: 500;
        text-align: right;
    }
    
    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: all 0.3s;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
    
    .btn-blue { background: #0B5ED7; color: white; }
    .btn-blue:hover { background: #0A4CA8; transform: translateY(-2px); }
    
    .btn-success { background: #059669; color: white; }
    .btn-success:hover { background: #047857; transform: translateY(-2px); }
    
    .btn-danger { background: #EF4444; color: white; }
    .btn-danger:hover { background: #DC2626; transform: translateY(-2px); }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: translateY(-2px);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .bill-summary-grid {
            grid-template-columns: 1fr 1fr;
        }
        .financial-summary-grid {
            grid-template-columns: 1fr 1fr;
        }
        .related-details {
            grid-template-columns: 1fr;
        }
        .detail-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
        }
        .detail-row .value {
            text-align: left;
        }
        .data-table {
            font-size: 0.7rem;
        }
        .data-table td,
        .data-table th {
            padding: 6px 8px;
        }
        .bill-summary-header {
            flex-direction: column;
            gap: 8px;
            text-align: center;
        }
    }
    
    @media (max-width: 480px) {
        .bill-summary-grid {
            grid-template-columns: 1fr;
        }
        .financial-summary-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

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
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c📄 Braick Dispensary - Bill Details', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏷️ Bill: <?= htmlspecialchars($bill['bill_number']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Total: TSh <?= number_format($total_amount, 2) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 Status: <?= ucfirst($bill['status']) ?>', 'font-size:13px; color:#7B2FBE;');
</script>

</body>
</html>