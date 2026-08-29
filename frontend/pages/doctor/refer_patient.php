<?php
// ================================================================
// FILE: frontend/pages/doctor/refer_patient.php
// DOCTOR - REFER PATIENT (TWO-STEP)
// FIXED: Changes assigned_doctor_id + doctor_id in visits to receiver
// Shows: Referred by Dr. X with reasons
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
// ================================================================
try {
    $stmt = $db->prepare("SHOW TABLES LIKE 'referrals'");
    $stmt->execute();
    $table_exists = $stmt->rowCount() > 0;
    
    if (!$table_exists) {
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
              `reason` text DEFAULT NULL,
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
        // Fix status column
        try {
            $stmt = $db->prepare("SHOW COLUMNS FROM referrals LIKE 'status'");
            $stmt->execute();
            $column_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($column_info) {
                $enum_values = $column_info['Type'] ?? '';
                if (strpos($enum_values, "'pending'") === false) {
                    $alter_sql = "
                        ALTER TABLE `referrals` 
                        MODIFY COLUMN `status` enum('pending','referred','accepted','rejected','completed','cancelled') 
                        NOT NULL DEFAULT 'referred'
                    ";
                    $db->exec($alter_sql);
                }
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
// GET PATIENTS LIST
// ================================================================
$patients_list = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT p.id, p.full_name, p.patient_id, p.gender, p.phone, p.date_of_birth, p.emergency_contact,
               v.id as visit_id, v.visit_number, v.diagnosis, v.status as visit_status
        FROM patients p
        JOIN visits v ON p.id = v.patient_id
        WHERE v.doctor_id = ?
        AND v.status NOT IN ('completed', 'cancelled')
        ORDER BY p.full_name ASC
    ");
    $stmt->execute([$user_id]);
    $patients_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patients_list = [];
    error_log("Patients list error: " . $e->getMessage());
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
// HANDLE FORM SUBMISSIONS - SEPARATE FOR INTERNAL AND EXTERNAL
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ================================================================
    // SUBMIT INTERNAL REFERRAL - FIXED
    // ================================================================
    if ($action === 'submit_internal') {
        $reason = trim($_POST['reason'] ?? '');
        $internal_notes = trim($_POST['internal_notes'] ?? '');
        $referred_to_doctor = isset($_POST['referred_to_doctor']) ? (int)$_POST['referred_to_doctor'] : 0;
        $selected_patients = isset($_POST['selected_patients']) ? $_POST['selected_patients'] : [];
        $urgency = 'routine';
        
        $errors = [];
        if (empty($selected_patients) || count($selected_patients) == 0) {
            $errors[] = "Please select at least one patient";
        }
        if ($referred_to_doctor <= 0) {
            $errors[] = "Please select a doctor";
        }
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                $referral_status = 'referred';
                $referrals_created = 0;
                $referral_numbers = [];
                
                // Get receiver doctor info
                $doctor_name = '';
                $stmt = $db->prepare("SELECT full_name, phone, specialty FROM users WHERE id = ?");
                $stmt->execute([$referred_to_doctor]);
                $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                $doctor_name = $doctor['full_name'] ?? 'Unknown Doctor';
                
                $combined_reason = $reason;
                if (!empty($internal_notes)) {
                    $combined_reason .= "\n\n--- Additional Notes from Referring Doctor ---\n" . $internal_notes;
                }
                
                // Get patient details for selected patients
                $placeholders = implode(',', array_fill(0, count($selected_patients), '?'));
                $stmt = $db->prepare("
                    SELECT id, full_name, patient_id, phone, emergency_contact 
                    FROM patients WHERE id IN ($placeholders)
                ");
                $stmt->execute($selected_patients);
                $patients_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($patients_data as $patient) {
                    // ================================================================
                    // ✅ FIX: Get the MOST RECENT ACTIVE visit for this patient
                    // ================================================================
                    $stmt_visit = $db->prepare("
                        SELECT id, visit_number, diagnosis, disease_code, treatment, symptoms, hpi, physical_exam, doctor_id
                        FROM visits 
                        WHERE patient_id = ? 
                        AND status NOT IN ('completed', 'cancelled')
                        ORDER BY id DESC LIMIT 1
                    ");
                    $stmt_visit->execute([$patient['id']]);
                    $visit_info = $stmt_visit->fetch(PDO::FETCH_ASSOC);
                    
                    // ✅ If no active visit exists, create one
                    if (!$visit_info) {
                        $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        $stmt = $db->prepare("
                            INSERT INTO visits (
                                visit_number, visit_date, patient_id, doctor_id,
                                branch_id, visit_type, status, created_at
                            ) VALUES (?, NOW(), ?, ?, ?, 'new', 'pending', NOW())
                        ");
                        $stmt->execute([$visit_number, $patient['id'], $user_id, $user_branch_id]);
                        $visit_id = $db->lastInsertId();
                        $visit_info = [
                            'id' => $visit_id, 
                            'visit_number' => $visit_number,
                            'doctor_id' => $user_id
                        ];
                    }
                    
                    $visit_id_to_use = $visit_info['id'];
                    $diagnosis = $visit_info['diagnosis'] ?? '';
                    $treatment = $visit_info['treatment'] ?? '';
                    $disease_code = $visit_info['disease_code'] ?? '';
                    $current_doctor_id = $visit_info['doctor_id'] ?? $user_id;
                    
                    // Build clinical notes
                    $patient_clinical_notes = "";
                    if (!empty($diagnosis)) {
                        $patient_clinical_notes .= "\n\n--- Diagnosis ---\n" . $diagnosis;
                    }
                    if (!empty($disease_code)) {
                        $patient_clinical_notes .= "\nDisease Code: " . $disease_code;
                    }
                    if (!empty($treatment)) {
                        $patient_clinical_notes .= "\n\n--- Treatment Given ---\n" . $treatment;
                    }
                    
                    // ================================================================
                    // ✅ CREATE REFERRAL RECORD WITH visit_id
                    // ================================================================
                    $referral_number = 'REF-' . date('Ymd') . '-' . str_pad($patient['id'], 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                    
                    $stmt = $db->prepare("
                        INSERT INTO referrals (
                            referral_number, visit_id, patient_id, from_doctor_id,
                            referral_type, to_doctor_id,
                            reason, clinical_notes, diagnosis, treatment_given,
                            urgency, status, referral_date, created_by, branch_id, created_at
                        ) VALUES (
                            ?, ?, ?, ?, 'internal', ?,
                            ?, ?, ?, ?, 
                            ?, ?, NOW(), ?, ?, NOW()
                        )
                    ");
                    
                    $stmt->execute([
                        $referral_number,
                        $visit_id_to_use,              // ✅ NOW visit_id is set!
                        $patient['id'],
                        $user_id,
                        $referred_to_doctor,
                        $combined_reason,
                        $patient_clinical_notes,
                        $diagnosis,
                        $treatment,
                        $urgency,
                        $referral_status,
                        $user_id,
                        $user_branch_id
                    ]);
                    
                    $referral_id = $db->lastInsertId();
                    $referrals_created++;
                    $referral_numbers[] = $referral_number;
                    
                    // ================================================================
                    // ✅ FIX 1: UPDATE VISIT - Change doctor_id to receiver
                    // ================================================================
                    $stmt_update = $db->prepare("
                        UPDATE visits 
                        SET doctor_id = ?,
                            is_referred = 1,
                            referred_by_doctor_id = ?,
                            referred_to_doctor_id = ?,
                            referral_id = ?,
                            status = 'assigned',
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt_update->execute([
                        $referred_to_doctor,    // ✅ doctor_id becomes receiver
                        $user_id,               // referring doctor
                        $referred_to_doctor,    // receiving doctor
                        $referral_id,
                        $visit_id_to_use
                    ]);
                    
                    // ================================================================
                    // ✅ FIX 2: UPDATE PATIENT - Assign to referred doctor
                    // ================================================================
                    $stmt_update = $db->prepare("
                        UPDATE patients 
                        SET assigned_doctor_id = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt_update->execute([$referred_to_doctor, $patient['id']]);
                    
                    // ================================================================
                    // ✅ FIX 3: UPDATE ALL OTHER ACTIVE VISITS FOR THIS PATIENT
                    // ================================================================
                    $stmt_visit_update = $db->prepare("
                        UPDATE visits 
                        SET doctor_id = ?,
                            is_referred = 1,
                            referred_by_doctor_id = ?,
                            referred_to_doctor_id = ?,
                            referral_id = ?,
                            status = 'assigned',
                            updated_at = NOW()
                        WHERE patient_id = ? 
                        AND doctor_id = ?
                        AND status NOT IN ('completed', 'cancelled')
                        AND id != ?
                    ");
                    $stmt_visit_update->execute([
                        $referred_to_doctor,    // ✅ doctor_id becomes receiver
                        $user_id,
                        $referred_to_doctor,
                        $referral_id,
                        $patient['id'],
                        $user_id,               // only visits from current doctor
                        $visit_id_to_use
                    ]);
                    
                    // ================================================================
                    // ✅ CREATE NOTIFICATION FOR RECEIVING DOCTOR
                    // ================================================================
                    $stmt = $db->prepare("
                        INSERT INTO notifications (user_id, branch_id, patient_id, title, message, type, link, created_at)
                        VALUES (?, ?, ?, ?, ?, 'info', ?, NOW())
                    ");
                    $notif_message = "New referral from Dr. " . $user_full_name . " for patient " . ($patient['full_name'] ?? '') . ". Patient has been assigned to you.";
                    $stmt->execute([
                        $referred_to_doctor,
                        $user_branch_id,
                        $patient['id'],
                        "📋 New Referral Received",
                        $notif_message,
                        "consultations.php"
                    ]);
                    
                    // ================================================================
                    // ✅ LOG ACTIVITY
                    // ================================================================
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (user_id, branch_id, patient_id, action, details, created_at) 
                        VALUES (?, ?, ?, 'referral_created', ?, NOW())
                    ");
                    $stmt->execute([
                        $user_id,
                        $user_branch_id,
                        $patient['id'],
                        "Patient referred internally: " . ($patient['full_name'] ?? '') . " (#$referral_number) - From Dr. " . $user_full_name . " to Dr. " . $doctor_name . " (Status: assigned, doctor_id changed from $current_doctor_id to $referred_to_doctor)"
                    ]);
                }
                
                $db->commit();
                
                $referral_list = implode(', ', $referral_numbers);
                $message = "✅ " . $referrals_created . " patient(s) referred internally successfully!<br>";
                $message .= "📋 Referrals: " . $referral_list . "<br>";
                $message .= "👨‍⚕️ Referred by: Dr. " . $user_full_name . " → To: Dr. " . $doctor_name . "<br>";
                $message .= "📌 Status: assigned (Patient assigned to new doctor)";
                $message .= "<br>🔄 Doctor ID changed from " . $user_id . " to " . $referred_to_doctor;
                $message_type = 'success';
                
                echo '<script>
                    setTimeout(function(){
                        window.location.href = "my_patients.php?success=referral";
                    }, 3000);
                </script>';
                
            } catch (Exception $e) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
                $message = "❌ Internal referral error: " . $e->getMessage();
                $message_type = 'error';
                error_log("Internal referral error: " . $e->getMessage());
            }
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // SUBMIT EXTERNAL REFERRAL - STAYS SAME (no doctor change)
    // ================================================================
    if ($action === 'submit_external') {
        $patient_id_post = isset($_POST['external_patient_id']) ? (int)$_POST['external_patient_id'] : 0;
        $referral_reason = trim($_POST['referral_reason'] ?? '');
        $external_facility = trim($_POST['external_facility'] ?? '');
        $external_address = trim($_POST['external_address'] ?? '');
        $external_phone = trim($_POST['external_phone'] ?? '');
        $external_email = trim($_POST['external_email'] ?? '');
        $expert_type = trim($_POST['expert_type'] ?? '');
        $expert_type_other = trim($_POST['expert_type_other'] ?? '');
        $external_notes = trim($_POST['external_notes'] ?? '');
        $urgency = $_POST['urgency'] ?? 'routine';
        
        if ($expert_type === 'Other (Specify)' && !empty($expert_type_other)) {
            $expert_type = $expert_type_other;
        }
        
        $errors = [];
        if ($patient_id_post <= 0) {
            $errors[] = "Please select a patient";
        }
        if (empty($external_facility)) {
            $errors[] = "Please enter facility name";
        }
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Get patient details
                $stmt = $db->prepare("SELECT id, full_name, patient_id, phone, emergency_contact FROM patients WHERE id = ?");
                $stmt->execute([$patient_id_post]);
                $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$patient) {
                    throw new Exception("Patient not found");
                }
                
                // Get latest active visit
                $stmt_visit = $db->prepare("
                    SELECT id, visit_number, diagnosis, disease_code, treatment, symptoms, hpi, physical_exam
                    FROM visits 
                    WHERE patient_id = ?
                    AND status NOT IN ('completed', 'cancelled')
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt_visit->execute([$patient_id_post]);
                $visit_info = $stmt_visit->fetch(PDO::FETCH_ASSOC);
                
                if (!$visit_info) {
                    $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $stmt = $db->prepare("
                        INSERT INTO visits (
                            visit_number, visit_date, patient_id, doctor_id,
                            branch_id, visit_type, status, created_at
                        ) VALUES (?, NOW(), ?, ?, ?, 'new', 'pending', NOW())
                    ");
                    $stmt->execute([$visit_number, $patient_id_post, $user_id, $user_branch_id]);
                    $visit_id = $db->lastInsertId();
                    $visit_info = ['id' => $visit_id, 'visit_number' => $visit_number];
                }
                
                $visit_id_to_use = $visit_info['id'];
                $diagnosis = $visit_info['diagnosis'] ?? '';
                $treatment = $visit_info['treatment'] ?? '';
                $disease_code = $visit_info['disease_code'] ?? '';
                
                // Build clinical notes
                $clinical_notes_final = "";
                if (!empty($diagnosis)) {
                    $clinical_notes_final .= "\n\n--- Diagnosis ---\n" . $diagnosis;
                }
                if (!empty($disease_code)) {
                    $clinical_notes_final .= "\nDisease Code: " . $disease_code;
                }
                if (!empty($treatment)) {
                    $clinical_notes_final .= "\n\n--- Treatment Given ---\n" . $treatment;
                }
                
                $combined_reason = $referral_reason;
                if (!empty($external_notes)) {
                    $combined_reason .= "\n\n--- Additional Notes from Referring Doctor ---\n" . $external_notes;
                }
                
                $clinical_notes_with_expert = $clinical_notes_final;
                if (!empty($expert_type)) {
                    $clinical_notes_with_expert = "Expert Type: " . $expert_type . "\n\n" . $clinical_notes_final;
                }
                
                $referral_number = 'REF-' . date('Ymd') . '-' . str_pad($patient_id_post, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                
                // Insert external referral
                $stmt = $db->prepare("
                    INSERT INTO referrals (
                        referral_number, visit_id, patient_id, from_doctor_id,
                        referral_type, to_hospital_name, to_hospital_address, to_hospital_phone,
                        reason, clinical_notes, diagnosis, treatment_given, expert_type,
                        urgency, status, referral_date, created_by, branch_id, created_at
                    ) VALUES (
                        ?, ?, ?, ?, 'external', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW()
                    )
                ");
                
                $stmt->execute([
                    $referral_number,
                    $visit_id_to_use,
                    $patient_id_post,
                    $user_id,
                    $external_facility,
                    $external_address,
                    $external_phone,
                    $combined_reason,
                    $clinical_notes_with_expert,
                    $diagnosis,
                    $treatment,
                    $expert_type,
                    $urgency,
                    'referred',
                    $user_id,
                    $user_branch_id
                ]);
                
                $referral_id = $db->lastInsertId();
                
                // ✅ External: Mark visit as referred but KEEP doctor_id (no change)
                $stmt_update = $db->prepare("
                    UPDATE visits 
                    SET is_referred = 1,
                        referred_by_doctor_id = ?,
                        referral_id = ?,
                        status = 'referred',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt_update->execute([
                    $user_id,
                    $referral_id,
                    $visit_id_to_use
                ]);
                
                // ✅ External: DO NOT change assigned_doctor_id on patients
                // Patient stays with current doctor
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, patient_id, action, details, created_at) 
                    VALUES (?, ?, ?, 'referral_created', ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    $user_branch_id,
                    $patient_id_post,
                    "Patient referred externally: " . ($patient['full_name'] ?? '') . " (#$referral_number) - To: " . $external_facility . " (Status: referred, doctor_id unchanged)"
                ]);
                
                $db->commit();
                
                $message = "✅ Patient referred externally successfully!<br>";
                $message .= "📋 Referral: " . $referral_number . "<br>";
                $message .= "🏥 To: " . $external_facility . "<br>";
                $message .= "👨‍⚕️ Referred by: Dr. " . $user_full_name . "<br>";
                $message .= "📌 Status: referred (Patient remains with current doctor)";
                $message_type = 'success';
                
                echo '<script>
                    setTimeout(function(){
                        window.location.href = "my_patients.php?success=referral";
                    }, 3000);
                </script>';
                
            } catch (Exception $e) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
                $message = "❌ External referral error: " . $e->getMessage();
                $message_type = 'error';
                error_log("External referral error: " . $e->getMessage());
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
        
        .referral-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 0;
        }
        
        .referral-column {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .referral-column:hover { border-color: var(--primary); box-shadow: var(--shadow-md); }
        [data-theme="dark"] .referral-column { background: #1E293B; border-color: #334155; }
        [data-theme="dark"] .referral-column:hover { border-color: #6EA8FE; }
        
        .referral-column .column-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            border-bottom: 3px solid var(--border-color);
            padding-bottom: 10px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .referral-column .column-title .badge-count {
            background: var(--primary);
            color: white;
            padding: 1px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            margin-left: auto;
        }
        .referral-column .column-title .badge-count.success { background: var(--success); }
        
        .column-internal .column-title { color: var(--primary); }
        .column-internal .column-title i { color: var(--primary); }
        .column-external .column-title { color: var(--success); }
        .column-external .column-title i { color: var(--success); }
        
        .multi-select-wrapper {
            position: relative;
            width: 100%;
        }
        
        .multi-select-trigger {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-height: 38px;
        }
        .multi-select-trigger:hover {
            border-color: var(--primary);
        }
        .multi-select-trigger .trigger-text {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .multi-select-trigger .trigger-arrow {
            font-size: 0.7rem;
            color: var(--text-secondary);
            transition: transform 0.3s ease;
        }
        .multi-select-trigger.open .trigger-arrow {
            transform: rotate(180deg);
        }
        .multi-select-trigger .selected-count {
            background: var(--primary);
            color: white;
            padding: 1px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            margin-left: 8px;
        }
        .multi-select-trigger .selected-count.zero { background: var(--gray-300); color: var(--text-secondary); }
        
        .multi-select-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            max-height: 220px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: var(--shadow-lg);
        }
        .multi-select-dropdown.open {
            display: block;
        }
        .multi-select-dropdown .dropdown-search {
            padding: 6px 10px;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            background: var(--bg-card);
            z-index: 1;
        }
        .multi-select-dropdown .dropdown-search input {
            width: 100%;
            padding: 5px 8px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.75rem;
            background: var(--bg-body);
            color: var(--text-primary);
            outline: none;
        }
        .multi-select-dropdown .dropdown-search input:focus {
            border-color: var(--primary);
        }
        .multi-select-dropdown .dropdown-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            border-bottom: 1px solid var(--border-color);
        }
        .multi-select-dropdown .dropdown-item:hover {
            background: var(--primary-bg);
        }
        .multi-select-dropdown .dropdown-item.checked {
            background: var(--primary-bg);
        }
        .multi-select-dropdown .dropdown-item input[type="checkbox"] {
            width: 15px;
            height: 15px;
            accent-color: var(--primary);
            cursor: pointer;
            flex-shrink: 0;
        }
        .multi-select-dropdown .dropdown-item .item-info {
            flex: 1;
            min-width: 0;
        }
        .multi-select-dropdown .dropdown-item .item-name {
            font-weight: 500;
            font-size: 0.78rem;
            color: var(--text-primary);
        }
        .multi-select-dropdown .dropdown-item .item-details {
            font-size: 0.58rem;
            color: var(--text-secondary);
        }
        .multi-select-dropdown .dropdown-item .item-check {
            color: var(--text-secondary);
            font-size: 0.7rem;
        }
        .multi-select-dropdown .dropdown-item.checked .item-check {
            color: var(--primary);
        }
        .multi-select-dropdown .dropdown-actions {
            padding: 6px 10px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 6px;
            position: sticky;
            bottom: 0;
            background: var(--bg-card);
        }
        .multi-select-dropdown .dropdown-actions button {
            font-size: 0.6rem;
            padding: 3px 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .multi-select-dropdown .dropdown-actions button:hover {
            background: var(--primary-bg);
            border-color: var(--primary);
            color: var(--primary);
        }
        .multi-select-dropdown .dropdown-actions button.select-all { color: var(--primary); border-color: var(--primary); }
        .multi-select-dropdown .dropdown-actions button.select-all:hover { background: var(--primary); color: white; }
        
        .doctor-select-wrapper select {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
            appearance: none;
            -webkit-appearance: none;
            padding-right: 30px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 10 10'%3E%3Cpath fill='%2364748B' d='M5 7L1 3h8z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 10px;
            min-height: 38px;
        }
        .doctor-select-wrapper select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        [data-theme="dark"] .doctor-select-wrapper select { background: #1E293B; border-color: #334155; color: #F1F5F9; }
        [data-theme="dark"] .doctor-select-wrapper select:focus { border-color: #6EA8FE; box-shadow: 0 0 0 3px rgba(110, 168, 254, 0.15); }
        
        .doctor-queue-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 12px;
            font-size: 0.55rem;
            font-weight: 600;
            margin-left: 4px;
        }
        .doctor-queue-badge.green { background: #D1FAE5; color: #059669; }
        .doctor-queue-badge.yellow { background: #FEF3C7; color: #D97706; }
        .doctor-queue-badge.orange { background: #FED7AA; color: #EA580C; }
        .doctor-queue-badge.red { background: #FEE2E2; color: #DC2626; }
        
        .doctor-info-text {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
            padding: 6px 10px;
            background: var(--gray-50);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        .doctor-info-text .status-item { display: flex; align-items: center; gap: 4px; font-size: 0.65rem; color: var(--text-secondary); }
        .doctor-info-text .status-dot { display: inline-block; width: 5px; height: 5px; border-radius: 50%; }
        .doctor-info-text .status-dot.online { background: #059669; animation: pulse-dot 2s infinite; }
        .doctor-info-text .status-dot.offline { background: #94A3B8; }
        @keyframes pulse-dot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(0.8); } }
        
        .form-label {
            display: block;
            font-size: 0.68rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 3px;
        }
        .form-label .optional {
            font-weight: 400;
            color: var(--text-secondary);
            font-size: 0.6rem;
        }
        .form-control {
            width: 100%;
            padding: 6px 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.78rem;
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
        
        textarea.form-control { resize: vertical; min-height: 45px; }
        select.form-control { appearance: auto; cursor: pointer; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
            min-height: 38px;
        }
        .btn-success { 
            background: #059669; 
            color: white; 
            box-shadow: 0 2px 8px rgba(5,150,105,0.25);
            width: 100%;
            justify-content: center;
        }
        .btn-success:hover { 
            background: #047857; 
            transform: translateY(-2px); 
            box-shadow: 0 4px 16px rgba(5,150,105,0.35); 
        }
        .btn-primary { 
            background: var(--primary); 
            color: white; 
            box-shadow: 0 2px 8px rgba(11,94,215,0.25);
            width: 100%;
            justify-content: center;
        }
        .btn-primary:hover { 
            background: var(--primary-dark); 
            transform: translateY(-2px); 
            box-shadow: 0 4px 16px rgba(11,94,215,0.35); 
        }
        .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
        .btn-outline:hover { background: var(--gray-50); border-color: var(--primary); color: var(--primary); }
        .btn-sm { padding: 4px 12px; font-size: 0.65rem; min-height: 28px; }
        
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }
        
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
        
        .expert-other-wrapper { display: none; margin-top: 4px; }
        .expert-other-wrapper.show { display: block; }
        
        .mt-2 { margin-top: 6px; }
        .mt-3 { margin-top: 10px; }
        .mb-3 { margin-bottom: 10px; }
        .text-danger { color: #EF4444; }
        .text-sm { font-size: 0.8rem; }
        .text-xs { font-size: 0.65rem; }
        .text-gray-400 { color: var(--text-secondary); }
        .flex-wrap { flex-wrap: wrap; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-2 { gap: 6px; }
        .gap-3 { gap: 10px; }
        .overflow-x-auto { overflow-x: auto; }
        .text-center { text-align: center; }
        .py-2 { padding-top: 6px; padding-bottom: 6px; }
        .full-width { grid-column: span 2; }
        
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
        
        .external-no-doctor-note {
            font-size: 0.6rem;
            color: var(--text-secondary);
            background: var(--gray-50);
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px dashed var(--border-color);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .external-no-doctor-note i { color: var(--success); }
        
        .form-actions {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid var(--border-color);
        }
        
        .referral-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #FEF3C7;
            color: #D97706;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.55rem;
            font-weight: 600;
            border: 1px solid #D97706;
        }
        [data-theme="dark"] .referral-badge {
            background: #3D2E0A;
            color: #FBBF24;
            border-color: #FBBF24;
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 14px; }
            .referral-columns { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .page-header-custom { padding: 14px 16px; }
            .page-header-custom .page-title { font-size: 1.1rem; }
            .referral-column { padding: 12px 14px; }
            .grid-2 { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .referral-column { padding: 8px 10px; }
        }
        
        .multi-select-dropdown::-webkit-scrollbar { width: 4px; }
        .multi-select-dropdown::-webkit-scrollbar-thumb { background: var(--primary-light); border-radius: 4px; }
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
                <span class="referral-badge">
                    <i class="fas fa-exchange-alt"></i> Referral
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-share-alt"></i>
                <span class="header-badge"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?></span>
                <span class="header-badge"><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($user_full_name) ?></span>
                <span class="header-badge"><i class="fas fa-users"></i> <?= count($patients_list) ?> patients</span>
                <span class="header-badge" style="background:rgba(52,211,153,0.15);border-color:rgba(52,211,153,0.1);">
                    <i class="fas fa-check-circle"></i> Status: Assigned
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.15);border-color:rgba(52,211,153,0.1);">
                    <i class="fas fa-sync-alt"></i> Doctor ID changes to receiver
                </span>
            </p>
        </div>
        <div class="no-print" style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="my_patients.php" class="btn-outline-light"><i class="fas fa-arrow-left"></i> Back</a>
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
    <!-- REFERRAL FORM - TWO COLUMN LAYOUT -->
    <!-- ================================================================ -->
    <div class="referral-columns">
        
        <!-- ============================================================ -->
        <!-- LEFT COLUMN: INTERNAL REFERRAL -->
        <!-- ============================================================ -->
        <div class="referral-column column-internal">
            <div class="column-title">
                <i class="fas fa-hospital"></i>
                Internal Referral
                <span class="badge-count" id="internalCountBadge">0 selected</span>
            </div>
            
            <form method="POST" action="" id="internalForm">
                <input type="hidden" name="action" value="submit_internal">
                
                <!-- Patients Multi-Select Dropdown with Checkboxes -->
                <div class="form-group">
                    <label class="form-label">Select Patients <span class="text-danger">*</span></label>
                    <div class="multi-select-wrapper" id="multiSelectWrapper">
                        <div class="multi-select-trigger" id="multiSelectTrigger" onclick="toggleDropdown()">
                            <span class="trigger-text" id="triggerText">Select patients...</span>
                            <span class="selected-count zero" id="selectedCountBadge">0</span>
                            <span class="trigger-arrow"><i class="fas fa-chevron-down"></i></span>
                        </div>
                        <div class="multi-select-dropdown" id="multiSelectDropdown">
                            <div class="dropdown-search">
                                <input type="text" id="searchPatients" placeholder="Search patients..." onkeyup="filterPatients()">
                            </div>
                            <div id="patientOptions">
                                <?php if (count($patients_list) > 0): ?>
                                    <?php foreach ($patients_list as $p): ?>
                                        <div class="dropdown-item" data-patient-id="<?= $p['id'] ?>" onclick="togglePatientItem(this)">
                                            <input type="checkbox" name="selected_patients[]" value="<?= $p['id'] ?>" id="patient_<?= $p['id'] ?>" onclick="event.stopPropagation(); updateSelection();">
                                            <div class="item-info">
                                                <div class="item-name"><?= htmlspecialchars($p['full_name']) ?></div>
                                                <div class="item-details">
                                                    ID: <?= htmlspecialchars($p['patient_id']) ?> • 
                                                    <?= htmlspecialchars($p['gender'] ?? 'N/A') ?> • 
                                                    <?= !empty($p['date_of_birth']) ? calculateAge($p['date_of_birth']) . ' yrs' : 'N/A' ?>
                                                    <?php if (!empty($p['phone'])): ?>
                                                        • <?= htmlspecialchars($p['phone']) ?>
                                                    <?php endif; ?>
                                                    <?php if (!empty($p['visit_number'])): ?>
                                                        <span class="badge badge-info"><?= htmlspecialchars($p['visit_number']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="item-check"><i class="fas fa-check-circle"></i></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4 text-gray-400">
                                        <p>No patients assigned to you</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="dropdown-actions">
                                <button type="button" class="select-all" onclick="selectAllPatients()">✅ Select All</button>
                                <button type="button" onclick="deselectAllPatients()">✖ Deselect All</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Doctor Selection -->
                <div class="form-group">
                    <label class="form-label">Refer To Doctor <span class="text-danger">*</span></label>
                    <div class="doctor-select-wrapper">
                        <select name="referred_to_doctor" class="form-control" required id="doctorSelect">
                            <option value="">-- Select Doctor --</option>
                            <?php if (count($doctors) > 0): ?>
                                <?php if ($online_count > 0): ?>
                                    <optgroup label="🟢 Online Doctors (<?= $online_count ?>)">
                                        <?php foreach ($doctors as $doctor): ?>
                                            <?php if ($doctor['is_online'] == 1): 
                                                $pending = $doctor['pending_patients'] ?? 0;
                                                $queue_class = $pending == 0 ? 'green' : ($pending <= 3 ? 'yellow' : ($pending <= 7 ? 'orange' : 'red'));
                                            ?>
                                                <option value="<?= $doctor['id'] ?>" 
                                                        data-patients="<?= $doctor['total_patients'] ?? 0 ?>"
                                                        data-pending="<?= $pending ?>"
                                                        data-visits="<?= $doctor['pending_visits'] ?? 0 ?>">
                                                    🟢 <?= htmlspecialchars($doctor['full_name']) ?> 
                                                    <?= !empty($doctor['specialty']) ? '(' . htmlspecialchars($doctor['specialty']) . ')' : '' ?>
                                                    <span class="doctor-queue-badge <?= $queue_class ?>">⏳ <?= $pending ?></span>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php if ($offline_count > 0): ?>
                                    <optgroup label="⚪ Offline Doctors (<?= $offline_count ?>)">
                                        <?php foreach ($doctors as $doctor): ?>
                                            <?php if ($doctor['is_online'] == 0): 
                                                $pending = $doctor['pending_patients'] ?? 0;
                                                $queue_class = $pending == 0 ? 'green' : ($pending <= 3 ? 'yellow' : ($pending <= 7 ? 'orange' : 'red'));
                                            ?>
                                                <option value="<?= $doctor['id'] ?>" 
                                                        data-patients="<?= $doctor['total_patients'] ?? 0 ?>"
                                                        data-pending="<?= $pending ?>"
                                                        data-visits="<?= $doctor['pending_visits'] ?? 0 ?>">
                                                    ⚪ <?= htmlspecialchars($doctor['full_name']) ?> 
                                                    <?= !empty($doctor['specialty']) ? '(' . htmlspecialchars($doctor['specialty']) . ')' : '' ?>
                                                    <span class="doctor-queue-badge <?= $queue_class ?>">⏳ <?= $pending ?></span>
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
                    
                    <div class="doctor-info-text">
                        <?php if (count($doctors) > 0): ?>
                            <span class="status-item"><span class="status-dot online"></span><strong><?= $online_count ?></strong> Online</span>
                            <span class="status-item"><span class="status-dot offline"></span><strong><?= $offline_count ?></strong> Offline</span>
                            <span class="status-item"><i class="fas fa-users"></i> <strong><?= count($doctors) ?></strong> doctor(s)</span>
                        <?php else: ?>
                            <span class="status-item" style="color:var(--danger);"><i class="fas fa-exclamation-triangle"></i> No other doctors found</span>
                        <?php endif; ?>
                    </div>
                    <div id="selectedDoctorDetails" style="display:none;margin-top:4px;padding:4px 10px;background:var(--primary-bg);border-radius:6px;border-left:3px solid var(--primary);">
                        <div id="selectedDoctorInfo"></div>
                    </div>
                </div>
                
                <!-- Reason - OPTIONAL -->
                <div class="form-group">
                    <label class="form-label">Reason for Referral <span class="optional">(Optional)</span></label>
                    <textarea name="reason" class="form-control" rows="2" placeholder="Explain why the patient(s) are being referred (optional)..."></textarea>
                </div>
                
                <!-- Additional Notes -->
                <div class="form-group">
                    <label class="form-label">Additional Notes <span class="optional">(Optional)</span></label>
                    <textarea name="internal_notes" class="form-control" rows="2" placeholder="Any additional notes for the receiving doctor..."></textarea>
                </div>
                
                <!-- Submit Button - GREEN -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-success" id="submitInternalBtn">
                        <i class="fas fa-paper-plane"></i> Submit Internal Referral
                    </button>
                    <div style="text-align:center;margin-top:4px;font-size:0.55rem;color:var(--success);">
                        <i class="fas fa-check-circle"></i> Patient will be <strong>assigned</strong> to the doctor
                    </div>
                    <div style="text-align:center;margin-top:2px;font-size:0.5rem;color:var(--primary-light);">
                        <i class="fas fa-info-circle"></i> Doctor ID will change from <?= $user_id ?> to selected doctor
                    </div>
                </div>
            </form>
        </div>
        
        <!-- ============================================================ -->
        <!-- RIGHT COLUMN: EXTERNAL REFERRAL -->
        <!-- ============================================================ -->
        <div class="referral-column column-external">
            <div class="column-title">
                <i class="fas fa-globe-africa"></i>
                External Referral
                <span class="badge-count success">Single Patient</span>
            </div>
            
            <form method="POST" action="" id="externalForm">
                <input type="hidden" name="action" value="submit_external">
                
                <!-- Single Patient Selection -->
                <div class="form-group">
                    <label class="form-label">Select Patient <span class="text-danger">*</span></label>
                    <select name="external_patient_id" class="form-control" id="externalPatientSelect">
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($patients_list as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['full_name']) ?> 
                                (<?= htmlspecialchars($p['patient_id']) ?>) 
                                <?= !empty($p['phone']) ? '- ' . htmlspecialchars($p['phone']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Note: No doctor selection for external -->
                <div class="external-no-doctor-note">
                    <i class="fas fa-info-circle"></i>
                    Sent from <strong>Dr. <?= htmlspecialchars($user_full_name) ?></strong> - No doctor selection required
                </div>
                
                <!-- Facility Details -->
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Facility/Hospital Name <span class="text-danger">*</span></label>
                        <input type="text" name="external_facility" class="form-control" placeholder="e.g. Muhimbili National Hospital">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="external_phone" class="form-control" placeholder="e.g. +255 700 000 000">
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">Address</label>
                        <textarea name="external_address" class="form-control" rows="1" placeholder="Facility address..."></textarea>
                    </div>
                </div>
                
                <!-- Expert Type -->
                <div class="form-group">
                    <label class="form-label">Expert Type</label>
                    <select name="expert_type" class="form-control" id="expertTypeSelect" onchange="toggleExpertOther()">
                        <option value="">-- Select Expert Type --</option>
                        <?php foreach ($expert_types as $expert): ?>
                            <option value="<?= htmlspecialchars($expert) ?>"><?= htmlspecialchars($expert) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="expert-other-wrapper" id="expertOtherWrapper">
                        <input type="text" name="expert_type_other" class="form-control" placeholder="Specify expert type...">
                    </div>
                </div>
                
                <!-- Urgency -->
                <div class="form-group">
                    <label class="form-label">Urgency</label>
                    <select name="urgency" class="form-control">
                        <option value="routine">🟢 Routine</option>
                        <option value="urgent">🟡 Urgent</option>
                        <option value="emergency">🔴 Emergency</option>
                    </select>
                </div>
                
                <!-- Reason - OPTIONAL -->
                <div class="form-group">
                    <label class="form-label">Reason for Referral <span class="optional">(Optional)</span></label>
                    <textarea name="referral_reason" class="form-control" rows="2" placeholder="Explain why the patient is being referred (optional)..."></textarea>
                </div>
                
                <!-- Additional Notes -->
                <div class="form-group">
                    <label class="form-label">Additional Notes <span class="optional">(Optional)</span></label>
                    <textarea name="external_notes" class="form-control" rows="2" placeholder="Any additional notes..."></textarea>
                </div>
                
                <!-- Submit Button - BLUE -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submitExternalBtn">
                        <i class="fas fa-paper-plane"></i> Submit External Referral
                    </button>
                    <div style="text-align:center;margin-top:4px;font-size:0.55rem;color:var(--primary-light);">
                        <i class="fas fa-check-circle"></i> Visit status: Referred
                    </div>
                </div>
            </form>
        </div>
        
    </div>

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
    // MULTI-SELECT DROPDOWN - INTERNAL PATIENTS
    // ================================================================
    var dropdownOpen = false;
    
    function toggleDropdown() {
        var dropdown = document.getElementById('multiSelectDropdown');
        var trigger = document.getElementById('multiSelectTrigger');
        if (dropdownOpen) {
            dropdown.classList.remove('open');
            trigger.classList.remove('open');
        } else {
            dropdown.classList.add('open');
            trigger.classList.add('open');
        }
        dropdownOpen = !dropdownOpen;
    }
    
    document.addEventListener('click', function(e) {
        var wrapper = document.getElementById('multiSelectWrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            var dropdown = document.getElementById('multiSelectDropdown');
            var trigger = document.getElementById('multiSelectTrigger');
            if (dropdown) dropdown.classList.remove('open');
            if (trigger) trigger.classList.remove('open');
            dropdownOpen = false;
        }
    });
    
    function togglePatientItem(element) {
        var checkbox = element.querySelector('input[type="checkbox"]');
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            element.classList.toggle('checked', checkbox.checked);
            updateSelection();
        }
    }
    
    function updateSelection() {
        var checkboxes = document.querySelectorAll('#patientOptions input[type="checkbox"]');
        var checked = document.querySelectorAll('#patientOptions input[type="checkbox"]:checked');
        var count = checked.length;
        
        var badge = document.getElementById('internalCountBadge');
        if (badge) {
            badge.textContent = count + ' selected';
            badge.className = count > 0 ? 'badge-count success' : 'badge-count';
        }
        
        var triggerText = document.getElementById('triggerText');
        var selectedNames = [];
        checked.forEach(function(cb) {
            var item = cb.closest('.dropdown-item');
            if (item) {
                var nameEl = item.querySelector('.item-name');
                if (nameEl) selectedNames.push(nameEl.textContent.trim());
            }
        });
        if (selectedNames.length > 0) {
            triggerText.textContent = selectedNames.join(', ');
            if (selectedNames.length > 3) {
                triggerText.textContent = selectedNames.slice(0, 3).join(', ') + ' + ' + (selectedNames.length - 3) + ' more';
            }
        } else {
            triggerText.textContent = 'Select patients...';
        }
        
        var countBadge = document.getElementById('selectedCountBadge');
        if (countBadge) {
            countBadge.textContent = count;
            countBadge.className = count > 0 ? 'selected-count' : 'selected-count zero';
        }
        
        // Enable/disable submit button
        var submitBtn = document.getElementById('submitInternalBtn');
        if (submitBtn) {
            if (count === 0) {
                submitBtn.disabled = true;
                submitBtn.title = 'Please select at least one patient';
            } else {
                submitBtn.disabled = false;
                submitBtn.title = '';
            }
        }
    }
    
    function filterPatients() {
        var search = document.getElementById('searchPatients').value.toLowerCase();
        var items = document.querySelectorAll('#patientOptions .dropdown-item');
        items.forEach(function(item) {
            var text = item.textContent.toLowerCase();
            if (text.includes(search)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }
    
    function selectAllPatients() {
        var checkboxes = document.querySelectorAll('#patientOptions input[type="checkbox"]');
        checkboxes.forEach(function(cb) {
            cb.checked = true;
            var item = cb.closest('.dropdown-item');
            if (item) item.classList.add('checked');
        });
        updateSelection();
        showToast('Info', 'All patients selected', 'info');
    }
    
    function deselectAllPatients() {
        var checkboxes = document.querySelectorAll('#patientOptions input[type="checkbox"]');
        checkboxes.forEach(function(cb) {
            cb.checked = false;
            var item = cb.closest('.dropdown-item');
            if (item) item.classList.remove('checked');
        });
        updateSelection();
        showToast('Info', 'All patients deselected', 'info');
    }

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
                    .replace(/\s*\(.*\)\s*/, '')
                    .replace(/\s*<span.*<\/span>/, '')
                    .trim();
                
                var queueStatus = '';
                var queueColor = '';
                if (pendingPatients == 0) {
                    queueStatus = '🟢 No patients waiting';
                    queueColor = '#059669';
                } else if (pendingPatients <= 3) {
                    queueStatus = '🟡 ' + pendingPatients + ' waiting - Short';
                    queueColor = '#D97706';
                } else if (pendingPatients <= 7) {
                    queueStatus = '🟠 ' + pendingPatients + ' waiting - Medium';
                    queueColor = '#EA580C';
                } else {
                    queueStatus = '🔴 ' + pendingPatients + ' waiting - Long';
                    queueColor = '#DC2626';
                }
                
                var html = `
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:4px;">
                        <span style="font-weight:600;font-size:0.78rem;">👨‍⚕️ Dr. ${doctorName}</span>
                        <span style="font-size:0.6rem;color:var(--text-secondary);background:var(--bg-card);padding:1px 8px;border-radius:10px;border:1px solid var(--border-color);">
                            👥 ${totalPatients}
                        </span>
                        <span style="font-size:0.6rem;font-weight:600;padding:1px 8px;border-radius:10px;background:${queueColor}20;color:${queueColor};border:1px solid ${queueColor}40;">
                            ${queueStatus}
                        </span>
                    </div>
                `;
                
                selectedDoctorInfo.innerHTML = html;
                selectedDoctorDetails.style.display = 'block';
            });
        }
    });

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
        // Internal form validation
        var internalForm = document.getElementById('internalForm');
        if (internalForm) {
            internalForm.addEventListener('submit', function(e) {
                var checked = document.querySelectorAll('#patientOptions input[type="checkbox"]:checked');
                var doctorSelect = document.querySelector('select[name="referred_to_doctor"]');
                
                if (checked.length === 0) {
                    e.preventDefault();
                    showToast('Error', 'Please select at least one patient', 'error');
                    return false;
                }
                if (!doctorSelect || !doctorSelect.value) {
                    e.preventDefault();
                    showToast('Error', 'Please select a doctor', 'error');
                    return false;
                }
                
                var submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                }
                return true;
            });
        }
        
        // External form validation
        var externalForm = document.getElementById('externalForm');
        if (externalForm) {
            externalForm.addEventListener('submit', function(e) {
                var patientSelect = document.querySelector('select[name="external_patient_id"]');
                var facility = document.querySelector('input[name="external_facility"]');
                
                if (!patientSelect || !patientSelect.value) {
                    e.preventDefault();
                    showToast('Error', 'Please select a patient', 'error');
                    return false;
                }
                if (!facility || !facility.value.trim()) {
                    e.preventDefault();
                    showToast('Error', 'Please enter facility/hospital name', 'error');
                    return false;
                }
                
                var submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                }
                return true;
            });
        }
        
        // Initialize selection
        updateSelection();
    });

    // ================================================================
    // CONSOLE LOG
    // ================================================================
    console.log('✅ Refer Patient - FIXED: doctor_id changes to receiver');
    console.log('✅ UPDATE visits SET doctor_id = receiver');
    console.log('✅ UPDATE patients SET assigned_doctor_id = receiver');
    console.log('✅ Sender doctor: Patient disappears from pending');
    console.log('✅ Receiver doctor: Patient appears in pending');
    console.log('✅ Status: assigned');
    console.log('✅ Referred by Dr. X with reason shown');
    console.log('✅ External referral: NO doctor_id change');
</script>

</body>
</html>