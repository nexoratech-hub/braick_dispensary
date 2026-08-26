<?php
// ================================================================
// FILE: frontend/pages/doctor/get_consultations.php
// AJAX ENDPOINT - Get consultations data for auto-update
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT DOCTOR
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    // Return JSON error for AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized - Please login first',
            'redirect' => '/dispensary_system/frontend/pages/login.php'
        ]);
        exit;
    }
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// GET DOCTOR DATA FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. Unknown';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// GET FILTER PARAMETER
// ================================================================
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$allowed_filters = ['pending', 'lab_test', 'prescribed', 'completed', 'cancelled'];
if (!in_array($filter, $allowed_filters)) {
    $filter = 'pending';
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// ================================================================
// VERIFY DOCTOR EXISTS AND IS ACTIVE
// ================================================================
try {
    $stmt = $db->prepare("SELECT id, full_name, branch_id, status FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor_data || $doctor_data['status'] !== 'active') {
        session_destroy();
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Doctor account inactive',
                'redirect' => '/dispensary_system/frontend/pages/login.php'
            ]);
            exit;
        }
        header('Location: /dispensary_system/frontend/pages/login.php');
        exit;
    }
    
    $doctor_name = $doctor_data['full_name'];
    $_SESSION['full_name'] = $doctor_name;
    
} catch (Exception $e) {
    error_log("get_consultations doctor verification error: " . $e->getMessage());
}

