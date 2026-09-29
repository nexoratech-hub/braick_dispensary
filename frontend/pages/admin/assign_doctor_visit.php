<?php
// ================================================================
// FILE: frontend/pages/admin/assign_doctor.php
// ADMIN - ASSIGN / CHANGE / REASSIGN DOCTOR & LAB TESTS (V26)
// ================================================================
// ✅ V26: CHANGE DOCTOR → INAFUTA visit ya zamani + bill yake KABISA
//         kisha inaunda visit MPYA + bill MPYA (hakuna duplicate!)
// ✅ V26: REASSIGN → INAFUTA visit + bills + bill_items + prescriptions
//         + lab_tests + vital_signs + procedures + payments
// ✅ V26: Patient assigned_doctor_id = NULL baada ya reassign
// ✅ V25: Filters 5 tu: Assigned, Lab Test, Prescribe, Waiting, Complete
// ✅ V22: Custom modal (no popup) for Delete/Complete/Reassign
// ✅ V22: Auto refresh page baada ya action
// BRAICK DISPENSARY
// ================================================================

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    ob_end_clean();
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['admin', 'reception'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    ob_end_clean();
    header('Location: ../login.php');
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 1);
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = (int)($_SESSION['branch_id'] ?? 1);
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$show_assigned_by = ($user_role === 'admin');

$selected_branch_id = $user_branch_id;
$branch_name = $user_branch_name;

if (isset($_GET['branch']) && $_GET['branch'] !== '' && $_GET['branch'] !== 'all') {
    $selected_branch_id = (int)$_GET['branch'];
}

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    ob_end_clean();
    die("Database connection error: " . $e->getMessage());
}

$message = '';
$message_type = '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$all_patients = [];
$unique_patients_for_dropdown = [];
$assigned_patients = [];
$lab_only_patients = [];
$waiting_patients = [];
$prescribed_patients = [];
$complete_patients = [];
$doctors = [];
$online_doctors = [];
$offline_doctors = [];
$online_doctors_count = 0;
$offline_doctors_count = 0;
$visit_type_options = [];
$assigned_count = 0;
$lab_only_count = 0;
$waiting_count = 0;
$prescribed_count = 0;
$complete_count = 0;
$branch_patients_total = 0;
$selected_patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$latest_vital_signs = null;
$selected_patient_data = null;
$change_mode = isset($_GET['change']) && $_GET['change'] == 1;
$lab_tests_catalog = [];
$unread_notifications = 0;

