<?php
// ================================================================
// FILE: frontend/pages/admin/edit_department.php
// SUPER ADMIN - EDIT DEPARTMENT
// BRAICK DISPENSARY
// WITH SHARED HEADER & SIDEBAR
// ================================================================

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

$dept_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($dept_id <= 0) {
    header('Location: departments.php');
    exit;
}

// Get department data
$stmt = $db->prepare("SELECT * FROM departments WHERE id = ?");
$stmt->execute([$dept_id]);
$department = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$department) {
    header('Location: departments.php');
    exit;
}

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
// GET BRANCHES FOR SELECTOR
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';
$form_data = [
    'name' => $department['name'],
    'description' => $department['description'] ?? '',
    'head_of_department' => $department['head_of_department'] ?? '',
    'status' => $department['status'] ?? 'active'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data['name'] = trim($_POST['name'] ?? '');
    $form_data['description'] = trim($_POST['description'] ?? '');
    $form_data['head_of_department'] = trim($_POST['head_of_department'] ?? '');
    $form_data['status'] = $_POST['status'] ?? 'active';
    
    if (empty($form_data['name'])) {
        $message = "Department name is required!";
        $message_type = 'error';
    } else {
        // Check if another department has the same name
        $stmt = $db->prepare("SELECT id FROM departments WHERE name = ? AND id != ?");
        $stmt->execute([$form_data['name'], $dept_id]);
        if ($stmt->fetch()) {
            $message = "Another department with this name already exists!";
            $message_type = 'error';
        } else {
            $stmt = $db->prepare("UPDATE departments SET name = ?, description = ?, head_of_department = ?, status = ? WHERE id = ?");
            if ($stmt->execute([$form_data['name'], $form_data['description'], $form_data['head_of_department'], $form_data['status'], $dept_id])) {
                $message = "Department updated successfully!";
                $message_type = 'success';
                // Refresh data
                $stmt = $db->prepare("SELECT * FROM departments WHERE id = ?");
                $stmt->execute([$dept_id]);
                $department = $stmt->fetch(PDO::FETCH_ASSOC);
                $form_data = [
                    'name' => $department['name'],
                    'description' => $department['description'] ?? '',
                    'head_of_department' => $department['head_of_department'] ?? '',
                    'status' => $department['status'] ?? 'active'
                ];
                echo '<script>setTimeout(function(){ window.location.href = "departments.php?branch=' . $selected_branch_id . '&updated=1"; }, 1500);</script>';
            } else {
                $message = "Failed to update department!";
                $message_type = 'error';
            }
        }
    }
}