// ================================================================
// ✅ AUTO-COMPLETE LOGIC - RUN BEFORE FETCHING DATA
// ================================================================
try {
    // 1. Check lab_test visits that have all tests completed
    $stmt = $db->prepare("
        SELECT v.id, v.visit_number, v.patient_id
        FROM visits v
        WHERE v.doctor_id = ? 
        AND v.status = 'lab_test'
        AND v.is_completed = 0
    ");
    $stmt->execute([$doctor_id]);
    $lab_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($lab_visits as $visit) {
        // Check if all lab tests for this visit are completed
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_tests,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tests
            FROM lab_tests 
            WHERE visit_id = ?
        ");
        $stmt->execute([$visit['id']]);
        $lab_status = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_tests = (int)($lab_status['total_tests'] ?? 0);
        $completed_tests = (int)($lab_status['completed_tests'] ?? 0);
        
        // If all tests are completed, move to prescribed status
        if ($total_tests > 0 && $completed_tests == $total_tests) {
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'prescribed', 
                    updated_at = NOW()
                WHERE id = ? AND status = 'lab_test'
            ");
            $stmt->execute([$visit['id']]);
            
            $db->commit();
            
            error_log("Visit #{$visit['visit_number']} auto-moved from lab_test to prescribed");
        }
    }
} catch (Exception $e) {
    error_log("Auto-complete lab error: " . $e->getMessage());
}

// 2. Check prescribed visits that have all bills paid
try {
    $stmt = $db->prepare("
        SELECT v.id, v.visit_number, v.patient_id
        FROM visits v
        WHERE v.doctor_id = ? 
        AND v.status = 'prescribed'
        AND v.is_completed = 0
    ");
    $stmt->execute([$doctor_id]);
    $prescribed_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($prescribed_visits as $visit) {
        // FIX: Changed 'patient_bills' to 'bills'
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_bills,
                SUM(CASE WHEN status IN ('pending', 'partial') THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(total_amount) as total_amount,
                SUM(paid_amount) as total_paid
            FROM bills 
            WHERE visit_id = ?
        ");
        $stmt->execute([$visit['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_bills = (int)($result['total_bills'] ?? 0);
        $pending_count = (int)($result['pending_count'] ?? 0);
        $paid_count = (int)($result['paid_count'] ?? 0);
        $total_amount = (float)($result['total_amount'] ?? 0);
        $total_paid = (float)($result['total_paid'] ?? 0);
        
        if ($total_bills > 0 && $pending_count == 0 && $paid_count > 0) {
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'completed', 
                    is_completed = 1, 
                    completed_at = NOW(), 
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$visit['id']]);
            
            // FIX: Changed 'patient_bills' to 'bills'
            $stmt = $db->prepare("
                UPDATE bills 
                SET status = 'paid', updated_at = NOW()
                WHERE visit_id = ? AND status IN ('pending', 'partial')
            ");
            $stmt->execute([$visit['id']]);
            
            $db->commit();
            error_log("Visit #{$visit['visit_number']} auto-completed (all bills paid)");
        }
    }
} catch (Exception $e) {
    error_log("Auto-complete prescribed error: " . $e->getMessage());
}

// ================================================================
// GET COUNTS FOR BADGES
// ================================================================
$counts = [
    'pending' => 0,
    'lab_test' => 0,
    'prescribed' => 0,
    'completed' => 0,
    'cancelled' => 0
];

$status_map = [
    'pending' => "status IN ('pending', 'assigned', 'with_doctor') AND is_completed = 0",
    'lab_test' => "status = 'lab_test' AND is_completed = 0",
    'prescribed' => "status = 'prescribed' AND is_completed = 0",
    'completed' => "status = 'completed' AND is_completed = 1",
    'cancelled' => "status = 'cancelled'"
];

foreach ($counts as $key => $value) {
    try {
        $condition = $status_map[$key] ?? "status = '$key'";
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND $condition");
        $stmt->execute([$doctor_id]);
        $counts[$key] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {
        $counts[$key] = 0;
    }
}

// ================================================================
// BUILD SEARCH AND STATUS CONDITIONS
// ================================================================
$params = [$doctor_id];
$search_condition = "";
$status_condition = "";

if (!empty($search)) {
    $search_condition = "AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR v.visit_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

switch ($filter) {
    case 'pending':
        $status_condition = "AND v.status IN ('pending', 'assigned', 'with_doctor') AND v.is_completed = 0";
        break;
    case 'lab_test':
        $status_condition = "AND v.status = 'lab_test' AND v.is_completed = 0";
        break;
    case 'prescribed':
        $status_condition = "AND v.status = 'prescribed' AND v.is_completed = 0";
        break;
    case 'completed':
        $status_condition = "AND v.status = 'completed' AND v.is_completed = 1";
        break;
    case 'cancelled':
        $status_condition = "AND v.status = 'cancelled'";
        break;
    default:
        $status_condition = "AND v.status IN ('pending', 'assigned', 'with_doctor') AND v.is_completed = 0";
        break;
}

// ================================================================
// GET CONSULTATIONS - FIXED: changed patient_bills to bills
// ================================================================
$sql = "
    SELECT 
        v.*,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone,
        p.gender,
        p.date_of_birth,
        p.address,
        p.blood_group,
        p.allergies,
        u.full_name as doctor_name,
        b.name as branch_name,
        (SELECT COUNT(*) FROM lab_tests WHERE visit_id = v.id AND status IN ('pending', 'in_progress')) as pending_lab_count,
        (SELECT COUNT(*) FROM lab_tests WHERE visit_id = v.id AND status = 'completed') as completed_lab_count,
        (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id AND status IN ('pending', 'dispensed')) as total_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id AND status = 'pending') as pending_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id AND status = 'dispensed') as dispensed_prescriptions,
        -- FIX: Changed 'patient_bills' to 'bills'
        (SELECT COUNT(*) FROM bills WHERE visit_id = v.id AND status IN ('pending', 'partial')) as pending_bills_count,
        (SELECT COUNT(*) FROM bills WHERE visit_id = v.id AND status = 'paid') as paid_bills_count,
        (SELECT COUNT(*) FROM bills WHERE visit_id = v.id) as total_bills_count,
        (SELECT COALESCE(SUM(total_amount), 0) FROM bills WHERE visit_id = v.id) as total_bill_amount,
        (SELECT COALESCE(SUM(paid_amount), 0) FROM bills WHERE visit_id = v.id) as total_paid_amount
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    LEFT JOIN branches b ON v.branch_id = b.id
    WHERE v.doctor_id = ? 
    $status_condition
    $search_condition
    ORDER BY 
        CASE 
            WHEN v.status IN ('pending', 'assigned', 'with_doctor') THEN 1
            WHEN v.status = 'lab_test' THEN 2
            WHEN v.status = 'prescribed' THEN 3
            WHEN v.status = 'completed' THEN 4
            ELSE 5
        END,
        v.created_at DESC
";

$consultations = [];
try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("get_consultations query error: " . $e->getMessage());
    $consultations = [];
}

// ================================================================
// BUILD HTML
// ================================================================
$html = '';
$total = count($consultations);

if (count($consultations) > 0) {
    foreach ($consultations as $consultation) {
        $initial = strtoupper(substr($consultation['patient_name'] ?? 'U', 0, 1));
        $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
        $color = $colors[abs(crc32($consultation['patient_name'] ?? 'U')) % count($colors)];
        
        $status_label = ucfirst(str_replace('_', ' ', $consultation['status'] ?? 'Pending'));
        $status_class = $consultation['status'] ?? 'pending';
        
        $pending_lab = (int)($consultation['pending_lab_count'] ?? 0);
        $completed_lab = (int)($consultation['completed_lab_count'] ?? 0);
        $pending_rx = (int)($consultation['pending_prescriptions'] ?? 0);
        $dispensed_rx = (int)($consultation['dispensed_prescriptions'] ?? 0);
        $pending_bills = (int)($consultation['pending_bills_count'] ?? 0);
        $paid_bills = (int)($consultation['paid_bills_count'] ?? 0);
        $total_bills = (int)($consultation['total_bills_count'] ?? 0);
        $total_amount = (float)($consultation['total_bill_amount'] ?? 0);
        $total_paid = (float)($consultation['total_paid_amount'] ?? 0);
        
        $can_complete = ($pending_bills == 0 && $paid_bills > 0 && $consultation['status'] === 'prescribed');
        
        $html .= '
        <div class="consultation-card animate-fade-in-up" data-visit-id="' . $consultation['id'] . '" data-status="' . $consultation['status'] . '">
            <div class="card-header">
                <div class="patient-info">
                    <div class="patient-avatar" style="background:' . $color . ';">
                        ' . $initial . '
                    </div>
                    <div>
                        <div class="patient-name">' . htmlspecialchars($consultation['patient_name'] ?? 'N/A') . '</div>
                        <div class="patient-id">ID: ' . htmlspecialchars($consultation['patient_code'] ?? 'N/A') . '</div>
                        <div class="patient-details">
                            ' . htmlspecialchars($consultation['gender'] ?? 'N/A') . ' • 
                            ' . htmlspecialchars($consultation['phone'] ?? 'N/A') . '
                            ' . (!empty($consultation['blood_group']) ? '• Blood: ' . htmlspecialchars($consultation['blood_group']) : '') . '
                            ' . (!empty($consultation['allergies']) ? '• Allergies: ' . htmlspecialchars($consultation['allergies']) : '') . '
                        </div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span class="visit-number">' . htmlspecialchars($consultation['visit_number'] ?? 'N/A') . '</span>
                    <span class="status-badge ' . $status_class . '">
                        ' . $status_label . '
                    </span>
                    ' . ($can_complete ? '<span class="status-badge completed" style="background:#D1FAE5;color:#059669;"><i class="fas fa-check"></i> Auto-complete</span>' : '') . '
                </div>
            </div>
            
            <!-- Indicators -->
            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:8px;">';
        
        if ($pending_lab > 0) {
            $html .= '<span class="lab-indicator"><i class="fas fa-flask pending"></i> ' . $pending_lab . ' lab(s) pending</span>';
        }
        if ($completed_lab > 0) {
            $html .= '<span class="lab-indicator"><i class="fas fa-check-circle completed"></i> ' . $completed_lab . ' lab(s) completed</span>';
        }
        if ($pending_rx > 0) {
            $html .= '<span class="lab-indicator"><i class="fas fa-prescription pending"></i> ' . $pending_rx . ' prescription(s) pending</span>';
        }
        if ($dispensed_rx > 0) {
            $html .= '<span class="lab-indicator"><i class="fas fa-check-circle completed"></i> ' . $dispensed_rx . ' prescription(s) dispensed</span>';
        }
        if ($pending_bills > 0) {
            $html .= '<span class="bill-indicator"><i class="fas fa-receipt pending"></i> ' . $pending_bills . ' bill(s) pending <span class="bill-amount">(TSh ' . number_format($total_amount) . ')</span></span>';
        }
        if ($paid_bills > 0) {
            $html .= '<span class="bill-indicator"><i class="fas fa-check-circle paid"></i> ' . $paid_bills . ' bill(s) paid <span class="bill-amount">(TSh ' . number_format($total_paid) . ')</span></span>';
        }
        
        $html .= '
            </div>
            
            <!-- Footer -->
            <div class="card-footer">
                <div class="meta">
                    <i class="far fa-calendar-alt"></i> ' . date('M d, Y', strtotime($consultation['created_at'])) . '
                    <span class="mx-1">•</span>
                    <i class="far fa-clock"></i> ' . date('h:i A', strtotime($consultation['created_at'])) . '
                    ' . (!empty($consultation['doctor_name']) ? '<span class="mx-1">•</span><i class="fas fa-user-md"></i> Dr. ' . htmlspecialchars($consultation['doctor_name']) : '') . '
                    ' . ($total_bills > 0 ? '<span class="mx-1">•</span><i class="fas fa-receipt"></i> Bills: ' . $paid_bills . '/' . $total_bills : '') . '
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">';
        
        if (in_array($filter, ['pending', 'lab_test', 'prescribed'])) {
            $html .= '<a href="consultation.php?visit_id=' . $consultation['id'] . '" class="btn btn-primary btn-sm"><i class="fas fa-stethoscope"></i> Continue</a>';
        }
        if (in_array($filter, ['completed', 'cancelled'])) {
            $html .= '<a href="consultation.php?visit_id=' . $consultation['id'] . '&view=1" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> View</a>';
        }
        if ($filter === 'prescribed' && $pending_bills > 0) {
            $html .= '<span class="text-xs text-gray-400 self-center"><i class="fas fa-clock"></i> Waiting for payment...</span>';
        }
        if ($filter === 'prescribed' && $pending_bills == 0 && $total_bills > 0) {
            $html .= '<span class="text-xs text-green-600 self-center animate-fade-in-up"><i class="fas fa-check-circle"></i> Auto-completing...</span>';
        }
        
        $html .= '
                </div>
            </div>
        </div>';
    }
} else {
    $icon_map = [
        'pending' => 'clock',
        'lab_test' => 'flask',
        'prescribed' => 'hourglass-half',
        'completed' => 'check-circle',
        'cancelled' => 'times-circle'
    ];
    $icon = $icon_map[$filter] ?? 'clock';
    $messages = [
        'pending' => 'All consultations have been processed or no pending consultations',
        'lab_test' => 'No consultations waiting for lab results',
        'prescribed' => 'All consultations have been completed or no prescribed consultations waiting for payment',
        'completed' => 'No completed consultations yet',
        'cancelled' => 'No cancelled consultations'
    ];
    $msg = $messages[$filter] ?? 'No consultations found';
    if (!empty($search)) {
        $msg .= '<br>Try adjusting your search criteria';
    }
    
    $html .= '
    <div class="empty-state" style="max-width:1200px;margin:0 auto;">
        <i class="fas fa-' . $icon . '"></i>
        <div class="empty-title">No ' . $filter . ' consultations</div>
        <div class="empty-sub">' . $msg . '</div>
    </div>';
}

// ================================================================
// GENERATE HASH FOR CHANGE DETECTION
// ================================================================
$hash_data = $total . $filter . $search;
foreach ($consultations as $c) {
    $hash_data .= $c['id'] . $c['status'] . $c['pending_lab_count'] . $c['completed_lab_count'] . $c['pending_bills_count'] . $c['paid_bills_count'];
}
$hash = md5($hash_data);

$timestamp = date('H:i:s');

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $consultations,
    'html' => $html,
    'total' => $total,
    'counts' => $counts,
    'hash' => $hash,
    'timestamp' => $timestamp,
    'filter' => $filter,
    'search' => $search,
    'doctor_id' => $doctor_id,
    'doctor_name' => $doctor_name
]);
exit;