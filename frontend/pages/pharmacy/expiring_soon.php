<?php
// ================================================================
// FILE: frontend/pages/pharmacy/expiring_soon.php
// PHARMACY - EXPIRING SOON MEDICINES
// SHOW MEDICINES EXPIRING WITHIN 30 DAYS
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Pharmacy User
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 9;
    $_SESSION['full_name'] = 'Pharmacy Staff';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
}

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET BRANCH FILTER
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$search_term = $_GET['search'] ?? '';
$days_filter = isset($_GET['days']) ? (int)$_GET['days'] : 30;

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET EXPIRING SOON MEDICINES
// ================================================================
$today = date('Y-m-d');
$expiry_threshold = date('Y-m-d', strtotime("+$days_filter days"));

$query = "
    SELECT 
        mi.*,
        b.name as branch_name,
        DATEDIFF(mi.expiry_date, CURDATE()) as days_remaining,
        CASE 
            WHEN mi.expiry_date < CURDATE() THEN 'Expired'
            WHEN mi.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Critical'
            WHEN mi.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY) THEN 'Urgent'
            WHEN mi.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Warning'
            ELSE 'Normal'
        END as expiry_status
    FROM medications_inventory mi
    LEFT JOIN branches b ON mi.branch_id = b.id
    WHERE mi.expiry_date IS NOT NULL
    AND mi.expiry_date <= ?
    AND mi.status = 'active'
";

$params = [$expiry_threshold];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $query .= " AND mi.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

