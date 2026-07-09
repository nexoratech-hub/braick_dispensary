<?php
// ================================================================
// FILE: frontend/pages/admin/view_branch.php
// SUPER ADMIN - VIEW BRANCH DETAILS
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

$branch_id = (int)($_GET['id'] ?? 0);

if ($branch_id <= 0) {
    header('Location: branches.php');
    exit;
}

// Get branch data
$stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->execute([$branch_id]);
$branch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$branch) {
    header('Location: branches.php');
    exit;
}

// Get branch statistics
$stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE branch_id = ?");
$stmt->execute([$branch_id]);
$employee_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
$stmt->execute([$branch_id]);
$patient_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ?");
$stmt->execute([$branch_id]);
$visit_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Branch - Braick Dispensary</title>
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { 
            --blue-600: #0B5ED7; 
            --blue-700: #0B4EA8; 
            --green-600: #059669; 
            --gray-50: #F8FAFC; 
            --gray-200: #E2E8F0; 
            --gray-400: #94A3B8; 
            --gray-500: #64748B; 
            --gray-700: #334155; 
            --gray-800: #1E293B; 
            --white: #FFFFFF; 
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', 'Segoe UI', sans-serif; 
            background: var(--gray-50); 
            color: var(--gray-800); 
        }
        .sidebar { 
            position: fixed; top: 0; left: 0; bottom: 0; 
            width: 270px; background: var(--blue-700); color: white; 
            z-index: 50; overflow-y: auto; 
            transition: transform 0.3s ease; 
        }
        .sidebar-brand { 
            padding: 22px 20px 16px; 
            border-bottom: 2px solid #0B3D8A; 
        }
        .sidebar-brand .logo { 
            width: 48px; height: 48px; 
            border-radius: 12px; 
            object-fit: cover; 
            background: white; 
            padding: 4px; 
        }
        .sidebar-brand .brand-text { 
            color: white; 
            font-weight: 700; 
            font-size: 1rem; 
        }
        .sidebar-brand .brand-sub { 
            color: #9EC5FE; 
            font-size: 0.7rem; 
        }
        .sidebar-nav { padding: 14px 10px; }
        .sidebar-nav .nav-label { 
            font-size: 0.55rem; 
            text-transform: uppercase; 
            letter-spacing: 0.1em; 
            color: #6EA8FE; 
            padding: 0 12px; 
            margin: 12px 0 6px; 
            font-weight: 700; 
        }
        .sidebar-link { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            padding: 9px 14px; 
            border-radius: 10px; 
            color: #D2E3FC; 
            text-decoration: none; 
            transition: all 0.3s; 
            font-size: 0.85rem; 
            font-weight: 500; 
            margin: 2px 0; 
        }
        .sidebar-link:hover { 
            background: #0B3D8A; 
            color: white; 
        }
        .sidebar-link.active { 
            background: var(--green-600); 
            color: white; 
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.4); 
        }
        .sidebar-link i { 
            width: 20px; 
            text-align: center; 
            font-size: 1rem; 
        }
        .top-nav { 
            position: fixed; 
            top: 0; 
            left: 270px; 
            right: 0; 
            height: 68px; 
            background: white; 
            z-index: 40; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 0 24px; 
            border-bottom: 2px solid #D2E3FC; 
        }
        .top-nav .avatar { 
            width: 38px; 
            height: 38px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 2px solid var(--gray-200); 
            cursor: pointer; 
            transition: all 0.3s; 
        }
        .top-nav .avatar:hover { 
            border-color: var(--blue-600); 
        }
        .top-nav .icon-btn { 
            width: 38px; 
            height: 38px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: var(--gray-500); 
            transition: all 0.3s; 
            background: transparent; 
            border: none; 
            cursor: pointer; 
            position: relative; 
        }
        .top-nav .icon-btn:hover { 
            background: #E8F0FE; 
            color: var(--blue-600); 
        }
        .notif-dot { 
            position: absolute; 
            top: 6px; 
            right: 6px; 
            width: 8px; 
            height: 8px; 
            background: var(--green-600); 
            border-radius: 50%; 
            border: 2px solid white; 
        }
        .top-nav .datetime { 
            font-size: 0.78rem; 
            color: var(--gray-500); 
            font-weight: 500; 
        }
        .main-content { 
            margin-left: 270px; 
            margin-top: 68px; 
            padding: 24px 28px; 
            min-height: calc(100vh - 68px); 
        }
        .card { 
            background: white; 
            border-radius: 16px; 
            padding: 24px; 
            border: 2px solid var(--gray-200); 
            transition: all 0.3s; 
        }
        .card:hover { 
            border-color: var(--blue-600); 
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08); 
        }
        .btn { 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            padding: 8px 20px; 
            border-radius: 10px; 
            font-weight: 600; 
            font-size: 0.85rem; 
            transition: all 0.3s; 
            cursor: pointer; 
            border: none; 
            text-decoration: none; 
        }
        .btn-blue { 
            background: var(--blue-600); 
            color: white; 
        }
        .btn-blue:hover { 
            background: var(--blue-700); 
            transform: translateY(-2px); 
        }
        .btn-outline { 
            background: transparent; 
            color: var(--gray-600); 
            border: 2px solid var(--gray-200); 
        }
        .btn-outline:hover { 
            background: #E8F0FE; 
            border-color: var(--blue-600); 
            color: var(--blue-600); 
        }
        .btn-sm { 
            padding: 4px 12px; 
            font-size: 0.75rem; 
            border-radius: 6px; 
        }
        .page-header { 
            border-bottom: 3px solid var(--blue-600); 
            padding-bottom: 12px; 
        }
        .page-header .page-title { 
            color: var(--blue-700); 
            font-size: 1.8rem; 
            font-weight: 700; 
        }
        .page-header .page-subtitle { 
            color: var(--gray-500); 
            font-size: 0.9rem; 
        }
        .stat-number { 
            font-size: 2rem; 
            font-weight: 700; 
            color: var(--blue-600); 
        }
        .stat-label { 
            font-size: 0.8rem; 
            color: var(--gray-500); 
        }
        .info-label { 
            font-size: 0.75rem; 
            color: var(--gray-400); 
            font-weight: 500; 
        }
        .info-value { 
            font-size: 0.95rem; 
            font-weight: 600; 
            color: var(--gray-800); 
        }
        .badge { 
            padding: 2px 10px; 
            border-radius: 20px; 
            font-size: 0.6rem; 
            font-weight: 600; 
            display: inline-flex; 
            align-items: center; 
            gap: 4px; 
            color: white; 
            border: none; 
        }
        .badge-success { 
            background: var(--green-600); 
            color: white; 
        }
        .badge-danger { 
            background: #EF4444; 
            color: white; 
        }
        .footer { 
            padding: 14px 0; 
            border-top: 2px solid var(--gray-200); 
            margin-top: 20px; 
            text-align: center; 
            font-size: 0.7rem; 
            color: var(--gray-500); 
        }
        .footer .footer-brand { 
            color: var(--blue-600); 
            font-weight: 600; 
        }
        @media (max-width: 1024px) { 
            .sidebar { transform: translateX(-100%); } 
            .sidebar.open { transform: translateX(0); } 
            .top-nav { left: 0; } 
            .main-content { margin-left: 0; padding: 16px; } 
        }
        @media (max-width: 640px) { 
            .main-content { padding: 10px; } 
        }
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
        <a href="branches.php" class="sidebar-link active"><i class="fas fa-store-alt"></i> Branches</a>
        <a href="departments.php" class="sidebar-link"><i class="fas fa-building"></i> Departments</a>
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
            <h1 class="page-title"><i class="fas fa-store-alt mr-2" style="color: var(--blue-600);"></i> Branch Details</h1>
            <p class="page-subtitle">View branch information and statistics</p>
        </div>
        <div class="flex gap-2">
            <a href="edit_branch.php?id=<?= $branch['id'] ?>" class="btn btn-blue btn-sm"><i class="fas fa-edit"></i> Edit</a>
            <a href="branches.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <!-- Branch Info -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        <div class="card">
            <h3 class="text-lg font-semibold text-[#0B5ED7] border-b pb-2 mb-4">
                <i class="fas fa-info-circle mr-2"></i> Branch Information
            </h3>
            <div class="space-y-3">
                <div>
                    <p class="info-label">Branch Name</p>
                    <p class="info-value"><?= htmlspecialchars($branch['name']) ?></p>
                </div>
                <div>
                    <p class="info-label">Location</p>
                    <p class="info-value"><?= htmlspecialchars($branch['location'] ?? 'Not specified') ?></p>
                </div>
                <div>
                    <p class="info-label">Phone</p>
                    <p class="info-value"><?= htmlspecialchars($branch['phone'] ?? 'Not specified') ?></p>
                </div>
                <div>
                    <p class="info-label">Email</p>
                    <p class="info-value"><?= htmlspecialchars($branch['email'] ?? 'Not specified') ?></p>
                </div>
                <div>
                    <p class="info-label">Status</p>
                    <p class="info-value">
                        <span class="badge <?= $branch['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                            <i class="fas fa-circle text-[5px]"></i> <?= ucfirst($branch['status']) ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="info-label">Created</p>
                    <p class="info-value"><?= date('F d, Y h:i A', strtotime($branch['created_at'])) ?></p>
                </div>
            </div>
        </div>

        <!-- Branch Statistics -->
        <div class="card">
            <h3 class="text-lg font-semibold text-[#0B5ED7] border-b pb-2 mb-4">
                <i class="fas fa-chart-bar mr-2"></i> Branch Statistics
            </h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center p-4 bg-blue-50 rounded-xl">
                    <p class="stat-number"><?= $employee_count ?></p>
                    <p class="stat-label">Employees</p>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-xl">
                    <p class="stat-number"><?= $patient_count ?></p>
                    <p class="stat-label">Patients</p>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-xl">
                    <p class="stat-number"><?= $visit_count ?></p>
                    <p class="stat-label">Visits</p>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-xl">
                    <p class="stat-number"><?= date('d/m/Y', strtotime($branch['created_at'])) ?></p>
                    <p class="stat-label">Created Date</p>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <p><span class="footer-brand">Braick Dispensary</span> Management System &copy; <?= date('Y') ?> All rights reserved</p>
    </footer>
</main>

<script>
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
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
    function updateDateTime() {
        var now = new Date();
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