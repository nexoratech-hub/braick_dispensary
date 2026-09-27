<?php
// ================================================================
// FILE: frontend/pages/admin/audit/edit_visit.php
// ADMIN AUDIT - FULL EDIT VISIT (V3 - SCROLL FIX)
// ✅ Blue theme throughout
// ✅ Visit type: ONLY services with category = 'Consultation'
// ✅ Live search filter on ALL dropdowns
// ✅ All sections expanded
// ✅ Full CRUD on lab tests, medications, procedures, equipment
// ✅ Stock reversal rules for medications (pending/confirmed only)
// ✅ Shared recalculateBill() function
// ✅ AUTO-SCROLL to section after submit (no jump to top!)
// ✅ Alert auto-dismiss on scroll
// ✅ Timezone: Africa/Dar_es_Salaam
// ================================================================

date_default_timezone_set('Africa/Dar_es_Salaam');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id          = $_SESSION['user_id'] ?? 0;
$user_full_name   = $_SESSION['full_name'] ?? 'Admin';
$user_role        = $_SESSION['role'] ?? 'admin';
$user_branch_id   = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic      = $_SESSION['profile_pic'] ?? '';

$visit_id           = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($visit_id <= 0) {
    header('Location: revenue.php?branch=' . $selected_branch_id);
    exit;
}

require_once __DIR__ . '/../../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $db->exec("SET time_zone = '+03:00'");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// HELPERS
// ================================================================
function money($amount, $currency = '')
{
    $formatted = number_format((float)$amount, 0, '.', ',');
    return $currency ? $currency . ' ' . $formatted : $formatted;
}

function recalculateBill(PDO $db, int $bill_id, int $user_id, int $branch_id, string $logDetails): array
{
    $stmt = $db->prepare("
        SELECT subtotal, pharmacy_discount, cashier_discount,
               pharmacy_premium, cashier_premium, paid_amount, status, total_amount
        FROM bills WHERE id = ?
    ");
    $stmt->execute([$bill_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bill) {
        throw new Exception("Bill not found: $bill_id");
    }

    $pharm_disc = (float)$bill['pharmacy_discount'];
    $cash_disc  = (float)$bill['cashier_discount'];
    $pharm_prem = (float)$bill['pharmacy_premium'];
    $cash_prem  = (float)$bill['cashier_premium'];

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_price), 0) AS raw,
               COALESCE(SUM(discount_amount), 0) AS disc
        FROM bill_items
        WHERE bill_id = ? AND status != 'cancelled'
    ");
    $stmt->execute([$bill_id]);
    $calc = $stmt->fetch(PDO::FETCH_ASSOC);

    $new_subtotal_raw = (float)$calc['raw'];
    $new_items_disc   = (float)$calc['disc'];
    $new_total_disc   = $new_items_disc + $pharm_disc + $cash_disc;
    $new_premium      = $pharm_prem + $cash_prem;
    $new_total        = max(0, $new_subtotal_raw - $pharm_disc - $cash_disc + $new_premium);

    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE bill_id = ?");
    $stmt->execute([$bill_id]);
    $new_paid = (float)$stmt->fetchColumn();

    if ($new_paid > $new_total) {
        $new_paid = $new_total;
    }

    $new_balance = max(0, $new_total - $new_paid);

    if ($new_total <= 0.001) {
        $new_status = 'paid';
    } elseif ($new_balance <= 0.001 && $new_paid > 0) {
        $new_status = 'paid';
    } elseif ($new_paid > 0 && $new_balance > 0) {
        $new_status = 'partial';
    } else {
        $new_status = 'pending';
    }

    $db->prepare("
        UPDATE bills
        SET subtotal = ?, total_discount = ?, premium_amount = ?,
            total_amount = ?, paid_amount = ?, balance = ?, status = ?,
            updated_at = NOW()
        WHERE id = ?
    ")->execute([
        $new_subtotal_raw, $new_total_disc, $new_premium,
        $new_total, $new_paid, $new_balance, $new_status, $bill_id
    ]);

    try {
        $db->prepare("INSERT INTO activity_logs
            (user_id, branch_id, action, details, ip_address, created_at)
            VALUES (?, ?, 'bill_recalculated', ?, ?, NOW())")
           ->execute([
               $user_id, $branch_id,
               $logDetails . " | Total: " . number_format($new_total) .
               " | Paid: " . number_format($new_paid) .
               " | Balance: " . number_format($new_balance) .
               " | Status: $new_status",
               $_SERVER['REMOTE_ADDR'] ?? 'cli'
           ]);
    } catch (Exception $e) {}

    return [
        'new_total'   => $new_total,
        'new_paid'    => $new_paid,
        'new_balance' => $new_balance,
        'new_status'  => $new_status,
    ];
}

