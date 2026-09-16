<?php
// ================================================================
// FILE: frontend/pages/admin/delete_lab_test.php
// DELETE LAB TEST - FIXED
// ✅ Processor file - No UI (redirects after action)
// ✅ Safe deletion with transaction
// ✅ Recalculates bill totals + updates visit status
// ✅ Logs activity for audit trail
// BRAICK DISPENSARY
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
// ROLE CHECK - ONLY ADMIN
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// GET PARAMETERS
// ================================================================
$test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';

if ($test_id <= 0) {
    header('Location: lab_tests.php?branch=' . urlencode($branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    header('Location: lab_tests.php?branch=' . urlencode($branch_id) . '&error=database_error');
    exit;
}

// ================================================================
// FETCH TEST DATA
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT lt.*, 
               v.id as visit_id, 
               v.visit_number,
               v.status as visit_status,
               p.full_name as patient_name,
               p.patient_id as patient_code
        FROM lab_tests lt
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN patients p ON v.patient_id = p.id
        WHERE lt.id = ?
    ");
    $stmt->execute([$test_id]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        header('Location: lab_tests.php?branch=' . urlencode($branch_id) . '&error=notfound');
        exit;
    }

    $test_name = $test['test_name'] ?? 'N/A';
    $patient_name = $test['patient_name'] ?? 'Unknown Patient';
    $visit_id = $test['visit_id'] ?? null;
    $visit_number = $test['visit_number'] ?? '';
    $test_branch_id = $test['branch_id'] ?? $user_branch_id;

    // ================================================================
    // SAFETY CHECK: Cannot delete if bill is PAID
    // ================================================================
    $stmt = $db->prepare("
        SELECT b.status, b.id as bill_id
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE bi.reference_id = ? 
          AND bi.reference_type = 'lab_test'
          AND bi.status != 'cancelled'
        LIMIT 1
    ");
    $stmt->execute([$test_id]);
    $bill_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($bill_info && $bill_info['status'] === 'paid') {
        header('Location: lab_tests.php?branch=' . urlencode($branch_id) . '&error=cannot_delete_paid');
        exit;
    }

    // ================================================================
    // START TRANSACTION
    // ================================================================
    $db->beginTransaction();

    // 1. Delete bill items linked to this lab test
    $stmt = $db->prepare("
        DELETE FROM bill_items 
        WHERE reference_id = ? AND reference_type = 'lab_test'
    ");
    $stmt->execute([$test_id]);

    // 2. Delete lab test equipment links
    $stmt = $db->prepare("
        DELETE FROM lab_test_equipment 
        WHERE lab_test_id = ?
    ");
    $stmt->execute([$test_id]);

    // 3. Delete the lab test itself
    $stmt = $db->prepare("
        DELETE FROM lab_tests 
        WHERE id = ?
    ");
    $stmt->execute([$test_id]);

    // ================================================================
    // 4. UPDATE VISIT STATUS if this was the only test
    // ================================================================
    if ($visit_id) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM lab_tests 
            WHERE visit_id = ?
        ");
        $stmt->execute([$visit_id]);
        $remaining_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

        if ($remaining_tests == 0 && $test['visit_status'] === 'lab_test') {
            // No more lab tests, update visit back to 'assigned'
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'assigned', 
                    updated_at = NOW() 
                WHERE id = ? AND status = 'lab_test'
            ");
            $stmt->execute([$visit_id]);
        }
    }

    // ================================================================
    // 5. RECALCULATE BILL TOTALS
    // ================================================================
    if ($visit_id) {
        $stmt = $db->prepare("SELECT id FROM bills WHERE visit_id = ?");
        $stmt->execute([$visit_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($bill) {
            // Recalculate subtotal
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(total_price), 0) as total 
                FROM bill_items 
                WHERE bill_id = ? AND status != 'cancelled'
            ");
            $stmt->execute([$bill['id']]);
            $subtotal = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

            // Get discount
            $stmt = $db->prepare("SELECT COALESCE(total_discount, 0) as total_discount FROM bills WHERE id = ?");
            $stmt->execute([$bill['id']]);
            $discount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_discount'] ?? 0);

            $total_amount = max(0, $subtotal - $discount);

            // Get paid amount
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as payment_total FROM payments WHERE bill_id = ?");
            $stmt->execute([$bill['id']]);
            $paid_amount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['payment_total'] ?? 0);

            $balance = $total_amount - $paid_amount;

            // Determine bill status
            if ($total_amount == 0) {
                $bill_status = 'cancelled';
            } elseif ($balance <= 0 && $total_amount > 0) {
                $bill_status = 'paid';
            } elseif ($paid_amount > 0 && $balance > 0) {
                $bill_status = 'partial';
            } else {
                $bill_status = 'pending';
            }

            $stmt = $db->prepare("
                UPDATE bills 
                SET subtotal = ?, 
                    total_amount = ?, 
                    paid_amount = ?, 
                    balance = ?, 
                    status = ?, 
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$subtotal, $total_amount, $paid_amount, $balance, $bill_status, $bill['id']]);
        }
    }

    // ================================================================
    // 6. LOG ACTIVITY
    // ================================================================
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_logs 
            (user_id, branch_id, patient_id, action, details, created_at)
            VALUES (?, ?, ?, 'lab_test_deleted', ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $test_branch_id,
            $test['patient_id'] ?? null,
            "Lab test #{$test_id} '{$test_name}' for patient '{$patient_name}' (Visit: {$visit_number}) deleted by {$user_full_name}"
        ]);
    } catch (Exception $e) {
        // Ignore logging errors
        error_log("Activity log error: " . $e->getMessage());
    }

    // ================================================================
    // COMMIT
    // ================================================================
    $db->commit();

    // Redirect with success
    header('Location: lab_tests.php?branch=' . urlencode($branch_id) . '&error=delete_success');
    exit;

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Delete lab test error: " . $e->getMessage());
    header('Location: lab_tests.php?branch=' . urlencode($branch_id) . '&error=database_error');
    exit;
}
?>