try {
    try {
        $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
        $stmt->execute([$selected_branch_id]);
        $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($branch_data) $branch_name = $branch_data['name'];
    } catch (Exception $e) {}

    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {}

    // CONSULTATION SERVICES
    $stmt = $db->prepare("
        SELECT id, service_name, description, price, unit, is_active
        FROM services 
        WHERE category_id = 2 AND is_active = 1 
        AND (branch_id = ? OR branch_id IS NULL)
        ORDER BY 
            CASE 
                WHEN service_name LIKE '%New Patient%' THEN 0
                WHEN service_name LIKE '%General%' THEN 1
                WHEN service_name LIKE '%Emergency%' THEN 2
                WHEN service_name LIKE '%Specialist%' THEN 3
                WHEN service_name LIKE '%Follow%' THEN 4
                ELSE 5
            END, service_name
    ");
    $stmt->execute([$selected_branch_id]);
    $consultation_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $visit_type_options = [];
    $default_service_id = null;
    
    foreach ($consultation_services as $service) {
        $service_id = $service['id'];
        $service_name = $service['service_name'];
        $price = (float)$service['price'];
        
        $icon = '🏥';
        if (strpos(strtolower($service_name), 'new') !== false) $icon = '🆕';
        elseif (strpos(strtolower($service_name), 'follow') !== false) $icon = '🔄';
        elseif (strpos(strtolower($service_name), 'emergency') !== false) $icon = '🚨';
        elseif (strpos(strtolower($service_name), 'specialist') !== false) $icon = '👨‍⚕️';
        
        $visit_type_options[$service_id] = [
            'id' => $service_id,
            'service_name' => $service_name,
            'price' => $price,
            'description' => $service['description'] ?? '',
            'icon' => $icon
        ];
        
        if (strpos(strtolower($service_name), 'new') !== false) {
            $default_service_id = $service_id;
        } elseif (strpos(strtolower($service_name), 'general') !== false && $default_service_id === null) {
            $default_service_id = $service_id;
        }
    }
    
    if ($default_service_id === null && !empty($visit_type_options)) {
        $default_service_id = array_key_first($visit_type_options);
    }
    
    // LAB TESTS CATALOG
    $stmt = $db->prepare("SELECT id, test_name, price, category FROM lab_tests_catalog WHERE is_active = 1 ORDER BY category, test_name");
    $stmt->execute();
    $lab_tests_catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ALL PATIENTS
    $query = "
        SELECT 
            p.id, p.full_name, p.patient_id, p.phone, p.gender,
            p.assigned_doctor_id, p.created_at as patient_created_at,
            v.id as visit_id, v.status as visit_status, v.visit_number,
            v.visit_type, v.service_id, v.consultation_fee,
            v.created_at as visit_created_at,
            v.doctor_id as visit_doctor_id,
            u.full_name as assigned_doctor_name,
            u.is_online as assigned_doctor_online,
            u_assigned.full_name as assigned_by_name,
            u_assigned.role as assigned_by_role,
            v.assigned_at,
            DATEDIFF(NOW(), p.created_at) as patient_days
        FROM patients p
        LEFT JOIN visits v ON p.id = v.patient_id 
            AND (
                v.status IN ('new', 'pending', 'assigned', 'with_doctor', 'lab_test', 'waiting', 'prescribed', 'completed', 'cancelled')
                OR v.status IS NULL
            )
            AND v.branch_id = ?
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN users u_assigned ON v.assigned_by_id = u_assigned.id
        WHERE p.branch_id = ?
    ";
    $params = [$selected_branch_id, $selected_branch_id];
    
    if (!empty($search)) {
        $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " ORDER BY v.created_at DESC, p.id DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $all_patients_raw = $stmt->fetchAll();
    
    $all_patients = [];
    foreach ($all_patients_raw as $patient) {
        $all_patients[] = $patient;
    }
    
    // DEDUPLICATE FOR DROPDOWN
    $unique_patients_for_dropdown = [];
    $seen_patients = [];
    
    foreach ($all_patients as $patient) {
        if (!empty($patient['assigned_doctor_name']) && !in_array($patient['id'], $seen_patients)) {
            $seen_patients[] = $patient['id'];
            $unique_patients_for_dropdown[] = $patient;
        }
    }
    
    foreach ($all_patients as $patient) {
        if (!in_array($patient['id'], $seen_patients)) {
            $seen_patients[] = $patient['id'];
            $unique_patients_for_dropdown[] = $patient;
        }
    }
    
    $branch_patients_total = count($unique_patients_for_dropdown);
    
    // CATEGORIZE
    foreach ($all_patients as $patient) {
        $patient['has_active_visit'] = !empty($patient['visit_id']);
        $patient['patient_days'] = isset($patient['patient_days']) ? (int)$patient['patient_days'] : 0;
        
        if ($patient['has_active_visit']) {
            $status = $patient['visit_status'];
            $has_doctor = !empty($patient['visit_doctor_id']);
            
            if ($status === 'lab_test') {
                $lab_only_patients[] = $patient;
            }
            elseif ($status === 'waiting') {
                $waiting_patients[] = $patient;
            }
            elseif ($status === 'prescribed') {
                $prescribed_patients[] = $patient;
            }
            elseif (in_array($status, ['assigned', 'with_doctor']) && $has_doctor) {
                $assigned_patients[] = $patient;
            }
            elseif (in_array($status, ['completed', 'cancelled'])) {
                $complete_patients[] = $patient;
            }
        }
    }
    
    $assigned_count = count($assigned_patients);
    $lab_only_count = count($lab_only_patients);
    $waiting_count = count($waiting_patients);
    $prescribed_count = count($prescribed_patients);
    $complete_count = count($complete_patients);
    
    if ($selected_patient_id > 0) {
        foreach ($all_patients as $p) {
            if ($p['id'] == $selected_patient_id) {
                $selected_patient_data = $p;
                break;
            }
        }
        
        $stmt = $db->prepare("SELECT vs.* FROM vital_signs vs WHERE vs.patient_id = ? ORDER BY vs.recorded_at DESC LIMIT 1");
        $stmt->execute([$selected_patient_id]);
        $latest_vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // DOCTORS
    $stmt = $db->prepare("SELECT id, full_name, specialty, is_online FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ? ORDER BY is_online DESC, full_name");
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
    
    // ============================================================
    // ✅ V26: HELPER - DELETE OLD VISIT DATA (KWA CHANGE DOCTOR)
    // Inafuta visit + bills + bill_items + payments + prescriptions
    // + prescription_items + lab_tests + vital_signs + procedures
    // Pia inarudisha stock kwa medications/equipment zilizotumika
    // ============================================================
    function deleteOldVisitData($db, $visit_id) {
        $deleted = [
            'payments' => 0, 'bill_items' => 0, 'bills' => 0,
            'prescription_items' => 0, 'prescriptions' => 0,
            'lab_tests' => 0, 'vital_signs' => 0, 'procedures' => 0,
            'visits' => 0
        ];
        $restored_meds = 0;
        $restored_equip = 0;
        
        // Rudisha medication stock kwa items zilizodispensed/confirmed
        try {
            $stmt = $db->prepare("
                SELECT pi.inventory_id, pi.quantity
                FROM prescription_items pi
                INNER JOIN prescriptions p ON pi.prescription_id = p.id
                WHERE p.visit_id = ?
                AND pi.inventory_id IS NOT NULL
                AND (pi.confirmed_at IS NOT NULL OR pi.dispensed_at IS NOT NULL)
                AND pi.cancelled_at IS NULL
            ");
            $stmt->execute([$visit_id]);
            $presc_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($presc_items as $item) {
                $qty = (int)$item['quantity'];
                if ($qty > 0 && !empty($item['inventory_id'])) {
                    $stmt2 = $db->prepare("UPDATE medications_inventory SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?");
                    $stmt2->execute([$qty, $item['inventory_id']]);
                    $restored_meds++;
                }
            }
        } catch (Exception $e) {}
        
        // Rudisha equipment stock kwa lab tests zilizotumika
        try {
            $stmt = $db->prepare("
                SELECT lte.equipment_id
                FROM lab_test_equipment lte
                INNER JOIN lab_tests lt ON lte.lab_test_id = lt.id
                WHERE lt.visit_id = ?
            ");
            $stmt->execute([$visit_id]);
            $equipment_links = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($equipment_links as $link) {
                if (!empty($link['equipment_id'])) {
                    $stmt2 = $db->prepare("UPDATE medical_equipment SET quantity = quantity + 1, updated_at = NOW() WHERE id = ?");
                    $stmt2->execute([$link['equipment_id']]);
                    $restored_equip++;
                }
            }
        } catch (Exception $e) {}
        
        // 1. Futa payments
        try {
            $stmt = $db->prepare("DELETE p FROM payments p INNER JOIN bills b ON p.bill_id = b.id WHERE b.visit_id = ?");
            $stmt->execute([$visit_id]);
            $deleted['payments'] = $stmt->rowCount();
        } catch (Exception $e) {}
        
        // 2. Futa bill_items
        try {
            $stmt = $db->prepare("DELETE bi FROM bill_items bi INNER JOIN bills b ON bi.bill_id = b.id WHERE b.visit_id = ?");
            $stmt->execute([$visit_id]);
            $deleted['bill_items'] = $stmt->rowCount();
        } catch (Exception $e) {}
        
        // 3. Futa bills
        try {
            $stmt = $db->prepare("DELETE FROM bills WHERE visit_id = ?");
            $stmt->execute([$visit_id]);
            $deleted['bills'] = $stmt->rowCount();
        } catch (Exception $e) {}
        
        // 4. Futa prescription_items
        try {
            $stmt = $db->prepare("DELETE pi FROM prescription_items pi INNER JOIN prescriptions p ON pi.prescription_id = p.id WHERE p.visit_id = ?");
            $stmt->execute([$visit_id]);
            $deleted['prescription_items'] = $stmt->rowCount();
        } catch (Exception $e) {}
        
        // 5. Futa prescriptions
        try {
            $stmt = $db->prepare("DELETE FROM prescriptions WHERE visit_id = ?");
            $stmt->execute([$visit_id]);
            $deleted['prescriptions'] = $stmt->rowCount();
        } catch (Exception $e) {}
        
        // 6. Futa lab_test_equipment links
        try {
            $stmt = $db->prepare("DELETE lte FROM lab_test_equipment lte INNER JOIN lab_tests lt ON lte.lab_test_id = lt.id WHERE lt.visit_id = ?");
            $stmt->execute([$visit_id]);
        } catch (Exception $e) {}
        
        // 7. Futa lab_tests
        try {
            $stmt = $db->prepare("DELETE FROM lab_tests WHERE visit_id = ?");
            $stmt->execute([$visit_id]);
            $deleted['lab_tests'] = $stmt->rowCount();
        } catch (Exception $e) {}
        
        // 8. Futa vital_signs
        try {
            $stmt = $db->prepare("DELETE FROM vital_signs WHERE visit_id = ?");
            $stmt->execute([$visit_id]);
            $deleted['vital_signs'] = $stmt->rowCount();
        } catch (Exception $e) {}
        
        // 9. Futa procedures
        try {
            $stmt = $db->prepare("DELETE FROM procedures WHERE visit_id = ?");
            $stmt->execute([$visit_id]);
            $deleted['procedures'] = $stmt->rowCount();
        } catch (Exception $e) {}
        
        // 10. Futa visit yenyewe
        try {
            $stmt = $db->prepare("DELETE FROM visits WHERE id = ?");
            $stmt->execute([$visit_id]);
            $deleted['visits'] = $stmt->rowCount();
        } catch (Exception $e) {}
        
        return [
            'deleted' => $deleted,
            'restored_meds' => $restored_meds,
            'restored_equip' => $restored_equip
        ];
    }
    
    // ============================================================
    // HELPER: COMPLETE VISIT
    // ============================================================
    function completeVisit($db, $visit_id, $patient_id, $branch_id, $user_id) {
        $stmt = $db->prepare("SELECT id, bill_number, total_amount, paid_amount FROM bills WHERE visit_id = ?");
        $stmt->execute([$visit_id]);
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($bills as $bill) {
            $total = (float)$bill['total_amount'];
            $paid = (float)$bill['paid_amount'];
            $remaining = $total - $paid;
            
            $stmt = $db->prepare("UPDATE bills SET status = 'paid', paid_amount = ?, balance = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$total, $bill['id']]);
            
            if ($remaining > 0) {
                $receipt = 'RCPT-COMPLETE-' . date('Ymd') . '-' . str_pad($bill['id'], 5, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("INSERT INTO payments (receipt_number, bill_id, patient_id, amount, payment_method, notes, received_by, branch_id, received_at, updated_at) VALUES (?, ?, ?, ?, 'cash', 'Auto-payment created by COMPLETE VISIT', ?, ?, NOW(), NOW())");
                $stmt->execute([$receipt, $bill['id'], $patient_id, $remaining, $user_id, $branch_id]);
            }
        }
        
        $stmt = $db->prepare("UPDATE bill_items bi INNER JOIN bills b ON bi.bill_id = b.id SET bi.status = 'paid', bi.updated_at = NOW() WHERE b.visit_id = ?");
        $stmt->execute([$visit_id]);
        
        $stmt = $db->prepare("UPDATE lab_tests SET status = 'completed', completed_at = NOW(), updated_at = NOW() WHERE visit_id = ? AND status NOT IN ('completed', 'cancelled')");
        $stmt->execute([$visit_id]);
        
        try {
            $stmt = $db->prepare("UPDATE prescriptions SET status = 'dispensed', dispensed_at = NOW(), updated_at = NOW() WHERE visit_id = ? AND status NOT IN ('dispensed', 'cancelled')");
            $stmt->execute([$visit_id]);
            
            $stmt = $db->prepare("UPDATE prescription_items pi INNER JOIN prescriptions p ON pi.prescription_id = p.id SET pi.confirmed_by = ?, pi.confirmed_at = NOW(), pi.dispensed_by = ?, pi.dispensed_at = NOW(), pi.updated_at = NOW() WHERE p.visit_id = ? AND (pi.confirmed_at IS NULL OR pi.dispensed_at IS NULL)");
            $stmt->execute([$user_id, $user_id, $visit_id]);
        } catch (Exception $e) {}
        
        $stmt = $db->prepare("UPDATE visits SET status = 'completed', is_completed = 1, completed_at = NOW(), payment_status = 'paid', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$visit_id]);
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM visits WHERE patient_id = ? AND branch_id = ? AND status IN ('new', 'pending', 'assigned', 'with_doctor', 'lab_test', 'waiting', 'prescribed') AND doctor_id IS NOT NULL");
        $stmt->execute([$patient_id, $branch_id]);
        if ((int)$stmt->fetchColumn() === 0) {
            $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL WHERE id = ?");
            $stmt->execute([$patient_id]);
        }
        
        return true;
    }
    
    // ============================================================
    // HELPER: DELETE VISIT (KWA DELETE ACTION)
    // ============================================================
    function deleteVisit($db, $visit_id, $patient_id, $branch_id) {
        $result = deleteOldVisitData($db, $visit_id);
        
        // Update patient.assigned_doctor_id
        $stmt = $db->prepare("SELECT COUNT(*) FROM visits WHERE patient_id = ? AND branch_id = ? AND status IN ('new', 'pending', 'assigned', 'with_doctor', 'lab_test', 'waiting', 'prescribed') AND doctor_id IS NOT NULL");
        $stmt->execute([$patient_id, $branch_id]);
        if ((int)$stmt->fetchColumn() === 0) {
            $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL WHERE id = ?");
            $stmt->execute([$patient_id]);
        }
        
        return [
            'restored_meds' => $result['restored_meds'],
            'restored_equip' => $result['restored_equip']
        ];
    }
    
    // ============================================================
    // HANDLE AJAX
    // ============================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        // GET FILTERED LIST
        if ($action === 'get_filtered_list') {
            ob_end_clean();
            header('Content-Type: application/json');
            $status = $_POST['status'] ?? 'assigned';
            $filtered = [];
            
            if ($status === 'assigned') $filtered = $assigned_patients;
            elseif ($status === 'lab_test') $filtered = $lab_only_patients;
            elseif ($status === 'prescribed') $filtered = $prescribed_patients;
            elseif ($status === 'waiting') $filtered = $waiting_patients;
            elseif ($status === 'complete') $filtered = $complete_patients;
            
            if (empty($filtered)) {
                $icons = ['assigned' => 'fa-user-check', 'lab_test' => 'fa-flask', 'prescribed' => 'fa-prescription', 'waiting' => 'fa-clock', 'complete' => 'fa-check-double'];
                $msgs = ['assigned' => 'No patients assigned', 'lab_test' => 'No lab test pending', 'prescribed' => 'No prescribed', 'waiting' => 'No waiting', 'complete' => 'No completed visits'];
                echo json_encode(['success' => true, 'html' => '<div class="empty-list-state"><i class="fas ' . ($icons[$status] ?? 'fa-inbox') . '"></i><p>' . ($msgs[$status] ?? 'No patients') . '</p></div>', 'count' => 0]);
                exit;
            }
            
            $html = '<div class="patient-list-table-wrap"><table class="patient-list-table">';
            $html .= '<thead><tr>';
            $html .= '<th>Patient / Service</th>';
            $html .= '<th>Patient ID</th>';
            $html .= '<th>Doctor</th>';
            if ($show_assigned_by) $html .= '<th>👤 Assigned By</th>';
            $html .= '<th>Status</th>';
            $html .= '<th style="text-align:center;">Actions</th>';
            $html .= '</tr></thead><tbody>';
            
            foreach ($filtered as $patient) {
                $assigned_days = 0;
                if (!empty($patient['visit_created_at'])) {
                    $assigned_days = (int)floor((time() - strtotime($patient['visit_created_at'])) / 86400);
                }
                $days_text = $assigned_days > 0 ? '<span class="days-badge">' . $assigned_days . 'd</span>' : '<span class="days-badge new">New</span>';
                
                $visit_number_display = !empty($patient['visit_number']) 
                    ? '<span style="font-size:0.65rem;color:var(--text-secondary);font-family:monospace;display:block;margin-top:2px;">' . htmlspecialchars($patient['visit_number']) . '</span>' 
                    : '';
                
                $doctor_html = !empty($patient['assigned_doctor_name'])
                    ? '<div class="doctor-pill"><i class="fas fa-user-md"></i><span>Dr. ' . htmlspecialchars($patient['assigned_doctor_name']) . '</span><span>' . ($patient['assigned_doctor_online'] == 1 ? '🟢' : '⚪') . '</span></div>'
                    : '<span class="no-doctor-tag"><i class="fas fa-minus-circle"></i> No doctor</span>';
                
                $assigned_by_html = '';
                if ($show_assigned_by) {
                    if (!empty($patient['assigned_by_name'])) {
                        $role_icon = 'fa-user';
                        $role_color = '#2563EB';
                        $role_bg = '#EFF6FF';
                        $role = strtolower($patient['assigned_by_role'] ?? '');
                        if ($role === 'reception') { $role_icon = 'fa-user-tie'; $role_color = '#7C3AED'; $role_bg = '#EDE9FE'; }
                        elseif ($role === 'admin') { $role_icon = 'fa-user-shield'; $role_color = '#D97706'; $role_bg = '#FEF3C7'; }
                        
                        $assigned_date = !empty($patient['assigned_at']) ? date('M d, H:i', strtotime($patient['assigned_at'])) : '';
                        
                        $assigned_by_html = '<div class="assigned-by-cell">';
                        $assigned_by_html .= '<span class="role-badge" style="background:' . $role_bg . ';color:' . $role_color . ';">';
                        $assigned_by_html .= '<i class="fas ' . $role_icon . '"></i> ' . htmlspecialchars($patient['assigned_by_name']);
                        $assigned_by_html .= '</span>';
                        if ($assigned_date) $assigned_by_html .= '<span class="assigned-date">' . $assigned_date . '</span>';
                        $assigned_by_html .= '</div>';
                    } else {
                        $assigned_by_html = '<span class="empty-cell">—</span>';
                    }
                }
                
                $status_badge = '';
                if ($status === 'assigned') $status_badge = '<span class="status-pill assigned"><i class="fas fa-check-circle"></i> Assigned</span>';
                elseif ($status === 'lab_test') $status_badge = '<span class="status-pill lab_test"><i class="fas fa-flask"></i> Lab Test</span>';
                elseif ($status === 'prescribed') $status_badge = '<span class="status-pill prescribed"><i class="fas fa-prescription"></i> Prescribed</span>';
                elseif ($status === 'waiting') $status_badge = '<span class="status-pill waiting"><i class="fas fa-clock"></i> Waiting</span>';
                elseif ($status === 'complete') $status_badge = '<span class="status-pill complete"><i class="fas fa-check-double"></i> Complete</span>';
                
                $row_id = 'visit-row-' . ($patient['visit_id'] ?? 'p' . $patient['id']);
                $visit_id_js = $patient['visit_id'] ?? 0;
                $patient_id_js = $patient['id'];
                
                $patient_name_js = htmlspecialchars(addslashes($patient['full_name']));
                $visit_number_js = htmlspecialchars(addslashes($patient['visit_number'] ?? 'N/A'));
                
                $html .= '<tr id="' . $row_id . '">';
                $html .= '<td>';
                $html .= '<div class="patient-name-cell"><i class="fas fa-user-circle"></i> <strong>' . htmlspecialchars($patient['full_name']) . '</strong> ' . $days_text . '</div>';
                $html .= $visit_number_display;
                $html .= '<div class="patient-service-cell"><i class="fas fa-stethoscope"></i> ' . htmlspecialchars($patient['visit_type'] ?? 'Consultation') . '</div>';
                $html .= '</td>';
                $html .= '<td><span class="patient-id-pill">' . htmlspecialchars($patient['patient_id'] ?? 'N/A') . '</span></td>';
                $html .= '<td>' . $doctor_html . '</td>';
                if ($show_assigned_by) $html .= '<td>' . $assigned_by_html . '</td>';
                $html .= '<td>' . $status_badge . '</td>';
                
                $html .= '<td class="actions-cell"><div class="action-group">';
                
                if ($status === 'assigned') {
                    $html .= '<button onclick="openModal(\'reassign\', ' . $patient_id_js . ', ' . $visit_id_js . ', \'' . $patient_name_js . '\', \'' . $visit_number_js . '\')" class="btn-action-mini reassign" title="Reassign"><i class="fas fa-user-minus"></i> <span class="btn-text">Reassign</span></button>';
                    $html .= '<button onclick="changeDoctor(' . $patient_id_js . ')" class="btn-action-mini change" title="Change Doctor"><i class="fas fa-sync-alt"></i> <span class="btn-text">Change</span></button>';
                }
                elseif ($status === 'lab_test') {
                    $html .= '<button onclick="openModal(\'delete\', ' . $patient_id_js . ', ' . $visit_id_js . ', \'' . $patient_name_js . '\', \'' . $visit_number_js . '\')" class="btn-action-mini delete" title="Delete"><i class="fas fa-trash-alt"></i> <span class="btn-text">Delete</span></button>';
                }
                elseif ($status === 'prescribed' || $status === 'waiting') {
                    $html .= '<button onclick="openModal(\'delete\', ' . $patient_id_js . ', ' . $visit_id_js . ', \'' . $patient_name_js . '\', \'' . $visit_number_js . '\')" class="btn-action-mini delete" title="Delete"><i class="fas fa-trash-alt"></i> <span class="btn-text">Delete</span></button>';
                    $html .= '<button onclick="openModal(\'complete\', ' . $patient_id_js . ', ' . $visit_id_js . ', \'' . $patient_name_js . '\', \'' . $visit_number_js . '\')" class="btn-action-mini complete" title="Complete"><i class="fas fa-check-double"></i> <span class="btn-text">Complete</span></button>';
                }
                elseif ($status === 'complete') {
                    $html .= '<button onclick="openModal(\'delete\', ' . $patient_id_js . ', ' . $visit_id_js . ', \'' . $patient_name_js . '\', \'' . $visit_number_js . '\')" class="btn-action-mini delete" title="Delete permanently"><i class="fas fa-trash-alt"></i> <span class="btn-text">Delete</span></button>';
                }
                
                $html .= '</div></td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table></div>';
            echo json_encode(['success' => true, 'html' => $html, 'count' => count($filtered)]);
            exit;
        }
        
        // ============================================================
        // ✅ V26: REASSIGN - Futa visit + ALL data
        // ============================================================
        if ($action === 'reassign_doctor') {
            ob_end_clean();
            header('Content-Type: application/json');
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            $visit_id = (int)($_POST['visit_id'] ?? 0);
            
            if ($patient_id <= 0 || $visit_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid IDs']);
                exit;
            }
            
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("SELECT id, visit_number, doctor_id, status FROM visits WHERE id = ? AND patient_id = ? LIMIT 1");
                $stmt->execute([$visit_id, $patient_id]);
                $visit = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$visit) throw new Exception('Visit not found');
                
                $visit_id_target = (int)$visit['id'];
                $visit_number_target = $visit['visit_number'];
                
                // V26: Tumia helper function
                $result = deleteOldVisitData($db, $visit_id_target);
                $deleted_counts = $result['deleted'];
                
                if ($deleted_counts['visits'] === 0) {
                    throw new Exception('Failed to delete visit');
                }
                
                // Update patient.assigned_doctor_id
                $stmt = $db->prepare("
                    SELECT COUNT(*) FROM visits 
                    WHERE patient_id = ? AND branch_id = ?
                    AND status IN ('assigned', 'with_doctor', 'waiting')
                    AND doctor_id IS NOT NULL
                ");
                $stmt->execute([$patient_id, $selected_branch_id]);
                $remaining_doctor_visits = (int)$stmt->fetchColumn();
                
                if ($remaining_doctor_visits === 0) {
                    $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL WHERE id = ?");
                    $stmt->execute([$patient_id]);
                }
                
                $db->commit();
                
                // Ujumbe
                $msg_parts = [];
                $msg_parts[] = "Visit #$visit_number_target deleted";
                if ($deleted_counts['bills'] > 0) $msg_parts[] = $deleted_counts['bills'] . " bill(s)";
                if ($deleted_counts['bill_items'] > 0) $msg_parts[] = $deleted_counts['bill_items'] . " item(s)";
                if ($deleted_counts['payments'] > 0) $msg_parts[] = $deleted_counts['payments'] . " payment(s)";
                if ($deleted_counts['prescriptions'] > 0) $msg_parts[] = $deleted_counts['prescriptions'] . " prescription(s)";
                if ($deleted_counts['lab_tests'] > 0) $msg_parts[] = $deleted_counts['lab_tests'] . " lab test(s)";
                if ($deleted_counts['vital_signs'] > 0) $msg_parts[] = $deleted_counts['vital_signs'] . " vital sign(s)";
                if ($deleted_counts['procedures'] > 0) $msg_parts[] = $deleted_counts['procedures'] . " procedure(s)";
                if ($result['restored_meds'] > 0) $msg_parts[] = $result['restored_meds'] . " med-stock restored";
                if ($result['restored_equip'] > 0) $msg_parts[] = $result['restored_equip'] . " equip-stock restored";
                
                $success_msg = 'Reassign OK! Patient hana doctor sasa. Deleted: ' . implode(', ', $msg_parts);
                
                echo json_encode([
                    'success' => true, 
                    'message' => $success_msg,
                    'reload' => true,
                    'deleted' => $deleted_counts
                ]);
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
        }
        
        // COMPLETE VISIT
        if ($action === 'complete_visit') {
            ob_end_clean();
            header('Content-Type: application/json');
            $visit_id = (int)($_POST['visit_id'] ?? 0);
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            
            if ($visit_id <= 0 || $patient_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid IDs']);
                exit;
            }
            
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("SELECT id FROM visits WHERE id = ? AND patient_id = ? LIMIT 1");
                $stmt->execute([$visit_id, $patient_id]);
                if (!$stmt->fetch()) throw new Exception('Visit not found');
                
                completeVisit($db, $visit_id, $patient_id, $selected_branch_id, $user_id);
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Visit COMPLETED! Bills PAID, Prescriptions DISPENSED, Lab tests COMPLETED']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        }
        
        // DELETE VISIT
        if ($action === 'delete_visit') {
            ob_end_clean();
            header('Content-Type: application/json');
            $visit_id = (int)($_POST['visit_id'] ?? 0);
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            
            if ($visit_id <= 0 || $patient_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid IDs']);
                exit;
            }
            
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("SELECT id, visit_number FROM visits WHERE id = ? AND patient_id = ? LIMIT 1");
                $stmt->execute([$visit_id, $patient_id]);
                if (!$stmt->fetch()) throw new Exception('Visit not found');
                
                $result = deleteVisit($db, $visit_id, $patient_id, $selected_branch_id);
                
                $db->commit();
                
                $msg = 'Visit DELETED!';
                if ($result['restored_meds'] > 0) $msg .= ' ' . $result['restored_meds'] . ' medication stock restored.';
                if ($result['restored_equip'] > 0) $msg .= ' ' . $result['restored_equip'] . ' equipment stock restored.';
                
                echo json_encode(['success' => true, 'message' => $msg]);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        }
        
        // GET PATIENT DETAILS
        if ($action === 'get_patient_details') {
            ob_end_clean();
            header('Content-Type: application/json');
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            
            if ($patient_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
                exit;
            }
            
            try {
                $stmt = $db->prepare("
                    SELECT p.*, 
                        v.id as visit_id, v.status as visit_status, v.visit_type, v.visit_number,
                        u.full_name as assigned_doctor_name, u.is_online as assigned_doctor_online,
                        DATEDIFF(NOW(), p.created_at) as patient_days 
                    FROM patients p 
                    LEFT JOIN visits v ON p.id = v.patient_id AND v.status NOT IN ('completed', 'cancelled')
                    LEFT JOIN users u ON v.doctor_id = u.id 
                    WHERE p.id = ?
                    ORDER BY v.created_at DESC LIMIT 1
                ");
                $stmt->execute([$patient_id]);
                $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($patient) {
                    echo json_encode(['success' => true, 'patient' => $patient, 'assigned_doctor' => $patient['assigned_doctor_name'] ?? null, 'patient_days' => $patient['patient_days'] ?? 0]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Patient not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        }
        
        // ============================================================
        // ✅ V26: CHANGE DOCTOR - Inafuta visit ya zamani + bill yake
        // Kisha inaunda visit mpya + bill mpya
        // ============================================================
        if ($action === 'change_doctor') {
            ob_end_clean();
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
                
                // ============================================================
                // ✅ V26: FUTA VISITS ZOTE ZA ZAMANI + DATA YAKE
                // ============================================================
                $deleted_old = [
                    'payments' => 0, 'bill_items' => 0, 'bills' => 0,
                    'prescription_items' => 0, 'prescriptions' => 0,
                    'lab_tests' => 0, 'vital_signs' => 0, 'procedures' => 0,
                    'visits' => 0
                ];
                $total_restored_meds = 0;
                $total_restored_equip = 0;
                
                // Pata visits zote za zamani za patient huyu ambazo hazijacomplete
                $stmt = $db->prepare("
                    SELECT id, visit_number, status, doctor_id 
                    FROM visits 
                    WHERE patient_id = ? 
                      AND branch_id = ?
                      AND status NOT IN ('completed', 'cancelled')
                    ORDER BY id DESC
                ");
                $stmt->execute([$patient_id, $selected_branch_id]);
                $old_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($old_visits as $old_visit) {
                    $old_visit_id = (int)$old_visit['id'];
                    $result = deleteOldVisitData($db, $old_visit_id);
                    
                    foreach ($result['deleted'] as $key => $count) {
                        $deleted_old[$key] += $count;
                    }
                    $total_restored_meds += $result['restored_meds'];
                    $total_restored_equip += $result['restored_equip'];
                }
                
                // Weka assigned_doctor_id = NULL
                $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL WHERE id = ?");
                $stmt->execute([$patient_id]);
                
                // ============================================================
                // Unda VISIT MPYA
                // ============================================================
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
                if ($doctor_id > 0) {
                    $stmt = $db->prepare("SELECT full_name FROM users WHERE id = ? AND status = 'active'");
                    $stmt->execute([$doctor_id]);
                    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($doctor) $doctor_name = $doctor['full_name'];
                }
                
                $visit_type_to_store = $is_lab_only ? 'Lab Tests Only' : $service_name;
                $service_id_to_store = $is_lab_only ? null : ($service_id > 0 ? $service_id : null);
                $doctor_id_to_store = $is_lab_only ? null : ($doctor_id > 0 ? $doctor_id : null);
                $visit_status = ($is_lab_only && !empty($lab_test_ids)) ? 'lab_test' : ($is_lab_only ? 'pending' : 'assigned');
                
                $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                $stmt = $db->prepare("
                    INSERT INTO visits 
                        (visit_number, patient_id, doctor_id, branch_id, visit_type, service_id, 
                         status, symptoms, notes, created_at, updated_at, consultation_fee, 
                         receptionist_id, assigned_by_id, assigned_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $visit_number, $patient_id, $doctor_id_to_store, $selected_branch_id, 
                    $visit_type_to_store, $service_id_to_store, $visit_status, 
                    $symptoms, $notes, $consultation_fee, $user_id, $user_id
                ]);
                $visit_id = $db->lastInsertId();
                
                if ($doctor_id > 0 && !$is_lab_only) {
                    $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = ? WHERE id = ?");
                    $stmt->execute([$doctor_id, $patient_id]);
                }
                
                // Unda consultation bill
                if (!$is_lab_only && $consultation_fee > 0 && $doctor_id > 0) {
                    $bill_number = 'BILL-CONS-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999);
                    
                    $stmt = $db->prepare("INSERT INTO bills (bill_number, patient_id, visit_id, branch_id, created_by, subtotal, total_amount, balance, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
                    $stmt->execute([$bill_number, $patient_id, $visit_id, $selected_branch_id, $user_id, $consultation_fee, $consultation_fee, $consultation_fee]);
                    $bill_id = $db->lastInsertId();
                    
                    $stmt = $db->prepare("INSERT INTO bill_items (bill_id, patient_id, branch_id, item_type, item_name, quantity, unit_price, total_price, status, created_at) VALUES (?, ?, ?, 'consultation', ?, 1, ?, ?, 'pending', NOW())");
                    $stmt->execute([$bill_id, $patient_id, $selected_branch_id, $service_name, $consultation_fee, $consultation_fee]);
                }
                
                // Unda lab tests + lab bill
                $lab_created = 0;
                $total_lab_fee = 0;
                if (!empty($lab_test_ids)) {
                    $test_ids_imploded = implode(',', array_map('intval', $lab_test_ids));
                    $stmt = $db->prepare("SELECT id, test_name, price FROM lab_tests_catalog WHERE id IN ($test_ids_imploded)");
                    $stmt->execute();
                    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($tests as $test) {
                        $stmt = $db->prepare("INSERT INTO lab_tests (visit_id, patient_id, doctor_id, test_id, test_name, test_price, status, branch_id, requested_by_id, requested_at, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())");
                        $stmt->execute([$visit_id, $patient_id, $is_lab_only ? null : ($doctor_id > 0 ? $doctor_id : null), $test['id'], $test['test_name'], $test['price'], $selected_branch_id, $user_id]);
                        $total_lab_fee += (float)$test['price'];
                        $lab_created++;
                    }
                    
                    if ($total_lab_fee > 0) {
                        $lab_bill_number = 'BILL-LAB-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999);
                        $stmt = $db->prepare("INSERT INTO bills (bill_number, patient_id, visit_id, branch_id, created_by, subtotal, total_amount, balance, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
                        $stmt->execute([$lab_bill_number, $patient_id, $visit_id, $selected_branch_id, $user_id, $total_lab_fee, $total_lab_fee, $total_lab_fee]);
                        $bill_id = $db->lastInsertId();
                        
                        foreach ($tests as $test) {
                            $stmt = $db->prepare("INSERT INTO bill_items (bill_id, patient_id, branch_id, item_type, item_id, item_name, quantity, unit_price, total_price, status, created_at) VALUES (?, ?, ?, 'lab_test', ?, ?, 1, ?, ?, 'pending', NOW())");
                            $stmt->execute([$bill_id, $patient_id, $selected_branch_id, $test['id'], $test['test_name'], $test['price'], $test['price']]);
                        }
                    }
                }
                
                $db->commit();
                
                // ============================================================
                // Ujumbe
                // ============================================================
                $msg = "";
                if ($doctor_id > 0 && !$is_lab_only) {
                    $msg = "Doctor <strong>$doctor_name</strong> assigned - $service_name";
                } else if ($is_lab_only && $lab_created > 0) {
                    $msg = "$lab_created lab test(s) requested";
                }
                $msg .= " | New Visit: $visit_number";
                
                // Ujumbe wa kile kilichofutwa
                if ($deleted_old['visits'] > 0) {
                    $msg .= ' | 🗑️ Deleted old: ' . $deleted_old['visits'] . ' visit(s)';
                    if ($deleted_old['bills'] > 0) $msg .= ', ' . $deleted_old['bills'] . ' bill(s)';
                    if ($deleted_old['bill_items'] > 0) $msg .= ', ' . $deleted_old['bill_items'] . ' item(s)';
                }
                if ($total_restored_meds > 0) $msg .= ' | ' . $total_restored_meds . ' med-stock restored';
                if ($total_restored_equip > 0) $msg .= ' | ' . $total_restored_equip . ' equip-stock restored';
                
                $response['success'] = true;
                $response['message'] = $msg;
                $response['visit_number'] = $visit_number;
                $response['visit_id'] = $visit_id;
                $response['deleted_old'] = $deleted_old;
                
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $response['message'] = '❌ Error: ' . $e->getMessage();
            }
            
            echo json_encode($response);
            exit;
        }
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
}

$common_symptoms = ['Fever', 'Headache', 'Cough', 'Sore Throat', 'Body Pain', 'Fatigue', 'Nausea', 'Vomiting', 'Diarrhea', 'Chest Pain', 'Shortness of Breath', 'Abdominal Pain', 'Dizziness', 'Rash', 'Swelling'];

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Doctor V26 - Braick Admin</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary:#0B5ED7; --primary-dark:#0A4CA8; --primary-light:#6EA8FE; --primary-bg:#E8F0FE;
            --success:#059669; --success-dark:#047857; --success-bg:#D1FAE5;
            --danger:#DC2626; --danger-dark:#B91C1C; --danger-bg:#FEE2E2;
            --warning:#D97706; --warning-bg:#FEF3C7; --orange:#EA580C; --orange-bg:#FFEDD5;
            --purple:#7C3AED; --purple-dark:#5B21B6; --purple-bg:#EDE9FE;
            --gray-100:#F1F5F9; --gray-200:#E2E8F0; --gray-300:#CBD5E1;
            --gray-400:#94A3B8; --gray-500:#64748B; --gray-600:#475569;
            --shadow-sm:0 1px 2px rgba(0,0,0,0.05);
            --shadow-md:0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg:0 10px 25px rgba(0,0,0,0.15);
            --bg-body:#F0F4F8; --bg-card:#FFFFFF;
            --text-primary:#1E293B; --text-secondary:#64748B;
            --border-color:#E2E8F0; --radius:12px; --radius-lg:18px;
        }
        [data-theme="dark"] {
            --bg-body:#0F172A; --bg-card:#1E293B; --text-primary:#F1F5F9; --text-secondary:#94A3B8;
            --border-color:#334155; --primary:#3B82F6; --primary-bg:#1E3A5F;
            --purple-bg:#2D1B5F; --success-bg:#1A3A2A; --danger-bg:#3A1A1A;
            --warning-bg:#3D2E0A; --orange-bg:#3D1F0A;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter','Segoe UI',sans-serif; background:var(--bg-body); color:var(--text-primary); }
        ::-webkit-scrollbar { width:6px; height:6px; }
        ::-webkit-scrollbar-track { background:var(--bg-body); }
        ::-webkit-scrollbar-thumb { background:var(--primary); border-radius:10px; }
        
        .main-content { margin-left:270px; margin-top:68px; padding:24px 28px; min-height:calc(100vh - 68px); }
        
        .page-header {
            background:linear-gradient(135deg,#2563EB,#1D4ED8);
            border-radius:var(--radius-lg); padding:22px 30px; margin-bottom:20px;
            display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:12px;
            box-shadow:0 8px 32px rgba(37,99,235,0.25); position:relative; overflow:hidden;
        }
        .page-header::before {
            content:''; position:absolute; top:-50%; right:-20%; width:400px; height:400px;
            background:radial-gradient(circle,rgba(255,255,255,0.08) 0%,transparent 70%); border-radius:50%;
        }
        .page-header .page-title {
            color:white; font-size:1.5rem; font-weight:700;
            display:flex; align-items:center; gap:10px; flex-wrap:wrap; position:relative; z-index:1;
        }
        .page-header .page-subtitle {
            color:rgba(255,255,255,0.85); font-size:0.85rem;
            display:flex; align-items:center; gap:6px; flex-wrap:wrap;
            position:relative; z-index:1; margin-top:4px;
        }
        .page-header .role-badge-display {
            background:rgba(255,255,255,0.2); color:white; padding:4px 14px;
            border-radius:20px; font-size:0.68rem; font-weight:700; text-transform:uppercase;
        }
        .header-badge {
            background:rgba(255,255,255,0.12); color:white; padding:4px 14px; border-radius:20px;
            font-size:0.68rem; font-weight:600; display:inline-flex; align-items:center; gap:4px;
            border:1px solid rgba(255,255,255,0.1);
        }
        .btn-outline-light {
            background:rgba(255,255,255,0.12); color:white; border:1px solid rgba(255,255,255,0.2);
            padding:8px 18px; border-radius:var(--radius); font-weight:600; font-size:0.82rem;
            text-decoration:none; display:inline-flex; align-items:center; gap:6px; cursor:pointer;
            position:relative; z-index:1; transition:all 0.3s;
        }
        .btn-outline-light:hover { background:rgba(255,255,255,0.25); transform:translateY(-2px); }
        
        .status-toggle-group {
            display:flex; gap:10px; flex-wrap:wrap; align-items:center;
            background:var(--bg-card); padding:16px 22px; border-radius:var(--radius-lg);
            border:1px solid var(--border-color); box-shadow:var(--shadow-md);
            max-width:1300px; margin:0 auto 20px;
        }
        .status-toggle-btn {
            display:inline-flex; align-items:center; gap:8px; padding:10px 20px;
            border-radius:30px; font-size:0.82rem; font-weight:700;
            border:2px solid var(--border-color); background:var(--bg-body); color:var(--text-secondary);
            cursor:pointer; font-family:inherit; transition:all 0.3s;
        }
        .status-toggle-btn:hover { border-color:var(--primary); color:var(--primary); transform:translateY(-2px); }
        .status-toggle-btn.active {
            background:linear-gradient(135deg,#2563EB,#1D4ED8); color:white;
            border-color:var(--primary); box-shadow:0 6px 20px rgba(37,99,235,0.35);
        }
        .status-toggle-btn.active[data-status="lab_test"] { background:linear-gradient(135deg,#7C3AED,#5B21B6); border-color:#7C3AED; }
        .status-toggle-btn.active[data-status="prescribed"] { background:linear-gradient(135deg,#059669,#047857); border-color:#059669; }
        .status-toggle-btn.active[data-status="waiting"] { background:linear-gradient(135deg,#D97706,#B45309); border-color:#D97706; }
        .status-toggle-btn.active[data-status="complete"] {
            background:linear-gradient(135deg,#059669,#047857); border-color:#059669;
            box-shadow:0 6px 20px rgba(5,150,105,0.35);
        }
        .toggle-count {
            background:rgba(255,255,255,0.25); padding:2px 10px; border-radius:10px;
            font-size:0.7rem; font-weight:800; min-width:26px; text-align:center;
        }
        .status-toggle-btn:not(.active) .toggle-count { background:var(--border-color); color:var(--text-secondary); }
        
        .patient-list-table-wrap { overflow-x:auto; border-radius:14px; border:1px solid var(--border-color); }
        .patient-list-table {
            width:100%; border-collapse:collapse; font-size:0.85rem; background:var(--bg-card);
        }
        .patient-list-table thead tr {
            background:linear-gradient(135deg,#F8FAFC,#EFF6FF);
            border-bottom:2px solid var(--border-color);
        }
        [data-theme="dark"] .patient-list-table thead tr { background:linear-gradient(135deg,#1E293B,#0F172A); }
        .patient-list-table thead th {
            padding:14px 16px; text-align:left; font-weight:700; font-size:0.68rem;
            text-transform:uppercase; color:var(--text-secondary); white-space:nowrap;
        }
        .patient-list-table tbody tr { border-bottom:1px solid var(--border-color); transition:background 0.2s; }
        .patient-list-table tbody tr:hover { background:var(--primary-bg); }
        .patient-list-table tbody td { padding:14px 16px; vertical-align:middle; }
        
        .patient-name-cell { font-weight:700; font-size:0.9rem; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .patient-name-cell i { color:var(--primary); }
        .patient-service-cell { font-size:0.72rem; color:var(--text-secondary); margin-top:4px; display:flex; align-items:center; gap:5px; }
        .patient-id-pill {
            font-family:'JetBrains Mono',monospace; font-size:0.8rem; font-weight:600;
            color:var(--primary); background:var(--primary-bg); padding:4px 12px; border-radius:8px;
            display:inline-block;
        }
        .doctor-pill {
            font-size:0.78rem; display:inline-flex; align-items:center; gap:7px;
            background:var(--primary-bg); padding:6px 14px; border-radius:14px;
            font-weight:600; color:var(--primary); border:1px solid var(--primary-light);
        }
        .no-doctor-tag {
            font-size:0.75rem; color:var(--text-secondary); font-style:italic;
            display:inline-flex; align-items:center; gap:5px; padding:5px 12px;
            background:var(--bg-body); border-radius:10px; border:1px dashed var(--border-color);
        }
        .assigned-by-cell { display:flex; flex-direction:column; gap:4px; }
        .role-badge {
            display:inline-flex; align-items:center; gap:6px; padding:5px 12px;
            border-radius:20px; font-size:0.72rem; font-weight:700; width:fit-content; border:1px solid;
        }
        .assigned-date { font-size:0.65rem; color:var(--text-secondary); font-weight:600; }
        
        .status-pill {
            display:inline-flex; align-items:center; gap:5px; padding:5px 12px;
            border-radius:10px; font-size:0.7rem; font-weight:800;
            text-transform:uppercase; white-space:nowrap;
        }
        .status-pill.assigned { background:var(--success-bg); color:var(--success); border:1px solid rgba(5,150,105,0.3); }
        .status-pill.lab_test { background:var(--purple-bg); color:var(--purple); border:1px solid rgba(124,58,237,0.3); }
        .status-pill.prescribed,.status-pill.complete { background:#D1FAE5; color:#059669; border:1px solid rgba(5,150,105,0.3); }
        .status-pill.waiting { background:var(--warning-bg); color:var(--warning); border:1px solid rgba(217,119,6,0.3); }
        
        .days-badge {
            display:inline-block; background:var(--primary); color:white;
            padding:3px 10px; border-radius:10px; font-size:0.65rem; font-weight:800;
        }
        .days-badge.new { background:var(--success); }
        
        .actions-cell { padding:12px 14px !important; text-align:center; white-space:nowrap; }
        .action-group { display:inline-flex; gap:6px; align-items:center; }
        
        .btn-action-mini {
            display:inline-flex; align-items:center; justify-content:center; gap:5px;
            height:32px; padding:0 12px; font-size:0.7rem; font-weight:700;
            border-radius:8px; border:none; cursor:pointer; font-family:inherit;
            transition:all 0.25s;
        }
        .btn-action-mini.reassign { background:linear-gradient(135deg,#DC2626,#B91C1C); color:white; box-shadow:0 2px 8px rgba(220,38,38,0.3); }
        .btn-action-mini.change { background:linear-gradient(135deg,#D97706,#B45309); color:white; box-shadow:0 2px 8px rgba(217,119,6,0.3); }
        .btn-action-mini.delete { background:linear-gradient(135deg,#991B1B,#7F1D1D); color:white; box-shadow:0 2px 8px rgba(153,27,27,0.3); }
        .btn-action-mini.complete { background:linear-gradient(135deg,#059669,#047857); color:white; box-shadow:0 2px 8px rgba(5,150,105,0.3); }
        .btn-action-mini.assign { background:linear-gradient(135deg,#059669,#047857); color:white; box-shadow:0 2px 8px rgba(5,150,105,0.3); }
        .btn-action-mini:hover { transform:translateY(-2px); box-shadow:0 5px 16px rgba(0,0,0,0.2); }
        
        .list-search-wrapper { position:relative; display:flex; align-items:center; flex:1; max-width:340px; }
        .list-search-icon { position:absolute; left:14px; color:var(--text-secondary); font-size:0.82rem; }
        .list-search-input {
            width:100%; padding:9px 36px 9px 38px; border:2px solid var(--border-color);
            border-radius:10px; font-size:0.82rem; outline:none;
            background:var(--bg-card); color:var(--text-primary);
        }
        .list-search-input:focus { border-color:var(--primary); box-shadow:0 0 0 4px rgba(11,94,215,0.12); }
        .list-search-clear {
            position:absolute; right:10px; background:var(--gray-200); border:none;
            color:var(--text-secondary); width:22px; height:22px; border-radius:50%;
            cursor:pointer; display:none; align-items:center; justify-content:center;
            font-size:0.65rem;
        }
        .list-search-clear.visible { display:flex; }
        
        .empty-list-state {
            text-align:center; padding:50px 30px; color:var(--text-secondary);
            background:var(--bg-card); border-radius:14px;
            border:2px dashed var(--border-color); margin:10px;
        }
        .empty-list-state i { font-size:3rem; color:var(--primary); opacity:0.3; display:block; margin-bottom:14px; }
        
        .modern-card {
            background:var(--bg-card); border-radius:var(--radius-lg);
            padding:20px 24px; border:1px solid var(--border-color);
            box-shadow:var(--shadow-md);
        }
        .modern-card .card-header {
            display:flex; justify-content:space-between; align-items:center;
            flex-wrap:wrap; gap:10px; margin-bottom:16px; padding-bottom:14px;
            border-bottom:2px solid var(--border-color);
        }
        .modern-card .card-title {
            font-size:0.95rem; font-weight:700; display:flex; align-items:center; gap:8px;
        }
        .modern-card .card-title i { color:var(--primary); }
        .modern-card .card-badge {
            padding:4px 14px; border-radius:20px; font-size:0.72rem; font-weight:800;
        }
        
        .form-card-modern {
            background:var(--bg-card); border-radius:var(--radius-lg);
            padding:28px 32px; border:1px solid var(--border-color);
            max-width:1300px; margin:0 auto; box-shadow:var(--shadow-md);
        }
        .form-card-modern .form-header {
            display:flex; align-items:center; gap:14px; margin-bottom:24px;
            padding-bottom:16px; border-bottom:2px solid var(--border-color);
        }
        .form-card-modern .form-header .form-icon {
            width:52px; height:52px; background:linear-gradient(135deg,#2563EB,#1D4ED8);
            border-radius:14px; display:flex; align-items:center; justify-content:center;
            color:white; font-size:1.3rem; flex-shrink:0;
        }
        .form-card-modern .form-header .form-title { font-size:1.1rem; font-weight:700; }
        
        .form-grid-6 { display:grid; grid-template-columns:1fr 1fr; gap:22px; }
        .form-grid-left,.form-grid-right { display:flex; flex-direction:column; gap:18px; }
        
        .form-card-item {
            background:var(--bg-body); border-radius:var(--radius); padding:20px 22px;
            border:1px solid var(--border-color); min-height:160px;
            display:flex; flex-direction:column;
        }
        .form-card-item .card-item-title {
            font-size:0.88rem; font-weight:700; display:flex; align-items:center;
            gap:8px; margin-bottom:12px; padding-bottom:10px;
            border-bottom:1px solid var(--border-color); flex-wrap:wrap;
        }
        .form-card-item .card-item-title i { color:var(--primary); }
        .form-card-item .card-item-title .required { color:var(--danger); }
        
        .patient-toggle-btn {
            display:flex; align-items:center; justify-content:space-between; width:100%;
            padding:12px 16px; background:var(--bg-card);
            border:2px solid var(--primary); border-radius:var(--radius);
            cursor:pointer; font-size:0.9rem; font-weight:700;
            color:var(--primary); font-family:inherit;
        }
        .patient-toggle-btn.active { background:linear-gradient(135deg,#2563EB,#1D4ED8); color:white; }
        .patient-toggle-btn .toggle-arrow { transition:transform 0.3s; }
        .patient-toggle-btn.active .toggle-arrow { transform:rotate(180deg); }
        
        .patient-toggle-content { max-height:0; overflow:hidden; transition:max-height 0.4s ease; }
        .patient-toggle-content.open { max-height:700px; margin-top:12px; }
        
        .patient-search-wrapper { position:relative; margin-bottom:10px; }
        .patient-search-wrapper i {
            position:absolute; left:14px; top:50%; transform:translateY(-50%);
            color:var(--text-secondary);
        }
        .patient-search-wrapper input {
            padding:11px 14px 11px 38px; font-size:0.85rem; width:100%;
            border:2px solid var(--border-color); border-radius:var(--radius);
            outline:none; background:var(--bg-card); color:var(--text-primary);
        }
        .patient-search-wrapper input:focus { border-color:var(--primary); }
        
        .patient-list-container {
            max-height:360px; overflow-y:auto; border:2px solid var(--border-color);
            border-radius:12px; background:var(--bg-card);
        }
        .patient-list-item {
            display:flex; align-items:center; gap:12px; padding:12px 16px;
            border-bottom:1px solid var(--border-color); cursor:pointer;
            transition:all 0.25s; font-size:0.85rem; position:relative;
        }
        .patient-list-item:hover { background:var(--primary-bg); }
        .patient-list-item.selected { background:var(--primary-bg); border-left:4px solid var(--primary); }
        .patient-list-item.assigned-highlight {
            background:linear-gradient(90deg,#FFF7ED,#FFEDD5); border-left:4px solid #EA580C;
        }
        [data-theme="dark"] .patient-list-item.assigned-highlight {
            background:linear-gradient(90deg,#3D1F0A,#4A2410);
        }
        .patient-list-item.assigned-highlight .patient-name { color:#9A3412; }
        [data-theme="dark"] .patient-list-item.assigned-highlight .patient-name { color:#FDBA74; }
        
        .assigned-badge {
            display:inline-flex; align-items:center; gap:4px;
            background:linear-gradient(135deg,#EA580C,#C2410C); color:white;
            padding:3px 10px; border-radius:10px; font-size:0.6rem;
            font-weight:800; text-transform:uppercase;
        }
        
        .patient-icon { font-size:1.2rem; flex-shrink:0; }
        .patient-info { flex:1; min-width:0; }
        .patient-name {
            font-weight:700; font-size:0.9rem;
            display:flex; align-items:center; gap:6px; flex-wrap:wrap;
        }
        .patient-meta {
            font-size:0.72rem; color:var(--text-secondary); margin-top:4px;
            display:flex; align-items:center; gap:8px; flex-wrap:wrap;
        }
        .status-badge-dropdown {
            font-size:0.65rem; font-weight:700; padding:3px 12px; border-radius:10px;
        }
        .status-badge-dropdown.assigned,.status-badge-dropdown.prescribed,.status-badge-dropdown.complete { background:#D1FAE5; color:#059669; }
        .status-badge-dropdown.pending { background:#FEF3C7; color:#D97706; }
        .status-badge-dropdown.lab_only { background:#EDE9FE; color:#7C3AED; }
        .status-badge-dropdown.waiting { background:#FEF3C7; color:#D97706; }
        
        .form-control-modern {
            width:100%; padding:11px 14px; border:2px solid var(--border-color);
            border-radius:var(--radius); font-size:0.88rem; outline:none;
            background:var(--bg-card); color:var(--text-primary);
            min-height:44px; font-family:inherit;
        }
        .form-control-modern:focus { border-color:var(--primary); box-shadow:0 0 0 4px rgba(37,99,235,0.08); }
        .form-control-modern.textarea { min-height:80px; resize:vertical; }
        
        .lab-modal-container-modern {
            background:var(--bg-card); border-radius:var(--radius);
            border:2px solid var(--purple); overflow:hidden;
        }
        .lab-modal-header-modern {
            display:flex; justify-content:space-between; align-items:center;
            padding:12px 18px; background:var(--purple-bg);
            border-bottom:2px solid var(--border-color);
        }
        .lab-test-scroll { max-height:320px; overflow-y:auto; }
        .lab-test-item-modern {
            display:flex; align-items:center; gap:12px; padding:10px 18px;
            border-bottom:1px solid var(--border-color); cursor:pointer;
        }
        .lab-test-item-modern:hover { background:var(--primary-bg); }
        .lab-test-checkbox { width:18px; height:18px; accent-color:var(--purple); cursor:pointer; flex-shrink:0; }
        .lab-test-item-modern label { cursor:pointer; flex:1; display:flex; align-items:center; gap:8px; flex-wrap:wrap; font-size:0.88rem; }
        .lab-test-category { font-size:0.6rem; background:var(--gray-200); color:var(--text-secondary); padding:2px 10px; border-radius:10px; }
        .lab-test-price { font-size:0.85rem; color:var(--success); font-weight:700; margin-left:auto; }
        .lab-test-item-modern.checked { background:var(--purple-bg); border-left:4px solid var(--purple); }
        .lab-modal-footer-modern {
            display:flex; justify-content:space-between; align-items:center;
            padding:12px 18px; border-top:2px solid var(--border-color);
            background:var(--bg-body); flex-wrap:wrap; gap:8px;
        }
        .lab-total-price {
            font-size:0.85rem; font-weight:700; color:var(--success);
            padding:5px 14px; background:var(--success-bg); border-radius:20px;
        }
        
        .btn-modern {
            display:inline-flex; align-items:center; gap:8px; padding:11px 26px;
            border-radius:var(--radius); font-weight:700; font-size:0.88rem;
            cursor:pointer; border:none; text-decoration:none;
            min-height:44px; font-family:inherit; transition:all 0.3s;
        }
        .btn-modern-primary {
            background:linear-gradient(135deg,#2563EB,#1D4ED8); color:white;
            box-shadow:0 4px 12px rgba(37,99,235,0.2);
        }
        .btn-modern-primary:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(37,99,235,0.3); color:white; }
        .btn-modern-outline {
            background:transparent; color:var(--text-secondary); border:2px solid var(--border-color);
        }
        .btn-modern-outline:hover { background:var(--bg-body); border-color:var(--primary); color:var(--primary); }
        .btn-modern-sm { padding:6px 14px; font-size:0.75rem; min-height:34px; border-radius:8px; }
        
        .form-actions-modern {
            display:flex; gap:12px; padding-top:20px; margin-top:20px;
            border-top:2px solid var(--border-color); flex-wrap:wrap;
        }
        
        .toast-modern {
            position:fixed; bottom:24px; right:24px; padding:16px 24px;
            border-radius:var(--radius); z-index:9999; max-width:420px;
            transform:translateY(100px); opacity:0; transition:all 0.4s;
            display:flex; align-items:center; gap:12px; color:white;
            box-shadow:var(--shadow-lg); font-size:0.88rem;
        }
        .toast-modern.show { transform:translateY(0); opacity:1; }
        .toast-modern.success { background:var(--success); }
        .toast-modern.error { background:var(--danger); }
        .toast-modern.info { background:var(--primary); }
        
        .modal-overlay {
            position:fixed; top:0; left:0; right:0; bottom:0;
            background:rgba(0,0,0,0.6); backdrop-filter:blur(4px);
            z-index:10000; display:none; align-items:center; justify-content:center;
            padding:20px; opacity:0; transition:opacity 0.3s ease;
        }
        .modal-overlay.show { display:flex; opacity:1; }
        
        .modal-container {
            background:var(--bg-card); border-radius:20px;
            max-width:520px; width:100%; overflow:hidden;
            box-shadow:0 25px 60px rgba(0,0,0,0.4);
            transform:scale(0.9) translateY(20px); transition:transform 0.3s cubic-bezier(0.34,1.56,0.64,1);
        }
        .modal-overlay.show .modal-container { transform:scale(1) translateY(0); }
        
        .modal-header {
            padding:24px 28px 20px; text-align:center;
            position:relative; overflow:hidden;
        }
        .modal-header.reassign { background:linear-gradient(135deg,#FEE2E2,#FECACA); }
        .modal-header.complete { background:linear-gradient(135deg,#D1FAE5,#A7F3D0); }
        .modal-header.delete { background:linear-gradient(135deg,#991B1B,#7F1D1D); }
        
        [data-theme="dark"] .modal-header.reassign { background:linear-gradient(135deg,#3A1A1A,#4A2020); }
        [data-theme="dark"] .modal-header.complete { background:linear-gradient(135deg,#1A3A2A,#1F4A34); }
        
        .modal-icon {
            width:80px; height:80px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            margin:0 auto 16px; font-size:2.2rem; color:white;
            box-shadow:0 8px 24px rgba(0,0,0,0.2);
        }
        .modal-header.reassign .modal-icon { background:linear-gradient(135deg,#DC2626,#B91C1C); }
        .modal-header.complete .modal-icon { background:linear-gradient(135deg,#059669,#047857); }
        .modal-header.delete .modal-icon { background:linear-gradient(135deg,#7F1D1D,#991B1B); }
        
        .modal-title { font-size:1.4rem; font-weight:800; margin-bottom:6px; }
        .modal-header.reassign .modal-title { color:#991B1B; }
        .modal-header.complete .modal-title { color:#065F46; }
        .modal-header.delete .modal-title { color:white; }
        
        [data-theme="dark"] .modal-header.reassign .modal-title { color:#FCA5A5; }
        [data-theme="dark"] .modal-header.complete .modal-title { color:#6EE7B7; }
        
        .modal-subtitle { font-size:0.88rem; font-weight:500; opacity:0.85; }
        .modal-header.reassign .modal-subtitle { color:#7F1D1D; }
        .modal-header.complete .modal-subtitle { color:#065F46; }
        .modal-header.delete .modal-subtitle { color:rgba(255,255,255,0.85); }
        
        [data-theme="dark"] .modal-header.reassign .modal-subtitle { color:#FCA5A5; }
        [data-theme="dark"] .modal-header.complete .modal-subtitle { color:#6EE7B7; }
        
        .modal-body { padding:24px 28px; }
        
        .modal-info-box {
            background:var(--bg-body); border-radius:12px;
            padding:16px 18px; margin-bottom:20px;
            border-left:4px solid var(--primary);
        }
        .modal-info-box.reassign { border-left-color:#DC2626; }
        .modal-info-box.complete { border-left-color:#059669; }
        .modal-info-box.delete { border-left-color:#991B1B; }
        
        .modal-info-title {
            font-size:0.72rem; font-weight:700; text-transform:uppercase;
            color:var(--text-secondary); margin-bottom:8px; letter-spacing:0.05em;
        }
        
        .modal-patient-info {
            font-size:1rem; font-weight:700; color:var(--text-primary);
            margin-bottom:4px;
        }
        .modal-visit-info {
            font-size:0.82rem; color:var(--text-secondary);
            font-family:'JetBrains Mono',monospace;
        }
        
        .modal-warning-list { list-style:none; padding:0; margin:0; }
        .modal-warning-list li {
            display:flex; align-items:flex-start; gap:10px;
            padding:8px 0; font-size:0.85rem; color:var(--text-primary);
            border-bottom:1px solid var(--border-color);
        }
        .modal-warning-list li:last-child { border-bottom:none; }
        .modal-warning-list li i { font-size:0.75rem; margin-top:3px; flex-shrink:0; }
        .modal-warning-list li i.fa-check-circle { color:#059669; }
        .modal-warning-list li i.fa-times-circle { color:#DC2626; }
        .modal-warning-list li i.fa-exclamation-triangle { color:#D97706; }
        .modal-warning-list li i.fa-info-circle { color:#2563EB; }
        
        .modal-warning-box {
            background:linear-gradient(135deg,#FEF3C7,#FDE68A);
            border:2px solid #F59E0B; border-radius:12px;
            padding:14px 16px; margin-top:16px;
            display:flex; align-items:flex-start; gap:10px;
        }
        [data-theme="dark"] .modal-warning-box { background:linear-gradient(135deg,#3D2E0A,#4A3A10); }
        .modal-warning-box i { color:#D97706; font-size:1.1rem; margin-top:2px; }
        .modal-warning-box .warning-text {
            font-size:0.82rem; font-weight:600; color:#92400E;
            line-height:1.5;
        }
        [data-theme="dark"] .modal-warning-box .warning-text { color:#FCD34D; }
        
        .modal-footer {
            padding:18px 28px 24px;
            display:flex; gap:12px; justify-content:flex-end;
            border-top:1px solid var(--border-color);
        }
        .modal-btn {
            padding:12px 24px; border-radius:12px; font-weight:700;
            font-size:0.88rem; cursor:pointer; border:none;
            font-family:inherit; transition:all 0.3s;
            display:inline-flex; align-items:center; gap:8px;
            min-width:120px; justify-content:center;
        }
        .modal-btn-cancel {
            background:var(--bg-body); color:var(--text-secondary);
            border:2px solid var(--border-color);
        }
        .modal-btn-cancel:hover { background:var(--border-color); color:var(--text-primary); }
        
        .modal-btn-confirm { color:white; box-shadow:0 4px 12px rgba(0,0,0,0.15); }
        .modal-btn-confirm.reassign { background:linear-gradient(135deg,#DC2626,#B91C1C); }
        .modal-btn-confirm.complete { background:linear-gradient(135deg,#059669,#047857); }
        .modal-btn-confirm.delete { background:linear-gradient(135deg,#991B1B,#7F1D1D); }
        .modal-btn-confirm:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(0,0,0,0.25); }
        .modal-btn-confirm:disabled { opacity:0.6; cursor:not-allowed; transform:none; }
        
        .footer-modern {
            padding:14px 0; border-top:1px solid var(--border-color);
            margin-top:24px; text-align:center; font-size:0.72rem;
            color:var(--text-secondary);
        }
        .footer-modern .footer-brand { color:var(--primary); font-weight:600; }
        
        .spinner {
            display:inline-block; width:16px; height:16px;
            border:2px solid rgba(255,255,255,0.3); border-top-color:white;
            border-radius:50%; animation:spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        
        .new-visit-badge {
            display:inline-flex; align-items:center; gap:4px;
            background:linear-gradient(135deg,#10B981,#059669); color:white;
            padding:3px 10px; border-radius:10px; font-size:0.6rem; font-weight:800;
        }
        
        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(20px); }
            to { opacity:1; transform:translateY(0); }
        }
        .animate-fade-in-up { animation:fadeInUp 0.5s ease forwards; opacity:0; }
        
        @media (max-width:1024px) {
            .main-content { margin-left:0; padding:16px; }
            .form-grid-6 { grid-template-columns:1fr; }
        }
        @media (max-width:768px) {
            .btn-action-mini { width:34px; height:34px; padding:0; }
            .btn-action-mini .btn-text { display:none; }
            .actions-cell { width:90px; padding:8px 6px !important; }
            .modal-footer { flex-direction:column; }
            .modal-btn { width:100%; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-md"></i>
                Assign / Change Doctor (V26)
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                Change & Reassign → Inafuta visit + bills kabisa
                
                <span class="header-badge"><i class="fas fa-user-check"></i> <span id="assignedCount"><?= $assigned_count ?></span> Assigned</span>
                <span class="header-badge" style="background:rgba(124,58,237,0.2);"><i class="fas fa-flask"></i> <span id="labOnlyCount"><?= $lab_only_count ?></span> Lab</span>
                <span class="header-badge" style="background:rgba(5,150,105,0.2);"><i class="fas fa-prescription"></i> <span id="prescribedCount"><?= $prescribed_count ?></span> Prescribe</span>
                <span class="header-badge" style="background:rgba(217,119,6,0.2);"><i class="fas fa-clock"></i> <span id="waitingCount"><?= $waiting_count ?></span> Waiting</span>
                <span class="header-badge" style="background:rgba(16,185,129,0.35);color:#A7F3D0;"><i class="fas fa-check-double"></i> <span id="completeCount"><?= $complete_count ?></span> Complete</span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light"><i class="fas fa-arrow-left"></i> Back</a>
            <button onclick="location.reload()" class="btn-outline-light"><i class="fas fa-sync-alt"></i> Refresh</button>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="status-toggle-group">
        <span style="font-size:0.82rem;font-weight:700;color:var(--text-secondary);"><i class="fas fa-filter"></i> Filter:</span>
        <button type="button" class="status-toggle-btn active" data-status="assigned" onclick="filterByStatus('assigned')">
            <i class="fas fa-user-check"></i> Assigned <span class="toggle-count" id="toggleAssignedCount"><?= $assigned_count ?></span>
        </button>
        <button type="button" class="status-toggle-btn" data-status="lab_test" onclick="filterByStatus('lab_test')">
            <i class="fas fa-flask"></i> Lab Test <span class="toggle-count" id="toggleLabCount"><?= $lab_only_count ?></span>
        </button>
        <button type="button" class="status-toggle-btn" data-status="prescribed" onclick="filterByStatus('prescribed')">
            <i class="fas fa-prescription"></i> Prescribe <span class="toggle-count" id="togglePrescribedCount"><?= $prescribed_count ?></span>
        </button>
        <button type="button" class="status-toggle-btn" data-status="waiting" onclick="filterByStatus('waiting')">
            <i class="fas fa-clock"></i> Waiting <span class="toggle-count" id="toggleWaitingCount"><?= $waiting_count ?></span>
        </button>
        <button type="button" class="status-toggle-btn" data-status="complete" onclick="filterByStatus('complete')">
            <i class="fas fa-check-double"></i> Complete <span class="toggle-count" id="toggleCompleteCount"><?= $complete_count ?></span>
        </button>
    </div>

    <!-- LIST CARD -->
    <div class="modern-card animate-fade-in-up" style="max-width:1300px;margin:0 auto 20px;" id="patientsListCard">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-user-check" id="listIcon"></i>
                <span id="listTitle">Assigned Patients</span>
                <span class="card-badge" id="listCountBadge" style="background:var(--success-bg);color:var(--success);"><?= $assigned_count ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:10px;flex:1;justify-content:flex-end;flex-wrap:wrap;">
                <div class="list-search-wrapper">
                    <i class="fas fa-search list-search-icon"></i>
                    <input type="text" class="list-search-input" id="listSearchInput" 
                           placeholder="Search..." oninput="performListSearch(this.value)">
                    <button type="button" class="list-search-clear" id="listSearchClear" onclick="clearListSearch()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        <div id="patientsListContainer">
            <div style="text-align:center;padding:30px;">
                <div class="spinner" style="border-color:var(--border-color);border-top-color:var(--primary);"></div>
            </div>
        </div>
    </div>

    <!-- FORM -->
    <div class="form-card-modern animate-fade-in-up">
        <div class="form-header">
            <div class="form-icon"><i class="fas fa-stethoscope"></i></div>
            <div>
                <h3 class="form-title">Assign / Change Doctor or Lab Test</h3>
                <p style="font-size:0.82rem;color:var(--text-secondary);margin-top:2px;">
                    <span style="color:var(--success);font-weight:700;"><i class="fas fa-plus-circle"></i> Kila assign inaunda visit mpya</span>
                    <span style="color:var(--orange);font-weight:700;margin-left:8px;"><i class="fas fa-circle"></i> Orange = Assigned</span>
                </p>
            </div>
        </div>
        
        <form method="POST" id="assignForm">
            <input type="hidden" name="action" value="change_doctor">
            <input type="hidden" name="patient_id" id="selectedPatientInput" value="<?= $selected_patient_id ?>">
            
            <div class="form-grid-6">
                <div class="form-grid-left">
                    <div class="form-card-item">
                        <div class="card-item-title"><i class="fas fa-user"></i> Select Patient <span class="required">*</span></div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            <button type="button" class="patient-toggle-btn" id="patientToggleBtn" onclick="togglePatientList()">
                                <span id="selectedPatientLabel">
                                    <?php if ($selected_patient_data): ?>
                                        ✓ <?= htmlspecialchars($selected_patient_data['full_name']) ?> (<?= htmlspecialchars($selected_patient_data['patient_id'] ?? '') ?>)
                                    <?php else: ?>
                                        Click to Select Patient
                                    <?php endif; ?>
                                </span>
                                <i class="fas fa-chevron-down toggle-arrow"></i>
                            </button>
                            <div class="patient-toggle-content" id="patientToggleContent">
                                <div class="patient-search-wrapper">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="patientSearchFilter" 
                                           placeholder="🔍 Search patient..." oninput="filterPatientList(this.value)">
                                </div>
                                <div class="patient-list-container" id="patientListContainer">
                                    <?php if (!empty($unique_patients_for_dropdown)): ?>
                                        <?php foreach ($unique_patients_for_dropdown as $patient): 
                                            $status_label = 'Complete'; $status_class = 'complete'; $status_icon = '✅'; $is_assigned = false;
                                            if (!empty($patient['assigned_doctor_name'])) { 
                                                $is_assigned = true; $status_label = 'Assigned'; $status_class = 'assigned'; $status_icon = '✅'; 
                                            } elseif (!empty($patient['visit_id'])) {
                                                if ($patient['visit_status'] === 'lab_test') { $status_label = 'Lab Test'; $status_class = 'lab_only'; $status_icon = '🧪'; }
                                                elseif ($patient['visit_status'] === 'waiting') { $status_label = 'Waiting'; $status_class = 'waiting'; $status_icon = '⏳'; }
                                                elseif ($patient['visit_status'] === 'prescribed') { $status_label = 'Prescribed'; $status_class = 'prescribed'; $status_icon = '💊'; }
                                                elseif ($patient['visit_status'] === 'pending' || $patient['visit_status'] === 'new' || empty($patient['visit_status'])) { $status_label = 'Pending'; $status_class = 'pending'; $status_icon = '🟡'; }
                                                elseif (in_array($patient['visit_status'], ['assigned', 'with_doctor'])) { $status_label = 'Assigned'; $status_class = 'assigned'; $status_icon = '✅'; }
                                                elseif (in_array($patient['visit_status'], ['completed', 'cancelled'])) { $status_label = 'Complete'; $status_class = 'complete'; $status_icon = '✅'; }
                                            }
                                            
                                            $doctor_info = '';
                                            if (!empty($patient['assigned_doctor_name'])) {
                                                $online = !empty($patient['assigned_doctor_online']) ? '🟢' : '⚪';
                                                $doctor_info = 'Dr. ' . htmlspecialchars($patient['assigned_doctor_name']) . ' ' . $online;
                                            }
                                            
                                            $is_selected = ($selected_patient_id == $patient['id']) ? 'selected' : '';
                                            $days = (int)($patient['patient_days'] ?? 0);
                                            $days_text = $days > 0 ? $days . 'd' : 'New';
                                            $search_data = strtolower($patient['full_name'] . ' ' . ($patient['patient_id'] ?? '') . ' ' . ($patient['phone'] ?? ''));
                                            $assigned_class = $is_assigned ? 'assigned-highlight' : '';
                                        ?>
                                            <div class="patient-list-item <?= $is_selected ?> <?= $assigned_class ?>" 
                                                 data-patient-id="<?= $patient['id'] ?>"
                                                 data-search="<?= htmlspecialchars($search_data) ?>"
                                                 onclick="selectPatient(<?= $patient['id'] ?>, '<?= htmlspecialchars(addslashes($patient['full_name'])) ?>', '<?= htmlspecialchars($patient['patient_id'] ?? '') ?>')">
                                                <span class="patient-icon"><?= $status_icon ?></span>
                                                <div class="patient-info">
                                                    <div class="patient-name">
                                                        <?= htmlspecialchars($patient['full_name']) ?>
                                                        <span class="days-badge <?= $days > 0 ? '' : 'new' ?>"><?= $days_text ?></span>
                                                        <?php if ($is_assigned): ?>
                                                            <span class="assigned-badge"><i class="fas fa-user-md"></i> ASSIGNED</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="patient-meta">
                                                        <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span>
                                                        <span class="status-badge-dropdown <?= $status_class ?>"><?= $status_label ?></span>
                                                        <?php if ($doctor_info): ?>
                                                            <span>👨‍⚕️ <?= $doctor_info ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div style="padding:20px;text-align:center;color:var(--text-secondary);">
                                            <i class="fas fa-user-slash"></i>
                                            <p>No patients found</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div id="selectedPatientInfo" style="display:<?= $selected_patient_id > 0 && $selected_patient_data ? 'flex' : 'none' ?>;margin-top:8px;padding:12px 16px;background:var(--primary-bg);border-radius:var(--radius);align-items:center;gap:8px;font-size:0.78rem;flex-wrap:wrap;">
                                <?php if ($selected_patient_data): ?>
                                    <i class="fas fa-user-circle" style="color:var(--primary);"></i>
                                    <span style="font-weight:700;"><?= htmlspecialchars($selected_patient_data['full_name'] ?? '') ?></span>
                                    <span>|</span>
                                    <span><?= htmlspecialchars($selected_patient_data['patient_id'] ?? '') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-card-item">
                        <div class="card-item-title"><i class="fas fa-tasks"></i> Select Action <span class="required">*</span></div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            <select name="assignment_type" class="form-control-modern" required id="assignmentTypeSelect" onchange="toggleAssignmentType(this.value)">
                                <option value="doctor">👨‍⚕️ Assign Doctor</option>
                                <option value="lab">🧪 Request Lab Test(s)</option>
                            </select>
                            <p style="font-size:0.72rem;color:var(--text-secondary);margin-top:8px;" id="assignmentTypeHelp">
                                👨‍⚕️ Assign doctor OR request lab tests
                            </p>
                        </div>
                    </div>
                    
                    <div class="form-card-item" id="doctorSelectCard">
                        <div class="card-item-title"><i class="fas fa-user-md"></i> Select Doctor <span class="required" id="doctorRequired">*</span></div>
                        <select name="doctor_id" class="form-control-modern" id="doctorSelect">
                            <option value="">-- Select Doctor --</option>
                            <?php if (!empty($online_doctors)): ?>
                                <optgroup label="🟢 Online (<?= $online_doctors_count ?>)">
                                    <?php foreach ($online_doctors as $doctor): ?>
                                        <option value="<?= $doctor['id'] ?>">🟢 Dr. <?= htmlspecialchars($doctor['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <?php if (!empty($offline_doctors)): ?>
                                <optgroup label="⚪ Offline (<?= $offline_doctors_count ?>)">
                                    <?php foreach ($offline_doctors as $doctor): ?>
                                        <option value="<?= $doctor['id'] ?>">⚪ Dr. <?= htmlspecialchars($doctor['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                        <p style="font-size:0.72rem;color:var(--text-secondary);margin-top:8px;">
                            🟢 <?= $online_doctors_count ?> online | ⚪ <?= $offline_doctors_count ?> offline
                        </p>
                    </div>
                </div>
                
                <div class="form-grid-right">
                    <div class="form-card-item" id="visitTypeSection">
                        <div class="card-item-title">
                            <i class="fas fa-tag"></i> Visit Type <span class="required">*</span>
                            <span style="font-size:0.62rem;font-weight:600;padding:3px 12px;border-radius:10px;background:var(--gray-200);color:var(--text-secondary);" id="visitTypePrice">
                                TSh <?= isset($visit_type_options[$default_service_id]) ? number_format($visit_type_options[$default_service_id]['price'] ?? 0, 0) : '0' ?>
                            </span>
                        </div>
                        <div style="flex:1;">
                            <select name="service_id" class="form-control-modern" id="visitTypeSelect" onchange="updateVisitTypePrice()">
                                <?php if (!empty($visit_type_options)): ?>
                                    <?php foreach ($visit_type_options as $service_id => $option): ?>
                                        <option value="<?= $service_id ?>" data-price="<?= $option['price'] ?? 0 ?>" <?= ($service_id === $default_service_id) ? 'selected' : '' ?>>
                                            <?= $option['icon'] ?? '🏥' ?> <?= htmlspecialchars($option['service_name']) ?> - TSh <?= number_format($option['price'] ?? 0, 0) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>❌ No Visit Type Available</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-card-item">
                        <div class="card-item-title"><i class="fas fa-notes-medical"></i> Symptoms</div>
                        <div style="flex:1;display:flex;flex-direction:column;gap:8px;">
                            <textarea name="symptoms" class="form-control-modern textarea" 
                                      placeholder="Describe patient symptoms..." rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-card-item" id="labSection" style="display:none;">
                        <div class="card-item-title">
                            <i class="fas fa-flask" style="color:var(--purple);"></i> Select Lab Tests
                            <span style="font-size:0.62rem;font-weight:600;padding:3px 12px;border-radius:10px;background:var(--purple-bg);color:var(--purple);" id="labSelectedCount">(0 selected)</span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            <div class="lab-modal-container-modern">
                                <div class="lab-modal-header-modern">
                                    <div style="font-weight:700;font-size:0.88rem;">
                                        <i class="fas fa-flask" style="color:var(--purple);"></i>
                                        Available Tests (<?= count($lab_tests_catalog) ?>)
                                    </div>
                                    <button type="button" onclick="closeLabTests()" style="background:none;border:none;color:var(--text-secondary);cursor:pointer;font-size:0.9rem;padding:4px 8px;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="lab-test-scroll">
                                    <?php if (!empty($lab_tests_catalog)): ?>
                                        <?php foreach ($lab_tests_catalog as $test): ?>
                                            <div class="lab-test-item-modern">
                                                <input type="checkbox" class="lab-test-checkbox" 
                                                       value="<?= $test['id'] ?>" id="lab_<?= $test['id'] ?>" 
                                                       onchange="updateLabSelection()">
                                                <label for="lab_<?= $test['id'] ?>">
                                                    <strong><?= htmlspecialchars($test['test_name']) ?></strong>
                                                    <?php if (!empty($test['category'])): ?>
                                                        <span class="lab-test-category"><?= htmlspecialchars($test['category']) ?></span>
                                                    <?php endif; ?>
                                                </label>
                                                <span class="lab-test-price">TSh <?= number_format($test['price'] ?? 0, 0) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div style="padding:20px;text-align:center;">
                                            <p>No lab tests available</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="lab-modal-footer-modern">
                                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                        <button type="button" class="btn-modern btn-modern-outline btn-modern-sm" onclick="selectAllLabTests()">
                                            <i class="fas fa-check-double"></i> All
                                        </button>
                                        <button type="button" class="btn-modern btn-modern-outline btn-modern-sm" onclick="deselectAllLabTests()">
                                            <i class="fas fa-times"></i> Clear
                                        </button>
                                        <span class="lab-total-price" id="labTotalPrice">Total: TSh 0</span>
                                    </div>
                                    <button type="button" class="btn-modern btn-modern-sm" style="background:var(--danger);color:white;" onclick="closeLabTests()">
                                        Close
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="lab_test_ids" id="selectedLabTestsInput" value="">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions-modern">
                <button type="submit" class="btn-modern btn-modern-primary" id="assignBtn">
                    <i class="fas fa-user-md"></i> Assign / Change Doctor
                    <span class="new-visit-badge"><i class="fas fa-plus-circle"></i> NEW VISIT</span>
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
            Assign Doctor V26
            <span style="margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>
</main>

<!-- MODAL -->
<div class="modal-overlay" id="actionModal">
    <div class="modal-container">
        <div class="modal-header" id="modalHeader">
            <div class="modal-icon" id="modalIcon">
                <i class="fas fa-question"></i>
            </div>
            <div class="modal-title" id="modalTitle">Confirm Action</div>
            <div class="modal-subtitle" id="modalSubtitle">Please review before proceeding</div>
        </div>
        
        <div class="modal-body">
            <div class="modal-info-box" id="modalInfoBox">
                <div class="modal-info-title">Patient Information</div>
                <div class="modal-patient-info" id="modalPatientName">-</div>
                <div class="modal-visit-info" id="modalVisitNumber">-</div>
            </div>
            
            <div class="modal-info-title" style="margin-bottom:8px;">This action will:</div>
            <ul class="modal-warning-list" id="modalWarningList">
                <li><i class="fas fa-info-circle"></i> <span>No details available</span></li>
            </ul>
            
            <div class="modal-warning-box" id="modalWarningBox" style="display:none;">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="warning-text" id="modalWarningText">
                    This action cannot be undone!
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="modal-btn modal-btn-confirm" id="modalConfirmBtn">
                <i class="fas fa-check"></i> <span id="modalConfirmText">Confirm</span>
            </button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast" class="toast-modern" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1rem;"></i>
    <div>
        <p style="font-weight:700;font-size:0.88rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.78rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    var modalContext = null;
    
    function openModal(action, patientId, visitId, patientName, visitNumber) {
        modalContext = { action, patientId, visitId, patientName, visitNumber };
        
        var header = document.getElementById('modalHeader');
        var icon = document.getElementById('modalIcon');
        var title = document.getElementById('modalTitle');
        var subtitle = document.getElementById('modalSubtitle');
        var infoBox = document.getElementById('modalInfoBox');
        var warningList = document.getElementById('modalWarningList');
        var warningBox = document.getElementById('modalWarningBox');
        var warningText = document.getElementById('modalWarningText');
        var confirmBtn = document.getElementById('modalConfirmBtn');
        var confirmText = document.getElementById('modalConfirmText');
        
        header.className = 'modal-header';
        infoBox.className = 'modal-info-box';
        confirmBtn.className = 'modal-btn modal-btn-confirm';
        warningBox.style.display = 'none';
        
        document.getElementById('modalPatientName').textContent = patientName;
        document.getElementById('modalVisitNumber').textContent = visitNumber;
        
        if (action === 'reassign') {
            header.classList.add('reassign');
            infoBox.classList.add('reassign');
            confirmBtn.classList.add('reassign');
            icon.innerHTML = '<i class="fas fa-user-minus"></i>';
            title.textContent = 'Reassign Doctor?';
            subtitle.textContent = 'UNAFUTA KILA KITU cha visit hii';
            confirmText.textContent = 'Reassign';
            
            warningList.innerHTML = `
                <li><i class="fas fa-times-circle"></i> <span>Delete <strong>VISIT</strong> itself</span></li>
                <li><i class="fas fa-times-circle"></i> <span>Delete <strong>ALL BILLS</strong> (hata zilizolipwa!)</span></li>
                <li><i class="fas fa-times-circle"></i> <span>Delete <strong>BILL ITEMS</strong></span></li>
                <li><i class="fas fa-times-circle"></i> <span>Delete <strong>PAYMENTS</strong></span></li>
                <li><i class="fas fa-times-circle"></i> <span>Delete <strong>PRESCRIPTIONS</strong></span></li>
                <li><i class="fas fa-times-circle"></i> <span>Delete <strong>LAB TESTS</strong></span></li>
                <li><i class="fas fa-times-circle"></i> <span>Delete <strong>VITAL SIGNS</strong> & <strong>PROCEDURES</strong></span></li>
                <li><i class="fas fa-info-circle"></i> <span>Patient ataonekana kwenye <strong>dropdown</strong> upange doctor mpya</span></li>
            `;
            
            warningBox.style.display = 'flex';
            warningText.innerHTML = '<strong>⚠️ WARNING:</strong> Hii itafuta kabisa visit na data zote! Huwezi kurudisha.';
            
        } else if (action === 'complete') {
            header.classList.add('complete');
            infoBox.classList.add('complete');
            confirmBtn.classList.add('complete');
            icon.innerHTML = '<i class="fas fa-check-double"></i>';
            title.textContent = 'Complete Visit?';
            subtitle.textContent = 'Mark all as paid and completed';
            confirmText.textContent = 'Complete';
            
            warningList.innerHTML = `
                <li><i class="fas fa-check-circle"></i> <span>Mark <strong>ALL bills</strong> as PAID (balance = 0)</span></li>
                <li><i class="fas fa-check-circle"></i> <span>Create auto-payment records</span></li>
                <li><i class="fas fa-check-circle"></i> <span>Mark <strong>ALL prescriptions</strong> as DISPENSED</span></li>
                <li><i class="fas fa-check-circle"></i> <span>Mark <strong>ALL lab tests</strong> as COMPLETED</span></li>
                <li><i class="fas fa-check-circle"></i> <span>Mark visit as COMPLETED</span></li>
            `;
            
        } else if (action === 'delete') {
            header.classList.add('delete');
            infoBox.classList.add('delete');
            confirmBtn.classList.add('delete');
            icon.innerHTML = '<i class="fas fa-trash-alt"></i>';
            title.textContent = 'Delete Visit?';
            subtitle.textContent = 'Permanently remove all data';
            confirmText.textContent = 'Delete';
            
            warningList.innerHTML = `
                <li><i class="fas fa-check-circle"></i> <span>Restore <strong>MEDICATION</strong> stock</span></li>
                <li><i class="fas fa-check-circle"></i> <span>Restore <strong>EQUIPMENT</strong> stock</span></li>
                <li><i class="fas fa-times-circle"></i> <span>Delete ALL payments, bills, bill items</span></li>
                <li><i class="fas fa-times-circle"></i> <span>Delete ALL prescriptions, lab tests</span></li>
                <li><i class="fas fa-times-circle"></i> <span>Delete vital signs & the visit itself</span></li>
            `;
            
            warningBox.style.display = 'flex';
            warningText.innerHTML = '<strong>⚠️ WARNING:</strong> This action CANNOT be undone! All visit data will be permanently deleted.';
        }
        
        var modal = document.getElementById('actionModal');
        modal.style.display = 'flex';
        setTimeout(function() { modal.classList.add('show'); }, 10);
        
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="fas fa-check"></i> <span id="modalConfirmText">' + confirmText.textContent + '</span>';
    }
    
    function closeModal() {
        var modal = document.getElementById('actionModal');
        modal.classList.remove('show');
        setTimeout(function() { modal.style.display = 'none'; }, 300);
        modalContext = null;
    }
    
    document.getElementById('modalConfirmBtn').addEventListener('click', function() {
        if (!modalContext) return;
        
        var btn = this;
        btn.disabled = true;
        var action = modalContext.action;
        btn.innerHTML = '<span class="spinner"></span> Processing...';
        
        var ajaxAction = '';
        if (action === 'reassign') ajaxAction = 'reassign_doctor';
        else if (action === 'complete') ajaxAction = 'complete_visit';
        else if (action === 'delete') ajaxAction = 'delete_visit';
        
        var fd = new FormData();
        fd.append('action', ajaxAction);
        fd.append('visit_id', modalContext.visitId);
        fd.append('patient_id', modalContext.patientId);
        
        fetch(window.location.href, { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                closeModal();
                if (d.success) {
                    showToast('✅ Success', d.message, 'success');
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    showToast('❌ Error', d.message || 'Failed', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> Confirm';
                }
            })
            .catch(function(e) {
                closeModal();
                showToast('❌ Error', 'Network: ' + e.message, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check"></i> Confirm';
            });
    });
    
    document.getElementById('actionModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var modal = document.getElementById('actionModal');
            if (modal.classList.contains('show')) closeModal();
        }
    });
    
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        document.getElementById('toastTitle').textContent = title;
        document.getElementById('toastMessage').textContent = message;
        toast.className = 'toast-modern ' + type;
        toast.style.display = 'flex';
        setTimeout(function() { toast.classList.add('show'); }, 10);
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 6000);
    }
    
    function togglePatientList() {
        document.getElementById('patientToggleContent').classList.toggle('open');
        document.getElementById('patientToggleBtn').classList.toggle('active');
    }
    
    function selectPatient(patientId, patientName, patientCode) {
        document.getElementById('selectedPatientInput').value = patientId;
        document.getElementById('selectedPatientLabel').innerHTML = '✓ ' + patientName + ' (' + patientCode + ')';
        
        document.querySelectorAll('.patient-list-item').forEach(function(item) {
            item.classList.remove('selected');
            if (item.getAttribute('data-patient-id') == patientId) item.classList.add('selected');
        });
        
        document.getElementById('patientToggleContent').classList.remove('open');
        document.getElementById('patientToggleBtn').classList.remove('active');
        showToast('👤 Selected', patientName, 'success');
    }
    
    function filterPatientList(query) {
        var term = query.toLowerCase().trim();
        document.querySelectorAll('.patient-list-item').forEach(function(item) {
            var searchData = item.getAttribute('data-search') || '';
            item.style.display = (term === '' || searchData.indexOf(term) !== -1) ? 'flex' : 'none';
        });
    }
    
    function toggleAssignmentType(type) {
        var labSection = document.getElementById('labSection');
        var doctorCard = document.getElementById('doctorSelectCard');
        var visitTypeSection = document.getElementById('visitTypeSection');
        var doctorSelect = document.getElementById('doctorSelect');
        var doctorRequired = document.getElementById('doctorRequired');
        var btn = document.getElementById('assignBtn');
        var helpText = document.getElementById('assignmentTypeHelp');
        
        if (type === 'lab') {
            doctorCard.style.display = 'none';
            visitTypeSection.style.display = 'none';
            labSection.style.display = 'block';
            doctorSelect.removeAttribute('required');
            if (doctorRequired) doctorRequired.style.display = 'none';
            helpText.textContent = '🧪 Lab test mode - Doctor not required';
            btn.innerHTML = '<i class="fas fa-flask"></i> Request Lab Tests <span class="new-visit-badge"><i class="fas fa-plus-circle"></i> NEW VISIT</span>';
            btn.style.background = '#7C3AED';
            btn.style.color = 'white';
        } else {
            doctorCard.style.display = 'block';
            visitTypeSection.style.display = 'block';
            labSection.style.display = 'none';
            doctorSelect.setAttribute('required', 'required');
            if (doctorRequired) doctorRequired.style.display = 'inline';
            helpText.textContent = '👨‍⚕️ Assign doctor to patient';
            btn.innerHTML = '<i class="fas fa-user-md"></i> Assign / Change Doctor <span class="new-visit-badge"><i class="fas fa-plus-circle"></i> NEW VISIT</span>';
            btn.style.background = '';
            btn.style.color = '';
        }
    }
    
    function updateLabSelection() {
        var checkboxes = document.querySelectorAll('.lab-test-checkbox');
        var count = 0, total = 0, ids = [];
        
        checkboxes.forEach(function(cb) {
            var item = cb.closest('.lab-test-item-modern');
            if (cb.checked) {
                count++;
                ids.push(cb.value);
                if (item) item.classList.add('checked');
                var priceText = item ? (item.querySelector('.lab-test-price')?.textContent || '') : '';
                var price = parseFloat(priceText.replace(/[^0-9.]/g, ''));
                if (!isNaN(price)) total += price;
            } else {
                if (item) item.classList.remove('checked');
            }
        });
        
        document.getElementById('labSelectedCount').textContent = '(' + count + ' selected)';
        document.getElementById('labTotalPrice').textContent = 'Total: TSh ' + total.toLocaleString();
        document.getElementById('selectedLabTestsInput').value = ids.join(',');
    }
    
    function selectAllLabTests() {
        document.querySelectorAll('.lab-test-checkbox').forEach(function(cb) {
            cb.checked = true;
            cb.closest('.lab-test-item-modern')?.classList.add('checked');
        });
        updateLabSelection();
    }
    
    function deselectAllLabTests() {
        document.querySelectorAll('.lab-test-checkbox').forEach(function(cb) {
            cb.checked = false;
            cb.closest('.lab-test-item-modern')?.classList.remove('checked');
        });
        updateLabSelection();
    }
    
    function closeLabTests() {
        document.getElementById('labSection').style.display = 'none';
        document.getElementById('assignmentTypeSelect').value = 'doctor';
        toggleAssignmentType('doctor');
    }
    
    function updateVisitTypePrice() {
        var select = document.getElementById('visitTypeSelect');
        if (!select) return;
        var opt = select.options[select.selectedIndex];
        if (!opt) return;
        var price = parseFloat(opt.dataset.price || 0);
        document.getElementById('visitTypePrice').textContent = 'TSh ' + price.toLocaleString();
    }
    
    function changeDoctor(patientId) {
        window.location.href = 'assign_doctor.php?patient_id=' + patientId + '&change=1';
    }
    
    var currentStatus = 'assigned';
    
    function filterByStatus(status) {
        document.querySelectorAll('.status-toggle-btn').forEach(function(b) {
            b.classList.remove('active');
            if (b.dataset.status === status) b.classList.add('active');
        });
        currentStatus = status;
        
        document.getElementById('listSearchInput').value = '';
        document.getElementById('listSearchClear').classList.remove('visible');
        
        var titles = {
            'assigned': { title: 'Assigned Patients', icon: 'fa-user-check', color: 'var(--success)', bg: 'var(--success-bg)' },
            'lab_test': { title: 'Lab Test Patients', icon: 'fa-flask', color: 'var(--purple)', bg: 'var(--purple-bg)' },
            'prescribed': { title: 'Prescribed Patients', icon: 'fa-prescription', color: '#059669', bg: '#D1FAE5' },
            'waiting': { title: 'Waiting Patients', icon: 'fa-clock', color: '#D97706', bg: '#FEF3C7' },
            'complete': { title: 'Complete Patients', icon: 'fa-check-double', color: '#059669', bg: '#D1FAE5' }
        };
        
        var t = titles[status] || titles['assigned'];
        document.getElementById('listTitle').textContent = t.title;
        document.getElementById('listIcon').className = 'fas ' + t.icon;
        document.getElementById('listIcon').style.color = t.color;
        
        var badge = document.getElementById('listCountBadge');
        var countId = 'toggle' + status.split('_').map(function(w) { return w.charAt(0).toUpperCase() + w.slice(1); }).join('') + 'Count';
        badge.textContent = document.getElementById(countId)?.textContent || 0;
        badge.style.background = t.bg;
        badge.style.color = t.color;
        
        fetchList(status);
    }
    
    function fetchList(status) {
        var container = document.getElementById('patientsListContainer');
        container.innerHTML = '<div style="text-align:center;padding:30px;"><div class="spinner" style="border-color:var(--border-color);border-top-color:var(--primary);"></div></div>';
        
        var fd = new FormData();
        fd.append('action', 'get_filtered_list');
        fd.append('status', status);
        
        fetch(window.location.href, { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success && d.html) {
                    container.innerHTML = d.html;
                } else {
                    container.innerHTML = '<div class="empty-list-state"><i class="fas fa-inbox"></i><p>No patients found</p></div>';
                }
            })
            .catch(function() {
                container.innerHTML = '<div class="empty-list-state"><i class="fas fa-exclamation-triangle" style="color:var(--danger);"></i><p>Error loading</p></div>';
            });
    }
    
    function performListSearch(query) {
        var term = query.toLowerCase().trim();
        document.getElementById('listSearchClear').classList.toggle('visible', term.length > 0);
        
        var rows = document.querySelectorAll('#patientsListContainer tbody tr');
        rows.forEach(function(row) {
            row.style.display = (term === '' || row.textContent.toLowerCase().indexOf(term) !== -1) ? '' : 'none';
        });
    }
    
    function clearListSearch() {
        document.getElementById('listSearchInput').value = '';
        document.getElementById('listSearchClear').classList.remove('visible');
        document.querySelectorAll('#patientsListContainer tbody tr').forEach(function(r) { r.style.display = ''; });
    }
    
    document.getElementById('assignForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var fd = new FormData(this);
        fd.append('action', 'change_doctor');
        
        var pid = document.getElementById('selectedPatientInput').value;
        if (!pid || pid === '0') {
            showToast('❌ Error', 'Please select a patient first', 'error');
            return;
        }
        
        var hidden = document.getElementById('selectedLabTestsInput');
        if (hidden && hidden.value) {
            hidden.value.split(',').forEach(function(id) { if (id) fd.append('lab_test_ids[]', id); });
        }
        
        var btn = document.getElementById('assignBtn');
        var orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Processing...';
        
        fetch(window.location.href, { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                btn.disabled = false;
                btn.innerHTML = orig;
                if (d.success) {
                    showToast('✅ Success', d.message, 'success');
                    setTimeout(function() { window.location.href = 'assign_doctor.php'; }, 3000);
                } else {
                    showToast('❌ Error', d.message || 'Failed', 'error');
                }
            })
            .catch(function(e) {
                btn.disabled = false;
                btn.innerHTML = orig;
                showToast('❌ Error', 'Network: ' + e.message, 'error');
            });
    });
    
    document.addEventListener('DOMContentLoaded', function() {
        updateVisitTypePrice();
        filterByStatus('assigned');
        
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                document.getElementById('listSearchInput')?.focus();
            }
        });
    });
    
    console.log('%c👨‍⚕️ Braick Admin - Assign Doctor V26', 'font-size:18px; font-weight:bold; color:#2563EB;');
    console.log('%c✅ V26: CHANGE DOCTOR → Inafuta visit ya zamani + bill yake KABISA', 'font-size:12px; color:#DC2626; font-weight:bold;');
    console.log('%c✅ V26: Kisha inaunda visit MPYA + bill MPYA (hakuna duplicate!)', 'font-size:12px; color:#059669; font-weight:bold;');
    console.log('%c✅ V26: REASSIGN → Patient abaki HANA DOCTOR (assigned_doctor_id = NULL)', 'font-size:12px; color:#DC2626; font-weight:bold;');
    console.log('%c✅ V26: Stock inarudishwa kwa meds/equipment zilizotumika', 'font-size:12px; color:#7C3AED; font-weight:bold;');
</script>

</body>
</html>
<?php ob_end_flush(); ?>