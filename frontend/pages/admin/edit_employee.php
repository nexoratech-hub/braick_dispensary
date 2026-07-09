<?php
// ================================================================
// FILE: frontend/pages/admin/edit_employee.php
// SUPER ADMIN - EDIT EMPLOYEE
// ================================================================

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

$employee_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($employee_id <= 0) {
    header('Location: employees.php?branch=' . $selected_branch_id);
    exit;
}

// Get employee data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role != 'admin'");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: employees.php?branch=' . $selected_branch_id);
    exit;
}

// Get branches
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active'");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get roles
$roles = [];
try {
    $stmt = $db->query("SELECT id, name FROM roles ORDER BY name");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $roles = [];
}

// Get departments
$departments = [];
try {
    $stmt = $db->query("SELECT id, name FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $departments = [];
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? '';
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    
    $stmt = $db->prepare("
        UPDATE users 
        SET full_name = ?, email = ?, phone = ?, role = ?, branch_id = ?, status = ?
        WHERE id = ? AND role != 'admin'
    ");
    
    if ($stmt->execute([$full_name, $email, $phone, $role, $branch_id, $status, $employee_id])) {
        $message = "Employee updated successfully!";
        $message_type = 'success';
        header('Location: employees.php?branch=' . $branch_id . '&updated=1');
        exit;
    } else {
        $message = "Failed to update employee!";
        $message_type = 'error';
    }
}

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee - Braick Dispensary</title>
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Same styles as add_employee.php */
        :root { --primary: #0B5ED7; --primary-dark: #0A4CA8; --secondary: #0AA84F; --bg-body: #F1F5F9; --bg-card: #FFFFFF; --border-color: #E2E8F0; --radius: 18px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: var(--bg-body); color: #1E293B; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        .sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: 270px; background: var(--primary); color: white; z-index: 50; overflow-y: auto; transition: transform 0.3s ease; }
        .sidebar-brand { padding: 22px 20px 16px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand .logo { width: 48px; height: 48px; border-radius: 12px; object-fit: cover; background: white; padding: 4px; }
        .sidebar-nav { padding: 14px 10px; }
        .sidebar-nav .nav-label { font-size: 0.55rem; text-transform: uppercase; letter-spacing: 0.1em; color: rgba(255,255,255,0.4); padding: 0 12px; margin: 12px 0 6px; font-weight: 700; }
        .sidebar-link { display: flex; align-items: center; gap: 12px; padding: 9px 14px; border-radius: 10px; color: rgba(255,255,255,0.75); text-decoration: none; transition: all 0.3s; font-size: 0.85rem; font-weight: 500; margin: 2px 0; }
        .sidebar-link:hover { background: rgba(10, 168, 79, 0.25); color: #FFFFFF; }
        .sidebar-link.active { background: var(--secondary); color: white; box-shadow: 0 4px 12px rgba(10, 168, 79, 0.4); }
        .sidebar-link i { width: 20px; text-align: center; font-size: 1rem; }
        .top-nav { position: fixed; top: 0; left: 270px; right: 0; height: 68px; background: var(--bg-card); z-index: 40; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; border-bottom: 1px solid var(--border-color); }
        .top-nav .search-wrapper { display: flex; align-items: center; background: var(--bg-body); border-radius: 10px; border: 1px solid var(--border-color); transition: all 0.3s; }
        .top-nav .search-wrapper:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12); }
        .top-nav .search-wrapper input { border: none; background: transparent; padding: 8px 14px; width: 280px; font-size: 0.85rem; outline: none; color: #1E293B; }
        .top-nav .search-wrapper input::placeholder { color: #94A3B8; }
        .top-nav .search-wrapper .search-btn { background: var(--primary); color: white; border: none; padding: 8px 16px; border-radius: 0 10px 10px 0; cursor: pointer; font-size: 0.85rem; transition: all 0.3s; }
        .top-nav .search-wrapper .search-btn:hover { background: var(--primary-dark); }
        .top-nav .branch-selector { border: 1px solid var(--border-color); border-radius: 10px; padding: 6px 12px; background: var(--bg-body); font-size: 0.82rem; font-weight: 500; cursor: pointer; outline: none; min-width: 180px; color: #1E293B; transition: all 0.3s; }
        .top-nav .branch-selector:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12); }
        .top-nav .datetime { font-size: 0.78rem; color: #64748B; font-weight: 500; }
        .top-nav .avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); cursor: pointer; transition: all 0.3s; }
        .top-nav .avatar:hover { border-color: var(--primary); }
        .top-nav .icon-btn { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #64748B; transition: all 0.3s; background: transparent; border: none; cursor: pointer; position: relative; }
        .top-nav .icon-btn:hover { background: var(--bg-body); color: var(--primary); }
        .notif-dot { position: absolute; top: 6px; right: 6px; width: 8px; height: 8px; background: #EF4444; border-radius: 50%; border: 2px solid var(--bg-card); }
        .main-content { margin-left: 270px; margin-top: 68px; padding: 24px 28px; min-height: calc(100vh - 68px); }
        .card { background: var(--bg-card); border-radius: var(--radius); padding: 24px; border: 1px solid var(--border-color); transition: all 0.3s; }
        .card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        .card-title { font-size: 1.1rem; font-weight: 600; color: #1E293B; }
        .card-title i { color: var(--primary); }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 20px; border-radius: 10px; font-weight: 600; font-size: 0.85rem; transition: all 0.3s; cursor: pointer; border: none; text-decoration: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }
        .btn-secondary { background: var(--secondary); color: white; }
        .btn-secondary:hover { background: #08944A; transform: translateY(-2px); }
        .btn-outline { background: transparent; color: #64748B; border: 1px solid var(--border-color); }
        .btn-outline:hover { background: var(--bg-body); border-color: var(--primary); color: var(--primary); }
        .btn-sm { padding: 4px 12px; font-size: 0.75rem; border-radius: 6px; }
        .form-label { font-size: 0.8rem; font-weight: 600; color: #1E293B; margin-bottom: 4px; display: block; }
        .form-control { width: 100%; padding: 10px 14px; border: 2px solid var(--border-color); border-radius: 10px; font-size: 0.9rem; transition: all 0.3s; outline: none; background: var(--bg-card); color: #1E293B; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12); }
        @media (max-width: 1024px) { .sidebar { transform: translateX(-100%); } .sidebar.open { transform: translateX(0); } .top-nav { left: 0; } .main-content { margin-left: 0; padding: 16px; } .top-nav .search-wrapper input { width: 160px; } }
        @media (max-width: 640px) { .top-nav .search-wrapper input { width: 100px; } .top-nav .branch-selector { min-width: 120px; font-size: 0.7rem; } .top-nav .datetime { display: none; } .main-content { padding: 10px; } }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B5ED7%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p class="font-bold text-base leading-tight">Braick Dispensary</p>
                <p class="text-xs opacity-80">Super Admin</p>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-label">Main Menu</div>
        <a href="dashboard.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-home"></i> Dashboard</a>
        <a href="employees.php?branch=<?= $selected_branch_id ?>" class="sidebar-link active"><i class="fas fa-users"></i> Employees</a>
        <a href="branches.php" class="sidebar-link"><i class="fas fa-store-alt"></i> Branches</a>
        <a href="departments.php" class="sidebar-link"><i class="fas fa-building"></i> Departments</a>
        <a href="reports.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-chart-bar"></i> Reports</a>
        <div class="nav-label">System</div>
        <a href="settings.php" class="sidebar-link"><i class="fas fa-cog"></i> Settings</a>
        <a href="backups.php" class="sidebar-link"><i class="fas fa-database"></i> Backups</a>
        <a href="system_logs.php" class="sidebar-link"><i class="fas fa-history"></i> System Logs</a>
        <div class="nav-label">Account</div>
        <a href="profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="../../../logout.php" class="sidebar-link" style="margin-top:8px;border-top:1px solid rgba(255,255,255,0.1);padding-top:12px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</aside>

<!-- TOP NAV -->
<nav class="top-nav">
    <div class="flex items-center gap-4">
        <button id="sidebarToggle" class="lg:hidden icon-btn"><i class="fas fa-bars text-lg"></i></button>
        <div class="search-wrapper">
            <input type="text" placeholder="Search...">
            <button class="search-btn"><i class="fas fa-search"></i></button>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <select class="branch-selector"><option>🌐 All Branches</option></select>
        <span class="datetime" id="currentDateTime"></span>
        <button class="icon-btn"><i class="fas fa-bell text-lg"></i><span class="notif-dot"></span></button>
        <a href="profile.php"><img src="<?= $logo_url ?>" alt="Profile" class="avatar" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2238%22 height=%2238%22%3E%3Crect width=%2238%22 height=%2238%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2219%22 y=%2225%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'"></a>
    </div>
</nav>

<!-- MAIN CONTENT -->
<main class="main-content">
    <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-[#0B5ED7]"><i class="fas fa-user-edit mr-2"></i> Edit Employee</h1>
            <p class="text-sm text-[#64748B]">Update employee information</p>
        </div>
        <div>
            <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Employees
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-[#E6F7EE] text-[#0AA84F] border border-[#0AA84F]/20' : 'bg-[#FEF2F2] text-[#EF4444] border border-[#EF4444]/20' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <h3 class="text-lg font-semibold text-[#0B5ED7] border-b pb-2 mb-4">
                        <i class="fas fa-user mr-2"></i> Personal Information
                    </h3>
                </div>
                
                <div>
                    <label class="form-label">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($employee['full_name']) ?>" required>
                </div>
                
                <div>
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($employee['username']) ?>" disabled>
                    <p class="text-xs text-gray-400 mt-1">Username cannot be changed</p>
                </div>
                
                <div>
                    <label class="form-label">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($employee['email']) ?>" required>
                </div>
                
                <div>
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($employee['phone'] ?? '') ?>">
                </div>
                
                <div class="md:col-span-2 mt-4">
                    <h3 class="text-lg font-semibold text-[#0B5ED7] border-b pb-2 mb-4">
                        <i class="fas fa-briefcase mr-2"></i> Professional Information
                    </h3>
                </div>
                
                <div>
                    <label class="form-label">Role <span class="text-red-500">*</span></label>
                    <select name="role" class="form-control" required>
                        <option value="doctor" <?= $employee['role'] === 'doctor' ? 'selected' : '' ?>>Doctor</option>
                        <option value="reception" <?= $employee['role'] === 'reception' ? 'selected' : '' ?>>Receptionist</option>
                        <option value="laboratory" <?= $employee['role'] === 'laboratory' ? 'selected' : '' ?>>Laboratory Technician</option>
                        <option value="pharmacy" <?= $employee['role'] === 'pharmacy' ? 'selected' : '' ?>>Pharmacist</option>
                        <option value="cashier" <?= $employee['role'] === 'cashier' ? 'selected' : '' ?>>Cashier</option>
                    </select>
                </div>
                
                <div>
                    <label class="form-label">Branch <span class="text-red-500">*</span></label>
                    <select name="branch_id" class="form-control" required>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= $branch['id'] ?>" <?= $branch['id'] == $employee['branch_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($branch['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="active" <?= ($employee['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($employee['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            
            <div class="flex gap-3 mt-6 pt-4 border-t">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Employee</button>
                <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>
    </div>

    <footer class="mt-6 pt-4 border-t border-[#E2E8F0] text-center text-sm text-[#94A3B8]">
        <p>Braick Dispensary Management System v2.0 &copy; <?= date('Y') ?> All rights reserved</p>
    </footer>
</main>

<script>
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    sidebarToggle?.addEventListener('click', () => { sidebar.classList.toggle('open'); });
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });
    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentDateTime').textContent = 
            now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) + 
            ' • ' + 
            now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);
</script>

</body>
</html>