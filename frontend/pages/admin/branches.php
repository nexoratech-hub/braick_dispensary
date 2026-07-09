<?php
// ================================================================
// FILE: frontend/pages/admin/branches.php
// SUPER ADMIN - BRANCH MANAGEMENT
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
// BRANCH SELECTION FOR SIDEBAR
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$total_employees = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_doctors = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_branches = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$pending_lab_tests = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'");
    $pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_lab_tests = 0;
}

$pending_prescriptions = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
    $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

// ================================================================
// HANDLE DELETE
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete') {
        $branch_id = (int)$_POST['branch_id'];
        
        // Check if branch has users
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE branch_id = ?");
        $stmt->execute([$branch_id]);
        $user_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        if ($user_count > 0) {
            $message = "Cannot delete branch! There are $user_count employees assigned to this branch.";
            $message_type = 'error';
        } else {
            // Check if branch has patients
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
            $stmt->execute([$branch_id]);
            $patient_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            if ($patient_count > 0) {
                $message = "Cannot delete branch! There are $patient_count patients assigned to this branch.";
                $message_type = 'error';
            } else {
                $stmt = $db->prepare("DELETE FROM branches WHERE id = ?");
                if ($stmt->execute([$branch_id])) {
                    $message = "Branch deleted successfully!";
                    $message_type = 'success';
                    // Refresh branch count
                    $stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
                    $total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                } else {
                    $message = "Failed to delete branch!";
                    $message_type = 'error';
                }
            }
        }
    }
}

