<?php
// ================================================================
// FILE: frontend/pages/pharmacy/export_pdf.php
// PHARMACY - EXPORT REPORT AS PDF (WITH PREVIEW)
// ================================================================
// FIXED: Added Braick Logo + Proper Margins
// USAGE: export_pdf.php?type=stock&branch_id=1
//        export_pdf.php?type=medicines&branch_id=1
//        export_pdf.php?type=financial&branch_id=1
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// SESSION - Default to pharm.peter
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 5;
    $_SESSION['full_name'] = 'Peter Ngalula';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'pharm.peter';
    $_SESSION['email'] = 'peter@braick.com';
    $_SESSION['phone'] = '+255 700 000 004';
    $_SESSION['is_admin'] = false;
    $_SESSION['profile_pic'] = '';
}

$user_id = $_SESSION['user_id'] ?? 5;
$user_full_name = $_SESSION['full_name'] ?? 'Peter Ngalula';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$is_admin = $_SESSION['is_admin'] ?? false;

$db = getDB();

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_base64 = '';

// Try to load logo and convert to base64 for PDF
if (file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_path)) {
    $logo_data = file_get_contents($_SERVER['DOCUMENT_ROOT'] . $logo_path);
    $logo_base64 = 'data:image/png;base64,' . base64_encode($logo_data);
} else {
    // Fallback: create simple text logo
    $logo_base64 = '';
}

// ================================================================
// GET PARAMETERS
// ================================================================
$report_type = isset($_GET['type']) ? $_GET['type'] : 'stock';
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : $user_branch_id;

// ================================================================
// GET DATA FOR REPORT
// ================================================================

// 1. Total Medicines
$stmt = $db->prepare("SELECT COUNT(*) as count FROM medications_inventory WHERE branch_id = ? AND status = 'active'");
$stmt->execute([$branch_id]);
$total_medicines = $stmt->fetch()['count'] ?? 0;

// 2. Total Stock Quantity
$stmt = $db->prepare("SELECT SUM(quantity) as total FROM medications_inventory WHERE branch_id = ? AND status = 'active'");
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
    SELECT medication_name, quantity, reorder_level, unit
    FROM medications_inventory 
    WHERE branch_id = ? AND quantity <= reorder_level AND quantity > 0 AND status = 'active'
    ORDER BY quantity ASC
    LIMIT 20
");
$stmt->execute([$branch_id]);
$low_stock_items = $stmt->fetchAll();

