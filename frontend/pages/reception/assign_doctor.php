<?php
// ================================================================
// FILE: frontend/pages/reception/assign_doctor.php
// RECEPTION - ASSIGN / CHANGE / REASSIGN DOCTOR & LAB TESTS (V7)
// ✅ Shared Header + Sidebar included
// ✅ Patient count (branch-specific) kwenye Select Patient
// ✅ Reassign + Change buttons COMPACT (mini style)
// ✅ Text size imeongezwa
// ✅ Search + Patient list INSIDE "Select Patient" toggle
// ✅ Waiting/Lab/Prescribe = NO buttons (view only)
// ✅ Assigned = Reassign + Change Doctor buttons
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
$waiting_patients = [];
$prescribed_patients = [];
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
$waiting_count = 0;
$prescribed_count = 0;
$branch_patients_total = 0;
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
            (SELECT COUNT(*) FROM lab_tests lt 
             WHERE lt.patient_id = p.id 
             AND lt.status NOT IN ('completed', 'cancelled') 
             AND lt.visit_id = v.id) as pending_lab_tests_count
        FROM patients p
        LEFT JOIN visits v ON p.id = v.patient_id 
            AND v.status IN ('new', 'pending', 'assigned', 'with_doctor', 'lab_test', 'waiting', 'prescribed')
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
        if (!empty($patient['visit_id']) && in_array($patient['visit_status'], ['completed', 'cancelled'])) {
            continue;
        }
        $all_patients[] = $patient;
    }
    
    $branch_patients_total = count($all_patients);
    
    // CATEGORIZE PATIENTS
    $pending_patients = [];
    $assigned_patients = [];
    $lab_only_patients = [];
    $waiting_patients = [];
    $prescribed_patients = [];
    
    foreach ($all_patients as $patient) {
        $patient['has_active_visit'] = !empty($patient['visit_id']);
        $patient['patient_days'] = isset($patient['patient_days']) ? (int)$patient['patient_days'] : 0;
        
        if ($patient['has_active_visit']) {
            $status = $patient['visit_status'];
            
            if ($status === 'lab_test') {
                $pending_labs = (int)($patient['pending_lab_tests_count'] ?? 0);
                if ($pending_labs > 0) $lab_only_patients[] = $patient;
            }
            elseif ($status === 'waiting') {
                $waiting_patients[] = $patient;
            }
            elseif ($status === 'prescribed') {
                $prescribed_patients[] = $patient;
            }
            elseif (in_array($status, ['new', 'pending'])) {
                $pending_patients[] = $patient;
            }
            elseif (in_array($status, ['assigned', 'with_doctor'])) {
                $assigned_patients[] = $patient;
            }
        }
    }
    
    $pending_count = count($pending_patients);
    $assigned_count = count($assigned_patients);
    $lab_only_count = count($lab_only_patients);
    $waiting_count = count($waiting_patients);
    $prescribed_count = count($prescribed_patients);
    
    if ($selected_patient_id > 0) {
        foreach ($all_patients as $p) {
            if ($p['id'] == $selected_patient_id) {
                $selected_patient_data = $p;
                break;
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
    
    // GET DOCTORS
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
    
    // HELPER: CREATE LAB ONLY BILL
    function createLabOnlyBill($db, $patient_id, $visit_id, $lab_test_ids, $user_id, $branch_id) {
        $total_lab_fee = 0;
        $lab_test_names = [];
        
        if (empty($lab_test_ids)) return ['status' => 'error', 'message' => 'No lab tests selected'];
        
        $test_ids_imploded = implode(',', array_map('intval', $lab_test_ids));
        $stmt = $db->prepare("SELECT id, test_name, price FROM lab_tests_catalog WHERE id IN ($test_ids_imploded)");
        $stmt->execute();
        $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tests as $test) {
            $total_lab_fee += (float)$test['price'];
            $lab_test_names[] = $test['test_name'];
        }
        
        if ($total_lab_fee <= 0) return ['status' => 'error', 'message' => 'No lab tests with price > 0 selected'];
        
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
    
    // HELPER: CREATE VISIT BILL
    function createVisitBill($db, $patient_id, $visit_id, $service_name, $consultation_fee, $user_id, $branch_id) {
        if ($consultation_fee <= 0) return ['status' => 'error', 'message' => 'Consultation fee is 0 or negative'];
        
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
            echo json_encode([
                'success' => true,
                'pending_count' => $pending_count,
                'assigned_count' => $assigned_count,
                'lab_only_count' => $lab_only_count,
                'waiting_count' => $waiting_count,
                'prescribed_count' => $prescribed_count,
                'branch_patients_total' => $branch_patients_total,
                'online_count' => $online_doctors_count,
                'offline_count' => $offline_doctors_count,
                'total_doctors' => $total_doctors,
                'timestamp' => date('H:i:s')
            ]);
            exit;
        }
        
        if ($action === 'get_filtered_list') {
            header('Content-Type: application/json');
            
            $status = $_POST['status'] ?? 'assigned';
            $filtered = [];
            
            if ($status === 'assigned') $filtered = $assigned_patients;
            elseif ($status === 'lab_test') $filtered = $lab_only_patients;
            elseif ($status === 'prescribed') $filtered = $prescribed_patients;
            elseif ($status === 'waiting') $filtered = $waiting_patients;
            
            if (empty($filtered)) {
                echo json_encode(['success' => true, 'html' => '', 'count' => 0]);
                exit;
            }
            
            $html = '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:0.85rem;">';
            $html .= '<thead><tr style="border-bottom:2px solid var(--border-color);">';
            $html .= '<th style="padding:12px 14px;text-align:left;font-weight:700;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);">Patient / Service</th>';
            $html .= '<th style="padding:12px 14px;text-align:left;font-weight:700;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);">Patient ID</th>';
            $html .= '<th style="padding:12px 14px;text-align:left;font-weight:700;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);">Doctor</th>';
            $html .= '<th style="padding:12px 14px;text-align:left;font-weight:700;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);">Status</th>';
            
            if ($status === 'assigned') {
                $html .= '<th style="padding:12px 14px;text-align:center;font-weight:700;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);">Actions</th>';
            }
            
            $html .= '</tr></thead><tbody>';
            
            foreach ($filtered as $patient) {
                $assigned_days = 0;
                if (!empty($patient['visit_created_at'])) {
                    $assigned_days = (int)floor((time() - strtotime($patient['visit_created_at'])) / 86400);
                }
                $days_text = $assigned_days > 0 
                    ? '<span class="assigned-days-badge-blue">' . $assigned_days . ' days</span>' 
                    : '<span class="assigned-days-badge-blue new">Just added</span>';
                
                $doctor_html = !empty($patient['assigned_doctor_name'])
                    ? '<span style="font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;background:var(--primary-bg);padding:5px 12px;border-radius:12px;border:1px solid var(--primary-light);"><i class="fas fa-user-md" style="color:var(--primary);"></i> Dr. ' . htmlspecialchars($patient['assigned_doctor_name']) . ' ' . ($patient['assigned_doctor_online'] == 1 ? '🟢' : '⚪') . '</span>'
                    : '<span class="text-gray-400 text-xs">No doctor</span>';
                
                $status_badge = '';
                if ($status === 'assigned') $status_badge = '<span class="status-badge-dropdown assigned">✅ Assigned</span>';
                elseif ($status === 'lab_test') $status_badge = '<span class="status-badge-dropdown lab_only">🧪 Lab Test</span>';
                elseif ($status === 'prescribed') $status_badge = '<span style="background:#D1FAE5;color:#059669;padding:3px 12px;border-radius:10px;font-size:0.65rem;font-weight:700;">💊 Prescribed</span>';
                elseif ($status === 'waiting') $status_badge = '<span style="background:#FEF3C7;color:#D97706;padding:3px 12px;border-radius:10px;font-size:0.65rem;font-weight:700;">⏳ Waiting</span>';
                
                $html .= '<tr id="patient-row-' . $patient['id'] . '" style="border-bottom:1px solid var(--border-color);">';
                $html .= '<td style="padding:12px 14px;font-weight:600;font-size:0.88rem;">' . htmlspecialchars($patient['full_name']) . ' ' . $days_text . '<span class="text-xs text-gray-400 block" style="font-size:0.72rem;">' . htmlspecialchars($patient['visit_type'] ?? 'Consultation') . '</span></td>';
                $html .= '<td style="padding:12px 14px;font-family:monospace;font-size:0.82rem;">' . htmlspecialchars($patient['patient_id'] ?? 'N/A') . '</td>';
                $html .= '<td style="padding:12px 14px;">' . $doctor_html . '</td>';
                $html .= '<td style="padding:12px 14px;">' . $status_badge . '</td>';
                
                // ✅ COMPACT ACTION BUTTONS - Only for Assigned
                if ($status === 'assigned') {
                    $html .= '<td class="actions-cell">';
                    $html .= '<div class="action-group">';
                    $html .= '<button onclick="reassignDoctor(' . $patient['id'] . ', ' . $patient['visit_id'] . ')" class="btn-action-mini reassign" title="Reassign (Remove Doctor)"><i class="fas fa-user-minus"></i> <span class="btn-text">Reassign</span></button>';
                    $html .= '<button onclick="changeDoctor(' . $patient['id'] . ')" class="btn-action-mini change" title="Change Doctor"><i class="fas fa-sync-alt"></i> <span class="btn-text">Change</span></button>';
                    $html .= '</div>';
                    $html .= '</td>';
                }
                
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table></div>';
            
            echo json_encode(['success' => true, 'html' => $html, 'count' => count($filtered)]);
            exit;
        }
        
        if ($action === 'reassign_doctor') {
            header('Content-Type: application/json');
            
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            $visit_id = (int)($_POST['visit_id'] ?? 0);
            
            if ($patient_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
                exit;
            }
            
            try {
                $db->beginTransaction();
                
                if ($visit_id > 0) {
                    $stmt = $db->prepare("SELECT id, visit_number, doctor_id, status FROM visits WHERE id = ? AND patient_id = ? LIMIT 1");
                    $stmt->execute([$visit_id, $patient_id]);
                } else {
                    $stmt = $db->prepare("SELECT id, visit_number, doctor_id, status FROM visits WHERE patient_id = ? AND status IN ('assigned', 'with_doctor', 'waiting', 'pending', 'new') AND branch_id = ? ORDER BY id DESC LIMIT 1");
                    $stmt->execute([$patient_id, $selected_branch_id]);
                }
                $visit = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$visit) throw new Exception('No active visit found for this patient');
                
                $stmt = $db->prepare("UPDATE visits SET doctor_id = NULL, status = 'pending', assigned_at = NULL, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$visit['id']]);
                
                $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL WHERE id = ?");
                $stmt->execute([$patient_id]);
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Doctor removed. Patient returned to Pending list.',
                    'visit_id' => $visit['id'],
                    'visit_number' => $visit['visit_number']
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
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
                
                $stmt = $db->prepare("SELECT id, status, doctor_id, visit_number FROM visits WHERE patient_id = ? AND status IN ('new', 'pending', 'assigned', 'with_doctor', 'lab_test', 'waiting') AND branch_id = ? ORDER BY id DESC LIMIT 1");
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
                $oxygen_saturation = isset($_POST['oxygen_saturation']) && $_POST['oxygen_saturation'] !== '' ? (int)$_POST['oxygen_saturation'] : null;
                $vital_notes = trim($_POST['vital_notes'] ?? '');
                
                $has_vital = ($temperature !== null && $temperature !== '') || ($bp_systolic !== null && $bp_systolic !== '') || ($bp_diastolic !== null && $bp_diastolic !== '') || ($pulse_rate !== null && $pulse_rate !== '') || ($weight !== null && $weight !== '') || ($height !== null && $height !== '') || $oxygen_saturation !== null;
                
                if ($has_vital && $visit_id) {
                    $bmi = null;
                    if ($weight && $height && $height > 0) {
                        $height_m = $height / 100;
                        $bmi = round($weight / ($height_m * $height_m), 1);
                    }
                    $stmt = $db->prepare("INSERT INTO vital_signs (patient_id, visit_id, recorded_by, branch_id, temperature, blood_pressure_systolic, blood_pressure_diastolic, pulse_rate, weight, height, bmi, oxygen_saturation, notes, recorded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$patient_id, $visit_id, $user_id, $selected_branch_id, $temperature ?: null, $bp_systolic ?: null, $bp_diastolic ?: null, $pulse_rate ?: null, $weight ?: null, $height ?: null, $bmi, $oxygen_saturation, $vital_notes ?: null]);
                }
                
                $db->commit();
                
                $bill_message = '';
                if ($bill_created && $bill_number) $bill_message = ' 💰 Bill #' . $bill_number . ' sent to Cashier!';
                
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
    $waiting_patients = [];
    $prescribed_patients = [];
    $doctors = [];
    $online_doctors = [];
    $offline_doctors = [];
    $visit_type_options = [];
    $pending_count = 0;
    $assigned_count = 0;
    $lab_only_count = 0;
    $waiting_count = 0;
    $prescribed_count = 0;
    $branch_patients_total = 0;
    $unread_notifications = 0;
}

$common_symptoms = [
    'Fever', 'Headache', 'Cough', 'Sore Throat', 'Body Pain',
    'Fatigue', 'Nausea', 'Vomiting', 'Diarrhea', 'Chest Pain',
    'Shortness of Breath', 'Abdominal Pain', 'Dizziness', 'Rash', 'Swelling'
];

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ✅ INCLUDE SHARED HEADER + SIDEBAR
include_once __DIR__ . '/../../components/reception_header.php';
include_once __DIR__ . '/../../components/reception_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Doctor - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
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
        
        /* ============================================================
           PAGE HEADER
           ============================================================ */
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
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i { font-size: 1.6rem; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 4px;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            backdrop-filter: blur(4px);
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 600;
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
            padding: 8px 18px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
            cursor: pointer;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        /* ============================================================
           STATUS TOGGLE BUTTONS
           ============================================================ */
        .status-toggle-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            background: var(--bg-card);
            padding: 14px 20px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            max-width: 1300px;
            margin: 0 auto 20px;
        }
        
        .status-toggle-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 20px;
            border-radius: 30px;
            font-size: 0.82rem;
            font-weight: 700;
            border: 2px solid var(--border-color);
            background: var(--bg-body);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .status-toggle-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
        }
        
        .status-toggle-btn.active {
            background: var(--primary-gradient);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .status-toggle-btn.active[data-status="lab_test"] {
            background: linear-gradient(135deg, #7C3AED, #5B21B6);
            border-color: #7C3AED;
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
        }
        
        .status-toggle-btn.active[data-status="prescribed"] {
            background: linear-gradient(135deg, #059669, #047857);
            border-color: #059669;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .status-toggle-btn.active[data-status="waiting"] {
            background: linear-gradient(135deg, #D97706, #B45309);
            border-color: #D97706;
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
        }
        
        .toggle-count {
            background: rgba(255,255,255,0.25);
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 800;
        }
        
        /* ============================================================
           ✅ COMPACT ACTION BUTTONS (MINI STYLE)
           ============================================================ */
        .btn-action-mini {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            font-size: 0.65rem;
            font-weight: 700;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            min-height: 26px;
            line-height: 1;
            white-space: nowrap;
            letter-spacing: 0.01em;
            font-family: inherit;
        }
        
        .btn-action-mini i {
            font-size: 0.6rem;
        }
        
        .btn-action-mini.reassign {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
            box-shadow: 0 1px 4px rgba(220, 38, 38, 0.3);
        }
        
        .btn-action-mini.reassign:hover {
            background: linear-gradient(135deg, #B91C1C, #991B1B);
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(220, 38, 38, 0.4);
        }
        
        .btn-action-mini.change {
            background: linear-gradient(135deg, #D97706, #B45309);
            color: white;
            box-shadow: 0 1px 4px rgba(217, 119, 6, 0.3);
        }
        
        .btn-action-mini.change:hover {
            background: linear-gradient(135deg, #B45309, #92400E);
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(217, 119, 6, 0.4);
        }
        
        .actions-cell {
            padding: 10px 12px !important;
        }
        
        .action-group {
            display: flex;
            gap: 5px;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: center;
        }
        
        /* ============================================================
           FORM CARD
           ============================================================ */
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
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .form-card-modern .form-header .form-subtitle {
            font-size: 0.82rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
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
            font-size: 0.88rem;
            font-weight: 700;
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
            font-size: 1rem;
        }
        
        .form-card-item .card-item-title .required {
            color: var(--danger);
            margin-left: 2px;
        }
        
        .form-card-item .card-item-title .badge-label {
            font-size: 0.62rem;
            font-weight: 600;
            padding: 3px 12px;
            border-radius: 10px;
            background: var(--gray-200);
            color: var(--text-secondary);
            margin-left: 4px;
        }
        
        /* PATIENT TOGGLE */
        .patient-toggle-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 12px 16px;
            background: var(--bg-card);
            border: 2px solid var(--primary);
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--primary);
            font-family: inherit;
        }
        
        .patient-toggle-btn:hover {
            background: var(--primary-bg);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        }
        
        .patient-toggle-btn.active {
            background: var(--primary-gradient);
            color: white;
            border-color: var(--primary);
        }
        
        .patient-toggle-btn .toggle-arrow {
            transition: transform 0.3s ease;
            font-size: 0.85rem;
        }
        
        .patient-toggle-btn.active .toggle-arrow {
            transform: rotate(180deg);
        }
        
        .patient-toggle-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease;
            margin-top: 0;
        }
        
        .patient-toggle-content.open {
            max-height: 600px;
            margin-top: 12px;
        }
        
        /* PATIENT SEARCH */
        .patient-search-wrapper {
            position: relative;
            margin-bottom: 10px;
        }
        
        .patient-search-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 0.85rem;
            pointer-events: none;
        }
        
        .patient-search-wrapper input {
            padding: 11px 14px 11px 38px;
            font-size: 0.85rem;
            width: 100%;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .patient-search-wrapper input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
        }
        
        /* PATIENT LIST */
        .patient-list-container {
            max-height: 320px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            background: var(--bg-card);
        }
        
        .patient-list-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.2s ease;
            font-size: 0.85rem;
        }
        
        .patient-list-item:hover {
            background: var(--primary-bg);
        }
        
        .patient-list-item.selected {
            background: var(--primary-bg);
            border-left: 4px solid var(--primary);
        }
        
        .patient-list-item:last-child {
            border-bottom: none;
        }
        
        .patient-list-item .patient-icon {
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .patient-list-item .patient-info {
            flex: 1;
            min-width: 0;
        }
        
        .patient-list-item .patient-name {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .patient-list-item .patient-meta {
            font-size: 0.72rem;
            color: var(--text-secondary);
            margin-top: 3px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* FORM CONTROLS */
        .form-control-modern {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.88rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
            min-height: 44px;
            font-family: inherit;
        }
        
        .form-control-modern:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
        }
        
        .form-control-modern.textarea {
            min-height: 80px;
            resize: vertical;
            font-family: inherit;
        }
        
        /* LAB SECTION */
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
            font-weight: 700;
            font-size: 0.88rem;
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
            font-size: 0.88rem;
        }
        
        .lab-test-item-modern label strong {
            font-size: 0.88rem;
            color: var(--text-primary);
            font-weight: 700;
        }
        
        .lab-test-item-modern .lab-test-category {
            font-size: 0.6rem;
            background: var(--gray-200);
            color: var(--text-secondary);
            padding: 2px 10px;
            border-radius: 10px;
        }
        
        .lab-test-item-modern .lab-test-price {
            font-size: 0.85rem;
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
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--success);
            padding: 5px 14px;
            background: var(--success-bg);
            border-radius: 20px;
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
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
            margin-bottom: 3px;
        }
        
        .vital-item-modern .vital-input {
            border: none;
            background: transparent;
            padding: 4px 0;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            outline: none;
            width: 100%;
            font-family: inherit;
        }
        
        .vital-item-modern .vital-unit { 
            font-size: 0.6rem; 
            color: var(--text-secondary); 
            display: block; 
            font-weight: 600;
        }
        
        .vital-item-modern.bmi-item { 
            background: var(--primary-bg); 
            border-color: var(--primary); 
        }
        
        .vital-item-modern.spo2-item {
            background: rgba(8, 145, 178, 0.05);
            border-color: #0891B2;
        }
        
        .vital-item-modern.spo2-item .vital-label {
            color: #0891B2;
        }
        
        .vital-item-modern.spo2-item .vital-input {
            color: #0891B2;
            font-weight: 700;
        }
        
        .spo2-category {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            margin-top: 2px;
            background: var(--gray-200);
            color: var(--text-secondary);
        }
        
        .spo2-category.spo2-normal {
            background: rgba(5, 150, 105, 0.15);
            color: #059669;
        }
        
        .spo2-category.spo2-low {
            background: rgba(217, 119, 6, 0.15);
            color: #D97706;
            animation: pulse-spo2 1.5s infinite;
        }
        
        .spo2-category.spo2-critical {
            background: rgba(220, 38, 38, 0.3);
            color: #DC2626;
            font-weight: 800;
            animation: pulse-spo2 1s infinite;
        }
        
        @keyframes pulse-spo2 {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        /* BUTTONS */
        .btn-modern {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 26px;
            border-radius: var(--radius);
            font-weight: 700;
            font-size: 0.88rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
            min-height: 44px;
            font-family: inherit;
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
            padding: 6px 14px; 
            font-size: 0.75rem; 
            min-height: 34px; 
            border-radius: 8px; 
        }
        
        .btn-modern-purple { 
            background: var(--purple); 
            color: white; 
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
            font-size: 0.65rem;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 10px;
            margin-left: 6px;
        }
        .status-badge-dropdown.pending { background: #FEF3C7; color: #D97706; }
        .status-badge-dropdown.assigned { background: #D1FAE5; color: #059669; }
        .status-badge-dropdown.lab_only { background: #EDE9FE; color: #7C3AED; border: 1px dashed #7C3AED; }
        .status-badge-dropdown.no_visit { background: var(--gray-200); color: var(--gray-600); }
        
        .days-badge-blue {
            display: inline-block;
            background: var(--primary) !important;
            color: #ffffff !important;
            padding: 3px 10px !important;
            border-radius: 10px !important;
            font-size: 0.65rem !important;
            font-weight: 700 !important;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }
        .days-badge-blue.new {
            background: var(--success) !important;
        }
        
        .assigned-days-badge-blue {
            display: inline-block;
            background: var(--primary) !important;
            color: #ffffff !important;
            padding: 3px 10px !important;
            border-radius: 10px !important;
            font-size: 0.65rem !important;
            font-weight: 700 !important;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }
        .assigned-days-badge-blue.new {
            background: var(--success) !important;
        }
        
        /* MODERN CARD */
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
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .modern-card .card-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .modern-card .card-badge {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
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
            font-size: 0.88rem;
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
            font-size: 0.72rem;
            color: var(--text-secondary);
        }
        
        .footer-modern .footer-brand { color: var(--primary); font-weight: 600; }
        
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
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .form-card-modern { padding: 20px; }
            .form-grid-6 { grid-template-columns: 1fr; gap: 16px; }
        }
        
        @media (max-width: 768px) {
            .main-content { margin-top: 64px; padding: 14px; }
            .form-card-modern { padding: 14px; }
            .page-header { padding: 14px 16px; }
            .page-header .page-title { font-size: 1.15rem; }
            .vital-grid-modern { grid-template-columns: repeat(2, 1fr); }
            .form-actions-modern { flex-direction: column; }
            .form-actions-modern .btn-modern { width: 100%; justify-content: center; }
            .form-card-item { min-height: 130px; padding: 14px 16px; }
            .status-toggle-group { padding: 10px; gap: 6px; }
            .status-toggle-btn { padding: 7px 14px; font-size: 0.75rem; }
            
            /* ✅ MOBILE - Buttons zinakuwa icon tu */
            .btn-action-mini {
                width: 28px;
                height: 28px;
                justify-content: center;
                padding: 0;
                border-radius: 8px;
            }
            
            .btn-action-mini .btn-text {
                display: none;
            }
            
            .btn-action-mini i {
                font-size: 0.7rem;
            }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .form-card-modern { padding: 10px; }
            .vital-grid-modern { grid-template-columns: 1fr 1fr; }
            .page-header .header-badge { font-size: 0.55rem; padding: 2px 8px; }
            .form-card-item { min-height: 100px; padding: 12px 14px; }
            .form-card-item .card-item-title { font-size: 0.75rem; }
            .form-control-modern { font-size: 0.78rem; padding: 9px 12px; min-height: 38px; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-md"></i>
                Assign / Change / Reassign Doctor
                <span class="role-badge-display">RECEPTION</span>
                <span style="background:rgba(255,255,255,0.12);color:white;padding:4px 14px;border-radius:20px;font-size:0.68rem;">
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
                    <i class="fas fa-user-check"></i>
                    <span id="assignedCount"><?= $assigned_count ?></span> Assigned
                </span>
                <span class="header-badge" style="background:rgba(124,58,237,0.2);border-color:rgba(124,58,237,0.3);color:#A78BFA;">
                    <i class="fas fa-flask"></i>
                    <span id="labOnlyCount"><?= $lab_only_count ?></span> Lab Test
                </span>
                <span class="header-badge" style="background:rgba(5,150,105,0.2);border-color:rgba(5,150,105,0.3);color:#6EE7B7;">
                    <i class="fas fa-prescription"></i>
                    <span id="prescribedCount"><?= $prescribed_count ?></span> Prescribe
                </span>
                <span class="header-badge" style="background:rgba(217,119,6,0.2);border-color:rgba(217,119,6,0.3);color:#FCD34D;">
                    <i class="fas fa-clock"></i>
                    <span id="waitingCount"><?= $waiting_count ?></span> Waiting
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
        <div style="max-width:1300px;margin:0 auto 16px;padding:14px 20px;border-radius:var(--radius);background:<?= $message_type === 'success' ? 'var(--success-bg)' : 'var(--danger-bg)' ?>;color:<?= $message_type === 'success' ? 'var(--success)' : 'var(--danger)' ?>;border:2px solid <?= $message_type === 'success' ? 'var(--success)' : 'var(--danger)' ?>;display:flex;align-items:center;gap:10px;font-size:0.88rem;font-weight:600;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- STATUS TOGGLE BUTTONS -->
    <div class="status-toggle-group">
        <span style="font-size:0.82rem;font-weight:700;color:var(--text-secondary);margin-right:8px;">
            <i class="fas fa-filter"></i> Filter:
        </span>
        
        <button type="button" class="status-toggle-btn active" data-status="assigned" onclick="filterByStatus('assigned')">
            <i class="fas fa-user-check"></i> 
            Assigned 
            <span class="toggle-count" id="toggleAssignedCount"><?= $assigned_count ?></span>
        </button>
        
        <button type="button" class="status-toggle-btn" data-status="lab_test" onclick="filterByStatus('lab_test')">
            <i class="fas fa-flask"></i> 
            Lab Test 
            <span class="toggle-count" id="toggleLabCount"><?= $lab_only_count ?></span>
        </button>
        
        <button type="button" class="status-toggle-btn" data-status="prescribed" onclick="filterByStatus('prescribed')">
            <i class="fas fa-prescription"></i> 
            Prescribe 
            <span class="toggle-count" id="togglePrescribedCount"><?= $prescribed_count ?></span>
        </button>
        
        <button type="button" class="status-toggle-btn" data-status="waiting" onclick="filterByStatus('waiting')">
            <i class="fas fa-clock"></i> 
            Waiting 
            <span class="toggle-count" id="toggleWaitingCount"><?= $waiting_count ?></span>
        </button>
        
        <span style="margin-left:auto;font-size:0.78rem;color:var(--text-secondary);font-weight:600;">
            <i class="fas fa-users"></i> 
            Total: <strong id="totalBranchPatients"><?= $branch_patients_total ?></strong>
        </span>
    </div>

    <!-- PATIENTS LIST -->
    <div class="modern-card animate-fade-in-up" style="max-width:1300px;margin:0 auto 20px;" id="patientsListCard">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-user-check" id="listIcon"></i>
                <span id="listTitle">Assigned Patients (With Doctor)</span>
                <span class="card-badge" style="background:var(--success-bg);color:var(--success);" id="listCountBadge"><?= $assigned_count ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
                <span class="text-xs text-gray-400" style="font-size:0.7rem;" id="listUpdateTime">(Auto-updated <?= date('h:i:s A') ?>)</span>
                <span class="text-xs text-green-500" style="font-size:0.7rem;">
                    <span class="live-indicator-modern"></span> Live
                </span>
            </div>
        </div>
        
        <div id="patientsListContainer">
            <div style="text-align:center;padding:30px;">
                <div class="spinner" style="border-color:var(--border-color);border-top-color:var(--primary);"></div>
                <p style="font-size:0.85rem;color:var(--text-secondary);margin-top:8px;">Loading...</p>
            </div>
        </div>
    </div>

    <!-- ASSIGN FORM -->
    <div class="form-card-modern animate-fade-in-up" id="mainFormCard" style="animation-delay:0.1s;">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas <?= $change_mode ? 'fa-sync-alt' : 'fa-stethoscope' ?>"></i>
            </div>
            <div>
                <h3 class="form-title">
                    <?= $change_mode ? '🔄 Change Doctor' : 'Assign / Change Doctor or Lab Test' ?>
                    <?php if ($change_mode && $selected_patient_data): ?>
                        <span style="font-weight:400;font-size:0.8rem;color:var(--warning);">
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
            <input type="hidden" name="patient_id" id="selectedPatientInput" value="<?= $selected_patient_id ?>">
            
            <div class="form-grid-6">
                
                <!-- LEFT SIDE -->
                <div class="form-grid-left">
                    
                    <!-- CARD 1: Select Patient -->
                    <div class="form-card-item">
                        <div class="card-item-title">
                            <i class="fas fa-user"></i> Select Patient <span class="required">*</span>
                            <span class="badge-label" id="patientCountBadge">All Patients (<?= $branch_patients_total ?>)</span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            
                            <button type="button" class="patient-toggle-btn" id="patientToggleBtn" onclick="togglePatientList()">
                                <span id="patientToggleLabel">
                                    <i class="fas fa-users"></i> 
                                    <span id="selectedPatientLabel">
                                        <?php if ($selected_patient_data): ?>
                                            ✓ <?= htmlspecialchars($selected_patient_data['full_name']) ?> (<?= htmlspecialchars($selected_patient_data['patient_id'] ?? '') ?>)
                                        <?php else: ?>
                                            Click to Select Patient
                                        <?php endif; ?>
                                    </span>
                                </span>
                                <i class="fas fa-chevron-down toggle-arrow"></i>
                            </button>
                            
                            <div class="patient-toggle-content" id="patientToggleContent">
                                
                                <div class="patient-search-wrapper">
                                    <i class="fas fa-search"></i>
                                    <input type="text" 
                                           id="patientSearchFilter" 
                                           placeholder="🔍 Search patient by name, ID, or phone..." 
                                           oninput="filterPatientList(this.value)">
                                </div>
                                
                                <div class="patient-list-container" id="patientListContainer">
                                    <?php if (!empty($all_patients) && count($all_patients) > 0): ?>
                                        <?php foreach ($all_patients as $patient): 
                                            $status_label = 'No Visit';
                                            $status_class = 'no_visit';
                                            $status_icon = '📋';
                                            
                                            if (!empty($patient['visit_id'])) {
                                                if ($patient['visit_status'] === 'lab_test') {
                                                    $status_label = 'Lab Test'; $status_class = 'lab_only'; $status_icon = '🧪';
                                                } elseif ($patient['visit_status'] === 'waiting') {
                                                    $status_label = 'Waiting'; $status_class = 'pending'; $status_icon = '⏳';
                                                } elseif ($patient['visit_status'] === 'prescribed') {
                                                    $status_label = 'Prescribed'; $status_class = 'lab_only'; $status_icon = '💊';
                                                } elseif (in_array($patient['visit_status'], ['new', 'pending'])) {
                                                    $status_label = 'Pending'; $status_class = 'pending'; $status_icon = '🟡';
                                                } elseif (in_array($patient['visit_status'], ['assigned', 'with_doctor'])) {
                                                    $status_label = 'Assigned'; $status_class = 'assigned'; $status_icon = '✅';
                                                }
                                            }
                                            
                                            $doctor_info = '';
                                            if (!empty($patient['assigned_doctor_name'])) {
                                                $online_status = !empty($patient['assigned_doctor_online']) ? '🟢' : '⚪';
                                                $doctor_info = 'Dr. ' . htmlspecialchars($patient['assigned_doctor_name']) . ' ' . $online_status;
                                            }
                                            
                                            $is_selected = ($selected_patient_id == $patient['id']) ? 'selected' : '';
                                            $days = (int)($patient['patient_days'] ?? 0);
                                            $days_text = $days > 0 ? '📅 ' . $days . 'd' : '📅 New';
                                            
                                            $search_data = strtolower($patient['full_name'] . ' ' . ($patient['patient_id'] ?? '') . ' ' . ($patient['phone'] ?? ''));
                                        ?>
                                            <div class="patient-list-item <?= $is_selected ?>" 
                                                 data-patient-id="<?= $patient['id'] ?>"
                                                 data-search="<?= htmlspecialchars($search_data) ?>"
                                                 onclick="selectPatient(<?= $patient['id'] ?>, '<?= htmlspecialchars(addslashes($patient['full_name'])) ?>', '<?= htmlspecialchars($patient['patient_id'] ?? '') ?>')">
                                                <span class="patient-icon"><?= $status_icon ?></span>
                                                <div class="patient-info">
                                                    <div class="patient-name">
                                                        <?= htmlspecialchars($patient['full_name']) ?>
                                                        <span class="days-badge-blue"><?= $days_text ?></span>
                                                    </div>
                                                    <div class="patient-meta">
                                                        <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span>
                                                        <span class="status-badge-dropdown <?= $status_class ?>"><?= $status_label ?></span>
                                                        <?php if ($doctor_info): ?>
                                                            <span style="color:var(--primary);">👨‍⚕️ <?= $doctor_info ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div style="padding:20px;text-align:center;color:var(--text-secondary);font-size:0.85rem;">
                                            <i class="fas fa-user-slash"></i>
                                            <p>No patients found</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="margin-top:10px;font-size:0.72rem;color:var(--text-secondary);font-weight:600;" id="patientStats">
                                    <span style="color:var(--warning);">🟡 <span id="pendingStat"><?= $pending_count ?></span> Pending</span>
                                    <span style="margin:0 6px;">|</span>
                                    <span style="color:var(--success);">✅ <span id="assignedStat"><?= $assigned_count ?></span> Assigned</span>
                                    <span style="margin:0 6px;">|</span>
                                    <span style="color:var(--purple);">🧪 <span id="labOnlyStat"><?= $lab_only_count ?></span> Lab</span>
                                    <span style="margin:0 6px;">|</span>
                                    <span style="color:#D97706;">⏳ <span id="waitingStat"><?= $waiting_count ?></span> Waiting</span>
                                    <span style="margin:0 6px;">|</span>
                                    <span style="color:#059669;">💊 <span id="prescribedStat"><?= $prescribed_count ?></span> Prescribed</span>
                                </div>
                            </div>
                            
                            <div id="selectedPatientInfo" style="display:<?= $selected_patient_id > 0 && $selected_patient_data ? 'block' : 'none' ?>;margin-top:8px;padding:10px 14px;background:var(--primary-bg);border-radius:var(--radius);border:1px solid var(--primary-light);">
                                <?php if ($selected_patient_data): 
                                    $patient_days = (int)($selected_patient_data['patient_days'] ?? 0);
                                    $days_text = $patient_days > 0 ? '<span class="days-badge-blue">📅 ' . $patient_days . ' days ago</span>' : '<span class="days-badge-blue new">📅 Just registered</span>';
                                ?>
                                    <div style="display:flex;align-items:center;gap:8px;font-size:0.78rem;flex-wrap:wrap;">
                                        <i class="fas fa-user-circle" style="color:var(--primary);"></i>
                                        <span style="font-weight:700;"><?= htmlspecialchars($selected_patient_data['full_name'] ?? '') ?></span>
                                        <span style="color:var(--text-secondary);">|</span>
                                        <span><?= htmlspecialchars($selected_patient_data['patient_id'] ?? '') ?></span>
                                        <?= $days_text ?>
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
                            <p style="font-size:0.72rem;color:var(--text-secondary);margin-top:8px;" id="assignmentTypeHelp">👨‍⚕️ Assign a doctor to the patient or change existing doctor</p>
                        </div>
                    </div>
                    
                    <!-- CARD 3: Select Doctor -->
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
                            
                            <p style="font-size:0.72rem;color:var(--text-secondary);margin-top:8px;" id="doctorAvailability">
                                <i class="fas fa-info-circle"></i>
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
                            <p style="font-size:0.72rem;color:var(--text-secondary);margin-top:8px;" id="visitTypeDescription">
                                <i class="fas fa-info-circle"></i>
                                <?= isset($visit_type_options[$default_service_id]) ? ($visit_type_options[$default_service_id]['description'] ?? 'Select a visit type') : 'No consultation services available' ?>
                            </p>
                            <div id="feeNote" style="margin-top:4px;font-size:0.72rem;color:var(--primary);">
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
                            <select name="symptoms_select" class="form-control-modern" id="symptomsSelect" style="min-height:40px;">
                                <option value="">-- Select Common Symptom --</option>
                                <?php foreach ($common_symptoms as $symptom): ?>
                                    <option value="<?= htmlspecialchars($symptom) ?>"><?= htmlspecialchars($symptom) ?></option>
                                <?php endforeach; ?>
                                <option value="other">✏️ Other (Type below)</option>
                            </select>
                            <textarea name="symptoms" class="form-control-modern textarea" placeholder="Describe patient symptoms in detail..." id="symptomsTextarea" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <!-- CARD 6: Lab Tests -->
                    <div class="form-card-item" id="labSection" style="display:none;">
                        <div class="card-item-title">
                            <i class="fas fa-flask" style="color:var(--purple);"></i> Select Lab Tests
                            <span class="badge-label" id="labSelectedCount">(0 selected)</span>
                            <span class="badge-label" style="background:var(--purple);color:white;border-radius:10px;padding:3px 10px;font-size:0.6rem;">🧪 Lab Only</span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            <div class="lab-modal-container-modern">
                                <div class="lab-modal-header-modern">
                                    <div class="lab-modal-title">
                                        <i class="fas fa-flask" style="color:var(--purple);"></i>
                                        Available Lab Tests
                                        <span style="font-size:0.75rem;color:var(--text-secondary);font-weight:400;">(<?= count($lab_tests_catalog) ?> tests)</span>
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
                                            <p style="font-size:0.85rem;">No lab tests available</p>
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
                                        <span style="font-size:0.75rem;color:var(--text-secondary);" id="labSelectionCount">(0 tests)</span>
                                    </div>
                                    <button type="button" class="btn-modern btn-modern-purple btn-modern-sm" onclick="closeLabTests()" style="background:var(--danger);">
                                        <i class="fas fa-times"></i> Close
                                    </button>
                                </div>
                            </div>
                            
                            <div class="lab-selected-summary-modern" id="labSelectedSummary" style="display:none;">
                                <span style="font-weight:700;color:var(--primary);">
                                    <i class="fas fa-check-circle"></i> Selected:
                                </span>
                                <span id="labSelectedNames" style="color:var(--text-primary);"></span>
                                <span style="font-size:0.75rem;color:var(--text-secondary);margin-left:6px;">
                                    (Bill sent to Cashier)
                                </span>
                            </div>
                            
                            <input type="hidden" name="lab_test_ids" id="selectedLabTestsInput" value="">
                        </div>
                    </div>
                    
                </div>
                
            </div>
            
            <!-- VITAL SIGNS -->
            <div class="form-card-item" style="margin-top:18px;">
                <div class="card-item-title">
                    <i class="fas fa-heartbeat" style="color:#DC2626;"></i> Vital Signs
                    <span class="badge-label">Optional - 7 Vitals</span>
                    <?php if ($selected_patient_id > 0 && $latest_vital_signs): ?>
                        <span style="font-size:0.7rem;color:var(--success);margin-left:8px;">
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
                    
                    <div class="vital-item-modern spo2-item">
                        <span class="vital-label">🫁 Oxygen Saturation (SpO₂)</span>
                        <input type="number" name="oxygen_saturation" class="vital-input" placeholder="98" value="<?= $latest_vital_signs['oxygen_saturation'] ?? '' ?>" id="spo2Input" oninput="calculateSpO2Category()">
                        <span class="vital-unit">%</span>
                        <span class="spo2-category" id="spo2Category">Auto</span>
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
                <button type="submit" class="btn-modern <?= $change_mode ? 'btn-modern-outline' : 'btn-modern-primary' ?>" id="assignBtn" style="<?= $change_mode ? 'background:var(--warning);color:white;border-color:var(--warning);' : '' ?>">
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

    <footer class="footer-modern">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            Assign / Change / Reassign Doctor
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
        <p style="font-weight:700;font-size:0.88rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.78rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    // ============================================================
    // TOAST
    // ============================================================
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

    // ============================================================
    // TOGGLE PATIENT LIST
    // ============================================================
    function togglePatientList() {
        var content = document.getElementById('patientToggleContent');
        var btn = document.getElementById('patientToggleBtn');
        if (content && btn) {
            content.classList.toggle('open');
            btn.classList.toggle('active');
        }
    }

    // ============================================================
    // SELECT PATIENT
    // ============================================================
    function selectPatient(patientId, patientName, patientCode) {
        document.getElementById('selectedPatientInput').value = patientId;
        
        var label = document.getElementById('selectedPatientLabel');
        if (label) label.innerHTML = '✓ ' + patientName + ' (' + patientCode + ')';
        
        document.querySelectorAll('.patient-list-item').forEach(function(item) {
            item.classList.remove('selected');
            if (item.getAttribute('data-patient-id') == patientId) {
                item.classList.add('selected');
            }
        });
        
        var content = document.getElementById('patientToggleContent');
        var btn = document.getElementById('patientToggleBtn');
        if (content) content.classList.remove('open');
        if (btn) btn.classList.remove('active');
        
        fetchPatientDetails(patientId);
        showToast('👤 Patient Selected', patientName, 'success');
    }

    // ============================================================
    // FILTER PATIENT LIST
    // ============================================================
    function filterPatientList(query) {
        var searchTerm = query.toLowerCase().trim();
        var items = document.querySelectorAll('.patient-list-item');
        var visibleCount = 0;
        
        items.forEach(function(item) {
            var searchData = item.getAttribute('data-search') || '';
            if (searchTerm === '' || searchData.indexOf(searchTerm) !== -1) {
                item.style.display = 'flex';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
        
        var badge = document.getElementById('patientCountBadge');
        if (badge) {
            if (searchTerm === '') {
                badge.textContent = 'All Patients (<?= $branch_patients_total ?>)';
            } else {
                badge.textContent = 'Found: ' + visibleCount;
            }
        }
    }

    // ============================================================
    // SYMPTOMS
    // ============================================================
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

    // ============================================================
    // BMI + SPO2
    // ============================================================
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

    function calculateSpO2Category() {
        var spo2Input = document.getElementById('spo2Input');
        var spo2Category = document.getElementById('spo2Category');
        if (!spo2Input || !spo2Category) return;
        
        var spo2 = parseFloat(spo2Input.value);
        if (!spo2 || spo2 <= 0) {
            spo2Category.textContent = 'Auto';
            spo2Category.className = 'spo2-category';
            return;
        }
        
        var category = '';
        var categoryClass = '';
        if (spo2 >= 95) { category = 'Normal'; categoryClass = 'spo2-normal'; }
        else if (spo2 >= 90) { category = 'Low'; categoryClass = 'spo2-low'; }
        else { category = 'Critical'; categoryClass = 'spo2-critical'; }
        
        spo2Category.textContent = category;
        spo2Category.className = 'spo2-category ' + categoryClass;
    }

    // ============================================================
    // TOGGLE ASSIGNMENT TYPE
    // ============================================================
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

    // ============================================================
    // LAB FUNCTIONS
    // ============================================================
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

    // ============================================================
    // CHANGE DOCTOR (open form)
    // ============================================================
    function changeDoctor(patientId) {
        window.location.href = 'assign_doctor.php?patient_id=' + patientId + '&change=1';
    }

    // ============================================================
    // REASSIGN DOCTOR
    // ============================================================
    function reassignDoctor(patientId, visitId) {
        if (!confirm('⚠️ Reassign patient?\n\nThis will REMOVE the current doctor from this patient.\nPatient will return to Pending list.\n\nContinue?')) return;
        
        var formData = new FormData();
        formData.append('action', 'reassign_doctor');
        formData.append('patient_id', patientId);
        formData.append('visit_id', visitId);
        
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast('✅ Reassigned', data.message, 'success');
                    var row = document.getElementById('patient-row-' + patientId);
                    if (row) {
                        row.style.transition = 'all 0.5s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(-20px)';
                        setTimeout(function() { 
                            row.remove(); 
                            setTimeout(function() { location.reload(); }, 800);
                        }, 500);
                    }
                } else {
                    showToast('❌ Error', data.message || 'Failed to reassign', 'error');
                }
            })
            .catch(function(error) {
                showToast('❌ Error', 'Network error: ' + error.message, 'error');
            });
    }

    // ============================================================
    // FILTER BY STATUS
    // ============================================================
    function filterByStatus(status) {
        document.querySelectorAll('.status-toggle-btn').forEach(function(btn) {
            btn.classList.remove('active');
            if (btn.dataset.status === status) btn.classList.add('active');
        });
        
        var titles = {
            'assigned': { title: 'Assigned Patients (With Doctor)', icon: 'fa-user-check', color: 'var(--success)' },
            'lab_test': { title: 'Lab Test Patients', icon: 'fa-flask', color: 'var(--purple)' },
            'prescribed': { title: 'Prescribed Patients', icon: 'fa-prescription', color: '#059669' },
            'waiting': { title: 'Waiting Patients', icon: 'fa-clock', color: '#D97706' }
        };
        
        var t = titles[status] || titles['assigned'];
        var listTitle = document.getElementById('listTitle');
        var listIcon = document.getElementById('listIcon');
        var listCountBadge = document.getElementById('listCountBadge');
        
        if (listTitle) listTitle.textContent = t.title;
        if (listIcon) { listIcon.className = 'fas ' + t.icon; listIcon.style.color = t.color; }
        
        var counts = {
            'assigned': document.getElementById('toggleAssignedCount')?.textContent || 0,
            'lab_test': document.getElementById('toggleLabCount')?.textContent || 0,
            'prescribed': document.getElementById('togglePrescribedCount')?.textContent || 0,
            'waiting': document.getElementById('toggleWaitingCount')?.textContent || 0
        };
        if (listCountBadge) listCountBadge.textContent = counts[status] || 0;
        
        fetchFilteredList(status);
    }

    function fetchFilteredList(status) {
        var container = document.getElementById('patientsListContainer');
        if (!container) return;
        
        container.innerHTML = '<div style="text-align:center;padding:30px;"><div class="spinner" style="border-color:var(--border-color);border-top-color:var(--primary);"></div><p style="font-size:0.85rem;color:var(--text-secondary);margin-top:8px;">Loading...</p></div>';
        
        var formData = new FormData();
        formData.append('action', 'get_filtered_list');
        formData.append('status', status);
        
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success && data.html) {
                    container.innerHTML = data.html;
                } else {
                    var icons = {
                        'assigned': 'fa-user-check',
                        'lab_test': 'fa-flask',
                        'prescribed': 'fa-prescription',
                        'waiting': 'fa-clock'
                    };
                    var msgs = {
                        'assigned': 'No patients currently assigned to a doctor',
                        'lab_test': 'No lab test requests pending',
                        'prescribed': 'No prescribed patients',
                        'waiting': 'No waiting patients'
                    };
                    container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--text-secondary);"><i class="fas ' + (icons[status] || 'fa-inbox') + '" style="font-size:2rem;"></i><p style="font-size:0.88rem;margin-top:8px;">' + (msgs[status] || 'No patients found') + '</p></div>';
                }
            })
            .catch(function() {
                container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--danger);"><i class="fas fa-exclamation-triangle"></i> Error loading data</div>';
            });
    }

    // ============================================================
    // FETCH PATIENT DETAILS
    // ============================================================
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
                            ? '<span style="background:var(--primary-bg);padding:3px 10px;border-radius:12px;border:1px solid var(--primary-light);font-size:0.72rem;font-weight:700;"><i class="fas fa-user-md" style="color:var(--primary);"></i> Dr. ' + escapeHtml(doctorName) + '</span>'
                            : '<span style="color:var(--text-secondary);font-size:0.72rem;">No doctor assigned</span>';
                        var patientDays = data.patient_days || 0;
                        var daysHtml = '<span class="days-badge-blue">📅 ' + (patientDays > 0 ? patientDays + ' days ago' : 'Just registered') + '</span>';
                        
                        infoDiv.innerHTML = '<div style="display:flex;align-items:center;gap:8px;font-size:0.78rem;flex-wrap:wrap;">' +
                            '<i class="fas fa-user-circle" style="color:var(--primary);"></i>' +
                            '<span style="font-weight:700;">' + escapeHtml(data.patient.full_name || '') + '</span>' +
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

    // ============================================================
    // LIVE UPDATE
    // ============================================================
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
        document.getElementById('assignedCount').textContent = data.assigned_count;
        document.getElementById('labOnlyCount').textContent = data.lab_only_count;
        document.getElementById('prescribedCount').textContent = data.prescribed_count;
        document.getElementById('waitingCount').textContent = data.waiting_count;
        
        document.getElementById('pendingStat').textContent = data.pending_count;
        document.getElementById('assignedStat').textContent = data.assigned_count;
        document.getElementById('labOnlyStat').textContent = data.lab_only_count;
        document.getElementById('waitingStat').textContent = data.waiting_count;
        document.getElementById('prescribedStat').textContent = data.prescribed_count;
        
        document.getElementById('toggleAssignedCount').textContent = data.assigned_count;
        document.getElementById('toggleLabCount').textContent = data.lab_only_count;
        document.getElementById('togglePrescribedCount').textContent = data.prescribed_count;
        document.getElementById('toggleWaitingCount').textContent = data.waiting_count;
        
        document.getElementById('onlineDoctorCount').textContent = data.online_count;
        document.getElementById('offlineDoctorCount').textContent = data.offline_count;
        document.getElementById('onlineCountDisplay').textContent = '🟢 ' + data.online_count + ' online';
        document.getElementById('offlineCountDisplay').textContent = '⚪ ' + data.offline_count + ' offline';
        document.getElementById('totalBranchPatients').textContent = data.branch_patients_total;
        
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        document.getElementById('listUpdateTime').textContent = '(Auto-updated ' + timeStr + ')';
    }

    function startLiveUpdate() {
        if (updateInterval) clearInterval(updateInterval);
        fetchLiveData();
        updateInterval = setInterval(fetchLiveData, 5000);
    }

    function stopLiveUpdate() {
        if (updateInterval) { clearInterval(updateInterval); updateInterval = null; }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) stopLiveUpdate();
        else startLiveUpdate();
    });

    // ============================================================
    // FORM SUBMIT
    // ============================================================
    document.getElementById('assignForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        formData.append('action', 'change_doctor');
        
        var patientId = document.getElementById('selectedPatientInput').value;
        if (!patientId || patientId === '0') {
            showToast('❌ Error', 'Please select a patient first', 'error');
            return;
        }
        
        var visitTypeSelect = document.getElementById('visitTypeSelect');
        if (visitTypeSelect) formData.append('service_id', visitTypeSelect.value);
        
        var hiddenInput = document.getElementById('selectedLabTestsInput');
        if (hiddenInput && hiddenInput.value) {
            hiddenInput.value.split(',').forEach(function(id) { if (id) formData.append('lab_test_ids[]', id); });
        }
        
        var btn = document.getElementById('assignBtn');
        var originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Processing...';
        
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                
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
                btn.innerHTML = originalHTML;
                showToast('❌ Error', 'Network error: ' + error.message, 'error');
            });
    });

    // ============================================================
    // INIT
    // ============================================================
    document.addEventListener('DOMContentLoaded', function() {
        calculateBMI();
        calculateSpO2Category();
        updateVisitTypePrice();
        
        filterByStatus('assigned');
        setTimeout(function() { startLiveUpdate(); }, 2000);
        
        var assignmentType = document.getElementById('assignmentTypeSelect');
        if (assignmentType && assignmentType.value === 'lab') toggleAssignmentType('lab');
        
        <?php if ($change_mode && $selected_patient_id > 0): ?>
        var content = document.getElementById('patientToggleContent');
        var btn = document.getElementById('patientToggleBtn');
        if (content && btn) { content.classList.add('open'); btn.classList.add('active'); }
        <?php endif; ?>
    });

    console.log('%c👨‍⚕️ Braick - Assign / Reassign Doctor V7', 'font-size:18px; font-weight:bold; color:#2563EB;');
    console.log('%c✅ Shared Header + Sidebar: YES', 'font-size:13px; color:#059669;');
    console.log('%c✅ COMPACT Buttons (mini style)', 'font-size:13px; color:#D97706; font-weight:bold;');
    console.log('%c✅ Patient Count: <?= $branch_patients_total ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c✅ Mobile: Icon-only buttons', 'font-size:13px; color:#0EA5E9;');
</script>

</body>
</html>