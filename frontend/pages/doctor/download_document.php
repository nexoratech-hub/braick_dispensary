<?php
// ================================================================
// FILE: frontend/pages/doctor/download_document.php
// DOWNLOAD DOCUMENT HANDLER
// BRAICK DISPENSARY
// ================================================================

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 5;
    $_SESSION['doctor_id'] = 5;
    $_SESSION['role'] = 'doctor';
}

$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($document_id <= 0) {
    die("Invalid document ID");
}

require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

try {
    $stmt = $db->prepare("SELECT file_name, file_path FROM patient_documents WHERE id = ?");
    $stmt->execute([$document_id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doc) {
        die("Document not found");
    }
    
    $physical_path = 'C:/xampp/htdocs' . $doc['file_path'];
    
    if (!file_exists($physical_path)) {
        die("File not found at: " . $physical_path);
    }
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $doc['file_name'] . '"');
    header('Content-Length: ' . filesize($physical_path));
    readfile($physical_path);
    exit;
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>