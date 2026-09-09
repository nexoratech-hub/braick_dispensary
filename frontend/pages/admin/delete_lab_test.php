<?php
// ================================================================
// FILE: frontend/pages/admin/delete_lab_test.php
// DELETE LAB TEST - FIXED
// BRAICK DISPENSARY
// ================================================================

// Start session
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

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    header('Location: lab_tests.php?branch=' . urlencode($branch_id) . '&error=database_error');
    exit;
}

// ================================================================
// CHECK IF TEST EXISTS AND GET ITS DATA
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT lt.*, v.id as visit_id, v.visit_number
        FROM lab_tests lt
        LEFT JOIN visits v ON lt.visit_id = v.id
        WHERE lt.id = ?
    ");
    $stmt->execute([$test_id]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        header('Location: lab_tests.php?branch=' . urlencode($branch_id) . '&error=notfound');
        exit;
    }

    // ================================================================
    // CHECK IF TEST CAN BE DELETED
    // - Completed tests CAN be deleted
    // - Only tests that are NOT linked to a bill that is paid
    // ================================================================
    
    // Check if test has bill items
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM bill_items 
        WHERE reference_id = ? AND reference_type = 'lab_test' AND status != 'cancelled'
    ");
    $stmt->execute([$test_id]);
    $bill_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    if ($bill_count > 0) {
        // Check if bill is paid
        $stmt = $db->prepare("
            SELECT b.status 
            FROM bill_items bi
            LEFT JOIN bills b ON bi.bill_id = b.id
            WHERE bi.reference_id = ? AND bi.reference_type = 'lab_test'
            LIMIT 1
        ");
        $stmt->execute([$test_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bill && $bill['status'] === 'paid') {
            // Cannot delete if bill is paid
            header('Location: lab_tests.php?branch=' . urlencode($branch_id) . '&error=cannot_delete_paid');
            exit;
        }
    }

    // ================================================================
    // START TRANSACTION - DELETE LAB TEST
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

    // 4. If this test was the only test for a visit, update visit status
    $visit_id = $test['visit_id'] ?? null;
    if ($visit_id) {
        // Check if there are any other lab tests for this visit
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE visit_id = ? AND id != ?
        ");
        $stmt->execute([$visit_id, $test_id]);
        $remaining_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        if ($remaining_tests == 0) {
            // No more lab tests, update visit status back to assigned
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'assigned', updated_at = NOW() 
                WHERE id = ? AND status = 'lab_test'
            ");
            $stmt->execute([$visit_id]);
        }
    }

    // 5. Update bill totals
    // Find the bill for this visit
    if ($visit_id) {
        $stmt = $db->prepare("
            SELECT id FROM bills WHERE visit_id = ?
        ");
        $stmt->execute([$visit_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bill) {
            // Recalculate bill totals
            $stmt = $db->prepare("
                SELECT SUM(total_price) as total 
                FROM bill_items 
                WHERE bill_id = ? AND status != 'cancelled'
            ");
            $stmt->execute([$bill['id']]);
            $subtotal = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
            
            $stmt = $db->prepare("
                SELECT total_discount FROM bills WHERE id = ?
            ");
            $stmt->execute([$bill['id']]);
            $discount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_discount'] ?? 0);
            
            $total_amount = max(0, $subtotal - $discount);
            
            $stmt = $db->prepare("
                SELECT SUM(amount) as payment_total FROM payments WHERE bill_id = ?
            ");
            $stmt->execute([$bill['id']]);
            $paid_amount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['payment_total'] ?? 0);
            
            $balance = $total_amount - $paid_amount;
            
            // Determine bill status
            if ($total_amount == 0) {
                $bill_status = 'pending';
            } elseif ($balance <= 0 && $total_amount > 0) {
                $bill_status = 'paid';
            } elseif ($paid_amount > 0 && $balance > 0) {
                $bill_status = 'partial';
            } else {
                $bill_status = 'pending';
            }
            
            $stmt = $db->prepare("
                UPDATE bills 
                SET subtotal = ?, total_amount = ?, paid_amount = ?, balance = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$subtotal, $total_amount, $paid_amount, $balance, $bill_status, $bill['id']]);
        }
    }

    // Commit transaction
    $db->commit();

    // Redirect with success message
    header('Location: lab_tests.php?branch=' . urlencode($branch_id) . '&error=delete_success');
    exit;

} catch (Exception $e) {
    // Rollback on error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Delete lab test error: " . $e->getMessage());
    header('Location: lab_tests.php?branch=' . urlencode($branch_id) . '&error=database_error');
    exit;
}
?>