<?php
// ================================================================
// FILE: frontend/pages/admin/add_patient.php
// ADMIN - REGISTER NEW PATIENT
// ✅ Uses SHARED header & sidebar
// ✅ Beautiful page details card
// ✅ Full dark mode support
// ✅ 7 Vital Signs (with SpO2)
// ================================================================

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$full_name = $user_full_name;
$role = $user_role;
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? 'admin';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

$message = '';
$message_type = '';

$doctors = [];
$online_doctors = [];
$offline_doctors = [];
$online_doctors_count = 0;
$offline_doctors_count = 0;
$total_doctors = 0;
$branches_list = [];
$current_branch_name = 'All Branches';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $target_branch_id = $user_branch_id;
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $target_branch_id = (int)$selected_branch_id;
    }

    foreach ($branches_list as $b) {
        if ($b['id'] == $target_branch_id) {
            $current_branch_name = $b['name'];
            break;
        }
    }

    $unread_notifications = 0;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) { $unread_notifications = 0; }

    // AJAX: DOCTOR STATUS
    if (isset($_POST['action']) && $_POST['action'] === 'get_doctor_status') {
        header('Content-Type: application/json');
        $branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : $target_branch_id;

        $stmt = $db->prepare("SELECT id, full_name, specialty, is_online FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ? ORDER BY is_online DESC, full_name");
        $stmt->execute([$branch_id]);
        $doctors_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $online = []; $offline = [];
        $online_count = 0; $offline_count = 0;

        foreach ($doctors_list as $doc) {
            if ($doc['is_online'] == 1) { $online[] = $doc; $online_count++; }
            else { $offline[] = $doc; $offline_count++; }
        }

        $options_html = '<option value="">-- Select Doctor --</option>';

        if ($online_count > 0) {
            $options_html .= '<optgroup label="🟢 Online Doctors (' . $online_count . ')" style="font-weight:600;color:#059669;">';
            foreach ($online as $doc) {
                $options_html .= '<option value="' . $doc['id'] . '" data-online="1" style="font-weight:500;color:#059669;padding:4px;">🟢 Dr. ' . htmlspecialchars($doc['full_name']);
                if (!empty($doc['specialty'])) $options_html .= ' (' . htmlspecialchars($doc['specialty']) . ')';
                $options_html .= '</option>';
            }
            $options_html .= '</optgroup>';
        }

        if ($offline_count > 0) {
            $options_html .= '<optgroup label="⚪ Offline Doctors (' . $offline_count . ')" style="font-weight:600;color:var(--text-secondary);">';
            foreach ($offline as $doc) {
                $options_html .= '<option value="' . $doc['id'] . '" data-online="0" style="color:var(--text-secondary);padding:4px;">⚪ Dr. ' . htmlspecialchars($doc['full_name']);
                if (!empty($doc['specialty'])) $options_html .= ' (' . htmlspecialchars($doc['specialty']) . ')';
                $options_html .= '</option>';
            }
            $options_html .= '</optgroup>';
        }

        if (empty($doctors_list)) $options_html .= '<option value="" disabled>No doctors available</option>';

        echo json_encode([
            'success' => true, 'online_count' => $online_count, 'offline_count' => $offline_count,
            'total_doctors' => count($doctors_list), 'doctor_options' => $options_html,
            'timestamp' => date('H:i:s')
        ]);
        exit;
    }

    // AJAX: AUTOCOMPLETE
    if (isset($_GET['action']) && $_GET['action'] === 'search_patients') {
        header('Content-Type: application/json');
        $query = isset($_GET['q']) ? trim($_GET['q']) : '';
        $branch = isset($_GET['branch']) ? (int)$_GET['branch'] : $target_branch_id;

        if (strlen($query) < 1) {
            echo json_encode(['success' => true, 'patients' => []]);
            exit;
        }

        try {
            $stmt = $db->prepare("
                SELECT p.id, p.patient_id, p.full_name, p.phone, p.gender, 
                       p.date_of_birth, p.address, p.blood_group, p.allergies, 
                       p.emergency_contact, p.marital_status,
                       (SELECT COUNT(*) FROM visits v WHERE v.patient_id = p.id AND v.status NOT IN ('completed', 'cancelled')) as active_visits
                FROM patients p
                WHERE p.branch_id = ? AND p.full_name LIKE ?
                ORDER BY p.full_name ASC LIMIT 10
            ");
            $stmt->execute([$branch, "%$query%"]);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'patients' => $patients, 'count' => count($patients)]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage(), 'patients' => []]);
        }
        exit;
    }

    // GET DOCTORS
    $stmt = $db->prepare("SELECT id, full_name, specialty, is_online FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ? ORDER BY is_online DESC, full_name");
    $stmt->execute([$target_branch_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($doctors as $doc) {
        if ($doc['is_online'] == 1) { $online_doctors[] = $doc; $online_doctors_count++; }
        else { $offline_doctors[] = $doc; $offline_doctors_count++; }
    }
    $total_doctors = count($doctors);

    // GET SERVICES
    $consultation_services = [];
    try {
        $stmt = $db->prepare("
            SELECT id, service_name, description, price, unit, is_active
            FROM services 
            WHERE category_id = 2 AND is_active = 1 
            AND (branch_id = ? OR branch_id IS NULL)
            ORDER BY 
                CASE 
                    WHEN service_name LIKE '%New Patient%' THEN 0
                    WHEN service_name LIKE '%General%' THEN 1
                    WHEN service_name LIKE '%Consultation-B%' THEN 2
                    WHEN service_name LIKE '%Emergency%' THEN 3
                    WHEN service_name LIKE '%Specialist%' THEN 4
                    WHEN service_name LIKE '%Follow%' THEN 5
                    ELSE 6
                END, service_name
        ");
        $stmt->execute([$target_branch_id]);
        $consultation_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $consultation_services = []; }

    $visit_type_options = [];
    $default_service_id = null;
    $default_price = 0;
    $default_service_name = 'General Consultation';
    $default_key = null;

    if (!empty($consultation_services)) {
        foreach ($consultation_services as $service) {
            $service_id = $service['id'];
            $service_name = $service['service_name'];
            $price = (float)($service['price'] ?? 0);
            $key = $service_id;

            $icon = '🏥';
            if (strpos(strtolower($service_name), 'new patient') !== false) $icon = '🆕';
            elseif (strpos(strtolower($service_name), 'follow') !== false) $icon = '🔄';
            elseif (strpos(strtolower($service_name), 'consultation-b') !== false) $icon = '🚨';
            elseif (strpos(strtolower($service_name), 'emergency') !== false) $icon = '🚨';
            elseif (strpos(strtolower($service_name), 'specialist') !== false) $icon = '👨‍⚕️';

            $visit_type_options[$key] = [
                'service_id' => $service_id, 'service_name' => $service_name,
                'display_name' => $service_name, 'price' => $price,
                'unit' => $service['unit'] ?? 'each', 'description' => $service['description'] ?? '',
                'is_active' => $service['is_active'] ?? 1, 'icon' => $icon
            ];

            if (strpos(strtolower($service_name), 'new patient') !== false) {
                $default_service_id = $service_id; $default_price = $price;
                $default_service_name = $service_name; $default_key = $key;
            } elseif ($default_key === null) {
                $default_service_id = $service_id; $default_price = $price;
                $default_service_name = $service_name; $default_key = $key;
            }
        }
    }

    if (empty($visit_type_options)) {
        $visit_type_options = [
            1 => ['service_id' => null, 'service_name' => 'New Patient', 'display_name' => 'New Patient',
                  'price' => 10000, 'unit' => 'each', 'description' => 'New patient consultation',
                  'is_active' => 1, 'icon' => '🆕']
        ];
        $default_key = 1; $default_price = 10000; $default_service_name = 'New Patient';
    }

    if (!function_exists('generateUniquePatientId')) {
        function generateUniquePatientId($db, $branch_id) {
            $year = date('Y');
            $branch_code = str_pad($branch_id, 2, '0', STR_PAD_LEFT);
            $pattern = "P-$year-$branch_code-%";

            $stmt = $db->prepare("SELECT patient_id FROM patients WHERE branch_id = ? AND patient_id LIKE ? ORDER BY patient_id DESC LIMIT 1");
            $stmt->execute([$branch_id, $pattern]);
            $last = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($last) {
                $parts = explode('-', $last['patient_id']);
                $last_number = intval(end($parts));
                $next_number = $last_number + 1;
                $new_number = str_pad($next_number, 4, '0', STR_PAD_LEFT);
            } else { $new_number = '0001'; }

            $patient_id_number = "P-$year-$branch_code-$new_number";

            $stmt = $db->prepare("SELECT COUNT(*) FROM patients WHERE patient_id = ?");
            $stmt->execute([$patient_id_number]);
            $exists = $stmt->fetchColumn();

            if ($exists > 0) {
                $counter = 1;
                while ($exists > 0) {
                    $new_number = str_pad($next_number + $counter, 4, '0', STR_PAD_LEFT);
                    $patient_id_number = "P-$year-$branch_code-$new_number";
                    $stmt = $db->prepare("SELECT COUNT(*) FROM patients WHERE patient_id = ?");
                    $stmt->execute([$patient_id_number]);
                    $exists = $stmt->fetchColumn();
                    $counter++;
                }
            }
            return $patient_id_number;
        }
    }

    $patient_id_number = generateUniquePatientId($db, $target_branch_id);

    $common_symptoms = [
        'Fever' => 'Fever', 'Headache' => 'Headache', 'Cough' => 'Cough',
        'Sore Throat' => 'Sore Throat', 'Body Pain' => 'Body Pain', 'Fatigue' => 'Fatigue',
        'Nausea' => 'Nausea', 'Vomiting' => 'Vomiting', 'Diarrhea' => 'Diarrhea',
        'Chest Pain' => 'Chest Pain', 'Shortness of Breath' => 'Shortness of Breath',
        'Abdominal Pain' => 'Abdominal Pain', 'Dizziness' => 'Dizziness', 'Rash' => 'Rash', 'Swelling' => 'Swelling'
    ];

    $common_allergies = [
        'Penicillin' => 'Penicillin', 'Sulfa Drugs' => 'Sulfa Drugs', 'Aspirin' => 'Aspirin',
        'Ibuprofen' => 'Ibuprofen', 'Codeine' => 'Codeine', 'Latex' => 'Latex',
        'Peanuts' => 'Peanuts', 'Shellfish' => 'Shellfish', 'Eggs' => 'Eggs',
        'Milk' => 'Milk', 'Wheat' => 'Wheat', 'Soy' => 'Soy',
        'Dust' => 'Dust', 'Pollen' => 'Pollen', 'Animal Dander' => 'Animal Dander'
    ];

    $marital_statuses = [
        'Single' => 'Single', 'Married' => 'Married', 'Divorced' => 'Divorced',
        'Widowed' => 'Widowed', 'Separated' => 'Separated'
    ];

    // FORM SUBMISSION
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_patient'])) {
        $full_name_input = trim($_POST['full_name'] ?? '');
        $date_of_birth = $_POST['date_of_birth'] ?? null;
        $gender = $_POST['gender'] ?? null;
        $marital_status = $_POST['marital_status'] ?? null;
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $emergency_contact = trim($_POST['emergency_contact'] ?? '');
        $blood_group = $_POST['blood_group'] ?? null;
        $allergies = trim($_POST['allergies'] ?? '');

        $registered_by_id = $user_id;
        $registered_by_name = $user_full_name;

        $form_branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : $target_branch_id;
        if ($form_branch_id <= 0) $form_branch_id = $target_branch_id;

        $assign_doctor = isset($_POST['assign_doctor']) ? 1 : 0;
        $doctor_id = (int)($_POST['doctor_id'] ?? 0);
        $service_id = isset($_POST['visit_type']) ? (int)$_POST['visit_type'] : 0;

        if ($service_id > 0) {
            $stmt = $db->prepare("SELECT id, service_name, price FROM services WHERE id = ? AND is_active = 1");
            $stmt->execute([$service_id]);
            $service = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($service) {
                $visit_type_name = $service['service_name'];
                $consultation_fee = (float)$service['price'];
                $service_id_to_store = $service['id'];
            } else {
                $visit_type_name = 'General Consultation';
                $consultation_fee = 15000;
                $service_id_to_store = null;
            }
        } else {
            $visit_type_name = 'General Consultation';
            $consultation_fee = 15000;
            $service_id_to_store = null;
        }

        $symptoms = trim($_POST['symptoms'] ?? '');
        $complaint = trim($_POST['complaint'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        $temperature = !empty($_POST['temperature']) ? (float)$_POST['temperature'] : null;
        $bp_systolic = !empty($_POST['bp_systolic']) ? (int)$_POST['bp_systolic'] : null;
        $bp_diastolic = !empty($_POST['bp_diastolic']) ? (int)$_POST['bp_diastolic'] : null;
        $pulse_rate = !empty($_POST['pulse_rate']) ? (int)$_POST['pulse_rate'] : null;
        $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
        $height = !empty($_POST['height']) ? (float)$_POST['height'] : null;
        $oxygen_saturation = !empty($_POST['oxygen_saturation']) ? (int)$_POST['oxygen_saturation'] : null;
        $vital_notes = trim($_POST['vital_notes'] ?? '');

        $bmi = null;
        if ($weight && $height && $height > 0) {
            $height_m = $height / 100;
            $bmi = round($weight / ($height_m * $height_m), 1);
        }

        $errors = [];
        if (empty($full_name_input)) $errors[] = 'Full name is required';
        if (empty($gender)) $errors[] = 'Gender is required';

        $duplicate_error = '';
        if (empty($errors) && !empty($full_name_input) && !empty($phone)) {
            $stmt = $db->prepare("SELECT id, full_name, patient_id FROM patients WHERE full_name = ? AND phone = ? AND branch_id = ? AND date_of_birth = ?");
            $stmt->execute([$full_name_input, $phone, $form_branch_id, $date_of_birth]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $duplicate_error = "❌ A patient with the same name, phone, and date of birth already exists.<br>👤 Name: <strong>" . htmlspecialchars($existing['full_name']) . "</strong><br>🆔 ID: <strong>" . htmlspecialchars($existing['patient_id']) . "</strong>";
            }
        }

        if (!empty($duplicate_error)) {
            $message = $duplicate_error; $message_type = 'error';
        } elseif (empty($errors)) {
            try {
                $db->beginTransaction();

                $final_patient_id = generateUniquePatientId($db, $form_branch_id);

                $stmt = $db->prepare("
                    INSERT INTO patients (
                        patient_id, full_name, date_of_birth, gender, phone, email, 
                        address, emergency_contact, blood_group, allergies, branch_id, 
                        created_by, marital_status, registered_by, registered_by_name,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $final_patient_id, $full_name_input, $date_of_birth, $gender, $phone, $email,
                    $address, $emergency_contact, $blood_group, $allergies, $form_branch_id,
                    $registered_by_id, $marital_status, $registered_by_id, $registered_by_name
                ]);
                $patient_db_id = $db->lastInsertId();

                $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad($patient_db_id, 4, '0', STR_PAD_LEFT);
                $visit_status = 'pending';
                if ($assign_doctor && $doctor_id > 0) $visit_status = 'assigned';

                $consultation_fee_to_store = ($assign_doctor && $doctor_id > 0) ? $consultation_fee : 0;

                $stmt = $db->prepare("
                    INSERT INTO visits (
                        visit_number, visit_date, patient_id, receptionist_id, branch_id, 
                        visit_type, service_id, consultation_fee, status, doctor_id,
                        symptoms, complaint, notes, created_at, updated_at
                    ) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $visit_number, $patient_db_id, $user_id, $form_branch_id,
                    $visit_type_name, $service_id_to_store, $consultation_fee_to_store,
                    $visit_status, $assign_doctor && $doctor_id > 0 ? $doctor_id : null,
                    $symptoms, $complaint, $notes
                ]);
                $visit_id = $db->lastInsertId();

                if ($visit_id && ($temperature || $bp_systolic || $bp_diastolic || $pulse_rate || $weight || $height || $oxygen_saturation)) {
                    $stmt = $db->prepare("
                        INSERT INTO vital_signs (
                            patient_id, visit_id, recorded_by, branch_id,
                            temperature, blood_pressure_systolic, blood_pressure_diastolic,
                            pulse_rate, weight, height, bmi, oxygen_saturation, notes, recorded_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $patient_db_id, $visit_id, $user_id, $form_branch_id,
                        $temperature, $bp_systolic, $bp_diastolic,
                        $pulse_rate, $weight, $height, $bmi, $oxygen_saturation, $vital_notes ?: null
                    ]);
                }

                $bill_created = false;
                $bill_number = null;

                if ($assign_doctor && $doctor_id > 0) {
                    $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = ? WHERE id = ?");
                    $stmt->execute([$doctor_id, $patient_db_id]);

                    $stmt = $db->prepare("UPDATE visits SET assigned_at = NOW() WHERE id = ?");
                    $stmt->execute([$visit_id]);

                    if ($consultation_fee > 0) {
                        $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($patient_db_id, 4, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999);

                        $stmt = $db->prepare("
                            INSERT INTO bills (
                                bill_number, patient_id, visit_id, subtotal, discount_amount, 
                                total_amount, paid_amount, balance, status, created_by, branch_id, created_at
                            ) VALUES (?, ?, ?, ?, 0, ?, 0, ?, 'pending', ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $bill_number, $patient_db_id, $visit_id,
                            $consultation_fee, $consultation_fee, $consultation_fee,
                            $user_id, $form_branch_id
                        ]);
                        $bill_id = $db->lastInsertId();

                        $stmt = $db->prepare("
                            INSERT INTO bill_items (
                                bill_id, patient_id, branch_id, item_type, item_name,
                                quantity, unit_price, total_price, status, created_at
                            ) VALUES (?, ?, ?, 'consultation', ?, 1, ?, ?, 'pending', NOW())
                        ");
                        $stmt->execute([
                            $bill_id, $patient_db_id, $form_branch_id,
                            $visit_type_name, $consultation_fee, $consultation_fee
                        ]);

                        $stmt = $db->prepare("UPDATE visits SET visit_total = visit_total + ?, consultation_fee = ? WHERE id = ?");
                        $stmt->execute([$consultation_fee, $consultation_fee, $visit_id]);

                        $bill_created = true;

                        try {
                            $stmt = $db->prepare("SELECT id FROM users WHERE role = 'cashier' AND status = 'active' AND branch_id = ?");
                            $stmt->execute([$form_branch_id]);
                            $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            foreach ($cashiers as $cashier) {
                                $stmt = $db->prepare("
                                    INSERT INTO notifications (user_id, branch_id, title, message, type, link, is_read, created_at)
                                    VALUES (?, ?, '💰 New Bill Created', ?, 'bill', 'cashier_dashboard.php', 0, NOW())
                                ");
                                $stmt->execute([
                                    $cashier['id'], $form_branch_id,
                                    "Consultation bill #$bill_number (TSh " . number_format($consultation_fee) . ") for patient " . htmlspecialchars($full_name_input) . " - " . $visit_type_name
                                ]);
                            }
                        } catch (Exception $e) {}
                    }
                }

                $db->commit();

                $message = "✅ Patient registered successfully!";
                $message .= "<br>📋 Patient ID: <strong>$final_patient_id</strong>";
                $message .= "<br>📋 Visit #: <strong>$visit_number</strong>";
                $message .= "<br>📋 Visit Type: <strong>" . htmlspecialchars($visit_type_name) . "</strong>";
                $message .= "<br>📋 Consultation Fee: <strong>TSh " . number_format($consultation_fee, 0) . "</strong>";
                $message .= "<br>🏥 Branch: <strong>" . htmlspecialchars($current_branch_name) . "</strong>";
                $message .= "<br>👤 Registered By: <strong>" . htmlspecialchars($registered_by_name) . "</strong>";

                if ($assign_doctor && $doctor_id > 0) {
                    $doctor_name = '';
                    foreach ($doctors as $doc) { if ($doc['id'] == $doctor_id) { $doctor_name = $doc['full_name']; break; } }
                    $message .= "<br>👨‍⚕️ Doctor: <strong>Dr. " . htmlspecialchars($doctor_name) . "</strong>";
                    if ($bill_created && $bill_number) $message .= "<br>💰 Bill: <strong>#$bill_number</strong> sent to Cashier!";
                } else {
                    $message .= "<br>⏳ Doctor: <strong>Not assigned</strong> - Patient is in Pending list";
                }

                if ($temperature || $bp_systolic || $pulse_rate || $weight || $height || $oxygen_saturation) {
                    $message .= "<br>❤️ Vital signs recorded!";
                    if ($oxygen_saturation) {
                        $spo2_status = $oxygen_saturation >= 95 ? '✅ Normal' : ($oxygen_saturation >= 90 ? '⚠️ Low' : '🚨 Critical');
                        $message .= " (SpO₂: {$oxygen_saturation}% - {$spo2_status})";
                    }
                }

                $message_type = 'success';

                echo '<script>
                    setTimeout(function(){ 
                        window.location.href = "patients.php?branch=' . urlencode($selected_branch_id) . '&registered=1"; 
                    }, 4000);
                </script>';

            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }

} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $unread_notifications = 0;
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES
       ================================================================ */
    :root {
        --page-primary: #0B5ED7;
        --page-primary-dark: #0A4CA8;
        --page-primary-light: #E8F0FE;
        --page-success: #059669;
        --page-success-dark: #047857;
        --page-success-bg: #D1FAE5;
        --page-danger: #DC2626;
        --page-danger-bg: #FEE2E2;
        --page-warning: #D97706;
        --page-warning-bg: #FEF3C7;
        --page-purple: #7C3AED;
        --page-purple-bg: #EDE9FE;
        --page-cyan: #0891B2;
        --page-cyan-bg: #CFFAFE;
        --page-gray-50: #F8FAFC;
        --page-gray-100: #F1F5F9;
        --page-gray-200: #E2E8F0;
        --page-gray-300: #CBD5E1;
        --page-gray-400: #94A3B8;
        --page-gray-500: #64748B;
        --page-gray-600: #475569;
        --page-gray-700: #334155;
        --page-gray-800: #1E293B;
        --page-gray-900: #0F172A;
        --page-bg-body: #F1F5F9;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-border: #E2E8F0;
        --page-radius: 12px;
        --page-radius-lg: 18px;
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-border: #334155;
        --page-primary-bg: #1E3A5F;
        --page-success-bg: #1A3A2A;
        --page-danger-bg: #3A1A1A;
        --page-warning-bg: #3D2E0A;
        --page-purple-bg: #2A1A3A;
        --page-cyan-bg: #164E63;
    }

    /* ================================================================
       BODY & MAIN CONTENT - DARK MODE
       ================================================================ */
    body {
        background: var(--page-bg-body, #F1F5F9);
    }

    html[data-theme="dark"] body {
        background: #0F172A !important;
    }

    .main-content {
        background: var(--page-bg-body, #F1F5F9);
    }

    html[data-theme="dark"] .main-content {
        background: #0F172A !important;
    }

    /* ================================================================
       ✅ BEAUTIFUL BLUE PAGE DETAILS CARD (STATS)
       ================================================================ */
    .page-details-card {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4FB0 40%, #083D8A 100%);
        border-radius: 24px;
        padding: 32px 36px;
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 12px 40px rgba(11, 94, 215, 0.35), 0 6px 16px rgba(11, 94, 215, 0.2);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .page-details-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 20px 50px rgba(11, 94, 215, 0.45), 0 10px 20px rgba(11, 94, 215, 0.25);
    }

    /* Decorative circles */
    .page-details-card::before {
        content: '';
        position: absolute;
        top: -100px;
        right: -50px;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-details-card::after {
        content: '';
        position: absolute;
        bottom: -120px;
        left: -80px;
        width: 350px;
        height: 350px;
        background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    /* Animated shine */
    .page-details-card .shine {
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
        animation: shine 6s infinite;
        pointer-events: none;
    }

    @keyframes shine {
        0% { left: -100%; }
        50% { left: 100%; }
        100% { left: 100%; }
    }

    /* Header */
    .page-details-header {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        position: relative;
        z-index: 2;
        margin-bottom: 24px;
    }

    .page-details-title-wrapper {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .page-details-icon {
        width: 60px;
        height: 60px;
        background: rgba(255,255,255,0.2);
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        backdrop-filter: blur(10px);
        border: 1.5px solid rgba(255,255,255,0.25);
        color: white;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .page-details-title {
        font-size: 1.7rem;
        font-weight: 800;
        color: white;
        margin: 0 0 4px 0;
        letter-spacing: -0.02em;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .page-details-role {
        background: rgba(255,255,255,0.25);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.3);
    }

    .page-details-subtitle {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.9);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .page-details-subtitle i {
        opacity: 0.85;
    }

    /* Stats Grid */
    .page-details-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
        position: relative;
        z-index: 2;
        margin-bottom: 20px;
    }

    .stat-card {
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.25);
        border-radius: 14px;
        padding: 16px 18px;
        display: flex;
        align-items: center;
        gap: 14px;
        backdrop-filter: blur(10px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: default;
    }

    .stat-card:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        border-color: rgba(255,255,255,0.4);
    }

    .stat-card-icon {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
        background: rgba(255,255,255,0.25);
        color: white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }

    .stat-card-icon.success { background: linear-gradient(135deg, #059669, #34D399); }
    .stat-card-icon.warning { background: linear-gradient(135deg, #D97706, #FBBF24); }
    .stat-card-icon.danger { background: linear-gradient(135deg, #DC2626, #F87171); }
    .stat-card-icon.purple { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
    .stat-card-icon.cyan { background: linear-gradient(135deg, #0891B2, #22D3EE); }

    .stat-card-info {
        flex: 1;
        min-width: 0;
    }

    .stat-card-value {
        font-size: 1.4rem;
        font-weight: 800;
        color: white;
        line-height: 1.1;
        margin: 0;
        letter-spacing: -0.02em;
    }

    .stat-card-label {
        font-size: 0.72rem;
        color: rgba(255,255,255,0.85);
        font-weight: 600;
        margin: 2px 0 0 0;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .stat-card-sublabel {
        font-size: 0.6rem;
        color: rgba(255,255,255,0.7);
        margin: 0;
        font-weight: 500;
    }

    /* Info Badges Row */
    .page-details-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        position: relative;
        z-index: 2;
    }

    .info-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.25);
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
        color: white;
        backdrop-filter: blur(10px);
        transition: all 0.3s ease;
    }

    .info-badge:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .info-badge i {
        font-size: 0.75rem;
        opacity: 0.9;
    }

    .info-badge strong {
        font-weight: 700;
    }

    /* Action Button */
    .page-details-back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 11px 22px;
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.3);
        border-radius: 14px;
        color: white;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        backdrop-filter: blur(10px);
        position: relative;
        z-index: 2;
        white-space: nowrap;
    }

    .page-details-back-btn:hover {
        background: rgba(255,255,255,0.3);
        transform: translateX(-4px);
        color: white;
        box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        border-color: rgba(255,255,255,0.5);
    }

    /* ================================================================
       FORM CARD
       ================================================================ */
    .form-card-modern {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 20px;
        padding: 28px 32px;
        border: 1px solid var(--page-border, #E2E8F0);
        max-width: 1200px;
        margin: 0 auto;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    html[data-theme="dark"] .form-card-modern {
        background: #1E293B;
        border-color: #334155;
        box-shadow: 0 2px 12px rgba(0,0,0,0.3);
    }

    .form-card-modern .form-header {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .form-card-modern .form-header {
        border-bottom-color: #334155;
    }

    .form-card-modern .form-header .form-icon {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }

    .form-card-modern .form-header .form-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
    }

    html[data-theme="dark"] .form-card-modern .form-header .form-title {
        color: #F1F5F9;
    }

    .form-card-modern .form-header .form-subtitle {
        font-size: 0.75rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 2px;
    }

    /* ================================================================
       FORM ELEMENTS
       ================================================================ */
    .form-label {
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 4px;
        display: block;
    }

    html[data-theme="dark"] .form-label {
        color: #F1F5F9;
    }

    .form-label .required { color: #DC2626; margin-left: 2px; }
    .form-label .label-icon { margin-right: 4px; color: #0B5ED7; }

    html[data-theme="dark"] .form-label .label-icon { color: #6EA8FE; }

    .form-control {
        width: 100%;
        padding: 9px 12px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 12px;
        font-size: 0.8rem;
        outline: none;
        background: var(--page-bg-card, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        font-family: inherit;
        transition: all 0.3s ease;
    }

    html[data-theme="dark"] .form-control {
        background: #0F172A;
        color: #F1F5F9;
        border-color: #334155;
    }

    html[data-theme="dark"] .form-control::placeholder {
        color: #64748B;
    }

    html[data-theme="dark"] .form-control option {
        background: #1E293B;
        color: #F1F5F9;
    }

    .form-control:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.08);
    }

    html[data-theme="dark"] .form-control:focus {
        border-color: #6EA8FE;
        box-shadow: 0 0 0 4px rgba(110, 168, 254, 0.15);
    }

    .form-row { margin-bottom: 16px; }

    .form-actions {
        display: flex;
        gap: 10px;
        padding-top: 20px;
        margin-top: 20px;
        border-top: 2px solid var(--page-border, #E2E8F0);
        flex-wrap: wrap;
    }

    html[data-theme="dark"] .form-actions {
        border-top-color: #334155;
    }

    /* Autocomplete */
    .autocomplete-wrapper { position: relative; }

    .autocomplete-dropdown {
        position: absolute;
        top: 100%; left: 0; right: 0;
        background: var(--page-bg-card, #FFFFFF);
        border: 2px solid #0B5ED7;
        border-top: none;
        border-radius: 0 0 12px 12px;
        max-height: 280px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        display: none;
    }

    html[data-theme="dark"] .autocomplete-dropdown {
        background: #1E293B;
        border-color: #3B82F6;
    }

    .autocomplete-dropdown.show { display: block; }

    .autocomplete-item {
        padding: 10px 14px;
        cursor: pointer;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        display: flex;
        align-items: center;
        gap: 10px;
        transition: background 0.2s ease;
    }

    html[data-theme="dark"] .autocomplete-item {
        border-bottom-color: #334155;
    }

    .autocomplete-item:hover, .autocomplete-item.active {
        background: var(--page-primary-light, #E8F0FE);
    }

    html[data-theme="dark"] .autocomplete-item:hover,
    html[data-theme="dark"] .autocomplete-item.active {
        background: #1E3A5F;
    }

    .autocomplete-item .patient-avatar {
        width: 34px; height: 34px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.8rem;
        flex-shrink: 0;
    }

    .autocomplete-item .patient-info { flex: 1; min-width: 0; }
    .autocomplete-item .patient-info .patient-name {
        font-weight: 600;
        font-size: 0.82rem;
        color: var(--page-text-primary, #1E293B);
        display: block;
    }
    html[data-theme="dark"] .autocomplete-item .patient-info .patient-name {
        color: #F1F5F9;
    }
    .autocomplete-item .patient-info .patient-meta {
        font-size: 0.6rem;
        color: var(--page-text-secondary, #64748B);
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 2px;
    }

    .autocomplete-empty, .autocomplete-loading {
        padding: 16px;
        text-align: center;
        color: var(--page-text-secondary, #64748B);
        font-size: 0.75rem;
    }

    /* Allergy chips */
    .allergy-checkbox-group { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }

    .allergy-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px 3px 8px;
        border-radius: 20px;
        border: 2px solid var(--page-border, #E2E8F0);
        background: var(--page-bg-body, #F1F5F9);
        cursor: pointer;
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
        user-select: none;
        transition: all 0.3s ease;
    }

    html[data-theme="dark"] .allergy-chip {
        background: #0F172A;
        border-color: #334155;
        color: #94A3B8;
    }

    .allergy-chip:hover { border-color: #0B5ED7; background: #E8F0FE; }
    html[data-theme="dark"] .allergy-chip:hover {
        background: #1E3A5F;
        border-color: #6EA8FE;
    }

    .allergy-chip input[type="checkbox"] { display: none; }
    .allergy-chip.active {
        border-color: #DC2626;
        background: #FEE2E2;
        color: #B91C1C;
    }
    html[data-theme="dark"] .allergy-chip.active {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }

    /* Vital Signs */
    .vital-signs-section {
        margin-top: 20px;
        padding-top: 16px;
        border-top: 2px solid var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .vital-signs-section {
        border-top-color: #334155;
    }

    .vital-signs-section .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 14px;
    }

    .vital-signs-section .section-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    html[data-theme="dark"] .vital-signs-section .section-title { color: #F1F5F9; }

    .vital-signs-section .section-title i { color: #DC2626; font-size: 1rem; }

    .vital-signs-section .section-badge {
        font-size: 0.6rem;
        font-weight: 500;
        padding: 2px 12px;
        border-radius: 12px;
        background: #FEF3C7;
        color: #D97706;
        border: 1px solid #D97706;
    }

    html[data-theme="dark"] .vital-signs-section .section-badge {
        background: #3D2E0A;
        color: #FBBF24;
        border-color: #FBBF24;
    }

    .vital-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 12px;
    }

    .vital-grid-4 {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
    }

    .vital-card {
        background: var(--page-bg-body, #F1F5F9);
        border-radius: 12px;
        padding: 14px 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        min-height: 110px;
    }

    html[data-theme="dark"] .vital-card {
        background: #0F172A;
        border-color: #334155;
    }

    .vital-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
    }

    .vital-card:hover { border-color: #6EA8FE; transform: translateY(-3px); }

    .vital-card .vital-header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }

    .vital-card .vital-icon {
        width: 32px; height: 32px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
        background: var(--page-bg-card, #FFFFFF);
    }

    .vital-card .vital-label {
        font-size: 0.62rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        display: block;
    }

    html[data-theme="dark"] .vital-card .vital-label { color: #F1F5F9; }

    .vital-card .vital-sublabel {
        font-size: 0.5rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 500;
        display: block;
    }

    .vital-card .vital-input-wrap {
        position: relative;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: auto;
    }

    .vital-card .vital-input-wrap input {
        width: 100%;
        padding: 8px 12px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 8px;
        font-size: 0.95rem;
        font-weight: 700;
        background: var(--page-bg-card, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        outline: none;
    }

    html[data-theme="dark"] .vital-card .vital-input-wrap input {
        background: #1E293B;
        color: #F1F5F9;
        border-color: #334155;
    }

    .vital-card .vital-input-wrap input:focus { border-color: #0B5ED7; }

    .vital-card .vital-unit {
        font-size: 0.55rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 600;
        padding: 2px 6px;
        background: var(--page-gray-200, #E2E8F0);
        border-radius: 6px;
        white-space: nowrap;
    }

    html[data-theme="dark"] .vital-card .vital-unit {
        background: #334155;
    }

    .vital-card.temperature::before { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    .vital-card.temperature .vital-icon { color: #DC2626; background: rgba(220, 38, 38, 0.1); }

    .vital-card.bp::before { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .vital-card.bp .vital-icon { color: #0B5ED7; background: rgba(11, 94, 215, 0.1); }

    .vital-card.pulse::before { background: linear-gradient(135deg, #059669, #047857); }
    .vital-card.pulse .vital-icon { color: #059669; background: rgba(5, 150, 105, 0.1); }

    .vital-card.weight::before { background: linear-gradient(135deg, #D97706, #B45309); }
    .vital-card.weight .vital-icon { color: #D97706; background: rgba(217, 119, 6, 0.1); }

    .vital-card.height::before { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    .vital-card.height .vital-icon { color: #7C3AED; background: rgba(124, 58, 237, 0.1); }

    .vital-card.bmi::before { background: linear-gradient(135deg, #0D9488, #0F766E); }
    .vital-card.bmi .vital-icon { color: #0D9488; background: rgba(13, 148, 136, 0.1); }
    .vital-card.bmi .vital-input-wrap input { background: #E8F0FE; font-weight: 800; color: #0B5ED7; }

    .vital-card.spo2::before { background: linear-gradient(135deg, #0891B2, #0E7490); }
    .vital-card.spo2 .vital-icon { color: #0891B2; background: rgba(8, 145, 178, 0.1); }
    .vital-card.spo2 .vital-input-wrap input { background: rgba(8, 145, 178, 0.05); font-weight: 700; color: #0891B2; }
    .vital-card.spo2 .vital-input-wrap input:focus { border-color: #0891B2; box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.15); }

    .vital-bmi-category {
        font-size: 0.5rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 6px;
        display: inline-block;
        margin-top: 4px;
    }

    .vital-bmi-category.underweight { background: rgba(217, 119, 6, 0.15); color: #D97706; }
    .vital-bmi-category.normal { background: rgba(5, 150, 105, 0.15); color: #059669; }
    .vital-bmi-category.overweight { background: rgba(217, 119, 6, 0.15); color: #D97706; }
    .vital-bmi-category.obese { background: rgba(220, 38, 38, 0.15); color: #DC2626; }

    .vital-bmi-category.spo2-normal { background: rgba(8, 145, 178, 0.15); color: #0891B2; }
    .vital-bmi-category.spo2-low { background: rgba(217, 119, 6, 0.15); color: #D97706; animation: pulse-spo2 1.5s infinite; }
    .vital-bmi-category.spo2-critical { background: rgba(220, 38, 38, 0.3); color: #DC2626; font-weight: 700; animation: pulse-spo2 1s infinite; }

    @keyframes pulse-spo2 {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }

    .vital-card.bp .vital-input-wrap input { text-align: center; padding: 8px 4px; }
    .vital-card.bp .vital-input-wrap .bp-separator { color: var(--page-text-secondary, #64748B); font-weight: 700; font-size: 0.8rem; }

    /* Assign doctor */
    .assign-doctor-section {
        background: var(--page-primary-light, #E8F0FE);
        border-radius: 12px;
        padding: 18px 20px;
        border: 2px solid #6EA8FE;
        margin-top: 16px;
    }

    html[data-theme="dark"] .assign-doctor-section {
        background: #1E3A5F;
        border-color: #3B82F6;
    }

    .assign-doctor-section .section-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        flex-wrap: wrap;
    }

    html[data-theme="dark"] .assign-doctor-section .section-title { color: #F1F5F9; }

    .assign-doctor-section .section-title i { color: #0B5ED7; }

    .assign-doctor-toggle {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 10px;
        border: 2px solid var(--page-border, #E2E8F0);
        cursor: pointer;
        margin-bottom: 12px;
    }

    html[data-theme="dark"] .assign-doctor-toggle {
        background: #0F172A;
        border-color: #334155;
    }

    .assign-doctor-toggle input[type="checkbox"] {
        width: 18px; height: 18px;
        accent-color: #0B5ED7;
        cursor: pointer;
    }

    .assign-doctor-toggle .toggle-label { font-weight: 500; color: var(--page-text-primary, #1E293B); font-size: 0.8rem; }
    html[data-theme="dark"] .assign-doctor-toggle .toggle-label { color: #F1F5F9; }
    .assign-doctor-toggle .toggle-sub { font-size: 0.65rem; color: var(--page-text-secondary, #64748B); }
    .assign-doctor-toggle .fee-info {
        margin-left: auto;
        font-size: 0.65rem;
        font-weight: 600;
        color: #0B5ED7;
        background: var(--page-primary-light, #E8F0FE);
        padding: 2px 12px;
        border-radius: 12px;
    }

    html[data-theme="dark"] .assign-doctor-toggle .fee-info {
        background: #1E3A5F;
        color: #6EA8FE;
    }

    .assign-doctor-fields { display: none; margin-top: 10px; }
    .assign-doctor-fields.show { display: block; }

    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 20px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        border: none;
        text-decoration: none;
        min-height: 40px;
        transition: all 0.3s ease;
    }

    .btn-primary {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
    }

    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(11, 94, 215, 0.35); color: white; }

    .btn-outline {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .btn-outline {
        color: #94A3B8;
        border-color: #334155;
    }

    .btn-outline:hover { background: var(--page-bg-body, #F1F5F9); border-color: #0B5ED7; color: #0B5ED7; }
    html[data-theme="dark"] .btn-outline:hover {
        background: #0F172A;
        border-color: #6EA8FE;
        color: #6EA8FE;
    }

    /* Alert */
    .alert {
        padding: 14px 18px;
        border-radius: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
        animation: slideDown 0.4s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .alert-success { background: #D1FAE5; color: #047857; border: 1px solid #059669; }
    .alert-error { background: #FEE2E2; color: #B91C1C; border: 1px solid #DC2626; }

    html[data-theme="dark"] .alert-success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
    html[data-theme="dark"] .alert-error { background: #3A1A1A; color: #F87171; border-color: #F87171; }

    .alert i { font-size: 1.1rem; margin-top: 2px; }

    /* Toast */
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 14px 20px;
        border-radius: 12px;
        z-index: 9999;
        max-width: 400px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }

    .toast-custom.show { transform: translateY(0); opacity: 1; }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #DC2626; }
    .toast-custom.info { background: #0B5ED7; }

    /* Grid */
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .grid-full { grid-column: 1 / -1; }

    /* Responsive */
    @media (max-width: 1024px) {
        .form-card-modern { padding: 20px; }
        .vital-grid-4 { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .page-details-card { padding: 22px 20px; }
        .page-details-title { font-size: 1.3rem; }
        .page-details-icon { width: 48px; height: 48px; font-size: 1.3rem; }
        .page-details-stats { grid-template-columns: 1fr 1fr; gap: 10px; }
        .stat-card { padding: 12px 14px; gap: 10px; }
        .stat-card-icon { width: 38px; height: 38px; font-size: 0.95rem; }
        .stat-card-value { font-size: 1.15rem; }
        .form-card-modern { padding: 14px; }
        .grid-2 { grid-template-columns: 1fr; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
        .vital-grid-3, .vital-grid-4 { grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .vital-card { padding: 10px; min-height: 95px; }
        .vital-card .vital-icon { width: 26px; height: 26px; font-size: 0.85rem; }
        .vital-card .vital-label { font-size: 0.55rem; }
    }

    @media (max-width: 640px) {
        .page-details-stats { grid-template-columns: 1fr; }
        .form-card-modern { padding: 10px; }
        .vital-grid-3, .vital-grid-4 { grid-template-columns: repeat(2, 1fr); gap: 6px; }
    }

    /* Print */
    @media print {
        .page-details-card, .form-actions, .btn, .page-details-back-btn { display: none !important; }
        .form-card-modern { box-shadow: none !important; border: 1px solid #ddd !important; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================
         ✅ BEAUTIFUL BLUE PAGE DETAILS CARD
         ================================================================ -->
    <div class="page-details-card">
        <div class="shine"></div>

        <div class="page-details-header">
            <div class="page-details-title-wrapper">
                <div class="page-details-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div>
                    <h1 class="page-details-title">
                        Add New Patient
                        <span class="page-details-role">👑 ADMIN</span>
                    </h1>
                    <p class="page-details-subtitle">
                        <i class="fas fa-hospital"></i>
                        Create new patient in <strong><?= htmlspecialchars($current_branch_name) ?></strong>
                    </p>
                </div>
            </div>
            <a href="patients.php?branch=<?= urlencode($selected_branch_id) ?>" class="page-details-back-btn">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>

        <!-- Stats Grid -->
        <div class="page-details-stats">
            <div class="stat-card">
                <div class="stat-card-icon cyan">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="stat-card-info">
                    <p class="stat-card-value" style="font-size:0.95rem;font-family:'Courier New',monospace;"><?= $patient_id_number ?></p>
                    <p class="stat-card-label"><i class="fas fa-arrow-right"></i> Next Patient ID</p>
                    <p class="stat-card-sublabel">Auto-generated</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-icon warning">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-card-info">
                    <p class="stat-card-value" id="headerFeeDisplay">TSh <?= number_format($default_price, 0) ?></p>
                    <p class="stat-card-label"><i class="fas fa-tag"></i> Consultation Fee</p>
                    <p class="stat-card-sublabel" id="headerFeeType"><?= htmlspecialchars($default_service_name) ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-icon success">
                    <i class="fas fa-user-md"></i>
                </div>
                <div class="stat-card-info">
                    <p class="stat-card-value"><?= $online_doctors_count ?> <span style="font-size:0.8rem;opacity:0.7;">/ <?= $total_doctors ?></span></p>
                    <p class="stat-card-label"><i class="fas fa-circle" style="font-size:0.4rem;color:#34D399;"></i> Doctors Online</p>
                    <p class="stat-card-sublabel"><?= $offline_doctors_count ?> offline</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-icon purple">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <div class="stat-card-info">
                    <p class="stat-card-value">7</p>
                    <p class="stat-card-label"><i class="fas fa-check-circle"></i> Vital Signs</p>
                    <p class="stat-card-sublabel">With SpO₂ tracking</p>
                </div>
            </div>
        </div>

        <!-- Info Badges -->
        <div class="page-details-badges">
            <span class="info-badge">
                <i class="fas fa-user-shield"></i>
                Registering as: <strong><?= htmlspecialchars($user_full_name) ?></strong>
            </span>
            <span class="info-badge">
                <i class="fas fa-search"></i>
                Autocomplete enabled
            </span>
            <span class="info-badge">
                <i class="fas fa-users"></i>
                Family can share phone
            </span>
            <span class="info-badge">
                <i class="fas fa-shield-alt"></i>
                Duplicate detection ON
            </span>
        </div>
    </div>

    <!-- Alert -->
    <?php if ($message): ?>
        <div class="alert <?= $message_type === 'success' ? 'alert-success' : 'alert-error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="form-card-modern">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <div>
                <h3 class="form-title">Patient Registration Form</h3>
                <p class="form-subtitle">
                    Fill in the patient details below to complete registration
                    <span style="color:#7C3AED;font-weight:600;margin-left:6px;">
                        <i class="fas fa-user-shield"></i> Registered by: <?= htmlspecialchars($user_full_name) ?>
                    </span>
                </p>
            </div>
        </div>

        <form method="POST" action="" id="registrationForm" autocomplete="off">
            <input type="hidden" name="branch_id" value="<?= $target_branch_id ?>">
            <input type="hidden" name="register_patient" value="1">
            <input type="hidden" name="registered_by_id" value="<?= $user_id ?>">
            <input type="hidden" name="registered_by_name" value="<?= htmlspecialchars($user_full_name) ?>">

            <!-- Full Name -->
            <div class="form-row grid-full">
                <label class="form-label">
                    <i class="fas fa-user label-icon"></i> Full Name <span class="required">*</span>
                    <span style="font-size:0.6rem;color:var(--page-text-secondary);font-weight:400;">(Type to search existing patients)</span>
                </label>
                <div class="autocomplete-wrapper">
                    <input type="text" name="full_name" id="fullNameInput" class="form-control"
                           placeholder="Start typing patient name..."
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                           autocomplete="off" required
                           oninput="searchPatients(this.value)"
                           onfocus="searchPatients(this.value)"
                           onblur="setTimeout(function(){ hideAutocomplete(); }, 200)">
                    <div class="autocomplete-dropdown" id="autocompleteDropdown">
                        <div class="autocomplete-loading" id="autocompleteLoading" style="display:none;">
                            <i class="fas fa-spinner fa-spin"></i> Searching...
                        </div>
                        <div id="autocompleteResults"></div>
                    </div>
                </div>
            </div>

            <!-- Patient Type + Gender -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-tag label-icon"></i> Patient Type <span class="required">*</span>
                    </label>
                    <select name="visit_type" class="form-control" id="visitTypeSelect" required onchange="updateFeeDisplay()">
                        <?php foreach ($visit_type_options as $id => $option): ?>
                            <option value="<?= $id ?>"
                                    data-price="<?= $option['price'] ?>"
                                    data-service-name="<?= htmlspecialchars($option['service_name']) ?>"
                                    <?= ($id === $default_key) ? 'selected' : '' ?>>
                                <?= $option['icon'] ?? '🏥' ?> <?= htmlspecialchars($option['display_name']) ?> - TSh <?= number_format($option['price'], 0) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="margin-top:6px;">
                        <span style="display:inline-block;padding:3px 12px;border-radius:20px;background:#E8F0FE;color:#0B5ED7;font-weight:600;font-size:0.7rem;border:1px solid #6EA8FE;" id="visitTypePreview">
                            <?= htmlspecialchars($default_service_name) ?>
                        </span>
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-venus-mars label-icon"></i> Gender <span class="required">*</span>
                    </label>
                    <select name="gender" class="form-control" required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?= ($_POST['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>👨 Male</option>
                        <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>👩 Female</option>
                        <option value="Other" <?= ($_POST['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>⚧ Other</option>
                    </select>
                </div>
            </div>

            <!-- Marital + DOB -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-ring label-icon"></i> Marital Status
                    </label>
                    <select name="marital_status" class="form-control">
                        <option value="">Select Marital Status</option>
                        <?php foreach ($marital_statuses as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($_POST['marital_status'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-calendar label-icon"></i> Date of Birth
                    </label>
                    <input type="date" name="date_of_birth" class="form-control"
                           value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                </div>
            </div>

            <!-- Phone + Email -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-phone label-icon"></i> Phone Number
                        <span style="font-size:0.6rem;color:#059669;font-weight:400;">(Optional - Family can share)</span>
                    </label>
                    <input type="tel" name="phone" class="form-control" placeholder="e.g. 0759 154 160"
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>

                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-envelope label-icon"></i> Email
                        <span style="font-size:0.6rem;color:#059669;font-weight:400;">(Optional)</span>
                    </label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. john@example.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <!-- Blood Group + Emergency Contact -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-tint label-icon"></i> Blood Group
                    </label>
                    <select name="blood_group" class="form-control">
                        <option value="">Select Blood Group</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                            <option value="<?= $bg ?>" <?= ($_POST['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-phone-alt label-icon"></i> Emergency Contact
                    </label>
                    <input type="tel" name="emergency_contact" class="form-control" placeholder="e.g. 0755 123 456"
                           value="<?= htmlspecialchars($_POST['emergency_contact'] ?? '') ?>">
                </div>
            </div>

            <!-- Address -->
            <div class="form-row grid-full">
                <label class="form-label">
                    <i class="fas fa-home label-icon"></i> Address
                </label>
                <textarea name="address" class="form-control" placeholder="Enter full address..." rows="2"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
            </div>

            <!-- Allergies -->
            <div class="form-row grid-full">
                <label class="form-label">
                    <i class="fas fa-allergies label-icon"></i> Allergies
                    <span style="font-size:0.6rem;color:var(--page-text-secondary);font-weight:400;">(Click to select common allergies)</span>
                </label>
                <div class="allergy-checkbox-group" id="allergyCheckboxGroup">
                    <?php foreach ($common_allergies as $key => $label): ?>
                        <label class="allergy-chip" data-allergy="<?= htmlspecialchars($label) ?>">
                            <input type="checkbox" value="<?= htmlspecialchars($label) ?>" class="allergy-checkbox">
                            <span>⚠️</span>
                            <?= htmlspecialchars($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <textarea name="allergies" id="allergiesTextarea" class="form-control" style="margin-top:8px;" placeholder="List any known allergies..." rows="2"><?= htmlspecialchars($_POST['allergies'] ?? '') ?></textarea>
            </div>

            <!-- Vital Signs -->
            <div class="vital-signs-section">
                <div class="section-header">
                    <div class="section-title">
                        <i class="fas fa-heartbeat"></i>
                        Vital Signs
                        <span style="font-size:0.7rem;font-weight:400;color:var(--page-text-secondary);">(7 vitals)</span>
                    </div>
                    <span class="section-badge"><i class="fas fa-info-circle"></i> Optional</span>
                </div>

                <!-- Row 1: Temp | BP | Pulse -->
                <div class="vital-grid-3">
                    <div class="vital-card temperature">
                        <div class="vital-header">
                            <span class="vital-icon">🌡️</span>
                            <div>
                                <span class="vital-label">Temperature</span>
                                <span class="vital-sublabel">Body temp</span>
                            </div>
                        </div>
                        <div class="vital-input-wrap">
                            <input type="number" name="temperature" step="0.1" min="30" max="45" placeholder="36.5" value="<?= htmlspecialchars($_POST['temperature'] ?? '') ?>">
                            <span class="vital-unit">°C</span>
                        </div>
                    </div>

                    <div class="vital-card bp">
                        <div class="vital-header">
                            <span class="vital-icon">💓</span>
                            <div>
                                <span class="vital-label">Blood Pressure</span>
                                <span class="vital-sublabel">Systolic / Diastolic</span>
                            </div>
                        </div>
                        <div class="vital-input-wrap">
                            <input type="number" name="bp_systolic" placeholder="120" value="<?= htmlspecialchars($_POST['bp_systolic'] ?? '') ?>">
                            <span class="bp-separator">/</span>
                            <input type="number" name="bp_diastolic" placeholder="80" value="<?= htmlspecialchars($_POST['bp_diastolic'] ?? '') ?>">
                            <span class="vital-unit">mmHg</span>
                        </div>
                    </div>

                    <div class="vital-card pulse">
                        <div class="vital-header">
                            <span class="vital-icon">❤️</span>
                            <div>
                                <span class="vital-label">Pulse Rate</span>
                                <span class="vital-sublabel">Heart beats</span>
                            </div>
                        </div>
                        <div class="vital-input-wrap">
                            <input type="number" name="pulse_rate" placeholder="72" value="<?= htmlspecialchars($_POST['pulse_rate'] ?? '') ?>">
                            <span class="vital-unit">bpm</span>
                        </div>
                    </div>
                </div>

                <!-- Row 2: Weight | Height | BMI | SpO2 -->
                <div class="vital-grid-4">
                    <div class="vital-card weight">
                        <div class="vital-header">
                            <span class="vital-icon">⚖️</span>
                            <div>
                                <span class="vital-label">Weight</span>
                                <span class="vital-sublabel">Body mass</span>
                            </div>
                        </div>
                        <div class="vital-input-wrap">
                            <input type="number" name="weight" step="0.1" placeholder="65" id="weightInput" oninput="calculateBMI()" value="<?= htmlspecialchars($_POST['weight'] ?? '') ?>">
                            <span class="vital-unit">kg</span>
                        </div>
                    </div>

                    <div class="vital-card height">
                        <div class="vital-header">
                            <span class="vital-icon">📏</span>
                            <div>
                                <span class="vital-label">Height</span>
                                <span class="vital-sublabel">Body length</span>
                            </div>
                        </div>
                        <div class="vital-input-wrap">
                            <input type="number" name="height" step="0.1" placeholder="170" id="heightInput" oninput="calculateBMI()" value="<?= htmlspecialchars($_POST['height'] ?? '') ?>">
                            <span class="vital-unit">cm</span>
                        </div>
                    </div>

                    <div class="vital-card bmi">
                        <div class="vital-header">
                            <span class="vital-icon">📊</span>
                            <div>
                                <span class="vital-label">BMI</span>
                                <span class="vital-sublabel">Auto-calculated</span>
                            </div>
                        </div>
                        <div class="vital-input-wrap">
                            <input type="number" name="bmi" id="bmiOutput" readonly step="0.1" placeholder="22.5" value="<?= htmlspecialchars($_POST['bmi'] ?? '') ?>">
                            <span class="vital-unit">kg/m²</span>
                        </div>
                        <span class="vital-bmi-category" id="bmiCategory">Auto</span>
                    </div>

                    <div class="vital-card spo2">
                        <div class="vital-header">
                            <span class="vital-icon">🫁</span>
                            <div>
                                <span class="vital-label">Oxygen Saturation</span>
                                <span class="vital-sublabel">SpO₂ level</span>
                            </div>
                        </div>
                        <div class="vital-input-wrap">
                            <input type="number" name="oxygen_saturation" step="1" min="0" max="100" placeholder="98" id="spo2Input" oninput="calculateSpO2Category()" value="<?= htmlspecialchars($_POST['oxygen_saturation'] ?? '') ?>">
                            <span class="vital-unit">%</span>
                        </div>
                        <span class="vital-bmi-category" id="spo2Category">Auto</span>
                    </div>
                </div>

                <div style="margin-top:12px;">
                    <input type="text" name="vital_notes" class="form-control" placeholder="Vital signs notes (optional)" value="<?= htmlspecialchars($_POST['vital_notes'] ?? '') ?>" style="font-size:0.7rem;padding:6px 10px;">
                </div>
            </div>

            <!-- Assign Doctor -->
            <div class="form-row grid-full" style="margin-top:16px;">
                <div class="assign-doctor-section">
                    <div class="section-title">
                        <i class="fas fa-user-md"></i>
                        Assign Doctor <span style="font-weight:400;font-size:0.75rem;color:var(--page-text-secondary);">(Optional)</span>
                        <span style="margin-left:auto;font-size:0.6rem;color:#059669;">
                            <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#34D399;margin-right:4px;"></span>
                            <span id="doctorUpdateStatus">Live</span>
                        </span>
                    </div>

                    <div class="assign-doctor-toggle" onclick="toggleAssignDoctor(event)">
                        <input type="checkbox" name="assign_doctor" id="assignDoctorCheckbox" value="1"
                               <?= isset($_POST['assign_doctor']) && $_POST['assign_doctor'] == 1 ? 'checked' : '' ?>
                               onchange="toggleAssignDoctor()">
                        <span class="toggle-label"><i class="fas fa-check-circle"></i> Assign doctor after registration</span>
                        <span class="toggle-sub">(Bill will be created with fee)</span>
                        <span class="fee-info">💰 TSh <span id="toggleFeeAmount"><?= number_format($default_price, 0) ?></span></span>
                    </div>

                    <div id="feeInfoBox" style="background:#FEF3C7;border:2px solid #D97706;border-radius:10px;padding:10px 14px;margin-top:10px;display:none;align-items:center;gap:10px;font-size:0.75rem;color:#D97706;">
                        <i class="fas fa-info-circle" style="font-size:1rem;"></i>
                        <span>
                            <strong>Fee: TSh <span id="feeAmountDisplay"><?= number_format($default_price, 0) ?></span></strong>
                            — This fee will be sent to Cashier when doctor is assigned.
                        </span>
                    </div>

                    <div class="assign-doctor-fields <?= (isset($_POST['assign_doctor']) && $_POST['assign_doctor'] == 1) ? 'show' : '' ?>" id="assignDoctorFields">
                        <div class="grid-2">
                            <div class="form-row" style="margin-bottom:0;">
                                <label class="form-label">
                                    <i class="fas fa-user-md label-icon"></i> Select Doctor
                                    <span style="font-size:0.6rem;font-weight:400;color:var(--page-text-secondary);">(<?= $online_doctors_count ?> online, <?= $offline_doctors_count ?> offline)</span>
                                </label>
                                <select name="doctor_id" class="form-control" id="doctorSelect">
                                    <option value="">-- Select Doctor --</option>
                                    <?php if (!empty($online_doctors)): ?>
                                        <optgroup label="🟢 Online Doctors (<?= $online_doctors_count ?>)" style="font-weight:600;color:#059669;">
                                            <?php foreach ($online_doctors as $doctor): ?>
                                                <option value="<?= $doctor['id'] ?>" data-online="1" style="font-weight:500;color:#059669;">
                                                    🟢 Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                                                    <?php if (!empty($doctor['specialty'])): ?>(<?= htmlspecialchars($doctor['specialty']) ?>)<?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                    <?php if (!empty($offline_doctors)): ?>
                                        <optgroup label="⚪ Offline Doctors (<?= $offline_doctors_count ?>)" style="font-weight:600;color:var(--page-text-secondary);">
                                            <?php foreach ($offline_doctors as $doctor): ?>
                                                <option value="<?= $doctor['id'] ?>" data-online="0" style="color:var(--page-text-secondary);">
                                                    ⚪ Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                </select>
                                <div style="margin-top:4px;font-size:0.6rem;color:var(--page-text-secondary);">
                                    <span style="color:#059669;" id="onlineCountDisplay">🟢 <?= $online_doctors_count ?> online</span>
                                    <span style="margin:0 4px;">|</span>
                                    <span id="offlineCountDisplay">⚪ <?= $offline_doctors_count ?> offline</span>
                                </div>
                            </div>

                            <div class="form-row" style="margin-bottom:0;">
                                <label class="form-label">
                                    <i class="fas fa-money-bill-wave label-icon"></i> Consultation Fee
                                </label>
                                <div style="padding:10px 14px;background:var(--page-bg-body);border-radius:10px;border:2px solid var(--page-border);">
                                    <span style="font-size:1rem;font-weight:700;color:#0B5ED7;">TSh <span id="feeDisplay"><?= number_format($default_price, 0) ?></span></span>
                                </div>
                            </div>
                        </div>

                        <div class="grid-2" style="margin-top:14px;">
                            <div class="form-row" style="margin-bottom:0;">
                                <label class="form-label"><i class="fas fa-notes-medical label-icon"></i> Symptoms</label>
                                <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:4px;">
                                    <?php foreach ($common_symptoms as $symptom): ?>
                                        <span style="display:inline-flex;align-items:center;gap:3px;padding:2px 10px 2px 6px;border-radius:16px;border:2px solid var(--page-border);background:var(--page-bg-body);cursor:pointer;font-size:0.65rem;color:var(--page-text-secondary);user-select:none;"
                                              class="symptom-chip" data-symptom="<?= htmlspecialchars($symptom) ?>" onclick="toggleSymptom(this)">
                                            <span>🩺</span>
                                            <?= htmlspecialchars($symptom) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <textarea name="symptoms" class="form-control" placeholder="Patient symptoms..." rows="2" id="symptomsTextarea" style="font-size:0.7rem;margin-top:8px;"><?= htmlspecialchars($_POST['symptoms'] ?? '') ?></textarea>
                            </div>
                            <div class="form-row" style="margin-bottom:0;">
                                <label class="form-label"><i class="fas fa-comment-medical label-icon"></i> Complaint / Reason</label>
                                <textarea name="complaint" class="form-control" placeholder="Main complaint..." rows="2" style="font-size:0.7rem;"><?= htmlspecialchars($_POST['complaint'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="form-row grid-full" style="margin-top:10px;margin-bottom:0;">
                            <label class="form-label"><i class="fas fa-sticky-note label-icon"></i> Additional Notes</label>
                            <textarea name="notes" class="form-control" placeholder="Any additional notes..." rows="1" style="font-size:0.7rem;"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="registerBtn">
                    <i class="fas fa-save"></i> Register Patient
                </button>
                <button type="reset" class="btn btn-outline" id="resetFormBtn">
                    <i class="fas fa-undo"></i> Reset Form
                </button>
                <a href="patients.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

</main>

<!-- Toast -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    var selectedBranchId = <?= (int)$target_branch_id ?>;

    // ================================================================
    // DARK MODE BACKGROUND ENFORCEMENT
    // ================================================================
    function enforceDarkModeBackground() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var body = document.body;
        var mainContent = document.querySelector('.main-content');

        if (isDark) {
            if (body) body.style.background = '#0F172A';
            if (mainContent) mainContent.style.background = '#0F172A';
        } else {
            if (body) body.style.background = '#F1F5F9';
            if (mainContent) mainContent.style.background = '#F1F5F9';
        }
    }

    enforceDarkModeBackground();
    document.addEventListener('darkModeChanged', function() { setTimeout(enforceDarkModeBackground, 50); });
    new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            if (m.attributeName === 'data-theme') enforceDarkModeBackground();
        });
    }).observe(document.documentElement, { attributes: true });

    // ================================================================
    // AUTOCOMPLETE
    // ================================================================
    var autocompleteTimeout = null;
    var currentAutocompleteIndex = -1;

    function searchPatients(query) {
        var dropdown = document.getElementById('autocompleteDropdown');
        var results = document.getElementById('autocompleteResults');
        var loading = document.getElementById('autocompleteLoading');

        if (!dropdown || !results) return;
        if (query.length < 1) { dropdown.classList.remove('show'); results.innerHTML = ''; return; }

        if (autocompleteTimeout) clearTimeout(autocompleteTimeout);

        autocompleteTimeout = setTimeout(function() {
            if (loading) loading.style.display = 'block';
            results.innerHTML = '';
            dropdown.classList.add('show');

            fetch('add_patient.php?action=search_patients&q=' + encodeURIComponent(query) + '&branch=' + selectedBranchId)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (loading) loading.style.display = 'none';

                    if (data.success && data.patients && data.patients.length > 0) {
                        var html = '';
                        data.patients.forEach(function(p) {
                            var initial = (p.full_name || 'U').charAt(0).toUpperCase();
                            var activeVisit = p.active_visits > 0;
                            var visitBadge = activeVisit 
                                ? '<span style="background:#FEF3C7;color:#D97706;padding:0 6px;border-radius:8px;font-size:0.5rem;font-weight:600;">🔄 Active</span>' 
                                : '<span style="background:#E2E8F0;color:#64748B;padding:0 6px;border-radius:8px;font-size:0.5rem;font-weight:600;">📋 No Visit</span>';

                            html += '<div class="autocomplete-item" onclick="selectPatient(' + p.id + ', \'' + escapeHtml(p.full_name) + '\', \'' + escapeHtml(p.phone || '') + '\', \'' + escapeHtml(p.gender || '') + '\', \'' + escapeHtml(p.date_of_birth || '') + '\', \'' + escapeHtml(p.address || '') + '\', \'' + escapeHtml(p.blood_group || '') + '\', \'' + escapeHtml(p.allergies || '') + '\', \'' + escapeHtml(p.emergency_contact || '') + '\', \'' + escapeHtml(p.marital_status || '') + '\')">' +
                                '<div class="patient-avatar">' + initial + '</div>' +
                                '<div class="patient-info">' +
                                    '<span class="patient-name">' + escapeHtml(p.full_name) + '</span>' +
                                    '<div class="patient-meta">' +
                                        '<span><i class="fas fa-id-card"></i> ' + escapeHtml(p.patient_id || 'N/A') + '</span>' +
                                        (p.phone ? '<span><i class="fas fa-phone"></i> ' + escapeHtml(p.phone) + '</span>' : '') +
                                        (p.gender ? '<span><i class="fas fa-venus-mars"></i> ' + escapeHtml(p.gender) + '</span>' : '') +
                                        visitBadge +
                                    '</div>' +
                                '</div>' +
                            '</div>';
                        });
                        results.innerHTML = html;
                        currentAutocompleteIndex = -1;
                    } else {
                        results.innerHTML = '<div class="autocomplete-empty"><i class="fas fa-user-plus" style="font-size:1.2rem;display:block;margin-bottom:4px;color:#0B5ED7;"></i><p style="font-size:0.75rem;">No existing patient found</p><p style="font-size:0.6rem;color:#64748B;">Continue typing to register new patient</p></div>';
                    }
                })
                .catch(function() { if (loading) loading.style.display = 'none'; });
        }, 250);
    }

    function selectPatient(id, name, phone, gender, dob, address, bloodGroup, allergies, emergencyContact, maritalStatus) {
        document.getElementById('fullNameInput').value = name;

        var phoneInput = document.querySelector('input[name="phone"]');
        if (phoneInput && !phoneInput.value) phoneInput.value = phone;

        var genderSelect = document.querySelector('select[name="gender"]');
        if (genderSelect && gender) {
            for (var i = 0; i < genderSelect.options.length; i++) {
                if (genderSelect.options[i].value === gender) { genderSelect.selectedIndex = i; break; }
            }
        }

        var dobInput = document.querySelector('input[name="date_of_birth"]');
        if (dobInput && dob) dobInput.value = dob;

        var addressInput = document.querySelector('textarea[name="address"]');
        if (addressInput && address) addressInput.value = address;

        var bloodSelect = document.querySelector('select[name="blood_group"]');
        if (bloodSelect && bloodGroup) {
            for (var i = 0; i < bloodSelect.options.length; i++) {
                if (bloodSelect.options[i].value === bloodGroup) { bloodSelect.selectedIndex = i; break; }
            }
        }

        var allergiesTextarea = document.getElementById('allergiesTextarea');
        if (allergiesTextarea && allergies) { allergiesTextarea.value = allergies; syncAllergyChips(); }

        var emergencyInput = document.querySelector('input[name="emergency_contact"]');
        if (emergencyInput && emergencyContact) emergencyInput.value = emergencyContact;

        var maritalSelect = document.querySelector('select[name="marital_status"]');
        if (maritalSelect && maritalStatus) {
            for (var i = 0; i < maritalSelect.options.length; i++) {
                if (maritalSelect.options[i].value === maritalStatus) { maritalSelect.selectedIndex = i; break; }
            }
        }

        hideAutocomplete();
        showToast('✅ Patient Selected', 'Details auto-filled. You can modify any field.', 'success');
    }

    function hideAutocomplete() {
        var dropdown = document.getElementById('autocompleteDropdown');
        if (dropdown) dropdown.classList.remove('show');
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML.replace(/'/g, "\\'");
    }

    document.getElementById('fullNameInput')?.addEventListener('keydown', function(e) {
        var dropdown = document.getElementById('autocompleteDropdown');
        var items = dropdown.querySelectorAll('.autocomplete-item');
        if (!dropdown.classList.contains('show')) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            currentAutocompleteIndex = Math.min(currentAutocompleteIndex + 1, items.length - 1);
            items.forEach(function(item, i) { item.classList.toggle('active', i === currentAutocompleteIndex); });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            currentAutocompleteIndex = Math.max(currentAutocompleteIndex - 1, -1);
            items.forEach(function(item, i) { item.classList.toggle('active', i === currentAutocompleteIndex); });
        } else if (e.key === 'Enter' && currentAutocompleteIndex >= 0) {
            e.preventDefault();
            items[currentAutocompleteIndex].click();
        } else if (e.key === 'Escape') {
            hideAutocomplete();
        }
    });

    // ================================================================
    // BMI
    // ================================================================
    function calculateBMI() {
        var weightInput = document.getElementById('weightInput');
        var heightInput = document.getElementById('heightInput');
        var bmiOutput = document.getElementById('bmiOutput');
        var bmiCategory = document.getElementById('bmiCategory');
        if (!weightInput || !heightInput || !bmiOutput || !bmiCategory) return;

        var weight = parseFloat(weightInput.value);
        var height = parseFloat(heightInput.value);

        if (weight && height && height > 0) {
            var heightM = height / 100;
            var bmi = Math.round((weight / (heightM * heightM)) * 10) / 10;
            bmiOutput.value = bmi;

            var category = '', categoryClass = '';
            if (bmi < 18.5) { category = 'Underweight'; categoryClass = 'underweight'; }
            else if (bmi < 25) { category = 'Normal'; categoryClass = 'normal'; }
            else if (bmi < 30) { category = 'Overweight'; categoryClass = 'overweight'; }
            else { category = 'Obese'; categoryClass = 'obese'; }

            bmiCategory.textContent = category;
            bmiCategory.className = 'vital-bmi-category ' + categoryClass;
        } else {
            bmiOutput.value = '';
            bmiCategory.textContent = 'Auto';
            bmiCategory.className = 'vital-bmi-category';
        }
    }

    // SpO2 Category
    function calculateSpO2Category() {
        var spo2Input = document.getElementById('spo2Input');
        var spo2Category = document.getElementById('spo2Category');
        if (!spo2Input || !spo2Category) return;

        var spo2 = parseFloat(spo2Input.value);

        if (!spo2 || spo2 <= 0) {
            spo2Category.textContent = 'Auto';
            spo2Category.className = 'vital-bmi-category';
            return;
        }

        var category = '', categoryClass = '';
        if (spo2 >= 95) { category = 'Normal'; categoryClass = 'spo2-normal'; }
        else if (spo2 >= 90) { category = 'Low'; categoryClass = 'spo2-low'; }
        else { category = 'Critical'; categoryClass = 'spo2-critical'; }

        spo2Category.textContent = category;
        spo2Category.className = 'vital-bmi-category ' + categoryClass;
    }

    // ================================================================
    // ALLERGIES
    // ================================================================
    var allergyChips = document.querySelectorAll('.allergy-chip');
    var allergiesTextarea = document.getElementById('allergiesTextarea');

    function syncAllergyChips() {
        if (!allergiesTextarea) return;
        var allergyList = allergiesTextarea.value.split(',').map(function(s) { return s.trim(); }).filter(function(s) { return s !== ''; });
        allergyChips.forEach(function(chip) {
            var name = chip.dataset.allergy;
            var cb = chip.querySelector('.allergy-checkbox');
            if (allergyList.includes(name)) { chip.classList.add('active'); if (cb) cb.checked = true; }
            else { chip.classList.remove('active'); if (cb) cb.checked = false; }
        });
    }

    allergyChips.forEach(function(chip) {
        chip.addEventListener('click', function(e) {
            e.preventDefault();
            var cb = this.querySelector('.allergy-checkbox');
            var name = this.dataset.allergy;
            this.classList.toggle('active');
            if (cb) cb.checked = !cb.checked;

            var list = allergiesTextarea.value.split(',').map(function(s) { return s.trim(); }).filter(function(s) { return s !== ''; });
            if (cb && cb.checked) { if (!list.includes(name)) list.push(name); }
            else { list = list.filter(function(item) { return item !== name; }); }
            allergiesTextarea.value = list.join(', ');
        });
    });

    allergiesTextarea?.addEventListener('input', syncAllergyChips);
    syncAllergyChips();

    // ================================================================
    // SYMPTOMS
    // ================================================================
    function toggleSymptom(el) {
        var symptom = el.dataset.symptom;
        var textarea = document.getElementById('symptomsTextarea');
        if (!textarea) return;

        var list = textarea.value.split(',').map(function(s) { return s.trim(); }).filter(function(s) { return s !== ''; });
        el.classList.toggle('active');

        if (el.classList.contains('active')) {
            el.style.borderColor = '#0B5ED7';
            el.style.background = '#E8F0FE';
            el.style.color = '#0B5ED7';
            if (!list.includes(symptom)) list.push(symptom);
        } else {
            el.style.borderColor = '';
            el.style.background = '';
            el.style.color = '';
            list = list.filter(function(item) { return item !== symptom; });
        }

        textarea.value = list.join(', ');
    }

    // ================================================================
    // FEE DISPLAY
    // ================================================================
    function updateFeeDisplay() {
        var select = document.getElementById('visitTypeSelect');
        if (!select) return;
        var opt = select.options[select.selectedIndex];
        var price = opt.dataset.price || 0;
        var name = opt.dataset.serviceName || 'Consultation';
        var formatted = parseInt(price).toLocaleString();

        ['headerFeeDisplay', 'feeDisplay', 'toggleFeeAmount', 'feeAmountDisplay'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.textContent = formatted;
        });

        var preview = document.getElementById('visitTypePreview');
        if (preview) preview.textContent = name;

        var headerType = document.getElementById('headerFeeType');
        if (headerType) headerType.textContent = name;
    }

    // ================================================================
    // ASSIGN DOCTOR TOGGLE
    // ================================================================
    function toggleAssignDoctor(event) {
        var cb = document.getElementById('assignDoctorCheckbox');
        var fields = document.getElementById('assignDoctorFields');
        var feeBox = document.getElementById('feeInfoBox');

        if (event && event.target && event.target.tagName !== 'INPUT') cb.checked = !cb.checked;

        if (cb && cb.checked) {
            if (fields) fields.classList.add('show');
            if (feeBox) feeBox.style.display = 'flex';
        } else {
            if (fields) fields.classList.remove('show');
            if (feeBox) feeBox.style.display = 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var cb = document.getElementById('assignDoctorCheckbox');
        var feeBox = document.getElementById('feeInfoBox');
        if (cb && !cb.checked && feeBox) feeBox.style.display = 'none';
        else if (cb && cb.checked && feeBox) feeBox.style.display = 'flex';
        updateFeeDisplay();

        var spo2Input = document.getElementById('spo2Input');
        if (spo2Input) {
            spo2Input.addEventListener('input', calculateSpO2Category);
            spo2Input.addEventListener('change', calculateSpO2Category);
            calculateSpO2Category();
        }
    });

    // ================================================================
    // DOCTOR AUTO-UPDATE
    // ================================================================
    var isUpdatingDoctors = false;
    function fetchDoctorStatus() {
        if (isUpdatingDoctors) return;
        isUpdatingDoctors = true;

        var formData = new FormData();
        formData.append('action', 'get_doctor_status');
        formData.append('branch_id', selectedBranchId);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var onlineCount = document.getElementById('onlineCountDisplay');
                    var offlineCount = document.getElementById('offlineCountDisplay');
                    var status = document.getElementById('doctorUpdateStatus');
                    if (onlineCount) onlineCount.textContent = '🟢 ' + data.online_count + ' online';
                    if (offlineCount) offlineCount.textContent = '⚪ ' + data.offline_count + ' offline';
                    if (status) status.textContent = 'Live ' + data.timestamp;

                    var sel = document.getElementById('doctorSelect');
                    if (sel && data.doctor_options) {
                        var current = sel.value;
                        sel.innerHTML = data.doctor_options;
                        if (current) sel.value = current;
                    }
                }
                isUpdatingDoctors = false;
            })
            .catch(function() { isUpdatingDoctors = false; });
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
        }, 3500);
    }

    // ================================================================
    // FORM VALIDATION
    // ================================================================
    document.getElementById('registrationForm')?.addEventListener('submit', function(e) {
        var name = document.querySelector('input[name="full_name"]').value.trim();
        var gender = document.querySelector('select[name="gender"]').value;
        var assignDoctor = document.getElementById('assignDoctorCheckbox').checked;

        if (!name) { e.preventDefault(); showToast('Error', 'Please enter patient full name', 'error'); return false; }
        if (!gender) { e.preventDefault(); showToast('Error', 'Please select gender', 'error'); return false; }

        if (assignDoctor) {
            var docSel = document.getElementById('doctorSelect');
            if (!docSel.value) {
                e.preventDefault();
                showToast('Error', 'Please select a doctor', 'error');
                docSel.focus();
                return false;
            }
        }

        var btn = document.getElementById('registerBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...';
    });

    // ================================================================
    // RESET
    // ================================================================
    document.getElementById('resetFormBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('registrationForm').reset();

        if (allergiesTextarea) allergiesTextarea.value = '';
        allergyChips.forEach(function(chip) {
            chip.classList.remove('active');
            var cb = chip.querySelector('.allergy-checkbox');
            if (cb) cb.checked = false;
        });

        document.querySelectorAll('.symptom-chip').forEach(function(chip) {
            chip.classList.remove('active');
            chip.style.borderColor = '';
            chip.style.background = '';
            chip.style.color = '';
        });

        var st = document.getElementById('symptomsTextarea');
        if (st) st.value = '';

        var bmiOut = document.getElementById('bmiOutput');
        var bmiCat = document.getElementById('bmiCategory');
        if (bmiOut) bmiOut.value = '';
        if (bmiCat) { bmiCat.textContent = 'Auto'; bmiCat.className = 'vital-bmi-category'; }

        var spo2In = document.getElementById('spo2Input');
        var spo2Cat = document.getElementById('spo2Category');
        if (spo2In) spo2In.value = '';
        if (spo2Cat) { spo2Cat.textContent = 'Auto'; spo2Cat.className = 'vital-bmi-category'; }

        var cb = document.getElementById('assignDoctorCheckbox');
        var fields = document.getElementById('assignDoctorFields');
        var feeBox = document.getElementById('feeInfoBox');
        if (cb) cb.checked = false;
        if (fields) fields.classList.remove('show');
        if (feeBox) feeBox.style.display = 'none';

        updateFeeDisplay();
        hideAutocomplete();
        showToast('🔄 Reset', 'Form has been reset', 'info');
    });

    // ================================================================
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        calculateBMI();
        calculateSpO2Category();
        updateFeeDisplay();
        setTimeout(fetchDoctorStatus, 2000);
        setInterval(fetchDoctorStatus, 3000);

        document.getElementById('visitTypeSelect')?.addEventListener('change', updateFeeDisplay);

        document.addEventListener('click', function(e) {
            var wrapper = document.querySelector('.autocomplete-wrapper');
            if (wrapper && !wrapper.contains(e.target)) hideAutocomplete();
        });

        console.log('%c👑 Braick - Admin Add Patient', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
        console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
        console.log('%c🎨 Beautiful blue page details card', 'font-size:13px; color:#0B5ED7;');
        console.log('%c🌙 FULL DARK MODE inafanya kazi', 'font-size:13px; color:#7C3AED;');
    });
</script>

</body>
</html>