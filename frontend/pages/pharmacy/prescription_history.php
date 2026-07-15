<?php
// ================================================================
// FILE: frontend/pages/pharmacy/prescription_history.php
// PHARMACY - PRESCRIPTION HISTORY
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

$db = getDB();

// ================================================================
// GET FILTERS
// ================================================================
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$patient_filter = isset($_GET['patient']) ? (int)$_GET['patient'] : 0;

// ================================================================
// GET PRESCRIPTION HISTORY
// ================================================================
$query = "
    SELECT ps.*, 
           p.full_name as patient_name, p.patient_id,
           u.full_name as doctor_name,
           (SELECT COUNT(*) FROM prescription_sale_items WHERE sale_id = ps.id) as item_count,
           (SELECT GROUP_CONCAT(medicine_name) FROM prescription_sale_items WHERE sale_id = ps.id) as medicine_names
    FROM prescription_sales ps
    LEFT JOIN patients p ON ps.patient_id = p.id
    LEFT JOIN users u ON ps.doctor_id = u.id
    WHERE ps.branch_id = ?
";

if ($filter === 'dispensed') {
    $query .= " AND ps.status = 'dispensed'";
} elseif ($filter === 'pending') {
    $query .= " AND ps.status = 'pending'";
} elseif ($filter === 'cancelled') {
    $query .= " AND ps.status = 'cancelled'";
} elseif ($filter === 'today') {
    $query .= " AND DATE(ps.created_at) = CURDATE()";
}

if (!empty($search)) {
    $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR ps.sale_number LIKE ?)";
}

if ($patient_filter > 0) {
    $query .= " AND ps.patient_id = ?";
}

if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND DATE(ps.created_at) BETWEEN ? AND ?";
}

$query .= " ORDER BY ps.created_at DESC";

$stmt = $db->prepare($query);

$params = [$user_branch_id];

if (!empty($search)) {
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($patient_filter > 0) {
    $params[] = $patient_filter;
}

if (!empty($date_from) && !empty($date_to)) {
    $params[] = $date_from;
    $params[] = $date_to;
}

$stmt->execute($params);
$prescriptions = $stmt->fetchAll();

// ================================================================
// GET PATIENTS FOR FILTER
// ================================================================
$patients = [];
$stmt = $db->prepare("
    SELECT DISTINCT p.id, p.full_name, p.patient_id 
    FROM patients p
    JOIN prescription_sales ps ON p.id = ps.patient_id
    WHERE ps.branch_id = ?
    ORDER BY p.full_name
");
$stmt->execute([$user_branch_id]);
$patients = $stmt->fetchAll();

// ================================================================
// GET STATUS COUNTS
// ================================================================
$counts = [];
$statuses = ['pending', 'dispensed', 'cancelled'];
foreach ($statuses as $status) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescription_sales WHERE branch_id = ? AND status = ?");
    $stmt->execute([$user_branch_id, $status]);
    $counts[$status] = $stmt->fetch()['count'] ?? 0;
}

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$pending_prescriptions = $counts['pending'] ?? 0;
$low_stock_count = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND quantity <= reorder_level AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_count = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $low_stock_count = 0;
}

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE
// ================================================================
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

