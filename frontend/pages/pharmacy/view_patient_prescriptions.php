<?php
// ================================================================
// FILE: frontend/pages/pharmacy/view_patient_prescriptions.php
// V8.6 - CANCEL WITHOUT STOCK RETURN + EDITABLE QUANTITY
// ================================================================
// ✅ V8.6: Quantity inaweza kuedit (input field)
// ✅ V8.6: Total Price (GROSS) ina green background
// ✅ V8.5: Cancel HAIRUDISHI stock - inapunguza bill tu
// ✅ V8.5: No stock_movements entry
// ✅ V8.5: Cards zinaonyesha maandishi yote (no truncation)
// ✅ V8.3: cancelled_quantity + original_quantity zinahifadhiwa
// ✅ V8.3: Auto-sync prescriptions.status = 'cancelled'
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
$user_phone = $_SESSION['phone'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;

$message = '';
$message_type = '';
$currency = 'TSh';

// ================================================================
// PRE-DEFINED OPTIONS
// ================================================================
$instruction_options = [
    'Take with food', 'Take on empty stomach', 'Take after meals',
    'Take before meals', 'Take with plenty of water', 'Do not crush or chew',
    'Take at bedtime', 'Take in the morning', 'Take with milk',
    'Avoid alcohol', 'Avoid driving', 'Complete full course',
    'Store in a cool dry place', 'Keep out of reach of children',
    'As directed by doctor', 'Other - Please specify'
];

$frequency_options = [
    'Once Daily', 'Twice Daily', 'Three Times Daily', 'Four Times Daily',
    'Every 4 Hours', 'Every 6 Hours', 'Every 8 Hours', 'Every 12 Hours',
    'At Bedtime', 'In the Morning', 'With Meals', 'On Empty Stomach',
    'As Needed', 'Weekly', 'Monthly'
];

$route_options = [
    'Oral', 'Injection', 'Intravenous (IV)', 'Intramuscular (IM)',
    'Subcutaneous (SC)', 'Ophthalmic', 'Otic', 'Nasal',
    'Inhalation', 'Topical', 'Sublingual', 'Rectal', 'Vaginal'
];

$dosage_options = [
    '1', '2', '3', '4', '5', '6', '7', '8', '9', '10',
    '12', '15', '20', '25', '30', '40', '50', '60', '75', '80',
    '100', '120', '125', '150', '180', '200', '225', '250', '300',
    '350', '400', '450', '500', '600', '700', '750', '800', '900',
    '1000', '1200', '1500', '2000', '2500', '3000', '5000'
];

try {
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';

    // ================================================================
    // ✅ V8.6 CANCEL HANDLER - NO STOCK RETURN
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_item') {
        $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
        $patient_id_post = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
        $visit_id_post = isset($_POST['visit_id']) ? (int)$_POST['visit_id'] : 0;

        if ($item_id > 0 && $patient_id_post > 0 && $visit_id_post > 0) {
            try {
                $db->beginTransaction();

                $stmt = $db->prepare("
                    SELECT pi.*, p.prescription_number, p.visit_id, p.status AS rx_status
                    FROM prescription_items pi
                    JOIN prescriptions p ON pi.prescription_id = p.id
                    WHERE pi.id = ?
                      AND pi.patient_id = ?
                      AND p.branch_id = ?
                      AND p.visit_id = ?
                ");
                $stmt->execute([$item_id, $patient_id_post, $user_branch_id, $visit_id_post]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$item) {
                    throw new Exception("Item not found");
                }

                $item_total = (float)$item['total_price'];
                $item_qty = (int)$item['quantity'];
                $item_name = trim($item['medication_name']);
                $was_dispensed = !empty($item['dispensed_at']);
                $rx_status = $item['rx_status'];
                $prescription_id_of_item = (int)$item['prescription_id'];
                $item_dosage = $item['dosage'] ?? '';

                // ================================================================
                // ✅ V8.6: NO STOCK RETURN - Only log the cancellation
                // ================================================================

                // GET BILL
                $stmt_bill = $db->prepare("
                    SELECT id, subtotal, total_amount, balance, paid_amount,
                           discount_amount, total_discount, premium_amount,
                           pharmacy_discount, cashier_discount,
                           pharmacy_premium, cashier_premium
                    FROM bills
                    WHERE patient_id = ? AND visit_id = ? AND branch_id = ?
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt_bill->execute([$patient_id_post, $visit_id_post, $user_branch_id]);
                $bill = $stmt_bill->fetch(PDO::FETCH_ASSOC);

                $bill_info = '';
                if ($bill) {
                    $bill_id = $bill['id'];
                    $current_paid = (float)$bill['paid_amount'];
                    $current_total_discount = (float)$bill['total_discount'];
                    $current_premium = (float)$bill['premium_amount'];

                    $removed_bi_count = 0;
                    $bill_item_name_pattern = $item_name . ' (' . $item_dosage . ')';

                    // Try 1: reference_id = prescription_id AND item_name LIKE
                    $stmt_bi_del1 = $db->prepare("
                        DELETE FROM bill_items
                        WHERE bill_id = ? 
                          AND reference_id = ? 
                          AND reference_type = 'prescription'
                          AND item_name LIKE ?
                        LIMIT 1
                    ");
                    $stmt_bi_del1->execute([$bill_id, $prescription_id_of_item, $bill_item_name_pattern . '%']);
                    $removed_bi_count += $stmt_bi_del1->rowCount();

                    // Try 2: reference_id = prescription_id
                    if ($removed_bi_count === 0) {
                        $stmt_bi_del2 = $db->prepare("
                            DELETE FROM bill_items
                            WHERE bill_id = ? 
                              AND reference_id = ? 
                              AND reference_type = 'prescription'
                            LIMIT 1
                        ");
                        $stmt_bi_del2->execute([$bill_id, $prescription_id_of_item]);
                        $removed_bi_count += $stmt_bi_del2->rowCount();
                    }

                    // Try 3: reference_id = item_id
                    if ($removed_bi_count === 0) {
                        $stmt_bi_del3 = $db->prepare("
                            DELETE FROM bill_items
                            WHERE bill_id = ? 
                              AND reference_id = ? 
                              AND reference_type = 'prescription'
                            LIMIT 1
                        ");
                        $stmt_bi_del3->execute([$bill_id, $item_id]);
                        $removed_bi_count += $stmt_bi_del3->rowCount();
                    }

                    // Try 4: item_name + dosage
                    if ($removed_bi_count === 0) {
                        $stmt_bi_del4 = $db->prepare("
                            DELETE FROM bill_items
                            WHERE bill_id = ? 
                              AND item_type = 'medication'
                              AND item_name LIKE ?
                            LIMIT 1
                        ");
                        $stmt_bi_del4->execute([$bill_id, $bill_item_name_pattern . '%']);
                        $removed_bi_count += $stmt_bi_del4->rowCount();
                    }

                    // Try 5: item_name only
                    if ($removed_bi_count === 0) {
                        $stmt_bi_del5 = $db->prepare("
                            DELETE FROM bill_items
                            WHERE bill_id = ? 
                              AND item_type = 'medication'
                              AND item_name LIKE ?
                            LIMIT 1
                        ");
                        $stmt_bi_del5->execute([$bill_id, $item_name . '%']);
                        $removed_bi_count += $stmt_bi_del5->rowCount();
                    }

                    // Recalculate subtotal
                    $stmt_med = $db->prepare("
                        SELECT SUM(total_price) as med_total, COUNT(*) as med_count
                        FROM bill_items
                        WHERE bill_id = ? AND item_type = 'medication' AND status != 'cancelled'
                    ");
                    $stmt_med->execute([$bill_id]);
                    $med_data = $stmt_med->fetch(PDO::FETCH_ASSOC);
                    $med_total = (float)($med_data['med_total'] ?? 0);

                    $stmt_other = $db->prepare("
                        SELECT SUM(total_price) as other_total
                        FROM bill_items
                        WHERE bill_id = ? AND item_type != 'medication' AND status != 'cancelled'
                    ");
                    $stmt_other->execute([$bill_id]);
                    $other_total = (float)($stmt_other->fetch(PDO::FETCH_ASSOC)['other_total'] ?? 0);

                    $new_subtotal = $med_total + $other_total;
                    $new_total_amount = $new_subtotal + $current_premium - $current_total_discount;
                    if ($new_total_amount < 0) $new_total_amount = 0;

                    $new_balance = $new_total_amount - $current_paid;
                    if ($new_balance < 0) $new_balance = 0;

                    if ($new_total_amount <= 0 && $new_subtotal <= 0) {
                        $new_status = 'cancelled';
                    } elseif ($new_balance <= 0 && $new_total_amount > 0) {
                        $new_status = 'paid';
                    } elseif ($current_paid > 0) {
                        $new_status = 'partial';
                    } else {
                        $new_status = 'pending';
                    }

                    $stmt_update = $db->prepare("
                        UPDATE bills
                        SET subtotal = ?,
                            total_amount = ?,
                            balance = ?,
                            status = ?,
                            notes = CONCAT(
                                COALESCE(notes, ''),
                                ' | CANCELLED BY ', ?, ': ', ?, ' (Qty: ', ?, ') -', ?, ' at ', NOW()
                            ),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt_update->execute([
                        $new_subtotal,
                        $new_total_amount,
                        $new_balance,
                        $new_status,
                        $user_full_name,
                        $item_name,
                        $item_qty,
                        number_format($item_total, 0),
                        $bill_id
                    ]);

                    $bill_info = " | Bill: {$currency} " . number_format($new_subtotal, 0)
                               . " | removed: {$removed_bi_count}";
                }

                // ✅ V8.6: Store cancelled quantity (NOT delete it)
                $stmt_cancel_log = $db->prepare("
                    UPDATE prescription_items
                    SET cancelled_by = ?,
                        cancelled_at = NOW(),
                        cancelled_quantity = quantity,
                        original_quantity = CASE 
                            WHEN original_quantity > 0 THEN original_quantity 
                            ELSE quantity 
                        END,
                        quantity = 0
                    WHERE id = ? AND patient_id = ? AND branch_id = ?
                ");
                $stmt_cancel_log->execute([
                    $user_id,
                    $item_id,
                    $patient_id_post,
                    $user_branch_id
                ]);

                // Auto-sync prescriptions.status
                $stmt_check_pres = $db->prepare("
                    SELECT COUNT(*) as active_count
                    FROM prescription_items
                    WHERE prescription_id = ?
                      AND cancelled_at IS NULL
                      AND quantity > 0
                ");
                $stmt_check_pres->execute([$prescription_id_of_item]);
                $active_count = (int)($stmt_check_pres->fetch(PDO::FETCH_ASSOC)['active_count'] ?? 0);

                if ($active_count === 0) {
                    $stmt_update_pres = $db->prepare("
                        UPDATE prescriptions
                        SET status = 'cancelled', updated_at = NOW()
                        WHERE id = ? AND branch_id = ?
                    ");
                    $stmt_update_pres->execute([$prescription_id_of_item, $user_branch_id]);
                }

                // Update OTHER prescriptions in this visit
                $stmt_check_all_rx = $db->prepare("
                    SELECT p.id 
                    FROM prescriptions p
                    WHERE p.patient_id = ?
                      AND p.branch_id = ?
                      AND p.visit_id = ?
                      AND p.status IN ('pending', 'confirmed', 'dispensed')
                      AND NOT EXISTS (
                          SELECT 1 FROM prescription_items pi 
                          WHERE pi.prescription_id = p.id 
                          AND pi.cancelled_at IS NULL 
                          AND pi.quantity > 0
                      )
                ");
                $stmt_check_all_rx->execute([$patient_id_post, $user_branch_id, $visit_id_post]);
                $empty_rx_list = $stmt_check_all_rx->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($empty_rx_list)) {
                    $placeholders = implode(',', array_fill(0, count($empty_rx_list), '?'));
                    $stmt_bulk_cancel = $db->prepare("
                        UPDATE prescriptions
                        SET status = 'cancelled', updated_at = NOW()
                        WHERE id IN ($placeholders) AND branch_id = ?
                    ");
                    $params_cancel = array_merge($empty_rx_list, [$user_branch_id]);
                    $stmt_bulk_cancel->execute($params_cancel);
                }

                $db->commit();

                $_SESSION['flash_message'] = "✅ Medication cancelled by {$user_full_name}: {$item_name} (Qty: {$item_qty}){$bill_info}";
                $_SESSION['flash_type'] = 'success';

                header("Location: view_patient_prescriptions.php?patient_id={$patient_id_post}&visit_id={$visit_id_post}");
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }

    // ================================================================
    // SAVE AND CONFIRM HANDLER (with QUANTITY EDIT support)
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_and_confirm') {
        $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
        $visit_id = isset($_POST['visit_id']) ? (int)$_POST['visit_id'] : 0;
        $premium_amount = isset($_POST['premium_amount']) ? (float)str_replace(',', '', $_POST['premium_amount']) : 0;
        $discount_amount = isset($_POST['discount_amount']) ? (float)str_replace(',', '', $_POST['discount_amount']) : 0;

        if (isset($_POST['items'])) {
            foreach ($_POST['items'] as $item_id => $item_data) {
                $dosage = trim($item_data['dosage'] ?? '');
                $frequency = trim($item_data['frequency'] ?? '');
                $route = trim($item_data['route'] ?? '');
                $duration = trim($item_data['duration'] ?? '');
                $instructions = trim($item_data['instructions'] ?? '');

                // ✅ V8.6: Quantity editable
                $new_quantity = isset($item_data['quantity']) ? (int)$item_data['quantity'] : 0;
                if ($new_quantity < 1) $new_quantity = 1;
                if ($new_quantity > 99999) $new_quantity = 99999;

                // Get current unit_price to recalculate total_price
                $stmt_price = $db->prepare("
                    SELECT unit_price FROM prescription_items 
                    WHERE id = ? AND patient_id = ? AND branch_id = ?
                ");
                $stmt_price->execute([$item_id, $patient_id, $user_branch_id]);
                $price_row = $stmt_price->fetch(PDO::FETCH_ASSOC);
                $unit_price = (float)($price_row['unit_price'] ?? 0);
                $new_total_price = $unit_price * $new_quantity;

                $stmt = $db->prepare("
                    UPDATE prescription_items
                    SET dosage = ?, frequency = ?, route = ?, duration = ?, instructions = ?,
                        quantity = ?, total_price = ?
                    WHERE id = ? AND patient_id = ? AND branch_id = ?
                      AND cancelled_at IS NULL
                ");
                $stmt->execute([
                    $dosage, $frequency, $route, $duration, $instructions,
                    $new_quantity, $new_total_price,
                    $item_id, $patient_id, $user_branch_id
                ]);
            }
        }

        if ($patient_id > 0 && $visit_id > 0) {
            try {
                $db->beginTransaction();

                $stmt = $db->prepare("
                    SELECT id, prescription_number, visit_id, status
                    FROM prescriptions
                    WHERE patient_id = ?
                      AND branch_id = ?
                      AND visit_id = ?
                      AND status IN ('pending', 'confirmed', 'dispensed')
                ");
                $stmt->execute([$patient_id, $user_branch_id, $visit_id]);
                $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($prescriptions)) {
                    throw new Exception("No prescriptions found for this visit");
                }

                $stmt = $db->prepare("
                    UPDATE prescriptions
                    SET status = 'confirmed', pharmacy_id = ?, updated_at = NOW()
                    WHERE patient_id = ?
                      AND branch_id = ?
                      AND visit_id = ?
                      AND status = 'pending'
                ");
                $stmt->execute([$user_id, $patient_id, $user_branch_id, $visit_id]);

                $stmt_confirm_items = $db->prepare("
                    UPDATE prescription_items pi
                    JOIN prescriptions p ON pi.prescription_id = p.id
                    SET pi.confirmed_by = ?,
                        pi.confirmed_at = NOW()
                    WHERE pi.patient_id = ?
                      AND p.branch_id = ?
                      AND p.visit_id = ?
                      AND pi.cancelled_at IS NULL
                      AND pi.confirmed_at IS NULL
                ");
                $stmt_confirm_items->execute([
                    $user_id,
                    $patient_id,
                    $user_branch_id,
                    $visit_id
                ]);

                $stmt = $db->prepare("
                    SELECT id, total_amount, paid_amount, balance, discount_amount,
                           total_discount, subtotal, premium_amount, premium_note,
                           pharmacy_discount, cashier_discount,
                           pharmacy_premium, cashier_premium,
                           pharmacy_premium_note, cashier_premium_note
                    FROM bills
                    WHERE patient_id = ?
                      AND visit_id = ?
                      AND branch_id = ?
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$patient_id, $visit_id, $user_branch_id]);
                $existing_bill = $stmt->fetch(PDO::FETCH_ASSOC);

                $bill_id = null;

                if ($existing_bill) {
                    $bill_id = $existing_bill['id'];
                    $current_paid = (float)$existing_bill['paid_amount'];
                    $current_total_discount = (float)$existing_bill['total_discount'];
                    $current_premium = (float)($existing_bill['premium_amount'] ?? 0);
                    $current_pharmacy_discount = (float)($existing_bill['pharmacy_discount'] ?? 0);
                    $current_pharmacy_premium  = (float)($existing_bill['pharmacy_premium'] ?? 0);

                    $stmt_med = $db->prepare("
                        SELECT SUM(pi.total_price) as med_total, COUNT(*) as med_count
                        FROM prescription_items pi
                        JOIN prescriptions p ON pi.prescription_id = p.id
                        WHERE pi.patient_id = ?
                          AND p.branch_id = ?
                          AND p.visit_id = ?
                          AND p.status IN ('pending', 'confirmed', 'dispensed')
                          AND pi.cancelled_at IS NULL
                          AND pi.quantity > 0
                    ");
                    $stmt_med->execute([$patient_id, $user_branch_id, $visit_id]);
                    $med_data = $stmt_med->fetch(PDO::FETCH_ASSOC);
                    $med_total = (float)($med_data['med_total'] ?? 0);

                    $stmt_other = $db->prepare("
                        SELECT SUM(total_price) as other_total
                        FROM bill_items
                        WHERE bill_id = ? AND item_type != 'medication' AND status != 'cancelled'
                    ");
                    $stmt_other->execute([$bill_id]);
                    $other_total = (float)($stmt_other->fetch(PDO::FETCH_ASSOC)['other_total'] ?? 0);

                    $new_pharmacy_premium  = $current_pharmacy_premium + $premium_amount;
                    $new_pharmacy_discount = $current_pharmacy_discount + $discount_amount;
                    $new_premium_amount = $current_premium + $premium_amount;
                    $new_total_discount = $current_total_discount + $discount_amount;

                    $new_subtotal = $med_total + $other_total;

                    $new_total_amount = $new_subtotal + $new_premium_amount - $new_total_discount;
                    if ($new_total_amount < 0) $new_total_amount = 0;

                    $new_balance = $new_total_amount - $current_paid;
                    if ($new_balance < 0) $new_balance = 0;

                    if ($new_balance <= 0 && $new_total_amount > 0) {
                        $new_status = 'paid';
                    } elseif ($current_paid > 0) {
                        $new_status = 'partial';
                    } else {
                        $new_status = 'pending';
                    }

                    $stmt_update_bill = $db->prepare("
                        UPDATE bills
                        SET subtotal              = ?,
                            discount_amount       = ?,
                            total_discount        = ?,
                            premium_amount        = ?,
                            pharmacy_discount     = ?,
                            pharmacy_premium      = ?,
                            pharmacy_premium_note = ?,
                            total_amount          = ?,
                            balance               = ?,
                            status                = ?,
                            updated_at            = NOW(),
                            notes = CONCAT(
                                COALESCE(notes, ''),
                                ' | CONFIRMED BY ', ?,
                                ' - Premium +', ?,
                                ' Discount ', ?,
                                ' at ', NOW()
                            )
                        WHERE id = ? AND patient_id = ? AND visit_id = ?
                    ");
                    $stmt_update_bill->execute([
                        $new_subtotal,
                        $new_pharmacy_discount,
                        $new_total_discount,
                        $new_premium_amount,
                        $new_pharmacy_discount,
                        $new_pharmacy_premium,
                        'Pharmacy Premium: ' . number_format($new_pharmacy_premium, 0),
                        $new_total_amount,
                        $new_balance,
                        $new_status,
                        $user_full_name,
                        number_format($premium_amount, 0),
                        number_format($discount_amount, 0),
                        $bill_id, $patient_id, $visit_id
                    ]);

                    $stmt_del_bi = $db->prepare("
                        DELETE FROM bill_items
                        WHERE bill_id = ? AND item_type = 'medication' AND reference_type = 'prescription'
                    ");
                    $stmt_del_bi->execute([$bill_id]);

                    $stmt_items = $db->prepare("
                        SELECT pi.*, p.prescription_number
                        FROM prescription_items pi
                        JOIN prescriptions p ON pi.prescription_id = p.id
                        WHERE pi.patient_id = ?
                          AND p.branch_id = ?
                          AND p.visit_id = ?
                          AND p.status IN ('pending', 'confirmed', 'dispensed')
                          AND pi.cancelled_at IS NULL
                          AND pi.quantity > 0
                    ");
                    $stmt_items->execute([$patient_id, $user_branch_id, $visit_id]);
                    $items_for_bill = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($items_for_bill as $item) {
                        $gross_price = (float)$item['unit_price'] * (int)$item['quantity'];

                        $stmt_ins = $db->prepare("
                            INSERT INTO bill_items (
                                bill_id, patient_id, branch_id, item_type, item_name,
                                quantity, unit_price, total_price, discount_amount,
                                tax_amount, final_price, reference_id, reference_type,
                                status, created_at, updated_at
                            ) VALUES (?, ?, ?, 'medication', ?, ?, ?, ?, 0.00, 0.00, ?, ?, 'prescription', 'pending', NOW(), NOW())
                        ");
                        $stmt_ins->execute([
                            $bill_id, $patient_id, $user_branch_id,
                            $item['medication_name'] . ' (' . ($item['dosage'] ?? '') . ')',
                            $item['quantity'], $item['unit_price'], $gross_price,
                            $gross_price,
                            $item['prescription_id']
                        ]);
                    }

                    $message = "✅ Confirmed by {$user_full_name}! Bill updated (" . count($items_for_bill) . " medications).";
                    $message_type = 'success';

                } else {
                    $bill_number = 'BILL-PRES-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);

                    $stmt_items = $db->prepare("
                        SELECT SUM(pi.total_price) as med_total, COUNT(*) as med_count
                        FROM prescription_items pi
                        JOIN prescriptions p ON pi.prescription_id = p.id
                        WHERE pi.patient_id = ?
                          AND p.branch_id = ?
                          AND p.visit_id = ?
                          AND p.status IN ('pending', 'confirmed', 'dispensed')
                          AND pi.cancelled_at IS NULL
                          AND pi.quantity > 0
                    ");
                    $stmt_items->execute([$patient_id, $user_branch_id, $visit_id]);
                    $med_data = $stmt_items->fetch(PDO::FETCH_ASSOC);
                    $med_total = (float)($med_data['med_total'] ?? 0);

                    $subtotal = $med_total;
                    $final_total = $subtotal + $premium_amount - $discount_amount;
                    if ($final_total < 0) $final_total = 0;

                    $stmt = $db->prepare("
                        INSERT INTO bills (
                            bill_number, patient_id, visit_id, branch_id, created_by,
                            subtotal,
                            discount_amount, total_discount,
                            premium_amount, premium_note,
                            pharmacy_discount, cashier_discount,
                            pharmacy_premium, cashier_premium,
                            pharmacy_premium_note, cashier_premium_note,
                            total_amount, paid_amount, balance, status, payment_method, notes,
                            created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'cash', ?, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $bill_number, $patient_id, $visit_id, $user_branch_id, $user_id,
                        $subtotal,
                        $discount_amount,
                        $discount_amount,
                        $premium_amount,
                        'Pharmacy Premium: ' . number_format($premium_amount, 0),
                        $discount_amount,
                        0.00,
                        $premium_amount,
                        0.00,
                        'Pharmacy Premium: ' . number_format($premium_amount, 0),
                        NULL,
                        $final_total,
                        0,
                        $final_total,
                        "Confirmed by {$user_full_name} - Pharmacy Premium: " . number_format($premium_amount, 2)
                            . " Pharmacy Discount: " . number_format($discount_amount, 2)
                    ]);
                    $bill_id = $db->lastInsertId();

                    $stmt_items = $db->prepare("
                        SELECT pi.*, p.prescription_number
                        FROM prescription_items pi
                        JOIN prescriptions p ON pi.prescription_id = p.id
                        WHERE pi.patient_id = ?
                          AND p.branch_id = ?
                          AND p.visit_id = ?
                          AND p.status IN ('pending', 'confirmed', 'dispensed')
                          AND pi.cancelled_at IS NULL
                          AND pi.quantity > 0
                    ");
                    $stmt_items->execute([$patient_id, $user_branch_id, $visit_id]);
                    $items_for_bill = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($items_for_bill as $item) {
                        $gross_price = (float)$item['unit_price'] * (int)$item['quantity'];

                        $stmt = $db->prepare("
                            INSERT INTO bill_items (
                                bill_id, patient_id, branch_id, item_type, item_name,
                                quantity, unit_price, total_price, discount_amount,
                                tax_amount, final_price, reference_id, reference_type,
                                status, created_at, updated_at
                            ) VALUES (?, ?, ?, 'medication', ?, ?, ?, ?, 0.00, 0.00, ?, ?, 'prescription', 'pending', NOW(), NOW())
                        ");
                        $stmt->execute([
                            $bill_id, $patient_id, $user_branch_id,
                            $item['medication_name'] . ' (' . ($item['dosage'] ?? '') . ')',
                            $item['quantity'], $item['unit_price'], $gross_price,
                            $gross_price,
                            $item['prescription_id']
                        ]);
                    }

                    $message = "✅ Confirmed by {$user_full_name}! New bill created with " . count($items_for_bill) . " medications.";
                    $message_type = 'success';
                }

                $db->commit();

                $_SESSION['flash_message'] = $message;
                $_SESSION['flash_type'] = $message_type;

                header('Location: pending_prescriptions.php');
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }

    // ================================================================
    // GET DATA
    // ================================================================
    $stmt = $db->prepare("SELECT * FROM patients WHERE id = ? AND branch_id = ?");
    $stmt->execute([$patient_id, $user_branch_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        $message = "❌ Patient not found";
        $message_type = 'error';
    }

    if ($visit_id <= 0) {
        $stmt_v = $db->prepare("
            SELECT visit_id FROM prescriptions
            WHERE patient_id = ?
              AND branch_id = ?
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt_v->execute([$patient_id, $user_branch_id]);
        $v_data = $stmt_v->fetch(PDO::FETCH_ASSOC);
        $visit_id = (int)($v_data['visit_id'] ?? 0);
    }

    $visit_info = null;
    if ($visit_id > 0) {
        $stmt_v = $db->prepare("SELECT * FROM visits WHERE id = ? AND patient_id = ?");
        $stmt_v->execute([$visit_id, $patient_id]);
        $visit_info = $stmt_v->fetch(PDO::FETCH_ASSOC);
    }

    $prescriptions = [];
    if ($visit_id > 0) {
        $stmt = $db->prepare("
            SELECT p.*, u.full_name as doctor_name, v.visit_number, v.visit_date
            FROM prescriptions p
            LEFT JOIN users u ON p.doctor_id = u.id
            LEFT JOIN visits v ON p.visit_id = v.id
            WHERE p.patient_id = ?
              AND p.branch_id = ?
              AND p.visit_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$patient_id, $user_branch_id, $visit_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $items = [];
    $total_quantity = 0;
    $total_amount = 0;
    $total_items = 0;
    $cancelled_qty = 0;
    $cancelled_amount = 0;
    $cancelled_count = 0;

    if ($visit_id > 0) {
        $stmt = $db->prepare("
            SELECT
                pi.*,
                p.prescription_number,
                p.visit_id,
                p.status as prescription_status,
                p.created_at as prescription_date,
                u_confirm.full_name as confirmed_by_name,
                u_cancel.full_name as cancelled_by_name
            FROM prescription_items pi
            JOIN prescriptions p ON pi.prescription_id = p.id
            LEFT JOIN users u_confirm ON pi.confirmed_by = u_confirm.id
            LEFT JOIN users u_cancel ON pi.cancelled_by = u_cancel.id
            WHERE pi.patient_id = ?
              AND p.branch_id = ?
              AND p.visit_id = ?
            ORDER BY pi.created_at DESC
        ");
        $stmt->execute([$patient_id, $user_branch_id, $visit_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            $is_cancelled = !empty($item['cancelled_at']);
            if ($is_cancelled) {
                $c_qty = (int)($item['cancelled_quantity'] ?? 0);
                if ($c_qty === 0 && !empty($item['original_quantity'])) {
                    $c_qty = (int)$item['original_quantity'];
                }
                if ($c_qty === 0 && !empty($item['total_price']) && !empty($item['unit_price'])) {
                    $c_qty = (int)round((float)$item['total_price'] / (float)$item['unit_price']);
                }

                $c_amount = (float)($item['unit_price'] ?? 0) * $c_qty;
                if ($c_amount <= 0 && !empty($item['total_price'])) {
                    $c_amount = (float)$item['total_price'];
                }

                $cancelled_qty += $c_qty;
                $cancelled_amount += $c_amount;
                $cancelled_count++;
            } else {
                $total_quantity += $item['quantity'];
                $total_amount += $item['total_price'];
                $total_items++;
            }
        }
    }

    $bill = null;
    if ($visit_id > 0) {
        $stmt = $db->prepare("
            SELECT * FROM bills
            WHERE patient_id = ? AND visit_id = ? AND branch_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$patient_id, $visit_id, $user_branch_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $message_type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }

} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $items = [];
    $total_quantity = 0;
    $total_amount = 0;
    $total_items = 0;
    $cancelled_qty = 0;
    $cancelled_amount = 0;
    $cancelled_count = 0;
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function formatMoney($amount) {
    return number_format($amount, 0, '.', ',');
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y h:i A', strtotime($datetime));
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

$existing_premium = 0;
$existing_pharmacy_premium = 0;
$existing_cashier_premium = 0;
$existing_pharmacy_discount = 0;
$existing_cashier_discount = 0;

if ($bill) {
    $existing_pharmacy_premium = (float)($bill['pharmacy_premium'] ?? 0);
    $existing_cashier_premium  = (float)($bill['cashier_premium'] ?? 0);

    if ($existing_pharmacy_premium == 0 && $existing_cashier_premium == 0
        && !empty($bill['premium_amount'])) {
        $existing_pharmacy_premium = (float)$bill['premium_amount'];
    }

    $existing_premium = $existing_pharmacy_premium + $existing_cashier_premium;

    $existing_pharmacy_discount = (float)($bill['pharmacy_discount'] ?? 0);
    $existing_cashier_discount  = (float)($bill['cashier_discount'] ?? 0);

    if ($existing_pharmacy_discount == 0 && $existing_cashier_discount == 0
        && !empty($bill['discount_amount'])) {
        $existing_pharmacy_discount = (float)$bill['discount_amount'];
    }
}

include_once '../../components/pharmacy_header.php';
include_once '../../components/pharmacy_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Prescriptions - Braick Dispensary</title>

    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            --success-light: #34D399;
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-muted: #94A3B8;
            --border-color: #E2E8F0;
            --radius: 10px;
            --radius-lg: 16px;
            --shadow: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
            --field-min-height: 38px;
        }

        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
            --border-color: #334155;
            --gray-50: #1A1A2E;
            --gray-100: #1E293B;
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
            -webkit-font-smoothing: antialiased;
        }

        .mono, .money, .money-value, .date-mono, .stat-value,
        .visit-number-badge, .patient-id, .qty-number, .total-qty,
        .summary-number, .item-price {
            font-family: 'JetBrains Mono', monospace !important;
            font-feature-settings: 'tnum';
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.02em;
        }

        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--radius-lg);
            padding: 20px 28px;
            margin-bottom: 24px;
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

        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.12);
            padding: 6px 14px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.75rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }

        .visit-badge {
            background: linear-gradient(135deg, #FCD34D, #F59E0B);
            color: #78350F;
            padding: 3px 12px;
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-family: 'JetBrains Mono', monospace;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.4);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .visit-date-badge {
            background: rgba(255,255,255,0.25);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            border: 1px solid rgba(255,255,255,0.3);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .visit-info-card {
            background: linear-gradient(135deg, var(--bg-card) 0%, var(--primary-bg) 100%);
            border-radius: var(--radius-lg);
            padding: 16px 22px;
            border: 2px solid var(--primary);
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow);
        }

        .visit-info-card .visit-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #FCD34D, #F59E0B);
            color: #78350F;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
            flex-shrink: 0;
        }

        .visit-info-card .visit-details { flex: 1; min-width: 150px; }

        .visit-info-card .visit-label {
            font-size: 0.6rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 3px;
        }

        .visit-info-card .visit-value {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text-primary);
            font-family: 'JetBrains Mono', monospace;
        }

        .visit-info-card .visit-number-value {
            font-size: 1.1rem;
            font-weight: 800;
            color: #78350F;
            font-family: 'JetBrains Mono', monospace;
        }

        .patient-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 20px;
            box-shadow: var(--shadow);
        }

        .patient-card:hover { border-color: var(--primary-light); }

        .patient-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
            font-family: 'JetBrains Mono', monospace;
        }

        .patient-info h2 { font-size: 1.2rem; font-weight: 700; color: var(--text-primary); }

        .patient-info .patient-details {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .patient-info .patient-details span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* ================================================================
           ITEMS GRID
           ================================================================ */
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }

        .item-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            padding: 20px 22px;
            transition: var(--transition);
            box-shadow: var(--shadow);
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .item-card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .item-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .item-card.dispensed::before {
            background: linear-gradient(90deg, var(--success), #34D399);
        }

        .item-card.confirmed::before {
            background: linear-gradient(90deg, #0B5ED7, #6EA8FE);
        }

        .item-card.cancelled {
            opacity: 0.85;
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.03), rgba(185, 28, 28, 0.06));
            border-color: var(--danger);
        }

        .item-card.cancelled::before {
            background: linear-gradient(90deg, var(--danger), #F87171);
        }

        .item-card .cancel-item-btn {
            position: absolute;
            top: 12px;
            left: 12px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: white;
            border: 2px solid var(--bg-card);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            transition: var(--transition);
            z-index: 10;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.4);
        }

        .item-card .cancel-item-btn:hover {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            transform: scale(1.15) rotate(90deg);
            box-shadow: 0 4px 14px rgba(220, 38, 38, 0.6);
        }

        .item-card .cancel-item-btn:active {
            transform: scale(0.95);
        }

        .item-card .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 8px;
            margin-left: 36px;
            padding-bottom: 10px;
            border-bottom: 2px dashed var(--border-color);
        }

        .item-card .item-name {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.3;
            word-break: break-word;
        }

        .item-card.cancelled .item-name {
            text-decoration: line-through;
            color: var(--text-secondary);
        }

        .item-card .item-prescription {
            font-size: 0.6rem;
            color: var(--text-secondary);
            background: var(--gray-100);
            padding: 3px 10px;
            border-radius: 12px;
            font-family: 'JetBrains Mono', monospace;
            white-space: nowrap;
        }

        .item-card .item-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .item-card .item-detail { display: flex; flex-direction: column; }

        .item-card .item-detail .label {
            font-size: 0.58rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }

        .item-card .item-detail .value-display {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            padding: 8px 12px;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            min-height: var(--field-min-height);
            display: flex;
            align-items: center;
            word-break: break-word;
            line-height: 1.4;
        }

        .item-card .item-detail .value-display.highlight {
            color: var(--primary);
            font-weight: 700;
            border-color: var(--primary-light);
            font-family: 'JetBrains Mono', monospace;
            justify-content: center;
        }

        .item-card.cancelled .item-detail .value-display.highlight {
            color: var(--danger);
            border-color: var(--danger);
        }

        /* ================================================================
           QUANTITY INPUT - EDITABLE
           ================================================================ */
        .item-card .item-detail .qty-input-wrapper {
            display: flex;
            align-items: center;
            gap: 0;
            border: 2px solid var(--success);
            border-radius: var(--radius);
            background: var(--bg-body);
            overflow: hidden;
            min-height: var(--field-min-height);
        }

        .item-card .item-detail .qty-input-wrapper:focus-within {
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
        }

        .item-card .item-detail .qty-btn {
            width: 34px;
            height: var(--field-min-height);
            border: none;
            background: var(--success-bg);
            color: var(--success);
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            font-family: inherit;
            flex-shrink: 0;
        }

        .item-card .item-detail .qty-btn:hover {
            background: var(--success);
            color: white;
        }

        .item-card .item-detail .qty-btn:active {
            transform: scale(0.95);
        }

        .item-card .item-detail .qty-input {
            flex: 1;
            border: none;
            background: transparent;
            text-align: center;
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--success);
            font-family: 'JetBrains Mono', monospace;
            padding: 6px 4px;
            min-width: 50px;
            outline: none;
            height: var(--field-min-height);
            -moz-appearance: textfield;
        }

        .item-card .item-detail .qty-input::-webkit-outer-spin-button,
        .item-card .item-detail .qty-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .item-card .item-detail .qty-input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .item-card.cancelled .item-detail .qty-input-wrapper {
            border-color: var(--danger);
        }

        .item-card.cancelled .item-detail .qty-btn {
            background: var(--danger-bg);
            color: var(--danger);
        }

        .item-card.cancelled .item-detail .qty-input {
            color: var(--danger);
            text-decoration: line-through;
        }

        /* ================================================================
           FIELD WRAPPER
           ================================================================ */
        .item-card .item-detail select,
        .item-card .item-detail input {
            width: 100%;
            min-height: var(--field-min-height);
            padding: 8px 10px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.78rem;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: var(--transition);
            font-family: inherit;
            line-height: 1.4;
        }

        .item-card .item-detail select:focus,
        .item-card .item-detail input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
            outline: none;
        }

        .item-card .item-detail select:disabled,
        .item-card .item-detail input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .item-card .item-detail .field-wrapper {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .item-card .item-detail .field-wrapper select {
            width: 100%;
            min-height: var(--field-min-height);
            padding: 8px 10px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.78rem;
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: inherit;
            line-height: 1.4;
        }

        .item-card .item-detail .field-wrapper input {
            width: 100%;
            min-height: var(--field-min-height);
            padding: 8px 10px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.78rem;
            background: var(--bg-card);
            color: var(--text-primary);
            font-family: inherit;
            line-height: 1.4;
        }

        .item-card .item-detail .field-wrapper select:focus,
        .item-card .item-detail .field-wrapper input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
            outline: none;
        }

        /* ================================================================
           INSTRUCTIONS
           ================================================================ */
        .item-card .item-instructions {
            padding-top: 10px;
            border-top: 2px dashed var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .item-card .item-instructions label {
            font-size: 0.58rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .item-card .item-instructions .instr-row {
            display: flex;
            flex-direction: column;
            gap: 6px;
            width: 100%;
        }

        .item-card .item-instructions .instr-row select,
        .item-card .item-instructions .instr-row input {
            width: 100%;
            min-height: var(--field-min-height);
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.8rem;
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: inherit;
            line-height: 1.5;
            word-break: break-word;
            white-space: normal;
        }

        .item-card .item-instructions .instr-row select:focus,
        .item-card .item-instructions .instr-row input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
            outline: none;
        }

        .item-card .item-instructions .instr-row input {
            min-height: 60px;
            resize: vertical;
        }

        /* ================================================================
           ITEM PRICE - GREEN BACKGROUND
           ================================================================ */
        .item-card .item-price {
            text-align: right;
            font-weight: 700;
            font-size: 0.95rem;
            color: white;
            font-family: 'JetBrains Mono', monospace;
            padding: 10px 14px;
            border-radius: var(--radius);
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            flex-wrap: wrap;
        }

        .item-card .item-price .label {
            font-weight: 500;
            color: rgba(255,255,255,0.85);
            font-size: 0.7rem;
            font-family: 'Inter', sans-serif;
        }

        .item-card .item-price .amount {
            font-weight: 800;
            font-size: 1.05rem;
            color: white;
        }

        .item-card.cancelled .item-price {
            background: linear-gradient(135deg, var(--danger), var(--danger-dark));
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
        }

        .item-card.cancelled .item-price .label,
        .item-card.cancelled .item-price .amount {
            color: white;
        }

        .item-card .item-badge {
            position: absolute;
            top: 14px;
            right: 14px;
            font-size: 0.55rem;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 12px;
            background: var(--primary-bg);
            color: var(--primary);
            font-family: 'JetBrains Mono', monospace;
        }

        .item-card .status-badge {
            position: absolute;
            top: 40px;
            right: 14px;
            font-size: 0.5rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-family: 'JetBrains Mono', monospace;
        }

        .status-badge.pending { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .status-badge.confirmed { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        .status-badge.dispensed { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .status-badge.cancelled { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }

        .audit-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            flex-wrap: wrap;
            line-height: 1.4;
        }

        .audit-badge.confirmed-audit {
            background: linear-gradient(135deg, rgba(11, 94, 215, 0.1), rgba(11, 94, 215, 0.15));
            color: var(--primary);
            border: 1px solid var(--primary-light);
        }

        .audit-badge.cancelled-audit {
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.1), rgba(220, 38, 38, 0.15));
            color: var(--danger);
            border: 1px solid #FCA5A5;
        }

        .audit-badge i { font-size: 0.7rem; }

        .audit-badge .audit-name {
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
        }

        .audit-badge .audit-time {
            font-size: 0.58rem;
            opacity: 0.8;
        }

        .audit-badge .qty-cancelled-tag {
            background: var(--danger);
            color: white;
            padding: 3px 10px;
            border-radius: 8px;
            font-size: 0.6rem;
            font-weight: 800;
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: 0.02em;
        }

        .summary-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .summary-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }

        .summary-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }

        .summary-card .summary-number {
            font-size: 1.8rem;
            font-weight: 800;
            display: block;
            font-family: 'JetBrains Mono', monospace;
        }

        .summary-card .summary-label {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .summary-card.total .summary-number { color: var(--primary); }
        .summary-card.items .summary-number { color: #7C3AED; }
        .summary-card.qty .summary-number { color: var(--warning); }
        .summary-card.amount .summary-number { color: var(--success); }
        .summary-card.cancelled-card { border-color: var(--danger); }
        .summary-card.cancelled-card .summary-number { color: var(--danger); }

        .discount-section {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }

        .discount-section .discount-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .discount-section .discount-title i { color: var(--warning); }

        .discount-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 16px;
        }

        .discount-grid-card {
            background: var(--bg-body);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            transition: var(--transition);
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .discount-grid-card:hover { border-color: var(--primary-light); box-shadow: var(--shadow-md); }

        .discount-grid-card .card-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .discount-grid-card.premium-card .card-icon {
            background: linear-gradient(135deg, #F59E0B, #D97706);
            color: white;
        }

        .discount-grid-card.discount-card .card-icon {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
        }

        .discount-grid-card .card-content { flex: 1; }

        .discount-grid-card .card-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 6px;
        }

        .discount-grid-card .card-input-group {
            display: flex;
            align-items: center;
            gap: 4px;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 0 10px;
            transition: var(--transition);
            height: 40px;
        }

        .discount-grid-card .card-input-group:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }

        .discount-grid-card.premium-card .card-input-group:focus-within {
            border-color: #D97706;
            box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.1);
        }

        .discount-grid-card .card-input-group .currency-symbol {
            font-weight: 700;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .discount-grid-card .card-input-group input {
            flex: 1;
            border: none;
            background: transparent;
            padding: 6px 0;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            outline: none;
            height: 100%;
            min-width: 60px;
            font-family: 'JetBrains Mono', monospace;
        }

        .discount-grid-card .card-help {
            font-size: 0.55rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .discount-grid-card .existing-premium-note {
            display: inline-block;
            font-size: 0.6rem;
            color: #D97706;
            background: rgba(217, 119, 6, 0.1);
            padding: 2px 8px;
            border-radius: 10px;
            margin-top: 4px;
            border: 1px solid rgba(217, 119, 6, 0.2);
        }

        .final-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 12px;
            padding-top: 16px;
            border-top: 2px solid var(--border-color);
            margin-top: 4px;
        }

        .final-item {
            text-align: center;
            padding: 8px 12px;
            border-radius: var(--radius);
            background: var(--bg-body);
        }

        .final-item .final-label {
            font-size: 0.55rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
        }

        .final-item .final-value {
            font-size: 1.1rem;
            font-weight: 700;
            display: block;
            margin-top: 2px;
            color: var(--text-primary);
            font-family: 'JetBrains Mono', monospace;
        }

        .final-item .final-value.premium-value { color: #D97706; }
        .final-item .final-value.discount-value { color: var(--primary); }

        .final-item.final-total {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            border-radius: var(--radius);
        }

        .final-item.final-total .final-label { color: rgba(255,255,255,0.8); }
        .final-item.final-total .final-value { color: white; font-size: 1.3rem; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.8rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        }

        .btn-success { background: var(--success); color: white; }
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
        }

        .badge-status {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 16px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }

        [data-theme="dark"] .badge-warning { background: #3A2A1A; color: #F59E0B; border-color: #D97706; }
        [data-theme="dark"] .badge-info { background: #1E3A5F; color: #6EA8FE; border-color: #3B82F6; }
        [data-theme="dark"] .badge-success { background: #1A3A2A; color: #34D399; border-color: #059669; }
        [data-theme="dark"] .badge-danger { background: #3A1A1A; color: #F87171; border-color: #DC2626; }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-secondary);
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px dashed var(--border-color);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--success);
            display: block;
            margin-bottom: 16px;
            opacity: 0.6;
        }

        .empty-state p { font-size: 1.1rem; font-weight: 600; color: var(--text-primary); }
        .empty-state .sub { font-size: 0.85rem; color: var(--text-secondary); margin-top: 6px; font-weight: 400; }

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

        .cancel-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.75);
            z-index: 9999;
            backdrop-filter: blur(6px);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .cancel-modal-overlay.active {
            display: flex;
            animation: fadeIn 0.25s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(30px) scale(0.95); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }

        @keyframes pulseWarning {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.5); }
            50% { box-shadow: 0 0 0 16px rgba(220, 38, 38, 0); }
        }

        .cancel-modal {
            background: var(--bg-card);
            border-radius: 20px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 25px 60px rgba(0,0,0,0.35);
            animation: slideUp 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow: hidden;
            border: 2px solid var(--border-color);
        }

        .cancel-modal-header {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            padding: 24px 24px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cancel-modal-header::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 60%);
            animation: rotateBg 8s linear infinite;
        }

        @keyframes rotateBg {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .cancel-modal-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            font-size: 2.2rem;
            color: white;
            border: 3px solid rgba(255,255,255,0.4);
            position: relative;
            z-index: 1;
            animation: pulseWarning 2s ease-in-out infinite;
        }

        .cancel-modal-title {
            color: white;
            font-size: 1.25rem;
            font-weight: 800;
            margin: 0 0 4px;
            position: relative;
            z-index: 1;
            letter-spacing: -0.02em;
        }

        .cancel-modal-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.75rem;
            margin: 0;
            position: relative;
            z-index: 1;
            font-weight: 500;
        }

        .cancel-modal-body {
            padding: 20px 24px 8px;
        }

        .cancel-modal-info {
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.06), rgba(185, 28, 28, 0.1));
            border: 2px solid rgba(220, 38, 38, 0.2);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }

        .cancel-modal-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            font-size: 0.8rem;
            gap: 8px;
        }

        .cancel-modal-info-row + .cancel-modal-info-row {
            border-top: 1px dashed rgba(220, 38, 38, 0.2);
        }

        .cancel-modal-info-row .info-label {
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .cancel-modal-info-row .info-label i {
            color: var(--danger);
            width: 14px;
            font-size: 0.75rem;
        }

        .cancel-modal-info-row .info-value {
            color: var(--text-primary);
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            text-align: right;
            max-width: 60%;
            word-break: break-word;
        }

        .cancel-modal-info-row .info-value.med-name {
            font-family: 'Inter', sans-serif;
            color: var(--danger);
            font-size: 0.85rem;
        }

        .cancel-modal-info-row .info-value.amount {
            color: var(--danger);
            font-size: 0.9rem;
        }

        .cancel-modal-warning {
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            border: 2px solid #F59E0B;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 16px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        [data-theme="dark"] .cancel-modal-warning {
            background: linear-gradient(135deg, #3A2A1A, #2A1E10);
            border-color: #D97706;
        }

        .cancel-modal-warning i {
            color: #D97706;
            font-size: 1.1rem;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .cancel-modal-warning-text {
            font-size: 0.72rem;
            color: #78350F;
            font-weight: 600;
            line-height: 1.5;
        }

        [data-theme="dark"] .cancel-modal-warning-text {
            color: #FCD34D;
        }

        .cancel-modal-warning-text strong {
            font-weight: 800;
        }

        .cancel-modal-actions {
            display: flex;
            gap: 10px;
            padding: 8px 24px 20px;
        }

        .cancel-modal-btn {
            flex: 1;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.25s ease;
            font-family: inherit;
        }

        .cancel-modal-btn.keep-btn {
            background: var(--bg-body);
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }

        .cancel-modal-btn.keep-btn:hover {
            background: var(--primary-bg);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .cancel-modal-btn.confirm-btn {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
            box-shadow: 0 4px 16px rgba(220, 38, 38, 0.4);
        }

        .cancel-modal-btn.confirm-btn:hover {
            background: linear-gradient(135deg, #B91C1C, #991B1B);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.6);
        }

        .cancel-modal-btn.confirm-btn:active,
        .cancel-modal-btn.keep-btn:active {
            transform: translateY(0);
        }

        .cancel-modal-audit-note {
            text-align: center;
            padding: 0 24px 20px;
            font-size: 0.65rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .cancel-modal-audit-note i {
            color: var(--primary);
        }

        .cancel-modal-audit-note strong {
            color: var(--primary);
            font-weight: 700;
        }

        .footer {
            padding: 10px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
        }

        .footer .footer-brand { color: var(--primary); font-weight: 600; }

        .pdf-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            backdrop-filter: blur(4px);
        }

        .pdf-modal-overlay.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .pdf-modal {
            background: white;
            border-radius: var(--radius-lg);
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .pdf-modal-header {
            padding: 16px 20px;
            border-bottom: 2px solid #E2E8F0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            background: #0B5ED7;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .pdf-modal-header .modal-title {
            color: white;
            font-weight: 700;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pdf-modal-header .modal-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .pdf-modal-header .modal-actions .btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: var(--radius);
            font-size: 0.75rem;
        }

        .pdf-modal-header .modal-actions .btn:hover { background: rgba(255,255,255,0.25); }

        .pdf-modal-header .modal-actions .btn-danger-modal {
            background: rgba(239, 68, 68, 0.3);
            border-color: rgba(239, 68, 68, 0.3);
        }

        .pdf-modal-header .modal-actions .btn-danger-modal:hover { background: rgba(239, 68, 68, 0.5); }

        .pdf-modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
            background: #f8f9fa;
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
        }

        .pdf-content {
            background: white;
            padding: 20px;
            border-radius: var(--radius);
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 14px; }
            .items-grid { grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); }
        }

        @media (max-width: 768px) {
            .page-header { padding: 14px 16px; }
            .page-header .page-title { font-size: 1.1rem; }
            .items-grid { grid-template-columns: 1fr; }
            .discount-grid { grid-template-columns: 1fr; }
            .final-row { grid-template-columns: 1fr 1fr; gap: 8px; }
            .summary-section { grid-template-columns: 1fr 1fr; }
            .patient-card { flex-direction: column; text-align: center; }
            .patient-info .patient-details { justify-content: center; }
            .item-card .item-details { grid-template-columns: 1fr; }
            .visit-info-card { flex-direction: column; text-align: center; }
            .cancel-modal-actions { flex-direction: column-reverse; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .summary-section { grid-template-columns: 1fr; }
            .final-row { grid-template-columns: 1fr; }
            .discount-section { padding: 12px 14px; }
            .discount-grid-card { padding: 12px 14px; }
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
                <span style="background:rgba(255,255,255,0.2);color:white;padding:2px 10px;border-radius:20px;font-size:0.55rem;font-weight:600;text-transform:uppercase;">PHARMACY V8.6</span>
                <?php if ($visit_info): ?>
                    <span class="visit-badge">
                        <i class="fas fa-calendar-check"></i>
                        <?= htmlspecialchars($visit_info['visit_number'] ?? 'N/A') ?>
                    </span>
                    <span class="visit-date-badge">
                        <i class="fas fa-calendar-day"></i>
                        <?= $visit_info['visit_date'] ? date('d M Y', strtotime($visit_info['visit_date'])) : date('d M Y', strtotime($visit_info['created_at'] ?? 'now')) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hourglass-half"></i>
                <strong>PENDING, CONFIRMED, DISPENSED</strong> — All medications can be cancelled with audit trail
                <?php if ($patient): ?>
                    <span style="background:rgba(255,255,255,0.12);color:white;padding:2px 10px;border-radius:20px;font-size:0.55rem;">
                        <?= htmlspecialchars($patient['full_name']) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="pending_prescriptions.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="generatePDF()" class="btn-outline-light pdf-btn">
                <i class="fas fa-file-pdf"></i> View PDF
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="p-3 rounded-lg mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : ($message_type === 'warning' ? 'bg-yellow-100 text-yellow-700 border border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800') ?>" style="max-width:1400px;margin:0 auto 12px;font-size:0.8rem;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if ($patient && count($items) > 0): ?>

    <!-- VISIT INFO CARD -->
    <?php if ($visit_info): ?>
    <div class="visit-info-card">
        <div class="visit-icon">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="visit-details">
            <div class="visit-label">Visit Number</div>
            <div class="visit-number-value"><?= htmlspecialchars($visit_info['visit_number'] ?? 'N/A') ?></div>
        </div>
        <div class="visit-details">
            <div class="visit-label">Visit Date</div>
            <div class="visit-value">
                <?= $visit_info['visit_date'] ? date('d M Y', strtotime($visit_info['visit_date'])) : date('d M Y', strtotime($visit_info['created_at'] ?? 'now')) ?>
            </div>
        </div>
        <div class="visit-details">
            <div class="visit-label">Doctor</div>
            <div class="visit-value">
                <?= htmlspecialchars($prescriptions[0]['doctor_name'] ?? 'N/A') ?>
            </div>
        </div>
        <div style="margin-left:auto;">
            <span class="badge-status badge-info" style="font-size:0.7rem;padding:4px 16px;">
                📋 <?= count($prescriptions) ?> Prescriptions
            </span>
        </div>
    </div>
    <?php endif; ?>

    <!-- PATIENT CARD -->
    <div class="patient-card">
        <div class="patient-avatar" style="background: <?= '#' . substr(md5($patient['full_name']), 0, 6) ?>;">
            <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
        </div>
        <div class="patient-info">
            <h2><?= htmlspecialchars($patient['full_name']) ?></h2>
            <div class="patient-details">
                <span><i class="fas fa-id-card"></i> ID: <span class="mono"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span></span>
                <span><i class="fas fa-venus-mars"></i> <?= ucfirst($patient['gender'] ?? 'N/A') ?></span>
                <span><i class="fas fa-calendar-alt"></i> <span class="mono"><?= calculateAge($patient['date_of_birth'] ?? '') ?></span> yrs</span>
                <span><i class="fas fa-phone"></i> <span class="mono"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span></span>
                <?php if (!empty($patient['blood_group'])): ?>
                    <span><i class="fas fa-tint"></i> <?= htmlspecialchars($patient['blood_group']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="summary-section" id="summarySection">
        <div class="summary-card total">
            <span class="summary-number" id="totalPrescriptions"><?= count($prescriptions) ?></span>
            <span class="summary-label">📋 Prescriptions</span>
        </div>
        <div class="summary-card items">
            <span class="summary-number" id="totalItems"><?= $total_items ?></span>
            <span class="summary-label">📦 Active Items</span>
        </div>
        <div class="summary-card qty">
            <span class="summary-number" id="totalQty"><?= $total_quantity ?></span>
            <span class="summary-label">📊 Active Quantity</span>
        </div>
        <div class="summary-card amount">
            <span class="summary-number" id="totalAmountDisplay"><?= $currency ?> <?= formatMoney($total_amount) ?></span>
            <span class="summary-label">💰 Active Subtotal</span>
        </div>
        <?php if ($cancelled_count > 0): ?>
        <div class="summary-card cancelled-card">
            <span class="summary-number"><?= $cancelled_count ?></span>
            <span class="summary-label">❌ Cancelled Items</span>
        </div>
        <div class="summary-card cancelled-card">
            <span class="summary-number"><?= $cancelled_qty ?></span>
            <span class="summary-label">📊 Cancelled Qty</span>
        </div>
        <div class="summary-card cancelled-card">
            <span class="summary-number">-<?= $currency ?> <?= formatMoney($cancelled_amount) ?></span>
            <span class="summary-label">💰 Cancelled Amount</span>
        </div>
        <?php endif; ?>
    </div>

    <!-- FORM -->
    <form method="POST" action="" id="prescriptionForm">
        <input type="hidden" name="action" value="save_and_confirm">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <input type="hidden" name="visit_id" value="<?= $visit_id ?>">
        <input type="hidden" name="total_amount" id="totalAmountHidden" value="<?= $total_amount ?>">
        <input type="hidden" name="premium_amount" id="premiumAmountHidden" value="0">
        <input type="hidden" name="discount_amount" id="discountAmountHidden" value="0">

        <!-- ITEMS GRID -->
        <div class="items-grid" id="itemsGrid">
            <?php foreach ($items as $index => $item):
                $rx_status = $item['prescription_status'] ?? 'pending';
                $is_cancelled = !empty($item['cancelled_at']);
                $card_class = '';

                if ($is_cancelled) {
                    $card_class = 'cancelled';
                } elseif ($rx_status === 'dispensed') {
                    $card_class = 'dispensed';
                } elseif ($rx_status === 'confirmed') {
                    $card_class = 'confirmed';
                }

                $display_cancelled_qty = 0;
                $display_cancelled_amount = 0;
                if ($is_cancelled) {
                    $display_cancelled_qty = (int)($item['cancelled_quantity'] ?? 0);
                    if ($display_cancelled_qty === 0 && !empty($item['original_quantity'])) {
                        $display_cancelled_qty = (int)$item['original_quantity'];
                    }
                    if ($display_cancelled_qty === 0 && !empty($item['total_price']) && !empty($item['unit_price'])) {
                        $display_cancelled_qty = (int)round((float)$item['total_price'] / (float)$item['unit_price']);
                    }

                    $display_cancelled_amount = (float)($item['unit_price'] ?? 0) * $display_cancelled_qty;
                    if ($display_cancelled_amount <= 0) {
                        $display_cancelled_amount = (float)($item['total_price'] ?? 0);
                    }
                }
            ?>
                <div class="item-card <?= $card_class ?>" data-item-id="<?= $item['id'] ?>" data-unit-price="<?= (float)($item['unit_price'] ?? 0) ?>" data-status="<?= $is_cancelled ? 'cancelled' : $rx_status ?>">
                    <div class="item-badge">#<?= $index + 1 ?></div>

                    <div class="status-badge <?= $is_cancelled ? 'cancelled' : $rx_status ?>">
                        <?php if ($is_cancelled): ?>❌ Cancelled
                        <?php elseif ($rx_status === 'pending'): ?>⏳ Pending
                        <?php elseif ($rx_status === 'confirmed'): ?>✅ Confirmed
                        <?php elseif ($rx_status === 'dispensed'): ?>💊 Dispensed
                        <?php endif; ?>
                    </div>

                    <?php if (!$is_cancelled): ?>
                        <button type="button"
                                class="cancel-item-btn"
                                onclick="openCancelModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['medication_name'])) ?>', <?= $item['quantity'] ?>, <?= $item['total_price'] ?>, '<?= $rx_status ?>')"
                                title="Cancel this medication">
                            <i class="fas fa-times"></i>
                        </button>
                    <?php endif; ?>

                    <div class="item-header">
                        <span class="item-name"><?= htmlspecialchars($item['medication_name']) ?></span>
                        <span class="item-prescription"><?= htmlspecialchars($item['prescription_number'] ?? 'N/A') ?></span>
                    </div>

                    <?php if ($is_cancelled && !empty($item['cancelled_by_name'])): ?>
                        <div class="audit-badge cancelled-audit" title="Cancelled by <?= htmlspecialchars($item['cancelled_by_name']) ?> on <?= formatDate($item['cancelled_at']) ?>">
                            <i class="fas fa-user-times"></i>
                            <span>Cancelled by</span>
                            <span class="audit-name"><?= htmlspecialchars($item['cancelled_by_name']) ?></span>
                            <span class="audit-time">• <?= formatDate($item['cancelled_at']) ?></span>
                            <?php if ($display_cancelled_qty > 0): ?>
                                <span class="qty-cancelled-tag">
                                    <i class="fas fa-ban"></i> × <?= number_format($display_cancelled_qty) ?> cancelled
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php elseif (!$is_cancelled && !empty($item['confirmed_by_name'])): ?>
                        <div class="audit-badge confirmed-audit" title="Confirmed by <?= htmlspecialchars($item['confirmed_by_name']) ?> on <?= formatDate($item['confirmed_at']) ?>">
                            <i class="fas fa-user-check"></i>
                            <span>Confirmed by</span>
                            <span class="audit-name"><?= htmlspecialchars($item['confirmed_by_name']) ?></span>
                            <span class="audit-time">• <?= formatDate($item['confirmed_at']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="item-details">
                        <div class="item-detail">
                            <span class="label">💊 Dosage</span>
                            <div class="field-wrapper">
                                <select name="items[<?= $item['id'] ?>][dosage]" class="dosage-select" data-item-id="<?= $item['id'] ?>" onchange="updateLiveData()" <?= $is_cancelled ? 'disabled' : '' ?>>
                                    <option value="">-- Select --</option>
                                    <?php foreach ($dosage_options as $dos): ?>
                                        <option value="<?= htmlspecialchars($dos) ?>" <?= $item['dosage'] == $dos ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dos) ?> mg
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="dosage-manual" data-item-id="<?= $item['id'] ?>"
                                       value="<?= htmlspecialchars($item['dosage'] ?? '') ?>"
                                       placeholder="Manual entry..." oninput="updateLiveData()" <?= $is_cancelled ? 'disabled' : '' ?>>
                            </div>
                        </div>

                        <div class="item-detail">
                            <span class="label">🕐 Frequency</span>
                            <div class="field-wrapper">
                                <select name="items[<?= $item['id'] ?>][frequency]" class="frequency-select" data-item-id="<?= $item['id'] ?>" onchange="updateLiveData()" <?= $is_cancelled ? 'disabled' : '' ?>>
                                    <option value="">-- Select --</option>
                                    <?php foreach ($frequency_options as $freq): ?>
                                        <option value="<?= htmlspecialchars($freq) ?>" <?= $item['frequency'] == $freq ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($freq) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="frequency-manual" data-item-id="<?= $item['id'] ?>"
                                       value="<?= htmlspecialchars($item['frequency'] ?? '') ?>"
                                       placeholder="Manual entry..." oninput="updateLiveData()" <?= $is_cancelled ? 'disabled' : '' ?>>
                            </div>
                        </div>

                        <!-- ✅ V8.6: QUANTITY EDITABLE -->
                        <div class="item-detail">
                            <span class="label">📦 Quantity (Editable)</span>
                            <div class="qty-input-wrapper">
                                <button type="button" class="qty-btn" onclick="changeQty(<?= $item['id'] ?>, -1)" <?= $is_cancelled ? 'disabled' : '' ?>>
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number"
                                       name="items[<?= $item['id'] ?>][quantity]"
                                       id="qty_input_<?= $item['id'] ?>"
                                       class="qty-input"
                                       data-item-id="<?= $item['id'] ?>"
                                       value="<?= $is_cancelled ? $display_cancelled_qty : $item['quantity'] ?>"
                                       min="1"
                                       max="99999"
                                       step="1"
                                       oninput="onQtyChange(<?= $item['id'] ?>)"
                                       onchange="onQtyChange(<?= $item['id'] ?>)"
                                       <?= $is_cancelled ? 'disabled' : '' ?>>
                                <button type="button" class="qty-btn" onclick="changeQty(<?= $item['id'] ?>, 1)" <?= $is_cancelled ? 'disabled' : '' ?>>
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <div class="item-detail">
                            <span class="label">📅 Duration</span>
                            <div class="field-wrapper">
                                <select class="duration-select" data-item-id="<?= $item['id'] ?>" onchange="updateLiveData()" <?= $is_cancelled ? 'disabled' : '' ?>>
                                    <option value="">-- Select --</option>
                                    <option value="1 day">1 day</option>
                                    <option value="2 days">2 days</option>
                                    <option value="3 days">3 days</option>
                                    <option value="5 days">5 days</option>
                                    <option value="7 days">7 days</option>
                                    <option value="10 days">10 days</option>
                                    <option value="14 days">14 days</option>
                                    <option value="21 days">21 days</option>
                                    <option value="1 month">1 month</option>
                                    <option value="2 months">2 months</option>
                                    <option value="3 months">3 months</option>
                                    <option value="6 months">6 months</option>
                                    <option value="1 year">1 year</option>
                                </select>
                                <input type="text" name="items[<?= $item['id'] ?>][duration]" class="duration-manual" data-item-id="<?= $item['id'] ?>"
                                       value="<?= htmlspecialchars($item['duration'] ?? '') ?>"
                                       placeholder="Manual entry..." oninput="updateLiveData()" <?= $is_cancelled ? 'disabled' : '' ?>>
                            </div>
                        </div>

                        <div class="item-detail" style="grid-column: span 2;">
                            <span class="label">📏 Route</span>
                            <div class="field-wrapper">
                                <select name="items[<?= $item['id'] ?>][route]" class="route-select" data-item-id="<?= $item['id'] ?>" onchange="updateLiveData()" <?= $is_cancelled ? 'disabled' : '' ?>>
                                    <option value="">-- Select --</option>
                                    <?php foreach ($route_options as $route): ?>
                                        <option value="<?= htmlspecialchars($route) ?>" <?= $item['route'] == $route ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($route) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="route-manual" data-item-id="<?= $item['id'] ?>"
                                       value="<?= htmlspecialchars($item['route'] ?? '') ?>"
                                       placeholder="Manual entry..." oninput="updateLiveData()" <?= $is_cancelled ? 'disabled' : '' ?>>
                            </div>
                        </div>
                    </div>

                    <div class="item-instructions">
                        <label><i class="fas fa-edit"></i> Instructions</label>
                        <div class="instr-row">
                            <select id="instr_select_<?= $item['id'] ?>" class="instr-select" data-item-id="<?= $item['id'] ?>" onchange="updateInstructionInput(<?= $item['id'] ?>); updateLiveData();" <?= $is_cancelled ? 'disabled' : '' ?>>
                                <option value="">-- Select Preset --</option>
                                <?php foreach ($instruction_options as $opt): ?>
                                    <option value="<?= htmlspecialchars($opt) ?>" <?= $item['instructions'] == $opt ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($opt) ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="__custom__">✏️ Custom instruction...</option>
                            </select>
                            <input type="text" name="items[<?= $item['id'] ?>][instructions]"
                                   id="instr_input_<?= $item['id'] ?>"
                                   class="instr-input"
                                   data-item-id="<?= $item['id'] ?>"
                                   value="<?= htmlspecialchars($item['instructions'] ?? '') ?>"
                                   placeholder="Type custom instructions here (full text will be preserved)..."
                                   oninput="updateLiveData()" <?= $is_cancelled ? 'disabled' : '' ?>>
                        </div>
                    </div>

                    <!-- ✅ V8.6: Total Price with GREEN background -->
                    <div class="item-price" id="itemPrice_<?= $item['id'] ?>">
                        <?php if ($is_cancelled && $display_cancelled_amount > 0): ?>
                            <span class="label">❌ Cancelled Amount:</span>
                            <span class="amount" style="text-decoration: line-through;">
                                <?= $currency ?> <?= formatMoney($display_cancelled_amount) ?>
                            </span>
                            <span style="font-size:0.6rem; background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 6px; font-weight: 700;">
                                <?= number_format($display_cancelled_qty) ?> × <?= $currency ?> <?= formatMoney((float)($item['unit_price'] ?? 0)) ?>
                            </span>
                        <?php else: ?>
                            <span class="label">💰 Total Price (GROSS):</span>
                            <span class="amount" id="item_total_<?= $item['id'] ?>">
                                <?= $currency ?> <?= formatMoney($item['total_price'] ?? 0) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- DISCOUNT SECTION -->
        <div class="discount-section">
            <div class="discount-title">
                <i class="fas fa-tag"></i>
                Pharmacy Premium & Pharmacy Discount
                <span style="font-size:0.55rem;font-weight:400;color:var(--text-secondary);margin-left:8px;">
                    (Both are CUMULATIVE — stored in BILLS table only)
                </span>
            </div>

            <div class="discount-grid">
                <div class="discount-grid-card premium-card">
                    <div class="card-icon"><i class="fas fa-star"></i></div>
                    <div class="card-content">
                        <div class="card-label">⭐ Pharmacy Premium</div>
                        <div class="card-input-group">
                            <span class="currency-symbol"><?= $currency ?></span>
                            <input type="text" class="premium-input" id="premiumAmount"
                                   placeholder="0" value="0"
                                   oninput="calculateFinal()">
                        </div>
                        <div class="card-help">Additional charge (adds to existing pharmacy premium)</div>

                        <?php if ($existing_pharmacy_premium > 0): ?>
                            <div class="existing-premium-note">
                                <i class="fas fa-info-circle"></i>
                                Existing Pharmacy: <?= $currency ?> <?= formatMoney($existing_pharmacy_premium) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($existing_cashier_premium > 0): ?>
                            <div class="existing-premium-note" style="background:rgba(11,94,215,0.1);color:#0B5ED7;border-color:rgba(11,94,215,0.2);">
                                <i class="fas fa-info-circle"></i>
                                Existing Cashier: <?= $currency ?> <?= formatMoney($existing_cashier_premium) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="discount-grid-card discount-card">
                    <div class="card-icon"><i class="fas fa-percentage"></i></div>
                    <div class="card-content">
                        <div class="card-label">🎯 Pharmacy Discount</div>
                        <div class="card-input-group">
                            <span class="currency-symbol"><?= $currency ?></span>
                            <input type="text" class="discount-input" id="discountAmount"
                                   placeholder="0" value="0"
                                   oninput="calculateFinal()">
                        </div>
                        <div class="card-help">Discount applied to subtotal</div>

                        <?php if ($existing_pharmacy_discount > 0): ?>
                            <div class="existing-premium-note" style="background:rgba(11,94,215,0.1);color:#0B5ED7;border-color:rgba(11,94,215,0.2);">
                                <i class="fas fa-info-circle"></i>
                                Existing Pharmacy: <?= $currency ?> <?= formatMoney($existing_pharmacy_discount) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="final-row">
                <div class="final-item">
                    <span class="final-label">💰 Subtotal (GROSS)</span>
                    <span class="final-value" id="subtotalDisplay"><?= $currency ?> <?= formatMoney($total_amount) ?></span>
                </div>
                <div class="final-item">
                    <span class="final-label">➕ Pharmacy Premium</span>
                    <span class="final-value premium-value" id="premiumDisplay"><?= $currency ?> 0</span>
                </div>
                <div class="final-item">
                    <span class="final-label">➖ Pharmacy Discount</span>
                    <span class="final-value discount-value" id="discountDisplay"><?= $currency ?> 0</span>
                </div>
                <div class="final-item final-total">
                    <span class="final-label">✅ Final Amount</span>
                    <span class="final-value final-amount" id="finalAmount"><?= $currency ?> <?= formatMoney($total_amount + $existing_premium) ?></span>
                </div>
            </div>

            <div style="text-align:center;margin-top:12px;padding:8px 16px;background:var(--bg-body);border-radius:var(--radius);font-size:0.75rem;color:var(--text-secondary);">
                <i class="fas fa-calculator" style="color:var(--primary);"></i>
                <strong>Pharmacy Premium</strong> (cumulative):
                <?= $currency ?> <span id="existingPremiumDisplay" class="mono"><?= formatMoney($existing_pharmacy_premium) ?></span>
                + <?= $currency ?> <span id="newPremiumDisplay" class="mono">0</span>
                = <strong style="color:#D97706;"><?= $currency ?> <span id="totalPremiumDisplay" class="mono"><?= formatMoney($existing_pharmacy_premium) ?></span></strong>
            </div>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="action-buttons">
            <a href="pending_prescriptions.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button type="submit" class="btn btn-success" onclick="return confirmPrescription()">
                <i class="fas fa-check-circle"></i> Save & Confirm
            </button>
        </div>
    </form>

    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <p>No prescriptions for this visit</p>
            <p class="sub">
                <?php if ($visit_info): ?>
                    Visit: <strong><?= htmlspecialchars($visit_info['visit_number'] ?? 'N/A') ?></strong>
                    (<?= $visit_info['visit_date'] ? date('d M Y', strtotime($visit_info['visit_date'])) : 'N/A' ?>)
                    <br>
                <?php endif; ?>
                All medications have been cancelled or no prescriptions found ✅
            </p>
            <a href="pending_prescriptions.php" class="btn btn-primary" style="margin-top:16px;">
                <i class="fas fa-arrow-left"></i> Back to Prescriptions
            </a>
        </div>
    <?php endif; ?>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Prescriptions V8.6 (Editable Quantity + Green Total)
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- CANCEL CONFIRMATION MODAL -->
<div class="cancel-modal-overlay" id="cancelModalOverlay">
    <div class="cancel-modal">
        <div class="cancel-modal-header">
            <div class="cancel-modal-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2 class="cancel-modal-title">Cancel Medication?</h2>
            <p class="cancel-modal-subtitle">This action cannot be undone</p>
        </div>

        <div class="cancel-modal-body">
            <div class="cancel-modal-info">
                <div class="cancel-modal-info-row">
                    <span class="info-label"><i class="fas fa-pills"></i> Medication</span>
                    <span class="info-value med-name" id="modalMedName">—</span>
                </div>
                <div class="cancel-modal-info-row">
                    <span class="info-label"><i class="fas fa-info-circle"></i> Status</span>
                    <span class="info-value" id="modalMedStatus">—</span>
                </div>
                <div class="cancel-modal-info-row">
                    <span class="info-label"><i class="fas fa-sort-numeric-up"></i> Quantity</span>
                    <span class="info-value" id="modalMedQty">—</span>
                </div>
                <div class="cancel-modal-info-row">
                    <span class="info-label"><i class="fas fa-money-bill"></i> Amount</span>
                    <span class="info-value amount" id="modalMedAmount">—</span>
                </div>
            </div>

            <div class="cancel-modal-warning">
                <i class="fas fa-info-circle"></i>
                <div class="cancel-modal-warning-text">
                    This item will be <strong>removed from the bill</strong> and marked as <strong>CANCELLED</strong>.
                    <br>
                    This action will be logged as <strong>Cancelled by <?= htmlspecialchars($user_full_name) ?></strong>.
                </div>
            </div>
        </div>

        <div class="cancel-modal-actions">
            <button type="button" class="cancel-modal-btn keep-btn" onclick="closeCancelModal()">
                <i class="fas fa-times"></i> No, Keep It
            </button>
            <button type="button" class="cancel-modal-btn confirm-btn" id="modalConfirmBtn" onclick="confirmCancelItem()">
                <i class="fas fa-check"></i> Yes, Cancel
            </button>
        </div>

        <div class="cancel-modal-audit-note">
            <i class="fas fa-shield-alt"></i>
            <span>Logged by <strong><?= htmlspecialchars($user_full_name) ?></strong></span>
        </div>
    </div>
</div>

<!-- PDF MODAL -->
<div class="pdf-modal-overlay" id="pdfModal">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <div class="modal-title">
                <i class="fas fa-file-pdf" style="color:rgba(255,255,255,0.8);"></i>
                Prescriptions PDF - <?= htmlspecialchars($patient['full_name'] ?? 'Patient') ?>
            </div>
            <div class="modal-actions">
                <button onclick="downloadPDF()" class="btn">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="window.print()" class="btn">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="closePDFModal()" class="btn btn-danger-modal">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
        <div class="pdf-modal-body" id="pdfModalBody">
            <div class="pdf-content" id="pdfContent"></div>
        </div>
    </div>
</div>

<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:0.9rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    var htmlElement = document.documentElement;
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') htmlElement.setAttribute('data-theme', 'dark');
    else if (savedDarkMode === 'false') htmlElement.removeAttribute('data-theme');
    else {
        var cookieDark = document.cookie.match(/dark_mode=([^;]+)/);
        if (cookieDark && cookieDark[1] === 'true') htmlElement.setAttribute('data-theme', 'dark');
    }

    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() { sidebar.classList.toggle('open'); });
    }

    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) footerTimestamp.textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    function formatMoney(amount) { return Number(amount).toLocaleString('en-US'); }
    function unformatMoney(str) { return parseFloat(String(str).replace(/,/g, '')) || 0; }

    var existingPremium         = <?= (float)$existing_premium ?>;
    var existingPharmacyPremium = <?= (float)$existing_pharmacy_premium ?>;
    var existingCashierPremium  = <?= (float)$existing_cashier_premium ?>;
    var existingPharmacyDiscount = <?= (float)$existing_pharmacy_discount ?>;
    var currentUserName = '<?= htmlspecialchars(addslashes($user_full_name)) ?>';
    var currencySymbol = '<?= $currency ?>';

    var pendingCancelItem = {
        id: null,
        name: '',
        quantity: 0,
        totalPrice: 0,
        status: ''
    };

    // ================================================================
    // ✅ V8.6: QUANTITY CHANGE HANDLERS
    // ================================================================
    function changeQty(itemId, delta) {
        var input = document.getElementById('qty_input_' + itemId);
        if (!input || input.disabled) return;

        var current = parseInt(input.value) || 1;
        var newVal = current + delta;
        if (newVal < 1) newVal = 1;
        if (newVal > 99999) newVal = 99999;

        input.value = newVal;
        onQtyChange(itemId);
    }

    function onQtyChange(itemId) {
        var input = document.getElementById('qty_input_' + itemId);
        if (!input) return;

        var qty = parseInt(input.value) || 0;
        if (qty < 1) { qty = 1; input.value = 1; }
        if (qty > 99999) { qty = 99999; input.value = 99999; }

        // Get unit_price from card
        var card = document.querySelector('.item-card[data-item-id="' + itemId + '"]');
        if (!card) return;

        var unitPrice = parseFloat(card.getAttribute('data-unit-price')) || 0;
        var totalPrice = qty * unitPrice;

        // Update the item total display
        var totalEl = document.getElementById('item_total_' + itemId);
        if (totalEl) {
            totalEl.textContent = currencySymbol + ' ' + formatMoney(totalPrice);
        }

        // Recalculate overall
        updateLiveData();
    }

    function openCancelModal(itemId, medicationName, quantity, totalPrice, status) {
        pendingCancelItem.id = itemId;
        pendingCancelItem.name = medicationName;
        pendingCancelItem.quantity = quantity;
        pendingCancelItem.totalPrice = totalPrice;
        pendingCancelItem.status = status;

        var statusLabel = status === 'dispensed' ? '💊 DISPENSED' : (status === 'confirmed' ? '✅ CONFIRMED' : '⏳ PENDING');

        document.getElementById('modalMedName').textContent = medicationName;
        document.getElementById('modalMedStatus').textContent = statusLabel;
        document.getElementById('modalMedQty').textContent = quantity + ' units';
        document.getElementById('modalMedAmount').textContent = '<?= $currency ?> ' + formatMoney(totalPrice);

        document.getElementById('cancelModalOverlay').classList.add('active');
    }

    function closeCancelModal() {
        document.getElementById('cancelModalOverlay').classList.remove('active');
        pendingCancelItem = { id: null, name: '', quantity: 0, totalPrice: 0, status: '' };
    }

    function confirmCancelItem() {
        if (!pendingCancelItem.id) return;

        var confirmBtn = document.getElementById('modalConfirmBtn');
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';

        var card = document.querySelector('.item-card[data-item-id="' + pendingCancelItem.id + '"]');
        if (card) {
            card.style.opacity = '0.3';
            card.style.pointerEvents = 'none';
        }

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '';

        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'cancel_item';
        form.appendChild(actionInput);

        var itemInput = document.createElement('input');
        itemInput.type = 'hidden';
        itemInput.name = 'item_id';
        itemInput.value = pendingCancelItem.id;
        form.appendChild(itemInput);

        var patientInput = document.createElement('input');
        patientInput.type = 'hidden';
        patientInput.name = 'patient_id';
        patientInput.value = <?= $patient_id ?>;
        form.appendChild(patientInput);

        var visitInput = document.createElement('input');
        visitInput.type = 'hidden';
        visitInput.name = 'visit_id';
        visitInput.value = <?= $visit_id ?>;
        form.appendChild(visitInput);

        document.body.appendChild(form);
        form.submit();
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeCancelModal();
            closePDFModal();
        }
    });

    document.getElementById('cancelModalOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeCancelModal();
    });

    function syncField(selectSelector, inputSelector, dataAttr) {
        var inputs = document.querySelectorAll(inputSelector);
        inputs.forEach(function(input) {
            var itemId = input.getAttribute(dataAttr);
            var select = document.querySelector(selectSelector + '[data-item-id="' + itemId + '"]');
            if (select) {
                input.addEventListener('input', function() {
                    var val = this.value.trim();
                    var found = false;
                    for (var i = 0; i < select.options.length; i++) {
                        if (select.options[i].value === val) { select.selectedIndex = i; found = true; break; }
                    }
                    if (!found && val === '') select.selectedIndex = 0;
                    updateLiveData();
                });
                select.addEventListener('change', function() {
                    if (this.value !== '') input.value = this.value;
                    else input.value = '';
                    updateLiveData();
                });
            }
        });
    }

    // ================================================================
    // ✅ V8.6: Update live data with editable quantity
    // ================================================================
    function updateLiveData() {
        var totalQty = 0, totalAmount = 0, totalItems = 0;

        document.querySelectorAll('.item-card:not(.cancelled)').forEach(function(card) {
            var itemId = card.getAttribute('data-item-id');
            var unitPrice = parseFloat(card.getAttribute('data-unit-price')) || 0;

            var qtyInput = document.getElementById('qty_input_' + itemId);
            var qty = qtyInput ? (parseInt(qtyInput.value) || 0) : 0;
            if (qty < 1) qty = 1;

            totalQty += qty;
            totalAmount += (qty * unitPrice);
            totalItems++;
        });

        document.getElementById('totalQty').textContent = totalQty;
        document.getElementById('totalItems').textContent = totalItems;
        document.getElementById('totalAmountDisplay').textContent = currencySymbol + ' ' + formatMoney(totalAmount);
        document.getElementById('subtotalDisplay').textContent = currencySymbol + ' ' + formatMoney(totalAmount);
        document.getElementById('totalAmountHidden').value = totalAmount;

        calculateFinal();
    }

    function calculateFinal() {
        var totalAmount = parseFloat(document.getElementById('totalAmountHidden').value) || 0;
        var premiumValue = unformatMoney(document.getElementById('premiumAmount').value);
        var discountValue = unformatMoney(document.getElementById('discountAmount').value);

        if (premiumValue < 0) { premiumValue = 0; document.getElementById('premiumAmount').value = '0'; }
        if (discountValue < 0) { discountValue = 0; document.getElementById('discountAmount').value = '0'; }

        var totalPharmacyPremium = existingPharmacyPremium + premiumValue;

        var finalAmount = totalAmount + premiumValue - discountValue;
        if (finalAmount < 0) finalAmount = 0;

        document.getElementById('premiumAmountHidden').value = premiumValue;
        document.getElementById('discountAmountHidden').value = discountValue;

        document.getElementById('subtotalDisplay').textContent = currencySymbol + ' ' + formatMoney(totalAmount);
        document.getElementById('premiumDisplay').textContent = currencySymbol + ' ' + formatMoney(premiumValue);
        document.getElementById('discountDisplay').textContent = currencySymbol + ' ' + formatMoney(discountValue);
        document.getElementById('finalAmount').textContent = currencySymbol + ' ' + formatMoney(finalAmount);

        document.getElementById('existingPremiumDisplay').textContent = formatMoney(existingPharmacyPremium);
        document.getElementById('newPremiumDisplay').textContent = formatMoney(premiumValue);
        document.getElementById('totalPremiumDisplay').textContent = formatMoney(totalPharmacyPremium);
    }

    function confirmPrescription() {
        var patientName = '<?= addslashes($patient['full_name'] ?? 'Unknown') ?>';
        var visitNumber = '<?= addslashes($visit_info['visit_number'] ?? 'N/A') ?>';
        var totalItems = document.getElementById('totalItems').textContent;
        var totalQty = document.getElementById('totalQty').textContent;
        var subtotal = document.getElementById('subtotalDisplay').textContent;
        var finalAmount = document.getElementById('finalAmount').textContent;
        var premiumValue = unformatMoney(document.getElementById('premiumAmount').value);
        var discountValue = unformatMoney(document.getElementById('discountAmount').value);
        var totalPharmacyPremium = existingPharmacyPremium + premiumValue;

        var message = 'Confirm this prescription?\n\n';
        message += '📅 Visit: ' + visitNumber + '\n';
        message += '👤 Patient: ' + patientName + '\n';
        message += '📦 Active Items: ' + totalItems + '\n';
        message += '📊 Active Quantity: ' + totalQty + '\n';
        message += '💰 Subtotal (GROSS): ' + subtotal + '\n';
        if (premiumValue > 0) {
            message += '⭐ New Pharmacy Premium: ' + currencySymbol + ' ' + formatMoney(premiumValue) + '\n';
            message += '📊 Total Pharmacy Premium: ' + currencySymbol + ' ' + formatMoney(totalPharmacyPremium) + '\n';
        }
        if (discountValue > 0) {
            message += '🎯 Pharmacy Discount: ' + currencySymbol + ' ' + formatMoney(discountValue) + '\n';
        }
        message += '✅ Final Amount: ' + finalAmount + '\n\n';
        message += '📝 This will be logged as: Confirmed by ' + currentUserName + '\n';
        message += '⚠️ Only remaining (non-cancelled) medications will be confirmed.';

        return confirm(message);
    }

    function updateInstructionInput(itemId) {
        var select = document.getElementById('instr_select_' + itemId);
        var input = document.getElementById('instr_input_' + itemId);
        if (select.value === '__custom__') {
            input.value = '';
            input.focus();
            input.style.borderColor = 'var(--warning)';
        } else {
            input.value = select.value;
            input.style.borderColor = 'var(--border-color)';
        }
        updateLiveData();
    }

    document.addEventListener('DOMContentLoaded', function() {
        syncField('.dosage-select', '.dosage-manual', 'data-item-id');
        syncField('.frequency-select', '.frequency-manual', 'data-item-id');
        syncField('.duration-select', '.duration-manual', 'data-item-id');
        syncField('.route-select', '.route-manual', 'data-item-id');

        var premiumInput = document.getElementById('premiumAmount');
        if (premiumInput) {
            premiumInput.addEventListener('input', function() {
                var raw = this.value.replace(/,/g, '');
                var num = parseFloat(raw);
                if (!isNaN(num) && raw.length > 0) this.value = formatMoney(num);
                else if (raw.length === 0) this.value = '0';
                calculateFinal();
            });
            premiumInput.addEventListener('focus', function() { this.select(); });
        }

        var discountInput = document.getElementById('discountAmount');
        if (discountInput) {
            discountInput.addEventListener('input', function() {
                var raw = this.value.replace(/,/g, '');
                var num = parseFloat(raw);
                if (!isNaN(num) && raw.length > 0) this.value = formatMoney(num);
                else if (raw.length === 0) this.value = '0';
                calculateFinal();
            });
            discountInput.addEventListener('focus', function() { this.select(); });
        }

        <?php foreach ($items as $item): ?>
            <?php if (empty($item['cancelled_at'])): ?>
                var instrInput<?= $item['id'] ?> = document.getElementById('instr_input_<?= $item['id'] ?>');
                var instrSelect<?= $item['id'] ?> = document.getElementById('instr_select_<?= $item['id'] ?>');
                if (instrInput<?= $item['id'] ?> && instrSelect<?= $item['id'] ?>) {
                    var currentVal = instrInput<?= $item['id'] ?>.value.trim();
                    if (currentVal) {
                        var found = false;
                        for (var i = 0; i < instrSelect<?= $item['id'] ?>.options.length; i++) {
                            if (instrSelect<?= $item['id'] ?>.options[i].value === currentVal) {
                                instrSelect<?= $item['id'] ?>.selectedIndex = i;
                                found = true;
                                break;
                            }
                        }
                        if (!found) instrSelect<?= $item['id'] ?>.value = '__custom__';
                    }
                }
            <?php endif; ?>
        <?php endforeach; ?>

        calculateFinal();
    });

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

    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');

        var itemsHtml = '';
        var totalQty = 0, totalAmount = 0, totalItems = 0;
        var cancelledQty = 0, cancelledAmount = 0, cancelledCount = 0;
        var cancelledItemsHtml = '';

        document.querySelectorAll('.item-card').forEach(function(card) {
            var itemId = card.getAttribute('data-item-id');
            var unitPrice = parseFloat(card.getAttribute('data-unit-price')) || 0;

            var medName = card.querySelector('.item-name')?.textContent || 'N/A';
            var prescription = card.querySelector('.item-prescription')?.textContent || 'N/A';
            var dosage = card.querySelector('.dosage-select')?.value || card.querySelector('.dosage-manual')?.value || 'N/A';
            var frequency = card.querySelector('.frequency-select')?.value || card.querySelector('.frequency-manual')?.value || 'N/A';

            var qtyInput = document.getElementById('qty_input_' + itemId);
            var quantity = qtyInput ? (parseInt(qtyInput.value) || 0) : 0;

            var duration = card.querySelector('.duration-select')?.value || card.querySelector('.duration-manual')?.value || 'N/A';
            var route = card.querySelector('.route-select')?.value || card.querySelector('.route-manual')?.value || 'N/A';
            var instructions = card.querySelector('.instr-input')?.value || 'No instructions';
            var price = quantity * unitPrice;
            var isCancelled = card.classList.contains('cancelled');
            var auditBadge = card.querySelector('.audit-badge');
            var auditText = auditBadge ? auditBadge.textContent.trim() : '';

            if (isCancelled) {
                cancelledQty += quantity;
                cancelledAmount += price;
                cancelledCount++;
                cancelledItemsHtml += `<div style="border:1px solid #FCA5A5;border-radius:8px;padding:10px 14px;margin-bottom:8px;background:#FEF2F2;page-break-inside:avoid;"><div style="display:flex;justify-content:space-between;"><strong style="color:#DC2626;text-decoration:line-through;">${medName}</strong><span style="color:#DC2626;">❌ CANCELLED</span></div><div style="font-size:12px;color:#991B1B;">Qty: ${quantity} = <strong>-${currencySymbol} ${formatMoney(price)}</strong></div>${auditText ? `<div style="font-size:11px;color:#B91C1C;margin-top:4px;">${auditText}</div>` : ''}</div>`;
            } else {
                totalQty += quantity;
                totalAmount += price;
                totalItems++;
                itemsHtml += `<div style="border:1px solid #E2E8F0;border-radius:8px;padding:12px 16px;margin-bottom:10px;page-break-inside:avoid;"><div style="display:flex;justify-content:space-between;"><strong style="color:#0B5ED7;font-size:16px;">${medName}</strong><span style="font-family:monospace;font-size:12px;color:#64748B;">${prescription}</span></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 16px;font-size:14px;margin-top:6px;"><div><strong>Dosage:</strong> ${dosage}</div><div><strong>Frequency:</strong> ${frequency}</div><div><strong>Quantity:</strong> ${quantity}</div><div><strong>Duration:</strong> ${duration}</div><div style="grid-column:span 2;"><strong>Route:</strong> ${route}</div><div style="grid-column:span 2;"><strong>Instructions:</strong> ${instructions}</div>${auditText ? `<div style="grid-column:span 2;color:#0B5ED7;font-size:11px;">${auditText}</div>` : ''}<div style="grid-column:span 2;text-align:right;font-weight:700;color:#059669;">Total: ${currencySymbol} ${formatMoney(price)}</div></div></div>`;
            }
        });

        var premiumValue = unformatMoney(document.getElementById('premiumAmount')?.value);
        var discountValue = unformatMoney(document.getElementById('discountAmount')?.value);
        var finalAmount = totalAmount + premiumValue - discountValue;

        var html = `<div style="font-family:'Inter',sans-serif;padding:20px;"><div style="text-align:center;padding-bottom:16px;border-bottom:3px solid #0B5ED7;margin-bottom:20px;"><div style="font-size:1.6rem;font-weight:800;color:#0B5ED7;">BRAICK DISPENSARY</div><div style="font-size:0.8rem;color:#64748B;">Tunajali Afya Yako</div><div style="font-size:0.9rem;font-weight:600;color:#0B5ED7;margin-top:6px;">Prescriptions (With Cancelled Items)</div></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;padding:12px 16px;background:#F8FAFC;border-radius:8px;margin-bottom:16px;"><div><strong>Patient:</strong> <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></div><div><strong>ID:</strong> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></div><div><strong>Visit #:</strong> <?= htmlspecialchars($visit_info['visit_number'] ?? 'N/A') ?></div><div><strong>Date:</strong> <?= date('d M Y') ?></div></div><div style="margin-bottom:16px;"><div style="font-size:1rem;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin-bottom:10px;">✅ Active Items (${totalItems})</div>${itemsHtml}</div>${cancelledCount > 0 ? `<div style="margin-bottom:16px;"><div style="font-size:1rem;font-weight:700;color:#DC2626;border-bottom:2px solid #FCA5A5;padding-bottom:4px;margin-bottom:10px;">❌ Cancelled Items (${cancelledCount})</div>${cancelledItemsHtml}</div>` : ''}<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;margin:16px 0;padding:12px 16px;background:#E8F0FE;border-radius:8px;"><div style="text-align:center;"><div style="font-size:0.6rem;color:#64748B;text-transform:uppercase;">Active Items</div><div style="font-weight:700;color:#0B5ED7;font-family:monospace;">${totalItems}</div></div><div style="text-align:center;"><div style="font-size:0.6rem;color:#64748B;text-transform:uppercase;">Active Qty</div><div style="font-weight:700;color:#D97706;font-family:monospace;">${totalQty}</div></div><div style="text-align:center;"><div style="font-size:0.6rem;color:#64748B;text-transform:uppercase;">Cancelled Qty</div><div style="font-weight:700;color:#DC2626;font-family:monospace;">${cancelledQty}</div></div><div style="text-align:center;"><div style="font-size:0.6rem;color:#64748B;text-transform:uppercase;">Final Amount</div><div style="font-weight:700;color:#059669;font-family:monospace;">${currencySymbol} ${formatMoney(finalAmount)}</div></div></div><div style="margin-top:20px;padding-top:12px;border-top:2px solid #E2E8F0;text-align:center;font-size:12px;color:#94A3B8;">Braick Dispensary • Generated on <?= date('F d, Y h:i:s A') ?></div></div>`;
        content.innerHTML = html;
        modal.classList.add('active');
    }

    function closePDFModal() {
        document.getElementById('pdfModal').classList.remove('active');
    }

    function downloadPDF() {
        var element = document.getElementById('pdfContent');
        var opt = {
            margin: [10, 10, 10, 10],
            filename: 'Prescriptions_Visit_<?= $visit_id ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', logging: false },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
        };
        html2pdf().set(opt).from(element).save();
    }

    document.getElementById('pdfModal').addEventListener('click', function(e) {
        if (e.target === this) closePDFModal();
    });

    console.log('%c💊 Braick - Prescriptions V8.6', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Quantity: EDITABLE (with +/- buttons)', 'font-size:13px; color:#059669; font-weight:bold;');
    console.log('%c✅ Total Price: GREEN background', 'font-size:13px; color:#059669; font-weight:bold;');
    console.log('%c✅ Cancel: Bill-only (NO stock return)', 'font-size:13px; color:#DC2626; font-weight:bold;');
</script>

</body>
</html>