function getVisitBillId(PDO $db, int $visit_id): ?int
{
    $stmt = $db->prepare("SELECT id FROM bills WHERE visit_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$visit_id]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

function createBillIfMissing(PDO $db, int $visit_id, int $user_id, int $user_branch_id, string $prefix = 'BILL'): int
{
    $stmt = $db->prepare("SELECT patient_id, branch_id FROM visits WHERE id = ?");
    $stmt->execute([$visit_id]);
    $v = $stmt->fetch(PDO::FETCH_ASSOC);

    $bill_number = $prefix . '-' . date('Ymd') . '-' . str_pad($visit_id, 4, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999);

    $db->prepare("
        INSERT INTO bills
            (bill_number, patient_id, visit_id, branch_id, created_by,
             subtotal, total_amount, paid_amount, balance, status,
             created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, 'pending', NOW(), NOW())
    ")->execute([
        $bill_number,
        $v['patient_id'] ?? null,
        $visit_id,
        $v['branch_id'] ?? $user_branch_id,
        $user_id
    ]);

    return (int)$db->lastInsertId();
}

// ================================================================
// POST HANDLERS
// ================================================================
$alert_message = '';
$alert_type    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        // ----------------------------------------------------
        // 1. UPDATE VISIT BASIC DETAILS
        // ----------------------------------------------------
        if ($action === 'update_visit') {
            $db->beginTransaction();

            $visit_type       = trim($_POST['visit_type'] ?? '');
            $service_id       = (int)($_POST['service_id'] ?? 0) ?: null;
            $consultation_fee = (float)($_POST['consultation_fee'] ?? 0);
            $doctor_id        = (int)($_POST['doctor_id'] ?? 0) ?: null;
            $receptionist_id  = (int)($_POST['receptionist_id'] ?? 0) ?: null;
            $status           = $_POST['status'] ?? 'pending';
            $symptoms         = trim($_POST['symptoms'] ?? '');
            $hpi              = trim($_POST['hpi'] ?? '');
            $physical_exam    = trim($_POST['physical_exam'] ?? '');
            $notes            = trim($_POST['notes'] ?? '');
            $follow_up_date   = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;

            $db->prepare("
                UPDATE visits SET
                    visit_type = ?, service_id = ?, consultation_fee = ?,
                    doctor_id = ?, receptionist_id = ?,
                    status = ?, symptoms = ?, hpi = ?, physical_exam = ?,
                    notes = ?, follow_up_date = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $visit_type, $service_id, $consultation_fee,
                $doctor_id, $receptionist_id,
                $status, $symptoms, $hpi, $physical_exam,
                $notes, $follow_up_date, $visit_id
            ]);

            $bill_id = getVisitBillId($db, $visit_id);
            if ($bill_id) {
                $db->prepare("
                    UPDATE bill_items
                    SET unit_price = ?, total_price = ?, final_price = ?
                    WHERE bill_id = ? AND item_type = 'consultation'
                    LIMIT 1
                ")->execute([$consultation_fee, $consultation_fee, $consultation_fee, $bill_id]);

                recalculateBill($db, $bill_id, $user_id, $user_branch_id,
                    "Updated visit details for visit #$visit_id");
            }

            try {
                $db->prepare("INSERT INTO activity_logs
                    (user_id, branch_id, action, details, ip_address, created_at)
                    VALUES (?, ?, 'edit_visit', ?, ?, NOW())")
                   ->execute([
                       $user_id, $user_branch_id,
                       "Updated visit #$visit_id: type='$visit_type', doctor_id=$doctor_id, receptionist_id=$receptionist_id",
                       $_SERVER['REMOTE_ADDR'] ?? 'cli'
                   ]);
            } catch (Exception $e) {}

            $db->commit();
            $alert_message = "✅ Visit details updated successfully.";
            $alert_type    = 'success';
        }

        // ----------------------------------------------------
        // 2. UPDATE DIAGNOSIS
        // ----------------------------------------------------
        elseif ($action === 'update_diagnosis') {
            $db->beginTransaction();

            $diagnosis    = trim($_POST['diagnosis'] ?? '');
            $disease_id   = (int)($_POST['disease_id'] ?? 0) ?: null;
            $disease_code = trim($_POST['disease_code'] ?? '');

            if ($disease_id && empty($disease_code)) {
                $stmt = $db->prepare("SELECT disease_code FROM diseases WHERE id = ?");
                $stmt->execute([$disease_id]);
                $disease_code = $stmt->fetchColumn() ?: '';
            }

            $db->prepare("
                UPDATE visits SET diagnosis = ?, disease_id = ?, disease_code = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$diagnosis, $disease_id, $disease_code, $visit_id]);

            try {
                $db->prepare("INSERT INTO activity_logs
                    (user_id, branch_id, action, details, ip_address, created_at)
                    VALUES (?, ?, 'edit_diagnosis', ?, ?, NOW())")
                   ->execute([
                       $user_id, $user_branch_id,
                       "Updated diagnosis for visit #$visit_id: '$diagnosis'",
                       $_SERVER['REMOTE_ADDR'] ?? 'cli'
                   ]);
            } catch (Exception $e) {}

            $db->commit();
            $alert_message = "✅ Diagnosis updated successfully.";
            $alert_type    = 'success';
        }

        // ----------------------------------------------------
        // 3. ADD LAB TEST
        // ----------------------------------------------------
        elseif ($action === 'add_lab_test') {
            $db->beginTransaction();

            $test_id = (int)($_POST['test_id'] ?? 0);
            if (!$test_id) throw new Exception("Please select a lab test.");

            $stmt = $db->prepare("SELECT id, test_name, price, category FROM lab_tests_catalog WHERE id = ? AND is_active = 1");
            $stmt->execute([$test_id]);
            $catalog = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$catalog) throw new Exception("Lab test not found in catalog.");

            $test_name  = $catalog['test_name'];
            $test_price = (float)$catalog['price'];
            $category   = $catalog['category'] ?? 'Lab Tests';

            $stmt = $db->prepare("SELECT patient_id FROM visits WHERE id = ?");
            $stmt->execute([$visit_id]);
            $patient_id = (int)$stmt->fetchColumn();

            $db->prepare("
                INSERT INTO lab_tests
                    (visit_id, patient_id, doctor_id, test_id, test_name, test_price,
                     status, branch_id, requested_by_id, requested_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW(), NOW())
            ")->execute([
                $visit_id, $patient_id, $user_id,
                $test_id, $test_name, $test_price,
                $user_branch_id, $user_id
            ]);
            $new_lab_test_id = (int)$db->lastInsertId();

            $bill_id = getVisitBillId($db, $visit_id);
            if (!$bill_id) {
                $bill_id = createBillIfMissing($db, $visit_id, $user_id, $user_branch_id, 'BILL-LAB');
            }

            $db->prepare("
                INSERT INTO bill_items
                    (bill_id, patient_id, branch_id, item_type, item_id, item_name,
                     description, quantity, unit_price, total_price, discount_amount,
                     final_price, reference_id, reference_type, status, created_at, updated_at)
                VALUES (?, ?, ?, 'lab_test', ?, ?, ?, 1, ?, ?, 0, ?, ?, 'lab_request', 'pending', NOW(), NOW())
            ")->execute([
                $bill_id, $patient_id, $user_branch_id,
                $new_lab_test_id, $test_name, $category,
                $test_price, $test_price, $test_price,
                $new_lab_test_id
            ]);

            recalculateBill($db, $bill_id, $user_id, $user_branch_id,
                "Added lab test '$test_name' (" . money($test_price) . ") to visit #$visit_id");

            try {
                $db->prepare("INSERT INTO activity_logs
                    (user_id, branch_id, patient_id, action, details, ip_address, created_at)
                    VALUES (?, ?, ?, 'add_lab_test', ?, ?, NOW())")
                   ->execute([
                       $user_id, $user_branch_id, $patient_id,
                       "Added lab test '$test_name' (" . money($test_price) . ") to visit #$visit_id",
                       $_SERVER['REMOTE_ADDR'] ?? 'cli'
                   ]);
            } catch (Exception $e) {}

            $db->commit();
            $alert_message = "✅ Lab test '$test_name' added successfully.";
            $alert_type    = 'success';
        }

        // ----------------------------------------------------
        // 4. DELETE LAB TEST
        // ----------------------------------------------------
        elseif ($action === 'delete_lab_test') {
            $db->beginTransaction();

            $lab_test_id = (int)($_POST['lab_test_id'] ?? 0);
            if (!$lab_test_id) throw new Exception("Invalid lab test ID.");

            $stmt = $db->prepare("SELECT id, test_name, test_price, visit_id, patient_id FROM lab_tests WHERE id = ?");
            $stmt->execute([$lab_test_id]);
            $lab = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$lab) throw new Exception("Lab test not found.");

            $test_name  = $lab['test_name'];
            $patient_id = (int)$lab['patient_id'];

            $bill_id = getVisitBillId($db, $visit_id);
            if ($bill_id) {
                $db->prepare("
                    DELETE FROM bill_items
                    WHERE bill_id = ? AND item_type = 'lab_test' AND item_id = ?
                ")->execute([$bill_id, $lab_test_id]);
            }

            $db->prepare("DELETE FROM lab_tests WHERE id = ?")->execute([$lab_test_id]);

            if ($bill_id) {
                recalculateBill($db, $bill_id, $user_id, $user_branch_id,
                    "Deleted lab test '$test_name' from visit #$visit_id");
            }

            try {
                $db->prepare("INSERT INTO activity_logs
                    (user_id, branch_id, patient_id, action, details, ip_address, created_at)
                    VALUES (?, ?, ?, 'delete_lab_test', ?, ?, NOW())")
                   ->execute([
                       $user_id, $user_branch_id, $patient_id,
                       "Deleted lab test '$test_name' (ID: $lab_test_id) from visit #$visit_id",
                       $_SERVER['REMOTE_ADDR'] ?? 'cli'
                   ]);
            } catch (Exception $e) {}

            $db->commit();
            $alert_message = "✅ Lab test '$test_name' deleted. Bill recalculated.";
            $alert_type    = 'success';
        }

        // ----------------------------------------------------
        // 5. ADD MEDICATION
        // ----------------------------------------------------
        elseif ($action === 'add_medication') {
            $db->beginTransaction();

            $inventory_id = (int)($_POST['inventory_id'] ?? 0);
            $quantity     = max(1, (int)($_POST['quantity'] ?? 1));
            $dosage       = trim($_POST['dosage'] ?? '');
            $frequency    = trim($_POST['frequency'] ?? '');
            $duration     = trim($_POST['duration'] ?? '');
            $route        = trim($_POST['route'] ?? '');
            $instructions = trim($_POST['instructions'] ?? '');

            if (!$inventory_id) throw new Exception("Please select a medication.");

            $stmt = $db->prepare("
                SELECT id, medication_name, quantity AS stock, selling_price, unit
                FROM medications_inventory
                WHERE id = ? AND branch_id = ?
            ");
            $stmt->execute([$inventory_id, $user_branch_id]);
            $med = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$med) throw new Exception("Medication not found in inventory for this branch.");

            $med_name   = $med['medication_name'];
            $unit_price = (float)$med['selling_price'];
            $total_price = $unit_price * $quantity;

            $stmt = $db->prepare("SELECT patient_id FROM visits WHERE id = ?");
            $stmt->execute([$visit_id]);
            $patient_id = (int)$stmt->fetchColumn();

            $stmt = $db->prepare("SELECT id FROM prescriptions WHERE visit_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$visit_id]);
            $prescription_id = $stmt->fetchColumn();

            if (!$prescription_id) {
                $prescription_number = 'RX-' . date('Ymd') . '-' . str_pad($visit_id, 4, '0', STR_PAD_LEFT);
                $db->prepare("
                    INSERT INTO prescriptions
                        (prescription_number, visit_id, patient_id, doctor_id,
                         status, branch_id, created_at, updated_at)
                    VALUES (?, ?, ?, ?, 'pending', ?, NOW(), NOW())
                ")->execute([
                    $prescription_number, $visit_id, $patient_id,
                    $user_id, $user_branch_id
                ]);
                $prescription_id = (int)$db->lastInsertId();
            }

            $db->prepare("
                INSERT INTO prescription_items
                    (prescription_id, patient_id, inventory_id, medication_name,
                     dosage, frequency, quantity, original_quantity, duration, route,
                     instructions, unit_price, total_price, original_total_price,
                     branch_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $prescription_id, $patient_id, $inventory_id, $med_name,
                $dosage, $frequency, $quantity, $quantity, $duration, $route,
                $instructions, $unit_price, $total_price, $total_price,
                $user_branch_id
            ]);
            $new_item_id = (int)$db->lastInsertId();

            $bill_id = getVisitBillId($db, $visit_id);
            if (!$bill_id) {
                $bill_id = createBillIfMissing($db, $visit_id, $user_id, $user_branch_id, 'BILL-MED');
            }

            $db->prepare("
                INSERT INTO bill_items
                    (bill_id, patient_id, branch_id, item_type, item_id, item_name,
                     description, quantity, unit_price, total_price, discount_amount,
                     final_price, reference_id, reference_type, status, created_at, updated_at)
                VALUES (?, ?, ?, 'medication', ?, ?, ?, ?, ?, ?, 0, ?, ?, 'prescription', 'pending', NOW(), NOW())
            ")->execute([
                $bill_id, $patient_id, $user_branch_id,
                $inventory_id, $med_name, trim($dosage . ' ' . $frequency),
                $quantity, $unit_price, $total_price, $total_price,
                $new_item_id
            ]);

            recalculateBill($db, $bill_id, $user_id, $user_branch_id,
                "Added medication '$med_name' x$quantity to visit #$visit_id");

            try {
                $db->prepare("INSERT INTO activity_logs
                    (user_id, branch_id, patient_id, action, details, ip_address, created_at)
                    VALUES (?, ?, ?, 'add_medication', ?, ?, NOW())")
                   ->execute([
                       $user_id, $user_branch_id, $patient_id,
                       "Added medication '$med_name' x$quantity (" . money($total_price) . ") to visit #$visit_id",
                       $_SERVER['REMOTE_ADDR'] ?? 'cli'
                   ]);
            } catch (Exception $e) {}

            $db->commit();
            $alert_message = "✅ Medication '$med_name' x$quantity added successfully.";
            $alert_type    = 'success';
        }

        // ----------------------------------------------------
        // 6. DELETE MEDICATION
        // ----------------------------------------------------
        elseif ($action === 'delete_medication') {
            $db->beginTransaction();

            $item_id = (int)($_POST['prescription_item_id'] ?? 0);
            if (!$item_id) throw new Exception("Invalid prescription item ID.");

            $stmt = $db->prepare("
                SELECT pi.id, pi.inventory_id, pi.medication_name, pi.quantity,
                       pi.unit_price, pi.total_price, pi.status,
                       p.status AS prescription_status,
                       p.visit_id, pi.patient_id
                FROM prescription_items pi
                INNER JOIN prescriptions p ON pi.prescription_id = p.id
                WHERE pi.id = ?
            ");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) throw new Exception("Prescription item not found.");

            $med_name       = $item['medication_name'];
            $quantity       = (int)$item['quantity'];
            $inventory_id   = (int)($item['inventory_id'] ?? 0);
            $patient_id     = (int)$item['patient_id'];
            $item_status    = strtolower($item['status'] ?? $item['prescription_status'] ?? 'pending');
            $stock_reversed = false;

            if (in_array($item_status, ['pending', 'confirmed'], true) && $inventory_id > 0) {
                $db->prepare("
                    UPDATE medications_inventory
                    SET quantity = quantity + ?, updated_at = NOW()
                    WHERE id = ?
                ")->execute([$quantity, $inventory_id]);
                $stock_reversed = true;

                try {
                    $stmtStock = $db->prepare("SELECT quantity FROM medications_inventory WHERE id = ?");
                    $stmtStock->execute([$inventory_id]);
                    $new_stock = (int)$stmtStock->fetchColumn();
                    $db->prepare("
                        INSERT INTO stock_movements
                            (inventory_id, patient_id, movement_type, quantity,
                             previous_stock, new_stock, reference_type, reference_id,
                             performed_by, branch_id, notes, created_at)
                        VALUES (?, ?, 'in', ?, ?, ?, 'prescription', ?, ?, ?, ?, NOW())
                    ")->execute([
                        $inventory_id, $patient_id, $quantity,
                        $new_stock - $quantity, $new_stock,
                        $item_id, $user_id, $user_branch_id,
                        "Stock returned due to deletion of prescription item #$item_id"
                    ]);
                } catch (Exception $e) {}
            }

            $db->prepare("DELETE FROM prescription_items WHERE id = ?")->execute([$item_id]);

            $bill_id = getVisitBillId($db, $visit_id);
            if ($bill_id) {
                $db->prepare("
                    DELETE FROM bill_items
                    WHERE bill_id = ? AND item_type = 'medication' AND reference_id = ?
                ")->execute([$bill_id, $item_id]);

                recalculateBill($db, $bill_id, $user_id, $user_branch_id,
                    "Deleted medication '$med_name' x$quantity from visit #$visit_id" .
                    ($stock_reversed ? " (Stock returned)" : " (Dispensed - stock NOT returned)")
                );
            }

            try {
                $db->prepare("INSERT INTO activity_logs
                    (user_id, branch_id, patient_id, action, details, ip_address, created_at)
                    VALUES (?, ?, ?, 'delete_medication', ?, ?, NOW())")
                   ->execute([
                       $user_id, $user_branch_id, $patient_id,
                       "Deleted medication '$med_name' x$quantity (status: $item_status) from visit #$visit_id" .
                       ($stock_reversed ? " | STOCK RETURNED +$quantity" : " | DISPENSED - no stock return"),
                       $_SERVER['REMOTE_ADDR'] ?? 'cli'
                   ]);
            } catch (Exception $e) {}

            $db->commit();
            $msg = "✅ Medication '$med_name' deleted.";
            if ($stock_reversed) {
                $msg .= " Stock returned: +$quantity";
            } else {
                $msg .= " (Dispensed - stock not reversed)";
            }
            $msg .= " Bill recalculated.";
            $alert_message = $msg;
            $alert_type    = 'success';
        }

        // ----------------------------------------------------
        // 7. ADD PROCEDURE
        // ----------------------------------------------------
        elseif ($action === 'add_procedure') {
            $db->beginTransaction();

            $procedure_id = (int)($_POST['procedure_id'] ?? 0);
            if (!$procedure_id) throw new Exception("Please select a procedure.");

            $stmt = $db->prepare("
                SELECT id, procedure_name, price, category
                FROM procedures_catalog
                WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$procedure_id]);
            $proc = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$proc) throw new Exception("Procedure not found in catalog.");

            $proc_name  = $proc['procedure_name'];
            $proc_price = (float)$proc['price'];
            $category   = $proc['category'] ?? 'Procedures';

            $stmt = $db->prepare("SELECT patient_id FROM visits WHERE id = ?");
            $stmt->execute([$visit_id]);
            $patient_id = (int)$stmt->fetchColumn();

            $db->prepare("
                INSERT INTO procedures
                    (visit_id, patient_id, doctor_id, procedure_id, procedure_name,
                     procedure_category, category, procedure_price, status,
                     branch_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())
            ")->execute([
                $visit_id, $patient_id, $user_id,
                $procedure_id, $proc_name, $category, $category,
                $proc_price, $user_branch_id
            ]);
            $new_proc_id = (int)$db->lastInsertId();

            $bill_id = getVisitBillId($db, $visit_id);
            if (!$bill_id) {
                $bill_id = createBillIfMissing($db, $visit_id, $user_id, $user_branch_id, 'BILL-PROC');
            }

            $db->prepare("
                INSERT INTO bill_items
                    (bill_id, patient_id, branch_id, item_type, item_id, item_name,
                     description, quantity, unit_price, total_price, discount_amount,
                     final_price, reference_id, reference_type, status, created_at, updated_at)
                VALUES (?, ?, ?, 'procedure', ?, ?, ?, 1, ?, ?, 0, ?, ?, 'procedure', 'pending', NOW(), NOW())
            ")->execute([
                $bill_id, $patient_id, $user_branch_id,
                $new_proc_id, $proc_name, $category,
                $proc_price, $proc_price, $proc_price,
                $new_proc_id
            ]);

            recalculateBill($db, $bill_id, $user_id, $user_branch_id,
                "Added procedure '$proc_name' (" . money($proc_price) . ") to visit #$visit_id");

            try {
                $db->prepare("INSERT INTO activity_logs
                    (user_id, branch_id, patient_id, action, details, ip_address, created_at)
                    VALUES (?, ?, ?, 'add_procedure', ?, ?, NOW())")
                   ->execute([
                       $user_id, $user_branch_id, $patient_id,
                       "Added procedure '$proc_name' (" . money($proc_price) . ") to visit #$visit_id",
                       $_SERVER['REMOTE_ADDR'] ?? 'cli'
                   ]);
            } catch (Exception $e) {}

            $db->commit();
            $alert_message = "✅ Procedure '$proc_name' added successfully.";
            $alert_type    = 'success';
        }

        // ----------------------------------------------------
        // 8. DELETE PROCEDURE
        // ----------------------------------------------------
        elseif ($action === 'delete_procedure') {
            $db->beginTransaction();

            $procedure_id = (int)($_POST['procedure_record_id'] ?? 0);
            if (!$procedure_id) throw new Exception("Invalid procedure ID.");

            $stmt = $db->prepare("SELECT id, procedure_name, procedure_price, patient_id FROM procedures WHERE id = ?");
            $stmt->execute([$procedure_id]);
            $proc = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$proc) throw new Exception("Procedure not found.");

            $proc_name  = $proc['procedure_name'];
            $patient_id = (int)$proc['patient_id'];

            $bill_id = getVisitBillId($db, $visit_id);
            if ($bill_id) {
                $db->prepare("
                    DELETE FROM bill_items
                    WHERE bill_id = ? AND item_type = 'procedure' AND item_id = ?
                ")->execute([$bill_id, $procedure_id]);
            }

            $db->prepare("DELETE FROM procedures WHERE id = ?")->execute([$procedure_id]);

            if ($bill_id) {
                recalculateBill($db, $bill_id, $user_id, $user_branch_id,
                    "Deleted procedure '$proc_name' from visit #$visit_id");
            }

            try {
                $db->prepare("INSERT INTO activity_logs
                    (user_id, branch_id, patient_id, action, details, ip_address, created_at)
                    VALUES (?, ?, ?, 'delete_procedure', ?, ?, NOW())")
                   ->execute([
                       $user_id, $user_branch_id, $patient_id,
                       "Deleted procedure '$proc_name' (ID: $procedure_id) from visit #$visit_id",
                       $_SERVER['REMOTE_ADDR'] ?? 'cli'
                   ]);
            } catch (Exception $e) {}

            $db->commit();
            $alert_message = "✅ Procedure '$proc_name' deleted. Bill recalculated.";
            $alert_type    = 'success';
        }

        // ----------------------------------------------------
        // 9. ADD EQUIPMENT
        // ----------------------------------------------------
        elseif ($action === 'add_equipment') {
            $db->beginTransaction();

            $equipment_id = (int)($_POST['equipment_id'] ?? 0);
            $quantity     = max(1, (int)($_POST['equipment_qty'] ?? 1));
            if (!$equipment_id) throw new Exception("Please select equipment.");

            $stmt = $db->prepare("
                SELECT id, equipment_name, selling_price, category, unit
                FROM medical_equipment
                WHERE id = ? AND branch_id = ?
            ");
            $stmt->execute([$equipment_id, $user_branch_id]);
            $eqp = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$eqp) throw new Exception("Equipment not found for this branch.");

            $eqp_name    = $eqp['equipment_name'];
            $unit_price  = (float)$eqp['selling_price'];
            $total_price = $unit_price * $quantity;
            $category    = $eqp['category'] ?? 'Equipment';

            $stmt = $db->prepare("SELECT patient_id FROM visits WHERE id = ?");
            $stmt->execute([$visit_id]);
            $patient_id = (int)$stmt->fetchColumn();

            $bill_id = getVisitBillId($db, $visit_id);
            if (!$bill_id) {
                $bill_id = createBillIfMissing($db, $visit_id, $user_id, $user_branch_id, 'BILL-EQP');
            }

            $db->prepare("
                INSERT INTO bill_items
                    (bill_id, patient_id, branch_id, item_type, item_id, item_name,
                     description, quantity, unit_price, total_price, discount_amount,
                     final_price, reference_id, reference_type, status, created_at, updated_at)
                VALUES (?, ?, ?, 'equipment', ?, ?, ?, ?, ?, ?, 0, ?, ?, 'inventory', 'pending', NOW(), NOW())
            ")->execute([
                $bill_id, $patient_id, $user_branch_id,
                $equipment_id, $eqp_name, $category,
                $quantity, $unit_price, $total_price, $total_price,
                $equipment_id
            ]);

            recalculateBill($db, $bill_id, $user_id, $user_branch_id,
                "Added equipment '$eqp_name' x$quantity to visit #$visit_id");

            try {
                $db->prepare("INSERT INTO activity_logs
                    (user_id, branch_id, patient_id, action, details, ip_address, created_at)
                    VALUES (?, ?, ?, 'add_equipment', ?, ?, NOW())")
                   ->execute([
                       $user_id, $user_branch_id, $patient_id,
                       "Added equipment '$eqp_name' x$quantity (" . money($total_price) . ") to visit #$visit_id",
                       $_SERVER['REMOTE_ADDR'] ?? 'cli'
                   ]);
            } catch (Exception $e) {}

            $db->commit();
            $alert_message = "✅ Equipment '$eqp_name' x$quantity added successfully.";
            $alert_type    = 'success';
        }

        // ----------------------------------------------------
        // 10. DELETE EQUIPMENT
        // ----------------------------------------------------
        elseif ($action === 'delete_equipment') {
            $db->beginTransaction();

            $bill_item_id = (int)($_POST['bill_item_id'] ?? 0);
            if (!$bill_item_id) throw new Exception("Invalid bill item ID.");

            $stmt = $db->prepare("
                SELECT id, bill_id, item_name, total_price, quantity
                FROM bill_items
                WHERE id = ? AND item_type = 'equipment'
            ");
            $stmt->execute([$bill_item_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) throw new Exception("Equipment bill item not found.");

            $eqp_name = $item['item_name'];
            $bill_id  = (int)$item['bill_id'];

            $db->prepare("DELETE FROM bill_items WHERE id = ?")->execute([$bill_item_id]);

            recalculateBill($db, $bill_id, $user_id, $user_branch_id,
                "Deleted equipment '$eqp_name' from visit #$visit_id");

            try {
                $db->prepare("INSERT INTO activity_logs
                    (user_id, branch_id, action, details, ip_address, created_at)
                    VALUES (?, ?, 'delete_equipment', ?, ?, NOW())")
                   ->execute([
                       $user_id, $user_branch_id,
                       "Deleted equipment '$eqp_name' (Bill Item ID: $bill_item_id) from visit #$visit_id",
                       $_SERVER['REMOTE_ADDR'] ?? 'cli'
                   ]);
            } catch (Exception $e) {}

            $db->commit();
            $alert_message = "✅ Equipment '$eqp_name' deleted. Bill recalculated.";
            $alert_type    = 'success';
        }

        // ----------------------------------------------------
        // 11. UPDATE BILL DISCOUNTS / PREMIUMS
        // ----------------------------------------------------
        elseif ($action === 'update_bill_totals') {
            $db->beginTransaction();

            $bill_id           = (int)($_POST['bill_id'] ?? 0);
            $pharmacy_discount = (float)($_POST['pharmacy_discount'] ?? 0);
            $cashier_discount  = (float)($_POST['cashier_discount'] ?? 0);
            $pharmacy_premium  = (float)($_POST['pharmacy_premium'] ?? 0);
            $cashier_premium   = (float)($_POST['cashier_premium'] ?? 0);
            $pharmacy_note     = trim($_POST['pharmacy_premium_note'] ?? '');
            $cashier_note      = trim($_POST['cashier_premium_note'] ?? '');

            if (!$bill_id) throw new Exception("Invalid bill ID.");

            $db->prepare("
                UPDATE bills
                SET pharmacy_discount = ?, cashier_discount = ?,
                    pharmacy_premium = ?, cashier_premium = ?,
                    pharmacy_premium_note = ?, cashier_premium_note = ?,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $pharmacy_discount, $cashier_discount,
                $pharmacy_premium, $cashier_premium,
                $pharmacy_note, $cashier_note, $bill_id
            ]);

            recalculateBill($db, $bill_id, $user_id, $user_branch_id,
                "Updated discounts/premiums for bill #$bill_id");

            $db->commit();
            $alert_message = "✅ Bill totals updated successfully.";
            $alert_type    = 'success';
        }

        // ----------------------------------------------------
        // 12. UPDATE MEDICATION STATUS
        // ----------------------------------------------------
        elseif ($action === 'update_medication_status') {
            $db->beginTransaction();

            $item_id    = (int)($_POST['prescription_item_id'] ?? 0);
            $new_status = $_POST['new_status'] ?? 'pending';
            $allowed    = ['pending', 'confirmed', 'dispensed', 'cancelled'];

            if (!$item_id || !in_array($new_status, $allowed, true)) {
                throw new Exception("Invalid status update request.");
            }

            $stmt = $db->prepare("
                SELECT pi.id, pi.inventory_id, pi.medication_name, pi.quantity,
                       pi.status, pi.prescription_id, p.status AS presc_status
                FROM prescription_items pi
                INNER JOIN prescriptions p ON pi.prescription_id = p.id
                WHERE pi.id = ?
            ");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) throw new Exception("Prescription item not found.");

            $old_status   = strtolower($item['status'] ?? 'pending');
            $med_name     = $item['medication_name'];
            $quantity     = (int)$item['quantity'];
            $inventory_id = (int)($item['inventory_id'] ?? 0);

            if (in_array($old_status, ['pending', 'confirmed'], true)
                && $new_status === 'dispensed'
                && $inventory_id > 0) {

                $stmtStock = $db->prepare("SELECT quantity FROM medications_inventory WHERE id = ?");
                $stmtStock->execute([$inventory_id]);
                $current = (int)$stmtStock->fetchColumn();

                if ($current < $quantity) {
                    throw new Exception("Insufficient stock for '$med_name'. Available: $current, Required: $quantity");
                }

                $db->prepare("
                    UPDATE medications_inventory
                    SET quantity = quantity - ?, updated_at = NOW()
                    WHERE id = ?
                ")->execute([$quantity, $inventory_id]);

                try {
                    $db->prepare("
                        INSERT INTO stock_movements
                            (inventory_id, movement_type, quantity,
                             previous_stock, new_stock, reference_type, reference_id,
                             performed_by, branch_id, notes, created_at)
                        VALUES (?, 'out', ?, ?, ?, 'prescription', ?, ?, ?, ?, NOW())
                    ")->execute([
                        $inventory_id, $quantity,
                        $current, $current - $quantity,
                        $item_id, $user_id, $user_branch_id,
                        "Dispensed via status update to 'dispensed'"
                    ]);
                } catch (Exception $e) {}
            }

            $db->prepare("
                UPDATE prescription_items SET status = ?, dispensed_at = ?, dispensed_by = ?
                WHERE id = ?
            ")->execute([
                $new_status,
                $new_status === 'dispensed' ? date('Y-m-d H:i:s') : null,
                $new_status === 'dispensed' ? $user_id : null,
                $item_id
            ]);

            $stmt = $db->prepare("
                SELECT COUNT(*) FROM prescription_items
                WHERE prescription_id = ? AND status NOT IN ('dispensed', 'cancelled')
            ");
            $stmt->execute([$item['prescription_id']]);
            $remaining = (int)$stmt->fetchColumn();
            if ($remaining === 0) {
                $db->prepare("UPDATE prescriptions SET status = 'dispensed', dispensed_at = NOW() WHERE id = ?")
                   ->execute([$item['prescription_id']]);
            }

            try {
                $db->prepare("INSERT INTO activity_logs
                    (user_id, branch_id, action, details, ip_address, created_at)
                    VALUES (?, ?, 'update_medication_status', ?, ?, NOW())")
                   ->execute([
                       $user_id, $user_branch_id,
                       "Medication '$med_name' status: $old_status → $new_status",
                       $_SERVER['REMOTE_ADDR'] ?? 'cli'
                   ]);
            } catch (Exception $e) {}

            $db->commit();
            $alert_message = "✅ Medication status updated: $old_status → $new_status.";
            $alert_type    = 'success';
        }

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $alert_message = "❌ Error: " . $e->getMessage();
        $alert_type    = 'error';
    }
}

// ================================================================
// DETECT SECTION AFTER POST (for auto-scroll - NO MORE JUMP TO TOP)
// ================================================================
$scroll_section = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && !empty($alert_message)) {
    $action_to_section = [
        'update_visit'              => 'visit',
        'update_diagnosis'          => 'diagnosis',
        'add_lab_test'              => 'lab',
        'delete_lab_test'           => 'lab',
        'add_medication'            => 'medication',
        'delete_medication'         => 'medication',
        'update_medication_status'  => 'medication',
        'add_procedure'             => 'procedure',
        'delete_procedure'          => 'procedure',
        'add_equipment'             => 'equipment',
        'delete_equipment'          => 'equipment',
        'update_bill_totals'        => 'bill',
    ];
    if (isset($action_to_section[$_POST['action']])) {
        $scroll_section = $action_to_section[$_POST['action']];
    }
}

// ================================================================
// CURRENCY
// ================================================================
$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['setting_value'])) {
        $currency = $row['setting_value'];
    }
} catch (Exception $e) {}