<style>
    /* ================================================================
       PRESCRIPTION HISTORY STYLES
       ================================================================ */
    
    .filter-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 12px;
    }
    
    .filter-tab {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        background: var(--bg-body);
        color: var(--text-secondary);
        border: 2px solid transparent;
    }
    
    .filter-tab:hover {
        background: var(--primary-bg);
        color: var(--primary);
    }
    
    .filter-tab.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    
    .filter-tab .count {
        background: rgba(255,255,255,0.2);
        padding: 0 6px;
        border-radius: 10px;
        font-size: 0.6rem;
        margin-left: 4px;
    }
    
    .filter-tab.active .count {
        background: rgba(255,255,255,0.25);
    }
    
    .filter-tab.pending { background: #FEF3C7; color: #D97706; }
    .filter-tab.pending.active { background: #D97706; color: white; }
    
    .filter-tab.dispensed { background: #D1FAE5; color: #059669; }
    .filter-tab.dispensed.active { background: #059669; color: white; }
    
    .filter-tab.cancelled { background: #FEE2E2; color: #DC2626; }
    .filter-tab.cancelled.active { background: #DC2626; color: white; }
    
    [data-theme="dark"] .filter-tab.pending { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .filter-tab.dispensed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .filter-tab.cancelled { background: #3A1A1A; color: #F87171; }
    
    .prescription-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 16px;
    }
    
    .prescription-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
    }
    
    .prescription-card .prescription-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 8px;
        padding-bottom: 8px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .prescription-card .prescription-header .sale-number {
        font-weight: 700;
        font-size: 1rem;
        color: var(--text-primary);
        font-family: monospace;
    }
    
    .prescription-card .prescription-body {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: 12px;
        margin-bottom: 10px;
    }
    
    .prescription-card .prescription-body .info-item .label {
        font-size: 0.6rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .prescription-card .prescription-body .info-item .value {
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--text-primary);
    }
    
    .prescription-card .prescription-medicines {
        background: var(--bg-body);
        border-radius: 8px;
        padding: 10px 14px;
        margin-bottom: 10px;
        max-height: 80px;
        overflow-y: auto;
    }
    
    .prescription-card .prescription-medicines::-webkit-scrollbar {
        width: 4px;
    }
    
    .prescription-card .prescription-medicines::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 4px;
    }
    
    .prescription-card .prescription-medicines .medicine-item {
        display: flex;
        justify-content: space-between;
        padding: 3px 0;
        border-bottom: 1px solid var(--border-color);
        font-size: 0.8rem;
    }
    
    .prescription-card .prescription-medicines .medicine-item:last-child {
        border-bottom: none;
    }
    
    .prescription-card .prescription-medicines .medicine-item .med-price {
        font-weight: 600;
        color: var(--primary);
    }
    
    .prescription-card .prescription-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    
    .btn-action {
        padding: 4px 12px;
        border-radius: 6px;
        font-size: 0.65rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .btn-action:hover {
        transform: scale(1.05);
    }
    
    .btn-view {
        background: var(--primary);
        color: white;
    }
    .btn-view:hover {
        background: var(--primary-dark);
    }
    
    .btn-print {
        background: #64748B;
        color: white;
    }
    .btn-print:hover {
        background: #475569;
    }
    
    .btn-pdf {
        background: #DC2626;
        color: white;
    }
    .btn-pdf:hover {
        background: #B91C1C;
    }
    
    .btn-reprint {
        background: #059669;
        color: white;
    }
    .btn-reprint:hover {
        background: #047857;
    }
    
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    
    .summary-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 2px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
        text-decoration: none;
        color: var(--text-primary);
    }
    
    .summary-card:hover {
        border-color: var(--primary);
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
    }
    
    .summary-card .number {
        font-size: 1.5rem;
        font-weight: 700;
    }
    
    .summary-card .label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        margin-top: 2px;
    }
    
    .summary-card.pending .number { color: #D97706; }
    .summary-card.dispensed .number { color: #059669; }
    .summary-card.cancelled .number { color: #DC2626; }
    
    .filter-section {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 12px 16px;
        border: 2px solid var(--border-color);
        margin-bottom: 16px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }
    
    .filter-section .form-control {
        padding: 6px 12px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.8rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
    }
    
    .filter-section .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .filter-section .btn-filter {
        padding: 6px 16px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        background: var(--primary);
        color: white;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .filter-section .btn-filter:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
    }
    
    .filter-section .btn-clear {
        padding: 6px 16px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .filter-section .btn-clear:hover {
        border-color: var(--primary);
        color: var(--primary);
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
    
    .empty-state .sub {
        font-size: 0.8rem;
        margin-top: 4px;
    }
    
    .history-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }
    
    .history-stats .stat-item {
        background: var(--bg-card);
        border-radius: 10px;
        padding: 10px 14px;
        border: 2px solid var(--border-color);
        text-align: center;
    }
    
    .history-stats .stat-item .stat-number {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .history-stats .stat-item .stat-label {
        font-size: 0.6rem;
        color: var(--text-secondary);
    }
    
    @media (max-width: 768px) {
        .prescription-card .prescription-body {
            grid-template-columns: 1fr 1fr;
        }
        .prescription-card .prescription-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .summary-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .filter-section {
            flex-direction: column;
            align-items: stretch;
        }
        .filter-tabs {
            flex-wrap: wrap;
        }
        .filter-tab {
            font-size: 0.7rem;
            padding: 4px 12px;
        }
        .history-stats {
            grid-template-columns: repeat(2, 1fr);
        }
        .prescription-card .prescription-actions {
            justify-content: flex-start;
        }
    }
    
    @media (max-width: 480px) {
        .summary-grid {
            grid-template-columns: 1fr 1fr;
        }
        .btn-action {
            font-size: 0.55rem;
            padding: 2px 6px;
        }
        .prescription-card .prescription-body {
            grid-template-columns: 1fr;
        }
        .history-stats {
            grid-template-columns: 1fr 1fr;
        }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .filter-section, .filter-tabs, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .prescription-card { border: 1px solid #ddd !important; page-break-inside: avoid; }
        .page-header { border-bottom: 2px solid #0B5ED7 !important; }
    }
</style>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search prescriptions..." 
                   value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-history mr-2" style="color: var(--primary);"></i> Prescription History
            </h1>
            <p class="page-subtitle">
                View all dispensed prescriptions
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <?php if ($counts['dispensed'] > 0): ?>
                    <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                        <i class="fas fa-check-circle mr-1"></i> <?= $counts['dispensed'] ?> dispensed
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="summary-grid animate-fade-in-up">
        <a href="?status=pending" class="summary-card pending">
            <p class="number"><?= $counts['pending'] ?? 0 ?></p>
            <p class="label"><i class="fas fa-clock mr-1"></i> Pending</p>
        </a>
        <a href="?status=dispensed" class="summary-card dispensed">
            <p class="number"><?= $counts['dispensed'] ?? 0 ?></p>
            <p class="label"><i class="fas fa-check-circle mr-1"></i> Dispensed</p>
        </a>
        <a href="?status=cancelled" class="summary-card cancelled">
            <p class="number"><?= $counts['cancelled'] ?? 0 ?></p>
            <p class="label"><i class="fas fa-times-circle mr-1"></i> Cancelled</p>
        </a>
    </div>

    <!-- History Stats -->
    <?php if (count($prescriptions) > 0): 
        $total_amount = 0;
        foreach ($prescriptions as $pres) {
            $total_amount += $pres['total_amount'];
        }
    ?>
    <div class="history-stats animate-fade-in-up">
        <div class="stat-item">
            <p class="stat-number"><?= count($prescriptions) ?></p>
            <p class="stat-label">Total Prescriptions</p>
        </div>
        <div class="stat-item">
            <p class="stat-number">TSh <?= number_format($total_amount) ?></p>
            <p class="stat-label">Total Amount</p>
        </div>
        <div class="stat-item">
            <p class="stat-number"><?= count($patients) ?></p>
            <p class="stat-label">Patients</p>
        </div>
        <div class="stat-item">
            <p class="stat-number">
                <?= count($prescriptions) > 0 ? round($total_amount / count($prescriptions), 0) : 0 ?>
            </p>
            <p class="stat-label">Avg per Prescription</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="filter-tabs animate-fade-in-up">
        <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
            <i class="fas fa-list mr-1"></i> All
            <span class="count"><?= array_sum($counts) ?></span>
        </a>
        <a href="?filter=pending" class="filter-tab pending <?= $filter === 'pending' ? 'active' : '' ?>">
            <i class="fas fa-clock mr-1"></i> Pending
            <span class="count"><?= $counts['pending'] ?? 0 ?></span>
        </a>
        <a href="?filter=dispensed" class="filter-tab dispensed <?= $filter === 'dispensed' ? 'active' : '' ?>">
            <i class="fas fa-check-circle mr-1"></i> Dispensed
            <span class="count"><?= $counts['dispensed'] ?? 0 ?></span>
        </a>
        <a href="?filter=cancelled" class="filter-tab cancelled <?= $filter === 'cancelled' ? 'active' : '' ?>">
            <i class="fas fa-times-circle mr-1"></i> Cancelled
            <span class="count"><?= $counts['cancelled'] ?? 0 ?></span>
        </a>
        <a href="?filter=today" class="filter-tab <?= $filter === 'today' ? 'active' : '' ?>">
            <i class="fas fa-calendar-day mr-1"></i> Today
        </a>
    </div>

    <!-- Filter Section -->
    <div class="filter-section animate-fade-in-up">
        <form method="GET" action="" class="flex flex-wrap items-center gap-3 w-full">
            
            <input type="hidden" name="filter" value="<?= $filter ?>">
            
            <input type="text" name="search" class="form-control" placeholder="Search by patient, ID..." 
                   value="<?= htmlspecialchars($search) ?>" style="flex:1; min-width:120px;">
            
            <select name="patient" class="form-control" style="min-width:150px;">
                <option value="">All Patients</option>
                <?php foreach ($patients as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $patient_filter == $p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['patient_id']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>" style="width:140px;">
            <span class="text-sm text-gray-400">to</span>
            <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>" style="width:140px;">
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-search mr-1"></i> Apply
            </button>
            
            <a href="prescription_history.php" class="btn-clear">
                <i class="fas fa-times mr-1"></i> Clear
            </a>
        </form>
    </div>

    <!-- Prescriptions List -->
    <div class="animate-fade-in-up">
        <?php if (count($prescriptions) > 0): ?>
            <?php foreach ($prescriptions as $prescription): ?>
                <div class="prescription-card">
                    <div class="prescription-header">
                        <div class="sale-number">
                            <?= htmlspecialchars($prescription['sale_number']) ?>
                            <span class="badge <?= 
                                $prescription['status'] === 'pending' ? 'badge-pending' :
                                ($prescription['status'] === 'dispensed' ? 'badge-dispensed' : 'badge-cancelled')
                            ?>">
                                <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                            </span>
                        </div>
                        <div class="text-sm text-gray-400">
                            <i class="fas fa-calendar-alt mr-1"></i>
                            <?= date('M d, Y h:i A', strtotime($prescription['created_at'])) ?>
                            <?php if ($prescription['status'] === 'dispensed' && $prescription['dispensed_at']): ?>
                                <span class="ml-2">
                                    <i class="fas fa-check-circle text-green-600 mr-1"></i>
                                    Dispensed: <?= date('M d, Y h:i A', strtotime($prescription['dispensed_at'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="prescription-body">
                        <div class="info-item">
                            <div class="label">Patient</div>
                            <div class="value"><?= htmlspecialchars($prescription['patient_name'] ?? 'Unknown') ?></div>
                            <div class="text-xs text-gray-400">ID: <?= htmlspecialchars($prescription['patient_id'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Doctor</div>
                            <div class="value"><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Items</div>
                            <div class="value"><?= $prescription['item_count'] ?> items</div>
                        </div>
                        <div class="info-item">
                            <div class="label">Total Amount</div>
                            <div class="value" style="color: #0D9488; font-weight:700;">
                                TSh <?= number_format($prescription['total_amount'] ?? 0) ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="prescription-medicines">
                        <?php 
                            $stmt = $db->prepare("SELECT medicine_name, quantity, total_price FROM prescription_sale_items WHERE sale_id = ?");
                            $stmt->execute([$prescription['id']]);
                            $items = $stmt->fetchAll();
                        ?>
                        <?php foreach ($items as $item): ?>
                            <div class="medicine-item">
                                <span><?= htmlspecialchars($item['medicine_name']) ?> <span class="text-xs text-gray-400">x<?= $item['quantity'] ?></span></span>
                                <span class="med-price">TSh <?= number_format($item['total_price'] ?? 0) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="prescription-actions">
                        <a href="view_sale.php?type=prescription&id=<?= $prescription['id'] ?>" class="btn-action btn-view">
                            <i class="fas fa-eye"></i> View
                        </a>
                        
                        <?php if ($prescription['status'] === 'dispensed'): ?>
                            <a href="print_receipt.php?type=prescription&id=<?= $prescription['id'] ?>" class="btn-action btn-reprint" target="_blank">
                                <i class="fas fa-print"></i> Reprint Receipt
                            </a>
                            <a href="download_pdf.php?type=prescription&id=<?= $prescription['id'] ?>" class="btn-action btn-pdf">
                                <i class="fas fa-file-pdf"></i> PDF
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription"></i>
                <p>No prescriptions found</p>
                <p class="sub">
                    <?php if (!empty($search) || $patient_filter > 0 || !empty($date_from)): ?>
                        Try adjusting your filters.
                    <?php else: ?>
                        No prescriptions have been processed yet.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer mt-5">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Prescription History
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

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        var filter = '<?= $filter ?>';
        var url = 'prescription_history.php?filter=' + filter;
        if (query) url += '&search=' + encodeURIComponent(query);
        window.location.href = url;
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
    }

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
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
            searchInput?.select();
        }
        if (e.key === 'Escape' && document.activeElement === searchInput) {
            searchInput.value = '';
            performSearch();
        }
    });

    console.log('%c💊 Braick - Prescription History', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Pending: <?= $counts['pending'] ?? 0 ?> | Dispensed: <?= $counts['dispensed'] ?? 0 ?> | Cancelled: <?= $counts['cancelled'] ?? 0 ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📋 Total Prescriptions: <?= count($prescriptions) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>