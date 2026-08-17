<?php
// ================================================================
// FILE: frontend/pages/admin/services.php
// ADMIN - SERVICES MANAGEMENT
// FULL CRUD: ADD, EDIT, DELETE
// INCLUDES: Services, Lab Tests, Tools, Procedures
// BRANCH SPECIFIC - WITH NULL SUPPORT
// BLUE THEME WITH BRANCH FILTER
// WITH SESSION MANAGEMENT & LOGIN PROTECTION
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

// ================================================================
// ROLE CHECK - ONLY ADMIN CAN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// VERIFY USER EXISTS IN DATABASE - FIX FOR FOREIGN KEY
// ================================================================
require_once '../../../backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// CHECK IF USER EXISTS, IF NOT CREATE DEFAULT
// ================================================================
try {
    $check_stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
    $check_stmt->execute([$user_id]);
    $user_exists = $check_stmt->fetch();
    
    if (!$user_exists) {
        $default_password = password_hash('admin123', PASSWORD_DEFAULT);
        $insert_stmt = $db->prepare("
            INSERT INTO users (id, username, password, full_name, email, phone, role, branch_id, status, created_at) 
            VALUES (?, 'admin', ?, 'System Admin', 'admin@braick.com', '+255 700 000 000', 'admin', 1, 'active', NOW())
            ON DUPLICATE KEY UPDATE id = id
        ");
        $insert_stmt->execute([$user_id, $default_password]);
    }
} catch (Exception $e) {
    $user_id = 1;
}

// ================================================================
// INCLUDE FUNCTIONS
// ================================================================
require_once '../../../backend/helpers/functions.php';

// ================================================================
// GET PARAMETERS
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'services';
$search = $_GET['search'] ?? '';

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// GET CATEGORIES
// ================================================================
$categories = [];
try {
    $stmt = $db->query("SELECT id, category_name FROM service_categories ORDER BY category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// ================================================================
// LAB TEST CATEGORIES
// ================================================================
$lab_categories = [
    'Hematology',
    'Biochemistry',
    'Microbiology',
    'Immunology',
    'Serology',
    'Urinalysis',
    'Stool Analysis',
    'Pathology',
    'Radiology',
    'Cardiology',
    'Endocrinology',
    'Other'
];

// ================================================================
// HANDLE CRUD OPERATIONS
// ================================================================
$message = '';
$message_type = '';

// ================================================================
// GENERATE PROCEDURE CODE
// ================================================================
function generateProcedureCode($db, $branch_id) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM procedures WHERE branch_id = ? OR branch_id IS NULL
        ");
        $stmt->execute([$branch_id]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        $next_num = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        return 'PROC-' . date('Y') . '-' . $next_num;
    } catch (Exception $e) {
        return 'PROC-' . date('Ymd') . '-' . rand(100, 999);
    }
}

// ================================================================
// HELPER FUNCTION: Clean price input - REMOVE COMMAS
// ================================================================
function cleanPrice($price) {
    // Remove commas, spaces first
    $price = str_replace(',', '', $price);
    $price = str_replace(' ', '', $price);
    // Remove any other non-numeric characters except decimal point
    $price = preg_replace('/[^0-9.]/', '', $price);
    return (float)$price;
}

// ================================================================
// CRUD OPERATIONS
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $branch_id = $_POST['branch_id'] ?? null;
    
    // If branch_id is 'all' or empty, set to NULL (All Branches)
    if ($branch_id === 'all' || $branch_id === '' || $branch_id === 'NULL') {
        $branch_id = null;
    } elseif (is_numeric($branch_id)) {
        $branch_id = (int)$branch_id;
    } else {
        $branch_id = null;
    }
    
    // ================================================================
    // ADD SERVICE
    // ================================================================
    if ($action === 'add_service') {
        $service_name = trim($_POST['service_name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        // FIXED: Clean price properly - remove commas
        $price = cleanPrice($_POST['price'] ?? '0');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($service_name)) {
            $message = "❌ Service name is required";
            $message_type = 'error';
        } elseif ($price < 0) {
            $message = "❌ Price cannot be negative";
            $message_type = 'error';
        } else {
            try {
                $check_user = $db->prepare("SELECT id FROM users WHERE id = ?");
                $check_user->execute([$user_id]);
                $valid_user = $check_user->fetch();
                $created_by = $valid_user ? $user_id : null;
                
                $stmt = $db->prepare("
                    INSERT INTO services (
                        service_name, category_id, description, branch_id, 
                        price, is_active, created_by, 
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $service_name, $category_id, $description, $branch_id,
                    $price, $is_active, $created_by
                ]);
                $message = "✅ Service added successfully! Price: TSh " . number_format($price, 0);
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // ================================================================
    // UPDATE SERVICE
    // ================================================================
    if ($action === 'update_service') {
        $service_id = (int)($_POST['service_id'] ?? 0);
        $service_name = trim($_POST['service_name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        // FIXED: Clean price properly - remove commas
        $price = cleanPrice($_POST['price'] ?? '0');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($service_id <= 0 || empty($service_name)) {
            $message = "❌ Invalid service data";
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE services 
                    SET service_name = ?, category_id = ?, description = ?, 
                        branch_id = ?, price = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $service_name, $category_id, $description, 
                    $branch_id, $price, $is_active, $service_id
                ]);
                $message = "✅ Service updated successfully! Price: TSh " . number_format($price, 0);
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // ================================================================
    // DELETE SERVICE
    // ================================================================
    if ($action === 'delete_service') {
        $service_id = isset($_POST['delete_id']) ? (int)$_POST['delete_id'] : 0;
        
        if ($service_id <= 0) {
            $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
        }
        
        if ($service_id <= 0) {
            $message = "❌ Invalid service ID";
            $message_type = 'error';
        } else {
            try {
                $check_stmt = $db->prepare("SELECT id, service_name FROM services WHERE id = ?");
                $check_stmt->execute([$service_id]);
                $service = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$service) {
                    $message = "❌ Service not found";
                    $message_type = 'error';
                } else {
                    $stmt = $db->prepare("DELETE FROM services WHERE id = ?");
                    $stmt->execute([$service_id]);
                    $message = "✅ Service '" . htmlspecialchars($service['service_name']) . "' deleted successfully!";
                    $message_type = 'success';
                }
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // ================================================================
    // ADD PROCEDURE - FIXED PRICE (REMOVE COMMAS)
    // ================================================================
    if ($action === 'add_procedure') {
        $procedure_name = trim($_POST['procedure_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        // CRITICAL: Remove commas before converting to float
        $price_raw = str_replace(',', '', $_POST['price'] ?? '0');
        $price_raw = str_replace(' ', '', $price_raw);
        $price = (float)$price_raw;
        $description = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($procedure_name) || $price <= 0) {
            $message = "❌ Procedure name and valid price are required!";
            $message_type = 'error';
        } else {
            try {
                $procedure_code = generateProcedureCode($db, $branch_id);
                $stmt = $db->prepare("
                    INSERT INTO procedures (
                        procedure_name, procedure_code, category, branch_id, 
                        price, description, is_active, created_by, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $procedure_name, 
                    $procedure_code, 
                    $category, 
                    $branch_id, 
                    $price, 
                    $description, 
                    $is_active, 
                    $user_id
                ]);
                $message = "✅ Procedure added successfully! Code: " . $procedure_code . " | Price: TSh " . number_format($price, 0);
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // ================================================================
    // UPDATE PROCEDURE - FIXED PRICE (REMOVE COMMAS)
    // ================================================================
    if ($action === 'update_procedure') {
        $procedure_id = (int)($_POST['procedure_id'] ?? 0);
        $procedure_name = trim($_POST['procedure_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        // CRITICAL: Remove commas before converting to float
        $price_raw = str_replace(',', '', $_POST['price'] ?? '0');
        $price_raw = str_replace(' ', '', $price_raw);
        $price = (float)$price_raw;
        $description = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($procedure_id <= 0 || empty($procedure_name)) {
            $message = "❌ Invalid procedure data";
            $message_type = 'error';
        } elseif ($price <= 0) {
            $message = "❌ Price must be greater than 0";
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE procedures 
                    SET procedure_name = ?, category = ?, price = ?, 
                        description = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $procedure_name, 
                    $category, 
                    $price, 
                    $description, 
                    $is_active, 
                    $procedure_id
                ]);
                $message = "✅ Procedure updated successfully! Price: TSh " . number_format($price, 0);
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // ================================================================
    // DELETE PROCEDURE
    // ================================================================
    if ($action === 'delete_procedure') {
        $procedure_id = isset($_POST['delete_id']) ? (int)$_POST['delete_id'] : 0;
        
        if ($procedure_id <= 0) {
            $message = "❌ Invalid procedure ID";
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM procedures WHERE id = ?");
                $stmt->execute([$procedure_id]);
                $message = "✅ Procedure deleted successfully!";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // ================================================================
    // ADD TOOL - FIXED PRICE
    // ================================================================
    if ($action === 'add_tool') {
        $tool_name = trim($_POST['tool_name'] ?? '');
        $procedure_name = trim($_POST['procedure_name'] ?? '');
        // FIXED: Clean price properly - remove commas
        $price = cleanPrice($_POST['price'] ?? '0');
        
        if (empty($tool_name) || $price <= 0) {
            $message = "❌ Tool name and price are required!";
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO procedure_tools (
                        procedure_name, tool_name, branch_id, price, is_active, created_at
                    ) VALUES (?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$procedure_name, $tool_name, $branch_id, $price]);
                $message = "✅ Tool added successfully! Price: TSh " . number_format($price, 0);
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // ================================================================
    // UPDATE TOOL - FIXED PRICE
    // ================================================================
    if ($action === 'update_tool') {
        $tool_id = (int)($_POST['tool_id'] ?? 0);
        $tool_name = trim($_POST['tool_name'] ?? '');
        $procedure_name = trim($_POST['procedure_name'] ?? '');
        // FIXED: Clean price properly - remove commas
        $price = cleanPrice($_POST['price'] ?? '0');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($tool_id <= 0 || empty($tool_name)) {
            $message = "❌ Invalid tool data";
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE procedure_tools 
                    SET tool_name = ?, procedure_name = ?, price = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$tool_name, $procedure_name, $price, $is_active, $tool_id]);
                $message = "✅ Tool updated successfully! Price: TSh " . number_format($price, 0);
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // ================================================================
    // DELETE TOOL
    // ================================================================
    if ($action === 'delete_tool') {
        $tool_id = isset($_POST['delete_id']) ? (int)$_POST['delete_id'] : 0;
        
        if ($tool_id <= 0) {
            $message = "❌ Invalid tool ID";
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM procedure_tools WHERE id = ?");
                $stmt->execute([$tool_id]);
                $message = "✅ Tool deleted successfully!";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // ================================================================
    // ADD LAB TEST - FIXED PRICE
    // ================================================================
    if ($action === 'add_lab_test') {
        $test_name = trim($_POST['test_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        // FIXED: Clean price properly - remove commas
        $price = cleanPrice($_POST['price'] ?? '0');
        
        if (empty($test_name) || $price <= 0) {
            $message = "❌ Test name and price are required!";
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO lab_tests_catalog (
                        test_name, category, branch_id, price, is_active, created_at
                    ) VALUES (?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$test_name, $category, $branch_id, $price]);
                $message = "✅ Lab test added successfully! Price: TSh " . number_format($price, 0);
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // ================================================================
    // UPDATE LAB TEST - FIXED PRICE
    // ================================================================
    if ($action === 'update_lab_test') {
        $test_id = (int)($_POST['test_id'] ?? 0);
        $test_name = trim($_POST['test_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        // FIXED: Clean price properly - remove commas
        $price = cleanPrice($_POST['price'] ?? '0');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($test_id <= 0 || empty($test_name)) {
            $message = "❌ Invalid test data";
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE lab_tests_catalog 
                    SET test_name = ?, category = ?, price = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$test_name, $category, $price, $is_active, $test_id]);
                $message = "✅ Lab test updated successfully! Price: TSh " . number_format($price, 0);
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // ================================================================
    // DELETE LAB TEST
    // ================================================================
    if ($action === 'delete_lab_test') {
        $test_id = isset($_POST['delete_id']) ? (int)$_POST['delete_id'] : 0;
        
        if ($test_id <= 0) {
            $message = "❌ Invalid test ID";
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM lab_tests_catalog WHERE id = ?");
                $stmt->execute([$test_id]);
                $message = "✅ Lab test deleted successfully!";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// FETCH DATA WITH BRANCH FILTER
// ================================================================

// Build branch filter - SHOW ALL SERVICES FOR SELECTED BRANCH
$branch_filter = "";
$params = [];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id = (int)$selected_branch_id;
    $branch_filter = " AND (s.branch_id = ? OR s.branch_id IS NULL)";
    $params[] = $branch_id;
} else {
    $branch_filter = "";
}

// Search filter
$search_filter = "";
if (!empty($search)) {
    $search_filter = " AND (s.service_name LIKE ? OR s.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

// ================================================================
// 1. SERVICES
// ================================================================
$services = [];
try {
    $query = "
        SELECT s.*, 
               c.category_name,
               b.name as branch_name,
               u.full_name as created_by_name
        FROM services s
        LEFT JOIN service_categories c ON s.category_id = c.id
        LEFT JOIN branches b ON s.branch_id = b.id
        LEFT JOIN users u ON s.created_by = u.id
        WHERE 1=1 $branch_filter $search_filter
        ORDER BY s.service_name ASC
    ";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching services: " . $e->getMessage());
    $services = [];
}

// ================================================================
// 2. PROCEDURES
// ================================================================
$procedures = [];
try {
    $query = "
        SELECT p.*, b.name as branch_name
        FROM procedures p
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE 1=1
    ";
    
    $proc_params = [];
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $query .= " AND (p.branch_id = ? OR p.branch_id IS NULL)";
        $proc_params[] = (int)$selected_branch_id;
    }
    
    if (!empty($search)) {
        $query .= " AND (p.procedure_name LIKE ? OR p.category LIKE ? OR p.description LIKE ?)";
        $search_term = "%$search%";
        $proc_params[] = $search_term;
        $proc_params[] = $search_term;
        $proc_params[] = $search_term;
    }
    
    $query .= " ORDER BY p.procedure_name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($proc_params);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $procedures = [];
}

// ================================================================
// 3. TOOLS
// ================================================================
$tools = [];
try {
    $query = "
        SELECT t.*, b.name as branch_name
        FROM procedure_tools t
        LEFT JOIN branches b ON t.branch_id = b.id
        WHERE 1=1
    ";
    
    $tool_params = [];
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $query .= " AND (t.branch_id = ? OR t.branch_id IS NULL)";
        $tool_params[] = (int)$selected_branch_id;
    }
    
    if (!empty($search)) {
        $query .= " AND (t.tool_name LIKE ? OR t.procedure_name LIKE ?)";
        $search_term = "%$search%";
        $tool_params[] = $search_term;
        $tool_params[] = $search_term;
    }
    
    $query .= " ORDER BY t.tool_name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($tool_params);
    $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tools = [];
}

// ================================================================
// 4. LAB TESTS
// ================================================================
$lab_tests = [];
try {
    $query = "
        SELECT l.*, b.name as branch_name
        FROM lab_tests_catalog l
        LEFT JOIN branches b ON l.branch_id = b.id
        WHERE 1=1
    ";
    
    $lab_params = [];
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $query .= " AND (l.branch_id = ? OR l.branch_id IS NULL)";
        $lab_params[] = (int)$selected_branch_id;
    }
    
    if (!empty($search)) {
        $query .= " AND (l.test_name LIKE ? OR l.category LIKE ?)";
        $search_term = "%$search%";
        $lab_params[] = $search_term;
        $lab_params[] = $search_term;
    }
    
    $query .= " ORDER BY l.test_name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($lab_params);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lab_tests = [];
}

// ================================================================
// GET SINGLE ITEM FOR EDIT/VIEW
// ================================================================
$edit_item = null;
$edit_type = null;

if (isset($_GET['edit']) && is_numeric($_GET['edit']) && isset($_GET['type'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_type = $_GET['type'];
    
    if ($edit_type === 'service') {
        try {
            $stmt = $db->prepare("
                SELECT s.*, c.category_name 
                FROM services s
                LEFT JOIN service_categories c ON s.category_id = c.id
                WHERE s.id = ?
            ");
            $stmt->execute([$edit_id]);
            $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    } elseif ($edit_type === 'procedure') {
        try {
            $stmt = $db->prepare("SELECT * FROM procedures WHERE id = ?");
            $stmt->execute([$edit_id]);
            $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    } elseif ($edit_type === 'tool') {
        try {
            $stmt = $db->prepare("SELECT * FROM procedure_tools WHERE id = ?");
            $stmt->execute([$edit_id]);
            $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    } elseif ($edit_type === 'lab_test') {
        try {
            $stmt = $db->prepare("SELECT * FROM lab_tests_catalog WHERE id = ?");
            $stmt->execute([$edit_id]);
            $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    }
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getStatusBadge($status) {
    return $status ? 'success' : 'danger';
}

function getStatusLabel($status) {
    return $status ? 'Active' : 'Inactive';
}

function getBranchDisplay($branch_name) {
    return $branch_name ?? 'All Branches';
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Management - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
            --radius: 10px;
            --radius-lg: 14px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        .top-nav {
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .top-nav .avatar:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all 0.3s;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover {
            background: var(--bg-body);
            color: var(--primary);
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .branch-selector {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            font-size: 0.78rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .branch-selector:focus {
            border-color: var(--primary);
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .page-header {
            background: var(--primary-gradient);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .tabs {
            display: flex;
            gap: 4px;
            background: var(--bg-card);
            padding: 4px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            border: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        [data-theme="dark"] .tabs {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        .tab-btn {
            padding: 10px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: transparent;
            color: var(--gray-500);
            flex: 1;
            text-align: center;
            min-width: 100px;
        }
        
        .tab-btn:hover {
            background: var(--gray-100);
            color: var(--gray-700);
        }
        
        .tab-btn.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
        }
        
        [data-theme="dark"] .tab-btn.active {
            background: var(--primary);
            color: white;
        }
        
        .tab-btn i { margin-right: 8px; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
            margin-bottom: 24px;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        [data-theme="dark"] .card {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-title i { color: var(--primary); }
        
        .table-container {
            overflow-x: auto;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
        }
        
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 600px;
        }
        
        .table-container thead {
            background: var(--primary-gradient);
            color: #ffffff;
        }
        
        .table-container thead th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            border-bottom: 3px solid var(--primary-dark);
        }
        
        .table-container thead th i { margin-right: 6px; opacity: 0.8; }
        
        .table-container tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table-container tbody tr:last-child { border-bottom: none; }
        .table-container tbody tr:hover { background: var(--primary-bg); }
        [data-theme="dark"] .table-container tbody tr:hover { background: #1E3A5F; }
        
        .table-container tbody td {
            padding: 10px 16px;
            vertical-align: middle;
            color: var(--text-primary);
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .status-badge.active { background: var(--success-bg); color: var(--success); }
        .status-badge.inactive { background: var(--danger-bg); color: var(--danger); }
        
        .price-display {
            font-weight: 600;
            color: var(--primary);
        }
        
        [data-theme="dark"] .price-display { color: var(--primary-light); }
        
        .action-btns {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            text-decoration: none;
        }
        
        .btn-icon:hover { transform: scale(1.1); }
        
        .btn-icon.view { background: var(--primary-bg); color: var(--primary); }
        .btn-icon.view:hover { background: var(--primary); color: white; }
        .btn-icon.edit { background: var(--warning-bg); color: var(--warning); }
        .btn-icon.edit:hover { background: var(--warning); color: white; }
        .btn-icon.delete { background: var(--danger-bg); color: var(--danger); }
        .btn-icon.delete:hover { background: var(--danger); color: white; }
        
        .branch-filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            margin-bottom: 20px;
            padding: 12px 16px;
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
        }
        
        .branch-filter-bar select, .branch-filter-bar input {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.8rem;
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
        }
        
        .branch-filter-bar select:focus, .branch-filter-bar input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        .form-group { margin-bottom: 14px; }
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        .form-label .required { color: var(--danger); margin-left: 2px; }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.85rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        textarea.form-control { resize: vertical; min-height: 60px; }
        select.form-control { appearance: auto; cursor: pointer; }
        
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        
        .price-preview {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .price-preview .formatted-price {
            font-weight: 700;
            color: var(--success);
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            background: var(--success-bg);
            padding: 2px 12px;
            border-radius: 4px;
        }
        
        [data-theme="dark"] .price-preview .formatted-price {
            background: #1A3A2A;
            color: #34D399;
        }
        
        .price-input {
            font-family: 'Courier New', monospace;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
        }
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.2);
        }
        .btn-danger:hover {
            background: var(--danger-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(220, 38, 38, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-sm { padding: 4px 12px; font-size: 0.7rem; }
        
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            border: 1px solid transparent;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        .alert-info { background: var(--primary-bg); color: var(--primary); border-color: var(--primary); }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-item {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            text-align: center;
        }
        
        [data-theme="dark"] .stat-item {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        .stat-item .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        .stat-item .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.show { display: flex; }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px 35px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        [data-theme="dark"] .modal-content { background: var(--gray-800); }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        
        .modal-header h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .modal-header h2 i { color: var(--primary); }
        
        .modal-close {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: var(--danger-bg);
            color: var(--danger);
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-close:hover {
            background: var(--danger);
            color: white;
            transform: rotate(90deg);
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
            border-top: 2px solid var(--border-color);
            padding-top: 16px;
        }
        
        .toast-custom {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 14px 22px;
            border-radius: var(--radius);
            z-index: 9999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ffffff;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        .branch-tag {
            display: inline-block;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 1px 10px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .branch-tag.all-branches {
            background: #FEF3C7;
            color: #D97706;
        }
        
        [data-theme="dark"] .branch-tag.all-branches {
            background: #3D2E0A;
            color: #FBBF24;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--gray-300);
            display: block;
            margin-bottom: 12px;
        }
        
        [data-theme="dark"] .empty-state i { color: var(--gray-600); }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .form-row-2 { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .tabs { flex-direction: column; }
            .tab-btn { flex: none; }
            .table-container table { font-size: 0.75rem; }
            .table-container thead th, .table-container tbody td { padding: 8px 10px; }
            .modal-content { padding: 20px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-row { grid-template-columns: 1fr; }
            .modal-content { padding: 15px; }
        }
    </style>
</head>
<body>

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
            <input type="text" id="searchInput" placeholder="Search services..." value="<?= htmlspecialchars($search) ?>">
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
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <a href="../notifications.php" class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot no-notif"></span>
        </a>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-concierge-bell"></i>
                Services Management
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-list"></i>
                Manage <strong>Services</strong>, <strong>Procedures</strong>, <strong>Tools</strong> & <strong>Lab Tests</strong>
                <span class="header-badge">
                    <i class="fas fa-tag"></i> Total: <?= count($services) + count($procedures) + count($tools) + count($lab_tests) ?> items
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-concierge-bell"></i> Services: <?= count($services) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <button onclick="openAddModal('service')" class="btn-outline-light">
                <i class="fas fa-plus"></i> Add Service
            </button>
            <button onclick="openAddModal('procedure')" class="btn-outline-light">
                <i class="fas fa-syringe"></i> Add Procedure
            </button>
            <button onclick="openAddModal('tool')" class="btn-outline-light">
                <i class="fas fa-tools"></i> Add Tool
            </button>
            <button onclick="openAddModal('lab_test')" class="btn-outline-light">
                <i class="fas fa-microscope"></i> Add Lab Test
            </button>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="branch-filter-bar">
        <span class="text-sm font-semibold text-gray-500"><i class="fas fa-filter"></i> Filter:</span>
        <form method="GET" action="" class="flex flex-wrap gap-2 items-center">
            <select name="branch" onchange="this.form.submit()" class="min-w-[150px]">
                <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>All Branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" class="min-w-[180px]">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Filter
            </button>
            <a href="services.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Clear
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- STATS -->
    <!-- ================================================================ -->
    <div class="stats-row">
        <div class="stat-item">
            <div class="stat-number"><?= count($services) ?></div>
            <div class="stat-label">Services</div>
        </div>
        <div class="stat-item">
            <div class="stat-number" style="color:#7C3AED;"><?= count($procedures) ?></div>
            <div class="stat-label">Procedures</div>
        </div>
        <div class="stat-item">
            <div class="stat-number" style="color:#D97706;"><?= count($tools) ?></div>
            <div class="stat-label">Tools</div>
        </div>
        <div class="stat-item">
            <div class="stat-number" style="color:#0D9488;"><?= count($lab_tests) ?></div>
            <div class="stat-label">Lab Tests</div>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- TABS -->
    <!-- ================================================================ -->
    <div class="tabs">
        <button class="tab-btn <?= $active_tab === 'services' ? 'active' : '' ?>" data-tab="services">
            <i class="fas fa-concierge-bell"></i> Services (<?= count($services) ?>)
        </button>
        <button class="tab-btn <?= $active_tab === 'procedures' ? 'active' : '' ?>" data-tab="procedures">
            <i class="fas fa-syringe"></i> Procedures (<?= count($procedures) ?>)
        </button>
        <button class="tab-btn <?= $active_tab === 'tools' ? 'active' : '' ?>" data-tab="tools">
            <i class="fas fa-tools"></i> Tools (<?= count($tools) ?>)
        </button>
        <button class="tab-btn <?= $active_tab === 'lab_tests' ? 'active' : '' ?>" data-tab="lab_tests">
            <i class="fas fa-microscope"></i> Lab Tests (<?= count($lab_tests) ?>)
        </button>
    </div>

    <!-- ================================================================ -->
    <!-- TAB 1: SERVICES -->
    <!-- ================================================================ -->
    <div class="tab-content <?= $active_tab === 'services' ? 'active' : '' ?>" id="tab-services">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-list"></i> All Services <?php if ($selected_branch_id !== 'all'): ?>for <?= htmlspecialchars($branches[array_search($selected_branch_id, array_column($branches, 'id'))]['name'] ?? 'Branch') ?><?php endif; ?></h3>
                <button onclick="openAddModal('service')" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add Service
                </button>
            </div>
            <div class="table-container">
                <?php if (count($services) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Service Name</th>
                                <th>Category</th>
                                <th>Branch</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($services as $s): ?>
                                <tr data-id="<?= $s['id'] ?>">
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($s['service_name']) ?></strong>
                                        <?php if (!empty($s['description'])): ?>
                                            <div class="text-xs text-gray-400"><?= htmlspecialchars(substr($s['description'], 0, 50)) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge" style="background:var(--primary-bg);color:var(--primary);padding:2px 12px;border-radius:12px;font-size:0.65rem;">
                                            <?= htmlspecialchars($s['category_name'] ?? 'Uncategorized') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($s['branch_id'] === null): ?>
                                            <span class="branch-tag all-branches">🌐 All Branches</span>
                                        <?php else: ?>
                                            <span class="branch-tag"><?= htmlspecialchars($s['branch_name'] ?? 'N/A') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="price-display">TSh <?= number_format($s['price'] ?? 0, 0) ?></td>
                                    <td>
                                        <span class="status-badge <?= $s['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn-icon view" onclick="viewItem('service', <?= $s['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="?edit=<?= $s['id'] ?>&type=service&branch=<?= urlencode($selected_branch_id) ?>" class="btn-icon edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn-icon delete" onclick="deleteItem('service', <?= $s['id'] ?>, '<?= addslashes($s['service_name']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-concierge-bell"></i>
                        <p>No services found for this branch. Click "Add Service" to create one.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TAB 2: PROCEDURES -->
    <!-- ================================================================ -->
    <div class="tab-content <?= $active_tab === 'procedures' ? 'active' : '' ?>" id="tab-procedures">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-syringe"></i> All Procedures</h3>
                <button onclick="openAddModal('procedure')" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add Procedure
                </button>
            </div>
            <div class="table-container">
                <?php if (count($procedures) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Procedure Name</th>
                                <th>Code</th>
                                <th>Category</th>
                                <th>Branch</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($procedures as $p): ?>
                                <tr data-id="<?= $p['id'] ?>">
                                    <td><?= $i++ ?></td>
                                    <td><strong><?= htmlspecialchars($p['procedure_name']) ?></strong></td>
                                    <td><span class="code-badge"><?= htmlspecialchars($p['procedure_code'] ?? 'N/A') ?></span></td>
                                    <td><?= htmlspecialchars($p['category'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($p['branch_id'] === null): ?>
                                            <span class="branch-tag all-branches">🌐 All Branches</span>
                                        <?php else: ?>
                                            <span class="branch-tag"><?= htmlspecialchars($p['branch_name'] ?? 'N/A') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="price-display">TSh <?= number_format($p['price'] ?? 0, 0) ?></td>
                                    <td>
                                        <span class="status-badge <?= $p['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn-icon view" onclick="viewItem('procedure', <?= $p['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="?edit=<?= $p['id'] ?>&type=procedure&branch=<?= urlencode($selected_branch_id) ?>" class="btn-icon edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn-icon delete" onclick="deleteItem('procedure', <?= $p['id'] ?>, '<?= addslashes($p['procedure_name']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-syringe"></i>
                        <p>No procedures found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TAB 3: TOOLS -->
    <!-- ================================================================ -->
    <div class="tab-content <?= $active_tab === 'tools' ? 'active' : '' ?>" id="tab-tools">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-tools"></i> All Tools</h3>
                <button onclick="openAddModal('tool')" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add Tool
                </button>
            </div>
            <div class="table-container">
                <?php if (count($tools) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tool Name</th>
                                <th>Procedure Name</th>
                                <th>Branch</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($tools as $t): ?>
                                <tr data-id="<?= $t['id'] ?>">
                                    <td><?= $i++ ?></td>
                                    <td><strong><?= htmlspecialchars($t['tool_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($t['procedure_name'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($t['branch_id'] === null): ?>
                                            <span class="branch-tag all-branches">🌐 All Branches</span>
                                        <?php else: ?>
                                            <span class="branch-tag"><?= htmlspecialchars($t['branch_name'] ?? 'N/A') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="price-display">TSh <?= number_format($t['price'] ?? 0, 0) ?></td>
                                    <td>
                                        <span class="status-badge <?= $t['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $t['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn-icon view" onclick="viewItem('tool', <?= $t['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="?edit=<?= $t['id'] ?>&type=tool&branch=<?= urlencode($selected_branch_id) ?>" class="btn-icon edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn-icon delete" onclick="deleteItem('tool', <?= $t['id'] ?>, '<?= addslashes($t['tool_name']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-tools"></i>
                        <p>No tools found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TAB 4: LAB TESTS -->
    <!-- ================================================================ -->
    <div class="tab-content <?= $active_tab === 'lab_tests' ? 'active' : '' ?>" id="tab-lab_tests">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-microscope"></i> All Lab Tests</h3>
                <button onclick="openAddModal('lab_test')" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add Lab Test
                </button>
            </div>
            <div class="table-container">
                <?php if (count($lab_tests) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Test Name</th>
                                <th>Category</th>
                                <th>Branch</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($lab_tests as $l): ?>
                                <tr data-id="<?= $l['id'] ?>">
                                    <td><?= $i++ ?></td>
                                    <td><strong><?= htmlspecialchars($l['test_name']) ?></strong></td>
                                    <td>
                                        <?php if (!empty($l['category'])): ?>
                                            <span class="badge" style="background:#EDE9FE;color:#7C3AED;padding:2px 12px;border-radius:12px;font-size:0.65rem;">
                                                <?= htmlspecialchars($l['category']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge" style="background:var(--gray-100);color:var(--gray-500);padding:2px 12px;border-radius:12px;font-size:0.65rem;">Uncategorized</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($l['branch_id'] === null): ?>
                                            <span class="branch-tag all-branches">🌐 All Branches</span>
                                        <?php else: ?>
                                            <span class="branch-tag"><?= htmlspecialchars($l['branch_name'] ?? 'N/A') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="price-display">TSh <?= number_format($l['price'] ?? 0, 0) ?></td>
                                    <td>
                                        <span class="status-badge <?= $l['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $l['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn-icon view" onclick="viewItem('lab_test', <?= $l['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="?edit=<?= $l['id'] ?>&type=lab_test&branch=<?= urlencode($selected_branch_id) ?>" class="btn-icon edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn-icon delete" onclick="deleteItem('lab_test', <?= $l['id'] ?>, '<?= addslashes($l['test_name']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-microscope"></i>
                        <p>No lab tests found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ADD MODAL -->
    <!-- ================================================================ -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="addModalTitle"><i class="fas fa-plus"></i> Add New</h2>
                <button class="modal-close" onclick="closeModal('addModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="addForm">
                <input type="hidden" name="action" id="addFormAction" value="">
                
                <div class="form-group">
                    <label class="form-label">Branch <span class="required">*</span></label>
                    <select name="branch_id" id="addBranchSelect" class="form-control" required>
                        <option value="">-- Select Branch --</option>
                        <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="addFormFields"></div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- EDIT MODAL -->
    <!-- ================================================================ -->
    <?php if ($edit_item && isset($edit_type)): ?>
    <div class="modal-overlay show" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit <?= ucfirst($edit_type) ?></h2>
                <a href="?branch=<?= urlencode($selected_branch_id) ?>" class="modal-close">
                    <i class="fas fa-times"></i>
                </a>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_<?= $edit_type ?>">
                <input type="hidden" name="<?= $edit_type ?>_id" value="<?= $edit_item['id'] ?>">
                
                <div class="form-group">
                    <label class="form-label">Branch <span class="required">*</span></label>
                    <select name="branch_id" class="form-control" required>
                        <option value="">-- Select Branch --</option>
                        <option value="all" <?= $edit_item['branch_id'] === null ? 'selected' : '' ?>>🌐 All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $edit_item['branch_id'] == $b['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($edit_type === 'service'): ?>
                    <div class="form-group">
                        <label class="form-label">Service Name <span class="required">*</span></label>
                        <input type="text" name="service_name" class="form-control" value="<?= htmlspecialchars($edit_item['service_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-control">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $edit_item['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($edit_item['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">Price (TSh) <span class="required">*</span></label>
                            <input type="text" name="price" class="form-control price-input" value="<?= number_format($edit_item['price'] ?? 0, 0) ?>" required oninput="formatPriceInput(this)">
                            <div class="price-preview">
                                <span>Formatted:</span>
                                <span class="formatted-price">TSh <?= number_format($edit_item['price'] ?? 0, 0) ?></span>
                            </div>
                        </div>
                        <div class="form-group" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="checkbox" name="is_active" value="1" <?= $edit_item['is_active'] ? 'checked' : '' ?>>
                                <span>Active</span>
                            </label>
                        </div>
                    </div>
                <?php elseif ($edit_type === 'procedure'): ?>
                    <div class="form-group">
                        <label class="form-label">Procedure Name <span class="required">*</span></label>
                        <input type="text" name="procedure_name" class="form-control" value="<?= htmlspecialchars($edit_item['procedure_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($edit_item['category'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($edit_item['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">Price (TSh) <span class="required">*</span></label>
                            <input type="text" name="price" class="form-control price-input" value="<?= number_format($edit_item['price'] ?? 0, 0) ?>" required oninput="formatPriceInput(this)">
                            <div class="price-preview">
                                <span>Formatted:</span>
                                <span class="formatted-price">TSh <?= number_format($edit_item['price'] ?? 0, 0) ?></span>
                            </div>
                        </div>
                        <div class="form-group" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="checkbox" name="is_active" value="1" <?= $edit_item['is_active'] ? 'checked' : '' ?>>
                                <span>Active</span>
                            </label>
                        </div>
                    </div>
                <?php elseif ($edit_type === 'tool'): ?>
                    <div class="form-group">
                        <label class="form-label">Tool Name <span class="required">*</span></label>
                        <input type="text" name="tool_name" class="form-control" value="<?= htmlspecialchars($edit_item['tool_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Procedure Name</label>
                        <input type="text" name="procedure_name" class="form-control" value="<?= htmlspecialchars($edit_item['procedure_name'] ?? '') ?>">
                    </div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">Price (TSh) <span class="required">*</span></label>
                            <input type="text" name="price" class="form-control price-input" value="<?= number_format($edit_item['price'] ?? 0, 0) ?>" required oninput="formatPriceInput(this)">
                            <div class="price-preview">
                                <span>Formatted:</span>
                                <span class="formatted-price">TSh <?= number_format($edit_item['price'] ?? 0, 0) ?></span>
                            </div>
                        </div>
                        <div class="form-group" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="checkbox" name="is_active" value="1" <?= $edit_item['is_active'] ? 'checked' : '' ?>>
                                <span>Active</span>
                            </label>
                        </div>
                    </div>
                <?php elseif ($edit_type === 'lab_test'): ?>
                    <div class="form-group">
                        <label class="form-label">Test Name <span class="required">*</span></label>
                        <input type="text" name="test_name" class="form-control" value="<?= htmlspecialchars($edit_item['test_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-control">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($lab_categories as $cat): ?>
                                <option value="<?= $cat ?>" <?= $edit_item['category'] == $cat ? 'selected' : '' ?>>
                                    <?= $cat ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="other" <?= !in_array($edit_item['category'] ?? '', $lab_categories) && !empty($edit_item['category']) ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">Price (TSh) <span class="required">*</span></label>
                            <input type="text" name="price" class="form-control price-input" value="<?= number_format($edit_item['price'] ?? 0, 0) ?>" required oninput="formatPriceInput(this)">
                            <div class="price-preview">
                                <span>Formatted:</span>
                                <span class="formatted-price">TSh <?= number_format($edit_item['price'] ?? 0, 0) ?></span>
                            </div>
                        </div>
                        <div class="form-group" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="checkbox" name="is_active" value="1" <?= $edit_item['is_active'] ? 'checked' : '' ?>>
                                <span>Active</span>
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="modal-actions">
                    <a href="?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- VIEW MODAL -->
    <!-- ================================================================ -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="viewModalTitle"><i class="fas fa-eye"></i> Details</h2>
                <button class="modal-close" onclick="closeModal('viewModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="viewModalBody"></div>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="closeModal('viewModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DELETE CONFIRM MODAL -->
    <!-- ================================================================ -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content" style="max-width:450px;">
            <div class="modal-header">
                <h2><i class="fas fa-trash" style="color:var(--danger);"></i> Confirm Delete</h2>
                <button class="modal-close" onclick="closeModal('deleteModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" id="deleteAction" value="">
                <input type="hidden" name="delete_id" id="deleteId" value="">
                <p id="deleteMessage" style="margin-bottom:20px;font-size:1rem;">Are you sure you want to delete this item?</p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Services Management
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // FORMAT PRICE INPUT - FIXED
    // ================================================================
    function formatPriceInput(input) {
        // Remove all non-numeric characters except numbers
        var raw = input.value.replace(/[^0-9]/g, '');
        if (raw === '') {
            input.value = '';
            var preview = input.closest('.form-group').querySelector('.formatted-price');
            if (preview) preview.textContent = 'TSh 0';
            return;
        }
        var formatted = parseInt(raw).toLocaleString('en-US');
        input.value = formatted;
        var preview = input.closest('.form-group').querySelector('.formatted-price');
        if (preview) preview.textContent = 'TSh ' + formatted;
    }

    // ================================================================
    // CLEAN PRICE BEFORE FORM SUBMIT - FIXED
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var addForm = document.getElementById('addForm');
        if (addForm) {
            addForm.addEventListener('submit', function(e) {
                var priceInput = this.querySelector('[name="price"]');
                if (priceInput) {
                    // Remove commas before submitting
                    var cleanValue = priceInput.value.replace(/,/g, '');
                    priceInput.value = cleanValue;
                    console.log('Price cleaned: ' + cleanValue);
                }
            });
        }
    });

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
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    // ================================================================
    // TABS
    // ================================================================
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            var tab = this.dataset.tab;
            document.querySelectorAll('.tab-content').forEach(function(content) {
                content.classList.remove('active');
            });
            document.getElementById('tab-' + tab).classList.add('active');
            var url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);
        });
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
    
    document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    });

    // ================================================================
    // ADD MODAL
    // ================================================================
    function openAddModal(type) {
        var title = document.getElementById('addModalTitle');
        var action = document.getElementById('addFormAction');
        var fields = document.getElementById('addFormFields');
        
        var typeLabels = {
            'service': 'Service',
            'procedure': 'Procedure',
            'tool': 'Tool',
            'lab_test': 'Lab Test'
        };
        
        title.innerHTML = '<i class="fas fa-plus"></i> Add New ' + typeLabels[type];
        action.value = 'add_' + type;
        
        var html = '';
        
        if (type === 'service') {
            html = `
                <div class="form-group">
                    <label class="form-label">Service Name <span class="required">*</span></label>
                    <input type="text" name="service_name" class="form-control" required placeholder="e.g. General Consultation">
                </div>
                <div class="form-group">
                    <label class="form-label">Category <span class="required">*</span></label>
                    <select name="category_id" class="form-control" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Service description..."></textarea>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Price (TSh) <span class="required">*</span></label>
                        <input type="text" name="price" class="form-control price-input" placeholder="e.g. 10000" required oninput="formatPriceInput(this)">
                        <div class="price-preview">
                            <span>Formatted:</span>
                            <span class="formatted-price">TSh 0</span>
                        </div>
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span>Active</span>
                        </label>
                    </div>
                </div>
            `;
        } else if (type === 'procedure') {
            html = `
                <div class="form-group">
                    <label class="form-label">Procedure Name <span class="required">*</span></label>
                    <input type="text" name="procedure_name" class="form-control" required placeholder="e.g. Wound Dressing">
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <input type="text" name="category" class="form-control" placeholder="e.g. Surgery, Wound Care">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Procedure description..."></textarea>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Price (TSh) <span class="required">*</span></label>
                        <input type="text" name="price" class="form-control price-input" placeholder="e.g. 150000" required oninput="formatPriceInput(this)">
                        <div class="price-preview">
                            <span>Formatted:</span>
                            <span class="formatted-price">TSh 0</span>
                        </div>
                        <small style="color: var(--text-secondary); font-size: 0.65rem; display: block; margin-top: 4px;">
                            <i class="fas fa-info-circle"></i> Enter numbers without commas (e.g., 150000 for 150,000)
                        </small>
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span>Active</span>
                        </label>
                    </div>
                </div>
                <div class="form-group" style="background:var(--gray-50);padding:10px 14px;border-radius:8px;font-size:0.75rem;color:var(--gray-500);">
                    <i class="fas fa-info-circle"></i> 
                    Procedure code will be generated automatically
                </div>
            `;
        } else if (type === 'tool') {
            html = `
                <div class="form-group">
                    <label class="form-label">Tool Name <span class="required">*</span></label>
                    <input type="text" name="tool_name" class="form-control" required placeholder="e.g. Syringe, Scalpel">
                </div>
                <div class="form-group">
                    <label class="form-label">Procedure Name</label>
                    <input type="text" name="procedure_name" class="form-control" placeholder="e.g. Injection, Wound Dressing">
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Price (TSh) <span class="required">*</span></label>
                        <input type="text" name="price" class="form-control price-input" placeholder="e.g. 500" required oninput="formatPriceInput(this)">
                        <div class="price-preview">
                            <span>Formatted:</span>
                            <span class="formatted-price">TSh 0</span>
                        </div>
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span>Active</span>
                        </label>
                    </div>
                </div>
            `;
        } else if (type === 'lab_test') {
            html = `
                <div class="form-group">
                    <label class="form-label">Test Name <span class="required">*</span></label>
                    <input type="text" name="test_name" class="form-control" required placeholder="e.g. Complete Blood Count">
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-control">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($lab_categories as $cat): ?>
                            <option value="<?= $cat ?>"><?= $cat ?></option>
                        <?php endforeach; ?>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Price (TSh) <span class="required">*</span></label>
                        <input type="text" name="price" class="form-control price-input" placeholder="e.g. 5000" required oninput="formatPriceInput(this)">
                        <div class="price-preview">
                            <span>Formatted:</span>
                            <span class="formatted-price">TSh 0</span>
                        </div>
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span>Active</span>
                        </label>
                    </div>
                </div>
            `;
        }
        
        fields.innerHTML = html;
        openModal('addModal');
    }

    // ================================================================
    // VIEW ITEM
    // ================================================================
    function viewItem(type, id) {
        var title = document.getElementById('viewModalTitle');
        var body = document.getElementById('viewModalBody');
        
        var typeLabels = {
            'service': 'Service',
            'procedure': 'Procedure',
            'tool': 'Tool',
            'lab_test': 'Lab Test'
        };
        
        title.innerHTML = '<i class="fas fa-eye"></i> ' + typeLabels[type] + ' Details';
        
        var tabId = 'tab-' + type + 's';
        var tableBody = document.querySelector('#' + tabId + ' tbody');
        
        if (!tableBody) {
            body.innerHTML = '<p class="text-center text-gray-500">Table not found</p>';
            openModal('viewModal');
            return;
        }
        
        var rows = tableBody.querySelectorAll('tr');
        var row = null;
        
        for (var i = 0; i < rows.length; i++) {
            var rowId = rows[i].getAttribute('data-id');
            if (rowId == id) {
                row = rows[i];
                break;
            }
        }
        
        if (!row) {
            body.innerHTML = '<div class="text-center py-8"><i class="fas fa-exclamation-circle text-4xl text-red-500 block mb-3"></i><p class="text-gray-500">Item not found. It may have been deleted.</p></div>';
            openModal('viewModal');
            return;
        }
        
        var cells = row.querySelectorAll('td');
        var labels = ['ID', 'Name', 'Category', 'Branch', 'Price', 'Status'];
        var html = '<div class="space-y-2">';
        
        var dataIndex = 0;
        for (var i = 1; i < cells.length && dataIndex < labels.length; i++) {
            var label = labels[dataIndex] || 'Field';
            var value = cells[i]?.textContent?.trim() || 'N/A';
            
            if (value.includes('TSh')) {
                value = value.trim();
            }
            
            if (label === 'Status' && (value.includes('Active') || value.includes('Inactive'))) {
                var isActive = value.includes('Active');
                value = '<span class="status-badge ' + (isActive ? 'active' : 'inactive') + '">' + value.trim() + '</span>';
            }
            
            if (label === 'Branch') {
                if (value.includes('All Branches')) {
                    value = '<span class="branch-tag all-branches">🌐 ' + value + '</span>';
                } else {
                    value = '<span class="branch-tag">🏥 ' + value + '</span>';
                }
            }
            
            html += `
                <div style="padding:8px 0;border-bottom:1px solid var(--border-color);">
                    <div style="font-size:0.7rem;color:var(--text-secondary);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">${label}</div>
                    <div style="font-size:0.95rem;font-weight:500;margin-top:2px;">${value}</div>
                </div>
            `;
            dataIndex++;
        }
        
        html += '</div>';
        body.innerHTML = html;
        openModal('viewModal');
    }

    // ================================================================
    // DELETE ITEM
    // ================================================================
    function deleteItem(type, id, name) {
        document.getElementById('deleteAction').value = 'delete_' + type;
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteMessage').textContent = 'Are you sure you want to delete "' + name + '"? This action cannot be undone.';
        openModal('deleteModal');
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 5000);
    }
    
    <?php if ($message && $message_type): ?>
        setTimeout(function() {
            showToast('<?= $message_type === 'success' ? 'Success' : 'Error' ?>', 
                '<?= addslashes($message) ?>', 
                '<?= $message_type ?>'
            );
        }, 500);
    <?php endif; ?>

    // ================================================================
    // SEARCH
    // ================================================================
    document.getElementById('searchBtn')?.addEventListener('click', function() {
        var query = document.getElementById('searchInput').value.trim();
        var url = new URL(window.location.href);
        url.searchParams.set('search', query);
        window.location.href = url.toString();
    });
    
    document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            document.getElementById('searchBtn').click();
        }
    });

    console.log('%c🛠️ Services Management - FIXED', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Price fixed: Commas removed before saving to database', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Delete fixed: Uses delete_id from form', 'font-size:13px; color:#34D399;');
    console.log('%c🏢 Branch: <?= $selected_branch_id === 'all' ? 'All Branches' : htmlspecialchars($selected_branch_id) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>