<?php
// ================================================================
// FILE: frontend/api/get_online_doctors.php
// RETURNS ONLINE DOCTORS FOR RECEPTION
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DEFAULT
// ================================================================
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 6;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
}

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : $user_branch_id;

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET ALL DOCTORS IN THIS BRANCH
// ================================================================
$stmt = $db->prepare("
    SELECT id, full_name, specialty, is_online 
    FROM users 
    WHERE role = 'doctor' AND status = 'active' AND branch_id = ?
    ORDER BY is_online DESC, full_name
");
$stmt->execute([$branch_id]);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// COUNT ONLINE DOCTORS
// ================================================================
$online_count = 0;
foreach ($doctors as $doc) {
    if ($doc['is_online'] == 1) {
        $online_count++;
    }
}
$total_doctors = count($doctors);

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

echo json_encode([
    'success' => true,
    'doctors' => $doctors,
    'online_count' => $online_count,
    'total_doctors' => $total_doctors,
    'branch_id' => $branch_id,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>