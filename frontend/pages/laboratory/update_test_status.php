<?php
// ================================================================
// FILE: frontend/pages/laboratory/update_test_status.php
// LABORATORY - UPDATE TEST STATUS (Complete Version)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE LAB.DODOMA (ID: 8) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
    $_SESSION['user_id'] = 8;
    $_SESSION['full_name'] = 'Lab Technician Dodoma';
    $_SESSION['role'] = 'laboratory';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'lab.dodoma';
}

$user_id = $_SESSION['user_id'] ?? 8;
$user_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET PARAMETERS
// ================================================================
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;

// POST data
$result = isset($_POST['result']) ? trim($_POST['result']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

$response = [
    'success' => false,
    'message' => 'Invalid action',
    'redirect' => ''
];

// ================================================================
// ACTION: CONFIRM_TEST - Confirm a single test from lab_tests
// ================================================================
if ($action === 'confirm_test' && $id > 0) {
    try {
        // Check if test exists in lab_tests
        $stmt = $db->prepare("SELECT * FROM lab_tests WHERE id = ? AND branch_id = ?");
        $stmt->execute([$id, $user_branch_id]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) {
            header('Location: pending_requests.php?success=0&message=Test+not+found');
            exit;
        }
        
        // Update test status to 'in_progress'
        $stmt = $db->prepare("
            UPDATE lab_tests 
            SET status = 'in_progress', lab_technician_id = ? 
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$user_id, $id, $user_branch_id]);
        
        // Create bill for this test (if price exists)
        createTestBill($db, $id, $user_id, $user_branch_id);
        
        header('Location: in_progress.php?success=1&message=Test+confirmed+successfully!');
        exit;
        
    } catch (Exception $e) {
        header('Location: pending_requests.php?success=0&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// ================================================================
// ACTION: CANCEL_TEST - Cancel a single test from lab_tests
// ================================================================
if ($action === 'cancel_test' && $id > 0) {
    try {
        // Update test status to 'cancelled'
        $stmt = $db->prepare("
            UPDATE lab_tests 
            SET status = 'cancelled' 
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$id, $user_branch_id]);
        
        header('Location: pending_requests.php?success=1&message=Test+cancelled+successfully!');
        exit;
        
    } catch (Exception $e) {
        header('Location: pending_requests.php?success=0&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// ================================================================
// ACTION: ACCEPT - Accept a full request from lab_requests
// ================================================================
if ($action === 'accept' && $id > 0) {
    try {
        // Get request details
        $stmt = $db->prepare("SELECT * FROM lab_requests WHERE id = ? AND branch_id = ?");
        $stmt->execute([$id, $user_branch_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            header('Location: pending_requests.php?success=0&message=Request+not+found');
            exit;
        }
        
        // Update request status
        $stmt = $db->prepare("
            UPDATE lab_requests 
            SET status = 'accepted', accepted_at = NOW(), lab_technician_id = ?
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$user_id, $id, $user_branch_id]);
        
        // Update all items to in_progress
        $stmt = $db->prepare("
            UPDATE lab_request_items 
            SET status = 'in_progress'
            WHERE request_id = ?
        ");
        $stmt->execute([$id]);
        
        header('Location: in_progress.php?success=1&message=Request+accepted+successfully!');
        exit;
        
    } catch (Exception $e) {
        header('Location: pending_requests.php?success=0&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// ================================================================
// ACTION: COMPLETE - Complete entire request
// ================================================================
if ($action === 'complete' && $id > 0) {
    try {
        // Update all items to completed
        $stmt = $db->prepare("
            UPDATE lab_request_items 
            SET status = 'completed', completed_at = NOW()
            WHERE request_id = ?
        ");
        $stmt->execute([$id]);
        
        // Update request
        $stmt = $db->prepare("
            UPDATE lab_requests 
            SET status = 'completed', completed_at = NOW()
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$id, $user_branch_id]);
        
        header('Location: completed_requests.php?success=1&message=Request+completed+successfully!');
        exit;
        
    } catch (Exception $e) {
        header('Location: in_progress.php?success=0&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// ================================================================
// ACTION: CANCEL - Cancel request
// ================================================================
if ($action === 'cancel' && $id > 0) {
    try {
        $stmt = $db->prepare("
            UPDATE lab_requests 
            SET status = 'cancelled', cancelled_at = NOW()
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$id, $user_branch_id]);
        
        header('Location: pending_requests.php?success=1&message=Request+cancelled!');
        exit;
        
    } catch (Exception $e) {
        header('Location: pending_requests.php?success=0&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// ================================================================
// ACTION: UPDATE_TEST - Update individual test item
// ================================================================
if ($action === 'update_test' && $id > 0 && $test_id > 0) {
    try {
        if (empty($status)) {
            header('Location: view_request.php?id=' . $id . '&success=0&message=Status+is+required');
            exit;
        }
        
        $stmt = $db->prepare("
            UPDATE lab_request_items 
            SET status = ?, result = ?, comments = ?, 
                completed_at = CASE WHEN ? = 'completed' THEN NOW() ELSE completed_at END
            WHERE id = ? AND request_id = ?
        ");
        $stmt->execute([$status, $result, $notes, $status, $test_id, $id]);
        
        // Check if all tests are completed
        $stmt_check = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM lab_request_items 
            WHERE request_id = ?
        ");
        $stmt_check->execute([$id]);
        $check = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        $request_status = 'in_progress';
        if ($check['total'] == $check['completed'] && $check['total'] > 0) {
            $request_status = 'completed';
        }
        
        // Update request status
        $stmt_update = $db->prepare("
            UPDATE lab_requests 
            SET status = ?, completed_at = CASE WHEN ? = 'completed' THEN NOW() ELSE completed_at END
            WHERE id = ?
        ");
        $stmt_update->execute([$request_status, $request_status, $id]);
        
        header('Location: view_request.php?id=' . $id . '&success=1&message=Test+updated+successfully!');
        exit;
        
    } catch (Exception $e) {
        header('Location: view_request.php?id=' . $id . '&success=0&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// ================================================================
// ================================================================
// HELPER FUNCTIONS
// ================================================================

function createTestBill($db, $test_id, $user_id, $branch_id) {
    try {
        // Get test details
        $stmt = $db->prepare("
            SELECT lt.*, v.patient_id, v.id as visit_id
            FROM lab_tests lt
            JOIN visits v ON lt.visit_id = v.id
            WHERE lt.id = ?
        ");
        $stmt->execute([$test_id]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) return null;
        
        $patient_id = $test['patient_id'];
        $visit_id = $test['visit_id'];
        $test_name = $test['test_name'];
        
        // Get test price from catalog
        $stmt = $db->prepare("
            SELECT price FROM lab_tests_catalog 
            WHERE test_name = ? 
            LIMIT 1
        ");
        $stmt->execute([$test_name]);
        $catalog = $stmt->fetch(PDO::FETCH_ASSOC);
        $price = $catalog['price'] ?? 0;
        
        if ($price <= 0) return null;
        
        // Check if bill exists
        $stmt = $db->prepare("
            SELECT id FROM patient_bills 
            WHERE patient_id = ? AND visit_id = ? AND status != 'paid'
        ");
        $stmt->execute([$patient_id, $visit_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bill) {
            $bill_id = $bill['id'];
            
            // Check if already added
            $stmt = $db->prepare("
                SELECT id FROM bill_items 
                WHERE bill_id = ? AND item_name = ? AND item_type = 'lab_test'
            ");
            $stmt->execute([$bill_id, $test_name]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existing) {
                $stmt = $db->prepare("
                    INSERT INTO bill_items (bill_id, item_type, item_name, quantity, unit_price, total_price, department)
                    VALUES (?, 'lab_test', ?, 1, ?, ?, 'Laboratory')
                ");
                $stmt->execute([$bill_id, $test_name, $price, $price]);
                
                // Update patient_bills
                $stmt = $db->prepare("
                    UPDATE patient_bills 
                    SET subtotal = subtotal + ?,
                        total_amount = total_amount + ?,
                        balance = balance + ?
                    WHERE id = ?
                ");
                $stmt->execute([$price, $price, $price, $bill_id]);
            }
        } else {
            // Create new bill
            $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($patient_id, 6, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("
                INSERT INTO patient_bills (
                    bill_number, patient_id, visit_id, subtotal, total_amount, balance,
                    status, created_by, branch_id
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
            ");
            $stmt->execute([$bill_number, $patient_id, $visit_id, $price, $price, $price, $user_id, $branch_id]);
            $bill_id = $db->lastInsertId();
            
            // Add to bill_items
            $stmt = $db->prepare("
                INSERT INTO bill_items (bill_id, item_type, item_name, quantity, unit_price, total_price, department)
                VALUES (?, 'lab_test', ?, 1, ?, ?, 'Laboratory')
            ");
            $stmt->execute([$bill_id, $test_name, $price, $price]);
        }
        
        return $bill_id;
        
    } catch (Exception $e) {
        error_log("Bill creation error: " . $e->getMessage());
        return null;
    }
}

// ================================================================
// IF ACTION NOT RECOGNIZED
// ================================================================
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'pending_requests.php';
header('Location: ' . $referer . '?success=0&message=' . urlencode('Invalid action: ' . $action));
exit;
?>