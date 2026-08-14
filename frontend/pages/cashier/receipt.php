<?php
// ================================================================
// FILE: frontend/pages/cashier/receipt.php
// RECEIPT - VIEW AND PRINT RECEIPT
// WITH BRAICK LOGO AND DETAILS - IMPROVED DESIGN
// ALLOWS RECEPTION, CASHIER AND ADMIN
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
    header('Location: ../login.php');
    exit;
}

// ================================================================
// ALLOWED ROLES: Cashier, Reception, Admin
// ================================================================
$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Cashier';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

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
        $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ? ORDER BY id");
        $stmt->execute([$receipt_data['bill_id']]);
        $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '';

$default_letter = strtoupper(substr($user_full_name, 0, 1));

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/cashier_header.php';
include_once __DIR__ . '/../../components/cashier_sidebar.php';
?>

<style>
    /* ================================================================
       RECEIPT STYLES - IMPROVED DESIGN
       ================================================================ */
    
    .receipt-wrapper {
        max-width: 900px;
        margin: 0 auto;
        background: var(--bg-card);
        border-radius: 20px;
        border: 1px solid var(--border-color);
        overflow: hidden;
        box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        transition: all 0.3s ease;
    }
    
    .receipt-wrapper:hover {
        box-shadow: 0 8px 40px rgba(5, 150, 105, 0.1);
    }
    
    /* ===== RECEIPT HEADER ===== */
    .receipt-header {
        background: linear-gradient(135deg, #065F46, #047857);
        color: white;
        padding: 28px 36px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
        position: relative;
        overflow: hidden;
    }
    
    .receipt-header::after {
        content: '';
        position: absolute;
        top: -60px;
        right: -60px;
        width: 200px;
        height: 200px;
        background: rgba(255,255,255,0.04);
        border-radius: 50%;
    }
    
    .receipt-header::before {
        content: '';
        position: absolute;
        bottom: -80px;
        left: -80px;
        width: 250px;
        height: 250px;
        background: rgba(255,255,255,0.03);
        border-radius: 50%;
    }
    
    [data-theme="dark"] .receipt-header {
        background: linear-gradient(135deg, #064E3B, #047857);
    }
    
    .receipt-header .logo-area {
        display: flex;
        align-items: center;
        gap: 18px;
        position: relative;
        z-index: 1;
    }
    
    .receipt-header .logo-area .logo-img {
        width: 68px;
        height: 68px;
        border-radius: 14px;
        background: white;
        padding: 6px;
        object-fit: cover;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }
    
    .receipt-header .logo-area .brand-name {
        font-size: 1.6rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }
    
    .receipt-header .logo-area .brand-sub {
        font-size: 0.7rem;
        opacity: 0.8;
        font-weight: 400;
        letter-spacing: 0.03em;
    }
    
    .receipt-header .logo-area .brand-tag {
        display: inline-block;
        background: rgba(255,255,255,0.15);
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.55rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-top: 2px;
        border: 1px solid rgba(255,255,255,0.08);
    }
    
    .receipt-header .receipt-number {
        text-align: right;
        position: relative;
        z-index: 1;
    }
    
    .receipt-header .receipt-number .label {
        font-size: 0.6rem;
        opacity: 0.6;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 500;
    }
    
    .receipt-header .receipt-number .number {
        font-size: 1.3rem;
        font-weight: 700;
        font-family: 'Courier New', monospace;
        letter-spacing: 0.02em;
        background: rgba(255,255,255,0.1);
        padding: 2px 14px;
        border-radius: 8px;
        display: inline-block;
        margin-top: 2px;
    }
    
    .receipt-header .receipt-number .date-badge {
        font-size: 0.65rem;
        opacity: 0.7;
        margin-top: 6px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
    }
    
    .receipt-header .receipt-number .date-badge i {
        font-size: 0.6rem;
        opacity: 0.5;
    }
    
    /* ===== RECEIPT BODY ===== */
    .receipt-body {
        padding: 28px 36px;
    }
    
    /* Business Details */
    .business-details {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px 20px;
        padding-bottom: 18px;
        margin-bottom: 18px;
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
        letter-spacing: 0.06em;
        font-weight: 600;
        margin-bottom: 2px;
    }
    
    .business-details .detail-item .value {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.85rem;
    }
    
    /* Patient Details */
    .patient-details {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr;
        gap: 12px 20px;
        padding: 16px 20px;
        background: var(--bg-body);
        border-radius: 12px;
        margin-bottom: 22px;
        font-size: 0.85rem;
        border: 1px solid var(--border-color);
    }
    
    .patient-details .detail-item .label {
        font-size: 0.6rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 600;
        margin-bottom: 2px;
    }
    
    .patient-details .detail-item .value {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.9rem;
    }
    
    .patient-details .detail-item .value .patient-badge {
        display: inline-block;
        background: var(--primary-bg);
        color: var(--primary);
        padding: 1px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        margin-left: 6px;
    }
    
    [data-theme="dark"] .patient-details .detail-item .value .patient-badge {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    /* ===== TABLE ===== */
    .receipt-table-wrap {
        overflow-x: auto;
        margin-bottom: 18px;
        border-radius: 12px;
        border: 1px solid var(--border-color);
    }
    
    .receipt-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    
    .receipt-table thead th {
        text-align: left;
        padding: 12px 16px;
        background: var(--bg-body);
        color: var(--text-secondary);
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 700;
        border-bottom: 2px solid var(--border-color);
    }
    
    .receipt-table thead th:last-child {
        text-align: right;
    }
    
    .receipt-table tbody td {
        padding: 10px 16px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        font-size: 0.85rem;
    }
    
    .receipt-table tbody td:last-child {
        text-align: right;
        font-weight: 600;
        font-family: 'Courier New', monospace;
    }
    
    .receipt-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    .receipt-table tbody tr:hover td {
        background: var(--table-hover);
    }
    
    .receipt-table .item-name {
        font-weight: 500;
    }
    
    .receipt-table .item-type {
        font-size: 0.6rem;
        color: var(--text-secondary);
        display: block;
        margin-top: 1px;
    }
    
    .receipt-table .text-right {
        text-align: right;
    }
    
    .receipt-table .font-mono {
        font-family: 'Courier New', monospace;
    }
    
    /* ===== TOTALS ===== */
    .totals-section {
        display: flex;
        justify-content: flex-end;
        padding-top: 18px;
        border-top: 2px solid var(--border-color);
    }
    
    .totals-box {
        width: 340px;
    }
    
    .totals-box .total-row {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        font-size: 0.9rem;
        align-items: center;
    }
    
    .totals-box .total-row .label {
        color: var(--text-secondary);
        font-weight: 500;
        font-size: 0.85rem;
    }
    
    .totals-box .total-row .value {
        font-weight: 600;
        color: var(--text-primary);
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
    }
    
    .totals-box .total-row .value .currency {
        font-size: 0.75rem;
        color: var(--text-secondary);
        font-weight: 400;
        margin-right: 2px;
    }
    
    .totals-box .total-row.grand-total {
        border-top: 2px solid var(--border-color);
        padding-top: 10px;
        margin-top: 6px;
        font-size: 1.1rem;
    }
    
    .totals-box .total-row.grand-total .label {
        font-weight: 700;
        font-size: 1rem;
        color: var(--text-primary);
    }
    
    .totals-box .total-row.grand-total .value {
        color: var(--success);
        font-weight: 700;
        font-size: 1.2rem;
    }
    
    .totals-box .total-row.balance .value {
        color: var(--danger);
        font-weight: 700;
    }
    
    /* ===== STATUS BADGE ===== */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 16px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .status-badge i {
        font-size: 0.4rem;
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
    
    /* ===== PAYMENT METHOD ===== */
    .payment-method-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 500;
        background: var(--bg-body);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
    }
    
    /* ===== RECEIPT FOOTER ===== */
    .receipt-footer {
        padding: 20px 36px;
        background: var(--bg-body);
        border-top: 2px solid var(--border-color);
        text-align: center;
    }
    
    .receipt-footer .thank-you {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--success);
        margin-bottom: 4px;
        letter-spacing: -0.01em;
    }
    
    .receipt-footer .thank-you i {
        margin-right: 8px;
        font-size: 1rem;
        opacity: 0.7;
    }
    
    .receipt-footer .footer-text {
        font-size: 0.75rem;
        color: var(--text-secondary);
        opacity: 0.7;
    }
    
    .receipt-footer .footer-text strong {
        color: var(--text-primary);
        opacity: 1;
    }
    
    .receipt-footer .footer-copy {
        font-size: 0.6rem;
        color: var(--text-secondary);
        opacity: 0.4;
        margin-top: 6px;
        letter-spacing: 0.02em;
    }
    
    /* ===== CASHIER INFO ===== */
    .cashier-info {
        margin-top: 18px;
        padding-top: 14px;
        border-top: 1px solid var(--border-color);
        font-size: 0.75rem;
        color: var(--text-secondary);
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .cashier-info span {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .cashier-info i {
        opacity: 0.4;
        font-size: 0.7rem;
    }
    
    .cashier-info strong {
        color: var(--text-primary);
        font-weight: 600;
    }
    
    /* ================================================================
       PRINT STYLES
       ================================================================ */
    @media print {
        /* Hide navigation, sidebar, buttons */
        .top-nav, .sidebar, .no-print, .btn, 
        #sidebarToggle, #darkModeToggle, .page-header .btn,
        .footer, .dark-toggle-btn, .icon-btn, .search-wrapper {
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
            max-width: 100% !important;
        }
        
        .receipt-wrapper:hover {
            box-shadow: none !important;
        }
        
        .receipt-header {
            background: #065F46 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            padding: 20px 30px !important;
        }
        
        [data-theme="dark"] .receipt-header {
            background: #064E3B !important;
        }
        
        .receipt-header .logo-area .logo-img {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .receipt-body {
            padding: 20px 30px !important;
        }
        
        .receipt-footer {
            background: #F8FAFC !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            padding: 16px 30px !important;
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
        
        [data-theme="dark"] .status-badge.completed {
            background: #1A3A2A !important;
            color: #34D399 !important;
        }
        
        .payment-method-badge {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        /* Page break control */
        .receipt-wrapper {
            page-break-inside: avoid;
        }
        
        .receipt-table-wrap {
            page-break-inside: avoid;
        }
    }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .receipt-header {
            flex-direction: column;
            text-align: center;
            padding: 20px 24px;
        }
        
        .receipt-header .logo-area {
            flex-direction: column;
            text-align: center;
        }
        
        .receipt-header .receipt-number {
            text-align: center;
        }
        
        .receipt-header .receipt-number .date-badge {
            justify-content: center;
        }
        
        .business-details {
            grid-template-columns: 1fr 1fr;
            gap: 8px 16px;
        }
        
        .patient-details {
            grid-template-columns: 1fr;
            gap: 8px;
        }
        
        .totals-box {
            width: 100%;
        }
        
        .receipt-body {
            padding: 16px 20px;
        }
        
        .receipt-footer {
            padding: 16px 20px;
        }
        
        .receipt-table thead th,
        .receipt-table tbody td {
            padding: 8px 12px;
            font-size: 0.75rem;
        }
        
        .cashier-info {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
    }
    
    @media (max-width: 480px) {
        .business-details {
            grid-template-columns: 1fr;
        }
        
        .receipt-header .logo-area .brand-name {
            font-size: 1.3rem;
        }
        
        .receipt-header .logo-area .logo-img {
            width: 56px;
            height: 56px;
        }
        
        .receipt-header .receipt-number .number {
            font-size: 1rem;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5" style="background:linear-gradient(135deg, var(--success), var(--success-dark));border-radius:16px;padding:20px 24px;margin-bottom:24px;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:16px;box-shadow:0 4px 20px rgba(5,150,105,0.25);position:relative;overflow:hidden;">
        <div style="position:relative;z-index:1;">
            <h1 class="page-title" style="font-size:1.6rem; font-weight:700; color:white;display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0;">
                <i class="fas fa-receipt" style="color:rgba(255,255,255,0.9);"></i> 
                Receipt
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;padding:4px 14px;border-radius:20px;font-size:0.65rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;backdrop-filter:blur(4px);"><?= strtoupper($user_role) ?></span>
                <?php if ($is_reception): ?>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;border-color:rgba(52,211,153,0.3);font-size:0.6rem;">
                        <i class="fas fa-check-circle"></i> Full Access
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle" style="color:rgba(255,255,255,0.85);font-size:0.9rem;display:flex;align-items:center;gap:8px;flex-wrap:wrap;position:relative;z-index:1;margin:0;">
                Payment receipt details
                <span class="branch-tag" style="background:rgba(255,255,255,0.15);color:white;padding:2px 14px;border-radius:20px;font-size:0.7rem;font-weight:600;border:1px solid rgba(255,255,255,0.1);backdrop-filter:blur(4px);">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <?php if ($is_reception): ?>
                    <span class="header-badge" style="background:rgba(52,211,153,0.2);color:#34D399;border-color:rgba(52,211,153,0.2);padding:2px 12px;border-radius:16px;font-size:0.6rem;">
                        <i class="fas fa-user-tag"></i> Reception Access
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap no-print" style="position:relative;z-index:1;">
            <button onclick="window.print()" class="btn btn-primary" style="background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.2);padding:8px 18px;border-radius:10px;font-weight:500;font-size:0.82rem;transition:all 0.3s;text-decoration:none;display:inline-flex;align-items:center;gap:8px;backdrop-filter:blur(4px);cursor:pointer;">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <a href="payment_history.php" class="btn btn-outline" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.15);padding:8px 18px;border-radius:10px;font-weight:500;font-size:0.82rem;transition:all 0.3s;text-decoration:none;display:inline-flex;align-items:center;gap:8px;backdrop-filter:blur(4px);">
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
                     class="logo-img"
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2268%22 height=%2268%22%3E%3Crect width=%2268%22 height=%2268%22 fill=%22%23065F46%22 rx=%2214%22/%3E%3Ctext x=%2234%22 y=%2244%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2230%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
                <div>
                    <div class="brand-name">Braick Dispensary</div>
                    <div class="brand-sub">Quality Healthcare Services</div>
                    <div class="brand-tag">Est. 2024</div>
                </div>
            </div>
            <div class="receipt-number">
                <div class="label">Receipt Number</div>
                <div class="number">#<?= htmlspecialchars($receipt_data['receipt_number'] ?? $receipt_data['payment_number'] ?? 'N/A') ?></div>
                <div class="date-badge">
                    <i class="fas fa-calendar-alt"></i>
                    <?= date('d M Y', strtotime($receipt_data['received_at'] ?? $receipt_data['payment_date'] ?? 'now')) ?>
                    <i class="fas fa-clock ml-1"></i>
                    <?= date('h:i A', strtotime($receipt_data['received_at'] ?? $receipt_data['payment_date'] ?? 'now')) ?>
                </div>
            </div>
        </div>
        
        <!-- Receipt Body -->
        <div class="receipt-body">
            
            <!-- Business Details -->
            <div class="business-details">
                <div class="detail-item">
                    <span class="label"><i class="fas fa-store-alt"></i> Branch</span>
                    <span class="value"><?= htmlspecialchars($receipt_data['branch_name'] ?? 'Braick Dispensary') ?></span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-map-marker-alt"></i> Location</span>
                    <span class="value"><?= htmlspecialchars($receipt_data['branch_location'] ?? 'Dodoma, Tanzania') ?></span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="value"><?= htmlspecialchars($receipt_data['branch_phone'] ?? '+255 759 154 160') ?></span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-envelope"></i> Email</span>
                    <span class="value"><?= htmlspecialchars($receipt_data['branch_email'] ?? 'info@braick.com') ?></span>
                </div>
            </div>
            
            <!-- Patient Details -->
            <div class="patient-details">
                <div class="detail-item">
                    <span class="label"><i class="fas fa-user"></i> Patient Name</span>
                    <span class="value">
                        <?= htmlspecialchars($receipt_data['patient_name'] ?? 'Unknown') ?>
                        <span class="patient-badge">ID: <?= htmlspecialchars($receipt_data['patient_code'] ?? 'N/A') ?></span>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-file-invoice"></i> Bill Number</span>
                    <span class="value"><?= htmlspecialchars($receipt_data['bill_number'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="value"><?= htmlspecialchars($receipt_data['patient_phone'] ?? 'N/A') ?></span>
                </div>
            </div>
            
            <!-- Bill Items Table -->
            <div class="receipt-table-wrap">
                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th style="width:45%;">Description</th>
                            <th class="text-right" style="width:15%;">Qty</th>
                            <th class="text-right" style="width:20%;">Unit Price</th>
                            <th class="text-right" style="width:20%;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bill_items) > 0): ?>
                            <?php foreach ($bill_items as $item): ?>
                                <tr>
                                    <td>
                                        <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                                        <span class="item-type"><?= ucfirst($item['item_type'] ?? 'other') ?></span>
                                    </td>
                                    <td class="text-right"><?= $item['quantity'] ?></td>
                                    <td class="text-right">TSh <?= number_format($item['unit_price']) ?></td>
                                    <td class="text-right font-mono">TSh <?= number_format($item['total_price']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center" style="padding:24px; color:var(--text-secondary); opacity:0.6;">
                                    <i class="fas fa-box-open" style="font-size:1.2rem; display:block; margin-bottom:6px;"></i>
                                    No items found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Totals -->
            <div class="totals-section">
                <div class="totals-box">
                    <div class="total-row">
                        <span class="label">Subtotal</span>
                        <span class="value"><span class="currency">TSh</span> <?= number_format($receipt_data['bill_total'] ?? 0) ?></span>
                    </div>
                    <div class="total-row">
                        <span class="label">Amount Paid</span>
                        <span class="value" style="color:var(--success);">
                            <span class="currency">TSh</span> <?= number_format($receipt_data['amount'] ?? 0) ?>
                        </span>
                    </div>
                    <div class="total-row">
                        <span class="label">Payment Method</span>
                        <span class="value">
                            <span class="payment-method-badge">
                                <i class="fas <?= $receipt_data['payment_method'] === 'cash' ? 'fa-money-bill-wave' : ($receipt_data['payment_method'] === 'm-pesa' ? 'fa-mobile-alt' : 'fa-credit-card') ?>"></i>
                                <?= ucfirst(str_replace('-', ' ', $receipt_data['payment_method'] ?? 'cash')) ?>
                            </span>
                        </span>
                    </div>
                    <div class="total-row">
                        <span class="label">Status</span>
                        <span class="value">
                            <span class="status-badge <?= $receipt_data['bill_status'] ?? 'completed' ?>">
                                <i class="fas fa-circle"></i>
                                <?= ucfirst($receipt_data['bill_status'] ?? 'Completed') ?>
                            </span>
                        </span>
                    </div>
                    
                    <?php if (($receipt_data['bill_balance'] ?? 0) > 0): ?>
                        <div class="total-row balance" style="border-top:1px solid var(--border-color); padding-top:8px; margin-top:4px;">
                            <span class="label" style="font-weight:600;">Remaining Balance</span>
                            <span class="value"><span class="currency">TSh</span> <?= number_format($receipt_data['bill_balance'] ?? 0) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="total-row grand-total">
                        <span class="label">Total Paid</span>
                        <span class="value"><span class="currency">TSh</span> <?= number_format($receipt_data['amount'] ?? 0) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Cashier Info -->
            <div class="cashier-info">
                <span>
                    <i class="fas fa-user-check"></i>
                    <strong>Cashier:</strong> <?= htmlspecialchars($receipt_data['cashier_name'] ?? $user_full_name) ?>
                </span>
                <span>
                    <i class="fas fa-calendar-check"></i>
                    <strong>Date:</strong> <?= date('d/m/Y h:i A', strtotime($receipt_data['received_at'] ?? $receipt_data['payment_date'] ?? 'now')) ?>
                </span>
                <span>
                    <i class="fas fa-fingerprint"></i>
                    <strong>Transaction ID:</strong> #<?= $payment_id ?>
                </span>
            </div>
        </div>
        
        <!-- Receipt Footer -->
        <div class="receipt-footer">
            <div class="thank-you">
                <i class="fas fa-heart" style="color:var(--danger); opacity:0.6;"></i>
                Thank You for Choosing Braick Dispensary
            </div>
            <div class="footer-text">
                This is a computer generated receipt. For any inquiries, please contact us at 
                <strong><?= htmlspecialchars($receipt_data['branch_phone'] ?? '+255 759 154 160') ?></strong>
            </div>
            <div class="footer-copy">
                <?= date('Y') ?> &copy; Braick Dispensary - All rights reserved
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer mt-5" style="padding:12px 0;border-top:1px solid var(--border-color);margin-top:20px;text-align:center;font-size:0.65rem;color:var(--text-secondary);">
        <p>
            <span class="footer-brand" style="color:var(--success); font-weight:600;">Braick Dispensary</span> 
            <span style="color:var(--text-secondary); opacity:0.3; margin:0 6px;">|</span>
            Receipt
            <span style="color:var(--text-secondary); opacity:0.3; margin:0 6px;">|</span>
            <span class="text-gray-400">👤 <?= htmlspecialchars($user_full_name) ?></span>
            <?php if ($is_reception): ?>
                <span style="color:#34D399;font-size:0.5rem;margin-left:4px;">👀 Reception</span>
            <?php endif; ?>
            <span style="color:var(--text-secondary); opacity:0.3; margin:0 6px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none; position:fixed; bottom:24px; right:24px; padding:14px 20px; border-radius:12px; z-index:999; max-width:380px; transform:translateY(100px); opacity:0; transition:all 0.4s cubic-bezier(0.4,0,0.2,1); display:flex; align-items:center; gap:12px; color:white; box-shadow:0 10px 40px rgba(0,0,0,0.15);">
    <i class="fas fa-info-circle" style="font-size:1.2rem;"></i>
    <div>
        <p style="font-weight:600; font-size:0.9rem; margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.8rem; opacity:0.9; margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE - Controlled by header
    // ================================================================
    
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
        
        toast.className = 'toast-custom ' + (type || 'info');
        toastTitle.textContent = title || 'Notification';
        toastMessage.textContent = message || '';
        toast.style.display = 'flex';
        
        setTimeout(function() {
            toast.classList.add('show');
        }, 50);
        
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
        
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('open');
                }
            }
        });
    }

    console.log('%c🧾 Braick - Receipt (Improved Design)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Reception access: <?= $is_reception ? 'YES' : 'NO' ?>', 'font-size:13px; color:#34D399;');
    console.log('%c📋 Payment #: <?= htmlspecialchars($receipt_data['receipt_number'] ?? $receipt_data['payment_number'] ?? 'N/A') ?>', 'font-size:13px; color:#34D399;');
    console.log('%c👤 Patient: <?= htmlspecialchars($receipt_data['patient_name'] ?? 'Unknown') ?>', 'font-size:13px; color:#6EE7B7;');
    console.log('%c💰 Amount: TSh <?= number_format($receipt_data['amount'] ?? 0) ?>', 'font-size:13px; color:#6EE7B7;');
    console.log('%c🎨 Improved design with better fonts and layout', 'font-size:13px; color:#8B5CF6;');
    console.log('%c🖨️ Click Print to print receipt', 'font-size:13px; color:#6EE7B7;');
</script>

</body>
</html>