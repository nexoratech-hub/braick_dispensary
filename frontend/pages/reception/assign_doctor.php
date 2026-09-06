<?php
// ================================================================
// FILE: frontend/pages/reception/assign_doctor.php
// RECEPTION - ASSIGN / CHANGE DOCTOR & LAB TESTS
// ✅ FIXED: Lab mode - Doctor dropdown HIDDEN completely
// ✅ FIXED: 3 cards left, 3 cards right - Uniform sizes
// ✅ FIXED: Clean modern design with beautiful CSS
// ✅ FIXED: Lab bills sent to bills table
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
    
    // GET ALL PATIENTS - AUTO-REMOVE COMPLETED
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
            v.lab_fees_total,
            v.pharmacy_fees_total,
            v.other_fees_total,
            v.visit_total,
            v.payment_status,
            v.total_discount,
            v.discount_percent,
            v.created_at as visit_created_at,
            v.doctor_id as visit_doctor_id,
            DATEDIFF(NOW(), p.created_at) as patient_days,
            CASE 
                WHEN v.status IN ('completed', 'cancelled') THEN 1 
                ELSE 0 
            END as visit_is_completed,
            (
                SELECT COUNT(*) 
                FROM lab_tests lt 
                WHERE lt.patient_id = p.id 
                AND lt.status NOT IN ('completed', 'cancelled')
                AND lt.visit_id = v.id
            ) as pending_lab_tests_count
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
    $pending_count = 0;
    $assigned_count = 0;
    $lab_only_count = 0;
    
    foreach ($all_patients as $patient) {
        $patient['has_active_visit'] = !empty($patient['visit_id']);
        $patient['patient_days'] = isset($patient['patient_days']) ? (int)$patient['patient_days'] : 0;
        
        if ($patient['has_active_visit']) {
            if ($patient['visit_status'] === 'lab_test' && empty($patient['visit_doctor_id'])) {
                $lab_only_patients[] = $patient;
                $lab_only_count++;
            }
            elseif (in_array($patient['visit_status'], ['new', 'pending'])) {
                $pending_patients[] = $patient;
                $pending_count++;
            } 
            elseif ($patient['visit_status'] === 'assigned' || $patient['visit_status'] === 'with_doctor') {
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
            ORDER BY vs.recorded_at DESC
            LIMIT 1
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
    
    if ($selected_patient_id > 0) {
        $stmt = $db->prepare("
            SELECT lt.*, 
                   CONCAT('Test #', lt.id) as request_number,
                   'Lab Test' as test_names,
                   1 as test_count,
                   lt.test_price as lab_total,
                   lt.status as test_status,
                   lt.results as test_results
            FROM lab_tests lt
            WHERE lt.patient_id = ? AND lt.branch_id = ?
            ORDER BY lt.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$selected_patient_id, $selected_branch_id]);
        $lab_tests_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // ✅ FIXED: CREATE LAB ONLY BILL - sends to bills table
    // ================================================================
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
        
        // Check existing bill
        $stmt = $db->prepare("
            SELECT id, bill_number FROM bills 
            WHERE visit_id = ? AND status IN ('pending', 'partial')
            LIMIT 1
        ");
        $stmt->execute([$visit_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing bill with lab fees
            $stmt = $db->prepare("
                UPDATE bills 
                SET total_amount = total_amount + ?,
                    balance = balance + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$total_lab_fee, $total_lab_fee, $existing['id']]);
            
            $bill_id = $existing['id'];
            $bill_number = $existing['bill_number'];
            
            // Add lab test items to bill_items
            foreach ($tests as $test) {
                $stmt = $db->prepare("
                    INSERT INTO bill_items (
                        bill_id, patient_id, branch_id, item_type, item_name,
                        quantity, unit_price, total_price, status, created_at
                    ) VALUES (?, ?, ?, 'lab_test', ?, 1, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $bill_id,
                    $patient_id,
                    $branch_id,
                    $test['test_name'],
                    $test['price'],
                    $test['price']
                ]);
            }
            
            return [
                'status' => 'updated',
                'message' => 'Lab tests added to existing bill',
                'bill_id' => $bill_id,
                'bill_number' => $bill_number,
                'total_lab_fee' => $total_lab_fee
            ];
        }
        
        // Create new bill
        $bill_number = 'BILL-LAB-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999);
        
        $stmt = $db->prepare("
            INSERT INTO bills (
                bill_number, patient_id, visit_id, 
                branch_id, created_by,
                subtotal, total_amount, balance, 
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $bill_number,
            $patient_id,
            $visit_id,
            $branch_id,
            $user_id,
            $total_lab_fee,
            $total_lab_fee,
            $total_lab_fee
        ]);
        $bill_id = $db->lastInsertId();
        
        // Add each lab test as bill item
        foreach ($tests as $test) {
            $stmt = $db->prepare("
                INSERT INTO bill_items (
                    bill_id, patient_id, branch_id, item_type, item_name,
                    quantity, unit_price, total_price, status, created_at
                ) VALUES (?, ?, ?, 'lab_test', ?, 1, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $bill_id,
                $patient_id,
                $branch_id,
                $test['test_name'],
                $test['price'],
                $test['price']
            ]);
        }
        
        // Update visit lab fees
        $stmt = $db->prepare("UPDATE visits SET lab_fees_total = lab_fees_total + ? WHERE id = ?");
        $stmt->execute([$total_lab_fee, $visit_id]);
        
        // Notify Cashier
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE role = 'cashier' AND status = 'active' AND branch_id = ?");
            $stmt->execute([$branch_id]);
            $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($cashiers as $cashier) {
                $stmt = $db->prepare("
                    INSERT INTO notifications (user_id, branch_id, title, message, type, link, is_read, created_at)
                    VALUES (?, ?, '🧪 Lab Test Bill Created', ?, 'bill', ?, 0, NOW())
                ");
                $stmt->execute([
                    $cashier['id'],
                    $branch_id,
                    "Lab Test bill #$bill_number (TSh " . number_format($total_lab_fee) . ") for patient ID #$patient_id - " . implode(', ', $lab_test_names),
                    "cashier_dashboard.php"
                ]);
            }
        } catch (Exception $e) {
            error_log("Cashier notification error: " . $e->getMessage());
        }
        
        return [
            'status' => 'created',
            'message' => 'Lab bill created and sent to Cashier!',
            'bill_id' => $bill_id,
            'bill_number' => $bill_number,
            'total_lab_fee' => $total_lab_fee
        ];
    }
    
    // ================================================================
    // CREATE VISIT BILL FUNCTION
    // ================================================================
    function createVisitBill($db, $patient_id, $visit_id, $service_name, $consultation_fee, $user_id, $branch_id) {
        if ($consultation_fee <= 0) {
            return ['status' => 'error', 'message' => 'Consultation fee is 0 or negative'];
        }
        
        // Check existing bill
        $stmt = $db->prepare("
            SELECT id, bill_number FROM bills 
            WHERE visit_id = ? AND status IN ('pending', 'partial')
            LIMIT 1
        ");
        $stmt->execute([$visit_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing bill with consultation fee
            $stmt = $db->prepare("
                UPDATE bills 
                SET total_amount = total_amount + ?,
                    balance = balance + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$consultation_fee, $consultation_fee, $existing['id']]);
            
            $bill_id = $existing['id'];
            $bill_number = $existing['bill_number'];
            
            // Add consultation item to bill_items
            $stmt = $db->prepare("
                INSERT INTO bill_items (
                    bill_id, patient_id, branch_id, item_type, item_name,
                    quantity, unit_price, total_price, status, created_at
                ) VALUES (?, ?, ?, 'consultation', ?, 1, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $bill_id,
                $patient_id,
                $branch_id,
                $service_name,
                $consultation_fee,
                $consultation_fee
            ]);
            
            return [
                'status' => 'updated',
                'message' => 'Consultation fee added to existing bill',
                'bill_id' => $bill_id,
                'bill_number' => $bill_number,
                'total_consultation_fee' => $consultation_fee
            ];
        }
        
        // Create new bill
        $bill_number = 'BILL-CONS-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999);
        
        $stmt = $db->prepare("
            INSERT INTO bills (
                bill_number, patient_id, visit_id, 
                branch_id, created_by,
                subtotal, total_amount, balance, 
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $bill_number,
            $patient_id,
            $visit_id,
            $branch_id,
            $user_id,
            $consultation_fee,
            $consultation_fee,
            $consultation_fee
        ]);
        $bill_id = $db->lastInsertId();
        
        // Add consultation item to bill_items
        $stmt = $db->prepare("
            INSERT INTO bill_items (
                bill_id, patient_id, branch_id, item_type, item_name,
                quantity, unit_price, total_price, status, created_at
            ) VALUES (?, ?, ?, 'consultation', ?, 1, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $bill_id,
            $patient_id,
            $branch_id,
            $service_name,
            $consultation_fee,
            $consultation_fee
        ]);
        
        // Notify Cashier
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE role = 'cashier' AND status = 'active' AND branch_id = ?");
            $stmt->execute([$branch_id]);
            $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($cashiers as $cashier) {
                $stmt = $db->prepare("
                    INSERT INTO notifications (user_id, branch_id, title, message, type, link, is_read, created_at)
                    VALUES (?, ?, '💰 Consultation Bill Created', ?, 'bill', ?, 0, NOW())
                ");
                $stmt->execute([
                    $cashier['id'],
                    $branch_id,
                    "Consultation bill #$bill_number (TSh " . number_format($consultation_fee) . ") for patient ID #$patient_id - $service_name",
                    "cashier_dashboard.php"
                ]);
            }
        } catch (Exception $e) {
            error_log("Cashier notification error: " . $e->getMessage());
        }
        
        return [
            'status' => 'created',
            'message' => 'Consultation bill created and sent to Cashier!',
            'bill_id' => $bill_id,
            'bill_number' => $bill_number,
            'total_consultation_fee' => $consultation_fee
        ];
    }
    
    // ================================================================
    // HANDLE AJAX REQUESTS
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'get_live_data') {
            header('Content-Type: application/json');
            
            // Build patient options HTML
            $patient_options_html = '';
            if (!empty($all_patients) && is_array($all_patients) && count($all_patients) > 0) {
                $patient_options_html .= '<optgroup label="📋 All Patients (' . count($all_patients) . ' - Newest First)">';
                foreach ($all_patients as $patient) {
                    $status_label = '📋 No Visit';
                    $status_class = 'no_visit';
                    $status_icon = '📋';
                    
                    if (!empty($patient['visit_id'])) {
                        if ($patient['visit_status'] === 'lab_test' && empty($patient['visit_doctor_id'])) {
                            $status_label = '🧪 Lab Only';
                            $status_class = 'lab_only';
                            $status_icon = '🧪';
                        } elseif (in_array($patient['visit_status'], ['new', 'pending'])) {
                            $status_label = '⏳ Pending';
                            $status_class = 'pending';
                            $status_icon = '⏳';
                        } elseif ($patient['visit_status'] === 'assigned' || $patient['visit_status'] === 'with_doctor') {
                            $status_label = '✅ Assigned';
                            $status_class = 'assigned';
                            $status_icon = '✅';
                        }
                    }
                    
                    $doctor_info = '';
                    if (!empty($patient['assigned_doctor_name'])) {
                        $online_status = !empty($patient['assigned_doctor_online']) ? '🟢' : '⚪';
                        $doctor_info = ' 👨‍⚕️ Dr. ' . htmlspecialchars($patient['assigned_doctor_name']) . ' ' . $online_status;
                    }
                    
                    $selected = ($selected_patient_id == $patient['id']) ? 'selected' : '';
                    
                    $days = isset($patient['patient_days']) ? (int)$patient['patient_days'] : 0;
                    $days_text = $days > 0 ? '<span class="days-badge-blue">📅 ' . $days . ' days</span>' : '<span class="days-badge-blue new">📅 New</span>';
                    
                    $assigned_days_text = '';
                    if (!empty($patient['visit_id']) && !empty($patient['visit_created_at'])) {
                        $assigned_days = (int)floor((time() - strtotime($patient['visit_created_at'])) / 86400);
                        $assigned_days_text = ' <span class="assigned-days-badge-blue">Visit: ' . $assigned_days . ' days ago</span>';
                    }
                    
                    $patient_options_html .= '
                        <option value="' . $patient['id'] . '" data-status="' . $status_class . '" data-doctor="' . htmlspecialchars($patient['assigned_doctor_name'] ?? '') . '" ' . $selected . '>
                            ' . $status_icon . ' ' . htmlspecialchars($patient['full_name']) . ' (' . htmlspecialchars($patient['patient_id'] ?? 'N/A') . ')
                            ' . (!empty($patient['phone']) ? '- ' . htmlspecialchars($patient['phone']) : '') . '
                            ' . $days_text . '
                            ' . $assigned_days_text . '
                            ' . $doctor_info . '
                            <span class="status-badge-dropdown ' . $status_class . '">' . $status_label . '</span>
                        </option>
                    ';
                }
                $patient_options_html .= '</optgroup>';
            } else {
                $patient_options_html .= '<option value="" disabled>No patients found</option>';
            }
            
            // Build assigned patients HTML
            $assigned_list_html = '';
            if (count($assigned_patients) > 0) {
                foreach ($assigned_patients as $patient) {
                    $assigned_days = 0;
                    if (!empty($patient['visit_created_at'])) {
                        $assigned_days = (int)floor((time() - strtotime($patient['visit_created_at'])) / 86400);
                    }
                    $days_text = $assigned_days > 0 ? '<span class="assigned-days-badge-blue">' . $assigned_days . ' days</span>' : '<span class="assigned-days-badge-blue new">Just assigned</span>';
                    
                    $assigned_list_html .= '
                        <tr id="assigned-row-' . $patient['id'] . '" style="border-bottom:1px solid var(--border-color);">
                            <td style="padding:8px 10px;font-weight:500;font-size:0.78rem;">
                                ' . htmlspecialchars($patient['full_name']) . '
                                ' . $days_text . '
                                <span class="text-xs text-gray-400 block">' . htmlspecialchars($patient['visit_type'] ?? 'Consultation') . '</span>
                            </td>
                            <td style="padding:8px 10px;font-family:monospace;font-size:0.75rem;">' . htmlspecialchars($patient['patient_id'] ?? 'N/A') . '</td>
                            <td style="padding:8px 10px;">
                                ' . (!empty($patient['assigned_doctor_name']) ? '
                                    <span class="assigned-doctor-tag-modern">
                                        <i class="fas fa-user-md"></i>
                                        Dr. ' . htmlspecialchars($patient['assigned_doctor_name']) . '
                                        ' . ($patient['assigned_doctor_online'] == 1 ? '<span class="text-green-500 text-xs">🟢</span>' : '<span class="text-gray-400 text-xs">⚪</span>') . '
                                    </span>
                                ' : '<span class="text-gray-400 text-xs">No doctor</span>') . '
                            </td>
                            <td style="padding:8px 10px;">
                                <span class="status-badge-dropdown assigned">✅ Assigned</span>
                            </td>
                            <td style="padding:8px 10px;">
                                <button onclick="selectPatientAndChange(' . $patient['id'] . ')" class="btn-modern btn-modern-warning btn-modern-sm" style="padding:3px 10px;font-size:0.65rem;">
                                    <i class="fas fa-sync-alt"></i> Change
                                </button>
                            </td>
                        </tr>
                    ';
                }
            }
            
            // Build lab only patients HTML
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
                        if ($lab_result) {
                            $lab_test_id = $lab_result['id'];
                            $lab_test_name = $lab_result['test_name'];
                        }
                    } catch (Exception $e) {}
                    
                    $view_link = $lab_test_id > 0 ? 
                        '<a href="lab_tests.php?id=' . $lab_test_id . '" class="btn-modern btn-modern-purple btn-modern-sm" style="padding:3px 10px;font-size:0.65rem;background:var(--purple);color:white;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:3px;">
                            <i class="fas fa-eye"></i> View
                        </a>' : 
                        '<span class="text-gray-400 text-xs">No test</span>';
                    
                    $lab_only_html .= '
                        <tr id="lab-row-' . $patient['id'] . '" style="border-bottom:1px solid var(--border-color);">
                            <td style="padding:8px 10px;font-weight:500;font-size:0.78rem;">
                                ' . htmlspecialchars($patient['full_name']) . '
                                ' . $days_text . '
                                ' . ($lab_test_id > 0 ? '<span class="text-xs text-purple-500 block">' . htmlspecialchars($lab_test_name) . '</span>' : '') . '
                            </td>
                            <td style="padding:8px 10px;font-family:monospace;font-size:0.75rem;">' . htmlspecialchars($patient['patient_id'] ?? 'N/A') . '</td>
                            <td style="padding:8px 10px;">
                                <span class="lab-only-tag-modern">
                                    <i class="fas fa-flask"></i>
                                    Lab Only
                                    <span class="text-xs" style="color:var(--purple);">🧪</span>
                                </span>
                            </td>
                            <td style="padding:8px 10px;">
                                <span class="status-badge-dropdown lab_only">🧪 Pending</span>
                            </td>
                            <td style="padding:8px 10px;">
                                ' . $view_link . '
                            </td>
                        </tr>
                    ';
                }
            }
            
            // Build doctor options HTML
            $doctor_options_html = '';
            if (!empty($online_doctors) && count($online_doctors) > 0) {
                $doctor_options_html .= '<optgroup label="🟢 Online Doctors (' . $online_doctors_count . ')" style="font-weight:600;color:#059669;">';
                foreach ($online_doctors as $doctor) {
                    $doctor_options_html .= '
                        <option value="' . $doctor['id'] . '" data-online="1" style="font-weight:500;color:#059669;padding:4px;">
                            🟢 Dr. ' . htmlspecialchars($doctor['full_name']) . '
                            ' . (!empty($doctor['specialty']) ? '(' . htmlspecialchars($doctor['specialty']) . ')' : '') . '
                        </option>
                    ';
                }
                $doctor_options_html .= '</optgroup>';
            }
            
            if (!empty($offline_doctors) && count($offline_doctors) > 0) {
                $doctor_options_html .= '<optgroup label="⚪ Offline Doctors (' . $offline_doctors_count . ')" style="font-weight:600;color:var(--text-secondary);">';
                foreach ($offline_doctors as $doctor) {
                    $doctor_options_html .= '
                        <option value="' . $doctor['id'] . '" data-online="0" style="color:var(--text-secondary);padding:4px;">
                            ⚪ Dr. ' . htmlspecialchars($doctor['full_name']) . '
                            ' . (!empty($doctor['specialty']) ? '(' . htmlspecialchars($doctor['specialty']) . ')' : '') . '
                        </option>
                    ';
                }
                $doctor_options_html .= '</optgroup>';
            }
            
            if (empty($online_doctors) && empty($offline_doctors)) {
                $doctor_options_html .= '<option value="" disabled>No doctors available</option>';
            }
            
            // Build visit type options HTML
            $visit_type_options_html = '';
            if (!empty($visit_type_options) && is_array($visit_type_options)) {
                foreach ($visit_type_options as $service_id => $option) {
                    $price = $option['price'] ?? 0;
                    $icon = $option['icon'] ?? '🏥';
                    $service_name = $option['service_name'] ?? 'Consultation';
                    $selected = ($service_id === $default_service_id) ? 'selected' : '';
                    $visit_type_options_html .= '
                        <option value="' . $service_id . '" data-price="' . $price . '" data-service-name="' . htmlspecialchars($service_name) . '" ' . $selected . '>
                            ' . $icon . ' ' . htmlspecialchars($service_name) . ' - TSh ' . number_format($price, 0) . '
                        </option>
                    ';
                }
            } else {
                $visit_type_options_html .= '<option value="" data-price="0" selected disabled>❌ No Visit Type Available</option>';
            }
            
            // Build lab tests HTML
            $lab_tests_html = '';
            if (!empty($lab_tests_catalog) && count($lab_tests_catalog) > 0) {
                foreach ($lab_tests_catalog as $test) {
                    $lab_tests_html .= '
                        <div class="lab-test-item-modern">
                            <input type="checkbox" name="lab_test_ids[]" value="' . $test['id'] . '" 
                                   id="lab_test_' . $test['id'] . '" 
                                   class="lab-test-checkbox" 
                                   onchange="updateLabSelection(this)">
                            <label for="lab_test_' . $test['id'] . '">
                                <strong>' . htmlspecialchars($test['test_name']) . '</strong>
                                ' . (!empty($test['category']) ? '<span class="lab-test-category">' . htmlspecialchars($test['category']) . '</span>' : '') . '
                            </label>
                            <span class="lab-test-price">TSh ' . number_format($test['price'] ?? 0, 0) . '</span>
                        </div>
                    ';
                }
            } else {
                $lab_tests_html .= '
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-flask"></i>
                        <p style="font-size:0.75rem;">No lab tests available</p>
                        <p style="font-size:0.65rem;margin-top:2px;">Please add lab tests to the catalog first</p>
                    </div>
                ';
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
                'lab_only_count' => $lab_only_count,
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
                $stmt = $db->prepare("
                    SELECT p.*, 
                           u.full_name as assigned_doctor_name,
                           u.is_online as assigned_doctor_online,
                           v.id as visit_id,
                           v.status as visit_status,
                           v.visit_type,
                           v.created_at as visit_created_at,
                           DATEDIFF(NOW(), p.created_at) as patient_days,
                           DATEDIFF(NOW(), v.created_at) as visit_days
                    FROM patients p
                    LEFT JOIN visits v ON p.id = v.patient_id AND v.status NOT IN ('completed', 'cancelled')
                    LEFT JOIN users u ON v.doctor_id = u.id
                    WHERE p.id = ?
                ");
                $stmt->execute([$patient_id]);
                $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($patient) {
                    echo json_encode([
                        'success' => true,
                        'patient' => $patient,
                        'assigned_doctor' => $patient['assigned_doctor_name'] ?? null,
                        'patient_days' => $patient['patient_days'] ?? 0,
                        'visit_days' => $patient['visit_days'] ?? null,
                        'visit_type' => $patient['visit_type'] ?? null,
                        'visit_status' => $patient['visit_status'] ?? null
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Patient not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        }
        
        // ================================================================
        // ✅ FIXED: CHANGE DOCTOR WITH LAB TESTS - BILL CREATION
        // ================================================================
        if ($action === 'change_doctor') {
            header('Content-Type: application/json');
            
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            $doctor_id = (int)($_POST['doctor_id'] ?? 0);
            $service_id = (int)($_POST['service_id'] ?? 0);
            $symptoms = trim($_POST['symptoms'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $lab_test_ids = isset($_POST['lab_test_ids']) ? $_POST['lab_test_ids'] : [];
            $assignment_type = $_POST['assignment_type'] ?? 'doctor';
            
            if (!is_array($lab_test_ids)) {
                $lab_test_ids = [];
            }
            
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
                    } else {
                        $stmt = $db->prepare("SELECT id, service_name, price FROM services WHERE category_id = 2 AND is_active = 1 AND (branch_id = ? OR branch_id IS NULL) LIMIT 1");
                        $stmt->execute([$selected_branch_id]);
                        $fallback = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($fallback) {
                            $service_name = $fallback['service_name'];
                            $consultation_fee = (float)$fallback['price'];
                        }
                    }
                } else if ($is_lab_only) {
                    $service_name = 'Lab Tests Only';
                    $consultation_fee = 0;
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
                
                // CHECK/ CREATE VISIT
                $stmt = $db->prepare("
                    SELECT id, status, doctor_id, visit_number, visit_type, service_id
                    FROM visits 
                    WHERE patient_id = ? AND status IN ('new', 'pending', 'assigned', 'with_doctor', 'lab_test')
                    AND branch_id = ?
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$patient_id, $selected_branch_id]);
                $existing_visit = $stmt->fetch();
                
                $visit_id = null;
                $visit_number = '';
                
                $visit_type_to_store = $is_lab_only ? 'Lab Tests Only' : $service_name;
                $service_id_to_store = $is_lab_only ? null : ($service_id > 0 ? $service_id : null);
                
                if ($existing_visit) {
                    $visit_id = $existing_visit['id'];
                    $visit_number = $existing_visit['visit_number'];
                    
                    $visit_status = ($is_lab_only && !empty($lab_test_ids)) ? 'lab_test' : 
                                    ($is_lab_only ? 'pending' : 'assigned');
                    
                    $doctor_id_to_store = ($is_lab_only) ? null : ($doctor_id > 0 ? $doctor_id : null);
                    
                    $stmt = $db->prepare("
                        UPDATE visits 
                        SET doctor_id = ?, 
                            status = ?,
                            visit_type = ?,
                            service_id = ?,
                            symptoms = ?,
                            notes = ?,
                            consultation_fee = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $doctor_id_to_store,
                        $visit_status,
                        $visit_type_to_store,
                        $service_id_to_store,
                        $symptoms,
                        $notes,
                        $consultation_fee,
                        $visit_id
                    ]);
                    
                } else {
                    $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $visit_status = ($is_lab_only && !empty($lab_test_ids)) ? 'lab_test' : 
                                    ($is_lab_only ? 'pending' : 'assigned');
                    
                    $doctor_id_to_store = ($is_lab_only) ? null : ($doctor_id > 0 ? $doctor_id : null);
                    
                    $stmt = $db->prepare("
                        INSERT INTO visits (
                            visit_number, patient_id, doctor_id, branch_id, 
                            visit_type, service_id, status, symptoms, notes, 
                            created_at, updated_at, consultation_fee, receptionist_id
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)
                    ");
                    $stmt->execute([
                        $visit_number, 
                        $patient_id, 
                        $doctor_id_to_store, 
                        $selected_branch_id, 
                        $visit_type_to_store,
                        $service_id_to_store,
                        $visit_status, 
                        $symptoms, 
                        $notes, 
                        $consultation_fee, 
                        $user_id
                    ]);
                    $visit_id = $db->lastInsertId();
                }
                
                // UPDATE PATIENT ASSIGNED DOCTOR
                if ($doctor_id > 0 && !$is_lab_only) {
                    $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = ? WHERE id = ?");
                    $stmt->execute([$doctor_id, $patient_id]);
                } elseif ($is_lab_only) {
                    $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL WHERE id = ?");
                    $stmt->execute([$patient_id]);
                }
                
                // ================================================================
                // ✅ FIXED: CREATE LAB ONLY BILL - sends to bills table
                // ================================================================
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
                    // Create consultation bill
                    $bill_result = createVisitBill($db, $patient_id, $visit_id, $service_name, $consultation_fee, $user_id, $selected_branch_id);
                    if ($bill_result && $bill_result['status'] !== 'error') {
                        $bill_created = true;
                        $bill_number = $bill_result['bill_number'];
                    }
                }
                
                // CREATE LAB TESTS
                $lab_created = false;
                $lab_test_ids_created = [];
                if (!empty($lab_test_ids)) {
                    $test_ids_imploded = implode(',', array_map('intval', $lab_test_ids));
                    $stmt = $db->prepare("SELECT id, test_name, price FROM lab_tests_catalog WHERE id IN ($test_ids_imploded)");
                    $stmt->execute();
                    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($tests as $test) {
                        $stmt = $db->prepare("
                            INSERT INTO lab_tests (
                                visit_id, patient_id, doctor_id, test_id, test_name, 
                                test_price, status, branch_id, created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
                        ");
                        $stmt->execute([
                            $visit_id,
                            $patient_id,
                            $is_lab_only ? null : ($doctor_id > 0 ? $doctor_id : null),
                            $test['id'],
                            $test['test_name'],
                            $test['price'],
                            $selected_branch_id
                        ]);
                        $lab_created = true;
                        $lab_test_ids_created[] = $test['id'];
                    }
                }
                
                // SAVE VITAL SIGNS
                $temperature = $_POST['temperature'] ?? null;
                $bp_systolic = $_POST['bp_systolic'] ?? null;
                $bp_diastolic = $_POST['bp_diastolic'] ?? null;
                $pulse_rate = $_POST['pulse_rate'] ?? null;
                $weight = $_POST['weight'] ?? null;
                $height = $_POST['height'] ?? null;
                $vital_notes = trim($_POST['vital_notes'] ?? '');
                
                $has_vital = $temperature !== null && $temperature !== '' || 
                             $bp_systolic !== null && $bp_systolic !== '' || 
                             $bp_diastolic !== null && $bp_diastolic !== '' || 
                             $pulse_rate !== null && $pulse_rate !== '' || 
                             $weight !== null && $weight !== '' || 
                             $height !== null && $height !== '';
                
                if ($has_vital && $visit_id) {
                    $bmi = null;
                    if ($weight && $height && $height > 0) {
                        $height_m = $height / 100;
                        $bmi = round($weight / ($height_m * $height_m), 1);
                    }
                    
                    $stmt = $db->prepare("
                        INSERT INTO vital_signs (
                            patient_id, visit_id, recorded_by, branch_id,
                            temperature, blood_pressure_systolic, blood_pressure_diastolic,
                            pulse_rate, weight, height, bmi, notes, recorded_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $patient_id,
                        $visit_id,
                        $user_id,
                        $selected_branch_id,
                        $temperature ?: null,
                        $bp_systolic ?: null,
                        $bp_diastolic ?: null,
                        $pulse_rate ?: null,
                        $weight ?: null,
                        $height ?: null,
                        $bmi,
                        $vital_notes ?: null
                    ]);
                }
                
                $db->commit();
                
                // BUILD RESPONSE
                $bill_message = '';
                if ($bill_created && $bill_number) {
                    $bill_message = ' 💰 Bill #' . $bill_number . ' sent to Cashier!';
                } elseif ($is_lab_only && empty($lab_test_ids)) {
                    $bill_message = ' ⚠️ No lab tests selected';
                }
                
                $lab_text = '';
                if ($lab_created) {
                    $lab_text = ' 🧪 ' . count($lab_test_ids) . ' lab test(s) requested!';
                    if ($total_lab_fee > 0) {
                        $lab_text .= ' (TSh ' . number_format($total_lab_fee, 0) . ')';
                    }
                }
                
                $doctor_text = '';
                if ($doctor_id > 0 && !$is_lab_only) {
                    $online_text = $doctor_online == 1 ? '🟢 Online' : '⚪ Offline';
                    $doctor_text = "Doctor <strong>$doctor_name</strong> ($online_text) assigned - $service_name";
                } else if ($is_lab_only && !empty($lab_test_ids)) {
                    $doctor_text = "🧪 Lab tests requested - No doctor assigned";
                } else {
                    $doctor_text = "✅ Patient processed - " . ($is_lab_only ? 'Lab only' : 'Doctor assigned');
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
                $response['visit_type'] = $visit_type_to_store;
                
            } catch (Exception $e) {
                $db->rollBack();
                $response['message'] = '❌ Error: ' . $e->getMessage();
                error_log("ERROR in change_doctor: " . $e->getMessage());
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

// ================================================================
// COMMON SYMPTOMS
// ================================================================
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
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ===== CSS STYLES - IMPROVED WITH 6 CARDS (3 LEFT, 3 RIGHT) ===== */
        :root {
            --primary: #2563EB;
            --primary-dark: #1D4ED8;
            --primary-light: #60A5FA;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #2563EB, #1D4ED8);
            --success: #059669;
            --success-dark: #047857;
            --success-light: #34D399;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-dark: #5B21B6;
            --purple-light: #A78BFA;
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
            --shadow-xl: 0 20px 30px rgba(0,0,0,0.12);
            --bg-body: #F0F4F8;
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
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-bg: #1E3A5F;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
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
        
        /* ===== PAGE HEADER ===== */
        .page-header {
            background: var(--primary-gradient);
            border-radius: var(--radius-lg);
            padding: 20px 28px;
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
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i { font-size: 1.4rem; opacity: 0.9; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 2px;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.55rem;
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
            padding: 5px 14px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.7rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ===== FORM CARD - 6 CARDS (3 LEFT, 3 RIGHT) ===== */
        .form-card-modern {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            max-width: 1200px;
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
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .form-card-modern .form-header .form-icon {
            width: 44px;
            height: 44px;
            background: var(--primary-gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
            flex-shrink: 0;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.2);
        }
        
        .form-card-modern .form-header .form-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-card-modern .form-header .form-subtitle {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        /* ===== 6 CARDS GRID - 3 LEFT, 3 RIGHT ===== */
        .form-grid-6 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-grid-left {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .form-grid-right {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .form-card-item {
            background: var(--bg-body);
            border-radius: var(--radius);
            padding: 16px 18px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            min-height: 120px;
            display: flex;
            flex-direction: column;
        }
        
        .form-card-item:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-sm);
        }
        
        .form-card-item .card-item-title {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .form-card-item .card-item-title i {
            color: var(--primary);
            font-size: 0.8rem;
        }
        
        .form-card-item .card-item-title .required {
            color: var(--danger);
            margin-left: 2px;
        }
        
        .form-card-item .card-item-title .badge-label {
            font-size: 0.5rem;
            font-weight: 400;
            padding: 1px 8px;
            border-radius: 10px;
            background: var(--gray-200);
            color: var(--text-secondary);
            margin-left: 4px;
        }
        
        /* ===== FORM CONTROLS ===== */
        .form-control-modern {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.75rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748B' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 10px;
            cursor: pointer;
            min-height: 38px;
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
            padding: 4px 0;
        }
        
        .form-control-modern option {
            padding: 4px 8px;
            font-size: 0.7rem;
            color: var(--text-primary);
        }
        
        .form-control-modern option:hover {
            background: var(--primary-bg);
        }
        
        .form-control-modern.textarea {
            min-height: 60px;
            resize: vertical;
        }
        
        /* ===== DOCTOR SELECT CARD - HIDDEN IN LAB MODE ===== */
        #doctorSelectCard {
            transition: all 0.3s ease;
        }
        
        #doctorSelectCard[style*="display: none"] {
            opacity: 0;
            height: 0;
            overflow: hidden;
            padding: 0;
            margin: 0;
            min-height: 0;
        }
        
        /* ===== LAB SECTION INSIDE CARD ===== */
        .lab-modal-container-modern {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 2px solid var(--purple);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            margin-top: 6px;
        }
        
        .lab-modal-header-modern {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 12px;
            background: var(--purple-bg);
            border-bottom: 2px solid var(--border-color);
        }
        
        .lab-modal-header-modern .lab-modal-title {
            font-weight: 600;
            font-size: 0.7rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .lab-test-item-modern {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 10px;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s ease;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .lab-test-item-modern:hover {
            background: var(--primary-bg);
        }
        
        .lab-test-item-modern:last-child {
            border-bottom: none;
        }
        
        .lab-test-item-modern .lab-test-checkbox {
            width: 14px;
            height: 14px;
            accent-color: var(--purple);
            cursor: pointer;
            flex-shrink: 0;
        }
        
        .lab-test-item-modern label {
            cursor: pointer;
            flex: 1;
            display: flex;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap;
            font-size: 0.7rem;
        }
        
        .lab-test-item-modern label strong {
            font-size: 0.7rem;
            color: var(--text-primary);
        }
        
        .lab-test-item-modern .lab-test-category {
            font-size: 0.45rem;
            background: var(--gray-200);
            color: var(--text-secondary);
            padding: 1px 6px;
            border-radius: 8px;
        }
        
        .lab-test-item-modern .lab-test-price {
            font-size: 0.6rem;
            color: var(--success);
            font-weight: 600;
            white-space: nowrap;
            margin-left: auto;
        }
        
        .lab-test-item-modern.checked {
            background: var(--purple-bg);
            border-left: 3px solid var(--purple);
        }
        
        .lab-modal-footer-modern {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 10px;
            border-top: 2px solid var(--border-color);
            background: var(--bg-body);
            flex-wrap: wrap;
            gap: 4px;
        }
        
        .lab-modal-footer-modern .lab-total-price {
            font-size: 0.6rem;
            font-weight: 600;
            color: var(--success);
            padding: 2px 8px;
            background: var(--success-bg);
            border-radius: 16px;
        }
        
        .lab-test-scroll {
            max-height: 180px;
            overflow-y: auto;
            padding: 2px 0;
        }
        
        /* ===== VITAL SIGNS ===== */
        .vital-grid-modern {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        
        .vital-item-modern {
            background: var(--bg-body);
            border-radius: var(--radius);
            padding: 6px 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .vital-item-modern .vital-label {
            font-size: 0.5rem;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
        }
        
        .vital-item-modern .vital-input {
            border: none;
            background: transparent;
            padding: 2px 0;
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--text-primary);
            outline: none;
            width: 100%;
        }
        
        .vital-item-modern .vital-unit { font-size: 0.45rem; color: var(--text-secondary); display: block; }
        .vital-item-modern.bmi-item { background: var(--primary-bg); border-color: var(--primary); }
        
        /* ===== BUTTONS ===== */
        .btn-modern {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
            min-height: 38px;
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
        
        .btn-modern-sm { padding: 4px 12px; font-size: 0.6rem; min-height: 28px; border-radius: 6px; }
        .btn-modern-purple { background: var(--purple); color: white; }
        .btn-modern-purple:hover { background: var(--purple-dark); }
        
        .form-actions-modern {
            display: flex;
            gap: 10px;
            padding-top: 16px;
            margin-top: 16px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        /* ===== STATUS BADGES ===== */
        .status-badge-dropdown {
            display: inline-block;
            font-size: 0.45rem;
            font-weight: 500;
            padding: 1px 6px;
            border-radius: 6px;
            margin-left: 4px;
        }
        .status-badge-dropdown.pending { background: #FEF3C7; color: #D97706; }
        .status-badge-dropdown.assigned { background: #D1FAE5; color: #059669; }
        .status-badge-dropdown.lab_only { background: #EDE9FE; color: #7C3AED; border: 1px dashed #7C3AED; }
        .status-badge-dropdown.no_visit { background: var(--gray-200); color: var(--gray-600); }
        
        /* ===== DAYS BADGES ===== */
        .days-badge-blue {
            display: inline-block;
            background: var(--primary) !important;
            color: #ffffff !important;
            padding: 1px 8px !important;
            border-radius: 10px !important;
            font-size: 0.5rem !important;
            font-weight: 600 !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }
        .days-badge-blue.new {
            background: var(--success) !important;
            box-shadow: 0 2px 4px rgba(5, 150, 105, 0.2);
        }
        .assigned-days-badge-blue {
            display: inline-block;
            background: var(--primary) !important;
            color: #ffffff !important;
            padding: 1px 8px !important;
            border-radius: 10px !important;
            font-size: 0.5rem !important;
            font-weight: 600 !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }
        .assigned-days-badge-blue.new {
            background: var(--success) !important;
            box-shadow: 0 2px 4px rgba(5, 150, 105, 0.2);
        }
        
        /* ===== TOAST ===== */
        .toast-modern {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: var(--radius);
            z-index: 999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: var(--shadow-lg);
            font-size: 0.75rem;
        }
        
        .toast-modern.show { transform: translateY(0); opacity: 1; }
        .toast-modern.success { background: var(--success); }
        .toast-modern.error { background: var(--danger); }
        .toast-modern.info { background: var(--primary); }
        .toast-modern.warning { background: var(--warning); }
        
        /* ===== FOOTER ===== */
        .footer-modern {
            padding: 12px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.6rem;
            color: var(--text-secondary);
        }
        
        .footer-modern .footer-brand { color: var(--primary); font-weight: 500; }
        
        /* ===== ANIMATIONS ===== */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
        
        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .live-indicator-modern {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #34D399;
            animation: pulse-dot 1.5s infinite;
            margin-right: 4px;
        }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.8); }
        }
        
        /* ===== AUTO-REMOVE NOTIFICATION ===== */
        .auto-remove-notification {
            background: var(--success-bg);
            border: 2px solid var(--success);
            border-radius: var(--radius);
            padding: 8px 14px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.7rem;
            color: var(--success);
            transition: all 0.5s ease;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .auto-remove-notification.hide {
            opacity: 0;
            transform: translateY(-20px);
            max-height: 0;
            padding: 0 14px;
            margin-bottom: 0;
            overflow: hidden;
            border: none;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 14px; }
            .form-card-modern { padding: 16px; }
            .form-grid-6 { grid-template-columns: 1fr; gap: 14px; }
        }
        
        @media (max-width: 768px) {
            .form-card-modern { padding: 12px; }
            .page-header { padding: 12px 14px; }
            .page-header .page-title { font-size: 1rem; }
            .vital-grid-modern { grid-template-columns: repeat(2, 1fr); }
            .form-actions-modern { flex-direction: column; }
            .form-actions-modern .btn-modern { width: 100%; justify-content: center; }
            .form-grid-left { gap: 12px; }
            .form-grid-right { gap: 12px; }
            .form-card-item { min-height: 100px; padding: 12px 14px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 6px; }
            .form-card-modern { padding: 8px; }
            .vital-grid-modern { grid-template-columns: 1fr 1fr; }
            .page-header .header-badge { font-size: 0.45rem; padding: 1px 6px; }
            .form-card-item { min-height: 80px; padding: 10px 12px; }
            .form-card-item .card-item-title { font-size: 0.6rem; }
            .form-control-modern { font-size: 0.65rem; padding: 6px 10px; min-height: 32px; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
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
            <span id="clockDisplay" style="font-weight:500;font-size:0.75rem;"><?= date('d M Y • h:i:s A') ?></span>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText" style="font-size:0.7rem;">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-md"></i>
                Assign / Change Doctor
                <span class="role-badge-display">RECEPTION</span>
                <span class="update-badge-light">
                    <span class="live-indicator-modern"></span> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                Select patient, assign doctor or request lab tests in <strong><?= htmlspecialchars($branch_name) ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-user-md"></i>
                    <span class="online-count" id="onlineDoctorCount"><?= $online_doctors_count ?></span> Online
                </span>
                <span class="header-badge">
                    <i class="fas fa-user-md"></i>
                    <span class="offline-count" id="offlineDoctorCount"><?= $offline_doctors_count ?></span> Offline
                </span>
                <span class="header-badge">
                    <i class="fas fa-user-clock"></i>
                    <span id="pendingCount"><?= $pending_count ?></span> Pending
                </span>
                <span class="header-badge">
                    <i class="fas fa-user-check"></i>
                    <span id="assignedCount"><?= $assigned_count ?></span> Assigned
                </span>
                <span class="header-badge" style="background:rgba(124,58,237,0.2);border-color:rgba(124,58,237,0.3);color:#7C3AED;">
                    <i class="fas fa-flask"></i>
                    <span id="labOnlyCount"><?= $lab_only_count ?></span> Lab Only
                </span>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert-modern" style="max-width:1200px;margin:0 auto 12px;padding:8px 14px;border-radius:var(--radius);background:<?= $message_type === 'success' ? 'var(--success-bg)' : 'var(--danger-bg)' ?>;color:<?= $message_type === 'success' ? 'var(--success)' : 'var(--danger)' ?>;border:2px solid <?= $message_type === 'success' ? 'var(--success)' : 'var(--danger)' ?>;display:flex;align-items:center;gap:6px;font-size:0.75rem;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- AUTO-REMOVE NOTIFICATION -->
    <div class="auto-remove-notification" id="autoRemoveNotification">
        <i class="fas fa-check-circle" style="color:var(--success);font-size:0.9rem;"></i>
        <span style="font-weight:500;color:var(--text-primary);font-size:0.65rem;">
            ✅ Patients with completed consultations or completed lab results are <strong>automatically removed</strong> from these lists.
        </span>
        <span style="font-size:0.55rem;color:var(--text-secondary);margin-left:auto;">
            <i class="fas fa-info-circle"></i> Only active patients shown
        </span>
    </div>

    <!-- ================================================================ -->
    <!-- ASSIGNED PATIENTS LIST -->
    <!-- ================================================================ -->
    <div class="modern-card animate-fade-in-up" style="max-width:1200px;margin:0 auto 16px;background:var(--bg-card);border-radius:var(--radius-lg);padding:16px 20px;border:1px solid var(--border-color);box-shadow:var(--shadow-md);">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px;padding-bottom:8px;border-bottom:2px solid var(--border-color);">
            <div class="card-title" style="font-size:0.8rem;font-weight:600;color:var(--text-primary);display:flex;align-items:center;gap:6px;">
                <i class="fas fa-user-check"></i>
                Assigned Patients (With Doctor)
                <span class="card-badge success" style="background:var(--success-bg);color:var(--success);padding:1px 8px;border-radius:20px;font-size:0.55rem;font-weight:500;" id="assignedListCount"><?= $assigned_count ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:4px;">
                <span class="text-xs text-gray-400" style="font-size:0.55rem;" id="assignedListUpdate">(Auto-updated <?= date('h:i:s A') ?>)</span>
                <span class="text-xs text-green-500" style="font-size:0.55rem;">
                    <span class="live-indicator-modern"></span> Live
                </span>
            </div>
        </div>
        
        <div id="assignedPatientsList">
            <?php if (count($assigned_patients) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.7rem;">
                        <thead>
                            <tr style="border-bottom:2px solid var(--border-color);">
                                <th style="padding:6px 8px;text-align:left;font-weight:500;font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);">Patient / Service</th>
                                <th style="padding:6px 8px;text-align:left;font-weight:500;font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);">Patient ID</th>
                                <th style="padding:6px 8px;text-align:left;font-weight:500;font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);">Assigned Doctor</th>
                                <th style="padding:6px 8px;text-align:left;font-weight:500;font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);">Status</th>
                                <th style="padding:6px 8px;text-align:left;font-weight:500;font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);">Action</th>
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
                                    <td style="padding:6px 8px;font-weight:500;font-size:0.7rem;">
                                        <?= htmlspecialchars($patient['full_name']) ?>
                                        <?= $days_text ?>
                                        <span class="text-xs text-gray-400 block" style="font-size:0.6rem;"><?= htmlspecialchars($patient['visit_type'] ?? 'Consultation') ?></span>
                                    </td>
                                    <td style="padding:6px 8px;font-family:monospace;font-size:0.65rem;"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                                    <td style="padding:6px 8px;">
                                        <?php if (!empty($patient['assigned_doctor_name'])): ?>
                                            <span class="assigned-doctor-tag-modern" style="font-size:0.7rem;">
                                                <i class="fas fa-user-md"></i>
                                                Dr. <?= htmlspecialchars($patient['assigned_doctor_name']) ?>
                                                <?php if ($patient['assigned_doctor_online'] == 1): ?>
                                                    <span class="text-green-500 text-xs">🟢</span>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-xs">⚪</span>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">No doctor</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:6px 8px;">
                                        <span class="status-badge-dropdown assigned">✅ Assigned</span>
                                    </td>
                                    <td style="padding:6px 8px;">
                                        <button onclick="selectPatientAndChange(<?= $patient['id'] ?>)" class="btn-modern btn-modern-warning btn-modern-sm" style="padding:2px 8px;font-size:0.6rem;">
                                            <i class="fas fa-sync-alt"></i> Change
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-3 text-gray-400" id="noAssignedPatients">
                    <i class="fas fa-user-check text-2xl block mb-2" style="font-size:1.2rem;"></i>
                    <p style="font-size:0.7rem;">No patients currently assigned to a doctor</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- LAB TESTS CARD -->
    <!-- ================================================================ -->
    <div class="modern-card animate-fade-in-up" style="animation-delay:0.15s;max-width:1200px;margin:0 auto 16px;background:var(--bg-card);border-radius:var(--radius-lg);padding:16px 20px;border:1px solid var(--border-color);box-shadow:var(--shadow-md);border-color:var(--purple);">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px;padding-bottom:8px;border-bottom:2px solid var(--border-color);">
            <div class="card-title" style="font-size:0.8rem;font-weight:600;color:var(--text-primary);display:flex;align-items:center;gap:6px;">
                <i class="fas fa-flask" style="color:var(--purple);"></i>
                Lab Test Requests (No Doctor)
                <span class="card-badge lab-badge" style="background:var(--purple-bg);color:var(--purple);padding:1px 8px;border-radius:20px;font-size:0.55rem;font-weight:500;" id="labOnlyListCount"><?= $lab_only_count ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:4px;">
                <span class="text-xs text-gray-400" style="font-size:0.55rem;" id="labOnlyUpdate">(Auto-updated <?= date('h:i:s A') ?>)</span>
                <span class="text-xs text-purple-500" style="font-size:0.55rem;">
                    <span class="live-indicator-modern"></span> Live
                </span>
            </div>
        </div>
        
        <div id="labOnlyPatientsList">
            <?php if (count($lab_only_patients) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.7rem;">
                        <thead>
                            <tr style="border-bottom:2px solid var(--border-color);">
                                <th style="padding:6px 8px;text-align:left;font-weight:500;font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);">Patient / Days</th>
                                <th style="padding:6px 8px;text-align:left;font-weight:500;font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);">Patient ID</th>
                                <th style="padding:6px 8px;text-align:left;font-weight:500;font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);">Type</th>
                                <th style="padding:6px 8px;text-align:left;font-weight:500;font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);">Status</th>
                                <th style="padding:6px 8px;text-align:left;font-weight:500;font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);">Action</th>
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
                                    if ($lab_result) {
                                        $lab_test_id = $lab_result['id'];
                                        $lab_test_name = $lab_result['test_name'];
                                    }
                                } catch (Exception $e) {}
                                
                                $status_label = '🧪 Pending';
                                $status_class = 'lab_only';
                                
                                if ($lab_test_id > 0) {
                                    $view_link = '<a href="lab_tests.php?id=' . $lab_test_id . '" class="btn-modern btn-modern-purple btn-modern-sm" style="padding:2px 8px;font-size:0.6rem;background:var(--purple);color:white;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:3px;">
                                        <i class="fas fa-eye"></i> View
                                    </a>';
                                } else {
                                    $view_link = '<span class="text-gray-400 text-xs">No test</span>';
                                }
                            ?>
                                <tr id="lab-row-<?= $patient['id'] ?>" style="border-bottom:1px solid var(--border-color);">
                                    <td style="padding:6px 8px;font-weight:500;font-size:0.7rem;">
                                        <?= htmlspecialchars($patient['full_name']) ?>
                                        <?= $days_text ?>
                                        <?php if ($lab_test_id > 0): ?>
                                            <span class="text-xs text-purple-500 block" style="font-size:0.6rem;"><?= htmlspecialchars($lab_test_name) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:6px 8px;font-family:monospace;font-size:0.65rem;"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                                    <td style="padding:6px 8px;">
                                        <span class="lab-only-tag-modern" style="font-size:0.7rem;">
                                            <i class="fas fa-flask"></i>
                                            Lab Only
                                            <span class="text-xs" style="color:var(--purple);">🧪</span>
                                        </span>
                                    </td>
                                    <td style="padding:6px 8px;">
                                        <span class="status-badge-dropdown <?= $status_class ?>"><?= $status_label ?></span>
                                    </td>
                                    <td style="padding:6px 8px;">
                                        <?= $view_link ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-3 text-gray-400" id="noLabPatients">
                    <i class="fas fa-flask text-2xl block mb-2" style="font-size:1.2rem;color:var(--purple);"></i>
                    <p style="font-size:0.7rem;">No lab test requests pending</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ASSIGN FORM - 6 CARDS (3 LEFT, 3 RIGHT) -->
    <!-- ================================================================ -->
    <div class="form-card-modern animate-fade-in-up <?= $change_mode ? 'change-mode-active-modern' : '' ?>" id="mainFormCard" style="animation-delay:0.1s;">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas <?= $change_mode ? 'fa-sync-alt' : 'fa-stethoscope' ?>"></i>
            </div>
            <div>
                <h3 class="form-title">
                    <?= $change_mode ? '🔄 Change Doctor' : 'Assign / Change Doctor or Lab Test' ?>
                    <?php if ($change_mode && $selected_patient_data): ?>
                        <span class="text-xs text-yellow-500" style="font-weight:400;font-size:0.65rem;">
                            - Changing: <?= htmlspecialchars($selected_patient_data['full_name']) ?>
                            <?php if (!empty($selected_patient_data['assigned_doctor_name'])): ?>
                                (Current: Dr. <?= htmlspecialchars($selected_patient_data['assigned_doctor_name']) ?>)
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </h3>
                <p class="form-subtitle">
                    <?php if ($change_mode): ?>
                        <span class="text-yellow-500">🔄 Change Mode:</span> Select new doctor for patient
                    <?php else: ?>
                        <span class="text-gray-500">Select patient and assign a doctor OR request lab tests</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <form method="POST" action="" id="assignForm">
            <input type="hidden" name="action" value="change_doctor">
            
            <!-- ================================================================ -->
            <!-- 6 CARDS GRID - 3 LEFT, 3 RIGHT -->
            <!-- ================================================================ -->
            <div class="form-grid-6">
                
                <!-- ===== LEFT SIDE - 3 CARDS ===== -->
                <div class="form-grid-left">
                    
                    <!-- CARD 1: Select Patient -->
                    <div class="form-card-item" style="min-height:130px;">
                        <div class="card-item-title">
                            <i class="fas fa-user"></i> Select Patient <span class="required">*</span>
                            <span class="badge-label">All Patients - Newest First</span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            <select name="patient_id" class="form-control-modern" required id="patientSelect" <?= $change_mode ? 'style="border-color:var(--warning);"' : '' ?>>
                                <option value="">-- Select Patient --</option>
                                <?php if (!empty($all_patients) && is_array($all_patients) && count($all_patients) > 0): ?>
                                    <optgroup label="📋 All Patients (<?= count($all_patients) ?> - Newest First)">
                                        <?php foreach ($all_patients as $patient): 
                                            $status_label = '📋 No Visit';
                                            $status_class = 'no_visit';
                                            $status_icon = '📋';
                                            
                                            if (!empty($patient['visit_id'])) {
                                                if ($patient['visit_status'] === 'lab_test' && empty($patient['visit_doctor_id'])) {
                                                    $status_label = '🧪 Lab Only';
                                                    $status_class = 'lab_only';
                                                    $status_icon = '🧪';
                                                } elseif (in_array($patient['visit_status'], ['new', 'pending'])) {
                                                    $status_label = '⏳ Pending';
                                                    $status_class = 'pending';
                                                    $status_icon = '⏳';
                                                } elseif ($patient['visit_status'] === 'assigned' || $patient['visit_status'] === 'with_doctor') {
                                                    $status_label = '✅ Assigned';
                                                    $status_class = 'assigned';
                                                    $status_icon = '✅';
                                                }
                                            }
                                            
                                            $doctor_info = '';
                                            if (!empty($patient['assigned_doctor_name'])) {
                                                $online_status = !empty($patient['assigned_doctor_online']) ? '🟢' : '⚪';
                                                $doctor_info = ' 👨‍⚕️ Dr. ' . htmlspecialchars($patient['assigned_doctor_name']) . ' ' . $online_status;
                                            }
                                            
                                            $selected = ($selected_patient_id == $patient['id']) ? 'selected' : '';
                                            
                                            $days = isset($patient['patient_days']) ? (int)$patient['patient_days'] : 0;
                                            $days_text = $days > 0 ? '<span class="days-badge-blue">📅 ' . $days . ' days</span>' : '<span class="days-badge-blue new">📅 New</span>';
                                            
                                            $assigned_days_text = '';
                                            if (!empty($patient['visit_id']) && !empty($patient['visit_created_at'])) {
                                                $assigned_days = (int)floor((time() - strtotime($patient['visit_created_at'])) / 86400);
                                                $assigned_days_text = ' <span class="assigned-days-badge-blue">Visit: ' . $assigned_days . ' days ago</span>';
                                            }
                                        ?>
                                            <option value="<?= $patient['id'] ?>" data-status="<?= $status_class ?>" data-doctor="<?= htmlspecialchars($patient['assigned_doctor_name'] ?? '') ?>" <?= $selected ?>>
                                                <?= $status_icon ?> <?= htmlspecialchars($patient['full_name']) ?> (<?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>)
                                                <?php if (!empty($patient['phone'])): ?>
                                                    - <?= htmlspecialchars($patient['phone']) ?>
                                                <?php endif; ?>
                                                <?= $days_text ?>
                                                <?= $assigned_days_text ?>
                                                <?= $doctor_info ?>
                                                <span class="status-badge-dropdown <?= $status_class ?>"><?= $status_label ?></span>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php else: ?>
                                    <option value="" disabled>No patients found</option>
                                <?php endif; ?>
                            </select>
                            
                            <div class="mt-1 text-xs text-gray-400" id="patientStats" style="font-size:0.55rem;">
                                <?php if ($pending_count > 0): ?>
                                    <span class="text-yellow-500">🟡 <span id="pendingStat"><?= $pending_count ?></span> Pending</span>
                                    <span class="mx-1">|</span>
                                <?php endif; ?>
                                <?php if ($assigned_count > 0): ?>
                                    <span class="text-green-500">✅ <span id="assignedStat"><?= $assigned_count ?></span> Assigned</span>
                                    <span class="mx-1">|</span>
                                <?php endif; ?>
                                <?php if ($lab_only_count > 0): ?>
                                    <span class="text-purple-500">🧪 <span id="labOnlyStat"><?= $lab_only_count ?></span> Lab Only</span>
                                    <span class="mx-1">|</span>
                                <?php endif; ?>
                                <span class="text-gray-400">Total: <?= count($all_patients) ?> patients</span>
                                <span class="text-xs text-green-500 ml-2" id="liveUpdateStatus">🔄 Live</span>
                            </div>
                            
                            <div id="selectedPatientInfo" class="mt-2 p-2 bg-primary-bg rounded-lg border border-primary-light" style="display:<?= $selected_patient_id > 0 && $selected_patient_data ? 'block' : 'none' ?>;padding:4px 8px;">
                                <?php if ($selected_patient_data): 
                                    $patient_days = isset($selected_patient_data['patient_days']) ? (int)$selected_patient_data['patient_days'] : 0;
                                    $days_text = $patient_days > 0 ? '<span class="days-badge-blue">📅 ' . $patient_days . ' days ago</span>' : '<span class="days-badge-blue new">📅 Just registered</span>';
                                ?>
                                    <div class="flex items-center gap-2 text-sm flex-wrap" style="font-size:0.65rem;">
                                        <i class="fas fa-user-circle text-primary"></i>
                                        <span class="font-semibold"><?= htmlspecialchars($selected_patient_data['full_name'] ?? '') ?></span>
                                        <span class="text-gray-400">|</span>
                                        <span><?= htmlspecialchars($selected_patient_data['patient_id'] ?? '') ?></span>
                                        <?= $days_text ?>
                                        <?php if (!empty($selected_patient_data['assigned_doctor_name'])): ?>
                                            <span class="assigned-doctor-tag-modern" style="font-size:0.6rem;">
                                                <i class="fas fa-user-md"></i>
                                                Dr. <?= htmlspecialchars($selected_patient_data['assigned_doctor_name']) ?>
                                                <?php if ($selected_patient_data['assigned_doctor_online'] == 1): ?>
                                                    <span class="text-green-500 text-xs">🟢</span>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-xs">⚪</span>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">No doctor assigned</span>
                                        <?php endif; ?>
                                        <?php if (!empty($selected_patient_data['visit_type'])): ?>
                                            <span class="text-xs text-primary">📋 <?= htmlspecialchars($selected_patient_data['visit_type']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($change_mode): ?>
                                            <span class="text-xs text-yellow-500 font-bold" style="background:var(--warning-bg);padding:1px 6px;border-radius:12px;font-size:0.5rem;">
                                                🔄 Change Mode
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- CARD 2: Select Action -->
                    <div class="form-card-item" style="min-height:110px;">
                        <div class="card-item-title">
                            <i class="fas fa-tasks"></i> Select Action <span class="required">*</span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            <select name="assignment_type" class="form-control-modern" required id="assignmentTypeSelect" onchange="toggleAssignmentType(this.value)">
                                <option value="doctor" <?= $change_mode ? 'selected' : '' ?>>👨‍⚕️ Assign / Lab Test</option>
                                <option value="lab">🧪 Request Lab Test(s) (No Doctor Required)</option>
                            </select>
                            <p class="text-xs text-gray-400 mt-1" id="assignmentTypeHelp" style="font-size:0.55rem;">👨‍⚕️ Assign a doctor to the patient or change existing doctor</p>
                        </div>
                    </div>
                    
                    <!-- CARD 3: Select Doctor (HIDDEN IN LAB MODE) -->
                    <div class="form-card-item" id="doctorSelectCard" style="min-height:110px;">
                        <div class="card-item-title">
                            <i class="fas fa-user-md"></i> Select Doctor <span class="required" id="doctorRequired">*</span>
                            <span class="badge-label">Online/Offline</span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            <select name="doctor_id" class="form-control-modern" required id="doctorSelect">
                                <option value="">-- Select Doctor --</option>
                                
                                <?php if (!empty($online_doctors) && count($online_doctors) > 0): ?>
                                    <optgroup label="🟢 Online Doctors (<?= $online_doctors_count ?>)" style="font-weight:600;color:#059669;">
                                        <?php foreach ($online_doctors as $doctor): ?>
                                            <option value="<?= $doctor['id'] ?>" data-online="1" style="font-weight:500;color:#059669;padding:4px;">
                                                🟢 Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                                                <?php if (!empty($doctor['specialty'])): ?>
                                                    (<?= htmlspecialchars($doctor['specialty']) ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                
                                <?php if (!empty($offline_doctors) && count($offline_doctors) > 0): ?>
                                    <optgroup label="⚪ Offline Doctors (<?= $offline_doctors_count ?>)" style="font-weight:600;color:var(--text-secondary);">
                                        <?php foreach ($offline_doctors as $doctor): ?>
                                            <option value="<?= $doctor['id'] ?>" data-online="0" style="color:var(--text-secondary);padding:4px;">
                                                ⚪ Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                                                <?php if (!empty($doctor['specialty'])): ?>
                                                    (<?= htmlspecialchars($doctor['specialty']) ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                
                                <?php if (empty($online_doctors) && empty($offline_doctors)): ?>
                                    <option value="" disabled>No doctors available</option>
                                <?php endif; ?>
                            </select>
                            
                            <p class="text-xs text-gray-400 mt-1" id="doctorAvailability" style="font-size:0.55rem;">
                                <i class="fas fa-info-circle mr-1"></i> 
                                <span class="text-green-500" id="onlineCountDisplay">🟢 <?= $online_doctors_count ?> online</span>
                                <span class="text-gray-400 mx-1">|</span>
                                <span class="text-gray-500" id="offlineCountDisplay">⚪ <?= $offline_doctors_count ?> offline</span>
                                <span class="text-xs text-gray-400 ml-2" style="font-size:0.5rem;" id="lastDoctorUpdate">Updated: <?= date('H:i:s') ?></span>
                            </p>
                        </div>
                    </div>
                    
                </div>
                
                <!-- ===== RIGHT SIDE - 3 CARDS ===== -->
                <div class="form-grid-right">
                    
                    <!-- CARD 4: Visit Type -->
                    <div class="form-card-item" id="visitTypeSection" style="min-height:110px;">
                        <div class="card-item-title">
                            <i class="fas fa-tag"></i> Visit Type (Service) <span class="required">*</span>
                            <span class="badge-label" id="visitTypePrice" style="font-size:0.5rem;">
                                <?php 
                                if (isset($visit_type_options[$default_service_id])) {
                                    echo 'Fee: TSh ' . number_format($visit_type_options[$default_service_id]['price'] ?? 0, 0);
                                } else {
                                    echo 'Fee: TSh 0';
                                }
                                ?>
                            </span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            <select name="service_id" class="form-control-modern" id="visitTypeSelect" onchange="updateVisitTypePrice()">
                                <?php if (!empty($visit_type_options) && is_array($visit_type_options)): ?>
                                    <?php foreach ($visit_type_options as $service_id => $option): 
                                        $price = $option['price'] ?? 0;
                                        $icon = $option['icon'] ?? '🏥';
                                        $service_name = $option['service_name'] ?? 'Consultation';
                                        $selected = ($service_id === $default_service_id) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $service_id ?>" data-price="<?= $price ?>" data-service-name="<?= htmlspecialchars($service_name) ?>" <?= $selected ?>>
                                            <?= $icon ?> <?= htmlspecialchars($service_name) ?> - TSh <?= number_format($price, 0) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" data-price="0" selected disabled>❌ No Visit Type Available</option>
                                <?php endif; ?>
                            </select>
                            <p class="text-xs text-gray-400 mt-1" id="visitTypeDescription" style="font-size:0.55rem;">
                                <i class="fas fa-info-circle mr-1"></i> 
                                <?php if (isset($visit_type_options[$default_service_id])): ?>
                                    <?= $visit_type_options[$default_service_id]['description'] ?? 'Select a visit type' ?>
                                <?php else: ?>
                                    No consultation services available for this branch
                                <?php endif; ?>
                            </p>
                            <div id="feeNote" class="mt-1 text-xs text-blue-500" style="font-size:0.55rem;">
                                👨‍⚕️ Consultation Mode: Doctor required, Consultation fee applies
                            </div>
                            <span id="consultationFeeDisplay" style="font-size:0.5rem;color:var(--text-secondary);margin-top:2px;"></span>
                        </div>
                    </div>
                    
                    <!-- CARD 5: Symptoms -->
                    <div class="form-card-item" style="min-height:120px;">
                        <div class="card-item-title">
                            <i class="fas fa-notes-medical"></i> Symptoms <span class="badge-label">Reception fills this</span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;gap:6px;">
                            <select name="symptoms_select" class="form-control-modern" id="symptomsSelect" style="min-height:32px;">
                                <option value="">-- Select Common Symptom --</option>
                                <?php foreach ($common_symptoms as $symptom): ?>
                                    <option value="<?= htmlspecialchars($symptom) ?>"><?= htmlspecialchars($symptom) ?></option>
                                <?php endforeach; ?>
                                <option value="other">✏️ Other (Type below)</option>
                            </select>
                            <textarea name="symptoms" class="form-control-modern textarea" placeholder="Describe patient symptoms in detail..." id="symptomsTextarea" rows="2" style="font-size:0.65rem;padding:5px 10px;min-height:50px;"></textarea>
                        </div>
                    </div>
                    
                    <!-- CARD 6: Lab Tests (Hidden by default) -->
                    <div class="form-card-item" id="labSection" style="display:none;min-height:130px;">
                        <div class="card-item-title">
                            <i class="fas fa-flask" style="color:var(--purple);"></i> Select Lab Tests
                            <span class="badge-label" id="labSelectedCount" style="font-size:0.5rem;">(0 selected)</span>
                            <span class="badge-label" style="background:var(--purple);color:white;border-radius:10px;padding:0 6px;font-size:0.45rem;">🧪 Lab Only</span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            <div class="lab-modal-container-modern" style="border-color:var(--purple);">
                                <div class="lab-modal-header-modern" style="padding:4px 10px;">
                                    <div class="lab-modal-title" style="font-size:0.6rem;">
                                        <i class="fas fa-flask" style="color:var(--purple);"></i>
                                        Select Lab Tests
                                    </div>
                                    <button type="button" class="lab-modal-close-modern" onclick="closeLabTests()" title="Close Lab Tests" style="background:none;border:none;color:var(--text-secondary);cursor:pointer;font-size:0.7rem;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                
                                <div class="lab-test-scroll" id="labTestsContainer">
                                    <?php if (!empty($lab_tests_catalog) && count($lab_tests_catalog) > 0): ?>
                                        <?php foreach ($lab_tests_catalog as $test): ?>
                                            <div class="lab-test-item-modern">
                                                <input type="checkbox" name="lab_test_ids[]" value="<?= $test['id'] ?>" 
                                                       id="lab_test_<?= $test['id'] ?>" 
                                                       class="lab-test-checkbox" 
                                                       onchange="updateLabSelection(this)">
                                                <label for="lab_test_<?= $test['id'] ?>" style="font-size:0.65rem;">
                                                    <strong><?= htmlspecialchars($test['test_name']) ?></strong>
                                                    <?php if (!empty($test['category'])): ?>
                                                        <span class="lab-test-category"><?= htmlspecialchars($test['category']) ?></span>
                                                    <?php endif; ?>
                                                </label>
                                                <span class="lab-test-price">TSh <?= number_format($test['price'] ?? 0, 0) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-3 text-gray-400">
                                            <i class="fas fa-flask"></i>
                                            <p style="font-size:0.6rem;">No lab tests available</p>
                                            <p style="font-size:0.5rem;margin-top:2px;">Please add lab tests to the catalog first</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="lab-modal-footer-modern">
                                    <div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;">
                                        <button type="button" class="btn-modern btn-modern-outline btn-modern-sm" onclick="selectAllLabTests()" style="font-size:0.5rem;padding:2px 8px;min-height:24px;">
                                            <i class="fas fa-check-double"></i> All
                                        </button>
                                        <button type="button" class="btn-modern btn-modern-outline btn-modern-sm" onclick="deselectAllLabTests()" style="font-size:0.5rem;padding:2px 8px;min-height:24px;">
                                            <i class="fas fa-times"></i> Clear
                                        </button>
                                        <span class="lab-total-price" id="labTotalPrice" style="font-size:0.55rem;">Total: TSh 0</span>
                                        <span class="text-xs text-gray-400" id="labSelectionCount" style="font-size:0.5rem;">(0 tests)</span>
                                    </div>
                                    <button type="button" class="btn-modern btn-modern-primary btn-modern-sm" onclick="closeLabTests()" style="font-size:0.5rem;padding:2px 10px;min-height:24px;background:var(--danger);">
                                        <i class="fas fa-times"></i> Close
                                    </button>
                                </div>
                            </div>
                            
                            <div class="lab-selected-summary-modern" id="labSelectedSummary" style="display:none;padding:4px 8px;background:var(--success-bg);border-radius:var(--radius);margin-top:4px;border:1px solid var(--success);">
                                <span style="font-weight:600;color:var(--primary);font-size:0.6rem;">
                                    <i class="fas fa-check-circle"></i> Selected:
                                </span>
                                <span id="labSelectedNames" style="color:var(--text-primary);font-size:0.6rem;"></span>
                                <span style="font-size:0.5rem;color:var(--text-secondary);margin-left:4px;">
                                    (Bill sent to Cashier)
                                </span>
                            </div>
                            
                            <input type="hidden" name="lab_test_ids" id="selectedLabTestsInput" value="">
                        </div>
                    </div>
                    
                </div>
                
            </div>
            
            <!-- ================================================================ -->
            <!-- VITAL SIGNS - Full Width -->
            <!-- ================================================================ -->
            <div class="form-card-item" style="margin-top:16px;min-height:100px;">
                <div class="card-item-title">
                    <i class="fas fa-heartbeat" style="color:#DC2626;"></i> Vital Signs
                    <span class="badge-label">Optional - Any value accepted</span>
                    <?php if ($selected_patient_id > 0 && $latest_vital_signs): ?>
                        <span class="text-xs text-green-500 ml-2" style="font-size:0.5rem;">
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
                        <div style="display:flex;gap:4px;align-items:center;">
                            <input type="number" name="bp_systolic" class="vital-input" style="width:45%;" placeholder="120" value="<?= $latest_vital_signs['blood_pressure_systolic'] ?? '' ?>">
                            <span style="color:var(--text-secondary);font-weight:700;font-size:0.6rem;">/</span>
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
                        <span class="vital-normal" id="bmiCategory" style="font-size:0.45rem;">Auto-calculated</span>
                    </div>
                </div>
                
                <div class="mt-2">
                    <input type="text" name="vital_notes" class="form-control-modern" placeholder="Vital signs notes (optional)" value="<?= $latest_vital_signs['notes'] ?? '' ?>" style="font-size:0.6rem;padding:4px 10px;min-height:32px;">
                </div>
            </div>
            
            <!-- Additional Notes -->
            <div class="form-card-item" style="margin-top:4px;min-height:60px;">
                <div class="card-item-title">
                    <i class="fas fa-sticky-note"></i> Additional Notes <span class="badge-label">Optional</span>
                </div>
                <textarea name="notes" class="form-control-modern textarea" placeholder="Any additional notes..." id="notesInput" rows="2" style="font-size:0.65rem;padding:5px 10px;min-height:50px;"></textarea>
            </div>
            
            <!-- FORM ACTIONS -->
            <div class="form-actions-modern">
                <button type="submit" class="btn-modern <?= $change_mode ? 'btn-modern-warning' : 'btn-modern-primary' ?>" id="assignBtn" style="font-size:0.75rem;padding:8px 20px;">
                    <i class="fas <?= $change_mode ? 'fa-sync-alt' : 'fa-user-md' ?>"></i> 
                    <?= $change_mode ? 'Change Doctor' : 'Assign / Change Doctor' ?>
                </button>
                <button type="reset" class="btn-modern btn-modern-outline" style="font-size:0.75rem;padding:8px 20px;">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="dashboard.php" class="btn-modern btn-modern-outline" style="font-size:0.75rem;padding:8px 20px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
            
            <div class="mt-3 pt-2 text-xs text-gray-400 text-center border-t border-gray-200 dark:border-gray-700" style="font-size:0.55rem;">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>Visit Type:</strong> Uses Service ID from services table. Fee from services.price.
                <span class="mx-2">|</span>
                <span id="formTimestamp"><?= date('h:i:s A') ?></span>
                <span class="mx-2">|</span>
                <span class="text-green-500" id="liveIndicator">
                    <span class="live-indicator-modern"></span> Live
                </span>
                <?php if ($change_mode): ?>
                    <span class="mx-2">|</span>
                    <span class="text-yellow-500 font-bold" style="font-size:0.55rem;">🔄 Change Mode Active</span>
                <?php endif; ?>
                <span class="mx-2">|</span>
                <span class="text-blue-400" id="cashierNotification" style="font-size:0.55rem;">
                    <i class="fas fa-cash-register"></i> Bill sent to Cashier
                </span>
            </div>
        </form>
    </div>

    <!-- QUICK STATS -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 mt-4" style="max-width:1200px;margin:16px auto 0;">
        <div class="stat-card-modern" style="padding:10px 14px;background:var(--bg-card);border-radius:var(--radius);border:1px solid var(--border-color);">
            <div class="stat-icon" style="font-size:1rem;">🟡</div>
            <p class="stat-number primary" style="font-size:1.2rem;" id="pendingStatNumber"><?= $pending_count ?></p>
            <p class="stat-label" style="font-size:0.55rem;">Pending (No Doctor)</p>
            <p class="text-xs text-gray-400" style="font-size:0.45rem;" id="pendingUpdateTime">Updated: <?= date('H:i:s') ?></p>
        </div>
        <div class="stat-card-modern" style="padding:10px 14px;background:var(--bg-card);border-radius:var(--radius);border:1px solid var(--border-color);">
            <div class="stat-icon" style="font-size:1rem;">✅</div>
            <p class="stat-number green" style="font-size:1.2rem;" id="assignedStatNumber"><?= $assigned_count ?></p>
            <p class="stat-label" style="font-size:0.55rem;">Assigned (Has Doctor)</p>
            <p class="text-xs text-gray-400" style="font-size:0.45rem;" id="assignedUpdateTime">Updated: <?= date('H:i:s') ?></p>
        </div>
        <div class="stat-card-modern" style="padding:10px 14px;background:var(--bg-card);border-radius:var(--radius);border:1px solid var(--border-color);">
            <div class="stat-icon" style="font-size:1rem;">🧪</div>
            <p class="stat-number purple" style="font-size:1.2rem;" id="labOnlyStatNumber"><?= $lab_only_count ?></p>
            <p class="stat-label" style="font-size:0.55rem;">Lab Only (No Doctor)</p>
            <p class="text-xs text-gray-400" style="font-size:0.45rem;" id="labOnlyUpdateTime">Updated: <?= date('H:i:s') ?></p>
        </div>
        <div class="stat-card-modern" style="padding:10px 14px;background:var(--bg-card);border-radius:var(--radius);border:1px solid var(--border-color);">
            <div class="stat-icon" style="font-size:1rem;">👨‍⚕️</div>
            <p class="stat-number orange" style="font-size:1.2rem;" id="availableDoctorsStat"><?= $total_doctors ?></p>
            <p class="stat-label" style="font-size:0.55rem;">Total Doctors</p>
            <p class="text-xs text-gray-400" style="font-size:0.45rem;" id="onlineDoctorsStatTime">🟢 <?= $online_doctors_count ?> online, ⚪ <?= $offline_doctors_count ?> offline</p>
        </div>
    </div>

    <footer class="footer-modern">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Assign / Change Doctor
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-modern" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:0.9rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.75rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.65rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // CLOCK UPDATE
    // ================================================================
    function updateClock() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        var el = document.getElementById('clockDisplay');
        if (el) el.textContent = dateStr + ' • ' + timeStr;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // ================================================================
    // SEARCH
    // ================================================================
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

    // ================================================================
    // DARK MODE
    // ================================================================
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

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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

    // ================================================================
    // TOAST
    // ================================================================
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

    // ================================================================
    // AUTO-REMOVE NOTIFICATION
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var notification = document.getElementById('autoRemoveNotification');
        if (notification) {
            setTimeout(function() {
                notification.classList.add('hide');
            }, 5000);
        }
    });

    // ================================================================
    // SYMPTOMS SELECT
    // ================================================================
    var symptomsSelect = document.getElementById('symptomsSelect');
    var symptomsTextarea = document.getElementById('symptomsTextarea');
    symptomsSelect?.addEventListener('change', function() {
        var value = this.value;
        if (value && value !== 'other') {
            var currentValue = symptomsTextarea.value.trim();
            if (currentValue) {
                symptomsTextarea.value = currentValue + ', ' + value;
            } else {
                symptomsTextarea.value = value;
            }
        } else if (value === 'other') {
            symptomsTextarea.focus();
        }
    });

    // ================================================================
    // BMI CALCULATOR
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
            var bmi = weight / (heightM * heightM);
            bmi = Math.round(bmi * 10) / 10;
            bmiOutput.value = bmi;
            bmiCategory.textContent = 'BMI: ' + bmi;
        } else {
            bmiOutput.value = '';
            bmiCategory.textContent = 'Auto-calculated';
        }
    }

    // ================================================================
    // ✅ FIXED: TOGGLE ASSIGNMENT TYPE - HIDE DOCTOR COMPLETELY
    // ================================================================
    function toggleAssignmentType(type) {
        var labSection = document.getElementById('labSection');
        var doctorSelect = document.getElementById('doctorSelect');
        var doctorRequired = document.getElementById('doctorRequired');
        var assignBtn = document.getElementById('assignBtn');
        var helpText = document.getElementById('assignmentTypeHelp');
        var visitTypeSection = document.getElementById('visitTypeSection');
        var visitTypeSelect = document.getElementById('visitTypeSelect');
        var visitTypePrice = document.getElementById('visitTypePrice');
        var consultationFeeDisplay = document.getElementById('consultationFeeDisplay');
        var feeNote = document.getElementById('feeNote');
        var doctorSelectCard = document.getElementById('doctorSelectCard');
        
        if (type === 'lab') {
            // ✅ HIDE DOCTOR SECTION
            if (doctorSelectCard) {
                doctorSelectCard.style.display = 'none';
            }
            labSection.style.display = 'block';
            doctorSelect.removeAttribute('required');
            if (doctorRequired) {
                doctorRequired.style.display = 'none';
            }
            helpText.textContent = '🧪 Lab test request selected - Doctor is NOT required';
            assignBtn.innerHTML = '<i class="fas fa-flask"></i> Request Lab Tests (No Doctor)';
            
            if (visitTypeSection) {
                visitTypeSection.style.display = 'none';
            }
            if (visitTypeSelect) {
                visitTypeSelect.disabled = true;
            }
            if (visitTypePrice) visitTypePrice.style.display = 'none';
            if (consultationFeeDisplay) consultationFeeDisplay.textContent = '🚫 No consultation fee (Lab only)';
            
            if (feeNote) {
                feeNote.innerHTML = '<span class="text-purple-500" style="font-size:0.55rem;">🧪 Lab Test Mode: No doctor assigned, No consultation fee</span>';
            }
            
            assignBtn.style.background = '#7C3AED';
            assignBtn.style.color = 'white';
            
        } else {
            // ✅ SHOW DOCTOR SECTION
            if (doctorSelectCard) {
                doctorSelectCard.style.display = 'block';
            }
            labSection.style.display = 'none';
            doctorSelect.setAttribute('required', 'required');
            if (doctorRequired) {
                doctorRequired.style.display = 'inline';
            }
            helpText.textContent = '👨‍⚕️ Doctor assignment selected - Doctor is required';
            assignBtn.innerHTML = '<i class="fas fa-user-md"></i> Assign / Change Doctor';
            
            if (visitTypeSection) {
                visitTypeSection.style.display = 'block';
            }
            if (visitTypeSelect) {
                visitTypeSelect.disabled = false;
                var defaultOption = visitTypeSelect.querySelector('option[selected]');
                if (defaultOption) {
                    visitTypeSelect.value = defaultOption.value;
                } else {
                    var options = visitTypeSelect.options;
                    for (var i = 0; i < options.length; i++) {
                        if (options[i].value && options[i].value !== 'no_visit' && !options[i].disabled) {
                            visitTypeSelect.value = options[i].value;
                            break;
                        }
                    }
                }
            }
            if (visitTypePrice) visitTypePrice.style.display = 'inline';
            if (consultationFeeDisplay) consultationFeeDisplay.textContent = '';
            
            if (feeNote) {
                feeNote.innerHTML = '<span class="text-blue-500" style="font-size:0.55rem;">👨‍⚕️ Consultation Mode: Doctor required, Consultation fee applies</span>';
            }
            
            assignBtn.style.background = '';
            assignBtn.style.color = '';
        }
    }

    // ================================================================
    // LAB FUNCTIONS
    // ================================================================
    function updateLabSelection(checkbox) {
        var checkboxes = document.querySelectorAll('.lab-test-checkbox');
        var count = 0;
        var total = 0;
        var names = [];
        var selectedIds = [];
        
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
        if (totalPriceEl) {
            totalPriceEl.textContent = count > 0 ? 'Total: TSh ' + total.toLocaleString() : 'Total: TSh 0';
        }
        
        var selectionCountEl = document.getElementById('labSelectionCount');
        if (selectionCountEl) {
            selectionCountEl.textContent = '(' + count + ' tests)';
        }
        
        var summaryEl = document.getElementById('labSelectedSummary');
        var namesEl = document.getElementById('labSelectedNames');
        if (summaryEl && namesEl) {
            if (count > 0) {
                summaryEl.style.display = 'block';
                namesEl.textContent = names.join(', ');
            } else {
                summaryEl.style.display = 'none';
            }
        }
        
        var hiddenInput = document.getElementById('selectedLabTestsInput');
        if (hiddenInput) {
            hiddenInput.value = selectedIds.join(',');
        }
        
        var existingHidden = document.querySelectorAll('input[name="lab_test_ids[]"]');
        existingHidden.forEach(function(el) {
            if (el.type === 'hidden') el.remove();
        });
        
        selectedIds.forEach(function(id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'lab_test_ids[]';
            input.value = id;
            document.getElementById('assignForm').appendChild(input);
        });
    }

    function selectAllLabTests() {
        var checkboxes = document.querySelectorAll('.lab-test-checkbox');
        checkboxes.forEach(function(cb) {
            cb.checked = true;
            var item = cb.closest('.lab-test-item-modern');
            if (item) item.classList.add('checked');
        });
        updateLabSelection(null);
    }

    function deselectAllLabTests() {
        var checkboxes = document.querySelectorAll('.lab-test-checkbox');
        checkboxes.forEach(function(cb) {
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

    // ================================================================
    // VISIT TYPE PRICE
    // ================================================================
    function updateVisitTypePrice() {
        var select = document.getElementById('visitTypeSelect');
        var priceDisplay = document.getElementById('visitTypePrice');
        var feeDisplay = document.getElementById('consultationFeeDisplay');
        var descriptionEl = document.getElementById('visitTypeDescription');
        
        if (!select) return;
        
        var selectedOption = select.options[select.selectedIndex];
        var price = selectedOption.dataset.price || 0;
        var serviceName = selectedOption.dataset.serviceName || 'Consultation';
        
        if (priceDisplay) {
            priceDisplay.textContent = 'Fee: TSh ' + parseInt(price).toLocaleString();
        }
        
        if (feeDisplay) {
            feeDisplay.textContent = '💵 ' + serviceName + ' - TSh ' + parseInt(price).toLocaleString();
        }
        
        if (descriptionEl) {
            descriptionEl.textContent = '📋 ' + serviceName + ' | Fee: TSh ' + parseInt(price).toLocaleString();
        }
    }

    // ================================================================
    // SELECT PATIENT AND CHANGE
    // ================================================================
    function selectPatientAndChange(patientId) {
        var select = document.getElementById('patientSelect');
        if (select) {
            select.value = patientId;
            if (select.value != patientId) {
                window.location.href = 'assign_doctor.php?patient_id=' + patientId + '&change=1';
                return;
            }
            var event = new Event('change', { bubbles: true });
            select.dispatchEvent(event);
        }
        var formCard = document.getElementById('mainFormCard');
        if (formCard) formCard.classList.add('change-mode-active-modern');
        var doctorSelect = document.getElementById('doctorSelect');
        if (doctorSelect) {
            setTimeout(function() {
                doctorSelect.focus();
                showToast('🔄 Change Mode', 'Patient auto-selected. Choose new doctor from the dropdown below.', 'warning');
            }, 500);
        }
        document.getElementById('mainFormCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ================================================================
    // FETCH PATIENT DETAILS
    // ================================================================
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
                            ? '<span class="assigned-doctor-tag-modern"><i class="fas fa-user-md"></i> Dr. ' + escapeHtml(doctorName) + '</span>'
                            : '<span class="text-gray-400 text-xs">No doctor assigned</span>';
                        var patientDays = data.patient_days || 0;
                        var daysText = patientDays > 0 ? patientDays + ' days ago' : 'Just registered';
                        var daysHtml = '<span class="days-badge-blue">📅 ' + daysText + '</span>';
                        
                        var visitDaysHtml = '';
                        if (data.visit_days !== undefined && data.visit_days !== null && data.visit_days > 0) {
                            visitDaysHtml = '<span class="assigned-days-badge-blue">Assigned: ' + data.visit_days + ' days ago</span>';
                        }
                        
                        var visitTypeHtml = '';
                        if (data.visit_type) {
                            visitTypeHtml = '<span class="text-xs text-primary">📋 ' + escapeHtml(data.visit_type) + '</span>';
                        }
                        
                        var changeModeHtml = '';
                        if (<?= $change_mode ? 'true' : 'false' ?>) {
                            changeModeHtml = '<span class="text-xs text-yellow-500 font-bold" style="background:var(--warning-bg);padding:1px 6px;border-radius:12px;font-size:0.5rem;">🔄 Change Mode</span>';
                        }
                        
                        infoDiv.innerHTML = `
                            <div class="flex items-center gap-2 text-sm flex-wrap" style="font-size:0.65rem;">
                                <i class="fas fa-user-circle text-primary"></i>
                                <span class="font-semibold">${escapeHtml(data.patient.full_name || '')}</span>
                                <span class="text-gray-400">|</span>
                                <span>${escapeHtml(data.patient.patient_id || '')}</span>
                                ${daysHtml}
                                ${visitDaysHtml}
                                ${visitTypeHtml}
                                ${doctorHtml}
                                ${changeModeHtml}
                            </div>
                        `;
                        infoDiv.style.display = 'block';
                    }
                }
            })
            .catch(function(error) { console.error('Error fetching patient details:', error); });
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ================================================================
    // LIVE DATA UPDATE
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;

    function fetchLiveData() {
        if (isUpdating) return;
        isUpdating = true;
        
        var formData = new FormData();
        formData.append('action', 'get_live_data');
        
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(response) { 
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json(); 
            })
            .then(function(data) {
                if (data.success) {
                    updateUI(data);
                }
                isUpdating = false;
            })
            .catch(function(error) {
                console.error('Live update error:', error);
                isUpdating = false;
            });
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
        document.getElementById('lastDoctorUpdate').textContent = 'Updated: ' + timeStr;
        document.getElementById('assignedListUpdate').textContent = '(Auto-updated ' + timeStr + ')';
        document.getElementById('labOnlyUpdate').textContent = '(Auto-updated ' + timeStr + ')';
        
        var patientSelect = document.getElementById('patientSelect');
        if (patientSelect && data.patient_options !== undefined) {
            var currentValue = patientSelect.value;
            if (patientSelect.innerHTML !== data.patient_options) {
                patientSelect.innerHTML = data.patient_options;
                if (currentValue) {
                    var found = false;
                    var options = patientSelect.querySelectorAll('option');
                    options.forEach(function(opt) {
                        if (opt.value == currentValue) found = true;
                    });
                    if (found) patientSelect.value = currentValue;
                }
            }
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
        if (doctorSelect && data.doctor_options !== undefined) {
            var currentDocValue = doctorSelect.value;
            if (doctorSelect.innerHTML !== data.doctor_options) {
                doctorSelect.innerHTML = data.doctor_options;
                if (currentDocValue) {
                    var found = false;
                    var options = doctorSelect.querySelectorAll('option');
                    options.forEach(function(opt) {
                        if (opt.value == currentDocValue) found = true;
                    });
                    if (found) doctorSelect.value = currentDocValue;
                }
            }
        }
        
        var visitTypeSelect = document.getElementById('visitTypeSelect');
        if (visitTypeSelect && data.visit_type_options !== undefined) {
            var currentVisitValue = visitTypeSelect.value;
            if (visitTypeSelect.innerHTML !== data.visit_type_options) {
                visitTypeSelect.innerHTML = data.visit_type_options;
                if (currentVisitValue) {
                    var found = false;
                    var options = visitTypeSelect.querySelectorAll('option');
                    options.forEach(function(opt) {
                        if (opt.value == currentVisitValue) found = true;
                    });
                    if (found) visitTypeSelect.value = currentVisitValue;
                }
                updateVisitTypePrice();
            }
        }
        
        var labContainer = document.getElementById('labTestsContainer');
        if (labContainer && data.lab_tests_html !== undefined) {
            var currentSelected = [];
            var currentCheckboxes = labContainer.querySelectorAll('.lab-test-checkbox:checked');
            currentCheckboxes.forEach(function(cb) {
                currentSelected.push(cb.value);
            });
            
            labContainer.innerHTML = data.lab_tests_html;
            
            var newCheckboxes = labContainer.querySelectorAll('.lab-test-checkbox');
            newCheckboxes.forEach(function(cb) {
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
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) stopLiveUpdate();
        else startLiveUpdate();
    });

    // ================================================================
    // FORM SUBMIT HANDLER
    // ================================================================
    document.getElementById('assignForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'change_doctor');
        
        var visitTypeSelect = document.getElementById('visitTypeSelect');
        if (visitTypeSelect) {
            var serviceId = visitTypeSelect.value;
            formData.append('service_id', serviceId);
        }
        
        var hiddenInput = document.getElementById('selectedLabTestsInput');
        if (hiddenInput && hiddenInput.value) {
            var ids = hiddenInput.value.split(',');
            ids.forEach(function(id) {
                if (id) formData.append('lab_test_ids[]', id);
            });
        }
        
        var btn = document.getElementById('assignBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Processing...';
        
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-user-md"></i> Assign / Change Doctor';
                
                if (data.success) {
                    var billMessage = '';
                    if (data.bill_sent_to_cashier && data.bill_number) {
                        billMessage = ' 💰 Bill #' + data.bill_number + ' sent to Cashier!';
                        var cashierNotif = document.getElementById('cashierNotification');
                        if (cashierNotif) {
                            cashierNotif.innerHTML = '<i class="fas fa-cash-register"></i> Bill #' + data.bill_number + ' sent to Cashier ✅';
                            cashierNotif.style.color = '#34D399';
                        }
                    } else if (data.is_lab_only && data.lab_tests_added) {
                        billMessage = ' 💰 Lab bill sent to Cashier!';
                        var cashierNotif = document.getElementById('cashierNotification');
                        if (cashierNotif) {
                            cashierNotif.innerHTML = '<i class="fas fa-cash-register"></i> Lab bill sent to Cashier ✅';
                            cashierNotif.style.color = '#34D399';
                        }
                    }
                    
                    var labMessage = '';
                    if (data.lab_tests_added) {
                        labMessage = ' 🧪 ' + (data.lab_test_ids ? data.lab_test_ids.length : 0) + ' lab test(s) requested!';
                        if (data.total_lab_fee > 0) {
                            labMessage += ' (TSh ' + data.total_lab_fee.toLocaleString() + ')';
                        }
                    }
                    
                    var serviceMessage = '';
                    if (data.service_name && !data.is_lab_only) {
                        serviceMessage = ' 📋 ' + data.service_name;
                    }
                    
                    var doctorMessage = '';
                    if (data.has_doctor) {
                        doctorMessage = ' ✅ Doctor assigned';
                    } else if (data.is_lab_only) {
                        doctorMessage = ' 🧪 No doctor assigned (Lab only)';
                    }
                    
                    showToast('✅ Success', data.message + billMessage + labMessage + serviceMessage + doctorMessage, 'success');
                    
                    if (data.patient_id) {
                        setTimeout(function() {
                            window.location.href = 'assign_doctor.php';
                        }, 3000);
                    }
                } else {
                    showToast('❌ Error', data.message || 'Failed to process request', 'error');
                }
            })
            .catch(function(error) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-user-md"></i> Assign / Change Doctor';
                showToast('❌ Error', 'Network error: ' + error.message, 'error');
            });
    });

    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        calculateBMI();
        updateVisitTypePrice();
        
        var patientSelect = document.getElementById('patientSelect');
        patientSelect?.addEventListener('change', function() {
            var selectedId = this.value;
            if (selectedId) fetchPatientDetails(selectedId);
        });
        
        var initialPatientId = <?= $selected_patient_id ?: 0 ?>;
        if (initialPatientId > 0) {
            fetchPatientDetails(initialPatientId);
            var isChangeMode = <?= $change_mode ? 'true' : 'false' ?>;
            if (isChangeMode) {
                setTimeout(function() {
                    var doctorSelect = document.getElementById('doctorSelect');
                    if (doctorSelect) {
                        doctorSelect.focus();
                        showToast('🔄 Change Mode', 'Patient auto-selected. Choose new doctor from the dropdown below.', 'warning');
                    }
                }, 1000);
            }
        }
        
        setTimeout(function() {
            updateLabSelection(null);
        }, 500);
        
        setTimeout(function() { startLiveUpdate(); }, 2000);
        
        var assignmentType = document.getElementById('assignmentTypeSelect');
        if (assignmentType && assignmentType.value === 'lab') {
            toggleAssignmentType('lab');
        }
    });

    console.log('%c👨‍⚕️ Braick - Assign / Change Doctor (FULLY FIXED - 6 CARDS)', 'font-size:18px; font-weight:bold; color:#2563EB;');
    console.log('%c✅ 3 Cards Left: Patient, Action, Doctor', 'font-size:13px; color:#34D399;');
    console.log('%c✅ 3 Cards Right: Visit Type, Symptoms, Lab Tests', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Lab mode - Doctor dropdown HIDDEN completely', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Lab bills sent to bills table', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>