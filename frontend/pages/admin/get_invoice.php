<?php
// ================================================================
// FILE: frontend/pages/admin/get_invoice.php
// ADMIN - GET INVOICE HTML FOR PDF VIEW
// ✅ BLUE THEME
// ✅ Opens in new window / tab
// ✅ Logo ya Braick inaonekana
// ✅ Print + Close buttons
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

$allowed_roles = ['admin', 'pharmacy'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

// ================================================================
// GET PURCHASE ID
// ================================================================
$purchase_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($purchase_id <= 0) {
    http_response_code(400);
    echo "Invalid purchase ID";
    exit;
}

// ================================================================
// DATABASE CONNECTION
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo "Database error";
    exit;
}

// ================================================================
// GET PURCHASE DATA
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, u.full_name as creator_name, a.full_name as cancelled_by_name
    FROM purchases p
    LEFT JOIN users u ON p.created_by = u.id
    LEFT JOIN users a ON p.cancelled_by = a.id
    WHERE p.id = ?
");
$stmt->execute([$purchase_id]);
$purchase = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$purchase) {
    http_response_code(404);
    echo "Purchase not found";
    exit;
}

// ================================================================
// GET PURCHASE ITEMS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        pi.*,
        CASE 
            WHEN pi.item_type = 'medicine' THEN mi.medication_name 
            WHEN pi.item_type = 'equipment' THEN eq.equipment_name 
        END as item_name,
        CASE 
            WHEN pi.item_type = 'medicine' THEN mi.category 
            WHEN pi.item_type = 'equipment' THEN eq.category 
        END as category,
        CASE 
            WHEN pi.item_type = 'medicine' THEN mi.unit 
            WHEN pi.item_type = 'equipment' THEN eq.unit 
        END as unit,
        CASE 
            WHEN pi.item_type = 'medicine' THEN mi.batch_number 
            WHEN pi.item_type = 'equipment' THEN eq.batch_number 
        END as batch_number,
        u.full_name as added_by_full_name
    FROM purchase_items pi
    LEFT JOIN medications_inventory mi ON pi.item_id = mi.id AND pi.item_type = 'medicine'
    LEFT JOIN medical_equipment eq ON pi.item_id = eq.id AND pi.item_type = 'equipment'
    LEFT JOIN users u ON pi.added_by = u.id
    WHERE pi.purchase_id = ?
    ORDER BY pi.added_at DESC
");
$stmt->execute([$purchase_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// LOGO PATH - MULTIPLE FALLBACKS
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_alternatives = [
    '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG',
    '/dispensary_system/frontend/assets/uploads/profiles/logo.png',
    '/dispensary_system/frontend/assets/uploads/profiles/logo.jpg',
];
foreach ($logo_alternatives as $alt) {
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . $alt)) {
        $logo_path = $alt;
        break;
    }
}

$logo_fallback = 'data:image/svg+xml,' . urlencode('<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80"><rect width="80" height="80" rx="12" fill="#0B5ED7"/><text x="40" y="52" text-anchor="middle" fill="white" font-size="36" font-weight="bold" font-family="Arial">B</text></svg>');

// ================================================================
// COMPUTE SUMMARY
// ================================================================
$profit = ($purchase['total_selling_value'] ?? 0) - ($purchase['total_buying_cost'] ?? 0);
$profit_percent = $purchase['total_buying_cost'] > 0 ? round(($profit / $purchase['total_buying_cost']) * 100, 1) : 0;

$added_by_names = [];
foreach ($items as $item) {
    if (!empty($item['added_by_full_name'])) {
        $added_by_names[] = $item['added_by_full_name'];
    }
}
$unique_names = array_unique($added_by_names);

function fmt($amount) {
    return number_format((float)$amount, 0, '.', ',');
}

// Branch name
$branch_name = '';
if (!empty($purchase['branch_id'])) {
    $stmt = $db->prepare("SELECT name, location, phone FROM branches WHERE id = ?");
    $stmt->execute([$purchase['branch_id']]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch) {
        $branch_name = $branch['name'];
    }
}

