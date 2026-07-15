<?php
// ================================================================
// FILE: frontend/pages/cashier/paid_bills.php
// CASHIER - PAID BILLS
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
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';

// ================================================================
// GET PAID BILLS
// ================================================================
$query = "
    SELECT 
        b.*,
        p.full_name as patient_name,
        p.patient_id,
        v.visit_number,
        pay.receipt_number,
        pay.payment_method,
        pay.amount as payment_amount,
        pay.received_at as payment_date,
        pay.reference_number,
        u.full_name as cashier_name
    FROM bills b
    JOIN patients p ON b.patient_id = p.id
    LEFT JOIN visits v ON b.visit_id = v.id
    LEFT JOIN (
        SELECT * FROM payments ORDER BY received_at DESC LIMIT 1
    ) pay ON b.id = pay.bill_id
    LEFT JOIN users u ON pay.received_by = u.id
    WHERE b.branch_id = ? AND b.status = 'paid'
";

if (!empty($search)) {
    $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR b.bill_number LIKE ?)";
}

if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND DATE(b.updated_at) BETWEEN ? AND ?";
}

if (!empty($payment_method)) {
    $query .= " AND pay.payment_method = ?";
}

$query .= " ORDER BY b.updated_at DESC";

$stmt = $db->prepare($query);

$params = [$user_branch_id];

if (!empty($search)) {
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_from) && !empty($date_to)) {
    $params[] = $date_from;
    $params[] = $date_to;
}

if (!empty($payment_method)) {
    $params[] = $payment_method;
}

$stmt->execute($params);
$paid_bills = $stmt->fetchAll();

// ================================================================
// GET STATISTICS
// ================================================================
$total_paid = count($paid_bills);
$total_amount = 0;
foreach ($paid_bills as $bill) {
    $total_amount += $bill['grand_total'];
}

