<?php
// ================================================================
// FILE: frontend/pages/doctor/view_patient.php
// DOCTOR - VIEW PATIENT COMPLETE HISTORY
// WITH SEPARATE CARDS: Lab Tests, Diagnosis, Prescriptions, Procedures, Tools, Bills
// FIXED: Lab Tests now join with visits to get patient_id
// THEME: BLUE THEME
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
// GET PATIENT DETAILS - INCLUDING MARITAL STATUS
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
               (SELECT COUNT(*) FROM lab_tests lt JOIN visits v ON lt.visit_id = v.id WHERE v.patient_id = p.id) as total_lab_tests,
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
// GET ALL VISITS WITH FULL DETAILS - ORDERED BY DATE DESC
// ================================================================
$visits = [];
$last_visit = null;
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
    
    // Get last visit (first one since ORDER BY DESC)
    if (count($visits) > 0) {
        $last_visit = $visits[0];
    }
} catch (Exception $e) {
    $visits = [];
    $last_visit = null;
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
    
    // Get procedures for this visit
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
    
    // Get medications for this visit
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
// GET ALL DIAGNOSES
// ================================================================
$diagnoses = [];
try {
    $stmt = $db->prepare("
        SELECT v.id, v.visit_number, v.created_at, v.diagnosis, v.symptoms, v.treatment, 
               v.complaint, v.notes, v.follow_up_date,
               u.full_name as doctor_name,
               (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id) as prescriptions_count,
               (SELECT COUNT(*) FROM lab_tests WHERE visit_id = v.id) as lab_tests_count
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ? AND v.diagnosis IS NOT NULL AND v.diagnosis != ''
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $diagnoses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $diagnoses = [];
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
        LIMIT 50
    ");
    $stmt->execute([$patient_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $prescriptions = [];
}

// ================================================================
// GET ALL LAB TESTS - FIXED: Join with visits to get patient_id
// ================================================================
$lab_tests = [];
try {
    $stmt = $db->prepare("
        SELECT lt.*, 
               u.full_name as doctor_name,
               lab.full_name as lab_technician_name,
               v.patient_id
        FROM lab_tests lt
        JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        LEFT JOIN users lab ON lt.lab_technician_id = lab.id
        WHERE v.patient_id = ?
        ORDER BY lt.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lab_tests = [];
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
// GET ALL TOOLS
// ================================================================
$tools = [];
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
        AND (bi.item_type = 'tool' OR bi.item_type = 'equipment' OR bi.item_type = 'instrument')
        ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tools = [];
}

// ================================================================
// GET ALL BILLS WITH THEIR ITEMS
// ================================================================
$bills = [];
$bill_items = [];
try {
    // Get all bills
    $stmt = $db->prepare("
        SELECT pb.*, 
               u.full_name as created_by_name
        FROM patient_bills pb
        LEFT JOIN users u ON pb.created_by = u.id
        WHERE pb.patient_id = ?
        ORDER BY pb.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all bill items for each bill
    foreach ($bills as $bill) {
        $stmt = $db->prepare("
            SELECT bi.* 
            FROM bill_items bi
            WHERE bi.bill_id = ?
            ORDER BY bi.created_at DESC
        ");
        $stmt->execute([$bill['id']]);
        $bill_items[$bill['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $bills = [];
    $bill_items = [];
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
<!-- STYLES - BLUE THEME -->
<!-- ================================================================ -->
<style>
    :root {
        --primary: #0B5ED7;
        --primary-dark: #0A4CA8;
        --primary-light: #6EA8FE;
        --primary-bg: #E8F0FE;
        --primary-gradient: linear-gradient(135deg, #0B5ED7, #1A73E8);
        --shadow-primary: 0 4px 20px rgba(11, 94, 215, 0.25);
    }
    
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
    
    .btn-print {
        background: #4B5563;
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
        border: 2px solid #4B5563;
    }
    .btn-print:hover {
        background: #374151;
        border-color: #374151;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(75, 85, 99, 0.3);
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
    .tag-primary { background: #E8F0FE; color: #0B5ED7; }
    .tag-purple { background: #EDE9FE; color: #7C3AED; }
    
    /* Stats Grid - 6 Cards - BLUE THEME */
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
    
    /* History Cards - BLUE THEME */
    .history-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }
    .history-card:hover {
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
    .title-pink { color: #DB2777; }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
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
    
    /* Bill Items Sub-table */
    .bill-items-subtable {
        width: 100%;
        margin-top: 4px;
        font-size: 0.75rem;
        background: var(--bg-body);
        border-radius: 6px;
    }
    .bill-items-subtable th {
        font-size: 0.6rem;
        padding: 4px 8px;
        background: var(--border-color);
        color: var(--text-secondary);
        border: none;
    }
    .bill-items-subtable td {
        padding: 4px 8px;
        border-bottom: 1px solid var(--border-color);
        font-size: 0.75rem;
    }
    .bill-items-subtable tr:last-child td {
        border-bottom: none;
    }
    .bill-items-subtable .item-total {
        font-weight: 600;
        color: var(--primary);
    }
    
    .badge-item-paid { background: #D1FAE5; color: #059669; font-size: 0.55rem; padding: 1px 8px; border-radius: 12px; }
    .badge-item-pending { background: #FEF3C7; color: #D97706; font-size: 0.55rem; padding: 1px 8px; border-radius: 12px; }
    
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
    .badge-success { background: #D1FAE5; color: #059669; border-color: #A7F3D0; }
    .badge-danger { background: #FEE2E2; color: #DC2626; border-color: #FCA5A5; }
    .badge-info { background: #E8F0FE; color: #0B5ED7; border-color: #BFDBFE; }
    .badge-primary { background: #E8F0FE; color: #0B5ED7; border-color: #BFDBFE; }
    .badge-purple { background: #EDE9FE; color: #7C3AED; border-color: #C4B5FD; }
    .badge-completed { background: #D1FAE5; color: #059669; border-color: #A7F3D0; }
    
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
    
    /* Marital Status Badge */
    .marital-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .marital-single { background: #E8F0FE; color: #0B5ED7; }
    .marital-married { background: #D1FAE5; color: #059669; }
    .marital-divorced { background: #FEF3C7; color: #D97706; }
    .marital-widowed { background: #EDE9FE; color: #7C3AED; }
    .marital-separated { background: #FEE2E2; color: #DC2626; }
    
    /* Dark Mode */
    [data-theme="dark"] .badge-pending { background: #3D2E0A; color: #FBBF24; border-color: #78350F; }
    [data-theme="dark"] .badge-warning { background: #3D2E0A; color: #FBBF24; border-color: #78350F; }
    [data-theme="dark"] .badge-success { background: #1A3A2A; color: #34D399; border-color: #065F46; }
    [data-theme="dark"] .badge-danger { background: #3A1A1A; color: #F87171; border-color: #7F1D1D; }
    [data-theme="dark"] .badge-info { background: #1E3A5F; color: #6EA8FE; border-color: #1E3A5F; }
    [data-theme="dark"] .badge-primary { background: #1E3A5F; color: #6EA8FE; border-color: #1E3A5F; }
    [data-theme="dark"] .badge-purple { background: #2D1A3A; color: #A78BFA; border-color: #2D1A3A; }
    [data-theme="dark"] .badge-completed { background: #1A3A2A; color: #34D399; border-color: #065F46; }
    [data-theme="dark"] .marital-single { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .marital-married { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .marital-divorced { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .marital-widowed { background: #2D1A3A; color: #A78BFA; }
    [data-theme="dark"] .marital-separated { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .bill-items-subtable { background: #0F172A; }
    [data-theme="dark"] .bill-items-subtable th { background: #1E293B; }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 30px 10px;
        color: var(--text-secondary);
    }
    .empty-state i {
        font-size: 2.5rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 10px;
    }
    .empty-state p { font-size: 0.9rem; margin: 0; }
    .empty-state .sub-text { font-size: 0.75rem; opacity: 0.6; margin-top: 4px; }
    
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
        .history-card { padding: 14px 16px; }
        .bill-items-subtable { font-size: 0.65rem; }
        .bill-items-subtable th, .bill-items-subtable td { padding: 2px 6px; }
    }
    
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .page-subtitle { flex-direction: column; align-items: flex-start; gap: 4px; }
        .separator { display: none; }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .footer, .no-print { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .history-card { border: 1px solid #ddd !important; page-break-inside: avoid; }
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
                <?php if (count($visits) > 0): ?>
                    <span class="page-badge" style="background:#D1FAE5;color:#059669;">
                        <i class="fas fa-clock"></i> Last Visit: <?= date('M d, Y', strtotime($visits[0]['created_at'] ?? 'now')) ?>
                    </span>
                <?php endif; ?>
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
                <span class="separator">|</span>
                <span class="status-badge badge-purple">
                    <i class="fas fa-clinic-medical"></i> <?= count($visits) ?> Total Visits
                </span>
            </p>
        </div>
        <div class="page-header-right no-print">
            <button onclick="exportPDF(<?= $patient_id ?>)" class="btn btn-pdf">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
            <a href="my_patients.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> My Patients
            </a>
            <button onclick="window.print()" class="btn btn-print">
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
                <span><i class="fas fa-ring"></i> <span class="marital-badge <?= 
                    strtolower($patient['marital_status'] ?? '') === 'single' ? 'marital-single' : 
                    (strtolower($patient['marital_status'] ?? '') === 'married' ? 'marital-married' : 
                    (strtolower($patient['marital_status'] ?? '') === 'divorced' ? 'marital-divorced' : 
                    (strtolower($patient['marital_status'] ?? '') === 'widowed' ? 'marital-widowed' : 
                    (strtolower($patient['marital_status'] ?? '') === 'separated' ? 'marital-separated' : '')))) 
                ?>"><?= htmlspecialchars($patient['marital_status'] ?? 'N/A') ?></span></span>
                <span><i class="fas fa-birthday-cake"></i> <?= calculateAge($patient['date_of_birth'] ?? '') ?> years</span>
                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
                <span><i class="fas fa-tint"></i> <?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
                <span><i class="fas fa-clinic-medical"></i> <?= count($visits) ?> Visits</span>
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
                <?php if (!empty($patient['doctor_name'])): ?>
                    <span class="tag tag-primary"><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($patient['doctor_name']) ?></span>
                <?php endif; ?>
                <span class="tag tag-purple"><i class="fas fa-stethoscope"></i> <?= count($diagnoses) ?> Diagnoses</span>
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
                <span class="stat-card-number"><?= count($visits) ?></span>
                <span class="stat-card-label">Visits</span>
            </div>
        </div>
        <div class="stat-card stat-card-green">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-prescription"></i></div>
                <span class="stat-card-number"><?= count($prescriptions) ?></span>
                <span class="stat-card-label">Prescriptions</span>
            </div>
        </div>
        <div class="stat-card stat-card-purple">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-flask"></i></div>
                <span class="stat-card-number"><?= count($lab_tests) ?></span>
                <span class="stat-card-label">Lab Tests</span>
            </div>
        </div>
        <div class="stat-card stat-card-orange">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-calendar-check"></i></div>
                <span class="stat-card-number"><?= count($appointments) ?></span>
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
    <!-- LAST VISIT SUMMARY -->
    <!-- ================================================================ -->
    <?php if ($last_visit): 
        $last_visit_id = $last_visit['id'];
        $last_items = $visit_items[$last_visit_id] ?? [];
    ?>
    <div class="history-card" style="border-color: var(--primary); background: var(--primary-bg);">
        <h3 class="card-title" style="border-bottom-color: var(--primary);">
            <i class="fas fa-star title-blue"></i> Last Visit Summary
            <span class="text-sm font-normal text-gray-400">(<?= date('M d, Y h:i A', strtotime($last_visit['created_at'])) ?>)</span>
            <span class="status-badge badge-success" style="margin-left:auto;">
                <i class="fas fa-check-circle"></i> Most Recent
            </span>
        </h3>
        
        <div class="info-grid" style="margin-bottom: 12px;">
            <div class="info-item"><span class="info-label">Visit Number</span><span class="info-value font-mono"><?= htmlspecialchars($last_visit['visit_number'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Doctor</span><span class="info-value">Dr. <?= htmlspecialchars($last_visit['doctor_name'] ?? 'Not assigned') ?></span></div>
            <div class="info-item"><span class="info-label">Status</span><span class="info-value"><span class="status-badge <?= getStatusBadgeClass($last_visit['status'] ?? 'pending') ?>"><?= ucfirst($last_visit['status'] ?? 'Pending') ?></span></span></div>
        </div>
        
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 8px 20px; background: var(--bg-card); padding: 12px 16px; border-radius: 10px; border: 1px solid var(--border-color);">
            <?php if (!empty($last_visit['symptoms'])): ?>
                <div><span class="info-label">Symptoms</span><div class="info-value"><?= htmlspecialchars($last_visit['symptoms']) ?></div></div>
            <?php endif; ?>
            <?php if (!empty($last_visit['complaint'])): ?>
                <div><span class="info-label">Complaint</span><div class="info-value"><?= htmlspecialchars($last_visit['complaint']) ?></div></div>
            <?php endif; ?>
            <?php if (!empty($last_visit['diagnosis'])): ?>
                <div><span class="info-label">Diagnosis</span><div class="info-value"><?= htmlspecialchars($last_visit['diagnosis']) ?></div></div>
            <?php endif; ?>
            <?php if (!empty($last_visit['treatment'])): ?>
                <div><span class="info-label">Treatment</span><div class="info-value"><?= htmlspecialchars($last_visit['treatment']) ?></div></div>
            <?php endif; ?>
            <?php if (!empty($last_visit['follow_up_date'])): ?>
                <div><span class="info-label">Follow-up</span><div class="info-value"><?= date('M d, Y', strtotime($last_visit['follow_up_date'])) ?></div></div>
            <?php endif; ?>
            <?php if (!empty($last_visit['notes'])): ?>
                <div><span class="info-label">Notes</span><div class="info-value"><?= htmlspecialchars($last_visit['notes']) ?></div></div>
            <?php endif; ?>
        </div>
        
        <?php if (count($last_items['prescriptions'] ?? []) > 0 || count($last_items['lab_tests'] ?? []) > 0 || count($last_items['procedures'] ?? []) > 0): ?>
            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border-color);">
                <div style="display:flex; flex-wrap:wrap; gap: 12px;">
                    <?php if (count($last_items['prescriptions'] ?? []) > 0): ?>
                        <span class="badge badge-info"><i class="fas fa-prescription"></i> <?= count($last_items['prescriptions']) ?> Prescriptions</span>
                    <?php endif; ?>
                    <?php if (count($last_items['lab_tests'] ?? []) > 0): ?>
                        <span class="badge badge-purple"><i class="fas fa-flask"></i> <?= count($last_items['lab_tests']) ?> Lab Tests</span>
                    <?php endif; ?>
                    <?php if (count($last_items['procedures'] ?? []) > 0): ?>
                        <span class="badge badge-danger"><i class="fas fa-syringe"></i> <?= count($last_items['procedures']) ?> Procedures</span>
                    <?php endif; ?>
                    <?php if (count($last_items['medications'] ?? []) > 0): ?>
                        <span class="badge badge-success"><i class="fas fa-pills"></i> <?= count($last_items['medications']) ?> Medications</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PATIENT DETAILS -->
    <!-- ================================================================ -->
    <div class="history-card">
        <h3 class="card-title">
            <i class="fas fa-info-circle title-blue"></i> Patient Details
        </h3>
        <div class="info-grid">
            <div class="info-item"><span class="info-label">Full Name</span><span class="info-value"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Patient ID</span><span class="info-value font-mono"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Date of Birth</span><span class="info-value"><?= !empty($patient['date_of_birth']) ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></span></div>
            <div class="info-item"><span class="info-label">Age</span><span class="info-value"><?= calculateAge($patient['date_of_birth'] ?? '') ?> years</span></div>
            <div class="info-item"><span class="info-label">Gender</span><span class="info-value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Marital Status</span><span class="info-value"><span class="marital-badge <?= 
                strtolower($patient['marital_status'] ?? '') === 'single' ? 'marital-single' : 
                (strtolower($patient['marital_status'] ?? '') === 'married' ? 'marital-married' : 
                (strtolower($patient['marital_status'] ?? '') === 'divorced' ? 'marital-divorced' : 
                (strtolower($patient['marital_status'] ?? '') === 'widowed' ? 'marital-widowed' : 
                (strtolower($patient['marital_status'] ?? '') === 'separated' ? 'marital-separated' : '')))) 
            ?>"><?= htmlspecialchars($patient['marital_status'] ?? 'N/A') ?></span></span></div>
            <div class="info-item"><span class="info-label">Blood Group</span><span class="info-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Phone</span><span class="info-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Email</span><span class="info-value"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Emergency Contact</span><span class="info-value"><?= htmlspecialchars($patient['emergency_contact'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Address</span><span class="info-value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Allergies</span><span class="info-value"><?= htmlspecialchars($patient['allergies'] ?? 'None') ?></span></div>
            <div class="info-item"><span class="info-label">Branch</span><span class="info-value"><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></span></div>
            <div class="info-item"><span class="info-label">Assigned Doctor</span><span class="info-value"><?= htmlspecialchars($patient['doctor_name'] ?? 'Not Assigned') ?></span></div>
            <div class="info-item"><span class="info-label">Registered</span><span class="info-value"><?= date('M d, Y h:i A', strtotime($patient['created_at'] ?? 'now')) ?></span></div>
            <div class="info-item"><span class="info-label">Total Visits</span><span class="info-value"><?= count($visits) ?></span></div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 1. DIAGNOSIS HISTORY -->
    <!-- ================================================================ -->
    <div class="history-card">
        <h3 class="card-title">
            <i class="fas fa-stethoscope title-blue"></i> Diagnosis History
            <span class="text-sm font-normal text-gray-400">(<?= count($diagnoses) ?> diagnoses)</span>
        </h3>
        
        <?php if (count($diagnoses) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Visit #</th><th>Date</th><th>Diagnosis</th><th>Doctor</th><th>Treatment</th><th>Follow-up</th></tr></thead>
                    <tbody>
                        <?php $i = 1; foreach ($diagnoses as $diag): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td class="font-mono"><?= htmlspecialchars($diag['visit_number'] ?? 'N/A') ?></td>
                                <td><?= date('M d, Y', strtotime($diag['created_at'])) ?></td>
                                <td><strong><?= htmlspecialchars($diag['diagnosis'] ?? 'N/A') ?></strong></td>
                                <td><?= htmlspecialchars($diag['doctor_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars(substr($diag['treatment'] ?? '', 0, 30)) . (strlen($diag['treatment'] ?? '') > 30 ? '...' : '') ?></td>
                                <td><?= !empty($diag['follow_up_date']) ? date('M d, Y', strtotime($diag['follow_up_date'])) : 'N/A' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-stethoscope"></i>
                <p>No diagnoses recorded</p>
                <p class="sub-text">Diagnoses will appear here once recorded</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 2. LAB TEST HISTORY - FIXED: Join with visits to get patient_id -->
    <!-- ================================================================ -->
    <div class="history-card">
        <h3 class="card-title">
            <i class="fas fa-flask title-purple"></i> Lab Test History
            <span class="text-sm font-normal text-gray-400">(<?= count($lab_tests) ?> tests)</span>
        </h3>
        
        <?php if (count($lab_tests) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Test Name</th><th>Type</th><th>Date</th><th>Doctor</th><th>Sample Type</th><th>Results</th><th>Reference Range</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php $i = 1; foreach ($lab_tests as $test): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong></td>
                                <td><span class="item-type-badge"><?= htmlspecialchars($test['test_type'] ?? 'General') ?></span></td>
                                <td><?= date('M d, Y', strtotime($test['created_at'])) ?></td>
                                <td><?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($test['sample_type'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($test['status'] === 'completed' && !empty($test['results'])): ?>
                                        <span class="font-mono" style="color:var(--success);"><?= htmlspecialchars($test['results']) ?></span>
                                    <?php elseif ($test['status'] === 'completed'): ?>
                                        <span class="text-green-600">Results available</span>
                                    <?php else: ?>
                                        <span class="text-gray-400">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="text-xs text-gray-500"><?= htmlspecialchars($test['reference_range'] ?? 'N/A') ?></span></td>
                                <td><span class="status-badge <?= $test['status'] === 'completed' ? 'badge-success' : 'badge-pending' ?>"><?= ucfirst($test['status'] ?? 'Pending') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-flask"></i>
                <p>No lab tests recorded</p>
                <p class="sub-text">Lab tests will appear here once requested</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 3. PRESCRIPTION HISTORY -->
    <!-- ================================================================ -->
    <div class="history-card">
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
            <div class="empty-state">
                <i class="fas fa-prescription"></i>
                <p>No prescriptions recorded</p>
                <p class="sub-text">Prescriptions will appear here once prescribed</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 4. PROCEDURE HISTORY -->
    <!-- ================================================================ -->
    <div class="history-card">
        <h3 class="card-title">
            <i class="fas fa-syringe title-red"></i> Procedure History
            <span class="text-sm font-normal text-gray-400">(<?= count($procedures) ?> procedures)</span>
        </h3>
        
        <?php if (count($procedures) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Procedure Name</th><th>Date</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php $i = 1; foreach ($procedures as $proc): 
                            $is_paid = ($proc['payment_status'] ?? 'pending') === 'paid';
                        ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($proc['item_name'] ?? 'N/A') ?></strong></td>
                                <td><?= date('M d, Y', strtotime($proc['created_at'])) ?></td>
                                <td><?= $proc['quantity'] ?? 1 ?></td>
                                <td>TSh <?= number_format($proc['unit_price'] ?? 0, 0) ?></td>
                                <td class="font-mono">TSh <?= number_format($proc['total_price'] ?? 0, 0) ?></td>
                                <td><span class="status-badge <?= $is_paid ? 'badge-success' : 'badge-pending' ?>"><?= ucfirst($proc['payment_status'] ?? 'Pending') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-syringe"></i>
                <p>No procedures recorded</p>
                <p class="sub-text">Procedures will appear here once performed</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 5. TOOLS HISTORY -->
    <!-- ================================================================ -->
    <div class="history-card">
        <h3 class="card-title">
            <i class="fas fa-tools title-orange"></i> Tools History
            <span class="text-sm font-normal text-gray-400">(<?= count($tools) ?> tools)</span>
        </h3>
        
        <?php if (count($tools) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Tool Name</th><th>Type</th><th>Date</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php $i = 1; foreach ($tools as $tool): 
                            $is_paid = ($tool['payment_status'] ?? 'pending') === 'paid';
                        ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($tool['item_name'] ?? 'N/A') ?></strong></td>
                                <td><span class="item-type-badge"><?= htmlspecialchars($tool['item_type'] ?? 'Tool') ?></span></td>
                                <td><?= date('M d, Y', strtotime($tool['created_at'])) ?></td>
                                <td><?= $tool['quantity'] ?? 1 ?></td>
                                <td>TSh <?= number_format($tool['unit_price'] ?? 0, 0) ?></td>
                                <td class="font-mono">TSh <?= number_format($tool['total_price'] ?? 0, 0) ?></td>
                                <td><span class="status-badge <?= $is_paid ? 'badge-success' : 'badge-pending' ?>"><?= ucfirst($tool['payment_status'] ?? 'Pending') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-tools"></i>
                <p>No tools recorded</p>
                <p class="sub-text">Tools will appear here once used</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 6. BILL HISTORY -->
    <!-- ================================================================ -->
    <div class="history-card">
        <h3 class="card-title">
            <i class="fas fa-receipt title-teal"></i> Bill History
            <span class="text-sm font-normal text-gray-400">(<?= count($bills) ?> bills)</span>
        </h3>
        
        <?php if (count($bills) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Bill Number</th><th>Date</th><th>Items</th><th>Total Amount</th><th>Paid</th><th>Balance</th><th>Status</th><th>Created By</th></tr></thead>
                    <tbody>
                        <?php $i = 1; foreach ($bills as $bill): 
                            $items = $bill_items[$bill['id']] ?? [];
                            $total_items = count($items);
                        ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td class="font-mono"><strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong></td>
                                <td><?= date('M d, Y', strtotime($bill['created_at'])) ?></td>
                                <td>
                                    <span class="badge badge-info"><?= $total_items ?> items</span>
                                    <?php if ($total_items > 0): ?>
                                        <button class="btn btn-sm btn-outline" onclick="toggleItems(<?= $bill['id'] ?>)" style="font-size:0.6rem; padding:1px 8px; margin-left:4px;">
                                            <i class="fas fa-chevron-down" id="arrow_<?= $bill['id'] ?>"></i> View
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td class="font-mono">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                                <td class="font-mono text-green-600">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                <td class="font-mono <?= ($bill['balance'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' ?>">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></td>
                                <td><span class="status-badge <?= getStatusBadgeClass($bill['status'] ?? 'pending') ?>"><?= ucfirst($bill['status'] ?? 'Pending') ?></span></td>
                                <td><?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?></td>
                            </tr>
                            <?php if ($total_items > 0): ?>
                                <tr id="items_<?= $bill['id'] ?>" style="display:none; background: var(--bg-body);">
                                    <td colspan="9" style="padding: 4px 12px;">
                                        <table class="bill-items-subtable">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Item Name</th>
                                                    <th>Type</th>
                                                    <th>Qty</th>
                                                    <th>Unit Price</th>
                                                    <th>Total</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $j = 1; foreach ($items as $item): ?>
                                                    <tr>
                                                        <td><?= $j++ ?></td>
                                                        <td><strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong></td>
                                                        <td><span class="item-type-badge"><?= htmlspecialchars($item['item_type'] ?? 'Other') ?></span></td>
                                                        <td><?= $item['quantity'] ?? 1 ?></td>
                                                        <td>TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                                        <td class="item-total">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                                        <td>
                                                            <span class="<?= ($item['payment_status'] ?? 'pending') === 'paid' ? 'badge-item-paid' : 'badge-item-pending' ?>">
                                                                <?= ucfirst($item['payment_status'] ?? 'Pending') ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>No bills recorded</p>
                <p class="sub-text">Bills will appear here once generated</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- APPOINTMENTS -->
    <!-- ================================================================ -->
    <div class="history-card">
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
            <div class="empty-state">
                <i class="fas fa-calendar-check"></i>
                <p>No appointments found</p>
                <p class="sub-text">Appointments will appear here once scheduled</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="separator">|</span>
            Patient History - <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>
            <span class="separator">|</span>
            <?= count($visits) ?> Visits | <?= count($diagnoses) ?> Diagnoses | <?= count($prescriptions) ?> Prescriptions | <?= count($lab_tests) ?> Lab Tests | <?= count($procedures) ?> Procedures | <?= count($tools) ?> Tools | <?= count($bills) ?> Bills
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
    // TOGGLE BILL ITEMS
    // ================================================================
    function toggleItems(billId) {
        var itemsRow = document.getElementById('items_' + billId);
        var arrow = document.getElementById('arrow_' + billId);
        if (itemsRow) {
            if (itemsRow.style.display === 'none') {
                itemsRow.style.display = 'table-row';
                if (arrow) {
                    arrow.className = 'fas fa-chevron-up';
                }
            } else {
                itemsRow.style.display = 'none';
                if (arrow) {
                    arrow.className = 'fas fa-chevron-down';
                }
            }
        }
    }

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

    // ================================================================
    // DARK MODE - Controlled by header
    // ================================================================
    
    console.log('%c👤 Patient History - <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Patient ID: <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>', 'font-size:12px; color:#059669;');
    console.log('%c💍 Marital Status: <?= htmlspecialchars($patient['marital_status'] ?? 'N/A') ?>', 'font-size:12px; color:#7C3AED;');
    console.log('%c📊 Total Visits: <?= count($visits) ?>', 'font-size:12px; color:#64748B;');
    console.log('%c🔄 Last Visit: <?= $last_visit ? date('M d, Y', strtotime($last_visit['created_at'])) : 'N/A' ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c🩺 Diagnoses: <?= count($diagnoses) ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c💊 Prescriptions: <?= count($prescriptions) ?>', 'font-size:12px; color:#7C3AED;');
    console.log('%c🧪 Lab Tests: <?= count($lab_tests) ?> (FIXED - JOIN with visits)', 'font-size:12px; color:#D97706;');
    console.log('%c💉 Procedures: <?= count($procedures) ?>', 'font-size:12px; color:#DC2626;');
    console.log('%c🔧 Tools: <?= count($tools) ?>', 'font-size:12px; color:#D97706;');
    console.log('%c💰 Bills: <?= count($bills) ?> (Showing all items per bill)', 'font-size:12px; color:#0D9488;');
    console.log('%c✅ Lab Tests fixed - JOIN visits ON visit_id to get patient_id', 'font-size:11px; color:#34D399;');
    console.log('%c✅ Blue Theme applied', 'font-size:11px; color:#0B5ED7;');
</script>

</body>
</html>