<?php
// ================================================================
// FILE: frontend/pages/admin/assign_doctor.php
// ADMIN - ASSIGN / CHANGE / REASSIGN DOCTOR & LAB TESTS (V6 FINAL)
// ✅ FIXED: Assigned By column (jina la aliye-assign) - INAJAZWA
// ✅ FIXED: Bill + bill_items zinatumwa kwa Cashier
// ✅ FIXED: Notifications kwa Cashiers
// ✅ FIXED: Buttons zote zinalingana (compact)
// ✅ FIXED: Branch filter inafanya kazi
// ✅ FIXED: Lab Test inaonyesha aliye request
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
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 1);
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = (int)($_SESSION['branch_id'] ?? 1);
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// ✅ BRANCH SELECTION
// ================================================================
$selected_branch_id = 'all';
$branch_name = 'All Branches';
$show_all_branches = false;

if (isset($_GET['branch']) && $_GET['branch'] !== '' && $_GET['branch'] !== 'all') {
    $selected_branch_id = (int)$_GET['branch'];
} elseif (isset($_GET['branch_id']) && $_GET['branch_id'] > 0) {
    $selected_branch_id = (int)$_GET['branch_id'];
} else {
    $selected_branch_id = $user_branch_id;
}

if ($selected_branch_id === 'all' || $selected_branch_id === 0) {
    $show_all_branches = true;
    $branch_name = 'All Branches';
    $selected_branch_id = 0;
}

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

$message = '';
$message_type = '';
$all_patients = [];
$assigned_patients = [];
$lab_only_patients = [];
$waiting_patients = [];
$prescribed_patients = [];
$complete_patients = [];
$pending_patients = [];
$doctors = [];
$online_doctors = [];
$offline_doctors = [];
$online_doctors_count = 0;
$offline_doctors_count = 0;
$total_doctors = 0;
$visit_type_options = [];
$assigned_count = 0;
$lab_only_count = 0;
$waiting_count = 0;
$prescribed_count = 0;
$complete_count = 0;
$pending_count = 0;
$branch_patients_total = 0;
$selected_patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$latest_vital_signs = null;
$selected_patient_data = null;
$change_mode = isset($_GET['change']) && $_GET['change'] == 1;
$lab_tests_catalog = [];
$unread_notifications = 0;
$branch_has_patients = true;

