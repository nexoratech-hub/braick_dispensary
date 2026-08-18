<?php
// ================================================================
// FILE: frontend/pages/admin/view_otc_sale.php
// VIEW OTC SALE DETAILS
// BRAICK DISPENSARY
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
// CHECK IF USER HAS ACCESS (Admin only)
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

// ================================================================
// GET USER DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET SALE ID - ONLY FROM URL
// ================================================================
$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ================================================================
// IF NO ID, REDIRECT
// ================================================================
if ($sale_id <= 0) {
    header('Location: view_pharmacy.php?branch=all');
    exit;
}

// ================================================================
// GET OTC SALE DATA
// ================================================================
$query = "SELECT * FROM otc_sales WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

// ================================================================
// IF SALE NOT FOUND, REDIRECT
// ================================================================
if (!$sale) {
    header('Location: view_pharmacy.php?branch=all&error=not_found');
    exit;
}

// ================================================================
// GET SALE ITEMS
// ================================================================
$items_query = "SELECT * FROM otc_sale_items WHERE sale_id = ?";
$items_stmt = $db->prepare($items_query);
$items_stmt->execute([$sale_id]);
$sale_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET SOLD BY USER INFO
// ================================================================
$sold_by_name = 'Unknown';
if ($sale['sold_by'] > 0) {
    $user_stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
    $user_stmt->execute([$sale['sold_by']]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $sold_by_name = $user_data['full_name'] ?? 'Unknown';
}

// ================================================================
// GET BRANCH NAME
// ================================================================
$branch_name = 'Unknown';
if ($sale['branch_id'] > 0) {
    $branch_stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $branch_stmt->execute([$sale['branch_id']]);
    $branch_data = $branch_stmt->fetch(PDO::FETCH_ASSOC);
    $branch_name = $branch_data['name'] ?? 'Unknown';
}

// ================================================================
// GET PATIENT NAME (if patient_id exists)
// ================================================================
$patient_name = 'Walk-in Customer';
if (!empty($sale['patient_id']) && $sale['patient_id'] > 0) {
    $patient_stmt = $db->prepare("SELECT full_name FROM patients WHERE id = ?");
    $patient_stmt->execute([$sale['patient_id']]);
    $patient_data = $patient_stmt->fetch(PDO::FETCH_ASSOC);
    $patient_name = $patient_data['full_name'] ?? 'Walk-in Customer';
}

// ================================================================
// GET SELECTED BRANCH FOR SIDEBAR
// ================================================================
$selected_branch_id = $sale['branch_id'] ?? 'all';

// ================================================================
// GET TOTAL BRANCHES FOR SIDEBAR
// ================================================================
$stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// GET TOTAL EMPLOYEES FOR SIDEBAR
// ================================================================
if ($selected_branch_id === 'all') {
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE status = 'active' AND role != 'admin'");
} else {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active' AND role != 'admin' AND branch_id = ?");
    $stmt->execute([$selected_branch_id]);
}
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// GET TOTAL DOCTORS FOR SIDEBAR
// ================================================================
if ($selected_branch_id === 'all') {
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE status = 'active' AND role = 'doctor'");
} else {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active' AND role = 'doctor' AND branch_id = ?");
    $stmt->execute([$selected_branch_id]);
}
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

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
// FORMAT FUNCTIONS
// ================================================================
function format_currency($amount) {
    if ($amount == 0) {
        return 'TSh 0';
    }
    return 'TSh ' . number_format($amount, 2);
}

function format_date($date) {
    if (empty($date)) {
        return 'N/A';
    }
    return date('F d, Y H:i:s', strtotime($date));
}

function get_payment_method_badge($method) {
    $badges = [
        'cash' => 'bg-green-100 text-green-700',
        'm-pesa' => 'bg-purple-100 text-purple-700',
        'airtel_money' => 'bg-red-100 text-red-700',
        'tigo_pesa' => 'bg-blue-100 text-blue-700',
        'halopesa' => 'bg-yellow-100 text-yellow-700',
        'bank' => 'bg-indigo-100 text-indigo-700',
        'card' => 'bg-gray-100 text-gray-700',
        'other' => 'bg-gray-100 text-gray-700'
    ];
    return $badges[$method] ?? 'bg-gray-100 text-gray-700';
}

function get_payment_status_badge($status) {
    $badges = [
        'paid' => 'bg-green-100 text-green-700',
        'pending' => 'bg-yellow-100 text-yellow-700',
        'partial' => 'bg-blue-100 text-blue-700',
        'cancelled' => 'bg-red-100 text-red-700'
    ];
    return $badges[$status] ?? 'bg-gray-100 text-gray-700';
}

// ================================================================
// INCLUDE THE SHARED HEADER (FAVICON, SEARCH, DATE/TIME, DARK MODE)
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';

// ================================================================
// INCLUDE THE SHARED SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header" style="background:#0B5ED7;border-radius:18px;padding:28px 36px;margin-bottom:28px;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:16px;box-shadow:0 8px 32px rgba(11,94,215,0.3);position:relative;overflow:hidden;">
        <div style="position:relative;z-index:1;">
            <h1 style="color:white;font-size:1.8rem;font-weight:700;display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:0;">
                <i class="fas fa-shopping-cart" style="font-size:2rem;opacity:0.9;"></i>
                OTC Sale #<?= htmlspecialchars($sale['sale_number']) ?>
                <span style="background:rgba(255,255,255,0.2);color:white;padding:4px 14px;border-radius:20px;font-size:0.65rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;"><?= strtoupper($user_role) ?></span>
                <span style="background:rgba(52,211,153,0.2);border:1px solid rgba(52,211,153,0.3);color:#34D399;padding:4px 14px;border-radius:20px;font-size:0.7rem;font-weight:500;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fas fa-circle" style="font-size:6px;"></i> <?= ucfirst($sale['payment_status'] ?? 'pending') ?>
                </span>
            </h1>
            <p style="color:rgba(255,255,255,0.85);font-size:0.95rem;display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0;">
                <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                <span style="background:rgba(255,255,255,0.12);color:white;padding:4px 14px;border-radius:20px;font-size:0.7rem;font-weight:500;display:inline-flex;align-items:center;gap:6px;border:1px solid rgba(255,255,255,0.1);">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?>
                </span>
                <span style="background:rgba(255,255,255,0.12);color:white;padding:4px 14px;border-radius:20px;font-size:0.7rem;font-weight:500;display:inline-flex;align-items:center;gap:6px;border:1px solid rgba(255,255,255,0.1);">
                    <i class="fas fa-calendar"></i> <?= format_date($sale['created_at']) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_pharmacy.php?branch=<?= $selected_branch_id ?>" style="background:rgba(255,255,255,0.12);color:white;border:1px solid rgba(255,255,255,0.2);padding:8px 18px;border-radius:12px;font-weight:500;font-size:0.82rem;transition:all 0.3s;text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
                <i class="fas fa-arrow-left"></i> Back to Pharmacy
            </a>
        </div>
    </div>

    <!-- Sale Details -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        <!-- Sale Information -->
        <div class="detail-card animate-fade-in-up" style="animation-delay:0.05s;">
            <div class="card-title">
                <i class="fas fa-info-circle"></i>
                Sale Information
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Sale Number</span>
                <span class="detail-value"><strong><?= htmlspecialchars($sale['sale_number']) ?></strong></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Customer</span>
                <span class="detail-value"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Customer Phone</span>
                <span class="detail-value"><?= htmlspecialchars($sale['customer_phone'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Patient</span>
                <span class="detail-value"><?= htmlspecialchars($patient_name) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Branch</span>
                <span class="detail-value"><?= htmlspecialchars($branch_name) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Sold By</span>
                <span class="detail-value"><?= htmlspecialchars($sold_by_name) ?></span>
            </div>
        </div>
        
        <!-- Payment Information -->
        <div class="detail-card animate-fade-in-up" style="animation-delay:0.1s;">
            <div class="card-title">
                <i class="fas fa-credit-card"></i>
                Payment Information
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Total Amount</span>
                <span class="detail-value" style="font-size:1.1rem;font-weight:700;color:#0B5ED7;">
                    <?= format_currency($sale['total_amount']) ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Discount</span>
                <span class="detail-value" style="color:#EF4444;">
                    - <?= format_currency($sale['discount_amount']) ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Net Amount</span>
                <span class="detail-value" style="font-size:1.3rem;font-weight:700;color:#059669;">
                    <?= format_currency($sale['net_amount']) ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Payment Method</span>
                <span class="detail-value">
                    <span class="px-2 py-1 rounded-full text-xs font-medium <?= get_payment_method_badge($sale['payment_method']) ?>">
                        <?= strtoupper($sale['payment_method'] ?? 'CASH') ?>
                    </span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Payment Status</span>
                <span class="detail-value">
                    <span class="px-2 py-1 rounded-full text-xs font-medium <?= get_payment_status_badge($sale['payment_status']) ?>">
                        <?= ucfirst($sale['payment_status'] ?? 'pending') ?>
                    </span>
                </span>
            </div>
            <?php if (!empty($sale['notes'])): ?>
            <div class="detail-row">
                <span class="detail-label">Notes</span>
                <span class="detail-value"><?= htmlspecialchars($sale['notes']) ?></span>
            </div>
            <?php endif; ?>
            <div class="detail-row">
                <span class="detail-label">Sale Date</span>
                <span class="detail-value"><?= format_date($sale['created_at']) ?></span>
            </div>
            <?php if (!empty($sale['updated_at']) && $sale['updated_at'] != $sale['created_at']): ?>
            <div class="detail-row">
                <span class="detail-label">Last Updated</span>
                <span class="detail-value"><?= format_date($sale['updated_at']) ?></span>
            </div>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- Sale Items -->
    <div class="detail-card animate-fade-in-up mt-5" style="animation-delay:0.15s;">
        <div class="card-title">
            <i class="fas fa-list"></i>
            Sale Items
            <span class="badge-count" style="background:#0B5ED7;color:white;padding:1px 10px;border-radius:20px;font-size:0.6rem;font-weight:600;margin-left:auto;"><?= count($sale_items) ?> items</span>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Medicine Name</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($sale_items) > 0): ?>
                        <?php $counter = 1; ?>
                        <?php foreach ($sale_items as $item): ?>
                            <tr>
                                <td><?= $counter++ ?></td>
                                <td><?= htmlspecialchars($item['medicine_name'] ?? 'Unknown') ?></td>
                                <td class="text-right"><?= number_format($item['quantity'] ?? 0) ?></td>
                                <td class="text-right"><?= format_currency($item['unit_price'] ?? 0) ?></td>
                                <td class="text-right"><strong><?= format_currency($item['total_price'] ?? 0) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Totals Row -->
                        <tr style="border-top:2px solid #E2E8F0;">
                            <td colspan="4" class="text-right font-semibold">Subtotal</td>
                            <td class="text-right font-semibold"><?= format_currency($sale['total_amount']) ?></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-right font-semibold" style="color:#EF4444;">Discount</td>
                            <td class="text-right font-semibold" style="color:#EF4444;">- <?= format_currency($sale['discount_amount']) ?></td>
                        </tr>
                        <tr style="border-top:2px solid #0B5ED7;">
                            <td colspan="4" class="text-right font-bold" style="font-size:1.1rem;color:#0B5ED7;">Total</td>
                            <td class="text-right font-bold" style="font-size:1.1rem;color:#0B5ED7;"><?= format_currency($sale['net_amount']) ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-gray-400">
                                <i class="fas fa-box-open text-2xl block mb-2"></i>
                                No items found for this sale
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-2 gap-3 mt-5">
        <a href="view_pharmacy.php?branch=<?= $selected_branch_id ?>" class="quick-action">
            <span class="icon">🏥</span>
            <span class="label">Pharmacy</span>
        </a>
        <a href="otc_sales.php?branch=<?= $selected_branch_id ?>" class="quick-action">
            <span class="icon">📋</span>
            <span class="label">All OTC Sales</span>
        </a>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            OTC Sale #<?= htmlspecialchars($sale['sale_number']) ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
    // ================================================================
    // BRANCH SWITCHER - For sidebar
    // ================================================================
    function switchBranch(branchId) {
        if (branchId === 'all') {
            window.location.href = 'view_pharmacy.php?branch=all';
        } else {
            window.location.href = 'view_pharmacy.php?branch=' + branchId;
        }
    }

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('sidebar');
        var sidebarToggle = document.getElementById('sidebarToggle');
        var overlay = document.getElementById('sidebarOverlay');
        
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'sidebarOverlay';
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:45;display:none;backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);';
            document.body.appendChild(overlay);
        }
        
        function toggleSidebar() {
            var isOpen = sidebar.classList.contains('open');
            if (isOpen) {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                document.body.style.overflow = '';
            } else {
                sidebar.classList.add('open');
                overlay.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }
        
        if (sidebarToggle) {
            sidebarToggle.removeEventListener('click', toggleSidebar);
            sidebarToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleSidebar();
            });
        }
        
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
        
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
    });

    // ================================================================
    // DATE & TIME - Footer
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c🧾 Braick Dispensary - View OTC Sale', 'font-size:18px; font-weight:bold; color:#1A56DB;');
    console.log('%c🧾 Sale: <?= htmlspecialchars($sale['sale_number']) ?>', 'font-size:13px; color:#1A56DB;');
    console.log('%c👤 Customer: <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Amount: <?= format_currency($sale['net_amount']) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c💳 Payment: <?= ucfirst($sale['payment_method'] ?? 'cash') ?> - <?= ucfirst($sale['payment_status'] ?? 'pending') ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c🖼️ Favicon: Ipo kwenye admin_header.php (Braick Logo)', 'font-size:13px; color:#1A56DB;');
    console.log('%c✅ Using SHARED HEADER - admin_header.php', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Using SHARED SIDEBAR - admin_sidebar.php', 'font-size:13px; color:#34D399;');
    console.log('%c🔒 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#059669;');
</script>

<!-- ================================================================ -->
<!-- STYLES ZA KUONGEZEA KWA VIEW OTC SALE -->
<!-- ================================================================ -->
<style>
    .detail-card {
        background: var(--bg-card);
        border-radius: 18px;
        border: 1px solid var(--border-color);
        padding: 20px 24px;
        transition: all 0.3s ease;
        box-shadow: var(--shadow-sm);
    }
    
    .detail-card:hover {
        border-color: #0B5ED7;
        box-shadow: var(--shadow-md);
    }
    
    .detail-card .card-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 12px;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .detail-card .card-title i {
        color: #0B5ED7;
    }
    
    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid var(--border-color);
        font-size: 0.85rem;
    }
    
    .detail-row:last-child {
        border-bottom: none;
    }
    
    .detail-label {
        font-weight: 500;
        color: var(--text-secondary);
    }
    
    .detail-value {
        font-weight: 500;
        color: var(--text-primary);
    }
    
    .table-container {
        overflow-x: auto;
    }
    
    .table-container table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    
    .table-container table thead {
        background: var(--bg-body);
    }
    
    .table-container table th {
        padding: 10px 14px;
        text-align: left;
        font-weight: 600;
        color: var(--text-secondary);
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--border-color);
    }
    
    .table-container table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
    }
    
    .table-container table tr:hover {
        background: var(--table-hover);
    }
    
    .table-container table tr:last-child td {
        border-bottom: none;
    }
    
    .quick-action {
        padding: 16px;
        border-radius: 12px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        display: block;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
    }
    
    .quick-action:hover {
        border-color: #0B5ED7;
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .quick-action .icon {
        font-size: 1.6rem;
        display: block;
        margin-bottom: 4px;
    }
    
    .quick-action .label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .badge-count {
        background: #0B5ED7;
        color: white;
        padding: 1px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        margin-left: auto;
    }
</style>

</body>
</html>