<?php
// ================================================================
// FILE: frontend/pages/pharmacy/reports.php
// PHARMACY - REPORTS DASHBOARD (STOCK & MEDICINES ONLY)
// BRAICK DISPENSARY
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
// GET REPORT PARAMETERS
// ================================================================
$report_type = isset($_GET['type']) ? $_GET['type'] : 'stock';

// ================================================================
// ================================================================
// STOCK REPORTS
// ================================================================

// 1. Total Medicines
$stmt = $db->prepare("SELECT COUNT(*) as count FROM medications_inventory WHERE branch_id = ? AND status = 'active'");
$stmt->execute([$user_branch_id]);
$total_medicines = $stmt->fetch()['count'] ?? 0;

// 2. Total Stock Quantity
$stmt = $db->prepare("SELECT SUM(quantity) as total FROM medications_inventory WHERE branch_id = ? AND status = 'active'");
$stmt->execute([$user_branch_id]);
$total_stock = $stmt->fetch()['total'] ?? 0;

// 3. Low Stock
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND quantity <= reorder_level AND quantity > 0 AND status = 'active'
");
$stmt->execute([$user_branch_id]);
$low_stock_count = $stmt->fetch()['count'] ?? 0;

// 4. Out of Stock
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND quantity = 0 AND status = 'active'
");
$stmt->execute([$user_branch_id]);
$out_of_stock = $stmt->fetch()['count'] ?? 0;

// 5. Expired Medicines
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND expiry_date IS NOT NULL 
    AND expiry_date < CURDATE()
    AND status = 'active'
");
$stmt->execute([$user_branch_id]);
$expired_count = $stmt->fetch()['count'] ?? 0;

// 6. Expiring Soon (within 30 days)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND expiry_date IS NOT NULL 
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND status = 'active'
");
$stmt->execute([$user_branch_id]);
$expiring_soon = $stmt->fetch()['count'] ?? 0;

