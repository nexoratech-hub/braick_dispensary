<?php
// ================================================================
// FILE: frontend/pages/pharmacy/out_of_stock.php
// PHARMACY - OUT OF STOCK MEDICINES REPORT
// ✅ Inaonyesha dawa zilizoisha kabisa (quantity = 0)
// USING NEW DATABASE: dispensary_db
// BRAICK DISPENSARY
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';

// ================================================================
// GET OUT OF STOCK MEDICINES (quantity = 0)
// ✅ Inaonyesha ACTIVE pekee (kwa sababu inactive haziuzwi)
// ✅ Lakini zinaonekana kama active hata kama qty=0
// ================================================================
$query = "
    SELECT 
        id,
        medication_name,
        category,
        unit,
        quantity,
        reorder_level,
        selling_price,
        unit_cost,
        supplier,
        expiry_date,
        batch_number,
        status,
        created_at,
        updated_at,
        DATEDIFF(expiry_date, CURDATE()) as days_remaining
    FROM medications_inventory 
    WHERE branch_id = ?
    AND quantity = 0
    AND status = 'active'
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
";

$params = [$user_branch_id];

if (!empty($category_filter)) {
    $query .= " AND category = ?";
    $params[] = $category_filter;
}

if (!empty($search)) {
    $query .= " AND medication_name LIKE ?";
    $params[] = "%$search%";
}

$query .= " ORDER BY medication_name ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$out_of_stock_medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT medication_name) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND quantity = 0 AND status = 'active'
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
");
$stmt->execute([$user_branch_id]);
$total_out_of_stock = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(DISTINCT medication_name) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND quantity = 0 AND status = 'active'
    AND expiry_date IS NOT NULL AND expiry_date != '0000-00-00' 
    AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute([$user_branch_id]);
$expiring_out_of_stock = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(DISTINCT medication_name) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND quantity = 0 AND status = 'active'
    AND category IN (SELECT category FROM medications_inventory WHERE branch_id = ? AND quantity > 0 AND status = 'active')
");
$stmt->execute([$user_branch_id, $user_branch_id]);
$has_alternative = $stmt->fetch()['count'] ?? 0;

