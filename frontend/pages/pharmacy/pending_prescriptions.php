<?php
// ================================================================
// FILE: frontend/pages/pharmacy/pending_prescriptions.php
// PHARMACY - PRESCRIPTIONS (MEDICATION BILLS ONLY)
// ✅ FILTERS FIRST (All, Pending, Confirmed, Dispensed)
// ✅ THEN SEARCH BAR
// ✅ THEN PATIENT TABLES
// ✅ VIEW BUTTON - Small width, "VIEW" text only
// ✅ BLUE THEME
// ✅ TAREHE INAONYESHA - Prescription dates included
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
                    
                    if (empty($items)) {
                        throw new Exception("No items found in this prescription");
                    }
                    
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
                    
                    $discount_calc = 0;
                    if ($discount_percent > 0) {
                        $discount_calc = ($medication_total * $discount_percent) / 100;
                    } elseif ($discount_amount > 0) {
                        $discount_calc = $discount_amount;
                    }
                    
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
                        
                        $stmt_med = $db->prepare("
                            SELECT SUM(total_price) as med_total, COUNT(*) as med_count
                            FROM bill_items
                            WHERE bill_id = ? AND item_type = 'medication' AND status != 'cancelled'
                        ");
                        $stmt_med->execute([$bill_id]);
                        $med_data = $stmt_med->fetch(PDO::FETCH_ASSOC);
                        $med_count = $med_data['med_count'] ?? 0;
                        
                        $stmt_other = $db->prepare("
                            SELECT SUM(total_price) as other_total
                            FROM bill_items
                            WHERE bill_id = ? AND item_type != 'medication' AND status != 'cancelled'
                        ");
                        $stmt_other->execute([$bill_id]);
                        $other_data = $stmt_other->fetch(PDO::FETCH_ASSOC);
                        $other_total = $other_data['other_total'] ?? 0;
                        
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
                        
                        $stmt_new_med = $db->prepare("
                            SELECT SUM(total_price) as med_total, SUM(discount_amount) as med_discount
                            FROM bill_items
                            WHERE bill_id = ? AND item_type = 'medication' AND status != 'cancelled'
                        ");
                        $stmt_new_med->execute([$bill_id]);
                        $new_med_data = $stmt_new_med->fetch(PDO::FETCH_ASSOC);
                        $new_med_total = $new_med_data['med_total'] ?? 0;
                        $new_med_discount = $new_med_data['med_discount'] ?? 0;
                        
                        $new_total = $new_med_total + $other_total;
                        
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
                            $new_total,
                            $discount_calc,
                            $bill_id,
                            $user_branch_id
                        ]);
                        
                        $message = "✅ Prescription confirmed! Bill #{$existing_bill['bill_number']} updated.<br>";
                        $message .= "Medication discount: " . $currency . " " . number_format($discount_calc, 0) . "<br>";
                        $message .= "New bill total: " . $currency . " " . number_format($new_total, 0) . " (was " . $currency . " " . number_format($current_total, 0) . ")";
                        $message_type = 'success';
                        
                    } else {
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
                
                $stmt = $db->prepare("
                    UPDATE prescriptions 
                    SET status = 'dispensed', 
                        dispensed_at = NOW(), 
                        updated_at = NOW(),
                        pharmacy_id = ?
                    WHERE id = ? AND branch_id = ?
                ");
                $stmt->execute([$user_id, $item['prescription_id'], $user_branch_id]);
                
                $stmt = $db->prepare("
                    UPDATE prescription_items 
                    SET dispensed_at = NOW(),
                        dispensed_by = ?
                    WHERE prescription_id = ?
                ");
                $stmt->execute([$user_id, $item['prescription_id']]);
                
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
        
        $stmt_items = $db->prepare("
            SELECT 
                pi.*,
                p.prescription_number,
                p.status as prescription_status,
                p.created_at as prescription_created_at,
                p.updated_at as prescription_updated_at,
                p.dispensed_at as prescription_dispensed_at
            FROM prescription_items pi
            JOIN prescriptions p ON pi.prescription_id = p.id
            WHERE pi.patient_id = ? AND p.branch_id = ?
            AND p.status IN ('pending', 'confirmed', 'dispensed')
            ORDER BY pi.created_at DESC
        ");
        $stmt_items->execute([$patient['patient_id'], $user_branch_id]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
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
        $prescription_dates = [];
        
        foreach ($items as $item) {
            $total_quantity += $item['quantity'];
            $medication_count++;
            
            $med_name = $item['medication_name'];
            if (!isset($unique_medications[$med_name])) {
                $unique_medications[$med_name] = 0;
            }
            $unique_medications[$med_name] += $item['quantity'];
            
            $total_prescription_amount += ($item['unit_price'] ?? 0) * $item['quantity'];
            
            if (!empty($item['prescription_id'])) {
                $all_prescription_ids[] = $item['prescription_id'];
            }
            
            if (!empty($item['prescription_created_at'])) {
                $prescription_dates[] = $item['prescription_created_at'];
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
        
        if ($has_dispensed) {
            $patient_status = 'dispensed';
        } elseif ($has_confirmed) {
            $patient_status = 'confirmed';
        } else {
            $patient_status = 'pending';
        }
        
        // Get the latest prescription date
        $latest_prescription_date = null;
        if (!empty($prescription_dates)) {
            $latest_prescription_date = max($prescription_dates);
        }
        
        // Get bill info
        $bill_id = null;
        $bill_number = null;
        $bill_total = 0;
        $bill_status = null;
        $bill_discount = 0;
        $bill_med_total = 0;
        $bill_other_total = 0;
        
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
        
        if (!$bill_id) {
            $bill_total = $total_prescription_amount;
            $bill_status = 'pending';
            $bill_number = 'Calculated';
        }
        
        $status_counts[$patient_status] = ($status_counts[$patient_status] ?? 0) + 1;
        $total_patients++;
        
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
            'prescription_count' => count($prescriptions),
            'latest_prescription_date' => $latest_prescription_date,
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

/**
 * Format date for display
 * @param string $datetime The datetime string
 * @param bool $showTime Whether to show time
 * @return string Formatted date
 */
function formatDate($datetime, $showTime = true) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return 'N/A';
    }
    
    if ($showTime) {
        return date('d M Y, H:i', $timestamp);
    }
    return date('d M Y', $timestamp);
}

/**
 * Format date short (for badges)
 * @param string $datetime The datetime string
 * @return string Formatted date
 */
function formatDateShort($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return 'N/A';
    }
    
    return date('d/m/Y', $timestamp);
}

/**
 * Get time ago string
 * @param string $datetime The datetime string
 * @return string Time ago
 */
function timeAgo($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return 'N/A';
    }
    
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('d M Y', $timestamp);
    }
}

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
        :root {
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
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* PAGE HEADER */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8, #083C8A);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.3);
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
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i { font-size: 2rem; opacity: 0.9; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
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
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .branch-tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
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
            padding: 8px 16px;
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
        }
        
        .update-badge-light {
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.8);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        /* ================================================================ */
        /* FILTER TABS - ZINAANZA KWANZA */
        /* ================================================================ */
        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 16px;
            padding: 12px 16px;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }
        
        .filter-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 10px;
            font-size: 0.78rem;
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
            font-size: 0.65rem;
            font-weight: 800;
        }
        
        .filter-tab:hover .tab-count {
            background: var(--primary);
            color: white;
        }
        
        .filter-tab.active {
            border-color: transparent;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .filter-tab.active .tab-count {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        
        /* Tab variants */
        .filter-tab.all.active {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        }
        
        .filter-tab.pending.active {
            background: linear-gradient(135deg, #D97706, #B45309);
        }
        
        .filter-tab.confirmed.active {
            background: linear-gradient(135deg, #3B82F6, #0B5ED7);
        }
        
        .filter-tab.dispensed.active {
            background: linear-gradient(135deg, #059669, #047857);
        }
        
        .filter-tab .tab-icon {
            font-size: 0.9rem;
        }
        
        /* ================================================================ */
        /* SEARCH TOOLBAR - INAFUATA BAADA YA FILTERS */
        /* ================================================================ */
        .search-toolbar {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: var(--radius-lg);
            padding: 12px 20px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.2);
        }
        
        .search-toolbar .toolbar-title {
            color: white;
            font-size: 0.9rem;
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
            transition: all 0.3s ease;
            min-width: 380px;
            height: 40px;
        }
        
        .search-toolbar .search-wrapper:focus-within {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.5);
            box-shadow: 0 0 0 3px rgba(255,255,255,0.1);
        }
        
        .search-toolbar .search-wrapper .search-icon {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
            margin-right: 8px;
        }
        
        .search-toolbar .search-wrapper input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: white;
            font-size: 0.85rem;
            padding: 0;
            font-weight: 500;
        }
        
        .search-toolbar .search-wrapper input::placeholder {
            color: rgba(255,255,255,0.6);
        }
        
        .search-toolbar .search-wrapper .search-clear {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            transition: all 0.2s ease;
            margin-left: 8px;
        }
        
        .search-toolbar .search-wrapper .search-clear.visible { display: flex; }
        .search-toolbar .search-wrapper .search-clear:hover { background: rgba(255,255,255,0.35); }
        
        .search-results-count {
            color: rgba(255,255,255,0.9);
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            padding: 3px 12px;
            border-radius: 12px;
            margin-left: 8px;
            white-space: nowrap;
            display: none;
        }
        
        /* SEARCH INFO BOX */
        .search-info-box {
            display: none;
            margin-bottom: 16px;
            padding: 14px 20px;
            border-radius: 12px;
            background: linear-gradient(135deg, #E8F0FE, #D6E4FF);
            border: 2px solid var(--primary);
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            animation: slideDown 0.4s ease;
        }
        
        [data-theme="dark"] .search-info-box {
            background: linear-gradient(135deg, #1E3A5F, #16294A);
        }
        
        .search-info-box.show { display: flex; }
        
        .search-info-box .info-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .search-info-box .info-text {
            flex: 1;
            min-width: 180px;
        }
        
        .search-info-box .info-label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .search-info-box .info-value {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1.1;
        }
        
        .search-info-box .info-value small {
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-left: 6px;
        }
        
        .search-info-box .info-divider {
            width: 2px;
            height: 45px;
            background: var(--border-color);
        }
        
        .search-info-box .info-search-term {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .search-info-box .info-search-term strong {
            color: var(--primary);
            font-weight: 700;
        }
        
        /* PATIENT CARD */
        .patient-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        
        .patient-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .patient-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            padding: 14px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .patient-header:hover {
            background: linear-gradient(135deg, #0A4CA8, #083C8A);
        }
        
        .patient-header .patient-info {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
            min-width: 250px;
        }
        
        .patient-header .patient-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.3rem;
            color: white;
            flex-shrink: 0;
            border: 2px solid rgba(255,255,255,0.4);
            backdrop-filter: blur(4px);
        }
        
        .patient-header .patient-name {
            font-weight: 700;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .patient-header .patient-meta {
            display: flex;
            gap: 14px;
            font-size: 0.75rem;
            opacity: 0.9;
            flex-wrap: wrap;
            margin-top: 3px;
        }
        
        .patient-header .patient-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .patient-header .patient-stats {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .patient-header .patient-stats .stat-pill {
            background: rgba(255,255,255,0.2);
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.15);
        }
        
        /* ✅ DATE PILL IN HEADER */
        .patient-header .stat-pill.date-pill {
            background: rgba(255,255,255,0.3);
            border: 1px solid rgba(255,255,255,0.4);
            font-size: 0.65rem;
        }
        
        .patient-header .chevron {
            font-size: 0.9rem;
            transition: transform 0.3s ease;
        }
        
        .patient-header .chevron.rotated {
            transform: rotate(180deg);
        }
        
        .patient-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.3s ease;
            background: var(--bg-card);
        }
        
        .patient-body.open {
            max-height: 5000px;
            padding: 16px 22px 20px;
        }
        
        /* PATIENT ACTIONS */
        .patient-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding-bottom: 14px;
            border-bottom: 2px dashed var(--border-color);
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        
        .patient-actions .patient-actions-info {
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* ✅ VIEW BUTTON - SMALL WIDTH */
        .btn-view-patient {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 7px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.72rem;
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(5, 150, 105, 0.3);
            text-decoration: none;
            width: auto;
            min-width: 80px;
        }
        
        .btn-view-patient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 16px rgba(5, 150, 105, 0.5);
        }
        
        /* TABLE */
        .table-scroll { overflow-x: auto; border-radius: 10px; }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #ffffff;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
        }
        
        .data-table thead th i { margin-right: 5px; opacity: 0.7; }
        
        .data-table tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td { background: var(--primary-bg); }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:nth-child(even) td { background: var(--gray-50); }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td { background: #1A1A2E; }
        [data-theme="dark"] .data-table tbody tr:hover td { background: #1E3A5F; }
        
        /* ✅ DATE CELL STYLING */
        .date-cell {
            font-size: 0.7rem;
            color: var(--text-secondary);
            white-space: nowrap;
        }
        
        .date-cell .date-main {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .date-cell .date-time {
            font-size: 0.65rem;
            color: var(--text-secondary);
            display: block;
            margin-top: 1px;
        }
        
        .date-cell .date-ago {
            font-size: 0.6rem;
            color: var(--primary);
            display: block;
            margin-top: 1px;
            font-weight: 600;
        }
        
        /* BADGES */
        .badge-status {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-info { background: var(--info-bg); color: var(--info); border: 1px solid var(--info); }
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        
        [data-theme="dark"] .badge-warning { background: #3A2A1A; color: #F59E0B; border-color: #D97706; }
        [data-theme="dark"] .badge-info { background: #1E3A5F; color: #6EA8FE; border-color: #3B82F6; }
        [data-theme="dark"] .badge-success { background: #1A3A2A; color: #34D399; border-color: #059669; }
        [data-theme="dark"] .badge-danger { background: #3A1A1A; color: #F87171; border-color: #DC2626; }
        
        /* QTY BADGE */
        .qty-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            border: 1px solid var(--primary);
        }
        
        [data-theme="dark"] .qty-badge {
            background: #1E3A5F;
            color: #93C5FD;
            border-color: #3B82F6;
        }
        
        /* SEARCH HIGHLIGHT */
        mark.search-highlight {
            background: #FEF08A;
            color: #713F12;
            padding: 0 2px;
            border-radius: 3px;
            font-weight: 700;
            box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.3);
            animation: highlight-pulse 0.6s ease;
        }
        
        [data-theme="dark"] mark.search-highlight {
            background: #FCD34D;
            color: #422006;
        }
        
        @keyframes highlight-pulse {
            0% { background-color: #FDE047; transform: scale(1.05); }
            50% { background-color: #FCD34D; transform: scale(1.1); }
            100% { background-color: #FEF08A; transform: scale(1); }
        }
        
        /* EMPTY STATE */
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
        .empty-state.no-results i { color: var(--primary); }
        
        /* TOAST */
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
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
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
        .toast-custom.warning { background: linear-gradient(135deg, #D97706, #B45309); }
        
        /* FOOTER */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ANIMATIONS */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
        
        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .filter-tabs { flex-direction: column; align-items: stretch; }
            .filter-tab { justify-content: center; }
            .search-toolbar { flex-direction: column; align-items: stretch; }
            .search-toolbar .search-wrapper { min-width: 100%; }
            .patient-header { flex-direction: column; align-items: stretch; }
            .patient-actions { flex-direction: column; align-items: stretch; }
            .btn-view-patient { width: 100%; justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header .page-title { font-size: 1.1rem; }
            .data-table td { padding: 6px 8px; font-size: 0.7rem; }
            .filter-tab { font-size: 0.7rem; padding: 6px 12px; }
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
                <span class="update-badge-light">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                Manage prescriptions (Grouped by Patient)
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="branch-tag" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-users"></i> <?= $total_patients ?> Patients
                </span>
                <span class="branch-tag" style="background:rgba(255,255,255,0.15);">
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
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : ($message_type === 'warning' ? 'bg-yellow-100 text-yellow-700 border border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800') ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ✅ 1. FILTER TABS - ZINAANZA KWANZA -->
    <!-- ================================================================ -->
    <div class="filter-tabs animate-fade-in-up">
        <a href="?status=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-tab all <?= $filter_status === 'all' ? 'active' : '' ?>">
            <span class="tab-icon">📋</span>
            All
            <span class="tab-count"><?= $total_patients ?></span>
        </a>
        
        <a href="?status=pending<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-tab pending <?= $filter_status === 'pending' ? 'active' : '' ?>">
            <span class="tab-icon">⏳</span>
            Pending
            <span class="tab-count"><?= $status_counts['pending'] ?? 0 ?></span>
        </a>
        
        <a href="?status=confirmed<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-tab confirmed <?= $filter_status === 'confirmed' ? 'active' : '' ?>">
            <span class="tab-icon">✅</span>
            Confirmed
            <span class="tab-count"><?= $status_counts['confirmed'] ?? 0 ?></span>
        </a>
        
        <a href="?status=dispensed<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-tab dispensed <?= $filter_status === 'dispensed' ? 'active' : '' ?>">
            <span class="tab-icon">💊</span>
            Dispensed
            <span class="tab-count"><?= $status_counts['dispensed'] ?? 0 ?></span>
        </a>
        
        <?php if (!empty($search) || $filter_status !== 'all'): ?>
            <a href="pending_prescriptions.php" 
               style="margin-left:auto;padding:8px 16px;border-radius:10px;font-size:0.75rem;font-weight:700;background:transparent;color:var(--danger);border:2px solid var(--danger);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-times"></i> Clear All
            </a>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- ✅ 2. SEARCH TOOLBAR - INAFUATA BAADA YA FILTERS -->
    <!-- ================================================================ -->
    <div class="search-toolbar animate-fade-in-up">
        <div class="toolbar-title">
            <i class="fas fa-search"></i>
            Search Medicines
            <span style="background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:0.65rem;font-weight:600;" id="headerCount">
                <?= $total_patients ?> Patients
            </span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" 
                       id="tableSearch" 
                       placeholder="Search medicine name to see total qty..." 
                       autocomplete="off"
                       value="<?= htmlspecialchars($search) ?>">
                <button type="button" class="search-clear" id="searchClear" title="Clear search">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <span class="search-results-count" id="searchResultsCount"></span>
        </div>
    </div>

    <!-- SEARCH INFO BOX - Shows total qty for searched medicine -->
    <div class="search-info-box" id="searchInfoBox">
        <div class="info-icon">
            <i class="fas fa-pills"></i>
        </div>
        <div class="info-text">
            <div class="info-label">Total Quantity for Search</div>
            <div class="info-search-term">
                "<strong id="searchTermDisplay"></strong>"
            </div>
        </div>
        <div class="info-divider"></div>
        <div class="info-text" style="text-align:center;min-width:120px;">
            <div class="info-label">Total Quantity</div>
            <div class="info-value" id="totalQtyDisplay">0 <small>units</small></div>
        </div>
        <div class="info-divider"></div>
        <div class="info-text" style="text-align:center;min-width:100px;">
            <div class="info-label">Patients</div>
            <div class="info-value" id="totalPatientsDisplay">0 <small>patients</small></div>
        </div>
        <div class="info-divider"></div>
        <div class="info-text" style="text-align:center;min-width:100px;">
            <div class="info-label">Prescriptions</div>
            <div class="info-value" id="totalPrescriptionsDisplay">0 <small>items</small></div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ✅ 3. PATIENTS LIST - TABLES ZINAFUATA -->
    <!-- ================================================================ -->
    <div id="patientsContainer">
        <?php if (count($patient_data) > 0): ?>
            <?php foreach ($patient_data as $patient): 
                $patient_id = $patient['patient_id'];
                $status = $patient['status'] ?? 'pending';
                $status_class = getStatusBadgeClass($status);
                $status_label = getStatusLabel($status);
                $age = calculateAge($patient['date_of_birth'] ?? '');
                $total_qty = $patient['total_quantity'] ?? 0;
                $medication_count = $patient['medication_count'] ?? 0;
                $doctor_names = implode(', ', $patient['doctor_names'] ?? []);
                $prescription_count = $patient['prescription_count'] ?? 0;
                $bill_total = $patient['bill_total'] ?? 0;
                $bill_status = $patient['bill_status'] ?? null;
                $bill_discount = $patient['bill_discount'] ?? 0;
                $items = $patient['items'] ?? [];
                $latest_date = $patient['latest_prescription_date'] ?? null;
            ?>
                <div class="patient-card animate-fade-in-up" data-patient-id="<?= $patient_id ?>">
                    <!-- PATIENT HEADER -->
                    <div class="patient-header" onclick="togglePatient(<?= $patient_id ?>)">
                        <div class="patient-info">
                            <div class="patient-avatar" style="background: <?= '#' . substr(md5($patient['patient_name']), 0, 6) ?>;">
                                <?= strtoupper(substr($patient['patient_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="patient-name">
                                    <?= htmlspecialchars($patient['patient_name']) ?>
                                    <span style="background:rgba(255,255,255,0.25);padding:2px 10px;border-radius:12px;font-size:0.6rem;font-weight:600;">
                                        <i class="fas fa-pills"></i> <?= $medication_count ?> Item(s)
                                    </span>
                                    <span class="badge-status <?= $status_class ?>" style="font-size:0.55rem;padding:2px 8px;">
                                        <?= $status_label ?>
                                    </span>
                                </div>
                                <div class="patient-meta">
                                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_code']) ?></span>
                                    <?php if (!empty($patient['phone']) && $patient['phone'] !== 'N/A'): ?>
                                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($patient['gender'])): ?>
                                        <span><i class="fas fa-<?= $patient['gender'] === 'Female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($patient['gender']) ?> • <?= $age ?> yrs</span>
                                    <?php endif; ?>
                                    <span><i class="fas fa-prescription"></i> <?= $prescription_count ?> Rx</span>
                                    <?php if (!empty($latest_date)): ?>
                                        <span><i class="fas fa-calendar-alt"></i> <?= formatDateShort($latest_date) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="patient-stats">
                            <?php if (!empty($doctor_names)): ?>
                                <span class="stat-pill">
                                    <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($doctor_names) ?>
                                </span>
                            <?php endif; ?>
                            <span class="stat-pill" style="background:rgba(255,255,255,0.3);">
                                <i class="fas fa-pills"></i> Qty: <?= $total_qty ?>
                            </span>
                            <span class="stat-pill" style="background:rgba(255,255,255,0.3);">
                                <i class="fas fa-money-bill-wave"></i> <?= $currency ?> <?= number_format($bill_total, 0) ?>
                            </span>
                            <?php if (!empty($latest_date)): ?>
                                <span class="stat-pill date-pill">
                                    <i class="fas fa-calendar-check"></i> <?= formatDate($latest_date, false) ?>
                                </span>
                            <?php endif; ?>
                            <i class="fas fa-chevron-down chevron" id="chevron-<?= $patient_id ?>"></i>
                        </div>
                    </div>
                    
                    <!-- PATIENT BODY -->
                    <div class="patient-body" id="body-<?= $patient_id ?>">
                        
                        <!-- PATIENT ACTIONS -->
                        <div class="patient-actions">
                            <div class="patient-actions-info">
                                <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                                <strong><?= $medication_count ?></strong> medication(s) • Total Qty: <strong><?= $total_qty ?></strong>
                                <?php if ($bill_discount > 0): ?>
                                    • Discount: <strong style="color:var(--success);">-<?= $currency ?> <?= number_format($bill_discount, 0) ?></strong>
                                <?php endif; ?>
                                <?php if (!empty($latest_date)): ?>
                                    • <i class="fas fa-calendar-alt"></i> Latest: <strong><?= formatDate($latest_date) ?></strong>
                                <?php endif; ?>
                            </div>
                            <!-- ✅ VIEW BUTTON - SMALL WIDTH, "VIEW" TEXT ONLY -->
                            <a href="view_patient_prescriptions.php?patient_id=<?= $patient_id ?>" class="btn-view-patient">
                                <i class="fas fa-eye"></i> VIEW
                            </a>
                        </div>
                        
                        <!-- PATIENT MEDICATIONS TABLE -->
                        <div class="table-scroll">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="width:50px;">#</th>
                                        <th><i class="fas fa-pills"></i> Medication</th>
                                        <th><i class="fas fa-sort-numeric-up"></i> Qty</th>
                                        <th><i class="fas fa-prescription"></i> Dosage</th>
                                        <th><i class="fas fa-clock"></i> Frequency</th>
                                        <th><i class="fas fa-route"></i> Route</th>
                                        <th><i class="fas fa-prescription"></i> Rx #</th>
                                        <th><i class="fas fa-calendar-alt"></i> Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; foreach ($items as $item): 
                                        $med_name = $item['medication_name'] ?? 'N/A';
                                        $med_qty = $item['quantity'] ?? 0;
                                        $pres_num = $item['prescription_number'] ?? 'N/A';
                                        $item_date = $item['prescription_created_at'] ?? ($item['created_at'] ?? null);
                                    ?>
                                        <tr class="med-row"
                                            data-search="<?= htmlspecialchars(strtolower($med_name . ' ' . $patient['patient_name'] . ' ' . $patient['patient_code'] . ' ' . $pres_num . ' ' . $patient['phone'] . ' ' . ($item_date ? date('d M Y', strtotime($item_date)) : '') . ' ' . ($item_date ? date('d/m/Y', strtotime($item_date)) : ''))) ?>"
                                            data-med-name="<?= htmlspecialchars(strtolower($med_name)) ?>"
                                            data-qty="<?= $med_qty ?>"
                                            data-date="<?= $item_date ?>">
                                            <td class="row-number"><?= $i++ ?></td>
                                            <td>
                                                <div style="font-weight:600;font-size:0.85rem;" data-searchable>
                                                    <?= htmlspecialchars($med_name) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="qty-badge" data-searchable>
                                                    <i class="fas fa-pills"></i> <?= number_format($med_qty) ?>
                                                </span>
                                            </td>
                                            <td style="font-size:0.75rem;" data-searchable><?= htmlspecialchars($item['dosage'] ?? '—') ?></td>
                                            <td style="font-size:0.75rem;" data-searchable><?= htmlspecialchars($item['frequency'] ?? '—') ?></td>
                                            <td style="font-size:0.75rem;" data-searchable><?= htmlspecialchars($item['route'] ?? '—') ?></td>
                                            <td>
                                                <span style="font-family:'Courier New',monospace;font-size:0.7rem;background:var(--primary-bg);color:var(--primary);padding:2px 8px;border-radius:4px;font-weight:600;" data-searchable>
                                                    <?= htmlspecialchars($pres_num) ?>
                                                </span>
                                            </td>
                                            <!-- ✅ DATE COLUMN -->
                                            <td class="date-cell" data-searchable>
                                                <?php if (!empty($item_date)): ?>
                                                    <span class="date-main"><?= date('d M Y', strtotime($item_date)) ?></span>
                                                    <span class="date-time"><?= date('H:i', strtotime($item_date)) ?></span>
                                                    <span class="date-ago"><?= timeAgo($item_date) ?></span>
                                                <?php else: ?>
                                                    <span style="color:var(--text-secondary);">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- NO SEARCH RESULTS -->
            <div id="noSearchResults" style="display:none;">
                <div class="empty-state no-results">
                    <i class="fas fa-search"></i>
                    <p>No patients match your search</p>
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
                    <?php elseif ($filter_status !== 'all'): ?>
                        No <?= ucfirst($filter_status) ?> prescriptions
                    <?php else: ?>
                        All prescriptions have been processed ✅
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Prescriptions (Grouped by Patient)
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
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
    // TOGGLE PATIENT CARD
    // ================================================================
    function togglePatient(patientId) {
        var body = document.getElementById('body-' + patientId);
        var chevron = document.getElementById('chevron-' + patientId);
        
        if (body) {
            body.classList.toggle('open');
        }
        if (chevron) {
            chevron.classList.toggle('rotated');
        }
    }
    
    // Auto-open first patient card
    document.addEventListener('DOMContentLoaded', function() {
        var firstBody = document.querySelector('.patient-body');
        var firstChevron = document.querySelector('.chevron');
        if (firstBody) {
            setTimeout(function() {
                firstBody.classList.add('open');
                if (firstChevron) firstChevron.classList.add('rotated');
            }, 300);
        }
    });

    // ================================================================
    // LIVE SEARCH WITH TOTAL QTY FOR SPECIFIC MEDICINE
    // ================================================================
    var tableSearch = document.getElementById('tableSearch');
    var searchClear = document.getElementById('searchClear');
    var searchResultsCount = document.getElementById('searchResultsCount');
    var searchInfoBox = document.getElementById('searchInfoBox');
    var searchTermDisplay = document.getElementById('searchTermDisplay');
    var totalQtyDisplay = document.getElementById('totalQtyDisplay');
    var totalPatientsDisplay = document.getElementById('totalPatientsDisplay');
    var totalPrescriptionsDisplay = document.getElementById('totalPrescriptionsDisplay');
    var patientCards = document.querySelectorAll('.patient-card');
    var noSearchResults = document.getElementById('noSearchResults');
    var headerCount = document.getElementById('headerCount');
    
    var totalPatients = patientCards.length;
    if (headerCount) headerCount.textContent = totalPatients + ' Patients';
    
    var originalHTMLMap = new WeakMap();
    
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function escapeRegex(text) {
        return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    function numberFormat(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    function highlightText(element, query) {
        if (!element) return 0;
        
        if (!originalHTMLMap.has(element)) {
            originalHTMLMap.set(element, element.innerHTML);
        }
        
        var originalHTML = originalHTMLMap.get(element);
        
        if (!query || query.trim() === '') {
            element.innerHTML = originalHTML;
            return 0;
        }
        
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = originalHTML;
        var textContent = tempDiv.textContent || tempDiv.innerText || '';
        
        if (!textContent.trim()) {
            element.innerHTML = originalHTML;
            return 0;
        }
        
        var escapedQuery = escapeRegex(query);
        var regex = new RegExp('(' + escapedQuery + ')', 'gi');
        
        var matches = textContent.match(regex);
        var matchCount = matches ? matches.length : 0;
        
        if (matchCount === 0) {
            element.innerHTML = originalHTML;
            return 0;
        }
        
        var escapedText = escapeHtml(textContent);
        var highlightedHTML = escapedText.replace(regex, '<mark class="search-highlight">$1</mark>');
        
        element.innerHTML = highlightedHTML;
        return matchCount;
    }
    
    function removeAllHighlights() {
        document.querySelectorAll('[data-searchable]').forEach(function(el) {
            if (originalHTMLMap.has(el)) {
                el.innerHTML = originalHTMLMap.get(el);
            }
        });
    }
    
    function calculateSearchTotals(query) {
        if (!query || query.trim() === '') {
            if (searchInfoBox) searchInfoBox.classList.remove('show');
            return { totalQty: 0, totalPatients: 0, totalPrescriptions: 0 };
        }
        
        var lowerQuery = query.toLowerCase().trim();
        var allRows = document.querySelectorAll('.med-row');
        var totalQty = 0;
        var totalPatientsSet = new Set();
        var totalPrescriptions = 0;
        
        allRows.forEach(function(row) {
            var medName = row.dataset.medName || '';
            var qty = parseInt(row.dataset.qty) || 0;
            
            if (medName.indexOf(lowerQuery) !== -1) {
                totalQty += qty;
                totalPrescriptions++;
                
                var patientCard = row.closest('.patient-card');
                if (patientCard) {
                    totalPatientsSet.add(patientCard.dataset.patientId);
                }
            }
        });
        
        var totalPatients = totalPatientsSet.size;
        
        if (searchInfoBox && totalPrescriptions > 0) {
            searchTermDisplay.textContent = query;
            totalQtyDisplay.innerHTML = numberFormat(totalQty) + ' <small>units</small>';
            totalPatientsDisplay.innerHTML = numberFormat(totalPatients) + ' <small>patients</small>';
            totalPrescriptionsDisplay.innerHTML = numberFormat(totalPrescriptions) + ' <small>items</small>';
            searchInfoBox.classList.add('show');
        } else if (searchInfoBox) {
            searchInfoBox.classList.remove('show');
        }
        
        return { totalQty: totalQty, totalPatients: totalPatients, totalPrescriptions: totalPrescriptions };
    }
    
    function performLiveSearch() {
        var query = tableSearch.value.toLowerCase().trim();
        
        if (query.length > 0) {
            searchClear.classList.add('visible');
        } else {
            searchClear.classList.remove('visible');
        }
        
        removeAllHighlights();
        
        var visiblePatients = 0;
        
        patientCards.forEach(function(card) {
            var medRows = card.querySelectorAll('.med-row');
            var hasMatch = false;
            
            medRows.forEach(function(row) {
                var searchData = row.getAttribute('data-search') || '';
                if (query === '' || searchData.includes(query)) {
                    hasMatch = true;
                }
            });
            
            if (hasMatch) {
                card.style.display = '';
                visiblePatients++;
                
                if (query !== '') {
                    var body = card.querySelector('.patient-body');
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
                            var searchableElements = row.querySelectorAll('[data-searchable]');
                            searchableElements.forEach(function(el) {
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
        
        if (visiblePatients === 0 && query !== '') {
            noSearchResults.style.display = '';
        } else {
            noSearchResults.style.display = 'none';
        }
        
        if (headerCount) headerCount.textContent = visiblePatients + ' Patients';
        
        if (query !== '') {
            searchResultsCount.textContent = visiblePatients + ' patients found';
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
    
    if (tableSearch && tableSearch.value.trim() !== '') {
        performLiveSearch();
    }
    
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (tableSearch) {
                tableSearch.focus();
                tableSearch.select();
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
        if (footerTimestamp) footerTimestamp.textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    <?php if ($message && $message_type): ?>
        setTimeout(function() {
            showToast('<?= $message_type === 'success' ? '✅ Success' : ($message_type === 'warning' ? '⚠️ Warning' : '❌ Error') ?>', 
                '<?= addslashes(strip_tags($message)) ?>', 
                '<?= $message_type ?>'
            );
        }, 500);
    <?php endif; ?>

    console.log('%c💊 Braick - Prescriptions (Grouped by Patient)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ 1. Filters (All, Pending, Confirmed, Dispensed)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ 2. Search bar - inatafuta dawa na total qty', 'font-size:13px; color:#FCD34D;');
    console.log('%c✅ 3. Patient tables with DATE display', 'font-size:13px; color:#34D399;');
    console.log('%c✅ VIEW button - small width', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>