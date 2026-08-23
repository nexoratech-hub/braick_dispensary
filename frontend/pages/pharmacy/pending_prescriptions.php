<?php
// ================================================================
// FILE: frontend/pages/pharmacy/pending_prescriptions.php
// PHARMACY - PRESCRIPTIONS (Pending, Confirmed, Dispensed)
// WITH INSTRUCTIONS EDITING (Drop-down + Manual)
// USING NEW DATABASE: dispensary_db
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT PHARMACY
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE CONNECTION - NEW DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';
$message_type = '';
$currency = 'TSh';

// ================================================================
// PRE-DEFINED INSTRUCTIONS OPTIONS
// ================================================================
$instruction_options = [
    'Take with food',
    'Take on empty stomach',
    'Take after meals',
    'Take before meals',
    'Take with plenty of water',
    'Do not crush or chew',
    'Take at bedtime',
    'Take in the morning',
    'Take with milk',
    'Avoid alcohol',
    'Avoid driving',
    'Complete full course',
    'Store in a cool dry place',
    'Keep out of reach of children',
    'As directed by doctor',
    'Other - Please specify'
];

try {
    // ================================================================
    // GET SYSTEM SETTINGS - NEW DB
    // ================================================================
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
    
    // ================================================================
    // ✅ AJAX HANDLER - Update pharmacy instructions
    // ================================================================
    if (isset($_POST['action']) && $_POST['action'] === 'update_instructions') {
        header('Content-Type: application/json');
        
        $prescription_id = isset($_POST['prescription_id']) ? (int)$_POST['prescription_id'] : 0;
        $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
        $instructions = isset($_POST['instructions']) ? trim($_POST['instructions']) : '';
        $mode = isset($_POST['mode']) ? $_POST['mode'] : 'manual';
        
        if ($prescription_id > 0 && $item_id > 0) {
            try {
                $stmt = $db->prepare("
                    UPDATE prescription_items 
                    SET pharmacy_instructions = ?,
                        pharmacy_instruction_mode = ?,
                        pharmacy_instruction_updated_at = NOW(),
                        pharmacy_instruction_updated_by = ?
                    WHERE id = ? AND prescription_id = ? AND prescription_id IN (
                        SELECT id FROM prescriptions WHERE branch_id = ? AND status IN ('pending', 'confirmed', 'dispensed')
                    )
                ");
                $stmt->execute([
                    $instructions,
                    $mode,
                    $user_id,
                    $item_id,
                    $prescription_id,
                    $user_branch_id
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Instructions updated successfully'
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid prescription or item ID'
            ]);
        }
        exit;
    }
    
    // ================================================================
    // ✅ AJAX HANDLER - Get updated prescription status
    // ================================================================
    if (isset($_POST['action']) && $_POST['action'] === 'get_prescriptions_status') {
        header('Content-Type: application/json');
        
        $branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : $user_branch_id;
        $filter_status = isset($_POST['filter_status']) ? $_POST['filter_status'] : 'all';
        $search = isset($_POST['search']) ? trim($_POST['search']) : '';
        
        // Build query for prescriptions - NEW DB
        $conditions = ["p.branch_id = ?"];
        $params = [$branch_id];
        
        // Handle all statuses including dispensed
        if ($filter_status === 'all') {
            $conditions[] = "p.status IN ('pending', 'confirmed', 'dispensed')";
        } elseif ($filter_status === 'dispensed') {
            $conditions[] = "p.status = 'dispensed'";
        } else {
            $conditions[] = "p.status = ?";
            $params[] = $filter_status;
        }
        
        if (!empty($search)) {
            $conditions[] = "(pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.prescription_number LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $where_clause = implode(" AND ", $conditions);
        
        $sql = "
            SELECT 
                p.id,
                p.prescription_number,
                p.total_amount,
                p.status,
                p.created_at,
                p.dispensed_at,
                pat.id as patient_id,
                pat.full_name as patient_name,
                pat.patient_id as patient_code,
                pat.phone,
                pat.gender,
                pat.date_of_birth,
                u.full_name as doctor_name,
                u.specialty,
                v.visit_number,
                ph.full_name as pharmacy_name,
                -- Get bill info from bills table
                b.id as bill_id,
                b.bill_number,
                b.total_amount as bill_total,
                b.discount_amount as bill_discount,
                b.balance as bill_balance,
                b.status as bill_status
            FROM prescriptions p
            JOIN patients pat ON p.patient_id = pat.id
            LEFT JOIN users u ON p.doctor_id = u.id
            LEFT JOIN visits v ON p.visit_id = v.id
            LEFT JOIN users ph ON p.pharmacy_id = ph.id
            LEFT JOIN bills b ON b.visit_id = p.visit_id AND b.patient_id = p.patient_id
            WHERE $where_clause
            GROUP BY p.id
            ORDER BY 
                CASE 
                    WHEN p.status = 'pending' THEN 0 
                    WHEN p.status = 'confirmed' THEN 1 
                    WHEN p.status = 'dispensed' THEN 2 
                    ELSE 3 
                END,
                p.created_at DESC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get status counts - NEW DB
        $status_counts = [];
        foreach (['pending', 'confirmed', 'dispensed'] as $status) {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = ?");
            $stmt->execute([$branch_id, $status]);
            $status_counts[$status] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        }
        
        $total_count = $status_counts['pending'] + $status_counts['confirmed'] + $status_counts['dispensed'];
        
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
                $is_dispensed = ($status === 'dispensed');
                
                // Get prescription items with instructions
                $stmt_items = $db->prepare("
                    SELECT 
                        pi.*,
                        pi.instructions as doctor_instructions,
                        pi.pharmacy_instructions,
                        pi.pharmacy_instruction_mode,
                        pi.pharmacy_instruction_updated_at,
                        pu.full_name as instruction_updated_by_name
                    FROM prescription_items pi
                    LEFT JOIN users pu ON pi.pharmacy_instruction_updated_by = pu.id
                    WHERE pi.prescription_id = ?
                ");
                $stmt_items->execute([$pres['id']]);
                $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                
                $medication_list = '';
                $total_qty = 0;
                $item_data = [];
                foreach ($items as $item) {
                    $medication_list .= '<div class="text-xs">' . htmlspecialchars($item['medication_name']) . 
                                       ' (' . $item['quantity'] . ')</div>';
                    $total_qty += $item['quantity'];
                    $item_data[] = $item;
                }
                if (empty($medication_list)) {
                    $medication_list = '<span class="text-xs text-gray-400">No items</span>';
                }
                
                // Build instructions HTML for each item - FIXED: Removed syntax errors
                $instructions_html = '';
                foreach ($item_data as $idx => $item) {
                    $item_id = $item['id'];
                    $doctor_instr = $item['doctor_instructions'] ?? '';
                    $pharmacy_instr = $item['pharmacy_instructions'] ?? '';
                    $has_pharmacy_instr = !empty($pharmacy_instr);
                    $updated_by = $item['instruction_updated_by_name'] ?? '';
                    $updated_at = !empty($item['pharmacy_instruction_updated_at']) ? formatDate($item['pharmacy_instruction_updated_at']) : '';
                    
                    $instructions_html .= '
                    <div class="instruction-item mb-2 p-2 border rounded" style="border-color:var(--border-color);background:var(--bg-body);">
                        <div class="flex items-center justify-between flex-wrap gap-1">
                            <span class="font-medium text-xs">' . htmlspecialchars($item['medication_name']) . '</span>
                            <span class="text-xs text-gray-400">Qty: ' . $item['quantity'] . '</span>
                        </div>
                        
                        <!-- Doctor Instructions (Read-only) -->
                        <div class="doctor-instructions mt-1 p-1 bg-blue-50 dark:bg-blue-900/20 rounded" style="border-left:3px solid var(--primary);">
                            <span class="text-xs font-semibold" style="color:var(--primary);">
                                <i class="fas fa-user-md"></i> Doctor\'s Instructions:
                            </span>
                            <span class="text-xs" style="color:var(--text-secondary);">
                                ' . (!empty($doctor_instr) ? nl2br(htmlspecialchars($doctor_instr)) : '<em>No instructions from doctor</em>') . '
                            </span>
                        </div>
                        
                        <!-- Pharmacy Instructions -->
                        <div class="pharmacy-instructions mt-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-semibold" style="color:var(--success);">
                                    <i class="fas fa-prescription-bottle"></i> Pharmacy Instructions:
                                </span>';
                                
                    if ($is_dispensed) {
                        $instructions_html .= '
                                <span class="text-xs text-green-600">' . ($has_pharmacy_instr ? '✅ Added' : 'Not added') . '</span>';
                    } else {
                        $instructions_html .= '
                                <span class="text-xs text-gray-400" id="instr_status_' . $item_id . '">
                                    ' . ($has_pharmacy_instr ? '✅ Added' : '⏳ Not added') . '
                                </span>';
                    }
                    
                    $instructions_html .= '
                                ' . (!empty($updated_by) ? '<span class="text-xs text-gray-400">by ' . htmlspecialchars($updated_by) . '</span>' : '') . '
                                ' . (!empty($updated_at) ? '<span class="text-xs text-gray-400">' . $updated_at . '</span>' : '') . '
                            </div>
                            
                            <!-- Current Pharmacy Instructions Display -->
                            <div class="mt-1" id="instr_display_' . $item_id . '">
                                ' . ($has_pharmacy_instr ? 
                                    '<div class="text-xs p-1 bg-green-50 dark:bg-green-900/20 rounded" style="border-left:3px solid var(--success);">
                                        <span style="color:var(--text-primary);">' . nl2br(htmlspecialchars($pharmacy_instr)) . '</span>
                                    </div>' : 
                                    '<div class="text-xs text-gray-400 italic">No pharmacy instructions added yet</div>'
                                ) . '
                            </div>';
                    
                    // Edit Form - Only for non-dispensed
                    if (!$is_dispensed) {
                        $instructions_html .= '
                            <div class="mt-1 flex flex-wrap gap-1" id="instr_form_' . $item_id . '">
                                <select class="instr-select text-xs p-1 border rounded" 
                                        style="border-color:var(--border-color);background:var(--bg-card);color:var(--text-primary);min-width:160px;"
                                        onchange="toggleInstructionInput(this, ' . $item_id . ')" id="instr_select_' . $item_id . '">
                                    <option value="">-- Select Instruction --</option>';
                        foreach ($instruction_options as $opt) {
                            $instructions_html .= '<option value="' . htmlspecialchars($opt) . '">' . htmlspecialchars($opt) . '</option>';
                        }
                        $instructions_html .= '
                                    <option value="__custom__">✏️ Custom...</option>
                                </select>
                                <input type="text" class="instr-input text-xs p-1 border rounded" 
                                       style="border-color:var(--border-color);background:var(--bg-card);color:var(--text-primary);flex:1;min-width:120px;display:none;"
                                       placeholder="Enter custom instructions..."
                                       id="instr_input_' . $item_id . '">
                                <button class="btn-save-instr text-xs px-2 py-1 rounded" 
                                        style="background:var(--success);color:white;border:none;cursor:pointer;"
                                        onclick="saveInstructions(' . $pres['id'] . ', ' . $item_id . ')">
                                    <i class="fas fa-save"></i> Save
                                </button>
                                <button class="btn-clear-instr text-xs px-2 py-1 rounded" 
                                        style="background:var(--danger);color:white;border:none;cursor:pointer;"
                                        onclick="clearInstructions(' . $pres['id'] . ', ' . $item_id . ')">
                                    <i class="fas fa-trash"></i> Clear
                                </button>
                            </div>';
                    }
                    
                    $instructions_html .= '
                        </div>
                    </div>';
                }
                
                $rows_html .= '
                <tr data-prescription-id="' . $pres['id'] . '" data-status="' . $status . '">
                    <td style="text-align:center;">' . $i++ . '</td>
                    <td>
                        <span class="font-mono font-semibold" style="color:var(--primary);font-size:0.7rem;">
                            ' . htmlspecialchars($pres['prescription_number'] ?? 'N/A') . '
                        </span>
                        ' . (!empty($pres['visit_number']) ? '<div class="text-xs text-gray-400">Visit: ' . htmlspecialchars($pres['visit_number']) . '</div>' : '') . '
                        ' . (!empty($pres['doctor_name']) ? '<div class="text-xs text-gray-400">Dr. ' . htmlspecialchars($pres['doctor_name']) . '</div>' : '') . '
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
                        ' . $medication_list . '
                        <div class="text-xs text-gray-400">Total: ' . $total_qty . ' items</div>
                    </td>
                    <td style="text-align:center;">
                        <span class="font-semibold" style="font-size:0.75rem;">' . $total_qty . '</span>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge-status ' . $status_badge_class . '">
                            ' . $status_label . '
                        </span>
                        ' . ($status === 'confirmed' && !$is_paid ? '<div class="text-xs" style="color:var(--warning);">⏳ Waiting for payment</div>' : '') . '
                        ' . ($status === 'confirmed' && $is_paid ? '<div class="text-xs" style="color:var(--success);">✅ Payment confirmed</div>' : '') . '
                        ' . ($status === 'dispensed' && !empty($pres['pharmacy_name']) ? '<div class="text-xs" style="color:var(--success);">by ' . htmlspecialchars($pres['pharmacy_name']) . '</div>' : '') . '
                    </td>
                    <td style="text-align:center;">
                        ' . ($bill_exists ? ($is_paid ? 
                            '<span class="badge-status badge-success">✅ Paid</span><div class="text-xs" style="color:var(--success);">💊 Auto-Dispensed!</div>' : 
                            '<span class="badge-status badge-warning">⏳ Pending</span><div class="text-xs" style="color:var(--warning);">' . $currency . ' ' . number_format($pres['bill_total'] ?? 0) . '</div>'
                        ) : '<span class="text-xs" style="color:var(--text-secondary);">No bill</span>') . '
                    </td>
                    <td style="text-align:center;">
                        <span class="text-xs">' . ($status === 'dispensed' && !empty($pres['dispensed_at']) ? formatDate($pres['dispensed_at']) : formatDate($pres['created_at'] ?? '')) . '</span>
                    </td>
                    <td style="text-align:center;">
                        <div class="action-buttons" style="justify-content:center;flex-direction:column;gap:4px;">
                            <!-- Instructions Section -->
                            <div class="instructions-section w-full" style="max-width:320px;">
                                ' . $instructions_html . '
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex flex-wrap gap-1 justify-center">
                                <a href="view_prescription.php?id=' . $pres['id'] . '" class="btn-view" title="View Prescription Details">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                ' . ($status === 'dispensed' ? 
                                    '<span class="btn-dispensed"><i class="fas fa-check-circle"></i> Dispensed</span>' :
                                    ($status === 'confirmed' && $is_paid ? 
                                        '<span class="btn-auto-dispensed"><i class="fas fa-check-circle"></i> Auto-Dispensed</span>' :
                                        ($status === 'confirmed' ? 
                                            '<span class="btn-auto-dispensed"><i class="fas fa-clock"></i> Awaiting Pay</span>' :
                                            '<form method="POST" action="" style="display:inline;" onsubmit="return confirm(\'Confirm this prescription?\\n\\n✅ Status will change to: Confirmed\\n💳 Bill will be created and sent to Cashier.\\n\\n👤 Patient: ' . addslashes($pres['patient_name'] ?? 'Unknown') . '\\n📦 Items: ' . $total_qty . '\\n\\n⚠️ After payment, status will auto-change to: Dispensed\');">
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
                        <p style="font-size:0.85rem;">No prescriptions found</p>
                        <p class="text-xs mt-1" style="color:var(--text-secondary);">
                            ' . (!empty($search) ? 'No results for "<strong>' . htmlspecialchars($search) . '</strong>"' : 
                            ($filter_status !== 'all' ? 'No ' . ucfirst($filter_status) . ' prescriptions' : 
                            'No prescriptions found')) . '
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
        // Get confirmed prescriptions with paid bills - NEW DB
        $stmt = $db->prepare("
            SELECT 
                p.id as prescription_id,
                p.patient_id,
                p.visit_id,
                p.prescription_number,
                p.total_amount,
                b.id as bill_id,
                b.bill_number
            FROM prescriptions p
            JOIN bills b ON b.visit_id = p.visit_id AND b.patient_id = p.patient_id
            WHERE p.branch_id = ? 
            AND p.status = 'confirmed'
            AND b.status = 'paid'
            AND p.id NOT IN (
                SELECT pi.prescription_id 
                FROM prescription_items pi 
                WHERE pi.prescription_id = p.id AND pi.dispensed_at IS NOT NULL
            )
            GROUP BY p.id
        ");
        $stmt->execute([$user_branch_id]);
        $to_dispense = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($to_dispense as $item) {
            try {
                $db->beginTransaction();
                
                // Get prescription items
                $stmt_items = $db->prepare("
                    SELECT * FROM prescription_items 
                    WHERE prescription_id = ?
                ");
                $stmt_items->execute([$item['prescription_id']]);
                $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($items)) {
                    $db->rollBack();
                    continue;
                }
                
                // Check stock for each item
                $stock_errors = [];
                foreach ($items as $pres_item) {
                    $stmt_stock = $db->prepare("
                        SELECT SUM(quantity) as total_available 
                        FROM medications_inventory 
                        WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0
                    ");
                    $stmt_stock->execute([$pres_item['medication_name'], $user_branch_id]);
                    $stock = $stmt_stock->fetch(PDO::FETCH_ASSOC);
                    $available = $stock['total_available'] ?? 0;
                    
                    if ($available < $pres_item['quantity']) {
                        $stock_errors[] = "{$pres_item['medication_name']} - Required: {$pres_item['quantity']}, Available: {$available}";
                    }
                }
                
                if (!empty($stock_errors)) {
                    $db->rollBack();
                    error_log("Auto-dispense stock error: " . implode(', ', $stock_errors));
                    continue;
                }
                
                // Update prescription status to dispensed
                $stmt = $db->prepare("
                    UPDATE prescriptions 
                    SET status = 'dispensed', 
                        dispensed_at = NOW(), 
                        updated_at = NOW(),
                        pharmacy_id = ?
                    WHERE id = ? AND branch_id = ?
                ");
                $stmt->execute([$user_id, $item['prescription_id'], $user_branch_id]);
                
                // Update prescription items with dispensed_at
                $stmt = $db->prepare("
                    UPDATE prescription_items 
                    SET dispensed_at = NOW(),
                        dispensed_by = ?
                    WHERE prescription_id = ?
                ");
                $stmt->execute([$user_id, $item['prescription_id']]);
                
                // Update inventory - FIFO
                foreach ($items as $pres_item) {
                    $needed = $pres_item['quantity'];
                    
                    // Get batches ordered by expiry date (earliest first)
                    $stmt_batches = $db->prepare("
                        SELECT id, medication_name, quantity, batch_number, expiry_date
                        FROM medications_inventory 
                        WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0
                        ORDER BY expiry_date ASC
                    ");
                    $stmt_batches->execute([$pres_item['medication_name'], $user_branch_id]);
                    $batches = $stmt_batches->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($batches as $batch) {
                        if ($needed <= 0) break;
                        
                        $deduct = min($needed, $batch['quantity']);
                        $new_qty = $batch['quantity'] - $deduct;
                        
                        // Update inventory
                        $stmt_update = $db->prepare("
                            UPDATE medications_inventory 
                            SET quantity = ?,
                                updated_at = NOW()
                            WHERE id = ? AND branch_id = ?
                        ");
                        $stmt_update->execute([$new_qty, $batch['id'], $user_branch_id]);
                        
                        // LOG STOCK MOVEMENT
                        $stmt_log = $db->prepare("
                            INSERT INTO stock_movements (
                                inventory_id,
                                patient_id,
                                movement_type,
                                quantity,
                                previous_stock,
                                new_stock,
                                reference_type,
                                reference_id,
                                performed_by,
                                branch_id,
                                notes,
                                created_at
                            ) VALUES (?, ?, 'out', ?, ?, ?, 'prescription', ?, ?, ?, ?, NOW())
                        ");
                        $stmt_log->execute([
                            $batch['id'],
                            $item['patient_id'],
                            $deduct,
                            $batch['quantity'],
                            $new_qty,
                            $item['prescription_id'],
                            $user_id,
                            $user_branch_id,
                            "Auto-dispensed from batch {$batch['batch_number']} - Prescription #{$item['prescription_number']}"
                        ]);
                        
                        $needed -= $deduct;
                    }
                }
                
                // Update bill items to paid
                $stmt = $db->prepare("
                    UPDATE bill_items 
                    SET status = 'paid',
                        updated_at = NOW()
                    WHERE bill_id = ? AND reference_type = 'prescription' AND reference_id = ?
                ");
                $stmt->execute([$item['bill_id'], $item['prescription_id']]);
                
                // Update bill
                $stmt = $db->prepare("
                    UPDATE bills 
                    SET status = 'paid',
                        paid_amount = total_amount,
                        balance = 0,
                        updated_at = NOW()
                    WHERE id = ? AND visit_id = ?
                ");
                $stmt->execute([$item['bill_id'], $item['visit_id']]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                    VALUES (?, ?, 'prescription_auto_dispensed', ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    $user_branch_id,
                    "Prescription #{$item['prescription_number']} auto-dispensed after payment - Bill: {$item['bill_number']}"
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
    // ✅ HANDLE CONFIRM ACTION - NEW DB
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_prescription') {
        $prescription_id = isset($_POST['prescription_id']) ? (int)$_POST['prescription_id'] : 0;
        
        if ($prescription_id > 0) {
            try {
                $db->beginTransaction();
                
                // Get prescription details - NEW DB
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
                    // Get prescription items
                    $stmt_items = $db->prepare("
                        SELECT * FROM prescription_items 
                        WHERE prescription_id = ?
                    ");
                    $stmt_items->execute([$prescription_id]);
                    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($items)) {
                        throw new Exception("No items found in this prescription");
                    }
                    
                    // Calculate total
                    $total_amount = 0;
                    foreach ($items as $item) {
                        // Get price from inventory
                        $stmt_price = $db->prepare("
                            SELECT selling_price FROM medications_inventory 
                            WHERE medication_name = ? 
                            AND branch_id = ? 
                            AND status = 'active'
                            AND quantity > 0
                            ORDER BY created_at DESC
                            LIMIT 1
                        ");
                        $stmt_price->execute([$item['medication_name'], $user_branch_id]);
                        $price_result = $stmt_price->fetch(PDO::FETCH_ASSOC);
                        $unit_price = $price_result['selling_price'] ?? 0;
                        
                        $item_total = $unit_price * $item['quantity'];
                        $total_amount += $item_total;
                        
                        // Update item unit price
                        $stmt_update = $db->prepare("
                            UPDATE prescription_items 
                            SET unit_price = ?,
                                total_price = ?
                            WHERE id = ? AND prescription_id = ?
                        ");
                        $stmt_update->execute([$unit_price, $item_total, $item['id'], $prescription_id]);
                    }
                    
                    // Check if bill already exists
                    $stmt_check = $db->prepare("
                        SELECT id, bill_number, status FROM bills 
                        WHERE visit_id = ? AND patient_id = ?
                        ORDER BY id DESC LIMIT 1
                    ");
                    $stmt_check->execute([$prescription['visit_id'], $prescription['patient_id']]);
                    $existing_bill = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$existing_bill) {
                        // Create new bill - NEW DB
                        $bill_number = 'BILL-PRES-' . date('Ymd') . '-' . str_pad($prescription['patient_id'], 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                        
                        $stmt = $db->prepare("
                            INSERT INTO bills (
                                bill_number,
                                patient_id,
                                visit_id,
                                branch_id,
                                created_by,
                                subtotal,
                                discount_amount,
                                discount_percent,
                                total_amount,
                                paid_amount,
                                balance,
                                status,
                                payment_method,
                                notes,
                                created_at,
                                updated_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'cash', ?, NOW(), NOW())
                        ");
                        
                        $stmt->execute([
                            $bill_number,
                            $prescription['patient_id'],
                            $prescription['visit_id'],
                            $user_branch_id,
                            $user_id,
                            $total_amount,
                            0,
                            0,
                            $total_amount,
                            0,
                            $total_amount,
                            "Prescription #{$prescription['prescription_number']} - Confirmed"
                        ]);
                        
                        $bill_id = $db->lastInsertId();
                        
                        // Create bill items for each prescription item
                        foreach ($items as $item) {
                            $stmt = $db->prepare("
                                INSERT INTO bill_items (
                                    bill_id,
                                    patient_id,
                                    branch_id,
                                    item_type,
                                    item_name,
                                    quantity,
                                    unit_price,
                                    total_price,
                                    discount_amount,
                                    tax_amount,
                                    final_price,
                                    reference_id,
                                    reference_type,
                                    status,
                                    created_at,
                                    updated_at
                                ) VALUES (?, ?, ?, 'medication', ?, ?, ?, ?, ?, ?, ?, ?, 'prescription', 'pending', NOW(), NOW())
                            ");
                            
                            $item_total = $item['unit_price'] * $item['quantity'];
                            $stmt->execute([
                                $bill_id,
                                $prescription['patient_id'],
                                $user_branch_id,
                                $item['medication_name'],
                                $item['quantity'],
                                $item['unit_price'],
                                $item_total,
                                0,
                                0,
                                $item_total,
                                $prescription_id
                            ]);
                        }
                    }
                    
                    // Update prescription status to confirmed
                    $stmt = $db->prepare("
                        UPDATE prescriptions 
                        SET status = 'confirmed', 
                            pharmacy_id = ?,
                            total_amount = ?,
                            updated_at = NOW()
                        WHERE id = ? AND branch_id = ?
                    ");
                    $stmt->execute([$user_id, $total_amount, $prescription_id, $user_branch_id]);
                    
                    // Log activity
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                        VALUES (?, ?, 'prescription_confirmed', ?, NOW())
                    ");
                    $stmt->execute([
                        $user_id,
                        $user_branch_id,
                        "Prescription #{$prescription['prescription_number']} confirmed - Total: " . number_format($total_amount, 2)
                    ]);
                    
                    $db->commit();
                    
                    $_SESSION['flash_message'] = "✅ Prescription confirmed! Bill sent to Cashier. Total: " . $currency . " " . number_format($total_amount, 2);
                    $_SESSION['flash_type'] = 'success';
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
    // BUILD QUERY - NEW DB (Includes Dispensed)
    // ================================================================
    $conditions = ["p.branch_id = ?"];
    $params = [$user_branch_id];
    
    // Handle all statuses including dispensed
    if ($filter_status === 'all') {
        $conditions[] = "p.status IN ('pending', 'confirmed', 'dispensed')";
    } elseif ($filter_status === 'dispensed') {
        $conditions[] = "p.status = 'dispensed'";
    } else {
        $conditions[] = "p.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($search)) {
        $conditions[] = "(pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.prescription_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(" AND ", $conditions);
    
    $sql = "
        SELECT 
            p.*,
            p.dispensed_at,
            pat.full_name as patient_name,
            pat.patient_id as patient_code,
            pat.phone,
            pat.gender,
            pat.date_of_birth,
            u.full_name as doctor_name,
            u.specialty,
            v.visit_number,
            ph.full_name as pharmacy_name,
            b.id as bill_id,
            b.bill_number,
            b.total_amount as bill_total,
            b.discount_amount as bill_discount,
            b.balance as bill_balance,
            b.status as bill_status
        FROM prescriptions p
        LEFT JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN users u ON p.doctor_id = u.id
        LEFT JOIN visits v ON p.visit_id = v.id
        LEFT JOIN users ph ON p.pharmacy_id = ph.id
        LEFT JOIN bills b ON b.visit_id = p.visit_id AND b.patient_id = p.patient_id
        WHERE $where_clause
        GROUP BY p.id
        ORDER BY 
            CASE 
                WHEN p.status = 'pending' THEN 0 
                WHEN p.status = 'confirmed' THEN 1 
                WHEN p.status = 'dispensed' THEN 2 
                ELSE 3 
            END,
            p.created_at DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get items for each prescription
    foreach ($prescriptions as &$pres) {
        $stmt_items = $db->prepare("
            SELECT 
                pi.*,
                pi.instructions as doctor_instructions,
                pi.pharmacy_instructions,
                pi.pharmacy_instruction_mode,
                pi.pharmacy_instruction_updated_at,
                pu.full_name as instruction_updated_by_name
            FROM prescription_items pi
            LEFT JOIN users pu ON pi.pharmacy_instruction_updated_by = pu.id
            WHERE pi.prescription_id = ?
        ");
        $stmt_items->execute([$pres['id']]);
        $pres['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        $total_qty = 0;
        foreach ($pres['items'] as $item) {
            $total_qty += $item['quantity'];
        }
        $pres['total_items_qty'] = $total_qty;
    }
    
    // ================================================================
    // GET STATUS COUNTS - NEW DB
    // ================================================================
    $status_counts = [];
    foreach (['pending', 'confirmed', 'dispensed'] as $status) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = ?");
        $stmt->execute([$user_branch_id, $status]);
        $status_counts[$status] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    }
    
    $total_count = $status_counts['pending'] + $status_counts['confirmed'] + $status_counts['dispensed'];
    
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
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

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
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
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
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
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
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .page-header .header-badge {
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
        
        .page-header .btn-outline-light {
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
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
        }
        
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
        
        .new-db-tag {
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.7);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.55rem;
            font-weight: 600;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.08);
            letter-spacing: 0.03em;
        }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.8); }
        }
        
        /* ================================================================
           STATS ROW - 4 CARDS
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
        
        .filter-btn.dispensed.active {
            background: var(--success);
            border-color: var(--success);
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
        
        .btn-save-instr {
            background: var(--success);
            color: white;
            padding: 2px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.6rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }
        
        .btn-save-instr:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
        }
        
        .btn-clear-instr {
            background: var(--danger);
            color: white;
            padding: 2px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.6rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }
        
        .btn-clear-instr:hover {
            background: #991B1B;
            transform: translateY(-1px);
        }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            align-items: center;
        }
        
        /* ================================================================
           INSTRUCTION STYLES
           ================================================================ */
        .instruction-item {
            transition: all 0.3s ease;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 6px 8px;
            margin-bottom: 4px;
        }
        
        .instruction-item:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .doctor-instructions {
            background: var(--primary-bg);
            border-left: 3px solid var(--primary);
            padding: 4px 8px;
            border-radius: 4px;
        }
        
        [data-theme="dark"] .doctor-instructions {
            background: #1E3A5F;
        }
        
        .pharmacy-instructions {
            margin-top: 4px;
        }
        
        .instr-select {
            padding: 2px 6px;
            font-size: 0.7rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--bg-card);
            color: var(--text-primary);
            min-height: 28px;
        }
        
        .instr-select:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        .instr-input {
            padding: 2px 6px;
            font-size: 0.7rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--bg-card);
            color: var(--text-primary);
            min-height: 28px;
            flex: 1;
            min-width: 100px;
        }
        
        .instr-input:focus {
            border-color: var(--primary);
            outline: none;
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
        
        .count-badge.dispensed {
            background: var(--success);
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
        .footer .new-db-footer {
            color: var(--success);
            font-weight: 600;
            font-size: 0.6rem;
        }
        
        .font-mono { font-family: 'Courier New', monospace; }
        
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
            .instruction-item { padding: 4px 6px; }
            .instr-select { min-width: 100px; font-size: 0.6rem; }
            .instr-input { font-size: 0.6rem; min-width: 80px; }
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
                    <i class="fas fa-list"></i> <span id="totalCount"><?= $total_count ?></span> Total
                </span>
                <span class="new-db-tag">
                    <i class="fas fa-database"></i> New DB
                </span>
                <span class="live-badge">
                    <span class="live-update-indicator"></span>
                    Auto-Update <span id="liveTime" style="font-weight:400;font-size:0.5rem;"><?= date('H:i:s') ?></span>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-arrow-right"></i>
                <span class="header-badge" style="background:rgba(251,191,36,0.15);border-color:rgba(251,191,36,0.1);font-size:0.5rem;">⏳ Pending</span>
                <span class="header-badge" style="background:rgba(96,165,250,0.15);border-color:rgba(96,165,250,0.1);font-size:0.5rem;">✅ Confirmed</span>
                <span class="header-badge" style="background:rgba(52,211,153,0.15);border-color:rgba(52,211,153,0.1);font-size:0.5rem;">💊 Dispensed</span>
                <span class="header-badge" style="background:rgba(52,211,153,0.15);border-color:rgba(52,211,153,0.1);font-size:0.5rem;">
                    <i class="fas fa-sticky-note"></i> Edit Instructions
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
    <div class="stats-row animate-fade-in-up">
        <a href="?status=all" class="stat-card total <?= $filter_status === 'all' ? 'ring-2 ring-white ring-opacity-50' : '' ?>">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="stat-number" id="statTotal"><?= $total_count ?></div>
            <div class="stat-label">📋 All</div>
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
        <a href="?status=dispensed" class="stat-card dispensed <?= $filter_status === 'dispensed' ? 'ring-2 ring-white ring-opacity-50' : '' ?>">
            <div class="stat-icon"><i class="fas fa-prescription-bottle"></i></div>
            <div class="stat-number" id="statDispensed"><?= $status_counts['dispensed'] ?? 0 ?></div>
            <div class="stat-label">💊 Dispensed</div>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section animate-fade-in-up">
        <div class="filter-row">
            <a href="?status=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>">📋 All</a>
            <a href="?status=pending<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ Pending</a>
            <a href="?status=confirmed<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= $filter_status === 'confirmed' ? 'active' : '' ?>">✅ Confirmed</a>
            <a href="?status=dispensed<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn dispensed <?= $filter_status === 'dispensed' ? 'active' : '' ?>">💊 Dispensed</a>
            
            <div style="flex:1;"></div>
            
            <form method="GET" class="filter-row" style="flex:1;gap:6px;" id="filterForm">
                <input type="hidden" name="status" id="filterStatus" value="<?= htmlspecialchars($filter_status) ?>">
                <input type="text" name="search" class="filter-input" id="searchInput" placeholder="Search patient, prescription..." value="<?= htmlspecialchars($search) ?>">
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
    <div class="table-container animate-fade-in-up" id="prescriptionsContainer">
        <div class="table-scroll">
            <table class="data-table" id="prescriptionsTable">
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
                        <th style="text-align:center;"><i class="fas fa-cog"></i> Actions &amp; Instructions</th>
                    </tr>
                </thead>
                <tbody id="prescriptionsTableBody">
                    <?php if (count($prescriptions) > 0): ?>
                        <?php $i = 1; foreach ($prescriptions as $pres): 
                            $age = calculateAge($pres['date_of_birth'] ?? '');
                            $is_paid = ($pres['bill_status'] ?? '') === 'paid';
                            $bill_exists = !empty($pres['bill_id']);
                            $status = $pres['status'] ?? 'pending';
                            $total_qty = $pres['total_items_qty'] ?? 0;
                            $is_dispensed = ($status === 'dispensed');
                            $dispensed_by = $pres['pharmacy_name'] ?? '';
                            
                            // Build medication list
                            $med_list = '';
                            foreach ($pres['items'] as $item) {
                                $med_list .= '<div class="text-xs">' . htmlspecialchars($item['medication_name']) . 
                                           ' (' . $item['quantity'] . ')</div>';
                            }
                            if (empty($med_list)) {
                                $med_list = '<span class="text-xs text-gray-400">No items</span>';
                            }
                            
                            // Build instructions HTML for each item
                            $instructions_html = '';
                            foreach ($pres['items'] as $idx => $item) {
                                $item_id = $item['id'];
                                $doctor_instr = $item['doctor_instructions'] ?? '';
                                $pharmacy_instr = $item['pharmacy_instructions'] ?? '';
                                $has_pharmacy_instr = !empty($pharmacy_instr);
                                $updated_by = $item['instruction_updated_by_name'] ?? '';
                                $updated_at = !empty($item['pharmacy_instruction_updated_at']) ? formatDate($item['pharmacy_instruction_updated_at']) : '';
                                
                                $instructions_html .= '
                                <div class="instruction-item mb-2 p-2 border rounded" style="border-color:var(--border-color);background:var(--bg-body);">
                                    <div class="flex items-center justify-between flex-wrap gap-1">
                                        <span class="font-medium text-xs">' . htmlspecialchars($item['medication_name']) . '</span>
                                        <span class="text-xs text-gray-400">Qty: ' . $item['quantity'] . '</span>
                                    </div>
                                    
                                    <!-- Doctor Instructions (Read-only) -->
                                    <div class="doctor-instructions mt-1 p-1 bg-blue-50 dark:bg-blue-900/20 rounded" style="border-left:3px solid var(--primary);">
                                        <span class="text-xs font-semibold" style="color:var(--primary);">
                                            <i class="fas fa-user-md"></i> Doctor\'s Instructions:
                                        </span>
                                        <span class="text-xs" style="color:var(--text-secondary);">
                                            ' . (!empty($doctor_instr) ? nl2br(htmlspecialchars($doctor_instr)) : '<em>No instructions from doctor</em>') . '
                                        </span>
                                    </div>
                                    
                                    <!-- Pharmacy Instructions -->
                                    <div class="pharmacy-instructions mt-2">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-xs font-semibold" style="color:var(--success);">
                                                <i class="fas fa-prescription-bottle"></i> Pharmacy Instructions:
                                            </span>';
                                            
                                if ($is_dispensed) {
                                    $instructions_html .= '
                                            <span class="text-xs text-green-600">' . ($has_pharmacy_instr ? '✅ Added' : 'Not added') . '</span>';
                                } else {
                                    $instructions_html .= '
                                            <span class="text-xs text-gray-400" id="instr_status_' . $item_id . '">
                                                ' . ($has_pharmacy_instr ? '✅ Added' : '⏳ Not added') . '
                                            </span>';
                                }
                                
                                $instructions_html .= '
                                            ' . (!empty($updated_by) ? '<span class="text-xs text-gray-400">by ' . htmlspecialchars($updated_by) . '</span>' : '') . '
                                            ' . (!empty($updated_at) ? '<span class="text-xs text-gray-400">' . $updated_at . '</span>' : '') . '
                                        </div>
                                        
                                        <!-- Current Pharmacy Instructions Display -->
                                        <div class="mt-1" id="instr_display_' . $item_id . '">
                                            ' . ($has_pharmacy_instr ? 
                                                '<div class="text-xs p-1 bg-green-50 dark:bg-green-900/20 rounded" style="border-left:3px solid var(--success);">
                                                    <span style="color:var(--text-primary);">' . nl2br(htmlspecialchars($pharmacy_instr)) . '</span>
                                                </div>' : 
                                                '<div class="text-xs text-gray-400 italic">No pharmacy instructions added yet</div>'
                                            ) . '
                                        </div>';
                                
                                // Edit Form - Only for non-dispensed
                                if (!$is_dispensed) {
                                    $instructions_html .= '
                                        <div class="mt-1 flex flex-wrap gap-1" id="instr_form_' . $item_id . '">
                                            <select class="instr-select text-xs p-1 border rounded" 
                                                    style="border-color:var(--border-color);background:var(--bg-card);color:var(--text-primary);min-width:160px;"
                                                    onchange="toggleInstructionInput(this, ' . $item_id . ')" id="instr_select_' . $item_id . '">
                                                <option value="">-- Select Instruction --</option>';
                                    foreach ($instruction_options as $opt) {
                                        $instructions_html .= '<option value="' . htmlspecialchars($opt) . '">' . htmlspecialchars($opt) . '</option>';
                                    }
                                    $instructions_html .= '
                                                <option value="__custom__">✏️ Custom...</option>
                                            </select>
                                            <input type="text" class="instr-input text-xs p-1 border rounded" 
                                                   style="border-color:var(--border-color);background:var(--bg-card);color:var(--text-primary);flex:1;min-width:120px;display:none;"
                                                   placeholder="Enter custom instructions..."
                                                   id="instr_input_' . $item_id . '">
                                            <button class="btn-save-instr text-xs px-2 py-1 rounded" 
                                                    style="background:var(--success);color:white;border:none;cursor:pointer;"
                                                    onclick="saveInstructions(' . $pres['id'] . ', ' . $item_id . ')">
                                                <i class="fas fa-save"></i> Save
                                            </button>
                                            <button class="btn-clear-instr text-xs px-2 py-1 rounded" 
                                                    style="background:var(--danger);color:white;border:none;cursor:pointer;"
                                                    onclick="clearInstructions(' . $pres['id'] . ', ' . $item_id . ')">
                                                <i class="fas fa-trash"></i> Clear
                                            </button>
                                        </div>';
                                }
                                
                                $instructions_html .= '
                                    </div>
                                </div>';
                            }
                        ?>
                            <tr data-prescription-id="<?= $pres['id'] ?>" data-status="<?= $status ?>">
                                <td style="text-align:center;"><?= $i++ ?></td>
                                <td>
                                    <span class="font-mono font-semibold" style="color:var(--primary);font-size:0.7rem;">
                                        <?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?>
                                    </span>
                                    <?php if (!empty($pres['visit_number'])): ?>
                                        <div class="text-xs text-gray-400">Visit: <?= htmlspecialchars($pres['visit_number']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($pres['doctor_name'])): ?>
                                        <div class="text-xs text-gray-400">Dr. <?= htmlspecialchars($pres['doctor_name']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($is_dispensed && !empty($dispensed_by)): ?>
                                        <div class="text-xs text-green-600">Dispensed by: <?= htmlspecialchars($dispensed_by) ?></div>
                                    <?php endif; ?>
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
                                    <?= $med_list ?>
                                    <div class="text-xs text-gray-400">Total: <?= $total_qty ?> items</div>
                                </td>
                                <td style="text-align:center;">
                                    <span class="font-semibold" style="font-size:0.75rem;"><?= $total_qty ?></span>
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
                                    <?php if ($is_dispensed && !empty($pres['dispensed_at'])): ?>
                                        <div class="text-xs" style="color:var(--success);">📅 <?= formatDate($pres['dispensed_at']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($bill_exists): ?>
                                        <?php if ($is_paid): ?>
                                            <span class="badge-status badge-success">✅ Paid</span>
                                            <?php if (!$is_dispensed): ?>
                                                <div class="text-xs" style="color:var(--success);">💊 Auto-Dispensed!</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge-status badge-warning">⏳ Pending</span>
                                            <div class="text-xs" style="color:var(--warning);"><?= $currency ?> <?= number_format($pres['bill_total'] ?? 0) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-xs" style="color:var(--text-secondary);">No bill</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="text-xs"><?= ($is_dispensed && !empty($pres['dispensed_at'])) ? formatDate($pres['dispensed_at']) : formatDate($pres['created_at'] ?? '') ?></span>
                                </td>
                                <td style="text-align:center;">
                                    <div class="action-buttons" style="justify-content:center;flex-direction:column;gap:4px;">
                                        <!-- Instructions Section -->
                                        <div class="instructions-section w-full" style="max-width:320px;">
                                            <?= $instructions_html ?>
                                        </div>
                                        
                                        <!-- Action Buttons -->
                                        <div class="flex flex-wrap gap-1 justify-center">
                                            <a href="view_prescription.php?id=<?= $pres['id'] ?>" class="btn-view" title="View Prescription Details">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            
                                            <?php if ($status === 'dispensed'): ?>
                                                <span class="btn-dispensed"><i class="fas fa-check-circle"></i> Dispensed</span>
                                            <?php elseif ($status === 'confirmed' && $is_paid): ?>
                                                <span class="btn-auto-dispensed"><i class="fas fa-check-circle"></i> Auto-Dispensed</span>
                                            <?php elseif ($status === 'confirmed'): ?>
                                                <span class="btn-auto-dispensed"><i class="fas fa-clock"></i> Awaiting Pay</span>
                                            <?php else: ?>
                                                <form method="POST" action="" style="display:inline;" 
                                                      onsubmit="return confirm('Confirm this prescription?\n\n✅ Status will change to: Confirmed\n💳 Bill will be created and sent to Cashier.\n\n👤 Patient: <?= addslashes($pres['patient_name'] ?? 'Unknown') ?>\n📦 Items: <?= $total_qty ?>\n\n⚠️ After payment, status will auto-change to: Dispensed');">
                                                    <input type="hidden" name="action" value="confirm_prescription">
                                                    <input type="hidden" name="prescription_id" value="<?= $pres['id'] ?>">
                                                    <button type="submit" class="btn-confirm" title="Confirm - Send Bill to Cashier">
                                                        <i class="fas fa-check-circle"></i> Confirm
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="text-center py-6" style="color:var(--text-secondary);">
                                    <i class="fas fa-prescription text-2xl block mb-2" style="color:var(--border-color);"></i>
                                    <p style="font-size:0.85rem;">No prescriptions found</p>
                                    <p class="text-xs mt-1" style="color:var(--text-secondary);">
                                        <?php if (!empty($search)): ?>
                                            No results for "<strong><?= htmlspecialchars($search) ?></strong>"
                                        <?php elseif ($filter_status !== 'all'): ?>
                                            No <?= ucfirst($filter_status) ?> prescriptions
                                        <?php else: ?>
                                            No prescriptions found
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
                <span class="text-xs" style="color:var(--text-secondary);">
                    <?= $filter_status === 'all' ? '(All)' : '(' . ucfirst($filter_status) . ')' ?>
                </span>
                <span class="text-xs" style="color:var(--text-secondary);margin-left:6px;">
                    <i class="fas fa-sticky-note"></i> Edit instructions
                </span>
            </span>
            <span>
                <span class="count-badge <?= $filter_status === 'dispensed' ? 'dispensed' : '' ?>" id="totalCountBadge"><?= $total_count ?></span> Total
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
            <span class="new-db-footer"><i class="fas fa-database"></i> New DB</span>
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
    // ✅ AUTO-UPDATE - EVERY 3 SECONDS
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
        
        var tbody = document.getElementById('prescriptionsTableBody');
        if (tbody && data.rows_html) {
            tbody.innerHTML = data.rows_html;
        }
        
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
            var rows = tbody ? tbody.querySelectorAll('tr').length : 0;
            rowCount.textContent = rows;
        }
        if (updateTimeDisplay) updateTimeDisplay.textContent = 'Last update: ' + timeStr;
        
        var liveTime = document.getElementById('liveTime');
        if (liveTime) liveTime.textContent = timeStr;
    }

    function startAutoUpdate() {
        if (updateInterval) clearInterval(updateInterval);
        setTimeout(function() {
            fetchPrescriptionsStatus();
        }, 1000);
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
    // ✅ INSTRUCTION FUNCTIONS
    // ================================================================
    function toggleInstructionInput(select, itemId) {
        var input = document.getElementById('instr_input_' + itemId);
        if (!input) return;
        
        if (select.value === '__custom__') {
            input.style.display = 'block';
            input.focus();
        } else {
            input.style.display = 'none';
            input.value = '';
        }
    }

    function saveInstructions(prescriptionId, itemId) {
        var select = document.getElementById('instr_select_' + itemId);
        var input = document.getElementById('instr_input_' + itemId);
        var instructions = '';
        var mode = 'manual';
        
        if (select.value === '__custom__') {
            instructions = input.value.trim();
            mode = 'manual';
        } else if (select.value && select.value !== '__custom__') {
            instructions = select.value;
            mode = 'dropdown';
        } else {
            if (input && input.value.trim()) {
                instructions = input.value.trim();
                mode = 'manual';
            }
        }
        
        if (!instructions) {
            showToast('⚠️ Warning', 'Please select or enter instructions', 'warning');
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'update_instructions');
        formData.append('prescription_id', prescriptionId);
        formData.append('item_id', itemId);
        formData.append('instructions', instructions);
        formData.append('mode', mode);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('✅ Success', 'Instructions updated successfully', 'success');
                var displayDiv = document.getElementById('instr_display_' + itemId);
                if (displayDiv) {
                    displayDiv.innerHTML = '<div class="text-xs p-1 bg-green-50 dark:bg-green-900/20 rounded" style="border-left:3px solid var(--success);">' +
                        '<span style="color:var(--text-primary);">' + escapeHtml(instructions) + '</span>' +
                        '</div>';
                }
                var statusSpan = document.getElementById('instr_status_' + itemId);
                if (statusSpan) {
                    statusSpan.textContent = '✅ Added';
                }
                select.value = '';
                if (input) {
                    input.style.display = 'none';
                    input.value = '';
                }
            } else {
                showToast('❌ Error', data.message || 'Failed to save instructions', 'error');
            }
        })
        .catch(function(error) {
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
        });
    }

    function clearInstructions(prescriptionId, itemId) {
        if (!confirm('Clear pharmacy instructions for this medication?')) return;
        
        var formData = new FormData();
        formData.append('action', 'update_instructions');
        formData.append('prescription_id', prescriptionId);
        formData.append('item_id', itemId);
        formData.append('instructions', '');
        formData.append('mode', 'manual');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('✅ Success', 'Instructions cleared', 'success');
                var displayDiv = document.getElementById('instr_display_' + itemId);
                if (displayDiv) {
                    displayDiv.innerHTML = '<div class="text-xs text-gray-400 italic">No pharmacy instructions added yet</div>';
                }
                var statusSpan = document.getElementById('instr_status_' + itemId);
                if (statusSpan) {
                    statusSpan.textContent = '⏳ Not added';
                }
                var select = document.getElementById('instr_select_' + itemId);
                if (select) select.value = '';
                var input = document.getElementById('instr_input_' + itemId);
                if (input) {
                    input.style.display = 'none';
                    input.value = '';
                }
            } else {
                showToast('❌ Error', data.message || 'Failed to clear instructions', 'error');
            }
        })
        .catch(function(error) {
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML.replace(/\n/g, '<br>');
    }

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

    console.log('%c💊 Braick - Prescriptions (All Statuses + Instructions)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Using NEW DATABASE: dispensary_db', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:12px; color:#059669;');
    console.log('%c📋 Total: <?= $total_count ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c⏳ Pending: <?= $status_counts['pending'] ?? 0 ?>', 'font-size:12px; color:#D97706;');
    console.log('%c✅ Confirmed: <?= $status_counts['confirmed'] ?? 0 ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c💊 Dispensed: <?= $status_counts['dispensed'] ?? 0 ?>', 'font-size:12px; color:#059669;');
    console.log('%c🔄 Auto-update every 3 seconds', 'font-size:12px; color:#34D399;');
    console.log('%c✅ Tables: prescriptions, prescription_items, bills, bill_items, medications_inventory, stock_movements', 'font-size:12px; color:#34D399;');
</script>

</body>
</html>