try {
    // NOTIFICATIONS
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // BRANCH NAME
    if (!$show_all_branches && $selected_branch_id > 0) {
        $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
        $stmt->execute([$selected_branch_id]);
        $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($branch_data) {
            $branch_name = $branch_data['name'];
        } else {
            $branch_name = 'Unknown Branch';
        }
    }

    // CONSULTATION SERVICES
    if ($show_all_branches) {
        $stmt = $db->prepare("SELECT id, service_name, description, price, unit, is_active FROM services WHERE category_id = 2 AND is_active = 1 ORDER BY service_name");
        $stmt->execute();
    } else {
        $stmt = $db->prepare("SELECT id, service_name, description, price, unit, is_active FROM services WHERE category_id = 2 AND is_active = 1 AND (branch_id = ? OR branch_id IS NULL) ORDER BY service_name");
        $stmt->execute([$selected_branch_id]);
    }
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
        elseif (strpos(strtolower($service_name), 'general') !== false) $icon = '🏥';

        $visit_type_options[$service_id] = [
            'id' => $service_id,
            'service_name' => $service_name,
            'price' => $price,
            'icon' => $icon
        ];

        if ($default_service_id === null) $default_service_id = $service_id;
    }

    // LAB TESTS CATALOG
    $stmt = $db->prepare("SELECT id, test_name, price, category FROM lab_tests_catalog WHERE is_active = 1 ORDER BY category, test_name");
    $stmt->execute();
    $lab_tests_catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // DOCTORS
    if ($show_all_branches) {
        $stmt = $db->prepare("SELECT id, full_name, specialty, is_online, branch_id FROM users WHERE role = 'doctor' AND status = 'active' ORDER BY is_online DESC, full_name");
        $stmt->execute();
    } else {
        $stmt = $db->prepare("SELECT id, full_name, specialty, is_online, branch_id FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ? ORDER BY is_online DESC, full_name");
        $stmt->execute([$selected_branch_id]);
    }
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($doctors) && !$show_all_branches) {
        $stmt = $db->prepare("SELECT id, full_name, specialty, is_online, branch_id FROM users WHERE role = 'doctor' AND status = 'active' ORDER BY is_online DESC, full_name");
        $stmt->execute();
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

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

    // PATIENTS
    if ($show_all_branches) {
        $stmt = $db->prepare("
            SELECT p.id, p.full_name, p.patient_id, p.phone, p.gender, 
                   p.branch_id, p.assigned_doctor_id, 
                   p.created_at as patient_created_at,
                   u.full_name as assigned_doctor_name,
                   u.is_online as assigned_doctor_online,
                   DATEDIFF(NOW(), p.created_at) as patient_days
            FROM patients p
            LEFT JOIN users u ON p.assigned_doctor_id = u.id
            ORDER BY p.created_at DESC, p.id DESC
        ");
        $stmt->execute();
    } else {
        $stmt = $db->prepare("
            SELECT p.id, p.full_name, p.patient_id, p.phone, p.gender, 
                   p.branch_id, p.assigned_doctor_id, 
                   p.created_at as patient_created_at,
                   u.full_name as assigned_doctor_name,
                   u.is_online as assigned_doctor_online,
                   DATEDIFF(NOW(), p.created_at) as patient_days
            FROM patients p
            LEFT JOIN users u ON p.assigned_doctor_id = u.id
            WHERE p.branch_id = ?
            ORDER BY p.created_at DESC, p.id DESC
        ");
        $stmt->execute([$selected_branch_id]);
    }
    $patients_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($patients_raw) && !$show_all_branches) {
        $branch_has_patients = false;
    }

    // ============================================================
    // ✅ GET LATEST VISIT FOR EACH PATIENT (WITH assigned_by info)
    // ============================================================
    $all_patients = [];
    foreach ($patients_raw as $patient) {
        $stmt = $db->prepare("
            SELECT 
                v.id, 
                v.status, 
                v.visit_number, 
                v.visit_type, 
                v.service_id, 
                v.consultation_fee, 
                v.created_at as visit_created_at, 
                v.doctor_id,
                v.assigned_by_id,
                v.assigned_at,
                u_assigned.full_name as assigned_by_name,
                u_assigned.role as assigned_by_role
            FROM visits v
            LEFT JOIN users u_assigned ON v.assigned_by_id = u_assigned.id
            WHERE v.patient_id = ? 
            AND v.status IN ('new', 'pending', 'assigned', 'with_doctor', 'lab_test', 'waiting', 'prescribed', 'completed')
            ORDER BY v.id DESC
            LIMIT 1
        ");
        $stmt->execute([$patient['id']]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($visit) {
            $patient['visit_id'] = $visit['id'];
            $patient['visit_status'] = $visit['status'];
            $patient['visit_number'] = $visit['visit_number'];
            $patient['visit_type'] = $visit['visit_type'];
            $patient['service_id'] = $visit['service_id'];
            $patient['consultation_fee'] = $visit['consultation_fee'];
            $patient['visit_created_at'] = $visit['visit_created_at'];
            $patient['visit_doctor_id'] = $visit['doctor_id'];
            // ✅ Assigned By info
            $patient['assigned_by_id'] = $visit['assigned_by_id'];
            $patient['assigned_by_name'] = $visit['assigned_by_name'] ?? null;
            $patient['assigned_by_role'] = $visit['assigned_by_role'] ?? null;
            $patient['assigned_at'] = $visit['assigned_at'];
        } else {
            $patient['visit_id'] = null;
            $patient['visit_status'] = null;
            $patient['visit_number'] = null;
            $patient['visit_type'] = null;
            $patient['service_id'] = null;
            $patient['consultation_fee'] = null;
            $patient['visit_created_at'] = null;
            $patient['visit_doctor_id'] = null;
            $patient['assigned_by_id'] = null;
            $patient['assigned_by_name'] = null;
            $patient['assigned_by_role'] = null;
            $patient['assigned_at'] = null;
        }

        $patient['patient_days'] = isset($patient['patient_days']) ? (int)$patient['patient_days'] : 0;
        $all_patients[] = $patient;
    }

    $branch_patients_total = count($all_patients);

    // CATEGORIZE
    foreach ($all_patients as $patient) {
        $status = $patient['visit_status'] ?? '';

        if ($status === 'completed') {
            $complete_patients[] = $patient;
        } elseif ($status === 'lab_test') {
            $stmt = $db->prepare("SELECT COUNT(*) FROM lab_tests WHERE visit_id = ? AND status NOT IN ('completed', 'cancelled')");
            $stmt->execute([$patient['visit_id']]);
            $pending_labs = (int)$stmt->fetchColumn();
            
            if ($pending_labs > 0) {
                $lab_only_patients[] = $patient;
            } else {
                $complete_patients[] = $patient;
            }
        } elseif ($status === 'waiting') {
            $waiting_patients[] = $patient;
        } elseif ($status === 'prescribed') {
            $prescribed_patients[] = $patient;
        } elseif (in_array($status, ['new', 'pending'])) {
            $pending_patients[] = $patient;
        } elseif (in_array($status, ['assigned', 'with_doctor'])) {
            $assigned_patients[] = $patient;
        }
    }

    $assigned_count = count($assigned_patients);
    $lab_only_count = count($lab_only_patients);
    $waiting_count = count($waiting_patients);
    $prescribed_count = count($prescribed_patients);
    $complete_count = count($complete_patients);
    $pending_count = count($pending_patients);

    if ($selected_patient_id > 0) {
        foreach ($all_patients as $p) {
            if ($p['id'] == $selected_patient_id) {
                $selected_patient_data = $p;
                break;
            }
        }
    }

    if ($selected_patient_id > 0) {
        $stmt = $db->prepare("SELECT * FROM vital_signs WHERE patient_id = ? ORDER BY recorded_at DESC LIMIT 1");
        $stmt->execute([$selected_patient_id]);
        $latest_vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // AJAX HANDLERS
    // ============================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'get_live_data') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'assigned_count' => $assigned_count,
                'lab_only_count' => $lab_only_count,
                'waiting_count' => $waiting_count,
                'prescribed_count' => $prescribed_count,
                'complete_count' => $complete_count,
                'pending_count' => $pending_count,
                'branch_patients_total' => $branch_patients_total,
                'online_count' => $online_doctors_count,
                'offline_count' => $offline_doctors_count,
                'total_doctors' => $total_doctors
            ]);
            exit;
        }

        // ============================================================
        // ✅ GET FILTERED LIST (WITH ASSIGNED BY)
        // ============================================================
        if ($action === 'get_filtered_list') {
            header('Content-Type: application/json');
            $status = $_POST['status'] ?? 'assigned';
            $filtered = [];

            if ($status === 'assigned') $filtered = $assigned_patients;
            elseif ($status === 'lab_test') $filtered = $lab_only_patients;
            elseif ($status === 'prescribed') $filtered = $prescribed_patients;
            elseif ($status === 'waiting') $filtered = $waiting_patients;
            elseif ($status === 'complete') $filtered = $complete_patients;

            if (empty($filtered)) {
                echo json_encode(['success' => true, 'html' => '', 'count' => 0]);
                exit;
            }

            $html = '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;">';
            $html .= '<thead><tr style="background:var(--page-gray-50);">';
            $html .= '<th style="padding:12px 16px;text-align:left;font-size:0.68rem;text-transform:uppercase;color:var(--page-text-secondary);">Patient</th>';
            $html .= '<th style="padding:12px 16px;text-align:left;font-size:0.68rem;text-transform:uppercase;color:var(--page-text-secondary);">Patient ID</th>';
            $html .= '<th style="padding:12px 16px;text-align:left;font-size:0.68rem;text-transform:uppercase;color:var(--page-text-secondary);">Doctor</th>';
            $html .= '<th style="padding:12px 16px;text-align:left;font-size:0.68rem;text-transform:uppercase;color:var(--page-text-secondary);">👤 Assigned By</th>';
            $html .= '<th style="padding:12px 16px;text-align:left;font-size:0.68rem;text-transform:uppercase;color:var(--page-text-secondary);">Status</th>';
            $html .= '<th style="padding:12px 16px;text-align:right;font-size:0.68rem;text-transform:uppercase;color:var(--page-text-secondary);">Actions</th>';
            $html .= '</tr></thead><tbody>';

            foreach ($filtered as $p) {
                $days = (int)($p['patient_days'] ?? 0);
                $days_text = $days > 0 ? '<span class="days-badge">' . $days . ' days</span>' : '<span class="days-badge new">New</span>';

                $doctor_html = !empty($p['assigned_doctor_name'])
                    ? '<span class="assigned-doctor-tag"><i class="fas fa-user-md"></i> Dr. ' . htmlspecialchars($p['assigned_doctor_name']) . ' ' . ($p['assigned_doctor_online'] == 1 ? '🟢' : '⚪') . '</span>'
                    : '<span style="color:var(--page-text-secondary);font-size:0.75rem;">No doctor</span>';

                // ✅ ASSIGNED BY - INAONYESHA JINA LA ALIYE ASSIGN
                $assigned_by_html = '';
                if (!empty($p['assigned_by_name'])) {
                    $role_icon = 'fa-user';
                    $role_color = '#0B5ED7';
                    $role_bg = '#E8F0FE';
                    $role = strtolower($p['assigned_by_role'] ?? '');
                    
                    if ($role === 'reception') {
                        $role_icon = 'fa-user-tie';
                        $role_color = '#7C3AED';
                        $role_bg = '#EDE9FE';
                    } elseif ($role === 'admin') {
                        $role_icon = 'fa-user-shield';
                        $role_color = '#D97706';
                        $role_bg = '#FEF3C7';
                    } elseif ($role === 'doctor') {
                        $role_icon = 'fa-user-md';
                        $role_color = '#059669';
                        $role_bg = '#D1FAE5';
                    }
                    
                    $assigned_date = !empty($p['assigned_at']) ? date('M d, H:i', strtotime($p['assigned_at'])) : '';
                    
                    $assigned_by_html = '<div style="display:flex;flex-direction:column;gap:3px;">';
                    $assigned_by_html .= '<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:0.7rem;font-weight:600;background:' . $role_bg . ';color:' . $role_color . ';width:fit-content;">';
                    $assigned_by_html .= '<i class="fas ' . $role_icon . '" style="font-size:0.65rem;"></i>';
                    $assigned_by_html .= htmlspecialchars($p['assigned_by_name']);
                    $assigned_by_html .= '</span>';
                    if ($assigned_date) {
                        $assigned_by_html .= '<span style="font-size:0.62rem;color:var(--page-text-secondary);margin-left:4px;">';
                        $assigned_by_html .= '<i class="fas fa-clock" style="font-size:0.55rem;"></i> ' . $assigned_date;
                        $assigned_by_html .= '</span>';
                    }
                    $assigned_by_html .= '</div>';
                } else {
                    $assigned_by_html = '<span style="color:var(--page-text-secondary);font-size:0.72rem;font-style:italic;">—</span>';
                }

                $status_badge = '';
                if ($status === 'assigned') $status_badge = '<span class="status-badge assigned">✅ Assigned</span>';
                elseif ($status === 'lab_test') $status_badge = '<span class="status-badge lab_only">🧪 Lab Test</span>';
                elseif ($status === 'prescribed') $status_badge = '<span class="status-badge assigned">💊 Prescribed</span>';
                elseif ($status === 'waiting') $status_badge = '<span class="status-badge pending">⏳ Waiting</span>';
                elseif ($status === 'complete') $status_badge = '<span class="status-badge" style="background:#E0F2FE;color:#0891B2;">✅ Complete</span>';

                $actions = '';
                if ($status === 'assigned') {
                    $actions = '<div class="action-group">';
                    $actions .= '<button onclick="reassignDoctor(' . $p['id'] . ', ' . $p['visit_id'] . ')" class="btn-mini btn-mini-danger" title="Reassign"><i class="fas fa-user-minus"></i> Reassign</button>';
                    $actions .= '<button onclick="changeDoctor(' . $p['id'] . ')" class="btn-mini btn-mini-warning" title="Change"><i class="fas fa-sync-alt"></i> Change</button>';
                    $actions .= '</div>';
                } elseif (in_array($status, ['lab_test', 'prescribed', 'waiting'])) {
                    $actions = '<div class="action-group">';
                    $actions .= '<button onclick="completeVisit(' . $p['id'] . ', ' . $p['visit_id'] . ')" class="btn-mini btn-mini-success" title="Complete"><i class="fas fa-check"></i> Complete</button>';
                    $actions .= '<button onclick="cancelVisit(' . $p['id'] . ', ' . $p['visit_id'] . ')" class="btn-mini btn-mini-danger" title="Cancel"><i class="fas fa-times"></i> Cancel</button>';
                    $actions .= '</div>';
                } elseif ($status === 'complete') {
                    $actions = '<span class="status-badge" style="background:#D1FAE5;color:#059669;"><i class="fas fa-check-circle"></i> Completed</span>';
                }

                $html .= '<tr id="patient-row-' . $p['id'] . '" style="border-bottom:1px solid var(--page-border);">';
                $html .= '<td style="padding:12px 16px;font-weight:600;">' . htmlspecialchars($p['full_name']) . ' ' . $days_text . '</td>';
                $html .= '<td style="padding:12px 16px;font-family:monospace;font-size:0.8rem;">' . htmlspecialchars($p['patient_id'] ?? 'N/A') . '</td>';
                $html .= '<td style="padding:12px 16px;">' . $doctor_html . '</td>';
                $html .= '<td style="padding:12px 16px;">' . $assigned_by_html . '</td>';
                $html .= '<td style="padding:12px 16px;">' . $status_badge . '</td>';
                $html .= '<td style="padding:12px 16px;text-align:right;">' . $actions . '</td>';
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

            try {
                $db->beginTransaction();
                $stmt = $db->prepare("UPDATE visits SET doctor_id = NULL, status = 'pending', assigned_by_id = NULL, assigned_at = NULL, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$visit_id]);
                $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL WHERE id = ?");
                $stmt->execute([$patient_id]);
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Doctor removed. Patient returned to Pending.']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        }

        if ($action === 'complete_visit') {
            header('Content-Type: application/json');
            $visit_id = (int)($_POST['visit_id'] ?? 0);
            try {
                $stmt = $db->prepare("UPDATE visits SET status = 'completed', is_completed = 1, completed_at = NOW(), updated_at = NOW() WHERE id = ?");
                $stmt->execute([$visit_id]);
                echo json_encode(['success' => true, 'message' => 'Visit completed!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        }

        if ($action === 'cancel_visit') {
            header('Content-Type: application/json');
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            $visit_id = (int)($_POST['visit_id'] ?? 0);
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("UPDATE visits SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$visit_id]);
                $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL WHERE id = ?");
                $stmt->execute([$patient_id]);
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Visit cancelled.']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        }

        if ($action === 'get_patient_details') {
            header('Content-Type: application/json');
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            try {
                $stmt = $db->prepare("SELECT p.*, u.full_name as assigned_doctor_name, DATEDIFF(NOW(), p.created_at) as patient_days FROM patients p LEFT JOIN users u ON p.assigned_doctor_id = u.id WHERE p.id = ?");
                $stmt->execute([$patient_id]);
                $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($patient) {
                    echo json_encode(['success' => true, 'patient' => $patient, 'assigned_doctor' => $patient['assigned_doctor_name'] ?? null, 'patient_days' => $patient['patient_days'] ?? 0]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        }

        // ============================================================
        // ✅ CHANGE DOCTOR - WITH BILL + NOTIFICATION + ASSIGNED_BY
        // ============================================================
        if ($action === 'change_doctor') {
            header('Content-Type: application/json');
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            $doctor_id = (int)($_POST['doctor_id'] ?? 0);
            $service_id = (int)($_POST['service_id'] ?? 0);
            $symptoms = trim($_POST['symptoms'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $assignment_type = $_POST['assignment_type'] ?? 'doctor';

            $response = ['success' => false, 'message' => ''];

            if ($patient_id <= 0) { $response['message'] = 'Select patient'; echo json_encode($response); exit; }
            if ($assignment_type === 'doctor' && $doctor_id <= 0) { $response['message'] = 'Select doctor'; echo json_encode($response); exit; }

            try {
                $db->beginTransaction();

                $is_lab_only = ($assignment_type === 'lab');
                $service_name = 'General Consultation';
                $consultation_fee = 0;

                if ($service_id > 0 && !$is_lab_only) {
                    $stmt = $db->prepare("SELECT service_name, price FROM services WHERE id = ?");
                    $stmt->execute([$service_id]);
                    $service = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($service) {
                        $service_name = $service['service_name'];
                        $consultation_fee = (float)$service['price'];
                    }
                } elseif ($is_lab_only) {
                    $service_name = 'Lab Tests Only';
                }

                $stmt = $db->prepare("SELECT id, visit_number, branch_id FROM visits WHERE patient_id = ? AND status IN ('new', 'pending', 'assigned', 'with_doctor', 'lab_test', 'waiting', 'prescribed') ORDER BY id DESC LIMIT 1");
                $stmt->execute([$patient_id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                $visit_status = $is_lab_only ? 'lab_test' : 'assigned';
                $visit_id = null;
                $visit_number = '';
                $visit_branch = (!$show_all_branches && $selected_branch_id > 0) ? $selected_branch_id : $user_branch_id;

                if ($existing) {
                    $visit_id = $existing['id'];
                    $visit_number = $existing['visit_number'];
                    $visit_branch = $existing['branch_id'] ?: $visit_branch;
                    
                    // ✅ UPDATE with assigned_by_id and assigned_at
                    $stmt = $db->prepare("
                        UPDATE visits 
                        SET doctor_id = ?, 
                            status = ?, 
                            visit_type = ?, 
                            service_id = ?, 
                            symptoms = ?, 
                            notes = ?, 
                            assigned_by_id = ?,
                            assigned_at = NOW(),
                            updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $is_lab_only ? null : ($doctor_id > 0 ? $doctor_id : null), 
                        $visit_status, 
                        $service_name, 
                        $is_lab_only ? null : $service_id, 
                        $symptoms, 
                        $notes,
                        $user_id,          // ✅ ALIYE ASSIGN
                        $visit_id
                    ]);
                } else {
                    $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // ✅ INSERT with assigned_by_id and assigned_at
                    $stmt = $db->prepare("
                        INSERT INTO visits 
                        (visit_number, patient_id, doctor_id, assigned_by_id, assigned_at, branch_id, visit_type, service_id, status, symptoms, notes, created_at, updated_at, receptionist_id) 
                        VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)
                    ");
                    $stmt->execute([
                        $visit_number, 
                        $patient_id, 
                        $is_lab_only ? null : $doctor_id, 
                        $user_id,          // ✅ ALIYE ASSIGN
                        $visit_branch, 
                        $service_name, 
                        $is_lab_only ? null : $service_id, 
                        $visit_status, 
                        $symptoms, 
                        $notes, 
                        $user_id
                    ]);
                    $visit_id = $db->lastInsertId();
                }

                // Update patient
                if ($is_lab_only) {
                    $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL WHERE id = ?");
                    $stmt->execute([$patient_id]);
                } else {
                    $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = ? WHERE id = ?");
                    $stmt->execute([$doctor_id, $patient_id]);
                }

                // ============================================================
                // ✅ CREATE BILL WITH ITEMS
                // ============================================================
                $bill_created = false;
                $bill_number = null;
                $bill_id = null;
                $total_bill_amount = 0;
                $lab_test_names = [];
                $lab_count = 0;

                $stmt = $db->prepare("SELECT id, bill_number FROM bills WHERE visit_id = ? AND status IN ('pending', 'partial') LIMIT 1");
                $stmt->execute([$visit_id]);
                $existing_bill = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing_bill) {
                    $bill_id = $existing_bill['id'];
                    $bill_number = $existing_bill['bill_number'];
                } else {
                    $prefix = $is_lab_only ? 'BILL-LAB' : 'BILL-CONS';
                    $bill_number = $prefix . '-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999);
                    $stmt = $db->prepare("INSERT INTO bills (bill_number, patient_id, visit_id, branch_id, created_by, subtotal, total_amount, balance, status, created_at) VALUES (?, ?, ?, ?, ?, 0, 0, 0, 'pending', NOW())");
                    $stmt->execute([$bill_number, $patient_id, $visit_id, $visit_branch, $user_id]);
                    $bill_id = $db->lastInsertId();
                }

                // ✅ ADD LAB TESTS (with requested_by_id)
                if ($is_lab_only && !empty($_POST['lab_test_ids'])) {
                    $lab_ids = $_POST['lab_test_ids'];
                    if (!is_array($lab_ids)) $lab_ids = [$lab_ids];

                    $total_lab_fee = 0;
                    foreach ($lab_ids as $test_id) {
                        $test_id = (int)$test_id;
                        $stmt = $db->prepare("SELECT test_name, price FROM lab_tests_catalog WHERE id = ?");
                        $stmt->execute([$test_id]);
                        $test = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($test) {
                            // ✅ Ongeza requested_by_id na requested_at
                            $stmt = $db->prepare("
                                INSERT INTO lab_tests 
                                (visit_id, patient_id, test_id, test_name, test_price, status, branch_id, requested_by_id, requested_at, created_at) 
                                VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())
                            ");
                            $stmt->execute([
                                $visit_id, $patient_id, $test_id, $test['test_name'], $test['price'], 
                                $visit_branch, $user_id
                            ]);
                            $lab_test_id = $db->lastInsertId();
                            
                            $stmt = $db->prepare("
                                INSERT INTO bill_items 
                                (bill_id, patient_id, branch_id, item_type, item_name, quantity, unit_price, total_price, status, reference_type, reference_id, created_at) 
                                VALUES (?, ?, ?, 'lab_test', ?, 1, ?, ?, 'pending', 'lab_test', ?, NOW())
                            ");
                            $stmt->execute([
                                $bill_id, $patient_id, $visit_branch, 
                                $test['test_name'], $test['price'], $test['price'], 
                                $lab_test_id
                            ]);
                            
                            $total_lab_fee += (float)$test['price'];
                            $lab_test_names[] = $test['test_name'];
                            $lab_count++;
                        }
                    }
                    
                    if ($total_lab_fee > 0) {
                        $stmt = $db->prepare("UPDATE bills SET subtotal = subtotal + ?, total_amount = total_amount + ?, balance = balance + ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$total_lab_fee, $total_lab_fee, $total_lab_fee, $bill_id]);
                        
                        $stmt = $db->prepare("UPDATE visits SET lab_fees_total = COALESCE(lab_fees_total, 0) + ? WHERE id = ?");
                        $stmt->execute([$total_lab_fee, $visit_id]);
                        
                        $total_bill_amount += $total_lab_fee;
                    }
                }

                // ADD CONSULTATION
                if (!$is_lab_only && $consultation_fee > 0) {
                    $stmt = $db->prepare("
                        INSERT INTO bill_items 
                        (bill_id, patient_id, branch_id, item_type, item_name, quantity, unit_price, total_price, status, reference_type, created_at) 
                        VALUES (?, ?, ?, 'consultation', ?, 1, ?, ?, 'pending', 'visit', NOW())
                    ");
                    $stmt->execute([
                        $bill_id, $patient_id, $visit_branch, 
                        $service_name, $consultation_fee, $consultation_fee
                    ]);
                    
                    $stmt = $db->prepare("UPDATE bills SET subtotal = subtotal + ?, total_amount = total_amount + ?, balance = balance + ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$consultation_fee, $consultation_fee, $consultation_fee, $bill_id]);
                    
                    $stmt = $db->prepare("UPDATE visits SET consultation_fee = ? WHERE id = ?");
                    $stmt->execute([$consultation_fee, $visit_id]);
                    
                    $total_bill_amount += $consultation_fee;
                }

                // NOTIFY CASHIERS
                if ($total_bill_amount > 0 && $bill_id) {
                    try {
                        $stmt = $db->prepare("SELECT full_name, patient_id FROM patients WHERE id = ?");
                        $stmt->execute([$patient_id]);
                        $patient_info = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $stmt = $db->prepare("SELECT id FROM users WHERE role = 'cashier' AND status = 'active' AND branch_id = ?");
                        $stmt->execute([$visit_branch]);
                        $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (empty($cashiers)) {
                            $stmt = $db->query("SELECT id FROM users WHERE role = 'cashier' AND status = 'active'");
                            $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        }
                        
                        if ($is_lab_only) {
                            $title = '🧪 Lab Test Bill Created';
                            $message = 'Lab Test bill #' . $bill_number 
                                     . ' (TSh ' . number_format($total_bill_amount, 0) . ') '
                                     . 'for patient ' . $patient_info['full_name'] 
                                     . ' (ID: ' . $patient_info['patient_id'] . ')'
                                     . ' — ' . implode(', ', $lab_test_names);
                        } else {
                            $title = '💰 Consultation Bill Created';
                            $message = 'Consultation bill #' . $bill_number 
                                     . ' (TSh ' . number_format($total_bill_amount, 0) . ') '
                                     . 'for patient ' . $patient_info['full_name'] 
                                     . ' (ID: ' . $patient_info['patient_id'] . ')'
                                     . ' — ' . $service_name;
                        }
                        
                        foreach ($cashiers as $cashier) {
                            $stmt = $db->prepare("
                                INSERT INTO notifications 
                                (user_id, branch_id, title, message, type, link, is_read, created_at) 
                                VALUES (?, ?, ?, ?, 'bill', ?, 0, NOW())
                            ");
                            $stmt->execute([
                                $cashier['id'], 
                                $visit_branch, 
                                $title, 
                                $message,
                                '/dispensary_system/frontend/pages/cashier/dashboard.php'
                            ]);
                        }
                        
                        $bill_created = true;
                        
                        try {
                            $stmt = $db->prepare("
                                INSERT INTO activity_logs 
                                (user_id, branch_id, action, details, created_at) 
                                VALUES (?, ?, 'bill_created', ?, NOW())
                            ");
                            $stmt->execute([
                                $user_id, 
                                $visit_branch,
                                ($is_lab_only ? 'Lab Test' : 'Consultation') . ' bill #' . $bill_number 
                                . ' - TSh ' . number_format($total_bill_amount, 0) 
                                . ' for ' . $patient_info['full_name']
                            ]);
                        } catch (Exception $e) {}
                        
                    } catch (Exception $e) {
                        error_log("Cashier notification error: " . $e->getMessage());
                    }
                }

                // Vitals
                $temperature = $_POST['temperature'] ?? null;
                $bp_systolic = $_POST['bp_systolic'] ?? null;
                $bp_diastolic = $_POST['bp_diastolic'] ?? null;
                $pulse_rate = $_POST['pulse_rate'] ?? null;
                $weight = $_POST['weight'] ?? null;
                $height = $_POST['height'] ?? null;
                $oxygen_saturation = $_POST['oxygen_saturation'] ?? null;

                if ($temperature || $bp_systolic || $bp_diastolic || $pulse_rate || $weight || $height || $oxygen_saturation) {
                    $bmi = null;
                    if ($weight && $height && $height > 0) {
                        $hm = $height / 100;
                        $bmi = round($weight / ($hm * $hm), 1);
                    }
                    $stmt = $db->prepare("INSERT INTO vital_signs (patient_id, visit_id, recorded_by, branch_id, temperature, blood_pressure_systolic, blood_pressure_diastolic, pulse_rate, weight, height, bmi, oxygen_saturation, recorded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$patient_id, $visit_id, $user_id, $visit_branch, $temperature ?: null, $bp_systolic ?: null, $bp_diastolic ?: null, $pulse_rate ?: null, $weight ?: null, $height ?: null, $bmi, $oxygen_saturation ?: null]);
                }

                $db->commit();

                $doctor_name = 'Doctor';
                if ($doctor_id > 0) {
                    $stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
                    $stmt->execute([$doctor_id]);
                    $doctor_name = $stmt->fetch(PDO::FETCH_ASSOC)['full_name'] ?? 'Doctor';
                }

                $success_msg = $is_lab_only 
                    ? "🧪 {$lab_count} Lab test(s) requested! Visit: {$visit_number}" 
                    : "✅ Dr. {$doctor_name} assigned! Visit: {$visit_number}";
                
                if ($bill_created) {
                    $success_msg .= " 💰 Bill #{$bill_number} (TSh " . number_format($total_bill_amount, 0) . ") sent to Cashier!";
                }

                $response['success'] = true;
                $response['message'] = $success_msg;
                $response['patient_id'] = $patient_id;
                $response['bill_created'] = $bill_created;
                $response['bill_number'] = $bill_number;
                $response['bill_amount'] = $total_bill_amount;

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
}

$common_symptoms = ['Fever', 'Headache', 'Cough', 'Sore Throat', 'Body Pain', 'Fatigue', 'Nausea', 'Vomiting', 'Diarrhea', 'Chest Pain', 'Shortness of Breath', 'Abdominal Pain', 'Dizziness', 'Rash', 'Swelling'];

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/admin_header.php';

if ($user_role === 'admin') {
    include_once __DIR__ . '/../../components/admin_sidebar.php';
} else {
    if (file_exists(__DIR__ . '/../../components/reception_sidebar.php')) {
        include_once __DIR__ . '/../../components/reception_sidebar.php';
    } else {
        include_once __DIR__ . '/../../components/admin_sidebar.php';
    }
}
?>

<style>
:root {
    --page-primary: #0B5ED7;
    --page-primary-bg: #E8F0FE;
    --page-primary-light: #6EA8FE;
    --page-success: #059669;
    --page-success-bg: #D1FAE5;
    --page-danger: #DC2626;
    --page-danger-bg: #FEE2E2;
    --page-warning: #D97706;
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
}
[data-theme="dark"] {
    --page-bg-body: #0F172A;
    --page-bg-card: #1E293B;
    --page-text-primary: #F1F5F9;
    --page-text-secondary: #94A3B8;
    --page-border: #334155;
    --page-primary-bg: #1E3A5F;
    --page-purple-bg: #2D1B5F;
}
html[data-theme="dark"] body, html[data-theme="dark"] .main-content { background: #0F172A !important; }

.btn-mini {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 0.7rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.25s ease;
    white-space: nowrap;
    min-height: 28px;
    min-width: 85px;
    letter-spacing: 0.01em;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
.btn-mini:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
.btn-mini:active { transform: translateY(0); }
.btn-mini i { font-size: 0.65rem; }
.btn-mini-success { background: linear-gradient(135deg, #059669, #047857); color: white; }
.btn-mini-success:hover { background: linear-gradient(135deg, #047857, #065F46); }
.btn-mini-danger { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; }
.btn-mini-danger:hover { background: linear-gradient(135deg, #B91C1C, #991B1B); }
.btn-mini-warning { background: linear-gradient(135deg, #D97706, #B45309); color: white; }
.btn-mini-warning:hover { background: linear-gradient(135deg, #B45309, #92400E); }

.action-group {
    display: flex;
    gap: 6px;
    justify-content: flex-end;
    flex-wrap: nowrap;
    align-items: center;
}

.no-patients-notice {
    background: linear-gradient(135deg, #FEF3C7, #FDE68A);
    border: 2px solid #D97706;
    border-radius: 16px;
    padding: 24px 28px;
    margin: 0 auto 24px;
    max-width: 1300px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.no-patients-notice i { font-size: 2rem; color: #D97706; flex-shrink: 0; }
.no-patients-notice strong { color: #92400E; font-size: 1.05rem; display: block; margin-bottom: 4px; }
.no-patients-notice p { color: #78350F; font-size: 0.85rem; margin: 0; }
[data-theme="dark"] .no-patients-notice { background: #3D2E0A; border-color: #D97706; }
[data-theme="dark"] .no-patients-notice strong { color: #FBBF24; }
[data-theme="dark"] .no-patients-notice p { color: #FDE68A; }

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
    box-shadow: 0 8px 32px rgba(37, 99, 235, 0.3);
    max-width: 1300px;
    margin-left: auto;
    margin-right: auto;
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
}
.page-header-title i {
    width: 44px; height: 44px;
    background: rgba(255,255,255,0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}
.page-header-subtitle {
    font-size: 0.9rem;
    color: rgba(255,255,255,0.9);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
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
}
.role-badge-display {
    background: rgba(255,255,255,0.25);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
}
.live-indicator {
    display: inline-block;
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #34D399;
    animation: pulse-dot 1.5s infinite;
    margin-right: 4px;
}
@keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
.page-header-actions { display: flex; gap: 8px; flex-wrap: wrap; }
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
    cursor: pointer;
    transition: all 0.3s;
}
.btn-outline-light:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
    color: white;
}

.status-toggle-group {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    background: var(--page-bg-card);
    padding: 12px 16px;
    border-radius: 14px;
    border: 2px solid var(--page-border);
    box-shadow: var(--page-shadow-md);
    max-width: 1300px;
    margin: 0 auto 20px;
}
.status-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 2px solid var(--page-border);
    background: var(--page-bg-body);
    color: var(--page-text-secondary);
    cursor: pointer;
    transition: all 0.3s;
    font-family: inherit;
}
.status-toggle-btn:hover {
    border-color: var(--page-primary);
    color: var(--page-primary);
    transform: translateY(-1px);
}
.status-toggle-btn.active {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    border-color: var(--page-primary);
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}
.status-toggle-btn.active[data-status="lab_test"] {
    background: linear-gradient(135deg, #7C3AED, #5B21B6);
    border-color: #7C3AED;
}
.status-toggle-btn.active[data-status="prescribed"] {
    background: linear-gradient(135deg, #059669, #047857);
    border-color: #059669;
}
.status-toggle-btn.active[data-status="waiting"] {
    background: linear-gradient(135deg, #D97706, #B45309);
    border-color: #D97706;
}
.status-toggle-btn.active[data-status="complete"] {
    background: linear-gradient(135deg, #0891B2, #0E7490);
    border-color: #0891B2;
}
.toggle-count {
    background: rgba(255,255,255,0.25);
    padding: 1px 10px;
    border-radius: 10px;
    font-size: 0.65rem;
    font-weight: 700;
}

.modern-card {
    background: var(--page-bg-card);
    border-radius: 18px;
    border: 1px solid var(--page-border);
    box-shadow: var(--page-shadow-md);
    margin: 0 auto 20px;
    max-width: 1300px;
    overflow: hidden;
}
.modern-card-header {
    padding: 18px 24px;
    background: var(--page-gray-50);
    border-bottom: 2px solid var(--page-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
html[data-theme="dark"] .modern-card-header { background: #0F172A; border-bottom-color: #334155; }
.modern-card-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--page-text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
}
.modern-card-title .title-icon {
    width: 36px; height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #2563EB, #1D4ED8);
    color: white;
}
.modern-card-title .title-icon.success { background: linear-gradient(135deg, #059669, #047857); }
.modern-card-badge {
    background: var(--page-primary-bg);
    color: var(--page-primary);
    padding: 3px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    border: 1.5px solid var(--page-primary-light);
}
.modern-card-badge.success {
    background: var(--page-success-bg);
    color: var(--page-success);
    border-color: var(--page-success);
}
.modern-card-body { padding: 0; }

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 700;
}
.status-badge.pending { background: #FEF3C7; color: #D97706; }
.status-badge.assigned { background: #D1FAE5; color: #059669; }
.status-badge.lab_only { background: #EDE9FE; color: #7C3AED; border: 1.5px dashed #7C3AED; }

.days-badge {
    display: inline-block;
    background: var(--page-primary);
    color: white;
    padding: 2px 12px;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 600;
}
.days-badge.new { background: var(--page-success); }

.assigned-doctor-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 600;
    background: #D1FAE5;
    color: #047857;
    border: 1.5px solid #34D399;
}

.patient-toggle-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 12px 16px;
    background: var(--page-bg-card);
    border: 2px solid var(--page-primary);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--page-primary);
    font-family: inherit;
}
.patient-toggle-btn:hover {
    background: var(--page-primary-bg);
    transform: translateY(-1px);
}
.patient-toggle-btn.active {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
}
.patient-toggle-btn .toggle-arrow { transition: transform 0.3s; }
.patient-toggle-btn.active .toggle-arrow { transform: rotate(180deg); }

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

.patient-search-wrapper { position: relative; margin-bottom: 10px; }
.patient-search-wrapper i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--page-text-secondary);
    pointer-events: none;
}
.patient-search-wrapper input {
    width: 100%;
    padding: 10px 14px 10px 36px;
    border: 2px solid var(--page-border);
    border-radius: 12px;
    font-size: 0.8rem;
    outline: none;
    background: var(--page-bg-card);
    color: var(--page-text-primary);
    font-family: inherit;
}
.patient-search-wrapper input:focus {
    border-color: var(--page-primary);
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
}

.patient-list-container {
    max-height: 320px;
    overflow-y: auto;
    border: 1px solid var(--page-border);
    border-radius: 12px;
    background: var(--page-bg-card);
}
.patient-list-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--page-border);
    cursor: pointer;
    font-size: 0.8rem;
    transition: background 0.2s;
}
.patient-list-item:hover { background: var(--page-primary-bg); }
.patient-list-item.selected {
    background: var(--page-primary-bg);
    border-left: 4px solid var(--page-primary);
}
.patient-list-item .patient-info { flex: 1; }
.patient-list-item .patient-name {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--page-text-primary);
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.patient-list-item .patient-meta {
    font-size: 0.7rem;
    color: var(--page-text-secondary);
    margin-top: 2px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.form-card-modern {
    background: var(--page-bg-card);
    border-radius: 20px;
    padding: 28px 32px;
    border: 2px solid var(--page-border);
    max-width: 1300px;
    margin: 0 auto 24px;
    box-shadow: var(--page-shadow-md);
}
.form-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding-bottom: 20px;
    margin-bottom: 24px;
    border-bottom: 2px solid var(--page-border);
}
.form-header-icon {
    width: 56px; height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    background: linear-gradient(135deg, #2563EB, #1D4ED8);
    color: white;
}
.form-header h3 {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--page-text-primary);
    margin: 0 0 4px 0;
}
.form-header p { font-size: 0.8rem; color: var(--page-text-secondary); margin: 0; }

.form-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--page-text-primary);
    margin-bottom: 6px;
    display: block;
}
.form-label .required { color: var(--page-danger); }
.form-label .label-icon { margin-right: 4px; color: var(--page-primary); }

.form-control-modern {
    width: 100%;
    padding: 11px 16px;
    border: 2px solid var(--page-border);
    border-radius: 12px;
    font-size: 0.85rem;
    outline: none;
    background: var(--page-bg-card);
    color: var(--page-text-primary);
    font-family: inherit;
    transition: all 0.3s;
}
.form-control-modern:focus {
    border-color: var(--page-primary);
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
}

.grid-2-modern { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.form-row-modern { margin-bottom: 20px; }

.vital-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 12px; }
.vital-grid-row2 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
.vital-card {
    background: var(--page-bg-body);
    border-radius: 14px;
    padding: 14px 16px;
    border: 2px solid var(--page-border);
    position: relative;
    min-height: 100px;
    display: flex;
    flex-direction: column;
}
.vital-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    border-radius: 14px 14px 0 0;
}
.vital-card.temperature::before { background: linear-gradient(90deg, #DC2626, #F87171); }
.vital-card.bp::before { background: linear-gradient(90deg, #2563EB, #60A5FA); }
.vital-card.pulse::before { background: linear-gradient(90deg, #059669, #34D399); }
.vital-card.weight::before { background: linear-gradient(90deg, #D97706, #FBBF24); }
.vital-card.height::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }
.vital-card.bmi::before { background: linear-gradient(90deg, #0D9488, #2DD4BF); }
.vital-card.spo2::before { background: linear-gradient(90deg, #0891B2, #22D3EE); }
.vital-card.bmi { background: rgba(37, 99, 235, 0.08); border-color: var(--page-primary); }
.vital-card.spo2 { background: rgba(8, 145, 178, 0.08); border-color: #0891B2; }

.vital-header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.vital-label {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--page-text-primary);
    text-transform: uppercase;
}
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
.vital-unit { font-size: 0.65rem; color: var(--page-text-secondary); font-weight: 600; display: block; margin-top: 2px; }

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
.spo2-status.critical { background: rgba(220, 38, 38, 0.15); color: #DC2626; }

.lab-modal-container-modern {
    background: var(--page-bg-card);
    border-radius: 12px;
    border: 2px solid var(--page-purple);
    overflow: hidden;
}
.lab-modal-header-modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 18px;
    background: var(--page-purple-bg);
    border-bottom: 2px solid var(--page-border);
}
.lab-test-scroll { max-height: 320px; overflow-y: auto; }
.lab-test-item-modern {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 18px;
    border-bottom: 1px solid var(--page-border);
    cursor: pointer;
}
.lab-test-item-modern:hover { background: var(--page-primary-bg); }
.lab-test-item-modern .lab-test-checkbox {
    width: 18px; height: 18px;
    accent-color: var(--page-purple);
    cursor: pointer;
}
.lab-test-item-modern label {
    flex: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
}
.lab-test-item-modern .lab-test-category {
    font-size: 0.55rem;
    background: var(--page-gray-100);
    color: var(--page-text-secondary);
    padding: 2px 10px;
    border-radius: 10px;
}
.lab-test-item-modern .lab-test-price {
    font-size: 0.8rem;
    color: var(--page-success);
    font-weight: 700;
}
.lab-test-item-modern.checked {
    background: var(--page-purple-bg);
    border-left: 4px solid var(--page-purple);
}
.lab-modal-footer-modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 18px;
    border-top: 2px solid var(--page-border);
    background: var(--page-bg-body);
    flex-wrap: wrap;
    gap: 8px;
}

.btn-modern {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 24px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    border: none;
    text-decoration: none;
    min-height: 44px;
    font-family: inherit;
    transition: all 0.3s;
}
.btn-modern-primary {
    background: linear-gradient(135deg, #2563EB, #1D4ED8);
    color: white;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
}
.btn-modern-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
    color: white;
}
.btn-modern-outline {
    background: transparent;
    color: var(--page-text-primary);
    border: 2px solid var(--page-border);
}
.btn-modern-outline:hover {
    background: var(--page-gray-50);
    border-color: var(--page-primary);
    color: var(--page-primary);
}
.btn-modern-sm { padding: 7px 16px; font-size: 0.78rem; min-height: 36px; }

.form-actions-modern {
    display: flex;
    gap: 12px;
    padding-top: 24px;
    margin-top: 24px;
    border-top: 2px solid var(--page-border);
    flex-wrap: wrap;
}

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
    position: relative;
    box-shadow: var(--page-shadow);
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
}
.stat-card.pending::before { background: linear-gradient(90deg, #D97706, #F59E0B); }
.stat-card.assigned::before { background: linear-gradient(90deg, #059669, #34D399); }
.stat-card.doctors::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }
.stat-icon { font-size: 2rem; margin-bottom: 10px; display: block; }
.stat-number { font-size: 2.2rem; font-weight: 800; margin: 0 0 6px 0; }
.stat-number.pending { color: #D97706; }
.stat-number.assigned { color: #059669; }
.stat-number.doctors { color: #7C3AED; }
.stat-label { font-size: 0.78rem; color: var(--page-text-secondary); font-weight: 600; margin: 0; text-transform: uppercase; }

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

.empty-state { padding: 40px 20px; text-align: center; color: var(--page-text-secondary); }
.empty-state i { font-size: 2.5rem; display: block; margin-bottom: 12px; opacity: 0.5; }

@media (max-width: 768px) {
    .grid-2-modern { grid-template-columns: 1fr; }
    .vital-grid { grid-template-columns: repeat(2, 1fr); }
    .vital-grid-row2 { grid-template-columns: repeat(2, 1fr); }
    .page-header-card { padding: 20px; }
    
    .btn-mini {
        width: 30px;
        height: 30px;
        min-width: 30px;
        padding: 0;
        justify-content: center;
    }
    .btn-mini span { display: none; }
    .btn-mini i { font-size: 0.75rem; margin: 0; }
    .action-group { justify-content: flex-end; }
}
</style>

<main class="main-content">

    <?php if (!$branch_has_patients): ?>
    <div class="no-patients-notice">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>⚠️ Hakuna Patients kwenye <?= htmlspecialchars($branch_name) ?></strong>
            <p>Branch <strong><?= htmlspecialchars($branch_name) ?></strong> haina patients bado. Chagua branch nyingine au ongeza patients kwanza.</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="page-header-card">
        <div>
            <h1 class="page-header-title">
                <i class="fas fa-user-md"></i>
                Assign / Change / Reassign Doctor
                <span class="role-badge-display"><?= $user_role === 'admin' ? 'ADMIN' : 'RECEPTION' ?></span>
                <span class="page-header-badge" style="background:rgba(52,211,153,0.25);">
                    <span class="live-indicator"></span> Live
                </span>
            </h1>
            <p class="page-header-subtitle">
                <i class="fas fa-hospital"></i>
                Manage patients in <strong><?= htmlspecialchars($branch_name) ?></strong>
                <span class="page-header-badge"><i class="fas fa-user-md"></i> <span style="color:#34D399;"><?= $online_doctors_count ?></span> Online</span>
                <span class="page-header-badge"><i class="fas fa-user-check"></i> <span id="assignedCountHeader"><?= $assigned_count ?></span> Assigned</span>
                <span class="page-header-badge" style="background:rgba(124,58,237,0.25);"><i class="fas fa-flask"></i> <span id="labCountHeader"><?= $lab_only_count ?></span> Lab</span>
                <span class="page-header-badge" style="background:rgba(217,119,6,0.25);"><i class="fas fa-clock"></i> <span id="waitingCountHeader"><?= $waiting_count ?></span> Waiting</span>
            </p>
        </div>
        <div class="page-header-actions">
            <a href="<?= $user_role === 'admin' ? '../admin/dashboard.php' : 'dashboard.php' ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <button onclick="location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div style="max-width:1300px;margin:0 auto 20px;padding:16px 20px;border-radius:14px;background:<?= $message_type === 'success' ? '#D1FAE5' : '#FEE2E2' ?>;color:<?= $message_type === 'success' ? '#047857' : '#B91C1C' ?>;display:flex;gap:12px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <div class="status-toggle-group">
        <span style="font-size:0.75rem;font-weight:600;color:var(--page-text-secondary);margin-right:8px;">
            <i class="fas fa-filter"></i> Filter:
        </span>
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
            <i class="fas fa-check-circle"></i> Complete <span class="toggle-count" id="toggleCompleteCount"><?= $complete_count ?></span>
        </button>
        <span style="margin-left:auto;font-size:0.7rem;color:var(--page-text-secondary);">
            <i class="fas fa-users"></i> Total: <strong id="totalBranchPatients"><?= $branch_patients_total ?></strong>
        </span>
    </div>

    <div class="modern-card">
        <div class="modern-card-header">
            <div class="modern-card-title">
                <div class="title-icon success"><i class="fas fa-user-check" id="listIcon"></i></div>
                <span id="listTitle">Assigned Patients</span>
                <span class="modern-card-badge success" id="listCountBadge"><?= $assigned_count ?></span>
            </div>
            <span style="font-size:0.7rem;color:var(--page-text-secondary);" id="listUpdateTime">(Auto <?= date('h:i:s A') ?>)</span>
        </div>
        <div class="modern-card-body" id="patientsListContainer">
            <div style="text-align:center;padding:40px;">
                <div class="spinner" style="border-color:var(--page-border);border-top-color:var(--page-primary);"></div>
                <p style="font-size:0.8rem;color:var(--page-text-secondary);margin-top:8px;">Loading...</p>
            </div>
        </div>
    </div>

    <div class="form-card-modern" id="mainFormCard">
        <div class="form-header">
            <div class="form-header-icon">
                <i class="fas fa-stethoscope"></i>
            </div>
            <div>
                <h3>Assign / Change Doctor or Lab Test</h3>
                <p>Select patient and assign a doctor OR request lab tests — <strong><?= htmlspecialchars($branch_name) ?></strong></p>
            </div>
        </div>

        <form method="POST" action="?branch=<?= urlencode($selected_branch_id) ?>" id="assignForm">
            <input type="hidden" name="action" value="change_doctor">
            <input type="hidden" name="patient_id" id="selectedPatientInput" value="<?= $selected_patient_id ?>">
            <input type="hidden" name="branch_id" value="<?= $selected_branch_id ?>">

            <div class="grid-2-modern">
                <div>
                    <div class="form-row-modern">
                        <label class="form-label">
                            <i class="fas fa-user label-icon"></i> Select Patient <span class="required">*</span>
                            <span style="font-weight:400;font-size:0.65rem;padding:2px 10px;border-radius:12px;background:var(--page-gray-100);color:var(--page-text-secondary);margin-left:6px;" id="patientCountBadge">All Patients (<?= $branch_patients_total ?>)</span>
                        </label>

                        <button type="button" class="patient-toggle-btn" id="patientToggleBtn" onclick="togglePatientList()">
                            <span>
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
                                <?php if (!empty($all_patients)): ?>
                                    <?php foreach ($all_patients as $patient):
                                        $status_class = 'no_visit';
                                        $status_icon = '📋';
                                        $status_label = 'No Visit';

                                        $vs = $patient['visit_status'] ?? '';
                                        if ($vs === 'completed') { $status_label = 'Complete'; $status_class = 'assigned'; $status_icon = '✅'; }
                                        elseif ($vs === 'lab_test') { $status_label = 'Lab Test'; $status_class = 'lab_only'; $status_icon = '🧪'; }
                                        elseif ($vs === 'waiting') { $status_label = 'Waiting'; $status_class = 'pending'; $status_icon = '⏳'; }
                                        elseif ($vs === 'prescribed') { $status_label = 'Prescribed'; $status_class = 'assigned'; $status_icon = '💊'; }
                                        elseif (in_array($vs, ['new', 'pending'])) { $status_label = 'Pending'; $status_class = 'pending'; $status_icon = '🟡'; }
                                        elseif (in_array($vs, ['assigned', 'with_doctor'])) { $status_label = 'Assigned'; $status_class = 'assigned'; $status_icon = '✅'; }

                                        $doctor_info = '';
                                        if (!empty($patient['assigned_doctor_name'])) {
                                            $online_status = !empty($patient['assigned_doctor_online']) ? '🟢' : '⚪';
                                            $doctor_info = 'Dr. ' . htmlspecialchars($patient['assigned_doctor_name']) . ' ' . $online_status;
                                        }

                                        $is_selected = ($selected_patient_id == $patient['id']) ? 'selected' : '';
                                        $days = (int)($patient['patient_days'] ?? 0);
                                        $days_text = $days > 0 ? '📅 ' . $days . 'd' : '📅 New';
                                        $search_data = strtolower(($patient['full_name'] ?? '') . ' ' . ($patient['patient_id'] ?? '') . ' ' . ($patient['phone'] ?? ''));
                                    ?>
                                        <div class="patient-list-item <?= $is_selected ?>"
                                             data-patient-id="<?= $patient['id'] ?>"
                                             data-search="<?= htmlspecialchars($search_data) ?>"
                                             onclick="selectPatient(<?= $patient['id'] ?>, '<?= htmlspecialchars(addslashes($patient['full_name'])) ?>', '<?= htmlspecialchars($patient['patient_id'] ?? '') ?>')">
                                            <span style="font-size:1.2rem;"><?= $status_icon ?></span>
                                            <div class="patient-info">
                                                <div class="patient-name">
                                                    <?= htmlspecialchars($patient['full_name']) ?>
                                                    <span class="days-badge"><?= $days_text ?></span>
                                                </div>
                                                <div class="patient-meta">
                                                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span>
                                                    <span class="status-badge <?= $status_class ?>"><?= $status_label ?></span>
                                                    <?php if ($doctor_info): ?>
                                                        <span style="color:var(--page-primary);">👨‍⚕️ <?= $doctor_info ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-user-slash"></i>
                                        <p><strong>Hakuna patients kwenye <?= htmlspecialchars($branch_name) ?></strong></p>
                                        <p style="font-size:0.7rem;margin-top:8px;color:var(--page-text-secondary);">Chagua branch nyingine au ongeza patients kwanza.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-row-modern">
                        <label class="form-label"><i class="fas fa-tasks label-icon"></i> Select Action <span class="required">*</span></label>
                        <select name="assignment_type" class="form-control-modern" required id="assignmentTypeSelect" onchange="toggleAssignmentType(this.value)">
                            <option value="doctor">👨‍⚕️ Assign Doctor</option>
                            <option value="lab">🧪 Request Lab Test(s) (No Doctor)</option>
                        </select>
                        <p style="font-size:0.7rem;color:var(--page-text-secondary);margin-top:6px;" id="assignmentTypeHelp">
                            👨‍⚕️ Assign a doctor to the patient or change existing doctor
                        </p>
                    </div>

                    <div class="form-row-modern" id="doctorSelectCard">
                        <label class="form-label"><i class="fas fa-user-md label-icon"></i> Select Doctor <span class="required">*</span></label>
                        <select name="doctor_id" class="form-control-modern" required id="doctorSelect">
                            <option value="">-- Select Doctor --</option>
                            <?php if (!empty($online_doctors)): ?>
                                <optgroup label="🟢 Online (<?= $online_doctors_count ?>)">
                                    <?php foreach ($online_doctors as $doctor): ?>
                                        <option value="<?= $doctor['id'] ?>" data-online="1">🟢 Dr. <?= htmlspecialchars($doctor['full_name']) ?><?= !empty($doctor['specialty']) ? ' (' . htmlspecialchars($doctor['specialty']) . ')' : '' ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <?php if (!empty($offline_doctors)): ?>
                                <optgroup label="⚪ Offline (<?= $offline_doctors_count ?>)">
                                    <?php foreach ($offline_doctors as $doctor): ?>
                                        <option value="<?= $doctor['id'] ?>" data-online="0">⚪ Dr. <?= htmlspecialchars($doctor['full_name']) ?><?= !empty($doctor['specialty']) ? ' (' . htmlspecialchars($doctor['specialty']) . ')' : '' ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                        <p style="font-size:0.7rem;color:var(--page-text-secondary);margin-top:6px;">
                            <span style="color:var(--page-success);font-weight:600;">🟢 <?= $online_doctors_count ?> online</span>
                            <span style="margin:0 4px;">|</span>
                            <span>⚪ <?= $offline_doctors_count ?> offline</span>
                        </p>
                    </div>
                </div>

                <div>
                    <div class="form-row-modern" id="visitTypeSection">
                        <label class="form-label">
                            <i class="fas fa-tag label-icon"></i> Visit Type <span class="required">*</span>
                            <span style="font-weight:400;font-size:0.65rem;padding:2px 10px;border-radius:12px;background:var(--page-gray-100);color:var(--page-text-secondary);margin-left:6px;" id="visitTypePrice">
                                Fee: TSh <?= isset($visit_type_options[$default_service_id]) ? number_format($visit_type_options[$default_service_id]['price'] ?? 0, 0) : '0' ?>
                            </span>
                        </label>
                        <select name="service_id" class="form-control-modern" id="visitTypeSelect" onchange="updateVisitTypePrice()">
                            <?php if (!empty($visit_type_options)): ?>
                                <?php foreach ($visit_type_options as $sid => $option): ?>
                                    <option value="<?= $sid ?>" data-price="<?= $option['price'] ?? 0 ?>" <?= $sid === $default_service_id ? 'selected' : '' ?>>
                                        <?= $option['icon'] ?? '🏥' ?> <?= htmlspecialchars($option['service_name']) ?> - TSh <?= number_format($option['price'] ?? 0, 0) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="">❌ No Visit Type Available</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-row-modern">
                        <label class="form-label"><i class="fas fa-notes-medical label-icon"></i> Symptoms</label>
                        <select name="symptoms_select" class="form-control-modern" id="symptomsSelect" style="margin-bottom:8px;">
                            <option value="">-- Select Common Symptom --</option>
                            <?php foreach ($common_symptoms as $sym): ?>
                                <option value="<?= htmlspecialchars($sym) ?>"><?= htmlspecialchars($sym) ?></option>
                            <?php endforeach; ?>
                            <option value="other">✏️ Other</option>
                        </select>
                        <textarea name="symptoms" class="form-control-modern" placeholder="Describe symptoms..." id="symptomsTextarea" rows="2"></textarea>
                    </div>

                    <div class="form-row-modern" id="labSection" style="display:none;">
                        <label class="form-label">
                            <i class="fas fa-flask label-icon" style="color:var(--page-purple);"></i> Select Lab Tests
                            <span style="font-weight:400;font-size:0.65rem;padding:2px 10px;border-radius:12px;background:var(--page-gray-100);color:var(--page-text-secondary);margin-left:6px;" id="labSelectedCount">0 selected</span>
                        </label>

                        <div class="lab-modal-container-modern">
                            <div class="lab-modal-header-modern">
                                <div style="font-weight:600;font-size:0.85rem;display:flex;align-items:center;gap:8px;">
                                    <i class="fas fa-flask" style="color:var(--page-purple);"></i> Available (<?= count($lab_tests_catalog) ?>)
                                </div>
                                <button type="button" onclick="closeLabTests()" style="background:none;border:none;cursor:pointer;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="lab-test-scroll" id="labTestsContainer">
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
                                    <button type="button" class="btn-modern btn-modern-outline btn-modern-sm" onclick="selectAllLabTests()"><i class="fas fa-check-double"></i> All</button>
                                    <button type="button" class="btn-modern btn-modern-outline btn-modern-sm" onclick="deselectAllLabTests()"><i class="fas fa-times"></i> Clear</button>
                                    <span style="font-size:0.8rem;font-weight:700;color:var(--page-success);padding:4px 14px;background:var(--page-success-bg);border-radius:20px;" id="labTotalPrice">Total: TSh 0</span>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="lab_test_ids" id="selectedLabTestsInput" value="">
                    </div>
                </div>
            </div>

            <div class="form-row-modern" style="margin-top:20px;">
                <label class="form-label" style="font-size:0.85rem;">
                    <i class="fas fa-heartbeat" style="color:#DC2626;"></i> Vital Signs
                    <span style="font-weight:400;font-size:0.65rem;padding:2px 10px;border-radius:12px;background:var(--page-gray-100);color:var(--page-text-secondary);margin-left:6px;">Optional - 7 Vitals</span>
                </label>

                <div class="vital-grid">
                    <div class="vital-card temperature">
                        <div class="vital-header"><span>🌡️</span><div><span class="vital-label">Temperature</span></div></div>
                        <input type="number" name="temperature" class="vital-input" step="0.1" placeholder="36.5" value="<?= $latest_vital_signs['temperature'] ?? '' ?>">
                        <span class="vital-unit">°C</span>
                    </div>
                    <div class="vital-card bp">
                        <div class="vital-header"><span>💓</span><div><span class="vital-label">Blood Pressure</span></div></div>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <input type="number" name="bp_systolic" class="vital-input" style="width:45%;" placeholder="120" value="<?= $latest_vital_signs['blood_pressure_systolic'] ?? '' ?>">
                            <span style="font-weight:700;">/</span>
                            <input type="number" name="bp_diastolic" class="vital-input" style="width:45%;" placeholder="80" value="<?= $latest_vital_signs['blood_pressure_diastolic'] ?? '' ?>">
                        </div>
                        <span class="vital-unit">mmHg</span>
                    </div>
                    <div class="vital-card pulse">
                        <div class="vital-header"><span>❤️</span><div><span class="vital-label">Pulse Rate</span></div></div>
                        <input type="number" name="pulse_rate" class="vital-input" placeholder="72" value="<?= $latest_vital_signs['pulse_rate'] ?? '' ?>">
                        <span class="vital-unit">bpm</span>
                    </div>
                </div>

                <div class="vital-grid-row2">
                    <div class="vital-card weight">
                        <div class="vital-header"><span>⚖️</span><div><span class="vital-label">Weight</span></div></div>
                        <input type="number" name="weight" class="vital-input" step="0.1" placeholder="65" value="<?= $latest_vital_signs['weight'] ?? '' ?>" id="weightInput" oninput="calculateBMI()">
                        <span class="vital-unit">kg</span>
                    </div>
                    <div class="vital-card height">
                        <div class="vital-header"><span>📏</span><div><span class="vital-label">Height</span></div></div>
                        <input type="number" name="height" class="vital-input" step="0.1" placeholder="170" value="<?= $latest_vital_signs['height'] ?? '' ?>" id="heightInput" oninput="calculateBMI()">
                        <span class="vital-unit">cm</span>
                    </div>
                    <div class="vital-card bmi">
                        <div class="vital-header"><span>📊</span><div><span class="vital-label">BMI</span></div></div>
                        <input type="number" name="bmi" class="vital-input" id="bmiOutput" readonly placeholder="22.5" value="<?= $latest_vital_signs['bmi'] ?? '' ?>">
                        <span class="vital-unit">kg/m²</span>
                    </div>
                    <div class="vital-card spo2">
                        <div class="vital-header"><span>🫁</span><div><span class="vital-label">SpO₂</span></div></div>
                        <input type="number" name="oxygen_saturation" class="vital-input" placeholder="98" value="<?= $latest_vital_signs['oxygen_saturation'] ?? '' ?>" id="spo2Input" oninput="updateSpO2Status()">
                        <span class="vital-unit">%</span>
                        <span class="spo2-status" id="spo2Status">Auto</span>
                    </div>
                </div>
            </div>

            <div class="form-row-modern">
                <label class="form-label"><i class="fas fa-sticky-note label-icon"></i> Additional Notes</label>
                <textarea name="notes" class="form-control-modern" placeholder="Optional..." rows="2"></textarea>
            </div>

            <div class="form-actions-modern">
                <button type="submit" class="btn-modern btn-modern-primary" id="assignBtn">
                    <i class="fas fa-user-md"></i> Assign / Change Doctor
                </button>
                <button type="reset" class="btn-modern btn-modern-outline"><i class="fas fa-undo"></i> Reset</button>
                <a href="<?= $user_role === 'admin' ? '../admin/dashboard.php' : 'dashboard.php' ?>" class="btn-modern btn-modern-outline"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>
    </div>

    <div class="stats-grid">
        <div class="stat-card assigned">
            <span class="stat-icon">✅</span>
            <p class="stat-number assigned" id="assignedStatNumber"><?= $assigned_count ?></p>
            <p class="stat-label">Assigned</p>
        </div>
        <div class="stat-card pending">
            <span class="stat-icon">🧪</span>
            <p class="stat-number pending" id="labStatNumber"><?= $lab_only_count ?></p>
            <p class="stat-label">Lab Test</p>
        </div>
        <div class="stat-card doctors">
            <span class="stat-icon">⏳</span>
            <p class="stat-number doctors" id="waitingStatNumber"><?= $waiting_count ?></p>
            <p class="stat-label">Waiting</p>
        </div>
    </div>

</main>

<div id="toast" class="toast-modern" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.2rem;"></i>
    <div>
        <p style="font-weight:700;font-size:0.85rem;margin:0 0 2px 0;" id="toastTitle">Notification</p>
        <p style="font-size:0.78rem;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
var BRANCH_PARAM = '<?= urlencode($selected_branch_id) ?>';
var FETCH_URL = '?branch=' + BRANCH_PARAM;

function showToast(title, message, type) {
    var toast = document.getElementById('toast');
    if (!toast) return;
    document.getElementById('toastTitle').textContent = title;
    document.getElementById('toastMessage').innerHTML = message;
    toast.className = 'toast-modern ' + type;
    toast.style.display = 'flex';
    void toast.offsetWidth;
    toast.classList.add('show');
    clearTimeout(toast.timeout);
    toast.timeout = setTimeout(function() {
        toast.classList.remove('show');
        setTimeout(function() { toast.style.display = 'none'; }, 400);
    }, 5000);
}

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
    document.getElementById('selectedPatientLabel').innerHTML = '✓ ' + patientName + ' (' + patientCode + ')';
    
    document.querySelectorAll('.patient-list-item').forEach(function(item) {
        item.classList.remove('selected');
        if (item.getAttribute('data-patient-id') == patientId) item.classList.add('selected');
    });
    
    document.getElementById('patientToggleContent').classList.remove('open');
    document.getElementById('patientToggleBtn').classList.remove('active');
    
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
        badge.textContent = searchTerm === '' ? 'All Patients (<?= $branch_patients_total ?>)' : 'Found: ' + visibleCount;
    }
}

document.getElementById('symptomsSelect')?.addEventListener('change', function() {
    var value = this.value;
    var target = document.getElementById('symptomsTextarea');
    if (!target) return;
    if (value && value !== 'other') {
        var current = target.value.trim();
        target.value = current ? current + ', ' + value : value;
    } else if (value === 'other') {
        target.focus();
    }
});

function calculateBMI() {
    var weight = parseFloat(document.getElementById('weightInput')?.value);
    var height = parseFloat(document.getElementById('heightInput')?.value);
    var output = document.getElementById('bmiOutput');
    if (!output) return;
    if (weight && height && height > 0) {
        var hm = height / 100;
        output.value = Math.round((weight / (hm * hm)) * 10) / 10;
    } else {
        output.value = '';
    }
}

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
    var status = '', cls = '';
    if (spo2 >= 95) { status = '✅ Normal'; cls = 'normal'; }
    else if (spo2 >= 90) { status = '⚠️ Low'; cls = 'low'; }
    else { status = '🚨 Critical'; cls = 'critical'; }
    spo2Status.textContent = status;
    spo2Status.className = 'spo2-status ' + cls;
}

function toggleAssignmentType(type) {
    var labSection = document.getElementById('labSection');
    var doctorSelect = document.getElementById('doctorSelect');
    var assignBtn = document.getElementById('assignBtn');
    var helpText = document.getElementById('assignmentTypeHelp');
    var visitTypeSection = document.getElementById('visitTypeSection');
    var doctorSelectCard = document.getElementById('doctorSelectCard');
    
    if (type === 'lab') {
        if (doctorSelectCard) doctorSelectCard.style.display = 'none';
        labSection.style.display = 'block';
        doctorSelect.removeAttribute('required');
        helpText.textContent = '🧪 Lab test request - Doctor NOT required';
        assignBtn.innerHTML = '<i class="fas fa-flask"></i> Request Lab Tests';
        if (visitTypeSection) visitTypeSection.style.display = 'none';
    } else {
        if (doctorSelectCard) doctorSelectCard.style.display = 'block';
        labSection.style.display = 'none';
        doctorSelect.setAttribute('required', 'required');
        helpText.textContent = '👨‍⚕️ Doctor assignment - Doctor required';
        assignBtn.innerHTML = '<i class="fas fa-user-md"></i> Assign / Change Doctor';
        if (visitTypeSection) visitTypeSection.style.display = 'block';
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
            var priceText = item ? item.querySelector('.lab-test-price')?.textContent || '' : '';
            var price = parseFloat(priceText.replace(/[^0-9.]/g, ''));
            if (!isNaN(price)) total += price;
        } else {
            if (item) item.classList.remove('checked');
        }
    });
    
    document.getElementById('labSelectedCount').textContent = count + ' selected';
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
    var display = document.getElementById('visitTypePrice');
    if (!select || !display) return;
    var opt = select.options[select.selectedIndex];
    var price = opt.dataset.price || 0;
    display.textContent = 'Fee: TSh ' + parseInt(price).toLocaleString();
}

function changeDoctor(patientId) {
    window.location.href = '?branch=' + BRANCH_PARAM + '&patient_id=' + patientId + '&change=1';
}

function reassignDoctor(patientId, visitId) {
    if (!confirm('⚠️ Reassign?\n\nRemove current doctor?')) return;
    
    var formData = new FormData();
    formData.append('action', 'reassign_doctor');
    formData.append('patient_id', patientId);
    formData.append('visit_id', visitId);
    
    fetch(FETCH_URL, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('✅ Reassigned', data.message, 'success');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showToast('❌ Error', data.message, 'error');
            }
        });
}

function completeVisit(patientId, visitId) {
    if (!confirm('✅ Mark as COMPLETED?')) return;
    
    var formData = new FormData();
    formData.append('action', 'complete_visit');
    formData.append('patient_id', patientId);
    formData.append('visit_id', visitId);
    
    fetch(FETCH_URL, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('✅ Complete', data.message, 'success');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showToast('❌ Error', data.message, 'error');
            }
        });
}

function cancelVisit(patientId, visitId) {
    if (!confirm('❌ Cancel this visit?')) return;
    
    var formData = new FormData();
    formData.append('action', 'cancel_visit');
    formData.append('patient_id', patientId);
    formData.append('visit_id', visitId);
    
    fetch(FETCH_URL, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('✅ Cancelled', data.message, 'success');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showToast('❌ Error', data.message, 'error');
            }
        });
}

function filterByStatus(status) {
    document.querySelectorAll('.status-toggle-btn').forEach(function(btn) {
        btn.classList.remove('active');
        if (btn.dataset.status === status) btn.classList.add('active');
    });
    
    var titles = {
        'assigned': 'Assigned Patients (With Doctor)',
        'lab_test': 'Lab Test Patients',
        'prescribed': 'Prescribed Patients',
        'waiting': 'Waiting Patients',
        'complete': 'Completed Patients'
    };
    
    document.getElementById('listTitle').textContent = titles[status] || 'Patients';
    
    var container = document.getElementById('patientsListContainer');
    container.innerHTML = '<div style="text-align:center;padding:40px;"><div class="spinner" style="border-color:var(--page-border);border-top-color:var(--page-primary);"></div></div>';
    
    var formData = new FormData();
    formData.append('action', 'get_filtered_list');
    formData.append('status', status);
    
    fetch(FETCH_URL, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.html) {
                container.innerHTML = data.html;
                document.getElementById('listCountBadge').textContent = data.count;
            } else {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><p>No patients in this status</p></div>';
                document.getElementById('listCountBadge').textContent = '0';
            }
        });
}

document.getElementById('assignForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    formData.append('action', 'change_doctor');
    
    var patientId = document.getElementById('selectedPatientInput').value;
    if (!patientId || patientId === '0') {
        showToast('❌ Error', 'Select a patient first', 'error');
        return;
    }
    
    var visitTypeSelect = document.getElementById('visitTypeSelect');
    if (visitTypeSelect) formData.append('service_id', visitTypeSelect.value);
    
    var hiddenInput = document.getElementById('selectedLabTestsInput');
    if (hiddenInput && hiddenInput.value) {
        hiddenInput.value.split(',').forEach(function(id) {
            if (id) formData.append('lab_test_ids[]', id);
        });
    }
    
    var btn = document.getElementById('assignBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Processing...';
    
    fetch(FETCH_URL, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-user-md"></i> Assign / Change Doctor';
            
            if (data.success) {
                showToast('✅ Success', data.message, 'success');
                setTimeout(function() { location.reload(); }, 3000);
            } else {
                showToast('❌ Error', data.message, 'error');
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-user-md"></i> Assign / Change Doctor';
            showToast('❌ Error', 'Network: ' + err.message, 'error');
        });
});

document.addEventListener('DOMContentLoaded', function() {
    calculateBMI();
    updateSpO2Status();
    updateVisitTypePrice();
    filterByStatus('assigned');
    
    <?php if ($change_mode && $selected_patient_id > 0): ?>
    var content = document.getElementById('patientToggleContent');
    var btn = document.getElementById('patientToggleBtn');
    if (content && btn) { content.classList.add('open'); btn.classList.add('active'); }
    <?php endif; ?>
});

console.log('%c👨‍⚕️ Braick - Assign Doctor V6 FINAL', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Assigned By column imeongezwa na inajazwa', 'font-size:12px;color:#7C3AED;font-weight:bold;');
console.log('%c✅ Bill + bill_items zinatumwa kwa Cashier', 'font-size:12px;color:#059669;font-weight:bold;');
console.log('%c✅ Notifications kwa Cashier', 'font-size:12px;color:#059669;');
console.log('%c✅ Lab Test inaonyesha aliye request', 'font-size:12px;color:#7C3AED;');
console.log('%c📊 Branch: <?= htmlspecialchars($branch_name) ?> (ID: <?= $selected_branch_id ?>)', 'font-size:12px;color:#0B5ED7;');
console.log('%c📊 Patients: <?= $branch_patients_total ?>', 'font-size:12px;color:#059669;');
</script>

</body>
</html>