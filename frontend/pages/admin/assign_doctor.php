<?php
// ================================================================
// FILE: frontend/pages/admin/assign_doctor.php
// ADMIN / RECEPTION - ASSIGN / CHANGE DOCTOR & LAB REQUESTS
// ✅ Uses SHARED header & sidebar
// ✅ FIXED: Assigned Patients query (uses LATEST visit per patient)
// ✅ Beautiful modern card CSS
// ✅ 7 Vital Signs (with SpO2)
// ✅ Full dark mode support
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['admin', 'reception'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'reception';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$selected_branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : $user_branch_id;
$branch_name = $user_branch_name;

if ($user_role !== 'admin') {
    $selected_branch_id = $user_branch_id;
    $branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

$message = '';
$message_type = '';
$all_patients = [];
$pending_patients = [];
$assigned_patients = [];
$doctors = [];
$online_doctors = [];
$offline_doctors = [];
$online_doctors_count = 0;
$offline_doctors_count = 0;
$total_doctors = 0;
$visit_type_options = [];
$pending_count = 0;
$assigned_count = 0;
$selected_patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$latest_vital_signs = null;
$selected_patient_data = null;
$change_mode = isset($_GET['change']) && $_GET['change'] == 1;
$lab_tests_catalog = [];
$unread_notifications = 0;
$branches = [];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    if ($selected_branch_id > 0) {
        $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
        $stmt->execute([$selected_branch_id]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($branch) $branch_name = $branch['name'];
    }

    if ($user_role === 'admin') {
        $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
        $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Consultation services
    $stmt = $db->prepare("
        SELECT s.id, s.service_name, s.description, s.price, s.unit, s.is_active
        FROM services s
        LEFT JOIN service_categories sc ON s.category_id = sc.id
        WHERE sc.category_name LIKE '%Consultation%' 
        AND s.is_active = 1 
        AND (s.branch_id = ? OR s.branch_id IS NULL)
        ORDER BY 
            CASE 
                WHEN s.service_name LIKE '%New%' OR s.service_name LIKE '%General%' THEN 0
                WHEN s.service_name LIKE '%Emergency%' THEN 1
                WHEN s.service_name LIKE '%Specialist%' THEN 2
                WHEN s.service_name LIKE '%Follow%' THEN 3
                ELSE 4
            END,
            s.service_name
    ");
    $stmt->execute([$selected_branch_id]);
    $consultation_services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($consultation_services)) {
        $stmt = $db->prepare("SELECT id, service_name, description, price, unit, is_active FROM services WHERE is_active = 1 AND (branch_id = ? OR branch_id IS NULL) ORDER BY service_name");
        $stmt->execute([$selected_branch_id]);
        $consultation_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $visit_type_options = [];
    $default_key = 'general_consultation';

    foreach ($consultation_services as $service) {
        $service_name = $service['service_name'];
        $key = strtolower(str_replace(' ', '_', $service_name));
        $key = str_replace('-', '_', $key);
        $key = preg_replace('/[^a-z_]/', '', $key);
        if (empty($key)) $key = 'consultation_' . $service['id'];

        $icon = '🆕';
        if (strpos(strtolower($service_name), 'follow') !== false) $icon = '🔄';
        elseif (strpos(strtolower($service_name), 'emergency') !== false) $icon = '🚨';
        elseif (strpos(strtolower($service_name), 'specialist') !== false) $icon = '👨‍⚕️';
        elseif (strpos(strtolower($service_name), 'general') !== false) $icon = '🏥';

        $visit_type_options[$key] = [
            'id' => $service['id'],
            'name' => $service_name,
            'display_name' => $service_name,
            'price' => (float)($service['price'] ?? 15000),
            'unit' => $service['unit'] ?? 'each',
            'description' => $service['description'] ?? '',
            'is_active' => $service['is_active'] ?? 1,
            'icon' => $icon
        ];

        if (strpos(strtolower($service_name), 'new') !== false || strpos(strtolower($service_name), 'general') !== false) {
            $default_key = $key;
        }
    }

    if (empty($visit_type_options)) {
        $visit_type_options = [
            'new_patient' => ['id' => null, 'name' => 'New Patient Consultation', 'display_name' => 'New Patient', 'price' => 15000, 'unit' => 'each', 'description' => 'First time consultation', 'is_active' => 1, 'icon' => '🆕'],
            'general_consultation' => ['id' => null, 'name' => 'General Consultation', 'display_name' => 'General Consultation', 'price' => 12000, 'unit' => 'each', 'description' => 'Standard doctor consultation', 'is_active' => 1, 'icon' => '🏥'],
            'follow_up' => ['id' => null, 'name' => 'Follow-up Consultation', 'display_name' => 'Follow-up', 'price' => 8000, 'unit' => 'each', 'description' => 'Follow-up visit', 'is_active' => 1, 'icon' => '🔄'],
            'emergency' => ['id' => null, 'name' => 'Emergency Consultation', 'display_name' => 'Emergency', 'price' => 25000, 'unit' => 'each', 'description' => 'Emergency visit', 'is_active' => 1, 'icon' => '🚨']
        ];
    }

    // Lab tests
    $stmt = $db->prepare("SELECT id, test_name, test_code, category, price, description, reference_range FROM lab_tests_catalog WHERE is_active = 1 AND (branch_id = ? OR branch_id IS NULL) ORDER BY category, test_name");
    $stmt->execute([$selected_branch_id]);
    $lab_tests_catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ================================================================
    // ✅ FIXED: ALL PATIENTS QUERY - Uses LATEST ACTIVE VISIT per patient
    // (Chagua visit ya latest kwa kila patient, priority: with_doctor > assigned > lab_test > pending)
    // ================================================================
    $stmt = $db->prepare("
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
            v.created_at as visit_created_at,
            v.doctor_id as visit_doctor_id,
            v.consultation_fee,
            DATEDIFF(NOW(), p.created_at) as patient_days
        FROM patients p
        LEFT JOIN visits v ON v.id = (
            SELECT v2.id 
            FROM visits v2 
            WHERE v2.patient_id = p.id 
              AND v2.status IN ('pending', 'assigned', 'with_doctor', 'lab_test')
            ORDER BY 
                CASE v2.status
                    WHEN 'with_doctor' THEN 1
                    WHEN 'assigned' THEN 2
                    WHEN 'lab_test' THEN 3
                    WHEN 'pending' THEN 4
                    ELSE 5
                END,
                v2.id DESC
            LIMIT 1
        )
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE p.branch_id = ?
        ORDER BY p.created_at DESC, p.id DESC
    ");
    $stmt->execute([$selected_branch_id]);
    $all_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($selected_patient_id > 0) {
        foreach ($all_patients as $p) {
            if ($p['id'] == $selected_patient_id) {
                $selected_patient_data = $p;
                break;
            }
        }
    }

    // Separate patients
    foreach ($all_patients as $patient) {
        $patient['has_active_visit'] = !empty($patient['visit_id']);
        $patient['patient_days'] = isset($patient['patient_days']) ? (int)$patient['patient_days'] : 0;

        if ($patient['has_active_visit']) {
            if (in_array($patient['visit_status'], ['pending', 'lab_test'])) {
                $pending_patients[] = $patient;
                $pending_count++;
            } elseif ($patient['visit_status'] === 'assigned' || $patient['visit_status'] === 'with_doctor') {
                $assigned_patients[] = $patient;
                $assigned_count++;
            }
        }
    }

    if ($selected_patient_id > 0) {
        $stmt = $db->prepare("SELECT vs.*, u.full_name as recorded_by_name FROM vital_signs vs LEFT JOIN users u ON vs.recorded_by = u.id WHERE vs.patient_id = ? ORDER BY vs.recorded_at DESC LIMIT 1");
        $stmt->execute([$selected_patient_id]);
        $latest_vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $stmt = $db->prepare("SELECT id, full_name, specialty, is_online FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ? ORDER BY is_online DESC, full_name");
    $stmt->execute([$selected_branch_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($doctors as $doc) {
        if ($doc['is_online'] == 1) { $online_doctors[] = $doc; $online_doctors_count++; }
        else { $offline_doctors[] = $doc; $offline_doctors_count++; }
    }
    $total_doctors = count($doctors);

    $lab_tests = [];
    if ($selected_patient_id > 0) {
        $stmt = $db->prepare("
            SELECT lt.*, ltc.test_name, ltc.category, ltc.price
            FROM lab_tests lt
            LEFT JOIN lab_tests_catalog ltc ON lt.test_id = ltc.id
            WHERE lt.patient_id = ? AND lt.branch_id = ?
            ORDER BY lt.created_at DESC LIMIT 10
        ");
        $stmt->execute([$selected_patient_id, $selected_branch_id]);
        $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Helper function
    function createVisitBill($db, $patient_id, $visit_id, $visit_type, $consultation_fee, $user_id, $branch_id) {
        $stmt = $db->prepare("SELECT id, bill_number, status FROM bills WHERE visit_id = ? AND status IN ('pending', 'partial') LIMIT 1");
        $stmt->execute([$visit_id]);
        $existing_bill = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_bill) {
            $stmt = $db->prepare("UPDATE bills SET subtotal = ?, total_amount = ?, balance = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$consultation_fee, $consultation_fee, $consultation_fee, $existing_bill['id']]);

            $item_name = 'Consultation (' . ucfirst(str_replace('_', ' ', $visit_type)) . ')';
            $stmt = $db->prepare("UPDATE bill_items SET unit_price = ?, total_price = ?, item_name = ? WHERE bill_id = ? AND item_type = 'consultation'");
            $stmt->execute([$consultation_fee, $consultation_fee, $item_name, $existing_bill['id']]);

            return ['status' => 'updated', 'message' => 'Bill updated', 'bill_id' => $existing_bill['id'], 'bill_number' => $existing_bill['bill_number']];
        }

        $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999);

        $stmt = $db->prepare("INSERT INTO bills (bill_number, patient_id, visit_id, branch_id, created_by, subtotal, total_amount, balance, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$bill_number, $patient_id, $visit_id, $branch_id, $user_id, $consultation_fee, $consultation_fee, $consultation_fee]);
        $bill_id = $db->lastInsertId();

        $item_name = 'Consultation (' . ucfirst(str_replace('_', ' ', $visit_type)) . ')';
        $stmt = $db->prepare("INSERT INTO bill_items (bill_id, patient_id, branch_id, item_type, item_name, quantity, unit_price, total_price, status, created_at) VALUES (?, ?, ?, 'consultation', ?, 1, ?, ?, 'pending', NOW())");
        $stmt->execute([$bill_id, $patient_id, $branch_id, $item_name, $consultation_fee, $consultation_fee]);

        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE role = 'cashier' AND status = 'active' AND branch_id = ?");
            $stmt->execute([$branch_id]);
            $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($cashiers as $cashier) {
                $stmt = $db->prepare("INSERT INTO notifications (user_id, branch_id, title, message, type, link, is_read, created_at) VALUES (?, ?, '💰 New Bill Created', ?, 'info', ?, 0, NOW())");
                $stmt->execute([$cashier['id'], $branch_id, "Consultation bill #$bill_number (TSh " . number_format($consultation_fee) . ") for patient ID #$patient_id", "cashier_dashboard.php"]);
            }
        } catch (Exception $e) { error_log("Cashier notification error: " . $e->getMessage()); }

        return ['status' => 'created', 'message' => 'New bill created and sent to Cashier!', 'bill_id' => $bill_id, 'bill_number' => $bill_number];
    }

    // Handle AJAX
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $assignment_type = $_POST['assignment_type'] ?? 'doctor';

        // ================================================================
        // ✅ FIXED: AJAX - Live data (uses same fixed query)
        // ================================================================
        if ($action === 'get_live_data') {
            header('Content-Type: application/json');

            $stmt = $db->prepare("
                SELECT 
                    p.id,
                    p.full_name,
                    p.patient_id,
                    p.phone,
                    p.gender,
                    p.assigned_doctor_id,
                    p.created_at as patient_created_at,
                    u.full_name as assigned_doctor_name,
                    u.is_online as assigned_doctor_online,
                    v.id as visit_id,
                    v.status as visit_status,
                    v.visit_number,
                    v.doctor_id as visit_doctor_id,
                    v.consultation_fee,
                    v.visit_type,
                    v.created_at as visit_created_at,
                    DATEDIFF(NOW(), p.created_at) as patient_days
                FROM patients p
                LEFT JOIN visits v ON v.id = (
                    SELECT v2.id 
                    FROM visits v2 
                    WHERE v2.patient_id = p.id 
                      AND v2.status IN ('pending', 'assigned', 'with_doctor', 'lab_test')
                    ORDER BY 
                        CASE v2.status
                            WHEN 'with_doctor' THEN 1
                            WHEN 'assigned' THEN 2
                            WHEN 'lab_test' THEN 3
                            WHEN 'pending' THEN 4
                            ELSE 5
                        END,
                        v2.id DESC
                    LIMIT 1
                )
                LEFT JOIN users u ON v.doctor_id = u.id
                WHERE p.branch_id = ?
                ORDER BY p.created_at DESC, p.id DESC
            ");
            $stmt->execute([$selected_branch_id]);
            $updated_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT id, full_name, specialty, is_online FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ? ORDER BY is_online DESC, full_name");
            $stmt->execute([$selected_branch_id]);
            $updated_doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $online = 0; $offline = 0;
            foreach ($updated_doctors as $doc) {
                if ($doc['is_online'] == 1) $online++;
                else $offline++;
            }

            $pending = 0; $assigned = 0;
            foreach ($updated_patients as $p) {
                if (!empty($p['visit_id'])) {
                    if (in_array($p['visit_status'], ['pending', 'lab_test'])) $pending++;
                    elseif ($p['visit_status'] === 'assigned' || $p['visit_status'] === 'with_doctor') $assigned++;
                }
            }

            $patient_options = '<optgroup label="📋 All Patients (' . count($updated_patients) . ')">';
            foreach ($updated_patients as $p) {
                $status_label = '📋 No Visit';
                $status_class = 'no_visit';
                $status_icon = '📋';

                if (!empty($p['visit_id'])) {
                    if (in_array($p['visit_status'], ['pending', 'lab_test'])) {
                        if ($p['visit_status'] === 'lab_test' && empty($p['visit_doctor_id'])) {
                            $status_label = '🧪 Lab Only'; $status_class = 'lab_only'; $status_icon = '🧪';
                        } else {
                            $status_label = '⏳ Pending'; $status_class = 'pending'; $status_icon = '⏳';
                        }
                    } elseif ($p['visit_status'] === 'assigned' || $p['visit_status'] === 'with_doctor') {
                        $status_label = '✅ Assigned'; $status_class = 'assigned'; $status_icon = '✅';
                    }
                }

                $doctor_info = '';
                if (!empty($p['assigned_doctor_name'])) {
                    $online_status = !empty($p['assigned_doctor_online']) ? '🟢' : '⚪';
                    $doctor_info = ' 👨‍⚕️ Dr. ' . htmlspecialchars($p['assigned_doctor_name']) . ' ' . $online_status;
                }

                $selected = ($selected_patient_id == $p['id']) ? 'selected' : '';
                $days = isset($p['patient_days']) ? (int)$p['patient_days'] : 0;
                $days_text = $days > 0 ? '📅 ' . $days . ' days' : '📅 New';

                $patient_options .= '<option value="' . $p['id'] . '" data-status="' . $status_class . '" ' . $selected . '>';
                $patient_options .= $status_icon . ' ' . htmlspecialchars($p['full_name']) . ' (' . htmlspecialchars($p['patient_id'] ?? 'N/A') . ')';
                if (!empty($p['phone'])) $patient_options .= ' - ' . htmlspecialchars($p['phone']);
                $patient_options .= ' - ' . $days_text . $doctor_info;
                $patient_options .= '</option>';
            }
            $patient_options .= '</optgroup>';

            if (empty($updated_patients)) $patient_options = '<option value="" disabled>No patients found</option>';

            // Assigned list HTML
            $assigned_html = '';
            $assigned_count_list = 0;
            foreach ($updated_patients as $p) {
                if (empty($p['visit_id'])) continue;
                if (!in_array($p['visit_status'], ['assigned', 'with_doctor'])) continue;

                $assigned_count_list++;
                $doctor_name = !empty($p['assigned_doctor_name']) ? 'Dr. ' . htmlspecialchars($p['assigned_doctor_name']) : 'No doctor';
                $is_online = !empty($p['assigned_doctor_online']) ? '🟢' : '⚪';

                $assigned_days = 0;
                if (!empty($p['visit_created_at'])) {
                    $assigned_days = (int)floor((time() - strtotime($p['visit_created_at'])) / 86400);
                }
                $assigned_days_text = $assigned_days > 0 ? '<span class="assigned-days-badge">' . $assigned_days . ' days</span>' : '<span class="assigned-days-badge new">Just assigned</span>';

                $assigned_html .= '
                    <tr id="assigned-row-' . $p['id'] . '" class="table-row">
                        <td class="table-cell">
                            <span class="patient-name">' . htmlspecialchars($p['full_name']) . '</span>
                            ' . $assigned_days_text . '
                        </td>
                        <td class="table-cell-mono">' . htmlspecialchars($p['patient_id'] ?? 'N/A') . '</td>
                        <td class="table-cell">
                            <span class="assigned-doctor-tag">
                                <i class="fas fa-user-md"></i>
                                ' . $doctor_name . '
                                <span class="online-status">' . $is_online . '</span>
                            </span>
                        </td>
                        <td class="table-cell">
                            <span class="status-badge assigned">✅ Assigned</span>
                        </td>
                        <td class="table-cell">
                            <button onclick="selectPatientAndChange(' . $p['id'] . ')" class="btn-change">
                                <i class="fas fa-sync-alt"></i> Change
                            </button>
                        </td>
                    </tr>
                ';
            }

            if (empty($assigned_html)) {
                $assigned_html = '
                    <tr>
                        <td colspan="5" class="empty-state">
                            <i class="fas fa-user-check"></i>
                            <p>No patients currently assigned</p>
                        </td>
                    </tr>
                ';
            }

            // Doctor options
            $doctor_options = '';
            $online_options = '';
            $offline_options = '';
            foreach ($updated_doctors as $doc) {
                $specialty_text = !empty($doc['specialty']) ? ' (' . $doc['specialty'] . ')' : '';
                if ($doc['is_online'] == 1) {
                    $online_options .= '<option value="' . $doc['id'] . '" data-online="1">🟢 Dr. ' . htmlspecialchars($doc['full_name']) . $specialty_text . '</option>';
                } else {
                    $offline_options .= '<option value="' . $doc['id'] . '" data-online="0">⚪ Dr. ' . htmlspecialchars($doc['full_name']) . $specialty_text . '</option>';
                }
            }

            if (!empty($online_options)) $doctor_options .= '<optgroup label="🟢 Online Doctors (' . $online . ')">' . $online_options . '</optgroup>';
            if (!empty($offline_options)) $doctor_options .= '<optgroup label="⚪ Offline Doctors (' . $offline . ')">' . $offline_options . '</optgroup>';

            echo json_encode([
                'success' => true,
                'pending_count' => $pending,
                'assigned_count' => $assigned,
                'online_count' => $online,
                'offline_count' => $offline,
                'total_doctors' => count($updated_doctors),
                'patient_options' => $patient_options,
                'doctor_options' => $doctor_options,
                'assigned_list_html' => $assigned_html,
                'assigned_list_count' => $assigned_count_list,
                'timestamp' => date('H:i:s')
            ]);
            exit;
        }

        // AJAX: Patient details
        if ($action === 'get_patient_details') {
            header('Content-Type: application/json');
            $patient_id = (int)($_POST['patient_id'] ?? 0);

            if ($patient_id > 0) {
                $stmt = $db->prepare("
                    SELECT p.id, p.full_name, p.patient_id, p.phone, p.gender, p.date_of_birth, p.blood_group, p.allergies, p.address,
                           p.assigned_doctor_id, p.created_at as patient_created_at,
                           u.full_name as assigned_doctor_name,
                           v.id as visit_id, v.status as visit_status, v.visit_number, v.consultation_fee,
                           v.doctor_id as visit_doctor_id, v.visit_type, v.created_at as visit_date,
                           DATEDIFF(NOW(), p.created_at) as patient_days,
                           DATEDIFF(NOW(), v.created_at) as visit_days
                    FROM patients p
                    LEFT JOIN visits v ON v.id = (
                        SELECT v2.id 
                        FROM visits v2 
                        WHERE v2.patient_id = p.id 
                          AND v2.status IN ('pending', 'assigned', 'with_doctor', 'lab_test')
                        ORDER BY 
                            CASE v2.status
                                WHEN 'with_doctor' THEN 1
                                WHEN 'assigned' THEN 2
                                WHEN 'lab_test' THEN 3
                                WHEN 'pending' THEN 4
                                ELSE 5
                            END,
                            v2.id DESC
                        LIMIT 1
                    )
                    LEFT JOIN users u ON v.doctor_id = u.id
                    WHERE p.id = ? AND p.branch_id = ?
                ");
                $stmt->execute([$patient_id, $selected_branch_id]);
                $patient = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($patient) {
                    echo json_encode([
                        'success' => true, 'patient' => $patient,
                        'has_active_visit' => !empty($patient['visit_id']),
                        'visit_status' => $patient['visit_status'] ?? 'none',
                        'assigned_doctor' => $patient['assigned_doctor_name'] ?? 'None',
                        'assigned_doctor_id' => $patient['assigned_doctor_id'] ?? null,
                        'is_lab_only' => ($patient['visit_status'] === 'lab_test' && empty($patient['visit_doctor_id'])),
                        'consultation_fee' => $patient['consultation_fee'] ?? 0,
                        'visit_type' => $patient['visit_type'] ?? 'general_consultation',
                        'patient_days' => $patient['patient_days'] ?? 0,
                        'visit_days' => $patient['visit_days'] ?? 0
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Patient not found']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
            }
            exit;
        }

        // AJAX: Change doctor
        if ($action === 'change_doctor') {
            header('Content-Type: application/json');

            $patient_id = (int)($_POST['patient_id'] ?? 0);
            $doctor_id = (int)($_POST['doctor_id'] ?? 0);
            $visit_type_key = $_POST['visit_type'] ?? 'general_consultation';
            $service_id = (int)($_POST['service_id'] ?? 0);
            $symptoms = trim($_POST['symptoms'] ?? '');
            $complaint = trim($_POST['complaint'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            $response = ['success' => false, 'message' => ''];

            if ($patient_id <= 0) { $response['message'] = 'Please select a patient'; echo json_encode($response); exit; }
            if ($doctor_id <= 0) { $response['message'] = 'Please select a doctor'; echo json_encode($response); exit; }

            try {
                $db->beginTransaction();

                $stmt = $db->prepare("SELECT full_name, is_online FROM users WHERE id = ?");
                $stmt->execute([$doctor_id]);
                $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                $doctor_name = $doctor['full_name'] ?? 'Unknown';
                $doctor_online = $doctor['is_online'] ?? 0;

                $consultation_fee = $visit_type_options[$visit_type_key]['price'] ?? 0;

                $stmt = $db->prepare("SELECT id, status, doctor_id, visit_number FROM visits WHERE patient_id = ? AND status = 'lab_test' AND doctor_id IS NULL AND branch_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$patient_id, $selected_branch_id]);
                $lab_only_visit = $stmt->fetch(PDO::FETCH_ASSOC);

                $visit_id = null; $visit_number = ''; $bill_result = null;

                if ($lab_only_visit) {
                    $visit_id = $lab_only_visit['id'];
                    $visit_number = $lab_only_visit['visit_number'];
                    $stmt = $db->prepare("UPDATE visits SET doctor_id = ?, status = 'assigned', visit_type = ?, symptoms = ?, complaint = ?, notes = ?, consultation_fee = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$doctor_id, $visit_type_key, $symptoms, $complaint, $notes, $consultation_fee, $visit_id]);
                } else {
                    $stmt = $db->prepare("SELECT id, status, visit_type, doctor_id, visit_number FROM visits WHERE patient_id = ? AND status IN ('pending', 'assigned', 'with_doctor') AND branch_id = ? ORDER BY id DESC LIMIT 1");
                    $stmt->execute([$patient_id, $selected_branch_id]);
                    $existing_visit = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($existing_visit) {
                        $visit_id = $existing_visit['id'];
                        $visit_number = $existing_visit['visit_number'];

                        if ($existing_visit['status'] === 'with_doctor' || $existing_visit['status'] === 'completed') {
                            $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                            $stmt = $db->prepare("INSERT INTO visits (visit_number, patient_id, doctor_id, branch_id, visit_type, status, symptoms, complaint, notes, created_at, updated_at, consultation_fee, receptionist_id) VALUES (?, ?, ?, ?, ?, 'assigned', ?, ?, ?, NOW(), NOW(), ?, ?)");
                            $stmt->execute([$visit_number, $patient_id, $doctor_id, $selected_branch_id, $visit_type_key, $symptoms, $complaint, $notes, $consultation_fee, $user_id]);
                            $visit_id = $db->lastInsertId();
                        } else {
                            $stmt = $db->prepare("UPDATE visits SET doctor_id = ?, status = 'assigned', visit_type = ?, symptoms = ?, complaint = ?, notes = ?, consultation_fee = ?, updated_at = NOW() WHERE id = ?");
                            $stmt->execute([$doctor_id, $visit_type_key, $symptoms, $complaint, $notes, $consultation_fee, $visit_id]);
                        }
                    } else {
                        $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        $stmt = $db->prepare("INSERT INTO visits (visit_number, patient_id, doctor_id, branch_id, visit_type, status, symptoms, complaint, notes, created_at, updated_at, consultation_fee, receptionist_id) VALUES (?, ?, ?, ?, ?, 'assigned', ?, ?, ?, NOW(), NOW(), ?, ?)");
                        $stmt->execute([$visit_number, $patient_id, $doctor_id, $selected_branch_id, $visit_type_key, $symptoms, $complaint, $notes, $consultation_fee, $user_id]);
                        $visit_id = $db->lastInsertId();
                    }
                }

                if ($consultation_fee > 0) {
                    $bill_result = createVisitBill($db, $patient_id, $visit_id, $visit_type_key, $consultation_fee, $user_id, $selected_branch_id);
                }

                $temperature = $_POST['temperature'] ?? null;
                $bp_systolic = $_POST['bp_systolic'] ?? null;
                $bp_diastolic = $_POST['bp_diastolic'] ?? null;
                $pulse_rate = $_POST['pulse_rate'] ?? null;
                $weight = $_POST['weight'] ?? null;
                $height = $_POST['height'] ?? null;
                $oxygen_saturation = $_POST['oxygen_saturation'] ?? null;
                $vital_notes = trim($_POST['vital_notes'] ?? '');

                $has_vital = ($temperature !== null && $temperature !== '') || ($bp_systolic !== null && $bp_systolic !== '') || ($bp_diastolic !== null && $bp_diastolic !== '') || ($pulse_rate !== null && $pulse_rate !== '') || ($weight !== null && $weight !== '') || ($height !== null && $height !== '') || ($oxygen_saturation !== null && $oxygen_saturation !== '');

                if ($has_vital && $visit_id) {
                    $bmi = null;
                    if ($weight && $height && $height > 0) {
                        $height_m = $height / 100;
                        $bmi = round($weight / ($height_m * $height_m), 1);
                    }

                    $stmt = $db->prepare("INSERT INTO vital_signs (patient_id, visit_id, recorded_by, branch_id, temperature, blood_pressure_systolic, blood_pressure_diastolic, pulse_rate, weight, height, bmi, oxygen_saturation, notes, recorded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$patient_id, $visit_id, $user_id, $selected_branch_id, $temperature ?: null, $bp_systolic ?: null, $bp_diastolic ?: null, $pulse_rate ?: null, $weight ?: null, $height ?: null, $bmi, $oxygen_saturation ?: null, $vital_notes ?: null]);
                }

                $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = ? WHERE id = ?");
                $stmt->execute([$doctor_id, $patient_id]);

                $db->commit();

                $fee_text = '';
                if ($consultation_fee > 0) {
                    $fee_text = ' - Fee: TSh ' . number_format($consultation_fee);
                    if ($bill_result && $bill_result['status'] === 'created') $fee_text .= ' - ✅ Bill #' . $bill_result['bill_number'] . ' sent to Cashier!';
                    else if ($bill_result && $bill_result['status'] === 'updated') $fee_text .= ' - Bill #' . $bill_result['bill_number'] . ' updated';
                } else {
                    $fee_text = ' - Fee WAIVED';
                }

                $online_text = $doctor_online == 1 ? '🟢 Online' : '⚪ Offline';

                $response['success'] = true;
                $response['message'] = "✅ Doctor <strong>$doctor_name</strong> ($online_text) assigned successfully! Visit: $visit_number" . $fee_text;
                $response['visit_number'] = $visit_number;
                $response['doctor_name'] = $doctor_name;
                $response['patient_id'] = $patient_id;
                $response['bill'] = $bill_result;
                $response['doctor_online'] = $doctor_online;
                $response['bill_sent_to_cashier'] = ($bill_result && $bill_result['status'] === 'created') ? true : false;

            } catch (Exception $e) {
                $db->rollBack();
                $response['message'] = '❌ Error: ' . $e->getMessage();
            }

            echo json_encode($response);
            exit;
        }

        // Fallback: assign_doctor
        if ($action === 'assign_doctor' && $assignment_type === 'doctor') {
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            $doctor_id = (int)($_POST['doctor_id'] ?? 0);

            if ($patient_id > 0 && $doctor_id > 0) {
                header('Location: assign_doctor.php?patient_id=' . $patient_id . '&success=1&branch_id=' . $selected_branch_id);
                exit;
            }
        }

        // Lab test request
        if ($action === 'assign_doctor' && $assignment_type === 'lab') {
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            $lab_test_ids = isset($_POST['lab_test_ids']) ? $_POST['lab_test_ids'] : [];
            $lab_notes = trim($_POST['lab_notes'] ?? '');
            $symptoms = trim($_POST['symptoms'] ?? '');
            $complaint = trim($_POST['complaint'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if ($patient_id > 0 && !empty($lab_test_ids)) {
                try {
                    $db->beginTransaction();

                    $stmt = $db->prepare("SELECT id, status, doctor_id FROM visits WHERE patient_id = ? AND status IN ('pending', 'assigned', 'with_doctor', 'lab_test') AND branch_id = ? ORDER BY id DESC LIMIT 1");
                    $stmt->execute([$patient_id, $selected_branch_id]);
                    $existing_visit = $stmt->fetch(PDO::FETCH_ASSOC);

                    $visit_id = null; $visit_number = '';

                    if ($existing_visit) {
                        $visit_id = $existing_visit['id'];
                        $stmt = $db->prepare("UPDATE visits SET status = 'lab_test', symptoms = ?, complaint = ?, notes = ?, updated_at = NOW(), doctor_id = NULL, consultation_fee = 0 WHERE id = ?");
                        $stmt->execute([$symptoms, $complaint, $notes, $visit_id]);

                        $stmt = $db->prepare("SELECT visit_number FROM visits WHERE id = ?");
                        $stmt->execute([$visit_id]);
                        $visit = $stmt->fetch(PDO::FETCH_ASSOC);
                        $visit_number = $visit['visit_number'] ?? '';
                    } else {
                        $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        $stmt = $db->prepare("INSERT INTO visits (visit_number, patient_id, doctor_id, branch_id, visit_type, status, symptoms, complaint, notes, created_at, updated_at, receptionist_id, consultation_fee) VALUES (?, ?, NULL, ?, 'lab_only', 'lab_test', ?, ?, ?, NOW(), NOW(), ?, 0)");
                        $stmt->execute([$visit_number, $patient_id, $selected_branch_id, $symptoms, $complaint, $notes, $user_id]);
                        $visit_id = $db->lastInsertId();
                    }

                    $lab_total = 0;
                    foreach ($lab_test_ids as $test_id) {
                        $stmt = $db->prepare("SELECT test_name, price FROM lab_tests_catalog WHERE id = ? AND is_active = 1");
                        $stmt->execute([$test_id]);
                        $test = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($test) {
                            $lab_total += $test['price'];
                            $stmt = $db->prepare("INSERT INTO lab_tests (visit_id, patient_id, doctor_id, test_id, test_name, test_price, status, branch_id, notes, created_at) VALUES (?, ?, NULL, ?, ?, ?, 'pending', ?, ?, NOW())");
                            $stmt->execute([$visit_id, $patient_id, $test_id, $test['test_name'], $test['price'], $selected_branch_id, $lab_notes]);
                        }
                    }

                    $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL WHERE id = ?");
                    $stmt->execute([$patient_id]);

                    $db->commit();

                    $message = "✅ Lab test request created! Visit #: $visit_number";
                    $message_type = 'success';

                    header('Location: assign_doctor.php?patient_id=' . $patient_id . '&success=1&branch_id=' . $selected_branch_id);
                    exit;

                } catch (Exception $e) {
                    $db->rollBack();
                    $message = "❌ Error: " . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
    }
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

$common_symptoms = ['Fever' => 'Fever', 'Headache' => 'Headache', 'Cough' => 'Cough', 'Sore Throat' => 'Sore Throat', 'Body Pain' => 'Body Pain', 'Fatigue' => 'Fatigue', 'Nausea' => 'Nausea', 'Vomiting' => 'Vomiting', 'Diarrhea' => 'Diarrhea', 'Chest Pain' => 'Chest Pain', 'Shortness of Breath' => 'Shortness of Breath', 'Abdominal Pain' => 'Abdominal Pain', 'Dizziness' => 'Dizziness', 'Rash' => 'Rash', 'Swelling' => 'Swelling'];

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';

if ($user_role === 'admin') {
    include_once '../../components/admin_sidebar.php';
} else {
    if (file_exists(__DIR__ . '/../../components/reception_sidebar.php')) {
        include_once '../../components/reception_sidebar.php';
    } else {
        include_once '../../components/admin_sidebar.php';
    }
}
?>

<!-- ================================================================
     PAGE-SPECIFIC CSS - MODERN CARDS + FULL DARK MODE
     ================================================================ -->
<style>
    :root {
        --page-primary: #0B5ED7;
        --page-primary-dark: #0A4CA8;
        --page-primary-bg: #E8F0FE;
        --page-primary-light: #6EA8FE;
        --page-success: #059669;
        --page-success-bg: #D1FAE5;
        --page-danger: #DC2626;
        --page-danger-bg: #FEE2E2;
        --page-warning: #D97706;
        --page-warning-bg: #FEF3C7;
        --page-purple: #7C3AED;
        --page-purple-bg: #EDE9FE;
        --page-gray-50: #F8FAFC;
        --page-gray-100: #F1F5F9;
        --page-bg-body: #F0F4F8;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-border: #E2E8F0;
        --page-shadow: 0 1px 3px rgba(0,0,0,0.08);
        --page-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --page-shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
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
        --page-purple-bg: #2D1B5F;
        --page-primary: #3B82F6;
        --page-shadow: 0 1px 3px rgba(0,0,0,0.3);
        --page-shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --page-shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
    }

    /* BODY & MAIN CONTENT - DARK MODE */
    body { background: var(--page-bg-body, #F0F4F8); }
    html[data-theme="dark"] body { background: #0F172A !important; }

    .main-content { background: var(--page-bg-body, #F0F4F8); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; }

    /* BADGES */
    .days-badge, .assigned-days-badge {
        display: inline-block;
        background: var(--page-primary) !important;
        color: #ffffff !important;
        padding: 2px 12px !important;
        border-radius: 12px !important;
        font-size: 0.65rem !important;
        font-weight: 600 !important;
        border: none !important;
        box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
    }
    .days-badge.new, .assigned-days-badge.new {
        background: var(--page-success) !important;
    }

    /* ================================================================
       BLUE PAGE HEADER CARD
       ================================================================ */
    .page-header-card {
        background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 50%, #1E40AF 100%);
        border-radius: 20px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        color: white;
        box-shadow: 0 8px 32px rgba(37, 99, 235, 0.3), 0 4px 12px rgba(37, 99, 235, 0.2);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .page-header-card::before {
        content: '';
        position: absolute;
        top: -50%; right: -10%;
        width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-title {
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0 0 6px 0;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        color: white;
        position: relative;
        z-index: 2;
    }

    .page-header-title i {
        width: 44px; height: 44px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-subtitle {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.9);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .page-header-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        backdrop-filter: blur(10px);
    }

    .role-badge-display {
        background: rgba(255,255,255,0.25);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .live-indicator {
        display: inline-block;
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #34D399;
        animation: pulse-dot 1.5s infinite;
        margin-right: 4px;
    }

    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.3; transform: scale(0.8); }
    }

    .page-header-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .btn-outline-light {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.3);
        border-radius: 12px;
        color: white;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        white-space: nowrap;
        cursor: pointer;
    }

    .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    /* ================================================================
       MODERN CARDS
       ================================================================ */
    .modern-card {
        background: var(--page-bg-card);
        border-radius: 18px;
        border: 1px solid var(--page-border);
        box-shadow: var(--page-shadow-md);
        transition: all 0.3s ease;
        margin-bottom: 24px;
        overflow: hidden;
    }

    html[data-theme="dark"] .modern-card { background: #1E293B; border-color: #334155; }

    .modern-card:hover {
        border-color: var(--page-primary);
        box-shadow: var(--page-shadow-lg);
        transform: translateY(-2px);
    }

    .modern-card-header {
        padding: 18px 24px;
        background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%);
        border-bottom: 2px solid var(--page-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }

    html[data-theme="dark"] .modern-card-header {
        background: linear-gradient(135deg, #0F172A 0%, #1A2536 100%);
        border-bottom-color: #334155;
    }

    .modern-card-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--page-text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    html[data-theme="dark"] .modern-card-title { color: #F1F5F9; }

    .modern-card-title .title-icon {
        width: 36px; height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #2563EB, #1D4ED8);
        color: white;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    }

    .modern-card-title .title-icon.success {
        background: linear-gradient(135deg, #059669, #047857);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
    }

    .modern-card-title .title-icon.purple {
        background: linear-gradient(135deg, #7C3AED, #5B21B6);
        box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);
    }

    .modern-card-badge {
        background: var(--page-primary-bg);
        color: var(--page-primary);
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: 1.5px solid var(--page-primary-light);
    }

    .modern-card-badge.success {
        background: var(--page-success-bg);
        color: var(--page-success);
        border-color: var(--page-success);
    }

    .modern-card-badge.purple {
        background: var(--page-purple-bg);
        color: var(--page-purple);
        border-color: var(--page-purple);
    }

    .modern-card-body { padding: 20px 24px; }

    /* ================================================================
       TABLE STYLES
       ================================================================ */
    .modern-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.85rem;
    }

    .modern-table thead th {
        padding: 12px 16px;
        text-align: left;
        font-weight: 700;
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--page-text-secondary);
        background: var(--page-gray-50);
        border-bottom: 2px solid var(--page-border);
    }

    html[data-theme="dark"] .modern-table thead th {
        background: #0F172A;
        color: #94A3B8;
        border-bottom-color: #334155;
    }

    .table-row {
        border-bottom: 1px solid var(--page-border);
        transition: background 0.2s ease;
    }

    html[data-theme="dark"] .table-row { border-bottom-color: #334155; }

    .table-row:hover { background: var(--page-gray-50); }
    html[data-theme="dark"] .table-row:hover { background: #0F172A; }

    .table-row:last-child { border-bottom: none; }

    .table-cell {
        padding: 12px 16px;
        vertical-align: middle;
        color: var(--page-text-primary);
    }

    html[data-theme="dark"] .table-cell { color: #F1F5F9; }

    .table-cell-mono {
        padding: 12px 16px;
        font-family: 'Courier New', monospace;
        font-size: 0.8rem;
        color: var(--page-text-secondary);
    }

    html[data-theme="dark"] .table-cell-mono { color: #94A3B8; }

    .patient-name { font-weight: 600; margin-right: 6px; }

    /* STATUS BADGES */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    .status-badge.pending { background: #FEF3C7; color: #D97706; }
    .status-badge.assigned { background: #D1FAE5; color: #059669; }
    .status-badge.with_doctor { background: #EDE9FE; color: #7C3AED; }
    .status-badge.lab_test { background: #EDE9FE; color: #7C3AED; }
    .status-badge.lab_only { background: #EDE9FE; color: #7C3AED; border: 1.5px dashed #7C3AED; }
    .status-badge.no_visit { background: #E2E8F0; color: #475569; }

    html[data-theme="dark"] .status-badge.pending { background: #3D2E0A; color: #FBBF24; }
    html[data-theme="dark"] .status-badge.assigned { background: #1A3A2A; color: #34D399; }
    html[data-theme="dark"] .status-badge.with_doctor { background: #2D1B5F; color: #A78BFA; }
    html[data-theme="dark"] .status-badge.lab_test { background: #2D1B5F; color: #A78BFA; }
    html[data-theme="dark"] .status-badge.lab_only { background: #2D1B5F; color: #A78BFA; }
    html[data-theme="dark"] .status-badge.no_visit { background: #334155; color: #CBD5E1; }

    /* ASSIGNED DOCTOR TAG */
    .assigned-doctor-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
        background: linear-gradient(135deg, #D1FAE5, #A7F3D0);
        color: #047857;
        border: 1.5px solid #34D399;
    }

    html[data-theme="dark"] .assigned-doctor-tag {
        background: linear-gradient(135deg, #1A3A2A, #065F46);
        color: #34D399;
        border-color: #34D399;
    }

    /* BUTTONS */
    .btn-change {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        background: linear-gradient(135deg, #D97706, #B45309);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.72rem;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(217, 119, 6, 0.25);
    }

    .btn-change:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 14px rgba(217, 119, 6, 0.35);
    }

    /* STAT CARDS */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-top: 24px;
        max-width: 1100px;
        margin-left: auto;
        margin-right: auto;
    }

    .stat-card {
        background: var(--page-bg-card);
        border-radius: 16px;
        padding: 22px 20px;
        border: 1.5px solid var(--page-border);
        text-align: center;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        box-shadow: var(--page-shadow);
    }

    html[data-theme="dark"] .stat-card { background: #1E293B; border-color: #334155; }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
    }

    .stat-card.pending::before { background: linear-gradient(90deg, #D97706, #F59E0B); }
    .stat-card.assigned::before { background: linear-gradient(90deg, #059669, #34D399); }
    .stat-card.doctors::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--page-shadow-lg);
        border-color: var(--page-primary);
    }

    .stat-icon { font-size: 2rem; margin-bottom: 10px; display: block; }
    .stat-number { font-size: 2.2rem; font-weight: 800; line-height: 1.1; margin: 0 0 6px 0; letter-spacing: -0.02em; }
    .stat-number.pending { color: #D97706; }
    .stat-number.assigned { color: #059669; }
    .stat-number.doctors { color: #7C3AED; }
    .stat-label { font-size: 0.78rem; color: var(--page-text-secondary); font-weight: 600; margin: 0 0 6px 0; text-transform: uppercase; letter-spacing: 0.04em; }
    .stat-meta { font-size: 0.7rem; color: var(--page-text-secondary); margin: 0; opacity: 0.8; }

    /* FORM CARD */
    .form-card-modern {
        background: var(--page-bg-card);
        border-radius: 20px;
        padding: 28px 32px;
        border: 2px solid var(--page-border);
        max-width: 1100px;
        margin: 0 auto 24px;
        box-shadow: var(--page-shadow-md);
        transition: all 0.3s ease;
    }

    html[data-theme="dark"] .form-card-modern { background: #1E293B; border-color: #334155; }

    .form-card-modern:hover { border-color: var(--page-primary); box-shadow: var(--page-shadow-lg); }

    .form-card-modern.change-mode-active {
        border-color: var(--page-warning) !important;
        box-shadow: 0 0 0 4px rgba(217, 119, 6, 0.12) !important;
    }

    .form-header {
        display: flex;
        align-items: center;
        gap: 16px;
        padding-bottom: 20px;
        margin-bottom: 24px;
        border-bottom: 2px solid var(--page-border);
    }

    html[data-theme="dark"] .form-header { border-bottom-color: #334155; }

    .form-header-icon {
        width: 56px; height: 56px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        flex-shrink: 0;
        background: linear-gradient(135deg, #2563EB, #1D4ED8);
        color: white;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    .form-header h3 {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--page-text-primary);
        margin: 0 0 4px 0;
    }

    html[data-theme="dark"] .form-header h3 { color: #F1F5F9; }

    .form-header p { font-size: 0.8rem; color: var(--page-text-secondary); margin: 0; }

    .form-label {
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--page-text-primary);
        margin-bottom: 6px;
        display: block;
    }

    html[data-theme="dark"] .form-label { color: #F1F5F9; }

    .form-label .required { color: var(--page-danger); margin-left: 2px; }
    .form-label .label-icon { margin-right: 4px; color: var(--page-primary); }
    .form-label .label-badge {
        font-weight: 400;
        font-size: 0.65rem;
        padding: 2px 10px;
        border-radius: 12px;
        background: var(--page-gray-100);
        color: var(--page-text-secondary);
        margin-left: 6px;
    }

    html[data-theme="dark"] .form-label .label-badge { background: #0F172A; color: #94A3B8; }

    .form-control-modern {
        width: 100%;
        padding: 11px 16px;
        border: 2px solid var(--page-border);
        border-radius: 12px;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--page-bg-card);
        color: var(--page-text-primary);
        font-family: inherit;
    }

    html[data-theme="dark"] .form-control-modern {
        background: #0F172A;
        color: #F1F5F9;
        border-color: #334155;
    }

    html[data-theme="dark"] .form-control-modern::placeholder { color: #64748B; }
    html[data-theme="dark"] .form-control-modern option { background: #1E293B; color: #F1F5F9; }

    .form-control-modern:focus {
        border-color: var(--page-primary);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
    }

    html[data-theme="dark"] .form-control-modern:focus {
        border-color: #6EA8FE;
        box-shadow: 0 0 0 4px rgba(110, 168, 254, 0.15);
    }

    select.form-control-modern { appearance: auto; cursor: pointer; }
    textarea.form-control-modern { resize: vertical; min-height: 70px; }

    .grid-2-modern { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-row-modern { margin-bottom: 20px; }
    .form-row-modern:last-child { margin-bottom: 0; }

    /* VITAL SIGNS */
    .vital-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 12px; }
    .vital-grid-row2 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }

    .vital-card {
        background: var(--page-bg-body);
        border-radius: 14px;
        padding: 14px 16px;
        border: 2px solid var(--page-border);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        min-height: 100px;
        display: flex;
        flex-direction: column;
    }

    html[data-theme="dark"] .vital-card { background: #0F172A; border-color: #334155; }

    .vital-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
    }

    .vital-card.temperature::before { background: linear-gradient(90deg, #DC2626, #F87171); }
    .vital-card.bp::before { background: linear-gradient(90deg, #2563EB, #60A5FA); }
    .vital-card.pulse::before { background: linear-gradient(90deg, #059669, #34D399); }
    .vital-card.weight::before { background: linear-gradient(90deg, #D97706, #FBBF24); }
    .vital-card.height::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }
    .vital-card.bmi::before { background: linear-gradient(90deg, #0D9488, #2DD4BF); }
    .vital-card.spo2::before { background: linear-gradient(90deg, #0891B2, #22D3EE); }

    .vital-card:hover {
        border-color: var(--page-primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .vital-card.bmi { background: linear-gradient(135deg, rgba(37, 99, 235, 0.08), rgba(37, 99, 235, 0.04)); border-color: var(--page-primary); }
    .vital-card.spo2 { background: linear-gradient(135deg, rgba(8, 145, 178, 0.08), rgba(8, 145, 178, 0.04)); border-color: #0891B2; }

    .vital-header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
    .vital-icon { font-size: 1.2rem; }

    .vital-label {
        font-size: 0.65rem;
        font-weight: 700;
        color: var(--page-text-primary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        display: block;
    }

    html[data-theme="dark"] .vital-label { color: #F1F5F9; }

    .vital-input {
        border: none;
        background: transparent;
        padding: 4px 0;
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--page-text-primary);
        outline: none;
        width: 100%;
        font-family: inherit;
    }

    html[data-theme="dark"] .vital-input { color: #F1F5F9; }

    .vital-input:focus { color: var(--page-primary); }
    .vital-input::placeholder { color: var(--page-text-secondary); opacity: 0.5; font-weight: 400; }

    .vital-card.bmi .vital-input { font-weight: 800; color: var(--page-primary); }
    .vital-card.spo2 .vital-input { font-weight: 800; color: #0891B2; }

    .vital-unit { font-size: 0.65rem; color: var(--page-text-secondary); font-weight: 600; display: block; margin-top: 2px; }
    .vital-hint { font-size: 0.6rem; color: var(--page-success); margin-top: 4px; display: flex; align-items: center; gap: 4px; }

    .spo2-status {
        display: inline-block;
        font-size: 0.65rem;
        font-weight: 700;
        padding: 2px 10px;
        border-radius: 10px;
        margin-top: 4px;
    }

    .spo2-status.normal { background: rgba(5, 150, 105, 0.15); color: #059669; }
    .spo2-status.low { background: rgba(217, 119, 6, 0.15); color: #D97706; }
    .spo2-status.critical { background: rgba(220, 38, 38, 0.15); color: #DC2626; animation: pulse-spo2 1.5s infinite; }

    @keyframes pulse-spo2 { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }

    /* LAB TEST CARDS */
    .lab-tests-container {
        background: var(--page-bg-body);
        border-radius: 14px;
        border: 2px solid var(--page-border);
        padding: 8px;
        max-height: 320px;
        overflow-y: auto;
    }

    html[data-theme="dark"] .lab-tests-container { background: #0F172A; border-color: #334155; }

    .lab-test-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-radius: 10px;
        background: var(--page-bg-card);
        margin-bottom: 6px;
        transition: all 0.25s ease;
        cursor: pointer;
        border: 2px solid transparent;
    }

    html[data-theme="dark"] .lab-test-item { background: #1E293B; }

    .lab-test-item:hover { background: var(--page-primary-bg); border-color: var(--page-primary); transform: translateX(4px); }
    html[data-theme="dark"] .lab-test-item:hover { background: #1E3A5F; border-color: #6EA8FE; }
    .lab-test-item:last-child { margin-bottom: 0; }

    .lab-test-item .lab-checkbox { width: 20px; height: 20px; accent-color: var(--page-purple); cursor: pointer; flex-shrink: 0; }
    .lab-test-info { flex: 1; min-width: 0; }
    .lab-test-name { font-weight: 700; font-size: 0.85rem; color: var(--page-text-primary); display: block; margin-bottom: 2px; }
    html[data-theme="dark"] .lab-test-name { color: #F1F5F9; }
    .lab-test-category { display: inline-block; font-size: 0.65rem; padding: 2px 10px; background: var(--page-purple-bg); color: var(--page-purple); border-radius: 10px; font-weight: 600; }
    .lab-test-price { font-size: 0.78rem; font-weight: 700; color: var(--page-success); white-space: nowrap; }

    /* BUTTONS */
    .btn-modern {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 11px 24px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        min-height: 44px;
        font-family: inherit;
    }

    .btn-modern-primary {
        background: linear-gradient(135deg, #2563EB, #1D4ED8);
        color: white;
        box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
    }

    .btn-modern-primary:hover {
        background: linear-gradient(135deg, #1D4ED8, #1E40AF);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
        color: white;
    }

    .btn-modern-warning {
        background: linear-gradient(135deg, #D97706, #B45309);
        color: white;
        box-shadow: 0 4px 14px rgba(217, 119, 6, 0.3);
    }

    .btn-modern-warning:hover {
        background: linear-gradient(135deg, #B45309, #92400E);
        transform: translateY(-2px);
        color: white;
    }

    .btn-modern-outline {
        background: transparent;
        color: var(--page-text-primary);
        border: 2px solid var(--page-border);
    }

    html[data-theme="dark"] .btn-modern-outline { color: #F1F5F9; border-color: #334155; }

    .btn-modern-outline:hover {
        background: var(--page-gray-50);
        border-color: var(--page-primary);
        color: var(--page-primary);
        transform: translateY(-2px);
    }

    html[data-theme="dark"] .btn-modern-outline:hover { background: #0F172A; border-color: #6EA8FE; color: #6EA8FE; }

    .btn-modern-sm { padding: 7px 16px; font-size: 0.78rem; min-height: 36px; }

    .form-actions-modern {
        display: flex;
        gap: 12px;
        padding-top: 24px;
        margin-top: 24px;
        border-top: 2px solid var(--page-border);
        flex-wrap: wrap;
    }

    html[data-theme="dark"] .form-actions-modern { border-top-color: #334155; }

    /* ALERTS & TOAST */
    .alert-modern {
        padding: 16px 20px;
        border-radius: 14px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        max-width: 1100px;
        margin-left: auto;
        margin-right: auto;
        animation: slideDown 0.4s ease;
    }

    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

    .alert-modern-success { background: #D1FAE5; color: #047857; border: 2px solid #059669; }
    .alert-modern-error { background: #FEE2E2; color: #B91C1C; border: 2px solid #DC2626; }

    html[data-theme="dark"] .alert-modern-success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
    html[data-theme="dark"] .alert-modern-error { background: #3A1A1A; color: #F87171; border-color: #F87171; }

    .toast-modern {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 16px 22px;
        border-radius: 14px;
        z-index: 9999;
        max-width: 420px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: flex-start;
        gap: 12px;
        color: white;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }

    .toast-modern.show { transform: translateY(0); opacity: 1; }
    .toast-modern.success { background: linear-gradient(135deg, #059669, #047857); }
    .toast-modern.error { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    .toast-modern.info { background: linear-gradient(135deg, #2563EB, #1D4ED8); }
    .toast-modern.warning { background: linear-gradient(135deg, #D97706, #B45309); }

    .spinner {
        display: inline-block;
        width: 16px; height: 16px;
        border: 2px solid rgba(255,255,255,0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    .empty-state {
        padding: 40px 20px;
        text-align: center;
        color: var(--page-text-secondary);
    }

    .empty-state i { font-size: 2.5rem; display: block; margin-bottom: 12px; opacity: 0.5; }
    .empty-state p { margin: 0; font-size: 0.85rem; }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
        .vital-grid-row2 { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-card { padding: 20px; }
        .page-header-title { font-size: 1.2rem; }
        .page-header-title i { width: 36px; height: 36px; font-size: 1rem; }
        .form-card-modern { padding: 20px 16px; }
        .form-header { flex-direction: column; text-align: center; }
        .grid-2-modern { grid-template-columns: 1fr; gap: 14px; }
        .vital-grid { grid-template-columns: repeat(2, 1fr); }
        .vital-grid-row2 { grid-template-columns: repeat(2, 1fr); }
        .form-actions-modern { flex-direction: column; }
        .form-actions-modern .btn-modern { width: 100%; }
        .modern-card-body { padding: 16px; }
        .modern-card-header { padding: 14px 16px; }
        .stats-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 480px) {
        .vital-grid { grid-template-columns: 1fr; }
        .vital-grid-row2 { grid-template-columns: 1fr; }
    }

    @media print {
        .page-header-card, .btn-modern, .btn-outline-light, .form-actions-modern, .toast-modern { display: none !important; }
        .form-card-modern, .modern-card { box-shadow: none !important; border: 1px solid #ddd !important; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Blue Page Header Card -->
    <div class="page-header-card">
        <div>
            <h1 class="page-header-title">
                <i class="fas fa-user-md"></i>
                Assign / Change Doctor
                <span class="role-badge-display"><?= $user_role === 'admin' ? 'ADMIN' : 'RECEPTION' ?></span>
                <span class="page-header-badge" style="background:rgba(52,211,153,0.25);">
                    <span class="live-indicator"></span> Live
                </span>
            </h1>
            <p class="page-header-subtitle">
                <i class="fas fa-hospital"></i>
                Select patient, assign or change doctor in <strong><?= htmlspecialchars($branch_name) ?></strong>

                <span class="page-header-badge">
                    <i class="fas fa-user-md"></i>
                    <span style="color:#34D399;font-weight:700;"><?= $online_doctors_count ?></span> Online
                </span>
                <span class="page-header-badge">
                    <i class="fas fa-user-md"></i>
                    <span style="color:#F87171;font-weight:700;"><?= $offline_doctors_count ?></span> Offline
                </span>
                <span class="page-header-badge">
                    <i class="fas fa-user-clock"></i>
                    <span id="pendingCountHeader"><?= $pending_count ?></span> Pending
                </span>
                <span class="page-header-badge">
                    <i class="fas fa-user-check"></i>
                    <span id="assignedCountHeader"><?= $assigned_count ?></span> Assigned
                </span>
            </p>
        </div>
        <div class="page-header-actions">
            <a href="<?= $user_role === 'admin' ? '../admin/dashboard.php' : 'dashboard.php' ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert-modern alert-modern-<?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================
         ASSIGNED PATIENTS CARD
         ================================================================ -->
    <div class="modern-card">
        <div class="modern-card-header">
            <div class="modern-card-title">
                <div class="title-icon success"><i class="fas fa-user-check"></i></div>
                Assigned Patients
                <span class="modern-card-badge success" id="assignedListCount"><?= $assigned_count ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:0.7rem;color:var(--page-text-secondary);" id="assignedListUpdate">(Auto <?= date('h:i:s A') ?>)</span>
                <span style="font-size:0.7rem;color:#059669;display:inline-flex;align-items:center;gap:4px;">
                    <span class="live-indicator"></span> Live
                </span>
            </div>
        </div>

        <div class="modern-card-body" style="padding:0;">
            <div style="overflow-x:auto;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Patient / Days</th>
                            <th>Patient ID</th>
                            <th>Assigned Doctor</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="assignedPatientsTableBody">
                        <?php foreach ($assigned_patients as $patient): 
                            $assigned_days = 0;
                            if (!empty($patient['visit_created_at'])) {
                                $assigned_days = (int)floor((time() - strtotime($patient['visit_created_at'])) / 86400);
                            }
                            $days_text = $assigned_days > 0 ? '<span class="assigned-days-badge">' . $assigned_days . ' days</span>' : '<span class="assigned-days-badge new">Just assigned</span>';
                        ?>
                            <tr id="assigned-row-<?= $patient['id'] ?>" class="table-row">
                                <td class="table-cell">
                                    <span class="patient-name"><?= htmlspecialchars($patient['full_name']) ?></span>
                                    <?= $days_text ?>
                                </td>
                                <td class="table-cell-mono"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                                <td class="table-cell">
                                    <?php if (!empty($patient['assigned_doctor_name'])): ?>
                                        <span class="assigned-doctor-tag">
                                            <i class="fas fa-user-md"></i>
                                            Dr. <?= htmlspecialchars($patient['assigned_doctor_name']) ?>
                                            <span class="online-status"><?= $patient['assigned_doctor_online'] == 1 ? '🟢' : '⚪' ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--page-text-secondary);font-size:0.75rem;">No doctor</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-cell">
                                    <span class="status-badge assigned">✅ Assigned</span>
                                </td>
                                <td class="table-cell">
                                    <button onclick="selectPatientAndChange(<?= $patient['id'] ?>)" class="btn-change">
                                        <i class="fas fa-sync-alt"></i> Change
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($assigned_patients)): ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <i class="fas fa-user-check"></i>
                                    <p>No patients currently assigned</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- LAB TESTS CARD -->
    <?php if ($selected_patient_id > 0 && !empty($lab_tests)): ?>
    <div class="modern-card" style="border-color:var(--page-purple);border-width:2px;">
        <div class="modern-card-header" style="background:linear-gradient(135deg, #EDE9FE, #DDD6FE);">
            <div class="modern-card-title">
                <div class="title-icon purple"><i class="fas fa-flask"></i></div>
                Lab Tests for Selected Patient
                <span class="modern-card-badge purple"><?= count($lab_tests) ?></span>
            </div>
        </div>
        <div class="modern-card-body" style="padding:0;">
            <div style="overflow-x:auto;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Price</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $test): 
                            $status_class = $test['status'] ?? 'pending';
                            $status_label = ucfirst(str_replace('_', ' ', $test['status'] ?? 'Pending'));
                            $icon = $status_class === 'completed' ? '✅' : ($status_class === 'in_progress' ? '⏳' : '⏰');
                        ?>
                            <tr class="table-row">
                                <td class="table-cell" style="font-weight:600;"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                <td class="table-cell" style="color:var(--page-text-secondary);font-size:0.8rem;"><?= htmlspecialchars($test['category'] ?? 'N/A') ?></td>
                                <td class="table-cell">
                                    <span class="status-badge <?= $status_class ?>"><?= $icon ?> <?= $status_label ?></span>
                                </td>
                                <td class="table-cell" style="font-weight:700;color:#059669;">
                                    TSh <?= number_format($test['price'] ?? 0, 0) ?>
                                </td>
                                <td class="table-cell" style="font-size:0.8rem;color:var(--page-text-secondary);">
                                    <?= date('d/m/Y H:i', strtotime($test['created_at'] ?? 'now')) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================
         ASSIGN FORM CARD
         ================================================================ -->
    <div class="form-card-modern <?= $change_mode ? 'change-mode-active' : '' ?>" id="mainFormCard">
        <div class="form-header">
            <div class="form-header-icon" style="<?= $change_mode ? 'background:linear-gradient(135deg, #D97706, #B45309);' : '' ?>">
                <i class="fas <?= $change_mode ? 'fa-sync-alt' : 'fa-stethoscope' ?>"></i>
            </div>
            <div>
                <h3>
                    <?= $change_mode ? '🔄 Change Doctor' : 'Assign or Change Doctor' ?>
                    <?php if ($change_mode && $selected_patient_data): ?>
                        <span style="font-size:0.75rem;color:#D97706;font-weight:500;">
                            — <?= htmlspecialchars($selected_patient_data['full_name']) ?>
                        </span>
                    <?php endif; ?>
                </h3>
                <p><?= $change_mode ? '🔄 Change Mode: Select new doctor for patient' : 'Select patient and assign a doctor' ?></p>
            </div>
        </div>

        <form method="POST" action="" id="assignForm">
            <input type="hidden" name="action" value="assign_doctor">

            <!-- ROW 1 -->
            <div class="grid-2-modern">
                <div class="form-row-modern">
                    <label class="form-label">
                        <i class="fas fa-user label-icon"></i> Select Patient <span class="required">*</span>
                        <span class="label-badge">Newest First</span>
                    </label>

                    <select name="patient_id" class="form-control-modern" required id="patientSelect">
                        <option value="">-- Select Patient --</option>
                        <?php if (!empty($all_patients)): ?>
                            <optgroup label="📋 All Patients (<?= count($all_patients) ?>)">
                                <?php foreach ($all_patients as $patient): 
                                    $status_label = '📋 No Visit';
                                    $status_class = 'no_visit';
                                    $status_icon = '📋';

                                    if (!empty($patient['visit_id'])) {
                                        if (in_array($patient['visit_status'], ['pending', 'lab_test'])) {
                                            if ($patient['visit_status'] === 'lab_test' && empty($patient['visit_doctor_id'])) {
                                                $status_label = '🧪 Lab Only'; $status_class = 'lab_only'; $status_icon = '🧪';
                                            } else {
                                                $status_label = '⏳ Pending'; $status_class = 'pending'; $status_icon = '⏳';
                                            }
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
                                    $days = isset($patient['patient_days']) ? (int)$patient['patient_days'] : 0;
                                    $days_text = $days > 0 ? '📅 ' . $days . ' days' : '📅 New';
                                ?>
                                    <option value="<?= $patient['id'] ?>" data-status="<?= $status_class ?>" <?= $selected ?>>
                                        <?= $status_icon ?> <?= htmlspecialchars($patient['full_name']) ?> (<?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>)
                                        <?php if (!empty($patient['phone'])): ?> - <?= htmlspecialchars($patient['phone']) ?><?php endif; ?>
                                        - <?= $days_text ?><?= $doctor_info ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php else: ?>
                            <option value="" disabled>No patients found</option>
                        <?php endif; ?>
                    </select>

                    <p style="font-size:0.7rem;color:var(--page-text-secondary);margin-top:6px;">
                        <?php if ($pending_count > 0): ?>
                            🟡 <span style="color:#D97706;font-weight:600;"><?= $pending_count ?></span> Pending |
                        <?php endif; ?>
                        <?php if ($assigned_count > 0): ?>
                            ✅ <span style="color:#059669;font-weight:600;"><?= $assigned_count ?></span> Assigned |
                        <?php endif; ?>
                        Total: <strong><?= count($all_patients) ?></strong> patients
                    </p>
                </div>

                <div class="form-row-modern">
                    <label class="form-label">
                        <i class="fas fa-tasks label-icon"></i> Select Action <span class="required">*</span>
                    </label>
                    <select name="assignment_type" class="form-control-modern" required id="assignmentTypeSelect" onchange="toggleAssignmentType(this.value)">
                        <option value="doctor" <?= $change_mode ? 'selected' : '' ?>>👨‍⚕️ Assign / Change Doctor</option>
                        <option value="lab">🧪 Request Lab Test(s)</option>
                    </select>
                    <p style="font-size:0.7rem;color:var(--page-text-secondary);margin-top:6px;" id="assignmentTypeHelp">
                        👨‍⚕️ Assign a doctor to the patient or change existing doctor
                    </p>
                </div>
            </div>

            <!-- ROW 2 -->
            <div class="grid-2-modern" id="doctorSection">
                <div class="form-row-modern">
                    <label class="form-label">
                        <i class="fas fa-user-md label-icon"></i> Select Doctor <span class="required">*</span>
                    </label>
                    <select name="doctor_id" class="form-control-modern" required id="doctorSelect">
                        <option value="">-- Select Doctor --</option>
                        <?php if (!empty($online_doctors)): ?>
                            <optgroup label="🟢 Online Doctors (<?= $online_doctors_count ?>)">
                                <?php foreach ($online_doctors as $doctor): ?>
                                    <option value="<?= $doctor['id'] ?>" data-online="1">
                                        🟢 Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                                        <?php if (!empty($doctor['specialty'])): ?> (<?= htmlspecialchars($doctor['specialty']) ?>)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if (!empty($offline_doctors)): ?>
                            <optgroup label="⚪ Offline Doctors (<?= $offline_doctors_count ?>)">
                                <?php foreach ($offline_doctors as $doctor): ?>
                                    <option value="<?= $doctor['id'] ?>" data-online="0">
                                        ⚪ Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                                        <?php if (!empty($doctor['specialty'])): ?> (<?= htmlspecialchars($doctor['specialty']) ?>)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                    <?php if (!empty($doctors)): ?>
                        <p style="font-size:0.7rem;color:var(--page-text-secondary);margin-top:6px;">
                            <span style="color:#059669;font-weight:600;">🟢 <?= $online_doctors_count ?> online</span>
                            <span style="margin:0 4px;">|</span>
                            <span>⚪ <?= $offline_doctors_count ?> offline</span>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="form-row-modern">
                    <label class="form-label">
                        <i class="fas fa-tag label-icon"></i> Visit Type <span class="required">*</span>
                        <span class="label-badge" id="visitTypePrice">Fee: TSh 15,000</span>
                    </label>
                    <select name="visit_type" class="form-control-modern" required id="visitTypeSelect" onchange="updateVisitTypePrice()">
                        <?php foreach ($visit_type_options as $key => $option):
                            $is_default = (strpos(strtolower($option['name']), 'new') !== false || strpos(strtolower($option['name']), 'general') !== false);
                            $selected = $is_default ? 'selected' : '';
                        ?>
                            <option value="<?= htmlspecialchars($key) ?>" data-price="<?= $option['price'] ?>" <?= $selected ?>>
                                <?= $option['icon'] ?? '🆕' ?> <?= htmlspecialchars($option['display_name']) ?> - TSh <?= number_format($option['price'], 0) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p style="font-size:0.7rem;color:var(--page-text-secondary);margin-top:6px;">
                        <i class="fas fa-info-circle"></i> Standard doctor consultation
                    </p>
                </div>
            </div>

            <!-- ROW 3 -->
            <div class="grid-2-modern">
                <div class="form-row-modern">
                    <label class="form-label">
                        <i class="fas fa-notes-medical label-icon"></i> Common Symptoms
                    </label>
                    <select name="symptoms_select" class="form-control-modern" id="symptomsSelect">
                        <option value="">-- Select Common Symptom --</option>
                        <?php foreach ($common_symptoms as $key => $symptom): ?>
                            <option value="<?= htmlspecialchars($symptom) ?>"><?= htmlspecialchars($symptom) ?></option>
                        <?php endforeach; ?>
                        <option value="other">✏️ Other (Type below)</option>
                    </select>
                </div>

                <div class="form-row-modern">
                    <label class="form-label">
                        <i class="fas fa-file-medical label-icon"></i> Symptoms Details
                    </label>
                    <textarea name="symptoms" class="form-control-modern" placeholder="Describe patient symptoms in detail..." id="symptomsTextarea" rows="3"></textarea>
                </div>
            </div>

            <!-- ROW 4 -->
            <div class="grid-2-modern">
                <div class="form-row-modern">
                    <label class="form-label">
                        <i class="fas fa-comment-medical label-icon"></i> Complaint / Reason
                    </label>
                    <textarea name="complaint" class="form-control-modern" placeholder="Patient's main complaint..." id="complaintInput" rows="3"></textarea>
                </div>

                <div class="form-row-modern">
                    <label class="form-label">
                        <i class="fas fa-sticky-note label-icon"></i> Additional Notes
                    </label>
                    <textarea name="notes" class="form-control-modern" placeholder="Any additional notes..." id="notesInput" rows="3"></textarea>
                </div>
            </div>

            <!-- LAB TEST SECTION -->
            <div id="labSection" style="display:none;">
                <div class="modern-card" style="border-color:var(--page-purple);border-width:2px;margin-bottom:16px;">
                    <div class="modern-card-header" style="background:linear-gradient(135deg, #EDE9FE, #DDD6FE);">
                        <div class="modern-card-title">
                            <div class="title-icon purple"><i class="fas fa-flask"></i></div>
                            Select Lab Tests
                            <span class="modern-card-badge purple" id="labSelectedCount">0 selected</span>
                        </div>
                        <button type="button" class="btn-modern btn-modern-outline btn-modern-sm" onclick="closeLabTests()">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>

                    <div class="modern-card-body">
                        <div class="lab-tests-container" id="labTestsContainer">
                            <?php if (!empty($lab_tests_catalog)): ?>
                                <?php foreach ($lab_tests_catalog as $test): ?>
                                    <div class="lab-test-item">
                                        <input type="checkbox" name="lab_test_ids[]" value="<?= $test['id'] ?>" id="lab_test_<?= $test['id'] ?>" class="lab-checkbox" onchange="updateLabSelection()">
                                        <label for="lab_test_<?= $test['id'] ?>" class="lab-test-info" style="cursor:pointer;">
                                            <span class="lab-test-name"><?= htmlspecialchars($test['test_name']) ?></span>
                                            <?php if (!empty($test['category'])): ?>
                                                <span class="lab-test-category"><?= htmlspecialchars($test['category']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <span class="lab-test-price">TSh <?= number_format($test['price'] ?? 0, 0) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-flask"></i>
                                    <p>No lab tests available</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;flex-wrap:wrap;gap:10px;">
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <button type="button" class="btn-modern btn-modern-outline btn-modern-sm" onclick="selectAllLabTests()">
                                    <i class="fas fa-check-double"></i> Select All
                                </button>
                                <button type="button" class="btn-modern btn-modern-outline btn-modern-sm" onclick="deselectAllLabTests()">
                                    <i class="fas fa-times"></i> Clear All
                                </button>
                                <span class="lab-test-price" id="labTotalPrice" style="padding:6px 14px;background:var(--page-success-bg);border-radius:20px;">
                                    Total: TSh 0
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-row-modern">
                    <label class="form-label">
                        <i class="fas fa-notes-medical label-icon"></i> Lab Test Notes
                    </label>
                    <textarea name="lab_notes" class="form-control-modern" placeholder="Any special instructions for lab tests..." rows="2" id="labNotes"></textarea>
                </div>
            </div>

            <!-- VITAL SIGNS - 7 CARDS -->
            <div class="form-row-modern">
                <label class="form-label" style="font-size:0.85rem;">
                    <i class="fas fa-heartbeat" style="color:#DC2626;"></i> Vital Signs
                    <span class="label-badge">Optional — 7 vitals with SpO₂</span>
                </label>

                <div class="vital-grid">
                    <div class="vital-card temperature">
                        <div class="vital-header">
                            <span class="vital-icon">🌡️</span>
                            <div><span class="vital-label">Temperature</span></div>
                        </div>
                        <input type="number" name="temperature" class="vital-input" step="0.1" placeholder="36.5" value="<?= $latest_vital_signs['temperature'] ?? '' ?>">
                        <span class="vital-unit">Celsius (°C)</span>
                    </div>

                    <div class="vital-card bp">
                        <div class="vital-header">
                            <span class="vital-icon">💓</span>
                            <div><span class="vital-label">Blood Pressure</span></div>
                        </div>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <input type="number" name="bp_systolic" class="vital-input" style="width:45%;" placeholder="120" value="<?= $latest_vital_signs['blood_pressure_systolic'] ?? '' ?>">
                            <span style="color:var(--page-text-secondary);font-weight:700;">/</span>
                            <input type="number" name="bp_diastolic" class="vital-input" style="width:45%;" placeholder="80" value="<?= $latest_vital_signs['blood_pressure_diastolic'] ?? '' ?>">
                        </div>
                        <span class="vital-unit">Systolic / Diastolic (mmHg)</span>
                    </div>

                    <div class="vital-card pulse">
                        <div class="vital-header">
                            <span class="vital-icon">❤️</span>
                            <div><span class="vital-label">Pulse Rate</span></div>
                        </div>
                        <input type="number" name="pulse_rate" class="vital-input" placeholder="72" value="<?= $latest_vital_signs['pulse_rate'] ?? '' ?>">
                        <span class="vital-unit">Beats per minute (bpm)</span>
                    </div>
                </div>

                <div class="vital-grid-row2">
                    <div class="vital-card weight">
                        <div class="vital-header">
                            <span class="vital-icon">⚖️</span>
                            <div><span class="vital-label">Weight</span></div>
                        </div>
                        <input type="number" name="weight" class="vital-input" step="0.1" placeholder="65" value="<?= $latest_vital_signs['weight'] ?? '' ?>" id="weightInput" oninput="calculateBMI()">
                        <span class="vital-unit">Kilograms (kg)</span>
                    </div>

                    <div class="vital-card height">
                        <div class="vital-header">
                            <span class="vital-icon">📏</span>
                            <div><span class="vital-label">Height</span></div>
                        </div>
                        <input type="number" name="height" class="vital-input" step="0.1" placeholder="170" value="<?= $latest_vital_signs['height'] ?? '' ?>" id="heightInput" oninput="calculateBMI()">
                        <span class="vital-unit">Centimeters (cm)</span>
                    </div>

                    <div class="vital-card bmi">
                        <div class="vital-header">
                            <span class="vital-icon">📊</span>
                            <div><span class="vital-label">BMI</span></div>
                        </div>
                        <input type="number" name="bmi" class="vital-input" id="bmiOutput" readonly step="0.1" placeholder="22.5" value="<?= $latest_vital_signs['bmi'] ?? '' ?>">
                        <span class="vital-unit">kg/m² — Auto</span>
                        <span class="vital-hint" id="bmiCategory">Auto-calculated</span>
                    </div>

                    <div class="vital-card spo2">
                        <div class="vital-header">
                            <span class="vital-icon">🫁</span>
                            <div><span class="vital-label">SpO₂</span></div>
                        </div>
                        <input type="number" name="oxygen_saturation" class="vital-input" placeholder="98" value="<?= $latest_vital_signs['oxygen_saturation'] ?? '' ?>" id="spo2Input" oninput="updateSpO2Status()">
                        <span class="vital-unit">Oxygen Saturation %</span>
                        <span class="spo2-status" id="spo2Status">Auto</span>
                    </div>
                </div>

                <div style="margin-top:12px;">
                    <input type="text" name="vital_notes" class="form-control-modern" placeholder="Vital signs notes (optional)" value="<?= $latest_vital_signs['notes'] ?? '' ?>" style="font-size:0.8rem;padding:8px 14px;">
                </div>
            </div>

            <!-- FORM ACTIONS -->
            <div class="form-actions-modern">
                <button type="submit" class="btn-modern <?= $change_mode ? 'btn-modern-warning' : 'btn-modern-primary' ?>" id="assignBtn">
                    <i class="fas <?= $change_mode ? 'fa-sync-alt' : 'fa-user-md' ?>"></i>
                    <?= $change_mode ? 'Change Doctor' : 'Assign / Change Doctor' ?>
                </button>
                <button type="reset" class="btn-modern btn-modern-outline" id="resetBtn">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="<?= $user_role === 'admin' ? '../admin/dashboard.php' : 'dashboard.php' ?>" class="btn-modern btn-modern-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- STAT CARDS -->
    <div class="stats-grid">
        <div class="stat-card pending">
            <span class="stat-icon">🟡</span>
            <p class="stat-number pending" id="pendingStatNumber"><?= $pending_count ?></p>
            <p class="stat-label">Pending (No Doctor)</p>
            <p class="stat-meta" id="pendingUpdateTime">Updated <?= date('H:i:s') ?></p>
        </div>
        <div class="stat-card assigned">
            <span class="stat-icon">✅</span>
            <p class="stat-number assigned" id="assignedStatNumber"><?= $assigned_count ?></p>
            <p class="stat-label">Assigned (Has Doctor)</p>
            <p class="stat-meta" id="assignedUpdateTime">Updated <?= date('H:i:s') ?></p>
        </div>
        <div class="stat-card doctors">
            <span class="stat-icon">👨‍⚕️</span>
            <p class="stat-number doctors" id="availableDoctorsStat"><?= $total_doctors ?></p>
            <p class="stat-label">Total Doctors</p>
            <p class="stat-meta" id="onlineDoctorsStatTime">🟢 <?= $online_doctors_count ?> online, ⚪ <?= $offline_doctors_count ?> offline</p>
        </div>
    </div>

</main>

<!-- Toast -->
<div id="toast" class="toast-modern" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.2rem;"></i>
    <div>
        <p style="font-weight:700;font-size:0.85rem;margin:0 0 2px 0;" id="toastTitle">Notification</p>
        <p style="font-size:0.78rem;opacity:0.95;margin:0;line-height:1.4;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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
            if (body) body.style.background = '#F0F4F8';
            if (mainContent) mainContent.style.background = '#F0F4F8';
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
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-modern ' + type;
        toastTitle.textContent = title;
        toastMessage.innerHTML = message;
        toast.style.display = 'flex';
        void toast.offsetWidth;
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 4000);
    }

    // ================================================================
    // SYMPTOMS SELECT
    // ================================================================
    document.getElementById('symptomsSelect')?.addEventListener('change', function() {
        var value = this.value;
        var target = document.getElementById('symptomsTextarea');
        if (!target) return;

        if (value && value !== 'other') {
            var currentValue = target.value.trim();
            if (currentValue) target.value = currentValue + ', ' + value;
            else target.value = value;
        } else if (value === 'other') {
            target.focus();
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

            var category = '';
            if (bmi < 18.5) category = 'Underweight';
            else if (bmi < 25) category = '✓ Normal';
            else if (bmi < 30) category = 'Overweight';
            else category = 'Obese';

            bmiCategory.textContent = category;
        } else {
            bmiOutput.value = '';
            bmiCategory.textContent = 'Auto-calculated';
        }
    }

    // ================================================================
    // SpO2 STATUS
    // ================================================================
    function updateSpO2Status() {
        var spo2Input = document.getElementById('spo2Input');
        var spo2Status = document.getElementById('spo2Status');
        if (!spo2Input || !spo2Status) return;

        var spo2 = parseFloat(spo2Input.value);

        if (!spo2 || spo2 <= 0) {
            spo2Status.textContent = 'Auto';
            spo2Status.className = 'spo2-status';
            return;
        }

        var status = '';
        var statusClass = '';

        if (spo2 >= 95) { status = '✅ Normal'; statusClass = 'normal'; }
        else if (spo2 >= 90) { status = '⚠️ Low'; statusClass = 'low'; }
        else { status = '🚨 Critical'; statusClass = 'critical'; }

        spo2Status.textContent = status;
        spo2Status.className = 'spo2-status ' + statusClass;
    }

    // ================================================================
    // TOGGLE ASSIGNMENT TYPE
    // ================================================================
    function toggleAssignmentType(type) {
        var doctorSection = document.getElementById('doctorSection');
        var labSection = document.getElementById('labSection');
        var doctorSelect = document.getElementById('doctorSelect');
        var assignBtn = document.getElementById('assignBtn');
        var helpText = document.getElementById('assignmentTypeHelp');

        if (type === 'lab') {
            doctorSection.style.display = 'none';
            labSection.style.display = 'block';
            doctorSelect.removeAttribute('required');
            helpText.textContent = '🧪 Lab test request — Doctor not required';
            assignBtn.innerHTML = '<i class="fas fa-flask"></i> Request Lab Tests';
        } else {
            doctorSection.style.display = 'block';
            labSection.style.display = 'none';
            doctorSelect.setAttribute('required', 'required');
            helpText.textContent = '👨‍⚕️ Assign or change doctor — Doctor required';
            assignBtn.innerHTML = '<i class="fas fa-user-md"></i> Assign / Change Doctor';
        }
    }

    function closeLabTests() {
        var labSection = document.getElementById('labSection');
        if (labSection) labSection.style.display = 'none';
        var select = document.getElementById('assignmentTypeSelect');
        if (select) { select.value = 'doctor'; toggleAssignmentType('doctor'); }
    }

    function selectAllLabTests() {
        document.querySelectorAll('.lab-checkbox').forEach(function(cb) { cb.checked = true; });
        updateLabSelection();
    }

    function deselectAllLabTests() {
        document.querySelectorAll('.lab-checkbox').forEach(function(cb) { cb.checked = false; });
        updateLabSelection();
    }

    function updateLabSelection() {
        var checkboxes = document.querySelectorAll('.lab-checkbox:checked');
        var count = checkboxes.length;
        var total = 0;

        checkboxes.forEach(function(cb) {
            var item = cb.closest('.lab-test-item');
            if (item) {
                var priceText = item.querySelector('.lab-test-price')?.textContent || '';
                var price = parseFloat(priceText.replace(/[^0-9.]/g, ''));
                if (!isNaN(price)) total += price;
            }
        });

        var countEl = document.getElementById('labSelectedCount');
        if (countEl) countEl.textContent = count + ' selected';

        var totalPriceEl = document.getElementById('labTotalPrice');
        if (totalPriceEl) {
            totalPriceEl.textContent = 'Total: TSh ' + total.toLocaleString();
        }
    }

    // ================================================================
    // VISIT TYPE PRICE
    // ================================================================
    function updateVisitTypePrice() {
        var select = document.getElementById('visitTypeSelect');
        var priceDisplay = document.getElementById('visitTypePrice');
        if (!select || !priceDisplay) return;

        var selectedOption = select.options[select.selectedIndex];
        var price = selectedOption.dataset.price || 0;
        priceDisplay.textContent = 'Fee: TSh ' + parseInt(price).toLocaleString();
    }

    // ================================================================
    // SELECT PATIENT AND CHANGE
    // ================================================================
    function selectPatientAndChange(patientId) {
        var select = document.getElementById('patientSelect');
        if (select) {
            select.value = patientId;
            if (select.value != patientId) {
                window.location.href = 'assign_doctor.php?patient_id=' + patientId + '&change=1&branch_id=<?= $selected_branch_id ?>';
                return;
            }
            var event = new Event('change', { bubbles: true });
            select.dispatchEvent(event);
        }

        var formCard = document.getElementById('mainFormCard');
        if (formCard) formCard.classList.add('change-mode-active');

        var doctorSelect = document.getElementById('doctorSelect');
        if (doctorSelect) {
            setTimeout(function() {
                doctorSelect.focus();
                showToast('🔄 Change Mode', 'Patient selected. Choose new doctor from the dropdown.', 'warning');
            }, 500);
        }

        document.getElementById('mainFormCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ================================================================
    // MANUAL REFRESH
    // ================================================================
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        btn.innerHTML = '<span class="spinner"></span> Loading...';
        btn.disabled = true;
        setTimeout(function() { window.location.reload(); }, 800);
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
        formData.append('branch_id', '<?= $selected_branch_id ?>');

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) updateUI(data);
                isUpdating = false;
            })
            .catch(function() { isUpdating = false; });
    }

    function updateUI(data) {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

        ['pendingCount', 'pendingStat', 'pendingStatNumber', 'pendingCountHeader'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.textContent = data.pending_count;
        });

        ['assignedCount', 'assignedStat', 'assignedStatNumber', 'assignedListCount', 'assignedCountHeader'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.textContent = data.assigned_count;
        });

        var availableDoctorsStat = document.getElementById('availableDoctorsStat');
        if (availableDoctorsStat) availableDoctorsStat.textContent = data.total_doctors;

        var onlineDoctorsStatTime = document.getElementById('onlineDoctorsStatTime');
        if (onlineDoctorsStatTime) onlineDoctorsStatTime.textContent = '🟢 ' + data.online_count + ' online, ⚪ ' + data.offline_count + ' offline';

        ['pendingUpdateTime', 'assignedUpdateTime'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.textContent = 'Updated ' + timeStr;
        });

        var assignedListUpdate = document.getElementById('assignedListUpdate');
        if (assignedListUpdate) assignedListUpdate.textContent = '(Auto ' + timeStr + ')';

        // Update assigned table
        var assignedTableBody = document.getElementById('assignedPatientsTableBody');
        if (assignedTableBody && data.assigned_list_html !== undefined) {
            assignedTableBody.innerHTML = data.assigned_list_html;
        }

        // Update patient options (preserve selection)
        var patientSelect = document.getElementById('patientSelect');
        if (patientSelect && data.patient_options) {
            var currentValue = patientSelect.value;
            patientSelect.innerHTML = data.patient_options;
            if (currentValue) patientSelect.value = currentValue;
        }

        // Update doctor options
        var doctorSelect = document.getElementById('doctorSelect');
        if (doctorSelect && data.doctor_options) {
            var currentDocValue = doctorSelect.value;
            doctorSelect.innerHTML = '<option value="">-- Select Doctor --</option>' + data.doctor_options;
            if (currentDocValue) doctorSelect.value = currentDocValue;
        }
    }

    function startLiveUpdate() {
        if (updateInterval) clearInterval(updateInterval);
        setTimeout(fetchLiveData, 1500);
        updateInterval = setInterval(fetchLiveData, 5000);
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            if (updateInterval) { clearInterval(updateInterval); updateInterval = null; }
        } else {
            startLiveUpdate();
        }
    });

    // ================================================================
    // FORM SUBMIT (AJAX)
    // ================================================================
    document.getElementById('assignForm')?.addEventListener('submit', function(e) {
        var type = document.querySelector('select[name="assignment_type"]');
        if (type && type.value === 'lab') {
            return true;
        }

        e.preventDefault();

        var formData = new FormData(this);
        formData.append('action', 'change_doctor');

        var visitTypeSelect = document.getElementById('visitTypeSelect');
        var visitType = visitTypeSelect ? visitTypeSelect.value : 'general_consultation';
        formData.append('visit_type', visitType);

        var btn = document.getElementById('assignBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Assigning...';

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-user-md"></i> Assign / Change Doctor';

                if (data.success) {
                    var billMsg = '';
                    if (data.bill_sent_to_cashier) billMsg = ' 💰 Bill #' + data.bill.bill_number + ' sent to Cashier!';
                    showToast('✅ Success', data.message + billMsg, 'success');

                    if (data.patient_id) {
                        fetchLiveData();
                        setTimeout(function() {
                            window.location.href = 'assign_doctor.php?branch_id=<?= $selected_branch_id ?>';
                        }, 2500);
                    }
                } else {
                    showToast('❌ Error', data.message || 'Failed', 'error');
                }
            })
            .catch(function(error) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-user-md"></i> Assign / Change Doctor';
                showToast('❌ Error', 'Network error', 'error');
            });
    });

    // ================================================================
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        calculateBMI();
        updateSpO2Status();
        updateVisitTypePrice();
        setTimeout(startLiveUpdate, 2000);
    });

    console.log('%c👨‍⚕️ Braick - Assign / Change Doctor', 'font-size:18px; font-weight:bold; color:#2563EB;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🔧 FIXED: Assigned Patients query (latest visit)', 'font-size:13px; color:#DC2626; font-weight:bold;');
    console.log('%c🎨 Modern card CSS applied', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🌙 FULL DARK MODE works', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>