// Admin contact
$admin_phone = '';
try {
    $stmt = $db->prepare("SELECT phone FROM users WHERE role = 'admin' AND branch_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$purchase['branch_id'] ?? 1]);
    $admin_phone = $stmt->fetchColumn() ?: '';
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice - Braick Dispensary</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ================================================================
           BLUE THEME — PRINT OPTIMIZED
           ================================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #F1F5F9;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            color: #1E293B;
        }
        
        .invoice-container {
            max-width: 900px;
            width: 100%;
            background: #FFFFFF;
            border-radius: 12px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.12);
            padding: 30px 35px;
            position: relative;
        }
        
        /* ================================================================
           PRINT BUTTONS
           ================================================================ */
        .print-btn-wrapper {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        
        .print-btn {
            background: #0B5ED7;
            color: white;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .print-btn:hover {
            background: #0A4CA8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .print-btn.danger {
            background: #64748B;
        }
        
        .print-btn.danger:hover {
            background: #475569;
        }
        
        /* ================================================================
           HEADER WITH LOGO
           ================================================================ */
        .invoice-header {
            text-align: center;
            border-bottom: 3px double #0B5ED7;
            padding-bottom: 18px;
            margin-bottom: 22px;
        }
        
        .invoice-header .logo {
            max-height: 70px;
            width: auto;
            display: block;
            margin: 0 auto 10px;
        }
        
        .invoice-header h1 {
            font-size: 24px;
            color: #0B5ED7;
            margin: 0;
            letter-spacing: 1px;
        }
        
        .invoice-header .slogan {
            font-size: 11px;
            color: #059669;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 2px;
        }
        
        .invoice-header p {
            font-size: 12px;
            color: #64748B;
            margin: 2px 0;
        }
        
        .invoice-header .branch-name {
            font-size: 13px;
            font-weight: 600;
            color: #0B5ED7;
            margin-top: 6px;
        }
        
        /* ================================================================
           INVOICE TITLE
           ================================================================ */
        .invoice-title {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .invoice-title h2 {
            font-size: 20px;
            color: #0B5ED7;
            margin: 0;
            letter-spacing: 1px;
        }
        
        .invoice-title p {
            font-size: 12px;
            color: #64748B;
            margin: 4px 0;
        }
        
        .invoice-title p span {
            margin-left: 16px;
            display: inline-block;
        }
        
        .status-completed { color: #0B5ED7; font-weight: 700; }
        .status-in-progress { color: #1A73E8; font-weight: 700; }
        .status-cancelled { color: #64748B; font-weight: 700; }
        
        /* ================================================================
           INVOICE INFO GRID
           ================================================================ */
        .invoice-info {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            padding: 14px 16px;
            background: #E8F0FE;
            border-radius: 10px;
            border: 1px solid #BFDBFE;
            margin-bottom: 20px;
        }
        
        .invoice-info .info-item .label {
            font-size: 10px;
            color: #64748B;
            margin: 0;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .invoice-info .info-item .value {
            font-size: 14px;
            font-weight: 600;
            margin: 2px 0;
            color: #1E293B;
        }
        
        .invoice-info .info-item .value .status-badge {
            font-size: 11px;
            padding: 3px 12px;
            border-radius: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: white;
        }
        
        .invoice-info .info-item .value .status-badge.completed {
            background: #0B5ED7;
        }
        
        .invoice-info .info-item .value .status-badge.in_progress,
        .invoice-info .info-item .value .status-badge.in-progress {
            background: #1A73E8;
        }
        
        .invoice-info .info-item .value .status-badge.cancelled {
            background: #64748B;
        }
        
        /* ================================================================
           CANCELLATION BOX
           ================================================================ */
        .cancellation-box {
            background: #F1F5F9;
            border: 2px solid #94A3B8;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 16px;
        }
        
        .cancellation-box .cancellation-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .cancellation-box .cancellation-reason {
            font-size: 14px;
            color: #1E293B;
            margin-top: 2px;
        }
        
        .cancellation-box .cancelled-by {
            font-size: 12px;
            color: #64748B;
            margin-top: 2px;
        }
        
        /* ================================================================
           INVOICE TABLE
           ================================================================ */
        .invoice-table-wrapper {
            overflow-x: auto;
            margin-bottom: 20px;
            border-radius: 10px;
            border: 1px solid #E2E8F0;
        }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .invoice-table thead th {
            background: #0B5ED7;
            color: white;
            padding: 10px 12px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        
        .invoice-table thead th.text-center { text-align: center; }
        .invoice-table thead th.text-right { text-align: right; }
        
        .invoice-table tbody td {
            padding: 8px 12px;
            border-bottom: 1px solid #E2E8F0;
            vertical-align: middle;
        }
        
        .invoice-table tbody td.text-center { text-align: center; }
        .invoice-table tbody td.text-right { text-align: right; }
        
        .invoice-table tbody td .batch-info {
            font-size: 11px;
            color: #64748B;
            display: block;
            margin-top: 2px;
        }
        
        .invoice-table tbody tr:nth-child(even) {
            background: #F8FAFC;
        }
        
        .invoice-table tbody tr:hover {
            background: #E8F0FE;
        }
        
        .invoice-table tfoot td {
            padding: 12px;
            border-top: 3px double #0B5ED7;
            font-weight: 700;
            font-size: 14px;
            background: #E8F0FE;
        }
        
        .invoice-table .text-danger { color: #DC2626; }
        .invoice-table .text-success { color: #0B5ED7; }
        .invoice-table .fw-bold { font-weight: 700; }
        
        /* ================================================================
           INVOICE SUMMARY
           ================================================================ */
        .invoice-summary {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 12px;
            padding: 16px;
            background: #E8F0FE;
            border-radius: 10px;
            border: 1px solid #BFDBFE;
            margin-bottom: 20px;
        }
        
        .invoice-summary .summary-item .label {
            font-size: 10px;
            color: #64748B;
            margin: 0;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .invoice-summary .summary-item .value {
            font-size: 18px;
            font-weight: 700;
            margin: 4px 0 0 0;
        }
        
        .invoice-summary .summary-item .value.text-danger { color: #DC2626; }
        .invoice-summary .summary-item .value.text-success { color: #0B5ED7; }
        .invoice-summary .summary-item .value.text-warning { color: #1A73E8; }
        
        /* ================================================================
           ADDED BY
           ================================================================ */
        .invoice-added-by {
            font-size: 12px;
            color: #64748B;
            border-top: 1px solid #E2E8F0;
            padding-top: 14px;
            margin-top: 10px;
        }
        
        .invoice-added-by p {
            margin: 2px 0;
        }
        
        .invoice-added-by strong {
            color: #1E293B;
        }
        
        /* ================================================================
           OFFICIAL STAMP
           ================================================================ */
        .official-stamp {
            margin-top: 20px;
            padding-top: 14px;
            border-top: 2px solid #E2E8F0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .official-stamp .stamp-left {
            font-size: 12px;
            color: #64748B;
        }
        
        .official-stamp .stamp-left strong {
            color: #1E293B;
        }
        
        .official-stamp .stamp-box {
            text-align: center;
            padding: 8px 20px;
            border: 3px solid #0B5ED7;
            border-radius: 10px;
            background: #E8F0FE;
            min-width: 170px;
        }
        
        .official-stamp .stamp-box .stamp-title {
            font-size: 9px;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        
        .official-stamp .stamp-box .stamp-name {
            font-size: 14px;
            font-weight: 800;
            color: #0B5ED7;
        }
        
        .official-stamp .stamp-box .stamp-line {
            font-size: 11px;
            color: #64748B;
            margin-top: 2px;
        }
        
        .official-stamp .stamp-box .stamp-date {
            font-size: 9px;
            color: #94A3B8;
            margin-top: 2px;
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .invoice-footer {
            text-align: center;
            border-top: 2px solid #0B5ED7;
            padding-top: 14px;
            margin-top: 20px;
        }
        
        .invoice-footer p {
            font-size: 11px;
            color: #64748B;
            margin: 0;
        }
        
        .invoice-footer .thank-you {
            font-size: 10px;
            color: #94A3B8;
            margin: 2px 0;
        }
        
        /* ================================================================
           NO ITEMS
           ================================================================ */
        .no-items {
            text-align: center;
            padding: 30px;
            color: #94A3B8;
            border: 2px dashed #E2E8F0;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .no-items i {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 8px;
            color: #0B5ED7;
            opacity: 0.5;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 768px) {
            .invoice-container { padding: 16px 18px; }
            .invoice-info { grid-template-columns: 1fr 1fr; gap: 8px; padding: 10px 12px; }
            .invoice-summary { grid-template-columns: 1fr 1fr; gap: 8px; padding: 10px 12px; }
            .invoice-title p span { display: block; margin-left: 0; }
            .invoice-table { font-size: 12px; }
            .invoice-table thead th,
            .invoice-table tbody td { padding: 5px 8px; }
            .print-btn-wrapper { justify-content: center; }
            .print-btn { flex: 1; justify-content: center; }
            .official-stamp { flex-direction: column; text-align: center; }
        }
        
        @media (max-width: 480px) {
            .invoice-container { padding: 10px 12px; }
            .invoice-info { grid-template-columns: 1fr; }
            .invoice-summary { grid-template-columns: 1fr; }
            .invoice-table { font-size: 10px; }
            .invoice-table thead th,
            .invoice-table tbody td { padding: 4px 6px; }
            .invoice-header h1 { font-size: 18px; }
            .invoice-title h2 { font-size: 16px; }
            .invoice-summary .summary-item .value { font-size: 15px; }
        }
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
                display: block !important;
            }
            .invoice-container {
                box-shadow: none !important;
                padding: 15mm !important;
                border-radius: 0 !important;
                max-width: 100% !important;
            }
            .print-btn-wrapper,
            .no-print {
                display: none !important;
            }
            .invoice-table thead th {
                background: #0B5ED7 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .invoice-info,
            .invoice-summary {
                background: #E8F0FE !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .official-stamp .stamp-box {
                background: #E8F0FE !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                border-color: #0B5ED7 !important;
            }
            .invoice-table tbody tr:nth-child(even) {
                background: #F8FAFC !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .invoice-table tbody tr:hover {
                background: #F8FAFC !important;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        
        <!-- PRINT BUTTONS -->
        <div class="print-btn-wrapper no-print">
            <button class="print-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Print Invoice
            </button>
            <button class="print-btn danger" onclick="window.close()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
        
        <!-- INVOICE HEADER -->
        <div class="invoice-header">
            <img src="<?= $logo_path ?>" alt="Braick Dispensary Logo" class="logo" 
                 onerror="this.onerror=null; this.src='<?= $logo_fallback ?>'">
            <h1>BRAICK DISPENSARY</h1>
            <p class="slogan">Tunajali Afya Yako</p>
            <p><i class="fas fa-map-marker-alt"></i> Dodoma, Tanzania</p>
            <p>
                <i class="fas fa-phone"></i> <?= htmlspecialchars($admin_phone ?: '+255 123 456 789') ?> 
                <span style="margin:0 8px;">|</span> 
                <i class="fas fa-envelope"></i> info@braick.com
            </p>
            <?php if (!empty($branch_name)): ?>
                <p class="branch-name">
                    <i class="fas fa-store-alt"></i> Branch: <?= htmlspecialchars($branch_name) ?>
                </p>
            <?php endif; ?>
        </div>
        
        <!-- INVOICE TITLE -->
        <div class="invoice-title">
            <h2>📋 PURCHASE INVOICE</h2>
            <p>
                <strong>Invoice #:</strong> <?= htmlspecialchars($purchase['invoice_number']) ?>
                <span><strong>Date:</strong> <?= date('d/m/Y H:i', strtotime($purchase['created_at'])) ?></span>
                <?php if ($purchase['completed_at']): ?>
                    <span><strong>Completed:</strong> <?= date('d/m/Y H:i', strtotime($purchase['completed_at'])) ?></span>
                <?php endif; ?>
                <span>
                    <strong>Status:</strong> 
                    <span class="status-<?= strtolower($purchase['status']) ?>">
                        <?= $purchase['status'] ?>
                    </span>
                </span>
                <span><strong>Type:</strong> <?= ucfirst($purchase['purchase_type']) ?></span>
            </p>
        </div>
        
        <!-- CANCELLATION REASON -->
        <?php if ($purchase['status'] === 'CANCELLED' && !empty($purchase['cancelled_reason'])): ?>
            <div class="cancellation-box">
                <div class="cancellation-label">
                    <i class="fas fa-exclamation-triangle"></i> Cancellation Reason
                </div>
                <div class="cancellation-reason">
                    <?= htmlspecialchars($purchase['cancelled_reason']) ?>
                </div>
                <?php if (!empty($purchase['cancelled_by_name'])): ?>
                    <div class="cancelled-by">
                        <i class="fas fa-user"></i> Cancelled by: <?= htmlspecialchars($purchase['cancelled_by_name']) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- PURCHASE INFO -->
        <div class="invoice-info">
            <div class="info-item">
                <p class="label">Created By</p>
                <p class="value">
                    <i class="fas fa-user-circle" style="color:#0B5ED7;"></i>
                    <?= htmlspecialchars($purchase['creator_name'] ?? $purchase['created_by_name'] ?? 'Unknown') ?>
                </p>
            </div>
            <div class="info-item">
                <p class="label">Purchase Type</p>
                <p class="value" style="text-transform:uppercase;">
                    <i class="fas fa-tag" style="color:#0B5ED7;"></i>
                    <?= ucfirst($purchase['purchase_type']) ?>
                </p>
            </div>
            <div class="info-item">
                <p class="label">Status</p>
                <p class="value">
                    <span class="status-badge <?= strtolower($purchase['status']) ?>">
                        <i class="fas <?= $purchase['status'] === 'COMPLETED' ? 'fa-check-circle' : ($purchase['status'] === 'IN_PROGRESS' ? 'fa-spinner fa-spin' : 'fa-times-circle') ?>"></i>
                        <?= $purchase['status'] ?>
                    </span>
                </p>
            </div>
            <div class="info-item">
                <p class="label">Total Items</p>
                <p class="value">
                    <i class="fas fa-boxes" style="color:#0B5ED7;"></i>
                    <?= number_format($purchase['total_items']) ?>
                </p>
            </div>
            <div class="info-item">
                <p class="label">Total Quantity</p>
                <p class="value">
                    <i class="fas fa-cubes" style="color:#0B5ED7;"></i>
                    <?= number_format($purchase['total_quantity']) ?> units
                </p>
            </div>
            <div class="info-item">
                <p class="label">Expected Profit</p>
                <p class="value" style="color:<?= $profit >= 0 ? '#0B5ED7' : '#DC2626' ?>;">
                    <i class="fas fa-chart-line"></i>
                    TSh <?= fmt($profit) ?>
                    <span style="font-size:13px;font-weight:400;">(<?= $profit_percent ?>%)</span>
                </p>
            </div>
        </div>
        
        <!-- ITEMS TABLE -->
        <?php if (count($items) > 0): ?>
            <div class="invoice-table-wrapper">
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th style="min-width:180px;">Item</th>
                            <th style="width:60px;text-align:center;">Qty</th>
                            <th style="width:110px;text-align:right;">Buy Price</th>
                            <th style="width:120px;text-align:right;">Buy Total</th>
                            <th style="width:110px;text-align:right;">Sell Price</th>
                            <th style="width:120px;text-align:right;">Sell Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td style="text-align:center;"><?= $counter++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['item_name'] ?? 'Unknown') ?></strong>
                                    <span class="batch-info">
                                        <?= htmlspecialchars($item['unit'] ?? 'pcs') ?> | 
                                        Batch: <?= htmlspecialchars($item['batch_number'] ?? 'N/A') ?>
                                        <?php if (!empty($item['added_by_full_name'])): ?>
                                            | Added by: <?= htmlspecialchars($item['added_by_full_name']) ?>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="text-center"><?= number_format($item['quantity']) ?></td>
                                <td class="text-right text-danger">TSh <?= fmt($item['buying_price'] ?? 0) ?></td>
                                <td class="text-right text-danger fw-bold">TSh <?= fmt($item['total_buying_cost'] ?? 0) ?></td>
                                <td class="text-right text-success">TSh <?= fmt($item['selling_price'] ?? 0) ?></td>
                                <td class="text-right text-success fw-bold">TSh <?= fmt($item['total_selling_value'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="fw-bold">TOTALS</td>
                            <td></td>
                            <td class="text-right text-danger fw-bold" style="font-size:15px;">
                                TSh <?= fmt($purchase['total_buying_cost'] ?? 0) ?>
                            </td>
                            <td></td>
                            <td class="text-right text-success fw-bold" style="font-size:15px;">
                                TSh <?= fmt($purchase['total_selling_value'] ?? 0) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="no-items">
                <i class="fas fa-box-open"></i>
                <p>No items found in this purchase</p>
            </div>
        <?php endif; ?>
        
        <!-- SUMMARY -->
        <div class="invoice-summary">
            <div class="summary-item">
                <p class="label">Grand Total (Buying)</p>
                <p class="value text-danger">
                    TSh <?= fmt($purchase['total_buying_cost'] ?? 0) ?>
                </p>
            </div>
            <div class="summary-item">
                <p class="label">Grand Total (Selling)</p>
                <p class="value text-success">
                    TSh <?= fmt($purchase['total_selling_value'] ?? 0) ?>
                </p>
            </div>
            <div class="summary-item">
                <p class="label">Expected Profit</p>
                <p class="value" style="color:<?= $profit >= 0 ? '#0B5ED7' : '#DC2626' ?>;">
                    TSh <?= fmt($profit) ?>
                    <span style="font-size:13px;font-weight:400;">(<?= $profit_percent ?>%)</span>
                </p>
            </div>
            <div class="summary-item">
                <p class="label">Total Items</p>
                <p class="value text-warning">
                    <?= number_format($purchase['total_items']) ?> items
                    <span style="font-size:13px;font-weight:400;display:block;">
                        <?= number_format($purchase['total_quantity']) ?> units
                    </span>
                </p>
            </div>
        </div>
        
        <!-- ADDED BY -->
        <div class="invoice-added-by">
            <p><strong><i class="fas fa-user-plus" style="color:#0B5ED7;"></i> Added By:</strong></p>
            <p style="font-size:12px;">
                <?= !empty($unique_names) ? implode(', ', $unique_names) : 'N/A' ?>
            </p>
        </div>
        
        <!-- OFFICIAL STAMP -->
        <div class="official-stamp">
            <div class="stamp-left">
                <span>Generated by: <strong><?= htmlspecialchars($purchase['creator_name'] ?? 'Admin') ?></strong></span>
                <span style="margin-left:14px;">Date: <strong><?= date('F d, Y') ?></strong></span>
                <span style="margin-left:14px;display:block;font-size:10px;color:#94A3B8;margin-top:4px;">
                    <i class="fas fa-print"></i> Printed: <?= date('h:i A') ?>
                </span>
            </div>
            <div class="stamp-box">
                <div class="stamp-title">Official Stamp</div>
                <div class="stamp-name">BRAICK DISPENSARY</div>
                <div class="stamp-line">Approved By: _________________</div>
                <div class="stamp-date">Date: <?= date('F d, Y') ?></div>
            </div>
        </div>
        
        <!-- FOOTER -->
        <div class="invoice-footer">
            <p>&copy; <?= date('Y') ?> <strong>Braick Dispensary</strong> - All rights reserved</p>
            <p class="thank-you">Thank you for your business!</p>
        </div>
        
    </div>

<script>
    console.log('%c📄 Braick - Purchase Invoice (BLUE THEME)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
    console.log('%c✅ Blue theme applied', 'font-size:12px;color:#34D399;');
    console.log('%c✅ Logo ya Braick inaonekana', 'font-size:12px;color:#34D399;');
    console.log('%c📋 Invoice: <?= htmlspecialchars($purchase['invoice_number'] ?? 'N/A') ?>', 'font-size:12px;color:#64748B;');
</script>
</body>
</html>