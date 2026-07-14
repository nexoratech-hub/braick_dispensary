<?php
// ================================================================
// FILE: frontend/pages/cashier/receipt.php
// RECEIPT - VIEW AND PRINT RECEIPT
// WITH BRAICK LOGO AND DETAILS
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// SESSION - Default to reception.rose
// ================================================================
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 6;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'reception.rose';
    $_SESSION['is_admin'] = false;
}

$user_id = $_SESSION['user_id'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_full_name = $_SESSION['full_name'] ?? 'Rose Mwangi';

// ================================================================
// DATABASE CONNECTION
// ================================================================
$db = getDB();

// ================================================================
// GET PAYMENT ID
// ================================================================
$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
$receipt_data = null;
$bill_items = [];
$error = null;

if ($payment_id <= 0) {
    $error = "Invalid payment ID!";
} else {
    // Get payment details with bill and patient info
    $stmt = $db->prepare("
        SELECT 
            p.*,
            pb.bill_number,
            pb.total_amount as bill_total,
            pb.paid_amount as bill_paid,
            pb.balance as bill_balance,
            pb.status as bill_status,
            pat.full_name as patient_name,
            pat.patient_id as patient_code,
            pat.phone as patient_phone,
            pat.address as patient_address,
            u.full_name as cashier_name,
            b.name as branch_name,
            b.location as branch_location,
            b.phone as branch_phone,
            b.email as branch_email
        FROM payments p
        LEFT JOIN patient_bills pb ON p.bill_id = pb.id
        LEFT JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN users u ON p.received_by = u.id
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE p.id = ? AND p.branch_id = ?
    ");
    $stmt->execute([$payment_id, $user_branch_id]);
    $receipt_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$receipt_data) {
        $error = "Payment not found!";
    } else {
        // Get bill items
        $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ?");
        $stmt->execute([$receipt_data['bill_id']]);
        $bill_items = $stmt->fetchAll();
    }
}

// ================================================================
// IF ERROR, REDIRECT
// ================================================================
if ($error) {
    $_SESSION['receipt_error'] = $error;
    header('Location: payment_history.php');
    exit;
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
include_once __DIR__ . '/../../components/reception_header.php';
include_once __DIR__ . '/../../components/cashier_sidebar.php';
?>

<style>
    /* ================================================================
       RECEIPT STYLES
       ================================================================ */
    
    .receipt-wrapper {
        max-width: 800px;
        margin: 0 auto;
        background: var(--bg-card);
        border-radius: 16px;
        border: 2px solid var(--border-color);
        overflow: hidden;
    }
    
    .receipt-header {
        background: #065F46;
        color: white;
        padding: 24px 30px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    }
    
    [data-theme="dark"] .receipt-header {
        background: #064E3B;
    }
    
    .receipt-header .logo-area {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    
    .receipt-header .logo-area img {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        background: white;
        padding: 4px;
        object-fit: cover;
    }
    
    .receipt-header .logo-area .brand-name {
        font-size: 1.4rem;
        font-weight: 700;
    }
    
    .receipt-header .logo-area .brand-sub {
        font-size: 0.7rem;
        opacity: 0.8;
    }
    
    .receipt-header .receipt-number {
        text-align: right;
    }
    
    .receipt-header .receipt-number .label {
        font-size: 0.65rem;
        opacity: 0.7;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .receipt-header .receipt-number .number {
        font-size: 1.2rem;
        font-weight: 700;
        font-family: monospace;
    }
    
    .receipt-body {
        padding: 24px 30px;
    }
    
    /* Business Details */
    .business-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        padding-bottom: 16px;
        margin-bottom: 16px;
        border-bottom: 2px dashed var(--border-color);
        font-size: 0.85rem;
    }
    
    .business-details .detail-item {
        display: flex;
        flex-direction: column;
    }
    
    .business-details .detail-item .label {
        font-size: 0.6rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .business-details .detail-item .value {
        font-weight: 500;
        color: var(--text-primary);
    }
    
    /* Patient Details */
    .patient-details {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 12px;
        padding: 12px 16px;
        background: var(--bg-body);
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 0.85rem;
    }
    
    .patient-details .detail-item .label {
        font-size: 0.6rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .patient-details .detail-item .value {
        font-weight: 600;
        color: var(--text-primary);
    }
    
    /* Table */
    .receipt-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        margin-bottom: 16px;
    }
    
    .receipt-table thead th {
        text-align: left;
        padding: 10px 12px;
        background: var(--bg-body);
        color: var(--text-secondary);
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--border-color);
    }
    
    .receipt-table tbody td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
    }
    
    .receipt-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    .receipt-table tbody tr:hover td {
        background: var(--table-hover);
    }
    
    .receipt-table .text-right {
        text-align: right;
    }
    
    .receipt-table .font-mono {
        font-family: monospace;
    }
    
    /* Totals */
    .totals-section {
        display: flex;
        justify-content: flex-end;
        padding-top: 16px;
        border-top: 2px solid var(--border-color);
    }
    
    .totals-box {
        width: 300px;
    }
    
    .totals-box .total-row {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        font-size: 0.9rem;
    }
    
    .totals-box .total-row .label {
        color: var(--text-secondary);
    }
    
    .totals-box .total-row .value {
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .totals-box .total-row.grand-total {
        border-top: 2px solid var(--border-color);
        padding-top: 8px;
        margin-top: 4px;
        font-size: 1.1rem;
    }
    
    .totals-box .total-row.grand-total .value {
        color: var(--primary);
        font-weight: 700;
    }
    
    .totals-box .total-row.balance {
        font-size: 1rem;
    }
    
    .totals-box .total-row.balance .value {
        color: var(--danger);
        font-weight: 700;
    }
    
    /* Payment Status Badge */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    .status-badge.completed {
        background: #D1FAE5;
        color: #059669;
    }
    
    .status-badge.pending {
        background: #FEF3C7;
        color: #D97706;
    }
    
    .status-badge.failed {
        background: #FEE2E2;
        color: #DC2626;
    }
    
    [data-theme="dark"] .status-badge.completed {
        background: #1A3A2A;
        color: #34D399;
    }
    
    [data-theme="dark"] .status-badge.pending {
        background: #3D2E0A;
        color: #FBBF24;
    }
    
    [data-theme="dark"] .status-badge.failed {
        background: #3A1A1A;
        color: #F87171;
    }
    
    /* Receipt Footer */
    .receipt-footer {
        padding: 16px 30px;
        background: var(--bg-body);
        border-top: 2px solid var(--border-color);
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .receipt-footer .thank-you {
        font-size: 1rem;
        font-weight: 600;
        color: var(--primary);
        margin-bottom: 4px;
    }
    
    /* Print Styles */
    @media print {
        .top-nav, .sidebar, .btn, .no-print {
            display: none !important;
        }
        
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .receipt-wrapper {
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }
        
        .receipt-header {
            background: #065F46 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        [data-theme="dark"] .receipt-header {
            background: #064E3B !important;
        }
        
        .receipt-body {
            padding: 20px !important;
        }
        
        .receipt-footer {
            background: #F8FAFC !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        [data-theme="dark"] .receipt-footer {
            background: #1E293B !important;
        }
        
        .patient-details {
            background: #F1F5F9 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        [data-theme="dark"] .patient-details {
            background: #1E293B !important;
        }
        
        .receipt-table thead th {
            background: #F1F5F9 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        [data-theme="dark"] .receipt-table thead th {
            background: #1E293B !important;
        }
        
        .status-badge.completed {
            background: #D1FAE5 !important;
            color: #059669 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
    
    /* Responsive */
    @media (max-width: 640px) {
        .receipt-header {
            flex-direction: column;
            text-align: center;
        }
        
        .receipt-header .receipt-number {
            text-align: center;
        }
        
        .business-details {
            grid-template-columns: 1fr;
        }
        
        .patient-details {
            grid-template-columns: 1fr 1fr;
        }
        
        .totals-box {
            width: 100%;
        }
        
        .receipt-body {
            padding: 16px;
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
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select class="branch-selector" disabled style="opacity:0.7;cursor:not-allowed;">
            <option value="<?= $user_branch_id ?>">
                🏥 <?= htmlspecialchars($user_branch_name) ?>
            </option>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn" id="notifBtn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="../cashier/profile.php">
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
                <i class="fas fa-receipt mr-2" style="color: var(--primary);"></i> Receipt
            </h1>
            <p class="page-subtitle">
                Payment receipt details
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap no-print">
            <button onclick="window.print()" class="btn btn-blue btn-sm">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <a href="payment_history.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECEIPT -->
    <!-- ================================================================ -->
    <div class="receipt-wrapper">
        
        <!-- Receipt Header -->
        <div class="receipt-header">
            <div class="logo-area">
                <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" 
                     alt="Braick Logo"
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2260%22%3E%3Crect width=%2260%22 height=%2260%22 fill=%22%23065F46%22 rx=%2212%22/%3E%3Ctext x=%2230%22 y=%2238%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2224%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
                <div>
                    <div class="brand-name">Braick Dispensary</div>
                    <div class="brand-sub">Quality Healthcare Services</div>
                </div>
            </div>
            <div class="receipt-number">
                <div class="label">Receipt Number</div>
                <div class="number"><?= htmlspecialchars($receipt_data['payment_number'] ?? 'N/A') ?></div>
                <div style="font-size:0.65rem; opacity:0.7; margin-top:4px;">
                    <?= date('d/m/Y h:i A', strtotime($receipt_data['payment_date'] ?? 'now')) ?>
                </div>
            </div>
        </div>
        
        <!-- Receipt Body -->
        <div class="receipt-body">
            
            <!-- Business Details -->
            <div class="business-details">
                <div class="detail-item">
                    <span class="label">Branch</span>
                    <span class="value"><?= htmlspecialchars($receipt_data['branch_name'] ?? 'Braick Dispensary') ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Location</span>
                    <span class="value"><?= htmlspecialchars($receipt_data['branch_location'] ?? 'Dodoma, Tanzania') ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Phone</span>
                    <span class="value"><?= htmlspecialchars($receipt_data['branch_phone'] ?? '+255 759 154 160') ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Email</span>
                    <span class="value"><?= htmlspecialchars($receipt_data['branch_email'] ?? 'info@braick.com') ?></span>
                </div>
            </div>
            
            <!-- Patient Details -->
            <div class="patient-details">
                <div class="detail-item">
                    <span class="label">Patient Name</span>
                    <span class="value"><?= htmlspecialchars($receipt_data['patient_name'] ?? 'Unknown') ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Patient ID</span>
                    <span class="value"><?= htmlspecialchars($receipt_data['patient_code'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Bill Number</span>
                    <span class="value"><?= htmlspecialchars($receipt_data['bill_number'] ?? 'N/A') ?></span>
                </div>
            </div>
            
            <!-- Bill Items Table -->
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th style="width:50%;">Item</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($bill_items) > 0): ?>
                        <?php foreach ($bill_items as $item): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($item['item_name']) ?>
                                    <span style="font-size:0.65rem; color:var(--text-secondary); display:block;">
                                        <?= ucfirst($item['item_type'] ?? 'other') ?>
                                    </span>
                                </td>
                                <td class="text-right"><?= $item['quantity'] ?></td>
                                <td class="text-right">TSh <?= number_format($item['unit_price']) ?></td>
                                <td class="text-right font-mono">TSh <?= number_format($item['total_price']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-gray-400 py-3">No items found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Totals -->
            <div class="totals-section">
                <div class="totals-box">
                    <div class="total-row">
                        <span class="label">Subtotal</span>
                        <span class="value">TSh <?= number_format($receipt_data['bill_total'] ?? 0) ?></span>
                    </div>
                    <div class="total-row">
                        <span class="label">Paid Amount</span>
                        <span class="value" style="color:var(--success);">
                            TSh <?= number_format($receipt_data['amount'] ?? 0) ?>
                        </span>
                    </div>
                    <div class="total-row">
                        <span class="label">Payment Method</span>
                        <span class="value">
                            <?= ucfirst(str_replace('-', ' ', $receipt_data['payment_method'] ?? 'cash')) ?>
                        </span>
                    </div>
                    <div class="total-row">
                        <span class="label">Status</span>
                        <span class="value">
                            <span class="status-badge <?= $receipt_data['status'] ?? 'completed' ?>">
                                <i class="fas fa-circle text-[6px]"></i>
                                <?= ucfirst($receipt_data['status'] ?? 'Completed') ?>
                            </span>
                        </span>
                    </div>
                    
                    <?php if (($receipt_data['bill_balance'] ?? 0) > 0): ?>
                        <div class="total-row balance">
                            <span class="label">Remaining Balance</span>
                            <span class="value">TSh <?= number_format($receipt_data['bill_balance'] ?? 0) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="total-row grand-total">
                        <span class="label">Total Paid</span>
                        <span class="value">TSh <?= number_format($receipt_data['amount'] ?? 0) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Cashier Info -->
            <div style="margin-top:16px; padding-top:12px; border-top:1px solid var(--border-color); font-size:0.75rem; color:var(--text-secondary); display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px;">
                <span>
                    <strong>Cashier:</strong> <?= htmlspecialchars($receipt_data['cashier_name'] ?? $user_full_name) ?>
                </span>
                <span>
                    <strong>Date:</strong> <?= date('d/m/Y h:i A', strtotime($receipt_data['payment_date'] ?? 'now')) ?>
                </span>
            </div>
        </div>
        
        <!-- Receipt Footer -->
        <div class="receipt-footer">
            <div class="thank-you">Thank You for Choosing Braick Dispensary</div>
            <div>This is a computer generated receipt. For any inquiries, please contact us.</div>
            <div style="margin-top:4px; font-size:0.6rem;">
                <?= date('Y') ?> &copy; Braick Dispensary - All rights reserved
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer mt-5">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Receipt
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
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // AUTO PRINT (Optional - uncomment to auto print)
    // ================================================================
    // window.onload = function() {
    //     setTimeout(function() {
    //         window.print();
    //     }, 1000);
    // };

    console.log('%c🧾 Braick - Receipt', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c📋 Payment #: <?= htmlspecialchars($receipt_data['payment_number'] ?? 'N/A') ?>', 'font-size:13px; color:#34D399;');
    console.log('%c👤 Patient: <?= htmlspecialchars($receipt_data['patient_name'] ?? 'Unknown') ?>', 'font-size:13px; color:#6EE7B7;');
    console.log('%c💰 Amount: TSh <?= number_format($receipt_data['amount'] ?? 0) ?>', 'font-size:13px; color:#6EE7B7;');
    console.log('%c🖨️ Click Print to print receipt', 'font-size:13px; color:#6EE7B7;');
</script>

</body>
</html>