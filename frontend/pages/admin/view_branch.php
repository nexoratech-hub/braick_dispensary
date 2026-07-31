<?php
// ================================================================
// FILE: frontend/pages/admin/view_branch.php
// SUPER ADMIN - VIEW BRANCH DETAILS (FIXED)
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
// GET BRANCH ID
// ================================================================
$branch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';

if ($branch_id <= 0) {
    header('Location: branches.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// SIMPLE QUERY - GET BRANCH DATA DIRECTLY
// ================================================================
$stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->execute([$branch_id]);
$branch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$branch) {
    header('Location: branches.php?branch=' . $selected_branch_id . '&error=notfound');
    exit;
}

// ================================================================
// GET STATISTICS - SEPARATE QUERIES (NO SUBQUERIES)
// ================================================================

// Staff counts by role
$staff_counts = [
    'admin' => 0,
    'doctor' => 0,
    'reception' => 0,
    'pharmacy' => 0,
    'cashier' => 0,
    'laboratory' => 0
];

try {
    $stmt = $db->prepare("SELECT role, COUNT(*) as count FROM users WHERE branch_id = ? GROUP BY role");
    $stmt->execute([$branch_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        if (isset($staff_counts[$row['role']])) {
            $staff_counts[$row['role']] = (int)$row['count'];
        }
    }
} catch (Exception $e) {
    // Ignore errors
}

$admin_count = $staff_counts['admin'];
$doctor_count = $staff_counts['doctor'];
$reception_count = $staff_counts['reception'];
$pharmacy_count = $staff_counts['pharmacy'];
$cashier_count = $staff_counts['cashier'];
$lab_count = $staff_counts['laboratory'];
$total_staff = $admin_count + $doctor_count + $reception_count + $pharmacy_count + $cashier_count + $lab_count;

// Patient count
$patient_count = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_count = $result['count'] ?? 0;
} catch (Exception $e) {
    $patient_count = 0;
}

// Visit counts
$active_visits = 0;
$completed_visits = 0;
try {
    $stmt = $db->prepare("SELECT status, COUNT(*) as count FROM visits WHERE branch_id = ? GROUP BY status");
    $stmt->execute([$branch_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        if ($row['status'] === 'completed') {
            $completed_visits = (int)$row['count'];
        } elseif (!in_array($row['status'], ['completed', 'cancelled'])) {
            $active_visits += (int)$row['count'];
        }
    }
} catch (Exception $e) {
    // Ignore errors
}
$total_visits = $active_visits + $completed_visits;

// Bill counts and revenue
$paid_bills = 0;
$pending_bills = 0;
$partial_bills = 0;
$total_revenue = 0;

try {
    $stmt = $db->prepare("SELECT status, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue FROM patient_bills WHERE branch_id = ? GROUP BY status");
    $stmt->execute([$branch_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        if ($row['status'] === 'paid') {
            $paid_bills = (int)$row['count'];
            $total_revenue = (float)$row['revenue'];
        } elseif ($row['status'] === 'pending') {
            $pending_bills = (int)$row['count'];
        } elseif ($row['status'] === 'partial') {
            $partial_bills = (int)$row['count'];
        }
    }
} catch (Exception $e) {
    // Ignore errors
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT ACTIVITIES
// ================================================================
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM activity_logs 
        WHERE branch_id = ? OR branch_id IS NULL
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$branch_id]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_activities = [];
}

// ================================================================
// GET RECENT PATIENTS
// ================================================================
$recent_patients = [];
try {
    $stmt = $db->prepare("
        SELECT id, patient_id, full_name, phone, created_at 
        FROM patients 
        WHERE branch_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$branch_id]);
    $recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_patients = [];
}

// ================================================================
// GET RECENT BILLS
// ================================================================
$recent_bills = [];
try {
    $stmt = $db->prepare("
        SELECT id, bill_number, total_amount, status, created_at 
        FROM patient_bills 
        WHERE branch_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$branch_id]);
    $recent_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_bills = [];
}

