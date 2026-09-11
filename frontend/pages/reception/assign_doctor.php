<?php
// ================================================================
// FILE: frontend/pages/reception/assign_doctor.php
// RECEPTION - ASSIGN / CHANGE DOCTOR & LAB TESTS
// ✅ FIXED: Bigger cards with proper sizes
// ✅ FIXED: Lab test card is now bigger and readable
// ✅ FIXED: 3 cards left, 3 cards right - Uniform sizes
// ✅ FIXED: Lab mode - Doctor dropdown HIDDEN completely
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Receptionist';
$branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? 'reception';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$user_branch_id = $branch_id;
$selected_branch_id = $branch_id;
$message = '';
$message_type = '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$all_patients = [];
$pending_patients = [];
$assigned_patients = [];
$lab_only_patients = [];
$doctors = [];
$online_doctors = [];
$offline_doctors = [];
$online_doctors_count = 0;
$offline_doctors_count = 0;
$total_doctors = 0;
$visit_type_options = [];
$pending_count = 0;
$assigned_count = 0;
$lab_only_count = 0;
$selected_patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$latest_vital_signs = null;
$selected_patient_data = null;
$change_mode = isset($_GET['change']) && $_GET['change'] == 1;
$lab_tests_catalog = [];
$lab_tests_list = [];

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
    
    // GET CONSULTATION SERVICES
    $stmt = $db->prepare("
        SELECT id, service_name, description, price, unit, is_active
        FROM services 
        WHERE category_id = 2 
        AND is_active = 1 
        AND (branch_id = ? OR branch_id IS NULL)
        ORDER BY 
            CASE 
                WHEN service_name LIKE '%New Patient%' THEN 0
                WHEN service_name LIKE '%General%' THEN 1
                WHEN service_name LIKE '%Emergency%' THEN 2
                WHEN service_name LIKE '%Specialist%' THEN 3
                WHEN service_name LIKE '%Follow%' THEN 4
                WHEN service_name LIKE '%Consultation%' THEN 5
                ELSE 6
            END,
            service_name
    ");
    $stmt->execute([$selected_branch_id]);
    $consultation_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $visit_type_options = [];
    $default_service_id = null;
    
    if (!empty($consultation_services)) {
        foreach ($consultation_services as $service) {
            $service_id = $service['id'];
            $service_name = $service['service_name'];
            $price = (float)$service['price'];
            
            $icon = '🏥';
            if (strpos(strtolower($service_name), 'new') !== false) $icon = '🆕';
            elseif (strpos(strtolower($service_name), 'follow') !== false) $icon = '🔄';
            elseif (strpos(strtolower($service_name), 'emergency') !== false) $icon = '🚨';
            elseif (strpos(strtolower($service_name), 'specialist') !== false) $icon = '👨‍⚕️';
            elseif (strpos(strtolower($service_name), 'general') !== false) $icon = '🏥';
            
            $visit_type_options[$service_id] = [
                'id' => $service_id,
                'service_name' => $service_name,
                'price' => $price,
                'unit' => $service['unit'] ?? 'each',
                'description' => $service['description'] ?? '',
                'is_active' => $service['is_active'],
                'icon' => $icon
            ];
            
            if (strpos(strtolower($service_name), 'new') !== false) {
                $default_service_id = $service_id;
            } elseif (strpos(strtolower($service_name), 'general') !== false && $default_service_id === null) {
                $default_service_id = $service_id;
            }
        }
    }
    
    if ($default_service_id === null && !empty($visit_type_options)) {
        $default_service_id = array_key_first($visit_type_options);
    }
    
    // GET LAB TESTS CATALOG
    $stmt = $db->prepare("
        SELECT id, test_name, price, category 
        FROM lab_tests_catalog 
        WHERE is_active = 1 
        ORDER BY category, test_name
    ");
    $stmt->execute();
    $lab_tests_catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // GET ALL PATIENTS
    $query = "
        SELECT 
            p.id,
            p.full_name,
            p.patient_id,
            p.phone,
            p.gender,
            p.date_of_birth,
            p.blood_group,
            p.allergies,
            p.assigned_doctor_id,
            p.created_at as patient_created_at,
            u.full_name as assigned_doctor_name,
            u.is_online as assigned_doctor_online,
            v.id as visit_id,
            v.status as visit_status,
            v.visit_number,
            v.visit_type,
            v.service_id,
            v.consultation_fee,
            v.created_at as visit_created_at,
            v.doctor_id as visit_doctor_id,
            DATEDIFF(NOW(), p.created_at) as patient_days,
            (SELECT COUNT(*) FROM lab_tests lt WHERE lt.patient_id = p.id AND lt.status NOT IN ('completed', 'cancelled') AND lt.visit_id = v.id) as pending_lab_tests_count
        FROM patients p
        LEFT JOIN visits v ON p.id = v.patient_id 
            AND v.status IN ('new', 'pending', 'assigned', 'with_doctor', 'lab_test', 'completed')
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE p.branch_id = ?
    ";
    $params = [$selected_branch_id];
    
    if (!empty($search)) {
        $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " GROUP BY p.id ORDER BY p.created_at DESC, p.id DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $all_patients_raw = $stmt->fetchAll();
    
    $all_patients = [];
    foreach ($all_patients_raw as $patient) {
        if (!empty($patient['visit_id']) && ($patient['visit_status'] === 'completed' || $patient['visit_status'] === 'cancelled')) {
            continue;
        }
        if (!empty($patient['visit_id']) && $patient['visit_status'] === 'lab_test') {
            $pending_labs = (int)($patient['pending_lab_tests_count'] ?? 0);
            if ($pending_labs == 0) {
                continue;
            }
        }
        $all_patients[] = $patient;
    }
    
    if ($selected_patient_id > 0) {
        foreach ($all_patients as $p) {
            if ($p['id'] == $selected_patient_id) {
                $selected_patient_data = $p;
                break;
            }
        }
    }
    
    $pending_patients = [];
    $assigned_patients = [];
    $lab_only_patients = [];
    
    foreach ($all_patients as $patient) {
        $patient['has_active_visit'] = !empty($patient['visit_id']);
        $patient['patient_days'] = isset($patient['patient_days']) ? (int)$patient['patient_days'] : 0;
        
        if ($patient['has_active_visit']) {
            if ($patient['visit_status'] === 'lab_test' && empty($patient['visit_doctor_id'])) {
                $lab_only_patients[] = $patient;
                $lab_only_count++;
            } elseif (in_array($patient['visit_status'], ['new', 'pending'])) {
                $pending_patients[] = $patient;
                $pending_count++;
            } elseif ($patient['visit_status'] === 'assigned' || $patient['visit_status'] === 'with_doctor') {
                $assigned_patients[] = $patient;
                $assigned_count++;
            }
        }
    }
    
    if ($selected_patient_id > 0) {
        $stmt = $db->prepare("
            SELECT vs.*, u.full_name as recorded_by_name
            FROM vital_signs vs
            LEFT JOIN users u ON vs.recorded_by = u.id
            WHERE vs.patient_id = ?
            ORDER BY vs.recorded_at DESC LIMIT 1
        ");
        $stmt->execute([$selected_patient_id]);
        $latest_vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $stmt = $db->prepare("
        SELECT id, full_name, specialty, is_online 
        FROM users 
        WHERE role = 'doctor' AND status = 'active' AND branch_id = ?
        ORDER BY is_online DESC, full_name
    ");
    $stmt->execute([$selected_branch_id]);
    $doctors = $stmt->fetchAll();
    
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
    
    if ($selected_patient_id > 0) {
        $stmt = $db->prepare("
            SELECT lt.*, CONCAT('Test #', lt.id) as request_number, 'Lab Test' as test_names, 1 as test_count,
                   lt.test_price as lab_total, lt.status as test_status, lt.results as test_results
            FROM lab_tests lt
            WHERE lt.patient_id = ? AND lt.branch_id = ?
            ORDER BY lt.created_at DESC LIMIT 10
        ");
        $stmt->execute([$selected_patient_id, $selected_branch_id]);
        $lab_tests_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // CREATE LAB ONLY BILL
    function createLabOnlyBill($db, $patient_id, $visit_id, $lab_test_ids, $user_id, $branch_id) {
        $total_lab_fee = 0;
        $lab_test_names = [];
        
        if (empty($lab_test_ids)) {
            return ['status' => 'error', 'message' => 'No lab tests selected'];
        }
        
        $test_ids_imploded = implode(',', array_map('intval', $lab_test_ids));
        $stmt = $db->prepare("SELECT id, test_name, price FROM lab_tests_catalog WHERE id IN ($test_ids_imploded)");
        $stmt->execute();
        $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tests as $test) {
            $total_lab_fee += (float)$test['price'];
            $lab_test_names[] = $test['test_name'];
        }
        
        if ($total_lab_fee <= 0) {
            return ['status' => 'error', 'message' => 'No lab tests with price > 0 selected'];
        }
        
        $stmt = $db->prepare("SELECT id, bill_number FROM bills WHERE visit_id = ? AND status IN ('pending', 'partial') LIMIT 1");
        $stmt->execute([$visit_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            $stmt = $db->prepare("UPDATE bills SET total_amount = total_amount + ?, balance = balance + ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$total_lab_fee, $total_lab_fee, $existing['id']]);
            $bill_id = $existing['id'];
            $bill_number = $existing['bill_number'];
            
            foreach ($tests as $test) {
                $stmt = $db->prepare("INSERT INTO bill_items (bill_id, patient_id, branch_id, item_type, item_name, quantity, unit_price, total_price, status, created_at) VALUES (?, ?, ?, 'lab_test', ?, 1, ?, ?, 'pending', NOW())");
                $stmt->execute([$bill_id, $patient_id, $branch_id, $test['test_name'], $test['price'], $test['price']]);
            }
            
            return ['status' => 'updated', 'message' => 'Lab tests added to existing bill', 'bill_id' => $bill_id, 'bill_number' => $bill_number, 'total_lab_fee' => $total_lab_fee];
        }
        
        $bill_number = 'BILL-LAB-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999);
        
        $stmt = $db->prepare("INSERT INTO bills (bill_number, patient_id, visit_id, branch_id, created_by, subtotal, total_amount, balance, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$bill_number, $patient_id, $visit_id, $branch_id, $user_id, $total_lab_fee, $total_lab_fee, $total_lab_fee]);
        $bill_id = $db->lastInsertId();
        
        foreach ($tests as $test) {
            $stmt = $db->prepare("INSERT INTO bill_items (bill_id, patient_id, branch_id, item_type, item_name, quantity, unit_price, total_price, status, created_at) VALUES (?, ?, ?, 'lab_test', ?, 1, ?, ?, 'pending', NOW())");
            $stmt->execute([$bill_id, $patient_id, $branch_id, $test['test_name'], $test['price'], $test['price']]);
        }
        
        $stmt = $db->prepare("UPDATE visits SET lab_fees_total = lab_fees_total + ? WHERE id = ?");
        $stmt->execute([$total_lab_fee, $visit_id]);
        
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE role = 'cashier' AND status = 'active' AND branch_id = ?");
            $stmt->execute([$branch_id]);
            $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($cashiers as $cashier) {
                $stmt = $db->prepare("INSERT INTO notifications (user_id, branch_id, title, message, type, link, is_read, created_at) VALUES (?, ?, '🧪 Lab Test Bill Created', ?, 'bill', ?, 0, NOW())");
                $stmt->execute([$cashier['id'], $branch_id, "Lab Test bill #$bill_number (TSh " . number_format($total_lab_fee) . ") for patient ID #$patient_id - " . implode(', ', $lab_test_names), "cashier_dashboard.php"]);
            }
        } catch (Exception $e) {}
        
        return ['status' => 'created', 'message' => 'Lab bill created and sent to Cashier!', 'bill_id' => $bill_id, 'bill_number' => $bill_number, 'total_lab_fee' => $total_lab_fee];
    }
    
    // CREATE VISIT BILL
    function createVisitBill($db, $patient_id, $visit_id, $service_name, $consultation_fee, $user_id, $branch_id) {
        if ($consultation_fee <= 0) {
            return ['status' => 'error', 'message' => 'Consultation fee is 0 or negative'];
        }
        
        $stmt = $db->prepare("SELECT id, bill_number FROM bills WHERE visit_id = ? AND status IN ('pending', 'partial') LIMIT 1");
        $stmt->execute([$visit_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            $stmt = $db->prepare("UPDATE bills SET total_amount = total_amount + ?, balance = balance + ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$consultation_fee, $consultation_fee, $existing['id']]);
            $bill_id = $existing['id'];
            $bill_number = $existing['bill_number'];
            
            $stmt = $db->prepare("INSERT INTO bill_items (bill_id, patient_id, branch_id, item_type, item_name, quantity, unit_price, total_price, status, created_at) VALUES (?, ?, ?, 'consultation', ?, 1, ?, ?, 'pending', NOW())");
            $stmt->execute([$bill_id, $patient_id, $branch_id, $service_name, $consultation_fee, $consultation_fee]);
            
            return ['status' => 'updated', 'message' => 'Consultation fee added to existing bill', 'bill_id' => $bill_id, 'bill_number' => $bill_number, 'total_consultation_fee' => $consultation_fee];
        }
        
        $bill_number = 'BILL-CONS-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999);
        
        $stmt = $db->prepare("INSERT INTO bills (bill_number, patient_id, visit_id, branch_id, created_by, subtotal, total_amount, balance, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$bill_number, $patient_id, $visit_id, $branch_id, $user_id, $consultation_fee, $consultation_fee, $consultation_fee]);
        $bill_id = $db->lastInsertId();
        
        $stmt = $db->prepare("INSERT INTO bill_items (bill_id, patient_id, branch_id, item_type, item_name, quantity, unit_price, total_price, status, created_at) VALUES (?, ?, ?, 'consultation', ?, 1, ?, ?, 'pending', NOW())");
        $stmt->execute([$bill_id, $patient_id, $branch_id, $service_name, $consultation_fee, $consultation_fee]);
        
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE role = 'cashier' AND status = 'active' AND branch_id = ?");
            $stmt->execute([$branch_id]);
            $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($cashiers as $cashier) {
                $stmt = $db->prepare("INSERT INTO notifications (user_id, branch_id, title, message, type, link, is_read, created_at) VALUES (?, ?, '💰 Consultation Bill Created', ?, 'bill', ?, 0, NOW())");
                $stmt->execute([$cashier['id'], $branch_id, "Consultation bill #$bill_number (TSh " . number_format($consultation_fee) . ") for patient ID #$patient_id - $service_name", "cashier_dashboard.php"]);
            }
        } catch (Exception $e) {}
        
        return ['status' => 'created', 'message' => 'Consultation bill created and sent to Cashier!', 'bill_id' => $bill_id, 'bill_number' => $bill_number, 'total_consultation_fee' => $consultation_fee];
    }
    
    // HANDLE AJAX
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'get_live_data') {
            header('Content-Type: application/json');
            
            $patient_options_html = '';
            if (!empty($all_patients)) {
                $patient_options_html .= '<optgroup label="📋 All Patients (' . count($all_patients) . ')">';
                foreach ($all_patients as $patient) {
                    $status_label = '📋 No Visit';
                    $status_class = 'no_visit';
                    $status_icon = '📋';
                    
                    if (!empty($patient['visit_id'])) {
                        if ($patient['visit_status'] === 'lab_test' && empty($patient['visit_doctor_id'])) {
                            $status_label = '🧪 Lab Only'; $status_class = 'lab_only'; $status_icon = '🧪';
                        } elseif (in_array($patient['visit_status'], ['new', 'pending'])) {
                            $status_label = '⏳ Pending'; $status_class = 'pending'; $status_icon = '⏳';
                        } elseif ($patient['visit_status'] === 'assigned' || $patient['visit_status'] === 'with_doctor') {
                            $status_label = '✅ Assigned'; $status_class = 'assigned'; $status_icon = '✅';
                        }
                    }
                    
                    $doctor_info = '';
                    if (!empty($patient['assigned_doctor_name'])) {
                        $online_status = !empty($patient['assigned_doctor_online']) ? '🟢' : '⚪';
                        $doctor_info = ' 👨‍⚕️ Dr. ' . htmlspecialchars($patient['assigned_doctor_name']) . ' ' . $online_status;
                    }
                    
                    $selected = ($selected_patient_id == $patient['id']) ? 'selected' : '';
                    $days = (int)($patient['patient_days'] ?? 0);
                    $days_text = $days > 0 ? '<span class="days-badge-blue">📅 ' . $days . ' days</span>' : '<span class="days-badge-blue new">📅 New</span>';
                    
                    $patient_options_html .= '<option value="' . $patient['id'] . '" data-status="' . $status_class . '" ' . $selected . '>' . $status_icon . ' ' . htmlspecialchars($patient['full_name']) . ' (' . htmlspecialchars($patient['patient_id'] ?? 'N/A') . ') ' . $days_text . $doctor_info . ' <span class="status-badge-dropdown ' . $status_class . '">' . $status_label . '</span></option>';
                }
                $patient_options_html .= '</optgroup>';
            } else {
                $patient_options_html .= '<option value="" disabled>No patients found</option>';
            }
            
            $assigned_list_html = '';
            if (count($assigned_patients) > 0) {
                foreach ($assigned_patients as $patient) {
                    $assigned_days = 0;
                    if (!empty($patient['visit_created_at'])) {
                        $assigned_days = (int)floor((time() - strtotime($patient['visit_created_at'])) / 86400);
                    }
                    $days_text = $assigned_days > 0 ? '<span class="assigned-days-badge-blue">' . $assigned_days . ' days</span>' : '<span class="assigned-days-badge-blue new">Just assigned</span>';
                    
                    $assigned_list_html .= '<tr id="assigned-row-' . $patient['id'] . '" style="border-bottom:1px solid var(--border-color);">
                        <td style="padding:10px 12px;font-weight:500;font-size:0.85rem;">' . htmlspecialchars($patient['full_name']) . ' ' . $days_text . '<span class="text-xs text-gray-400 block">' . htmlspecialchars($patient['visit_type'] ?? 'Consultation') . '</span></td>
                        <td style="padding:10px 12px;font-family:monospace;font-size:0.8rem;">' . htmlspecialchars($patient['patient_id'] ?? 'N/A') . '</td>
                        <td style="padding:10px 12px;">' . (!empty($patient['assigned_doctor_name']) ? '<span class="assigned-doctor-tag-modern"><i class="fas fa-user-md"></i> Dr. ' . htmlspecialchars($patient['assigned_doctor_name']) . ' ' . ($patient['assigned_doctor_online'] == 1 ? '🟢' : '⚪') . '</span>' : '<span class="text-gray-400 text-xs">No doctor</span>') . '</td>
                        <td style="padding:10px 12px;"><span class="status-badge-dropdown assigned">✅ Assigned</span></td>
                        <td style="padding:10px 12px;"><button onclick="selectPatientAndChange(' . $patient['id'] . ')" class="btn-modern btn-modern-warning btn-modern-sm"> <i class="fas fa-sync-alt"></i> Change</button></td>
                    </tr>';
                }
            }
            
            $lab_only_html = '';
            if (count($lab_only_patients) > 0) {
                foreach ($lab_only_patients as $patient) {
                    $lab_days = 0;
                    if (!empty($patient['visit_created_at'])) {
                        $lab_days = (int)floor((time() - strtotime($patient['visit_created_at'])) / 86400);
                    }
                    $days_text = $lab_days > 0 ? '<span class="assigned-days-badge-blue">' . $lab_days . ' days</span>' : '<span class="assigned-days-badge-blue new">Just requested</span>';
                    
                    $lab_test_id = 0;
                    $lab_test_name = '';
                    try {
                        $stmt = $db->prepare("SELECT id, test_name FROM lab_tests WHERE patient_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
                        $stmt->execute([$patient['id']]);
                        $lab_result = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($lab_result) { $lab_test_id = $lab_result['id']; $lab_test_name = $lab_result['test_name']; }
                    } catch (Exception $e) {}
                    
                    $view_link = $lab_test_id > 0 ? '<a href="lab_tests.php?id=' . $lab_test_id . '" class="btn-modern btn-modern-purple btn-modern-sm" style="background:var(--purple);color:white;text-decoration:none;display:inline-flex;align-items:center;gap:4px;"><i class="fas fa-eye"></i> View</a>' : '<span class="text-gray-400 text-xs">No test</span>';
                    
                    $lab_only_html .= '<tr id="lab-row-' . $patient['id'] . '" style="border-bottom:1px solid var(--border-color);">
                        <td style="padding:10px 12px;font-weight:500;font-size:0.85rem;">' . htmlspecialchars($patient['full_name']) . ' ' . $days_text . ($lab_test_id > 0 ? '<span class="text-xs text-purple-500 block">' . htmlspecialchars($lab_test_name) . '</span>' : '') . '</td>
                        <td style="padding:10px 12px;font-family:monospace;font-size:0.8rem;">' . htmlspecialchars($patient['patient_id'] ?? 'N/A') . '</td>
                        <td style="padding:10px 12px;"><span class="lab-only-tag-modern"><i class="fas fa-flask"></i> Lab Only 🧪</span></td>
                        <td style="padding:10px 12px;"><span class="status-badge-dropdown lab_only">🧪 Pending</span></td>
                        <td style="padding:10px 12px;">' . $view_link . '</td>
                    </tr>';
                }
            }
            
            $doctor_options_html = '';
            if (!empty($online_doctors)) {
                $doctor_options_html .= '<optgroup label="🟢 Online Doctors (' . $online_doctors_count . ')">';
                foreach ($online_doctors as $doctor) {
                    $doctor_options_html .= '<option value="' . $doctor['id'] . '" data-online="1">🟢 Dr. ' . htmlspecialchars($doctor['full_name']) . (!empty($doctor['specialty']) ? ' (' . htmlspecialchars($doctor['specialty']) . ')' : '') . '</option>';
                }
                $doctor_options_html .= '</optgroup>';
            }
            if (!empty($offline_doctors)) {
                $doctor_options_html .= '<optgroup label="⚪ Offline Doctors (' . $offline_doctors_count . ')">';
                foreach ($offline_doctors as $doctor) {
                    $doctor_options_html .= '<option value="' . $doctor['id'] . '" data-online="0">⚪ Dr. ' . htmlspecialchars($doctor['full_name']) . (!empty($doctor['specialty']) ? ' (' . htmlspecialchars($doctor['specialty']) . ')' : '') . '</option>';
                }
                $doctor_options_html .= '</optgroup>';
            }
            if (empty($online_doctors) && empty($offline_doctors)) {
                $doctor_options_html .= '<option value="" disabled>No doctors available</option>';
            }
            
            $visit_type_options_html = '';
            if (!empty($visit_type_options)) {
                foreach ($visit_type_options as $service_id => $option) {
                    $price = $option['price'] ?? 0;
                    $icon = $option['icon'] ?? '🏥';
                    $service_name = $option['service_name'] ?? 'Consultation';
                    $selected = ($service_id === $default_service_id) ? 'selected' : '';
                    $visit_type_options_html .= '<option value="' . $service_id . '" data-price="' . $price . '" data-service-name="' . htmlspecialchars($service_name) . '" ' . $selected . '>' . $icon . ' ' . htmlspecialchars($service_name) . ' - TSh ' . number_format($price, 0) . '</option>';
                }
            } else {
                $visit_type_options_html .= '<option value="" data-price="0" selected disabled>❌ No Visit Type Available</option>';
            }
            
            $lab_tests_html = '';
            if (!empty($lab_tests_catalog)) {
                foreach ($lab_tests_catalog as $test) {
                    $lab_tests_html .= '<div class="lab-test-item-modern">
                        <input type="checkbox" name="lab_test_ids[]" value="' . $test['id'] . '" id="lab_test_' . $test['id'] . '" class="lab-test-checkbox" onchange="updateLabSelection(this)">
                        <label for="lab_test_' . $test['id'] . '">
                            <strong>' . htmlspecialchars($test['test_name']) . '</strong>
                            ' . (!empty($test['category']) ? '<span class="lab-test-category">' . htmlspecialchars($test['category']) . '</span>' : '') . '
                        </label>
                        <span class="lab-test-price">TSh ' . number_format($test['price'] ?? 0, 0) . '</span>
                    </div>';
                }
            } else {
                $lab_tests_html .= '<div class="text-center py-4 text-gray-400"><i class="fas fa-flask"></i><p style="font-size:0.85rem;">No lab tests available</p></div>';
            }
            
            echo json_encode([
                'success' => true,
                'pending_count' => $pending_count,
                'assigned_count' => $assigned_count,
                'lab_only_count' => $lab_only_count,
                'online_count' => $online_doctors_count,
                'offline_count' => $offline_doctors_count,
                'total_doctors' => $total_doctors,
                'patient_options' => $patient_options_html,
                'assigned_list_html' => $assigned_list_html,
                'assigned_list_count' => $assigned_count,
                'lab_only_html' => $lab_only_html,
                'doctor_options' => $doctor_options_html,
                'visit_type_options' => $visit_type_options_html,
                'lab_tests_html' => $lab_tests_html,
                'timestamp' => date('H:i:s')
            ]);
            exit;
        }
        
        if ($action === 'get_patient_details') {
            header('Content-Type: application/json');
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            
            if ($patient_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
                exit;
            }
            
            try {
                $stmt = $db->prepare("SELECT p.*, u.full_name as assigned_doctor_name, u.is_online as assigned_doctor_online, v.id as visit_id, v.status as visit_status, v.visit_type, v.created_at as visit_created_at, DATEDIFF(NOW(), p.created_at) as patient_days, DATEDIFF(NOW(), v.created_at) as visit_days FROM patients p LEFT JOIN visits v ON p.id = v.patient_id AND v.status NOT IN ('completed', 'cancelled') LEFT JOIN users u ON v.doctor_id = u.id WHERE p.id = ?");
                $stmt->execute([$patient_id]);
                $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($patient) {
                    echo json_encode(['success' => true, 'patient' => $patient, 'assigned_doctor' => $patient['assigned_doctor_name'] ?? null, 'patient_days' => $patient['patient_days'] ?? 0, 'visit_days' => $patient['visit_days'] ?? null, 'visit_type' => $patient['visit_type'] ?? null, 'visit_status' => $patient['visit_status'] ?? null]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Patient not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        }
        
        // CHANGE DOCTOR
        if ($action === 'change_doctor') {
            header('Content-Type: application/json');
            
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            $doctor_id = (int)($_POST['doctor_id'] ?? 0);
            $service_id = (int)($_POST['service_id'] ?? 0);
            $symptoms = trim($_POST['symptoms'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $lab_test_ids = isset($_POST['lab_test_ids']) ? $_POST['lab_test_ids'] : [];
            $assignment_type = $_POST['assignment_type'] ?? 'doctor';
            
            if (!is_array($lab_test_ids)) $lab_test_ids = [];
            
            $response = ['success' => false, 'message' => ''];
            
            if ($patient_id <= 0) {
                $response['message'] = 'Please select a patient';
                echo json_encode($response);
                exit;
            }
            
            $is_lab_only = ($assignment_type === 'lab');
            
            if (!$is_lab_only && $doctor_id <= 0) {
                $response['message'] = 'Please select a doctor';
                echo json_encode($response);
                exit;
            }
            
            try {
                $db->beginTransaction();
                
                $service_name = 'General Consultation';
                $consultation_fee = 0;
                
                if ($service_id > 0 && !$is_lab_only) {
                    $stmt = $db->prepare("SELECT id, service_name, price FROM services WHERE id = ? AND is_active = 1");
                    $stmt->execute([$service_id]);
                    $service = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($service) {
                        $service_name = $service['service_name'];
                        $consultation_fee = (float)$service['price'];
                    }
                } else if ($is_lab_only) {
                    $service_name = 'Lab Tests Only';
                }
                
                $doctor_name = 'No Doctor Assigned';
                $doctor_online = 0;
                if ($doctor_id > 0) {
                    $stmt = $db->prepare("SELECT full_name, is_online FROM users WHERE id = ? AND status = 'active'");
                    $stmt->execute([$doctor_id]);
                    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($doctor) {
                        $doctor_name = $doctor['full_name'];
                        $doctor_online = $doctor['is_online'] ?? 0;
                    }
                }
                
                $stmt = $db->prepare("SELECT id, status, doctor_id, visit_number FROM visits WHERE patient_id = ? AND status IN ('new', 'pending', 'assigned', 'with_doctor', 'lab_test') AND branch_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$patient_id, $selected_branch_id]);
                $existing_visit = $stmt->fetch();
                
                $visit_id = null;
                $visit_number = '';
                $visit_type_to_store = $is_lab_only ? 'Lab Tests Only' : $service_name;
                $service_id_to_store = $is_lab_only ? null : ($service_id > 0 ? $service_id : null);
                $doctor_id_to_store = ($is_lab_only) ? null : ($doctor_id > 0 ? $doctor_id : null);
                $visit_status = ($is_lab_only && !empty($lab_test_ids)) ? 'lab_test' : ($is_lab_only ? 'pending' : 'assigned');
                
                if ($existing_visit) {
                    $visit_id = $existing_visit['id'];
                    $visit_number = $existing_visit['visit_number'];
                    $stmt = $db->prepare("UPDATE visits SET doctor_id = ?, status = ?, visit_type = ?, service_id = ?, symptoms = ?, notes = ?, consultation_fee = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$doctor_id_to_store, $visit_status, $visit_type_to_store, $service_id_to_store, $symptoms, $notes, $consultation_fee, $visit_id]);
                } else {
                    $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $stmt = $db->prepare("INSERT INTO visits (visit_number, patient_id, doctor_id, branch_id, visit_type, service_id, status, symptoms, notes, created_at, updated_at, consultation_fee, receptionist_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)");
                    $stmt->execute([$visit_number, $patient_id, $doctor_id_to_store, $selected_branch_id, $visit_type_to_store, $service_id_to_store, $visit_status, $symptoms, $notes, $consultation_fee, $user_id]);
                    $visit_id = $db->lastInsertId();
                }
                
                if ($doctor_id > 0 && !$is_lab_only) {
                    $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = ? WHERE id = ?");
                    $stmt->execute([$doctor_id, $patient_id]);
                } elseif ($is_lab_only) {
                    $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL WHERE id = ?");
                    $stmt->execute([$patient_id]);
                }
                
                $bill_result = null;
                $bill_created = false;
                $bill_number = null;
                $total_lab_fee = 0;
                
                if ($is_lab_only && !empty($lab_test_ids)) {
                    $lab_bill = createLabOnlyBill($db, $patient_id, $visit_id, $lab_test_ids, $user_id, $selected_branch_id);
                    if ($lab_bill && $lab_bill['status'] !== 'error') {
                        $bill_result = $lab_bill;
                        $bill_created = true;
                        $bill_number = $lab_bill['bill_number'];
                        $total_lab_fee = $lab_bill['total_lab_fee'] ?? 0;
                    }
                } elseif (!$is_lab_only && $consultation_fee > 0 && $doctor_id > 0) {
                    $bill_result = createVisitBill($db, $patient_id, $visit_id, $service_name, $consultation_fee, $user_id, $selected_branch_id);
                    if ($bill_result && $bill_result['status'] !== 'error') {
                        $bill_created = true;
                        $bill_number = $bill_result['bill_number'];
                    }
                }
                
                $lab_created = false;
                $lab_test_ids_created = [];
                if (!empty($lab_test_ids)) {
                    $test_ids_imploded = implode(',', array_map('intval', $lab_test_ids));
                    $stmt = $db->prepare("SELECT id, test_name, price FROM lab_tests_catalog WHERE id IN ($test_ids_imploded)");
                    $stmt->execute();
                    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($tests as $test) {
                        $stmt = $db->prepare("INSERT INTO lab_tests (visit_id, patient_id, doctor_id, test_id, test_name, test_price, status, branch_id, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())");
                        $stmt->execute([$visit_id, $patient_id, $is_lab_only ? null : ($doctor_id > 0 ? $doctor_id : null), $test['id'], $test['test_name'], $test['price'], $selected_branch_id]);
                        $lab_created = true;
                        $lab_test_ids_created[] = $test['id'];
                    }
                }
                
                $temperature = $_POST['temperature'] ?? null;
                $bp_systolic = $_POST['bp_systolic'] ?? null;
                $bp_diastolic = $_POST['bp_diastolic'] ?? null;
                $pulse_rate = $_POST['pulse_rate'] ?? null;
                $weight = $_POST['weight'] ?? null;
                $height = $_POST['height'] ?? null;
                $vital_notes = trim($_POST['vital_notes'] ?? '');
                
                $has_vital = ($temperature !== null && $temperature !== '') || ($bp_systolic !== null && $bp_systolic !== '') || ($bp_diastolic !== null && $bp_diastolic !== '') || ($pulse_rate !== null && $pulse_rate !== '') || ($weight !== null && $weight !== '') || ($height !== null && $height !== '');
                
                if ($has_vital && $visit_id) {
                    $bmi = null;
                    if ($weight && $height && $height > 0) {
                        $height_m = $height / 100;
                        $bmi = round($weight / ($height_m * $height_m), 1);
                    }
                    $stmt = $db->prepare("INSERT INTO vital_signs (patient_id, visit_id, recorded_by, branch_id, temperature, blood_pressure_systolic, blood_pressure_diastolic, pulse_rate, weight, height, bmi, notes, recorded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$patient_id, $visit_id, $user_id, $selected_branch_id, $temperature ?: null, $bp_systolic ?: null, $bp_diastolic ?: null, $pulse_rate ?: null, $weight ?: null, $height ?: null, $bmi, $vital_notes ?: null]);
                }
                
                $db->commit();
                
                $bill_message = '';
                if ($bill_created && $bill_number) {
                    $bill_message = ' 💰 Bill #' . $bill_number . ' sent to Cashier!';
                }
                
                $lab_text = '';
                if ($lab_created) {
                    $lab_text = ' 🧪 ' . count($lab_test_ids) . ' lab test(s) requested!';
                    if ($total_lab_fee > 0) $lab_text .= ' (TSh ' . number_format($total_lab_fee, 0) . ')';
                }
                
                $doctor_text = '';
                if ($doctor_id > 0 && !$is_lab_only) {
                    $online_text = $doctor_online == 1 ? '🟢 Online' : '⚪ Offline';
                    $doctor_text = "Doctor <strong>$doctor_name</strong> ($online_text) assigned - $service_name";
                } else if ($is_lab_only && !empty($lab_test_ids)) {
                    $doctor_text = "🧪 Lab tests requested - No doctor assigned";
                } else {
                    $doctor_text = "✅ Patient processed";
                }
                
                $response['success'] = true;
                $response['message'] = "✅ $doctor_text! Visit: $visit_number" . $bill_message . $lab_text;
                $response['visit_number'] = $visit_number;
                $response['doctor_name'] = $doctor_name;
                $response['patient_id'] = $patient_id;
                $response['bill'] = $bill_result;
                $response['service_name'] = $service_name;
                $response['doctor_online'] = $doctor_online;
                $response['bill_sent_to_cashier'] = $bill_created;
                $response['bill_number'] = $bill_number;
                $response['lab_tests_added'] = $lab_created;
                $response['is_lab_only'] = $is_lab_only;
                $response['has_doctor'] = ($doctor_id > 0 && !$is_lab_only);
                $response['total_lab_fee'] = $total_lab_fee;
                $response['lab_test_ids'] = $lab_test_ids_created;
                
            } catch (Exception $e) {
                $db->rollBack();
                $response['message'] = '❌ Error: ' . $e->getMessage();
            }
            
            echo json_encode($response);
            exit;
        }
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $all_patients = [];
    $pending_patients = [];
    $assigned_patients = [];
    $lab_only_patients = [];
    $doctors = [];
    $online_doctors = [];
    $offline_doctors = [];
    $visit_type_options = [];
    $pending_count = 0;
    $assigned_count = 0;
    $lab_only_count = 0;
    $unread_notifications = 0;
}

$common_symptoms = [
    'Fever', 'Headache', 'Cough', 'Sore Throat', 'Body Pain',
    'Fatigue', 'Nausea', 'Vomiting', 'Diarrhea', 'Chest Pain',
    'Shortness of Breath', 'Abdominal Pain', 'Dizziness', 'Rash', 'Swelling'
];

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Doctor - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563EB;
            --primary-dark: #1D4ED8;
            --primary-light: #60A5FA;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #2563EB, #1D4ED8);
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-dark: #5B21B6;
            --purple-bg: #EDE9FE;
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
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-bg: #1E3A5F;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* PAGE HEADER */
        .page-header {
            background: var(--primary-gradient);
            border-radius: var(--radius-lg);
            padding: 22px 30px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 8px 32px rgba(37, 99, 235, 0.25);
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
        
        .page-header .page-title i { font-size: 1.5rem; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 3px;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            backdrop-filter: blur(4px);
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.12);
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
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 16px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.75rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        /* FORM CARD */
        .form-card-modern {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 28px 32px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            max-width: 1300px;
            margin: 0 auto;
            box-shadow: var(--shadow-md);
        }
        
        .form-card-modern:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-lg);
        }
        
        .form-card-modern .form-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .form-card-modern .form-header .form-icon {
            width: 52px;
            height: 52px;
            background: var(--primary-gradient);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            flex-shrink: 0;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.2);
        }
        
        .form-card-modern .form-header .form-title {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-card-modern .form-header .form-subtitle {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        /* 6 CARDS GRID */
        .form-grid-6 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }
        
        .form-grid-left, .form-grid-right {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        
        /* CARD ITEM - BIGGER */
        .form-card-item {
            background: var(--bg-body);
            border-radius: var(--radius);
            padding: 20px 22px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            min-height: 160px;
            display: flex;
            flex-direction: column;
        }
        
        .form-card-item:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-sm);
        }
        
        .form-card-item .card-item-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .form-card-item .card-item-title i {
            color: var(--primary);
            font-size: 0.9rem;
        }
        
        .form-card-item .card-item-title .required {
            color: var(--danger);
            margin-left: 2px;
        }
        
        .form-card-item .card-item-title .badge-label {
            font-size: 0.55rem;
            font-weight: 400;
            padding: 2px 10px;
            border-radius: 10px;
            background: var(--gray-200);
            color: var(--text-secondary);
            margin-left: 4px;
        }
        
        /* FORM CONTROLS */
        .form-control-modern {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.85rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
            min-height: 44px;
        }
        
        .form-control-modern:hover {
            border-color: var(--primary-light);
        }
        
        .form-control-modern:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
        }
        
        .form-control-modern optgroup {
            font-weight: 700;
            color: var(--text-primary);
            padding: 6px 0;
            font-size: 0.9rem;
        }
        
        .form-control-modern option {
            padding: 6px 10px;
            font-size: 0.85rem;
            color: var(--text-primary);
            line-height: 1.6;
        }
        
        .form-control-modern.textarea {
            min-height: 80px;
            resize: vertical;
            font-family: inherit;
        }
        
        /* LAB SECTION - BIGGER */
        .lab-modal-container-modern {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 2px solid var(--purple);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            margin-top: 8px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .lab-modal-header-modern {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 18px;
            background: var(--purple-bg);
            border-bottom: 2px solid var(--border-color);
        }
        
        .lab-modal-header-modern .lab-modal-title {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .lab-test-scroll {
            max-height: 320px;
            overflow-y: auto;
            padding: 4px 0;
        }
        
        .lab-test-item-modern {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 18px;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s ease;
            cursor: pointer;
        }
        
        .lab-test-item-modern:hover {
            background: var(--primary-bg);
        }
        
        .lab-test-item-modern:last-child {
            border-bottom: none;
        }
        
        .lab-test-item-modern .lab-test-checkbox {
            width: 18px;
            height: 18px;
            accent-color: var(--purple);
            cursor: pointer;
            flex-shrink: 0;
        }
        
        .lab-test-item-modern label {
            cursor: pointer;
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            font-size: 0.85rem;
        }
        
        .lab-test-item-modern label strong {
            font-size: 0.85rem;
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .lab-test-item-modern .lab-test-category {
            font-size: 0.55rem;
            background: var(--gray-200);
            color: var(--text-secondary);
            padding: 2px 10px;
            border-radius: 10px;
        }
        
        .lab-test-item-modern .lab-test-price {
            font-size: 0.8rem;
            color: var(--success);
            font-weight: 700;
            white-space: nowrap;
            margin-left: auto;
        }
        
        .lab-test-item-modern.checked {
            background: var(--purple-bg);
            border-left: 4px solid var(--purple);
        }
        
        .lab-modal-footer-modern {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 18px;
            border-top: 2px solid var(--border-color);
            background: var(--bg-body);
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .lab-modal-footer-modern .lab-total-price {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--success);
            padding: 4px 14px;
            background: var(--success-bg);
            border-radius: 20px;
        }
        
        .lab-selected-summary-modern {
            padding: 10px 16px;
            background: var(--success-bg);
            border-radius: var(--radius);
            margin-top: 8px;
            border: 1px solid var(--success);
            font-size: 0.75rem;
        }
        
        /* VITAL SIGNS */
        .vital-grid-modern {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        
        .vital-item-modern {
            background: var(--bg-body);
            border-radius: var(--radius);
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .vital-item-modern .vital-label {
            font-size: 0.6rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
            margin-bottom: 2px;
        }
        
        .vital-item-modern .vital-input {
            border: none;
            background: transparent;
            padding: 4px 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            outline: none;
            width: 100%;
        }
        
        .vital-item-modern .vital-unit { 
            font-size: 0.55rem; 
            color: var(--text-secondary); 
            display: block; 
            font-weight: 500;
        }
        
        .vital-item-modern.bmi-item { 
            background: var(--primary-bg); 
            border-color: var(--primary); 
        }
        
        /* BUTTONS */
        .btn-modern {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
            min-height: 44px;
        }
        
        .btn-modern-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        
        .btn-modern-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.3);
        }
        
        .btn-modern-warning {
            background: var(--warning);
            color: white;
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.2);
        }
        
        .btn-modern-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(217, 119, 6, 0.3);
        }
        
        .btn-modern-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-modern-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-modern-sm { 
            padding: 6px 16px; 
            font-size: 0.7rem; 
            min-height: 34px; 
            border-radius: 8px; 
        }
        
        .btn-modern-purple { 
            background: var(--purple); 
            color: white; 
        }
        
        .btn-modern-purple:hover { 
            background: var(--purple-dark); 
        }
        
        .form-actions-modern {
            display: flex;
            gap: 12px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        /* STATUS BADGES */
        .status-badge-dropdown {
            display: inline-block;
            font-size: 0.55rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 10px;
            margin-left: 6px;
        }
        .status-badge-dropdown.pending { background: #FEF3C7; color: #D97706; }
        .status-badge-dropdown.assigned { background: #D1FAE5; color: #059669; }
        .status-badge-dropdown.lab_only { background: #EDE9FE; color: #7C3AED; border: 1px dashed #7C3AED; }
        .status-badge-dropdown.no_visit { background: var(--gray-200); color: var(--gray-600); }
        
        /* DAYS BADGES */
        .days-badge-blue {
            display: inline-block;
            background: var(--primary) !important;
            color: #ffffff !important;
            padding: 2px 10px !important;
            border-radius: 10px !important;
            font-size: 0.6rem !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }
        .days-badge-blue.new {
            background: var(--success) !important;
        }
        .assigned-days-badge-blue {
            display: inline-block;
            background: var(--primary) !important;
            color: #ffffff !important;
            padding: 2px 10px !important;
            border-radius: 10px !important;
            font-size: 0.6rem !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }
        .assigned-days-badge-blue.new {
            background: var(--success) !important;
        }
        
        /* ASSIGNED PATIENTS TABLE */
        .modern-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
        }
        
        .modern-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .modern-card .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .modern-card .card-badge {
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        /* TOAST */
        .toast-modern {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 16px 24px;
            border-radius: var(--radius);
            z-index: 9999;
            max-width: 420px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-lg);
            font-size: 0.85rem;
        }
        
        .toast-modern.show { transform: translateY(0); opacity: 1; }
        .toast-modern.success { background: var(--success); }
        .toast-modern.error { background: var(--danger); }
        .toast-modern.info { background: var(--primary); }
        .toast-modern.warning { background: var(--warning); }
        
        .live-indicator-modern {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34D399;
            animation: pulse-dot 1.5s infinite;
            margin-right: 4px;
        }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.8); }
        }
        
        .footer-modern {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer-modern .footer-brand { color: var(--primary); font-weight: 500; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
        
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .auto-remove-notification {
            background: var(--success-bg);
            border: 2px solid var(--success);
            border-radius: var(--radius);
            padding: 10px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.75rem;
            color: var(--success);
            transition: all 0.5s ease;
            max-width: 1300px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .auto-remove-notification.hide {
            opacity: 0;
            transform: translateY(-20px);
            max-height: 0;
            padding: 0 18px;
            margin-bottom: 0;
            overflow: hidden;
            border: none;
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .form-card-modern { padding: 20px; }
            .form-grid-6 { grid-template-columns: 1fr; gap: 16px; }
        }
        
        @media (max-width: 768px) {
            .form-card-modern { padding: 14px; }
            .page-header { padding: 14px 16px; }
            .page-header .page-title { font-size: 1.1rem; }
            .vital-grid-modern { grid-template-columns: repeat(2, 1fr); }
            .form-actions-modern { flex-direction: column; }
            .form-actions-modern .btn-modern { width: 100%; justify-content: center; }
            .form-card-item { min-height: 130px; padding: 14px 16px; }
            .lab-test-item-modern { padding: 8px 14px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .form-card-modern { padding: 10px; }
            .vital-grid-modern { grid-template-columns: 1fr 1fr; }
            .page-header .header-badge { font-size: 0.5rem; padding: 2px 8px; }
            .form-card-item { min-height: 100px; padding: 12px 14px; }
            .form-card-item .card-item-title { font-size: 0.7rem; }
            .form-control-modern { font-size: 0.75rem; padding: 8px 12px; min-height: 38px; }
            .lab-test-item-modern { padding: 6px 10px; }
            .lab-test-item-modern label strong { font-size: 0.75rem; }
        }
    </style>
</head>
<body>

<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search patients..." value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime">
            <i class="fas fa-clock" style="color:var(--primary-light);"></i>
            <span id="clockDisplay" style="font-weight:500;font-size:0.8rem;"><?= date('d M Y • h:i:s A') ?></span>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText" style="font-size:0.75rem;">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-md"></i>
                Assign / Change Doctor
                <span class="role-badge-display">RECEPTION</span>
                <span class="update-badge-light" style="background:rgba(255,255,255,0.12);color:white;padding:3px 12px;border-radius:20px;font-size:0.6rem;">
                    <span class="live-indicator-modern"></span> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                Select patient, assign doctor or request lab tests
                
                <span class="header-badge">
                    <i class="fas fa-user-md"></i>
                    <span id="onlineDoctorCount"><?= $online_doctors_count ?></span> Online
                </span>
                <span class="header-badge">
                    <i class="fas fa-user-md"></i>
                    <span id="offlineDoctorCount"><?= $offline_doctors_count ?></span> Offline
                </span>
                <span class="header-badge">
                    <i class="fas fa-user-clock"></i>
                    <span id="pendingCount"><?= $pending_count ?></span> Pending
                </span>
                <span class="header-badge">
                    <i class="fas fa-user-check"></i>
                    <span id="assignedCount"><?= $assigned_count ?></span> Assigned
                </span>
                <span class="header-badge" style="background:rgba(124,58,237,0.2);border-color:rgba(124,58,237,0.3);color:#A78BFA;">
                    <i class="fas fa-flask"></i>
                    <span id="labOnlyCount"><?= $lab_only_count ?></span> Lab Only
                </span>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div style="max-width:1300px;margin:0 auto 16px;padding:12px 18px;border-radius:var(--radius);background:<?= $message_type === 'success' ? 'var(--success-bg)' : 'var(--danger-bg)' ?>;color:<?= $message_type === 'success' ? 'var(--success)' : 'var(--danger)' ?>;border:2px solid <?= $message_type === 'success' ? 'var(--success)' : 'var(--danger)' ?>;display:flex;align-items:center;gap:8px;font-size:0.85rem;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- AUTO-REMOVE NOTIFICATION -->
    <div class="auto-remove-notification" id="autoRemoveNotification">
        <i class="fas fa-check-circle" style="color:var(--success);font-size:1rem;"></i>
        <span style="font-weight:500;color:var(--text-primary);font-size:0.75rem;">
            ✅ Patients with completed consultations or completed lab results are <strong>automatically removed</strong> from these lists.
        </span>
        <span style="font-size:0.65rem;color:var(--text-secondary);margin-left:auto;">
            <i class="fas fa-info-circle"></i> Only active patients shown
        </span>
    </div>

    <!-- ASSIGNED PATIENTS LIST -->
    <div class="modern-card animate-fade-in-up" style="max-width:1300px;margin:0 auto 20px;">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-user-check"></i>
                Assigned Patients (With Doctor)
                <span class="card-badge" style="background:var(--success-bg);color:var(--success);" id="assignedListCount"><?= $assigned_count ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
                <span class="text-xs text-gray-400" style="font-size:0.65rem;" id="assignedListUpdate">(Auto-updated <?= date('h:i:s A') ?>)</span>
                <span class="text-xs text-green-500" style="font-size:0.65rem;">
                    <span class="live-indicator-modern"></span> Live
                </span>
            </div>
        </div>
        
        <div id="assignedPatientsList">
            <?php if (count($assigned_patients) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                        <thead>
                            <tr style="border-bottom:2px solid var(--border-color);">
                                <th style="padding:10px 12px;text-align:left;font-weight:600;font-size:0.65rem;text-transform:uppercase;color:var(--text-secondary);">Patient / Service</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:600;font-size:0.65rem;text-transform:uppercase;color:var(--text-secondary);">Patient ID</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:600;font-size:0.65rem;text-transform:uppercase;color:var(--text-secondary);">Assigned Doctor</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:600;font-size:0.65rem;text-transform:uppercase;color:var(--text-secondary);">Status</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:600;font-size:0.65rem;text-transform:uppercase;color:var(--text-secondary);">Action</th>
                            </tr>
                        </thead>
                        <tbody id="assignedPatientsTableBody">
                            <?php foreach ($assigned_patients as $patient): 
                                $assigned_days = 0;
                                if (!empty($patient['visit_created_at'])) {
                                    $assigned_days = (int)floor((time() - strtotime($patient['visit_created_at'])) / 86400);
                                }
                                $days_text = $assigned_days > 0 ? '<span class="assigned-days-badge-blue">' . $assigned_days . ' days</span>' : '<span class="assigned-days-badge-blue new">Just assigned</span>';
                            ?>
                                <tr id="assigned-row-<?= $patient['id'] ?>" style="border-bottom:1px solid var(--border-color);">
                                    <td style="padding:10px 12px;font-weight:500;font-size:0.85rem;">
                                        <?= htmlspecialchars($patient['full_name']) ?>
                                        <?= $days_text ?>
                                        <span class="text-xs text-gray-400 block" style="font-size:0.7rem;"><?= htmlspecialchars($patient['visit_type'] ?? 'Consultation') ?></span>
                                    </td>
                                    <td style="padding:10px 12px;font-family:monospace;font-size:0.8rem;"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                                    <td style="padding:10px 12px;">
                                        <?php if (!empty($patient['assigned_doctor_name'])): ?>
                                            <span style="font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;background:var(--primary-bg);padding:4px 12px;border-radius:12px;border:1px solid var(--primary-light);">
                                                <i class="fas fa-user-md" style="color:var(--primary);"></i>
                                                Dr. <?= htmlspecialchars($patient['assigned_doctor_name']) ?>
                                                <?php if ($patient['assigned_doctor_online'] == 1): ?>
                                                    <span style="color:#059669;">🟢</span>
                                                <?php else: ?>
                                                    <span style="color:var(--gray-400);">⚪</span>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">No doctor</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 12px;">
                                        <span class="status-badge-dropdown assigned">✅ Assigned</span>
                                    </td>
                                    <td style="padding:10px 12px;">
                                        <button onclick="selectPatientAndChange(<?= $patient['id'] ?>)" class="btn-modern btn-modern-warning btn-modern-sm">
                                            <i class="fas fa-sync-alt"></i> Change
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4 text-gray-400" id="noAssignedPatients">
                    <i class="fas fa-user-check text-2xl block mb-2" style="font-size:1.5rem;"></i>
                    <p style="font-size:0.85rem;">No patients currently assigned to a doctor</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- LAB TESTS CARD -->
    <div class="modern-card animate-fade-in-up" style="animation-delay:0.15s;max-width:1300px;margin:0 auto 20px;border-color:var(--purple);">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-flask" style="color:var(--purple);"></i>
                Lab Test Requests (No Doctor)
                <span class="card-badge" style="background:var(--purple-bg);color:var(--purple);" id="labOnlyListCount"><?= $lab_only_count ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
                <span class="text-xs text-gray-400" style="font-size:0.65rem;" id="labOnlyUpdate">(Auto-updated <?= date('h:i:s A') ?>)</span>
                <span class="text-xs text-purple-500" style="font-size:0.65rem;">
                    <span class="live-indicator-modern"></span> Live
                </span>
            </div>
        </div>
        
        <div id="labOnlyPatientsList">
            <?php if (count($lab_only_patients) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                        <thead>
                            <tr style="border-bottom:2px solid var(--border-color);">
                                <th style="padding:10px 12px;text-align:left;font-weight:600;font-size:0.65rem;text-transform:uppercase;color:var(--text-secondary);">Patient / Days</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:600;font-size:0.65rem;text-transform:uppercase;color:var(--text-secondary);">Patient ID</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:600;font-size:0.65rem;text-transform:uppercase;color:var(--text-secondary);">Type</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:600;font-size:0.65rem;text-transform:uppercase;color:var(--text-secondary);">Status</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:600;font-size:0.65rem;text-transform:uppercase;color:var(--text-secondary);">Action</th>
                            </tr>
                        </thead>
                        <tbody id="labOnlyTableBody">
                            <?php foreach ($lab_only_patients as $patient): 
                                $lab_days = 0;
                                if (!empty($patient['visit_created_at'])) {
                                    $lab_days = (int)floor((time() - strtotime($patient['visit_created_at'])) / 86400);
                                }
                                $days_text = $lab_days > 0 ? '<span class="assigned-days-badge-blue">' . $lab_days . ' days</span>' : '<span class="assigned-days-badge-blue new">Just requested</span>';
                                
                                $lab_test_id = 0;
                                $lab_test_name = '';
                                try {
                                    $stmt = $db->prepare("SELECT id, test_name FROM lab_tests WHERE patient_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
                                    $stmt->execute([$patient['id']]);
                                    $lab_result = $stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($lab_result) { $lab_test_id = $lab_result['id']; $lab_test_name = $lab_result['test_name']; }
                                } catch (Exception $e) {}
                                
                                $view_link = $lab_test_id > 0 ? '<a href="lab_tests.php?id=' . $lab_test_id . '" class="btn-modern btn-modern-purple btn-modern-sm" style="text-decoration:none;"><i class="fas fa-eye"></i> View</a>' : '<span class="text-gray-400 text-xs">No test</span>';
                            ?>
                                <tr id="lab-row-<?= $patient['id'] ?>" style="border-bottom:1px solid var(--border-color);">
                                    <td style="padding:10px 12px;font-weight:500;font-size:0.85rem;">
                                        <?= htmlspecialchars($patient['full_name']) ?>
                                        <?= $days_text ?>
                                        <?php if ($lab_test_id > 0): ?>
                                            <span class="text-xs text-purple-500 block" style="font-size:0.7rem;"><?= htmlspecialchars($lab_test_name) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 12px;font-family:monospace;font-size:0.8rem;"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                                    <td style="padding:10px 12px;">
                                        <span style="font-size:0.75rem;display:inline-flex;align-items:center;gap:6px;background:var(--purple-bg);padding:4px 12px;border-radius:12px;border:1px dashed var(--purple);">
                                            <i class="fas fa-flask" style="color:var(--purple);"></i> Lab Only 🧪
                                        </span>
                                    </td>
                                    <td style="padding:10px 12px;">
                                        <span class="status-badge-dropdown lab_only">🧪 Pending</span>
                                    </td>
                                    <td style="padding:10px 12px;">
                                        <?= $view_link ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4 text-gray-400" id="noLabPatients">
                    <i class="fas fa-flask text-2xl block mb-2" style="font-size:1.5rem;color:var(--purple);"></i>
                    <p style="font-size:0.85rem;">No lab test requests pending</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ASSIGN FORM -->
    <div class="form-card-modern animate-fade-in-up <?= $change_mode ? 'change-mode-active-modern' : '' ?>" id="mainFormCard" style="animation-delay:0.1s;">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas <?= $change_mode ? 'fa-sync-alt' : 'fa-stethoscope' ?>"></i>
            </div>
            <div>
                <h3 class="form-title">
                    <?= $change_mode ? '🔄 Change Doctor' : 'Assign / Change Doctor or Lab Test' ?>
                    <?php if ($change_mode && $selected_patient_data): ?>
                        <span style="font-weight:400;font-size:0.75rem;color:var(--warning);">
                            - Changing: <?= htmlspecialchars($selected_patient_data['full_name']) ?>
                        </span>
                    <?php endif; ?>
                </h3>
                <p class="form-subtitle">
                    <?php if ($change_mode): ?>
                        <span style="color:var(--warning);">🔄 Change Mode:</span> Select new doctor for patient
                    <?php else: ?>
                        Select patient and assign a doctor OR request lab tests
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <form method="POST" action="" id="assignForm">
            <input type="hidden" name="action" value="change_doctor">
            
            <div class="form-grid-6">
                
                <!-- LEFT SIDE -->
                <div class="form-grid-left">
                    
                    <!-- CARD 1: Select Patient -->
                    <div class="form-card-item">
                        <div class="card-item-title">
                            <i class="fas fa-user"></i> Select Patient <span class="required">*</span>
                            <span class="badge-label">All Patients - Newest First</span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            <select name="patient_id" class="form-control-modern" required id="patientSelect">
                                <option value="">-- Select Patient --</option>
                                <?php if (!empty($all_patients) && count($all_patients) > 0): ?>
                                    <optgroup label="📋 All Patients (<?= count($all_patients) ?>)">
                                        <?php foreach ($all_patients as $patient): 
                                            $status_label = '📋 No Visit';
                                            $status_class = 'no_visit';
                                            $status_icon = '📋';
                                            
                                            if (!empty($patient['visit_id'])) {
                                                if ($patient['visit_status'] === 'lab_test' && empty($patient['visit_doctor_id'])) {
                                                    $status_label = '🧪 Lab Only'; $status_class = 'lab_only'; $status_icon = '🧪';
                                                } elseif (in_array($patient['visit_status'], ['new', 'pending'])) {
                                                    $status_label = '⏳ Pending'; $status_class = 'pending'; $status_icon = '⏳';
                                                } elseif ($patient['visit_status'] === 'assigned' || $patient['visit_status'] === 'with_doctor') {
                                                    $status_label = '✅ Assigned'; $status_class = 'assigned'; $status_icon = '✅';
                                                }
                                            }
                                            
                                            $doctor_info = '';
                                            if (!empty($patient['assigned_doctor_name'])) {
                                                $online_status = !empty($patient['assigned_doctor_online']) ? '🟢' : '⚪';
                                                $doctor_info = ' 👨‍⚕️ Dr. ' . htmlspecialchars($patient['assigned_doctor_name']) . ' ' . $online_status;
                                            }
                                            
                                            $selected = ($selected_patient_id == $patient['id']) ? 'selected' : '';
                                            $days = (int)($patient['patient_days'] ?? 0);
                                            $days_text = $days > 0 ? '<span class="days-badge-blue">📅 ' . $days . ' days</span>' : '<span class="days-badge-blue new">📅 New</span>';
                                        ?>
                                            <option value="<?= $patient['id'] ?>" data-status="<?= $status_class ?>" <?= $selected ?>>
                                                <?= $status_icon ?> <?= htmlspecialchars($patient['full_name']) ?> (<?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>)
                                                <?= $days_text ?>
                                                <?= $doctor_info ?>
                                                <span class="status-badge-dropdown <?= $status_class ?>"><?= $status_label ?></span>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php else: ?>
                                    <option value="" disabled>No patients found</option>
                                <?php endif; ?>
                            </select>
                            
                            <div style="margin-top:6px;font-size:0.65rem;color:var(--text-secondary);" id="patientStats">
                                <span style="color:var(--warning);">🟡 <span id="pendingStat"><?= $pending_count ?></span> Pending</span>
                                <span class="mx-1">|</span>
                                <span style="color:var(--success);">✅ <span id="assignedStat"><?= $assigned_count ?></span> Assigned</span>
                                <span class="mx-1">|</span>
                                <span style="color:var(--purple);">🧪 <span id="labOnlyStat"><?= $lab_only_count ?></span> Lab Only</span>
                                <span class="mx-1">|</span>
                                <span>Total: <?= count($all_patients) ?></span>
                            </div>
                            
                            <div id="selectedPatientInfo" style="display:<?= $selected_patient_id > 0 && $selected_patient_data ? 'block' : 'none' ?>;margin-top:8px;padding:10px 14px;background:var(--primary-bg);border-radius:var(--radius);border:1px solid var(--primary-light);">
                                <?php if ($selected_patient_data): 
                                    $patient_days = (int)($selected_patient_data['patient_days'] ?? 0);
                                    $days_text = $patient_days > 0 ? '<span class="days-badge-blue">📅 ' . $patient_days . ' days ago</span>' : '<span class="days-badge-blue new">📅 Just registered</span>';
                                ?>
                                    <div style="display:flex;align-items:center;gap:8px;font-size:0.75rem;flex-wrap:wrap;">
                                        <i class="fas fa-user-circle" style="color:var(--primary);"></i>
                                        <span style="font-weight:600;"><?= htmlspecialchars($selected_patient_data['full_name'] ?? '') ?></span>
                                        <span style="color:var(--text-secondary);">|</span>
                                        <span><?= htmlspecialchars($selected_patient_data['patient_id'] ?? '') ?></span>
                                        <?= $days_text ?>
                                        <?php if (!empty($selected_patient_data['assigned_doctor_name'])): ?>
                                            <span style="background:var(--primary-bg);padding:3px 10px;border-radius:12px;border:1px solid var(--primary-light);font-size:0.7rem;">
                                                <i class="fas fa-user-md" style="color:var(--primary);"></i>
                                                Dr. <?= htmlspecialchars($selected_patient_data['assigned_doctor_name']) ?>
                                                <?php if ($selected_patient_data['assigned_doctor_online'] == 1): ?>
                                                    <span style="color:#059669;">🟢</span>
                                                <?php else: ?>
                                                    <span style="color:var(--gray-400);">⚪</span>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- CARD 2: Select Action -->
                    <div class="form-card-item">
                        <div class="card-item-title">
                            <i class="fas fa-tasks"></i> Select Action <span class="required">*</span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            <select name="assignment_type" class="form-control-modern" required id="assignmentTypeSelect" onchange="toggleAssignmentType(this.value)">
                                <option value="doctor" <?= $change_mode ? 'selected' : '' ?>>👨‍⚕️ Assign Doctor</option>
                                <option value="lab">🧪 Request Lab Test(s) (No Doctor)</option>
                            </select>
                            <p style="font-size:0.65rem;color:var(--text-secondary);margin-top:6px;" id="assignmentTypeHelp">👨‍⚕️ Assign a doctor to the patient or change existing doctor</p>
                        </div>
                    </div>
                    
                    <!-- CARD 3: Select Doctor (HIDDEN IN LAB MODE) -->
                    <div class="form-card-item" id="doctorSelectCard">
                        <div class="card-item-title">
                            <i class="fas fa-user-md"></i> Select Doctor <span class="required" id="doctorRequired">*</span>
                            <span class="badge-label">Online/Offline</span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            <select name="doctor_id" class="form-control-modern" required id="doctorSelect">
                                <option value="">-- Select Doctor --</option>
                                <?php if (!empty($online_doctors)): ?>
                                    <optgroup label="🟢 Online Doctors (<?= $online_doctors_count ?>)">
                                        <?php foreach ($online_doctors as $doctor): ?>
                                            <option value="<?= $doctor['id'] ?>" data-online="1">🟢 Dr. <?= htmlspecialchars($doctor['full_name']) ?><?= !empty($doctor['specialty']) ? ' (' . htmlspecialchars($doctor['specialty']) . ')' : '' ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php if (!empty($offline_doctors)): ?>
                                    <optgroup label="⚪ Offline Doctors (<?= $offline_doctors_count ?>)">
                                        <?php foreach ($offline_doctors as $doctor): ?>
                                            <option value="<?= $doctor['id'] ?>" data-online="0">⚪ Dr. <?= htmlspecialchars($doctor['full_name']) ?><?= !empty($doctor['specialty']) ? ' (' . htmlspecialchars($doctor['specialty']) . ')' : '' ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                            
                            <p style="font-size:0.65rem;color:var(--text-secondary);margin-top:6px;" id="doctorAvailability">
                                <i class="fas fa-info-circle mr-1"></i>
                                <span style="color:var(--success);" id="onlineCountDisplay">🟢 <?= $online_doctors_count ?> online</span>
                                <span style="margin:0 4px;">|</span>
                                <span id="offlineCountDisplay">⚪ <?= $offline_doctors_count ?> offline</span>
                            </p>
                        </div>
                    </div>
                    
                </div>
                
                <!-- RIGHT SIDE -->
                <div class="form-grid-right">
                    
                    <!-- CARD 4: Visit Type -->
                    <div class="form-card-item" id="visitTypeSection">
                        <div class="card-item-title">
                            <i class="fas fa-tag"></i> Visit Type (Service) <span class="required">*</span>
                            <span class="badge-label" id="visitTypePrice">
                                Fee: TSh <?= isset($visit_type_options[$default_service_id]) ? number_format($visit_type_options[$default_service_id]['price'] ?? 0, 0) : '0' ?>
                            </span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            <select name="service_id" class="form-control-modern" id="visitTypeSelect" onchange="updateVisitTypePrice()">
                                <?php if (!empty($visit_type_options)): ?>
                                    <?php foreach ($visit_type_options as $service_id => $option): 
                                        $selected = ($service_id === $default_service_id) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $service_id ?>" data-price="<?= $option['price'] ?? 0 ?>" <?= $selected ?>>
                                            <?= $option['icon'] ?? '🏥' ?> <?= htmlspecialchars($option['service_name']) ?> - TSh <?= number_format($option['price'] ?? 0, 0) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" data-price="0" selected disabled>❌ No Visit Type Available</option>
                                <?php endif; ?>
                            </select>
                            <p style="font-size:0.65rem;color:var(--text-secondary);margin-top:6px;" id="visitTypeDescription">
                                <i class="fas fa-info-circle mr-1"></i>
                                <?= isset($visit_type_options[$default_service_id]) ? ($visit_type_options[$default_service_id]['description'] ?? 'Select a visit type') : 'No consultation services available' ?>
                            </p>
                            <div id="feeNote" style="margin-top:4px;font-size:0.65rem;color:var(--primary);">
                                👨‍⚕️ Consultation Mode: Doctor required, Consultation fee applies
                            </div>
                        </div>
                    </div>
                    
                    <!-- CARD 5: Symptoms -->
                    <div class="form-card-item">
                        <div class="card-item-title">
                            <i class="fas fa-notes-medical"></i> Symptoms
                            <span class="badge-label">Reception fills this</span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;gap:8px;">
                            <select name="symptoms_select" class="form-control-modern" id="symptomsSelect" style="min-height:38px;">
                                <option value="">-- Select Common Symptom --</option>
                                <?php foreach ($common_symptoms as $symptom): ?>
                                    <option value="<?= htmlspecialchars($symptom) ?>"><?= htmlspecialchars($symptom) ?></option>
                                <?php endforeach; ?>
                                <option value="other">✏️ Other (Type below)</option>
                            </select>
                            <textarea name="symptoms" class="form-control-modern textarea" placeholder="Describe patient symptoms in detail..." id="symptomsTextarea" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <!-- CARD 6: Lab Tests (Hidden by default) - BIGGER -->
                    <div class="form-card-item" id="labSection" style="display:none;">
                        <div class="card-item-title">
                            <i class="fas fa-flask" style="color:var(--purple);"></i> Select Lab Tests
                            <span class="badge-label" id="labSelectedCount">(0 selected)</span>
                            <span class="badge-label" style="background:var(--purple);color:white;border-radius:10px;padding:2px 10px;font-size:0.55rem;">🧪 Lab Only</span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            <div class="lab-modal-container-modern">
                                <div class="lab-modal-header-modern">
                                    <div class="lab-modal-title">
                                        <i class="fas fa-flask" style="color:var(--purple);"></i>
                                        Available Lab Tests
                                        <span style="font-size:0.7rem;color:var(--text-secondary);font-weight:400;">(<?= count($lab_tests_catalog) ?> tests)</span>
                                    </div>
                                    <button type="button" onclick="closeLabTests()" style="background:none;border:none;color:var(--text-secondary);cursor:pointer;font-size:0.9rem;padding:4px 8px;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                
                                <div class="lab-test-scroll" id="labTestsContainer">
                                    <?php if (!empty($lab_tests_catalog)): ?>
                                        <?php foreach ($lab_tests_catalog as $test): ?>
                                            <div class="lab-test-item-modern">
                                                <input type="checkbox" name="lab_test_ids[]" value="<?= $test['id'] ?>" id="lab_test_<?= $test['id'] ?>" class="lab-test-checkbox" onchange="updateLabSelection(this)">
                                                <label for="lab_test_<?= $test['id'] ?>">
                                                    <strong><?= htmlspecialchars($test['test_name']) ?></strong>
                                                    <?php if (!empty($test['category'])): ?>
                                                        <span class="lab-test-category"><?= htmlspecialchars($test['category']) ?></span>
                                                    <?php endif; ?>
                                                </label>
                                                <span class="lab-test-price">TSh <?= number_format($test['price'] ?? 0, 0) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-4 text-gray-400">
                                            <i class="fas fa-flask" style="font-size:1.5rem;"></i>
                                            <p style="font-size:0.8rem;">No lab tests available</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="lab-modal-footer-modern">
                                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                        <button type="button" class="btn-modern btn-modern-outline btn-modern-sm" onclick="selectAllLabTests()">
                                            <i class="fas fa-check-double"></i> Select All
                                        </button>
                                        <button type="button" class="btn-modern btn-modern-outline btn-modern-sm" onclick="deselectAllLabTests()">
                                            <i class="fas fa-times"></i> Clear All
                                        </button>
                                        <span class="lab-total-price" id="labTotalPrice">Total: TSh 0</span>
                                        <span style="font-size:0.7rem;color:var(--text-secondary);" id="labSelectionCount">(0 tests)</span>
                                    </div>
                                    <button type="button" class="btn-modern btn-modern-primary btn-modern-sm" onclick="closeLabTests()" style="background:var(--danger);">
                                        <i class="fas fa-times"></i> Close
                                    </button>
                                </div>
                            </div>
                            
                            <div class="lab-selected-summary-modern" id="labSelectedSummary" style="display:none;">
                                <span style="font-weight:600;color:var(--primary);">
                                    <i class="fas fa-check-circle"></i> Selected:
                                </span>
                                <span id="labSelectedNames" style="color:var(--text-primary);"></span>
                                <span style="font-size:0.7rem;color:var(--text-secondary);margin-left:6px;">
                                    (Bill sent to Cashier)
                                </span>
                            </div>
                            
                            <input type="hidden" name="lab_test_ids" id="selectedLabTestsInput" value="">
                        </div>
                    </div>
                    
                </div>
                
            </div>
            
            <!-- VITAL SIGNS - Full Width -->
            <div class="form-card-item" style="margin-top:18px;">
                <div class="card-item-title">
                    <i class="fas fa-heartbeat" style="color:#DC2626;"></i> Vital Signs
                    <span class="badge-label">Optional</span>
                    <?php if ($selected_patient_id > 0 && $latest_vital_signs): ?>
                        <span style="font-size:0.65rem;color:var(--success);margin-left:8px;">
                            <i class="fas fa-check-circle"></i> Latest: <?= date('d/m/Y H:i', strtotime($latest_vital_signs['recorded_at'])) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="vital-grid-modern">
                    <div class="vital-item-modern">
                        <span class="vital-label">🌡️ Temperature</span>
                        <input type="number" name="temperature" class="vital-input" step="0.1" placeholder="36.5" value="<?= $latest_vital_signs['temperature'] ?? '' ?>">
                        <span class="vital-unit">°C</span>
                    </div>
                    
                    <div class="vital-item-modern">
                        <span class="vital-label">💓 Blood Pressure</span>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <input type="number" name="bp_systolic" class="vital-input" style="width:45%;" placeholder="120" value="<?= $latest_vital_signs['blood_pressure_systolic'] ?? '' ?>">
                            <span style="color:var(--text-secondary);font-weight:700;">/</span>
                            <input type="number" name="bp_diastolic" class="vital-input" style="width:45%;" placeholder="80" value="<?= $latest_vital_signs['blood_pressure_diastolic'] ?? '' ?>">
                        </div>
                        <span class="vital-unit">mmHg</span>
                    </div>
                    
                    <div class="vital-item-modern">
                        <span class="vital-label">❤️ Pulse Rate</span>
                        <input type="number" name="pulse_rate" class="vital-input" placeholder="72" value="<?= $latest_vital_signs['pulse_rate'] ?? '' ?>">
                        <span class="vital-unit">bpm</span>
                    </div>
                    
                    <div class="vital-item-modern">
                        <span class="vital-label">⚖️ Weight</span>
                        <input type="number" name="weight" class="vital-input" step="0.1" placeholder="65" value="<?= $latest_vital_signs['weight'] ?? '' ?>" id="weightInput" oninput="calculateBMI()">
                        <span class="vital-unit">kg</span>
                    </div>
                    
                    <div class="vital-item-modern">
                        <span class="vital-label">📏 Height</span>
                        <input type="number" name="height" class="vital-input" step="0.1" placeholder="170" value="<?= $latest_vital_signs['height'] ?? '' ?>" id="heightInput" oninput="calculateBMI()">
                        <span class="vital-unit">cm</span>
                    </div>
                    
                    <div class="vital-item-modern bmi-item">
                        <span class="vital-label">📊 BMI</span>
                        <input type="number" name="bmi" class="vital-input" id="bmiOutput" readonly step="0.1" placeholder="22.5" value="<?= $latest_vital_signs['bmi'] ?? '' ?>">
                        <span class="vital-unit">kg/m²</span>
                    </div>
                </div>
                
                <div style="margin-top:12px;">
                    <input type="text" name="vital_notes" class="form-control-modern" placeholder="Vital signs notes (optional)" value="<?= $latest_vital_signs['notes'] ?? '' ?>">
                </div>
            </div>
            
            <!-- Additional Notes -->
            <div class="form-card-item" style="margin-top:12px;">
                <div class="card-item-title">
                    <i class="fas fa-sticky-note"></i> Additional Notes <span class="badge-label">Optional</span>
                </div>
                <textarea name="notes" class="form-control-modern textarea" placeholder="Any additional notes..." id="notesInput" rows="2"></textarea>
            </div>
            
            <!-- FORM ACTIONS -->
            <div class="form-actions-modern">
                <button type="submit" class="btn-modern <?= $change_mode ? 'btn-modern-warning' : 'btn-modern-primary' ?>" id="assignBtn">
                    <i class="fas <?= $change_mode ? 'fa-sync-alt' : 'fa-user-md' ?>"></i> 
                    <?= $change_mode ? 'Change Doctor' : 'Assign / Change Doctor' ?>
                </button>
                <button type="reset" class="btn-modern btn-modern-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="dashboard.php" class="btn-modern btn-modern-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- QUICK STATS -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;max-width:1300px;margin:20px auto 0;">
        <div style="padding:16px 20px;background:var(--bg-card);border-radius:var(--radius-lg);border:1px solid var(--border-color);box-shadow:var(--shadow-md);text-align:center;">
            <div style="font-size:1.5rem;">🟡</div>
            <p style="font-size:1.5rem;font-weight:700;color:var(--warning);" id="pendingStatNumber"><?= $pending_count ?></p>
            <p style="font-size:0.7rem;color:var(--text-secondary);font-weight:600;">Pending (No Doctor)</p>
            <p style="font-size:0.55rem;color:var(--text-secondary);" id="pendingUpdateTime">Updated: <?= date('H:i:s') ?></p>
        </div>
        <div style="padding:16px 20px;background:var(--bg-card);border-radius:var(--radius-lg);border:1px solid var(--border-color);box-shadow:var(--shadow-md);text-align:center;">
            <div style="font-size:1.5rem;">✅</div>
            <p style="font-size:1.5rem;font-weight:700;color:var(--success);" id="assignedStatNumber"><?= $assigned_count ?></p>
            <p style="font-size:0.7rem;color:var(--text-secondary);font-weight:600;">Assigned (Has Doctor)</p>
            <p style="font-size:0.55rem;color:var(--text-secondary);" id="assignedUpdateTime">Updated: <?= date('H:i:s') ?></p>
        </div>
        <div style="padding:16px 20px;background:var(--bg-card);border-radius:var(--radius-lg);border:1px solid var(--border-color);box-shadow:var(--shadow-md);text-align:center;">
            <div style="font-size:1.5rem;">🧪</div>
            <p style="font-size:1.5rem;font-weight:700;color:var(--purple);" id="labOnlyStatNumber"><?= $lab_only_count ?></p>
            <p style="font-size:0.7rem;color:var(--text-secondary);font-weight:600;">Lab Only (No Doctor)</p>
            <p style="font-size:0.55rem;color:var(--text-secondary);" id="labOnlyUpdateTime">Updated: <?= date('H:i:s') ?></p>
        </div>
        <div style="padding:16px 20px;background:var(--bg-card);border-radius:var(--radius-lg);border:1px solid var(--border-color);box-shadow:var(--shadow-md);text-align:center;">
            <div style="font-size:1.5rem;">👨‍⚕️</div>
            <p style="font-size:1.5rem;font-weight:700;color:var(--primary);" id="availableDoctorsStat"><?= $total_doctors ?></p>
            <p style="font-size:0.7rem;color:var(--text-secondary);font-weight:600;">Total Doctors</p>
            <p style="font-size:0.55rem;color:var(--text-secondary);">🟢 <?= $online_doctors_count ?> online, ⚪ <?= $offline_doctors_count ?> offline</p>
        </div>
    </div>

    <footer class="footer-modern">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            Assign / Change Doctor
            <span style="margin:0 8px;">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span style="margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<div id="toast" class="toast-modern" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    // CLOCK
    function updateClock() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        var el = document.getElementById('clockDisplay');
        if (el) el.textContent = dateStr + ' • ' + timeStr;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // SEARCH
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'assign_doctor.php?search=' + encodeURIComponent(query);
        }
    }
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) { if (e.key === 'Enter') performSearch(); });

    // DARK MODE
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
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

    // SIDEBAR
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    sidebarToggle?.addEventListener('click', function() { sidebar.classList.toggle('open'); });
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // TOAST
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        toast.className = 'toast-modern ' + type;
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

    // AUTO-REMOVE NOTIFICATION
    document.addEventListener('DOMContentLoaded', function() {
        var notification = document.getElementById('autoRemoveNotification');
        if (notification) {
            setTimeout(function() { notification.classList.add('hide'); }, 5000);
        }
    });

    // SYMPTOMS
    var symptomsSelect = document.getElementById('symptomsSelect');
    var symptomsTextarea = document.getElementById('symptomsTextarea');
    symptomsSelect?.addEventListener('change', function() {
        var value = this.value;
        if (value && value !== 'other') {
            var currentValue = symptomsTextarea.value.trim();
            symptomsTextarea.value = currentValue ? currentValue + ', ' + value : value;
        } else if (value === 'other') {
            symptomsTextarea.focus();
        }
    });

    // BMI
    function calculateBMI() {
        var weightInput = document.getElementById('weightInput');
        var heightInput = document.getElementById('heightInput');
        var bmiOutput = document.getElementById('bmiOutput');
        if (!weightInput || !heightInput || !bmiOutput) return;
        var weight = parseFloat(weightInput.value);
        var height = parseFloat(heightInput.value);
        if (weight && height && height > 0) {
            var heightM = height / 100;
            bmiOutput.value = Math.round((weight / (heightM * heightM)) * 10) / 10;
        } else {
            bmiOutput.value = '';
        }
    }

    // TOGGLE ASSIGNMENT TYPE
    function toggleAssignmentType(type) {
        var labSection = document.getElementById('labSection');
        var doctorSelect = document.getElementById('doctorSelect');
        var doctorRequired = document.getElementById('doctorRequired');
        var assignBtn = document.getElementById('assignBtn');
        var helpText = document.getElementById('assignmentTypeHelp');
        var visitTypeSection = document.getElementById('visitTypeSection');
        var visitTypeSelect = document.getElementById('visitTypeSelect');
        var visitTypePrice = document.getElementById('visitTypePrice');
        var feeNote = document.getElementById('feeNote');
        var doctorSelectCard = document.getElementById('doctorSelectCard');
        
        if (type === 'lab') {
            if (doctorSelectCard) doctorSelectCard.style.display = 'none';
            labSection.style.display = 'block';
            doctorSelect.removeAttribute('required');
            if (doctorRequired) doctorRequired.style.display = 'none';
            helpText.textContent = '🧪 Lab test request selected - Doctor is NOT required';
            assignBtn.innerHTML = '<i class="fas fa-flask"></i> Request Lab Tests (No Doctor)';
            if (visitTypeSection) visitTypeSection.style.display = 'none';
            if (visitTypeSelect) visitTypeSelect.disabled = true;
            if (visitTypePrice) visitTypePrice.style.display = 'none';
            if (feeNote) feeNote.innerHTML = '<span style="color:var(--purple);">🧪 Lab Test Mode: No doctor assigned, No consultation fee</span>';
            assignBtn.style.background = '#7C3AED';
            assignBtn.style.color = 'white';
        } else {
            if (doctorSelectCard) doctorSelectCard.style.display = 'block';
            labSection.style.display = 'none';
            doctorSelect.setAttribute('required', 'required');
            if (doctorRequired) doctorRequired.style.display = 'inline';
            helpText.textContent = '👨‍⚕️ Doctor assignment selected - Doctor is required';
            assignBtn.innerHTML = '<i class="fas fa-user-md"></i> Assign / Change Doctor';
            if (visitTypeSection) visitTypeSection.style.display = 'block';
            if (visitTypeSelect) visitTypeSelect.disabled = false;
            if (visitTypePrice) visitTypePrice.style.display = 'inline';
            if (feeNote) feeNote.innerHTML = '<span style="color:var(--primary);">👨‍⚕️ Consultation Mode: Doctor required, Consultation fee applies</span>';
            assignBtn.style.background = '';
            assignBtn.style.color = '';
        }
    }

    // LAB FUNCTIONS
    function updateLabSelection(checkbox) {
        var checkboxes = document.querySelectorAll('.lab-test-checkbox');
        var count = 0, total = 0, names = [], selectedIds = [];
        
        checkboxes.forEach(function(cb) {
            var item = cb.closest('.lab-test-item-modern');
            if (cb.checked) {
                count++;
                selectedIds.push(cb.value);
                if (item) item.classList.add('checked');
                var nameEl = item ? item.querySelector('label strong') : null;
                if (nameEl) names.push(nameEl.textContent);
                var priceText = item ? item.querySelector('.lab-test-price')?.textContent || '' : '';
                var price = parseFloat(priceText.replace(/[^0-9.]/g, ''));
                if (!isNaN(price)) total += price;
            } else {
                if (item) item.classList.remove('checked');
            }
        });
        
        var countEl = document.getElementById('labSelectedCount');
        if (countEl) countEl.textContent = '(' + count + ' selected)';
        var totalPriceEl = document.getElementById('labTotalPrice');
        if (totalPriceEl) totalPriceEl.textContent = count > 0 ? 'Total: TSh ' + total.toLocaleString() : 'Total: TSh 0';
        var selectionCountEl = document.getElementById('labSelectionCount');
        if (selectionCountEl) selectionCountEl.textContent = '(' + count + ' tests)';
        
        var summaryEl = document.getElementById('labSelectedSummary');
        var namesEl = document.getElementById('labSelectedNames');
        if (summaryEl && namesEl) {
            if (count > 0) { summaryEl.style.display = 'block'; namesEl.textContent = names.join(', '); }
            else { summaryEl.style.display = 'none'; }
        }
        
        var hiddenInput = document.getElementById('selectedLabTestsInput');
        if (hiddenInput) hiddenInput.value = selectedIds.join(',');
        
        var existingHidden = document.querySelectorAll('input[name="lab_test_ids[]"]');
        existingHidden.forEach(function(el) { if (el.type === 'hidden') el.remove(); });
        
        selectedIds.forEach(function(id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'lab_test_ids[]';
            input.value = id;
            document.getElementById('assignForm').appendChild(input);
        });
    }

    function selectAllLabTests() {
        document.querySelectorAll('.lab-test-checkbox').forEach(function(cb) {
            cb.checked = true;
            var item = cb.closest('.lab-test-item-modern');
            if (item) item.classList.add('checked');
        });
        updateLabSelection(null);
    }

    function deselectAllLabTests() {
        document.querySelectorAll('.lab-test-checkbox').forEach(function(cb) {
            cb.checked = false;
            var item = cb.closest('.lab-test-item-modern');
            if (item) item.classList.remove('checked');
        });
        updateLabSelection(null);
    }

    function closeLabTests() {
        var labSection = document.getElementById('labSection');
        if (labSection) labSection.style.display = 'none';
        var select = document.getElementById('assignmentTypeSelect');
        if (select) { select.value = 'doctor'; toggleAssignmentType('doctor'); }
    }

    function updateVisitTypePrice() {
        var select = document.getElementById('visitTypeSelect');
        var priceDisplay = document.getElementById('visitTypePrice');
        var descriptionEl = document.getElementById('visitTypeDescription');
        if (!select) return;
        var selectedOption = select.options[select.selectedIndex];
        var price = selectedOption.dataset.price || 0;
        var serviceName = selectedOption.textContent.split(' - ')[0] || 'Consultation';
        if (priceDisplay) priceDisplay.textContent = 'Fee: TSh ' + parseInt(price).toLocaleString();
        if (descriptionEl) descriptionEl.textContent = '📋 ' + serviceName.trim() + ' | Fee: TSh ' + parseInt(price).toLocaleString();
    }

    function selectPatientAndChange(patientId) {
        var select = document.getElementById('patientSelect');
        if (select) {
            select.value = patientId;
            if (select.value != patientId) {
                window.location.href = 'assign_doctor.php?patient_id=' + patientId + '&change=1';
                return;
            }
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        var formCard = document.getElementById('mainFormCard');
        if (formCard) formCard.classList.add('change-mode-active-modern');
        var doctorSelect = document.getElementById('doctorSelect');
        if (doctorSelect) {
            setTimeout(function() {
                doctorSelect.focus();
                showToast('🔄 Change Mode', 'Patient auto-selected. Choose new doctor.', 'warning');
            }, 500);
        }
        formCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function fetchPatientDetails(patientId) {
        var formData = new FormData();
        formData.append('action', 'get_patient_details');
        formData.append('patient_id', patientId);
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success && data.patient) {
                    var infoDiv = document.getElementById('selectedPatientInfo');
                    if (infoDiv) {
                        var doctorName = data.assigned_doctor || 'No doctor assigned';
                        var doctorHtml = doctorName !== 'No doctor assigned' 
                            ? '<span style="background:var(--primary-bg);padding:3px 10px;border-radius:12px;border:1px solid var(--primary-light);font-size:0.7rem;"><i class="fas fa-user-md" style="color:var(--primary);"></i> Dr. ' + escapeHtml(doctorName) + '</span>'
                            : '<span style="color:var(--text-secondary);font-size:0.7rem;">No doctor assigned</span>';
                        var patientDays = data.patient_days || 0;
                        var daysHtml = '<span class="days-badge-blue">📅 ' + (patientDays > 0 ? patientDays + ' days ago' : 'Just registered') + '</span>';
                        
                        infoDiv.innerHTML = '<div style="display:flex;align-items:center;gap:8px;font-size:0.75rem;flex-wrap:wrap;">' +
                            '<i class="fas fa-user-circle" style="color:var(--primary);"></i>' +
                            '<span style="font-weight:600;">' + escapeHtml(data.patient.full_name || '') + '</span>' +
                            '<span style="color:var(--text-secondary);">|</span>' +
                            '<span>' + escapeHtml(data.patient.patient_id || '') + '</span>' +
                            daysHtml + doctorHtml + '</div>';
                        infoDiv.style.display = 'block';
                    }
                }
            });
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // LIVE UPDATE
    var updateInterval = null;
    var isUpdating = false;

    function fetchLiveData() {
        if (isUpdating) return;
        isUpdating = true;
        var formData = new FormData();
        formData.append('action', 'get_live_data');
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) updateUI(data);
                isUpdating = false;
            })
            .catch(function() { isUpdating = false; });
    }

    function updateUI(data) {
        document.getElementById('pendingCount').textContent = data.pending_count;
        document.getElementById('assignedCount').textContent = data.assigned_count;
        document.getElementById('labOnlyCount').textContent = data.lab_only_count;
        document.getElementById('pendingStat').textContent = data.pending_count;
        document.getElementById('assignedStat').textContent = data.assigned_count;
        document.getElementById('labOnlyStat').textContent = data.lab_only_count;
        document.getElementById('pendingStatNumber').textContent = data.pending_count;
        document.getElementById('assignedStatNumber').textContent = data.assigned_count;
        document.getElementById('labOnlyStatNumber').textContent = data.lab_only_count;
        document.getElementById('onlineDoctorCount').textContent = data.online_count;
        document.getElementById('offlineDoctorCount').textContent = data.offline_count;
        document.getElementById('availableDoctorsStat').textContent = data.total_doctors;
        document.getElementById('assignedListCount').textContent = data.assigned_count;
        document.getElementById('labOnlyListCount').textContent = data.lab_only_count;
        document.getElementById('onlineCountDisplay').textContent = '🟢 ' + data.online_count + ' online';
        document.getElementById('offlineCountDisplay').textContent = '⚪ ' + data.offline_count + ' offline';
        
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        document.getElementById('pendingUpdateTime').textContent = 'Updated: ' + timeStr;
        document.getElementById('assignedUpdateTime').textContent = 'Updated: ' + timeStr;
        document.getElementById('labOnlyUpdateTime').textContent = 'Updated: ' + timeStr;
        document.getElementById('assignedListUpdate').textContent = '(Auto-updated ' + timeStr + ')';
        document.getElementById('labOnlyUpdate').textContent = '(Auto-updated ' + timeStr + ')';
        
        var patientSelect = document.getElementById('patientSelect');
        if (patientSelect && data.patient_options !== undefined && patientSelect.innerHTML !== data.patient_options) {
            var currentValue = patientSelect.value;
            patientSelect.innerHTML = data.patient_options;
            if (currentValue) patientSelect.value = currentValue;
        }
        
        var tableBody = document.getElementById('assignedPatientsTableBody');
        var noAssigned = document.getElementById('noAssignedPatients');
        if (tableBody && data.assigned_list_html !== undefined) {
            if (data.assigned_list_count > 0) {
                tableBody.innerHTML = data.assigned_list_html;
                if (noAssigned) noAssigned.style.display = 'none';
            } else {
                tableBody.innerHTML = '';
                if (noAssigned) noAssigned.style.display = 'block';
            }
        }
        
        var labTableBody = document.getElementById('labOnlyTableBody');
        var noLab = document.getElementById('noLabPatients');
        if (labTableBody && data.lab_only_html !== undefined) {
            if (data.lab_only_count > 0) {
                labTableBody.innerHTML = data.lab_only_html;
                if (noLab) noLab.style.display = 'none';
            } else {
                labTableBody.innerHTML = '';
                if (noLab) noLab.style.display = 'block';
            }
        }
        
        var doctorSelect = document.getElementById('doctorSelect');
        if (doctorSelect && data.doctor_options !== undefined && doctorSelect.innerHTML !== data.doctor_options) {
            var currentDocValue = doctorSelect.value;
            doctorSelect.innerHTML = data.doctor_options;
            if (currentDocValue) doctorSelect.value = currentDocValue;
        }
        
        var visitTypeSelect = document.getElementById('visitTypeSelect');
        if (visitTypeSelect && data.visit_type_options !== undefined && visitTypeSelect.innerHTML !== data.visit_type_options) {
            var currentVisitValue = visitTypeSelect.value;
            visitTypeSelect.innerHTML = data.visit_type_options;
            if (currentVisitValue) visitTypeSelect.value = currentVisitValue;
            updateVisitTypePrice();
        }
        
        var labContainer = document.getElementById('labTestsContainer');
        if (labContainer && data.lab_tests_html !== undefined) {
            var currentSelected = [];
            labContainer.querySelectorAll('.lab-test-checkbox:checked').forEach(function(cb) { currentSelected.push(cb.value); });
            labContainer.innerHTML = data.lab_tests_html;
            labContainer.querySelectorAll('.lab-test-checkbox').forEach(function(cb) {
                if (currentSelected.includes(cb.value)) {
                    cb.checked = true;
                    var item = cb.closest('.lab-test-item-modern');
                    if (item) item.classList.add('checked');
                }
            });
            updateLabSelection(null);
        }
    }

    function startLiveUpdate() {
        if (updateInterval) clearInterval(updateInterval);
        fetchLiveData();
        updateInterval = setInterval(fetchLiveData, 3000);
    }

    function stopLiveUpdate() {
        if (updateInterval) { clearInterval(updateInterval); updateInterval = null; }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) stopLiveUpdate();
        else startLiveUpdate();
    });

    // FORM SUBMIT
    document.getElementById('assignForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        formData.append('action', 'change_doctor');
        
        var visitTypeSelect = document.getElementById('visitTypeSelect');
        if (visitTypeSelect) formData.append('service_id', visitTypeSelect.value);
        
        var hiddenInput = document.getElementById('selectedLabTestsInput');
        if (hiddenInput && hiddenInput.value) {
            hiddenInput.value.split(',').forEach(function(id) { if (id) formData.append('lab_test_ids[]', id); });
        }
        
        var btn = document.getElementById('assignBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Processing...';
        
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-user-md"></i> Assign / Change Doctor';
                
                if (data.success) {
                    var msg = data.message;
                    if (data.bill_sent_to_cashier && data.bill_number) {
                        msg += ' 💰 Bill #' + data.bill_number + ' sent to Cashier!';
                    }
                    showToast('✅ Success', msg, 'success');
                    if (data.patient_id) {
                        setTimeout(function() { window.location.href = 'assign_doctor.php'; }, 3000);
                    }
                } else {
                    showToast('❌ Error', data.message || 'Failed', 'error');
                }
            })
            .catch(function(error) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-user-md"></i> Assign / Change Doctor';
                showToast('❌ Error', 'Network error: ' + error.message, 'error');
            });
    });

    // INIT
    document.addEventListener('DOMContentLoaded', function() {
        calculateBMI();
        updateVisitTypePrice();
        
        document.getElementById('patientSelect')?.addEventListener('change', function() {
            var selectedId = this.value;
            if (selectedId) fetchPatientDetails(selectedId);
        });
        
        var initialPatientId = <?= $selected_patient_id ?: 0 ?>;
        if (initialPatientId > 0) {
            fetchPatientDetails(initialPatientId);
        }
        
        setTimeout(function() { updateLabSelection(null); }, 500);
        setTimeout(function() { startLiveUpdate(); }, 2000);
        
        var assignmentType = document.getElementById('assignmentTypeSelect');
        if (assignmentType && assignmentType.value === 'lab') toggleAssignmentType('lab');
    });

    console.log('%c👨‍⚕️ Braick - Assign Doctor (BIGGER CARDS)', 'font-size:18px; font-weight:bold; color:#2563EB;');
    console.log('%c✅ Cards size increased - min-height: 160px', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Lab test card bigger - 320px scroll area, 18px checkboxes', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Form controls bigger - 44px min-height', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>