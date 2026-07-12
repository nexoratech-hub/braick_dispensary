<?php
// ================================================================
// FILE: frontend/pages/doctor/my_patients.php
// DOCTOR - MY PATIENTS LIST (FIXED - REMOVED p.status)
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
    $_SESSION['profile_pic'] = '';
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
// GET SEARCH PARAMETERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// ================================================================
// GET ALL PATIENTS FOR THIS DOCTOR - FIXED: REMOVED p.status
// ================================================================
$sql = "
    SELECT DISTINCT 
        p.id,
        p.full_name,
        p.patient_id,
        p.phone,
        p.email,
        p.date_of_birth,
        p.gender,
        p.address,
        p.blood_group,
        p.allergies,
        p.emergency_contact,
        p.created_at,
        (SELECT COUNT(*) FROM visits WHERE patient_id = p.id AND doctor_id = ?) as total_visits,
        (SELECT COUNT(*) FROM visits WHERE patient_id = p.id AND doctor_id = ? AND status = 'pending') as pending_visits,
        (SELECT MAX(created_at) FROM visits WHERE patient_id = p.id AND doctor_id = ?) as last_visit_date
    FROM patients p
    JOIN visits v ON p.id = v.patient_id
    WHERE v.doctor_id = ?
";

$params = [$doctor_id, $doctor_id, $doctor_id, $doctor_id];

