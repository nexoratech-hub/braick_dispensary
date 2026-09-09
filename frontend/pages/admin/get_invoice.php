<?php
// ================================================================
// FILE: frontend/pages/admin/get_invoice.php
// ADMIN - GET INVOICE HTML FOR PDF VIEW
// OPENS IN NEW WINDOW / TAB
// ================================================================

// ================================================================
// SESSION START
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

// ================================================================
// CHECK USER ACCESS (Admin or Pharmacy)
// ================================================================
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

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG';

// ================================================================
// GENERATE INVOICE HTML
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

// Format money function
function fmt($amount) {
    return number_format((float)$amount, 0, '.', ',');
}

// Get branch name if available
$branch_name = '';
if (!empty($purchase['branch_id'])) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $stmt->execute([$purchase['branch_id']]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch) {
        $branch_name = $branch['name'];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice - Braick Dispensary</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ================================================================ */
        /* RESET & BASE */
        /* ================================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }
        
        /* ================================================================ */
        /* INVOICE CONTAINER */
        /* ================================================================ */
        .invoice-container {
            max-width: 900px;
            width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.12);
            padding: 30px 35px;
            position: relative;
        }
        
        /* ================================================================ */
        /* PRINT BUTTON - TOP RIGHT */
        /* ================================================================ */
        .print-btn-wrapper {
            text-align: right;
            margin-bottom: 15px;
        }
        
        .print-btn {
            background: #0B5ED7;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .print-btn:hover {
            background: #0A4CA8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .print-btn i { font-size: 1rem; }
        
        /* ================================================================ */
        /* INVOICE HEADER */
        /* ================================================================ */
        .invoice-header {
            text-align: center;
            border-bottom: 3px solid #0B5ED7;
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
            margin-top: 4px;
        }
        
        /* ================================================================ */
        /* INVOICE TITLE */
        /* ================================================================ */
        .invoice-title {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .invoice-title h2 {
            font-size: 20px;
            color: #0B5ED7;
            margin: 0;
        }
        
        .invoice-title p {
            font-size: 12px;
            color: #64748B;
            margin: 2px 0;
        }
        
        .invoice-title p span {
            margin-left: 20px;
        }
        
        .invoice-title .status-completed {
            color: #059669;
        }
        
        .invoice-title .status-in-progress {
            color: #D97706;
        }
        
        .invoice-title .status-cancelled {
            color: #DC2626;
        }
        
        /* ================================================================ */
        /* INVOICE INFO GRID */
        /* ================================================================ */
        .invoice-info {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            padding: 14px 16px;
            background: #F8FAFC;
            border-radius: 8px;
            border: 1px solid #E2E8F0;
            margin-bottom: 20px;
        }
        
        .invoice-info .info-item .label {
            font-size: 10px;
            color: #64748B;
            margin: 0;
            font-weight: 600;
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
            padding: 2px 10px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .invoice-info .info-item .value .status-badge.completed {
            background: #D1FAE5;
            color: #059669;
        }
        
        .invoice-info .info-item .value .status-badge.in-progress {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .invoice-info .info-item .value .status-badge.cancelled {
            background: #FEE2E2;
            color: #DC2626;
        }
        
        /* ================================================================ */
        /* INVOICE TABLE */
        /* ================================================================ */
        .invoice-table-wrapper {
            overflow-x: auto;
            margin-bottom: 20px;
        }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .invoice-table thead th {
            background: #0B5ED7;
            color: white;
            padding: 8px 12px;
            text-align: left;
            border: 1px solid #0B5ED7;
            white-space: nowrap;
        }
        
        .invoice-table thead th.text-center {
            text-align: center;
        }
        
        .invoice-table thead th.text-right {
            text-align: right;
        }
        
        .invoice-table tbody td {
            padding: 7px 12px;
            border: 1px solid #E2E8F0;
            vertical-align: middle;
        }
        
        .invoice-table tbody td.text-center {
            text-align: center;
        }
        
        .invoice-table tbody td.text-right {
            text-align: right;
        }
        
        .invoice-table tbody td .batch-info {
            font-size: 11px;
            color: #64748B;
            display: block;
        }
        
        .invoice-table tbody tr:nth-child(even) {
            background: #F8FAFC;
        }
        
        .invoice-table tbody tr:hover {
            background: #E8F0FE;
        }
        
        .invoice-table tfoot td {
            padding: 10px 12px;
            border-top: 2px solid #0B5ED7;
            font-weight: 700;
            font-size: 14px;
        }
        
        .invoice-table .text-danger { color: #DC2626; }
        .invoice-table .text-success { color: #059669; }
        .invoice-table .fw-bold { font-weight: 700; }
        
        /* ================================================================ */
        /* INVOICE SUMMARY */
        /* ================================================================ */
        .invoice-summary {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 12px;
            padding: 14px 16px;
            background: #F8FAFC;
            border-radius: 8px;
            border: 1px solid #E2E8F0;
            margin-bottom: 20px;
        }
        
        .invoice-summary .summary-item .label {
            font-size: 10px;
            color: #64748B;
            margin: 0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .invoice-summary .summary-item .value {
            font-size: 18px;
            font-weight: 700;
            margin: 2px 0;
        }
        
        .invoice-summary .summary-item .value.text-danger { color: #DC2626; }
        .invoice-summary .summary-item .value.text-success { color: #059669; }
        .invoice-summary .summary-item .value.text-warning { color: #D97706; }
        
        /* ================================================================ */
        /* CANCELLATION REASON */
        /* ================================================================ */
        .cancellation-box {
            background: #FEF2F2;
            border: 2px solid #DC2626;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 16px;
        }
        
        .cancellation-box .cancellation-label {
            font-size: 11px;
            font-weight: 600;
            color: #DC2626;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .cancellation-box .cancellation-reason {
            font-size: 14px;
            color: #991B1B;
            margin-top: 2px;
        }
        
        .cancellation-box .cancelled-by {
            font-size: 12px;
            color: #64748B;
            margin-top: 2px;
        }
        
        /* ================================================================ */
        /* ADDED BY INFO */
        /* ================================================================ */
        .invoice-added-by {
            font-size: 12px;
            color: #64748B;
            border-top: 1px solid #E2E8F0;
            padding-top: 12px;
            margin-top: 10px;
        }
        
        .invoice-added-by p {
            margin: 2px 0;
        }
        
        .invoice-added-by strong {
            color: #1E293B;
        }
        
        /* ================================================================ */
        /* FOOTER */
        /* ================================================================ */
        .invoice-footer {
            text-align: center;
            border-top: 2px solid #0B5ED7;
            padding-top: 14px;
            margin-top: 16px;
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
        
        /* ================================================================ */
        /* RESPONSIVE */
        /* ================================================================ */
        @media (max-width: 768px) {
            .invoice-container {
                padding: 16px 18px;
            }
            .invoice-info {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                padding: 10px 12px;
            }
            .invoice-summary {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                padding: 10px 12px;
            }
            .invoice-title p span {
                display: block;
                margin-left: 0;
            }
            .invoice-table {
                font-size: 12px;
            }
            .invoice-table thead th,
            .invoice-table tbody td {
                padding: 4px 8px;
            }
            .print-btn-wrapper {
                text-align: center;
            }
            .print-btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .invoice-container {
                padding: 10px 12px;
            }
            .invoice-info {
                grid-template-columns: 1fr;
            }
            .invoice-summary {
                grid-template-columns: 1fr;
            }
            .invoice-table {
                font-size: 10px;
            }
            .invoice-table thead th,
            .invoice-table tbody td {
                padding: 3px 5px;
            }
            .invoice-header h1 {
                font-size: 18px;
            }
            .invoice-title h2 {
                font-size: 16px;
            }
            .invoice-summary .summary-item .value {
                font-size: 15px;
            }
        }
        
        /* ================================================================ */
        /* PRINT STYLES */
        /* ================================================================ */
        @media print {
            body {
                background: white;
                padding: 10px;
            }
            .invoice-container {
                box-shadow: none;
                padding: 20px;
                border-radius: 0;
            }
            .print-btn-wrapper {
                display: none !important;
            }
            .no-print {
                display: none !important;
            }
            .invoice-table tbody tr:nth-child(even) {
                background: #F8FAFC;
            }
            .invoice-table tbody tr:hover {
                background: #F8FAFC;
            }
            .invoice-info {
                background: #F8FAFC;
            }
            .invoice-summary {
                background: #F8FAFC;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        
        <!-- ================================================================ -->
        <!-- PRINT BUTTON (hidden when printing) -->
        <!-- ================================================================ -->
        <div class="print-btn-wrapper no-print">
            <button class="print-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Print Invoice
            </button>
        </div>
        
        <!-- ================================================================ -->
        <!-- INVOICE HEADER -->
        <!-- ================================================================ -->
        <div class="invoice-header">
            <img src="<?= $logo_path ?>" alt="Braick Dispensary Logo" class="logo" 
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2280%22 height=%2280%22%3E%3Crect width=%2280%22 height=%2280%22 fill=%22%230B5ED7%22 rx=%2212%22/%3E%3Ctext x=%2240%22 y=%2250%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2230%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <h1>Braick Dispensary</h1>
            <p><i class="fas fa-map-marker-alt"></i> Dodoma, Tanzania</p>
            <p><i class="fas fa-phone"></i> +255 123 456 789 | <i class="fas fa-envelope"></i> info@braick.com</p>
            <?php if (!empty($branch_name)): ?>
                <p class="branch-name"><i class="fas fa-store-alt"></i> Branch: <?= htmlspecialchars($branch_name) ?></p>
            <?php endif; ?>
        </div>
        
        <!-- ================================================================ -->
        <!-- INVOICE TITLE -->
        <!-- ================================================================ -->
        <div class="invoice-title">
            <h2>PURCHASE INVOICE</h2>
            <p>
                <strong>Invoice #:</strong> <?= htmlspecialchars($purchase['invoice_number']) ?>
                <span>
                    <strong>Date:</strong> <?= date('d/m/Y H:i', strtotime($purchase['created_at'])) ?>
                </span>
                <?php if ($purchase['completed_at']): ?>
                    <span>
                        <strong>Completed:</strong> <?= date('d/m/Y H:i', strtotime($purchase['completed_at'])) ?>
                    </span>
                <?php endif; ?>
                <span>
                    <strong>Status:</strong> 
                    <span class="status-<?= strtolower($purchase['status']) ?>">
                        <?= $purchase['status'] ?>
                    </span>
                </span>
                <span>
                    <strong>Type:</strong> <?= ucfirst($purchase['purchase_type']) ?>
                </span>
            </p>
        </div>
        
        <!-- ================================================================ -->
        <!-- CANCELLATION REASON (if cancelled) -->
        <!-- ================================================================ -->
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
                        Cancelled by: <?= htmlspecialchars($purchase['cancelled_by_name']) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- ================================================================ -->
        <!-- PURCHASE INFO -->
        <!-- ================================================================ -->
        <div class="invoice-info">
            <div class="info-item">
                <p class="label">Created By</p>
                <p class="value"><?= htmlspecialchars($purchase['creator_name'] ?? $purchase['created_by_name'] ?? 'Unknown') ?></p>
            </div>
            <div class="info-item">
                <p class="label">Purchase Type</p>
                <p class="value" style="text-transform:uppercase;">
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
                <p class="value"><?= number_format($purchase['total_items']) ?></p>
            </div>
            <div class="info-item">
                <p class="label">Total Quantity</p>
                <p class="value"><?= number_format($purchase['total_quantity']) ?> units</p>
            </div>
            <div class="info-item">
                <p class="label">Expected Profit</p>
                <p class="value" style="color:<?= $profit >= 0 ? '#059669' : '#DC2626' ?>;">
                    TSh <?= fmt($profit) ?>
                    <span style="font-size:13px;font-weight:400;">
                        (<?= $profit_percent ?>% margin)
                    </span>
                </p>
            </div>
        </div>
        
        <!-- ================================================================ -->
        <!-- ITEMS TABLE -->
        <!-- ================================================================ -->
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
                            <td class="text-right text-danger fw-bold" style="font-size:15px;border-top:2px solid #DC2626;">
                                TSh <?= fmt($purchase['total_buying_cost'] ?? 0) ?>
                            </td>
                            <td></td>
                            <td class="text-right text-success fw-bold" style="font-size:15px;border-top:2px solid #059669;">
                                TSh <?= fmt($purchase['total_selling_value'] ?? 0) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align:center;padding:30px;color:#94A3B8;border:2px dashed #E2E8F0;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-box-open" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                <p>No items found in this purchase</p>
            </div>
        <?php endif; ?>
        
        <!-- ================================================================ -->
        <!-- SUMMARY -->
        <!-- ================================================================ -->
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
                <p class="value" style="color:<?= $profit >= 0 ? '#059669' : '#DC2626' ?>;">
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
        
        <!-- ================================================================ -->
        <!-- ADDED BY INFORMATION -->
        <!-- ================================================================ -->
        <div class="invoice-added-by">
            <p><strong>Added By:</strong></p>
            <p style="font-size:12px;">
                <?= !empty($unique_names) ? implode(', ', $unique_names) : 'N/A' ?>
            </p>
        </div>
        
        <!-- ================================================================ -->
        <!-- FOOTER -->
        <!-- ================================================================ -->
        <div class="invoice-footer">
            <p>&copy; <?= date('Y') ?> Braick Dispensary - All rights reserved</p>
            <p class="thank-you">Thank you for your business!</p>
        </div>
        
    </div>
</body>
</html>