// ================================================================
// GET UNREAD NOTIFICATIONS
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
       PAID BILLS STYLES
       ================================================================ */
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .stat-box {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 2px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .stat-box:hover {
        border-color: var(--primary);
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
    }
    
    .stat-box .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .stat-box .stat-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        margin-top: 2px;
    }
    
    .stat-box .stat-icon {
        font-size: 1.2rem;
        margin-bottom: 4px;
        color: var(--primary);
    }
    
    .filter-section {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 12px 16px;
        border: 2px solid var(--border-color);
        margin-bottom: 16px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }
    
    .filter-section .form-control {
        padding: 6px 12px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.8rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
    }
    
    .filter-section .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .filter-section .btn-filter {
        padding: 6px 16px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        background: var(--primary);
        color: white;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .filter-section .btn-filter:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
    }
    
    .filter-section .btn-clear {
        padding: 6px 16px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .filter-section .btn-clear:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .table-wrap {
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }
    
    .data-table th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #fff;
        background: var(--primary);
        border-bottom: 3px solid var(--primary-dark);
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .data-table th:first-child {
        border-radius: 8px 0 0 0;
    }
    
    .data-table th:last-child {
        border-radius: 0 8px 0 0;
    }
    
    .data-table tbody tr:nth-child(even) {
        background: var(--primary-bg);
    }
    
    .data-table tbody tr:nth-child(odd) {
        background: var(--bg-card);
    }
    
    .data-table tbody tr:hover {
        background: #D1FAE5;
    }
    
    [data-theme="dark"] .data-table tbody tr:hover {
        background: #1A3A2A;
    }
    
    .data-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .badge-paid {
        background: #059669;
        color: white;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .badge-paid i {
        font-size: 0.5rem;
    }
    
    .btn-action {
        padding: 3px 10px;
        border-radius: 4px;
        font-size: 0.65rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .btn-action:hover {
        transform: scale(1.05);
    }
    
    .btn-view {
        background: #0B5ED7;
        color: white;
    }
    .btn-view:hover {
        background: #0A4CA8;
    }
    
    .btn-print {
        background: #64748B;
        color: white;
    }
    .btn-print:hover {
        background: #475569;
    }
    
    .btn-pdf {
        background: #DC2626;
        color: white;
    }
    .btn-pdf:hover {
        background: #B91C1C;
    }
    
    .btn-receipt {
        background: #059669;
        color: white;
    }
    .btn-receipt:hover {
        background: #047857;
    }
    
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 3rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 12px;
    }
    
    .method-badge {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 10px;
        display: inline-block;
    }
    
    .method-badge.cash { background: #E8F0FE; color: #0B5ED7; }
    .method-badge.m-pesa { background: #D1FAE5; color: #059669; }
    .method-badge.airtel_money { background: #FEF3C7; color: #D97706; }
    .method-badge.tigo_pesa { background: #F3E8FF; color: #7C3AED; }
    .method-badge.halopesa { background: #FCE4EC; color: #DB2777; }
    .method-badge.bank { background: #E0F2FE; color: #0284C7; }
    .method-badge.card { background: #F1F5F9; color: #475569; }
    .method-badge.other { background: #F1F5F9; color: #64748B; }
    
    [data-theme="dark"] .method-badge.cash { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .method-badge.m-pesa { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .method-badge.airtel_money { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .method-badge.tigo_pesa { background: #2A1A3A; color: #9B4DCA; }
    [data-theme="dark"] .method-badge.halopesa { background: #3A1A2A; color: #F472B6; }
    [data-theme="dark"] .method-badge.bank { background: #1A2A3A; color: #38BDF8; }
    [data-theme="dark"] .method-badge.card { background: #1E293B; color: #94A3B8; }
    [data-theme="dark"] .method-badge.other { background: #1E293B; color: #94A3B8; }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .filter-section {
            flex-direction: column;
            align-items: stretch;
        }
        .data-table {
            font-size: 0.7rem;
        }
        .data-table th,
        .data-table td {
            padding: 6px 10px;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .data-table th,
        .data-table td {
            padding: 4px 6px;
            font-size: 0.65rem;
        }
        .btn-action {
            font-size: 0.55rem;
            padding: 2px 6px;
        }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .filter-section, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .stat-box { border: 1px solid #ddd !important; page-break-inside: avoid; }
        .card { border: 1px solid #ddd !important; page-break-inside: avoid; }
        .page-header { border-bottom: 2px solid #0B5ED7 !important; }
        .data-table th { background: #0B5ED7 !important; color: white !important; }
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
            <input type="text" id="searchInput" placeholder="Search bills, patients..." 
                   value="<?= htmlspecialchars($search) ?>">
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
                <i class="fas fa-check-circle mr-2" style="color: #059669;"></i> Paid Bills
            </h1>
            <p class="page-subtitle">
                View all fully paid bills
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-check-circle mr-1"></i> <?= $total_paid ?> paid bills
                </span>
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <p class="stat-number"><?= $total_paid ?></p>
            <p class="stat-label">Total Paid Bills</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <p class="stat-number">TSh <?= number_format($total_amount) ?></p>
            <p class="stat-label">Total Amount</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-receipt"></i></div>
            <p class="stat-number"><?= $total_paid > 0 ? number_format($total_amount / $total_paid, 0) : 0 ?></p>
            <p class="stat-label">Average Bill</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER SECTION -->
    <!-- ================================================================ -->
    <div class="filter-section animate-fade-in-up">
        <form method="GET" action="" class="flex flex-wrap items-center gap-3 w-full">
            
            <input type="text" name="search" class="form-control" placeholder="Search by patient, bill..." 
                   value="<?= htmlspecialchars($search) ?>" style="flex:1; min-width:120px;">
            
            <select name="payment_method" class="form-control" style="min-width:130px;">
                <option value="">All Methods</option>
                <option value="cash" <?= $payment_method === 'cash' ? 'selected' : '' ?>>Cash</option>
                <option value="m-pesa" <?= $payment_method === 'm-pesa' ? 'selected' : '' ?>>M-Pesa</option>
                <option value="airtel_money" <?= $payment_method === 'airtel_money' ? 'selected' : '' ?>>Airtel Money</option>
                <option value="tigo_pesa" <?= $payment_method === 'tigo_pesa' ? 'selected' : '' ?>>Tigo Pesa</option>
                <option value="halopesa" <?= $payment_method === 'halopesa' ? 'selected' : '' ?>>Halopesa</option>
                <option value="bank" <?= $payment_method === 'bank' ? 'selected' : '' ?>>Bank</option>
                <option value="card" <?= $payment_method === 'card' ? 'selected' : '' ?>>Card</option>
                <option value="other" <?= $payment_method === 'other' ? 'selected' : '' ?>>Other</option>
            </select>
            
            <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>" style="width:140px;">
            <span class="text-sm text-gray-400">to</span>
            <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>" style="width:140px;">
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-search mr-1"></i> Apply
            </button>
            
            <a href="paid_bills.php" class="btn-clear">
                <i class="fas fa-times mr-1"></i> Clear
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- PAID BILLS TABLE -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i>
                Paid Bills List
                <span class="text-sm font-normal text-gray-400">(<?= $total_paid ?> records)</span>
            </h3>
        </div>
        
        <div class="table-wrap">
            <?php if (count($paid_bills) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Patient</th>
                            <th>Visit</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Receipt</th>
                            <th>Cashier</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paid_bills as $bill): ?>
                            <tr>
                                <td class="font-mono text-xs font-semibold">
                                    <?= htmlspecialchars($bill['bill_number']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($bill['patient_name'] ?? 'Unknown') ?>
                                    <div class="text-xs text-gray-400">ID: <?= htmlspecialchars($bill['patient_id'] ?? 'N/A') ?></div>
                                </td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></td>
                                <td class="font-semibold">TSh <?= number_format($bill['grand_total']) ?></td>
                                <td>
                                    <span class="method-badge <?= $bill['payment_method'] ?? 'other' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $bill['payment_method'] ?? 'Other')) ?>
                                    </span>
                                </td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($bill['receipt_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($bill['cashier_name'] ?? 'N/A') ?></td>
                                <td class="text-sm">
                                    <?= date('M d, Y', strtotime($bill['payment_date'] ?? $bill['updated_at'])) ?>
                                    <div class="text-xs text-gray-400">
                                        <?= date('h:i A', strtotime($bill['payment_date'] ?? $bill['updated_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex gap-1 flex-wrap">
                                        <a href="view_bill.php?id=<?= $bill['bill_number'] ?>" class="btn-action btn-view" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="print_receipt.php?receipt=<?= $bill['receipt_number'] ?>" class="btn-action btn-receipt" title="Receipt">
                                            <i class="fas fa-receipt"></i>
                                        </a>
                                        <a href="#" class="btn-action btn-pdf" onclick="downloadPDF('<?= $bill['receipt_number'] ?>'); return false;" title="PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="color: #D1FAE5;"></i>
                    <p>No paid bills found</p>
                    <p class="sub">
                        <?php if (!empty($search) || !empty($date_from) || !empty($payment_method)): ?>
                            Try adjusting your filters.
                        <?php else: ?>
                            No bills have been paid yet.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Total Records -->
        <?php if (count($paid_bills) > 0): ?>
            <div class="mt-3 text-sm text-gray-400 text-right">
                Showing <?= count($paid_bills) ?> records
                <?php if (!empty($search)): ?>
                    (filtered by: "<?= htmlspecialchars($search) ?>")
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK SUMMARY -->
    <!-- ================================================================ -->
    <?php if (count($paid_bills) > 0): ?>
        <div class="card mt-4 animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-pie title-green mr-2"></i>
                    Payment Methods Summary
                </h3>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <?php 
                    $method_summary = [];
                    foreach ($paid_bills as $bill) {
                        $method = $bill['payment_method'] ?? 'other';
                        if (!isset($method_summary[$method])) {
                            $method_summary[$method] = ['count' => 0, 'total' => 0];
                        }
                        $method_summary[$method]['count']++;
                        $method_summary[$method]['total'] += $bill['grand_total'];
                    }
                ?>
                <?php foreach ($method_summary as $method => $data): ?>
                    <div class="text-center p-3 border rounded-lg bg-gray-50">
                        <p class="text-sm font-medium text-gray-600"><?= ucfirst(str_replace('_', ' ', $method)) ?></p>
                        <p class="text-lg font-bold text-blue-600"><?= $data['count'] ?></p>
                        <p class="text-xs text-gray-500">TSh <?= number_format($data['total']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer mt-5">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Paid Bills
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
        var url = 'paid_bills.php';
        var params = [];
        if (query) params.push('search=' + encodeURIComponent(query));
        var method = document.querySelector('select[name="payment_method"]')?.value;
        if (method) params.push('payment_method=' + method);
        var date_from = document.querySelector('input[name="date_from"]')?.value;
        var date_to = document.querySelector('input[name="date_to"]')?.value;
        if (date_from) params.push('date_from=' + date_from);
        if (date_to) params.push('date_to=' + date_to);
        if (params.length > 0) {
            window.location.href = url + '?' + params.join('&');
        } else {
            window.location.href = url;
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
    // DOWNLOAD PDF
    // ================================================================
    function downloadPDF(receiptNumber) {
        if (!receiptNumber) {
            showToast('Error', 'No receipt found!', 'error');
            return;
        }
        showToast('Downloading PDF', 'Preparing receipt PDF...', 'info');
        window.location.href = 'download_pdf.php?receipt=' + receiptNumber;
        setTimeout(function() {
            showToast('Success', 'PDF downloaded successfully!', 'success');
        }, 3000);
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
        if (e.key === 'Escape' && document.activeElement === searchInput) {
            searchInput.value = '';
            performSearch();
        }
    });

    console.log('%c💰 Braick - Paid Bills', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Paid Bills: <?= $total_paid ?> | Amount: TSh <?= number_format($total_amount) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>