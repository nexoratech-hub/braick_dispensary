<?php
// ================================================================
// FILE: logout.php
// BRAICK DISPENSARY - LOGOUT
// Clears session and redirects to login
// ================================================================

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// INCLUDE AUTH HELPER
// ================================================================
require_once __DIR__ . '/backend/core/Auth.php';

// ================================================================
// PERFORM LOGOUT
// ================================================================
$auth = new Auth();
$auth->logout(true); // true = redirect to login

// If for some reason logout doesn't redirect, do it manually
header('Location: /dispensary_system/frontend/pages/login.php');
exit;
?>