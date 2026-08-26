<?php
// ================================================================
// FILE: frontend/pages/doctor/complete_waiting_consultations.php
// MANUAL SCRIPT - Complete all waiting consultations with paid bills
// USING NEW DATABASE: dispensary_db
// BRAICK DISPENSARY
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS DOCTOR OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER INFO FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['full_name'] ?? 'User';
$user_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// INCLUDE DATABASE - USING NEW DATABASE (dispensary_db)
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// HANDLE FORCE COMPLETE
// ================================================================
if (isset($_GET['force_complete']) && is_numeric($_GET['force_complete'])) {
    $force_visit_id = (int)$_GET['force_complete'];
    
    $is_admin = ($user_role === 'admin');
    
    if ($is_admin) {
        $stmt = $db->prepare("
            UPDATE visits 
            SET status = 'completed', 
                is_completed = 1, 
                completed_at = NOW(), 
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$force_visit_id]);
    } else {
        $stmt = $db->prepare("
            UPDATE visits 
            SET status = 'completed', 
                is_completed = 1, 
                completed_at = NOW(), 
                updated_at = NOW()
            WHERE id = ? AND doctor_id = ?
        ");
        $stmt->execute([$force_visit_id, $user_id]);
    }
    
    // Log activity
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_logs (
                user_id, 
                branch_id, 
                action, 
                details, 
                created_at
            ) VALUES (?, ?, 'visit_force_completed', ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $user_branch_id,
            "Visit #$force_visit_id force completed by " . ($is_admin ? 'admin' : 'doctor') . ": " . $user_name
        ]);
    } catch (Exception $e) {}
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?completed=1');
    exit;
}

// ================================================================
// GET ALL WAITING VISITS - Using new database tables
// ================================================================
$is_admin = ($user_role === 'admin');

if ($is_admin) {
    // Admin can see all waiting visits
    $stmt = $db->prepare("
        SELECT v.id, v.visit_number, v.patient_id, v.status, v.is_completed, v.doctor_id,
               p.full_name as patient_name,
               u.full_name as doctor_name,
               (SELECT COUNT(*) FROM bills WHERE visit_id = v.id AND status IN ('pending', 'partial')) as pending_bills,
               (SELECT COUNT(*) FROM bills WHERE visit_id = v.id AND status = 'paid') as paid_bills,
               (SELECT COUNT(*) FROM bills WHERE visit_id = v.id) as total_bills
        FROM visits v
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.status IN ('pending', 'assigned', 'with_doctor', 'lab_test', 'prescribed')
        AND v.is_completed = 0
        ORDER BY v.id DESC
    ");
    $stmt->execute();
} else {
    // Doctor can only see their own waiting visits
    $stmt = $db->prepare("
        SELECT v.id, v.visit_number, v.patient_id, v.status, v.is_completed, v.doctor_id,
               p.full_name as patient_name,
               u.full_name as doctor_name,
               (SELECT COUNT(*) FROM bills WHERE visit_id = v.id AND status IN ('pending', 'partial')) as pending_bills,
               (SELECT COUNT(*) FROM bills WHERE visit_id = v.id AND status = 'paid') as paid_bills,
               (SELECT COUNT(*) FROM bills WHERE visit_id = v.id) as total_bills
        FROM visits v
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.status IN ('pending', 'assigned', 'with_doctor', 'lab_test', 'prescribed')
        AND v.is_completed = 0
        AND v.doctor_id = ?
        ORDER BY v.id DESC
    ");
    $stmt->execute([$user_id]);
}

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
        if ($is_admin) {
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'completed', 
                    is_completed = 1, 
                    completed_at = NOW(), 
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$visit['id']]);
        } else {
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'completed', 
                    is_completed = 1, 
                    completed_at = NOW(), 
                    updated_at = NOW()
                WHERE id = ? AND doctor_id = ?
            ");
            $stmt->execute([$visit['id'], $user_id]);
        }
        
        $completed_count++;
        $updated_visits[] = $visit;
        
        // Log activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_logs (
                    user_id, 
                    branch_id, 
                    action, 
                    details, 
                    created_at
                ) VALUES (?, ?, 'visit_auto_completed', ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $user_branch_id,
                "Visit #" . $visit['visit_number'] . " auto-completed - Bills: $total (All paid) | Patient: " . ($visit['patient_name'] ?? 'Unknown')
            ]);
        } catch (Exception $e) {}
    }
}

