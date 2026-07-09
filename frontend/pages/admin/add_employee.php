<?php
// ================================================================
// FILE: frontend/pages/admin/add_employee.php
// SUPER ADMIN - ADD EMPLOYEE
// BRAICK DISPENSARY
// ================================================================

session_start();

// Check if logged in as super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

// Include database and helpers
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// BRANCH SELECTION
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
// GET BRANCHES, ROLES, DEPARTMENTS
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$roles = [];
try {
    $stmt = $db->query("SELECT id, name FROM roles ORDER BY name");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $roles = [];
}

$departments = [];
try {
    $stmt = $db->query("SELECT id, name FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $departments = [];
}

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? 'password123';
    $role = $_POST['role'] ?? '';
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    $selected_roles = $_POST['selected_roles'] ?? [];
    $selected_departments = $_POST['selected_departments'] ?? [];
    
    // Validation
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    if (empty($email)) {
        $errors[] = 'Email is required';
    }
    if (empty($role)) {
        $errors[] = 'Role is required';
    }
    if ($branch_id <= 0) {
        $errors[] = 'Branch is required';
    }
    
    // Check if username exists
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = 'Username already exists';
        }
    }
    
    // Check if email exists
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already exists';
        }
    }
    
    // Save employee
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("
            INSERT INTO users (username, password, full_name, email, phone, role, branch_id, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        
        if ($stmt->execute([$username, $hashed_password, $full_name, $email, $phone, $role, $branch_id])) {
            $user_id = $db->lastInsertId();
            
            // Assign roles if any
            if (!empty($selected_roles)) {
                foreach ($selected_roles as $role_id) {
                    try {
                        $stmt = $db->prepare("INSERT INTO employee_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)");
                        $stmt->execute([$user_id, $role_id, $_SESSION['user_id']]);
                    } catch (Exception $e) {}
                }
            }
            
            // Assign departments if any
            if (!empty($selected_departments)) {
                foreach ($selected_departments as $dept_id) {
                    try {
                        $stmt = $db->prepare("INSERT INTO employee_departments (user_id, department_id, assigned_by) VALUES (?, ?, ?)");
                        $stmt->execute([$user_id, $dept_id, $_SESSION['user_id']]);
                    } catch (Exception $e) {}
                }
            }
            
            // Log activity
            try {
                $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'employee_added', ?)");
                $stmt->execute([$_SESSION['user_id'], "Employee $full_name added"]);
            } catch (Exception $e) {}
            
            $message = "Employee added successfully!";
            $message_type = 'success';
            
            // Redirect to employees list
            header('Location: employees.php?branch=' . $branch_id . '&success=1');
            exit;
        } else {
            $errors[] = 'Failed to add employee. Please try again.';
        }
    }
    
    if (!empty($errors)) {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
$selected_branch_id = $selected_branch_id ?? 'all';
$total_employees = $total_employees ?? 0;
$total_doctors = $total_doctors ?? 0;
$total_branches = $total_branches ?? 0;
$pending_lab_tests = $pending_lab_tests ?? 0;
$pending_prescriptions = $pending_prescriptions ?? 0;
include_once '../../components/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee - Braick Dispensary</title>
    
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
            padding: 24px;
            border: 2px solid var(--gray-200);
            transition: all 0.3s;
        }
        
        .card:hover {
            border-color: var(--blue-600);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
        }
        
        .card-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 16px;
            flex-wrap: wrap; gap: 8px;
        }
        
        .card-title { font-size: 1.1rem; font-weight: 600; color: #1E293B; }
        .card-title i { color: var(--blue-600); }
        
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
            background: #0B3D8A;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-green {
            background: var(--green-600); color: white;
        }
        .btn-green:hover {
            background: #047857;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
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
           FORM
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
        
        .form-control.error { border-color: #EF4444; }
        
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
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SHARED SIDEBAR -->
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
                <i class="fas fa-user-plus mr-2" style="color: var(--blue-600);"></i> Add New Employee
            </h1>
            <p class="page-subtitle">Create a new employee account</p>
        </div>
        <div>
            <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Employees
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
                
                <!-- Personal Information -->
                <div class="md:col-span-2">
                    <h3 class="text-lg font-semibold text-[#0B5ED7] border-b pb-2 mb-4">
                        <i class="fas fa-user mr-2"></i> Personal Information
                    </h3>
                </div>
                
                <div>
                    <label class="form-label">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="full_name" class="form-control" placeholder="Enter full name" required>
                </div>
                
                <div>
                    <label class="form-label">Username <span class="text-red-500">*</span></label>
                    <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                </div>
                
                <div>
                    <label class="form-label">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="Enter email" required>
                </div>
                
                <div>
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="Enter phone number">
                </div>
                
                <div>
                    <label class="form-label">Password</label>
                    <input type="text" name="password" class="form-control" placeholder="Default: password123" value="password123">
                    <p class="text-xs text-gray-400 mt-1">Default password is: <strong>password123</strong></p>
                </div>
                
                <!-- Professional Information -->
                <div class="md:col-span-2 mt-4">
                    <h3 class="text-lg font-semibold text-[#0B5ED7] border-b pb-2 mb-4">
                        <i class="fas fa-briefcase mr-2"></i> Professional Information
                    </h3>
                </div>
                
                <div>
                    <label class="form-label">Primary Role <span class="text-red-500">*</span></label>
                    <select name="role" class="form-control" required>
                        <option value="">Select Role</option>
                        <option value="doctor">Doctor</option>
                        <option value="reception">Receptionist</option>
                        <option value="laboratory">Laboratory Technician</option>
                        <option value="pharmacy">Pharmacist</option>
                        <option value="cashier">Cashier</option>
                    </select>
                </div>
                
                <div>
                    <label class="form-label">Branch <span class="text-red-500">*</span></label>
                    <select name="branch_id" class="form-control" required>
                        <option value="">Select Branch</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= $branch['id'] ?>" <?= $branch['id'] == $selected_branch_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($branch['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Multiple Roles -->
                <?php if (!empty($roles)): ?>
                <div>
                    <label class="form-label">Additional Roles</label>
                    <select name="selected_roles[]" class="form-control" multiple style="height:100px;">
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Hold Ctrl/Cmd to select multiple</p>
                </div>
                <?php endif; ?>
                
                <!-- Departments -->
                <?php if (!empty($departments)): ?>
                <div>
                    <label class="form-label">Departments</label>
                    <select name="selected_departments[]" class="form-control" multiple style="height:100px;">
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Hold Ctrl/Cmd to select multiple</p>
                </div>
                <?php endif; ?>
                
            </div>
            
            <!-- Submit Buttons -->
            <div class="flex gap-3 mt-6 pt-4 border-t">
                <button type="submit" class="btn btn-blue">
                    <i class="fas fa-save"></i> Save Employee
                </button>
                <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline">
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
            Add Employee
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
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

    console.log('%c👤 Braick - Add Employee', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔗 Using Shared Sidebar', 'font-size:13px; color:#059669;');
</script>

</body>
</html>