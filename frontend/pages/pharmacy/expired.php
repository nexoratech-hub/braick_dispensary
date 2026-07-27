<?php
// ================================================================
// FILE: frontend/pages/pharmacy/expired.php
// PHARMACY - EXPIRED MEDICINES REPORT
// BRAICK DISPENSARY
// ================================================================
// FIXED:
// 1. Shows ALL expired medicines (active + inactive)
// 2. Only Dodoma branch
// 3. Sort by expiry date (oldest first)
// 4. Shows status badge (Active/Inactive)
// 5. NO EDIT BUTTON - Only View and Delete (Inactive)
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
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';

// ================================================================
// BUILD QUERY - SHOWS ALL EXPIRED (ACTIVE + INACTIVE)
// ================================================================
$query = "
    SELECT *, 
        DATEDIFF(CURDATE(), expiry_date) as days_expired
    FROM medications_inventory 
    WHERE branch_id = ?
    AND expiry_date IS NOT NULL 
    AND expiry_date < CURDATE()
";

$params = [$user_branch_id];

// Status filter (active/inactive/all)
if ($status_filter === 'active') {
    $query .= " AND status = 'active'";
} elseif ($status_filter === 'inactive') {
    $query .= " AND status = 'inactive'";
}

// Search filter
if (!empty($search)) {
    $query .= " AND medication_name LIKE ?";
    $params[] = "%$search%";
}

$query .= " ORDER BY expiry_date ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$expired_medicines = $stmt->fetchAll();

// ================================================================
// GET STATISTICS
// ================================================================

