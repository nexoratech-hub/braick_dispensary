<?php
// ================================================================
// FILE: frontend/pages/pharmacy/export_pdf.php
// PHARMACY - EXPORT REPORT AS PDF (WITH PREVIEW)
// USING NEW DATABASE: dispensary_db
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT PHARMACY
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$is_admin = $_SESSION['role'] === 'admin' || ($_SESSION['is_admin'] ?? false);
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE CONNECTION - NEW DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_base64 = '';

// Try to load logo and convert to base64 for PDF
if (file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_path)) {
    $logo_data = file_get_contents($_SERVER['DOCUMENT_ROOT'] . $logo_path);
    $logo_base64 = 'data:image/png;base64,' . base64_encode($logo_data);
}

// ================================================================
// GET PARAMETERS
// ================================================================
$report_type = isset($_GET['type']) ? $_GET['type'] : 'stock';
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : $user_branch_id;

// If user is not admin, force their branch
if (!$is_admin) {
    $branch_id = $user_branch_id;
}

// ================================================================
// GET DATA FOR REPORT - NEW DATABASE
// ================================================================

// 1. Total Medicines
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND status = 'active'
");
$stmt->execute([$branch_id]);
$total_medicines = $stmt->fetch()['count'] ?? 0;

// 2. Total Stock Quantity
$stmt = $db->prepare("
    SELECT SUM(quantity) as total 
    FROM medications_inventory 
    WHERE branch_id = ? AND status = 'active'
");
$stmt->execute([$branch_id]);
$total_stock = $stmt->fetch()['total'] ?? 0;

// 3. Low Stock
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND quantity <= reorder_level AND quantity > 0 AND status = 'active'
");
$stmt->execute([$branch_id]);
$low_stock_count = $stmt->fetch()['count'] ?? 0;

// 4. Out of Stock
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND quantity = 0 AND status = 'active'
");
$stmt->execute([$branch_id]);
$out_of_stock = $stmt->fetch()['count'] ?? 0;

// 5. Expired Medicines (all - active + inactive)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND expiry_date IS NOT NULL 
    AND expiry_date < CURDATE()
");
$stmt->execute([$branch_id]);
$expired_count = $stmt->fetch()['count'] ?? 0;

// 6. Expiring Soon (within 30 days)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND expiry_date IS NOT NULL 
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND status = 'active'
");
$stmt->execute([$branch_id]);
$expiring_soon = $stmt->fetch()['count'] ?? 0;

// 7. Categories Breakdown
$stmt = $db->prepare("
    SELECT category, COUNT(*) as count, SUM(quantity) as total_quantity
    FROM medications_inventory 
    WHERE branch_id = ? AND status = 'active'
    GROUP BY category
    ORDER BY count DESC
");
$stmt->execute([$branch_id]);
$categories_breakdown = $stmt->fetchAll();

// 8. Low Stock Items
$stmt = $db->prepare("
    SELECT medication_name, quantity, reorder_level, unit, batch_number
    FROM medications_inventory 
    WHERE branch_id = ? AND quantity <= reorder_level AND quantity > 0 AND status = 'active'
    ORDER BY quantity ASC
    LIMIT 20
");
$stmt->execute([$branch_id]);
$low_stock_items = $stmt->fetchAll();

// 9. Expired Items
$stmt = $db->prepare("
    SELECT medication_name, quantity, expiry_date, status, batch_number
    FROM medications_inventory 
    WHERE branch_id = ? AND expiry_date IS NOT NULL 
    AND expiry_date < CURDATE()
    ORDER BY expiry_date ASC
    LIMIT 20
");
$stmt->execute([$branch_id]);
$expired_items = $stmt->fetchAll();

// 10. Most Dispensed Medicines - NEW DB (prescriptions + prescription_items)
$stmt = $db->prepare("
    SELECT 
        pi.medication_name as medicine_name,
        SUM(pi.quantity) as total_dispensed,
        COUNT(DISTINCT p.id) as times_dispensed
    FROM prescription_items pi
    JOIN prescriptions p ON pi.prescription_id = p.id
    WHERE p.branch_id = ? AND p.status = 'dispensed'
    GROUP BY pi.medication_name
    ORDER BY total_dispensed DESC
    LIMIT 10
");
$stmt->execute([$branch_id]);
$most_dispensed = $stmt->fetchAll();

// 11. Top OTC Medicines - NEW DB (otc_sale_items + otc_sales)
$stmt = $db->prepare("
    SELECT 
        oi.item_name as medicine_name,
        SUM(oi.quantity) as total_sold,
        COUNT(DISTINCT os.id) as times_sold
    FROM otc_sale_items oi
    JOIN otc_sales os ON oi.sale_id = os.id
    WHERE os.branch_id = ? AND os.payment_status = 'paid'
    GROUP BY oi.item_name
    ORDER BY total_sold DESC
    LIMIT 10
");
$stmt->execute([$branch_id]);
$top_otc = $stmt->fetchAll();

// 12. Financial (Admin only) - NEW DB (bills)
$total_revenue = 0;
$total_prescription_revenue = 0;
$total_otc_revenue = 0;

if ($is_admin) {
    // Prescription revenue from bills
    $stmt = $db->prepare("
        SELECT SUM(total_amount) as total 
        FROM bills 
        WHERE branch_id = ? AND status = 'paid'
    ");
    $stmt->execute([$branch_id]);
    $total_prescription_revenue = $stmt->fetch()['total'] ?? 0;

    // OTC revenue from bills
    $stmt = $db->prepare("
        SELECT SUM(total_amount) as total 
        FROM bills 
        WHERE branch_id = ? AND status = 'paid' AND bill_number LIKE 'BILL-OTC-%'
    ");
    $stmt->execute([$branch_id]);
    $total_otc_revenue = $stmt->fetch()['total'] ?? 0;

    $total_revenue = $total_prescription_revenue + $total_otc_revenue;
}

// 13. Revenue by Month (Admin only)
$revenue_by_month = [];
if ($is_admin) {
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(total_amount) as total_revenue
        FROM bills 
        WHERE branch_id = ? AND status = 'paid'
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute([$branch_id]);
    $revenue_by_month = $stmt->fetchAll();
}

// ================================================================
// GENERATE REPORT PREVIEW
// ================================================================
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pharmacy Report - <?= ucfirst($report_type) ?></title>
    <style>
        /* ================================================================
           PDF STYLES - WITH MARGINS
           ================================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #1E293B;
            background: #f1f5f9;
            padding: 20px;
        }
        
        /* ✅ MAIN CONTAINER WITH MARGINS */
        .report-container {
            max-width: 1100px;
            margin: 0 auto;
            background: #ffffff;
            padding: 50px 60px 40px 60px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        /* ✅ PRINT MARGINS */
        @media print {
            body {
                padding: 0;
                background: #ffffff;
            }
            .report-container {
                padding: 60px 70px 50px 70px;
                margin: 0;
                border-radius: 0;
                box-shadow: none;
                max-width: 100%;
                height: 100%;
                min-height: 100vh;
            }
            .page-break {
                page-break-before: always;
            }
            .action-bar {
                display: none !important;
            }
            .no-print {
                display: none !important;
            }
        }
        
        /* ================================================================
           HEADER WITH LOGO
           ================================================================ */
        .report-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 15px;
            border-bottom: 3px solid #0B5ED7;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .report-header .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .report-header .logo {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            object-fit: contain;
            background: #f8fafc;
            padding: 4px;
            border: 1px solid #e2e8f0;
        }
        
        .report-header .brand-text .brand-name {
            font-size: 20px;
            font-weight: 700;
            color: #0B5ED7;
            line-height: 1.2;
        }
        
        .report-header .brand-text .brand-sub {
            font-size: 10px;
            color: #64748B;
            font-weight: 400;
        }
        
        .report-header .brand-text .new-db-tag {
            display: inline-block;
            font-size: 7px;
            font-weight: 700;
            color: #059669;
            background: #D1FAE5;
            padding: 1px 8px;
            border-radius: 10px;
            margin-top: 2px;
            letter-spacing: 0.03em;
        }
        
        .report-header .header-right {
            text-align: right;
        }
        
        .report-header .header-right .title {
            font-size: 18px;
            font-weight: 700;
            color: #1E293B;
        }
        
        .report-header .header-right .title .icon {
            margin-right: 6px;
        }
        
        .report-header .header-right .subtitle {
            font-size: 10px;
            color: #64748B;
            margin-top: 2px;
        }
        
        .report-header .header-right .date {
            font-size: 9px;
            color: #94A3B8;
            margin-top: 2px;
        }
        
        /* ================================================================
           STATS GRID
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 12px 14px;
            text-align: center;
            transition: all 0.2s ease;
        }
        
        .stat-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        
        .stat-box .number {
            font-size: 18px;
            font-weight: 700;
            color: #0B5ED7;
        }
        
        .stat-box .label {
            font-size: 9px;
            color: #64748B;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .stat-box .sub-label {
            font-size: 8px;
            color: #94A3B8;
            margin-top: 1px;
        }
        
        .stat-box.orange .number { color: #D97706; }
        .stat-box.red .number { color: #DC2626; }
        .stat-box.purple .number { color: #7C3AED; }
        .stat-box.green .number { color: #059669; }
        .stat-box.pink .number { color: #DB2777; }
        .stat-box.teal .number { color: #0D9488; }
        
        /* ================================================================
           SECTIONS
           ================================================================ */
        .section {
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #0B5ED7;
            padding-bottom: 6px;
            border-bottom: 2px solid #E2E8F0;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section-title .badge-count {
            background: #0B5ED7;
            color: white;
            font-size: 8px;
            padding: 1px 10px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        /* ================================================================
           TABLES
           ================================================================ */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        
        table thead th {
            background: #0B5ED7;
            color: white;
            padding: 6px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        table thead th:first-child {
            border-radius: 4px 0 0 0;
        }
        
        table thead th:last-child {
            border-radius: 0 4px 0 0;
        }
        
        table tbody td {
            padding: 5px 10px;
            border-bottom: 1px solid #E2E8F0;
        }
        
        table tbody tr:nth-child(even) {
            background: #F8FAFC;
        }
        
        table tbody tr:hover {
            background: #E8F0FE;
        }
        
        .rank-badge {
            display: inline-block;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            text-align: center;
            line-height: 22px;
            font-weight: 700;
            font-size: 10px;
            color: white;
        }
        
        .rank-badge.gold { background: #D97706; }
        .rank-badge.silver { background: #9CA3AF; }
        .rank-badge.bronze { background: #CD7F32; }
        .rank-badge.normal { background: #0B5ED7; }
        
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: 600;
        }
        
        .status-badge.active { background: #D1FAE5; color: #059669; }
        .status-badge.inactive { background: #FEE2E2; color: #DC2626; }
        .status-badge.expired { background: #FEE2E2; color: #DC2626; }
        
        /* ================================================================
           WATERMARK - NEW DB
           ================================================================ */
        .watermark {
            position: fixed;
            bottom: 30px;
            right: 30px;
            font-size: 12px;
            font-weight: 700;
            color: rgba(5, 150, 105, 0.08);
            transform: rotate(-15deg);
            pointer-events: none;
            z-index: 0;
            letter-spacing: 2px;
        }
        
        @media print {
            .watermark {
                display: block;
            }
        }
        
        /* ================================================================
           ACTION BUTTONS
           ================================================================ */
        .action-bar {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #E2E8F0;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 28px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        
        .btn-pdf {
            background: #DC2626;
            color: white;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
        }
        
        .btn-pdf:hover {
            background: #B91C1C;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.35);
        }
        
        .btn-primary {
            background: #0B5ED7;
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        
        .btn-primary:hover {
            background: #0A4CA8;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(11, 94, 215, 0.35);
        }
        
        .btn-success {
            background: #059669;
            color: white;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        }
        
        .btn-success:hover {
            background: #047857;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.35);
        }
        
        .btn-outline {
            background: transparent;
            color: #64748B;
            border: 2px solid #E2E8F0;
        }
        
        .btn-outline:hover {
            border-color: #0B5ED7;
            color: #0B5ED7;
            background: #E8F0FE;
            transform: translateY(-2px);
        }
        
        .btn i {
            font-size: 1rem;
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .report-footer {
            text-align: center;
            padding-top: 15px;
            border-top: 2px solid #E2E8F0;
            margin-top: 20px;
            font-size: 9px;
            color: #94A3B8;
        }
        
        .report-footer .brand {
            color: #0B5ED7;
            font-weight: 600;
        }
        
        .report-footer .new-db-footer {
            color: #059669;
            font-weight: 600;
            font-size: 8px;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .report-container {
                padding: 30px 35px 25px 35px;
            }
        }
        
        @media (max-width: 768px) {
            .report-container {
                padding: 20px 20px 15px 20px;
            }
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .report-header {
                flex-direction: column;
                text-align: center;
            }
            .report-header .header-left {
                flex-direction: column;
                text-align: center;
            }
            .report-header .header-right {
                text-align: center;
            }
            .report-header .header-right .title {
                font-size: 16px;
            }
        }
        
        @media (max-width: 480px) {
            .report-container {
                padding: 12px 12px 10px 12px;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .report-header .header-right .title {
                font-size: 14px;
            }
            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .btn {
                justify-content: center;
            }
            @media print {
                .report-container {
                    padding: 40px 30px 30px 30px;
                }
            }
        }
        
        /* ================================================================
           PRINT - FINAL MARGINS
           ================================================================ */
        @media print {
            .report-container {
                padding: 60px 70px 50px 70px;
            }
            .action-bar {
                display: none !important;
            }
            .stat-box {
                break-inside: avoid;
            }
            table {
                break-inside: auto;
            }
            tr {
                break-inside: avoid;
            }
            .page-break {
                page-break-before: always;
                border-top: none;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<!-- Watermark -->
<div class="watermark">NEW DB</div>

<div class="report-container">

    <!-- ================================================================ -->
    <!-- HEADER WITH LOGO -->
    <!-- ================================================================ -->
    <div class="report-header">
        <div class="header-left">
            <?php if (!empty($logo_base64)): ?>
                <img src="<?= $logo_base64 ?>" alt="Braick Logo" class="logo">
            <?php else: ?>
                <div class="logo" style="display:flex;align-items:center;justify-content:center;background:#0B5ED7;color:white;font-size:24px;font-weight:700;width:55px;height:55px;border-radius:12px;">
                    B
                </div>
            <?php endif; ?>
            <div class="brand-text">
                <div class="brand-name">Braick Dispensary</div>
                <div class="brand-sub">Pharmacy Department</div>
                <span class="new-db-tag"><i class="fas fa-database"></i> New Database</span>
            </div>
        </div>
        <div class="header-right">
            <div class="title">
                <?php 
                    $title_map = [
                        'stock' => '📦 Stock Report',
                        'medicines' => '💊 Medicines Report',
                        'financial' => '💰 Financial Report'
                    ];
                    echo $title_map[$report_type] ?? '📊 Report';
                ?>
            </div>
            <div class="subtitle">
                Branch: <?= htmlspecialchars($user_branch_name) ?> 
                | Generated by: <?= htmlspecialchars($user_full_name) ?>
            </div>
            <div class="date">
                Generated on: <?= date('F d, Y h:i A') ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STOCK REPORT -->
    <!-- ================================================================ -->
    <?php if ($report_type === 'stock'): ?>
    
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="number"><?= number_format($total_medicines) ?></div>
            <div class="label">Total Medicines</div>
            <div class="sub-label">Active in inventory</div>
        </div>
        <div class="stat-box orange">
            <div class="number"><?= number_format($low_stock_count) ?></div>
            <div class="label">Low Stock</div>
            <div class="sub-label">Below reorder level</div>
        </div>
        <div class="stat-box red">
            <div class="number"><?= number_format($out_of_stock) ?></div>
            <div class="label">Out of Stock</div>
            <div class="sub-label">Quantity = 0</div>
        </div>
        <div class="stat-box pink">
            <div class="number"><?= number_format($expired_count) ?></div>
            <div class="label">Expired</div>
            <div class="sub-label">Past expiry date</div>
        </div>
        <div class="stat-box purple">
            <div class="number"><?= number_format($expiring_soon) ?></div>
            <div class="label">Expiring Soon</div>
            <div class="sub-label">Within 30 days</div>
        </div>
    </div>
    
    <!-- Categories Breakdown -->
    <div class="section">
        <div class="section-title">
            📂 Categories Breakdown
            <span class="badge-count"><?= count($categories_breakdown) ?></span>
        </div>
        <?php if (count($categories_breakdown) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th style="text-align:center;">Medicines</th>
                    <th style="text-align:right;">Total Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories_breakdown as $cat): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($cat['category'] ?? 'Uncategorized') ?></strong></td>
                        <td style="text-align:center;"><?= $cat['count'] ?></td>
                        <td style="text-align:right;"><?= number_format($cat['total_quantity'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:#94A3B8; text-align:center; padding:20px;">No categories found</p>
        <?php endif; ?>
    </div>
    
    <!-- Low Stock Items -->
    <div class="section">
        <div class="section-title">⚠️ Low Stock Items <span class="badge-count"><?= count($low_stock_items) ?></span></div>
        <?php if (count($low_stock_items) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th style="text-align:center;">Current Qty</th>
                    <th style="text-align:center;">Reorder Level</th>
                    <th>Batch</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($low_stock_items as $item): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['medication_name']) ?></strong></td>
                        <td style="text-align:center;color:#D97706;font-weight:600;"><?= $item['quantity'] ?></td>
                        <td style="text-align:center;"><?= $item['reorder_level'] ?></td>
                        <td><?= htmlspecialchars($item['batch_number'] ?? 'N/A') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:#94A3B8; text-align:center; padding:20px;">✅ No low stock items</p>
        <?php endif; ?>
    </div>
    
    <!-- Expired Items -->
    <div class="section">
        <div class="section-title">🗑️ Expired Items <span class="badge-count"><?= count($expired_items) ?></span></div>
        <?php if (count($expired_items) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th style="text-align:center;">Quantity</th>
                    <th>Expiry Date</th>
                    <th>Batch</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expired_items as $item): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['medication_name']) ?></strong></td>
                        <td style="text-align:center;"><?= $item['quantity'] ?></td>
                        <td style="color:#DC2626;font-weight:600;"><?= date('d/m/Y', strtotime($item['expiry_date'])) ?></td>
                        <td><?= htmlspecialchars($item['batch_number'] ?? 'N/A') ?></td>
                        <td>
                            <span class="status-badge <?= ($item['status'] ?? 'active') === 'active' ? 'active' : 'inactive' ?>">
                                <?= ucfirst($item['status'] ?? 'Active') ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:#94A3B8; text-align:center; padding:20px;">✅ No expired items</p>
        <?php endif; ?>
    </div>
    
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- MEDICINES REPORT -->
    <!-- ================================================================ -->
    <?php if ($report_type === 'medicines'): ?>
    
    <!-- Most Dispensed -->
    <div class="section">
        <div class="section-title">💊 Most Dispensed (Prescriptions) <span class="badge-count">Top 10</span></div>
        <?php if (count($most_dispensed) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Medicine</th>
                    <th style="text-align:right;">Total Qty</th>
                    <th style="text-align:right;">Times</th>
                </tr>
            </thead>
            <tbody>
                <?php $rank = 1; ?>
                <?php foreach ($most_dispensed as $med): ?>
                    <tr>
                        <td>
                            <span class="rank-badge <?= $rank <= 3 ? ['gold', 'silver', 'bronze'][$rank-1] : 'normal' ?>">
                                <?= $rank ?>
                            </span>
                        </td>
                        <td><strong><?= htmlspecialchars($med['medicine_name']) ?></strong></td>
                        <td style="text-align:right;"><?= number_format($med['total_dispensed']) ?></td>
                        <td style="text-align:right;"><?= number_format($med['times_dispensed']) ?></td>
                    </tr>
                    <?php $rank++; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:#94A3B8; text-align:center; padding:20px;">No prescription data found</p>
        <?php endif; ?>
    </div>
    
    <!-- Top OTC -->
    <div class="section">
        <div class="section-title">🛒 Top OTC Medicines <span class="badge-count">Top 10</span></div>
        <?php if (count($top_otc) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Medicine</th>
                    <th style="text-align:right;">Total Qty</th>
                    <th style="text-align:right;">Times</th>
                </tr>
            </thead>
            <tbody>
                <?php $rank = 1; ?>
                <?php foreach ($top_otc as $med): ?>
                    <tr>
                        <td>
                            <span class="rank-badge <?= $rank <= 3 ? ['gold', 'silver', 'bronze'][$rank-1] : 'normal' ?>">
                                <?= $rank ?>
                            </span>
                        </td>
                        <td><strong><?= htmlspecialchars($med['medicine_name']) ?></strong></td>
                        <td style="text-align:right;"><?= number_format($med['total_sold']) ?></td>
                        <td style="text-align:right;"><?= number_format($med['times_sold']) ?></td>
                    </tr>
                    <?php $rank++; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:#94A3B8; text-align:center; padding:20px;">No OTC data found</p>
        <?php endif; ?>
    </div>
    
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FINANCIAL REPORT (ADMIN ONLY) -->
    <!-- ================================================================ -->
    <?php if ($is_admin && $report_type === 'financial'): ?>
    
    <!-- Revenue Stats -->
    <div class="stats-grid">
        <div class="stat-box green">
            <div class="number">TSh <?= number_format($total_revenue) ?></div>
            <div class="label">Total Revenue</div>
            <div class="sub-label">All paid bills</div>
        </div>
        <div class="stat-box blue">
            <div class="number">TSh <?= number_format($total_prescription_revenue) ?></div>
            <div class="label">Prescription Revenue</div>
            <div class="sub-label">From prescriptions</div>
        </div>
        <div class="stat-box purple">
            <div class="number">TSh <?= number_format($total_otc_revenue) ?></div>
            <div class="label">OTC Revenue</div>
            <div class="sub-label">From OTC sales</div>
        </div>
    </div>
    
    <!-- Revenue by Month -->
    <div class="section">
        <div class="section-title">📊 Revenue by Month (Last 6 Months)</div>
        <?php if (count($revenue_by_month) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th style="text-align:right;">Revenue (TSh)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($revenue_by_month as $month): ?>
                    <tr>
                        <td><strong><?= date('F Y', strtotime($month['month'] . '-01')) ?></strong></td>
                        <td style="text-align:right;font-weight:600;color:#0B5ED7;">
                            TSh <?= number_format($month['total_revenue'] ?? 0) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:#94A3B8; text-align:center; padding:20px;">No revenue data found</p>
        <?php endif; ?>
    </div>
    
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ACTION BUTTONS -->
    <!-- ================================================================ -->
    <div class="action-bar no-print">
        <button onclick="downloadPDF()" class="btn btn-pdf">
            <i class="fas fa-file-pdf"></i> Download as PDF
        </button>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Print Report
        </button>
        <a href="reports.php?type=<?= $report_type ?>" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Reports
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <div class="report-footer">
        <p>
            <span class="brand">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Pharmacy Report
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Generated: <?= date('Y-m-d H:i:s') ?>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span class="new-db-footer"><i class="fas fa-database"></i> New DB</span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
        <p style="margin-top:4px;font-size:8px;color:#CBD5E1;">
            <?php if ($report_type === 'stock'): ?>
            📦 Stock summary, categories, low stock and expired items.
            <?php elseif ($report_type === 'medicines'): ?>
            💊 Most dispensed (prescriptions) and top OTC medicines.
            <?php elseif ($report_type === 'financial'): ?>
            💰 Revenue summary and monthly breakdown.
            <?php endif; ?>
            <span style="margin-left:10px;">🔒 Generated by <?= htmlspecialchars($user_full_name) ?></span>
        </p>
    </div>

</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DOWNLOAD PDF - Uses print to save as PDF
    // ================================================================
    function downloadPDF() {
        var btn = document.querySelector('.btn-pdf');
        var originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing PDF...';
        btn.disabled = true;
        
        setTimeout(function() {
            window.print();
            btn.innerHTML = originalText;
            btn.disabled = false;
        }, 500);
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        // Ctrl+P - Print
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
        // Ctrl+D - Download PDF
        if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
            e.preventDefault();
            downloadPDF();
        }
        // Ctrl+B - Back
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            window.location.href = 'reports.php?type=<?= $report_type ?>';
        }
    });

    console.log('%c📄 Braick - Pharmacy Report (NEW DATABASE)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Using NEW DATABASE: dispensary_db', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Report Type: <?= ucfirst($report_type) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🖼️ Logo: <?= !empty($logo_base64) ? '✅ Loaded' : '❌ Using fallback' ?>', 'font-size:13px; color:#34D399;');
    console.log('%c📐 Margins: TOP:60px RIGHT:70px BOTTOM:50px LEFT:70px', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Tables: medications_inventory, prescriptions, prescription_items, otc_sales, otc_sale_items, bills', 'font-size:13px; color:#34D399;');
    console.log('%c⌨️ Ctrl+P - Print | Ctrl+D - Download PDF | Ctrl+B - Back', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>