<?php
// ================================================================
// FILE: frontend/pages/admin/users.php
// SUPER ADMIN - USERS MANAGEMENT
// VIEW AND MANAGE ALL SYSTEM USERS
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET FILTERS
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$selected_role = $_GET['role'] ?? 'all';
$selected_status = $_GET['status'] ?? 'all';
$search_term = $_GET['search'] ?? '';

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// FETCH USERS - NO profile_image, using profile_pic instead
// ================================================================
$query = "
    SELECT 
        u.id,
        u.full_name,
        u.username,
        u.email,
        u.phone,
        u.role,
        u.status,
        u.branch_id,
        u.profile_pic,
        u.created_at,
        u.last_online,
        u.updated_at,
        b.name as branch_name,
        (SELECT COUNT(*) FROM visits WHERE doctor_id = u.id) as visit_count,
        (SELECT COUNT(*) FROM prescriptions WHERE doctor_id = u.id) as prescription_count,
        (SELECT COUNT(*) FROM patient_bills WHERE created_by = u.id) as bill_count
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE 1=1
";

if (!empty($search_term)) {
    $query .= " AND (u.full_name LIKE :search OR u.username LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)";
}

if ($selected_role !== 'all') {
    $query .= " AND u.role = :role";
}

if ($selected_branch_id !== 'all') {
    $query .= " AND u.branch_id = :branch_id";
}

if ($selected_status !== 'all') {
    $query .= " AND u.status = :status";
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);

if (!empty($search_term)) {
    $stmt->bindValue(':search', '%' . $search_term . '%');
}

if ($selected_role !== 'all') {
    $stmt->bindValue(':role', $selected_role);
}

if ($selected_branch_id !== 'all') {
    $stmt->bindValue(':branch_id', $selected_branch_id);
}

if ($selected_status !== 'all') {
    $stmt->bindValue(':status', $selected_status);
}

$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
$total_users = count($users);
$active_users = 0;
$admin_count = 0;
$doctor_count = 0;
$receptionist_count = 0;
$pharmacist_count = 0;
$cashier_count = 0;
$laboratory_count = 0;

foreach ($users as $user) {
    if ($user['status'] === 'active') {
        $active_users++;
    }
    switch ($user['role']) {
        case 'admin': $admin_count++; break;
        case 'doctor': $doctor_count++; break;
        case 'reception': $receptionist_count++; break;
        case 'pharmacy': $pharmacist_count++; break;
        case 'cashier': $cashier_count++; break;
        case 'laboratory': $laboratory_count++; break;
    }
}

// ================================================================
// GET ROLE BADGE CLASS
// ================================================================
function getRoleBadge($role) {
    $classes = [
        'admin' => 'danger',
        'doctor' => 'primary',
        'reception' => 'info',
        'pharmacy' => 'success',
        'cashier' => 'warning',
        'laboratory' => 'secondary'
    ];
    return $classes[$role] ?? 'secondary';
}

function getRoleIcon($role) {
    $icons = [
        'admin' => 'fa-user-tie',
        'doctor' => 'fa-user-md',
        'reception' => 'fa-user-nurse',
        'pharmacy' => 'fa-prescription-bottle',
        'cashier' => 'fa-calculator',
        'laboratory' => 'fa-microscope'
    ];
    return $icons[$role] ?? 'fa-user';
}

function getStatusBadge($status) {
    return $status === 'active' ? 'success' : 'danger';
}