if (!empty($search_term)) {
    $query .= " AND (mi.medication_name LIKE ? OR mi.category LIKE ? OR mi.batch_number LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

$query .= " ORDER BY mi.expiry_date ASC, mi.medication_name ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$expiring_medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
$critical_count = 0;
$urgent_count = 0;
$warning_count = 0;
$expired_count = 0;

foreach ($expiring_medicines as $medicine) {
    if ($medicine['expiry_date'] < $today) {
        $expired_count++;
    } elseif ($medicine['days_remaining'] <= 7) {
        $critical_count++;
    } elseif ($medicine['days_remaining'] <= 14) {
        $urgent_count++;
    } elseif ($medicine['days_remaining'] <= 30) {
        $warning_count++;
    }
}

// ================================================================
// TOTAL COUNT
// ================================================================
$total_expiring = count($expiring_medicines);

// ================================================================
// GET STATUS BADGE CLASS
// ================================================================
function getExpiryBadge($status) {
    $classes = [
        'Expired' => 'danger',
        'Critical' => 'danger',
        'Urgent' => 'warning',
        'Warning' => 'info',
        'Normal' => 'success'
    ];
    return $classes[$status] ?? 'secondary';
}

function getExpiryIcon($status) {
    $icons = [
        'Expired' => 'fa-times-circle',
        'Critical' => 'fa-exclamation-circle',
        'Urgent' => 'fa-exclamation-triangle',
        'Warning' => 'fa-clock',
        'Normal' => 'fa-check-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/pharmacy_header.php';
include_once '../../components/pharmacy_sidebar.php';
?>

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
            <input type="text" id="searchInput" placeholder="Search medicines..." value="<?= htmlspecialchars($search_term) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-clock mr-2" style="color: #D97706;"></i> Expiring Soon
            </h1>
            <p class="page-subtitle">
                Medicines expiring within <?= $days_filter ?> days
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                <span class="ml-2 date-badge">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
        
        <div class="stat-card orange">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Total Expiring</p>
                    <p class="stat-number"><?= number_format($total_expiring) ?></p>
                    <span class="stat-trend">Within <?= $days_filter ?> days</span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card red">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Critical</p>
                    <p class="stat-number"><?= number_format($critical_count) ?></p>
                    <span class="stat-trend">Within 7 days</span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card orange-dark">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Urgent</p>
                    <p class="stat-number"><?= number_format($urgent_count) ?></p>
                    <span class="stat-trend">8-14 days</span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card red">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Expired</p>
                    <p class="stat-number"><?= number_format($expired_count) ?></p>
                    <span class="stat-trend">Already expired</span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-filter title-blue mr-2"></i> Filters
            </h3>
        </div>
        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="filter-group">
                        <label>Branch</label>
                        <select name="branch" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>All Branches</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($branch['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Days Range</label>
                        <select name="days" class="filter-select" onchange="this.form.submit()">
                            <option value="7" <?= $days_filter == 7 ? 'selected' : '' ?>>Next 7 Days</option>
                            <option value="14" <?= $days_filter == 14 ? 'selected' : '' ?>>Next 14 Days</option>
                            <option value="30" <?= $days_filter == 30 ? 'selected' : '' ?>>Next 30 Days</option>
                            <option value="60" <?= $days_filter == 60 ? 'selected' : '' ?>>Next 60 Days</option>
                            <option value="90" <?= $days_filter == 90 ? 'selected' : '' ?>>Next 90 Days</option>
                        </select>
                    </div>
                    
                    <div class="filter-group" style="flex: 1; min-width: 200px;">
                        <label>Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Search by name, category, batch..." value="<?= htmlspecialchars($search_term) ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-blue">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="expiring_soon.php?branch=<?= $selected_branch_id ?>&days=<?= $days_filter ?>" class="btn btn-outline">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- EXPIRING SOON TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Expiring Medicines
                <span class="text-xs text-gray-400 font-normal">(<?= number_format($total_expiring) ?> items)</span>
            </h3>
            <div class="flex gap-2">
                <button onclick="window.print()" class="btn btn-outline btn-sm">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="exportCSV()" class="btn btn-green btn-sm">
                    <i class="fas fa-file-export"></i> Export CSV
                </button>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table" id="expiringTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Medicine Name</th>
                        <th>Category</th>
                        <th>Batch #</th>
                        <th>Quantity</th>
                        <th>Expiry Date</th>
                        <th>Days Left</th>
                        <th>Status</th>
                        <th>Branch</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($expiring_medicines) > 0): ?>
                        <?php $i = 1; foreach ($expiring_medicines as $medicine): ?>
                            <?php 
                                $status = $medicine['expiry_status'];
                                $days = $medicine['days_remaining'];
                                $row_class = '';
                                
                                if ($status === 'Expired') {
                                    $row_class = 'expired-row';
                                } elseif ($status === 'Critical') {
                                    $row_class = 'critical-row';
                                } elseif ($status === 'Urgent') {
                                    $row_class = 'urgent-row';
                                }
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td><?= $i++ ?></td>
                                <td class="font-medium">
                                    <?= htmlspecialchars($medicine['medication_name']) ?>
                                </td>
                                <td>
                                    <span class="category-badge"><?= htmlspecialchars($medicine['category'] ?? 'N/A') ?></span>
                                </td>
                                <td class="font-mono text-sm"><?= htmlspecialchars($medicine['batch_number'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="quantity-badge <?= $medicine['quantity'] <= 0 ? 'danger' : ($medicine['quantity'] <= $medicine['reorder_level'] ? 'warning' : '') ?>">
                                        <?= number_format($medicine['quantity']) ?> <?= htmlspecialchars($medicine['unit'] ?? '') ?>
                                    </span>
                                </td>
                                <td class="font-mono text-sm">
                                    <?php if ($medicine['expiry_date'] < $today): ?>
                                        <span class="text-danger"><?= date('M d, Y', strtotime($medicine['expiry_date'])) ?></span>
                                    <?php else: ?>
                                        <?= date('M d, Y', strtotime($medicine['expiry_date'])) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($medicine['expiry_date'] < $today): ?>
                                        <span class="text-danger font-bold">Expired</span>
                                    <?php else: ?>
                                        <span class="days-remaining <?= $days <= 7 ? 'danger' : ($days <= 14 ? 'warning' : '') ?>">
                                            <?= $days ?> days
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getExpiryBadge($status) ?>" style="font-size:0.6rem; padding: 2px 10px;">
                                        <i class="fas <?= getExpiryIcon($status) ?>"></i>
                                        <?= $status ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="branch-badge-sm">
                                        <?= htmlspecialchars($medicine['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit_medicine.php?id=<?= $medicine['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn btn-sm btn-blue" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="markAsExpired(<?= $medicine['id'] ?>)" 
                                                class="btn btn-sm btn-danger" title="Mark as Expired">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-gray-400 text-sm py-8">
                                <i class="fas fa-check-circle text-4xl block mb-3 text-green-500"></i>
                                <p class="text-lg font-medium">No expiring medicines found</p>
                                <p class="text-sm">All medicines are within their expiry date</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Expiring Soon
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<style>
    /* ================================================================
       CUSTOM STYLES
       ================================================================ */
    
    /* Stat Cards */
    .stat-card {
        border-radius: 12px;
        padding: 16px 18px;
        border: none;
        transition: all 0.3s ease;
        color: white;
        min-height: 80px;
        display: block;
        position: relative;
        overflow: hidden;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .stat-card.orange { background: #D97706; }
    .stat-card.orange-dark { background: #B45309; }
    .stat-card.red { background: #DC2626; }
    
    .stat-card .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        background: rgba(255,255,255,0.2);
        color: white;
        flex-shrink: 0;
    }
    
    .stat-card .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: white;
        line-height: 1.2;
    }
    
    .stat-card .stat-label {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.9);
        font-weight: 500;
        margin-bottom: 2px;
    }
    
    .stat-card .stat-trend {
        font-size: 0.6rem;
        font-weight: 500;
        padding: 2px 10px;
        border-radius: 12px;
        background: rgba(255,255,255,0.15);
        color: white;
        display: inline-block;
        margin-top: 2px;
    }
    
    /* Row Styles */
    .expired-row td {
        background: #FEE2E2 !important;
        color: #991B1B !important;
    }
    
    .expired-row:hover td {
        background: #FECACA !important;
    }
    
    .critical-row td {
        background: #FEF3C7 !important;
    }
    
    .critical-row:hover td {
        background: #FDE68A !important;
    }
    
    .urgent-row td {
        background: #FEF9C3 !important;
    }
    
    .urgent-row:hover td {
        background: #FDE047 !important;
    }
    
    [data-theme="dark"] .expired-row td {
        background: #3A1A1A !important;
        color: #F87171 !important;
    }
    
    [data-theme="dark"] .critical-row td {
        background: #3D2E0A !important;
        color: #FBBF24 !important;
    }
    
    [data-theme="dark"] .urgent-row td {
        background: #3D3A0A !important;
        color: #FCD34D !important;
    }
    
    /* Filter Section */
    .filter-section {
        padding: 16px 20px;
    }
    
    .filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: flex-end;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex: 1;
        min-width: 140px;
    }
    
    .filter-group label {
        font-size: 0.65rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .filter-select,
    .filter-input {
        padding: 6px 10px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        background: var(--bg-body);
        color: var(--text-primary);
        font-size: 0.8rem;
        outline: none;
        transition: all 0.3s;
        width: 100%;
    }
    
    .filter-select:focus,
    .filter-input:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .filter-actions {
        display: flex;
        gap: 6px;
        flex: 0 0 auto;
        align-items: center;
    }
    
    /* Category Badge */
    .category-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 500;
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    [data-theme="dark"] .category-badge {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    /* Quantity Badge */
    .quantity-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        background: #D1FAE5;
        color: #059669;
    }
    
    .quantity-badge.warning {
        background: #FEF3C7;
        color: #D97706;
    }
    
    .quantity-badge.danger {
        background: #FEE2E2;
        color: #DC2626;
    }
    
    [data-theme="dark"] .quantity-badge {
        background: #1A3A2A;
        color: #34D399;
    }
    
    [data-theme="dark"] .quantity-badge.warning {
        background: #3D2E0A;
        color: #FBBF24;
    }
    
    [data-theme="dark"] .quantity-badge.danger {
        background: #3A1A1A;
        color: #F87171;
    }
    
    /* Days Remaining */
    .days-remaining {
        font-weight: 600;
        font-size: 0.85rem;
    }
    
    .days-remaining.danger {
        color: #DC2626;
    }
    
    .days-remaining.warning {
        color: #D97706;
    }
    
    .text-danger {
        color: #DC2626 !important;
    }
    
    .text-success {
        color: #059669 !important;
    }
    
    /* Branch Badge Small */
    .branch-badge-sm {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.55rem;
        font-weight: 500;
        background: #F1F5F9;
        color: #64748B;
    }
    
    [data-theme="dark"] .branch-badge-sm {
        background: #334155;
        color: #94A3B8;
    }
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 4px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.65rem;
        transition: all 0.3s;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-sm { padding: 3px 8px; font-size: 0.6rem; }
    
    .btn-blue { background: #0B5ED7; color: white; }
    .btn-blue:hover { background: #0A4CA8; transform: translateY(-1px); }
    
    .btn-green { background: #059669; color: white; }
    .btn-green:hover { background: #047857; transform: translateY(-1px); }
    
    .btn-danger { background: #DC2626; color: white; }
    .btn-danger:hover { background: #B91C1C; transform: translateY(-1px); }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: translateY(-1px);
    }
    
    /* Badges */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        color: white;
    }
    
    .badge-success { background: #059669; }
    .badge-danger { background: #DC2626; }
    .badge-warning { background: #D97706; color: #1E293B; }
    .badge-info { background: #0B5ED7; }
    .badge-secondary { background: #64748B; }
    
    [data-theme="dark"] .badge-warning { color: #1E293B; }
    
    /* Card */
    .card {
        background: var(--bg-card);
        border-radius: 12px;
        border: 1px solid var(--border-color);
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: var(--shadow-sm);
    }
    
    .card:hover {
        box-shadow: var(--shadow-md);
    }
    
    .card-header {
        padding: 14px 20px;
        background: var(--bg-body);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    [data-theme="dark"] .card-header {
        background: #0F172A;
    }
    
    .card-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
    }
    
    .title-blue { color: #0B5ED7; }
    .title-green { color: #059669; }
    
    /* Data Table */
    .data-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.8rem;
    }
    
    .data-table thead th {
        background: #0B5ED7 !important;
        color: white !important;
        font-weight: 600;
        padding: 10px 12px;
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        border-bottom: none !important;
        white-space: nowrap;
    }
    
    .data-table thead th:first-child {
        border-radius: 8px 0 0 0;
    }
    
    .data-table thead th:last-child {
        border-radius: 0 8px 0 0;
    }
    
    .data-table td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
        transition: background 0.2s ease;
    }
    
    .data-table tbody tr:hover td {
        background: var(--table-hover);
    }
    
    .data-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    .data-table .text-center { text-align: center; }
    .data-table .text-sm { font-size: 0.75rem; }
    .data-table .text-xs { font-size: 0.65rem; }
    .data-table .font-mono { font-family: 'Courier New', monospace; }
    .data-table .font-medium { font-weight: 500; }
    .data-table .font-bold { font-weight: 700; }
    .data-table .py-8 { padding-top: 32px; padding-bottom: 32px; }
    .data-table .text-4xl { font-size: 2.5rem; }
    .data-table .text-lg { font-size: 1.1rem; }
    .data-table .text-green-500 { color: #059669; }
    .data-table .text-gray-400 { color: var(--text-secondary); }
    .data-table .mb-3 { margin-bottom: 12px; }
    .data-table .block { display: block; }
    
    /* Page Header */
    .page-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
    }
    
    .page-title i {
        color: #0B5ED7;
    }
    
    .page-subtitle {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin: 4px 0 0 0;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 4px;
    }
    
    .branch-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #E8F0FE;
        color: #0B5ED7;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 500;
    }
    
    [data-theme="dark"] .branch-tag {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    .date-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: var(--text-secondary);
        font-size: 0.75rem;
    }
    
    /* Footer */
    .footer {
        margin-top: 30px;
        padding: 16px 20px;
        background: var(--bg-card);
        border-radius: 12px;
        border: 1px solid var(--border-color);
        text-align: center;
    }
    
    .footer p {
        margin: 0;
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .footer-brand {
        font-weight: 700;
        color: #0B5ED7;
    }
    
    /* Grid */
    .grid {
        display: grid;
        gap: 16px;
    }
    
    .grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
    
    @media (min-width: 640px) {
        .sm\:grid-cols-4 { grid-template-columns: repeat(4, 1fr); }
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .filter-form { flex-direction: column; align-items: stretch; }
        .filter-group { min-width: 100%; }
        .filter-actions { flex-direction: row; }
    }
    
    @media (max-width: 768px) {
        .data-table { font-size: 0.7rem; }
        .data-table td, .data-table th { padding: 6px 8px; }
        .data-table thead th { font-size: 0.55rem; padding: 6px 8px; }
        .action-buttons { flex-direction: column; gap: 3px; }
        .action-buttons .btn { width: 100%; justify-content: center; }
        .grid-cols-2 { grid-template-columns: 1fr; }
        .page-title { font-size: 1.1rem; }
        .page-subtitle { font-size: 0.75rem; }
        .card-header { flex-direction: column; align-items: flex-start; gap: 8px; }
    }
    
    @media (max-width: 480px) {
        .stat-card { min-height: 65px; padding: 10px 14px; }
        .stat-card .stat-number { font-size: 1.2rem; }
        .stat-card .stat-icon { width: 32px; height: 32px; font-size: 0.9rem; }
        .filter-actions { flex-direction: column; }
        .filter-actions .btn { width: 100%; justify-content: center; }
    }
</style>

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
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            var days = '<?= $days_filter ?>';
            window.location.href = 'expiring_soon.php?search=' + encodeURIComponent(query) + '&branch=' + branch + '&days=' + days;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // EXPORT CSV
    // ================================================================
    function exportCSV() {
        var table = document.getElementById('expiringTable');
        if (!table) return;
        
        var rows = table.querySelectorAll('tr');
        var csv = [];
        
        // Get headers
        var headers = [];
        table.querySelectorAll('thead th').forEach(function(th) {
            headers.push(th.textContent.trim());
        });
        csv.push(headers.join(','));
        
        // Get data rows
        table.querySelectorAll('tbody tr').forEach(function(row) {
            var rowData = [];
            row.querySelectorAll('td').forEach(function(td) {
                // Skip action buttons column (last column)
                if (rowData.length < headers.length - 1) {
                    rowData.push('"' + td.textContent.trim() + '"');
                }
            });
            csv.push(rowData.join(','));
        });
        
        // Download CSV
        var blob = new Blob([csv.join('\n')], { type: 'text/csv' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'expiring_medicines_' + new Date().toISOString().slice(0,10) + '.csv';
        a.click();
        window.URL.revokeObjectURL(url);
    }

    // ================================================================
    // MARK AS EXPIRED
    // ================================================================
    function markAsExpired(id) {
        if (!confirm('⚠️ Are you sure you want to mark this medicine as EXPIRED?\n\nThis action will update the status to inactive.')) {
            return;
        }
        
        fetch('mark_medicine_expired.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                alert('✅ Medicine marked as expired successfully!');
                location.reload();
            } else {
                alert('❌ Error: ' + (data.message || 'Failed to mark as expired'));
            }
        })
        .catch(function(error) {
            alert('❌ Network error. Please try again.');
        });
    }

    console.log('%c💊 Pharmacy - Expiring Soon Medicines', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Total Expiring: <?= number_format($total_expiring) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c🔴 Critical: <?= number_format($critical_count) ?> | 🟠 Urgent: <?= number_format($urgent_count) ?> | ⚪ Warning: <?= number_format($warning_count) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Export CSV available', 'font-size:13px; color:#059669;');
</script>

</body>
</html>