// ================================================================
// FIX: Get created date safely
// ================================================================
$created_date = 'N/A';
if (isset($department['created_at']) && !empty($department['created_at'])) {
    $created_date = date('F d, Y', strtotime($department['created_at']));
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
$selected_branch_id = $selected_branch_id ?? 'all';
$total_employees = $total_employees ?? 0;
$total_doctors = $total_doctors ?? 0;
$total_branches = $total_branches ?? 0;
$pending_lab_tests = $pending_lab_tests ?? 0;
$pending_prescriptions = $pending_prescriptions ?? 0;
include_once '../../components/admin_sidebar.php';
?>

<style>
    /* ================================================================
       ADDITIONAL FORM STYLES - BEAUTIFUL LIKE DASHBOARD
       ================================================================ */
    
    /* Form Card */
    .form-card {
        background: var(--bg-card);
        border-radius: 20px;
        padding: 28px 32px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    
    .form-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 8px 30px rgba(11, 94, 215, 0.08);
    }
    
    /* Form Header */
    .form-header {
        display: flex;
        align-items: center;
        gap: 16px;
        padding-bottom: 20px;
        margin-bottom: 24px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .form-header-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        flex-shrink: 0;
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .form-header h3 {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }
    
    .form-header p {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin: 0;
    }
    
    /* Form Labels */
    .form-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 6px;
        display: block;
    }
    
    .form-label i {
        width: 20px;
        text-align: center;
        font-size: 0.85rem;
    }
    
    .form-label .required {
        color: #EF4444;
        margin-left: 2px;
    }
    
    /* Form Controls */
    .form-control {
        width: 100%;
        padding: 10px 16px;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--bg-card);
        color: var(--text-primary);
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }
    
    .form-control:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
    }
    
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.5;
    }
    
    .form-control:disabled {
        background: var(--bg-body);
        color: var(--text-secondary);
        cursor: not-allowed;
    }
    
    /* Form Row with Icon */
    .form-row-icon {
        position: relative;
    }
    
    .form-row-icon .form-control {
        padding-left: 44px;
    }
    
    .form-row-icon .input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
        font-size: 1rem;
        pointer-events: none;
        transition: color 0.3s ease;
    }
    
    .form-row-icon .form-control:focus + .input-icon,
    .form-row-icon .form-control:focus ~ .input-icon {
        color: #0B5ED7;
    }
    
    /* Textarea with icon */
    .form-textarea-icon {
        position: relative;
    }
    
    .form-textarea-icon .form-control {
        padding-left: 44px;
        padding-top: 12px;
        min-height: 80px;
        resize: vertical;
    }
    
    .form-textarea-icon .input-icon {
        position: absolute;
        left: 14px;
        top: 14px;
        color: var(--text-secondary);
        font-size: 1rem;
        pointer-events: none;
        transition: color 0.3s ease;
    }
    
    .form-textarea-icon .form-control:focus + .input-icon,
    .form-textarea-icon .form-control:focus ~ .input-icon {
        color: #0B5ED7;
    }
    
    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 10px 24px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        min-height: 44px;
        min-width: 120px;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        color: white;
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #0A4CA8, #1557B0);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.4);
    }
    
    .btn-primary:active {
        transform: translateY(0px);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-primary);
        border: 2px solid var(--border-color);
    }
    
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: translateY(-2px);
    }
    
    .btn-sm {
        padding: 6px 16px;
        font-size: 0.8rem;
        min-height: 36px;
        min-width: 90px;
    }
    
    /* Button Group */
    .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 24px;
        margin-top: 24px;
        border-top: 2px solid var(--border-color);
    }
    
    /* Responsive */
    @media (max-width: 640px) {
        .form-card {
            padding: 18px 16px;
        }
        .form-header {
            flex-direction: column;
            text-align: center;
        }
        .form-header-icon {
            width: 48px;
            height: 48px;
            font-size: 1.2rem;
        }
        .btn {
            padding: 8px 16px;
            font-size: 0.8rem;
            min-height: 38px;
            min-width: 100%;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

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
            <input type="text" id="searchInput" placeholder="Search departments...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches_list as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <!-- Dark Mode Toggle -->
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
                <i class="fas fa-edit mr-2" style="color: var(--blue-600);"></i> Edit Department
            </h1>
            <p class="page-subtitle">
                Update department information
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-building mr-1"></i> <?= htmlspecialchars($department['name']) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="departments.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
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
    <!-- FORM -->
    <!-- ================================================================ -->
    <div class="form-card">
        <!-- Form Header -->
        <div class="form-header">
            <div class="form-header-icon">
                <i class="fas fa-building"></i>
            </div>
            <div>
                <h3>Edit Department Information</h3>
                <p>Update the details of this department</p>
            </div>
        </div>
        
        <form method="POST" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- Department Name -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-tag text-blue-600"></i> Department Name
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="name" class="form-control" 
                               placeholder="e.g. Medical Department" 
                               value="<?= htmlspecialchars($form_data['name']) ?>" required>
                        <span class="input-icon"><i class="fas fa-building"></i></span>
                    </div>
                </div>
                
                <!-- Head of Department -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-user-tie text-blue-600"></i> Head of Department
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="head_of_department" class="form-control" 
                               placeholder="e.g. Dr. John Doe"
                               value="<?= htmlspecialchars($form_data['head_of_department']) ?>">
                        <span class="input-icon"><i class="fas fa-user-tie"></i></span>
                    </div>
                </div>
                
                <!-- Description -->
                <div class="md:col-span-2">
                    <label class="form-label">
                        <i class="fas fa-align-left text-blue-600"></i> Description
                    </label>
                    <div class="form-textarea-icon">
                        <textarea name="description" class="form-control" rows="3" 
                                  placeholder="Enter department description..."><?= htmlspecialchars($form_data['description']) ?></textarea>
                        <span class="input-icon"><i class="fas fa-align-left"></i></span>
                    </div>
                </div>
                
                <!-- Status -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-circle text-blue-600"></i> Status
                    </label>
                    <div class="form-row-icon">
                        <select name="status" class="form-control">
                            <option value="active" <?= $form_data['status'] === 'active' ? 'selected' : '' ?>>
                                ✅ Active
                            </option>
                            <option value="inactive" <?= $form_data['status'] === 'inactive' ? 'selected' : '' ?>>
                                ⛔ Inactive
                            </option>
                        </select>
                        <span class="input-icon"><i class="fas fa-toggle-on"></i></span>
                    </div>
                </div>
                
                <!-- Created Date -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-calendar text-gray-500"></i> Created Date
                    </label>
                    <div class="form-row-icon">
                        <input type="text" class="form-control" 
                               value="<?= $created_date ?>" disabled>
                        <span class="input-icon"><i class="fas fa-calendar-day"></i></span>
                    </div>
                </div>
                
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Department
                </button>
                <a href="departments.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Edit Department
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
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
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

    // ================================================================
    // SEARCH
    // ================================================================
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

    console.log('%c🏢 Braick - Edit Department', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Department: <?= htmlspecialchars($department['name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📅 Created: <?= $created_date ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🔗 Shared Header & Sidebar: ACTIVE', 'font-size:13px; color:#64748B;');
    console.log('%c🌙 Dark Mode: ' + (localStorage.getItem('darkMode') === 'true' ? 'ON' : 'OFF'), 'font-size:13px; color:#64748B;');
</script>

</body>
</html>