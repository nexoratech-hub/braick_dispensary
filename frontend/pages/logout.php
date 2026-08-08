<?php
// ================================================================
// FILE: frontend/pages/logout.php
// BRAICK DISPENSARY - LOGOUT
// ================================================================

session_start();

require_once __DIR__ . '/../../backend/core/Auth.php';

$auth = new Auth();
$auth->logout(true);
?>