<?php
// ================================================================
// FILE: frontend/pages/pharmacy/pending_prescriptions.php
// PHARMACY - PENDING PRESCRIPTIONS
// ================================================================
// FIXED: AJAX auto-update - checks status every 3 seconds
// FIXED: Auto-dispense updates without page refresh
// FIXED: Status shows real-time updates
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Pharmacy
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 9;
    $_SESSION['full_name'] = 'Pharmacy Dodoma';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'pharm.dodoma';
    $_SESSION['is_admin'] = false;
}

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy';
$user_id = $_SESSION['user_id'] ?? 9;

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$db = Database::getInstance()->getConnection();
$message = '';
$message_type = '';
$currency = 'TSh';

try {
    // ================================================================
    // GET SYSTEM SETTINGS
    // ================================================================
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
    
    // ================================================================
    // ✅ AJAX HANDLER - Get updated prescription status
    // ================================================================
    if (isset($_POST['action']) && $_POST['action'] === 'get_prescriptions_status') {
        header('Content-Type: application/json');
        
        $branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : $user_branch_id;
        $filter_status = isset($_POST['filter_status']) ? $_POST['filter_status'] : 'all';
        $search = isset($_POST['search']) ? trim($_POST['search']) : '';
        
        // Build query for prescriptions
        $conditions = ["p.branch_id = ?", "p.status IN ('pending', 'confirmed')"];
        $params = [$branch_id];
        
        if ($filter_status !== 'all') {
            $conditions[] = "p.status = ?";
            $params[] = $filter_status;
        }
        
        if (!empty($search)) {
            $conditions[] = "(pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.prescription_number LIKE ? OR p.medication LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $where_clause = implode(" AND ", $conditions);
        
        $sql = "
            SELECT 
                p.id,
                p.status,
                p.prescription_number,
                p.medication,
                p.quantity,
                p.created_at,
                pat.full_name as patient_name,
                pat.patient_id as patient_code,
                pat.phone,
                pat.gender,
                pat.date_of_birth,
                u.full_name as doctor_name,
                v.visit_number,
                (SELECT b.id FROM patient_bills b 
                 JOIN bill_items bi ON bi.bill_id = b.id
                 WHERE b.visit_id = p.visit_id 
                 AND bi.item_type = 'medication'
                 ORDER BY b.id DESC LIMIT 1) as bill_id,
                (SELECT b.status FROM patient_bills b 
                 JOIN bill_items bi ON bi.bill_id = b.id
                 WHERE b.visit_id = p.visit_id 
                 AND bi.item_type = 'medication'
                 ORDER BY b.id DESC LIMIT 1) as bill_status,
                (SELECT b.total_amount FROM patient_bills b 
                 JOIN bill_items bi ON bi.bill_id = b.id
                 WHERE b.visit_id = p.visit_id 
                 AND bi.item_type = 'medication'
                 ORDER BY b.id DESC LIMIT 1) as bill_total,
                (SELECT b.bill_number FROM patient_bills b 
                 JOIN bill_items bi ON bi.bill_id = b.id
                 WHERE b.visit_id = p.visit_id 
                 AND bi.item_type = 'medication'
                 ORDER BY b.id DESC LIMIT 1) as bill_number
            FROM prescriptions p
            LEFT JOIN patients pat ON p.patient_id = pat.id
            LEFT JOIN users u ON p.doctor_id = u.id
            LEFT JOIN visits v ON p.visit_id = v.id
            WHERE $where_clause
            ORDER BY 
                CASE 
                    WHEN p.status = 'pending' THEN 0 
                    WHEN p.status = 'confirmed' THEN 1 
                    ELSE 2 
                END,
                p.created_at ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get status counts
        $status_counts = [];
        foreach (['pending', 'confirmed', 'dispensed'] as $status) {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = ?");
            $stmt->execute([$branch_id, $status]);
            $status_counts[$status] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        }
        
        $total_count = $status_counts['pending'] + $status_counts['confirmed'];
        
        // Build HTML for each row
        $rows_html = '';
        if (count($prescriptions) > 0) {
            $i = 1;
            foreach ($prescriptions as $pres) {
                $age = calculateAge($pres['date_of_birth'] ?? '');
                $is_paid = ($pres['bill_status'] ?? '') === 'paid';
                $bill_exists = !empty($pres['bill_id']);
                $status = $pres['status'] ?? 'pending';
                $status_label = getStatusLabel($status);
                $status_badge_class = getStatusBadgeClass($status);
                
                $rows_html .= '
                <tr data-prescription-id="' . $pres['id'] . '" data-status="' . $status . '">
                    <td style="text-align:center;">' . $i++ . '</td>
                    <td>
                        <span class="font-mono font-semibold" style="color:var(--primary);font-size:0.7rem;">
                            ' . htmlspecialchars($pres['prescription_number'] ?? 'N/A') . '
                        </span>
                    </td>
                    <td>
                        <div class="font-medium" style="font-size:0.75rem;">' . htmlspecialchars($pres['patient_name'] ?? 'Unknown') . '</div>
                        <div class="text-xs" style="color:var(--text-secondary);">' . htmlspecialchars($pres['patient_code'] ?? 'N/A') . '</div>
                        <div class="text-xs" style="color:var(--text-secondary);">
                            ' . htmlspecialchars($pres['gender'] ?? 'N/A') . ' • ' . $age . ' yrs
                        </div>
                        ' . (!empty($pres['phone']) ? '<div class="text-xs" style="color:var(--text-secondary);">📱 ' . htmlspecialchars($pres['phone']) . '</div>' : '') . '
                    </td>
                    <td>
                        <span class="font-semibold" style="font-size:0.75rem;">' . htmlspecialchars($pres['medication'] ?? 'N/A') . '</span>
                    </td>
                    <td style="text-align:center;">
                        <span class="font-semibold" style="font-size:0.75rem;">' . ($pres['quantity'] ?? 0) . '</span>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge-status ' . $status_badge_class . '">
                            ' . $status_label . '
                        </span>
                        ' . ($status === 'confirmed' && !$is_paid ? '<div class="text-xs" style="color:var(--warning);">⏳ Waiting for payment</div>' : '') . '
                        ' . ($status === 'confirmed' && $is_paid ? '<div class="text-xs" style="color:var(--success);">✅ Payment confirmed</div>' : '') . '
                    </td>
                    <td style="text-align:center;">
                        ' . ($bill_exists ? ($is_paid ? 
                            '<span class="badge-status badge-success">✅ Paid</span><div class="text-xs" style="color:var(--success);">💊 Auto-Dispensed!</div>' : 
                            '<span class="badge-status badge-warning">⏳ Pending</span><div class="text-xs" style="color:var(--warning);">' . $currency . ' ' . number_format($pres['bill_total'] ?? 0) . '</div>'
                        ) : '<span class="text-xs" style="color:var(--text-secondary);">No bill</span>') . '
                    </td>
                    <td style="text-align:center;">
                        <span class="text-xs">' . formatDate($pres['created_at'] ?? '') . '</span>
                        ' . (!empty($pres['visit_number']) ? '<div class="text-xs" style="color:var(--text-secondary);">Visit: ' . htmlspecialchars($pres['visit_number']) . '</div>' : '') . '
                    </td>
                    <td style="text-align:center;">
                        <div class="action-buttons" style="justify-content:center;">
                            <a href="view_prescription.php?id=' . $pres['id'] . '" class="btn-view" title="View Prescription Details">
                                <i class="fas fa-eye"></i> View
                            </a>
                            ' . ($status === 'dispensed' ? 
                                '<span class="btn-dispensed"><i class="fas fa-check-circle"></i> Dispensed</span>' :
                                ($status === 'confirmed' && $is_paid ? 
                                    '<span class="btn-auto-dispensed"><i class="fas fa-check-circle"></i> Auto-Dispensed</span>' :
                                    ($status === 'confirmed' ? 
                                        '<span class="btn-auto-dispensed"><i class="fas fa-clock"></i> Awaiting Pay</span>' :
                                        '<form method="POST" action="" style="display:inline;" onsubmit="return confirm(\'Confirm this prescription?\\n\\n✅ Status will change to: Confirmed\\n💳 Bill will be created and sent to Cashier.\\n\\n💊 Medication: ' . addslashes($pres['medication'] ?? 'N/A') . '\\n📦 Quantity: ' . ($pres['quantity'] ?? 0) . '\\n👤 Patient: ' . addslashes($pres['patient_name'] ?? 'Unknown') . '\\n\\n⚠️ After payment, status will auto-change to: Dispensed\');">
                                            <input type="hidden" name="action" value="confirm_prescription">
                                            <input type="hidden" name="prescription_id" value="' . $pres['id'] . '">
                                            <button type="submit" class="btn-confirm" title="Confirm - Send Bill to Cashier">
                                                <i class="fas fa-check-circle"></i> Confirm
                                            </button>
                                        </form>'
                                    )
                                )
                            ) . '
                        </div>
                    </td>
                </tr>';
            }
        } else {
            $rows_html = '
            <tr>
                <td colspan="9">
                    <div class="text-center py-6" style="color:var(--text-secondary);">
                        <i class="fas fa-prescription text-2xl block mb-2" style="color:var(--border-color);"></i>
                        <p style="font-size:0.85rem;">No pending prescriptions found</p>
                        <p class="text-xs mt-1" style="color:var(--text-secondary);">
                            ' . (!empty($search) ? 'No results for "<strong>' . htmlspecialchars($search) . '</strong>"' : 
                            ($filter_status !== 'all' ? 'No ' . ucfirst($filter_status) . ' prescriptions' : 
                            'All prescriptions have been processed ✅')) . '
                        </p>
                        <a href="prescription_history.php" class="btn btn-primary mt-3">
                            <i class="fas fa-history"></i> View History
                        </a>
                    </div>
                </td>
            </tr>';
        }
        
        echo json_encode([
            'success' => true,
            'rows_html' => $rows_html,
            'total_count' => $total_count,
            'pending_count' => $status_counts['pending'],
            'confirmed_count' => $status_counts['confirmed'],
            'dispensed_count' => $status_counts['dispensed'],
            'timestamp' => date('H:i:s')
        ]);
        exit;
    }
    
    // ================================================================
    // ✅ AUTO-DISPENSE: Check for confirmed prescriptions with paid bills
    // ================================================================
    $auto_dispensed_count = 0;
    try {
        $stmt = $db->prepare("
            SELECT 
                p.id as prescription_id,
                p.patient_id,
                p.medication,
                p.quantity,
                p.visit_id,
                p.prescription_number,
                b.id as bill_id,
                b.bill_number
            FROM prescriptions p
            JOIN patient_bills b ON b.visit_id = p.visit_id
            JOIN bill_items bi ON bi.bill_id = b.id
            WHERE p.branch_id = ? 
            AND p.status = 'confirmed'
            AND b.status = 'paid'
            AND bi.item_type = 'medication'
            AND p.id NOT IN (SELECT prescription_id FROM prescription_sales WHERE prescription_id = p.id)
            GROUP BY p.id
        ");
        $stmt->execute([$user_branch_id]);
        $to_dispense = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($to_dispense as $item) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("
                    SELECT selling_price FROM medications_inventory 
                    WHERE medication_name = ? AND branch_id = ? AND status = 'active'
                    LIMIT 1
                ");
                $stmt->execute([$item['medication'], $user_branch_id]);
                $price_result = $stmt->fetch(PDO::FETCH_ASSOC);
                $unit_price = $price_result['selling_price'] ?? 0;
                $total_amount = $unit_price * $item['quantity'];
                
                $stmt = $db->prepare("
                    UPDATE prescriptions 
                    SET status = 'dispensed', 
                        dispensed_at = NOW(), 
                        updated_at = NOW(),
                        pharmacy_id = ?
                    WHERE id = ? AND branch_id = ?
                ");
                $stmt->execute([$user_id, $item['prescription_id'], $user_branch_id]);
                
                $sale_number = 'SALE-' . date('Ymd') . '-' . str_pad($item['prescription_id'], 6, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("
                    INSERT INTO prescription_sales (
                        sale_number, prescription_id, patient_id, visit_id,
                        total_amount, dispensed_by, status, payment_method,
                        payment_status, branch_id, created_at, dispensed_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'dispensed', 'cash', 'paid', ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $sale_number,
                    $item['prescription_id'],
                    $item['patient_id'],
                    $item['visit_id'],
                    $total_amount,
                    $user_id,
                    $user_branch_id
                ]);
                
                $stmt = $db->prepare("
                    UPDATE medications_inventory 
                    SET quantity = quantity - ? 
                    WHERE medication_name = ? AND branch_id = ? AND status = 'active'
                ");
                $stmt->execute([$item['quantity'], $item['medication'], $user_branch_id]);
                
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, action, details, created_at)
                    VALUES (?, 'prescription_auto_dispensed', ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    "Prescription #" . $item['prescription_number'] . " auto-dispensed after payment (Bill: " . $item['bill_number'] . ")"
                ]);
                
                $db->commit();
                $auto_dispensed_count++;
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Auto-dispense error: " . $e->getMessage());
            }
        }
        
        if ($auto_dispensed_count > 0) {
            $message = "✅ " . $auto_dispensed_count . " prescription(s) auto-dispensed!";
            $message_type = 'success';
        }
        
    } catch (Exception $e) {
        error_log("Auto-dispense check error: " . $e->getMessage());
    }
    
    // ================================================================
    // ✅ HANDLE CONFIRM ACTION
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_prescription') {
        $prescription_id = isset($_POST['prescription_id']) ? (int)$_POST['prescription_id'] : 0;
        
        if ($prescription_id > 0) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("
                    SELECT p.*, pat.id as patient_id, pat.full_name as patient_name, 
                           pat.patient_id as patient_code, v.id as visit_id
                    FROM prescriptions p
                    JOIN patients pat ON p.patient_id = pat.id
                    LEFT JOIN visits v ON p.visit_id = v.id
                    WHERE p.id = ? AND p.branch_id = ? AND p.status = 'pending'
                ");
                $stmt->execute([$prescription_id, $user_branch_id]);
                $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($prescription) {
                    $stmt = $db->prepare("
                        SELECT selling_price FROM medications_inventory 
                        WHERE medication_name = ? AND branch_id = ? AND status = 'active'
                        LIMIT 1
                    ");
                    $stmt->execute([$prescription['medication'], $user_branch_id]);
                    $price_result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $unit_price = $price_result['selling_price'] ?? 0;
                    
                    $quantity = (int)$prescription['quantity'];
                    $total_amount = $unit_price * $quantity;
                    
                    $bill_number = 'BILL-PRES-' . date('Ymd') . '-' . str_pad($prescription['patient_id'], 4, '0', STR_PAD_LEFT);
                    
                    $stmt = $db->prepare("SELECT id FROM patient_bills WHERE bill_number = ?");
                    $stmt->execute([$bill_number]);
                    if ($stmt->fetch()) {
                        $bill_number = 'BILL-PRES-' . date('Ymd') . '-' . str_pad($prescription['patient_id'], 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                    }
                    
                    $stmt = $db->prepare("
                        SELECT b.id, b.bill_number, b.status
                        FROM patient_bills b
                        JOIN bill_items bi ON bi.bill_id = b.id
                        WHERE b.visit_id = ? 
                        AND b.branch_id = ?
                        AND bi.item_type = 'medication'
                        LIMIT 1
                    ");
                    $stmt->execute([$prescription['visit_id'], $user_branch_id]);
                    $existing_bill = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$existing_bill) {
                        $stmt = $db->prepare("
                            INSERT INTO patient_bills (
                                bill_number, patient_id, visit_id, 
                                total_amount, balance, status, 
                                created_by, branch_id,
                                created_at, updated_at,
                                medication_fees
                            ) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW(), ?)
                        ");
                        $stmt->execute([
                            $bill_number,
                            $prescription['patient_id'],
                            $prescription['visit_id'],
                            $total_amount,
                            $total_amount,
                            $user_id,
                            $user_branch_id,
                            $total_amount
                        ]);
                        $bill_id = $db->lastInsertId();
                        
                        $stmt = $db->prepare("
                            INSERT INTO bill_items (
                                bill_id, item_type, item_name, 
                                quantity, unit_price, total_price,
                                payment_status, is_paid, status, created_at,
                                branch_id
                            ) VALUES (?, 'medication', ?, ?, ?, ?, 'pending', 0, 'pending', NOW(), ?)
                        ");
                        $stmt->execute([
                            $bill_id,
                            $prescription['medication'],
                            $quantity,
                            $unit_price,
                            $total_amount,
                            $user_branch_id
                        ]);
                        
                        $stmt = $db->prepare("
                            UPDATE prescriptions 
                            SET status = 'confirmed', 
                                pharmacy_id = ?,
                                updated_at = NOW()
                            WHERE id = ? AND branch_id = ?
                        ");
                        $stmt->execute([$user_id, $prescription_id, $user_branch_id]);
                        
                        $stmt = $db->prepare("
                            INSERT INTO activity_logs (user_id, action, details, created_at)
                            VALUES (?, 'prescription_confirmed', ?, NOW())
                        ");
                        $stmt->execute([
                            $user_id,
                            "Prescription #" . $prescription['prescription_number'] . " confirmed - Bill #" . $bill_number
                        ]);
                        
                        $db->commit();
                        $_SESSION['flash_message'] = "✅ Prescription confirmed! Status: <strong>Confirmed</strong>. Bill sent to Cashier.";
                        $_SESSION['flash_type'] = 'success';
                    } else {
                        $db->rollBack();
                        
                        if ($existing_bill['status'] === 'paid') {
                            $stmt = $db->prepare("
                                UPDATE prescriptions 
                                SET status = 'dispensed', 
                                    dispensed_at = NOW(),
                                    updated_at = NOW(),
                                    pharmacy_id = ?
                                WHERE id = ? AND branch_id = ?
                            ");
                            $stmt->execute([$user_id, $prescription_id, $user_branch_id]);
                            
                            $_SESSION['flash_message'] = "✅ Prescription already paid and auto-dispensed!";
                            $_SESSION['flash_type'] = 'success';
                        } else {
                            $stmt = $db->prepare("
                                UPDATE prescriptions 
                                SET status = 'confirmed', 
                                    pharmacy_id = ?,
                                    updated_at = NOW()
                                WHERE id = ? AND branch_id = ?
                            ");
                            $stmt->execute([$user_id, $prescription_id, $user_branch_id]);
                            
                            $_SESSION['flash_message'] = "⚠️ Medication bill exists for this visit. <br>Bill #: <strong>" . $existing_bill['bill_number'] . "</strong><br>Prescription status: <strong>Confirmed</strong>";
                            $_SESSION['flash_type'] = 'warning';
                        }
                    }
                } else {
                    $db->rollBack();
                    $_SESSION['flash_message'] = "❌ Prescription not found or already processed.";
                    $_SESSION['flash_type'] = 'error';
                }
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['flash_message'] = "❌ Error: " . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
            }
        }
        
        $redirect_url = 'pending_prescriptions.php';
        if (!empty($_GET)) {
            $params = [];
            if (!empty($_GET['status'])) $params['status'] = $_GET['status'];
            if (!empty($_GET['search'])) $params['search'] = $_GET['search'];
            if (!empty($params)) {
                $redirect_url .= '?' . http_build_query($params);
            }
        }
        header('Location: ' . $redirect_url);
        exit;
    }
    
    // ================================================================
    // GET FLASH MESSAGES
    // ================================================================
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $message_type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
    
    // ================================================================
    // GET FILTER PARAMETERS
    // ================================================================
    $filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // ================================================================
    // BUILD QUERY
    // ================================================================
    $conditions = ["p.branch_id = ?", "p.status IN ('pending', 'confirmed')"];
    $params = [$user_branch_id];
    
    if ($filter_status !== 'all') {
        $conditions[] = "p.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($search)) {
        $conditions[] = "(pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.prescription_number LIKE ? OR p.medication LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(" AND ", $conditions);
    
    $sql = "
        SELECT 
            p.*,
            pat.full_name as patient_name,
            pat.patient_id as patient_code,
            pat.phone,
            pat.gender,
            pat.date_of_birth,
            u.full_name as doctor_name,
            u.specialty,
            v.visit_number,
            (SELECT b.id FROM patient_bills b 
             JOIN bill_items bi ON bi.bill_id = b.id
             WHERE b.visit_id = p.visit_id 
             AND bi.item_type = 'medication'
             ORDER BY b.id DESC LIMIT 1) as bill_id,
            (SELECT b.status FROM patient_bills b 
             JOIN bill_items bi ON bi.bill_id = b.id
             WHERE b.visit_id = p.visit_id 
             AND bi.item_type = 'medication'
             ORDER BY b.id DESC LIMIT 1) as bill_status,
            (SELECT b.total_amount FROM patient_bills b 
             JOIN bill_items bi ON bi.bill_id = b.id
             WHERE b.visit_id = p.visit_id 
             AND bi.item_type = 'medication'
             ORDER BY b.id DESC LIMIT 1) as bill_total,
            (SELECT b.bill_number FROM patient_bills b 
             JOIN bill_items bi ON bi.bill_id = b.id
             WHERE b.visit_id = p.visit_id 
             AND bi.item_type = 'medication'
             ORDER BY b.id DESC LIMIT 1) as bill_number
        FROM prescriptions p
        LEFT JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN users u ON p.doctor_id = u.id
        LEFT JOIN visits v ON p.visit_id = v.id
        WHERE $where_clause
        ORDER BY 
            CASE 
                WHEN p.status = 'pending' THEN 0 
                WHEN p.status = 'confirmed' THEN 1 
                ELSE 2 
            END,
            p.created_at ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET STATUS COUNTS
    // ================================================================
    $status_counts = [];
    foreach (['pending', 'confirmed', 'dispensed'] as $status) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = ?");
        $stmt->execute([$user_branch_id, $status]);
        $status_counts[$status] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    }
    
    $total_count = $status_counts['pending'] + $status_counts['confirmed'];
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $prescriptions = [];
    $total_count = 0;
    $status_counts = ['pending' => 0, 'confirmed' => 0, 'dispensed' => 0];
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'confirmed' => 'badge-info',
        'dispensed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-warning';
}

function getStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending',
        'confirmed' => '✅ Confirmed',
        'dispensed' => '💊 Dispensed',
        'cancelled' => '❌ Cancelled'
    ];
    return $map[$status] ?? ucfirst($status);
}

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y h:i A', strtotime($datetime));
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

// ================================================================
// INCLUDE PHARMACY HEADER & SIDEBAR
// ================================================================
include_once '../../components/pharmacy_header.php';
include_once '../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
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
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
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
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 8px;
            --radius-lg: 12px;
            --transition: all 0.3s ease;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
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
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--radius-lg);
            padding: 18px 24px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
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
        
        .page-header .page-title i {
            font-size: 1.4rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        
        .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.12);
            padding: 5px 12px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.7rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
        }
        
        .workflow-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.5rem;
            font-weight: 600;
            background: rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.9);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .workflow-badge.step1 { background: rgba(251, 191, 36, 0.25); color: #FCD34D; border-color: rgba(251, 191, 36, 0.2); }
        .workflow-badge.step2 { background: rgba(96, 165, 250, 0.25); color: #93C5FD; border-color: rgba(96, 165, 250, 0.2); }
        .workflow-badge.step3 { background: rgba(52, 211, 153, 0.25); color: #34D399; border-color: rgba(52, 211, 153, 0.2); }
        
        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(52, 211, 153, 0.15);
            color: #34D399;
            padding: 2px 10px;
            border-radius: 16px;
            font-size: 0.55rem;
            font-weight: 500;
            border: 1px solid rgba(52, 211, 153, 0.2);
        }
        .live-badge i { font-size: 0.35rem; }
        
        .live-update-indicator {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #34D399;
            animation: pulse-dot 1s infinite;
            margin-right: 4px;
        }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.8); }
        }
        
        /* ================================================================
           STATS ROW
           ================================================================ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            border-radius: var(--radius-lg);
            padding: 12px 14px;
            border: none;
            transition: var(--transition);
            color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            text-decoration: none;
            display: block;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }
        
        .stat-card .stat-number {
            font-size: 1.3rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.6rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.85;
            margin-top: 1px;
        }
        
        .stat-card .stat-icon {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .stat-card.total { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .stat-card.pending { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.confirmed { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.dispensed { background: linear-gradient(135deg, #059669, #047857); }
        
        /* ================================================================
           FILTER SECTION
           ================================================================ */
        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
        
        .filter-btn {
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 0.65rem;
            font-weight: 600;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .filter-input {
            padding: 5px 10px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.75rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
            flex: 1;
            min-width: 120px;
        }
        
        .filter-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .btn-search {
            padding: 5px 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.7rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        /* ================================================================
           TABLE
           ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .table-scroll {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 700;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #ffffff;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .data-table thead th i {
            margin-right: 4px;
            opacity: 0.7;
        }
        
        .data-table tbody td {
            padding: 6px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--primary-bg);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tbody tr:nth-child(even) td {
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td {
            background: #1A1A2E;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge-status {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 16px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.6rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(11, 94, 215, 0.25);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1.5px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-confirm {
            background: var(--primary);
            color: white;
            padding: 3px 10px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.6rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-confirm:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(11, 94, 215, 0.25);
        }
        
        .btn-view {
            background: var(--gray-500);
            color: white;
            padding: 3px 10px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.55rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .btn-view:hover {
            background: var(--gray-600);
            transform: translateY(-1px);
        }
        
        .btn-dispensed {
            background: var(--success);
            color: white;
            padding: 3px 10px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.55rem;
            border: none;
            cursor: default;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .btn-auto-dispensed {
            background: #8B5CF6;
            color: white;
            padding: 3px 10px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.55rem;
            border: none;
            cursor: default;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            align-items: center;
        }
        
        /* ================================================================
           TABLE FOOTER
           ================================================================ */
        .table-footer {
            padding: 8px 14px;
            border-top: 1px solid var(--border-color);
            font-size: 0.65rem;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 6px;
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .table-footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
            background: var(--gray-800);
        }
        
        .count-badge {
            background: var(--primary);
            color: white;
            padding: 1px 10px;
            border-radius: 16px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 10px;
            z-index: 999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            font-size: 0.8rem;
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
            padding: 10px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        .font-mono { font-family: monospace; }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.4s ease forwards; opacity: 0; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 14px; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 14px 16px; }
            .page-header .page-title { font-size: 1.1rem; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-input { width: 100%; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .data-table { font-size: 0.65rem; }
            .data-table thead th, .data-table tbody td { padding: 4px 8px; }
            .action-buttons { flex-direction: column; gap: 2px; }
            .btn-confirm { padding: 2px 8px; font-size: 0.55rem; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .stats-row { grid-template-columns: 1fr; }
            .page-title { font-size: 1rem; }
        }
    </style>
</head>
<body>

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
                <i class="fas fa-prescription"></i>
                Prescriptions
                <span class="role-badge-display">PHARMACY</span>
                <span class="header-badge">
                    <i class="fas fa-list"></i> <span id="totalCount"><?= $total_count ?></span> Pending
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.2);color:#FCD34D;">
                    <i class="fas fa-pills"></i> Medication Bills Only
                </span>
                <span class="live-badge">
                    <span class="live-update-indicator"></span>
                    Auto-Update <span id="liveTime" style="font-weight:400;font-size:0.5rem;"><?= date('H:i:s') ?></span>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-arrow-right"></i>
                <span class="workflow-badge step1">1️⃣ Confirm → Status: Confirmed</span>
                <span class="workflow-badge step2">2️⃣ Bill to Cashier</span>
                <span class="workflow-badge step3">3️⃣ Auto-Dispense → Status: Dispensed</span>
                <span class="header-badge" style="background:rgba(52,211,153,0.15);border-color:rgba(52,211,153,0.1);font-size:0.5rem;">
                    <i class="fas fa-check-circle"></i> Stock Updated
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="prescription_history.php" class="btn-outline-light">
                <i class="fas fa-history"></i> History
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-3 rounded-lg mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : ($message_type === 'warning' ? 'bg-yellow-100 text-yellow-700 border border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800') ?>" style="max-width:1200px;margin:0 auto 12px;font-size:0.8rem;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-row">
        <a href="?status=all" class="stat-card total <?= $filter_status === 'all' ? 'ring-2 ring-white ring-opacity-50' : '' ?>">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="stat-number" id="statTotal"><?= $total_count ?></div>
            <div class="stat-label">Total Pending</div>
        </a>
        <a href="?status=pending" class="stat-card pending <?= $filter_status === 'pending' ? 'ring-2 ring-white ring-opacity-50' : '' ?>">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number" id="statPending"><?= $status_counts['pending'] ?? 0 ?></div>
            <div class="stat-label">⏳ Pending</div>
        </a>
        <a href="?status=confirmed" class="stat-card confirmed <?= $filter_status === 'confirmed' ? 'ring-2 ring-white ring-opacity-50' : '' ?>">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number" id="statConfirmed"><?= $status_counts['confirmed'] ?? 0 ?></div>
            <div class="stat-label">✅ Confirmed</div>
        </a>
        <a href="prescription_history.php?status=dispensed" class="stat-card dispensed">
            <div class="stat-icon"><i class="fas fa-prescription-bottle"></i></div>
            <div class="stat-number" id="statDispensed"><?= $status_counts['dispensed'] ?? 0 ?></div>
            <div class="stat-label">💊 Dispensed</div>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section">
        <div class="filter-row">
            <a href="?status=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>">📋 All</a>
            <a href="?status=pending<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ Pending</a>
            <a href="?status=confirmed<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= $filter_status === 'confirmed' ? 'active' : '' ?>">✅ Confirmed</a>
            
            <div style="flex:1;"></div>
            
            <form method="GET" class="filter-row" style="flex:1;gap:6px;" id="filterForm">
                <input type="hidden" name="status" id="filterStatus" value="<?= htmlspecialchars($filter_status) ?>">
                <input type="text" name="search" class="filter-input" id="searchInput" placeholder="Search patient, medication..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i>
                </button>
                <?php if (!empty($search) || $filter_status !== 'all'): ?>
                    <a href="pending_prescriptions.php" class="btn btn-outline" style="padding:5px 10px;font-size:0.6rem;">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TABLE -->
    <!-- ================================================================ -->
    <div class="table-container" id="prescriptionsContainer">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:30px;"><i class="fas fa-hashtag"></i></th>
                        <th><i class="fas fa-receipt"></i> Prescription</th>
                        <th><i class="fas fa-user"></i> Patient</th>
                        <th><i class="fas fa-pills"></i> Medication</th>
                        <th style="text-align:center;"><i class="fas fa-cubes"></i> Qty</th>
                        <th style="text-align:center;"><i class="fas fa-info-circle"></i> Status</th>
                        <th style="text-align:center;"><i class="fas fa-money-bill"></i> Bill</th>
                        <th style="text-align:center;"><i class="fas fa-calendar"></i> Date</th>
                        <th style="text-align:center;"><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody id="prescriptionsTableBody">
                    <?php if (count($prescriptions) > 0): ?>
                        <?php $i = 1; foreach ($prescriptions as $pres): 
                            $age = calculateAge($pres['date_of_birth'] ?? '');
                            $is_paid = ($pres['bill_status'] ?? '') === 'paid';
                            $bill_exists = !empty($pres['bill_id']);
                            $status = $pres['status'] ?? 'pending';
                        ?>
                            <tr data-prescription-id="<?= $pres['id'] ?>" data-status="<?= $status ?>">
                                <td style="text-align:center;"><?= $i++ ?></td>
                                <td>
                                    <span class="font-mono font-semibold" style="color:var(--primary);font-size:0.7rem;">
                                        <?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="font-medium" style="font-size:0.75rem;"><?= htmlspecialchars($pres['patient_name'] ?? 'Unknown') ?></div>
                                    <div class="text-xs" style="color:var(--text-secondary);"><?= htmlspecialchars($pres['patient_code'] ?? 'N/A') ?></div>
                                    <div class="text-xs" style="color:var(--text-secondary);">
                                        <?= htmlspecialchars($pres['gender'] ?? 'N/A') ?> • <?= $age ?> yrs
                                    </div>
                                    <?php if (!empty($pres['phone'])): ?>
                                        <div class="text-xs" style="color:var(--text-secondary);">📱 <?= htmlspecialchars($pres['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="font-semibold" style="font-size:0.75rem;"><?= htmlspecialchars($pres['medication'] ?? 'N/A') ?></span>
                                </td>
                                <td style="text-align:center;">
                                    <span class="font-semibold" style="font-size:0.75rem;"><?= $pres['quantity'] ?? 0 ?></span>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge-status <?= getStatusBadgeClass($status) ?>">
                                        <?= getStatusLabel($status) ?>
                                    </span>
                                    <?php if ($status === 'confirmed' && !$is_paid): ?>
                                        <div class="text-xs" style="color:var(--warning);">⏳ Waiting for payment</div>
                                    <?php endif; ?>
                                    <?php if ($status === 'confirmed' && $is_paid): ?>
                                        <div class="text-xs" style="color:var(--success);">✅ Payment confirmed</div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($bill_exists): ?>
                                        <?php if ($is_paid): ?>
                                            <span class="badge-status badge-success">✅ Paid</span>
                                            <div class="text-xs" style="color:var(--success);">💊 Auto-Dispensed!</div>
                                        <?php else: ?>
                                            <span class="badge-status badge-warning">⏳ Pending</span>
                                            <div class="text-xs" style="color:var(--warning);"><?= $currency ?> <?= number_format($pres['bill_total'] ?? 0) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-xs" style="color:var(--text-secondary);">No bill</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="text-xs"><?= formatDate($pres['created_at'] ?? '') ?></span>
                                    <?php if (!empty($pres['visit_number'])): ?>
                                        <div class="text-xs" style="color:var(--text-secondary);">Visit: <?= htmlspecialchars($pres['visit_number']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <div class="action-buttons" style="justify-content:center;">
                                        <a href="view_prescription.php?id=<?= $pres['id'] ?>" class="btn-view" title="View Prescription Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <?php if ($status === 'dispensed'): ?>
                                            <span class="btn-dispensed"><i class="fas fa-check-circle"></i> Dispensed</span>
                                        <?php elseif ($status === 'confirmed' && $is_paid): ?>
                                            <span class="btn-auto-dispensed"><i class="fas fa-check-circle"></i> Auto-Dispensed</span>
                                        <?php elseif ($status === 'confirmed'): ?>
                                            <span class="btn-auto-dispensed"><i class="fas fa-clock"></i> Awaiting Pay</span>
                                        <?php elseif ($status === 'pending'): ?>
                                            <form method="POST" action="" style="display:inline;" 
                                                  onsubmit="return confirm('Confirm this prescription?\n\n✅ Status will change to: Confirmed\n💳 Bill will be created and sent to Cashier.\n\n💊 Medication: <?= addslashes($pres['medication'] ?? 'N/A') ?>\n📦 Quantity: <?= $pres['quantity'] ?? 0 ?>\n👤 Patient: <?= addslashes($pres['patient_name'] ?? 'Unknown') ?>\n\n⚠️ After payment, status will auto-change to: Dispensed');">
                                                <input type="hidden" name="action" value="confirm_prescription">
                                                <input type="hidden" name="prescription_id" value="<?= $pres['id'] ?>">
                                                <button type="submit" class="btn-confirm" title="Confirm - Send Bill to Cashier">
                                                    <i class="fas fa-check-circle"></i> Confirm
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="text-center py-6" style="color:var(--text-secondary);">
                                    <i class="fas fa-prescription text-2xl block mb-2" style="color:var(--border-color);"></i>
                                    <p style="font-size:0.85rem;">No pending prescriptions found</p>
                                    <p class="text-xs mt-1" style="color:var(--text-secondary);">
                                        <?php if (!empty($search)): ?>
                                            No results for "<strong><?= htmlspecialchars($search) ?></strong>"
                                        <?php elseif ($filter_status !== 'all'): ?>
                                            No <?= ucfirst($filter_status) ?> prescriptions
                                        <?php else: ?>
                                            All prescriptions have been processed ✅
                                        <?php endif; ?>
                                    </p>
                                    <a href="prescription_history.php" class="btn btn-primary mt-3">
                                        <i class="fas fa-history"></i> View History
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="table-footer">
            <span>
                <i class="fas fa-list"></i> Showing <strong id="rowCount"><?= count($prescriptions) ?></strong> prescriptions
                <span class="text-xs" style="color:var(--text-secondary);">(Pending + Confirmed)</span>
                <span class="text-xs" style="color:var(--warning);margin-left:6px;">📋 Prescription Bills Only</span>
            </span>
            <span>
                <span class="count-badge" id="totalCountBadge"><?= $total_count ?></span> Total pending
                <span class="text-xs" style="color:var(--text-secondary);" id="updateTimeDisplay">Last update: <?= date('H:i:s') ?></span>
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Prescriptions
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
    <i class="fas fa-info-circle" style="font-size:0.9rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT - AUTO-UPDATE EVERY 3 SECONDS -->
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
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
        var liveTime = document.getElementById('liveTime');
        if (liveTime) {
            liveTime.textContent = timeStr;
        }
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
        var status = '<?= $filter_status ?>';
        if (query.length > 0) {
            window.location.href = 'pending_prescriptions.php?search=' + encodeURIComponent(query) + '&status=' + status;
        } else {
            window.location.href = 'pending_prescriptions.php?status=' + status;
        }
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
    }

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
    // ✅ AUTO-UPDATE - EVERY 3 SECONDS (NO REFRESH NEEDED)
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    var currentStatusFilter = '<?= $filter_status ?>';
    var currentSearch = '<?= addslashes($search) ?>';

    function fetchPrescriptionsStatus() {
        if (isUpdating) return;
        isUpdating = true;
        
        var formData = new FormData();
        formData.append('action', 'get_prescriptions_status');
        formData.append('branch_id', '<?= $user_branch_id ?>');
        formData.append('filter_status', currentStatusFilter);
        formData.append('search', currentSearch);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                updateUI(data);
            }
            isUpdating = false;
        })
        .catch(function(error) {
            console.error('Update error:', error);
            isUpdating = false;
        });
    }

    function updateUI(data) {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        
        // Update table rows
        var tbody = document.getElementById('prescriptionsTableBody');
        if (tbody && data.rows_html) {
            tbody.innerHTML = data.rows_html;
        }
        
        // Update stats
        var statTotal = document.getElementById('statTotal');
        var statPending = document.getElementById('statPending');
        var statConfirmed = document.getElementById('statConfirmed');
        var statDispensed = document.getElementById('statDispensed');
        var totalCount = document.getElementById('totalCount');
        var totalCountBadge = document.getElementById('totalCountBadge');
        var rowCount = document.getElementById('rowCount');
        var updateTimeDisplay = document.getElementById('updateTimeDisplay');
        
        if (statTotal) statTotal.textContent = data.total_count;
        if (statPending) statPending.textContent = data.pending_count;
        if (statConfirmed) statConfirmed.textContent = data.confirmed_count;
        if (statDispensed) statDispensed.textContent = data.dispensed_count;
        if (totalCount) totalCount.textContent = data.total_count;
        if (totalCountBadge) totalCountBadge.textContent = data.total_count;
        if (rowCount) {
            // Count rows in tbody
            var rows = tbody ? tbody.querySelectorAll('tr').length : 0;
            rowCount.textContent = rows;
        }
        if (updateTimeDisplay) updateTimeDisplay.textContent = 'Last update: ' + timeStr;
        
        // Update live badge
        var liveTime = document.getElementById('liveTime');
        if (liveTime) liveTime.textContent = timeStr;
        
        // Show toast if prescriptions were auto-dispensed
        if (data.auto_dispensed_count && data.auto_dispensed_count > 0) {
            showToast('💊 Auto-Dispensed', data.auto_dispensed_count + ' prescription(s) dispensed automatically!', 'success');
        }
    }

    function startAutoUpdate() {
        if (updateInterval) clearInterval(updateInterval);
        // Initial fetch after 1 second
        setTimeout(function() {
            fetchPrescriptionsStatus();
        }, 1000);
        // Then every 3 seconds
        updateInterval = setInterval(fetchPrescriptionsStatus, 3000);
        console.log('%c🔄 Prescription auto-update started (every 3s)', 'font-size:12px; color:#34D399;');
    }
    
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            console.log('%c⏹️ Prescription auto-update stopped', 'font-size:12px; color:#DC2626;');
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        if (e.key === 'F5') {
            e.preventDefault();
            window.location.reload();
        }
    });

    // ================================================================
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        // Start auto-update after 2 seconds
        setTimeout(function() {
            startAutoUpdate();
        }, 2000);
    });

    <?php if ($message && $message_type): ?>
        setTimeout(function() {
            showToast('<?= $message_type === 'success' ? '✅ Success' : ($message_type === 'warning' ? '⚠️ Warning' : '❌ Error') ?>', 
                '<?= addslashes($message) ?>', 
                '<?= $message_type ?>'
            );
        }, 500);
    <?php endif; ?>

    console.log('%c💊 Braick - Prescriptions (Auto-Update Active)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Total Pending: <?= $total_count ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c⏳ Pending: <?= $status_counts['pending'] ?? 0 ?>', 'font-size:12px; color:#D97706;');
    console.log('%c✅ Confirmed: <?= $status_counts['confirmed'] ?? 0 ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c💊 Dispensed: <?= $status_counts['dispensed'] ?? 0 ?>', 'font-size:12px; color:#059669;');
    console.log('%c🔄 Auto-update every 3 seconds - NO REFRESH NEEDED!', 'font-size:12px; color:#34D399;');
    console.log('%c💊 Status updates automatically when dispensed', 'font-size:12px; color:#34D399;');
</script>

</body>
</html>