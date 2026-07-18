<?php
// ================================================================
// FILE: frontend/pages/doctor/search.php
// DOCTOR - SEARCH PATIENTS
// SEARCH BY NAME, PATIENT ID, OR PHONE
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DR. JOHN MUSHI (ID: 5) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 5;
    $_SESSION['doctor_id'] = 5;
    $_SESSION['full_name'] = 'Dr. John Mushi';
    $_SESSION['username'] = 'dr.john';
    $_SESSION['email'] = 'john@braick.com';
    $_SESSION['phone'] = '+255 700 000 011';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['specialty'] = 'General Medicine';
    $_SESSION['profile_pic'] = '';
    $_SESSION['is_online'] = 1;
}

$doctor_id = $_SESSION['user_id'] ?? 5;
$doctor_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// GET SEARCH QUERY
// ================================================================
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all'; // all, pending, completed

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// SEARCH PATIENTS
// ================================================================
$results = [];
$total_results = 0;
$search_term = '';

if (!empty($query)) {
    try {
        $search_term = "%$query%";
        
        // Build query - search patients that belong to this doctor
        $sql = "
            SELECT DISTINCT 
                p.id,
                p.patient_id,
                p.full_name,
                p.phone,
                p.email,
                p.gender,
                p.date_of_birth,
                p.address,
                p.blood_group,
                p.allergies,
                p.created_at,
                (SELECT COUNT(*) FROM visits WHERE patient_id = p.id AND doctor_id = ?) as total_visits,
                (SELECT status FROM visits WHERE patient_id = p.id AND doctor_id = ? 
                 ORDER BY created_at DESC LIMIT 1) as last_status,
                (SELECT created_at FROM visits WHERE patient_id = p.id AND doctor_id = ? 
                 ORDER BY created_at DESC LIMIT 1) as last_visit
            FROM patients p
            WHERE p.id IN (
                SELECT DISTINCT patient_id FROM visits WHERE doctor_id = ?
            )
            AND (
                p.full_name LIKE ? 
                OR p.patient_id LIKE ? 
                OR p.phone LIKE ?
                OR p.email LIKE ?
            )
        ";
        
        $params = [
            $doctor_id, // for total_visits
            $doctor_id, // for last_status
            $doctor_id, // for last_visit
            $doctor_id, // for subquery
            $search_term,
            $search_term,
            $search_term,
            $search_term
        ];
        
        // Add filter
        if ($filter === 'pending') {
            $sql .= " AND p.id IN (
                SELECT DISTINCT patient_id FROM visits 
                WHERE doctor_id = ? AND status IN ('pending', 'assigned', 'with_doctor')
            )";
            $params[] = $doctor_id;
        } elseif ($filter === 'completed') {
            $sql .= " AND p.id IN (
                SELECT DISTINCT patient_id FROM visits 
                WHERE doctor_id = ? AND status = 'completed'
            )";
            $params[] = $doctor_id;
        }
        
        $sql .= " ORDER BY p.full_name ASC LIMIT 50";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_results = count($results);
        
    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
        $results = [];
        $total_results = 0;
    }
}