// ================================================================
// GET STATS FOR DOCTOR - Using new database
// ================================================================
$doctor_stats = [];
if (!$is_admin) {
    try {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_patients,
                SUM(CASE WHEN status IN ('pending', 'assigned', 'with_doctor', 'lab_test', 'prescribed') AND is_completed = 0 THEN 1 ELSE 0 END) as waiting_patients,
                SUM(CASE WHEN status = 'completed' AND is_completed = 1 THEN 1 ELSE 0 END) as completed_patients
            FROM visits 
            WHERE doctor_id = ?
        ");
        $stmt->execute([$user_id]);
        $doctor_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// ================================================================
// DISPLAY RESULTS
// ================================================================
$message = isset($_GET['completed']) ? '✅ Visit force completed successfully!' : '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Complete Waiting Consultations</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2563EB; }
        .success { color: #059669; font-weight: bold; }
        .warning { color: #D97706; }
        .info { color: #2563EB; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2563EB; color: white; }
        tr:hover { background: #f1f5f9; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
        .badge-completed { background: #D1FAE5; color: #059669; }
        .badge-pending { background: #FEF3C7; color: #D97706; }
        .badge-paid { background: #D1FAE5; color: #059669; }
        .badge-waiting { background: #FEE2E2; color: #DC2626; }
        .badge-lab { background: #EDE9FE; color: #7C3AED; }
        .btn { display: inline-block; padding: 10px 24px; background: #2563EB; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; border: none; cursor: pointer; }
        .btn:hover { background: #1D4ED8; }
        .btn-success { background: #059669; }
        .btn-success:hover { background: #047857; }
        .btn-danger { background: #DC2626; }
        .btn-danger:hover { background: #B91C1C; }
        .btn-sm { padding: 4px 12px; font-size: 0.7rem; }
        .stats { display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap; }
        .stat-box { background: #f8fafc; padding: 15px 20px; border-radius: 8px; border: 1px solid #e2e8f0; flex: 1; min-width: 120px; text-align: center; }
        .stat-box .number { font-size: 2rem; font-weight: 700; }
        .stat-box .label { font-size: 0.8rem; color: #64748B; }
        .stat-box .number.purple { color: #7C3AED; }
        .stat-box .number.green { color: #059669; }
        .stat-box .number.orange { color: #D97706; }
        .stat-box .number.red { color: #DC2626; }
        .stat-box .number.blue { color: #2563EB; }
        .alert { padding: 12px 16px; border-radius: 8px; margin: 10px 0; }
        .alert-success { background: #D1FAE5; color: #059669; border: 1px solid #6EE7B7; }
        .alert-info { background: #DBEAFE; color: #1E40AF; border: 1px solid #93C5FD; }
        .alert-warning { background: #FEF3C7; color: #92400E; border: 1px solid #FBBF24; }
        .user-info { background: #f8fafc; padding: 10px 16px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2563EB; }
        .badge-success { background: #D1FAE5; color: #059669; }
        .badge-danger { background: #FEE2E2; color: #DC2626; }
        .badge-warning { background: #FEF3C7; color: #D97706; }
        .badge-info { background: #DBEAFE; color: #2563EB; }
        @media (max-width: 768px) {
            body { margin: 20px; }
            .container { padding: 15px; }
            .stats { flex-direction: column; }
            table { font-size: 0.8rem; }
            th, td { padding: 6px 8px; }
            .stat-box .number { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🔄 Complete Waiting Consultations</h1>
    
    <!-- User Info -->
    <div class="user-info">
        <strong>👤 <?= htmlspecialchars($user_name) ?></strong>
        <span style="margin-left: 16px; background: #E8F0FE; padding: 2px 12px; border-radius: 12px; font-size: 0.75rem; color: #2563EB;">
            <?= ucfirst(htmlspecialchars($user_role)) ?>
        </span>
        <?php if (!$is_admin): ?>
            <span style="margin-left: 16px; color: #64748B; font-size: 0.8rem;">
                <i class="fas fa-user-md"></i> Your Patients Only
            </span>
        <?php else: ?>
            <span style="margin-left: 16px; color: #DC2626; font-size: 0.8rem;">
                <i class="fas fa-user-shield"></i> Admin Mode - All Patients
            </span>
        <?php endif; ?>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>
    
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
        <?php if (!$is_admin && $doctor_stats): ?>
            <div class="stat-box">
                <div class="number blue"><?= $doctor_stats['total_patients'] ?? 0 ?></div>
                <div class="label">Total Patients</div>
            </div>
            <div class="stat-box">
                <div class="number green"><?= $doctor_stats['completed_patients'] ?? 0 ?></div>
                <div class="label">Completed</div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($completed_count > 0): ?>
        <div class="alert alert-success">
            ✅ <strong><?= $completed_count ?></strong> consultation(s) completed successfully!
        </div>
        
        <h3>📋 Completed Visits</h3>
        <table>
            <thead>
                <tr>
                    <th>Visit #</th>
                    <th>Patient</th>
                    <th>Doctor</th>
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
                        <td><?= htmlspecialchars($visit['patient_name'] ?? $visit['patient_id']) ?></td>
                        <td><?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></td>
                        <td><span class="badge badge-warning"><?= htmlspecialchars($visit['status']) ?></span></td>
                        <td><?= $visit['total_bills'] ?></td>
                        <td><?= $visit['paid_bills'] ?></td>
                        <td><span class="badge badge-success">✅ Completed</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="alert alert-warning">
            ⚠️ No waiting consultations were completed. Either all waiting consultations have pending bills or none are waiting.
        </div>
    <?php endif; ?>
    
    <!-- Show all waiting visits -->
    <h3>📋 All Waiting Visits</h3>
    <?php if (count($waiting_visits) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Visit #</th>
                    <th>Patient</th>
                    <th>Doctor</th>
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
                        <td><?= htmlspecialchars($visit['patient_name'] ?? $visit['patient_id']) ?></td>
                        <td><?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></td>
                        <td>
                            <span class="badge <?= $visit['status'] === 'lab_test' ? 'badge-lab' : 'badge-waiting' ?>">
                                <?= htmlspecialchars($visit['status']) ?>
                            </span>
                        </td>
                        <td><?= $visit['pending_bills'] ?></td>
                        <td><?= $visit['paid_bills'] ?></td>
                        <td><?= $visit['total_bills'] ?></td>
                        <td>
                            <?php if ($visit['pending_bills'] == 0 && $visit['total_bills'] > 0): ?>
                                <a href="?force_complete=<?= $visit['id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Complete this consultation?')">✅ Complete</a>
                            <?php else: ?>
                                <span class="warning">⏳ Waiting</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="alert alert-info">
            ℹ️ No waiting visits found. All consultations are completed or none exist.
        </div>
    <?php endif; ?>
    
    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px;">
        <a href="consultations.php" class="btn">📋 Go to Consultations</a>
        <a href="dashboard.php" class="btn" style="background:#64748B;">🏠 Dashboard</a>
        <a href="appointments.php" class="btn" style="background:#7C3AED;">📅 Appointments</a>
        <?php if ($is_admin): ?>
            <a href="?admin=1" class="btn btn-danger" style="background:#DC2626;" onclick="return confirm('Run auto-complete for all visits?')">🔄 Auto-Complete All</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>