<?php
// ================================================================
// FILE: frontend/pages/pharmacy/pending_prescriptions.php
// PHARMACY - PRESCRIPTIONS (MEDICATION BILLS ONLY)
// FIXED: Update only medication discount, keep other bill items
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
    // ================================================================
    // GET SYSTEM SETTINGS
    // ================================================================
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
    
    // ================================================================
    // GET FLASH MESSAGES
    // ================================================================
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $message_type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
    
    // ================================================================
    // HANDLE CONFIRM PRESCRIPTION - FIXED
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_prescription') {
        $prescription_id = isset($_POST['prescription_id']) ? (int)$_POST['prescription_id'] : 0;
        $discount_amount = isset($_POST['discount_amount']) ? (float)$_POST['discount_amount'] : 0;
        $discount_percent = isset($_POST['discount_percent']) ? (float)$_POST['discount_percent'] : 0;
        
        if ($prescription_id > 0) {
            try {
                $db->beginTransaction();
                
                // Get prescription details
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
                    // Get prescription items
                    $stmt_items = $db->prepare("SELECT * FROM prescription_items WHERE prescription_id = ?");
                    $stmt_items->execute([$prescription_id]);
                    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($items)) {
                        throw new Exception("No items found in this prescription");
                    }
                    
                    // Calculate medication total from prescription items
                    $medication_total = 0;
                    foreach ($items as $item) {
                        $stmt_price = $db->prepare("
                            SELECT selling_price FROM medications_inventory 
                            WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0
                            ORDER BY created_at DESC LIMIT 1
                        ");
                        $stmt_price->execute([$item['medication_name'], $user_branch_id]);
                        $price_result = $stmt_price->fetch(PDO::FETCH_ASSOC);
                        $unit_price = $price_result['selling_price'] ?? 0;
                        
                        $item_total = $unit_price * $item['quantity'];
                        $medication_total += $item_total;
                        
                        $stmt_update = $db->prepare("
                            UPDATE prescription_items 
                            SET unit_price = ?, total_price = ?
                            WHERE id = ? AND prescription_id = ?
                        ");
                        $stmt_update->execute([$unit_price, $item_total, $item['id'], $prescription_id]);
                    }
                    
                    // Apply discount
                    $discount_calc = 0;
                    if ($discount_percent > 0) {
                        $discount_calc = ($medication_total * $discount_percent) / 100;
                    } elseif ($discount_amount > 0) {
                        $discount_calc = $discount_amount;
                    }
                    
                    // ================================================================
                    // FIND THE BILL FOR THIS PRESCRIPTION
                    // ================================================================
                    $stmt_bill = $db->prepare("
                        SELECT DISTINCT b.id, b.bill_number, b.total_amount, b.subtotal, b.discount_amount
                        FROM bills b
                        JOIN bill_items bi ON b.id = bi.bill_id
                        WHERE bi.reference_id = ? 
                        AND bi.reference_type = 'prescription'
                        AND bi.item_type = 'medication'
                        AND b.status != 'cancelled'
                        LIMIT 1
                    ");
                    $stmt_bill->execute([$prescription_id]);
                    $existing_bill = $stmt_bill->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing_bill) {
                        $bill_id = $existing_bill['id'];
                        $current_total = $existing_bill['total_amount'];
                        $current_discount = $existing_bill['discount_amount'] ?? 0;
                        
                        // ================================================================
                        // GET CURRENT MEDICATION TOTAL FROM BILL_ITEMS
                        // ================================================================
                        $stmt_med = $db->prepare("
                            SELECT SUM(total_price) as med_total, COUNT(*) as med_count
                            FROM bill_items
                            WHERE bill_id = ? AND item_type = 'medication' AND status != 'cancelled'
                        ");
                        $stmt_med->execute([$bill_id]);
                        $med_data = $stmt_med->fetch(PDO::FETCH_ASSOC);
                        $current_med_total = $med_data['med_total'] ?? 0;
                        $med_count = $med_data['med_count'] ?? 0;
                        
                        // ================================================================
                        // GET OTHER ITEMS TOTAL (unchanged)
                        // ================================================================
                        $stmt_other = $db->prepare("
                            SELECT SUM(total_price) as other_total
                            FROM bill_items
                            WHERE bill_id = ? AND item_type != 'medication' AND status != 'cancelled'
                        ");
                        $stmt_other->execute([$bill_id]);
                        $other_data = $stmt_other->fetch(PDO::FETCH_ASSOC);
                        $other_total = $other_data['other_total'] ?? 0;
                        
                        // ================================================================
                        // UPDATE MEDICATION ITEMS WITH DISCOUNT (pro-rata)
                        // ================================================================
                        $discount_per_item = ($discount_calc > 0 && $med_count > 0) 
                            ? $discount_calc / $med_count 
                            : 0;
                        
                        $stmt_update_items = $db->prepare("
                            UPDATE bill_items 
                            SET discount_amount = ?,
                                total_price = total_price - ?,
                                final_price = total_price - ?,
                                updated_at = NOW()
                            WHERE bill_id = ? 
                            AND item_type = 'medication'
                            AND reference_type = 'prescription'
                            AND reference_id = ?
                        ");
                        $stmt_update_items->execute([
                            $discount_per_item,
                            $discount_per_item,
                            $discount_per_item,
                            $bill_id,
                            $prescription_id
                        ]);
                        
                        // ================================================================
                        // GET NEW MEDICATION TOTAL FROM BILL_ITEMS (with discount)
                        // ================================================================
                        $stmt_new_med = $db->prepare("
                            SELECT SUM(total_price) as med_total, SUM(discount_amount) as med_discount
                            FROM bill_items
                            WHERE bill_id = ? AND item_type = 'medication' AND status != 'cancelled'
                        ");
                        $stmt_new_med->execute([$bill_id]);
                        $new_med_data = $stmt_new_med->fetch(PDO::FETCH_ASSOC);
                        $new_med_total = $new_med_data['med_total'] ?? 0;
                        $new_med_discount = $new_med_data['med_discount'] ?? 0;
                        
                        // ================================================================
                        // ✅ FIX: CALCULATE NEW BILL TOTAL
                        // NEW TOTAL = (NEW MEDICATION TOTAL) + OTHER ITEMS
                        // ================================================================
                        $new_total = $new_med_total + $other_total;
                        
                        // ================================================================
                        // ✅ FIX: UPDATE BILL - ONLY CHANGE TOTAL AND DISCOUNT
                        // ================================================================
                        $stmt_update_bill = $db->prepare("
                            UPDATE bills 
                            SET discount_amount = ?,
                                total_amount = ?,
                                balance = ?,
                                updated_at = NOW(),
                                notes = CONCAT(COALESCE(notes, ''), ' | Pharmacy discount: ', ?, ' applied ', NOW())
                            WHERE id = ? AND branch_id = ?
                        ");
                        $stmt_update_bill->execute([
                            $new_med_discount,
                            $new_total,
                            $new_total, // balance = total (not paid yet)
                            $discount_calc,
                            $bill_id,
                            $user_branch_id
                        ]);
                        
                        $message = "✅ Prescription confirmed! Bill #{$existing_bill['bill_number']} updated.<br>";
                        $message .= "Medication discount: " . $currency . " " . number_format($discount_calc, 0) . "<br>";
                        $message .= "New bill total: " . $currency . " " . number_format($new_total, 0) . " (was " . $currency . " " . number_format($current_total, 0) . ")";
                        $message_type = 'success';
                        
                    } else {
                        // ================================================================
                        // CREATE NEW BILL (NO EXISTING BILL)
                        // ================================================================
                        $bill_number = 'BILL-PRES-' . date('Ymd') . '-' . str_pad($prescription['patient_id'], 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                        $final_med_total = $medication_total - $discount_calc;
                        
                        $stmt = $db->prepare("
                            INSERT INTO bills (
                                bill_number, patient_id, visit_id, branch_id, created_by,
                                subtotal, discount_amount, discount_percent, total_amount,
                                paid_amount, balance, status, payment_method, notes,
                                created_at, updated_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'cash', ?, NOW(), NOW())
                        ");
                        $stmt->execute([
                            $bill_number, $prescription['patient_id'], $prescription['visit_id'],
                            $user_branch_id, $user_id, $final_med_total, $discount_calc, $discount_percent, $final_med_total,
                            0, $final_med_total, "Prescription #{$prescription['prescription_number']} - Confirmed"
                        ]);
                        $bill_id = $db->lastInsertId();
                        
                        // Create bill items for each prescription item
                        foreach ($items as $item) {
                            $item_total = ($item['unit_price'] ?? 0) * $item['quantity'];
                            $stmt = $db->prepare("
                                INSERT INTO bill_items (
                                    bill_id, patient_id, branch_id, item_type, item_name,
                                    quantity, unit_price, total_price, discount_amount,
                                    tax_amount, final_price, reference_id, reference_type,
                                    status, created_at, updated_at
                                ) VALUES (?, ?, ?, 'medication', ?, ?, ?, ?, ?, ?, ?, ?, 'prescription', 'pending', NOW(), NOW())
                            ");
                            $stmt->execute([
                                $bill_id, $prescription['patient_id'], $user_branch_id,
                                $item['medication_name'], $item['quantity'],
                                $item['unit_price'] ?? 0, $item_total, 0, 0, $item_total,
                                $prescription_id
                            ]);
                        }
                        
                        $message = "✅ Prescription confirmed! New bill created: #{$bill_number}<br>";
                        $message .= "Total: " . $currency . " " . number_format($final_med_total, 0);
                        $message_type = 'success';
                    }
                    
                    // Update prescription status to confirmed
                    $stmt = $db->prepare("
                        UPDATE prescriptions 
                        SET status = 'confirmed', pharmacy_id = ?, updated_at = NOW()
                        WHERE id = ? AND branch_id = ?
                    ");
                    $stmt->execute([$user_id, $prescription_id, $user_branch_id]);
                    
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
                error_log("Confirm prescription error: " . $e->getMessage());
            }
        }
        
        // Redirect to clear POST
        $redirect_url = 'pending_prescriptions.php';
        if (!empty($_GET)) {
            $params = [];
            if (!empty($_GET['status'])) $params['status'] = $_GET['status'];
            if (!empty($_GET['search'])) $params['search'] = $_GET['search'];
            if (!empty($params)) {
                $redirect_url .= '?' . http_build_query($params);
            }
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
            SELECT 
                p.id as prescription_id,
                p.patient_id,
                p.visit_id,
                p.prescription_number,
                b.id as bill_id,
                b.bill_number
            FROM prescriptions p
            JOIN bills b ON b.visit_id = p.visit_id AND b.patient_id = p.patient_id
            WHERE p.branch_id = ? 
            AND p.status = 'confirmed'
            AND b.status = 'paid'
            AND p.id NOT IN (
                SELECT pi.prescription_id 
                FROM prescription_items pi 
                WHERE pi.prescription_id = p.id AND pi.dispensed_at IS NOT NULL
            )
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
                
                if (empty($items)) {
                    $db->rollBack();
                    continue;
                }
                
                // Check stock
                $stock_errors = [];
                foreach ($items as $pres_item) {
                    $stmt_stock = $db->prepare("
                        SELECT SUM(quantity) as total_available 
                        FROM medications_inventory 
                        WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0
                    ");
                    $stmt_stock->execute([$pres_item['medication_name'], $user_branch_id]);
                    $stock = $stmt_stock->fetch(PDO::FETCH_ASSOC);
                    $available = $stock['total_available'] ?? 0;
                    
                    if ($available < $pres_item['quantity']) {
                        $stock_errors[] = "{$pres_item['medication_name']} - Required: {$pres_item['quantity']}, Available: {$available}";
                    }
                }
                
                if (!empty($stock_errors)) {
                    $db->rollBack();
                    error_log("Auto-dispense stock error: " . implode(', ', $stock_errors));
                    continue;
                }
                
                // Update prescription
                $stmt = $db->prepare("
                    UPDATE prescriptions 
                    SET status = 'dispensed', 
                        dispensed_at = NOW(), 
                        updated_at = NOW(),
                        pharmacy_id = ?
                    WHERE id = ? AND branch_id = ?
                ");
                $stmt->execute([$user_id, $item['prescription_id'], $user_branch_id]);
                
                // Update prescription items
                $stmt = $db->prepare("
                    UPDATE prescription_items 
                    SET dispensed_at = NOW(),
                        dispensed_by = ?
                    WHERE prescription_id = ?
                ");
                $stmt->execute([$user_id, $item['prescription_id']]);
                
                // Update inventory
                foreach ($items as $pres_item) {
                    $needed = $pres_item['quantity'];
                    
                    $stmt_batches = $db->prepare("
                        SELECT id, medication_name, quantity, batch_number, expiry_date
                        FROM medications_inventory 
                        WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0
                        ORDER BY expiry_date ASC
                    ");
                    $stmt_batches->execute([$pres_item['medication_name'], $user_branch_id]);
                    $batches = $stmt_batches->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($batches as $batch) {
                        if ($needed <= 0) break;
                        
                        $deduct = min($needed, $batch['quantity']);
                        $new_qty = $batch['quantity'] - $deduct;
                        
                        $stmt_update = $db->prepare("
                            UPDATE medications_inventory 
                            SET quantity = ?,
                                updated_at = NOW()
                            WHERE id = ? AND branch_id = ?
                        ");
                        $stmt_update->execute([$new_qty, $batch['id'], $user_branch_id]);
                        
                        $stmt_log = $db->prepare("
                            INSERT INTO stock_movements (
                                inventory_id, patient_id, movement_type, quantity,
                                previous_stock, new_stock, reference_type, reference_id,
                                performed_by, branch_id, notes, created_at
                            ) VALUES (?, ?, 'out', ?, ?, ?, 'prescription', ?, ?, ?, ?, NOW())
                        ");
                        $stmt_log->execute([
                            $batch['id'], $item['patient_id'], $deduct,
                            $batch['quantity'], $new_qty,
                            $item['prescription_id'], $user_id, $user_branch_id,
                            "Auto-dispensed from batch {$batch['batch_number']} - Prescription #{$item['prescription_number']}"
                        ]);
                        
                        $needed -= $deduct;
                    }
                }
                
                $db->commit();
                $auto_dispensed_count++;
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Auto-dispense error: " . $e->getMessage());
            }
        }
        
        if ($auto_dispensed_count > 0) {
            $message = "✅ " . $auto_dispensed_count . " prescription(s) auto-dispensed!";
            $message_type = 'success';
        }
        
    } catch (Exception $e) {
        error_log("Auto-dispense check error: " . $e->getMessage());
    }
    
    // ================================================================
    // GET FILTER PARAMETERS
    // ================================================================
    $filter_status = isset($_GET['status']) ? $_GET['status'] : 'pending';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // ================================================================
    // MAIN QUERY - GET PATIENTS WITH PRESCRIPTIONS
    // ================================================================
    $conditions = ["p.branch_id = ?"];
    $params = [$user_branch_id];
    
    if ($filter_status === 'all') {
        $conditions[] = "p.status IN ('pending', 'confirmed', 'dispensed')";
    } elseif ($filter_status === 'dispensed') {
        $conditions[] = "p.status = 'dispensed'";
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
    
    // ================================================================
    // GET DISTINCT PATIENTS WITH PRESCRIPTIONS
    // ================================================================
    $sql = "
        SELECT DISTINCT
            pat.id as patient_id,
            pat.full_name as patient_name,
            pat.patient_id as patient_code,
            pat.phone,
            pat.gender,
            pat.date_of_birth,
            pat.branch_id
        FROM patients pat
        JOIN prescriptions p ON pat.id = p.patient_id AND p.branch_id = ?
        WHERE $where_clause
        ORDER BY pat.full_name ASC
    ";
    
    $full_params = [$user_branch_id];
    foreach ($params as $param) {
        $full_params[] = $param;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($full_params);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET PRESCRIPTIONS, ITEMS, AND BILLS FOR EACH PATIENT
    // ================================================================
    $patient_data = [];
    $status_counts = ['pending' => 0, 'confirmed' => 0, 'dispensed' => 0];
    $total_patients = 0;
    
    foreach ($patients as $patient) {
        // Get all prescriptions for this patient
        $stmt_pres = $db->prepare("
            SELECT 
                p.*,
                u.full_name as doctor_name,
                v.visit_number,
                v.visit_date
            FROM prescriptions p
            LEFT JOIN users u ON p.doctor_id = u.id
            LEFT JOIN visits v ON p.visit_id = v.id
            WHERE p.patient_id = ? AND p.branch_id = ?
            AND p.status IN ('pending', 'confirmed', 'dispensed')
            ORDER BY p.created_at DESC
        ");
        $stmt_pres->execute([$patient['patient_id'], $user_branch_id]);
        $prescriptions = $stmt_pres->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($prescriptions)) {
            continue;
        }
        
        // Get all prescription items for this patient
        $stmt_items = $db->prepare("
            SELECT 
                pi.*,
                p.prescription_number,
                p.status as prescription_status
            FROM prescription_items pi
            JOIN prescriptions p ON pi.prescription_id = p.id
            WHERE pi.patient_id = ? AND p.branch_id = ?
            AND p.status IN ('pending', 'confirmed', 'dispensed')
            ORDER BY pi.created_at DESC
        ");
        $stmt_items->execute([$patient['patient_id'], $user_branch_id]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        // ================================================================
        // CALCULATE TOTALS - GROUP ALL MEDICATIONS
        // ================================================================
        $total_quantity = 0;
        $medication_count = 0;
        $unique_medications = [];
        $total_prescription_amount = 0;
        $prescription_numbers = [];
        $doctor_names = [];
        $visit_numbers = [];
        $patient_status = 'pending';
        $has_confirmed = false;
        $has_dispensed = false;
        $all_prescription_ids = [];
        $prescription_id_list = [];
        
        foreach ($items as $item) {
            $total_quantity += $item['quantity'];
            $medication_count++;
            
            // Track unique medications
            $med_name = $item['medication_name'];
            if (!isset($unique_medications[$med_name])) {
                $unique_medications[$med_name] = 0;
            }
            $unique_medications[$med_name] += $item['quantity'];
            
            // Calculate amount from prescription items only
            $total_prescription_amount += ($item['unit_price'] ?? 0) * $item['quantity'];
            
            // Track prescription IDs
            if (!empty($item['prescription_id'])) {
                $all_prescription_ids[] = $item['prescription_id'];
                $prescription_id_list[] = $item['prescription_id'];
            }
        }
        
        foreach ($prescriptions as $pres) {
            if (!empty($pres['prescription_number'])) {
                $prescription_numbers[] = $pres['prescription_number'];
            }
            if (!empty($pres['doctor_name'])) {
                $doctor_names[] = $pres['doctor_name'];
            }
            if (!empty($pres['visit_number'])) {
                $visit_numbers[] = $pres['visit_number'];
            }
            
            if ($pres['status'] === 'dispensed') $has_dispensed = true;
            if ($pres['status'] === 'confirmed') $has_confirmed = true;
        }
        
        // Determine overall status
        if ($has_dispensed) {
            $patient_status = 'dispensed';
        } elseif ($has_confirmed) {
            $patient_status = 'confirmed';
        } else {
            $patient_status = 'pending';
        }
        
        // ================================================================
        // GET BILL - MEDICATION TOTAL ONLY
        // ================================================================
        $bill_id = null;
        $bill_number = null;
        $bill_total = 0;
        $bill_status = null;
        $bill_discount = 0;
        $bill_med_total = 0;
        $bill_other_total = 0;
        
        // Get prescription IDs for this patient
        $prescription_ids = [];
        foreach ($prescriptions as $pres) {
            $prescription_ids[] = $pres['id'];
        }
        
        if (!empty($prescription_ids)) {
            $placeholders = implode(',', array_fill(0, count($prescription_ids), '?'));
            $stmt_bill_items = $db->prepare("
                SELECT 
                    bi.bill_id,
                    b.bill_number,
                    b.status as bill_status,
                    SUM(CASE WHEN bi.item_type = 'medication' THEN bi.total_price ELSE 0 END) as medication_total,
                    SUM(CASE WHEN bi.item_type != 'medication' THEN bi.total_price ELSE 0 END) as other_total,
                    SUM(CASE WHEN bi.item_type = 'medication' THEN bi.discount_amount ELSE 0 END) as med_discount
                FROM bill_items bi
                JOIN bills b ON bi.bill_id = b.id
                WHERE bi.patient_id = ? 
                AND bi.branch_id = ?
                AND bi.reference_type = 'prescription'
                AND bi.reference_id IN ($placeholders)
                AND bi.status != 'cancelled'
                GROUP BY bi.bill_id
                ORDER BY b.created_at DESC
                LIMIT 1
            ");
            $bill_params = [$patient['patient_id'], $user_branch_id];
            foreach ($prescription_ids as $pid) {
                $bill_params[] = $pid;
            }
            $stmt_bill_items->execute($bill_params);
            $bill_items_data = $stmt_bill_items->fetch(PDO::FETCH_ASSOC);
            
            if ($bill_items_data && $bill_items_data['bill_id']) {
                $bill_id = $bill_items_data['bill_id'];
                $bill_number = $bill_items_data['bill_number'];
                $bill_status = $bill_items_data['bill_status'] ?? 'pending';
                $bill_med_total = $bill_items_data['medication_total'] ?? 0;
                $bill_other_total = $bill_items_data['other_total'] ?? 0;
                $bill_discount = $bill_items_data['med_discount'] ?? 0;
                $bill_total = $bill_med_total + $bill_other_total - $bill_discount;
            }
        }
        
        // If no medication bill found, use calculated amount
        if (!$bill_id) {
            $bill_total = $total_prescription_amount;
            $bill_status = 'pending';
            $bill_number = 'Calculated';
        }
        
        // ================================================================
        // UPDATE STATUS COUNTS
        // ================================================================
        $status_counts[$patient_status] = ($status_counts[$patient_status] ?? 0) + 1;
        $total_patients++;
        
        // ================================================================
        // STORE PATIENT DATA
        // ================================================================
        $patient_data[] = [
            'patient_id' => $patient['patient_id'],
            'patient_name' => $patient['patient_name'] ?? 'Unknown',
            'patient_code' => $patient['patient_code'] ?? 'N/A',
            'phone' => $patient['phone'] ?? 'N/A',
            'gender' => $patient['gender'] ?? 'N/A',
            'date_of_birth' => $patient['date_of_birth'] ?? null,
            'status' => $patient_status,
            'total_quantity' => $total_quantity,
            'medication_count' => $medication_count,
            'unique_medications' => $unique_medications,
            'total_prescription_amount' => $total_prescription_amount,
            'prescription_numbers' => array_unique($prescription_numbers),
            'doctor_names' => array_unique($doctor_names),
            'visit_numbers' => array_unique($visit_numbers),
            'prescriptions' => $prescriptions,
            'items' => $items,
            'bill_id' => $bill_id,
            'bill_number' => $bill_number,
            'bill_total' => $bill_total,
            'bill_status' => $bill_status,
            'bill_discount' => $bill_discount,
            'bill_med_total' => $bill_med_total,
            'bill_other_total' => $bill_other_total,
            'prescription_count' => count($prescriptions),
            'prescription_ids' => $prescription_ids
        ];
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $patient_data = [];
    $status_counts = ['pending' => 0, 'confirmed' => 0, 'dispensed' => 0];
    $total_patients = 0;
    error_log("Prescriptions error: " . $e->getMessage());
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'confirmed' => 'badge-info',
        'dispensed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-warning';
}

function getStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending',
        'confirmed' => '✅ Confirmed',
        'dispensed' => '💊 Dispensed',
        'cancelled' => '❌ Cancelled'
    ];
    return $map[$status] ?? ucfirst($status);
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

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
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
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
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 8px;
            --radius-lg: 12px;
            --shadow: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
            --transition: all 0.3s ease;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --gray-50: #1A1A2E;
            --gray-100: #1E293B;
            --gray-200: #2D3748;
            --gray-300: #4A5568;
            --gray-400: #718096;
            --gray-500: #A0AEC0;
            --gray-600: #CBD5E1;
            --gray-700: #E2E8F0;
            --primary-bg: #1E3A5F;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3A2A1A;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--radius-lg);
            padding: 18px 24px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .page-header .page-title i { font-size: 1.4rem; opacity: 0.9; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.12);
            padding: 5px 12px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.7rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
        }
        
        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(52, 211, 153, 0.15);
            color: #34D399;
            padding: 2px 10px;
            border-radius: 16px;
            font-size: 0.55rem;
            font-weight: 500;
            border: 1px solid rgba(52, 211, 153, 0.2);
        }
        
        .live-update-indicator {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #34D399;
            animation: pulse-dot 1s infinite;
            margin-right: 4px;
        }
        
        .prescription-only-badge {
            background: rgba(251, 191, 36, 0.15);
            color: #FCD34D;
            padding: 2px 10px;
            border-radius: 16px;
            font-size: 0.55rem;
            font-weight: 500;
            border: 1px solid rgba(251, 191, 36, 0.15);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.8); }
        }
        
        /* ================================================================
           STATS ROW
           ================================================================ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            border-radius: var(--radius-lg);
            padding: 12px 14px;
            border: none;
            transition: var(--transition);
            color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            text-decoration: none;
            display: block;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }
        
        .stat-card .stat-number {
            font-size: 1.3rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.6rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.85;
            margin-top: 1px;
        }
        
        .stat-card .stat-icon { font-size: 0.9rem; opacity: 0.8; }
        
        .stat-card.total { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .stat-card.pending { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.confirmed { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.dispensed { background: linear-gradient(135deg, #059669, #047857); }
        
        /* ================================================================
           FILTER SECTION
           ================================================================ */
        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
        
        .filter-btn {
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 0.65rem;
            font-weight: 600;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .filter-btn.dispensed.active {
            background: var(--success);
            border-color: var(--success);
        }
        
        .filter-input {
            padding: 5px 10px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.75rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
            flex: 1;
            min-width: 120px;
        }
        
        .filter-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .btn-search {
            padding: 5px 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.7rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        /* ================================================================
           TABLE - REDUCED COLUMN WIDTH
           ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        
        .table-scroll { overflow-x: auto; }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 8px 10px;
            font-weight: 700;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #ffffff;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .data-table thead th i { margin-right: 4px; opacity: 0.8; }
        .data-table thead th:first-child { text-align: left; }
        .data-table thead th:nth-child(3) { text-align: center; }
        .data-table thead th:nth-child(4) { text-align: center; }
        .data-table thead th:nth-child(5) { text-align: center; width: 80px; }
        .data-table thead th:nth-child(6) { text-align: center; width: 120px; }
        .data-table thead th:last-child { text-align: center; width: 70px; }
        
        .data-table tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        
        .data-table tbody tr:hover td {
            background: var(--primary-bg);
        }
        
        .data-table tbody tr:last-child td { border-bottom: none; }
        
        /* ================================================================
           BADGES - SMALLER
           ================================================================ */
        .badge-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: capitalize;
            white-space: nowrap;
        }
        
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        
        [data-theme="dark"] .badge-warning { background: #3A2A1A; color: #F59E0B; border-color: #D97706; }
        [data-theme="dark"] .badge-info { background: #1E3A5F; color: #6EA8FE; border-color: #3B82F6; }
        [data-theme="dark"] .badge-success { background: #1A3A2A; color: #34D399; border-color: #059669; }
        [data-theme="dark"] .badge-danger { background: #3A1A1A; color: #F87171; border-color: #DC2626; }
        
        /* ================================================================
           MEDICATION LIST - SMALLER
           ================================================================ */
        .medication-list {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            margin-top: 3px;
            justify-content: center;
        }
        
        .med-item {
            background: var(--primary-bg);
            color: var(--primary);
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 0.55rem;
            font-weight: 500;
            border: 1px solid var(--border-color);
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        [data-theme="dark"] .med-item {
            background: #1E3A5F;
            color: #6EA8FE;
            border-color: #334155;
        }
        
        .med-item .qty {
            background: var(--primary);
            color: white;
            padding: 0px 4px;
            border-radius: 2px;
            font-size: 0.45rem;
            font-weight: 700;
        }
        
        [data-theme="dark"] .med-item .qty {
            background: #3B82F6;
        }
        
        /* ================================================================
           BUTTONS - ONLY VIEW
           ================================================================ */
        .btn-view-items {
            background: var(--success);
            color: white;
            padding: 4px 14px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.6rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            width: 100%;
            justify-content: center;
        }
        
        .btn-view-items:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(5, 150, 105, 0.25);
        }
        
        .btn-view-items i { font-size: 0.6rem; }
        
        .action-cell {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
        }
        
        /* ================================================================
           TABLE FOOTER
           ================================================================ */
        .table-footer {
            padding: 6px 12px;
            border-top: 1px solid var(--border-color);
            font-size: 0.6rem;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 4px;
            background: var(--gray-50);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        
        [data-theme="dark"] .table-footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
            background: var(--gray-800);
        }
        
        .count-badge {
            background: var(--primary);
            color: white;
            padding: 1px 8px;
            border-radius: 12px;
            font-size: 0.55rem;
            font-weight: 600;
        }
        
        .count-badge.dispensed {
            background: var(--success);
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 10px;
            z-index: 999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            font-size: 0.8rem;
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 8px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 16px;
            text-align: center;
            font-size: 0.6rem;
            color: var(--text-secondary);
            transition: border-color 0.3s ease;
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        .footer .new-db-footer {
            color: var(--success);
            font-weight: 600;
            font-size: 0.55rem;
        }
        
        .font-mono { font-family: 'Courier New', monospace; }
        .text-center { text-align: center; }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.4s ease forwards; opacity: 0; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 14px; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 14px 16px; }
            .page-header .page-title { font-size: 1.1rem; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-input { width: 100%; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .data-table { font-size: 0.6rem; }
            .data-table thead th, .data-table tbody td { padding: 4px 6px; }
            .data-table thead th { font-size: 0.5rem; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .stats-row { grid-template-columns: 1fr; }
            .data-table { font-size: 0.55rem; }
            .med-item { font-size: 0.45rem; padding: 1px 4px; }
            .btn-view-items { font-size: 0.5rem; padding: 3px 8px; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription"></i>
                Prescriptions
                <span class="role-badge-display">PHARMACY</span>
                <span class="header-badge">
                    <i class="fas fa-list"></i> <span id="totalCount"><?= $total_patients ?></span> Patients
                </span>
                <span class="prescription-only-badge">
                    <i class="fas fa-pills"></i> Medication Bills Only
                </span>
                <span class="live-badge">
                    <span class="live-update-indicator"></span>
                    Live <span id="liveTime" style="font-weight:400;font-size:0.5rem;"><?= date('H:i:s') ?></span>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-arrow-right"></i>
                <span class="header-badge" style="background:rgba(251,191,36,0.15);font-size:0.5rem;">⏳ Pending</span>
                <span class="header-badge" style="background:rgba(96,165,250,0.15);font-size:0.5rem;">✅ Confirmed</span>
                <span class="header-badge" style="background:rgba(52,211,153,0.15);font-size:0.5rem;">💊 Dispensed</span>
                <span class="header-badge" style="background:rgba(251,191,36,0.15);font-size:0.5rem;">
                    <i class="fas fa-user"></i> One row per patient
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.15);font-size:0.5rem;">
                    <i class="fas fa-eye"></i> View Only
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.15);font-size:0.5rem;">
                    <i class="fas fa-pills"></i> Medication Total Only
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <a href="prescription_history.php" class="btn-outline-light">
                <i class="fas fa-history"></i> History
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-3 rounded-lg mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : ($message_type === 'warning' ? 'bg-yellow-100 text-yellow-700 border border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800') ?>" style="max-width:1200px;margin:0 auto 12px;font-size:0.75rem;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- STATS CARDS -->
    <div class="stats-row animate-fade-in-up">
        <a href="?status=all" class="stat-card total <?= $filter_status === 'all' ? 'ring-2 ring-white ring-opacity-50' : '' ?>">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="stat-number" id="statTotal"><?= $total_patients ?></div>
            <div class="stat-label">📋 All Patients</div>
        </a>
        <a href="?status=pending" class="stat-card pending <?= $filter_status === 'pending' ? 'ring-2 ring-white ring-opacity-50' : '' ?>">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number" id="statPending"><?= $status_counts['pending'] ?? 0 ?></div>
            <div class="stat-label">⏳ Pending</div>
        </a>
        <a href="?status=confirmed" class="stat-card confirmed <?= $filter_status === 'confirmed' ? 'ring-2 ring-white ring-opacity-50' : '' ?>">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number" id="statConfirmed"><?= $status_counts['confirmed'] ?? 0 ?></div>
            <div class="stat-label">✅ Confirmed</div>
        </a>
        <a href="?status=dispensed" class="stat-card dispensed <?= $filter_status === 'dispensed' ? 'ring-2 ring-white ring-opacity-50' : '' ?>">
            <div class="stat-icon"><i class="fas fa-prescription-bottle"></i></div>
            <div class="stat-number" id="statDispensed"><?= $status_counts['dispensed'] ?? 0 ?></div>
            <div class="stat-label">💊 Dispensed</div>
        </a>
    </div>

    <!-- FILTERS -->
    <div class="filter-section animate-fade-in-up">
        <div class="filter-row">
            <a href="?status=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>">📋 All</a>
            <a href="?status=pending<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ Pending</a>
            <a href="?status=confirmed<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= $filter_status === 'confirmed' ? 'active' : '' ?>">✅ Confirmed</a>
            <a href="?status=dispensed<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn dispensed <?= $filter_status === 'dispensed' ? 'active' : '' ?>">💊 Dispensed</a>
            
            <div style="flex:1;"></div>
            
            <form method="GET" class="filter-row" style="flex:1;gap:6px;" id="filterForm">
                <input type="hidden" name="status" id="filterStatus" value="<?= htmlspecialchars($filter_status) ?>">
                <input type="text" name="search" class="filter-input" id="searchInput" placeholder="Search patient..." value="<?= htmlspecialchars($search) ?>" style="font-size:0.7rem;padding:4px 8px;">
                <button type="submit" class="btn-search" style="font-size:0.65rem;padding:4px 12px;">
                    <i class="fas fa-search"></i>
                </button>
                <?php if (!empty($search) || $filter_status !== 'all'): ?>
                    <a href="pending_prescriptions.php" class="btn" style="padding:4px 8px;font-size:0.6rem;background:transparent;color:var(--text-secondary);border:1.5px solid var(--border-color);border-radius:var(--radius);text-decoration:none;">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TABLE - ONE ROW PER PATIENT - ONLY VIEW BUTTON -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" id="prescriptionsContainer">
        <div class="table-scroll">
            <table class="data-table" id="prescriptionsTable">
                <thead>
                    <tr>
                        <th style="min-width:140px;width:17%;"><i class="fas fa-receipt"></i> Prescriptions</th>
                        <th style="min-width:150px;width:19%;"><i class="fas fa-user"></i> Patient</th>
                        <th style="text-align:center;min-width:100px;width:17%;"><i class="fas fa-cubes"></i> Medications</th>
                        <th style="text-align:center;min-width:50px;width:8%;"><i class="fas fa-cubes"></i> Qty</th>
                        <th style="text-align:center;min-width:70px;width:10%;"><i class="fas fa-info-circle"></i> Status</th>
                        <th style="text-align:center;min-width:100px;width:15%;"><i class="fas fa-pills"></i> Prescription Bill</th>
                        <th style="text-align:center;min-width:60px;width:10%;"><i class="fas fa-eye"></i> Action</th>
                    </tr>
                </thead>
                <tbody id="prescriptionsTableBody">
                    <?php if (count($patient_data) > 0): ?>
                        <?php foreach ($patient_data as $patient): 
                            $age = calculateAge($patient['date_of_birth'] ?? '');
                            $status = $patient['status'] ?? 'pending';
                            $status_class = getStatusBadgeClass($status);
                            $status_label = getStatusLabel($status);
                            $total_qty = $patient['total_quantity'] ?? 0;
                            $medication_count = $patient['medication_count'] ?? 0;
                            $unique_meds = $patient['unique_medications'] ?? [];
                            $prescription_numbers = implode(', ', $patient['prescription_numbers'] ?? []);
                            $doctor_names = implode(', ', $patient['doctor_names'] ?? []);
                            $visit_numbers = implode(', ', $patient['visit_numbers'] ?? []);
                            $prescription_count = $patient['prescription_count'] ?? 0;
                            
                            // ✅ Use bill_total which is now ONLY medication total
                            $bill_total = $patient['bill_total'] ?? 0;
                            $bill_status = $patient['bill_status'] ?? null;
                            $bill_number = $patient['bill_number'] ?? null;
                            $bill_id = $patient['bill_id'] ?? null;
                            $bill_discount = $patient['bill_discount'] ?? 0;
                            
                            $is_paid = ($bill_status === 'paid');
                            $has_bill = !empty($bill_id);
                        ?>
                            <tr data-patient-id="<?= $patient['patient_id'] ?>" data-status="<?= $status ?>">
                                <td>
                                    <?php if (!empty($prescription_numbers)): ?>
                                        <div class="font-mono font-semibold" style="color:var(--primary);font-size:0.6rem;">
                                            <?= htmlspecialchars($prescription_numbers) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400" style="font-size:0.55rem;">No prescriptions</span>
                                    <?php endif; ?>
                                    <?php if (!empty($visit_numbers)): ?>
                                        <div class="text-xs text-gray-400" style="font-size:0.5rem;">Visits: <?= htmlspecialchars($visit_numbers) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($doctor_names)): ?>
                                        <div class="text-xs text-gray-400" style="font-size:0.5rem;">Dr. <?= htmlspecialchars($doctor_names) ?></div>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-400" style="font-size:0.5rem;"><?= $prescription_count ?> prescription(s)</div>
                                </td>
                                <td>
                                    <div class="font-medium" style="font-size:0.75rem;"><?= htmlspecialchars($patient['patient_name'] ?? 'Unknown') ?></div>
                                    <div class="text-xs" style="color:var(--text-secondary);font-size:0.55rem;">ID: <?= htmlspecialchars($patient['patient_code'] ?? 'N/A') ?></div>
                                    <div class="text-xs" style="color:var(--text-secondary);font-size:0.55rem;">
                                        <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?> • <?= $age ?> yrs
                                    </div>
                                    <?php if (!empty($patient['phone']) && $patient['phone'] !== 'N/A'): ?>
                                        <div class="text-xs" style="color:var(--text-secondary);font-size:0.5rem;">📱 <?= htmlspecialchars($patient['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <div class="medication-list">
                                        <?php 
                                        $med_count = 0;
                                        foreach ($unique_meds as $med_name => $qty): 
                                            $med_count++;
                                            $display_name = strlen($med_name) > 15 ? substr($med_name, 0, 13) . '…' : $med_name;
                                        ?>
                                            <span class="med-item" title="<?= htmlspecialchars($med_name) ?> - <?= $qty ?> units">
                                                <?= htmlspecialchars($display_name) ?>
                                                <span class="qty"><?= $qty ?></span>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="text-xs text-gray-400 mt-1" style="font-size:0.5rem;">
                                        <?= $med_count ?> medication(s)
                                    </div>
                                </td>
                                <td style="text-align:center;">
                                    <span class="font-bold" style="font-size:1rem;color:var(--primary);"><?= $total_qty ?></span>
                                    <div class="text-xs text-gray-400" style="font-size:0.45rem;"><?= $medication_count ?> items</div>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge-status <?= $status_class ?>">
                                        <?= $status_label ?>
                                    </span>
                                    <?php if ($status === 'confirmed' && !$is_paid && $has_bill): ?>
                                        <div class="text-xs" style="color:var(--warning);font-size:0.45rem;">⏳ Wait pay</div>
                                    <?php endif; ?>
                                    <?php if ($status === 'confirmed' && $is_paid): ?>
                                        <div class="text-xs" style="color:var(--success);font-size:0.45rem;">✅ Paid</div>
                                    <?php endif; ?>
                                    <?php if ($status === 'dispensed'): ?>
                                        <div class="text-xs" style="color:var(--success);font-size:0.45rem;">💊 Done</div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($has_bill): ?>
                                        <?php if ($is_paid): ?>
                                            <span class="badge-status badge-success" style="font-size:0.5rem;padding:1px 6px;">✅ Paid</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-warning" style="font-size:0.5rem;padding:1px 6px;">⏳ Pending</span>
                                        <?php endif; ?>
                                        <div class="font-semibold" style="color:var(--primary);font-size:0.75rem;">
                                            <?= $currency ?> <?= number_format($bill_total, 0) ?>
                                        </div>
                                        <?php if ($bill_discount > 0): ?>
                                            <div class="text-xs" style="color:var(--success);font-size:0.45rem;">Disc: -<?= $currency ?> <?= number_format($bill_discount, 0) ?></div>
                                        <?php endif; ?>
                                        <div class="text-xs text-gray-400" style="font-size:0.45rem;"><?= htmlspecialchars($bill_number ?? '') ?></div>
                                    <?php else: ?>
                                        <span class="text-xs" style="color:var(--text-secondary);font-size:0.5rem;">No bill</span>
                                        <?php if ($total_qty > 0): ?>
                                            <div class="text-xs" style="color:var(--text-secondary);font-size:0.45rem;">(<?= $currency ?> <?= number_format($patient['total_prescription_amount'] ?? 0, 0) ?>)</div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <!-- ONLY VIEW BUTTON - NO OTHER BUTTONS -->
                                    <div class="action-cell">
                                        <a href="view_patient_prescriptions.php?patient_id=<?= $patient['patient_id'] ?>" class="btn-view-items">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="text-center py-4" style="color:var(--text-secondary);">
                                    <i class="fas fa-prescription text-2xl block mb-2" style="color:var(--border-color);font-size:1.5rem;"></i>
                                    <p style="font-size:0.75rem;">No patients with prescriptions found</p>
                                    <p class="text-xs mt-1" style="color:var(--text-secondary);font-size:0.65rem;">
                                        <?php if (!empty($search)): ?>
                                            No results for "<strong><?= htmlspecialchars($search) ?></strong>"
                                        <?php elseif ($filter_status !== 'all'): ?>
                                            No <?= ucfirst($filter_status) ?> prescriptions
                                        <?php else: ?>
                                            No patients with prescriptions found
                                        <?php endif; ?>
                                    </p>
                                    <a href="prescription_history.php" class="btn" style="background:var(--primary);color:white;padding:4px 14px;border-radius:4px;text-decoration:none;margin-top:8px;display:inline-flex;align-items:center;gap:4px;font-size:0.65rem;">
                                        <i class="fas fa-history"></i> View History
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="table-footer">
            <span>
                <i class="fas fa-list"></i> Showing <strong id="rowCount"><?= count($patient_data) ?></strong> patients
                <span class="text-xs" style="color:var(--text-secondary);font-size:0.5rem;">
                    <?= $filter_status === 'all' ? '(All)' : '(' . ucfirst($filter_status) . ')' ?>
                </span>
            </span>
            <span>
                <span class="count-badge <?= $filter_status === 'dispensed' ? 'dispensed' : '' ?>" id="totalCountBadge"><?= $total_patients ?></span> Total
                <span class="text-xs" style="color:var(--text-secondary);font-size:0.5rem;" id="updateTimeDisplay">Last update: <?= date('H:i:s') ?></span>
            </span>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Prescriptions
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            <span class="new-db-footer"><i class="fas fa-pills"></i> Medication Bills Only</span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:0.9rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    var htmlElement = document.documentElement;
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
    } else if (savedDarkMode === 'false') {
        htmlElement.removeAttribute('data-theme');
    } else {
        var cookieDark = document.cookie.match(/dark_mode=([^;]+)/);
        if (cookieDark && cookieDark[1] === 'true') {
            htmlElement.setAttribute('data-theme', 'dark');
        }
    }

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
        var liveTime = document.getElementById('liveTime');
        if (liveTime) {
            liveTime.textContent = timeStr;
        }
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
        var status = '<?= $filter_status ?>';
        if (query.length > 0) {
            window.location.href = 'pending_prescriptions.php?search=' + encodeURIComponent(query) + '&status=' + status;
        } else {
            window.location.href = 'pending_prescriptions.php?status=' + status;
        }
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
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
    // AUTO-UPDATE EVERY 10 SECONDS
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    var currentStatusFilter = '<?= $filter_status ?>';
    var currentSearch = '<?= addslashes($search) ?>';

    function fetchPrescriptionsStatus() {
        if (isUpdating) return;
        isUpdating = true;
        
        var formData = new FormData();
        formData.append('action', 'get_prescriptions_status');
        formData.append('branch_id', '<?= $user_branch_id ?>');
        formData.append('filter_status', currentStatusFilter);
        formData.append('search', currentSearch);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                updateUI(data);
            }
            isUpdating = false;
        })
        .catch(function(error) {
            console.error('Update error:', error);
            isUpdating = false;
        });
    }

    function updateUI(data) {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        
        var tbody = document.getElementById('prescriptionsTableBody');
        if (tbody && data.rows_html) {
            tbody.innerHTML = data.rows_html;
        }
        
        var statTotal = document.getElementById('statTotal');
        var statPending = document.getElementById('statPending');
        var statConfirmed = document.getElementById('statConfirmed');
        var statDispensed = document.getElementById('statDispensed');
        var totalCount = document.getElementById('totalCount');
        var totalCountBadge = document.getElementById('totalCountBadge');
        var rowCount = document.getElementById('rowCount');
        var updateTimeDisplay = document.getElementById('updateTimeDisplay');
        
        if (statTotal) statTotal.textContent = data.total_count;
        if (statPending) statPending.textContent = data.pending_count;
        if (statConfirmed) statConfirmed.textContent = data.confirmed_count;
        if (statDispensed) statDispensed.textContent = data.dispensed_count;
        if (totalCount) totalCount.textContent = data.total_count;
        if (totalCountBadge) totalCountBadge.textContent = data.total_count;
        if (rowCount) {
            var rows = tbody ? tbody.querySelectorAll('tr').length : 0;
            rowCount.textContent = rows;
        }
        if (updateTimeDisplay) updateTimeDisplay.textContent = 'Last update: ' + timeStr;
        
        var liveTime = document.getElementById('liveTime');
        if (liveTime) liveTime.textContent = timeStr;
    }

    function startAutoUpdate() {
        if (updateInterval) clearInterval(updateInterval);
        setTimeout(function() {
            fetchPrescriptionsStatus();
        }, 1000);
        updateInterval = setInterval(fetchPrescriptionsStatus, 10000);
        console.log('%c🔄 Prescription auto-update started (every 10s)', 'font-size:12px; color:#34D399;');
    }
    
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            console.log('%c⏹️ Prescription auto-update stopped', 'font-size:12px; color:#DC2626;');
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        if (e.key === 'F5') {
            e.preventDefault();
            window.location.reload();
        }
    });

    // ================================================================
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startAutoUpdate();
        }, 2000);
    });

    <?php if ($message && $message_type): ?>
        setTimeout(function() {
            showToast('<?= $message_type === 'success' ? '✅ Success' : ($message_type === 'warning' ? '⚠️ Warning' : '❌ Error') ?>', 
                '<?= addslashes($message) ?>', 
                '<?= $message_type ?>'
            );
        }, 500);
    <?php endif; ?>

    console.log('%c💊 Braick - Prescriptions (View Only - Medication Total)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 ONE ROW PER PATIENT - Medications grouped together', 'font-size:13px; color:#34D399;');
    console.log('%c💰 Prescription Bill column shows ONLY medication total', 'font-size:13px; color:#D97706;');
    console.log('%c👁️ ONLY View button - No Confirm/Update buttons', 'font-size:13px; color:#6EA8FE;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:12px; color:#059669;');
    console.log('%c📋 Total Patients: <?= count($patient_data) ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c✅ FIXED: Bill total = (Medication total after discount) + Other items', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>