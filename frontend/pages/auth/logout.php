<?php
// logout.php
session_start();
session_destroy();
header('Location: frontend/pages/auth/login.php');
exit;