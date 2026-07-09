<?php
// ================================================================
// FILE: frontend/pages/admin/add_department.php
// SUPER ADMIN - ADD DEPARTMENT
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
// VARIABLES FOR SHARED SIDEBAR
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';

// Get statistics for sidebar badges
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'");
$pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
$pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $head_of_department = trim($_POST['head_of_department'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    if (empty($name)) {
        $message = "Department name is required!";
        $message_type = 'error';
    } else {
        // Check if department already exists
        $stmt = $db->prepare("SELECT id FROM departments WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            $message = "Department already exists!";
            $message_type = 'error';
        } else {
            $stmt = $db->prepare("INSERT INTO departments (name, description, head_of_department, status, created_at) VALUES (?, ?, ?, ?, NOW())");
            if ($stmt->execute([$name, $description, $head_of_department, $status])) {
                $message = "Department added successfully!";
                $message_type = 'success';
                // Clear form
                $name = $description = $head_of_department = '';
            } else {
                $message = "Failed to add department!";
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
    <title>Add Department - Braick Dispensary</title>
    
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
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px; margin-top: 68px;
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
            background: #0B3D8A;
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
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SHARED SIDEBAR -->
<!-- ================================================================ -->
<?php 
// Variables for shared sidebar
$total_employees = $total_employees ?? 0;
$total_doctors = $total_doctors ?? 0;
$total_branches = $total_branches ?? 0;
$pending_lab_tests = $pending_lab_tests ?? 0;
$pending_prescriptions = $pending_prescriptions ?? 0;
$selected_branch_id = $selected_branch_id ?? 'all';

include_once '../../components/admin_sidebar.php'; 
?>

<!-- ================================================================ -->
<!-- TOP NAV -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
    </div>
    <div class="flex items-center gap-3">
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
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-plus-circle mr-2" style="color: var(--blue-600);"></i> Add New Department
            </h1>
            <p class="page-subtitle">Create a new department</p>
        </div>
        <div>
            <a href="departments.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Departments
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label">Department Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Medical Department" required>
                </div>
                <div>
                    <label class="form-label">Head of Department</label>
                    <input type="text" name="head_of_department" class="form-control" placeholder="e.g. Dr. John Doe">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Enter department description..."></textarea>
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
                    <i class="fas fa-save"></i> Add Department
                </button>
                <a href="departments.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>
</main>

<script>
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