// ================================================================
// FETCH VISIT
// ================================================================
$visit = null;
try {
    $stmt = $db->prepare("
        SELECT
            v.*,
            pat.patient_id AS patient_code,
            pat.full_name  AS patient_name,
            pat.phone      AS patient_phone,
            pat.gender, pat.date_of_birth, pat.address,
            pat.blood_group, pat.allergies,
            d.full_name    AS doctor_name,
            d.role         AS doctor_role,
            r.full_name    AS receptionist_name,
            r.role         AS receptionist_role,
            ab.full_name   AS assigned_by_name,
            br.name        AS branch_name
        FROM visits v
        LEFT JOIN patients pat ON v.patient_id = pat.id
        LEFT JOIN users    d   ON v.doctor_id = d.id
        LEFT JOIN users    r   ON v.receptionist_id = r.id
        LEFT JOIN users    ab  ON v.assigned_by_id = ab.id
        LEFT JOIN branches br  ON v.branch_id = br.id
        WHERE v.id = ?
    ");
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching visit: " . $e->getMessage());
}

if (!$visit) {
    die("Visit not found.");
}

$visit_branch_id = (int)($visit['branch_id'] ?? $user_branch_id);

// ================================================================
// FETCH BILLS
// ================================================================
$bills = [];
try {
    $stmt = $db->prepare("
        SELECT b.*, u.full_name AS created_by_name
        FROM bills b
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.visit_id = ?
        ORDER BY b.id ASC
    ");
    $stmt->execute([$visit_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$bill_ids = array_column($bills, 'id');

$total_billed     = 0;
$total_paid       = 0;
$total_balance    = 0;
$total_discount   = 0;
$total_premium    = 0;
$pharm_disc_total = 0;
$cash_disc_total  = 0;

foreach ($bills as $b) {
    $total_billed     += (float)$b['total_amount'];
    $total_paid       += (float)$b['paid_amount'];
    $total_balance    += (float)$b['balance'];
    $total_discount   += (float)$b['total_discount'];
    $total_premium    += (float)$b['premium_amount'];
    $pharm_disc_total += (float)$b['pharmacy_discount'];
    $cash_disc_total  += (float)$b['cashier_discount'];
}

$primary_bill_id = !empty($bill_ids) ? (int)end($bill_ids) : null;

// ================================================================
// FETCH LAB TESTS
// ================================================================
$lab_tests = [];
try {
    $stmt = $db->prepare("
        SELECT lt.*,
               u1.full_name AS requested_by_name,
               u2.full_name AS performed_by_name
        FROM lab_tests lt
        LEFT JOIN users u1 ON lt.requested_by_id = u1.id
        LEFT JOIN users u2 ON lt.performed_by = u2.id
        WHERE lt.visit_id = ?
        ORDER BY lt.id ASC
    ");
    $stmt->execute([$visit_id]);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// FETCH PRESCRIPTION ITEMS
// ================================================================
$prescription_items = [];
try {
    $stmt = $db->prepare("
        SELECT pi.*,
               p.prescription_number,
               p.status AS prescription_status,
               mi.quantity AS current_stock,
               mi.unit AS stock_unit
        FROM prescription_items pi
        INNER JOIN prescriptions p ON pi.prescription_id = p.id
        LEFT JOIN medications_inventory mi ON pi.inventory_id = mi.id
        WHERE p.visit_id = ?
        ORDER BY pi.id ASC
    ");
    $stmt->execute([$visit_id]);
    $prescription_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// FETCH PROCEDURES
// ================================================================
$procedures = [];
try {
    $stmt = $db->prepare("
        SELECT pr.*, u.full_name AS doctor_name
        FROM procedures pr
        LEFT JOIN users u ON pr.doctor_id = u.id
        WHERE pr.visit_id = ?
        ORDER BY pr.id ASC
    ");
    $stmt->execute([$visit_id]);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// FETCH EQUIPMENT ITEMS
// ================================================================
$equipment_items = [];
if (!empty($bill_ids)) {
    $ph = implode(',', array_fill(0, count($bill_ids), '?'));
    try {
        $stmt = $db->prepare("
            SELECT bi.*, b.bill_number
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE bi.bill_id IN ($ph) AND bi.item_type = 'equipment'
            ORDER BY bi.id ASC
        ");
        $stmt->execute($bill_ids);
        $equipment_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// ================================================================
// FETCH DROPDOWN LISTS
// ================================================================

// ✅ VISIT TYPES: ONLY services with category = 'Consultation'
$visit_types = [];
try {
    $stmt = $db->prepare("
        SELECT s.id, s.service_name, s.price, s.category_id, sc.category_name
        FROM services s
        INNER JOIN service_categories sc ON s.category_id = sc.id
        WHERE s.is_active = 1
          AND s.branch_id = ?
          AND LOWER(sc.category_name) = 'consultation'
        ORDER BY s.service_name ASC
    ");
    $stmt->execute([$visit_branch_id]);
    $visit_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Doctors
$doctors = [];
try {
    $stmt = $db->prepare("
        SELECT id, full_name, role, specialty, email, phone
        FROM users
        WHERE role = 'doctor' AND status = 'active' AND (branch_id = ? OR branch_id IS NULL)
        ORDER BY full_name ASC
    ");
    $stmt->execute([$visit_branch_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Receptionists
$receptionists = [];
try {
    $stmt = $db->prepare("
        SELECT id, full_name, role
        FROM users
        WHERE role = 'reception' AND status = 'active' AND (branch_id = ? OR branch_id IS NULL)
        ORDER BY full_name ASC
    ");
    $stmt->execute([$visit_branch_id]);
    $receptionists = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Diseases
$diseases_list = [];
try {
    $stmt = $db->prepare("
        SELECT id, disease_code, disease_name, icd_code, category, treatment
        FROM diseases
        WHERE is_active = 1 AND (branch_id = ? OR branch_id IS NULL)
        ORDER BY disease_name ASC
    ");
    $stmt->execute([$visit_branch_id]);
    $diseases_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Lab tests catalog
$lab_test_catalog = [];
try {
    $stmt = $db->prepare("
        SELECT id, test_name, test_code, category, price, reference_range
        FROM lab_tests_catalog
        WHERE is_active = 1 AND branch_id = ?
        ORDER BY test_name ASC
    ");
    $stmt->execute([$visit_branch_id]);
    $lab_test_catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Procedures catalog
$procedures_catalog = [];
try {
    $stmt = $db->prepare("
        SELECT id, procedure_name, procedure_code, category, price
        FROM procedures_catalog
        WHERE is_active = 1 AND branch_id = ?
        ORDER BY procedure_name ASC
    ");
    $stmt->execute([$visit_branch_id]);
    $procedures_catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Equipment catalog
$equipment_catalog = [];
try {
    $stmt = $db->prepare("
        SELECT id, equipment_name, category, unit, quantity, selling_price
        FROM medical_equipment
        WHERE status = 'active' AND branch_id = ?
        ORDER BY equipment_name ASC
    ");
    $stmt->execute([$visit_branch_id]);
    $equipment_catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Medications inventory
$medications_inventory = [];
try {
    $stmt = $db->prepare("
        SELECT id, medication_name, category, unit, quantity, selling_price
        FROM medications_inventory
        WHERE status = 'active' AND branch_id = ? AND quantity > 0
        ORDER BY medication_name ASC
    ");
    $stmt->execute([$visit_branch_id]);
    $medications_inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// HEADER / SIDEBAR
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../../components/admin_audit_header.php';
include_once __DIR__ . '/../../../components/admin_audit_sidebar.php';

function getInitials($name)
{
    $parts = explode(' ', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Visit • <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></title>
<link rel="icon" href="<?= $logo_path ?>" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ================================================================
   BLUE THEME - FINAL
   ================================================================ */
:root {
    --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;

    --blue-50:  #EFF6FF;
    --blue-100: #DBEAFE;
    --blue-200: #BFDBFE;
    --blue-300: #93C5FD;
    --blue-400: #60A5FA;
    --blue-500: #3B82F6;
    --blue-600: #2563EB;
    --blue-700: #1D4ED8;
    --blue-800: #1E40AF;
    --blue-900: #1E3A8A;

    --primary: #1D4ED8;
    --primary-dark: #1E3A8A;
    --primary-light: #3B82F6;
    --primary-bg: #EFF6FF;
    --primary-soft: #DBEAFE;

    --success: #059669;
    --success-bg: #D1FAE5;
    --danger: #DC2626;
    --danger-bg: #FEE2E2;
    --warning: #D97706;
    --warning-bg: #FEF3C7;

    --slate: #94A3B8;
    --slate-bg: #F1F5F9;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --text-muted: #94A3B8;
    --border-color: #E2E8F0;
    --border-strong: #CBD5E1;

    --shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.06);
    --shadow-md: 0 4px 12px rgba(15, 23, 42, 0.08);
    --shadow-lg: 0 10px 25px rgba(15, 23, 42, 0.1);
    --shadow-xl: 0 20px 50px rgba(15, 23, 42, 0.15);
    --shadow-blue: 0 8px 24px rgba(29, 78, 216, 0.25);

    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --radius-full: 9999px;
}
[data-theme="dark"] {
    --bg-body: #0B1220;
    --bg-card: #111C33;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --text-muted: #64748B;
    --border-color: #1E2E4A;
    --border-strong: #2A3E5F;
    --primary-bg: #12294A;
    --primary-soft: #1E3A8A;
    --slate-bg: #1E2A3D;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: var(--font-primary);
    background: var(--bg-body);
    color: var(--text-primary);
    -webkit-font-smoothing: antialiased;
    line-height: 1.5;
    min-height: 100vh;
}
.font-mono, .money-number {
    font-family: var(--font-mono) !important;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

/* ============== ALERT ============== */
.alert {
    padding: 16px 22px;
    border-radius: var(--radius-md);
    margin-bottom: 18px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-weight: 600;
    font-size: 0.85rem;
    animation: slideDown 0.4s ease;
    border-left: 4px solid;
    box-shadow: var(--shadow-sm);
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.alert.success { background: var(--success-bg); color: var(--success); border-left-color: var(--success); }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left-color: var(--danger); }
.alert i { font-size: 1.2rem; margin-top: 2px; }

/* ============== PAGE HEADER ============== */
.page-header {
    background: linear-gradient(135deg, var(--blue-700) 0%, var(--blue-800) 60%, var(--blue-900) 100%);
    border-radius: var(--radius-lg);
    padding: 24px 28px;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: var(--shadow-blue);
    position: relative;
    overflow: hidden;
}
.page-header::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(147, 197, 253, 0.18) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.page-header::after {
    content: '';
    position: absolute;
    bottom: -60%; left: -5%;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.page-title {
    color: white; font-size: 1.5rem; font-weight: 900;
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    position: relative; z-index: 1;
}
.page-title i { font-size: 1.6rem; color: var(--blue-300); }
.page-subtitle {
    color: rgba(255,255,255,0.92); font-size: 0.8rem;
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    margin-top: 8px; position: relative; z-index: 1;
}
.header-badge {
    background: rgba(255,255,255,0.15); color: white;
    padding: 4px 12px; border-radius: var(--radius-full);
    font-size: 0.68rem; font-weight: 700;
    display: inline-flex; align-items: center; gap: 5px;
    backdrop-filter: blur(10px); border: 1px solid rgba(147, 197, 253, 0.35);
}
.header-badge.solid {
    background: linear-gradient(135deg, var(--blue-500), var(--blue-600));
    border-color: rgba(255,255,255,0.3);
}

.btn-header {
    background: rgba(255,255,255,0.18); color: white;
    border: 1px solid rgba(147, 197, 253, 0.4);
    padding: 10px 16px; border-radius: var(--radius-sm);
    font-weight: 700; font-size: 0.75rem;
    transition: all 0.25s; text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
    backdrop-filter: blur(10px); position: relative; z-index: 1;
    cursor: pointer;
}
.btn-header:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); box-shadow: var(--shadow-md); }

/* ============== CARDS ============== */
.card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    overflow: visible;
    box-shadow: var(--shadow-sm);
    margin-bottom: 20px;
    transition: box-shadow 0.25s;
    scroll-margin-top: 20px;
}
.card:hover { box-shadow: var(--shadow-md); }

.card-header {
    padding: 16px 22px;
    background: linear-gradient(135deg, var(--blue-600), var(--blue-700));
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 10px;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}
.card-header .title {
    color: white; font-size: 0.92rem; font-weight: 800;
    display: flex; align-items: center; gap: 10px;
}
.card-header .title i { color: var(--blue-200); font-size: 1rem; }
.card-header .count {
    color: rgba(255,255,255,0.95); font-size: 0.7rem; font-weight: 700;
    background: rgba(255,255,255,0.2); padding: 5px 12px;
    border-radius: var(--radius-full); backdrop-filter: blur(10px);
    border: 1px solid rgba(147, 197, 253, 0.3);
}
.card-body { padding: 22px; }

/* ============== FORM ============== */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
}
.form-group { display: flex; flex-direction: column; gap: 6px; position: relative; }
.form-group.full { grid-column: 1 / -1; }
.form-label {
    font-size: 0.65rem; color: var(--text-secondary);
    text-transform: uppercase; letter-spacing: 0.06em;
    font-weight: 800; display: flex; align-items: center; gap: 5px;
}
.form-label i { color: var(--blue-600); font-size: 0.75rem; }
.form-input, .form-select, .form-textarea {
    padding: 10px 12px;
    border: 1.5px solid var(--border-color);
    border-radius: var(--radius-sm);
    font-size: 0.82rem;
    font-family: var(--font-primary);
    background: var(--bg-card);
    color: var(--text-primary);
    transition: all 0.2s;
    font-weight: 600;
    width: 100%;
}
.form-textarea { min-height: 80px; resize: vertical; font-weight: 500; }
.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--blue-500);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}
.form-input[readonly] { background: var(--slate-bg); color: var(--text-secondary); cursor: not-allowed; }
.form-help { font-size: 0.65rem; color: var(--text-muted); font-weight: 600; }

/* ============== BUTTONS ============== */
.btn {
    padding: 10px 20px;
    border-radius: var(--radius-sm);
    font-size: 0.78rem;
    font-weight: 800;
    border: none;
    cursor: pointer;
    transition: all 0.25s;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    text-decoration: none;
    font-family: var(--font-primary);
}
.btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }
.btn-primary {
    background: linear-gradient(135deg, var(--blue-600), var(--blue-700));
    color: white;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}
.btn-primary:hover { box-shadow: 0 8px 22px rgba(37, 99, 235, 0.45); }

/* ============== ADD FORM ============== */
.add-form {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding: 18px 22px;
    background: linear-gradient(135deg, var(--blue-50), var(--blue-100));
    border-bottom: 1px solid var(--blue-200);
    align-items: flex-end;
    border-radius: 0;
}
.add-form .form-group { flex: 1; min-width: 160px; margin: 0; }
.add-form .btn { flex-shrink: 0; }

/* ============== SEARCHABLE DROPDOWN ============== */
.searchable { position: relative; width: 100%; }
.searchable-input-wrap { position: relative; display: flex; align-items: center; }
.searchable-input-wrap i.search-icon {
    position: absolute;
    left: 12px;
    color: var(--blue-600);
    font-size: 0.8rem;
    pointer-events: none;
    z-index: 2;
}
.searchable-input-wrap input.search-input {
    padding-left: 34px !important;
    cursor: pointer;
    background: white;
}
.searchable-input-wrap input.search-input:focus {
    border-color: var(--blue-500);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18);
}
.searchable-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    max-height: 280px;
    overflow-y: auto;
    background: white;
    border: 1.5px solid var(--blue-300);
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow-lg);
    z-index: 9999;
    display: none;
    animation: dropdownOpen 0.15s ease;
}
.searchable-dropdown.open { display: block; }
@keyframes dropdownOpen {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
.searchable-dropdown::-webkit-scrollbar { width: 8px; }
.searchable-dropdown::-webkit-scrollbar-track { background: var(--blue-50); }
.searchable-dropdown::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--blue-400), var(--blue-600));
    border-radius: 10px;
}
.searchable-option {
    padding: 10px 14px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    border-bottom: 1px solid var(--blue-50);
    transition: all 0.15s;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    color: var(--text-primary);
}
.searchable-option:last-child { border-bottom: none; }
.searchable-option:hover, .searchable-option.active {
    background: linear-gradient(90deg, var(--blue-50), var(--blue-100));
    color: var(--blue-800);
}
.searchable-option.selected {
    background: linear-gradient(90deg, var(--blue-500), var(--blue-600));
    color: white;
}
.searchable-option .opt-label { flex: 1; font-weight: 700; }
.searchable-option .opt-meta {
    font-size: 0.65rem;
    color: var(--text-secondary);
    font-family: var(--font-mono);
    font-weight: 700;
    background: var(--blue-50);
    padding: 3px 8px;
    border-radius: 5px;
    white-space: nowrap;
}
.searchable-option.selected .opt-meta { background: rgba(255,255,255,0.25); color: white; }
.searchable-option.no-result {
    padding: 14px;
    text-align: center;
    color: var(--text-muted);
    font-style: italic;
    font-weight: 600;
    cursor: default;
    justify-content: center;
}
.searchable-option.no-result:hover { background: white; }

/* ============== TABLES ============== */
.table-wrapper { overflow-x: auto; border-radius: 0 0 var(--radius-lg) var(--radius-lg); }
.table-wrapper::-webkit-scrollbar { height: 8px; }
.table-wrapper::-webkit-scrollbar-track { background: var(--border-color); }
.table-wrapper::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--blue-400), var(--blue-600));
    border-radius: 10px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
    min-width: 800px;
}
.data-table thead th {
    text-align: left;
    padding: 12px 14px;
    font-weight: 800;
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--blue-800);
    background: var(--blue-50);
    white-space: nowrap;
    border-bottom: 2px solid var(--blue-200);
}
.data-table tbody td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
    font-weight: 500;
}
.data-table tbody tr { transition: background 0.15s; }
.data-table tbody tr:hover td { background: var(--blue-50); }
.data-table tbody tr:last-child td { border-bottom: none; }

.item-name { font-weight: 700; font-size: 0.8rem; color: var(--blue-900); }
.item-code {
    font-size: 0.6rem; color: var(--blue-700);
    font-family: var(--font-mono); background: var(--blue-50);
    padding: 2px 7px; border-radius: 4px; display: inline-block; margin-top: 4px;
    border: 1px solid var(--blue-200); font-weight: 700;
}
.item-sub { font-size: 0.68rem; color: var(--text-secondary); margin-top: 3px; font-weight: 600; }
.money-cell { text-align: right; font-family: var(--font-mono); font-weight: 700; white-space: nowrap; }
.money-cell.total { color: var(--blue-700); font-weight: 800; }
.qty-cell { text-align: center; font-weight: 800; color: var(--blue-600); font-family: var(--font-mono); }
.status-cell { text-align: center; }
.actions-cell { text-align: center; white-space: nowrap; width: 100px; }

/* ============== STATUS BADGES ============== */
.status-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: var(--radius-full);
    font-size: 0.62rem; font-weight: 800; text-transform: uppercase;
    white-space: nowrap;
}
.status-badge.paid { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.status-badge.pending { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
.status-badge.partial { background: var(--blue-100); color: var(--blue-700); border: 1px solid var(--blue-400); }
.status-badge.cancelled { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
.status-badge.completed { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.status-badge.in_progress { background: var(--blue-100); color: var(--blue-700); border: 1px solid var(--blue-400); }
.status-badge.dispensed { background: var(--blue-200); color: var(--blue-800); border: 1px solid var(--blue-500); }
.status-badge.confirmed { background: var(--blue-100); color: var(--blue-700); border: 1px solid var(--blue-400); }

/* ============== ACTION BUTTONS ============== */
.btn-action {
    width: 34px; height: 34px; border-radius: 8px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.8rem; border: none; cursor: pointer;
    transition: all 0.25s; text-decoration: none;
}
.btn-action:hover { transform: translateY(-2px) scale(1.08); }
.btn-action.delete { background: rgba(220, 38, 38, 0.1); color: #DC2626; border: 1px solid rgba(220, 38, 38, 0.2); }
.btn-action.delete:hover { background: #DC2626; color: white; box-shadow: 0 6px 16px rgba(220,38,38,0.4); }

/* ============== BILL SUMMARY ============== */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 12px;
    padding: 20px;
    background: linear-gradient(135deg, var(--blue-50), var(--blue-100));
}
.summary-box {
    background: white;
    border-radius: var(--radius-md);
    padding: 14px 12px;
    text-align: center;
    box-shadow: var(--shadow-sm);
    border: 1.5px solid var(--blue-200);
    transition: all 0.25s;
}
.summary-box:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-blue);
    border-color: var(--blue-400);
}
.summary-label {
    font-size: 0.58rem; color: var(--blue-700);
    text-transform: uppercase; letter-spacing: 0.06em;
    font-weight: 800; margin-bottom: 5px;
    display: flex; align-items: center; justify-content: center; gap: 4px;
}
.summary-label i { color: var(--blue-500); font-size: 0.7rem; }
.summary-value {
    font-family: var(--font-mono);
    font-size: 1rem;
    font-weight: 900;
    color: var(--blue-800);
    letter-spacing: -0.03em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.summary-value.paid { color: var(--success); }
.summary-value.balance { color: var(--danger); }
.summary-value.discount { color: var(--warning); }

/* ============== MODAL ============== */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(8px); z-index: 99999; display: none;
    align-items: center; justify-content: center; padding: 20px;
}
.modal-overlay.active { display: flex; }
.modal-box {
    background: var(--bg-card); border-radius: var(--radius-lg);
    max-width: 520px; width: 100%; padding: 32px;
    box-shadow: var(--shadow-xl);
    animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    text-align: center;
    border-top: 4px solid var(--blue-600);
}
@keyframes modalPop {
    0% { opacity: 0; transform: scale(0.85) translateY(20px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}
.modal-icon {
    width: 72px; height: 72px; border-radius: 50%;
    background: var(--danger-bg); color: var(--danger);
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; margin: 0 auto 18px;
    animation: iconPulse 1.5s infinite;
}
@keyframes iconPulse {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); }
    50% { transform: scale(1.05); box-shadow: 0 0 0 14px rgba(220, 38, 38, 0); }
}
.modal-title { font-size: 1.25rem; font-weight: 800; margin-bottom: 10px; color: var(--blue-900); }
.modal-text { font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 24px; line-height: 1.7; }
.modal-text strong {
    color: var(--blue-700); background: var(--blue-50);
    padding: 4px 12px; border-radius: 6px;
    font-family: var(--font-mono); display: inline-block; margin: 4px 0;
    border: 1px solid var(--blue-200);
}
.modal-warning { color: var(--danger); font-weight: 700; display: block; margin-top: 10px; font-size: 0.8rem; }
.modal-actions { display: flex; gap: 12px; justify-content: center; }
.modal-btn {
    padding: 11px 26px; border-radius: var(--radius-md);
    font-weight: 700; font-size: 0.85rem; border: none; cursor: pointer;
    transition: all 0.25s; display: inline-flex; align-items: center; gap: 8px;
}
.modal-btn.cancel { background: var(--border-color); color: var(--text-primary); }
.modal-btn.cancel:hover { background: var(--border-strong); transform: translateY(-2px); }
.modal-btn.danger { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; }
.modal-btn.danger:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(220, 38, 38, 0.5); }

/* ============== EMPTY STATE ============== */
.empty-state { padding: 40px 20px; text-align: center; color: var(--text-secondary); }
.empty-state i { font-size: 2rem; opacity: 0.35; display: block; margin-bottom: 12px; color: var(--blue-500); }
.empty-state p { font-weight: 600; font-size: 0.82rem; }

/* ============== SECTION HIGHLIGHT (after scroll) ============== */
.section-highlighted {
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.35), 0 10px 30px rgba(37, 99, 235, 0.2) !important;
}

/* ============== RESPONSIVE ============== */
@media (max-width: 1024px) { .summary-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 640px) {
    .summary-grid { grid-template-columns: repeat(2, 1fr); }
    .add-form { flex-direction: column; }
    .add-form .form-group { min-width: 100%; }
}
@media print {
    .btn, .btn-action, .btn-header, .add-form, .modal-overlay, .searchable-dropdown { display: none !important; }
    .card { break-inside: avoid; }
}
</style>
</head>
<body>

<main class="main-content">

    <!-- HIDDEN INPUT: tells JS which section to scroll to after POST -->
    <input type="hidden" id="scrollSection" value="<?= htmlspecialchars($scroll_section) ?>">

    <?php if (!empty($alert_message)): ?>
        <div class="alert <?= $alert_type ?>" id="alertBox">
            <i class="fas fa-<?= $alert_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <div><?= $alert_message ?></div>
        </div>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit"></i> Edit Visit
            </h1>
            <p class="page-subtitle">
                <span class="header-badge solid"><i class="fas fa-hashtag"></i> <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span>
                <span class="header-badge"><i class="fas fa-calendar"></i> <?= !empty($visit['visit_date']) ? date('d M Y, H:i', strtotime($visit['visit_date'])) : 'N/A' ?></span>
                <span class="header-badge"><i class="fas fa-user"></i> <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></span>
                <span class="header-badge"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_visit.php?id=<?= $visit_id ?>&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-eye"></i> View Visit
            </a>
            <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ============================================================
         SECTION 1: VISIT TYPE + DOCTOR + RECEPTIONIST
         ============================================================ -->
    <div class="card" id="section-visit">
        <div class="card-header">
            <div class="title"><i class="fas fa-info-circle"></i> Visit Details & Assignment</div>
            <span class="count">Consultation Services Only</span>
        </div>
        <div class="card-body">
            <form method="POST" id="visitForm">
                <input type="hidden" name="action" value="update_visit">
                <div class="form-grid">

                    <div class="form-group full">
                        <label class="form-label"><i class="fas fa-stethoscope"></i> Visit Type / Consultation Service</label>
                        <div class="searchable" id="serviceSearchable">
                            <div class="searchable-input-wrap">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" class="form-input search-input" id="service_search"
                                       placeholder="Search consultation service..."
                                       autocomplete="off" readonly>
                            </div>
                            <input type="hidden" name="service_id" id="service_id"
                                   value="<?= (int)($visit['service_id'] ?? 0) ?>">
                            <input type="hidden" name="visit_type" id="visit_type_hidden"
                                   value="<?= htmlspecialchars($visit['visit_type'] ?? '') ?>">
                            <div class="searchable-dropdown" id="service_dropdown">
                                <?php foreach ($visit_types as $vt): ?>
                                    <div class="searchable-option"
                                         data-value="<?= (int)$vt['id'] ?>"
                                         data-name="<?= htmlspecialchars($vt['service_name']) ?>"
                                         data-price="<?= (float)$vt['price'] ?>">
                                        <span class="opt-label">
                                            <i class="fas fa-stethoscope" style="color:var(--blue-500);margin-right:6px;"></i>
                                            <?= htmlspecialchars($vt['service_name']) ?>
                                        </span>
                                        <span class="opt-meta"><?= $currency ?> <?= number_format((float)$vt['price'], 0) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <span class="form-help">Services in "Consultation" category only.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-heading"></i> Visit Type Label</label>
                        <input type="text" id="visit_type_label" class="form-input"
                               value="<?= htmlspecialchars($visit['visit_type'] ?? '') ?>"
                               placeholder="Auto-filled from service" readonly
                               style="background:var(--blue-50);color:var(--blue-800);">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-money-bill"></i> Consultation Fee</label>
                        <input type="number" name="consultation_fee" id="consultation_fee"
                               class="form-input" step="0.01" min="0"
                               value="<?= (float)($visit['consultation_fee'] ?? 0) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user-md"></i> Doctor</label>
                        <div class="searchable" id="doctorSearchable">
                            <div class="searchable-input-wrap">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" class="form-input search-input" id="doctor_search"
                                       placeholder="Search doctor..." autocomplete="off" readonly>
                            </div>
                            <input type="hidden" name="doctor_id" id="doctor_id"
                                   value="<?= (int)($visit['doctor_id'] ?? 0) ?>">
                            <div class="searchable-dropdown" id="doctor_dropdown">
                                <?php foreach ($doctors as $d): ?>
                                    <div class="searchable-option"
                                         data-value="<?= (int)$d['id'] ?>"
                                         data-name="<?= htmlspecialchars($d['full_name']) ?>">
                                        <span class="opt-label">
                                            <i class="fas fa-user-md" style="color:var(--blue-500);margin-right:6px;"></i>
                                            <?= htmlspecialchars($d['full_name']) ?>
                                        </span>
                                        <?php if (!empty($d['specialty'])): ?>
                                            <span class="opt-meta"><?= htmlspecialchars($d['specialty']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user-tie"></i> Receptionist</label>
                        <div class="searchable" id="receptionistSearchable">
                            <div class="searchable-input-wrap">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" class="form-input search-input" id="receptionist_search"
                                       placeholder="Search receptionist..." autocomplete="off" readonly>
                            </div>
                            <input type="hidden" name="receptionist_id" id="receptionist_id"
                                   value="<?= (int)($visit['receptionist_id'] ?? 0) ?>">
                            <div class="searchable-dropdown" id="receptionist_dropdown">
                                <?php foreach ($receptionists as $r): ?>
                                    <div class="searchable-option"
                                         data-value="<?= (int)$r['id'] ?>"
                                         data-name="<?= htmlspecialchars($r['full_name']) ?>">
                                        <span class="opt-label">
                                            <i class="fas fa-user-tie" style="color:var(--blue-500);margin-right:6px;"></i>
                                            <?= htmlspecialchars($r['full_name']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-info-circle"></i> Visit Status</label>
                        <select name="status" class="form-select">
                            <?php
                            $statuses = ['pending','assigned','with_doctor','lab_test','lab_completed','prescribed','waiting','completed','cancelled'];
                            foreach ($statuses as $st):
                            ?>
                                <option value="<?= $st ?>" <?= (strtolower($visit['status'] ?? '') === $st) ? 'selected' : '' ?>>
                                    <?= strtoupper(str_replace('_', ' ', $st)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar-check"></i> Follow Up Date</label>
                        <input type="date" name="follow_up_date" class="form-input"
                               value="<?= !empty($visit['follow_up_date']) ? htmlspecialchars($visit['follow_up_date']) : '' ?>">
                    </div>

                    <div class="form-group full">
                        <label class="form-label"><i class="fas fa-thermometer-half"></i> Symptoms</label>
                        <textarea name="symptoms" class="form-textarea" rows="2"><?= htmlspecialchars($visit['symptoms'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group full">
                        <label class="form-label"><i class="fas fa-history"></i> HPI (History of Present Illness)</label>
                        <textarea name="hpi" class="form-textarea" rows="3"><?= htmlspecialchars($visit['hpi'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group full">
                        <label class="form-label"><i class="fas fa-stethoscope"></i> Physical Examination</label>
                        <textarea name="physical_exam" class="form-textarea" rows="3"><?= htmlspecialchars($visit['physical_exam'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group full">
                        <label class="form-label"><i class="fas fa-sticky-note"></i> Notes</label>
                        <textarea name="notes" class="form-textarea" rows="2"><?= htmlspecialchars($visit['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:18px;justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Visit Details
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================
         SECTION 2: DIAGNOSIS
         ============================================================ -->
    <div class="card" id="section-diagnosis">
        <div class="card-header">
            <div class="title"><i class="fas fa-diagnoses"></i> Diagnosis</div>
            <span class="count">Manual or dropdown</span>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_diagnosis">
                <div class="form-grid">

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-list"></i> Select from Diseases</label>
                        <div class="searchable" id="diseaseSearchable">
                            <div class="searchable-input-wrap">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" class="form-input search-input" id="disease_search"
                                       placeholder="Search disease..." autocomplete="off" readonly>
                            </div>
                            <input type="hidden" name="disease_id" id="disease_id"
                                   value="<?= (int)($visit['disease_id'] ?? 0) ?>">
                            <div class="searchable-dropdown" id="disease_dropdown">
                                <?php foreach ($diseases_list as $dis): ?>
                                    <div class="searchable-option"
                                         data-value="<?= (int)$dis['id'] ?>"
                                         data-name="<?= htmlspecialchars($dis['disease_name']) ?>"
                                         data-code="<?= htmlspecialchars($dis['disease_code'] ?? '') ?>"
                                         data-treatment="<?= htmlspecialchars($dis['treatment'] ?? '') ?>">
                                        <span class="opt-label">
                                            <i class="fas fa-virus" style="color:var(--blue-500);margin-right:6px;"></i>
                                            <?= htmlspecialchars($dis['disease_name']) ?>
                                        </span>
                                        <?php if (!empty($dis['disease_code'])): ?>
                                            <span class="opt-meta"><?= htmlspecialchars($dis['disease_code']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-hashtag"></i> Disease Code</label>
                        <input type="text" name="disease_code" id="disease_code" class="form-input"
                               value="<?= htmlspecialchars($visit['disease_code'] ?? '') ?>" placeholder="Auto-filled">
                    </div>

                    <div class="form-group full">
                        <label class="form-label"><i class="fas fa-notes-medical"></i> Diagnosis Text</label>
                        <textarea name="diagnosis" id="diagnosis" class="form-textarea" rows="3"
                                  placeholder="Andika diagnosis hapa..."><?= htmlspecialchars($visit['diagnosis'] ?? '') ?></textarea>
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:14px;justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Diagnosis
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================
         SECTION 3: LAB TESTS
         ============================================================ -->
    <div class="card" id="section-lab">
        <div class="card-header">
            <div class="title"><i class="fas fa-flask"></i> Lab Tests</div>
            <span class="count"><?= count($lab_tests) ?> test<?= count($lab_tests) !== 1 ? 's' : '' ?></span>
        </div>

        <form method="POST" class="add-form" id="labForm">
            <input type="hidden" name="action" value="add_lab_test">
            <div class="form-group" style="flex:2;">
                <label class="form-label"><i class="fas fa-vial"></i> Add Lab Test</label>
                <div class="searchable" id="labSearchable">
                    <div class="searchable-input-wrap">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="form-input search-input" id="lab_search"
                               placeholder="Search lab test..." autocomplete="off" readonly>
                    </div>
                    <input type="hidden" name="test_id" id="lab_test_id" required>
                    <div class="searchable-dropdown" id="lab_dropdown">
                        <?php foreach ($lab_test_catalog as $lt): ?>
                            <div class="searchable-option"
                                 data-value="<?= (int)$lt['id'] ?>"
                                 data-name="<?= htmlspecialchars($lt['test_name']) ?>">
                                <span class="opt-label">
                                    <i class="fas fa-flask" style="color:var(--blue-500);margin-right:6px;"></i>
                                    <?= htmlspecialchars($lt['test_name']) ?>
                                    <?= !empty($lt['test_code']) ? ' (' . htmlspecialchars($lt['test_code']) . ')' : '' ?>
                                </span>
                                <span class="opt-meta"><?= $currency ?> <?= number_format((float)$lt['price'], 0) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Test
            </button>
        </form>

        <?php if (!empty($lab_tests)): ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Test Name</th>
                        <th>Results</th>
                        <th style="text-align:center;">Status</th>
                        <th style="text-align:right;">Price</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($lab_tests as $lt):
                        $st = strtolower($lt['status'] ?? 'pending');
                        $st_class = 'pending'; $st_icon = 'fa-clock';
                        if ($st === 'completed') { $st_class = 'completed'; $st_icon = 'fa-check-circle'; }
                        elseif ($st === 'in_progress') { $st_class = 'in_progress'; $st_icon = 'fa-spinner'; }
                        elseif ($st === 'cancelled') { $st_class = 'cancelled'; $st_icon = 'fa-times-circle'; }
                    ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <div class="item-name"><?= htmlspecialchars($lt['test_name'] ?? 'N/A') ?></div>
                            </td>
                            <td>
                                <div class="item-sub" style="max-width:300px;">
                                    <?= !empty($lt['results']) ? htmlspecialchars(substr($lt['results'], 0, 100)) . (strlen($lt['results']) > 100 ? '...' : '') : '<em style="color:var(--text-muted);">No results yet</em>' ?>
                                </div>
                            </td>
                            <td class="status-cell">
                                <span class="status-badge <?= $st_class ?>">
                                    <i class="fas <?= $st_icon ?>"></i> <?= strtoupper($st) ?>
                                </span>
                            </td>
                            <td class="money-cell"><?= $currency ?> <?= number_format((float)($lt['test_price'] ?? 0), 0) ?></td>
                            <td class="actions-cell">
                                <button type="button" class="btn-action delete"
                                        onclick="confirmDelete('delete_lab_test', {lab_test_id: <?= (int)$lt['id'] ?>}, 'lab test', '<?= htmlspecialchars(addslashes($lt['test_name'])) ?>', <?= (float)$lt['test_price'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-flask"></i>
                <p>No lab tests yet for this visit.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================================
         SECTION 4: MEDICATIONS
         ============================================================ -->
    <div class="card" id="section-medication">
        <div class="card-header">
            <div class="title"><i class="fas fa-pills"></i> Medications</div>
            <span class="count"><?= count($prescription_items) ?> medication<?= count($prescription_items) !== 1 ? 's' : '' ?></span>
        </div>

        <form method="POST" class="add-form" id="medForm">
            <input type="hidden" name="action" value="add_medication">
            <div class="form-group" style="flex:2; min-width:220px;">
                <label class="form-label"><i class="fas fa-prescription-bottle-medical"></i> Medication</label>
                <div class="searchable" id="medSearchable">
                    <div class="searchable-input-wrap">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="form-input search-input" id="med_search"
                               placeholder="Search medication..." autocomplete="off" readonly>
                    </div>
                    <input type="hidden" name="inventory_id" id="med_inventory_id" required>
                    <div class="searchable-dropdown" id="med_dropdown">
                        <?php foreach ($medications_inventory as $mi): ?>
                            <div class="searchable-option"
                                 data-value="<?= (int)$mi['id'] ?>"
                                 data-name="<?= htmlspecialchars($mi['medication_name']) ?>">
                                <span class="opt-label">
                                    <i class="fas fa-pills" style="color:var(--blue-500);margin-right:6px;"></i>
                                    <?= htmlspecialchars($mi['medication_name']) ?>
                                </span>
                                <span class="opt-meta">
                                    Stock: <?= (int)$mi['quantity'] ?> <?= htmlspecialchars($mi['unit'] ?? '') ?>
                                    • <?= $currency ?> <?= number_format((float)$mi['selling_price'], 0) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="form-group" style="max-width:110px;">
                <label class="form-label"><i class="fas fa-sort-numeric-up"></i> Quantity</label>
                <input type="number" name="quantity" class="form-input" min="1" value="1" required>
            </div>
            <div class="form-group" style="max-width:150px;">
                <label class="form-label"><i class="fas fa-prescription"></i> Dosage</label>
                <input type="text" name="dosage" class="form-input" placeholder="e.g. 500mg">
            </div>
            <div class="form-group" style="max-width:150px;">
                <label class="form-label"><i class="fas fa-redo"></i> Frequency</label>
                <input type="text" name="frequency" class="form-input" placeholder="e.g. TID">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Medication
            </button>
        </form>

        <?php if (!empty($prescription_items)): ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Medication</th>
                        <th style="text-align:center;">Qty</th>
                        <th>Dosage</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:center;">Status</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($prescription_items as $pi):
                        $st = strtolower($pi['status'] ?? 'pending');
                        $st_class = 'pending'; $st_icon = 'fa-clock';
                        if ($st === 'dispensed') { $st_class = 'dispensed'; $st_icon = 'fa-check-circle'; }
                        elseif ($st === 'confirmed') { $st_class = 'confirmed'; $st_icon = 'fa-check'; }
                        elseif ($st === 'cancelled') { $st_class = 'cancelled'; $st_icon = 'fa-times-circle'; }
                    ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <div class="item-name"><?= htmlspecialchars($pi['medication_name'] ?? 'N/A') ?></div>
                                <div class="item-sub">
                                    Rx: <?= htmlspecialchars($pi['prescription_number'] ?? 'N/A') ?>
                                    <?php if (isset($pi['current_stock'])): ?>
                                        • Stock: <?= (int)$pi['current_stock'] ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="qty-cell"><?= (int)$pi['quantity'] ?></td>
                            <td>
                                <div class="item-sub">
                                    <?= htmlspecialchars($pi['dosage'] ?? '') ?>
                                    <?= !empty($pi['frequency']) ? ' • ' . htmlspecialchars($pi['frequency']) : '' ?>
                                    <?= !empty($pi['duration']) ? ' • ' . htmlspecialchars($pi['duration']) : '' ?>
                                </div>
                            </td>
                            <td class="money-cell"><?= $currency ?> <?= number_format((float)($pi['unit_price'] ?? 0), 0) ?></td>
                            <td class="money-cell total"><?= $currency ?> <?= number_format((float)($pi['total_price'] ?? 0), 0) ?></td>
                            <td class="status-cell">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="update_medication_status">
                                    <input type="hidden" name="prescription_item_id" value="<?= (int)$pi['id'] ?>">
                                    <select name="new_status" class="form-select" style="padding:5px 8px;font-size:0.65rem;width:auto;"
                                            onchange="this.form.submit()">
                                        <option value="pending" <?= $st === 'pending' ? 'selected' : '' ?>>PENDING</option>
                                        <option value="confirmed" <?= $st === 'confirmed' ? 'selected' : '' ?>>CONFIRMED</option>
                                        <option value="dispensed" <?= $st === 'dispensed' ? 'selected' : '' ?>>DISPENSED</option>
                                        <option value="cancelled" <?= $st === 'cancelled' ? 'selected' : '' ?>>CANCELLED</option>
                                    </select>
                                </form>
                            </td>
                            <td class="actions-cell">
                                <button type="button" class="btn-action delete"
                                        onclick="confirmDelete('delete_medication', {prescription_item_id: <?= (int)$pi['id'] ?>}, 'medication (<?= $st ?>)', '<?= htmlspecialchars(addslashes($pi['medication_name'])) ?> x<?= (int)$pi['quantity'] ?>', <?= (float)$pi['total_price'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-pills"></i>
                <p>No medications prescribed for this visit yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================================
         SECTION 5: PROCEDURES
         ============================================================ -->
    <div class="card" id="section-procedure">
        <div class="card-header">
            <div class="title"><i class="fas fa-procedures"></i> Procedures</div>
            <span class="count"><?= count($procedures) ?> procedure<?= count($procedures) !== 1 ? 's' : '' ?></span>
        </div>

        <form method="POST" class="add-form" id="procForm">
            <input type="hidden" name="action" value="add_procedure">
            <div class="form-group" style="flex:2;">
                <label class="form-label"><i class="fas fa-syringe"></i> Add Procedure</label>
                <div class="searchable" id="procSearchable">
                    <div class="searchable-input-wrap">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="form-input search-input" id="proc_search"
                               placeholder="Search procedure..." autocomplete="off" readonly>
                    </div>
                    <input type="hidden" name="procedure_id" id="proc_id" required>
                    <div class="searchable-dropdown" id="proc_dropdown">
                        <?php foreach ($procedures_catalog as $pc): ?>
                            <div class="searchable-option"
                                 data-value="<?= (int)$pc['id'] ?>"
                                 data-name="<?= htmlspecialchars($pc['procedure_name']) ?>">
                                <span class="opt-label">
                                    <i class="fas fa-procedures" style="color:var(--blue-500);margin-right:6px;"></i>
                                    <?= htmlspecialchars($pc['procedure_name']) ?>
                                </span>
                                <span class="opt-meta"><?= $currency ?> <?= number_format((float)$pc['price'], 0) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Procedure
            </button>
        </form>

        <?php if (!empty($procedures)): ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Procedure</th>
                        <th>Category</th>
                        <th style="text-align:center;">Status</th>
                        <th style="text-align:right;">Price</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($procedures as $p):
                        $st = strtolower($p['status'] ?? 'pending');
                        $st_class = 'pending'; $st_icon = 'fa-clock';
                        if ($st === 'completed') { $st_class = 'completed'; $st_icon = 'fa-check-circle'; }
                        elseif ($st === 'in_progress') { $st_class = 'in_progress'; $st_icon = 'fa-spinner'; }
                        elseif ($st === 'cancelled') { $st_class = 'cancelled'; $st_icon = 'fa-times-circle'; }
                    ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td class="item-name"><?= htmlspecialchars($p['procedure_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($p['procedure_category'] ?? $p['category'] ?? '—') ?></td>
                            <td class="status-cell">
                                <span class="status-badge <?= $st_class ?>">
                                    <i class="fas <?= $st_icon ?>"></i> <?= strtoupper($st) ?>
                                </span>
                            </td>
                            <td class="money-cell"><?= $currency ?> <?= number_format((float)($p['procedure_price'] ?? 0), 0) ?></td>
                            <td class="actions-cell">
                                <button type="button" class="btn-action delete"
                                        onclick="confirmDelete('delete_procedure', {procedure_record_id: <?= (int)$p['id'] ?>}, 'procedure', '<?= htmlspecialchars(addslashes($p['procedure_name'])) ?>', <?= (float)$p['procedure_price'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-procedures"></i>
                <p>No procedures for this visit yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================================
         SECTION 6: EQUIPMENT
         ============================================================ -->
    <div class="card" id="section-equipment">
        <div class="card-header">
            <div class="title"><i class="fas fa-toolbox"></i> Equipment / Materials</div>
            <span class="count"><?= count($equipment_items) ?> item<?= count($equipment_items) !== 1 ? 's' : '' ?></span>
        </div>

        <form method="POST" class="add-form" id="eqpForm">
            <input type="hidden" name="action" value="add_equipment">
            <div class="form-group" style="flex:2;">
                <label class="form-label"><i class="fas fa-tools"></i> Add Equipment</label>
                <div class="searchable" id="eqpSearchable">
                    <div class="searchable-input-wrap">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="form-input search-input" id="eqp_search"
                               placeholder="Search equipment..." autocomplete="off" readonly>
                    </div>
                    <input type="hidden" name="equipment_id" id="eqp_id" required>
                    <div class="searchable-dropdown" id="eqp_dropdown">
                        <?php foreach ($equipment_catalog as $eq): ?>
                            <div class="searchable-option"
                                 data-value="<?= (int)$eq['id'] ?>"
                                 data-name="<?= htmlspecialchars($eq['equipment_name']) ?>">
                                <span class="opt-label">
                                    <i class="fas fa-tools" style="color:var(--blue-500);margin-right:6px;"></i>
                                    <?= htmlspecialchars($eq['equipment_name']) ?>
                                </span>
                                <span class="opt-meta">
                                    Stock: <?= (int)$eq['quantity'] ?>
                                    • <?= $currency ?> <?= number_format((float)$eq['selling_price'], 0) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="form-group" style="max-width:120px;">
                <label class="form-label"><i class="fas fa-sort-numeric-up"></i> Quantity</label>
                <input type="number" name="equipment_qty" class="form-input" min="1" value="1" required>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Equipment
            </button>
        </form>

        <?php if (!empty($equipment_items)): ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Equipment</th>
                        <th>Description</th>
                        <th style="text-align:center;">Qty</th>
                        <th style="text-align:right;">Unit</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($equipment_items as $eq): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td class="item-name"><?= htmlspecialchars($eq['item_name'] ?? 'N/A') ?></td>
                            <td>
                                <div class="item-sub"><?= htmlspecialchars($eq['description'] ?? '—') ?></div>
                                <?php if (!empty($eq['bill_number'])): ?>
                                    <span class="item-code"><?= htmlspecialchars($eq['bill_number']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="qty-cell"><?= (int)($eq['quantity'] ?? 1) ?></td>
                            <td class="money-cell"><?= $currency ?> <?= number_format((float)($eq['unit_price'] ?? 0), 0) ?></td>
                            <td class="money-cell total"><?= $currency ?> <?= number_format((float)($eq['total_price'] ?? 0), 0) ?></td>
                            <td class="actions-cell">
                                <button type="button" class="btn-action delete"
                                        onclick="confirmDelete('delete_equipment', {bill_item_id: <?= (int)$eq['id'] ?>}, 'equipment', '<?= htmlspecialchars(addslashes($eq['item_name'])) ?>', <?= (float)$eq['total_price'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-toolbox"></i>
                <p>No equipment recorded for this visit yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================================
         SECTION 7: BILL SUMMARY
         ============================================================ -->
    <?php if ($primary_bill_id): ?>
    <div class="card" id="section-bill">
        <div class="card-header">
            <div class="title"><i class="fas fa-file-invoice-dollar"></i> Bill Summary</div>
            <span class="count">Bill #<?= (int)$primary_bill_id ?></span>
        </div>
        <div class="summary-grid">
            <div class="summary-box">
                <div class="summary-label"><i class="fas fa-file-invoice"></i> Total Billed</div>
                <div class="summary-value"><?= $currency ?> <?= number_format($total_billed, 0) ?></div>
            </div>
            <div class="summary-box">
                <div class="summary-label"><i class="fas fa-check-circle"></i> Total Paid</div>
                <div class="summary-value paid"><?= $currency ?> <?= number_format($total_paid, 0) ?></div>
            </div>
            <div class="summary-box">
                <div class="summary-label"><i class="fas fa-percent"></i> Pharm Disc</div>
                <div class="summary-value discount"><?= $currency ?> <?= number_format($pharm_disc_total, 0) ?></div>
            </div>
            <div class="summary-box">
                <div class="summary-label"><i class="fas fa-percent"></i> Cashier Disc</div>
                <div class="summary-value discount"><?= $currency ?> <?= number_format($cash_disc_total, 0) ?></div>
            </div>
            <div class="summary-box">
                <div class="summary-label"><i class="fas fa-star"></i> Premium</div>
                <div class="summary-value discount"><?= $currency ?> <?= number_format($total_premium, 0) ?></div>
            </div>
            <div class="summary-box">
                <div class="summary-label"><i class="fas fa-exclamation-circle"></i> Balance</div>
                <div class="summary-value balance"><?= $currency ?> <?= number_format($total_balance, 0) ?></div>
            </div>
        </div>

        <?php $primary_bill = null; foreach ($bills as $b) { if ((int)$b['id'] === $primary_bill_id) { $primary_bill = $b; break; } } ?>
        <?php if ($primary_bill): ?>
        <form method="POST" class="card-body" style="border-top:2px solid var(--blue-200);">
            <input type="hidden" name="action" value="update_bill_totals">
            <input type="hidden" name="bill_id" value="<?= (int)$primary_bill_id ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-percent"></i> Pharmacy Discount</label>
                    <input type="number" name="pharmacy_discount" class="form-input" step="0.01" min="0"
                           value="<?= (float)($primary_bill['pharmacy_discount'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-percent"></i> Cashier Discount</label>
                    <input type="number" name="cashier_discount" class="form-input" step="0.01" min="0"
                           value="<?= (float)($primary_bill['cashier_discount'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-star"></i> Pharmacy Premium</label>
                    <input type="number" name="pharmacy_premium" class="form-input" step="0.01" min="0"
                           value="<?= (float)($primary_bill['pharmacy_premium'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-star"></i> Cashier Premium</label>
                    <input type="number" name="cashier_premium" class="form-input" step="0.01" min="0"
                           value="<?= (float)($primary_bill['cashier_premium'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-sticky-note"></i> Pharmacy Premium Note</label>
                    <input type="text" name="pharmacy_premium_note" class="form-input"
                           value="<?= htmlspecialchars($primary_bill['pharmacy_premium_note'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-sticky-note"></i> Cashier Premium Note</label>
                    <input type="text" name="cashier_premium_note" class="form-input"
                           value="<?= htmlspecialchars($primary_bill['cashier_premium_note'] ?? '') ?>">
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:14px;justify-content:flex-end;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Bill Totals
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</main>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <h3 class="modal-title" id="deleteModalTitle">Delete Record?</h3>
        <p class="modal-text" id="deleteModalText">
            Are you sure you want to delete<br>
            <strong id="deleteRecordNumber">#</strong><br>
            <span id="deleteItemAmount" style="font-size:0.8rem;color:var(--danger);font-weight:700;"></span>
            <span class="modal-warning">
                <i class="fas fa-exclamation-circle"></i> Bill totals and payments will be recalculated!
            </span>
        </p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" id="deleteAction" value="">
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="modal-btn danger">
                    <i class="fas fa-trash"></i> Yes, Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
/* ================================================================
   AUTO-SCROLL TO SECTION AFTER FORM SUBMIT
   Prevents page jump to top after add/delete operations
   ================================================================ */
(function() {
    var section = document.getElementById('scrollSection');
    if (section && section.value) {
        var sectionId = 'section-' + section.value;
        var target    = document.getElementById(sectionId);

        if (target) {
            // Wait for layout to settle, then smooth scroll
            setTimeout(function() {
                var yOffset = target.getBoundingClientRect().top + window.pageYOffset - 20;
                window.scrollTo({ top: yOffset, behavior: 'smooth' });

                // Highlight section briefly so user sees confirmation
                target.classList.add('section-highlighted');
                setTimeout(function() {
                    target.classList.remove('section-highlighted');
                }, 1800);
            }, 120);

            // Clean URL so refresh doesn't re-scroll
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, document.title,
                    window.location.pathname + window.location.search);
            }
        }
    }

    /* Auto-dismiss alert when user scrolls */
    var alertBox = document.getElementById('alertBox');
    if (alertBox) {
        var dismissTimer = setTimeout(function() {
            alertBox.style.transition = 'all 0.5s ease';
            alertBox.style.opacity = '0';
            setTimeout(function() { if (alertBox.parentNode) alertBox.remove(); }, 500);
        }, 8000);

        window.addEventListener('scroll', function() {
            if (alertBox && alertBox.parentNode) {
                clearTimeout(dismissTimer);
                alertBox.style.transition = 'all 0.4s ease';
                alertBox.style.opacity = '0';
                alertBox.style.transform = 'translateY(-10px)';
                setTimeout(function() { if (alertBox.parentNode) alertBox.remove(); }, 400);
            }
        }, { once: true, passive: true });
    }
})();

/* ================================================================
   SEARCHABLE DROPDOWN FACTORY
   ================================================================ */
function initSearchable(config) {
    var wrap       = document.getElementById(config.wrapId);
    if (!wrap) return null;

    var input       = document.getElementById(config.inputId);
    var dropdown    = document.getElementById(config.dropdownId);
    var hiddenInput = document.getElementById(config.hiddenId);
    var allOptions  = Array.from(dropdown.querySelectorAll('.searchable-option'));

    var selectedLabel = '';

    // Set initial display text
    if (hiddenInput && hiddenInput.value) {
        var matched = allOptions.find(function(opt) {
            return opt.getAttribute('data-value') === hiddenInput.value;
        });
        if (matched) {
            selectedLabel = matched.getAttribute('data-name') || matched.textContent.trim();
            input.value = selectedLabel;
            matched.classList.add('selected');
        }
    }

    // Filter function (live)
    function filterOptions(query) {
        var q = (query || '').toLowerCase().trim();
        var visibleCount = 0;

        allOptions.forEach(function(opt) {
            var label = (opt.getAttribute('data-name') || opt.textContent).toLowerCase();
            if (q === '' || label.indexOf(q) !== -1) {
                opt.style.display = 'flex';
                visibleCount++;
            } else {
                opt.style.display = 'none';
            }
        });

        // "No result" message
        var noResult = dropdown.querySelector('.searchable-option.no-result');
        if (visibleCount === 0) {
            if (!noResult) {
                noResult = document.createElement('div');
                noResult.className = 'searchable-option no-result';
                noResult.textContent = 'No matching result';
                dropdown.appendChild(noResult);
            } else {
                noResult.style.display = 'flex';
            }
        } else if (noResult) {
            noResult.remove();
        }
    }

    function openDropdown() {
        // Close others
        document.querySelectorAll('.searchable-dropdown.open').forEach(function(d) {
            if (d !== dropdown) d.classList.remove('open');
        });
        dropdown.classList.add('open');

        input.value = '';
        filterOptions('');
        input.focus();
    }

    function closeDropdown() {
        dropdown.classList.remove('open');
        if (hiddenInput.value && selectedLabel) {
            input.value = selectedLabel;
        } else {
            input.value = '';
        }
    }

    input.addEventListener('click', function(e) {
        e.stopPropagation();
        if (dropdown.classList.contains('open')) {
            closeDropdown();
        } else {
            openDropdown();
        }
    });

    input.addEventListener('focus', function() {
        if (!dropdown.classList.contains('open')) {
            openDropdown();
        }
    });

    input.addEventListener('input', function() {
        filterOptions(this.value);
    });

    input.addEventListener('keydown', function(e) {
        var visible = allOptions.filter(function(o) { return o.style.display !== 'none'; });
        var currentIdx = visible.findIndex(function(o) { return o.classList.contains('active'); });

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (currentIdx < visible.length - 1) {
                if (currentIdx >= 0) visible[currentIdx].classList.remove('active');
                visible[currentIdx + 1].classList.add('active');
                visible[currentIdx + 1].scrollIntoView({ block: 'nearest' });
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (currentIdx > 0) {
                if (currentIdx >= 0) visible[currentIdx].classList.remove('active');
                visible[currentIdx - 1].classList.add('active');
                visible[currentIdx - 1].scrollIntoView({ block: 'nearest' });
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (currentIdx >= 0 && visible[currentIdx]) {
                visible[currentIdx].click();
            }
        } else if (e.key === 'Escape') {
            closeDropdown();
        }
    });

    allOptions.forEach(function(opt) {
        opt.addEventListener('click', function(e) {
            e.stopPropagation();

            allOptions.forEach(function(o) { o.classList.remove('selected'); });
            opt.classList.add('selected');

            var value = opt.getAttribute('data-value');
            var name  = opt.getAttribute('data-name') || opt.textContent.trim();

            hiddenInput.value = value;
            selectedLabel = name;
            input.value = name;

            closeDropdown();

            if (typeof config.onSelect === 'function') {
                config.onSelect(value, opt, name);
            }
        });
    });

    document.addEventListener('click', function(e) {
        if (!wrap.contains(e.target)) {
            closeDropdown();
        }
    });

    return {
        open: openDropdown,
        close: closeDropdown,
        getValue: function() { return hiddenInput.value; }
    };
}

/* ================================================================
   INITIALIZE ALL SEARCHABLE DROPDOWNS
   ================================================================ */
document.addEventListener('DOMContentLoaded', function() {

    // SERVICE (auto-fills consultation fee + visit type label)
    initSearchable({
        wrapId: 'serviceSearchable',
        inputId: 'service_search',
        dropdownId: 'service_dropdown',
        hiddenId: 'service_id',
        onSelect: function(value, opt, name) {
            var price = opt.getAttribute('data-price') || '0';
            document.getElementById('consultation_fee').value = price;
            document.getElementById('visit_type_label').value = name;
            document.getElementById('visit_type_hidden').value = name;
        }
    });

    // DOCTOR
    initSearchable({
        wrapId: 'doctorSearchable',
        inputId: 'doctor_search',
        dropdownId: 'doctor_dropdown',
        hiddenId: 'doctor_id'
    });

    // RECEPTIONIST
    initSearchable({
        wrapId: 'receptionistSearchable',
        inputId: 'receptionist_search',
        dropdownId: 'receptionist_dropdown',
        hiddenId: 'receptionist_id'
    });

    // DISEASE (auto-fills diagnosis + code)
    initSearchable({
        wrapId: 'diseaseSearchable',
        inputId: 'disease_search',
        dropdownId: 'disease_dropdown',
        hiddenId: 'disease_id',
        onSelect: function(value, opt, name) {
            var code = opt.getAttribute('data-code') || '';
            document.getElementById('diagnosis').value = name;
            if (code) document.getElementById('disease_code').value = code;
        }
    });

    // LAB TEST
    initSearchable({
        wrapId: 'labSearchable',
        inputId: 'lab_search',
        dropdownId: 'lab_dropdown',
        hiddenId: 'lab_test_id'
    });

    // MEDICATION
    initSearchable({
        wrapId: 'medSearchable',
        inputId: 'med_search',
        dropdownId: 'med_dropdown',
        hiddenId: 'med_inventory_id'
    });

    // PROCEDURE
    initSearchable({
        wrapId: 'procSearchable',
        inputId: 'proc_search',
        dropdownId: 'proc_dropdown',
        hiddenId: 'proc_id'
    });

    // EQUIPMENT
    initSearchable({
        wrapId: 'eqpSearchable',
        inputId: 'eqp_search',
        dropdownId: 'eqp_dropdown',
        hiddenId: 'eqp_id'
    });
});

/* ================================================================
   DELETE MODAL
   ================================================================ */
function confirmDelete(action, payload, typeLabel, itemLabel, itemAmount) {
    var CURRENCY = '<?= addslashes($currency) ?>';
    var title = 'Delete ' + typeLabel + '?';
    var amountText = (itemAmount !== undefined && itemAmount > 0)
        ? 'Amount: ' + CURRENCY + ' ' + Math.round(itemAmount).toLocaleString()
        : '';

    document.getElementById('deleteModalTitle').textContent = title;
    document.getElementById('deleteRecordNumber').textContent = itemLabel;
    document.getElementById('deleteItemAmount').textContent = amountText;
    document.getElementById('deleteAction').value = action;

    var form = document.getElementById('deleteForm');
    form.querySelectorAll('input[data-dynamic="1"]').forEach(function(el) { el.remove(); });

    for (var k in payload) {
        if (!payload.hasOwnProperty(k)) continue;
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = k;
        inp.value = payload[k];
        inp.setAttribute('data-dynamic', '1');
        form.appendChild(inp);
    }

    document.getElementById('deleteModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDeleteModal();
});

console.log('%c✏️ Edit Visit — V3 with SCROLL FIX', 'font-size:16px; font-weight:bold; color:#1D4ED8;');
console.log('%c✅ No more jump-to-top after add/delete', 'font-size:12px; color:#1D4ED8; font-weight:bold;');
console.log('%c✅ Live search on all dropdowns', 'font-size:12px; color:#1D4ED8; font-weight:bold;');
</script>

</body>
</html>