// ================================================================
// GET STATS
// ================================================================
try {
    // Total patients for this doctor
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM visits WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Pending patients
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM visits WHERE doctor_id = ? AND status IN ('pending', 'assigned', 'with_doctor')");
    $stmt->execute([$doctor_id]);
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Completed patients
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM visits WHERE doctor_id = ? AND status = 'completed'");
    $stmt->execute([$doctor_id]);
    $completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
} catch (Exception $e) {
    $total_patients = 0;
    $pending_count = 0;
    $completed_count = 0;
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

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">
                <i class="fas fa-search"></i> Search Patients
                <span class="page-badge"><?= $total_patients ?> total</span>
            </h1>
            <p class="page-subtitle">
                Search for patients by name, ID, phone or email
                <?php if (!empty($query)): ?>
                    <span class="search-result-badge">
                        <i class="fas fa-search"></i> Results for: "<strong><?= htmlspecialchars($query) ?></strong>"
                    </span>
                    <span class="search-result-count">
                        <?= $total_results ?> patient(s) found
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="page-header-right">
            <a href="dashboard.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="my_patients.php" class="btn btn-primary">
                <i class="fas fa-users"></i> My Patients
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SEARCH FORM -->
    <!-- ================================================================ -->
    <div class="search-card">
        <form method="GET" action="" class="search-form">
            <div class="search-input-group">
                <div class="search-input-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="q" class="search-input" 
                           placeholder="Search by patient name, ID, phone or email..." 
                           value="<?= htmlspecialchars($query) ?>"
                           autofocus>
                    <button type="submit" class="search-submit-btn">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
            <div class="search-filters">
                <a href="?q=<?= urlencode($query) ?>&filter=all" 
                   class="filter-chip <?= $filter === 'all' ? 'active' : '' ?>">
                    All
                </a>
                <a href="?q=<?= urlencode($query) ?>&filter=pending" 
                   class="filter-chip <?= $filter === 'pending' ? 'active' : '' ?>">
                    <i class="fas fa-clock"></i> Pending
                </a>
                <a href="?q=<?= urlencode($query) ?>&filter=completed" 
                   class="filter-chip <?= $filter === 'completed' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i> Completed
                </a>
                <?php if (!empty($query)): ?>
                    <a href="search.php" class="filter-chip clear-btn">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-card stat-card-blue">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-users"></i></div>
                <div class="stat-card-info">
                    <span class="stat-card-label">Total Patients</span>
                    <span class="stat-card-number"><?= $total_patients ?></span>
                </div>
            </div>
        </div>
        <div class="stat-card stat-card-orange">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-card-info">
                    <span class="stat-card-label">Pending</span>
                    <span class="stat-card-number"><?= $pending_count ?></span>
                </div>
            </div>
        </div>
        <div class="stat-card stat-card-green">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-card-info">
                    <span class="stat-card-label">Completed</span>
                    <span class="stat-card-number"><?= $completed_count ?></span>
                </div>
            </div>
        </div>
        <div class="stat-card stat-card-purple">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-search"></i></div>
                <div class="stat-card-info">
                    <span class="stat-card-label">Search Results</span>
                    <span class="stat-card-number"><?= $total_results ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SEARCH RESULTS -->
    <!-- ================================================================ -->
    <?php if (!empty($query)): ?>
        
        <?php if ($total_results > 0): ?>
            <div class="results-section">
                <div class="results-header">
                    <h3 class="results-title">
                        <i class="fas fa-list"></i> Results
                        <span class="results-count"><?= $total_results ?> patient(s)</span>
                    </h3>
                </div>
                
                <div class="results-grid">
                    <?php foreach ($results as $patient): ?>
                        <div class="result-card">
                            <div class="result-card-header">
                                <div class="patient-avatar" style="background: <?= '#' . substr(md5($patient['full_name']), 0, 6) ?>;">
                                    <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                                </div>
                                <div class="result-card-info">
                                    <h4 class="result-name"><?= htmlspecialchars($patient['full_name']) ?></h4>
                                    <span class="result-id"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span>
                                </div>
                                <span class="status-badge status-<?= $patient['last_status'] ?? 'pending' ?>">
                                    <?= ucfirst($patient['last_status'] ?? 'Pending') ?>
                                </span>
                            </div>
                            <div class="result-card-body">
                                <div class="result-details">
                                    <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                                    <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
                                    <span><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                                    <span><i class="fas fa-calendar-alt"></i> Visits: <strong><?= $patient['total_visits'] ?? 0 ?></strong></span>
                                    <?php if (!empty($patient['last_visit'])): ?>
                                        <span><i class="fas fa-clock"></i> Last: <?= date('M d, Y', strtotime($patient['last_visit'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="result-card-actions">
                                <a href="consultation.php?patient_id=<?= $patient['id'] ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-stethoscope"></i> Consult
                                </a>
                                <a href="view_patient.php?id=<?= $patient['id'] ?>" class="btn btn-outline btn-sm">
                                    <i class="fas fa-user"></i> Profile
                                </a>
                                <a href="appointment.php?patient_id=<?= $patient['id'] ?>" class="btn btn-outline btn-sm">
                                    <i class="fas fa-calendar-plus"></i> Appointment
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state large">
                <i class="fas fa-search"></i>
                <h3>No Results Found</h3>
                <p>No patients matching "<strong><?= htmlspecialchars($query) ?></strong>"</p>
                <p class="text-sm text-gray-400">Try searching by name, patient ID, phone number, or email</p>
                <div class="empty-actions">
                    <a href="my_patients.php" class="btn btn-primary">
                        <i class="fas fa-users"></i> View All Patients
                    </a>
                    <a href="search.php" class="btn btn-outline">
                        <i class="fas fa-search"></i> New Search
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- Empty state when no search query -->
        <div class="empty-state large">
            <i class="fas fa-search"></i>
            <h3>Search for Patients</h3>
            <p>Enter a patient name, ID, phone number, or email above to search</p>
            <p class="text-sm text-gray-400">You can search through all patients you have treated</p>
            <div class="empty-actions">
                <a href="my_patients.php" class="btn btn-primary">
                    <i class="fas fa-users"></i> View All Patients
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="separator">|</span>
            Search Patients
            <span class="separator">|</span>
            <span id="footerTimestamp"><?= date('H:i:s') ?></span>
            <span class="separator">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- STYLES -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       SEARCH PAGE STYLES
       ================================================================ */
    
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 24px 28px;
        min-height: calc(100vh - 68px);
        background: var(--bg-body);
        color: var(--text-primary);
        transition: all 0.3s ease;
    }
    
    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 3px solid var(--primary);
    }
    
    .page-header-left { flex: 1; }
    
    .page-title {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .page-title i { color: var(--primary); }
    
    .page-badge {
        font-size: 0.7rem;
        font-weight: 600;
        background: var(--primary-bg);
        color: var(--primary);
        padding: 2px 14px;
        border-radius: 20px;
    }
    
    .page-subtitle {
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin-top: 4px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }
    
    .search-result-badge {
        background: var(--primary-bg);
        color: var(--primary);
        padding: 2px 14px;
        border-radius: 20px;
        font-size: 0.8rem;
    }
    
    .search-result-count {
        background: #D1FAE5;
        color: #059669;
        padding: 2px 14px;
        border-radius: 20px;
        font-size: 0.8rem;
    }
    
    [data-theme="dark"] .search-result-badge {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    [data-theme="dark"] .search-result-count {
        background: #1A3A2A;
        color: #34D399;
    }
    
    .page-header-right {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    /* ================================================================
       SEARCH CARD
       ================================================================ */
    .search-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px;
        border: 1px solid var(--border-color);
        margin-bottom: 24px;
        transition: all 0.3s ease;
    }
    
    .search-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }
    
    .search-form {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    
    .search-input-group {
        display: flex;
        justify-content: center;
    }
    
    .search-input-wrapper {
        position: relative;
        width: 100%;
        max-width: 700px;
    }
    
    .search-icon {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
        font-size: 1rem;
    }
    
    .search-input {
        width: 100%;
        padding: 14px 50px 14px 46px;
        border: 2px solid var(--border-color);
        border-radius: 14px;
        font-size: 1rem;
        background: var(--bg-body);
        color: var(--text-primary);
        transition: all 0.3s ease;
        outline: none;
    }
    
    .search-input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
    }
    
    .search-input::placeholder {
        color: var(--text-secondary);
        opacity: 0.6;
    }
    
    .search-submit-btn {
        position: absolute;
        right: 4px;
        top: 50%;
        transform: translateY(-50%);
        background: var(--primary);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 10px;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .search-submit-btn:hover {
        background: var(--primary-dark);
        transform: translateY(-50%) scale(1.05);
    }
    
    /* ================================================================
       FILTER CHIPS
       ================================================================ */
    .search-filters {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .filter-chip {
        padding: 6px 16px;
        border-radius: 20px;
        border: 2px solid var(--border-color);
        background: transparent;
        color: var(--text-secondary);
        font-size: 0.8rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
    }
    
    .filter-chip:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .filter-chip.active {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }
    
    .filter-chip.clear-btn {
        border-color: var(--danger);
        color: var(--danger);
    }
    
    .filter-chip.clear-btn:hover {
        background: var(--danger);
        color: white;
    }
    
    /* ================================================================
       STATS GRID
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }
    
    .stat-card-inner {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    
    .stat-card-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: white;
        flex-shrink: 0;
    }
    
    .stat-card-blue .stat-card-icon { background: linear-gradient(135deg, #0B5ED7, #1A73E8); }
    .stat-card-orange .stat-card-icon { background: linear-gradient(135deg, #D97706, #F59E0B); }
    .stat-card-green .stat-card-icon { background: linear-gradient(135deg, #059669, #34D399); }
    .stat-card-purple .stat-card-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
    
    .stat-card-info {
        display: flex;
        flex-direction: column;
        flex: 1;
    }
    
    .stat-card-label {
        font-size: 0.7rem;
        font-weight: 500;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    
    .stat-card-number {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.2;
    }
    
    /* ================================================================
       RESULTS SECTION
       ================================================================ */
    .results-section {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 1px solid var(--border-color);
    }
    
    .results-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--border-color);
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .results-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .results-title i { color: var(--primary); }
    
    .results-count {
        font-size: 0.8rem;
        font-weight: 400;
        color: var(--text-secondary);
        background: var(--bg-body);
        padding: 2px 14px;
        border-radius: 20px;
    }
    
    .results-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 16px;
    }
    
    /* ================================================================
       RESULT CARD
       ================================================================ */
    .result-card {
        background: var(--bg-body);
        border-radius: 14px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .result-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }
    
    .result-card-header {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 12px;
    }
    
    .patient-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
    }
    
    .result-card-info {
        flex: 1;
    }
    
    .result-name {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }
    
    .result-id {
        font-size: 0.8rem;
        color: var(--text-secondary);
        font-family: monospace;
    }
    
    .status-badge {
        display: inline-block;
        font-size: 0.65rem;
        font-weight: 600;
        padding: 2px 14px;
        border-radius: 20px;
        white-space: nowrap;
    }
    
    .status-pending { background: #FEF3C7; color: #D97706; }
    .status-assigned { background: #E8F0FE; color: #0B5ED7; }
    .status-with_doctor { background: #E8F0FE; color: #0B5ED7; }
    .status-completed { background: #D1FAE5; color: #059669; }
    .status-cancelled { background: #FEE2E2; color: #DC2626; }
    
    [data-theme="dark"] .status-pending { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-assigned { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-with_doctor { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-completed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-cancelled { background: #3A1A1A; color: #F87171; }
    
    .result-card-body {
        margin-bottom: 12px;
    }
    
    .result-details {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .result-details i {
        width: 16px;
        color: var(--text-secondary);
    }
    
    .result-details strong {
        color: var(--text-primary);
    }
    
    .result-card-actions {
        display: flex;
        gap: 8px;
        padding-top: 12px;
        border-top: 1px solid var(--border-color);
        flex-wrap: wrap;
    }
    
    .result-card-actions .btn {
        flex: 1;
        justify-content: center;
        min-width: 80px;
    }
    
    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-primary {
        background: var(--primary);
        color: white;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
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
    }
    
    .btn-sm { padding: 4px 12px; font-size: 0.7rem; border-radius: 6px; }
    
    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 3rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 12px;
    }
    
    .empty-state h3 {
        font-size: 1.2rem;
        color: var(--text-primary);
        margin: 0 0 4px 0;
    }
    
    .empty-state p {
        margin: 2px 0;
        font-size: 0.9rem;
    }
    
    .empty-state.large { padding: 60px 20px; }
    .empty-state.large i { font-size: 4rem; }
    
    .empty-actions {
        display: flex;
        gap: 12px;
        justify-content: center;
        margin-top: 16px;
        flex-wrap: wrap;
    }
    
    .text-sm { font-size: 0.85rem; }
    .text-gray-400 { color: var(--text-secondary); }
    
    /* ================================================================
       FOOTER
       ================================================================ */
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: var(--primary); font-weight: 600; }
    .separator { color: var(--border-color); margin: 0 4px; }
    
    /* ================================================================
       TOAST
       ================================================================ */
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 12px 18px;
        border-radius: 12px;
        z-index: 999;
        max-width: 360px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    .toast-custom.show { transform: translateY(0); opacity: 1; }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #EF4444; }
    .toast-custom.info { background: var(--primary); }
    .toast-custom.warning { background: #D97706; }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1200px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .results-grid { grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
    }
    
    @media (max-width: 1024px) {
        .main-content { padding: 20px; }
    }
    
    @media (max-width: 768px) {
        .main-content { padding: 16px; margin-left: 0; }
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .results-grid { grid-template-columns: 1fr; }
        .page-title { font-size: 1.2rem; }
        .page-header { flex-direction: column; }
        .page-header-right { width: 100%; }
        .page-header-right .btn { flex: 1; justify-content: center; }
        .search-input { font-size: 0.9rem; padding: 12px 40px 12px 40px; }
        .search-submit-btn { padding: 6px 12px; font-size: 0.8rem; }
        .result-card-actions { flex-direction: column; }
        .result-card-actions .btn { width: 100%; }
        .empty-actions { flex-direction: column; }
        .empty-actions .btn { width: 100%; }
        .search-filters { justify-content: center; }
        .search-card { padding: 16px; }
        .results-section { padding: 16px; }
    }
    
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
        .result-details { flex-direction: column; gap: 4px; }
        .result-card-header { flex-wrap: wrap; }
    }
    
    /* Dark theme overrides */
    [data-theme="dark"] .result-card { background: #1E293B; border-color: #334155; }
    [data-theme="dark"] .result-card:hover { border-color: var(--primary); }
    [data-theme="dark"] .search-card { background: #1E293B; border-color: #334155; }
    [data-theme="dark"] .search-input { background: #0F172A; border-color: #334155; }
    [data-theme="dark"] .search-input:focus { border-color: var(--primary); }
    [data-theme="dark"] .results-section { background: #1E293B; border-color: #334155; }
    [data-theme="dark"] .stat-card { background: #1E293B; border-color: #334155; }
    [data-theme="dark"] .stat-card:hover { border-color: var(--primary); }
    [data-theme="dark"] .filter-chip { border-color: #334155; }
    [data-theme="dark"] .filter-chip:hover { border-color: var(--primary); }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // SEARCH WITH ENTER KEY (already handled by form)
    // ================================================================
    
    // Auto-focus search input
    var searchInput = document.querySelector('.search-input');
    if (searchInput && !searchInput.value) {
        searchInput.focus();
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 5000);
    }

    // ================================================================
    // DARK MODE
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    console.log('%c🔍 Search Patients - Doctor Panel', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    <?php if (!empty($query)): ?>
        console.log('%c📊 Search Results: <?= $total_results ?> patient(s) found for "<?= addslashes($query) ?>"', 'font-size:12px; color:#059669;');
    <?php else: ?>
        console.log('%c💡 Enter a search term to find patients', 'font-size:12px; color:#64748B;');
    <?php endif; ?>
</script>

<!-- ================================================================ -->
<!-- DOCTOR STATS AUTO-UPDATE -->
<!-- ================================================================ -->
<script src="/dispensary_system/frontend/assets/js/doctor_global_stats.js"></script>

</body>
</html>