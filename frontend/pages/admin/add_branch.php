<?php
// ================================================================
// FILE: frontend/pages/admin/add_branch.php
// SUPER ADMIN - ADD BRANCH
// BRAICK DISPENSARY
// USING SHARED SIDEBAR
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
// GET STATISTICS FOR SIDEBAR BADGES
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
$stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'");
$pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$pending_prescriptions = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
$pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    if (empty($name)) {
        $message = "Branch name is required!";
        $message_type = 'error';
    } else {
        // Check if branch already exists
        $stmt = $db->prepare("SELECT id FROM branches WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            $message = "Branch already exists!";
            $message_type = 'error';
        } else {
            $stmt = $db->prepare("INSERT INTO branches (name, location, phone, email, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            
            if ($stmt->execute([$name, $location, $phone, $email, $status])) {
                $message = "Branch added successfully!";
                $message_type = 'success';
                // Clear form
                $name = $location = $phone = $email = '';
            } else {
                $message = "Failed to add branch!";
                $message_type = 'error';
            }
        }
    }
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
    <title>Add Branch - Braick Dispensary</title>
    
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
        
        .top-nav .datetime {
            font-size: 0.78rem; color: var(--gray-500); font-weight: 500;
        }
        
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
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px; margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           CARDS
           ================================================================ */
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
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 20px; border-radius: 10px;
            font-weight: 600; font-size: 0.85rem;
            transition: all 0.3s; cursor: pointer;
            border: none; text-decoration: none;
        }
        
        .btn-blue {
            background: var(--blue-600); color: white;
        }
        .btn-blue:hover {
            background: var(--blue-700);
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background: transparent; color: var(--gray-600);
            border: 2px solid var(--gray-200);
        }
        .btn-outline:hover {
            background: #E8F0FE;
            border-color: var(--blue-600);
            color: var(--blue-600);
        }
        
        .btn-sm { padding: 4px 12px; font-size: 0.75rem; border-radius: 6px; }
        
        /* ================================================================
           FORMS
           ================================================================ */
        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 4px;
            display: block;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s;
            outline: none;
            background: white;
            color: var(--gray-700);
        }
        
        .form-control:focus {
            border-color: var(--blue-600);
            box-shadow: 0 0 0 3px #D2E3FC;
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
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
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SHARED SIDEBAR - SAME FOR ALL PAGES -->
<!-- ================================================================ -->
<?php
// Variables needed by sidebar
$selected_branch_id = $selected_branch_id ?? 'all';
$total_employees = $total_employees ?? 0;
$total_doctors = $total_doctors ?? 0;
$total_branches = $total_branches ?? 0;
$pending_lab_tests = $pending_lab_tests ?? 0;
$pending_prescriptions = $pending_prescriptions ?? 0;

include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <input type="text" placeholder="Search...">
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
                <i class="fas fa-plus-circle mr-2" style="color: var(--blue-600);"></i> Add New Branch
            </h1>
            <p class="page-subtitle">Create a new dispensary branch</p>
        </div>
        <div>
            <a href="branches.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Branches
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

    <!-- Form -->
    <div class="card">
        <form method="POST" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label">Branch Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Braick Dispensary - Dodoma" required>
                </div>
                <div>
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" placeholder="e.g. Chang'ombe, Dodoma">
                </div>
                <div>
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" placeholder="e.g. +255 759 154 160">
                </div>
                <div>
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. dodoma@dispensary.com">
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            
            <div class="flex gap-3 mt-6 pt-4 border-t">
                <button type="submit" class="btn btn-blue">
                    <i class="fas fa-save"></i> Add Branch
                </button>
                <a href="branches.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');

    // Sidebar Toggle
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

    // Date & Time
    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentDateTime').textContent = 
            now.toLocaleDateString('en-US', { 
                weekday: 'short', 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
            }) + 
            ' • ' + 
            now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit', 
                hour12: true 
            });
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c🏢 Braick - Add Branch', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Using Shared Sidebar', 'font-size:13px; color:#059669;');
</script>

</body>
</html>