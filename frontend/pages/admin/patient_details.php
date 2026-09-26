<?php
// ================================================================
// FILE: frontend/pages/admin/patient_details.php
// VIEW PATIENT - V5 (Delete Without Reason + Full English)
// ================================================================
// V5 FIXED: Delete visit without typing "DELETE"
// V5 FIXED: All messages in English
// V4 FIXED: Full deletion with stock return for ALL tables
// V4 FIXED: Delete prescription, lab test, visit, bill, bill item
// V4 FIXED: Smart delete logic (Paid/Dispensed = no changes)
// V3 FIXED: Zero-quantity prescriptions are hidden
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'reception';
$branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

$message = '';
$message_type = '';

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id <= 0) {
    header('Location: patients.php?error=invalid_patient');
    exit;
}

$patient = null;
$active_visit = null;
$visit_history = [];
$bills = [];
$bill_items = [];
$procedures = [];
$tools = [];
$latest_vitals = null;
$prescriptions = [];
$prescriptions_by_visit = [];
$lab_tests = [];
$vital_signs = [];
$age = 'N/A';
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// HELPER: Log stock movement
// ================================================================
function logStockMovementHelper($db, $item_type, $item_id, $patient_id, $movement_type, $quantity, $previous_stock, $new_stock, $reference_type, $reference_id, $performed_by, $branch_id, $notes) {
    try {
        if ($item_type === 'medicine') {
            $sql = "INSERT INTO stock_movements 
                    (inventory_id, patient_id, movement_type, quantity, previous_stock, new_stock, 
                     reference_type, reference_id, performed_by, branch_id, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        } else {
            $sql = "INSERT INTO stock_movements 
                    (equipment_id, patient_id, movement_type, quantity, previous_stock, new_stock, 
                     reference_type, reference_id, performed_by, branch_id, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $item_id, $patient_id, $movement_type, $quantity, 
            $previous_stock, $new_stock, 
            $reference_type, $reference_id, $performed_by, $branch_id, $notes
        ]);
        return $db->lastInsertId();
    } catch (Exception $e) {
        error_log("logStockMovement error: " . $e->getMessage());
        return false;
    }
}

// ================================================================
// HELPER: Recalculate bill total
// ================================================================
function recalculateBill($db, $bill_id) {
    try {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(total_price), 0) as subtotal 
            FROM bill_items 
            WHERE bill_id = ? AND status != 'cancelled'
        ");
        $stmt->execute([$bill_id]);
        $subtotal = (float)($stmt->fetch(PDO::FETCH_ASSOC)['subtotal'] ?? 0);
        
        $stmt = $db->prepare("
            SELECT discount_amount, pharmacy_discount, cashier_discount, 
                   total_discount, premium_amount, paid_amount
            FROM bills WHERE id = ?
        ");
        $stmt->execute([$bill_id]);
        $bill_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $pharmacy_discount = (float)($bill_data['pharmacy_discount'] ?? 0);
        if ($pharmacy_discount == 0) {
            $pharmacy_discount = (float)($bill_data['discount_amount'] ?? 0);
        }
        $cashier_discount = (float)($bill_data['cashier_discount'] ?? 0);
        $total_discount = $pharmacy_discount + $cashier_discount;
        $premium_amount = (float)($bill_data['premium_amount'] ?? 0);
        
        if ($total_discount > $subtotal) $total_discount = $subtotal;
        
        $total_amount = $subtotal + $premium_amount - $total_discount;
        if ($total_amount < 0) $total_amount = 0;
        
        $paid_amount = (float)($bill_data['paid_amount'] ?? 0);
        $balance = $total_amount - $paid_amount;
        if ($balance < 0) $balance = 0;
        if ($paid_amount > $total_amount) $paid_amount = $total_amount;
        
        if ($total_amount <= 0) {
            $status = 'paid';
        } elseif ($balance <= 0.01) {
            $status = 'paid';
        } elseif ($paid_amount > 0 && $balance > 0) {
            $status = 'partial';
        } else {
            $status = 'pending';
        }
        
        $stmt = $db->prepare("
            UPDATE bills 
            SET subtotal = ?, total_amount = ?, paid_amount = ?, balance = ?, 
                status = ?, total_discount = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$subtotal, $total_amount, $paid_amount, $balance, $status, $total_discount, $bill_id]);
        
        return [
            'subtotal' => $subtotal,
            'total' => $total_amount,
            'paid' => $paid_amount,
            'balance' => $balance,
            'status' => $status
        ];
    } catch (Exception $e) {
        error_log("Recalculate bill error: " . $e->getMessage());
        return null;
    }
}

