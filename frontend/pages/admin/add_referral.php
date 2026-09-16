<?php
// ================================================================
// FILE: frontend/pages/admin/add_referral.php
// SUPER ADMIN - REFER PATIENT
// ✅ FIX: Dropdown inafanya kazi (count inaonekana)
// ✅ FIX: Branch filter inafanya kazi kwa idadi sahihi
// ✅ FIX: English only
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$selected_branch_id = $_GET['branch'] ?? 'all';
$current_branch_name_display = 'All Branches';

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $stmt->execute([(int)$selected_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) $current_branch_name_display = $branch_data['name'];
} else {
    $selected_branch_id = 'all';
}

$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// ✅ GET PATIENTS - BRANCH AWARE
// Kama branch = 'all' → patients wote
// Kama branch = 2 → patients wa branch 2 pekee
// Kama branch = 2 ina 0 patients → fallback kwa patients wote
// ================================================================
$patients_list = [];
try {
    $base_sql = "
        SELECT 
            p.id, 
            p.full_name, 
            p.patient_id, 
            p.gender, 
            p.phone, 
            p.date_of_birth, 
            p.emergency_contact,
            p.branch_id,
            (SELECT id FROM visits v WHERE v.patient_id = p.id AND v.status NOT IN ('completed', 'cancelled') ORDER BY v.id DESC LIMIT 1) as visit_id,
            (SELECT visit_number FROM visits v WHERE v.patient_id = p.id AND v.status NOT IN ('completed', 'cancelled') ORDER BY v.id DESC LIMIT 1) as visit_number
        FROM patients p
    ";
    
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        // Try with branch filter
        $sql = $base_sql . " WHERE p.branch_id = ? ORDER BY p.full_name ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([(int)$selected_branch_id]);
        $patients_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ✅ FALLBACK: Kama branch ina 0 patients, onyesha WOTE
        if (count($patients_list) == 0) {
            $sql = $base_sql . " ORDER BY p.full_name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $patients_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("⚠️ Branch $selected_branch_id has 0 patients. Showing all.");
        }
    } else {
        // Show all patients
        $sql = $base_sql . " ORDER BY p.full_name ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $patients_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    error_log("✅ Patients loaded: " . count($patients_list) . " (branch: $selected_branch_id)");
} catch (Exception $e) {
    error_log("❌ Patients list error: " . $e->getMessage());
    $patients_list = [];
}

