<?php
// ================================================================
// FILE: frontend/pages/admin/services.php
// ADMIN - SERVICES MANAGEMENT
// ✅ Uses SHARED header & sidebar (NO DUPLICATES)
// ✅ Blue theme + full dark mode support via --page-* variables
// ✅ Full CRUD: Services, Procedures, Equipment, Lab Tests
// ✅ Duplicate check for Lab Tests
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

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// PARAMETERS
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'services';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $branches = []; }

$selected_branch_name = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    foreach ($branches as $b) {
        if ($b['id'] == $selected_branch_id) { $selected_branch_name = $b['name']; break; }
    }
}

// ================================================================
// GET CATEGORIES
// ================================================================
$categories = [];
try {
    $stmt = $db->query("SELECT id, category_name FROM service_categories ORDER BY category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $categories = []; }

// ================================================================
// HELPERS
// ================================================================
function cleanPrice($price) {
    $price = str_replace(',', '', $price);
    $price = str_replace(' ', '', $price);
    $price = preg_replace('/[^0-9.]/', '', $price);
    return (float)$price;
}

function generateProcedureCode($db, $branch_id) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM procedures_catalog WHERE branch_id = ? OR branch_id IS NULL");
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
        $stmt = $db->prepare("SELECT batch_number FROM medical_equipment WHERE equipment_name = ? AND branch_id = ? ORDER BY id DESC LIMIT 1");
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

function getBranchName($db, $branch_id) {
    if ($branch_id === null || $branch_id === '' || $branch_id === 'all') return 'All Branches';
    try {
        $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
        $stmt->execute([$branch_id]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        return $branch ? $branch['name'] : 'Unknown Branch (ID: ' . $branch_id . ')';
    } catch (Exception $e) { return 'Unknown Branch'; }
}

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
    
    $sql = "SELECT id, test_name, category, branch_id, price FROM lab_tests_catalog 
        WHERE test_name = ? AND category = ? $branch_condition AND price = ? $exclude_condition LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ================================================================
// HANDLE POST REQUESTS (CRUD)
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $branch_id = $_POST['branch_id'] ?? null;
    
    if ($branch_id === 'all' || $branch_id === '' || $branch_id === 'NULL') $branch_id = null;
    elseif (is_numeric($branch_id)) $branch_id = (int)$branch_id;
    else $branch_id = null;
    
    // ADD SERVICE
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
                $stmt = $db->prepare("INSERT INTO services (service_name, category_id, description, branch_id, price, is_active, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$service_name, $category_id, $description, $branch_id, $price, $is_active, $user_id]);
                $message = "✅ Service added successfully! Price: TSh " . number_format($price, 0);
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // UPDATE SERVICE
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
                $stmt = $db->prepare("UPDATE services SET service_name = ?, category_id = ?, description = ?, branch_id = ?, price = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$service_name, $category_id, $description, $branch_id, $price, $is_active, $service_id]);
                $message = "✅ Service updated successfully! Price: TSh " . number_format($price, 0);
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // DELETE SERVICE
    if ($action === 'delete_service') {
        $service_id = isset($_POST['delete_id']) ? (int)$_POST['delete_id'] : 0;
        if ($service_id <= 0) {
            $message = "❌ Invalid service ID";
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("SELECT id, service_name FROM services WHERE id = ?");
                $stmt->execute([$service_id]);
                $service = $stmt->fetch(PDO::FETCH_ASSOC);
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
    
    // ADD PROCEDURE
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
                if ($cat['id'] == $category_id) { $final_category = $cat['category_name']; break; }
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
                $stmt = $db->prepare("INSERT INTO procedures_catalog (procedure_name, procedure_code, category, branch_id, price, description, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$procedure_name, $procedure_code, $final_category, $branch_id, $price, $description, $is_active, $user_id]);
                $message = "✅ Procedure added successfully! Code: " . $procedure_code . " | Price: TSh " . number_format($price, 0);
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // UPDATE PROCEDURE
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
                if ($cat['id'] == $category_id) { $final_category = $cat['category_name']; break; }
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
                $stmt = $db->prepare("UPDATE procedures_catalog SET procedure_name = ?, category = ?, price = ?, description = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$procedure_name, $final_category, $price, $description, $is_active, $procedure_id]);
                $message = "✅ Procedure updated successfully! Price: TSh " . number_format($price, 0);
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // DELETE PROCEDURE
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
    
    // ADD EQUIPMENT
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
                if ($cat['id'] == $category_id) { $final_category = $cat['category_name']; break; }
            }
        } elseif (!empty($category_name)) {
            $final_category = $category_name;
        }
        
        if (empty($batch_number)) {
            $batch_number = generateEquipmentBatch($db, $equipment_name, $branch_id);
        }
        
        $errors = [];
        if (empty($equipment_name)) $errors[] = 'Equipment name is required';
        if ($quantity < 0) $errors[] = 'Quantity cannot be negative';
        if ($selling_price < 0) $errors[] = 'Selling price cannot be negative';
        if (!empty($expiry_date) && strtotime($expiry_date) < strtotime(date('Y-m-d'))) $errors[] = 'Expiry date cannot be in the past';
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("INSERT INTO medical_equipment (equipment_name, category, unit, quantity, reorder_level, selling_price, supplier, expiry_date, batch_number, branch_id, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$equipment_name, $final_category, $unit, $quantity, $reorder_level, $selling_price, $supplier, $expiry_date, $batch_number, $branch_id, $status, $user_id]);
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
    
    // UPDATE EQUIPMENT
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
                if ($cat['id'] == $category_id) { $final_category = $cat['category_name']; break; }
            }
        } elseif (!empty($category_name)) {
            $final_category = $category_name;
        }
        
        if ($equipment_id <= 0 || empty($equipment_name)) {
            $message = "❌ Invalid equipment data";
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("UPDATE medical_equipment SET equipment_name = ?, category = ?, unit = ?, quantity = ?, reorder_level = ?, selling_price = ?, supplier = ?, expiry_date = ?, status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$equipment_name, $final_category, $unit, $quantity, $reorder_level, $selling_price, $supplier, $expiry_date, $status, $equipment_id]);
                $message = "✅ Equipment updated successfully!";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // DELETE EQUIPMENT
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
    
    // ADD LAB TEST
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
                if ($cat['id'] == $category_id) { $final_category = $cat['category_name']; break; }
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
                $duplicate = checkLabTestDuplicate($db, $test_name, $final_category, $branch_id, $price);
                
                if ($duplicate) {
                    $message = "❌ Duplicate test found!<br>";
                    $message .= "Test '<strong>" . htmlspecialchars($duplicate['test_name']) . "</strong>' already exists";
                    $message_type = 'error';
                } else {
                    $db->beginTransaction();
                    $stmt = $db->prepare("INSERT INTO lab_tests_catalog (test_name, category, branch_id, price, description, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$test_name, $final_category, $branch_id, $price, $description, $is_active, $user_id]);
                    $test_id = $db->lastInsertId();
                    
                    if (!empty($equipment_ids)) {
                        $equipment_ids = array_map('intval', $equipment_ids);
                        foreach ($equipment_ids as $equip_id) {
                            $stmt = $db->prepare("SELECT id FROM medical_equipment WHERE id = ?");
                            $stmt->execute([$equip_id]);
                            if ($stmt->fetch()) {
                                $stmt = $db->prepare("INSERT INTO lab_test_equipment (lab_test_id, equipment_id, branch_id, created_at) VALUES (?, ?, ?, NOW())");
                                $stmt->execute([$test_id, $equip_id, $branch_id]);
                            }
                        }
                    }
                    $db->commit();
                    
                    $equipment_text = !empty($equipment_ids) ? ' with ' . count($equipment_ids) . ' equipment(s) linked (FREE)' : '';
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
    
    // UPDATE LAB TEST
    if ($action === 'update_lab_test') {
        $test_id = 0;
        if (isset($_POST['test_id']) && is_numeric($_POST['test_id'])) $test_id = (int)$_POST['test_id'];
        elseif (isset($_POST['lab_test_id']) && is_numeric($_POST['lab_test_id'])) $test_id = (int)$_POST['lab_test_id'];
        
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
                if ($cat['id'] == $category_id) { $final_category = $cat['category_name']; break; }
            }
        } elseif (!empty($category_name)) {
            $final_category = $category_name;
        }
        
        if ($test_id <= 0) {
            $message = "❌ Invalid test ID";
            $message_type = 'error';
        } elseif (empty($test_name)) {
            $message = "❌ Test name is required";
            $message_type = 'error';
        } else {
            try {
                $check_stmt = $db->prepare("SELECT id, branch_id FROM lab_tests_catalog WHERE id = ?");
                $check_stmt->execute([$test_id]);
                $existing_test = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$existing_test) {
                    $message = "❌ Test not found";
                    $message_type = 'error';
                } else {
                    if ($branch_id === null && $existing_test['branch_id'] !== null) $branch_id = $existing_test['branch_id'];
                    $duplicate = checkLabTestDuplicate($db, $test_name, $final_category, $branch_id, $price, $test_id);
                    
                    if ($duplicate) {
                        $message = "❌ Duplicate test found!";
                        $message_type = 'error';
                    } else {
                        $db->beginTransaction();
                        $stmt = $db->prepare("UPDATE lab_tests_catalog SET test_name = ?, category = ?, price = ?, description = ?, is_active = ?, branch_id = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$test_name, $final_category, $price, $description, $is_active, $branch_id, $test_id]);
                        
                        $stmt = $db->prepare("DELETE FROM lab_test_equipment WHERE lab_test_id = ?");
                        $stmt->execute([$test_id]);
                        
                        if (!empty($equipment_ids)) {
                            $equipment_ids = array_map('intval', $equipment_ids);
                            foreach ($equipment_ids as $equip_id) {
                                $stmt = $db->prepare("SELECT id FROM medical_equipment WHERE id = ?");
                                $stmt->execute([$equip_id]);
                                if ($stmt->fetch()) {
                                    $stmt = $db->prepare("INSERT INTO lab_test_equipment (lab_test_id, equipment_id, branch_id, created_at) VALUES (?, ?, ?, NOW())");
                                    $stmt->execute([$test_id, $equip_id, $branch_id]);
                                }
                            }
                        }
                        $db->commit();
                        
                        $equipment_text = !empty($equipment_ids) ? ' with ' . count($equipment_ids) . ' equipment(s) linked' : '';
                        $message = "✅ Lab test updated successfully! Price: TSh " . number_format($price, 0) . $equipment_text;
                        $message_type = 'success';
                        
                        echo '<script>setTimeout(function() { window.location.href = "services.php?branch=' . urlencode($selected_branch_id) . '&tab=lab_tests&success=1"; }, 2000);</script>';
                    }
                }
            } catch (Exception $e) {
                if (isset($db)) $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // DELETE LAB TEST
    if ($action === 'delete_lab_test') {
        $test_id = isset($_POST['delete_id']) ? (int)$_POST['delete_id'] : 0;
        if ($test_id <= 0) {
            $message = "❌ Invalid test ID";
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM lab_test_equipment WHERE lab_test_id = ?");
                $stmt->execute([$test_id]);
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
$services = [];
try {
    $branch_filter = buildBranchFilter($selected_branch_id, 's');
    $search_filter = buildSearchFilter($search, ['s.service_name', 's.description']);
    $query = "SELECT s.*, c.category_name, b.name as branch_name, u.full_name as created_by_name
        FROM services s
        LEFT JOIN service_categories c ON s.category_id = c.id
        LEFT JOIN branches b ON s.branch_id = b.id
        LEFT JOIN users u ON s.created_by = u.id
        WHERE 1=1 $branch_filter $search_filter
        ORDER BY s.created_at DESC, s.service_name ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $services = []; }

$procedures = [];
try {
    $branch_filter = buildBranchFilter($selected_branch_id, 'p');
    $search_filter = buildSearchFilter($search, ['p.procedure_name', 'p.category', 'p.description']);
    $query = "SELECT p.*, b.name as branch_name, u.full_name as created_by_name
        FROM procedures_catalog p
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE 1=1 $branch_filter $search_filter
        ORDER BY p.procedure_name ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $procedures = []; }

$equipment = [];
try {
    $branch_filter = buildBranchFilter($selected_branch_id, 'e');
    $search_filter = buildSearchFilter($search, ['e.equipment_name', 'e.category']);
    $query = "SELECT MIN(e.id) as equipment_id, e.equipment_name, e.category, e.unit, e.branch_id,
        SUM(e.quantity) as total_quantity, MIN(e.reorder_level) as reorder_level,
        MIN(e.selling_price) as selling_price, MIN(e.supplier) as supplier,
        MIN(e.expiry_date) as expiry_date, GROUP_CONCAT(e.id) as batch_ids,
        GROUP_CONCAT(e.batch_number SEPARATOR '|') as batch_numbers,
        GROUP_CONCAT(e.quantity SEPARATOR '|') as batch_quantities,
        GROUP_CONCAT(e.expiry_date SEPARATOR '|') as batch_expiries,
        MIN(DATEDIFF(e.expiry_date, CURDATE())) as days_remaining,
        u.full_name as created_by_name, b.name as branch_name,
        CASE WHEN SUM(e.quantity) <= 0 THEN 'inactive'
             WHEN MIN(e.expiry_date) IS NULL OR MIN(e.expiry_date) = '0000-00-00' THEN 'active'
             WHEN SUM(CASE WHEN e.status = 'active' AND (e.expiry_date IS NULL OR e.expiry_date >= CURDATE()) THEN 1 ELSE 0 END) > 0 THEN 'active'
             ELSE 'inactive' END as computed_status
        FROM medical_equipment e
        LEFT JOIN users u ON e.created_by = u.id
        LEFT JOIN branches b ON e.branch_id = b.id
        WHERE 1=1 $branch_filter $search_filter
        GROUP BY e.equipment_name, e.category, e.unit, e.branch_id
        ORDER BY e.equipment_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $equipment = []; }

$lab_tests = [];
$duplicate_warning = [];
try {
    $branch_filter = buildBranchFilter($selected_branch_id, 'l');
    $search_filter = buildSearchFilter($search, ['l.test_name', 'l.category']);
    $query = "SELECT l.*, u.full_name as created_by_name, b.name as branch_name,
        GROUP_CONCAT(DISTINCT e.equipment_name SEPARATOR ', ') as equipment_names,
        GROUP_CONCAT(DISTINCT e.id SEPARATOR ',') as equipment_ids
        FROM lab_tests_catalog l
        LEFT JOIN users u ON l.created_by = u.id
        LEFT JOIN branches b ON l.branch_id = b.id
        LEFT JOIN lab_test_equipment le ON l.id = le.lab_test_id
        LEFT JOIN medical_equipment e ON le.equipment_id = e.id
        WHERE 1=1 $branch_filter $search_filter
        GROUP BY l.id
        ORDER BY l.test_name ASC, l.price ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT test_name, COUNT(*) as count FROM lab_tests_catalog l GROUP BY test_name HAVING COUNT(*) > 1");
    $stmt->execute();
    $duplicate_warning = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $lab_tests = []; $duplicate_warning = []; }

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
            $stmt = $db->prepare("SELECT s.*, c.category_name FROM services s LEFT JOIN service_categories c ON s.category_id = c.id WHERE s.id = ?");
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
            $stmt = $db->prepare("SELECT l.*, GROUP_CONCAT(DISTINCT le.equipment_id SEPARATOR ',') as current_equipment_ids
                FROM lab_tests_catalog l
                LEFT JOIN lab_test_equipment le ON l.id = le.lab_test_id
                WHERE l.id = ? GROUP BY l.id");
            $stmt->execute([$edit_id]);
            $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($edit_item && isset($edit_item['current_equipment_ids']) && !empty($edit_item['current_equipment_ids'])) {
                $current_equipment_ids = explode(',', $edit_item['current_equipment_ids']);
                $current_equipment_ids = array_map('intval', $current_equipment_ids);
            } else {
                $current_equipment_ids = [];
            }
        } catch (Exception $e) { $edit_item = null; $current_equipment_ids = []; }
    }
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================
     PAGE-SPECIFIC CSS - TUMIA VARIABLES ZA HEADER (--page-*)
     ================================================================ -->
<style>
    /* PAGE HEADER */
    .page-header-serv {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
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

    .page-header-serv::before {
        content: '';
        position: absolute;
        top: -50%; right: -20%;
        width: 300px; height: 300px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-serv .page-title-serv {
        color: white;
        font-size: 1.6rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-serv .page-subtitle-serv {
        color: rgba(255,255,255,0.85);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 6px;
    }

    .page-header-serv .role-badge-display {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .page-header-serv .header-badge-serv {
        background: rgba(255,255,255,0.12);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .page-header-serv .btn-outline-light-serv {
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
        cursor: pointer;
        font-family: inherit;
    }

    .page-header-serv .btn-outline-light-serv:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    /* TABS */
    .tabs-serv {
        display: flex;
        gap: 4px;
        background: var(--page-bg-card, #FFFFFF);
        padding: 4px;
        border-radius: 12px;
        margin-bottom: 24px;
        border: 2px solid var(--page-border, #E2E8F0);
        flex-wrap: wrap;
    }

    [data-theme="dark"] .tabs-serv { background: #1E293B; border-color: #334155; }

    .tab-btn-serv {
        padding: 10px 24px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.3s ease;
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        flex: 1;
        text-align: center;
        min-width: 120px;
        font-family: inherit;
    }

    .tab-btn-serv:hover {
        background: var(--page-hover, #F8FAFC);
        color: var(--page-text-primary, #1E293B);
    }

    .tab-btn-serv.active {
        background: #0B5ED7;
        color: white;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
    }

    .tab-btn-serv i { margin-right: 8px; }
    .tab-content-serv { display: none; }
    .tab-content-serv.active { display: block; }

    /* CARD */
    .card-serv {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 14px;
        padding: 20px 24px;
        border: 1px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm);
        margin-bottom: 24px;
    }

    [data-theme="dark"] .card-serv { background: #1E293B; border-color: #334155; }

    .card-serv:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: var(--page-shadow-md);
    }

    .card-header-serv {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 8px;
    }

    .card-title-serv {
        font-size: 1rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }

    .card-title-serv i { color: var(--page-primary, #0B5ED7); }

    /* TABLE */
    .table-container-serv {
        overflow-x: auto;
        border-radius: 12px;
        border: 1px solid var(--page-border, #E2E8F0);
    }

    .table-container-serv table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        min-width: 650px;
    }

    .table-container-serv thead {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: #ffffff;
    }

    .table-container-serv thead th {
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
        border-bottom: 3px solid #0A4CA8;
    }

    .table-container-serv tbody tr {
        transition: all 0.3s ease;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
    }

    .table-container-serv tbody tr:last-child { border-bottom: none; }
    .table-container-serv tbody tr:hover { background: #E8F0FE; }
    [data-theme="dark"] .table-container-serv tbody tr:hover { background: #1E3A5F; }

    .table-container-serv tbody td {
        padding: 10px 16px;
        vertical-align: middle;
        color: var(--page-text-primary, #1E293B);
    }

    /* BADGES & TAGS */
    .status-badge-serv {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    .status-badge-serv.active { background: #D1FAE5; color: #059669; }
    .status-badge-serv.inactive { background: #FEE2E2; color: #DC2626; }

    [data-theme="dark"] .status-badge-serv.active { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge-serv.inactive { background: #3A1A1A; color: #F87171; }

    .branch-tag-serv {
        display: inline-block;
        background: #E8F0FE;
        color: #0B5ED7;
        padding: 1px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
    }

    [data-theme="dark"] .branch-tag-serv { background: #1E3A5F; color: #6EA8FE; }

    .branch-tag-serv.all-branches { background: #FEF3C7; color: #D97706; }
    [data-theme="dark"] .branch-tag-serv.all-branches { background: #3D2E0A; color: #FBBF24; }

    .code-badge-serv {
        display: inline-block;
        background: var(--page-hover, #F8FAFC);
        color: var(--page-text-secondary, #64748B);
        padding: 1px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-family: monospace;
        border: 1px solid var(--page-border, #E2E8F0);
    }

    [data-theme="dark"] .code-badge-serv { background: #0F172A; border-color: #334155; }

    .batch-number-serv {
        font-family: monospace;
        font-size: 0.65rem;
        font-weight: 600;
        padding: 1px 8px;
        border-radius: 4px;
        background: #E8F0FE;
        color: #0B5ED7;
    }

    [data-theme="dark"] .batch-number-serv { background: #1E3A5F; color: #6EA8FE; }

    .stock-badge-serv, .expiry-badge-serv {
        padding: 2px 8px;
        border-radius: 8px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }

    .stock-badge-serv.ok { background: #D1FAE5; color: #059669; }
    .stock-badge-serv.low { background: #FEF3C7; color: #D97706; }
    .stock-badge-serv.out { background: #FEE2E2; color: #DC2626; }

    .expiry-badge-serv.valid { background: #D1FAE5; color: #059669; }
    .expiry-badge-serv.expiring { background: #FEF3C7; color: #D97706; }
    .expiry-badge-serv.expired { background: #FEE2E2; color: #DC2626; }
    .expiry-badge-serv.no-expiry { background: var(--page-hover, #F8FAFC); color: var(--page-text-secondary, #64748B); }

    [data-theme="dark"] .stock-badge-serv.ok,
    [data-theme="dark"] .expiry-badge-serv.valid { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .stock-badge-serv.low,
    [data-theme="dark"] .expiry-badge-serv.expiring { background: #3A2A1A; color: #FBBF24; }
    [data-theme="dark"] .stock-badge-serv.out,
    [data-theme="dark"] .expiry-badge-serv.expired { background: #3A1A1A; color: #F87171; }

    .price-display-serv {
        font-weight: 600;
        color: var(--page-primary, #0B5ED7);
    }

    [data-theme="dark"] .price-display-serv { color: #6EA8FE; }

    .equipment-tags-serv { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }

    .equipment-tag-serv {
        font-size: 0.6rem;
        background: #CCFBF1;
        color: #0D9488;
        padding: 1px 8px;
        border-radius: 10px;
        border: 1px solid #0D9488;
    }

    /* ACTION BUTTONS */
    .action-btns-serv { display: flex; gap: 6px; flex-wrap: wrap; }

    .btn-icon-serv {
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
        font-family: inherit;
    }

    .btn-icon-serv:hover { transform: scale(1.1); }

    .btn-icon-serv.view { background: #E8F0FE; color: #0B5ED7; }
    .btn-icon-serv.view:hover { background: #0B5ED7; color: white; }
    .btn-icon-serv.edit { background: #FEF3C7; color: #D97706; }
    .btn-icon-serv.edit:hover { background: #D97706; color: white; }
    .btn-icon-serv.delete { background: #FEE2E2; color: #DC2626; }
    .btn-icon-serv.delete:hover { background: #DC2626; color: white; }

    /* FORMS */
    .form-group-serv { margin-bottom: 14px; }
    .form-label-serv {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--page-text-secondary, #64748B);
        margin-bottom: 4px;
    }
    .form-label-serv .required { color: #DC2626; margin-left: 2px; }

    .form-control-serv {
        width: 100%;
        padding: 8px 12px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 12px;
        font-size: 0.85rem;
        background: var(--page-input-bg, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        outline: none;
        transition: all 0.3s ease;
        font-family: inherit;
    }

    .form-control-serv:focus {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }

    textarea.form-control-serv { resize: vertical; min-height: 60px; }
    select.form-control-serv { appearance: auto; cursor: pointer; }

    .form-row-2-serv { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .form-row-3-serv { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }

    .price-input-serv {
        font-family: 'Courier New', monospace;
        font-size: 1rem;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    /* BUTTONS */
    .btn-serv {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 20px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        font-family: inherit;
    }

    .btn-primary-serv {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
    }
    .btn-primary-serv:hover {
        background: linear-gradient(135deg, #0A4CA8, #083C8A);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        color: white;
    }

    .btn-success-serv {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
        box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
    }
    .btn-success-serv:hover {
        background: linear-gradient(135deg, #047857, #065F46);
        transform: translateY(-2px);
        color: white;
    }

    .btn-danger-serv {
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        color: white;
    }
    .btn-danger-serv:hover {
        background: linear-gradient(135deg, #B91C1C, #991B1B);
        color: white;
    }

    .btn-outline-serv {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }
    .btn-outline-serv:hover {
        background: var(--page-hover, #F8FAFC);
        border-color: var(--page-primary, #0B5ED7);
        color: var(--page-primary, #0B5ED7);
    }

    .btn-sm-serv { padding: 4px 12px; font-size: 0.7rem; border-radius: 8px; }

    /* MESSAGES */
    .alert-serv {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 0.9rem;
        border: 1px solid transparent;
        animation: slideDownServ 0.3s ease;
    }

    @keyframes slideDownServ {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .alert-success-serv { background: #D1FAE5; color: #065F46; border-color: #059669; }
    .alert-error-serv { background: #FEE2E2; color: #991B1B; border-color: #DC2626; }
    .alert-warning-serv { background: #FEF3C7; color: #92400E; border-color: #D97706; }

    [data-theme="dark"] .alert-success-serv { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .alert-error-serv { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .alert-warning-serv { background: #3D2E0A; color: #FBBF24; }

    /* STATS ROW */
    .stats-row-serv {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-item-serv {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 12px;
        padding: 16px 20px;
        border: 1px solid var(--page-border, #E2E8F0);
        text-align: center;
    }

    [data-theme="dark"] .stat-item-serv { background: #1E293B; border-color: #334155; }

    .stat-item-serv .stat-number-serv {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--page-primary, #0B5ED7);
    }

    .stat-item-serv .stat-label-serv {
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* MODAL */
    .modal-overlay-serv {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        backdrop-filter: blur(4px);
        align-items: center;
        justify-content: center;
    }

    .modal-overlay-serv.show { display: flex; }

    .modal-content-serv {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 14px;
        max-width: 700px;
        width: 95%;
        max-height: 90vh;
        overflow-y: auto;
        padding: 30px 35px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: modalSlideInServ 0.3s ease;
    }

    [data-theme="dark"] .modal-content-serv { background: #1E293B; }

    @keyframes modalSlideInServ {
        from { transform: translateY(-30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .modal-header-serv {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        padding-bottom: 16px;
        margin-bottom: 20px;
    }

    .modal-header-serv h2 {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 0;
    }

    .modal-header-serv h2 i { color: var(--page-primary, #0B5ED7); }

    .modal-close-serv {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: none;
        background: #FEE2E2;
        color: #DC2626;
        cursor: pointer;
        font-size: 1rem;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }

    .modal-close-serv:hover {
        background: #DC2626;
        color: white;
        transform: rotate(90deg);
    }

    .modal-actions-serv {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        justify-content: flex-end;
        border-top: 2px solid var(--page-border, #E2E8F0);
        padding-top: 16px;
        flex-wrap: wrap;
    }

    /* DUPLICATE WARNING */
    .duplicate-warning-serv {
        background: #FEF3C7;
        color: #92400E;
        padding: 8px 14px;
        border-radius: 8px;
        border: 1px solid #D97706;
        font-size: 0.8rem;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: opacity 0.8s ease;
    }

    /* EQUIPMENT CHECKBOX GROUP */
    .equipment-checkbox-group-serv {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px;
        max-height: 150px;
        overflow-y: auto;
        padding: 8px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 8px;
        background: var(--page-hover, #F8FAFC);
    }

    [data-theme="dark"] .equipment-checkbox-group-serv { background: #0F172A; border-color: #334155; }

    .equipment-checkbox-item-serv {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        cursor: pointer;
        color: var(--page-text-primary, #1E293B);
    }

    .equipment-checkbox-item-serv:hover { background: #E8F0FE; }
    [data-theme="dark"] .equipment-checkbox-item-serv:hover { background: #1E3A5F; }

    .equipment-checkbox-item-serv input[type="checkbox"] {
        accent-color: #0B5ED7;
        width: 14px;
        height: 14px;
        cursor: pointer;
    }

    .equipment-checkbox-item-serv .equip-qty {
        font-size: 0.6rem;
        color: var(--page-text-muted, #94A3B8);
        margin-left: auto;
    }

    .equipment-checkbox-item-serv .equip-free {
        font-size: 0.55rem;
        color: #059669;
        font-weight: 600;
    }

    .current-equipment-tags-serv {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin: 8px 0 12px;
        padding: 8px 12px;
        background: #CCFBF1;
        border-radius: 8px;
        border: 1px solid #0D9488;
    }

    .current-equipment-tag-serv {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.7rem;
        background: var(--page-bg-card, #FFFFFF);
        padding: 2px 10px;
        border-radius: 12px;
        border: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
    }

    .current-equipment-tag-serv i { color: #0D9488; }

    /* EMPTY STATE */
    .empty-state-serv {
        text-align: center;
        padding: 40px 20px;
        color: var(--page-text-secondary, #64748B);
    }

    .empty-state-serv i {
        font-size: 3rem;
        color: var(--page-text-muted, #94A3B8);
        display: block;
        margin-bottom: 12px;
    }

    /* FOOTER */
    .footer-serv {
        padding: 14px 0;
        border-top: 1px solid var(--page-border, #E2E8F0);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-serv .footer-brand-serv { color: #0B5ED7; font-weight: 600; }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
        .form-row-2-serv, .form-row-3-serv { grid-template-columns: 1fr; }
        .tabs-serv { flex-direction: column; }
        .tab-btn-serv { flex: none; }
    }

    @media (max-width: 768px) {
        .page-header-serv { padding: 16px 18px; }
        .page-header-serv .page-title-serv { font-size: 1.3rem; }
        .stats-row-serv { grid-template-columns: 1fr 1fr; }
        .modal-content-serv { padding: 20px; }
        .table-container-serv table { font-size: 0.75rem; }
        .table-container-serv thead th, .table-container-serv tbody td { padding: 8px 10px; }
    }

    @media (max-width: 480px) {
        .stats-row-serv { grid-template-columns: 1fr; }
        .modal-content-serv { padding: 15px; }
        .equipment-checkbox-group-serv { grid-template-columns: 1fr; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-serv">
        <div>
            <h1 class="page-title-serv">
                <i class="fas fa-concierge-bell"></i>
                Services Management
                <span class="role-badge-display">ADMIN</span>
                <?php if ($selected_branch_id !== 'all'): ?>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($selected_branch_name) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle-serv">
                <i class="fas fa-list"></i>
                Manage <strong>Services</strong>, <strong>Procedures</strong>, <strong>Equipment</strong> & <strong>Lab Tests</strong>
                <span class="header-badge-serv">
                    <i class="fas fa-tag"></i> Total: <?= count($services) + count($procedures) + count($equipment) + count($lab_tests) ?> items
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="openAddModal('service')" class="btn-outline-light-serv">
                <i class="fas fa-plus"></i> Add Service
            </button>
            <button onclick="openAddModal('procedure')" class="btn-outline-light-serv">
                <i class="fas fa-syringe"></i> Add Procedure
            </button>
            <button onclick="openAddModal('equipment')" class="btn-outline-light-serv">
                <i class="fas fa-tools"></i> Add Equipment
            </button>
            <button onclick="openAddModal('lab_test')" class="btn-outline-light-serv">
                <i class="fas fa-microscope"></i> Add Lab Test
            </button>
            <a href="dashboard.php" class="btn-outline-light-serv">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- FILTERS -->
    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-bottom:20px;padding:12px 16px;background:var(--page-bg-card,#FFFFFF);border-radius:12px;border:2px solid var(--page-border,#E2E8F0);">
        <span style="font-size:0.85rem;font-weight:600;color:var(--page-text-secondary,#64748B);">
            <i class="fas fa-filter"></i> Filter:
        </span>
        <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <select name="branch" onchange="this.form.submit()" class="form-control-serv" style="min-width:150px;padding:6px 12px;font-size:0.8rem;">
                <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                        🏥 <?= htmlspecialchars($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" 
                   class="form-control-serv" style="min-width:180px;padding:6px 12px;font-size:0.8rem;">
            <button type="submit" class="btn-serv btn-primary-serv btn-sm-serv">
                <i class="fas fa-search"></i> Filter
            </button>
            <a href="services.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-serv btn-outline-serv btn-sm-serv">
                <i class="fas fa-times"></i> Clear
            </a>
        </form>
    </div>

    <!-- STATS -->
    <div class="stats-row-serv">
        <div class="stat-item-serv">
            <div class="stat-number-serv"><?= count($services) ?></div>
            <div class="stat-label-serv">Services</div>
        </div>
        <div class="stat-item-serv">
            <div class="stat-number-serv" style="color:#7C3AED;"><?= count($procedures) ?></div>
            <div class="stat-label-serv">Procedures</div>
        </div>
        <div class="stat-item-serv">
            <div class="stat-number-serv" style="color:#D97706;"><?= count($equipment) ?></div>
            <div class="stat-label-serv">Equipment</div>
        </div>
        <div class="stat-item-serv">
            <div class="stat-number-serv" style="color:#0D9488;"><?= count($lab_tests) ?></div>
            <div class="stat-label-serv">Lab Tests</div>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div class="alert-serv alert-<?= $message_type ?>-serv">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- DUPLICATE WARNING -->
    <?php if (!empty($duplicate_warning)): ?>
        <div class="duplicate-warning-serv" id="duplicateWarningServ">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Duplicate Tests Detected:</strong>
            <?php foreach ($duplicate_warning as $dup): ?>
                <span style="margin-left:8px;">"<?= htmlspecialchars($dup['test_name']) ?>" (<strong><?= $dup['count'] ?></strong> entries)</span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="tabs-serv">
        <button class="tab-btn-serv <?= $active_tab === 'services' ? 'active' : '' ?>" data-tab="services">
            <i class="fas fa-concierge-bell"></i> Services (<?= count($services) ?>)
        </button>
        <button class="tab-btn-serv <?= $active_tab === 'procedures' ? 'active' : '' ?>" data-tab="procedures">
            <i class="fas fa-syringe"></i> Procedures (<?= count($procedures) ?>)
        </button>
        <button class="tab-btn-serv <?= $active_tab === 'equipment' ? 'active' : '' ?>" data-tab="equipment">
            <i class="fas fa-tools"></i> Equipment (<?= count($equipment) ?>)
        </button>
        <button class="tab-btn-serv <?= $active_tab === 'lab_tests' ? 'active' : '' ?>" data-tab="lab_tests">
            <i class="fas fa-microscope"></i> Lab Tests (<?= count($lab_tests) ?>)
        </button>
    </div>

    <!-- ================================================================ -->
    <!-- TAB 1: SERVICES -->
    <!-- ================================================================ -->
    <div class="tab-content-serv <?= $active_tab === 'services' ? 'active' : '' ?>" id="tab-services">
        <div class="card-serv">
            <div class="card-header-serv">
                <h3 class="card-title-serv"><i class="fas fa-list"></i> All Services</h3>
                <button onclick="openAddModal('service')" class="btn-serv btn-primary-serv btn-sm-serv">
                    <i class="fas fa-plus"></i> Add Service
                </button>
            </div>
            <div class="table-container-serv">
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
                                            <div style="font-size:0.7rem;color:var(--page-text-muted);"><?= htmlspecialchars(substr($s['description'], 0, 50)) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="branch-tag-serv" style="background:#EDE9FE;color:#7C3AED;">
                                            <?= htmlspecialchars($s['category_name'] ?? 'Uncategorized') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($s['branch_id'] === null): ?>
                                            <span class="branch-tag-serv all-branches">🌐 All Branches</span>
                                        <?php else: ?>
                                            <span class="branch-tag-serv"><?= htmlspecialchars($s['branch_name'] ?? 'N/A') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="price-display-serv">TSh <?= number_format($s['price'] ?? 0, 0) ?></td>
                                    <td>
                                        <span class="status-badge-serv <?= $s['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns-serv">
                                            <button class="btn-icon-serv view" onclick="viewItem('service', <?= $s['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="?edit=<?= $s['id'] ?>&type=service&branch=<?= urlencode($selected_branch_id) ?>&tab=<?= $active_tab ?>" class="btn-icon-serv edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn-icon-serv delete" onclick="deleteItem('service', <?= $s['id'] ?>, '<?= addslashes($s['service_name']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state-serv">
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
    <div class="tab-content-serv <?= $active_tab === 'procedures' ? 'active' : '' ?>" id="tab-procedures">
        <div class="card-serv">
            <div class="card-header-serv">
                <h3 class="card-title-serv"><i class="fas fa-syringe"></i> All Procedures</h3>
                <button onclick="openAddModal('procedure')" class="btn-serv btn-primary-serv btn-sm-serv">
                    <i class="fas fa-plus"></i> Add Procedure
                </button>
            </div>
            <div class="table-container-serv">
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
                                    <td><span class="code-badge-serv"><?= htmlspecialchars($p['procedure_code'] ?? 'N/A') ?></span></td>
                                    <td><?= htmlspecialchars($p['category'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($p['branch_id'] === null): ?>
                                            <span class="branch-tag-serv all-branches">🌐 All Branches</span>
                                        <?php else: ?>
                                            <span class="branch-tag-serv"><?= htmlspecialchars($p['branch_name'] ?? 'N/A') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="price-display-serv">TSh <?= number_format($p['price'] ?? 0, 0) ?></td>
                                    <td>
                                        <span class="status-badge-serv <?= $p['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns-serv">
                                            <button class="btn-icon-serv view" onclick="viewItem('procedure', <?= $p['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="?edit=<?= $p['id'] ?>&type=procedure&branch=<?= urlencode($selected_branch_id) ?>&tab=<?= $active_tab ?>" class="btn-icon-serv edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn-icon-serv delete" onclick="deleteItem('procedure', <?= $p['id'] ?>, '<?= addslashes($p['procedure_name']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state-serv">
                        <i class="fas fa-syringe"></i>
                        <p>No procedures found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TAB 3: EQUIPMENT -->
    <!-- ================================================================ -->
    <div class="tab-content-serv <?= $active_tab === 'equipment' ? 'active' : '' ?>" id="tab-equipment">
        <div class="card-serv">
            <div class="card-header-serv">
                <h3 class="card-title-serv"><i class="fas fa-tools"></i> Medical Equipment</h3>
                <button onclick="openAddModal('equipment')" class="btn-serv btn-primary-serv btn-sm-serv">
                    <i class="fas fa-plus"></i> Add Equipment
                </button>
            </div>
            <div class="table-container-serv">
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
                                            <span class="branch-tag-serv all-branches">🌐 All Branches</span>
                                        <?php else: ?>
                                            <span class="branch-tag-serv"><?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;"><strong><?= number_format($item['total_quantity'] ?? 0) ?></strong></td>
                                    <td>
                                        <span class="stock-badge-serv <?= $stock['class'] ?>">
                                            <i class="fas <?= $stock['class'] === 'ok' ? 'fa-check-circle' : ($stock['class'] === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                                            <?= $stock['label'] ?>
                                        </span>
                                    </td>
                                    <td class="price-display-serv">
                                        <?= ($item['selling_price'] ?? 0) > 0 ? 'TSh ' . number_format($item['selling_price'], 0) : 'FREE' ?>
                                    </td>
                                    <td>
                                        <span class="expiry-badge-serv <?= $expiry['class'] ?>"><?= $expiry['label'] ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($first_batch)): ?>
                                            <span class="batch-number-serv"><?= htmlspecialchars($first_batch) ?></span>
                                            <?php if ($batch_count > 1): ?>
                                                <span style="font-size:0.6rem;color:var(--page-text-muted);">+<?= $batch_count - 1; ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:var(--page-text-muted);">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge-serv <?= ($item['computed_status'] ?? 'active') === 'active' ? 'active' : 'inactive' ?>">
                                            <?= ucfirst($item['computed_status'] ?? 'active') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns-serv">
                                            <button class="btn-icon-serv view" onclick="viewItem('equipment', <?= $item['equipment_id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="?edit=<?= $item['equipment_id'] ?>&type=equipment&branch=<?= urlencode($selected_branch_id) ?>&tab=<?= $active_tab ?>" class="btn-icon-serv edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn-icon-serv delete" onclick="deleteItem('equipment', <?= $item['equipment_id'] ?>, '<?= addslashes($item['equipment_name']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state-serv">
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
    <div class="tab-content-serv <?= $active_tab === 'lab_tests' ? 'active' : '' ?>" id="tab-lab_tests">
        <div class="card-serv">
            <div class="card-header-serv">
                <h3 class="card-title-serv"><i class="fas fa-microscope"></i> All Lab Tests</h3>
                <button onclick="openAddModal('lab_test')" class="btn-serv btn-primary-serv btn-sm-serv">
                    <i class="fas fa-plus"></i> Add Lab Test
                </button>
            </div>
            <div class="table-container-serv">
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
                                            <span class="branch-tag-serv" style="background:#EDE9FE;color:#7C3AED;"><?= htmlspecialchars($l['category']) ?></span>
                                        <?php else: ?>
                                            <span class="branch-tag-serv">Uncategorized</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($l['branch_id'] === null): ?>
                                            <span class="branch-tag-serv all-branches">🌐 All Branches</span>
                                        <?php else: ?>
                                            <span class="branch-tag-serv"><?= htmlspecialchars($l['branch_name'] ?? 'N/A') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="price-display-serv">TSh <?= number_format($l['price'] ?? 0, 0) ?></td>
                                    <td>
                                        <?php if (!empty($eq_arr)): ?>
                                            <div class="equipment-tags-serv">
                                                <?php foreach ($eq_arr as $eq): ?>
                                                    <span class="equipment-tag-serv"><i class="fas fa-tools"></i> <?= htmlspecialchars($eq) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="font-size:0.7rem;color:var(--page-text-muted);">No equipment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge-serv <?= $l['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $l['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns-serv">
                                            <button class="btn-icon-serv view" onclick="viewItem('lab_test', <?= $l['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="?edit=<?= $l['id'] ?>&type=lab_test&branch=<?= urlencode($selected_branch_id) ?>&tab=<?= $active_tab ?>" class="btn-icon-serv edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn-icon-serv delete" onclick="deleteItem('lab_test', <?= $l['id'] ?>, '<?= addslashes($l['test_name']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state-serv">
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
    <div class="modal-overlay-serv" id="addModal">
        <div class="modal-content-serv">
            <div class="modal-header-serv">
                <h2 id="addModalTitle"><i class="fas fa-plus"></i> Add New</h2>
                <button class="modal-close-serv" onclick="closeModal('addModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="addForm">
                <input type="hidden" name="action" id="addFormAction" value="">
                
                <div class="form-group-serv">
                    <label class="form-label-serv">Branch <span class="required">*</span></label>
                    <select name="branch_id" id="addBranchSelect" class="form-control-serv" required>
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
                <div class="modal-actions-serv">
                    <button type="button" class="btn-serv btn-outline-serv" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn-serv btn-success-serv"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- EDIT MODAL -->
    <!-- ================================================================ -->
    <?php if ($edit_item && isset($edit_type)): ?>
    <div class="modal-overlay-serv show" id="editModal">
        <div class="modal-content-serv">
            <div class="modal-header-serv">
                <h2><i class="fas fa-edit"></i> Edit <?= ucfirst($edit_type) ?></h2>
                <a href="?branch=<?= urlencode($selected_branch_id) ?>&tab=<?= $active_tab ?>" class="modal-close-serv">
                    <i class="fas fa-times"></i>
                </a>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update_<?= $edit_type ?>">
                
                <?php if ($edit_type === 'lab_test'): ?>
                    <input type="hidden" name="test_id" value="<?= $edit_item['id'] ?>">
                <?php elseif ($edit_type === 'service'): ?>
                    <input type="hidden" name="service_id" value="<?= $edit_item['id'] ?>">
                <?php elseif ($edit_type === 'procedure'): ?>
                    <input type="hidden" name="procedure_id" value="<?= $edit_item['id'] ?>">
                <?php elseif ($edit_type === 'equipment'): ?>
                    <input type="hidden" name="equipment_id" value="<?= $edit_item['id'] ?>">
                <?php endif; ?>
                
                <div class="form-group-serv">
                    <label class="form-label-serv">Branch <span class="required">*</span></label>
                    <select name="branch_id" class="form-control-serv" required>
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
                    <div class="form-group-serv">
                        <label class="form-label-serv">Service Name <span class="required">*</span></label>
                        <input type="text" name="service_name" class="form-control-serv" value="<?= htmlspecialchars($edit_item['service_name']) ?>" required>
                    </div>
                    <div class="form-group-serv">
                        <label class="form-label-serv">Category</label>
                        <select name="category_id" class="form-control-serv">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $edit_item['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-serv">
                        <label class="form-label-serv">Description</label>
                        <textarea name="description" class="form-control-serv" rows="2"><?= htmlspecialchars($edit_item['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row-2-serv">
                        <div class="form-group-serv">
                            <label class="form-label-serv">Price (TSh) <span class="required">*</span></label>
                            <input type="text" name="price" class="form-control-serv price-input-serv" value="<?= number_format($edit_item['price'] ?? 0, 0) ?>" required oninput="formatPriceInput(this)">
                        </div>
                        <div class="form-group-serv" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="checkbox" name="is_active" value="1" <?= $edit_item['is_active'] ? 'checked' : '' ?>>
                                <span>Active</span>
                            </label>
                        </div>
                    </div>
                <?php elseif ($edit_type === 'procedure'): ?>
                    <div class="form-group-serv">
                        <label class="form-label-serv">Procedure Name <span class="required">*</span></label>
                        <input type="text" name="procedure_name" class="form-control-serv" value="<?= htmlspecialchars($edit_item['procedure_name']) ?>" required>
                    </div>
                    <div class="form-group-serv">
                        <label class="form-label-serv">Category</label>
                        <select name="category_id" class="form-control-serv">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $edit_item['category'] == $cat['category_name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="0">-- Other (Type manually) --</option>
                        </select>
                        <input type="text" name="category_name" class="form-control-serv" style="margin-top:4px;display:none;" value="<?= htmlspecialchars($edit_item['category'] ?? '') ?>">
                    </div>
                    <div class="form-group-serv">
                        <label class="form-label-serv">Description</label>
                        <textarea name="description" class="form-control-serv" rows="2"><?= htmlspecialchars($edit_item['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row-2-serv">
                        <div class="form-group-serv">
                            <label class="form-label-serv">Price (TSh) <span class="required">*</span></label>
                            <input type="text" name="price" class="form-control-serv price-input-serv" value="<?= number_format($edit_item['price'] ?? 0, 0) ?>" required oninput="formatPriceInput(this)">
                        </div>
                        <div class="form-group-serv" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="checkbox" name="is_active" value="1" <?= $edit_item['is_active'] ? 'checked' : '' ?>>
                                <span>Active</span>
                            </label>
                        </div>
                    </div>
                <?php elseif ($edit_type === 'equipment'): ?>
                    <div class="form-group-serv">
                        <label class="form-label-serv">Equipment Name <span class="required">*</span></label>
                        <input type="text" name="equipment_name" class="form-control-serv" value="<?= htmlspecialchars($edit_item['equipment_name']) ?>" required>
                    </div>
                    <div class="form-group-serv">
                        <label class="form-label-serv">Category</label>
                        <select name="category_id" class="form-control-serv">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $edit_item['category'] == $cat['category_name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="0">-- Other (Type manually) --</option>
                        </select>
                        <input type="text" name="category_name" class="form-control-serv" style="margin-top:4px;display:none;" value="<?= htmlspecialchars($edit_item['category'] ?? '') ?>">
                    </div>
                    <div class="form-row-3-serv">
                        <div class="form-group-serv">
                            <label class="form-label-serv">Unit</label>
                            <select name="unit" class="form-control-serv">
                                <option value="pcs" <?= $edit_item['unit'] === 'pcs' ? 'selected' : '' ?>>Pieces (pcs)</option>
                                <option value="box" <?= $edit_item['unit'] === 'box' ? 'selected' : '' ?>>Box</option>
                                <option value="pack" <?= $edit_item['unit'] === 'pack' ? 'selected' : '' ?>>Pack</option>
                                <option value="set" <?= $edit_item['unit'] === 'set' ? 'selected' : '' ?>>Set</option>
                                <option value="each" <?= $edit_item['unit'] === 'each' ? 'selected' : '' ?>>Each</option>
                            </select>
                        </div>
                        <div class="form-group-serv">
                            <label class="form-label-serv">Quantity <span class="required">*</span></label>
                            <input type="number" name="quantity" class="form-control-serv" required min="0" value="<?= $edit_item['quantity'] ?? 0 ?>">
                        </div>
                        <div class="form-group-serv">
                            <label class="form-label-serv">Reorder Level <span class="required">*</span></label>
                            <input type="number" name="reorder_level" class="form-control-serv" required min="0" value="<?= $edit_item['reorder_level'] ?? 5 ?>">
                        </div>
                    </div>
                    <div class="form-row-2-serv">
                        <div class="form-group-serv">
                            <label class="form-label-serv">Selling Price (TSh)</label>
                            <input type="text" name="selling_price" class="form-control-serv price-input-serv" value="<?= number_format($edit_item['selling_price'] ?? 0, 0) ?>" oninput="formatPriceInput(this)">
                        </div>
                        <div class="form-group-serv">
                            <label class="form-label-serv">Supplier</label>
                            <input type="text" name="supplier" class="form-control-serv" value="<?= htmlspecialchars($edit_item['supplier'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row-2-serv">
                        <div class="form-group-serv">
                            <label class="form-label-serv">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control-serv" value="<?= $edit_item['expiry_date'] ?? '' ?>">
                        </div>
                        <div class="form-group-serv" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="checkbox" name="is_active" value="1" <?= ($edit_item['status'] ?? 'active') === 'active' ? 'checked' : '' ?>>
                                <span>Active</span>
                            </label>
                            <input type="hidden" name="status" value="<?= ($edit_item['status'] ?? 'active') === 'active' ? 'active' : 'inactive' ?>">
                        </div>
                    </div>
                <?php elseif ($edit_type === 'lab_test'): ?>
                    <div class="form-group-serv">
                        <label class="form-label-serv">Test Name <span class="required">*</span></label>
                        <input type="text" name="test_name" class="form-control-serv" value="<?= htmlspecialchars($edit_item['test_name']) ?>" required>
                    </div>
                    <div class="form-group-serv">
                        <label class="form-label-serv">Category</label>
                        <select name="category_id" class="form-control-serv">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $edit_item['category'] == $cat['category_name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="0">-- Other (Type manually) --</option>
                        </select>
                        <input type="text" name="category_name" class="form-control-serv" style="margin-top:4px;display:none;" value="<?= htmlspecialchars($edit_item['category'] ?? '') ?>">
                    </div>
                    <div class="form-group-serv">
                        <label class="form-label-serv">Description</label>
                        <textarea name="description" class="form-control-serv" rows="2"><?= htmlspecialchars($edit_item['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row-2-serv">
                        <div class="form-group-serv">
                            <label class="form-label-serv">Price (TSh) <span class="required">*</span></label>
                            <input type="text" name="price" class="form-control-serv price-input-serv" value="<?= number_format($edit_item['price'] ?? 0, 0) ?>" required oninput="formatPriceInput(this)">
                        </div>
                        <div class="form-group-serv" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="checkbox" name="is_active" value="1" <?= $edit_item['is_active'] ? 'checked' : '' ?>>
                                <span>Active</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Equipment Selection -->
                    <div class="form-group-serv">
                        <label class="form-label-serv">
                            <i class="fas fa-tools"></i> Select Equipment (FREE)
                            <span style="font-size:0.6rem;font-weight:400;color:var(--page-text-muted);">Equipment price is NOT added to test price</span>
                        </label>
                        
                        <?php if (!empty($current_equipment_ids)): ?>
                            <div class="current-equipment-tags-serv">
                                <span style="font-weight:600;font-size:0.7rem;color:#0D9488;">
                                    <i class="fas fa-link"></i> Currently Linked Equipment:
                                </span>
                                <?php 
                                    $placeholders = implode(',', array_fill(0, count($current_equipment_ids), '?'));
                                    $stmt = $db->prepare("SELECT id, equipment_name FROM medical_equipment WHERE id IN ($placeholders)");
                                    $stmt->execute($current_equipment_ids);
                                    $current_eq = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($current_eq as $eq) {
                                        echo '<span class="current-equipment-tag-serv"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($eq['equipment_name']) . '</span>';
                                    }
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php 
                        $equip_branch = ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) ? (int)$selected_branch_id : $user_branch_id;
                        $equipment_for_lab_edit = [];
                        try {
                            $stmt = $db->prepare("SELECT id, equipment_name, quantity FROM medical_equipment WHERE (branch_id = ? OR branch_id IS NULL) AND status = 'active' ORDER BY equipment_name");
                            $stmt->execute([$equip_branch]);
                            $equipment_for_lab_edit = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {}
                        ?>
                        
                        <?php if (count($equipment_for_lab_edit) > 0): ?>
                            <div class="equipment-checkbox-group-serv">
                                <?php foreach ($equipment_for_lab_edit as $eq): 
                                    $checked = in_array($eq['id'], $current_equipment_ids) ? 'checked' : '';
                                ?>
                                    <label class="equipment-checkbox-item-serv">
                                        <input type="checkbox" name="equipment_ids[]" value="<?= $eq['id'] ?>" <?= $checked ?>>
                                        <?= htmlspecialchars($eq['equipment_name']) ?>
                                        <span class="equip-qty">(<?= $eq['quantity'] ?? 0 ?> in stock)</span>
                                        <span class="equip-free"><?= $checked ? '✅ Selected' : 'FREE' ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="padding:10px;background:#FEF3C7;border-radius:8px;color:#92400E;font-size:0.8rem;">
                                <i class="fas fa-exclamation-triangle"></i> No equipment available for this branch.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="modal-actions-serv">
                    <a href="?branch=<?= urlencode($selected_branch_id) ?>&tab=<?= $active_tab ?>" class="btn-serv btn-outline-serv">Cancel</a>
                    <button type="submit" class="btn-serv btn-success-serv"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- VIEW MODAL -->
    <!-- ================================================================ -->
    <div class="modal-overlay-serv" id="viewModal">
        <div class="modal-content-serv">
            <div class="modal-header-serv">
                <h2 id="viewModalTitle"><i class="fas fa-eye"></i> Details</h2>
                <button class="modal-close-serv" onclick="closeModal('viewModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="viewModalBody"></div>
            <div class="modal-actions-serv">
                <button class="btn-serv btn-outline-serv" onclick="closeModal('viewModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DELETE CONFIRM MODAL -->
    <!-- ================================================================ -->
    <div class="modal-overlay-serv" id="deleteModal">
        <div class="modal-content-serv" style="max-width:450px;">
            <div class="modal-header-serv">
                <h2><i class="fas fa-trash" style="color:#DC2626;"></i> Confirm Delete</h2>
                <button class="modal-close-serv" onclick="closeModal('deleteModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" id="deleteAction" value="">
                <input type="hidden" name="delete_id" id="deleteId" value="">
                <p id="deleteMessage" style="margin-bottom:20px;font-size:1rem;">Are you sure you want to delete this item?</p>
                <div class="modal-actions-serv">
                    <button type="button" class="btn-serv btn-outline-serv" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn-serv btn-danger-serv"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </form>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer-serv">
        <p>
            <span class="footer-brand-serv">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Services Management
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // FOOTER TIME ONLY
    // ================================================================
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    // ================================================================
    // FORMAT PRICE INPUT
    // ================================================================
    function formatPriceInput(input) {
        var raw = input.value.replace(/[^0-9]/g, '');
        if (raw === '') { input.value = ''; return; }
        input.value = parseInt(raw).toLocaleString('en-US');
    }

    // ================================================================
    // DUPLICATE WARNING AUTO-HIDE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var dw = document.getElementById('duplicateWarningServ');
        if (dw) {
            setTimeout(function() {
                dw.style.opacity = '0';
                setTimeout(function() { dw.style.display = 'none'; }, 800);
            }, 5000);
        }
    });

    // ================================================================
    // TABS
    // ================================================================
    document.querySelectorAll('.tab-btn-serv').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn-serv').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            var tab = this.dataset.tab;
            document.querySelectorAll('.tab-content-serv').forEach(function(c) { c.classList.remove('active'); });
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
    
    document.querySelectorAll('.modal-overlay-serv').forEach(function(overlay) {
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
                <div class="form-group-serv">
                    <label class="form-label-serv">Service Name <span class="required">*</span></label>
                    <input type="text" name="service_name" class="form-control-serv" required placeholder="e.g. General Consultation">
                </div>
                <div class="form-group-serv">
                    <label class="form-label-serv">Category <span class="required">*</span></label>
                    <select name="category_id" class="form-control-serv" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group-serv">
                    <label class="form-label-serv">Description</label>
                    <textarea name="description" class="form-control-serv" rows="2" placeholder="Service description..."></textarea>
                </div>
                <div class="form-row-2-serv">
                    <div class="form-group-serv">
                        <label class="form-label-serv">Price (TSh) <span class="required">*</span></label>
                        <input type="text" name="price" class="form-control-serv price-input-serv" placeholder="e.g. 10000" required oninput="formatPriceInput(this)">
                    </div>
                    <div class="form-group-serv" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span>Active</span>
                        </label>
                    </div>
                </div>
            `;
        } else if (type === 'procedure') {
            html = `
                <div class="form-group-serv">
                    <label class="form-label-serv">Procedure Name <span class="required">*</span></label>
                    <input type="text" name="procedure_name" class="form-control-serv" required placeholder="e.g. Wound Dressing">
                </div>
                <div class="form-group-serv">
                    <label class="form-label-serv">Category <span class="required">*</span></label>
                    <select name="category_id" class="form-control-serv" required onchange="toggleManualCategoryServ(this)">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                        <option value="0">-- Other (Type manually) --</option>
                    </select>
                    <input type="text" name="category_name" class="form-control-serv" style="margin-top:4px;display:none;" placeholder="Enter custom category...">
                </div>
                <div class="form-group-serv">
                    <label class="form-label-serv">Description</label>
                    <textarea name="description" class="form-control-serv" rows="2"></textarea>
                </div>
                <div class="form-row-2-serv">
                    <div class="form-group-serv">
                        <label class="form-label-serv">Price (TSh) <span class="required">*</span></label>
                        <input type="text" name="price" class="form-control-serv price-input-serv" required oninput="formatPriceInput(this)">
                    </div>
                    <div class="form-group-serv" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span>Active</span>
                        </label>
                    </div>
                </div>
            `;
        } else if (type === 'equipment') {
            html = `
                <div class="form-group-serv">
                    <label class="form-label-serv">Equipment Name <span class="required">*</span></label>
                    <input type="text" name="equipment_name" class="form-control-serv" required>
                </div>
                <div class="form-group-serv">
                    <label class="form-label-serv">Category <span class="required">*</span></label>
                    <select name="category_id" class="form-control-serv" required onchange="toggleManualCategoryServ(this)">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                        <option value="0">-- Other --</option>
                    </select>
                    <input type="text" name="category_name" class="form-control-serv" style="margin-top:4px;display:none;" placeholder="Custom...">
                </div>
                <div class="form-row-3-serv">
                    <div class="form-group-serv">
                        <label class="form-label-serv">Unit</label>
                        <select name="unit" class="form-control-serv">
                            <option value="pcs">Pieces (pcs)</option>
                            <option value="box">Box</option>
                            <option value="pack">Pack</option>
                            <option value="set">Set</option>
                        </select>
                    </div>
                    <div class="form-group-serv">
                        <label class="form-label-serv">Quantity <span class="required">*</span></label>
                        <input type="number" name="quantity" class="form-control-serv" required min="0" value="0">
                    </div>
                    <div class="form-group-serv">
                        <label class="form-label-serv">Reorder Level <span class="required">*</span></label>
                        <input type="number" name="reorder_level" class="form-control-serv" required min="0" value="5">
                    </div>
                </div>
                <div class="form-row-2-serv">
                    <div class="form-group-serv">
                        <label class="form-label-serv">Selling Price (TSh)</label>
                        <input type="text" name="selling_price" class="form-control-serv price-input-serv" value="0" oninput="formatPriceInput(this)">
                    </div>
                    <div class="form-group-serv">
                        <label class="form-label-serv">Supplier</label>
                        <input type="text" name="supplier" class="form-control-serv">
                    </div>
                </div>
                <div class="form-row-2-serv">
                    <div class="form-group-serv">
                        <label class="form-label-serv">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control-serv">
                    </div>
                    <div class="form-group-serv">
                        <label class="form-label-serv">Batch Number</label>
                        <input type="text" name="batch_number" class="form-control-serv" placeholder="Auto-generated if empty">
                    </div>
                </div>
            `;
        } else if (type === 'lab_test') {
            html = `
                <div class="form-group-serv">
                    <label class="form-label-serv">Test Name <span class="required">*</span></label>
                    <input type="text" name="test_name" class="form-control-serv" required>
                </div>
                <div class="form-group-serv">
                    <label class="form-label-serv">Category <span class="required">*</span></label>
                    <select name="category_id" class="form-control-serv" required onchange="toggleManualCategoryServ(this)">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                        <option value="0">-- Other --</option>
                    </select>
                    <input type="text" name="category_name" class="form-control-serv" style="margin-top:4px;display:none;">
                </div>
                <div class="form-group-serv">
                    <label class="form-label-serv">Description</label>
                    <textarea name="description" class="form-control-serv" rows="2"></textarea>
                </div>
                <div class="form-row-2-serv">
                    <div class="form-group-serv">
                        <label class="form-label-serv">Price (TSh) <span class="required">*</span></label>
                        <input type="text" name="price" class="form-control-serv price-input-serv" required oninput="formatPriceInput(this)">
                    </div>
                    <div class="form-group-serv" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span>Active</span>
                        </label>
                    </div>
                </div>
                <div class="form-group-serv">
                    <label class="form-label-serv">
                        <i class="fas fa-tools"></i> Select Equipment (FREE)
                    </label>
                    <?php 
                    $equip_branch = ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) ? (int)$selected_branch_id : $user_branch_id;
                    $equipment_for_add = [];
                    try {
                        $stmt = $db->prepare("SELECT id, equipment_name, quantity FROM medical_equipment WHERE (branch_id = ? OR branch_id IS NULL) AND status = 'active' ORDER BY equipment_name");
                        $stmt->execute([$equip_branch]);
                        $equipment_for_add = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {}
                    ?>
                    <?php if (count($equipment_for_add) > 0): ?>
                        <div class="equipment-checkbox-group-serv">
                            <?php foreach ($equipment_for_add as $eq): ?>
                                <label class="equipment-checkbox-item-serv">
                                    <input type="checkbox" name="equipment_ids[]" value="<?= $eq['id'] ?>">
                                    <?= htmlspecialchars($eq['equipment_name']) ?>
                                    <span class="equip-qty">(<?= $eq['quantity'] ?? 0 ?> in stock)</span>
                                    <span class="equip-free">FREE</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="padding:10px;background:#FEF3C7;border-radius:8px;color:#92400E;font-size:0.8rem;">
                            <i class="fas fa-exclamation-triangle"></i> No equipment available.
                        </div>
                    <?php endif; ?>
                </div>
            `;
        }
        
        fields.innerHTML = html;
        openModal('addModal');
    }

    // ================================================================
    // TOGGLE MANUAL CATEGORY
    // ================================================================
    function toggleManualCategoryServ(select) {
        var manualInput = select.closest('.form-group-serv').querySelector('input[name="category_name"]');
        if (!manualInput) return;
        if (select.value === '0') {
            manualInput.style.display = 'block';
            manualInput.required = true;
            manualInput.focus();
        } else {
            manualInput.style.display = 'none';
            manualInput.required = false;
            manualInput.value = '';
        }
    }

    // Apply on page load for edit modal
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('select[name="category_id"]').forEach(function(select) {
            if (select.value === '0') {
                var manualInput = select.closest('.form-group-serv').querySelector('input[name="category_name"]');
                if (manualInput) { manualInput.style.display = 'block'; manualInput.required = true; }
            }
        });
    });

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
            body.innerHTML = '<p style="text-align:center;color:var(--page-text-secondary);">Table not found</p>';
            openModal('viewModal');
            return;
        }
        
        var rows = tableBody.querySelectorAll('tr');
        var row = null;
        for (var i = 0; i < rows.length; i++) {
            if (rows[i].getAttribute('data-id') == id) { row = rows[i]; break; }
        }
        
        if (!row) {
            body.innerHTML = '<div style="text-align:center;padding:32px;"><i class="fas fa-exclamation-circle" style="font-size:2rem;color:#DC2626;display:block;margin-bottom:12px;"></i><p style="color:var(--page-text-secondary);">Item not found.</p></div>';
            openModal('viewModal');
            return;
        }
        
        var cells = row.querySelectorAll('td');
        var labels = ['ID', 'Name', 'Category', 'Branch', 'Price/Quantity', 'Stock', 'Expiry', 'Status'];
        var html = '<div>';
        
        var dataIndex = 0;
        for (var i = 1; i < cells.length && dataIndex < labels.length; i++) {
            var label = labels[dataIndex] || 'Field';
            var value = cells[i]?.textContent?.trim() || 'N/A';
            
            html += `
                <div style="padding:8px 0;border-bottom:1px solid var(--page-border,#E2E8F0);">
                    <div style="font-size:0.7rem;color:var(--page-text-secondary);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">${label}</div>
                    <div style="font-size:0.95rem;font-weight:500;margin-top:2px;color:var(--page-text-primary);">${value}</div>
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

    console.log('%c🛠️ Braick - Services Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c✅ NO duplicate header JavaScript', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
    console.log('%c✅ Full CRUD: Services, Procedures, Equipment, Lab Tests', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>