<?php
// ================================================================
// FILE: frontend/pages/admin/pharmacy_inventory.php
// SUPER ADMIN - VIEW PHARMACY INVENTORY
// ✅ Inatumia SHARED HEADER & SIDEBAR pekee
// ✅ Imeondoa top-nav, search, dark mode, datetime, avatar, branch selector
// ✅ Imeondoa DOCTYPE, html, head, body
// ✅ BLUE THEME - 3+3 stat cards
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

$pharmacy_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

if ($pharmacy_id <= 0) {
    header('Location: pharmacies.php?branch=' . $selected_branch_id . '&error=invalid_id');
    exit;
}

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// FETCH PHARMACY
$stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->execute([$pharmacy_id]);
$pharmacy = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pharmacy) {
    header('Location: pharmacies.php?branch=' . $selected_branch_id . '&error=notfound');
    exit;
}

// BUILD INVENTORY QUERY
$sql = "
    SELECT id, medication_name, category, unit, quantity, reorder_level,
           unit_cost, selling_price, supplier, expiry_date, batch_number,
           status, created_at, updated_at
    FROM medications_inventory
    WHERE branch_id = ?
";
$params = [$pharmacy_id];

if ($filter === 'outofstock') {
    $sql .= " AND quantity <= 0";
} elseif ($filter === 'lowstock') {
    $sql .= " AND quantity <= reorder_level AND quantity > 0";
} elseif ($filter === 'expired') {
    $sql .= " AND expiry_date < CURDATE()";
} elseif ($filter === 'expiring') {
    $sql .= " AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
} elseif ($filter === 'instock') {
    $sql .= " AND quantity > reorder_level AND quantity > 0";
}

if (!empty($search)) {
    $sql .= " AND (medication_name LIKE ? OR category LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY medication_name ASC";

$inventory_items = [];
try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $inventory_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $inventory_items = [];
}

// CALCULATE STATISTICS
$total_items = count($inventory_items);
$total_quantity = 0;
$total_value = 0;
$out_of_stock = 0;
$low_stock = 0;
$expired = 0;
$expiring_soon = 0;
$in_stock = 0;
$total_cost_value = 0;

foreach ($inventory_items as $item) {
    $qty = $item['quantity'] ?? 0;
    $total_quantity += $qty;
    $total_value += ($qty * ($item['selling_price'] ?? 0));
    $total_cost_value += ($qty * ($item['unit_cost'] ?? 0));
    
    if ($qty <= 0) {
        $out_of_stock++;
    } elseif ($qty <= ($item['reorder_level'] ?? 0)) {
        $low_stock++;
    } else {
        $in_stock++;
    }
    
    $expiry = $item['expiry_date'] ?? null;
    if ($expiry) {
        $expiry_time = strtotime($expiry);
        if ($expiry_time < time()) {
            $expired++;
        } elseif ($expiry_time < strtotime('+30 days')) {
            $expiring_soon++;
        }
    }
}

