<?php
// ================================================================
// FILE: frontend/pages/cashier/patient_bills.php
// CASHIER - VIEW PAID BILLS FOR A SPECIFIC PATIENT
// DISPLAYS ONLY PAID BILLS WITH "PAID" WATERMARK
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
// GET PATIENT ID FROM URL
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if ($patient_id <= 0) {
    header('Location: patients.php');
    exit;
}

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$db = getDB();

// ================================================================
// GET PATIENT DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, b.name as branch_name 
    FROM patients p
    LEFT JOIN branches b ON p.branch_id = b.id
    WHERE p.id = ?
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: patients.php');
    exit;
}

// ================================================================
// GET ONLY PAID BILLS FOR THIS PATIENT
// ================================================================
$stmt = $db->prepare("
    SELECT 
        pb.*,
        v.visit_number,
        v.visit_type,
        v.visit_date,
        u.full_name as doctor_name,
        u2.full_name as created_by_name,
        (
            SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id
        ) as item_count,
        (
            SELECT SUM(total_price) FROM bill_items WHERE bill_id = pb.id
        ) as items_total
    FROM patient_bills pb
    LEFT JOIN visits v ON pb.visit_id = v.id
    LEFT JOIN users u ON v.doctor_id = u.id
    LEFT JOIN users u2 ON pb.created_by = u2.id
    WHERE pb.patient_id = ? AND pb.branch_id = ? AND pb.status = 'paid'
    ORDER BY pb.updated_at DESC
");
$stmt->execute([$patient_id, $user_branch_id]);
$bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET BILL ITEMS FOR EACH BILL
// ================================================================
$bill_items = [];
foreach ($bills as $bill) {
    $stmt = $db->prepare("
        SELECT * FROM bill_items 
        WHERE bill_id = ? 
        ORDER BY created_at ASC
    ");
    $stmt->execute([$bill['id']]);
    $bill_items[$bill['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// CALCULATE SUMMARY
// ================================================================
$total_bills = count($bills);
$total_amount = 0;
$total_paid = 0;

foreach ($bills as $bill) {
    $total_amount += (float)$bill['total_amount'];
    $total_paid += (float)$bill['paid_amount'];
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
       PATIENT BILLS STYLES
       ================================================================ */
    .patient-profile-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 24px;
        flex-wrap: wrap;
    }
    
    .patient-profile-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }
    
    .patient-avatar-large {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
        box-shadow: 0 4px 14px rgba(0,0,0,0.15);
    }
    
    .patient-info h2 {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }
    
    .patient-info .patient-id {
        font-size: 0.85rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .patient-info .patient-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 4px;
    }
    
    .patient-info .patient-meta span {
        font-size: 0.8rem;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 4px;
        background: var(--bg-body);
        padding: 2px 12px;
        border-radius: 20px;
        border: 1px solid var(--border-color);
    }
    
    .patient-info .patient-meta span i {
        color: var(--primary);
    }
    
    /* Summary Stats */
    .summary-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin-bottom: 24px;
    }
    
    .summary-stat {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 16px;
        border: 2px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .summary-stat:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }
    
    .summary-stat .stat-number {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .summary-stat .stat-number.green {
        color: #059669;
    }
    
    .summary-stat .stat-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        margin-top: 2px;
    }
    
    /* Bill Row with Watermark */
    .bill-row {
        background: var(--bg-card);
        border-radius: 12px;
        border: 2px solid var(--border-color);
        margin-bottom: 16px;
        overflow: hidden;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .bill-row:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.06);
    }
    
    /* ================================================================
       "PAID" WATERMARK - SLASH LINE / STRIKETHROUGH
       ================================================================ */
    .bill-row .watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-30deg);
        font-size: 7rem;
        font-weight: 900;
        color: rgba(5, 150, 105, 0.08);
        letter-spacing: 8px;
        text-transform: uppercase;
        pointer-events: none;
        z-index: 1;
        white-space: nowrap;
        user-select: none;
        font-family: 'Arial Black', 'Impact', sans-serif;
        text-shadow: 0 2px 10px rgba(5, 150, 105, 0.05);
        border: 4px solid rgba(5, 150, 105, 0.10);
        padding: 20px 60px;
        border-radius: 20px;
    }
    
    [data-theme="dark"] .bill-row .watermark {
        color: rgba(52, 211, 153, 0.06);
        border-color: rgba(52, 211, 153, 0.06);
    }
    
    /* Additional watermark style - diagonal slash effect */
    .bill-row .watermark::before {
        content: '';
        position: absolute;
        top: -20px;
        left: -20px;
        right: -20px;
        bottom: -20px;
        background: repeating-linear-gradient(
            45deg,
            transparent,
            transparent 40px,
            rgba(5, 150, 105, 0.02) 40px,
            rgba(5, 150, 105, 0.02) 41px
        );
        border-radius: 20px;
        pointer-events: none;
    }
    
    /* ================================================================
       BILL ROW HEADER
       ================================================================ */
    .bill-row-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 20px;
        background: var(--bg-body);
        border-bottom: 2px solid var(--border-color);
        cursor: pointer;
        transition: background 0.2s ease;
        flex-wrap: wrap;
        gap: 8px;
        position: relative;
        z-index: 2;
    }
    
    .bill-row-header:hover {
        background: var(--table-hover);
    }
    
    .bill-row-header .bill-number {
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--primary);
        font-family: monospace;
    }
    
    .bill-row-header .bill-status {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 4px 14px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #D1FAE5;
        color: #059669;
    }
    
    [data-theme="dark"] .bill-row-header .bill-status {
        background: #1A3A2A;
        color: #34D399;
    }
    
    .bill-row-header .bill-amount {
        font-weight: 700;
        font-size: 1.1rem;
        color: var(--text-primary);
    }
    
    .bill-row-header .bill-amount .amount-paid {
        color: #059669;
    }
    
    .bill-row-body {
        padding: 16px 20px;
        background: var(--bg-card);
        position: relative;
        z-index: 2;
    }
    
    .bill-row-body.collapsed {
        display: none;
    }
    
    .bill-row-body .bill-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 8px 16px;
        margin-bottom: 12px;
    }
    
    .bill-row-body .bill-detail-item {
        display: flex;
        flex-direction: column;
    }
    
    .bill-row-body .bill-detail-item .label {
        font-size: 0.65rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    
    .bill-row-body .bill-detail-item .value {
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--text-primary);
    }
    
    .bill-row-body .bill-detail-item .value.doctor {
        color: var(--primary);
    }
    
    /* Bill Items Table */
    .bill-items-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 12px;
        font-size: 0.82rem;
    }
    
    .bill-items-table thead th {
        text-align: left;
        padding: 6px 10px;
        font-weight: 600;
        font-size: 0.65rem;
        text-transform: uppercase;
        color: var(--text-secondary);
        border-bottom: 2px solid var(--border-color);
        background: var(--bg-body);
    }
    
    .bill-items-table tbody td {
        padding: 6px 10px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
    }
    
    .bill-items-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    .bill-items-table tbody tr:hover {
        background: var(--table-hover);
    }
    
    .bill-items-table .item-total {
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .bill-row-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 20px;
        border-top: 2px solid var(--border-color);
        background: var(--bg-body);
        border-radius: 0 0 12px 12px;
        flex-wrap: wrap;
        gap: 8px;
        position: relative;
        z-index: 2;
    }
    
    .bill-row-footer .total-summary {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .bill-row-footer .total-summary span {
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--text-secondary);
    }
    
    .bill-row-footer .total-summary .strong {
        font-weight: 700;
        color: var(--text-primary);
    }
    
    .bill-row-footer .total-summary .paid {
        color: #059669;
    }
    
    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 12px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.7rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-primary {
        background: #0B5ED7;
        color: white;
    }
    
    .btn-primary:hover {
        background: #0A4CA8;
        transform: scale(1.05);
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
        padding: 3px 8px;
        font-size: 0.65rem;
        border-radius: 4px;
    }
    
    .toggle-icon {
        transition: transform 0.3s ease;
    }
    
    .toggle-icon.expanded {
        transform: rotate(180deg);
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
    
    .empty-state p {
        font-size: 1rem;
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
    
    @media (max-width: 768px) {
        .patient-profile-card {
            padding: 16px 18px;
        }
        .patient-avatar-large {
            width: 56px;
            height: 56px;
            font-size: 1.4rem;
        }
        .patient-info h2 {
            font-size: 1.1rem;
        }
        .bill-row-header {
            padding: 10px 14px;
        }
        .bill-row-body {
            padding: 12px 14px;
        }
        .bill-row-footer {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }
        .bill-row-footer .action-buttons {
            justify-content: center;
        }
        .summary-stats {
            grid-template-columns: repeat(2, 1fr);
        }
        .bill-items-table {
            font-size: 0.7rem;
        }
        .bill-items-table thead th,
        .bill-items-table tbody td {
            padding: 4px 6px;
        }
        .bill-row .watermark {
            font-size: 4rem;
            padding: 15px 30px;
        }
    }
    
    @media (max-width: 480px) {
        .summary-stats {
            grid-template-columns: 1fr;
        }
        .bill-details-grid {
            grid-template-columns: 1fr 1fr !important;
        }
        .bill-row-footer .total-summary {
            flex-direction: column;
            gap: 4px;
        }
        .bill-row-footer .action-buttons .btn {
            width: 100%;
            justify-content: center;
        }
        .bill-row .watermark {
            font-size: 2.5rem;
            padding: 10px 20px;
            transform: translate(-50%, -50%) rotate(-25deg);
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
            <input type="text" id="searchInput" placeholder="Search patients...">
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
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
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
                <i class="fas fa-file-invoice-dollar mr-2" style="color: var(--primary);"></i> Paid Bills
                <span class="role-badge-display ml-2">CASHIER</span>
            </h1>
            <p class="page-subtitle">
                View all paid bills for patient
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-hashtag mr-1"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-check-circle mr-1"></i> <?= $total_bills ?> paid bill(s)
                </span>
            </p>
        </div>
        <div>
            <a href="patients.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>
    </div>

    <!-- Patient Profile -->
    <div class="patient-profile-card mb-5">
        <div class="patient-avatar-large" style="background: <?= '#' . substr(md5($patient['full_name']), 0, 6) ?>;">
            <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
        </div>
        <div class="patient-info">
            <h2><?= htmlspecialchars($patient['full_name']) ?></h2>
            <p class="patient-id"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></p>
            <div class="patient-meta">
                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
                <span><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                <span><i class="fas fa-store-alt"></i> <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="summary-stats">
        <div class="summary-stat">
            <p class="stat-number green"><?= $total_bills ?></p>
            <p class="stat-label">Paid Bills</p>
        </div>
        <div class="summary-stat">
            <p class="stat-number green"><?= number_format($total_paid, 2) ?></p>
            <p class="stat-label">Total Paid</p>
        </div>
        <div class="summary-stat">
            <p class="stat-number purple"><?= number_format($total_amount, 2) ?></p>
            <p class="stat-label">Total Amount</p>
        </div>
        <div class="summary-stat">
            <p class="stat-number" style="color: #059669;">✅ All Paid</p>
            <p class="stat-label">Status</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILLS LIST - ONLY PAID BILLS WITH WATERMARK -->
    <!-- ================================================================ -->
    <?php if (count($bills) > 0): ?>
        
        <?php foreach ($bills as $bill): 
            $items = $bill_items[$bill['id']] ?? [];
        ?>
        <div class="bill-row">
            <!-- PAID WATERMARK - Large strikethrough text -->
            <div class="watermark">✅ PAID</div>
            
            <!-- Bill Header - Click to toggle details -->
            <div class="bill-row-header" onclick="toggleBill(<?= $bill['id'] ?>)">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="bill-number">#<?= htmlspecialchars($bill['bill_number']) ?></span>
                    <span class="bill-status">
                        ✅ Paid
                    </span>
                    <?php if ($bill['item_count'] > 0): ?>
                        <span class="text-xs text-gray-400">(<?= $bill['item_count'] ?> items)</span>
                    <?php endif; ?>
                    <?php if ($bill['visit_number']): ?>
                        <span class="text-xs text-gray-400 font-mono">Visit: <?= htmlspecialchars($bill['visit_number']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-4 flex-wrap">
                    <span class="bill-amount">
                        <span class="amount-paid"><?= number_format($bill['total_amount'] ?? 0, 2) ?></span>
                        <span class="text-xs text-green-600 ml-1">✅ Paid</span>
                    </span>
                    <i class="fas fa-chevron-down toggle-icon" id="toggleIcon_<?= $bill['id'] ?>"></i>
                </div>
            </div>
            
            <!-- Bill Body - Collapsible -->
            <div class="bill-row-body collapsed" id="billBody_<?= $bill['id'] ?>">
                <!-- Bill Details -->
                <div class="bill-details-grid">
                    <div class="bill-detail-item">
                        <span class="label">Visit Number</span>
                        <span class="value"><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></span>
                    </div>
                    <div class="bill-detail-item">
                        <span class="label">Visit Type</span>
                        <span class="value capitalize"><?= htmlspecialchars($bill['visit_type'] ?? 'N/A') ?></span>
                    </div>
                    <div class="bill-detail-item">
                        <span class="label">Visit Date</span>
                        <span class="value"><?= $bill['visit_date'] ? date('M d, Y', strtotime($bill['visit_date'])) : 'N/A' ?></span>
                    </div>
                    <div class="bill-detail-item">
                        <span class="label">Doctor</span>
                        <span class="value doctor">Dr. <?= htmlspecialchars($bill['doctor_name'] ?? 'Not assigned') ?></span>
                    </div>
                    <div class="bill-detail-item">
                        <span class="label">Created By</span>
                        <span class="value"><?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?></span>
                    </div>
                    <div class="bill-detail-item">
                        <span class="label">Paid At</span>
                        <span class="value"><?= date('M d, Y h:i A', strtotime($bill['updated_at'])) ?></span>
                    </div>
                </div>
                
                <!-- Bill Items Table -->
                <?php if (count($items) > 0): ?>
                    <table class="bill-items-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th>Type</th>
                                <th style="text-align:right;">Qty</th>
                                <th style="text-align:right;">Unit Price</th>
                                <th style="text-align:right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; foreach ($items as $item): ?>
                                <tr>
                                    <td><?= $counter++ ?></td>
                                    <td><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></td>
                                    <td><span class="text-xs capitalize"><?= htmlspecialchars($item['item_type'] ?? 'N/A') ?></span></td>
                                    <td style="text-align:right;"><?= $item['quantity'] ?? 1 ?></td>
                                    <td style="text-align:right;"><?= number_format($item['unit_price'] ?? 0, 2) ?></td>
                                    <td style="text-align:right;" class="item-total"><?= number_format($item['total_price'] ?? 0, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="text-center py-2 text-gray-400 text-sm">
                        <i class="fas fa-info-circle mr-1"></i> No items in this bill
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Bill Footer -->
            <div class="bill-row-footer">
                <div class="total-summary">
                    <span>Subtotal: <span class="strong"><?= number_format($bill['subtotal'] ?? 0, 2) ?></span></span>
                    <?php if (($bill['discount_amount'] ?? 0) > 0): ?>
                        <span>Discount: <span class="strong" style="color:#D97706;">-<?= number_format($bill['discount_amount'], 2) ?></span></span>
                    <?php endif; ?>
                    <span>Total: <span class="strong"><?= number_format($bill['total_amount'] ?? 0, 2) ?></span></span>
                    <span>✅ <span class="paid">Fully Paid</span></span>
                </div>
                <div class="action-buttons">
                    <a href="view_bill.php?id=<?= $bill['id'] ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <a href="print_receipt.php?bill_id=<?= $bill['id'] ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-print"></i> Receipt
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-invoice"></i>
            <p>No paid bills found for this patient</p>
            <p class="text-sm text-gray-400 mt-2">The patient has no paid bills yet</p>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
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
    // TOGGLE BILL DETAILS
    // ================================================================
    function toggleBill(billId) {
        var body = document.getElementById('billBody_' + billId);
        var icon = document.getElementById('toggleIcon_' + billId);
        
        if (body) {
            if (body.classList.contains('collapsed')) {
                body.classList.remove('collapsed');
                if (icon) icon.classList.add('expanded');
            } else {
                body.classList.add('collapsed');
                if (icon) icon.classList.remove('expanded');
            }
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
    // INIT - Expand first bill by default
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var firstBillBody = document.querySelector('.bill-row-body');
        var firstIcon = document.querySelector('.toggle-icon');
        if (firstBillBody && firstIcon) {
            firstBillBody.classList.remove('collapsed');
            firstIcon.classList.add('expanded');
        }
    });

    console.log('%c💰 Braick - Paid Bills (With Watermark)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Paid Bills: <?= $total_bills ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Total Paid: <?= number_format($total_paid, 2) ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Each bill has a "PAID" watermark with slash/strikethrough effect', 'font-size:12px; color:#34D399;');
</script>

</body>
</html>