<?php
// ================================================================
// FILE: frontend/pages/cashier/process_payment.php
// CASHIER - PROCESS PAYMENT - V27.0 FINAL FIX
// ================================================================
// ✅ V27: FIX DOUBLE DEDUCTION - Lock bill + stock movements check
// ✅ V27: Auto-repair IMEFUTWA (inasababisha double deduction)
// ✅ V27: Stock deduction inafanyika TU wakati wa payment
// ✅ V27: Inatumia bill_items.id kwa reference_id (SAHIHI)
// ✅ V26.2: Lab equipment deduction kwa visit_id
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

$is_admin = ($user_role === 'admin');
$is_reception = ($user_role === 'reception');

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// V27: CHECK IF ALREADY DEDUCTED (SAFE CHECK)
// ================================================================
function isStockAlreadyDeducted($db, $item_type, $reference_type, $reference_id, $equipment_id_or_inventory_id, $branch_id) {
    try {
        if ($item_type === 'medication') {
            // For medications, check via prescription_id
            $stmt = $db->prepare("
                SELECT COUNT(*) as cnt FROM stock_movements 
                WHERE inventory_id = ? 
                  AND reference_type = ? 
                  AND reference_id = ?
                  AND movement_type = 'out'
                  AND branch_id = ?
            ");
            $stmt->execute([$equipment_id_or_inventory_id, $reference_type, $reference_id, $branch_id]);
        } else {
            // For equipment, check via equipment_id
            $stmt = $db->prepare("
                SELECT COUNT(*) as cnt FROM stock_movements 
                WHERE equipment_id = ? 
                  AND reference_type = ? 
                  AND reference_id = ?
                  AND movement_type = 'out'
                  AND branch_id = ?
            ");
            $stmt->execute([$equipment_id_or_inventory_id, $reference_type, $reference_id, $branch_id]);
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ((int)($result['cnt'] ?? 0)) > 0;
    } catch (Exception $e) {
        error_log("isStockAlreadyDeducted error: " . $e->getMessage());
        return false;
    }
}

// ================================================================
// V27: DEDUCT MEDICATION STOCK (FIFO) - WITH SAFETY CHECK
// ================================================================
function deductMedicationStock($db, $medication_name, $quantity, $branch_id, $user_id, $patient_id, $reference_type = 'prescription', $reference_id = 0, $prescription_number = '') {
    $result = ['deducted' => 0, 'remaining' => $quantity, 'details' => [], 'errors' => [], 'skipped' => false];
    
    try {
        $needed = (int)$quantity;
        if ($needed <= 0) return $result;
        
        // ✅ V27: SAFETY CHECK
        if ($reference_type === 'prescription' && $reference_id > 0) {
            $stmt_check = $db->prepare("
                SELECT COUNT(*) as cnt FROM stock_movements 
                WHERE reference_type = 'prescription' 
                  AND reference_id = ?
                  AND movement_type = 'out'
                  AND branch_id = ?
            ");
            $stmt_check->execute([$reference_id, $branch_id]);
            $existing = (int)$stmt_check->fetch(PDO::FETCH_ASSOC)['cnt'];
            
            if ($existing > 0) {
                error_log("⏭️ V27: Medication deduction SKIPPED - Rx #$reference_id already deducted");
                $result['skipped'] = true;
                $result['errors'][] = "Rx #$reference_id already deducted";
                return $result;
            }
        }
        
        $clean_name = preg_replace('/\s*\(Batch:.*?\)\s*/i', '', $medication_name);
        $clean_name = trim($clean_name);
        
        $stmt = $db->prepare("
            SELECT id, medication_name, quantity, batch_number, expiry_date
            FROM medications_inventory 
            WHERE (LOWER(TRIM(medication_name)) = LOWER(TRIM(?)) 
                   OR LOWER(TRIM(medication_name)) = LOWER(TRIM(?)))
              AND branch_id = ? 
              AND status = 'active' 
              AND quantity > 0
              AND (expiry_date IS NULL OR expiry_date = '0000-00-00' OR expiry_date >= CURDATE())
            ORDER BY 
                CASE WHEN expiry_date IS NULL OR expiry_date = '0000-00-00' THEN 2 ELSE 1 END ASC,
                expiry_date ASC,
                id ASC
            FOR UPDATE
        ");
        $stmt->execute([$clean_name, $medication_name, $branch_id]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($batches)) {
            $result['errors'][] = "No active batches found for $clean_name";
            return $result;
        }
        
        foreach ($batches as $batch) {
            if ($needed <= 0) break;
            
            $batch_id = (int)$batch['id'];
            $available = (int)$batch['quantity'];
            $deduct = min($needed, $available);
            $new_stock = $available - $deduct;
            
            $db->prepare("UPDATE medications_inventory SET quantity = ?, updated_at = NOW() WHERE id = ? AND branch_id = ?")
               ->execute([$new_stock, $batch_id, $branch_id]);
            
            $result['deducted'] += $deduct;
            $result['remaining'] -= $deduct;
            $result['details'][] = "{$batch['batch_number']}: {$available} → {$new_stock}";
            
            try {
                $notes = "V27 - Dispensed";
                if (!empty($prescription_number)) $notes .= " - Rx #{$prescription_number}";
                $notes .= " - Qty: {$deduct} - Batch: " . ($batch['batch_number'] ?? 'N/A');
                
                $db->prepare("
                    INSERT INTO stock_movements 
                    (inventory_id, patient_id, movement_type, quantity, previous_stock, new_stock, 
                     reference_type, reference_id, performed_by, branch_id, notes, created_at)
                    VALUES (?, ?, 'out', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ")->execute([
                    $batch_id, $patient_id, $deduct, $available, $new_stock,
                    $reference_type, $reference_id, $user_id, $branch_id, $notes
                ]);
            } catch (Exception $e) {
                error_log("V27 stock_movements insert error (med): " . $e->getMessage());
            }
            
            $needed -= $deduct;
        }
        
        if ($needed > 0) {
            $result['errors'][] = "Insufficient stock for $clean_name (needed {$needed} more)";
        }
    } catch (Exception $e) {
        $result['errors'][] = "System error: " . $e->getMessage();
        error_log("V27 deductMedicationStock error: " . $e->getMessage());
    }
    
    return $result;
}

// ================================================================
// V27: DEDUCT EQUIPMENT STOCK - WITH SAFETY CHECK
// ================================================================
function deductEquipmentStock($db, $equipment_id, $quantity, $branch_id, $user_id, $patient_id, $reference_type = 'procedure', $reference_id = 0, $notes = '') {
    $result = ['deducted' => 0, 'details' => [], 'errors' => [], 'skipped' => false];
    
    try {
        $eq_id = (int)$equipment_id;
        $qty = (int)$quantity;
        
        if ($eq_id <= 0 || $qty <= 0) {
            $result['errors'][] = "Invalid equipment ID or quantity";
            return $result;
        }
        
        // ✅ V27: SAFETY CHECK - Check if already deducted
        $stmt_check = $db->prepare("
            SELECT COUNT(*) as cnt FROM stock_movements 
            WHERE equipment_id = ? 
              AND reference_type = ?
              AND reference_id = ?
              AND movement_type = 'out'
              AND branch_id = ?
        ");
        $stmt_check->execute([$eq_id, $reference_type, $reference_id, $branch_id]);
        $existing = (int)$stmt_check->fetch(PDO::FETCH_ASSOC)['cnt'];
        
        if ($existing > 0) {
            error_log("⏭️ V27: Equipment #$eq_id SKIPPED - reference $reference_type #$reference_id already deducted");
            $result['skipped'] = true;
            $result['errors'][] = "Equipment #$eq_id already deducted for $reference_type #$reference_id";
            return $result;
        }
        
        $stmt = $db->prepare("
            SELECT id, equipment_name, quantity as stock, batch_number, status, branch_id 
            FROM medical_equipment 
            WHERE id = ? AND branch_id = ? 
            FOR UPDATE
        ");
        $stmt->execute([$eq_id, $branch_id]);
        $equip = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$equip) {
            $result['errors'][] = "Equipment #$eq_id not found in branch $branch_id";
            return $result;
        }
        
        if ($equip['status'] !== 'active') {
            $result['errors'][] = "{$equip['equipment_name']} is not active";
            return $result;
        }
        
        $previous_stock = (int)$equip['stock'];
        
        if ($previous_stock < $qty) {
            $result['errors'][] = "Insufficient stock for {$equip['equipment_name']} (have: $previous_stock, need: $qty)";
            return $result;
        }
        
        $new_stock = $previous_stock - $qty;
        
        $db->prepare("UPDATE medical_equipment SET quantity = ?, updated_at = NOW() WHERE id = ? AND branch_id = ?")
           ->execute([$new_stock, $eq_id, $branch_id]);
        
        $result['deducted'] = $qty;
        $result['details'][] = "{$equip['equipment_name']}: {$previous_stock} → {$new_stock}";
        
        try {
            $log_notes = !empty($notes) ? $notes : "V27 - Equipment used";
            $log_notes .= " | Batch: " . ($equip['batch_number'] ?? 'N/A');
            $log_notes .= " | Stock: {$previous_stock} → {$new_stock}";
            
            $db->prepare("
                INSERT INTO stock_movements 
                (equipment_id, patient_id, movement_type, quantity, previous_stock, new_stock, 
                 reference_type, reference_id, performed_by, branch_id, notes, created_at)
                VALUES (?, ?, 'out', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $eq_id, $patient_id, $qty, $previous_stock, $new_stock,
                $reference_type, $reference_id, $user_id, $branch_id, $log_notes
            ]);
            
            error_log("✅ V27: Equipment {$equip['equipment_name']} deducted $qty ($previous_stock → $new_stock) for $reference_type #$reference_id");
        } catch (Exception $e) {
            error_log("V27 stock_movements insert error (equip): " . $e->getMessage());
        }
    } catch (Exception $e) {
        $result['errors'][] = "System error: " . $e->getMessage();
        error_log("V27 deductEquipmentStock error: " . $e->getMessage());
    }
    
    return $result;
}

// ================================================================
// V27: DEDUCT ALL STOCK FOR PAID BILL
// ✅ Inaitwa TU kwenye PAYMENT (sio page load)
// ✅ Ina SAFETY CHECK kabla ya kila deduction
// ✅ Inatumia bill_items.id kwa reference_id (SAHIHI)
// ================================================================
function deductAllStockForPaidBill($db, $bill_id, $branch_id, $user_id) {
    $result = [
        'medications_deducted' => 0,
        'medication_items' => 0,
        'equipment_deducted' => 0,
        'equipment_items' => 0,
        'lab_equipment_deducted' => 0,
        'lab_equipment_items' => 0,
        'skipped' => 0,
        'errors' => []
    ];
    
    try {
        error_log("═══════════════════════════════════════════════════════");
        error_log("🔍 V27: deductAllStockForPaidBill START - Bill #$bill_id");
        error_log("═══════════════════════════════════════════════════════");
        
        // ✅ SAFETY CHECK 1: Verify bill is FULLY PAID
        $stmt_check = $db->prepare("SELECT status, balance, visit_id FROM bills WHERE id = ? AND branch_id = ? FOR UPDATE");
        $stmt_check->execute([$bill_id, $branch_id]);
        $bill_check = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$bill_check) {
            $result['errors'][] = "Bill #$bill_id not found";
            return $result;
        }
        
        if ($bill_check['status'] !== 'paid' || (float)$bill_check['balance'] > 0.01) {
            error_log("⏭️ V27: Bill #$bill_id NOT fully paid - SKIPPING");
            return $result;
        }
        
        $bill_visit_id = (int)$bill_check['visit_id'];
        
        // ============================================================
        // 1. MEDICATIONS FROM PRESCRIPTIONS
        // ============================================================
        $stmt = $db->prepare("
            SELECT DISTINCT p.id as prescription_id, p.patient_id, p.prescription_number
            FROM prescriptions p
            INNER JOIN bill_items bi ON bi.reference_id = p.id AND bi.reference_type = 'prescription'
            WHERE bi.bill_id = ? AND bi.branch_id = ? 
              AND bi.item_type = 'medication'
              AND bi.status != 'cancelled'
              AND p.status IN ('confirmed', 'dispensed')
        ");
        $stmt->execute([$bill_id, $branch_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($prescriptions as $pres) {
            $prescription_id = $pres['prescription_id'];
            $patient_id = $pres['patient_id'];
            $prescription_number = $pres['prescription_number'];
            
            // ✅ SAFETY CHECK 2: Check if already deducted
            $stmt_check_disp = $db->prepare("
                SELECT COUNT(*) as dispensed_count 
                FROM prescription_items 
                WHERE prescription_id = ? AND dispensed_at IS NOT NULL AND cancelled_at IS NULL
            ");
            $stmt_check_disp->execute([$prescription_id]);
            $already_dispensed = (int)$stmt_check_disp->fetch(PDO::FETCH_ASSOC)['dispensed_count'];
            
            if ($already_dispensed > 0) {
                error_log("⏭️ V27: Rx #$prescription_number already dispensed - SKIPPING");
                $result['skipped']++;
                continue;
            }
            
            $stmt_items = $db->prepare("
                SELECT * FROM prescription_items 
                WHERE prescription_id = ? 
                  AND cancelled_at IS NULL 
                  AND quantity > 0
            ");
            $stmt_items->execute([$prescription_id]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items as $item) {
                $med_name = preg_replace('/\s*\(Batch:.*?\)\s*/i', '', $item['medication_name']);
                $med_name = trim($med_name);
                
                $deduct_result = deductMedicationStock(
                    $db, $med_name, $item['quantity'], $branch_id, $user_id, $patient_id,
                    'prescription', $prescription_id, $prescription_number
                );
                
                if ($deduct_result['deducted'] > 0) {
                    $result['medications_deducted'] += $deduct_result['deducted'];
                    $result['medication_items']++;
                }
                if ($deduct_result['skipped']) {
                    $result['skipped']++;
                }
                if (!empty($deduct_result['errors'])) {
                    $result['errors'] = array_merge($result['errors'], $deduct_result['errors']);
                }
            }
            
            // Mark prescription as dispensed
            $db->prepare("
                UPDATE prescriptions 
                SET status = 'dispensed', dispensed_at = NOW(), updated_at = NOW(), pharmacy_id = ?
                WHERE id = ? AND branch_id = ? AND status = 'confirmed'
            ")->execute([$user_id, $prescription_id, $branch_id]);
            
            $db->prepare("
                UPDATE prescription_items 
                SET dispensed_at = NOW(), dispensed_by = ? 
                WHERE prescription_id = ? AND cancelled_at IS NULL
            ")->execute([$user_id, $prescription_id]);
        }
        
        // ============================================================
        // 2. EQUIPMENT (DIRECT BILL ITEMS)
        // ✅ V27: SAFETY CHECK kwa kila item
        // ============================================================
        $stmt = $db->prepare("
            SELECT bi.id as bill_item_id, bi.item_id as equipment_id, bi.quantity, 
                   bi.item_name, me.equipment_name
            FROM bill_items bi
            INNER JOIN medical_equipment me ON bi.item_id = me.id 
                AND bi.item_type = 'equipment'
                AND me.branch_id = bi.branch_id
            WHERE bi.bill_id = ? AND bi.branch_id = ?
              AND bi.item_type = 'equipment'
              AND bi.status != 'cancelled'
        ");
        $stmt->execute([$bill_id, $branch_id]);
        $equipment_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($equipment_items as $eq) {
            $equipment_id = (int)$eq['equipment_id'];
            $qty_needed = (int)$eq['quantity'];
            $bill_item_id = (int)$eq['bill_item_id'];
            
            if ($equipment_id <= 0 || $qty_needed <= 0) continue;
            
            $stmt_patient = $db->prepare("SELECT patient_id FROM bills WHERE id = ?");
            $stmt_patient->execute([$bill_id]);
            $patient_id = (int)$stmt_patient->fetchColumn();
            
            // ✅ V27: Use bill_item_id as reference_id (SAHIHI)
            $deduct_result = deductEquipmentStock(
                $db, $equipment_id, $qty_needed, $branch_id, $user_id, $patient_id,
                'equipment', $bill_item_id,
                "V27 - Equipment - Bill #{$bill_id} - {$eq['equipment_name']}"
            );
            
            if ($deduct_result['deducted'] > 0) {
                $result['equipment_deducted'] += $deduct_result['deducted'];
                $result['equipment_items']++;
            }
            if ($deduct_result['skipped']) {
                $result['skipped']++;
            }
            if (!empty($deduct_result['errors'])) {
                $result['errors'] = array_merge($result['errors'], $deduct_result['errors']);
            }
        }
        
        // ============================================================
        // 3. LAB-LINKED EQUIPMENT (V26.2 FIX)
        // ✅ Inatumia visit_id (haitegemei bill_items.reference_id)
        // ============================================================
        if ($bill_visit_id > 0) {
            $stmt = $db->prepare("
                SELECT 
                    lt.id as lab_test_id,
                    lt.test_id,
                    lt.test_name,
                    lt.patient_id,
                    lt.branch_id,
                    lt.visit_id,
                    lte.equipment_id,
                    me.equipment_name,
                    me.quantity as current_stock
                FROM lab_tests lt
                INNER JOIN lab_test_equipment lte 
                    ON lte.lab_test_id = lt.test_id
                    AND lte.branch_id = lt.branch_id
                INNER JOIN medical_equipment me 
                    ON lte.equipment_id = me.id
                    AND me.branch_id = lt.branch_id
                WHERE lt.branch_id = ?
                  AND lt.visit_id = ?
                  AND lt.status != 'cancelled'
                  AND me.status = 'active'
            ");
            $stmt->execute([$branch_id, $bill_visit_id]);
            $lab_equipment_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($lab_equipment_items as $eq) {
                $equipment_id = (int)$eq['equipment_id'];
                $lab_test_id = (int)$eq['lab_test_id'];
                $patient_id = (int)$eq['patient_id'];
                $quantity_used = 1;
                
                if ($equipment_id <= 0) continue;
                
                // ✅ V27: Use lab_test_id as reference_id
                $deduct_result = deductEquipmentStock(
                    $db, $equipment_id, $quantity_used, $branch_id, $user_id, $patient_id,
                    'lab_test', $lab_test_id,
                    "V27 - Lab equipment - {$eq['test_name']} - Lab #{$lab_test_id}"
                );
                
                if ($deduct_result['deducted'] > 0) {
                    $result['lab_equipment_deducted'] += $deduct_result['deducted'];
                    $result['lab_equipment_items']++;
                }
                if ($deduct_result['skipped']) {
                    $result['skipped']++;
                }
                if (!empty($deduct_result['errors'])) {
                    $result['errors'] = array_merge($result['errors'], $deduct_result['errors']);
                }
            }
        }
        
        error_log("✅ V27: Bill #$bill_id done - Meds: {$result['medications_deducted']}, Equip: {$result['equipment_deducted']}, Lab Equip: {$result['lab_equipment_deducted']}, Skipped: {$result['skipped']}");
        
    } catch (Exception $e) {
        $result['errors'][] = "System error: " . $e->getMessage();
        error_log("V27 deductAllStockForPaidBill error: " . $e->getMessage());
    }
    
    return $result;
}

// ================================================================
// V27: HELPER FUNCTIONS
// ================================================================
function removeDuplicateBillItems($db, $branch_id) {
    try {
        $stmt = $db->prepare("
            SELECT bi1.id as keep_id, bi2.id as delete_id, bi2.bill_id
            FROM bill_items bi1
            INNER JOIN bill_items bi2 
                ON bi1.bill_id = bi2.bill_id
                AND bi1.item_name = bi2.item_name
                AND bi1.item_type = bi2.item_type
                AND COALESCE(bi1.reference_id, 0) = COALESCE(bi2.reference_id, 0)
                AND COALESCE(bi1.reference_type, '') = COALESCE(bi2.reference_type, '')
                AND bi1.id < bi2.id
            INNER JOIN bills b ON bi1.bill_id = b.id
            WHERE b.branch_id = ? AND bi2.status != 'cancelled'
        ");
        $stmt->execute([$branch_id]);
        $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($duplicates)) return 0;
        
        $deleted = 0;
        $affected_bills = [];
        foreach ($duplicates as $dup) {
            $stmt_del = $db->prepare("DELETE FROM bill_items WHERE id = ? AND status != 'paid'");
            $stmt_del->execute([$dup['delete_id']]);
            if ($stmt_del->rowCount() > 0) {
                $deleted++;
                if (!in_array($dup['bill_id'], $affected_bills)) $affected_bills[] = $dup['bill_id'];
            }
        }
        foreach ($affected_bills as $bill_id) {
            recalculateBillSubtotal($db, $bill_id, $branch_id);
        }
        return $deleted;
    } catch (Exception $e) { return 0; }
}

function recalculateBillSubtotal($db, $bill_id, $branch_id) {
    try {
        $stmt = $db->prepare("SELECT COALESCE(SUM(total_price), 0) as new_subtotal FROM bill_items WHERE bill_id = ? AND branch_id = ? AND status != 'cancelled'");
        $stmt->execute([$bill_id, $branch_id]);
        $new_subtotal = (float)($stmt->fetch(PDO::FETCH_ASSOC)['new_subtotal'] ?? 0);
        
        $stmt_bill = $db->prepare("SELECT subtotal, pharmacy_discount, cashier_discount, pharmacy_premium, cashier_premium, paid_amount FROM bills WHERE id = ? AND branch_id = ?");
        $stmt_bill->execute([$bill_id, $branch_id]);
        $bill = $stmt_bill->fetch(PDO::FETCH_ASSOC);
        if (!$bill) return false;
        
        if (abs((float)$bill['subtotal'] - $new_subtotal) < 0.01) return false;
        
        $pharmacy_discount = (float)$bill['pharmacy_discount'];
        $cashier_discount = (float)$bill['cashier_discount'];
        $pharmacy_premium = (float)$bill['pharmacy_premium'];
        $cashier_premium = (float)$bill['cashier_premium'];
        $paid_amount = (float)$bill['paid_amount'];
        
        $total_discount = $pharmacy_discount + $cashier_discount;
        if ($total_discount > $new_subtotal) $total_discount = $new_subtotal;
        $total_premium = $pharmacy_premium + $cashier_premium;
        $new_total = $new_subtotal + $total_premium - $total_discount;
        if ($new_total < 0) $new_total = 0;
        
        $new_balance = $new_total - $paid_amount;
        if ($new_balance < 0) $new_balance = 0;
        
        if ($new_total <= 0 || $new_balance <= 0.01) {
            $new_status = 'paid';
            $paid_amount = $new_total;
            $new_balance = 0;
        } elseif ($paid_amount > 0 && $new_balance > 0) {
            $new_status = 'partial';
        } else {
            $new_status = 'pending';
        }
        
        $db->prepare("
            UPDATE bills SET subtotal = ?, total_discount = ?, premium_amount = ?,
                total_amount = ?, paid_amount = ?, balance = ?, status = ?, updated_at = NOW()
            WHERE id = ? AND branch_id = ?
        ")->execute([$new_subtotal, $total_discount, $total_premium, $new_total, $paid_amount, $new_balance, $new_status, $bill_id, $branch_id]);
        return true;
    } catch (Exception $e) { return false; }
}

function syncProceduresStatus($db, $bill_id, $branch_id) {
    try {
        $stmt_bill = $db->prepare("SELECT status, balance FROM bills WHERE id = ? AND branch_id = ?");
        $stmt_bill->execute([$bill_id, $branch_id]);
        $bill = $stmt_bill->fetch(PDO::FETCH_ASSOC);
        if (!$bill) return 0;
        
        $procedure_status = 'pending';
        if ($bill['status'] === 'paid' && (float)$bill['balance'] <= 0.01) $procedure_status = 'completed';
        elseif ($bill['status'] === 'partial') $procedure_status = 'in_progress';
        
        $stmt = $db->prepare("
            SELECT DISTINCT bi.reference_id, bi.item_type 
            FROM bill_items bi
            WHERE bi.bill_id = ? AND bi.item_type IN ('procedure', 'equipment')
            AND bi.reference_id IS NOT NULL AND bi.reference_id > 0
            AND bi.status != 'cancelled'
        ");
        $stmt->execute([$bill_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updated_count = 0;
        foreach ($rows as $row) {
            $table = ($row['item_type'] === 'equipment') ? 'equipment' : 'procedures';
            try {
                $stmt_update = $db->prepare("UPDATE $table SET status = ?, updated_at = NOW() WHERE id = ? AND branch_id = ? AND status != 'cancelled'");
                $stmt_update->execute([$procedure_status, $row['reference_id'], $branch_id]);
                if ($stmt_update->rowCount() > 0) $updated_count++;
            } catch (Exception $e) {}
        }
        return $updated_count;
    } catch (Exception $e) { return 0; }
}

function isVisitReadyForCompletion($db, $visit_id, $branch_id) {
    if (empty($visit_id) || $visit_id <= 0) return false;
    try {
        $stmt = $db->prepare("SELECT id, status, is_completed FROM visits WHERE id = ? AND branch_id = ?");
        $stmt->execute([$visit_id, $branch_id]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$visit) return false;
        if ($visit['status'] !== 'waiting') return false;
        if ((int)$visit['is_completed'] === 1) return false;
        return true;
    } catch (Exception $e) { return false; }
}

function updateVisitPaymentStatus($db, $visit_id, $branch_id) {
    if (empty($visit_id) || $visit_id <= 0) return false;
    try {
        $stmt = $db->prepare("SELECT id, total_amount, paid_amount, balance, status FROM bills WHERE visit_id = ? AND branch_id = ? AND status != 'cancelled'");
        $stmt->execute([$visit_id, $branch_id]);
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($bills)) {
            $db->prepare("UPDATE visits SET payment_status = 'pending', visit_total = 0, updated_at = NOW() WHERE id = ? AND branch_id = ?")->execute([$visit_id, $branch_id]);
            return true;
        }
        
        $visit_total = 0; $visit_paid = 0; $visit_balance = 0;
        $all_paid = true; $any_paid = false;
        foreach ($bills as $bill) {
            $visit_total += (float)$bill['total_amount'];
            $visit_paid += (float)$bill['paid_amount'];
            $visit_balance += (float)$bill['balance'];
            if ($bill['status'] === 'paid') $any_paid = true;
            elseif ($bill['status'] === 'partial') { $any_paid = true; $all_paid = false; }
            else $all_paid = false;
        }
        
        $visit_payment_status = 'pending';
        if ($visit_total <= 0) $visit_payment_status = 'paid';
        elseif ($all_paid && $visit_balance <= 0.01) $visit_payment_status = 'paid';
        elseif ($any_paid && $visit_balance > 0) $visit_payment_status = 'partial';
        
        $stmt = $db->prepare("SELECT COALESCE(SUM(total_discount), 0) as total_discount FROM bills WHERE visit_id = ? AND branch_id = ? AND status != 'cancelled'");
        $stmt->execute([$visit_id, $branch_id]);
        $visit_discount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_discount'] ?? 0);
        
        $db->prepare("UPDATE visits SET payment_status = ?, visit_total = ?, total_discount = ?, updated_at = NOW() WHERE id = ? AND branch_id = ?")
           ->execute([$visit_payment_status, $visit_total, $visit_discount, $visit_id, $branch_id]);
        return true;
    } catch (Exception $e) { return false; }
}

function updateVisitCompletionStatus($db, $visit_id, $branch_id) {
    if (empty($visit_id) || $visit_id <= 0) return false;
    try {
        if (!isVisitReadyForCompletion($db, $visit_id, $branch_id)) return false;
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_bills,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_bills,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bills
            FROM bills WHERE visit_id = ? AND branch_id = ?
        ");
        $stmt->execute([$visit_id, $branch_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = (int)($result['total_bills'] ?? 0);
        $paid = (int)($result['paid_bills'] ?? 0);
        $cancelled = (int)($result['cancelled_bills'] ?? 0);
        $active_bills = $total - $cancelled;
        if ($active_bills > 0 && $paid == $active_bills) {
            $stmt = $db->prepare("
                UPDATE visits SET is_completed = 1, completed_at = NOW(), status = 'completed',
                    payment_status = 'paid', updated_at = NOW()
                WHERE id = ? AND branch_id = ? AND status = 'waiting' AND is_completed = 0
            ");
            $stmt->execute([$visit_id, $branch_id]);
            return $stmt->rowCount() > 0;
        }
        return false;
    } catch (Exception $e) { return false; }
}

function autoCompleteVisits($db, $visit_ids, $branch_id) {
    if (empty($visit_ids) || !is_array($visit_ids)) return 0;
    $completed_count = 0;
    try {
        foreach ($visit_ids as $visit_id) {
            if ($visit_id <= 0) continue;
            if (!isVisitReadyForCompletion($db, $visit_id, $branch_id)) continue;
            $stmt_visit = $db->prepare("
                SELECT v.id, v.is_completed, COALESCE(SUM(b.balance), 0) as total_balance, COUNT(b.id) as bill_count
                FROM visits v
                LEFT JOIN bills b ON b.visit_id = v.id AND b.status != 'cancelled'
                WHERE v.id = ? AND v.branch_id = ? GROUP BY v.id
            ");
            $stmt_visit->execute([$visit_id, $branch_id]);
            $visit = $stmt_visit->fetch(PDO::FETCH_ASSOC);
            if (!$visit) continue;
            $balance = (float)$visit['total_balance'];
            $is_completed = (int)$visit['is_completed'];
            $bill_count = (int)$visit['bill_count'];
            if ($is_completed == 0 && $balance <= 0.01 && $bill_count > 0) {
                $stmt_update = $db->prepare("
                    UPDATE visits SET is_completed = 1, completed_at = NOW(), status = 'completed',
                        payment_status = 'paid', updated_at = NOW()
                    WHERE id = ? AND branch_id = ? AND status = 'waiting' AND is_completed = 0
                ");
                $stmt_update->execute([$visit_id, $branch_id]);
                if ($stmt_update->rowCount() > 0) $completed_count++;
            }
        }
        return $completed_count;
    } catch (Exception $e) { return 0; }
}

function recalculateBillTotals($db, $bill_id, $branch_id) {
    $stmt = $db->prepare("
        SELECT subtotal, discount_amount, cashier_discount, pharmacy_discount,
               premium_amount, cashier_premium, pharmacy_premium,
               total_discount, total_amount, paid_amount, status
        FROM bills WHERE id = ? AND branch_id = ?
    ");
    $stmt->execute([$bill_id, $branch_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bill) return null;
    
    $subtotal = (float)$bill['subtotal'];
    $pharmacy_discount = (float)($bill['pharmacy_discount'] ?? 0);
    if ($pharmacy_discount == 0 && (float)$bill['discount_amount'] > 0) $pharmacy_discount = (float)$bill['discount_amount'];
    $cashier_discount = (float)($bill['cashier_discount'] ?? 0);
    $pharmacy_premium = (float)($bill['pharmacy_premium'] ?? 0);
    $cashier_premium = (float)($bill['cashier_premium'] ?? 0);
    $paid_amount = (float)$bill['paid_amount'];
    
    $total_discount = $pharmacy_discount + $cashier_discount;
    if ($total_discount > $subtotal) $total_discount = $subtotal;
    $total_premium = $pharmacy_premium + $cashier_premium;
    $total_amount = $subtotal + $total_premium - $total_discount;
    if ($total_amount < 0) $total_amount = 0;
    $balance = $total_amount - $paid_amount;
    if ($balance < 0) $balance = 0;
    if ($balance > 0 && $balance <= 0.01) { $balance = 0; $paid_amount = $total_amount; }
    if ($balance <= 0.01) { $balance = 0; $paid_amount = $total_amount; }
    
    if ($total_amount <= 0) $status = 'paid';
    elseif ($balance <= 0.01) $status = 'paid';
    elseif ($paid_amount > 0 && $balance > 0) $status = 'partial';
    else $status = 'pending';
    
    $db->prepare("
        UPDATE bills SET total_discount = ?, premium_amount = ?, total_amount = ?, 
            paid_amount = ?, balance = ?, status = ?, updated_at = NOW()
        WHERE id = ? AND branch_id = ?
    ")->execute([$total_discount, $total_premium, $total_amount, $paid_amount, $balance, $status, $bill_id, $branch_id]);
    
    return [
        'subtotal' => $subtotal, 'pharmacy_discount' => $pharmacy_discount,
        'cashier_discount' => $cashier_discount, 'total_discount' => $total_discount,
        'pharmacy_premium' => $pharmacy_premium, 'cashier_premium' => $cashier_premium,
        'premium_amount' => $total_premium, 'total_amount' => $total_amount,
        'paid_amount' => $paid_amount, 'balance' => $balance, 'status' => $status
    ];
}

$selected_bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$message = '';
$message_type = '';
$currency = 'TSh';

try {
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';

    // ================================================================
    // V27: HANDLE PAYMENT
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        
        $action = $_POST['action'];
        $item_ids = isset($_POST['item_ids']) ? $_POST['item_ids'] : [];
        $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
        $cashier_discount = isset($_POST['discount_amount']) ? floatval($_POST['discount_amount']) : 0;
        $cashier_premium = isset($_POST['premium_amount']) ? floatval($_POST['premium_amount']) : 0;
        $premium_note = isset($_POST['premium_note']) ? trim($_POST['premium_note']) : '';
        $partial_amount = isset($_POST['partial_amount']) ? floatval($_POST['partial_amount']) : 0;
        
        if ($action === 'complete_payment' || $action === 'partial_payment') {
            if (empty($item_ids) || !is_array($item_ids)) {
                echo json_encode(['success' => false, 'message' => 'No items selected for payment']);
                exit;
            }
            
            if ($action === 'partial_payment' && $partial_amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Please enter a valid partial amount']);
                exit;
            }
            
            try {
                $db->beginTransaction();
                
                removeDuplicateBillItems($db, $user_branch_id);
                
                $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
                $stmt = $db->prepare("
                    SELECT bi.*, 
                        b.id as bill_id, b.bill_number, b.patient_id, b.branch_id,
                        b.visit_id, b.balance as bill_balance, b.subtotal as bill_subtotal,
                        b.total_amount as bill_total,
                        b.discount_amount as pharmacy_discount_legacy,
                        b.pharmacy_discount as pharmacy_discount_new,
                        b.cashier_discount as existing_cashier_discount,
                        b.total_discount as existing_total_discount,
                        b.premium_amount as bill_premium_total,
                        b.pharmacy_premium as bill_pharmacy_premium,
                        b.cashier_premium as bill_cashier_premium,
                        b.premium_note as bill_premium_note,
                        b.status as bill_status, b.paid_amount as bill_paid,
                        (SELECT status FROM prescriptions WHERE id = bi.reference_id AND bi.reference_type = 'prescription') as prescription_status
                    FROM bill_items bi
                    JOIN bills b ON bi.bill_id = b.id
                    WHERE bi.id IN ($placeholders) AND bi.status != 'paid' AND bi.status != 'cancelled'
                    FOR UPDATE
                ");
                $stmt->execute($item_ids);
                $selected_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($selected_items)) {
                    $db->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Selected items not found or already paid']);
                    exit;
                }
                
                // FULL PAYMENT validation
                if ($action === 'complete_payment') {
                    $affected_bill_ids = array_unique(array_column($selected_items, 'bill_id'));
                    $all_payable_items = [];
                    foreach ($affected_bill_ids as $abid) {
                        $stmt_all = $db->prepare("SELECT id FROM bill_items WHERE bill_id = ? AND branch_id = ? AND status != 'paid' AND status != 'cancelled'");
                        $stmt_all->execute([$abid, $user_branch_id]);
                        $all_payable_items = array_merge($all_payable_items, $stmt_all->fetchAll(PDO::FETCH_COLUMN));
                    }
                    
                    sort($all_payable_items);
                    $selected_sorted = $item_ids;
                    sort($selected_sorted);
                    
                    if ($all_payable_items !== array_map('intval', $selected_sorted)) {
                        $db->rollBack();
                        echo json_encode(['success' => false, 'message' => "⚠️ FULL PAYMENT INATAKA ITEMS ZOTE!\n\nUmechagua " . count($item_ids) . " kati ya " . count($all_payable_items) . " items."]);
                        exit;
                    }
                }
                
                // Check locked bills
                $locked_bills = [];
                foreach ($selected_items as $item) {
                    if ($item['item_type'] === 'medication' && $item['reference_type'] === 'prescription') {
                        $pres_status = $item['prescription_status'] ?? 'pending';
                        if ($pres_status !== 'confirmed' && $pres_status !== 'dispensed') {
                            if (!in_array($item['bill_id'], $locked_bills)) $locked_bills[] = $item['bill_id'];
                        }
                    }
                }
                
                if (!empty($locked_bills)) {
                    $db->rollBack();
                    echo json_encode(['success' => false, 'message' => "🔒 HAIWEZI KULIPWA!\n\nBill(s) zina prescriptions ambazo hazijaconfirm."]);
                    exit;
                }
                
                // Process each bill
                $bill_map = [];
                $total_original_amount = 0;
                $affected_visit_ids = [];
                
                foreach ($selected_items as $item) {
                    $bill_id = $item['bill_id'];
                    $visit_id = $item['visit_id'] ?? 0;
                    if ($visit_id > 0 && !in_array($visit_id, $affected_visit_ids)) $affected_visit_ids[] = $visit_id;
                    
                    if (!isset($bill_map[$bill_id])) {
                        $stmt_bill = $db->prepare("SELECT paid_amount, balance, subtotal, total_amount, total_discount, discount_amount, pharmacy_discount, cashier_discount, premium_amount, pharmacy_premium, cashier_premium, premium_note FROM bills WHERE id = ? AND branch_id = ? FOR UPDATE");
                        $stmt_bill->execute([$bill_id, $user_branch_id]);
                        $current_bill = $stmt_bill->fetch(PDO::FETCH_ASSOC);
                        
                        $pharmacy_discount_val = (float)($current_bill['pharmacy_discount'] ?? 0);
                        if ($pharmacy_discount_val == 0 && (float)($current_bill['discount_amount'] ?? 0) > 0) $pharmacy_discount_val = (float)$current_bill['discount_amount'];
                        
                        $stmt_sub = $db->prepare("SELECT COALESCE(SUM(total_price), 0) as correct_subtotal FROM bill_items WHERE bill_id = ? AND branch_id = ? AND status != 'cancelled'");
                        $stmt_sub->execute([$bill_id, $user_branch_id]);
                        $correct_subtotal = (float)($stmt_sub->fetch(PDO::FETCH_ASSOC)['correct_subtotal'] ?? 0);
                        
                        $bill_map[$bill_id] = [
                            'bill_id' => $bill_id, 'bill_number' => $item['bill_number'],
                            'patient_id' => $item['patient_id'], 'visit_id' => $visit_id,
                            'items' => [], 'items_total' => 0,
                            'bill_paid' => (float)($current_bill['paid_amount'] ?? 0),
                            'bill_subtotal' => $correct_subtotal > 0 ? $correct_subtotal : (float)($current_bill['subtotal'] ?? 0),
                            'bill_total' => (float)($current_bill['total_amount'] ?? 0),
                            'pharmacy_discount' => $pharmacy_discount_val,
                            'existing_cashier_discount' => (float)($current_bill['cashier_discount'] ?? 0),
                            'pharmacy_premium' => (float)($current_bill['pharmacy_premium'] ?? 0),
                            'existing_cashier_premium' => (float)($current_bill['cashier_premium'] ?? 0),
                            'bill_premium_note' => $current_bill['premium_note'] ?? ''
                        ];
                    }
                    $bill_map[$bill_id]['items'][] = $item;
                    $bill_map[$bill_id]['items_total'] += (float)$item['total_price'];
                    $total_original_amount += (float)$item['total_price'];
                }
                
                $success_count = 0;
                $total_items_updated = 0;
                $processed_bill_ids = [];
                $fully_paid_bill_ids = [];
                $total_amount_paid = 0;
                
                foreach ($bill_map as $bill_id => $bill_data) {
                    $processed_bill_ids[] = $bill_id;
                    
                    $pharmacy_discount = $bill_data['pharmacy_discount'];
                    $pharmacy_premium = $bill_data['pharmacy_premium'];
                    $existing_cashier_discount = $bill_data['existing_cashier_discount'];
                    $existing_cashier_premium = $bill_data['existing_cashier_premium'];
                    
                    $bill_portion = ($total_original_amount > 0) ? ($bill_data['items_total'] / $total_original_amount) : 1;
                    
                    $bill_cashier_discount = round($cashier_discount * $bill_portion, 2);
                    $new_cashier_discount = ($bill_cashier_discount > 0) ? $bill_cashier_discount : $existing_cashier_discount;
                    
                    $bill_cashier_premium = round($cashier_premium * $bill_portion, 2);
                    $new_cashier_premium = ($bill_cashier_premium > 0) ? $bill_cashier_premium : $existing_cashier_premium;
                    
                    $new_total_discount = $pharmacy_discount + $new_cashier_discount;
                    if ($new_total_discount > $bill_data['bill_subtotal']) {
                        $new_total_discount = $bill_data['bill_subtotal'];
                        $new_cashier_discount = $new_total_discount - $pharmacy_discount;
                        if ($new_cashier_discount < 0) $new_cashier_discount = 0;
                    }
                    
                    $new_total_premium = $pharmacy_premium + $new_cashier_premium;
                    $new_total_amount = $bill_data['bill_subtotal'] + $new_total_premium - $new_total_discount;
                    if ($new_total_amount < 0) $new_total_amount = 0;
                    
                    if ($action === 'complete_payment') {
                        $bill_payment = $new_total_amount - $bill_data['bill_paid'];
                        if ($bill_payment < 0) $bill_payment = 0;
                        $new_paid_amount = $new_total_amount;
                        $new_balance = 0;
                    } else {
                        $bill_payment = round($partial_amount * $bill_portion, 2);
                        $max_payable = $new_total_amount - $bill_data['bill_paid'];
                        if ($bill_payment > $max_payable) $bill_payment = $max_payable;
                        if ($bill_payment < 0) $bill_payment = 0;
                        $new_paid_amount = $bill_data['bill_paid'] + $bill_payment;
                        $new_balance = $new_total_amount - $new_paid_amount;
                        if ($new_balance < 0) $new_balance = 0;
                    }
                    
                    if ($new_total_amount <= 0 || $new_balance <= 0.01) $new_status = 'paid';
                    elseif ($new_paid_amount > 0 && $new_balance > 0) $new_status = 'partial';
                    else $new_status = 'pending';
                    
                    $final_premium_note = '';
                    if ($new_total_premium > 0) {
                        if (!empty($premium_note)) $final_premium_note = $premium_note;
                        elseif (!empty($bill_data['bill_premium_note'])) $final_premium_note = $bill_data['bill_premium_note'];
                        else $final_premium_note = 'Premium Charge';
                    }
                    
                    $db->prepare("
                        UPDATE bills SET paid_amount = ?, balance = ?, total_amount = ?, subtotal = ?,
                            cashier_discount = ?, cashier_premium = ?, pharmacy_discount = ?, pharmacy_premium = ?,
                            total_discount = ?, discount_amount = ?, premium_amount = ?, premium_note = ?, 
                            status = ?, updated_at = NOW()
                        WHERE id = ? AND branch_id = ?
                    ")->execute([
                        $new_paid_amount, $new_balance, $new_total_amount, $bill_data['bill_subtotal'],
                        $new_cashier_discount, $new_cashier_premium, $pharmacy_discount, $pharmacy_premium,
                        $new_total_discount, $pharmacy_discount, $new_total_premium, $final_premium_note,
                        $new_status, $bill_id, $user_branch_id
                    ]);
                    
                    if ($action === 'complete_payment') {
                        $stmt_update_items = $db->prepare("UPDATE bill_items SET status = 'paid', updated_at = NOW() WHERE bill_id = ? AND branch_id = ? AND status != 'cancelled'");
                        $stmt_update_items->execute([$bill_id, $user_branch_id]);
                        $total_items_updated += $stmt_update_items->rowCount();
                    } else {
                        $item_ids_for_bill = array_column($bill_data['items'], 'id');
                        if (!empty($item_ids_for_bill)) {
                            $placeholders2 = implode(',', array_fill(0, count($item_ids_for_bill), '?'));
                            $stmt_update_items = $db->prepare("UPDATE bill_items SET status = 'paid', updated_at = NOW() WHERE id IN ($placeholders2)");
                            $stmt_update_items->execute($item_ids_for_bill);
                            $total_items_updated += count($item_ids_for_bill);
                        }
                    }
                    
                    syncProceduresStatus($db, $bill_id, $user_branch_id);
                    
                    if ($new_status === 'paid' && $new_balance <= 0.01) {
                        $fully_paid_bill_ids[] = $bill_id;
                    }
                    
                    $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                    $notes = 'V27 Payment';
                    
                    $db->prepare("
                        INSERT INTO payments (receipt_number, bill_id, patient_id, amount, payment_method, received_by, branch_id, received_at, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                    ")->execute([$receipt_number, $bill_id, $bill_data['patient_id'], $bill_payment, $payment_method, $user_id, $user_branch_id, $notes]);
                    
                    recalculateBillTotals($db, $bill_id, $user_branch_id);
                    $total_amount_paid += $bill_payment;
                    $success_count++;
                }
                
                // ================================================================
                // V27: STOCK DEDUCTION - FULLY PAID BILLS ONLY
                // ================================================================
                $stock_summary = [
                    'medications_deducted' => 0, 'medication_items' => 0,
                    'equipment_deducted' => 0, 'equipment_items' => 0,
                    'lab_equipment_deducted' => 0, 'lab_equipment_items' => 0,
                    'skipped' => 0, 'errors' => [],
                    'fully_paid_bills' => count($fully_paid_bill_ids)
                ];
                
                foreach ($fully_paid_bill_ids as $paid_bill_id) {
                    $bill_stock = deductAllStockForPaidBill($db, $paid_bill_id, $user_branch_id, $user_id);
                    
                    $stock_summary['medications_deducted'] += $bill_stock['medications_deducted'];
                    $stock_summary['medication_items'] += $bill_stock['medication_items'];
                    $stock_summary['equipment_deducted'] += $bill_stock['equipment_deducted'];
                    $stock_summary['equipment_items'] += $bill_stock['equipment_items'];
                    $stock_summary['lab_equipment_deducted'] += $bill_stock['lab_equipment_deducted'];
                    $stock_summary['lab_equipment_items'] += $bill_stock['lab_equipment_items'];
                    $stock_summary['skipped'] += $bill_stock['skipped'];
                    $stock_summary['errors'] = array_merge($stock_summary['errors'], $bill_stock['errors']);
                }
                
                // Update visits
                foreach ($affected_visit_ids as $visit_id) {
                    if ($visit_id > 0) {
                        updateVisitPaymentStatus($db, $visit_id, $user_branch_id);
                        updateVisitCompletionStatus($db, $visit_id, $user_branch_id);
                    }
                }
                
                $completed_visits = autoCompleteVisits($db, $affected_visit_ids, $user_branch_id);
                
                $db->commit();
                
                // Build message
                $message = "✅ " . $success_count . " bill(s) updated!<br>";
                $message .= "💰 Total Paid: <strong>" . $currency . " " . number_format($total_amount_paid, 0) . "</strong>";
                $message .= "<br>📦 " . $total_items_updated . " item(s) marked as PAID";
                
                if ($action === 'partial_payment') {
                    $message .= "<br>⚠️ <strong>PARTIAL payment</strong> — Stock HAIJADEDUCT";
                } else {
                    if ($stock_summary['medications_deducted'] > 0) {
                        $message .= "<br>💊 <strong>" . $stock_summary['medications_deducted'] . "</strong> medication qty deducted";
                    }
                    if ($stock_summary['equipment_deducted'] > 0) {
                        $message .= "<br>🛠️ <strong>" . $stock_summary['equipment_deducted'] . "</strong> equipment qty deducted";
                    }
                    if ($stock_summary['lab_equipment_deducted'] > 0) {
                        $message .= "<br>🧪 <strong>" . $stock_summary['lab_equipment_deducted'] . "</strong> lab equipment qty deducted";
                    }
                    if ($stock_summary['skipped'] > 0) {
                        $message .= "<br>⏭️ " . $stock_summary['skipped'] . " item(s) skipped (already deducted)";
                    }
                }
                if ($completed_visits > 0) $message .= "<br>✅ " . $completed_visits . " visit(s) COMPLETE!";
                
                echo json_encode([
                    'success' => true, 
                    'message' => $message,
                    'total_paid' => $total_amount_paid,
                    'count' => $success_count, 
                    'items_updated' => $total_items_updated,
                    'stock_summary' => $stock_summary,
                    'payment_type' => $action
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                error_log("V27 payment error: " . $e->getMessage());
            }
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }

    // ================================================================
    // GET BILLS (READ-ONLY - NO AUTO-REPAIR!)
    // ✅ V27: AUTO-REPAIR IMEFUTWA - Haifanyi deduction yoyote
    // ================================================================
    $bills_query = "
        SELECT b.*, b.discount_amount, b.pharmacy_discount, b.cashier_discount,
            b.total_discount, b.paid_amount, b.balance, b.subtotal,
            b.total_amount, b.premium_amount, b.premium_note, b.visit_id,
            b.pharmacy_premium, b.cashier_premium,
            b.pharmacy_premium_note, b.cashier_premium_note,
            v.visit_number, v.visit_type, v.visit_date,
            v.payment_status as visit_payment_status,
            v.status as visit_status, v.is_completed as visit_is_completed,
            u.full_name as doctor_name, p.full_name as patient_name,
            p.patient_id as patient_number,
            p.phone, p.gender, p.date_of_birth, p.address, p.blood_group, p.email
        FROM bills b
        JOIN patients p ON b.patient_id = p.id
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE b.branch_id = ? AND b.status != 'cancelled'
    ";

    $params = [$user_branch_id];

    if ($selected_bill_id > 0) {
        $bills_query .= " AND b.id = ?";
        $params[] = $selected_bill_id;
    }

    if (!empty($search)) {
        $bills_query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR b.bill_number LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $bills_query .= " ORDER BY b.created_at ASC";

    $stmt = $db->prepare($bills_query);
    $stmt->execute($params);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ V27: HAKUNA AUTO-REPAIR! Deduction inafanyika TU wakati wa payment.
    
    // Sync procedures status (hii ni safe - ina-update status pekee)
    try {
        $stmt = $db->prepare("
            SELECT DISTINCT b.id FROM bills b
            WHERE b.branch_id = ? AND b.status IN ('paid', 'partial')
        ");
        $stmt->execute([$user_branch_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $bid) {
            syncProceduresStatus($db, $bid, $user_branch_id);
        }
    } catch (Exception $e) {}
    
    // Update bill_items status for paid bills (hii ni safe)
    try {
        $db->prepare("
            UPDATE bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            SET bi.status = 'paid', bi.updated_at = NOW()
            WHERE b.branch_id = ? AND b.status = 'paid'
            AND b.balance <= 0.01 AND bi.status != 'cancelled' AND bi.status != 'paid'
        ")->execute([$user_branch_id]);
    } catch (Exception $e) {}

    $stmt = $db->prepare($bills_query);
    $stmt->execute($params);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bills as $index => $bill) {
        $recalc = recalculateBillTotals($db, $bill['id'], $user_branch_id);
        if ($recalc) {
            $bills[$index]['subtotal'] = $recalc['subtotal'];
            $bills[$index]['total_discount'] = $recalc['total_discount'];
            $bills[$index]['discount_amount'] = $recalc['pharmacy_discount'];
            $bills[$index]['pharmacy_discount'] = $recalc['pharmacy_discount'];
            $bills[$index]['cashier_discount'] = $recalc['cashier_discount'];
            $bills[$index]['pharmacy_premium'] = $recalc['pharmacy_premium'];
            $bills[$index]['cashier_premium'] = $recalc['cashier_premium'];
            $bills[$index]['premium_amount'] = $recalc['premium_amount'];
            $bills[$index]['total_amount'] = $recalc['total_amount'];
            $bills[$index]['paid_amount'] = $recalc['paid_amount'];
            $bills[$index]['balance'] = $recalc['balance'];
            $bills[$index]['status'] = $recalc['status'];
        }
    }

    $all_items_by_bill = [];
    $medication_confirmed = [];
    $bill_locked_status = [];
    $bill_pending_prescription_count = [];
    
    foreach ($bills as $bill) {
        $stmt = $db->prepare("
            SELECT bi.*,
                (SELECT status FROM prescriptions WHERE id = bi.reference_id AND bi.reference_type = 'prescription') as prescription_status
            FROM bill_items bi
            WHERE bi.bill_id = ? AND bi.status != 'cancelled'
            GROUP BY bi.id
            ORDER BY bi.item_type ASC, bi.created_at ASC
        ");
        $stmt->execute([$bill['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $all_items_by_bill[$bill['id']] = $items;
        
        $has_medication = false;
        $med_confirmed = true;
        $pending_pres_count = 0;
        foreach ($items as $item) {
            if ($item['item_type'] === 'medication') {
                $has_medication = true;
                $pres_status = $item['prescription_status'] ?? 'pending';
                if ($pres_status !== 'confirmed' && $pres_status !== 'dispensed') {
                    $med_confirmed = false;
                    if ($item['reference_type'] === 'prescription') $pending_pres_count++;
                }
                if (($item['unit_price'] ?? 0) <= 0) $med_confirmed = false;
            }
        }
        $medication_confirmed[$bill['id']] = $has_medication ? $med_confirmed : true;
        $bill_pending_prescription_count[$bill['id']] = $pending_pres_count;
        $bill_locked_status[$bill['id']] = ($pending_pres_count > 0);
    }

    $patient_bills_data = [];
    $patient_map = [];

    foreach ($bills as $bill) {
        $patient_id = $bill['patient_id'];
        if (!isset($patient_map[$patient_id])) {
            $patient_map[$patient_id] = [
                'patient_id' => $patient_id,
                'full_name' => $bill['patient_name'],
                'patient_number' => $bill['patient_number'],
                'phone' => $bill['phone'],
                'gender' => $bill['gender'],
                'date_of_birth' => $bill['date_of_birth'],
                'address' => $bill['address'],
                'blood_group' => $bill['blood_group'],
                'email' => $bill['email'],
                'doctor_name' => $bill['doctor_name'],
                'bills' => []
            ];
        }
        $bill['items'] = $all_items_by_bill[$bill['id']] ?? [];
        $bill['med_confirmed'] = $medication_confirmed[$bill['id']] ?? true;
        $bill['is_locked'] = $bill_locked_status[$bill['id']] ?? false;
        $bill['pending_prescriptions'] = $bill_pending_prescription_count[$bill['id']] ?? 0;
        $patient_map[$patient_id]['bills'][] = $bill;
    }

    $patient_bills_data = array_values($patient_map);

    $total_patients = count($patient_bills_data);
    $total_bills = count($bills);
    $total_balance = 0; $total_subtotal = 0; $total_amount = 0;
    $total_pharmacy_discount = 0; $total_cashier_discount = 0; $total_discount = 0;
    $total_paid = 0; $total_pharmacy_premium = 0; $total_cashier_premium = 0;
    $total_premium = 0; $total_locked_bills = 0; $total_pending_prescriptions = 0;

    foreach ($bills as $bill) {
        $total_subtotal += (float)($bill['subtotal'] ?? 0);
        $total_amount += (float)($bill['total_amount'] ?? 0);
        $total_paid += (float)($bill['paid_amount'] ?? 0);
        $total_pharmacy_discount += (float)($bill['discount_amount'] ?? 0);
        $total_cashier_discount += (float)($bill['cashier_discount'] ?? 0);
        $total_discount += (float)($bill['total_discount'] ?? 0);
        $total_balance += (float)($bill['balance'] ?? 0);
        $total_pharmacy_premium += (float)($bill['pharmacy_premium'] ?? 0);
        $total_cashier_premium += (float)($bill['cashier_premium'] ?? 0);
        $total_premium += (float)($bill['premium_amount'] ?? 0);
        if ($bill_locked_status[$bill['id']] ?? false) $total_locked_bills++;
        $total_pending_prescriptions += ($bill_pending_prescription_count[$bill['id']] ?? 0);
    }

    $has_selected_bill = $selected_bill_id > 0 && !empty($bills);
    $selected_bill = null;
    if ($has_selected_bill) {
        foreach ($bills as $bill) {
            if ($bill['id'] == $selected_bill_id) { $selected_bill = $bill; break; }
        }
    }

} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $bills = []; $patient_bills_data = [];
    $total_bills = 0; $total_patients = 0; $total_balance = 0;
    $total_subtotal = 0; $total_amount = 0; $total_pharmacy_discount = 0;
    $total_cashier_discount = 0; $total_discount = 0; $total_paid = 0;
    $total_pharmacy_premium = 0; $total_cashier_premium = 0; $total_premium = 0;
    $total_locked_bills = 0; $total_pending_prescriptions = 0;
    $has_selected_bill = false; $selected_bill = null; $currency = 'TSh';
    $all_items_by_bill = []; $medication_confirmed = [];
    $bill_locked_status = []; $bill_pending_prescription_count = [];
    error_log("Process payment error: " . $e->getMessage());
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Payment - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --font-primary: 'Inter', -apple-system, sans-serif;
            --font-mono: 'JetBrains Mono', 'Courier New', monospace;
            --primary: #059669;
            --primary-dark: #047857;
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-dark: #B45309;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
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
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 30px rgba(0,0,0,0.12);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --locked-color: #DC2626;
            --locked-bg: #FEE2E2;
            --sidebar-width: 270px;
            --content-max-width: 1200px;
            --radius: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --locked-bg: #3A1A1A;
            --gray-50: #1E293B;
            --gray-100: #334155;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: var(--font-primary); 
            background: var(--bg-body); 
            color: var(--text-primary); 
            -webkit-font-smoothing: antialiased;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: 68px;
            padding: 20px 24px 140px 24px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: linear-gradient(135deg, #059669 0%, #047857 50%, #065F46 100%);
            border-radius: var(--radius-xl);
            padding: 20px 28px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            box-shadow: 0 8px 30px rgba(5, 150, 105, 0.22);
            max-width: var(--content-max-width);
            margin-left: auto;
            margin-right: auto;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -10%;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.4rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i { font-size: 1.6rem; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.78rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 6px;
            position: relative;
            z-index: 1;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 18px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            backdrop-filter: blur(8px);
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 3px 12px;
            border-radius: 18px;
            font-size: 0.68rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            backdrop-filter: blur(8px);
        }
        
        .page-header .header-badge.locked {
            background: rgba(239, 68, 68, 0.4);
            color: #FECACA;
        }
        
        .page-header .header-badge.new-version {
            background: rgba(16,185,129,0.35);
            color: #A7F3D0;
            font-weight: 700;
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.78rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            backdrop-filter: blur(8px);
            transition: var(--transition);
            position: relative;
            z-index: 1;
            cursor: pointer;
        }
        
        .page-header .btn-outline-light:hover { 
            background: rgba(255,255,255,0.28);
            transform: translateY(-2px);
        }
        
        .locked-warning {
            background: var(--locked-bg);
            border: 2px solid var(--locked-color);
            border-radius: var(--radius-lg);
            padding: 14px 20px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            max-width: var(--content-max-width);
            margin-left: auto;
            margin-right: auto;
        }
        
        .locked-warning i { font-size: 1.8rem; color: var(--locked-color); }
        
        .locked-warning .warning-content h4 {
            font-weight: 800;
            font-size: 0.9rem;
            color: var(--locked-color);
            margin-bottom: 3px;
        }
        
        .locked-warning .warning-content p {
            font-size: 0.78rem;
            color: var(--locked-color);
            opacity: 0.85;
        }
        
        .patient-card {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            border: 2px solid var(--border-color);
            margin-bottom: 18px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            max-width: var(--content-max-width);
            margin-left: auto;
            margin-right: auto;
            transition: var(--transition);
        }
        
        .patient-card:hover { 
            border-color: var(--success);
            box-shadow: 0 8px 30px rgba(5, 150, 105, 0.15);
        }
        
        .patient-card.locked-card { border-color: var(--locked-color); }
        
        .patient-card .card-header {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            padding: 16px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .patient-card.locked-card .card-header {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
        }
        
        .patient-card .card-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -10%;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .patient-card .card-header .patient-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .patient-card .card-header .patient-avatar {
            width: 44px; height: 44px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.15rem;
            color: white;
            border: 2px solid rgba(255,255,255,0.4);
            backdrop-filter: blur(8px);
            flex-shrink: 0;
        }
        
        .patient-card .card-header .patient-name { 
            font-weight: 800; 
            font-size: 0.98rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .patient-card .card-header .patient-id { 
            font-size: 0.68rem; 
            opacity: 0.85; 
            font-family: var(--font-mono);
            font-weight: 600;
        }
        
        .patient-card .card-header .patient-doctor {
            font-size: 0.62rem;
            opacity: 0.85;
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .patient-card .card-header .bill-summary {
            display: flex;
            gap: 8px;
            font-size: 0.68rem;
            background: rgba(255,255,255,0.15);
            padding: 6px 14px;
            border-radius: 18px;
            align-items: center;
            flex-wrap: wrap;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.15);
            position: relative;
            z-index: 1;
        }
        
        .patient-card .card-header .bill-summary .summary-item {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: var(--font-mono);
            font-weight: 700;
        }
        
        .patient-card .card-header .bill-summary .divider {
            opacity: 0.3;
            font-weight: 300;
        }
        
        .patient-card .card-body { padding: 0; }
        .patient-card .card-body.collapsed { display: none; }
        
        .master-table-wrap { 
            overflow-x: auto; 
            background: var(--bg-card);
            border-radius: 0 0 var(--radius-xl) var(--radius-xl);
        }
        
        .master-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            min-width: 900px;
        }
        
        .master-table thead th {
            text-align: left;
            padding: 11px 14px;
            font-weight: 800;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: white;
            background: linear-gradient(135deg, #064E3B, #065F46);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .master-table tbody td {
            padding: 11px 14px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            font-family: var(--font-mono);
            font-variant-numeric: tabular-nums;
        }
        
        .master-table tbody tr:hover td { background: var(--success-bg); }
        [data-theme="dark"] .master-table tbody tr:hover td { background: rgba(5,150,105,0.15); }
        
        .master-table tbody tr.item-locked td { background: var(--locked-bg) !important; }
        [data-theme="dark"] .master-table tbody tr.item-locked td { background: rgba(220,38,38,0.15) !important; }
        
        .master-table tbody tr.bill-paid td { opacity: 0.65; background: var(--success-bg); }
        [data-theme="dark"] .master-table tbody tr.bill-paid td { background: rgba(5,150,105,0.1); }
        
        .header-info-row { background: linear-gradient(135deg, #064E3B, #065F46); }
        .header-info-row td { padding: 10px 14px !important; }
        
        .header-info-content {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            font-size: 0.75rem;
        }
        
        .header-info-content .info-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 10px;
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .header-info-content .info-item .label {
            font-weight: 600;
            color: rgba(255,255,255,0.75);
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .header-info-content .info-item .value {
            font-weight: 800;
            font-size: 0.85rem;
            font-family: var(--font-mono);
            color: white;
        }
        
        .header-info-content .info-item .value.subtotal-value { color: #6EE7B7; }
        .header-info-content .info-item .value.paid-value { color: #34D399; }
        .header-info-content .info-item .value.balance-value { color: #FCA5A5; }
        .header-info-content .info-item .value.balance-value.zero-balance { color: #6EE7B7; }
        .header-info-content .info-item .value.pharm-discount-value { color: #FCD34D; }
        .header-info-content .info-item .value.cashier-discount-value { color: #93C5FD; }
        
        .bill-header-row {
            background: var(--gray-100);
            border-bottom: 2px solid var(--border-color);
        }
        
        [data-theme="dark"] .bill-header-row { background: var(--gray-700); }
        .bill-header-row td { padding: 9px 14px !important; }
        
        .bill-header-info {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            font-size: 0.72rem;
        }
        
        .bill-header-info .bill-number {
            font-weight: 800;
            color: var(--primary);
            font-family: var(--font-mono);
            font-size: 0.82rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .bill-header-info .bill-status {
            font-size: 0.58rem;
            font-weight: 800;
            padding: 2px 10px;
            border-radius: 14px;
            text-transform: uppercase;
        }
        
        .bill-header-info .bill-status.pending { background: #FEF3C7; color: #B45309; border: 1px solid #D97706; }
        .bill-header-info .bill-status.partial { background: #DBEAFE; color: #1E40AF; border: 1px solid #2563EB; }
        .bill-header-info .bill-status.paid { background: #D1FAE5; color: #047857; border: 1px solid #059669; }
        
        .bill-header-info .visit-status-badge {
            font-size: 0.58rem;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-transform: uppercase;
        }
        
        .bill-header-info .visit-status-badge.waiting { background: #FEF3C7; color: #B45309; border: 1px solid #D97706; }
        .bill-header-info .visit-status-badge.completed { background: #D1FAE5; color: #047857; border: 1px solid #059669; }
        .bill-header-info .visit-status-badge.lab_test { background: #DBEAFE; color: #1E40AF; border: 1px solid #2563EB; }
        .bill-header-info .visit-status-badge.assigned { background: #EDE9FE; color: #5B21B6; border: 1px solid #7C3AED; }
        .bill-header-info .visit-status-badge.prescribed { background: #FCE7F3; color: #BE185D; border: 1px solid #DB2777; }
        
        .locked-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.56rem;
            font-weight: 800;
            padding: 2px 10px;
            border-radius: 14px;
            background: var(--locked-bg);
            color: var(--locked-color);
            border: 1px solid var(--locked-color);
            text-transform: uppercase;
        }
        
        .premium-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.56rem;
            font-weight: 700;
            padding: 2px 9px;
            border-radius: 12px;
            background: #FEF3C7;
            color: #B45309;
            border: 1px solid #D97706;
        }
        
        .premium-badge.cashier { background: #EDE9FE; color: #6D28D9; border-color: #7C3AED; }
        
        [data-theme="dark"] .premium-badge { background: #3D2E0A; color: #FCD34D; }
        [data-theme="dark"] .premium-badge.cashier { background: #2D1B5E; color: #C4B5FD; }
        
        .item-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--success);
            border-radius: 5px;
            transition: var(--transition);
        }
        
        .item-checkbox:hover:not(:disabled) { transform: scale(1.15); }
        .item-checkbox:disabled { opacity: 0.3; cursor: not-allowed; }
        
        .payment-controls-wrapper {
            position: fixed;
            bottom: 0;
            left: var(--sidebar-width);
            right: 0;
            z-index: 100;
            display: flex;
            justify-content: center;
            padding: 0 16px 8px;
            pointer-events: none;
        }
        
        @media (max-width: 1024px) {
            .payment-controls-wrapper { left: 0; padding: 0 10px 6px; }
        }
        
        .payment-controls {
            background: var(--bg-card);
            border: 2px solid var(--success);
            border-radius: 14px;
            padding: 8px 14px;
            box-shadow: 0 -4px 20px rgba(5, 150, 105, 0.18), 0 4px 16px rgba(0,0,0,0.08);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            max-width: var(--content-max-width);
            width: 100%;
            pointer-events: auto;
            justify-content: center;
        }
        
        .payment-controls .control-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: nowrap;
            flex-shrink: 0;
        }
        
        .payment-controls .control-group label {
            font-size: 0.62rem;
            font-weight: 800;
            color: var(--text-primary);
            white-space: nowrap;
            font-family: var(--font-mono);
            display: inline-flex;
            align-items: center;
            gap: 3px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .payment-controls select,
        .payment-controls input[type="text"] {
            padding: 6px 8px;
            border: 1.5px solid var(--border-color);
            border-radius: 7px;
            font-size: 0.72rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            width: 92px;
            font-family: var(--font-mono);
            height: 34px;
            font-weight: 700;
            transition: var(--transition);
        }
        
        .payment-controls select:focus,
        .payment-controls input[type="text"]:focus {
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
        }
        
        .payment-controls .divider {
            width: 1px;
            height: 24px;
            background: var(--border-color);
            flex-shrink: 0;
        }
        
        .total-display {
            display: flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, var(--success-bg), #A7F3D0);
            padding: 4px 10px;
            border-radius: 10px;
            border: 2px solid var(--success);
            height: 40px;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.15);
        }
        
        [data-theme="dark"] .total-display { background: linear-gradient(135deg, #1A3A2A, #064E3B); }
        
        .total-display .total-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 6px;
            gap: 1px;
            line-height: 1;
        }
        
        .total-display .total-item .label {
            font-size: 0.5rem;
            color: var(--text-secondary);
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
            font-family: var(--font-mono);
            letter-spacing: 0.03em;
        }
        
        .total-display .total-item .value {
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--primary);
            font-family: var(--font-mono);
            white-space: nowrap;
        }
        
        .total-display .total-item .value.grand { color: var(--danger); font-size: 0.9rem; }
        .total-display .total-item .value.premium { color: var(--warning); }
        
        .total-display .total-item.premium-card {
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            border: 1.5px solid #D97706;
            border-radius: 6px;
            padding: 2px 8px;
        }
        
        [data-theme="dark"] .total-display .total-item.premium-card {
            background: linear-gradient(135deg, #3D2E0A, #4A3A12);
        }
        
        .total-display .total-item.premium-card .label { color: #D97706; font-size: 0.45rem; }
        .total-display .total-item.premium-card .value { color: #D97706; font-size: 0.68rem; font-weight: 900; }
        
        .payment-controls .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 14px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 0.68rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
            flex-shrink: 0;
            height: 34px;
            font-family: var(--font-mono);
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }
        
        .payment-controls .btn i { font-size: 0.65rem; }
        
        .payment-controls .btn-success {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            box-shadow: 0 2px 6px rgba(5, 150, 105, 0.3);
        }
        
        .payment-controls .btn-success:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.45);
        }
        
        .payment-controls .btn-warning {
            background: linear-gradient(135deg, #D97706, #B45309);
            color: white;
            box-shadow: 0 2px 6px rgba(217, 119, 6, 0.3);
        }
        
        .payment-controls .btn-warning:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.45);
        }
        
        .payment-controls .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1.5px solid var(--border-color);
        }
        
        .payment-controls .btn-outline:hover:not(:disabled) {
            background: var(--bg-body);
            border-color: var(--success);
            color: var(--success);
        }
        
        .payment-controls .btn:disabled { 
            opacity: 0.4; 
            cursor: not-allowed; 
            transform: none !important; 
            filter: grayscale(0.5);
        }
        
        .payment-controls .selected-counter {
            font-size: 0.65rem;
            color: var(--text-secondary);
            padding: 6px 12px;
            background: var(--bg-body);
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            font-family: var(--font-mono);
            font-weight: 800;
            height: 34px;
            flex-shrink: 0;
            border: 1.5px solid var(--border-color);
            text-transform: uppercase;
        }
        
        .payment-controls .selected-counter strong {
            color: var(--primary);
            margin-left: 5px;
            font-size: 0.8rem;
        }
        
        .payment-controls .amount-input-wrap {
            position: relative;
            display: inline-block;
        }
        
        .payment-controls .amount-input-wrap .currency-prefix {
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 800;
            font-family: var(--font-mono);
            pointer-events: none;
            z-index: 2;
        }
        
        .payment-controls .amount-input-wrap input {
            padding-left: 42px !important;
            text-align: right;
            font-family: var(--font-mono);
            font-size: 0.72rem;
            font-weight: 800;
            width: 100px;
        }
        
        .payment-controls .premium-input {
            border-color: #D97706 !important;
            background: #FEF3C7 !important;
            color: #B45309 !important;
        }
        
        [data-theme="dark"] .payment-controls .premium-input {
            background: #3D2E0A !important;
            color: #FCD34D !important;
        }
        
        .toast-custom {
            position: fixed;
            bottom: 100px;
            right: 24px;
            padding: 16px 24px;
            border-radius: var(--radius-lg);
            z-index: 999;
            max-width: 500px;
            transform: translateY(120px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 14px;
            color: white;
            box-shadow: 0 12px 32px rgba(0,0,0,0.25);
            font-size: 0.85rem;
            line-height: 1.5;
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: linear-gradient(135deg, #059669, #047857); }
        .toast-custom.error { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        .toast-custom.warning { background: linear-gradient(135deg, #D97706, #B45309); }
        
        .empty-state {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            border: 2px dashed var(--border-color);
            padding: 70px 30px;
            box-shadow: var(--shadow-md);
            text-align: center;
            max-width: var(--content-max-width);
            margin-left: auto;
            margin-right: auto;
        }
        
        .empty-state i {
            color: var(--success);
            font-size: 3.5rem;
            display: block;
            margin-bottom: 18px;
            opacity: 0.6;
        }
        
        .empty-state h3 {
            color: var(--text-primary);
            font-size: 1.2rem;
            font-weight: 800;
            margin-bottom: 6px;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        
        @media (max-width: 1200px) {
            .payment-controls { gap: 6px; padding: 6px 10px; }
            .payment-controls .control-group label { font-size: 0.58rem; }
            .payment-controls select,
            .payment-controls input[type="text"] { width: 80px; font-size: 0.68rem; padding: 5px 6px; height: 30px; }
            .payment-controls .amount-input-wrap input { width: 88px; padding-left: 38px !important; }
            .total-display { padding: 3px 8px; height: 36px; }
            .total-display .total-item .value { font-size: 0.65rem; }
            .total-display .total-item .value.grand { font-size: 0.8rem; }
            .payment-controls .btn { padding: 6px 10px; font-size: 0.62rem; height: 30px; }
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px 16px 140px 16px; }
        }
        
        @media (max-width: 768px) {
            .main-content { padding-bottom: 300px; }
            
            .payment-controls {
                max-height: 260px;
                overflow-y: auto;
                flex-direction: column;
                align-items: stretch;
                padding: 10px;
                gap: 6px;
            }
            
            .payment-controls .control-group { justify-content: space-between; width: 100%; }
            .payment-controls .control-group select,
            .payment-controls .control-group input { flex: 1; width: auto; }
            .payment-controls .btn { width: 100%; justify-content: center; }
            .payment-controls .divider { width: 100%; height: 1px; }
            .payment-controls .selected-counter { width: 100%; justify-content: center; }
            
            .total-display {
                flex-wrap: wrap;
                justify-content: center;
                height: auto;
                padding: 8px 10px;
                width: 100%;
            }
            
            .page-header { padding: 16px 20px; }
            .page-header .page-title { font-size: 1.15rem; }
        }
        
        .master-table-wrap::-webkit-scrollbar { height: 8px; }
        .master-table-wrap::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
        .master-table-wrap::-webkit-scrollbar-thumb { background: var(--success); border-radius: 4px; }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-money-bill-wave"></i>
                Process Payments
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
                <?php if ($total_locked_bills > 0): ?>
                    <span class="header-badge locked">
                        <i class="fas fa-lock"></i> <?= $total_locked_bills ?> LOCKED
                    </span>
                <?php endif; ?>
                <span class="header-badge new-version">
                    <i class="fas fa-shield-alt"></i> V27.0 FINAL - NO DOUBLE DEDUCTION
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-credit-card"></i>
                Stock inadeduct TU kama bill imelipwa YOTE (FULL)
                <span class="header-badge">
                    <i class="fas fa-file-invoice"></i> <?= $total_bills ?> Bill(s)
                </span>
                <span class="header-badge">
                    <i class="fas fa-money-bill"></i> Balance: <?= $currency ?> <?= number_format($total_balance, 0) ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-check-circle"></i> Paid: <?= $currency ?> <?= number_format($total_paid, 0) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="pending_bills.php" class="btn-outline-light">
                <i class="fas fa-clock"></i> Pending Bills
            </a>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- LOCKED WARNING -->
    <?php if ($total_locked_bills > 0): ?>
        <div class="locked-warning">
            <i class="fas fa-lock"></i>
            <div class="warning-content">
                <h4>🔒 <?= $total_locked_bills ?> Bill(s) LOCKED — Haiwezi Kulipwa</h4>
                <p>Kuna <strong><?= $total_pending_prescriptions ?></strong> prescription(s) ambazo hazijathibitishwa na pharmacy.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- PATIENT CARDS -->
    <?php if (count($patient_bills_data) > 0): ?>
        <?php foreach ($patient_bills_data as $patient): 
            $patient_bills = isset($patient['bills']) && is_array($patient['bills']) ? $patient['bills'] : [];
            $patient_total_balance = 0;
            $patient_total_subtotal = 0;
            $patient_total_amount = 0;
            $patient_paid_amount = 0;
            $patient_pharmacy_discount = 0;
            $patient_cashier_discount = 0;
            $patient_pharmacy_premium = 0;
            $patient_cashier_premium = 0;
            $patient_items = 0;
            $patient_med_items = 0;
            $patient_equip_items = 0;
            $patient_locked_items = 0;
            $patient_has_locked = false;
            
            foreach ($patient_bills as $bill) {
                $patient_total_balance += (float)($bill['balance'] ?? 0);
                $patient_total_subtotal += (float)($bill['subtotal'] ?? 0);
                $patient_total_amount += (float)($bill['total_amount'] ?? 0);
                $patient_paid_amount += (float)($bill['paid_amount'] ?? 0);
                $patient_pharmacy_discount += (float)($bill['pharmacy_discount'] ?? 0);
                $patient_cashier_discount += (float)($bill['cashier_discount'] ?? 0);
                $patient_pharmacy_premium += (float)($bill['pharmacy_premium'] ?? 0);
                $patient_cashier_premium += (float)($bill['cashier_premium'] ?? 0);
                
                if ($bill['is_locked'] ?? false) $patient_has_locked = true;
                $patient_locked_items += ($bill['pending_prescriptions'] ?? 0);
                
                foreach ($bill['items'] as $item) {
                    $patient_items++;
                    if ($item['item_type'] === 'medication') $patient_med_items++;
                    if ($item['item_type'] === 'equipment') $patient_equip_items++;
                }
            }
            
            $doctor_name = $patient['doctor_name'] ?? 'Not Assigned';
            $is_selected_patient = $has_selected_bill && $selected_bill && $selected_bill['patient_id'] == $patient['patient_id'];
        ?>
        <div class="patient-card <?= $patient_has_locked ? 'locked-card' : '' ?>" data-patient-id="<?= $patient['patient_id'] ?>">
            <div class="card-header" onclick="togglePatientCard(this)">
                <div class="patient-info">
                    <div class="patient-avatar" style="background: <?= '#' . substr(md5($patient['full_name']), 0, 6) ?>;">
                        <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="patient-name">
                            <?= htmlspecialchars($patient['full_name']) ?>
                            <?php if ($patient_has_locked): ?>
                                <span class="locked-badge" style="font-size:0.55rem;padding:2px 10px;">
                                    <i class="fas fa-lock"></i> LOCKED
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="patient-id"><?= htmlspecialchars($patient['patient_number']) ?></div>
                        <div class="patient-doctor">
                            <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($doctor_name) ?>
                        </div>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <div class="bill-summary">
                        <span class="summary-item"><i class="fas fa-box"></i> <?= $patient_items ?></span>
                        <span class="divider">•</span>
                        <span class="summary-item" style="color:#FCD34D;"><i class="fas fa-pills"></i> <?= $patient_med_items ?></span>
                        <?php if ($patient_equip_items > 0): ?>
                            <span class="divider">•</span>
                            <span class="summary-item" style="color:#FCA5A5;"><i class="fas fa-tools"></i> <?= $patient_equip_items ?></span>
                        <?php endif; ?>
                        <?php if ($patient_locked_items > 0): ?>
                            <span class="divider">•</span>
                            <span class="summary-item" style="color:#FCA5A5;"><i class="fas fa-lock"></i> <?= $patient_locked_items ?></span>
                        <?php endif; ?>
                        <span class="divider">•</span>
                        <span class="summary-item" style="color:#6EE7B7;"><?= $currency ?> <?= number_format($patient_paid_amount, 0) ?></span>
                        <span class="divider">•</span>
                        <span class="summary-item" style="color:<?= $patient_total_balance > 0 ? '#FCD34D' : '#6EE7B7' ?>;">
                            BAL: <?= $currency ?> <?= number_format($patient_total_balance, 0) ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="card-body <?= $is_selected_patient ? '' : 'collapsed' ?>">
                <div class="master-table-wrap">
                    <table class="master-table">
                        <thead>
                            <tr>
                                <th style="width:50px; text-align:center;">
                                    <input type="checkbox" class="item-checkbox select-all-items" 
                                           data-patient-id="<?= $patient['patient_id'] ?>" 
                                           onchange="selectAllItems(this, <?= $patient['patient_id'] ?>); updateSelectedTotal();"
                                           title="Select all items">
                                </th>
                                <th style="min-width:180px;">Item Name</th>
                                <th style="min-width:100px;">Type</th>
                                <th style="text-align:center; min-width:60px;">Qty</th>
                                <th style="text-align:right; min-width:110px;">Total</th>
                                <th style="text-align:center; min-width:110px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="header-info-row">
                                <td colspan="6">
                                    <div class="header-info-content">
                                        <div class="info-item">
                                            <span class="label"><i class="fas fa-calculator"></i> Subtotal</span>
                                            <span class="value subtotal-value"><?= $currency ?> <?= number_format($patient_total_subtotal, 0) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="label"><i class="fas fa-check-circle"></i> Paid</span>
                                            <span class="value paid-value"><?= $currency ?> <?= number_format($patient_paid_amount, 0) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="label"><i class="fas fa-balance-scale"></i> Balance</span>
                                            <span class="value balance-value <?= $patient_total_balance <= 0 ? 'zero-balance' : '' ?>">
                                                <?= $currency ?> <?= number_format($patient_total_balance, 0) ?>
                                            </span>
                                        </div>
                                        <?php if ($patient_pharmacy_discount > 0): ?>
                                        <div class="info-item">
                                            <span class="label"><i class="fas fa-tag"></i> Pharm Disc</span>
                                            <span class="value pharm-discount-value"><?= $currency ?> <?= number_format($patient_pharmacy_discount, 0) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($patient_cashier_discount > 0): ?>
                                        <div class="info-item">
                                            <span class="label"><i class="fas fa-tag"></i> Cashier Disc</span>
                                            <span class="value cashier-discount-value"><?= $currency ?> <?= number_format($patient_cashier_discount, 0) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            
                            <?php foreach ($patient_bills as $bill): 
                                $items = isset($bill['items']) && is_array($bill['items']) ? $bill['items'] : [];
                                $bill_is_locked = $bill['is_locked'] ?? false;
                                $bill_pending_pres = $bill['pending_prescriptions'] ?? 0;
                                $bill_number = $bill['bill_number'] ?? 'N/A';
                                $bill_status = $bill['status'] ?? 'pending';
                                $bill_pharmacy_premium = (float)($bill['pharmacy_premium'] ?? 0);
                                $bill_cashier_premium = (float)($bill['cashier_premium'] ?? 0);
                                $bill_pharmacy_discount = (float)($bill['pharmacy_discount'] ?? 0);
                                $bill_cashier_discount = (float)($bill['cashier_discount'] ?? 0);
                                $bill_balance = (float)($bill['balance'] ?? 0);
                                $bill_paid = (float)($bill['paid_amount'] ?? 0);
                                $bill_subtotal = (float)($bill['subtotal'] ?? 0);
                                $bill_total_amount = (float)($bill['total_amount'] ?? 0);
                                
                                $visit_status = $bill['visit_status'] ?? 'waiting';
                                $visit_is_completed = (int)($bill['visit_is_completed'] ?? 0);
                            ?>
                                <tr class="bill-header-row" 
                                    data-bill-id="<?= $bill['id'] ?>"
                                    data-bill-subtotal="<?= $bill_subtotal ?>"
                                    data-bill-paid="<?= $bill_paid ?>"
                                    data-bill-pharm-disc="<?= $bill_pharmacy_discount ?>"
                                    data-bill-cashier-disc="<?= $bill_cashier_discount ?>"
                                    data-bill-pharm-prem="<?= $bill_pharmacy_premium ?>"
                                    data-bill-cashier-prem="<?= $bill_cashier_premium ?>"
                                    <?= $bill_is_locked ? 'style="background:var(--locked-bg);"' : '' ?>>
                                    <td colspan="6">
                                        <div class="bill-header-info">
                                            <span class="bill-number"><i class="fas fa-file-invoice"></i> <?= htmlspecialchars($bill_number) ?></span>
                                            <span class="bill-status <?= $bill_status ?>"><?= ucfirst($bill_status) ?></span>
                                            
                                            <?php if ($visit_is_completed): ?>
                                                <span class="visit-status-badge completed"><i class="fas fa-check-circle"></i> Complete</span>
                                            <?php elseif ($visit_status === 'waiting'): ?>
                                                <span class="visit-status-badge waiting"><i class="fas fa-clock"></i> Waiting</span>
                                            <?php elseif ($visit_status === 'lab_test'): ?>
                                                <span class="visit-status-badge lab_test"><i class="fas fa-flask"></i> Lab Test</span>
                                            <?php elseif ($visit_status === 'assigned'): ?>
                                                <span class="visit-status-badge assigned"><i class="fas fa-user-md"></i> Assigned</span>
                                            <?php elseif ($visit_status === 'prescribed'): ?>
                                                <span class="visit-status-badge prescribed"><i class="fas fa-prescription"></i> Prescribed</span>
                                            <?php else: ?>
                                                <span class="visit-status-badge waiting"><i class="fas fa-info-circle"></i> <?= ucfirst($visit_status) ?></span>
                                            <?php endif; ?>
                                            
                                            <?php if ($bill_is_locked): ?>
                                                <span class="locked-badge"><i class="fas fa-lock"></i> <?= $bill_pending_pres ?> Pending</span>
                                            <?php endif; ?>
                                            <span style="color:var(--text-secondary);">
                                                Sub: <strong style="color:var(--primary);"><?= $currency ?> <?= number_format($bill_subtotal, 0) ?></strong>
                                            </span>
                                            <span style="color:var(--text-secondary);">
                                                Total: <strong style="color:var(--danger);"><?= $currency ?> <?= number_format($bill_total_amount, 0) ?></strong>
                                            </span>
                                            <span style="color:var(--text-secondary);">
                                                Paid: <strong style="color:var(--success);"><?= $currency ?> <?= number_format($bill_paid, 0) ?></strong>
                                            </span>
                                            <span style="color:var(--text-secondary);">
                                                Bal: <strong style="color:<?= $bill_balance > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                                                    <?= $currency ?> <?= number_format($bill_balance, 0) ?>
                                                </strong>
                                            </span>
                                            <?php if ($bill_pharmacy_discount > 0): ?>
                                                <span class="premium-badge"><i class="fas fa-tag"></i> Pharm Disc: <?= $currency ?> <?= number_format($bill_pharmacy_discount, 0) ?></span>
                                            <?php endif; ?>
                                            <?php if ($bill_cashier_discount > 0): ?>
                                                <span class="premium-badge cashier"><i class="fas fa-tag"></i> Cashier Disc: <?= $currency ?> <?= number_format($bill_cashier_discount, 0) ?></span>
                                            <?php endif; ?>
                                            <?php if ($bill_pharmacy_premium > 0): ?>
                                                <span class="premium-badge"><i class="fas fa-crown"></i> Pharm Prem: <?= $currency ?> <?= number_format($bill_pharmacy_premium, 0) ?></span>
                                            <?php endif; ?>
                                            <?php if ($bill_cashier_premium > 0): ?>
                                                <span class="premium-badge cashier"><i class="fas fa-crown"></i> Cashier Prem: <?= $currency ?> <?= number_format($bill_cashier_premium, 0) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                
                                <?php foreach ($items as $item): 
                                    $is_paid = ($item['status'] === 'paid');
                                    $is_cancelled = ($item['status'] === 'cancelled');
                                    $is_medication = ($item['item_type'] === 'medication');
                                    $is_equipment = ($item['item_type'] === 'equipment');
                                    $pres_status = $item['prescription_status'] ?? 'pending';
                                    $is_item_locked = ($is_medication && $item['reference_type'] === 'prescription' && $pres_status !== 'confirmed' && $pres_status !== 'dispensed');
                                    $can_select = !$is_paid && !$is_cancelled && !$is_item_locked;
                                    $price = (float)($item['total_price'] ?? $item['unit_price'] ?? 0);
                                    $qty = (int)($item['quantity'] ?? 1);
                                    $item_status = $item['status'] ?? 'pending';
                                ?>
                                    <tr class="item-row <?= $is_paid ? 'bill-paid' : '' ?> <?= $is_item_locked ? 'item-locked' : '' ?>" 
                                        data-item-id="<?= $item['id'] ?>" 
                                        data-price="<?= $price ?>"
                                        data-bill-id="<?= $bill['id'] ?>"
                                        data-patient-id="<?= $patient['patient_id'] ?>"
                                        data-item-type="<?= htmlspecialchars($item['item_type'] ?? '') ?>"
                                        data-is-locked="<?= $is_item_locked ? 'true' : 'false' ?>">
                                        <td style="text-align:center;">
                                            <?php if ($is_item_locked): ?>
                                                <i class="fas fa-lock" style="color:var(--locked-color); font-size:1.05rem;"></i>
                                            <?php elseif ($can_select): ?>
                                                <input type="checkbox" class="item-checkbox item-select" 
                                                       data-id="<?= $item['id'] ?>" 
                                                       data-price="<?= $price ?>"
                                                       data-bill-id="<?= $bill['id'] ?>"
                                                       data-patient-id="<?= $patient['patient_id'] ?>"
                                                       data-is-locked="<?= $is_item_locked ? 'true' : 'false' ?>"
                                                       data-item-type="<?= htmlspecialchars($item['item_type'] ?? '') ?>"
                                                       onchange="updateSelectedTotal()">
                                            <?php elseif ($is_paid): ?>
                                                <i class="fas fa-check-circle" style="color:var(--success); font-size:1.05rem;"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:8px; font-weight:700;">
                                                <?php if ($is_medication): ?>
                                                    <i class="fas fa-pills" style="color:var(--success);"></i>
                                                <?php elseif ($is_equipment): ?>
                                                    <i class="fas fa-tools" style="color:var(--warning);"></i>
                                                <?php elseif ($item['item_type'] === 'lab_test'): ?>
                                                    <i class="fas fa-flask" style="color:#0EA5E9;"></i>
                                                <?php elseif ($item['item_type'] === 'procedure'): ?>
                                                    <i class="fas fa-syringe" style="color:var(--purple);"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-cube"></i>
                                                <?php endif; ?>
                                                <span><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-size:0.58rem; font-weight:700; background:var(--bg-body); padding:3px 10px; border-radius:8px; text-transform:uppercase;">
                                                <?= ucfirst($item['item_type'] ?? 'item') ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center; font-weight:800; font-size:0.85rem;"><?= $qty ?></td>
                                        <td style="text-align:right; font-weight:800; <?= $is_paid ? 'color:var(--success);' : 'color:var(--danger);' ?> font-size:0.85rem;">
                                            <?= $currency ?> <?= number_format($price, 0) ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <?php if ($is_item_locked): ?>
                                                <span class="locked-badge"><i class="fas fa-lock"></i> Locked</span>
                                            <?php elseif ($is_paid): ?>
                                                <span class="bill-status paid"><i class="fas fa-check"></i> Paid</span>
                                            <?php elseif ($item_status === 'partial'): ?>
                                                <span class="bill-status partial"><i class="fas fa-hourglass-half"></i> Partial</span>
                                            <?php else: ?>
                                                <span class="bill-status pending"><i class="fas fa-clock"></i> Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <h3>No Pending Bills</h3>
            <p>All bills have been paid. Great job! 🎉</p>
        </div>
    <?php endif; ?>

</main>

<!-- COMPACT PAYMENT CONTROLS -->
<div class="payment-controls-wrapper">
    <div class="payment-controls" id="paymentControls">
        <div class="control-group">
            <label><i class="fas fa-hand-holding-usd"></i> Method:</label>
            <select id="paymentMethod">
                <option value="cash">💰 Cash</option>
                <option value="m-pesa">📱 M-Pesa</option>
                <option value="airtel_money">📱 Airtel</option>
                <option value="tigo_pesa">📱 Tigo</option>
                <option value="halopesa">📱 Halo</option>
                <option value="card">💳 Card</option>
                <option value="bank">🏦 Bank</option>
                <option value="insurance">🏥 Insurance</option>
            </select>
        </div>
        
        <div class="divider"></div>
        
        <div class="control-group">
            <label><i class="fas fa-percent"></i> Disc:</label>
            <div class="amount-input-wrap">
                <span class="currency-prefix"><?= $currency ?></span>
                <input type="text" id="discountAmount" class="discount-input" placeholder="0" 
                       value="0" oninput="formatAmount(this); updateSelectedTotal();">
            </div>
        </div>
        
        <div class="divider"></div>
        
        <div class="control-group">
            <label><i class="fas fa-crown"></i> Prem:</label>
            <div class="amount-input-wrap">
                <span class="currency-prefix"><?= $currency ?></span>
                <input type="text" id="premiumAmount" class="premium-input" placeholder="0" 
                       value="0" oninput="formatAmount(this); updateSelectedTotal();">
            </div>
        </div>
        
        <div class="divider"></div>
        
        <div class="control-group">
            <label><i class="fas fa-hand-holding-heart"></i> Partial:</label>
            <div class="amount-input-wrap">
                <span class="currency-prefix"><?= $currency ?></span>
                <input type="text" id="partialAmount" class="partial-input" placeholder="0" 
                       value="0" oninput="formatAmount(this); this.dataset.userEdited='true'; updateSelectedTotal();">
            </div>
        </div>
        
        <div class="divider"></div>
        
        <div class="total-display" id="totalDisplay">
            <div class="total-item">
                <span class="label">Subtotal</span>
                <span class="value" id="displayTotal"><?= $currency ?> 0</span>
            </div>
            <div style="color:var(--border-color);font-size:1rem;">|</div>
            <div class="total-item">
                <span class="label">Pharm Disc</span>
                <span class="value" style="color:#D97706;" id="displayPharmDiscount"><?= $currency ?> 0</span>
            </div>
            <div class="total-item">
                <span class="label">Cashier Disc</span>
                <span class="value" style="color:var(--warning);" id="displayDiscount"><?= $currency ?> 0</span>
            </div>
            <div style="color:var(--border-color);font-size:1rem;">|</div>
            <div class="total-item premium-card" id="pharmPremiumCard" style="display:none;">
                <span class="label"><i class="fas fa-crown"></i> Pharm</span>
                <span class="value premium" id="displayPharmPremium"><?= $currency ?> 0</span>
            </div>
            <div class="total-item premium-card" id="premiumCard" style="display:none;">
                <span class="label"><i class="fas fa-crown"></i> Cashier</span>
                <span class="value premium" id="displayPremium"><?= $currency ?> 0</span>
            </div>
            <div class="total-item">
                <span class="label">Total</span>
                <span class="value grand" id="displayGrandTotal"><?= $currency ?> 0</span>
            </div>
        </div>
        
        <div style="flex:1; display:flex; gap:6px; justify-content:flex-end; flex-wrap:wrap; min-width:0;">
            <span class="selected-counter">
                Selected: <strong id="selectedCountNum">0</strong>
            </span>
            <button onclick="selectAllItemsAllPatients()" class="btn btn-outline" title="Select all">
                <i class="fas fa-check-double"></i> All
            </button>
            <button onclick="deselectAllItems()" class="btn btn-outline" title="Deselect all">
                <i class="fas fa-times"></i> None
            </button>
            <button onclick="processPayment('partial')" class="btn btn-warning" id="partialPayBtn">
                <i class="fas fa-hand-holding-heart"></i> PARTIAL
            </button>
            <button onclick="processPayment('full')" class="btn btn-success" id="fullPayBtn">
                <i class="fas fa-check-circle"></i> FULL
            </button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.3rem;"></i>
    <div>
        <p style="font-weight:800;font-size:0.88rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.78rem;opacity:0.95;margin:0;margin-top:2px;" id="toastMessage"></p>
    </div>
</div>

<script>
    var currency = '<?= $currency ?>';
    var totalLockedBills = <?= $total_locked_bills ?>;

    console.log('%c💰 Process Payment V27.0 FINAL', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c✅ NO AUTO-REPAIR - Deduction TU wakati wa payment');
    console.log('%c✅ Safety check kabla ya kila deduction');
    console.log('%c✅ Inatumia bill_items.id kwa reference_id (SAHIHI)');
    console.log('%c✅ Lab equipment deduction kwa visit_id');

    (function() {
        var htmlElement = document.documentElement;
        function syncDarkMode() {
            var isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) htmlElement.setAttribute('data-theme', 'dark');
            else htmlElement.removeAttribute('data-theme');
        }
        syncDarkMode();
    })();

    function formatAmount(input) {
        var val = input.value.replace(/[^0-9.]/g, '');
        var parts = val.split('.');
        var whole = parts[0];
        var decimal = parts.length > 1 ? '.' + parts[1].slice(0, 2) : '';
        if (whole.length > 0) whole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        input.value = whole + decimal;
        input.dataset.rawValue = parseFloat(val) || 0;
    }
    
    function getRawValue(input) {
        var raw = input.dataset.rawValue;
        if (raw !== undefined && raw !== '') return parseFloat(raw) || 0;
        return parseFloat(input.value.replace(/,/g, '')) || 0;
    }

    function togglePatientCard(header) {
        var card = header.closest('.patient-card');
        var body = card.querySelector('.card-body');
        if (body.classList.contains('collapsed')) body.classList.remove('collapsed');
        else body.classList.add('collapsed');
    }

    function selectAllItems(checkbox, patientId) {
        var checkboxes = document.querySelectorAll('.item-select[data-patient-id="' + patientId + '"]:not([data-is-locked="true"])');
        checkboxes.forEach(function(cb) {
            if (!cb.disabled) cb.checked = checkbox.checked;
        });
        updateSelectedTotal();
    }

    function selectAllItemsAllPatients() {
        var checkboxes = document.querySelectorAll('.item-select:not([data-is-locked="true"]):not(:disabled)');
        checkboxes.forEach(function(cb) { cb.checked = true; });
        document.querySelectorAll('.select-all-items').forEach(function(cb) { cb.checked = true; });
        updateSelectedTotal();
    }

    function deselectAllItems() {
        document.querySelectorAll('.item-select').forEach(function(cb) { cb.checked = false; });
        document.querySelectorAll('.select-all-items').forEach(function(cb) { cb.checked = false; });
        var partialInput = document.getElementById('partialAmount');
        partialInput.dataset.userEdited = 'false';
        partialInput.value = '0';
        partialInput.dataset.rawValue = 0;
        updateSelectedTotal();
    }

    function getBillProRatedValues(billId) {
        var billHeader = document.querySelector('.bill-header-row[data-bill-id="' + billId + '"]');
        if (!billHeader) return { pharmacyDiscount: 0, cashierDiscount: 0, pharmacyPremium: 0, cashierPremium: 0, paid: 0 };
        
        var allItems = document.querySelectorAll('.item-select[data-bill-id="' + billId + '"]');
        var totalItemsPrice = 0;
        allItems.forEach(function(cb) { totalItemsPrice += parseFloat(cb.dataset.price) || 0; });
        
        var selectedPrice = 0;
        allItems.forEach(function(cb) { if (cb.checked) selectedPrice += parseFloat(cb.dataset.price) || 0; });
        
        var ratio = (totalItemsPrice > 0) ? (selectedPrice / totalItemsPrice) : 0;
        
        return {
            pharmacyDiscount: (parseFloat(billHeader.dataset.billPharmDisc) || 0) * ratio,
            cashierDiscount: (parseFloat(billHeader.dataset.billCashierDisc) || 0) * ratio,
            pharmacyPremium: (parseFloat(billHeader.dataset.billPharmPrem) || 0) * ratio,
            cashierPremium: (parseFloat(billHeader.dataset.billCashierPrem) || 0) * ratio,
            paid: (parseFloat(billHeader.dataset.billPaid) || 0) * ratio
        };
    }

    function updateSelectedTotal() {
        var checkboxes = document.querySelectorAll('.item-select:checked');
        var count = checkboxes.length;
        var hasLockedItems = false;
        
        checkboxes.forEach(function(cb) {
            if (cb.getAttribute('data-is-locked') === 'true') hasLockedItems = true;
        });
        
        var partialInput = document.getElementById('partialAmount');
        var cashier_discount_input = getRawValue(document.getElementById('discountAmount'));
        var partial = getRawValue(partialInput);
        var cashier_premium_input = getRawValue(document.getElementById('premiumAmount'));
        
        var selectedSubtotal = 0;
        checkboxes.forEach(function(cb) { selectedSubtotal += parseFloat(cb.dataset.price) || 0; });
        
        var billIds = new Set();
        checkboxes.forEach(function(cb) { if (cb.dataset.billId) billIds.add(cb.dataset.billId); });
        
        var totalPaid = 0, pharmacyDiscount = 0, cashierDiscount = 0, pharmacyPremium = 0, cashierPremium = 0;
        
        billIds.forEach(function(billId) {
            var values = getBillProRatedValues(billId);
            totalPaid += values.paid;
            pharmacyDiscount += values.pharmacyDiscount;
            cashierDiscount += values.cashierDiscount;
            pharmacyPremium += values.pharmacyPremium;
            cashierPremium += values.cashierPremium;
        });
        
        var totalDiscount = pharmacyDiscount + cashierDiscount + cashier_discount_input;
        var totalPremium = pharmacyPremium + cashierPremium + cashier_premium_input;
        
        var grand_total = selectedSubtotal + totalPremium - totalPaid - totalDiscount;
        if (grand_total < 0) grand_total = 0;
        
        document.getElementById('selectedCountNum').textContent = count;
        document.getElementById('displayTotal').textContent = currency + ' ' + selectedSubtotal.toFixed(0);
        document.getElementById('displayPharmDiscount').textContent = currency + ' ' + pharmacyDiscount.toFixed(0);
        document.getElementById('displayDiscount').textContent = currency + ' ' + (cashierDiscount + cashier_discount_input).toFixed(0);
        document.getElementById('displayPharmPremium').textContent = currency + ' ' + pharmacyPremium.toFixed(0);
        document.getElementById('displayPremium').textContent = currency + ' ' + (cashierPremium + cashier_premium_input).toFixed(0);
        document.getElementById('displayGrandTotal').textContent = currency + ' ' + grand_total.toFixed(0);
        
        var totalSelectableItems = document.querySelectorAll('.item-select:not([data-is-locked="true"]):not(:disabled)').length;
        var allItemsSelected = (count > 0 && count === totalSelectableItems);
        
        if (partialInput.dataset.userEdited !== 'true') {
            if (count === 0 || allItemsSelected) {
                partialInput.value = '0';
                partialInput.dataset.rawValue = 0;
                partial = 0;
            } else {
                partialInput.value = grand_total.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                partialInput.dataset.rawValue = grand_total;
                partial = grand_total;
            }
        }
        
        document.getElementById('pharmPremiumCard').style.display = pharmacyPremium > 0 ? 'flex' : 'none';
        document.getElementById('premiumCard').style.display = (cashierPremium + cashier_premium_input) > 0 ? 'flex' : 'none';
        
        var fullBtn = document.getElementById('fullPayBtn');
        var partialBtn = document.getElementById('partialPayBtn');
        
        if (hasLockedItems) {
            fullBtn.disabled = true;
            fullBtn.innerHTML = '<i class="fas fa-lock"></i> LOCKED';
            partialBtn.disabled = true;
            partialBtn.innerHTML = '<i class="fas fa-lock"></i> LOCKED';
            return;
        }
        
        var hasPartialAmount = partial > 0 && partial < grand_total;
        
        if (count === 0) {
            fullBtn.disabled = true;
            fullBtn.innerHTML = '<i class="fas fa-check-circle"></i> FULL';
            partialBtn.disabled = true;
            partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> PARTIAL';
            return;
        }
        
        if (allItemsSelected) {
            if (hasPartialAmount) {
                fullBtn.disabled = true;
                fullBtn.innerHTML = '<i class="fas fa-check-circle"></i> FULL';
                partialBtn.disabled = false;
                partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> PAY PARTIAL ' + currency + ' ' + partial.toFixed(0);
            } else {
                fullBtn.disabled = false;
                fullBtn.innerHTML = '<i class="fas fa-check-circle"></i> FULL ' + currency + ' ' + grand_total.toFixed(0);
                partialBtn.disabled = true;
                partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> PARTIAL';
            }
            return;
        }
        
        fullBtn.disabled = true;
        fullBtn.innerHTML = '<i class="fas fa-check-circle"></i> FULL';
        
        if (hasPartialAmount) {
            partialBtn.disabled = false;
            partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> PAY PARTIAL ' + currency + ' ' + partial.toFixed(0);
        } else {
            partialBtn.disabled = true;
            partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> PARTIAL';
        }
    }

    function processPayment(type) {
        var checkboxes = document.querySelectorAll('.item-select:checked');
        var itemIds = [];
        var medication_count = 0;
        var equipment_count = 0;
        
        checkboxes.forEach(function(cb) {
            itemIds.push(parseInt(cb.dataset.id));
            if (cb.dataset.itemType === 'medication') medication_count++;
            if (cb.dataset.itemType === 'equipment') equipment_count++;
        });
        
        if (itemIds.length === 0) {
            showToast('⚠️ No Selection', 'Please select at least one item', 'warning');
            return;
        }
        
        var paymentMethod = document.getElementById('paymentMethod').value;
        var cashier_discount_input = getRawValue(document.getElementById('discountAmount'));
        var partialAmount = getRawValue(document.getElementById('partialAmount'));
        var cashier_premium_input = getRawValue(document.getElementById('premiumAmount'));
        
        var selectedSubtotal = 0;
        checkboxes.forEach(function(cb) { selectedSubtotal += parseFloat(cb.dataset.price) || 0; });
        
        var billIds = new Set();
        checkboxes.forEach(function(cb) { if (cb.dataset.billId) billIds.add(cb.dataset.billId); });
        
        var totalPaid = 0, pharmacyDiscount = 0, cashierDiscount = 0, pharmacyPremium = 0, cashierPremium = 0;
        
        billIds.forEach(function(billId) {
            var values = getBillProRatedValues(billId);
            totalPaid += values.paid;
            pharmacyDiscount += values.pharmacyDiscount;
            cashierDiscount += values.cashierDiscount;
            pharmacyPremium += values.pharmacyPremium;
            cashierPremium += values.cashierPremium;
        });
        
        var totalDiscount = pharmacyDiscount + cashierDiscount + cashier_discount_input;
        var totalPremium = pharmacyPremium + cashierPremium + cashier_premium_input;
        
        var grandTotal = selectedSubtotal + totalPremium - totalPaid - totalDiscount;
        if (grandTotal < 0) grandTotal = 0;
        
        if (type === 'full') {
            var totalSelectableItems = document.querySelectorAll('.item-select:not([data-is-locked="true"]):not(:disabled)').length;
            
            if (itemIds.length !== totalSelectableItems) {
                showToast('⚠️ FULL Payment', 
                    'FULL payment inahitaji items ZOTE kuchaguliwa.<br><br>Umechagua <strong>' + itemIds.length + '</strong> kati ya <strong>' + totalSelectableItems + '</strong> items.', 
                    'warning');
                return;
            }
            
            if (partialAmount > 0 && partialAmount < grandTotal) {
                showToast('⚠️ Partial Amount Filled', 
                    'Umejaza PARTIAL amount (<strong>' + currency + ' ' + partialAmount.toFixed(0) + '</strong>).<br><br>Kama unataka kulipia FULL, futa partial kwanza.', 
                    'warning');
                return;
            }
        }
        
        if (type === 'partial') {
            if (partialAmount <= 0) {
                showToast('⚠️ Invalid Amount', 'Please enter a valid partial amount.', 'warning');
                return;
            }
            if (partialAmount > grandTotal) {
                showToast('⚠️ Amount Exceeds', 'Partial amount inazidi grand total.', 'warning');
                return;
            }
            if (partialAmount >= grandTotal) {
                showToast('⚠️ Full Amount', 'Partial amount ni sawa au zaidi ya grand total.', 'warning');
                return;
            }
        }
        
        var stock_info = '';
        if (type === 'full') {
            if (medication_count > 0) stock_info += '<br>💊 ' + medication_count + ' medication - stock itadeduct';
            if (equipment_count > 0) stock_info += '<br>🛠️ ' + equipment_count + ' equipment - stock itadeduct';
            stock_info += '<br>🧪 Lab equipment itadeduct (kama ipo)';
            stock_info += '<br>✅ SAFETY CHECK - Double deduction imezuiwa';
        } else {
            stock_info = '<br>⚠️ PARTIAL payment - stock HAIJADEDUCT';
        }
        
        var confirmMsg = (type === 'partial' ? '💳 PARTIAL' : '💰 FULL') + ' PAYMENT\n' +
                         '═══════════════════════════════\n' +
                         'Items: ' + itemIds.length + '\n' +
                         'Subtotal: ' + currency + ' ' + selectedSubtotal.toFixed(0) + '\n' +
                         'TOTAL: ' + currency + ' ' + grandTotal.toFixed(0) + '\n' +
                         (type === 'partial' ? 'Paying: ' + currency + ' ' + partialAmount.toFixed(0) + '\n' : '') +
                         stock_info.replace(/<br>/g, '\n') +
                         '\n\nConfirm?';
        
        if (!confirm(confirmMsg)) return;
        
        var btn = type === 'partial' ? document.getElementById('partialPayBtn') : document.getElementById('fullPayBtn');
        var originalHtml = btn.innerHTML;
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;
        
        var formData = new FormData();
        formData.append('action', type === 'partial' ? 'partial_payment' : 'complete_payment');
        formData.append('payment_method', paymentMethod);
        if (cashier_discount_input > 0) formData.append('discount_amount', cashier_discount_input);
        if (cashier_premium_input > 0) formData.append('premium_amount', cashier_premium_input);
        if (type === 'partial') formData.append('partial_amount', partialAmount);
        itemIds.forEach(function(id) { formData.append('item_ids[]', id); });
        
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('✅ Success', data.message, 'success');
                setTimeout(function() { window.location.reload(); }, 5000);
            } else {
                showToast('❌ Error', data.message, 'error');
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        })
        .catch(function(error) {
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        });
    }

    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + (type || 'info');
        toastTitle.textContent = title || 'Notification';
        toastMessage.innerHTML = message || '';
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 12000);
    }

    document.addEventListener('DOMContentLoaded', function() {
        var partialInput = document.getElementById('partialAmount');
        if (partialInput) partialInput.dataset.userInput = 'false';
        updateSelectedTotal();
        
        console.log('✅ Process Payment V27.0 loaded');
        console.log('✅ NO AUTO-REPAIR');
        console.log('✅ Safety check kabla ya deduction');
    });
</script>

</body>
</html>