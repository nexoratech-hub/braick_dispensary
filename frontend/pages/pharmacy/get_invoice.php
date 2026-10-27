<?php
// ================================================================
// FILE: frontend/pages/pharmacy/get_invoice.php
// GET PURCHASE INVOICE (PDF) - V2.0
// ✅ Inachukua columns zote za purchase_items (item_name, n.k.)
// ✅ Inaonyesha discount (inapunguza buying cost)
// ✅ Inaonyesha final buying cost
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo '<div style="text-align:center;padding:30px;color:#DC2626;">Unauthorized</div>';
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$purchase_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($purchase_id <= 0) {
    echo '<div style="text-align:center;padding:30px;color:#DC2626;">Invalid purchase ID</div>';
    exit;
}

// ✅ Chukua purchase NA columns zote za discount
$stmt = $db->prepare("
    SELECT p.*, u.full_name as creator_name, b.name as branch_name, b.location as branch_location, b.phone as branch_phone
    FROM purchases p
    LEFT JOIN users u ON p.created_by = u.id
    LEFT JOIN branches b ON p.branch_id = b.id
    WHERE p.id = ? AND p.branch_id = ?
");
$stmt->execute([$purchase_id, $user_branch_id]);
$purchase = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$purchase) {
    echo '<div style="text-align:center;padding:30px;color:#DC2626;">Purchase not found</div>';
    exit;
}