// GET BRANCHES FOR FILTER
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getStatusBadge($status) {
    $classes = [
        'active' => 'success', 'inactive' => 'danger', 'pending' => 'warning',
        'dispensed' => 'success', 'confirmed' => 'info', 'cancelled' => 'danger',
        'paid' => 'success', 'partial' => 'warning'
    ];
    return $classes[$status] ?? 'secondary';
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
:root {
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-bg: #EFF6FF;
    --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    --success: #059669;
    --success-bg: #D1FAE5;
    --danger: #DC2626;
    --danger-bg: #FEE2E2;
    --warning: #D97706;
    --warning-bg: #FEF3C7;
    --bg-body: #F0F4F8;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --radius: 12px;
    --radius-lg: 18px;
    --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    --table-hover: #F8FAFC;
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary: #3B82F6;
    --primary-bg: #1E3A5F;
    --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
    --table-hover: #1E293B;
}

/* PAGE HEADER */
.page-header-custom {
    background: var(--primary-gradient);
    border-radius: var(--radius-lg);
    padding: 24px 32px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
    position: relative;
    overflow: hidden;
}

.page-header-custom::before {
    content: '';
    position: absolute;
    top: -60%; right: -10%;
    width: 400px; height: 400px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    pointer-events: none;
}

.page-header-custom .page-title {
    color: white;
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin: 0;
}

.page-header-custom .page-title i { font-size: 1.6rem; opacity: 0.9; }

.page-header-custom .page-subtitle {
    color: rgba(255,255,255,0.85);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin-top: 6px;
}

.page-header-custom .role-badge-display {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.page-header-custom .header-badge {
    background: rgba(255,255,255,0.12);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid rgba(255,255,255,0.1);
}

.page-header-custom .btn-outline-light {
    background: rgba(255,255,255,0.12);
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 8px 18px;
    border-radius: var(--radius);
    font-weight: 500;
    font-size: 0.82rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    position: relative;
    z-index: 1;
    transition: all 0.3s;
}

.page-header-custom .btn-outline-light:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
}

/* STATS GRID 6 - 3+3 */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--primary-gradient);
    border-radius: var(--radius);
    padding: 20px 24px;
    border: 2px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(11, 94, 215, 0.2);
    text-decoration: none;
    color: white;
    position: relative;
    overflow: hidden;
    min-height: 90px;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(11, 94, 215, 0.35);
    border-color: rgba(255,255,255,0.2);
}

.stat-card .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.1);
}

.stat-card .stat-label {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.8);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0;
}

.stat-card .stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
    margin: 0;
    line-height: 1.2;
}

.stat-card .stat-sub {
    font-size: 0.6rem;
    color: rgba(255,255,255,0.6);
    margin-top: 2px;
}

/* BADGES */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    color: white;
}

.badge-success { background: #059669; }
.badge-danger { background: #DC2626; }
.badge-warning { background: #D97706; color: #1E293B; }
.badge-info { background: #0B5ED7; }
.badge-secondary { background: #64748B; }

[data-theme="dark"] .badge-warning { color: #1E293B; }

/* FILTER BAR */
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
    align-items: center;
    background: var(--bg-card);
    padding: 16px 20px;
    border-radius: var(--radius);
    border: 2px solid var(--border-color);
    box-shadow: var(--shadow-sm);
}

.filter-bar select, .filter-bar input {
    background: var(--bg-body);
    border: 2px solid var(--border-color);
    border-radius: var(--radius);
    padding: 8px 14px;
    font-size: 0.8rem;
    color: var(--text-primary);
    outline: none;
    transition: all 0.3s;
    min-width: 150px;
}

.filter-bar select:focus, .filter-bar input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
}

/* BUTTONS */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
    text-decoration: none;
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}

.btn-primary {
    background: var(--primary-gradient);
    color: white;
}

.btn-primary:hover {
    box-shadow: 0 4px 16px rgba(11, 94, 215, 0.35);
}

.btn-outline {
    background: transparent;
    color: var(--text-secondary);
    border: 2px solid var(--border-color);
}

.btn-outline:hover {
    background: var(--bg-body);
    border-color: var(--primary);
    color: var(--primary);
}

.btn-sm {
    padding: 5px 12px;
    font-size: 0.7rem;
    border-radius: 6px;
}

/* CARD */
.card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 2px solid var(--border-color);
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: var(--shadow-sm);
    margin-bottom: 24px;
}

.card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-md);
}

.card-header {
    padding: 16px 24px;
    background: var(--bg-body);
    border-bottom: 2px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.card-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
}

.card-title i { margin-right: 8px; color: var(--primary); }

/* DATA TABLE */
.data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.8rem;
}

.data-table thead th {
    background: var(--primary-gradient);
    color: white;
    font-weight: 600;
    padding: 12px 14px;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: none;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 5;
    text-align: left;
}

.data-table thead th:first-child { border-radius: 8px 0 0 0; }
.data-table thead th:last-child { border-radius: 0 8px 0 0; }

.data-table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
}

