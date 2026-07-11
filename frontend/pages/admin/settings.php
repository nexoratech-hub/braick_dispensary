<?php
// ================================================================
// FILE: frontend/pages/admin/settings.php
// SUPER ADMIN - SYSTEM SETTINGS
// BRAICK DISPENSARY
// WITH SHARED HEADER & SIDEBAR
// UPDATED TO MATCH YOUR DATABASE STRUCTURE
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
// BRANCH SELECTION
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$active_tab = $_GET['tab'] ?? 'services';

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
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branches[] = $row;
}

// ================================================================
// GET SERVICE CATEGORIES
// ================================================================
$categories = [];
$stmt = $db->query("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY display_order, category_name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $categories[$row['id']] = $row;
}

// ================================================================
// HANDLE SERVICE CATEGORY FORM
// ================================================================
$message = '';
$message_type = '';

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ================================================================
    // ADD/EDIT CATEGORY
    // ================================================================
    if ($action === 'add_category' || $action === 'edit_category') {
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $category_name = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-file-medical');
        $color = trim($_POST['color'] ?? '#0B5ED7');
        $display_order = (int)($_POST['display_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($category_name)) {
            $message = "Category name is required!";
            $message_type = 'error';
        } else {
            if ($action === 'add_category') {
                // Check if category exists
                $stmt = $db->prepare("SELECT id FROM service_categories WHERE category_name = ?");
                $stmt->execute([$category_name]);
                if ($stmt->fetch()) {
                    $message = "Category already exists!";
                    $message_type = 'error';
                } else {
                    $stmt = $db->prepare("INSERT INTO service_categories (category_name, description, icon, color, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$category_name, $description, $icon, $color, $display_order, $is_active])) {
                        $message = "Category added successfully!";
                        $message_type = 'success';
                        // Refresh categories
                        $categories = [];
                        $stmt = $db->query("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY display_order, category_name");
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $categories[$row['id']] = $row;
                        }
                    } else {
                        $message = "Failed to add category!";
                        $message_type = 'error';
                    }
                }
            } elseif ($action === 'edit_category' && $category_id > 0) {
                // Check if another category has same name
                $stmt = $db->prepare("SELECT id FROM service_categories WHERE category_name = ? AND id != ?");
                $stmt->execute([$category_name, $category_id]);
                if ($stmt->fetch()) {
                    $message = "Another category with this name already exists!";
                    $message_type = 'error';
                } else {
                    $stmt = $db->prepare("UPDATE service_categories SET category_name = ?, description = ?, icon = ?, color = ?, display_order = ?, is_active = ? WHERE id = ?");
                    if ($stmt->execute([$category_name, $description, $icon, $color, $display_order, $is_active, $category_id])) {
                        $message = "Category updated successfully!";
                        $message_type = 'success';
                        // Refresh categories
                        $categories = [];
                        $stmt = $db->query("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY display_order, category_name");
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $categories[$row['id']] = $row;
                        }
                    } else {
                        $message = "Failed to update category!";
                        $message_type = 'error';
                    }
                }
            }
        }
    }
    
    // ================================================================
    // DELETE CATEGORY
    // ================================================================
    if ($action === 'delete_category') {
        $category_id = (int)($_POST['category_id'] ?? 0);
        if ($category_id > 0) {
            // Check if category has services
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM services WHERE category_id = ?");
            $stmt->execute([$category_id]);
            $used = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            if ($used > 0) {
                $message = "Cannot delete! This category has $used service(s) assigned.";
                $message_type = 'error';
            } else {
                // Soft delete - set is_active to 0
                $stmt = $db->prepare("UPDATE service_categories SET is_active = 0 WHERE id = ?");
                if ($stmt->execute([$category_id])) {
                    $message = "Category deleted successfully!";
                    $message_type = 'success';
                    // Refresh categories
                    $categories = [];
                    $stmt = $db->query("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY display_order, category_name");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $categories[$row['id']] = $row;
                    }
                } else {
                    $message = "Failed to delete category!";
                    $message_type = 'error';
                }
            }
        }
    }
    
    // ================================================================
    // ADD/EDIT SERVICE
    // ================================================================
    if ($action === 'add_service' || $action === 'edit_service') {
        $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $service_name = trim($_POST['service_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = trim($_POST['price'] ?? '0');
        $unit = trim($_POST['unit'] ?? 'each');
        $display_order = (int)($_POST['display_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($service_name)) {
            $message = "Service name is required!";
            $message_type = 'error';
        } elseif ($category_id <= 0) {
            $message = "Please select a category!";
            $message_type = 'error';
        } elseif (empty($price) || $price <= 0) {
            $message = "Valid price is required!";
            $message_type = 'error';
        } else {
            if ($action === 'add_service') {
                $stmt = $db->prepare("INSERT INTO services (category_id, service_name, description, price, unit, display_order, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$category_id, $service_name, $description, $price, $unit, $display_order, $is_active, $_SESSION['user_id']])) {
                    $message = "Service added successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Failed to add service!";
                    $message_type = 'error';
                }
            } elseif ($action === 'edit_service' && $service_id > 0) {
                $stmt = $db->prepare("UPDATE services SET category_id = ?, service_name = ?, description = ?, price = ?, unit = ?, display_order = ?, is_active = ? WHERE id = ?");
                if ($stmt->execute([$category_id, $service_name, $description, $price, $unit, $display_order, $is_active, $service_id])) {
                    $message = "Service updated successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Failed to update service!";
                    $message_type = 'error';
                }
            }
        }
    }
    
    // ================================================================
    // DELETE SERVICE
    // ================================================================
    if ($action === 'delete_service') {
        $service_id = (int)($_POST['service_id'] ?? 0);
        if ($service_id > 0) {
            // Check if service is being used in visits
            try {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM visit_services WHERE service_id = ?");
                $stmt->execute([$service_id]);
                $used = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            } catch (Exception $e) {
                $used = 0;
            }
            
            if ($used > 0) {
                $message = "Cannot delete! This service is used in $used visit(s).";
                $message_type = 'error';
            } else {
                // Soft delete - set is_active to 0
                $stmt = $db->prepare("UPDATE services SET is_active = 0 WHERE id = ?");
                if ($stmt->execute([$service_id])) {
                    $message = "Service deleted successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Failed to delete service!";
                    $message_type = 'error';
                }
            }
        }
    }
}

// ================================================================
// GET ALL SERVICES WITH CATEGORY NAMES
// ================================================================
$services = [];
$query = "SELECT s.*, c.category_name, c.icon as category_icon, c.color as category_color, u.full_name as created_by_name 
          FROM services s
          LEFT JOIN service_categories c ON s.category_id = c.id
          LEFT JOIN users u ON s.created_by = u.id
          WHERE 1=1";

// Filter by active status if requested
if (isset($_GET['show']) && $_GET['show'] === 'active') {
    $query .= " AND s.is_active = 1";
} elseif (isset($_GET['show']) && $_GET['show'] === 'inactive') {
    $query .= " AND s.is_active = 0";
}

$query .= " ORDER BY c.display_order, c.category_name, s.display_order, s.service_name";
$services = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET CATEGORY STATS
// ================================================================
$category_stats = [];
foreach ($categories as $cat_id => $cat) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM services WHERE category_id = ? AND is_active = 1");
    $stmt->execute([$cat_id]);
    $category_stats[$cat_id] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
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
       SETTINGS PAGE STYLES
       ================================================================ */
    
    .settings-sidebar {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 16px;
        border: 2px solid var(--border-color);
        position: sticky;
        top: 80px;
    }
    
    .settings-sidebar .nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-radius: 10px;
        color: var(--text-secondary);
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 0.85rem;
        font-weight: 500;
        border: 2px solid transparent;
        margin-bottom: 4px;
    }
    
    .settings-sidebar .nav-link:hover {
        background: var(--bg-body);
        color: #0B5ED7;
        border-color: #E8F0FE;
    }
    
    .settings-sidebar .nav-link.active {
        background: #E8F0FE;
        color: #0B5ED7;
        border-color: #0B5ED7;
        font-weight: 600;
    }
    
    [data-theme="dark"] .settings-sidebar .nav-link.active {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: #6EA8FE;
    }
    
    .settings-sidebar .nav-link i {
        width: 20px;
        text-align: center;
        font-size: 0.95rem;
    }
    
    .settings-sidebar .nav-link .badge {
        margin-left: auto;
        background: #0B5ED7;
        color: white;
        font-size: 0.6rem;
        padding: 2px 8px;
        border-radius: 12px;
    }
    
    .settings-sidebar .nav-link.active .badge {
        background: #0B5ED7;
        color: white;
    }
    
    .settings-content {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px;
        border: 2px solid var(--border-color);
    }
    
    .settings-content .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding-bottom: 16px;
        margin-bottom: 20px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .settings-content .section-header h2 {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .settings-content .section-header h2 i {
        color: #0B5ED7;
    }
    
    .service-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 18px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 12px;
    }
    
    .service-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.06);
    }
    
    .service-card .service-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .service-card .service-name {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .service-card .service-category {
        font-size: 0.7rem;
        font-weight: 500;
        padding: 2px 12px;
        border-radius: 12px;
        background: #E8F0FE;
        color: #0B5ED7;
        display: inline-block;
    }
    
    [data-theme="dark"] .service-card .service-category {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    .service-card .service-price {
        font-size: 1.1rem;
        font-weight: 700;
        color: #0B5ED7;
    }
    
    .service-card .service-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 6px;
        font-size: 0.75rem;
        color: var(--text-secondary);
    }
    
    .service-card .service-meta span {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .service-card .service-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    
    .btn-sm {
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    
    .btn-edit {
        background: #059669;
        color: white;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .btn-edit:hover {
        background: #047857;
        transform: scale(1.05);
    }
    
    .btn-delete {
        background: #EF4444;
        color: white;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .btn-delete:hover {
        background: #DC2626;
        transform: scale(1.05);
    }
    
    .badge-status {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .badge-status.active { background: #E6F7EE; color: #059669; }
    .badge-status.inactive { background: #FEE2E2; color: #EF4444; }
    
    [data-theme="dark"] .badge-status.active { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .badge-status.inactive { background: #3A1A1A; color: #F87171; }
    
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 999;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
    }
    
    .modal-overlay.show {
        display: flex;
    }
    
    .modal {
        background: var(--bg-card);
        border-radius: 20px;
        padding: 32px;
        max-width: 560px;
        width: 95%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 25px 60px rgba(0,0,0,0.3);
        animation: modalIn 0.3s ease;
    }
    
    @keyframes modalIn {
        from {
            transform: scale(0.9) translateY(20px);
            opacity: 0;
        }
        to {
            transform: scale(1) translateY(0);
            opacity: 1;
        }
    }
    
    .modal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 16px;
        margin-bottom: 20px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .modal .modal-header h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .modal .modal-header h3 i {
        color: #0B5ED7;
    }
    
    .modal .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--text-secondary);
        cursor: pointer;
        transition: color 0.3s ease;
    }
    
    .modal .modal-close:hover {
        color: #EF4444;
    }
    
    .form-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
        display: block;
    }
    
    .form-label .required {
        color: #EF4444;
        margin-left: 2px;
    }
    
    .form-control {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--bg-card);
        color: var(--text-primary);
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }
    
    .form-control:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
    }
    
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.5;
    }
    
    .form-row {
        margin-bottom: 16px;
    }
    
    .form-row:last-child {
        margin-bottom: 0;
    }
    
    .form-actions {
        display: flex;
        gap: 12px;
        padding-top: 16px;
        margin-top: 16px;
        border-top: 2px solid var(--border-color);
        flex-wrap: wrap;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-primary {
        background: #0B5ED7;
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    .btn-primary:hover {
        background: #0A4CA8;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(11, 94, 215, 0.4);
    }
    
    .btn-secondary {
        background: var(--bg-body);
        color: var(--text-primary);
        border: 2px solid var(--border-color);
    }
    .btn-secondary:hover {
        background: var(--bg-card);
        border-color: #0B5ED7;
        color: #0B5ED7;
    }
    
    .btn-success {
        background: #059669;
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    .btn-success:hover {
        background: #047857;
        transform: translateY(-2px);
    }
    
    .btn-danger {
        background: #EF4444;
        color: white;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }
    .btn-danger:hover {
        background: #DC2626;
        transform: translateY(-2px);
    }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 3rem;
        color: var(--border-color);
        margin-bottom: 12px;
    }
    
    .category-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    [data-theme="dark"] .category-chip {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    @media (max-width: 768px) {
        .settings-sidebar {
            position: relative;
            top: 0;
            margin-bottom: 16px;
        }
        .settings-content {
            padding: 16px;
        }
        .service-card .service-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .modal {
            padding: 20px;
            margin: 10px;
            width: 100%;
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
            <input type="text" id="searchInput" placeholder="Search services...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
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
                <i class="fas fa-cog mr-2" style="color: var(--blue-600);"></i> System Settings
            </h1>
            <p class="page-subtitle">
                Manage services, categories, and system configurations
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= $selected_branch_id === 'all' ? 'All Branches' : 'Branch' ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-list mr-1"></i> <?= count($services) ?> services
                </span>
                <span class="ml-2 inline-flex bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs border border-purple-200">
                    <i class="fas fa-folder mr-1"></i> <?= count($categories) ?> categories
                </span>
            </p>
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
    <!-- SETTINGS LAYOUT - SIDEBAR + CONTENT -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">
        
        <!-- Settings Sidebar -->
        <div class="lg:col-span-1">
            <div class="settings-sidebar">
                <nav>
                    <a href="?tab=services&branch=<?= $selected_branch_id ?>" class="nav-link <?= $active_tab === 'services' ? 'active' : '' ?>">
                        <i class="fas fa-stethoscope"></i>
                        <span>Services & Pricing</span>
                        <span class="badge"><?= count($services) ?></span>
                    </a>
                    <a href="?tab=categories&branch=<?= $selected_branch_id ?>" class="nav-link <?= $active_tab === 'categories' ? 'active' : '' ?>">
                        <i class="fas fa-folder"></i>
                        <span>Categories</span>
                        <span class="badge"><?= count($categories) ?></span>
                    </a>
                    <a href="?tab=branches&branch=<?= $selected_branch_id ?>" class="nav-link <?= $active_tab === 'branches' ? 'active' : '' ?>">
                        <i class="fas fa-store-alt"></i>
                        <span>Branches</span>
                        <span class="badge"><?= $total_branches ?></span>
                    </a>
                    <a href="?tab=departments&branch=<?= $selected_branch_id ?>" class="nav-link <?= $active_tab === 'departments' ? 'active' : '' ?>">
                        <i class="fas fa-building"></i>
                        <span>Departments</span>
                        <span class="badge">0</span>
                    </a>
                    <a href="?tab=roles&branch=<?= $selected_branch_id ?>" class="nav-link <?= $active_tab === 'roles' ? 'active' : '' ?>">
                        <i class="fas fa-user-tag"></i>
                        <span>Roles & Permissions</span>
                    </a>
                    <a href="?tab=financial&branch=<?= $selected_branch_id ?>" class="nav-link <?= $active_tab === 'financial' ? 'active' : '' ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Financial Settings</span>
                    </a>
                    <a href="?tab=security&branch=<?= $selected_branch_id ?>" class="nav-link <?= $active_tab === 'security' ? 'active' : '' ?>">
                        <i class="fas fa-shield-alt"></i>
                        <span>Security</span>
                    </a>
                    <a href="?tab=system&branch=<?= $selected_branch_id ?>" class="nav-link <?= $active_tab === 'system' ? 'active' : '' ?>">
                        <i class="fas fa-server"></i>
                        <span>System</span>
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- Settings Content -->
        <div class="lg:col-span-3">
            <div class="settings-content">
                
                <!-- ================================================================ -->
                <!-- SERVICES & PRICING TAB -->
                <!-- ================================================================ -->
                <?php if ($active_tab === 'services'): ?>
                
                <div class="section-header">
                    <h2>
                        <i class="fas fa-stethoscope"></i>
                        Services & Pricing
                        <span class="text-sm font-normal text-gray-400">(<?= count($services) ?> services)</span>
                    </h2>
                    <div class="flex gap-2 flex-wrap">
                        <button onclick="openModal('addServiceModal')" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Add Service
                        </button>
                    </div>
                </div>
                
                <!-- Filter -->
                <div class="flex flex-wrap gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-500">Show:</span>
                        <a href="?tab=services&branch=<?= $selected_branch_id ?>&show=all" 
                           class="text-sm px-3 py-1 rounded-full <?= !isset($_GET['show']) || $_GET['show'] === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600' ?>">
                            All
                        </a>
                        <a href="?tab=services&branch=<?= $selected_branch_id ?>&show=active" 
                           class="text-sm px-3 py-1 rounded-full <?= isset($_GET['show']) && $_GET['show'] === 'active' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600' ?>">
                            Active
                        </a>
                        <a href="?tab=services&branch=<?= $selected_branch_id ?>&show=inactive" 
                           class="text-sm px-3 py-1 rounded-full <?= isset($_GET['show']) && $_GET['show'] === 'inactive' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600' ?>">
                            Inactive
                        </a>
                    </div>
                </div>
                
                <!-- Services List Grouped by Category -->
                <?php if (count($services) > 0): ?>
                    <?php 
                    $grouped_services = [];
                    foreach ($services as $service) {
                        $cat_key = $service['category_id'] ?? 0;
                        $cat_name = $service['category_name'] ?? 'Uncategorized';
                        $cat_color = $service['category_color'] ?? '#0B5ED7';
                        $cat_icon = $service['category_icon'] ?? 'fa-file-medical';
                        if (!isset($grouped_services[$cat_key])) {
                            $grouped_services[$cat_key] = [
                                'name' => $cat_name,
                                'color' => $cat_color,
                                'icon' => $cat_icon,
                                'services' => []
                            ];
                        }
                        $grouped_services[$cat_key]['services'][] = $service;
                    }
                    ?>
                    
                    <?php foreach ($grouped_services as $cat_id => $group): ?>
                        <div class="mb-4">
                            <div class="flex items-center gap-3 mb-2">
                                <i class="fas <?= $group['icon'] ?>" style="color: <?= $group['color'] ?>;"></i>
                                <h3 class="text-sm font-semibold text-gray-700">
                                    <?= htmlspecialchars($group['name']) ?>
                                </h3>
                                <span class="text-xs text-gray-400">(<?= count($group['services']) ?> services)</span>
                            </div>
                            
                            <?php foreach ($group['services'] as $service): ?>
                                <div class="service-card">
                                    <div class="service-header">
                                        <div>
                                            <div class="service-name">
                                                <?= htmlspecialchars($service['service_name']) ?>
                                                <span class="badge-status <?= $service['is_active'] ? 'active' : 'inactive' ?> ml-2">
                                                    <?= $service['is_active'] ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </div>
                                            <div class="service-meta">
                                                <span>
                                                    <i class="fas fa-tag"></i>
                                                    <?= htmlspecialchars($service['unit'] ?? 'each') ?>
                                                </span>
                                                <?php if ($service['display_order'] > 0): ?>
                                                    <span>
                                                        <i class="fas fa-sort"></i>
                                                        Order: <?= $service['display_order'] ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($service['created_by_name']): ?>
                                                    <span>
                                                        <i class="fas fa-user"></i>
                                                        <?= htmlspecialchars($service['created_by_name']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($service['description'])): ?>
                                                <div class="text-sm text-gray-500 mt-1">
                                                    <?= htmlspecialchars($service['description']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right">
                                            <div class="service-price">TSh <?= number_format($service['price'], 2) ?></div>
                                            <div class="service-actions mt-2">
                                                <button onclick="editService(<?= htmlspecialchars(json_encode($service)) ?>)" 
                                                        class="btn btn-edit btn-sm">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="deleteService(<?= $service['id'] ?>)" 
                                                        class="btn btn-delete btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-stethoscope"></i>
                        <h4 class="font-semibold text-gray-700">No Services Found</h4>
                        <p class="text-sm">Click "Add Service" to create your first service.</p>
                    </div>
                <?php endif; ?>
                
                <?php endif; ?>
                
                <!-- ================================================================ -->
                <!-- CATEGORIES TAB -->
                <!-- ================================================================ -->
                <?php if ($active_tab === 'categories'): ?>
                
                <div class="section-header">
                    <h2>
                        <i class="fas fa-folder"></i>
                        Service Categories
                        <span class="text-sm font-normal text-gray-400">(<?= count($categories) ?> categories)</span>
                    </h2>
                    <button onclick="openModal('addCategoryModal')" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add Category
                    </button>
                </div>
                
                <?php if (count($categories) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($categories as $cat): ?>
                            <div class="service-card">
                                <div class="service-header">
                                    <div>
                                        <div class="service-name">
                                            <i class="fas <?= $cat['icon'] ?? 'fa-folder' ?>" style="color: <?= $cat['color'] ?? '#0B5ED7' ?>;"></i>
                                            <?= htmlspecialchars($cat['category_name']) ?>
                                            <span class="badge-status <?= $cat['is_active'] ? 'active' : 'inactive' ?> ml-2">
                                                <?= $cat['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </div>
                                        <div class="service-meta">
                                            <span>
                                                <i class="fas fa-tag"></i>
                                                <?= $category_stats[$cat['id']] ?? 0 ?> services
                                            </span>
                                            <?php if ($cat['display_order'] > 0): ?>
                                                <span>
                                                    <i class="fas fa-sort"></i>
                                                    Order: <?= $cat['display_order'] ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($cat['description'])): ?>
                                            <div class="text-sm text-gray-500 mt-1">
                                                <?= htmlspecialchars($cat['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="service-actions">
                                        <button onclick="editCategory(<?= htmlspecialchars(json_encode($cat)) ?>)" 
                                                class="btn btn-edit btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteCategory(<?= $cat['id'] ?>)" 
                                                class="btn btn-delete btn-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h4 class="font-semibold text-gray-700">No Categories Found</h4>
                        <p class="text-sm">Click "Add Category" to create your first service category.</p>
                    </div>
                <?php endif; ?>
                
                <?php endif; ?>
                
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer mt-5">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            System Settings
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- MODALS -->
<!-- ================================================================ -->

<!-- Add Service Modal -->
<div id="addServiceModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Service</h3>
            <button class="modal-close" onclick="closeModal('addServiceModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_service">
            
            <div class="form-row">
                <label class="form-label">Category <span class="required">*</span></label>
                <select name="category_id" class="form-control" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <label class="form-label">Service Name <span class="required">*</span></label>
                <input type="text" name="service_name" class="form-control" placeholder="e.g. General Consultation" required>
            </div>
            
            <div class="form-row">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Brief description of the service"></textarea>
            </div>
            
            <div class="form-row">
                <label class="form-label">Price (TSh) <span class="required">*</span></label>
                <input type="number" name="price" class="form-control" placeholder="15000" step="0.01" min="0" required>
            </div>
            
            <div class="form-row">
                <label class="form-label">Unit</label>
                <input type="text" name="unit" class="form-control" placeholder="e.g. each, per visit, per test" value="each">
            </div>
            
            <div class="form-row">
                <label class="form-label">Display Order</label>
                <input type="number" name="display_order" class="form-control" placeholder="0" value="0">
            </div>
            
            <div class="form-row">
                <label class="form-label">
                    <input type="checkbox" name="is_active" checked> Active
                </label>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">Add Service</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('addServiceModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Service Modal -->
<div id="editServiceModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Service</h3>
            <button class="modal-close" onclick="closeModal('editServiceModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_service">
            <input type="hidden" name="service_id" id="edit_service_id">
            
            <div class="form-row">
                <label class="form-label">Category <span class="required">*</span></label>
                <select name="category_id" id="edit_category_id" class="form-control" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <label class="form-label">Service Name <span class="required">*</span></label>
                <input type="text" name="service_name" id="edit_service_name" class="form-control" required>
            </div>
            
            <div class="form-row">
                <label class="form-label">Description</label>
                <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
            </div>
            
            <div class="form-row">
                <label class="form-label">Price (TSh) <span class="required">*</span></label>
                <input type="number" name="price" id="edit_price" class="form-control" step="0.01" min="0" required>
            </div>
            
            <div class="form-row">
                <label class="form-label">Unit</label>
                <input type="text" name="unit" id="edit_unit" class="form-control" value="each">
            </div>
            
            <div class="form-row">
                <label class="form-label">Display Order</label>
                <input type="number" name="display_order" id="edit_display_order" class="form-control" value="0">
            </div>
            
            <div class="form-row">
                <label class="form-label">
                    <input type="checkbox" name="is_active" id="edit_is_active"> Active
                </label>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">Update Service</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('editServiceModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Category Modal -->
<div id="addCategoryModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-folder-plus"></i> Add New Category</h3>
            <button class="modal-close" onclick="closeModal('addCategoryModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_category">
            
            <div class="form-row">
                <label class="form-label">Category Name <span class="required">*</span></label>
                <input type="text" name="category_name" class="form-control" placeholder="e.g. Consultation" required>
            </div>
            
            <div class="form-row">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Brief description of the category"></textarea>
            </div>
            
            <div class="form-row">
                <label class="form-label">Icon (Font Awesome)</label>
                <input type="text" name="icon" class="form-control" placeholder="fa-file-medical" value="fa-file-medical">
                <small class="text-xs text-gray-400">e.g. fa-stethoscope, fa-pills, fa-flask</small>
            </div>
            
            <div class="form-row">
                <label class="form-label">Color</label>
                <div class="flex items-center gap-3">
                    <input type="color" name="color" class="w-12 h-12 rounded-lg border-2 border-gray-200 cursor-pointer" value="#0B5ED7">
                    <input type="text" name="color_text" id="color_text" class="form-control flex-1" placeholder="#0B5ED7" value="#0B5ED7">
                </div>
            </div>
            
            <div class="form-row">
                <label class="form-label">Display Order</label>
                <input type="number" name="display_order" class="form-control" placeholder="0" value="0">
            </div>
            
            <div class="form-row">
                <label class="form-label">
                    <input type="checkbox" name="is_active" checked> Active
                </label>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">Add Category</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('addCategoryModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editCategoryModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Category</h3>
            <button class="modal-close" onclick="closeModal('editCategoryModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_category">
            <input type="hidden" name="category_id" id="edit_category_id">
            
            <div class="form-row">
                <label class="form-label">Category Name <span class="required">*</span></label>
                <input type="text" name="category_name" id="edit_category_name" class="form-control" required>
            </div>
            
            <div class="form-row">
                <label class="form-label">Description</label>
                <textarea name="description" id="edit_category_description" class="form-control" rows="2"></textarea>
            </div>
            
            <div class="form-row">
                <label class="form-label">Icon (Font Awesome)</label>
                <input type="text" name="icon" id="edit_category_icon" class="form-control" value="fa-file-medical">
            </div>
            
            <div class="form-row">
                <label class="form-label">Color</label>
                <div class="flex items-center gap-3">
                    <input type="color" name="color" id="edit_category_color" class="w-12 h-12 rounded-lg border-2 border-gray-200 cursor-pointer" value="#0B5ED7">
                    <input type="text" name="color_text" id="edit_category_color_text" class="form-control flex-1" value="#0B5ED7">
                </div>
            </div>
            
            <div class="form-row">
                <label class="form-label">Display Order</label>
                <input type="number" name="display_order" id="edit_category_display_order" class="form-control" value="0">
            </div>
            
            <div class="form-row">
                <label class="form-label">
                    <input type="checkbox" name="is_active" id="edit_category_is_active"> Active
                </label>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">Update Category</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('editCategoryModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

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
    // MODAL FUNCTIONS
    // ================================================================
    function openModal(id) {
        document.getElementById(id).classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
        document.body.style.overflow = '';
    }
    
    // Close modal on outside click
    document.querySelectorAll('.modal-overlay').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    });

    // ================================================================
    // COLOR PICKER SYNC
    // ================================================================
    document.querySelectorAll('input[type="color"]').forEach(function(colorInput) {
        colorInput.addEventListener('input', function() {
            var textInput = this.closest('.flex').querySelector('input[type="text"]');
            if (textInput) {
                textInput.value = this.value;
            }
        });
    });
    
    document.querySelectorAll('input[name="color_text"]').forEach(function(textInput) {
        textInput.addEventListener('input', function() {
            var colorInput = this.closest('.flex').querySelector('input[type="color"]');
            if (colorInput && this.value.match(/^#[0-9a-fA-F]{6}$/)) {
                colorInput.value = this.value;
            }
        });
    });

    // ================================================================
    // EDIT SERVICE
    // ================================================================
    function editService(service) {
        document.getElementById('edit_service_id').value = service.id;
        document.getElementById('edit_category_id').value = service.category_id || '';
        document.getElementById('edit_service_name').value = service.service_name;
        document.getElementById('edit_description').value = service.description || '';
        document.getElementById('edit_price').value = service.price;
        document.getElementById('edit_unit').value = service.unit || 'each';
        document.getElementById('edit_display_order').value = service.display_order || 0;
        document.getElementById('edit_is_active').checked = service.is_active == 1;
        openModal('editServiceModal');
    }

    // ================================================================
    // DELETE SERVICE
    // ================================================================
    function deleteService(serviceId) {
        if (confirm('⚠️ Are you sure you want to DELETE this service?\n\nThis action CANNOT be undone!')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="delete_service"><input type="hidden" name="service_id" value="' + serviceId + '">';
            document.body.appendChild(form);
            form.submit();
        }
    }

    // ================================================================
    // EDIT CATEGORY
    // ================================================================
    function editCategory(category) {
        document.getElementById('edit_category_id').value = category.id;
        document.getElementById('edit_category_name').value = category.category_name;
        document.getElementById('edit_category_description').value = category.description || '';
        document.getElementById('edit_category_icon').value = category.icon || 'fa-file-medical';
        document.getElementById('edit_category_color').value = category.color || '#0B5ED7';
        document.getElementById('edit_category_color_text').value = category.color || '#0B5ED7';
        document.getElementById('edit_category_display_order').value = category.display_order || 0;
        document.getElementById('edit_category_is_active').checked = category.is_active == 1;
        openModal('editCategoryModal');
    }

    // ================================================================
    // DELETE CATEGORY
    // ================================================================
    function deleteCategory(categoryId) {
        if (confirm('⚠️ Are you sure you want to DELETE this category?\n\nAll services in this category will be affected!')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="delete_category"><input type="hidden" name="category_id" value="' + categoryId + '">';
            document.body.appendChild(form);
            form.submit();
        }
    }

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

    console.log('%c⚙️ Braick - System Settings', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📂 Categories: <?= count($categories) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💊 Services: <?= count($services) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔗 Database Structure: service_categories + services', 'font-size:13px; color:#64748B;');
    console.log('%c🌙 Dark Mode: ' + (localStorage.getItem('darkMode') === 'true' ? 'ON' : 'OFF'), 'font-size:13px; color:#64748B;');
</script>

</body>
</html>