<?php
// ================================================================
// FILE: frontend/pages/reception/assign_doctor.php
// RECEPTION - ASSIGN / CHANGE / REASSIGN DOCTOR & LAB TESTS (V26)
// ✅ V26: Filter buttons zimepanuliwa kujaza width yote (row moja)
// ✅ V25: Total Patients kwenye page header
// ✅ V24: Saa+Tarehe kwenye kila patient row
// ✅ V23: SHARED HEADER/SIDEBAR + LAB TEST filter fix
// BRAICK DISPENSARY
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
    switch ($_SESSION['role']) {
        case 'doctor':     header('Location: ../doctor/dashboard.php');     break;
        case 'pharmacy':   header('Location: ../pharmacy/dashboard.php');   break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier':    header('Location: ../cashier/dashboard.php');    break;
        default:           header('Location: ../login.php');                break;
    }
    exit;
}

$user_id        = (int)($_SESSION['user_id'] ?? 1);
$full_name      = $_SESSION['full_name']   ?? 'Receptionist';
$branch_id      = (int)($_SESSION['branch_id'] ?? 1);
$branch_name    = $_SESSION['branch_name'] ?? 'Dodoma';
$username       = $_SESSION['username']    ?? 'reception';
$profile_pic    = $_SESSION['profile_pic'] ?? '';
$user_role      = $_SESSION['role']        ?? 'reception';

$show_assigned_by    = ($user_role === 'admin');
$user_branch_id      = $branch_id;
$selected_branch_id  = $branch_id;
$message             = '';
$message_type        = '';
$search              = isset($_GET['search']) ? trim($_GET['search']) : '';

