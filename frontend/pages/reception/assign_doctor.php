<?php
// ================================================================
// FILE: frontend/pages/reception/assign_doctor.php
// RECEPTION - ASSIGN / CHANGE DOCTOR FOR PATIENT
// SHOWS ALL PATIENTS WITH ACTIVE VISIT (NEW + PENDING + ASSIGNED)
// FEES ARE HIDDEN - TAKEN FROM DATABASE AUTOMATICALLY
// WITH 7 DAYS FEE VALIDATION
// BILLS GO TO CASHIER AUTOMATICALLY
// AUTO-UPDATE DOCTOR DROPDOWN (3 SECONDS) - INSTANT STATUS CHANGE
// FIXED: Dynamic fee mapping based on visit type
// BRAICK DISPENSARY
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

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = $user_branch_id;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$message = '';
$message_type = '';

// Initialize variables
$all_active_patients = [];
$pending_patients = [];
$assigned_patients = [];
$doctors = [];
$online_doctors_count = 0;
$total_doctors = 0;
$visit_type_mapping = [];
$pending_count = 0;
$assigned_count = 0;

try {
    $db = getDB();
    
    // ================================================================
    // GET CONSULTATION FEES FROM DATABASE (DYNAMIC MAPPING)
    // ================================================================
    $visit_type_mapping = [];
    
    // Get all consultation services
    $stmt = $db->prepare("
        SELECT s.id, s.service_name, s.price, sc.category_name
        FROM services s
        JOIN service_categories sc ON s.category_id = sc.id
        WHERE sc.category_name = 'Consultation'
        AND s.is_active = 1
        ORDER BY s.service_name
    ");
    $stmt->execute();
    $services = $stmt->fetchAll();
    
    // Map services to visit types based on service name
    foreach ($services as $service) {
        $service_name = strtolower($service['service_name']);
        
        // Map service names to visit types
        if (strpos($service_name, 'general') !== false || strpos($service_name, 'new') !== false) {
            $visit_type_mapping['new'] = [
                'fee' => $service['price'],
                'id' => $service['id'],
                'name' => $service['service_name']
            ];
        } elseif (strpos($service_name, 'follow-up') !== false || strpos($service_name, 'followup') !== false) {
            $visit_type_mapping['follow-up'] = [
                'fee' => $service['price'],
                'id' => $service['id'],
                'name' => $service['service_name']
            ];
        } elseif (strpos($service_name, 'emergency') !== false) {
            $visit_type_mapping['emergency'] = [
                'fee' => $service['price'],
                'id' => $service['id'],
                'name' => $service['service_name']
            ];
        } elseif (strpos($service_name, 'specialist') !== false) {
            $visit_type_mapping['specialist'] = [
                'fee' => $service['price'],
                'id' => $service['id'],
                'name' => $service['service_name']
            ];
        }
    }
    
    // Fallback: if no mapping found, use default values
    if (empty($visit_type_mapping)) {
        // Try to get any consultation services
        $stmt = $db->prepare("
            SELECT id, service_name, price 
            FROM services 
            WHERE category_id = (SELECT id FROM service_categories WHERE category_name = 'Consultation' LIMIT 1)
            AND is_active = 1
            LIMIT 4
        ");
        $stmt->execute();
        $fallback_services = $stmt->fetchAll();
        
        if (count($fallback_services) >= 4) {
            $visit_type_mapping = [
                'new' => ['fee' => $fallback_services[0]['price'], 'id' => $fallback_services[0]['id'], 'name' => $fallback_services[0]['service_name']],
                'follow-up' => ['fee' => $fallback_services[1]['price'], 'id' => $fallback_services[1]['id'], 'name' => $fallback_services[1]['service_name']],
                'emergency' => ['fee' => $fallback_services[2]['price'], 'id' => $fallback_services[2]['id'], 'name' => $fallback_services[2]['service_name']],
                'specialist' => ['fee' => $fallback_services[3]['price'], 'id' => $fallback_services[3]['id'], 'name' => $fallback_services[3]['service_name']]
            ];
        } else {
            // Ultimate fallback - hardcoded values
            $visit_type_mapping = [
                'new' => ['fee' => 15000, 'id' => null, 'name' => 'General Consultation'],
                'follow-up' => ['fee' => 10000, 'id' => null, 'name' => 'Follow-up Consultation'],
                'emergency' => ['fee' => 25000, 'id' => null, 'name' => 'Emergency Consultation'],
                'specialist' => ['fee' => 30000, 'id' => null, 'name' => 'Specialist Consultation']
            ];
        }
    }
    
    // ================================================================
    // GET ALL PATIENTS WITH ACTIVE VISIT
    // ================================================================
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.full_name,
            p.patient_id,
            p.phone,
            p.assigned_doctor_id,
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
        INNER JOIN visits v ON p.id = v.patient_id
        LEFT JOIN users u ON p.assigned_doctor_id = u.id
        WHERE p.branch_id = ? 
        AND v.status IN ('new', 'pending', 'assigned')
        ORDER BY 
            CASE 
                WHEN v.status IN ('new', 'pending') THEN 0
                WHEN v.status = 'assigned' THEN 1
                ELSE 2
            END,
            p.full_name
    ");
    $stmt->execute([$selected_branch_id]);
    $all_active_patients = $stmt->fetchAll();
    
    // ================================================================
    // CHECK 7 DAYS FEE VALIDITY FOR EACH PATIENT
    // ================================================================
    $pending_patients = [];
    $assigned_patients = [];
    $pending_count = 0;
    $assigned_count = 0;
    
    foreach ($all_active_patients as $patient) {
        // Check if patient has paid visit within last 7 days
        $stmt = $db->prepare("
            SELECT pb.created_at as paid_date, pb.total_amount
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
        
        if (in_array($patient['visit_status'], ['new', 'pending'])) {
            $pending_patients[] = $patient;
            $pending_count++;
        } elseif ($patient['visit_status'] === 'assigned') {
            $assigned_patients[] = $patient;
            $assigned_count++;
        }
    }
    
    // ================================================================
    // GET DOCTORS IN THIS BRANCH (Initial load)
    // ================================================================
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
            $online_doctors_count++;
        }
    }
    $total_doctors = count($doctors);
    
    // ================================================================
    // HANDLE FORM SUBMISSION
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $patient_id = (int)($_POST['patient_id'] ?? 0);
        $doctor_id = (int)($_POST['doctor_id'] ?? 0);
        $visit_type = $_POST['visit_type'] ?? 'new';
        $symptoms = trim($_POST['symptoms'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        $errors = [];
        if ($patient_id <= 0) $errors[] = 'Please select a patient';
        if ($doctor_id <= 0) $errors[] = 'Please select a doctor';
        
        if (empty($errors)) {
            $stmt = $db->prepare("SELECT id, is_online FROM users WHERE id = ? AND role = 'doctor' AND status = 'active' AND branch_id = ?");
            $stmt->execute([$doctor_id, $selected_branch_id]);
            $doctor_check = $stmt->fetch();
            if (!$doctor_check) {
                $errors[] = 'Selected doctor is not available.';
            }
        }
        
        if (empty($errors)) {
            // ================================================================
            // GET FEE BASED ON VISIT TYPE USING DYNAMIC MAPPING
            // ================================================================
            $fee_key = $visit_type;
            $consultation_fee = $visit_type_mapping[$fee_key]['fee'] ?? 0;
            $consultation_service_id = $visit_type_mapping[$fee_key]['id'] ?? null;
            $consultation_service_name = $visit_type_mapping[$fee_key]['name'] ?? 'Consultation Fee';
            
            // ================================================================
            // CHECK 7 DAYS VALIDITY - WAIVE FEE IF PAID WITHIN 7 DAYS
            // ================================================================
            $charge_fee = true;
            $stmt = $db->prepare("
                SELECT pb.created_at as paid_date, pb.total_amount
                FROM patient_bills pb
                WHERE pb.patient_id = ? 
                AND pb.branch_id = ? 
                AND pb.status = 'paid'
                AND pb.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY pb.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$patient_id, $selected_branch_id]);
            $paid_visit = $stmt->fetch();
            
            if ($paid_visit) {
                $charge_fee = false; // Fee is waived (valid within 7 days)
                $consultation_fee = 0;
            }
            
            // Check if patient has existing active visit
            $stmt = $db->prepare("
                SELECT id, status, visit_type, doctor_id FROM visits 
                WHERE patient_id = ? AND status IN ('new', 'pending', 'assigned') 
                AND branch_id = ?
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$patient_id, $selected_branch_id]);
            $existing_visit = $stmt->fetch();
            
            $visit_id = null;
            $visit_number = '';
            $is_new_visit = false;
            
            if ($existing_visit) {
                // Update existing visit
                $visit_id = $existing_visit['id'];
                $stmt = $db->prepare("
                    UPDATE visits 
                    SET doctor_id = ?, status = 'assigned', 
                        visit_type = ?, symptoms = ?, notes = ?, updated_at = NOW(),
                        consultation_fee = ?
                    WHERE id = ?
                ");
                $stmt->execute([$doctor_id, $visit_type, $symptoms, $notes, $consultation_fee, $visit_id]);
                
                $stmt = $db->prepare("SELECT visit_number FROM visits WHERE id = ?");
                $stmt->execute([$visit_id]);
                $visit = $stmt->fetch();
                $visit_number = $visit['visit_number'] ?? '';
                
                if ($charge_fee && $consultation_fee > 0) {
                    $message = "✅ Doctor changed successfully! Visit #" . $visit_number . " - Fee: TSh " . number_format($consultation_fee) . " (" . $consultation_service_name . ")";
                } else {
                    $message = "✅ Doctor changed successfully! Visit #" . $visit_number . " - Fee WAIVED (valid paid visit within 7 days)";
                }
                $message_type = 'success';
                
            } else {
                // Create new visit
                $visit_number = 'V-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                $is_new_visit = true;
                
                $stmt = $db->prepare("
                    INSERT INTO visits (
                        visit_number, patient_id, doctor_id, branch_id, 
                        visit_type, status, symptoms, notes, created_at, updated_at,
                        consultation_fee
                    ) VALUES (?, ?, ?, ?, ?, 'assigned', ?, ?, NOW(), NOW(), ?)
                ");
                
                if ($stmt->execute([$visit_number, $patient_id, $doctor_id, $selected_branch_id, 
                    $visit_type, $symptoms, $notes, $consultation_fee])) {
                    $visit_id = $db->lastInsertId();
                    
                    if ($charge_fee && $consultation_fee > 0) {
                        $message = "✅ Doctor assigned successfully! Visit #$visit_number - Fee: TSh " . number_format($consultation_fee) . " (" . $consultation_service_name . ")";
                    } else {
                        $message = "✅ Doctor assigned successfully! Visit #$visit_number - Fee WAIVED (valid paid visit within 7 days)";
                    }
                    $message_type = 'success';
                } else {
                    $message = "❌ Failed to assign doctor!";
                    $message_type = 'error';
                }
            }
            
            // ================================================================
            // CREATE BILL IF FEE IS CHARGED
            // ================================================================
            if ($message_type === 'success' && $consultation_fee > 0 && $visit_id) {
                $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($patient_id, 6, '0', STR_PAD_LEFT);
                
                try {
                    // Check if bill already exists for this visit
                    $stmt = $db->prepare("SELECT id FROM patient_bills WHERE visit_id = ?");
                    $stmt->execute([$visit_id]);
                    $existing_bill = $stmt->fetch();
                    
                    if (!$existing_bill) {
                        // Insert bill
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
                            $_SESSION['user_id'],
                            $selected_branch_id
                        ]);
                        $bill_id = $db->lastInsertId();
                        
                        // Add bill item
                        $stmt = $db->prepare("
                            INSERT INTO bill_items (
                                bill_id, item_type, item_id, item_name, 
                                quantity, unit_price, total_price, created_at
                            ) VALUES (?, 'consultation', ?, ?, 1, ?, ?, NOW())
                        ");
                        $type_labels = [
                            'new' => 'New Patient',
                            'follow-up' => 'Follow-up',
                            'emergency' => 'Emergency',
                            'specialist' => 'Specialist'
                        ];
                        $type_label = $type_labels[$visit_type] ?? ucfirst($visit_type);
                        $item_name = $consultation_service_name . ' (' . $type_label . ')';
                        
                        $stmt->execute([
                            $bill_id,
                            $consultation_service_id,
                            $item_name,
                            $consultation_fee,
                            $consultation_fee
                        ]);
                        
                        $_SESSION['current_bill_id'] = $bill_id;
                    }
                    
                } catch (Exception $e) {
                    error_log("Bill creation failed: " . $e->getMessage());
                }
            }
            
            // UPDATE PATIENT ASSIGNED DOCTOR
            if ($message_type === 'success') {
                $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = ? WHERE id = ?");
                $stmt->execute([$doctor_id, $patient_id]);
                
                // Log activity
                try {
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (user_id, action, details, created_at) 
                        VALUES (?, 'doctor_assigned', ?, NOW())
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'], 
                        "Doctor assigned/changed for patient ID: $patient_id in $branch_name - Visit: $visit_number - Fee: TSh " . number_format($consultation_fee)
                    ]);
                } catch (Exception $e) {}
                
                echo '<script>
                    setTimeout(function(){ 
                        window.location.href = "assign_doctor.php?success=1"; 
                    }, 2000);
                </script>';
            }
            
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $all_active_patients = [];
    $pending_patients = [];
    $assigned_patients = [];
    $doctors = [];
    $visit_type_mapping = [];
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
// INCLUDE SHARED HEADER & SIDEBAR
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
           ROOT VARIABLES
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
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
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
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
           TOP NAV
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
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
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
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
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
            border-radius: 10px;
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
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
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
            background: rgba(255,255,255,0.15);
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
        }
        
        .page-header .header-badge .online-count {
            color: #34D399;
            font-weight: 700;
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 10px;
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
            background: rgba(255,255,255,0.12);
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
           FORM CARD
           ================================================================ */
        .form-card {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 32px 36px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            max-width: 1000px;
            margin: 0 auto;
            box-shadow: var(--shadow-md);
        }
        
        .form-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 30px rgba(11, 94, 215, 0.08);
        }
        
        .form-card .form-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .form-card .form-header .form-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        
        .form-card .form-header .form-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .form-card .form-header .form-subtitle {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
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
        
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.08);
        }
        
        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }
        
        .form-control:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .form-row {
            margin-bottom: 18px;
        }
        
        .form-row:last-child {
            margin-bottom: 0;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        select.form-control {
            appearance: auto;
            cursor: pointer;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 60px;
        }
        
        /* ================================================================
           STATUS BADGE
           ================================================================ */
        .status-badge-dropdown {
            display: inline-block;
            font-size: 0.55rem;
            font-weight: 600;
            padding: 1px 8px;
            border-radius: 8px;
            margin-left: 4px;
        }
        
        .status-badge-dropdown.pending {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .status-badge-dropdown.assigned {
            background: #E8F0FE;
            color: #0B5ED7;
        }
        
        [data-theme="dark"] .status-badge-dropdown.pending {
            background: #3D2E0A;
            color: #FBBF24;
        }
        
        [data-theme="dark"] .status-badge-dropdown.assigned {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(11, 94, 215, 0.35);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-sm { 
            padding: 5px 14px; 
            font-size: 0.75rem; 
            border-radius: 8px; 
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .role-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--primary-bg);
            color: var(--primary);
            text-transform: uppercase;
        }
        
        [data-theme="dark"] .role-badge-display {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .branch-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        [data-theme="dark"] .branch-badge-display {
            background: #1A3A2A;
            color: #34D399;
        }
        
        /* ================================================================
           STATS CARD
           ================================================================ */
        .stat-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .stat-card .stat-number.primary {
            color: var(--primary);
        }
        
        .stat-card .stat-number.green {
            color: var(--success);
        }
        
        .stat-card .stat-number.orange {
            color: #D97706;
        }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .stat-card .stat-icon {
            font-size: 1.4rem;
            margin-bottom: 4px;
        }
        
        /* ================================================================
           DOCTOR STATUS UPDATE ANIMATION
           ================================================================ */
        .doctor-status-updated {
            animation: flashUpdate 0.6s ease;
        }
        
        @keyframes flashUpdate {
            0% { background-color: rgba(11, 94, 215, 0.05); }
            30% { background-color: rgba(11, 94, 215, 0.25); }
            70% { background-color: rgba(11, 94, 215, 0.15); }
            100% { background-color: transparent; }
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
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
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { 
            color: var(--primary); 
            font-weight: 600; 
        }
        
        /* ================================================================
           ALERT
           ================================================================ */
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .alert-success {
            background: var(--success-bg);
            color: var(--success-dark);
            border: 1px solid var(--success);
        }
        
        .alert-error {
            background: var(--danger-bg);
            color: var(--danger-dark);
            border: 1px solid var(--danger);
        }
        
        .alert i {
            font-size: 1.1rem;
            margin-top: 2px;
        }
        
        .alert .alert-content {
            flex: 1;
        }
        
        /* ================================================================
           GRID HELPERS
           ================================================================ */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        
        .grid-full {
            grid-column: 1 / -1;
        }
        
        @media (max-width: 640px) {
            .grid-2 {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .form-card { padding: 20px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .form-card { padding: 14px; }
            .form-card .form-header .form-title { font-size: 1rem; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .form-card { padding: 12px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
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
        
        .online-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #059669;
            animation: pulse-dot 1.5s infinite;
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
        
        <span class="datetime" id="currentDateTime"></span>
        
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
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">RECEPTION</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                Assign or change doctor for a patient in <strong><?= htmlspecialchars($branch_name) ?></strong>
                
                <span class="header-badge" id="onlineDoctorBadge">
                    <i class="fas fa-user-md"></i>
                    <span class="online-count" id="onlineDoctorCount"><?= $online_doctors_count ?></span> Online
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-user-clock"></i>
                    <span id="pendingCount"><?= $pending_count ?></span> Pending
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-user-check"></i>
                    <span id="assignedCount"><?= $assigned_count ?></span> Assigned
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
        <div class="alert <?= $message_type === 'success' ? 'alert-success' : 'alert-error' ?>" style="max-width:1000px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div class="alert-content"><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ASSIGN FORM -->
    <!-- ================================================================ -->
    <div class="form-card animate-fade-in-up">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-stethoscope"></i>
            </div>
            <div>
                <h3 class="form-title">Assign / Change Doctor</h3>
                <p class="form-subtitle">Select a patient and a doctor to assign or change</p>
            </div>
        </div>
        
        <form method="POST" action="" id="assignForm">
            <!-- ============================================================ -->
            <!-- ROW 1: Patient + Doctor -->
            <!-- ============================================================ -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-user label-icon"></i> Patient <span class="required">*</span>
                    </label>
                    <select name="patient_id" class="form-control" required id="patientSelect">
                        <option value="">-- Select Patient --</option>
                        
                        <?php if (!empty($all_active_patients) && is_array($all_active_patients) && count($all_active_patients) > 0): ?>
                            <?php foreach ($all_active_patients as $patient): 
                                $is_pending = in_array($patient['visit_status'], ['new', 'pending']);
                                $status_label = $is_pending ? '⏳ Pending' : '✅ Assigned';
                                $status_class = $is_pending ? 'pending' : 'assigned';
                                $doctor_info = !empty($patient['assigned_doctor_name']) ? ' - Dr. ' . htmlspecialchars($patient['assigned_doctor_name']) : '';
                            ?>
                                <option value="<?= $patient['id'] ?>" <?= ($_GET['patient_id'] ?? 0) == $patient['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($patient['full_name']) ?> 
                                    (<?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>)
                                    <?php if (!empty($patient['phone'])): ?>
                                        - <?= htmlspecialchars($patient['phone']) ?>
                                    <?php endif; ?>
                                    <?= $doctor_info ?>
                                    <span class="status-badge-dropdown <?= $status_class ?>"><?= $status_label ?></span>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No patients with active visits found</option>
                        <?php endif; ?>
                    </select>
                    
                    <div class="mt-1 text-xs text-gray-400">
                        <?php if ($pending_count > 0): ?>
                            <span class="text-yellow-500">⏳ <?= $pending_count ?> Pending</span>
                            <span class="mx-1">|</span>
                        <?php endif; ?>
                        <?php if ($assigned_count > 0): ?>
                            <span class="text-blue-500">✅ <?= $assigned_count ?> Assigned</span>
                        <?php endif; ?>
                        <?php if ($pending_count == 0 && $assigned_count == 0): ?>
                            <span class="text-green-500">✅ No patients with active visits</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-user-md label-icon"></i> Doctor <span class="required">*</span>
                        <span class="text-xs font-normal text-gray-400" id="doctorUpdateStatus">(Auto-updates every 3s)</span>
                    </label>
                    <select name="doctor_id" class="form-control" required id="doctorSelect">
                        <option value="">-- Select Doctor --</option>
                        <?php if (!empty($doctors) && is_array($doctors) && count($doctors) > 0): ?>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?= $doctor['id'] ?>">
                                    Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                                    <?php if (!empty($doctor['specialty'])): ?>
                                        (<?= htmlspecialchars($doctor['specialty']) ?>)
                                    <?php endif; ?>
                                    <?php if ($doctor['is_online'] == 1): ?>
                                        🟢 Online
                                    <?php else: ?>
                                        ⚪ Offline
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No doctors available</option>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($doctors) || !is_array($doctors) || count($doctors) == 0): ?>
                        <p class="text-xs text-red-500 mt-1">
                            <i class="fas fa-exclamation-circle mr-1"></i> 
                            No doctors available in <?= htmlspecialchars($branch_name) ?>
                        </p>
                    <?php else: ?>
                        <p class="text-xs text-gray-400 mt-1" id="doctorAvailability">
                            <i class="fas fa-info-circle mr-1"></i> 
                            <?= count($doctors) ?> doctor(s) available
                            <span class="text-green-500" id="onlineCountDisplay">(<?= $online_doctors_count ?> online)</span>
                            <span class="text-xs text-gray-400" id="doctorLastUpdate"></span>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ============================================================ -->
            <!-- ROW 2: Visit Type (Updated with dynamic fee mapping) -->
            <!-- ============================================================ -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-tag label-icon"></i> Visit Type <span class="required">*</span>
                        <span class="text-xs font-normal text-gray-400">(Fee valid for 7 days after payment)</span>
                    </label>
                    <select name="visit_type" class="form-control" required id="visitTypeSelect">
                        <option value="new">🆕 New Patient (General Consultation)</option>
                        <option value="follow-up">🔄 Follow-up (Follow-up Consultation)</option>
                        <option value="emergency">🚨 Emergency (Emergency Consultation)</option>
                        <option value="specialist">👨‍⚕️ Specialist (Specialist Consultation)</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-notes-medical label-icon"></i> Common Symptoms
                    </label>
                    <select name="symptoms_select" class="form-control" id="symptomsSelect">
                        <option value="">-- Select Common Symptom --</option>
                        <?php foreach ($common_symptoms as $key => $symptom): ?>
                            <option value="<?= htmlspecialchars($symptom) ?>">
                                <?= htmlspecialchars($symptom) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="other">✏️ Other (Type below)</option>
                    </select>
                </div>
            </div>
            
            <!-- ============================================================ -->
            <!-- ROW 3: Symptoms Details + Notes -->
            <!-- ============================================================ -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-file-medical label-icon"></i> Symptoms Details
                    </label>
                    <textarea name="symptoms" class="form-control" placeholder="Describe patient symptoms in detail..." id="symptomsTextarea" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-sticky-note label-icon"></i> Additional Notes
                    </label>
                    <textarea name="notes" class="form-control" placeholder="Any additional notes..." id="notesInput" rows="3"></textarea>
                </div>
            </div>
            
            <!-- ============================================================ -->
            <!-- FORM ACTIONS -->
            <!-- ============================================================ -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="assignBtn" <?= (empty($all_active_patients) || !is_array($all_active_patients) || count($all_active_patients) == 0) || empty($doctors) || !is_array($doctors) || count($doctors) == 0 ? 'disabled' : '' ?>>
                    <i class="fas fa-user-md"></i> Assign / Change Doctor
                </button>
                <button type="reset" class="btn btn-outline" id="resetBtn">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="dashboard.php" class="btn btn-outline">
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
                <span id="formTimestamp"><?= date('h:i:s A') ?></span>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK STATS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-5" style="max-width:1000px;margin:24px auto 0;">
        <div class="stat-card" id="pendingStats">
            <div class="stat-icon">⏳</div>
            <p class="stat-number primary" id="pendingStat"><?= $pending_count ?></p>
            <p class="stat-label">Pending (Waiting)</p>
        </div>
        <div class="stat-card" id="assignedStats">
            <div class="stat-icon">✅</div>
            <p class="stat-number green" id="assignedStat"><?= $assigned_count ?></p>
            <p class="stat-label">Assigned (Can Change)</p>
        </div>
        <div class="stat-card" id="doctorStats">
            <div class="stat-icon">👨‍⚕️</div>
            <p class="stat-number orange" id="availableDoctorsStat"><?= count($doctors) ?></p>
            <p class="stat-label">Doctors Available</p>
            <p class="text-xs text-gray-400" id="onlineDoctorsStatTime"><?= $online_doctors_count ?> online</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Assign Doctor
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
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- GLOBAL STATS AUTO-UPDATE -->
<!-- ================================================================ -->
<script src="/dispensary_system/frontend/assets/js/global_stats.js"></script>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + timeStr;
        document.getElementById('formTimestamp').textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
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
        
        toast.className = 'toast-custom ' + type;
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
    // AUTO-UPDATE DOCTOR DROPDOWN (3 SECONDS)
    // ================================================================
    var doctorUpdateInterval = null;
    var isDoctorUpdating = false;
    var lastDoctorData = '';

    function updateDoctorDropdown() {
        if (isDoctorUpdating) return;
        isDoctorUpdating = true;
        
        var branchId = <?= json_encode($selected_branch_id) ?>;
        var currentDoctorId = document.getElementById('doctorSelect').value;
        
        // Use get_online_doctors.php API
        fetch('/dispensary_system/frontend/api/get_online_doctors.php?branch_id=' + branchId + '&t=' + new Date().getTime())
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    var allDoctors = data.doctors || [];
                    var onlineCount = data.online_count || 0;
                    var totalDoctors = data.total_doctors || 0;
                    
                    // ================================================================
                    // UPDATE ONLINE COUNT DISPLAY - INSTANT UPDATE
                    // ================================================================
                    var onlineCountElement = document.getElementById('onlineDoctorCount');
                    if (onlineCountElement) {
                        onlineCountElement.textContent = onlineCount;
                    }
                    
                    var onlineStatTime = document.getElementById('onlineDoctorsStatTime');
                    if (onlineStatTime) {
                        onlineStatTime.textContent = onlineCount + ' online';
                    }
                    
                    var availableDoctorsStat = document.getElementById('availableDoctorsStat');
                    if (availableDoctorsStat) {
                        availableDoctorsStat.textContent = totalDoctors;
                    }
                    
                    // ================================================================
                    // UPDATE DOCTOR AVAILABILITY MESSAGE
                    // ================================================================
                    var onlineCountDisplay = document.getElementById('onlineCountDisplay');
                    if (onlineCountDisplay) {
                        onlineCountDisplay.textContent = '(' + onlineCount + ' online)';
                    }
                    
                    // ================================================================
                    // CREATE DATA HASH TO CHECK IF CHANGED
                    // ================================================================
                    var dataHash = JSON.stringify(allDoctors);
                    
                    if (dataHash !== lastDoctorData) {
                        lastDoctorData = dataHash;
                        
                        // ================================================================
                        // REBUILD DROPDOWN
                        // ================================================================
                        var select = document.getElementById('doctorSelect');
                        var currentValue = select.value;
                        
                        // Keep first option
                        var firstOption = select.options[0];
                        select.innerHTML = '';
                        select.appendChild(firstOption);
                        
                        if (allDoctors.length > 0) {
                            allDoctors.forEach(function(doc) {
                                var option = document.createElement('option');
                                option.value = doc.id;
                                var statusText = doc.is_online == 1 ? '🟢 Online' : '⚪ Offline';
                                var specialtyText = doc.specialty ? ' (' + doc.specialty + ')' : '';
                                option.textContent = 'Dr. ' + doc.full_name + specialtyText + ' - ' + statusText;
                                if (String(doc.id) === currentValue) {
                                    option.selected = true;
                                }
                                select.appendChild(option);
                            });
                        } else {
                            var option = document.createElement('option');
                            option.value = '';
                            option.textContent = 'No doctors available';
                            option.disabled = true;
                            select.appendChild(option);
                        }
                        
                        // ================================================================
                        // SHOW UPDATE NOTIFICATION
                        // ================================================================
                        var updateStatus = document.getElementById('doctorUpdateStatus');
                        if (updateStatus) {
                            var now = new Date();
                            updateStatus.textContent = '✅ Updated ' + now.toLocaleTimeString();
                            updateStatus.className = 'text-xs font-normal text-green-500';
                            setTimeout(function() {
                                updateStatus.textContent = '(Auto-updates every 3s)';
                                updateStatus.className = 'text-xs font-normal text-gray-400';
                            }, 3000);
                        }
                        
                        // ================================================================
                        // FLASH EFFECT ON DROPDOWN
                        // ================================================================
                        select.classList.add('doctor-status-updated');
                        setTimeout(function() {
                            select.classList.remove('doctor-status-updated');
                        }, 600);
                    }
                }
                isDoctorUpdating = false;
            })
            .catch(function(error) {
                console.error('Error updating doctor dropdown:', error);
                isDoctorUpdating = false;
            });
    }

    function startDoctorAutoUpdate() {
        if (doctorUpdateInterval) {
            clearInterval(doctorUpdateInterval);
        }
        updateDoctorDropdown();
        doctorUpdateInterval = setInterval(updateDoctorDropdown, 3000); // 3 seconds
    }

    function stopDoctorAutoUpdate() {
        if (doctorUpdateInterval) {
            clearInterval(doctorUpdateInterval);
            doctorUpdateInterval = null;
        }
    }

    // ================================================================
    // VISIBILITY CHANGE - PAUSE WHEN HIDDEN
    // ================================================================
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopDoctorAutoUpdate();
        } else {
            startDoctorAutoUpdate();
        }
    });

    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startDoctorAutoUpdate();
        }, 2000);
    });

    console.log('%c👨‍⚕️ Braick - Assign/Change Doctor (FIXED - Dynamic Fees)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c⏳ Pending Patients: <?= $pending_count ?>', 'font-size:13px; color:#D97706;');
    console.log('%c✅ Assigned Patients: <?= $assigned_count ?>', 'font-size:13px; color:#059669;');
    console.log('%c👨‍⚕️ Doctors Available: <?= count($doctors) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Fee Mapping:', 'font-size:13px; color:#F59E0B;', <?= json_encode($visit_type_mapping) ?>);
    console.log('%c✅ New Patient -> General Consultation', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Follow-up -> Follow-up Consultation', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Emergency -> Emergency Consultation', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Specialist -> Specialist Consultation', 'font-size:13px; color:#34D399;');
    console.log('%c🔄 Doctor dropdown auto-updates every 3 seconds', 'font-size:13px; color:#34D399;');
    console.log('%c📅 7 Days Rule: Fee waived if paid within last 7 days', 'font-size:13px; color:#F59E0B;');
</script>

</body>
</html>