<?php
// ================================================================
// FILE: frontend/pages/doctor/new_visit.php
// DOCTOR - NEW VISIT
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DR. SARAH MWAMBA (ID: 2) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 2;
    $_SESSION['full_name'] = 'Dr. Sarah Mwamba';
    $_SESSION['username'] = 'dr.sarah';
    $_SESSION['email'] = 'sarah@braick.com';
    $_SESSION['phone'] = '+255 700 000 001';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['specialty'] = 'Cardiology';
}

$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// INCLUDE DATABASE
// ================================================================
$db_path = 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("❌ Database file not found at: " . $db_path);
}
$db = Database::getInstance()->getConnection();

// ================================================================
// GET PATIENT LIST FOR SELECTION
// ================================================================
$stmt = $db->prepare("
    SELECT p.* FROM patients p
    JOIN visits v ON p.id = v.patient_id
    WHERE v.doctor_id = ?
    GROUP BY p.id
    ORDER BY p.full_name
");
$stmt->execute([$doctor_id]);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET DOCTOR'S BRANCH NAME
// ================================================================
$doctor_branch_name = 'Not Assigned';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $doctor_branch_name = $branch_data['name'];
    }
} catch (Exception $e) {
    $doctor_branch_name = 'Branch';
}

// ================================================================
// VARIABLES FOR SIDEBAR
// ================================================================
$selected_branch_id = $doctor_branch_id;
$total_employees = 0;
$total_doctors = 0;
$total_branches = 0;
$pending_lab_tests = 0;
$pending_prescriptions = 0;

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_header.php';
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_sidebar.php';
?>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="page-title">
                <i class="fas fa-plus-circle mr-2" style="color: #0B5ED7;"></i> New Visit
            </h1>
            <p class="page-subtitle">
                Start a new patient visit
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
            </p>
        </div>
        <a href="my_patients.php" class="btn btn-outline btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <div class="card max-w-2xl mx-auto">
        <form method="POST" action="save_visit.php">
            <div class="space-y-4">
                <!-- Patient Selection -->
                <div>
                    <label class="form-label">Select Patient <span class="text-red-500">*</span></label>
                    <select name="patient_id" class="form-control" required>
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= $patient['id'] ?>">
                                <?= htmlspecialchars($patient['full_name']) ?> (<?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">
                        <a href="new_patient.php" class="text-blue-600 hover:underline">+ Add New Patient</a>
                    </p>
                </div>

                <!-- Visit Type -->
                <div>
                    <label class="form-label">Visit Type <span class="text-red-500">*</span></label>
                    <select name="visit_type" class="form-control" required>
                        <option value="new">New Patient</option>
                        <option value="follow-up" selected>Follow-up</option>
                        <option value="emergency">Emergency</option>
                    </select>
                </div>

                <!-- Symptoms -->
                <div>
                    <label class="form-label">Symptoms</label>
                    <textarea name="symptoms" class="form-control" rows="3" placeholder="Describe the patient's symptoms..."></textarea>
                </div>

                <!-- Diagnosis -->
                <div>
                    <label class="form-label">Diagnosis</label>
                    <textarea name="diagnosis" class="form-control" rows="3" placeholder="Enter diagnosis..."></textarea>
                </div>

                <!-- Notes -->
                <div>
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes..."></textarea>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3 pt-4 border-t">
                    <button type="submit" class="btn btn-blue flex-1">
                        <i class="fas fa-save"></i> Save Visit
                    </button>
                    <button type="reset" class="btn btn-outline">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            New Visit
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($doctor_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<style>
    .max-w-2xl { max-width: 42rem; }
    .mx-auto { margin-left: auto; margin-right: auto; }
    .form-label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-primary); margin-bottom: 4px; }
    .form-control { width: 100%; padding: 10px 14px; border: 2px solid var(--border-color); border-radius: 10px; font-size: 0.9rem; background: var(--bg-card); color: var(--text-primary); outline: none; transition: all 0.3s; }
    .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12); }
    .form-control::placeholder { color: var(--text-secondary); opacity: 0.6; }
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 10px; font-weight: 600; font-size: 0.85rem; transition: all 0.3s; cursor: pointer; border: none; text-decoration: none; }
    .btn-blue { background: var(--primary); color: white; }
    .btn-blue:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
    .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
    .btn-outline:hover { background: var(--bg-body); border-color: var(--primary); color: var(--primary); }
    .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
    .flex-1 { flex: 1; }
    .text-red-500 { color: #EF4444; }
    .text-xs { font-size: 0.75rem; }
</style>

<script>
    console.log('%c👨‍⚕️ New Visit - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
</script>

</body>
</html>