// 7. Categories Breakdown
$stmt = $db->prepare("
    SELECT category, COUNT(*) as count, SUM(quantity) as total_quantity
    FROM medications_inventory 
    WHERE branch_id = ? AND status = 'active'
    GROUP BY category
    ORDER BY count DESC
");
$stmt->execute([$user_branch_id]);
$categories_breakdown = $stmt->fetchAll();

// ================================================================
// MEDICINES REPORTS
// ================================================================

// 8. Most Dispensed Medicines (Prescriptions)
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
$stmt->execute([$user_branch_id]);
$most_dispensed = $stmt->fetchAll();

// 9. Top OTC Medicines Sold
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
$stmt->execute([$user_branch_id]);
$top_otc = $stmt->fetchAll();

// ================================================================
// FINANCIAL REPORTS (Only for Admin)
// ================================================================
$total_revenue = 0;
$total_prescription_revenue = 0;
$total_otc_revenue = 0;
$revenue_by_month = [];

if ($is_admin) {
    // Total Prescription Revenue
    $stmt = $db->prepare("
        SELECT SUM(total_amount) as total 
        FROM prescription_sales 
        WHERE branch_id = ? AND status = 'dispensed'
    ");
    $stmt->execute([$user_branch_id]);
    $total_prescription_revenue = $stmt->fetch()['total'] ?? 0;

    // Total OTC Revenue
    $stmt = $db->prepare("
        SELECT SUM(total_amount) as total 
        FROM otc_sales 
        WHERE branch_id = ?
    ");
    $stmt->execute([$user_branch_id]);
    $total_otc_revenue = $stmt->fetch()['total'] ?? 0;

    $total_revenue = $total_prescription_revenue + $total_otc_revenue;

    // Revenue by Month (Last 6 months)
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
    $stmt->execute([$user_branch_id, $user_branch_id]);
    $revenue_by_month = $stmt->fetchAll();
}

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$pending_prescriptions_sidebar = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescription_sales WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_prescriptions_sidebar = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions_sidebar = 0;
}

$low_stock_sidebar = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND quantity <= reorder_level AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_sidebar = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $low_stock_sidebar = 0;
}

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

$profile_pic = $_SESSION['profile_pic'] ?? '';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!-- ================================================================ -->
<!-- STYLES -->
<!-- ================================================================ -->
<style>
    :root {
        --primary: #0B5ED7;
        --primary-dark: #0A3D8A;
        --primary-light: #E8F0FE;
        --success: #059669;
        --success-dark: #047857;
        --success-light: #D1FAE5;
        --warning: #D97706;
        --warning-light: #FEF3C7;
        --danger: #DC2626;
        --danger-light: #FEE2E2;
        --purple: #7C3AED;
        --purple-light: #EDE9FE;
        --teal: #0D9488;
        --teal-light: #CCFBF1;
        
        --bg-body: #F1F5F9;
        --bg-card: #FFFFFF;
        --border-color: #E2E8F0;
        --text-primary: #0F172A;
        --text-secondary: #475569;
        --text-muted: #94A3B8;
        --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
    }
    
    [data-theme="dark"] {
        --bg-body: #0F172A;
        --bg-card: #1E293B;
        --border-color: #334155;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --text-muted: #64748B;
        --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --shadow-lg: 0 8px 30px rgba(0,0,0,0.4);
    }
    
    .report-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 20px;
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 12px;
    }
    
    .report-tab {
        padding: 8px 20px;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid transparent;
    }
    
    .report-tab:hover {
        background: var(--primary-light);
        color: var(--primary);
    }
    
    .report-tab.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    
    .report-tab i {
        margin-right: 6px;
    }
    
    [data-theme="dark"] .report-tab:hover {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        border-radius: 14px;
        padding: 16px 18px;
        border: none;
        transition: all 0.3s;
        color: white;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        min-height: 80px;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    
    .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
    .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .stat-card.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
    .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    .stat-card.teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
    .stat-card.pink { background: linear-gradient(135deg, #DB2777, #BE185D); }
    
    .stat-card .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        background: rgba(255,255,255,0.15);
        color: white;
        flex-shrink: 0;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover .stat-icon {
        transform: scale(1.1) rotate(-5deg);
        background: rgba(255,255,255,0.25);
    }
    
    .stat-card .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: white;
        line-height: 1.2;
    }
    
    .stat-card .stat-label {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.85);
        font-weight: 500;
        margin-top: 2px;
    }
    
    .stat-card .stat-trend {
        font-size: 0.55rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 20px;
        background: rgba(255,255,255,0.15);
        color: white;
        display: inline-block;
        margin-top: 4px;
    }
    
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }
    
    .card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .card-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .card-title i {
        color: var(--primary);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
        padding: 4px 14px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.7rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .btn-outline:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .btn-sm {
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    
    .table-wrap {
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }
    
    .data-table thead th {
        text-align: left;
        padding: 8px 12px;
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: white;
        background: var(--primary);
        border-bottom: 2px solid var(--primary-dark);
        white-space: nowrap;
    }
    
    .data-table thead th:first-child {
        border-radius: 6px 0 0 0;
    }
    
    .data-table thead th:last-child {
        border-radius: 0 6px 0 0;
    }
    
    .data-table tbody tr:nth-child(even) {
        background: var(--primary-light);
    }
    
    .data-table tbody tr:hover td {
        background: var(--success-light);
    }
    
    [data-theme="dark"] .data-table tbody tr:nth-child(even) {
        background: #1E293B;
    }
    
    [data-theme="dark"] .data-table tbody tr:hover td {
        background: #1A3A2A;
    }
    
    .data-table td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .rank-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        font-weight: 700;
        font-size: 0.75rem;
        color: white;
    }
    
    .rank-badge.gold { background: #D97706; }
    .rank-badge.silver { background: #9CA3AF; }
    .rank-badge.bronze { background: #CD7F32; }
    .rank-badge.normal { background: var(--primary); }
    
    .empty-state {
        text-align: center;
        padding: 30px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 2.5rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 8px;
    }
    
    .empty-state p {
        font-size: 0.85rem;
    }
    
    .empty-state .sub {
        font-size: 0.7rem;
        color: var(--text-muted);
    }
    
    .admin-only {
        display: <?= $is_admin ? 'block' : 'none' ?>;
    }
    
    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .report-tabs {
            justify-content: center;
        }
        .report-tab {
            font-size: 0.7rem;
            padding: 5px 12px;
        }
        .card {
            padding: 12px 14px;
        }
        .stat-card .stat-number {
            font-size: 1.2rem;
        }
        .stat-card {
            padding: 12px 14px;
            min-height: 65px;
        }
        .stat-card .stat-icon {
            width: 32px;
            height: 32px;
            font-size: 0.8rem;
        }
        .data-table {
            font-size: 0.7rem;
        }
        .data-table th,
        .data-table td {
            padding: 4px 8px;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .stat-card .stat-number {
            font-size: 1rem;
        }
        .stat-card .stat-label {
            font-size: 0.6rem;
        }
        .stat-card .stat-icon {
            width: 28px;
            height: 28px;
            font-size: 0.7rem;
        }
        .stat-card {
            padding: 8px 10px;
            min-height: 55px;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i> Reports
            </h1>
            <p class="page-subtitle">
                View pharmacy stock and medicines reports
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <?php if ($is_admin): ?>
                    <span class="ml-2 inline-flex bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs border border-purple-200">
                        <i class="fas fa-crown mr-1"></i> Admin
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <button onclick="window.print()" class="btn-outline">
                <i class="fas fa-print"></i> Print Report
            </button>
            <a href="dashboard.php" class="btn-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- REPORT TABS -->
    <!-- ================================================================ -->
    <div class="report-tabs animate-fade-in-up">
        <a href="reports.php?type=stock" class="report-tab <?= $report_type === 'stock' ? 'active' : '' ?>">
            <i class="fas fa-warehouse"></i> Stock Report
        </a>
        <a href="reports.php?type=medicines" class="report-tab <?= $report_type === 'medicines' ? 'active' : '' ?>">
            <i class="fas fa-pills"></i> Medicines Report
        </a>
        <?php if ($is_admin): ?>
        <a href="reports.php?type=financial" class="report-tab <?= $report_type === 'financial' ? 'active' : '' ?>">
            <i class="fas fa-money-bill-wave"></i> Financial Report
        </a>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- STOCK REPORT -->
    <!-- ================================================================ -->
    <?php if ($report_type === 'stock'): ?>
    
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-card blue">
            <div>
                <p class="stat-number"><?= number_format($total_medicines) ?></p>
                <p class="stat-label"><i class="fas fa-pills mr-1"></i> Total Medicines</p>
                <span class="stat-trend">Active</span>
            </div>
            <div class="stat-icon"><i class="fas fa-pills"></i></div>
        </div>
        
        <div class="stat-card teal">
            <div>
                <p class="stat-number"><?= number_format($total_stock) ?></p>
                <p class="stat-label"><i class="fas fa-boxes mr-1"></i> Total Stock</p>
                <span class="stat-trend">All quantities</span>
            </div>
            <div class="stat-icon"><i class="fas fa-boxes"></i></div>
        </div>
        
        <div class="stat-card orange">
            <div>
                <p class="stat-number"><?= number_format($low_stock_count) ?></p>
                <p class="stat-label"><i class="fas fa-exclamation-triangle mr-1"></i> Low Stock</p>
                <span class="stat-trend">Below reorder</span>
            </div>
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
        
        <div class="stat-card red">
            <div>
                <p class="stat-number"><?= number_format($out_of_stock) ?></p>
                <p class="stat-label"><i class="fas fa-times-circle mr-1"></i> Out of Stock</p>
                <span class="stat-trend">Empty</span>
            </div>
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        </div>
        
        <div class="stat-card pink">
            <div>
                <p class="stat-number"><?= number_format($expired_count) ?></p>
                <p class="stat-label"><i class="fas fa-skull mr-1"></i> Expired</p>
                <span class="stat-trend">Past expiry</span>
            </div>
            <div class="stat-icon"><i class="fas fa-skull"></i></div>
        </div>
        
        <div class="stat-card purple">
            <div>
                <p class="stat-number"><?= number_format($expiring_soon) ?></p>
                <p class="stat-label"><i class="fas fa-clock mr-1"></i> Expiring Soon</p>
                <span class="stat-trend">Within 30 days</span>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
    </div>
    
    <!-- Category Breakdown -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-tags" style="color: var(--purple);"></i>
                Categories Breakdown
            </h3>
        </div>
        <?php if (count($categories_breakdown) > 0): ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 6px 0 0 0;">Category</th>
                        <th>Number of Medicines</th>
                        <th style="border-radius: 0 6px 0 0;">Total Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories_breakdown as $cat): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($cat['category'] ?? 'Uncategorized') ?></strong>
                            </td>
                            <td><?= $cat['count'] ?></td>
                            <td><?= number_format($cat['total_quantity'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-tags"></i>
            <p>No categories found</p>
            <p class="sub">Add medicines with categories to see breakdown</p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- MEDICINES REPORT -->
    <!-- ================================================================ -->
    <?php if ($report_type === 'medicines'): ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        <!-- Most Dispensed Prescriptions -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-prescription" style="color: var(--primary);"></i>
                    Most Dispensed (Prescriptions)
                </h3>
            </div>
            <?php if (count($most_dispensed) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="border-radius: 6px 0 0 0;">#</th>
                            <th>Medicine</th>
                            <th>Total Qty</th>
                            <th style="border-radius: 0 6px 0 0;">Times</th>
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
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription"></i>
                <p>No prescription data found</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Top OTC Medicines -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-shopping-cart" style="color: var(--purple);"></i>
                    Top OTC Medicines
                </h3>
            </div>
            <?php if (count($top_otc) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="border-radius: 6px 0 0 0;">#</th>
                            <th>Medicine</th>
                            <th>Total Qty</th>
                            <th style="border-radius: 0 6px 0 0;">Times</th>
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
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <p>No OTC data found</p>
            </div>
            <?php endif; ?>
        </div>
        
    </div>
    
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FINANCIAL REPORT (ADMIN ONLY) -->
    <!-- ================================================================ -->
    <?php if ($is_admin && $report_type === 'financial'): ?>
    
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-card green">
            <div>
                <p class="stat-number">TSh <?= number_format($total_revenue) ?></p>
                <p class="stat-label"><i class="fas fa-money-bill-wave mr-1"></i> Total Revenue</p>
                <span class="stat-trend">All time</span>
            </div>
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
        </div>
        
        <div class="stat-card blue">
            <div>
                <p class="stat-number">TSh <?= number_format($total_prescription_revenue) ?></p>
                <p class="stat-label"><i class="fas fa-prescription mr-1"></i> Prescription Revenue</p>
                <span class="stat-trend">Dispensed</span>
            </div>
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
        </div>
        
        <div class="stat-card purple">
            <div>
                <p class="stat-number">TSh <?= number_format($total_otc_revenue) ?></p>
                <p class="stat-label"><i class="fas fa-shopping-cart mr-1"></i> OTC Revenue</p>
                <span class="stat-trend">Walk-in</span>
            </div>
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
        </div>
    </div>
    
    <!-- Revenue by Month -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-chart-bar" style="color: var(--primary);"></i>
                Revenue by Month (Last 6 Months)
            </h3>
        </div>
        <?php if (count($revenue_by_month) > 0): ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 6px 0 0 0;">Month</th>
                        <th style="border-radius: 0 6px 0 0;">Revenue (TSh)</th>
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
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-chart-bar"></i>
            <p>No revenue data found</p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer mt-5">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Reports
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
        
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
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
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
    });

    console.log('%c📊 Braick - Reports Dashboard', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Report Type: <?= ucfirst($report_type) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📦 Total Medicines: <?= $total_medicines ?> | Low Stock: <?= $low_stock_count ?>', 'font-size:13px; color:#D97706;');
    <?php if ($is_admin): ?>
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue) ?>', 'font-size:13px; color:#0B5ED7;');
    <?php endif; ?>
</script>

</body>
</html>