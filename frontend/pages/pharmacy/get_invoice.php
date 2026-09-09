<?php
// ================================================================
// FILE: frontend/pages/pharmacy/get_invoice.php
// GET INVOICE HTML FOR PDF VIEW
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
    SELECT p.*, u.full_name as creator_name 
    FROM purchases p
    LEFT JOIN users u ON p.created_by = u.id
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
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice - Braick Dispensary</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: white;
            padding: 20px;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
        }
        .invoice-header {
            text-align: center;
            border-bottom: 3px solid #0B5ED7;
            padding-bottom: 15px;
            margin-bottom: 20px;
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
        .invoice-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding: 12px;
            background: #F8FAFC;
            border-radius: 8px;
            border: 1px solid #E2E8F0;
            margin-bottom: 20px;
        }
        .invoice-info .label {
            font-size: 11px;
            color: #64748B;
            margin: 0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .invoice-info .value {
            font-size: 14px;
            font-weight: 600;
            margin: 2px 0;
        }
        .invoice-info .text-right {
            text-align: right;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .invoice-table thead th {
            background: #0B5ED7;
            color: white;
            padding: 8px 10px;
            text-align: left;
            border: 1px solid #0B5ED7;
        }
        .invoice-table thead th.text-center {
            text-align: center;
        }
        .invoice-table thead th.text-right {
            text-align: right;
        }
        .invoice-table tbody td {
            padding: 6px 10px;
            border: 1px solid #E2E8F0;
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
        }
        .invoice-table tbody tr:nth-child(even) {
            background: #F8FAFC;
        }
        .invoice-table tfoot td {
            padding: 8px 10px;
            border-top: 2px solid #0B5ED7;
            font-weight: 700;
        }
        .invoice-summary {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            padding: 12px;
            background: #F8FAFC;
            border-radius: 8px;
            border: 1px solid #E2E8F0;
            margin-bottom: 20px;
        }
        .invoice-summary .label {
            font-size: 11px;
            color: #64748B;
            margin: 0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .invoice-summary .value {
            font-size: 18px;
            font-weight: 700;
            margin: 2px 0;
        }
        .invoice-summary .text-right {
            text-align: right;
        }
        .invoice-added-by {
            font-size: 11px;
            color: #64748B;
            border-top: 1px solid #E2E8F0;
            padding-top: 10px;
            margin-top: 10px;
        }
        .invoice-added-by p {
            margin: 2px 0;
        }
        .invoice-footer {
            text-align: center;
            border-top: 2px solid #0B5ED7;
            padding-top: 12px;
            margin-top: 15px;
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
        .text-danger { color: #DC2626; }
        .text-success { color: #059669; }
        .text-primary { color: #0B5ED7; }
        .fw-bold { font-weight: 700; }
        .mt-2 { margin-top: 10px; }
        .mb-2 { margin-bottom: 10px; }
        
        @media print {
            body { padding: 10px; }
            .no-print { display: none !important; }
        }
        @media (max-width: 600px) {
            .invoice-info { grid-template-columns: 1fr; }
            .invoice-info .text-right { text-align: left; }
            .invoice-summary { grid-template-columns: 1fr; }
            .invoice-summary .text-right { text-align: left; }
            .invoice-title p span { display: block; margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Invoice Header with Logo -->
        <div class="invoice-header">
            <img src="<?= $logo_path ?>" alt="Braick Dispensary Logo" class="logo" 
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2280%22 height=%2280%22%3E%3Crect width=%2280%22 height=%2280%22 fill=%22%230B5ED7%22 rx=%2212%22/%3E%3Ctext x=%2240%22 y=%2250%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2230%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <h1>Braick Dispensary</h1>
            <p><i class="fas fa-map-marker-alt"></i> Dodoma, Tanzania</p>
            <p><i class="fas fa-phone"></i> +255 123 456 789 | <i class="fas fa-envelope"></i> info@braick.com</p>
        </div>
        
        <!-- Invoice Title -->
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
                    <strong>Status:</strong> <span class="status-completed"><?= $purchase['status'] ?></span>
                </span>
            </p>
        </div>
        
        <!-- Purchase Info -->
        <div class="invoice-info">
            <div>
                <p class="label">Created By</p>
                <p class="value"><?= htmlspecialchars($purchase['creator_name'] ?? $purchase['created_by_name'] ?? 'Unknown') ?></p>
            </div>
            <div class="text-right">
                <p class="label">Purchase Type</p>
                <p class="value" style="text-transform:uppercase;"><?= ucfirst($purchase['purchase_type']) ?></p>
            </div>
            <div>
                <p class="label">Total Items</p>
                <p class="value"><?= number_format($purchase['total_items']) ?></p>
            </div>
            <div class="text-right">
                <p class="label">Total Quantity</p>
                <p class="value"><?= number_format($purchase['total_quantity']) ?> units</p>
            </div>
        </div>
        
        <!-- Items Table -->
        <?php if (count($items) > 0): ?>
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Item</th>
                        <th style="width:60px;text-align:center;">Qty</th>
                        <th style="width:100px;text-align:right;">Buy Price</th>
                        <th style="width:100px;text-align:right;">Sell Price</th>
                        <th style="width:120px;text-align:right;">Buy Total</th>
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
                                <br><span class="batch-info"><?= htmlspecialchars($item['unit'] ?? 'pcs') ?> | Batch: <?= htmlspecialchars($item['batch_number'] ?? 'N/A') ?></span>
                            </td>
                            <td class="text-center"><?= number_format($item['quantity']) ?></td>
                            <td class="text-right text-danger">TSh <?= fmt($item['buying_price'] ?? 0) ?></td>
                            <td class="text-right text-success">TSh <?= fmt($item['selling_price'] ?? 0) ?></td>
                            <td class="text-right text-danger fw-bold">TSh <?= fmt($item['total_buying_cost'] ?? 0) ?></td>
                            <td class="text-right text-success fw-bold">TSh <?= fmt($item['total_selling_value'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="fw-bold">TOTALS</td>
                        <td></td>
                        <td class="text-right text-danger fw-bold" style="font-size:15px;">
                            TSh <?= fmt($purchase['total_buying_cost'] ?? 0) ?>
                        </td>
                        <td class="text-right text-success fw-bold" style="font-size:15px;">
                            TSh <?= fmt($purchase['total_selling_value'] ?? 0) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        <?php else: ?>
            <div style="text-align:center;padding:30px;color:#94A3B8;">
                <i class="fas fa-box-open" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                <p>No items found in this purchase</p>
            </div>
        <?php endif; ?>
        
        <!-- Summary -->
        <div class="invoice-summary">
            <div>
                <p class="label">Grand Total (Buying)</p>
                <p class="value text-danger">
                    TSh <?= fmt($purchase['total_buying_cost'] ?? 0) ?>
                </p>
            </div>
            <div>
                <p class="label">Grand Total (Selling)</p>
                <p class="value text-success">
                    TSh <?= fmt($purchase['total_selling_value'] ?? 0) ?>
                </p>
            </div>
            <div>
                <p class="label">Expected Profit</p>
                <p class="value" style="color:<?= $profit >= 0 ? '#059669' : '#DC2626' ?>;">
                    TSh <?= fmt($profit) ?>
                    <span style="font-size:13px;font-weight:400;">(<?= $profit_percent ?>%)</span>
                </p>
            </div>
        </div>
        
        <!-- Added By Information -->
        <div class="invoice-added-by">
            <p><strong>Added By:</strong></p>
            <p style="font-size:12px;">
                <?= !empty($unique_names) ? implode(', ', $unique_names) : 'N/A' ?>
            </p>
        </div>
        
        <!-- Footer -->
        <div class="invoice-footer">
            <p>&copy; <?= date('Y') ?> Braick Dispensary - All rights reserved</p>
            <p class="thank-you">Thank you for your business!</p>
        </div>
    </div>
</body>
</html>