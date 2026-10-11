<?php
// ================================================================
// FILE: frontend/pages/cashier/make_payment.php
// CASHIER - MAKE PAYMENT - V19.0 FINAL
// ================================================================
// ✅ V19.0: Inatumia LOGIC sawa na process_payment.php
// ✅ V19.0: HAINA global isStockAlreadyDeducted() check
// ✅ V19.0: Kila item ina safety check yake
// ✅ V19.0: Lab-linked equipment query HAINA lte.branch_id = lt.branch_id
// ✅ V19.0: Inatumia bill_items.id kwa reference_id (SAHIHI)
// ✅ V19.0: Inatumia visit_id kwa lab equipment (kama process_payment)
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
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_email = $_SESSION['email'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';

$is_admin = ($user_role === 'admin');
$is_reception = ($user_role === 'reception');

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;

if ($bill_id <= 0) {
    header('Location: partial_payments.php?error=invalid_bill');
    exit;
}

$message = '';
$message_type = '';
$currency = 'TSh';

// ================================================================
// ✅ V19.0: SAFETY CHECK - Check if item already deducted
// ================================================================
function isStockAlreadyDeductedForItem($db, $item_type, $reference_type, $reference_id, $stock_id, $branch_id) {
    try {
        if ($item_type === 'medication') {
            $stmt = $db->prepare("
                SELECT COUNT(*) as cnt FROM stock_movements 
                WHERE inventory_id = ? 
                  AND reference_type = ? 
                  AND reference_id = ?
                  AND movement_type = 'out'
                  AND branch_id = ?
            ");
            $stmt->execute([$stock_id, $reference_type, $reference_id, $branch_id]);
        } else {
            $stmt = $db->prepare("
                SELECT COUNT(*) as cnt FROM stock_movements 
                WHERE equipment_id = ? 
                  AND reference_type = ? 
                  AND reference_id = ?
                  AND movement_type = 'out'
                  AND branch_id = ?
            ");
            $stmt->execute([$stock_id, $reference_type, $reference_id, $branch_id]);
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ((int)($result['cnt'] ?? 0)) > 0;
    } catch (Exception $e) {
        error_log("isStockAlreadyDeductedForItem error: " . $e->getMessage());
        return false;
    }
}

// ================================================================
// ✅ V19.0: DEDUCT MEDICATION STOCK (FIFO)
// ================================================================
function deductMedicationStock($db, $medication_name, $quantity, $branch_id, $user_id, $patient_id, $reference_type = 'prescription', $reference_id = 0, $prescription_number = '') {
    $result = ['deducted' => 0, 'remaining' => $quantity, 'details' => [], 'errors' => [], 'skipped' => false];
    
    try {
        $needed = (int)$quantity;
        if ($needed <= 0) return $result;
        
        // SAFETY CHECK
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
                error_log("⏭️ V19.0: Medication deduction SKIPPED - Rx #$reference_id already deducted");
                $result['skipped'] = true;
                $result['errors'][] = "Rx #$reference_id already deducted";
                return $result;
            }
        }
        
        $clean_name = preg_replace('/\s*\(Batch:.*?\)\s*/i', '', $medication_name);
        $clean_name = trim($clean_name);
        
        $stmt = $db->prepare("
            SELECT id, quantity, batch_number, expiry_date
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
                $notes = "V19.0 - Dispensed at full payment";
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
                error_log("V19.0 stock_movements insert error (med): " . $e->getMessage());
            }
            
            $needed -= $deduct;
        }
        
        if ($needed > 0) {
            $result['errors'][] = "Insufficient stock for $clean_name (needed {$needed} more)";
        }
    } catch (Exception $e) {
        $result['errors'][] = "System error: " . $e->getMessage();
        error_log("V19.0 deductMedicationStock error: " . $e->getMessage());
    }
    
    return $result;
}

// ================================================================
// ✅ V19.0: DEDUCT EQUIPMENT STOCK
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
        
        // SAFETY CHECK - Check if already deducted
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
            error_log("⏭️ V19.0: Equipment #$eq_id SKIPPED - reference $reference_type #$reference_id already deducted");
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
            $log_notes = !empty($notes) ? $notes : "V19.0 - Equipment used";
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
            
            error_log("✅ V19.0: Equipment {$equip['equipment_name']} deducted $qty ($previous_stock → $new_stock) for $reference_type #$reference_id");
        } catch (Exception $e) {
            error_log("V19.0 stock_movements insert error (equip): " . $e->getMessage());
        }
    } catch (Exception $e) {
        $result['errors'][] = "System error: " . $e->getMessage();
        error_log("V19.0 deductEquipmentStock error: " . $e->getMessage());
    }
    
    return $result;
}

