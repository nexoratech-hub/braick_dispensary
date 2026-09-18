<?php
// ================================================================
// FILE: frontend/pages/reception/new_patient.php
// RECEPTION - REGISTER NEW PATIENT (V11 - FIXED assigned_by)
// ✅ assigned_by_id inahifadhiwa kwa database
// ✅ VITAL SIGNS: 7 CARDS (3+4 with SpO2)
// ✅ AUTOCOMPLETE patient names
// ✅ SAVES created_by, registered_by, registered_by_name
// ================================================================

session_start();

// ================================================================
// CHECK SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reception') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// GET SESSION DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Receptionist';
$user_role = $_SESSION['role'] ?? 'reception';
$branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? 'reception';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$full_name = $user_full_name;
$user_branch_id = $branch_id;
$selected_branch_id = $branch_id;
$message = '';
$message_type = '';

// Initialize variables
$doctors = [];
$online_doctors = [];
$offline_doctors = [];
$online_doctors_count = 0;
$offline_doctors_count = 0;
$total_doctors = 0;

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $unread_notifications = 0;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $unread_notifications = 0;
    }

    // ================================================================
    // AJAX: DOCTOR STATUS
    // ================================================================
    if (isset($_POST['action']) && $_POST['action'] === 'get_doctor_status') {
        header('Content-Type: application/json');
        $branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : $selected_branch_id;
        
        $stmt = $db->prepare("
            SELECT id, full_name, specialty, is_online 
            FROM users 
            WHERE role = 'doctor' AND status = 'active' AND branch_id = ?
            ORDER BY is_online DESC, full_name
        ");
        $stmt->execute([$branch_id]);
        $doctors_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $online = [];
        $offline = [];
        $online_count = 0;
        $offline_count = 0;
        
        foreach ($doctors_list as $doc) {
            if ($doc['is_online'] == 1) {
                $online[] = $doc;
                $online_count++;
            } else {
                $offline[] = $doc;
                $offline_count++;
            }
        }
        
        $options_html = '<option value="">-- Select Doctor --</option>';
        
        if ($online_count > 0) {
            $options_html .= '<optgroup label="🟢 Online Doctors (' . $online_count . ')" style="font-weight:600;color:#059669;">';
            foreach ($online as $doc) {
                $options_html .= '<option value="' . $doc['id'] . '" data-online="1" style="font-weight:500;color:#059669;padding:4px;">';
                $options_html .= '🟢 Dr. ' . htmlspecialchars($doc['full_name']);
                if (!empty($doc['specialty'])) $options_html .= ' (' . htmlspecialchars($doc['specialty']) . ')';
                $options_html .= '</option>';
            }
            $options_html .= '</optgroup>';
        }
        
        if ($offline_count > 0) {
            $options_html .= '<optgroup label="⚪ Offline Doctors (' . $offline_count . ')" style="font-weight:600;color:var(--text-secondary);">';
            foreach ($offline as $doc) {
                $options_html .= '<option value="' . $doc['id'] . '" data-online="0" style="color:var(--text-secondary);padding:4px;">';
                $options_html .= '⚪ Dr. ' . htmlspecialchars($doc['full_name']);
                if (!empty($doc['specialty'])) $options_html .= ' (' . htmlspecialchars($doc['specialty']) . ')';
                $options_html .= '</option>';
            }
            $options_html .= '</optgroup>';
        }
        
        if (empty($doctors_list)) {
            $options_html .= '<option value="" disabled>No doctors available</option>';
        }
        
        echo json_encode([
            'success' => true,
            'online_count' => $online_count,
            'offline_count' => $offline_count,
            'total_doctors' => count($doctors_list),
            'doctor_options' => $options_html,
            'timestamp' => date('H:i:s')
        ]);
        exit;
    }

    // ================================================================
    // AJAX: AUTOCOMPLETE PATIENT NAMES
    // ================================================================
    if (isset($_GET['action']) && $_GET['action'] === 'search_patients') {
        header('Content-Type: application/json');
        $query = isset($_GET['q']) ? trim($_GET['q']) : '';
        $branch = isset($_GET['branch']) ? (int)$_GET['branch'] : $selected_branch_id;
        
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
                ORDER BY p.full_name ASC
                LIMIT 10
            ");
            $stmt->execute([$branch, "%$query%"]);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'patients' => $patients, 'count' => count($patients)]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage(), 'patients' => []]);
        }
        exit;
    }

    // ================================================================
    // GET DOCTORS
    // ================================================================
    $stmt = $db->prepare("
        SELECT id, full_name, specialty, is_online 
        FROM users 
        WHERE role = 'doctor' AND status = 'active' AND branch_id = ?
        ORDER BY is_online DESC, full_name
    ");
    $stmt->execute([$selected_branch_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $online_doctors = [];
    $offline_doctors = [];
    $online_doctors_count = 0;
    $offline_doctors_count = 0;
    
    foreach ($doctors as $doc) {
        if ($doc['is_online'] == 1) {
            $online_doctors[] = $doc;
            $online_doctors_count++;
        } else {
            $offline_doctors[] = $doc;
            $offline_doctors_count++;
        }
    }
    $total_doctors = count($doctors);
    
    // ================================================================
    // GET CONSULTATION SERVICES
    // ================================================================
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
                END,
                service_name
        ");
        $stmt->execute([$selected_branch_id]);
        $consultation_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $consultation_services = [];
    }
    
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
                'service_id' => $service_id,
                'service_name' => $service_name,
                'display_name' => $service_name,
                'price' => $price,
                'unit' => $service['unit'] ?? 'each',
                'description' => $service['description'] ?? '',
                'is_active' => $service['is_active'] ?? 1,
                'icon' => $icon
            ];
            
            if (strpos(strtolower($service_name), 'new patient') !== false) {
                $default_service_id = $service_id;
                $default_price = $price;
                $default_service_name = $service_name;
                $default_key = $key;
            } elseif ($default_key === null) {
                $default_service_id = $service_id;
                $default_price = $price;
                $default_service_name = $service_name;
                $default_key = $key;
            }
        }
    }
    
    if (empty($visit_type_options)) {
        $visit_type_options = [
            1 => [
                'service_id' => null,
                'service_name' => 'New Patient',
                'display_name' => 'New Patient',
                'price' => 10000,
                'unit' => 'each',
                'description' => 'New patient consultation',
                'is_active' => 1,
                'icon' => '🆕'
            ]
        ];
        $default_key = 1;
        $default_price = 10000;
        $default_service_name = 'New Patient';
    }
    
    // ================================================================
    // GENERATE PATIENT ID
    // ================================================================
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
        } else {
            $new_number = '0001';
        }
        
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

    $patient_id_number = generateUniquePatientId($db, $selected_branch_id);
    
    // ================================================================
    // COMMON LISTS
    // ================================================================
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
    
    // ================================================================
    // HANDLE FORM SUBMISSION
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_patient'])) {
        // ✅ Patient data
        $patient_full_name = trim($_POST['full_name'] ?? '');
        $date_of_birth = $_POST['date_of_birth'] ?? null;
        $gender = $_POST['gender'] ?? null;
        $marital_status = $_POST['marital_status'] ?? null;
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $emergency_contact = trim($_POST['emergency_contact'] ?? '');
        $blood_group = $_POST['blood_group'] ?? null;
        $allergies = trim($_POST['allergies'] ?? '');
        $branch_id = $selected_branch_id;
        
        // ✅ registration info
        $registered_by_id = $user_id;
        $registered_by_name = $user_full_name;
        
        $assign_doctor = isset($_POST['assign_doctor']) ? 1 : 0;
        $doctor_id = (int)($_POST['doctor_id'] ?? 0);
        $service_id = isset($_POST['visit_type']) ? (int)$_POST['visit_type'] : 0;
        
        // Service details
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
        
        // Vital Signs
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
        if (empty($patient_full_name)) $errors[] = 'Full name is required';
        if (empty($gender)) $errors[] = 'Gender is required';
        
        // Exact duplicate check only
        $duplicate_error = '';
        if (empty($errors) && !empty($patient_full_name) && !empty($phone)) {
            $stmt = $db->prepare("SELECT id, full_name, patient_id FROM patients WHERE full_name = ? AND phone = ? AND branch_id = ? AND date_of_birth = ?");
            $stmt->execute([$patient_full_name, $phone, $branch_id, $date_of_birth]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $duplicate_error = "❌ A patient with the same name, phone, and date of birth already exists in <strong>" . htmlspecialchars($branch_name) . "</strong> branch.<br>
                                   👤 Name: <strong>" . htmlspecialchars($existing['full_name']) . "</strong><br>
                                   🆔 ID: <strong>" . htmlspecialchars($existing['patient_id']) . "</strong>";
            }
        }
        
        if (!empty($duplicate_error)) {
            $message = $duplicate_error;
            $message_type = 'error';
        } elseif (empty($errors)) {
            try {
                $db->beginTransaction();
                
                $final_patient_id = generateUniquePatientId($db, $branch_id);
                
                // ✅ INSERT PATIENT
                $stmt = $db->prepare("
                    INSERT INTO patients (
                        patient_id, full_name, date_of_birth, gender, phone, email, 
                        address, emergency_contact, blood_group, allergies, branch_id, 
                        created_by, marital_status, registered_by, registered_by_name,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $final_patient_id, 
                    $patient_full_name, 
                    $date_of_birth, 
                    $gender, 
                    $phone, 
                    $email,
                    $address, 
                    $emergency_contact, 
                    $blood_group, 
                    $allergies, 
                    $branch_id,
                    $registered_by_id,
                    $marital_status,
                    $registered_by_id,
                    $registered_by_name
                ]);
                $patient_db_id = $db->lastInsertId();
                
                // CREATE VISIT
                $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad($patient_db_id, 4, '0', STR_PAD_LEFT);
                $visit_status = 'pending';
                if ($assign_doctor && $doctor_id > 0) $visit_status = 'assigned';
                
                $consultation_fee_to_store = ($assign_doctor && $doctor_id > 0) ? $consultation_fee : 0;
                
                // ✅ FIXED: Save assigned_by_id and assigned_at if doctor is assigned
                $assigned_by_id_to_store = ($assign_doctor && $doctor_id > 0) ? $user_id : null;
                $assigned_at_to_store = ($assign_doctor && $doctor_id > 0) ? date('Y-m-d H:i:s') : null;
                
                $stmt = $db->prepare("
                    INSERT INTO visits (
                        visit_number, visit_date, patient_id, receptionist_id, branch_id, 
                        visit_type, service_id, consultation_fee, status, doctor_id,
                        symptoms, complaint, notes, 
                        assigned_by_id, assigned_at,
                        created_at, updated_at
                    ) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $visit_number, $patient_db_id, $user_id, $branch_id,
                    $visit_type_name, $service_id_to_store, $consultation_fee_to_store,
                    $visit_status, $assign_doctor && $doctor_id > 0 ? $doctor_id : null,
                    $symptoms, $complaint, $notes,
                    $assigned_by_id_to_store, $assigned_at_to_store
                ]);
                $visit_id = $db->lastInsertId();
                
                // SAVE VITAL SIGNS (with SpO2)
                if ($visit_id && ($temperature || $bp_systolic || $bp_diastolic || $pulse_rate || $weight || $height || $oxygen_saturation)) {
                    $stmt = $db->prepare("
                        INSERT INTO vital_signs (
                            patient_id, visit_id, recorded_by, branch_id,
                            temperature, blood_pressure_systolic, blood_pressure_diastolic,
                            pulse_rate, weight, height, bmi, oxygen_saturation, notes, recorded_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $patient_db_id, $visit_id, $user_id, $branch_id,
                        $temperature, $bp_systolic, $bp_diastolic,
                        $pulse_rate, $weight, $height, $bmi, $oxygen_saturation, $vital_notes ?: null
                    ]);
                }
                
                // CREATE BILL IF DOCTOR ASSIGNED
                $bill_created = false;
                $bill_number = null;
                
                if ($assign_doctor && $doctor_id > 0) {
                    $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = ? WHERE id = ?");
                    $stmt->execute([$doctor_id, $patient_db_id]);
                    
                    // ✅ Update assigned_at AND assigned_by_id (kama doctor ameassign)
                    $stmt = $db->prepare("UPDATE visits SET assigned_at = NOW(), assigned_by_id = ? WHERE id = ?");
                    $stmt->execute([$user_id, $visit_id]);
                    
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
                            $user_id, $branch_id
                        ]);
                        $bill_id = $db->lastInsertId();
                        
                        $stmt = $db->prepare("
                            INSERT INTO bill_items (
                                bill_id, patient_id, branch_id, item_type, item_name,
                                quantity, unit_price, total_price, status, created_at
                            ) VALUES (?, ?, ?, 'consultation', ?, 1, ?, ?, 'pending', NOW())
                        ");
                        $stmt->execute([
                            $bill_id, $patient_db_id, $branch_id,
                            $visit_type_name, $consultation_fee, $consultation_fee
                        ]);
                        
                        $stmt = $db->prepare("UPDATE visits SET visit_total = visit_total + ?, consultation_fee = ? WHERE id = ?");
                        $stmt->execute([$consultation_fee, $consultation_fee, $visit_id]);
                        
                        $bill_created = true;
                        
                        try {
                            $stmt = $db->prepare("SELECT id FROM users WHERE role = 'cashier' AND status = 'active' AND branch_id = ?");
                            $stmt->execute([$branch_id]);
                            $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($cashiers as $cashier) {
                                $stmt = $db->prepare("
                                    INSERT INTO notifications (user_id, branch_id, title, message, type, link, is_read, created_at)
                                    VALUES (?, ?, '💰 New Bill Created', ?, 'bill', 'cashier_dashboard.php', 0, NOW())
                                ");
                                $stmt->execute([
                                    $cashier['id'], $branch_id,
                                    "Consultation bill #$bill_number (TSh " . number_format($consultation_fee) . ") for patient " . htmlspecialchars($patient_full_name) . " - " . $visit_type_name
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
                $message .= "<br>👤 Registered By: <strong>" . htmlspecialchars($registered_by_name) . "</strong>";
                
                if ($assign_doctor && $doctor_id > 0) {
                    $doctor_name = '';
                    foreach ($doctors as $doc) {
                        if ($doc['id'] == $doctor_id) {
                            $doctor_name = $doc['full_name'];
                            break;
                        }
                    }
                    $message .= "<br>👨‍⚕️ Doctor: <strong>Dr. " . htmlspecialchars($doctor_name) . "</strong>";
                    $message .= "<br>✍️ Assigned By: <strong>" . htmlspecialchars($registered_by_name) . "</strong> (saved to database)";
                    if ($bill_created && $bill_number) {
                        $message .= "<br>💰 Bill: <strong>#$bill_number</strong> sent to Cashier!";
                    }
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
                        window.location.href = "patients.php?registered=1"; 
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

// ================================================================
// LOGO & PROFILE URLS
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$dark_mode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] : 'false';

// ================================================================
// INCLUDE SHARED SIDEBAR ONLY (NOT HEADER)
// ================================================================
include_once __DIR__ . '/../../components/reception_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $dark_mode === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register New Patient - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ... CSS yote kama ilivyo kwenye original ... */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-dark: #B91C1C;
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
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
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
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --primary-bg: #1E3A5F;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3D2E0A;
            --purple-bg: #2A1A3A;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        .top-nav {
            position: fixed;
            top: 0; left: 270px; right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: #0B5ED7;
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: #0B5ED7;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.85rem;
        }
        
        .top-nav .branch-badge {
            background: var(--success-bg);
            color: var(--success);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .top-nav .datetime {
            font-size: 0.75rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .top-nav .avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
        }
        
        .top-nav .icon-btn {
            width: 38px; height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .notif-dot {
            position: absolute;
            top: 6px; right: 6px;
            width: 8px; height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
        }
        
        .notif-dot.has-notif { background: #EF4444; }
        .notif-dot.no-notif { background: #94A3B8; }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.8rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: var(--radius-lg);
            padding: 20px 28px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header .page-title {
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
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 4px;
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.72rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            position: relative;
            z-index: 1;
        }
        
        .form-card-modern {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 28px 32px;
            border: 1px solid var(--border-color);
            max-width: 1200px;
            margin: 0 auto;
            box-shadow: var(--shadow-md);
        }
        
        .form-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .form-header .form-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        
        .form-header .form-title {
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        .form-header .form-subtitle {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .form-label {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            display: block;
        }
        
        .form-label .required { color: var(--danger); margin-left: 2px; }
        .form-label .label-icon { margin-right: 4px; color: var(--primary); }
        
        .form-control {
            width: 100%;
            padding: 9px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.8rem;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.08);
        }
        
        .form-row { margin-bottom: 16px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .grid-full { grid-column: 1 / -1; }
        
        .form-actions {
            display: flex;
            gap: 10px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            border: none;
            text-decoration: none;
            min-height: 40px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        /* Vital Cards CSS */
        .vital-signs-section {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 2px solid var(--border-color);
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
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .vital-signs-section .section-title i { color: #DC2626; }
        
        .vital-signs-section .section-badge {
            font-size: 0.6rem;
            padding: 2px 12px;
            border-radius: 12px;
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        .vital-grid-6 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .vital-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .vital-card {
            background: var(--bg-body);
            border-radius: var(--radius);
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            position: relative;
            overflow: hidden;
            min-height: 110px;
            display: flex;
            flex-direction: column;
        }
        
        .vital-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
        }
        
        .vital-card .vital-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }
        
        .vital-card .vital-icon {
            width: 32px; height: 32px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .vital-card .vital-label {
            font-size: 0.62rem;
            font-weight: 700;
            display: block;
            text-transform: uppercase;
        }
        
        .vital-card .vital-sublabel {
            font-size: 0.5rem;
            color: var(--text-secondary);
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
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 700;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
        }
        
        .vital-card .vital-input-wrap input:focus {
            border-color: var(--primary);
        }
        
        .vital-card .vital-unit {
            font-size: 0.55rem;
            color: var(--text-secondary);
            font-weight: 600;
            padding: 2px 6px;
            background: var(--gray-200);
            border-radius: 6px;
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
        .vital-card.bmi input { background: var(--primary-bg); color: var(--primary); font-weight: 800; }
        .vital-card.spo2::before { background: linear-gradient(135deg, #0891B2, #0E7490); }
        .vital-card.spo2 .vital-icon { color: #0891B2; background: rgba(8, 145, 178, 0.1); }
        .vital-card.spo2 input { background: rgba(8, 145, 178, 0.05); color: #0891B2; font-weight: 700; }
        
        .vital-bmi-category {
            font-size: 0.5rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 6px;
            display: inline-block;
            margin-top: 4px;
            background: var(--gray-200);
            color: var(--text-secondary);
        }
        
        .vital-bmi-category.normal { background: rgba(5, 150, 105, 0.15); color: #059669; }
        .vital-bmi-category.underweight { background: rgba(217, 119, 6, 0.15); color: #D97706; }
        .vital-bmi-category.overweight { background: rgba(217, 119, 6, 0.15); color: #D97706; }
        .vital-bmi-category.obese { background: rgba(220, 38, 38, 0.15); color: #DC2626; }
        .vital-bmi-category.spo2-normal { background: rgba(5, 150, 105, 0.15); color: #059669; }
        .vital-bmi-category.spo2-low { background: rgba(217, 119, 6, 0.15); color: #D97706; }
        .vital-bmi-category.spo2-critical { background: rgba(220, 38, 38, 0.3); color: #DC2626; font-weight: 700; }
        
        .assign-doctor-section {
            background: var(--primary-bg);
            border-radius: var(--radius);
            padding: 18px 20px;
            border: 2px solid var(--primary-light);
            margin-top: 16px;
        }
        
        .assign-doctor-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background: var(--bg-card);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            cursor: pointer;
            margin-bottom: 12px;
        }
        
        .assign-doctor-toggle input[type="checkbox"] {
            width: 18px; height: 18px;
            accent-color: var(--primary);
        }
        
        .assign-doctor-toggle .fee-info {
            margin-left: auto;
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--primary);
            background: var(--primary-bg);
            padding: 2px 12px;
            border-radius: 12px;
        }
        
        .assign-doctor-fields { display: none; margin-top: 10px; }
        .assign-doctor-fields.show { display: block; }
        
        .toast-custom {
            position: fixed;
            bottom: 24px; right: 24px;
            padding: 14px 20px;
            border-radius: var(--radius);
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s;
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .form-card-modern { padding: 20px; }
            .vital-grid-4 { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .form-card-modern { padding: 14px; }
            .grid-2 { grid-template-columns: 1fr; }
            .vital-grid-6, .vital-grid-4 { grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .vital-card { padding: 10px; min-height: 95px; }
        }
    </style>
</head>
<body>

<!-- TOP NAV -->
<nav class="top-nav">
    <div style="display:flex;align-items:center;gap:16px;flex:1;">
        <button id="sidebarToggle" style="background:transparent;border:none;color:var(--text-secondary);cursor:pointer;display:none;">
            <i class="fas fa-bars" style="font-size:1.2rem;"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search" style="color:var(--text-secondary);margin-left:12px;"></i>
            <input type="text" id="searchInput" placeholder="Search patients...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search"></i> Search
            </button>
        </div>
    </div>
    
    <div style="display:flex;align-items:center;gap:12px;">
        <span class="branch-badge">
            <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime">
            <i class="fas fa-clock"></i>
            <span id="clockDisplay"><?= date('d M Y • h:i:s A') ?></span>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell" style="font-size:1rem;"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar">
        </a>
    </div>
</nav>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-plus"></i>
                Register New Patient
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                Create a new patient record in <strong><?= htmlspecialchars($branch_name) ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-id-card"></i>
                    Next ID: <strong><?= $patient_id_number ?></strong>
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-money-bill-wave"></i>
                    Fee: <strong>TSh <?= number_format($default_price, 0) ?></strong>
                </span>
                
                <span class="header-badge" style="background:rgba(124,58,237,0.2);">
                    <i class="fas fa-user-tie"></i>
                    Registering as: <strong><?= htmlspecialchars($user_full_name) ?></strong>
                </span>
            </p>
        </div>
        <div>
            <a href="patients.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div style="max-width:1200px;margin:0 auto 16px;padding:14px 18px;border-radius:var(--radius);background:<?= $message_type === 'success' ? 'var(--success-bg)' : 'var(--danger-bg)' ?>;color:<?= $message_type === 'success' ? 'var(--success-dark)' : 'var(--danger-dark)' ?>;border:1px solid <?= $message_type === 'success' ? 'var(--success)' : 'var(--danger)' ?>;display:flex;align-items:flex-start;gap:12px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.1rem;margin-top:2px;"></i>
            <div style="flex:1;"><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- FORM -->
    <div class="form-card-modern">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <div>
                <h3 class="form-title">Patient Registration Form</h3>
                <p class="form-subtitle">
                    Fill in the patient details below
                    <span style="color:var(--purple);font-weight:600;margin-left:6px;">
                        <i class="fas fa-user-tie"></i> Registered by: <?= htmlspecialchars($user_full_name) ?>
                    </span>
                </p>
            </div>
        </div>
        
        <form method="POST" action="" id="registrationForm" autocomplete="off">
            
            <!-- FULL NAME -->
            <div class="form-row grid-full">
                <label class="form-label">
                    <i class="fas fa-user label-icon"></i> Full Name <span class="required">*</span>
                    <span style="font-size:0.6rem;color:var(--text-secondary);font-weight:400;">
                        (Type to search existing patients)
                    </span>
                </label>
                <div style="position:relative;">
                    <input type="text" name="full_name" id="fullNameInput" class="form-control" 
                           placeholder="Start typing patient name..." 
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" 
                           autocomplete="off" required
                           oninput="searchPatients(this.value)"
                           onfocus="searchPatients(this.value)"
                           onblur="setTimeout(function(){ hideAutocomplete(); }, 200)">
                    <div id="autocompleteDropdown" style="position:absolute;top:100%;left:0;right:0;background:var(--bg-card);border:2px solid var(--primary);border-top:none;border-radius:0 0 var(--radius) var(--radius);max-height:280px;overflow-y:auto;z-index:1000;display:none;box-shadow:0 8px 24px rgba(0,0,0,0.15);">
                        <div id="autocompleteLoading" style="padding:16px;text-align:center;display:none;">
                            <i class="fas fa-spinner fa-spin"></i> Searching...
                        </div>
                        <div id="autocompleteResults"></div>
                    </div>
                </div>
            </div>
            
            <!-- VISIT TYPE + GENDER -->
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
            
            <!-- MARITAL + DOB -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label"><i class="fas fa-ring label-icon"></i> Marital Status</label>
                    <select name="marital_status" class="form-control">
                        <option value="">Select Marital Status</option>
                        <?php foreach ($marital_statuses as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($_POST['marital_status'] ?? '') === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <label class="form-label"><i class="fas fa-calendar label-icon"></i> Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                </div>
            </div>
            
            <!-- PHONE + EMAIL -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-phone label-icon"></i> Phone Number
                        <span style="font-size:0.6rem;color:var(--success);">(Optional)</span>
                    </label>
                    <input type="tel" name="phone" class="form-control" placeholder="e.g. 0759 154 160" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-envelope label-icon"></i> Email
                        <span style="font-size:0.6rem;color:var(--success);">(Optional)</span>
                    </label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. john@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>
            
            <!-- BLOOD + EMERGENCY -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label"><i class="fas fa-tint label-icon"></i> Blood Group</label>
                    <select name="blood_group" class="form-control">
                        <option value="">Select Blood Group</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                            <option value="<?= $bg ?>" <?= ($_POST['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <label class="form-label"><i class="fas fa-phone-alt label-icon"></i> Emergency Contact</label>
                    <input type="tel" name="emergency_contact" class="form-control" placeholder="e.g. 0755 123 456" value="<?= htmlspecialchars($_POST['emergency_contact'] ?? '') ?>">
                </div>
            </div>
            
            <!-- ADDRESS -->
            <div class="form-row grid-full">
                <label class="form-label"><i class="fas fa-home label-icon"></i> Address</label>
                <textarea name="address" class="form-control" placeholder="Enter full address..." rows="2"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
            </div>
            
            <!-- ALLERGIES -->
            <div class="form-row grid-full">
                <label class="form-label">
                    <i class="fas fa-allergies label-icon"></i> Allergies
                    <span style="font-size:0.6rem;color:var(--text-secondary);font-weight:400;">(Click to select)</span>
                </label>
                <div id="allergyCheckboxGroup" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
                    <?php foreach ($common_allergies as $key => $label): ?>
                        <label class="allergy-chip" data-allergy="<?= htmlspecialchars($label) ?>" style="display:inline-flex;align-items:center;gap:4px;padding:3px 12px 3px 8px;border-radius:20px;border:2px solid var(--border-color);background:var(--bg-body);cursor:pointer;font-size:0.7rem;color:var(--text-secondary);user-select:none;">
                            <input type="checkbox" value="<?= htmlspecialchars($label) ?>" class="allergy-checkbox" style="display:none;">
                            <span>⚠️</span> <?= htmlspecialchars($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <textarea name="allergies" id="allergiesTextarea" class="form-control" style="margin-top:8px;" placeholder="List any known allergies..." rows="2"><?= htmlspecialchars($_POST['allergies'] ?? '') ?></textarea>
            </div>
            
            <!-- VITAL SIGNS -->
            <div class="vital-signs-section">
                <div class="section-header">
                    <div class="section-title">
                        <i class="fas fa-heartbeat"></i>
                        Vital Signs
                        <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);">(Record patient vitals)</span>
                    </div>
                    <span class="section-badge">
                        <i class="fas fa-info-circle"></i> Optional - 7 Vital Signs
                    </span>
                </div>
                
                <!-- ROW 1: 3 cards -->
                <div class="vital-grid-6">
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
                            <input type="number" name="bp_systolic" placeholder="120" value="<?= htmlspecialchars($_POST['bp_systolic'] ?? '') ?>" style="text-align:center;">
                            <span style="font-weight:700;">/</span>
                            <input type="number" name="bp_diastolic" placeholder="80" value="<?= htmlspecialchars($_POST['bp_diastolic'] ?? '') ?>" style="text-align:center;">
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
                
                <!-- ROW 2: 4 cards -->
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
                                <span class="vital-label">Oxygen Sat.</span>
                                <span class="vital-sublabel">SpO₂ level</span>
                            </div>
                        </div>
                        <div class="vital-input-wrap">
                            <input type="number" name="oxygen_saturation" step="1" min="0" placeholder="98" id="spo2Input" value="<?= htmlspecialchars($_POST['oxygen_saturation'] ?? '') ?>">
                            <span class="vital-unit">%</span>
                        </div>
                        <span class="vital-bmi-category" id="spo2Category">Auto</span>
                    </div>
                </div>
                
                <div style="margin-top:12px;">
                    <input type="text" name="vital_notes" class="form-control" placeholder="Vital signs notes (optional)" value="<?= htmlspecialchars($_POST['vital_notes'] ?? '') ?>" style="font-size:0.7rem;padding:6px 10px;">
                </div>
            </div>
            
            <!-- ASSIGN DOCTOR -->
            <div class="form-row grid-full" style="margin-top:16px;">
                <div class="assign-doctor-section">
                    <div style="font-size:0.85rem;font-weight:600;display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
                        <i class="fas fa-user-md" style="color:var(--primary);"></i>
                        Assign Doctor <span style="font-weight:400;font-size:0.75rem;color:var(--text-secondary);">(Optional)</span>
                        <span style="margin-left:auto;font-size:0.6rem;color:var(--success);">
                            <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#34D399;margin-right:4px;"></span>
                            <span id="doctorUpdateStatus">Live</span>
                        </span>
                    </div>
                    
                    <div class="assign-doctor-toggle" onclick="toggleAssignDoctor(event)">
                        <input type="checkbox" name="assign_doctor" id="assignDoctorCheckbox" value="1" 
                               <?= isset($_POST['assign_doctor']) && $_POST['assign_doctor'] == 1 ? 'checked' : '' ?>
                               onchange="toggleAssignDoctor()">
                        <span style="font-weight:500;font-size:0.8rem;">
                            <i class="fas fa-check-circle" style="color:var(--primary);"></i> Assign doctor after registration
                        </span>
                        <span style="font-size:0.65rem;color:var(--text-secondary);">(Bill will be created with fee)</span>
                        <span class="fee-info">
                            💰 TSh <span id="toggleFeeAmount"><?= number_format($default_price, 0) ?></span>
                        </span>
                    </div>
                    
                    <div id="feeInfoBox" style="background:var(--warning-bg);border:2px solid var(--warning);border-radius:10px;padding:10px 14px;margin-top:10px;display:none;align-items:center;gap:10px;font-size:0.75rem;color:var(--warning);">
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
                                    <span id="doctorCountBadge" style="font-size:0.6rem;font-weight:400;color:var(--text-secondary);">(<?= $online_doctors_count ?> online, <?= $offline_doctors_count ?> offline)</span>
                                </label>
                                <select name="doctor_id" class="form-control" id="doctorSelect">
                                    <option value="">-- Select Doctor --</option>
                                    <?php if (!empty($online_doctors) && count($online_doctors) > 0): ?>
                                        <optgroup label="🟢 Online Doctors (<?= $online_doctors_count ?>)">
                                            <?php foreach ($online_doctors as $doctor): ?>
                                                <option value="<?= $doctor['id'] ?>" data-online="1">
                                                    🟢 Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                                                    <?php if (!empty($doctor['specialty'])): ?>(<?= htmlspecialchars($doctor['specialty']) ?>)<?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                    <?php if (!empty($offline_doctors) && count($offline_doctors) > 0): ?>
                                        <optgroup label="⚪ Offline Doctors (<?= $offline_doctors_count ?>)">
                                            <?php foreach ($offline_doctors as $doctor): ?>
                                                <option value="<?= $doctor['id'] ?>" data-online="0">
                                                    ⚪ Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                </select>
                                <div style="margin-top:4px;font-size:0.6rem;color:var(--text-secondary);">
                                    <span style="color:#059669;" id="onlineCountDisplay">🟢 <?= $online_doctors_count ?> online</span>
                                    <span style="margin:0 4px;">|</span>
                                    <span id="offlineCountDisplay">⚪ <?= $offline_doctors_count ?> offline</span>
                                    <span style="margin:0 4px;">|</span>
                                    <span id="lastDoctorUpdate">Updated: <?= date('H:i:s') ?></span>
                                </div>
                            </div>
                            
                            <div class="form-row" style="margin-bottom:0;">
                                <label class="form-label">
                                    <i class="fas fa-money-bill-wave label-icon"></i> Consultation Fee
                                </label>
                                <div style="padding:10px 14px;background:var(--bg-body);border-radius:10px;border:2px solid var(--border-color);">
                                    <span style="font-size:1rem;font-weight:700;color:var(--primary);">
                                        TSh <span id="feeDisplay"><?= number_format($default_price, 0) ?></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid-2" style="margin-top:14px;">
                            <div class="form-row" style="margin-bottom:0;">
                                <label class="form-label"><i class="fas fa-notes-medical label-icon"></i> Symptoms</label>
                                <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:4px;" id="symptomSelector">
                                    <?php foreach ($common_symptoms as $symptom): ?>
                                        <span style="display:inline-flex;align-items:center;gap:3px;padding:2px 10px 2px 6px;border-radius:16px;border:2px solid var(--border-color);background:var(--bg-body);cursor:pointer;font-size:0.65rem;color:var(--text-secondary);user-select:none;" 
                                              class="symptom-chip" data-symptom="<?= htmlspecialchars($symptom) ?>" onclick="toggleSymptom(this)">
                                            <span>🩺</span> <?= htmlspecialchars($symptom) ?>
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
            
            <!-- HIDDEN FIELDS -->
            <input type="hidden" name="branch_id" value="<?= $selected_branch_id ?>">
            <input type="hidden" name="register_patient" value="1">
            <input type="hidden" name="registered_by_id" value="<?= $user_id ?>">
            <input type="hidden" name="registered_by_name" value="<?= htmlspecialchars($user_full_name) ?>">
            
            <!-- ACTIONS -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="registerBtn">
                    <i class="fas fa-save"></i> Register Patient
                </button>
                <button type="reset" class="btn btn-outline" id="resetFormBtn">
                    <i class="fas fa-undo"></i> Reset Form
                </button>
                <a href="patients.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
            
            <!-- FOOTER INFO -->
            <div style="margin-top:16px;padding-top:12px;font-size:0.6rem;color:var(--text-secondary);text-align:center;border-top:1px solid var(--border-color);">
                <i class="fas fa-info-circle"></i>
                Patient ID: <strong><?= $patient_id_number ?></strong>
                <span style="margin:0 6px;">|</span>
                <span style="color:var(--success);"><i class="fas fa-user-tie"></i> Assigned By: <strong><?= htmlspecialchars($user_full_name) ?></strong> (saved to database)</span>
                <span style="margin:0 6px;">|</span>
                <span style="color:var(--primary);"><i class="fas fa-heartbeat"></i> 7 Vital signs (with SpO₂)</span>
            </div>
        </form>
    </div>

    <!-- FOOTER -->
    <footer style="padding:14px 0;border-top:1px solid var(--border-color);margin-top:24px;text-align:center;font-size:0.65rem;color:var(--text-secondary);">
        <p>
            <span style="color:var(--primary);font-weight:600;">Braick Dispensary</span> Management System
            <span style="margin:0 6px;">|</span>
            Register Patient
            <span style="margin:0 6px;">|</span>
            Logged in as: <strong><?= htmlspecialchars($full_name) ?></strong>
            <span style="margin:0 6px;">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
        </p>
    </footer>

</main>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    var selectedBranchId = <?= (int)$selected_branch_id ?>;

    // DARK MODE
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    if (localStorage.getItem('darkMode') === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        if (darkIcon) darkIcon.className = 'fas fa-sun';
        if (darkText) darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // AUTOCOMPLETE
    var autocompleteTimeout = null;
    var currentAutocompleteIndex = -1;
    
    function searchPatients(query) {
        var dropdown = document.getElementById('autocompleteDropdown');
        var results = document.getElementById('autocompleteResults');
        var loading = document.getElementById('autocompleteLoading');
        
        if (!dropdown || !results) return;
        if (query.length < 1) {
            dropdown.style.display = 'none';
            return;
        }
        
        if (autocompleteTimeout) clearTimeout(autocompleteTimeout);
        
        autocompleteTimeout = setTimeout(function() {
            if (loading) loading.style.display = 'block';
            results.innerHTML = '';
            dropdown.style.display = 'block';
            
            fetch('new_patient.php?action=search_patients&q=' + encodeURIComponent(query) + '&branch=' + selectedBranchId)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (loading) loading.style.display = 'none';
                    
                    if (data.success && data.patients && data.patients.length > 0) {
                        var html = '';
                        data.patients.forEach(function(p) {
                            var initial = (p.full_name || 'U').charAt(0).toUpperCase();
                            var activeVisit = p.active_visits > 0;
                            var visitBadge = activeVisit 
                                ? '<span style="background:var(--warning-bg);color:var(--warning);padding:0 6px;border-radius:8px;font-size:0.5rem;">🔄 Active</span>' 
                                : '<span style="background:var(--gray-200);color:var(--text-secondary);padding:0 6px;border-radius:8px;font-size:0.5rem;">📋 No Visit</span>';
                            
                            html += '<div style="padding:8px 14px;cursor:pointer;border-bottom:1px solid var(--border-color);display:flex;align-items:center;gap:10px;" ' +
                                    'onclick="selectPatient(' + p.id + ', \'' + escapeHtml(p.full_name) + '\', \'' + escapeHtml(p.phone || '') + '\', \'' + escapeHtml(p.gender || '') + '\', \'' + escapeHtml(p.date_of_birth || '') + '\', \'' + escapeHtml(p.address || '') + '\', \'' + escapeHtml(p.blood_group || '') + '\', \'' + escapeHtml(p.allergies || '') + '\', \'' + escapeHtml(p.emergency_contact || '') + '\', \'' + escapeHtml(p.marital_status || '') + '\')">' +
                                '<div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.75rem;flex-shrink:0;">' + initial + '</div>' +
                                '<div style="flex:1;min-width:0;">' +
                                    '<div style="font-weight:600;font-size:0.8rem;color:var(--text-primary);">' + escapeHtml(p.full_name) + '</div>' +
                                    '<div style="font-size:0.6rem;color:var(--text-secondary);display:flex;gap:8px;flex-wrap:wrap;margin-top:2px;">' +
                                        '<span><i class="fas fa-id-card"></i> ' + escapeHtml(p.patient_id || 'N/A') + '</span>' +
                                        (p.phone ? '<span><i class="fas fa-phone"></i> ' + escapeHtml(p.phone) + '</span>' : '') +
                                        (p.gender ? '<span>' + escapeHtml(p.gender) + '</span>' : '') +
                                        visitBadge +
                                    '</div>' +
                                '</div>' +
                            '</div>';
                        });
                        results.innerHTML = html;
                    } else {
                        results.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-secondary);font-size:0.75rem;">' +
                            '<i class="fas fa-user-plus" style="font-size:1.2rem;display:block;margin-bottom:4px;color:var(--primary);"></i>' +
                            '<p>No existing patient found</p>' +
                            '<p style="font-size:0.6rem;">Continue typing to register new patient</p></div>';
                    }
                });
        }, 250);
    }
    
    function selectPatient(id, name, phone, gender, dob, address, bloodGroup, allergies, emergencyContact, maritalStatus) {
        document.getElementById('fullNameInput').value = name;
        
        var fields = {
            'input[name="phone"]': phone,
            'input[name="date_of_birth"]': dob,
            'textarea[name="address"]': address,
            'input[name="emergency_contact"]': emergencyContact
        };
        
        for (var sel in fields) {
            var el = document.querySelector(sel);
            if (el && !el.value) el.value = fields[sel];
        }
        
        var setSelect = function(name, value) {
            var sel = document.querySelector('select[name="' + name + '"]');
            if (sel && value) {
                for (var i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].value === value) { sel.selectedIndex = i; break; }
                }
            }
        };
        setSelect('gender', gender);
        setSelect('blood_group', bloodGroup);
        setSelect('marital_status', maritalStatus);
        
        var allergiesTextarea = document.getElementById('allergiesTextarea');
        if (allergiesTextarea && allergies) {
            allergiesTextarea.value = allergies;
            syncAllergyChips();
        }
        
        hideAutocomplete();
        showToast('✅ Patient Selected', 'Details auto-filled', 'success');
    }
    
    function hideAutocomplete() {
        var d = document.getElementById('autocompleteDropdown');
        if (d) d.style.display = 'none';
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML.replace(/'/g, "\\'");
    }

    // BMI
    function calculateBMI() {
        var weight = parseFloat(document.getElementById('weightInput')?.value);
        var height = parseFloat(document.getElementById('heightInput')?.value);
        var output = document.getElementById('bmiOutput');
        var category = document.getElementById('bmiCategory');
        
        if (weight && height && height > 0) {
            var bmi = Math.round((weight / ((height/100) * (height/100))) * 10) / 10;
            if (output) output.value = bmi;
            
            var cat = '', cls = '';
            if (bmi < 18.5) { cat = 'Underweight'; cls = 'underweight'; }
            else if (bmi < 25) { cat = 'Normal'; cls = 'normal'; }
            else if (bmi < 30) { cat = 'Overweight'; cls = 'overweight'; }
            else { cat = 'Obese'; cls = 'obese'; }
            
            if (category) {
                category.textContent = cat;
                category.className = 'vital-bmi-category ' + cls;
            }
        } else if (output) {
            output.value = '';
            if (category) { category.textContent = 'Auto'; category.className = 'vital-bmi-category'; }
        }
    }

    // SpO2
    function calculateSpO2Category() {
        var input = document.getElementById('spo2Input');
        var category = document.getElementById('spo2Category');
        if (!input || !category) return;
        
        var spo2 = parseFloat(input.value);
        if (!spo2 || spo2 <= 0) {
            category.textContent = 'Auto';
            category.className = 'vital-bmi-category';
            return;
        }
        
        var cat = '', cls = '';
        if (spo2 >= 95) { cat = 'Normal'; cls = 'spo2-normal'; }
        else if (spo2 >= 90) { cat = 'Low'; cls = 'spo2-low'; }
        else { cat = 'Critical'; cls = 'spo2-critical'; }
        
        category.textContent = cat;
        category.className = 'vital-bmi-category ' + cls;
    }

    // Allergies
    var allergyChips = document.querySelectorAll('.allergy-chip');
    var allergiesTextarea = document.getElementById('allergiesTextarea');
    
    function syncAllergyChips() {
        if (!allergiesTextarea) return;
        var list = allergiesTextarea.value.split(',').map(s => s.trim()).filter(s => s);
        
        allergyChips.forEach(function(chip) {
            var name = chip.dataset.allergy;
            var cb = chip.querySelector('.allergy-checkbox');
            if (list.includes(name)) {
                chip.style.borderColor = 'var(--danger)';
                chip.style.background = 'var(--danger-bg)';
                chip.style.color = 'var(--danger-dark)';
                if (cb) cb.checked = true;
            } else {
                chip.style.borderColor = 'var(--border-color)';
                chip.style.background = 'var(--bg-body)';
                chip.style.color = 'var(--text-secondary)';
                if (cb) cb.checked = false;
            }
        });
    }
    
    allergyChips.forEach(function(chip) {
        chip.addEventListener('click', function(e) {
            e.preventDefault();
            var cb = this.querySelector('.allergy-checkbox');
            var name = this.dataset.allergy;
            if (cb) cb.checked = !cb.checked;
            
            var list = allergiesTextarea.value.split(',').map(s => s.trim()).filter(s => s);
            if (cb && cb.checked) {
                if (!list.includes(name)) list.push(name);
            } else {
                list = list.filter(i => i !== name);
            }
            allergiesTextarea.value = list.join(', ');
            syncAllergyChips();
        });
    });
    
    allergiesTextarea?.addEventListener('input', syncAllergyChips);
    syncAllergyChips();

    // Symptoms
    function toggleSymptom(el) {
        var symptom = el.dataset.symptom;
        var textarea = document.getElementById('symptomsTextarea');
        var list = textarea.value.split(',').map(s => s.trim()).filter(s => s);
        
        if (list.includes(symptom)) {
            list = list.filter(i => i !== symptom);
            el.style.borderColor = 'var(--border-color)';
            el.style.background = 'var(--bg-body)';
            el.style.color = 'var(--text-secondary)';
        } else {
            list.push(symptom);
            el.style.borderColor = 'var(--primary)';
            el.style.background = 'var(--primary-bg)';
            el.style.color = 'var(--primary)';
        }
        textarea.value = list.join(', ');
    }

    // Fee
    function updateFeeDisplay() {
        var select = document.getElementById('visitTypeSelect');
        if (!select) return;
        var opt = select.options[select.selectedIndex];
        var price = parseInt(opt.dataset.price || 0).toLocaleString();
        
        ['feeDisplay', 'toggleFeeAmount', 'feeAmountDisplay'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.textContent = price;
        });
    }

    // Toggle Assign Doctor
    function toggleAssignDoctor(event) {
        var cb = document.getElementById('assignDoctorCheckbox');
        var fields = document.getElementById('assignDoctorFields');
        var feeBox = document.getElementById('feeInfoBox');
        
        if (event && event.target && event.target.tagName !== 'INPUT') {
            cb.checked = !cb.checked;
        }
        
        if (cb.checked) {
            if (fields) fields.classList.add('show');
            if (feeBox) feeBox.style.display = 'flex';
        } else {
            if (fields) fields.classList.remove('show');
            if (feeBox) feeBox.style.display = 'none';
        }
    }

    // Doctor status update
    var doctorInterval = null;
    function fetchDoctorStatus() {
        var fd = new FormData();
        fd.append('action', 'get_doctor_status');
        fd.append('branch_id', selectedBranchId);
        
        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(function(data) {
                if (data.success) {
                    document.getElementById('onlineCountDisplay').textContent = '🟢 ' + data.online_count + ' online';
                    document.getElementById('offlineCountDisplay').textContent = '⚪ ' + data.offline_count + ' offline';
                    document.getElementById('doctorCountBadge').textContent = '(' + data.online_count + ' online, ' + data.offline_count + ' offline)';
                    document.getElementById('lastDoctorUpdate').textContent = 'Updated: ' + data.timestamp;
                    document.getElementById('doctorUpdateStatus').textContent = 'Live ' + data.timestamp;
                    
                    var sel = document.getElementById('doctorSelect');
                    if (sel && data.doctor_options) {
                        var current = sel.value;
                        sel.innerHTML = data.doctor_options;
                        if (current) sel.value = current;
                    }
                }
            });
    }
    
    function startDoctorUpdate() {
        if (doctorInterval) clearInterval(doctorInterval);
        setTimeout(fetchDoctorStatus, 1000);
        doctorInterval = setInterval(fetchDoctorStatus, 3000);
    }

    // DateTime
    function updateDateTime() {
        var now = new Date();
        var d = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        var t = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        document.getElementById('clockDisplay').textContent = d + ' • ' + t;
        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + t;
    }
    setInterval(updateDateTime, 1000);

    // Toast
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var t = document.getElementById('toastTitle');
        var m = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + type;
        t.textContent = title;
        m.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(() => toast.style.display = 'none', 400);
        }, 3500);
    }

    // Form validation
    document.getElementById('registrationForm')?.addEventListener('submit', function(e) {
        var name = document.querySelector('input[name="full_name"]').value.trim();
        var gender = document.querySelector('select[name="gender"]').value;
        var assignDoc = document.getElementById('assignDoctorCheckbox').checked;
        
        if (!name) { e.preventDefault(); showToast('Error', 'Please enter patient full name', 'error'); return false; }
        if (!gender) { e.preventDefault(); showToast('Error', 'Please select gender', 'error'); return false; }
        
        if (assignDoc) {
            var docSel = document.getElementById('doctorSelect');
            if (!docSel.value) { e.preventDefault(); showToast('Error', 'Please select a doctor', 'error'); docSel.focus(); return false; }
        }
        
        var btn = document.getElementById('registerBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...';
    });

    // Reset
    document.getElementById('resetFormBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('registrationForm').reset();
        allergiesTextarea.value = '';
        syncAllergyChips();
        document.querySelectorAll('.symptom-chip').forEach(function(c) {
            c.style.borderColor = 'var(--border-color)';
            c.style.background = 'var(--bg-body)';
            c.style.color = 'var(--text-secondary)';
        });
        document.getElementById('bmiOutput').value = '';
        document.getElementById('bmiCategory').textContent = 'Auto';
        document.getElementById('bmiCategory').className = 'vital-bmi-category';
        document.getElementById('spo2Category').textContent = 'Auto';
        document.getElementById('spo2Category').className = 'vital-bmi-category';
        document.getElementById('assignDoctorCheckbox').checked = false;
        document.getElementById('assignDoctorFields').classList.remove('show');
        document.getElementById('feeInfoBox').style.display = 'none';
        updateFeeDisplay();
        hideAutocomplete();
    });

    // Init
    document.addEventListener('DOMContentLoaded', function() {
        calculateBMI();
        calculateSpO2Category();
        updateFeeDisplay();
        setTimeout(startDoctorUpdate, 2000);
        
        document.getElementById('spo2Input')?.addEventListener('input', calculateSpO2Category);
        document.getElementById('visitTypeSelect')?.addEventListener('change', updateFeeDisplay);
        
        document.addEventListener('click', function(e) {
            var wrap = e.target.closest('div[style*="position:relative"]');
            if (!wrap) hideAutocomplete();
        });
        
        var searchBtn = document.getElementById('searchBtn');
        var searchInput = document.getElementById('searchInput');
        function doSearch() {
            var q = searchInput.value.trim();
            if (q) window.location.href = 'search.php?q=' + encodeURIComponent(q);
        }
        searchBtn?.addEventListener('click', doSearch);
        searchInput?.addEventListener('keypress', e => { if (e.key === 'Enter') doSearch(); });
        
        console.log('%c👤 Braick - New Patient (assigned_by FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
        console.log('%c✅ assigned_by_id inahifadhiwa database', 'font-size:13px; color:#059669; font-weight:bold;');
        console.log('%c✅ 7 Vital Signs (with SpO2)', 'font-size:13px; color:#0891B2;');
    });
</script>

</body>
</html>