<?php
// ================================================================
// FILE: frontend/pages/pharmacy/dispensed.php
// PHARMACY - DISPENSED MEDICINES REPORT
// ✅ Inaonyesha dawa zote zilizotolewa (dispensed)
// ✅ Kwa tarehe, mteja, na muuzaji
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
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : date('Y-m-d');
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';

// ================================================================
// TRY TO DETECT DISPENSED TABLE
// ================================================================
$dispensed_table = null;
$possible_tables = ['dispensed_medications', 'dispensed_items', 'dispensing_records', 'dispensed', 'sales_items', 'sales'];

foreach ($possible_tables as $tbl) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '$tbl'");
        if ($stmt->rowCount() > 0) {
            $dispensed_table = $tbl;
            break;
        }
    } catch (Exception $e) {}
}

$dispensed_items = [];
$total_dispensed = 0;
$total_quantity = 0;
$total_value = 0;

if ($dispensed_table) {
    try {
        // Build query based on available table
        $query = "SELECT * FROM `$dispensed_table` WHERE 1=1";
        $params = [];
        
        // Date filter
        if (!empty($date_from)) {
            $query .= " AND DATE(created_at) >= ?";
            $params[] = $date_from;
        }
        if (!empty($date_to)) {
            $query .= " AND DATE(created_at) <= ?";
            $params[] = $date_to;
        }
        
        // Search filter
        if (!empty($search)) {
            $query .= " AND (medication_name LIKE ? OR patient_name LIKE ? OR batch_number LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $query .= " ORDER BY created_at DESC LIMIT 500";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $dispensed_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Statistics
        $total_dispensed = count($dispensed_items);
        foreach ($dispensed_items as $item) {
            $total_quantity += $item['quantity'] ?? 0;
            $total_value += ($item['quantity'] ?? 0) * ($item['selling_price'] ?? $item['unit_price'] ?? 0);
        }
        
    } catch (Exception $e) {
        $dispensed_items = [];
    }
}

// ================================================================
// GET STATISTICS
// ================================================================
$today_dispensed = 0;
$today_value = 0;

if ($dispensed_table) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count, COALESCE(SUM(quantity * COALESCE(selling_price, unit_price, 0)), 0) as total_value
            FROM `$dispensed_table` 
            WHERE DATE(created_at) = CURDATE()
        ");
        $stmt->execute();
        $today_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $today_dispensed = $today_data['count'] ?? 0;
        $today_value = $today_data['total_value'] ?? 0;
    } catch (Exception $e) {}
}

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
    <title>Dispensed Medicines - Braick Dispensary</title>
    
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
            background: linear-gradient(135deg, #0D9488, #0F766E);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(13, 148, 136, 0.25);
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
        
        .stat-card.teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
        .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
        
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
        
        .table-wrap {
            overflow-x: auto;
        }
        
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
            background: var(--teal);
            border-bottom: 3px solid #0F766E;
            white-space: nowrap;
        }
        
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        
        .data-table tbody tr:nth-child(even) { background: var(--teal-light); }
        .data-table tbody tr:hover td { background: var(--primary-light); }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) { background: #1E293B; }
        
        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .batch-number {
            font-family: monospace;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
            background: var(--primary-light);
            color: var(--primary);
        }
        
        .badge-qty {
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
            background: var(--success-light);
            color: var(--success);
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
        
        .message-box.info {
            background: var(--primary-light);
            color: #0A4CA8;
            border: 2px solid #6EA8FE;
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
                <i class="fas fa-prescription-bottle-alt"></i> Dispensed Medicines
                <span class="badge-count"><?= number_format($total_dispensed) ?> records</span>
            </h1>
            <p class="page-subtitle">
                View all dispensed medicines history
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
        <div class="stat-card teal">
            <div>
                <p class="stat-label">Total Dispensed</p>
                <p class="stat-number"><?= number_format($total_dispensed) ?></p>
                <span class="stat-trend"><i class="fas fa-pills"></i> All records</span>
            </div>
            <div class="stat-icon"><i class="fas fa-pills"></i></div>
        </div>
        
        <div class="stat-card blue">
            <div>
                <p class="stat-label">Today Dispensed</p>
                <p class="stat-number"><?= number_format($today_dispensed) ?></p>
                <span class="stat-trend"><i class="fas fa-calendar-day"></i> <?= date('M d, Y') ?></span>
            </div>
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
        </div>
        
        <div class="stat-card green">
            <div>
                <p class="stat-label">Total Value</p>
                <p class="stat-number">TSh <?= number_format($total_value) ?></p>
                <span class="stat-trend"><i class="fas fa-coins"></i> All dispensed</span>
            </div>
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
        </div>
    </div>

    <!-- INFO MESSAGE -->
    <?php if (!$dispensed_table): ?>
        <div class="message-box info">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Dispensed table haipo bado.</strong> 
                Ukiwa na table ya dispensed (kama <code>dispensed_medications</code>, <code>dispensed_items</code>, au <code>sales</code>), 
                record zitaonekana hapa automatically.
            </div>
        </div>
    <?php endif; ?>

    <!-- FILTERS -->
    <div class="card animate-fade-in-up">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="filter-group" style="flex:2;min-width:200px;">
                <label>Search</label>
                <input type="text" name="search" placeholder="Medicine name, patient, batch..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="filter-group" style="flex:0 0 auto;">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Filter
                </button>
            </div>
            <div class="filter-group" style="flex:0 0 auto;">
                <a href="dispensed.php" class="btn-reset">
                    <i class="fas fa-times"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- TABLE -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list" style="color:var(--teal);"></i> Dispensed Records
                <span style="font-size:0.8rem;color:var(--text-secondary);">(<?= number_format(count($dispensed_items)) ?> records)</span>
            </h3>
            <button onclick="window.print()" class="btn-reset" style="padding:6px 14px;">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
        
        <?php if (count($dispensed_items) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="border-radius: 8px 0 0 0;">#</th>
                            <th>Date</th>
                            <th>Medicine</th>
                            <th>Quantity</th>
                            <th>Batch</th>
                            <th>Patient</th>
                            <th>Dispensed By</th>
                            <th>Price</th>
                            <th style="border-radius: 0 8px 0 0;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; foreach ($dispensed_items as $item): ?>
                            <tr>
                                <td><?= $counter++ ?></td>
                                <td style="font-size:0.75rem;">
                                    <?= date('d/m/Y H:i', strtotime($item['created_at'] ?? $item['dispensed_at'] ?? 'now')) ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($item['medication_name'] ?? $item['item_name'] ?? 'N/A') ?></strong>
                                </td>
                                <td>
                                    <span class="badge-qty">
                                        <?= number_format($item['quantity'] ?? 0) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($item['batch_number'])): ?>
                                        <span class="batch-number"><?= htmlspecialchars($item['batch_number']) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($item['patient_name'] ?? $item['customer_name'] ?? 'Walk-in') ?></td>
                                <td><?= htmlspecialchars($item['dispensed_by_name'] ?? $item['created_by_name'] ?? 'N/A') ?></td>
                                <td>TSh <?= number_format($item['selling_price'] ?? $item['unit_price'] ?? 0) ?></td>
                                <td><strong>TSh <?= number_format(($item['quantity'] ?? 0) * ($item['selling_price'] ?? $item['unit_price'] ?? 0)) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription-bottle"></i>
                <p>No dispensed medicines found</p>
                <p class="sub">
                    <?php if (!$dispensed_table): ?>
                        Create a dispensed table ili records zionekane hapa
                    <?php else: ?>
                        Hakuna records kwenye kipindi hiki
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Dispensed Medicines Report
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

    console.log('%c💊 Braick - Dispensed Medicines Report', 'font-size:18px; font-weight:bold; color:#0D9488;');
    console.log('%c✅ Table detected: <?= $dispensed_table ?? "NONE" ?>', 'font-size:13px; color:#34D399;');
    console.log('%c📊 Total Dispensed: <?= $total_dispensed ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💰 Total Value: TSh <?= number_format($total_value) ?>', 'font-size:13px; color:#059669;');
</script>

</body>
</html>