// Add search filter
if (!empty($search)) {
    $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY last_visit_date DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
$total_patients = count($patients);

// ================================================================
// GET BRANCH NAME
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
// FUNCTIONS
// ================================================================
function time_ago($timestamp) {
    if (empty($timestamp)) return 'N/A';
    $time = strtotime($timestamp);
    if ($time === false) return 'N/A';
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
    return date('M d, Y', $time);
}

function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
    $index = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $index = ($index + ord($name[$i])) % count($colors);
    }
    return $colors[$index];
}

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

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-injured mr-2" style="color: #0B5ED7;"></i> My Patients
            </h1>
            <p class="page-subtitle">
                View all patients assigned to you
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-users mr-1"></i> <?= $total_patients ?> patients
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Search -->
    <div class="card mb-6">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="search" class="form-control" placeholder="Search by name, ID or phone..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-blue btn-sm">
                <i class="fas fa-search"></i> Search
            </button>
            <?php if ($search): ?>
                <a href="my_patients.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Patients Grid -->
    <?php if (count($patients) > 0): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php foreach ($patients as $patient): ?>
                <div class="patient-card animate-fade-in-up">
                    <div class="patient-card-header">
                        <div class="patient-card-avatar" style="background: <?= getUserColor($patient['full_name']) ?>;">
                            <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                        </div>
                        <div class="patient-card-info">
                            <h4 class="patient-card-name"><?= htmlspecialchars($patient['full_name']) ?></h4>
                            <p class="patient-card-id">ID: <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></p>
                            <p class="patient-card-phone"><?= htmlspecialchars($patient['phone'] ?? 'No phone') ?></p>
                        </div>
                    </div>
                    <div class="patient-card-body">
                        <div class="patient-card-stats">
                            <div>
                                <span class="stat-number"><?= $patient['total_visits'] ?? 0 ?></span>
                                <span class="stat-label">Visits</span>
                            </div>
                            <div>
                                <span class="stat-number text-orange-500"><?= $patient['pending_visits'] ?? 0 ?></span>
                                <span class="stat-label">Pending</span>
                            </div>
                            <div>
                                <span class="stat-number text-green-500"><?= ($patient['total_visits'] ?? 0) - ($patient['pending_visits'] ?? 0) ?></span>
                                <span class="stat-label">Completed</span>
                            </div>
                        </div>
                        <div class="patient-card-footer">
                            <span class="last-visit">
                                <i class="far fa-clock mr-1"></i>
                                Last visit: <?= time_ago($patient['last_visit_date'] ?? '') ?>
                            </span>
                        </div>
                    </div>
                    <div class="patient-card-actions">
                        <a href="patient_details.php?id=<?= $patient['id'] ?>" class="btn btn-view btn-sm" title="View Details">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="consultation.php?patient_id=<?= $patient['id'] ?>" class="btn btn-consult btn-sm" title="Consult">
                            <i class="fas fa-stethoscope"></i> Consult
                        </a>
                        <a href="prescribe.php?patient_id=<?= $patient['id'] ?>" class="btn btn-green btn-sm" title="Prescribe">
                            <i class="fas fa-prescription"></i> Prescribe
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card text-center py-12">
            <i class="fas fa-user-injured text-4xl text-gray-300 block mb-3"></i>
            <p class="text-gray-400">
                <?php if ($search): ?>
                    No patients found matching "<strong><?= htmlspecialchars($search) ?></strong>"
                <?php else: ?>
                    No patients assigned to you yet
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            My Patients
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($doctor_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- STYLES -->
<!-- ================================================================ -->
<style>
    .patient-card {
        background: var(--bg-card);
        border-radius: 16px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        overflow: hidden;
    }
    
    .patient-card:hover {
        border-color: var(--primary);
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.12);
        transform: translateY(-4px);
    }
    
    .patient-card-header {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .patient-card-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
    }
    
    .patient-card-info {
        flex: 1;
    }
    
    .patient-card-name {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .patient-card-id {
        font-size: 0.75rem;
        color: var(--text-secondary);
        font-family: monospace;
    }
    
    .patient-card-phone {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .patient-card-body {
        padding: 14px 20px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .patient-card-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        text-align: center;
        margin-bottom: 10px;
    }
    
    .patient-card-stats .stat-number {
        display: block;
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .patient-card-stats .stat-number.text-orange-500 {
        color: #D97706;
    }
    
    .patient-card-stats .stat-number.text-green-500 {
        color: #059669;
    }
    
    .patient-card-stats .stat-label {
        display: block;
        font-size: 0.65rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .patient-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .last-visit {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .patient-card-actions {
        display: flex;
        gap: 6px;
        padding: 12px 20px;
        background: var(--bg-body);
        flex-wrap: wrap;
    }
    
    [data-theme="dark"] .patient-card-actions {
        background: #0F172A;
    }
    
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .form-control {
        width: 100%;
        padding: 8px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s;
    }
    
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }
    
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.6;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.78rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-blue {
        background: var(--primary);
        color: white;
    }
    .btn-blue:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .btn-view {
        background: var(--primary);
        color: white;
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    .btn-view:hover {
        background: var(--primary-dark);
        transform: scale(1.05);
    }
    
    .btn-consult {
        background: #7C3AED;
        color: white;
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    .btn-consult:hover {
        background: #6D28D9;
        transform: scale(1.05);
    }
    
    .btn-green {
        background: #059669;
        color: white;
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    .btn-green:hover {
        background: #047857;
        transform: scale(1.05);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
    }
    
    .btn-sm {
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    
    .min-w-[200px] { min-width: 200px; }
    .mb-6 { margin-bottom: 1.5rem; }
    .ml-2 { margin-left: 0.5rem; }
    .mr-1 { margin-right: 0.25rem; }
    .mr-2 { margin-right: 0.5rem; }
    .gap-2 { gap: 0.5rem; }
    .gap-3 { gap: 0.75rem; }
    .gap-6 { gap: 1.5rem; }
    .flex { display: flex; }
    .flex-wrap { flex-wrap: wrap; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .text-center { text-align: center; }
    .py-12 { padding-top: 3rem; padding-bottom: 3rem; }
    .text-4xl { font-size: 2.25rem; }
    .text-gray-300 { color: var(--text-muted); }
    .text-gray-400 { color: var(--text-muted); }
    
    [data-theme="dark"] .text-gray-300 { color: #64748B; }
    [data-theme="dark"] .text-gray-400 { color: #64748B; }
    
    @media (max-width: 640px) {
        .patient-card-actions {
            flex-direction: column;
        }
        .patient-card-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .min-w-[200px] { min-width: 100%; }
        .grid-cols-1 { grid-template-columns: 1fr; }
    }
</style>

</body>
</html>