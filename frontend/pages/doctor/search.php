<?php
// ================================================================
// FILE: frontend/pages/doctor/search.php
// GLOBAL SEARCH RESULTS PAGE
// BRAICK DISPENSARY
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure doctor session
if (!isset($_SESSION['doctor_id'])) {
    $_SESSION['doctor_id'] = 1;
    $_SESSION['full_name'] = 'Dr. Sarah Mwamba';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['specialty'] = 'Cardiology';
}

// Get search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$module = isset($_GET['module']) ? $_GET['module'] : 'doctor';

// Include header
include_once '../../components/doctor_header.php';
include_once '../../components/doctor_sidebar.php';

// ================================================================
// SIMULATED SEARCH RESULTS - Replace with database queries
// ================================================================
function getSearchResults($query) {
    if (empty($query)) {
        return ['patients' => [], 'doctors' => [], 'prescriptions' => [], 'appointments' => []];
    }
    
    $q = strtolower($query);
    
    // Simulated data
    $all_patients = [
        ['id' => 1, 'name' => 'John Doe', 'phone' => '0755 123 456', 'patient_id' => 'P-2024-001', 'last_visit' => '2024-01-15'],
        ['id' => 2, 'name' => 'Mary Jane', 'phone' => '0755 234 567', 'patient_id' => 'P-2024-002', 'last_visit' => '2024-01-14'],
        ['id' => 3, 'name' => 'James Kijana', 'phone' => '0755 345 678', 'patient_id' => 'P-2024-003', 'last_visit' => '2024-01-13'],
        ['id' => 4, 'name' => 'Sarah Mwamba', 'phone' => '0755 456 789', 'patient_id' => 'P-2024-004', 'last_visit' => '2024-01-12'],
        ['id' => 5, 'name' => 'Peter Mushi', 'phone' => '0755 567 890', 'patient_id' => 'P-2024-005', 'last_visit' => '2024-01-11'],
        ['id' => 6, 'name' => 'Grace Ngalula', 'phone' => '0755 678 901', 'patient_id' => 'P-2024-006', 'last_visit' => '2024-01-10'],
        ['id' => 7, 'name' => 'David Mwangi', 'phone' => '0755 789 012', 'patient_id' => 'P-2024-007', 'last_visit' => '2024-01-09'],
        ['id' => 8, 'name' => 'Alice Mwamba', 'phone' => '0755 890 123', 'patient_id' => 'P-2024-008', 'last_visit' => '2024-01-08'],
    ];
    
    $all_doctors = [
        ['id' => 1, 'name' => 'Dr. Sarah Mwamba', 'specialty' => 'Cardiology', 'status' => 'online'],
        ['id' => 2, 'name' => 'Dr. James Kijana', 'specialty' => 'Pediatrics', 'status' => 'online'],
        ['id' => 3, 'name' => 'Dr. Mary Ngalula', 'specialty' => 'Gynecology', 'status' => 'offline'],
        ['id' => 4, 'name' => 'Dr. Peter Mushi', 'specialty' => 'Orthopedics', 'status' => 'online'],
    ];
    
    $all_prescriptions = [
        ['id' => 1, 'patient_name' => 'John Doe', 'medication' => 'Paracetamol 500mg', 'status' => 'dispensed'],
        ['id' => 2, 'patient_name' => 'Mary Jane', 'medication' => 'Amoxicillin 250mg', 'status' => 'pending'],
        ['id' => 3, 'patient_name' => 'James Kijana', 'medication' => 'Metformin 850mg', 'status' => 'dispensed'],
        ['id' => 4, 'patient_name' => 'Sarah Mwamba', 'medication' => 'Ciprofloxacin 500mg', 'status' => 'pending'],
    ];
    
    $all_appointments = [
        ['id' => 1, 'patient_name' => 'John Doe', 'date' => '2024-01-15 10:00', 'status' => 'confirmed'],
        ['id' => 2, 'patient_name' => 'Mary Jane', 'date' => '2024-01-15 11:30', 'status' => 'scheduled'],
        ['id' => 3, 'patient_name' => 'James Kijana', 'date' => '2024-01-16 09:00', 'status' => 'pending'],
    ];
    
    $results = [
        'patients' => [],
        'doctors' => [],
        'prescriptions' => [],
        'appointments' => []
    ];
    
    // Filter patients
    foreach ($all_patients as $p) {
        if (stripos($p['name'], $q) !== false || 
            stripos($p['patient_id'], $q) !== false || 
            stripos($p['phone'], $q) !== false) {
            $results['patients'][] = $p;
        }
    }
    
    // Filter doctors
    foreach ($all_doctors as $d) {
        if (stripos($d['name'], $q) !== false || 
            stripos($d['specialty'], $q) !== false) {
            $results['doctors'][] = $d;
        }
    }
    
    // Filter prescriptions
    foreach ($all_prescriptions as $p) {
        if (stripos($p['patient_name'], $q) !== false || 
            stripos($p['medication'], $q) !== false) {
            $results['prescriptions'][] = $p;
        }
    }
    
    // Filter appointments
    foreach ($all_appointments as $a) {
        if (stripos($a['patient_name'], $q) !== false) {
            $results['appointments'][] = $a;
        }
    }
    
    return $results;
}