.data-table tbody tr:nth-child(even) { background: var(--primary-bg); }
.data-table tbody tr:hover td { background: var(--table-hover); }

.data-table tbody tr:last-child td { border-bottom: none; }

/* STATUS DOT */
.status-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 6px;
}

.status-dot.green { background: #059669; }
.status-dot.amber { background: #D97706; }
.status-dot.red { background: #DC2626; }
.status-dot.blue { background: #0B5ED7; }

/* EMPTY STATE */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 3rem;
    color: var(--border-color);
    margin-bottom: 16px;
    display: block;
}

.empty-state h3 {
    font-size: 1.2rem;
    color: var(--text-primary);
    margin-bottom: 8px;
}

/* FOOTER */
.footer {
    padding: 14px 0;
    border-top: 2px solid var(--border-color);
    margin-top: 24px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.footer .footer-brand { color: var(--primary); font-weight: 500; }

/* TOAST */
.toast-custom {
    position: fixed;
    bottom: 24px;
    right: 24px;
    padding: 14px 20px;
    border-radius: 12px;
    z-index: 9999;
    max-width: 400px;
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.4s ease;
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
}

.toast-custom.show { transform: translateY(0); opacity: 1; }
.toast-custom.success { background: #059669; }
.toast-custom.error { background: #DC2626; }
.toast-custom.info { background: #0B5ED7; }
.toast-custom.warning { background: #D97706; }

.text-red-600 { color: #DC2626; }
.text-amber-600 { color: #D97706; }
.text-green-600 { color: #059669; }
.text-blue-600 { color: #0B5ED7; }
.text-gray-500 { color: var(--text-secondary); }
.font-bold { font-weight: 700; }
.font-medium { font-weight: 500; }
.font-semibold { font-weight: 600; }

/* ANIMATION */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in-up {
    animation: fadeInUp 0.5s ease forwards;
    opacity: 0;
}

/* RESPONSIVE */
@media (max-width: 1024px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .page-header-custom { padding: 16px 18px; }
    .page-header-custom .page-title { font-size: 1.2rem; }
    .stats-grid { grid-template-columns: 1fr 1fr; }
    .filter-bar { flex-direction: column; align-items: stretch; }
    .filter-bar select, .filter-bar input { width: 100%; min-width: unset; }
    .data-table { font-size: 0.7rem; }
    .data-table td, .data-table th { padding: 6px 8px; }
}

@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .page-header-custom { flex-direction: column; align-items: flex-start; }
}
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-boxes"></i>
                Pharmacy Inventory
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($pharmacy['name']) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-pills"></i> <?= number_format($total_items) ?> Items
                </span>
                <span class="header-badge">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_value, 0) ?> Stock Value
                </span>
                <?php if ($filter !== 'all'): ?>
                    <span class="header-badge">
                        <i class="fas fa-filter"></i> Filter: <?= ucfirst(str_replace('_', ' ', $filter)) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="add_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-plus"></i> Add Item
            </a>
            <a href="view_pharmacy.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- 6 STATS - 3+3 GRID -->
    <div class="stats-grid animate-fade-in-up">
        
        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>&filter=all" class="stat-card">
            <div class="stat-icon"><i class="fas fa-boxes"></i></div>
            <div>
                <p class="stat-label">Total Items</p>
                <p class="stat-value"><?= number_format($total_items) ?></p>
                <p class="stat-sub">Inventory items</p>
            </div>
        </a>
        
        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>&filter=instock" class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div>
                <p class="stat-label">In Stock</p>
                <p class="stat-value"><?= number_format($in_stock) ?></p>
                <p class="stat-sub">Available items</p>
            </div>
        </a>
        
        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>&filter=lowstock" class="stat-card">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div>
                <p class="stat-label">Low Stock</p>
                <p class="stat-value"><?= number_format($low_stock) ?></p>
                <p class="stat-sub">Below reorder level</p>
            </div>
        </a>
        
        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>&filter=outofstock" class="stat-card">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div>
                <p class="stat-label">Out of Stock</p>
                <p class="stat-value"><?= number_format($out_of_stock) ?></p>
                <p class="stat-sub">Zero quantity</p>
            </div>
        </a>
        
        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>&filter=expired" class="stat-card">
            <div class="stat-icon"><i class="fas fa-skull"></i></div>
            <div>
                <p class="stat-label">Expired</p>
                <p class="stat-value"><?= number_format($expired) ?></p>
                <p class="stat-sub">Past expiry date</p>
            </div>
        </a>
        
        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>&filter=expiring" class="stat-card">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div>
                <p class="stat-label">Expiring Soon</p>
                <p class="stat-value"><?= number_format($expiring_soon) ?></p>
                <p class="stat-sub">Next 30 days</p>
            </div>
        </a>
        
    </div>

    <!-- FILTERS -->
    <div class="filter-bar animate-fade-in-up">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;width:100%;">
            <input type="hidden" name="id" value="<?= $pharmacy['id'] ?>">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
            
            <select name="filter" onchange="this.form.submit()" style="flex:1;min-width:150px;">
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Items</option>
                <option value="instock" <?= $filter === 'instock' ? 'selected' : '' ?>>In Stock</option>
                <option value="lowstock" <?= $filter === 'lowstock' ? 'selected' : '' ?>>Low Stock</option>
                <option value="outofstock" <?= $filter === 'outofstock' ? 'selected' : '' ?>>Out of Stock</option>
                <option value="expired" <?= $filter === 'expired' ? 'selected' : '' ?>>Expired</option>
                <option value="expiring" <?= $filter === 'expiring' ? 'selected' : '' ?>>Expiring Soon</option>
            </select>
            
            <input type="text" name="search" placeholder="Search by name or category..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px;">
            
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Clear
            </a>
        </form>
    </div>

    <!-- INVENTORY TABLE -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list"></i>
                Inventory Items (<?= count($inventory_items) ?>)
            </h3>
            <div style="display:flex;gap:8px;">
                <a href="add_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add Item
                </a>
                <a href="export_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>&filter=<?= $filter ?>" class="btn btn-outline btn-sm">
                    <i class="fas fa-file-export"></i> Export
                </a>
            </div>
        </div>
        <div style="overflow-x:auto;">
            <?php if (count($inventory_items) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="min-width: 120px;">Medicine</th>
                            <th style="min-width: 100px;">Category</th>
                            <th style="width: 70px;">Qty</th>
                            <th style="width: 100px;">Reorder</th>
                            <th style="width: 110px;">Selling Price</th>
                            <th style="min-width: 110px;">Expiry Date</th>
                            <th style="width: 130px;">Status</th>
                            <th style="width: 100px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory_items as $item): 
                            $is_expired = !empty($item['expiry_date']) && strtotime($item['expiry_date']) < time();
                            $is_expiring_soon = !empty($item['expiry_date']) && strtotime($item['expiry_date']) > time() && strtotime($item['expiry_date']) < strtotime('+30 days');
                            $is_low_stock = ($item['quantity'] ?? 0) <= ($item['reorder_level'] ?? 0) && ($item['quantity'] ?? 0) > 0;
                            $is_out_of_stock = ($item['quantity'] ?? 0) <= 0;
                            $is_healthy = !$is_expired && !$is_expiring_soon && !$is_low_stock && !$is_out_of_stock;
                        ?>
                            <tr>
                                <td style="font-weight:500;"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge" style="font-size:0.55rem;padding:2px 10px;background:var(--primary-bg);color:var(--primary);">
                                        <?= htmlspecialchars($item['category'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="font-bold <?= $is_out_of_stock ? 'text-red-600' : ($is_low_stock ? 'text-amber-600' : 'text-green-600') ?>">
                                        <?= number_format($item['quantity'] ?? 0) ?>
                                    </span>
                                </td>
                                <td><?= number_format($item['reorder_level'] ?? 0) ?></td>
                                <td>
                                    <span class="font-semibold text-blue-600">TSh <?= number_format($item['selling_price'] ?? 0, 0) ?></span>
                                </td>
                                <td class="<?= $is_expired ? 'text-red-600 font-bold' : ($is_expiring_soon ? 'text-amber-600' : 'text-gray-500') ?>">
                                    <?= !empty($item['expiry_date']) ? date('M d, Y', strtotime($item['expiry_date'])) : 'N/A' ?>
                                    <?php if ($is_expired): ?>
                                        <span class="text-red-600" style="font-size:0.6rem;display:block;font-weight:700;">(EXPIRED)</span>
                                    <?php elseif ($is_expiring_soon): ?>
                                        <span class="text-amber-600" style="font-size:0.6rem;display:block;">(Expiring Soon)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_out_of_stock): ?>
                                        <span class="badge badge-danger" style="font-size:0.6rem;padding:2px 10px;">
                                            <span class="status-dot red"></span> Out of Stock
                                        </span>
                                    <?php elseif ($is_low_stock): ?>
                                        <span class="badge badge-warning" style="font-size:0.6rem;padding:2px 10px;">
                                            <span class="status-dot amber"></span> Low Stock
                                        </span>
                                    <?php elseif ($is_expired): ?>
                                        <span class="badge badge-danger" style="font-size:0.6rem;padding:2px 10px;">
                                            <span class="status-dot red"></span> Expired
                                        </span>
                                    <?php elseif ($is_expiring_soon): ?>
                                        <span class="badge badge-warning" style="font-size:0.6rem;padding:2px 10px;">
                                            <span class="status-dot amber"></span> Expiring Soon
                                        </span>
                                    <?php elseif ($is_healthy): ?>
                                        <span class="badge badge-success" style="font-size:0.6rem;padding:2px 10px;">
                                            <span class="status-dot green"></span> In Stock
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary" style="font-size:0.6rem;padding:2px 10px;">
                                            Unknown
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex;gap:4px;align-items:center;">
                                        <a href="edit_inventory.php?id=<?= $item['id'] ?>&branch=<?= $pharmacy['id'] ?>" 
                                           class="btn btn-sm btn-outline" style="padding:3px 8px;font-size:0.6rem;">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view_inventory.php?id=<?= $item['id'] ?>&branch=<?= $pharmacy['id'] ?>" 
                                           class="btn btn-sm btn-primary" style="padding:3px 8px;font-size:0.6rem;">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-boxes"></i>
                    <h3>No Inventory Items Found</h3>
                    <p><?= !empty($search) || $filter !== 'all' ? 'No items match your search criteria.' : 'This pharmacy has no inventory items yet.' ?></p>
                    <?php if (!empty($search) || $filter !== 'all'): ?>
                        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary" style="margin-top:16px;">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php else: ?>
                        <a href="add_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary" style="margin-top:16px;">
                            <i class="fas fa-plus"></i> Add Item
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            Pharmacy Inventory - <?= htmlspecialchars($pharmacy['name']) ?>
            <span style="margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE JAVASCRIPT - PEKEE -->
<!-- ================================================================ -->
<script>
// Footer time update only (header ina yake)
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

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
        setTimeout(function() { toast.style.display = 'none'; }, 400);
    }, 3500);
}

console.log('%c📦 Braick - Pharmacy Inventory (Shared Header/Sidebar)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Inatumia SHARED HEADER & SIDEBAR pekee', 'font-size:12px;color:#34D399;');
console.log('%c✅ BLUE THEME - 6 stat cards (3+3 grid)', 'font-size:12px;color:#34D399;');
console.log('%c📊 Total Items: <?= number_format($total_items) ?>', 'font-size:12px;color:#7C3AED;');
console.log('%c💰 Total Value: TSh <?= number_format($total_value, 0) ?>', 'font-size:12px;color:#0B5ED7;');
console.log('%c🏥 Pharmacy: <?= htmlspecialchars($pharmacy['name']) ?>', 'font-size:12px;color:#059669;');
</script>

</body>
</html>