// ================================================================
// HELPER: Return medication stock
// ================================================================
function returnMedicationStock($db, $prescription_id, $patient_id, $user_id, $branch_id, $reason) {
    $returned_count = 0;
    $returned_qty = 0;
    
    try {
        $stmt = $db->prepare("
            SELECT pi.id, pi.inventory_id, pi.medication_name, pi.quantity
            FROM prescription_items pi
            WHERE pi.prescription_id = ?
        ");
        $stmt->execute([$prescription_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $qty = (int)$item['quantity'];
            if ($qty <= 0) continue;
            
            $inv_id = (int)$item['inventory_id'];
            
            if ($inv_id > 0) {
                $stmt_stk = $db->prepare("
                    SELECT id, quantity FROM medications_inventory 
                    WHERE id = ? AND branch_id = ?
                    FOR UPDATE
                ");
                $stmt_stk->execute([$inv_id, $branch_id]);
                $stock = $stmt_stk->fetch(PDO::FETCH_ASSOC);
                
                if ($stock) {
                    $prev = (int)$stock['quantity'];
                    $new = $prev + $qty;
                    
                    $db->prepare("UPDATE medications_inventory SET quantity = ?, updated_at = NOW() WHERE id = ?")
                       ->execute([$new, $stock['id']]);
                    
                    logStockMovementHelper($db, 'medicine', $stock['id'], $patient_id, 'in', $qty,
                        $prev, $new, 'prescription_delete', $prescription_id,
                        $user_id, $branch_id, $reason);
                    
                    $returned_count++;
                    $returned_qty += $qty;
                }
            } else {
                $stmt_stk = $db->prepare("
                    SELECT id, quantity FROM medications_inventory 
                    WHERE medication_name = ? AND branch_id = ? AND status = 'active'
                    ORDER BY expiry_date ASC
                    LIMIT 1
                    FOR UPDATE
                ");
                $stmt_stk->execute([$item['medication_name'], $branch_id]);
                $stock = $stmt_stk->fetch(PDO::FETCH_ASSOC);
                
                if ($stock) {
                    $prev = (int)$stock['quantity'];
                    $new = $prev + $qty;
                    
                    $db->prepare("UPDATE medications_inventory SET quantity = ?, updated_at = NOW() WHERE id = ?")
                       ->execute([$new, $stock['id']]);
                    
                    logStockMovementHelper($db, 'medicine', $stock['id'], $patient_id, 'in', $qty,
                        $prev, $new, 'prescription_delete', $prescription_id,
                        $user_id, $branch_id, $reason);
                    
                    $returned_count++;
                    $returned_qty += $qty;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Return medication stock error: " . $e->getMessage());
    }
    
    return ['count' => $returned_count, 'qty' => $returned_qty];
}

// ================================================================
// HELPER: Return equipment stock for lab test
// ================================================================
function returnEquipmentStockForLabTest($db, $lab_test_id, $patient_id, $user_id, $branch_id, $reason) {
    $returned_count = 0;
    $returned_qty = 0;
    
    try {
        $stmt = $db->prepare("
            SELECT lt.test_id, lt.equipment_id, lt.equipment_used
            FROM lab_tests lt WHERE lt.id = ?
        ");
        $stmt->execute([$lab_test_id]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) return ['count' => 0, 'qty' => 0];
        
        $stmt_links = $db->prepare("
            SELECT lte.equipment_id
            FROM lab_test_equipment lte
            WHERE lte.lab_test_id = ?
        ");
        $stmt_links->execute([$test['test_id']]);
        $equipment_links = $stmt_links->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($equipment_links) && !empty($test['equipment_id'])) {
            $equipment_links = [['equipment_id' => $test['equipment_id']]];
        }
        
        $qty_per_eq = 1;
        if (count($equipment_links) == 1 && !empty($test['equipment_used'])) {
            $qty_per_eq = (int)$test['equipment_used'];
            if ($qty_per_eq <= 0) $qty_per_eq = 1;
        }
        
        foreach ($equipment_links as $link) {
            $eq_id = (int)$link['equipment_id'];
            if ($eq_id <= 0) continue;
            
            $stmt_eq = $db->prepare("
                SELECT id, equipment_name, quantity 
                FROM medical_equipment 
                WHERE id = ? AND branch_id = ?
                FOR UPDATE
            ");
            $stmt_eq->execute([$eq_id, $branch_id]);
            $equip = $stmt_eq->fetch(PDO::FETCH_ASSOC);
            
            if (!$equip) continue;
            
            $prev = (int)$equip['quantity'];
            $new = $prev + $qty_per_eq;
            
            $db->prepare("UPDATE medical_equipment SET quantity = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$new, $equip['id']]);
            
            logStockMovementHelper($db, 'equipment', $equip['id'], $patient_id, 'in', $qty_per_eq,
                $prev, $new, 'lab_test_delete', $lab_test_id,
                $user_id, $branch_id, $reason);
            
            $returned_count++;
            $returned_qty += $qty_per_eq;
        }
    } catch (Exception $e) {
        error_log("Return equipment stock error: " . $e->getMessage());
    }
    
    return ['count' => $returned_count, 'qty' => $returned_qty];
}

// ================================================================
// HELPER: Return equipment stock for bill item
// ================================================================
function returnEquipmentStockForBillItem($db, $bill_item_id, $patient_id, $user_id, $branch_id, $reason) {
    try {
        $stmt = $db->prepare("
            SELECT id, item_id, item_name, quantity 
            FROM bill_items 
            WHERE id = ? AND item_type = 'equipment' AND status != 'cancelled'
        ");
        $stmt->execute([$bill_item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) return ['count' => 0, 'qty' => 0];
        
        $eq_id = (int)($item['item_id'] ?? 0);
        $qty = (int)($item['quantity'] ?? 1);
        if ($qty <= 0) $qty = 1;
        
        if ($eq_id <= 0) {
            $clean_name = preg_replace('/\s*\(FREE\)\s*$/i', '', $item['item_name']);
            $stmt_eq = $db->prepare("
                SELECT id FROM medical_equipment 
                WHERE equipment_name = ? AND branch_id = ?
                LIMIT 1
            ");
            $stmt_eq->execute([$clean_name, $branch_id]);
            $eq_row = $stmt_eq->fetch(PDO::FETCH_ASSOC);
            $eq_id = $eq_row ? (int)$eq_row['id'] : 0;
        }
        
        if ($eq_id <= 0) return ['count' => 0, 'qty' => 0];
        
        $stmt_eq = $db->prepare("
            SELECT id, equipment_name, quantity 
            FROM medical_equipment 
            WHERE id = ? AND branch_id = ?
            FOR UPDATE
        ");
        $stmt_eq->execute([$eq_id, $branch_id]);
        $equip = $stmt_eq->fetch(PDO::FETCH_ASSOC);
        
        if (!$equip) return ['count' => 0, 'qty' => 0];
        
        $prev = (int)$equip['quantity'];
        $new = $prev + $qty;
        
        $db->prepare("UPDATE medical_equipment SET quantity = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$new, $equip['id']]);
        
        logStockMovementHelper($db, 'equipment', $equip['id'], $patient_id, 'in', $qty,
            $prev, $new, 'bill_item_delete', $bill_item_id,
            $user_id, $branch_id, $reason);
        
        return ['count' => 1, 'qty' => $qty, 'name' => $equip['equipment_name']];
    } catch (Exception $e) {
        error_log("Return equipment stock for bill item error: " . $e->getMessage());
        return ['count' => 0, 'qty' => 0];
    }
}

// ================================================================
// HANDLE POST ACTIONS
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // ============================================================
    // DELETE PRESCRIPTION - Full deletion with stock return
    // ============================================================
    if ($action === 'delete_prescription') {
        $prescription_id = (int)($_POST['prescription_id'] ?? 0);
        
        if ($prescription_id > 0) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("SELECT * FROM prescriptions WHERE id = ? AND patient_id = ?");
                $stmt->execute([$prescription_id, $patient_id]);
                $presc = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$presc) throw new Exception("Prescription not found");
                
                $presc_status = $presc['status'];
                $presc_number = $presc['prescription_number'];
                
                $should_return_stock = in_array($presc_status, ['pending', 'confirmed']);
                $should_reduce_bill = in_array($presc_status, ['pending', 'confirmed']);
                
                $stock_result = ['count' => 0, 'qty' => 0];
                $bills_affected = [];
                
                if ($should_return_stock) {
                    $stock_result = returnMedicationStock(
                        $db, $prescription_id, $patient_id, $user_id, $branch_id,
                        "Stock returned - Deleted {$presc_status} Rx #{$presc_number}"
                    );
                }
                
                if ($should_reduce_bill) {
                    $stmt_bills = $db->prepare("
                        SELECT DISTINCT b.id, b.bill_number
                        FROM bills b
                        INNER JOIN bill_items bi ON b.id = bi.bill_id
                        WHERE bi.reference_id = ? 
                          AND bi.reference_type = 'prescription'
                    ");
                    $stmt_bills->execute([$prescription_id]);
                    $related_bills = $stmt_bills->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($related_bills as $bill) {
                        $db->prepare("
                            DELETE FROM bill_items 
                            WHERE bill_id = ? 
                              AND reference_id = ? 
                              AND reference_type = 'prescription'
                        ")->execute([$bill['id'], $prescription_id]);
                        
                        recalculateBill($db, $bill['id']);
                        $bills_affected[] = $bill['bill_number'];
                    }
                }
                
                $db->prepare("DELETE FROM prescription_items WHERE prescription_id = ?")->execute([$prescription_id]);
                $db->prepare("DELETE FROM prescriptions WHERE id = ? AND patient_id = ?")->execute([$prescription_id, $patient_id]);
                
                try {
                    $log_desc = "Deleted prescription: {$presc_number} (Status: {$presc_status})";
                    if ($stock_result['count'] > 0) {
                        $log_desc .= " | Stock returned: {$stock_result['count']} item(s) (qty: {$stock_result['qty']})";
                    }
                    if (!empty($bills_affected)) {
                        $log_desc .= " | Bills affected: " . implode(', ', $bills_affected);
                    }
                    
                    $db->prepare("
                        INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                        VALUES (?, ?, 'DELETE_PRESCRIPTION', ?, ?, NOW())
                    ")->execute([
                        $user_id, $branch_id, $log_desc, $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                } catch (Exception $e) {}
                
                $db->commit();
                
                $flash_msg = "✅ <strong>Prescription deleted successfully!</strong>";
                if ($stock_result['count'] > 0) {
                    $flash_msg .= "<br>📦 Stock returned: <strong>{$stock_result['count']}</strong> item(s) (Total qty: {$stock_result['qty']})";
                }
                if (!empty($bills_affected)) {
                    $flash_msg .= "<br>💰 Bills reduced: <strong>" . implode(', ', $bills_affected) . "</strong>";
                }
                if (!$should_return_stock) {
                    $flash_msg .= "<br>⚠️ Status: {$presc_status} - Stock NOT returned";
                }
                
                $_SESSION['flash_message'] = $flash_msg;
                $_SESSION['flash_type'] = 'success';
                header('Location: patient_details.php?id=' . $patient_id);
                exit;
                
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $_SESSION['flash_message'] = "❌ Error: " . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
                header('Location: patient_details.php?id=' . $patient_id);
                exit;
            }
        }
    }
    
    // ============================================================
    // DELETE LAB TEST - Full deletion with equipment stock return
    // ============================================================
    if ($action === 'delete_lab_test') {
        $test_id = (int)($_POST['test_id'] ?? 0);
        
        if ($test_id > 0) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("
                    SELECT lt.*, v.visit_number
                    FROM lab_tests lt
                    LEFT JOIN visits v ON lt.visit_id = v.id
                    WHERE lt.id = ?
                ");
                $stmt->execute([$test_id]);
                $test = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$test) throw new Exception("Lab test not found");
                
                $should_return_stock = in_array($test['status'], ['pending', 'in_progress']);
                $should_reduce_bill = in_array($test['status'], ['pending', 'in_progress']);
                
                $stock_result = ['count' => 0, 'qty' => 0];
                $bills_affected = [];
                
                if ($should_return_stock) {
                    $stock_result = returnEquipmentStockForLabTest(
                        $db, $test_id, $patient_id, $user_id, $branch_id,
                        "Stock returned - Deleted lab test: {$test['test_name']} (Status: {$test['status']})"
                    );
                }
                
                if ($should_reduce_bill) {
                    $stmt_bills = $db->prepare("
                        SELECT DISTINCT b.id, b.bill_number
                        FROM bills b
                        INNER JOIN bill_items bi ON b.id = bi.bill_id
                        WHERE bi.reference_id = ? 
                          AND bi.reference_type = 'lab_test'
                    ");
                    $stmt_bills->execute([$test_id]);
                    $related_bills = $stmt_bills->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($related_bills as $bill) {
                        $db->prepare("
                            DELETE FROM bill_items 
                            WHERE bill_id = ? 
                              AND reference_id = ? 
                              AND reference_type = 'lab_test'
                        ")->execute([$bill['id'], $test_id]);
                        
                        recalculateBill($db, $bill['id']);
                        $bills_affected[] = $bill['bill_number'];
                    }
                }
                
                $db->prepare("DELETE FROM lab_tests WHERE id = ?")->execute([$test_id]);
                
                try {
                    $log_desc = "Deleted lab test: {$test['test_name']} (Status: {$test['status']})";
                    if ($stock_result['count'] > 0) {
                        $log_desc .= " | Equipment returned: {$stock_result['count']} item(s) (qty: {$stock_result['qty']})";
                    }
                    if (!empty($bills_affected)) {
                        $log_desc .= " | Bills affected: " . implode(', ', $bills_affected);
                    }
                    
                    $db->prepare("
                        INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                        VALUES (?, ?, 'DELETE_LAB_TEST', ?, ?, NOW())
                    ")->execute([
                        $user_id, $branch_id, $log_desc, $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                } catch (Exception $e) {}
                
                $db->commit();
                
                $flash_msg = "✅ <strong>Lab test deleted successfully!</strong>";
                if ($stock_result['count'] > 0) {
                    $flash_msg .= "<br>📦 Equipment stock returned: <strong>{$stock_result['count']}</strong> item(s) (Total qty: {$stock_result['qty']})";
                }
                if (!empty($bills_affected)) {
                    $flash_msg .= "<br>💰 Bills reduced: <strong>" . implode(', ', $bills_affected) . "</strong>";
                }
                if (!$should_return_stock) {
                    $flash_msg .= "<br>⚠️ Status: {$test['status']} - Stock NOT returned";
                }
                
                $_SESSION['flash_message'] = $flash_msg;
                $_SESSION['flash_type'] = 'success';
                header('Location: patient_details.php?id=' . $patient_id);
                exit;
                
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $_SESSION['flash_message'] = "❌ Error: " . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
                header('Location: patient_details.php?id=' . $patient_id);
                exit;
            }
        }
    }
    
    // ============================================================
    // DELETE BILL ITEM (Procedure/Equipment)
    // ============================================================
    if ($action === 'delete_bill_item') {
        $bill_item_id = (int)($_POST['bill_item_id'] ?? 0);
        $item_type = $_POST['item_type'] ?? '';
        
        if ($bill_item_id > 0) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("
                    SELECT bi.*, b.bill_number, b.patient_id
                    FROM bill_items bi
                    INNER JOIN bills b ON bi.bill_id = b.id
                    WHERE bi.id = ? AND b.patient_id = ?
                ");
                $stmt->execute([$bill_item_id, $patient_id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$item) throw new Exception("Bill item not found");
                
                $should_return_stock = ($item['status'] !== 'paid');
                $stock_result = ['count' => 0, 'qty' => 0];
                
                if ($should_return_stock && $item['item_type'] === 'equipment') {
                    $stock_result = returnEquipmentStockForBillItem(
                        $db, $bill_item_id, $patient_id, $user_id, $branch_id,
                        "Stock returned - Deleted {$item['item_type']}: {$item['item_name']}"
                    );
                }
                
                $db->prepare("DELETE FROM bill_items WHERE id = ?")->execute([$bill_item_id]);
                recalculateBill($db, $item['bill_id']);
                
                try {
                    $db->prepare("
                        INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                        VALUES (?, ?, 'DELETE_BILL_ITEM', ?, ?, NOW())
                    ")->execute([
                        $user_id, $branch_id,
                        "Deleted bill item: {$item['item_name']} ({$item['item_type']}) | Bill: {$item['bill_number']}",
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                } catch (Exception $e) {}
                
                $db->commit();
                
                $flash_msg = "✅ <strong>Item deleted from bill!</strong>";
                if ($stock_result['count'] > 0) {
                    $flash_msg .= "<br>📦 Equipment stock returned: <strong>{$stock_result['qty']}</strong>";
                }
                
                $_SESSION['flash_message'] = $flash_msg;
                $_SESSION['flash_type'] = 'success';
                header('Location: patient_details.php?id=' . $patient_id);
                exit;
                
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $_SESSION['flash_message'] = "❌ Error: " . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
                header('Location: patient_details.php?id=' . $patient_id);
                exit;
            }
        }
    }
    
    // ============================================================
    // DELETE ENTIRE VISIT - Full deletion of everything
    // ============================================================
    if ($action === 'delete_visit') {
        $visit_id = (int)($_POST['visit_id'] ?? 0);
        
        if ($visit_id > 0) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("SELECT * FROM visits WHERE id = ? AND patient_id = ?");
                $stmt->execute([$visit_id, $patient_id]);
                $visit = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$visit) throw new Exception("Visit not found");
                
                $visit_number = $visit['visit_number'];
                $total_stock_returned = 0;
                
                // 1. Return stock for medications (pending/confirmed only)
                $stmt = $db->prepare("
                    SELECT p.id, p.prescription_number, p.status
                    FROM prescriptions p
                    WHERE p.visit_id = ?
                ");
                $stmt->execute([$visit_id]);
                $prescriptions_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $med_stock_count = 0;
                foreach ($prescriptions_list as $presc) {
                    if (in_array($presc['status'], ['pending', 'confirmed'])) {
                        $result = returnMedicationStock(
                            $db, $presc['id'], $patient_id, $user_id, $branch_id,
                            "Stock returned - Deleted visit: {$visit_number} (Rx #{$presc['prescription_number']})"
                        );
                        $med_stock_count += $result['qty'];
                    }
                }
                
                // 2. Return equipment stock for lab tests
                $stmt = $db->prepare("
                    SELECT lt.id, lt.test_name, lt.status
                    FROM lab_tests lt
                    WHERE lt.visit_id = ?
                ");
                $stmt->execute([$visit_id]);
                $lab_tests_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $lab_stock_count = 0;
                foreach ($lab_tests_list as $lab) {
                    if (in_array($lab['status'], ['pending', 'in_progress'])) {
                        $result = returnEquipmentStockForLabTest(
                            $db, $lab['id'], $patient_id, $user_id, $branch_id,
                            "Stock returned - Deleted visit: {$visit_number} (Test: {$lab['test_name']})"
                        );
                        $lab_stock_count += $result['qty'];
                    }
                }
                
                // 3. Return equipment stock for bill items
                $stmt = $db->prepare("
                    SELECT bi.id, bi.item_name
                    FROM bill_items bi
                    INNER JOIN bills b ON bi.bill_id = b.id
                    WHERE b.visit_id = ? 
                      AND bi.item_type = 'equipment'
                      AND bi.status != 'paid'
                ");
                $stmt->execute([$visit_id]);
                $equip_bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $equip_stock_count = 0;
                foreach ($equip_bill_items as $eq_item) {
                    $result = returnEquipmentStockForBillItem(
                        $db, $eq_item['id'], $patient_id, $user_id, $branch_id,
                        "Stock returned - Deleted visit: {$visit_number} (Item: {$eq_item['item_name']})"
                    );
                    $equip_stock_count += $result['qty'];
                }
                
                // 4. Delete prescription_items
                $stmt = $db->prepare("
                    DELETE pi FROM prescription_items pi 
                    INNER JOIN prescriptions p ON pi.prescription_id = p.id 
                    WHERE p.visit_id = ?
                ");
                $stmt->execute([$visit_id]);
                $prescription_items_deleted = $stmt->rowCount();
                
                // 5. Delete prescriptions
                $stmt = $db->prepare("DELETE FROM prescriptions WHERE visit_id = ?");
                $stmt->execute([$visit_id]);
                $prescriptions_deleted = $stmt->rowCount();
                
                // 6. Delete lab tests
                $stmt = $db->prepare("DELETE FROM lab_tests WHERE visit_id = ?");
                $stmt->execute([$visit_id]);
                $lab_tests_deleted = $stmt->rowCount();
                
                // 7. Delete procedures
                try {
                    $stmt = $db->prepare("DELETE FROM procedures WHERE visit_id = ?");
                    $stmt->execute([$visit_id]);
                    $procedures_deleted = $stmt->rowCount();
                } catch (Exception $e) { $procedures_deleted = 0; }
                
                // 8. Delete bill items, payments, and bills
                $stmt = $db->prepare("SELECT id FROM bills WHERE visit_id = ?");
                $stmt->execute([$visit_id]);
                $visit_bills = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $bills_deleted = 0;
                $bill_items_deleted = 0;
                $payments_deleted = 0;
                
                if (!empty($visit_bills)) {
                    $placeholders = implode(',', array_fill(0, count($visit_bills), '?'));
                    
                    $stmt = $db->prepare("DELETE FROM payments WHERE bill_id IN ($placeholders)");
                    $stmt->execute($visit_bills);
                    $payments_deleted = $stmt->rowCount();
                    
                    $stmt = $db->prepare("DELETE FROM bill_items WHERE bill_id IN ($placeholders)");
                    $stmt->execute($visit_bills);
                    $bill_items_deleted = $stmt->rowCount();
                    
                    $stmt = $db->prepare("DELETE FROM bills WHERE visit_id = ?");
                    $stmt->execute([$visit_id]);
                    $bills_deleted = $stmt->rowCount();
                }
                
                // 9. Delete vital signs
                try {
                    $stmt = $db->prepare("DELETE FROM vital_signs WHERE visit_id = ?");
                    $stmt->execute([$visit_id]);
                    $vitals_deleted = $stmt->rowCount();
                } catch (Exception $e) { $vitals_deleted = 0; }
                
                // 10. Delete visit
                $stmt = $db->prepare("DELETE FROM visits WHERE id = ? AND patient_id = ?");
                $stmt->execute([$visit_id, $patient_id]);
                $visit_deleted = $stmt->rowCount();
                
                try {
                    $log_desc = "Deleted visit: {$visit_number}";
                    $log_desc .= " | Med stock returned: {$med_stock_count} qty";
                    $log_desc .= " | Lab equip returned: {$lab_stock_count} qty";
                    $log_desc .= " | Bill equip returned: {$equip_stock_count} qty";
                    $log_desc .= " | Bills: {$bills_deleted}, Items: {$bill_items_deleted}, Payments: {$payments_deleted}";
                    
                    $db->prepare("
                        INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                        VALUES (?, ?, 'DELETE_VISIT', ?, ?, NOW())
                    ")->execute([
                        $user_id, $branch_id, $log_desc, $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                } catch (Exception $e) {}
                
                $db->commit();
                
                $flash_msg = "✅ <strong>Visit deleted successfully!</strong>";
                $flash_msg .= "<br>📋 Visit: <strong>{$visit_number}</strong>";
                $flash_msg .= "<br>🗑️ Deleted:";
                if ($visit_deleted) $flash_msg .= " 1 visit,";
                if ($prescriptions_deleted) $flash_msg .= " {$prescriptions_deleted} prescription(s),";
                if ($prescription_items_deleted) $flash_msg .= " {$prescription_items_deleted} prescription item(s),";
                if ($lab_tests_deleted) $flash_msg .= " {$lab_tests_deleted} lab test(s),";
                if ($procedures_deleted) $flash_msg .= " {$procedures_deleted} procedure(s),";
                if ($bills_deleted) $flash_msg .= " {$bills_deleted} bill(s),";
                if ($bill_items_deleted) $flash_msg .= " {$bill_items_deleted} bill item(s),";
                if ($payments_deleted) $flash_msg .= " {$payments_deleted} payment(s),";
                if ($vitals_deleted) $flash_msg .= " {$vitals_deleted} vital sign(s),";
                $flash_msg = rtrim($flash_msg, ',');
                
                if ($med_stock_count + $lab_stock_count + $equip_stock_count > 0) {
                    $flash_msg .= "<br>📦 Stock returned:";
                    if ($med_stock_count) $flash_msg .= " Med: {$med_stock_count},";
                    if ($lab_stock_count) $flash_msg .= " Lab Equip: {$lab_stock_count},";
                    if ($equip_stock_count) $flash_msg .= " Bill Equip: {$equip_stock_count},";
                    $flash_msg = rtrim($flash_msg, ',');
                }
                
                $_SESSION['flash_message'] = $flash_msg;
                $_SESSION['flash_type'] = 'success';
                header('Location: patient_details.php?id=' . $patient_id);
                exit;
                
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $_SESSION['flash_message'] = "❌ Error: " . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
                header('Location: patient_details.php?id=' . $patient_id);
                exit;
            }
        }
    }
    
    // ============================================================
    // DELETE BILL (with all items & payments)
    // ============================================================
    if ($action === 'delete_bill') {
        $bill_id = (int)($_POST['bill_id'] ?? 0);
        
        if ($bill_id > 0) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("SELECT * FROM bills WHERE id = ? AND patient_id = ?");
                $stmt->execute([$bill_id, $patient_id]);
                $bill = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$bill) throw new Exception("Bill not found");
                
                $bill_number = $bill['bill_number'];
                
                $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ?");
                $stmt->execute([$bill_id]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stock_returned = 0;
                
                foreach ($items as $item) {
                    if ($item['item_type'] === 'equipment' && $item['status'] !== 'paid') {
                        $result = returnEquipmentStockForBillItem(
                            $db, $item['id'], $patient_id, $user_id, $branch_id,
                            "Stock returned - Deleted bill: {$bill_number} (Item: {$item['item_name']})"
                        );
                        $stock_returned += $result['qty'];
                    }
                }
                
                $stmt = $db->prepare("DELETE FROM payments WHERE bill_id = ?");
                $stmt->execute([$bill_id]);
                $payments_deleted = $stmt->rowCount();
                
                $stmt = $db->prepare("DELETE FROM bill_items WHERE bill_id = ?");
                $stmt->execute([$bill_id]);
                $items_deleted = $stmt->rowCount();
                
                $stmt = $db->prepare("DELETE FROM bills WHERE id = ? AND patient_id = ?");
                $stmt->execute([$bill_id, $patient_id]);
                
                try {
                    $db->prepare("
                        INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                        VALUES (?, ?, 'DELETE_BILL', ?, ?, NOW())
                    ")->execute([
                        $user_id, $branch_id,
                        "Deleted bill: {$bill_number} | Items: {$items_deleted}, Payments: {$payments_deleted}, Stock returned: {$stock_returned}",
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                } catch (Exception $e) {}
                
                $db->commit();
                
                $flash_msg = "✅ <strong>Bill deleted successfully!</strong>";
                $flash_msg .= "<br>🗑️ Deleted: {$items_deleted} item(s), {$payments_deleted} payment(s)";
                if ($stock_returned > 0) {
                    $flash_msg .= "<br>📦 Stock returned: <strong>{$stock_returned}</strong>";
                }
                
                $_SESSION['flash_message'] = $flash_msg;
                $_SESSION['flash_type'] = 'success';
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $_SESSION['flash_message'] = "❌ Error: " . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
            }
            header('Location: patient_details.php?id=' . $patient_id);
            exit;
        }
    }
}

// Flash messages
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// ================================================================
// LOAD PATIENT DATA
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as created_by_name, b.name as branch_name,
            doc.full_name as assigned_doctor_name, doc.is_online as assigned_doctor_online
        FROM patients p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN users doc ON p.assigned_doctor_id = doc.id
        WHERE p.id = ? AND p.branch_id = ?
    ");
    $stmt->execute([$patient_id, $branch_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        header('Location: patients.php?error=patient_not_found');
        exit;
    }
    
    $stmt = $db->prepare("
        SELECT v.*, u.full_name as doctor_name
        FROM visits v LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ? AND v.status IN ('pending', 'assigned', 'with_doctor', 'lab_test')
        ORDER BY v.created_at DESC LIMIT 1
    ");
    $stmt->execute([$patient_id]);
    $active_visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("
        SELECT v.*, u.full_name as doctor_name, b.total_amount as bill_amount, b.status as bill_status, b.bill_number
        FROM visits v 
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN bills b ON v.id = b.visit_id
        WHERE v.patient_id = ? ORDER BY v.created_at DESC LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $visit_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("
        SELECT b.*, v.visit_number, u.full_name as created_by_name
        FROM bills b
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.patient_id = ? ORDER BY b.created_at DESC LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($bills as $bill) {
        $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ? ORDER BY created_at DESC");
        $stmt->execute([$bill['id']]);
        $bill_items[$bill['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $stmt = $db->prepare("
        SELECT bi.*, b.patient_id, b.bill_number, b.created_at as bill_date
        FROM bill_items bi JOIN bills b ON bi.bill_id = b.id
        WHERE b.patient_id = ? AND bi.item_type = 'procedure'
        ORDER BY bi.created_at DESC LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("
        SELECT bi.*, b.patient_id, b.bill_number, b.created_at as bill_date
        FROM bill_items bi JOIN bills b ON bi.bill_id = b.id
        WHERE b.patient_id = ? AND bi.item_type = 'equipment'
        ORDER BY bi.created_at DESC LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("
        SELECT vs.*, u.full_name as recorded_by_name
        FROM vital_signs vs LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE vs.patient_id = ? ORDER BY vs.recorded_at DESC LIMIT 1
    ");
    $stmt->execute([$patient_id]);
    $latest_vitals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as doctor_name, v.visit_number, v.visit_date,
            (SELECT COUNT(*) FROM prescription_items pi WHERE pi.prescription_id = p.id AND pi.quantity > 0) as item_count,
            (SELECT COALESCE(SUM(pi.total_price), 0) FROM prescription_items pi WHERE pi.prescription_id = p.id AND pi.quantity > 0) as total_amount
        FROM prescriptions p 
        LEFT JOIN users u ON p.doctor_id = u.id
        LEFT JOIN visits v ON p.visit_id = v.id
        WHERE p.patient_id = ? 
        AND EXISTS (
            SELECT 1 FROM prescription_items pi_check 
            WHERE pi_check.prescription_id = p.id 
            AND pi_check.quantity > 0
        )
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $prescriptions_by_visit = [];
    foreach ($prescriptions as $presc) {
        $vid = $presc['visit_id'] ?? 0;
        if (!isset($prescriptions_by_visit[$vid])) {
            $prescriptions_by_visit[$vid] = [];
        }
        $prescriptions_by_visit[$vid][] = $presc;
    }
    
    $stmt = $db->prepare("
        SELECT lt.*, u.full_name as doctor_name
        FROM lab_tests lt LEFT JOIN users u ON lt.doctor_id = u.id
        WHERE lt.visit_id IN (SELECT id FROM visits WHERE patient_id = ?)
        ORDER BY lt.created_at DESC LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($patient['date_of_birth'])) {
        $birthDate = new DateTime($patient['date_of_birth']);
        $today = new DateTime('today');
        $age = $birthDate->diff($today)->y;
    }
    
    $branch_name = $patient['branch_name'] ?? $branch_name;
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
}

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<style>
:root {
    --page-bg-body: #F1F5F9;
    --page-bg-card: #FFFFFF;
    --page-text-primary: #1E293B;
    --page-text-secondary: #64748B;
    --page-text-muted: #94A3B8;
    --page-border: #E2E8F0;
    --page-hover: #F8FAFC;
}
[data-theme="dark"] {
    --page-bg-body: #0F172A;
    --page-bg-card: #1E293B;
    --page-text-primary: #F1F5F9;
    --page-text-secondary: #94A3B8;
    --page-text-muted: #64748B;
    --page-border: #334155;
    --page-hover: #0F172A;
}
body { background: var(--page-bg-body, #F1F5F9); }
html[data-theme="dark"] body { background: #0F172A !important; }
.main-content { background: var(--page-bg-body, #F1F5F9); }
html[data-theme="dark"] .main-content { background: #0F172A !important; color: #F1F5F9; }

/* PAGE HEADER */
.page-header-custom {
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083D8A 100%);
    border-radius: 20px;
    padding: 28px 32px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 12px 40px rgba(11, 94, 215, 0.35);
    position: relative;
    overflow: hidden;
}
.page-header-custom::before {
    content: '';
    position: absolute;
    top: -60%; right: -10%;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.page-header-custom::after {
    content: '';
    position: absolute;
    bottom: -40%; left: -5%;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.page-header-custom .page-title {
    color: white;
    font-size: 1.6rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin: 0;
}
.page-header-custom .page-title i {
    width: 48px; height: 48px;
    background: rgba(255,255,255,0.2);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
}
.page-header-custom .page-subtitle {
    color: rgba(255,255,255,0.95);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin-top: 10px;
}
.page-header-custom .header-badge {
    background: rgba(255,255,255,0.18);
    color: white;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.15);
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.btn-glass {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: rgba(255,255,255,0.18);
    border: 1.5px solid rgba(255,255,255,0.3);
    border-radius: 10px;
    color: white;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.82rem;
    transition: all 0.3s ease;
    cursor: pointer;
    white-space: nowrap;
    backdrop-filter: blur(10px);
}
.btn-glass:hover {
    background: rgba(255,255,255,0.32);
    transform: translateY(-2px);
    color: white;
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}
.btn-glass.pdf-btn {
    background: rgba(220,38,38,0.4);
    border-color: rgba(220,38,38,0.6);
}
.btn-glass.pdf-btn:hover { background: rgba(220,38,38,0.6); }

/* ALERTS */
.alert-box {
    padding: 18px 22px;
    border-radius: 14px;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    font-size: 0.9rem;
    font-weight: 500;
    border-left: 5px solid;
    animation: slideDown 0.4s ease;
    line-height: 1.6;
}
.alert-box.success { background: #D1FAE5; color: #065F46; border-left-color: #059669; }
.alert-box.error { background: #FEE2E2; color: #991B1B; border-left-color: #DC2626; }
.alert-box.info { background: #E8F0FE; color: #0A4CA8; border-left-color: #0B5ED7; }
html[data-theme="dark"] .alert-box.success { background: #1A3A2A; color: #34D399; }
html[data-theme="dark"] .alert-box.error { background: #3A1A1A; color: #F87171; }
html[data-theme="dark"] .alert-box.info { background: #1E3A5F; color: #6EA8FE; }
.alert-box i { font-size: 1.4rem; flex-shrink: 0; margin-top: 2px; }
.alert-box strong { font-weight: 800; }
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* PROFILE HEADER */
.profile-header {
    background: var(--page-bg-card, #FFFFFF);
    border-radius: 20px;
    padding: 28px 32px;
    border: 2px solid #6EA8FE;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    gap: 24px;
    align-items: center;
    box-shadow: 0 8px 24px rgba(11, 94, 215, 0.12);
    position: relative;
    overflow: hidden;
}
.profile-header::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #0B5ED7, #1A73E8, #6EA8FE);
}
html[data-theme="dark"] .profile-header { background: #1E293B; border-color: #3B82F6; }
.profile-avatar {
    width: 100px; height: 100px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.8rem;
    font-weight: 800;
    color: #ffffff;
    flex-shrink: 0;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    border: 4px solid rgba(255,255,255,0.3);
}
.profile-avatar.avatar-male { background: linear-gradient(135deg, #0B5ED7, #1A73E8); }
.profile-avatar.avatar-female { background: linear-gradient(135deg, #DC2626, #EF4444); }
.profile-avatar.avatar-other { background: linear-gradient(135deg, #7C3AED, #9B4DCA); }
.profile-info { flex: 1; min-width: 200px; }
.profile-info .patient-name {
    font-size: 1.7rem;
    font-weight: 800;
    color: var(--page-text-primary, #1E293B);
    letter-spacing: -0.02em;
}
html[data-theme="dark"] .profile-info .patient-name { color: #F1F5F9; }
.profile-info .patient-id {
    font-size: 0.8rem;
    font-family: 'Monaco', monospace;
    color: var(--page-text-secondary, #64748B);
    background: var(--page-hover, #F8FAFC);
    padding: 4px 14px;
    border-radius: 12px;
    display: inline-block;
    margin-left: 10px;
    font-weight: 700;
    border: 1px solid var(--page-border);
}
html[data-theme="dark"] .profile-info .patient-id { background: #0F172A; color: #94A3B8; }
.profile-info .patient-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 12px;
}
.profile-info .patient-meta .meta-item {
    font-size: 0.82rem;
    color: var(--page-text-secondary, #64748B);
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    background: var(--page-hover, #F8FAFC);
    border-radius: 8px;
    border: 1px solid var(--page-border);
    font-weight: 500;
}
.profile-info .patient-meta .meta-item i { color: #0B5ED7; width: 14px; }
html[data-theme="dark"] .profile-info .patient-meta .meta-item i { color: #6EA8FE; }
.profile-actions { display: flex; gap: 10px; flex-wrap: wrap; }

/* BUTTONS */
.btn-custom {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.78rem;
    transition: all 0.25s ease;
    cursor: pointer;
    border: none;
    text-decoration: none;
    white-space: nowrap;
    letter-spacing: 0.01em;
}
.btn-primary-custom { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; }
.btn-primary-custom:hover { color: white; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(11, 94, 215, 0.35); }
.btn-success-custom { background: linear-gradient(135deg, #059669, #047857); color: white; }
.btn-success-custom:hover { background: linear-gradient(135deg, #047857, #065F46); color: white; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(5, 150, 105, 0.35); }
.btn-warning-custom { background: linear-gradient(135deg, #F59E0B, #D97706); color: white; }
.btn-warning-custom:hover { background: linear-gradient(135deg, #D97706, #B45309); color: white; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(245, 158, 11, 0.35); }
.btn-danger-custom { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; }
.btn-danger-custom:hover { background: linear-gradient(135deg, #B91C1C, #991B1B); color: white; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(220, 38, 38, 0.35); }
.btn-outline-custom {
    background: transparent;
    color: var(--page-text-secondary, #64748B);
    border: 2px solid var(--page-border, #E2E8F0);
}
.btn-outline-custom:hover {
    background: var(--page-hover, #F1F5F9);
    border-color: #0B5ED7;
    color: #0B5ED7;
    transform: translateY(-2px);
}
.btn-xs-custom { padding: 7px 12px; font-size: 0.7rem; border-radius: 8px; min-width: 36px; justify-content: center; }
.action-buttons { display: flex; gap: 5px; flex-wrap: wrap; justify-content: center; align-items: center; }

/* CARDS */
.detail-card {
    background: var(--page-bg-card, #FFFFFF);
    border-radius: 18px;
    padding: 24px 28px;
    border: 2px solid var(--page-border, #E2E8F0);
    margin-bottom: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    transition: all 0.3s ease;
}
html[data-theme="dark"] .detail-card { background: #1E293B; border-color: #334155; }
.detail-card:hover { border-color: #0B5ED7; box-shadow: 0 8px 24px rgba(11, 94, 215, 0.1); }
.detail-card .card-title {
    font-size: 1rem;
    font-weight: 800;
    color: var(--page-text-primary, #1E293B);
    border-bottom: 2px solid var(--page-border, #E2E8F0);
    padding-bottom: 14px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
html[data-theme="dark"] .detail-card .card-title { color: #F1F5F9; border-bottom-color: #334155; }
.detail-card .card-title i { 
    color: #0B5ED7; 
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #E8F0FE, #D6E4FF);
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}
html[data-theme="dark"] .detail-card .card-title i { color: #6EA8FE; background: #1E3A5F; }

.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; }
.detail-grid .detail-item {
    display: flex;
    flex-direction: column;
    padding: 10px 0;
    border-bottom: 1px dashed var(--page-border, #E2E8F0);
}
html[data-theme="dark"] .detail-grid .detail-item { border-bottom-color: #334155; }
.detail-grid .detail-item .detail-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--page-text-secondary, #64748B);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.detail-grid .detail-item .detail-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--page-text-primary, #1E293B);
    margin-top: 3px;
}
html[data-theme="dark"] .detail-grid .detail-item .detail-value { color: #F1F5F9; }

/* VITAL SIGNS */
.vital-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
.vital-grid.second-row { margin-top: 12px; }
.vital-item {
    background: linear-gradient(135deg, #E8F0FE, #D6E4FF);
    border-radius: 12px;
    padding: 14px 12px;
    border-left: 4px solid #0B5ED7;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
}
html[data-theme="dark"] .vital-item { background: linear-gradient(135deg, #1E3A5F, #1E40AF); }
.vital-item:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 8px 24px rgba(11, 94, 215, 0.2); }
.vital-item .vital-label {
    font-size: 0.62rem;
    font-weight: 800;
    color: var(--page-text-secondary, #64748B);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: block;
}
.vital-item .vital-value {
    font-size: 1.15rem;
    font-weight: 900;
    color: #0A4CA8;
    margin-top: 2px;
    display: block;
}
html[data-theme="dark"] .vital-item .vital-value { color: #6EA8FE; }
.vital-item .vital-unit { font-size: 0.6rem; font-weight: 600; color: var(--page-text-secondary, #64748B); }
.vital-item.green { border-left-color: #059669; }
.vital-item.green .vital-value { color: #059669; }
.vital-item.purple { border-left-color: #7C3AED; }
.vital-item.purple .vital-value { color: #7C3AED; }
.vital-item.orange { border-left-color: #F59E0B; }
.vital-item.orange .vital-value { color: #F59E0B; }
.vital-item.teal { border-left-color: #0D9488; }
.vital-item.teal .vital-value { color: #0D9488; }
.vital-item.red { border-left-color: #DC2626; }
.vital-item.red .vital-value { color: #DC2626; }
.vital-item.cyan { border-left-color: #0891B2; }
.vital-item.cyan .vital-value { color: #0891B2; }

/* BADGES */
.badge-custom {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.65rem;
    font-weight: 800;
    padding: 4px 12px;
    border-radius: 20px;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}
.badge-success { background: #D1FAE5; color: #059669; border: 1px solid #6EE7B7; }
.badge-danger { background: #FEE2E2; color: #DC2626; border: 1px solid #FCA5A5; }
.badge-warning { background: #FEF3C7; color: #D97706; border: 1px solid #FCD34D; }
.badge-info { background: #E8F0FE; color: #0B5ED7; border: 1px solid #93C5FD; }
.badge-purple { background: #EDE9FE; color: #7C3AED; border: 1px solid #C4B5FD; }
.badge-secondary { background: #E2E8F0; color: #64748B; border: 1px solid #CBD5E1; }
html[data-theme="dark"] .badge-success { background: #1A3A2A; color: #34D399; border-color: #065F46; }
html[data-theme="dark"] .badge-danger { background: #3A1A1A; color: #F87171; border-color: #991B1B; }
html[data-theme="dark"] .badge-warning { background: #3A2A1A; color: #FBBF24; border-color: #B45309; }
html[data-theme="dark"] .badge-info { background: #1E3A5F; color: #6EA8FE; border-color: #1E40AF; }
html[data-theme="dark"] .badge-purple { background: #2D1B4E; color: #A78BFA; border-color: #6D28D9; }
html[data-theme="dark"] .badge-secondary { background: #334155; color: #94A3B8; border-color: #475569; }

/* TABLES */
.table-wrapper { overflow-x: auto; border-radius: 12px; }
.data-table-custom { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.data-table-custom thead th {
    text-align: left;
    padding: 12px 14px;
    font-weight: 800;
    font-size: 0.68rem;
    text-transform: uppercase;
    color: white;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    white-space: nowrap;
    letter-spacing: 0.05em;
}
.data-table-custom thead th:first-child { border-radius: 12px 0 0 0; }
.data-table-custom thead th:last-child { border-radius: 0 12px 0 0; }
.data-table-custom tbody td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--page-border, #E2E8F0);
    color: var(--page-text-primary, #1E293B);
    vertical-align: middle;
}
html[data-theme="dark"] .data-table-custom tbody td { color: #F1F5F9; border-bottom-color: #334155; }
.data-table-custom tbody tr:nth-child(even) td { background: var(--page-hover, #F8FAFC); }
html[data-theme="dark"] .data-table-custom tbody tr:nth-child(even) td { background: #1E3A5F; }
.data-table-custom tbody tr:hover td { background: #E8F0FE; }
html[data-theme="dark"] .data-table-custom tbody tr:hover td { background: #1E40AF; }

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--page-text-muted, #94A3B8);
}
.empty-state i {
    font-size: 3rem;
    color: var(--page-text-muted, #94A3B8);
    display: block;
    margin-bottom: 12px;
    opacity: 0.4;
}
.empty-state p {
    font-size: 0.9rem;
    font-weight: 500;
}

/* MODALS */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 9999;
    backdrop-filter: blur(6px);
    justify-content: center;
    align-items: center;
    padding: 20px;
}
.modal-overlay.active { display: flex; }
.modal-content {
    background: var(--page-bg-card, #FFFFFF);
    border-radius: 20px;
    max-width: 580px;
    width: 100%;
    padding: 28px 32px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: slideUp 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
}
html[data-theme="dark"] .modal-content { background: #1E293B; }
@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 2px solid var(--page-border, #E2E8F0);
}
.modal-header .modal-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: #DC2626;
    display: flex;
    align-items: center;
    gap: 10px;
}
.modal-header .modal-title i {
    width: 40px;
    height: 40px;
    background: #FEE2E2;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.modal-close {
    background: var(--page-hover, #F1F5F9);
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    font-size: 1.3rem;
    cursor: pointer;
    color: var(--page-text-secondary, #64748B);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.modal-close:hover { background: #FEE2E2; color: #DC2626; transform: rotate(90deg); }

.warning-box {
    background: linear-gradient(135deg, #FEE2E2, #FECACA);
    border: 2px solid #DC2626;
    border-radius: 14px;
    padding: 16px 18px;
    margin-bottom: 18px;
    display: flex;
    gap: 14px;
    align-items: flex-start;
}
html[data-theme="dark"] .warning-box { background: linear-gradient(135deg, #3A1A1A, #4A1A1A); border-color: #991B1B; }
.warning-box i { color: #DC2626; font-size: 1.6rem; flex-shrink: 0; margin-top: 2px; }
.warning-box .warning-text { font-size: 0.85rem; color: #991B1B; line-height: 1.6; }
html[data-theme="dark"] .warning-box .warning-text { color: #F87171; }
.warning-box .warning-text strong { display: block; font-size: 1rem; margin-bottom: 6px; font-weight: 800; }

.info-box-modal {
    background: linear-gradient(135deg, #E8F0FE, #D6E4FF);
    border-radius: 12px;
    padding: 16px 18px;
    margin-bottom: 18px;
    border: 1px solid #93C5FD;
}
html[data-theme="dark"] .info-box-modal { background: linear-gradient(135deg, #1E3A5F, #1E40AF); border-color: #1E40AF; }

.success-box-modal {
    background: linear-gradient(135deg, #D1FAE5, #A7F3D0);
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 18px;
    font-size: 0.82rem;
    color: #065F46;
    font-weight: 700;
    border: 1px solid #6EE7B7;
}
html[data-theme="dark"] .success-box-modal { background: linear-gradient(135deg, #1A3A2A, #065F46); color: #6EE7B7; border-color: #065F46; }

.modal-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    padding-top: 20px;
    border-top: 2px solid var(--page-border, #E2E8F0);
}
.btn-confirm-delete {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    color: white;
    padding: 12px 28px;
    border-radius: 10px;
    font-weight: 800;
    font-size: 0.85rem;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.25s;
}
.btn-confirm-delete:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(220, 38, 38, 0.4); }
.btn-cancel-modal {
    background: transparent;
    color: var(--page-text-secondary, #64748B);
    border: 2px solid var(--page-border, #E2E8F0);
    padding: 12px 28px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-cancel-modal:hover { border-color: #0B5ED7; color: #0B5ED7; }

.presc-status-pending { color: #D97706; font-weight: 800; }
.presc-status-confirmed { color: #0B5ED7; font-weight: 800; }
.presc-status-dispensed { color: #059669; font-weight: 800; }
.presc-status-cancelled { color: #DC2626; font-weight: 800; }

.delete-list {
    background: var(--page-hover, #F8FAFC);
    border: 1px solid var(--page-border, #E2E8F0);
    border-radius: 12px;
    padding: 16px 18px;
    margin-bottom: 18px;
    font-size: 0.82rem;
}
.delete-list .list-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 0;
    font-weight: 600;
}
.delete-list .list-item i { width: 18px; }

@media (max-width: 1024px) { .vital-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px) {
    .page-header-custom { padding: 20px; }
    .page-header-custom .page-title { font-size: 1.15rem; }
    .detail-grid { grid-template-columns: 1fr; }
    .profile-header { flex-direction: column; text-align: center; padding: 20px; }
    .profile-info .patient-meta { justify-content: center; }
    .profile-actions { justify-content: center; width: 100%; }
    .vital-grid { grid-template-columns: repeat(2, 1fr); }
    .action-buttons { flex-direction: column; }
    .action-buttons .btn-xs-custom { width: 100%; }
    .modal-content { padding: 22px; }
}
@media print {
    .btn-glass, .action-buttons, .profile-actions { display: none !important; }
}
</style>

<main class="main-content">

    <?php if ($patient): ?>
    
    <!-- FLASH MESSAGE -->
    <?php if ($message): ?>
        <div class="alert-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : ($message_type === 'info' ? 'fa-info-circle' : 'fa-exclamation-circle')) ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>
    
    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div style="position:relative;z-index:1;">
            <h1 class="page-title">
                <i class="fas fa-user-circle"></i>
                Patient Details
                <span class="header-badge"><?= strtoupper($role) ?></span>
            </h1>
            <p class="page-subtitle">
                <span><i class="fas fa-id-card"></i> <strong><?= htmlspecialchars($patient['full_name']) ?></strong></span>
                <span class="header-badge"><i class="fas fa-hashtag"></i> <?= htmlspecialchars($patient['patient_id']) ?></span>
                <span class="header-badge"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?></span>
            </p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="patients.php" class="btn-glass">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="export_patient_pdf.php?id=<?= $patient['id'] ?>&branch=<?= $branch_id ?>" 
               target="_blank" class="btn-glass pdf-btn">
                <i class="fas fa-file-pdf"></i> Export PDF
            </a>
        </div>
    </div>
    
    <!-- PROFILE HEADER -->
    <div class="profile-header">
        <?php
            $gender = $patient['gender'] ?? '';
            $avatar_class = 'avatar-other';
            if ($gender === 'Male') $avatar_class = 'avatar-male';
            elseif ($gender === 'Female') $avatar_class = 'avatar-female';
        ?>
        <div class="profile-avatar <?= $avatar_class ?>">
            <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
        </div>
        
        <div class="profile-info">
            <div>
                <span class="patient-name"><?= htmlspecialchars($patient['full_name']) ?></span>
                <span class="patient-id"><?= htmlspecialchars($patient['patient_id']) ?></span>
                <?php if (!empty($patient['assigned_doctor_name'])): ?>
                    <span class="badge-custom badge-info" style="margin-left:10px;">
                        <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($patient['assigned_doctor_name']) ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="patient-meta">
                <span class="meta-item"><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                <span class="meta-item"><i class="fas fa-calendar"></i> <?= !empty($patient['date_of_birth']) ? date('d M Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></span>
                <span class="meta-item"><i class="fas fa-clock"></i> <?= $age ?> yrs</span>
                <span class="meta-item"><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                <span class="meta-item"><i class="fas fa-tint"></i> <?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
            </div>
        </div>
        
        <div class="profile-actions">
            <a href="assign_doctor.php?patient_id=<?= $patient['id'] ?>" class="btn-custom btn-primary-custom btn-xs-custom">
                <i class="fas fa-user-md"></i> Assign Doctor
            </a>
            <a href="edit_patient.php?id=<?= $patient['id'] ?>" class="btn-custom btn-warning-custom btn-xs-custom">
                <i class="fas fa-edit"></i> Edit Patient
            </a>
            <button onclick="window.print()" class="btn-custom btn-outline-custom btn-xs-custom">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
    
    <!-- 1. PERSONAL INFO -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-user"></i> Personal Information
            <a href="edit_patient.php?id=<?= $patient['id'] ?>" class="btn-custom btn-warning-custom btn-xs-custom" style="margin-left:auto;">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>
        
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Full Name</span>
                <span class="detail-value"><?= htmlspecialchars($patient['full_name']) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Patient ID</span>
                <span class="detail-value" style="font-family:monospace;"><?= htmlspecialchars($patient['patient_id']) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Gender</span>
                <span class="detail-value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Date of Birth</span>
                <span class="detail-value"><?= !empty($patient['date_of_birth']) ? date('d M Y', strtotime($patient['date_of_birth'])) : 'N/A' ?> (<?= $age ?> yrs)</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Email</span>
                <span class="detail-value"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Blood Group</span>
                <span class="detail-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Marital Status</span>
                <span class="detail-value"><?= htmlspecialchars($patient['marital_status'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item" style="grid-column:1/-1;">
                <span class="detail-label">Address</span>
                <span class="detail-value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>
    
    <!-- 2. LATEST VITALS -->
    <?php if ($latest_vitals): 
        $spo2_value = !empty($latest_vitals['oxygen_saturation']) ? (int)$latest_vitals['oxygen_saturation'] : null;
    ?>
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-heartbeat" style="color:#DC2626;background:#FEE2E2;"></i>
            Latest Vital Signs (7 Signs)
            <span style="font-size:0.75rem;font-weight:400;color:var(--page-text-secondary);margin-left:auto;">
                <?= date('d M Y h:i A', strtotime($latest_vitals['recorded_at'])) ?>
            </span>
        </div>
        
        <div class="vital-grid">
            <div class="vital-item">
                <span class="vital-label">🌡️ Temp</span>
                <span class="vital-value"><?= $latest_vitals['temperature'] ?? 'N/A' ?> <span class="vital-unit">°C</span></span>
            </div>
            <div class="vital-item green">
                <span class="vital-label">❤️ BP</span>
                <span class="vital-value">
                    <?php if (!empty($latest_vitals['blood_pressure_systolic']) && !empty($latest_vitals['blood_pressure_diastolic'])): ?>
                        <?= $latest_vitals['blood_pressure_systolic'] ?>/<?= $latest_vitals['blood_pressure_diastolic'] ?>
                        <span class="vital-unit">mmHg</span>
                    <?php else: ?>N/A<?php endif; ?>
                </span>
            </div>
            <div class="vital-item purple">
                <span class="vital-label">💓 Pulse</span>
                <span class="vital-value"><?= $latest_vitals['pulse_rate'] ?? 'N/A' ?> <span class="vital-unit">bpm</span></span>
            </div>
            <div class="vital-item cyan">
                <span class="vital-label">🫁 SpO2</span>
                <span class="vital-value"><?= $spo2_value !== null ? $spo2_value : 'N/A' ?> <span class="vital-unit">%</span></span>
            </div>
        </div>
        
        <div class="vital-grid second-row">
            <div class="vital-item orange">
                <span class="vital-label">⚖️ Weight</span>
                <span class="vital-value"><?= $latest_vitals['weight'] ?? 'N/A' ?> <span class="vital-unit">kg</span></span>
            </div>
            <div class="vital-item teal">
                <span class="vital-label">📏 Height</span>
                <span class="vital-value"><?= $latest_vitals['height'] ?? 'N/A' ?> <span class="vital-unit">cm</span></span>
            </div>
            <div class="vital-item red">
                <span class="vital-label">📊 BMI</span>
                <span class="vital-value"><?= $latest_vitals['bmi'] ?? 'N/A' ?> <span class="vital-unit">kg/m²</span></span>
            </div>
            <div class="vital-item" style="visibility:hidden;"></div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- 3. VISIT HISTORY -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-clock"></i> Visit History
            <span style="font-size:0.75rem;font-weight:400;color:var(--page-text-secondary);">(Last 10 visits)</span>
            <a href="new_visit.php?patient_id=<?= $patient['id'] ?>" class="btn-custom btn-success-custom btn-xs-custom" style="margin-left:auto;">
                <i class="fas fa-plus"></i> New Visit
            </a>
        </div>
        
        <?php if (count($visit_history) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table-custom">
                    <thead>
                        <tr>
                            <th>Visit #</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Bill</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visit_history as $visit): ?>
                            <tr>
                                <td style="font-family:monospace;font-weight:700;"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></td>
                                <td><?= date('d M Y', strtotime($visit['created_at'])) ?></td>
                                <td><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-custom badge-<?= $visit['status'] ?? 'secondary' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'Pending')) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($visit['bill_amount'])): ?>
                                        <strong>TSh <?= number_format($visit['bill_amount'], 0) ?></strong>
                                    <?php else: ?>
                                        <span style="color:var(--page-text-muted);">No bill</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_visit.php?id=<?= $visit['id'] ?>" class="btn-custom btn-primary-custom btn-xs-custom" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_visit.php?id=<?= $visit['id'] ?>" class="btn-custom btn-warning-custom btn-xs-custom" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn-custom btn-danger-custom btn-xs-custom" 
                                                title="Delete Visit (EVERYTHING)"
                                                onclick="confirmDeleteVisit(<?= $visit['id'] ?>, '<?= addslashes($visit['visit_number']) ?>', '<?= addslashes($visit['doctor_name'] ?? 'N/A') ?>', '<?= date('d M Y', strtotime($visit['created_at'])) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clock"></i>
                <p>No visit history found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 4. PRESCRIPTIONS -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-prescription" style="color:#059669;background:#D1FAE5;"></i>
            Prescriptions
            <span style="font-size:0.75rem;font-weight:400;color:var(--page-text-secondary);">(<?= count($prescriptions) ?> total)</span>
        </div>
        
        <?php if (count($prescriptions) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table-custom">
                    <thead>
                        <tr>
                            <th>Prescription #</th>
                            <th>Visit #</th>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $presc): 
                            $status = $presc['status'] ?? 'pending';
                            $status_class = 'secondary';
                            if ($status === 'pending') $status_class = 'warning';
                            elseif ($status === 'confirmed') $status_class = 'info';
                            elseif ($status === 'dispensed') $status_class = 'success';
                            elseif ($status === 'cancelled') $status_class = 'danger';
                            
                            $item_count = (int)($presc['item_count'] ?? 0);
                            $total_amount = (float)($presc['total_amount'] ?? 0);
                        ?>
                            <tr>
                                <td style="font-family:monospace;font-weight:700;"><?= htmlspecialchars($presc['prescription_number'] ?? 'N/A') ?></td>
                                <td style="font-family:monospace;font-size:0.75rem;"><?= htmlspecialchars($presc['visit_number'] ?? 'N/A') ?></td>
                                <td><?= date('d M Y', strtotime($presc['created_at'])) ?></td>
                                <td><?= htmlspecialchars($presc['doctor_name'] ?? 'N/A') ?></td>
                                <td style="text-align:center;">
                                    <span class="badge-custom badge-info"><?= $item_count ?> item(s)</span>
                                </td>
                                <td><strong>TSh <?= number_format($total_amount, 0) ?></strong></td>
                                <td>
                                    <span class="badge-custom badge-<?= $status_class ?>">
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_prescription.php?id=<?= $presc['id'] ?>&patient_id=<?= $patient_id ?>" 
                                           class="btn-custom btn-primary-custom btn-xs-custom" 
                                           title="View Prescription" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($status === 'pending'): ?>
                                            <a href="edit_prescription.php?id=<?= $presc['id'] ?>&patient_id=<?= $patient_id ?>" 
                                               class="btn-custom btn-warning-custom btn-xs-custom" 
                                               title="Edit Prescription">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <button type="button" 
                                                class="btn-custom btn-danger-custom btn-xs-custom" 
                                                title="Delete Prescription (returns stock, reduces bill)"
                                                onclick="confirmDeletePrescription(
                                                    <?= $presc['id'] ?>, 
                                                    '<?= addslashes($presc['prescription_number'] ?? 'N/A') ?>',
                                                    '<?= $status ?>',
                                                    <?= $item_count ?>,
                                                    <?= $total_amount ?>
                                                )">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription"></i>
                <p>No prescriptions found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 5. LAB TESTS -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-flask" style="color:#0D9488;background:#CCFBF1;"></i>
            Lab Tests
            <span style="font-size:0.75rem;font-weight:400;color:var(--page-text-secondary);">(Last 10)</span>
        </div>
        
        <?php if (count($lab_tests) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table-custom">
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Results</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $test): 
                            $test_status = $test['status'] ?? 'pending';
                            $status_class = 'secondary';
                            if ($test_status === 'pending') $status_class = 'warning';
                            elseif ($test_status === 'in_progress') $status_class = 'info';
                            elseif ($test_status === 'completed') $status_class = 'success';
                            elseif ($test_status === 'cancelled') $status_class = 'danger';
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong></td>
                                <td><?= date('d M Y', strtotime($test['created_at'])) ?></td>
                                <td><?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-custom badge-<?= $status_class ?>">
                                        <?= ucfirst(str_replace('_', ' ', $test_status)) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($test['results'])): ?>
                                        <span style="color:#059669;font-weight:700;">✅ Available</span>
                                    <?php else: ?>
                                        <span style="color:var(--page-text-secondary);">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="lab_test_details.php?id=<?= $test['id'] ?>&branch=<?= $branch_id ?>" class="btn-custom btn-primary-custom btn-xs-custom" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../laboratory/edit_test.php?id=<?= $test['id'] ?>" class="btn-custom btn-warning-custom btn-xs-custom" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn-custom btn-danger-custom btn-xs-custom"
                                                title="Delete Lab Test (returns equipment stock)"
                                                onclick="confirmDeleteLabTest(<?= $test['id'] ?>, '<?= addslashes($test['test_name']) ?>', '<?= $test_status ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-flask"></i>
                <p>No lab tests found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 6. BILLS -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-receipt" style="color:#D97706;background:#FEF3C7;"></i>
            Bills
            <span style="font-size:0.75rem;font-weight:400;color:var(--page-text-secondary);">(Last 10)</span>
        </div>
        
        <?php if (count($bills) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table-custom">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Date</th>
                            <th>Visit</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $bill): ?>
                            <tr>
                                <td style="font-family:monospace;font-weight:700;"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                                <td><?= date('d M Y', strtotime($bill['created_at'])) ?></td>
                                <td style="font-family:monospace;"><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></td>
                                <td><strong>TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></strong></td>
                                <td style="color:#059669;font-weight:700;">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                <td style="color:<?= ($bill['balance'] ?? 0) > 0 ? '#DC2626' : '#059669' ?>;font-weight:700;">
                                    TSh <?= number_format($bill['balance'] ?? 0, 0) ?>
                                </td>
                                <td>
                                    <span class="badge-custom badge-<?= $bill['status'] ?? 'secondary' ?>">
                                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_bill.php?id=<?= $bill['id'] ?>" class="btn-custom btn-primary-custom btn-xs-custom" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_bill.php?id=<?= $bill['id'] ?>" class="btn-custom btn-warning-custom btn-xs-custom" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn-custom btn-danger-custom btn-xs-custom"
                                                title="Delete Bill (removes items, payments & returns stock)"
                                                onclick="confirmDeleteBill(<?= $bill['id'] ?>, '<?= addslashes($bill['bill_number']) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>No bills found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 7. PROCEDURES -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-syringe" style="color:#7C3AED;background:#EDE9FE;"></i>
            Procedures
            <span style="font-size:0.75rem;font-weight:400;color:var(--page-text-secondary);">(Last 10)</span>
        </div>
        
        <?php if (count($procedures) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table-custom">
                    <thead>
                        <tr>
                            <th>Procedure</th>
                            <th>Date</th>
                            <th>Qty</th>
                            <th>Total</th>
                            <th>Bill #</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($procedures as $procedure): 
                            $is_paid = ($procedure['status'] ?? 'pending') === 'paid';
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($procedure['item_name'] ?? 'N/A') ?></strong></td>
                                <td><?= date('d M Y', strtotime($procedure['created_at'])) ?></td>
                                <td><?= $procedure['quantity'] ?? 1 ?></td>
                                <td><strong>TSh <?= number_format($procedure['total_price'] ?? 0, 0) ?></strong></td>
                                <td style="font-family:monospace;font-size:0.75rem;"><?= htmlspecialchars($procedure['bill_number'] ?? 'N/A') ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-custom btn-danger-custom btn-xs-custom"
                                                title="Delete Procedure (reduces bill)"
                                                onclick="confirmDeleteBillItem(<?= $procedure['id'] ?>, '<?= addslashes($procedure['item_name']) ?>', 'procedure', <?= $is_paid ? 'true' : 'false' ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-syringe"></i>
                <p>No procedures found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 8. TOOLS / EQUIPMENT -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-tools" style="color:#D97706;background:#FEF3C7;"></i>
            Tools / Equipment
            <span style="font-size:0.75rem;font-weight:400;color:var(--page-text-secondary);">(Last 10)</span>
        </div>
        
        <?php if (count($tools) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table-custom">
                    <thead>
                        <tr>
                            <th>Tool Name</th>
                            <th>Date</th>
                            <th>Qty</th>
                            <th>Total</th>
                            <th>Bill #</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tools as $tool): 
                            $is_paid = ($tool['status'] ?? 'pending') === 'paid';
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($tool['item_name'] ?? 'N/A') ?></strong></td>
                                <td><?= date('d M Y', strtotime($tool['created_at'])) ?></td>
                                <td><?= $tool['quantity'] ?? 1 ?></td>
                                <td><strong>TSh <?= number_format($tool['total_price'] ?? 0, 0) ?></strong></td>
                                <td style="font-family:monospace;font-size:0.75rem;"><?= htmlspecialchars($tool['bill_number'] ?? 'N/A') ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-custom btn-danger-custom btn-xs-custom"
                                                title="Delete Equipment (returns stock, reduces bill)"
                                                onclick="confirmDeleteBillItem(<?= $tool['id'] ?>, '<?= addslashes($tool['item_name']) ?>', 'equipment', <?= $is_paid ? 'true' : 'false' ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-tools"></i>
                <p>No tools found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <?php else: ?>
    
    <div class="detail-card">
        <div class="empty-state">
            <i class="fas fa-user-slash" style="font-size:3rem;"></i>
            <h3 style="font-size:1.2rem;margin:12px 0;">Patient Not Found</h3>
            <a href="patients.php" class="btn-custom btn-primary-custom" style="margin-top:12px;">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>
    </div>
    
    <?php endif; ?>

</main>

<!-- MODAL: DELETE PRESCRIPTION -->
<div class="modal-overlay" id="deletePrescriptionModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-prescription"></i>
                Delete Prescription
            </div>
            <button class="modal-close" onclick="closeDeletePrescriptionModal()">&times;</button>
        </div>
        
        <div class="warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="warning-text">
                <strong>⚠️ WARNING: THIS ACTION CANNOT BE UNDONE!</strong>
                The prescription will be permanently deleted from all tables.
            </div>
        </div>
        
        <div class="info-box-modal">
            <div style="font-size:0.72rem;font-weight:800;color:#0B5ED7;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.05em;">
                <i class="fas fa-info-circle"></i> Prescription Details:
            </div>
            <div style="font-size:0.88rem;line-height:1.8;">
                <div><strong>Rx #:</strong> <span id="delPrescNumber" style="font-family:monospace;font-weight:700;"></span></div>
                <div><strong>Status:</strong> <span id="delPrescStatus"></span></div>
                <div><strong>Items:</strong> <span id="delPrescItems"></span></div>
                <div><strong>Total:</strong> <span id="delPrescAmount" style="color:#DC2626;font-weight:800;"></span></div>
            </div>
        </div>
        
        <div class="delete-list">
            <div style="font-weight:800;font-size:0.85rem;color:#DC2626;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.05em;">
                <i class="fas fa-trash"></i> WHAT WILL HAPPEN:
            </div>
            <div class="list-item">
                <i class="fas fa-times-circle" style="color:#DC2626;"></i> 
                Prescription + all its items will be deleted
            </div>
            <div class="list-item" id="stockReturnItem">
                <i class="fas fa-check-circle" style="color:#059669;"></i> 
                <span id="stockReturnText">Medication stock WILL BE RETURNED</span>
            </div>
            <div class="list-item" id="billReduceItem">
                <i class="fas fa-check-circle" style="color:#059669;"></i> 
                <span id="billReduceText">Bill amount WILL BE REDUCED</span>
            </div>
        </div>
        
        <div class="success-box-modal" id="stockWarnBox" style="display:none;">
            <i class="fas fa-info-circle"></i>
            This status is already dispensed/paid — <strong>Stock WILL NOT return</strong> and <strong>Bill WILL NOT change</strong>.
        </div>
        
        <form method="POST" id="deletePrescriptionForm">
            <input type="hidden" name="action" value="delete_prescription">
            <input type="hidden" name="prescription_id" id="deletePrescId" value="">
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel-modal" onclick="closeDeletePrescriptionModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-confirm-delete">
                    <i class="fas fa-trash"></i> YES, DELETE
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: DELETE VISIT (EVERYTHING) -->
<div class="modal-overlay" id="deleteVisitModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-exclamation-triangle"></i>
                Delete Visit (EVERYTHING)
            </div>
            <button class="modal-close" onclick="closeDeleteVisitModal()">&times;</button>
        </div>
        
        <div class="warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="warning-text">
                <strong>⚠️ CRITICAL WARNING: THIS ACTION CANNOT BE UNDONE!</strong>
                Deleting this visit will permanently remove <strong>EVERYTHING</strong> related to it.
            </div>
        </div>
        
        <div class="info-box-modal">
            <div style="font-size:0.72rem;font-weight:800;color:#0B5ED7;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.05em;">
                <i class="fas fa-info-circle"></i> Visit Details:
            </div>
            <div style="font-size:0.88rem;line-height:1.8;">
                <div><strong>Visit #:</strong> <span id="delVisitNumber" style="font-family:monospace;font-weight:700;"></span></div>
                <div><strong>Doctor:</strong> Dr. <span id="delVisitDoctor"></span></div>
                <div><strong>Date:</strong> <span id="delVisitDate"></span></div>
            </div>
        </div>
        
        <div class="delete-list">
            <div style="font-weight:800;font-size:0.85rem;color:#DC2626;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.05em;">
                <i class="fas fa-trash"></i> WHAT WILL BE DELETED:
            </div>
            <div class="list-item"><i class="fas fa-times-circle" style="color:#DC2626;"></i> All prescriptions + items</div>
            <div class="list-item"><i class="fas fa-times-circle" style="color:#DC2626;"></i> All lab tests</div>
            <div class="list-item"><i class="fas fa-times-circle" style="color:#DC2626;"></i> All procedures</div>
            <div class="list-item"><i class="fas fa-times-circle" style="color:#DC2626;"></i> All bills + items + payments</div>
            <div class="list-item"><i class="fas fa-times-circle" style="color:#DC2626;"></i> All vital signs</div>
            <div class="list-item"><i class="fas fa-times-circle" style="color:#DC2626;"></i> The visit itself</div>
            <div class="list-item" style="margin-top:8px;padding-top:8px;border-top:1px dashed var(--page-border);">
                <i class="fas fa-check-circle" style="color:#059669;"></i> 
                <strong>Medication + Equipment stock WILL BE RETURNED</strong> (if not paid)
            </div>
        </div>
        
        <div style="background:#FEF3C7;border-radius:10px;padding:14px 18px;margin-bottom:18px;font-size:0.85rem;color:#92400E;font-weight:700;border:2px solid #FCD34D;display:flex;align-items:center;gap:10px;">
            <i class="fas fa-info-circle" style="font-size:1.3rem;"></i>
            <div>Click <strong>"YES, DELETE EVERYTHING"</strong> to permanently delete this visit and all its data.</div>
        </div>
        
        <form method="POST" id="deleteVisitForm">
            <input type="hidden" name="action" value="delete_visit">
            <input type="hidden" name="visit_id" id="deleteVisitId" value="">
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel-modal" onclick="closeDeleteVisitModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-confirm-delete" id="confirmDeleteBtn">
                    <i class="fas fa-trash"></i> YES, DELETE EVERYTHING
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: DELETE BILL ITEM (Procedure / Equipment) -->
<div class="modal-overlay" id="deleteBillItemModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title" id="billItemModalTitle">
                <i class="fas fa-trash"></i>
                Delete Item
            </div>
            <button class="modal-close" onclick="closeDeleteBillItemModal()">&times;</button>
        </div>
        
        <div class="warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="warning-text">
                <strong>⚠️ Warning!</strong>
                <span id="billItemWarningText">This item will be removed from the bill and stock will be returned (if not paid).</span>
            </div>
        </div>
        
        <div class="info-box-modal">
            <div style="font-size:0.72rem;font-weight:800;color:#0B5ED7;margin-bottom:8px;text-transform:uppercase;">
                <i class="fas fa-info-circle"></i> Item Details:
            </div>
            <div style="font-size:0.88rem;">
                <div><strong>Name:</strong> <span id="billItemName"></span></div>
                <div><strong>Type:</strong> <span id="billItemType"></span></div>
            </div>
        </div>
        
        <div class="delete-list">
            <div style="font-weight:800;font-size:0.85rem;color:#DC2626;margin-bottom:10px;text-transform:uppercase;">
                <i class="fas fa-trash"></i> WHAT WILL HAPPEN:
            </div>
            <div class="list-item"><i class="fas fa-times-circle" style="color:#DC2626;"></i> Item will be deleted from bill_items</div>
            <div class="list-item"><i class="fas fa-check-circle" style="color:#059669;"></i> Bill amount WILL BE REDUCED</div>
            <div class="list-item" id="billItemStockItem"><i class="fas fa-check-circle" style="color:#059669;"></i> Equipment stock WILL BE RETURNED</div>
        </div>
        
        <div class="success-box-modal" id="billItemPaidWarn" style="display:none;">
            <i class="fas fa-info-circle"></i>
            This item is already paid — <strong>Stock WILL NOT be returned</strong>.
        </div>
        
        <form method="POST" id="deleteBillItemForm">
            <input type="hidden" name="action" value="delete_bill_item">
            <input type="hidden" name="bill_item_id" id="deleteBillItemId" value="">
            <input type="hidden" name="item_type" id="deleteBillItemType" value="">
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel-modal" onclick="closeDeleteBillItemModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-confirm-delete">
                    <i class="fas fa-trash"></i> YES, DELETE
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: DELETE BILL / LAB TEST (Generic) -->
<div class="modal-overlay" id="deleteGenericModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title" id="genericModalTitle">
                <i class="fas fa-trash"></i> Delete Record
            </div>
            <button class="modal-close" onclick="closeGenericModal()">&times;</button>
        </div>
        
        <div class="warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="warning-text">
                <strong>⚠️ Warning!</strong>
                <span id="genericWarningText">Are you sure you want to delete this record?</span>
            </div>
        </div>
        
        <div class="info-box-modal">
            <div style="font-size:0.72rem;font-weight:800;color:#0B5ED7;margin-bottom:6px;text-transform:uppercase;">
                <i class="fas fa-info-circle"></i> Record Details:
            </div>
            <div style="font-size:0.95rem;font-weight:800;" id="genericRecordName"></div>
        </div>
        
        <div class="delete-list" id="genericDeleteList"></div>
        
        <form method="POST" id="genericDeleteForm">
            <input type="hidden" name="action" id="genericAction" value="">
            <input type="hidden" name="bill_id" id="genericBillId" value="">
            <input type="hidden" name="test_id" id="genericTestId" value="">
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel-modal" onclick="closeGenericModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-confirm-delete">
                    <i class="fas fa-trash"></i> Yes, Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ================================================================
// DELETE PRESCRIPTION MODAL
// ================================================================
function confirmDeletePrescription(prescId, prescNumber, status, itemCount, amount) {
    document.getElementById('deletePrescId').value = prescId;
    document.getElementById('delPrescNumber').textContent = prescNumber;
    
    var statusDisplay = document.getElementById('delPrescStatus');
    statusDisplay.textContent = status.charAt(0).toUpperCase() + status.slice(1);
    statusDisplay.className = 'presc-status-' + status;
    
    document.getElementById('delPrescItems').textContent = itemCount + ' item(s)';
    document.getElementById('delPrescAmount').textContent = 'TSh ' + Number(amount).toLocaleString();
    
    var shouldReturn = (status === 'pending' || status === 'confirmed');
    
    var stockReturnText = document.getElementById('stockReturnText');
    var billReduceText = document.getElementById('billReduceText');
    var stockWarnBox = document.getElementById('stockWarnBox');
    var stockReturnItem = document.getElementById('stockReturnItem');
    var billReduceItem = document.getElementById('billReduceItem');
    
    if (shouldReturn) {
        stockReturnText.innerHTML = '<strong style="color:#059669;">Medication stock WILL BE RETURNED</strong>';
        stockReturnItem.querySelector('i').className = 'fas fa-check-circle';
        stockReturnItem.querySelector('i').style.color = '#059669';
        
        billReduceText.innerHTML = '<strong style="color:#059669;">Bill amount WILL BE REDUCED</strong>';
        billReduceItem.querySelector('i').className = 'fas fa-check-circle';
        billReduceItem.querySelector('i').style.color = '#059669';
        
        stockWarnBox.style.display = 'none';
    } else {
        stockReturnText.innerHTML = '<strong style="color:#DC2626;">Stock WILL NOT return</strong>';
        stockReturnItem.querySelector('i').className = 'fas fa-times-circle';
        stockReturnItem.querySelector('i').style.color = '#DC2626';
        
        billReduceText.innerHTML = '<strong style="color:#DC2626;">Bill WILL NOT change</strong>';
        billReduceItem.querySelector('i').className = 'fas fa-times-circle';
        billReduceItem.querySelector('i').style.color = '#DC2626';
        
        stockWarnBox.style.display = 'block';
    }
    
    document.getElementById('deletePrescriptionModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeletePrescriptionModal() {
    document.getElementById('deletePrescriptionModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ================================================================
// DELETE VISIT MODAL
// ================================================================
function confirmDeleteVisit(visitId, visitNumber, doctorName, visitDate) {
    document.getElementById('deleteVisitId').value = visitId;
    document.getElementById('delVisitNumber').textContent = visitNumber;
    document.getElementById('delVisitDoctor').textContent = doctorName;
    document.getElementById('delVisitDate').textContent = visitDate;
    document.getElementById('deleteVisitModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteVisitModal() {
    document.getElementById('deleteVisitModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ================================================================
// DELETE BILL ITEM MODAL (Procedure/Equipment)
// ================================================================
function confirmDeleteBillItem(itemId, itemName, itemType, isPaid) {
    document.getElementById('deleteBillItemId').value = itemId;
    document.getElementById('deleteBillItemType').value = itemType;
    document.getElementById('billItemName').textContent = itemName;
    document.getElementById('billItemType').textContent = itemType.charAt(0).toUpperCase() + itemType.slice(1);
    
    var title = document.getElementById('billItemModalTitle');
    title.innerHTML = '<i class="fas fa-trash"></i> Delete ' + (itemType === 'equipment' ? 'Equipment' : 'Procedure');
    
    var stockItem = document.getElementById('billItemStockItem');
    var paidWarn = document.getElementById('billItemPaidWarn');
    
    if (itemType === 'equipment') {
        stockItem.style.display = 'flex';
        if (isPaid) {
            stockItem.innerHTML = '<i class="fas fa-times-circle" style="color:#DC2626;"></i> Stock WILL NOT return (already paid)';
            paidWarn.style.display = 'block';
        } else {
            stockItem.innerHTML = '<i class="fas fa-check-circle" style="color:#059669;"></i> Equipment stock WILL BE RETURNED';
            paidWarn.style.display = 'none';
        }
    } else {
        stockItem.style.display = 'none';
        paidWarn.style.display = 'none';
    }
    
    document.getElementById('deleteBillItemModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteBillItemModal() {
    document.getElementById('deleteBillItemModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ================================================================
// GENERIC DELETE MODAL (Bill / Lab Test)
// ================================================================
function confirmDeleteBill(billId, billNumber) {
    document.getElementById('genericModalTitle').innerHTML = '<i class="fas fa-trash"></i> Delete Bill';
    document.getElementById('genericAction').value = 'delete_bill';
    document.getElementById('genericBillId').value = billId;
    document.getElementById('genericTestId').value = '';
    document.getElementById('genericRecordName').textContent = 'Bill #: ' + billNumber;
    document.getElementById('genericWarningText').textContent = 'This bill will be deleted along with all its items and payments.';
    document.getElementById('genericDeleteList').innerHTML = 
        '<div style="font-weight:800;font-size:0.85rem;color:#DC2626;margin-bottom:10px;text-transform:uppercase;">' +
        '<i class="fas fa-trash"></i> WHAT WILL HAPPEN:</div>' +
        '<div class="list-item"><i class="fas fa-times-circle" style="color:#DC2626;"></i> Bill will be deleted</div>' +
        '<div class="list-item"><i class="fas fa-times-circle" style="color:#DC2626;"></i> All bill items will be deleted</div>' +
        '<div class="list-item"><i class="fas fa-times-circle" style="color:#DC2626;"></i> All payments will be deleted</div>' +
        '<div class="list-item"><i class="fas fa-check-circle" style="color:#059669;"></i> Equipment stock WILL BE RETURNED (if not paid)</div>';
    document.getElementById('deleteGenericModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function confirmDeleteLabTest(testId, testName, status) {
    var canReturn = (status === 'pending' || status === 'in_progress');
    
    document.getElementById('genericModalTitle').innerHTML = '<i class="fas fa-trash"></i> Delete Lab Test';
    document.getElementById('genericAction').value = 'delete_lab_test';
    document.getElementById('genericBillId').value = '';
    document.getElementById('genericTestId').value = testId;
    document.getElementById('genericRecordName').textContent = 'Test: ' + testName;
    document.getElementById('genericWarningText').textContent = 'This lab test will be deleted along with all its results.';
    
    var stockHtml = canReturn 
        ? '<div class="list-item"><i class="fas fa-check-circle" style="color:#059669;"></i> Equipment stock WILL BE RETURNED</div>' +
          '<div class="list-item"><i class="fas fa-check-circle" style="color:#059669;"></i> Bill amount WILL BE REDUCED</div>'
        : '<div class="list-item"><i class="fas fa-times-circle" style="color:#DC2626;"></i> Stock WILL NOT return (status: ' + status + ')</div>' +
          '<div class="list-item"><i class="fas fa-times-circle" style="color:#DC2626;"></i> Bill WILL NOT change</div>';
    
    document.getElementById('genericDeleteList').innerHTML = 
        '<div style="font-weight:800;font-size:0.85rem;color:#DC2626;margin-bottom:10px;text-transform:uppercase;">' +
        '<i class="fas fa-trash"></i> WHAT WILL HAPPEN:</div>' +
        '<div class="list-item"><i class="fas fa-times-circle" style="color:#DC2626;"></i> Lab test will be deleted</div>' +
        '<div class="list-item"><i class="fas fa-times-circle" style="color:#DC2626;"></i> Bill items will be deleted</div>' +
        stockHtml;
    
    document.getElementById('deleteGenericModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeGenericModal() {
    document.getElementById('deleteGenericModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ================================================================
// ESCAPE KEY & CLICK OUTSIDE
// ================================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeletePrescriptionModal();
        closeDeleteVisitModal();
        closeGenericModal();
        closeDeleteBillItemModal();
    }
});

['deletePrescriptionModal', 'deleteVisitModal', 'deleteGenericModal', 'deleteBillItemModal'].forEach(function(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    }
});

// Dark mode enforcement
function enforceDarkModeBackground() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var body = document.body;
    var mainContent = document.querySelector('.main-content');
    if (isDark) {
        if (body) body.style.background = '#0F172A';
        if (mainContent) mainContent.style.background = '#0F172A';
    } else {
        if (body) body.style.background = '#F1F5F9';
        if (mainContent) mainContent.style.background = '#F1F5F9';
    }
}
enforceDarkModeBackground();
document.addEventListener('darkModeChanged', function() { setTimeout(enforceDarkModeBackground, 50); });

console.log('%c Braick - Patient Details V5 (Delete Without Reason)', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Delete without typing "DELETE" - One click only', 'font-size:13px;color:#34D399;font-weight:bold;');
console.log('%c✅ Delete Prescription → Stock returned + Bill reduced', 'font-size:13px;color:#34D399;');
console.log('%c✅ Delete Lab Test → Equipment stock returned (ALL linked)', 'font-size:13px;color:#34D399;');
console.log('%c✅ Delete Visit → EVERYTHING deleted + stock returned', 'font-size:13px;color:#34D399;');
console.log('%c✅ Delete Bill Item → Stock returned + Bill reduced', 'font-size:13px;color:#34D399;');
console.log('%c✅ Smart delete: Paid/Dispensed = NO changes', 'font-size:13px;color:#D97706;');
console.log('%c✅ All messages in English', 'font-size:13px;color:#0B5ED7;');
</script>

</body>
</html>