// ================================================================
// GET ALL BRANCHES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT * FROM branches ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
// Variables needed by sidebar
$selected_branch_id = $selected_branch_id ?? 'all';
$total_employees = $total_employees ?? 0;
$total_doctors = $total_doctors ?? 0;
$total_branches = $total_branches ?? 0;
$pending_lab_tests = $pending_lab_tests ?? 0;
$pending_prescriptions = $pending_prescriptions ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branches - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --blue-600: #0B5ED7;
            --blue-700: #0B4EA8;
            --green-600: #059669;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
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
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); }
        ::-webkit-scrollbar-thumb { background: var(--blue-600); border-radius: 10px; }
        
        /* ================================================================
           TOP NAV - WHITE
           ================================================================ */
        .top-nav {
            position: fixed; top: 0; left: 270px; right: 0;
            height: 68px; background: white; z-index: 40;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; border-bottom: 2px solid #D2E3FC;
        }
        
        .top-nav .search-wrapper {
            display: flex; align-items: center;
            background: var(--gray-50); border-radius: 10px;
            border: 2px solid var(--gray-200);
            transition: all 0.3s;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--blue-600);
            box-shadow: 0 0 0 3px #D2E3FC;
        }
        
        .top-nav .search-wrapper input {
            border: none; background: transparent;
            padding: 8px 14px; width: 280px;
            font-size: 0.85rem; outline: none;
            color: var(--gray-700);
        }
        
        .top-nav .search-wrapper input::placeholder { color: var(--gray-400); }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--blue-600); color: white;
            border: none; padding: 8px 16px;
            border-radius: 0 10px 10px 0;
            cursor: pointer; font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .top-nav .search-wrapper .search-btn:hover { background: #0B3D8A; }
        
        .top-nav .branch-selector {
            border: 2px solid var(--gray-200);
            border-radius: 10px; padding: 6px 12px;
            background: white; font-size: 0.82rem;
            font-weight: 500; cursor: pointer; outline: none;
            min-width: 180px; color: var(--gray-700);
            transition: all 0.3s;
        }
        
        .top-nav .branch-selector:focus {
            border-color: var(--blue-600);
            box-shadow: 0 0 0 3px #D2E3FC;
        }
        
        .top-nav .datetime {
            font-size: 0.78rem; color: var(--gray-500); font-weight: 500;
        }
        
        .top-nav .avatar {
            width: 38px; height: 38px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--gray-200);
            cursor: pointer; transition: all 0.3s;
        }
        
        .top-nav .avatar:hover { border-color: var(--blue-600); }
        
        .top-nav .icon-btn {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--gray-500); transition: all 0.3s;
            background: transparent; border: none; cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover { background: #E8F0FE; color: var(--blue-600); }
        
        .notif-dot {
            position: absolute; top: 6px; right: 6px;
            width: 8px; height: 8px; background: var(--green-600);
            border-radius: 50%; border: 2px solid white;
        }
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px; margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           CARDS - WHITE BACKGROUND
           ================================================================ */
        .card {
            background: white;
            border-radius: 16px;
            padding: 18px 20px;
            border: 2px solid var(--gray-200);
            transition: all 0.3s;
        }
        
        .card:hover {
            border-color: var(--blue-600);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
        }
        
        .card-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 12px;
            flex-wrap: wrap; gap: 8px;
        }
        
        .card-title {
            font-size: 0.9rem; font-weight: 600; color: var(--gray-800);
        }
        
        .card-title .title-blue { color: var(--blue-600); }
        .card-title .title-green { color: var(--green-600); }
        
        /* ================================================================
           TABLE - BLUE HEADER
           ================================================================ */
        .table-wrap { overflow-x: auto; }
        
        .data-table {
            width: 100%; border-collapse: collapse;
            font-size: 0.8rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 8px 10px;
            font-weight: 700;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--blue-600);
            border-bottom: 3px solid #0B3D8A;
            white-space: nowrap;
        }
        
        .data-table tbody tr:nth-child(even) {
            background: #E8F0FE;
        }
        
        .data-table tbody tr:nth-child(odd) {
            background: white;
        }
        
        .data-table tbody tr:hover {
            background: #D1FAE5;
        }
        
        .data-table td {
            padding: 8px 10px;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-700);
            vertical-align: middle;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            padding: 2px 10px; border-radius: 20px;
            font-size: 0.6rem; font-weight: 600;
            display: inline-flex; align-items: center; gap: 4px;
            color: white;
            border: none;
        }
        
        .badge-success { background: var(--green-600); color: white; }
        .badge-danger { background: #EF4444; color: white; }
        .badge-info { background: var(--blue-600); color: white; }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 6px;
            font-weight: 600; font-size: 0.65rem;
            transition: all 0.2s; cursor: pointer;
            border: none; text-decoration: none;
            white-space: nowrap;
        }
        
        .btn-blue {
            background: var(--blue-600); color: white;
        }
        .btn-blue:hover {
            background: #0B3D8A;
            transform: translateY(-1px);
        }
        
        .btn-green {
            background: var(--green-600); color: white;
        }
        .btn-green:hover {
            background: #047857;
            transform: translateY(-1px);
        }
        
        .btn-outline {
            background: transparent; color: var(--gray-600);
            border: 1.5px solid var(--gray-200);
        }
        .btn-outline:hover {
            background: #E8F0FE;
            border-color: var(--blue-600);
            color: var(--blue-600);
        }
        
        .btn-danger {
            background: #EF4444; color: white;
        }
        .btn-danger:hover {
            background: #DC2626;
            transform: translateY(-1px);
        }
        
        .btn-sm { padding: 3px 8px; font-size: 0.6rem; border-radius: 4px; }
        
        .btn-view { 
            background: var(--blue-600); 
            color: white; 
            padding: 2px 7px; 
            font-size: 0.6rem; 
            border-radius: 4px;
        }
        .btn-view:hover { 
            background: #0B3D8A; 
            transform: scale(1.05);
        }
        
        .btn-edit { 
            background: var(--green-600); 
            color: white; 
            padding: 2px 7px; 
            font-size: 0.6rem; 
            border-radius: 4px;
        }
        .btn-edit:hover { 
            background: #047857; 
            transform: scale(1.05);
        }
        
        .btn-delete { 
            background: #EF4444; 
            color: white; 
            padding: 2px 7px; 
            font-size: 0.6rem; 
            border-radius: 4px;
        }
        .btn-delete:hover { 
            background: #DC2626; 
            transform: scale(1.05);
        }
        
        .action-buttons {
            display: flex;
            align-items: center;
            gap: 3px;
            flex-wrap: nowrap;
            justify-content: center;
        }
        
        .action-buttons .btn {
            padding: 2px 7px;
            font-size: 0.6rem;
            border-radius: 4px;
            font-weight: 600;
        }
        
        .action-buttons .btn i {
            font-size: 0.65rem;
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            border-bottom: 3px solid var(--blue-600);
            padding-bottom: 12px;
        }
        
        .page-header .page-title {
            color: #0B3D8A;
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .page-header .page-subtitle {
            color: var(--gray-500);
            font-size: 0.9rem;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed; bottom: 24px; right: 24px;
            padding: 12px 18px; border-radius: 12px;
            z-index: 999; max-width: 360px;
            transform: translateY(100px); opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; align-items: center; gap: 10px;
            color: white;
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--green-600); }
        .toast-custom.error { background: #EF4444; }
        .toast-custom.info { background: var(--blue-600); }
        
        /* ================================================================
           FOOTER
           ================================================================ */
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
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper input { width: 160px; }
        }
        
        @media (max-width: 640px) {
            .top-nav .search-wrapper input { width: 100px; }
            .top-nav .branch-selector { min-width: 120px; font-size: 0.7rem; }
            .top-nav .datetime { display: none; }
            .main-content { padding: 10px; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        
        .spinner {
            display: inline-block; width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SHARED SIDEBAR - SAME FOR ALL PAGES -->
<!-- ================================================================ -->
<?php include_once '../../components/admin_sidebar.php'; ?>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <input type="text" placeholder="Search branches...">
            <button class="search-btn"><i class="fas fa-search"></i></button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select class="branch-selector">
            <option>🌐 All Branches</option>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2238%22 height=%2238%22%3E%3Crect width=%2238%22 height=%2238%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2219%22 y=%2225%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-store-alt mr-2" style="color: var(--blue-600);"></i> Branch Management
            </h1>
            <p class="page-subtitle">Manage all dispensary branches</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="add_branch.php" class="btn btn-blue btn-sm">
                <i class="fas fa-plus"></i> Add Branch
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

    <!-- ================================================================ -->
    <!-- BRANCHES TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> All Branches
                <span class="text-sm font-normal text-gray-400">(<?= count($branches) ?> branches)</span>
            </h3>
        </div>
        
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Branch Name</th>
                        <th>Location</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th style="border-radius: 0 8px 0 0; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($branches) > 0): ?>
                        <?php foreach ($branches as $index => $branch): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td class="font-medium"><?= htmlspecialchars($branch['name']) ?></td>
                                <td><?= htmlspecialchars($branch['location'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($branch['phone'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($branch['email'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge <?= $branch['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                        <i class="fas fa-circle text-[5px]"></i>
                                        <?= ucfirst($branch['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- VIEW Button -->
                                        <a href="view_branch.php?id=<?= $branch['id'] ?>" 
                                           class="btn btn-view" 
                                           title="View Branch">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <!-- EDIT Button -->
                                        <a href="edit_branch.php?id=<?= $branch['id'] ?>" 
                                           class="btn btn-edit" 
                                           title="Edit Branch">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <!-- DELETE Button -->
                                        <button onclick="deleteBranch(<?= $branch['id'] ?>)" 
                                                class="btn btn-delete" 
                                                title="Delete Branch">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-400">
                                <i class="fas fa-store-alt text-3xl block mb-2"></i>
                                No branches found. Click "Add Branch" to get started.
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
            Branch Management
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var toast = document.getElementById('toast');

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
    // DELETE BRANCH
    // ================================================================
    function deleteBranch(branchId) {
        if (confirm('⚠️ Are you sure you want to DELETE this branch?\n\nThis action CANNOT be undone!\n\nEmployees and patients assigned to this branch will be affected.')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="branch_id" value="${branchId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

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
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
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
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c🏢 Braick - Branch Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total Branches: <?= count($branches) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔗 Using Shared Sidebar', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>