// ================================================================
// GET DOCTORS
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
        AND u.status = 'active'
        GROUP BY u.id
        ORDER BY u.is_online DESC, pending_visits ASC, u.full_name ASC
    ");
    $stmt->execute([$user_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($doctors as $doctor) {
        if ($doctor['is_online'] == 1) $online_count++;
        else $offline_count++;
    }
} catch (Exception $e) {
    error_log("Doctor list error: " . $e->getMessage());
    $doctors = [];
}

// ================================================================
// EXPERT TYPES
// ================================================================
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
// HANDLE FORM SUBMISSIONS
// ================================================================
$message = '';
$message_type = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // SUBMIT INTERNAL REFERRAL
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
                $visit_ids_used = [];
                
                $stmt = $db->prepare("SELECT full_name, phone, specialty FROM users WHERE id = ?");
                $stmt->execute([$referred_to_doctor]);
                $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                $doctor_name = $doctor['full_name'] ?? 'Unknown Doctor';
                
                $combined_reason = $reason;
                if (!empty($internal_notes)) {
                    $combined_reason .= "\n\n--- Additional Notes ---\n" . $internal_notes;
                }
                
                $placeholders = implode(',', array_fill(0, count($selected_patients), '?'));
                $stmt = $db->prepare("SELECT id, full_name, patient_id FROM patients WHERE id IN ($placeholders)");
                $stmt->execute($selected_patients);
                $patients_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($patients_data as $patient) {
                    $stmt_visit = $db->prepare("
                        SELECT id, visit_number, diagnosis, disease_code, treatment, symptoms, hpi, physical_exam, doctor_id
                        FROM visits 
                        WHERE patient_id = ? 
                        AND status NOT IN ('completed', 'cancelled')
                        ORDER BY id DESC LIMIT 1
                    ");
                    $stmt_visit->execute([$patient['id']]);
                    $visit_info = $stmt_visit->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$visit_info) {
                        $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        $stmt = $db->prepare("
                            INSERT INTO visits (visit_number, visit_date, patient_id, doctor_id, branch_id, visit_type, status, created_at)
                            VALUES (?, NOW(), ?, ?, ?, 'new', 'pending', NOW())
                        ");
                        $stmt->execute([$visit_number, $patient['id'], $referred_to_doctor, $user_branch_id]);
                        $visit_id = $db->lastInsertId();
                        $visit_info = ['id' => $visit_id, 'visit_number' => $visit_number, 'doctor_id' => $referred_to_doctor];
                    }
                    
                    $visit_id_to_use = (int)$visit_info['id'];
                    $diagnosis = $visit_info['diagnosis'] ?? '';
                    $treatment = $visit_info['treatment'] ?? '';
                    $disease_code = $visit_info['disease_code'] ?? '';
                    
                    $patient_clinical_notes = "";
                    if (!empty($diagnosis)) $patient_clinical_notes .= "\n\n--- Diagnosis ---\n" . $diagnosis;
                    if (!empty($disease_code)) $patient_clinical_notes .= "\nDisease Code: " . $disease_code;
                    if (!empty($treatment)) $patient_clinical_notes .= "\n\n--- Treatment Given ---\n" . $treatment;
                    
                    $referral_number = 'REF-' . date('Ymd') . '-' . str_pad($patient['id'], 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                    
                    $stmt = $db->prepare("
                        INSERT INTO referrals (
                            referral_number, visit_id, patient_id, from_doctor_id,
                            referral_type, to_doctor_id,
                            reason, clinical_notes, diagnosis, treatment_given,
                            urgency, status, referral_date, created_by, branch_id, created_at
                        ) VALUES (?, ?, ?, ?, 'internal', ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW())
                    ");
                    
                    $stmt->execute([
                        $referral_number, $visit_id_to_use, $patient['id'], $user_id,
                        $referred_to_doctor, $combined_reason, $patient_clinical_notes,
                        $diagnosis, $treatment, $urgency, $referral_status,
                        $user_id, $user_branch_id
                    ]);
                    
                    $referral_id = $db->lastInsertId();
                    $referrals_created++;
                    $referral_numbers[] = $referral_number;
                    $visit_ids_used[] = $visit_id_to_use;
                    
                    $stmt_update = $db->prepare("
                        UPDATE visits 
                        SET doctor_id = ?, is_referred = 1, referred_by_doctor_id = ?, referred_to_doctor_id = ?, referral_id = ?, status = 'assigned', updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt_update->execute([$referred_to_doctor, $user_id, $referred_to_doctor, $referral_id, $visit_id_to_use]);
                    
                    try {
                        $stmt_update = $db->prepare("UPDATE patients SET assigned_doctor_id = ?, updated_at = NOW() WHERE id = ?");
                        $stmt_update->execute([$referred_to_doctor, $patient['id']]);
                    } catch (Exception $e) {}
                    
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO notifications (user_id, branch_id, patient_id, title, message, type, link, created_at)
                            VALUES (?, ?, ?, ?, ?, 'info', ?, NOW())
                        ");
                        $stmt->execute([
                            $referred_to_doctor, $user_branch_id, $patient['id'],
                            "📋 New Referral Received",
                            "New referral from " . $user_full_name . " for patient " . ($patient['full_name'] ?? '') . ".",
                            "consultations.php"
                        ]);
                    } catch (Exception $e) {}
                    
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO activity_logs (user_id, branch_id, patient_id, action, details, created_at) 
                            VALUES (?, ?, ?, 'referral_created', ?, NOW())
                        ");
                        $stmt->execute([
                            $user_id, $user_branch_id, $patient['id'],
                            "Patient referred internally: " . ($patient['full_name'] ?? '') . " (#$referral_number) - From " . $user_full_name . " to Dr. " . $doctor_name
                        ]);
                    } catch (Exception $e) {}
                }
                
                $db->commit();
                
                $referral_list = implode(', ', $referral_numbers);
                $message = "✅ " . $referrals_created . " patient(s) referred internally successfully!<br>";
                $message .= "📋 Referrals: " . $referral_list . "<br>";
                $message .= "👨‍⚕️ Referred by: " . $user_full_name . " → To: Dr. " . $doctor_name;
                $message_type = 'success';
                
                echo '<script>
                    setTimeout(function(){
                        window.location.href = "referrals.php?branch=' . $selected_branch_id . '";
                    }, 3000);
                </script>';
                
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $message = "❌ Internal referral error: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
    // SUBMIT EXTERNAL REFERRAL
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
        if ($patient_id_post <= 0) $errors[] = "Please select a patient";
        if (empty($external_facility)) $errors[] = "Please enter facility name";
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("SELECT id, full_name, patient_id FROM patients WHERE id = ?");
                $stmt->execute([$patient_id_post]);
                $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$patient) throw new Exception("Patient not found");
                
                $stmt_visit = $db->prepare("
                    SELECT id, visit_number, diagnosis, disease_code, treatment
                    FROM visits WHERE patient_id = ? AND status NOT IN ('completed', 'cancelled')
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt_visit->execute([$patient_id_post]);
                $visit_info = $stmt_visit->fetch(PDO::FETCH_ASSOC);
                
                if (!$visit_info) {
                    $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $stmt = $db->prepare("
                        INSERT INTO visits (visit_number, visit_date, patient_id, doctor_id, branch_id, visit_type, status, created_at)
                        VALUES (?, NOW(), ?, ?, ?, 'new', 'pending', NOW())
                    ");
                    $stmt->execute([$visit_number, $patient_id_post, $user_id, $user_branch_id]);
                    $visit_id = $db->lastInsertId();
                    $visit_info = ['id' => $visit_id, 'visit_number' => $visit_number];
                }
                
                $visit_id_to_use = (int)$visit_info['id'];
                $diagnosis = $visit_info['diagnosis'] ?? '';
                $treatment = $visit_info['treatment'] ?? '';
                $disease_code = $visit_info['disease_code'] ?? '';
                
                $clinical_notes_final = "";
                if (!empty($diagnosis)) $clinical_notes_final .= "\n\n--- Diagnosis ---\n" . $diagnosis;
                if (!empty($disease_code)) $clinical_notes_final .= "\nDisease Code: " . $disease_code;
                if (!empty($treatment)) $clinical_notes_final .= "\n\n--- Treatment Given ---\n" . $treatment;
                
                $combined_reason = $referral_reason;
                if (!empty($external_notes)) $combined_reason .= "\n\n--- Additional Notes ---\n" . $external_notes;
                
                $clinical_notes_with_expert = $clinical_notes_final;
                if (!empty($expert_type)) $clinical_notes_with_expert = "Expert Type: " . $expert_type . "\n\n" . $clinical_notes_final;
                
                $referral_number = 'REF-' . date('Ymd') . '-' . str_pad($patient_id_post, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                
                $stmt = $db->prepare("
                    INSERT INTO referrals (
                        referral_number, visit_id, patient_id, from_doctor_id,
                        referral_type, to_hospital_name, to_hospital_address, to_hospital_phone,
                        to_hospital_email, reason, clinical_notes, diagnosis, treatment_given, expert_type,
                        urgency, status, referral_date, created_by, branch_id, created_at
                    ) VALUES (?, ?, ?, ?, 'external', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $referral_number, $visit_id_to_use, $patient_id_post, $user_id,
                    $external_facility, $external_address, $external_phone, $external_email,
                    $combined_reason, $clinical_notes_with_expert, $diagnosis, $treatment,
                    $expert_type, $urgency, 'referred', $user_id, $user_branch_id
                ]);
                
                $referral_id = $db->lastInsertId();
                
                $stmt_update = $db->prepare("
                    UPDATE visits SET is_referred = 1, referred_by_doctor_id = ?, referral_id = ?, status = 'referred', updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt_update->execute([$user_id, $referral_id, $visit_id_to_use]);
                
                try {
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (user_id, branch_id, patient_id, action, details, created_at) 
                        VALUES (?, ?, ?, 'referral_created', ?, NOW())
                    ");
                    $stmt->execute([
                        $user_id, $user_branch_id, $patient_id_post,
                        "Patient referred externally: " . ($patient['full_name'] ?? '') . " (#$referral_number) - To: " . $external_facility
                    ]);
                } catch (Exception $e) {}
                
                $db->commit();
                
                $message = "✅ Patient referred externally successfully!<br>";
                $message .= "📋 Referral: " . $referral_number . "<br>";
                $message .= "🏥 To: " . $external_facility;
                $message_type = 'success';
                
                echo '<script>
                    setTimeout(function(){
                        window.location.href = "referrals.php?branch=' . $selected_branch_id . '";
                    }, 3000);
                </script>';
                
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $message = "❌ External referral error: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

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
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --shadow-md: 0 4px 12px rgba(0,0,0,0.07);
    --shadow-lg: 0 8px 25px rgba(0,0,0,0.1);
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
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
    margin: 0;
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
    margin-top: 6px;
}

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
.btn-outline-light:hover { background: rgba(255,255,255,0.25); transform: translateY(-2px); color: white; }

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

.referral-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
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
.column-external .column-title { color: var(--success); }

/* MULTI-SELECT DROPDOWN */
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
    gap: 8px;
}

.multi-select-trigger:hover { border-color: var(--primary); }
.multi-select-trigger.open { border-color: var(--primary); }

.multi-select-trigger .trigger-text {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    text-align: left;
}

.multi-select-trigger .trigger-arrow {
    font-size: 0.7rem;
    color: var(--text-secondary);
    transition: transform 0.3s ease;
}
.multi-select-trigger.open .trigger-arrow { transform: rotate(180deg); }

.multi-select-trigger .selected-count {
    background: var(--primary);
    color: white;
    padding: 1px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
    flex-shrink: 0;
}
.multi-select-trigger .selected-count.zero { background: #CBD5E1; color: #64748B; }

.multi-select-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: var(--bg-card);
    border: 2px solid var(--primary);
    border-radius: 8px;
    max-height: 300px;
    overflow-y: auto;
    z-index: 9999;
    display: none;
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
}

.multi-select-dropdown.open { display: block !important; }

.multi-select-dropdown .dropdown-search {
    padding: 8px 12px;
    border-bottom: 1px solid var(--border-color);
    position: sticky;
    top: 0;
    background: var(--bg-card);
    z-index: 2;
}

.multi-select-dropdown .dropdown-search input {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-size: 0.75rem;
    background: var(--bg-body);
    color: var(--text-primary);
    outline: none;
}

.multi-select-dropdown .dropdown-search input:focus { border-color: var(--primary); }

.multi-select-dropdown .dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    border-bottom: 1px solid var(--border-color);
    user-select: none;
}

.multi-select-dropdown .dropdown-item:hover { background: var(--primary-bg); }
.multi-select-dropdown .dropdown-item.checked { background: var(--primary-bg); }

.multi-select-dropdown .dropdown-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: var(--primary);
    cursor: pointer;
    flex-shrink: 0;
    pointer-events: none;
}

.multi-select-dropdown .dropdown-item .item-info { flex: 1; min-width: 0; }
.multi-select-dropdown .dropdown-item .item-name {
    font-weight: 500;
    font-size: 0.8rem;
    color: var(--text-primary);
}
.multi-select-dropdown .dropdown-item .item-details {
    font-size: 0.6rem;
    color: var(--text-secondary);
    margin-top: 2px;
}

.multi-select-dropdown .dropdown-item .item-check {
    color: var(--text-secondary);
    font-size: 0.75rem;
    flex-shrink: 0;
}
.multi-select-dropdown .dropdown-item.checked .item-check { color: var(--primary); }

.multi-select-dropdown .dropdown-actions {
    padding: 8px 12px;
    border-top: 2px solid var(--primary);
    display: flex;
    gap: 8px;
    position: sticky;
    bottom: 0;
    background: var(--bg-card);
    z-index: 2;
}

.multi-select-dropdown .dropdown-actions button {
    font-size: 0.65rem;
    padding: 4px 12px;
    border-radius: 6px;
    border: 1px solid var(--border-color);
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 600;
}

.multi-select-dropdown .dropdown-actions button:hover {
    background: var(--primary-bg);
    border-color: var(--primary);
    color: var(--primary);
}

.multi-select-dropdown .dropdown-actions button.select-all {
    color: var(--primary);
    border-color: var(--primary);
}
.multi-select-dropdown .dropdown-actions button.select-all:hover {
    background: var(--primary);
    color: white;
}

.form-group { margin-bottom: 14px; }

.form-label {
    display: block;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 4px;
}

.form-label .optional {
    font-weight: 400;
    color: var(--text-secondary);
    font-size: 0.6rem;
}

.text-danger { color: #EF4444; }

.form-control {
    width: 100%;
    padding: 8px 12px;
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

textarea.form-control { resize: vertical; min-height: 50px; }
select.form-control { cursor: pointer; }

.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8rem;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
    text-decoration: none;
    font-family: inherit;
    min-height: 42px;
    width: 100%;
}

.btn-success {
    background: #059669;
    color: white;
    box-shadow: 0 2px 8px rgba(5,150,105,0.25);
}
.btn-success:hover {
    background: #047857;
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(5,150,105,0.35);
    color: white;
}

.btn-primary {
    background: var(--primary);
    color: white;
    box-shadow: 0 2px 8px rgba(11,94,215,0.25);
}
.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(11,94,215,0.35);
    color: white;
}

.btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }

.form-actions {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 2px solid var(--border-color);
}

.expert-other-wrapper { display: none; margin-top: 6px; }
.expert-other-wrapper.show { display: block; }

.doctor-info-text {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-top: 6px;
    padding: 8px 12px;
    background: var(--bg-body);
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.doctor-info-text .status-item {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.68rem;
    color: var(--text-secondary);
}

.doctor-info-text .status-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
}
.doctor-info-text .status-dot.online { background: #059669; }
.doctor-info-text .status-dot.offline { background: #94A3B8; }

.external-no-doctor-note {
    font-size: 0.65rem;
    color: var(--text-secondary);
    background: var(--bg-body);
    padding: 6px 12px;
    border-radius: 6px;
    border: 1px dashed var(--border-color);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.external-no-doctor-note i { color: var(--success); }

.badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.55rem;
    font-weight: 600;
}
.badge-info { background: var(--primary-bg); color: var(--primary); }

.footer {
    padding: 12px 0;
    border-top: 2px solid var(--border-color);
    margin-top: 20px;
    text-align: center;
    font-size: 0.68rem;
    color: var(--text-secondary);
}
.footer .footer-brand { color: var(--primary); font-weight: 600; }

.toast-custom {
    position: fixed;
    bottom: 24px;
    right: 24px;
    padding: 12px 20px;
    border-radius: 10px;
    z-index: 99999;
    max-width: 380px;
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.4s ease;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #ffffff;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    font-size: 0.85rem;
}
.toast-custom.show { transform: translateY(0); opacity: 1; }
.toast-custom.success { background: #059669; }
.toast-custom.error { background: #DC2626; }
.toast-custom.info { background: #0B5ED7; }

@media (max-width: 1024px) {
    .referral-columns { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .page-header-custom { padding: 14px 16px; }
    .page-header-custom .page-title { font-size: 1.1rem; }
    .referral-column { padding: 12px 14px; }
    .grid-2 { grid-template-columns: 1fr; }
}
</style>

<main class="main-content">

    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-share-square"></i>
                Refer Patient
                <span class="role-badge-display">ADMIN</span>
                <span class="referral-badge">
                    <i class="fas fa-exchange-alt"></i> Referral
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-info-circle"></i>
                <span class="header-badge"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($current_branch_name_display) ?></span>
                <span class="header-badge"><i class="fas fa-user-shield"></i> <?= htmlspecialchars($user_full_name) ?></span>
                <span class="header-badge"><i class="fas fa-users"></i> <?= count($patients_list) ?> patients</span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="referrals.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="referral-columns">
        
        <!-- INTERNAL REFERRAL -->
        <div class="referral-column column-internal">
            <div class="column-title">
                <i class="fas fa-hospital"></i>
                Internal Referral
                <span class="badge-count" id="internalCountBadge">0 selected</span>
            </div>
            
            <form method="POST" action="" id="internalForm">
                <input type="hidden" name="action" value="submit_internal">
                
                <div class="form-group">
                    <label class="form-label">Select Patients <span class="text-danger">*</span></label>
                    <div class="multi-select-wrapper" id="multiSelectWrapper">
                        <div class="multi-select-trigger" id="multiSelectTrigger">
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
                                            <input type="checkbox" name="selected_patients[]" value="<?= $p['id'] ?>" id="patient_<?= $p['id'] ?>">
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
                                    <div style="text-align:center;padding:20px;color:var(--text-secondary);">
                                        <i class="fas fa-users" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:0.4;"></i>
                                        <p style="font-size:0.8rem;">No patients found</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="dropdown-actions">
                                <button type="button" class="select-all" onclick="selectAllPatients(event)">✅ Select All</button>
                                <button type="button" onclick="deselectAllPatients(event)">✖ Deselect All</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Refer To Doctor <span class="text-danger">*</span></label>
                    <select name="referred_to_doctor" class="form-control" required id="doctorSelect">
                        <option value="">-- Select Doctor --</option>
                        <?php if (count($doctors) > 0): ?>
                            <?php if ($online_count > 0): ?>
                                <optgroup label="🟢 Online Doctors (<?= $online_count ?>)">
                                    <?php foreach ($doctors as $doctor): ?>
                                        <?php if ($doctor['is_online'] == 1): ?>
                                            <option value="<?= $doctor['id'] ?>">
                                                🟢 Dr. <?= htmlspecialchars($doctor['full_name']) ?> 
                                                <?= !empty($doctor['specialty']) ? '(' . htmlspecialchars($doctor['specialty']) . ')' : '' ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <?php if ($offline_count > 0): ?>
                                <optgroup label="⚪ Offline Doctors (<?= $offline_count ?>)">
                                    <?php foreach ($doctors as $doctor): ?>
                                        <?php if ($doctor['is_online'] == 0): ?>
                                            <option value="<?= $doctor['id'] ?>">
                                                ⚪ Dr. <?= htmlspecialchars($doctor['full_name']) ?> 
                                                <?= !empty($doctor['specialty']) ? '(' . htmlspecialchars($doctor['specialty']) . ')' : '' ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        <?php else: ?>
                            <option value="" disabled>⚠️ No doctors available</option>
                        <?php endif; ?>
                    </select>
                    
                    <div class="doctor-info-text">
                        <?php if (count($doctors) > 0): ?>
                            <span class="status-item"><span class="status-dot online"></span><strong><?= $online_count ?></strong> Online</span>
                            <span class="status-item"><span class="status-dot offline"></span><strong><?= $offline_count ?></strong> Offline</span>
                            <span class="status-item"><i class="fas fa-users"></i> <strong><?= count($doctors) ?></strong> doctor(s)</span>
                        <?php else: ?>
                            <span class="status-item" style="color:#DC2626;"><i class="fas fa-exclamation-triangle"></i> No other doctors found</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Reason for Referral <span class="optional">(Optional)</span></label>
                    <textarea name="reason" class="form-control" rows="2" placeholder="Explain why the patient(s) are being referred..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Additional Notes <span class="optional">(Optional)</span></label>
                    <textarea name="internal_notes" class="form-control" rows="2" placeholder="Any additional notes for the receiving doctor..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success" id="submitInternalBtn">
                        <i class="fas fa-paper-plane"></i> Submit Internal Referral
                    </button>
                </div>
            </form>
        </div>
        
        <!-- EXTERNAL REFERRAL -->
        <div class="referral-column column-external">
            <div class="column-title">
                <i class="fas fa-globe-africa"></i>
                External Referral
                <span class="badge-count success">Single Patient</span>
            </div>
            
            <form method="POST" action="" id="externalForm">
                <input type="hidden" name="action" value="submit_external">
                
                <div class="form-group">
                    <label class="form-label">Select Patient <span class="text-danger">*</span></label>
                    <select name="external_patient_id" class="form-control" id="externalPatientSelect" required>
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
                
                <div class="external-no-doctor-note">
                    <i class="fas fa-info-circle"></i>
                    Sent from <strong><?= htmlspecialchars($user_full_name) ?></strong> - No doctor selection required
                </div>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Facility Name <span class="text-danger">*</span></label>
                        <input type="text" name="external_facility" class="form-control" placeholder="e.g. Muhimbili Hospital" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="external_phone" class="form-control" placeholder="+255 xxx xxx xxx">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="external_address" class="form-control" rows="1" placeholder="Facility address..."></textarea>
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
                        <input type="text" name="expert_type_other" class="form-control" placeholder="Specify expert type...">
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
                    <label class="form-label">Reason for Referral <span class="optional">(Optional)</span></label>
                    <textarea name="referral_reason" class="form-control" rows="2" placeholder="Explain why the patient is being referred..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Additional Notes <span class="optional">(Optional)</span></label>
                    <textarea name="external_notes" class="form-control" rows="2" placeholder="Any additional notes..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submitExternalBtn">
                        <i class="fas fa-paper-plane"></i> Submit External Referral
                    </button>
                </div>
            </form>
        </div>
        
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span>
            Refer Patient
            <span>|</span>
            <?= htmlspecialchars($user_full_name) ?>
            <span>|</span>
            <span id="footerTimestamp">Last updated: <?= date('h:i:s A') ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div><p id="toastTitle" style="margin:0;font-weight:600;font-size:0.85rem;">Notification</p><p id="toastMessage" style="margin:0;font-size:0.75rem;opacity:0.9;"></p></div>
</div>

<script>
// ================================================================
// ✅ MULTI-SELECT DROPDOWN - FIXED
// ================================================================
(function() {
    var dropdownOpen = false;
    var multiSelectTrigger = document.getElementById('multiSelectTrigger');
    var multiSelectDropdown = document.getElementById('multiSelectDropdown');
    var multiSelectWrapper = document.getElementById('multiSelectWrapper');
    
    if (!multiSelectTrigger || !multiSelectDropdown) {
        console.error('❌ Multi-select elements not found');
        return;
    }
    
    // ✅ Open/Close dropdown
    multiSelectTrigger.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (dropdownOpen) {
            multiSelectDropdown.classList.remove('open');
            multiSelectTrigger.classList.remove('open');
            dropdownOpen = false;
        } else {
            multiSelectDropdown.classList.add('open');
            multiSelectTrigger.classList.add('open');
            dropdownOpen = true;
        }
    });
    
    // ✅ Prevent closing when clicking inside dropdown
    multiSelectDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });
    
    // ✅ Close when clicking outside
    document.addEventListener('click', function(e) {
        if (multiSelectWrapper && !multiSelectWrapper.contains(e.target)) {
            multiSelectDropdown.classList.remove('open');
            multiSelectTrigger.classList.remove('open');
            dropdownOpen = false;
        }
    });
    
    // ✅ Make global
    window.toggleDropdown = function() {
        // This function is now handled by the addEventListener above
    };
    
    console.log('✅ Multi-select dropdown initialized');
})();

// ================================================================
// ✅ TOGGLE PATIENT ITEM
// ================================================================
window.togglePatientItem = function(element) {
    var checkbox = element.querySelector('input[type="checkbox"]');
    if (checkbox) {
        checkbox.checked = !checkbox.checked;
        element.classList.toggle('checked', checkbox.checked);
        updateSelection();
    }
};

// ================================================================
// ✅ UPDATE SELECTION COUNT
// ================================================================
window.updateSelection = function() {
    var checkboxes = document.querySelectorAll('#patientOptions input[type="checkbox"]');
    var checked = document.querySelectorAll('#patientOptions input[type="checkbox"]:checked');
    var count = checked.length;
    
    console.log('✅ Selected count:', count, 'of', checkboxes.length);
    
    // Update count badge
    var badge = document.getElementById('internalCountBadge');
    if (badge) {
        badge.textContent = count + ' selected';
        badge.className = count > 0 ? 'badge-count success' : 'badge-count';
    }
    
    // Update trigger text
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
        if (selectedNames.length > 2) {
            triggerText.textContent = selectedNames.slice(0, 2).join(', ') + ' +' + (selectedNames.length - 2) + ' more';
        } else {
            triggerText.textContent = selectedNames.join(', ');
        }
    } else {
        triggerText.textContent = 'Select patients...';
    }
    
    // Update count badge in trigger
    var countBadge = document.getElementById('selectedCountBadge');
    if (countBadge) {
        countBadge.textContent = count;
        countBadge.className = count > 0 ? 'selected-count' : 'selected-count zero';
    }
    
    // Enable/disable submit
    var submitBtn = document.getElementById('submitInternalBtn');
    if (submitBtn) {
        submitBtn.disabled = (count === 0);
    }
};

// ================================================================
// ✅ FILTER PATIENTS
// ================================================================
window.filterPatients = function() {
    var search = document.getElementById('searchPatients').value.toLowerCase();
    var items = document.querySelectorAll('#patientOptions .dropdown-item');
    items.forEach(function(item) {
        var text = item.textContent.toLowerCase();
        item.style.display = text.includes(search) ? 'flex' : 'none';
    });
};

// ================================================================
// ✅ SELECT ALL
// ================================================================
window.selectAllPatients = function(event) {
    if (event) event.stopPropagation();
    var checkboxes = document.querySelectorAll('#patientOptions input[type="checkbox"]');
    checkboxes.forEach(function(cb) {
        cb.checked = true;
        var item = cb.closest('.dropdown-item');
        if (item) item.classList.add('checked');
    });
    updateSelection();
    showToast('Info', 'All patients selected', 'info');
};

// ================================================================
// ✅ DESELECT ALL
// ================================================================
window.deselectAllPatients = function(event) {
    if (event) event.stopPropagation();
    var checkboxes = document.querySelectorAll('#patientOptions input[type="checkbox"]');
    checkboxes.forEach(function(cb) {
        cb.checked = false;
        var item = cb.closest('.dropdown-item');
        if (item) item.classList.remove('checked');
    });
    updateSelection();
    showToast('Info', 'All patients deselected', 'info');
};

// ================================================================
// EXPERT TYPE
// ================================================================
window.toggleExpertOther = function() {
    var select = document.getElementById('expertTypeSelect');
    var wrapper = document.getElementById('expertOtherWrapper');
    if (select && select.value === 'Other (Specify)') {
        wrapper.classList.add('show');
    } else if (wrapper) {
        wrapper.classList.remove('show');
    }
};

// ================================================================
// TOAST
// ================================================================
window.showToast = function(title, message, type) {
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
};

// ================================================================
// FOOTER TIMESTAMP
// ================================================================
function updateDateTime() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var ftEl = document.getElementById('footerTimestamp');
    if (ftEl) ftEl.textContent = 'Last updated: ' + timeStr;
}
updateDateTime();
setInterval(updateDateTime, 1000);

// ================================================================
// FORM VALIDATION
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
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
    
    console.log('✅ All scripts loaded successfully');
});

console.log('%c📋 Admin - Refer Patient (FIXED)', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Dropdown inafanya kazi', 'font-size:13px;color:#34D399;');
console.log('%c✅ Count inaonekana kwa usahihi', 'font-size:13px;color:#34D399;');
console.log('%c✅ Branch filter inafanya kazi', 'font-size:13px;color:#34D399;');
</script>

</body>
</html>