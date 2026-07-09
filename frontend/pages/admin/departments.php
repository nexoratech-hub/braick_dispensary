<?php
// ================================================================
// FILE: frontend/pages/admin/departments.php
// SUPER ADMIN - DEPARTMENT MANAGEMENT
// BRAICK DISPENSARY
// ================================================================

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// HANDLE DELETE
// ================================================================
$message = '';
$message_type = '';

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $dept_id = (int)$_GET['id'];
    
    // Check if department has employees
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM employee_departments WHERE department_id = ?");
        $stmt->execute([$dept_id]);
        $employee_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        if ($employee_count > 0) {
            $message = "Cannot delete department! There are <strong>$employee_count</strong> employees assigned to this department.";
            $message_type = 'error';
        } else {
            $stmt = $db->prepare("DELETE FROM departments WHERE id = ?");
            if ($stmt->execute([$dept_id])) {
                $message = "Department deleted successfully!";
                $message_type = 'success';
            } else {
                $message = "Failed to delete department!";
                $message_type = 'error';
            }
        }
    } catch (Exception $e) {
        // If employee_departments table doesn't exist, try direct delete
        $stmt = $db->prepare("DELETE FROM departments WHERE id = ?");
        if ($stmt->execute([$dept_id])) {
            $message = "Department deleted successfully!";
            $message_type = 'success';
        } else {
            $message = "Failed to delete department!";
            $message_type = 'error';
        }
    }
}

