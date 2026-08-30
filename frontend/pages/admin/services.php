<?php
// ================================================================
// FILE: frontend/pages/admin/services.php
// ADMIN - SERVICES MANAGEMENT
// FULL CRUD: ADD, VIEW, EDIT, DELETE
// INCLUDES: Services, Procedures, Lab Tests, Equipment
// WITH DUPLICATE CHECK FOR LAB TESTS
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
// INCLUDE DATABASE
// ================================================================
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET PARAMETERS
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'services';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

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
// GET BRANCH NAME FOR DISPLAY
// ================================================================
$selected_branch_name = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    foreach ($branches as $b) {
        if ($b['id'] == $selected_branch_id) {
            $selected_branch_name = $b['name'];
            break;
        }
    }
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
// HELPER FUNCTIONS
// ================================================================
function cleanPrice($price) {
    $price = str_replace(',', '', $price);
    $price = str_replace(' ', '', $price);
    $price = preg_replace('/[^0-9.]/', '', $price);
    return (float)$price;
}

function generateProcedureCode($db, $branch_id) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM procedures_catalog 
            WHERE branch_id = ? OR branch_id IS NULL
        ");
        $stmt->execute([$branch_id]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        $next_num = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        return 'PROC-' . date('Y') . '-' . $next_num;
    } catch (Exception $e) {
        return 'PROC-' . date('Ymd') . '-' . rand(100, 999);
    }
}

function generateEquipmentBatch($db, $equipment_name, $branch_id) {
    try {
        $stmt = $db->prepare("
            SELECT batch_number FROM medical_equipment 
            WHERE equipment_name = ? AND branch_id = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$equipment_name, $branch_id]);
        $last = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($last && !empty($last['batch_number'])) {
            $parts = explode('-', $last['batch_number']);
            if (count($parts) >= 2) {
                $last_num = intval(end($parts));
                $new_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
                return 'EQP-' . date('Ymd') . '-' . $new_num;
            }
        }
        return 'EQP-' . date('Ymd') . '-0001';
    } catch (Exception $e) {
        return 'EQP-' . date('Ymd') . '-' . rand(1000, 9999);
    }
}

function getStatusBadge($status) {
    return $status ? 'success' : 'danger';
}

function getStatusLabel($status) {
    return $status ? 'Active' : 'Inactive';
}

function getBranchDisplay($branch_name) {
    return $branch_name ?? 'All Branches';
}

function getStockStatus($quantity, $reorder_level) {
    if ($quantity <= 0) return ['class' => 'out', 'label' => 'Out of Stock'];
    if ($quantity <= $reorder_level) return ['class' => 'low', 'label' => 'Low Stock'];
    return ['class' => 'ok', 'label' => 'In Stock'];
}

function getExpiryStatus($expiry_date) {
    if (empty($expiry_date) || $expiry_date === '0000-00-00') {
        return ['class' => 'no-expiry', 'label' => '∞ No Expiry', 'days' => null];
    }
    $days = floor((strtotime($expiry_date) - time()) / 86400);
    if ($days < 0) return ['class' => 'expired', 'label' => 'Expired', 'days' => $days];
    if ($days <= 30) return ['class' => 'expiring', 'label' => 'Expiring Soon', 'days' => $days];
    return ['class' => 'valid', 'label' => 'Valid', 'days' => $days];
}

// ================================================================
// GET BRANCH NAME - WITH NULL HANDLING
// ================================================================
function getBranchName($db, $branch_id) {
    if ($branch_id === null || $branch_id === '' || $branch_id === 'all') {
        return 'All Branches';
    }
    
    try {
        $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
        $stmt->execute([$branch_id]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        return $branch ? $branch['name'] : 'Unknown Branch (ID: ' . $branch_id . ')';
    } catch (Exception $e) {
        return 'Unknown Branch';
    }
}

// ================================================================
// BUILD BRANCH FILTER
// ================================================================
function buildBranchFilter($selected_branch_id, $table_alias = '') {
    $prefix = $table_alias ? $table_alias . '.' : '';
    
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $branch_id = (int)$selected_branch_id;
        return " AND ({$prefix}branch_id = $branch_id OR {$prefix}branch_id IS NULL)";
    }
    return "";
}

function buildSearchFilter($search, $fields = []) {
    if (empty($search) || empty($fields)) return "";
    
    $conditions = [];
    $search_term = "%$search%";
    
    foreach ($fields as $field) {
        $conditions[] = "$field LIKE '$search_term'";
    }
    
    return " AND (" . implode(" OR ", $conditions) . ")";
}

