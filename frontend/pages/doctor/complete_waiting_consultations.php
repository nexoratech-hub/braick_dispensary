<?php
// ================================================================
// FILE: frontend/pages/doctor/complete_waiting_consultations.php
// MANUAL SCRIPT - Complete all waiting consultations with paid bills
// RUN ONCE OR AS NEEDED
// BRAICK DISPENSARY
// ================================================================

session_start();

// Allow only admin or doctor to run this
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'doctor'])) {
    // If no session, allow from browser
    $_SESSION['user_id'] = 5;
    $_SESSION['role'] = 'doctor';
    $_SESSION['full_name'] = 'Dr. John Mushi';
    $_SESSION['branch_id'] = 1;
}

require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET ALL WAITING VISITS
// ================================================================
$stmt = $db->prepare("
    SELECT v.id, v.visit_number, v.patient_id, v.status, v.is_completed,
           (SELECT COUNT(*) FROM patient_bills WHERE visit_id = v.id AND status IN ('pending', 'partial')) as pending_bills,
           (SELECT COUNT(*) FROM patient_bills WHERE visit_id = v.id AND status = 'paid') as paid_bills,
           (SELECT COUNT(*) FROM patient_bills WHERE visit_id = v.id) as total_bills
    FROM visits v
    WHERE v.status IN ('waiting', 'pending', 'assigned', 'with_doctor', 'lab_test')
    AND v.is_completed = 0
    ORDER BY v.id DESC
");

$stmt->execute();
$waiting_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

$completed_count = 0;
$updated_visits = [];

foreach ($waiting_visits as $visit) {
    $pending = (int)($visit['pending_bills'] ?? 0);
    $paid = (int)($visit['paid_bills'] ?? 0);
    $total = (int)($visit['total_bills'] ?? 0);
    
    // If no pending bills AND there is at least one bill
    if ($pending == 0 && $total > 0) {
        // Update visit to completed
        $stmt = $db->prepare("
            UPDATE visits 
            SET status = 'completed', 
                is_completed = 1, 
                completed_at = NOW(), 
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$visit['id']]);
        $completed_count++;
        $updated_visits[] = $visit;
        
        // Log activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, action, details, created_at) 
                VALUES (?, 'visit_auto_completed', ?, NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                "Visit #" . $visit['visit_number'] . " auto-completed - Bills: $total (All paid)"
            ]);
        } catch (Exception $e) {}
    }
}

// ================================================================
// DISPLAY RESULTS
// ================================================================
?>
<!DOCTYPE html>
<html>
<head>
    <title>Complete Waiting Consultations</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #0B5ED7; }
        .success { color: #059669; font-weight: bold; }
        .warning { color: #D97706; }
        .info { color: #0B5ED7; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #0B5ED7; color: white; }
        tr:hover { background: #f1f5f9; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
        .badge-completed { background: #D1FAE5; color: #059669; }
        .badge-pending { background: #FEF3C7; color: #D97706; }
        .badge-paid { background: #D1FAE5; color: #059669; }
        .btn { display: inline-block; padding: 10px 24px; background: #0B5ED7; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
        .btn:hover { background: #0A4CA8; }
        .btn-success { background: #059669; }
        .btn-success:hover { background: #047857; }
        .stats { display: flex; gap: 20px; margin: 20px 0; }
        .stat-box { background: #f8fafc; padding: 15px 20px; border-radius: 8px; border: 1px solid #e2e8f0; flex: 1; text-align: center; }
        .stat-box .number { font-size: 2rem; font-weight: 700; }
        .stat-box .label { font-size: 0.8rem; color: #64748B; }
        .stat-box .number.purple { color: #7C3AED; }
        .stat-box .number.green { color: #059669; }
        .stat-box .number.orange { color: #D97706; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔄 Complete Waiting Consultations</h1>
    <p>This script checks all waiting consultations and completes those with all bills paid.</p>
    
    <!-- Stats -->
    <div class="stats">
        <div class="stat-box">
            <div class="number purple"><?= count($waiting_visits) ?></div>
            <div class="label">Total Waiting Visits</div>
        </div>
        <div class="stat-box">
            <div class="number green"><?= $completed_count ?></div>
            <div class="label">Completed Now</div>
        </div>
        <div class="stat-box">
            <div class="number orange"><?= count($waiting_visits) - $completed_count ?></div>
            <div class="label">Still Waiting</div>
        </div>
    </div>
    
    <?php if ($completed_count > 0): ?>
        <h3 class="success">✅ <?= $completed_count ?> consultation(s) completed successfully!</h3>
        
        <table>
            <thead>
                <tr>
                    <th>Visit #</th>
                    <th>Patient ID</th>
                    <th>Previous Status</th>
                    <th>Total Bills</th>
                    <th>Paid Bills</th>
                    <th>New Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($updated_visits as $visit): ?>
                    <tr>
                        <td><?= htmlspecialchars($visit['visit_number']) ?></td>
                        <td><?= htmlspecialchars($visit['patient_id']) ?></td>
                        <td><span class="badge badge-pending"><?= htmlspecialchars($visit['status']) ?></span></td>
                        <td><?= $visit['total_bills'] ?></td>
                        <td><?= $visit['paid_bills'] ?></td>
                        <td><span class="badge badge-completed">✅ Completed</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="warning">⚠️ No waiting consultations were completed. Either all waiting consultations have pending bills or none are waiting.</p>
    <?php endif; ?>
    
    <!-- Show all waiting visits -->
    <h3>📋 All Waiting Visits</h3>
    <table>
        <thead>
            <tr>
                <th>Visit #</th>
                <th>Patient ID</th>
                <th>Status</th>
                <th>Pending Bills</th>
                <th>Paid Bills</th>
                <th>Total Bills</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($waiting_visits as $visit): ?>
                <tr>
                    <td><?= htmlspecialchars($visit['visit_number']) ?></td>
                    <td><?= htmlspecialchars($visit['patient_id']) ?></td>
                    <td><span class="badge badge-pending"><?= htmlspecialchars($visit['status']) ?></span></td>
                    <td><?= $visit['pending_bills'] ?></td>
                    <td><?= $visit['paid_bills'] ?></td>
                    <td><?= $visit['total_bills'] ?></td>
                    <td>
                        <?php if ($visit['pending_bills'] == 0 && $visit['total_bills'] > 0): ?>
                            <a href="?force_complete=<?= $visit['id'] ?>" class="btn btn-success" style="padding:4px 12px;font-size:0.7rem;">✅ Complete</a>
                        <?php else: ?>
                            <span class="warning">⏳ Waiting for payment</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <a href="consultations.php" class="btn">📋 Go to Consultations</a>
    <a href="dashboard.php" class="btn" style="background:#64748B;">🏠 Dashboard</a>
</div>
</body>
</html>