// ================================================================
// GET STAFF LIST
// ================================================================
$staff = [];
try {
    $stmt = $db->prepare("
        SELECT id, full_name, email, phone, role, status, created_at 
        FROM users 
        WHERE branch_id = ? AND role != 'admin'
        ORDER BY role, full_name
    ");
    $stmt->execute([$branch_id]);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $staff = [];
}

// ================================================================
// STATUS BADGE CLASS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'active' => 'success',
        'inactive' => 'danger',
        'pending' => 'warning',
        'paid' => 'success',
        'partial' => 'warning',
        'cancelled' => 'danger'
    ];
    return isset($classes[$status]) ? $classes[$status] : 'secondary';
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function safeDate($date, $format = 'M d, Y') {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    try {
        $timestamp = strtotime($date);
        if ($timestamp === false || $timestamp <= 0) {
            return 'N/A';
        }
        if (date('Y', $timestamp) < 2000) {
            return 'N/A';
        }
        return date($format, $timestamp);
    } catch (Exception $e) {
        return 'N/A';
    }
}

function safeDateTime($date) {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    try {
        $timestamp = strtotime($date);
        if ($timestamp === false || $timestamp <= 0) {
            return 'N/A';
        }
        if (date('Y', $timestamp) < 2000) {
            return 'N/A';
        }
        return date('M d, Y h:i A', $timestamp);
    } catch (Exception $e) {
        return 'N/A';
    }
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
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
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
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
                <i class="fas fa-store-alt mr-2"></i> <?= htmlspecialchars($branch['name'] ?? 'Unknown Branch') ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-info-circle"></i> View complete branch information
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch['name'] ?? 'N/A') ?>
                </span>
                <span class="ml-2 date-badge">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="edit_branch.php?id=<?= $branch_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit"></i> Edit Branch
            </a>
            <a href="branches.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Branches
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BRANCH INFO CARD -->
    <!-- ================================================================ -->
    <div class="branch-info-card mb-5">
        <div class="branch-info-header">
            <div class="branch-icon-wrapper">
                <i class="fas fa-hospital"></i>
            </div>
            <div class="branch-title-section">
                <h2><?= htmlspecialchars($branch['name'] ?? 'Unknown Branch') ?></h2>
                <span class="branch-id">ID: <?= (int)$branch_id ?></span>
            </div>
            <div class="branch-status-section">
                <?php
                $status = $branch['status'] ?? 'inactive';
                $status_badge = getStatusBadge($status);
                $status_icon = $status === 'active' ? 'check-circle' : 'times-circle';
                ?>
                <span class="badge badge-<?= $status_badge ?>">
                    <i class="fas fa-<?= $status_icon ?>"></i>
                    <?= ucfirst($status) ?>
                </span>
                <span class="branch-created">
                    <i class="fas fa-calendar-alt"></i>
                    Created: <?= safeDate($branch['created_at'] ?? '') ?>
                </span>
            </div>
        </div>
        
        <div class="branch-info-grid">
            <div class="info-item">
                <span class="info-label"><i class="fas fa-map-marker-alt"></i> Location</span>
                <span class="info-value"><?= htmlspecialchars($branch['location'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-phone"></i> Phone</span>
                <span class="info-value"><?= htmlspecialchars($branch['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                <span class="info-value"><?= htmlspecialchars($branch['email'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-clock"></i> Last Updated</span>
                <span class="info-value"><?= safeDateTime($branch['updated_at'] ?? '') ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid mb-5">
        <div class="stat-card stat-blue">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-content">
                <span class="stat-label">Total Staff</span>
                <span class="stat-number"><?= number_format($total_staff) ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-green">
            <div class="stat-icon"><i class="fas fa-user-injured"></i></div>
            <div class="stat-content">
                <span class="stat-label">Total Patients</span>
                <span class="stat-number"><?= number_format($patient_count) ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-orange">
            <div class="stat-icon"><i class="fas fa-stethoscope"></i></div>
            <div class="stat-content">
                <span class="stat-label">Active Visits</span>
                <span class="stat-number"><?= number_format($active_visits) ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-purple">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-content">
                <span class="stat-label">Total Revenue</span>
                <span class="stat-number">TSh <?= number_format($total_revenue, 0) ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-cyan">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-content">
                <span class="stat-label">Pending Bills</span>
                <span class="stat-number"><?= number_format($pending_bills) ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-pink">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <span class="stat-label">Paid Bills</span>
                <span class="stat-number"><?= number_format($paid_bills) ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STAFF BREAKDOWN -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-users-cog title-blue mr-2"></i> Staff Breakdown
                <span class="text-xs text-gray-400 font-normal">(<?= number_format($total_staff) ?> total)</span>
            </h3>
        </div>
        <div class="staff-breakdown">
            <div class="role-item role-admin">
                <span class="role-icon"><i class="fas fa-user-tie"></i></span>
                <span class="role-name">Admins</span>
                <span class="role-count"><?= number_format($admin_count) ?></span>
            </div>
            <div class="role-item role-doctor">
                <span class="role-icon"><i class="fas fa-user-md"></i></span>
                <span class="role-name">Doctors</span>
                <span class="role-count"><?= number_format($doctor_count) ?></span>
            </div>
            <div class="role-item role-reception">
                <span class="role-icon"><i class="fas fa-user-friends"></i></span>
                <span class="role-name">Reception</span>
                <span class="role-count"><?= number_format($reception_count) ?></span>
            </div>
            <div class="role-item role-pharmacy">
                <span class="role-icon"><i class="fas fa-prescription-bottle"></i></span>
                <span class="role-name">Pharmacy</span>
                <span class="role-count"><?= number_format($pharmacy_count) ?></span>
            </div>
            <div class="role-item role-cashier">
                <span class="role-icon"><i class="fas fa-cash-register"></i></span>
                <span class="role-name">Cashiers</span>
                <span class="role-count"><?= number_format($cashier_count) ?></span>
            </div>
            <div class="role-item role-lab">
                <span class="role-icon"><i class="fas fa-flask"></i></span>
                <span class="role-name">Lab Techs</span>
                <span class="role-count"><?= number_format($lab_count) ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VISITS & BILLS SUMMARY -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-stethoscope title-green mr-2"></i> Visits Summary
                </h3>
            </div>
            <div class="summary-stats">
                <div class="summary-stat">
                    <span class="summary-label">Active Visits</span>
                    <span class="summary-value blue"><?= number_format($active_visits) ?></span>
                </div>
                <div class="summary-stat">
                    <span class="summary-label">Completed Visits</span>
                    <span class="summary-value green"><?= number_format($completed_visits) ?></span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-invoice title-blue mr-2"></i> Bills Summary
                </h3>
            </div>
            <div class="summary-stats three">
                <div class="summary-stat">
                    <span class="summary-label">Paid</span>
                    <span class="summary-value green"><?= number_format($paid_bills) ?></span>
                </div>
                <div class="summary-stat">
                    <span class="summary-label">Pending</span>
                    <span class="summary-value orange"><?= number_format($pending_bills) ?></span>
                </div>
                <div class="summary-stat">
                    <span class="summary-label">Partial</span>
                    <span class="summary-value purple"><?= number_format($partial_bills) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PATIENTS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user-injured title-blue mr-2"></i> Recent Patients
                <span class="text-xs text-gray-400 font-normal">(Last 10)</span>
            </h3>
            <a href="patients.php?branch=<?= $branch_id ?>" class="btn btn-sm btn-outline">View All →</a>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Registered</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_patients) > 0): ?>
                        <?php foreach ($recent_patients as $patient): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                                <td class="font-medium"><?= htmlspecialchars($patient['full_name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= safeDate($patient['created_at'] ?? '') ?></td>
                                <td>
                                    <a href="patient_details.php?id=<?= $patient['id'] ?>&branch=<?= $branch_id ?>" 
                                       class="btn btn-sm btn-link">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-gray-400 text-sm py-4">No patients found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STAFF LIST -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-users title-blue mr-2"></i> Staff List
                <span class="text-xs text-gray-400 font-normal">(<?= count($staff) ?> staff members)</span>
            </h3>
            <a href="employees.php?branch=<?= $branch_id ?>" class="btn btn-sm btn-outline">View All →</a>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($staff) > 0): ?>
                        <?php foreach ($staff as $member): ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($member['full_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="role-badge role-<?= $member['role'] ?? 'unknown' ?>">
                                        <?= ucfirst($member['role'] ?? 'Unknown') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($member['email'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($member['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-<?= ($member['status'] ?? 'inactive') === 'active' ? 'success' : 'danger' ?>" style="font-size:0.6rem;">
                                        <?= ucfirst($member['status'] ?? 'Inactive') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_employee.php?id=<?= $member['id'] ?>&branch=<?= $branch_id ?>" 
                                       class="btn btn-sm btn-link">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-gray-400 text-sm py-4">No staff found</td></tr>
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
            <?= htmlspecialchars($branch['name'] ?? 'Branch') ?> Details
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
        --text-primary: #0F172A;
        --text-secondary: #64748B;
        --border-color: #E2E8F0;
        --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
        --shadow-md: 0 4px 20px rgba(0,0,0,0.08);
        --radius: 16px;
        --radius-sm: 10px;
        --blue: #0B5ED7;
        --blue-light: #EFF6FF;
        --green: #059669;
        --green-light: #ECFDF5;
        --orange: #F59E0B;
        --orange-light: #FFFBEB;
        --purple: #7B2FBE;
        --purple-light: #F5F3FF;
        --cyan: #0891B2;
        --cyan-light: #CCFBF1;
        --pink: #DB2777;
        --pink-light: #FCE4EC;
        --red: #EF4444;
        --red-light: #FEE2E2;
    }

    [data-theme="dark"] {
        --bg-body: #0F172A;
        --bg-card: #1E293B;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --border-color: #334155;
        --shadow-sm: 0 2px 8px rgba(0,0,0,0.3);
        --shadow-md: 0 4px 20px rgba(0,0,0,0.4);
        --blue-light: #1E3A5F;
        --green-light: #1A3A2A;
        --orange-light: #3D2E0A;
        --purple-light: #2D1B4E;
        --cyan-light: #0D2E2A;
        --pink-light: #3A1A2A;
    }

    /* ================================================================
       BASE
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
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
    }

    .title-blue { color: var(--blue); }
    .title-green { color: var(--green); }

    /* ================================================================
       BRANCH INFO CARD
       ================================================================ */
    .branch-info-card {
        background: var(--bg-card);
        border-radius: var(--radius);
        border: 1px solid var(--border-color);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        transition: all 0.3s ease;
    }

    .branch-info-card:hover {
        box-shadow: var(--shadow-md);
        border-color: var(--blue);
    }

    .branch-info-header {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 18px 24px;
        background: var(--blue);
        color: white;
        flex-wrap: wrap;
    }

    .branch-icon-wrapper {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        flex-shrink: 0;
        border: 1px solid rgba(255,255,255,0.15);
    }

    .branch-title-section h2 {
        font-size: 1.3rem;
        font-weight: 700;
        margin: 0;
        color: white;
    }

    .branch-title-section .branch-id {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.8);
        font-family: 'Courier New', monospace;
    }

    .branch-status-section {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .branch-created {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.85);
    }

    .branch-created i {
        margin-right: 4px;
    }

    .branch-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        padding: 18px 24px;
    }

    .info-item .info-label {
        font-size: 0.65rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
        display: block;
    }

    .info-item .info-label i {
        margin-right: 4px;
        color: var(--blue);
    }

    .info-item .info-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
        display: block;
        margin-top: 2px;
    }

    /* ================================================================
       STATS CARDS
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 16px;
    }

    .stat-card {
        background: var(--bg-card);
        border-radius: var(--radius-sm);
        padding: 16px 18px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        box-shadow: var(--shadow-sm);
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        transition: height 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
    }

    .stat-card:hover::before {
        height: 6px;
    }

    .stat-card .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
        margin-bottom: 8px;
    }

    .stat-card .stat-content {
        flex: 1;
    }

    .stat-card .stat-label {
        font-size: 0.6rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        display: block;
    }

    .stat-card .stat-number {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--text-primary);
        display: block;
        margin-top: 2px;
    }

    .stat-blue::before { background: var(--blue); }
    .stat-blue .stat-icon { background: var(--blue-light); color: var(--blue); }

    .stat-green::before { background: var(--green); }
    .stat-green .stat-icon { background: var(--green-light); color: var(--green); }

    .stat-orange::before { background: var(--orange); }
    .stat-orange .stat-icon { background: var(--orange-light); color: var(--orange); }

    .stat-purple::before { background: var(--purple); }
    .stat-purple .stat-icon { background: var(--purple-light); color: var(--purple); }

    .stat-cyan::before { background: var(--cyan); }
    .stat-cyan .stat-icon { background: var(--cyan-light); color: var(--cyan); }

    .stat-pink::before { background: var(--pink); }
    .stat-pink .stat-icon { background: var(--pink-light); color: var(--pink); }

    [data-theme="dark"] .stat-blue .stat-icon { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .stat-green .stat-icon { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .stat-orange .stat-icon { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .stat-purple .stat-icon { background: #2D1B4E; color: #A78BFA; }
    [data-theme="dark"] .stat-cyan .stat-icon { background: #0D2E2A; color: #2DD4BF; }
    [data-theme="dark"] .stat-pink .stat-icon { background: #3A1A2A; color: #F472B6; }

    /* ================================================================
       STAFF BREAKDOWN
       ================================================================ */
    .staff-breakdown {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 10px;
        padding: 16px 20px;
    }

    .role-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        background: var(--bg-body);
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }

    .role-item:hover {
        transform: translateY(-2px);
        border-color: var(--blue);
        box-shadow: var(--shadow-sm);
    }

    .role-item .role-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 0.85rem;
    }

    .role-admin .role-icon { background: var(--purple-light); color: var(--purple); }
    .role-doctor .role-icon { background: var(--blue-light); color: var(--blue); }
    .role-reception .role-icon { background: var(--green-light); color: var(--green); }
    .role-pharmacy .role-icon { background: var(--orange-light); color: var(--orange); }
    .role-cashier .role-icon { background: var(--cyan-light); color: var(--cyan); }
    .role-lab .role-icon { background: var(--pink-light); color: var(--pink); }

    [data-theme="dark"] .role-admin .role-icon { background: #2D1B4E; color: #A78BFA; }
    [data-theme="dark"] .role-doctor .role-icon { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .role-reception .role-icon { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .role-pharmacy .role-icon { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .role-cashier .role-icon { background: #0D2E2A; color: #2DD4BF; }
    [data-theme="dark"] .role-lab .role-icon { background: #3A1A2A; color: #F472B6; }

    .role-item .role-name {
        font-size: 0.75rem;
        color: var(--text-secondary);
        flex: 1;
    }

    .role-item .role-count {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    /* ================================================================
       SUMMARY STATS
       ================================================================ */
    .summary-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        padding: 16px 20px;
    }

    .summary-stats.three {
        grid-template-columns: repeat(3, 1fr);
    }

    .summary-stat {
        text-align: center;
        padding: 12px;
        background: var(--bg-body);
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }

    .summary-stat:hover {
        border-color: var(--blue);
        transform: translateY(-2px);
    }

    .summary-stat .summary-label {
        font-size: 0.6rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        font-weight: 600;
        display: block;
    }

    .summary-stat .summary-value {
        font-size: 1.2rem;
        font-weight: 700;
        display: block;
        margin-top: 2px;
    }

    .summary-value.blue { color: var(--blue); }
    .summary-value.green { color: var(--green); }
    .summary-value.orange { color: var(--orange); }
    .summary-value.purple { color: var(--purple); }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        color: white;
        letter-spacing: 0.02em;
        transition: all 0.2s ease;
    }

    .badge-success { background: var(--green); }
    .badge-danger { background: var(--red); }
    .badge-warning { background: var(--orange); color: #1E293B; }
    .badge-info { background: var(--blue); }
    .badge-secondary { background: #94A3B8; }

    /* ================================================================
       ROLE BADGES
       ================================================================ */
    .role-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .role-admin { background: var(--purple-light); color: var(--purple); }
    .role-doctor { background: var(--blue-light); color: var(--blue); }
    .role-reception { background: var(--green-light); color: var(--green); }
    .role-pharmacy { background: var(--orange-light); color: var(--orange); }
    .role-cashier { background: var(--cyan-light); color: var(--cyan); }
    .role-laboratory { background: var(--pink-light); color: var(--pink); }
    .role-unknown { background: var(--border-color); color: var(--text-secondary); }

    [data-theme="dark"] .role-admin { background: #2D1B4E; color: #A78BFA; }
    [data-theme="dark"] .role-doctor { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .role-reception { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .role-pharmacy { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .role-cashier { background: #0D2E2A; color: #2DD4BF; }
    [data-theme="dark"] .role-laboratory { background: #3A1A2A; color: #F472B6; }
    [data-theme="dark"] .role-unknown { background: #334155; color: #94A3B8; }

    /* ================================================================
       DATA TABLE
       ================================================================ */
    .table-responsive {
        overflow-x: auto;
        padding: 0;
    }

    .data-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.8rem;
    }

    .data-table thead th {
        background: var(--blue);
        color: white;
        font-weight: 600;
        padding: 10px 14px;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: none;
        white-space: nowrap;
    }

    .data-table thead th:first-child {
        border-radius: 8px 0 0 0;
    }

    .data-table thead th:last-child {
        border-radius: 0 8px 0 0;
    }

    .data-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
        transition: background 0.2s ease;
    }

    .data-table tbody tr:hover td {
        background: var(--bg-body);
    }

    .data-table tbody tr:last-child td {
        border-bottom: none;
    }

    .data-table .text-center { text-align: center; }
    .data-table .text-xs { font-size: 0.65rem; }
    .data-table .font-mono { font-family: 'Courier New', monospace; }
    .data-table .font-medium { font-weight: 500; }

    /* ================================================================
       BUTTONS
       ================================================================ */
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
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
    }

    .btn-primary {
        background: var(--blue);
        color: white;
        border-color: var(--blue);
    }

    .btn-primary:hover {
        background: #0A4CA8;
        border-color: #0A4CA8;
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.35);
    }

    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 1.5px solid var(--border-color);
    }

    .btn-outline:hover {
        background: var(--bg-body);
        border-color: var(--blue);
        color: var(--blue);
    }

    .btn-link {
        background: transparent;
        color: var(--blue);
        border: none;
        padding: 0 4px;
        font-weight: 500;
        font-size: 0.75rem;
    }

    .btn-link:hover {
        color: #0A4CA8;
        transform: none;
        box-shadow: none;
        text-decoration: underline;
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
        color: var(--blue);
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
        background: var(--blue-light);
        color: var(--blue);
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
        color: var(--blue);
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .main-content { padding: 16px; }
        .branch-info-grid { grid-template-columns: 1fr 1fr; }
    }

    @media (max-width: 768px) {
        .main-content { padding: 12px; }
        .branch-info-header {
            flex-direction: column;
            align-items: flex-start;
            text-align: center;
        }
        .branch-status-section {
            margin-left: 0;
            width: 100%;
            justify-content: center;
        }
        .branch-info-grid { grid-template-columns: 1fr; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .staff-breakdown { grid-template-columns: 1fr 1fr; }
        .summary-stats { grid-template-columns: 1fr; }
        .summary-stats.three { grid-template-columns: 1fr 1fr; }
        .data-table { font-size: 0.7rem; }
        .data-table td, .data-table th { padding: 6px 8px; }
        .data-table thead th { font-size: 0.55rem; padding: 6px 8px; }
        .page-title { font-size: 1.2rem; }
        .page-subtitle { font-size: 0.75rem; }
        .card-header { flex-direction: column; align-items: flex-start; gap: 8px; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 10px; }
        .stats-grid { grid-template-columns: 1fr; }
        .staff-breakdown { grid-template-columns: 1fr; }
        .summary-stats.three { grid-template-columns: 1fr; }
        .page-header { flex-direction: column; align-items: flex-start !important; }
        .btn { font-size: 0.7rem; padding: 5px 10px; }
        .branch-icon-wrapper { width: 44px; height: 44px; font-size: 1.2rem; }
        .branch-title-section h2 { font-size: 1.1rem; }
    }

    /* ================================================================
       PRINT
       ================================================================ */
    @media print {
        .top-nav, .sidebar, #sidebarToggle, .btn, .dark-toggle-btn,
        .icon-btn, .search-wrapper, .page-header .flex.gap-2, .footer {
            display: none !important;
        }
        .main-content { padding: 0 !important; background: white !important; }
        .card, .branch-info-card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
            break-inside: avoid;
        }
        .branch-info-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .data-table thead th { background: #0B5ED7 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .badge, .stat-icon, .role-icon, .role-badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // Dark Mode
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

    // Sidebar Toggle
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

    // Search
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // Branch Switcher
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    // Date & Time
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

    console.log('%c🏢 Braick Dispensary - View Branch', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Branch: <?= addslashes($branch['name'] ?? 'Unknown') ?> (ID: <?= $branch_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c📍 Location: <?= addslashes($branch['location'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📞 Phone: <?= addslashes($branch['phone'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📧 Email: <?= addslashes($branch['email'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>