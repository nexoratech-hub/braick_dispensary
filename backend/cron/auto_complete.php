<?php
// ================================================================
// FILE: backend/cron/auto_complete.php
// AUTO-COMPLETE + AUTO-DISPENSE (CRON JOB)
// ✅ Inafanya kazi 24/7 — haihitaji mtu online
// ✅ Inaangalia bills paid → inacomplete visits
// ✅ Inadispense medications → inapunguza stock
// ✅ Ina-clear assigned_doctor_id
// ✅ Ina-log kwenye activity_logs
// ================================================================

// ✅ Usiruhusu ku-run kwenye web (security)
if (php_sapi_name() !== 'cli' && !isset($_GET['cron_secret'])) {
    // Kama unataka pia kupitia URL, weka secret
    $secret = 'CHANGE_THIS_TO_RANDOM_STRING_12345';
    if (!isset($_GET['cron_secret']) || $_GET['cron_secret'] !== $secret) {
        http_response_code(403);
        die('Access denied');
    }
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $start_time = microtime(true);
    
    // ✅ Auto-complete visits zenye bills paid
    $result = autoCompletePaidVisits($db, null, 0);
    
    $duration = round((microtime(true) - $start_time) * 1000, 2);
    
    // Log output
    $log_message = sprintf(
        "[%s] Auto-complete: %d visits completed, %d medications dispensed, %d errors, duration: %sms",
        date('Y-m-d H:i:s'),
        $result['visits_completed'] ?? 0,
        $result['dispensed'] ?? 0,
        count($result['errors'] ?? []),
        $duration
    );
    
    // Andika kwenye log file
    $log_file = __DIR__ . '/../../logs/auto_complete.log';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    @file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
    
    // Print kama ni CLI
    if (php_sapi_name() === 'cli') {
        echo $log_message . PHP_EOL;
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                echo "  ERROR: $error" . PHP_EOL;
            }
        }
    } else {
        // Kama ni HTTP request, rudisha JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'visits_completed' => $result['visits_completed'] ?? 0,
            'dispensed' => $result['dispensed'] ?? 0,
            'errors' => $result['errors'] ?? [],
            'duration_ms' => $duration,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
} catch (Exception $e) {
    $error_message = '[' . date('Y-m-d H:i:s') . '] CRON ERROR: ' . $e->getMessage();
    $log_file = __DIR__ . '/../../logs/auto_complete.log';
    @file_put_contents($log_file, $error_message . PHP_EOL, FILE_APPEND);
    
    if (php_sapi_name() === 'cli') {
        echo $error_message . PHP_EOL;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}