$all_patients                 = [];
$unique_patients_for_dropdown = [];
$assigned_patients            = [];
$lab_only_patients            = [];
$waiting_patients             = [];
$prescribed_patients          = [];
$complete_patients            = [];
$doctors                      = [];
$online_doctors               = [];
$offline_doctors              = [];
$online_doctors_count         = 0;
$offline_doctors_count        = 0;
$total_doctors                = 0;
$visit_type_options           = [];
$assigned_count               = 0;
$lab_only_count               = 0;
$waiting_count                = 0;
$prescribed_count             = 0;
$complete_count               = 0;
$branch_patients_total        = 0;
$selected_patient_id          = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$latest_vital_signs           = null;
$selected_patient_data        = null;
$change_mode                  = isset($_GET['change']) && $_GET['change'] == 1;
$lab_tests_catalog            = [];
$unread_notifications         = 0;

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();

    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) { $unread_notifications = 0; }

    // Consultation services
    $stmt = $db->prepare("
        SELECT id, service_name, description, price, unit, is_active
        FROM services
        WHERE category_id = 2 AND is_active = 1
        AND (branch_id = ? OR branch_id IS NULL)
        ORDER BY
            CASE
                WHEN service_name LIKE '%New Patient%' THEN 0
                WHEN service_name LIKE '%General%'     THEN 1
                WHEN service_name LIKE '%Emergency%'   THEN 2
                WHEN service_name LIKE '%Specialist%'  THEN 3
                WHEN service_name LIKE '%Follow%'      THEN 4
                ELSE 5
            END, service_name
    ");
    $stmt->execute([$selected_branch_id]);
    $consultation_services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $visit_type_options = [];
    $default_service_id = null;

    if (!empty($consultation_services)) {
        foreach ($consultation_services as $service) {
            $service_id   = $service['id'];
            $service_name = $service['service_name'];
            $price        = (float)$service['price'];

            $icon = '🏥';
            if (strpos(strtolower($service_name), 'new') !== false)          $icon = '🆕';
            elseif (strpos(strtolower($service_name), 'follow') !== false)   $icon = '🔄';
            elseif (strpos(strtolower($service_name), 'emergency') !== false) $icon = '🚨';
            elseif (strpos(strtolower($service_name), 'specialist') !== false) $icon = '👨‍⚕️';
            elseif (strpos(strtolower($service_name), 'general') !== false)   $icon = '🏥';

            $visit_type_options[$service_id] = [
                'id'           => $service_id,
                'service_name' => $service_name,
                'price'        => $price,
                'unit'         => $service['unit'] ?? 'each',
                'description'  => $service['description'] ?? '',
                'is_active'    => $service['is_active'],
                'icon'         => $icon
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

    // Lab tests catalog
    $stmt = $db->prepare("SELECT id, test_name, price, category FROM lab_tests_catalog WHERE is_active = 1 ORDER BY category, test_name");
    $stmt->execute();
    $lab_tests_catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================
    // MAIN QUERY
    // ============================================================
    $query = "
        SELECT
            p.id, p.full_name, p.patient_id, p.phone, p.gender, p.date_of_birth,
            p.blood_group, p.allergies, p.assigned_doctor_id, p.created_at as patient_created_at,
            v.id as visit_id, v.status as visit_status, v.visit_number, v.visit_type,
            v.service_id, v.consultation_fee, v.created_at as visit_created_at, v.updated_at as visit_updated_at,
            v.doctor_id as visit_doctor_id, v.assigned_by_id, v.assigned_at,
            u.full_name as assigned_doctor_name,
            u.is_online as assigned_doctor_online,
            u_assigned.full_name as assigned_by_name,
            u_assigned.role as assigned_by_role,
            DATEDIFF(NOW(), p.created_at) as patient_days,
            (SELECT COUNT(*) FROM lab_tests lt
             WHERE lt.patient_id = p.id
             AND lt.status IN ('pending','in_progress')
             AND lt.visit_id = v.id) as pending_lab_tests_count
        FROM patients p
        LEFT JOIN visits v ON p.id = v.patient_id
            AND v.status IN ('new','pending','assigned','with_doctor','lab_test','waiting','prescribed','completed','cancelled')
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
    $all_patients = $stmt->fetchAll();

    // Dedup for dropdown
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

    // ============================================================
    // CATEGORIZE (V26: Lab Test = pending/in_progress only)
    // ============================================================
    foreach ($all_patients as $patient) {
        $patient['has_active_visit'] = !empty($patient['visit_id']);
        $patient['patient_days']     = isset($patient['patient_days']) ? (int)$patient['patient_days'] : 0;

        if ($patient['has_active_visit']) {
            $status            = $patient['visit_status'];
            $pending_lab_count = (int)($patient['pending_lab_tests_count'] ?? 0);

            if ($status === 'lab_test' && $pending_lab_count > 0) {
                $lab_only_patients[] = $patient;
            } elseif ($status === 'lab_test' && $pending_lab_count === 0) {
                if (!empty($patient['assigned_doctor_name'])) {
                    $assigned_patients[] = $patient;
                }
            } elseif ($status === 'waiting') {
                $waiting_patients[] = $patient;
            } elseif ($status === 'prescribed') {
                $prescribed_patients[] = $patient;
            } elseif (in_array($status, ['assigned','with_doctor'])) {
                if (!empty($patient['assigned_doctor_name'])) {
                    $assigned_patients[] = $patient;
                }
            } elseif (in_array($status, ['completed','cancelled'])) {
                $complete_patients[] = $patient;
            }
        }
    }

    $assigned_count   = count($assigned_patients);
    $lab_only_count   = count($lab_only_patients);
    $waiting_count    = count($waiting_patients);
    $prescribed_count = count($prescribed_patients);
    $complete_count   = count($complete_patients);

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

    // Doctors
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

    // ============================================================
    // HELPERS (guarded)
    // ============================================================
    if (!function_exists('createLabOnlyBill')) {
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
            if ($total_lab_fee <= 0) return ['status' => 'error', 'message' => 'No lab tests with price > 0'];

            $stmt = $db->prepare("SELECT id, bill_number FROM bills WHERE visit_id = ? AND status IN ('pending','partial') LIMIT 1");
            $stmt->execute([$visit_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $stmt = $db->prepare("UPDATE bills SET subtotal = subtotal + ?, total_amount = total_amount + ?, balance = balance + ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$total_lab_fee, $total_lab_fee, $total_lab_fee, $existing['id']]);
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

            $stmt = $db->prepare("UPDATE visits SET lab_fees_total = COALESCE(lab_fees_total, 0) + ? WHERE id = ?");
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

            return ['status' => 'created', 'message' => 'Lab bill created!', 'bill_id' => $bill_id, 'bill_number' => $bill_number, 'total_lab_fee' => $total_lab_fee];
        }
    }

    if (!function_exists('createVisitBill')) {
        function createVisitBill($db, $patient_id, $visit_id, $service_name, $consultation_fee, $user_id, $branch_id) {
            if ($consultation_fee <= 0) return ['status' => 'error', 'message' => 'Consultation fee is 0'];

            $stmt = $db->prepare("SELECT id, bill_number FROM bills WHERE visit_id = ? AND status IN ('pending','partial') LIMIT 1");
            $stmt->execute([$visit_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $stmt = $db->prepare("UPDATE bills SET subtotal = subtotal + ?, total_amount = total_amount + ?, balance = balance + ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$consultation_fee, $consultation_fee, $consultation_fee, $existing['id']]);
                $bill_id = $existing['id'];
                $bill_number = $existing['bill_number'];

                $stmt = $db->prepare("INSERT INTO bill_items (bill_id, patient_id, branch_id, item_type, item_name, quantity, unit_price, total_price, status, created_at) VALUES (?, ?, ?, 'consultation', ?, 1, ?, ?, 'pending', NOW())");
                $stmt->execute([$bill_id, $patient_id, $branch_id, $service_name, $consultation_fee, $consultation_fee]);
                return ['status' => 'updated', 'message' => 'Consultation fee added', 'bill_id' => $bill_id, 'bill_number' => $bill_number, 'total_consultation_fee' => $consultation_fee];
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
                    $stmt->execute([$cashier['id'], $branch_id, "Consultation bill #$bill_number (TSh " . number_format($consultation_fee) . ") for patient ID #$patient_id", "cashier_dashboard.php"]);
                }
            } catch (Exception $e) {}

            return ['status' => 'created', 'message' => 'Consultation bill created!', 'bill_id' => $bill_id, 'bill_number' => $bill_number, 'total_consultation_fee' => $consultation_fee];
        }
    }

    if (!function_exists('deleteOldVisitData')) {
        function deleteOldVisitData($db, $visit_id) {
            $deleted = [
                'payments' => 0, 'bill_items' => 0, 'bills' => 0,
                'prescription_items' => 0, 'prescriptions' => 0,
                'lab_tests' => 0, 'vital_signs' => 0, 'procedures' => 0,
                'visits' => 0
            ];

            try { $stmt = $db->prepare("DELETE p FROM payments p INNER JOIN bills b ON p.bill_id = b.id WHERE b.visit_id = ?"); $stmt->execute([$visit_id]); $deleted['payments'] = $stmt->rowCount(); } catch (Exception $e) {}
            try { $stmt = $db->prepare("DELETE bi FROM bill_items bi INNER JOIN bills b ON bi.bill_id = b.id WHERE b.visit_id = ?"); $stmt->execute([$visit_id]); $deleted['bill_items'] = $stmt->rowCount(); } catch (Exception $e) {}
            try { $stmt = $db->prepare("DELETE FROM bills WHERE visit_id = ?"); $stmt->execute([$visit_id]); $deleted['bills'] = $stmt->rowCount(); } catch (Exception $e) {}
            try { $stmt = $db->prepare("DELETE pi FROM prescription_items pi INNER JOIN prescriptions p ON pi.prescription_id = p.id WHERE p.visit_id = ?"); $stmt->execute([$visit_id]); $deleted['prescription_items'] = $stmt->rowCount(); } catch (Exception $e) {}
            try { $stmt = $db->prepare("DELETE FROM prescriptions WHERE visit_id = ?"); $stmt->execute([$visit_id]); $deleted['prescriptions'] = $stmt->rowCount(); } catch (Exception $e) {}
            try { $stmt = $db->prepare("DELETE FROM lab_tests WHERE visit_id = ?"); $stmt->execute([$visit_id]); $deleted['lab_tests'] = $stmt->rowCount(); } catch (Exception $e) {}
            try { $stmt = $db->prepare("DELETE FROM vital_signs WHERE visit_id = ?"); $stmt->execute([$visit_id]); $deleted['vital_signs'] = $stmt->rowCount(); } catch (Exception $e) {}
            try { $stmt = $db->prepare("DELETE FROM procedures WHERE visit_id = ?"); $stmt->execute([$visit_id]); $deleted['procedures'] = $stmt->rowCount(); } catch (Exception $e) {}
            try { $stmt = $db->prepare("DELETE FROM visits WHERE id = ?"); $stmt->execute([$visit_id]); $deleted['visits'] = $stmt->rowCount(); } catch (Exception $e) {}

            return $deleted;
        }
    }

    // ============================================================
    // AJAX HANDLERS
    // ============================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // ============================================================
        // GET FILTERED LIST
        // ============================================================
        if ($action === 'get_filtered_list') {
            header('Content-Type: application/json');
            $status   = $_POST['status'] ?? 'assigned';
            $filtered = [];

            if ($status === 'assigned')        $filtered = $assigned_patients;
            elseif ($status === 'lab_test')    $filtered = $lab_only_patients;
            elseif ($status === 'prescribed')  $filtered = $prescribed_patients;
            elseif ($status === 'waiting')     $filtered = $waiting_patients;
            elseif ($status === 'complete')    $filtered = $complete_patients;

            if (empty($filtered)) {
                $icons = ['assigned'=>'fa-user-check','lab_test'=>'fa-flask','prescribed'=>'fa-prescription','waiting'=>'fa-clock','complete'=>'fa-check-double'];
                $msgs  = [
                    'assigned'   => 'No patients currently assigned to a doctor',
                    'lab_test'   => 'No pending or in-progress lab tests',
                    'prescribed' => 'No prescribed patients',
                    'waiting'    => 'No waiting patients',
                    'complete'   => 'No completed visits'
                ];
                echo json_encode(['success' => true, 'html' => '<div class="empty-list-state"><i class="fas ' . ($icons[$status] ?? 'fa-inbox') . '"></i><p>' . ($msgs[$status] ?? 'No patients found') . '</p></div>', 'count' => 0]);
                exit;
            }

            $html = '<div class="patient-list-table-wrap"><table class="patient-list-table">';
            $html .= '<thead><tr>';
            $html .= '<th>Patient / Service</th>';
            $html .= '<th>Patient ID</th>';
            $html .= '<th>Date & Time</th>';
            $html .= '<th>Doctor</th>';
            if ($show_assigned_by) $html .= '<th>Assigned By</th>';
            $html .= '<th>Status</th>';
            if ($status === 'assigned') $html .= '<th style="text-align:center;">Actions</th>';
            $html .= '</tr></thead><tbody>';

            foreach ($filtered as $patient) {
                // ✅ SAA + TAREHE
                $base_date = !empty($patient['visit_created_at']) ? $patient['visit_created_at'] : ($patient['patient_created_at'] ?? null);
                $date_full = $base_date ? date('d/m/Y', strtotime($base_date)) : '—';
                $time_full = $base_date ? date('h:i A', strtotime($base_date)) : '—';

                $assigned_days = 0;
                if (!empty($patient['visit_created_at'])) {
                    $assigned_days = (int)floor((time() - strtotime($patient['visit_created_at'])) / 86400);
                }
                $days_text = $assigned_days > 0
                    ? '<span class="days-badge">' . $assigned_days . 'd</span>'
                    : '<span class="days-badge new">New</span>';

                $visit_number_display = !empty($patient['visit_number'])
                    ? '<span class="visit-number-pill"><i class="fas fa-hashtag"></i> ' . htmlspecialchars($patient['visit_number']) . '</span>'
                    : '';

                $doctor_html = !empty($patient['assigned_doctor_name'])
                    ? '<div class="doctor-pill"><i class="fas fa-user-md"></i><span>Dr. ' . htmlspecialchars($patient['assigned_doctor_name']) . '</span><span class="doctor-status">' . ($patient['assigned_doctor_online'] == 1 ? '🟢' : '⚪') . '</span></div>'
                    : '<span class="no-doctor-tag"><i class="fas fa-minus-circle"></i> No doctor</span>';

                $assigned_by_html = '';
                if ($show_assigned_by) {
                    if (!empty($patient['assigned_by_name'])) {
                        $role_icon  = 'fa-user';
                        $role_color = '#2563EB';
                        $role_bg    = '#EFF6FF';
                        $role       = strtolower($patient['assigned_by_role'] ?? '');
                        if ($role === 'reception')     { $role_icon = 'fa-user-tie';    $role_color = '#7C3AED'; $role_bg = '#EDE9FE'; }
                        elseif ($role === 'admin')     { $role_icon = 'fa-user-shield'; $role_color = '#D97706'; $role_bg = '#FEF3C7'; }
                        elseif ($role === 'doctor')    { $role_icon = 'fa-user-md';     $role_color = '#059669'; $role_bg = '#D1FAE5'; }

                        $assigned_date = !empty($patient['assigned_at']) ? date('d/m H:i', strtotime($patient['assigned_at'])) : '';

                        $assigned_by_html = '<div class="assigned-by-cell">';
                        $assigned_by_html .= '<span class="role-badge" style="background:' . $role_bg . ';color:' . $role_color . ';">';
                        $assigned_by_html .= '<i class="fas ' . $role_icon . '"></i> ' . htmlspecialchars($patient['assigned_by_name']);
                        $assigned_by_html .= '</span>';
                        if ($assigned_date) $assigned_by_html .= '<span class="assigned-date"><i class="fas fa-clock"></i> ' . $assigned_date . '</span>';
                        $assigned_by_html .= '</div>';
                    } else {
                        $assigned_by_html = '<span class="empty-cell">—</span>';
                    }
                }

                $status_badge = '';
                if ($status === 'assigned')       $status_badge = '<span class="status-pill assigned"><i class="fas fa-check-circle"></i> Assigned</span>';
                elseif ($status === 'lab_test')   $status_badge = '<span class="status-pill lab_test"><i class="fas fa-flask"></i> Lab Test</span>';
                elseif ($status === 'prescribed') $status_badge = '<span class="status-pill prescribed"><i class="fas fa-prescription"></i> Prescribed</span>';
                elseif ($status === 'waiting')    $status_badge = '<span class="status-pill waiting"><i class="fas fa-clock"></i> Waiting</span>';
                elseif ($status === 'complete')   $status_badge = '<span class="status-pill complete"><i class="fas fa-check-double"></i> Complete</span>';

                $row_id = 'visit-row-' . ($patient['visit_id'] ?? 'p' . $patient['id']);

                $html .= '<tr id="' . $row_id . '">';
                $html .= '<td>';
                $html .= '<div class="patient-name-cell"><i class="fas fa-user-circle"></i> <strong>' . htmlspecialchars($patient['full_name']) . '</strong> ' . $days_text . '</div>';
                $html .= $visit_number_display;
                $html .= '<div class="patient-service-cell"><i class="fas fa-stethoscope"></i> ' . htmlspecialchars($patient['visit_type'] ?? 'Consultation') . '</div>';
                $html .= '</td>';
                $html .= '<td><span class="patient-id-pill">' . htmlspecialchars($patient['patient_id'] ?? 'N/A') . '</span></td>';

                $html .= '<td>';
                $html .= '<div class="datetime-cell">';
                $html .= '<span class="dt-date"><i class="fas fa-calendar-day"></i> ' . $date_full . '</span>';
                $html .= '<span class="dt-time"><i class="fas fa-clock"></i> ' . $time_full . '</span>';
                $html .= '</div>';
                $html .= '</td>';

                $html .= '<td>' . $doctor_html . '</td>';
                if ($show_assigned_by) $html .= '<td>' . $assigned_by_html . '</td>';
                $html .= '<td>' . $status_badge . '</td>';

                if ($status === 'assigned') {
                    $html .= '<td class="actions-cell"><div class="action-group">';
                    if (!empty($patient['visit_id']) && !empty($patient['assigned_doctor_name'])) {
                        $html .= '<button onclick="reassignDoctor(' . $patient['id'] . ', ' . $patient['visit_id'] . ')" class="btn-action-mini reassign" title="Reassign"><i class="fas fa-user-minus"></i> <span class="btn-text">Reassign</span></button>';
                        $html .= '<button onclick="changeDoctor(' . $patient['id'] . ')" class="btn-action-mini change" title="Change Doctor"><i class="fas fa-sync-alt"></i> <span class="btn-text">Change</span></button>';
                    } else {
                        $html .= '<button onclick="quickAssign(' . $patient['id'] . ')" class="btn-action-mini assign" title="Assign"><i class="fas fa-user-plus"></i> <span class="btn-text">Assign</span></button>';
                    }
                    $html .= '</div></td>';
                }

                $html .= '</tr>';
            }

            $html .= '</tbody></table></div>';
            echo json_encode(['success' => true, 'html' => $html, 'count' => count($filtered)]);
            exit;
        }

        // ============================================================
        // REASSIGN DOCTOR
        // ============================================================
        if ($action === 'reassign_doctor') {
            header('Content-Type: application/json');
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            $visit_id   = (int)($_POST['visit_id'] ?? 0);

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
                    $stmt = $db->prepare("SELECT id, visit_number, doctor_id, status FROM visits WHERE patient_id = ? AND status IN ('assigned','with_doctor','waiting') AND branch_id = ? ORDER BY id DESC LIMIT 1");
                    $stmt->execute([$patient_id, $selected_branch_id]);
                }
                $visit = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$visit) throw new Exception('No active visit found');

                $visit_id_target     = (int)$visit['id'];
                $visit_number_target = $visit['visit_number'];

                $deleted_counts = deleteOldVisitData($db, $visit_id_target);

                if ($deleted_counts['visits'] === 0) throw new Exception('Failed to delete visit');

                $stmt = $db->prepare("
                    SELECT COUNT(*) FROM visits
                    WHERE patient_id = ? AND branch_id = ?
                    AND status IN ('assigned','with_doctor','waiting')
                    AND doctor_id IS NOT NULL
                ");
                $stmt->execute([$patient_id, $selected_branch_id]);
                $remaining_doctor_visits = (int)$stmt->fetchColumn();

                if ($remaining_doctor_visits === 0) {
                    $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL WHERE id = ?");
                    $stmt->execute([$patient_id]);
                }

                $db->commit();

                $msg_parts = ["Visit #$visit_number_target deleted"];
                if ($deleted_counts['bills'] > 0)         $msg_parts[] = $deleted_counts['bills'] . " bill(s)";
                if ($deleted_counts['bill_items'] > 0)    $msg_parts[] = $deleted_counts['bill_items'] . " item(s)";
                if ($deleted_counts['payments'] > 0)      $msg_parts[] = $deleted_counts['payments'] . " payment(s)";
                if ($deleted_counts['prescriptions'] > 0) $msg_parts[] = $deleted_counts['prescriptions'] . " prescription(s)";
                if ($deleted_counts['lab_tests'] > 0)     $msg_parts[] = $deleted_counts['lab_tests'] . " lab test(s)";
                if ($deleted_counts['vital_signs'] > 0)   $msg_parts[] = $deleted_counts['vital_signs'] . " vital sign(s)";
                if ($deleted_counts['procedures'] > 0)    $msg_parts[] = $deleted_counts['procedures'] . " procedure(s)";

                echo json_encode([
                    'success' => true,
                    'message' => 'Reassign OK! Patient hana doctor sasa. Deleted: ' . implode(', ', $msg_parts),
                    'reload'  => true,
                    'deleted' => $deleted_counts
                ]);
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
        }

        // ============================================================
        // GET PATIENT DETAILS
        // ============================================================
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
                        v.id as visit_id, v.status as visit_status, v.visit_type, v.created_at as visit_created_at,
                        v.visit_number,
                        u.full_name as assigned_doctor_name, u.is_online as assigned_doctor_online,
                        DATEDIFF(NOW(), p.created_at) as patient_days
                    FROM patients p
                    LEFT JOIN visits v ON p.id = v.patient_id AND v.status NOT IN ('completed','cancelled')
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
        // CHANGE DOCTOR
        // ============================================================
        if ($action === 'change_doctor') {
            header('Content-Type: application/json');

            $patient_id      = (int)($_POST['patient_id'] ?? 0);
            $doctor_id       = (int)($_POST['doctor_id'] ?? 0);
            $service_id      = (int)($_POST['service_id'] ?? 0);
            $symptoms        = trim($_POST['symptoms'] ?? '');
            $notes           = trim($_POST['notes'] ?? '');
            $lab_test_ids    = isset($_POST['lab_test_ids']) ? $_POST['lab_test_ids'] : [];
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

                $deleted_old = [
                    'payments' => 0, 'bill_items' => 0, 'bills' => 0,
                    'prescription_items' => 0, 'prescriptions' => 0,
                    'lab_tests' => 0, 'vital_signs' => 0, 'procedures' => 0,
                    'visits' => 0
                ];

                $stmt = $db->prepare("
                    SELECT id, visit_number, status, doctor_id
                    FROM visits
                    WHERE patient_id = ? AND branch_id = ?
                    AND status NOT IN ('completed','cancelled')
                    ORDER BY id DESC
                ");
                $stmt->execute([$patient_id, $selected_branch_id]);
                $old_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($old_visits as $old_visit) {
                    $deleted = deleteOldVisitData($db, (int)$old_visit['id']);
                    foreach ($deleted as $key => $count) {
                        $deleted_old[$key] += $count;
                    }
                }

                $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL WHERE id = ?");
                $stmt->execute([$patient_id]);

                $service_name     = 'General Consultation';
                $consultation_fee = 0;

                if ($service_id > 0 && !$is_lab_only) {
                    $stmt = $db->prepare("SELECT id, service_name, price FROM services WHERE id = ? AND is_active = 1");
                    $stmt->execute([$service_id]);
                    $service = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($service) {
                        $service_name     = $service['service_name'];
                        $consultation_fee = (float)$service['price'];
                    }
                } elseif ($is_lab_only) {
                    $service_name = 'Lab Tests Only';
                }

                $doctor_name   = 'No Doctor Assigned';
                $doctor_online = 0;
                if ($doctor_id > 0) {
                    $stmt = $db->prepare("SELECT full_name, is_online FROM users WHERE id = ? AND status = 'active'");
                    $stmt->execute([$doctor_id]);
                    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($doctor) {
                        $doctor_name   = $doctor['full_name'];
                        $doctor_online = $doctor['is_online'] ?? 0;
                    }
                }

                $visit_type_to_store = $is_lab_only ? 'Lab Tests Only' : $service_name;
                $service_id_to_store = $is_lab_only ? null : ($service_id > 0 ? $service_id : null);
                $doctor_id_to_store  = ($is_lab_only) ? null : ($doctor_id > 0 ? $doctor_id : null);
                $visit_status        = ($is_lab_only && !empty($lab_test_ids)) ? 'lab_test' : ($is_lab_only ? 'pending' : 'assigned');

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

                $bill_created  = false;
                $bill_number   = null;
                $total_lab_fee = 0;

                if ($is_lab_only && !empty($lab_test_ids)) {
                    $lab_bill = createLabOnlyBill($db, $patient_id, $visit_id, $lab_test_ids, $user_id, $selected_branch_id);
                    if ($lab_bill && $lab_bill['status'] !== 'error') {
                        $bill_created  = true;
                        $bill_number   = $lab_bill['bill_number'];
                        $total_lab_fee = $lab_bill['total_lab_fee'] ?? 0;
                    }
                } elseif (!$is_lab_only && $consultation_fee > 0 && $doctor_id > 0) {
                    $bill_result = createVisitBill($db, $patient_id, $visit_id, $service_name, $consultation_fee, $user_id, $selected_branch_id);
                    if ($bill_result && $bill_result['status'] !== 'error') {
                        $bill_created = true;
                        $bill_number  = $bill_result['bill_number'];
                    }
                }

                $lab_created = false;
                if (!empty($lab_test_ids)) {
                    $test_ids_imploded = implode(',', array_map('intval', $lab_test_ids));
                    $stmt = $db->prepare("SELECT id, test_name, price FROM lab_tests_catalog WHERE id IN ($test_ids_imploded)");
                    $stmt->execute();
                    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($tests as $test) {
                        $stmt = $db->prepare("INSERT INTO lab_tests (visit_id, patient_id, doctor_id, test_id, test_name, test_price, status, branch_id, requested_by_id, requested_at, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())");
                        $stmt->execute([$visit_id, $patient_id, $is_lab_only ? null : ($doctor_id > 0 ? $doctor_id : null), $test['id'], $test['test_name'], $test['price'], $selected_branch_id, $user_id]);
                        $lab_created = true;
                    }
                }

                $temperature       = $_POST['temperature'] ?? null;
                $bp_systolic       = $_POST['bp_systolic'] ?? null;
                $bp_diastolic      = $_POST['bp_diastolic'] ?? null;
                $pulse_rate        = $_POST['pulse_rate'] ?? null;
                $weight            = $_POST['weight'] ?? null;
                $height            = $_POST['height'] ?? null;
                $oxygen_saturation = isset($_POST['oxygen_saturation']) && $_POST['oxygen_saturation'] !== '' ? (int)$_POST['oxygen_saturation'] : null;
                $vital_notes       = trim($_POST['vital_notes'] ?? '');

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
                if ($bill_created && $bill_number) $bill_message = ' 💰 New Bill #' . $bill_number . ' sent to Cashier!';

                $lab_text = '';
                if ($lab_created) {
                    $lab_text = ' 🧪 ' . count($lab_test_ids) . ' lab test(s) requested!';
                    if ($total_lab_fee > 0) $lab_text .= ' (TSh ' . number_format($total_lab_fee, 0) . ')';
                }

                $doctor_text = '';
                if ($doctor_id > 0 && !$is_lab_only) {
                    $online_text = $doctor_online == 1 ? '🟢 Online' : '⚪ Offline';
                    $doctor_text = "Doctor <strong>$doctor_name</strong> ($online_text) assigned - $service_name";
                } elseif ($is_lab_only && !empty($lab_test_ids)) {
                    $doctor_text = "🧪 Lab tests requested - No doctor assigned";
                } else {
                    $doctor_text = "✅ Patient processed";
                }

                $deleted_msg = '';
                if ($deleted_old['visits'] > 0) {
                    $deleted_msg = ' 🗑️ Deleted old: ' . $deleted_old['visits'] . ' visit(s)';
                    if ($deleted_old['bills'] > 0)         $deleted_msg .= ', ' . $deleted_old['bills'] . ' bill(s)';
                    if ($deleted_old['bill_items'] > 0)    $deleted_msg .= ', ' . $deleted_old['bill_items'] . ' item(s)';
                    if ($deleted_old['payments'] > 0)      $deleted_msg .= ', ' . $deleted_old['payments'] . ' payment(s)';
                    if ($deleted_old['prescriptions'] > 0) $deleted_msg .= ', ' . $deleted_old['prescriptions'] . ' prescription(s)';
                    if ($deleted_old['lab_tests'] > 0)     $deleted_msg .= ', ' . $deleted_old['lab_tests'] . ' lab test(s)';
                }

                $response['success']              = true;
                $response['message']              = "✅ $doctor_text! New Visit: $visit_number" . $bill_message . $lab_text . $deleted_msg;
                $response['patient_id']           = $patient_id;
                $response['visit_number']         = $visit_number;
                $response['visit_id']             = $visit_id;
                $response['bill_sent_to_cashier'] = $bill_created;
                $response['bill_number']          = $bill_number;
                $response['deleted_old']          = $deleted_old;

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

$common_symptoms = ['Fever','Headache','Cough','Sore Throat','Body Pain','Fatigue','Nausea','Vomiting','Diarrhea','Chest Pain','Shortness of Breath','Abdominal Pain','Dizziness','Rash','Swelling'];

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic)
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once __DIR__ . '/../../components/reception_header.php';
include_once __DIR__ . '/../../components/reception_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= (isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true') ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Doctor V26 - Braick Dispensary</title>

    <link rel="icon" href="<?= htmlspecialchars($logo_path) ?>" type="image/png">
    <link rel="shortcut icon" href="<?= htmlspecialchars($logo_path) ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 50%, #1E40AF 100%);
            border-radius: 20px;
            padding: 22px 30px;
            margin-bottom: 16px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 10px 40px rgba(37, 99, 235, 0.3);
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute;
            top: -60%; right: -10%;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .page-header .page-title {
            color: #fff;
            font-size: 1.4rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            letter-spacing: -0.5px;
        }
        .page-header .page-title i { font-size: 1.5rem; }
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.78rem;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 6px;
        }
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.62rem;
            font-weight: 700;
            text-transform: uppercase;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.15);
        }
        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: #fff;
            padding: 3px 11px;
            border-radius: 20px;
            font-size: 0.62rem;
            font-weight: 600;
            backdrop-filter: blur(8px);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 8px 16px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all .3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(8px);
            position: relative;
            z-index: 1;
            cursor: pointer;
        }
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.28);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            color: #fff;
        }

        /* ================================================================
           ✅ FILTER BAR — FULL WIDTH, ROW MOJA
           Kila button inajaza nafasi sawa (flex: 1)
           ================================================================ */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
            overflow-x: auto;
            overflow-y: hidden;
            background: var(--bg-card);
            padding: 12px 16px;
            border-radius: 14px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 1400px;
            margin: 0 auto 16px;
            position: relative;
        }
        .filter-bar::-webkit-scrollbar { height: 3px; }
        .filter-bar::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }
        .filter-bar::-webkit-scrollbar-track { background: transparent; }

        .filter-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 16px;
            right: 16px;
            height: 3px;
            background: linear-gradient(90deg, #2563EB, #7C3AED, #059669, #D97706, #DC2626);
            border-radius: 0 0 3px 3px;
        }

        .filter-bar .filter-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-secondary);
            margin-right: 4px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            flex-shrink: 0;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding-right: 12px;
            border-right: 1px solid var(--border-color);
        }

        /* ✅ Filter buttons — kubwa, flex: 1, kujaza card */
        .filter-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 10px 18px;
            border-radius: 24px;
            font-size: 0.78rem;
            font-weight: 700;
            border: 2px solid var(--border-color);
            background: var(--bg-body);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all .25s ease;
            font-family: inherit;
            flex: 1 1 0;
            min-width: 110px;
            white-space: nowrap;
            line-height: 1;
        }
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        }
        .filter-btn.active {
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            color: #fff;
            border-color: #2563EB;
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.35);
        }
        .filter-btn.active[data-status="lab_test"] {
            background: linear-gradient(135deg, #7C3AED, #5B21B6);
            border-color: #7C3AED;
            box-shadow: 0 6px 18px rgba(124, 58, 237, 0.35);
        }
        .filter-btn.active[data-status="prescribed"] {
            background: linear-gradient(135deg, #059669, #047857);
            border-color: #059669;
            box-shadow: 0 6px 18px rgba(5, 150, 105, 0.35);
        }
        .filter-btn.active[data-status="waiting"] {
            background: linear-gradient(135deg, #D97706, #B45309);
            border-color: #D97706;
            box-shadow: 0 6px 18px rgba(217, 119, 6, 0.35);
        }
        .filter-btn.active[data-status="complete"] {
            background: linear-gradient(135deg, #059669, #047857);
            border-color: #059669;
            box-shadow: 0 6px 18px rgba(5, 150, 105, 0.35);
        }
        .filter-btn i { font-size: 0.75rem; }

        .filter-count {
            background: rgba(255,255,255,0.3);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.68rem;
            font-weight: 800;
            min-width: 24px;
            text-align: center;
            font-family: 'JetBrains Mono', monospace;
            line-height: 1.4;
        }
        .filter-btn:not(.active) .filter-count {
            background: var(--border-color);
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .filter-bar { padding: 10px 12px; gap: 6px; }
            .filter-btn { padding: 8px 14px; font-size: 0.7rem; min-width: 90px; }
            .filter-count { font-size: 0.62rem; min-width: 20px; padding: 1px 6px; }
        }

        /* ================================================================
           PATIENT LIST TABLE
           ================================================================ */
        .patient-list-table-wrap {
            overflow-x: auto;
            border-radius: 14px;
            border: 1px solid var(--border-color);
        }
        .patient-list-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            background: var(--bg-card);
        }
        .patient-list-table thead tr {
            background: linear-gradient(135deg, #F8FAFC, #EFF6FF);
            border-bottom: 2px solid var(--border-color);
        }
        [data-theme="dark"] .patient-list-table thead tr {
            background: linear-gradient(135deg, #1E293B, #0F172A);
        }
        .patient-list-table thead th {
            padding: 12px 14px;
            text-align: left;
            font-weight: 700;
            font-size: 0.66rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            letter-spacing: 0.06em;
            white-space: nowrap;
        }
        .patient-list-table tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s ease;
        }
        .patient-list-table tbody tr:hover { background: var(--primary-bg); }
        .patient-list-table tbody tr:last-child { border-bottom: none; }
        .patient-list-table tbody td {
            padding: 12px 14px;
            vertical-align: middle;
        }

        .patient-name-cell {
            font-weight: 700;
            font-size: 0.88rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
        }
        .patient-name-cell i { color: var(--primary); font-size: 0.88rem; }

        .patient-service-cell {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 4px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .patient-service-cell i { font-size: 0.62rem; opacity: 0.75; }

        .visit-number-pill {
            display: inline-block;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.62rem;
            font-weight: 600;
            color: var(--text-secondary);
            background: var(--bg-body);
            padding: 2px 8px;
            border-radius: 6px;
            margin-top: 4px;
            border: 1px solid var(--border-color);
        }
        .visit-number-pill i { font-size: 0.55rem; opacity: 0.7; }

        .patient-id-pill {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--primary);
            background: var(--primary-bg);
            padding: 4px 10px;
            border-radius: 8px;
            display: inline-block;
            border: 1px solid rgba(11, 94, 215, 0.15);
            white-space: nowrap;
        }
        [data-theme="dark"] .patient-id-pill {
            background: rgba(59, 130, 246, 0.15);
            border-color: rgba(59, 130, 246, 0.3);
            color: #93C5FD;
        }

        /* ✅ DATE/TIME CELL */
        .datetime-cell {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .datetime-cell .dt-date,
        .datetime-cell .dt-time {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.72rem;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
            white-space: nowrap;
        }
        .datetime-cell .dt-date { color: var(--text-primary); }
        .datetime-cell .dt-time { color: var(--primary); font-weight: 700; }
        .datetime-cell i { font-size: 0.65rem; opacity: 0.8; }

        [data-theme="dark"] .datetime-cell .dt-time { color: #93C5FD; }

        .doctor-pill {
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--primary-bg);
            padding: 5px 12px;
            border-radius: 14px;
            border: 1px solid var(--primary-light);
            font-weight: 600;
            color: var(--primary);
            white-space: nowrap;
        }
        [data-theme="dark"] .doctor-pill {
            background: rgba(59, 130, 246, 0.15);
            border-color: rgba(96, 165, 250, 0.3);
            color: #93C5FD;
        }
        .doctor-pill i { font-size: 0.72rem; }
        .doctor-status { font-size: 0.68rem; margin-left: 2px; }

        .no-doctor-tag {
            font-size: 0.72rem;
            color: var(--text-secondary);
            font-style: italic;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background: var(--bg-body);
            border-radius: 10px;
            border: 1px dashed var(--border-color);
            white-space: nowrap;
        }

        .assigned-by-cell { display: flex; flex-direction: column; gap: 4px; }
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 11px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            width: fit-content;
            border: 1px solid;
            white-space: nowrap;
        }
        .role-badge i { font-size: 0.65rem; }
        .assigned-date {
            font-size: 0.62rem;
            color: var(--text-secondary);
            margin-left: 4px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: 'JetBrains Mono', monospace;
        }
        .assigned-date i { font-size: 0.55rem; }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 11px;
            border-radius: 10px;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }
        .status-pill.assigned    { background: #D1FAE5; color: #059669; border: 1px solid rgba(5, 150, 105, 0.3); }
        .status-pill.lab_test    { background: #EDE9FE; color: #7C3AED; border: 1px solid rgba(124, 58, 237, 0.3); }
        .status-pill.prescribed,
        .status-pill.complete    { background: #D1FAE5; color: #059669; border: 1px solid rgba(5, 150, 105, 0.3); }
        .status-pill.waiting     { background: #FEF3C7; color: #D97706; border: 1px solid rgba(217, 119, 6, 0.3); }

        [data-theme="dark"] .status-pill.assigned,
        [data-theme="dark"] .status-pill.prescribed,
        [data-theme="dark"] .status-pill.complete { background: #1A3A2A; color: #6EE7B7; border: 1px solid rgba(16, 185, 129, 0.4); }
        [data-theme="dark"] .status-pill.lab_test { background: #2D1B5F; color: #C4B5FD; border: 1px solid rgba(124, 58, 237, 0.4); }
        [data-theme="dark"] .status-pill.waiting  { background: #3D2E0A; color: #FCD34D; border: 1px solid rgba(217, 119, 6, 0.4); }

        .days-badge {
            display: inline-block;
            background: var(--primary);
            color: #fff;
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 0.62rem;
            font-weight: 800;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
            font-family: 'JetBrains Mono', monospace;
        }
        .days-badge.new {
            background: #059669;
            box-shadow: 0 2px 6px rgba(5, 150, 105, 0.25);
        }

        .empty-cell { color: var(--text-secondary); font-size: 0.75rem; font-style: italic; }

        .actions-cell {
            padding: 10px 12px !important;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
        }
        .action-group {
            display: inline-flex;
            gap: 5px;
            align-items: center;
            justify-content: center;
        }

        .btn-action-mini {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            height: 30px;
            padding: 0 11px;
            font-size: 0.68rem;
            font-weight: 700;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all .25s ease;
            white-space: nowrap;
            font-family: inherit;
            line-height: 1;
        }
        .btn-action-mini i { font-size: 0.68rem; }
        .btn-action-mini.reassign { background: linear-gradient(135deg, #DC2626, #B91C1C); color: #fff; box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3); }
        .btn-action-mini.reassign:hover { transform: translateY(-2px); box-shadow: 0 5px 16px rgba(220, 38, 38, 0.45); }
        .btn-action-mini.change { background: linear-gradient(135deg, #D97706, #B45309); color: #fff; box-shadow: 0 2px 8px rgba(217, 119, 6, 0.3); }
        .btn-action-mini.change:hover { transform: translateY(-2px); box-shadow: 0 5px 16px rgba(217, 119, 6, 0.45); }
        .btn-action-mini.assign { background: linear-gradient(135deg, #059669, #047857); color: #fff; box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3); }
        .btn-action-mini.assign:hover { transform: translateY(-2px); box-shadow: 0 5px 16px rgba(5, 150, 105, 0.45); }
        .btn-action-mini:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }

        /* ================================================================
           LIST SEARCH
           ================================================================ */
        .list-search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            flex: 1;
            max-width: 320px;
            min-width: 160px;
        }
        .list-search-wrapper .list-search-icon {
            position: absolute;
            left: 12px; top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 0.78rem;
            pointer-events: none;
            z-index: 2;
        }
        .list-search-input {
            width: 100%;
            padding: 8px 34px 8px 34px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
            transition: all .3s ease;
            font-family: inherit;
        }
        .list-search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        .list-search-clear {
            position: absolute;
            right: 10px; top: 50%;
            transform: translateY(-50%);
            background: var(--gray-200, #E2E8F0);
            border: none;
            color: var(--text-secondary);
            width: 20px; height: 20px;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 0.6rem;
            transition: all .25s ease;
            z-index: 2;
        }
        .list-search-clear:hover { background: #DC2626; color: #fff; }
        .list-search-clear.visible { display: flex; }

        .list-search-count {
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--primary);
            background: var(--primary-bg);
            padding: 3px 9px;
            border-radius: 20px;
            white-space: nowrap;
            font-family: 'JetBrains Mono', monospace;
            display: none;
            border: 1px solid rgba(11, 94, 215, 0.2);
        }
        .list-search-count.visible { display: inline-block; }
        .list-search-count.no-results { color: #DC2626; background: #FEE2E2; border-color: rgba(220, 38, 38, 0.2); }

        .search-highlight {
            background: linear-gradient(180deg, transparent 50%, #FEF3C7 50%);
            color: inherit;
            font-weight: 900;
            padding: 0 2px;
            border-radius: 3px;
        }

        .empty-list-state {
            text-align: center;
            padding: 50px 30px;
            color: var(--text-secondary);
            background: var(--bg-card);
            border-radius: 14px;
            border: 2px dashed var(--border-color);
            margin: 10px;
        }
        .empty-list-state i {
            font-size: 3rem;
            color: var(--primary);
            opacity: 0.3;
            display: block;
            margin-bottom: 14px;
            animation: float-icon 3s ease-in-out infinite;
        }
        @keyframes float-icon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        .empty-list-state p { font-size: 0.95rem; font-weight: 600; color: var(--text-primary); }

        /* ================================================================
           MODERN CARD
           ================================================================ */
        .modern-card {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            transition: all .3s ease;
        }
        .modern-card:hover { box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
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
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modern-card .card-title i { color: var(--primary); font-size: 1rem; }
        .modern-card .card-badge {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 800;
            font-family: 'JetBrains Mono', monospace;
        }

        /* ================================================================
           FORM CARD
           ================================================================ */
        .form-card-modern {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 26px 30px;
            border: 1px solid var(--border-color);
            transition: all .3s ease;
            max-width: 1300px;
            margin: 0 auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }
        .form-card-modern .form-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 22px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--border-color);
        }
        .form-card-modern .form-header .form-icon {
            width: 50px; height: 50px;
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.25rem;
            flex-shrink: 0;
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.25);
        }
        .form-card-modern .form-header .form-title {
            font-size: 1.08rem;
            font-weight: 800;
            color: var(--text-primary);
        }
        .form-card-modern .form-header .form-subtitle {
            font-size: 0.78rem;
            color: var(--text-secondary);
            margin-top: 3px;
        }

        .form-grid-6 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-grid-left,
        .form-grid-right { display: flex; flex-direction: column; gap: 16px; }

        .form-card-item {
            background: var(--bg-body);
            border-radius: 14px;
            padding: 18px 20px;
            border: 1px solid var(--border-color);
            transition: all .3s ease;
            min-height: 150px;
            display: flex;
            flex-direction: column;
        }
        .form-card-item:hover {
            border-color: var(--primary-light);
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.08);
        }
        .form-card-item .card-item-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
        }
        .form-card-item .card-item-title i { color: var(--primary); font-size: 0.95rem; }
        .form-card-item .card-item-title .required { color: #DC2626; margin-left: 2px; }
        .form-card-item .card-item-title .badge-label {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 3px 11px;
            border-radius: 10px;
            background: #E2E8F0;
            color: var(--text-secondary);
            margin-left: 4px;
        }
        [data-theme="dark"] .form-card-item .card-item-title .badge-label { background: #334155; }

        .patient-toggle-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 11px 15px;
            background: var(--bg-card);
            border: 2px solid var(--primary);
            border-radius: 12px;
            cursor: pointer;
            transition: all .3s ease;
            font-size: 0.88rem;
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
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            color: #fff;
            border-color: #2563EB;
        }
        .patient-toggle-btn .toggle-arrow { transition: transform .3s ease; font-size: 0.85rem; }
        .patient-toggle-btn.active .toggle-arrow { transform: rotate(180deg); }

        .patient-toggle-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height .4s ease;
            margin-top: 0;
        }
        .patient-toggle-content.open { max-height: 700px; margin-top: 12px; }

        .patient-search-wrapper { position: relative; margin-bottom: 10px; }
        .patient-search-wrapper i {
            position: absolute;
            left: 12px; top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 0.8rem;
            pointer-events: none;
        }
        .patient-search-wrapper input {
            padding: 10px 12px 10px 34px;
            font-size: 0.82rem;
            width: 100%;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
            transition: all .3s ease;
            font-family: inherit;
        }
        .patient-search-wrapper input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
        }

        .patient-list-container {
            max-height: 360px;
            overflow-y: auto;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-card);
            box-shadow: inset 0 2px 6px rgba(0,0,0,0.03);
        }
        .patient-list-container::-webkit-scrollbar { width: 6px; }
        .patient-list-container::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }

        .patient-list-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: all .25s ease;
            font-size: 0.85rem;
            position: relative;
        }
        .patient-list-item::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 3px;
            background: transparent;
            transition: background .25s ease;
        }
        .patient-list-item:hover { background: var(--primary-bg); }
        .patient-list-item:hover::before { background: var(--primary); }
        .patient-list-item.selected { background: var(--primary-bg); }
        .patient-list-item.selected::before { background: var(--primary); width: 4px; }
        .patient-list-item:last-child { border-bottom: none; }

        .patient-list-item.assigned-highlight {
            background: linear-gradient(90deg, #FFF7ED 0%, #FFEDD5 100%);
            border-left: 4px solid #EA580C;
        }
        [data-theme="dark"] .patient-list-item.assigned-highlight {
            background: linear-gradient(90deg, #3D1F0A 0%, #4A2410 100%);
            border-left: 4px solid #F97316;
        }
        .assigned-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: linear-gradient(135deg, #EA580C, #C2410C);
            color: #fff;
            padding: 2px 9px;
            border-radius: 10px;
            font-size: 0.58rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            box-shadow: 0 2px 6px rgba(234, 88, 12, 0.4);
        }

        .patient-icon { font-size: 1.15rem; flex-shrink: 0; }
        .patient-info { flex: 1; min-width: 0; }
        .patient-name {
            font-weight: 700;
            font-size: 0.88rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .patient-meta {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .status-badge-dropdown {
            display: inline-block;
            font-size: 0.62rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 10px;
            margin-left: 6px;
        }
        .status-badge-dropdown.pending   { background: #FEF3C7; color: #D97706; }
        .status-badge-dropdown.assigned  { background: #D1FAE5; color: #059669; }
        .status-badge-dropdown.lab_only  { background: #EDE9FE; color: #7C3AED; border: 1px dashed #7C3AED; }
        .status-badge-dropdown.prescribed{ background: #D1FAE5; color: #059669; }
        .status-badge-dropdown.waiting   { background: #FEF3C7; color: #D97706; }
        .status-badge-dropdown.complete  { background: #D1FAE5; color: #059669; }

        .form-control-modern {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 0.85rem;
            transition: all .3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
            min-height: 42px;
            font-family: inherit;
        }
        .form-control-modern:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
        }
        .form-control-modern.textarea { min-height: 76px; resize: vertical; }

        /* LAB MODAL */
        .lab-modal-container-modern {
            background: var(--bg-card);
            border-radius: 12px;
            border: 2px solid #7C3AED;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-top: 8px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .lab-modal-header-modern {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 11px 16px;
            background: #EDE9FE;
            border-bottom: 2px solid var(--border-color);
        }
        [data-theme="dark"] .lab-modal-header-modern { background: #2D1B5F; }
        .lab-modal-header-modern .lab-modal-title {
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .lab-test-scroll { max-height: 320px; overflow-y: auto; padding: 4px 0; }
        .lab-test-scroll::-webkit-scrollbar { width: 6px; }
        .lab-test-scroll::-webkit-scrollbar-thumb { background: #7C3AED; border-radius: 10px; }

        .lab-test-item-modern {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            border-bottom: 1px solid var(--border-color);
            transition: background .2s ease;
            cursor: pointer;
        }
        .lab-test-item-modern:hover { background: var(--primary-bg); }
        .lab-test-item-modern:last-child { border-bottom: none; }
        .lab-test-item-modern .lab-test-checkbox {
            width: 18px; height: 18px;
            accent-color: #7C3AED;
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
        .lab-test-item-modern label strong { font-size: 0.85rem; color: var(--text-primary); font-weight: 700; }
        .lab-test-item-modern .lab-test-category {
            font-size: 0.58rem;
            background: #E2E8F0;
            color: var(--text-secondary);
            padding: 2px 9px;
            border-radius: 10px;
        }
        [data-theme="dark"] .lab-test-item-modern .lab-test-category { background: #334155; }
        .lab-test-item-modern .lab-test-price {
            font-size: 0.82rem;
            color: #059669;
            font-weight: 700;
            white-space: nowrap;
            margin-left: auto;
        }
        .lab-test-item-modern.checked {
            background: #EDE9FE;
            border-left: 4px solid #7C3AED;
        }
        [data-theme="dark"] .lab-test-item-modern.checked { background: #2D1B5F; }

        .lab-modal-footer-modern {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 11px 16px;
            border-top: 2px solid var(--border-color);
            background: var(--bg-body);
            flex-wrap: wrap;
            gap: 8px;
        }
        .lab-modal-footer-modern .lab-total-price {
            font-size: 0.82rem;
            font-weight: 700;
            color: #059669;
            padding: 5px 12px;
            background: #D1FAE5;
            border-radius: 20px;
        }
        [data-theme="dark"] .lab-modal-footer-modern .lab-total-price { background: #1A3A2A; color: #6EE7B7; }

        /* VITAL GRID */
        .vital-grid-modern {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        .vital-item-modern {
            background: var(--bg-body);
            border-radius: 12px;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            transition: all .3s ease;
        }
        .vital-item-modern .vital-label {
            font-size: 0.62rem;
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
            font-size: 0.58rem;
            color: var(--text-secondary);
            display: block;
            font-weight: 600;
        }
        .vital-item-modern.bmi-item { background: #E8F0FE; border-color: #2563EB; }
        [data-theme="dark"] .vital-item-modern.bmi-item { background: #1E3A5F; }
        .vital-item-modern.spo2-item { background: rgba(8, 145, 178, 0.05); border-color: #0891B2; }
        .vital-item-modern.spo2-item .vital-label { color: #0891B2; }
        .vital-item-modern.spo2-item .vital-input { color: #0891B2; font-weight: 700; }

        .spo2-category {
            display: inline-block;
            font-size: 0.58rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            margin-top: 2px;
            background: #E2E8F0;
            color: var(--text-secondary);
        }
        .spo2-category.spo2-normal   { background: rgba(5, 150, 105, 0.15); color: #059669; }
        .spo2-category.spo2-low      { background: rgba(217, 119, 6, 0.15); color: #D97706; }
        .spo2-category.spo2-critical { background: rgba(220, 38, 38, 0.3); color: #DC2626; font-weight: 800; }

        /* BUTTONS */
        .btn-modern {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.85rem;
            transition: all .3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
            min-height: 42px;
            font-family: inherit;
        }
        .btn-modern-primary {
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            color: #fff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        .btn-modern-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.3);
            color: #fff;
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
        .btn-modern-sm { padding: 6px 13px; font-size: 0.72rem; min-height: 32px; border-radius: 8px; }
        .btn-modern-purple { background: #7C3AED; color: #fff; }
        .btn-modern-purple:hover { background: #5B21B6; color: #fff; }

        .form-actions-modern {
            display: flex;
            gap: 12px;
            padding-top: 18px;
            margin-top: 18px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }

        .selected-patient-info {
            margin-top: 8px;
            padding: 11px 14px;
            background: #E8F0FE;
            border-radius: 12px;
            border: 1px solid #6EA8FE;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.76rem;
            flex-wrap: wrap;
        }
        [data-theme="dark"] .selected-patient-info { background: #1E3A5F; border-color: #3B82F6; }
        .selected-patient-info i { color: var(--primary); }

        .new-visit-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: linear-gradient(135deg, #10B981, #059669);
            color: #fff;
            padding: 3px 9px;
            border-radius: 10px;
            font-size: 0.58rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
        }

        /* FOOTER */
        .footer {
            padding: 12px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.68rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 700; }

        /* TOAST */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 999;
            max-width: 420px;
            transform: translateY(100px);
            opacity: 0;
            transition: all .4s cubic-bezier(.4, 0, .2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: #fff;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            font-size: 0.85rem;
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success, #059669); }
        .toast-custom.error   { background: var(--danger, #DC2626); }
        .toast-custom.info    { background: var(--primary, #0B5ED7); }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .form-card-modern { padding: 18px; }
            .form-grid-6 { grid-template-columns: 1fr; gap: 14px; }
        }
        @media (max-width: 768px) {
            .form-card-modern { padding: 14px; }
            .page-header { padding: 14px 16px; flex-direction: column; align-items: flex-start; }
            .page-header .page-title { font-size: 1.1rem; }
            .vital-grid-modern { grid-template-columns: repeat(2, 1fr); }
            .form-actions-modern { flex-direction: column; }
            .form-actions-modern .btn-modern { width: 100%; justify-content: center; }
            .form-card-item { min-height: 130px; padding: 14px 16px; }
            .btn-action-mini {
                width: 32px; height: 32px;
                min-width: 32px; min-height: 32px;
                padding: 0; gap: 0;
            }
            .btn-action-mini .btn-text { display: none; }
            .btn-action-mini i { font-size: 0.75rem; }
        }
        @media (max-width: 480px) {
            .vital-grid-modern { grid-template-columns: 1fr 1fr; }
            .form-card-item { min-height: 100px; padding: 12px 14px; }
            .form-control-modern { font-size: 0.78rem; padding: 9px 12px; min-height: 38px; }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp .5s ease forwards;
            opacity: 0;
        }

        .spinner {
            display: inline-block;
            width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<main class="main-content">

    <!-- ================================================================
         PAGE HEADER
         ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-md"></i>
                Assign / Change / Reassign Doctor
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                Change & Reassign → Inafuta visit + bills kabisa                <span class="header-badge"><i class="fas fa-user-md"></i> <span id="onlineDoctorCount"><?= $online_doctors_count ?></span> Online</span>
                <span class="header-badge"><i class="fas fa-user-md"></i> <span id="offlineDoctorCount"><?= $offline_doctors_count ?></span> Offline</span>
                <span class="header-badge"><i class="fas fa-user-check"></i> <span id="assignedCount"><?= $assigned_count ?></span> Assigned</span>
                <span class="header-badge" style="background:rgba(124,58,237,0.2);border-color:rgba(124,58,237,0.3);color:#A78BFA;">
                    <i class="fas fa-flask"></i> <span id="labOnlyCount"><?= $lab_only_count ?></span> Lab Test
                </span>
                <span class="header-badge" style="background:rgba(5,150,105,0.2);border-color:rgba(5,150,105,0.3);color:#6EE7B7;">
                    <i class="fas fa-prescription"></i> <span id="prescribedCount"><?= $prescribed_count ?></span> Prescribe
                </span>
                <span class="header-badge" style="background:rgba(217,119,6,0.2);border-color:rgba(217,119,6,0.3);color:#FCD34D;">
                    <i class="fas fa-clock"></i> <span id="waitingCount"><?= $waiting_count ?></span> Waiting
                </span>
                <span class="header-badge" style="background:rgba(5,150,105,0.2);border-color:rgba(5,150,105,0.3);color:#6EE7B7;">
                    <i class="fas fa-check-double"></i> <span id="completeCount"><?= $complete_count ?></span> Complete
                </span>

                <!-- ✅ TOTAL PATIENTS BADGE -->
                <span class="header-badge" style="background:rgba(255,255,255,0.25);border-color:rgba(255,255,255,0.4);font-weight:700;">
                    <i class="fas fa-users"></i> Total: <strong><?= $branch_patients_total ?></strong>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ============================================================
         ✅ FILTER BAR — FULL WIDTH, ROW MOJA (kila button inajaza nafasi sawa)
         ============================================================ -->
    <div class="filter-bar">
        <span class="filter-label">
            <i class="fas fa-filter"></i> Filter:
        </span>

        <button type="button" class="filter-btn active" data-status="assigned" onclick="filterByStatus('assigned')">
            <i class="fas fa-user-check"></i>
            Assigned
            <span class="filter-count" id="toggleAssignedCount"><?= $assigned_count ?></span>
        </button>

        <button type="button" class="filter-btn" data-status="lab_test" onclick="filterByStatus('lab_test')">
            <i class="fas fa-flask"></i>
            Lab Test
            <span class="filter-count" id="toggleLabCount"><?= $lab_only_count ?></span>
        </button>

        <button type="button" class="filter-btn" data-status="prescribed" onclick="filterByStatus('prescribed')">
            <i class="fas fa-prescription"></i>
            Prescribe
            <span class="filter-count" id="togglePrescribedCount"><?= $prescribed_count ?></span>
        </button>

        <button type="button" class="filter-btn" data-status="waiting" onclick="filterByStatus('waiting')">
            <i class="fas fa-clock"></i>
            Waiting
            <span class="filter-count" id="toggleWaitingCount"><?= $waiting_count ?></span>
        </button>

        <button type="button" class="filter-btn" data-status="complete" onclick="filterByStatus('complete')">
            <i class="fas fa-check-double"></i>
            Complete
            <span class="filter-count" id="toggleCompleteCount"><?= $complete_count ?></span>
        </button>
    </div>

    <!-- ============================================================
         PATIENTS LIST
         ============================================================ -->
    <div class="modern-card animate-fade-in-up" style="max-width:1400px;margin:0 auto 20px;" id="patientsListCard">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-user-check" id="listIcon"></i>
                <span id="listTitle">Assigned Patients (With Doctor)</span>
                <span class="card-badge" style="background:#D1FAE5;color:#059669;" id="listCountBadge"><?= $assigned_count ?></span>
            </div>

            <div style="display:flex;align-items:center;gap:10px;flex:1;justify-content:flex-end;flex-wrap:wrap;">
                <div class="list-search-wrapper" id="listSearchWrapper">
                    <i class="fas fa-search list-search-icon"></i>
                    <input type="text"
                           class="list-search-input"
                           id="listSearchInput"
                           placeholder="Search patients..."
                           oninput="performListSearch(this.value)">
                    <button type="button"
                            class="list-search-clear"
                            id="listSearchClear"
                            onclick="clearListSearch()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <span class="list-search-count" id="listSearchCount"></span>
            </div>
        </div>

        <div id="patientsListContainer">
            <div style="text-align:center;padding:30px;">
                <div class="spinner" style="border-color:var(--border-color);border-top-color:var(--primary);"></div>
                <p style="font-size:0.85rem;color:var(--text-secondary);margin-top:8px;">Loading...</p>
            </div>
        </div>
    </div>

    <!-- ============================================================
         ASSIGN FORM
         ============================================================ -->
    <div class="form-card-modern animate-fade-in-up" id="mainFormCard" style="animation-delay:.1s;">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas <?= $change_mode ? 'fa-sync-alt' : 'fa-stethoscope' ?>"></i>
            </div>
            <div>
                <h3 class="form-title">
                    <?= $change_mode ? '🔄 Change Doctor' : 'Assign / Change Doctor or Lab Test' ?>
                    <?php if ($change_mode && $selected_patient_data): ?>
                        <span style="font-weight:400;font-size:0.78rem;color:#D97706;">
                            - Changing: <?= htmlspecialchars($selected_patient_data['full_name']) ?>
                        </span>
                    <?php endif; ?>
                </h3>
                <p class="form-subtitle">
                    <?php if ($change_mode): ?>
                        <span style="color:#D97706;">🔄 Change Mode:</span> Visit ya zamani + bill yake zitafutwa, visit mpya itaundwa
                    <?php else: ?>
                        Select patient and assign a doctor OR request lab tests
                    <?php endif; ?>
                    <span style="color:#059669;font-weight:700;margin-left:8px;">
                        <i class="fas fa-plus-circle"></i> Every Assign Create New Visit
                    </span>
                </p>
            </div>
        </div>

        <form method="POST" action="" id="assignForm">
            <input type="hidden" name="action" value="change_doctor">
            <input type="hidden" name="patient_id" id="selectedPatientInput" value="<?= $selected_patient_id ?>">

            <div class="form-grid-6">
                <div class="form-grid-left">
                    <div class="form-card-item">
                        <div class="card-item-title">
                            <i class="fas fa-user"></i> Select Patient <span class="required">*</span>
                            <span class="badge-label" id="patientCountBadge">All (<?= $branch_patients_total ?>)</span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            <button type="button" class="patient-toggle-btn" id="patientToggleBtn" onclick="togglePatientList()">
                                <span id="patientToggleLabel">
                                    <i class="fas fa-users"></i>
                                    <span id="selectedPatientLabel">
                                        <?php if ($selected_patient_data): ?>
                                            ✓ <?= htmlspecialchars($selected_patient_data['full_name']) ?>
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
                                    <input type="text" id="patientSearchFilter" placeholder="🔍 Search patient..." oninput="filterPatientList(this.value)">
                                </div>

                                <div class="patient-list-container" id="patientListContainer">
                                    <?php if (!empty($unique_patients_for_dropdown)): ?>
                                        <?php foreach ($unique_patients_for_dropdown as $patient):
                                            $status_label = 'Complete';
                                            $status_class = 'complete';
                                            $status_icon  = '✅';
                                            $is_assigned  = false;
                                            $pending_lab  = (int)($patient['pending_lab_tests_count'] ?? 0);

                                            if (!empty($patient['assigned_doctor_name'])) {
                                                $is_assigned  = true;
                                                $status_label = 'Assigned'; $status_class = 'assigned'; $status_icon = '✅';
                                            } elseif (!empty($patient['visit_id'])) {
                                                if ($patient['visit_status'] === 'lab_test' && $pending_lab > 0) {
                                                    $status_label = 'Lab Test'; $status_class = 'lab_only'; $status_icon = '🧪';
                                                } elseif ($patient['visit_status'] === 'waiting') {
                                                    $status_label = 'Waiting'; $status_class = 'waiting'; $status_icon = '⏳';
                                                } elseif ($patient['visit_status'] === 'prescribed') {
                                                    $status_label = 'Prescribed'; $status_class = 'prescribed'; $status_icon = '💊';
                                                } elseif (in_array($patient['visit_status'], ['assigned','with_doctor'])) {
                                                    $status_label = 'Assigned'; $status_class = 'assigned'; $status_icon = '✅';
                                                } elseif (in_array($patient['visit_status'], ['completed','cancelled'])) {
                                                    $status_label = 'Complete'; $status_class = 'complete'; $status_icon = '✅';
                                                } else {
                                                    $status_label = 'Pending'; $status_class = 'pending'; $status_icon = '🟡';
                                                }
                                            }

                                            $doctor_info = '';
                                            if (!empty($patient['assigned_doctor_name'])) {
                                                $online_status = !empty($patient['assigned_doctor_online']) ? '🟢' : '⚪';
                                                $doctor_info   = 'Dr. ' . htmlspecialchars($patient['assigned_doctor_name']) . ' ' . $online_status;
                                            }

                                            $is_selected = ($selected_patient_id == $patient['id']) ? 'selected' : '';
                                            $days        = (int)($patient['patient_days'] ?? 0);
                                            $days_text   = $days > 0 ? $days . 'd' : 'New';
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
                                                            <span class="assigned-badge">
                                                                <i class="fas fa-user-md"></i> ASSIGNED
                                                            </span>
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
                                        <div style="padding:20px;text-align:center;color:var(--text-secondary);font-size:0.85rem;">
                                            <i class="fas fa-user-slash"></i>
                                            <p>No patients found</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div id="selectedPatientInfo" style="display:<?= $selected_patient_id > 0 && $selected_patient_data ? 'block' : 'none' ?>;" class="selected-patient-info">
                                <?php if ($selected_patient_data):
                                    $patient_days = (int)($selected_patient_data['patient_days'] ?? 0);
                                    $days_text    = $patient_days > 0 ? '<span class="days-badge">📅 ' . $patient_days . ' days ago</span>' : '<span class="days-badge new">📅 Just registered</span>';
                                ?>
                                    <i class="fas fa-user-circle" style="font-size:1.1rem;"></i>
                                    <span style="font-weight:700;"><?= htmlspecialchars($selected_patient_data['full_name'] ?? '') ?></span>
                                    <span>|</span>
                                    <span><?= htmlspecialchars($selected_patient_data['patient_id'] ?? '') ?></span>
                                    <?= $days_text ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-card-item">
                        <div class="card-item-title">
                            <i class="fas fa-tasks"></i> Select Action <span class="required">*</span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            <select name="assignment_type" class="form-control-modern" required id="assignmentTypeSelect" onchange="toggleAssignmentType(this.value)">
                                <option value="doctor" <?= $change_mode ? 'selected' : '' ?>>👨‍⚕️ Assign Doctor</option>
                                <option value="lab">🧪 Request Lab Test(s) (No Doctor)</option>
                            </select>
                            <p style="font-size:0.7rem;color:var(--text-secondary);margin-top:8px;" id="assignmentTypeHelp">👨‍⚕️ Assign a doctor to the patient</p>
                        </div>
                    </div>

                    <div class="form-card-item" id="doctorSelectCard">
                        <div class="card-item-title">
                            <i class="fas fa-user-md"></i> Select Doctor <span class="required" id="doctorRequired">*</span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            <select name="doctor_id" class="form-control-modern" required id="doctorSelect">
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
                            <p style="font-size:0.7rem;color:var(--text-secondary);margin-top:8px;">
                                🟢 <?= $online_doctors_count ?> online | ⚪ <?= $offline_doctors_count ?> offline
                            </p>
                        </div>
                    </div>
                </div>

                <div class="form-grid-right">
                    <div class="form-card-item" id="visitTypeSection">
                        <div class="card-item-title">
                            <i class="fas fa-tag"></i> Visit Type <span class="required">*</span>
                            <span class="badge-label" id="visitTypePrice">
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
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-card-item">
                        <div class="card-item-title">
                            <i class="fas fa-notes-medical"></i> Symptoms
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;gap:8px;">
                            <select name="symptoms_select" class="form-control-modern" id="symptomsSelect" style="min-height:40px;">
                                <option value="">-- Select Common Symptom --</option>
                                <?php foreach ($common_symptoms as $symptom): ?>
                                    <option value="<?= htmlspecialchars($symptom) ?>"><?= htmlspecialchars($symptom) ?></option>
                                <?php endforeach; ?>
                                <option value="other">✏️ Other</option>
                            </select>
                            <textarea name="symptoms" class="form-control-modern textarea" placeholder="Symptoms..." id="symptomsTextarea" rows="2"></textarea>
                        </div>
                    </div>

                    <div class="form-card-item" id="labSection" style="display:none;">
                        <div class="card-item-title">
                            <i class="fas fa-flask" style="color:#7C3AED;"></i> Select Lab Tests
                            <span class="badge-label" id="labSelectedCount">(0 selected)</span>
                        </div>
                        <div style="flex:1;display:flex;flex-direction:column;">
                            <div class="lab-modal-container-modern">
                                <div class="lab-modal-header-modern">
                                    <div class="lab-modal-title">
                                        <i class="fas fa-flask" style="color:#7C3AED;"></i>
                                        Available Tests (<?= count($lab_tests_catalog) ?>)
                                    </div>
                                    <button type="button" onclick="closeLabTests()" style="background:none;border:none;color:var(--text-secondary);cursor:pointer;font-size:0.9rem;padding:4px 8px;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="lab-test-scroll">
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
                                </div>
                                <div class="lab-modal-footer-modern">
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <button type="button" class="btn-modern btn-modern-outline btn-modern-sm" onclick="selectAllLabTests()">
                                            <i class="fas fa-check-double"></i> All
                                        </button>
                                        <button type="button" class="btn-modern btn-modern-outline btn-modern-sm" onclick="deselectAllLabTests()">
                                            <i class="fas fa-times"></i> Clear
                                        </button>
                                        <span class="lab-total-price" id="labTotalPrice">Total: TSh 0</span>
                                    </div>
                                    <button type="button" class="btn-modern btn-modern-purple btn-modern-sm" onclick="closeLabTests()" style="background:#DC2626;">
                                        <i class="fas fa-times"></i> Close
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="lab_test_ids" id="selectedLabTestsInput" value="">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-card-item" style="margin-top:18px;">
                <div class="card-item-title">
                    <i class="fas fa-heartbeat" style="color:#DC2626;"></i> Vital Signs
                    <span class="badge-label">Optional - 7 Vitals</span>
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
                        <span class="vital-label">🫁 SpO₂</span>
                        <input type="number" name="oxygen_saturation" class="vital-input" placeholder="98" value="<?= $latest_vital_signs['oxygen_saturation'] ?? '' ?>" id="spo2Input" oninput="calculateSpO2Category()">
                        <span class="vital-unit">%</span>
                        <span class="spo2-category" id="spo2Category">Auto</span>
                    </div>
                </div>
            </div>

            <div class="form-card-item" style="margin-top:12px;">
                <div class="card-item-title">
                    <i class="fas fa-sticky-note"></i> Notes <span class="badge-label">Optional</span>
                </div>
                <textarea name="notes" class="form-control-modern textarea" placeholder="Notes..." id="notesInput" rows="2"></textarea>
            </div>

            <div class="form-actions-modern">
                <button type="submit" class="btn-modern <?= $change_mode ? 'btn-modern-outline' : 'btn-modern-primary' ?>" id="assignBtn" style="<?= $change_mode ? 'background:#D97706;color:#fff;border-color:#D97706;' : '' ?>">
                    <i class="fas <?= $change_mode ? 'fa-sync-alt' : 'fa-user-md' ?>"></i>
                    <?= $change_mode ? 'Change Doctor' : 'Assign / Change Doctor' ?>
                    <span class="new-visit-badge" style="margin-left:6px;">
                        <i class="fas fa-plus-circle"></i> NEW VISIT
                    </span>
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

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 6px;opacity:.4;">|</span>
            Assign Doctor
            <span style="margin:0 6px;opacity:.4;">|</span>
            <span id="footerTimestamp"><?= date('h:i:s A') ?></span>
            <span style="margin:0 6px;opacity:.4;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    // CLOCK
    function updateClock() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true });
        var ft = document.getElementById('footerTimestamp');
        if (ft) ft.textContent = timeStr;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // TOAST
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
        }, 6000);
    }

    // PATIENT TOGGLE
    function togglePatientList() {
        var content = document.getElementById('patientToggleContent');
        var btn = document.getElementById('patientToggleBtn');
        if (content && btn) {
            content.classList.toggle('open');
            btn.classList.toggle('active');
        }
    }

    function selectPatient(patientId, patientName, patientCode) {
        document.getElementById('selectedPatientInput').value = patientId;

        var label = document.getElementById('selectedPatientLabel');
        if (label) label.innerHTML = '✓ ' + patientName + ' (' + patientCode + ')';

        document.querySelectorAll('.patient-list-item').forEach(function(item) {
            item.classList.remove('selected');
            if (item.getAttribute('data-patient-id') == patientId) item.classList.add('selected');
        });

        var content = document.getElementById('patientToggleContent');
        var btn = document.getElementById('patientToggleBtn');
        if (content) content.classList.remove('open');
        if (btn) btn.classList.remove('active');

        fetchPatientDetails(patientId);
        showToast('👤 Patient Selected', patientName, 'success');
    }

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
            badge.textContent = searchTerm === '' ? 'All (<?= $branch_patients_total ?>)' : 'Found: ' + visibleCount;
        }
    }

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

    // BMI + SpO2
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

        var category = '', categoryClass = '';
        if (spo2 >= 95)      { category = 'Normal';   categoryClass = 'spo2-normal'; }
        else if (spo2 >= 90) { category = 'Low';      categoryClass = 'spo2-low'; }
        else                 { category = 'Critical'; categoryClass = 'spo2-critical'; }

        spo2Category.textContent = category;
        spo2Category.className = 'spo2-category ' + categoryClass;
    }

    // ASSIGNMENT TYPE
    function toggleAssignmentType(type) {
        var labSection = document.getElementById('labSection');
        var doctorSelect = document.getElementById('doctorSelect');
        var doctorRequired = document.getElementById('doctorRequired');
        var assignBtn = document.getElementById('assignBtn');
        var helpText = document.getElementById('assignmentTypeHelp');
        var visitTypeSection = document.getElementById('visitTypeSection');
        var visitTypeSelect = document.getElementById('visitTypeSelect');
        var visitTypePrice = document.getElementById('visitTypePrice');
        var doctorSelectCard = document.getElementById('doctorSelectCard');

        if (type === 'lab') {
            if (doctorSelectCard) doctorSelectCard.style.display = 'none';
            labSection.style.display = 'block';
            doctorSelect.removeAttribute('required');
            if (doctorRequired) doctorRequired.style.display = 'none';
            helpText.textContent = '🧪 Lab test mode - Doctor NOT required';
            assignBtn.innerHTML = '<i class="fas fa-flask"></i> Request Lab Tests <span class="new-visit-badge" style="margin-left:6px;"><i class="fas fa-plus-circle"></i> NEW VISIT</span>';
            if (visitTypeSection) visitTypeSection.style.display = 'none';
            if (visitTypeSelect) visitTypeSelect.disabled = true;
            if (visitTypePrice) visitTypePrice.style.display = 'none';
            assignBtn.style.background = '#7C3AED';
            assignBtn.style.color = '#fff';
        } else {
            if (doctorSelectCard) doctorSelectCard.style.display = 'block';
            labSection.style.display = 'none';
            doctorSelect.setAttribute('required', 'required');
            if (doctorRequired) doctorRequired.style.display = 'inline';
            helpText.textContent = '👨‍⚕️ Assign doctor to patient';
            assignBtn.innerHTML = '<i class="fas fa-user-md"></i> Assign / Change Doctor <span class="new-visit-badge" style="margin-left:6px;"><i class="fas fa-plus-circle"></i> NEW VISIT</span>';
            if (visitTypeSection) visitTypeSection.style.display = 'block';
            if (visitTypeSelect) visitTypeSelect.disabled = false;
            if (visitTypePrice) visitTypePrice.style.display = 'inline';
            assignBtn.style.background = '';
            assignBtn.style.color = '';
        }
    }

    // LAB TESTS
    function updateLabSelection(checkbox) {
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

        var countEl = document.getElementById('labSelectedCount');
        if (countEl) countEl.textContent = '(' + count + ' selected)';
        var totalPriceEl = document.getElementById('labTotalPrice');
        if (totalPriceEl) totalPriceEl.textContent = 'Total: TSh ' + total.toLocaleString();
        var hiddenInput = document.getElementById('selectedLabTestsInput');
        if (hiddenInput) hiddenInput.value = ids.join(',');
    }

    function selectAllLabTests() {
        document.querySelectorAll('.lab-test-checkbox').forEach(function(cb) {
            cb.checked = true;
            cb.closest('.lab-test-item-modern')?.classList.add('checked');
        });
        updateLabSelection(null);
    }

    function deselectAllLabTests() {
        document.querySelectorAll('.lab-test-checkbox').forEach(function(cb) {
            cb.checked = false;
            cb.closest('.lab-test-item-modern')?.classList.remove('checked');
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
        if (!select) return;
        var selectedOption = select.options[select.selectedIndex];
        var price = selectedOption.dataset.price || 0;
        if (priceDisplay) priceDisplay.textContent = 'TSh ' + parseInt(price).toLocaleString();
    }

    // REASSIGN / CHANGE
    function changeDoctor(patientId) {
        window.location.href = 'assign_doctor.php?patient_id=' + patientId + '&change=1';
    }

    function quickAssign(patientId) {
        document.getElementById('selectedPatientInput').value = patientId;
        showToast('👤 Patient Selected', 'Select doctor and click Assign', 'info');
        document.getElementById('mainFormCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function reassignDoctor(patientId, visitId) {
        if (!confirm('⚠️ REASSIGN — UNAFUTA KILA KITU!\n\nHii itafuta KABISA:\n• Visit\n• Bills\n• Bill items\n• Payments\n• Prescriptions\n• Lab tests\n• Vital signs\n• Procedures\n\nPatient atabaki HANA DOCTOR.\n\nUna uhakika?')) return;

        document.querySelectorAll('.btn-action-mini.reassign').forEach(function(b) {
            b.disabled = true;
            b.innerHTML = '<span class="spinner" style="border-color:rgba(255,255,255,0.3);border-top-color:#fff;width:12px;height:12px;"></span>';
        });

        var formData = new FormData();
        formData.append('action', 'reassign_doctor');
        formData.append('patient_id', patientId);
        formData.append('visit_id', visitId);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast('✅ Reassigned', data.message || 'Visit na bills zote zimefutwa!', 'success');
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    showToast('❌ Error', data.message || 'Failed to reassign', 'error');
                    document.querySelectorAll('.btn-action-mini.reassign').forEach(function(b) {
                        b.disabled = false;
                        b.innerHTML = '<i class="fas fa-user-minus"></i> <span class="btn-text">Reassign</span>';
                    });
                }
            })
            .catch(function(error) {
                showToast('❌ Error', 'Network error: ' + error.message, 'error');
                document.querySelectorAll('.btn-action-mini.reassign').forEach(function(b) {
                    b.disabled = false;
                    b.innerHTML = '<i class="fas fa-user-minus"></i> <span class="btn-text">Reassign</span>';
                });
            });
    }

    // FILTERS
    var currentListStatus = 'assigned';
    var currentSearchQuery = '';
    var originalTableHTML = '';

    function filterByStatus(status) {
        document.querySelectorAll('.filter-btn').forEach(function(btn) {
            btn.classList.remove('active');
            if (btn.dataset.status === status) btn.classList.add('active');
        });

        var searchInput = document.getElementById('listSearchInput');
        if (searchInput) searchInput.value = '';
        var clearBtn = document.getElementById('listSearchClear');
        if (clearBtn) clearBtn.classList.remove('visible');
        var countBadge = document.getElementById('listSearchCount');
        if (countBadge) countBadge.classList.remove('visible', 'no-results');
        currentSearchQuery = '';
        currentListStatus = status;

        var titles = {
            'assigned':   { title: 'Assigned Patients (With Doctor)', icon: 'fa-user-check',   color: '#059669', bg: '#D1FAE5' },
            'lab_test':   { title: 'Lab Test Patients (Pending Only)', icon: 'fa-flask',        color: '#7C3AED', bg: '#EDE9FE' },
            'prescribed': { title: 'Prescribed Patients',              icon: 'fa-prescription', color: '#059669', bg: '#D1FAE5' },
            'waiting':    { title: 'Waiting Patients',                 icon: 'fa-clock',        color: '#D97706', bg: '#FEF3C7' },
            'complete':   { title: 'Complete Patients',                icon: 'fa-check-double', color: '#059669', bg: '#D1FAE5' }
        };

        var t = titles[status] || titles['assigned'];
        var listTitle = document.getElementById('listTitle');
        var listIcon = document.getElementById('listIcon');
        var listCountBadge = document.getElementById('listCountBadge');

        if (listTitle) listTitle.textContent = t.title;
        if (listIcon) { listIcon.className = 'fas ' + t.icon; listIcon.style.color = t.color; }

        var counts = {
            'assigned':   document.getElementById('toggleAssignedCount')?.textContent || 0,
            'lab_test':   document.getElementById('toggleLabCount')?.textContent || 0,
            'prescribed': document.getElementById('togglePrescribedCount')?.textContent || 0,
            'waiting':    document.getElementById('toggleWaitingCount')?.textContent || 0,
            'complete':   document.getElementById('toggleCompleteCount')?.textContent || 0
        };
        if (listCountBadge) {
            listCountBadge.textContent = counts[status] || 0;
            listCountBadge.style.background = t.bg;
            listCountBadge.style.color = t.color;
        }

        fetchFilteredList(status);
    }

    function fetchFilteredList(status) {
        var container = document.getElementById('patientsListContainer');
        if (!container) return;

        container.innerHTML = '<div style="text-align:center;padding:30px;"><div class="spinner" style="border-color:var(--border-color);border-top-color:var(--primary);"></div></div>';

        var formData = new FormData();
        formData.append('action', 'get_filtered_list');
        formData.append('status', status);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success && data.html) {
                    container.innerHTML = data.html;
                    originalTableHTML = data.html;
                    if (currentSearchQuery) {
                        setTimeout(function() { performListSearch(currentSearchQuery); }, 100);
                    }
                } else {
                    container.innerHTML = '<div class="empty-list-state"><i class="fas fa-inbox"></i><p>No patients found</p></div>';
                    originalTableHTML = '';
                }
            })
            .catch(function() {
                container.innerHTML = '<div class="empty-list-state"><i class="fas fa-exclamation-triangle" style="color:#DC2626;"></i><p>Error loading data</p></div>';
            });
    }

    function performListSearch(query) {
        currentSearchQuery = query;
        var searchTerm = query.toLowerCase().trim();
        var clearBtn = document.getElementById('listSearchClear');
        var countBadge = document.getElementById('listSearchCount');
        var container = document.getElementById('patientsListContainer');

        if (clearBtn) {
            if (searchTerm.length > 0) clearBtn.classList.add('visible');
            else clearBtn.classList.remove('visible');
        }

        if (searchTerm.length === 0) {
            if (container && originalTableHTML) container.innerHTML = originalTableHTML;
            if (countBadge) countBadge.classList.remove('visible', 'no-results');
            return;
        }

        if (!container) return;
        var rows = container.querySelectorAll('.patient-list-table tbody tr');
        if (rows.length === 0) { setTimeout(function() { performListSearch(query); }, 300); return; }

        var matchCount = 0;
        rows.forEach(function(row) {
            row.querySelectorAll('.search-highlight').forEach(function(el) {
                var parent = el.parentNode;
                parent.replaceChild(document.createTextNode(el.textContent), el);
                parent.normalize();
            });

            var searchableText = (row.textContent || '').toLowerCase();
            if (searchableText.indexOf(searchTerm) !== -1) {
                matchCount++;
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        if (countBadge) {
            if (matchCount > 0) {
                countBadge.textContent = '🔍 ' + matchCount + ' matches';
                countBadge.classList.remove('no-results');
                countBadge.classList.add('visible');
            } else {
                countBadge.textContent = '❌ No matches';
                countBadge.classList.add('no-results', 'visible');
            }
        }
    }

    function clearListSearch() {
        var searchInput = document.getElementById('listSearchInput');
        var clearBtn = document.getElementById('listSearchClear');
        var countBadge = document.getElementById('listSearchCount');
        var container = document.getElementById('patientsListContainer');

        if (searchInput) searchInput.value = '';
        if (clearBtn) clearBtn.classList.remove('visible');
        if (countBadge) countBadge.classList.remove('visible', 'no-results');
        currentSearchQuery = '';

        if (container && originalTableHTML) container.innerHTML = originalTableHTML;
        if (searchInput) searchInput.focus();
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
                            ? '<span class="doctor-pill"><i class="fas fa-user-md"></i> Dr. ' + escapeHtml(doctorName) + '</span>'
                            : '<span class="no-doctor-tag"><i class="fas fa-minus-circle"></i> No doctor assigned</span>';
                        var patientDays = data.patient_days || 0;
                        var daysHtml = '<span class="days-badge ' + (patientDays > 0 ? '' : 'new') + '">📅 ' + (patientDays > 0 ? patientDays + ' days ago' : 'Just registered') + '</span>';

                        infoDiv.innerHTML = '<i class="fas fa-user-circle" style="font-size:1.1rem;color:var(--primary);"></i>' +
                            '<span style="font-weight:700;">' + escapeHtml(data.patient.full_name || '') + '</span>' +
                            '<span>|</span>' +
                            '<span>' + escapeHtml(data.patient.patient_id || '') + '</span>' +
                            daysHtml + doctorHtml;
                        infoDiv.style.display = 'flex';
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

    // FORM SUBMIT
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

    // INIT
    document.addEventListener('DOMContentLoaded', function() {
        calculateBMI();
        calculateSpO2Category();
        updateVisitTypePrice();
        filterByStatus('assigned');

        var assignmentType = document.getElementById('assignmentTypeSelect');
        if (assignmentType && assignmentType.value === 'lab') toggleAssignmentType('lab');

        <?php if ($change_mode && $selected_patient_id > 0): ?>
        var content = document.getElementById('patientToggleContent');
        var btn = document.getElementById('patientToggleBtn');
        if (content && btn) { content.classList.add('open'); btn.classList.add('active'); }
        <?php endif; ?>

        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                document.getElementById('listSearchInput')?.focus();
            }
        });
    });

    console.log('%c👨‍⚕️ Braick - Assign Doctor V26 (Filters Full Width, Row Moja)', 'font-size:18px;font-weight:bold;color:#2563EB;');
    console.log('%c✅ V26: Filter buttons zinajaza width yote ya card (flex: 1)', 'font-size:12px;color:#059669;font-weight:bold;');
    console.log('%c✅ V26: Total Patients kwenye page header', 'font-size:12px;color:#059669;font-weight:bold;');
    console.log('%c✅ V26: Saa na Tarehe kwenye kila patient row', 'font-size:12px;color:#7C3AED;font-weight:bold;');
</script>

</body>
</html>