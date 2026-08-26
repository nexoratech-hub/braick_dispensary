<?php
// ================================================================
// FILE: frontend/pages/doctor/get_patient_visits.php
// AJAX - Get visits for a specific patient (DOCTOR)
// USING NEW DATABASE: dispensary_db
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// HEADERS - Return JSON
// ================================================================
header('Content-Type: application/json');

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT DOCTOR
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized - Please login first',
        'redirect' => '/dispensary_system/frontend/pages/login.php'
    ]);
    exit;
}

// ================================================================
// GET DOCTOR DATA FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. Unknown';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// GET PATIENT ID
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'active';

if ($patient_id <= 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid patient ID'
    ]);
    exit;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
$db_path = 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Database configuration not found'
    ]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// ================================================================
// VERIFY DOCTOR EXISTS AND IS ACTIVE
// ================================================================
try {
    $stmt = $db->prepare("SELECT id, full_name, branch_id, status FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor_data || $doctor_data['status'] !== 'active') {
        session_destroy();
        echo json_encode([
            'success' => false,
            'message' => 'Doctor account inactive',
            'redirect' => '/dispensary_system/frontend/pages/login.php'
        ]);
        exit;
    }
    
    $doctor_name = $doctor_data['full_name'];
    $_SESSION['full_name'] = $doctor_name;
    
} catch (Exception $e) {
    error_log("get_patient_visits verification error: " . $e->getMessage());
}

// ================================================================
// VERIFY PATIENT BELONGS TO THIS DOCTOR
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE patient_id = ? AND doctor_id = ?
    ");
    $stmt->execute([$patient_id, $doctor_id]);
    $patient_check = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (($patient_check['count'] ?? 0) == 0) {
        // Check if patient exists at all
        $stmt = $db->prepare("SELECT id, full_name FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) {
            echo json_encode([
                'success' => false,
                'message' => 'Patient not found'
            ]);
            exit;
        }
        
        // Patient exists but not assigned to this doctor
        echo json_encode([
            'success' => false,
            'message' => 'This patient is not assigned to you',
            'patient_name' => $patient['full_name'] ?? 'Unknown'
        ]);
        exit;
    }
} catch (Exception $e) {
    error_log("get_patient_visits patient verification error: " . $e->getMessage());
}

// ================================================================
// BUILD STATUS CONDITION
// ================================================================
$status_condition = "";
$status_params = [];

if ($status_filter === 'active') {
    $status_condition = "AND v.status IN ('pending', 'assigned', 'with_doctor', 'lab_test', 'prescribed')";
} elseif ($status_filter === 'completed') {
    $status_condition = "AND v.status = 'completed' AND v.is_completed = 1";
} elseif ($status_filter === 'cancelled') {
    $status_condition = "AND v.status = 'cancelled'";
} elseif ($status_filter === 'all') {
    $status_condition = "";
} else {
    $status_condition = "AND v.status IN ('pending', 'assigned', 'with_doctor', 'lab_test', 'prescribed')";
}

