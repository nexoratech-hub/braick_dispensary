<?php
// ================================================================
// FILE: frontend/pages/pharmacy/view_otc_sale.php
// PHARMACY - VIEW OTC SALE DETAILS (NO FINANCIAL DATA)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 5;
    $_SESSION['full_name'] = 'Peter Ngalula';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'pharm.peter';
    $_SESSION['is_admin'] = false;
}

$user_id = $_SESSION['user_id'] ?? 5;
$user_full_name = $_SESSION['full_name'] ?? 'Peter Ngalula';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

$db = getDB();

// ================================================================
// GET SALE ID
// ================================================================
$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($sale_id <= 0) {
    header('Location: otc_history.php');
    exit;
}

// ================================================================
// GET OTC SALE DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        os.*,
        u.full_name as cashier_name,
        b.name as branch_name
    FROM otc_sales os
    LEFT JOIN users u ON os.sold_by = u.id
    LEFT JOIN branches b ON os.branch_id = b.id
    WHERE os.id = ? AND os.branch_id = ?
");
$stmt->execute([$sale_id, $user_branch_id]);
$sale = $stmt->fetch();

if (!$sale) {
    header('Location: otc_history.php');
    exit;
}

// ================================================================
// GET SALE ITEMS
// ================================================================
$stmt = $db->prepare("
    SELECT * FROM otc_sale_items WHERE sale_id = ?
");
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll();

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
// GET STATISTICS FOR SIDEBAR
// ================================================================
$pending_prescriptions = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescription_sales WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_prescriptions = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

$low_stock_count = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND quantity <= reorder_level AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_count = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $low_stock_count = 0;
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!-- ================================================================ -->
<!-- STYLES -->
<!-- ================================================================ -->
<style>
    :root {
        --primary: #0B5ED7;
        --primary-dark: #0A3D8A;
        --primary-light: #E8F0FE;
        --success: #059669;
        --success-light: #D1FAE5;
        --warning: #D97706;
        --warning-light: #FEF3C7;
        --danger: #DC2626;
        --danger-light: #FEE2E2;
        --purple: #7C3AED;
        --purple-light: #EDE9FE;
        --otc-color: #7C3AED;
        --otc-bg: #EDE9FE;
        
        --bg-body: #F1F5F9;
        --bg-card: #FFFFFF;
        --border-color: #E2E8F0;
        --text-primary: #0F172A;
        --text-secondary: #475569;
        --text-muted: #94A3B8;
    }
    
    [data-theme="dark"] {
        --bg-body: #0F172A;
        --bg-card: #1E293B;
        --border-color: #334155;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --text-muted: #64748B;
    }
    
    .detail-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }
    
    .detail-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .detail-card .detail-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .detail-card .detail-header .sale-number {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-primary);
        font-family: monospace;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .detail-card .detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }
    
    .detail-card .detail-grid .info-item .label {
        font-size: 0.6rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
    }
    
    .detail-card .detail-grid .info-item .value {
        font-size: 0.95rem;
        font-weight: 500;
        color: var(--text-primary);
    }
    
    .detail-card .detail-grid .info-item .sub-value {
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .items-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        margin-bottom: 16px;
    }
    
    .items-table th {
        text-align: left;
        padding: 8px 12px;
        font-weight: 600;
        color: var(--text-secondary);
        font-size: 0.7rem;
        text-transform: uppercase;
        background: var(--bg-body);
        border-bottom: 2px solid var(--border-color);
    }
    
    .items-table td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
    }
    
    .items-table tr:hover td {
        background: var(--primary-light);
    }
    
    [data-theme="dark"] .items-table tr:hover td {
        background: #1E3A5F;
    }
    
    .items-table .text-right {
        text-align: right;
    }
    
    .items-table .qty-badge {
        background: var(--bg-body);
        padding: 2px 10px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.75rem;
        color: var(--text-secondary);
    }
    
    [data-theme="dark"] .items-table .qty-badge {
        background: #1E293B;
    }
    
    .summary-box {
        background: var(--bg-body);
        border-radius: 10px;
        padding: 16px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 10px;
        border: 1px solid var(--border-color);
    }
    
    .summary-box .total-label {
        font-weight: 600;
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    
    .summary-box .total-items {
        font-size: 0.9rem;
        color: var(--text-secondary);
    }
    
    .summary-box .total-items strong {
        color: var(--primary);
    }
    
    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .status-badge.dispensed {
        background: var(--success-light);
        color: var(--success);
    }
    
    .status-badge.pending {
        background: var(--warning-light);
        color: var(--warning);
    }
    
    .status-badge.cancelled {
        background: var(--danger-light);
        color: var(--danger);
    }
    
    .status-badge.completed {
        background: var(--success-light);
        color: var(--success);
    }
    
    [data-theme="dark"] .status-badge.dispensed {
        background: #1A3A2A;
        color: #34D399;
    }
    
    [data-theme="dark"] .status-badge.pending {
        background: #3D2E0A;
        color: #FBBF24;
    }
    
    [data-theme="dark"] .status-badge.cancelled {
        background: #3A1A1A;
        color: #F87171;
    }
    
    .type-badge {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: var(--otc-bg);
        color: var(--otc-color);
    }
    
    [data-theme="dark"] .type-badge {
        background: #2A1A3A;
        color: #9B4DCA;
    }
    
    .action-buttons {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 2px solid var(--border-color);
    }
    
    .btn-action {
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.85rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .btn-primary {
        background: var(--primary);
        color: white;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
    }
    
    .btn-success {
        background: var(--success);
        color: white;
    }
    .btn-success:hover {
        background: var(--success-dark);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    .btn-outline:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .btn-danger {
        background: var(--danger);
        color: white;
    }
    .btn-danger:hover {
        background: var(--danger-dark);
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
    
    .medicines-list {
        background: var(--bg-body);
        border-radius: 10px;
        padding: 16px 20px;
        border: 1px solid var(--border-color);
    }
    
    .medicines-list .medicine-item {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid var(--border-color);
        font-size: 0.9rem;
    }
    
    .medicines-list .medicine-item:last-child {
        border-bottom: none;
    }
    
    .medicines-list .medicine-item .med-name {
        font-weight: 500;
        color: var(--text-primary);
    }
    
    .medicines-list .medicine-item .med-details {
        color: var(--text-secondary);
        font-size: 0.8rem;
    }
    
    @media (max-width: 768px) {
        .detail-card .detail-grid {
            grid-template-columns: 1fr 1fr;
        }
        .detail-card {
            padding: 16px 18px;
        }
        .action-buttons {
            flex-direction: column;
            align-items: stretch;
        }
        .action-buttons .btn-action {
            justify-content: center;
        }
        .summary-box {
            flex-direction: column;
            text-align: center;
        }
    }
    
    @media (max-width: 480px) {
        .detail-card .detail-grid {
            grid-template-columns: 1fr;
        }
        .items-table {
            font-size: 0.75rem;
        }
        .items-table th,
        .items-table td {
            padding: 4px 8px;
        }
        .medicines-list .medicine-item {
            flex-direction: column;
            gap: 2px;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-shopping-cart mr-2" style="color: var(--otc-color);"></i> OTC Sale Details
            </h1>
            <p class="page-subtitle">
                View OTC sale information
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="ml-2 type-badge">
                    <i class="fas fa-shopping-cart"></i> OTC
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="otc_history.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- OTC SALE DETAILS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="detail-header">
            <div class="sale-number">
                <?= htmlspecialchars($sale['sale_number']) ?>
                <span class="status-badge <?= $sale['status'] ?? 'dispensed' ?>">
                    <?= ucfirst($sale['status'] ?? 'Dispensed') ?>
                </span>
            </div>
            <div class="text-sm text-gray-400">
                <i class="fas fa-calendar-alt mr-1"></i>
                <?= date('M d, Y h:i A', strtotime($sale['created_at'])) ?>
            </div>
        </div>
        
        <!-- Customer Info -->
        <div class="detail-grid">
            <div class="info-item">
                <div class="label">Customer</div>
                <div class="value"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></div>
                <div class="sub-value">Phone: <?= htmlspecialchars($sale['customer_phone'] ?? 'N/A') ?></div>
            </div>
            <div class="info-item">
                <div class="label">Cashier</div>
                <div class="value"><?= htmlspecialchars($sale['cashier_name'] ?? 'Unknown') ?></div>
                <div class="sub-value">ID: <?= $sale['sold_by'] ?? 'N/A' ?></div>
            </div>
            <div class="info-item">
                <div class="label">Branch</div>
                <div class="value"><?= htmlspecialchars($sale['branch_name'] ?? $user_branch_name) ?></div>
            </div>
            <div class="info-item">
                <div class="label">Payment Method</div>
                <div class="value">
                    <span class="status-badge dispensed">
                        <?= ucfirst(str_replace('_', ' ', $sale['payment_method'] ?? 'cash')) ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- ================================================================ -->
        <!-- ITEMS LIST - NO FINANCIAL DATA -->
        <!-- ================================================================ -->
        <h4 class="font-semibold text-gray-700 mb-3">
            <i class="fas fa-pills mr-2" style="color: var(--primary);"></i>
            Medicines Dispensed
        </h4>
        
        <?php if (count($items) > 0): ?>
            <div class="medicines-list">
                <?php foreach ($items as $item): ?>
                    <div class="medicine-item">
                        <span class="med-name">
                            <?= htmlspecialchars($item['medicine_name']) ?>
                        </span>
                        <span class="med-details">
                            Quantity: <strong><?= $item['quantity'] ?></strong>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription"></i>
                <p>No medicines found for this sale</p>
            </div>
        <?php endif; ?>
        
        <!-- ================================================================ -->
        <!-- SUMMARY - NO FINANCIAL DATA -->
        <!-- ================================================================ -->
        <div class="summary-box">
            <span class="total-label">
                <i class="fas fa-pills mr-1"></i> Total Items
            </span>
            <span class="total-items">
                <strong><?= count($items) ?></strong> medicine(s)
            </span>
        </div>
        
        <!-- ================================================================ -->
        <!-- ACTION BUTTONS -->
        <!-- ================================================================ -->
        <div class="action-buttons">
            <a href="print_receipt.php?type=otc&id=<?= $sale['id'] ?>" class="btn-action btn-success" target="_blank">
                <i class="fas fa-print"></i> Print Receipt
            </a>
            <a href="otc_history.php" class="btn-action btn-outline">
                <i class="fas fa-arrow-left"></i> Back to History
            </a>
            <?php if (($sale['status'] ?? '') === 'pending'): ?>
                <button onclick="confirmCancel(<?= $sale['id'] ?>)" class="btn-action btn-danger">
                    <i class="fas fa-times"></i> Cancel Sale
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer mt-5">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            OTC Sale Details
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
    // CANCEL SALE
    // ================================================================
    function confirmCancel(saleId) {
        if (confirm('Are you sure you want to cancel this OTC sale?')) {
            window.location.href = 'cancel_otc_sale.php?id=' + saleId;
        }
    }

    // ================================================================
    // CONSOLE
    // ================================================================
    console.log('%c💊 Braick - View OTC Sale (NO FINANCIAL DATA)', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c📋 Sale #: <?= $sale['sale_number'] ?? 'N/A' ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Customer: <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📦 Items: <?= count($items) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🚫 No financial data shown', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>