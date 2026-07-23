<?php
// ================================================================
// FILE: frontend/pages/doctor/get_consultations.php
// AJAX ENDPOINT - Returns JSON data for consultations auto-update
// Auto-updates every 3 seconds without page refresh
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DR. JOHN MUSHI (ID: 5) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 5;
    $_SESSION['doctor_id'] = 5;
    $_SESSION['full_name'] = 'Dr. John Mushi';
    $_SESSION['username'] = 'dr.john';
    $_SESSION['email'] = 'john@braick.com';
    $_SESSION['phone'] = '+255 700 000 011';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['specialty'] = 'General Medicine';
    $_SESSION['profile_pic'] = '';
    $_SESSION['is_online'] = 1;
}

$doctor_id = $_SESSION['user_id'] ?? 5;
$doctor_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// GET FILTER PARAMETER
// ================================================================
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Allowed filters
$allowed_filters = ['pending', 'lab_test', 'prescribed', 'completed', 'cancelled'];
if (!in_array($filter, $allowed_filters)) {
    $filter = 'pending';
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// AUTO-COMPLETE LOGIC - Check all prescribed visits
// ================================================================
try {
    // Get all prescribed visits for this doctor (waiting for payment)
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
        // Check bill status for this visit
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_bills,
                SUM(CASE WHEN status IN ('pending', 'partial') THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(total_amount) as total_amount,
                SUM(paid_amount) as total_paid
            FROM patient_bills 
            WHERE visit_id = ?
        ");
        $stmt->execute([$visit['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_bills = (int)($result['total_bills'] ?? 0);
        $pending_count = (int)($result['pending_count'] ?? 0);
        $paid_count = (int)($result['paid_count'] ?? 0);
        $total_amount = (float)($result['total_amount'] ?? 0);
        $total_paid = (float)($result['total_paid'] ?? 0);
        
        // If there are bills AND no pending bills AND at least one paid bill
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
            
            // Update bills to paid
            $stmt = $db->prepare("
                UPDATE patient_bills 
                SET status = 'paid', updated_at = NOW()
                WHERE visit_id = ? AND status IN ('pending', 'partial')
            ");
            $stmt->execute([$visit['id']]);
            
            // Update bill_items to paid
            $stmt = $db->prepare("
                UPDATE bill_items 
                SET payment_status = 'paid', 
                    is_paid = 1, 
                    status = 'paid',
                    paid_at = NOW(),
                    updated_at = NOW()
                WHERE bill_id IN (SELECT id FROM patient_bills WHERE visit_id = ?)
            ");
            $stmt->execute([$visit['id']]);
            
            // Update prescriptions to dispensed
            $stmt = $db->prepare("
                UPDATE prescriptions 
                SET status = 'dispensed', 
                    dispensed_at = NOW(),
                    updated_at = NOW()
                WHERE visit_id = ? AND status = 'pending'
            ");
            $stmt->execute([$visit['id']]);
            
            // Log activity
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, action, details, created_at) 
                    VALUES (?, 'consultation_auto_completed', ?, NOW())
                ");
                $stmt->execute([
                    $doctor_id,
                    "Consultation #" . $visit['visit_number'] . " auto-completed - Bills: $total_bills (TSh " . number_format($total_amount) . " all paid)"
                ]);
            } catch (Exception $e) {}
            
            $db->commit();
        }
    }
} catch (Exception $e) {
    // Silent fail for auto-complete
    error_log("Auto-complete error: " . $e->getMessage());
}

// ================================================================
// GET CONSULTATIONS BASED ON FILTER
// ================================================================
$params = [$doctor_id];
$search_condition = "";
$status_condition = "";

// Build search condition
if (!empty($search)) {
    $search_condition = "AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR v.visit_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Build status condition based on filter
switch ($filter) {
    case 'pending':
        // Active consultations (with_doctor, assigned, pending)
        $status_condition = "AND v.status IN ('pending', 'assigned', 'with_doctor') AND v.is_completed = 0";
        break;
    case 'lab_test':
        // Waiting for lab results
        $status_condition = "AND v.status = 'lab_test' AND v.is_completed = 0";
        break;
    case 'prescribed':
        // Doctor saved, waiting for payment
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

// Get consultations with all related data
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
        (SELECT COUNT(*) FROM patient_bills WHERE visit_id = v.id AND status IN ('pending', 'partial')) as pending_bills_count,
        (SELECT COUNT(*) FROM patient_bills WHERE visit_id = v.id AND status = 'paid') as paid_bills_count,
        (SELECT COUNT(*) FROM patient_bills WHERE visit_id = v.id) as total_bills_count,
        (SELECT COALESCE(SUM(total_amount), 0) FROM patient_bills WHERE visit_id = v.id) as total_bill_amount,
        (SELECT COALESCE(SUM(paid_amount), 0) FROM patient_bills WHERE visit_id = v.id) as total_paid_amount
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    LEFT JOIN branches b ON v.branch_id = b.id
    WHERE v.doctor_id = ? 
    $status_condition
    $search_condition
    ORDER BY v.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_consultations = count($consultations);

// ================================================================
// GET COUNTS FOR BADGES
// ================================================================
$pending_count = 0;
$lab_test_count = 0;
$prescribed_count = 0;
$completed_count = 0;
$cancelled_count = 0;

// Pending (active consultations)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM visits 
    WHERE doctor_id = ? 
    AND status IN ('pending', 'assigned', 'with_doctor') 
    AND is_completed = 0
");
$stmt->execute([$doctor_id]);
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Lab Test (waiting for lab results)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM visits 
    WHERE doctor_id = ? 
    AND status = 'lab_test' 
    AND is_completed = 0
");
$stmt->execute([$doctor_id]);
$lab_test_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Prescribed (waiting for payment)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM visits 
    WHERE doctor_id = ? 
    AND status = 'prescribed' 
    AND is_completed = 0
");
$stmt->execute([$doctor_id]);
$prescribed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Completed
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM visits 
    WHERE doctor_id = ? 
    AND status = 'completed' 
    AND is_completed = 1
");
$stmt->execute([$doctor_id]);
$completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Cancelled
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM visits 
    WHERE doctor_id = ? 
    AND status = 'cancelled'
");
$stmt->execute([$doctor_id]);
$cancelled_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// GENERATE HTML FOR CONSULTATIONS
// ================================================================
function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
    $index = abs(crc32($name)) % count($colors);
    return $colors[$index];
}

function formatCurrency($amount) {
    return 'TSh ' . number_format($amount, 0);
}

$html_output = '';

if (count($consultations) > 0) {
    foreach ($consultations as $consultation) {
        $initial = strtoupper(substr($consultation['patient_name'] ?? 'U', 0, 1));
        $color = getUserColor($consultation['patient_name'] ?? 'U');
        $status = $consultation['status'] ?? 'pending';
        
        $html_output .= '
            <div class="consultation-card animate-fade-in-up" data-visit-id="' . $consultation['id'] . '" data-status="' . $status . '">
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
                            </div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <span class="visit-number">' . htmlspecialchars($consultation['visit_number'] ?? 'N/A') . '</span>
                        <span class="status-badge ' . $status . '">
                            ' . ucfirst(str_replace('_', ' ', $status)) . '
                        </span>
                    </div>
                </div>';
        
        // Lab, Prescription & Bill Indicators
        $html_output .= '
                <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:8px;">';
        
        if (($consultation['pending_lab_count'] ?? 0) > 0) {
            $html_output .= '
                    <span class="lab-indicator">
                        <i class="fas fa-flask pending"></i>
                        ' . $consultation['pending_lab_count'] . ' lab(s) pending
                    </span>';
        }
        if (($consultation['completed_lab_count'] ?? 0) > 0) {
            $html_output .= '
                    <span class="lab-indicator">
                        <i class="fas fa-check-circle completed"></i>
                        ' . $consultation['completed_lab_count'] . ' lab(s) completed
                    </span>';
        }
        if (($consultation['pending_prescriptions'] ?? 0) > 0) {
            $html_output .= '
                    <span class="lab-indicator">
                        <i class="fas fa-prescription pending"></i>
                        ' . $consultation['pending_prescriptions'] . ' prescription(s) pending
                    </span>';
        }
        if (($consultation['dispensed_prescriptions'] ?? 0) > 0) {
            $html_output .= '
                    <span class="lab-indicator">
                        <i class="fas fa-check-circle completed"></i>
                        ' . $consultation['dispensed_prescriptions'] . ' prescription(s) dispensed
                    </span>';
        }
        if (($consultation['pending_bills_count'] ?? 0) > 0) {
            $html_output .= '
                    <span class="bill-indicator">
                        <i class="fas fa-receipt pending"></i>
                        ' . $consultation['pending_bills_count'] . ' bill(s) pending
                        <span class="bill-amount">
                            (TSh ' . number_format($consultation['total_bill_amount'] ?? 0) . ')
                        </span>
                    </span>';
        }
        if (($consultation['paid_bills_count'] ?? 0) > 0) {
            $html_output .= '
                    <span class="bill-indicator">
                        <i class="fas fa-check-circle paid"></i>
                        ' . $consultation['paid_bills_count'] . ' bill(s) paid
                        <span class="bill-amount">
                            (TSh ' . number_format($consultation['total_paid_amount'] ?? 0) . ')
                        </span>
                    </span>';
        }
        
        $html_output .= '
                </div>';
        
        // Footer
        $html_output .= '
                <div class="card-footer">
                    <div class="meta">
                        <i class="far fa-calendar-alt"></i> ' . date('M d, Y', strtotime($consultation['created_at'])) . '
                        <span class="mx-1">•</span>
                        <i class="far fa-clock"></i> ' . date('h:i A', strtotime($consultation['created_at'])) . '
                        ' . (!empty($consultation['doctor_name']) ? '
                        <span class="mx-1">•</span>
                        <i class="fas fa-user-md"></i> Dr. ' . htmlspecialchars($consultation['doctor_name']) : '') . '
                        ' . (($consultation['total_bills_count'] ?? 0) > 0 ? '
                        <span class="mx-1">•</span>
                        <i class="fas fa-receipt"></i> Bills: ' . ($consultation['paid_bills_count'] ?? 0) . '/' . ($consultation['total_bills_count'] ?? 0) : '') . '
                    </div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">';
        
        $filter = $GLOBALS['filter'];
        if ($filter === 'pending' || $filter === 'lab_test' || $filter === 'prescribed') {
            $html_output .= '
                        <a href="consultation.php?visit_id=' . $consultation['id'] . '" class="btn btn-primary btn-sm">
                            <i class="fas fa-stethoscope"></i> Continue
                        </a>';
        }
        if ($filter === 'completed' || $filter === 'cancelled') {
            $html_output .= '
                        <a href="consultation.php?visit_id=' . $consultation['id'] . '&view=1" class="btn btn-outline btn-sm">
                            <i class="fas fa-eye"></i> View
                        </a>';
        }
        if ($filter === 'prescribed' && ($consultation['pending_bills_count'] ?? 0) > 0) {
            $html_output .= '
                        <span class="text-xs text-gray-400 self-center">
                            <i class="fas fa-clock"></i> Waiting for payment...
                        </span>';
        }
        if ($filter === 'prescribed' && ($consultation['pending_bills_count'] ?? 0) == 0 && ($consultation['total_bills_count'] ?? 0) > 0) {
            $html_output .= '
                        <span class="text-xs text-green-600 self-center animate-fade-in-up">
                            <i class="fas fa-check-circle"></i> Auto-completing...
                        </span>';
        }
        
        $html_output .= '
                    </div>
                </div>
            </div>';
    }
} else {
    $empty_icon = 'clock';
    if ($filter === 'lab_test') $empty_icon = 'flask';
    elseif ($filter === 'prescribed') $empty_icon = 'hourglass-half';
    elseif ($filter === 'completed') $empty_icon = 'check-circle';
    elseif ($filter === 'cancelled') $empty_icon = 'times-circle';
    
    $empty_message = 'No ' . $filter . ' consultations';
    if ($filter === 'pending') $empty_message = 'All consultations have been processed or no pending consultations';
    elseif ($filter === 'lab_test') $empty_message = 'No consultations waiting for lab results';
    elseif ($filter === 'prescribed') $empty_message = 'All consultations have been completed or no prescribed consultations waiting for payment';
    elseif ($filter === 'completed') $empty_message = 'No completed consultations yet';
    else $empty_message = 'No cancelled consultations';
    
    $html_output .= '
        <div class="empty-state" style="max-width:1200px;margin:0 auto;">
            <i class="fas fa-' . $empty_icon . '"></i>
            <div class="empty-title">' . $empty_message . '</div>
            <div class="empty-sub">
                ' . $empty_message . '
                ' . (!empty($search) ? '<br>Try adjusting your search criteria' : '') . '
            </div>
        </div>';
}

// ================================================================
// CREATE DATA HASH FOR CHANGE DETECTION
// ================================================================
$data_array = [
    'total' => $total_consultations,
    'pending' => $pending_count,
    'lab_test' => $lab_test_count,
    'prescribed' => $prescribed_count,
    'completed' => $completed_count,
    'cancelled' => $cancelled_count,
    'filter' => $filter,
    'search' => $search
];
$data_hash = md5(json_encode($data_array));

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

echo json_encode([
    'success' => true,
    'hash' => $data_hash,
    'total' => $total_consultations,
    'counts' => [
        'pending' => $pending_count,
        'lab_test' => $lab_test_count,
        'prescribed' => $prescribed_count,
        'completed' => $completed_count,
        'cancelled' => $cancelled_count
    ],
    'timestamp' => date('Y-m-d H:i:s'),
    'html' => $html_output
]);
?>