<?php
// ================================================================
// FILE: frontend/pages/doctor/refer_patient.php
// DOCTOR - REFER PATIENT (TWO-STEP) 
// WITH referrals TABLE - AUTO CREATE IF NOT EXISTS
// STATUS: referred (FIXED - NOT NULL WITH DEFAULT)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT DOCTOR
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// GET DOCTOR DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Dr. Unknown';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_online = $_SESSION['is_online'] ?? 0;
$user_phone = $_SESSION['phone'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// CHECK IF referrals TABLE EXISTS - CREATE IF NOT
// ALSO FIX STATUS COLUMN TO HAVE DEFAULT 'referred'
// ================================================================
try {
    // Check if table exists
    $stmt = $db->prepare("SHOW TABLES LIKE 'referrals'");
    $stmt->execute();
    $table_exists = $stmt->rowCount() > 0;
    
    if (!$table_exists) {
        // Create table with status default 'referred'
        $create_sql = "
            CREATE TABLE IF NOT EXISTS `referrals` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `referral_number` varchar(50) NOT NULL,
              `visit_id` int(11) DEFAULT NULL,
              `patient_id` int(11) NOT NULL,
              `from_doctor_id` int(11) NOT NULL,
              `referral_type` enum('internal','external') NOT NULL DEFAULT 'internal',
              `to_doctor_id` int(11) DEFAULT NULL,
              `to_hospital_name` varchar(255) DEFAULT NULL,
              `to_hospital_address` text DEFAULT NULL,
              `to_hospital_phone` varchar(20) DEFAULT NULL,
              `to_hospital_email` varchar(100) DEFAULT NULL,
              `reason` text NOT NULL,
              `clinical_notes` text DEFAULT NULL,
              `diagnosis` text DEFAULT NULL,
              `treatment_given` text DEFAULT NULL,
              `expert_type` varchar(100) DEFAULT NULL,
              `urgency` enum('routine','urgent','emergency') DEFAULT 'routine',
              `status` enum('pending','referred','accepted','rejected','completed','cancelled') NOT NULL DEFAULT 'referred',
              `notes` text DEFAULT NULL,
              `internal_notes` text DEFAULT NULL,
              `external_notes` text DEFAULT NULL,
              `referral_date` datetime NOT NULL,
              `created_by` int(11) DEFAULT NULL,
              `branch_id` int(11) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              `accepted_at` timestamp NULL DEFAULT NULL,
              `completed_at` timestamp NULL DEFAULT NULL,
              `cancelled_at` timestamp NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `referral_number` (`referral_number`),
              KEY `visit_id` (`visit_id`),
              KEY `patient_id` (`patient_id`),
              KEY `from_doctor_id` (`from_doctor_id`),
              KEY `to_doctor_id` (`to_doctor_id`),
              KEY `branch_id` (`branch_id`),
              KEY `idx_referrals_status` (`status`),
              KEY `idx_referrals_date` (`referral_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($create_sql);
    } else {
        // ================================================================
        // FIX EXISTING TABLE - ADD 'pending' TO ENUM AND SET DEFAULT
        // ================================================================
        try {
            // First, check if 'pending' is already in the enum
            $stmt = $db->prepare("SHOW COLUMNS FROM referrals LIKE 'status'");
            $stmt->execute();
            $column_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($column_info) {
                $enum_values = $column_info['Type'] ?? '';
                // Check if 'pending' is in the enum
                if (strpos($enum_values, "'pending'") === false) {
                    // Add 'pending' to enum and set default to 'referred'
                    $alter_sql = "
                        ALTER TABLE `referrals` 
                        MODIFY COLUMN `status` enum('pending','referred','accepted','rejected','completed','cancelled') 
                        NOT NULL DEFAULT 'referred'
                    ";
                    $db->exec($alter_sql);
                } else {
                    // Just set default to 'referred'
                    $alter_sql = "
                        ALTER TABLE `referrals` 
                        MODIFY COLUMN `status` enum('pending','referred','accepted','rejected','completed','cancelled') 
                        NOT NULL DEFAULT 'referred'
                    ";
                    $db->exec($alter_sql);
                }
                
                // Update any NULL status to 'referred'
                $update_sql = "UPDATE `referrals` SET `status` = 'referred' WHERE `status` IS NULL OR `status` = ''";
                $db->exec($update_sql);
            }
        } catch (Exception $e) {
            error_log("Status column fix error: " . $e->getMessage());
        }
    }
} catch (Exception $e) {
    error_log("Table check error: " . $e->getMessage());
}

// ================================================================
// GET ADMIN INFO
// ================================================================
$admin_phone = '';
$admin_email = '';
$admin_name = '';
try {
    $stmt = $db->prepare("SELECT full_name, phone, email FROM users WHERE role = 'admin' AND branch_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$user_branch_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        $admin_phone = $admin['phone'] ?? '';
        $admin_email = $admin['email'] ?? '';
        $admin_name = $admin['full_name'] ?? 'Admin';
    }
} catch (Exception $e) {
    $admin_phone = '';
    $admin_email = '';
    $admin_name = 'Admin';
}

$message = '';
$message_type = '';
$error_message = '';

// ================================================================
// VERIFY DOCTOR
// ================================================================
try {
    $stmt = $db->prepare("SELECT id, full_name, branch_id, specialty, profile_pic, status, is_online, phone FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$user_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor_data || $doctor_data['status'] !== 'active') {
        session_destroy();
        header('Location: /dispensary_system/frontend/pages/login.php');
        exit;
    }
    
    $user_full_name = $doctor_data['full_name'];
    $user_branch_id = $doctor_data['branch_id'] ?? 1;
    $user_specialty = $doctor_data['specialty'] ?? 'General Medicine';
    $profile_pic = $doctor_data['profile_pic'] ?? '';
    $is_online = $doctor_data['is_online'] ?? 0;
    $user_phone = $doctor_data['phone'] ?? '';
    
    $_SESSION['full_name'] = $user_full_name;
    $_SESSION['branch_id'] = $user_branch_id;
    $_SESSION['specialty'] = $user_specialty;
    $_SESSION['profile_pic'] = $profile_pic;
    $_SESSION['is_online'] = $is_online;
    $_SESSION['phone'] = $user_phone;
    
} catch (Exception $e) {
    error_log("refer_patient verification error: " . $e->getMessage());
}

// ================================================================
// GET PATIENT ID
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;

// ================================================================
// GET ALL PATIENTS
// ================================================================
$patients_list = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT p.id, p.full_name, p.patient_id, p.gender, p.phone, p.date_of_birth, p.emergency_contact
        FROM patients p
        JOIN visits v ON p.id = v.patient_id
        WHERE v.doctor_id = ?
        ORDER BY p.full_name ASC
    ");
    $stmt->execute([$user_id]);
    $patients_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patients_list = [];
}

// ================================================================
// GET SELECTED PATIENT DETAILS
// ================================================================
$patient = null;
$last_visit = null;
$medications = [];
$procedures = [];
$diagnosis = '';
$treatment = '';
$disease_code = '';
$disease_name = '';
$symptoms = '';
$hpi = '';
$physical_exam = '';
$vital_signs = null;
$lab_tests = [];
$procedure_equipment_list = [];

if ($patient_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) {
            $error_message = '❌ Patient not found.';
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE patient_id = ? AND doctor_id = ?");
            $stmt->execute([$patient_id, $user_id]);
            $visit_check = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (($visit_check['count'] ?? 0) == 0) {
                if ($patient['assigned_doctor_id'] != $user_id) {
                    $patient = null;
                    $error_message = '❌ This patient is not assigned to you.';
                }
            }
        }
        
        if ($patient) {
            $stmt = $db->prepare("
                SELECT v.id, v.visit_number, v.diagnosis, v.symptoms, v.hpi, v.physical_exam, 
                       v.treatment, v.disease_code, v.created_at, v.status, v.consultation_fee,
                       v.disease_id, v.complaint, v.notes
                FROM visits v
                WHERE v.patient_id = ? AND v.doctor_id = ?
                ORDER BY v.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$patient_id, $user_id]);
            $visit_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$visit_info) {
                $stmt = $db->prepare("
                    SELECT v.id, v.visit_number, v.diagnosis, v.symptoms, v.hpi, v.physical_exam,
                           v.treatment, v.disease_code, v.created_at, v.status, v.consultation_fee,
                           v.disease_id, v.complaint, v.notes
                    FROM visits v
                    WHERE v.patient_id = ?
                    ORDER BY v.created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$patient_id]);
                $visit_info = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            if ($visit_info) {
                $patient['visit_id'] = $visit_info['id'] ?? null;
                $patient['visit_number'] = $visit_info['visit_number'] ?? null;
                $patient['diagnosis'] = $visit_info['diagnosis'] ?? null;
                $patient['symptoms'] = $visit_info['symptoms'] ?? null;
                $patient['hpi'] = $visit_info['hpi'] ?? null;
                $patient['physical_exam'] = $visit_info['physical_exam'] ?? null;
                $patient['treatment'] = $visit_info['treatment'] ?? null;
                $patient['disease_code'] = $visit_info['disease_code'] ?? null;
                $patient['consultation_fee'] = $visit_info['consultation_fee'] ?? 0;
                $patient['last_visit_date'] = $visit_info['created_at'] ?? null;
                $patient['last_visit_status'] = $visit_info['status'] ?? null;
                $patient['disease_id'] = $visit_info['disease_id'] ?? null;
                $patient['complaint'] = $visit_info['complaint'] ?? null;
                $patient['notes'] = $visit_info['notes'] ?? null;
                $last_visit = $visit_info;
                $diagnosis = $visit_info['diagnosis'] ?? '';
                $treatment = $visit_info['treatment'] ?? '';
                $disease_code = $visit_info['disease_code'] ?? '';
                $symptoms = $visit_info['symptoms'] ?? '';
                $hpi = $visit_info['hpi'] ?? '';
                $physical_exam = $visit_info['physical_exam'] ?? '';
                
                if (!empty($visit_info['disease_id'])) {
                    $stmt_disease = $db->prepare("SELECT disease_name FROM diseases WHERE id = ?");
                    $stmt_disease->execute([$visit_info['disease_id']]);
                    $disease = $stmt_disease->fetch(PDO::FETCH_ASSOC);
                    if ($disease) {
                        $disease_name = $disease['disease_name'];
                    }
                }
                
                if (empty($disease_name) && !empty($diagnosis)) {
                    $disease_name = $diagnosis;
                }
                
                $stmt = $db->prepare("
                    SELECT pi.medication_name, pi.dosage, pi.frequency, pi.quantity, pi.instructions, pi.total_price,
                           pi.duration, pi.route, pi.unit_price, pi.created_at
                    FROM prescriptions p
                    JOIN prescription_items pi ON p.id = pi.prescription_id
                    WHERE p.visit_id = ?
                ");
                $stmt->execute([$visit_info['id']]);
                $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("
                    SELECT bi.id, bi.item_name, bi.quantity, bi.total_price, bi.item_type,
                           bi.created_at, bi.unit_price, bi.description
                    FROM bill_items bi
                    JOIN bills b ON bi.bill_id = b.id
                    WHERE b.visit_id = ?
                    AND bi.item_type = 'procedure'
                    AND bi.status != 'cancelled'
                    ORDER BY bi.created_at DESC
                ");
                $stmt->execute([$visit_info['id']]);
                $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("
                    SELECT p.id, p.procedure_id, p.procedure_name, p.status,
                           pc.required_equipment_id, pc.equipment_quantity_used,
                           me.equipment_name, me.batch_number, me.quantity as equipment_stock
                    FROM procedures p
                    LEFT JOIN procedures_catalog pc ON p.procedure_id = pc.id
                    LEFT JOIN medical_equipment me ON pc.required_equipment_id = me.id
                    WHERE p.visit_id = ? AND p.status != 'cancelled'
                    ORDER BY p.created_at DESC
                ");
                $stmt->execute([$visit_info['id']]);
                $procedure_equipment_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("
                    SELECT lt.test_name, lt.results, lt.status, lt.test_price, lt.created_at,
                           u.full_name as lab_technician_name
                    FROM lab_tests lt
                    LEFT JOIN users u ON lt.lab_technician_id = u.id
                    WHERE lt.visit_id = ?
                    ORDER BY lt.created_at DESC
                ");
                $stmt->execute([$visit_info['id']]);
                $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("
                    SELECT temperature, blood_pressure_systolic, blood_pressure_diastolic,
                           pulse_rate, weight, height, bmi, notes, recorded_at,
                           u.full_name as recorded_by_name
                    FROM vital_signs vs
                    LEFT JOIN users u ON vs.recorded_by = u.id
                    WHERE vs.visit_id = ?
                    ORDER BY vs.recorded_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([$visit_info['id']]);
                $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        error_log("Patient fetch error: " . $e->getMessage());
        $patient = null;
        $error_message = '❌ Database error occurred.';
    }
}

// ================================================================
// GET DOCTORS LIST WITH PATIENT COUNTS AND PENDING VISITS
// ================================================================
$doctors = [];
$online_count = 0;
$offline_count = 0;
try {
    $stmt = $db->prepare("
        SELECT 
            u.id, 
            u.full_name, 
            u.specialty, 
            u.phone, 
            u.email, 
            u.is_online, 
            u.last_online, 
            u.profile_pic,
            COUNT(DISTINCT p.id) as total_patients,
            COUNT(DISTINCT CASE 
                WHEN v.status IN ('pending', 'assigned', 'with_doctor', 'lab_test', 'prescribed') 
                THEN v.patient_id 
                ELSE NULL 
            END) as pending_patients,
            SUM(CASE 
                WHEN v.status IN ('pending', 'assigned', 'with_doctor', 'lab_test', 'prescribed') 
                THEN 1 
                ELSE 0 
            END) as pending_visits
        FROM users u
        LEFT JOIN patients p ON p.assigned_doctor_id = u.id
        LEFT JOIN visits v ON v.doctor_id = u.id AND v.status NOT IN ('completed', 'cancelled')
        WHERE u.role = 'doctor' 
        AND u.id != ? 
        AND u.branch_id = ?
        AND u.status = 'active'
        GROUP BY u.id
        ORDER BY u.is_online DESC, pending_visits ASC, u.full_name ASC
    ");
    $stmt->execute([$user_id, $user_branch_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($doctors as $doctor) {
        if ($doctor['is_online'] == 1) {
            $online_count++;
        } else {
            $offline_count++;
        }
    }
} catch (Exception $e) {
    $doctors = [];
    error_log("Doctor list error: " . $e->getMessage());
}

$specialties = [
    'General Medicine', 'Cardiology', 'Dermatology', 'Endocrinology',
    'Gastroenterology', 'Hematology', 'Infectious Diseases', 'Nephrology',
    'Neurology', 'Obstetrics & Gynecology', 'Oncology', 'Ophthalmology',
    'Orthopedics', 'Otolaryngology (ENT)', 'Pediatrics', 'Psychiatry',
    'Pulmonology', 'Radiology', 'Rheumatology', 'Surgery', 'Urology'
];

$expert_types = [
    'Cardiology Expert', 'Dermatology Expert', 'Endocrinology Expert',
    'Gastroenterology Expert', 'Hematology Expert', 'Infectious Diseases Expert',
    'Nephrology Expert', 'Neurology Expert', 'Obstetrics & Gynecology Expert',
    'Oncology Expert', 'Ophthalmology Expert', 'Orthopedics Expert',
    'Otolaryngology (ENT) Expert', 'Pediatrics Expert', 'Psychiatry Expert',
    'Pulmonology Expert', 'Radiology Expert', 'Rheumatology Expert',
    'Surgery Expert', 'Urology Expert', 'General Medicine Expert',
    'Emergency Medicine Expert', 'Intensive Care Expert', 'Nutrition Expert',
    'Physiotherapy Expert', 'Other (Specify)'
];

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_referral') {
        $referral_type = $_POST['referral_type'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        $referral_reason = trim($_POST['referral_reason'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $patient_id_post = (int)($_POST['patient_id'] ?? 0);
        $visit_id_post = isset($_POST['visit_id']) ? (int)$_POST['visit_id'] : 0;
        $urgency = $_POST['urgency'] ?? 'routine';
        $referred_to_doctor = isset($_POST['referred_to_doctor']) ? (int)$_POST['referred_to_doctor'] : 0;
        $internal_notes = trim($_POST['internal_notes'] ?? '');
        $external_facility = trim($_POST['external_facility'] ?? '');
        $external_address = trim($_POST['external_address'] ?? '');
        $external_phone = trim($_POST['external_phone'] ?? '');
        $external_email = trim($_POST['external_email'] ?? '');
        $expert_type = trim($_POST['expert_type'] ?? '');
        $expert_type_other = trim($_POST['expert_type_other'] ?? '');
        $clinical_summary = trim($_POST['clinical_summary'] ?? '');
        $external_notes = trim($_POST['external_notes'] ?? '');
        
        if ($expert_type === 'Other (Specify)' && !empty($expert_type_other)) {
            $expert_type = $expert_type_other;
        }
        
        $errors = [];
        if ($patient_id_post <= 0) {
            $errors[] = "Please select a patient";
        }
        if (empty($referral_type)) {
            $errors[] = "Please select referral type";
        }
        
        if ($referral_type === 'internal') {
            if (empty($reason)) {
                $errors[] = "Please enter reason for referral";
            }
            if ($referred_to_doctor <= 0) {
                $errors[] = "Please select a doctor";
            }
        } elseif ($referral_type === 'external') {
            if (empty($referral_reason)) {
                $errors[] = "Please enter reason for referral";
            }
            if (empty($external_facility)) {
                $errors[] = "Please enter facility name";
            }
        }
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                $referral_number = 'REF-' . date('Ymd') . '-' . str_pad($patient_id_post, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                
                $visit_id_to_use = null;
                
                if ($visit_id_post > 0) {
                    $visit_id_to_use = $visit_id_post;
                }
                
                if ($visit_id_to_use === null && $patient_id_post > 0) {
                    $stmt = $db->prepare("SELECT id FROM visits WHERE patient_id = ? AND doctor_id = ? ORDER BY created_at DESC LIMIT 1");
                    $stmt->execute([$patient_id_post, $user_id]);
                    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($visit) {
                        $visit_id_to_use = $visit['id'];
                    }
                }
                
                if ($visit_id_to_use === null && $patient_id_post > 0) {
                    $stmt = $db->prepare("SELECT id FROM visits WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
                    $stmt->execute([$patient_id_post]);
                    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($visit) {
                        $visit_id_to_use = $visit['id'];
                    }
                }
                
                if ($visit_id_to_use === null && $patient_id_post > 0) {
                    $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $stmt = $db->prepare("
                        INSERT INTO visits (
                            visit_number, visit_date, patient_id, doctor_id,
                            branch_id, visit_type, status, created_at
                        ) VALUES (?, NOW(), ?, ?, ?, 'new', 'pending', NOW())
                    ");
                    $stmt->execute([$visit_number, $patient_id_post, $user_id, $user_branch_id]);
                    $visit_id_to_use = $db->lastInsertId();
                }
                
                // Build clinical notes
                $clinical_notes_final = $clinical_summary;
                if (!empty($diagnosis)) {
                    $clinical_notes_final .= "\n\n--- Diagnosis ---\n" . $diagnosis;
                }
                if (!empty($disease_code)) {
                    $clinical_notes_final .= "\nDisease Code: " . $disease_code;
                }
                if (!empty($treatment)) {
                    $clinical_notes_final .= "\n\n--- Treatment Given ---\n" . $treatment;
                }
                
                // ================================================================
                // FORCE STATUS TO 'referred' - EXPLICITLY SET
                // ================================================================
                $referral_status = 'referred';
                
                if ($referral_type === 'internal') {
                    // Get doctor name
                    $stmt = $db->prepare("SELECT full_name, phone, specialty FROM users WHERE id = ?");
                    $stmt->execute([$referred_to_doctor]);
                    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                    $doctor_name = $doctor['full_name'] ?? 'Unknown Doctor';
                    
                    $combined_reason = $reason;
                    if (!empty($internal_notes)) {
                        $combined_reason .= "\n\n--- Additional Notes from Referring Doctor ---\n" . $internal_notes;
                    }
                    
                    // ================================================================
                    // INTERNAL REFERRAL - EXPLICIT STATUS
                    // ================================================================
                    $stmt = $db->prepare("
                        INSERT INTO referrals (
                            referral_number, visit_id, patient_id, from_doctor_id,
                            referral_type, to_doctor_id,
                            reason, clinical_notes, diagnosis, treatment_given,
                            urgency, status, referral_date, created_by, branch_id, created_at
                        ) VALUES (
                            ?, ?, ?, ?,
                            ?, ?,
                            ?, ?, ?, ?,
                            ?, ?, NOW(), ?, ?, NOW()
                        )
                    ");
                    
                    $stmt->execute([
                        $referral_number,
                        $visit_id_to_use,
                        $patient_id_post,
                        $user_id,
                        $referral_type,
                        $referred_to_doctor,
                        $combined_reason,
                        $clinical_notes_final,
                        $diagnosis,
                        $treatment,
                        $urgency,
                        $referral_status,  // EXPLICIT: 'referred'
                        $user_id,
                        $user_branch_id
                    ]);
                    
                    // Create notification for receiving doctor
                    $stmt = $db->prepare("
                        INSERT INTO notifications (user_id, branch_id, patient_id, title, message, type, link, created_at)
                        VALUES (?, ?, ?, ?, ?, 'info', ?, NOW())
                    ");
                    $notif_message = "New referral from Dr. " . $user_full_name . " for patient " . ($patient['full_name'] ?? '');
                    $stmt->execute([
                        $referred_to_doctor,
                        $user_branch_id,
                        $patient_id_post,
                        "📋 New Referral Received",
                        $notif_message,
                        "my_patients.php"
                    ]);
                    
                } else {
                    // External referral
                    $combined_reason = $referral_reason;
                    if (!empty($external_notes)) {
                        $combined_reason .= "\n\n--- Additional Notes from Referring Doctor ---\n" . $external_notes;
                    }
                    
                    $clinical_notes_with_expert = $clinical_notes_final;
                    if (!empty($expert_type)) {
                        $clinical_notes_with_expert = "Expert Type: " . $expert_type . "\n\n" . $clinical_notes_final;
                    }
                    
                    // ================================================================
                    // EXTERNAL REFERRAL - EXPLICIT STATUS
                    // ================================================================
                    $stmt = $db->prepare("
                        INSERT INTO referrals (
                            referral_number, visit_id, patient_id, from_doctor_id,
                            referral_type, to_hospital_name, to_hospital_address, to_hospital_phone,
                            reason, clinical_notes, diagnosis, treatment_given,
                            urgency, status, referral_date, created_by, branch_id, created_at
                        ) VALUES (
                            ?, ?, ?, ?,
                            ?, ?, ?, ?,
                            ?, ?, ?, ?,
                            ?, ?, NOW(), ?, ?, NOW()
                        )
                    ");
                    
                    $stmt->execute([
                        $referral_number,
                        $visit_id_to_use,
                        $patient_id_post,
                        $user_id,
                        $referral_type,
                        $external_facility,
                        $external_address,
                        $external_phone,
                        $combined_reason,
                        $clinical_notes_with_expert,
                        $diagnosis,
                        $treatment,
                        $urgency,
                        $referral_status,  // EXPLICIT: 'referred'
                        $user_id,
                        $user_branch_id
                    ]);
                }
                
                $referral_id = $db->lastInsertId();
                
                // ================================================================
                // FORCE UPDATE STATUS AGAIN - DOUBLE CHECK
                // ================================================================
                $stmt_force = $db->prepare("UPDATE referrals SET status = 'referred' WHERE id = ?");
                $stmt_force->execute([$referral_id]);
                
                // ================================================================
                // VERIFY STATUS WAS SAVED CORRECTLY
                // ================================================================
                $stmt_verify = $db->prepare("SELECT status FROM referrals WHERE id = ?");
                $stmt_verify->execute([$referral_id]);
                $verify = $stmt_verify->fetch(PDO::FETCH_ASSOC);
                $saved_status = $verify['status'] ?? 'NULL';
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, patient_id, action, details, created_at) 
                    VALUES (?, ?, ?, 'referral_created', ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    $user_branch_id,
                    $patient_id_post,
                    "Patient referred: " . ($patient['full_name'] ?? '') . " (#$referral_number) - Type: $referral_type - Status: $saved_status"
                ]);
                
                $db->commit();
                
                $message = "✅ Patient referred successfully! Referral #: " . $referral_number . " (Status: " . $saved_status . ")";
                $message_type = 'success';
                
                echo '<script>
                    setTimeout(function(){
                        window.location.href = "my_patients.php?success=referral";
                    }, 2000);
                </script>';
                
            } catch (Exception $e) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
                error_log("Referral error: " . $e->getMessage());
            }
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
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

function getVitalStatus($value, $type) {
    if ($value === null || $value === '' || $value === '--') return ['label' => 'N/A', 'class' => 'unknown'];
    switch ($type) {
        case 'temperature':
            if ($value > 37.5) return ['label' => 'HIGH', 'class' => 'high'];
            if ($value < 36.0) return ['label' => 'LOW', 'class' => 'low'];
            return ['label' => 'NORMAL', 'class' => 'normal'];
        case 'systolic':
            if ($value > 140) return ['label' => 'HIGH', 'class' => 'high'];
            if ($value < 90) return ['label' => 'LOW', 'class' => 'low'];
            return ['label' => 'NORMAL', 'class' => 'normal'];
        case 'pulse':
            if ($value > 100) return ['label' => 'HIGH', 'class' => 'high'];
            if ($value < 60) return ['label' => 'LOW', 'class' => 'low'];
            return ['label' => 'NORMAL', 'class' => 'normal'];
        case 'bmi':
            if ($value >= 30) return ['label' => 'OBESE', 'class' => 'high'];
            if ($value >= 25) return ['label' => 'OVERWEIGHT', 'class' => 'high'];
            if ($value >= 18.5) return ['label' => 'NORMAL', 'class' => 'normal'];
            return ['label' => 'UNDERWEIGHT', 'class' => 'low'];
        default:
            return ['label' => 'N/A', 'class' => 'unknown'];
    }
}

$doctor_branch_name = 'Not Assigned';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$user_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $doctor_branch_name = $branch_data['name'];
    }
} catch (Exception $e) {
    $doctor_branch_name = 'Branch';
}

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refer Patient - Braick Dispensary</title>
    <link rel="icon" href="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --white: #FFFFFF;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.07);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.4);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 8px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .page-header-custom {
            background: var(--primary-gradient);
            border-radius: 14px;
            padding: 20px 28px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header-custom .page-title {
            color: white;
            font-size: 1.4rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .page-header-custom .page-title i { font-size: 1.6rem; opacity: 0.9; }
        .page-header-custom .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.78rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        .btn-outline-light:hover { background: rgba(255,255,255,0.25); transform: translateY(-2px); }
        
        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 16px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 3px 12px;
            border-radius: 16px;
            font-size: 0.65rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .btn-view-pdf {
            background: #DC2626;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-view-pdf:hover {
            background: #B91C1C;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(220, 38, 38, 0.3);
            color: white;
        }
        
        .card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            margin-bottom: 16px;
        }
        .card:hover { border-color: var(--primary); box-shadow: var(--shadow-md); }
        [data-theme="dark"] .card { background: #1E293B; border-color: #334155; }
        [data-theme="dark"] .card:hover { border-color: #6EA8FE; }
        
        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 10px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .title-blue { color: var(--primary); }
        .title-purple { color: #7C3AED; }
        .title-green { color: #059669; }
        .title-orange { color: #D97706; }
        .title-red { color: #DC2626; }
        
        .status-verified {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            background: #D1FAE5;
            color: #059669;
            border: 2px solid #059669;
            margin-left: auto;
        }
        .status-verified i { font-size: 0.7rem; }
        
        .form-label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 3px;
        }
        .form-control {
            width: 100%;
            padding: 6px 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        [data-theme="dark"] .form-control { background: #1E293B; border-color: #334155; color: #F1F5F9; }
        [data-theme="dark"] .form-control:focus { border-color: #6EA8FE; box-shadow: 0 0 0 3px rgba(110, 168, 254, 0.15); }
        
        textarea.form-control { resize: vertical; min-height: 60px; }
        select.form-control { appearance: auto; cursor: pointer; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
            min-height: 34px;
        }
        .btn-success { background: #059669; color: white; box-shadow: 0 2px 8px rgba(5,150,105,0.2); }
        .btn-success:hover { background: #047857; transform: translateY(-2px); box-shadow: 0 4px 16px rgba(5,150,105,0.3); }
        .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
        .btn-outline:hover { background: var(--gray-50); border-color: var(--primary); color: var(--primary); }
        .btn-primary { background: var(--primary); color: white; box-shadow: 0 2px 8px rgba(11,94,215,0.2); }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 16px rgba(11,94,215,0.3); }
        .btn-sm { padding: 4px 10px; font-size: 0.65rem; min-height: 26px; }
        
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.55rem;
            font-weight: 600;
        }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        
        .status-badge-visit {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 16px;
        }
        .status-badge-visit.pending { background: #FEF3C7; color: #D97706; }
        .status-badge-visit.completed { background: #D1FAE5; color: #059669; }
        .status-badge-visit.cancelled { background: #FEE2E2; color: #DC2626; }
        
        .alert {
            padding: 10px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            border: 1px solid transparent;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        
        .detail-row {
            display: flex;
            padding: 4px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 140px;
            flex-shrink: 0;
            font-size: 0.75rem;
        }
        .detail-value { flex: 1; color: var(--text-primary); font-size: 0.8rem; }
        
        .patient-select-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .patient-select-card {
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 12px 14px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-primary);
            display: block;
        }
        .patient-select-card:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11,94,215,0.12); }
        .patient-select-card .patient-name { font-weight: 600; font-size: 0.95rem; }
        .patient-select-card .patient-id { font-size: 0.65rem; color: var(--text-secondary); font-family: monospace; }
        .patient-select-card .patient-meta { font-size: 0.65rem; color: var(--text-secondary); margin-top: 3px; }
        
        .referral-type-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 16px;
        }
        .referral-type-option {
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        .referral-type-option:hover { border-color: var(--primary); background: var(--primary-bg); transform: translateY(-2px); }
        .referral-type-option.active { border-color: var(--primary); background: var(--primary); color: white; }
        .referral-type-option.active .option-desc { color: rgba(255,255,255,0.8); }
        .referral-type-option.active .option-title { color: white; }
        .referral-type-option .option-icon { font-size: 1.6rem; display: block; margin-bottom: 4px; }
        .referral-type-option .option-title { font-weight: 600; font-size: 0.85rem; }
        .referral-type-option .option-desc { font-size: 0.7rem; opacity: 0.7; margin-top: 2px; }
        
        .internal-form, .external-form { display: none; padding-top: 12px; border-top: 2px solid var(--border-color); margin-top: 12px; }
        .internal-form.active, .external-form.active { display: block; }
        
        .doctor-select-wrapper select {
            appearance: none;
            -webkit-appearance: none;
            padding-right: 30px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 10 10'%3E%3Cpath fill='%2364748B' d='M5 7L1 3h8z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 10px;
        }
        
        .doctor-info-text {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-top: 6px;
            padding: 8px 12px;
            background: var(--gray-50);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        .doctor-info-text .status-item { display: flex; align-items: center; gap: 4px; font-size: 0.7rem; color: var(--text-secondary); }
        .doctor-info-text .status-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; }
        .doctor-info-text .status-dot.online { background: #059669; animation: pulse-dot 2s infinite; }
        .doctor-info-text .status-dot.offline { background: #94A3B8; }
        @keyframes pulse-dot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(0.8); } }
        
        .expert-other-wrapper { display: none; margin-top: 6px; }
        .expert-other-wrapper.show { display: block; }
        
        .vital-grid-6 {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 6px;
        }
        .vital-item {
            background: var(--primary-bg);
            border-radius: 6px;
            padding: 6px 4px 4px 4px;
            border-left: 3px solid var(--primary);
            text-align: center;
        }
        .vital-item .vital-icon { font-size: 0.8rem; display: block; line-height: 1.2; }
        .vital-item .vital-label {
            font-size: 0.45rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
            line-height: 1.2;
        }
        .vital-item .vital-value {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--primary-dark);
            line-height: 1.3;
        }
        .vital-item .vital-unit { font-size: 0.45rem; color: var(--text-secondary); }
        .vital-item .vital-status {
            font-size: 0.45rem;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 1px;
            line-height: 1.2;
        }
        .vital-status.normal { background: var(--success-bg); color: var(--success); }
        .vital-status.high { background: var(--danger-bg); color: var(--danger); }
        .vital-status.low { background: var(--warning-bg); color: var(--warning); }
        .vital-status.unknown { background: var(--gray-200); color: var(--gray-500); }
        
        .vital-item.temp { border-left-color: #DC2626; }
        .vital-item.bp { border-left-color: var(--primary); }
        .vital-item.pulse { border-left-color: #7C3AED; }
        .vital-item.weight { border-left-color: #D97706; }
        .vital-item.height { border-left-color: #0D9488; }
        .vital-item.bmi { border-left-color: #2563EB; }
        .vital-item.temp .vital-value { color: #DC2626; }
        .vital-item.bp .vital-value { color: var(--primary); }
        .vital-item.pulse .vital-value { color: #7C3AED; }
        .vital-item.weight .vital-value { color: #D97706; }
        .vital-item.height .vital-value { color: #0D9488; }
        .vital-item.bmi .vital-value { color: #2563EB; }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            margin-top: 4px;
        }
        .data-table thead th {
            background: var(--primary);
            color: white;
            padding: 6px 10px;
            text-align: left;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 700;
        }
        .data-table tbody td {
            padding: 5px 10px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
            font-size: 0.78rem;
            word-wrap: break-word;
            word-break: break-word;
            white-space: normal;
        }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:hover td { background: var(--primary-bg); border-radius: 4px; }
        
        .full-width { grid-column: span 2; }
        .full-width-3 { grid-column: span 3; }
        .mt-2 { margin-top: 6px; }
        .mt-3 { margin-top: 10px; }
        .mt-4 { margin-top: 14px; }
        .mb-3 { margin-bottom: 10px; }
        .text-danger { color: #EF4444; }
        .text-sm { font-size: 0.8rem; }
        .text-xs { font-size: 0.65rem; }
        .font-semibold { font-weight: 600; }
        .text-gray-400 { color: var(--text-secondary); }
        .text-gray-500 { color: var(--gray-500); }
        .text-green-600 { color: #059669; }
        .text-purple-600 { color: #7C3AED; }
        .flex-wrap { flex-wrap: wrap; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-3 { gap: 10px; }
        .gap-2 { gap: 6px; }
        .overflow-x-auto { overflow-x: auto; }
        .text-center { text-align: center; }
        .py-2 { padding-top: 6px; padding-bottom: 6px; }
        .py-3 { padding-top: 10px; padding-bottom: 10px; }
        .py-6 { padding-top: 20px; padding-bottom: 20px; }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 10px 18px;
            border-radius: 8px;
            z-index: 9999;
            max-width: 340px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #ffffff;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: #059669; }
        .toast-custom.error { background: #DC2626; }
        .toast-custom.info { background: #0B5ED7; }
        
        .footer {
            padding: 10px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 16px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        .queue-info-box {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: var(--bg-card);
            border-radius: 6px;
            border: 1px solid var(--border-color);
            margin-top: 4px;
        }
        .queue-info-box .queue-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .queue-info-box .queue-dot.green { background: #059669; }
        .queue-info-box .queue-dot.yellow { background: #D97706; }
        .queue-info-box .queue-dot.orange { background: #EA580C; }
        .queue-info-box .queue-dot.red { background: #DC2626; }
        .queue-badge {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 12px;
        }
        .queue-badge.green { background: #D1FAE5; color: #059669; }
        .queue-badge.yellow { background: #FEF3C7; color: #D97706; }
        .queue-badge.orange { background: #FED7AA; color: #EA580C; }
        .queue-badge.red { background: #FEE2E2; color: #DC2626; }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 14px; }
            .vital-grid-6 { grid-template-columns: repeat(3, 1fr); }
            .grid-3 { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .page-header-custom { padding: 14px 16px; }
            .page-header-custom .page-title { font-size: 1.1rem; }
            .card { padding: 12px 14px; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; }
            .referral-type-selector { grid-template-columns: 1fr; }
            .grid-2 { grid-template-columns: 1fr; }
            .grid-3 { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
            .patient-select-grid { grid-template-columns: 1fr; }
            .vital-grid-6 { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .card { padding: 8px 10px; }
            .vital-grid-6 { grid-template-columns: 1fr 1fr; }
            .data-table { font-size: 0.7rem; }
            .data-table thead th, .data-table tbody td { padding: 3px 6px; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-md"></i>
                Refer Patient
                <span class="role-badge-display">DOCTOR</span>
                <span class="status-verified">
                    <i class="fas fa-check-circle"></i> Status: referred
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-share-alt"></i>
                <?php if ($patient): ?>
                    Patient: <strong><?= htmlspecialchars($patient['full_name']) ?></strong>
                    <span class="header-badge"><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id']) ?></span>
                    <span class="header-badge"><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                <?php else: ?>
                    Select a patient from the list below
                <?php endif; ?>
                <span class="header-badge"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?></span>
                <span class="header-badge"><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($user_full_name) ?></span>
            </p>
        </div>
        <div class="no-print" style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="my_patients.php" class="btn-outline-light"><i class="fas fa-arrow-left"></i> Back</a>
            <?php if ($patient): ?>
                <a href="refer_patient_pdf.php?patient_id=<?= $patient_id ?>" target="_blank" class="btn-view-pdf">
                    <i class="fas fa-file-pdf"></i> View PDF
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error_message ?></div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STEP 1: SELECT PATIENT -->
    <!-- ================================================================ -->
    <?php if (!$patient): ?>
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-users title-blue mr-2"></i>
            Select Patient
            <span class="text-sm font-normal text-gray-400">(<?= count($patients_list) ?> patients)</span>
        </h3>
        <?php if (count($patients_list) > 0): ?>
            <div class="patient-select-grid">
                <?php foreach ($patients_list as $p): ?>
                    <a href="refer_patient.php?patient_id=<?= $p['id'] ?>" class="patient-select-card">
                        <div class="patient-name"><?= htmlspecialchars($p['full_name']) ?></div>
                        <div class="patient-id">ID: <?= htmlspecialchars($p['patient_id']) ?></div>
                        <div class="patient-meta">
                            <?= htmlspecialchars($p['gender'] ?? 'N/A') ?> • 
                            <?= !empty($p['date_of_birth']) ? calculateAge($p['date_of_birth']) . ' yrs' : 'N/A' ?> • 
                            <?= htmlspecialchars($p['phone'] ?? 'N/A') ?> •
                            Emergency: <?= htmlspecialchars($p['emergency_contact'] ?? 'N/A') ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-gray-400">
                <i class="fas fa-users text-3xl block mb-2" style="color: var(--border-color);"></i>
                <p>No patients assigned to you yet</p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STEP 2: REFERRAL FORM -->
    <!-- ================================================================ -->
    <?php if ($patient): ?>
    
    <!-- Patient Information Card -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-user-circle title-blue mr-2"></i>
            Patient Information
            <?php if ($patient['visit_number']): ?>
                <span class="badge badge-info ml-2">Last Visit: <?= htmlspecialchars($patient['visit_number']) ?></span>
            <?php endif; ?>
            <?php if ($patient['consultation_fee'] > 0): ?>
                <span class="badge badge-success ml-2">Fee: TSh <?= number_format($patient['consultation_fee'], 0) ?></span>
            <?php endif; ?>
        </h3>
        <div class="grid-2">
            <div class="detail-row"><span class="detail-label">Full Name</span><span class="detail-value"><strong><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></strong></span></div>
            <div class="detail-row"><span class="detail-label">Patient ID</span><span class="detail-value"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Gender</span><span class="detail-value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Date of Birth</span><span class="detail-value"><?= !empty($patient['date_of_birth']) ? date('d/m/Y', strtotime($patient['date_of_birth'])) . ' (' . calculateAge($patient['date_of_birth']) . ' yrs)' : 'N/A' ?></span></div>
            <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Emergency Contact</span><span class="detail-value"><strong><?= htmlspecialchars($patient['emergency_contact'] ?? 'N/A') ?></strong></span></div>
            <div class="detail-row"><span class="detail-label">Blood Group</span><span class="detail-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Referred By</span><span class="detail-value"><strong>Dr. <?= htmlspecialchars($user_full_name) ?></strong> (<?= htmlspecialchars($user_specialty) ?>)</span></div>
        </div>
    </div>

    <!-- Visit Information Card -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-clinic-medical title-green mr-2"></i>
            Visit Information
        </h3>
        <div class="grid-2">
            <div class="detail-row"><span class="detail-label">Visit Number</span><span class="detail-value"><strong><?= htmlspecialchars($patient['visit_number'] ?? 'N/A') ?></strong></span></div>
            <div class="detail-row"><span class="detail-label">Visit Date</span><span class="detail-value"><?= !empty($patient['last_visit_date']) ? date('d/m/Y h:i A', strtotime($patient['last_visit_date'])) : 'N/A' ?></span></div>
            <div class="detail-row"><span class="detail-label">Visit Status</span><span class="detail-value"><span class="status-badge-visit <?= $patient['last_visit_status'] ?? 'pending' ?>"><?= ucfirst($patient['last_visit_status'] ?? 'N/A') ?></span></span></div>
            <div class="detail-row"><span class="detail-label">Consultation Fee</span><span class="detail-value">TSh <?= number_format($patient['consultation_fee'] ?? 0, 0) ?></span></div>
            <?php if (!empty($patient['complaint'])): ?>
                <div class="detail-row full-width"><span class="detail-label">Complaint</span><span class="detail-value"><?= htmlspecialchars($patient['complaint']) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($patient['notes'])): ?>
                <div class="detail-row full-width"><span class="detail-label">Notes</span><span class="detail-value"><?= htmlspecialchars($patient['notes']) ?></span></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VITAL SIGNS -->
    <!-- ================================================================ -->
    <?php if ($vital_signs): ?>
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-heartbeat title-red mr-2"></i>
            Vital Signs
            <?php if ($vital_signs['recorded_by_name']): ?>
                <span class="text-xs text-gray-400 font-normal ml-2"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($vital_signs['recorded_by_name']) ?></span>
            <?php endif; ?>
            <span class="text-xs text-gray-400 font-normal ml-2"><i class="fas fa-clock"></i> <?= isset($vital_signs['recorded_at']) ? date('d/m/Y h:i A', strtotime($vital_signs['recorded_at'])) : 'N/A' ?></span>
        </h3>
        <?php
            $temp_status = getVitalStatus($vital_signs['temperature'] ?? null, 'temperature');
            $sys = $vital_signs['blood_pressure_systolic'] ?? null;
            $bp_status = getVitalStatus($sys, 'systolic');
            $pulse_status = getVitalStatus($vital_signs['pulse_rate'] ?? null, 'pulse');
            $bmi_status = getVitalStatus($vital_signs['bmi'] ?? null, 'bmi');
        ?>
        <div class="vital-grid-6">
            <div class="vital-item temp">
                <span class="vital-icon">🌡️</span>
                <span class="vital-label">Temperature</span>
                <span class="vital-value"><?= $vital_signs['temperature'] ?? '--' ?> <span class="vital-unit">°C</span></span>
                <span class="vital-status <?= $temp_status['class'] ?>"><?= $temp_status['label'] ?></span>
            </div>
            <div class="vital-item bp">
                <span class="vital-icon">❤️</span>
                <span class="vital-label">Blood Pressure</span>
                <span class="vital-value"><?= ($vital_signs['blood_pressure_systolic'] ?? '--') . '/' . ($vital_signs['blood_pressure_diastolic'] ?? '--') ?> <span class="vital-unit">mmHg</span></span>
                <span class="vital-status <?= $bp_status['class'] ?>"><?= $bp_status['label'] ?></span>
            </div>
            <div class="vital-item pulse">
                <span class="vital-icon">💓</span>
                <span class="vital-label">Pulse Rate</span>
                <span class="vital-value"><?= $vital_signs['pulse_rate'] ?? '--' ?> <span class="vital-unit">bpm</span></span>
                <span class="vital-status <?= $pulse_status['class'] ?>"><?= $pulse_status['label'] ?></span>
            </div>
            <div class="vital-item weight">
                <span class="vital-icon">⚖️</span>
                <span class="vital-label">Weight</span>
                <span class="vital-value"><?= $vital_signs['weight'] ?? '--' ?> <span class="vital-unit">kg</span></span>
            </div>
            <div class="vital-item height">
                <span class="vital-icon">📏</span>
                <span class="vital-label">Height</span>
                <span class="vital-value"><?= $vital_signs['height'] ?? '--' ?> <span class="vital-unit">cm</span></span>
            </div>
            <div class="vital-item bmi">
                <span class="vital-icon">📊</span>
                <span class="vital-label">BMI</span>
                <span class="vital-value"><?= $vital_signs['bmi'] ?? '--' ?> <span class="vital-unit">kg/m²</span></span>
                <span class="vital-status <?= $bmi_status['class'] ?>"><?= $bmi_status['label'] ?></span>
            </div>
        </div>
        <?php if (!empty($vital_signs['notes'])): ?>
            <div class="mt-2 text-xs text-gray-500" style="padding:2px 10px;background:var(--gray-50);border-radius:4px;border-left:3px solid var(--primary);">
                <strong>Notes:</strong> <?= htmlspecialchars($vital_signs['notes']) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- SYMPTOMS, HPI, PHYSICAL EXAMINATION -->
    <!-- ================================================================ -->
    <div class="card">
        <h3 class="card-title"><i class="fas fa-list-ul title-blue mr-2"></i> Clinical History</h3>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th style="width:33.33%;">Symptoms</th><th style="width:33.33%;">HPI</th><th style="width:33.33%;">Physical Examination</th></tr></thead>
                <tbody>
                    <tr>
                        <td style="vertical-align:top;white-space:pre-wrap;word-wrap:break-word;word-break:break-word;"><?= nl2br(htmlspecialchars($symptoms ?? 'No symptoms recorded')) ?></td>
                        <td style="vertical-align:top;white-space:pre-wrap;word-wrap:break-word;word-break:break-word;"><?= nl2br(htmlspecialchars($hpi ?? 'No HPI recorded')) ?></td>
                        <td style="vertical-align:top;white-space:pre-wrap;word-wrap:break-word;word-break:break-word;"><?= nl2br(htmlspecialchars($physical_exam ?? 'No physical exam recorded')) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- LAB TESTS -->
    <!-- ================================================================ -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-flask title-purple mr-2"></i>
            Laboratory Tests
            <span class="text-sm font-normal text-gray-400">(<?= count($lab_tests) ?>)</span>
        </h3>
        <?php if (count($lab_tests) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Test Name</th><th>Results</th><th>Lab Technician</th></tr></thead>
                    <tbody>
                        <?php foreach ($lab_tests as $test): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong></td>
                                <td><?php if (!empty($test['results'])): ?><span style="color:#059669;font-weight:600;"><?= htmlspecialchars($test['results']) ?></span><?php else: ?><span class="badge badge-warning">⏳ Pending</span><?php endif; ?></td>
                                <td><?= htmlspecialchars($test['lab_technician_name'] ?? 'Not assigned') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-400 text-center py-2">No lab tests found for this visit</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- DIAGNOSIS -->
    <!-- ================================================================ -->
    <div class="card">
        <h3 class="card-title"><i class="fas fa-diagnoses title-blue mr-2"></i> Diagnosis</h3>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Disease Name</th><th>Disease Code</th><th>Treatment</th></tr></thead>
                <tbody>
                    <tr>
                        <td><strong><?= htmlspecialchars($disease_name ?: ($diagnosis ?? 'No diagnosis recorded')) ?></strong></td>
                        <td><?= htmlspecialchars($disease_code ?? 'N/A') ?></td>
                        <td style="word-wrap:break-word;word-break:break-word;white-space:normal;"><?= nl2br(htmlspecialchars($treatment ?? 'No treatment recorded')) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-prescription title-purple mr-2"></i>
            Prescriptions
            <span class="text-sm font-normal text-gray-400">(<?= count($medications) ?>)</span>
        </h3>
        <?php if (count($medications) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Prescription Name</th><th>Quantity</th><th>Instructions</th></tr></thead>
                    <tbody>
                        <?php foreach ($medications as $med): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($med['medication_name'] ?? 'N/A') ?></strong></td>
                                <td><?= $med['quantity'] ?? 0 ?></td>
                                <td style="word-wrap:break-word;word-break:break-word;white-space:normal;"><?= htmlspecialchars($med['instructions'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-400 text-center py-2">No medications prescribed</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- PROCEDURES -->
    <!-- ================================================================ -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-syringe title-orange mr-2"></i>
            Procedures
            <span class="text-sm font-normal text-gray-400">(<?= count($procedure_equipment_list) > 0 ? count($procedure_equipment_list) : count($procedures) ?>)</span>
        </h3>
        <?php 
        $procedures_display = [];
        if (count($procedure_equipment_list) > 0) {
            foreach ($procedure_equipment_list as $proc) {
                $procedures_display[] = ['name' => $proc['procedure_name'] ?? 'N/A', 'equipment' => $proc['equipment_name'] ?? 'None'];
            }
        } elseif (count($procedures) > 0) {
            foreach ($procedures as $proc) {
                $procedures_display[] = ['name' => $proc['item_name'] ?? 'N/A', 'equipment' => $proc['equipment_name'] ?? 'None'];
            }
        }
        ?>
        <?php if (count($procedures_display) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Procedure Name</th><th>Medical Equipment Used</th></tr></thead>
                    <tbody>
                        <?php foreach ($procedures_display as $proc): ?>
                            <tr><td><strong><?= htmlspecialchars($proc['name']) ?></strong></td><td><?= htmlspecialchars($proc['equipment']) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-400 text-center py-2">No procedures performed</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- REFERRAL FORM -->
    <!-- ================================================================ -->
    <form method="POST" action="" id="referralForm">
        <input type="hidden" name="action" value="save_referral">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <input type="hidden" name="visit_id" value="<?= $visit_id ?>">
        
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-share-alt title-purple mr-2"></i> 
                Referral Details
                <span class="status-verified" style="margin-left:auto;">
                    <i class="fas fa-check-circle"></i> Status: referred
                </span>
            </h3>
            
            <div class="referral-type-selector">
                <div class="referral-type-option active" onclick="selectReferralType('internal', this)" data-type="internal">
                    <span class="option-icon">🏥</span>
                    <div class="option-title">Internal Referral</div>
                    <div class="option-desc">Refer within this facility</div>
                </div>
                <div class="referral-type-option" onclick="selectReferralType('external', this)" data-type="external">
                    <span class="option-icon">🌍</span>
                    <div class="option-title">External Referral</div>
                    <div class="option-desc">Refer to outside facility</div>
                </div>
            </div>
            
            <input type="hidden" name="referral_type" id="referralType" value="internal">
            
            <!-- ============================================================ -->
            <!-- INTERNAL REFERRAL -->
            <!-- ============================================================ -->
            <div class="internal-form active" id="internalForm">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Referred To <span class="text-danger">*</span></label>
                        <div class="doctor-select-wrapper">
                            <select name="referred_to_doctor" class="form-control" required id="doctorSelect">
                                <option value="">-- Select Doctor --</option>
                                <?php if (count($doctors) > 0): ?>
                                    <?php if ($online_count > 0): ?>
                                        <optgroup label="🟢 Online Doctors (<?= $online_count ?>)">
                                            <?php foreach ($doctors as $doctor): ?>
                                                <?php if ($doctor['is_online'] == 1): ?>
                                                    <option value="<?= $doctor['id'] ?>" 
                                                            data-patients="<?= $doctor['total_patients'] ?? 0 ?>"
                                                            data-pending="<?= $doctor['pending_patients'] ?? 0 ?>"
                                                            data-visits="<?= $doctor['pending_visits'] ?? 0 ?>">
                                                        🟢 <?= htmlspecialchars($doctor['full_name']) ?> 
                                                        <?= !empty($doctor['specialty']) ? '(' . htmlspecialchars($doctor['specialty']) . ')' : '' ?>
                                                        - 👥 <?= $doctor['total_patients'] ?? 0 ?> patients
                                                        <?php if (($doctor['pending_patients'] ?? 0) > 0): ?>
                                                            ⏳ <?= $doctor['pending_patients'] ?? 0 ?> waiting
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                    <?php if ($offline_count > 0): ?>
                                        <optgroup label="⚪ Offline Doctors (<?= $offline_count ?>)">
                                            <?php foreach ($doctors as $doctor): ?>
                                                <?php if ($doctor['is_online'] == 0): ?>
                                                    <option value="<?= $doctor['id'] ?>" 
                                                            data-patients="<?= $doctor['total_patients'] ?? 0 ?>"
                                                            data-pending="<?= $doctor['pending_patients'] ?? 0 ?>"
                                                            data-visits="<?= $doctor['pending_visits'] ?? 0 ?>">
                                                        ⚪ <?= htmlspecialchars($doctor['full_name']) ?> 
                                                        <?= !empty($doctor['specialty']) ? '(' . htmlspecialchars($doctor['specialty']) . ')' : '' ?>
                                                        - 👥 <?= $doctor['total_patients'] ?? 0 ?> patients
                                                        <?php if (($doctor['pending_patients'] ?? 0) > 0): ?>
                                                            ⏳ <?= $doctor['pending_patients'] ?? 0 ?> waiting
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <option value="" disabled>⚠️ No other doctors available</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="doctor-info-text" id="doctorInfoText">
                            <?php if (count($doctors) > 0): ?>
                                <div style="display:flex;flex-wrap:wrap;gap:12px;width:100%;">
                                    <span class="status-item"><span class="status-dot online"></span><strong><?= $online_count ?></strong> Online</span>
                                    <span class="status-item"><span class="status-dot offline"></span><strong><?= $offline_count ?></strong> Offline</span>
                                    <span class="status-item"><i class="fas fa-users"></i> <strong><?= count($doctors) ?></strong> doctor(s)</span>
                                    <span class="status-item" style="margin-left:auto;font-size:0.65rem;color:var(--text-secondary);">
                                        <i class="fas fa-info-circle"></i> Select a doctor to see patient queue
                                    </span>
                                </div>
                                <div id="selectedDoctorDetails" style="display:none;margin-top:8px;padding:8px 12px;background:var(--primary-bg);border-radius:6px;border-left:3px solid var(--primary);width:100%;">
                                    <div id="selectedDoctorInfo"></div>
                                </div>
                            <?php else: ?>
                                <span class="status-item" style="color:var(--danger);"><i class="fas fa-exclamation-triangle"></i> No other doctors found</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Specialty</label>
                        <select name="specialty" class="form-control">
                            <option value="">-- Select Specialty --</option>
                            <?php foreach ($specialties as $specialty): ?>
                                <option value="<?= htmlspecialchars($specialty) ?>"><?= htmlspecialchars($specialty) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Reason for Referral <span class="text-danger">*</span></label>
                    <textarea name="reason" class="form-control" rows="2" placeholder="Explain why the patient is being referred..." required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Additional Notes</label>
                    <textarea name="internal_notes" class="form-control" rows="2" placeholder="Any additional notes..."></textarea>
                    <small class="text-xs text-gray-400"><i class="fas fa-info-circle"></i> These notes will be included in the referral</small>
                </div>
            </div>
            
            <!-- ============================================================ -->
            <!-- EXTERNAL REFERRAL -->
            <!-- ============================================================ -->
            <div class="external-form" id="externalForm">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Facility/Hospital Name <span class="text-danger">*</span></label>
                        <input type="text" name="external_facility" class="form-control" placeholder="e.g. Muhimbili National Hospital">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expert Type</label>
                        <select name="expert_type" class="form-control" id="expertTypeSelect" onchange="toggleExpertOther()">
                            <option value="">-- Select Expert Type --</option>
                            <?php foreach ($expert_types as $expert): ?>
                                <option value="<?= htmlspecialchars($expert) ?>"><?= htmlspecialchars($expert) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="expert-other-wrapper" id="expertOtherWrapper">
                            <input type="text" name="expert_type_other" class="form-control" placeholder="Specify expert type..." style="margin-top:4px;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="external_phone" class="form-control" placeholder="e.g. +255 700 000 000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="external_email" class="form-control" placeholder="e.g. hospital@email.com">
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">Address</label>
                        <textarea name="external_address" class="form-control" rows="2" placeholder="Facility address..."></textarea>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Urgency</label>
                    <select name="urgency" class="form-control">
                        <option value="routine">🟢 Routine</option>
                        <option value="urgent">🟡 Urgent</option>
                        <option value="emergency">🔴 Emergency</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Reason for Referral <span class="text-danger">*</span></label>
                    <textarea name="referral_reason" class="form-control" rows="2" placeholder="Explain why the patient is being referred..." required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Additional Notes</label>
                    <textarea name="external_notes" class="form-control" rows="2" placeholder="Any additional notes..."></textarea>
                    <small class="text-xs text-gray-400"><i class="fas fa-info-circle"></i> These notes will be included in the referral</small>
                </div>
            </div>
            
            <!-- Clinical Summary -->
            <div class="form-group mt-3">
                <label class="form-label">Clinical Summary</label>
                <textarea name="clinical_summary" id="clinicalSummary" class="form-control" rows="4" placeholder="Summary of patient's clinical history..."><?php 
                    if ($last_visit) {
                        echo "--- Last Visit Information ---\n";
                        echo "Visit: " . ($last_visit['visit_number'] ?? 'N/A') . "\n";
                        echo "Date: " . date('M d, Y', strtotime($last_visit['created_at'])) . "\n";
                        echo "Status: " . ($last_visit['status'] ?? 'N/A') . "\n";
                        if (!empty($diagnosis)) { echo "\n--- Diagnosis ---\n" . $diagnosis . "\n"; }
                        if (!empty($disease_code)) { echo "Disease Code: " . $disease_code . "\n"; }
                        if (!empty($treatment)) { echo "\n--- Treatment Given ---\n" . $treatment . "\n"; }
                    }
                ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">General Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Any additional general notes..."></textarea>
            </div>
            
            <!-- View PDF Button -->
            <div class="mt-3 no-print">
                <a href="refer_patient_pdf.php?patient_id=<?= $patient_id ?>" target="_blank" class="btn-view-pdf">
                    <i class="fas fa-file-pdf"></i> View PDF
                </a>
                <span class="text-xs text-gray-400 ml-2"><i class="fas fa-info-circle"></i> Opens in new window</span>
            </div>
            
            <!-- Form Actions -->
            <div class="mt-3 flex flex-wrap gap-2 no-print" style="padding-top:12px;border-top:2px solid var(--border-color);">
                <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> Submit Referral</button>
                <button type="reset" class="btn btn-outline" onclick="resetForm()"><i class="fas fa-undo"></i> Clear</button>
                <a href="refer_patient.php" class="btn btn-outline"><i class="fas fa-users"></i> Change Patient</a>
                <a href="my_patients.php" class="btn btn-outline"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </div>
    </form>
    
    <?php endif; ?>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Refer Patient
            <span class="text-gray-300 mx-2">|</span>
            Dr. <?= htmlspecialchars($user_full_name) ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('h:i:s A') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div><p id="toastTitle">Notification</p><p id="toastMessage"></p></div>
</div>

<script>
    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // DATE/TIME UPDATE
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) footerTimestamp.textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // EXPERT TYPE - SHOW OTHER INPUT
    // ================================================================
    function toggleExpertOther() {
        var select = document.getElementById('expertTypeSelect');
        var otherWrapper = document.getElementById('expertOtherWrapper');
        if (select && select.value === 'Other (Specify)') {
            otherWrapper.classList.add('show');
        } else if (otherWrapper) {
            otherWrapper.classList.remove('show');
        }
    }

    // ================================================================
    // REFERRAL TYPE SELECTOR
    // ================================================================
    function selectReferralType(type, element) {
        document.querySelectorAll('.referral-type-option').forEach(function(btn) {
            btn.classList.remove('active');
        });
        if (element) element.classList.add('active');
        
        document.getElementById('referralType').value = type;
        
        var internalForm = document.getElementById('internalForm');
        var externalForm = document.getElementById('externalForm');
        
        if (type === 'internal') {
            internalForm.classList.add('active');
            externalForm.classList.remove('active');
            document.querySelectorAll('.internal-form .form-control[required]').forEach(function(el) {
                el.setAttribute('required', 'required');
            });
            document.querySelectorAll('.external-form .form-control[required]').forEach(function(el) {
                el.removeAttribute('required');
            });
        } else {
            internalForm.classList.remove('active');
            externalForm.classList.add('active');
            document.querySelectorAll('.external-form .form-control[required]').forEach(function(el) {
                el.setAttribute('required', 'required');
            });
            document.querySelectorAll('.internal-form .form-control[required]').forEach(function(el) {
                el.removeAttribute('required');
            });
        }
    }

    // ================================================================
    // SHOW DOCTOR QUEUE INFO ON SELECT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var doctorSelect = document.getElementById('doctorSelect');
        var selectedDoctorDetails = document.getElementById('selectedDoctorDetails');
        var selectedDoctorInfo = document.getElementById('selectedDoctorInfo');
        
        if (doctorSelect) {
            doctorSelect.addEventListener('change', function() {
                var selectedOption = this.options[this.selectedIndex];
                if (!selectedOption || !selectedOption.value) {
                    selectedDoctorDetails.style.display = 'none';
                    return;
                }
                
                var doctorName = selectedOption.text;
                var totalPatients = parseInt(selectedOption.dataset.patients) || 0;
                var pendingPatients = parseInt(selectedOption.dataset.pending) || 0;
                var pendingVisits = parseInt(selectedOption.dataset.visits) || 0;
                
                doctorName = doctorName.replace(/[🟢⚪]\s*/, '')
                    .replace(/\s*-\s*👥\s*\d+\s*patients.*/, '')
                    .replace(/\s*⏳\s*\d+\s*waiting.*/, '')
                    .trim();
                
                var queueStatus = '';
                var queueColor = '';
                if (pendingPatients == 0) {
                    queueStatus = '🟢 No patients waiting - Available now!';
                    queueColor = '#059669';
                } else if (pendingPatients <= 3) {
                    queueStatus = '🟡 ' + pendingPatients + ' patient(s) waiting - Short queue';
                    queueColor = '#D97706';
                } else if (pendingPatients <= 7) {
                    queueStatus = '🟠 ' + pendingPatients + ' patient(s) waiting - Medium queue';
                    queueColor = '#EA580C';
                } else {
                    queueStatus = '🔴 ' + pendingPatients + ' patient(s) waiting - Long queue';
                    queueColor = '#DC2626';
                }
                
                var html = `
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                        <div style="font-weight:600;font-size:0.85rem;color:var(--text-primary);">
                            👨‍⚕️ Dr. ${doctorName}
                        </div>
                        <div style="font-size:0.7rem;color:var(--text-secondary);background:var(--bg-card);padding:2px 10px;border-radius:12px;border:1px solid var(--border-color);">
                            👥 <strong>${totalPatients}</strong> total patients
                        </div>
                        <div style="font-size:0.7rem;color:var(--text-secondary);background:var(--bg-card);padding:2px 10px;border-radius:12px;border:1px solid var(--border-color);">
                            📋 <strong>${pendingVisits}</strong> pending visits
                        </div>
                        <div style="font-size:0.7rem;font-weight:600;padding:2px 12px;border-radius:12px;background:${queueColor}20;color:${queueColor};border:1px solid ${queueColor}40;">
                            ${queueStatus}
                        </div>
                    </div>
                    <div style="font-size:0.65rem;color:var(--text-secondary);margin-top:6px;border-top:1px solid var(--border-color);padding-top:4px;display:flex;flex-wrap:wrap;gap:4px;">
                        <span><i class="fas fa-info-circle"></i></span>
                        <span>
                            ${pendingPatients == 0 ? '✅ This doctor has NO pending patients. Referral will be seen immediately.' :
                            pendingPatients <= 3 ? '✅ This doctor has a SHORT queue. Referral will be seen soon.' :
                            pendingPatients <= 7 ? '⚠️ This doctor has a MODERATE queue. Consider urgency when referring.' :
                            '⚠️ This doctor has a LONG queue. Only refer if URGENT.'}
                        </span>
                    </div>
                `;
                
                selectedDoctorInfo.innerHTML = html;
                selectedDoctorDetails.style.display = 'block';
            });
        }
    });

    // ================================================================
    // RESET FORM
    // ================================================================
    function resetForm() {
        if (!confirm('Clear all form fields?')) return;
        document.getElementById('doctorSelect').value = '';
        document.getElementById('selectedDoctorDetails').style.display = 'none';
        document.querySelectorAll('.external-form input, .external-form textarea, .external-form select').forEach(function(el) {
            el.value = '';
        });
        document.querySelectorAll('textarea[name="reason"], textarea[name="referral_reason"], textarea[name="internal_notes"], textarea[name="external_notes"], textarea[name="notes"], textarea[name="clinical_summary"]').forEach(function(el) {
            el.value = '';
        });
        var otherWrapper = document.getElementById('expertOtherWrapper');
        if (otherWrapper) otherWrapper.classList.remove('show');
        showToast('Info', 'Form has been cleared', 'info');
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
        }, 4000);
    }

    // ================================================================
    // FORM SUBMIT VALIDATION
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.internal-form .form-control[required]').forEach(function(el) {
            el.setAttribute('required', 'required');
        });
        document.querySelectorAll('.external-form .form-control[required]').forEach(function(el) {
            el.removeAttribute('required');
        });
        
        var form = document.getElementById('referralForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                var referralType = document.getElementById('referralType')?.value || 'internal';
                var patientId = document.querySelector('input[name="patient_id"]')?.value;
                
                if (!patientId || patientId == 0) {
                    e.preventDefault();
                    showToast('Error', 'Please select a patient', 'error');
                    return false;
                }
                
                if (referralType === 'internal') {
                    var reason = document.querySelector('textarea[name="reason"]')?.value?.trim();
                    var doctorSelect = document.querySelector('select[name="referred_to_doctor"]');
                    if (!reason) {
                        e.preventDefault();
                        showToast('Error', 'Please enter reason for referral', 'error');
                        document.querySelector('textarea[name="reason"]')?.focus();
                        return false;
                    }
                    if (!doctorSelect || !doctorSelect.value) {
                        e.preventDefault();
                        showToast('Error', 'Please select a doctor to refer to', 'error');
                        return false;
                    }
                }
                
                if (referralType === 'external') {
                    var facility = document.querySelector('input[name="external_facility"]');
                    var reasonExternal = document.querySelector('textarea[name="referral_reason"]')?.value?.trim();
                    if (!facility || !facility.value.trim()) {
                        e.preventDefault();
                        showToast('Error', 'Please enter facility/hospital name', 'error');
                        facility?.focus();
                        return false;
                    }
                    if (!reasonExternal) {
                        e.preventDefault();
                        showToast('Error', 'Please enter reason for referral', 'error');
                        document.querySelector('textarea[name="referral_reason"]')?.focus();
                        return false;
                    }
                }
                
                var submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                }
                return true;
            });
        }
    });

    // ================================================================
    // CONSOLE LOG
    // ================================================================
    console.log('✅ Refer Patient - Status: referred (FIXED)');
    console.log('✅ status column set to: referred (NOT NULL WITH DEFAULT)');
    console.log('✅ ALTER TABLE runs automatically to fix status column');
    console.log('✅ UPDATE NULL status to referred runs automatically');
    console.log('✅ Internal referrals saved with status: referred');
    console.log('✅ External referrals saved with status: referred');
    console.log('✅ Force update after insert to ensure status is set');
    console.log('✅ Verification: SELECT status FROM referrals WHERE id = ?');
</script>

</body>
</html>