// ================================================================
// ✅ V19.0: DEDUCT ALL STOCK FOR PAID BILL (KAMA PROCESS_PAYMENT)
// ✅ HAINA global isStockAlreadyDeducted() check
// ✅ Kila item ina safety check yake
// ✅ Lab equipment query HAINA lte.branch_id = lt.branch_id
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
        error_log("🔍 V19.0: deductAllStockForPaidBill START - Bill #$bill_id");
        error_log("═══════════════════════════════════════════════════════");
        
        // SAFETY CHECK 1: Verify bill is FULLY PAID
        $stmt_check = $db->prepare("SELECT status, balance, visit_id FROM bills WHERE id = ? AND branch_id = ? FOR UPDATE");
        $stmt_check->execute([$bill_id, $branch_id]);
        $bill_check = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$bill_check) {
            $result['errors'][] = "Bill #$bill_id not found";
            return $result;
        }
        
        if ($bill_check['status'] !== 'paid' || (float)$bill_check['balance'] > 0.01) {
            error_log("⏭️ V19.0: Bill #$bill_id NOT fully paid - SKIPPING");
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
        
        error_log("💊 V19.0: Found " . count($prescriptions) . " prescriptions");
        
        foreach ($prescriptions as $pres) {
            $prescription_id = $pres['prescription_id'];
            $patient_id = $pres['patient_id'];
            $prescription_number = $pres['prescription_number'];
            
            // SAFETY CHECK 2: Check if already dispensed
            $stmt_check_disp = $db->prepare("
                SELECT COUNT(*) as dispensed_count 
                FROM prescription_items 
                WHERE prescription_id = ? AND dispensed_at IS NOT NULL AND cancelled_at IS NULL
            ");
            $stmt_check_disp->execute([$prescription_id]);
            $already_dispensed = (int)$stmt_check_disp->fetch(PDO::FETCH_ASSOC)['dispensed_count'];
            
            if ($already_dispensed > 0) {
                error_log("⏭️ V19.0: Rx #$prescription_number already dispensed - SKIPPING");
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
        
        error_log("🛠️ V19.0: Found " . count($equipment_items) . " direct equipment items");
        
        foreach ($equipment_items as $eq) {
            $equipment_id = (int)$eq['equipment_id'];
            $qty_needed = (int)$eq['quantity'];
            $bill_item_id = (int)$eq['bill_item_id'];
            
            if ($equipment_id <= 0 || $qty_needed <= 0) continue;
            
            $stmt_patient = $db->prepare("SELECT patient_id FROM bills WHERE id = ?");
            $stmt_patient->execute([$bill_id]);
            $patient_id = (int)$stmt_patient->fetchColumn();
            
            $deduct_result = deductEquipmentStock(
                $db, $equipment_id, $qty_needed, $branch_id, $user_id, $patient_id,
                'equipment', $bill_item_id,
                "V19.0 - Equipment - Bill #{$bill_id} - {$eq['equipment_name']}"
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
        // 3. LAB-LINKED EQUIPMENT (V19.0 FIX)
        // ✅ HAINA lte.branch_id = lt.branch_id
        // ✅ Inatumia visit_id (kama process_payment.php)
        // ============================================================
        if ($bill_visit_id > 0) {
            error_log("🧪 V19.0: Querying lab-linked equipment for bill #$bill_id (visit_id: $bill_visit_id)");
            
            $stmt = $db->prepare("
                SELECT 
                    lt.id as lab_test_id,
                    lt.test_id,
                    lt.test_name,
                    lt.patient_id,
                    lt.branch_id,
                    lt.visit_id,
                    lt.doctor_id,
                    lte.equipment_id,
                    me.equipment_name,
                    me.quantity as current_stock
                FROM lab_tests lt
                INNER JOIN lab_test_equipment lte 
                    ON lte.lab_test_id = lt.test_id
                    -- ✅ V19.0: HAKUNA lte.branch_id = lt.branch_id!
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
            
            error_log("🧪 V19.0: Found " . count($lab_equipment_items) . " lab-equipment links");
            
            foreach ($lab_equipment_items as $debug) {
                error_log("   → Lab #{$debug['lab_test_id']} ({$debug['test_name']}) uses Equipment #{$debug['equipment_id']} ({$debug['equipment_name']}) - Stock: {$debug['current_stock']}");
            }
            
            foreach ($lab_equipment_items as $eq) {
                $equipment_id = (int)$eq['equipment_id'];
                $lab_test_id = (int)$eq['lab_test_id'];
                $patient_id = (int)$eq['patient_id'];
                $quantity_used = 1;
                
                if ($equipment_id <= 0) continue;
                
                $deduct_result = deductEquipmentStock(
                    $db, $equipment_id, $quantity_used, $branch_id, $user_id, $patient_id,
                    'lab_test', $lab_test_id,
                    "V19.0 - Lab equipment - {$eq['test_name']} - Lab #{$lab_test_id}"
                );
                
                if ($deduct_result['deducted'] > 0) {
                    $result['lab_equipment_deducted'] += $deduct_result['deducted'];
                    $result['lab_equipment_items']++;
                    error_log("✅ V19.0: Deducted {$deduct_result['deducted']} x {$eq['equipment_name']} for Lab #{$lab_test_id}");
                }
                if ($deduct_result['skipped']) {
                    $result['skipped']++;
                }
                if (!empty($deduct_result['errors'])) {
                    $result['errors'] = array_merge($result['errors'], $deduct_result['errors']);
                }
            }
        } else {
            error_log("⚠️ V19.0: Bill #$bill_id has no visit_id - skipping lab equipment");
        }
        
        error_log("═══════════════════════════════════════════════════════");
        error_log("✅ V19.0: Bill #$bill_id done");
        error_log("   Meds: {$result['medications_deducted']} qty");
        error_log("   Equipment: {$result['equipment_deducted']} qty");
        error_log("   Lab Equipment: {$result['lab_equipment_deducted']} qty");
        error_log("   Skipped: {$result['skipped']}");
        error_log("═══════════════════════════════════════════════════════");
        
    } catch (Exception $e) {
        $result['errors'][] = "System error: " . $e->getMessage();
        error_log("V19.0 deductAllStockForPaidBill error: " . $e->getMessage());
    }
    
    return $result;
}

// ================================================================
// ✅ V19.0: HELPER FUNCTIONS
// ================================================================
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
            WHERE bi.bill_id = ?
            AND bi.item_type IN ('procedure', 'equipment')
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

function isBillLocked($db, $bill_id) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as pending_count
            FROM bill_items bi
            LEFT JOIN prescriptions pr ON bi.reference_id = pr.id AND bi.reference_type = 'prescription'
            WHERE bi.bill_id = ? 
            AND bi.item_type = 'medication' 
            AND bi.reference_type = 'prescription'
            AND bi.status != 'cancelled'
            AND (pr.status IS NULL OR pr.status NOT IN ('confirmed', 'dispensed'))
        ");
        $stmt->execute([$bill_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ((int)($result['pending_count'] ?? 0)) > 0;
    } catch (Exception $e) { return true; }
}

function smartUpdateBillItems($db, $bill_id, $amount_paid, $is_fully_paid) {
    try {
        if ($is_fully_paid) {
            $stmt = $db->prepare("UPDATE bill_items SET status = 'paid', updated_at = NOW() WHERE bill_id = ? AND status != 'cancelled'");
            $stmt->execute([$bill_id]);
            return $stmt->rowCount();
        }
        
        $stmt = $db->prepare("
            SELECT id, total_price, status, item_type FROM bill_items 
            WHERE bill_id = ? AND status != 'cancelled'
            ORDER BY CASE WHEN item_type = 'equipment' THEN 1 ELSE 0 END ASC, created_at ASC
        ");
        $stmt->execute([$bill_id]);
        $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $remaining_to_pay = $amount_paid;
        $updated = 0;
        
        foreach ($all_items as $item) {
            if ($remaining_to_pay <= 0) break;
            if ($item['status'] === 'paid') continue;
            if ($item['item_type'] === 'equipment') continue;
            
            $item_price = (float)$item['total_price'];
            if ($remaining_to_pay >= $item_price) {
                $db->prepare("UPDATE bill_items SET status = 'paid', updated_at = NOW() WHERE id = ?")->execute([$item['id']]);
                $remaining_to_pay -= $item_price;
                $updated++;
            } else {
                $db->prepare("UPDATE bill_items SET status = 'partial', updated_at = NOW() WHERE id = ?")->execute([$item['id']]);
                $remaining_to_pay = 0;
                $updated++;
                break;
            }
        }
        return $updated;
    } catch (Exception $e) { return 0; }
}

try {
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';

    if (isBillLocked($db, $bill_id)) {
        $_SESSION['flash_message'] = "🔒 Bill hii haiwezi kulipwa! Kuna prescriptions ambazo hazijathibitishwa na pharmacy.";
        $_SESSION['flash_type'] = 'error';
        header('Location: partial_payments.php?locked=1');
        exit;
    }

    // ================================================================
    // AJAX: MAKE PAYMENT
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        
        $action = $_POST['action'];
        $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
        $cashier_discount_input = isset($_POST['discount_amount']) ? floatval($_POST['discount_amount']) : 0;
        $cashier_premium_input = isset($_POST['premium_amount']) ? floatval($_POST['premium_amount']) : 0;
        $premium_note = isset($_POST['premium_note']) ? trim($_POST['premium_note']) : '';
        $partial_amount = isset($_POST['partial_amount']) ? floatval($_POST['partial_amount']) : 0;
        $payment_type = isset($_POST['payment_type']) ? $_POST['payment_type'] : 'full';
        $bill_id_post = isset($_POST['bill_id']) ? (int)$_POST['bill_id'] : 0;
        
        if ($action === 'make_payment') {
            if ($bill_id_post <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid bill ID']);
                exit;
            }
            
            if (isBillLocked($db, $bill_id_post)) {
                echo json_encode(['success' => false, 'message' => '🔒 HAIWEZI KULIPWA! Kuna prescriptions ambazo hazijathibitishwa.', 'locked' => true]);
                exit;
            }
            
            try {
                $db->beginTransaction();
                
                // Lock bill FOR UPDATE
                $stmt = $db->prepare("
                    SELECT b.*, p.full_name as patient_name, p.patient_id as patient_number, p.id as patient_id_ref
                    FROM bills b
                    JOIN patients p ON b.patient_id = p.id
                    WHERE b.id = ? AND b.branch_id = ? AND b.status != 'cancelled'
                    FOR UPDATE
                ");
                $stmt->execute([$bill_id_post, $user_branch_id]);
                $current_bill = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$current_bill) {
                    $db->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Bill not found']);
                    exit;
                }
                
                $visit_id = (int)($current_bill['visit_id'] ?? 0);
                
                $subtotal = (float)($current_bill['subtotal'] ?? 0);
                $paid_amount = (float)($current_bill['paid_amount'] ?? 0);
                $db_balance = (float)($current_bill['balance'] ?? 0);
                
                $existing_pharmacy_discount = (float)($current_bill['pharmacy_discount'] ?? 0);
                if ($existing_pharmacy_discount == 0) $existing_pharmacy_discount = (float)($current_bill['discount_amount'] ?? 0);
                $existing_pharmacy_premium = (float)($current_bill['pharmacy_premium'] ?? 0);
                
                $existing_cashier_discount = (float)($current_bill['cashier_discount'] ?? 0);
                $existing_cashier_premium = (float)($current_bill['cashier_premium'] ?? 0);
                
                $new_cashier_discount = max(0, $cashier_discount_input);
                $new_cashier_premium = max(0, $cashier_premium_input);
                
                $total_cashier_discount = $existing_cashier_discount + $new_cashier_discount;
                $total_cashier_premium = $existing_cashier_premium + $new_cashier_premium;
                
                $total_pharmacy_discount = $existing_pharmacy_discount;
                $total_pharmacy_premium = $existing_pharmacy_premium;
                
                $total_discount = $total_pharmacy_discount + $total_cashier_discount;
                $total_premium = $total_pharmacy_premium + $total_cashier_premium;
                
                $remaining_balance = $db_balance + $new_cashier_premium - $new_cashier_discount;
                if ($remaining_balance < 0) $remaining_balance = 0;
                
                if ($payment_type === 'partial') {
                    $amount_to_pay = min($partial_amount, $remaining_balance);
                    if ($amount_to_pay <= 0) {
                        $db->rollBack();
                        echo json_encode(['success' => false, 'message' => 'Invalid partial amount']);
                        exit;
                    }
                    if ($amount_to_pay >= $remaining_balance) {
                        $amount_to_pay = $remaining_balance;
                        $payment_type = 'full';
                    }
                } else {
                    $amount_to_pay = $remaining_balance;
                }
                
                if ($amount_to_pay <= 0) {
                    $db->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Amount to pay must be greater than 0']);
                    exit;
                }
                
                $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                $new_paid = $paid_amount + $amount_to_pay;
                
                $new_balance = $remaining_balance - $amount_to_pay;
                if ($new_balance < 0) $new_balance = 0;
                
                $new_total_amount = $subtotal + $total_premium - $total_discount;
                if ($new_total_amount < 0) $new_total_amount = 0;
                
                $premium_note_final = $current_bill['premium_note'] ?? '';
                if (!empty($premium_note) && $new_cashier_premium > 0) {
                    if (!empty($premium_note_final)) $premium_note_final .= ', ' . $premium_note;
                    else $premium_note_final = $premium_note;
                }
                
                $is_fully_paid = ($new_balance <= 0.01);
                
                if ($is_fully_paid) $new_status = 'paid';
                elseif ($new_paid > 0 && $new_balance > 0) $new_status = 'partial';
                else $new_status = $current_bill['status'] ?? 'pending';
                
                $db->prepare("
                    UPDATE bills 
                    SET paid_amount = ?, balance = ?, total_amount = ?,
                        cashier_discount = ?, cashier_premium = ?,
                        pharmacy_discount = ?, pharmacy_premium = ?,
                        total_discount = ?, discount_amount = ?,
                        premium_amount = ?, premium_note = ?, status = ?, updated_at = NOW()
                    WHERE id = ? AND branch_id = ?
                ")->execute([
                    $new_paid, $new_balance, $new_total_amount,
                    $total_cashier_discount, $total_cashier_premium,
                    $total_pharmacy_discount, $total_pharmacy_premium,
                    $total_discount, $total_pharmacy_discount,
                    $total_premium, $premium_note_final, $new_status,
                    $bill_id_post, $user_branch_id
                ]);
                
                $items_updated = smartUpdateBillItems($db, $bill_id_post, $amount_to_pay, $is_fully_paid);
                $procedures_synced = syncProceduresStatus($db, $bill_id_post, $user_branch_id);
                
                // ============================================================
                // ✅ V19.0: FULL STOCK DEDUCTION ON FULL PAYMENT
                // ✅ KAMA PROCESS_PAYMENT - Hakuna global check!
                // ============================================================
                $stock_result = [
                    'medications_deducted' => 0,
                    'medication_items' => 0,
                    'equipment_deducted' => 0,
                    'equipment_items' => 0,
                    'lab_equipment_deducted' => 0,
                    'lab_equipment_items' => 0,
                    'skipped' => 0,
                    'errors' => []
                ];
                
                if ($is_fully_paid) {
                    // ✅ V19.0: Hakuna global isStockAlreadyDeducted() check
                    // Kila item ina safety check yake ya isStockAlreadyDeductedForItem()
                    $stock_result = deductAllStockForPaidBill($db, $bill_id_post, $user_branch_id, $user_id);
                    error_log("✅ V19.0 STOCK DEDUCTED: Bill #$bill_id_post → Meds: {$stock_result['medications_deducted']}, Equip: {$stock_result['equipment_deducted']}, Lab: {$stock_result['lab_equipment_deducted']}, Skipped: {$stock_result['skipped']}");
                }
                
                $notes = 'V19.0 Payment | Pharm Disc: ' . $currency . ' ' . number_format($total_pharmacy_discount, 0) . ' | Cashier Disc: ' . $currency . ' ' . number_format($total_cashier_discount, 0);
                if ($total_premium > 0) {
                    $notes .= ' | Pharm Prem: ' . $currency . ' ' . number_format($total_pharmacy_premium, 0);
                    $notes .= ' | Cashier Prem: ' . $currency . ' ' . number_format($total_cashier_premium, 0);
                }
                if ($is_fully_paid) $notes .= ' | FULLY PAID';
                
                $db->prepare("
                    INSERT INTO payments (receipt_number, bill_id, patient_id, amount, payment_method, reference_number, received_by, branch_id, received_at, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ")->execute([
                    $receipt_number, $bill_id_post,
                    $current_bill['patient_id_ref'] ?? $current_bill['patient_id'],
                    $amount_to_pay, $payment_method, null, $user_id, $user_branch_id, $notes
                ]);
                
                if ($is_fully_paid && $visit_id > 0) {
                    updateVisitPaymentStatus($db, $visit_id, $user_branch_id);
                    updateVisitCompletionStatus($db, $visit_id, $user_branch_id);
                }
                
                $db->commit();
                
                $message = "✅ Payment successful! Amount: <strong>" . $currency . " " . number_format($amount_to_pay, 0) . "</strong>";
                if ($new_cashier_premium > 0) $message .= " | 👑 Cashier Premium: " . $currency . " " . number_format($new_cashier_premium, 0);
                if ($new_cashier_discount > 0) $message .= " | 🏷️ Cashier Discount: " . $currency . " " . number_format($new_cashier_discount, 0);
                
                if ($is_fully_paid) {
                    $message .= "<br>🎉 Bill FULLY PAID!";
                    if ($items_updated > 0) $message .= "<br>📋 " . $items_updated . " items marked as PAID";
                    if ($procedures_synced > 0) $message .= "<br>🩺 " . $procedures_synced . " procedure(s) SYNCED";
                    
                    if ($stock_result['medications_deducted'] > 0) {
                        $message .= "<br>💊 <strong>" . $stock_result['medications_deducted'] . "</strong> medication qty deducted ({$stock_result['medication_items']} items)";
                    }
                    if ($stock_result['equipment_deducted'] > 0) {
                        $message .= "<br>🛠️ <strong>" . $stock_result['equipment_deducted'] . "</strong> equipment qty deducted ({$stock_result['equipment_items']} items)";
                    }
                    if ($stock_result['lab_equipment_deducted'] > 0) {
                        $message .= "<br>🧪 <strong>" . $stock_result['lab_equipment_deducted'] . "</strong> lab equipment qty deducted ({$stock_result['lab_equipment_items']} items)";
                    }
                    if ($stock_result['skipped'] > 0) {
                        $message .= "<br>⏭️ " . $stock_result['skipped'] . " item(s) skipped (already deducted)";
                    }
                    if (!empty($stock_result['errors'])) {
                        $message .= "<br>⚠️ " . count($stock_result['errors']) . " stock issue(s)";
                    }
                } else {
                    $message .= "<br>💰 Remaining: <strong>" . $currency . " " . number_format($new_balance, 0) . "</strong>";
                    if ($procedures_synced > 0) $message .= "<br>🩺 " . $procedures_synced . " procedure(s) → IN PROGRESS";
                    $message .= "<br>ℹ️ Stock itadeduct tu baada ya FULL PAYMENT";
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'receipt_number' => $receipt_number,
                    'amount_paid' => $amount_to_pay,
                    'new_balance' => $new_balance,
                    'new_status' => $new_status,
                    'is_paid' => $is_fully_paid,
                    'is_partial' => !$is_fully_paid,
                    'stock_result' => $stock_result
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                error_log("❌ V19.0 Payment error: " . $e->getMessage());
            }
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }

    // ================================================================
    // ✅ V19.0: GET BILL - NO AUTO-REPAIR!
    // ================================================================
    $stmt = $db->prepare("
        SELECT 
            b.*, b.subtotal, b.total_amount, b.paid_amount, b.balance,
            b.discount_amount, b.pharmacy_discount, b.cashier_discount, b.total_discount,
            b.premium_amount, b.premium_note, b.patient_id as patient_id_ref,
            b.pharmacy_premium, b.cashier_premium,
            b.pharmacy_premium_note, b.cashier_premium_note,
            b.visit_id,
            v.visit_number, v.visit_type, v.visit_date, v.status as visit_status,
            v.payment_status as visit_payment_status,
            u.full_name as doctor_name,
            p.full_name as patient_name, p.patient_id as patient_number,
            p.phone, p.email, p.gender, p.date_of_birth, p.address, p.blood_group,
            b.created_at as bill_created, b.updated_at as bill_updated
        FROM bills b
        JOIN patients p ON b.patient_id = p.id
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE b.id = ? AND b.branch_id = ? AND b.status != 'cancelled'
    ");
    $stmt->execute([$bill_id, $user_branch_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        header('Location: partial_payments.php?error=bill_not_found');
        exit;
    }

    $subtotal = (float)($bill['subtotal'] ?? 0);
    $paid_amount = (float)($bill['paid_amount'] ?? 0);
    $db_balance = (float)($bill['balance'] ?? 0);
    
    $pharmacy_discount = (float)($bill['pharmacy_discount'] ?? 0);
    if ($pharmacy_discount == 0) $pharmacy_discount = (float)($bill['discount_amount'] ?? 0);
    $pharmacy_premium = (float)($bill['pharmacy_premium'] ?? 0);
    
    $cashier_discount = (float)($bill['cashier_discount'] ?? 0);
    $cashier_premium = (float)($bill['cashier_premium'] ?? 0);
    
    $total_discount = $pharmacy_discount + $cashier_discount;
    $total_premium = $pharmacy_premium + $cashier_premium;
    
    $existing_premium = $total_premium;
    $existing_premium_note = $bill['premium_note'] ?? '';
    
    $remaining_balance = $db_balance;
    if ($remaining_balance < 0) $remaining_balance = 0;
    
    $visit_id_ref = (int)($bill['visit_id'] ?? 0);
    
    if ($visit_id_ref > 0) {
        updateVisitPaymentStatus($db, $visit_id_ref, $user_branch_id);
        updateVisitCompletionStatus($db, $visit_id_ref, $user_branch_id);
    }
    
    syncProceduresStatus($db, $bill_id, $user_branch_id);
    
    $stmt = $db->prepare("
        SELECT bi.*,
            (SELECT status FROM prescriptions WHERE id = bi.reference_id AND reference_type = 'prescription') as prescription_status
        FROM bill_items bi
        WHERE bi.bill_id = ? AND bi.status != 'cancelled'
        ORDER BY bi.status DESC, bi.item_type ASC, bi.created_at ASC
    ");
    $stmt->execute([$bill_id]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $bill_status = $bill['status'] ?? 'pending';
    $is_paid = ($remaining_balance <= 0.01);
    $is_partial = ($remaining_balance > 0 && $paid_amount > 0);
    $is_pending = ($paid_amount == 0 && $remaining_balance > 0);

} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $bill = null; $bill_items = [];
    $subtotal = 0; $paid_amount = 0; $db_balance = 0; $remaining_balance = 0;
    $total_discount = 0; $pharmacy_discount = 0; $cashier_discount = 0;
    $pharmacy_premium = 0; $cashier_premium = 0; $total_premium = 0;
    $existing_premium = 0; $existing_premium_note = '';
    $bill_status = 'pending'; $is_paid = false; $is_partial = false; $is_pending = true;
    $currency = 'TSh'; $visit_id_ref = 0;
    error_log("Make payment error: " . $e->getMessage());
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
    <title>Make Payment - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --font-mono: 'JetBrains Mono', 'Courier New', monospace;
            --primary: #059669;
            --primary-dark: #047857;
            --primary-light: #34D399;
            --primary-bg: #D1FAE5;
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --gold: #D97706;
            --cashier-discount-color: #2563EB;
            --cashier-premium-color: #7C3AED;
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
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-header-bg: #059669;
            --table-header-text: #FFFFFF;
            --table-stripe: #D1FAE5;
            --table-hover: #A7F3D0;
            --page-header-bg-from: #059669;
            --page-header-bg-to: #047857;
            --page-header-shadow: rgba(5, 150, 105, 0.25);
            --locked-color: #DC2626;
            --locked-bg: #FEE2E2;
            --sidebar-width: 270px;
            --content-max-width: 1100px;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.4);
            --table-header-bg: #047857;
            --table-stripe: #1A3A2A;
            --table-hover: #065F46;
            --page-header-bg-from: #047857;
            --page-header-bg-to: #065F46;
            --page-header-shadow: rgba(5, 150, 105, 0.15);
            --primary-bg: #1A3A2A;
            --success-bg: #1A3A2A;
            --warning-bg: #3D2E0A;
            --locked-bg: #3A1A1A;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: 68px;
            padding: 20px 24px 180px 24px;
            min-height: calc(100vh - 68px);
            max-width: 1400px;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--page-header-bg-from), var(--page-header-bg-to));
            border-radius: 16px;
            padding: 20px 28px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            box-shadow: 0 4px 20px var(--page-header-shadow);
            position: relative;
            overflow: hidden;
            max-width: var(--content-max-width);
            margin-left: auto;
            margin-right: auto;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -20%;
            width: 300px; height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.5rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i { font-size: 1.7rem; opacity: 0.9; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
            position: relative;
            z-index: 1;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.62rem;
            font-weight: 700;
            text-transform: uppercase;
            backdrop-filter: blur(4px);
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 600;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .header-badge.premium-badge-header {
            background: rgba(251,191,36,0.3);
            color: #FCD34D;
            font-weight: 700;
        }
        
        .page-header .header-badge.v19-badge {
            background: rgba(16,185,129,0.35);
            color: #A7F3D0;
            border-color: rgba(16,185,129,0.5);
            font-weight: 700;
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.78rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .bill-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            margin: 0 auto 20px;
            max-width: var(--content-max-width);
            width: 100%;
        }
        
        .bill-card .card-header {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .bill-card .card-body { padding: 20px 24px; }
        
        .patient-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 20px;
            margin-bottom: 16px;
            padding: 16px 20px;
            background: var(--primary-bg);
            border-radius: 12px;
            border: 1px solid var(--primary-light);
        }
        
        .patient-info-grid .info-item span:first-child {
            display: block;
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .patient-info-grid .info-item span:last-child {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .bill-summary-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .bill-summary-card {
            background: var(--bg-card);
            border-radius: 10px;
            padding: 10px 8px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            min-height: 85px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .bill-summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .bill-summary-card .label {
            font-size: 0.5rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            display: block;
            letter-spacing: 0.04em;
            line-height: 1.1;
        }
        
        .bill-summary-card .value {
            font-size: 0.9rem;
            font-weight: 800;
            display: block;
            margin-top: 3px;
            font-family: var(--font-mono);
            line-height: 1.1;
        }
        
        .bill-summary-card .sub-label {
            font-size: 0.45rem;
            color: var(--text-secondary);
            display: block;
            margin-top: 2px;
            font-weight: 500;
        }
        
        .bill-summary-card.total { border-color: #059669; }
        .bill-summary-card.total .label { color: #059669; }
        .bill-summary-card.total .value { color: #059669; }
        
        .bill-summary-card.pharm-discount { border-color: #D97706; background: linear-gradient(135deg, var(--bg-card) 0%, rgba(217, 119, 6, 0.05) 100%); }
        .bill-summary-card.pharm-discount .label { color: #D97706; }
        .bill-summary-card.pharm-discount .value { color: #D97706; }
        
        .bill-summary-card.cashier-discount { border-color: #2563EB; background: linear-gradient(135deg, var(--bg-card) 0%, rgba(37, 99, 235, 0.05) 100%); }
        .bill-summary-card.cashier-discount .label { color: #2563EB; }
        .bill-summary-card.cashier-discount .value { color: #2563EB; }
        
        .bill-summary-card.pharm-premium { border-color: #F59E0B; background: linear-gradient(135deg, var(--bg-card) 0%, rgba(245, 158, 11, 0.05) 100%); }
        .bill-summary-card.pharm-premium .label { color: #F59E0B; }
        .bill-summary-card.pharm-premium .value { color: #F59E0B; }
        
        .bill-summary-card.cashier-premium { border-color: #7C3AED; background: linear-gradient(135deg, var(--bg-card) 0%, rgba(124, 58, 237, 0.05) 100%); }
        .bill-summary-card.cashier-premium .label { color: #7C3AED; }
        .bill-summary-card.cashier-premium .value { color: #7C3AED; }
        
        .bill-summary-card.paid { border-color: #059669; }
        .bill-summary-card.paid .label { color: #059669; }
        .bill-summary-card.paid .value { color: #059669; }
        
        .bill-summary-card.balance { border-color: #DC2626; }
        .bill-summary-card.balance .label { color: #DC2626; }
        .bill-summary-card.balance .value { color: #DC2626; }
        .bill-summary-card.balance.zero { border-color: #059669; }
        .bill-summary-card.balance.zero .label { color: #059669; }
        .bill-summary-card.balance.zero .value { color: #059669; }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            min-width: 600px;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            background: var(--table-header-bg);
            color: var(--table-header-text);
            white-space: nowrap;
        }
        
        .data-table tbody td {
            padding: 8px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-family: var(--font-mono);
        }
        
        .data-table tbody tr:nth-child(even) { background: var(--table-stripe); }
        .data-table tbody tr:hover td { background: var(--table-hover); }
        
        .data-table tbody tr.locked-item td {
            background: var(--locked-bg) !important;
            opacity: 0.85;
        }
        
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
            padding: 12px 20px;
            box-shadow: 0 -4px 20px rgba(5, 150, 105, 0.18), 0 4px 16px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: var(--content-max-width);
            width: 100%;
            pointer-events: auto;
        }
        
        .payment-controls .control-row-1 {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            justify-content: center;
            padding-bottom: 10px;
            border-bottom: 1.5px dashed var(--border-color);
        }
        
        .payment-controls .control-row-2 {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            justify-content: space-between;
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
            padding: 7px 10px;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.72rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            width: 110px;
            font-family: var(--font-mono);
            height: 36px;
            font-weight: 700;
            transition: all 0.2s ease;
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
            width: 110px;
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
        
        .payment-controls .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 0.72rem;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
            flex-shrink: 0;
            height: 36px;
            font-family: var(--font-mono);
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }
        
        .payment-controls .btn i { font-size: 0.68rem; }
        
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
        
        .payment-controls .btn:disabled { 
            opacity: 0.5; 
            cursor: not-allowed; 
            transform: none !important; 
        }
        
        .payment-controls .remaining-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            border: 2px solid #D97706;
            border-radius: 10px;
            height: 42px;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(217, 119, 6, 0.15);
        }
        
        [data-theme="dark"] .payment-controls .remaining-badge {
            background: linear-gradient(135deg, #3D2E0A, #4A3A12);
        }
        
        .payment-controls .remaining-badge .label {
            font-size: 0.58rem;
            font-weight: 800;
            color: #D97706;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-family: var(--font-mono);
        }
        
        .payment-controls .remaining-badge .value {
            font-size: 0.9rem;
            font-weight: 900;
            color: #D97706;
            font-family: var(--font-mono);
            letter-spacing: -0.02em;
        }
        
        .toast-custom {
            position: fixed;
            bottom: 140px;
            right: 24px;
            padding: 16px 24px;
            border-radius: 14px;
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
        .toast-custom.info { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            max-width: var(--content-max-width);
            margin-left: auto;
            margin-right: auto;
        }
        
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        @media (max-width: 1200px) {
            .bill-summary-grid { grid-template-columns: repeat(4, 1fr); }
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px 16px 220px 16px; }
        }
        
        @media (max-width: 768px) {
            .bill-summary-grid { grid-template-columns: repeat(3, 1fr); }
            .patient-info-grid { grid-template-columns: 1fr; }
            
            .payment-controls .control-row-1,
            .payment-controls .control-row-2 {
                flex-direction: column;
                align-items: stretch;
            }
            
            .payment-controls .control-group { 
                justify-content: space-between; 
                width: 100%;
            }
            
            .payment-controls .control-group select,
            .payment-controls .control-group input { 
                flex: 1; 
                width: auto; 
            }
            
            .payment-controls .btn { 
                width: 100%; 
                justify-content: center; 
            }
            
            .payment-controls .divider {
                width: 100%;
                height: 1px;
            }
            
            .main-content { padding-bottom: 380px; }
        }
        
        @media (max-width: 480px) {
            .bill-summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-money-bill-wave"></i>
                Make Payment
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
                <?php if ($existing_premium > 0): ?>
                    <span class="header-badge premium-badge-header">
                        <i class="fas fa-crown"></i> Premium: <?= $currency ?> <?= number_format($existing_premium, 0) ?>
                    </span>
                <?php endif; ?>
                <span class="header-badge v19-badge">
                    <i class="fas fa-shield-alt"></i> V19.0 - SAME AS PROCESS_PAYMENT
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-credit-card"></i>
                Complete payment for bill
                <span class="header-badge">
                    <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-money-bill"></i> Remaining: <?= $currency ?> <?= number_format($remaining_balance, 0) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="partial_payments.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- BILL CARD -->
    <div class="bill-card">
        <div class="card-header">
            <div>
                <span style="font-weight:700;font-family:var(--font-mono);">
                    <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                </span>
                <span style="font-size:0.7rem;opacity:0.8;margin-left:12px;">
                    <?= date('d/m/Y H:i', strtotime($bill['bill_created'] ?? 'now')) ?>
                </span>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <span style="padding:4px 16px;border-radius:20px;font-size:0.7rem;font-weight:600;background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.2);">
                    <?= ucfirst($bill_status) ?>
                </span>
            </div>
        </div>
        
        <div class="card-body">
            <div class="patient-info-grid">
                <div class="info-item"><span>Patient Name</span><span><strong><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></strong></span></div>
                <div class="info-item"><span>Patient ID</span><span><?= htmlspecialchars($bill['patient_number'] ?? 'N/A') ?></span></div>
                <div class="info-item"><span>Phone</span><span><?= htmlspecialchars($bill['phone'] ?? 'N/A') ?></span></div>
                <div class="info-item"><span>Gender</span><span><?= htmlspecialchars($bill['gender'] ?? 'N/A') ?></span></div>
                <div class="info-item"><span>Doctor</span><span>Dr. <?= htmlspecialchars($bill['doctor_name'] ?? 'Not Assigned') ?></span></div>
                <div class="info-item"><span>Visit Type</span><span><?= ucfirst($bill['visit_type'] ?? 'N/A') ?></span></div>
            </div>
            
            <div class="bill-summary-grid">
                <div class="bill-summary-card total">
                    <span class="label">📋 Subtotal</span>
                    <span class="value"><?= $currency ?> <?= number_format($subtotal, 0) ?></span>
                    <span class="sub-label">Bill amount</span>
                </div>
                
                <div class="bill-summary-card pharm-discount">
                    <span class="label">🏷️ Pharm Disc</span>
                    <span class="value" id="pharmDiscountDisplay"><?= $currency ?> <?= number_format($pharmacy_discount, 0) ?></span>
                    <span class="sub-label">From Pharmacy</span>
                </div>
                
                <div class="bill-summary-card cashier-discount">
                    <span class="label">🏷️ Cashier Disc</span>
                    <span class="value" id="cashierDiscountDisplay"><?= $currency ?> <?= number_format($cashier_discount, 0) ?></span>
                    <span class="sub-label">From Cashier</span>
                </div>
                
                <div class="bill-summary-card pharm-premium">
                    <span class="label">👑 Pharm Prem</span>
                    <span class="value" id="pharmPremiumDisplay"><?= $currency ?> <?= number_format($pharmacy_premium, 0) ?></span>
                    <span class="sub-label">From Pharmacy</span>
                </div>
                
                <div class="bill-summary-card cashier-premium">
                    <span class="label">👑 Cashier Prem</span>
                    <span class="value" id="cashierPremiumDisplay"><?= $currency ?> <?= number_format($cashier_premium, 0) ?></span>
                    <span class="sub-label">From Cashier</span>
                </div>
                
                <div class="bill-summary-card paid">
                    <span class="label">✅ Paid Amount</span>
                    <span class="value" id="paidAmountDisplay"><?= $currency ?> <?= number_format($paid_amount, 0) ?></span>
                    <span class="sub-label" id="paidStatus">
                        <?php if ($paid_amount > 0): ?>Partial<?php else: ?>No payments<?php endif; ?>
                    </span>
                </div>
                
                <div class="bill-summary-card balance <?= $remaining_balance <= 0 ? 'zero' : '' ?>">
                    <span class="label">📊 Remaining</span>
                    <span class="value" id="balanceDisplay"><?= $currency ?> <?= number_format($remaining_balance, 0) ?></span>
                    <span class="sub-label" id="balanceStatus">
                        <?= $remaining_balance <= 0 ? '✅ Fully paid!' : 'Pending' ?>
                    </span>
                </div>
            </div>
            
            <h4 style="font-size:0.85rem;font-weight:600;margin-top:16px;margin-bottom:10px;">
                <i class="fas fa-list" style="color:var(--primary);"></i> Bill Items
                <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);">(<?= count($bill_items) ?> items)</span>
            </h4>
            
            <div style="overflow-x:auto;border-radius:12px;border:1px solid var(--border-color);">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:5%;">#</th>
                            <th style="width:30%;">Item Name</th>
                            <th style="width:12%;">Type</th>
                            <th style="width:8%;text-align:center;">Qty</th>
                            <th style="width:15%;text-align:right;">Unit Price</th>
                            <th style="width:15%;text-align:right;">Total</th>
                            <th style="width:15%;text-align:center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bill_items) > 0): ?>
                            <?php $i = 1; foreach ($bill_items as $item): 
                                $is_item_paid = ($item['status'] === 'paid');
                                $is_item_partial = ($item['status'] === 'partial');
                                $is_medication = ($item['item_type'] === 'medication');
                                $is_equipment = ($item['item_type'] === 'equipment');
                                $pres_status = $item['prescription_status'] ?? 'pending';
                                $is_item_locked = ($is_medication && $item['reference_type'] === 'prescription' && $pres_status !== 'confirmed' && $pres_status !== 'dispensed');
                                $price = (float)($item['total_price'] ?? $item['unit_price'] ?? 0);
                                $unit_price = (float)($item['unit_price'] ?? 0);
                                $qty = (int)($item['quantity'] ?? 1);
                            ?>
                            <tr class="<?= $is_item_paid ? 'paid-item' : '' ?> <?= $is_item_locked ? 'locked-item' : '' ?>">
                                <td><?= $i++ ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <?php if ($is_medication): ?>
                                            <i class="fas fa-pills" style="color:var(--success);"></i>
                                        <?php elseif ($is_equipment): ?>
                                            <i class="fas fa-tools" style="color:var(--warning);"></i>
                                        <?php elseif ($item['item_type'] === 'lab_test'): ?>
                                            <i class="fas fa-flask" style="color:#0EA5E9;"></i>
                                        <?php elseif ($item['item_type'] === 'procedure'): ?>
                                            <i class="fas fa-syringe" style="color:var(--purple);"></i>
                                        <?php elseif ($item['item_type'] === 'consultation'): ?>
                                            <i class="fas fa-stethoscope" style="color:var(--primary);"></i>
                                        <?php else: ?>
                                            <i class="fas fa-cube"></i>
                                        <?php endif; ?>
                                        <strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong>
                                    </div>
                                    <?php if ($is_item_locked): ?>
                                        <div style="font-size:0.5rem;color:var(--locked-color);margin-top:2px;">
                                            <i class="fas fa-lock"></i> Waiting pharmacy
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size:0.55rem;background:var(--bg-body);padding:1px 8px;border-radius:4px;border:1px solid var(--border-color);">
                                        <?= ucfirst($item['item_type'] ?? 'item') ?>
                                    </span>
                                </td>
                                <td style="text-align:center;"><?= $qty ?></td>
                                <td style="text-align:right;"><?= $currency ?> <?= number_format($unit_price, 0) ?></td>
                                <td style="text-align:right;font-weight:600;<?= $is_item_paid ? 'color:var(--success);' : ($is_item_partial ? 'color:var(--warning);' : 'color:var(--danger);') ?>">
                                    <?= $currency ?> <?= number_format($price, 0) ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($is_item_locked): ?>
                                        <span style="font-size:0.55rem;font-weight:600;padding:3px 12px;border-radius:12px;background:var(--locked-bg);color:var(--locked-color);border:1px solid var(--locked-color);">
                                            <i class="fas fa-lock"></i> Locked
                                        </span>
                                    <?php elseif ($is_item_paid): ?>
                                        <span style="font-size:0.55rem;font-weight:600;padding:3px 12px;border-radius:12px;background:var(--success-bg);color:var(--success);">✅ Paid</span>
                                    <?php elseif ($is_item_partial): ?>
                                        <span style="font-size:0.55rem;font-weight:600;padding:3px 12px;border-radius:12px;background:var(--warning-bg);color:var(--warning);">🔄 Partial</span>
                                    <?php else: ?>
                                        <span style="font-size:0.55rem;font-weight:600;padding:3px 12px;border-radius:12px;background:var(--warning-bg);color:var(--warning);">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text-secondary);">No items found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <footer class="footer">
        <p>
            <span style="color:var(--success);font-weight:600;">Braick Dispensary</span> Management System
            <span>|</span> Make Payment V19.0 (Same as Process Payment)
            <span>|</span>
            <span>👤 <?= htmlspecialchars($user_full_name) ?></span>
            <span>|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?>
        </p>
    </footer>

</main>

<!-- PAYMENT CONTROLS - 2 ROWS -->
<?php if (!$is_paid && $remaining_balance > 0): ?>
<div class="payment-controls-wrapper">
    <div class="payment-controls" id="paymentControls">
        
        <!-- ROW 1: INPUTS -->
        <div class="control-row-1">
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
                    <input type="text" id="discountAmount" placeholder="0" value="0" oninput="formatAmount(this); updateTotals();">
                </div>
            </div>
            
            <div class="divider"></div>
            
            <div class="control-group">
                <label><i class="fas fa-crown"></i> Prem:</label>
                <div class="amount-input-wrap">
                    <span class="currency-prefix"><?= $currency ?></span>
                    <input type="text" id="premiumAmount" class="premium-input" placeholder="0" value="0" oninput="formatAmount(this); updateTotals();">
                </div>
            </div>
            
            <div class="divider"></div>
            
            <div class="control-group">
                <label><i class="fas fa-hand-holding-heart"></i> Partial:</label>
                <div class="amount-input-wrap">
                    <span class="currency-prefix"><?= $currency ?></span>
                    <input type="text" id="partialAmount" placeholder="0" value="0" oninput="formatAmount(this); updateTotals();">
                </div>
            </div>
        </div>
        
        <!-- ROW 2: ACTIONS -->
        <div class="control-row-2">
            <div class="remaining-badge">
                <span class="label">Remaining</span>
                <span class="value" id="displayBalanceGrand"><?= $currency ?> <?= number_format($remaining_balance, 0) ?></span>
            </div>
            
            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-left:auto;">
                <button onclick="processPayment('partial')" class="btn btn-warning" id="partialPayBtn">
                    <i class="fas fa-hand-holding-heart"></i> PARTIAL
                </button>
                <button onclick="processPayment('full')" class="btn btn-success" id="fullPayBtn">
                    <i class="fas fa-check-circle"></i> FULL
                </button>
            </div>
        </div>
        
    </div>
</div>
<?php endif; ?>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.3rem;"></i>
    <div>
        <p style="font-weight:800;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.78rem;opacity:0.95;margin:0;margin-top:2px;" id="toastMessage"></p>
    </div>
</div>

<script>
    var billId = <?= $bill_id ?>;
    var patientId = <?= $bill['patient_id_ref'] ?? $bill['patient_id'] ?? 0 ?>;
    
    var subtotal = <?= $subtotal ?>;
    var paidAmount = <?= $paid_amount ?>;
    var dbBalance = <?= $remaining_balance ?>;
    
    var pharmacyDiscount = <?= $pharmacy_discount ?>;
    var pharmacyPremium = <?= $pharmacy_premium ?>;
    
    var cashierDiscountExisting = <?= $cashier_discount ?>;
    var cashierPremiumExisting = <?= $cashier_premium ?>;
    
    var currency = '<?= $currency ?>';
    var isPaid = <?= $is_paid ? 'true' : 'false' ?>;
    var visitId = <?= $visit_id_ref ?? 0 ?>;

    console.log('%c💰 Make Payment V19.0 FINAL', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c✅ SAME LOGIC AS process_payment.php');
    console.log('%c✅ NO global isStockAlreadyDeducted() check');
    console.log('%c✅ Kila item ina safety check yake');
    console.log('%c✅ Lab equipment query HAINA lte.branch_id = lt.branch_id');

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

    function updateTotals() {
        if (isPaid) return;
        
        var discountInput = document.getElementById('discountAmount');
        var premiumInput = document.getElementById('premiumAmount');
        var partialInput = document.getElementById('partialAmount');
        
        var newCashierDiscount = getRawValue(discountInput);
        var newCashierPremium = getRawValue(premiumInput);
        var partial = getRawValue(partialInput);
        
        var remainingBalance = dbBalance + newCashierPremium - newCashierDiscount;
        if (remainingBalance < 0) remainingBalance = 0;
        
        document.getElementById('displayBalanceGrand').textContent = currency + ' ' + remainingBalance.toFixed(0);
        
        var fullBtn = document.getElementById('fullPayBtn');
        var partialBtn = document.getElementById('partialPayBtn');
        
        if (remainingBalance <= 0) {
            fullBtn.disabled = true;
            fullBtn.innerHTML = '<i class="fas fa-check-circle"></i> Already Paid';
            partialBtn.disabled = true;
        } else {
            fullBtn.disabled = false;
            fullBtn.innerHTML = '<i class="fas fa-check-circle"></i> FULL (' + currency + ' ' + remainingBalance.toFixed(0) + ')';
            
            if (partial > 0 && partial <= remainingBalance) {
                partialBtn.disabled = false;
                partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> PARTIAL (' + currency + ' ' + partial.toFixed(0) + ')';
            } else {
                partialBtn.disabled = true;
                partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> PARTIAL';
            }
        }
    }

    function processPayment(type) {
        if (isPaid) {
            showToast('✅ Already Paid', 'Bill is fully paid', 'info');
            return;
        }
        
        var paymentMethod = document.getElementById('paymentMethod').value;
        var newCashierDiscount = getRawValue(document.getElementById('discountAmount'));
        var newCashierPremium = getRawValue(document.getElementById('premiumAmount'));
        var partialAmount = getRawValue(document.getElementById('partialAmount'));
        
        var remainingBalance = dbBalance + newCashierPremium - newCashierDiscount;
        if (remainingBalance < 0) remainingBalance = 0;
        
        var amountToPay = remainingBalance;
        
        if (type === 'partial') {
            if (partialAmount <= 0) {
                showToast('⚠️ Invalid Amount', 'Please enter a valid partial amount', 'warning');
                return;
            }
            if (partialAmount > remainingBalance) {
                showToast('⚠️ Amount Exceeds', 'Partial amount exceeds remaining balance', 'warning');
                return;
            }
            amountToPay = partialAmount;
        }
        
        if (amountToPay <= 0) {
            showToast('⚠️ Invalid Amount', 'Amount to pay must be greater than 0', 'warning');
            return;
        }
        
        var stockWarning = '';
        if (type === 'full') {
            stockWarning = '\n\n⚠️ STOCK ITADEDUCT:\n💊 Medications\n🛠️ Equipment\n🧪 Lab-linked equipment\n\n✅ SAFETY: Double deduction imezuiwa';
        } else {
            stockWarning = '\n\nℹ️ Partial payment — stock HAITADEDUCT.\nStock itadeduct tu kwa FULL payment.';
        }
        
        var confirmMsg = '💳 ' + (type === 'partial' ? 'PARTIAL' : 'FULL') + ' PAYMENT\n' +
                         '═══════════════════════════════\n' +
                         'Patient: <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>\n' +
                         'Subtotal: ' + currency + ' ' + subtotal.toFixed(0) + '\n' +
                         '───────────────────────────────\n' +
                         '📊 DB Balance: ' + currency + ' ' + dbBalance.toFixed(0) + '\n' +
                         '👑 New Premium: ' + currency + ' ' + newCashierPremium.toFixed(0) + '\n' +
                         '🏷️ New Discount: ' + currency + ' ' + newCashierDiscount.toFixed(0) + '\n' +
                         '───────────────────────────────\n' +
                         '📊 Remaining: ' + currency + ' ' + remainingBalance.toFixed(0) + '\n' +
                         '───────────────────────────────\n' +
                         '💰 Amount to Pay: ' + currency + ' ' + amountToPay.toFixed(0) + '\n' +
                         'Method: ' + paymentMethod.toUpperCase() + '\n' +
                         stockWarning +
                         '\n\nConfirm payment?';
        
        if (!confirm(confirmMsg)) return;
        
        var btn = type === 'partial' ? document.getElementById('partialPayBtn') : document.getElementById('fullPayBtn');
        var originalHtml = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Processing...';
        
        var formData = new FormData();
        formData.append('action', 'make_payment');
        formData.append('bill_id', billId);
        formData.append('payment_method', paymentMethod);
        formData.append('payment_type', type);
        if (newCashierDiscount > 0) formData.append('discount_amount', newCashierDiscount);
        if (newCashierPremium > 0) formData.append('premium_amount', newCashierPremium);
        formData.append('partial_amount', amountToPay);
        
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('✅ Success', data.message, 'success');
                if (data.is_paid) {
                    setTimeout(function() { window.location.href = 'partial_payments.php?success=paid'; }, 5000);
                } else {
                    setTimeout(function() { window.location.reload(); }, 2500);
                }
            } else {
                if (data.locked) {
                    showToast('🔒 LOCKED', data.message, 'error');
                } else {
                    showToast('❌ Error', data.message, 'error');
                }
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        })
        .catch(function(error) {
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
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

    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) footerTimestamp.textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    document.addEventListener('DOMContentLoaded', function() {
        updateTotals();
        console.log('✅ Make Payment V19.0 loaded');
        console.log('✅ SAME LOGIC AS process_payment.php');
        console.log('✅ NO global isStockAlreadyDeducted()');
        console.log('✅ Lab-linked equipment itadeduct');
    });
</script>

</body>
</html>