// ================================================================
// GET ALL DEPARTMENTS
// ================================================================
$departments = [];
$stmt = $db->query("SELECT * FROM departments ORDER BY name");
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no departments exist, create default ones
if (empty($departments)) {
    $default_departments = [
        ['Medical Department', 'Handles all medical consultations and treatments'],
        ['Laboratory Department', 'Handles all laboratory tests and results'],
        ['Pharmacy Department', 'Handles medicine dispensing and inventory'],
        ['Reception Department', 'Handles patient registration and appointments'],
        ['Finance Department', 'Handles billing, payments and financial records'],
        ['Administration Department', 'Handles administrative tasks and management'],
        ['IT Department', 'Handles system maintenance and support']
    ];
    
    foreach ($default_departments as $dept) {
        $stmt = $db->prepare("INSERT INTO departments (name, description, status) VALUES (?, ?, 'active')");
        $stmt->execute([$dept[0], $dept[1]]);
    }
    
    // Refresh departments
    $stmt = $db->query("SELECT * FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - Braick Dispensary</title>
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --blue-600: #0B5ED7; --blue-700: #0B4EA8; --green-600: #059669; --gray-50: #F8FAFC; --gray-200: #E2E8F0; --gray-400: #94A3B8; --gray-500: #64748B; --gray-700: #334155; --gray-800: #1E293B; --white: #FFFFFF; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: var(--gray-50); color: var(--gray-800); }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--gray-50); }
        ::-webkit-scrollbar-thumb { background: var(--blue-600); border-radius: 10px; }
        .sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: 270px; background: var(--blue-700); color: white; z-index: 50; overflow-y: auto; transition: transform 0.3s ease; }
        .sidebar-brand { padding: 22px 20px 16px; border-bottom: 2px solid #0B3D8A; }
        .sidebar-brand .logo { width: 48px; height: 48px; border-radius: 12px; object-fit: cover; background: white; padding: 4px; }
        .sidebar-brand .brand-text { color: white; font-weight: 700; font-size: 1rem; }
        .sidebar-brand .brand-sub { color: #9EC5FE; font-size: 0.7rem; }
        .sidebar-nav { padding: 14px 10px; }
        .sidebar-nav .nav-label { font-size: 0.55rem; text-transform: uppercase; letter-spacing: 0.1em; color: #6EA8FE; padding: 0 12px; margin: 12px 0 6px; font-weight: 700; }
        .sidebar-link { display: flex; align-items: center; gap: 12px; padding: 9px 14px; border-radius: 10px; color: #D2E3FC; text-decoration: none; transition: all 0.3s; font-size: 0.85rem; font-weight: 500; margin: 2px 0; }
        .sidebar-link:hover { background: #0B3D8A; color: white; }
        .sidebar-link.active { background: var(--green-600); color: white; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.4); }
        .sidebar-link i { width: 20px; text-align: center; font-size: 1rem; }
        .sidebar-link .badge { margin-left: auto; background: #0B3D8A; padding: 1px 9px; border-radius: 20px; font-size: 0.65rem; font-weight: 600; color: white; }
        .top-nav { position: fixed; top: 0; left: 270px; right: 0; height: 68px; background: white; z-index: 40; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; border-bottom: 2px solid #D2E3FC; }
        .top-nav .avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid var(--gray-200); cursor: pointer; transition: all 0.3s; }
        .top-nav .avatar:hover { border-color: var(--blue-600); }
        .top-nav .icon-btn { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--gray-500); transition: all 0.3s; background: transparent; border: none; cursor: pointer; position: relative; }
        .top-nav .icon-btn:hover { background: #E8F0FE; color: var(--blue-600); }
        .notif-dot { position: absolute; top: 6px; right: 6px; width: 8px; height: 8px; background: var(--green-600); border-radius: 50%; border: 2px solid white; }
        .top-nav .datetime { font-size: 0.78rem; color: var(--gray-500); font-weight: 500; }
        .main-content { margin-left: 270px; margin-top: 68px; padding: 24px 28px; min-height: calc(100vh - 68px); }
        .card { background: white; border-radius: 16px; padding: 18px 20px; border: 2px solid var(--gray-200); transition: all 0.3s; }
        .card:hover { border-color: var(--blue-600); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
        .card-title { font-size: 0.9rem; font-weight: 600; color: var(--gray-800); }
        .card-title .title-blue { color: var(--blue-600); }
        .btn { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.65rem; transition: all 0.2s; cursor: pointer; border: none; text-decoration: none; white-space: nowrap; }
        .btn-blue { background: var(--blue-600); color: white; }
        .btn-blue:hover { background: #0B3D8A; transform: translateY(-1px); }
        .btn-green { background: var(--green-600); color: white; }
        .btn-green:hover { background: #047857; transform: translateY(-1px); }
        .btn-outline { background: transparent; color: var(--gray-600); border: 1.5px solid var(--gray-200); }
        .btn-outline:hover { background: #E8F0FE; border-color: var(--blue-600); color: var(--blue-600); }
        .btn-danger { background: #EF4444; color: white; }
        .btn-danger:hover { background: #DC2626; transform: translateY(-1px); }
        .btn-sm { padding: 3px 8px; font-size: 0.6rem; border-radius: 4px; }
        .btn-view { background: var(--blue-600); color: white; padding: 2px 7px; font-size: 0.6rem; border-radius: 4px; }
        .btn-view:hover { background: #0B3D8A; transform: scale(1.05); }
        .btn-edit { background: var(--green-600); color: white; padding: 2px 7px; font-size: 0.6rem; border-radius: 4px; }
        .btn-edit:hover { background: #047857; transform: scale(1.05); }
        .btn-delete { background: #EF4444; color: white; padding: 2px 7px; font-size: 0.6rem; border-radius: 4px; }
        .btn-delete:hover { background: #DC2626; transform: scale(1.05); }
        .action-buttons { display: flex; align-items: center; gap: 3px; flex-wrap: nowrap; justify-content: center; }
        .action-buttons .btn { padding: 2px 7px; font-size: 0.6rem; border-radius: 4px; font-weight: 600; }
        .action-buttons .btn i { font-size: 0.65rem; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .data-table thead th { text-align: left; padding: 8px 10px; font-weight: 700; font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.05em; color: white; background: var(--blue-600); border-bottom: 3px solid #0B3D8A; white-space: nowrap; }
        .data-table tbody tr:nth-child(even) { background: #E8F0FE; }
        .data-table tbody tr:nth-child(odd) { background: white; }
        .data-table tbody tr:hover { background: #D1FAE5; }
        .data-table td { padding: 8px 10px; border-bottom: 1px solid var(--gray-200); color: var(--gray-700); vertical-align: middle; }
        .badge { padding: 2px 10px; border-radius: 20px; font-size: 0.6rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; color: white; border: none; }
        .badge-success { background: var(--green-600); color: white; }
        .badge-danger { background: #EF4444; color: white; }
        .badge-info { background: var(--blue-600); color: white; }
        .page-header { border-bottom: 3px solid var(--blue-600); padding-bottom: 12px; }
        .page-header .page-title { color: var(--blue-700); font-size: 1.8rem; font-weight: 700; }
        .page-header .page-subtitle { color: var(--gray-500); font-size: 0.9rem; }
        .footer { padding: 14px 0; border-top: 2px solid var(--gray-200); margin-top: 20px; text-align: center; font-size: 0.7rem; color: var(--gray-500); }
        .footer .footer-brand { color: var(--blue-600); font-weight: 600; }
        .toast-custom { position: fixed; bottom: 24px; right: 24px; padding: 12px 18px; border-radius: 12px; z-index: 999; max-width: 360px; transform: translateY(100px); opacity: 0; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; gap: 10px; color: white; }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--green-600); }
        .toast-custom.error { background: #EF4444; }
        .toast-custom.info { background: var(--blue-600); }
        @media (max-width: 1024px) { .sidebar { transform: translateX(-100%); } .sidebar.open { transform: translateX(0); } .top-nav { left: 0; } .main-content { margin-left: 0; padding: 16px; } }
        @media (max-width: 640px) { .main-content { padding: 10px; } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in-up { animation: fadeInUp 0.4s ease forwards; opacity: 0; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">Super Admin</p>
            </div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Main Menu</div>
        <a href="dashboard.php" class="sidebar-link"><i class="fas fa-home"></i> Dashboard</a>
        <a href="employees.php" class="sidebar-link"><i class="fas fa-users"></i> Employees</a>
        <a href="branches.php" class="sidebar-link"><i class="fas fa-store-alt"></i> Branches</a>
        <a href="departments.php" class="sidebar-link active"><i class="fas fa-building"></i> Departments <span class="badge"><?= count($departments) ?></span></a>
        <a href="reports.php" class="sidebar-link"><i class="fas fa-chart-bar"></i> Reports</a>
        <div class="nav-label">System</div>
        <a href="settings.php" class="sidebar-link"><i class="fas fa-cog"></i> Settings</a>
        <a href="backups.php" class="sidebar-link"><i class="fas fa-database"></i> Backups</a>
        <a href="system_logs.php" class="sidebar-link"><i class="fas fa-history"></i> System Logs</a>
        <div class="nav-label">Account</div>
        <a href="profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="../../../logout.php" class="sidebar-link" style="margin-top:8px;border-top:2px solid #0B3D8A;padding-top:12px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</aside>

<!-- TOP NAV -->
<nav class="top-nav">
    <div class="flex items-center gap-4">
        <button id="sidebarToggle" class="lg:hidden icon-btn"><i class="fas fa-bars text-lg"></i></button>
    </div>
    <div class="flex items-center gap-3">
        <span class="datetime" id="currentDateTime"></span>
        <button class="icon-btn"><i class="fas fa-bell text-lg"></i><span class="notif-dot"></span></button>
        <a href="profile.php"><img src="<?= $logo_url ?>" alt="Profile" class="avatar" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2238%22 height=%2238%22%3E%3Crect width=%2238%22 height=%2238%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2219%22 y=%2225%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'"></a>
    </div>
</nav>

<!-- MAIN CONTENT -->
<main class="main-content">
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title"><i class="fas fa-building mr-2" style="color: var(--blue-600);"></i> Department Management</h1>
            <p class="page-subtitle">Manage all departments in the organization</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="add_department.php" class="btn btn-blue btn-sm"><i class="fas fa-plus"></i> Add Department</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-list title-blue mr-2"></i> All Departments <span class="text-sm font-normal text-gray-400">(<?= count($departments) ?> departments)</span></h3>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Department Name</th>
                        <th>Description</th>
                        <th>Head of Department</th>
                        <th>Status</th>
                        <th style="border-radius: 0 8px 0 0; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($departments) > 0): ?>
                        <?php foreach ($departments as $index => $dept): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td class="font-medium"><?= htmlspecialchars($dept['name']) ?></td>
                                <td><?= htmlspecialchars($dept['description'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($dept['head_of_department'] ?? 'Not assigned') ?></td>
                                <td>
                                    <span class="badge <?= $dept['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                        <i class="fas fa-circle text-[5px]"></i> <?= ucfirst($dept['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_department.php?id=<?= $dept['id'] ?>" class="btn btn-view" title="View Department"><i class="fas fa-eye"></i></a>
                                        <a href="edit_department.php?id=<?= $dept['id'] ?>" class="btn btn-edit" title="Edit Department"><i class="fas fa-edit"></i></a>
                                        <a href="?action=delete&id=<?= $dept['id'] ?>" class="btn btn-delete" title="Delete Department" onclick="return confirmDelete(<?= $dept['id'] ?>);"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-8 text-gray-400">
                                <i class="fas fa-building text-3xl block mb-2"></i>
                                No departments found. Click "Add Department" to get started.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer class="footer">
        <p><span class="footer-brand">Braick Dispensary</span> Management System &copy; <?= date('Y') ?> All rights reserved</p>
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

<!-- JAVASCRIPT -->
<script>
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    sidebarToggle?.addEventListener('click', function() { sidebar.classList.toggle('open'); });
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    function confirmDelete(deptId) {
        if (confirm('⚠️ Are you sure you want to DELETE this department?\n\nThis action CANNOT be undone!\n\nAll employees assigned to this department must be removed first.')) {
            return true;
        }
        return false;
    }

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
        }, 4000);
    }

    function updateDateTime() {
        var now = new Date();
        document.getElementById('currentDateTime').textContent = 
            now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) + 
            ' • ' + 
            now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c🏢 Braick - Department Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total Departments: <?= count($departments) ?>', 'font-size:13px; color:#059669;');
</script>

</body>
</html>