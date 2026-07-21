<?php
// ================================================================
// FILE: frontend/pages/doctor/view_patient.php
// DOCTOR - VIEW PATIENT COMPLETE HISTORY
// WITH FULL VISIT DETAILS (Symptoms, Diagnosis, Treatment, etc.)
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
// GET PATIENT ID
// ================================================================
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id <= 0) {
    header('Location: my_patients.php');
    exit;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET PATIENT DETAILS
// ================================================================
$patient = null;
try {
    $stmt = $db->prepare("
        SELECT p.*, 
               b.name as branch_name,
               u.full_name as doctor_name,
               u.specialty as doctor_specialty,
               (SELECT COUNT(*) FROM visits WHERE patient_id = p.id) as total_visits,
               (SELECT COUNT(*) FROM prescriptions WHERE patient_id = p.id) as total_prescriptions,
               (SELECT COUNT(*) FROM lab_tests WHERE patient_id = p.id) as total_lab_tests,
               (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id) as total_appointments
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN users u ON p.assigned_doctor_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        header('Location: my_patients.php?error=patient_not_found');
        exit;
    }
} catch (Exception $e) {
    header('Location: my_patients.php?error=database');
    exit;
}

// ================================================================
// GET ALL VISITS WITH FULL DETAILS
// ================================================================
$visits = [];
try {
    $stmt = $db->prepare("
        SELECT v.*, 
               u.full_name as doctor_name,
               u.specialty as doctor_specialty,
               (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id) as prescriptions_count,
               (SELECT COUNT(*) FROM lab_tests WHERE visit_id = v.id) as lab_tests_count
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ?
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $visits = [];
}

// ================================================================
// GET VISIT ITEMS (Prescriptions, Lab Tests, Procedures per visit)
// ================================================================
$visit_items = [];
foreach ($visits as $visit) {
    $visit_id = $visit['id'];
    
    // Get prescriptions for this visit
    $stmt = $db->prepare("
        SELECT p.*, GROUP_CONCAT(DISTINCT pi.medication_name SEPARATOR ', ') as medications_list
        FROM prescriptions p
        LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
        WHERE p.visit_id = ?
        GROUP BY p.id
    ");
    $stmt->execute([$visit_id]);
    $visit_items[$visit_id]['prescriptions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get lab tests for this visit
    $stmt = $db->prepare("
        SELECT * FROM lab_tests WHERE visit_id = ?
    ");
    $stmt->execute([$visit_id]);
    $visit_items[$visit_id]['lab_tests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get procedures for this visit (from bill_items linked via patient_bills)
    $stmt = $db->prepare("
        SELECT bi.* 
        FROM bill_items bi
        JOIN patient_bills pb ON bi.bill_id = pb.id
        WHERE pb.visit_id = ? 
        AND (
            bi.item_type = 'procedure' 
            OR bi.item_type = '' 
            OR bi.item_name LIKE '%Biopsy%'
            OR bi.item_name LIKE '%Casting%'
            OR bi.item_name LIKE '%Cauterization%'
            OR bi.item_name LIKE '%Wound Dressing%'
            OR bi.item_name LIKE '%Injection%'
            OR bi.item_name LIKE '%Suturing%'
            OR bi.item_name LIKE '%POP%'
            OR bi.item_name LIKE '%Incision%'
            OR bi.item_name LIKE '%Catheterization%'
            OR bi.item_name LIKE '%Chest Tube%'
            OR bi.item_name LIKE '%Circumcision%'
            OR bi.item_name LIKE '%Skin Grafting%'
            OR bi.item_name LIKE '%Joint Aspiration%'
            OR bi.item_name LIKE '%Lumbar Puncture%'
            OR bi.item_name LIKE '%Paracentesis%'
            OR bi.item_name LIKE '%Thoracentesis%'
        )
        ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $visit_items[$visit_id]['procedures'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get medications for this visit (from bill_items)
    $stmt = $db->prepare("
        SELECT bi.* 
        FROM bill_items bi
        JOIN patient_bills pb ON bi.bill_id = pb.id
        WHERE pb.visit_id = ? AND bi.item_type = 'medication'
        ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $visit_items[$visit_id]['medications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// GET ALL PRESCRIPTIONS
// ================================================================
$prescriptions = [];
try {
    $stmt = $db->prepare("
        SELECT p.*, 
               u.full_name as doctor_name,
               GROUP_CONCAT(DISTINCT pi.medication_name SEPARATOR ', ') as medications_list,
               COUNT(pi.id) as medications_count
        FROM prescriptions p
        LEFT JOIN users u ON p.doctor_id = u.id
        LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
        WHERE p.patient_id = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT 30
    ");
    $stmt->execute([$patient_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $prescriptions = [];
}

// ================================================================
// GET ALL LAB TESTS
// ================================================================
$lab_tests = [];
try {
    $stmt = $db->prepare("
        SELECT lt.*, 
               u.full_name as doctor_name,
               lab.full_name as lab_technician_name
        FROM lab_tests lt
        LEFT JOIN users u ON lt.doctor_id = u.id
        LEFT JOIN users lab ON lt.lab_technician_id = lab.id
        WHERE lt.visit_id IN (SELECT id FROM visits WHERE patient_id = ?)
        ORDER BY lt.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lab_tests = [];
}

// ================================================================
// GET ALL APPOINTMENTS
// ================================================================
$appointments = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, 
               u.full_name as doctor_name,
               u.specialty as doctor_specialty
        FROM appointments a
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC
    ");
    $stmt->execute([$patient_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $appointments = [];
}

// ================================================================
// GET ALL BILLS
// ================================================================
$bills = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM patient_bills 
        WHERE patient_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bills = [];
}

// ================================================================
// GET ALL PROCEDURES
// ================================================================
$procedures = [];
try {
    $stmt = $db->prepare("
        SELECT 
            bi.id,
            bi.bill_id,
            bi.item_type,
            bi.item_name,
            bi.quantity,
            bi.unit_price,
            bi.total_price,
            bi.payment_status,
            bi.status,
            bi.created_at,
            pb.patient_id,
            pb.bill_number,
            u.full_name as doctor_name
        FROM bill_items bi
        JOIN patient_bills pb ON bi.bill_id = pb.id
        LEFT JOIN users u ON pb.created_by = u.id
        WHERE pb.patient_id = ? 
        AND (
            bi.item_type = 'procedure' 
            OR bi.item_type = '' 
            OR bi.item_name LIKE '%Biopsy%'
            OR bi.item_name LIKE '%Casting%'
            OR bi.item_name LIKE '%Cauterization%'
            OR bi.item_name LIKE '%Wound Dressing%'
            OR bi.item_name LIKE '%Injection%'
            OR bi.item_name LIKE '%Suturing%'
            OR bi.item_name LIKE '%POP%'
            OR bi.item_name LIKE '%Incision%'
            OR bi.item_name LIKE '%Catheterization%'
            OR bi.item_name LIKE '%Chest Tube%'
            OR bi.item_name LIKE '%Circumcision%'
            OR bi.item_name LIKE '%Skin Grafting%'
            OR bi.item_name LIKE '%Joint Aspiration%'
            OR bi.item_name LIKE '%Lumbar Puncture%'
            OR bi.item_name LIKE '%Paracentesis%'
            OR bi.item_name LIKE '%Thoracentesis%'
        )
        ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $procedures = [];
}

// ================================================================
// GET ALL CONSULTATIONS
// ================================================================
$consultations = [];
try {
    $stmt = $db->prepare("
        SELECT v.id, v.visit_number, v.created_at, v.diagnosis, v.treatment, v.symptoms, v.notes,
               u.full_name as doctor_name,
               (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id) as prescriptions_count,
               (SELECT COUNT(*) FROM lab_tests WHERE visit_id = v.id) as lab_tests_count
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ? AND v.diagnosis IS NOT NULL AND v.diagnosis != ''
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $consultations = [];
}

// ================================================================
// GET MEDICATIONS HISTORY
// ================================================================
$medications_history = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT pi.medication_name, 
               COUNT(pi.id) as times_prescribed,
               MAX(p.created_at) as last_prescribed
        FROM prescription_items pi
        JOIN prescriptions p ON pi.prescription_id = p.id
        WHERE p.patient_id = ?
        GROUP BY pi.medication_name
        ORDER BY times_prescribed DESC
    ");
    $stmt->execute([$patient_id]);
    $medications_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $medications_history = [];
}

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
// HELPER FUNCTIONS
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
    $index = abs(crc32($name)) % count($colors);
    return $colors[$index];
}

function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-pending',
        'assigned' => 'badge-info',
        'with_doctor' => 'badge-primary',
        'lab_test' => 'badge-warning',
        'prescribed' => 'badge-purple',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger',
        'scheduled' => 'badge-info',
        'confirmed' => 'badge-success',
        'in-progress' => 'badge-warning',
        'paid' => 'badge-success',
        'partial' => 'badge-warning',
        'dispensed' => 'badge-success'
    ];
    return $map[$status] ?? 'badge-info';
}

function time_ago($timestamp) {
    if (empty($timestamp)) return 'N/A';
    try {
        $time = strtotime($timestamp);
        if ($time === false) return 'N/A';
        $diff = time() - $time;
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
        return date('M d, Y', $time);
    } catch (Exception $e) {
        return 'N/A';
    }
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_header.php';
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_sidebar.php';
?>

<!-- ================================================================ -->
<!-- STYLES -->
<!-- ================================================================ -->
<style>
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 24px 28px;
        min-height: calc(100vh - 68px);
        background: var(--bg-body);
        color: var(--text-primary);
        transition: all 0.3s ease;
    }
    
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
    .separator { color: var(--border-color); margin: 0 4px; }
    
    .page-header-right {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    
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
    
    .btn-pdf {
        background: #DC2626;
        color: white;
        border: none;
        padding: 8px 18px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.82rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        border: 2px solid #DC2626;
    }
    .btn-pdf:hover {
        background: #B91C1C;
        border-color: #B91C1C;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }
    
    /* Patient Profile */
    .patient-profile {
        display: flex;
        align-items: center;
        gap: 24px;
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        margin-bottom: 24px;
        transition: all 0.3s ease;
    }
    .patient-profile:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }
    
    .patient-avatar-large {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
        box-shadow: 0 4px 14px rgba(0,0,0,0.15);
    }
    
    .patient-profile-info { flex: 1; }
    .patient-name {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 4px 0;
    }
    .patient-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 12px 20px;
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin-bottom: 8px;
    }
    .patient-meta i { width: 18px; }
    
    .patient-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 0;
    }
    .tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
    }
    .tag-danger { background: #FEE2E2; color: #DC2626; }
    .tag-info { background: #E8F0FE; color: #0B5ED7; }
    .tag-success { background: #D1FAE5; color: #059669; }
    .tag-warning { background: #FEF3C7; color: #D97706; }
    
    /* Stats Grid - 6 Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 12px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 14px 16px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        text-align: center;
    }
    .stat-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }
    .stat-card-inner { display: flex; flex-direction: column; align-items: center; gap: 4px; }
    .stat-card-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        color: white;
    }
    .stat-card-blue .stat-card-icon { background: linear-gradient(135deg, #0B5ED7, #1A73E8); }
    .stat-card-green .stat-card-icon { background: linear-gradient(135deg, #059669, #34D399); }
    .stat-card-purple .stat-card-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
    .stat-card-orange .stat-card-icon { background: linear-gradient(135deg, #D97706, #F59E0B); }
    .stat-card-red .stat-card-icon { background: linear-gradient(135deg, #DC2626, #EF4444); }
    .stat-card-teal .stat-card-icon { background: linear-gradient(135deg, #0D9488, #14B8A6); }
    
    .stat-card-number {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.2;
    }
    .stat-card-label {
        font-size: 0.6rem;
        font-weight: 500;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    
    /* Consultation Card */
    .consultation-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }
    .consultation-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }
    
    .card-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
    }
    .title-blue { color: #0B5ED7; }
    .title-green { color: #059669; }
    .title-purple { color: #7C3AED; }
    .title-orange { color: #D97706; }
    .title-red { color: #DC2626; }
    .title-teal { color: #0D9488; }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px 20px;
    }
    .info-item { display: flex; flex-direction: column; }
    .info-label {
        font-size: 0.65rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 500;
    }
    .info-value {
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--text-primary);
        padding: 2px 0;
    }
    .font-mono { font-family: monospace; }
    
    /* Tables */
    .table-wrap { overflow-x: auto; }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }
    .data-table th {
        text-align: left;
        padding: 8px 12px;
        font-weight: 600;
        font-size: 0.65rem;
        text-transform: uppercase;
        color: var(--text-secondary);
        border-bottom: 2px solid var(--border-color);
    }
    .data-table td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
    }
    .data-table tr:hover td { background: var(--primary-bg); }
    
    /* Status Badges */
    .status-badge {
        display: inline-block;
        font-size: 0.65rem;
        font-weight: 600;
        padding: 2px 12px;
        border-radius: 20px;
        line-height: 1.4;
        text-align: center;
        min-width: 50px;
        border: 1px solid transparent;
    }
    
    .badge-pending { background: #FEF3C7; color: #D97706; border-color: #FDE68A; }
    .badge-warning { background: #FEF3C7; color: #D97706; border-color: #FDE68A; }
    .badge-success { background: transparent !important; color: #059669; border: none !important; font-weight: 700; }
    .badge-danger { background: #FEE2E2; color: #DC2626; border-color: #FCA5A5; }
    .badge-info { background: #E8F0FE; color: #0B5ED7; border-color: #BFDBFE; }
    .badge-primary { background: #E8F0FE; color: #0B5ED7; border-color: #BFDBFE; }
    .badge-purple { background: #EDE9FE; color: #7C3AED; border-color: #C4B5FD; }
    
    /* Item Type Badge */
    .item-type-badge {
        font-size: 0.6rem;
        font-weight: 500;
        padding: 2px 8px;
        border-radius: 12px;
        background: var(--bg-body);
        color: var(--text-secondary);
        border: 1px solid var(--border-color);
    }
    
    /* Visit Detail Cards */
    .visit-detail-card {
        background: var(--bg-body);
        border-radius: 10px;
        padding: 14px 18px;
        margin-bottom: 12px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .visit-detail-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-sm);
    }
    
    .visit-detail-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 10px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .visit-detail-info {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }
    
    .visit-number {
        font-weight: 700;
        color: var(--primary);
        font-family: monospace;
        font-size: 0.85rem;
    }
    
    .visit-date {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .visit-doctor {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .visit-detail-body {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .visit-detail-row {
        display: flex;
        padding: 2px 0;
        gap: 8px;
    }
    
    .visit-detail-label {
        font-weight: 600;
        color: var(--text-secondary);
        min-width: 100px;
        flex-shrink: 0;
        font-size: 0.8rem;
    }
    
    .visit-detail-value {
        color: var(--text-primary);
        flex: 1;
        font-size: 0.85rem;
    }
    
    .visit-detail-subsection {
        margin-top: 6px;
        padding-top: 6px;
        border-top: 1px dashed var(--border-color);
    }
    
    .visit-detail-subsection .visit-detail-label {
        display: block;
        margin-bottom: 4px;
        min-width: auto;
    }
    
    .visit-detail-items {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    
    .visit-item-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: var(--bg-card);
        padding: 3px 10px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        font-size: 0.75rem;
        color: var(--text-primary);
    }
    
    .visit-item-tag .badge {
        font-size: 0.6rem;
        padding: 1px 6px;
    }
    
    /* Dark Mode */
    [data-theme="dark"] .badge-pending { background: #3D2E0A; color: #FBBF24; border-color: #78350F; }
    [data-theme="dark"] .badge-warning { background: #3D2E0A; color: #FBBF24; border-color: #78350F; }
    [data-theme="dark"] .badge-success { background: transparent !important; color: #34D399; border: none !important; }
    [data-theme="dark"] .badge-danger { background: #3A1A1A; color: #F87171; border-color: #7F1D1D; }
    [data-theme="dark"] .badge-info { background: #1E3A5F; color: #6EA8FE; border-color: #1E3A5F; }
    [data-theme="dark"] .badge-primary { background: #1E3A5F; color: #6EA8FE; border-color: #1E3A5F; }
    [data-theme="dark"] .badge-purple { background: #2D1A3A; color: #A78BFA; border-color: #2D1A3A; }
    [data-theme="dark"] .visit-detail-card { background: #1E293B; border-color: #334155; }
    [data-theme="dark"] .visit-item-tag { background: #1E293B; border-color: #334155; }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 20px 10px;
        color: var(--text-secondary);
    }
    .empty-state i {
        font-size: 2rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 6px;
    }
    .empty-state p { font-size: 0.85rem; margin: 0; }
    
    /* Toast */
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
    
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: var(--primary); font-weight: 600; }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .stats-grid { grid-template-columns: repeat(3, 1fr); }
        .info-grid { grid-template-columns: repeat(2, 1fr); }
        .main-content { padding: 16px; }
        .page-title { font-size: 1.3rem; }
    }
    
    @media (max-width: 768px) {
        .main-content { margin-left: 0; padding: 12px; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .info-grid { grid-template-columns: 1fr; }
        .page-header { flex-direction: column; }
        .page-header-right { width: 100%; }
        .page-header-right .btn { flex: 1; justify-content: center; }
        .patient-profile { flex-direction: column; text-align: center; padding: 16px; }
        .patient-meta { justify-content: center; }
        .patient-tags { justify-content: center; }
        .data-table { font-size: 0.7rem; }
        .data-table th, .data-table td { padding: 4px 8px; }
        .visit-detail-row { flex-direction: column; gap: 2px; }
        .visit-detail-label { min-width: auto; }
        .visit-detail-header { flex-direction: column; align-items: flex-start; }
    }
    
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .page-subtitle { flex-direction: column; align-items: flex-start; gap: 4px; }
        .separator { display: none; }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .consultation-card { border: 1px solid #ddd !important; page-break-inside: avoid; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">
                <i class="fas fa-user-circle"></i> Patient History
                <span class="page-badge"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span>
            </h1>
            <p class="page-subtitle">
                Complete medical history and patient records
                <span class="separator">|</span>
                <span class="status-badge badge-info">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>
                </span>
                <span class="separator">|</span>
                <span class="status-badge badge-success">
                    <i class="fas fa-calendar-alt"></i> Registered: <?= date('M d, Y', strtotime($patient['created_at'] ?? 'now')) ?>
                </span>
            </p>
        </div>
        <div class="page-header-right">
            <button onclick="exportPDF(<?= $patient_id ?>)" class="btn btn-pdf">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
            <a href="my_patients.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> My Patients
            </a>
            <button onclick="window.print()" class="btn btn-outline">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT PROFILE -->
    <!-- ================================================================ -->
    <div class="patient-profile">
        <div class="patient-avatar-large" style="background: <?= getUserColor($patient['full_name'] ?? 'Unknown') ?>;">
            <?= strtoupper(substr($patient['full_name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="patient-profile-info">
            <h2 class="patient-name"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></h2>
            <div class="patient-meta">
                <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span>
                <span><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                <span><i class="fas fa-birthday-cake"></i> <?= calculateAge($patient['date_of_birth'] ?? '') ?> years</span>
                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
                <span><i class="fas fa-tint"></i> <?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
            </div>
            <div class="patient-tags">
                <?php if (!empty($patient['allergies']) && $patient['allergies'] !== 'None' && $patient['allergies'] !== 'N/A'): ?>
                    <span class="tag tag-danger"><i class="fas fa-exclamation-triangle"></i> Allergies: <?= htmlspecialchars($patient['allergies']) ?></span>
                <?php endif; ?>
                <span class="tag tag-info"><i class="fas fa-address-book"></i> <?= htmlspecialchars($patient['address'] ?? 'No address') ?></span>
                <?php if (!empty($patient['emergency_contact'])): ?>
                    <span class="tag tag-warning"><i class="fas fa-phone-alt"></i> Emergency: <?= htmlspecialchars($patient['emergency_contact']) ?></span>
                <?php endif; ?>
                <span class="tag tag-success"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-card stat-card-blue">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-clinic-medical"></i></div>
                <span class="stat-card-number"><?= $patient['total_visits'] ?? 0 ?></span>
                <span class="stat-card-label">Visits</span>
            </div>
        </div>
        <div class="stat-card stat-card-green">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-prescription"></i></div>
                <span class="stat-card-number"><?= $patient['total_prescriptions'] ?? 0 ?></span>
                <span class="stat-card-label">Prescriptions</span>
            </div>
        </div>
        <div class="stat-card stat-card-purple">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-flask"></i></div>
                <span class="stat-card-number"><?= $patient['total_lab_tests'] ?? 0 ?></span>
                <span class="stat-card-label">Lab Tests</span>
            </div>
        </div>
        <div class="stat-card stat-card-orange">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-calendar-check"></i></div>
                <span class="stat-card-number"><?= $patient['total_appointments'] ?? 0 ?></span>
                <span class="stat-card-label">Appointments</span>
            </div>
        </div>
        <div class="stat-card stat-card-red">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-syringe"></i></div>
                <span class="stat-card-number"><?= count($procedures) ?></span>
                <span class="stat-card-label">Procedures</span>
            </div>
        </div>
        <div class="stat-card stat-card-teal">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-receipt"></i></div>
                <span class="stat-card-number"><?= count($bills) ?></span>
                <span class="stat-card-label">Bills</span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT DETAILS -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-info-circle title-blue"></i> Patient Details
        </h3>
        <div class="info-grid">
            <div class="info-item"><span class="info-label">Full Name</span><span class="info-value"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Patient ID</span><span class="info-value font-mono"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Date of Birth</span><span class="info-value"><?= !empty($patient['date_of_birth']) ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></span></div>
            <div class="info-item"><span class="info-label">Age</span><span class="info-value"><?= calculateAge($patient['date_of_birth'] ?? '') ?> years</span></div>
            <div class="info-item"><span class="info-label">Gender</span><span class="info-value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Blood Group</span><span class="info-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Phone</span><span class="info-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Email</span><span class="info-value"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Emergency Contact</span><span class="info-value"><?= htmlspecialchars($patient['emergency_contact'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Branch</span><span class="info-value"><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Allergies</span><span class="info-value"><?= htmlspecialchars($patient['allergies'] ?? 'None') ?></span></div>
            <div class="info-item"><span class="info-label">Address</span><span class="info-value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Assigned Doctor</span><span class="info-value"><?= htmlspecialchars($patient['doctor_name'] ?? 'Not Assigned') ?></span></div>
            <div class="info-item"><span class="info-label">Registered</span><span class="info-value"><?= date('M d, Y h:i A', strtotime($patient['created_at'] ?? 'now')) ?></span></div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MEDICATIONS HISTORY -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-pills title-blue"></i> Medications History
            <span class="text-sm font-normal text-gray-400">(<?= count($medications_history) ?> unique medications)</span>
        </h3>
        
        <?php if (count($medications_history) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Medication Name</th><th>Times Prescribed</th><th>Last Prescribed</th></tr></thead>
                    <tbody>
                        <?php $i = 1; foreach ($medications_history as $med): ?>
                            <tr><td><?= $i++ ?></td><td><strong><?= htmlspecialchars($med['medication_name'] ?? 'N/A') ?></strong></td><td><span class="badge badge-info"><?= $med['times_prescribed'] ?? 0 ?></span></td><td><?= time_ago($med['last_prescribed'] ?? '') ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-pills"></i><p>No medication history found</p></div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- PROCEDURES & TOOLS -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-syringe title-red"></i> Procedures & Tools History
            <span class="text-sm font-normal text-gray-400">(<?= count($procedures) ?> items)</span>
        </h3>
        
        <?php if (count($procedures) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Item Name</th><th>Type</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php $i = 1; foreach ($procedures as $proc): 
                            $is_paid = ($proc['payment_status'] ?? 'pending') === 'paid';
                        ?>
                            <tr class="<?= $is_paid ? 'paid-row' : '' ?>">
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($proc['item_name'] ?? 'N/A') ?></strong></td>
                                <td><span class="item-type-badge"><?= (!empty($proc['item_type']) && $proc['item_type'] == 'procedure') ? 'Procedure' : (!empty($proc['item_type']) ? htmlspecialchars($proc['item_type']) : 'Tool') ?></span></td>
                                <td><?= $proc['quantity'] ?? 1 ?></td>
                                <td><?= number_format($proc['unit_price'] ?? 0, 2) ?></td>
                                <td class="font-mono">TSh <?= number_format($proc['total_price'] ?? 0, 2) ?></td>
                                <td><span class="status-badge <?= $is_paid ? 'badge-success' : 'badge-pending' ?>"><?= ucfirst($proc['payment_status'] ?? 'Pending') ?></span></td>
                                <td><?= date('M d, Y', strtotime($proc['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-syringe"></i><p>No procedures recorded for this patient</p></div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- VISITS WITH FULL DETAILS -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-history title-blue"></i> Visit History
            <span class="text-sm font-normal text-gray-400">(<?= count($visits) ?> visits)</span>
        </h3>
        
        <?php if (count($visits) > 0): ?>
            <?php foreach ($visits as $visit): 
                $visit_id = $visit['id'];
                $items = $visit_items[$visit_id] ?? [];
                $visit_prescriptions = $items['prescriptions'] ?? [];
                $visit_lab_tests = $items['lab_tests'] ?? [];
                $visit_procedures = $items['procedures'] ?? [];
                $visit_medications = $items['medications'] ?? [];
            ?>
            <div class="visit-detail-card">
                <div class="visit-detail-header">
                    <div class="visit-detail-info">
                        <span class="visit-number"><?= htmlspecialchars($visit['visit_number']) ?></span>
                        <span class="visit-date"><?= date('M d, Y h:i A', strtotime($visit['created_at'])) ?></span>
                        <span class="visit-doctor">Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'Not assigned') ?></span>
                        <span class="status-badge <?= getStatusBadgeClass($visit['status'] ?? 'pending') ?>">
                            <?= ucfirst($visit['status'] ?? 'Pending') ?>
                        </span>
                    </div>
                    <?php if ($visit['is_completed'] ?? 0): ?>
                        <span class="badge badge-success">✅ Completed</span>
                    <?php endif; ?>
                </div>
                
                <div class="visit-detail-body">
                    <!-- Symptoms -->
                    <?php if (!empty($visit['symptoms'])): ?>
                    <div class="visit-detail-row">
                        <span class="visit-detail-label">Symptoms:</span>
                        <span class="visit-detail-value"><?= htmlspecialchars($visit['symptoms']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Complaint -->
                    <?php if (!empty($visit['complaint'])): ?>
                    <div class="visit-detail-row">
                        <span class="visit-detail-label">Complaint:</span>
                        <span class="visit-detail-value"><?= htmlspecialchars($visit['complaint']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Diagnosis -->
                    <?php if (!empty($visit['diagnosis'])): ?>
                    <div class="visit-detail-row">
                        <span class="visit-detail-label">Diagnosis:</span>
                        <span class="visit-detail-value"><?= htmlspecialchars($visit['diagnosis']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Treatment -->
                    <?php if (!empty($visit['treatment'])): ?>
                    <div class="visit-detail-row">
                        <span class="visit-detail-label">Treatment:</span>
                        <span class="visit-detail-value"><?= htmlspecialchars($visit['treatment']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Notes -->
                    <?php if (!empty($visit['notes'])): ?>
                    <div class="visit-detail-row">
                        <span class="visit-detail-label">Notes:</span>
                        <span class="visit-detail-value"><?= htmlspecialchars($visit['notes']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Follow-up Date -->
                    <?php if (!empty($visit['follow_up_date'])): ?>
                    <div class="visit-detail-row">
                        <span class="visit-detail-label">Follow-up:</span>
                        <span class="visit-detail-value"><?= date('M d, Y', strtotime($visit['follow_up_date'])) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Prescriptions -->
                    <?php if (count($visit_prescriptions) > 0): ?>
                    <div class="visit-detail-subsection">
                        <span class="visit-detail-label">💊 Prescriptions:</span>
                        <div class="visit-detail-items">
                            <?php foreach ($visit_prescriptions as $pres): ?>
                                <div class="visit-item-tag">
                                    #<?= htmlspecialchars($pres['prescription_number']) ?>
                                    <?php if (!empty($pres['medications_list'])): ?>
                                        (<?= htmlspecialchars(substr($pres['medications_list'], 0, 30)) . (strlen($pres['medications_list'] ?? '') > 30 ? '...' : '') ?>)
                                    <?php endif; ?>
                                    <span class="badge <?= $pres['status'] === 'dispensed' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= ucfirst($pres['status'] ?? 'Pending') ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Lab Tests -->
                    <?php if (count($visit_lab_tests) > 0): ?>
                    <div class="visit-detail-subsection">
                        <span class="visit-detail-label">🧪 Lab Tests:</span>
                        <div class="visit-detail-items">
                            <?php foreach ($visit_lab_tests as $test): ?>
                                <div class="visit-item-tag">
                                    <?= htmlspecialchars($test['test_name']) ?>
                                    <span class="badge <?= $test['status'] === 'completed' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= ucfirst($test['status'] ?? 'Pending') ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Procedures -->
                    <?php if (count($visit_procedures) > 0): ?>
                    <div class="visit-detail-subsection">
                        <span class="visit-detail-label">💉 Procedures:</span>
                        <div class="visit-detail-items">
                            <?php foreach ($visit_procedures as $proc): ?>
                                <div class="visit-item-tag">
                                    <?= htmlspecialchars($proc['item_name']) ?>
                                    <span class="badge <?= ($proc['payment_status'] ?? 'pending') === 'paid' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= ucfirst($proc['payment_status'] ?? 'Pending') ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Medications -->
                    <?php if (count($visit_medications) > 0): ?>
                    <div class="visit-detail-subsection">
                        <span class="visit-detail-label">💊 Medications:</span>
                        <div class="visit-detail-items">
                            <?php foreach ($visit_medications as $med): ?>
                                <div class="visit-item-tag">
                                    <?= htmlspecialchars($med['item_name']) ?>
                                    x<?= $med['quantity'] ?>
                                    <span class="badge <?= ($med['payment_status'] ?? 'pending') === 'paid' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= ucfirst($med['payment_status'] ?? 'Pending') ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-clinic-medical"></i><p>No visits recorded for this patient</p></div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- CONSULTATIONS -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-stethoscope title-blue"></i> Consultation History
            <span class="text-sm font-normal text-gray-400">(<?= count($consultations) ?> consultations)</span>
        </h3>
        
        <?php if (count($consultations) > 0): ?>
            <div class="consultation-list">
                <?php foreach ($consultations as $consult): ?>
                    <div class="consultation-item">
                        <div class="consultation-header">
                            <span class="consultation-date"><i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($consult['created_at'])) ?></span>
                            <span class="consultation-doctor"><i class="fas fa-user-md"></i> <?= htmlspecialchars($consult['doctor_name'] ?? 'N/A') ?></span>
                            <span class="consultation-number"><?= htmlspecialchars($consult['visit_number'] ?? 'N/A') ?></span>
                        </div>
                        <div class="consultation-body">
                            <div class="consultation-row"><span class="consultation-label">Diagnosis:</span><span class="consultation-value"><?= htmlspecialchars($consult['diagnosis'] ?? 'N/A') ?></span></div>
                            <div class="consultation-row"><span class="consultation-label">Treatment:</span><span class="consultation-value"><?= htmlspecialchars($consult['treatment'] ?? 'N/A') ?></span></div>
                            <?php if (!empty($consult['symptoms'])): ?>
                                <div class="consultation-row"><span class="consultation-label">Symptoms:</span><span class="consultation-value"><?= htmlspecialchars($consult['symptoms']) ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($consult['notes'])): ?>
                                <div class="consultation-row"><span class="consultation-label">Notes:</span><span class="consultation-value"><?= htmlspecialchars($consult['notes']) ?></span></div>
                            <?php endif; ?>
                            <div class="consultation-stats">
                                <span class="badge badge-info"><?= $consult['prescriptions_count'] ?? 0 ?> Prescriptions</span>
                                <span class="badge badge-purple"><?= $consult['lab_tests_count'] ?? 0 ?> Lab Tests</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-stethoscope"></i><p>No consultations recorded</p></div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-prescription title-green"></i> Prescription History
            <span class="text-sm font-normal text-gray-400">(<?= count($prescriptions) ?> prescriptions)</span>
        </h3>
        
        <?php if (count($prescriptions) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Prescription #</th><th>Date</th><th>Diagnosis</th><th>Doctor</th><th>Medications</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php $i = 1; foreach ($prescriptions as $prescription): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td class="font-mono"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></td>
                                <td><?= date('M d, Y', strtotime($prescription['created_at'])) ?></td>
                                <td><?= htmlspecialchars(substr($prescription['diagnosis'] ?? '', 0, 25)) . (strlen($prescription['diagnosis'] ?? '') > 25 ? '...' : '') ?></td>
                                <td><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></td>
                                <td><span class="badge badge-info"><?= $prescription['medications_count'] ?? 0 ?> med(s)</span></td>
                                <td><span class="status-badge <?= getStatusBadgeClass($prescription['status'] ?? 'pending') ?>"><?= ucfirst($prescription['status'] ?? 'Pending') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-prescription"></i><p>No prescriptions found</p></div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- LAB TESTS -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-flask title-purple"></i> Lab Tests History
            <span class="text-sm font-normal text-gray-400">(<?= count($lab_tests) ?> tests)</span>
        </h3>
        
        <?php if (count($lab_tests) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Test Name</th><th>Date</th><th>Doctor</th><th>Results</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($lab_tests as $test): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong></td>
                                <td><?= date('M d, Y', strtotime($test['created_at'])) ?></td>
                                <td><?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                                <td><?php if ($test['status'] === 'completed' && !empty($test['results'])): ?><span class="text-green-600"><?= htmlspecialchars(substr($test['results'], 0, 30)) . (strlen($test['results'] ?? '') > 30 ? '...' : '') ?></span><?php elseif ($test['status'] === 'completed'): ?><span class="text-green-600">Results available</span><?php else: ?><span class="text-gray-400">Pending</span><?php endif; ?></td>
                                <td><span class="status-badge <?= getStatusBadgeClass($test['status'] ?? 'pending') ?>"><?= ucfirst($test['status'] ?? 'Pending') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-flask"></i><p>No lab tests found</p></div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- APPOINTMENTS -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-calendar-check title-purple"></i> Appointments History
            <span class="text-sm font-normal text-gray-400">(<?= count($appointments) ?> appointments)</span>
        </h3>
        
        <?php if (count($appointments) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Date & Time</th><th>Doctor</th><th>Purpose</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php $i = 1; foreach ($appointments as $appointment): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= date('M d, Y h:i A', strtotime($appointment['appointment_date'])) ?></td>
                                <td><?= htmlspecialchars($appointment['doctor_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($appointment['purpose'] ?? 'N/A') ?></td>
                                <td><span class="status-badge <?= getStatusBadgeClass($appointment['status'] ?? 'scheduled') ?>"><?= ucfirst($appointment['status'] ?? 'Scheduled') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-calendar-check"></i><p>No appointments found</p></div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- BILLS -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-receipt title-orange"></i> Bills History
            <span class="text-sm font-normal text-gray-400">(<?= count($bills) ?> bills)</span>
        </h3>
        
        <?php if (count($bills) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Bill Number</th><th>Date</th><th>Total Amount</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php $i = 1; foreach ($bills as $bill): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td class="font-mono"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                                <td><?= date('M d, Y', strtotime($bill['created_at'])) ?></td>
                                <td class="font-mono">TSh <?= number_format($bill['total_amount'] ?? 0, 2) ?></td>
                                <td class="font-mono text-green-600">TSh <?= number_format($bill['paid_amount'] ?? 0, 2) ?></td>
                                <td class="font-mono <?= ($bill['balance'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' ?>">TSh <?= number_format($bill['balance'] ?? 0, 2) ?></td>
                                <td><span class="status-badge <?= getStatusBadgeClass($bill['status'] ?? 'pending') ?>"><?= ucfirst($bill['status'] ?? 'Pending') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-receipt"></i><p>No bills found</p></div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="separator">|</span>
            Patient History - <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>
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
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // PDF EXPORT
    // ================================================================
    function exportPDF(patientId) {
        showToast('📄 Generating PDF', 'Please wait...', 'info');
        var pdfWindow = window.open(
            'export_patient_pdf.php?id=' + patientId,
            '_blank',
            'width=1000,height=800,scrollbars=yes,resizable=yes'
        );
        if (!pdfWindow) {
            showToast('⚠️ Popup Blocked', 'Please allow popups for this site', 'warning');
        }
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

    console.log('%c👤 Patient History - <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Patient ID: <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>', 'font-size:12px; color:#059669;');
    console.log('%c📊 Visits: <?= $patient['total_visits'] ?? 0 ?>', 'font-size:12px; color:#64748B;');
    console.log('%c💊 Prescriptions: <?= $patient['total_prescriptions'] ?? 0 ?>', 'font-size:12px; color:#7C3AED;');
    console.log('%c🧪 Lab Tests: <?= $patient['total_lab_tests'] ?? 0 ?>', 'font-size:12px; color:#D97706;');
    console.log('%c📅 Appointments: <?= $patient['total_appointments'] ?? 0 ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c💉 Procedures: <?= count($procedures) ?>', 'font-size:12px; color:#DC2626;');
    console.log('%c💰 Bills: <?= count($bills) ?>', 'font-size:12px; color:#D97706;');
    console.log('%c📋 Visit details now show: Symptoms, Diagnosis, Treatment, Prescriptions, Lab Tests, Procedures, Medications', 'font-size:11px; color:#34D399;');
</script>

</body>
</html>