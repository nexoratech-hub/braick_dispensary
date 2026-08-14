<?php
// ================================================================
// FILE: frontend/pages/doctor/refer_patient.php
// DOCTOR - REFER PATIENT (TWO-STEP)
// Step 1: Select patient from list
// Step 2: Referral form
// Session-based login (NO BYPASS)
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

$message = '';
$message_type = '';
$error_message = '';

// ================================================================
// VERIFY DOCTOR EXISTS AND IS ACTIVE
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
// GET PATIENT ID FROM URL
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;

// ================================================================
// GET ALL PATIENTS FOR THIS DOCTOR
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
// GET SELECTED PATIENT DETAILS (if patient_id is provided)
// ================================================================
$patient = null;
$last_visit = null;
$medications = [];
$procedures = [];
$diagnosis = '';
$treatment = '';

if ($patient_id > 0) {
    try {
        // Check if patient exists
        $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) {
            $error_message = '❌ Patient not found. Please select a valid patient.';
        } else {
            // Check if this doctor has access to this patient
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
        
        // Get latest visit info if patient exists
        if ($patient) {
            // Get last visit - TRY TO FIND ANY VISIT
            $stmt = $db->prepare("
                SELECT id, visit_number, diagnosis, symptoms, treatment, created_at, status
                FROM visits 
                WHERE patient_id = ? AND doctor_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$patient_id, $user_id]);
            $visit_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If no visit found with this doctor, get any visit
            if (!$visit_info) {
                $stmt = $db->prepare("
                    SELECT id, visit_number, diagnosis, symptoms, treatment, created_at, status
                    FROM visits 
                    WHERE patient_id = ?
                    ORDER BY created_at DESC
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
                $patient['treatment'] = $visit_info['treatment'] ?? null;
                $patient['last_visit_date'] = $visit_info['created_at'] ?? null;
                $patient['last_visit_status'] = $visit_info['status'] ?? null;
                $last_visit = $visit_info;
                $diagnosis = $visit_info['diagnosis'] ?? '';
                $treatment = $visit_info['treatment'] ?? '';
                
                // Get medications from last visit
                $stmt = $db->prepare("
                    SELECT pi.medication_name, pi.dosage, pi.frequency, pi.quantity, pi.instructions
                    FROM prescriptions p
                    JOIN prescription_items pi ON p.id = pi.prescription_id
                    WHERE p.visit_id = ?
                ");
                $stmt->execute([$visit_info['id']]);
                $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get procedures from last visit
                $stmt = $db->prepare("
                    SELECT bi.item_name, bi.quantity, bi.total_price
                    FROM bill_items bi
                    WHERE bi.bill_id IN (SELECT id FROM patient_bills WHERE visit_id = ?)
                    AND bi.item_type = 'procedure'
                    AND bi.status != 'cancelled'
                ");
                $stmt->execute([$visit_info['id']]);
                $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        error_log("Patient fetch error: " . $e->getMessage());
        $patient = null;
        $error_message = '❌ Database error occurred.';
    }
}

// ================================================================
// GET DOCTORS LIST (For Internal Referral)
// ================================================================
$doctors = [];
$online_count = 0;
$offline_count = 0;
try {
    $stmt = $db->prepare("
        SELECT id, full_name, specialty, phone, email, is_online, last_online, profile_pic
        FROM users 
        WHERE role = 'doctor' 
        AND id != ? 
        AND branch_id = ?
        AND status = 'active'
        ORDER BY is_online DESC, full_name ASC
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
}

// ================================================================
// GET SPECIALTIES
// ================================================================
$specialties = [
    'General Medicine',
    'Cardiology',
    'Dermatology',
    'Endocrinology',
    'Gastroenterology',
    'Hematology',
    'Infectious Diseases',
    'Nephrology',
    'Neurology',
    'Obstetrics & Gynecology',
    'Oncology',
    'Ophthalmology',
    'Orthopedics',
    'Otolaryngology (ENT)',
    'Pediatrics',
    'Psychiatry',
    'Pulmonology',
    'Radiology',
    'Rheumatology',
    'Surgery',
    'Urology'
];

// ================================================================
// EXPERT TYPES FOR EXTERNAL REFERRAL DROPDOWN
// ================================================================
$expert_types = [
    'Cardiology Expert',
    'Dermatology Expert',
    'Endocrinology Expert',
    'Gastroenterology Expert',
    'Hematology Expert',
    'Infectious Diseases Expert',
    'Nephrology Expert',
    'Neurology Expert',
    'Obstetrics & Gynecology Expert',
    'Oncology Expert',
    'Ophthalmology Expert',
    'Orthopedics Expert',
    'Otolaryngology (ENT) Expert',
    'Pediatrics Expert',
    'Psychiatry Expert',
    'Pulmonology Expert',
    'Radiology Expert',
    'Rheumatology Expert',
    'Surgery Expert',
    'Urology Expert',
    'General Medicine Expert',
    'Emergency Medicine Expert',
    'Intensive Care Expert',
    'Nutrition Expert',
    'Physiotherapy Expert',
    'Other (Specify)'
];

// ================================================================
// HANDLE FORM SUBMISSION - FIXED
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_referral') {
        $referral_type = $_POST['referral_type'] ?? '';
        $reason = trim($_POST['reason'] ?? '');          // Internal reason
        $referral_reason = trim($_POST['referral_reason'] ?? ''); // External reason
        $notes = trim($_POST['notes'] ?? '');
        $patient_id_post = (int)($_POST['patient_id'] ?? 0);
        $visit_id_post = isset($_POST['visit_id']) ? (int)$_POST['visit_id'] : 0;
        $urgency = $_POST['urgency'] ?? 'routine';
        
        // Internal referral fields
        $referred_to_doctor = isset($_POST['referred_to_doctor']) ? (int)$_POST['referred_to_doctor'] : 0;
        $internal_notes = trim($_POST['internal_notes'] ?? '');
        
        // External referral fields
        $external_facility = trim($_POST['external_facility'] ?? '');
        $external_address = trim($_POST['external_address'] ?? '');
        $external_phone = trim($_POST['external_phone'] ?? '');
        $external_email = trim($_POST['external_email'] ?? '');
        $expert_type = trim($_POST['expert_type'] ?? '');
        $expert_type_other = trim($_POST['expert_type_other'] ?? '');
        $clinical_summary = trim($_POST['clinical_summary'] ?? '');
        
        // If "Other" selected, use the custom value
        if ($expert_type === 'Other (Specify)' && !empty($expert_type_other)) {
            $expert_type = $expert_type_other;
        }
        
        // Validate
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
                
                // Build clinical notes with diagnosis, medications and procedures
                $clinical_notes_final = $notes;
                
                if ($last_visit) {
                    $clinical_notes_final .= "\n\n--- Last Visit Information ---\n";
                    $clinical_notes_final .= "Visit: " . ($last_visit['visit_number'] ?? 'N/A') . "\n";
                    $clinical_notes_final .= "Date: " . date('M d, Y', strtotime($last_visit['created_at'])) . "\n";
                    $clinical_notes_final .= "Status: " . ($last_visit['status'] ?? 'N/A') . "\n";
                    
                    if (!empty($diagnosis)) {
                        $clinical_notes_final .= "\nDiagnosis:\n" . $diagnosis . "\n";
                    }
                    
                    if (!empty($treatment)) {
                        $clinical_notes_final .= "\nTreatment Given:\n" . $treatment . "\n";
                    }
                    
                    if (count($medications) > 0) {
                        $clinical_notes_final .= "\nMedications Prescribed:\n";
                        foreach ($medications as $med) {
                            $clinical_notes_final .= "- " . ($med['medication_name'] ?? 'N/A');
                            if (!empty($med['dosage'])) {
                                $clinical_notes_final .= " " . $med['dosage'];
                            }
                            if (!empty($med['frequency'])) {
                                $clinical_notes_final .= " " . $med['frequency'];
                            }
                            if (!empty($med['quantity'])) {
                                $clinical_notes_final .= " x" . $med['quantity'];
                            }
                            $clinical_notes_final .= "\n";
                        }
                    }
                    
                    if (count($procedures) > 0) {
                        $clinical_notes_final .= "\nProcedures Performed:\n";
                        foreach ($procedures as $proc) {
                            $clinical_notes_final .= "- " . ($proc['item_name'] ?? 'N/A');
                            if (!empty($proc['quantity'])) {
                                $clinical_notes_final .= " x" . $proc['quantity'];
                            }
                            $clinical_notes_final .= "\n";
                        }
                    }
                    
                    if (!empty($patient['symptoms'])) {
                        $clinical_notes_final .= "\nSymptoms: " . $patient['symptoms'] . "\n";
                    }
                }
                
                // ================================================================
                // FIX: GET OR CREATE VISIT ID
                // ================================================================
                $visit_id_to_use = null;
                
                // 1. Try to use the provided visit_id
                if ($visit_id_post > 0) {
                    $visit_id_to_use = $visit_id_post;
                }
                
                // 2. If no visit_id, try to get the latest visit for this patient
                if ($visit_id_to_use === null && $patient_id_post > 0) {
                    $stmt = $db->prepare("
                        SELECT id FROM visits 
                        WHERE patient_id = ? AND doctor_id = ?
                        ORDER BY created_at DESC LIMIT 1
                    ");
                    $stmt->execute([$patient_id_post, $user_id]);
                    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($visit) {
                        $visit_id_to_use = $visit['id'];
                    }
                }
                
                // 3. If still no visit, try to get any visit for this patient
                if ($visit_id_to_use === null && $patient_id_post > 0) {
                    $stmt = $db->prepare("
                        SELECT id FROM visits 
                        WHERE patient_id = ?
                        ORDER BY created_at DESC LIMIT 1
                    ");
                    $stmt->execute([$patient_id_post]);
                    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($visit) {
                        $visit_id_to_use = $visit['id'];
                    }
                }
                
                // 4. If still no visit, create a new visit
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
                
                // ================================================================
                // INSERT REFERRAL - USING CORRECT SCHEMA
                // ================================================================
                if ($referral_type === 'internal') {
                    // Get doctor name for internal referral
                    $stmt = $db->prepare("SELECT full_name, phone, specialty FROM users WHERE id = ?");
                    $stmt->execute([$referred_to_doctor]);
                    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                    $doctor_name = $doctor['full_name'] ?? 'Unknown Doctor';
                    
                    // Internal referral - use to_doctor_id
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
                            ?, 'pending', NOW(), ?, ?, NOW()
                        )
                    ");
                    
                    $stmt->execute([
                        $referral_number,
                        $visit_id_to_use,
                        $patient_id_post,
                        $user_id,
                        $referral_type,
                        $referred_to_doctor,
                        $reason,
                        $clinical_notes_final,
                        $diagnosis,
                        $treatment,
                        $urgency,
                        $user_id,
                        $user_branch_id
                    ]);
                    
                    // Create notification for receiving doctor
                    $stmt = $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type, link, created_at)
                        VALUES (?, ?, ?, 'info', ?, NOW())
                    ");
                    $notif_message = "New referral from Dr. " . $user_full_name . " for patient " . ($patient['full_name'] ?? '');
                    $stmt->execute([
                        $referred_to_doctor,
                        "📋 New Referral Received",
                        $notif_message,
                        "referrals.php"
                    ]);
                    
                } else {
                    // External referral - use to_hospital_name, to_hospital_address, to_hospital_phone
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
                            ?, 'pending', NOW(), ?, ?, NOW()
                        )
                    ");
                    
                    // Build clinical notes with expert type
                    $clinical_notes_with_expert = $clinical_notes_final;
                    if (!empty($expert_type)) {
                        $clinical_notes_with_expert = "Expert Type: " . $expert_type . "\n\n" . $clinical_notes_final;
                    }
                    
                    $stmt->execute([
                        $referral_number,
                        $visit_id_to_use,
                        $patient_id_post,
                        $user_id,
                        $referral_type,
                        $external_facility,
                        $external_address,
                        $external_phone,
                        $referral_reason,
                        $clinical_notes_with_expert,
                        $diagnosis,
                        $treatment,
                        $urgency,
                        $user_id,
                        $user_branch_id
                    ]);
                }
                
                $referral_id = $db->lastInsertId();
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, action, details, created_at) 
                    VALUES (?, 'referral_created', ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    "Patient referred: " . ($patient['full_name'] ?? '') . " (#$referral_number) - Type: $referral_type"
                ]);
                
                $db->commit();
                
                $message = "✅ Patient referred successfully! Referral #: " . $referral_number;
                $message_type = 'success';
                
                echo '<script>
                    setTimeout(function(){
                        window.location.href = "my_patients.php?success=referral";
                    }, 2000);
                </script>';
                
            } catch (Exception $e) {
                $db->rollBack();
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
// GET LOGO HTML
// ================================================================
function getLogoHTML() {
    $logo_paths = [
        '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png',
        '/dispensary_system/frontend/assets/uploads/profiles/logo.png',
        '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.jpg',
        '/dispensary_system/frontend/assets/uploads/profiles/logo.jpg',
        '/dispensary_system/frontend/assets/img/braick_logo.png',
        '/dispensary_system/frontend/assets/img/logo.png'
    ];
    
    $logo_url = '';
    foreach ($logo_paths as $path) {
        $full_path = $_SERVER['DOCUMENT_ROOT'] . $path;
        if (file_exists($full_path)) {
            $logo_url = $path;
            break;
        }
    }
    
    if (!empty($logo_url)) {
        return '<img src="' . $logo_url . '" alt="Braick Dispensary" style="height:50px;width:auto;max-height:50px;border-radius:6px;object-fit:contain;">';
    }
    
    return '<div style="display:inline-block;background:#0B5ED7;color:white;padding:8px 20px;border-radius:8px;font-size:18px;font-weight:bold;font-family:Arial,sans-serif;">BRAICK</div>';
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

// ================================================================
// GET BRANCH NAME
// ================================================================
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

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refer Patient - Braick Dispensary</title>
    
    <style>
        /* ================================================================
           PAGE-SPECIFIC STYLES
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .page-header-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header-custom::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header-custom .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header-custom .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header-custom .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        [data-theme="dark"] .card {
            background: #1E293B;
            border-color: #334155;
        }
        [data-theme="dark"] .card:hover {
            border-color: #6EA8FE;
        }
        
        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .title-blue { color: var(--primary); }
        .title-purple { color: #7C3AED; }
        .title-green { color: #059669; }
        
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.85rem;
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
        
        [data-theme="dark"] .form-control {
            background: #1E293B;
            border-color: #334155;
            color: #F1F5F9;
        }
        [data-theme="dark"] .form-control:focus {
            border-color: #6EA8FE;
            box-shadow: 0 0 0 3px rgba(110, 168, 254, 0.15);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        
        select.form-control {
            appearance: auto;
            cursor: pointer;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
            min-height: 40px;
        }
        
        .btn-success {
            background: #059669;
            color: white;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
        }
        .btn-success:hover {
            background: #047857;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        }
        
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-success { background: var(--success-bg); color: var(--success); }
        
        .alert {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            border: 1px solid transparent;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        
        [data-theme="dark"] .alert-success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
        [data-theme="dark"] .alert-error { background: #3A1A1A; color: #F87171; border-color: #F87171; }
        
        .detail-row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .detail-row:last-child { border-bottom: none; }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 160px;
            flex-shrink: 0;
            font-size: 0.8rem;
        }
        .detail-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        
        .detail-value .phone-number {
            font-family: monospace;
            background: var(--bg-body);
            padding: 2px 8px;
            border-radius: 4px;
        }
        
        /* PATIENT SELECTION TABLE */
        .patient-select-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        
        .patient-select-card {
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 14px 16px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-primary);
            display: block;
        }
        
        .patient-select-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.12);
        }
        
        .patient-select-card .patient-name {
            font-weight: 600;
            font-size: 1rem;
        }
        .patient-select-card .patient-id {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-family: monospace;
        }
        .patient-select-card .patient-meta {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        
        .referral-type-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .referral-type-option {
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .referral-type-option:hover {
            border-color: var(--primary);
            background: var(--primary-bg);
            transform: translateY(-2px);
        }
        
        .referral-type-option.active {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }
        
        .referral-type-option .option-icon {
            font-size: 2rem;
            display: block;
            margin-bottom: 6px;
        }
        .referral-type-option .option-title {
            font-weight: 600;
            font-size: 0.95rem;
        }
        .referral-type-option .option-desc {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 2px;
        }
        
        .internal-form, .external-form {
            display: none;
            padding-top: 16px;
            border-top: 2px solid var(--border-color);
            margin-top: 16px;
        }
        .internal-form.active, .external-form.active {
            display: block;
        }
        
        .doctor-select-wrapper select {
            appearance: none;
            -webkit-appearance: none;
            padding-right: 36px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748B' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 12px;
        }
        
        .doctor-select-wrapper select option.doctor-online {
            background-color: var(--success-bg);
            color: var(--success);
            font-weight: 600;
        }
        .doctor-select-wrapper select option.doctor-offline {
            background-color: var(--gray-100);
            color: var(--gray-500);
        }
        
        .doctor-info-text {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 8px;
            padding: 8px 12px;
            background: var(--gray-50);
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }
        
        .doctor-info-text .status-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .doctor-info-text .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .doctor-info-text .status-dot.online {
            background: #059669;
            animation: pulse-dot 2s infinite;
        }
        .doctor-info-text .status-dot.offline {
            background: #94A3B8;
        }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }
        
        /* Expert Type - Show/Hide Other Input */
        .expert-other-wrapper {
            display: none;
            margin-top: 8px;
        }
        .expert-other-wrapper.show {
            display: block;
        }
        
        /* Urgency Badges */
        .urgency-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .urgency-badge.routine { background: var(--success-bg); color: var(--success); }
        .urgency-badge.urgent { background: var(--warning-bg); color: var(--warning); }
        .urgency-badge.emergency { background: var(--danger-bg); color: var(--danger); }
        
        .referral-letter {
            background: var(--bg-card);
            border: 2px solid var(--primary);
            border-radius: 14px;
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
            font-family: Arial, sans-serif;
            color: var(--text-primary);
        }
        
        .referral-letter .letter-header {
            text-align: center;
            border-bottom: 3px double var(--primary);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .referral-letter .letter-header .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .referral-letter .letter-header .logo-container img {
            height: 50px;
            width: auto;
            max-height: 50px;
            border-radius: 6px;
            object-fit: contain;
        }
        .referral-letter .letter-header h2 {
            color: var(--primary);
            font-size: 20px;
            margin: 0;
        }
        .referral-letter .letter-header .subtitle {
            font-size: 12px;
            color: var(--text-secondary);
            margin: 2px 0 0 0;
        }
        
        .referral-letter .letter-body .field-row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .referral-letter .letter-body .field-row .field-label {
            font-weight: 600;
            width: 160px;
            color: var(--text-secondary);
            flex-shrink: 0;
        }
        .referral-letter .letter-body .field-row .field-value {
            flex: 1;
            color: var(--text-primary);
        }
        
        .referral-letter .letter-footer {
            border-top: 2px solid var(--primary);
            padding-top: 15px;
            margin-top: 15px;
            text-align: center;
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        .toast-custom {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 14px 22px;
            border-radius: 10px;
            z-index: 9999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ffffff;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: #059669; }
        .toast-custom.error { background: #DC2626; }
        .toast-custom.info { background: #0B5ED7; }
        
        .mb-5 { margin-bottom: 20px; }
        .mt-4 { margin-top: 16px; }
        .mt-3 { margin-top: 12px; }
        .mr-2 { margin-right: 8px; }
        .mx-2 { margin-left: 8px; margin-right: 8px; }
        .text-danger { color: #EF4444; }
        .text-sm { font-size: 0.875rem; }
        .font-semibold { font-weight: 600; }
        .text-gray-600 { color: var(--text-secondary); }
        .text-green-600 { color: #059669; }
        .grid { display: grid; }
        .grid-cols-1 { grid-template-columns: 1fr; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .gap-4 { gap: 16px; }
        .md\:grid-cols-2 { grid-template-columns: 1fr 1fr; }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .sidebar-toggle-btn { display: block; }
        }
        
        @media (max-width: 768px) {
            .page-header-custom { padding: 16px 18px; }
            .page-header-custom .page-title { font-size: 1.3rem; }
            .card { padding: 14px 16px; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; }
            .referral-type-selector { grid-template-columns: 1fr; }
            .referral-letter { padding: 16px; }
            .referral-letter .letter-body .field-row { flex-direction: column; }
            .referral-letter .letter-body .field-row .field-label { width: 100%; }
            .doctor-info-text { flex-direction: column; align-items: flex-start; gap: 4px; }
            .md\:grid-cols-2 { grid-template-columns: 1fr; }
            .patient-select-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .card { padding: 10px 12px; }
            .referral-letter { padding: 12px; }
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
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-share-alt"></i>
                <?php if ($patient): ?>
                    Patient: <strong><?= htmlspecialchars($patient['full_name']) ?></strong>
                    <span class="separator">|</span>
                    ID: <?= htmlspecialchars($patient['patient_id']) ?>
                    <span class="separator">|</span>
                    Phone: <span class="phone-number"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                <?php else: ?>
                    Select a patient from the list below
                <?php endif; ?>
                <span class="separator">|</span>
                <span class="badge badge-info">Branch: <?= htmlspecialchars($doctor_branch_name) ?></span>
                <span class="separator">|</span>
                <span class="badge badge-info">
                    <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($user_full_name) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="my_patients.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= $error_message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STEP 1: SELECT PATIENT (Only if no patient selected) -->
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
            <div class="empty-state text-center py-6">
                <i class="fas fa-users text-4xl block mb-3" style="color: var(--border-color);"></i>
                <p class="text-gray-400">No patients assigned to you yet</p>
                <p class="text-xs text-gray-400">Patients will appear here once assigned to you</p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STEP 2: REFERRAL FORM (Only if patient selected) -->
    <!-- ================================================================ -->
    <?php if ($patient): ?>
    
    <!-- Patient Information with Emergency Contact -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-user-circle title-blue mr-2"></i>
            Patient Information
            <?php if ($patient['visit_number']): ?>
                <span class="badge badge-info ml-2">Last Visit: <?= htmlspecialchars($patient['visit_number']) ?></span>
            <?php endif; ?>
        </h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <div class="detail-row"><span class="detail-label">Full Name</span><span class="detail-value"><strong><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></strong></span></div>
            <div class="detail-row"><span class="detail-label">Patient ID</span><span class="detail-value"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Gender</span><span class="detail-value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Date of Birth</span><span class="detail-value"><?= !empty($patient['date_of_birth']) ? date('d/m/Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></span></div>
            <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Emergency Contact</span><span class="detail-value"><strong><?= htmlspecialchars($patient['emergency_contact'] ?? 'N/A') ?></strong></span></div>
            <div class="detail-row"><span class="detail-label">Blood Group</span><span class="detail-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Referred By</span><span class="detail-value"><strong>Dr. <?= htmlspecialchars($user_full_name) ?></strong> (<?= htmlspecialchars($user_specialty) ?>)</span></div>
            <?php if (!empty($patient['diagnosis'])): ?>
                <div class="detail-row" style="grid-column: span 2;"><span class="detail-label">Diagnosis</span><span class="detail-value"><?= htmlspecialchars($patient['diagnosis']) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($patient['symptoms'])): ?>
                <div class="detail-row" style="grid-column: span 2;"><span class="detail-label">Symptoms</span><span class="detail-value"><?= htmlspecialchars($patient['symptoms']) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($patient['visit_number'])): ?>
                <div class="detail-row" style="grid-column: span 2;"><span class="detail-label">Last Visit</span><span class="detail-value"><?= htmlspecialchars($patient['visit_number']) ?> (<?= !empty($patient['last_visit_date']) ? date('M d, Y', strtotime($patient['last_visit_date'])) : 'N/A' ?>)</span></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Referral Form -->
    <form method="POST" action="" id="referralForm">
        <input type="hidden" name="action" value="save_referral">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <input type="hidden" name="visit_id" value="<?= $visit_id ?>">
        
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-share-alt title-purple mr-2"></i>
                Referral Details
            </h3>
            
            <!-- Referral Type Selector -->
            <div class="referral-type-selector">
                <div class="referral-type-option active" onclick="selectReferralType('internal', this)" data-type="internal">
                    <span class="option-icon">🏥</span>
                    <div class="option-title">Internal Referral</div>
                    <div class="option-desc">Refer within this facility to another doctor</div>
                </div>
                <div class="referral-type-option" onclick="selectReferralType('external', this)" data-type="external">
                    <span class="option-icon">🌍</span>
                    <div class="option-title">External Referral</div>
                    <div class="option-desc">Refer to an outside facility or specialist</div>
                </div>
            </div>
            
            <input type="hidden" name="referral_type" id="referralType" value="internal">
            
            <!-- Internal Referral -->
            <div class="internal-form active" id="internalForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                                    <option value="<?= $doctor['id'] ?>" class="doctor-online">
                                                        🟢 <?= htmlspecialchars($doctor['full_name']) ?> 
                                                        <?= !empty($doctor['specialty']) ? '(' . htmlspecialchars($doctor['specialty']) . ')' : '' ?>
                                                        - Online
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                    
                                    <?php if ($offline_count > 0): ?>
                                        <optgroup label="⚪ Offline Doctors (<?= $offline_count ?>)">
                                            <?php foreach ($doctors as $doctor): ?>
                                                <?php if ($doctor['is_online'] == 0): ?>
                                                    <option value="<?= $doctor['id'] ?>" class="doctor-offline">
                                                        ⚪ <?= htmlspecialchars($doctor['full_name']) ?> 
                                                        <?= !empty($doctor['specialty']) ? '(' . htmlspecialchars($doctor['specialty']) . ')' : '' ?>
                                                        - Offline
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <option value="" disabled>⚠️ No other doctors available in this branch</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="doctor-info-text" id="doctorInfoText">
                            <?php if (count($doctors) > 0): ?>
                                <span class="status-item">
                                    <span class="status-dot online"></span>
                                    <strong><?= $online_count ?></strong> Online
                                </span>
                                <span class="status-item">
                                    <span class="status-dot offline"></span>
                                    <strong><?= $offline_count ?></strong> Offline
                                </span>
                                <span class="total-doctors">
                                    <i class="fas fa-users"></i> 
                                    <strong><?= count($doctors) ?></strong> doctor(s) in <strong><?= htmlspecialchars($doctor_branch_name) ?></strong>
                                </span>
                            <?php else: ?>
                                <span class="status-item" style="color:var(--danger);">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    No other doctors found in <strong><?= htmlspecialchars($doctor_branch_name) ?></strong> branch
                                </span>
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
                    <label class="form-label">Additional Notes</label>
                    <textarea name="internal_notes" class="form-control" rows="3" placeholder="Any additional notes for the receiving doctor..."></textarea>
                </div>
            </div>
            
            <!-- External Referral with Dropdown Expert Type -->
            <div class="external-form" id="externalForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                            <input type="text" name="expert_type_other" class="form-control" placeholder="Specify expert type..." style="margin-top:6px;">
                            <small class="text-xs text-gray-400">Please specify the expert type</small>
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
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">Address</label>
                        <textarea name="external_address" class="form-control" rows="2" placeholder="Facility address..."></textarea>
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
                
                <!-- SINGLE REASON FIELD - EXTERNAL -->
                <div class="form-group">
                    <label class="form-label">Reason for Referral <span class="text-danger">*</span></label>
                    <textarea name="referral_reason" class="form-control" rows="3" placeholder="Explain why the patient is being referred..." required></textarea>
                </div>
            </div>
            
            <!-- Common Fields -->
            <div class="form-group mt-4">
                <label class="form-label">Additional Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Any additional notes..."></textarea>
            </div>
            
            <!-- Clinical Summary with Diagnosis, Medications and Procedures -->
            <div class="form-group">
                <label class="form-label">Clinical Summary</label>
                <textarea name="clinical_summary" id="clinicalSummary" class="form-control" rows="6" placeholder="Summary of patient's clinical history, diagnosis, and treatment so far..."><?php 
                    if ($last_visit) {
                        echo "--- Last Visit Information ---\n";
                        echo "Visit: " . ($last_visit['visit_number'] ?? 'N/A') . "\n";
                        echo "Date: " . date('M d, Y', strtotime($last_visit['created_at'])) . "\n";
                        echo "Status: " . ($last_visit['status'] ?? 'N/A') . "\n";
                        
                        if (!empty($diagnosis)) {
                            echo "\n--- Diagnosis ---\n" . $diagnosis . "\n";
                        }
                        
                        if (!empty($treatment)) {
                            echo "\n--- Treatment Given ---\n" . $treatment . "\n";
                        }
                        
                        if (count($medications) > 0) {
                            echo "\n--- Medications Prescribed ---\n";
                            foreach ($medications as $med) {
                                echo "- " . ($med['medication_name'] ?? 'N/A');
                                if (!empty($med['dosage'])) {
                                    echo " " . $med['dosage'];
                                }
                                if (!empty($med['frequency'])) {
                                    echo " " . $med['frequency'];
                                }
                                if (!empty($med['quantity'])) {
                                    echo " x" . $med['quantity'];
                                }
                                echo "\n";
                            }
                        }
                        
                        if (count($procedures) > 0) {
                            echo "\n--- Procedures Performed ---\n";
                            foreach ($procedures as $proc) {
                                echo "- " . ($proc['item_name'] ?? 'N/A');
                                if (!empty($proc['quantity'])) {
                                    echo " x" . $proc['quantity'];
                                }
                                echo "\n";
                            }
                        }
                        
                        if (!empty($patient['symptoms'])) {
                            echo "\n--- Symptoms ---\n" . $patient['symptoms'] . "\n";
                        }
                    }
                ?></textarea>
                <?php if ($last_visit): ?>
                    <small class="text-xs text-gray-400 mt-1">
                        <i class="fas fa-info-circle"></i> Clinical summary includes diagnosis, treatment, medications, procedures, and symptoms from the last visit.
                    </small>
                <?php endif; ?>
            </div>
            
            <!-- External Referral Letter Preview -->
            <div class="mt-4" id="externalLetterPreview" style="display:none;">
                <h4 class="text-sm font-semibold text-gray-600 mb-3" style="color:var(--text-secondary);">
                    <i class="fas fa-file-pdf mr-2"></i> Referral Letter Preview
                </h4>
                <div class="referral-letter" id="referralLetter">
                    <div class="letter-header">
                        <div class="logo-container">
                            <?= getLogoHTML() ?>
                            <div>
                                <h2>BRAICK DISPENSARY</h2>
                                <p class="subtitle">Quality Healthcare Services</p>
                            </div>
                        </div>
                        <h2>REFERRAL LETTER</h2>
                    </div>
                    
                    <div class="letter-body">
                        <div class="field-row"><span class="field-label">Date:</span><span class="field-value" id="letterDate"><?= date('d/m/Y') ?></span></div>
                        <div class="field-row"><span class="field-label">Patient Name:</span><span class="field-value"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></span></div>
                        <div class="field-row"><span class="field-label">Patient ID:</span><span class="field-value"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span></div>
                        <div class="field-row"><span class="field-label">Phone:</span><span class="field-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span></div>
                        <div class="field-row"><span class="field-label">Emergency Contact:</span><span class="field-value"><?= htmlspecialchars($patient['emergency_contact'] ?? 'N/A') ?></span></div>
                        <div class="field-row"><span class="field-label">Age/Sex:</span><span class="field-value"><?= !empty($patient['date_of_birth']) ? calculateAge($patient['date_of_birth']) . ' yrs' : 'N/A' ?> / <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span></div>
                        <div class="field-row"><span class="field-label">Blood Group:</span><span class="field-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span></div>
                        <div class="field-row"><span class="field-label">Referred To:</span><span class="field-value" id="letterFacility">[Facility Name]</span></div>
                        <div class="field-row"><span class="field-label">Expert Type:</span><span class="field-value" id="letterExpertType">[Expert Type]</span></div>
                        <div class="field-row"><span class="field-label">Reason for Referral:</span><span class="field-value" id="letterReason">[Reason]</span></div>
                        <div class="field-row"><span class="field-label">Clinical Summary:</span><span class="field-value" id="letterSummary">[Clinical Summary]</span></div>
                        <div class="field-row"><span class="field-label">Referred By:</span><span class="field-value"><strong>Dr. <?= htmlspecialchars($user_full_name) ?></strong></span></div>
                        <div class="field-row"><span class="field-label">Specialty:</span><span class="field-value"><?= htmlspecialchars($user_specialty) ?></span></div>
                        <div class="field-row"><span class="field-label">Contact:</span><span class="field-value"><?= htmlspecialchars($doctor_branch_name) ?> | <?= htmlspecialchars($user_phone) ?> | <?= date('d/m/Y') ?></span></div>
                    </div>
                    
                    <div class="letter-footer">
                        <p>This is a computer generated referral letter. Please verify all information.</p>
                        <p><strong>Braick Dispensary</strong> - Quality Healthcare Services</p>
                    </div>
                </div>
                
                <div class="mt-3 flex gap-3">
                    <button type="button" class="btn btn-primary" onclick="printReferralLetter()">
                        <i class="fas fa-print"></i> Print Letter
                    </button>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="mt-4 flex flex-wrap gap-3" style="padding-top:16px;border-top:2px solid var(--border-color);">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-paper-plane"></i> Submit Referral
                </button>
                <button type="reset" class="btn btn-outline" onclick="resetForm()">
                    <i class="fas fa-undo"></i> Clear
                </button>
                <a href="refer_patient.php" class="btn btn-outline">
                    <i class="fas fa-users"></i> Change Patient
                </a>
                <a href="my_patients.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>
    </form>
    
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Refer Patient
            <span class="text-gray-300 mx-2">|</span>
            Dr. <?= htmlspecialchars($user_full_name) ?>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- Toast -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT - FULLY FIXED -->
<!-- ================================================================ -->
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
    // EXPERT TYPE - TOGGLE OTHER INPUT
    // ================================================================
    function toggleExpertOther() {
        var select = document.getElementById('expertTypeSelect');
        var otherWrapper = document.getElementById('expertOtherWrapper');
        
        if (select && select.value === 'Other (Specify)') {
            otherWrapper.classList.add('show');
        } else if (otherWrapper) {
            otherWrapper.classList.remove('show');
        }
        
        updateLetterPreview();
    }

    // ================================================================
    // SELECT REFERRAL TYPE - FIXED
    // ================================================================
    function selectReferralType(type, element) {
        document.querySelectorAll('.referral-type-option').forEach(function(btn) {
            btn.classList.remove('active');
        });
        if (element) {
            element.classList.add('active');
        }
        
        document.getElementById('referralType').value = type;
        
        var internalForm = document.getElementById('internalForm');
        var externalForm = document.getElementById('externalForm');
        var externalPreview = document.getElementById('externalLetterPreview');
        
        if (type === 'internal') {
            internalForm.classList.add('active');
            externalForm.classList.remove('active');
            if (externalPreview) externalPreview.style.display = 'none';
            
            // Set required fields
            document.querySelectorAll('.internal-form .form-control[required]').forEach(function(el) {
                el.setAttribute('required', 'required');
            });
            document.querySelectorAll('.external-form .form-control[required]').forEach(function(el) {
                el.removeAttribute('required');
            });
        } else {
            internalForm.classList.remove('active');
            externalForm.classList.add('active');
            if (externalPreview) externalPreview.style.display = 'block';
            
            // Set required fields
            document.querySelectorAll('.external-form .form-control[required]').forEach(function(el) {
                el.setAttribute('required', 'required');
            });
            document.querySelectorAll('.internal-form .form-control[required]').forEach(function(el) {
                el.removeAttribute('required');
            });
            
            updateLetterPreview();
        }
    }

    // ================================================================
    // UPDATE LETTER PREVIEW
    // ================================================================
    function updateLetterPreview() {
        var facility = document.querySelector('input[name="external_facility"]')?.value || '[Facility Name]';
        var expertType = document.querySelector('select[name="expert_type"]')?.value || '[Expert Type]';
        var expertTypeOther = document.querySelector('input[name="expert_type_other"]')?.value || '';
        var reason = document.querySelector('textarea[name="referral_reason"]')?.value || '[Reason]';
        var summary = document.querySelector('textarea[name="clinical_summary"]')?.value || '[Clinical Summary]';
        
        if (expertType === 'Other (Specify)' && expertTypeOther) {
            expertType = expertTypeOther + ' (Other)';
        }
        
        var letterFacility = document.getElementById('letterFacility');
        var letterExpertType = document.getElementById('letterExpertType');
        var letterReason = document.getElementById('letterReason');
        var letterSummary = document.getElementById('letterSummary');
        
        if (letterFacility) letterFacility.textContent = facility;
        if (letterExpertType) letterExpertType.textContent = expertType;
        if (letterReason) letterReason.textContent = reason;
        if (letterSummary) letterSummary.textContent = summary;
    }

    // ================================================================
    // FORM VALIDATION - FIXED
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize required fields
        document.querySelectorAll('.internal-form .form-control[required]').forEach(function(el) {
            el.setAttribute('required', 'required');
        });
        document.querySelectorAll('.external-form .form-control[required]').forEach(function(el) {
            el.removeAttribute('required');
        });
        
        // Listen to form changes for preview
        var inputs = document.querySelectorAll('.external-form input, .external-form textarea, .external-form select');
        inputs.forEach(function(input) {
            input.addEventListener('input', updateLetterPreview);
            input.addEventListener('change', updateLetterPreview);
        });
        
        // ================================================================
        // FORM SUBMIT VALIDATION - FIXED
        // ================================================================
        var form = document.getElementById('referralForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                var referralType = document.getElementById('referralType')?.value || 'internal';
                var patientId = document.querySelector('input[name="patient_id"]')?.value;
                
                console.log('🔍 Referral Type:', referralType);
                console.log('🔍 Patient ID:', patientId);
                
                // Check patient
                if (!patientId || patientId == 0) {
                    e.preventDefault();
                    showToast('Error', 'Please select a patient', 'error');
                    return false;
                }
                
                // ============================================================
                // INTERNAL REFERRAL VALIDATION
                // ============================================================
                if (referralType === 'internal') {
                    var reason = document.querySelector('textarea[name="reason"]')?.value?.trim();
                    var doctorSelect = document.querySelector('select[name="referred_to_doctor"]');
                    
                    console.log('🔍 Internal Reason:', reason);
                    
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
                
                // ============================================================
                // EXTERNAL REFERRAL VALIDATION
                // ============================================================
                if (referralType === 'external') {
                    var facility = document.querySelector('input[name="external_facility"]');
                    var reasonExternal = document.querySelector('textarea[name="referral_reason"]')?.value?.trim();
                    
                    console.log('🔍 External Facility:', facility?.value);
                    console.log('🔍 External Reason:', reasonExternal);
                    
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
                
                // Show loading
                var submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                }
                
                console.log('✅ Form validation passed - submitting...');
                return true;
            });
        }
    });

    // ================================================================
    // PRINT REFERRAL LETTER
    // ================================================================
    function printReferralLetter() {
        var letter = document.getElementById('referralLetter');
        if (!letter) {
            showToast('Error', 'No letter to print', 'error');
            return;
        }
        
        var printWindow = window.open('', '_blank', 'width=800,height=600');
        if (!printWindow) {
            showToast('Error', 'Please allow popups for printing', 'error');
            return;
        }
        
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var content = letter.outerHTML;
        
        printWindow.document.write('<!DOCTYPE html><html><head><title>Referral Letter</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; padding: 40px; background: ' + (isDark ? '#1E293B' : 'white') + '; }');
        printWindow.document.write('.referral-letter { max-width: 800px; margin: 0 auto; padding: 30px; border: 2px solid #0B5ED7; border-radius: 10px; background: ' + (isDark ? '#1E293B' : 'white') + '; color: ' + (isDark ? '#F1F5F9' : '#1E293B') + '; }');
        printWindow.document.write('.letter-header { text-align: center; border-bottom: 3px double #0B5ED7; padding-bottom: 15px; margin-bottom: 20px; }');
        printWindow.document.write('.letter-header .logo-container { display: flex; align-items: center; justify-content: center; gap: 15px; flex-wrap: wrap; margin-bottom: 10px; }');
        printWindow.document.write('.letter-header .logo-container img { height: 50px; width: auto; max-height: 50px; border-radius: 6px; }');
        printWindow.document.write('.letter-header h2 { color: #0B5ED7; font-size: 20px; margin: 0; }');
        printWindow.document.write('.letter-header .subtitle { font-size: 12px; color: ' + (isDark ? '#94A3B8' : '#666') + '; margin: 2px 0 0 0; }');
        printWindow.document.write('.letter-body .field-row { display: flex; padding: 6px 0; border-bottom: 1px solid ' + (isDark ? '#334155' : '#f0f0f0') + '; }');
        printWindow.document.write('.letter-body .field-row .field-label { font-weight: 600; width: 160px; color: ' + (isDark ? '#94A3B8' : '#555') + '; flex-shrink: 0; }');
        printWindow.document.write('.letter-body .field-row .field-value { flex: 1; color: ' + (isDark ? '#F1F5F9' : '#333') + '; }');
        printWindow.document.write('.letter-footer { border-top: 2px solid #0B5ED7; padding-top: 15px; margin-top: 15px; text-align: center; font-size: 12px; color: ' + (isDark ? '#94A3B8' : '#888') + '; }');
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(content);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        
        setTimeout(function() {
            printWindow.print();
        }, 500);
    }

    // ================================================================
    // RESET FORM
    // ================================================================
    function resetForm() {
        if (!confirm('Clear all form fields?')) return;
        document.getElementById('doctorSelect').value = '';
        document.querySelectorAll('.external-form input, .external-form textarea, .external-form select').forEach(function(el) {
            el.value = '';
        });
        document.querySelectorAll('textarea[name="reason"], textarea[name="referral_reason"], textarea[name="notes"], textarea[name="clinical_summary"]').forEach(function(el) {
            el.value = '';
        });
        var preview = document.getElementById('externalLetterPreview');
        if (preview) preview.style.display = 'none';
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
        }, 5000);
    }

    console.log('%c👨‍⚕️ Refer Patient - Two Step Process', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔐 Session-based login active', 'font-size:13px; color:#34D399;');
    console.log('%c🏥 Branch: <?= htmlspecialchars($doctor_branch_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= $patient ? htmlspecialchars($patient['full_name']) : 'Not selected' ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📞 Emergency Contact: <?= $patient ? htmlspecialchars($patient['emergency_contact'] ?? 'N/A') : 'N/A' ?>', 'font-size:13px; color:#059669;');
    console.log('%c👨‍⚕️ Referred By: Dr. <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔄 Internal Doctors: <?= count($doctors) ?> (same branch only)', 'font-size:13px; color:#059669;');
    console.log('%c🟢 Online: <?= $online_count ?> | ⚪ Offline: <?= $offline_count ?>', 'font-size:13px; color:#059669;');
    console.log('%c💊 Medications: <?= count($medications) ?> | 🔬 Procedures: <?= count($procedures) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📋 Internal Reason: name="reason" | External Reason: name="referral_reason"', 'font-size:13px; color:#059669;');
    console.log('%c✅ Validation fixed - checks correct field based on referral type', 'font-size:13px; color:#34D399;');
    console.log('%c✅ visit_id automatically handled - will create visit if needed', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>