// Total expired (all)
$stmt = $db->prepare("
    SELECT COUNT(*) as count, SUM(quantity) as total_quantity
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND expiry_date IS NOT NULL 
    AND expiry_date < CURDATE()
");
$stmt->execute([$user_branch_id]);
$total_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_expired = $total_data['count'] ?? 0;
$total_expired_units = $total_data['total_quantity'] ?? 0;

// Active expired
$stmt = $db->prepare("
    SELECT COUNT(*) as count, SUM(quantity) as total_quantity
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND expiry_date IS NOT NULL 
    AND expiry_date < CURDATE()
    AND status = 'active'
");
$stmt->execute([$user_branch_id]);
$active_data = $stmt->fetch(PDO::FETCH_ASSOC);
$active_expired = $active_data['count'] ?? 0;
$active_expired_units = $active_data['total_quantity'] ?? 0;

// Inactive expired
$stmt = $db->prepare("
    SELECT COUNT(*) as count, SUM(quantity) as total_quantity
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND expiry_date IS NOT NULL 
    AND expiry_date < CURDATE()
    AND status = 'inactive'
");
$stmt->execute([$user_branch_id]);
$inactive_data = $stmt->fetch(PDO::FETCH_ASSOC);
$inactive_expired = $inactive_data['count'] ?? 0;
$inactive_expired_units = $inactive_data['total_quantity'] ?? 0;

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$pending_prescriptions = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescription_sales WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_prescriptions = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
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
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    
    .stat-card.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
    .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .stat-card.gray { background: linear-gradient(135deg, #6B7280, #4B5563); }
    .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    
    .stat-card .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
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
    
    .card-title .title-red { color: var(--danger); }
    .card-title .title-blue { color: var(--primary); }
    
    .result-count {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .result-count strong {
        color: var(--primary);
    }
    
    .filter-group {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }
    
    .filter-btn {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        border: 2px solid var(--border-color);
        background: transparent;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .filter-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .filter-btn.active {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }
    
    .filter-btn.active:hover {
        background: var(--primary-dark);
        border-color: var(--primary-dark);
    }
    
    .filter-btn.red.active {
        background: var(--danger);
        border-color: var(--danger);
    }
    
    .filter-btn.clear-filter {
        border-color: var(--danger);
        color: var(--danger);
    }
    
    .filter-btn.clear-filter:hover {
        background: var(--danger);
        color: white;
    }
    
    .search-form {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .search-form input[type="text"] {
        padding: 8px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
        flex: 1;
        min-width: 200px;
    }
    
    .search-form input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .search-form .btn-search {
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
    
    .search-form .btn-search:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .search-form .btn-reset {
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
    
    .search-form .btn-reset:hover {
        border-color: var(--danger);
        color: var(--danger);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
        padding: 6px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.78rem;
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
    
    .btn-add {
        background: var(--success);
        color: white;
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-add:hover {
        background: var(--success-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
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
        background: var(--danger);
        border-bottom: 3px solid #991B1B;
        white-space: nowrap;
    }
    
    .data-table thead th:first-child {
        border-radius: 8px 0 0 0;
    }
    
    .data-table thead th:last-child {
        border-radius: 0 8px 0 0;
    }
    
    .data-table tbody tr:nth-child(even) {
        background: var(--danger-light);
    }
    
    .data-table tbody tr:hover td {
        background: var(--warning-light);
    }
    
    [data-theme="dark"] .data-table tbody tr:nth-child(even) {
        background: #3A1A1A;
    }
    
    [data-theme="dark"] .data-table tbody tr:hover td {
        background: #3D2E0A;
    }
    
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
    
    .status-badge.active {
        background: var(--success-light);
        color: var(--success);
    }
    
    .status-badge.inactive {
        background: var(--danger-light);
        color: var(--danger);
    }
    
    [data-theme="dark"] .status-badge.active {
        background: #1A3A2A;
        color: #34D399;
    }
    
    [data-theme="dark"] .status-badge.inactive {
        background: #3A1A1A;
        color: #F87171;
    }
    
    .expired-badge {
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 0.65rem;
        font-weight: 600;
        background: var(--danger-light);
        color: var(--danger);
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    [data-theme="dark"] .expired-badge {
        background: #3A1A1A;
        color: #F87171;
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
    
    [data-theme="dark"] .batch-number {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    /* ✅ ONLY View and Delete buttons - NO EDIT */
    .action-btn {
        padding: 4px 10px;
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
    }
    
    .action-btn.view {
        background: var(--purple);
        color: white;
    }
    
    .action-btn.view:hover {
        background: #6D28D9;
        transform: scale(1.05);
    }
    
    .action-btn.delete {
        background: var(--danger);
        color: white;
    }
    
    .action-btn.delete:hover {
        background: #991B1B;
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
    
    .empty-state p {
        font-size: 0.95rem;
    }
    
    .empty-state .sub {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-top: 4px;
    }
    
    .message-box {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        animation: slideDown 0.4s ease;
    }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
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
    
    .message-box i {
        font-size: 1.3rem;
    }
    
    [data-theme="dark"] .message-box.success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }
    
    [data-theme="dark"] .message-box.warning {
        background: #3D2E0A;
        color: #FBBF24;
        border-color: #FBBF24;
    }
    
    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }
    
    .animate-fade-in-up:nth-child(1) { animation-delay: 0.05s; }
    .animate-fade-in-up:nth-child(2) { animation-delay: 0.1s; }
    .animate-fade-in-up:nth-child(3) { animation-delay: 0.15s; }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .days-expired {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: var(--danger-light);
        color: var(--danger);
    }
    
    [data-theme="dark"] .days-expired {
        background: #3A1A1A;
        color: #F87171;
    }
    
    .footer {
        padding: 14px 0;
        border-top: 1px solid var(--border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .footer .footer-brand { 
        color: var(--primary); 
        font-weight: 600; 
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .search-form {
            flex-direction: column;
            align-items: stretch;
        }
        .search-form input[type="text"] {
            min-width: 100%;
        }
        .filter-group {
            justify-content: center;
        }
        .card {
            padding: 12px 14px;
        }
        .data-table {
            font-size: 0.7rem;
        }
        .data-table th,
        .data-table td {
            padding: 5px 8px;
        }
        .stat-card .stat-number {
            font-size: 1.3rem;
        }
        .stat-card {
            padding: 12px 16px;
            min-height: 70px;
        }
        .stat-card .stat-icon {
            width: 36px;
            height: 36px;
            font-size: 1rem;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .stat-card .stat-number {
            font-size: 1.1rem;
        }
        .stat-card .stat-label {
            font-size: 0.6rem;
        }
        .stat-card .stat-icon {
            width: 30px;
            height: 30px;
            font-size: 0.8rem;
        }
        .stat-card {
            padding: 8px 12px;
            min-height: 60px;
        }
        .data-table {
            font-size: 0.65rem;
        }
        .data-table th,
        .data-table td {
            padding: 4px 6px;
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
                <i class="fas fa-skull mr-2" style="color: var(--danger);"></i> Expired Medicines
            </h1>
            <p class="page-subtitle">
                View all expired medicines (active + inactive)
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs border border-red-200">
                    <i class="fas fa-exclamation-triangle mr-1"></i> <?= $total_expired ?> expired items
                </span>
            </p>
        </div>
        <div>
            <a href="inventory.php" class="btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <a href="expired.php" class="stat-card red">
            <div>
                <p class="stat-label">Total Expired</p>
                <p class="stat-number"><?= number_format($total_expired) ?></p>
                <span class="stat-trend"><i class="fas fa-skull"></i> <?= number_format($total_expired_units) ?> units</span>
            </div>
            <div class="stat-icon"><i class="fas fa-skull"></i></div>
        </a>
        
        <a href="expired.php?status=active" class="stat-card orange">
            <div>
                <p class="stat-label">Active (Still in stock)</p>
                <p class="stat-number"><?= number_format($active_expired) ?></p>
                <span class="stat-trend"><i class="fas fa-exclamation-triangle"></i> <?= number_format($active_expired_units) ?> units</span>
            </div>
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        </a>
        
        <a href="expired.php?status=inactive" class="stat-card gray">
            <div>
                <p class="stat-label">Inactive (Hidden)</p>
                <p class="stat-number"><?= number_format($inactive_expired) ?></p>
                <span class="stat-trend"><i class="fas fa-check-circle"></i> <?= number_format($inactive_expired_units) ?> units</span>
            </div>
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
    <?php if (count($expired_medicines) == 0 && empty($search)): ?>
        <div class="message-box success">
            <i class="fas fa-check-circle"></i>
            🎉 No expired medicines found! All medicines are within expiry date.
        </div>
    <?php elseif (count($expired_medicines) > 0 && $active_expired > 0): ?>
        <div class="message-box warning">
            <i class="fas fa-exclamation-triangle"></i>
            ⚠️ <strong><?= $active_expired ?></strong> expired medicine(s) are still <strong>ACTIVE</strong> in inventory. 
            Please remove or mark as inactive!
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FILTERS & SEARCH -->
    <!-- ================================================================ -->
    <div class="card mb-5 animate-fade-in-up">
        <div class="filter-group">
            <a href="expired.php" class="filter-btn <?= $status_filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-list"></i> All
            </a>
            <a href="expired.php?status=active" class="filter-btn red <?= $status_filter === 'active' ? 'active' : '' ?>">
                <i class="fas fa-exclamation-triangle"></i> Active
            </a>
            <a href="expired.php?status=inactive" class="filter-btn <?= $status_filter === 'inactive' ? 'active' : '' ?>">
                <i class="fas fa-check-circle"></i> Inactive
            </a>
            <?php if (!empty($search) || $status_filter !== 'all'): ?>
                <a href="expired.php" class="filter-btn clear-filter">
                    <i class="fas fa-times"></i> Clear Filter
                </a>
            <?php endif; ?>
        </div>
        
        <form method="GET" class="search-form">
            <?php if ($status_filter !== 'all'): ?>
                <input type="hidden" name="status" value="<?= $status_filter ?>">
            <?php endif; ?>
            
            <input type="text" name="search" placeholder="🔍 Search expired medicine..." 
                   value="<?= htmlspecialchars($search) ?>">
            
            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i> Search
            </button>
            
            <a href="expired.php" class="btn-reset">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- EXPIRED MEDICINES TABLE - NO EDIT BUTTON -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-red mr-2"></i>
                Expired Medicines
                <span class="result-count ml-2">(<strong><?= number_format(count($expired_medicines)) ?></strong> record(s))</span>
                <?php if ($active_expired > 0): ?>
                    <span class="ml-2 inline-flex bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs border border-red-200 animate-pulse">
                        <i class="fas fa-exclamation-circle mr-1"></i> <?= $active_expired ?> active
                    </span>
                <?php endif; ?>
            </h3>
        </div>
        
        <?php if (count($expired_medicines) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="border-radius: 8px 0 0 0;">#</th>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Expiry Date</th>
                            <th>Days Expired</th>
                            <th>Batch Number</th>
                            <th>Status</th>
                            <th style="border-radius: 0 8px 0 0;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach ($expired_medicines as $item): ?>
                            <tr>
                                <td><?= $counter++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['medication_name']) ?></strong>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></div>
                                </td>
                                <td><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                <td>
                                    <strong style="color: var(--danger);"><?= $item['quantity'] ?></strong>
                                    <?php if ($item['status'] === 'inactive'): ?>
                                        <span class="text-xs text-gray-400">(hidden)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="expired-badge">
                                        <i class="fas fa-calendar-times mr-1"></i>
                                        <?= date('M d, Y', strtotime($item['expiry_date'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="days-expired">
                                        <i class="fas fa-clock"></i>
                                        <?= $item['days_expired'] ?> days ago
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($item['batch_number'])): ?>
                                        <span class="batch-number"><?= htmlspecialchars($item['batch_number']) ?></span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $item['status'] ?? 'active' ?>">
                                        <?= ucfirst($item['status'] ?? 'Active') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <!-- ✅ ONLY View Button -->
                                        <a href="view_inventory.php?id=<?= $item['id'] ?>" 
                                           class="action-btn view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <!-- ✅ Delete Button (Mark as Inactive) - Only for active items -->
                                        <?php if (($item['status'] ?? '') === 'active'): ?>
                                            <form method="POST" action="inventory.php" style="display:inline;" 
                                                  onsubmit="return confirm('⚠️ Warning: This will hide the medicine from inventory. Continue?')">
                                                <input type="hidden" name="action" value="delete_medicine">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="action-btn delete" title="Mark as Inactive">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                <p>No expired medicines found</p>
                <p class="sub">All medicines are within expiry date 🎉</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- RECOMMENDATIONS -->
    <!-- ================================================================ -->
    <?php if (count($expired_medicines) > 0): ?>
    <div class="card mt-4 animate-fade-in-up" style="border-color: var(--danger);">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-lightbulb" style="color: var(--danger);"></i>
                Recommendations
            </h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 border rounded-lg" style="border-color: var(--border-color);">
                <h4 class="font-semibold text-red-600 mb-2">
                    <i class="fas fa-skull mr-1"></i> Active Expired Medicines
                </h4>
                <p class="text-sm text-gray-600">
                    <?php if ($active_expired > 0): ?>
                        <strong><?= $active_expired ?></strong> expired medicine(s) are still <span class="text-red-600 font-semibold">ACTIVE</span> in inventory.
                        <span class="block text-xs text-gray-400 mt-1">
                            ⚠️ These medicines should be <strong>marked as inactive</strong> or <strong>removed</strong> from inventory.
                        </span>
                        <span class="block text-xs text-gray-400 mt-1">
                            <?php 
                                $active_names = array_slice(array_column(
                                    array_filter($expired_medicines, function($item) {
                                        return $item['status'] === 'active';
                                    }), 'medication_name'), 0, 5);
                                if (!empty($active_names)) {
                                    echo '📌 ' . implode(', ', $active_names);
                                    if (count($active_names) > 5) echo ' and ' . (count($active_names) - 5) . ' more';
                                }
                            ?>
                        </span>
                    <?php else: ?>
                        ✅ All expired medicines are already marked as <span class="text-green-600 font-semibold">INACTIVE</span>.
                    <?php endif; ?>
                </p>
            </div>
            <div class="p-4 border rounded-lg" style="border-color: var(--border-color);">
                <h4 class="font-semibold text-orange-600 mb-2">
                    <i class="fas fa-clock mr-1"></i> Actions Required
                </h4>
                <p class="text-sm text-gray-600">
                    <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                        <li>📌 Review expired medicines regularly</li>
                        <li>🗑️ Remove or mark as inactive expired stock</li>
                        <li>📦 Check if any expired medicines are still in active prescriptions</li>
                        <li>🔄 Update inventory to prevent dispensing expired medicines</li>
                        <?php if ($active_expired > 0): ?>
                            <li class="text-red-600 font-semibold">⚠️ <strong><?= $active_expired ?></strong> item(s) need immediate attention!</li>
                        <?php endif; ?>
                    </ul>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Expired Medicines Report
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
    // SEARCH
    // ================================================================
    var searchInput = document.querySelector('.search-form input[type="text"]');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
        });
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var searchInput = document.querySelector('.search-form input[type="text"]');
            searchInput?.focus();
            searchInput?.select();
        }
        if (e.key === 'Escape') {
            var searchInput = document.querySelector('.search-form input[type="text"]');
            if (searchInput && document.activeElement === searchInput) {
                searchInput.value = '';
                searchInput.blur();
            }
        }
    });

    console.log('%c💊 Braick - Expired Medicines Report', 'font-size:18px; font-weight:bold; color:#DC2626;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🗑️ Total Expired: <?= $total_expired ?> (units: <?= $total_expired_units ?>)', 'font-size:13px; color:#DC2626;');
    console.log('%c⚠️ Active Expired: <?= $active_expired ?> (units: <?= $active_expired_units ?>)', 'font-size:13px; color:#D97706;');
    console.log('%c✅ Inactive Expired: <?= $inactive_expired ?> (units: <?= $inactive_expired_units ?>)', 'font-size:13px; color:#6B7280;');
    console.log('%c✅ Shows ALL expired medicines (active + inactive)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Only Dodoma branch (branch_id=1)', 'font-size:13px; color:#34D399;');
    console.log('%c🚫 NO EDIT button - Only View and Delete (Inactive)', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>