function highlightText($text, $query) {
    if (empty($query)) return $text;
    $q = preg_quote($query, '/');
    return preg_replace('/(' . $q . ')/i', '<span class="highlight">$1</span>', $text);
}

$results = getSearchResults($query);
$total = count($results['patients']) + count($results['doctors']) + 
         count($results['prescriptions']) + count($results['appointments']);
?>
<style>
    .result-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 16px;
    }
    
    .result-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .result-card .section-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 10px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .result-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 10px 14px;
        border-radius: 10px;
        transition: all 0.2s ease;
        border-bottom: 1px solid var(--border-color);
        text-decoration: none;
        color: var(--text-primary);
    }
    
    .result-item:hover {
        background: var(--primary-bg);
    }
    
    [data-theme="dark"] .result-item:hover {
        background: #1E3A5F;
    }
    
    .result-item:last-child {
        border-bottom: none;
    }
    
    .result-item .icon-box {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
        color: white;
    }
    
    .result-item .icon-box.blue { background: var(--primary); }
    .result-item .icon-box.green { background: var(--green); }
    .result-item .icon-box.purple { background: var(--purple); }
    .result-item .icon-box.orange { background: var(--orange); }
    
    .result-item .info {
        flex: 1;
    }
    
    .result-item .info .title {
        font-weight: 600;
        font-size: 0.95rem;
        color: var(--text-primary);
    }
    
    .result-item .info .sub {
        font-size: 0.78rem;
        color: var(--text-secondary);
    }
    
    .result-item .badge {
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        background: var(--bg-body);
        color: var(--text-secondary);
    }
    
    .result-item .badge.online { background: #ECFDF5; color: var(--green); }
    .result-item .badge.offline { background: #FEE2E2; color: var(--red); }
    
    [data-theme="dark"] .result-item .badge.online { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .result-item .badge.offline { background: #3A1A1A; color: #F87171; }
    
    .highlight {
        background: #FEF08A;
        padding: 0 4px;
        border-radius: 3px;
        font-weight: 700;
    }
    
    [data-theme="dark"] .highlight {
        background: #F59E0B;
        color: #0F172A;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-muted);
    }
    
    .empty-state i {
        font-size: 3rem;
        display: block;
        margin-bottom: 12px;
        color: var(--border-color);
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-search mr-2" style="color: var(--primary);"></i> 
                Search Results
            </h1>
            <p class="page-subtitle">
                <?php if (!empty($query)): ?>
                    Found <strong><?= $total ?></strong> result(s) for 
                    "<strong><?= htmlspecialchars($query) ?></strong>"
                    <span class="branch-tag ml-2">
                        <i class="fas fa-filter"></i> Doctor Module
                    </span>
                <?php else: ?>
                    Enter a search term to find patients, doctors, prescriptions, or appointments
                <?php endif; ?>
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <?php if (!empty($query)): ?>
        
        <!-- Search Summary -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
            <div class="bg-card p-4 rounded-xl border-2 border-border text-center">
                <p class="text-2xl font-bold text-primary"><?= count($results['patients']) ?></p>
                <p class="text-sm text-secondary"><i class="fas fa-user-injured mr-1"></i> Patients</p>
            </div>
            <div class="bg-card p-4 rounded-xl border-2 border-border text-center">
                <p class="text-2xl font-bold text-green"><?= count($results['doctors']) ?></p>
                <p class="text-sm text-secondary"><i class="fas fa-user-md mr-1"></i> Doctors</p>
            </div>
            <div class="bg-card p-4 rounded-xl border-2 border-border text-center">
                <p class="text-2xl font-bold text-purple"><?= count($results['prescriptions']) ?></p>
                <p class="text-sm text-secondary"><i class="fas fa-prescription mr-1"></i> Prescriptions</p>
            </div>
            <div class="bg-card p-4 rounded-xl border-2 border-border text-center">
                <p class="text-2xl font-bold text-orange"><?= count($results['appointments']) ?></p>
                <p class="text-sm text-secondary"><i class="fas fa-calendar-check mr-1"></i> Appointments</p>
            </div>
        </div>
        
        <?php if ($total > 0): ?>
            
            <!-- ================================================================ -->
            <!-- PATIENTS RESULTS -->
            <!-- ================================================================ -->
            <?php if (count($results['patients']) > 0): ?>
            <div class="result-card">
                <div class="section-title">
                    <i class="fas fa-user-injured"></i> Patients (<?= count($results['patients']) ?>)
                </div>
                <?php foreach ($results['patients'] as $patient): ?>
                    <a href="patient_details.php?id=<?= $patient['id'] ?>&search=<?= urlencode($query) ?>" class="result-item">
                        <div class="icon-box blue"><i class="fas fa-user"></i></div>
                        <div class="info">
                            <div class="title"><?= highlightText($patient['name'], $query) ?></div>
                            <div class="sub">
                                <?= highlightText($patient['patient_id'], $query) ?> • 
                                <?= highlightText($patient['phone'], $query) ?> • 
                                Last visit: <?= $patient['last_visit'] ?>
                            </div>
                        </div>
                        <span class="badge">Patient</span>
                        <i class="fas fa-chevron-right text-text-muted"></i>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- DOCTORS RESULTS -->
            <!-- ================================================================ -->
            <?php if (count($results['doctors']) > 0): ?>
            <div class="result-card">
                <div class="section-title">
                    <i class="fas fa-user-md"></i> Doctors (<?= count($results['doctors']) ?>)
                </div>
                <?php foreach ($results['doctors'] as $doctor): ?>
                    <a href="doctor_profile.php?id=<?= $doctor['id'] ?>&search=<?= urlencode($query) ?>" class="result-item">
                        <div class="icon-box green"><i class="fas fa-user-md"></i></div>
                        <div class="info">
                            <div class="title"><?= highlightText($doctor['name'], $query) ?></div>
                            <div class="sub"><?= highlightText($doctor['specialty'], $query) ?></div>
                        </div>
                        <span class="badge <?= $doctor['status'] ?>">
                            <?= $doctor['status'] === 'online' ? '🟢 Online' : '🔴 Offline' ?>
                        </span>
                        <i class="fas fa-chevron-right text-text-muted"></i>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- PRESCRIPTIONS RESULTS -->
            <!-- ================================================================ -->
            <?php if (count($results['prescriptions']) > 0): ?>
            <div class="result-card">
                <div class="section-title">
                    <i class="fas fa-prescription"></i> Prescriptions (<?= count($results['prescriptions']) ?>)
                </div>
                <?php foreach ($results['prescriptions'] as $prescription): ?>
                    <a href="view_prescriptions.php?search=<?= urlencode($query) ?>" class="result-item">
                        <div class="icon-box purple"><i class="fas fa-prescription"></i></div>
                        <div class="info">
                            <div class="title"><?= highlightText($prescription['patient_name'], $query) ?></div>
                            <div class="sub">
                                <?= highlightText($prescription['medication'], $query) ?> • 
                                Status: <?= ucfirst($prescription['status']) ?>
                            </div>
                        </div>
                        <span class="badge">Prescription</span>
                        <i class="fas fa-chevron-right text-text-muted"></i>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- APPOINTMENTS RESULTS -->
            <!-- ================================================================ -->
            <?php if (count($results['appointments']) > 0): ?>
            <div class="result-card">
                <div class="section-title">
                    <i class="fas fa-calendar-check"></i> Appointments (<?= count($results['appointments']) ?>)
                </div>
                <?php foreach ($results['appointments'] as $appointment): ?>
                    <a href="appointments.php?search=<?= urlencode($query) ?>" class="result-item">
                        <div class="icon-box orange"><i class="fas fa-calendar-check"></i></div>
                        <div class="info">
                            <div class="title"><?= highlightText($appointment['patient_name'], $query) ?></div>
                            <div class="sub"><?= $appointment['date'] ?> • Status: <?= ucfirst($appointment['status']) ?></div>
                        </div>
                        <span class="badge">Appointment</span>
                        <i class="fas fa-chevron-right text-text-muted"></i>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- No Results -->
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3 class="text-lg font-semibold text-text-primary">No results found</h3>
                <p class="text-sm text-text-secondary">
                    We couldn't find any matches for "<strong><?= htmlspecialchars($query) ?></strong>"
                </p>
                <p class="text-xs text-text-muted mt-2">
                    Try searching with different keywords or check your spelling
                </p>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- Empty Search -->
        <div class="empty-state">
            <i class="fas fa-search"></i>
            <h3 class="text-lg font-semibold text-text-primary">Search for anything</h3>
            <p class="text-sm text-text-secondary">
                Enter a patient name, doctor name, medication, or appointment date
            </p>
            <p class="text-xs text-text-muted mt-2">
                Tip: Press <kbd>Ctrl</kbd> + <kbd>K</kbd> to focus the search bar
            </p>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Search Results
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

</body>
</html>