// 9. Expired Items
$stmt = $db->prepare("
    SELECT medication_name, quantity, expiry_date, status
    FROM medications_inventory 
    WHERE branch_id = ? AND expiry_date IS NOT NULL 
    AND expiry_date < CURDATE()
    ORDER BY expiry_date ASC
    LIMIT 20
");
$stmt->execute([$branch_id]);
$expired_items = $stmt->fetchAll();

// 10. Most Dispensed Medicines
$stmt = $db->prepare("
    SELECT 
        medicine_name,
        SUM(quantity) as total_dispensed,
        COUNT(*) as times_dispensed
    FROM prescription_sale_items psi
    JOIN prescription_sales ps ON psi.sale_id = ps.id
    WHERE ps.branch_id = ? AND ps.status = 'dispensed'
    GROUP BY medicine_name
    ORDER BY total_dispensed DESC
    LIMIT 10
");
$stmt->execute([$branch_id]);
$most_dispensed = $stmt->fetchAll();

// 11. Top OTC Medicines
$stmt = $db->prepare("
    SELECT 
        medicine_name,
        SUM(quantity) as total_sold,
        COUNT(*) as times_sold
    FROM otc_sale_items osi
    JOIN otc_sales os ON osi.sale_id = os.id
    WHERE os.branch_id = ?
    GROUP BY medicine_name
    ORDER BY total_sold DESC
    LIMIT 10
");
$stmt->execute([$branch_id]);
$top_otc = $stmt->fetchAll();

// 12. Financial (Admin only)
$total_revenue = 0;
$total_prescription_revenue = 0;
$total_otc_revenue = 0;

if ($is_admin) {
    $stmt = $db->prepare("
        SELECT SUM(total_amount) as total 
        FROM prescription_sales 
        WHERE branch_id = ? AND status = 'dispensed'
    ");
    $stmt->execute([$branch_id]);
    $total_prescription_revenue = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("
        SELECT SUM(total_amount) as total 
        FROM otc_sales 
        WHERE branch_id = ?
    ");
    $stmt->execute([$branch_id]);
    $total_otc_revenue = $stmt->fetch()['total'] ?? 0;

    $total_revenue = $total_prescription_revenue + $total_otc_revenue;
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
            padding: 50px 60px 40px 60px; /* TOP RIGHT BOTTOM LEFT */
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
                padding: 60px 70px 50px 70px; /* TOP RIGHT BOTTOM LEFT */
                margin: 0;
                border-radius: 0;
                box-shadow: none;
                max-width: 100%;
                height: 100%;
                min-height: 100vh;
            }
            /* Ensure content fits in one page */
            .page-break {
                page-break-before: always;
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
        
        .report-header .header-right {
            text-align: right;
        }
        
        .report-header .header-right .title {
            font-size: 18px;
            font-weight: 700;
            color: #1E293B;
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
        
        .stat-box.orange .number { color: #D97706; }
        .stat-box.red .number { color: #DC2626; }
        .stat-box.purple .number { color: #7C3AED; }
        .stat-box.green .number { color: #059669; }
        .stat-box.pink .number { color: #DB2777; }
        
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
            /* Print margins for mobile */
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
                display: none;
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
        }
    </style>
</head>
<body>

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
        </div>
        <div class="stat-box orange">
            <div class="number"><?= number_format($low_stock_count) ?></div>
            <div class="label">Low Stock</div>
        </div>
        <div class="stat-box red">
            <div class="number"><?= number_format($out_of_stock) ?></div>
            <div class="label">Out of Stock</div>
        </div>
        <div class="stat-box pink">
            <div class="number"><?= number_format($expired_count) ?></div>
            <div class="label">Expired</div>
        </div>
        <div class="stat-box purple">
            <div class="number"><?= number_format($expiring_soon) ?></div>
            <div class="label">Expiring Soon</div>
        </div>
    </div>
    
    <!-- Categories Breakdown -->
    <div class="section">
        <div class="section-title">📂 Categories Breakdown</div>
        <?php if (count($categories_breakdown) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Number of Medicines</th>
                    <th>Total Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories_breakdown as $cat): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($cat['category'] ?? 'Uncategorized') ?></strong></td>
                        <td><?= $cat['count'] ?></td>
                        <td><?= number_format($cat['total_quantity'] ?? 0) ?></td>
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
        <div class="section-title">⚠️ Low Stock Items</div>
        <?php if (count($low_stock_items) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th>Current Qty</th>
                    <th>Reorder Level</th>
                    <th>Unit</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($low_stock_items as $item): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['medication_name']) ?></strong></td>
                        <td style="color:#D97706;font-weight:600;"><?= $item['quantity'] ?></td>
                        <td><?= $item['reorder_level'] ?></td>
                        <td><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:#94A3B8; text-align:center; padding:20px;">No low stock items</p>
        <?php endif; ?>
    </div>
    
    <!-- Expired Items -->
    <div class="section">
        <div class="section-title">🗑️ Expired Items</div>
        <?php if (count($expired_items) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th>Quantity</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expired_items as $item): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['medication_name']) ?></strong></td>
                        <td><?= $item['quantity'] ?></td>
                        <td style="color:#DC2626;font-weight:600;"><?= date('d/m/Y', strtotime($item['expiry_date'])) ?></td>
                        <td><?= ucfirst($item['status'] ?? 'active') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:#94A3B8; text-align:center; padding:20px;">No expired items</p>
        <?php endif; ?>
    </div>
    
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- MEDICINES REPORT -->
    <!-- ================================================================ -->
    <?php if ($report_type === 'medicines'): ?>
    
    <!-- Most Dispensed -->
    <div class="section">
        <div class="section-title">💊 Most Dispensed (Prescriptions)</div>
        <?php if (count($most_dispensed) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Medicine</th>
                    <th>Total Qty</th>
                    <th>Times</th>
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
                        <td><?= number_format($med['total_dispensed']) ?></td>
                        <td><?= number_format($med['times_dispensed']) ?></td>
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
        <div class="section-title">🛒 Top OTC Medicines</div>
        <?php if (count($top_otc) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Medicine</th>
                    <th>Total Qty</th>
                    <th>Times</th>
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
                        <td><?= number_format($med['total_sold']) ?></td>
                        <td><?= number_format($med['times_sold']) ?></td>
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
        </div>
        <div class="stat-box blue">
            <div class="number">TSh <?= number_format($total_prescription_revenue) ?></div>
            <div class="label">Prescription Revenue</div>
        </div>
        <div class="stat-box purple">
            <div class="number">TSh <?= number_format($total_otc_revenue) ?></div>
            <div class="label">OTC Revenue</div>
        </div>
    </div>
    
    <!-- Revenue by Month -->
    <div class="section">
        <div class="section-title">📊 Revenue by Month (Last 6 Months)</div>
        <?php 
        $stmt = $db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(total_amount) as total_revenue
            FROM (
                SELECT created_at, total_amount FROM prescription_sales WHERE branch_id = ? AND status = 'dispensed'
                UNION ALL
                SELECT created_at, total_amount FROM otc_sales WHERE branch_id = ?
            ) as combined
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->execute([$branch_id, $branch_id]);
        $revenue_by_month = $stmt->fetchAll();
        ?>
        <?php if (count($revenue_by_month) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Revenue (TSh)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($revenue_by_month as $month): ?>
                    <tr>
                        <td><strong><?= date('F Y', strtotime($month['month'] . '-01')) ?></strong></td>
                        <td>TSh <?= number_format($month['total_revenue'] ?? 0) ?></td>
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
    <div class="action-bar">
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
            &copy; <?= date('Y') ?> All rights reserved
        </p>
        <p style="margin-top:4px;font-size:8px;color:#CBD5E1;">
            This report was generated automatically from the Braick Dispensary System.
            <?php if ($report_type === 'stock'): ?>
            Report includes stock summary, categories, low stock and expired items.
            <?php elseif ($report_type === 'medicines'): ?>
            Report includes most dispensed and top OTC medicines.
            <?php elseif ($report_type === 'financial'): ?>
            Report includes revenue summary and monthly breakdown.
            <?php endif; ?>
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
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
            e.preventDefault();
            downloadPDF();
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            window.location.href = 'reports.php?type=<?= $report_type ?>';
        }
    });

    console.log('%c📄 Pharmacy Report Preview (With Logo & Margins)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Report Type: <?= ucfirst($report_type) ?>', 'font-size:12px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:12px; color:#64748B;');
    console.log('%c🖼️ Logo: <?= !empty($logo_base64) ? '✅ Loaded' : '❌ Using fallback' ?>', 'font-size:12px; color:#34D399;');
    console.log('%c📐 Margins: TOP:60px RIGHT:70px BOTTOM:50px LEFT:70px', 'font-size:12px; color:#34D399;');
    console.log('%c⌨️ Ctrl+P - Print | Ctrl+D - Download PDF | Ctrl+B - Back', 'font-size:12px; color:#34D399;');
</script>

</body>
</html>