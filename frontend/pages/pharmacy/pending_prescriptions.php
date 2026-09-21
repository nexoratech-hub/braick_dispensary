<?php
// ================================================================
// FILE: frontend/pages/pharmacy/pending_prescriptions.php
// PHARMACY - PRESCRIPTIONS V11 (MEDICATION + PHARMACY ONLY)
// ================================================================
// ✅ MEDICATION ITEMS ONLY - Bila consultation
// ✅ PHARMACY DISCOUNT/PREMIUM ONLY - Bila cashier
// ✅ Unit Price & Amount kwa kila dawa - SAHIHI
// ✅ Medication Total row kwenye table - SAHIHI
// ✅ FIX: bill_items.total_price = qty × unit_price (BILA discount)
// ✅ FIX: bills.total_amount = medication_total + premium - discount
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';
$message_type = '';
$currency = 'TSh';

try {
    // SYSTEM SETTINGS
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
    
    // FLASH MESSAGES
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $message_type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
    
    // ================================================================
    // HANDLE CONFIRM PRESCRIPTION
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_prescription') {
        $prescription_id = isset($_POST['prescription_id']) ? (int)$_POST['prescription_id'] : 0;
        $discount_amount = isset($_POST['discount_amount']) ? (float)$_POST['discount_amount'] : 0;
        $discount_percent = isset($_POST['discount_percent']) ? (float)$_POST['discount_percent'] : 0;
        
        if ($prescription_id > 0) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("
                    SELECT p.*, pat.id as patient_id, pat.full_name as patient_name, 
                           pat.patient_id as patient_code, v.id as visit_id
                    FROM prescriptions p
                    JOIN patients pat ON p.patient_id = pat.id
                    LEFT JOIN visits v ON p.visit_id = v.id
                    WHERE p.id = ? AND p.branch_id = ? AND p.status = 'pending'
                ");
                $stmt->execute([$prescription_id, $user_branch_id]);
                $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($prescription) {
                    $stmt_items = $db->prepare("SELECT * FROM prescription_items WHERE prescription_id = ?");
                    $stmt_items->execute([$prescription_id]);
                    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($items)) throw new Exception("No items found in this prescription");
                    
                    $medication_total = 0;
                    foreach ($items as $item) {
                        $stmt_price = $db->prepare("
                            SELECT selling_price FROM medications_inventory 
                            WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0
                            ORDER BY created_at DESC LIMIT 1
                        ");
                        $stmt_price->execute([$item['medication_name'], $user_branch_id]);
                        $unit_price = $stmt_price->fetch(PDO::FETCH_ASSOC)['selling_price'] ?? 0;
                        $item_total = $unit_price * $item['quantity'];
                        $medication_total += $item_total;
                        
                        // ✅ SAHIHI: total_price = qty × unit_price (BILA discount)
                        $db->prepare("UPDATE prescription_items SET unit_price = ?, total_price = ? WHERE id = ? AND prescription_id = ?")
                           ->execute([$unit_price, $item_total, $item['id'], $prescription_id]);
                    }
                    
                    $discount_calc = 0;
                    if ($discount_percent > 0) $discount_calc = ($medication_total * $discount_percent) / 100;
                    elseif ($discount_amount > 0) $discount_calc = $discount_amount;
                    
                    $stmt_bill = $db->prepare("
                        SELECT DISTINCT b.id, b.bill_number, b.pharmacy_premium 
                        FROM bills b
                        JOIN bill_items bi ON b.id = bi.bill_id
                        WHERE bi.reference_id = ? AND bi.reference_type = 'prescription'
                        AND bi.item_type = 'medication' AND b.status != 'cancelled' LIMIT 1
                    ");
                    $stmt_bill->execute([$prescription_id]);
                    $existing_bill = $stmt_bill->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing_bill) {
                        $bill_id = $existing_bill['id'];
                        $existing_premium = (float)($existing_bill['pharmacy_premium'] ?? 0);
                        
                        $stmt_med = $db->prepare("SELECT SUM(total_price) as med_total, COUNT(*) as med_count FROM bill_items WHERE bill_id = ? AND item_type = 'medication' AND status != 'cancelled'");
                        $stmt_med->execute([$bill_id]);
                        $med_data = $stmt_med->fetch(PDO::FETCH_ASSOC);
                        $med_count = $med_data['med_count'] ?? 0;
                        $current_med_total = (float)($med_data['med_total'] ?? 0);
                        
                        $discount_per_item = ($discount_calc > 0 && $med_count > 0) ? $discount_calc / $med_count : 0;
                        
                        // ✅ SAHIHI: discount_amount pekee, USIGUSE total_price/final_price
                        $db->prepare("
                            UPDATE bill_items SET discount_amount = ?, updated_at = NOW()
                            WHERE bill_id = ? AND item_type = 'medication' AND reference_type = 'prescription' AND reference_id = ?
                        ")->execute([$discount_per_item, $bill_id, $prescription_id]);
                        
                        // ✅ SAHIHI: total_amount = medication_total + premium - discount
                        $new_total = $current_med_total + $existing_premium - $discount_calc;
                        
                        $db->prepare("
                            UPDATE bills SET 
                                pharmacy_discount = ?, 
                                discount_amount = ?, 
                                total_amount = ?, 
                                balance = ? - paid_amount,
                                updated_at = NOW(),
                                notes = CONCAT(COALESCE(notes, ''), ' | Pharmacy discount: applied ', NOW())
                            WHERE id = ? AND branch_id = ?
                        ")->execute([$discount_calc, $discount_calc, $new_total, $new_total, $bill_id, $user_branch_id]);
                        
                        $message = "✅ Prescription confirmed! Bill #{$existing_bill['bill_number']} updated.";
                        $message_type = 'success';
                    } else {
                        $bill_number = 'BILL-PRES-' . date('Ymd') . '-' . str_pad($prescription['patient_id'], 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                        
                        // ✅ SAHIHI: total_amount = medication_total + 0 premium - discount
                        $initial_total = $medication_total - $discount_calc;
                        
                        $db->prepare("
                            INSERT INTO bills (bill_number, patient_id, visit_id, branch_id, created_by,
                            subtotal, discount_amount, pharmacy_discount, pharmacy_premium, discount_percent, total_discount, total_amount, paid_amount, balance, status, payment_method, notes, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 0, ?, 'pending', 'cash', ?, NOW(), NOW())
                        ")->execute([$bill_number, $prescription['patient_id'], $prescription['visit_id'], $user_branch_id, $user_id, $medication_total, $discount_calc, $discount_calc, $discount_percent, $discount_calc, $initial_total, $initial_total, "Prescription #{$prescription['prescription_number']} - Confirmed"]);
                        $bill_id = $db->lastInsertId();
                        
                        foreach ($items as $item) {
                            $item_total = ($item['unit_price'] ?? 0) * $item['quantity'];
                            // ✅ SAHIHI: total_price = qty × unit_price (BILA discount), discount_amount = 0
                            $db->prepare("
                                INSERT INTO bill_items (bill_id, patient_id, branch_id, item_type, item_name, quantity, unit_price, total_price, discount_amount, tax_amount, final_price, reference_id, reference_type, status, created_at, updated_at)
                                VALUES (?, ?, ?, 'medication', ?, ?, ?, ?, 0, 0, ?, ?, 'prescription', 'pending', NOW(), NOW())
                            ")->execute([$bill_id, $prescription['patient_id'], $user_branch_id, $item['medication_name'], $item['quantity'], $item['unit_price'] ?? 0, $item_total, $item_total, $prescription_id]);
                        }
                        
                        $message = "✅ Prescription confirmed! New bill: #{$bill_number}";
                        $message_type = 'success';
                    }
                    
                    $db->prepare("UPDATE prescriptions SET status = 'confirmed', pharmacy_id = ?, updated_at = NOW() WHERE id = ? AND branch_id = ?")
                       ->execute([$user_id, $prescription_id, $user_branch_id]);
                    
                    $db->commit();
                } else {
                    $db->rollBack();
                    $message = "❌ Prescription not found or already processed.";
                    $message_type = 'error';
                }
            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
        
        $redirect_url = 'pending_prescriptions.php';
        if (!empty($_GET)) {
            $params = [];
            if (!empty($_GET['status'])) $params['status'] = $_GET['status'];
            if (!empty($_GET['search'])) $params['search'] = $_GET['search'];
            if (!empty($params)) $redirect_url .= '?' . http_build_query($params);
        }
        header('Location: ' . $redirect_url);
        exit;
    }
    
    // ================================================================
    // AUTO-DISPENSE
    // ================================================================
    $auto_dispensed_count = 0;
    try {
        $stmt = $db->prepare("
            SELECT p.id as prescription_id, p.patient_id, p.visit_id, p.prescription_number, b.id as bill_id, b.bill_number
            FROM prescriptions p
            JOIN bills b ON b.visit_id = p.visit_id AND b.patient_id = p.patient_id
            WHERE p.branch_id = ? AND p.status = 'confirmed' AND b.status = 'paid'
            AND p.id NOT IN (SELECT pi.prescription_id FROM prescription_items pi WHERE pi.prescription_id = p.id AND pi.dispensed_at IS NOT NULL)
            GROUP BY p.id
        ");
        $stmt->execute([$user_branch_id]);
        $to_dispense = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($to_dispense as $item) {
            try {
                $db->beginTransaction();
                $stmt_items = $db->prepare("SELECT * FROM prescription_items WHERE prescription_id = ?");
                $stmt_items->execute([$item['prescription_id']]);
                $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($items)) { $db->rollBack(); continue; }
                
                $can_dispense = true;
                foreach ($items as $pres_item) {
                    $stmt_stock = $db->prepare("SELECT SUM(quantity) as total_available FROM medications_inventory WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0");
                    $stmt_stock->execute([$pres_item['medication_name'], $user_branch_id]);
                    $available = $stmt_stock->fetch(PDO::FETCH_ASSOC)['total_available'] ?? 0;
                    if ($available < $pres_item['quantity']) { $can_dispense = false; break; }
                }
                
                if (!$can_dispense) { $db->rollBack(); continue; }
                
                $db->prepare("UPDATE prescriptions SET status='dispensed', dispensed_at=NOW(), updated_at=NOW(), pharmacy_id=? WHERE id=? AND branch_id=?")
                   ->execute([$user_id, $item['prescription_id'], $user_branch_id]);
                
                $db->prepare("UPDATE prescription_items SET dispensed_at=NOW(), dispensed_by=? WHERE prescription_id=?")
                   ->execute([$user_id, $item['prescription_id']]);
                
                foreach ($items as $pres_item) {
                    $needed = $pres_item['quantity'];
                    $stmt_batches = $db->prepare("SELECT id, medication_name, quantity, batch_number, expiry_date FROM medications_inventory WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0 ORDER BY expiry_date ASC");
                    $stmt_batches->execute([$pres_item['medication_name'], $user_branch_id]);
                    $batches = $stmt_batches->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($batches as $batch) {
                        if ($needed <= 0) break;
                        $deduct = min($needed, $batch['quantity']);
                        $new_qty = $batch['quantity'] - $deduct;
                        
                        $db->prepare("UPDATE medications_inventory SET quantity=?, updated_at=NOW() WHERE id=? AND branch_id=?")->execute([$new_qty, $batch['id'], $user_branch_id]);
                        
                        $db->prepare("
                            INSERT INTO stock_movements (inventory_id, patient_id, movement_type, quantity, previous_stock, new_stock, reference_type, reference_id, performed_by, branch_id, notes, created_at)
                            VALUES (?, ?, 'out', ?, ?, ?, 'prescription', ?, ?, ?, ?, NOW())
                        ")->execute([$batch['id'], $item['patient_id'], $deduct, $batch['quantity'], $new_qty, $item['prescription_id'], $user_id, $user_branch_id, "Auto-dispensed - Rx #{$item['prescription_number']}"]);
                        
                        $needed -= $deduct;
                    }
                }
                
                $db->commit();
                $auto_dispensed_count++;
            } catch (Exception $e) { $db->rollBack(); }
        }
        
        if ($auto_dispensed_count > 0) {
            $message = "✅ " . $auto_dispensed_count . " prescription(s) auto-dispensed!";
            $message_type = 'success';
        }
    } catch (Exception $e) {}
    
    // ================================================================
    // FILTER PARAMETERS
    // ================================================================
    $filter_status = isset($_GET['status']) ? $_GET['status'] : 'pending';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // ================================================================
    // MAIN QUERY
    // ================================================================
    $conditions = ["p.branch_id = ?"];
    $params = [$user_branch_id];
    
    if ($filter_status === 'all') {
        $conditions[] = "p.status IN ('pending', 'confirmed', 'dispensed')";
    } else {
        $conditions[] = "p.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($search)) {
        $conditions[] = "(pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.prescription_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(" AND ", $conditions);
    
    $sql = "
        SELECT 
            p.visit_id, p.patient_id,
            pat.full_name as patient_name, pat.patient_id as patient_code,
            pat.phone, pat.gender, pat.date_of_birth,
            v.visit_number, v.visit_date, v.created_at as visit_created_at
        FROM prescriptions p
        JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN visits v ON p.visit_id = v.id
        WHERE $where_clause
        AND EXISTS (
            SELECT 1 FROM prescription_items pi 
            WHERE pi.prescription_id = p.id 
            AND pi.quantity > 0
        )
        GROUP BY p.visit_id, p.patient_id
        ORDER BY v.visit_date DESC, p.patient_id ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $visit_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // BUILD VISIT DATA
    // ================================================================
    $visit_data = [];
    
    foreach ($visit_groups as $vg) {
        $visit_id = $vg['visit_id'];
        $patient_id = $vg['patient_id'];
        
        $pres_conditions = ["p.patient_id = ?", "p.branch_id = ?", "p.visit_id = ?"];
        $pres_params = [$patient_id, $user_branch_id, $visit_id];
        
        if ($filter_status === 'all') {
            $pres_conditions[] = "p.status IN ('pending', 'confirmed', 'dispensed')";
        } else {
            $pres_conditions[] = "p.status = ?";
            $pres_params[] = $filter_status;
        }
        
        $pres_where = implode(" AND ", $pres_conditions);
        
        $stmt_pres = $db->prepare("
            SELECT p.*, u.full_name as doctor_name, v.visit_number, v.visit_date
            FROM prescriptions p
            LEFT JOIN users u ON p.doctor_id = u.id
            LEFT JOIN visits v ON p.visit_id = v.id
            WHERE $pres_where
            ORDER BY p.created_at DESC
        ");
        $stmt_pres->execute($pres_params);
        $prescriptions = $stmt_pres->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($prescriptions)) continue;
        
        // ✅ ITEM CONDITIONS - MEDICATION ONLY (bila consultation)
        $item_conditions = ["pi.patient_id = ?", "p.branch_id = ?", "p.visit_id = ?", "pi.quantity > 0"];
        $item_params = [$patient_id, $user_branch_id, $visit_id];
        
        if ($filter_status === 'all') {
            $item_conditions[] = "p.status IN ('pending', 'confirmed', 'dispensed')";
        } else {
            $item_conditions[] = "p.status = ?";
            $item_params[] = $filter_status;
        }
        
        $item_where = implode(" AND ", $item_conditions);
        
        // ✅ SAHIHI: Hesabu total_price = unit_price × quantity (BILA discount)
        $stmt_items = $db->prepare("
            SELECT pi.*, 
                p.prescription_number, 
                p.status as prescription_status,
                p.created_at as prescription_created_at, 
                p.updated_at as prescription_updated_at,
                p.dispensed_at as prescription_dispensed_at,
                bi.unit_price as bill_unit_price,
                bi.discount_amount as bill_item_discount,
                (COALESCE(bi.unit_price, pi.unit_price, 0) * pi.quantity) as calculated_total_price
            FROM prescription_items pi
            JOIN prescriptions p ON pi.prescription_id = p.id
            LEFT JOIN bill_items bi ON bi.reference_id = p.id 
                AND bi.reference_type = 'prescription' 
                AND bi.item_type = 'medication'
                AND bi.status != 'cancelled'
                AND (bi.item_name LIKE CONCAT('%', pi.medication_name, '%') OR bi.item_name = pi.medication_name)
            WHERE $item_where
            ORDER BY pi.created_at DESC
        ");
        $stmt_items->execute($item_params);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($items)) continue;
        
        $total_quantity = 0;
        $medication_count = 0;
        $prescription_numbers = [];
        $doctor_names = [];
        $prescription_dates = [];
        $prescription_statuses = [];
        
        foreach ($items as $item) {
            $total_quantity += $item['quantity'];
            $medication_count++;
            if (!empty($item['prescription_created_at'])) $prescription_dates[] = $item['prescription_created_at'];
        }
        
        if ($total_quantity <= 0 || $medication_count <= 0) continue;
        
        foreach ($prescriptions as $pres) {
            if (!empty($pres['prescription_number'])) $prescription_numbers[] = $pres['prescription_number'];
            if (!empty($pres['doctor_name'])) $doctor_names[] = $pres['doctor_name'];
            $prescription_statuses[] = $pres['status'];
        }
        
        $patient_status = 'pending';
        $has_pending = in_array('pending', $prescription_statuses);
        $has_confirmed = in_array('confirmed', $prescription_statuses);
        $has_dispensed = in_array('dispensed', $prescription_statuses);
        
        if ($filter_status === 'pending') $patient_status = 'pending';
        elseif ($filter_status === 'confirmed') $patient_status = 'confirmed';
        elseif ($filter_status === 'dispensed') $patient_status = 'dispensed';
        else {
            if ($has_dispensed) $patient_status = 'dispensed';
            elseif ($has_confirmed) $patient_status = 'confirmed';
            else $patient_status = 'pending';
        }
        
        $latest_prescription_date = !empty($prescription_dates) ? max($prescription_dates) : null;
        
        // ================================================================
        // ✅ GET BILL - PHARMACY ONLY
        // ✅ SAHIHI: medication_total = SUM(unit_price × quantity)
        // ================================================================
        $bill_id = null;
        $bill_number = null;
        $bill_status = null;
        $bill_pharmacy_discount = 0;
        $bill_pharmacy_premium = 0;
        $medication_total = 0;
        
        $prescription_ids = array_column($prescriptions, 'id');
        
        if (!empty($prescription_ids)) {
            $placeholders = implode(',', array_fill(0, count($prescription_ids), '?'));
            $stmt_bill_items = $db->prepare("
                SELECT bi.bill_id, 
                    b.bill_number, 
                    b.status as bill_status,
                    b.pharmacy_discount as bill_pharmacy_discount,
                    b.pharmacy_premium as bill_pharmacy_premium,
                    SUM(bi.unit_price * bi.quantity) as medication_total
                FROM bill_items bi
                JOIN bills b ON bi.bill_id = b.id
                WHERE bi.patient_id = ? AND bi.branch_id = ? AND bi.reference_type = 'prescription'
                  AND bi.reference_id IN ($placeholders) AND bi.item_type = 'medication' AND bi.status != 'cancelled'
                GROUP BY bi.bill_id
                ORDER BY b.created_at DESC LIMIT 1
            ");
            $bill_params = [$patient_id, $user_branch_id];
            foreach ($prescription_ids as $pid) $bill_params[] = $pid;
            $stmt_bill_items->execute($bill_params);
            $bill_items_data = $stmt_bill_items->fetch(PDO::FETCH_ASSOC);
            
            if ($bill_items_data && $bill_items_data['bill_id']) {
                $bill_id = $bill_items_data['bill_id'];
                $bill_number = $bill_items_data['bill_number'];
                $bill_status = $bill_items_data['bill_status'] ?? 'pending';
                $medication_total = (float)($bill_items_data['medication_total'] ?? 0);
                
                $bill_pharmacy_discount = (float)($bill_items_data['bill_pharmacy_discount'] ?? 0);
                $bill_pharmacy_premium = (float)($bill_items_data['bill_pharmacy_premium'] ?? 0);
            }
        }
        
        // Kama hakuna bill, hesabu kutoka items (unit_price × quantity)
        if (!$bill_id) {
            $medication_total = 0;
            foreach ($items as $it) {
                $unit_price = (float)($it['bill_unit_price'] ?? $it['unit_price'] ?? 0);
                $medication_total += $unit_price * $it['quantity'];
            }
            $bill_status = 'pending';
            $bill_number = 'Calculated';
        }
        
        $visit_data[] = [
            'visit_id' => $visit_id,
            'visit_number' => $vg['visit_number'] ?? 'N/A',
            'visit_date' => $vg['visit_date'] ?? null,
            'patient_id' => $patient_id,
            'patient_name' => $vg['patient_name'] ?? 'Unknown',
            'patient_code' => $vg['patient_code'] ?? 'N/A',
            'phone' => $vg['phone'] ?? 'N/A',
            'gender' => $vg['gender'] ?? 'N/A',
            'date_of_birth' => $vg['date_of_birth'] ?? null,
            'status' => $patient_status,
            'total_quantity' => $total_quantity,
            'medication_count' => $medication_count,
            'prescription_numbers' => array_unique($prescription_numbers),
            'doctor_names' => array_unique($doctor_names),
            'prescriptions' => $prescriptions,
            'items' => $items,
            'bill_id' => $bill_id,
            'bill_number' => $bill_number,
            'bill_status' => $bill_status,
            'bill_pharmacy_discount' => $bill_pharmacy_discount,
            'bill_pharmacy_premium' => $bill_pharmacy_premium,
            'medication_total' => $medication_total,
            'prescription_count' => count($prescriptions),
            'latest_prescription_date' => $latest_prescription_date,
        ];
    }
    
    $total_visits = count($visit_data);
    
    // STATUS COUNTS
    $status_counts = ['pending' => 0, 'confirmed' => 0, 'dispensed' => 0];
    
    foreach (['pending', 'confirmed', 'dispensed'] as $cs) {
        $count_conditions = ["p.branch_id = ?", "p.status = ?"];
        $count_params = [$user_branch_id, $cs];
        
        if (!empty($search)) {
            $count_conditions[] = "(pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.prescription_number LIKE ?)";
            $count_params[] = "%$search%";
            $count_params[] = "%$search%";
            $count_params[] = "%$search%";
        }
        
        $count_where = implode(" AND ", $count_conditions);
        $stmt_count = $db->prepare("
            SELECT COUNT(DISTINCT CONCAT(p.visit_id, '-', p.patient_id)) as count 
            FROM prescriptions p 
            JOIN patients pat ON p.patient_id = pat.id 
            WHERE $count_where
            AND EXISTS (
                SELECT 1 FROM prescription_items pi 
                WHERE pi.prescription_id = p.id 
                AND pi.quantity > 0
            )
        ");
        $stmt_count->execute($count_params);
        $status_counts[$cs] = (int)($stmt_count->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
    $all_conditions = ["p.branch_id = ?", "p.status IN ('pending', 'confirmed', 'dispensed')"];
    $all_params = [$user_branch_id];
    
    if (!empty($search)) {
        $all_conditions[] = "(pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.prescription_number LIKE ?)";
        $all_params[] = "%$search%";
        $all_params[] = "%$search%";
        $all_params[] = "%$search%";
    }
    
    $all_where = implode(" AND ", $all_conditions);
    $stmt_all = $db->prepare("
        SELECT COUNT(DISTINCT CONCAT(p.visit_id, '-', p.patient_id)) as count 
        FROM prescriptions p 
        JOIN patients pat ON p.patient_id = pat.id 
        WHERE $all_where
        AND EXISTS (
            SELECT 1 FROM prescription_items pi 
            WHERE pi.prescription_id = p.id 
            AND pi.quantity > 0
        )
    ");
    $stmt_all->execute($all_params);
    $total_all_visits = (int)($stmt_all->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $visit_data = [];
    $status_counts = ['pending' => 0, 'confirmed' => 0, 'dispensed' => 0];
    $total_visits = 0;
    $total_all_visits = 0;
}

// HELPERS
function getStatusBadgeClass($status) {
    $map = ['pending' => 'badge-warning', 'confirmed' => 'badge-info', 'dispensed' => 'badge-success', 'cancelled' => 'badge-danger'];
    return $map[$status] ?? 'badge-warning';
}

function getStatusLabel($status) {
    $map = ['pending' => '⏳ Pending', 'confirmed' => '✅ Confirmed', 'dispensed' => '💊 Dispensed', 'cancelled' => '❌ Cancelled'];
    return $map[$status] ?? ucfirst($status);
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    return (new DateTime($dob))->diff(new DateTime('today'))->y;
}

function formatDate($datetime, $showTime = true) {
    if (empty($datetime)) return 'N/A';
    $ts = strtotime($datetime);
    if ($ts === false) return 'N/A';
    return $showTime ? date('d M Y, H:i', $ts) : date('d M Y', $ts);
}

function timeAgo($datetime) {
    if (empty($datetime)) return 'N/A';
    $ts = strtotime($datetime);
    if ($ts === false) return 'N/A';
    $diff = time() - $ts;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) { $m = floor($diff / 60); return $m . ' min' . ($m > 1 ? 's' : '') . ' ago'; }
    if ($diff < 86400) { $h = floor($diff / 3600); return $h . ' hour' . ($h > 1 ? 's' : '') . ' ago'; }
    if ($diff < 604800) { $d = floor($diff / 86400); return $d . ' day' . ($d > 1 ? 's' : '') . ' ago'; }
    return date('d M Y', $ts);
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/pharmacy_header.php';
include_once '../../components/pharmacy_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --font-primary: 'Inter', -apple-system, sans-serif;
            --font-mono: 'JetBrains Mono', 'Courier New', monospace;
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-darker: #083C8A;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --info: #3B82F6;
            --info-bg: #DBEAFE;
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 10px;
            --radius-lg: 14px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --purple-bg: #2D1B4E;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: var(--font-primary);
            background: var(--bg-body);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
        }
        
        .mono, .patient-id, .qty-number, .money, .date-mono, .visit-number-badge, .discount-value, .premium-value {
            font-family: var(--font-mono) !important;
            font-feature-settings: 'tnum';
            font-variant-numeric: tabular-nums;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8, #083C8A);
            border-radius: 16px;
            padding: 20px 28px;
            margin-bottom: 16px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.3);
            position: relative;
            overflow: hidden;
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
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i { font-size: 1.8rem; opacity: 0.9; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            backdrop-filter: blur(4px);
        }
        
        .page-header .branch-tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 7px 14px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.78rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        /* FILTER TABS */
        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 14px;
            padding: 10px 14px;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }
        
        .filter-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 0.72rem;
            font-weight: 700;
            text-decoration: none;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .filter-tab:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
            transform: translateY(-1px);
        }
        
        .filter-tab .tab-count {
            background: var(--gray-200);
            color: var(--text-secondary);
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.62rem;
            font-weight: 800;
            font-family: var(--font-mono);
        }
        
        .filter-tab:hover .tab-count { background: var(--primary); color: white; }
        
        .filter-tab.active {
            border-color: transparent;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .filter-tab.active .tab-count { background: rgba(255,255,255,0.3); color: white; }
        
        .filter-tab.all.active { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .filter-tab.pending.active { background: linear-gradient(135deg, #D97706, #B45309); }
        .filter-tab.confirmed.active { background: linear-gradient(135deg, #3B82F6, #0B5ED7); }
        .filter-tab.dispensed.active { background: linear-gradient(135deg, #059669, #047857); }
        
        .filter-tab .tab-icon { font-size: 0.85rem; }
        
        /* SEARCH TOOLBAR */
        .search-toolbar {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: var(--radius-lg);
            padding: 10px 18px;
            margin-bottom: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.2);
        }
        
        .search-toolbar .toolbar-title {
            color: white;
            font-size: 0.82rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .search-toolbar .search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.25);
            border-radius: 10px;
            padding: 0 14px;
            min-width: 340px;
            height: 36px;
        }
        
        .search-toolbar .search-wrapper:focus-within {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.5);
        }
        
        .search-toolbar .search-wrapper .search-icon {
            color: rgba(255,255,255,0.8);
            font-size: 0.85rem;
            margin-right: 8px;
        }
        
        .search-toolbar .search-wrapper input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: white;
            font-size: 0.8rem;
            font-family: var(--font-primary);
        }
        
        .search-toolbar .search-wrapper input::placeholder { color: rgba(255,255,255,0.6); }
        
        .search-toolbar .search-wrapper .search-clear {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 22px; height: 22px;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            margin-left: 8px;
        }
        
        .search-toolbar .search-wrapper .search-clear.visible { display: flex; }
        
        .search-results-count {
            color: rgba(255,255,255,0.9);
            font-size: 0.68rem;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            padding: 3px 12px;
            border-radius: 12px;
            margin-left: 8px;
            display: none;
            font-family: var(--font-mono);
        }
        
        /* SEARCH INFO BOX */
        .search-info-box {
            display: none;
            margin-bottom: 14px;
            padding: 10px 16px;
            border-radius: 12px;
            background: linear-gradient(135deg, #E8F0FE, #D6E4FF);
            border: 2px solid var(--primary);
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        
        [data-theme="dark"] .search-info-box { background: linear-gradient(135deg, #1E3A5F, #16294A); }
        
        .search-info-box.show { display: flex; }
        
        .search-info-box .info-icon {
            width: 42px; height: 42px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        
        .search-info-box .info-text { flex: 1; min-width: 160px; }
        
        .search-info-box .info-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .search-info-box .info-value {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary);
            font-family: var(--font-mono);
        }
        
        .search-info-box .info-value small {
            font-size: 0.65rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-left: 6px;
            font-family: var(--font-primary);
        }
        
        .search-info-box .info-divider {
            width: 2px;
            height: 38px;
            background: var(--border-color);
        }
        
        /* VISIT CARD */
        .visit-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            margin-bottom: 14px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        
        .visit-card:hover { border-color: var(--primary); box-shadow: var(--shadow-md); }
        
        /* VISIT HEADER */
        .visit-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            padding: 10px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            cursor: pointer;
        }
        
        .visit-header:hover { background: linear-gradient(135deg, #0A4CA8, #083C8A); }
        
        .visit-header .visit-info {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 220px;
        }
        
        .visit-header .patient-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            border: 2px solid rgba(255,255,255,0.4);
            font-family: var(--font-mono);
            flex-shrink: 0;
        }
        
        .visit-header .patient-name {
            font-weight: 700;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .visit-header .visit-meta {
            display: flex;
            gap: 10px;
            font-size: 0.68rem;
            opacity: 0.9;
            flex-wrap: wrap;
            margin-top: 2px;
        }
        
        .visit-header .visit-meta span { display: flex; align-items: center; gap: 3px; }
        
        .visit-header .visit-badge {
            background: linear-gradient(135deg, #FCD34D, #F59E0B);
            color: #78350F;
            padding: 2px 10px;
            border-radius: 8px;
            font-size: 0.6rem;
            font-weight: 800;
            font-family: var(--font-mono);
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .visit-header .visit-date-badge {
            background: rgba(255,255,255,0.25);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 700;
            font-family: var(--font-mono);
            border: 1px solid rgba(255,255,255,0.3);
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .visit-header .visit-stats {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .visit-header .visit-stats .stat-pill {
            background: rgba(255,255,255,0.2);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(255,255,255,0.15);
        }
        
        .visit-header .stat-pill.discount-pill {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.35), rgba(217, 119, 6, 0.35));
            border: 1px solid rgba(245, 158, 11, 0.5);
        }
        
        .visit-header .stat-pill.premium-pill {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.35), rgba(109, 40, 217, 0.35));
            border: 1px solid rgba(124, 58, 237, 0.5);
        }
        
        .visit-header .stat-pill.date-pill {
            background: rgba(255,255,255,0.3);
            border: 1px solid rgba(255,255,255,0.4);
            font-family: var(--font-mono);
        }
        
        .visit-header .stat-pill .qty-value,
        .visit-header .stat-pill .money-value,
        .visit-header .stat-pill .discount-value,
        .visit-header .stat-pill .premium-value {
            font-family: var(--font-mono);
            font-weight: 800;
        }
        
        .visit-header .chevron { font-size: 0.85rem; transition: transform 0.3s ease; }
        .visit-header .chevron.rotated { transform: rotate(180deg); }
        
        .visit-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.3s ease;
        }
        
        .visit-body.open { max-height: 8000px; padding: 12px 16px 14px; }
        
        /* BILL SUMMARY */
        .bill-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 6px;
            padding: 8px 12px;
            background: linear-gradient(135deg, #F8FAFC, #F1F5F9);
            border-radius: 10px;
            border: 1.5px solid var(--primary-light);
            margin-bottom: 10px;
        }
        
        [data-theme="dark"] .bill-summary {
            background: linear-gradient(135deg, #1E293B, #0F172A);
            border-color: #3B82F6;
        }
        
        .bill-summary-item {
            display: flex;
            flex-direction: column;
            gap: 1px;
            text-align: center;
            padding: 3px 6px;
            border-right: 1px solid var(--border-color);
            min-height: 45px;
            justify-content: center;
        }
        
        .bill-summary-item:last-child { border-right: none; }
        
        .bill-summary-item .label {
            font-size: 0.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-secondary);
        }
        
        .bill-summary-item .value {
            font-size: 0.82rem;
            font-weight: 800;
            font-family: var(--font-mono);
            color: var(--primary);
            line-height: 1.1;
        }
        
        .bill-summary-item .value.discount { color: #D97706; }
        .bill-summary-item .value.premium { color: #7C3AED; }
        .bill-summary-item .value.total { color: #059669; }
        
        /* VISIT ACTIONS */
        .visit-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 1.5px dashed var(--border-color);
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        
        .visit-actions .visit-actions-info {
            font-size: 0.72rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .visit-actions .visit-actions-info strong {
            font-family: var(--font-mono);
            font-weight: 800;
            color: var(--text-primary);
        }
        
        .visit-actions .visit-actions-info .info-divider {
            color: var(--border-color);
            font-weight: 300;
        }
        
        .visit-actions .visit-actions-info .info-discount {
            background: var(--warning-bg);
            color: var(--warning);
            padding: 2px 8px;
            border-radius: 8px;
            font-weight: 800;
            font-family: var(--font-mono);
            font-size: 0.65rem;
        }
        
        .visit-actions .visit-actions-info .info-premium {
            background: var(--purple-bg);
            color: var(--purple);
            padding: 2px 8px;
            border-radius: 8px;
            font-weight: 800;
            font-family: var(--font-mono);
            font-size: 0.65rem;
        }
        
        .btn-view-patient {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.68rem;
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            border: none;
            cursor: pointer;
            text-decoration: none;
            min-width: 70px;
        }
        
        .btn-view-patient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 16px rgba(5, 150, 105, 0.5);
        }
        
        /* TABLE */
        .table-scroll { overflow-x: auto; border-radius: 8px; }
        
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.75rem; }
        
        .data-table thead th {
            text-align: left;
            padding: 8px 10px;
            font-weight: 700;
            font-size: 0.6rem;
            text-transform: uppercase;
            color: #ffffff;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
        }
        
        .data-table thead th i { margin-right: 5px; opacity: 0.7; }
        
        .data-table tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            font-size: 0.72rem;
        }
        
        .data-table tbody tr:hover td { background: var(--primary-bg); }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:nth-child(even) td { background: var(--gray-50); }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td { background: #1A1A2E; }
        [data-theme="dark"] .data-table tbody tr:hover td { background: #1E3A5F; }
        
        .date-cell { font-size: 0.68rem; color: var(--text-secondary); white-space: nowrap; }
        .date-cell .date-main { font-weight: 600; color: var(--text-primary); font-family: var(--font-mono); }
        .date-cell .date-time { font-size: 0.6rem; color: var(--text-secondary); display: block; font-family: var(--font-mono); }
        .date-cell .date-ago { font-size: 0.55rem; color: var(--primary); display: block; font-weight: 600; }
        
        .badge-status {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-info { background: var(--info-bg); color: var(--info); border: 1px solid var(--info); }
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        
        [data-theme="dark"] .badge-warning { background: #3A2A1A; color: #F59E0B; border-color: #D97706; }
        [data-theme="dark"] .badge-info { background: #1E3A5F; color: #6EA8FE; border-color: #3B82F6; }
        [data-theme="dark"] .badge-success { background: #1A3A2A; color: #34D399; border-color: #059669; }
        
        .qty-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.68rem;
            font-weight: 700;
            border: 1px solid var(--primary);
            font-family: var(--font-mono);
        }
        
        [data-theme="dark"] .qty-badge { background: #1E3A5F; color: #93C5FD; border-color: #3B82F6; }
        
        .rx-number {
            font-family: var(--font-mono);
            font-size: 0.65rem;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
        }
        
        [data-theme="dark"] .rx-number { background: #1E3A5F; color: #93C5FD; }
        
        .row-number {
            font-family: var(--font-mono);
            text-align: center;
            font-weight: 700;
            color: var(--text-secondary);
            font-size: 0.7rem;
        }
        
        mark.search-highlight {
            background: #FEF08A;
            color: #713F12;
            padding: 0 2px;
            border-radius: 3px;
            font-weight: 700;
        }
        
        [data-theme="dark"] mark.search-highlight { background: #FCD34D; color: #422006; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px dashed var(--border-color);
        }
        
        .empty-state i {
            font-size: 3.5rem;
            color: var(--success);
            display: block;
            margin-bottom: 12px;
        }
        
        .empty-state p { font-size: 1rem; font-weight: 600; color: var(--text-primary); }
        .empty-state .sub { font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px; font-weight: 400; }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 9999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-lg);
            font-size: 0.85rem;
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
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
        
        @media (max-width: 1024px) { 
            .main-content { margin-left: 0; padding: 16px; } 
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 14px 18px; }
            .page-header .page-title { font-size: 1.2rem; }
            .filter-tabs { flex-direction: column; align-items: stretch; }
            .filter-tab { justify-content: center; }
            .search-toolbar { flex-direction: column; align-items: stretch; }
            .search-toolbar .search-wrapper { min-width: 100%; }
            .visit-header { flex-direction: column; align-items: stretch; }
            .visit-actions { flex-direction: column; align-items: stretch; }
            .btn-view-patient { width: 100%; }
            .bill-summary { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription"></i>
                Prescriptions
                <span class="role-badge-display">PHARMACY</span>
            </h1>
            <p class="page-subtitle">
                Medication Prescriptions Only
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-calendar-check"></i> <?= $total_visits ?> Visits
                </span>
                <span class="branch-tag">
                    <i class="fas fa-calendar-alt"></i> <?= date('d M Y') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="prescription_history.php" class="btn-outline-light">
                <i class="fas fa-history"></i> History
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if (!empty($message)): ?>
        <div class="p-3 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800' ?>" style="font-size:0.75rem;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- FILTER TABS -->
    <div class="filter-tabs animate-fade-in-up">
        <a href="?status=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-tab all <?= $filter_status === 'all' ? 'active' : '' ?>">
            <span class="tab-icon">📋</span> All
            <span class="tab-count"><?= $total_all_visits ?></span>
        </a>
        
        <a href="?status=pending<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-tab pending <?= $filter_status === 'pending' ? 'active' : '' ?>">
            <span class="tab-icon">⏳</span> Pending
            <span class="tab-count"><?= $status_counts['pending'] ?? 0 ?></span>
        </a>
        
        <a href="?status=confirmed<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-tab confirmed <?= $filter_status === 'confirmed' ? 'active' : '' ?>">
            <span class="tab-icon">✅</span> Confirmed
            <span class="tab-count"><?= $status_counts['confirmed'] ?? 0 ?></span>
        </a>
        
        <a href="?status=dispensed<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-tab dispensed <?= $filter_status === 'dispensed' ? 'active' : '' ?>">
            <span class="tab-icon">💊</span> Dispensed
            <span class="tab-count"><?= $status_counts['dispensed'] ?? 0 ?></span>
        </a>
        
        <?php if (!empty($search) || $filter_status !== 'all'): ?>
            <a href="pending_prescriptions.php" 
               style="margin-left:auto;padding:6px 14px;border-radius:10px;font-size:0.68rem;font-weight:700;background:transparent;color:var(--danger);border:2px solid var(--danger);text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
                <i class="fas fa-times"></i> Clear
            </a>
        <?php endif; ?>
    </div>

    <!-- SEARCH TOOLBAR -->
    <div class="search-toolbar animate-fade-in-up">
        <div class="toolbar-title">
            <i class="fas fa-search"></i> Search Medicines
            <span style="background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:0.6rem;font-weight:600;" id="headerCount">
                <?= $total_visits ?> Visits
            </span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="tableSearch" placeholder="Search medicine name..." autocomplete="off" value="<?= htmlspecialchars($search) ?>">
                <button type="button" class="search-clear" id="searchClear">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <span class="search-results-count" id="searchResultsCount"></span>
        </div>
    </div>

    <!-- SEARCH INFO BOX -->
    <div class="search-info-box" id="searchInfoBox">
        <div class="info-icon"><i class="fas fa-pills"></i></div>
        <div class="info-text">
            <div class="info-label">Total Quantity for Search</div>
            <div style="font-size:0.72rem;color:var(--text-secondary);">"<strong id="searchTermDisplay"></strong>"</div>
        </div>
        <div class="info-divider"></div>
        <div class="info-text" style="text-align:center;min-width:100px;">
            <div class="info-label">Total Quantity</div>
            <div class="info-value" id="totalQtyDisplay">0 <small>units</small></div>
        </div>
        <div class="info-divider"></div>
        <div class="info-text" style="text-align:center;min-width:90px;">
            <div class="info-label">Visits</div>
            <div class="info-value" id="totalVisitsDisplay">0 <small>visits</small></div>
        </div>
        <div class="info-divider"></div>
        <div class="info-text" style="text-align:center;min-width:90px;">
            <div class="info-label">Prescriptions</div>
            <div class="info-value" id="totalPrescriptionsDisplay">0 <small>items</small></div>
        </div>
    </div>

    <!-- VISITS LIST -->
    <div id="visitsContainer">
        <?php if (count($visit_data) > 0): ?>
            <?php foreach ($visit_data as $visit): 
                $visit_id = $visit['visit_id'];
                $patient_id = $visit['patient_id'];
                $unique_key = 'v' . $visit_id . '_p' . $patient_id;
                $status = $visit['status'] ?? 'pending';
                $status_class = getStatusBadgeClass($status);
                $status_label = getStatusLabel($status);
                $age = calculateAge($visit['date_of_birth'] ?? '');
                $total_qty = $visit['total_quantity'] ?? 0;
                $medication_count = $visit['medication_count'] ?? 0;
                $doctor_names = implode(', ', $visit['doctor_names'] ?? []);
                $prescription_count = $visit['prescription_count'] ?? 0;
                $items = $visit['items'] ?? [];
                $latest_date = $visit['latest_prescription_date'] ?? null;
                $visit_number = $visit['visit_number'] ?? 'N/A';
                $visit_date = $visit['visit_date'] ?? null;
                $bill_number = $visit['bill_number'] ?? null;
                $medication_total = $visit['medication_total'] ?? 0;
                
                $bill_pharm_discount = $visit['bill_pharmacy_discount'] ?? 0;
                $bill_pharm_premium = $visit['bill_pharmacy_premium'] ?? 0;
                
                $view_url = 'view_patient_prescriptions.php';
                $view_label = 'VIEW';
                $view_icon = 'fa-eye';
                $view_title = 'View & Confirm Prescriptions';
                $view_btn_style = '';
                
                if ($status === 'confirmed') {
                    $view_url = 'view_confirmed_prescriptions.php';
                    $view_icon = 'fa-check-circle';
                    $view_title = 'View Confirmed Prescriptions';
                    $view_btn_style = 'background: linear-gradient(135deg, #3B82F6, #0B5ED7);';
                } elseif ($status === 'dispensed') {
                    $view_url = 'view_dispensed_prescriptions.php';
                    $view_icon = 'fa-pills';
                    $view_title = 'View Dispensed Prescriptions';
                    $view_btn_style = 'background: linear-gradient(135deg, #059669, #047857);';
                }
                
                $view_url .= '?patient_id=' . $patient_id . '&visit_id=' . $visit_id;
            ?>
                <div class="visit-card animate-fade-in-up" 
                     data-visit-id="<?= $visit_id ?>" 
                     data-patient-id="<?= $patient_id ?>"
                     data-unique-key="<?= $unique_key ?>"
                     data-status="<?= $status ?>">
                    
                    <div class="visit-header" onclick="toggleVisit('<?= $unique_key ?>')">
                        <div class="visit-info">
                            <div class="patient-avatar" style="background: <?= '#' . substr(md5($visit['patient_name']), 0, 6) ?>;">
                                <?= strtoupper(substr($visit['patient_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="patient-name">
                                    <?= htmlspecialchars($visit['patient_name']) ?>
                                    <span class="visit-badge">
                                        <i class="fas fa-calendar-check"></i>
                                        <?= htmlspecialchars($visit_number) ?>
                                    </span>
                                    <?php if (!empty($visit_date)): ?>
                                        <span class="visit-date-badge">
                                            <i class="fas fa-calendar-day"></i>
                                            <?= date('d M Y', strtotime($visit_date)) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="badge-status <?= $status_class ?>" style="font-size:0.5rem;padding:2px 6px;">
                                        <?= $status_label ?>
                                    </span>
                                </div>
                                <div class="visit-meta">
                                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($visit['patient_code']) ?></span>
                                    <?php if (!empty($visit['phone']) && $visit['phone'] !== 'N/A'): ?>
                                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($visit['phone']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($visit['gender'])): ?>
                                        <span><i class="fas fa-<?= $visit['gender'] === 'Female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($visit['gender']) ?> • <?= $age ?> yrs</span>
                                    <?php endif; ?>
                                    <span><i class="fas fa-prescription"></i> <?= $prescription_count ?> Rx</span>
                                </div>
                            </div>
                        </div>
                        <div class="visit-stats">
                            <?php if (!empty($doctor_names)): ?>
                                <span class="stat-pill">
                                    <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($doctor_names) ?>
                                </span>
                            <?php endif; ?>
                            <span class="stat-pill" style="background:rgba(255,255,255,0.3);">
                                <i class="fas fa-pills"></i> Qty: <span class="qty-value"><?= $total_qty ?></span>
                            </span>
                            
                            <?php if ($bill_pharm_discount > 0): ?>
                                <span class="stat-pill discount-pill" title="Pharmacy Discount">
                                    <i class="fas fa-pills"></i> Pharm Disc: <span class="discount-value">-<?= $currency ?> <?= number_format($bill_pharm_discount, 0) ?></span>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($bill_pharm_premium > 0): ?>
                                <span class="stat-pill premium-pill" title="Pharmacy Premium">
                                    <i class="fas fa-pills"></i> Pharm Prem: <span class="premium-value">+<?= $currency ?> <?= number_format($bill_pharm_premium, 0) ?></span>
                                </span>
                            <?php endif; ?>
                            
                            <span class="stat-pill" style="background:rgba(255,255,255,0.3);">
                                <i class="fas fa-money-bill-wave"></i> <span class="money-value"><?= $currency ?> <?= number_format($medication_total, 0) ?></span>
                            </span>
                            
                            <?php if (!empty($latest_date)): ?>
                                <span class="stat-pill date-pill">
                                    <i class="fas fa-clock"></i> <?= formatDate($latest_date) ?>
                                </span>
                            <?php endif; ?>
                            <i class="fas fa-chevron-down chevron" id="chevron-<?= $unique_key ?>"></i>
                        </div>
                    </div>
                    
                    <div class="visit-body" id="body-<?= $unique_key ?>">
                        
                        <!-- BILL SUMMARY - PHARMACY ONLY -->
                        <div class="bill-summary">
                            <div class="bill-summary-item">
                                <span class="label">Meds Total</span>
                                <span class="value"><?= $currency ?> <?= number_format($medication_total, 0) ?></span>
                            </div>
                            <div class="bill-summary-item">
                                <span class="label">Pharm Discount</span>
                                <span class="value discount">
                                    <?= $bill_pharm_discount > 0 ? '-' . $currency . ' ' . number_format($bill_pharm_discount, 0) : '—' ?>
                                </span>
                            </div>
                            <div class="bill-summary-item">
                                <span class="label">Pharm Premium</span>
                                <span class="value premium">
                                    <?= $bill_pharm_premium > 0 ? '+' . $currency . ' ' . number_format($bill_pharm_premium, 0) : '—' ?>
                                </span>
                            </div>
                            <div class="bill-summary-item">
                                <span class="label">Net Total</span>
                                <span class="value total">
                                    <?php 
                                        $net_total = $medication_total + $bill_pharm_premium - $bill_pharm_discount;
                                        echo $currency . ' ' . number_format($net_total, 0);
                                    ?>
                                </span>
                            </div>
                            <?php if ($bill_number && $bill_number !== 'Calculated'): ?>
                            <div class="bill-summary-item">
                                <span class="label">Bill #</span>
                                <span class="value" style="font-size:0.62rem;color:var(--text-secondary);"><?= htmlspecialchars($bill_number) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="visit-actions">
                            <div class="visit-actions-info">
                                <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                                <strong><?= $medication_count ?></strong> medication(s) • Total Qty: <strong><?= $total_qty ?></strong>
                                
                                <?php if ($bill_pharm_discount > 0): ?>
                                    <span class="info-divider">•</span>
                                    <span class="info-discount">
                                        <i class="fas fa-pills"></i> Pharm Discount: -<?= $currency ?> <?= number_format($bill_pharm_discount, 0) ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($bill_pharm_premium > 0): ?>
                                    <span class="info-divider">•</span>
                                    <span class="info-premium">
                                        <i class="fas fa-pills"></i> Pharm Premium: +<?= $currency ?> <?= number_format($bill_pharm_premium, 0) ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($latest_date)): ?>
                                    <span class="info-divider">•</span>
                                    <i class="fas fa-calendar-alt"></i> Latest: <strong><?= formatDate($latest_date) ?></strong>
                                <?php endif; ?>
                            </div>
                            <a href="<?= $view_url ?>" 
                               class="btn-view-patient" 
                               title="<?= $view_title ?>"
                               style="<?= $view_btn_style ?>">
                                <i class="fas <?= $view_icon ?>"></i> <?= $view_label ?>
                            </a>
                        </div>
                        
                        <div class="table-scroll">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="width:40px;text-align:center;">#</th>
                                        <th><i class="fas fa-pills"></i> Medication</th>
                                        <th style="text-align:center;"><i class="fas fa-sort-numeric-up"></i> Qty</th>
                                        <th style="text-align:right;"><i class="fas fa-coins"></i> Unit Price</th>
                                        <th style="text-align:right;"><i class="fas fa-money-bill"></i> Amount</th>
                                        <th><i class="fas fa-prescription"></i> Dosage</th>
                                        <th><i class="fas fa-prescription"></i> Rx #</th>
                                        <th><i class="fas fa-calendar-alt"></i> Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $i = 1; 
                                    $visit_med_total_calc = 0;
                                    foreach ($items as $item): 
                                        $med_name = $item['medication_name'] ?? 'N/A';
                                        $med_qty = $item['quantity'] ?? 0;
                                        $pres_num = $item['prescription_number'] ?? 'N/A';
                                        $item_date = $item['prescription_created_at'] ?? ($item['created_at'] ?? null);
                                        
                                        // ✅ SAHIHI: unit_price × quantity (BILA discount)
                                        $item_unit_price = (float)($item['bill_unit_price'] ?? $item['unit_price'] ?? 0);
                                        $item_total_price = $item_unit_price * $med_qty;
                                        
                                        $visit_med_total_calc += $item_total_price;
                                    ?>
                                        <tr class="med-row"
                                            data-search="<?= htmlspecialchars(strtolower($med_name . ' ' . $visit['patient_name'] . ' ' . $visit['patient_code'] . ' ' . $pres_num . ' ' . $visit['phone'] . ' ' . $visit_number)) ?>"
                                            data-med-name="<?= htmlspecialchars(strtolower($med_name)) ?>"
                                            data-qty="<?= $med_qty ?>">
                                            <td class="row-number"><?= $i++ ?></td>
                                            <td>
                                                <div style="font-weight:600;font-size:0.78rem;" data-searchable>
                                                    <?= htmlspecialchars($med_name) ?>
                                                </div>
                                            </td>
                                            <td style="text-align:center;">
                                                <span class="qty-badge" data-searchable>
                                                    <?= number_format($med_qty) ?>
                                                </span>
                                            </td>
                                            <td style="text-align:right;font-size:0.72rem;color:var(--text-secondary);font-family:var(--font-mono);" data-searchable>
                                                <?= $currency ?> <?= number_format($item_unit_price, 0) ?>
                                            </td>
                                            <td style="text-align:right;font-weight:700;font-family:var(--font-mono);color:var(--primary);font-size:0.75rem;" data-searchable>
                                                <?= $currency ?> <?= number_format($item_total_price, 0) ?>
                                            </td>
                                            <td style="font-size:0.7rem;" data-searchable><?= htmlspecialchars($item['dosage'] ?? '—') ?></td>
                                            <td>
                                                <span class="rx-number" data-searchable><?= htmlspecialchars($pres_num) ?></span>
                                            </td>
                                            <td class="date-cell" data-searchable>
                                                <?php if (!empty($item_date)): ?>
                                                    <span class="date-main"><?= date('d M Y', strtotime($item_date)) ?></span>
                                                    <span class="date-time"><?= date('H:i', strtotime($item_date)) ?></span>
                                                <?php else: ?>
                                                    <span style="color:var(--text-secondary);">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <!-- ✅ MEDICATION TOTAL ROW - SAHIHI -->
                                    <tr style="background:linear-gradient(135deg, #E8F0FE, #D6E4FF);font-weight:700;border-top:2px solid var(--primary);">
                                        <td colspan="4" style="text-align:right;padding:10px 12px;font-size:0.75rem;color:var(--primary);">
                                            <i class="fas fa-pills"></i> MEDICATION TOTAL:
                                        </td>
                                        <td style="text-align:right;padding:10px 12px;font-size:0.88rem;color:var(--primary);font-family:var(--font-mono);font-weight:800;">
                                            <?= $currency ?> <?= number_format($visit_med_total_calc, 0) ?>
                                        </td>
                                        <td colspan="3"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div id="noSearchResults" style="display:none;">
                <div class="empty-state">
                    <i class="fas fa-search" style="color: var(--primary);"></i>
                    <p>No visits match your search</p>
                    <p class="sub">Try a different keyword</p>
                </div>
            </div>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p>No prescriptions found</p>
                <p class="sub">
                    <?php if (!empty($search)): ?>
                        No results for "<strong><?= htmlspecialchars($search) ?></strong>"
                    <?php elseif ($filter_status === 'pending'): ?>
                        Hakuna pending prescriptions ✅
                    <?php elseif ($filter_status === 'confirmed'): ?>
                        Hakuna confirmed prescriptions
                    <?php elseif ($filter_status === 'dispensed'): ?>
                        Hakuna dispensed prescriptions
                    <?php else: ?>
                        All prescriptions have been processed ✅
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Prescriptions V11 (Medication + Pharmacy Only - FIXED)
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
        </p>
    </footer>

</main>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    // DARK MODE
    var htmlElement = document.documentElement;
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') htmlElement.setAttribute('data-theme', 'dark');
    else if (savedDarkMode === 'false') htmlElement.removeAttribute('data-theme');
    else {
        var cookieDark = document.cookie.match(/dark_mode=([^;]+)/);
        if (cookieDark && cookieDark[1] === 'true') htmlElement.setAttribute('data-theme', 'dark');
    }
    
    // SIDEBAR TOGGLE
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() { sidebar.classList.toggle('open'); });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) sidebar.classList.remove('open');
        }
    });
    
    // TOGGLE VISIT
    function toggleVisit(uniqueKey) {
        var body = document.getElementById('body-' + uniqueKey);
        var chevron = document.getElementById('chevron-' + uniqueKey);
        if (body) body.classList.toggle('open');
        if (chevron) chevron.classList.toggle('rotated');
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        var firstBody = document.querySelector('.visit-body');
        var firstChevron = document.querySelector('.chevron');
        if (firstBody) {
            setTimeout(function() {
                firstBody.classList.add('open');
                if (firstChevron) firstChevron.classList.add('rotated');
            }, 300);
        }
    });
    
    // LIVE SEARCH
    var tableSearch = document.getElementById('tableSearch');
    var searchClear = document.getElementById('searchClear');
    var searchResultsCount = document.getElementById('searchResultsCount');
    var searchInfoBox = document.getElementById('searchInfoBox');
    var searchTermDisplay = document.getElementById('searchTermDisplay');
    var totalQtyDisplay = document.getElementById('totalQtyDisplay');
    var totalVisitsDisplay = document.getElementById('totalVisitsDisplay');
    var totalPrescriptionsDisplay = document.getElementById('totalPrescriptionsDisplay');
    var visitCards = document.querySelectorAll('.visit-card');
    var noSearchResults = document.getElementById('noSearchResults');
    var headerCount = document.getElementById('headerCount');
    
    if (headerCount) headerCount.textContent = visitCards.length + ' Visits';
    
    var originalHTMLMap = new WeakMap();
    
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function escapeRegex(text) { return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
    function numberFormat(num) { return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    
    function highlightText(element, query) {
        if (!element) return;
        if (!originalHTMLMap.has(element)) originalHTMLMap.set(element, element.innerHTML);
        var originalHTML = originalHTMLMap.get(element);
        
        if (!query || query.trim() === '') { element.innerHTML = originalHTML; return; }
        
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = originalHTML;
        var textContent = tempDiv.textContent || tempDiv.innerText || '';
        
        if (!textContent.trim()) { element.innerHTML = originalHTML; return; }
        
        var regex = new RegExp('(' + escapeRegex(query) + ')', 'gi');
        if (!textContent.match(regex)) { element.innerHTML = originalHTML; return; }
        
        element.innerHTML = escapeHtml(textContent).replace(regex, '<mark class="search-highlight">$1</mark>');
    }
    
    function removeAllHighlights() {
        document.querySelectorAll('[data-searchable]').forEach(function(el) {
            if (originalHTMLMap.has(el)) el.innerHTML = originalHTMLMap.get(el);
        });
    }
    
    function calculateSearchTotals(query) {
        if (!query || query.trim() === '') {
            if (searchInfoBox) searchInfoBox.classList.remove('show');
            return;
        }
        
        var lowerQuery = query.toLowerCase().trim();
        var allRows = document.querySelectorAll('.med-row');
        var totalQty = 0;
        var totalVisitsSet = new Set();
        var totalPrescriptions = 0;
        
        allRows.forEach(function(row) {
            var medName = row.dataset.medName || '';
            var qty = parseInt(row.dataset.qty) || 0;
            
            if (medName.indexOf(lowerQuery) !== -1) {
                totalQty += qty;
                totalPrescriptions++;
                var visitCard = row.closest('.visit-card');
                if (visitCard) totalVisitsSet.add(visitCard.dataset.uniqueKey);
            }
        });
        
        var totalVisits = totalVisitsSet.size;
        
        if (searchInfoBox && totalPrescriptions > 0) {
            searchTermDisplay.textContent = query;
            totalQtyDisplay.innerHTML = numberFormat(totalQty) + ' <small>units</small>';
            totalVisitsDisplay.innerHTML = numberFormat(totalVisits) + ' <small>visits</small>';
            totalPrescriptionsDisplay.innerHTML = numberFormat(totalPrescriptions) + ' <small>items</small>';
            searchInfoBox.classList.add('show');
        } else if (searchInfoBox) searchInfoBox.classList.remove('show');
    }
    
    function performLiveSearch() {
        var query = tableSearch.value.toLowerCase().trim();
        
        if (query.length > 0) searchClear.classList.add('visible');
        else searchClear.classList.remove('visible');
        
        removeAllHighlights();
        
        var visibleVisits = 0;
        
        visitCards.forEach(function(card) {
            var medRows = card.querySelectorAll('.med-row');
            var hasMatch = false;
            
            medRows.forEach(function(row) {
                var searchData = row.getAttribute('data-search') || '';
                if (query === '' || searchData.includes(query)) hasMatch = true;
            });
            
            if (hasMatch) {
                card.style.display = '';
                visibleVisits++;
                
                if (query !== '') {
                    var body = card.querySelector('.visit-body');
                    var chevron = card.querySelector('.chevron');
                    if (body && !body.classList.contains('open')) {
                        body.classList.add('open');
                        if (chevron) chevron.classList.add('rotated');
                    }
                }
                
                var visibleRows = 0;
                medRows.forEach(function(row) {
                    var searchData = row.getAttribute('data-search') || '';
                    if (query === '' || searchData.includes(query)) {
                        row.style.display = '';
                        visibleRows++;
                        var rowNum = row.querySelector('.row-number');
                        if (rowNum) rowNum.textContent = visibleRows;
                        if (query !== '') {
                            row.querySelectorAll('[data-searchable]').forEach(function(el) {
                                highlightText(el, query);
                            });
                        }
                    } else {
                        row.style.display = 'none';
                    }
                });
            } else {
                card.style.display = 'none';
            }
        });
        
        calculateSearchTotals(query);
        
        if (visibleVisits === 0 && query !== '') noSearchResults.style.display = '';
        else noSearchResults.style.display = 'none';
        
        if (headerCount) headerCount.textContent = visibleVisits + ' Visits';
        
        if (query !== '') {
            searchResultsCount.textContent = visibleVisits + ' visits found';
            searchResultsCount.style.display = 'inline-block';
        } else {
            searchResultsCount.style.display = 'none';
        }
    }
    
    if (tableSearch) {
        tableSearch.addEventListener('input', performLiveSearch);
        tableSearch.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                tableSearch.value = '';
                performLiveSearch();
                tableSearch.blur();
            }
        });
    }
    
    if (searchClear) {
        searchClear.addEventListener('click', function() {
            tableSearch.value = '';
            performLiveSearch();
            tableSearch.focus();
        });
    }
    
    if (tableSearch && tableSearch.value.trim() !== '') performLiveSearch();
    
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (tableSearch) { tableSearch.focus(); tableSearch.select(); }
        }
    });
    
    // DATE & TIME
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) footerTimestamp.textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // TOAST
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        document.getElementById('toastTitle').textContent = title;
        document.getElementById('toastMessage').textContent = message;
        toast.className = 'toast-custom ' + type;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }
    
    <?php if (!empty($message) && !empty($message_type)): ?>
        setTimeout(function() {
            showToast('<?= $message_type === 'success' ? '✅ Success' : '❌ Error' ?>', 
                '<?= addslashes(strip_tags($message)) ?>', 
                '<?= $message_type ?>');
        }, 500);
    <?php endif; ?>
    
    console.log('%c💊 Braick - Prescriptions V11 (FIXED)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ MEDICATION ONLY (bila consultation)', 'font-size:13px; color:#34D399; font-weight:bold;');
    console.log('%c✅ PHARMACY DISCOUNT/PREMIUM ONLY', 'font-size:13px; color:#7C3AED; font-weight:bold;');
    console.log('%c✅ FIX: bill_items.total_price = qty × unit_price', 'font-size:12px; color:#D97706;');
    console.log('%c✅ FIX: bills.total_amount = medication_total + premium - discount', 'font-size:12px; color:#059669;');
    console.log('%c✅ FIX: Medication Total = SUM(unit_price × quantity)', 'font-size:12px; color:#3B82F6;');
</script>

</body>
</html>