// ✅ Chukua items NA columns zote muhimu
$stmt = $db->prepare("
    SELECT pi.*, u.full_name as added_by_full_name
    FROM purchase_items pi
    LEFT JOIN users u ON pi.added_by = u.id
    WHERE pi.purchase_id = ?
    ORDER BY pi.id ASC
");
$stmt->execute([$purchase_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Kokotoa discount
$total_buying = (float)($purchase['total_buying_cost'] ?? 0);
$total_selling = (float)($purchase['total_selling_value'] ?? 0);
$discount_amount = (float)($purchase['global_discount_amount'] ?? 0);
$discount_type = $purchase['global_discount_type'] ?? 'amount';
$discount_value = (float)($purchase['global_discount_value'] ?? 0);
$final_buying = (float)($purchase['final_total_buying_cost'] ?? $total_buying);

if ($final_buying == 0 && $discount_amount > 0) {
    $final_buying = $total_buying - $discount_amount;
}
if ($final_buying == 0 && $discount_amount == 0) {
    $final_buying = $total_buying;
}

$profit = $total_selling - $final_buying;

$branch_name_display = $purchase['branch_name'] ?? 'Dodoma';
$branch_location_display = $purchase['branch_location'] ?? 'Tanzania';
$branch_phone_display = $purchase['branch_phone'] ?? '+255 123 456 789';

function formatMoneyInv($amount) {
    if ($amount === null || $amount === '') return '0';
    return number_format((float)$amount, 0, '.', ',');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Purchase Invoice - <?= htmlspecialchars($purchase['invoice_number']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, 'Segoe UI', sans-serif;
            font-size: 12px;
            color: #1E293B;
            background: white;
            padding: 24px;
            line-height: 1.5;
        }
        
        .invoice-container { max-width: 850px; margin: 0 auto; }
        
        /* HEADER */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 16px;
            border-bottom: 3px solid #0B5ED7;
            margin-bottom: 20px;
        }
        
        .company-info { flex: 1; }
        .company-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            font-weight: 900;
            margin-bottom: 8px;
        }
        .company-name {
            font-size: 1.3rem;
            font-weight: 900;
            color: #0B5ED7;
            margin-bottom: 2px;
        }
        .company-detail {
            font-size: 0.72rem;
            color: #64748B;
            margin-bottom: 1px;
        }
        
        .invoice-title-block { text-align: right; }
        .invoice-title {
            font-size: 1.4rem;
            font-weight: 900;
            color: #0B5ED7;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        .invoice-meta {
            font-size: 0.72rem;
            color: #64748B;
            line-height: 1.6;
        }
        .invoice-meta strong { color: #1E293B; }
        
        .status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 4px;
        }
        .status-badge.COMPLETED { background: #D1FAE5; color: #059669; border: 1px solid #059669; }
        .status-badge.IN_PROGRESS { background: #FEF3C7; color: #D97706; border: 1px solid #D97706; }
        .status-badge.CANCELLED { background: #FEE2E2; color: #DC2626; border: 1px solid #DC2626; }
        
        /* INFO GRID */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        .info-item {
            padding: 10px 14px;
            background: #F8FAFC;
            border-radius: 8px;
            border-left: 3px solid #0B5ED7;
        }
        .info-label {
            font-size: 0.6rem;
            text-transform: uppercase;
            color: #64748B;
            font-weight: 700;
            letter-spacing: 0.05em;
            margin-bottom: 2px;
        }
        .info-value {
            font-size: 0.85rem;
            font-weight: 700;
            color: #1E293B;
        }
        
        /* TABLE */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 0.78rem;
        }
        .items-table thead th {
            background: #0B5ED7;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-size: 0.65rem;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.05em;
            border: none;
        }
        .items-table thead th.center { text-align: center; }
        .items-table thead th.right { text-align: right; }
        
        .items-table tbody td {
            padding: 10px 8px;
            border-bottom: 1px solid #E2E8F0;
            vertical-align: top;
        }
        .items-table tbody tr:nth-child(even) { background: #F8FAFC; }
        .items-table tbody tr:last-child td { border-bottom: 2px solid #0B5ED7; }
        
        .item-name { font-weight: 700; color: #1E293B; font-size: 0.82rem; }
        .item-detail { font-size: 0.65rem; color: #64748B; margin-top: 2px; }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-red { color: #DC2626; font-weight: 700; }
        .text-green { color: #059669; font-weight: 700; }
        .text-blue { color: #0B5ED7; font-weight: 700; }
        
        /* TOTALS */
        .totals-section {
            margin-left: auto;
            width: 380px;
            margin-bottom: 20px;
        }
        
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 8px 14px;
            font-size: 0.8rem;
        }
        .totals-table td.label {
            text-align: right;
            color: #64748B;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.03em;
        }
        .totals-table td.value {
            text-align: right;
            font-weight: 800;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
        }
        .totals-table tr.subtotal td { border-top: 2px solid #E2E8F0; }
        .totals-table tr.buying td { background: #FEE2E2; }
        .totals-table tr.buying td.label { color: #DC2626; }
        .totals-table tr.buying td.value { color: #DC2626; }
        .totals-table tr.discount td { background: #FEF3C7; }
        .totals-table tr.discount td.label { color: #D97706; }
        .totals-table tr.discount td.value { color: #D97706; }
        .totals-table tr.final-buying td { background: #FECACA; border-top: 2px solid #991B1B; }
        .totals-table tr.final-buying td.label { color: #991B1B; font-weight: 800; }
        .totals-table tr.final-buying td.value { color: #991B1B; font-weight: 900; font-size: 0.95rem; }
        .totals-table tr.selling td { background: #E8F0FE; }
        .totals-table tr.selling td.label { color: #0B5ED7; }
        .totals-table tr.selling td.value { color: #0B5ED7; }
        .totals-table tr.profit td { background: #D1FAE5; border-top: 2px solid #059669; }
        .totals-table tr.profit td.label { color: #059669; font-weight: 800; }
        .totals-table tr.profit td.value { color: #059669; font-weight: 900; font-size: 1rem; }
        
        /* DISCOUNT BANNER */
        .discount-banner {
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            border: 2px solid #D97706;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .discount-banner .icon {
            width: 40px;
            height: 40px;
            background: #D97706;
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 900;
            flex-shrink: 0;
        }
        .discount-banner .text-block { flex: 1; }
        .discount-banner .title {
            font-size: 0.75rem;
            font-weight: 800;
            color: #92400E;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 2px;
        }
        .discount-banner .desc {
            font-size: 0.8rem;
            color: #78350F;
            font-weight: 600;
        }
        .discount-banner .amount {
            font-size: 1.1rem;
            font-weight: 900;
            color: #DC2626;
            font-family: 'Courier New', monospace;
        }
        
        /* FOOTER */
        .invoice-footer {
            margin-top: 24px;
            padding-top: 12px;
            border-top: 2px dashed #E2E8F0;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 0.7rem;
            color: #64748B;
        }
        .footer-note {
            font-size: 0.7rem;
            color: #64748B;
            font-style: italic;
            max-width: 400px;
        }
        .footer-brand {
            text-align: right;
            font-size: 0.72rem;
            color: #64748B;
        }
        .footer-brand strong { color: #0B5ED7; }
        
        .signature-area {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            gap: 30px;
        }
        .signature-box {
            flex: 1;
            text-align: center;
            padding-top: 30px;
            border-top: 2px solid #CBD5E1;
            font-size: 0.72rem;
            color: #64748B;
            font-weight: 600;
        }
        
        @media print {
            body { padding: 8px; font-size: 11px; }
            .invoice-container { max-width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        
        <!-- HEADER -->
        <div class="invoice-header">
            <div class="company-info">
                <div class="company-logo">B</div>
                <div class="company-name">Braick Dispensary</div>
                <div class="company-detail"><?= htmlspecialchars($branch_name_display) ?>, <?= htmlspecialchars($branch_location_display) ?></div>
                <div class="company-detail"><?= htmlspecialchars($branch_phone_display) ?> | info@braick.com</div>
            </div>
            
            <div class="invoice-title-block">
                <div class="invoice-title">PURCHASE INVOICE</div>
                <div class="invoice-meta">
                    <div>Invoice #: <strong><?= htmlspecialchars($purchase['invoice_number']) ?></strong></div>
                    <div>Date: <strong><?= date('d/m/Y H:i', strtotime($purchase['created_at'])) ?></strong></div>
                    <?php if (!empty($purchase['completed_at'])): ?>
                        <div>Completed: <strong><?= date('d/m/Y H:i', strtotime($purchase['completed_at'])) ?></strong></div>
                    <?php endif; ?>
                </div>
                <span class="status-badge <?= htmlspecialchars($purchase['status']) ?>">
                    <?= htmlspecialchars($purchase['status']) ?>
                </span>
            </div>
        </div>
        
        <!-- INFO GRID -->
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Created By</div>
                <div class="info-value"><?= htmlspecialchars($purchase['creator_name'] ?? $purchase['created_by_name'] ?? 'Unknown') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Purchase Type</div>
                <div class="info-value"><?= ucfirst(htmlspecialchars($purchase['purchase_type'])) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Total Items</div>
                <div class="info-value"><?= number_format($purchase['total_items'] ?? 0) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Total Quantity</div>
                <div class="info-value"><?= number_format($purchase['total_quantity'] ?? 0) ?> units</div>
            </div>
            <div class="info-item">
                <div class="info-label">Branch</div>
                <div class="info-value"><?= htmlspecialchars($branch_name_display) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Payment Status</div>
                <div class="info-value" style="color:<?= $purchase['status'] === 'COMPLETED' ? '#059669' : '#D97706' ?>;">
                    <?= $purchase['status'] === 'COMPLETED' ? 'Paid' : 'Pending' ?>
                </div>
            </div>
        </div>
        
        <!-- ✅ DISCOUNT BANNER (kama > 0) -->
        <?php if ($discount_amount > 0): ?>
        <div class="discount-banner">
            <div class="icon">%</div>
            <div class="text-block">
                <div class="title">Discount Applied</div>
                <div class="desc">
                    <?php if ($discount_type === 'percent'): ?>
                        <?= number_format($discount_value, 2) ?>% off on total buying cost
                    <?php else: ?>
                        Fixed discount on total buying cost
                    <?php endif; ?>
                </div>
            </div>
            <div class="amount">-TSh <?= formatMoneyInv($discount_amount) ?></div>
        </div>
        <?php endif; ?>
        
        <!-- ITEMS TABLE -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:30px;" class="center">#</th>
                    <th>Item</th>
                    <th style="width:60px;" class="center">Qty</th>
                    <th style="width:90px;" class="right">Buy Price</th>
                    <th style="width:90px;" class="right">Sell Price</th>
                    <th style="width:100px;" class="right">Buy Total</th>
                    <th style="width:100px;" class="right">Sell Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($items) > 0): ?>
                    <?php $counter = 1; foreach ($items as $item): 
                        // ✅ Chukua item_name (sahihi) badala ya 'Unknown'
                        $item_name = $item['item_name'] ?? 'Unknown Item';
                        $item_unit = $item['item_unit'] ?? 'pcs';
                        $item_batch = $item['item_batch_number'] ?? 'N/A';
                        $item_category = $item['item_category'] ?? '';
                        
                        // ✅ Tumia total_buying_cost na total_selling_value (sahihi)
                        $item_buy_total = (float)($item['total_buying_cost'] ?? 0);
                        $item_sell_total = (float)($item['total_selling_value'] ?? 0);
                        $item_buy_price = (float)($item['buying_price'] ?? 0);
                        $item_sell_price = (float)($item['selling_price'] ?? 0);
                    ?>
                        <tr>
                            <td class="text-center"><?= $counter++ ?></td>
                            <td>
                                <div class="item-name"><?= htmlspecialchars($item_name) ?></div>
                                <div class="item-detail">
                                    <?= htmlspecialchars($item_unit) ?> 
                                    <?php if (!empty($item_category)): ?>
                                        | <?= htmlspecialchars($item_category) ?>
                                    <?php endif; ?>
                                    | Batch: <?= htmlspecialchars($item_batch) ?>
                                </div>
                            </td>
                            <td class="text-center"><?= number_format($item['quantity'] ?? 0) ?></td>
                            <td class="text-right text-red">TSh <?= formatMoneyInv($item_buy_price) ?></td>
                            <td class="text-right text-green">TSh <?= formatMoneyInv($item_sell_price) ?></td>
                            <td class="text-right text-red">TSh <?= formatMoneyInv($item_buy_total) ?></td>
                            <td class="text-right text-green">TSh <?= formatMoneyInv($item_sell_total) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding:20px;color:#64748B;">
                            No items in this purchase
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- TOTALS -->
        <div class="totals-section">
            <table class="totals-table">
                <tr class="subtotal buying">
                    <td class="label">Buying Cost</td>
                    <td class="value">TSh <?= formatMoneyInv($total_buying) ?></td>
                </tr>
                
                <?php if ($discount_amount > 0): ?>
                <tr class="discount">
                    <td class="label">
                        Discount
                        <?php if ($discount_type === 'percent'): ?>
                            (<?= number_format($discount_value, 2) ?>%)
                        <?php endif; ?>
                    </td>
                    <td class="value">-TSh <?= formatMoneyInv($discount_amount) ?></td>
                </tr>
                <?php endif; ?>
                
                <tr class="final-buying">
                    <td class="label">Final Buying Cost</td>
                    <td class="value">TSh <?= formatMoneyInv($final_buying) ?></td>
                </tr>
                
                <tr class="selling">
                    <td class="label">Total Selling Value</td>
                    <td class="value">TSh <?= formatMoneyInv($total_selling) ?></td>
                </tr>
                
                <tr class="profit">
                    <td class="label">Expected Profit</td>
                    <td class="value">TSh <?= formatMoneyInv($profit) ?></td>
                </tr>
            </table>
        </div>
        
        <!-- SIGNATURE -->
        <div class="signature-area">
            <div class="signature-box">
                Prepared By: <?= htmlspecialchars($purchase['creator_name'] ?? $purchase['created_by_name'] ?? 'N/A') ?>
            </div>
            <div class="signature-box">
                Approved By: _______________________
            </div>
        </div>
        
        <!-- FOOTER -->
        <div class="invoice-footer">
            <div class="footer-note">
                This is a computer-generated invoice. No signature is required.
            </div>
            <div class="footer-brand">
                <strong>Braick Dispensary</strong><br>
                Generated on <?= date('d/m/Y H:i:s') ?>
            </div>
        </div>
        
    </div>
</body>
</html>