// ================================================================
// CHECK DUPLICATE LAB TEST
// ================================================================
function checkLabTestDuplicate($db, $test_name, $category, $branch_id, $price, $exclude_id = 0) {
    $exclude_condition = $exclude_id > 0 ? "AND id != $exclude_id" : "";
    
    $branch_condition = "";
    $params = [$test_name, $category, $price];
    
    if ($branch_id === null || $branch_id === '' || $branch_id === 'all') {
        $branch_condition = "AND (branch_id IS NULL)";
    } else {
        $branch_condition = "AND branch_id = ?";
        $params[] = $branch_id;
    }
    
    $sql = "
        SELECT id, test_name, category, branch_id, price 
        FROM lab_tests_catalog 
        WHERE test_name = ? 
        AND category = ? 
        $branch_condition
        AND price = ?
        $exclude_condition
        LIMIT 1
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ================================================================
// HANDLE CRUD OPERATIONS
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $branch_id = $_POST['branch_id'] ?? null;
    
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
                $stmt = $db->prepare("
                    INSERT INTO services (
                        service_name, category_id, description, branch_id, 
                        price, is_active, created_by, 
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $service_name, $category_id, $description, $branch_id,
                    $price, $is_active, $user_id
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
    // ADD PROCEDURE
    // ================================================================
    if ($action === 'add_procedure') {
        $procedure_name = trim($_POST['procedure_name'] ?? '');
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $category_name = trim($_POST['category_name'] ?? '');
        $price = cleanPrice($_POST['price'] ?? '0');
        $description = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $final_category = '';
        if ($category_id > 0) {
            foreach ($categories as $cat) {
                if ($cat['id'] == $category_id) {
                    $final_category = $cat['category_name'];
                    break;
                }
            }
        } elseif (!empty($category_name)) {
            $final_category = $category_name;
        }
        
        if (empty($procedure_name) || $price < 0) {
            $message = "❌ Procedure name and valid price are required!";
            $message_type = 'error';
        } else {
            try {
                $procedure_code = generateProcedureCode($db, $branch_id);
                $stmt = $db->prepare("
                    INSERT INTO procedures_catalog (
                        procedure_name, procedure_code, category, branch_id, 
                        price, description, is_active, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $procedure_name,
                    $procedure_code,
                    $final_category,
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
    // UPDATE PROCEDURE
    // ================================================================
    if ($action === 'update_procedure') {
        $procedure_id = (int)($_POST['procedure_id'] ?? 0);
        $procedure_name = trim($_POST['procedure_name'] ?? '');
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $category_name = trim($_POST['category_name'] ?? '');
        $price = cleanPrice($_POST['price'] ?? '0');
        $description = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $final_category = '';
        if ($category_id > 0) {
            foreach ($categories as $cat) {
                if ($cat['id'] == $category_id) {
                    $final_category = $cat['category_name'];
                    break;
                }
            }
        } elseif (!empty($category_name)) {
            $final_category = $category_name;
        }
        
        if ($procedure_id <= 0 || empty($procedure_name)) {
            $message = "❌ Invalid procedure data";
            $message_type = 'error';
        } elseif ($price < 0) {
            $message = "❌ Price cannot be negative";
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE procedures_catalog 
                    SET procedure_name = ?, category = ?, price = ?, 
                        description = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $procedure_name, 
                    $final_category, 
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
                $stmt = $db->prepare("DELETE FROM procedures_catalog WHERE id = ?");
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
    // ADD EQUIPMENT
    // ================================================================
    if ($action === 'add_equipment') {
        $equipment_name = trim($_POST['equipment_name'] ?? '');
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $category_name = trim($_POST['category_name'] ?? '');
        $unit = trim($_POST['unit'] ?? 'pcs');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $reorder_level = (int)($_POST['reorder_level'] ?? 5);
        $selling_price = cleanPrice($_POST['selling_price'] ?? '0');
        $supplier = trim($_POST['supplier'] ?? '');
        $expiry_date = $_POST['expiry_date'] ?? '';
        $batch_number = trim($_POST['batch_number'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        $final_category = '';
        if ($category_id > 0) {
            foreach ($categories as $cat) {
                if ($cat['id'] == $category_id) {
                    $final_category = $cat['category_name'];
                    break;
                }
            }
        } elseif (!empty($category_name)) {
            $final_category = $category_name;
        }
        
        if (empty($batch_number)) {
            $batch_number = generateEquipmentBatch($db, $equipment_name, $branch_id);
        }
        
        $errors = [];
        if (empty($equipment_name)) { $errors[] = 'Equipment name is required'; }
        if ($quantity < 0) { $errors[] = 'Quantity cannot be negative'; }
        if ($selling_price < 0) { $errors[] = 'Selling price cannot be negative'; }
        if (!empty($expiry_date) && strtotime($expiry_date) < strtotime(date('Y-m-d'))) {
            $errors[] = 'Expiry date cannot be in the past';
        }
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO medical_equipment (
                        equipment_name, category, unit, quantity, reorder_level,
                        selling_price, supplier, expiry_date, batch_number,
                        branch_id, status, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $equipment_name, $final_category, $unit, $quantity, $reorder_level,
                    $selling_price, $supplier, $expiry_date, $batch_number,
                    $branch_id, $status, $user_id
                ]);
                
                $message = "✅ Equipment added successfully! Batch: <strong>$batch_number</strong>";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // UPDATE EQUIPMENT
    // ================================================================
    if ($action === 'update_equipment') {
        $equipment_id = (int)($_POST['equipment_id'] ?? 0);
        $equipment_name = trim($_POST['equipment_name'] ?? '');
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $category_name = trim($_POST['category_name'] ?? '');
        $unit = trim($_POST['unit'] ?? 'pcs');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $reorder_level = (int)($_POST['reorder_level'] ?? 5);
        $selling_price = cleanPrice($_POST['selling_price'] ?? '0');
        $supplier = trim($_POST['supplier'] ?? '');
        $expiry_date = $_POST['expiry_date'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        $final_category = '';
        if ($category_id > 0) {
            foreach ($categories as $cat) {
                if ($cat['id'] == $category_id) {
                    $final_category = $cat['category_name'];
                    break;
                }
            }
        } elseif (!empty($category_name)) {
            $final_category = $category_name;
        }
        
        if ($equipment_id <= 0 || empty($equipment_name)) {
            $message = "❌ Invalid equipment data";
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE medical_equipment 
                    SET equipment_name = ?, category = ?, unit = ?, quantity = ?, 
                        reorder_level = ?, selling_price = ?, supplier = ?, 
                        expiry_date = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $equipment_name, $final_category, $unit, $quantity,
                    $reorder_level, $selling_price, $supplier,
                    $expiry_date, $status, $equipment_id
                ]);
                $message = "✅ Equipment updated successfully!";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // ================================================================
    // DELETE EQUIPMENT
    // ================================================================
    if ($action === 'delete_equipment') {
        $equipment_id = isset($_POST['delete_id']) ? (int)$_POST['delete_id'] : 0;
        
        if ($equipment_id <= 0) {
            $message = "❌ Invalid equipment ID";
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM medical_equipment WHERE id = ?");
                $stmt->execute([$equipment_id]);
                $message = "✅ Equipment deleted successfully!";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // ================================================================
    // ADD LAB TEST - WITH DUPLICATE CHECK
    // ================================================================
    if ($action === 'add_lab_test') {
        $test_name = isset($_POST['test_name']) ? trim($_POST['test_name']) : '';
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $category_name = isset($_POST['category_name']) ? trim($_POST['category_name']) : '';
        $price = isset($_POST['price']) ? cleanPrice($_POST['price']) : 0;
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $equipment_ids = isset($_POST['equipment_ids']) && is_array($_POST['equipment_ids']) ? $_POST['equipment_ids'] : [];
        
        $final_category = '';
        if ($category_id > 0) {
            foreach ($categories as $cat) {
                if ($cat['id'] == $category_id) {
                    $final_category = $cat['category_name'];
                    break;
                }
            }
        } elseif (!empty($category_name)) {
            $final_category = $category_name;
        }
        
        if (empty($test_name)) {
            $message = "❌ Test name is required!";
            $message_type = 'error';
        } elseif ($price < 0) {
            $message = "❌ Price cannot be negative";
            $message_type = 'error';
        } else {
            try {
                // Check for duplicate
                $duplicate = checkLabTestDuplicate($db, $test_name, $final_category, $branch_id, $price);
                
                if ($duplicate) {
                    $message = "❌ Duplicate test found!<br>";
                    $message .= "Test '<strong>" . htmlspecialchars($duplicate['test_name']) . "</strong>' already exists with:<br>";
                    $message .= "• Category: " . htmlspecialchars($duplicate['category']) . "<br>";
                    $message .= "• Price: TSh " . number_format($duplicate['price'], 0) . "<br>";
                    $message .= "• Branch: " . getBranchName($db, $duplicate['branch_id']);
                    $message_type = 'error';
                } else {
                    $db->beginTransaction();
                    
                    $stmt = $db->prepare("
                        INSERT INTO lab_tests_catalog (
                            test_name, category, branch_id, price, description,
                            is_active, created_by, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $test_name, 
                        $final_category, 
                        $branch_id, 
                        $price, 
                        $description,
                        $is_active,
                        $user_id
                    ]);
                    $test_id = $db->lastInsertId();
                    
                    if (!empty($equipment_ids)) {
                        $equipment_ids = array_map('intval', $equipment_ids);
                        foreach ($equipment_ids as $equip_id) {
                            $stmt = $db->prepare("SELECT id FROM medical_equipment WHERE id = ?");
                            $stmt->execute([$equip_id]);
                            if ($stmt->fetch()) {
                                $stmt = $db->prepare("
                                    INSERT INTO lab_test_equipment (lab_test_id, equipment_id, branch_id, created_at)
                                    VALUES (?, ?, ?, NOW())
                                ");
                                $stmt->execute([$test_id, $equip_id, $branch_id]);
                            }
                        }
                    }
                    
                    $db->commit();
                    
                    $equipment_text = '';
                    if (!empty($equipment_ids)) {
                        $equipment_text = ' with ' . count($equipment_ids) . ' equipment(s) linked (FREE)';
                    }
                    
                    $message = "✅ Lab test added successfully!$equipment_text";
                    $message_type = 'success';
                }
            } catch (Exception $e) {
                if (isset($db)) $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // ================================================================
    // UPDATE LAB TEST - FIXED: Gets test_id from multiple possible field names
    // ================================================================
    if ($action === 'update_lab_test') {
        // ✅ FIX: Get test_id from multiple possible field names
        $test_id = 0;
        if (isset($_POST['test_id']) && is_numeric($_POST['test_id'])) {
            $test_id = (int)$_POST['test_id'];
        } elseif (isset($_POST['lab_test_id']) && is_numeric($_POST['lab_test_id'])) {
            $test_id = (int)$_POST['lab_test_id'];
        }
        
        $test_name = isset($_POST['test_name']) ? trim($_POST['test_name']) : '';
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $category_name = isset($_POST['category_name']) ? trim($_POST['category_name']) : '';
        $price = isset($_POST['price']) ? cleanPrice($_POST['price']) : 0;
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $equipment_ids = isset($_POST['equipment_ids']) && is_array($_POST['equipment_ids']) ? $_POST['equipment_ids'] : [];
        
        // Handle branch_id from POST
        $branch_id_input = isset($_POST['branch_id']) ? $_POST['branch_id'] : null;
        if ($branch_id_input === 'all' || $branch_id_input === '' || $branch_id_input === 'NULL') {
            $branch_id = null;
        } elseif (is_numeric($branch_id_input)) {
            $branch_id = (int)$branch_id_input;
        } else {
            $branch_id = null;
        }
        
        $final_category = '';
        if ($category_id > 0) {
            foreach ($categories as $cat) {
                if ($cat['id'] == $category_id) {
                    $final_category = $cat['category_name'];
                    break;
                }
            }
        } elseif (!empty($category_name)) {
            $final_category = $category_name;
        }
        
        // Check if we have valid data
        if ($test_id <= 0) {
            $message = "❌ Invalid test ID - Please select a valid test to edit";
            $message_type = 'error';
            error_log("Update lab test failed: test_id = $test_id");
        } elseif (empty($test_name)) {
            $message = "❌ Test name is required";
            $message_type = 'error';
        } elseif ($price < 0) {
            $message = "❌ Price cannot be negative";
            $message_type = 'error';
        } else {
            try {
                // Check if test exists
                $check_stmt = $db->prepare("SELECT id, branch_id FROM lab_tests_catalog WHERE id = ?");
                $check_stmt->execute([$test_id]);
                $existing_test = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$existing_test) {
                    $message = "❌ Test not found with ID: $test_id";
                    $message_type = 'error';
                    error_log("Update lab test: Test ID $test_id not found in database");
                } else {
                    // Use existing branch if not provided
                    if ($branch_id === null && $existing_test['branch_id'] !== null) {
                        $branch_id = $existing_test['branch_id'];
                    }
                    
                    // Check for duplicate excluding current test
                    $duplicate = checkLabTestDuplicate($db, $test_name, $final_category, $branch_id, $price, $test_id);
                    
                    if ($duplicate) {
                        $message = "❌ Duplicate test found!<br>";
                        $message .= "Test '<strong>" . htmlspecialchars($duplicate['test_name']) . "</strong>' already exists with:<br>";
                        $message .= "• Category: " . htmlspecialchars($duplicate['category']) . "<br>";
                        $message .= "• Price: TSh " . number_format($duplicate['price'], 0) . "<br>";
                        $message .= "• Branch: " . getBranchName($db, $duplicate['branch_id']);
                        $message_type = 'error';
                    } else {
                        $db->beginTransaction();
                        
                        // Update lab test details - include branch_id
                        $stmt = $db->prepare("
                            UPDATE lab_tests_catalog 
                            SET test_name = ?, category = ?, price = ?, 
                                description = ?, is_active = ?, 
                                branch_id = ?,
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $test_name, $final_category, $price, 
                            $description, $is_active,
                            $branch_id,
                            $test_id
                        ]);
                        
                        // Remove existing equipment links
                        $stmt = $db->prepare("DELETE FROM lab_test_equipment WHERE lab_test_id = ?");
                        $stmt->execute([$test_id]);
                        
                        // Add new equipment links
                        if (!empty($equipment_ids)) {
                            $equipment_ids = array_map('intval', $equipment_ids);
                            foreach ($equipment_ids as $equip_id) {
                                // Verify equipment exists
                                $stmt = $db->prepare("SELECT id FROM medical_equipment WHERE id = ?");
                                $stmt->execute([$equip_id]);
                                if ($stmt->fetch()) {
                                    $stmt = $db->prepare("
                                        INSERT INTO lab_test_equipment (lab_test_id, equipment_id, branch_id, created_at)
                                        VALUES (?, ?, ?, NOW())
                                    ");
                                    $stmt->execute([$test_id, $equip_id, $branch_id]);
                                }
                            }
                        }
                        
                        $db->commit();
                        
                        $equipment_text = '';
                        if (!empty($equipment_ids)) {
                            $equipment_text = ' with ' . count($equipment_ids) . ' equipment(s) linked';
                        }
                        
                        $message = "✅ Lab test updated successfully! Price: TSh " . number_format($price, 0) . $equipment_text;
                        $message_type = 'success';
                        
                        // Redirect after 2 seconds
                        echo '<script>
                            setTimeout(function() {
                                window.location.href = "services.php?branch=' . urlencode($selected_branch_id) . '&tab=lab_tests&success=1";
                            }, 2000);
                        </script>';
                    }
                }
            } catch (Exception $e) {
                if (isset($db)) $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
                error_log("Update lab test error: " . $e->getMessage());
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
                // Delete equipment links first
                $stmt = $db->prepare("DELETE FROM lab_test_equipment WHERE lab_test_id = ?");
                $stmt->execute([$test_id]);
                
                // Delete lab test
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
// FETCH DATA
// ================================================================

// 1. SERVICES
$services = [];
try {
    $branch_filter = buildBranchFilter($selected_branch_id, 's');
    $search_filter = buildSearchFilter($search, ['s.service_name', 's.description']);
    
    $query = "
        SELECT 
            s.*, 
            c.category_name,
            b.name as branch_name,
            u.full_name as created_by_name
        FROM services s
        LEFT JOIN service_categories c ON s.category_id = c.id
        LEFT JOIN branches b ON s.branch_id = b.id
        LEFT JOIN users u ON s.created_by = u.id
        WHERE 1=1 $branch_filter $search_filter
        ORDER BY s.created_at DESC, s.service_name ASC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $services = [];
}

// 2. PROCEDURES
$procedures = [];
try {
    $branch_filter = buildBranchFilter($selected_branch_id, 'p');
    $search_filter = buildSearchFilter($search, ['p.procedure_name', 'p.category', 'p.description']);
    
    $query = "
        SELECT 
            p.*, 
            b.name as branch_name, 
            u.full_name as created_by_name
        FROM procedures_catalog p
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE 1=1 $branch_filter $search_filter
        ORDER BY p.procedure_name ASC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $procedures = [];
}

// 3. MEDICAL EQUIPMENT - GROUPED BY EQUIPMENT NAME
$equipment = [];
try {
    $branch_filter = buildBranchFilter($selected_branch_id, 'e');
    $search_filter = buildSearchFilter($search, ['e.equipment_name', 'e.category']);
    
    $query = "
        SELECT 
            MIN(e.id) as equipment_id,
            e.equipment_name,
            e.category,
            e.unit,
            e.branch_id,
            SUM(e.quantity) as total_quantity,
            MIN(e.reorder_level) as reorder_level,
            MIN(e.selling_price) as selling_price,
            MIN(e.supplier) as supplier,
            MIN(e.expiry_date) as expiry_date,
            GROUP_CONCAT(e.id) as batch_ids,
            GROUP_CONCAT(e.batch_number SEPARATOR '|') as batch_numbers,
            GROUP_CONCAT(e.quantity SEPARATOR '|') as batch_quantities,
            GROUP_CONCAT(e.expiry_date SEPARATOR '|') as batch_expiries,
            GROUP_CONCAT(e.status SEPARATOR '|') as batch_statuses,
            MIN(DATEDIFF(e.expiry_date, CURDATE())) as days_remaining,
            MIN(e.created_by) as created_by,
            u.full_name as created_by_name,
            b.name as branch_name,
            CASE 
                WHEN SUM(e.quantity) <= 0 THEN 'inactive'
                WHEN MIN(e.expiry_date) IS NULL OR MIN(e.expiry_date) = '0000-00-00' THEN 'active'
                WHEN SUM(CASE WHEN e.status = 'active' AND (e.expiry_date IS NULL OR e.expiry_date >= CURDATE()) THEN 1 ELSE 0 END) > 0 THEN 'active'
                ELSE 'inactive'
            END as computed_status
        FROM medical_equipment e
        LEFT JOIN users u ON e.created_by = u.id
        LEFT JOIN branches b ON e.branch_id = b.id
        WHERE 1=1 $branch_filter $search_filter
        GROUP BY e.equipment_name, e.category, e.unit, e.branch_id
        ORDER BY e.equipment_name
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $equipment = [];
}

// 4. LAB TESTS - WITH DUPLICATE WARNING
$lab_tests = [];
$duplicate_warning = [];
try {
    $branch_filter = buildBranchFilter($selected_branch_id, 'l');
    $search_filter = buildSearchFilter($search, ['l.test_name', 'l.category']);
    
    $query = "
        SELECT 
            l.*, 
            u.full_name as created_by_name, 
            b.name as branch_name,
            GROUP_CONCAT(DISTINCT e.equipment_name SEPARATOR ', ') as equipment_names,
            GROUP_CONCAT(DISTINCT e.id SEPARATOR ',') as equipment_ids
        FROM lab_tests_catalog l
        LEFT JOIN users u ON l.created_by = u.id
        LEFT JOIN branches b ON l.branch_id = b.id
        LEFT JOIN lab_test_equipment le ON l.id = le.lab_test_id
        LEFT JOIN medical_equipment e ON le.equipment_id = e.id
        WHERE 1=1 $branch_filter $search_filter
        GROUP BY l.id
        ORDER BY l.test_name ASC, l.price ASC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check for potential duplicates (same name, different price or branch)
    $stmt = $db->prepare("
        SELECT test_name, COUNT(*) as count, 
               GROUP_CONCAT(DISTINCT price ORDER BY price SEPARATOR '|') as prices,
               GROUP_CONCAT(DISTINCT branch_id ORDER BY branch_id SEPARATOR '|') as branches
        FROM lab_tests_catalog l
        GROUP BY test_name
        HAVING COUNT(*) > 1
    ");
    $stmt->execute();
    $duplicate_warning = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lab_tests = [];
    $duplicate_warning = [];
}

// ================================================================
// GET EQUIPMENT FOR LAB TEST (GROUPED)
// ================================================================
$equipment_for_lab = [];
try {
    $branch_id = ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) ? (int)$selected_branch_id : $user_branch_id;
    
    $stmt = $db->prepare("
        SELECT 
            MIN(e.id) as equipment_id,
            e.equipment_name,
            e.category,
            SUM(e.quantity) as total_quantity,
            GROUP_CONCAT(e.batch_number SEPARATOR '|') as batch_numbers
        FROM medical_equipment e
        WHERE (e.branch_id = ? OR e.branch_id IS NULL)
        AND e.status = 'active'
        GROUP BY e.equipment_name, e.category
        ORDER BY e.equipment_name
    ");
    $stmt->execute([$branch_id]);
    $equipment_for_lab = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $equipment_for_lab = [];
}

// ================================================================
// GET SINGLE ITEM FOR EDIT
// ================================================================
$edit_item = null;
$edit_type = null;
$current_equipment_ids = [];

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
            $stmt = $db->prepare("SELECT * FROM procedures_catalog WHERE id = ?");
            $stmt->execute([$edit_id]);
            $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    } elseif ($edit_type === 'equipment') {
        try {
            $stmt = $db->prepare("SELECT * FROM medical_equipment WHERE id = ?");
            $stmt->execute([$edit_id]);
            $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    } elseif ($edit_type === 'lab_test') {
        try {
            error_log("Loading lab test for edit: ID = $edit_id");
            
            $stmt = $db->prepare("
                SELECT l.*, 
                       GROUP_CONCAT(DISTINCT le.equipment_id SEPARATOR ',') as current_equipment_ids
                FROM lab_tests_catalog l
                LEFT JOIN lab_test_equipment le ON l.id = le.lab_test_id
                WHERE l.id = ?
                GROUP BY l.id
            ");
            $stmt->execute([$edit_id]);
            $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($edit_item) {
                if (isset($edit_item['current_equipment_ids']) && !empty($edit_item['current_equipment_ids'])) {
                    $current_equipment_ids = explode(',', $edit_item['current_equipment_ids']);
                    $current_equipment_ids = array_map('intval', $current_equipment_ids);
                } else {
                    $current_equipment_ids = [];
                }
                error_log("Current equipment IDs: " . print_r($current_equipment_ids, true));
            } else {
                error_log("Lab test NOT found with ID: $edit_id");
                $edit_item = null;
                $current_equipment_ids = [];
            }
        } catch (Exception $e) {
            error_log("Error loading lab test for edit: " . $e->getMessage());
            $edit_item = null;
            $current_equipment_ids = [];
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
        /* ================================================================
           ALL CSS STYLES
           ================================================================ */
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
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --teal: #0D9488;
            --teal-bg: #CCFBF1;
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
            --transition: all 0.3s ease;
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
            min-width: 120px;
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
            min-width: 650px;
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
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
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
            max-width: 700px;
            width: 95%;
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
        
        .badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        .badge-teal { background: var(--teal-bg); color: var(--teal); }
        
        .code-badge {
            display: inline-block;
            background: var(--gray-100);
            color: var(--gray-600);
            padding: 1px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-family: monospace;
            border: 1px solid var(--gray-300);
        }
        [data-theme="dark"] .code-badge {
            background: var(--gray-700);
            color: var(--gray-400);
            border-color: var(--gray-600);
        }
        
        .batch-number {
            font-family: monospace;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 1px 8px;
            border-radius: 4px;
            background: var(--primary-bg);
            color: var(--primary);
        }
        [data-theme="dark"] .batch-number {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .stock-badge {
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .stock-badge.ok { background: var(--success-bg); color: var(--success); }
        .stock-badge.low { background: var(--warning-bg); color: var(--warning); animation: pulse 1.5s infinite; }
        .stock-badge.out { background: var(--danger-bg); color: var(--danger); animation: pulse 1s infinite; }
        
        .expiry-badge {
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .expiry-badge.valid { background: var(--success-bg); color: var(--success); }
        .expiry-badge.expiring { background: var(--warning-bg); color: var(--warning); animation: pulse 1.5s infinite; }
        .expiry-badge.expired { background: var(--danger-bg); color: var(--danger); animation: pulse 1s infinite; }
        .expiry-badge.no-expiry { background: var(--gray-200); color: var(--gray-500); }
        
        .days-remaining {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .days-remaining.good { background: var(--success-bg); color: var(--success); }
        .days-remaining.warning { background: var(--warning-bg); color: var(--warning); animation: pulse 1.5s infinite; }
        .days-remaining.danger { background: var(--danger-bg); color: var(--danger); animation: pulse 1s infinite; }
        .days-remaining.forever { background: var(--gray-200); color: var(--gray-500); }
        
        .equipment-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 4px;
        }
        .equipment-tag {
            font-size: 0.6rem;
            background: var(--teal-bg);
            color: var(--teal);
            padding: 1px 8px;
            border-radius: 10px;
            border: 1px solid var(--teal);
        }
        
        .equipment-checkbox-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            max-height: 150px;
            overflow-y: auto;
            padding: 8px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--gray-50);
        }
        [data-theme="dark"] .equipment-checkbox-group {
            border-color: var(--gray-600);
            background: var(--gray-700);
        }
        .equipment-checkbox-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
        }
        .equipment-checkbox-item:hover {
            background: var(--primary-bg);
        }
        .equipment-checkbox-item input[type="checkbox"] {
            accent-color: var(--primary);
            width: 14px;
            height: 14px;
            cursor: pointer;
        }
        .equipment-checkbox-item .equip-qty {
            font-size: 0.6rem;
            color: var(--gray-400);
            margin-left: auto;
        }
        .equipment-checkbox-item .equip-free {
            font-size: 0.55rem;
            color: var(--success);
            font-weight: 600;
        }
        
        /* Current equipment tags in edit modal */
        .current-equipment-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin: 8px 0 12px;
            padding: 8px 12px;
            background: var(--teal-bg);
            border-radius: 8px;
            border: 1px solid var(--teal);
        }
        .current-equipment-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.7rem;
            background: var(--bg-card);
            padding: 2px 10px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
        .current-equipment-tag i {
            color: var(--teal);
        }
        
        /* Duplicate warning - will disappear after 5 seconds */
        .duplicate-warning {
            background: var(--warning-bg);
            color: var(--warning);
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid var(--warning);
            font-size: 0.8rem;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: opacity 0.8s ease;
        }
        .duplicate-warning i {
            font-size: 1rem;
        }
        .duplicate-warning.hidden {
            opacity: 0;
            display: none;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        [data-theme="dark"] .footer { border-color: var(--gray-700); }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .form-row-2, .form-row-3 { grid-template-columns: 1fr; }
            .tabs { flex-direction: column; }
            .tab-btn { flex: none; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .modal-content { padding: 20px; }
            .table-container table { font-size: 0.75rem; }
            .table-container thead th, .table-container tbody td { padding: 8px 10px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-row { grid-template-columns: 1fr; }
            .modal-content { padding: 15px; }
            .equipment-checkbox-group { grid-template-columns: 1fr; }
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
                <?php if ($selected_branch_id !== 'all'): ?>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($selected_branch_name) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-list"></i>
                Manage <strong>Services</strong>, <strong>Procedures</strong>, <strong>Equipment</strong> & <strong>Lab Tests</strong>
                <span class="header-badge">
                    <i class="fas fa-tag"></i> Total: <?= count($services) + count($procedures) + count($equipment) + count($lab_tests) ?> items
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
            <button onclick="openAddModal('equipment')" class="btn-outline-light">
                <i class="fas fa-tools"></i> Add Equipment
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
                <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                        🏥 <?= htmlspecialchars($b['name']) ?>
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
            <div class="stat-number" style="color:#D97706;"><?= count($equipment) ?></div>
            <div class="stat-label">Equipment</div>
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
    <!-- DUPLICATE WARNING - DISAPPEARS AFTER 5 SECONDS -->
    <!-- ================================================================ -->
    <?php if (!empty($duplicate_warning)): ?>
        <div class="duplicate-warning" id="duplicateWarning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Duplicate Tests Detected:</strong>
            <?php foreach ($duplicate_warning as $dup): ?>
                <span style="margin-left:8px;">
                    "<?= htmlspecialchars($dup['test_name']) ?>" 
                    (<strong><?= $dup['count'] ?></strong> entries)
                </span>
            <?php endforeach; ?>
            <span style="margin-left:8px;font-size:0.7rem;">
                <i class="fas fa-info-circle"></i> Same name with different price or branch is allowed.
            </span>
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
        <button class="tab-btn <?= $active_tab === 'equipment' ? 'active' : '' ?>" data-tab="equipment">
            <i class="fas fa-tools"></i> Equipment (<?= count($equipment) ?>)
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
                <h3 class="card-title"><i class="fas fa-list"></i> All Services</h3>
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
                                        <span class="badge badge-info">
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
                        <p>No services found.</p>
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
    <!-- TAB 3: EQUIPMENT (GROUPED) -->
    <!-- ================================================================ -->
    <div class="tab-content <?= $active_tab === 'equipment' ? 'active' : '' ?>" id="tab-equipment">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-tools"></i> Medical Equipment</h3>
                <button onclick="openAddModal('equipment')" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add Equipment
                </button>
            </div>
            <div class="table-container">
                <?php if (count($equipment) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Equipment Name</th>
                                <th>Category</th>
                                <th>Branch</th>
                                <th>Total Qty</th>
                                <th>Stock</th>
                                <th>Price</th>
                                <th>Expiry</th>
                                <th>Batches</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($equipment as $item): 
                                $stock = getStockStatus($item['total_quantity'] ?? 0, $item['reorder_level'] ?? 5);
                                $expiry = getExpiryStatus($item['expiry_date'] ?? '');
                                $batch_count = $item['batch_numbers'] ? count(explode('|', $item['batch_numbers'])) : 0;
                                $first_batch = $item['batch_numbers'] ? explode('|', $item['batch_numbers'])[0] : '';
                            ?>
                                <tr data-id="<?= $item['equipment_id'] ?>">
                                    <td><?= $i++ ?></td>
                                    <td><strong><?= htmlspecialchars($item['equipment_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if ($item['branch_id'] === null): ?>
                                            <span class="branch-tag all-branches">🌐 All Branches</span>
                                        <?php else: ?>
                                            <span class="branch-tag"><?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;"><strong><?= number_format($item['total_quantity'] ?? 0) ?></strong></td>
                                    <td>
                                        <span class="stock-badge <?= $stock['class'] ?>">
                                            <i class="fas <?= $stock['class'] === 'ok' ? 'fa-check-circle' : ($stock['class'] === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                                            <?= $stock['label'] ?>
                                        </span>
                                    </td>
                                    <td class="price-display">
                                        <?= ($item['selling_price'] ?? 0) > 0 ? 'TSh ' . number_format($item['selling_price'], 0) : 'FREE' ?>
                                    </td>
                                    <td>
                                        <span class="expiry-badge <?= $expiry['class'] ?>">
                                            <?= $expiry['label'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($first_batch)): ?>
                                            <span class="batch-number"><?= htmlspecialchars($first_batch) ?></span>
                                            <?php if ($batch_count > 1): ?>
                                                <span style="font-size:0.6rem;color:var(--gray-400);">+<?= $batch_count - 1; ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= ($item['computed_status'] ?? 'active') === 'active' ? 'active' : 'inactive' ?>">
                                            <?= ucfirst($item['computed_status'] ?? 'active') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn-icon view" onclick="viewItem('equipment', <?= $item['equipment_id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="?edit=<?= $item['equipment_id'] ?>&type=equipment&branch=<?= urlencode($selected_branch_id) ?>" class="btn-icon edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn-icon delete" onclick="deleteItem('equipment', <?= $item['equipment_id'] ?>, '<?= addslashes($item['equipment_name']) ?>')">
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
                        <p>No equipment found.</p>
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
                                <th>Equipment (FREE)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($lab_tests as $l): 
                                $eq_names = $l['equipment_names'] ?? '';
                                $eq_arr = !empty($eq_names) ? explode(', ', $eq_names) : [];
                            ?>
                                <tr data-id="<?= $l['id'] ?>">
                                    <td><?= $i++ ?></td>
                                    <td><strong><?= htmlspecialchars($l['test_name']) ?></strong></td>
                                    <td>
                                        <?php if (!empty($l['category'])): ?>
                                            <span class="badge badge-purple"><?= htmlspecialchars($l['category']) ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-info">Uncategorized</span>
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
                                        <?php if (!empty($eq_arr)): ?>
                                            <div class="equipment-tags">
                                                <?php foreach ($eq_arr as $eq): ?>
                                                    <span class="equipment-tag"><i class="fas fa-tools"></i> <?= htmlspecialchars($eq) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size:0.7rem;">No equipment</span>
                                        <?php endif; ?>
                                    </td>
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
    <!-- EDIT MODAL - FIXED: Uses 'test_id' as field name -->
    <!-- ================================================================ -->
    <?php if ($edit_item && isset($edit_type)): ?>
    <div class="modal-overlay show" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit <?= ucfirst($edit_type) ?></h2>
                <a href="?branch=<?= urlencode($selected_branch_id) ?>&tab=<?= $active_tab ?>" class="modal-close">
                    <i class="fas fa-times"></i>
                </a>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update_<?= $edit_type ?>">
                
                <!-- ✅ FIX: Use 'test_id' for lab test, and 'service_id', 'procedure_id', 'equipment_id' for others -->
                <?php if ($edit_type === 'lab_test'): ?>
                    <input type="hidden" name="test_id" value="<?= $edit_item['id'] ?>">
                <?php elseif ($edit_type === 'service'): ?>
                    <input type="hidden" name="service_id" value="<?= $edit_item['id'] ?>">
                <?php elseif ($edit_type === 'procedure'): ?>
                    <input type="hidden" name="procedure_id" value="<?= $edit_item['id'] ?>">
                <?php elseif ($edit_type === 'equipment'): ?>
                    <input type="hidden" name="equipment_id" value="<?= $edit_item['id'] ?>">
                <?php endif; ?>
                
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
                        <select name="category_id" class="form-control">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $edit_item['category'] == $cat['category_name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="0">-- Other (Type manually) --</option>
                        </select>
                        <input type="text" name="category_name" class="form-control" style="margin-top:4px;display:none;" placeholder="Enter custom category..." value="<?= htmlspecialchars($edit_item['category'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($edit_item['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">Price (TSh) <span class="required">*</span></label>
                            <input type="text" name="price" class="form-control price-input" value="<?= number_format($edit_item['price'] ?? 0, 0) ?>" required oninput="formatPriceInput(this)">
                        </div>
                        <div class="form-group" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="checkbox" name="is_active" value="1" <?= $edit_item['is_active'] ? 'checked' : '' ?>>
                                <span>Active</span>
                            </label>
                        </div>
                    </div>
                <?php elseif ($edit_type === 'equipment'): ?>
                    <div class="form-group">
                        <label class="form-label">Equipment Name <span class="required">*</span></label>
                        <input type="text" name="equipment_name" class="form-control" value="<?= htmlspecialchars($edit_item['equipment_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-control">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $edit_item['category'] == $cat['category_name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="0">-- Other (Type manually) --</option>
                        </select>
                        <input type="text" name="category_name" class="form-control" style="margin-top:4px;display:none;" placeholder="Enter custom category..." value="<?= htmlspecialchars($edit_item['category'] ?? '') ?>">
                    </div>
                    <div class="form-row-3">
                        <div class="form-group">
                            <label class="form-label">Unit</label>
                            <select name="unit" class="form-control">
                                <option value="pcs" <?= $edit_item['unit'] === 'pcs' ? 'selected' : '' ?>>Pieces (pcs)</option>
                                <option value="box" <?= $edit_item['unit'] === 'box' ? 'selected' : '' ?>>Box</option>
                                <option value="pack" <?= $edit_item['unit'] === 'pack' ? 'selected' : '' ?>>Pack</option>
                                <option value="set" <?= $edit_item['unit'] === 'set' ? 'selected' : '' ?>>Set</option>
                                <option value="each" <?= $edit_item['unit'] === 'each' ? 'selected' : '' ?>>Each</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Quantity <span class="required">*</span></label>
                            <input type="number" name="quantity" class="form-control" required min="0" value="<?= $edit_item['quantity'] ?? 0 ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Reorder Level <span class="required">*</span></label>
                            <input type="number" name="reorder_level" class="form-control" required min="0" value="<?= $edit_item['reorder_level'] ?? 5 ?>">
                        </div>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">Selling Price (TSh)</label>
                            <input type="text" name="selling_price" class="form-control price-input" value="<?= number_format($edit_item['selling_price'] ?? 0, 0) ?>" oninput="formatPriceInput(this)">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Supplier</label>
                            <input type="text" name="supplier" class="form-control" value="<?= htmlspecialchars($edit_item['supplier'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control" value="<?= $edit_item['expiry_date'] ?? '' ?>">
                        </div>
                        <div class="form-group" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="checkbox" name="is_active" value="1" <?= ($edit_item['status'] ?? 'active') === 'active' ? 'checked' : '' ?>>
                                <span>Active</span>
                            </label>
                            <input type="hidden" name="status" value="<?= ($edit_item['status'] ?? 'active') === 'active' ? 'active' : 'inactive' ?>">
                        </div>
                    </div>
                <?php elseif ($edit_type === 'lab_test'): ?>
                    <!-- Lab Test Edit - FIXED -->
                    <div class="form-group">
                        <label class="form-label">Test Name <span class="required">*</span></label>
                        <input type="text" name="test_name" class="form-control" value="<?= htmlspecialchars($edit_item['test_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-control">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $edit_item['category'] == $cat['category_name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="0">-- Other (Type manually) --</option>
                        </select>
                        <input type="text" name="category_name" class="form-control" style="margin-top:4px;display:none;" placeholder="Enter custom category..." value="<?= htmlspecialchars($edit_item['category'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($edit_item['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">Price (TSh) <span class="required">*</span></label>
                            <input type="text" name="price" class="form-control price-input" value="<?= number_format($edit_item['price'] ?? 0, 0) ?>" required oninput="formatPriceInput(this)">
                        </div>
                        <div class="form-group" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="checkbox" name="is_active" value="1" <?= $edit_item['is_active'] ? 'checked' : '' ?>>
                                <span>Active</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Equipment Selection -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-tools"></i> Select Equipment (FREE)
                            <span style="font-size:0.6rem;font-weight:400;color:var(--gray-400);">Equipment price is NOT added to test price</span>
                        </label>
                        
                        <?php if (!empty($current_equipment_ids)): ?>
                            <div class="current-equipment-tags">
                                <span style="font-weight:600;font-size:0.7rem;color:var(--teal);">
                                    <i class="fas fa-link"></i> Currently Linked Equipment:
                                </span>
                                <?php 
                                    $current_names = [];
                                    if (!empty($current_equipment_ids)) {
                                        $placeholders = implode(',', array_fill(0, count($current_equipment_ids), '?'));
                                        $stmt = $db->prepare("SELECT id, equipment_name FROM medical_equipment WHERE id IN ($placeholders)");
                                        $stmt->execute($current_equipment_ids);
                                        $current_eq = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($current_eq as $eq) {
                                            echo '<span class="current-equipment-tag"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($eq['equipment_name']) . '</span>';
                                        }
                                    }
                                    if (empty($current_names)) {
                                        echo '<span class="text-muted" style="font-size:0.7rem;">No equipment currently linked</span>';
                                    }
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php 
                        $equip_branch = ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) ? (int)$selected_branch_id : $user_branch_id;
                        $equipment_for_lab_edit = [];
                        try {
                            $stmt = $db->prepare("
                                SELECT id, equipment_name, quantity 
                                FROM medical_equipment 
                                WHERE (branch_id = ? OR branch_id IS NULL)
                                AND status = 'active'
                                ORDER BY equipment_name
                            ");
                            $stmt->execute([$equip_branch]);
                            $equipment_for_lab_edit = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            $equipment_for_lab_edit = [];
                        }
                        ?>
                        
                        <?php if (count($equipment_for_lab_edit) > 0): ?>
                            <div class="equipment-checkbox-group">
                                <?php foreach ($equipment_for_lab_edit as $eq): 
                                    $checked = in_array($eq['id'], $current_equipment_ids) ? 'checked' : '';
                                ?>
                                    <label class="equipment-checkbox-item">
                                        <input type="checkbox" name="equipment_ids[]" value="<?= $eq['id'] ?>" <?= $checked ?>>
                                        <?= htmlspecialchars($eq['equipment_name']) ?>
                                        <span class="equip-qty">(<?= $eq['quantity'] ?? 0 ?> in stock)</span>
                                        <span class="equip-free"><?= $checked ? '✅ Selected' : 'FREE' ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div style="font-size:0.6rem;color:var(--gray-400);margin-top:4px;">
                                <i class="fas fa-info-circle"></i> 
                                <?php if (!empty($current_equipment_ids)): ?>
                                    Uncheck to remove equipment, check to add new equipment.
                                <?php else: ?>
                                    Check equipment to link to this test.
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div style="padding:10px;background:var(--warning-bg);border-radius:8px;color:var(--warning);font-size:0.8rem;">
                                <i class="fas fa-exclamation-triangle"></i> No equipment available for this branch. Please add equipment first.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="modal-actions">
                    <a href="?branch=<?= urlencode($selected_branch_id) ?>&tab=<?= $active_tab ?>" class="btn btn-outline">Cancel</a>
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
    // FORMAT PRICE INPUT
    // ================================================================
    function formatPriceInput(input) {
        var raw = input.value.replace(/[^0-9]/g, '');
        if (raw === '') {
            input.value = '';
            return;
        }
        var formatted = parseInt(raw).toLocaleString('en-US');
        input.value = formatted;
    }

    // ================================================================
    // DUPLICATE WARNING - DISAPPEAR AFTER 5 SECONDS
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var duplicateWarning = document.getElementById('duplicateWarning');
        if (duplicateWarning) {
            setTimeout(function() {
                duplicateWarning.style.transition = 'opacity 0.8s ease';
                duplicateWarning.style.opacity = '0';
                setTimeout(function() {
                    duplicateWarning.style.display = 'none';
                }, 800);
            }, 5000);
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
            'equipment': 'Equipment',
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
                    <label class="form-label">Category <span class="required">*</span></label>
                    <select name="category_id" class="form-control" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                        <option value="0">-- Other (Type manually) --</option>
                    </select>
                    <input type="text" name="category_name" class="form-control" style="margin-top:4px;display:none;" placeholder="Enter custom category...">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Procedure description..."></textarea>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Price (TSh) <span class="required">*</span></label>
                        <input type="text" name="price" class="form-control price-input" placeholder="e.g. 150000" required oninput="formatPriceInput(this)">
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span>Active</span>
                        </label>
                    </div>
                </div>
                <div class="form-group" style="background:var(--gray-50);padding:10px 14px;border-radius:8px;font-size:0.75rem;color:var(--gray-500);">
                    <i class="fas fa-info-circle"></i> Procedure code will be generated automatically
                </div>
            `;
        } else if (type === 'equipment') {
            html = `
                <div class="form-group">
                    <label class="form-label">Equipment Name <span class="required">*</span></label>
                    <input type="text" name="equipment_name" class="form-control" required placeholder="e.g. Sindano (Syringe)">
                </div>
                <div class="form-group">
                    <label class="form-label">Category <span class="required">*</span></label>
                    <select name="category_id" class="form-control" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                        <option value="0">-- Other (Type manually) --</option>
                    </select>
                    <input type="text" name="category_name" class="form-control" style="margin-top:4px;display:none;" placeholder="Enter custom category...">
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">Unit</label>
                        <select name="unit" class="form-control">
                            <option value="pcs">Pieces (pcs)</option>
                            <option value="box">Box</option>
                            <option value="pack">Pack</option>
                            <option value="set">Set</option>
                            <option value="each">Each</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantity <span class="required">*</span></label>
                        <input type="number" name="quantity" class="form-control" required min="0" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reorder Level <span class="required">*</span></label>
                        <input type="number" name="reorder_level" class="form-control" required min="0" value="5">
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Selling Price (TSh)</label>
                        <input type="text" name="selling_price" class="form-control price-input" placeholder="0 = FREE" value="0" oninput="formatPriceInput(this)">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Supplier</label>
                        <input type="text" name="supplier" class="form-control" placeholder="Supplier name">
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Batch Number</label>
                        <input type="text" name="batch_number" class="form-control" placeholder="Auto-generated if left empty">
                    </div>
                </div>
                <div class="form-group" style="background:var(--gray-50);padding:10px 14px;border-radius:8px;font-size:0.75rem;color:var(--gray-500);">
                    <i class="fas fa-info-circle"></i> Status will be <strong>Active</strong> by default. Leave expiry empty for no expiry (Active Forever).
                </div>
            `;
        } else if (type === 'lab_test') {
            html = `
                <div class="form-group">
                    <label class="form-label">Test Name <span class="required">*</span></label>
                    <input type="text" name="test_name" class="form-control" required placeholder="e.g. Complete Blood Count">
                </div>
                <div class="form-group">
                    <label class="form-label">Category <span class="required">*</span></label>
                    <select name="category_id" class="form-control" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                        <option value="0">-- Other (Type manually) --</option>
                    </select>
                    <input type="text" name="category_name" class="form-control" style="margin-top:4px;display:none;" placeholder="Enter custom category...">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Test description..."></textarea>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Price (TSh) <span class="required">*</span></label>
                        <input type="text" name="price" class="form-control price-input" placeholder="e.g. 5000" required oninput="formatPriceInput(this)">
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span>Active</span>
                        </label>
                    </div>
                </div>
                <!-- Equipment Selection -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-tools"></i> Select Equipment (FREE)
                        <span style="font-size:0.6rem;font-weight:400;color:var(--gray-400);">Equipment price is NOT added to test price</span>
                    </label>
                    <?php 
                    $equip_branch = ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) ? (int)$selected_branch_id : $user_branch_id;
                    $equipment_for_add = [];
                    try {
                        $stmt = $db->prepare("
                            SELECT id, equipment_name, quantity 
                            FROM medical_equipment 
                            WHERE (branch_id = ? OR branch_id IS NULL)
                            AND status = 'active'
                            ORDER BY equipment_name
                        ");
                        $stmt->execute([$equip_branch]);
                        $equipment_for_add = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {}
                    ?>
                    <?php if (count($equipment_for_add) > 0): ?>
                        <div class="equipment-checkbox-group">
                            <?php foreach ($equipment_for_add as $eq): ?>
                                <label class="equipment-checkbox-item">
                                    <input type="checkbox" name="equipment_ids[]" value="<?= $eq['id'] ?>">
                                    <?= htmlspecialchars($eq['equipment_name']) ?>
                                    <span class="equip-qty">(<?= $eq['quantity'] ?? 0 ?> in stock)</span>
                                    <span class="equip-free">FREE</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="padding:10px;background:var(--warning-bg);border-radius:8px;color:var(--warning);font-size:0.8rem;">
                            <i class="fas fa-exclamation-triangle"></i> No equipment available. Please add equipment first.
                        </div>
                    <?php endif; ?>
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
            'equipment': 'Equipment',
            'lab_test': 'Lab Test'
        };
        
        title.innerHTML = '<i class="fas fa-eye"></i> ' + typeLabels[type] + ' Details';
        
        var tabId = 'tab-' + type + (type === 'procedure' ? 's' : (type === 'equipment' ? '' : (type === 'lab_test' ? 's' : 's')));
        if (type === 'equipment') tabId = 'tab-equipment';
        else if (type === 'lab_test') tabId = 'tab-lab_tests';
        else if (type === 'procedure') tabId = 'tab-procedures';
        else if (type === 'service') tabId = 'tab-services';
        
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
        var labels = ['ID', 'Name', 'Category', 'Branch', 'Price/Quantity', 'Stock', 'Expiry', 'Status'];
        var html = '<div class="space-y-2">';
        
        var dataIndex = 0;
        for (var i = 1; i < cells.length && dataIndex < labels.length; i++) {
            var label = labels[dataIndex] || 'Field';
            var value = cells[i]?.textContent?.trim() || 'N/A';
            
            if (label === 'Status' && (value.includes('Active') || value.includes('Inactive'))) {
                var isActive = value.includes('Active');
                value = '<span class="status-badge ' + (isActive ? 'active' : 'inactive') + '">' + value.trim() + '</span>';
            }
            
            if (label === 'Branch' && value.includes('All Branches')) {
                value = '<span class="branch-tag all-branches">🌐 ' + value + '</span>';
            } else if (label === 'Branch' && !value.includes('All Branches') && value !== 'N/A') {
                value = '<span class="branch-tag">🏥 ' + value + '</span>';
            }
            
            if (label === 'Stock' && (value.includes('In Stock') || value.includes('Low Stock') || value.includes('Out of Stock'))) {
                var stockClass = value.includes('In Stock') ? 'ok' : (value.includes('Low Stock') ? 'low' : 'out');
                var icon = value.includes('In Stock') ? 'fa-check-circle' : (value.includes('Low Stock') ? 'fa-exclamation-triangle' : 'fa-times-circle');
                value = '<span class="stock-badge ' + stockClass + '"><i class="fas ' + icon + '"></i> ' + value + '</span>';
            }
            
            if (label === 'Expiry' && (value.includes('Valid') || value.includes('Expiring') || value.includes('Expired') || value.includes('No Expiry'))) {
                var expiryClass = value.includes('Valid') ? 'valid' : (value.includes('Expiring') ? 'expiring' : (value.includes('Expired') ? 'expired' : 'no-expiry'));
                value = '<span class="expiry-badge ' + expiryClass + '">' + value + '</span>';
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
    // CATEGORY - Toggle manual input
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('select[name="category_id"]').forEach(function(select) {
            select.addEventListener('change', function() {
                var manualInput = this.closest('.form-group').querySelector('input[name="category_name"]');
                if (manualInput) {
                    if (this.value === '0') {
                        manualInput.style.display = 'block';
                        manualInput.required = true;
                        manualInput.focus();
                    } else {
                        manualInput.style.display = 'none';
                        manualInput.required = false;
                        manualInput.value = '';
                    }
                }
            });
        });
    });

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

    console.log('%c🛠️ Services Management - FULL ADMIN', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= $selected_branch_name ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Full CRUD: Add, View, Edit, Delete', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Duplicate Check for Lab Tests', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Duplicate Warning disappears after 5 seconds', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Lab Test Edit: Can add/remove equipment (FIXED)', 'font-size:13px; color:#34D399;');
    console.log('%c🔧 Equipment: <?= count($equipment) ?> | Lab Tests: <?= count($lab_tests) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>