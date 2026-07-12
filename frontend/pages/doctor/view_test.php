<?php
// ================================================================
// FILE: frontend/pages/doctor/view_test.php
// DOCTOR - VIEW SINGLE LAB TEST (ALIAS)
// BRAICK DISPENSARY
// ================================================================

// Redirect to view_lab_test.php
$test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($test_id > 0) {
    header('Location: view_lab_test.php?id=' . $test_id);
    exit;
} else {
    header('Location: lab_results.php');
    exit;
}
?>