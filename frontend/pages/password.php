<?php
// hash_all_users.php - Run once, then delete
require_once __DIR__ . '/../../backend/config/database.php';

$newPassword = 'Braick01';
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

$db = Database::getInstance()->getConnection();

// Update all users
$stmt = $db->prepare("UPDATE users SET password = ?, is_default_password = 1, password_changed_at = NOW() WHERE 1=1");
$stmt->execute([$hashedPassword]);

echo "✅ All passwords updated to: Braick01\n";
echo "Hash used: " . $hashedPassword . "\n";
echo "Try logging in now!\n";
?>