// Get categories for filter
$stmt = $db->query("SELECT DISTINCT category FROM medications_inventory WHERE category IS NOT NULL AND category != '' AND category != '__other__' ORDER BY category");
$categories = $stmt->fetchAll();

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Out of Stock - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A3D8A;
            --primary-light: #E8F0FE;
            --success: #059669;
            --success-light: #D1FAE5;
            --warning: #D97706;
            --warning-light: #FEF3C7;
            --danger: #DC2626;
            --danger-light: #FEE2E2;
            --purple: #7C3AED;
            
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
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: linear-gradient(135deg, #DC2626, #991B1B);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(220, 38, 38, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -20%;
            width: 300px; height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i { font-size: 2rem; opacity: 0.9; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .branch-tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .page-header .badge-count {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            backdrop-filter: blur(4px);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            border-radius: 16px;
            padding: 18px 20px;
            border: none;
            transition: all 0.3s;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            min-height: 90px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }
        
        .stat-card.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
        .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        
        .stat-card .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            background: rgba(255,255,255,0.15);
            color: white;
            flex-shrink: 0;
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.85);
            font-weight: 500;
            margin-top: 2px;
        }
        
        .stat-card .stat-trend {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
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
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .filter-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-form .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
            min-width: 140px;
        }
        
        .filter-form label {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .filter-form input,
        .filter-form select {
            padding: 8px 14px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.85rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .filter-form input:focus,
        .filter-form select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .btn-search {
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            background: var(--primary);
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-reset {
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-reset:hover {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        .table-wrap { overflow-x: auto; }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--danger);
            border-bottom: 3px solid #991B1B;
            white-space: nowrap;
        }
        
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        
        .data-table tbody tr:nth-child(even) { background: var(--danger-light); }
        .data-table tbody tr:hover td { background: var(--warning-light); }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) { background: #3A1A1A; }
        
        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .status-badge {
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-badge.active { background: var(--success-light); color: var(--success); }
        .status-badge.danger { background: var(--danger-light); color: var(--danger); }
        
        .batch-number {
            font-family: monospace;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
            background: var(--primary-light);
            color: var(--primary);
        }
        
        .action-btn {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--primary);
            color: white;
        }
        
        .action-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 12px;
        }
        
        .empty-state p { font-size: 0.95rem; }
        .empty-state .sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 4px; }
        
        .message-box {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        
        .message-box.success {
            background: var(--success-light);
            color: #065F46;
            border: 2px solid #6EE7B7;
        }
        
        .message-box.warning {
            background: var(--warning-light);
            color: #92400E;
            border: 2px solid #FCD34D;
        }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .filter-form { flex-direction: column; align-items: stretch; }
            .data-table { font-size: 0.7rem; }
            .data-table th, .data-table td { padding: 5px 8px; }
            .stat-card .stat-number { font-size: 1.3rem; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-times-circle"></i> Out of Stock
                <span class="badge-count"><?= $total_out_of_stock ?> items</span>
            </h1>
            <p class="page-subtitle">
                Medicines zilizoisha kabisa (quantity = 0) - Haiwezi kuuzwa
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="inventory.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

    <!-- STATISTICS -->
    <div class="stats-grid animate-fade-in-up">
        <a href="out_of_stock.php" class="stat-card red">
            <div>
                <p class="stat-label">Out of Stock</p>
                <p class="stat-number"><?= number_format($total_out_of_stock) ?></p>
                <span class="stat-trend"><i class="fas fa-times-circle"></i> Need restocking</span>
            </div>
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        </a>
        
        <a href="out_of_stock.php?filter=expiring" class="stat-card orange">
            <div>
                <p class="stat-label">Expiring Soon</p>
                <p class="stat-number"><?= number_format($expiring_out_of_stock) ?></p>
                <span class="stat-trend"><i class="fas fa-clock"></i> Within 30 days</span>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </a>
        
        <div class="stat-card purple">
            <div>
                <p class="stat-label">Categories Affected</p>
                <p class="stat-number"><?= number_format($has_alternative) ?></p>
                <span class="stat-trend"><i class="fas fa-tags"></i> With alternatives</span>
            </div>
            <div class="stat-icon"><i class="fas fa-tags"></i></div>
        </div>
    </div>

    <!-- SUCCESS MESSAGE -->
    <?php if ($total_out_of_stock == 0 && empty($search)): ?>
        <div class="message-box success" id="successMessage">
            <i class="fas fa-check-circle"></i>
            🎉 Hakuna dawa iliyoisha stock! Medicines zote zina quantity.
        </div>
        <script>
            setTimeout(function() {
                var msg = document.getElementById('successMessage');
                if (msg) {
                    msg.style.opacity = '0';
                    msg.style.transform = 'translateY(-10px)';
                    setTimeout(function() { msg.style.display = 'none'; }, 500);
                }
            }, 5000);
        </script>
    <?php endif; ?>

    <!-- FILTERS -->
    <div class="card animate-fade-in-up">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Category</label>
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category_filter === $cat['category'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group" style="flex:2;min-width:200px;">
                <label>Search</label>
                <input type="text" name="search" placeholder="Search medicine..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="filter-group" style="flex:0 0 auto;">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Filter
                </button>
            </div>
            <div class="filter-group" style="flex:0 0 auto;">
                <a href="out_of_stock.php" class="btn-reset">
                    <i class="fas fa-times"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- TABLE -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list" style="color:var(--danger);"></i> 
                Out of Stock Medicines
                <span style="font-size:0.8rem;color:var(--text-secondary);">
                    (<?= number_format(count($out_of_stock_medicines)) ?> items)
                </span>
            </h3>
            <button onclick="window.print()" class="btn-reset" style="padding:6px 14px;">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
        
        <?php if (count($out_of_stock_medicines) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="border-radius: 8px 0 0 0;">#</th>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Current Qty</th>
                            <th>Reorder Level</th>
                            <th>Status</th>
                            <th>Expiry Date</th>
                            <th>Batch Number</th>
                            <th>Supplier</th>
                            <th style="border-radius: 0 8px 0 0;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; foreach ($out_of_stock_medicines as $item): ?>
                            <tr>
                                <td><?= $counter++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['medication_name']) ?></strong>
                                    <div style="font-size:0.65rem;color:var(--text-muted);">
                                        <?= htmlspecialchars($item['unit'] ?? 'pcs') ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                <td>
                                    <strong style="color: var(--danger);"><?= $item['quantity'] ?></strong>
                                </td>
                                <td><?= $item['reorder_level'] ?></td>
                                <td>
                                    <span class="status-badge danger">
                                        <i class="fas fa-times-circle"></i> Out of Stock
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($item['expiry_date']) && $item['expiry_date'] !== '0000-00-00'): ?>
                                        <?= date('M d, Y', strtotime($item['expiry_date'])) ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['batch_number'])): ?>
                                        <span class="batch-number"><?= htmlspecialchars($item['batch_number']) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($item['supplier'] ?? 'N/A') ?></td>
                                <td>
                                    <a href="view_inventory.php?id=<?= $item['id'] ?>&type=medicine" class="action-btn">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color:var(--success);"></i>
                <p>Hakuna dawa iliyoisha stock</p>
                <p class="sub">Medicines zote zina quantity 🎉</p>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Out of Stock Report
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c💊 Braick - Out of Stock Report', 'font-size:18px; font-weight:bold; color:#DC2626;');
    console.log('%c📦 Total Out of Stock: <?= $total_out_of_stock ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c⏰ Expiring Soon: <?= $expiring_out_of_stock ?>', 'font-size:13px; color:#D97706;');
</script>

</body>
</html>