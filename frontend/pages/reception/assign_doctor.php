<?php
// ================================================================
// FILE: frontend/pages/reception/assign_doctor.php
// RECEPTION - ASSIGN / CHANGE DOCTOR & LAB REQUESTS
// ================================================================
// BILL CREATION RULES:
// 1. ONLY Visit Bill (Consultation fee) is created when doctor is assigned
// 2. Visit bill is valid for 7 days (follow-up visits use same bill)
// 3. If visit type changes, old bill is canceled, new bill created
// 4. Lab requests - NO bill (Lab creates bill when confirming results)
// 5. Auto-update every 3 seconds - ALL FIELDS VIA AJAX
// 6. Visit Type options from services table (category_id = 2)
// 7. Select Patient shows ALL patients - NEWEST FIRST
// 8. Bill sent to Cashier with notification
// 9. Shared header with clock
// 10. MODERN DESIGN - Clean, Professional, Beautiful
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Rose Mwangi (Reception)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reception') {
    $_SESSION['user_id'] = 6;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'reception.rose';
    $_SESSION['is_admin'] = false;
}

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$user_id = $_SESSION['user_id'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = $user_branch_id;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$message = '';
$message_type = '';

// Initialize variables
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

try {
    $db = getDB();
    
    // ================================================================
    // ✅ GET CONSULTATION SERVICES FROM SERVICES TABLE (category_id = 2)
    // ================================================================
    $stmt = $db->prepare("
        SELECT id, service_name, description, price, unit, is_active
        FROM services 
        WHERE category_id = 2 AND is_active = 1 AND (branch_id = ? OR branch_id IS NULL)
        ORDER BY 
            CASE 
                WHEN service_name LIKE '%New%' OR service_name LIKE '%General%' THEN 0
                WHEN service_name LIKE '%Emergency%' THEN 1
                WHEN service_name LIKE '%Specialist%' THEN 2
                WHEN service_name LIKE '%Follow%' THEN 3
                ELSE 4
            END,
            service_name
    ");
    $stmt->execute([$selected_branch_id]);
    $consultation_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build visit type options
    $visit_type_options = [];
    $default_key = 'general_consultation';
    
    foreach ($consultation_services as $service) {
        $service_name = $service['service_name'];
        $key = strtolower(str_replace(' ', '_', $service_name));
        $key = str_replace('-', '_', $key);
        $key = preg_replace('/[^a-z_]/', '', $key);
        if (empty($key)) {
            $key = 'consultation_' . $service['id'];
        }
        
        $display_name = $service_name;
        
        $icon = '🆕';
        if (strpos(strtolower($service_name), 'follow') !== false) {
            $icon = '🔄';
        } elseif (strpos(strtolower($service_name), 'emergency') !== false) {
            $icon = '🚨';
        } elseif (strpos(strtolower($service_name), 'specialist') !== false) {
            $icon = '👨‍⚕️';
        } elseif (strpos(strtolower($service_name), 'general') !== false) {
            $icon = '🏥';
        }
        
        $visit_type_options[$key] = [
            'id' => $service['id'],
            'name' => $service_name,
            'display_name' => $display_name,
            'price' => (float)$service['price'],
            'unit' => $service['unit'] ?? 'each',
            'description' => $service['description'] ?? '',
            'is_active' => $service['is_active'],
            'icon' => $icon
        ];
        
        // Set default to New Patient / General Consultation
        if (strpos(strtolower($service_name), 'new') !== false || 
            strpos(strtolower($service_name), 'general') !== false) {
            $default_key = $key;
        }
    }
    
    // Fallback if no consultation services found
    if (empty($visit_type_options)) {
        $visit_type_options = [
            'new_patient' => [
                'id' => null,
                'name' => 'New Patient Consultation',
                'display_name' => 'New Patient',
                'price' => 15000,
                'unit' => 'each',
                'description' => 'First time consultation',
                'is_active' => 1,
                'icon' => '🆕'
            ],
            'general_consultation' => [
                'id' => null,
                'name' => 'General Consultation',
                'display_name' => 'General Consultation',
                'price' => 12000,
                'unit' => 'each',
                'description' => 'Standard doctor consultation',
                'is_active' => 1,
                'icon' => '🏥'
            ],
            'follow_up' => [
                'id' => null,
                'name' => 'Follow-up Consultation',
                'display_name' => 'Follow-up',
                'price' => 8000,
                'unit' => 'each',
                'description' => 'Follow-up visit',
                'is_active' => 1,
                'icon' => '🔄'
            ],
            'emergency' => [
                'id' => null,
                'name' => 'Emergency Consultation',
                'display_name' => 'Emergency',
                'price' => 25000,
                'unit' => 'each',
                'description' => 'Emergency visit',
                'is_active' => 1,
                'icon' => '🚨'
            ]
        ];
    }
    
    // ================================================================
    // GET LAB TESTS CATALOG
    // ================================================================
    $stmt = $db->prepare("SELECT id, test_name, price, category FROM lab_tests_catalog WHERE is_active = 1 ORDER BY category, test_name");
    $stmt->execute();
    $lab_tests_catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET ALL PATIENTS - ORDER BY CREATED AT DESC (NEWEST FIRST)
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
            v.registration_fee,
            v.payment_status
        FROM patients p
        LEFT JOIN visits v ON p.id = v.patient_id AND v.status IN ('new', 'pending', 'assigned', 'with_doctor', 'lab_test')
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE p.branch_id = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC, p.id DESC
    ");
    $stmt->execute([$selected_branch_id]);
    $all_patients = $stmt->fetchAll();
    
    // ================================================================
    // GET SELECTED PATIENT DATA
    // ================================================================
    if ($selected_patient_id > 0) {
        foreach ($all_patients as $p) {
            if ($p['id'] == $selected_patient_id) {
                $selected_patient_data = $p;
                break;
            }
        }
    }
    
    // ================================================================
    // SEPARATE PATIENTS FOR DISPLAY
    // ================================================================
    $pending_patients = [];
    $assigned_patients = [];
    $pending_count = 0;
    $assigned_count = 0;
    
    foreach ($all_patients as $patient) {
        // Check if patient has valid paid visit within 7 days
        $stmt = $db->prepare("
            SELECT pb.created_at as paid_date, pb.total_amount, pb.bill_number, pb.visit_id
            FROM patient_bills pb
            WHERE pb.patient_id = ? 
            AND pb.branch_id = ? 
            AND pb.status = 'paid'
            AND pb.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY pb.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$patient['id'], $selected_branch_id]);
        $paid_visit = $stmt->fetch();
        
        $patient['has_valid_paid_visit'] = $paid_visit ? true : false;
        $patient['paid_visit_date'] = $paid_visit ? $paid_visit['paid_date'] : null;
        $patient['paid_amount'] = $paid_visit ? $paid_visit['total_amount'] : 0;
        $patient['paid_visit_id'] = $paid_visit ? $paid_visit['visit_id'] : null;
        $patient['has_active_visit'] = !empty($patient['visit_id']);
        
        if ($patient['has_active_visit']) {
            if (in_array($patient['visit_status'], ['new', 'pending', 'lab_test'])) {
                $pending_patients[] = $patient;
                $pending_count++;
            } 
            elseif ($patient['visit_status'] === 'assigned' || $patient['visit_status'] === 'with_doctor') {
                $assigned_patients[] = $patient;
                $assigned_count++;
            }
        }
    }
    
    // ================================================================
    // GET LATEST VITAL SIGNS FOR SELECTED PATIENT
    // ================================================================
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
    
    // ================================================================
    // GET DOCTORS - SEPARATE ONLINE AND OFFLINE
    // ================================================================
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
    
    // ================================================================
    // GET LAB REQUESTS FOR SELECTED PATIENT
    // ================================================================
    $lab_requests = [];
    if ($selected_patient_id > 0) {
        $stmt = $db->prepare("
            SELECT lr.*, 
                   GROUP_CONCAT(lri.test_name SEPARATOR ', ') as test_names,
                   COUNT(lri.id) as test_count,
                   SUM(lri.price) as lab_total
            FROM lab_requests lr
            LEFT JOIN lab_request_items lri ON lr.id = lri.request_id
            WHERE lr.patient_id = ? AND lr.branch_id = ?
            GROUP BY lr.id
            ORDER BY lr.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$selected_patient_id, $selected_branch_id]);
        $lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // FUNCTION: CREATE VISIT BILL (Consultation fee) - SENT TO CASHIER
    // ================================================================
    function createVisitBill($db, $patient_id, $visit_id, $visit_type, $consultation_fee, $user_id, $branch_id) {
        // Check if patient has valid paid visit within 7 days
        $stmt = $db->prepare("
            SELECT pb.id, pb.visit_id, pb.bill_number, pb.created_at
            FROM patient_bills pb
            WHERE pb.patient_id = ? 
            AND pb.branch_id = ? 
            AND pb.status = 'paid'
            AND pb.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY pb.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$patient_id, $branch_id]);
        $paid_visit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($paid_visit) {
            return [
                'status' => 'waived',
                'message' => 'Fee WAIVED - Valid paid visit within 7 days',
                'bill_id' => $paid_visit['id'],
                'bill_number' => $paid_visit['bill_number']
            ];
        }
        
        // Check if there's an existing pending bill for this visit
        $stmt = $db->prepare("
            SELECT id, bill_number, status 
            FROM patient_bills 
            WHERE visit_id = ? AND status IN ('pending', 'partial')
            LIMIT 1
        ");
        $stmt->execute([$visit_id]);
        $existing_bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_bill) {
            $stmt = $db->prepare("
                UPDATE patient_bills 
                SET consultation_fee = ?, 
                    subtotal = ?, 
                    total_amount = ?, 
                    balance = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $consultation_fee,
                $consultation_fee,
                $consultation_fee,
                $consultation_fee,
                $existing_bill['id']
            ]);
            
            $stmt = $db->prepare("
                UPDATE bill_items 
                SET unit_price = ?, total_price = ?, item_name = ?
                WHERE bill_id = ? AND item_type = 'consultation'
            ");
            $item_name = 'Consultation (' . ucfirst(str_replace('_', ' ', $visit_type)) . ')';
            $stmt->execute([$consultation_fee, $consultation_fee, $item_name, $existing_bill['id']]);
            
            return [
                'status' => 'updated',
                'message' => 'Bill updated',
                'bill_id' => $existing_bill['id'],
                'bill_number' => $existing_bill['bill_number']
            ];
        }
        
        // ✅ CREATE NEW BILL
        $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999);
        
        $stmt = $db->prepare("
            INSERT INTO patient_bills (
                bill_number, patient_id, visit_id, 
                consultation_fee, subtotal, total_amount, balance, 
                status, created_by, branch_id, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
        ");
        $subtotal = $consultation_fee;
        $stmt->execute([
            $bill_number,
            $patient_id,
            $visit_id,
            $consultation_fee,
            $subtotal,
            $subtotal,
            $subtotal,
            $user_id,
            $branch_id
        ]);
        $bill_id = $db->lastInsertId();
        
        // ✅ ADD BILL ITEM
        $item_name = 'Consultation (' . ucfirst(str_replace('_', ' ', $visit_type)) . ')';
        
        $stmt = $db->prepare("
            INSERT INTO bill_items (
                bill_id, item_type, item_name, 
                quantity, unit_price, total_price, 
                payment_status, is_paid, status, created_at
            ) VALUES (?, 'consultation', ?, 1, ?, ?, 'pending', 0, 'pending', NOW())
        ");
        $stmt->execute([$bill_id, $item_name, $consultation_fee, $consultation_fee]);
        
        // ✅ NOTIFY CASHIER - Bill created
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE role = 'cashier' AND status = 'active' AND branch_id = ?");
            $stmt->execute([$branch_id]);
            $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($cashiers as $cashier) {
                $stmt = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type, link, is_read, created_at)
                    VALUES (?, '💰 New Bill Created', ?, 'bill', ?, 0, NOW())
                ");
                $stmt->execute([
                    $cashier['id'],
                    "Consultation bill #$bill_number (TSh " . number_format($consultation_fee) . ") for patient ID #$patient_id",
                    "cashier_dashboard.php"
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Cashier notification error: " . $e->getMessage());
        }
        
        return [
            'status' => 'created',
            'message' => 'New bill created and sent to Cashier!',
            'bill_id' => $bill_id,
            'bill_number' => $bill_number
        ];
    }
    
    // ================================================================
    // HANDLE FORM SUBMISSION - AJAX ENDPOINTS
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $assignment_type = $_POST['assignment_type'] ?? 'doctor';
        
        // ================================================================
        // AJAX: GET LIVE DATA - Auto-update
        // ================================================================
        if ($action === 'get_live_data') {
            header('Content-Type: application/json');
            
            // Get updated patients - NEWEST FIRST
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
                    v.registration_fee,
                    v.visit_type
                FROM patients p
                LEFT JOIN visits v ON p.id = v.patient_id AND v.status IN ('new', 'pending', 'assigned', 'with_doctor', 'lab_test')
                LEFT JOIN users u ON v.doctor_id = u.id
                WHERE p.branch_id = ?
                GROUP BY p.id
                ORDER BY p.created_at DESC, p.id DESC
            ");
            $stmt->execute([$selected_branch_id]);
            $updated_patients = $stmt->fetchAll();
            
            // Get updated doctors with online status
            $stmt = $db->prepare("
                SELECT id, full_name, specialty, is_online 
                FROM users 
                WHERE role = 'doctor' AND status = 'active' AND branch_id = ?
                ORDER BY is_online DESC, full_name
            ");
            $stmt->execute([$selected_branch_id]);
            $updated_doctors = $stmt->fetchAll();
            
            // Count online/offline
            $online = 0;
            $offline = 0;
            foreach ($updated_doctors as $doc) {
                if ($doc['is_online'] == 1) {
                    $online++;
                } else {
                    $offline++;
                }
            }
            
            // ✅ GET UPDATED CONSULTATION SERVICES
            $stmt = $db->prepare("
                SELECT id, service_name, description, price, unit, is_active
                FROM services 
                WHERE category_id = 2 AND is_active = 1 AND (branch_id = ? OR branch_id IS NULL)
                ORDER BY 
                    CASE 
                        WHEN service_name LIKE '%New%' OR service_name LIKE '%General%' THEN 0
                        WHEN service_name LIKE '%Emergency%' THEN 1
                        WHEN service_name LIKE '%Specialist%' THEN 2
                        WHEN service_name LIKE '%Follow%' THEN 3
                        ELSE 4
                    END,
                    service_name
            ");
            $stmt->execute([$selected_branch_id]);
            $updated_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Build visit type options HTML
            $visit_type_options_html = '';
            foreach ($updated_services as $service) {
                $service_name = $service['service_name'];
                $key = strtolower(str_replace(' ', '_', $service_name));
                $key = str_replace('-', '_', $key);
                $key = preg_replace('/[^a-z_]/', '', $key);
                if (empty($key)) {
                    $key = 'consultation_' . $service['id'];
                }
                
                $icon = '🆕';
                if (strpos(strtolower($service_name), 'follow') !== false) {
                    $icon = '🔄';
                } elseif (strpos(strtolower($service_name), 'emergency') !== false) {
                    $icon = '🚨';
                } elseif (strpos(strtolower($service_name), 'specialist') !== false) {
                    $icon = '👨‍⚕️';
                } elseif (strpos(strtolower($service_name), 'general') !== false) {
                    $icon = '🏥';
                }
                
                $selected = (strpos(strtolower($service_name), 'new') !== false || 
                            strpos(strtolower($service_name), 'general') !== false) ? 'selected' : '';
                
                $visit_type_options_html .= '
                    <option value="' . htmlspecialchars($key) . '" data-price="' . $service['price'] . '" data-id="' . $service['id'] . '" ' . $selected . '>
                        ' . $icon . ' ' . htmlspecialchars($service_name) . ' - TSh ' . number_format($service['price'], 0) . '
                    </option>
                ';
            }
            
            $pending = 0;
            $assigned = 0;
            
            foreach ($updated_patients as $p) {
                if (!empty($p['visit_id'])) {
                    if (in_array($p['visit_status'], ['new', 'pending', 'lab_test'])) {
                        $pending++;
                    } elseif ($p['visit_status'] === 'assigned' || $p['visit_status'] === 'with_doctor') {
                        $assigned++;
                    }
                }
            }
            
            // ✅ Build patient options - SHOW ALL PATIENTS - NEWEST FIRST
            $patient_options = '';
            $patient_options .= '<optgroup label="📋 All Patients (' . count($updated_patients) . ')">';
            
            foreach ($updated_patients as $p) {
                $status_label = '📋 No Visit';
                $status_class = 'no_visit';
                $status_icon = '📋';
                
                if (!empty($p['visit_id'])) {
                    if (in_array($p['visit_status'], ['new', 'pending', 'lab_test'])) {
                        if ($p['visit_status'] === 'lab_test' && empty($p['visit_doctor_id'])) {
                            $status_label = '🧪 Lab Only';
                            $status_class = 'lab_only';
                            $status_icon = '🧪';
                        } else {
                            $status_label = '⏳ Pending';
                            $status_class = 'pending';
                            $status_icon = '⏳';
                        }
                    } elseif ($p['visit_status'] === 'assigned' || $p['visit_status'] === 'with_doctor') {
                        $status_label = '✅ Assigned';
                        $status_class = 'assigned';
                        $status_icon = '✅';
                    }
                }
                
                $doctor_info = '';
                if (!empty($p['assigned_doctor_name'])) {
                    $online_status = !empty($p['assigned_doctor_online']) ? '🟢' : '⚪';
                    $doctor_info = ' 👨‍⚕️ Dr. ' . htmlspecialchars($p['assigned_doctor_name']) . ' ' . $online_status;
                }
                
                $selected = ($selected_patient_id == $p['id']) ? 'selected' : '';
                
                // Show if patient is new (created within last 7 days)
                $is_new = (strtotime($p['patient_created_at'] ?? 'now') > strtotime('-7 days'));
                $new_badge = $is_new ? ' 🆕' : '';
                
                $patient_options .= '<option value="' . $p['id'] . '" data-status="' . $status_class . '" data-doctor="' . htmlspecialchars($p['assigned_doctor_name'] ?? '') . '" ' . $selected . '>';
                $patient_options .= $status_icon . ' ' . htmlspecialchars($p['full_name']) . ' (' . htmlspecialchars($p['patient_id'] ?? 'N/A') . ')' . $new_badge;
                if (!empty($p['phone'])) {
                    $patient_options .= ' - ' . htmlspecialchars($p['phone']);
                }
                $patient_options .= $doctor_info;
                $patient_options .= ' <span class="status-badge-dropdown ' . $status_class . '">' . $status_label . '</span>';
                $patient_options .= '</option>';
            }
            
            $patient_options .= '</optgroup>';
            
            if (empty($updated_patients)) {
                $patient_options = '<option value="" disabled>No patients found</option>';
            }
            
            // Build assigned patients list HTML
            $assigned_html = '';
            $assigned_count_list = 0;
            foreach ($updated_patients as $p) {
                if (empty($p['visit_id'])) continue;
                if (!in_array($p['visit_status'], ['assigned', 'with_doctor'])) continue;
                
                $assigned_count_list++;
                $doctor_name = !empty($p['assigned_doctor_name']) ? 'Dr. ' . htmlspecialchars($p['assigned_doctor_name']) : 'No doctor';
                $is_online = !empty($p['assigned_doctor_online']) ? '🟢' : '⚪';
                
                $assigned_html .= '
                    <tr id="assigned-row-' . $p['id'] . '" style="border-bottom:1px solid var(--border-color);">
                        <td style="padding:10px 12px;font-weight:500;">' . htmlspecialchars($p['full_name']) . '</td>
                        <td style="padding:10px 12px;font-family:monospace;font-size:0.8rem;">' . htmlspecialchars($p['patient_id'] ?? 'N/A') . '</td>
                        <td style="padding:10px 12px;">
                            <span class="assigned-doctor-tag">
                                <i class="fas fa-user-md"></i>
                                ' . $doctor_name . '
                                <span class="text-xs">' . $is_online . '</span>
                            </span>
                        </td>
                        <td style="padding:10px 12px;">
                            <span class="status-badge-dropdown assigned">✅ Assigned</span>
                        </td>
                        <td style="padding:10px 12px;">
                            <button onclick="selectPatientAndChange(' . $p['id'] . ')" class="btn btn-outline btn-sm" style="padding:4px 12px;font-size:0.7rem;cursor:pointer;background:var(--warning-bg);color:var(--warning);border-color:var(--warning);">
                                <i class="fas fa-sync-alt"></i> Change
                            </button>
                        </td>
                    </tr>
                ';
            }
            
            if (empty($assigned_html)) {
                $assigned_html = '
                    <tr>
                        <td colspan="5" style="padding:30px 12px;text-align:center;color:var(--text-secondary);">
                            <i class="fas fa-user-check text-2xl block mb-2"></i>
                            <p>No patients currently assigned</p>
                        </td>
                    </tr>
                ';
            }
            
            // Build doctor options HTML with online/offline status
            $doctor_options = '';
            $online_options = '';
            $offline_options = '';
            
            foreach ($updated_doctors as $doc) {
                $specialty_text = !empty($doc['specialty']) ? ' (' . $doc['specialty'] . ')' : '';
                if ($doc['is_online'] == 1) {
                    $online_options .= '<option value="' . $doc['id'] . '" data-online="1" style="font-weight:500;color:#059669;padding:4px;">';
                    $online_options .= '🟢 Dr. ' . htmlspecialchars($doc['full_name']) . $specialty_text;
                    $online_options .= '</option>';
                } else {
                    $offline_options .= '<option value="' . $doc['id'] . '" data-online="0" style="color:var(--text-secondary);padding:4px;">';
                    $offline_options .= '⚪ Dr. ' . htmlspecialchars($doc['full_name']) . $specialty_text;
                    $offline_options .= '</option>';
                }
            }
            
            if (!empty($online_options)) {
                $doctor_options .= '<optgroup label="🟢 Online Doctors (' . $online . ')" style="font-weight:600;color:#059669;">' . $online_options . '</optgroup>';
            }
            if (!empty($offline_options)) {
                $doctor_options .= '<optgroup label="⚪ Offline Doctors (' . $offline . ')" style="font-weight:600;color:var(--text-secondary);">' . $offline_options . '</optgroup>';
            }
            
            // Build lab tests HTML
            $lab_tests_html = '';
            foreach ($lab_tests_catalog as $test) {
                $lab_tests_html .= '
                    <div class="lab-test-item">
                        <input type="checkbox" name="lab_test_ids[]" value="' . $test['id'] . '" id="lab_test_' . $test['id'] . '" class="lab-test-checkbox" onchange="updateLabSelection()">
                        <label for="lab_test_' . $test['id'] . '">
                            <strong>' . htmlspecialchars($test['test_name']) . '</strong>
                            ' . (!empty($test['category']) ? '<span class="lab-test-category">' . htmlspecialchars($test['category']) . '</span>' : '') . '
                        </label>
                        <span class="lab-test-price">TSh ' . number_format($test['price'] ?? 0, 0) . '</span>
                    </div>
                ';
            }
            
            if (empty($lab_tests_html)) {
                $lab_tests_html = '
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-flask"></i>
                        <p>No lab tests available</p>
                        <p class="text-xs mt-1">Please add lab tests to the catalog first</p>
                    </div>
                ';
            }
            
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
                'visit_type_options' => $visit_type_options_html,
                'lab_tests_html' => $lab_tests_html,
                'online_doctors_list' => $online_options,
                'offline_doctors_list' => $offline_options,
                'timestamp' => date('H:i:s')
            ]);
            exit;
        }
        
        // ================================================================
        // AJAX: GET PATIENT DETAILS
        // ================================================================
        if ($action === 'get_patient_details') {
            header('Content-Type: application/json');
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            
            if ($patient_id > 0) {
                $stmt = $db->prepare("
                    SELECT 
                        p.id, p.full_name, p.patient_id, p.phone, p.gender, 
                        p.date_of_birth, p.blood_group, p.allergies, p.address,
                        p.assigned_doctor_id,
                        u.full_name as assigned_doctor_name,
                        v.id as visit_id, v.status as visit_status, v.visit_number,
                        v.consultation_fee, v.registration_fee,
                        v.doctor_id as visit_doctor_id,
                        v.visit_type,
                        v.created_at as visit_date
                    FROM patients p
                    LEFT JOIN visits v ON p.id = v.patient_id AND v.status IN ('new', 'pending', 'assigned', 'with_doctor', 'lab_test')
                    LEFT JOIN users u ON v.doctor_id = u.id
                    WHERE p.id = ? AND p.branch_id = ?
                ");
                $stmt->execute([$patient_id, $selected_branch_id]);
                $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check valid paid visit within 7 days
                $stmt = $db->prepare("
                    SELECT pb.created_at as paid_date, pb.total_amount, pb.bill_number
                    FROM patient_bills pb
                    WHERE pb.patient_id = ? 
                    AND pb.branch_id = ? 
                    AND pb.status = 'paid'
                    AND pb.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ORDER BY pb.created_at DESC                    LIMIT 1
                ");
                $stmt->execute([$patient_id, $selected_branch_id]);
                $paid_visit = $stmt->fetch();
                
                if ($patient) {
                    echo json_encode([
                        'success' => true,
                        'patient' => $patient,
                        'has_active_visit' => !empty($patient['visit_id']),
                        'visit_status' => $patient['visit_status'] ?? 'none',
                        'assigned_doctor' => $patient['assigned_doctor_name'] ?? 'None',
                        'assigned_doctor_id' => $patient['assigned_doctor_id'] ?? null,
                        'is_lab_only' => ($patient['visit_status'] === 'lab_test' && empty($patient['visit_doctor_id'])),
                        'consultation_fee' => $patient['consultation_fee'] ?? 0,
                        'registration_fee' => $patient['registration_fee'] ?? 0,
                        'visit_type' => $patient['visit_type'] ?? 'general_consultation',
                        'has_valid_paid_visit' => $paid_visit ? true : false,
                        'paid_visit_date' => $paid_visit ? $paid_visit['paid_date'] : null,
                        'paid_bill_number' => $paid_visit ? $paid_visit['bill_number'] : null
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Patient not found']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
            }
            exit;
        }
        
        // ================================================================
        // AJAX: CHANGE DOCTOR - Creates only visit bill
        // ================================================================
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
            
            if ($patient_id <= 0) {
                $response['message'] = 'Please select a patient';
                echo json_encode($response);
                exit;
            }
            
            if ($doctor_id <= 0) {
                $response['message'] = 'Please select a doctor';
                echo json_encode($response);
                exit;
            }
            
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("SELECT full_name, is_online FROM users WHERE id = ?");
                $stmt->execute([$doctor_id]);
                $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                $doctor_name = $doctor['full_name'] ?? 'Unknown';
                $doctor_online = $doctor['is_online'] ?? 0;
                
                // ✅ Get consultation fee from visit_type_options
                $consultation_fee = $visit_type_options[$visit_type_key]['price'] ?? 0;
                $consultation_service_name = $visit_type_options[$visit_type_key]['name'] ?? 'Consultation';
                $consultation_service_id = $visit_type_options[$visit_type_key]['id'] ?? null;
                
                // Check if patient has lab-only visit
                $stmt = $db->prepare("
                    SELECT id, status, doctor_id, visit_number, consultation_fee, registration_fee, visit_type
                    FROM visits 
                    WHERE patient_id = ? AND status = 'lab_test' AND doctor_id IS NULL
                    AND branch_id = ?
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$patient_id, $selected_branch_id]);
                $lab_only_visit = $stmt->fetch();
                
                $visit_id = null;
                $visit_number = '';
                $bill_result = null;
                
                if ($lab_only_visit) {
                    // Update lab-only visit with doctor
                    $visit_id = $lab_only_visit['id'];
                    $visit_number = $lab_only_visit['visit_number'];
                    
                    $stmt = $db->prepare("
                        UPDATE visits 
                        SET doctor_id = ?, 
                            status = 'assigned',
                            visit_type = ?,
                            symptoms = ?,
                            complaint = ?,
                            notes = ?,
                            consultation_fee = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $doctor_id,
                        $visit_type_key,
                        $symptoms,
                        $complaint,
                        $notes,
                        $consultation_fee,
                        $visit_id
                    ]);
                    
                } else {
                    // Check for existing active visit
                    $stmt = $db->prepare("
                        SELECT id, status, visit_type, doctor_id, visit_number 
                        FROM visits 
                        WHERE patient_id = ? AND status IN ('new', 'pending', 'assigned', 'with_doctor') 
                        AND branch_id = ?
                        ORDER BY id DESC LIMIT 1
                    ");
                    $stmt->execute([$patient_id, $selected_branch_id]);
                    $existing_visit = $stmt->fetch();
                    
                    if ($existing_visit) {
                        $visit_id = $existing_visit['id'];
                        $visit_number = $existing_visit['visit_number'];
                        
                        // If status is 'with_doctor' or 'completed', create new visit
                        if ($existing_visit['status'] === 'with_doctor' || $existing_visit['status'] === 'completed') {
                            $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                            
                            $stmt = $db->prepare("
                                INSERT INTO visits (
                                    visit_number, patient_id, doctor_id, branch_id, 
                                    visit_type, status, symptoms, complaint, notes, 
                                    created_at, updated_at, consultation_fee, receptionist_id
                                ) VALUES (?, ?, ?, ?, ?, 'assigned', ?, ?, ?, NOW(), NOW(), ?, ?)
                            ");
                            $stmt->execute([
                                $visit_number, $patient_id, $doctor_id, $selected_branch_id, 
                                $visit_type_key, $symptoms, $complaint, $notes, 
                                $consultation_fee, $user_id
                            ]);
                            $visit_id = $db->lastInsertId();
                        } else {
                            // Check if visit type has changed
                            $old_visit_type = $existing_visit['visit_type'] ?? 'general_consultation';
                            
                            // If visit type changed, cancel old bill and create new
                            if ($old_visit_type !== $visit_type_key) {
                                // Cancel existing bills for this visit
                                $stmt = $db->prepare("
                                    UPDATE patient_bills 
                                    SET status = 'cancelled', updated_at = NOW() 
                                    WHERE visit_id = ? AND status IN ('pending', 'partial')
                                ");
                                $stmt->execute([$visit_id]);
                                
                                // Cancel bill items
                                $stmt = $db->prepare("
                                    UPDATE bill_items 
                                    SET status = 'cancelled' 
                                    WHERE bill_id IN (SELECT id FROM patient_bills WHERE visit_id = ? AND status = 'cancelled')
                                ");
                                $stmt->execute([$visit_id]);
                            }
                            
                            $stmt = $db->prepare("
                                UPDATE visits 
                                SET doctor_id = ?, status = 'assigned', 
                                    visit_type = ?, symptoms = ?, complaint = ?, notes = ?, 
                                    consultation_fee = ?,
                                    updated_at = NOW()
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                $doctor_id, 
                                $visit_type_key, 
                                $symptoms, 
                                $complaint, 
                                $notes,
                                $consultation_fee,
                                $visit_id
                            ]);
                        }
                    } else {
                        // Create new visit
                        $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        
                        $stmt = $db->prepare("
                            INSERT INTO visits (
                                visit_number, patient_id, doctor_id, branch_id, 
                                visit_type, status, symptoms, complaint, notes, 
                                created_at, updated_at, consultation_fee, receptionist_id
                            ) VALUES (?, ?, ?, ?, ?, 'assigned', ?, ?, ?, NOW(), NOW(), ?, ?)
                        ");
                        $stmt->execute([
                            $visit_number, $patient_id, $doctor_id, $selected_branch_id, 
                            $visit_type_key, $symptoms, $complaint, $notes, 
                            $consultation_fee, $user_id
                        ]);
                        $visit_id = $db->lastInsertId();
                    }
                }
                
                // ✅ CREATE VISIT BILL (Consultation fee) - SENT TO CASHIER
                $bill_result = null;
                if ($consultation_fee > 0) {
                    $bill_result = createVisitBill($db, $patient_id, $visit_id, $visit_type_key, $consultation_fee, $user_id, $selected_branch_id);
                }
                
                // Save vital signs
                $temperature = $_POST['temperature'] ?? null;
                $bp_systolic = $_POST['bp_systolic'] ?? null;
                $bp_diastolic = $_POST['bp_diastolic'] ?? null;
                $pulse_rate = $_POST['pulse_rate'] ?? null;
                $weight = $_POST['weight'] ?? null;
                $height = $_POST['height'] ?? null;
                $vital_notes = trim($_POST['vital_notes'] ?? '');
                
                $has_vital = $temperature || $bp_systolic || $bp_diastolic || 
                             $pulse_rate || $weight || $height;
                
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
                
                $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = ? WHERE id = ?");
                $stmt->execute([$doctor_id, $patient_id]);
                
                $db->commit();
                
                // Build response message
                $fee_text = '';
                if ($consultation_fee > 0) {
                    if ($bill_result && $bill_result['status'] === 'waived') {
                        $fee_text = ' - Fee WAIVED (valid paid visit within 7 days - Bill #' . $bill_result['bill_number'] . ')';
                    } else {
                        $fee_text = ' - Fee: TSh ' . number_format($consultation_fee);
                        if ($bill_result && $bill_result['status'] === 'created') {
                            $fee_text .= ' - ✅ Bill #' . $bill_result['bill_number'] . ' sent to Cashier!';
                        } else if ($bill_result && $bill_result['status'] === 'updated') {
                            $fee_text .= ' - Bill #' . $bill_result['bill_number'] . ' updated';
                        }
                    }
                } else {
                    $fee_text = ' - Fee WAIVED (consultation fee is zero)';
                }
                
                $online_text = $doctor_online == 1 ? '🟢 Online' : '⚪ Offline';
                
                $response['success'] = true;
                $response['message'] = "✅ Doctor <strong>$doctor_name</strong> ($online_text) assigned successfully! Visit: $visit_number" . $fee_text;
                $response['visit_number'] = $visit_number;
                $response['doctor_name'] = $doctor_name;
                $response['patient_id'] = $patient_id;
                $response['bill'] = $bill_result;
                $response['visit_type'] = $visit_type_key;
                $response['doctor_online'] = $doctor_online;
                $response['bill_sent_to_cashier'] = ($bill_result && $bill_result['status'] === 'created') ? true : false;
                
            } catch (Exception $e) {
                $db->rollBack();
                $response['message'] = '❌ Error: ' . $e->getMessage();
            }
            
            echo json_encode($response);
            exit;
        }
        
        // ================================================================
        // ORIGINAL ASSIGN DOCTOR (Fallback)
        // ================================================================
        if ($action === 'assign_doctor' && $assignment_type === 'doctor') {
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            $doctor_id = (int)($_POST['doctor_id'] ?? 0);
            $visit_type_key = $_POST['visit_type'] ?? 'general_consultation';
            $symptoms = trim($_POST['symptoms'] ?? '');
            $complaint = trim($_POST['complaint'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            $temperature = $_POST['temperature'] ?? null;
            $bp_systolic = $_POST['bp_systolic'] ?? null;
            $bp_diastolic = $_POST['bp_diastolic'] ?? null;
            $pulse_rate = $_POST['pulse_rate'] ?? null;
            $weight = $_POST['weight'] ?? null;
            $height = $_POST['height'] ?? null;
            $vital_notes = trim($_POST['vital_notes'] ?? '');
            
            $errors = [];
            if ($patient_id <= 0) $errors[] = 'Please select a patient';
            if ($doctor_id <= 0) $errors[] = 'Please select a doctor';
            
            if ($doctor_id > 0) {
                $stmt = $db->prepare("SELECT id, is_online FROM users WHERE id = ? AND role = 'doctor' AND status = 'active' AND branch_id = ?");
                $stmt->execute([$doctor_id, $selected_branch_id]);
                $doctor_check = $stmt->fetch();
                if (!$doctor_check) {
                    $errors[] = 'Selected doctor is not available.';
                }
            }
            
            if (empty($errors)) {
                $consultation_fee = $visit_type_options[$visit_type_key]['price'] ?? 0;
                $consultation_service_id = $visit_type_options[$visit_type_key]['id'] ?? null;
                $consultation_service_name = $visit_type_options[$visit_type_key]['name'] ?? 'Consultation Fee';
                
                try {
                    $db->beginTransaction();
                    
                    $stmt = $db->prepare("SELECT full_name, is_online FROM users WHERE id = ?");
                    $stmt->execute([$doctor_id]);
                    $doctor_data = $stmt->fetch();
                    $new_doctor_name = $doctor_data['full_name'] ?? '';
                    $new_doctor_online = $doctor_data['is_online'] ?? 0;
                    
                    // Check if patient has lab-only visit
                    $stmt = $db->prepare("
                        SELECT id, status, doctor_id, visit_number, visit_type
                        FROM visits 
                        WHERE patient_id = ? AND status = 'lab_test' AND doctor_id IS NULL
                        AND branch_id = ?
                        ORDER BY id DESC LIMIT 1
                    ");
                    $stmt->execute([$patient_id, $selected_branch_id]);
                    $lab_only_visit = $stmt->fetch();
                    
                    $visit_id = null;
                    $visit_number = '';
                    $bill_result = null;
                    $bill_created = false;
                    
                    if ($lab_only_visit) {
                        $visit_id = $lab_only_visit['id'];
                        $visit_number = $lab_only_visit['visit_number'];
                        
                        $stmt = $db->prepare("
                            UPDATE visits 
                            SET doctor_id = ?, 
                                status = 'assigned',
                                visit_type = ?,
                                symptoms = ?,
                                complaint = ?,
                                notes = ?,
                                consultation_fee = ?,
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $doctor_id,
                            $visit_type_key,
                            $symptoms,
                            $complaint,
                            $notes,
                            $consultation_fee,
                            $visit_id
                        ]);
                        
                    } else {
                        // Check for existing active visit
                        $stmt = $db->prepare("
                            SELECT id, status, visit_type, doctor_id, visit_number 
                            FROM visits 
                            WHERE patient_id = ? AND status IN ('new', 'pending', 'assigned', 'with_doctor') 
                            AND branch_id = ?
                            ORDER BY id DESC LIMIT 1
                        ");
                        $stmt->execute([$patient_id, $selected_branch_id]);
                        $existing_visit = $stmt->fetch();
                        
                        if ($existing_visit) {
                            $visit_id = $existing_visit['id'];
                            $visit_number = $existing_visit['visit_number'];
                            
                            if ($existing_visit['status'] === 'with_doctor' || $existing_visit['status'] === 'completed') {
                                $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                                
                                $stmt = $db->prepare("
                                    INSERT INTO visits (
                                        visit_number, patient_id, doctor_id, branch_id, 
                                        visit_type, status, symptoms, complaint, notes, 
                                        created_at, updated_at, consultation_fee, receptionist_id
                                    ) VALUES (?, ?, ?, ?, ?, 'assigned', ?, ?, ?, NOW(), NOW(), ?, ?)
                                ");
                                $stmt->execute([
                                    $visit_number, $patient_id, $doctor_id, $selected_branch_id, 
                                    $visit_type_key, $symptoms, $complaint, $notes, 
                                    $consultation_fee, $user_id
                                ]);
                                $visit_id = $db->lastInsertId();
                            } else {
                                // Check if visit type changed
                                $old_visit_type = $existing_visit['visit_type'] ?? 'general_consultation';
                                
                                if ($old_visit_type !== $visit_type_key) {
                                    // Cancel existing bills for this visit
                                    $stmt = $db->prepare("
                                        UPDATE patient_bills 
                                        SET status = 'cancelled', updated_at = NOW() 
                                        WHERE visit_id = ? AND status IN ('pending', 'partial')
                                    ");
                                    $stmt->execute([$visit_id]);
                                    
                                    $stmt = $db->prepare("
                                        UPDATE bill_items 
                                        SET status = 'cancelled' 
                                        WHERE bill_id IN (SELECT id FROM patient_bills WHERE visit_id = ? AND status = 'cancelled')
                                    ");
                                    $stmt->execute([$visit_id]);
                                }
                                
                                $stmt = $db->prepare("
                                    UPDATE visits 
                                    SET doctor_id = ?, status = 'assigned', 
                                        visit_type = ?, symptoms = ?, complaint = ?, notes = ?, 
                                        consultation_fee = ?,
                                        updated_at = NOW()
                                    WHERE id = ?
                                ");
                                $stmt->execute([
                                    $doctor_id, 
                                    $visit_type_key, 
                                    $symptoms, 
                                    $complaint, 
                                    $notes,
                                    $consultation_fee,
                                    $visit_id
                                ]);
                            }
                        } else {
                            $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                            
                            $stmt = $db->prepare("
                                INSERT INTO visits (
                                    visit_number, patient_id, doctor_id, branch_id, 
                                    visit_type, status, symptoms, complaint, notes, 
                                    created_at, updated_at, consultation_fee, receptionist_id
                                ) VALUES (?, ?, ?, ?, ?, 'assigned', ?, ?, ?, NOW(), NOW(), ?, ?)
                            ");
                            $stmt->execute([
                                $visit_number, $patient_id, $doctor_id, $selected_branch_id, 
                                $visit_type_key, $symptoms, $complaint, $notes, 
                                $consultation_fee, $user_id
                            ]);
                            $visit_id = $db->lastInsertId();
                        }
                    }
                    
                    // ✅ CREATE VISIT BILL (Consultation fee) - SENT TO CASHIER
                    if ($consultation_fee > 0) {
                        $bill_result = createVisitBill($db, $patient_id, $visit_id, $visit_type_key, $consultation_fee, $user_id, $selected_branch_id);
                        if ($bill_result && $bill_result['status'] === 'created') {
                            $bill_created = true;
                        }
                    }
                    
                    // Save vital signs
                    $has_vital = $temperature || $bp_systolic || $bp_diastolic || 
                                 $pulse_rate || $weight || $height;
                    
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
                    
                    $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = ? WHERE id = ?");
                    $stmt->execute([$doctor_id, $patient_id]);
                    
                    $db->commit();
                    
                    // Build message
                    $fee_text = '';
                    if ($consultation_fee > 0) {
                        if ($bill_result && $bill_result['status'] === 'waived') {
                            $fee_text = ' - Fee WAIVED (valid paid visit within 7 days)';
                        } else {
                            $fee_text = ' - Fee: TSh ' . number_format($consultation_fee);
                            if ($bill_created) {
                                $fee_text .= ' ✅ Bill #' . $bill_result['bill_number'] . ' sent to Cashier!';
                            }
                        }
                    } else {
                        $fee_text = ' - Fee WAIVED';
                    }
                    
                    $online_text = $new_doctor_online == 1 ? '🟢 Online' : '⚪ Offline';
                    
                    $message = "✅ Doctor <strong>$new_doctor_name</strong> ($online_text) assigned successfully! Visit #$visit_number" . $fee_text;
                    
                    if ($has_vital) {
                        $message .= " ✅ Vital Signs recorded!";
                    }
                    $message_type = 'success';
                    
                    echo '<script>
                        showToast("✅ Success", "' . addslashes($message) . '", "success");
                        setTimeout(function(){ 
                            window.location.href = "assign_doctor.php?patient_id=' . $patient_id . '&success=1"; 
                        }, 2000);
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
        
        // ================================================================
        // LAB TEST REQUEST - NO BILL AT ALL
        // ================================================================
        if ($action === 'assign_doctor' && $assignment_type === 'lab') {
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            $lab_test_ids = isset($_POST['lab_test_ids']) ? $_POST['lab_test_ids'] : [];
            $lab_notes = trim($_POST['lab_notes'] ?? '');
            $symptoms = trim($_POST['symptoms'] ?? '');
            $complaint = trim($_POST['complaint'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            $errors = [];
            if ($patient_id <= 0) $errors[] = 'Please select a patient';
            if (empty($lab_test_ids)) $errors[] = 'Please select at least one lab test';
            
            if (empty($errors)) {
                try {
                    $db->beginTransaction();
                    
                    // Check if patient has active visit
                    $stmt = $db->prepare("
                        SELECT id, status, doctor_id FROM visits 
                        WHERE patient_id = ? AND status IN ('new', 'pending', 'assigned', 'with_doctor', 'lab_test') 
                        AND branch_id = ?
                        ORDER BY id DESC LIMIT 1
                    ");
                    $stmt->execute([$patient_id, $selected_branch_id]);
                    $existing_visit = $stmt->fetch();
                    
                    $visit_id = null;
                    $visit_number = '';
                    
                    if ($existing_visit) {
                        $visit_id = $existing_visit['id'];
                        
                        $stmt = $db->prepare("
                            UPDATE visits 
                            SET status = 'lab_test', 
                                symptoms = ?, complaint = ?, notes = ?, 
                                updated_at = NOW(),
                                doctor_id = NULL,
                                consultation_fee = 0,
                                registration_fee = 0,
                                visit_total = 0
                            WHERE id = ?
                        ");
                        $stmt->execute([$symptoms, $complaint, $notes, $visit_id]);
                        
                        $stmt = $db->prepare("SELECT visit_number FROM visits WHERE id = ?");
                        $stmt->execute([$visit_id]);
                        $visit = $stmt->fetch();
                        $visit_number = $visit['visit_number'] ?? '';
                        
                    } else {
                        $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        
                        $stmt = $db->prepare("
                            INSERT INTO visits (
                                visit_number, patient_id, doctor_id, branch_id, 
                                visit_type, status, symptoms, complaint, notes, 
                                created_at, updated_at, receptionist_id,
                                consultation_fee, registration_fee, visit_total
                            ) VALUES (?, ?, NULL, ?, 'lab_only', 'lab_test', ?, ?, ?, NOW(), NOW(), ?, 0, 0, 0)
                        ");
                        
                        $stmt->execute([
                            $visit_number, $patient_id, $selected_branch_id, 
                            $symptoms, $complaint, $notes, $user_id
                        ]);
                        $visit_id = $db->lastInsertId();
                    }
                    
                    // Create lab request
                    $request_number = 'LAB-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt = $db->prepare("
                        INSERT INTO lab_requests (
                            request_number, visit_id, patient_id, doctor_id,
                            status, notes, requested_at, created_by, branch_id
                        ) VALUES (?, ?, ?, NULL, 'pending', ?, NOW(), ?, ?)
                    ");
                    $stmt->execute([
                        $request_number,
                        $visit_id,
                        $patient_id,
                        $lab_notes,
                        $user_id,
                        $selected_branch_id
                    ]);
                    $request_id = $db->lastInsertId();
                    
                    // Add lab test items
                    $lab_total = 0;
                    $test_names = [];
                    foreach ($lab_test_ids as $test_id) {
                        $stmt = $db->prepare("
                            SELECT test_name, price FROM lab_tests_catalog WHERE id = ? AND is_active = 1
                        ");
                        $stmt->execute([$test_id]);
                        $test = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($test) {
                            $lab_total += $test['price'];
                            $test_names[] = $test['test_name'];
                            $stmt = $db->prepare("
                                INSERT INTO lab_request_items (
                                    request_id, test_id, test_name, price, status, created_at
                                ) VALUES (?, ?, ?, ?, 'pending', NOW())
                            ");
                            $stmt->execute([
                                $request_id,
                                $test_id,
                                $test['test_name'],
                                $test['price']
                            ]);
                        }
                    }
                    
                    // ✅ NO BILL CREATED - Lab will create bill when confirming results
                    
                    // Update patient status
                    $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL WHERE id = ?");
                    $stmt->execute([$patient_id]);
                    
                    $db->commit();
                    
                    $message = "✅ Lab test request created successfully!";
                    $message .= "<br>📋 Request #: <strong>$request_number</strong>";
                    $message .= "<br>🧪 Tests: <strong>" . count($lab_test_ids) . "</strong> test(s) requested";
                    $message .= "<br>💰 Lab Total: <strong>TSh " . number_format($lab_total) . "</strong>";
                    $message .= "<br>👨‍⚕️ Doctor: <strong>Not assigned</strong> (lab only)";
                    $message .= "<br>💳 <strong>NO bill created</strong> - Bill will be created by Lab when results are confirmed";
                    $message_type = 'success';
                    
                    header('Location: assign_doctor.php?patient_id=' . $patient_id . '&success=1');
                    exit;
                    
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
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $all_patients = [];
    $pending_patients = [];
    $assigned_patients = [];
    $doctors = [];
    $online_doctors = [];
    $offline_doctors = [];
    $visit_type_options = [];
    $pending_count = 0;
    $assigned_count = 0;
}

// ================================================================
// COMMON SYMPTOMS LIST
// ================================================================
$common_symptoms = [
    'Fever' => 'Fever',
    'Headache' => 'Headache',
    'Cough' => 'Cough',
    'Sore Throat' => 'Sore Throat',
    'Body Pain' => 'Body Pain',
    'Fatigue' => 'Fatigue',
    'Nausea' => 'Nausea',
    'Vomiting' => 'Vomiting',
    'Diarrhea' => 'Diarrhea',
    'Chest Pain' => 'Chest Pain',
    'Shortness of Breath' => 'Shortness of Breath',
    'Abdominal Pain' => 'Abdominal Pain',
    'Dizziness' => 'Dizziness',
    'Rash' => 'Rash',
    'Swelling' => 'Swelling'
];

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
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
        /* ================================================================
           MODERN ROOT VARIABLES - FRESH & CLEAN
           ================================================================ */
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
            
            --pink: #EC4899;
            --pink-bg: #FDF2F8;
            
            --teal: #0D9488;
            --teal-bg: #E6FFFA;
            
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
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --purple-bg: #2D1B5F;
            --pink-bg: #3A1A2E;
            --teal-bg: #0D3D3A;
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
        
        /* ================================================================
           TOP NAV - WITH CLOCK
           ================================================================ */
        .top-nav {
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
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
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            transform: scale(1.02);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .top-nav .datetime i {
            color: var(--primary-light);
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .top-nav .avatar:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all 0.3s;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover {
            background: var(--bg-body);
            color: var(--primary);
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           MODERN PAGE HEADER
           ================================================================ */
        .page-header {
            background: var(--primary-gradient);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(37, 99, 235, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -5%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }
        
        .page-header .header-badge:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
        }
        
        .page-header .header-badge .online-count {
            color: #34D399;
            font-weight: 700;
        }
        
        .page-header .header-badge .offline-count {
            color: #F87171;
            font-weight: 700;
        }
        
        .page-header .header-badge .live-dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #34D399;
            animation: pulse-dot 1.5s infinite;
            margin-right: 2px;
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .update-badge-light {
            background: rgba(255,255,255,0.08);
            color: rgba(255,255,255,0.8);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        /* ================================================================
           MODERN CARD - CLEAN & FRESH
           ================================================================ */
        .modern-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            margin-bottom: 24px;
        }
        
        .modern-card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-lg);
        }
        
        .modern-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .modern-card .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modern-card .card-title i {
            color: var(--primary);
        }
        
        .modern-card .card-badge {
            background: var(--primary-bg);
            color: var(--primary);
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .modern-card .card-badge.success {
            background: var(--success-bg);
            color: var(--success);
        }
        
        /* ================================================================
           FORM CARD - MODERN & CLEAN
           ================================================================ */
        .form-card-modern {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 32px 36px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            max-width: 1100px;
            margin: 0 auto;
            box-shadow: var(--shadow-md);
        }
        
        .form-card-modern:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }
        
        .form-card-modern .form-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
            padding-bottom: 20px;
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
            font-size: 1.4rem;
            flex-shrink: 0;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.25);
        }
        
        .form-card-modern .form-header .form-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-card-modern .form-header .form-subtitle {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        /* ================================================================
           FORM ELEMENTS - MODERN
           ================================================================ */
        .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
            display: block;
        }
        
        .form-label .required {
            color: var(--danger);
            margin-left: 2px;
        }
        
        .form-label .label-icon {
            margin-right: 4px;
            color: var(--primary);
        }
        
        .form-label .label-badge {
            font-weight: 400;
            font-size: 0.6rem;
            padding: 1px 10px;
            border-radius: 12px;
            background: var(--gray-100);
            color: var(--text-secondary);
            margin-left: 6px;
        }
        
        .form-control-modern {
            width: 100%;
            padding: 10px 16px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.85rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .form-control-modern:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
        }
        
        .form-control-modern::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }
        
        .form-control-modern:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .form-control-modern.is-invalid {
            border-color: var(--danger);
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.08);
        }
        
        .form-control-modern.is-valid {
            border-color: var(--success);
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.08);
        }
        
        select.form-control-modern {
            appearance: auto;
            cursor: pointer;
        }
        
        textarea.form-control-modern {
            resize: vertical;
            min-height: 60px;
        }
        
        /* ✅ GRID 2 - KILA ROW INA FIELD MBILI */
        .grid-2-modern {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-row-modern {
            margin-bottom: 20px;
        }
        
        .form-row-modern:last-child {
            margin-bottom: 0;
        }
        
        /* ================================================================
           BUTTONS - MODERN
           ================================================================ */
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
        }
        
        .btn-modern-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        
        .btn-modern-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(37, 99, 235, 0.35);
        }
        
        .btn-modern-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-modern-success {
            background: var(--success);
            color: white;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        }
        
        .btn-modern-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(5, 150, 105, 0.35);
        }
        
        .btn-modern-warning {
            background: var(--warning);
            color: white;
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.25);
        }
        
        .btn-modern-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(217, 119, 6, 0.35);
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
            padding: 5px 14px;
            font-size: 0.75rem;
            border-radius: 8px;
        }
        
        .form-actions-modern {
            display: flex;
            gap: 12px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        /* ================================================================
           STATUS BADGES - MODERN
           ================================================================ */
        .status-badge-dropdown {
            display: inline-block;
            font-size: 0.55rem;
            font-weight: 500;
            padding: 1px 8px;
            border-radius: 8px;
            margin-left: 4px;
        }
        
        .status-badge-dropdown.pending {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .status-badge-dropdown.assigned {
            background: #D1FAE5;
            color: #059669;
        }
        
        .status-badge-dropdown.with_doctor {
            background: #EDE9FE;
            color: #7C3AED;
        }
        
        .status-badge-dropdown.lab_test {
            background: #EDE9FE;
            color: #7C3AED;
        }
        
        .status-badge-dropdown.lab_only {
            background: #EDE9FE;
            color: #7C3AED;
            border: 1px dashed #7C3AED;
        }
        
        .status-badge-dropdown.no_visit {
            background: var(--gray-200);
            color: var(--gray-600);
        }
        
        [data-theme="dark"] .status-badge-dropdown.pending {
            background: #3D2E0A;
            color: #FBBF24;
        }
        
        [data-theme="dark"] .status-badge-dropdown.assigned {
            background: #1A3A2A;
            color: #34D399;
        }
        
        [data-theme="dark"] .status-badge-dropdown.with_doctor {
            background: #2D1B5F;
            color: #A78BFA;
        }
        
        [data-theme="dark"] .status-badge-dropdown.lab_test {
            background: #2D1B5F;
            color: #A78BFA;
        }
        
        [data-theme="dark"] .status-badge-dropdown.lab_only {
            background: #2D1B5F;
            color: #A78BFA;
        }
        
        [data-theme="dark"] .status-badge-dropdown.no_visit {
            background: var(--gray-700);
            color: var(--gray-400);
        }
        
        /* ================================================================
           ASSIGNED DOCTOR TAG - MODERN
           ================================================================ */
        .assigned-doctor-tag-modern {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--success-bg);
            color: var(--success);
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 500;
            border: 1px solid var(--success);
        }
        
        [data-theme="dark"] .assigned-doctor-tag-modern {
            background: #1A3A2A;
            border-color: #34D399;
        }
        
        /* ================================================================
           STATS CARD - MODERN & CLEAN
           ================================================================ */
        .stat-card-modern {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-card-modern:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card-modern .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .stat-card-modern .stat-number.primary {
            color: var(--primary);
        }
        
        .stat-card-modern .stat-number.green {
            color: var(--success);
        }
        
        .stat-card-modern .stat-number.orange {
            color: var(--warning);
        }
        
        .stat-card-modern .stat-number.purple {
            color: var(--purple);
        }
        
        .stat-card-modern .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .stat-card-modern .stat-icon {
            font-size: 1.4rem;
            margin-bottom: 4px;
        }
        
        /* ================================================================
           ASSIGNMENT TYPE DROPDOWN
           ================================================================ */
        #assignmentTypeSelect {
            font-weight: 500;
            padding: 12px 16px;
            border-color: var(--primary);
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        #assignmentTypeSelect option {
            background: var(--bg-card);
            color: var(--text-primary);
            padding: 8px;
        }
        
        #assignmentTypeSelect option:checked {
            background: var(--primary);
            color: white;
        }
        
        /* ================================================================
           VISIT TYPE - MODERN
           ================================================================ */
        #visitTypeSelect {
            font-weight: 500;
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        #visitTypeSelect option {
            padding: 6px 10px;
        }
        
        #visitTypeSelect option:checked {
            background: var(--primary);
            color: white;
        }
        
        .visit-type-price-badge {
            font-weight: 600;
            color: var(--success);
            background: var(--success-bg);
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 0.7rem;
        }
        
        [data-theme="dark"] .visit-type-price-badge {
            background: #1A3A2A;
        }
        
        /* ================================================================
           LAB MODAL - MODERN
           ================================================================ */
        .lab-modal-container-modern {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 2px solid var(--purple);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            margin-bottom: 16px;
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
            font-size: 1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .lab-modal-header-modern .lab-modal-title .lab-test-count {
            font-size: 0.75rem;
            font-weight: 400;
            color: var(--text-secondary);
        }
        
        .lab-modal-close-modern {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 1.2rem;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .lab-modal-close-modern:hover {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        .lab-modal-body-modern {
            padding: 8px 12px;
        }
        
        .lab-modal-footer-modern {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            border-top: 2px solid var(--border-color);
            background: var(--bg-body);
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .lab-modal-footer-modern .lab-total-price {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--success);
            padding: 4px 12px;
            background: var(--success-bg);
            border-radius: 20px;
        }
        
        .lab-test-item-modern {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s ease;
            border-radius: 6px;
        }
        
        .lab-test-item-modern:hover {
            background: var(--primary-bg);
        }
        
        .lab-test-item-modern:last-child {
            border-bottom: none;
        }
        
        .lab-test-item-modern .lab-test-checkbox {
            width: 16px;
            height: 16px;
            accent-color: var(--purple);
            cursor: pointer;
            flex-shrink: 0;
        }
        
        .lab-test-item-modern label {
            cursor: pointer;
            flex: 1;
        }
        
        .lab-test-item-modern .lab-test-price {
            font-size: 0.7rem;
            color: var(--success);
            font-weight: 500;
            white-space: nowrap;
        }
        
        .lab-test-item-modern .lab-test-category {
            font-size: 0.6rem;
            color: var(--text-secondary);
            display: block;
        }
        
        .lab-selected-summary-modern {
            background: var(--primary-bg);
            border-radius: var(--radius);
            padding: 10px 14px;
            margin-top: 10px;
            border: 1px solid var(--primary);
        }
        
        /* ================================================================
           VITAL SIGNS - MODERN
           ================================================================ */
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
        
        .vital-item-modern:hover {
            border-color: var(--primary);
            background: var(--primary-bg);
        }
        
        .vital-item-modern .vital-label {
            font-size: 0.6rem;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
        }
        
        .vital-item-modern .vital-input {
            border: none;
            background: transparent;
            padding: 2px 0;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
            outline: none;
            width: 100%;
        }
        
        .vital-item-modern .vital-input:focus {
            color: var(--primary);
        }
        
        .vital-item-modern .vital-input::placeholder {
            color: var(--text-secondary);
            opacity: 0.4;
            font-weight: 400;
        }
        
        .vital-item-modern .vital-unit {
            font-size: 0.55rem;
            color: var(--text-secondary);
            display: block;
        }
        
        .vital-item-modern .vital-normal {
            font-size: 0.55rem;
            color: var(--success);
            display: block;
            margin-top: 2px;
        }
        
        .vital-item-modern.bmi-item {
            background: var(--primary-bg);
            border-color: var(--primary);
        }
        
        .vital-item-modern.bmi-item .vital-input {
            font-weight: 600;
            color: var(--primary);
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-modern {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: var(--radius);
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        
        .toast-modern.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-modern.success { background: var(--success); }
        .toast-modern.error { background: var(--danger); }
        .toast-modern.info { background: var(--primary); }
        .toast-modern.warning { background: var(--warning); }
        
        /* ================================================================
           ALERT
           ================================================================ */
        .alert-modern {
            padding: 14px 18px;
            border-radius: var(--radius);
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .alert-modern-success {
            background: var(--success-bg);
            color: var(--success-dark);
            border: 1px solid var(--success);
        }
        
        .alert-modern-error {
            background: var(--danger-bg);
            color: var(--danger-dark);
            border: 1px solid var(--danger);
        }
        
        .alert-modern i {
            font-size: 1.1rem;
            margin-top: 2px;
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer-modern {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer-modern .footer-brand {
            color: var(--primary);
            font-weight: 500;
        }
        
        /* ================================================================
           CHANGE MODE
           ================================================================ */
        .change-mode-active-modern {
            border-color: var(--warning) !important;
            box-shadow: 0 0 0 4px rgba(217, 119, 6, 0.12) !important;
        }
        
        .change-mode-active-modern .form-header {
            border-color: var(--warning) !important;
        }
        
        .change-mode-active-modern .form-icon {
            background: linear-gradient(135deg, var(--warning), #B45309) !important;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .form-card-modern { padding: 20px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .form-card-modern { padding: 14px; }
            .form-card-modern .form-header .form-title { font-size: 1rem; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .vital-grid-modern {
                grid-template-columns: repeat(2, 1fr);
            }
            .grid-2-modern {
                grid-template-columns: 1fr;
                gap: 14px;
            }
            .lab-modal-footer-modern {
                flex-direction: column;
                align-items: stretch;
            }
            .lab-modal-footer-modern .btn-modern {
                flex: 1;
                justify-content: center;
            }
            .form-actions-modern {
                flex-direction: column;
            }
            .form-actions-modern .btn-modern {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .form-card-modern { padding: 12px; }
            .vital-grid-modern { grid-template-columns: 1fr 1fr; }
            .lab-test-item-modern { flex-wrap: wrap; }
            .page-header .header-badge { font-size: 0.6rem; padding: 2px 10px; }
            .page-header .page-subtitle { font-size: 0.8rem; }
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
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
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34D399;
            animation: pulse-dot 1.5s infinite;
            margin-right: 4px;
        }
        
        /* ================================================================
           OPTGROUP STYLES
           ================================================================ */
        select optgroup {
            font-weight: 600;
            color: var(--text-secondary);
            padding: 4px 0;
        }
        
        select optgroup option {
            font-weight: 400;
            padding: 4px 8px;
        }
        
        select optgroup option[data-status="pending"] {
            border-left: 3px solid #D97706;
        }
        
        select optgroup option[data-status="assigned"] {
            border-left: 3px solid #059669;
        }
        
        select optgroup option[data-status="lab_only"] {
            border-left: 3px solid #7C3AED;
            border-left-style: dashed;
        }
        
        select optgroup option[data-status="no_visit"] {
            border-left: 3px solid #94A3B8;
            border-left-style: dotted;
        }
        
        /* ================================================================
           NEW PATIENT BADGE - PULSING
           ================================================================ */
        .new-patient-badge {
            display: inline-block;
            background: var(--success);
            color: white;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.5rem;
            font-weight: 700;
            text-transform: uppercase;
            animation: pulse-new 2s infinite;
            margin-left: 4px;
        }
        
        @keyframes pulse-new {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(0.95); }
        }
        
        /* ================================================================
           PATIENT OPTION STYLES
           ================================================================ */
        .patient-option-new {
            background: var(--success-bg) !important;
            font-weight: 600 !important;
        }
        
        .patient-option-new .new-tag {
            color: var(--success);
            font-weight: 700;
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - WITH CLOCK -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search patients...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <!-- ✅ CLOCK DISPLAY -->
        <span class="datetime" id="currentDateTime">
            <i class="fas fa-clock" style="color:var(--primary-light);"></i>
            <span id="clockDisplay" style="font-weight:500;"><?= date('d M Y • h:i:s A') ?></span>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
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
                Select patient, assign doctor or change existing doctor in <strong><?= htmlspecialchars($branch_name) ?></strong>
                
                <span class="header-badge" id="onlineDoctorBadge">
                    <i class="fas fa-user-md"></i>
                    <span class="online-count" id="onlineDoctorCount"><?= $online_doctors_count ?></span> Online
                </span>
                
                <span class="header-badge" id="offlineDoctorBadge">
                    <i class="fas fa-user-md"></i>
                    <span class="offline-count" id="offlineDoctorCount"><?= $offline_doctors_count ?></span> Offline
                </span>
                
                <span class="header-badge" id="pendingBadge">
                    <i class="fas fa-user-clock"></i>
                    <span id="pendingCount"><?= $pending_count ?></span> Pending
                </span>
                
                <span class="header-badge" id="assignedBadge">
                    <i class="fas fa-user-check"></i>
                    <span id="assignedCount"><?= $assigned_count ?></span> Assigned
                </span>
                
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-receipt"></i>
                    <span id="billStatus">Bill: Pending</span>
                </span>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert-modern alert-modern-<?= $message_type === 'success' ? 'success' : 'error' ?>" style="max-width:1100px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ASSIGNED PATIENTS LIST -->
    <!-- ================================================================ -->
    <div class="modern-card animate-fade-in-up">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-user-check"></i>
                Assigned Patients
                <span class="card-badge success" id="assignedListCount"><?= $assigned_count ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="text-xs text-gray-400" id="assignedListUpdate">(Auto-updated <?= date('h:i:s A') ?>)</span>
                <span class="text-xs text-green-500">
                    <span class="live-indicator-modern"></span> Live
                </span>
            </div>
        </div>
        
        <div id="assignedPatientsList">
            <?php if (count($assigned_patients) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                        <thead>
                            <tr style="border-bottom:2px solid var(--border-color);">
                                <th style="padding:10px 12px;text-align:left;font-weight:500;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);">Patient</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:500;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);">Patient ID</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:500;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);">Assigned Doctor</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:500;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);">Status</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:500;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);">Action</th>
                            </tr>
                        </thead>
                        <tbody id="assignedPatientsTableBody">
                            <?php foreach ($assigned_patients as $patient): ?>
                                <tr id="assigned-row-<?= $patient['id'] ?>" style="border-bottom:1px solid var(--border-color);">
                                    <td style="padding:10px 12px;font-weight:500;"><?= htmlspecialchars($patient['full_name']) ?></td>
                                    <td style="padding:10px 12px;font-family:monospace;font-size:0.8rem;"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                                    <td style="padding:10px 12px;">
                                        <?php if (!empty($patient['assigned_doctor_name'])): ?>
                                            <span class="assigned-doctor-tag-modern">
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
                                    <td style="padding:10px 12px;">
                                        <span class="status-badge-dropdown assigned">✅ Assigned</span>
                                    </td>
                                    <td style="padding:10px 12px;">
                                        <button onclick="selectPatientAndChange(<?= $patient['id'] ?>)" class="btn-modern btn-modern-warning btn-modern-sm" style="padding:4px 12px;font-size:0.7rem;">
                                            <i class="fas fa-sync-alt"></i> Change
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-6 text-gray-400" id="noAssignedPatients">
                    <i class="fas fa-user-check text-2xl block mb-2"></i>
                    <p>No patients currently assigned</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- LAB REQUESTS LIST -->
    <!-- ================================================================ -->
    <?php if ($selected_patient_id > 0): ?>
    <div class="modern-card animate-fade-in-up" style="border-color:var(--purple);border-width:2px;background:var(--purple-bg);" style="animation-delay:0.05s;">
        <div class="card-header" style="border-color:var(--purple);">
            <div class="card-title">
                <i class="fas fa-flask" style="color:var(--purple);"></i>
                Lab Requests
                <span class="card-badge" style="background:var(--purple);color:white;"><?= count($lab_requests) ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="text-xs text-gray-500">Auto-updated live</span>
                <span class="text-xs text-green-500">
                    <span class="live-indicator-modern"></span>
                </span>
            </div>
        </div>
        
        <div id="labRequestsList">
            <?php if (count($lab_requests) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                        <thead>
                            <tr style="border-bottom:2px solid var(--purple);">
                                <th style="padding:10px 12px;text-align:left;font-weight:500;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);">Request #</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:500;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);">Tests</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:500;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);">Status</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:500;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);">Total</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:500;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lab_requests as $req): 
                                $status_class = $req['status'] ?? 'pending';
                                $status_label = ucfirst(str_replace('_', ' ', $req['status'] ?? 'Pending'));
                                $icon = $status_class === 'completed' ? '✅' : ($status_class === 'in_progress' ? '⏳' : '⏰');
                                $lab_total = $req['lab_total'] ?? 0;
                            ?>
                                <tr style="border-bottom:1px solid var(--border-color);">
                                    <td style="padding:10px 12px;font-family:monospace;font-size:0.8rem;font-weight:500;"><?= htmlspecialchars($req['request_number'] ?? 'N/A') ?></td>
                                    <td style="padding:10px 12px;">
                                        <?= htmlspecialchars($req['test_names'] ?? 'No tests') ?>
                                        <span class="text-xs text-gray-400">(<?= $req['test_count'] ?? 0 ?> tests)</span>
                                    </td>
                                    <td style="padding:10px 12px;">
                                        <span class="lab-status-badge <?= $status_class ?>">
                                            <?= $icon ?> <?= $status_label ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px 12px;font-weight:500;color:var(--purple);">
                                        <?= $lab_total > 0 ? 'TSh ' . number_format($lab_total, 0) : 'TSh 0' ?>
                                    </td>
                                    <td style="padding:10px 12px;font-size:0.8rem;color:var(--text-secondary);">
                                        <?= date('d/m/Y H:i', strtotime($req['created_at'] ?? 'now')) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-6 text-gray-500">
                    <i class="fas fa-flask text-2xl block mb-2"></i>
                    <p>No lab requests for this patient</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ASSIGN FORM - MODERN WITH 2 COLUMNS PER ROW -->
    <!-- ================================================================ -->
    <div class="form-card-modern animate-fade-in-up <?= $change_mode ? 'change-mode-active-modern' : '' ?>" id="mainFormCard" style="animation-delay:0.1s;">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas <?= $change_mode ? 'fa-sync-alt' : 'fa-stethoscope' ?>"></i>
            </div>
            <div>
                <h3 class="form-title">
                    <?= $change_mode ? '🔄 Change Doctor' : 'Assign or Change Doctor' ?>
                    <?php if ($change_mode && $selected_patient_data): ?>
                        <span class="text-xs text-yellow-500" style="font-weight:400;">
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
                        <span class="text-yellow-500">🟡 Pending Patients</span> (no doctor) first →
                        <span class="text-green-500">✅ Assigned Patients</span> (have doctor) below
                    <?php endif; ?>
                    <span class="text-xs text-gray-400 ml-2" id="liveStatus">🔄 Live updates every 3s</span>
                    <span class="text-xs text-green-500 ml-2" id="billSentStatus">💰 Bill will be sent to Cashier</span>
                </p>
            </div>
        </div>
        
        <form method="POST" action="" id="assignForm">
            <input type="hidden" name="action" value="assign_doctor">
            
            <!-- ============================================================ -->
            <!-- ROW 1: PATIENT & ASSIGNMENT TYPE -->
            <!-- ============================================================ -->
            <div class="grid-2-modern">
                <div class="form-row-modern">
                    <label class="form-label">
                        <i class="fas fa-user label-icon"></i> Select Patient <span class="required">*</span>
                        <span class="label-badge">All Patients - Newest First</span>
                    </label>
                    <select name="patient_id" class="form-control-modern" required id="patientSelect" <?= $change_mode ? 'style="border-color:var(--warning);"' : '' ?>>
                        <option value="">-- Select Patient --</option>
                        
                        <?php 
                        if (!empty($all_patients) && is_array($all_patients) && count($all_patients) > 0): 
                        ?>
                            <optgroup label="📋 All Patients (<?= count($all_patients) ?> - Newest First)">
                                <?php foreach ($all_patients as $patient): 
                                    $status_label = '📋 No Visit';
                                    $status_class = 'no_visit';
                                    $status_icon = '📋';
                                    
                                    if (!empty($patient['visit_id'])) {
                                        if (in_array($patient['visit_status'], ['new', 'pending', 'lab_test'])) {
                                            if ($patient['visit_status'] === 'lab_test' && empty($patient['visit_doctor_id'])) {
                                                $status_label = '🧪 Lab Only';
                                                $status_class = 'lab_only';
                                                $status_icon = '🧪';
                                            } else {
                                                $status_label = '⏳ Pending';
                                                $status_class = 'pending';
                                                $status_icon = '⏳';
                                            }
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
                                    
                                    // Check if patient is new (created within last 7 days)
                                    $is_new = (strtotime($patient['patient_created_at'] ?? 'now') > strtotime('-7 days'));
                                    $new_badge = $is_new ? ' 🆕' : '';
                                    $new_class = $is_new ? 'patient-option-new' : '';
                                ?>
                                    <option value="<?= $patient['id'] ?>" data-status="<?= $status_class ?>" data-doctor="<?= htmlspecialchars($patient['assigned_doctor_name'] ?? '') ?>" <?= $selected ?> class="<?= $new_class ?>">
                                        <?= $status_icon ?> <?= htmlspecialchars($patient['full_name']) ?> (<?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>)
                                        <?php if ($is_new): ?>
                                            <span class="new-patient-badge">New</span>
                                        <?php endif; ?>
                                        <?php if (!empty($patient['phone'])): ?>
                                            - <?= htmlspecialchars($patient['phone']) ?>
                                        <?php endif; ?>
                                        <?= $doctor_info ?>
                                        <span class="status-badge-dropdown <?= $status_class ?>"><?= $status_label ?></span>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php else: ?>
                            <option value="" disabled>No patients found</option>
                        <?php endif; ?>
                    </select>
                    
                    <div class="mt-1 text-xs text-gray-400" id="patientStats">
                        <?php if ($pending_count > 0): ?>
                            <span class="text-yellow-500">🟡 <span id="pendingStat"><?= $pending_count ?></span> Pending</span>
                            <span class="mx-1">|</span>
                        <?php endif; ?>
                        <?php if ($assigned_count > 0): ?>
                            <span class="text-green-500">✅ <span id="assignedStat"><?= $assigned_count ?></span> Assigned</span>
                            <span class="mx-1">|</span>
                        <?php endif; ?>
                        <span class="text-gray-400">Total: <?= count($all_patients) ?> patients</span>
                        <span class="text-xs text-green-500 ml-2" id="liveUpdateStatus">🔄 Live</span>
                    </div>
                    
                    <!-- Selected patient info -->
                    <div id="selectedPatientInfo" class="mt-2 p-2 bg-primary-bg rounded-lg border border-primary-light" style="display:<?= $selected_patient_id > 0 && $selected_patient_data ? 'block' : 'none' ?>;">
                        <?php if ($selected_patient_data): ?>
                            <div class="flex items-center gap-2 text-sm flex-wrap">
                                <i class="fas fa-user-circle text-primary"></i>
                                <span class="font-semibold"><?= htmlspecialchars($selected_patient_data['full_name'] ?? '') ?></span>
                                <span class="text-gray-400">|</span>
                                <span><?= htmlspecialchars($selected_patient_data['patient_id'] ?? '') ?></span>
                                <?php if (!empty($selected_patient_data['assigned_doctor_name'])): ?>
                                    <span class="assigned-doctor-tag-modern">
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
                                <?php if ($change_mode): ?>
                                    <span class="text-xs text-yellow-500 font-bold" style="background:var(--warning-bg);padding:2px 8px;border-radius:12px;">
                                        🔄 Change Mode
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-row-modern">
                    <label class="form-label">
                        <i class="fas fa-tasks label-icon"></i> Select Action <span class="required">*</span>
                    </label>
                    <select name="assignment_type" class="form-control-modern" required id="assignmentTypeSelect" onchange="toggleAssignmentType(this.value)">
                        <option value="doctor" <?= $change_mode ? 'selected' : '' ?>>👨‍⚕️ Assign / Change Doctor</option>
                        <option value="lab">🧪 Request Lab Test(s)</option>
                    </select>
                    <p class="text-xs text-gray-400 mt-1" id="assignmentTypeHelp">👨‍⚕️ Assign a doctor to the patient or change existing doctor</p>
                </div>
            </div>
            
            <!-- ============================================================ -->
            <!-- ROW 2: DOCTOR & VISIT TYPE -->
            <!-- ============================================================ -->
            <div class="grid-2-modern" id="doctorSection">
                <div class="form-row-modern">
                    <label class="form-label">
                        <i class="fas fa-user-md label-icon"></i> Select Doctor <span class="required" id="doctorRequired">*</span>
                        <?php if ($change_mode): ?>
                            <span class="text-xs text-yellow-500 ml-2">🔄 Change Mode - Select new doctor</span>
                        <?php endif; ?>
                    </label>
                    <select name="doctor_id" class="form-control-modern" required id="doctorSelect" <?= $change_mode ? 'style="border-color:var(--warning);"' : '' ?>>
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
                    
                    <?php if (!empty($doctors) && count($doctors) > 0): ?>
                        <p class="text-xs text-gray-400 mt-1" id="doctorAvailability">
                            <i class="fas fa-info-circle mr-1"></i> 
                            <span class="text-green-500" id="onlineCountDisplay">🟢 <?= $online_doctors_count ?> online</span>
                            <span class="text-gray-400 mx-1">|</span>
                            <span class="text-gray-500" id="offlineCountDisplay">⚪ <?= $offline_doctors_count ?> offline</span>
                            <span class="text-xs text-gray-400 ml-2" id="lastDoctorUpdate">Updated: <?= date('H:i:s') ?></span>
                        </p>
                    <?php else: ?>
                        <p class="text-xs text-red-500 mt-1">
                            <i class="fas fa-exclamation-circle mr-1"></i> 
                            No doctors available in <?= htmlspecialchars($branch_name) ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="form-row-modern">
                    <label class="form-label">
                        <i class="fas fa-tag label-icon"></i> Visit Type <span class="required">*</span>
                        <span class="label-badge" id="visitTypePrice">Fee: TSh 15,000</span>
                    </label>
                    <select name="visit_type" class="form-control-modern" required id="visitTypeSelect" onchange="updateVisitTypePrice()">
                        <?php 
                        $default_key = 'general_consultation';
                        foreach ($visit_type_options as $key => $option):
                            // Default to New Patient / General Consultation
                            $is_default = (strpos(strtolower($option['name']), 'new') !== false || 
                                          strpos(strtolower($option['name']), 'general') !== false ||
                                          $key === 'new_patient');
                            $selected = $is_default ? 'selected' : '';
                            if ($is_default) $default_key = $key;
                        ?>
                            <option value="<?= htmlspecialchars($key) ?>" data-price="<?= $option['price'] ?>" data-id="<?= $option['id'] ?>" <?= $selected ?>>
                                <?= $option['icon'] ?? '🆕' ?> <?= htmlspecialchars($option['display_name']) ?> - TSh <?= number_format($option['price'], 0) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1" id="visitTypeDescription">
                        <i class="fas fa-info-circle mr-1"></i> 
                        <?= $visit_type_options[$default_key]['description'] ?? 'Standard doctor consultation' ?>
                    </p>
                </div>
            </div>
            
            <!-- ============================================================ -->
            <!-- ROW 3: SYMPTOMS (2 Columns - Common Symptoms + Symptoms Details) -->
            <!-- ============================================================ -->
            <div class="grid-2-modern" id="doctorSection">
                <div class="form-row-modern">
                    <label class="form-label">
                        <i class="fas fa-notes-medical label-icon"></i> Common Symptoms
                    </label>
                    <select name="symptoms_select" class="form-control-modern" id="symptomsSelect">
                        <option value="">-- Select Common Symptom --</option>
                        <?php foreach ($common_symptoms as $key => $symptom): ?>
                            <option value="<?= htmlspecialchars($symptom) ?>">
                                <?= htmlspecialchars($symptom) ?>
                            </option>
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
            
            <!-- ============================================================ -->
            <!-- ROW 4: COMPLAINT & NOTES (2 Columns) -->
            <!-- ============================================================ -->
            <div class="grid-2-modern" id="doctorSection">
                <div class="form-row-modern">
                    <label class="form-label">
                        <i class="fas fa-comment-medical label-icon"></i> Complaint / Reason
                    </label>
                    <textarea name="complaint" class="form-control-modern" placeholder="Patient's main complaint or reason for visit..." id="complaintInput" rows="3"></textarea>
                </div>
                
                <div class="form-row-modern">
                    <label class="form-label">
                        <i class="fas fa-sticky-note label-icon"></i> Additional Notes
                    </label>
                    <textarea name="notes" class="form-control-modern" placeholder="Any additional notes..." id="notesInput" rows="3"></textarea>
                </div>
            </div>
            
            <!-- ============================================================ -->
            <!-- LAB TEST SECTION -->
            <!-- ============================================================ -->
            <div id="labSection" style="display:none;">
                <div class="lab-modal-container-modern">
                    <div class="lab-modal-header-modern">
                        <div class="lab-modal-title">
                            <i class="fas fa-flask" style="color:var(--purple);"></i>
                            <span>Select Lab Tests</span>
                            <span class="lab-test-count" id="labSelectedCount">(0 selected)</span>
                        </div>
                        <button type="button" class="lab-modal-close-modern" onclick="closeLabTests()" title="Close Lab Tests">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="lab-modal-body-modern">
                        <div id="labTestsContainer" style="border:2px solid var(--border-color);border-radius:var(--radius);padding:4px 0;max-height:300px;overflow-y:auto;background:var(--bg-body);">
                            <?php if (!empty($lab_tests_catalog) && count($lab_tests_catalog) > 0): ?>
                                <?php foreach ($lab_tests_catalog as $test): ?>
                                    <div class="lab-test-item-modern">
                                        <input type="checkbox" name="lab_test_ids[]" value="<?= $test['id'] ?>" id="lab_test_<?= $test['id'] ?>" class="lab-test-checkbox" onchange="updateLabSelection()">
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
                                    <i class="fas fa-flask"></i>
                                    <p>No lab tests available</p>
                                    <p class="text-xs mt-1">Please add lab tests to the catalog first</p>
                                </div>
                            <?php endif; ?>
                        </div>
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
                        </div>
                        <button type="button" class="btn-modern btn-modern-primary btn-modern-sm" onclick="closeLabTests()" style="background:var(--danger);">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </div>
                
                <div class="lab-selected-summary-modern" id="labSelectedSummary" style="display:none;">
                    <span style="font-weight:600;color:var(--primary);">
                        <i class="fas fa-check-circle"></i> Selected Tests:
                    </span>
                    <span id="labSelectedNames" style="color:var(--text-primary);"></span>
                </div>
                
                <!-- Lab Notes -->
                <div class="form-row-modern" style="margin-top:12px;">
                    <label class="form-label">
                        <i class="fas fa-notes-medical label-icon"></i> Lab Test Notes
                    </label>
                    <textarea name="lab_notes" class="form-control-modern" placeholder="Any special instructions for lab tests..." rows="2" id="labNotes"></textarea>
                </div>
                
                <!-- Lab Symptoms & Complaint (2 Columns) -->
                <div class="grid-2-modern">
                    <div class="form-row-modern">
                        <label class="form-label">
                            <i class="fas fa-file-medical label-icon"></i> Symptoms Details
                        </label>
                        <textarea name="symptoms" class="form-control-modern" placeholder="Describe patient symptoms..." id="symptomsTextareaLab" rows="2"></textarea>
                    </div>
                    
                    <div class="form-row-modern">
                        <label class="form-label">
                            <i class="fas fa-comment-medical label-icon"></i> Complaint / Reason
                        </label>
                        <textarea name="complaint" class="form-control-modern" placeholder="Patient's main complaint..." id="complaintInputLab" rows="2"></textarea>
                    </div>
                </div>
            </div>
            
            <!-- ================================================================ -->
            <!-- VITAL SIGNS - 6 SIGNS (3x2 Grid) -->
            <!-- ================================================================ -->
            <div class="form-row-modern">
                <label class="form-label">
                    <i class="fas fa-heartbeat label-icon" style="color:#DC2626;"></i> Vital Signs
                    <span class="label-badge">Optional - 6 signs</span>
                    <?php if ($selected_patient_id > 0 && $latest_vital_signs): ?>
                        <span class="text-xs text-green-500 ml-2">
                            <i class="fas fa-check-circle"></i> Latest: <?= date('d/m/Y H:i', strtotime($latest_vital_signs['recorded_at'])) ?>
                        </span>
                    <?php endif; ?>
                </label>
                <div class="vital-grid-modern">
                    
                    <!-- 1. Temperature -->
                    <div class="vital-item-modern">
                        <span class="vital-label">🌡️ Temperature</span>
                        <input type="number" name="temperature" class="vital-input" 
                               step="0.1" min="35" max="42" placeholder="36.5"
                               value="<?= $latest_vital_signs['temperature'] ?? '' ?>">
                        <span class="vital-unit">°C</span>
                        <span class="vital-normal">Normal: 36.5-37.5</span>
                    </div>
                    
                    <!-- 2. Blood Pressure -->
                    <div class="vital-item-modern">
                        <span class="vital-label">💓 Blood Pressure</span>
                        <div style="display:flex;gap:4px;align-items:center;">
                            <input type="number" name="bp_systolic" class="vital-input" 
                                   style="width:45%;" min="80" max="220" placeholder="120"
                                   value="<?= $latest_vital_signs['blood_pressure_systolic'] ?? '' ?>">
                            <span style="color:var(--text-secondary);font-weight:700;">/</span>
                            <input type="number" name="bp_diastolic" class="vital-input" 
                                   style="width:45%;" min="50" max="140" placeholder="80"
                                   value="<?= $latest_vital_signs['blood_pressure_diastolic'] ?? '' ?>">
                        </div>
                        <span class="vital-unit">mmHg</span>
                        <span class="vital-normal">Normal: 120/80</span>
                    </div>
                    
                    <!-- 3. Pulse Rate -->
                    <div class="vital-item-modern">
                        <span class="vital-label">❤️ Pulse Rate</span>
                        <input type="number" name="pulse_rate" class="vital-input" 
                               min="40" max="180" placeholder="72"
                               value="<?= $latest_vital_signs['pulse_rate'] ?? '' ?>">
                        <span class="vital-unit">bpm</span>
                        <span class="vital-normal">Normal: 60-100</span>
                    </div>
                    
                    <!-- 4. Weight -->
                    <div class="vital-item-modern">
                        <span class="vital-label">⚖️ Weight</span>
                        <input type="number" name="weight" class="vital-input" 
                               step="0.1" min="2" max="300" placeholder="65"
                               value="<?= $latest_vital_signs['weight'] ?? '' ?>"
                               id="weightInput" oninput="calculateBMI()">
                        <span class="vital-unit">kg</span>
                        <span class="vital-normal">Recorded</span>
                    </div>
                    
                    <!-- 5. Height -->
                    <div class="vital-item-modern">
                        <span class="vital-label">📏 Height</span>
                        <input type="number" name="height" class="vital-input" 
                               step="0.1" min="40" max="250" placeholder="170"
                               value="<?= $latest_vital_signs['height'] ?? '' ?>"
                               id="heightInput" oninput="calculateBMI()">
                        <span class="vital-unit">cm</span>
                        <span class="vital-normal">Recorded</span>
                    </div>
                    
                    <!-- 6. BMI (Auto-calculated) -->
                    <div class="vital-item-modern bmi-item">
                        <span class="vital-label">📊 BMI</span>
                        <input type="number" name="bmi" class="vital-input" 
                               id="bmiOutput" readonly
                               step="0.1" placeholder="22.5"
                               value="<?= $latest_vital_signs['bmi'] ?? '' ?>">
                        <span class="vital-unit">kg/m²</span>
                        <span class="vital-normal" id="bmiCategory">Normal: 18.5-24.9</span>
                    </div>
                    
                </div>
                
                <!-- Vital Signs Notes -->
                <div class="mt-2">
                    <input type="text" name="vital_notes" class="form-control-modern" 
                           placeholder="Vital signs notes (optional)" 
                           value="<?= $latest_vital_signs['notes'] ?? '' ?>"
                           style="font-size:0.8rem;padding:6px 12px;">
                </div>
            </div>
            
            <!-- ============================================================ -->
            <!-- FORM ACTIONS -->
            <!-- ============================================================ -->
            <div class="form-actions-modern">
                <button type="submit" class="btn-modern <?= $change_mode ? 'btn-modern-warning' : 'btn-modern-primary' ?>" id="assignBtn">
                    <i class="fas <?= $change_mode ? 'fa-sync-alt' : 'fa-user-md' ?>"></i> 
                    <?= $change_mode ? 'Change Doctor' : 'Assign / Change Doctor' ?>
                </button>
                <button type="reset" class="btn-modern btn-modern-outline" id="resetBtn">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="dashboard.php" class="btn-modern btn-modern-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
            
            <!-- ============================================================ -->
            <!-- FOOTER INFO -->
            <!-- ============================================================ -->
            <div class="mt-4 pt-3 text-xs text-gray-400 text-center border-t border-gray-200 dark:border-gray-700">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>7 Days Rule:</strong> Consultation fee is waived if patient has a valid paid visit within the last 7 days.
                <span class="mx-2">|</span>
                <strong>Visit Type Change:</strong> If visit type changes, old bill is canceled and new bill created.
                <span class="mx-2">|</span>
                <span id="formTimestamp"><?= date('h:i:s A') ?></span>
                <span class="mx-2">|</span>
                <span class="text-green-500" id="liveIndicator">
                    <span class="live-indicator-modern"></span> Live
                </span>
                <?php if ($change_mode): ?>
                    <span class="mx-2">|</span>
                    <span class="text-yellow-500 font-bold">🔄 Change Mode Active</span>
                <?php endif; ?>
                <span class="mx-2">|</span>
                <span class="text-blue-400" id="cashierNotification">
                    <i class="fas fa-cash-register"></i> Bill sent to Cashier
                </span>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK STATS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-5" style="max-width:1100px;margin:24px auto 0;">
        <div class="stat-card-modern" id="pendingStats">
            <div class="stat-icon">🟡</div>
            <p class="stat-number primary" id="pendingStatNumber"><?= $pending_count ?></p>
            <p class="stat-label">Pending (No Doctor)</p>
            <p class="text-xs text-gray-400" id="pendingUpdateTime">Updated: <?= date('H:i:s') ?></p>
        </div>
        <div class="stat-card-modern" id="assignedStats">
            <div class="stat-icon">✅</div>
            <p class="stat-number green" id="assignedStatNumber"><?= $assigned_count ?></p>
            <p class="stat-label">Assigned (Has Doctor)</p>
            <p class="text-xs text-gray-400" id="assignedUpdateTime">Updated: <?= date('H:i:s') ?></p>
        </div>
        <div class="stat-card-modern" id="doctorStats">
            <div class="stat-icon">👨‍⚕️</div>
            <p class="stat-number orange" id="availableDoctorsStat"><?= $total_doctors ?></p>
            <p class="stat-label">Total Doctors</p>
            <p class="text-xs text-gray-400" id="onlineDoctorsStatTime">🟢 <?= $online_doctors_count ?> online, ⚪ <?= $offline_doctors_count ?> offline</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
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
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // CLOCK - UPDATE EVERY SECOND
    // ================================================================
    function updateClock() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('clockDisplay');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    
    // Call every second
    setInterval(updateClock, 1000);
    updateClock();

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
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // DATE & TIME (for footer)
    // ================================================================
    function updateFooterTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
        var formTimestamp = document.getElementById('formTimestamp');
        if (formTimestamp) {
            formTimestamp.textContent = timeStr;
        }
    }
    updateFooterTime();

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
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    // ================================================================
    // SYMPTOMS SELECT
    // ================================================================
    var symptomsSelect = document.getElementById('symptomsSelect');
    var symptomsTextarea = document.getElementById('symptomsTextarea');
    
    symptomsSelect?.addEventListener('change', function() {
        var value = this.value;
        var target = document.getElementById('symptomsTextarea');
        if (!target) return;
        
        if (value && value !== 'other') {
            var currentValue = target.value.trim();
            if (currentValue) {
                target.value = currentValue + ', ' + value;
            } else {
                target.value = value;
            }
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
            var color = '';
            if (bmi < 18.5) { category = 'Underweight'; color = '#D97706'; }
            else if (bmi < 25) { category = 'Normal'; color = '#059669'; }
            else if (bmi < 30) { category = 'Overweight'; color = '#D97706'; }
            else { category = 'Obese'; color = '#DC2626'; }
            
            bmiCategory.textContent = category + ' (18.5-24.9 Normal)';
            bmiCategory.style.color = color;
        } else {
            bmiOutput.value = '';
            bmiCategory.textContent = 'Normal: 18.5-24.9';
            bmiCategory.style.color = '';
        }
    }

    // ================================================================
    // TOGGLE ASSIGNMENT TYPE
    // ================================================================
    function toggleAssignmentType(type) {
        var doctorSection = document.getElementById('doctorSection');
        var labSection = document.getElementById('labSection');
        var doctorSelect = document.getElementById('doctorSelect');
        var doctorRequired = document.getElementById('doctorRequired');
        var assignBtn = document.getElementById('assignBtn');
        var helpText = document.getElementById('assignmentTypeHelp');
        
        if (type === 'lab') {
            doctorSection.style.display = 'none';
            labSection.style.display = 'block';
            doctorSelect.removeAttribute('required');
            if (doctorRequired) doctorRequired.textContent = '(Optional)';
            helpText.textContent = '🧪 Lab test request selected - Doctor not required';
            assignBtn.innerHTML = '<i class="fas fa-flask"></i> Request Lab Tests';
            openLabTests();
            
        } else {
            doctorSection.style.display = 'block';
            labSection.style.display = 'none';
            doctorSelect.setAttribute('required', 'required');
            if (doctorRequired) doctorRequired.textContent = '*';
            helpText.textContent = '👨‍⚕️ Doctor assignment selected - Doctor is required';
            assignBtn.innerHTML = '<i class="fas fa-user-md"></i> Assign / Change Doctor';
        }
    }

    // ================================================================
    // OPEN / CLOSE LAB TESTS
    // ================================================================
    function openLabTests() {
        var labSection = document.getElementById('labSection');
        if (labSection) labSection.style.display = 'block';
    }

    function closeLabTests() {
        var labSection = document.getElementById('labSection');
        if (labSection) labSection.style.display = 'none';
        
        var select = document.getElementById('assignmentTypeSelect');
        if (select) {
            select.value = 'doctor';
            toggleAssignmentType('doctor');
        }
    }

    // ================================================================
    // SELECT / DESELECT ALL LAB TESTS
    // ================================================================
    function selectAllLabTests() {
        var checkboxes = document.querySelectorAll('.lab-test-checkbox');
        checkboxes.forEach(function(cb) {
            cb.checked = true;
        });
        updateLabSelection();
    }

    function deselectAllLabTests() {
        var checkboxes = document.querySelectorAll('.lab-test-checkbox');
        checkboxes.forEach(function(cb) {
            cb.checked = false;
        });
        updateLabSelection();
    }

    // ================================================================
    // UPDATE LAB SELECTION
    // ================================================================
    function updateLabSelection() {
        var checkboxes = document.querySelectorAll('.lab-test-checkbox:checked');
        var count = checkboxes.length;
        var total = 0;
        var names = [];
        
        checkboxes.forEach(function(cb) {
            var item = cb.closest('.lab-test-item-modern');
            if (item) {
                var nameEl = item.querySelector('label strong');
                if (nameEl) {
                    names.push(nameEl.textContent);
                }
                var priceText = item.querySelector('.lab-test-price')?.textContent || '';
                var price = parseFloat(priceText.replace(/[^0-9.]/g, ''));
                if (!isNaN(price)) total += price;
            }
        });
        
        var countEl = document.getElementById('labSelectedCount');
        if (countEl) {
            countEl.textContent = '(' + count + ' selected)';
        }
        
        var totalPriceEl = document.getElementById('labTotalPrice');
        if (totalPriceEl) {
            if (count > 0) {
                totalPriceEl.textContent = 'Total: TSh ' + total.toLocaleString();
            } else {
                totalPriceEl.textContent = 'Total: TSh 0';
            }
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
    }

    // ================================================================
    // UPDATE VISIT TYPE PRICE AND DESCRIPTION
    // ================================================================
    function updateVisitTypePrice() {
        var select = document.getElementById('visitTypeSelect');
        var priceDisplay = document.getElementById('visitTypePrice');
        var descDisplay = document.getElementById('visitTypeDescription');
        
        if (!select || !priceDisplay) return;
        
        var selectedOption = select.options[select.selectedIndex];
        var price = selectedOption.dataset.price || 0;
        
        // Get description
        var key = select.value;
        <?php 
        $js_options = [];
        foreach ($visit_type_options as $key => $opt) {
            $js_options[$key] = [
                'price' => $opt['price'],
                'description' => $opt['description'] ?? '',
                'icon' => $opt['icon'] ?? '🆕'
            ];
        }
        ?>
        var options = <?= json_encode($js_options) ?>;
        
        var description = '';
        if (options[key]) {
            description = options[key].description || '';
        }
        
        priceDisplay.textContent = 'Fee: TSh ' + parseInt(price).toLocaleString();
        
        if (descDisplay) {
            if (description) {
                descDisplay.innerHTML = '<i class="fas fa-info-circle mr-1"></i> ' + description;
            } else {
                descDisplay.innerHTML = '<i class="fas fa-info-circle mr-1"></i> Select a visit type';
            }
        }
    }

    // ================================================================
    // ESCAPE HTML
    // ================================================================
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ================================================================
    // SELECT PATIENT AND CHANGE DOCTOR
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
        if (formCard) {
            formCard.classList.add('change-mode-active-modern');
        }
        
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
    // MANUAL REFRESH
    // ================================================================
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        btn.innerHTML = '<span class="spinner"></span> Loading...';
        btn.disabled = true;
        
        setTimeout(function() {
            window.location.reload();
        }, 1000);
        
        setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            btn.disabled = false;
            showToast('✅ Refreshed', 'Page data updated manually', 'success');
        }, 2000);
    }

    // ================================================================
    // LIVE DATA UPDATE - EVERY 3 SECONDS - AUTO UPDATE
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    var currentPatientId = <?= $selected_patient_id ?: 0 ?>;

    function fetchLiveData() {
        if (isUpdating) return;
        isUpdating = true;
        
        var formData = new FormData();
        formData.append('action', 'get_live_data');
        formData.append('branch_id', <?= json_encode($selected_branch_id) ?>);
        
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(response) { return response.json(); })
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
        var pendingCount = document.getElementById('pendingCount');
        var assignedCount = document.getElementById('assignedCount');
        var pendingStat = document.getElementById('pendingStat');
        var assignedStat = document.getElementById('assignedStat');
        var pendingStatNumber = document.getElementById('pendingStatNumber');
        var assignedStatNumber = document.getElementById('assignedStatNumber');
        var onlineDoctorCount = document.getElementById('onlineDoctorCount');
        var offlineDoctorCount = document.getElementById('offlineDoctorCount');
        var availableDoctorsStat = document.getElementById('availableDoctorsStat');
        var onlineDoctorsStatTime = document.getElementById('onlineDoctorsStatTime');
        var assignedListCount = document.getElementById('assignedListCount');
        var onlineCountDisplay = document.getElementById('onlineCountDisplay');
        var offlineCountDisplay = document.getElementById('offlineCountDisplay');
        
        if (pendingCount) pendingCount.textContent = data.pending_count;
        if (assignedCount) assignedCount.textContent = data.assigned_count;
        if (pendingStat) pendingStat.textContent = data.pending_count;
        if (assignedStat) assignedStat.textContent = data.assigned_count;
        if (pendingStatNumber) pendingStatNumber.textContent = data.pending_count;
        if (assignedStatNumber) assignedStatNumber.textContent = data.assigned_count;
        if (onlineDoctorCount) onlineDoctorCount.textContent = data.online_count;
        if (offlineDoctorCount) offlineDoctorCount.textContent = data.offline_count;
        if (availableDoctorsStat) availableDoctorsStat.textContent = data.total_doctors;
        if (onlineDoctorsStatTime) onlineDoctorsStatTime.textContent = '🟢 ' + data.online_count + ' online, ⚪ ' + data.offline_count + ' offline';
        if (assignedListCount) assignedListCount.textContent = data.assigned_count;
        if (onlineCountDisplay) onlineCountDisplay.textContent = '🟢 ' + data.online_count + ' online';
        if (offlineCountDisplay) offlineCountDisplay.textContent = '⚪ ' + data.offline_count + ' offline';
        
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        
        var pendingUpdate = document.getElementById('pendingUpdateTime');
        var assignedUpdate = document.getElementById('assignedUpdateTime');
        var lastDoctorUpdate = document.getElementById('lastDoctorUpdate');
        var liveUpdateStatus = document.getElementById('liveUpdateStatus');
        var assignedListUpdate = document.getElementById('assignedListUpdate');
        
        if (pendingUpdate) pendingUpdate.textContent = 'Updated: ' + timeStr;
        if (assignedUpdate) assignedUpdate.textContent = 'Updated: ' + timeStr;
        if (assignedListUpdate) assignedListUpdate.textContent = '(Auto-updated ' + timeStr + ')';
        if (lastDoctorUpdate) lastDoctorUpdate.textContent = 'Updated: ' + timeStr;
        if (liveUpdateStatus) {
            liveUpdateStatus.textContent = '🔄 Live ' + timeStr;
        }
        
        // Update patient select
        var patientSelect = document.getElementById('patientSelect');
        if (patientSelect && data.patient_options !== undefined) {
            var currentValue = patientSelect.value;
            var newOptions = data.patient_options;
            
            if (patientSelect.innerHTML !== newOptions) {
                patientSelect.innerHTML = newOptions;
                if (currentValue) {
                    var found = false;
                    var options = patientSelect.querySelectorAll('option');
                    options.forEach(function(opt) {
                        if (opt.value == currentValue) found = true;
                    });
                    if (found) {
                        patientSelect.value = currentValue;
                    } else {
                        var infoDiv = document.getElementById('selectedPatientInfo');
                        if (infoDiv) {
                            infoDiv.style.display = 'none';
                        }
                    }
                }
            }
        }
        
        // Update assigned patients list
        var assignedTableBody = document.getElementById('assignedPatientsTableBody');
        var noAssigned = document.getElementById('noAssignedPatients');
        if (assignedTableBody && data.assigned_list_html !== undefined) {
            if (data.assigned_list_count > 0) {
                assignedTableBody.innerHTML = data.assigned_list_html;
                if (noAssigned) noAssigned.style.display = 'none';
            } else {
                assignedTableBody.innerHTML = '';
                if (noAssigned) noAssigned.style.display = 'block';
            }
        }
        
        // ✅ Update doctor select with online/offline counts
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
                    if (found) {
                        doctorSelect.value = currentDocValue;
                    }
                }
            }
        }
        
        // Update visit type options
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
                    if (found) {
                        visitTypeSelect.value = currentVisitValue;
                    }
                }
                updateVisitTypePrice();
            }
        }
        
        // Update lab tests
        var labContainer = document.getElementById('labTestsContainer');
        if (labContainer && data.lab_tests_html !== undefined) {
            labContainer.innerHTML = data.lab_tests_html;
            var checkboxes = labContainer.querySelectorAll('.lab-test-checkbox');
            checkboxes.forEach(function(cb) {
                cb.addEventListener('change', updateLabSelection);
            });
        }
    }

    function startLiveUpdate() {
        if (updateInterval) clearInterval(updateInterval);
        fetchLiveData();
        updateInterval = setInterval(fetchLiveData, 3000);
        console.log('%c🔄 Live update started (every 3s)', 'font-size:12px; color:#34D399;');
    }

    function stopLiveUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            console.log('%c⏹️ Live update stopped', 'font-size:12px; color:#DC2626;');
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopLiveUpdate();
        } else {
            startLiveUpdate();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        var labSymptoms = document.getElementById('symptomsTextareaLab');
        var mainSymptoms = document.getElementById('symptomsTextarea');
        var labComplaint = document.getElementById('complaintInputLab');
        var mainComplaint = document.getElementById('complaintInput');
        var labNotes = document.getElementById('labNotes');
        var mainNotes = document.getElementById('notesInput');
        
        if (labSymptoms && mainSymptoms) {
            labSymptoms.addEventListener('input', function() {
                mainSymptoms.value = this.value;
            });
        }
        
        if (labComplaint && mainComplaint) {
            labComplaint.addEventListener('input', function() {
                mainComplaint.value = this.value;
            });
        }
        
        if (labNotes && mainNotes) {
            labNotes.addEventListener('input', function() {
                mainNotes.value = this.value;
            });
        }
        
        calculateBMI();
        updateVisitTypePrice();
        
        setTimeout(function() {
            startLiveUpdate();
        }, 2000);
        
        var patientSelect = document.getElementById('patientSelect');
        patientSelect?.addEventListener('change', function() {
            var selectedId = this.value;
            if (selectedId) {
                fetchPatientDetails(selectedId);
            }
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
    });

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
                        
                        var visitStatus = data.visit_status || 'none';
                        var statusHtml = '';
                        if (visitStatus !== 'none') {
                            var statusClass = visitStatus;
                            var statusLabel = visitStatus.charAt(0).toUpperCase() + visitStatus.slice(1);
                            if (visitStatus === 'lab_test' && data.is_lab_only) {
                                statusClass = 'lab_only';
                                statusLabel = '🧪 Lab Only';
                            }
                            statusHtml = '<span class="status-badge-dropdown ' + statusClass + '">' + statusLabel + '</span>';
                        }
                        
                        var changeModeHtml = '';
                        if (<?= $change_mode ? 'true' : 'false' ?>) {
                            changeModeHtml = '<span class="text-xs text-yellow-500 font-bold" style="background:var(--warning-bg);padding:2px 8px;border-radius:12px;">🔄 Change Mode</span>';
                        }
                        
                        var feeHtml = '';
                        if (data.consultation_fee > 0) {
                            feeHtml = '<span class="text-xs text-green-500">Fee: TSh ' + data.consultation_fee + '</span>';
                        } else if (data.is_lab_only) {
                            feeHtml = '<span class="text-xs text-purple-500">🧪 Lab Only (No consultation fee)</span>';
                        }
                        
                        infoDiv.innerHTML = `
                            <div class="flex items-center gap-2 text-sm flex-wrap">
                                <i class="fas fa-user-circle text-primary"></i>
                                <span class="font-semibold">${escapeHtml(data.patient.full_name || '')}</span>
                                <span class="text-gray-400">|</span>
                                <span>${escapeHtml(data.patient.patient_id || '')}</span>
                                ${doctorHtml}
                                ${statusHtml}
                                ${feeHtml}
                                ${changeModeHtml}
                            </div>
                        `;
                        infoDiv.style.display = 'block';
                    }
                }
            })
            .catch(function(error) {
                console.error('Error fetching patient details:', error);
            });
    }

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
        
        var selectedOption = visitTypeSelect.options[visitTypeSelect.selectedIndex];
        var serviceId = selectedOption.dataset.id || '';
        formData.append('service_id', serviceId);
        
        var btn = document.getElementById('assignBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Assigning...';
        
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-user-md"></i> Assign / Change Doctor';
                
                if (data.success) {
                    // ✅ Show bill sent to cashier message
                    var billMessage = '';
                    if (data.bill_sent_to_cashier) {
                        billMessage = ' 💰 Bill #' + data.bill.bill_number + ' sent to Cashier!';
                        // Update bill status badge
                        var billStatus = document.getElementById('billStatus');
                        if (billStatus) {
                            billStatus.textContent = 'Bill: Sent to Cashier ✅';
                            billStatus.style.color = '#34D399';
                        }
                        // Show notification in footer
                        var cashierNotif = document.getElementById('cashierNotification');
                        if (cashierNotif) {
                            cashierNotif.innerHTML = '<i class="fas fa-cash-register"></i> Bill #' + data.bill.bill_number + ' sent to Cashier ✅';
                            cashierNotif.style.color = '#34D399';
                        }
                    }
                    
                    showToast('✅ Success', data.message + billMessage, 'success');
                    
                    if (data.patient_id) {
                        var row = document.getElementById('assigned-row-' + data.patient_id);
                        if (row) {
                            row.remove();
                        }
                        
                        var assignedCount = document.getElementById('assignedCount');
                        var assignedStat = document.getElementById('assignedStat');
                        var assignedStatNumber = document.getElementById('assignedStatNumber');
                        var assignedListCount = document.getElementById('assignedListCount');
                        
                        if (assignedCount) assignedCount.textContent = parseInt(assignedCount.textContent) + 1;
                        if (assignedStat) assignedStat.textContent = parseInt(assignedStat.textContent) + 1;
                        if (assignedStatNumber) assignedStatNumber.textContent = parseInt(assignedStatNumber.textContent) + 1;
                        if (assignedListCount) assignedListCount.textContent = parseInt(assignedListCount.textContent) + 1;
                        
                        var infoDiv = document.getElementById('selectedPatientInfo');
                        if (infoDiv) {
                            infoDiv.style.display = 'none';
                        }
                        
                        var formCard = document.getElementById('mainFormCard');
                        if (formCard) {
                            formCard.classList.remove('change-mode-active-modern');
                        }
                        
                        fetchLiveData();
                        
                        setTimeout(function() {
                            window.location.href = 'assign_doctor.php';
                        }, 2000);
                    }
                } else {
                    showToast('❌ Error', data.message || 'Failed to assign doctor', 'error');
                }
            })
            .catch(function(error) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-user-md"></i> Assign / Change Doctor';
                showToast('❌ Error', 'Network error: ' + error.message, 'error');
            });
    });

    console.log('%c👨‍⚕️ Braick - Assign / Change Doctor (MODERN - 2 Columns per Row)', 'font-size:18px; font-weight:bold; color:#2563EB;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👥 All Patients: <?= count($all_patients) ?> (Newest First)', 'font-size:13px; color:#64748B;');
    console.log('%c🟡 Pending: <?= $pending_count ?>', 'font-size:13px; color:#D97706;');
    console.log('%c✅ Assigned: <?= $assigned_count ?>', 'font-size:13px; color:#059669;');
    console.log('%c👨‍⚕️ Doctors: <?= $total_doctors ?> (🟢 <?= $online_doctors_count ?> online, ⚪ <?= $offline_doctors_count ?> offline)', 'font-size:13px; color:#64748B;');
    console.log('%c🔄 Live updates every 3 seconds - Auto-update (No refresh needed!)', 'font-size:13px; color:#34D399;');
    console.log('%c📋 Visit Type: From services table (category_id=2) - Default: New Patient', 'font-size:13px; color:#2563EB;');
    console.log('%c💰 Fees: Included after assign doctor - Bill sent to Cashier', 'font-size:13px; color:#059669;');
    console.log('%c📋 7 Days Rule: Valid paid visit waives consultation fee', 'font-size:13px; color:#F59E0B;');
    console.log('%c🧪 Lab Request - NO bill (Lab creates bill when confirming)', 'font-size:13px; color:#7C3AED;');
    console.log('%c🟢⚪ Doctor online/offline status updates live without refresh!', 'font-size:13px; color:#34D399;');
    console.log('%c📐 2 Columns per row - Clean modern layout!', 'font-size:13px; color:#2563EB;');
    console.log('%c🕐 Clock updates every second - Shared header with time', 'font-size:13px; color:#8B5CF6;');
    console.log('%c💰 Bill sent to Cashier with notification', 'font-size:13px; color:#F59E0B;');
    console.log('%c🆕 NEW patients shown first - Ascending order', 'font-size:13px; color:#059669;');
    console.log('%c🎨 Fresh modern design with blue theme', 'font-size:13px; color:#2563EB;');
</script>

</body>
</html>