// ================================================================
// GET VISITS FOR THIS PATIENT - USING NEW DATABASE
// ================================================================
try {
    $sql = "
        SELECT 
            v.id, 
            v.visit_number, 
            v.visit_type,
            v.diagnosis, 
            v.treatment,
            v.symptoms,
            v.complaint,
            v.hpi,
            v.physical_exam,
            v.created_at,
            v.updated_at,
            v.status,
            v.is_completed,
            v.completed_at,
            v.visit_total,
            v.payment_status,
            v.consultation_fee,
            v.lab_fees_total,
            v.pharmacy_fees_total,
            v.other_fees_total,
            v.total_discount,
            v.discount_percent,
            v.follow_up_date,
            v.is_referred,
            p.id as patient_id,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone,
            p.gender,
            p.date_of_birth,
            p.blood_group,
            p.allergies,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            b.name as branch_name,
            (SELECT COUNT(*) FROM lab_tests WHERE visit_id = v.id AND status IN ('pending', 'in_progress')) as pending_lab_count,
            (SELECT COUNT(*) FROM lab_tests WHERE visit_id = v.id AND status = 'completed') as completed_lab_count,
            (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id AND status = 'pending') as pending_prescriptions,
            (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id AND status = 'dispensed') as dispensed_prescriptions,
            (SELECT COUNT(*) FROM bills WHERE visit_id = v.id AND status IN ('pending', 'partial')) as pending_bills,
            (SELECT COUNT(*) FROM bills WHERE visit_id = v.id AND status = 'paid') as paid_bills,
            (SELECT COUNT(*) FROM bills WHERE visit_id = v.id) as total_bills,
            (SELECT COALESCE(SUM(total_amount), 0) FROM bills WHERE visit_id = v.id) as total_bill_amount,
            (SELECT COALESCE(SUM(paid_amount), 0) FROM bills WHERE visit_id = v.id) as total_paid_amount,
            (SELECT COALESCE(SUM(balance), 0) FROM bills WHERE visit_id = v.id) as total_balance
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN branches b ON v.branch_id = b.id
        WHERE v.patient_id = ? 
        AND v.doctor_id = ?
        $status_condition
        ORDER BY v.created_at DESC
        LIMIT ?
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$patient_id, $doctor_id, $limit]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // IF NO VISITS FOUND, GET BASIC INFO
    // ================================================================
    if (empty($visits)) {
        // Get patient info
        $stmt = $db->prepare("SELECT full_name, patient_id, phone, gender, date_of_birth, blood_group, allergies FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'visits' => [],
            'count' => 0,
            'patient_id' => $patient_id,
            'patient_info' => $patient_info,
            'patient_name' => $patient_info['full_name'] ?? 'Unknown',
            'patient_code' => $patient_info['patient_id'] ?? 'N/A',
            'phone' => $patient_info['phone'] ?? 'N/A',
            'gender' => $patient_info['gender'] ?? 'N/A',
            'date_of_birth' => $patient_info['date_of_birth'] ?? null,
            'blood_group' => $patient_info['blood_group'] ?? 'N/A',
            'allergies' => $patient_info['allergies'] ?? 'None',
            'message' => 'No visits found for this patient',
            'status_filter' => $status_filter,
            'summary' => [
                'total_visits' => 0,
                'pending_visits' => 0,
                'completed_visits' => 0,
                'cancelled_visits' => 0,
                'lab_tests_pending' => 0,
                'lab_tests_completed' => 0,
                'prescriptions_pending' => 0,
                'prescriptions_dispensed' => 0,
                'bills_pending' => 0,
                'bills_paid' => 0,
                'total_bill_amount' => 0,
                'total_paid_amount' => 0,
                'total_balance' => 0
            ]
        ]);
        exit;
    }
    
    // ================================================================
    // GET PATIENT SUMMARY
    // ================================================================
    $patient_summary = [
        'total_visits' => count($visits),
        'pending_visits' => 0,
        'completed_visits' => 0,
        'cancelled_visits' => 0,
        'lab_tests_pending' => 0,
        'lab_tests_completed' => 0,
        'prescriptions_pending' => 0,
        'prescriptions_dispensed' => 0,
        'bills_pending' => 0,
        'bills_paid' => 0,
        'total_bill_amount' => 0,
        'total_paid_amount' => 0,
        'total_balance' => 0
    ];
    
    foreach ($visits as $visit) {
        if ($visit['status'] === 'completed' && $visit['is_completed'] == 1) {
            $patient_summary['completed_visits']++;
        } elseif ($visit['status'] === 'cancelled') {
            $patient_summary['cancelled_visits']++;
        } else {
            $patient_summary['pending_visits']++;
        }
        
        $patient_summary['lab_tests_pending'] += (int)($visit['pending_lab_count'] ?? 0);
        $patient_summary['lab_tests_completed'] += (int)($visit['completed_lab_count'] ?? 0);
        $patient_summary['prescriptions_pending'] += (int)($visit['pending_prescriptions'] ?? 0);
        $patient_summary['prescriptions_dispensed'] += (int)($visit['dispensed_prescriptions'] ?? 0);
        $patient_summary['bills_pending'] += (int)($visit['pending_bills'] ?? 0);
        $patient_summary['bills_paid'] += (int)($visit['paid_bills'] ?? 0);
        $patient_summary['total_bill_amount'] += (float)($visit['total_bill_amount'] ?? 0);
        $patient_summary['total_paid_amount'] += (float)($visit['total_paid_amount'] ?? 0);
        $patient_summary['total_balance'] += (float)($visit['total_balance'] ?? 0);
    }
    
    // ================================================================
    // GET PATIENT INFO
    // ================================================================
    $stmt = $db->prepare("SELECT full_name, patient_id, phone, gender, date_of_birth, blood_group, allergies, address, email FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate age
    $age = 'N/A';
    if (!empty($patient_info['date_of_birth']) && $patient_info['date_of_birth'] !== '0000-00-00') {
        $birthDate = new DateTime($patient_info['date_of_birth']);
        $today = new DateTime('today');
        $age = $birthDate->diff($today)->y;
    }
    
    // ================================================================
    // RETURN JSON
    // ================================================================
    echo json_encode([
        'success' => true,
        'visits' => $visits,
        'count' => count($visits),
        'patient_id' => $patient_id,
        'patient_info' => $patient_info,
        'patient_name' => $patient_info['full_name'] ?? 'Unknown',
        'patient_code' => $patient_info['patient_id'] ?? 'N/A',
        'phone' => $patient_info['phone'] ?? 'N/A',
        'gender' => $patient_info['gender'] ?? 'N/A',
        'date_of_birth' => $patient_info['date_of_birth'] ?? null,
        'age' => $age,
        'blood_group' => $patient_info['blood_group'] ?? 'N/A',
        'allergies' => $patient_info['allergies'] ?? 'None',
        'address' => $patient_info['address'] ?? 'N/A',
        'email' => $patient_info['email'] ?? 'N/A',
        'summary' => $patient_summary,
        'status_filter' => $status_filter,
        'doctor_name' => $doctor_name,
        'doctor_id' => $doctor_id,
        'branch_id' => $doctor_branch_id,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("get_patient_visits query error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
exit;