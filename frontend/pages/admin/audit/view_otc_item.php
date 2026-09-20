<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) { header('Location: /dispensary_system/frontend/pages/login.php'); exit; }

$item_id = (int)($_GET['id'] ?? 0);
$sale_id = (int)($_GET['sale_id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($item_id <= 0) { header('Location: other_services.php?tab=otc_bills'); exit; }

require_once __DIR__ . '/../../../../backend/config/database.php';
$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("
    SELECT osi.*, os.sale_number, os.customer_name, os.payment_status, os.created_at as sale_date,
           u.full_name as sold_by_name, b.name as branch_name
    FROM otc_sale_items osi
    INNER JOIN otc_sales os ON osi.sale_id = os.id
    LEFT JOIN users u ON os.sold_by = u.id
    LEFT JOIN branches b ON os.branch_id = b.id
    WHERE osi.id = ? AND osi.sale_id = ?
    LIMIT 1
");
$stmt->execute([$item_id, $sale_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) { $_SESSION['error_message'] = "Item not found."; header('Location: other_services.php?tab=otc_bills'); exit; }

include_once __DIR__ . '/../../../components/admin_audit_header.php';
include_once __DIR__ . '/../../../components/admin_audit_sidebar.php';
?>
<!DOCTYPE html>
<html><head>
<meta charset="UTF-8"><title>View OTC Item</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
body { font-family: 'Inter', sans-serif; background: #F1F5F9; margin: 0; }
.page-header { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); border-radius: 16px; padding: 20px 24px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; }
.page-header .page-title { color: white; font-size: 1.4rem; font-weight: 800; margin: 0; }
.btn-header { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 8px 14px; border-radius: 9px; font-weight: 600; font-size: 0.75rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
.info-card { background: white; border-radius: 14px; border: 2px solid #E2E8F0; overflow: hidden; margin-bottom: 16px; }
.info-card .card-header { padding: 14px 18px; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; font-weight: 800; }
.info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; padding: 20px; }
.info-item { display: flex; flex-direction: column; gap: 4px; padding: 10px 14px; background: #F8FAFC; border-radius: 8px; border-left: 3px solid #0B5ED7; }
.info-item .label { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; color: #64748B; }
.info-item .value { font-size: 0.9rem; font-weight: 700; color: #1E293B; }
.info-item .value.mono { font-family: 'JetBrains Mono', monospace; }
</style>
</head><body>
<main class="main-content" style="margin-left: 270px; margin-top: 68px; padding: 24px 28px;">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-pills"></i> OTC Item Details</h1>
        <a href="other_services.php?tab=otc_bills&branch=<?= $selected_branch_id ?>" class="btn-header">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    <div class="info-card">
        <div class="card-header"><i class="fas fa-info-circle"></i> Item Information</div>
        <div class="info-grid">
            <div class="info-item">
                <span class="label">Medication</span>
                <span class="value"><?= htmlspecialchars($item['item_name']) ?></span>
            </div>
            <div class="info-item">
                <span class="label">Quantity</span>
                <span class="value mono"><?= (int)$item['quantity'] ?></span>
            </div>
            <div class="info-item">
                <span class="label">Unit Price</span>
                <span class="value mono">TSh <?= number_format($item['unit_price'], 0) ?></span>
            </div>
            <div class="info-item">
                <span class="label">Total Price</span>
                <span class="value mono">TSh <?= number_format($item['total_price'], 0) ?></span>
            </div>
            <div class="info-item">
                <span class="label">Dosage</span>
                <span class="value"><?= htmlspecialchars($item['dosage'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="label">Sale Number</span>
                <span class="value mono"><?= htmlspecialchars($item['sale_number']) ?></span>
            </div>
            <div class="info-item">
                <span class="label">Customer</span>
                <span class="value"><?= htmlspecialchars($item['customer_name'] ?? 'Walk-in') ?></span>
            </div>
            <div class="info-item">
                <span class="label">Status</span>
                <span class="value"><?= strtoupper($item['payment_status']) ?></span>
            </div>
        </div>
    </div>
</main>
</body></html>