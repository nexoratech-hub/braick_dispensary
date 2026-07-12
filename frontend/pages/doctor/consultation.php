<?php
// ================================================================
// FILE: frontend/pages/doctor/consultation.php
// DOCTOR - CONSULTATION PAGE (WITH LAB RESULTS)
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
$doctor_specialty = $_SESSION['specialty'] ?? 'General Practitioner';

// ================================================================
// GET PARAMETERS
// ================================================================
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if ($visit_id <= 0 && $patient_id <= 0) {
    header('Location: my_patients.php');
    exit;
}

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
// GET VISIT & PATIENT DETAILS
// ================================================================
if ($visit_id > 0) {
    $stmt = $db->prepare("
        SELECT v.*, 
               p.id as patient_id,
               p.full_name as patient_name,
               p.patient_id as patient_code,
               p.phone,
               p.email,
               p.date_of_birth,
               p.gender,
               p.address,
               p.blood_group,
               p.allergies,
               p.emergency_contact,
               p.created_at as patient_registered,
               u.full_name as doctor_name,
               u.specialty as doctor_specialty,
               r.full_name as receptionist_name,
               b.name as branch_name
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        LEFT JOIN branches b ON v.branch_id = b.id
        WHERE v.id = ? AND v.doctor_id = ?
    ");
    $stmt->execute([$visit_id, $doctor_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit) {
        header('Location: my_patients.php?error=visit_not_found');
        exit;
    }
    $patient_id = $visit['patient_id'];
} else {
    // Get latest active visit for this patient
    $stmt = $db->prepare("
        SELECT v.*, 
               p.id as patient_id,
               p.full_name as patient_name,
               p.patient_id as patient_code,
               p.phone,
               p.email,
               p.date_of_birth,
               p.gender,
               p.address,
               p.blood_group,
               p.allergies,
               p.emergency_contact,
               p.created_at as patient_registered,
               u.full_name as doctor_name,
               u.specialty as doctor_specialty,
               r.full_name as receptionist_name,
               b.name as branch_name
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        LEFT JOIN branches b ON v.branch_id = b.id
        WHERE v.patient_id = ? AND v.doctor_id = ? AND v.status NOT IN ('completed', 'cancelled')
        ORDER BY v.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$patient_id, $doctor_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit) {
        header('Location: my_patients.php?error=no_active_visit');
        exit;
    }
    $visit_id = $visit['id'];
}

// ================================================================
// GET PATIENT HISTORY
// ================================================================
$stmt = $db->prepare("
    SELECT v.*, u.full_name as doctor_name 
    FROM visits v
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE v.patient_id = ? 
    ORDER BY v.created_at DESC
");
$stmt->execute([$patient_id]);
$patient_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PREVIOUS PRESCRIPTIONS
// ================================================================
$stmt = $db->prepare("
    SELECT * FROM prescriptions 
    WHERE patient_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$previous_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET EXISTING CONSULTATION
// ================================================================
$consultation = null;
try {
    $stmt = $db->prepare("SELECT * FROM consultations WHERE visit_id = ?");
    $stmt->execute([$visit_id]);
    $consultation = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist
}

// ================================================================
// GET VITAL SIGNS
// ================================================================
$vital_signs = null;
try {
    $stmt = $db->prepare("SELECT * FROM consultation_vitals WHERE visit_id = ? ORDER BY recorded_at DESC LIMIT 1");
    $stmt->execute([$visit_id]);
    $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $vital_signs = null;
}

// ================================================================
// GET SYMPTOMS
// ================================================================
$symptoms_list = [];
try {
    $stmt = $db->prepare("SELECT symptom FROM consultation_symptoms WHERE visit_id = ?");
    $stmt->execute([$visit_id]);
    $symptoms_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $symptoms_list = [];
}

// ================================================================
// GET DIAGNOSES
// ================================================================
$diagnoses = [];
try {
    $stmt = $db->prepare("SELECT * FROM consultation_diagnosis WHERE visit_id = ?");
    $stmt->execute([$visit_id]);
    $diagnoses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $diagnoses = [];
}

// ================================================================
// GET LAB REQUESTS
// ================================================================
$lab_requests = [];
try {
    $stmt = $db->prepare("SELECT * FROM consultation_lab_requests WHERE visit_id = ?");
    $stmt->execute([$visit_id]);
    $lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lab_requests = [];
}

// ================================================================
// GET LAB RESULTS - NEW: After lab tests are completed
// ================================================================
$lab_results = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM lab_tests 
        WHERE visit_id = ? AND status = 'completed'
        ORDER BY completed_at DESC
    ");
    $stmt->execute([$visit_id]);
    $lab_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lab_results = [];
}

// ================================================================
// GET MEDICATIONS FOR THIS VISIT
// ================================================================
$consultation_medications = [];
try {
    $stmt = $db->prepare("SELECT * FROM consultation_medications WHERE visit_id = ?");
    $stmt->execute([$visit_id]);
    $consultation_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $consultation_medications = [];
}

// ================================================================
// GET PROCEDURES
// ================================================================
$procedures = [];
try {
    $stmt = $db->prepare("SELECT * FROM consultation_procedures WHERE visit_id = ?");
    $stmt->execute([$visit_id]);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $procedures = [];
}

// ================================================================
// GET FOLLOW-UP
// ================================================================
$follow_up = null;
try {
    $stmt = $db->prepare("SELECT * FROM follow_ups WHERE visit_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$visit_id]);
    $follow_up = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $follow_up = null;
}

// ================================================================
// GET REFERRAL
// ================================================================
$referral = null;
try {
    $stmt = $db->prepare("SELECT * FROM referrals WHERE visit_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$visit_id]);
    $referral = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $referral = null;
}

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    
    // Update visit basic info
    $symptoms = trim($_POST['symptoms'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? 'with_doctor';
    
    $stmt = $db->prepare("
        UPDATE visits 
        SET symptoms = ?, diagnosis = ?, notes = ?, status = ?, updated_at = NOW()
        WHERE id = ? AND doctor_id = ?
    ");
    $stmt->execute([$symptoms, $diagnosis, $notes, $status, $visit_id, $doctor_id]);
    
    // Save/Update consultation
    $chief_complaint = trim($_POST['chief_complaint'] ?? '');
    $physical_exam = trim($_POST['physical_exam'] ?? '');
    $consultation_notes = trim($_POST['consultation_notes'] ?? '');
    
    try {
        if ($consultation) {
            $stmt = $db->prepare("
                UPDATE consultations 
                SET chief_complaint = ?, physical_exam = ?, notes = ?, updated_at = NOW()
                WHERE visit_id = ?
            ");
            $stmt->execute([$chief_complaint, $physical_exam, $consultation_notes, $visit_id]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO consultations (visit_id, patient_id, doctor_id, chief_complaint, physical_exam, notes, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$visit_id, $patient_id, $doctor_id, $chief_complaint, $physical_exam, $consultation_notes]);
        }
    } catch (Exception $e) {
        // Table might not exist
    }
    
    // Save Vital Signs
    $temperature = trim($_POST['temperature'] ?? '');
    $blood_pressure = trim($_POST['blood_pressure'] ?? '');
    $pulse_rate = trim($_POST['pulse_rate'] ?? '');
    $respiratory_rate = trim($_POST['respiratory_rate'] ?? '');
    $oxygen_saturation = trim($_POST['oxygen_saturation'] ?? '');
    $weight = trim($_POST['weight'] ?? '');
    $height = trim($_POST['height'] ?? '');
    $bmi = trim($_POST['bmi'] ?? '');
    
    try {
        if ($vital_signs) {
            $stmt = $db->prepare("
                UPDATE consultation_vitals 
                SET temperature = ?, blood_pressure = ?, pulse_rate = ?, respiratory_rate = ?,
                    oxygen_saturation = ?, weight = ?, height = ?, bmi = ?, recorded_at = NOW()
                WHERE visit_id = ?
            ");
            $stmt->execute([$temperature, $blood_pressure, $pulse_rate, $respiratory_rate, 
                           $oxygen_saturation, $weight, $height, $bmi, $visit_id]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO consultation_vitals (visit_id, patient_id, temperature, blood_pressure, pulse_rate,
                    respiratory_rate, oxygen_saturation, weight, height, bmi, recorded_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$visit_id, $patient_id, $temperature, $blood_pressure, $pulse_rate,
                           $respiratory_rate, $oxygen_saturation, $weight, $height, $bmi]);
        }
    } catch (Exception $e) {
        // Table might not exist
    }
    
    // Save Symptoms
    $selected_symptoms = isset($_POST['symptoms_tags']) ? $_POST['symptoms_tags'] : [];
    if (!is_array($selected_symptoms)) {
        $selected_symptoms = [];
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM consultation_symptoms WHERE visit_id = ?");
        $stmt->execute([$visit_id]);
        
        foreach ($selected_symptoms as $symptom) {
            $symptom = trim($symptom);
            if (!empty($symptom)) {
                $stmt = $db->prepare("INSERT INTO consultation_symptoms (visit_id, symptom) VALUES (?, ?)");
                $stmt->execute([$visit_id, $symptom]);
            }
        }
    } catch (Exception $e) {
        // Table might not exist
    }
    
    // Save Diagnoses
    $primary_diagnosis = trim($_POST['primary_diagnosis'] ?? '');
    $secondary_diagnosis = trim($_POST['secondary_diagnosis'] ?? '');
    $icd_code = trim($_POST['icd_code'] ?? '');
    
    try {
        if (!empty($diagnoses)) {
            $stmt = $db->prepare("
                UPDATE consultation_diagnosis 
                SET primary_diagnosis = ?, secondary_diagnosis = ?, icd_code = ?, updated_at = NOW()
                WHERE visit_id = ?
            ");
            $stmt->execute([$primary_diagnosis, $secondary_diagnosis, $icd_code, $visit_id]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO consultation_diagnosis (visit_id, patient_id, primary_diagnosis, secondary_diagnosis, icd_code, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$visit_id, $patient_id, $primary_diagnosis, $secondary_diagnosis, $icd_code]);
        }
    } catch (Exception $e) {
        // Table might not exist
    }
    
    // ================================================================
    // SAVE LAB REQUESTS
    // ================================================================
    $lab_tests = isset($_POST['lab_tests']) ? $_POST['lab_tests'] : [];
    if (!is_array($lab_tests)) {
        $lab_tests = [];
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM consultation_lab_requests WHERE visit_id = ?");
        $stmt->execute([$visit_id]);
        
        foreach ($lab_tests as $test) {
            $test = trim($test);
            if (!empty($test)) {
                $stmt = $db->prepare("
                    INSERT INTO consultation_lab_requests (visit_id, test_name, status, created_at) 
                    VALUES (?, ?, 'pending', NOW())
                ");
                $stmt->execute([$visit_id, $test]);
            }
        }
    } catch (Exception $e) {
        // Table might not exist
    }
    
    // ================================================================
    // SAVE MEDICATIONS - Only if lab results exist or doctor skips
    // ================================================================
    $med_names = isset($_POST['med_name']) ? $_POST['med_name'] : [];
    $med_dosages = isset($_POST['med_dosage']) ? $_POST['med_dosage'] : [];
    $med_frequencies = isset($_POST['med_frequency']) ? $_POST['med_frequency'] : [];
    $med_durations = isset($_POST['med_duration']) ? $_POST['med_duration'] : [];
    $med_instructions = isset($_POST['med_instructions']) ? $_POST['med_instructions'] : [];
    
    try {
        $stmt = $db->prepare("DELETE FROM consultation_medications WHERE visit_id = ?");
        $stmt->execute([$visit_id]);
        
        for ($i = 0; $i < count($med_names); $i++) {
            $name = trim($med_names[$i] ?? '');
            if (!empty($name)) {
                $stmt = $db->prepare("
                    INSERT INTO consultation_medications (visit_id, medication_name, dosage, frequency, duration, instructions, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $visit_id, 
                    $name, 
                    trim($med_dosages[$i] ?? ''), 
                    trim($med_frequencies[$i] ?? ''), 
                    trim($med_durations[$i] ?? ''), 
                    trim($med_instructions[$i] ?? '')
                ]);
            }
        }
    } catch (Exception $e) {
        // Table might not exist
    }
    
    // Save Procedures
    $procedure_list = isset($_POST['procedures']) ? $_POST['procedures'] : [];
    if (!is_array($procedure_list)) {
        $procedure_list = [];
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM consultation_procedures WHERE visit_id = ?");
        $stmt->execute([$visit_id]);
        
        foreach ($procedure_list as $procedure) {
            $procedure = trim($procedure);
            if (!empty($procedure)) {
                $stmt = $db->prepare("INSERT INTO consultation_procedures (visit_id, procedure_name, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$visit_id, $procedure]);
            }
        }
    } catch (Exception $e) {
        // Table might not exist
    }
    
    // Save Follow-up
    $schedule_followup = isset($_POST['schedule_followup']) ? 1 : 0;
    $followup_date = trim($_POST['followup_date'] ?? '');
    $followup_notes = trim($_POST['followup_notes'] ?? '');
    
    try {
        if ($schedule_followup && !empty($followup_date)) {
            if ($follow_up) {
                $stmt = $db->prepare("
                    UPDATE follow_ups 
                    SET followup_date = ?, notes = ?, updated_at = NOW()
                    WHERE visit_id = ?
                ");
                $stmt->execute([$followup_date, $followup_notes, $visit_id]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO follow_ups (visit_id, patient_id, doctor_id, followup_date, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$visit_id, $patient_id, $doctor_id, $followup_date, $followup_notes]);
            }
        } elseif ($follow_up) {
            $stmt = $db->prepare("DELETE FROM follow_ups WHERE visit_id = ?");
            $stmt->execute([$visit_id]);
        }
    } catch (Exception $e) {
        // Table might not exist
    }
    
    // Save Referral
    $refer_patient = isset($_POST['refer_patient']) ? 1 : 0;
    $referral_hospital = trim($_POST['referral_hospital'] ?? '');
    $referral_reason = trim($_POST['referral_reason'] ?? '');
    
    try {
        if ($refer_patient && !empty($referral_hospital)) {
            if ($referral) {
                $stmt = $db->prepare("
                    UPDATE referrals 
                    SET hospital = ?, reason = ?, updated_at = NOW()
                    WHERE visit_id = ?
                ");
                $stmt->execute([$referral_hospital, $referral_reason, $visit_id]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO referrals (visit_id, patient_id, doctor_id, hospital, reason, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$visit_id, $patient_id, $doctor_id, $referral_hospital, $referral_reason]);
            }
        } elseif ($referral) {
            $stmt = $db->prepare("DELETE FROM referrals WHERE visit_id = ?");
            $stmt->execute([$visit_id]);
        }
    } catch (Exception $e) {
        // Table might not exist
    }
    
    // If Complete Consultation
    if ($action === 'complete') {
        $stmt = $db->prepare("
            UPDATE visits 
            SET status = 'completed', updated_at = NOW()
            WHERE id = ? AND doctor_id = ?
        ");
        $stmt->execute([$visit_id, $doctor_id]);
        
        // Create medical record
        try {
            $stmt = $db->prepare("
                INSERT INTO medical_records (patient_id, visit_id, doctor_id, diagnosis, notes, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$patient_id, $visit_id, $doctor_id, $diagnosis, $consultation_notes]);
        } catch (Exception $e) {
            // Table might not exist
        }
        
        $message = 'Consultation completed successfully! Prescriptions sent to Pharmacy, Lab requests sent to Laboratory.';
        $message_type = 'success';
        
        echo '<script>setTimeout(function(){ window.location.href = "my_patients.php?completed=1"; }, 2000);</script>';
    } else {
        $message = 'Consultation saved successfully!';
        $message_type = 'success';
        
        header('Location: consultation.php?visit_id=' . $visit_id . '&saved=1');
        exit;
    }
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
// CALCULATE AGE
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    return $age;
}

// ================================================================
// GET USER COLOR
// ================================================================
function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
    $index = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $index = ($index + ord($name[$i])) % count($colors);
    }
    return $colors[$index];
}

// ================================================================
// GET STATUS BADGE CLASS
// ================================================================
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'completed': return 'badge-success';
        case 'cancelled': return 'badge-danger';
        case 'in_progress': return 'badge-warning';
        case 'pending': return 'badge-warning';
        case 'dispensed': return 'badge-success';
        case 'with_doctor': return 'badge-primary';
        case 'assigned': return 'badge-info';
        case 'prescribed': return 'badge-purple';
        case 'lab_test': return 'badge-warning';
        default: return 'badge-info';
    }
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
// CHECK IF LAB RESULTS ARE AVAILABLE
// ================================================================
$lab_results_available = count($lab_results) > 0;
$has_pending_lab_requests = count($lab_requests) > 0;

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
                <i class="fas fa-stethoscope mr-2" style="color: #0B5ED7;"></i> Doctor Consultation
            </h1>
            <p class="page-subtitle">
                Patient consultation, examination and treatment
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>
                </span>
                <span class="ml-2 inline-flex bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs border border-purple-200">
                    <i class="fas fa-hashtag mr-1"></i> <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                </span>
                <?php if ($lab_results_available): ?>
                    <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                        <i class="fas fa-check-circle mr-1"></i> Lab Results Available
                    </span>
                <?php endif; ?>
                <?php if ($has_pending_lab_requests && !$lab_results_available): ?>
                    <span class="ml-2 inline-flex bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs border border-yellow-200">
                        <i class="fas fa-clock mr-1"></i> Pending Lab Results
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="my_patients.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print Consultation
            </button>
            <button type="submit" form="consultationForm" name="action" value="save" class="btn btn-blue btn-sm">
                <i class="fas fa-save"></i> Save Consultation
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['saved'])): ?>
        <div class="p-4 rounded-xl mb-4 bg-green-100 text-green-700 border border-green-200">
            <i class="fas fa-check-circle mr-2"></i> Consultation saved successfully!
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- CONSULTATION FORM -->
    <!-- ================================================================ -->
    <form method="POST" action="" id="consultationForm">

        <!-- ================================================================ -->
        <!-- SECTION 1: PATIENT INFORMATION -->
        <!-- ================================================================ -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <!-- PATIENT INFORMATION CARD -->
            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-user title-blue mr-2"></i> Patient Information
                </h3>
                <div class="patient-header">
                    <div class="patient-avatar-large" style="background: <?= getUserColor($visit['patient_name'] ?? 'Unknown') ?>;">
                        <?= strtoupper(substr($visit['patient_name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="patient-header-info">
                        <h4 class="patient-name"><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></h4>
                        <p class="patient-id">ID: <?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?></p>
                        <p class="patient-gender-age">
                            <?= htmlspecialchars($visit['gender'] ?? 'N/A') ?> • 
                            <?= calculateAge($visit['date_of_birth'] ?? '') ?> years
                        </p>
                    </div>
                </div>
                <div class="patient-info-grid">
                    <div><span class="info-label">Date of Birth</span><span class="info-value"><?= !empty($visit['date_of_birth']) ? date('M d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?></span></div>
                    <div><span class="info-label">Phone</span><span class="info-value"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></span></div>
                    <div><span class="info-label">Blood Group</span><span class="info-value"><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></span></div>
                    <div><span class="info-label">Allergies</span><span class="info-value"><?= htmlspecialchars($visit['allergies'] ?? 'None') ?></span></div>
                    <div><span class="info-label">Emergency Contact</span><span class="info-value"><?= htmlspecialchars($visit['emergency_contact'] ?? 'N/A') ?></span></div>
                    <div><span class="info-label">Address</span><span class="info-value"><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></span></div>
                    <div class="col-span-2"><span class="info-label">Registered</span><span class="info-value"><?= date('M d, Y', strtotime($visit['patient_registered'] ?? 'now')) ?></span></div>
                </div>
            </div>

            <!-- VISIT INFORMATION CARD -->
            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-clinic-medical title-green mr-2"></i> Visit Information
                </h3>
                <div class="visit-info-grid">
                    <div><span class="info-label">Visit Number</span><span class="info-value font-mono"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span></div>
                    <div><span class="info-label">Visit Type</span><span class="info-value"><?= ucfirst($visit['visit_type'] ?? 'New') ?></span></div>
                    <div><span class="info-label">Assigned Doctor</span><span class="info-value"><?= htmlspecialchars($visit['doctor_name'] ?? $doctor_name) ?></span></div>
                    <div><span class="info-label">Receptionist</span><span class="info-value"><?= htmlspecialchars($visit['receptionist_name'] ?? 'N/A') ?></span></div>
                    <div><span class="info-label">Branch</span><span class="info-value"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></span></div>
                    <div><span class="info-label">Visit Date</span><span class="info-value"><?= date('M d, Y', strtotime($visit['created_at'])) ?></span></div>
                    <div><span class="info-label">Visit Time</span><span class="info-value"><?= date('h:i A', strtotime($visit['created_at'])) ?></span></div>
                    <div><span class="info-label">Queue Number</span><span class="info-value">#<?= sprintf('%03d', $visit['id'] ?? 1) ?></span></div>
                    <div class="col-span-2">
                        <span class="info-label">Current Status</span>
                        <span class="badge <?= getStatusBadgeClass($visit['status']) ?>">
                            <?= ucfirst($visit['status'] ?? 'Pending') ?>
                        </span>
                    </div>
                </div>
            </div>

        </div>

        <!-- ================================================================ -->
        <!-- SECTION 2: PATIENT HISTORY -->
        <!-- ================================================================ -->
        <div class="consultation-card mb-6">
            <h3 class="card-title">
                <i class="fas fa-history title-blue mr-2"></i> Patient History
                <span class="text-sm font-normal text-gray-400">(<?= count($patient_history) ?> previous visits)</span>
            </h3>
            <div class="history-grid">
                <div class="history-item">
                    <span class="history-label">Previous Diagnoses</span>
                    <ul class="history-list">
                        <?php 
                        $diagnoses_history = [];
                        foreach ($patient_history as $ph) {
                            if (!empty($ph['diagnosis'])) {
                                $diagnoses_history[] = $ph['diagnosis'];
                            }
                        }
                        if (count($diagnoses_history) > 0) {
                            foreach (array_slice($diagnoses_history, 0, 5) as $d) {
                                echo '<li>' . htmlspecialchars(substr($d, 0, 50)) . (strlen($d) > 50 ? '...' : '') . '</li>';
                            }
                        } else {
                            echo '<li class="text-gray-400">No previous diagnoses</li>';
                        }
                        ?>
                    </ul>
                </div>
                <div class="history-item">
                    <span class="history-label">Previous Medications</span>
                    <ul class="history-list">
                        <?php if (count($previous_medications) > 0): ?>
                            <?php foreach (array_slice($previous_medications, 0, 5) as $pm): ?>
                                <li><?= htmlspecialchars($pm['medication_name'] ?? 'N/A') ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="text-gray-400">No previous medications</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="history-item">
                    <span class="history-label">Previous Visits</span>
                    <ul class="history-list">
                        <?php if (count($patient_history) > 0): ?>
                            <?php foreach (array_slice($patient_history, 0, 5) as $ph): ?>
                                <li><?= date('M d, Y', strtotime($ph['created_at'])) ?> - <?= ucfirst($ph['status'] ?? 'Pending') ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="text-gray-400">No previous visits</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- SECTION 3: VITAL SIGNS + CHIEF COMPLAINT -->
        <!-- ================================================================ -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-heartbeat title-red mr-2"></i> Vital Signs
                </h3>
                <div class="vitals-grid">
                    <div>
                        <label class="form-label">Temperature (°C)</label>
                        <input type="text" name="temperature" class="form-control" placeholder="36.5" value="<?= htmlspecialchars($vital_signs['temperature'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label">Blood Pressure</label>
                        <input type="text" name="blood_pressure" class="form-control" placeholder="120/80" value="<?= htmlspecialchars($vital_signs['blood_pressure'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label">Pulse Rate (bpm)</label>
                        <input type="text" name="pulse_rate" class="form-control" placeholder="72" value="<?= htmlspecialchars($vital_signs['pulse_rate'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label">Respiratory Rate</label>
                        <input type="text" name="respiratory_rate" class="form-control" placeholder="16" value="<?= htmlspecialchars($vital_signs['respiratory_rate'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label">SpO₂ (%)</label>
                        <input type="text" name="oxygen_saturation" class="form-control" placeholder="98" value="<?= htmlspecialchars($vital_signs['oxygen_saturation'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label">Weight (kg)</label>
                        <input type="text" name="weight" class="form-control" placeholder="70" value="<?= htmlspecialchars($vital_signs['weight'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label">Height (cm)</label>
                        <input type="text" name="height" class="form-control" placeholder="175" value="<?= htmlspecialchars($vital_signs['height'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label">BMI</label>
                        <input type="text" name="bmi" class="form-control" placeholder="22.9" value="<?= htmlspecialchars($vital_signs['bmi'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-comment-medical title-blue mr-2"></i> Chief Complaint
                </h3>
                <textarea name="chief_complaint" class="form-control" rows="6" placeholder="Patient complains of fever, headache and body weakness for the last three days..."><?= htmlspecialchars($consultation['chief_complaint'] ?? '') ?></textarea>
            </div>

        </div>

        <!-- ================================================================ -->
        <!-- SECTION 4: SYMPTOMS + PHYSICAL EXAMINATION -->
        <!-- ================================================================ -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-list-ul title-blue mr-2"></i> Symptoms
                </h3>
                <div class="symptoms-tags">
                    <?php
                    $symptom_options = ['Fever', 'Headache', 'Cough', 'Chest Pain', 'Vomiting', 'Diarrhea', 
                                        'Abdominal Pain', 'Dizziness', 'Fatigue', 'Shortness of Breath', 
                                        'Nausea', 'Muscle Pain', 'Joint Pain', 'Skin Rash', 'Sore Throat'];
                    foreach ($symptom_options as $symptom):
                        $checked = in_array($symptom, $symptoms_list) ? 'checked' : '';
                    ?>
                        <label class="symptom-tag <?= $checked ? 'active' : '' ?>">
                            <input type="checkbox" name="symptoms_tags[]" value="<?= $symptom ?>" <?= $checked ?>>
                            <?= $symptom ?>
                        </label>
                    <?php endforeach; ?>
                    <label class="symptom-tag custom">
                        <input type="text" placeholder="Add custom..." class="custom-symptom">
                    </label>
                </div>
            </div>

            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-stethoscope title-blue mr-2"></i> Physical Examination
                </h3>
                <textarea name="physical_exam" class="form-control" rows="6" placeholder="Record physical examination findings..."><?= htmlspecialchars($consultation['physical_exam'] ?? '') ?></textarea>
            </div>

        </div>

        <!-- ================================================================ -->
        <!-- SECTION 5: DIAGNOSIS -->
        <!-- ================================================================ -->
        <div class="consultation-card mb-6">
            <h3 class="card-title">
                <i class="fas fa-diagnoses title-blue mr-2"></i> Diagnosis
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="form-label">Primary Diagnosis</label>
                    <input type="text" name="primary_diagnosis" class="form-control" placeholder="Primary diagnosis..." value="<?= htmlspecialchars($diagnoses[0]['primary_diagnosis'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">Secondary Diagnosis</label>
                    <input type="text" name="secondary_diagnosis" class="form-control" placeholder="Secondary diagnosis..." value="<?= htmlspecialchars($diagnoses[0]['secondary_diagnosis'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">ICD Code (Optional)</label>
                    <input type="text" name="icd_code" class="form-control" placeholder="e.g. I10" value="<?= htmlspecialchars($diagnoses[0]['icd_code'] ?? '') ?>">
                </div>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- SECTION 6: LABORATORY REQUESTS (Step 1) -->
        <!-- ================================================================ -->
        <div class="consultation-card mb-6">
            <h3 class="card-title">
                <i class="fas fa-flask title-blue mr-2"></i> Laboratory Requests
                <span class="text-sm font-normal text-gray-400">(Request tests before prescribing)</span>
                <button type="button" class="btn btn-outline btn-sm ml-2" onclick="addLabTest()">
                    <i class="fas fa-plus"></i> Add Test
                </button>
            </h3>
            <div class="lab-info-box">
                <i class="fas fa-info-circle text-blue-500"></i>
                <span class="text-sm text-gray-600">Request lab tests first. Medications can only be prescribed after receiving lab results.</span>
            </div>
            <div id="labTestsContainer" class="mt-3">
                <?php if (count($lab_requests) > 0): ?>
                    <?php foreach ($lab_requests as $index => $lab): ?>
                        <div class="lab-test-row">
                            <input type="text" name="lab_tests[]" class="form-control" value="<?= htmlspecialchars($lab['test_name']) ?>" placeholder="Enter test name...">
                            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove(); updateSummary();">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="lab-test-row">
                        <select name="lab_tests[]" class="form-control">
                            <option value="">-- Select Test --</option>
                            <option value="Malaria Test">Malaria Test</option>
                            <option value="Urine Test">Urine Test</option>
                            <option value="Blood Sugar">Blood Sugar</option>
                            <option value="Full Blood Count">Full Blood Count</option>
                            <option value="Pregnancy Test">Pregnancy Test</option>
                            <option value="HIV Test">HIV Test</option>
                            <option value="Typhoid">Typhoid</option>
                            <option value="Liver Function">Liver Function</option>
                            <option value="Kidney Function">Kidney Function</option>
                            <option value="X-Ray">X-Ray</option>
                            <option value="Ultrasound">Ultrasound</option>
                            <option value="COVID-19 Test">COVID-19 Test</option>
                            <option value="Stool Test">Stool Test</option>
                            <option value="Sputum Test">Sputum Test</option>
                            <option value="Other">Other</option>
                        </select>
                        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove(); updateSummary();">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Send Lab Requests Button -->
            <div class="mt-3">
                <button type="button" class="btn btn-warning btn-sm" onclick="submitLabRequests()">
                    <i class="fas fa-paper-plane"></i> Send to Laboratory
                </button>
                <span class="text-xs text-gray-400 ml-2">Lab results will appear below when completed</span>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- SECTION 7: LABORATORY RESULTS (Step 2 - After lab tests) -->
        <!-- ================================================================ -->
        <div class="consultation-card mb-6 <?= $lab_results_available ? 'border-green-500' : 'border-gray-200' ?>">
            <h3 class="card-title">
                <i class="fas fa-file-medical-alt title-green mr-2"></i> Laboratory Results
                <span class="text-sm font-normal text-gray-400">
                    <?php if ($lab_results_available): ?>
                        <span class="text-green-600">✅ <?= count($lab_results) ?> result(s) available</span>
                    <?php elseif ($has_pending_lab_requests): ?>
                        <span class="text-yellow-600">⏳ Waiting for lab results...</span>
                    <?php else: ?>
                        <span class="text-gray-400">No lab results yet</span>
                    <?php endif; ?>
                </span>
            </h3>
            
            <?php if ($lab_results_available): ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Test Name</th>
                                <th>Result</th>
                                <th>Unit</th>
                                <th>Reference Range</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lab_results as $result): ?>
                                <tr>
                                    <td><?= htmlspecialchars($result['test_name'] ?? 'N/A') ?></td>
                                    <td class="font-medium"><?= htmlspecialchars($result['results'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($result['unit'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($result['reference_range'] ?? '') ?></td>
                                    <td>
                                        <span class="badge badge-success">
                                            <?= ucfirst($result['status'] ?? 'Completed') ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($result['completed_at'] ?? $result['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Lab Results Confirmation -->
                <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <label class="checkbox-label">
                        <input type="checkbox" name="lab_results_reviewed" value="1" id="labResultsReviewed">
                        I have reviewed the lab results and am ready to prescribe medication
                    </label>
                </div>
            <?php elseif ($has_pending_lab_requests): ?>
                <div class="text-center py-6 text-yellow-600">
                    <i class="fas fa-clock text-3xl block mb-2"></i>
                    <p>Lab requests have been sent. Please wait for results.</p>
                    <p class="text-sm text-gray-400">Refresh the page to check for updates</p>
                </div>
            <?php else: ?>
                <div class="text-center py-6 text-gray-400">
                    <i class="fas fa-flask text-3xl block mb-2"></i>
                    <p>No lab results available</p>
                    <p class="text-sm">Please request lab tests above first</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ================================================================ -->
        <!-- SECTION 8: MEDICATIONS / PRESCRIPTION (Step 3 - After lab results) -->
        <!-- ================================================================ -->
        <div class="consultation-card mb-6 <?= $lab_results_available ? '' : 'opacity-50 pointer-events-none' ?>">
            <h3 class="card-title">
                <i class="fas fa-prescription title-blue mr-2"></i> Medication / Prescription
                <?php if (!$lab_results_available): ?>
                    <span class="text-sm font-normal text-yellow-600">(🔒 Requires lab results)</span>
                <?php else: ?>
                    <span class="text-sm font-normal text-green-600">(✅ Lab results reviewed)</span>
                <?php endif; ?>
                <button type="button" class="btn btn-outline btn-sm ml-2" onclick="addMedication()" <?= !$lab_results_available ? 'disabled' : '' ?>>
                    <i class="fas fa-plus"></i> Add Medication
                </button>
            </h3>
            
            <?php if (!$lab_results_available && $has_pending_lab_requests): ?>
                <div class="lab-info-box bg-yellow-50 border-yellow-200 mb-3">
                    <i class="fas fa-lock text-yellow-600"></i>
                    <span class="text-sm text-yellow-700">Medications are locked until lab results are available. Please wait for results or request lab tests.</span>
                </div>
            <?php elseif (!$lab_results_available && !$has_pending_lab_requests): ?>
                <div class="lab-info-box bg-blue-50 border-blue-200 mb-3">
                    <i class="fas fa-info-circle text-blue-500"></i>
                    <span class="text-sm text-blue-700">Please request lab tests first before prescribing medication.</span>
                </div>
            <?php endif; ?>
            
            <div id="medicationsContainer">
                <?php if (count($consultation_medications) > 0 && $lab_results_available): ?>
                    <?php foreach ($consultation_medications as $index => $med): ?>
                        <div class="medication-row">
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                                <input type="text" name="med_name[]" class="form-control" placeholder="Medicine" value="<?= htmlspecialchars($med['medication_name']) ?>">
                                <input type="text" name="med_dosage[]" class="form-control" placeholder="Dosage" value="<?= htmlspecialchars($med['dosage']) ?>">
                                <input type="text" name="med_frequency[]" class="form-control" placeholder="Frequency" value="<?= htmlspecialchars($med['frequency']) ?>">
                                <input type="text" name="med_duration[]" class="form-control" placeholder="Duration" value="<?= htmlspecialchars($med['duration']) ?>">
                                <input type="text" name="med_instructions[]" class="form-control" placeholder="Instructions" value="<?= htmlspecialchars($med['instructions']) ?>">
                            </div>
                            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove(); updateSummary();">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($lab_results_available): ?>
                    <div class="medication-row">
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                            <input type="text" name="med_name[]" class="form-control" placeholder="Paracetamol">
                            <input type="text" name="med_dosage[]" class="form-control" placeholder="500mg">
                            <input type="text" name="med_frequency[]" class="form-control" placeholder="3 Times Daily">
                            <input type="text" name="med_duration[]" class="form-control" placeholder="5 Days">
                            <input type="text" name="med_instructions[]" class="form-control" placeholder="After Meals">
                        </div>
                        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove(); updateSummary();">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php else: ?>
                    <div class="text-center py-6 text-gray-400">
                        <i class="fas fa-prescription text-2xl block mb-2"></i>
                        <p>Add medications after receiving lab results</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- SECTION 9: PROCEDURES -->
        <!-- ================================================================ -->
        <div class="consultation-card mb-6">
            <h3 class="card-title">
                <i class="fas fa-syringe title-blue mr-2"></i> Procedures
                <button type="button" class="btn btn-outline btn-sm ml-2" onclick="addProcedure()">
                    <i class="fas fa-plus"></i> Add Procedure
                </button>
            </h3>
            <div id="proceduresContainer">
                <?php if (count($procedures) > 0): ?>
                    <?php foreach ($procedures as $index => $proc): ?>
                        <div class="procedure-row">
                            <select name="procedures[]" class="form-control">
                                <option value="">-- Select Procedure --</option>
                                <option value="Injection" <?= $proc['procedure_name'] === 'Injection' ? 'selected' : '' ?>>Injection</option>
                                <option value="Wound Dressing" <?= $proc['procedure_name'] === 'Wound Dressing' ? 'selected' : '' ?>>Wound Dressing</option>
                                <option value="Minor Surgery" <?= $proc['procedure_name'] === 'Minor Surgery' ? 'selected' : '' ?>>Minor Surgery</option>
                                <option value="Nebulization" <?= $proc['procedure_name'] === 'Nebulization' ? 'selected' : '' ?>>Nebulization</option>
                                <option value="IV Fluids" <?= $proc['procedure_name'] === 'IV Fluids' ? 'selected' : '' ?>>IV Fluids</option>
                                <option value="Other" <?= $proc['procedure_name'] === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove(); updateSummary();">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="procedure-row">
                        <select name="procedures[]" class="form-control">
                            <option value="">-- Select Procedure --</option>
                            <option value="Injection">Injection</option>
                            <option value="Wound Dressing">Wound Dressing</option>
                            <option value="Minor Surgery">Minor Surgery</option>
                            <option value="Nebulization">Nebulization</option>
                            <option value="IV Fluids">IV Fluids</option>
                            <option value="Other">Other</option>
                        </select>
                        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove(); updateSummary();">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- SECTION 10: FOLLOW-UP + REFERRAL -->
        <!-- ================================================================ -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-calendar-check title-blue mr-2"></i> Follow Up
                </h3>
                <div class="space-y-3">
                    <label class="checkbox-label">
                        <input type="checkbox" name="schedule_followup" value="1" <?= $follow_up ? 'checked' : '' ?>>
                        Schedule Follow-up
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Follow-up Date</label>
                            <input type="date" name="followup_date" class="form-control" value="<?= htmlspecialchars($follow_up['followup_date'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="form-label">Follow-up Notes</label>
                            <input type="text" name="followup_notes" class="form-control" placeholder="Notes..." value="<?= htmlspecialchars($follow_up['notes'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-ambulance title-blue mr-2"></i> Referral
                </h3>
                <div class="space-y-3">
                    <label class="checkbox-label">
                        <input type="checkbox" name="refer_patient" value="1" <?= $referral ? 'checked' : '' ?>>
                        Refer Patient
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Referral Hospital</label>
                            <input type="text" name="referral_hospital" class="form-control" placeholder="Hospital name..." value="<?= htmlspecialchars($referral['hospital'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="form-label">Referral Reason</label>
                            <input type="text" name="referral_reason" class="form-control" placeholder="Reason..." value="<?= htmlspecialchars($referral['reason'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ================================================================ -->
        <!-- SECTION 11: DOCTOR NOTES + CONSULTATION SUMMARY -->
        <!-- ================================================================ -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-notes-medical title-blue mr-2"></i> Doctor Notes
                </h3>
                <textarea name="consultation_notes" class="form-control" rows="6" placeholder="Additional clinical notes..."><?= htmlspecialchars($consultation['notes'] ?? '') ?></textarea>
            </div>

            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-file-alt title-blue mr-2"></i> Consultation Summary
                </h3>
                <div class="summary-content">
                    <div class="summary-item">
                        <span class="summary-label">Diagnosis:</span>
                        <span class="summary-value" id="summaryDiagnosis"><?= htmlspecialchars($diagnoses[0]['primary_diagnosis'] ?? 'Not entered') ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Lab Requests:</span>
                        <span class="summary-value" id="summaryLab"><?= count($lab_requests) > 0 ? count($lab_requests) . ' test(s)' : 'None' ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Lab Results:</span>
                        <span class="summary-value" id="summaryLabResults"><?= count($lab_results) > 0 ? count($lab_results) . ' result(s)' : 'Pending' ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Medications:</span>
                        <span class="summary-value" id="summaryMed"><?= count($consultation_medications) > 0 ? count($consultation_medications) . ' medication(s)' : 'None' ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Procedures:</span>
                        <span class="summary-value" id="summaryProc"><?= count($procedures) > 0 ? count($procedures) . ' procedure(s)' : 'None' ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Follow-up:</span>
                        <span class="summary-value"><?= $follow_up ? 'Scheduled' : 'None' ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Referral:</span>
                        <span class="summary-value"><?= $referral ? 'Yes' : 'No' ?></span>
                    </div>
                </div>
            </div>

        </div>

        <!-- ================================================================ -->
        <!-- SECTION 12: FORM ACTIONS -->
        <!-- ================================================================ -->
        <div class="consultation-card">
            <div class="form-actions">
                <button type="submit" form="consultationForm" name="action" value="save" class="btn btn-blue">
                    <i class="fas fa-save"></i> Save Draft
                </button>
                <button type="submit" form="consultationForm" name="action" value="complete" class="btn btn-success" 
                        onclick="return confirm('Are you sure you want to complete this consultation?\n\nThis will:\n✅ Update visit status to Completed\n✅ Send prescriptions to Pharmacy\n✅ Send lab requests to Laboratory\n✅ Create medical record\n\nThis action cannot be undone!')">
                    <i class="fas fa-check-circle"></i> Complete Consultation
                </button>
                <button type="button" class="btn btn-outline" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Consultation
                </button>
                <a href="my_patients.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>

    </form>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Consultation
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($doctor_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
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
    .consultation-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .consultation-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
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
    
    .title-blue { color: var(--primary); }
    .title-green { color: #059669; }
    .title-red { color: #EF4444; }
    
    .patient-header {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 12px 16px;
        background: var(--primary-bg);
        border-radius: 12px;
        margin-bottom: 16px;
    }
    
    [data-theme="dark"] .patient-header {
        background: #1E3A5F;
    }
    
    .patient-avatar-large {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
    }
    
    .patient-header-info .patient-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .patient-header-info .patient-id {
        font-size: 0.8rem;
        color: var(--text-secondary);
        font-family: monospace;
    }
    
    .patient-header-info .patient-gender-age {
        font-size: 0.85rem;
        color: var(--text-secondary);
    }
    
    .patient-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px 16px;
    }
    
    .visit-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 6px 16px;
    }
    
    .info-label {
        display: block;
        font-size: 0.65rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .info-value {
        display: block;
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--text-primary);
    }
    
    .col-span-2 { grid-column: span 2; }
    
    .history-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 16px;
    }
    
    .history-item .history-label {
        display: block;
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-secondary);
        margin-bottom: 4px;
    }
    
    .history-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .history-list li {
        font-size: 0.8rem;
        color: var(--text-primary);
        padding: 2px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .history-list li:last-child {
        border-bottom: none;
    }
    
    .vitals-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: 10px;
    }
    
    .symptoms-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 8px 0;
    }
    
    .symptom-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 14px;
        border-radius: 20px;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
        color: var(--text-secondary);
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .symptom-tag:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .symptom-tag.active {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }
    
    .symptom-tag input[type="checkbox"] {
        display: none;
    }
    
    .symptom-tag.custom {
        border-style: dashed;
        padding: 4px 8px;
    }
    
    .symptom-tag.custom input {
        border: none;
        background: transparent;
        outline: none;
        font-size: 0.8rem;
        color: var(--text-primary);
        width: 100px;
    }
    
    .symptom-tag.custom input::placeholder {
        color: var(--text-secondary);
    }
    
    .lab-test-row,
    .medication-row,
    .procedure-row {
        display: flex;
        gap: 8px;
        margin-bottom: 8px;
        align-items: center;
    }
    
    .lab-test-row .form-control,
    .procedure-row .form-control {
        flex: 1;
    }
    
    .medication-row .grid {
        flex: 1;
    }
    
    .summary-content {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px 16px;
    }
    
    .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .summary-item:last-child {
        border-bottom: none;
    }
    
    .summary-label {
        font-size: 0.8rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .summary-value {
        font-size: 0.85rem;
        color: var(--text-primary);
        font-weight: 500;
        text-align: right;
    }
    
    .form-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--text-secondary);
        margin-bottom: 4px;
    }
    
    .form-control {
        width: 100%;
        padding: 8px 12px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.85rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }
    
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }
    
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.6;
    }
    
    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--text-primary);
        cursor: pointer;
    }
    
    .checkbox-label input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--primary);
        cursor: pointer;
    }
    
    .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 16px;
        margin-top: 16px;
        border-top: 2px solid var(--border-color);
    }
    
    .lab-info-box {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        border-radius: 8px;
        border: 1px solid;
        background: var(--bg-card);
    }
    
    .lab-info-box i {
        font-size: 1.1rem;
    }
    
    .opacity-50 {
        opacity: 0.5;
    }
    
    .pointer-events-none {
        pointer-events: none;
    }
    
    .badge {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: white;
        border: none;
    }
    
    .badge-success { background: #059669; }
    .badge-danger { background: #EF4444; }
    .badge-warning { background: #D97706; }
    .badge-info { background: var(--primary); }
    .badge-primary { background: #0B5ED7; }
    .badge-purple { background: #7C3AED; }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        min-height: 44px;
    }
    
    .btn-blue {
        background: var(--primary);
        color: white;
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
    }
    .btn-blue:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.4);
    }
    
    .btn-success {
        background: #059669;
        color: white;
        box-shadow: 0 4px 14px rgba(5, 150, 105, 0.3);
    }
    .btn-success:hover {
        background: #047857;
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(5, 150, 105, 0.4);
    }
    
    .btn-warning {
        background: #D97706;
        color: white;
        box-shadow: 0 4px 14px rgba(217, 119, 6, 0.3);
    }
    .btn-warning:hover {
        background: #B45309;
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(217, 119, 6, 0.4);
    }
    
    .btn-danger {
        background: #EF4444;
        color: white;
        padding: 6px 12px;
        min-height: 34px;
    }
    .btn-danger:hover {
        background: #DC2626;
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
        padding: 4px 12px;
        font-size: 0.75rem;
        min-height: 32px;
    }
    
    .table-wrap { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .data-table th { text-align: left; padding: 8px 12px; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; color: var(--text-secondary); border-bottom: 2px solid var(--border-color); }
    .data-table td { padding: 8px 12px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); }
    .data-table tr:hover td { background: var(--primary-bg); }
    
    .space-y-3 > * + * { margin-top: 0.75rem; }
    .ml-2 { margin-left: 0.5rem; }
    .mr-2 { margin-right: 0.5rem; }
    .mb-3 { margin-bottom: 0.75rem; }
    .mb-6 { margin-bottom: 1.5rem; }
    .mt-3 { margin-top: 0.75rem; }
    .gap-3 { gap: 0.75rem; }
    .gap-4 { gap: 1rem; }
    .gap-6 { gap: 1.5rem; }
    
    .font-mono { font-family: monospace; }
    .text-sm { font-size: 0.875rem; }
    .text-xs { font-size: 0.75rem; }
    .text-gray-400 { color: var(--text-muted); }
    .text-gray-500 { color: var(--text-secondary); }
    .text-gray-600 { color: var(--text-secondary); }
    .text-green-600 { color: #059669; }
    .text-yellow-600 { color: #D97706; }
    .text-yellow-700 { color: #B45309; }
    .text-blue-500 { color: var(--primary); }
    .text-blue-700 { color: var(--primary-dark); }
    
    .bg-green-50 { background: #ECFDF5; }
    .bg-yellow-50 { background: #FEF3C7; }
    .bg-blue-50 { background: #EFF6FF; }
    .border-green-500 { border-color: #059669; }
    .border-green-200 { border-color: #A7F3D0; }
    .border-yellow-200 { border-color: #FDE68A; }
    .border-blue-200 { border-color: #BFDBFE; }
    .border-gray-200 { border-color: var(--border-color); }
    
    [data-theme="dark"] .bg-green-50 { background: #1A3A2A; }
    [data-theme="dark"] .bg-yellow-50 { background: #3D2E0A; }
    [data-theme="dark"] .bg-blue-50 { background: #1E3A5F; }
    [data-theme="dark"] .text-yellow-700 { color: #FBBF24; }
    [data-theme="dark"] .text-blue-700 { color: #6EA8FE; }
    
    @media (max-width: 1024px) {
        .visit-info-grid { grid-template-columns: 1fr 1fr; }
        .history-grid { grid-template-columns: 1fr 1fr; }
        .vitals-grid { grid-template-columns: 1fr 1fr; }
        .summary-content { grid-template-columns: 1fr; }
    }
    
    @media (max-width: 768px) {
        .patient-info-grid { grid-template-columns: 1fr; }
        .visit-info-grid { grid-template-columns: 1fr; }
        .history-grid { grid-template-columns: 1fr; }
        .vitals-grid { grid-template-columns: 1fr 1fr; }
        .consultation-card { padding: 14px 16px; }
        .patient-header { flex-direction: column; text-align: center; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
        .medication-row .grid { grid-template-columns: 1fr !important; }
        .lab-test-row, .procedure-row { flex-direction: column; }
        .lab-test-row .form-control, .procedure-row .form-control { width: 100%; }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .consultation-card { border: 1px solid #ddd !important; box-shadow: none !important; page-break-inside: avoid; }
        .page-header { border-bottom: 2px solid #0B5ED7 !important; }
        .form-actions { display: none !important; }
        .lab-info-box { display: none !important; }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // ADD LAB TEST
    // ================================================================
    function addLabTest() {
        var container = document.getElementById('labTestsContainer');
        var row = document.createElement('div');
        row.className = 'lab-test-row';
        row.innerHTML = `
            <select name="lab_tests[]" class="form-control">
                <option value="">-- Select Test --</option>
                <option value="Malaria Test">Malaria Test</option>
                <option value="Urine Test">Urine Test</option>
                <option value="Blood Sugar">Blood Sugar</option>
                <option value="Full Blood Count">Full Blood Count</option>
                <option value="Pregnancy Test">Pregnancy Test</option>
                <option value="HIV Test">HIV Test</option>
                <option value="Typhoid">Typhoid</option>
                <option value="Liver Function">Liver Function</option>
                <option value="Kidney Function">Kidney Function</option>
                <option value="X-Ray">X-Ray</option>
                <option value="Ultrasound">Ultrasound</option>
                <option value="COVID-19 Test">COVID-19 Test</option>
                <option value="Stool Test">Stool Test</option>
                <option value="Sputum Test">Sputum Test</option>
                <option value="Other">Other</option>
            </select>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove(); updateSummary();">
                <i class="fas fa-times"></i>
            </button>
        `;
        container.appendChild(row);
        updateSummary();
    }

    // ================================================================
    // SUBMIT LAB REQUESTS
    // ================================================================
    function submitLabRequests() {
        var labTests = document.querySelectorAll('#labTestsContainer select, #labTestsContainer input');
        var hasTests = false;
        labTests.forEach(function(input) {
            if (input.value && input.value.trim() !== '') {
                hasTests = true;
            }
        });
        
        if (!hasTests) {
            showToast('Warning', 'Please add at least one lab test request', 'warning');
            return;
        }
        
        if (confirm('Send lab requests to Laboratory?\n\nThese tests will be sent to the lab for processing.')) {
            document.getElementById('consultationForm').submit();
        }
    }

    // ================================================================
    // ADD MEDICATION
    // ================================================================
    function addMedication() {
        var labResultsAvailable = <?= $lab_results_available ? 'true' : 'false' ?>;
        if (!labResultsAvailable) {
            showToast('Locked', 'Please wait for lab results before prescribing medication', 'warning');
            return;
        }
        
        var container = document.getElementById('medicationsContainer');
        var row = document.createElement('div');
        row.className = 'medication-row';
        row.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <input type="text" name="med_name[]" class="form-control" placeholder="Medicine">
                <input type="text" name="med_dosage[]" class="form-control" placeholder="Dosage">
                <input type="text" name="med_frequency[]" class="form-control" placeholder="Frequency">
                <input type="text" name="med_duration[]" class="form-control" placeholder="Duration">
                <input type="text" name="med_instructions[]" class="form-control" placeholder="Instructions">
            </div>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove(); updateSummary();">
                <i class="fas fa-times"></i>
            </button>
        `;
        container.appendChild(row);
        updateSummary();
    }

    // ================================================================
    // ADD PROCEDURE
    // ================================================================
    function addProcedure() {
        var container = document.getElementById('proceduresContainer');
        var row = document.createElement('div');
        row.className = 'procedure-row';
        row.innerHTML = `
            <select name="procedures[]" class="form-control">
                <option value="">-- Select Procedure --</option>
                <option value="Injection">Injection</option>
                <option value="Wound Dressing">Wound Dressing</option>
                <option value="Minor Surgery">Minor Surgery</option>
                <option value="Nebulization">Nebulization</option>
                <option value="IV Fluids">IV Fluids</option>
                <option value="Other">Other</option>
            </select>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove(); updateSummary();">
                <i class="fas fa-times"></i>
            </button>
        `;
        container.appendChild(row);
        updateSummary();
    }

    // ================================================================
    // UPDATE SUMMARY
    // ================================================================
    function updateSummary() {
        var labCount = document.querySelectorAll('#labTestsContainer .lab-test-row').length;
        document.getElementById('summaryLab').textContent = labCount > 0 ? labCount + ' test(s)' : 'None';
        
        var medCount = document.querySelectorAll('#medicationsContainer .medication-row').length;
        document.getElementById('summaryMed').textContent = medCount > 0 ? medCount + ' medication(s)' : 'None';
        
        var procCount = document.querySelectorAll('#proceduresContainer .procedure-row').length;
        document.getElementById('summaryProc').textContent = procCount > 0 ? procCount + ' procedure(s)' : 'None';
    }

    // ================================================================
    // SYMPTOM TAGS
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.symptom-tag input[type="checkbox"]').forEach(function(cb) {
            cb.addEventListener('change', function() {
                this.closest('.symptom-tag').classList.toggle('active');
            });
        });
        
        document.querySelector('.custom-symptom')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var value = this.value.trim();
                if (value) {
                    var tag = document.createElement('label');
                    tag.className = 'symptom-tag active';
                    tag.innerHTML = `<input type="checkbox" name="symptoms_tags[]" value="${value}" checked> ${value}`;
                    this.closest('.symptoms-tags').insertBefore(tag, this.parentElement);
                    this.value = '';
                }
            }
        });
        
        // Lab Results Reviewed checkbox - enable medications
        document.getElementById('labResultsReviewed')?.addEventListener('change', function() {
            var medSection = document.querySelector('.consultation-card:has(.fa-prescription)');
            if (this.checked) {
                medSection.classList.remove('opacity-50', 'pointer-events-none');
                showToast('Ready', 'Lab results reviewed. You can now prescribe medication.', 'success');
            } else {
                medSection.classList.add('opacity-50', 'pointer-events-none');
            }
        });
    });

    // ================================================================
    // SHOW TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    console.log('%c👨‍⚕️ Consultation - <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Visit: <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>', 'font-size:12px; color:#059669;');
    console.log('%c📊 Status: <?= ucfirst($visit['status'] ?? 'Pending') ?>', 'font-size:12px; color:#64748B;');
    console.log('%c🧪 Lab Results Available: <?= $lab_results_available ? 'YES' : 'NO' ?>', 'font-size:12px; color:' + (<?= $lab_results_available ? "'#059669'" : "'#D97706'" ?>));
    console.log('%c✅ Workflow: Lab Requests → Lab Results → Medications', 'font-size:12px; color:#0B5ED7;');
</script>

</body>
</html>