// ================================================================
// FORMAT LAST LOGIN - Using last_online instead of last_login
// ================================================================
function formatLastLogin($last_online) {
    if (empty($last_online) || $last_online === '0000-00-00 00:00:00') {
        return 'Never';
    }
    return date('M d, Y h:i A', strtotime($last_online));
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER
// ================================================================
include_once '../../components/admin_header.php';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - SHARED HEADER -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search users..." value="<?= htmlspecialchars($search_term) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
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
                <i class="fas fa-users-cog mr-2"></i> Users Management
            </h1>
            <p class="page-subtitle">
                Manage all system users across branches
                <span class="user-tag ml-2">
                    <i class="fas fa-user"></i> <?= $total_users ?> Users
                </span>
                <span class="ml-2 date-badge">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="add_user.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus-circle"></i> Add User
            </a>
            <button onclick="window.location.reload()" class="btn btn-outline btn-sm">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS SUMMARY -->
    <!-- ================================================================ -->
    <div class="stats-summary-grid mb-5">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <p class="stat-label">Total Users</p>
                <p class="stat-value"><?= $total_users ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <p class="stat-label">Active Users</p>
                <p class="stat-value"><?= $active_users ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-user-tie"></i>
            </div>
            <div>
                <p class="stat-label">Admins</p>
                <p class="stat-value"><?= $admin_count ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-user-md"></i>
            </div>
            <div>
                <p class="stat-label">Doctors</p>
                <p class="stat-value"><?= $doctor_count ?></p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filters-card mb-5">
        <form method="GET" action="" class="filters-form">
            <div class="filter-group">
                <label>Role</label>
                <select name="role" id="roleFilter" class="filter-select">
                    <option value="all" <?= $selected_role === 'all' ? 'selected' : '' ?>>All Roles</option>
                    <option value="admin" <?= $selected_role === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="doctor" <?= $selected_role === 'doctor' ? 'selected' : '' ?>>Doctor</option>
                    <option value="reception" <?= $selected_role === 'reception' ? 'selected' : '' ?>>Reception</option>
                    <option value="pharmacy" <?= $selected_role === 'pharmacy' ? 'selected' : '' ?>>Pharmacy</option>
                    <option value="cashier" <?= $selected_role === 'cashier' ? 'selected' : '' ?>>Cashier</option>
                    <option value="laboratory" <?= $selected_role === 'laboratory' ? 'selected' : '' ?>>Laboratory</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Branch</label>
                <select name="branch" id="branchFilter" class="filter-select">
                    <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>All Branches</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($branch['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Status</label>
                <select name="status" id="statusFilter" class="filter-select">
                    <option value="all" <?= $selected_status === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="active" <?= $selected_status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $selected_status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <a href="users.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- USERS TABLE -->
    <!-- ================================================================ -->
    <?php if (count($users) > 0): ?>
        <div class="card">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Branch</th>
                            <th>Status</th>
                            <th>Last Online</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar-sm">
                                            <?php if (!empty($user['profile_pic'])): ?>
                                                <img src="<?= htmlspecialchars($user['profile_pic']) ?>" alt="<?= htmlspecialchars($user['full_name']) ?>">
                                            <?php else: ?>
                                                <span class="avatar-initials-sm">
                                                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="user-name-sm"><?= htmlspecialchars($user['full_name']) ?></div>
                                            <div class="user-email-sm">
                                                <i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email'] ?? 'N/A') ?>
                                            </div>
                                            <div class="user-phone-sm">
                                                <i class="fas fa-phone"></i> <?= htmlspecialchars($user['phone'] ?? 'N/A') ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="username-cell">@<?= htmlspecialchars($user['username']) ?></span>
                                    <div class="user-id-cell">ID: <?= $user['id'] ?></div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?= $user['role'] ?>">
                                        <i class="fas <?= getRoleIcon($user['role']) ?>"></i>
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="branch-cell">
                                        <i class="fas fa-store-alt"></i>
                                        <?= htmlspecialchars($user['branch_name'] ?? 'No Branch') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($user['status']) ?>">
                                        <i class="fas fa-circle"></i>
                                        <?= ucfirst($user['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="login-cell">
                                        <i class="fas fa-clock"></i>
                                        <?= formatLastLogin($user['last_online'] ?? null) ?>
                                    </div>
                                    <?php if ($user['role'] === 'doctor'): ?>
                                        <div class="doctor-stats-sm">
                                            <span title="Visits"><i class="fas fa-stethoscope"></i> <?= $user['visit_count'] ?? 0 ?></span>
                                            <span title="Prescriptions"><i class="fas fa-prescription"></i> <?= $user['prescription_count'] ?? 0 ?></span>
                                            <span title="Bills"><i class="fas fa-file-invoice"></i> <?= $user['bill_count'] ?? 0 ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['status'] === 'active'): ?>
                                            <button onclick="toggleUser(<?= $user['id'] ?>, 'inactive')" class="btn btn-sm btn-outline-danger" title="Deactivate">
                                                <i class="fas fa-pause"></i>
                                            </button>
                                        <?php else: ?>
                                            <button onclick="toggleUser(<?= $user['id'] ?>, 'active')" class="btn btn-sm btn-outline-success" title="Activate">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="deleteUser(<?= $user['id'] ?>)" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            <h3>No Users Found</h3>
            <p>No users match your search criteria. Try adjusting your filters or add a new user.</p>
            <a href="add_user.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Add User
            </a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Users Management
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<style>
    /* ================================================================
       ROOT VARIABLES
       ================================================================ */
    :root {
        --bg-body: #F1F5F9;
        --bg-card: #FFFFFF;
        --bg-header: #0B5ED7;
        --text-primary: #0F172A;
        --text-secondary: #64748B;
        --border-color: #E2E8F0;
        --table-hover: #F8FAFC;
        --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
        --shadow-md: 0 4px 20px rgba(0,0,0,0.08);
        --radius: 16px;
        --radius-sm: 10px;
    }

    [data-theme="dark"] {
        --bg-body: #0F172A;
        --bg-card: #1E293B;
        --bg-header: #0B5ED7;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --border-color: #334155;
        --table-hover: #1E293B;
        --shadow-sm: 0 2px 8px rgba(0,0,0,0.3);
        --shadow-md: 0 4px 20px rgba(0,0,0,0.4);
    }

    /* ================================================================
       BASE STYLES
       ================================================================ */
    .main-content {
        padding: 20px 24px;
        background: var(--bg-body);
        min-height: 100vh;
        transition: all 0.3s ease;
    }

    .card {
        background: var(--bg-card);
        border-radius: var(--radius);
        border: 1px solid var(--border-color);
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: var(--shadow-sm);
    }

    .card:hover {
        box-shadow: var(--shadow-md);
    }

    .overflow-x-auto {
        overflow-x: auto;
    }

    /* ================================================================
       STATISTICS SUMMARY
       ================================================================ */
    .stats-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
    }

    .stat-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.3s ease;
        box-shadow: var(--shadow-sm);
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
        border-color: #0B5ED7;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
        transition: all 0.3s ease;
    }

    .stat-icon.blue { background: #EFF6FF; color: #0B5ED7; }
    .stat-icon.green { background: #ECFDF5; color: #059669; }
    .stat-icon.purple { background: #F5F3FF; color: #7B2FBE; }
    .stat-icon.orange { background: #FFFBEB; color: #F59E0B; }

    [data-theme="dark"] .stat-icon.blue { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .stat-icon.green { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .stat-icon.purple { background: #2D1B4E; color: #A78BFA; }
    [data-theme="dark"] .stat-icon.orange { background: #3D2E0A; color: #FBBF24; }

    .stat-card:hover .stat-icon {
        transform: scale(1.05);
    }

    .stat-label {
        font-size: 0.65rem;
        color: var(--text-secondary);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0;
    }

    .stat-value {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 2px 0 0 0;
    }

    /* ================================================================
       FILTERS CARD
       ================================================================ */
    .filters-card {
        background: var(--bg-card);
        border-radius: var(--radius);
        border: 1px solid var(--border-color);
        padding: 16px 20px;
        box-shadow: var(--shadow-sm);
    }

    .filters-form {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 12px;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex: 1;
        min-width: 140px;
    }

    .filter-group label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .filter-select {
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        background: var(--bg-body);
        color: var(--text-primary);
        font-size: 0.8rem;
        transition: all 0.3s ease;
        width: 100%;
    }

    .filter-select:focus {
        outline: none;
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }

    .filter-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        padding-bottom: 2px;
    }

    /* ================================================================
       DATA TABLE
       ================================================================ */
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
        padding: 12px 16px;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: none !important;
        white-space: nowrap;
        text-align: left;
    }

    .data-table thead th:first-child {
        border-radius: 8px 0 0 0;
    }

    .data-table thead th:last-child {
        border-radius: 0 8px 0 0;
        text-align: center;
    }

    .data-table td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
        transition: background 0.2s ease;
    }

    .data-table tbody tr {
        transition: background 0.2s ease;
    }

    .data-table tbody tr:hover td {
        background: var(--table-hover);
    }

    .data-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* User Cell */
    .user-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-avatar-sm {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
        background: #0B5ED7;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .user-avatar-sm img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .avatar-initials-sm {
        color: white;
        font-weight: 700;
        font-size: 1rem;
        text-transform: uppercase;
    }

    .user-name-sm {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.85rem;
    }

    .user-email-sm,
    .user-phone-sm {
        font-size: 0.65rem;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .user-email-sm i,
    .user-phone-sm i {
        font-size: 0.5rem;
        color: #0B5ED7;
    }

    /* Username Cell */
    .username-cell {
        font-weight: 600;
        color: var(--text-primary);
        font-family: 'Courier New', monospace;
        font-size: 0.8rem;
    }

    .user-id-cell {
        font-size: 0.6rem;
        color: var(--text-secondary);
    }

    /* Branch Cell */
    .branch-cell {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8rem;
        color: var(--text-secondary);
    }

    .branch-cell i {
        color: #0B5ED7;
        font-size: 0.7rem;
    }

    /* Login Cell */
    .login-cell {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.75rem;
        color: var(--text-secondary);
    }

    .login-cell i {
        color: #0B5ED7;
        font-size: 0.65rem;
    }

    .doctor-stats-sm {
        display: flex;
        gap: 8px;
        margin-top: 4px;
        font-size: 0.6rem;
        color: var(--text-secondary);
    }

    .doctor-stats-sm span {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        background: var(--bg-body);
        padding: 1px 6px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }

    .doctor-stats-sm i {
        color: #0B5ED7;
    }

    /* ================================================================
       ROLE BADGES
       ================================================================ */
    .role-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        white-space: nowrap;
    }

    .role-admin { background: #FEE2E2; color: #DC2626; }
    .role-doctor { background: #DBEAFE; color: #2563EB; }
    .role-reception { background: #E0F2FE; color: #0891B2; }
    .role-pharmacy { background: #D1FAE5; color: #059669; }
    .role-cashier { background: #FEF3C7; color: #D97706; }
    .role-laboratory { background: #E8E8E8; color: #4B5563; }

    [data-theme="dark"] .role-admin { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .role-doctor { background: #1A2A4A; color: #60A5FA; }
    [data-theme="dark"] .role-reception { background: #0A2A3A; color: #22D3EE; }
    [data-theme="dark"] .role-pharmacy { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .role-cashier { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .role-laboratory { background: #374151; color: #9CA3AF; }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        color: white;
        letter-spacing: 0.02em;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .badge-success { background: #059669; }
    .badge-danger { background: #EF4444; }
    .badge-warning { background: #F59E0B; color: #1E293B; }

    [data-theme="dark"] .badge-warning { color: #1E293B; }

    .badge i {
        font-size: 0.45rem;
    }

    /* ================================================================
       ACTION BUTTONS
       ================================================================ */
    .action-buttons {
        display: flex;
        gap: 4px;
        justify-content: center;
    }

    .action-buttons .btn {
        padding: 4px 8px;
        font-size: 0.7rem;
        border-radius: 6px;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        background: var(--bg-card);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .btn-sm {
        padding: 5px 10px;
        font-size: 0.65rem;
        border-radius: 6px;
    }

    .btn-primary {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }

    .btn-primary:hover {
        background: #0A4FB0;
        border-color: #0A4FB0;
        color: white;
    }

    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 1.5px solid var(--border-color);
    }

    .btn-outline:hover {
        background: var(--bg-body);
        border-color: #0B5ED7;
        color: #0B5ED7;
    }

    .btn-outline-danger {
        color: #EF4444;
        border-color: #EF4444;
    }

    .btn-outline-danger:hover {
        background: #EF4444;
        color: white;
    }

    .btn-outline-success {
        color: #059669;
        border-color: #059669;
    }

    .btn-outline-success:hover {
        background: #059669;
        color: white;
    }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: var(--bg-card);
        border-radius: var(--radius);
        border: 1px solid var(--border-color);
    }

    .empty-state i {
        font-size: 4rem;
        color: var(--text-secondary);
        opacity: 0.3;
        margin-bottom: 16px;
        display: block;
    }

    .empty-state h3 {
        font-size: 1.2rem;
        color: var(--text-primary);
        margin: 0 0 8px 0;
    }

    .empty-state p {
        color: var(--text-secondary);
        margin: 0 0 20px 0;
        font-size: 0.9rem;
    }

    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-title {
        font-size: 1.5rem;
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

    .user-tag {
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

    [data-theme="dark"] .user-tag {
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

    .flex { display: flex; }
    .flex-wrap { flex-wrap: wrap; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .gap-2 { gap: 8px; }
    .gap-3 { gap: 12px; }
    .gap-4 { gap: 16px; }
    .gap-5 { gap: 20px; }
    .mb-5 { margin-bottom: 20px; }
    .ml-2 { margin-left: 8px; }
    .mr-1 { margin-right: 4px; }
    .mr-2 { margin-right: 8px; }
    .text-gray-300 { color: #94A3B8; }
    .mx-2 { margin-left: 8px; margin-right: 8px; }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer {
        margin-top: 30px;
        padding: 16px 20px;
        background: var(--bg-card);
        border-radius: var(--radius);
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

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .main-content {
            padding: 16px;
        }
        
        .data-table {
            font-size: 0.7rem;
        }
        
        .data-table td,
        .data-table th {
            padding: 8px 12px;
        }
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 12px;
        }
        
        .stats-summary-grid {
            grid-template-columns: 1fr 1fr;
        }
        
        .filters-form {
            flex-direction: column;
            align-items: stretch;
        }
        
        .filter-group {
            min-width: 100%;
        }
        
        .filter-actions {
            justify-content: flex-end;
        }
        
        .page-title {
            font-size: 1.2rem;
        }
        
        .page-subtitle {
            font-size: 0.75rem;
        }
        
        .user-cell {
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }
        
        .action-buttons {
            flex-wrap: wrap;
        }
    }

    @media (max-width: 480px) {
        .main-content {
            padding: 10px;
        }
        
        .stats-summary-grid {
            grid-template-columns: 1fr;
        }
        
        .page-header {
            flex-direction: column;
            align-items: flex-start !important;
        }
        
        .btn {
            font-size: 0.7rem;
            padding: 5px 10px;
        }
        
        .btn-sm {
            font-size: 0.6rem;
            padding: 3px 6px;
        }
        
        .data-table {
            font-size: 0.6rem;
        }
        
        .data-table td,
        .data-table th {
            padding: 6px 8px;
        }
        
        .data-table thead th {
            font-size: 0.5rem;
            padding: 6px 8px;
        }
    }

    /* ================================================================
       PRINT STYLES
       ================================================================ */
    @media print {
        .top-nav,
        .sidebar,
        #sidebarToggle,
        .btn,
        .dark-toggle-btn,
        .icon-btn,
        .search-wrapper,
        .filters-card,
        .page-header .flex.gap-2,
        .footer,
        .action-buttons {
            display: none !important;
        }
        
        .main-content {
            padding: 0 !important;
            background: white !important;
        }
        
        .card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }
        
        .data-table thead th {
            background: #0B5ED7 !important;
            color: white !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .badge {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .badge-success { background: #059669 !important; color: white !important; }
        .badge-danger { background: #EF4444 !important; color: white !important; }
        
        .role-badge {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .role-admin { background: #FEE2E2 !important; color: #DC2626 !important; }
        .role-doctor { background: #DBEAFE !important; color: #2563EB !important; }
        .role-reception { background: #E0F2FE !important; color: #0891B2 !important; }
        .role-pharmacy { background: #D1FAE5 !important; color: #059669 !important; }
        .role-cashier { background: #FEF3C7 !important; color: #D97706 !important; }
        .role-laboratory { background: #E8E8E8 !important; color: #4B5563 !important; }
        
        .user-avatar-sm {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            background: #0B5ED7 !important;
        }
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
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    var roleFilter = document.getElementById('roleFilter');
    var branchFilter = document.getElementById('branchFilter');
    var statusFilter = document.getElementById('statusFilter');

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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
    // SEARCH
    // ================================================================
    function performSearch() {
        var query = searchInput.value.trim();
        var role = roleFilter.value;
        var branch = branchFilter.value;
        var status = statusFilter.value;
        
        var url = 'users.php?';
        var params = [];
        
        if (query.length > 0) params.push('search=' + encodeURIComponent(query));
        if (role !== 'all') params.push('role=' + encodeURIComponent(role));
        if (branch !== 'all') params.push('branch=' + encodeURIComponent(branch));
        if (status !== 'all') params.push('status=' + encodeURIComponent(status));
        
        window.location.href = url + params.join('&');
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // TOGGLE USER STATUS
    // ================================================================
    function toggleUser(id, status) {
        if (confirm('Are you sure you want to ' + (status === 'active' ? 'activate' : 'deactivate') + ' this user?')) {
            fetch('ajax/toggle_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: id,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to update user status. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
    }

    // ================================================================
    // DELETE USER
    // ================================================================
    function deleteUser(id) {
        if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            fetch('ajax/delete_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to delete user. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c👥 Braick Dispensary - Users Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total Users: <?= $total_users ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Active Users: <?= $active_users ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>