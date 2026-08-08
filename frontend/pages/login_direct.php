<?php
// ================================================================
// login_direct.php - DIRECT LOGIN TO RECEPTION
// USE THIS ONLY FOR TESTING
// ================================================================

session_start();

// Force login as reception.rose
$_SESSION['user_id'] = 6;
$_SESSION['username'] = 'reception.rose';
$_SESSION['full_name'] = 'Rose Mwangi';
$_SESSION['role'] = 'reception';
$_SESSION['branch_id'] = 1;
$_SESSION['branch_name'] = 'Dodoma';
$_SESSION['email'] = 'rose@braick.com';
$_SESSION['phone'] = '+255 700 000 005';
$_SESSION['profile_pic'] = '';
$_SESSION['login_time'] = time();

// Redirect to reception dashboard
header('Location: frontend/pages/reception/dashboard.php');
exit;
?>